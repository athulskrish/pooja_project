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

$submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_rating') {
    $submitted = true;
    set_flash('success', 'Thank you for your rating! Your feedback helps us maintain top culinary standards.');
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
            <div class="fs-1 text-warning d-flex justify-content-center gap-2 cursor-pointer">
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
            </div>
          </div>

          <!-- Rider Rating Stars -->
          <div class="mb-4 pt-3 border-top">
            <label class="form-label small fw-bold text-muted text-uppercase" style="letter-spacing: 0.5px;">
              Delivery Partner: <?= e($order['delivery_partner_name'] ?? 'Suresh Kumar') ?>
            </label>
            <div class="fs-1 text-warning d-flex justify-content-center gap-2 cursor-pointer">
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star"></i>
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
