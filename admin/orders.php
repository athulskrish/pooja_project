<?php
/**
 * Admin Comprehensive Order Fulfillment & Audit Log
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Orders Management & Dispatch';
$activePage = 'orders';

// Bootstrap core libraries before HTML output (prevents "headers already sent")
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
$currentUser = current_user();

$filterStatus = clean_input($_GET['status'] ?? '');

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
    } else {
        $orderId = (int)$_POST['order_id'];
        $newStatus = clean_input($_POST['new_status']);
        $assignedRider = clean_input($_POST['rider_name'] ?? '');

        $current = db_fetch_one("SELECT order_status FROM `orders` WHERE `order_id` = :id", [':id' => $orderId]);
        if ($current) {
            $oldStatus = $current['order_status'];

            db_query("UPDATE `orders` SET `order_status` = :st WHERE `order_id` = :id", [
                ':st' => $newStatus,
                ':id' => $orderId
            ]);

            // Insert into order_audit_trail
            db_query("
                INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`) 
                VALUES (:oid, :old, :new, :uid, NOW())
            ", [
                ':oid' => $orderId,
                ':old' => $oldStatus,
                ':new' => $newStatus,
                ':uid' => $currentUser['id'] ?? null
            ]);

            if (!empty($assignedRider)) {
                db_query("UPDATE `delivery_dispatches` SET `delivery_partner_name` = :rname WHERE `order_id` = :oid", [
                    ':rname' => $assignedRider,
                    ':oid' => $orderId
                ]);
            }

            if ($newStatus === 'DELIVERED') {
                db_query("UPDATE `delivery_dispatches` SET `delivered_at` = NOW(), `delivery_status` = 'DELIVERED' WHERE `order_id` = :id", [':id' => $orderId]);
            }

            set_flash('success', "Order #ZIP-{$orderId} status successfully advanced to {$newStatus}.");
            header("Location: " . ADMIN_URL . "/orders.php" . (!empty($filterStatus) ? "?status=" . urlencode($filterStatus) : ''));
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';

// Build query
$where = "1=1";
$params = [];
if (!empty($filterStatus)) {
    $where .= " AND o.order_status = :st";
    $params[':st'] = $filterStatus;
}

$orders = db_fetch_all("
    SELECT o.*, u.full_name, u.phone_number, u.email,
           a.address_line, a.pincode,
           p.payment_mode, p.payment_status, p.transaction_reference,
           d.delivery_partner_name, d.delivery_otp, d.delivery_status
    FROM `orders` o
    JOIN `users` u ON o.user_id = u.user_id
    LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    WHERE $where
    ORDER BY o.placed_at DESC
", $params);

// Fetch items for all orders
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

    foreach ($itemsRaw as $it) {
        $orderItemsGrouped[$it['order_id']][] = $it;
    }
}
?>

<div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
  
  <!-- Filter Pills Bar -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <span class="small fw-bold text-muted">Filter by Status:</span>
      <a href="<?= ADMIN_URL ?>/orders.php" class="btn btn-sm rounded-pill <?= empty($filterStatus) ? 'btn-dark' : 'btn-light border' ?>">All Orders</a>
      <a href="<?= ADMIN_URL ?>/orders.php?status=CONFIRMED" class="btn btn-sm rounded-pill <?= ($filterStatus === 'CONFIRMED') ? 'btn-info text-dark' : 'btn-light border' ?>">Confirmed</a>
      <a href="<?= ADMIN_URL ?>/orders.php?status=COOKING" class="btn btn-sm rounded-pill <?= ($filterStatus === 'COOKING') ? 'btn-warning text-dark' : 'btn-light border' ?>">Cooking</a>
      <a href="<?= ADMIN_URL ?>/orders.php?status=READY" class="btn btn-sm rounded-pill <?= ($filterStatus === 'READY') ? 'btn-primary' : 'btn-light border' ?>">Ready for Pickup</a>
      <a href="<?= ADMIN_URL ?>/orders.php?status=OUT_FOR_DELIVERY" class="btn btn-sm rounded-pill <?= ($filterStatus === 'OUT_FOR_DELIVERY') ? 'btn-warning' : 'btn-light border' ?>">Out for Delivery</a>
      <a href="<?= ADMIN_URL ?>/orders.php?status=DELIVERED" class="btn btn-sm rounded-pill <?= ($filterStatus === 'DELIVERED') ? 'btn-success' : 'btn-light border' ?>">Delivered</a>
      <a href="<?= ADMIN_URL ?>/orders.php?status=CANCELLED" class="btn btn-sm rounded-pill <?= ($filterStatus === 'CANCELLED') ? 'btn-danger' : 'btn-light border' ?>">Cancelled</a>
    </div>

    <div class="small text-muted">
      Total: <strong><?= count($orders) ?></strong> orders
    </div>
  </div>

  <!-- Orders Table -->
  <div class="table-responsive">
    <table class="table table-hover align-middle small mb-0">
      <thead class="table-light">
        <tr>
          <th>Order</th>
          <th>Customer Details</th>
          <th>Delivery Destination</th>
          <th>Ordered Dishes</th>
          <th>Bill Amount</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr>
            <td colspan="7" class="text-center py-4 text-muted">
              No orders found matching the filter criteria.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($orders as $order): ?>
            <?php $items = $orderItemsGrouped[$order['order_id']] ?? []; ?>
            <tr>
              <td>
                <div class="fw-bold font-monospace text-dark">#ZIP-<?= (int)$order['order_id'] ?></div>
                <div class="small text-muted"><?= date('d M, h:i A', strtotime($order['placed_at'])) ?></div>
                <?php if (!empty($order['delivery_otp'])): ?>
                  <span class="badge bg-mint text-dark font-monospace mt-1">OTP: <?= e($order['delivery_otp']) ?></span>
                <?php endif; ?>
              </td>

              <td>
                <div class="fw-bold text-dark"><?= e($order['full_name']) ?></div>
                <div class="small text-muted"><?= e($order['phone_number']) ?></div>
                <div class="small text-muted"><?= e($order['email']) ?></div>
              </td>

              <td style="max-width: 200px;">
                <div class="text-truncate text-dark fw-semibold" title="<?= e($order['address_line']) ?>"><?= e($order['address_line'] ?: 'Indiranagar, Bangalore') ?></div>
                <div class="small text-muted">PIN: <?= e($order['pincode'] ?: '560038') ?></div>
              </td>

              <td style="max-width: 220px;">
                <div class="d-flex flex-column gap-1">
                  <?php foreach ($items as $it): ?>
                    <div class="small text-truncate">
                      <span class="badge <?= $it['is_veg'] ? 'bg-success' : 'bg-danger' ?> px-1" style="font-size:0.6rem;"><?= (int)$it['quantity'] ?>x</span>
                      <span><?= e($it['item_name']) ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </td>

              <td>
                <div class="fw-bold text-forest fs-6"><?= format_inr($order['total_amount']) ?></div>
                <span class="badge bg-light text-dark border"><?= e($order['payment_mode']) ?></span>
              </td>

              <td>
                <?= get_status_badge($order['order_status']) ?>
              </td>

              <td>
                <div class="d-flex flex-column gap-1">
                  <button type="button" class="btn btn-sm btn-outline-success rounded-pill" data-bs-toggle="modal" data-bs-target="#statusModal<?= (int)$order['order_id'] ?>">
                    Advance Status
                  </button>
                  <a href="<?= CUSTOMER_URL ?>/invoice.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-sm btn-light border rounded-pill text-muted" target="_blank">
                    Tax Invoice
                  </a>
                </div>

                <!-- Status Update Modal -->
                <div class="modal fade" id="statusModal<?= (int)$order['order_id'] ?>" tabindex="-1">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content rounded-4 border-0 shadow-lg text-start">
                      <div class="modal-header border-bottom">
                        <h5 class="modal-title fw-bold">Update Order #ZIP-<?= (int)$order['order_id'] ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>

                      <form method="POST" action="<?= ADMIN_URL ?>/orders.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_order_status">
                        <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">

                        <div class="modal-body p-4">
                          <div class="mb-3">
                            <label class="form-label small fw-bold">Current Order Status</label>
                            <div><?= get_status_badge($order['order_status']) ?></div>
                          </div>

                          <div class="mb-3">
                            <label class="form-label small fw-bold">New Target Lifecycle Status *</label>
                            <select name="new_status" class="form-select rounded-3">
                              <option value="CONFIRMED" <?= ($order['order_status'] === 'CONFIRMED') ? 'selected' : '' ?>>CONFIRMED (Queued for Kitchen)</option>
                              <option value="COOKING" <?= ($order['order_status'] === 'COOKING') ? 'selected' : '' ?>>COOKING (Chef is preparing food)</option>
                              <option value="READY" <?= ($order['order_status'] === 'READY') ? 'selected' : '' ?>>READY (Ready for courier pickup)</option>
                              <option value="OUT_FOR_DELIVERY" <?= ($order['order_status'] === 'OUT_FOR_DELIVERY') ? 'selected' : '' ?>>OUT_FOR_DELIVERY (Courier dispatched)</option>
                              <option value="DELIVERED" <?= ($order['order_status'] === 'DELIVERED') ? 'selected' : '' ?>>DELIVERED (Handshake OTP verified)</option>
                              <option value="CANCELLED" <?= ($order['order_status'] === 'CANCELLED') ? 'selected' : '' ?>>CANCELLED (Refund triggered)</option>
                            </select>
                          </div>

                          <div class="mb-3">
                            <label class="form-label small fw-bold">Assigned Courier Partner</label>
                            <input type="text" name="rider_name" class="form-control rounded-3" value="<?= e($order['delivery_partner_name'] ?: 'Suresh Kumar (Zippy Express)') ?>">
                          </div>

                          <div class="p-3 rounded-3 bg-light border small">
                            <strong>Note:</strong> Every status modification triggers an immutable audit log entry into <code>order_audit_trail</code> for compliance.
                          </div>
                        </div>

                        <div class="modal-footer border-top">
                          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
                            Commit Status Update
                          </button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
