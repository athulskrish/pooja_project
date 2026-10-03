<?php
/**
 * Offers, Discounts & Coupon Hub
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Deals, Offers & Coupon Codes';
$activePage = 'offers';

require_once __DIR__ . '/includes/header.php';

$coupons = get_available_coupons();
?>

<main class="py-4">
  <div class="container-xl">
    
    <div class="text-center mb-5">
      <span class="badge rounded-pill px-3 py-1 mb-2 fw-semibold" style="background:#CEFFDC; color:#243E2C;">
        🔥 SAVE BIG ON EVERY ORDER
      </span>
      <h2 class="fw-bold font-outfit text-dark mb-2">Available Coupons & Payment Offers</h2>
      <p class="text-muted small" style="max-width: 500px; margin: 0 auto;">
        Apply verified discount codes at checkout to unlock savings across top restaurants in Bangalore.
      </p>
    </div>

    <!-- Active Promo Codes Grid -->
    <div class="row g-4 mb-5">
      
      <!-- Coupon 1 -->
      <div class="col-md-4">
        <div class="card h-100 border-0 rounded-4 shadow-sm p-4 bg-white d-flex flex-column justify-content-between position-relative overflow-hidden">
          <div class="position-absolute top-0 end-0 m-3">
            <span class="badge" style="background:#2E6F40; color:#CEFFDC;">20% OFF</span>
          </div>

          <div>
            <div class="brand-icon-box mb-3" style="width: 44px; height: 44px; background:#EDFBF2; color:#2E6F40;">
              <i class="fa-solid fa-seedling fs-5"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1 font-outfit">Lush Forest Special</h5>
            <p class="text-muted small mb-3">
              Get 20% discount up to ₹100 on orders above ₹299. Exclusive to our Figma Lush Forest edition!
            </p>
          </div>

          <div class="d-flex align-items-center justify-content-between p-2 rounded-3 border bg-light">
            <code class="fs-5 fw-bold text-forest">LUSHFOREST</code>
            <button class="btn btn-sm btn-success rounded-pill px-3 fw-bold" onclick="navigator.clipboard.writeText('LUSHFOREST'); localStorage.setItem('pooja_active_coupon', 'LUSHFOREST'); showToast('Coupon LUSHFOREST applied & copied!');" style="background:#2E6F40; border-color:#2E6F40;">
              APPLY
            </button>
          </div>
        </div>
      </div>

      <!-- Coupon 2 -->
      <div class="col-md-4">
        <div class="card h-100 border-0 rounded-4 shadow-sm p-4 bg-white d-flex flex-column justify-content-between position-relative overflow-hidden">
          <div class="position-absolute top-0 end-0 m-3">
            <span class="badge bg-danger">50% OFF</span>
          </div>

          <div>
            <div class="brand-icon-box mb-3" style="width: 44px; height: 44px; background:#fff1f1; color:#D32F2F;">
              <i class="fa-solid fa-fire-flame-curved fs-5"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1 font-outfit">Mega Feast 50</h5>
            <p class="text-muted small mb-3">
              Enjoy flat 50% discount up to ₹120 on orders above ₹399. Valid across all biryani and curries.
            </p>
          </div>

          <div class="d-flex align-items-center justify-content-between p-2 rounded-3 border bg-light">
            <code class="fs-5 fw-bold text-danger">ZIPPY50</code>
            <button class="btn btn-sm btn-danger rounded-pill px-3 fw-bold" onclick="navigator.clipboard.writeText('ZIPPY50'); localStorage.setItem('pooja_active_coupon', 'ZIPPY50'); showToast('Coupon ZIPPY50 applied & copied!');">
              APPLY
            </button>
          </div>
        </div>
      </div>

      <!-- Coupon 3 -->
      <div class="col-md-4">
        <div class="card h-100 border-0 rounded-4 shadow-sm p-4 bg-white d-flex flex-column justify-content-between position-relative overflow-hidden">
          <div class="position-absolute top-0 end-0 m-3">
            <span class="badge bg-warning text-dark">FLAT ₹50</span>
          </div>

          <div>
            <div class="brand-icon-box mb-3" style="width: 44px; height: 44px; background:#fffbe6; color:#E5A100;">
              <i class="fa-solid fa-gift fs-5"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1 font-outfit">Welcome Treat</h5>
            <p class="text-muted small mb-3">
              Flat ₹50 OFF on your first food order above ₹199. Zero terms & conditions, instant savings!
            </p>
          </div>

          <div class="d-flex align-items-center justify-content-between p-2 rounded-3 border bg-light">
            <code class="fs-5 fw-bold text-dark">WELCOME</code>
            <button class="btn btn-sm btn-dark rounded-pill px-3 fw-bold" onclick="navigator.clipboard.writeText('WELCOME'); localStorage.setItem('pooja_active_coupon', 'WELCOME'); showToast('Coupon WELCOME applied & copied!');">
              APPLY
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- Bank & Payment Offers -->
    <h4 class="fw-bold mb-3 font-outfit text-dark"><i class="bi bi-bank text-success me-2"></i>Bank & Digital Payment Offers</h4>
    
    <div class="row g-3">
      <div class="col-md-6">
        <div class="card border-0 rounded-4 shadow-sm p-3 bg-white d-flex flex-row align-items-center gap-3">
          <div class="p-3 rounded-3 bg-light border text-primary fs-3">
            <i class="bi bi-credit-card"></i>
          </div>
          <div>
            <div class="fw-bold text-dark">HDFC Bank Credit & Debit Cards</div>
            <p class="small text-muted mb-0">Flat 15% instant discount up to ₹150 on minimum transaction of ₹600.</p>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card border-0 rounded-4 shadow-sm p-3 bg-white d-flex flex-row align-items-center gap-3">
          <div class="p-3 rounded-3 bg-light border text-success fs-3">
            <i class="bi bi-qr-code-scan"></i>
          </div>
          <div>
            <div class="fw-bold text-dark">Google Pay & UPI Instant Cashback</div>
            <p class="small text-muted mb-0">Earn up to ₹75 scratch card cashback on payments made via UPI.</p>
          </div>
        </div>
      </div>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
