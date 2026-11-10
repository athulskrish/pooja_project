<?php
declare(strict_types=1);
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

requireAdmin();

$dashboardData = AdminService::getOperationsDashboardData();
$triageExceptions = $dashboardData['triageExceptions'];
$lowStockCount = $dashboardData['lowStockCount'];
$liveOrdersCount = $dashboardData['liveOrdersCount'];
$ordersAtRisk = $dashboardData['ordersAtRisk'];
$codPending = $dashboardData['codPending'];
$deliveryDelays = $dashboardData['deliveryDelays'];
$scheduledOrdersCount = $dashboardData['scheduledOrdersCount'];
$upcomingScheduledList = $dashboardData['upcomingScheduledList'];
$triageSeverityCounts = $dashboardData['triageSeverityCounts'];
$hourLabels = $dashboardData['hourLabels'];
$hourValues = $dashboardData['hourValues'];
$revenueToday = $dashboardData['revenueToday'];
$stockRiskTrend = $dashboardData['stockRiskTrend'];
$slaPerformance = $dashboardData['slaPerformance'];

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

<?php if (!empty($upcomingScheduledList)): ?>
<div class="alert p-3 rounded-4 mb-4 border d-flex flex-wrap align-items-center justify-content-between gap-3 shadow-xs" style="background:#FAF5FF; border-color:#E9D5FF !important;">
  <div class="d-flex align-items-center gap-3">
    <div class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0 shadow-sm" style="width:42px; height:42px; background:#7C3AED;">
      <i class="bi bi-calendar2-check-fill fs-5"></i>
    </div>
    <div>
      <div class="fw-bold text-dark font-outfit" style="color:#6B21A8 !important;">
        <?= count($upcomingScheduledList) ?> Advance Scheduled Meal Order(s) Logged
      </div>
      <div class="small text-muted">
        <?php foreach ($upcomingScheduledList as $idx => $so): ?>
          <span>#ZIP-<?= (int)$so['order_id'] ?> (<?= e($so['full_name']) ?> &bull; <strong style="color:#6B21A8;"><?= e($so['scheduled_slot'] ?: date('d M, h:i A', strtotime($so['scheduled_for']))) ?></strong>)<?= ($idx < count($upcomingScheduledList) - 1) ? ' &bull; ' : '' ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <a href="<?= ADMIN_URL ?>/orders.php?status=SCHEDULED" class="btn btn-sm text-white rounded-pill px-3 shadow-xs fw-semibold" style="background:#7C3AED;">
    View Scheduled Queue &rarr;
  </a>
</div>
<?php endif; ?>

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
