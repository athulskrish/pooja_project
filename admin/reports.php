<?php
/**
 * Executive BI Reports & Sales Intelligence
 * Zippy - Online Food Ordering Platform
 * MCSP-232: Major Project | Student: Pooja JP
 * 
 * Features:
 * - Period presets (Today, Yesterday, 7D, 30D, This Month, Custom Date Range)
 * - Comparative period-over-period growth indicators
 * - 5 Executive KPI Metric Cards (Gross Revenue, Net Food Sales, Volume, AOV, Cancellations)
 * - Interactive Combo Chart (Daily/Hourly Revenue Bars + Order Demand Spline)
 * - Revenue by Category Breakdown with % contribution
 * - Top 5 Selling Dishes (Product Velocity Leaderboard)
 * - Payment Settlement Mix (UPI vs COD vs Cards Donut Chart)
 * - Tabular Performance Breakdown Ledger with Column Totals
 * - One-Click CSV Export & Print/PDF Formatting
 */

// 1. Bootstrap Core Libraries before any HTML output
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();
$currentUser = current_user();

$pageTitle = 'Executive BI Reports';
$activePage = 'reports';

// 2. Parse & Validate Date Filter
$period = clean_input($_GET['period'] ?? '7d');
$now = new DateTime('now');
$todayStart = (clone $now)->setTime(0, 0, 0);
$todayEnd = (clone $now)->setTime(23, 59, 59);

$startDate = '';
$endDate = '';
$prevStartDate = '';
$prevEndDate = '';
$periodLabel = '';
$comparisonLabel = '';
$isSingleDay = false;

$rawStart = clean_input($_GET['start_date'] ?? '');
$rawEnd = clean_input($_GET['end_date'] ?? '');

switch ($period) {
    case 'today':
        $startDate = $todayStart->format('Y-m-d H:i:s');
        $endDate = $todayEnd->format('Y-m-d H:i:s');
        $prevStart = (clone $todayStart)->modify('-1 day');
        $prevEnd = (clone $todayEnd)->modify('-1 day');
        $prevStartDate = $prevStart->format('Y-m-d H:i:s');
        $prevEndDate = $prevEnd->format('Y-m-d H:i:s');
        $periodLabel = 'Today (' . $now->format('M j, Y') . ')';
        $comparisonLabel = 'vs yesterday';
        $isSingleDay = true;
        break;

    case 'yesterday':
        $yStart = (clone $todayStart)->modify('-1 day');
        $yEnd = (clone $todayEnd)->modify('-1 day');
        $startDate = $yStart->format('Y-m-d H:i:s');
        $endDate = $yEnd->format('Y-m-d H:i:s');
        $prevStart = (clone $yStart)->modify('-1 day');
        $prevEnd = (clone $yEnd)->modify('-1 day');
        $prevStartDate = $prevStart->format('Y-m-d H:i:s');
        $prevEndDate = $prevEnd->format('Y-m-d H:i:s');
        $periodLabel = 'Yesterday (' . $yStart->format('M j, Y') . ')';
        $comparisonLabel = 'vs day before';
        $isSingleDay = true;
        break;

    case '30d':
        $dStart = (clone $todayStart)->modify('-29 days');
        $startDate = $dStart->format('Y-m-d H:i:s');
        $endDate = $todayEnd->format('Y-m-d H:i:s');
        $prevStart = (clone $dStart)->modify('-30 days');
        $prevEnd = (clone $dStart)->modify('-1 second');
        $prevStartDate = $prevStart->format('Y-m-d H:i:s');
        $prevEndDate = $prevEnd->format('Y-m-d H:i:s');
        $periodLabel = 'Last 30 Days (' . $dStart->format('M j') . ' - ' . $now->format('M j, Y') . ')';
        $comparisonLabel = 'vs prior 30 days';
        break;

    case 'this_month':
        $mStart = (clone $todayStart)->modify('first day of this month');
        $startDate = $mStart->format('Y-m-d H:i:s');
        $endDate = $todayEnd->format('Y-m-d H:i:s');
        $prevStart = (clone $mStart)->modify('first day of previous month');
        $prevEnd = (clone $mStart)->modify('-1 second');
        $prevStartDate = $prevStart->format('Y-m-d H:i:s');
        $prevEndDate = $prevEnd->format('Y-m-d H:i:s');
        $periodLabel = 'This Month (' . $now->format('F Y') . ')';
        $comparisonLabel = 'vs previous month';
        break;

    case 'custom':
        $dStartObj = DateTime::createFromFormat('Y-m-d', $rawStart);
        $dEndObj = DateTime::createFromFormat('Y-m-d', $rawEnd);

        if ($dStartObj && $dEndObj && $dStartObj <= $dEndObj) {
            $dStartObj->setTime(0, 0, 0);
            $dEndObj->setTime(23, 59, 59);
            $startDate = $dStartObj->format('Y-m-d H:i:s');
            $endDate = $dEndObj->format('Y-m-d H:i:s');
            $diffDays = (int)$dStartObj->diff($dEndObj)->format('%a') + 1;
            $prevStart = (clone $dStartObj)->modify("-{$diffDays} days");
            $prevEnd = (clone $dStartObj)->modify('-1 second');
            $prevStartDate = $prevStart->format('Y-m-d H:i:s');
            $prevEndDate = $prevEnd->format('Y-m-d H:i:s');
            $periodLabel = $dStartObj->format('M j, Y') . ' - ' . $dEndObj->format('M j, Y');
            $comparisonLabel = "vs prior {$diffDays} days";
            $isSingleDay = ($diffDays === 1);
        } else {
            // Fallback to 7d
            $period = '7d';
            $dStart = (clone $todayStart)->modify('-6 days');
            $startDate = $dStart->format('Y-m-d H:i:s');
            $endDate = $todayEnd->format('Y-m-d H:i:s');
            $prevStart = (clone $dStart)->modify('-7 days');
            $prevEnd = (clone $dStart)->modify('-1 second');
            $prevStartDate = $prevStart->format('Y-m-d H:i:s');
            $prevEndDate = $prevEnd->format('Y-m-d H:i:s');
            $periodLabel = 'Last 7 Days (' . $dStart->format('M j') . ' - ' . $now->format('M j, Y') . ')';
            $comparisonLabel = 'vs prior 7 days';
        }
        break;

    case '7d':
    default:
        $period = '7d';
        $dStart = (clone $todayStart)->modify('-6 days');
        $startDate = $dStart->format('Y-m-d H:i:s');
        $endDate = $todayEnd->format('Y-m-d H:i:s');
        $prevStart = (clone $dStart)->modify('-7 days');
        $prevEnd = (clone $dStart)->modify('-1 second');
        $prevStartDate = $prevStart->format('Y-m-d H:i:s');
        $prevEndDate = $prevEnd->format('Y-m-d H:i:s');
        $periodLabel = 'Last 7 Days (' . $dStart->format('M j') . ' - ' . $now->format('M j, Y') . ')';
        $comparisonLabel = 'vs prior 7 days';
        break;
}

// 3. Query KPI Summary Aggregate
$kpiSummary = db_fetch_one("
    SELECT 
        COUNT(*) AS total_orders,
        COALESCE(SUM(CASE WHEN order_status = 'DELIVERED' THEN 1 ELSE 0 END), 0) AS delivered_orders,
        COALESCE(SUM(CASE WHEN order_status = 'CANCELLED' THEN 1 ELSE 0 END), 0) AS cancelled_orders,
        COALESCE(SUM(CASE WHEN order_status NOT IN ('CANCELLED') THEN 1 ELSE 0 END), 0) AS valid_orders,
        COALESCE(SUM(CASE WHEN order_status NOT IN ('CANCELLED') THEN total_amount ELSE 0 END), 0) AS gross_revenue,
        COALESCE(SUM(CASE WHEN order_status NOT IN ('CANCELLED') THEN subtotal ELSE 0 END), 0) AS net_food_sales,
        COALESCE(SUM(CASE WHEN order_status NOT IN ('CANCELLED') THEN tax_amount ELSE 0 END), 0) AS total_gst,
        COALESCE(SUM(CASE WHEN order_status NOT IN ('CANCELLED') THEN delivery_fee ELSE 0 END), 0) AS total_delivery_fee,
        COALESCE(SUM(CASE WHEN order_status NOT IN ('CANCELLED') THEN discount_amount ELSE 0 END), 0) AS total_discounts,
        COALESCE(SUM(CASE WHEN order_status = 'CANCELLED' THEN total_amount ELSE 0 END), 0) AS cancelled_loss,
        COALESCE(AVG(CASE WHEN order_status NOT IN ('CANCELLED') THEN total_amount ELSE NULL END), 0) AS avg_order_value
    FROM `orders`
    WHERE placed_at >= :start_date AND placed_at <= :end_date
", [
    ':start_date' => $startDate,
    ':end_date'   => $endDate,
]) ?: [];

$kpiGrossRevenue = (float)($kpiSummary['gross_revenue'] ?? 0);
$kpiNetFoodSales = (float)($kpiSummary['net_food_sales'] ?? 0);
$kpiTotalOrders = (int)($kpiSummary['total_orders'] ?? 0);
$kpiDelivered = (int)($kpiSummary['delivered_orders'] ?? 0);
$kpiCancelled = (int)($kpiSummary['cancelled_orders'] ?? 0);
$kpiValidOrders = (int)($kpiSummary['valid_orders'] ?? 0);
$kpiTotalGst = (float)($kpiSummary['total_gst'] ?? 0);
$kpiDeliveryFee = (float)($kpiSummary['total_delivery_fee'] ?? 0);
$kpiDiscounts = (float)($kpiSummary['total_discounts'] ?? 0);
$kpiCancelledLoss = (float)($kpiSummary['cancelled_loss'] ?? 0);
$kpiAov = (float)($kpiSummary['avg_order_value'] ?? 0);

$deliveryRate = $kpiValidOrders > 0 ? round(($kpiDelivered / $kpiValidOrders) * 100, 1) : 0;
$cancellationRate = $kpiTotalOrders > 0 ? round(($kpiCancelled / $kpiTotalOrders) * 100, 1) : 0;

// Query Prior Period Gross Revenue for Growth Badge
$prevGrossRevenue = (float)(db_fetch_val("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM `orders`
    WHERE order_status != 'CANCELLED'
      AND placed_at >= :prev_start
      AND placed_at <= :prev_end
", [
    ':prev_start' => $prevStartDate,
    ':prev_end'   => $prevEndDate,
]) ?: 0.0);

if ($prevGrossRevenue > 0) {
    $revenueGrowthPct = (($kpiGrossRevenue - $prevGrossRevenue) / $prevGrossRevenue) * 100;
} else {
    $revenueGrowthPct = $kpiGrossRevenue > 0 ? 100.0 : 0.0;
}

// 4. Time-Series Data (Hourly if single day, Daily if multi-day)
$trendLabels = [];
$revenueValues = [];
$orderValues = [];
$tableRows = [];

if ($isSingleDay) {
    $hourlyData = db_fetch_all("
        SELECT 
            HOUR(placed_at) AS time_slot,
            COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN total_amount ELSE 0 END), 0) AS revenue,
            COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN subtotal ELSE 0 END), 0) AS food_sales,
            COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN tax_amount ELSE 0 END), 0) AS gst_amount,
            COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN delivery_fee ELSE 0 END), 0) AS delivery_fee,
            COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN discount_amount ELSE 0 END), 0) AS discounts,
            COUNT(*) AS total_orders,
            COALESCE(SUM(CASE WHEN order_status = 'DELIVERED' THEN 1 ELSE 0 END), 0) AS delivered_count,
            COALESCE(SUM(CASE WHEN order_status = 'CANCELLED' THEN 1 ELSE 0 END), 0) AS cancelled_count,
            COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN 1 ELSE 0 END), 0) AS valid_order_count
        FROM `orders`
        WHERE placed_at >= :start_date AND placed_at <= :end_date
        GROUP BY HOUR(placed_at)
        ORDER BY time_slot ASC
    ", [':start_date' => $startDate, ':end_date' => $endDate]);

    $hourlyBySlot = [];
    foreach ($hourlyData as $row) {
        $hourlyBySlot[(int)$row['time_slot']] = $row;
    }

    for ($h = 0; $h < 24; $h++) {
        $slotLabel = sprintf('%02d:00', $h);
        $trendLabels[] = $slotLabel;
        $row = $hourlyBySlot[$h] ?? null;
        $rev = (float)($row['revenue'] ?? 0);
        $orders = (int)($row['valid_order_count'] ?? 0);
        $revenueValues[] = $rev;
        $orderValues[] = $orders;

        if ($row && $row['total_orders'] > 0) {
            $tableRows[] = [
                'label'           => $slotLabel . ' - ' . sprintf('%02d:59', $h),
                'total_orders'    => (int)$row['total_orders'],
                'delivered_count' => (int)$row['delivered_count'],
                'cancelled_count' => (int)$row['cancelled_count'],
                'food_sales'      => (float)$row['food_sales'],
                'gst_amount'      => (float)$row['gst_amount'],
                'delivery_fee'    => (float)$row['delivery_fee'],
                'discounts'       => (float)$row['discounts'],
                'revenue'         => $rev,
                'aov'             => $orders > 0 ? ($rev / $orders) : 0.0,
            ];
        }
    }
} else {
    $dailyData = db_fetch_all("
        SELECT 
            DATE(placed_at) AS sales_date,
            COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN total_amount ELSE 0 END), 0) AS revenue,
            COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN subtotal ELSE 0 END), 0) AS food_sales,
            COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN tax_amount ELSE 0 END), 0) AS gst_amount,
            COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN delivery_fee ELSE 0 END), 0) AS delivery_fee,
            COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN discount_amount ELSE 0 END), 0) AS discounts,
            COUNT(*) AS total_orders,
            COALESCE(SUM(CASE WHEN order_status = 'DELIVERED' THEN 1 ELSE 0 END), 0) AS delivered_count,
            COALESCE(SUM(CASE WHEN order_status = 'CANCELLED' THEN 1 ELSE 0 END), 0) AS cancelled_count,
            COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN 1 ELSE 0 END), 0) AS valid_order_count
        FROM `orders`
        WHERE placed_at >= :start_date AND placed_at <= :end_date
        GROUP BY DATE(placed_at)
        ORDER BY sales_date ASC
    ", [':start_date' => $startDate, ':end_date' => $endDate]);

    $dailyByDate = [];
    foreach ($dailyData as $row) {
        $dailyByDate[$row['sales_date']] = $row;
    }

    $curIter = new DateTime($startDate);
    $endIter = new DateTime($endDate);

    while ($curIter <= $endIter) {
        $dateKey = $curIter->format('Y-m-d');
        $trendLabels[] = $curIter->format('M j');
        $row = $dailyByDate[$dateKey] ?? null;
        $rev = (float)($row['revenue'] ?? 0);
        $orders = (int)($row['valid_order_count'] ?? 0);
        $revenueValues[] = $rev;
        $orderValues[] = $orders;

        if ($row && $row['total_orders'] > 0) {
            $tableRows[] = [
                'label'           => $curIter->format('D, M j, Y'),
                'total_orders'    => (int)$row['total_orders'],
                'delivered_count' => (int)$row['delivered_count'],
                'cancelled_count' => (int)$row['cancelled_count'],
                'food_sales'      => (float)$row['food_sales'],
                'gst_amount'      => (float)$row['gst_amount'],
                'delivery_fee'    => (float)$row['delivery_fee'],
                'discounts'       => (float)$row['discounts'],
                'revenue'         => $rev,
                'aov'             => $orders > 0 ? ($rev / $orders) : 0.0,
            ];
        }
        $curIter->modify('+1 day');
    }
}

// Reverse chronological order for ledger display
$displayTableRows = array_reverse($tableRows);

// 5. Query Category Mix & Contribution
$categorySales = db_fetch_all("
    SELECT c.category_id,
           c.category_name,
           COALESCE(SUM(oi.line_total), 0) AS category_revenue,
           COALESCE(SUM(oi.quantity), 0) AS items_sold
    FROM `categories` c
    JOIN `menu_items` m ON c.category_id = m.category_id
    JOIN `order_items` oi ON m.item_id = oi.item_id
    JOIN `orders` o ON oi.order_id = o.order_id
    WHERE o.order_status != 'CANCELLED'
      AND o.placed_at >= :start_date AND o.placed_at <= :end_date
    GROUP BY c.category_id, c.category_name
    ORDER BY category_revenue DESC
", [':start_date' => $startDate, ':end_date' => $endDate]);

$totalCategoryRevenue = 0.0;
foreach ($categorySales as $cs) {
    $totalCategoryRevenue += (float)$cs['category_revenue'];
}

$categoryLabels = [];
$categoryValues = [];
$categoryColors = [
    '#2E6F40', '#3b8a52', '#4ea666', '#68BA7F', '#82ca96', '#9ed9af', '#b9e8c7', '#CEFFDC'
];

foreach ($categorySales as $idx => $cs) {
    $categoryLabels[] = $cs['category_name'];
    $categoryValues[] = (float)$cs['category_revenue'];
}

// 6. Query Top 5 Selling Dishes (Product Velocity)
$topDishes = db_fetch_all("
    SELECT m.item_id,
           m.item_name,
           c.category_name,
           m.is_veg,
           m.price,
           m.rating,
           COALESCE(SUM(oi.quantity), 0) AS total_units_sold,
           COALESCE(SUM(oi.line_total), 0) AS total_revenue
    FROM `order_items` oi
    JOIN `orders` o ON oi.order_id = o.order_id
    JOIN `menu_items` m ON oi.item_id = m.item_id
    JOIN `categories` c ON m.category_id = c.category_id
    WHERE o.order_status != 'CANCELLED'
      AND o.placed_at >= :start_date AND o.placed_at <= :end_date
    GROUP BY m.item_id, m.item_name, c.category_name, m.is_veg, m.price, m.rating
    ORDER BY total_revenue DESC
    LIMIT 5
", [':start_date' => $startDate, ':end_date' => $endDate]);

// 7. Query Payment Mode & Settlement Mix
$paymentModes = db_fetch_all("
    SELECT COALESCE(p.payment_mode, 'COD') AS payment_mode,
           COUNT(*) AS payment_count,
           COALESCE(SUM(p.amount_paid), 0) AS total_amount
    FROM `payments` p
    JOIN `orders` o ON p.order_id = o.order_id
    WHERE o.order_status != 'CANCELLED'
      AND o.placed_at >= :start_date AND o.placed_at <= :end_date
    GROUP BY p.payment_mode
    ORDER BY total_amount DESC
", [':start_date' => $startDate, ':end_date' => $endDate]);

$totalPaymentAmount = 0.0;
foreach ($paymentModes as $pm) {
    $totalPaymentAmount += (float)$pm['total_amount'];
}

$paymentLabels = [];
$paymentValues = [];
$paymentModeNames = [
    'UPI'         => 'UPI / QR Scan',
    'COD'         => 'Cash on Delivery (COD)',
    'CREDIT_CARD' => 'Credit Card',
    'DEBIT_CARD'  => 'Debit Card',
    'NET_BANKING' => 'Net Banking',
];
$paymentColorPalette = [
    'UPI'         => '#2E6F40', // Lush Forest
    'COD'         => '#E5A100', // Gold/Amber
    'CREDIT_CARD' => '#2563EB', // Blue
    'DEBIT_CARD'  => '#0D9488', // Teal
    'NET_BANKING' => '#7C3AED', // Purple
];
$paymentChartColors = [];

foreach ($paymentModes as $pm) {
    $mode = $pm['payment_mode'];
    $paymentLabels[] = $paymentModeNames[$mode] ?? $mode;
    $paymentValues[] = (float)$pm['total_amount'];
    $paymentChartColors[] = $paymentColorPalette[$mode] ?? '#68BA7F';
}

// 8. Handle CSV Export (Before HTML output)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $cleanFilename = 'zippy_sales_report_' . $period . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $cleanFilename . '"');
    
    // Output BOM for Excel UTF-8 compatibility
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['ZIPPY ONLINE FOOD ORDERING PLATFORM - EXECUTIVE BUSINESS REPORT']);
    fputcsv($output, ['Report Period', $periodLabel]);
    fputcsv($output, ['Generated By', $currentUser['name'] ?? 'Administrator']);
    fputcsv($output, ['Generated At', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    
    // Summary KPI Section
    fputcsv($output, ['--- KPI SUMMARY METRICS ---']);
    fputcsv($output, ['Gross Revenue (INR)', number_format($kpiGrossRevenue, 2, '.', '')]);
    fputcsv($output, ['Net Food Sales (INR)', number_format($kpiNetFoodSales, 2, '.', '')]);
    fputcsv($output, ['Total Orders Placed', $kpiTotalOrders]);
    fputcsv($output, ['Delivered Orders', $kpiDelivered]);
    fputcsv($output, ['Cancelled Orders', $kpiCancelled]);
    fputcsv($output, ['Delivery Rate (%)', $deliveryRate . '%']);
    fputcsv($output, ['Cancellation Rate (%)', $cancellationRate . '%']);
    fputcsv($output, ['Average Order Value (INR)', number_format($kpiAov, 2, '.', '')]);
    fputcsv($output, ['GST Tax Collected (INR)', number_format($kpiTotalGst, 2, '.', '')]);
    fputcsv($output, ['Delivery Fees Collected (INR)', number_format($kpiDeliveryFee, 2, '.', '')]);
    fputcsv($output, ['Discounts Subsidized (INR)', number_format($kpiDiscounts, 2, '.', '')]);
    fputcsv($output, ['Cancelled Order Losses (INR)', number_format($kpiCancelledLoss, 2, '.', '')]);
    fputcsv($output, []);
    
    // Performance Breakdown Ledger
    fputcsv($output, ['--- PERFORMANCE BREAKDOWN LEDGER ---']);
    fputcsv($output, ['Timeframe / Date', 'Total Orders', 'Delivered', 'Cancelled', 'Food Sales (INR)', 'GST Tax (INR)', 'Delivery Fees (INR)', 'Discounts (INR)', 'Gross Revenue (INR)', 'AOV (INR)']);
    
    foreach ($displayTableRows as $tr) {
        fputcsv($output, [
            $tr['label'],
            $tr['total_orders'],
            $tr['delivered_count'],
            $tr['cancelled_count'],
            number_format($tr['food_sales'], 2, '.', ''),
            number_format($tr['gst_amount'], 2, '.', ''),
            number_format($tr['delivery_fee'], 2, '.', ''),
            number_format($tr['discounts'], 2, '.', ''),
            number_format($tr['revenue'], 2, '.', ''),
            number_format($tr['aov'], 2, '.', '')
        ]);
    }
    
    fclose($output);
    exit;
}

// 9. Render Admin Layout Header
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* Print Stylesheet for Executive PDF / Paper Export */
@media print {
  .admin-sidebar, 
  .admin-topbar, 
  .report-actions-bar, 
  .report-filter-bar, 
  .no-print {
    display: none !important;
  }
  .admin-main {
    margin-left: 0 !important;
    padding: 0 !important;
  }
  body {
    background: #FFFFFF !important;
    font-size: 11pt;
  }
  .card {
    border: 1px solid #E2ECE5 !important;
    box-shadow: none !important;
    page-break-inside: avoid;
    margin-bottom: 1.5rem !important;
  }
  .stat-card {
    box-shadow: none !important;
    border: 1px solid #E2ECE5 !important;
  }
}

/* Lush Forest Custom Accents */
.text-forest { color: #2E6F40 !important; }
.bg-forest { background-color: #2E6F40 !important; }
.border-forest { border-color: #2E6F40 !important; }

.btn-forest {
  background-color: #2E6F40;
  border-color: #2E6F40;
  color: #FFFFFF;
}
.btn-forest:hover {
  background-color: #235933;
  border-color: #235933;
  color: #FFFFFF;
}

.btn-outline-forest {
  border-color: #2E6F40;
  color: #2E6F40;
  background-color: transparent;
}
.btn-outline-forest:hover {
  background-color: #2E6F40;
  color: #FFFFFF;
}
</style>

<!-- =======================================================================
     HEADER & ACTION TOOLBAR
     ======================================================================= -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-1 mb-2">
      <i class="bi bi-graph-up-arrow me-1"></i> Executive BI Reports
    </span>
    <h1 class="h3 fw-bold font-outfit text-dark mb-1">Sales &amp; Business Intelligence</h1>
    <p class="text-muted mb-0 small">
      Showing audited performance for <strong class="text-dark"><?= e($periodLabel) ?></strong>.
    </p>
  </div>
  
  <div class="d-flex flex-wrap align-items-center gap-2 report-actions-bar">
    <!-- Export CSV -->
    <?php
      $exportParams = $_GET;
      $exportParams['export'] = 'csv';
      $exportUrl = ADMIN_URL . '/reports.php?' . http_build_query($exportParams);
    ?>
    <a href="<?= $exportUrl ?>" class="btn btn-outline-forest btn-sm rounded-pill px-3 fw-semibold shadow-xs d-inline-flex align-items-center gap-1">
      <i class="bi bi-file-earmark-spreadsheet"></i>
      <span>Export CSV</span>
    </a>
    
    <!-- Print / Save as PDF -->
    <button type="button" onclick="window.print()" class="btn btn-light border btn-sm rounded-pill px-3 fw-semibold shadow-xs d-inline-flex align-items-center gap-1">
      <i class="bi bi-printer"></i>
      <span>Print / PDF</span>
    </button>
  </div>
</div>

<!-- =======================================================================
     PERIOD FILTER SEGMENTED BAR & CUSTOM DATE PICKER
     ======================================================================= -->
<div class="card border rounded-4 shadow-xs p-3 bg-white mb-4 report-filter-bar" style="border-color:#E2ECE5 !important;">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
    <!-- Quick Preset Buttons -->
    <div class="d-flex flex-wrap align-items-center gap-2">
      <span class="small fw-bold text-muted text-uppercase me-1" style="font-size:0.75rem;">Period:</span>
      <a href="<?= ADMIN_URL ?>/reports.php?period=today" 
         class="btn btn-sm rounded-pill fw-semibold <?= ($period === 'today') ? 'btn-forest shadow-xs' : 'btn-light border' ?>">
        Today
      </a>
      <a href="<?= ADMIN_URL ?>/reports.php?period=yesterday" 
         class="btn btn-sm rounded-pill fw-semibold <?= ($period === 'yesterday') ? 'btn-forest shadow-xs' : 'btn-light border' ?>">
        Yesterday
      </a>
      <a href="<?= ADMIN_URL ?>/reports.php?period=7d" 
         class="btn btn-sm rounded-pill fw-semibold <?= ($period === '7d') ? 'btn-forest shadow-xs' : 'btn-light border' ?>">
        Last 7 Days
      </a>
      <a href="<?= ADMIN_URL ?>/reports.php?period=30d" 
         class="btn btn-sm rounded-pill fw-semibold <?= ($period === '30d') ? 'btn-forest shadow-xs' : 'btn-light border' ?>">
        Last 30 Days
      </a>
      <a href="<?= ADMIN_URL ?>/reports.php?period=this_month" 
         class="btn btn-sm rounded-pill fw-semibold <?= ($period === 'this_month') ? 'btn-forest shadow-xs' : 'btn-light border' ?>">
        This Month
      </a>
      <button type="button" 
              class="btn btn-sm rounded-pill fw-semibold <?= ($period === 'custom') ? 'btn-forest shadow-xs' : 'btn-light border' ?>" 
              data-bs-toggle="collapse" 
              data-bs-target="#customDateCollapse" 
              aria-expanded="<?= ($period === 'custom') ? 'true' : 'false' ?>">
        <i class="bi bi-calendar-range me-1"></i> Custom Range
      </button>
    </div>

    <!-- Live Indicator Tag -->
    <div class="text-muted small d-none d-md-flex align-items-center gap-2">
      <span class="live-pulse-dot green"></span>
      <span style="font-size:0.8rem;">Database Synced</span>
    </div>
  </div>

  <!-- Collapsible Custom Date Form -->
  <div class="collapse <?= ($period === 'custom') ? 'show' : '' ?> pt-3 mt-3 border-top" id="customDateCollapse">
    <form method="GET" action="<?= ADMIN_URL ?>/reports.php" class="row g-2 align-items-end">
      <input type="hidden" name="period" value="custom">
      <div class="col-sm-auto">
        <label for="startDateInput" class="form-label small fw-semibold text-muted mb-1">Start Date</label>
        <input type="date" 
               id="startDateInput" 
               name="start_date" 
               class="form-control form-control-sm rounded-3" 
               value="<?= e($rawStart ?: date('Y-m-d', strtotime('-6 days'))) ?>" 
               max="<?= date('Y-m-d') ?>" 
               required>
      </div>
      <div class="col-sm-auto">
        <label for="endDateInput" class="form-label small fw-semibold text-muted mb-1">End Date</label>
        <input type="date" 
               id="endDateInput" 
               name="end_date" 
               class="form-control form-control-sm rounded-3" 
               value="<?= e($rawEnd ?: date('Y-m-d')) ?>" 
               max="<?= date('Y-m-d') ?>" 
               required>
      </div>
      <div class="col-sm-auto">
        <button type="submit" class="btn btn-sm btn-forest rounded-pill px-3 fw-semibold">
          <i class="bi bi-funnel me-1"></i> Apply Filter
        </button>
        <?php if ($period === 'custom'): ?>
          <a href="<?= ADMIN_URL ?>/reports.php?period=7d" class="btn btn-sm btn-light border rounded-pill px-3 ms-1 text-muted">
            Reset
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- =======================================================================
     EXECUTIVE KPI SUMMARY CARDS (5 METRICS)
     ======================================================================= -->
<section class="row g-3 mb-4" aria-label="Executive KPI Overview">
  <!-- Card 1: Gross Revenue -->
  <div class="col-sm-6 col-lg-4 col-xl-2_5">
    <div class="stat-card dashboard-metric metric-success h-100">
      <div>
        <div class="metric-label">Gross Revenue</div>
        <div class="metric-value"><?= format_inr($kpiGrossRevenue) ?></div>
        <div class="d-flex align-items-center gap-1 mt-1">
          <?php if ($revenueGrowthPct >= 0): ?>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill py-0 px-2" style="font-size:0.7rem;">
              <i class="bi bi-arrow-up-right me-0_5"></i>+<?= number_format($revenueGrowthPct, 1) ?>%
            </span>
          <?php else: ?>
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill py-0 px-2" style="font-size:0.7rem;">
              <i class="bi bi-arrow-down-right me-0_5"></i><?= number_format($revenueGrowthPct, 1) ?>%
            </span>
          <?php endif; ?>
          <span class="text-muted" style="font-size:0.72rem;"><?= e($comparisonLabel) ?></span>
        </div>
      </div>
      <span class="metric-icon"><i class="bi bi-currency-rupee"></i></span>
    </div>
  </div>

  <!-- Card 2: Net Food Sales -->
  <div class="col-sm-6 col-lg-4 col-xl-2_5">
    <div class="stat-card dashboard-metric metric-mint h-100">
      <div>
        <div class="metric-label">Net Food Sales</div>
        <div class="metric-value"><?= format_inr($kpiNetFoodSales) ?></div>
        <div class="text-muted mt-1" style="font-size:0.72rem;">
          GST: <?= format_inr($kpiTotalGst) ?> &bull; Deliv: <?= format_inr($kpiDeliveryFee) ?>
        </div>
      </div>
      <span class="metric-icon"><i class="bi bi-receipt-cutoff"></i></span>
    </div>
  </div>

  <!-- Card 3: Order Volume -->
  <div class="col-sm-6 col-lg-4 col-xl-2_5">
    <div class="stat-card dashboard-metric metric-info h-100">
      <div>
        <div class="metric-label">Completed Orders</div>
        <div class="metric-value"><?= number_format($kpiValidOrders) ?></div>
        <div class="text-muted mt-1" style="font-size:0.72rem;">
          <span class="text-success fw-semibold"><?= $kpiDelivered ?> delivered</span> (<?= $deliveryRate ?>% fulfilled)
        </div>
      </div>
      <span class="metric-icon"><i class="bi bi-bag-check"></i></span>
    </div>
  </div>

  <!-- Card 4: Average Order Value (AOV) -->
  <div class="col-sm-6 col-lg-4 col-xl-2_5">
    <div class="stat-card dashboard-metric metric-warning h-100">
      <div>
        <div class="metric-label">Avg. Order Value</div>
        <div class="metric-value"><?= format_inr($kpiAov) ?></div>
        <div class="text-muted mt-1" style="font-size:0.72rem;">
          Discounts: <?= format_inr($kpiDiscounts) ?> absorbed
        </div>
      </div>
      <span class="metric-icon"><i class="bi bi-cart4"></i></span>
    </div>
  </div>

  <!-- Card 5: Cancellations & Loss -->
  <div class="col-sm-6 col-lg-4 col-xl-2_5">
    <div class="stat-card dashboard-metric metric-cod h-100">
      <div>
        <div class="metric-label">Cancellations</div>
        <div class="metric-value <?= $kpiCancelled > 0 ? 'text-danger' : '' ?>">
          <?= number_format($kpiCancelled) ?> <span style="font-size:1rem; font-weight:500;">(<?= $cancellationRate ?>%)</span>
        </div>
        <div class="text-muted mt-1" style="font-size:0.72rem;">
          Lost Revenue: <span class="fw-semibold text-danger"><?= format_inr($kpiCancelledLoss) ?></span>
        </div>
      </div>
      <span class="metric-icon"><i class="bi bi-x-circle"></i></span>
    </div>
  </div>
</section>

<!-- =======================================================================
     ROW 1: SALES & DEMAND COMBO CHART + CATEGORY MIX
     ======================================================================= -->
<div class="row g-3 mb-4">
  <!-- Left: Revenue & Orders Combo Chart -->
  <div class="col-xl-8">
    <section class="card border rounded-4 shadow-xs p-3 p-xl-4 bg-white h-100" style="border-color:#E2ECE5 !important;" aria-labelledby="salesTrendHeading">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
          <h2 class="h5 fw-bold font-outfit text-dark mb-1" id="salesTrendHeading">
            <?= $isSingleDay ? 'Hourly Demand & Revenue Curve' : 'Daily Revenue & Order Volume' ?>
          </h2>
          <p class="small text-muted mb-0">
            Green bars indicate gross revenue; the amber line plots concurrent order volume (cancelled orders excluded).
          </p>
        </div>
        <span class="badge rounded-pill bg-light text-dark border px-3 py-1 small">
          <?= $isSingleDay ? '24-Hour Distribution' : count($trendLabels) . ' Days Tracked' ?>
        </span>
      </div>

      <?php if ($kpiGrossRevenue == 0 && $kpiTotalOrders == 0): ?>
        <div class="d-flex flex-column align-items-center justify-content-center text-center text-muted py-5" style="min-height:320px;">
          <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mb-3" style="width:64px; height:64px;">
            <i class="bi bi-bar-chart text-muted fs-3"></i>
          </div>
          <h6 class="fw-bold text-dark mb-1">No Orders Recorded</h6>
          <p class="small text-muted mb-0">There are no completed or active orders recorded in this timeframe.</p>
        </div>
      <?php else: ?>
        <div style="height:320px;position:relative">
          <canvas id="salesTrendChart" role="img" aria-label="Sales and order volume chart"></canvas>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <!-- Right: Revenue by Category -->
  <div class="col-xl-4">
    <section class="card border rounded-4 shadow-xs p-3 p-xl-4 bg-white h-100" style="border-color:#E2ECE5 !important;" aria-labelledby="categoryMixHeading">
      <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
        <div>
          <h2 class="h5 fw-bold font-outfit text-dark mb-1" id="categoryMixHeading">Revenue by Category</h2>
          <p class="small text-muted mb-0">Product mix contribution for the period.</p>
        </div>
      </div>

      <?php if (empty($categorySales)): ?>
        <div class="d-flex flex-column align-items-center justify-content-center text-center text-muted py-5" style="min-height:320px;">
          <i class="bi bi-tag text-muted fs-3 mb-2"></i>
          <p class="small text-muted mb-0">No category sales recorded for this period.</p>
        </div>
      <?php else: ?>
        <div style="height:250px;position:relative" class="mb-3">
          <canvas id="categorySalesChart" role="img" aria-label="Category revenue breakdown chart"></canvas>
        </div>

        <!-- Top 3 Category Summary Pills -->
        <div class="d-flex flex-column gap-2 border-top pt-3">
          <?php 
            $topThreeCats = array_slice($categorySales, 0, 3);
            foreach ($topThreeCats as $i => $cat): 
              $cRev = (float)$cat['category_revenue'];
              $cPct = $totalCategoryRevenue > 0 ? round(($cRev / $totalCategoryRevenue) * 100, 1) : 0;
          ?>
            <div class="d-flex align-items-center justify-content-between small">
              <span class="text-truncate me-2 text-dark">
                <span class="badge rounded-circle p-1 me-1" style="background:<?= $categoryColors[$i % count($categoryColors)] ?>;"> </span>
                <?= e($cat['category_name']) ?>
              </span>
              <span class="fw-bold font-monospace text-dark">
                <?= format_inr($cRev) ?> <span class="text-muted fw-normal" style="font-size:0.75rem;">(<?= $cPct ?>%)</span>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<!-- =======================================================================
     ROW 2: PRODUCT VELOCITY (TOP DISHES) & PAYMENT SETTLEMENT MIX
     ======================================================================= -->
<div class="row g-3 mb-4">
  <!-- Left: Top Selling Dishes -->
  <div class="col-xl-7">
    <section class="card border rounded-4 shadow-xs p-3 p-xl-4 bg-white h-100" style="border-color:#E2ECE5 !important;">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
          <h2 class="h5 fw-bold font-outfit text-dark mb-1">Top Selling Dishes (Product Velocity)</h2>
          <p class="small text-muted mb-0">Leaderboard of highest revenue and quantity dishes in this timeframe.</p>
        </div>
        <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-1 small">
          Ranked by Revenue
        </span>
      </div>

      <?php if (empty($topDishes)): ?>
        <div class="text-center text-muted small py-4">
          No dish sales recorded in this period.
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted text-uppercase">
              <tr>
                <th style="font-size:0.72rem;">Dish Name</th>
                <th style="font-size:0.72rem;">Category</th>
                <th class="text-center" style="font-size:0.72rem;">Price</th>
                <th class="text-center" style="font-size:0.72rem;">Units Sold</th>
                <th class="text-end" style="font-size:0.72rem;">Total Revenue</th>
                <th class="text-end" style="font-size:0.72rem;">Rating</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topDishes as $rank => $dish): ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <span class="badge rounded-circle bg-light text-dark fw-bold" style="width:22px; height:22px; font-size:0.7rem; display:inline-flex; align-items:center; justify-content:center;">
                        <?= $rank + 1 ?>
                      </span>
                      <?php if (!empty($dish['is_veg'])): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle p-1" title="Vegetarian"><i class="bi bi-circle-fill" style="font-size:0.5rem;"></i></span>
                      <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle p-1" title="Non-Vegetarian"><i class="bi bi-triangle-fill" style="font-size:0.5rem;"></i></span>
                      <?php endif; ?>
                      <span class="fw-semibold text-dark text-truncate" style="max-width:200px;">
                        <?= e($dish['item_name']) ?>
                      </span>
                    </div>
                  </td>
                  <td>
                    <span class="badge bg-light text-muted border rounded-pill px-2 py-1" style="font-size:0.72rem;">
                      <?= e($dish['category_name']) ?>
                    </span>
                  </td>
                  <td class="text-center font-monospace small">
                    <?= format_inr($dish['price']) ?>
                  </td>
                  <td class="text-center">
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 fw-bold">
                      <?= number_format($dish['total_units_sold']) ?> sold
                    </span>
                  </td>
                  <td class="text-end fw-bold font-monospace text-dark">
                    <?= format_inr($dish['total_revenue']) ?>
                  </td>
                  <td class="text-end">
                    <span class="text-warning small fw-semibold">
                      <i class="bi bi-star-fill text-warning"></i> <?= number_format((float)$dish['rating'], 1) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <!-- Right: Payment Settlement Mix -->
  <div class="col-xl-5">
    <section class="card border rounded-4 shadow-xs p-3 p-xl-4 bg-white h-100" style="border-color:#E2ECE5 !important;">
      <div class="mb-3">
        <h2 class="h5 fw-bold font-outfit text-dark mb-1">Payment Settlement Mix</h2>
        <p class="small text-muted mb-0">Distribution of digital gateways vs. doorstep Cash on Delivery (COD).</p>
      </div>

      <?php if (empty($paymentModes)): ?>
        <div class="text-center text-muted small py-4">
          No payment transactions available for this period.
        </div>
      <?php else: ?>
        <div style="height:210px;position:relative" class="mb-3">
          <canvas id="paymentModeChart" role="img" aria-label="Payment mode distribution chart"></canvas>
        </div>

        <div class="d-flex flex-column gap-2 border-top pt-3">
          <?php foreach ($paymentModes as $i => $pm): 
            $mCode = $pm['payment_mode'];
            $mLabel = $paymentModeNames[$mCode] ?? $mCode;
            $mAmt = (float)$pm['total_amount'];
            $mPct = $totalPaymentAmount > 0 ? round(($mAmt / $totalPaymentAmount) * 100, 1) : 0;
            $mColor = $paymentColorPalette[$mCode] ?? '#68BA7F';
          ?>
            <div class="d-flex align-items-center justify-content-between small">
              <span class="text-truncate me-2 text-dark">
                <span class="badge rounded-circle p-1 me-1" style="background:<?= $mColor ?>;"> </span>
                <?= e($mLabel) ?> <span class="text-muted">(<?= (int)$pm['payment_count'] ?> orders)</span>
              </span>
              <span class="fw-bold font-monospace text-dark">
                <?= format_inr($mAmt) ?> <span class="text-muted fw-normal" style="font-size:0.75rem;">(<?= $mPct ?>%)</span>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<!-- =======================================================================
     ROW 3: AUDITED DAILY PERFORMANCE LEDGER TABLE
     ======================================================================= -->
<div class="card border rounded-4 shadow-xs bg-white mb-4 overflow-hidden" style="border-color:#E2ECE5 !important;">
  <div class="p-3 p-xl-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h2 class="h5 fw-bold font-outfit text-dark mb-1">Performance Breakdown Ledger</h2>
      <p class="small text-muted mb-0">
        Chronological audit of transactions, net food billings, tax collections, and gross revenue.
      </p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-light text-muted border rounded-pill px-3 py-2 small">
        <?= count($displayTableRows) ?> records
      </span>
      <a href="<?= $exportUrl ?>" class="btn btn-sm btn-outline-forest rounded-pill px-3">
        <i class="bi bi-download me-1"></i> CSV
      </a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" id="reportsAuditTable">
      <thead class="table-light small text-muted text-uppercase">
        <tr>
          <th style="font-size:0.72rem;"><?= $isSingleDay ? 'Hour Slot' : 'Date' ?></th>
          <th class="text-center" style="font-size:0.72rem;">Orders Placed</th>
          <th class="text-center" style="font-size:0.72rem;">Delivered</th>
          <th class="text-center" style="font-size:0.72rem;">Cancelled</th>
          <th class="text-end" style="font-size:0.72rem;">Food Sales (Subtotal)</th>
          <th class="text-end" style="font-size:0.72rem;">GST (5%)</th>
          <th class="text-end" style="font-size:0.72rem;">Delivery Fees</th>
          <th class="text-end" style="font-size:0.72rem;">Discounts</th>
          <th class="text-end" style="font-size:0.72rem;">Gross Revenue</th>
          <th class="text-end" style="font-size:0.72rem;">Avg. Order Value</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($displayTableRows)): ?>
          <tr>
            <td colspan="10" class="text-center text-muted py-4 small">
              No sales activity found for the selected timeframe.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($displayTableRows as $tr): ?>
            <tr>
              <td class="fw-semibold text-dark small font-monospace">
                <?= e($tr['label']) ?>
              </td>
              <td class="text-center">
                <span class="badge bg-light text-dark border rounded-pill px-2 py-1">
                  <?= (int)$tr['total_orders'] ?>
                </span>
              </td>
              <td class="text-center">
                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1">
                  <?= (int)$tr['delivered_count'] ?>
                </span>
              </td>
              <td class="text-center">
                <?php if ($tr['cancelled_count'] > 0): ?>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1">
                    <?= (int)$tr['cancelled_count'] ?>
                  </span>
                <?php else: ?>
                  <span class="text-muted small">0</span>
                <?php endif; ?>
              </td>
              <td class="text-end font-monospace small">
                <?= format_inr($tr['food_sales']) ?>
              </td>
              <td class="text-end font-monospace small text-muted">
                <?= format_inr($tr['gst_amount']) ?>
              </td>
              <td class="text-end font-monospace small text-muted">
                <?= format_inr($tr['delivery_fee']) ?>
              </td>
              <td class="text-end font-monospace small text-muted">
                <?= $tr['discounts'] > 0 ? '-' . format_inr($tr['discounts']) : '₹0.00' ?>
              </td>
              <td class="text-end font-monospace fw-bold text-forest">
                <?= format_inr($tr['revenue']) ?>
              </td>
              <td class="text-end font-monospace small text-dark">
                <?= format_inr($tr['aov']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <?php if (!empty($displayTableRows)): ?>
        <tfoot class="table-light fw-bold">
          <tr>
            <td class="text-dark small">PERIOD TOTALS</td>
            <td class="text-center"><?= number_format($kpiTotalOrders) ?></td>
            <td class="text-center text-success"><?= number_format($kpiDelivered) ?></td>
            <td class="text-center text-danger"><?= number_format($kpiCancelled) ?></td>
            <td class="text-end font-monospace"><?= format_inr($kpiNetFoodSales) ?></td>
            <td class="text-end font-monospace text-muted"><?= format_inr($kpiTotalGst) ?></td>
            <td class="text-end font-monospace text-muted"><?= format_inr($kpiDeliveryFee) ?></td>
            <td class="text-end font-monospace text-muted"><?= format_inr($kpiDiscounts) ?></td>
            <td class="text-end font-monospace text-forest fs-6"><?= format_inr($kpiGrossRevenue) ?></td>
            <td class="text-end font-monospace text-dark"><?= format_inr($kpiAov) ?></td>
          </tr>
        </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<!-- =======================================================================
     CHART.JS SCRIPTS & CONFIGURATIONS
     ======================================================================= -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Chart.js Default Font Configuration
  Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
  Chart.defaults.color = '#576E5F';

  // 1. Combo Chart: Revenue (Bar) + Orders (Spline Line)
  const trendCanvas = document.getElementById('salesTrendChart');
  if (trendCanvas) {
    new Chart(trendCanvas, {
      data: {
        labels: <?= json_encode($trendLabels) ?>,
        datasets: [
          {
            type: 'bar',
            label: 'Gross Revenue (INR)',
            data: <?= json_encode($revenueValues) ?>,
            backgroundColor: 'rgba(46, 111, 64, 0.75)',
            hoverBackgroundColor: '#2E6F40',
            borderRadius: 6,
            yAxisID: 'yRevenue',
            order: 2
          },
          {
            type: 'line',
            label: 'Order Volume',
            data: <?= json_encode($orderValues) ?>,
            borderColor: '#E5A100',
            backgroundColor: '#E5A100',
            borderWidth: 2.5,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: '#FFFFFF',
            pointBorderColor: '#E5A100',
            pointBorderWidth: 2,
            tension: 0.35,
            yAxisID: 'yOrders',
            order: 1
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false
        },
        plugins: {
          tooltip: {
            padding: 12,
            backgroundColor: 'rgba(36, 62, 44, 0.95)',
            titleFont: { weight: 'bold', size: 13 },
            callbacks: {
              label: function(context) {
                if (context.dataset.yAxisID === 'yRevenue') {
                  const val = Number(context.raw);
                  return 'Revenue: ₹' + val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
                return 'Orders: ' + context.raw + ' order(s)';
              }
            }
          },
          legend: {
            position: 'top',
            labels: {
              usePointStyle: true,
              boxWidth: 8,
              padding: 15,
              font: { weight: '600' }
            }
          }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { maxRotation: 45, minRotation: 0 }
          },
          yRevenue: {
            type: 'linear',
            position: 'left',
            beginAtZero: true,
            grid: { color: 'rgba(226, 236, 229, 0.6)' },
            title: {
              display: true,
              text: 'Revenue (INR)',
              font: { weight: 'bold' }
            },
            ticks: {
              callback: function(val) {
                return '₹' + Number(val).toLocaleString('en-IN');
              }
            }
          },
          yOrders: {
            type: 'linear',
            position: 'right',
            beginAtZero: true,
            grid: { drawOnChartArea: false },
            title: {
              display: true,
              text: 'Orders Placed',
              font: { weight: 'bold' }
            },
            ticks: {
              stepSize: 1,
              precision: 0
            }
          }
        }
      }
    });
  }

  // 2. Horizontal Bar Chart: Category Revenue Mix
  const categoryCanvas = document.getElementById('categorySalesChart');
  if (categoryCanvas) {
    const totalCatRevenue = <?= (float)$totalCategoryRevenue ?>;
    new Chart(categoryCanvas, {
      type: 'bar',
      data: {
        labels: <?= json_encode($categoryLabels) ?>,
        datasets: [{
          label: 'Revenue (INR)',
          data: <?= json_encode($categoryValues) ?>,
          backgroundColor: <?= json_encode($categoryColors) ?>,
          borderRadius: 6
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            padding: 10,
            callbacks: {
              label: function(context) {
                const val = Number(context.raw);
                const pct = totalCatRevenue > 0 ? ((val / totalCatRevenue) * 100).toFixed(1) : 0;
                return '₹' + val.toLocaleString('en-IN', { minimumFractionDigits: 2 }) + ' (' + pct + '% of sales)';
              }
            }
          }
        },
        scales: {
          x: {
            beginAtZero: true,
            grid: { color: 'rgba(226, 236, 229, 0.5)' },
            ticks: {
              callback: function(val) {
                return '₹' + Number(val).toLocaleString('en-IN');
              }
            }
          },
          y: {
            grid: { display: false }
          }
        }
      }
    });
  }

  // 3. Donut Chart: Payment Modes Settlement Mix
  const paymentCanvas = document.getElementById('paymentModeChart');
  if (paymentCanvas) {
    const totalPayAmt = <?= (float)$totalPaymentAmount ?>;
    new Chart(paymentCanvas, {
      type: 'doughnut',
      data: {
        labels: <?= json_encode($paymentLabels) ?>,
        datasets: [{
          data: <?= json_encode($paymentValues) ?>,
          backgroundColor: <?= json_encode($paymentChartColors) ?>,
          borderWidth: 2,
          borderColor: '#FFFFFF'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            padding: 10,
            callbacks: {
              label: function(context) {
                const val = Number(context.raw);
                const pct = totalPayAmt > 0 ? ((val / totalPayAmt) * 100).toFixed(1) : 0;
                return context.label + ': ₹' + val.toLocaleString('en-IN', { minimumFractionDigits: 2 }) + ' (' + pct + '%)';
              }
            }
          }
        }
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
