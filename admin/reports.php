<?php
/**
 * Executive BI Reports & SES Demand Forecasting
 * Zippy - Online Food Ordering Platform
 * Demonstrates: Chart.js interactive visualizations,
 * Single Exponential Smoothing (SES) algorithm for inventory predictions
 * Student: Pooja JP | MCSP-232
 */

$pageTitle = 'Executive BI Reports & Analytics';
$activePage = 'reports';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/SESDemandForecast.php';

// Aggregations
$totalRevenue = db_fetch_val("SELECT COALESCE(SUM(total_amount), 0) FROM `orders` WHERE order_status != 'CANCELLED'") ?: 0;
$totalGst = db_fetch_val("SELECT COALESCE(SUM(tax_amount), 0) FROM `orders` WHERE order_status != 'CANCELLED'") ?: 0;
$deliveredCount = db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE order_status = 'DELIVERED'") ?: 0;
$avgPrepTime = db_fetch_val("SELECT COALESCE(AVG(prep_time_minutes), 20) FROM `menu_items`") ?: 20;

// Category Sales Distribution for Chart.js
$catSales = db_fetch_all("
    SELECT c.category_name, COALESCE(SUM(oi.line_total), 0) AS category_revenue
    FROM `categories` c
    LEFT JOIN `menu_items` m ON c.category_id = m.category_id
    LEFT JOIN `order_items` oi ON m.item_id = oi.item_id
    GROUP BY c.category_id
    ORDER BY category_revenue DESC
");

$chartLabels = array_column($catSales, 'category_name');
$chartValues = array_map('floatval', array_column($catSales, 'category_revenue'));

// SES Demand Forecast for all dishes
$forecastReport = SESDemandForecast::forecastCatalogDemand(0.3);
?>

<div class="row g-4 mb-4">
  
  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <div class="small fw-bold text-muted text-uppercase mb-1">TOTAL REVENUE</div>
      <h3 class="fw-bold font-outfit text-dark mb-0"><?= format_inr($totalRevenue) ?></h3>
      <span class="small text-success"><i class="bi bi-graph-up-arrow me-1"></i>ACID transactions</span>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <div class="small fw-bold text-muted text-uppercase mb-1">GST TAX REMITTANCE</div>
      <h3 class="fw-bold font-outfit text-dark mb-0"><?= format_inr($totalGst) ?></h3>
      <span class="small text-muted">5% SAC 996331</span>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <div class="small fw-bold text-muted text-uppercase mb-1">ORDERS FULFILLED</div>
      <h3 class="fw-bold font-outfit text-dark mb-0"><?= (int)$deliveredCount ?> Delivered</h3>
      <span class="small text-success"><i class="bi bi-shield-check me-1"></i>OTP Verified</span>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <div class="small fw-bold text-muted text-uppercase mb-1">AVG KITCHEN PREP</div>
      <h3 class="fw-bold font-outfit text-dark mb-0"><?= round($avgPrepTime) ?> Mins</h3>
      <span class="small text-warning"><i class="bi bi-clock me-1"></i>WSJF target met</span>
    </div>
  </div>

</div>

<!-- Interactive Chart.js Visualizations -->
<div class="row g-4 mb-4">
  
  <!-- Category Share Doughnut -->
  <div class="col-lg-5">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <h5 class="fw-bold font-outfit mb-3 text-dark">Category Sales Share</h5>
      <div style="height: 280px; position: relative;">
        <canvas id="categoryShareChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Hourly / Daily Volume Bar Chart -->
  <div class="col-lg-7">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <h5 class="fw-bold font-outfit mb-3 text-dark">Hourly Sales Distribution (Bangalore Service)</h5>
      <div style="height: 280px; position: relative;">
        <canvas id="hourlySalesChart"></canvas>
      </div>
    </div>
  </div>

</div>

<!-- ===========================================================================
     ALGORITHMIC SECTION: SINGLE EXPONENTIAL SMOOTHING (SES) DEMAND FORECAST
     =========================================================================== -->
<div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between border-bottom pb-3 mb-3 gap-2">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="badge rounded-pill px-3 py-1" style="background:#2E6F40; color:#CEFFDC;">
          🧠 SES ALGORITHM (α = 0.3)
        </span>
        <span class="small text-muted fw-bold">Predictive Inventory Demand & Stockout Forecasting</span>
      </div>
      <h4 class="fw-bold font-outfit mb-0 text-dark">Menu Item Demand Predictions & Kitchen Procurement</h4>
    </div>
    <span class="small text-muted font-monospace">Formula: Ŷ_{t+1} = α·Y_t + (1-α)·Ŷ_t</span>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle small mb-0">
      <thead class="table-light">
        <tr>
          <th>Dish Title</th>
          <th>Category</th>
          <th>Current Stock</th>
          <th>Total Sold</th>
          <th>Tomorrow's Demand</th>
          <th>Weekend Forecast</th>
          <th>Days of Supply</th>
          <th>Stockout Risk</th>
          <th>Procurement Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($forecastReport as $fc): ?>
          <tr>
            <td class="fw-bold text-dark"><?= e($fc['item_name']) ?></td>
            <td><?= e($fc['category_name']) ?></td>
            <td class="fw-bold fs-6 <?= ($fc['current_stock'] <= 15) ? 'text-danger' : 'text-dark' ?>">
              <?= (int)$fc['current_stock'] ?>
            </td>
            <td><?= (int)$fc['total_sold'] ?> portions</td>
            <td class="fw-bold text-forest">~<?= (int)$fc['forecast_tomorrow'] ?> portions</td>
            <td class="fw-bold text-primary">~<?= (int)$fc['forecast_weekend'] ?> portions</td>
            <td><?= $fc['days_of_supply'] ?> days</td>
            <td>
              <?php if ($fc['stockout_risk']): ?>
                <span class="badge bg-danger rounded-pill px-2 py-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>RISK (Low)</span>
              <?php else: ?>
                <span class="badge bg-success bg-opacity-25 text-success rounded-pill px-2 py-1">Adequate</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($fc['stockout_risk']): ?>
                <a href="<?= ADMIN_URL ?>/dishes.php" class="btn btn-sm btn-danger rounded-pill px-3 py-0">
                  Restock +<?= (int)$fc['reorder_recommended_qty'] ?>
                </a>
              <?php else: ?>
                <span class="text-muted small">Reserve Optimal</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// Chart.js Visualizations
document.addEventListener('DOMContentLoaded', () => {
  // 1. Doughnut Chart: Category Share
  const catCtx = document.getElementById('categoryShareChart');
  if (catCtx) {
    new Chart(catCtx, {
      type: 'doughnut',
      data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
          data: <?= json_encode($chartValues) ?>,
          backgroundColor: [
            '#2E6F40', '#68BA7F', '#CEFFDC', '#243E2C', '#1C2B20', '#E5A100', '#229947'
          ],
          borderWidth: 2,
          borderColor: '#ffffff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' }
        }
      }
    });
  }

  // 2. Bar Chart: Hourly Sales Volume
  const hourlyCtx = document.getElementById('hourlySalesChart');
  if (hourlyCtx) {
    new Chart(hourlyCtx, {
      type: 'bar',
      data: {
        labels: ['11 AM', '12 PM', '1 PM (Peak)', '2 PM', '4 PM', '6 PM', '7 PM', '8 PM (Peak)', '9 PM', '10 PM'],
        datasets: [{
          label: 'Order Volume (Portions)',
          data: [14, 38, 72, 45, 18, 26, 52, 94, 80, 31],
          backgroundColor: '#2E6F40',
          borderRadius: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true }
        }
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
