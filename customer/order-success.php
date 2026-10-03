<?php
/**
 * Order Success & Confirmation Page
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Order Placed Successfully!';
$activePage = 'orders';

require_once __DIR__ . '/includes/header.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 1004;

// Fetch Order Details
$order = db_fetch_one("
    SELECT o.*, u.full_name, u.phone_number, u.email,
           a.address_line, a.pincode,
           p.payment_mode, p.transaction_reference, p.payment_status,
           d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp, d.delivery_status
    FROM `orders` o
    JOIN `users` u ON o.user_id = u.user_id
    LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    WHERE o.order_id = :id
", [':id' => $orderId]);

if (!$order) {
    echo "<div class='container py-5 text-center'><h4>Order not found</h4><a href='" . CUSTOMER_URL . "/index.php' class='btn btn-success rounded-pill mt-3'>Return to Home</a></div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Fetch Items
$orderItems = db_fetch_all("
    SELECT oi.*, m.item_name, m.is_veg, m.image_url 
    FROM `order_items` oi
    JOIN `menu_items` m ON oi.item_id = m.item_id
    WHERE oi.order_id = :id
", [':id' => $orderId]);
?>

<main class="py-5">
  <div class="container-xl">
    
    <div class="card border-0 rounded-4 shadow-sm p-4 p-md-5 bg-white text-center mb-4" style="max-width: 760px; margin: 0 auto;">
      
      <!-- Animated Check Icon -->
      <div class="mb-4">
        <div class="rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm" style="width: 84px; height: 84px; background: #CEFFDC; color: #2E6F40;">
          <i class="bi bi-check-lg" style="font-size: 3rem;"></i>
        </div>
      </div>

      <span class="badge rounded-pill px-3 py-1 mb-2 fw-semibold" style="background: #2E6F40; color: #CEFFDC;">
        ORDER CONFIRMED & QUEUED
      </span>

      <h2 class="fw-bold font-outfit text-dark mb-2">Thank you, <?= e($order['full_name']) ?>!</h2>
      <p class="text-muted small mb-4">
        Your order <strong>#ZIP-<?= (int)$order['order_id'] ?></strong> has been sent to the kitchen. Our cooks are preparing your fresh meal!
      </p>

      <!-- Secret 6-Digit Delivery OTP Card -->
      <div class="p-3 p-md-4 rounded-4 mb-4 text-start" style="background: #EDFBF2; border: 2px dashed #2E6F40;">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
          <div>
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="bi bi-shield-lock-fill text-success fs-5"></i>
              <strong class="text-dark">Secret Delivery Handshake OTP</strong>
            </div>
            <div class="small text-muted">
              Share this 6-digit code with courier <strong class="text-dark"><?= e($order['delivery_partner_name'] ?: 'Zippy Express Rider') ?></strong> only when you receive your food bag.
            </div>
          </div>
          <div class="bg-white px-4 py-2 rounded-3 border shadow-sm text-center">
            <span class="small text-muted d-block" style="font-size: 0.7rem; letter-spacing: 1px;">DELIVERY OTP</span>
            <span class="fs-2 fw-bold font-monospace text-forest" style="letter-spacing: 4px;">
              <?= e($order['delivery_otp'] ?: '482194') ?>
            </span>
          </div>
        </div>
      </div>

      <!-- Quick Metrics Grid -->
      <div class="row g-3 text-start mb-4">
        <div class="col-sm-6">
          <div class="p-3 rounded-3 bg-light border">
            <div class="small text-muted">Total Paid (<?= e($order['payment_mode']) ?>)</div>
            <div class="fw-bold fs-5 text-dark"><?= format_inr($order['total_amount']) ?></div>
            <div class="small text-muted text-truncate">Ref: <code class="text-dark"><?= e($order['transaction_reference']) ?></code></div>
            <?php if (str_starts_with($order['transaction_reference'] ?? '', 'pay_')): ?>
              <span class="badge rounded-pill mt-1" style="background:#02042B; color:#528FF0; font-size:0.7rem;">
                <i class="bi bi-shield-check me-1"></i> Razorpay Test Verified
              </span>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="p-3 rounded-3 bg-light border">
            <div class="small text-muted">Estimated Delivery Time</div>
            <div class="fw-bold fs-5 text-success">
              <i class="bi bi-clock-history me-1"></i> 25-30 Mins
            </div>
            <div class="small text-muted">Arriving at <?= e($order['address_line'] ?: 'Indiranagar') ?></div>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="d-flex flex-wrap gap-3 justify-content-center pt-2 border-top">
        <a href="<?= CUSTOMER_URL ?>/order-status.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
          <i class="bi bi-geo-alt-fill me-1"></i> Live Order Tracker
        </a>
        <a href="<?= CUSTOMER_URL ?>/invoice.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-outline-success rounded-pill px-4 py-2 fw-semibold" target="_blank">
          <i class="bi bi-file-earmark-text me-1"></i> Tax Invoice (GST)
        </a>
        <a href="<?= CUSTOMER_URL ?>/index.php" class="btn btn-light rounded-pill px-4 py-2 text-muted border">
          Order More Food
        </a>
      </div>

    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
