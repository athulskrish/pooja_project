<?php
declare(strict_types=1);
/**
 * Admin Dynamic Promo & Coupon Engine CRUD
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Promo Coupons Management';
$activePage = 'coupons';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid or expired CSRF token.');
    } else {
        $res = AdminService::handleCouponAction($_POST);
        setFlash($res['type'], $res['message']);
        header('Location: ' . ADMIN_URL . '/coupons.php');
        exit;
    }
}

$data = AdminService::getAdminCouponsData();
$coupons = $data['coupons'];
$activeCount = (int)$data['activeCount'];
$totalRedemptions = (int)$data['totalRedemptions'];
$totalSavings = (float)$data['totalSavings'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <span class="small fw-bold text-success text-uppercase">Promotions Engine</span>
    <h1 class="h3 fw-bold font-outfit text-dark mb-1"><i class="bi bi-ticket-perforated-fill text-success me-2"></i>Dynamic Promo Coupons</h1>
    <p class="text-muted mb-0">Manage percentage &amp; flat promo rules, minimum order thresholds, per-user limits, and expiry dates.</p>
  </div>
  <button type="button" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" style="background:#2E6F40; border-color:#2E6F40;" onclick="openCreateCouponModal()">
    <i class="bi bi-plus-lg me-1"></i> Create Promo Coupon
  </button>
</div>

<!-- KPI Summary Strip -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white">
      <div class="small text-muted fw-bold text-uppercase mb-1">Active Promo Codes</div>
      <div class="fs-3 fw-bold text-forest font-outfit"><?= $activeCount ?></div>
      <div class="small text-muted">Currently live on Cart &amp; Offers page</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white">
      <div class="small text-muted fw-bold text-uppercase mb-1">Total Redemptions</div>
      <div class="fs-3 fw-bold text-primary font-outfit"><?= number_format($totalRedemptions) ?></div>
      <div class="small text-muted">Tracked in `coupon_redemptions` ledger</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white">
      <div class="small text-muted fw-bold text-uppercase mb-1">Total Customer Savings</div>
      <div class="fs-3 fw-bold text-dark font-outfit"><?= formatInr($totalSavings) ?></div>
      <div class="small text-muted">Verified checkout discounts issued</div>
    </div>
  </div>
</div>

<!-- Coupons Data Table -->
<div class="card border-0 rounded-4 shadow-sm p-4 bg-white">
  <?php if (empty($coupons)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-ticket-perforated fs-1 opacity-50 mb-2 d-block"></i>
      <h5 class="fw-bold text-dark">No Dynamic Coupons Created</h5>
      <p class="small mb-3">Create your first promo code above to power the storefront offers.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="small text-muted text-uppercase border-bottom">
          <tr>
            <th>Code &amp; Title</th>
            <th>Discount Rule</th>
            <th>Eligibility &amp; Limits</th>
            <th>Validity</th>
            <th class="text-center">Usage</th>
            <th class="text-center">Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($coupons as $cp): ?>
            <?php
              $cid = (int)$cp['coupon_id'];
              $isPct = ($cp['discount_type'] ?? '') === 'percentage';
              $ruleLabel = $isPct
                ? ((int)$cp['discount_value'] . '% OFF' . ((float)$cp['max_discount'] > 0 ? (' (max ' . formatInr($cp['max_discount']) . ')') : ''))
                : ('Flat ' . formatInr($cp['discount_value']) . ' OFF');
              $isActive = !empty($cp['is_active']);
            ?>
            <tr>
              <td>
                <span class="badge bg-dark font-monospace fs-6"><?= e($cp['code']) ?></span>
                <div class="fw-semibold text-dark small mt-1"><?= e($cp['title']) ?></div>
                <div class="small text-muted text-truncate" style="max-width:240px;"><?= e($cp['description']) ?></div>
              </td>
              <td>
                <div class="fw-bold text-forest small"><?= e($ruleLabel) ?></div>
                <div class="small text-muted">Min order: <?= formatInr((float)$cp['min_order']) ?></div>
              </td>
              <td class="small">
                <div>Global Limit: <?= (int)$cp['usage_limit'] > 0 ? (int)$cp['usage_limit'] : 'Unlimited' ?></div>
                <div>Per-User Limit: <?= (int)$cp['per_user_limit'] > 0 ? (int)$cp['per_user_limit'] : 'Unlimited' ?></div>
                <?php if (!empty($cp['first_order_only'])): ?>
                  <span class="badge bg-info-subtle text-info border border-info-subtle mt-1">First Order Only</span>
                <?php endif; ?>
              </td>
              <td class="small text-muted">
                <?= !empty($cp['valid_until']) ? e(date('M j, Y', (int)strtotime((string)$cp['valid_until']))) : 'No Expiry' ?>
              </td>
              <td class="text-center">
                <div class="fw-bold font-monospace"><?= (int)$cp['used_count'] ?> uses</div>
                <div class="small text-muted"><?= formatInr((float)$cp['total_discount_given']) ?></div>
              </td>
              <td class="text-center">
                <form method="POST" action="<?= ADMIN_URL ?>/coupons.php" class="m-0">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="toggle_coupon">
                  <input type="hidden" name="coupon_id" value="<?= $cid ?>">
                  <button type="submit" class="btn btn-sm rounded-pill px-3 <?= $isActive ? 'btn-success-subtle text-success border border-success-subtle fw-bold' : 'btn-light text-muted border' ?>">
                    <?= $isActive ? 'Active' : 'Paused' ?>
                  </button>
                </form>
              </td>
              <td class="text-end">
                <div class="d-inline-flex gap-1">
                  <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2"
                          onclick="openEditCouponModal(<?= e(jsJson($cp)) ?>)" title="Edit Coupon">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="POST" action="<?= ADMIN_URL ?>/coupons.php" class="m-0" onsubmit="return confirm('Deactivate & archive coupon <?= e($cp['code']) ?>?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_coupon">
                    <input type="hidden" name="coupon_id" value="<?= $cid ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Archive Coupon">
                      <i class="bi bi-archive"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Create / Edit Coupon Modal -->
<div class="modal fade" id="couponFormModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold font-outfit" id="couponModalTitle">Create Promo Coupon</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="<?= ADMIN_URL ?>/coupons.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" id="couponActionInput" value="create_coupon">
        <input type="hidden" name="coupon_id" id="couponIdInput" value="0">

        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label small fw-bold">Coupon Code *</label>
              <input type="text" name="code" id="cpCode" class="form-control text-uppercase font-monospace fw-bold rounded-3" placeholder="MONSOON25" required>
            </div>
            <div class="col-md-8">
              <label class="form-label small fw-bold">Display Title *</label>
              <input type="text" name="title" id="cpTitle" class="form-control rounded-3" placeholder="Monsoon Feast Offer" required>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold">Description</label>
              <input type="text" name="description" id="cpDesc" class="form-control rounded-3" placeholder="25% OFF up to ₹150 on orders above ₹349">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold">Discount Type</label>
              <select name="discount_type" id="cpType" class="form-select rounded-3">
                <option value="percentage">Percentage (%)</option>
                <option value="flat">Flat Amount (₹)</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold">Discount Value *</label>
              <input type="number" step="0.01" min="1" name="discount_value" id="cpValue" class="form-control rounded-3" value="20" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold">Max Cap (₹)</label>
              <input type="number" step="0.01" min="0" name="max_discount" id="cpMax" class="form-control rounded-3" value="100">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold">Min Order Amount (₹)</label>
              <input type="number" step="0.01" min="0" name="min_order" id="cpMinOrder" class="form-control rounded-3" value="199">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold">Global Usage Limit (0 = Unlim.)</label>
              <input type="number" min="0" name="usage_limit" id="cpUsageLimit" class="form-control rounded-3" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold">Per-User Limit (0 = Unlim.)</label>
              <input type="number" min="0" name="per_user_limit" id="cpPerUser" class="form-control rounded-3" value="0">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Valid Until Date</label>
              <input type="date" name="valid_until" id="cpValidUntil" class="form-control rounded-3">
            </div>
            <div class="col-md-6 d-flex align-items-end gap-4 pb-2">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="first_order_only" value="1" id="cpFirstOrder">
                <label class="form-check-label small fw-semibold" for="cpFirstOrder">First Order Only</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="cpActive" checked>
                <label class="form-check-label small fw-semibold" for="cpActive">Active Immediately</label>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">Save Coupon</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openCreateCouponModal() {
  document.getElementById('couponModalTitle').textContent = 'Create Promo Coupon';
  document.getElementById('couponActionInput').value = 'create_coupon';
  document.getElementById('couponIdInput').value = '0';
  document.getElementById('cpCode').value = '';
  document.getElementById('cpTitle').value = '';
  document.getElementById('cpDesc').value = '';
  document.getElementById('cpType').value = 'percentage';
  document.getElementById('cpValue').value = '20';
  document.getElementById('cpMax').value = '100';
  document.getElementById('cpMinOrder').value = '199';
  document.getElementById('cpUsageLimit').value = '0';
  document.getElementById('cpPerUser').value = '0';
  document.getElementById('cpValidUntil').value = '';
  document.getElementById('cpFirstOrder').checked = false;
  document.getElementById('cpActive').checked = true;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('couponFormModal')).show();
}

function openEditCouponModal(cp) {
  document.getElementById('couponModalTitle').textContent = 'Edit Coupon: ' + (cp.code || '');
  document.getElementById('couponActionInput').value = 'update_coupon';
  document.getElementById('couponIdInput').value = cp.coupon_id || 0;
  document.getElementById('cpCode').value = cp.code || '';
  document.getElementById('cpTitle').value = cp.title || '';
  document.getElementById('cpDesc').value = cp.description || '';
  document.getElementById('cpType').value = cp.discount_type || 'percentage';
  document.getElementById('cpValue').value = cp.discount_value || 0;
  document.getElementById('cpMax').value = cp.max_discount || 0;
  document.getElementById('cpMinOrder').value = cp.min_order || 0;
  document.getElementById('cpUsageLimit').value = cp.usage_limit || 0;
  document.getElementById('cpPerUser').value = cp.per_user_limit || 0;
  document.getElementById('cpValidUntil').value = cp.valid_until ? String(cp.valid_until).substring(0, 10) : '';
  document.getElementById('cpFirstOrder').checked = Boolean(Number(cp.first_order_only || 0));
  document.getElementById('cpActive').checked = Boolean(Number(cp.is_active || 0));
  bootstrap.Modal.getOrCreateInstance(document.getElementById('couponFormModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
