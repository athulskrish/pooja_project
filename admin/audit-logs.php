<?php
/**
 * Regulatory & Operations Audit Trail Viewer
 * Zippy - Online Food Ordering Platform
 * Student: Pooja JP | Course: MCSP-232 (MCA Major Project)
 * Implements: UC-22 (Audit System Logs & Trails)
 */

$pageTitle = 'Order Audit Trail Logs';
$activePage = 'audit_logs';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
$currentUser = current_user();

require_once __DIR__ . '/includes/header.php';

// Filter parameters
$searchOrderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$filterStatus = clean_input($_GET['status'] ?? '');

$where = "1=1";
$params = [];

if ($searchOrderId > 0) {
    $where .= " AND a.order_id = :oid";
    $params[':oid'] = $searchOrderId;
}

if (!empty($filterStatus)) {
    $where .= " AND a.new_status = :st";
    $params[':st'] = $filterStatus;
}

$auditLogs = [];
try {
    $auditLogs = db_fetch_all("
        SELECT a.*, 
               u.full_name AS actor_name, 
               r.role_name AS actor_role,
               o.total_amount,
               o.placed_at
        FROM `order_audit_trail` a
        LEFT JOIN `users` u ON a.updated_by_user_id = u.user_id
        LEFT JOIN `roles` r ON u.role_id = r.role_id
        LEFT JOIN `orders` o ON a.order_id = o.order_id
        WHERE $where
        ORDER BY a.change_timestamp DESC, a.audit_id DESC
        LIMIT 100
    ", $params);
} catch (Exception $e) {
    $auditLogs = [];
}

// Summary stats
$totalAuditsCount = db_fetch_val("SELECT COUNT(*) FROM `order_audit_trail`") ?: 0;
$uniqueOrdersAudited = db_fetch_val("SELECT COUNT(DISTINCT order_id) FROM `order_audit_trail`") ?: 0;
?>

<div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2">
        <h4 class="fw-bold font-outfit mb-0 text-dark">Immutable Order Audit Trail</h4>
        <span class="badge rounded-pill bg-success bg-opacity-10 text-success fw-bold px-3 py-1">UC-22 Compliance</span>
      </div>
      <p class="text-muted small mb-0 mt-1">Tamper-evident transactional state changes recorded across all 4 system actors.</p>
    </div>

    <!-- Quick Stats -->
    <div class="d-flex gap-3">
      <div class="bg-light px-3 py-2 rounded-3 border text-center">
        <div class="small text-muted fw-bold">Total Transitions</div>
        <div class="fs-5 fw-bold font-monospace text-dark"><?= (int)$totalAuditsCount ?></div>
      </div>
      <div class="bg-light px-3 py-2 rounded-3 border text-center">
        <div class="small text-muted fw-bold">Orders Tracked</div>
        <div class="fs-5 fw-bold font-monospace text-forest" style="color:#2E6F40;"><?= (int)$uniqueOrdersAudited ?></div>
      </div>
    </div>
  </div>

  <!-- Filters Bar -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 bg-light p-3 rounded-3 border">
    <form method="GET" action="<?= ADMIN_URL ?>/audit-logs.php" class="d-flex flex-wrap align-items-center gap-2 m-0">
      <span class="small fw-bold text-muted">Filter by Status:</span>
      <a href="<?= ADMIN_URL ?>/audit-logs.php" class="btn btn-sm rounded-pill <?= empty($filterStatus) && empty($searchOrderId) ? 'btn-dark' : 'btn-white border' ?>">All</a>
      <a href="<?= ADMIN_URL ?>/audit-logs.php?status=CONFIRMED" class="btn btn-sm rounded-pill <?= ($filterStatus === 'CONFIRMED') ? 'btn-info text-dark' : 'btn-white border' ?>">Confirmed</a>
      <a href="<?= ADMIN_URL ?>/audit-logs.php?status=COOKING" class="btn btn-sm rounded-pill <?= ($filterStatus === 'COOKING') ? 'btn-warning text-dark' : 'btn-white border' ?>">Cooking</a>
      <a href="<?= ADMIN_URL ?>/audit-logs.php?status=READY" class="btn btn-sm rounded-pill <?= ($filterStatus === 'READY') ? 'btn-primary' : 'btn-white border' ?>">Ready</a>
      <a href="<?= ADMIN_URL ?>/audit-logs.php?status=OUT_FOR_DELIVERY" class="btn btn-sm rounded-pill <?= ($filterStatus === 'OUT_FOR_DELIVERY') ? 'btn-warning' : 'btn-white border' ?>">Out for Delivery</a>
      <a href="<?= ADMIN_URL ?>/audit-logs.php?status=DELIVERED" class="btn btn-sm rounded-pill <?= ($filterStatus === 'DELIVERED') ? 'btn-success' : 'btn-white border' ?>">Delivered</a>
    </form>

    <form method="GET" action="<?= ADMIN_URL ?>/audit-logs.php" class="d-flex align-items-center gap-2 m-0">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
        <input type="number" name="order_id" class="form-control border-start-0" placeholder="Search Order ID..." value="<?= $searchOrderId > 0 ? $searchOrderId : '' ?>" style="width: 140px;">
        <button class="btn btn-success" type="submit" style="background:#2E6F40; border-color:#2E6F40;">Search</button>
      </div>
      <?php if ($searchOrderId > 0 || !empty($filterStatus)): ?>
        <a href="<?= ADMIN_URL ?>/audit-logs.php" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Table -->
  <div class="table-responsive">
    <table class="table table-hover align-middle small mb-0">
      <thead class="table-light">
        <tr>
          <th style="width: 70px;">Log ID</th>
          <th style="width: 110px;">Order #</th>
          <th>Lifecycle Status Transition</th>
          <th>Initiated By (Actor)</th>
          <th>Order Total</th>
          <th>Logged Timestamp (IST)</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($auditLogs)): ?>
          <tr>
            <td colspan="6" class="text-center py-4 text-muted">
              No audit trail records match the specified filters.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($auditLogs as $log): ?>
            <tr>
              <td class="font-monospace text-muted fw-bold">#<?= (int)$log['audit_id'] ?></td>
              <td>
                <a href="<?= ADMIN_URL ?>/orders.php?status=&order_id=<?= (int)$log['order_id'] ?>" class="fw-bold font-monospace text-decoration-none text-dark">
                  #ZIP-<?= (int)$log['order_id'] ?>
                </a>
              </td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-secondary bg-opacity-25 text-dark border">
                    <?= e($log['old_status'] ?: 'NEW_CART') ?>
                  </span>
                  <i class="bi bi-arrow-right text-muted"></i>
                  <?= get_status_badge($log['new_status']) ?>
                </div>
              </td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge rounded-pill <?php
                    switch($log['actor_role']) {
                        case 'ADMIN': echo 'bg-danger'; break;
                        case 'KITCHEN': echo 'bg-warning text-dark'; break;
                        case 'RIDER': echo 'bg-info text-dark'; break;
                        default: echo 'bg-success'; break;
                    }
                  ?>">
                    <?= e($log['actor_role'] ?: 'SYSTEM') ?>
                  </span>
                  <span class="fw-semibold text-dark"><?= e($log['actor_name'] ?: 'System Trigger') ?></span>
                </div>
              </td>
              <td>
                <span class="fw-bold font-monospace text-dark">
                  <?= !empty($log['total_amount']) ? format_inr($log['total_amount']) : '—' ?>
                </span>
              </td>
              <td>
                <div class="text-dark fw-semibold"><?= date('d M Y, h:i:s A', strtotime($log['change_timestamp'])) ?></div>
                <div class="small text-muted"><?= date('e', strtotime($log['change_timestamp'])) ?> (Server Time)</div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
