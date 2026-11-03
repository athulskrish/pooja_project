<?php
/**
 * Admin Operations Dashboard
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Admin Operations Dashboard';
$activePage = 'dashboard';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/FCFSQueue.php';

require_admin();

$triageExceptions = FCFSQueue::getTriageExceptions();
$lowStockCount = (int)(db_fetch_val(
    "SELECT COUNT(*) FROM `menu_items` WHERE stock_quantity <= 15 AND is_available = 1"
) ?: 0);

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <span class="small fw-bold text-success text-uppercase">Admin workspace</span>
    <h1 class="h3 fw-bold font-outfit text-dark mb-1">Operations overview</h1>
    <p class="text-muted mb-0">Resolve exceptions first, then open the workspace for the task at hand.</p>
  </div>
  <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-2">
    <span class="live-pulse-dot green me-1"></span> Store operations
  </span>
</div>

<section class="card border rounded-4 shadow-xs bg-white mb-4 overflow-hidden" style="border-color:#E2ECE5 !important;" aria-labelledby="attentionHeading">
  <div class="p-3 p-xl-4 border-bottom d-flex align-items-center justify-content-between gap-3">
    <div>
      <h2 class="h5 fw-bold font-outfit text-dark mb-1" id="attentionHeading">Needs attention</h2>
      <p class="small text-muted mb-0">Only exceptions are shown here; full order, kitchen, delivery, and stock details live in their own workspaces.</p>
    </div>
    <?php if (!empty($triageExceptions)): ?>
      <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">
        <?= count($triageExceptions) ?> open exception<?= count($triageExceptions) === 1 ? '' : 's' ?>
      </span>
    <?php endif; ?>
  </div>

  <div class="p-3 p-xl-4">
    <?php if (!empty($triageExceptions)): ?>
      <div class="d-flex flex-column gap-2">
        <?php foreach ($triageExceptions as $exception): ?>
          <a href="<?= e($exception['action_url']) ?>" class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 rounded-3 border text-decoration-none text-dark">
            <span class="d-flex align-items-center gap-2">
              <span class="badge <?= e($exception['badge_class']) ?> rounded-pill">
                <i class="bi <?= e($exception['icon']) ?> me-1"></i><?= e($exception['type']) ?>
              </span>
              <span class="fw-semibold"><?= e($exception['title']) ?></span>
            </span>
            <span class="small fw-bold text-success">Review <i class="bi bi-arrow-right ms-1"></i></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="d-flex align-items-center gap-3 p-3 rounded-3 bg-success-subtle text-success">
        <i class="bi bi-check-circle-fill fs-4"></i>
        <div>
          <div class="fw-bold">No operational exceptions</div>
          <div class="small">There are no current queue, dispatch, or courier cash alerts.</div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($lowStockCount > 0): ?>
      <a href="<?= ADMIN_URL ?>/dishes.php" class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 mt-2 rounded-3 border border-warning-subtle bg-warning-subtle text-decoration-none text-dark">
        <span><i class="bi bi-exclamation-triangle-fill text-warning-emphasis me-2"></i><strong><?= $lowStockCount ?> menu item<?= $lowStockCount === 1 ? '' : 's' ?></strong> at or below the low-stock threshold</span>
        <span class="small fw-bold text-warning-emphasis">Review stock <i class="bi bi-arrow-right ms-1"></i></span>
      </a>
    <?php endif; ?>
  </div>
</section>

<section aria-labelledby="workspacesHeading">
  <div class="mb-3">
    <h2 class="h5 fw-bold font-outfit text-dark mb-1" id="workspacesHeading">Open a workspace</h2>
    <p class="small text-muted mb-0">Each workspace is the source of truth for its operational or business data.</p>
  </div>

  <div class="row g-3">
    <div class="col-sm-6 col-xl-4">
      <a href="<?= ADMIN_URL ?>/orders.php?status=LIVE" class="card h-100 p-3 p-xl-4 border rounded-4 shadow-xs text-decoration-none text-dark transition-hover" style="border-color:#E2ECE5 !important;">
        <div class="d-flex align-items-center gap-3 mb-2">
          <span class="rounded-3 p-2 text-success bg-success-subtle"><i class="bi bi-receipt fs-5"></i></span>
          <h3 class="h6 fw-bold mb-0">Live orders</h3>
        </div>
        <p class="small text-muted mb-0">Find an order, review its SLA and update its status.</p>
      </a>
    </div>
    <div class="col-sm-6 col-xl-4">
      <a href="<?= ADMIN_URL ?>/kitchen-view.php" class="card h-100 p-3 p-xl-4 border rounded-4 shadow-xs text-decoration-none text-dark transition-hover" style="border-color:#E2ECE5 !important;">
        <div class="d-flex align-items-center gap-3 mb-2">
          <span class="rounded-3 p-2 text-warning bg-warning-subtle"><i class="fa-solid fa-fire fs-5"></i></span>
          <h3 class="h6 fw-bold mb-0">Kitchen &amp; staffing</h3>
        </div>
        <p class="small text-muted mb-0">Review the cook queue, stove load, and staff roster.</p>
      </a>
    </div>
    <div class="col-sm-6 col-xl-4">
      <a href="<?= ADMIN_URL ?>/delivery-view.php" class="card h-100 p-3 p-xl-4 border rounded-4 shadow-xs text-decoration-none text-dark transition-hover" style="border-color:#E2ECE5 !important;">
        <div class="d-flex align-items-center gap-3 mb-2">
          <span class="rounded-3 p-2 text-primary bg-primary-subtle"><i class="bi bi-bicycle fs-5"></i></span>
          <h3 class="h6 fw-bold mb-0">Delivery fleet</h3>
        </div>
        <p class="small text-muted mb-0">Check rider availability and active deliveries.</p>
      </a>
    </div>
    <div class="col-sm-6 col-xl-4">
      <a href="<?= ADMIN_URL ?>/financial-transactions.php" class="card h-100 p-3 p-xl-4 border rounded-4 shadow-xs text-decoration-none text-dark transition-hover" style="border-color:#E2ECE5 !important;">
        <div class="d-flex align-items-center gap-3 mb-2">
          <span class="rounded-3 p-2 text-success bg-success-subtle"><i class="bi bi-cash-coin fs-5"></i></span>
          <h3 class="h6 fw-bold mb-0">Cash &amp; COD closeout</h3>
        </div>
        <p class="small text-muted mb-0">Reconcile settlements and cash collected.</p>
      </a>
    </div>
    <div class="col-sm-6 col-xl-4">
      <a href="<?= ADMIN_URL ?>/dishes.php" class="card h-100 p-3 p-xl-4 border rounded-4 shadow-xs text-decoration-none text-dark transition-hover" style="border-color:#E2ECE5 !important;">
        <div class="d-flex align-items-center gap-3 mb-2">
          <span class="rounded-3 p-2 text-warning bg-warning-subtle"><i class="fa-solid fa-bowl-food fs-5"></i></span>
          <h3 class="h6 fw-bold mb-0">Menu &amp; stock</h3>
        </div>
        <p class="small text-muted mb-0">Manage availability and inventory levels.</p>
      </a>
    </div>
    <div class="col-sm-6 col-xl-4">
      <a href="<?= ADMIN_URL ?>/reports.php" class="card h-100 p-3 p-xl-4 border rounded-4 shadow-xs text-decoration-none text-dark transition-hover" style="border-color:#E2ECE5 !important;">
        <div class="d-flex align-items-center gap-3 mb-2">
          <span class="rounded-3 p-2 text-primary bg-primary-subtle"><i class="bi bi-graph-up-arrow fs-5"></i></span>
          <h3 class="h6 fw-bold mb-0">Business insights</h3>
        </div>
        <p class="small text-muted mb-0">Explore sales trends, demand patterns, and forecast risks.</p>
      </a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
