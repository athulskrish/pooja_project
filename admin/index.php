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
usort($triageExceptions, function ($a, $b) {
    $rank = ['CRITICAL' => 3, 'WARNING' => 2, 'INFO' => 1];
    return ($rank[$b['severity']] ?? 0) <=> ($rank[$a['severity']] ?? 0);
});

$lowStockCount = (int)(db_fetch_val(
    "SELECT COUNT(*) FROM `menu_items` WHERE stock_quantity <= 15 AND is_available = 1"
) ?: 0);

$liveOrdersCount = (int)(db_fetch_val(
    "SELECT COUNT(*) FROM `orders` WHERE order_status IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY', 'DELIVERING')"
) ?: 0);

$ordersAtRisk = (int)(db_fetch_val(
    "SELECT COUNT(*) FROM `orders` WHERE order_status IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY') AND TIMESTAMPDIFF(MINUTE, placed_at, NOW()) > 30"
) ?: 0);

$codPending = (int)(db_fetch_val(
    "SELECT COUNT(*) FROM `money_transactions` WHERE txn_category = 'CUSTOMER_PAYMENT_COD' AND status != 'COMPLETED'"
) ?: 0);

$deliveryDelays = (int)(db_fetch_val(
    "SELECT COUNT(*) FROM `orders` WHERE order_status IN ('OUT_FOR_DELIVERY', 'DELIVERING') AND TIMESTAMPDIFF(MINUTE, placed_at, NOW()) > 45"
) ?: 0);

$triageSeverityCounts = ['CRITICAL' => 0, 'WARNING' => 0, 'INFO' => 0];
foreach ($triageExceptions as $exception) {
    $triageSeverityCounts[$exception['severity']] = ($triageSeverityCounts[$exception['severity']] ?? 0) + 1;
}

$hourlyOrderCounts = db_fetch_all(
    "SELECT HOUR(placed_at) AS hour_label, COUNT(*) AS order_count FROM `orders` WHERE DATE(placed_at) = CURDATE() AND order_status != 'CANCELLED' GROUP BY HOUR(placed_at) ORDER BY HOUR(placed_at) ASC"
);
$hourLabels = [];
$hourValues = [];
$hours = [];
for ($i = 0; $i <= 23; $i++) {
    $label = sprintf('%02d:00', $i);
    $hours[$i] = 0;
    $hourLabels[] = $label;
}
foreach ($hourlyOrderCounts as $item) {
    $hour = (int)($item['hour_label'] ?? 0);
    $hours[$hour] = (int)($item['order_count'] ?? 0);
}
foreach ($hours as $count) {
    $hourValues[] = $count;
}

$revenueToday = (float)(db_fetch_val(
    "SELECT COALESCE(SUM(total_amount), 0) FROM `orders` WHERE DATE(placed_at) = CURDATE() AND order_status != 'CANCELLED'"
) ?: 0.0);

$stockRiskTrend = (int)(db_fetch_val(
    "SELECT COUNT(*) FROM `menu_items` WHERE stock_quantity <= 10 AND is_available = 1"
) ?: 0);

$slaPerformance = (int)(db_fetch_val(
    "SELECT COUNT(*) FROM `orders` WHERE order_status IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY') AND TIMESTAMPDIFF(MINUTE, placed_at, NOW()) <= 25"
) ?: 0);

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <span class="small fw-bold text-success text-uppercase">Admin workspace</span>
    <h1 class="h3 fw-bold font-outfit text-dark mb-1">Operations overview</h1>
    <p class="text-muted mb-0">Resolve exceptions first, then move into the workspace that needs your attention.</p>
  </div>
  <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-2">
    <span class="live-pulse-dot green me-1"></span> Store operations
  </span>
</div>

<section class="row g-3 mb-4" aria-label="Operational KPI summary">
  <div class="col-sm-6 col-xl-2_5">
    <div class="stat-card dashboard-metric metric-success h-100">
      <div>
        <div class="metric-label">Total live orders</div>
        <div class="metric-value"><?= number_format($liveOrdersCount) ?></div>
      </div>
      <span class="metric-icon"><i class="bi bi-receipt"></i></span>
    </div>
  </div>
  <div class="col-sm-6 col-xl-2_5">
    <div class="stat-card dashboard-metric metric-warning h-100">
      <div>
        <div class="metric-label">Orders at risk</div>
        <div class="metric-value"><?= number_format($ordersAtRisk) ?></div>
      </div>
      <span class="metric-icon"><i class="bi bi-exclamation-triangle"></i></span>
    </div>
  </div>
  <div class="col-sm-6 col-xl-2_5">
    <div class="stat-card dashboard-metric metric-mint h-100">
      <div>
        <div class="metric-label">Low-stock items</div>
        <div class="metric-value"><?= number_format($lowStockCount) ?></div>
      </div>
      <span class="metric-icon"><i class="bi bi-box-seam"></i></span>
    </div>
  </div>
  <div class="col-sm-6 col-xl-2_5">
    <div class="stat-card dashboard-metric metric-cod h-100">
      <div>
        <div class="metric-label">COD pending</div>
        <div class="metric-value"><?= number_format($codPending) ?></div>
      </div>
      <span class="metric-icon"><i class="bi bi-cash-stack"></i></span>
    </div>
  </div>
  <div class="col-sm-6 col-xl-2_5">
    <div class="stat-card dashboard-metric metric-info h-100">
      <div>
        <div class="metric-label">Delivery delays</div>
        <div class="metric-value"><?= number_format($deliveryDelays) ?></div>
      </div>
      <span class="metric-icon"><i class="bi bi-bicycle"></i></span>
    </div>
  </div>
</section>

<section class="card border rounded-4 shadow-xs bg-white mb-4 overflow-hidden" style="border-color:#E2ECE5 !important;" aria-labelledby="attentionHeading">
  <div class="p-3 p-xl-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h2 class="h5 fw-bold font-outfit text-dark mb-1" id="attentionHeading">Prioritized triage board</h2>
      <p class="small text-muted mb-0">Highest-severity operational issues are surfaced first so the team can act in the correct order.</p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <?php if (!empty($triageExceptions)): ?>
        <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-2"><?= count($triageExceptions) ?> active alerts</span>
      <?php endif; ?>
      <?php foreach (['CRITICAL', 'WARNING', 'INFO'] as $severity): ?>
        <span class="badge rounded-pill bg-light text-dark border px-2 py-1 small fw-semibold">
          <?= $severity ?>: <?= $triageSeverityCounts[$severity] ?? 0 ?>
        </span>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="p-3 p-xl-4">
    <?php if (!empty($triageExceptions)): ?>
      <div class="d-flex flex-column gap-2">
        <?php foreach ($triageExceptions as $exception): ?>
          <a href="<?= e($exception['action_url']) ?>" class="triage-row d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 rounded-3 border text-decoration-none text-dark">
            <div class="d-flex align-items-center gap-3">
              <span class="triage-severity severity-<?= strtolower(e($exception['severity'])) ?>"><?= e($exception['severity']) ?></span>
              <span class="badge <?= e($exception['badge_class']) ?> rounded-pill">
                <i class="bi <?= e($exception['icon']) ?> me-1"></i><?= e($exception['type']) ?>
              </span>
              <span class="fw-semibold"><?= e($exception['title']) ?></span>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="small text-muted">Quick action</span>
              <span class="small fw-bold text-success">Review <i class="bi bi-arrow-right ms-1"></i></span>
            </div>
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
      <a href="<?= ADMIN_URL ?>/dishes.php" class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 mt-3 rounded-3 border border-warning-subtle bg-warning-subtle text-decoration-none text-dark">
        <span><i class="bi bi-exclamation-triangle-fill text-warning-emphasis me-2"></i><strong><?= $lowStockCount ?> menu item<?= $lowStockCount === 1 ? '' : 's' ?></strong> at or below the low-stock threshold</span>
        <span class="small fw-bold text-warning-emphasis">Review stock <i class="bi bi-arrow-right ms-1"></i></span>
      </a>
    <?php endif; ?>
  </div>
</section>

<section class="mb-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div>
      <h2 class="h5 fw-bold font-outfit text-dark mb-1">Live operating pulse</h2>
      <p class="small text-muted mb-0">The store is monitored continuously across orders, revenue, and SLA health.</p>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-xl-8">
      <div class="card border rounded-4 shadow-xs bg-white p-3 p-xl-4 h-100" style="border-color:#E2ECE5 !important;">
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
          <div>
            <h3 class="h6 fw-bold mb-1">Orders per hour</h3>
            <p class="small text-muted mb-0">Live order throughput today</p>
          </div>
          <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2 py-1">Today</span>
        </div>
        <div style="height:240px;position:relative;">
          <canvas id="ordersPerHourChart" aria-label="Orders per hour chart"></canvas>
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card border rounded-4 shadow-xs bg-white p-3 p-xl-4 h-100" style="border-color:#E2ECE5 !important;">
        <h3 class="h6 fw-bold mb-3">Operational snapshot</h3>
        <div class="mini-stat-list d-flex flex-column gap-3">
          <div class="mini-stat-item">
            <span class="mini-stat-label">Revenue today</span>
            <strong class="mini-stat-value">₹<?= number_format($revenueToday, 2) ?></strong>
          </div>
          <div class="mini-stat-item">
            <span class="mini-stat-label">Stock risk trend</span>
            <strong class="mini-stat-value danger"><?= number_format($stockRiskTrend) ?> items</strong>
          </div>
          <div class="mini-stat-item">
            <span class="mini-stat-label">SLA performance</span>
            <strong class="mini-stat-value success"><?= number_format($slaPerformance) ?> on time</strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section aria-labelledby="workspacesHeading">
  <div class="mb-3">
    <h2 class="h5 fw-bold font-outfit text-dark mb-1" id="workspacesHeading">Open a workspace</h2>
    <p class="small text-muted mb-0">Each workspace is the source of truth for its operational or business data.</p>
  </div>

  <div class="row g-3">
    <div class="col-sm-6 col-xl-4">
      <a href="<?= ADMIN_URL ?>/orders.php?status=LIVE" class="workspace-card critical h-100 p-3 p-xl-4 border rounded-4 shadow-xs text-decoration-none text-dark">
        <div class="workspace-card-head">
          <span class="workspace-icon success"><i class="bi bi-receipt fs-5"></i></span>
          <span class="workspace-tag danger">Priority</span>
        </div>
        <div class="workspace-card-body">
          <h3 class="h6 fw-bold mb-1">Live orders</h3>
          <p class="small text-muted mb-0">Track SLA, queue pressure, and status changes in real time.</p>
        </div>
        <div class="workspace-card-footer">
          <span class="workspace-count"><?= number_format($liveOrdersCount) ?></span>
          <span class="workspace-meta">in flow</span>
        </div>
      </a>
    </div>
    <div class="col-sm-6 col-xl-4">
      <a href="<?= ADMIN_URL ?>/kitchen-view.php" class="workspace-card warning h-100 p-3 p-xl-4 border rounded-4 shadow-xs text-decoration-none text-dark">
        <div class="workspace-card-head">
          <span class="workspace-icon warning"><i class="fa-solid fa-fire fs-5"></i></span>
          <span class="workspace-tag warning">Kitchen</span>
        </div>
        <div class="workspace-card-body">
          <h3 class="h6 fw-bold mb-1">Kitchen &amp; staffing</h3>
          <p class="small text-muted mb-0">See prep load, staffing pressure, and order queue risk.</p>
        </div>
        <div class="workspace-card-footer">
          <span class="workspace-count"><?= number_format($ordersAtRisk) ?></span>
          <span class="workspace-meta">risk watch</span>
        </div>
      </a>
    </div>
    <div class="col-sm-6 col-xl-4">
      <a href="<?= ADMIN_URL ?>/delivery-view.php" class="workspace-card info h-100 p-3 p-xl-4 border rounded-4 shadow-xs text-decoration-none text-dark">
        <div class="workspace-card-head">
          <span class="workspace-icon primary"><i class="bi bi-bicycle fs-5"></i></span>
          <span class="workspace-tag info">Dispatch</span>
        </div>
        <div class="workspace-card-body">
          <h3 class="h6 fw-bold mb-1">Delivery fleet</h3>
          <p class="small text-muted mb-0">Monitor rider availability and delay-sensitive handoffs.</p>
        </div>
        <div class="workspace-card-footer">
          <span class="workspace-count"><?= number_format($deliveryDelays) ?></span>
          <span class="workspace-meta">delayed</span>
        </div>
      </a>
    </div>
    <div class="col-sm-6 col-xl-4">
      <a href="<?= ADMIN_URL ?>/financial-transactions.php" class="workspace-card success h-100 p-3 p-xl-4 border rounded-4 shadow-xs text-decoration-none text-dark">
        <div class="workspace-card-head">
          <span class="workspace-icon success"><i class="bi bi-cash-coin fs-5"></i></span>
          <span class="workspace-tag success">Cash</span>
        </div>
        <div class="workspace-card-body">
          <h3 class="h6 fw-bold mb-1">Cash &amp; COD closeout</h3>
          <p class="small text-muted mb-0">Review pending cash movements and reconciliation gaps.</p>
        </div>
        <div class="workspace-card-footer">
          <span class="workspace-count"><?= number_format($codPending) ?></span>
          <span class="workspace-meta">pending</span>
        </div>
      </a>
    </div>
    <div class="col-sm-6 col-xl-4">
      <a href="<?= ADMIN_URL ?>/dishes.php" class="workspace-card warning-alt h-100 p-3 p-xl-4 border rounded-4 shadow-xs text-decoration-none text-dark">
        <div class="workspace-card-head">
          <span class="workspace-icon warning"><i class="fa-solid fa-bowl-food fs-5"></i></span>
          <span class="workspace-tag warning-alt">Inventory</span>
        </div>
        <div class="workspace-card-body">
          <h3 class="h6 fw-bold mb-1">Menu &amp; stock</h3>
          <p class="small text-muted mb-0">Keep availability, stock, and ingredient risk under control.</p>
        </div>
        <div class="workspace-card-footer">
          <span class="workspace-count"><?= number_format($lowStockCount) ?></span>
          <span class="workspace-meta">low stock</span>
        </div>
      </a>
    </div>
    <div class="col-sm-6 col-xl-4">
      <a href="<?= ADMIN_URL ?>/reports.php" class="workspace-card slate h-100 p-3 p-xl-4 border rounded-4 shadow-xs text-decoration-none text-dark">
        <div class="workspace-card-head">
          <span class="workspace-icon primary"><i class="bi bi-graph-up-arrow fs-5"></i></span>
          <span class="workspace-tag slate">Insights</span>
        </div>
        <div class="workspace-card-body">
          <h3 class="h6 fw-bold mb-1">Business insights</h3>
          <p class="small text-muted mb-0">Use trends to flag risk, see demand, and improve forecast accuracy.</p>
        </div>
        <div class="workspace-card-footer">
          <span class="workspace-count">7d</span>
          <span class="workspace-meta">window</span>
        </div>
      </a>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const hourlyChart = document.getElementById('ordersPerHourChart');
  if (hourlyChart) {
    new Chart(hourlyChart, {
      type: 'line',
      data: {
        labels: <?= json_encode($hourLabels) ?>,
        datasets: [{
          label: 'Orders',
          data: <?= json_encode($hourValues) ?>,
          borderColor: '#2E6F40',
          backgroundColor: 'rgba(46,111,64,0.12)',
          borderWidth: 3,
          pointRadius: 3,
          pointBackgroundColor: '#2E6F40',
          fill: true,
          tension: 0.3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { maxTicksLimit: 8 }
          },
          y: {
            beginAtZero: true,
            ticks: { precision: 0 }
          }
        }
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
