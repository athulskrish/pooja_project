<?php
/**
 * Checkout & Payment Processing
 * MCSP-232: E-Commerce Platform for Food Order
 * Concurrency Control: ACID Database Transaction with Row-Level Locking (Pessimistic)
 */
$pageTitle = 'Checkout & Payment - Zippy';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/controllers/OrderController.php';

// Process order submission if POST
OrderController::handleCheckout();

$data = OrderController::getCheckoutPageData();
$addresses = $data['addresses'];
$user = $data['user'];
?>

  <main class="container-xl py-4">
    <div class="row g-4">
      
      <!-- LEFT COLUMN: Steps (Account, Address, Payment Method) -->
      <div class="col-lg-7 col-xl-8">
        
        <!-- Step 1: Logged In Account -->
        <div class="p-4 bg-white rounded-4 border shadow-xs mb-4">
          <div class="d-flex align-items-center gap-3">
            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
              <i class="bi bi-check2 fs-5"></i>
            </div>
            <div>
              <h5 class="fw-bold text-pine mb-0">Logged In Account</h5>
              <span class="small text-muted"><?= sanitize($user['full_name'] ?? 'Pooja JP') ?> • <?= sanitize($user['phone_number'] ?? '+91 98765 43210') ?></span>
            </div>
          </div>
        </div>

        <!-- Checkout Form Starts Here -->
        <form method="POST" action="checkout.php" id="checkoutForm">
          <!-- Hidden Inputs for dynamic cart payload -->
          <input type="hidden" name="cart_data" id="hiddenCartData">
          <input type="hidden" name="coupon_code" id="hiddenCouponCode">

          <!-- Step 2: Delivery Address Selection -->
          <div class="p-4 bg-white rounded-4 border shadow-xs mb-4">
            <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
              <div class="d-flex align-items-center gap-3">
                <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
                  1
                </div>
                <div>
                  <h5 class="fw-bold text-pine mb-0">Select Delivery Destination</h5>
                  <span class="small text-muted">Choose saved address or enter new address</span>
                </div>
              </div>
              <a href="manage-addresses.php" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold">
                + Add New Address
              </a>
            </div>

            <div class="row g-3">
              <?php foreach ($addresses as $idx => $addr): ?>
                <div class="col-md-6">
                  <label class="address-box p-3 rounded-4 border d-block cursor-pointer position-relative <?= $addr['is_default'] ? 'border-success bg-light shadow-xs' : 'bg-white' ?>" style="cursor: pointer;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <span class="badge bg-success bg-opacity-10 text-success fw-bold">
                        <i class="bi bi-<?= strtolower($addr['address_type']) === 'home' ? 'house-door' : 'briefcase' ?> me-1"></i>
                        <?= sanitize($addr['address_type']) ?>
                      </span>
                      <input type="radio" name="address_id" value="<?= $addr['address_id'] ?>" class="form-check-input" <?= $addr['is_default'] ? 'checked' : '' ?>>
                    </div>
                    <h6 class="fw-bold text-pine mb-1"><?= sanitize($addr['title']) ?></h6>
                    <p class="small text-muted mb-2"><?= sanitize($addr['address_line']) ?> - <?= sanitize($addr['pincode']) ?></p>
                    <span class="badge bg-success bg-opacity-25 text-success small fw-bold">25 MINS ETA</span>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Step 3: Payment Method Selection -->
          <div class="p-4 bg-white rounded-4 border shadow-xs mb-4">
            <div class="d-flex align-items-center gap-3 pb-3 border-bottom mb-3">
              <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
                2
              </div>
              <div>
                <h5 class="fw-bold text-pine mb-0">Select Payment Method</h5>
                <span class="small text-muted">100% Encrypted & Secure Payment Gateway</span>
              </div>
            </div>

            <div class="accordion" id="paymentAccordion">
              
              <!-- Option 1: Instant UPI -->
              <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
                <h2 class="accordion-header">
                  <label class="accordion-button fw-bold text-pine d-flex align-items-center gap-2" for="payUpiRadio">
                    <input type="radio" name="payment_mode" value="UPI" id="payUpiRadio" class="form-check-input me-2" checked>
                    <i class="bi bi-phone text-success fs-5"></i> UPI Instant Pay (Google Pay / PhonePe / Paytm / QR)
                  </label>
                </h2>
                <div class="accordion-collapse collapse show p-3 bg-light border-top">
                  <div class="d-flex align-items-center gap-3 mb-2">
                    <span class="badge bg-white text-dark border p-2"><i class="bi bi-google text-primary me-1"></i> GPay</span>
                    <span class="badge bg-white text-dark border p-2"><i class="bi bi-phone text-purple me-1"></i> PhonePe</span>
                    <span class="badge bg-white text-dark border p-2"><i class="bi bi-wallet2 text-info me-1"></i> Paytm</span>
                  </div>
                  <div class="input-group">
                    <input type="text" class="form-control" placeholder="Enter Virtual UPI ID (e.g. pooja@oksbi)" value="pooja@okaxis">
                    <button class="btn btn-outline-success fw-bold" type="button">VERIFY VPA</button>
                  </div>
                </div>
              </div>

              <!-- Option 2: Cards -->
              <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
                <h2 class="accordion-header">
                  <label class="accordion-button collapsed fw-bold text-pine d-flex align-items-center gap-2" for="payCardRadio">
                    <input type="radio" name="payment_mode" value="CREDIT_CARD" id="payCardRadio" class="form-check-input me-2">
                    <i class="bi bi-credit-card text-success fs-5"></i> Credit or Debit Card (Visa / Mastercard / RuPay)
                  </label>
                </h2>
                <div class="accordion-collapse collapse p-3 bg-light border-top">
                  <div class="mb-2">
                    <label class="form-label small fw-bold">Card Number</label>
                    <input type="text" class="form-control" placeholder="4111 2222 3333 4444" maxlength="19">
                  </div>
                  <div class="row g-2">
                    <div class="col-6">
                      <label class="form-label small fw-bold">Expiry Date</label>
                      <input type="text" class="form-control" placeholder="MM/YY">
                    </div>
                    <div class="col-6">
                      <label class="form-label small fw-bold">CVV Code</label>
                      <input type="password" class="form-control" placeholder="123" maxlength="3">
                    </div>
                  </div>
                </div>
              </div>

              <!-- Option 3: COD -->
              <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
                <h2 class="accordion-header">
                  <label class="accordion-button collapsed fw-bold text-pine d-flex align-items-center gap-2" for="payCodRadio">
                    <input type="radio" name="payment_mode" value="COD" id="payCodRadio" class="form-check-input me-2">
                    <i class="bi bi-cash-stack text-success fs-5"></i> Pay on Delivery (Cash / Dynamic UPI on arrival)
                  </label>
                </h2>
                <div class="accordion-collapse collapse p-3 bg-light border-top">
                  <p class="small text-muted mb-0">Our delivery rider will present a dynamic digital UPI QR code or accept exact cash upon arrival.</p>
                </div>
              </div>

            </div>
          </div>

          <!-- Academic Rigor Callout Box -->
          <div class="p-3 rounded-4 bg-white border shadow-xs d-flex align-items-center gap-3">
            <i class="bi bi-cpu text-success fs-2"></i>
            <div>
              <div class="fw-bold small text-pine">ACID Transaction & Concurrency Row-Locking Engaged</div>
              <div class="small text-muted">Orders are committed using MySQL InnoDB transactions (<code>START TRANSACTION ... FOR UPDATE ... COMMIT</code>) ensuring real-time stock integrity.</div>
            </div>
          </div>

      </div>

      <!-- RIGHT COLUMN: Order Review & Submit Order -->
      <div class="col-lg-5 col-xl-4">
        <div class="p-4 bg-white rounded-4 border shadow-sm sticky-top" style="top: 80px;">
          
          <!-- Restaurant Mini Header -->
          <div class="d-flex align-items-center gap-3 pb-3 border-bottom mb-3">
            <img src="https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?auto=format&fit=crop&w=120&q=80" class="rounded-3 object-fit-cover shadow-xs" style="width: 50px; height: 50px;" alt="Meghana Foods">
            <div>
              <h6 class="fw-bold text-pine mb-0">Meghana Foods</h6>
              <span class="small text-muted">Indiranagar Outlet</span>
            </div>
          </div>

          <!-- Items in Cart Preview -->
          <div id="checkout-items-container" class="d-flex flex-column gap-2 mb-3">
            <!-- Rendered by app.js -->
          </div>

          <!-- Coupon Input -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-pine">Apply Promo Code</label>
            <div class="input-group mb-2">
              <input type="text" id="couponCodeInput" class="form-control font-monospace" placeholder="CODE (e.g. LUSHFOREST)">
              <button class="btn btn-success fw-bold px-3" type="button" onclick="applyCoupon()" style="background-color: var(--lush-forest);">APPLY</button>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <span class="badge bg-light text-success border cursor-pointer font-monospace" onclick="document.getElementById('couponCodeInput').value='LUSHFOREST'; applyCoupon();">LUSHFOREST</span>
              <span class="badge bg-light text-success border cursor-pointer font-monospace" onclick="document.getElementById('couponCodeInput').value='ZIPPY50'; applyCoupon();">ZIPPY50</span>
            </div>
          </div>

          <!-- Bill Summary -->
          <div class="d-flex flex-column gap-2 border-top pt-3 text-muted small">
            <div class="d-flex justify-content-between">
              <span>Item Total</span>
              <span class="fw-bold text-pine" id="bill-item-total">₹0</span>
            </div>
            <div class="d-flex justify-content-between">
              <span>Delivery Fee (2.4 km)</span>
              <span class="fw-bold text-pine" id="bill-delivery-fee">₹35</span>
            </div>
            <div class="d-flex justify-content-between">
              <span>Platform Fee</span>
              <span class="fw-bold text-pine" id="bill-platform-fee">₹5</span>
            </div>
            <div class="d-flex justify-content-between">
              <span>GST & Restaurant Taxes (5%)</span>
              <span class="fw-bold text-pine" id="bill-taxes">₹0</span>
            </div>
            <div class="d-flex justify-content-between text-success fw-bold" id="bill-discount-row" style="display: none;">
              <span>Coupon Savings</span>
              <span id="bill-discount-amount">-₹0</span>
            </div>
          </div>

          <!-- Final Total -->
          <div class="d-flex justify-content-between align-items-center pt-3 mt-3 border-top border-2">
            <span class="fw-bold text-pine fs-6">TO PAY</span>
            <span class="fw-bold text-success fs-5" id="bill-final-total">₹0</span>
          </div>

          <!-- Submit Button -->
          <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold py-3 mt-4 shadow-sm" style="background-color: var(--lush-forest);">
            <span>CONFIRM & PLACE ORDER</span>
            <i class="bi bi-arrow-right ms-2"></i>
          </button>

        </form>
        <!-- Checkout Form Ends Here -->

        <p class="text-center small text-muted mt-3 mb-0" style="font-size: 0.72rem;">
          By placing order, you agree to Zippy's <a href="terms.php" class="text-success text-decoration-underline">Terms of Service</a>.
        </p>

        </div>
      </div>

    </div>
  </main>

  <script>
    // Prepare hidden form inputs before submit
    document.getElementById('checkoutForm').addEventListener('submit', function(e) {
      document.getElementById('hiddenCartData').value = JSON.stringify(cart);
      document.getElementById('hiddenCouponCode').value = activeCoupon || '';
    });
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
