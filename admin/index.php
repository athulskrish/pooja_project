<?php
/**
 * Executive Admin Dashboard & Kitchen Dispatch Management
 * Zippy - Online Food Ordering Platform
 * Student: Pooja JP | MCSP-232
 */

$pageTitle = 'Operations & Kitchen Dashboard';
$activePage = 'dashboard';

// Bootstrap core libraries before HTML output (prevents "headers already sent")
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/WSJFQueue.php';
$currentUser = current_user();

// Quick Status Update POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_status_update') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
    } else {
        $orderId = (int)$_POST['order_id'];
        $newStatus = clean_input($_POST['new_status']);
        
        $currentOrder = db_fetch_one("SELECT order_status FROM `orders` WHERE `order_id` = :id", [':id' => $orderId]);
        if ($currentOrder) {
            $oldStatus = $currentOrder['order_status'];
            
            db_query("UPDATE `orders` SET `order_status` = :st WHERE `order_id` = :id", [
                ':st' => $newStatus,
                ':id' => $orderId
            ]);

            // Audit Trail Record
            db_query("
                INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`) 
                VALUES (:oid, :old, :new, :uid, NOW())
            ", [
                ':oid' => $orderId,
                ':old' => $oldStatus,
                ':new' => $newStatus,
                ':uid' => $currentUser['id'] ?? null
            ]);

            // If DELIVERED, update delivery_dispatches delivered_at
            if ($newStatus === 'DELIVERED') {
                db_query("UPDATE `delivery_dispatches` SET `delivered_at` = NOW(), `delivery_status` = 'DELIVERED' WHERE `order_id` = :id", [':id' => $orderId]);
            }

            set_flash('success', "Order #ZIP-{$orderId} status updated to {$newStatus}.");
            header("Location: " . ADMIN_URL . "/index.php");
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';

// 1. KPI Aggregations
$totalRevenue = db_fetch_val("SELECT COALESCE(SUM(total_amount), 0) FROM `orders` WHERE order_status != 'CANCELLED'") ?: 0;
$totalOrders = db_fetch_val("SELECT COUNT(*) FROM `orders`") ?: 0;
$activeKitchen = db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE order_status IN ('CONFIRMED', 'COOKING')") ?: 0;
$lowStockCount = db_fetch_val("SELECT COUNT(*) FROM `menu_items` WHERE stock_quantity <= 15 AND is_available = 1") ?: 0;

// 2. WSJF Prioritized Orders
$wsjfQueue = WSJFQueue::getPrioritizedOrders();

// 3. Recent 6 Orders
$recentOrders = db_fetch_all("
    SELECT o.*, u.full_name, u.phone_number, p.payment_mode, p.payment_status 
    FROM `orders` o
    JOIN `users` u ON o.user_id = u.user_id
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    ORDER BY o.placed_at DESC
    LIMIT 6
");
?>

<!-- ===========================================================================
     KPI METRIC CARDS
     =========================================================================== -->
<div class="row g-4 mb-4">
  
  <!-- KPI 1: Revenue -->
  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-bold text-uppercase" style="letter-spacing:0.5px;">TOTAL REVENUE</span>
        <div class="rounded-3 p-2 text-success" style="background:#EDFBF2;">
          <i class="bi bi-currency-rupee fs-4"></i>
        </div>
      </div>
      <h3 class="fw-bold font-outfit text-dark mb-1"><?= format_inr($totalRevenue) ?></h3>
      <span class="small text-success"><i class="bi bi-arrow-up-right me-1"></i>+14.8% from yesterday</span>
    </div>
  </div>

  <!-- KPI 2: Total Orders -->
  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-bold text-uppercase" style="letter-spacing:0.5px;">TOTAL ORDERS</span>
        <div class="rounded-3 p-2 text-primary" style="background:#eef4ff;">
          <i class="bi bi-bag-check fs-4"></i>
        </div>
      </div>
      <h3 class="fw-bold font-outfit text-dark mb-1"><?= (int)$totalOrders ?> Orders</h3>
      <span class="small text-muted">All-time fulfillment</span>
    </div>
  </div>

  <!-- KPI 3: Active Kitchen Tickets -->
  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-bold text-uppercase" style="letter-spacing:0.5px;">KITCHEN QUEUE</span>
        <div class="rounded-3 p-2 text-warning" style="background:#fffbe6;">
          <i class="fa-solid fa-fire-burner fs-4"></i>
        </div>
      </div>
      <h3 class="fw-bold font-outfit text-dark mb-1"><?= (int)$activeKitchen ?> Active</h3>
      <span class="small text-warning fw-bold"><i class="bi bi-clock-history me-1"></i>WSJF Scheduling active</span>
    </div>
  </div>

  <!-- KPI 4: Low Stock Alert -->
  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-bold text-uppercase" style="letter-spacing:0.5px;">LOW STOCK DISHES</span>
        <div class="rounded-3 p-2 text-danger" style="background:#fff1f1;">
          <i class="bi bi-exclamation-triangle fs-4"></i>
        </div>
      </div>
      <h3 class="fw-bold font-outfit text-dark mb-1"><?= (int)$lowStockCount ?> Dishes</h3>
      <a href="<?= ADMIN_URL ?>/dishes.php" class="small text-danger fw-bold text-decoration-none">
        Restock items &rarr;
      </a>
    </div>
  </div>

</div>

<!-- ===========================================================================
     WSJF KITCHEN PREPARATION QUEUE (ALGORITHMIC DEPTH)
     =========================================================================== -->
<div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between border-bottom pb-3 mb-3 gap-2">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="badge rounded-pill px-3 py-1" style="background:#2E6F40; color:#CEFFDC;">
          🧠 WSJF PRIORITY QUEUE
        </span>
        <span class="small text-muted fw-bold">Dynamic Kitchen Ticket Scheduling</span>
      </div>
      <h4 class="fw-bold font-outfit mb-0 text-dark">Active Kitchen Preparation Tickets</h4>
    </div>
    <a href="<?= KITCHEN_URL ?>/index.php" target="_blank" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-bold">
      <i class="fa-solid fa-fire-burner me-1"></i> Open Kitchen KDS Portal &rarr;
    </a>
  </div>

  <?php if (empty($wsjfQueue)): ?>
    <div class="text-center py-4 text-muted">
      <i class="bi bi-check2-all fs-1 text-success opacity-75 mb-2 d-block"></i>
      <h6>All kitchen tickets fulfilled! No active orders waiting.</h6>
      <div class="small">New orders placed by customers will automatically populate here in real-time.</div>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($wsjfQueue as $ticket): ?>
        <div class="col-md-6 col-xl-4">
          <div class="card border rounded-4 p-3 h-100 shadow-sm d-flex flex-column justify-content-between <?= ($ticket['wsjf_score'] >= 1.5) ? 'border-danger' : 'border-success' ?>">
            
            <div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge bg-dark fw-bold">Ticket #ZIP-<?= (int)$ticket['order_id'] ?></span>
                <span class="badge <?= $ticket['urgency_badge']['class'] ?>"><?= $ticket['urgency_badge']['label'] ?></span>
              </div>

              <div class="d-flex justify-content-between small text-muted mb-2">
                <span>Waiting: <strong><?= (int)$ticket['elapsed_minutes'] ?>m</strong></span>
                <span>Job Duration: <strong><?= (int)$ticket['job_duration'] ?>m</strong></span>
                <span class="text-forest fw-bold">WSJF: <?= $ticket['wsjf_score'] ?></span>
              </div>

              <div class="p-2 rounded-3 bg-light border mb-3 small">
                <div class="fw-bold text-dark mb-1">Customer: <?= e($ticket['customer_name']) ?></div>
                <div class="text-muted">
                  <?php foreach ($ticket['items'] as $it): ?>
                    <div>• <?= (int)$it['quantity'] ?>x <?= e($it['item_name']) ?></div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- 1-Click State Advance Form -->
            <form method="POST" action="<?= ADMIN_URL ?>/index.php" class="m-0">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="quick_status_update">
              <input type="hidden" name="order_id" value="<?= (int)$ticket['order_id'] ?>">

              <?php if ($ticket['order_status'] === 'CONFIRMED'): ?>
                <input type="hidden" name="new_status" value="COOKING">
                <button type="submit" class="btn btn-warning w-100 rounded-pill btn-sm fw-bold shadow-sm">
                  <i class="fa-solid fa-fire me-1"></i> Start Cooking
                </button>
              <?php elseif ($ticket['order_status'] === 'COOKING'): ?>
                <input type="hidden" name="new_status" value="READY">
                <button type="submit" class="btn btn-primary w-100 rounded-pill btn-sm fw-bold shadow-sm">
                  <i class="bi bi-box-seam me-1"></i> Mark Ready for Pickup
                </button>
              <?php else: ?>
                <input type="hidden" name="new_status" value="OUT_FOR_DELIVERY">
                <button type="submit" class="btn btn-success w-100 rounded-pill btn-sm fw-bold shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
                  <i class="bi bi-bicycle me-1"></i> Dispatch to Rider
                </button>
              <?php endif; ?>
            </form>

          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<!-- ===========================================================================
     RECENT ORDERS DISPATCH TABLE
     =========================================================================== -->
<div class="card border-0 rounded-4 shadow-sm p-4 bg-white">
  <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
    <h5 class="fw-bold font-outfit mb-0 text-dark">Recent Customer Transactions</h5>
    <a href="<?= ADMIN_URL ?>/orders.php" class="btn btn-link text-success btn-sm p-0 fw-bold text-decoration-none">
      View All Orders &rarr;
    </a>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle small mb-0">
      <thead class="table-light">
        <tr>
          <th>Order ID</th>
          <th>Customer</th>
          <th>Placed Time</th>
          <th>Total (INR)</th>
          <th>Payment</th>
          <th>Order Status</th>
          <th>Quick Transition</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentOrders as $ro): ?>
          <tr>
            <td class="fw-bold font-monospace">#ZIP-<?= (int)$ro['order_id'] ?></td>
            <td>
              <div class="fw-bold text-dark"><?= e($ro['full_name']) ?></div>
              <div class="small text-muted"><?= e($ro['phone_number']) ?></div>
            </td>
            <td><?= date('d M, h:i A', strtotime($ro['placed_at'])) ?></td>
            <td class="fw-bold text-forest"><?= format_inr($ro['total_amount']) ?></td>
            <td>
              <span class="badge bg-light text-dark border"><?= e($ro['payment_mode']) ?></span>
            </td>
            <td><?= get_status_badge($ro['order_status']) ?></td>
            <td>
              <form method="POST" action="<?= ADMIN_URL ?>/index.php" class="d-flex align-items-center gap-1 m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="quick_status_update">
                <input type="hidden" name="order_id" value="<?= (int)$ro['order_id'] ?>">
                
                <select name="new_status" class="form-select form-select-sm" style="max-width: 140px;">
                  <option value="CONFIRMED" <?= ($ro['order_status'] === 'CONFIRMED') ? 'selected' : '' ?>>Confirmed</option>
                  <option value="COOKING" <?= ($ro['order_status'] === 'COOKING') ? 'selected' : '' ?>>Cooking</option>
                  <option value="READY" <?= ($ro['order_status'] === 'READY') ? 'selected' : '' ?>>Ready</option>
                  <option value="OUT_FOR_DELIVERY" <?= ($ro['order_status'] === 'OUT_FOR_DELIVERY') ? 'selected' : '' ?>>Out for Delivery</option>
                  <option value="DELIVERED" <?= ($ro['order_status'] === 'DELIVERED') ? 'selected' : '' ?>>Delivered</option>
                  <option value="CANCELLED" <?= ($ro['order_status'] === 'CANCELLED') ? 'selected' : '' ?>>Cancelled</option>
                </select>

                <button type="submit" class="btn btn-sm btn-outline-success">Update</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
