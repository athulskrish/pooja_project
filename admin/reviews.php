<?php
declare(strict_types=1);
/**
 * Customer Food Reviews & Star Ratings Console
 * Zippy - Online Food Ordering Platform
 * Design: Figma "Lush Forest" Palette (#Combination-4)
 * Student: Pooja JP | MCSP-232 Major Project
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce strict Role-Based Access Control before any processing or file export
requireAdmin();

$pageTitle = 'Customer Food Reviews & Star Ratings';
$activePage = 'reviews';

// =============================================================================
// CSV EXPORT HANDLER (Executed before HTML headers are sent)
// =============================================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportTab = cleanInput($_GET['tab'] ?? 'orders');
    header('Content-Type: text/csv; charset=utf-8');

    if ($exportTab === 'dishes') {
        header('Content-Disposition: attachment; filename=zippy_dish_reviews_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'Review ID', 
            'Dish Name', 
            'Category', 
            'Dietary Type', 
            'Price (INR)', 
            'Customer Name', 
            'Customer Email', 
            'Overall Star Rating', 
            'Appearance Rating', 
            'Taste Rating', 
            'Portion Size Rating', 
            'Value Rating', 
            'Attribute Tags', 
            'Review Comments', 
            'Associated Order ID', 
            'Review Date'
        ]);

        $dishReviewsExport = AdminService::getDishReviewsForExport();

        foreach ($dishReviewsExport as $dr) {
            fputcsv($output, [
                $dr['dish_review_id'],
                $dr['item_name'],
                $dr['category_name'] ?? 'General',
                $dr['is_veg'] ? 'Vegetarian' : 'Non-Vegetarian',
                $dr['price'],
                $dr['customer_name'],
                $dr['customer_email'],
                $dr['rating'],
                $dr['appearance_rating'] ?? $dr['rating'],
                $dr['taste_rating'] ?? $dr['rating'],
                $dr['portion_rating'] ?? $dr['rating'],
                $dr['value_rating'] ?? $dr['rating'],
                $dr['tags'] ?? '',
                $dr['review_text'] ?? '',
                $dr['order_id'] ? '#ZIP-' . $dr['order_id'] : 'Direct Catalog Rating',
                $dr['created_at']
            ]);
        }
        fclose($output);
        exit;
    } else {
        header('Content-Disposition: attachment; filename=zippy_order_reviews_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Review ID', 'Order ID', 'Customer Name', 'Customer Email', 'Order Total (INR)', 'Food Rating', 'Rider Rating', 'Delivery Partner', 'Rider Tip (INR)', 'Ordered Items', 'Customer Feedback', 'Review Date']);

        $orderReviewsExport = AdminService::getOrderReviewsForExport();

        foreach ($orderReviewsExport as $or) {
            fputcsv($output, [
                $or['review_id'],
                '#ZIP-' . $or['order_id'],
                $or['customer_name'],
                $or['customer_email'],
                $or['total_amount'] ?? '0.00',
                $or['food_rating'],
                $or['rider_rating'],
                $or['delivery_partner_name'],
                $or['rider_tip'],
                $or['items_summary'] ?? 'N/A',
                $or['comments'] ?? '',
                $or['created_at']
            ]);
        }
        fclose($output);
        exit;
    }
}

// Include standard Admin layout header
require_once __DIR__ . '/includes/header.php';

// Request Parameters
$viewTab = cleanInput($_GET['tab'] ?? 'orders');
if (!in_array($viewTab, ['orders', 'dishes'], true)) {
    $viewTab = 'orders';
}
$starFilter = isset($_GET['star']) ? (int)$_GET['star'] : 0;
$sentimentFilter = cleanInput($_GET['sentiment'] ?? 'all');
$searchQuery = cleanInput($_GET['search'] ?? '');

$revData = AdminService::getAdminReviewsData($viewTab, $starFilter, $sentimentFilter, $searchQuery);
$totalOrderReviews = $revData['totalOrderReviews'];
$avgFoodRating = $revData['avgFoodRating'];
$avgRiderRating = $revData['avgRiderRating'];
$fiveStarFoodCount = $revData['fiveStarFoodCount'];
$criticalReviewsCount = $revData['criticalReviewsCount'];
$totalTips = $revData['totalTips'];
$totalDishReviews = $revData['totalDishReviews'];
$avgDishRating = $revData['avgDishRating'];
$criticalDishReviewsCount = $revData['criticalDishReviewsCount'];
$avgAppearance = $revData['avgAppearance'];
$avgTaste = $revData['avgTaste'];
$avgPortion = $revData['avgPortion'];
$avgValue = $revData['avgValue'];
$orderStarDist = $revData['orderStarDist'];
$dishStarDist = $revData['dishStarDist'];
$activeDist = $revData['activeDist'];
$activeTotal = $revData['activeTotal'];
$orderReviewsList = $revData['orderReviewsList'];
$dishReviewsList = $revData['dishReviewsList'];
$hasActiveFilters = $revData['hasActiveFilters'];
?>

<!-- =========================================================================
     PAGE HEADER & TOP ACTIONS
     ========================================================================= -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
  <div>
    <h3 class="fw-bold font-outfit text-dark mb-1">
      <i class="bi bi-star-fill text-warning me-2"></i>Customer Food Reviews & Ratings ⭐
    </h3>
    <p class="text-muted small mb-0">Live feedback, culinary taste scores, courier satisfaction, and quality assurance</p>
  </div>
  <div class="d-flex align-items-center gap-2">
    <!-- CSV Export Button -->
    <a href="<?= ADMIN_URL ?>/reviews.php?export=csv&tab=<?= e($viewTab) ?>" class="btn btn-outline-forest btn-sm rounded-pill px-3 shadow-xs" title="Export currently active review tab to CSV">
      <i class="bi bi-download me-1"></i> Export <?= $viewTab === 'dishes' ? 'Dishes' : 'Orders' ?> CSV
    </a>
    <a href="<?= CUSTOMER_URL ?>/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3" target="_blank" title="View customer storefront">
      <i class="bi bi-shop me-1"></i> Storefront
    </a>
  </div>
</div>

<!-- =========================================================================
     KPI SUMMARY METRICS
     ========================================================================= -->
<div class="row g-3 mb-4">
  
  <?php if ($viewTab === 'dishes'): ?>
    <!-- 1. Appearance Metric -->
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="small fw-bold text-muted text-uppercase">AVERAGE APPEARANCE</span>
          <span class="badge rounded-circle p-2 bg-primary bg-opacity-10 text-primary">
            <i class="bi bi-eye-fill"></i>
          </span>
        </div>
        <div class="d-flex align-items-baseline gap-2">
          <h2 class="fw-bold font-outfit text-dark mb-0">
            <?= $totalDishReviews > 0 ? number_format($avgAppearance, 1) : 'N/A' ?>
          </h2>
          <?php if ($totalDishReviews > 0): ?>
            <span class="fs-6 text-primary">/ 5.0 ★</span>
          <?php endif; ?>
        </div>
        <div class="small mt-2 d-flex align-items-center justify-content-between">
          <span class="text-muted">Plating &amp; visual presentation</span>
          <span class="text-warning"><?= renderStarRatingHtml($avgAppearance) ?></span>
        </div>
      </div>
    </div>

    <!-- 2. Taste Metric -->
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="small fw-bold text-muted text-uppercase">AVERAGE TASTE</span>
          <span class="badge rounded-circle p-2 bg-danger bg-opacity-10 text-danger">
            <i class="bi bi-fire"></i>
          </span>
        </div>
        <div class="d-flex align-items-baseline gap-2">
          <h2 class="fw-bold font-outfit text-dark mb-0">
            <?= $totalDishReviews > 0 ? number_format($avgTaste, 1) : 'N/A' ?>
          </h2>
          <?php if ($totalDishReviews > 0): ?>
            <span class="fs-6 text-danger">/ 5.0 ★</span>
          <?php endif; ?>
        </div>
        <div class="small mt-2 d-flex align-items-center justify-content-between">
          <span class="text-muted">Flavor, aroma &amp; seasoning</span>
          <span class="text-warning"><?= renderStarRatingHtml($avgTaste) ?></span>
        </div>
      </div>
    </div>

    <!-- 3. Portion Size Metric -->
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="small fw-bold text-muted text-uppercase">AVERAGE PORTION SIZE</span>
          <span class="badge rounded-circle p-2 bg-warning bg-opacity-25 text-warning">
            <i class="bi bi-pie-chart-fill"></i>
          </span>
        </div>
        <div class="d-flex align-items-baseline gap-2">
          <h2 class="fw-bold font-outfit text-dark mb-0">
            <?= $totalDishReviews > 0 ? number_format($avgPortion, 1) : 'N/A' ?>
          </h2>
          <?php if ($totalDishReviews > 0): ?>
            <span class="fs-6 text-warning">/ 5.0 ★</span>
          <?php endif; ?>
        </div>
        <div class="small mt-2 d-flex align-items-center justify-content-between">
          <span class="text-muted">Serving size &amp; fullness</span>
          <span class="text-warning"><?= renderStarRatingHtml($avgPortion) ?></span>
        </div>
      </div>
    </div>

    <!-- 4. Value for Money Metric -->
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="small fw-bold text-muted text-uppercase">AVERAGE VALUE</span>
          <span class="badge rounded-circle p-2 bg-success bg-opacity-10 text-success">
            <i class="bi bi-tag-fill"></i>
          </span>
        </div>
        <div class="d-flex align-items-baseline gap-2">
          <h2 class="fw-bold font-outfit text-forest mb-0">
            <?= $totalDishReviews > 0 ? number_format($avgValue, 1) : 'N/A' ?>
          </h2>
          <?php if ($totalDishReviews > 0): ?>
            <span class="fs-6 text-success">/ 5.0 ★</span>
          <?php endif; ?>
        </div>
        <div class="small mt-2 d-flex align-items-center justify-content-between">
          <span class="text-muted">Price-to-quality rating</span>
          <span class="text-warning"><?= renderStarRatingHtml($avgValue) ?></span>
        </div>
      </div>
    </div>

  <?php else: ?>
    <!-- Food Quality Index -->
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="small fw-bold text-muted text-uppercase">AVERAGE FOOD RATING</span>
          <span class="badge rounded-circle p-2 bg-warning bg-opacity-25 text-warning">
            <i class="bi bi-star-fill"></i>
          </span>
        </div>
        <div class="d-flex align-items-baseline gap-2">
          <h2 class="fw-bold font-outfit text-dark mb-0">
            <?= $totalOrderReviews > 0 ? number_format($avgFoodRating, 1) : 'N/A' ?>
          </h2>
          <?php if ($totalOrderReviews > 0): ?>
            <span class="fs-6 text-warning">/ 5.0 ⭐</span>
          <?php endif; ?>
        </div>
        <div class="small mt-2 d-flex align-items-center justify-content-between">
          <span class="text-muted">Based on <?= $totalOrderReviews ?> order reviews</span>
          <span class="text-warning"><?= renderStarRatingHtml($avgFoodRating) ?></span>
        </div>
      </div>
    </div>

    <!-- Courier Delivery Satisfaction -->
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="small fw-bold text-muted text-uppercase">COURIER SATISFACTION</span>
          <span class="badge rounded-circle p-2 bg-success bg-opacity-25 text-success">
            <i class="bi bi-bicycle"></i>
          </span>
        </div>
        <div class="d-flex align-items-baseline gap-2">
          <h2 class="fw-bold font-outfit text-forest mb-0">
            <?= $totalOrderReviews > 0 ? number_format($avgRiderRating, 1) : 'N/A' ?>
          </h2>
          <?php if ($totalOrderReviews > 0): ?>
            <span class="fs-6 text-muted">/ 5.0 🛵</span>
          <?php endif; ?>
        </div>
        <div class="small text-muted mt-2 d-flex align-items-center justify-content-between">
          <span>Tips remitted: <strong class="text-forest"><?= formatInr($totalTips) ?></strong></span>
          <span class="text-success"><?= renderStarRatingHtml($avgRiderRating) ?></span>
        </div>
      </div>
    </div>

    <!-- Critical Reviews Requiring Action -->
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100 <?= $criticalReviewsCount > 0 ? 'border-start border-danger border-4' : '' ?>">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="small fw-bold text-muted text-uppercase">CRITICAL FEEDBACK (&le; 2★)</span>
          <span class="badge rounded-circle p-2 <?= $criticalReviewsCount > 0 ? 'bg-danger bg-opacity-25 text-danger' : 'bg-success bg-opacity-25 text-success' ?>">
            <i class="bi <?= $criticalReviewsCount > 0 ? 'bi-exclamation-triangle-fill' : 'bi-shield-check' ?>"></i>
          </span>
        </div>
        <h2 class="fw-bold font-outfit mb-0 <?= $criticalReviewsCount > 0 ? 'text-danger' : 'text-dark' ?>">
          <?= $criticalReviewsCount ?> <?= $criticalReviewsCount === 1 ? 'Review' : 'Reviews' ?>
        </h2>
        <div class="small mt-2">
          <?php if ($criticalReviewsCount > 0): ?>
            <a href="<?= ADMIN_URL ?>/reviews.php?tab=orders&sentiment=critical" class="text-danger fw-semibold text-decoration-none">
              <i class="bi bi-arrow-right-circle me-1"></i> Inspect low rating alerts
            </a>
          <?php else: ?>
            <span class="text-success"><i class="bi bi-check2-circle me-1"></i> Zero service complaints logged</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Catalog Dish Reviews -->
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="small fw-bold text-muted text-uppercase">DISH-LEVEL REVIEWS</span>
          <span class="badge rounded-circle p-2 bg-info bg-opacity-25 text-info">
            <i class="bi bi-chat-heart-fill"></i>
          </span>
        </div>
        <div class="d-flex align-items-baseline gap-2">
          <h2 class="fw-bold font-outfit text-dark mb-0">
            <?= $totalDishReviews ?> Verified
          </h2>
          <?php if ($totalDishReviews > 0): ?>
            <span class="fs-6 text-muted">(<?= number_format($avgDishRating, 1) ?> ★)</span>
          <?php endif; ?>
        </div>
        <div class="small text-muted mt-2 d-flex align-items-center justify-content-between">
          <span>Taste, Appearance &amp; Portion</span>
          <span class="text-warning"><?= renderStarRatingHtml($avgDishRating) ?></span>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>

<!-- =========================================================================
     RATING DISTRIBUTION HISTOGRAM & PERFORMANCE CURVE
     ========================================================================= -->
<div class="card border-0 rounded-4 shadow-sm p-4 mb-4 bg-white">
  <div class="row align-items-center g-4">
    
    <!-- Score Summary on Left -->
    <div class="col-lg-3 text-center text-lg-start border-end-lg pe-lg-4">
      <div class="small fw-bold text-muted text-uppercase mb-1">
        <?= $viewTab === 'dishes' ? 'Catalog Dish Rating' : 'Order Food Rating' ?>
      </div>
      <div class="display-5 fw-bold font-outfit text-dark mb-1">
        <?= $activeTotal > 0 ? number_format($viewTab === 'dishes' ? $avgDishRating : $avgFoodRating, 1) : '0.0' ?>
      </div>
      <div class="mb-2 fs-5">
        <?= renderStarRatingHtml($viewTab === 'dishes' ? $avgDishRating : $avgFoodRating) ?>
      </div>
      <div class="small text-muted">
        Total of <strong><?= $activeTotal ?></strong> verified <?= $viewTab === 'dishes' ? 'item' : 'order' ?> rating(s)
      </div>
    </div>

    <?php if ($viewTab === 'dishes'): ?>
      <!-- Dish Star Distribution in Middle -->
      <div class="col-lg-5 ps-lg-3 pe-lg-3 border-end-lg">
        <div class="small fw-bold text-muted text-uppercase mb-2">Overall Star Distribution</div>
        <div class="d-flex flex-column gap-2">
          <?php for ($s = 5; $s >= 1; $s--): 
            $cnt = $activeDist[$s] ?? 0;
            $pct = $activeTotal > 0 ? round(($cnt / $activeTotal) * 100) : 0;
          ?>
            <div class="d-flex align-items-center gap-3">
              <a href="<?= ADMIN_URL ?>/reviews.php?tab=dishes&star=<?= $s ?>" 
                 class="text-decoration-none small fw-semibold text-dark d-flex align-items-center gap-1" 
                 style="width: 50px;" 
                 title="Filter by <?= $s ?> Stars">
                <span><?= $s ?></span> <i class="bi bi-star-fill text-warning" style="font-size:0.75rem;"></i>
              </a>

              <div class="progress flex-grow-1 rounded-pill" style="height: 8px; background-color: #E9ECEF;">
                <div class="progress-bar <?= $s >= 4 ? 'bg-success' : ($s === 3 ? 'bg-warning' : 'bg-danger') ?>" 
                     role="progressbar" 
                     style="width: <?= $pct ?>%;" 
                     aria-valuenow="<?= $pct ?>" 
                     aria-valuemin="0" 
                     aria-valuemax="100"></div>
              </div>

              <div class="text-end text-muted small" style="width: 65px; font-size:0.75rem;">
                <?= $cnt ?> (<?= $pct ?>%)
              </div>
            </div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Dish 4 Dimensions Breakdown Meter on Right -->
      <div class="col-lg-4 ps-lg-3">
        <div class="small fw-bold text-muted text-uppercase mb-2">Culinary Dimensions Averages</div>
        <div class="d-flex flex-column gap-2">
          <!-- Appearance -->
          <div>
            <div class="d-flex justify-content-between small mb-1">
              <span class="text-muted"><i class="bi bi-eye text-primary me-1"></i>Appearance</span>
              <strong class="text-dark font-monospace"><?= number_format($avgAppearance, 1) ?> / 5.0 ★</strong>
            </div>
            <div class="progress rounded-pill" style="height: 8px; background:#E9ECEF;">
              <div class="progress-bar bg-primary" role="progressbar" style="width: <?= ($avgAppearance / 5) * 100 ?>%;"></div>
            </div>
          </div>
          <!-- Taste -->
          <div>
            <div class="d-flex justify-content-between small mb-1">
              <span class="text-muted"><i class="bi bi-fire text-danger me-1"></i>Taste &amp; Flavor</span>
              <strong class="text-dark font-monospace"><?= number_format($avgTaste, 1) ?> / 5.0 ★</strong>
            </div>
            <div class="progress rounded-pill" style="height: 8px; background:#E9ECEF;">
              <div class="progress-bar bg-danger" role="progressbar" style="width: <?= ($avgTaste / 5) * 100 ?>%;"></div>
            </div>
          </div>
          <!-- Portion Size -->
          <div>
            <div class="d-flex justify-content-between small mb-1">
              <span class="text-muted"><i class="bi bi-pie-chart text-warning me-1"></i>Portion Size</span>
              <strong class="text-dark font-monospace"><?= number_format($avgPortion, 1) ?> / 5.0 ★</strong>
            </div>
            <div class="progress rounded-pill" style="height: 8px; background:#E9ECEF;">
              <div class="progress-bar bg-warning" role="progressbar" style="width: <?= ($avgPortion / 5) * 100 ?>%;"></div>
            </div>
          </div>
          <!-- Value -->
          <div>
            <div class="d-flex justify-content-between small mb-1">
              <span class="text-muted"><i class="bi bi-tag text-success me-1"></i>Value for Money</span>
              <strong class="text-dark font-monospace"><?= number_format($avgValue, 1) ?> / 5.0 ★</strong>
            </div>
            <div class="progress rounded-pill" style="height: 8px; background:#E9ECEF;">
              <div class="progress-bar bg-success" role="progressbar" style="width: <?= ($avgValue / 5) * 100 ?>%;"></div>
            </div>
          </div>
        </div>
      </div>

    <?php else: ?>
      <!-- Order Rating Distribution Bars on Right -->
      <div class="col-lg-9 ps-lg-4">
        <div class="d-flex flex-column gap-2">
          <?php for ($s = 5; $s >= 1; $s--): 
            $cnt = $activeDist[$s] ?? 0;
            $pct = $activeTotal > 0 ? round(($cnt / $activeTotal) * 100) : 0;
          ?>
            <div class="d-flex align-items-center gap-3">
              <a href="<?= ADMIN_URL ?>/reviews.php?tab=orders&star=<?= $s ?>" 
                 class="text-decoration-none small fw-semibold text-dark d-flex align-items-center gap-1" 
                 style="width: 55px;" 
                 title="Filter by <?= $s ?> Stars">
                <span><?= $s ?></span> <i class="bi bi-star-fill text-warning" style="font-size:0.75rem;"></i>
              </a>

              <div class="progress flex-grow-1 rounded-pill" style="height: 10px; background-color: #E9ECEF;">
                <div class="progress-bar <?= $s >= 4 ? 'bg-success' : ($s === 3 ? 'bg-warning' : 'bg-danger') ?>" 
                     role="progressbar" 
                     style="width: <?= $pct ?>%;" 
                     aria-valuenow="<?= $pct ?>" 
                     aria-valuemin="0" 
                     aria-valuemax="100"></div>
              </div>

              <div class="text-end text-muted small" style="width: 80px;">
                <?= $cnt ?> <span class="text-secondary">(<?= $pct ?>%)</span>
              </div>
            </div>
          <?php endfor; ?>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<!-- =========================================================================
     TABS, SEARCH BAR & MULTI-DIMENSIONAL FILTERS
     ========================================================================= -->
<div class="card border-0 rounded-4 shadow-sm p-3 mb-4 bg-white">
  
  <div class="d-flex flex-column gap-3">
    
    <!-- Top Row: Tab Navigation & Instant Search -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
      
      <!-- Tab Navigation (Shows global total counts) -->
      <ul class="nav nav-pills gap-2">
        <li class="nav-item">
          <a class="nav-link rounded-pill px-4 <?= ($viewTab === 'orders') ? 'active bg-success' : 'text-dark bg-light' ?>" 
             href="<?= ADMIN_URL ?>/reviews.php?tab=orders&star=<?= $starFilter ?>&sentiment=<?= e($sentimentFilter) ?>">
            <i class="bi bi-receipt me-1"></i> Order Reviews (<?= $totalOrderReviews ?>)
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link rounded-pill px-4 <?= ($viewTab === 'dishes') ? 'active bg-success' : 'text-dark bg-light' ?>" 
             href="<?= ADMIN_URL ?>/reviews.php?tab=dishes&star=<?= $starFilter ?>&sentiment=<?= e($sentimentFilter) ?>">
            <i class="fa-solid fa-bowl-food me-1"></i> Dish Reviews (<?= $totalDishReviews ?>)
          </a>
        </li>
      </ul>

      <!-- Live Instant Search Input -->
      <div class="position-relative flex-grow-1" style="max-width: 380px;">
        <span class="position-absolute top-50 start-0 translate-middle-y ps-3 text-muted">
          <i class="bi bi-search"></i>
        </span>
        <input type="text" 
               id="reviewLiveSearchInput" 
               class="form-control rounded-pill ps-5 pe-4 bg-light border-0 small" 
               placeholder="Instant search: order #, customer, dish, comment keywords..." 
               value="<?= e($searchQuery) ?>"
               autocomplete="off">
        <button type="button" 
                id="clearSearchBtn" 
                class="btn btn-link text-muted position-absolute top-50 end-0 translate-middle-y pe-3 p-0 <?= empty($searchQuery) ? 'd-none' : '' ?>" 
                style="text-decoration:none;" 
                title="Clear search">
          <i class="bi bi-x-circle-fill"></i>
        </button>
      </div>

    </div>

    <!-- Bottom Row: Filter Pills (Stars, Sentiment, Comments, Reset) -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top">
      
      <div class="d-flex flex-wrap align-items-center gap-1">
        <span class="small fw-bold text-muted me-2">Star Rating:</span>
        <a href="<?= ADMIN_URL ?>/reviews.php?tab=<?= $viewTab ?>&star=0&sentiment=<?= e($sentimentFilter) ?>" 
           class="btn btn-sm rounded-pill <?= $starFilter === 0 ? 'btn-dark' : 'btn-light border' ?>">
          All
        </a>
        <?php for ($s = 5; $s >= 1; $s--): ?>
          <a href="<?= ADMIN_URL ?>/reviews.php?tab=<?= $viewTab ?>&star=<?= $s ?>&sentiment=<?= e($sentimentFilter) ?>" 
             class="btn btn-sm rounded-pill <?= $starFilter === $s ? 'btn-warning text-dark fw-bold' : 'btn-light border text-muted' ?>">
            <?= $s ?> <i class="bi bi-star-fill text-warning"></i>
          </a>
        <?php endfor; ?>
      </div>

      <div class="d-flex flex-wrap align-items-center gap-1">
        <span class="small fw-bold text-muted me-2">Filter By:</span>
        
        <!-- Critical Sentiment Pill -->
        <a href="<?= ADMIN_URL ?>/reviews.php?tab=<?= $viewTab ?>&star=<?= $starFilter ?>&sentiment=<?= $sentimentFilter === 'critical' ? 'all' : 'critical' ?>" 
           class="btn btn-sm rounded-pill <?= $sentimentFilter === 'critical' ? 'btn-danger' : 'btn-light border text-muted' ?>">
          <i class="bi bi-exclamation-triangle me-1"></i> Critical (&le; 2★)
        </a>

        <!-- Positive Sentiment Pill -->
        <a href="<?= ADMIN_URL ?>/reviews.php?tab=<?= $viewTab ?>&star=<?= $starFilter ?>&sentiment=<?= $sentimentFilter === 'positive' ? 'all' : 'positive' ?>" 
           class="btn btn-sm rounded-pill <?= $sentimentFilter === 'positive' ? 'btn-success' : 'btn-light border text-muted' ?>">
          <i class="bi bi-hand-thumbs-up me-1"></i> Positive (4-5★)
        </a>

        <!-- With Written Comments Pill -->
        <a href="<?= ADMIN_URL ?>/reviews.php?tab=<?= $viewTab ?>&star=<?= $starFilter ?>&sentiment=<?= $sentimentFilter === 'with_comments' ? 'all' : 'with_comments' ?>" 
           class="btn btn-sm rounded-pill <?= $sentimentFilter === 'with_comments' ? 'btn-primary' : 'btn-light border text-muted' ?>">
          <i class="bi bi-chat-left-text me-1"></i> Has Written Text
        </a>

        <?php if ($hasActiveFilters): ?>
          <a href="<?= ADMIN_URL ?>/reviews.php?tab=<?= $viewTab ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3 ms-2">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filters
          </a>
        <?php endif; ?>
      </div>

    </div>

  </div>
</div>

<!-- =========================================================================
     REVIEWS LIST TABLE
     ========================================================================= -->
<div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden">
  
  <?php if ($viewTab === 'orders'): ?>
    <!-- =====================================================================
         ORDER REVIEWS TABLE
         ===================================================================== -->
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" id="orderReviewsTable">
        <thead class="table-light small text-uppercase fw-semibold text-muted">
          <tr>
            <th class="ps-4" style="min-width: 220px;">Order & Line Items</th>
            <th style="min-width: 170px;">Customer</th>
            <th style="min-width: 140px;">Food Rating</th>
            <th style="min-width: 180px;">Rider & Delivery</th>
            <th style="min-width: 280px;">Customer Feedback</th>
            <th style="min-width: 120px;">Date</th>
            <th class="text-end pe-4" style="min-width: 130px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($orderReviewsList)): ?>
            <?php foreach ($orderReviewsList as $rev): 
              $foodRate = (int)$rev['food_rating'];
              $riderRate = (int)$rev['rider_rating'];
              $searchKeywords = strtolower("ZIP-{$rev['order_id']} {$rev['customer_name']} {$rev['customer_email']} {$rev['customer_phone']} {$rev['delivery_partner_name']} {$rev['comments']} {$rev['items_summary']}");
            ?>
              <tr class="review-row" data-search="<?= e($searchKeywords) ?>">
                <!-- Order ID & Line Items -->
                <td class="ps-4">
                  <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark font-monospace">#ZIP-<?= (int)$rev['order_id'] ?></span>
                    <span class="badge bg-light text-forest border fw-bold"><?= formatInr($rev['total_amount'] ?? 0) ?></span>
                  </div>
                  <?php if (!empty($rev['items_summary'])): ?>
                    <div class="small text-muted text-truncate mt-1" style="max-width: 240px;" title="<?= e($rev['items_summary']) ?>">
                      <i class="bi bi-bag-check me-1 text-secondary"></i><?= e($rev['items_summary']) ?>
                    </div>
                  <?php endif; ?>
                </td>

                <!-- Customer Info -->
                <td>
                  <div class="fw-semibold text-dark small"><?= e($rev['customer_name']) ?></div>
                  <div class="text-muted small" style="font-size:0.75rem;"><?= e($rev['customer_email']) ?></div>
                  <?php if (!empty($rev['customer_phone'])): ?>
                    <div class="text-muted small" style="font-size:0.75rem;"><?= e($rev['customer_phone']) ?></div>
                  <?php endif; ?>
                </td>

                <!-- Food Rating (Icon-based) -->
                <td>
                  <div><?= renderStarRatingHtml($foodRate) ?></div>
                  <span class="badge <?= $foodRate >= 4 ? 'bg-success bg-opacity-25 text-success' : ($foodRate === 3 ? 'bg-warning bg-opacity-25 text-dark' : 'bg-danger bg-opacity-25 text-danger') ?> fw-bold mt-1" style="font-size:0.7rem;">
                    <?= $foodRate ?> / 5.0 Food
                  </span>
                </td>

                <!-- Rider & Tip -->
                <td>
                  <div class="small fw-semibold text-dark d-flex align-items-center gap-1">
                    <i class="bi bi-bicycle text-forest"></i> <?= e($rev['delivery_partner_name']) ?>
                  </div>
                  <div class="d-flex align-items-center gap-1 small text-warning mt-1">
                    <?= renderStarRatingHtml($riderRate) ?>
                    <span class="text-muted ms-1" style="font-size:0.75rem;">(<?= $riderRate ?>/5)</span>
                  </div>
                  <?php if ((float)$rev['rider_tip'] > 0): ?>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fw-bold px-2 py-0 mt-1" style="font-size:0.7rem;">
                      <i class="bi bi-heart-fill me-1 text-danger"></i> <?= formatInr($rev['rider_tip']) ?> Tip
                    </span>
                  <?php endif; ?>
                </td>

                <!-- Comments & Feedback -->
                <td>
                  <?php if (!empty($rev['comments'])): ?>
                    <div class="p-2 rounded-3 bg-light bg-opacity-50 border small text-dark fst-italic">
                      <i class="bi bi-quote text-muted me-1"></i>"<?= e($rev['comments']) ?>"
                    </div>
                  <?php else: ?>
                    <span class="text-muted small fst-italic"><i class="bi bi-dash me-1"></i>No written comments provided</span>
                  <?php endif; ?>
                </td>

                <!-- Date -->
                <td class="small text-muted">
                  <div><?= date('d M Y', strtotime($rev['created_at'])) ?></div>
                  <div style="font-size: 0.72rem;"><?= date('h:i A', strtotime($rev['created_at'])) ?></div>
                </td>

                <!-- Actions -->
                <td class="text-end pe-4">
                  <div class="d-flex align-items-center justify-content-end gap-1">
                    <!-- Inspect Order Modal Trigger -->
                    <button type="button" 
                            class="btn btn-sm btn-outline-forest rounded-pill px-2 py-1" 
                            style="font-size:0.75rem;" 
                            onclick="openReviewInspectModal(<?= htmlspecialchars(json_encode([
                              'order_id' => $rev['order_id'],
                              'customer_name' => $rev['customer_name'],
                              'customer_email' => $rev['customer_email'],
                              'customer_phone' => $rev['customer_phone'] ?? '',
                              'total_amount' => formatInr($rev['total_amount'] ?? 0),
                              'items_summary' => $rev['items_summary'] ?? 'N/A',
                              'food_rating' => $rev['food_rating'],
                              'rider_rating' => $rev['rider_rating'],
                              'delivery_partner' => $rev['delivery_partner_name'],
                              'rider_tip' => formatInr($rev['rider_tip'] ?? 0),
                              'comments' => $rev['comments'] ?: 'No comments provided',
                              'created_at' => date('d M Y, h:i A', strtotime($rev['created_at']))
                            ]), ENT_QUOTES, 'UTF-8') ?>)"
                            title="Inspect complete order details">
                      <i class="bi bi-eye"></i> Details
                    </button>

                    <!-- Link to Orders console -->
                    <a href="<?= ADMIN_URL ?>/orders.php?search=ZIP-<?= (int)$rev['order_id'] ?>" 
                       class="btn btn-sm btn-light border rounded-pill px-2 py-1 text-muted" 
                       style="font-size:0.75rem;" 
                       target="_blank" 
                       title="Open Order in Live Orders Hub">
                      <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr id="emptyStaticRow">
              <td colspan="7" class="text-center py-5 text-muted">
                <i class="bi bi-chat-quote fs-1 d-block mb-2 text-secondary opacity-50"></i>
                <h6 class="fw-bold">No order reviews match this criteria</h6>
                <p class="small text-muted mb-0">Try clearing active filters or selecting another star rating.</p>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  <?php else: ?>
    <!-- =====================================================================
         DISH REVIEWS TABLE
         ===================================================================== -->
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" id="dishReviewsTable">
        <thead class="table-light small text-uppercase fw-semibold text-muted">
          <tr>
            <th class="ps-4" style="min-width: 240px;">Dish &amp; Category</th>
            <th style="min-width: 160px;">Customer</th>
            <th style="min-width: 130px;">Overall Rating</th>
            <th style="min-width: 220px;">Dish Feedback (4 Criteria)</th>
            <th style="min-width: 170px;">Customer Attribute Tags</th>
            <th style="min-width: 240px;">Review Comments</th>
            <th style="min-width: 110px;">Date</th>
            <th class="text-end pe-4" style="min-width: 150px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($dishReviewsList)): ?>
            <?php foreach ($dishReviewsList as $dr): 
              $rating = (int)$dr['rating'];
              $appRate = (int)($dr['appearance_rating'] ?? $rating);
              $tasteRate = (int)($dr['taste_rating'] ?? $rating);
              $portionRate = (int)($dr['portion_rating'] ?? $rating);
              $valueRate = (int)($dr['value_rating'] ?? $rating);
              $searchKeywords = strtolower("{$dr['item_name']} {$dr['customer_name']} {$dr['customer_email']} {$dr['review_text']} {$dr['tags']} ZIP-{$dr['order_id']} appearance taste portion value");
            ?>
              <tr class="review-row" data-search="<?= e($searchKeywords) ?>">
                <!-- Dish Info -->
                <td class="ps-4">
                  <div class="d-flex align-items-center gap-3">
                    <img src="<?= e(getDishImageUrl($dr['image_url'])) ?>" 
                         alt="<?= e($dr['item_name']) ?>" 
                         class="rounded-3 object-fit-cover shadow-xs border" 
                         style="width: 48px; height: 48px;">
                    <div>
                      <div class="fw-bold text-dark small mb-1"><?= e($dr['item_name']) ?></div>
                      <div class="d-flex align-items-center gap-1">
                        <span class="badge <?= $dr['is_veg'] ? 'bg-success' : 'bg-danger' ?> px-1 py-0" style="font-size:0.6rem;">
                          <?= $dr['is_veg'] ? 'VEG' : 'NON-VEG' ?>
                        </span>
                        <span class="badge bg-light text-muted border px-1 py-0" style="font-size:0.6rem;">
                          <?= e($dr['category_name'] ?? 'Menu') ?>
                        </span>
                        <span class="small text-forest fw-bold ms-1" style="font-size:0.75rem;"><?= formatInr($dr['price']) ?></span>
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Customer & Source Order -->
                <td>
                  <div class="fw-semibold text-dark small"><?= e($dr['customer_name']) ?></div>
                  <div class="text-muted" style="font-size: 0.75rem;"><?= e($dr['customer_email']) ?></div>
                  <?php if (!empty($dr['order_id'])): ?>
                    <a href="<?= ADMIN_URL ?>/orders.php?search=ZIP-<?= (int)$dr['order_id'] ?>" 
                       class="badge bg-light text-secondary border text-decoration-none mt-1" 
                       target="_blank" 
                       style="font-size:0.68rem;">
                      Order #ZIP-<?= (int)$dr['order_id'] ?> <i class="bi bi-box-arrow-up-right ms-1"></i>
                    </a>
                  <?php endif; ?>
                </td>

                <!-- Overall Star Rating -->
                <td>
                  <div><?= renderStarRatingHtml($rating) ?></div>
                  <span class="badge <?= $rating >= 4 ? 'bg-success bg-opacity-25 text-success' : ($rating === 3 ? 'bg-warning bg-opacity-25 text-dark' : 'bg-danger bg-opacity-25 text-danger') ?> fw-bold mt-1" style="font-size:0.7rem;">
                    <?= $rating ?> / 5.0 Stars
                  </span>
                </td>

                <!-- Dish-Level Feedback (4 Dimensions: Appearance, Taste, Portion Size, Value) -->
                <td>
                  <div class="d-flex flex-column gap-1" style="min-width: 200px;">
                    <!-- Appearance -->
                    <div class="d-flex align-items-center justify-content-between small">
                      <span class="text-muted" style="font-size:0.75rem;"><i class="bi bi-eye text-primary me-1"></i>Appearance</span>
                      <span class="badge bg-light text-dark border font-monospace py-0 px-2" style="font-size:0.72rem;"><?= $appRate ?> / 5 ★</span>
                    </div>
                    <!-- Taste -->
                    <div class="d-flex align-items-center justify-content-between small">
                      <span class="text-muted" style="font-size:0.75rem;"><i class="bi bi-fire text-danger me-1"></i>Taste</span>
                      <span class="badge bg-light text-dark border font-monospace py-0 px-2" style="font-size:0.72rem;"><?= $tasteRate ?> / 5 ★</span>
                    </div>
                    <!-- Portion Size -->
                    <div class="d-flex align-items-center justify-content-between small">
                      <span class="text-muted" style="font-size:0.75rem;"><i class="bi bi-pie-chart text-warning me-1"></i>Portion</span>
                      <span class="badge bg-light text-dark border font-monospace py-0 px-2" style="font-size:0.72rem;"><?= $portionRate ?> / 5 ★</span>
                    </div>
                    <!-- Value -->
                    <div class="d-flex align-items-center justify-content-between small">
                      <span class="text-muted" style="font-size:0.75rem;"><i class="bi bi-tag text-success me-1"></i>Value</span>
                      <span class="badge bg-light text-dark border font-monospace py-0 px-2" style="font-size:0.72rem;"><?= $valueRate ?> / 5 ★</span>
                    </div>
                  </div>
                </td>

                <!-- Tags -->
                <td>
                  <?php if (!empty($dr['tags'])): ?>
                    <div class="d-flex flex-wrap gap-1">
                      <?php foreach (explode(',', $dr['tags']) as $tag): ?>
                        <span class="badge bg-light text-forest border px-2 py-1" style="font-size:0.68rem;">
                          <?= e(trim($tag)) ?>
                        </span>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <span class="text-muted small">&mdash;</span>
                  <?php endif; ?>
                </td>

                <!-- Review Text (Real Customer Comment) -->
                <td>
                  <?php if (!empty($dr['review_text'])): ?>
                    <div class="p-2 rounded-3 bg-light bg-opacity-50 border small text-dark fst-italic">
                      <i class="bi bi-quote text-muted me-1"></i>"<?= e($dr['review_text']) ?>"
                    </div>
                  <?php else: ?>
                    <span class="text-muted small fst-italic"><i class="bi bi-dash me-1"></i>No written comment provided</span>
                  <?php endif; ?>
                </td>

                <!-- Date -->
                <td class="small text-muted">
                  <?= date('d M Y', strtotime($dr['created_at'])) ?>
                </td>

                <!-- Actions -->
                <td class="text-end pe-4">
                  <div class="d-flex align-items-center justify-content-end gap-1">
                    <!-- Inspect Dish Feedback Modal Trigger -->
                    <button type="button" 
                            class="btn btn-sm btn-outline-forest rounded-pill px-2 py-1" 
                            style="font-size:0.75rem;" 
                            onclick="openDishReviewInspectModal(<?= htmlspecialchars(json_encode([
                              'review_id' => $dr['dish_review_id'],
                              'item_id' => $dr['item_id'],
                              'item_name' => $dr['item_name'],
                              'category_name' => $dr['category_name'] ?? 'General',
                              'image_url' => getDishImageUrl($dr['image_url']),
                              'price' => formatInr($dr['price']),
                              'is_veg' => (bool)$dr['is_veg'],
                              'customer_name' => $dr['customer_name'],
                              'customer_email' => $dr['customer_email'],
                              'customer_phone' => $dr['customer_phone'] ?? '',
                              'order_id' => $dr['order_id'],
                              'rating' => (int)$dr['rating'],
                              'appearance_rating' => $appRate,
                              'taste_rating' => $tasteRate,
                              'portion_rating' => $portionRate,
                              'value_rating' => $valueRate,
                              'tags' => $dr['tags'] ?? '',
                              'review_text' => $dr['review_text'] ?: 'No comments provided',
                              'created_at' => date('d M Y, h:i A', strtotime($dr['created_at']))
                            ]), ENT_QUOTES, 'UTF-8') ?>)"
                            title="Inspect full dish feedback breakdown">
                      <i class="bi bi-eye"></i> Details
                    </button>

                    <!-- Jump to Dishes Console -->
                    <a href="<?= ADMIN_URL ?>/dishes.php?search=<?= urlencode($dr['item_name']) ?>" 
                       class="btn btn-sm btn-light border rounded-pill px-2 py-1 text-muted" 
                       style="font-size:0.75rem;" 
                       target="_blank" 
                       title="Manage this dish in Dishes & Inventory">
                      <i class="fa-solid fa-bowl-food"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr id="emptyStaticRow">
              <td colspan="7" class="text-center py-5 text-muted">
                <i class="bi bi-star fs-1 d-block mb-2 text-secondary opacity-50"></i>
                <h6 class="fw-bold">No dish reviews match this criteria</h6>
                <p class="small text-muted mb-0">Try clearing active filters or selecting another star rating.</p>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <!-- Live Client Search Zero Results Placeholder -->
  <div id="liveSearchZeroRow" class="text-center py-5 d-none">
    <i class="bi bi-search fs-1 text-muted opacity-50 d-block mb-2"></i>
    <h6 class="fw-bold text-dark">No reviews match your keyword search</h6>
    <p class="small text-muted mb-3">Try searching with a different keyword, customer name, order number, or dish.</p>
    <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-3" onclick="resetLiveSearch()">
      Clear Search
    </button>
  </div>

</div>

<!-- =========================================================================
     ORDER DETAILS INSPECTION MODAL
     ========================================================================= -->
<div class="modal fade" id="reviewInspectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden text-start">
      
      <!-- Modal Header -->
      <div class="p-3 px-4 bg-dark text-white d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-success rounded-pill px-3 py-1">ORDER REVIEW HUD</span>
          <span class="fw-bold font-monospace" id="modalOrderId">#ZIP-0000</span>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body p-4 bg-light">
        <div class="row g-3">
          
          <!-- Customer & Order Meta -->
          <div class="col-md-6">
            <div class="card border-0 rounded-4 p-3 bg-white shadow-xs h-100">
              <div class="small fw-bold text-muted text-uppercase mb-2">Customer & Billing Details</div>
              <div class="fw-bold text-dark fs-6" id="modalCustName">Customer Name</div>
              <div class="small text-muted" id="modalCustEmail">email@example.com</div>
              <div class="small text-muted mb-2" id="modalCustPhone">9876543210</div>
              <div class="d-flex align-items-center gap-2 mt-auto pt-2 border-top">
                <span class="small text-muted">Order Total:</span>
                <span class="fw-bold text-forest" id="modalOrderTotal">₹0.00</span>
                <span class="small text-muted ms-auto" id="modalOrderDate">Date</span>
              </div>
            </div>
          </div>

          <!-- Rider & Delivery Meta -->
          <div class="col-md-6">
            <div class="card border-0 rounded-4 p-3 bg-white shadow-xs h-100">
              <div class="small fw-bold text-muted text-uppercase mb-2">Fulfillment & Courier</div>
              <div class="fw-bold text-dark fs-6" id="modalRiderName">Rider Name</div>
              <div class="small text-muted mb-2">Delivery Partner</div>
              <div class="d-flex align-items-center justify-content-between border-top pt-2 mt-auto">
                <div>
                  <span class="small text-muted d-block">Rider Rating</span>
                  <span class="badge bg-warning bg-opacity-25 text-dark fw-bold" id="modalRiderRating">5 / 5 ★</span>
                </div>
                <div>
                  <span class="small text-muted d-block">Rider Tip</span>
                  <span class="badge bg-success bg-opacity-25 text-success fw-bold" id="modalRiderTip">₹0.00</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Ordered Dishes Summary -->
          <div class="col-12">
            <div class="card border-0 rounded-4 p-3 bg-white shadow-xs">
              <div class="small fw-bold text-muted text-uppercase mb-1">Ordered Dishes in this Basket</div>
              <div class="small text-dark font-monospace p-2 rounded-3 bg-light border" id="modalItemsSummary">
                Loading line items...
              </div>
            </div>
          </div>

          <!-- Customer Comments Box -->
          <div class="col-12">
            <div class="card border-0 rounded-4 p-3 bg-white shadow-xs">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-bold text-muted text-uppercase">Customer Food Rating & Feedback</span>
                <span class="badge bg-warning bg-opacity-25 text-dark fw-bold" id="modalFoodRating">5 / 5 Food ★</span>
              </div>
              <div class="p-3 rounded-3 bg-light border small text-dark fst-italic" id="modalComments">
                "Customer comments will appear here."
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Modal Footer -->
      <div class="p-3 px-4 bg-white border-top d-flex align-items-center justify-content-between">
        <a id="modalManageOrderBtn" href="#" class="btn btn-outline-success btn-sm rounded-pill px-3" target="_blank">
          <i class="bi bi-box-arrow-up-right me-1"></i> Open Order in Orders Console
        </a>
        <button type="button" class="btn btn-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal">
          Close
        </button>
      </div>

    </div>
  </div>
</div>

<!-- =========================================================================
     DISH FEEDBACK INSPECTION MODAL (Appearance, Taste, Portion, Value)
     ========================================================================= -->
<div class="modal fade" id="dishReviewInspectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden text-start">
      
      <!-- Modal Header -->
      <div class="p-3 px-4 bg-dark text-white d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-success rounded-pill px-3 py-1">DISH FEEDBACK HUD</span>
          <span class="fw-bold font-outfit" id="dishModalItemName">Dish Name</span>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body p-4 bg-light">
        <div class="row g-3">
          
          <!-- Dish Meta & Customer Info -->
          <div class="col-md-5">
            <div class="card border-0 rounded-4 p-3 bg-white shadow-xs h-100">
              <div class="d-flex align-items-center gap-3 mb-3">
                <img id="dishModalImg" src="" alt="Dish" class="rounded-3 object-fit-cover shadow-xs border" style="width: 58px; height: 58px;">
                <div>
                  <div class="fw-bold text-dark fs-6" id="dishModalTitle">Dish Title</div>
                  <div class="d-flex align-items-center gap-1 mt-1">
                    <span class="badge bg-light text-muted border" id="dishModalCategory">Category</span>
                    <span class="badge bg-success" id="dishModalDiet">VEG</span>
                    <span class="small text-forest fw-bold ms-1" id="dishModalPrice">₹0.00</span>
                  </div>
                </div>
              </div>

              <div class="small fw-bold text-muted text-uppercase mb-1 pt-2 border-top">Customer Info</div>
              <div class="fw-semibold text-dark small" id="dishModalCustName">Customer</div>
              <div class="text-muted small" id="dishModalCustEmail">email@example.com</div>
              <div class="text-muted small mb-2" id="dishModalCustPhone">Phone</div>

              <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center small">
                <span class="text-muted" id="dishModalDate">Date</span>
                <span class="badge bg-light text-secondary border" id="dishModalOrderBadge">Catalog Rating</span>
              </div>
            </div>
          </div>

          <!-- 4-Dimension Sensory Scores -->
          <div class="col-md-7">
            <div class="card border-0 rounded-4 p-3 bg-white shadow-xs h-100">
              <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                <span class="small fw-bold text-muted text-uppercase">Culinary Feedback Dimensions</span>
                <span class="badge bg-warning bg-opacity-25 text-dark fw-bold fs-6" id="dishModalOverallRating">5.0 / 5 ★</span>
              </div>

              <div class="d-flex flex-column gap-3">
                <!-- Appearance -->
                <div>
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="text-dark fw-semibold"><i class="bi bi-eye text-primary me-1"></i>Appearance (Plating &amp; Color)</span>
                    <strong class="font-monospace text-primary" id="dishModalAppVal">5 / 5 ★</strong>
                  </div>
                  <div class="progress rounded-pill" style="height: 8px; background:#E9ECEF;">
                    <div class="progress-bar bg-primary" id="dishModalAppBar" role="progressbar" style="width: 100%;"></div>
                  </div>
                </div>

                <!-- Taste -->
                <div>
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="text-dark fw-semibold"><i class="bi bi-fire text-danger me-1"></i>Taste &amp; Flavor (Seasoning)</span>
                    <strong class="font-monospace text-danger" id="dishModalTasteVal">5 / 5 ★</strong>
                  </div>
                  <div class="progress rounded-pill" style="height: 8px; background:#E9ECEF;">
                    <div class="progress-bar bg-danger" id="dishModalTasteBar" role="progressbar" style="width: 100%;"></div>
                  </div>
                </div>

                <!-- Portion Size -->
                <div>
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="text-dark fw-semibold"><i class="bi bi-pie-chart text-warning me-1"></i>Portion Size (Adequacy)</span>
                    <strong class="font-monospace text-warning" id="dishModalPortionVal">5 / 5 ★</strong>
                  </div>
                  <div class="progress rounded-pill" style="height: 8px; background:#E9ECEF;">
                    <div class="progress-bar bg-warning" id="dishModalPortionBar" role="progressbar" style="width: 100%;"></div>
                  </div>
                </div>

                <!-- Value -->
                <div>
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="text-dark fw-semibold"><i class="bi bi-tag text-success me-1"></i>Value for Money</span>
                    <strong class="font-monospace text-success" id="dishModalValueVal">5 / 5 ★</strong>
                  </div>
                  <div class="progress rounded-pill" style="height: 8px; background:#E9ECEF;">
                    <div class="progress-bar bg-success" id="dishModalValueBar" role="progressbar" style="width: 100%;"></div>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- Tags & Review Text -->
          <div class="col-12">
            <div class="card border-0 rounded-4 p-3 bg-white shadow-xs">
              <div class="small fw-bold text-muted text-uppercase mb-2">Customer Feedback &amp; Tags</div>
              <div class="d-flex flex-wrap gap-1 mb-2" id="dishModalTags">
                <!-- Tags populated via JS -->
              </div>
              <div class="p-3 rounded-3 bg-light border small text-dark fst-italic" id="dishModalReviewText">
                "Review comments here."
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Modal Footer -->
      <div class="p-3 px-4 bg-white border-top d-flex align-items-center justify-content-between">
        <a id="dishModalManageDishBtn" href="#" class="btn btn-outline-forest btn-sm rounded-pill px-3" target="_blank">
          <i class="fa-solid fa-bowl-food me-1"></i> View in Dishes &amp; Inventory
        </a>
        <button type="button" class="btn btn-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal">
          Close
        </button>
      </div>

    </div>
  </div>
</div>

<!-- =========================================================================
     CLIENT INTERACTIVE SCRIPTS: LIVE SEARCH & MODAL CONTROLLER
     ========================================================================= -->
<script>
  // Open and populate Dish Details Inspection Modal
  function openDishReviewInspectModal(data) {
    document.getElementById('dishModalItemName').textContent = data.item_name;
    document.getElementById('dishModalTitle').textContent = data.item_name;
    document.getElementById('dishModalImg').src = data.image_url;
    document.getElementById('dishModalCategory').textContent = data.category_name;
    
    const dietBadge = document.getElementById('dishModalDiet');
    if (dietBadge) {
      dietBadge.textContent = data.is_veg ? 'VEG' : 'NON-VEG';
      dietBadge.className = data.is_veg ? 'badge bg-success' : 'badge bg-danger';
    }
    document.getElementById('dishModalPrice').textContent = data.price;
    document.getElementById('dishModalCustName').textContent = data.customer_name;
    document.getElementById('dishModalCustEmail').textContent = data.customer_email;
    document.getElementById('dishModalCustPhone').textContent = data.customer_phone || 'Phone not registered';
    document.getElementById('dishModalDate').textContent = data.created_at;

    const orderBadge = document.getElementById('dishModalOrderBadge');
    if (orderBadge) {
      orderBadge.textContent = data.order_id ? 'Order #ZIP-' + data.order_id : 'Direct Menu Feedback';
    }

    document.getElementById('dishModalOverallRating').textContent = data.rating + ' / 5.0 ★';
    
    // Dimension values & bars
    const appRate = Number(data.appearance_rating || data.rating);
    const tasteRate = Number(data.taste_rating || data.rating);
    const portRate = Number(data.portion_rating || data.rating);
    const valRate = Number(data.value_rating || data.rating);

    document.getElementById('dishModalAppVal').textContent = appRate + ' / 5 ★';
    document.getElementById('dishModalAppBar').style.width = ((appRate / 5) * 100) + '%';

    document.getElementById('dishModalTasteVal').textContent = tasteRate + ' / 5 ★';
    document.getElementById('dishModalTasteBar').style.width = ((tasteRate / 5) * 100) + '%';

    document.getElementById('dishModalPortionVal').textContent = portRate + ' / 5 ★';
    document.getElementById('dishModalPortionBar').style.width = ((portRate / 5) * 100) + '%';

    document.getElementById('dishModalValueVal').textContent = valRate + ' / 5 ★';
    document.getElementById('dishModalValueBar').style.width = ((valRate / 5) * 100) + '%';

    // Tags
    const tagsBox = document.getElementById('dishModalTags');
    if (tagsBox) {
      if (data.tags) {
        const tagArr = data.tags.split(',');
        tagsBox.innerHTML = tagArr.map(t => `<span class="badge bg-light text-forest border px-2 py-1">${t.trim()}</span>`).join(' ');
      } else {
        tagsBox.innerHTML = '<span class="text-muted small fst-italic">No tags attached</span>';
      }
    }

    document.getElementById('dishModalReviewText').textContent = '"' + data.review_text + '"';

    const manageBtn = document.getElementById('dishModalManageDishBtn');
    if (manageBtn) {
      manageBtn.href = '<?= ADMIN_URL ?>/dishes.php?search=' + encodeURIComponent(data.item_name);
    }

    const modalEl = document.getElementById('dishReviewInspectModal');
    if (modalEl) {
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }
  }
  // Open and populate Order Details Inspection Modal
  function openReviewInspectModal(data) {
    document.getElementById('modalOrderId').textContent = '#ZIP-' + data.order_id;
    document.getElementById('modalCustName').textContent = data.customer_name;
    document.getElementById('modalCustEmail').textContent = data.customer_email;
    document.getElementById('modalCustPhone').textContent = data.customer_phone || 'Phone not registered';
    document.getElementById('modalOrderTotal').textContent = data.total_amount;
    document.getElementById('modalOrderDate').textContent = data.created_at;
    document.getElementById('modalRiderName').textContent = data.delivery_partner;
    document.getElementById('modalRiderRating').textContent = data.rider_rating + ' / 5.0 Rider ★';
    document.getElementById('modalRiderTip').textContent = data.rider_tip;
    document.getElementById('modalItemsSummary').textContent = data.items_summary;
    document.getElementById('modalFoodRating').textContent = data.food_rating + ' / 5.0 Food ★';
    document.getElementById('modalComments').textContent = '"' + data.comments + '"';

    const manageBtn = document.getElementById('modalManageOrderBtn');
    if (manageBtn) {
      manageBtn.href = '<?= ADMIN_URL ?>/orders.php?search=ZIP-' + data.order_id;
    }

    const modalEl = document.getElementById('reviewInspectModal');
    if (modalEl) {
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }
  }

  // Client-Side Zero-Latency Live Search Controller
  const liveSearchInput = document.getElementById('reviewLiveSearchInput');
  const clearSearchBtn = document.getElementById('clearSearchBtn');
  const zeroResultsRow = document.getElementById('liveSearchZeroRow');
  const reviewRows = document.querySelectorAll('.review-row');
  const emptyStaticRow = document.getElementById('emptyStaticRow');

  function filterReviewRows() {
    const query = (liveSearchInput?.value || '').trim().toLowerCase();
    
    // Toggle clear button
    if (clearSearchBtn) {
      if (query.length > 0) {
        clearSearchBtn.classList.remove('d-none');
      } else {
        clearSearchBtn.classList.add('d-none');
      }
    }

    let visibleCount = 0;
    reviewRows.forEach(row => {
      const searchData = row.getAttribute('data-search') || '';
      if (query === '' || searchData.includes(query)) {
        row.classList.remove('d-none');
        visibleCount++;
      } else {
        row.classList.add('d-none');
      }
    });

    if (zeroResultsRow) {
      if (visibleCount === 0 && reviewRows.length > 0) {
        zeroResultsRow.classList.remove('d-none');
      } else {
        zeroResultsRow.classList.add('d-none');
      }
    }
  }

  function resetLiveSearch() {
    if (liveSearchInput) {
      liveSearchInput.value = '';
      filterReviewRows();
      liveSearchInput.focus();
    }
  }

  if (liveSearchInput) {
    liveSearchInput.addEventListener('input', filterReviewRows);
  }

  if (clearSearchBtn) {
    clearSearchBtn.addEventListener('click', resetLiveSearch);
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
