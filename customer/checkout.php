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
        $razorpayPaymentId = clean_input($_POST['razorpay_payment_id'] ?? '');
        $orderNotes = clean_input($_POST['order_notes'] ?? '');
        $selectedAddressId = !empty($_POST['address_id']) ? (int)$_POST['address_id'] : null;

        // Advance Order Scheduling parameters
        $deliveryType = clean_input($_POST['delivery_type'] ?? 'now');
        $isScheduled = ($deliveryType === 'schedule') ? 1 : 0;
        $scheduledFor = null;
        $scheduledSlot = null;

        if ($isScheduled) {
            $scheduledDate = clean_input($_POST['scheduled_date'] ?? date('Y-m-d'));
            $scheduledTime = clean_input($_POST['scheduled_time'] ?? '13:00');
            $scheduledSlotInput = clean_input($_POST['scheduled_slot'] ?? '');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduledDate)) {
                $scheduledDate = date('Y-m-d');
            }
            if (!preg_match('/^\d{2}:\d{2}$/', $scheduledTime)) {
                $scheduledTime = '13:00';
            }

            $combinedDateTimeStr = $scheduledDate . ' ' . $scheduledTime . ':00';
            $scheduledTimestamp = strtotime($combinedDateTimeStr);

            // If scheduled time is past or within next 20 mins, ensure at least 45 mins in future
            if (!$scheduledTimestamp || $scheduledTimestamp < time() + 1200) {
                $scheduledTimestamp = strtotime('+1 hour');
            }

            $scheduledFor = date('Y-m-d H:i:s', $scheduledTimestamp);

            if (!empty($scheduledSlotInput)) {
                $scheduledSlot = $scheduledSlotInput;
            } else {
                $dateLabel = (date('Y-m-d', $scheduledTimestamp) === date('Y-m-d')) 
                    ? 'Today' 
                    : ((date('Y-m-d', $scheduledTimestamp) === date('Y-m-d', strtotime('+1 day'))) ? 'Tomorrow' : date('D, d M', $scheduledTimestamp));
                $scheduledSlot = $dateLabel . ' (' . date('h:i A', $scheduledTimestamp) . ')';
            }

            $orderNotes = trim($orderNotes . " [ADVANCE ORDER: Scheduled for {$scheduledSlot}]");
        }

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
                    $maxPrepTime = 0;

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

                        $basePrice = (float)$dish['price'];
                        $addonTotal = 0.00;
                        $customParts = [];

                        // Verify and calculate customizations if present
                        if (!empty($item['customizations']) && is_array($item['customizations'])) {
                            $optIds = [];
                            foreach ($item['customizations'] as $c) {
                                if (!empty($c['optionId'])) {
                                    $optIds[] = (int)$c['optionId'];
                                } elseif (!empty($c['option_id'])) {
                                    $optIds[] = (int)$c['option_id'];
                                }
                            }

                            if (!empty($optIds)) {
                                $inList = implode(',', array_map('intval', $optIds));
                                $validOptions = db_fetch_all("
                                    SELECT o.*, g.group_name 
                                    FROM `dish_customization_options` o
                                    JOIN `dish_customization_groups` g ON o.group_id = g.group_id
                                    WHERE o.option_id IN ({$inList}) AND g.item_id = :iid AND o.is_available = 1
                                ", [':iid' => $itemId]);

                                foreach ($validOptions as $vo) {
                                    $optPrice = (float)$vo['price'];
                                    $addonTotal += $optPrice;
                                    $priceTag = ($optPrice > 0) ? " (+₹" . number_format($optPrice, 2) . ")" : "";
                                    $customParts[] = $vo['option_name'] . $priceTag;
                                }
                            }
                        }

                        $customText = !empty($customParts) ? implode(', ', $customParts) : (!empty($item['customizationText']) ? clean_input($item['customizationText']) : null);
                        $unitPrice = $basePrice + $addonTotal;
                        $lineTotal = round($unitPrice * $qty, 2);
                        $subtotal += $lineTotal;
                        $maxPrepTime = max($maxPrepTime, (int)$dish['prep_time_minutes']);

                        $verifiedItems[] = [
                            'item_id' => $dish['item_id'],
                            'item_name' => $dish['item_name'],
                            'price' => $unitPrice,
                            'base_price' => $basePrice,
                            'customization_total' => $addonTotal,
                            'customizations' => $customText,
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
                            `delivery_fee`, `platform_fee`, `discount_amount`, `total_amount`, 
                            `coupon_code`, `order_status`, `is_scheduled`, `scheduled_for`, `scheduled_slot`,
                            `order_notes`, `placed_at`, `estimated_delivery_time`
                        ) VALUES (
                            :uid, :aid, :sub, :tax, 
                            :del, :plat, :disc, :tot, 
                            :coupon, 'CONFIRMED', :is_sched, :sched_for, :sched_slot,
                            :notes, NOW(), :est_delivery
                        )
                    ");
                    $appliedCoupon = !empty($bill['coupon_code']) ? $bill['coupon_code'] : (!empty($couponCode) ? strtoupper(trim($couponCode)) : null);
                    $platformFee = (float)($bill['platform_fee'] ?? 5.00);
                    $estimatedDelivery = $scheduledFor ?: date('Y-m-d H:i:s', strtotime("+{$etaMinutes} minutes"));

                    $orderStmt->execute([
                        ':uid' => $userId,
                        ':aid' => $selectedAddressId,
                        ':sub' => $subtotal,
                        ':tax' => $taxAmount,
                        ':del' => $deliveryFee,
                        ':plat' => $platformFee,
                        ':disc' => $discountAmount,
                        ':tot' => $totalAmount,
                        ':coupon' => $appliedCoupon,
                        ':is_sched' => $isScheduled,
                        ':sched_for' => $scheduledFor,
                        ':sched_slot' => $scheduledSlot,
                        ':notes' => $orderNotes,
                        ':est_delivery' => $estimatedDelivery
                    ]);
                    $orderId = (int)$pdo->lastInsertId();

                    // Store standardized human-friendly tracking number (#ZIP-xxxx)
                    $orderNumber = '#ZIP-' . $orderId;
                    $pdo->prepare("UPDATE `orders` SET `order_number` = :onum WHERE `order_id` = :oid")->execute([
                        ':onum' => $orderNumber,
                        ':oid' => $orderId
                    ]);

                    // Step 6: Insert Order Items & Deduct Stock
                    $itemInsertStmt = $pdo->prepare("
                        INSERT INTO `order_items` (`order_id`, `item_id`, `quantity`, `unit_price`, `line_total`, `customizations`, `customization_total`) 
                        VALUES (:oid, :iid, :qty, :price, :ltot, :custom, :ctot)
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
                            ':ltot' => $vItem['line_total'],
                            ':custom' => $vItem['customizations'] ?? null,
                            ':ctot' => $vItem['customization_total'] ?? 0.00
                        ]);

                        $stockUpdateStmt->execute([
                            ':qty' => $vItem['qty'],
                            ':iid' => $vItem['item_id']
                        ]);
                    }

                    // Step 7: Record Payment Entry
                    $validModes = ['UPI', 'CREDIT_CARD', 'DEBIT_CARD', 'NET_BANKING', 'COD'];
                    if (!in_array($paymentMode, $validModes)) {
                        $paymentMode = 'UPI';
                    }

                    if ($paymentMode === 'COD') {
                        $txRef = 'COD-' . strtoupper(bin2hex(random_bytes(5)));
                        $payStatus = 'PENDING';
                    } else {
                        // Razorpay Online Sandbox Payment
                        if (!empty($razorpayPaymentId)) {
                            $txRef = $razorpayPaymentId;
                        } else {
                            $txRef = 'pay_test_' . substr(bin2hex(random_bytes(7)), 0, 14);
                        }
                        $payStatus = 'SUCCESSFUL';
                    }

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

                    // Step 8: Create Delivery Dispatch Ticket with 6-digit Secret OTP & Geolocation (1 Driver 1 Delivery Policy)
                    $availableRider = get_available_delivery_partner();
                    $assignedRiderId = $availableRider ? (int)$availableRider['user_id'] : null;
                    $assignedRiderName = $availableRider ? $availableRider['full_name'] : 'Zippy Fleet Courier Pool';
                    $assignedRiderPhone = $availableRider ? ($availableRider['phone_number'] ?? '+91 96320 98765') : '+91 96320 98765';

                    $deliveryOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    $dispStmt = $pdo->prepare("
                        INSERT INTO `delivery_dispatches` (
                            `order_id`, `rider_id`, `delivery_partner_name`, `delivery_partner_phone`, 
                            `delivery_otp`, `assigned_at`, `delivery_status`,
                            `current_latitude`, `current_longitude`, `delivery_latitude`, `delivery_longitude`,
                            `route_progress`, `distance_remaining_km`, `estimated_minutes_remaining`
                        ) VALUES (
                            :oid, :rid, :rname, :rphone, 
                            :otp, NOW(), 'ASSIGNED',
                            12.97840000, 77.64080000, 12.96980000, 77.64990000,
                            0, 2.40, :eta
                        )
                    ");
                    $dispStmt->execute([
                        ':oid'   => $orderId,
                        ':rid'   => $assignedRiderId,
                        ':rname' => $assignedRiderName,
                        ':rphone'=> $assignedRiderPhone,
                        ':otp'   => $deliveryOtp,
                        ':eta'   => $etaMinutes
                    ]);

                    // Step 9: Audit Trail Logging
                    $auditNewStatus = $isScheduled ? "CONFIRMED [Scheduled: {$scheduledSlot}]" : 'CONFIRMED';
                    $auditStmt = $pdo->prepare("
                        INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`) 
                        VALUES (:oid, 'PLACED', :astat, :uid, NOW())
                    ");
                    $auditStmt->execute([
                        ':oid' => $orderId,
                        ':astat' => $auditNewStatus,
                        ':uid' => $userId
                    ]);

                    // COMMIT ACID TRANSACTION
                    $pdo->commit();

                    // Step 10: Record Money Transaction in Financial Ledger
                    try {
                        if ($paymentMode === 'COD') {
                            record_money_transaction(
                                $orderId,
                                $userId,
                                'CUSTOMER_PAYMENT_COD',
                                'INWARD',
                                'COD_CASH',
                                (float)$totalAmount,
                                'PENDING',
                                $txRef,
                                'Pending Delivery Courier',
                                "Order {$orderNumber} Cash on Delivery pending doorstep collection."
                            );
                        } else {
                            $mappedMethod = in_array($paymentMode, ['CREDIT_CARD', 'DEBIT_CARD', 'NET_BANKING']) ? $paymentMode : 'UPI';
                            record_money_transaction(
                                $orderId,
                                $userId,
                                'CUSTOMER_PAYMENT_ONLINE',
                                'INWARD',
                                $mappedMethod,
                                (float)$totalAmount,
                                'COMPLETED',
                                $txRef,
                                'Razorpay Gateway / Online Pay',
                                "Order {$orderNumber} online prepayment received from customer."
                            );
                        }
                    } catch (Throwable $te) {
                        error_log("Money transaction recording error for Order #{$orderId}: " . $te->getMessage());
                    }

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
      <input type="hidden" name="razorpay_payment_id" id="razorpayPaymentIdInput" value="">
      <input type="hidden" name="razorpay_order_id" id="razorpayOrderIdInput" value="">
      <input type="hidden" name="razorpay_signature" id="razorpaySignatureInput" value="">

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
                  <input type="text" name="guest_name" class="form-control rounded-3" placeholder="Athul" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-bold">Email Address *</label>
                  <input type="email" name="guest_email" class="form-control rounded-3" placeholder="athul@example.com" required>
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

          <!-- Section 3: Delivery Timing (Immediate vs Advance Scheduled Meal) -->
          <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history text-success me-2"></i>3. Delivery Schedule & Timing</h5>
              <span class="badge rounded-pill px-3 py-1" style="background:#CEFFDC; color:#243E2C; font-size:0.75rem;">
                <i class="bi bi-calendar2-check-fill me-1"></i> Advance Pre-Order
              </span>
            </div>

            <div class="row g-3 mb-3">
              <!-- Option A: Deliver Now -->
              <div class="col-md-6">
                <label class="card h-100 p-3 border rounded-3 cursor-pointer delivery-type-card active" id="cardDeliverNow" style="cursor:pointer; border-color:#2E6F40 !important; background:#EDFBF2;">
                  <div class="d-flex align-items-center gap-3">
                    <input class="form-check-input m-0" type="radio" name="delivery_type" value="now" id="deliveryTypeNow" checked>
                    <div>
                      <div class="d-flex align-items-center gap-2">
                        <strong class="text-dark">⚡ Deliver Now (Standard)</strong>
                        <span class="badge bg-success rounded-pill px-2 py-0" style="font-size:0.65rem;">EXPRESS</span>
                      </div>
                      <span class="small text-muted d-block mt-1">Fresh cooking starts right away. Arrives in ~25-35 mins.</span>
                    </div>
                  </div>
                </label>
              </div>

              <!-- Option B: Schedule in Advance -->
              <div class="col-md-6">
                <label class="card h-100 p-3 border rounded-3 cursor-pointer delivery-type-card" id="cardDeliverSchedule" style="cursor:pointer;">
                  <div class="d-flex align-items-center gap-3">
                    <input class="form-check-input m-0" type="radio" name="delivery_type" value="schedule" id="deliveryTypeSchedule">
                    <div>
                      <div class="d-flex align-items-center gap-2">
                        <strong class="text-dark">📅 Schedule in Advance</strong>
                        <span class="badge rounded-pill px-2 py-0.5 text-white" style="background:#7C3AED; font-size:0.65rem;">POPULAR</span>
                      </div>
                      <span class="small text-muted d-block mt-1">Pre-order for lunch, dinner or tomorrow. Cooked fresh on time!</span>
                    </div>
                  </div>
                </label>
              </div>
            </div>

            <!-- Advance Scheduling Drawer -->
            <div id="advanceScheduleBox" style="display:none;" class="p-3 p-md-4 rounded-4 border mt-2 bg-light">
              <h6 class="fw-bold text-dark mb-3"><i class="bi bi-calendar-event text-primary me-2"></i>Select Advance Meal Date & Delivery Slot</h6>
              
              <!-- Quick Date Selection -->
              <div class="mb-3">
                <label class="form-label small fw-bold text-muted text-uppercase mb-2" style="font-size:0.72rem; letter-spacing:0.5px;">Choose Delivery Date</label>
                <div class="d-flex flex-wrap gap-2 mb-2" id="datePillsGroup">
                  <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 date-pill-btn active" data-date="<?= date('Y-m-d') ?>" style="background:#2E6F40; color:#fff;">
                    Today (<?= date('M j') ?>)
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 date-pill-btn" data-date="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                    Tomorrow (<?= date('M j', strtotime('+1 day')) ?>)
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 date-pill-btn" data-date="<?= date('Y-m-d', strtotime('+2 days')) ?>">
                    <?= date('D, M j', strtotime('+2 days')) ?>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="document.getElementById('customScheduledDate').focus(); document.getElementById('customScheduledDate').showPicker?.();">
                    <i class="bi bi-calendar3 me-1"></i> More Dates...
                  </button>
                </div>
                <input type="date" name="scheduled_date" id="customScheduledDate" class="form-control rounded-3 mt-1" style="max-width:220px;" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+7 days')) ?>" value="<?= date('Y-m-d') ?>">
              </div>

              <!-- Meal Slot Selection Chips -->
              <div class="mb-3">
                <label class="form-label small fw-bold text-muted text-uppercase mb-2" style="font-size:0.72rem; letter-spacing:0.5px;">Choose Meal Time Window</label>
                
                <!-- Lunch Slots -->
                <div class="mb-2">
                  <span class="small fw-semibold text-dark d-block mb-1"><i class="bi bi-sun text-warning me-1"></i> Lunch Slots (12:00 PM – 02:30 PM)</span>
                  <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-light border rounded-pill slot-chip-btn" data-time="12:15" data-slot="Lunch (12:00 PM - 12:30 PM)">12:00 PM - 12:30 PM</button>
                    <button type="button" class="btn btn-sm btn-light border rounded-pill slot-chip-btn" data-time="12:45" data-slot="Lunch (12:30 PM - 01:00 PM)">12:30 PM - 01:00 PM</button>
                    <button type="button" class="btn btn-sm btn-light border rounded-pill slot-chip-btn active" data-time="13:15" data-slot="Lunch (01:00 PM - 01:30 PM)" style="background:#7C3AED; color:#fff; border-color:#7C3AED;">01:00 PM - 01:30 PM</button>
                    <button type="button" class="btn btn-sm btn-light border rounded-pill slot-chip-btn" data-time="13:45" data-slot="Lunch (01:30 PM - 02:00 PM)">01:30 PM - 02:00 PM</button>
                    <button type="button" class="btn btn-sm btn-light border rounded-pill slot-chip-btn" data-time="14:15" data-slot="Lunch (02:00 PM - 02:30 PM)">02:00 PM - 02:30 PM</button>
                  </div>
                </div>

                <!-- Snacks / Evening Slots -->
                <div class="mb-2">
                  <span class="small fw-semibold text-dark d-block mb-1"><i class="bi bi-cup-hot text-danger me-1"></i> High Tea & Snacks (04:30 PM – 06:00 PM)</span>
                  <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-light border rounded-pill slot-chip-btn" data-time="16:45" data-slot="Snacks (04:30 PM - 05:00 PM)">04:30 PM - 05:00 PM</button>
                    <button type="button" class="btn btn-sm btn-light border rounded-pill slot-chip-btn" data-time="17:15" data-slot="Snacks (05:00 PM - 05:30 PM)">05:00 PM - 05:30 PM</button>
                    <button type="button" class="btn btn-sm btn-light border rounded-pill slot-chip-btn" data-time="17:45" data-slot="Snacks (05:30 PM - 06:00 PM)">05:30 PM - 06:00 PM</button>
                  </div>
                </div>

                <!-- Dinner Slots -->
                <div class="mb-2">
                  <span class="small fw-semibold text-dark d-block mb-1"><i class="bi bi-moon-stars text-primary me-1"></i> Dinner Slots (07:00 PM – 10:00 PM)</span>
                  <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-light border rounded-pill slot-chip-btn" data-time="19:15" data-slot="Dinner (07:00 PM - 07:30 PM)">07:00 PM - 07:30 PM</button>
                    <button type="button" class="btn btn-sm btn-light border rounded-pill slot-chip-btn" data-time="19:45" data-slot="Dinner (07:30 PM - 08:00 PM)">07:30 PM - 08:00 PM</button>
                    <button type="button" class="btn btn-sm btn-light border rounded-pill slot-chip-btn" data-time="20:15" data-slot="Dinner (08:00 PM - 08:30 PM)">08:00 PM - 08:30 PM</button>
                    <button type="button" class="btn btn-sm btn-light border rounded-pill slot-chip-btn" data-time="20:45" data-slot="Dinner (08:30 PM - 09:00 PM)">08:30 PM - 09:00 PM</button>
                    <button type="button" class="btn btn-sm btn-light border rounded-pill slot-chip-btn" data-time="21:15" data-slot="Dinner (09:00 PM - 09:30 PM)">09:00 PM - 09:30 PM</button>
                    <button type="button" class="btn btn-sm btn-light border rounded-pill slot-chip-btn" data-time="21:45" data-slot="Dinner (09:30 PM - 10:00 PM)">09:30 PM - 10:00 PM</button>
                  </div>
                </div>

                <!-- Hidden inputs keeping form values in sync -->
                <input type="hidden" name="scheduled_slot" id="scheduledSlotHidden" value="Lunch (01:00 PM - 01:30 PM)">
                <input type="hidden" name="scheduled_time" id="scheduledTimeHidden" value="13:15">
              </div>

              <!-- Live Schedule Preview Card -->
              <div class="alert alert-primary d-flex align-items-center gap-3 py-2 px-3 mb-0 rounded-3 border-0" style="background:#F3E8FF; color:#5B21B6;">
                <i class="bi bi-info-circle-fill fs-4 flex-shrink-0"></i>
                <div class="small">
                  <strong>Advance Meal Guarantee:</strong> Your order will be confirmed and pre-scheduled for <span class="fw-bold text-dark font-monospace" id="scheduleLiveSummary">Today • Lunch (01:00 PM - 01:30 PM)</span>. The Kitchen Display System (KDS) and kitchen staff will prepare your meal fresh to arrive hot right on schedule!
                </div>
              </div>
            </div>
          </div>

          <!-- Section 4: Cooking & Drop-off Instructions -->
          <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-chat-left-text-fill text-success me-2"></i>4. Cooking & Drop-off Instructions</h5>
            <input type="text" name="order_notes" class="form-control rounded-3" placeholder="E.g. Extra spicy, ring bell once, contactless leave at door">
          </div>

          <!-- Section 5: Payment Mode Selection -->
          <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="fw-bold text-dark mb-0"><i class="bi bi-credit-card-2-front-fill text-success me-2"></i>5. Select Payment Method</h5>
              <span class="badge rounded-pill px-3 py-1" style="background:#02042B; color:#528FF0; font-size:0.75rem;">
                <i class="bi bi-shield-check me-1"></i> Razorpay Test Sandbox
              </span>
            </div>

            <!-- Razorpay Info Banner -->
            <div class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 small rounded-3 mb-3 border-0" style="background:#EDFBF2; color:#1C2B20;">
              <i class="bi bi-info-circle-fill text-success fs-5 flex-shrink-0"></i>
              <div>
                <strong>Razorpay Test Sandbox Enabled:</strong> Online payments are processed in dummy test mode. You can test with test card <code>4111 1111 1111 1111</code>, dummy UPI <code>success@razorpay</code>, or 1-Click Sandbox Simulation.
              </div>
            </div>
            
            <div class="d-flex flex-column gap-3">
              
              <!-- UPI via Razorpay -->
              <label class="p-3 border rounded-3 d-flex align-items-center justify-content-between cursor-pointer payment-option-card active" style="cursor:pointer; background:#EDFBF2; border-color:#2E6F40 !important;">
                <div class="d-flex align-items-center gap-3">
                  <input class="form-check-input m-0" type="radio" name="payment_mode" value="UPI" checked>
                  <div>
                    <div class="d-flex align-items-center gap-2">
                      <strong class="text-dark">Instant UPI via Razorpay</strong>
                      <span class="badge bg-success rounded-pill px-2 py-0" style="font-size:0.65rem;">TEST MODE</span>
                    </div>
                    <span class="small text-muted">Google Pay, PhonePe, Paytm, BHIM — dummy VPA: <code>success@razorpay</code></span>
                  </div>
                </div>
                <span class="badge rounded-pill px-2 py-1" style="background:#02042B; color:#528FF0; font-size:0.75rem;">
                  <i class="bi bi-lightning-charge-fill text-warning"></i> FASTEST
                </span>
              </label>

              <!-- Cards via Razorpay -->
              <label class="p-3 border rounded-3 d-flex align-items-center justify-content-between cursor-pointer payment-option-card" style="cursor:pointer;">
                <div class="d-flex align-items-center gap-3">
                  <input class="form-check-input m-0" type="radio" name="payment_mode" value="CREDIT_CARD">
                  <div>
                    <div class="d-flex align-items-center gap-2">
                      <strong class="text-dark">Credit / Debit Card via Razorpay</strong>
                      <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-0" style="font-size:0.65rem;">TEST MODE</span>
                    </div>
                    <span class="small text-muted">Visa, MasterCard, RuPay — test card: <code>4111 1111 1111 1111</code>, CVV: <code>123</code></span>
                  </div>
                </div>
                <i class="bi bi-credit-card fs-4 text-muted"></i>
              </label>

              <!-- Net Banking via Razorpay -->
              <label class="p-3 border rounded-3 d-flex align-items-center justify-content-between cursor-pointer payment-option-card" style="cursor:pointer;">
                <div class="d-flex align-items-center gap-3">
                  <input class="form-check-input m-0" type="radio" name="payment_mode" value="NET_BANKING">
                  <div>
                    <div class="d-flex align-items-center gap-2">
                      <strong class="text-dark">Net Banking via Razorpay</strong>
                      <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-2 py-0" style="font-size:0.65rem;">TEST MODE</span>
                    </div>
                    <span class="small text-muted">HDFC, ICICI, SBI, Axis, Kotak — simulated instant sandbox auth</span>
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
                    <span class="small text-muted">Pay cash or UPI directly to courier upon receiving delivery</span>
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
                <span>Fulfillment Window</span>
                <span class="fw-bold text-forest text-end" id="summaryDeliveryType">⚡ Express (~25-30m)</span>
              </div>
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

              <button type="submit" id="checkoutSubmitBtn" class="btn btn-success w-100 rounded-pill fw-bold py-3 shadow" style="background:#2E6F40; border-color:#2E6F40; font-size:1.1rem;">
                <i class="bi bi-shield-lock-fill me-1"></i> <span id="checkoutSubmitBtnText">Pay & Place Order</span>
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

<!-- =========================================================================
     DUMMY RAZORPAY PAYMENT SYSTEM (AUTHENTIC SANDBOX CHECKOUT SIMULATOR)
     Self-contained, offline-resilient, realistic Razorpay payment gateway
     ========================================================================= -->
<div class="modal fade" id="razorpayDummyModal" tabindex="-1" aria-labelledby="rzpDummyTitle" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden" style="border: 1px solid rgba(82, 143, 240, 0.35) !important;">
      
      <!-- Razorpay Dark Navy Brand Header -->
      <div class="px-4 py-3 text-white d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #02042B 0%, #0c2340 100%); border-bottom: 3px solid #528FF0;">
        <div class="d-flex align-items-center gap-3">
          <div class="px-2 py-1 rounded fw-bold text-white shadow-sm d-flex align-items-center gap-1" style="background:#528FF0; font-size:0.85rem; letter-spacing:0.8px;">
            <i class="bi bi-lightning-charge-fill"></i> RAZORPAY
          </div>
          <div>
            <div class="d-flex align-items-center gap-2">
              <h6 class="fw-bold mb-0 text-white" id="rzpDummyTitle"><?= e(RAZORPAY_MERCHANT_NAME) ?></h6>
              <span class="badge rounded-pill bg-success bg-opacity-25 text-success border border-success px-2 py-0" style="font-size:0.65rem;">
                <span class="spinner-grow spinner-grow-sm me-1 text-success" style="width:0.4rem; height:0.4rem;" role="status"></span>TEST SANDBOX
              </span>
            </div>
            <div class="text-white-50 small" style="font-size:0.75rem;">Instant Sandbox Payment Gateway • MCSP-232</div>
          </div>
        </div>

        <div class="d-flex align-items-center gap-3">
          <div class="text-end d-none d-sm-block">
            <span class="text-white-50 small d-block" style="font-size:0.68rem; letter-spacing:0.5px;">AMOUNT TO PAY</span>
            <span class="fs-5 fw-bold text-white font-monospace" id="rzpHeaderAmount">₹0.00</span>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" title="Cancel & Return to Checkout"></button>
        </div>
      </div>

      <!-- Customer Summary & Academic Viva Testing Toolbar -->
      <div class="px-4 py-2 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="background:#f8fafc;">
        <div class="small text-muted d-flex align-items-center gap-2">
          <i class="bi bi-person-circle text-primary"></i>
          <span class="fw-semibold text-dark" id="rzpCustomerSummary"><?= e($currentUser['name'] ?? 'Athul') ?></span>
          <span class="text-muted">•</span>
          <span class="text-muted" id="rzpCustomerContact"><?= e($currentUser['email'] ?? 'athul@example.com') ?></span>
        </div>

        <!-- 1-Click Simulation Buttons for Viva / Evaluation -->
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-bold shadow-sm d-flex align-items-center gap-1" id="rzpQuickSuccessBtn" style="background:#2E6F40; border-color:#2E6F40; font-size:0.78rem;">
            <i class="bi bi-lightning-fill"></i> 1-Click Instant Success
          </button>
          <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-1 small" id="rzpSimulateFailBtn" style="font-size:0.75rem;">
            <i class="bi bi-x-circle me-1"></i> Simulate Failure
          </button>
        </div>
      </div>

      <!-- Simulated Failure Alert Banner (Initially Hidden) -->
      <div id="rzpFailureBanner" class="alert alert-danger mx-4 mt-3 mb-0 py-2 px-3 small rounded-3 d-none">
        <div class="d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-octagon-fill fs-5"></i>
            <div>
              <strong>Payment Declined (Test Simulation):</strong> <span id="rzpFailureMessage">Transaction declined by issuing bank (Insufficient test balance or invalid PIN).</span>
            </div>
          </div>
          <button type="button" class="btn btn-sm btn-link text-danger p-0 fw-bold" onclick="document.getElementById('rzpFailureBanner').classList.add('d-none');">Try Again</button>
        </div>
      </div>

      <!-- Main Interactive Modal Body -->
      <div class="modal-body p-0" id="rzpModalInteractiveBody">
        <div class="row g-0">
          
          <!-- Left Navigation Sidebar -->
          <div class="col-md-4 border-end bg-light" style="min-height:360px;">
            <div class="nav flex-column nav-pills p-3 gap-2" id="rzpNavTabs" role="tablist">
              
              <!-- Tab: UPI -->
              <button class="nav-link text-start active d-flex align-items-center justify-content-between p-3 rounded-3 rzp-tab-btn" id="rzp-tab-upi" data-bs-toggle="pill" data-bs-target="#rzp-pane-upi" type="button" role="tab">
                <div class="d-flex align-items-center gap-3">
                  <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="background:#EDFBF2; color:#2E6F40; width:36px; height:36px;">
                    <i class="bi bi-qr-code-scan fs-5"></i>
                  </div>
                  <div>
                    <div class="fw-bold text-dark small">UPI / QR Code</div>
                    <div class="text-muted" style="font-size:0.7rem;">Google Pay, PhonePe, Paytm</div>
                  </div>
                </div>
                <span class="badge bg-success rounded-pill px-2" style="font-size:0.6rem;">FASTEST</span>
              </button>

              <!-- Tab: Cards -->
              <button class="nav-link text-start d-flex align-items-center justify-content-between p-3 rounded-3 rzp-tab-btn" id="rzp-tab-cards" data-bs-toggle="pill" data-bs-target="#rzp-pane-cards" type="button" role="tab">
                <div class="d-flex align-items-center gap-3">
                  <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="background:#f0f6ff; color:#0d6efd; width:36px; height:36px;">
                    <i class="bi bi-credit-card-2-front fs-5"></i>
                  </div>
                  <div>
                    <div class="fw-bold text-dark small">Cards</div>
                    <div class="text-muted" style="font-size:0.7rem;">Visa, Mastercard, RuPay</div>
                  </div>
                </div>
                <i class="bi bi-chevron-right text-muted small"></i>
              </button>

              <!-- Tab: Net Banking -->
              <button class="nav-link text-start d-flex align-items-center justify-content-between p-3 rounded-3 rzp-tab-btn" id="rzp-tab-netbanking" data-bs-toggle="pill" data-bs-target="#rzp-pane-netbanking" type="button" role="tab">
                <div class="d-flex align-items-center gap-3">
                  <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="background:#fef3c7; color:#b45309; width:36px; height:36px;">
                    <i class="bi bi-bank fs-5"></i>
                  </div>
                  <div>
                    <div class="fw-bold text-dark small">Net Banking</div>
                    <div class="text-muted" style="font-size:0.7rem;">HDFC, ICICI, SBI, Axis</div>
                  </div>
                </div>
                <i class="bi bi-chevron-right text-muted small"></i>
              </button>

              <!-- Tab: Wallets -->
              <button class="nav-link text-start d-flex align-items-center justify-content-between p-3 rounded-3 rzp-tab-btn" id="rzp-tab-wallets" data-bs-toggle="pill" data-bs-target="#rzp-pane-wallets" type="button" role="tab">
                <div class="d-flex align-items-center gap-3">
                  <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="background:#f3e8ff; color:#7e22ce; width:36px; height:36px;">
                    <i class="bi bi-wallet2 fs-5"></i>
                  </div>
                  <div>
                    <div class="fw-bold text-dark small">Wallets</div>
                    <div class="text-muted" style="font-size:0.7rem;">Amazon Pay, Paytm, Mobikwik</div>
                  </div>
                </div>
                <i class="bi bi-chevron-right text-muted small"></i>
              </button>

            </div>

            <!-- Credentials Hint Card at Sidebar bottom -->
            <div class="p-3 mx-3 mb-3 rounded-3 border bg-white small" style="font-size:0.72rem;">
              <strong class="text-dark d-block mb-1"><i class="bi bi-shield-check text-success me-1"></i> Razorpay Test Credentials:</strong>
              <div class="text-muted mb-1">• UPI VPA: <code>success@razorpay</code></div>
              <div class="text-muted mb-1">• Card: <code>4111 1111 1111 1111</code></div>
              <div class="text-muted">• Demo OTP: <code>123456</code></div>
            </div>
          </div>

          <!-- Right Content Pane -->
          <div class="col-md-8 p-4 bg-white">
            <div class="tab-content" id="rzpTabContent">
              
              <!-- PANE 1: UPI -->
              <div class="tab-pane fade show active" id="rzp-pane-upi" role="tabpanel">
                <h6 class="fw-bold text-dark mb-3 d-flex align-items-center justify-content-between">
                  <span>Pay with UPI</span>
                  <span class="badge bg-light text-dark border small">Instant Approval</span>
                </h6>

                <!-- UPI Sub-options (QR vs VPA) -->
                <ul class="nav nav-pills mb-3 gap-2" id="upiSubTabs" role="tablist">
                  <li class="nav-item">
                    <button class="nav-link active btn-sm py-1 px-3 rounded-pill fw-semibold" id="upi-qr-tab" data-bs-toggle="pill" data-bs-target="#upi-qr-view" type="button">
                      <i class="bi bi-qr-code me-1"></i> Scan QR Code
                    </button>
                  </li>
                  <li class="nav-item">
                    <button class="nav-link btn-sm py-1 px-3 rounded-pill fw-semibold" id="upi-vpa-tab" data-bs-toggle="pill" data-bs-target="#upi-vpa-view" type="button">
                      <i class="bi bi-phone me-1"></i> UPI Apps & VPA
                    </button>
                  </li>
                </ul>

                <div class="tab-content">
                  <!-- QR View -->
                  <div class="tab-pane fade show active text-center py-2" id="upi-qr-view">
                    <div class="p-3 d-inline-block bg-white rounded-3 border shadow-sm mb-2" style="border: 2px dashed #528FF0 !important;">
                      <!-- High-res Scalable SVG QR Code -->
                      <svg width="150" height="150" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                        <rect width="100" height="100" fill="#ffffff"/>
                        <!-- QR corners -->
                        <path d="M5,5 h25 v25 h-25 z M10,10 v15 h15 v-15 z M14,14 h7 v7 h-7 z" fill="#02042B"/>
                        <path d="M70,5 h25 v25 h-25 z M75,10 v15 h15 v-15 z M79,14 h7 v7 h-7 z" fill="#02042B"/>
                        <path d="M5,70 h25 v25 h-25 z M10,75 v15 h15 v-15 z M14,79 h7 v7 h-7 z" fill="#02042B"/>
                        <!-- Mock data squares -->
                        <rect x="35" y="8" width="6" height="6" fill="#02042B"/>
                        <rect x="45" y="12" width="8" height="6" fill="#02042B"/>
                        <rect x="58" y="7" width="6" height="8" fill="#02042B"/>
                        <rect x="35" y="24" width="8" height="8" fill="#528FF0"/>
                        <rect x="48" y="24" width="6" height="6" fill="#02042B"/>
                        <rect x="58" y="22" width="6" height="9" fill="#02042B"/>
                        <rect x="8" y="38" width="6" height="8" fill="#02042B"/>
                        <rect x="18" y="44" width="8" height="6" fill="#02042B"/>
                        <rect x="34" y="38" width="32" height="24" rx="4" fill="#2E6F40"/>
                        <text x="50" y="54" font-size="11" font-weight="bold" fill="#ffffff" text-anchor="middle" font-family="sans-serif">ZIPPY</text>
                        <rect x="74" y="38" width="8" height="6" fill="#02042B"/>
                        <rect x="86" y="44" width="6" height="8" fill="#02042B"/>
                        <rect x="36" y="68" width="6" height="6" fill="#02042B"/>
                        <rect x="48" y="66" width="9" height="7" fill="#02042B"/>
                        <rect x="62" y="70" width="7" height="6" fill="#02042B"/>
                        <rect x="36" y="80" width="9" height="8" fill="#02042B"/>
                        <rect x="52" y="79" width="6" height="12" fill="#528FF0"/>
                        <rect x="68" y="82" width="12" height="8" fill="#02042B"/>
                        <rect x="86" y="72" width="6" height="6" fill="#02042B"/>
                      </svg>
                    </div>
                    <div class="small fw-semibold text-dark">Scan with any UPI App (GPay, PhonePe, Paytm, BHIM)</div>
                    <div class="text-muted small mb-3" style="font-size:0.75rem;">
                      <i class="bi bi-clock me-1 text-primary"></i> QR Code expires in <span class="fw-bold text-dark font-monospace" id="rzpQrTimer">08:00</span> mins
                    </div>

                    <button type="button" class="btn btn-success rounded-pill fw-bold px-4 py-2 shadow-sm w-100" id="rzpSimulateQrScanBtn" style="background:#2E6F40; border-color:#2E6F40;">
                      <i class="bi bi-qr-code-scan me-2"></i> Simulate QR Scan & Authorize Payment
                    </button>
                  </div>

                  <!-- VPA View -->
                  <div class="tab-pane fade" id="upi-vpa-view">
                    <label class="form-label small fw-bold text-dark">Quick Select UPI Provider</label>
                    <div class="row g-2 mb-3">
                      <div class="col-6">
                        <button type="button" class="btn btn-outline-secondary w-100 rounded-3 p-2 text-start d-flex align-items-center gap-2 rzp-app-btn" onclick="selectUpiPreset('athul@okhdfcbank')">
                          <span class="badge rounded-circle p-1" style="background:#EA4335; color:#fff;"><i class="bi bi-google"></i></span>
                          <span class="small fw-semibold text-dark">Google Pay</span>
                        </button>
                      </div>
                      <div class="col-6">
                        <button type="button" class="btn btn-outline-secondary w-100 rounded-3 p-2 text-start d-flex align-items-center gap-2 rzp-app-btn" onclick="selectUpiPreset('athul@ybl')">
                          <span class="badge rounded-circle p-1" style="background:#5f259f; color:#fff;"><i class="bi bi-phone"></i></span>
                          <span class="small fw-semibold text-dark">PhonePe</span>
                        </button>
                      </div>
                      <div class="col-6">
                        <button type="button" class="btn btn-outline-secondary w-100 rounded-3 p-2 text-start d-flex align-items-center gap-2 rzp-app-btn" onclick="selectUpiPreset('athul@paytm')">
                          <span class="badge rounded-circle p-1" style="background:#00b9f5; color:#fff;"><i class="bi bi-wallet"></i></span>
                          <span class="small fw-semibold text-dark">Paytm UPI</span>
                        </button>
                      </div>
                      <div class="col-6">
                        <button type="button" class="btn btn-outline-secondary w-100 rounded-3 p-2 text-start d-flex align-items-center gap-2 rzp-app-btn" onclick="selectUpiPreset('success@razorpay')">
                          <span class="badge rounded-circle p-1" style="background:#02042B; color:#528FF0;"><i class="bi bi-shield-check"></i></span>
                          <span class="small fw-semibold text-dark">success@razorpay</span>
                        </button>
                      </div>
                    </div>

                    <div class="mb-3">
                      <label class="form-label small text-muted">Virtual Payment Address (VPA)</label>
                      <div class="input-group">
                        <input type="text" class="form-control font-monospace" id="rzpUpiIdInput" value="success@razorpay" placeholder="username@bank">
                        <button class="btn btn-outline-success btn-sm px-3" type="button" onclick="document.getElementById('rzpUpiIdInput').value='success@razorpay';">Default</button>
                      </div>
                      <span class="text-muted" style="font-size:0.75rem;">Preset <code>success@razorpay</code> guarantees 100% sandbox approval.</span>
                    </div>

                    <button type="button" class="btn btn-success rounded-pill fw-bold w-100 py-2 shadow-sm" id="rzpPayUpiBtn" style="background:#2E6F40; border-color:#2E6F40;">
                      <i class="bi bi-check2-circle me-1"></i> Pay <span class="rzpModalAmtDisplay">₹0.00</span> via UPI
                    </button>
                  </div>
                </div>

              </div>

              <!-- PANE 2: CARDS -->
              <div class="tab-pane fade" id="rzp-pane-cards" role="tabpanel">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <h6 class="fw-bold text-dark mb-0">Credit or Debit Card</h6>
                  <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-0" id="rzpAutoFillCardBtn" style="font-size:0.72rem;">
                    <i class="bi bi-magic me-1"></i> Auto-Fill Test Card
                  </button>
                </div>

                <!-- Card Form View -->
                <div id="rzpCardFormView">
                  <div class="mb-3">
                    <label class="form-label small text-muted mb-1">Card Number</label>
                    <div class="input-group">
                      <input type="text" class="form-control font-monospace" id="rzpCardNumber" placeholder="4111 1111 1111 1111" maxlength="19" value="4111 1111 1111 1111">
                      <span class="input-group-text bg-white" id="rzpCardBrandBadge">
                        <span class="badge bg-primary px-2 py-1">VISA TEST</span>
                      </span>
                    </div>
                  </div>

                  <div class="row g-2 mb-3">
                    <div class="col-6">
                      <label class="form-label small text-muted mb-1">Expiry (MM/YY)</label>
                      <input type="text" class="form-control font-monospace" id="rzpCardExpiry" placeholder="12/28" maxlength="5" value="12/28">
                    </div>
                    <div class="col-6">
                      <label class="form-label small text-muted mb-1">CVV</label>
                      <input type="password" class="form-control font-monospace" id="rzpCardCvv" placeholder="123" maxlength="4" value="123">
                    </div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label small text-muted mb-1">Cardholder Name</label>
                    <input type="text" class="form-control" id="rzpCardHolder" placeholder="Athul" value="<?= e($currentUser['name'] ?? 'Athul') ?>">
                  </div>

                  <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="rzpSaveCard" checked>
                    <label class="form-check-label text-muted" for="rzpSaveCard" style="font-size:0.75rem;">
                      Save this card securely as per RBI tokenization guidelines (Sandbox)
                    </label>
                  </div>

                  <button type="button" class="btn btn-success rounded-pill fw-bold w-100 py-2 shadow-sm" id="rzpPayCardBtn" style="background:#2E6F40; border-color:#2E6F40;">
                    <i class="bi bi-lock-fill me-1"></i> Pay <span class="rzpModalAmtDisplay">₹0.00</span> via Card
                  </button>
                </div>

                <!-- 3D-Secure Bank OTP Screen (Simulated transition) -->
                <div id="rzpCardOtpView" class="p-3 rounded-3 bg-light border d-none">
                  <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                    <div class="d-flex align-items-center gap-2">
                      <span class="badge bg-dark fw-bold px-2 py-1">3D-SECURE</span>
                      <strong class="text-dark small">HDFC Bank Verified by VISA</strong>
                    </div>
                    <span class="badge bg-success bg-opacity-25 text-success small">Sandbox Mode</span>
                  </div>

                  <p class="small text-muted mb-2">
                    An OTP has been sent to your registered mobile ending in <strong>******3210</strong> to authorize payment of <strong class="text-dark rzpModalAmtDisplay">₹0.00</strong>.
                  </p>

                  <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">Enter 6-Digit Bank OTP</label>
                    <div class="input-group">
                      <input type="text" class="form-control font-monospace fs-5 text-center fw-bold" id="rzpBankOtpInput" value="123456" maxlength="6" style="letter-spacing:4px;">
                      <button class="btn btn-outline-secondary btn-sm" type="button" onclick="document.getElementById('rzpBankOtpInput').value='123456';">Fill Demo OTP</button>
                    </div>
                    <div class="small text-muted mt-1" style="font-size:0.72rem;">Demo OTP: <code>123456</code> (Instant authorization)</div>
                  </div>

                  <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="document.getElementById('rzpCardOtpView').classList.add('d-none'); document.getElementById('rzpCardFormView').classList.remove('d-none');">
                      Back
                    </button>
                    <button type="button" class="btn btn-success rounded-pill fw-bold flex-grow-1 shadow-sm" id="rzpSubmitOtpBtn" style="background:#2E6F40; border-color:#2E6F40;">
                      <i class="bi bi-shield-check me-1"></i> Submit OTP & Authorize
                    </button>
                  </div>
                </div>

              </div>

              <!-- PANE 3: NET BANKING -->
              <div class="tab-pane fade" id="rzp-pane-netbanking" role="tabpanel">
                <h6 class="fw-bold text-dark mb-2">Select Your Bank</h6>
                <p class="small text-muted mb-3" style="font-size:0.75rem;">Simulated instant authentication for all Indian banking networks.</p>

                <!-- Popular Banks Grid -->
                <div class="row g-2 mb-3" id="rzpBanksGrid">
                  <div class="col-4">
                    <label class="card p-2 text-center border rounded-3 h-100 cursor-pointer rzp-bank-card active" style="cursor:pointer; background:#EDFBF2; border-color:#2E6F40 !important;">
                      <input type="radio" name="rzp_bank" value="HDFC" class="d-none" checked>
                      <i class="bi bi-bank fs-4 text-primary"></i>
                      <span class="small fw-bold text-dark mt-1" style="font-size:0.75rem;">HDFC Bank</span>
                    </label>
                  </div>
                  <div class="col-4">
                    <label class="card p-2 text-center border rounded-3 h-100 cursor-pointer rzp-bank-card" style="cursor:pointer;">
                      <input type="radio" name="rzp_bank" value="ICICI" class="d-none">
                      <i class="bi bi-bank fs-4 text-warning"></i>
                      <span class="small fw-bold text-dark mt-1" style="font-size:0.75rem;">ICICI Bank</span>
                    </label>
                  </div>
                  <div class="col-4">
                    <label class="card p-2 text-center border rounded-3 h-100 cursor-pointer rzp-bank-card" style="cursor:pointer;">
                      <input type="radio" name="rzp_bank" value="SBI" class="d-none">
                      <i class="bi bi-bank fs-4 text-info"></i>
                      <span class="small fw-bold text-dark mt-1" style="font-size:0.75rem;">SBI</span>
                    </label>
                  </div>
                  <div class="col-4">
                    <label class="card p-2 text-center border rounded-3 h-100 cursor-pointer rzp-bank-card" style="cursor:pointer;">
                      <input type="radio" name="rzp_bank" value="AXIS" class="d-none">
                      <i class="bi bi-bank fs-4 text-danger"></i>
                      <span class="small fw-bold text-dark mt-1" style="font-size:0.75rem;">Axis Bank</span>
                    </label>
                  </div>
                  <div class="col-4">
                    <label class="card p-2 text-center border rounded-3 h-100 cursor-pointer rzp-bank-card" style="cursor:pointer;">
                      <input type="radio" name="rzp_bank" value="KOTAK" class="d-none">
                      <i class="bi bi-bank fs-4 text-danger"></i>
                      <span class="small fw-bold text-dark mt-1" style="font-size:0.75rem;">Kotak Bank</span>
                    </label>
                  </div>
                  <div class="col-4">
                    <label class="card p-2 text-center border rounded-3 h-100 cursor-pointer rzp-bank-card" style="cursor:pointer;">
                      <input type="radio" name="rzp_bank" value="PNB" class="d-none">
                      <i class="bi bi-bank fs-4 text-secondary"></i>
                      <span class="small fw-bold text-dark mt-1" style="font-size:0.75rem;">Punjab Nat.</span>
                    </label>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label small text-muted mb-1">Or Choose From All Indian Banks</label>
                  <select class="form-select form-select-sm" id="rzpBankSelect">
                    <option value="HDFC">HDFC Bank (Selected)</option>
                    <option value="ICICI">ICICI Bank</option>
                    <option value="SBI">State Bank of India</option>
                    <option value="AXIS">Axis Bank</option>
                    <option value="KOTAK">Kotak Mahindra Bank</option>
                    <option value="BOB">Bank of Baroda</option>
                    <option value="CANARA">Canara Bank</option>
                    <option value="UNION">Union Bank of India</option>
                    <option value="INDUS">IndusInd Bank</option>
                    <option value="YES">Yes Bank</option>
                    <option value="FEDERAL">Federal Bank</option>
                  </select>
                </div>

                <button type="button" class="btn btn-success rounded-pill fw-bold w-100 py-2 shadow-sm" id="rzpPayNetbankingBtn" style="background:#2E6F40; border-color:#2E6F40;">
                  <i class="bi bi-shield-lock-fill me-1"></i> Pay <span class="rzpModalAmtDisplay">₹0.00</span> via Net Banking
                </button>
              </div>

              <!-- PANE 4: WALLETS -->
              <div class="tab-pane fade" id="rzp-pane-wallets" role="tabpanel">
                <h6 class="fw-bold text-dark mb-2">Select Digital Wallet</h6>
                <p class="small text-muted mb-3" style="font-size:0.75rem;">1-Click Sandbox Authorization via Connected Wallets.</p>

                <div class="d-flex flex-column gap-2 mb-3">
                  <label class="card p-3 border rounded-3 d-flex flex-row align-items-center justify-content-between cursor-pointer rzp-wallet-card active" style="cursor:pointer; background:#EDFBF2; border-color:#2E6F40 !important;">
                    <div class="d-flex align-items-center gap-3">
                      <input type="radio" name="rzp_wallet" value="AMAZONPAY" class="form-check-input m-0" checked>
                      <div>
                        <strong class="text-dark small d-block">Amazon Pay Wallet</strong>
                        <span class="text-muted" style="font-size:0.72rem;">Balance: ₹2,500.00 (Simulated)</span>
                      </div>
                    </div>
                    <span class="badge bg-warning text-dark">Linked</span>
                  </label>

                  <label class="card p-3 border rounded-3 d-flex flex-row align-items-center justify-content-between cursor-pointer rzp-wallet-card" style="cursor:pointer;">
                    <div class="d-flex align-items-center gap-3">
                      <input type="radio" name="rzp_wallet" value="PHONEPE" class="form-check-input m-0">
                      <div>
                        <strong class="text-dark small d-block">PhonePe Wallet</strong>
                        <span class="text-muted" style="font-size:0.72rem;">Balance: ₹1,200.00</span>
                      </div>
                    </div>
                    <span class="badge bg-light text-dark border">Ready</span>
                  </label>

                  <label class="card p-3 border rounded-3 d-flex flex-row align-items-center justify-content-between cursor-pointer rzp-wallet-card" style="cursor:pointer;">
                    <div class="d-flex align-items-center gap-3">
                      <input type="radio" name="rzp_wallet" value="PAYTM" class="form-check-input m-0">
                      <div>
                        <strong class="text-dark small d-block">Paytm Postpaid / Wallet</strong>
                        <span class="text-muted" style="font-size:0.72rem;">Simulated 1-click Pay Later</span>
                      </div>
                    </div>
                    <span class="badge bg-light text-dark border">Ready</span>
                  </label>
                </div>

                <button type="button" class="btn btn-success rounded-pill fw-bold w-100 py-2 shadow-sm" id="rzpPayWalletBtn" style="background:#2E6F40; border-color:#2E6F40;">
                  <i class="bi bi-wallet2 me-1"></i> Pay <span class="rzpModalAmtDisplay">₹0.00</span> via Wallet
                </button>
              </div>

            </div>
          </div>

        </div>
      </div>

      <!-- Processing State View (Swapped into body during payment execution) -->
      <div class="modal-body p-5 bg-white text-center d-none" id="rzpModalProcessingBody">
        <div class="py-4">
          <!-- Animated Spinner -->
          <div class="mb-4 position-relative d-inline-block">
            <div class="spinner-border text-primary" style="width: 4rem; height: 4rem;" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <div class="position-absolute top-50 start-50 translate-middle">
              <i class="bi bi-shield-lock-fill text-primary fs-4"></i>
            </div>
          </div>

          <h5 class="fw-bold text-dark mb-2" id="rzpProcessingTitle">Contacting Razorpay Sandbox...</h5>
          <p class="text-muted small mb-3" id="rzpProcessingSubtitle">
            Securing 256-bit transactional handshake with banking server. Please do not refresh.
          </p>

          <div class="badge rounded-pill px-3 py-2 text-dark font-monospace" style="background:#f1f5f9; font-size:0.8rem;" id="rzpProcessingTxRef">
            Ref: Generating...
          </div>
        </div>
      </div>

      <!-- Success State View (Brief flash before redirect) -->
      <div class="modal-body p-5 bg-white text-center d-none" id="rzpModalSuccessBody">
        <div class="py-3">
          <div class="rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm mb-3" style="width: 72px; height: 72px; background: #CEFFDC; color: #2E6F40;">
            <i class="bi bi-check-lg" style="font-size: 2.5rem;"></i>
          </div>
          <h4 class="fw-bold text-dark mb-1">Payment Successful!</h4>
          <p class="text-muted small mb-2">Transaction authorized by Razorpay Test Sandbox.</p>
          <div class="small fw-bold text-forest font-monospace" id="rzpSuccessTxId">pay_rzp_test_...</div>
          <div class="text-muted small mt-2">Placing ACID transactional order now...</div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer bg-white border-top py-2 px-4 justify-content-between">
        <div class="d-flex align-items-center gap-3 text-muted small" style="font-size:0.75rem;">
          <span><i class="bi bi-shield-check text-success me-1"></i> 256-Bit SSL</span>
          <span><i class="bi bi-lock-fill text-primary me-1"></i> PCI-DSS Compliant</span>
          <span class="d-none d-sm-inline"><i class="bi bi-check-circle-fill text-success me-1"></i> Razorpay Simulated</span>
        </div>
        <button type="button" class="btn btn-sm btn-link text-muted text-decoration-none" data-bs-dismiss="modal">
          Cancel & Return
        </button>
      </div>

    </div>
  </div>
</div>

<script>
// Global grand total tracker
window.currentGrandTotal = 0;

document.addEventListener('DOMContentLoaded', () => {
  const jsonInput = document.getElementById('checkoutCartJsonInput');
  const couponInput = document.getElementById('checkoutCouponInput');
  const itemsContainer = document.getElementById('checkoutSummaryItems');
  const checkoutForm = document.getElementById('checkoutForm');

  // Synchronize client cart with hidden input
  if (jsonInput) jsonInput.value = JSON.stringify(cart);
  if (couponInput) couponInput.value = activeCoupon || '';

  // Synchronize UI bill breakdown
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
      window.currentGrandTotal = grandTotal;

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

  // Delivery Timing Toggle (Deliver Now vs Schedule in Advance)
  const cardNow = document.getElementById('cardDeliverNow');
  const cardSched = document.getElementById('cardDeliverSchedule');
  const radioNow = document.getElementById('deliveryTypeNow');
  const radioSched = document.getElementById('deliveryTypeSchedule');
  const advanceBox = document.getElementById('advanceScheduleBox');
  const summaryFulfillment = document.getElementById('summaryDeliveryType');
  const scheduleLiveSummary = document.getElementById('scheduleLiveSummary');
  const dateInput = document.getElementById('customScheduledDate');
  const slotHidden = document.getElementById('scheduledSlotHidden');
  const timeHidden = document.getElementById('scheduledTimeHidden');
  const datePills = document.querySelectorAll('.date-pill-btn');
  const slotChips = document.querySelectorAll('.slot-chip-btn');

  function updateScheduleSummary() {
    if (!radioSched || !radioSched.checked) {
      if (summaryFulfillment) {
        summaryFulfillment.className = 'fw-bold text-forest text-end';
        summaryFulfillment.textContent = '⚡ Express (~25-30m)';
      }
      return;
    }

    const selectedDate = dateInput ? dateInput.value : '';
    const selectedSlot = slotHidden ? slotHidden.value : 'Lunch (01:00 PM - 01:30 PM)';
    
    let dateStr = selectedDate;
    if (selectedDate) {
      const todayStr = '<?= date('Y-m-d') ?>';
      const tomorrowStr = '<?= date('Y-m-d', strtotime('+1 day')) ?>';
      if (selectedDate === todayStr) {
        dateStr = 'Today (' + '<?= date('M j') ?>' + ')';
      } else if (selectedDate === tomorrowStr) {
        dateStr = 'Tomorrow (' + '<?= date('M j', strtotime('+1 day')) ?>' + ')';
      } else {
        const dObj = new Date(selectedDate + 'T00:00:00');
        dateStr = dObj.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
      }
    }

    const fullSummary = `${dateStr} • ${selectedSlot}`;
    if (scheduleLiveSummary) {
      scheduleLiveSummary.textContent = fullSummary;
    }
    if (summaryFulfillment) {
      summaryFulfillment.className = 'fw-bold text-end';
      summaryFulfillment.style.color = '#7C3AED';
      summaryFulfillment.textContent = `📅 ${selectedSlot}`;
    }
  }

  function setDeliveryMode(isSchedule) {
    if (isSchedule) {
      if (radioSched) radioSched.checked = true;
      if (cardSched) {
        cardSched.classList.add('active');
        cardSched.style.background = '#F3E8FF';
        cardSched.style.borderColor = '#7C3AED';
      }
      if (cardNow) {
        cardNow.classList.remove('active');
        cardNow.style.background = '';
        cardNow.style.borderColor = '';
      }
      if (advanceBox) advanceBox.style.display = 'block';
    } else {
      if (radioNow) radioNow.checked = true;
      if (cardNow) {
        cardNow.classList.add('active');
        cardNow.style.background = '#EDFBF2';
        cardNow.style.borderColor = '#2E6F40';
      }
      if (cardSched) {
        cardSched.classList.remove('active');
        cardSched.style.background = '';
        cardSched.style.borderColor = '';
      }
      if (advanceBox) advanceBox.style.display = 'none';
    }
    updateScheduleSummary();
  }

  if (cardNow) {
    cardNow.addEventListener('click', () => setDeliveryMode(false));
  }
  if (cardSched) {
    cardSched.addEventListener('click', () => setDeliveryMode(true));
  }

  // Date Pills
  datePills.forEach(pill => {
    pill.addEventListener('click', function(e) {
      e.preventDefault();
      datePills.forEach(p => {
        p.classList.remove('active');
        p.style.background = '';
        p.style.color = '';
      });
      this.classList.add('active');
      this.style.background = '#2E6F40';
      this.style.color = '#fff';

      const d = this.getAttribute('data-date');
      if (dateInput) {
        dateInput.value = d;
      }
      updateScheduleSummary();
    });
  });

  if (dateInput) {
    dateInput.addEventListener('change', function() {
      datePills.forEach(p => {
        if (p.getAttribute('data-date') === this.value) {
          p.classList.add('active');
          p.style.background = '#2E6F40';
          p.style.color = '#fff';
        } else {
          p.classList.remove('active');
          p.style.background = '';
          p.style.color = '';
        }
      });
      updateScheduleSummary();
    });
  }

  // Slot Chips
  slotChips.forEach(chip => {
    chip.addEventListener('click', function(e) {
      e.preventDefault();
      slotChips.forEach(c => {
        c.classList.remove('active');
        c.style.background = '';
        c.style.color = '';
        c.style.borderColor = '';
      });
      this.classList.add('active');
      this.style.background = '#7C3AED';
      this.style.color = '#fff';
      this.style.borderColor = '#7C3AED';

      const sTime = this.getAttribute('data-time');
      const sSlot = this.getAttribute('data-slot');
      if (timeHidden) timeHidden.value = sTime;
      if (slotHidden) slotHidden.value = sSlot;
      updateScheduleSummary();
    });
  });

  // Payment Option selection visual styling
  const optionCards = document.querySelectorAll('.payment-option-card');
  optionCards.forEach(card => {
    card.addEventListener('click', function() {
      optionCards.forEach(c => {
        c.classList.remove('active');
        c.style.background = '';
        c.style.borderColor = '';
      });
      this.classList.add('active');
      this.style.background = '#EDFBF2';
      this.style.borderColor = '#2E6F40';
      const radio = this.querySelector('input[type="radio"]');
      if (radio) radio.checked = true;
    });
  });

  // Initialize Bootstrap Modal
  const modalEl = document.getElementById('razorpayDummyModal');
  if (modalEl) {
    rzpDummyModalInstance = new bootstrap.Modal(modalEl);
  }

  // Tab button switching resilience inside modal
  const tabBtns = document.querySelectorAll('.rzp-tab-btn');
  tabBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      tabBtns.forEach(b => b.classList.remove('active'));
      this.classList.add('active');
    });
  });

  // Bank Selection Cards sync
  const bankCards = document.querySelectorAll('.rzp-bank-card');
  const bankSelect = document.getElementById('rzpBankSelect');
  bankCards.forEach(card => {
    card.addEventListener('click', function() {
      bankCards.forEach(c => {
        c.classList.remove('active');
        c.style.background = '';
        c.style.borderColor = '';
      });
      this.classList.add('active');
      this.style.background = '#EDFBF2';
      this.style.borderColor = '#2E6F40';
      const radio = this.querySelector('input[name="rzp_bank"]');
      if (radio) {
        radio.checked = true;
        if (bankSelect) bankSelect.value = radio.value;
      }
    });
  });

  if (bankSelect) {
    bankSelect.addEventListener('change', function() {
      const val = this.value;
      bankCards.forEach(c => {
        const r = c.querySelector('input[name="rzp_bank"]');
        if (r && r.value === val) {
          c.classList.add('active');
          c.style.background = '#EDFBF2';
          c.style.borderColor = '#2E6F40';
          r.checked = true;
        } else {
          c.classList.remove('active');
          c.style.background = '';
          c.style.borderColor = '';
        }
      });
    });
  }

  // Wallet Selection Cards sync
  const walletCards = document.querySelectorAll('.rzp-wallet-card');
  walletCards.forEach(card => {
    card.addEventListener('click', function() {
      walletCards.forEach(c => {
        c.classList.remove('active');
        c.style.background = '';
        c.style.borderColor = '';
      });
      this.classList.add('active');
      this.style.background = '#EDFBF2';
      this.style.borderColor = '#2E6F40';
      const radio = this.querySelector('input[name="rzp_wallet"]');
      if (radio) radio.checked = true;
    });
  });

  // Auto-Fill Card Button
  const autoFillBtn = document.getElementById('rzpAutoFillCardBtn');
  if (autoFillBtn) {
    autoFillBtn.addEventListener('click', () => {
      document.getElementById('rzpCardNumber').value = '4111 1111 1111 1111';
      document.getElementById('rzpCardExpiry').value = '12/28';
      document.getElementById('rzpCardCvv').value = '123';
      document.getElementById('rzpCardBrandBadge').innerHTML = '<span class="badge bg-primary px-2 py-1">VISA TEST</span>';
    });
  }

  // Format Card Number input with spaces
  const cardNumInput = document.getElementById('rzpCardNumber');
  if (cardNumInput) {
    cardNumInput.addEventListener('input', function(e) {
      let val = this.value.replace(/\D/g, '').substring(0, 16);
      let formatted = val.match(/.{1,4}/g)?.join(' ') || val;
      this.value = formatted;
      
      const badge = document.getElementById('rzpCardBrandBadge');
      if (val.startsWith('4')) {
        badge.innerHTML = '<span class="badge bg-primary px-2 py-1">VISA TEST</span>';
      } else if (val.startsWith('5')) {
        badge.innerHTML = '<span class="badge bg-danger px-2 py-1">MASTERCARD</span>';
      } else if (val.startsWith('6') || val.startsWith('508')) {
        badge.innerHTML = '<span class="badge bg-success px-2 py-1">RUPAY TEST</span>';
      } else {
        badge.innerHTML = '<span class="badge bg-secondary px-2 py-1">CARD</span>';
      }
    });
  }

  // Card Pay Button -> Switch to 3D Secure OTP Screen
  const payCardBtn = document.getElementById('rzpPayCardBtn');
  if (payCardBtn) {
    payCardBtn.addEventListener('click', () => {
      document.getElementById('rzpCardFormView').classList.add('d-none');
      document.getElementById('rzpCardOtpView').classList.remove('d-none');
    });
  }

  // Card Submit OTP Button -> Authorize
  const submitOtpBtn = document.getElementById('rzpSubmitOtpBtn');
  if (submitOtpBtn) {
    submitOtpBtn.addEventListener('click', () => {
      executeRazorpayAuthorization('CREDIT_CARD');
    });
  }

  // Pay via UPI Button
  const payUpiBtn = document.getElementById('rzpPayUpiBtn');
  if (payUpiBtn) {
    payUpiBtn.addEventListener('click', () => {
      executeRazorpayAuthorization('UPI');
    });
  }

  // Simulate QR Scan Button
  const simulateQrBtn = document.getElementById('rzpSimulateQrScanBtn');
  if (simulateQrBtn) {
    simulateQrBtn.addEventListener('click', () => {
      executeRazorpayAuthorization('UPI');
    });
  }

  // Pay via Net Banking Button
  const payNetbankingBtn = document.getElementById('rzpPayNetbankingBtn');
  if (payNetbankingBtn) {
    payNetbankingBtn.addEventListener('click', () => {
      executeRazorpayAuthorization('NET_BANKING');
    });
  }

  // Pay via Wallet Button
  const payWalletBtn = document.getElementById('rzpPayWalletBtn');
  if (payWalletBtn) {
    payWalletBtn.addEventListener('click', () => {
      executeRazorpayAuthorization('UPI');
    });
  }

  // 1-Click Instant Success Button (Top bar)
  const quickSuccessBtn = document.getElementById('rzpQuickSuccessBtn');
  if (quickSuccessBtn) {
    quickSuccessBtn.addEventListener('click', () => {
      const selectedRadio = document.querySelector('input[name="payment_mode"]:checked');
      const mode = selectedRadio ? selectedRadio.value : 'UPI';
      executeRazorpayAuthorization(mode === 'COD' ? 'UPI' : mode);
    });
  }

  // Simulate Failure Button (Top bar)
  const simulateFailBtn = document.getElementById('rzpSimulateFailBtn');
  if (simulateFailBtn) {
    simulateFailBtn.addEventListener('click', () => {
      const banner = document.getElementById('rzpFailureBanner');
      banner.classList.remove('d-none');
      banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  }

  /**
   * Open the Dummy Razorpay Sandbox Modal
   */
  window.openDummyRazorpayModal = function(preferredMode) {
    const grandTotal = window.currentGrandTotal || 0;
    const formattedAmt = `₹${grandTotal.toFixed(2)}`;

    // Set amounts across modal
    document.getElementById('rzpHeaderAmount').textContent = formattedAmt;
    document.querySelectorAll('.rzpModalAmtDisplay').forEach(el => {
      el.textContent = formattedAmt;
    });

    // Customer details
    const guestNameInput = document.querySelector('input[name="guest_name"]');
    const guestEmailInput = document.querySelector('input[name="guest_email"]');
    const guestPhoneInput = document.querySelector('input[name="guest_phone"]');

    const customerName = guestNameInput ? guestNameInput.value.trim() : '<?= addslashes($currentUser['name'] ?? 'Athul') ?>';
    const customerEmail = guestEmailInput ? guestEmailInput.value.trim() : '<?= addslashes($currentUser['email'] ?? 'athul@example.com') ?>';
    const customerPhone = guestPhoneInput ? guestPhoneInput.value.trim() : '<?= addslashes($currentUser['phone'] ?? $_SESSION['user_phone'] ?? '9876543210') ?>';

    document.getElementById('rzpCustomerSummary').textContent = customerName;
    document.getElementById('rzpCustomerContact').textContent = `${customerEmail} • ${customerPhone}`;

    // Reset views
    document.getElementById('rzpModalInteractiveBody').classList.remove('d-none');
    document.getElementById('rzpModalProcessingBody').classList.add('d-none');
    document.getElementById('rzpModalSuccessBody').classList.add('d-none');
    document.getElementById('rzpFailureBanner').classList.add('d-none');
    document.getElementById('rzpCardFormView').classList.remove('d-none');
    document.getElementById('rzpCardOtpView').classList.add('d-none');

    // Activate corresponding tab
    const mode = preferredMode || 'UPI';
    if (mode === 'CREDIT_CARD' || mode === 'DEBIT_CARD') {
      const cardTab = document.getElementById('rzp-tab-cards');
      if (cardTab) bootstrap.Tab.getOrCreateInstance(cardTab).show();
    } else if (mode === 'NET_BANKING') {
      const nbTab = document.getElementById('rzp-tab-netbanking');
      if (nbTab) bootstrap.Tab.getOrCreateInstance(nbTab).show();
    } else {
      const upiTab = document.getElementById('rzp-tab-upi');
      if (upiTab) bootstrap.Tab.getOrCreateInstance(upiTab).show();
    }

    if (rzpDummyModalInstance) {
      rzpDummyModalInstance.show();
    }
  };

  /**
   * Execute Dummy Razorpay Authorization & Submit
   */
  function executeRazorpayAuthorization(methodMode) {
    // Generate authentic-format Razorpay payment reference
    const dummyPayId = 'pay_rzp_test_' + Math.random().toString(36).substring(2, 9) + Date.now().toString(36);
    const dummyOrderId = 'order_rzp_test_' + Math.random().toString(36).substring(2, 10);
    const dummySignature = 'sig_rzp_test_' + Math.random().toString(36).substring(2, 12);

    // Swap to Animated Processing Screen
    document.getElementById('rzpModalInteractiveBody').classList.add('d-none');
    document.getElementById('rzpModalProcessingBody').classList.remove('d-none');
    document.getElementById('rzpProcessingTxRef').textContent = 'Ref: ' + dummyPayId;

    setTimeout(() => {
      // Transition to Success Checkmark
      document.getElementById('rzpModalProcessingBody').classList.add('d-none');
      document.getElementById('rzpModalSuccessBody').classList.remove('d-none');
      document.getElementById('rzpSuccessTxId').textContent = dummyPayId;

      // Populate hidden inputs
      document.getElementById('razorpayPaymentIdInput').value = dummyPayId;
      document.getElementById('razorpayOrderIdInput').value = dummyOrderId;
      document.getElementById('razorpaySignatureInput').value = dummySignature;
      document.getElementById('checkoutCartJsonInput').value = JSON.stringify(cart);
      document.getElementById('checkoutCouponInput').value = activeCoupon || '';

      // Ensure payment mode radio matches
      const targetRadio = document.querySelector(`input[name="payment_mode"][value="${methodMode}"]`);
      if (targetRadio) {
        targetRadio.checked = true;
      }

      setTimeout(() => {
        if (rzpDummyModalInstance) rzpDummyModalInstance.hide();
        checkoutForm.submit();
      }, 650);

    }, 850);
  }

  // Mock global Razorpay object for universal compatibility
  window.Razorpay = function(options) {
    this.options = options || {};
    this.open = function() {
      const mode = this.options.notes?.payment_mode || 'UPI';
      window.openDummyRazorpayModal(mode);
    };
    this.on = function(evt, cb) {};
  };

  // Intercept Checkout Form Submission
  if (checkoutForm) {
    checkoutForm.addEventListener('submit', function(e) {
      const selectedRadio = document.querySelector('input[name="payment_mode"]:checked');
      const selectedMode = selectedRadio ? selectedRadio.value : 'UPI';
      const rzpPaymentId = document.getElementById('razorpayPaymentIdInput').value;

      // 1. Validate Cart
      if (!cart || cart.length === 0) {
        e.preventDefault();
        alert("Your cart is empty. Please add items to your cart before proceeding.");
        return false;
      }

      // 2. Validate Address
      const hasSavedAddr = document.querySelector('input[name="address_id"]:checked');
      const newAddrInput = document.querySelector('textarea[name="new_address_line"]');
      if (!hasSavedAddr && (!newAddrInput || !newAddrInput.value.trim())) {
        e.preventDefault();
        alert("Please select or enter a delivery address.");
        if (newAddrInput) newAddrInput.focus();
        return false;
      }

      // 3. Validate Guest Fields if present
      const guestNameInput = document.querySelector('input[name="guest_name"]');
      if (guestNameInput && !guestNameInput.value.trim()) {
        e.preventDefault();
        alert("Please enter your full name.");
        guestNameInput.focus();
        return false;
      }

      // Always synchronize cart JSON and coupon before submit
      document.getElementById('checkoutCartJsonInput').value = JSON.stringify(cart);
      document.getElementById('checkoutCouponInput').value = activeCoupon || '';

      // If COD or Razorpay has already authorized the payment, let standard POST proceed
      if (selectedMode === 'COD' || (rzpPaymentId && rzpPaymentId.trim() !== '')) {
        return true;
      }

      // Online payment via Razorpay requested -> Launch Dummy Razorpay Sandbox Modal
      e.preventDefault();
      window.openDummyRazorpayModal(selectedMode);
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
