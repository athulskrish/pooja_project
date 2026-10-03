<?php
/**
 * ACID Transactional Order Checkout & Payment Processing
 * Zippy - Online Food Ordering Platform
 * Demonstrates: ACID Transaction, Row-level Locking (SELECT ... FOR UPDATE),
 * OTP Generation, Multi-table 3NF Atomic Insertion
 * Student: Pooja JP | MCSP-232
 */

$pageTitle = 'Secure Checkout & Payment';
$activePage = 'cart';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/auth.php';

$currentUser = current_user();

// Saved addresses for current user
$savedAddresses = [];
if ($currentUser) {
    $savedAddresses = db_fetch_all("
        SELECT * FROM `user_addresses` 
        WHERE `user_id` = :uid 
        ORDER BY `is_default` DESC, `address_id` DESC
    ", [':uid' => $currentUser['id']]);
}

$errorMessage = null;

// Handle Checkout Form Submission (ACID Database Transaction)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_order') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Invalid or expired session token. Please try again.';
    } else {
        $rawCartJson = $_POST['cart_json'] ?? '[]';
        $cartItems = json_decode($rawCartJson, true);
        $couponCode = clean_input($_POST['coupon_code'] ?? '');
        $paymentMode = clean_input($_POST['payment_mode'] ?? 'UPI');
        $orderNotes = clean_input($_POST['order_notes'] ?? '');
        $selectedAddressId = !empty($_POST['address_id']) ? (int)$_POST['address_id'] : null;

        // Guest address input fallback
        $guestName = clean_input($_POST['guest_name'] ?? '');
        $guestPhone = clean_input($_POST['guest_phone'] ?? '');
        $guestEmail = clean_input($_POST['guest_email'] ?? '');
        $newAddressLine = clean_input($_POST['new_address_line'] ?? '');
        $newPincode = clean_input($_POST['new_pincode'] ?? '560038');

        if (empty($cartItems)) {
            $errorMessage = 'Your cart is empty. Please add items before checking out.';
        } else {
            $pdo = getDBConnection();
            if (!$pdo) {
                $errorMessage = 'Database connection failed. Cannot process checkout.';
            } else {
                try {
                    // BEGIN ACID TRANSACTION
                    $pdo->beginTransaction();

                    // Step 1: Ensure User exists (or register guest user)
                    $userId = $currentUser ? $currentUser['id'] : null;
                    if (!$userId) {
                        if (empty($guestEmail) || empty($guestName) || empty($guestPhone)) {
                            throw new Exception("Please provide your contact information (Name, Email, Phone) to place the order.");
                        }

                        // Check if guest email already exists in users table
                        $userStmt = $pdo->prepare("SELECT user_id FROM `users` WHERE LOWER(email) = LOWER(:email) LIMIT 1");
                        $userStmt->execute([':email' => $guestEmail]);
                        $existingUid = $userStmt->fetchColumn();

                        if ($existingUid) {
                            $userId = (int)$existingUid;
                        } else {
                            $dummyHash = password_hash('password123', PASSWORD_DEFAULT);
                            $regStmt = $pdo->prepare("
                                INSERT INTO `users` (`role_id`, `full_name`, `email`, `phone_number`, `password_hash`, `is_active`) 
                                VALUES (2, :name, :email, :phone, :hash, 1)
                            ");
                            $regStmt->execute([
                                ':name' => $guestName,
                                ':email' => $guestEmail,
                                ':phone' => $guestPhone,
                                ':hash' => $dummyHash
                            ]);
                            $userId = (int)$pdo->lastInsertId();
                        }
                    }

                    // Step 2: Ensure Delivery Address exists
                    if (!$selectedAddressId) {
                        if (empty($newAddressLine)) {
                            throw new Exception("Please select or enter a delivery address.");
                        }
                        $addrStmt = $pdo->prepare("
                            INSERT INTO `user_addresses` (`user_id`, `address_type`, `address_line`, `pincode`, `is_default`) 
                            VALUES (:uid, 'HOME', :line, :pin, 1)
                        ");
                        $addrStmt->execute([
                            ':uid' => $userId,
                            ':line' => $newAddressLine,
                            ':pin' => $newPincode
                        ]);
                        $selectedAddressId = (int)$pdo->lastInsertId();
                    }

                    // Step 3: Concurrency Control - Lock & Verify Stock via SELECT ... FOR UPDATE
                    $verifiedItems = [];
                    $subtotal = 0.00;
                    $maxPrepTime = 20;

                    $lockStmt = $pdo->prepare("
                        SELECT `item_id`, `item_name`, `price`, `stock_quantity`, `is_available`, `prep_time_minutes` 
                        FROM `menu_items` 
                        WHERE `item_id` = :id 
                        FOR UPDATE
                    ");

                    foreach ($cartItems as $item) {
                        $itemId = (int)($item['id'] ?? $item['item_id'] ?? 0);
                        $qty = max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));

                        $lockStmt->execute([':id' => $itemId]);
                        $dish = $lockStmt->fetch();

                        if (!$dish) {
                            throw new Exception("Dish item #{$itemId} not found in catalog.");
                        }

                        if (!$dish['is_available'] || $dish['stock_quantity'] < $qty) {
                            throw new Exception("Insufficient stock for '{$dish['item_name']}'. Only {$dish['stock_quantity']} portions remaining.");
                        }

                        $lineTotal = round((float)$dish['price'] * $qty, 2);
                        $subtotal += $lineTotal;
                        $maxPrepTime = max($maxPrepTime, (int)$dish['prep_time_minutes']);

                        $verifiedItems[] = [
                            'item_id' => $dish['item_id'],
                            'item_name' => $dish['item_name'],
                            'price' => (float)$dish['price'],
                            'qty' => $qty,
                            'line_total' => $lineTotal
                        ];
                    }

                    // Step 4: Compute final bill amounts
                    $bill = calculate_bill_totals($verifiedItems, $couponCode);
                    $taxAmount = $bill['gst_amount'];
                    $deliveryFee = $bill['delivery_fee'];
                    $discountAmount = $bill['discount'];
                    $totalAmount = $bill['final_total'];

                    // Dynamic ETA: Current time + Max Prep + 15 mins travel
                    $etaMinutes = $maxPrepTime + 15;

                    // Step 5: Insert Order Header
                    $orderStmt = $pdo->prepare("
                        INSERT INTO `orders` (
                            `user_id`, `address_id`, `subtotal`, `tax_amount`, 
                            `delivery_fee`, `discount_amount`, `total_amount`, 
                            `order_status`, `order_notes`, `placed_at`, `estimated_delivery_time`
                        ) VALUES (
                            :uid, :aid, :sub, :tax, :del, :disc, :tot, 
                            'CONFIRMED', :notes, NOW(), DATE_ADD(NOW(), INTERVAL :eta MINUTE)
                        )
                    ");
                    $orderStmt->execute([
                        ':uid' => $userId,
                        ':aid' => $selectedAddressId,
                        ':sub' => $subtotal,
                        ':tax' => $taxAmount,
                        ':del' => $deliveryFee,
                        ':disc' => $discountAmount,
                        ':tot' => $totalAmount,
                        ':notes' => $orderNotes,
                        ':eta' => $etaMinutes
                    ]);
                    $orderId = (int)$pdo->lastInsertId();

                    // Step 6: Insert Order Items & Deduct Stock
                    $itemInsertStmt = $pdo->prepare("
                        INSERT INTO `order_items` (`order_id`, `item_id`, `quantity`, `unit_price`, `line_total`) 
                        VALUES (:oid, :iid, :qty, :price, :ltot)
                    ");
                    $stockUpdateStmt = $pdo->prepare("
                        UPDATE `menu_items` 
                        SET `stock_quantity` = `stock_quantity` - :qty 
                        WHERE `item_id` = :iid
                    ");

                    foreach ($verifiedItems as $vItem) {
                        $itemInsertStmt->execute([
                            ':oid' => $orderId,
                            ':iid' => $vItem['item_id'],
                            ':qty' => $vItem['qty'],
                            ':price' => $vItem['price'],
                            ':ltot' => $vItem['line_total']
                        ]);

                        $stockUpdateStmt->execute([
                            ':qty' => $vItem['qty'],
                            ':iid' => $vItem['item_id']
                        ]);
                    }

                    // Step 7: Record Payment Entry
                    $txRef = strtoupper($paymentMode) . '-' . strtoupper(bin2hex(random_bytes(5)));
                    $payStatus = ($paymentMode === 'COD') ? 'PENDING' : 'SUCCESSFUL';

                    $payStmt = $pdo->prepare("
                        INSERT INTO `payments` (`order_id`, `payment_mode`, `transaction_reference`, `amount_paid`, `payment_status`, `payment_timestamp`) 
                        VALUES (:oid, :mode, :tx, :amt, :pstat, NOW())
                    ");
                    $payStmt->execute([
                        ':oid' => $orderId,
                        ':mode' => $paymentMode,
                        ':tx' => $txRef,
                        ':amt' => $totalAmount,
                        ':pstat' => $payStatus
                    ]);

                    // Step 8: Create Delivery Dispatch Ticket with 6-digit Secret OTP
                    $deliveryOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    $dispStmt = $pdo->prepare("
                        INSERT INTO `delivery_dispatches` (
                            `order_id`, `delivery_partner_name`, `delivery_partner_phone`, 
                            `delivery_otp`, `assigned_at`, `delivery_status`
                        ) VALUES (
                            :oid, 'Suresh Kumar (Zippy Express)', '+91 96320 98765', 
                            :otp, NOW(), 'ASSIGNED'
                        )
                    ");
                    $dispStmt->execute([
                        ':oid' => $orderId,
                        ':otp' => $deliveryOtp
                    ]);

                    // Step 9: Audit Trail Logging
                    $auditStmt = $pdo->prepare("
                        INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`) 
                        VALUES (:oid, 'PLACED', 'CONFIRMED', :uid, NOW())
                    ");
                    $auditStmt->execute([
                        ':oid' => $orderId,
                        ':uid' => $userId
                    ]);

                    // COMMIT ACID TRANSACTION
                    $pdo->commit();

                    // If user was guest, log them in
                    if (!$currentUser) {
                        $_SESSION['user_id'] = $userId;
                        $_SESSION['user_name'] = $guestName;
                        $_SESSION['user_email'] = $guestEmail;
                        $_SESSION['user_phone'] = $guestPhone;
                        $_SESSION['role_id'] = 2;
                        $_SESSION['role_name'] = 'CUSTOMER';
                    }

                    // Redirect to Success page with client script to clear cart
                    echo "<script>
                        localStorage.removeItem('pooja_zippy_cart');
                        localStorage.removeItem('pooja_active_coupon');
                        window.location.href = '" . CUSTOMER_URL . "/order-success.php?order_id=" . $orderId . "';
                    </script>";
                    exit;

                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errorMessage = "Transaction Rolled Back: " . $e->getMessage();
                }
            }
        }
    }
}
?>

<main class="py-4">
  <div class="container-xl">
    
    <div class="mb-4">
      <h2 class="fw-bold mb-0 font-outfit text-dark"><i class="bi bi-shield-lock-fill text-success me-2"></i>Checkout & Complete Payment</h2>
      <p class="text-muted small mb-0">High-concurrency ACID transactional order fulfillment</p>
    </div>

    <?php if ($errorMessage): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 mb-4 rounded-3 shadow-sm">
        <i class="bi bi-exclamation-octagon-fill fs-5"></i>
        <div><?= e($errorMessage) ?></div>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= CUSTOMER_URL ?>/checkout.php" id="checkoutForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="process_order">
      <input type="hidden" name="cart_json" id="checkoutCartJsonInput" value="[]">
      <input type="hidden" name="coupon_code" id="checkoutCouponInput" value="">

      <div class="row g-4">
        
        <!-- Left Column: Delivery Address & Payment Method -->
        <div class="col-lg-8">
          
          <!-- Section 1: Customer Contact / Authentication -->
          <?php if (!$currentUser): ?>
            <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-person-fill text-success me-2"></i>1. Contact Information</h5>
                <a href="<?= CUSTOMER_URL ?>/login.php?redirect=checkout" class="btn btn-outline-success btn-sm rounded-pill px-3">
                  Have an account? Sign In
                </a>
              </div>
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label small fw-bold">Full Name *</label>
                  <input type="text" name="guest_name" class="form-control rounded-3" placeholder="Pooja JP" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold">Email Address *</label>
                  <input type="email" name="guest_email" class="form-control rounded-3" placeholder="pooja@example.com" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold">Phone Number *</label>
                  <input type="tel" name="guest_phone" class="form-control rounded-3" placeholder="+91 98860 12345" required>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
              <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                  <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                    <i class="bi bi-person-check-fill fs-4"></i>
                  </div>
                  <div>
                    <h5 class="fw-bold text-dark mb-0">Logged in as <?= e($currentUser['name']) ?></h5>
                    <div class="small text-muted"><?= e($currentUser['email']) ?> | Phone: <?= e($_SESSION['user_phone'] ?? 'Verified') ?></div>
                  </div>
                </div>
                <span class="badge bg-success bg-opacity-25 text-success px-3 py-2 rounded-pill">Verified Account</span>
              </div>
            </div>
          <?php endif; ?>

          <!-- Section 2: Delivery Address -->
          <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-geo-alt-fill text-success me-2"></i>2. Delivery Destination</h5>
            
            <?php if (!empty($savedAddresses)): ?>
              <div class="row g-3 mb-3">
                <?php foreach ($savedAddresses as $idx => $addr): ?>
                  <div class="col-md-6">
                    <label class="card h-100 p-3 border rounded-3 address-selector-card cursor-pointer <?= $addr['is_default'] ? 'border-success bg-mint-soft' : '' ?>" style="cursor: pointer;">
                      <div class="d-flex align-items-start gap-2">
                        <input class="form-check-input mt-1" type="radio" name="address_id" value="<?= (int)$addr['address_id'] ?>" <?= ($idx === 0) ? 'checked' : '' ?>>
                        <div>
                          <span class="badge bg-dark small mb-1"><?= e($addr['address_type']) ?></span>
                          <div class="small fw-semibold text-dark"><?= e($addr['address_line']) ?></div>
                          <?php if (!empty($addr['landmark'])): ?>
                            <div class="small text-muted">Near <?= e($addr['landmark']) ?></div>
                          <?php endif; ?>
                          <div class="small text-muted">PIN: <?= e($addr['pincode']) ?></div>
                        </div>
                      </div>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="border-top pt-3">
              <a class="text-success small fw-bold text-decoration-none" data-bs-toggle="collapse" href="#newAddressCollapse" role="button">
                + Deliver to a different or new address
              </a>
              <div class="collapse <?= empty($savedAddresses) ? 'show' : '' ?> mt-3" id="newAddressCollapse">
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label small fw-bold">Street Address / Flat / Building *</label>
                    <textarea name="new_address_line" class="form-control rounded-3" rows="2" placeholder="Flat No, Apartment, Street Name, Landmark" <?= empty($savedAddresses) ? 'required' : '' ?>></textarea>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small fw-bold">Pincode (Bangalore) *</label>
                    <input type="text" name="new_pincode" class="form-control rounded-3" placeholder="560038" value="560038">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small fw-bold">City / State</label>
                    <input type="text" class="form-control rounded-3 bg-light" value="Bangalore, Karnataka" readonly>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Section 3: Delivery Instructions & Notes -->
          <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-chat-left-text-fill text-success me-2"></i>3. Cooking & Drop-off Instructions</h5>
            <input type="text" name="order_notes" class="form-control rounded-3" placeholder="E.g. Extra spicy, ring bell once, contactless leave at door">
          </div>

          <!-- Section 4: Payment Mode Selection -->
          <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-credit-card-2-front-fill text-success me-2"></i>4. Select Payment Method</h5>
            
            <div class="d-flex flex-column gap-3">
              
              <!-- UPI -->
              <label class="p-3 border rounded-3 d-flex align-items-center justify-content-between cursor-pointer payment-option-card active" style="cursor:pointer; background:#EDFBF2; border-color:#2E6F40 !important;">
                <div class="d-flex align-items-center gap-3">
                  <input class="form-check-input m-0" type="radio" name="payment_mode" value="UPI" checked>
                  <div>
                    <strong class="text-dark d-block">Instant UPI (Google Pay, PhonePe, Paytm, BHIM)</strong>
                    <span class="small text-muted">Direct instant settlement via UPI QR or VPA Intent</span>
                  </div>
                </div>
                <span class="badge bg-success rounded-pill px-2 py-1"><i class="bi bi-lightning-charge-fill"></i> FASTEST</span>
              </label>

              <!-- Cards -->
              <label class="p-3 border rounded-3 d-flex align-items-center justify-content-between cursor-pointer payment-option-card" style="cursor:pointer;">
                <div class="d-flex align-items-center gap-3">
                  <input class="form-check-input m-0" type="radio" name="payment_mode" value="CREDIT_CARD">
                  <div>
                    <strong class="text-dark d-block">Credit / Debit Card</strong>
                    <span class="small text-muted">Visa, MasterCard, RuPay & Diners Club</span>
                  </div>
                </div>
                <i class="bi bi-credit-card fs-4 text-muted"></i>
              </label>

              <!-- Net Banking -->
              <label class="p-3 border rounded-3 d-flex align-items-center justify-content-between cursor-pointer payment-option-card" style="cursor:pointer;">
                <div class="d-flex align-items-center gap-3">
                  <input class="form-check-input m-0" type="radio" name="payment_mode" value="NET_BANKING">
                  <div>
                    <strong class="text-dark d-block">Net Banking</strong>
                    <span class="small text-muted">All major Indian banks supported (HDFC, ICICI, SBI, Axis)</span>
                  </div>
                </div>
                <i class="bi bi-bank fs-4 text-muted"></i>
              </label>

              <!-- Cash on Delivery -->
              <label class="p-3 border rounded-3 d-flex align-items-center justify-content-between cursor-pointer payment-option-card" style="cursor:pointer;">
                <div class="d-flex align-items-center gap-3">
                  <input class="form-check-input m-0" type="radio" name="payment_mode" value="COD">
                  <div>
                    <strong class="text-dark d-block">Cash on Delivery (Pay upon arrival)</strong>
                    <span class="small text-muted">Pay via cash or UPI to rider upon receiving delivery</span>
                  </div>
                </div>
                <i class="bi bi-cash-stack fs-4 text-muted"></i>
              </label>

            </div>
          </div>

        </div>

        <!-- Right Column: Final Order Breakdown & Pay CTA -->
        <div class="col-lg-4">
          <div class="card border-0 rounded-4 shadow-sm p-4 bg-white sticky-top" style="top: 90px;">
            <h5 class="fw-bold text-dark border-bottom pb-3 mb-3">Order Summary</h5>
            
            <div id="checkoutSummaryItems" class="mb-3" style="max-height: 240px; overflow-y: auto;">
              <!-- Synced via JS -->
            </div>

            <!-- Bill Breakdown -->
            <div class="border-top pt-3">
              <div class="d-flex justify-content-between small text-muted mb-2">
                <span>Item Total</span>
                <span class="fw-bold text-dark" id="chkSubtotal">₹0.00</span>
              </div>
              <div class="d-flex justify-content-between small text-muted mb-2">
                <span>Delivery Fee</span>
                <span class="fw-semibold text-success" id="chkDelivery">₹0.00</span>
              </div>
              <div class="d-flex justify-content-between small text-muted mb-2">
                <span>Platform Fee</span>
                <span class="fw-semibold text-dark">₹<?= number_format(PLATFORM_FEE, 2) ?></span>
              </div>
              <div class="d-flex justify-content-between small text-muted mb-2">
                <span>Taxes & GST (5%)</span>
                <span class="fw-semibold text-dark" id="chkGst">₹0.00</span>
              </div>
              <div class="d-flex justify-content-between small text-success mb-2" id="chkDiscountRow" style="display:none !important;">
                <span>Coupon Discount</span>
                <span class="fw-bold" id="chkDiscount">-₹0.00</span>
              </div>

              <div class="border-top pt-3 mt-2 mb-4 d-flex justify-content-between align-items-center">
                <div>
                  <span class="fw-bold fs-6 text-dark d-block">FINAL AMOUNT</span>
                  <span class="small text-muted">ACID row-lock secured</span>
                </div>
                <span class="fw-bold fs-4 text-forest" id="chkTotal">₹0.00</span>
              </div>

              <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold py-3 shadow" style="background:#2E6F40; border-color:#2E6F40; font-size:1.1rem;">
                <i class="bi bi-lock-fill me-1"></i> Pay & Place Order
              </button>

              <div class="text-center small text-muted mt-3">
                <i class="bi bi-shield-check text-success me-1"></i> 256-Bit SSL Encrypted Payment Gateways
              </div>
            </div>

          </div>
        </div>

      </div>
    </form>

  </div>
</main>

<script>
// Synchronize client cart with checkout form
document.addEventListener('DOMContentLoaded', () => {
  const jsonInput = document.getElementById('checkoutCartJsonInput');
  const couponInput = document.getElementById('checkoutCouponInput');
  const itemsContainer = document.getElementById('checkoutSummaryItems');

  if (jsonInput) jsonInput.value = JSON.stringify(cart);
  if (couponInput) couponInput.value = activeCoupon || '';

  if (itemsContainer) {
    if (!cart || cart.length === 0) {
      itemsContainer.innerHTML = '<div class="text-center text-muted small py-3">No items in cart. Please return to menu.</div>';
    } else {
      let subtotal = 0;
      itemsContainer.innerHTML = '';
      cart.forEach(item => {
        subtotal += item.price * item.qty;
        const row = document.createElement('div');
        row.className = 'd-flex justify-content-between align-items-center small py-1';
        row.innerHTML = `
          <div class="text-truncate me-2">
            <span class="badge ${item.isVeg ? 'bg-success' : 'bg-danger'} px-1 me-1" style="font-size:0.6rem;">${item.qty}x</span>
            <span>${item.name}</span>
          </div>
          <span class="fw-bold">₹${(item.price * item.qty).toFixed(2)}</span>
        `;
        itemsContainer.appendChild(row);
      });

      const coupon = activeCoupon;
      let discount = 0;
      if (coupon === 'LUSHFOREST' && subtotal >= 299) {
        discount = Math.min(subtotal * 0.20, 100);
      } else if (coupon === 'ZIPPY50' && subtotal >= 399) {
        discount = Math.min(subtotal * 0.50, 120);
      } else if (coupon === 'WELCOME' && subtotal >= 199) {
        discount = 50;
      }

      const deliveryFee = subtotal >= 499 ? 0 : 40;
      const platformFee = 5;
      const taxable = Math.max(0, subtotal - discount);
      const gst = taxable * 0.05;
      const grandTotal = taxable + gst + deliveryFee + platformFee;

      document.getElementById('chkSubtotal').textContent = `₹${subtotal.toFixed(2)}`;
      document.getElementById('chkDelivery').textContent = deliveryFee === 0 ? 'FREE' : `₹${deliveryFee.toFixed(2)}`;
      document.getElementById('chkGst').textContent = `₹${gst.toFixed(2)}`;
      document.getElementById('chkTotal').textContent = `₹${grandTotal.toFixed(2)}`;

      const discRow = document.getElementById('chkDiscountRow');
      if (discount > 0) {
        discRow.style.setProperty('display', 'flex', 'important');
        document.getElementById('chkDiscount').textContent = `-₹${discount.toFixed(2)}`;
      } else {
        discRow.style.setProperty('display', 'none', 'important');
      }
    }
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
