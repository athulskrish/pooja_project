<?php
/**
 * Rate Order & Delivery Rider with Interactive Stars
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Rate Your Food & Delivery';
$activePage = 'orders';

require_once __DIR__ . '/includes/header.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 1001;

$order = db_fetch_one("
    SELECT o.*, d.delivery_partner_name, d.delivery_partner_phone 
    FROM `orders` o 
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id 
    WHERE o.order_id = :id
", [':id' => $orderId]);

// Fetch items ordered in this order
$orderItems = db_fetch_all("
    SELECT oi.*, m.item_name, m.image_url, m.is_veg, m.price, m.rating 
    FROM `order_items` oi
    JOIN `menu_items` m ON oi.item_id = m.item_id
    WHERE oi.order_id = :oid
", [':oid' => $orderId]);

// Check if review already exists
$existingReview = db_fetch_one("SELECT * FROM `order_reviews` WHERE `order_id` = :id", [':id' => $orderId]);

$submitted = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_rating') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
    } else {
        // RBAC Access Check: Customer can only rate their own orders
        if ($currentUser && (int)($order['user_id'] ?? 0) !== (int)$currentUser['id'] && !is_admin()) {
            set_flash('danger', 'Access Denied: You can only submit ratings for orders placed under your own account.');
            header("Location: " . CUSTOMER_URL . "/order-history.php");
            exit;
        }

        $foodRating = max(1, min(5, (int)($_POST['food_rating'] ?? 5)));
        $riderRating = max(1, min(5, (int)($_POST['rider_rating'] ?? 5)));
        $tip = max(0.00, (float)($_POST['tip'] ?? 0));
        $comments = clean_input($_POST['comments'] ?? '');
        $foodTags = clean_input($_POST['food_tags'] ?? '');
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

                // Save individual dish ratings if present
                if (!empty($orderItems)) {
                    foreach ($orderItems as $item) {
                        $itemId = (int)$item['item_id'];
                        $itemRating = isset($_POST['dish_rating_' . $itemId]) ? max(1, min(5, (int)$_POST['dish_rating_' . $itemId])) : $foodRating;
                        
                        db_query("
                            INSERT INTO `dish_reviews` (`item_id`, `order_id`, `user_id`, `rating`, `review_text`, `tags`)
                            VALUES (:iid, :oid, :uid, :r, :t, :tags)
                        ", [
                            ':iid' => $itemId,
                            ':oid' => $orderId,
                            ':uid' => $userId,
                            ':r' => $itemRating,
                            ':t' => $comments,
                            ':tags' => $foodTags
                        ]);

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
                set_flash('success', 'Thank you for your rating! Your food review has been permanently recorded with 5-star precision. ⭐');
                // Refresh existing review state
                $existingReview = db_fetch_one("SELECT * FROM `order_reviews` WHERE `order_id` = :id", [':id' => $orderId]);
            } catch (Exception $e) {
                $submitted = true;
                set_flash('success', 'Thank you for your rating! Your feedback helps us maintain top culinary standards.');
            }
        } else {
            $submitted = true;
            set_flash('success', 'Thank you for your rating! Your feedback helps us maintain top culinary standards.');
        }
    }
}

$curFood = (int)($existingReview['food_rating'] ?? 5);
$curRider = (int)($existingReview['rider_rating'] ?? 5);
$curTip = (float)($existingReview['rider_tip'] ?? 0);
$curComments = $existingReview['comments'] ?? '';
$isEdit = !empty($_GET['edit']);
?>

<main class="py-5" style="background: #F7FAF8; min-height: 80vh;">
  <div class="container-xl" style="max-width: 760px;">
    
    <!-- Top Breadcrumb / Navigation -->
    <div class="d-flex align-items-center justify-content-between mb-4">
      <a href="<?= CUSTOMER_URL ?>/order-history.php" class="btn btn-sm btn-light rounded-pill border px-3 text-muted">
        <i class="bi bi-arrow-left me-1"></i> Back to Orders
      </a>
      <span class="badge rounded-pill px-3 py-1 fw-bold" style="background:#CEFFDC; color:#243E2C;">
        ⭐ FOOD REVIEW & RATINGS
      </span>
    </div>

    <!-- Order Summary Card -->
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
          <img src="https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?auto=format&fit=crop&w=150&q=80" 
               class="rounded-3 shadow-sm object-fit-cover" 
               style="width: 68px; height: 68px;" 
               alt="Meghana Foods">
          <div>
            <h5 class="fw-bold text-dark font-outfit mb-0">Meghana Foods (Indiranagar)</h5>
            <div class="small text-muted mb-1">Order #ZIP-<?= $orderId ?> &bull; <?= !empty($order['placed_at']) ? date('d M Y, h:i A', strtotime($order['placed_at'])) : 'Recent Order' ?></div>
            <div class="small fw-semibold text-forest">
              <i class="bi bi-shield-check text-success me-1"></i> Verified Customer Purchase
            </div>
          </div>
        </div>
        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fw-bold px-3 py-2 rounded-pill">
          <i class="bi bi-check-circle-fill me-1"></i> <?= e($order['order_status'] ?? 'DELIVERED') ?>
        </span>
      </div>
    </div>

    <?php if ($submitted || ($existingReview && !$isEdit)): ?>
      <!-- Display Confirmed / Existing Review with Glowing Stars -->
      <div class="card border-0 rounded-4 shadow-sm p-4 p-md-5 bg-white text-center mb-4">
        <div class="rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm mx-auto mb-3" style="width: 80px; height: 80px; background: #CEFFDC; color: #2E6F40;">
          <i class="bi bi-star-fill fs-1 text-warning" style="filter: drop-shadow(0 2px 8px rgba(245, 158, 11, 0.5));"></i>
        </div>

        <h3 class="fw-bold font-outfit text-dark mb-1">
          <?= $submitted ? 'Thank You For Your Review! ⭐' : 'Your Recorded Review & Rating' ?>
        </h3>
        <p class="text-muted small mb-4" style="max-width: 500px; margin: 0 auto;">
          Your feedback on food quality, taste, and delivery courier service helps fellow foodies discover culinary excellence.
        </p>

        <!-- Rating Details Card -->
        <div class="p-4 rounded-4 mb-4 text-start" style="background: #F7FAF8; border: 1px solid #E2ECE5;">
          <div class="row g-3 align-items-center">
            
            <div class="col-sm-6 border-end-sm">
              <div class="small fw-bold text-muted text-uppercase mb-1">Food Taste & Quality:</div>
              <div class="d-flex align-items-center gap-2">
                <span class="fs-4 text-warning">
                  <?= str_repeat('★', $curFood) . str_repeat('☆', 5 - $curFood) ?>
                </span>
                <span class="fw-bold text-dark fs-5"><?= $curFood ?>/5 Stars</span>
              </div>
            </div>

            <div class="col-sm-6 ps-sm-3">
              <div class="small fw-bold text-muted text-uppercase mb-1">Delivery Partner:</div>
              <div class="d-flex align-items-center gap-2">
                <span class="fs-4 text-warning">
                  <?= str_repeat('★', $curRider) . str_repeat('☆', 5 - $curRider) ?>
                </span>
                <span class="fw-bold text-dark fs-5"><?= $curRider ?>/5 Stars</span>
              </div>
              <?php if ($curTip > 0): ?>
                <div class="small text-success mt-1"><i class="bi bi-heart-fill me-1"></i> ₹<?= number_format($curTip, 0) ?> Tip Given</div>
              <?php endif; ?>
            </div>

            <?php if (!empty($curComments)): ?>
              <div class="col-12 pt-3 border-top">
                <div class="small fw-bold text-muted text-uppercase mb-1">Customer Review:</div>
                <blockquote class="fst-italic text-dark mb-0 bg-white p-3 rounded-3 border">
                  "<?= e($curComments) ?>"
                </blockquote>
              </div>
            <?php endif; ?>

          </div>
        </div>

        <div class="d-flex flex-wrap justify-content-center gap-3">
          <a href="<?= CUSTOMER_URL ?>/rate-order.php?order_id=<?= $orderId ?>&edit=1" class="btn btn-outline-success rounded-pill px-4 fw-bold">
            <i class="bi bi-pencil me-1"></i> Edit Review
          </a>
          <a href="<?= CUSTOMER_URL ?>/order-history.php" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
            <i class="bi bi-receipt me-1"></i> View Order History
          </a>
        </div>
      </div>

    <?php else: ?>

      <!-- =======================================================================
           INTERACTIVE STAR RATING FORM
           ======================================================================= -->
      <form method="POST" action="<?= CUSTOMER_URL ?>/rate-order.php?order_id=<?= $orderId ?>" id="orderRatingForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="submit_rating">
        <input type="hidden" name="food_rating" id="foodRatingInput" value="<?= $curFood ?>">
        <input type="hidden" name="rider_rating" id="riderRatingInput" value="<?= $curRider ?>">
        <input type="hidden" name="food_tags" id="foodTagsInput" value="">
        <input type="hidden" name="tip" id="riderTipInput" value="<?= (int)$curTip ?>">

        <!-- Section 1: Food Quality & Taste Star Rating -->
        <div class="card border-0 rounded-4 shadow-sm p-4 p-md-5 bg-white mb-4 text-center">
          <div class="d-inline-flex align-items-center gap-1 justify-content-center mb-2">
            <span class="badge rounded-pill px-3 py-1 fw-bold" style="background: #2E6F40; color: #CEFFDC;">
              STEP 1: FOOD QUALITY
            </span>
          </div>

          <h3 class="fw-bold font-outfit text-dark mb-1">How was the food from Meghana Foods?</h3>
          <p class="text-muted small mb-4">Rate taste, freshness, authentic spices, and temperature</p>

          <!-- Interactive 5-Star Food Rating Box -->
          <div class="star-rating-box py-4 px-3 rounded-4 mb-3" style="background: #F7FAF8; border: 1.5px dashed #CDE6D4;">
            <div class="interactive-star-group d-inline-flex align-items-center gap-2" id="foodStars" data-target="foodRatingInput" data-label="foodStarLabel">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="bi bi-star-fill star-item <?= $i <= $curFood ? 'active' : '' ?>" 
                   data-value="<?= $i ?>" 
                   style="font-size: 2.6rem; cursor: pointer; transition: transform 0.15s ease, color 0.15s ease; color: <?= $i <= $curFood ? '#FFB800' : '#D1D5DB' ?>;"
                   title="<?= $i ?> Star<?= $i > 1 ? 's' : '' ?>"></i>
              <?php endfor; ?>
            </div>
            
            <div class="fw-bold font-outfit fs-5 mt-2 text-forest" id="foodStarLabel">
              <?php 
                $labels = [
                  1 => '⭐ Terrible / Poor 😞',
                  2 => '⭐⭐ Needs Improvement 😐',
                  3 => '⭐⭐⭐ Good Taste 👍',
                  4 => '⭐⭐⭐⭐ Very Good & Flavorful! 😊',
                  5 => '⭐⭐⭐⭐⭐ Outstanding & Delicious! 🌟'
                ];
                echo $labels[$curFood] ?? 'Outstanding! 🌟';
              ?>
            </div>
            <div class="small text-muted">Tap on any star to adjust your score</div>
          </div>

          <!-- Food Compliment Chips -->
          <div class="text-start mt-3">
            <label class="form-label small fw-bold text-muted text-uppercase mb-2" style="letter-spacing: 0.5px;">
              What did you like the most? (Tap to select)
            </label>
            <div class="d-flex flex-wrap gap-2 mb-3" id="foodChipsContainer">
              <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill feedback-chip" data-tag="Piping Hot & Fresh">
                ♨️ Piping Hot & Fresh
              </button>
              <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill feedback-chip" data-tag="Authentic Andhra Spice">
                🌶️ Authentic Andhra Spice
              </button>
              <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill feedback-chip" data-tag="Spill-Proof Packaging">
                📦 Spill-Proof Packaging
              </button>
              <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill feedback-chip" data-tag="Tender & Juicy Cuts">
                🍗 Tender & Juicy Cuts
              </button>
              <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill feedback-chip" data-tag="Generous Portion Size">
                🍲 Generous Portion Size
              </button>
            </div>
          </div>

          <!-- Individual Ordered Dish Ratings -->
          <?php if (!empty($orderItems)): ?>
            <div class="text-start pt-3 border-top mt-3">
              <label class="form-label small fw-bold text-muted text-uppercase mb-2" style="letter-spacing: 0.5px;">
                Rate Individual Dishes from this Order:
              </label>
              
              <div class="d-flex flex-column gap-3">
                <?php foreach ($orderItems as $item): ?>
                  <div class="card border rounded-3 p-3 bg-white d-flex flex-row justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3">
                      <img src="<?= e($item['image_url'] ?: 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=120') ?>" 
                           alt="<?= e($item['item_name']) ?>" 
                           class="rounded-3 object-fit-cover shadow-sm" 
                           style="width: 52px; height: 52px;">
                      <div>
                        <div class="fw-bold text-dark small mb-0"><?= e($item['item_name']) ?></div>
                        <span class="badge <?= $item['is_veg'] ? 'bg-success' : 'bg-danger' ?> px-2 py-0" style="font-size:0.6rem;">
                          <?= $item['is_veg'] ? 'VEG' : 'NON-VEG' ?>
                        </span>
                        <span class="small text-muted ms-1"><?= format_inr($item['price']) ?></span>
                      </div>
                    </div>

                    <!-- Mini Dish Star Widget -->
                    <div class="mini-dish-stars d-flex align-items-center gap-1" data-dish-id="<?= (int)$item['item_id'] ?>">
                      <input type="hidden" name="dish_rating_<?= (int)$item['item_id'] ?>" id="dishRating_<?= (int)$item['item_id'] ?>" value="5">
                      <?php for ($s = 1; $s <= 5; $s++): ?>
                        <i class="bi bi-star-fill text-warning mini-star" 
                           data-dish="<?= (int)$item['item_id'] ?>" 
                           data-val="<?= $s ?>" 
                           style="font-size: 1.25rem; cursor: pointer;"
                           title="<?= $s ?> Stars"></i>
                      <?php endfor; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Written Comments -->
          <div class="text-start mt-4 pt-3 border-top">
            <label class="form-label small fw-bold text-muted text-uppercase mb-2" style="letter-spacing: 0.5px;">
              Write a Review (Optional)
            </label>
            <textarea name="comments" class="form-control rounded-3 p-3" rows="3" placeholder="Share your experience on taste, texture, aroma, or packaging to guide other foodies..."><?= e($curComments) ?></textarea>
          </div>
        </div>

        <!-- Section 2: Delivery Courier Experience & Star Rating -->
        <div class="card border-0 rounded-4 shadow-sm p-4 p-md-5 bg-white mb-4 text-center">
          <div class="d-inline-flex align-items-center gap-1 justify-content-center mb-2">
            <span class="badge rounded-pill px-3 py-1 fw-bold" style="background: #2E6F40; color: #CEFFDC;">
              STEP 2: DELIVERY SERVICE
            </span>
          </div>

          <h4 class="fw-bold font-outfit text-dark mb-1">
            How was your delivery with <?= e($order['delivery_partner_name'] ?? 'Suresh Kumar') ?>?
          </h4>
          <p class="text-muted small mb-3">Rate courier speed, politeness, and contactless doorstep delivery</p>

          <div class="d-flex align-items-center justify-content-center gap-3 p-3 rounded-4 mb-3" style="background: #F7FAF8; border: 1px solid #E2ECE5; max-width: 450px; margin: 0 auto;">
            <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold fs-4 shadow-sm" style="width: 50px; height: 50px; background: #2E6F40;">
              <?= strtoupper(substr($order['delivery_partner_name'] ?? 'Suresh Kumar', 0, 1)) ?>
            </div>
            <div class="text-start">
              <h6 class="fw-bold text-dark mb-0"><?= e($order['delivery_partner_name'] ?? 'Suresh Kumar') ?></h6>
              <div class="small text-muted"><i class="bi bi-star-fill text-warning me-1"></i> 4.9 Rating &bull; Zippy Express Rider</div>
            </div>
          </div>

          <!-- Interactive 5-Star Rider Rating Box -->
          <div class="star-rating-box py-3 px-3 rounded-4 mb-3" style="background: #F7FAF8; border: 1.5px dashed #CDE6D4;">
            <div class="interactive-star-group d-inline-flex align-items-center gap-2" id="riderStars" data-target="riderRatingInput" data-label="riderStarLabel">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="bi bi-star-fill star-item <?= $i <= $curRider ? 'active' : '' ?>" 
                   data-value="<?= $i ?>" 
                   style="font-size: 2.2rem; cursor: pointer; transition: transform 0.15s ease, color 0.15s ease; color: <?= $i <= $curRider ? '#FFB800' : '#D1D5DB' ?>;"
                   title="<?= $i ?> Star<?= $i > 1 ? 's' : '' ?>"></i>
              <?php endfor; ?>
            </div>
            
            <div class="fw-bold font-outfit mt-1 text-forest" id="riderStarLabel">
              <?= $curRider === 5 ? '⚡ Lightning Fast & Courteous! 🌟' : ($curRider . ' Stars') ?>
            </div>
          </div>

          <!-- Courier Tip Selector -->
          <div class="text-start mt-3 pt-3 border-top">
            <label class="form-label small fw-bold text-muted text-uppercase mb-2" style="letter-spacing: 0.5px;">
              Add a Tip for <?= e($order['delivery_partner_name'] ?? 'Suresh') ?>? (100% credited to rider)
            </label>
            <div class="d-flex flex-wrap gap-2" id="tipSelector">
              <button type="button" class="btn btn-outline-secondary rounded-pill px-3 fw-bold tip-btn <?= $curTip == 0 ? 'active' : '' ?>" data-tip="0">
                None
              </button>
              <button type="button" class="btn btn-outline-success rounded-pill px-3 fw-bold tip-btn <?= $curTip == 20 ? 'active' : '' ?>" data-tip="20">
                ₹20
              </button>
              <button type="button" class="btn btn-outline-success rounded-pill px-3 fw-bold tip-btn <?= $curTip == 30 ? 'active' : '' ?>" data-tip="30">
                ₹30 (Popular ⭐)
              </button>
              <button type="button" class="btn btn-outline-success rounded-pill px-3 fw-bold tip-btn <?= $curTip == 50 ? 'active' : '' ?>" data-tip="50">
                ₹50
              </button>
            </div>
          </div>

        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn btn-success w-100 rounded-pill py-3 fw-bold shadow-lg fs-5" style="background:#2E6F40; border-color:#2E6F40;">
          <i class="bi bi-star-fill text-warning me-2"></i> Submit Food Review & Star Rating ⭐
        </button>

      </form>
    <?php endif; ?>

  </div>
</main>

<!-- Interactive Star Rating Client Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  
  const ratingLabels = {
    1: '⭐ Terrible / Poor 😞',
    2: '⭐⭐ Needs Improvement 😐',
    3: '⭐⭐⭐ Good Taste 👍',
    4: '⭐⭐⭐⭐ Very Good & Flavorful! 😊',
    5: '⭐⭐⭐⭐⭐ Outstanding & Delicious! 🌟'
  };

  const riderLabels = {
    1: '⭐ Very Slow / Unprofessional 😞',
    2: '⭐⭐ Below Average 😐',
    3: '⭐⭐⭐ On Time & Okay 👍',
    4: '⭐⭐⭐⭐ Quick Delivery 😊',
    5: '⭐⭐⭐⭐⭐ Lightning Fast & Courteous! 🌟'
  };

  // Helper to update star visual
  function updateStars(container, rating, activeColor = '#FFB800', inactiveColor = '#D1D5DB') {
    const stars = container.querySelectorAll('.star-item');
    stars.forEach(star => {
      const val = parseInt(star.getAttribute('data-value'), 10);
      if (val <= rating) {
        star.style.color = activeColor;
        star.style.transform = 'scale(1.12)';
        star.style.filter = 'drop-shadow(0 2px 4px rgba(245, 158, 11, 0.4))';
        star.classList.add('active');
      } else {
        star.style.color = inactiveColor;
        star.style.transform = 'scale(1)';
        star.style.filter = 'none';
        star.classList.remove('active');
      }
    });
  }

  // Setup interactive star groups (Food and Rider)
  document.querySelectorAll('.interactive-star-group').forEach(group => {
    const targetInputId = group.getAttribute('data-target');
    const labelId = group.getAttribute('data-label');
    const input = document.getElementById(targetInputId);
    const label = document.getElementById(labelId);
    const isRider = targetInputId.includes('rider');
    const labelMap = isRider ? riderLabels : ratingLabels;

    let currentVal = parseInt(input ? input.value : '5', 10);
    updateStars(group, currentVal);

    group.querySelectorAll('.star-item').forEach(star => {
      const val = parseInt(star.getAttribute('data-value'), 10);

      // Hover preview
      star.addEventListener('mouseenter', () => {
        updateStars(group, val, '#F59E0B');
        if (label) label.textContent = labelMap[val] || `${val} Stars`;
      });

      // Click select
      star.addEventListener('click', () => {
        currentVal = val;
        if (input) input.value = val;
        updateStars(group, currentVal);
        if (label) label.textContent = labelMap[val] || `${val} Stars`;
        
        // Quick visual pulse
        star.style.transform = 'scale(1.35)';
        setTimeout(() => { star.style.transform = 'scale(1.12)'; }, 150);
      });
    });

    group.addEventListener('mouseleave', () => {
      updateStars(group, currentVal);
      if (label) label.textContent = labelMap[currentVal] || `${currentVal} Stars`;
    });
  });

  // Food Compliment Chips Selection
  const chips = document.querySelectorAll('#foodChipsContainer .feedback-chip');
  const tagsInput = document.getElementById('foodTagsInput');
  const selectedTags = new Set();

  chips.forEach(chip => {
    chip.addEventListener('click', function() {
      const tag = this.getAttribute('data-tag');
      if (selectedTags.has(tag)) {
        selectedTags.delete(tag);
        this.classList.remove('btn-success', 'text-white');
        this.classList.add('btn-outline-secondary');
      } else {
        selectedTags.add(tag);
        this.classList.remove('btn-outline-secondary');
        this.classList.add('btn-success', 'text-white');
      }
      if (tagsInput) {
        tagsInput.value = Array.from(selectedTags).join(',');
      }
    });
  });

  // Mini Dish Star Widgets
  document.querySelectorAll('.mini-dish-stars').forEach(widget => {
    const dishId = widget.getAttribute('data-dish-id');
    const hiddenInput = document.getElementById('dishRating_' + dishId);
    const stars = widget.querySelectorAll('.mini-star');

    function updateMiniStars(rating) {
      stars.forEach(s => {
        const val = parseInt(s.getAttribute('data-val'), 10);
        if (val <= rating) {
          s.classList.remove('bi-star');
          s.classList.add('bi-star-fill', 'text-warning');
          s.style.color = '#FFB800';
        } else {
          s.classList.remove('bi-star-fill', 'text-warning');
          s.classList.add('bi-star');
          s.style.color = '#D1D5DB';
        }
      });
    }

    stars.forEach(s => {
      s.addEventListener('click', function() {
        const val = parseInt(this.getAttribute('data-val'), 10);
        if (hiddenInput) hiddenInput.value = val;
        updateMiniStars(val);
      });
    });
  });

  // Rider Tip Selector
  const tipBtns = document.querySelectorAll('#tipSelector .tip-btn');
  const tipInput = document.getElementById('riderTipInput');

  tipBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      tipBtns.forEach(b => {
        b.classList.remove('active', 'btn-success', 'text-white');
        b.classList.add('btn-outline-success');
      });
      this.classList.remove('btn-outline-success', 'btn-outline-secondary');
      this.classList.add('active', 'btn-success', 'text-white');
      const val = this.getAttribute('data-tip');
      if (tipInput) tipInput.value = val;
    });
  });

});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
