<?php
/**
 * Customer Order History & Reordering
 * MCSP-232: E-Commerce Platform for Food Order
 */
$pageTitle = 'My Orders & Order History - Zippy';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/models/Order.php';

$userId = $_SESSION['user']['user_id'] ?? 1;
$orders = Order::getUserOrders($userId);
?>

  <main class="container-xl py-4" style="max-width: 860px;">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="fw-bold text-pine mb-1">My Orders</h2>
        <p class="text-muted small mb-0">Track active orders, download tax invoices, and reorder favorites.</p>
      </div>
      <a href="restaurant.php" class="btn btn-sm btn-success rounded-pill px-4 fw-bold" style="background-color: var(--lush-forest);">
        + Order Food
      </a>
    </div>

    <?php if (empty($orders)): ?>
      <div class="text-center py-5 bg-white rounded-4 border">
        <i class="bi bi-clock-history text-muted fs-1 mb-2"></i>
        <h5 class="fw-bold text-pine">No past orders found</h5>
        <p class="text-muted small">You haven't placed any food orders yet.</p>
        <a href="restaurant.php" class="btn btn-sm btn-success rounded-pill px-4 fw-bold">Browse Menu</a>
      </div>
    <?php else: ?>
      <div class="d-flex flex-column gap-4">
        <?php foreach ($orders as $ord): ?>
          <?php 
            $status = strtoupper($ord['order_status']);
            $isDelivered = ($status === 'DELIVERED');
          ?>
          <div class="p-4 bg-white rounded-4 border shadow-xs">
            
            <!-- Order Header Bar -->
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 pb-3 border-bottom mb-3">
              <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                  <h5 class="fw-bold text-pine mb-0">Meghana Foods</h5>
                  <span class="badge bg-light text-muted border small">Indiranagar Outlet</span>
                </div>
                <div class="small text-muted font-monospace">
                  #<?= sanitize($ord['order_number']) ?> • <?= date('d M Y, h:i A', strtotime($ord['placed_at'])) ?>
                </div>
              </div>

              <div class="text-end">
                <span class="badge <?= $isDelivered ? 'bg-success' : 'bg-warning text-dark' ?> fw-bold px-3 py-1 rounded-pill">
                  <?= sanitize($status) ?>
                </span>
                <div class="fw-bold text-pine fs-6 mt-1"><?= formatPrice($ord['total_amount']) ?></div>
              </div>
            </div>

            <!-- Items List -->
            <div class="d-flex flex-column gap-2 mb-3">
              <?php if (!empty($ord['items'])): ?>
                <?php foreach ($ord['items'] as $item): ?>
                  <div class="d-flex justify-content-between small text-muted">
                    <div>
                      <span class="food-type-icon <?= !empty($item['is_veg']) ? 'veg' : 'non-veg' ?> me-1"></span>
                      <strong><?= sanitize($item['item_name']) ?></strong> &times; <?= $item['quantity'] ?? 1 ?>
                    </div>
                    <span><?= formatPrice(($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1)) ?></span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <!-- Order Footer & Action CTAs -->
            <div class="pt-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div class="small text-muted">
                <?php if (!$isDelivered): ?>
                  <span class="text-success fw-bold"><i class="bi bi-geo-alt-fill me-1"></i> Food in preparation</span>
                <?php else: ?>
                  <span>Delivered by <?= sanitize($ord['delivery_partner_name'] ?? 'Ramesh Kumar') ?></span>
                <?php endif; ?>
              </div>

              <div class="d-flex gap-2">
                <?php if (!$isDelivered): ?>
                  <a href="order-status.php?order=<?= urlencode($ord['order_number']) ?>" class="btn btn-sm btn-success rounded-pill px-3 fw-bold" style="background-color: var(--lush-forest);">
                    <i class="fa-solid fa-location-crosshairs me-1"></i> Track Live
                  </a>
                <?php else: ?>
                  <a href="rate-order.php?order=<?= urlencode($ord['order_number']) ?>" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold">
                    <i class="bi bi-star me-1"></i> Rate Order
                  </a>
                <?php endif; ?>
                
                <a href="invoice.php?order=<?= urlencode($ord['order_number']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                  <i class="bi bi-receipt me-1"></i> Invoice
                </a>

                <button class="btn btn-sm btn-light border rounded-pill px-3 fw-bold" onclick="reorderItems(<?= htmlspecialchars(json_encode($ord['items'] ?? [])) ?>)">
                  <i class="bi bi-arrow-repeat me-1 text-success"></i> Reorder
                </button>
              </div>
            </div>

          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </main>

  <script>
    function reorderItems(items) {
      if (!items || items.length === 0) return;
      items.forEach(it => {
        addToCart(it.item_id || 1, it.item_name, it.unit_price || 300, it.is_veg);
      });
      showToast('Items added to cart! Redirecting...');
      setTimeout(() => { window.location.href = 'cart.php'; }, 700);
    }
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
