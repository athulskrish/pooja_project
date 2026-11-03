<?php
/**
 * Executive BI Reports
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Business Insights';
$activePage = 'reports';

require_once __DIR__ . '/includes/header.php';

$period = clean_input($_GET['period'] ?? '7d');
$periodDays = [
    '7d' => 7,
    '30d' => 30,
    '90d' => 90,
];

if (!isset($periodDays[$period])) {
    $period = '7d';
}

$days = $periodDays[$period];
$periodStart = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));

$dailySales = db_fetch_all("
    SELECT DATE(placed_at) AS sales_date,
           COALESCE(SUM(total_amount), 0) AS revenue,
           COUNT(*) AS order_count
    FROM `orders`
    WHERE order_status != 'CANCELLED'
      AND placed_at >= :period_start
    GROUP BY DATE(placed_at)
    ORDER BY sales_date ASC
", [':period_start' => $periodStart]);

$categorySales = db_fetch_all("
    SELECT c.category_name,
           COALESCE(SUM(oi.line_total), 0) AS category_revenue
    FROM `categories` c
    JOIN `menu_items` m ON c.category_id = m.category_id
    JOIN `order_items` oi ON m.item_id = oi.item_id
    JOIN `orders` o ON oi.order_id = o.order_id
    WHERE o.order_status != 'CANCELLED'
      AND o.placed_at >= :period_start
    GROUP BY c.category_id, c.category_name
    ORDER BY category_revenue DESC
    LIMIT 8
", [':period_start' => $periodStart]);

$dailyRowsByDate = [];
foreach ($dailySales as $row) {
    $dailyRowsByDate[$row['sales_date']] = $row;
}

$trendLabels = [];
$revenueValues = [];
$orderValues = [];
for ($offset = $days - 1; $offset >= 0; $offset--) {
    $date = date('Y-m-d', strtotime("-{$offset} days"));
    $trendLabels[] = date('M j', strtotime($date));
    $revenueValues[] = (float)($dailyRowsByDate[$date]['revenue'] ?? 0);
    $orderValues[] = (int)($dailyRowsByDate[$date]['order_count'] ?? 0);
}

$categoryLabels = array_column($categorySales, 'category_name');
$categoryValues = array_map('floatval', array_column($categorySales, 'category_revenue'));
?>

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <span class="small fw-bold text-success text-uppercase">Business insights</span>
    <h1 class="h3 fw-bold font-outfit text-dark mb-1">Sales &amp; demand trends</h1>
    <p class="text-muted mb-0">Historical performance for the selected period. Operational totals and inventory actions live in their respective workspaces.</p>
  </div>
  <form method="GET" action="<?= ADMIN_URL ?>/reports.php" class="d-flex align-items-center gap-2">
    <label for="reportPeriod" class="small fw-semibold text-muted">Period</label>
    <select class="form-select form-select-sm rounded-pill" name="period" id="reportPeriod" onchange="this.form.submit()">
      <option value="7d" <?= $period === '7d' ? 'selected' : '' ?>>Last 7 days</option>
      <option value="30d" <?= $period === '30d' ? 'selected' : '' ?>>Last 30 days</option>
      <option value="90d" <?= $period === '90d' ? 'selected' : '' ?>>Last 90 days</option>
    </select>
  </form>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <section class="card border rounded-4 shadow-xs p-3 p-xl-4 bg-white h-100" style="border-color:#E2ECE5 !important;" aria-labelledby="salesTrendHeading">
      <div class="mb-3">
        <h2 class="h5 fw-bold font-outfit text-dark mb-1" id="salesTrendHeading">Daily revenue &amp; orders</h2>
        <p class="small text-muted mb-0">Cancelled orders are excluded. Hover over a point for the daily value.</p>
      </div>
      <div style="height:320px;position:relative">
        <canvas id="salesTrendChart" role="img" aria-label="Daily revenue and order count over the selected period"></canvas>
      </div>
    </section>
  </div>

  <div class="col-xl-4">
    <section class="card border rounded-4 shadow-xs p-3 p-xl-4 bg-white h-100" style="border-color:#E2ECE5 !important;" aria-labelledby="categoryMixHeading">
      <div class="mb-3">
        <h2 class="h5 fw-bold font-outfit text-dark mb-1" id="categoryMixHeading">Revenue by category</h2>
        <p class="small text-muted mb-0">Top categories for the selected period.</p>
      </div>
      <?php if (empty($categorySales)): ?>
        <div class="d-flex align-items-center justify-content-center text-center text-muted small h-100 py-5">
          No category sales are available for this period.
        </div>
      <?php else: ?>
        <div style="height:320px;position:relative">
          <canvas id="categorySalesChart" role="img" aria-label="Revenue comparison across top categories"></canvas>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const trendCanvas = document.getElementById('salesTrendChart');
  if (trendCanvas) {
    new Chart(trendCanvas, {
      type: 'line',
      data: {
        labels: <?= json_encode($trendLabels) ?>,
        datasets: [
          {
            label: 'Revenue (INR)',
            data: <?= json_encode($revenueValues) ?>,
            yAxisID: 'revenue',
            borderColor: '#2E6F40',
            backgroundColor: 'rgba(46,111,64,0.12)',
            fill: true,
            tension: 0.3
          },
          {
            label: 'Orders',
            data: <?= json_encode($orderValues) ?>,
            yAxisID: 'orders',
            borderColor: '#2563EB',
            backgroundColor: '#2563EB',
            tension: 0.3
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
          revenue: { type: 'linear', position: 'left', beginAtZero: true, title: { display: true, text: 'Revenue (INR)' } },
          orders: { type: 'linear', position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'Orders' } }
        }
      }
    });
  }

  const categoryCanvas = document.getElementById('categorySalesChart');
  if (categoryCanvas) {
    new Chart(categoryCanvas, {
      type: 'bar',
      data: {
        labels: <?= json_encode($categoryLabels) ?>,
        datasets: [{
          label: 'Revenue (INR)',
          data: <?= json_encode($categoryValues) ?>,
          backgroundColor: '#2E6F40',
          borderRadius: 6
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true } }
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
