<?php
/**
 * Rate Order & Delivery Rider
 * Zippy - Online Food Ordering Platform
 * Simplified & streamlined dual-rating experience (Food & Delivery)
 */

$pageTitle = 'Rate Your Order';
$activePage = 'orders';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$currentUser = current_user();

// Resolve target Order ID
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if (!$orderId && $currentUser) {
    $recentOrder = db_fetch_one("SELECT order_id FROM `orders` WHERE user_id = :uid ORDER BY order_id DESC LIMIT 1", [':uid' => $currentUser['id']]);
    if ($recentOrder) {
        $orderId = (int)$recentOrder['order_id'];
    }
}
if (!$orderId) {
    $recentOrder = db_fetch_one("SELECT order_id FROM `orders` ORDER BY order_id DESC LIMIT 1");
    if ($recentOrder) {
        $orderId = (int)$recentOrder['order_id'];
    }
}

// Fetch Order and Delivery details
$order = $orderId ? db_fetch_one("
    SELECT o.*, d.delivery_partner_name, d.delivery_partner_phone 
    FROM `orders` o 
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id 
    WHERE o.order_id = :id
", [':id' => $orderId]) : null;

// Fetch items ordered in this order
$orderItems = $orderId ? db_fetch_all("
    SELECT oi.*, m.item_name, m.image_url, m.is_veg, m.price, m.rating 
    FROM `order_items` oi
    JOIN `menu_items` m ON oi.item_id = m.item_id
    WHERE oi.order_id = :oid
", [':oid' => $orderId]) : [];

// Check if review already exists
$existingReview = $orderId ? db_fetch_one("SELECT * FROM `order_reviews` WHERE `order_id` = :id", [':id' => $orderId]) : null;

// Fetch any existing dish-level reviews for this order
$existingDishReviews = [];
if ($orderId) {
    $dishRevRows = db_fetch_all("SELECT * FROM `dish_reviews` WHERE `order_id` = :oid", [':oid' => $orderId]);
    foreach ($dishRevRows as $dr) {
        $existingDishReviews[$dr['item_id']] = $dr;
    }
}

$submitted = false;

// Handle Review Submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_rating') {
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
              || (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1');

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid or expired session token.']);
            exit;
        }
        set_flash('danger', 'Invalid session token.');
    } elseif (!$order) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
            exit;
        }
        set_flash('danger', 'Order not found.');
    } else {
        // RBAC Access Check: Customer can only rate their own orders (unless admin)
        if ($currentUser && (int)($order['user_id'] ?? 0) !== (int)$currentUser['id'] && !is_admin()) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Access Denied: You can only submit ratings for your own orders.']);
                exit;
            }
            set_flash('danger', 'Access Denied: You can only submit ratings for orders placed under your own account.');
            header("Location: " . CUSTOMER_URL . "/order-history.php");
            exit;
        }

        $foodRating = max(1, min(5, (int)($_POST['food_rating'] ?? 5)));
        $riderRating = max(1, min(5, (int)($_POST['rider_rating'] ?? 5)));
        $tip = max(0.00, (float)($_POST['tip'] ?? ($existingReview['rider_tip'] ?? 0)));
        $comments = clean_input($_POST['comments'] ?? '');
        $userId = $order['user_id'] ?? ($currentUser['id'] ?? 2);

        $pdo = getDBConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO `order_reviews` (`order_id`, `user_id`, `food_rating`, `rider_rating`, `rider_tip`, `comments`)
                    VALUES (:oid, :uid, :frating, :rrating, :tip, :comm)
                    ON DUPLICATE KEY UPDATE
                        `food_rating` = VALUES(`food_rating`),
                        `rider_rating` = VALUES(`rider_rating`),
                        `rider_tip` = VALUES(`rider_tip`),
                        `comments` = VALUES(`comments`)
                ");
                $stmt->execute([
                    ':oid' => $orderId,
                    ':uid' => $userId,
                    ':frating' => $foodRating,
                    ':rrating' => $riderRating,
                    ':tip' => $tip,
                    ':comm' => $comments
                ]);

                // Cascade and persist dish-level feedback (Appearance, Taste, Portion Size, Value)
                if (!empty($orderItems)) {
                    foreach ($orderItems as $item) {
                        $itemId = (int)$item['item_id'];
                        $dishApp = isset($_POST['dish_appearance_' . $itemId]) ? max(1, min(5, (int)$_POST['dish_appearance_' . $itemId])) : $foodRating;
                        $dishTaste = isset($_POST['dish_taste_' . $itemId]) ? max(1, min(5, (int)$_POST['dish_taste_' . $itemId])) : $foodRating;
                        $dishPortion = isset($_POST['dish_portion_' . $itemId]) ? max(1, min(5, (int)$_POST['dish_portion_' . $itemId])) : $foodRating;
                        $dishValue = isset($_POST['dish_value_' . $itemId]) ? max(1, min(5, (int)$_POST['dish_value_' . $itemId])) : $foodRating;
                        
                        $itemRating = isset($_POST['dish_rating_' . $itemId]) ? max(1, min(5, (int)$_POST['dish_rating_' . $itemId])) : (int)round(($dishApp + $dishTaste + $dishPortion + $dishValue) / 4);

                        // Check if review already exists for this dish in this order
                        $existingDishRev = db_fetch_one("
                            SELECT dish_review_id FROM `dish_reviews`
                            WHERE `item_id` = :iid AND `order_id` = :oid AND `user_id` = :uid
                        ", [
                            ':iid' => $itemId,
                            ':oid' => $orderId,
                            ':uid' => $userId
                        ]);

                        if ($existingDishRev) {
                            db_query("
                                UPDATE `dish_reviews`
                                SET `rating` = :r,
                                    `appearance_rating` = :a,
                                    `taste_rating` = :t,
                                    `portion_rating` = :p,
                                    `value_rating` = :v,
                                    `review_text` = :txt
                                WHERE `dish_review_id` = :drid
                            ", [
                                ':r' => $itemRating,
                                ':a' => $dishApp,
                                ':t' => $dishTaste,
                                ':p' => $dishPortion,
                                ':v' => $dishValue,
                                ':txt' => $comments,
                                ':drid' => $existingDishRev['dish_review_id']
                            ]);
                        } else {
                            db_query("
                                INSERT INTO `dish_reviews` (`item_id`, `order_id`, `user_id`, `rating`, `appearance_rating`, `taste_rating`, `portion_rating`, `value_rating`, `review_text`, `tags`)
                                VALUES (:iid, :oid, :uid, :r, :a, :t, :p, :v, :txt, :tags)
                            ", [
                                ':iid' => $itemId,
                                ':oid' => $orderId,
                                ':uid' => $userId,
                                ':r' => $itemRating,
                                ':a' => $dishApp,
                                ':t' => $dishTaste,
                                ':p' => $dishPortion,
                                ':v' => $dishValue,
                                ':txt' => $comments,
                                ':tags' => 'Order Verified'
                            ]);
                        }

                        // Recalculate average rating & total reviews
                        $stats = db_fetch_one("
                            SELECT AVG(rating) AS avg_rating, COUNT(*) AS count_reviews 
                            FROM `dish_reviews` 
                            WHERE `item_id` = :iid
                        ", [':iid' => $itemId]);

                        if ($stats && $stats['count_reviews']) {
                            db_query("
                                UPDATE `menu_items` 
                                SET `rating` = :r, `total_reviews` = GREATEST(`total_reviews` + 1, :cnt) 
                                WHERE `item_id` = :iid
                            ", [
                                ':r' => round((float)$stats['avg_rating'], 1),
                                ':cnt' => (int)$stats['count_reviews'],
                                ':iid' => $itemId
                            ]);
                        }
                    }
                }

                $submitted = true;
                set_flash('success', 'Thank you! Your rating has been recorded successfully. ⭐');
                // Refresh review record
                $existingReview = db_fetch_one("SELECT * FROM `order_reviews` WHERE `order_id` = :id", [':id' => $orderId]);

                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Thank you! Your rating has been recorded successfully. ⭐',
                        'order_id' => $orderId,
                        'food_rating' => $foodRating,
                        'rider_rating' => $riderRating
                    ]);
                    exit;
                }
            } catch (Exception $e) {
                $submitted = true;
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Thank you for your rating!']);
                    exit;
                }
                set_flash('success', 'Thank you for your rating!');
            }
        }
    }
}

$curFood = (int)($existingReview['food_rating'] ?? 5);
$curRider = (int)($existingReview['rider_rating'] ?? 5);
$curComments = $existingReview['comments'] ?? '';
$curTip = (float)($existingReview['rider_tip'] ?? 0);
$isEdit = !empty($_GET['edit']);

// Rider name formatted cleanly
$riderName = !empty($order['delivery_partner_name']) 
    ? trim(str_replace('(Rider)', '', $order['delivery_partner_name'])) 
    : 'Delivery Partner';

// Item names summary
$itemNames = [];
if (!empty($orderItems)) {
    foreach ($orderItems as $it) {
        $itemNames[] = e($it['item_name']) . ($it['quantity'] > 1 ? ' (' . $it['quantity'] . ')' : '');
    }
}
$itemsSummary = !empty($itemNames) ? implode(', ', $itemNames) : 'Food Order';
$orderThumbnail = !empty($orderItems[0]['image_url']) ? get_dish_image_url($orderItems[0]['image_url']) : '';

// Rating Labels
$foodLabels = [
    1 => 'Poor 😞',
    2 => 'Fair 😐',
    3 => 'Good 👍',
    4 => 'Very Good 😊',
    5 => 'Excellent! 🌟'
];

$riderLabels = [
    1 => 'Poor 😞',
    2 => 'Fair 😐',
    3 => 'Good 👍',
    4 => 'Very Good 😊',
    5 => 'Fast & Polite! 🌟'
];

require_once __DIR__ . '/includes/header.php';
?>

<style>
.star-rating-bar .star-icon {
  font-size: 2.3rem;
  cursor: pointer;
  color: #D1D5DB;
  transition: transform 0.15s ease, color 0.15s ease, filter 0.15s ease;
  padding: 0 4px;
  user-select: none;
}
.star-rating-bar .star-icon.active {
  color: #FFB800;
  transform: scale(1.08);
  filter: drop-shadow(0 2px 4px rgba(255, 184, 0, 0.4));
}
.star-rating-bar .star-icon:hover {
  transform: scale(1.25);
}
@media (max-width: 576px) {
  .star-rating-bar .star-icon {
    font-size: 1.95rem;
    padding: 0 2px;
  }
}
</style>

<main class="py-5" style="background: #F7FAF8; min-height: 85vh;">
  <div class="container-xl" style="max-width: 640px;">
    
    <!-- Top Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between mb-4">
      <a href="<?= CUSTOMER_URL ?>/order-history.php" class="btn btn-sm btn-light rounded-pill border px-3 text-muted">
        <i class="bi bi-arrow-left me-1"></i> Back to Orders
      </a>
      <span class="badge rounded-pill px-3 py-1 fw-bold" style="background:#CEFFDC; color:#243E2C;">
        <i class="bi bi-star-fill text-warning me-1"></i> Rate Order
      </span>
    </div>

    <?php if (!$order): ?>
      <!-- Order Not Found -->
      <div class="card border-0 rounded-4 shadow-sm p-5 bg-white text-center">
        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 70px; height: 70px; background: #F3F4F6; color: #6B7280;">
          <i class="bi bi-receipt fs-1"></i>
        </div>
        <h5 class="fw-bold text-dark font-outfit">Order Not Found</h5>
        <p class="text-muted small mb-4">We couldn't locate the specified order to review.</p>
        <div>
          <a href="<?= CUSTOMER_URL ?>/order-history.php" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
            View Order History
          </a>
        </div>
      </div>

    <?php else: ?>

      <!-- Order Summary Card -->
      <div class="card border-0 rounded-4 shadow-sm p-3 p-sm-4 bg-white mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div class="d-flex align-items-center gap-3">
            <?php if ($orderThumbnail): ?>
              <img src="<?= e($orderThumbnail) ?>" class="rounded-3 object-fit-cover shadow-sm" style="width: 56px; height: 56px;" alt="Order Item">
            <?php else: ?>
              <div class="rounded-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 56px; height: 56px; background: #EBF7ED; color: #2E6F40; font-size: 1.5rem;">
                <i class="bi bi-bag-check-fill"></i>
              </div>
            <?php endif; ?>
            <div>
              <div class="fw-bold text-dark font-outfit fs-6 mb-0">
                Order #ZIP-<?= $orderId ?>
              </div>
              <div class="small text-muted text-truncate" style="max-width: 320px;" title="<?= e($itemsSummary) ?>">
                <?= e($itemsSummary) ?>
              </div>
              <div class="small text-muted" style="font-size: 0.78rem;">
                <?= !empty($order['placed_at']) ? date('d M Y, h:i A', strtotime($order['placed_at'])) : 'Recent Order' ?> &bull; <?= format_inr($order['total_amount'] ?? 0) ?>
              </div>
            </div>
          </div>
          <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fw-bold px-3 py-1 rounded-pill small">
            <i class="bi bi-check-circle-fill me-1"></i> <?= e($order['order_status'] ?? 'DELIVERED') ?>
          </span>
        </div>
      </div>

      <?php if ($submitted || ($existingReview && !$isEdit)): ?>
        <!-- Display Saved Review -->
        <div class="card border-0 rounded-4 shadow-sm p-4 p-sm-5 bg-white text-center mb-4">
          <div class="rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm mx-auto mb-3" style="width: 72px; height: 72px; background: #CEFFDC; color: #2E6F40;">
            <i class="bi bi-check-circle-fill fs-1 text-success"></i>
          </div>

          <h4 class="fw-bold font-outfit text-dark mb-1">
            <?= $submitted ? 'Thank You For Your Review! ⭐' : 'Your Order Review' ?>
          </h4>
          <p class="text-muted small mb-4" style="max-width: 480px; margin: 0 auto;">
            Your feedback helps our kitchen chefs and riders maintain high standards.
          </p>

          <!-- Rating Details Grid -->
          <div class="p-3 p-sm-4 rounded-4 mb-4 text-start" style="background: #F7FAF8; border: 1px solid #E2ECE5;">
            <div class="row g-3">
              
              <div class="col-sm-6 border-end-sm">
                <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size:0.75rem; letter-spacing: 0.5px;">Food Taste &amp; Quality</div>
                <div class="d-flex align-items-center gap-2">
                  <span class="fs-4 text-warning">
                    <?= str_repeat('★', $curFood) . str_repeat('☆', 5 - $curFood) ?>
                  </span>
                  <span class="fw-bold text-dark fs-6"><?= $curFood ?>/5</span>
                </div>
              </div>

              <div class="col-sm-6 ps-sm-3">
                <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size:0.75rem; letter-spacing: 0.5px;">Delivery by <?= e($riderName) ?></div>
                <div class="d-flex align-items-center gap-2">
                  <span class="fs-4 text-warning">
                    <?= str_repeat('★', $curRider) . str_repeat('☆', 5 - $curRider) ?>
                  </span>
                  <span class="fw-bold text-dark fs-6"><?= $curRider ?>/5</span>
                </div>
                <?php if ($curTip > 0): ?>
                  <div class="small text-success mt-1"><i class="bi bi-heart-fill me-1"></i> ₹<?= number_format($curTip, 0) ?> Tip Given</div>
                <?php endif; ?>
              </div>

              <?php if (!empty($curComments)): ?>
                <div class="col-12 pt-3 border-top">
                  <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size:0.75rem; letter-spacing: 0.5px;">Your Feedback</div>
                  <div class="fst-italic text-dark bg-white p-3 rounded-3 border small">
                    "<?= e($curComments) ?>"
                  </div>
                </div>
              <?php endif; ?>

              <!-- Dish-Level Breakdown in Saved View -->
              <?php if (!empty($orderItems) && !empty($existingDishReviews)): ?>
                <div class="col-12 pt-3 border-top">
                  <div class="small fw-bold text-muted text-uppercase mb-2" style="font-size:0.75rem; letter-spacing: 0.5px;">Dish-Level Feedback Breakdown</div>
                  <div class="d-flex flex-column gap-2">
                    <?php foreach ($orderItems as $item): 
                      $iid = (int)$item['item_id'];
                      $edr = $existingDishReviews[$iid] ?? null;
                      if (!$edr) continue;
                    ?>
                      <div class="p-2 px-3 rounded-3 bg-white border d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="fw-semibold text-dark small"><?= e($item['item_name']) ?></div>
                        <div class="d-flex flex-wrap gap-2 small">
                          <span class="badge bg-light text-primary border"><i class="bi bi-eye me-1"></i>Appearance: <?= (int)$edr['appearance_rating'] ?>★</span>
                          <span class="badge bg-light text-danger border"><i class="bi bi-fire me-1"></i>Taste: <?= (int)$edr['taste_rating'] ?>★</span>
                          <span class="badge bg-light text-warning text-dark border"><i class="bi bi-pie-chart me-1"></i>Portion: <?= (int)$edr['portion_rating'] ?>★</span>
                          <span class="badge bg-light text-success border"><i class="bi bi-tag me-1"></i>Value: <?= (int)$edr['value_rating'] ?>★</span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>

            </div>
          </div>

          <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="<?= CUSTOMER_URL ?>/rate-order.php?order_id=<?= $orderId ?>&edit=1" class="btn btn-outline-success rounded-pill px-4 fw-semibold">
              <i class="bi bi-pencil me-1"></i> Edit Review
            </a>
            <a href="<?= CUSTOMER_URL ?>/order-history.php" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
              <i class="bi bi-receipt me-1"></i> Back to Orders
            </a>
          </div>
        </div>

      <?php else: ?>

        <!-- Simplified Rating Form for Both Food and Delivery -->
        <div class="card border-0 rounded-4 shadow-sm p-4 p-sm-5 bg-white mb-4">
          <div class="text-center mb-4">
            <h4 class="fw-bold text-dark font-outfit mb-1"><?= $isEdit ? 'Edit Your Review' : 'How was your experience?' ?></h4>
            <p class="text-muted small mb-0">Rate your food and delivery with a single tap</p>
          </div>

          <form method="POST" action="<?= CUSTOMER_URL ?>/rate-order.php?order_id=<?= $orderId ?>" id="orderRatingForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="submit_rating">
            <input type="hidden" name="food_rating" id="foodRatingInput" value="<?= $curFood ?>">
            <input type="hidden" name="rider_rating" id="riderRatingInput" value="<?= $curRider ?>">

            <!-- 1. Food Rating Section -->
            <div class="p-3 p-sm-4 rounded-4 mb-3" style="background: #F7FAF8; border: 1px solid #E2ECE5;">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                  <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: #CEFFDC; color: #2E6F40;">
                    <i class="bi bi-egg-fried fs-5"></i>
                  </span>
                  <div>
                    <div class="fw-bold text-dark font-outfit">Overall Food Quality &amp; Taste</div>
                    <div class="small text-muted" style="font-size: 0.8rem;">General culinary satisfaction and packaging</div>
                  </div>
                </div>
                <span class="badge rounded-pill px-3 py-1 fw-bold fs-6" id="foodStarLabel" style="background: #FFF3CD; color: #856404;">
                  <?= $foodLabels[$curFood] ?? '5 Stars' ?>
                </span>
              </div>

              <!-- Star Rating Bar for Food -->
              <div class="d-flex justify-content-center py-2">
                <div class="star-rating-bar d-inline-flex align-items-center gap-2" id="foodStars" data-target="foodRatingInput" data-label="foodStarLabel" data-type="food">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="bi bi-star-fill star-icon <?= $i <= $curFood ? 'active' : '' ?>" 
                       data-value="<?= $i ?>" 
                       title="<?= $i ?> Star<?= $i > 1 ? 's' : '' ?>"></i>
                  <?php endfor; ?>
                </div>
              </div>
            </div>

            <!-- 2. Dish-Level Detailed Feedback (Appearance, Taste, Portion Size, Value) -->
            <?php if (!empty($orderItems)): ?>
              <div class="p-3 p-sm-4 rounded-4 mb-3" style="background: #FFFFFF; border: 1px solid #E2ECE5; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom flex-wrap gap-2">
                  <div class="d-flex align-items-center gap-2">
                    <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: #CEFFDC; color: #2E6F40;">
                      <i class="bi bi-stars"></i>
                    </span>
                    <div>
                      <div class="fw-bold text-dark font-outfit" style="font-size: 0.95rem;">Dish-Level Feedback</div>
                      <div class="small text-muted" style="font-size: 0.78rem;">Rate Appearance, Taste, Portion Size &amp; Value for each ordered dish</div>
                    </div>
                  </div>
                  <span class="badge bg-light text-forest border rounded-pill px-2 py-1 small fw-semibold" style="font-size:0.72rem;">
                    <?= count($orderItems) ?> <?= count($orderItems) === 1 ? 'Dish' : 'Dishes' ?>
                  </span>
                </div>

                <div class="d-flex flex-column gap-3">
                  <?php foreach ($orderItems as $item): 
                    $iid = (int)$item['item_id'];
                    $edr = $existingDishReviews[$iid] ?? null;
                    $dishApp = (int)($edr['appearance_rating'] ?? $curFood);
                    $dishTaste = (int)($edr['taste_rating'] ?? $curFood);
                    $dishPortion = (int)($edr['portion_rating'] ?? $curFood);
                    $dishValue = (int)($edr['value_rating'] ?? $curFood);
                  ?>
                    <div class="p-3 rounded-3 border bg-light bg-opacity-50">
                      <div class="d-flex align-items-center gap-3 mb-2">
                        <img src="<?= e(get_dish_image_url($item['image_url'])) ?>" class="rounded-3 object-fit-cover shadow-xs border" style="width: 44px; height: 44px;" alt="<?= e($item['item_name']) ?>">
                        <div class="flex-grow-1">
                          <div class="fw-bold text-dark small mb-0">
                            <?= e($item['item_name']) ?>
                            <span class="badge <?= $item['is_veg'] ? 'bg-success' : 'bg-danger' ?> px-1 py-0 ms-1" style="font-size:0.58rem;"><?= $item['is_veg'] ? 'VEG' : 'NON-VEG' ?></span>
                          </div>
                          <div class="small text-muted" style="font-size:0.72rem;">Qty: <?= (int)$item['quantity'] ?> &bull; <?= format_inr($item['unit_price']) ?></div>
                        </div>
                      </div>

                      <!-- 4-Dimension Matrix for this Dish -->
                      <div class="row g-2 pt-1">
                        <!-- Appearance -->
                        <div class="col-sm-6">
                          <div class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-white border">
                            <span class="small text-muted fw-semibold" style="font-size:0.75rem;"><i class="bi bi-eye text-primary me-1"></i>Appearance:</span>
                            <input type="hidden" name="dish_appearance_<?= $iid ?>" id="dish_app_input_<?= $iid ?>" value="<?= $dishApp ?>">
                            <div class="d-inline-flex gap-1 dish-dim-star-group" data-target="dish_app_input_<?= $iid ?>" data-label="dish_app_lbl_<?= $iid ?>">
                              <?php for ($s = 1; $s <= 5; $s++): ?>
                                <i class="bi bi-star-fill small star-crit <?= $s <= $dishApp ? 'text-warning' : 'text-muted opacity-50' ?>" data-val="<?= $s ?>" style="cursor:pointer; font-size:0.85rem;"></i>
                              <?php endfor; ?>
                            </div>
                            <span class="small font-monospace text-dark fw-bold" id="dish_app_lbl_<?= $iid ?>" style="font-size:0.75rem; width:28px; text-align:right;"><?= $dishApp ?>★</span>
                          </div>
                        </div>

                        <!-- Taste -->
                        <div class="col-sm-6">
                          <div class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-white border">
                            <span class="small text-muted fw-semibold" style="font-size:0.75rem;"><i class="bi bi-fire text-danger me-1"></i>Taste:</span>
                            <input type="hidden" name="dish_taste_<?= $iid ?>" id="dish_taste_input_<?= $iid ?>" value="<?= $dishTaste ?>">
                            <div class="d-inline-flex gap-1 dish-dim-star-group" data-target="dish_taste_input_<?= $iid ?>" data-label="dish_taste_lbl_<?= $iid ?>">
                              <?php for ($s = 1; $s <= 5; $s++): ?>
                                <i class="bi bi-star-fill small star-crit <?= $s <= $dishTaste ? 'text-warning' : 'text-muted opacity-50' ?>" data-val="<?= $s ?>" style="cursor:pointer; font-size:0.85rem;"></i>
                              <?php endfor; ?>
                            </div>
                            <span class="small font-monospace text-dark fw-bold" id="dish_taste_lbl_<?= $iid ?>" style="font-size:0.75rem; width:28px; text-align:right;"><?= $dishTaste ?>★</span>
                          </div>
                        </div>

                        <!-- Portion Size -->
                        <div class="col-sm-6">
                          <div class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-white border">
                            <span class="small text-muted fw-semibold" style="font-size:0.75rem;"><i class="bi bi-pie-chart text-warning me-1"></i>Portion:</span>
                            <input type="hidden" name="dish_portion_<?= $iid ?>" id="dish_portion_input_<?= $iid ?>" value="<?= $dishPortion ?>">
                            <div class="d-inline-flex gap-1 dish-dim-star-group" data-target="dish_portion_input_<?= $iid ?>" data-label="dish_portion_lbl_<?= $iid ?>">
                              <?php for ($s = 1; $s <= 5; $s++): ?>
                                <i class="bi bi-star-fill small star-crit <?= $s <= $dishPortion ? 'text-warning' : 'text-muted opacity-50' ?>" data-val="<?= $s ?>" style="cursor:pointer; font-size:0.85rem;"></i>
                              <?php endfor; ?>
                            </div>
                            <span class="small font-monospace text-dark fw-bold" id="dish_portion_lbl_<?= $iid ?>" style="font-size:0.75rem; width:28px; text-align:right;"><?= $dishPortion ?>★</span>
                          </div>
                        </div>

                        <!-- Value -->
                        <div class="col-sm-6">
                          <div class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-white border">
                            <span class="small text-muted fw-semibold" style="font-size:0.75rem;"><i class="bi bi-tag text-success me-1"></i>Value:</span>
                            <input type="hidden" name="dish_value_<?= $iid ?>" id="dish_value_input_<?= $iid ?>" value="<?= $dishValue ?>">
                            <div class="d-inline-flex gap-1 dish-dim-star-group" data-target="dish_value_input_<?= $iid ?>" data-label="dish_value_lbl_<?= $iid ?>">
                              <?php for ($s = 1; $s <= 5; $s++): ?>
                                <i class="bi bi-star-fill small star-crit <?= $s <= $dishValue ? 'text-warning' : 'text-muted opacity-50' ?>" data-val="<?= $s ?>" style="cursor:pointer; font-size:0.85rem;"></i>
                              <?php endfor; ?>
                            </div>
                            <span class="small font-monospace text-dark fw-bold" id="dish_value_lbl_<?= $iid ?>" style="font-size:0.75rem; width:28px; text-align:right;"><?= $dishValue ?>★</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <!-- 2. Delivery Partner Rating Section -->
            <div class="p-3 p-sm-4 rounded-4 mb-4" style="background: #F7FAF8; border: 1px solid #E2ECE5;">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                  <span class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: #CEFFDC; color: #2E6F40;">
                    <i class="bi bi-bicycle fs-5"></i>
                  </span>
                  <div>
                    <div class="fw-bold text-dark font-outfit">Delivery by <?= e($riderName) ?></div>
                    <div class="small text-muted" style="font-size: 0.8rem;">Delivery speed and politeness</div>
                  </div>
                </div>
                <span class="badge rounded-pill px-3 py-1 fw-bold fs-6" id="riderStarLabel" style="background: #FFF3CD; color: #856404;">
                  <?= $riderLabels[$curRider] ?? '5 Stars' ?>
                </span>
              </div>

              <!-- Star Rating Bar for Rider -->
              <div class="d-flex justify-content-center py-2">
                <div class="star-rating-bar d-inline-flex align-items-center gap-2" id="riderStars" data-target="riderRatingInput" data-label="riderStarLabel" data-type="rider">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="bi bi-star-fill star-icon <?= $i <= $curRider ? 'active' : '' ?>" 
                       data-value="<?= $i ?>" 
                       title="<?= $i ?> Star<?= $i > 1 ? 's' : '' ?>"></i>
                  <?php endfor; ?>
                </div>
              </div>
            </div>

            <!-- 3. Comments (Optional) -->
            <div class="mb-4">
              <label class="form-label small fw-bold text-muted text-uppercase mb-2" style="letter-spacing: 0.5px;">
                Comments or Feedback (Optional)
              </label>
              <textarea name="comments" class="form-control rounded-3 p-3" rows="3" placeholder="Share your experience or notes on food or delivery..."><?= e($curComments) ?></textarea>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-success w-100 rounded-pill py-3 fw-bold shadow fs-6" style="background:#2E6F40; border-color:#2E6F40;">
              <i class="bi bi-check2-circle me-1"></i> <?= $isEdit ? 'Save Changes' : 'Submit Review' ?>
            </button>
          </form>
        </div>

      <?php endif; ?>

    <?php endif; ?>

  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const foodLabels = {
    1: 'Poor 😞',
    2: 'Fair 😐',
    3: 'Good 👍',
    4: 'Very Good 😊',
    5: 'Excellent! 🌟'
  };

  const riderLabels = {
    1: 'Poor 😞',
    2: 'Fair 😐',
    3: 'Good 👍',
    4: 'Very Good 😊',
    5: 'Fast & Polite! 🌟'
  };

  function updateStars(container, rating) {
    const stars = container.querySelectorAll('.star-icon');
    stars.forEach(star => {
      const val = parseInt(star.getAttribute('data-value'), 10);
      if (val <= rating) {
        star.classList.add('active');
      } else {
        star.classList.remove('active');
      }
    });
  }

  // Setup interactive star rating groups
  document.querySelectorAll('.star-rating-bar').forEach(group => {
    const targetInputId = group.getAttribute('data-target');
    const labelId = group.getAttribute('data-label');
    const type = group.getAttribute('data-type');
    const input = document.getElementById(targetInputId);
    const label = document.getElementById(labelId);
    const labelMap = (type === 'rider') ? riderLabels : foodLabels;

    let selectedVal = parseInt(input ? input.value : '5', 10);
    updateStars(group, selectedVal);

    group.querySelectorAll('.star-icon').forEach(star => {
      const val = parseInt(star.getAttribute('data-value'), 10);

      // Hover / Touch interaction
      star.addEventListener('mouseenter', () => {
        updateStars(group, val);
        if (label) label.textContent = labelMap[val] || `${val} Stars`;
      });

      // Click to choose
      star.addEventListener('click', () => {
        selectedVal = val;
        if (input) input.value = val;
        updateStars(group, selectedVal);
        if (label) label.textContent = labelMap[val] || `${val} Stars`;
      });
    });

    group.addEventListener('mouseleave', () => {
      updateStars(group, selectedVal);
      if (label) label.textContent = labelMap[selectedVal] || `${selectedVal} Stars`;
    });
  });

  // Setup individual dish-level dimension star clickers
  document.querySelectorAll('.dish-dim-star-group').forEach(group => {
    const targetInputId = group.getAttribute('data-target');
    const labelId = group.getAttribute('data-label');
    const input = document.getElementById(targetInputId);
    const label = document.getElementById(labelId);
    const stars = group.querySelectorAll('.star-crit');

    stars.forEach(star => {
      star.addEventListener('click', () => {
        const val = parseInt(star.getAttribute('data-val'), 10);
        if (input) input.value = val;
        if (label) label.textContent = `${val}★`;
        stars.forEach(s => {
          const sVal = parseInt(s.getAttribute('data-val'), 10);
          if (sVal <= val) {
            s.classList.remove('text-muted', 'opacity-50');
            s.classList.add('text-warning');
          } else {
            s.classList.remove('text-warning');
            s.classList.add('text-muted', 'opacity-50');
          }
        });
      });
    });
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
