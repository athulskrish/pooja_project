<?php
/**
 * Customer Order History & Tax Invoices
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Order History & Receipts';
$activePage = 'orders';

require_once __DIR__ . '/includes/header.php';

$userId = $currentUser ? $currentUser['id'] : 2; // Default to demo customer Pooja JP if guest

$orders = db_fetch_all("
    SELECT o.*, 
           COUNT(oi.order_item_id) AS total_items_count,
           d.delivery_partner_name, d.delivery_otp,
           p.payment_mode, p.payment_status
    FROM `orders` o
    LEFT JOIN `order_items` oi ON o.order_id = oi.order_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    WHERE o.user_id = :uid
    GROUP BY o.order_id
    ORDER BY o.placed_at DESC
", [':uid' => $userId]);

// Fetch item details for these orders
$orderItemsGrouped = [];
if (!empty($orders)) {
    $orderIds = array_column($orders, 'order_id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemsRaw = db_fetch_all("
        SELECT oi.*, m.item_name, m.is_veg 
        FROM `order_items` oi
        JOIN `menu_items` m ON oi.item_id = m.item_id
        WHERE oi.order_id IN ($placeholders)
    ", $orderIds);

    foreach ($itemsRaw as $item) {
        $orderItemsGrouped[$item['order_id']][] = $item;
    }
}
?>

<main class="py-4">
  <div class="container-xl">
    
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h2 class="fw-bold mb-0 font-outfit text-dark"><i class="bi bi-clock-history text-success me-2"></i>Past Orders & History</h2>
        <p class="text-muted small mb-0">Showing orders placed by <?= e($currentUser ? $currentUser['name'] : 'Pooja JP') ?></p>
      </div>
      <a href="<?= CUSTOMER_URL ?>/index.php#dishes" class="btn btn-success btn-sm rounded-pill px-3 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
        <i class="bi bi-plus-circle me-1"></i> Order Food
      </a>
    </div>

    <?php if (empty($orders)): ?>
      <div class="card border-0 rounded-4 shadow-sm p-5 text-center bg-white">
        <i class="bi bi-receipt text-muted opacity-50 mb-3" style="font-size: 3.5rem;"></i>
        <h5 class="fw-bold text-dark">No orders placed yet</h5>
        <p class="text-muted small mb-4">Explore our delicious biryanis, curries and rolls to place your first order.</p>
        <div>
          <a href="<?= CUSTOMER_URL ?>/index.php#dishes" class="btn btn-success rounded-pill px-4 py-2 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
            Browse Dishes
          </a>
        </div>
      </div>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($orders as $order): ?>
          <?php $items = $orderItemsGrouped[$order['order_id']] ?? []; ?>
          <div class="col-12">
            <div class="card border-0 rounded-4 shadow-sm p-4 bg-white hover-lift">
              <div class="d-flex flex-wrap align-items-center justify-content-between border-bottom pb-3 mb-3 gap-2">
                <div>
                  <span class="fw-bold fs-5 text-dark me-2">Order #ZIP-<?= (int)$order['order_id'] ?></span>
                  <span class="text-muted small">Placed on <?= date('d M Y, h:i A', strtotime($order['placed_at'])) ?></span>
                </div>
                <div>
                  <?= get_status_badge($order['order_status']) ?>
                </div>
              </div>

              <div class="row align-items-center g-3">
                
                <!-- Items Summary -->
                <div class="col-lg-6">
                  <div class="small fw-bold text-muted text-uppercase mb-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">Ordered Items:</div>
                  <div class="d-flex flex-column gap-1">
                    <?php foreach ($items as $item): ?>
                      <div class="d-flex align-items-center gap-2 small">
                        <span class="badge <?= $item['is_veg'] ? 'bg-success' : 'bg-danger' ?> px-1" style="font-size:0.6rem;"><?= (int)$item['quantity'] ?>x</span>
                        <span class="text-dark fw-semibold"><?= e($item['item_name']) ?></span>
                        <span class="text-muted ms-auto"><?= format_inr($item['line_total']) ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>

                <!-- Order Pricing & OTP -->
                <div class="col-lg-3 border-start ps-lg-4">
                  <div class="small text-muted mb-1">Total Bill: <strong class="text-forest fs-5 d-block"><?= format_inr($order['total_amount']) ?></strong></div>
                  <div class="small text-muted mb-2">Paid via <span class="badge bg-light text-dark border"><?= e($order['payment_mode']) ?></span></div>
                  <?php if (!empty($order['delivery_otp'])): ?>
                    <div class="small text-muted">Delivery OTP: <span class="badge bg-mint text-dark font-monospace fw-bold"><?= e($order['delivery_otp']) ?></span></div>
                  <?php endif; ?>
                </div>

                <!-- Action Buttons -->
                <div class="col-lg-3 text-lg-end border-start ps-lg-4">
                  <div class="d-flex flex-column gap-2">
                    <a href="<?= CUSTOMER_URL ?>/order-status.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-outline-success btn-sm rounded-pill fw-semibold py-2">
                      <i class="bi bi-geo-alt-fill me-1"></i> Live Tracker
                    </a>
                    <a href="<?= CUSTOMER_URL ?>/invoice.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-light btn-sm rounded-pill border py-2 text-muted" target="_blank">
                      <i class="bi bi-file-earmark-text me-1 text-success"></i> Tax Invoice
                    </a>
                    <a href="<?= CUSTOMER_URL ?>/rate-order.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-light btn-sm rounded-pill border py-2 text-muted">
                      <i class="bi bi-star me-1 text-warning"></i> Rate Food
                    </a>
                  </div>
                </div>

              </div>

            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
