<?php
/**
 * Order Success & Confirmation Celebration Page
 * MCSP-232: E-Commerce Platform for Food Order
 */
$pageTitle = 'Order Placed Successfully! - Zippy';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/models/Order.php';

$orderNumber = $_GET['order'] ?? 'ZIP-948261';
$order = Order::getByOrderNumber($orderNumber);
?>

  <main class="container-xl py-5" style="max-width: 760px;">
    
    <!-- Success Celebration Card -->
    <div class="p-4 p-md-5 bg-white rounded-4 border shadow-sm text-center mb-4">
      
      <!-- Animated Success Icon -->
      <div class="spec-icon mx-auto mb-3 bg-success text-white rounded-circle d-flex align-items-center justify-content-center shadow" style="width: 80px; height: 80px; font-size: 2.5rem; background-color: var(--lush-forest) !important;">
        <i class="bi bi-check-lg"></i>
      </div>

      <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1 rounded-pill mb-2">ORDER CONFIRMED</span>
      <h2 class="fw-extrabold text-pine mb-2">Thank you! Your food is on its way.</h2>
      <p class="text-muted small mb-4" style="max-width: 480px; margin: 0 auto;">
        We've received your order. Meghana Foods has accepted the ticket and cooking has begun in our Indiranagar kitchen.
      </p>

      <!-- Key Order Meta Grid -->
      <div class="row g-3 text-start mb-4">
        
        <!-- Order Number -->
        <div class="col-sm-6">
          <div class="p-3 rounded-3 bg-light border">
            <span class="small text-muted fw-bold text-uppercase d-block mb-1">ORDER ID</span>
            <div class="fw-bold text-pine font-monospace fs-5"><?= sanitize($order['order_number'] ?? $orderNumber) ?></div>
            <span class="small text-muted">Placed at <?= date('h:i A') ?></span>
          </div>
        </div>

        <!-- Secret Delivery OTP -->
        <div class="col-sm-6">
          <div class="p-3 rounded-3 border" style="background-color: var(--lush-mint-soft); border-color: var(--lush-sage) !important;">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="small text-success fw-bold text-uppercase">SECRET DELIVERY OTP</span>
              <span class="badge bg-success text-white small">Rider Verification</span>
            </div>
            <div class="fw-extrabold text-pine font-monospace fs-4" style="letter-spacing: 2px;">
              <?= sanitize($order['delivery_otp'] ?? '839210') ?>
            </div>
            <span class="small text-muted">Share only with rider upon doorstep delivery</span>
          </div>
        </div>

      </div>

      <!-- Action Buttons -->
      <div class="d-flex flex-wrap gap-2 justify-content-center">
        <a href="order-status.php?order=<?= urlencode($orderNumber) ?>" class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-xs" style="background-color: var(--lush-forest);">
          <i class="fa-solid fa-location-crosshairs me-2"></i> Track Order Live
        </a>
        <a href="invoice.php?order=<?= urlencode($orderNumber) ?>" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-bold">
          <i class="bi bi-receipt me-2"></i> GST Tax Invoice
        </a>
        <a href="kitchen-display.php" class="btn btn-warning rounded-pill px-4 py-2 fw-bold text-dark">
          <i class="fa-solid fa-fire-burner me-2"></i> View in Kitchen KDS
        </a>
      </div>

    </div>

    <!-- Items Ordered Summary -->
    <?php if (!empty($order['items'])): ?>
      <div class="p-4 bg-white rounded-4 border shadow-xs mb-4">
        <h5 class="fw-bold text-pine mb-3">Order Details</h5>
        <div class="d-flex flex-column gap-2 border-bottom pb-3 mb-3">
          <?php foreach ($order['items'] as $it): ?>
            <div class="d-flex justify-content-between align-items-center small">
              <div>
                <span class="food-type-icon <?= !empty($it['is_veg']) ? 'veg' : 'non-veg' ?> me-1"></span>
                <strong><?= sanitize($it['item_name']) ?></strong> &times; <?= $it['quantity'] ?>
              </div>
              <div class="fw-bold text-pine"><?= formatPrice($it['line_total'] ?? ($it['unit_price'] * $it['quantity'])) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="d-flex justify-content-between align-items-center fw-bold text-pine">
          <span>Total Paid (incl. 5% GST & delivery)</span>
          <span class="text-success fs-5"><?= formatPrice($order['total_amount'] ?? 0) ?></span>
        </div>
      </div>
    <?php endif; ?>

  </main>

  <script>
    // Clear client-side cart after successful order placement
    localStorage.removeItem('pooja_zippy_cart');
    localStorage.removeItem('pooja_active_coupon');
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
