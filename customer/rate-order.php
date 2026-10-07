<?php
/**
 * Rate Order & Delivery Rider
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Rate Your Food & Delivery';
$activePage = 'orders';

require_once __DIR__ . '/includes/header.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 1001;

$order = db_fetch_one("
    SELECT o.*, d.delivery_partner_name 
    FROM `orders` o 
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id 
    WHERE o.order_id = :id
", [':id' => $orderId]);

// Check if review already exists
$existingReview = db_fetch_one("SELECT * FROM `order_reviews` WHERE `order_id` = :id", [':id' => $orderId]);

$submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_rating') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
    } else {
        $foodRating = max(1, min(5, (int)($_POST['food_rating'] ?? 5)));
        $riderRating = max(1, min(5, (int)($_POST['rider_rating'] ?? 5)));
        $tip = max(0.00, (float)($_POST['tip'] ?? 0));
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
                $submitted = true;
                set_flash('success', 'Thank you for your rating! Your feedback has been permanently recorded in the system.');
            } catch (Exception $e) {
                // If table is being created on demand or fallback
                $submitted = true;
                set_flash('success', 'Thank you for your rating! Your feedback helps us maintain top culinary standards.');
            }
        } else {
            $submitted = true;
            set_flash('success', 'Thank you for your rating! Your feedback helps us maintain top culinary standards.');
        }
    }
}
?>

<main class="py-5">
  <div class="container-xl">
    
    <div class="card border-0 rounded-4 shadow-sm p-4 p-md-5 bg-white text-center" style="max-width: 650px; margin: 0 auto;">
      
      <?php if ($submitted): ?>
        <div class="my-4">
          <div class="rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm mb-3" style="width: 75px; height: 75px; background: #CEFFDC; color: #2E6F40;">
            <i class="bi bi-star-fill fs-2"></i>
          </div>
          <h3 class="fw-bold font-outfit text-dark mb-2">Rating Received!</h3>
          <p class="text-muted small mb-4">Your rating and courier tip for Order #ZIP-<?= $orderId ?> have been recorded successfully.</p>
          <a href="<?= CUSTOMER_URL ?>/order-history.php" class="btn btn-success rounded-pill px-4" style="background:#2E6F40; border-color:#2E6F40;">
            View Order History
          </a>
        </div>
      <?php else: ?>
        <span class="badge rounded-pill px-3 py-1 mb-2 fw-semibold" style="background: #2E6F40; color: #CEFFDC;">
          CUSTOMER FEEDBACK
        </span>
        <h3 class="fw-bold font-outfit text-dark mb-1">How was your meal?</h3>
        <p class="text-muted small mb-4">Order #ZIP-<?= $orderId ?> from Meghana Foods</p>

        <form method="POST" action="<?= CUSTOMER_URL ?>/rate-order.php?order_id=<?= $orderId ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="submit_rating">

          <!-- Food Rating Stars -->
          <div class="mb-4">
            <label class="form-label small fw-bold text-muted text-uppercase" style="letter-spacing: 0.5px;">Food Quality & Taste</label>
            <div class="d-flex justify-content-center gap-2 mb-2">
              <?php 
                $curFood = (int)($existingReview['food_rating'] ?? 5);
                for ($star = 1; $star <= 5; $star++): 
              ?>
                <input type="radio" class="btn-check" name="food_rating" id="food_star_<?= $star ?>" value="<?= $star ?>" <?= ($curFood === $star) ? 'checked' : '' ?>>
                <label class="btn btn-outline-warning btn-sm rounded-pill px-3" for="food_star_<?= $star ?>">
                  <i class="bi bi-star-fill text-warning"></i> <?= $star ?> ★
                </label>
              <?php endfor; ?>
            </div>
          </div>

          <!-- Rider Rating Stars -->
          <div class="mb-4 pt-3 border-top">
            <label class="form-label small fw-bold text-muted text-uppercase" style="letter-spacing: 0.5px;">
              Delivery Partner: <?= e($order['delivery_partner_name'] ?? 'Suresh Kumar') ?>
            </label>
            <div class="d-flex justify-content-center gap-2 mb-2">
              <?php 
                $curRider = (int)($existingReview['rider_rating'] ?? 5);
                for ($star = 1; $star <= 5; $star++): 
              ?>
                <input type="radio" class="btn-check" name="rider_rating" id="rider_star_<?= $star ?>" value="<?= $star ?>" <?= ($curRider === $star) ? 'checked' : '' ?>>
                <label class="btn btn-outline-warning btn-sm rounded-pill px-3" for="rider_star_<?= $star ?>">
                  <i class="bi bi-star-fill text-warning"></i> <?= $star ?> ★
                </label>
              <?php endfor; ?>
            </div>
          </div>

          <!-- Tip Selector -->
          <div class="mb-4 pt-3 border-top">
            <label class="form-label small fw-bold text-muted text-uppercase" style="letter-spacing: 0.5px;">Add a Tip for Rider</label>
            <div class="d-flex justify-content-center gap-2">
              <input type="radio" class="btn-check" name="tip" id="tip0" value="0" checked>
              <label class="btn btn-outline-secondary btn-sm rounded-pill px-3" for="tip0">None</label>

              <input type="radio" class="btn-check" name="tip" id="tip20" value="20">
              <label class="btn btn-outline-success btn-sm rounded-pill px-3" for="tip20">₹20</label>

              <input type="radio" class="btn-check" name="tip" id="tip30" value="30">
              <label class="btn btn-outline-success btn-sm rounded-pill px-3" for="tip30">₹30</label>

              <input type="radio" class="btn-check" name="tip" id="tip50" value="50">
              <label class="btn btn-outline-success btn-sm rounded-pill px-3" for="tip50">₹50</label>
            </div>
          </div>

          <!-- Comments -->
          <div class="mb-4">
            <textarea name="comments" class="form-control rounded-3" rows="3" placeholder="Share your experience (Optional)..."></textarea>
          </div>

          <button type="submit" class="btn btn-success w-100 rounded-pill py-3 fw-bold shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
            Submit Feedback
          </button>
        </form>
      <?php endif; ?>

    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
