<?php
/**
 * Offers & Deals Hub
 * MCSP-232: E-Commerce Platform for Food Order
 */
$pageTitle = 'Offers, Deals & Promo Codes - Zippy';
require_once __DIR__ . '/includes/header.php';
?>

  <main class="container-xl py-4" style="max-width: 960px;">
    
    <!-- Hero Banner -->
    <div class="p-4 p-md-5 rounded-4 mb-4 text-white shadow-sm" style="background: linear-gradient(135deg, #1C3322 0%, #243E2C 60%, #2E6F40 100%);">
      <span class="badge bg-mint text-pine fw-bold mb-2">OFFERS & PROMOTIONS</span>
      <h2 class="display-6 fw-bold mb-2">Save More on Every Meal</h2>
      <p class="lead text-white-50 mb-0" style="max-width: 600px;">
        Apply verified discount coupons, unlock bank cashbacks, and enjoy free delivery on your favorite dishes.
      </p>
    </div>

    <!-- Active Promo Codes Grid -->
    <h4 class="fw-bold text-pine mb-3"><i class="fa-solid fa-tags text-success me-2"></i> Verified Restaurant Promo Coupons</h4>
    
    <div class="row g-4 mb-5">
      
      <!-- Coupon 1: LUSHFOREST -->
      <div class="col-md-6">
        <div class="p-4 bg-white rounded-4 border shadow-xs h-100 d-flex flex-column justify-content-between" style="border-left: 5px solid var(--lush-forest) !important;">
          <div>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1">20% DISCOUNT</span>
              <span class="small text-muted fw-bold">NO MIN ORDER</span>
            </div>
            <h5 class="fw-bold text-pine mb-1">Get 20% OFF up to ₹100</h5>
            <p class="small text-muted mb-3">Valid across all Meghana Foods dishes, biryanis, curries, and sides.</p>
          </div>
          <div class="d-flex justify-content-between align-items-center pt-3 border-top">
            <span class="badge bg-light text-pine border font-monospace fs-6 px-3 py-2">LUSHFOREST</span>
            <button class="btn btn-sm btn-outline-success rounded-pill px-4 fw-bold" onclick="copyCouponCode('LUSHFOREST')">
              <i class="bi bi-copy me-1"></i> COPY CODE
            </button>
          </div>
        </div>
      </div>

      <!-- Coupon 2: ZIPPY50 -->
      <div class="col-md-6">
        <div class="p-4 bg-white rounded-4 border shadow-xs h-100 d-flex flex-column justify-content-between" style="border-left: 5px solid var(--lush-pine) !important;">
          <div>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="badge bg-danger bg-opacity-10 text-danger fw-bold px-3 py-1">50% DISCOUNT</span>
              <span class="small text-muted fw-bold">FIRST ORDER</span>
            </div>
            <h5 class="fw-bold text-pine mb-1">50% OFF on Your First Order</h5>
            <p class="small text-muted mb-3">Welcome deal for new customers. Maximum discount capped at ₹100.</p>
          </div>
          <div class="d-flex justify-content-between align-items-center pt-3 border-top">
            <span class="badge bg-light text-pine border font-monospace fs-6 px-3 py-2">ZIPPY50</span>
            <button class="btn btn-sm btn-outline-success rounded-pill px-4 fw-bold" onclick="copyCouponCode('ZIPPY50')">
              <i class="bi bi-copy me-1"></i> COPY CODE
            </button>
          </div>
        </div>
      </div>

      <!-- Coupon 3: STEALDEAL -->
      <div class="col-md-6">
        <div class="p-4 bg-white rounded-4 border shadow-xs h-100 d-flex flex-column justify-content-between" style="border-left: 5px solid var(--lush-sage) !important;">
          <div>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="badge bg-warning bg-opacity-25 text-dark fw-bold px-3 py-1">FLAT ₹125 OFF</span>
              <span class="small text-muted fw-bold">ABOVE ₹349</span>
            </div>
            <h5 class="fw-bold text-pine mb-1">Flat ₹125 OFF on Party Orders</h5>
            <p class="small text-muted mb-3">Valid on minimum cart value of ₹349. Great for family Biryani combos.</p>
          </div>
          <div class="d-flex justify-content-between align-items-center pt-3 border-top">
            <span class="badge bg-light text-pine border font-monospace fs-6 px-3 py-2">STEALDEAL</span>
            <button class="btn btn-sm btn-outline-success rounded-pill px-4 fw-bold" onclick="copyCouponCode('STEALDEAL')">
              <i class="bi bi-copy me-1"></i> COPY CODE
            </button>
          </div>
        </div>
      </div>

      <!-- Coupon 4: WELCOME20 -->
      <div class="col-md-6">
        <div class="p-4 bg-white rounded-4 border shadow-xs h-100 d-flex flex-column justify-content-between" style="border-left: 5px solid #0284C7 !important;">
          <div>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="badge bg-info bg-opacity-10 text-info fw-bold px-3 py-1">₹80 CASHBACK</span>
              <span class="small text-muted fw-bold">ALL USERS</span>
            </div>
            <h5 class="fw-bold text-pine mb-1">Instant ₹80 Off on Orders above ₹249</h5>
            <p class="small text-muted mb-3">Applicable on payments made via Google Pay, PhonePe, or Cards.</p>
          </div>
          <div class="d-flex justify-content-between align-items-center pt-3 border-top">
            <span class="badge bg-light text-pine border font-monospace fs-6 px-3 py-2">WELCOME20</span>
            <button class="btn btn-sm btn-outline-success rounded-pill px-4 fw-bold" onclick="copyCouponCode('WELCOME20')">
              <i class="bi bi-copy me-1"></i> COPY CODE
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- Bank Partner Deals -->
    <h4 class="fw-bold text-pine mb-3"><i class="bi bi-credit-card-2-front text-success me-2"></i> Bank & Payment Partner Deals</h4>
    <div class="row g-3">
      <div class="col-md-4">
        <div class="p-3 bg-white rounded-3 border">
          <div class="fw-bold text-pine"><i class="bi bi-bank me-2 text-danger"></i> HDFC Bank Cards</div>
          <div class="small text-muted">10% Instant Discount up to ₹150 on HDFC Credit & Debit Cards on orders &ge; ₹500.</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-3 bg-white rounded-3 border">
          <div class="fw-bold text-pine"><i class="bi bi-google me-2 text-primary"></i> Google Pay UPI</div>
          <div class="small text-muted">Win up to ₹50 cashback scratch card on orders &ge; ₹299 using Google Pay.</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-3 bg-white rounded-3 border">
          <div class="fw-bold text-pine"><i class="bi bi-lightning-charge-fill me-2 text-warning"></i> CRED Pay</div>
          <div class="small text-muted">Flat ₹60 cashback for CRED members with minimum bill amount of ₹400.</div>
        </div>
      </div>
    </div>

  </main>

  <script>
    function copyCouponCode(code) {
      navigator.clipboard.writeText(code).then(() => {
        showToast(`Coupon "${code}" copied to clipboard! Paste it at checkout.`);
      }).catch(() => {
        showToast(`Coupon: ${code}`);
      });
    }
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
