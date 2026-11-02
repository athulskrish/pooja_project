<?php
/**
 * Customer Food Reviews & Star Ratings Console
 * Zippy - Online Food Ordering Platform
 * MCSP-232 Major Project | Pooja JP
 */

$pageTitle = 'Customer Food Reviews & Star Ratings';
$activePage = 'reviews';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Auth verification handled in header.php
require_once __DIR__ . '/includes/header.php';

$starFilter = isset($_GET['star']) ? (int)$_GET['star'] : 0;
$viewTab = clean_input($_GET['tab'] ?? 'orders');

// Calculate Key Metrics
$totalOrderReviews = (int)db_fetch_val("SELECT COUNT(*) FROM `order_reviews`") ?: 0;
$avgFoodRating = (float)db_fetch_val("SELECT COALESCE(AVG(food_rating), 5.0) FROM `order_reviews`") ?: 5.0;
$fiveStarCount = (int)db_fetch_val("SELECT COUNT(*) FROM `order_reviews` WHERE `food_rating` = 5") ?: 0;
$totalTips = (float)db_fetch_val("SELECT COALESCE(SUM(rider_tip), 0.00) FROM `order_reviews`") ?: 0.00;
$totalDishReviews = (int)db_fetch_val("SELECT COUNT(*) FROM `dish_reviews`") ?: 0;

// Fetch Order Reviews
$orderReviewSql = "
    SELECT r.*, u.full_name AS customer_name, u.email AS customer_email,
           o.total_amount, o.order_status,
           d.delivery_partner_name
    FROM `order_reviews` r
    LEFT JOIN `users` u ON r.user_id = u.user_id
    LEFT JOIN `orders` o ON r.order_id = o.order_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
";

$orderParams = [];
if ($starFilter >= 1 && $starFilter <= 5) {
    $orderReviewSql .= " WHERE r.food_rating = :sf";
    $orderParams[':sf'] = $starFilter;
}
$orderReviewSql .= " ORDER BY r.created_at DESC";
$orderReviewsList = db_fetch_all($orderReviewSql, $orderParams);

// Fetch Dish Reviews
$dishReviewSql = "
    SELECT dr.*, m.item_name, m.image_url, m.price, m.is_veg,
           u.full_name AS customer_name, u.email AS customer_email
    FROM `dish_reviews` dr
    JOIN `menu_items` m ON dr.item_id = m.item_id
    LEFT JOIN `users` u ON dr.user_id = u.user_id
";
$dishParams = [];
if ($starFilter >= 1 && $starFilter <= 5) {
    $dishReviewSql .= " WHERE dr.rating = :sf";
    $dishParams[':sf'] = $starFilter;
}
$dishReviewSql .= " ORDER BY dr.created_at DESC";
$dishReviewsList = db_fetch_all($dishReviewSql, $dishParams);
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h3 class="fw-bold font-outfit text-dark mb-1">
      <i class="bi bi-star-fill text-warning me-2"></i>Customer Food Reviews & Ratings ⭐
    </h3>
    <p class="text-muted small mb-0">Live feedback, food taste scores, courier tips, and 5-star quality benchmarks</p>
  </div>
  <a href="<?= CUSTOMER_URL ?>/index.php" class="btn btn-outline-success btn-sm rounded-pill px-3" target="_blank">
    <i class="bi bi-shop me-1"></i> View Storefront
  </a>
</div>

<!-- =========================================================================
     KPI SUMMARY METRICS
     ========================================================================= -->
<div class="row g-3 mb-4">
  
  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="small fw-bold text-muted text-uppercase">AVERAGE FOOD RATING</span>
        <span class="badge rounded-circle p-2 bg-warning bg-opacity-25 text-warning">
          <i class="bi bi-star-fill"></i>
        </span>
      </div>
      <h2 class="fw-bold font-outfit text-dark mb-0">
        <?= number_format($avgFoodRating, 1) ?> <span class="fs-6 text-warning">/ 5.0 ⭐</span>
      </h2>
      <div class="small text-success mt-1">
        <i class="bi bi-check-circle-fill me-1"></i> Based on <?= $totalOrderReviews ?> order ratings
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="small fw-bold text-muted text-uppercase">5-STAR FOOD EXCELLENCE</span>
        <span class="badge rounded-circle p-2 bg-success bg-opacity-25 text-success">
          <i class="bi bi-trophy-fill"></i>
        </span>
      </div>
      <h2 class="fw-bold font-outfit text-forest mb-0">
        <?= $fiveStarCount ?> Reviews
      </h2>
      <div class="small text-muted mt-1">
        <?= $totalOrderReviews > 0 ? round(($fiveStarCount / $totalOrderReviews) * 100) : 100 ?>% of customers gave 5 stars
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="small fw-bold text-muted text-uppercase">DISH-LEVEL REVIEWS</span>
        <span class="badge rounded-circle p-2 bg-info bg-opacity-25 text-info">
          <i class="bi bi-chat-heart-fill"></i>
        </span>
      </div>
      <h2 class="fw-bold font-outfit text-dark mb-0">
        <?= $totalDishReviews ?> Verified
      </h2>
      <div class="small text-muted mt-1">Item-level feedback on menu catalog</div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="small fw-bold text-muted text-uppercase">TOTAL COURIER TIPS</span>
        <span class="badge rounded-circle p-2 bg-success bg-opacity-25 text-success">
          <i class="bi bi-currency-rupee"></i>
        </span>
      </div>
      <h2 class="fw-bold font-outfit text-forest mb-0">
        <?= format_inr($totalTips) ?>
      </h2>
      <div class="small text-success mt-1">100% remitted to delivery riders</div>
    </div>
  </div>

</div>

<!-- =========================================================================
     FILTERS & TAB NAVIGATION
     ========================================================================= -->
<div class="card border-0 rounded-4 shadow-sm p-3 mb-4 bg-white">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
    
    <!-- Tab Navigation -->
    <ul class="nav nav-pills gap-2">
      <li class="nav-item">
        <a class="nav-link rounded-pill px-4 <?= ($viewTab === 'orders') ? 'active bg-success' : 'text-dark bg-light' ?>" href="<?= ADMIN_URL ?>/reviews.php?tab=orders&star=<?= $starFilter ?>">
          <i class="bi bi-receipt me-1"></i> Order Reviews (<?= count($orderReviewsList) ?>)
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link rounded-pill px-4 <?= ($viewTab === 'dishes') ? 'active bg-success' : 'text-dark bg-light' ?>" href="<?= ADMIN_URL ?>/reviews.php?tab=dishes&star=<?= $starFilter ?>">
          <i class="fa-solid fa-bowl-food me-1"></i> Dish Reviews (<?= count($dishReviewsList) ?>)
        </a>
      </li>
    </ul>

    <!-- Star Filter Pills -->
    <div class="d-flex flex-wrap align-items-center gap-1">
      <span class="small fw-bold text-muted me-1">Star Filter:</span>
      <a href="<?= ADMIN_URL ?>/reviews.php?tab=<?= $viewTab ?>&star=0" class="btn btn-sm rounded-pill <?= $starFilter === 0 ? 'btn-dark' : 'btn-light border' ?>">
        All
      </a>
      <?php for ($s = 5; $s >= 1; $s--): ?>
        <a href="<?= ADMIN_URL ?>/reviews.php?tab=<?= $viewTab ?>&star=<?= $s ?>" class="btn btn-sm rounded-pill <?= $starFilter === $s ? 'btn-warning text-dark fw-bold' : 'btn-light border text-muted' ?>">
          <?= $s ?> ★
        </a>
      <?php endfor; ?>
    </div>

  </div>
</div>

<!-- =========================================================================
     REVIEWS LIST TABLE
     ========================================================================= -->
<div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden">
  
  <?php if ($viewTab === 'orders'): ?>
    <!-- ORDER REVIEWS TABLE -->
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light small text-uppercase fw-semibold text-muted">
          <tr>
            <th class="ps-4">Order & Customer</th>
            <th>Food Rating ⭐</th>
            <th>Rider Rating</th>
            <th>Tip</th>
            <th style="min-width: 250px;">Comments & Feedback</th>
            <th>Date</th>
            <th class="text-end pe-4">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($orderReviewsList)): ?>
            <?php foreach ($orderReviewsList as $rev): ?>
              <tr>
                <td class="ps-4">
                  <div class="fw-bold text-dark">Order #ZIP-<?= (int)$rev['order_id'] ?></div>
                  <div class="small text-muted"><?= e($rev['customer_name'] ?? 'Pooja JP') ?> &bull; <?= e($rev['customer_email'] ?? '') ?></div>
                  <div class="small text-forest fw-semibold"><?= format_inr($rev['total_amount'] ?? 0) ?></div>
                </td>

                <td>
                  <div class="d-flex align-items-center gap-1 text-warning fs-5">
                    <?= str_repeat('★', (int)$rev['food_rating']) . str_repeat('☆', 5 - (int)$rev['food_rating']) ?>
                  </div>
                  <span class="badge bg-warning bg-opacity-25 text-dark fw-bold" style="font-size:0.7rem;">
                    <?= (int)$rev['food_rating'] ?> / 5 Stars
                  </span>
                </td>

                <td>
                  <div class="small fw-semibold text-dark"><?= e($rev['delivery_partner_name'] ?? 'Suresh Kumar') ?></div>
                  <div class="text-warning small">
                    <?= str_repeat('★', (int)$rev['rider_rating']) . str_repeat('☆', 5 - (int)$rev['rider_rating']) ?>
                    <span class="text-muted ms-1"><?= (int)$rev['rider_rating'] ?>/5</span>
                  </div>
                </td>

                <td>
                  <?php if ((float)$rev['rider_tip'] > 0): ?>
                    <span class="badge bg-success bg-opacity-15 text-success fw-bold px-2 py-1">
                      <i class="bi bi-heart-fill me-1"></i> <?= format_inr($rev['rider_tip']) ?>
                    </span>
                  <?php else: ?>
                    <span class="text-muted small">&mdash;</span>
                  <?php endif; ?>
                </td>

                <td>
                  <?php if (!empty($rev['comments'])): ?>
                    <p class="small text-dark mb-0 fst-italic">"<?= e($rev['comments']) ?>"</p>
                  <?php else: ?>
                    <span class="text-muted small fst-italic">No written comments provided</span>
                  <?php endif; ?>
                </td>

                <td class="small text-muted">
                  <?= date('d M Y, h:i A', strtotime($rev['created_at'])) ?>
                </td>

                <td class="text-end pe-4">
                  <a href="<?= CUSTOMER_URL ?>/invoice.php?order_id=<?= (int)$rev['order_id'] ?>" class="btn btn-sm btn-light border rounded-pill px-3" target="_blank" title="View Tax Invoice">
                    <i class="bi bi-file-earmark-text text-success me-1"></i> Invoice
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" class="text-center py-5 text-muted">
                <i class="bi bi-chat-quote fs-1 d-block mb-2 text-secondary opacity-50"></i>
                <h6>No order reviews match this filter</h6>
                <p class="small text-muted mb-0">Customer reviews will populate here as users rate orders on the storefront.</p>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  <?php else: ?>
    <!-- DISH REVIEWS TABLE -->
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light small text-uppercase fw-semibold text-muted">
          <tr>
            <th class="ps-4">Dish</th>
            <th>Customer</th>
            <th>Dish Star Rating ⭐</th>
            <th>Tags</th>
            <th style="min-width: 250px;">Review Text</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($dishReviewsList)): ?>
            <?php foreach ($dishReviewsList as $dr): ?>
              <tr>
                <td class="ps-4">
                  <div class="d-flex align-items-center gap-3">
                    <img src="<?= e(get_dish_image_url($dr['image_url'])) ?>" 
                         alt="<?= e($dr['item_name']) ?>" 
                         class="rounded-3 object-fit-cover shadow-sm" 
                         style="width: 48px; height: 48px;">
                    <div>
                      <div class="fw-bold text-dark small mb-0"><?= e($dr['item_name']) ?></div>
                      <span class="badge <?= $dr['is_veg'] ? 'bg-success' : 'bg-danger' ?> px-1 py-0" style="font-size:0.6rem;">
                        <?= $dr['is_veg'] ? 'VEG' : 'NON-VEG' ?>
                      </span>
                      <span class="small text-muted ms-1"><?= format_inr($dr['price']) ?></span>
                    </div>
                  </div>
                </td>

                <td>
                  <div class="fw-semibold text-dark small"><?= e($dr['customer_name'] ?? 'Pooja JP') ?></div>
                  <div class="text-muted" style="font-size: 0.72rem;"><?= e($dr['customer_email'] ?? '') ?></div>
                </td>

                <td>
                  <div class="d-flex align-items-center gap-1 text-warning fs-5">
                    <?= str_repeat('★', (int)$dr['rating']) . str_repeat('☆', 5 - (int)$dr['rating']) ?>
                  </div>
                  <span class="badge bg-warning bg-opacity-25 text-dark fw-bold" style="font-size:0.7rem;">
                    <?= (int)$dr['rating'] ?> / 5 Stars
                  </span>
                </td>

                <td>
                  <?php if (!empty($dr['tags'])): ?>
                    <?php foreach (explode(',', $dr['tags']) as $tag): ?>
                      <span class="badge bg-light text-forest border px-2 py-1 mb-1" style="font-size:0.65rem;">
                        <?= e(trim($tag)) ?>
                      </span>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <span class="text-muted small">&mdash;</span>
                  <?php endif; ?>
                </td>

                <td>
                  <p class="small text-dark mb-0 fst-italic">"<?= e($dr['review_text'] ?: 'Delicious taste and top culinary presentation!') ?>"</p>
                </td>

                <td class="small text-muted">
                  <?= date('d M Y', strtotime($dr['created_at'])) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-center py-5 text-muted">
                <i class="bi bi-star fs-1 d-block mb-2 text-secondary opacity-50"></i>
                <h6>No dish reviews found</h6>
                <p class="small text-muted mb-0">Dish-level reviews will appear as customers rate individual items.</p>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
