<?php
declare(strict_types=1);
/**
 * Offers, Discounts & Coupon Hub
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Deals, Offers & Coupon Codes';
$activePage = 'offers';

require_once __DIR__ . '/includes/header.php';

$coupons = getAvailableCoupons();
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
      <?php
        $accentThemes = [
          ['badgeStyle' => 'background:#2E6F40; color:#CEFFDC;', 'iconBg' => 'background:#EDFBF2; color:#2E6F40;', 'icon' => 'fa-solid fa-seedling', 'codeClass' => 'text-forest', 'btnClass' => 'btn-success', 'btnStyle' => 'background:#2E6F40; border-color:#2E6F40;'],
          ['badgeStyle' => 'background:#dc3545; color:#fff;', 'iconBg' => 'background:#fff1f1; color:#D32F2F;', 'icon' => 'fa-solid fa-fire-flame-curved', 'codeClass' => 'text-danger', 'btnClass' => 'btn-danger', 'btnStyle' => ''],
          ['badgeStyle' => 'background:#ffc107; color:#212529;', 'iconBg' => 'background:#fffbe6; color:#E5A100;', 'icon' => 'fa-solid fa-gift', 'codeClass' => 'text-dark', 'btnClass' => 'btn-dark', 'btnStyle' => ''],
        ];
        $cIdx = 0;
      ?>
      <?php if (!empty($coupons)): ?>
        <?php foreach ($coupons as $cp): ?>
          <?php
            $theme = $accentThemes[$cIdx % count($accentThemes)];
            $cIdx++;
            $cpCode = (string)($cp['code'] ?? '');
            $cpTitle = (string)($cp['title'] ?? $cpCode);
            $cpDesc = (string)($cp['description'] ?? '');
            $badgeTxt = ($cp['discount_type'] ?? '') === 'percentage'
              ? ((int)$cp['discount_value'] . '% OFF')
              : ('FLAT ₹' . (int)$cp['discount_value']);
          ?>
          <div class="col-md-4">
            <div class="card h-100 border-0 rounded-4 shadow-sm p-4 bg-white d-flex flex-column justify-content-between position-relative overflow-hidden">
              <div class="position-absolute top-0 end-0 m-3 d-flex gap-1">
                <?php if (!empty($cp['first_order_only'])): ?>
                  <span class="badge bg-info-subtle text-info border border-info-subtle">1st Order</span>
                <?php endif; ?>
                <span class="badge" style="<?= e($theme['badgeStyle']) ?>"><?= e($badgeTxt) ?></span>
              </div>

              <div>
                <div class="brand-icon-box mb-3" style="width: 44px; height: 44px; <?= e($theme['iconBg']) ?>">
                  <i class="<?= e($theme['icon']) ?> fs-5"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1 font-outfit"><?= e($cpTitle) ?></h5>
                <p class="text-muted small mb-2"><?= e($cpDesc) ?></p>
                <div class="d-flex flex-wrap gap-2 small text-muted mb-3">
                  <?php if ((float)($cp['min_order'] ?? 0) > 0): ?>
                    <span><i class="bi bi-bag me-1"></i>Min order ₹<?= number_format((float)$cp['min_order'], 0) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($cp['valid_until'])): ?>
                    <span><i class="bi bi-calendar2 me-1"></i>Until <?= e(date('M j', strtotime((string)$cp['valid_until']))) ?></span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="d-flex align-items-center justify-content-between p-2 rounded-3 border bg-light">
                <code class="fs-5 fw-bold <?= e($theme['codeClass']) ?>"><?= e($cpCode) ?></code>
                <button type="button" class="btn btn-sm <?= e($theme['btnClass']) ?> rounded-pill px-3 fw-bold" style="<?= e($theme['btnStyle']) ?>"
                        onclick="navigator.clipboard?.writeText(<?= e(jsJson($cpCode)) ?>); localStorage.setItem('pooja_active_coupon', <?= e(jsJson($cpCode)) ?>); showToast('Coupon ' + <?= e(jsJson($cpCode)) ?> + ' applied & copied!');">
                  APPLY
                </button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="col-12">
          <div class="card border-0 rounded-4 shadow-sm p-5 text-center bg-white">
            <p class="text-muted mb-0">No active promo codes are currently scheduled. Check back soon!</p>
          </div>
        </div>
      <?php endif; ?>
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
