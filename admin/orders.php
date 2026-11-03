<?php
/**
 * Admin Comprehensive Live Orders Hub & Dispatch Management
 * Zippy - Online Food Ordering Platform
 * Student: Pooja JP | MCSP-232
 */

$pageTitle = 'Orders Management & Dispatch';
$activePage = 'orders';

// Bootstrap core libraries before HTML output (prevents "headers already sent")
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$currentUser = current_user();

// Current filter and search parameters
$filterStatus = clean_input($_GET['status'] ?? 'LIVE');
$searchQuery = clean_input($_GET['search'] ?? '');
$paymentFilter = clean_input($_GET['payment'] ?? '');

// Handle Status Update POST (Centralized via transition_order_status())
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
    } else {
        $orderId = (int)$_POST['order_id'];
        $newStatus = clean_input($_POST['new_status']);
        $assignedRider = clean_input($_POST['rider_name'] ?? '');

        $result = transition_order_status($orderId, $newStatus, $assignedRider, $currentUser['id'] ?? null);
        if ($result['success']) {
            set_flash('success', $result['message']);
        } else {
            set_flash('danger', $result['error'] ?? 'Failed to update order status.');
        }

        $redirectUrl = ADMIN_URL . "/orders.php" . (!empty($filterStatus) ? "?status=" . urlencode($filterStatus) : '');
        if (!empty($searchQuery)) $redirectUrl .= "&search=" . urlencode($searchQuery);
        header("Location: " . $redirectUrl);
        exit;
    }
}

// Handle Multi-Select Batch Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batch_status_update') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
    } else {
        $orderIds = isset($_POST['order_ids']) ? array_filter(array_map('intval', (array)$_POST['order_ids'])) : [];
        $newStatus = clean_input($_POST['new_status'] ?? '');
        $assignedRider = clean_input($_POST['rider_name'] ?? '');

        if (!empty($orderIds) && !empty($newStatus)) {
            $successCount = 0;
            $failedOrders = [];
            foreach ($orderIds as $oid) {
                $res = transition_order_status($oid, $newStatus, $assignedRider, $currentUser['id'] ?? null);
                if ($res['success']) {
                    $successCount++;
                } else {
                    $failedOrders[] = "#ZIP-{$oid}";
                }
            }

            if ($successCount > 0) {
                set_flash('success', "Batch action completed: {$successCount} order(s) successfully transitioned to {$newStatus}.");
            }
            if (!empty($failedOrders)) {
                set_flash('warning', "Notice: " . count($failedOrders) . " order(s) could not transition to {$newStatus} (" . implode(', ', array_slice($failedOrders, 0, 4)) . ").");
            }
        } else {
            set_flash('info', 'Please select at least one order and target status for batch update.');
        }

        $redirectUrl = ADMIN_URL . "/orders.php" . (!empty($filterStatus) ? "?status=" . urlencode($filterStatus) : '');
        if (!empty($searchQuery)) $redirectUrl .= "&search=" . urlencode($searchQuery);
        header("Location: " . $redirectUrl);
        exit;
    }
}

// Handle 1-Click Mark COD Cash Collected
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_cod_paid') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
    } else {
        $orderId = (int)$_POST['order_id'];
        $order = db_fetch_one("
            SELECT o.*, p.payment_mode, p.payment_status, p.amount_paid 
            FROM `orders` o 
            LEFT JOIN `payments` p ON o.order_id = p.order_id 
            WHERE o.order_id = :id
        ", [':id' => $orderId]);

        if ($order) {
            db_query("
                UPDATE `payments` 
                SET `payment_status` = 'SUCCESSFUL', `payment_timestamp` = NOW() 
                WHERE `order_id` = :id
            ", [':id' => $orderId]);

            $existingTx = db_fetch_one("
                SELECT * FROM `money_transactions` 
                WHERE `order_id` = :id AND `txn_category` = 'CUSTOMER_PAYMENT_COD'
            ", [':id' => $orderId]);

            if ($existingTx) {
                db_query("
                    UPDATE `money_transactions` 
                    SET `status` = 'COMPLETED', 
                        `handled_by` = :hby, 
                        `notes` = CONCAT(COALESCE(notes, ''), ' [Cash verified and marked collected by Store Admin]') 
                    WHERE `txn_id` = :tid
                ", [
                    ':hby' => $currentUser['name'] ?? 'Store Admin',
                    ':tid' => $existingTx['txn_id']
                ]);
            } else {
                record_money_transaction(
                    $orderId,
                    (int)$order['user_id'],
                    'CUSTOMER_PAYMENT_COD',
                    'INWARD',
                    'COD_CASH',
                    (float)$order['total_amount'],
                    'COMPLETED',
                    'COD-DIRECT-' . $orderId,
                    $currentUser['name'] ?? 'Store Admin',
                    "Order #ZIP-{$orderId} Cash on Delivery collected by Store Admin."
                );
            }

            set_flash('success', "Cash payment of " . format_inr($order['total_amount']) . " for Order #ZIP-{$orderId} marked as COLLECTED.");
            $redirectUrl = ADMIN_URL . "/orders.php" . (!empty($filterStatus) ? "?status=" . urlencode($filterStatus) : '');
            if (!empty($searchQuery)) $redirectUrl .= "&search=" . urlencode($searchQuery);
            header("Location: " . $redirectUrl);
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';

// 1. Live Counters (Consolidated single query via get_order_kpis(), reused from header.php)
$cntAll = $orderKpis['total'];
$cntLive = $orderKpis['live'];
$cntPreparing = $orderKpis['preparing'];
$cntReady = $orderKpis['ready'];
$cntDelivering = $orderKpis['delivering'];
$cntDelivered = $orderKpis['delivered'];
$cntCancelled = $orderKpis['cancelled'];

// 3. Build Dynamic Filtering Query
$where = "1=1";
$params = [];

if ($filterStatus === 'LIVE') {
    $where .= " AND o.order_status IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY')";
} elseif ($filterStatus === 'PREPARING') {
    $where .= " AND o.order_status IN ('CONFIRMED', 'COOKING')";
} elseif ($filterStatus === 'COOKING') {
    $where .= " AND o.order_status = 'COOKING'";
} elseif ($filterStatus === 'CONFIRMED') {
    $where .= " AND o.order_status = 'CONFIRMED'";
} elseif ($filterStatus === 'READY') {
    $where .= " AND o.order_status = 'READY'";
} elseif ($filterStatus === 'OUT_FOR_DELIVERY' || $filterStatus === 'DELIVERING') {
    $where .= " AND o.order_status = 'OUT_FOR_DELIVERY'";
} elseif ($filterStatus === 'DELIVERED') {
    $where .= " AND o.order_status = 'DELIVERED'";
} elseif ($filterStatus === 'CANCELLED') {
    $where .= " AND o.order_status = 'CANCELLED'";
} elseif ($filterStatus === 'ALL') {
    // Show all
}

if (!empty($paymentFilter)) {
    $where .= " AND p.payment_mode = :pmode";
    $params[':pmode'] = $paymentFilter;
}

if (!empty($searchQuery)) {
    $where .= " AND (
        o.order_id = :sq_id 
        OR u.full_name LIKE :sq_like 
        OR u.phone_number LIKE :sq_like 
        OR u.email LIKE :sq_like 
        OR a.address_line LIKE :sq_like 
        OR a.pincode LIKE :sq_like 
        OR d.delivery_partner_name LIKE :sq_like 
        OR d.delivery_otp LIKE :sq_like
    )";
    $cleanId = (int)preg_replace('/[^0-9]/', '', $searchQuery);
    $params[':sq_id'] = $cleanId;
    $params[':sq_like'] = "%" . $searchQuery . "%";
}

$orders = db_fetch_all("
    SELECT o.*, 
           u.full_name, u.phone_number, u.email,
           a.address_line, a.landmark, a.pincode,
           p.payment_mode, p.payment_status, p.transaction_reference,
           d.rider_id, d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp, d.delivery_status, d.dispatched_at,
           TIMESTAMPDIFF(MINUTE, o.placed_at, NOW()) AS elapsed_minutes,
           TIMESTAMPDIFF(MINUTE, COALESCE(d.dispatched_at, o.placed_at), NOW()) AS transit_minutes
    FROM `orders` o
    JOIN `users` u ON o.user_id = u.user_id
    LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    WHERE $where
    ORDER BY o.placed_at DESC
", $params);

$codPendingOrders = (int)(db_fetch_val(
    "SELECT COUNT(*) FROM `orders` o LEFT JOIN `payments` p ON o.order_id = p.order_id WHERE p.payment_mode = 'COD' AND (p.payment_status IS NULL OR p.payment_status != 'SUCCESSFUL') AND o.order_status NOT IN ('DELIVERED', 'CANCELLED')"
) ?: 0);

$delayedOrders = (int)(db_fetch_val(
    "SELECT COUNT(*) FROM `orders` WHERE order_status IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY') AND TIMESTAMPDIFF(MINUTE, placed_at, NOW()) > 45"
) ?: 0);

$registeredRiders = get_all_delivery_riders();

// Fetch items for all queried orders
$orderItemsGrouped = [];
if (!empty($orders)) {
    $orderIds = array_column($orders, 'order_id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemsRaw = db_fetch_all("
        SELECT oi.*, m.item_name, m.is_veg, m.prep_time_minutes 
        FROM `order_items` oi
        JOIN `menu_items` m ON oi.item_id = m.item_id
        WHERE oi.order_id IN ($placeholders)
    ", $orderIds);

    foreach ($itemsRaw as $it) {
        $orderItemsGrouped[$it['order_id']][] = $it;
    }
}
?>

<!-- ===========================================================================
     ORDERS LEDGER & LIVE STATUS SYNC
     =========================================================================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
  <div>
    <h4 class="fw-bold text-dark font-outfit mb-0"><i class="bi bi-receipt text-success me-2"></i>Orders ledger</h4>
    <div class="small text-muted">Search and manage each order from one sortable, filterable list.</div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <span class="badge bg-light text-forest border px-3 py-2 rounded-pill font-monospace shadow-xs" id="adminLiveSyncBadge">
      <span class="live-pulse-dot green me-1"></span> Live Sync: <span id="adminSyncTimer">4s</span>
    </span>
    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-xs" id="btnAdminChimeToggle" onclick="toggleAdminChime()">
      <i class="bi bi-bell-fill me-1" id="adminChimeIcon"></i> Sound: <span id="adminChimeLabel">ON</span>
    </button>
  </div>
</div>

<!-- ===========================================================================
     MAIN LIVE ORDERS MANAGEMENT CONSOLE
     =========================================================================== -->
<div class="card border rounded-4 shadow-xs p-4 bg-white mb-4" style="border-color:#E2ECE5 !important;">
  
  <!-- Filter Pills Bar & View Mode Toggle -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    
    <!-- Stage Filter Pills -->
    <div class="d-flex flex-wrap align-items-center gap-2">
      <span class="small fw-bold text-muted me-1">Filter Stage:</span>
      <a href="<?= ADMIN_URL ?>/orders.php?status=LIVE<?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" 
         class="btn btn-sm rounded-pill <?= ($filterStatus === 'LIVE') ? 'btn-dark' : 'btn-light border' ?>">
        ⚡ Live Active (<span id="ordersLiveCount"><?= $cntLive ?></span>)
      </a>
      <a href="<?= ADMIN_URL ?>/orders.php?status=PREPARING<?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" 
         class="btn btn-sm rounded-pill <?= ($filterStatus === 'PREPARING') ? 'btn-warning text-dark' : 'btn-light border' ?>">
        🍳 Being Prepared (<?= $cntPreparing ?>)
      </a>
      <a href="<?= ADMIN_URL ?>/orders.php?status=READY<?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" 
         class="btn btn-sm rounded-pill <?= ($filterStatus === 'READY') ? 'btn-success' : 'btn-light border' ?>">
        📦 Ready for Pickup (<span id="ordersReadyCount"><?= $cntReady ?></span>)
      </a>
      <a href="<?= ADMIN_URL ?>/orders.php?status=OUT_FOR_DELIVERY<?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" 
         class="btn btn-sm rounded-pill <?= ($filterStatus === 'OUT_FOR_DELIVERY') ? 'btn-primary' : 'btn-light border' ?>">
        🚴 Out for Delivery (<span id="ordersTransitCount"><?= $cntDelivering ?></span>)
      </a>
      <a href="<?= ADMIN_URL ?>/orders.php?status=DELIVERED<?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" 
         class="btn btn-sm rounded-pill <?= ($filterStatus === 'DELIVERED') ? 'btn-secondary text-white' : 'btn-light border' ?>">
        ✅ Delivered (<?= $cntDelivered ?>)
      </a>
      <a href="<?= ADMIN_URL ?>/orders.php?status=CANCELLED<?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" 
         class="btn btn-sm rounded-pill <?= ($filterStatus === 'CANCELLED') ? 'btn-danger' : 'btn-light border' ?>">
        ❌ Cancelled (<?= $cntCancelled ?>)
      </a>
      <a href="<?= ADMIN_URL ?>/orders.php?status=ALL<?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" 
         class="btn btn-sm rounded-pill <?= ($filterStatus === 'ALL') ? 'btn-dark' : 'btn-light border' ?>">
        All (<?= $cntAll ?>)
      </a>
    </div>

    <!-- Ledger utilities -->
    <div class="d-flex align-items-center gap-2">
      <a href="<?= ADMIN_URL ?>/audit-logs.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
        <i class="bi bi-journal-text me-1"></i> Audit Trail
      </a>
    </div>

  </div>

  <!-- Real-time Live Search & Filter Inputs -->
  <div class="row g-3 align-items-center mb-4">
    <div class="col-md-7 col-lg-8">
      <div class="admin-search-box d-flex align-items-center gap-2">
        <i class="bi bi-search text-muted"></i>
        <input type="text" id="orderLiveSearchInput" 
               value="<?= e($searchQuery) ?>" 
               placeholder="Instant Search: Order ID (#ZIP-1035), Customer Name, Phone, Dish Name (Biryani...), Address, Rider, or OTP..." 
               autocomplete="off">
        <button type="button" class="btn btn-link text-muted p-0 <?= empty($searchQuery) ? 'd-none' : '' ?>" id="clearOrderSearchBtn" title="Clear search">
          <i class="bi bi-x-circle-fill"></i>
        </button>
      </div>
    </div>

    <div class="col-md-5 col-lg-4">
      <div class="d-flex align-items-center gap-2 justify-content-md-end">
        <select class="form-select form-select-sm rounded-pill" id="paymentModeFilter" style="max-width: 170px;">
          <option value="">All Payment Modes</option>
          <option value="UPI" <?= ($paymentFilter === 'UPI') ? 'selected' : '' ?>>UPI / Online</option>
          <option value="COD" <?= ($paymentFilter === 'COD') ? 'selected' : '' ?>>Cash on Delivery</option>
          <option value="CREDIT_CARD" <?= ($paymentFilter === 'CREDIT_CARD') ? 'selected' : '' ?>>Credit Card</option>
          <option value="DEBIT_CARD" <?= ($paymentFilter === 'DEBIT_CARD') ? 'selected' : '' ?>>Debit Card</option>
        </select>

        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill small" id="ordersCounterBadge">
          <span id="filteredOrdersCount"><?= count($orders) ?></span> orders
        </span>
      </div>
    </div>
  </div>

  <div class="orders-kpi-strip row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
      <div class="orders-metric metric-live">
        <span class="metric-kicker">Live active</span>
        <strong><?= number_format($cntLive) ?></strong>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="orders-metric metric-prep">
        <span class="metric-kicker">Waiting prep</span>
        <strong><?= number_format($cntPreparing) ?></strong>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="orders-metric metric-ready">
        <span class="metric-kicker">Ready pickup</span>
        <strong><?= number_format($cntReady) ?></strong>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="orders-metric metric-delivery">
        <span class="metric-kicker">Out for delivery</span>
        <strong><?= number_format($cntDelivering) ?></strong>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="orders-metric metric-cod">
        <span class="metric-kicker">COD pending</span>
        <strong><?= number_format($codPendingOrders) ?></strong>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="orders-metric metric-delay">
        <span class="metric-kicker">Delayed</span>
        <strong><?= number_format($delayedOrders) ?></strong>
      </div>
    </div>
  </div>

  <!-- The ledger below is the only order detail view; legacy cards are not rendered. -->
  <?php if (false): ?>
  <div id="ordersCardsViewContainer">
    
    <?php if (empty($orders)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 opacity-50 mb-2 d-block"></i>
        <h6 class="fw-bold">No orders found matching the filter criteria.</h6>
        <p class="small text-muted">Try clearing the search query or switching to another status tab.</p>
        <a href="<?= ADMIN_URL ?>/orders.php?status=LIVE" class="btn btn-sm btn-outline-success rounded-pill px-3">
          View All Live Orders
        </a>
      </div>
    <?php else: ?>
      
      <div class="row g-3" id="ordersCardsGrid">
        <?php foreach ($orders as $order): ?>
          <?php
            $items = $orderItemsGrouped[$order['order_id']] ?? [];
            $status = $order['order_status'];
            
            // Determine visual card stage class
            $stageClass = match($status) {
              'CONFIRMED', 'COOKING' => 'stage-preparing',
              'READY' => 'stage-ready',
              'OUT_FOR_DELIVERY' => 'stage-delivering',
              'DELIVERED' => 'stage-delivered',
              'CANCELLED' => 'stage-cancelled',
              default => 'stage-preparing'
            };

            // Calculate Dynamic SLA Variance
            $maxPrep = 15;
            if (!empty($items)) {
              $preps = array_filter(array_column($items, 'prep_time_minutes'));
              if (!empty($preps)) $maxPrep = max($preps);
            }
            $slaDiff = (int)$order['elapsed_minutes'] - $maxPrep;
            if (in_array($status, ['CONFIRMED', 'COOKING'])) {
              if ($slaDiff > 15) {
                $stageClass .= ' sla-breach-critical';
              } elseif ($slaDiff > 0) {
                $stageClass .= ' sla-breach-overdue';
              } elseif ($slaDiff >= -5) {
                $stageClass .= ' sla-approaching';
              }
            }

            // Search string aggregation for zero-latency client filtering
            $itemNames = array_map(fn($it) => $it['item_name'], $items);
            $cardSearchData = strtolower("ZIP-{$order['order_id']} {$order['full_name']} {$order['phone_number']} {$order['email']} {$order['address_line']} {$order['pincode']} {$order['delivery_partner_name']} {$order['delivery_otp']} {$order['payment_mode']} {$status} " . implode(' ', $itemNames) . " " . ($order['order_notes'] ?? ''));
          ?>
          <div class="col-md-6 col-xl-4 order-card-item-col" 
               data-order-id="<?= (int)$order['order_id'] ?>"
               data-status="<?= e($status) ?>"
               data-payment="<?= e($order['payment_mode']) ?>"
               data-search="<?= e($cardSearchData) ?>">
            
            <div class="order-live-card p-3 <?= $stageClass ?>">
              
              <!-- Card Header -->
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <input type="checkbox" class="form-check-input order-select-checkbox m-0" value="<?= (int)$order['order_id'] ?>" title="Select order for batch operations" style="cursor: pointer; width: 1.15em; height: 1.15em;">
                  <span class="badge bg-dark fw-bold font-monospace">#ZIP-<?= (int)$order['order_id'] ?></span>
                  <?= get_status_badge($status) ?>
                  <?php if (in_array($status, ['CONFIRMED', 'COOKING'])): ?>
                    <?php if ($slaDiff > 15): ?>
                      <span class="badge rounded-pill bg-danger text-white" style="font-size:0.68rem;" title="Critical preparation delay"><i class="bi bi-exclamation-octagon-fill me-1"></i>+<?= $slaDiff ?>m Critical</span>
                    <?php elseif ($slaDiff > 0): ?>
                      <span class="badge rounded-pill bg-warning text-dark border border-warning" style="font-size:0.68rem;" title="Preparation time exceeded"><i class="bi bi-alarm-fill me-1"></i>+<?= $slaDiff ?>m Overdue</span>
                    <?php elseif ($slaDiff >= -5): ?>
                      <span class="badge rounded-pill bg-info-subtle text-info-emphasis border border-info-subtle" style="font-size:0.68rem;"><i class="bi bi-hourglass-split me-1"></i><?= abs($slaDiff) ?>m left</span>
                    <?php else: ?>
                      <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle" style="font-size:0.68rem;"><i class="bi bi-check-circle-fill me-1"></i>On Track</span>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
                <span class="small text-muted font-monospace" style="font-size:0.75rem;">
                  <?= date('h:i A', strtotime($order['placed_at'])) ?> &bull; <?= format_minutes_ago((int)$order['elapsed_minutes']) ?>
                </span>
              </div>

              <!-- Customer Info Bar -->
              <div class="p-2 rounded-3 bg-light border mb-2 small">
                <div class="d-flex justify-content-between align-items-center">
                  <div class="fw-bold text-dark text-truncate" title="<?= e($order['full_name']) ?>">
                    <i class="bi bi-person me-1 text-muted"></i><?= e($order['full_name']) ?>
                  </div>
                  <a href="tel:<?= e($order['phone_number']) ?>" class="text-success text-decoration-none fw-bold small">
                    <i class="bi bi-telephone me-1"></i><?= e($order['phone_number']) ?>
                  </a>
                </div>
                <div class="text-muted text-truncate mt-1" style="font-size:0.75rem;" title="<?= e($order['address_line']) ?>">
                  <i class="bi bi-geo-alt me-1 text-danger"></i><?= e($order['address_line'] ?: 'Indiranagar, Bangalore') ?> (PIN: <?= e($order['pincode'] ?: '560038') ?>)
                </div>
              </div>

              <!-- ==============================================
                   WHAT IS BEING PREPARED (Kitchen Stage Details)
                   ============================================== -->
              <?php if (in_array($status, ['CONFIRMED', 'COOKING', 'READY'])): ?>
                <div class="p-2 rounded-3 mb-2 small flex-grow-1" style="background:#FFFDF7; border: 1px solid #FDE68A;">
                  <div class="d-flex align-items-center justify-content-between mb-1 pb-1 border-bottom">
                    <span class="fw-bold text-dark small text-uppercase" style="font-size:0.75rem;">
                      <i class="fa-solid fa-fire text-warning me-1"></i>Items Being Prepared (<?= count($items) ?>):
                    </span>
                    <span class="badge bg-warning bg-opacity-25 text-dark" style="font-size:0.65rem;">
                      <?= ($status === 'COOKING') ? '🔥 On Stove' : '⏳ Queued' ?>
                    </span>
                  </div>

                  <div class="d-flex flex-column gap-1">
                    <?php foreach ($items as $it): ?>
                      <div class="d-flex align-items-center justify-content-between">
                        <span class="text-truncate">
                          <span class="badge <?= $it['is_veg'] ? 'bg-success' : 'bg-danger' ?> px-1 me-1" style="font-size:0.65rem;">
                            <?= (int)$it['quantity'] ?>x
                          </span>
                          <span class="text-dark fw-semibold"><?= e($it['item_name']) ?></span>
                        </span>
                        <span class="text-muted font-monospace" style="font-size:0.75rem;"><?= format_inr($it['line_total']) ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>

                  <?php if (!empty($order['order_notes'])): ?>
                    <div class="mt-2 pt-2 border-top text-warning fw-semibold" style="font-size:0.75rem;">
                      <i class="bi bi-chat-quote me-1"></i>Note: <?= e($order['order_notes']) ?>
                    </div>
                  <?php endif; ?>
                </div>

              <!-- ==============================================
                   WHAT IS BEING DELIVERED (Transit Stage Details)
                   ============================================== -->
              <?php elseif ($status === 'OUT_FOR_DELIVERY'): ?>
                <div class="p-2 rounded-3 mb-2 small flex-grow-1" style="background:#F0F7FF; border: 1px solid #BFDBFE;">
                  <div class="d-flex align-items-center justify-content-between mb-1 pb-1 border-bottom">
                    <span class="fw-bold text-primary small text-uppercase" style="font-size:0.75rem;">
                      <i class="bi bi-bicycle text-primary me-1"></i>Courier In Transit:
                    </span>
                    <span class="badge bg-primary text-white" style="font-size:0.65rem;">
                      <?= (int)$order['transit_minutes'] ?>m in transit
                    </span>
                  </div>

                  <!-- Rider Details -->
                  <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-dark fw-bold">
                      <i class="bi bi-person-badge me-1 text-primary"></i><?= e($order['delivery_partner_name'] ?: 'Suresh Kumar (Rider)') ?>
                    </span>
                    <a href="tel:<?= e($order['delivery_partner_phone'] ?: '+919876543210') ?>" class="text-primary text-decoration-none fw-bold small">
                      <i class="bi bi-telephone-fill me-1"></i>Call Rider
                    </a>
                  </div>

                  <!-- Items Summary -->
                  <div class="text-muted small text-truncate mb-2">
                    Package: <?= implode(', ', array_map(fn($it) => $it['quantity'] . 'x ' . $it['item_name'], $items)) ?>
                  </div>

                  <!-- Delivery Handshake OTP -->
                  <div class="d-flex align-items-center justify-content-between p-2 rounded-3 bg-white border">
                    <div>
                      <span class="text-muted small d-block" style="font-size:0.7rem; font-weight:700;">HANDSHAKE OTP:</span>
                      <span class="delivery-otp-pill"><?= e($order['delivery_otp'] ?: '482910') ?></span>
                    </div>
                    <div class="text-end">
                      <span class="badge bg-light text-dark border"><?= e($order['payment_mode']) ?></span>
                    </div>
                  </div>
                </div>

              <!-- ==============================================
                   COMPLETED OR CANCELLED STAGE DETAILS
                   ============================================== -->
              <?php else: ?>
                <div class="p-2 rounded-3 bg-light border mb-2 small flex-grow-1">
                  <div class="fw-bold text-dark small text-uppercase mb-1" style="font-size:0.75rem;">
                    <i class="bi bi-receipt me-1 text-forest"></i>Dishes In Order (<?= count($items) ?>):
                  </div>
                  <div class="d-flex flex-column gap-1">
                    <?php foreach ($items as $it): ?>
                      <div class="d-flex align-items-center justify-content-between">
                        <span class="text-truncate">
                          <span class="badge <?= $it['is_veg'] ? 'bg-success' : 'bg-danger' ?> px-1 me-1" style="font-size:0.65rem;">
                            <?= (int)$it['quantity'] ?>x
                          </span>
                          <span class="text-dark"><?= e($it['item_name']) ?></span>
                        </span>
                        <span class="text-muted font-monospace" style="font-size:0.75rem;"><?= format_inr($it['line_total']) ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <?php if (!empty($order['delivery_otp'])): ?>
                    <div class="mt-2 pt-1 border-top small text-muted">
                      Handshake OTP: <span class="font-monospace fw-bold"><?= e($order['delivery_otp']) ?></span>
                      <?php if (!empty($order['delivery_partner_name'])): ?>
                        • Rider: <?= e($order['delivery_partner_name']) ?>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <!-- Card Financial & Actions Footer -->
              <div class="pt-2 border-top d-flex flex-wrap align-items-center justify-content-between gap-2 mt-auto">
                <div>
                  <div class="fw-bold text-forest fs-6"><?= format_inr($order['total_amount']) ?></div>
                  <div class="d-flex align-items-center gap-1 mt-1 flex-wrap">
                    <?php if ($order['payment_mode'] === 'COD'): ?>
                      <?php if ($order['payment_status'] === 'SUCCESSFUL'): ?>
                        <span class="badge bg-success text-white" style="font-size:0.68rem;">
                          <i class="bi bi-cash-stack me-1"></i>COD CASH PAID (Collected)
                        </span>
                      <?php else: ?>
                        <span class="badge bg-warning text-dark border border-warning" style="font-size:0.68rem;" title="Collect cash at delivery">
                          <i class="bi bi-hourglass-split me-1"></i>COD PENDING (Collect Cash)
                        </span>
                        <form method="POST" action="<?= ADMIN_URL ?>/orders.php" class="d-inline m-0" onsubmit="return confirm('Mark cash of <?= format_inr($order['total_amount']) ?> as collected for #ZIP-<?= (int)$order['order_id'] ?>?');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="mark_cod_paid">
                          <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                          <button type="submit" class="btn btn-xs btn-outline-success py-0 px-2 rounded-pill fw-bold" style="font-size:0.65rem;" title="Mark Cash Collected">
                            Collect
                          </button>
                        </form>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="badge bg-forest text-white" style="font-size:0.68rem;">
                        <i class="bi bi-shield-check me-1"></i>ONLINE PAID (<?= e($order['payment_mode']) ?>)
                      </span>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="d-flex align-items-center gap-1">
                  <!-- 1-Click Quick Status Advance Button -->
                  <?php if ($status === 'CONFIRMED'): ?>
                    <form method="POST" action="<?= ADMIN_URL ?>/orders.php" class="m-0">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update_order_status">
                      <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                      <input type="hidden" name="new_status" value="COOKING">
                      <button type="submit" class="btn btn-warning btn-sm rounded-pill fw-bold px-2 py-1" style="font-size:0.78rem;">
                        <i class="fa-solid fa-fire me-1"></i>Start Cook
                      </button>
                    </form>
                  <?php elseif ($status === 'COOKING'): ?>
                    <form method="POST" action="<?= ADMIN_URL ?>/orders.php" class="m-0">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update_order_status">
                      <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                      <input type="hidden" name="new_status" value="READY">
                      <button type="submit" class="btn btn-primary btn-sm rounded-pill fw-bold px-2 py-1" style="font-size:0.78rem;">
                        <i class="bi bi-box-seam me-1"></i>Mark Ready
                      </button>
                    </form>
                  <?php elseif ($status === 'READY'): ?>
                    <form method="POST" action="<?= ADMIN_URL ?>/orders.php" class="m-0">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update_order_status">
                      <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                      <input type="hidden" name="new_status" value="OUT_FOR_DELIVERY">
                      <button type="submit" class="btn btn-success btn-sm rounded-pill fw-bold px-2 py-1" style="font-size:0.78rem; background:#2E6F40; border-color:#2E6F40;">
                        <i class="bi bi-bicycle me-1"></i>Dispatch
                      </button>
                    </form>
                  <?php elseif ($status === 'OUT_FOR_DELIVERY'): ?>
                    <form method="POST" action="<?= ADMIN_URL ?>/orders.php" class="m-0">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update_order_status">
                      <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                      <input type="hidden" name="new_status" value="DELIVERED">
                      <button type="submit" class="btn btn-success btn-sm rounded-pill fw-bold px-2 py-1" style="font-size:0.78rem;">
                        <i class="bi bi-check2-circle me-1"></i>Delivered
                      </button>
                    </form>
                  <?php endif; ?>

                  <!-- Modal Button for deep status change -->
                  <?php if ($status === 'OUT_FOR_DELIVERY'): ?>
                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-2 py-1" style="font-size:0.78rem;" 
                            onclick="openAdminGpsModal(<?= (int)$order['order_id'] ?>, '<?= e(addslashes($order['full_name'])) ?>', '<?= e(addslashes($order['delivery_partner_name'] ?: 'Rider')) ?>', '<?= e(addslashes($order['delivery_otp'] ?: '')) ?>')" title="Track Live GPS Route">
                      <i class="fa-solid fa-location-arrow me-1"></i>GPS
                    </button>
                  <?php endif; ?>
                  <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-2 py-1" style="font-size:0.78rem;" 
                          data-bs-toggle="modal" data-bs-target="#statusModal<?= (int)$order['order_id'] ?>" title="Manage Status & Courier">
                    <i class="bi bi-gear"></i>
                  </button>

                  <!-- Tax Invoice -->
                  <a href="<?= CUSTOMER_URL ?>/invoice.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-light border btn-sm rounded-pill px-2 py-1 text-muted" style="font-size:0.78rem;" target="_blank" title="Tax Invoice">
                    <i class="bi bi-file-earmark-text"></i>
                  </a>
                </div>
              </div>

            </div>
          </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>

  </div>

  <?php endif; ?>

  <!-- ===========================================================================
       VIEW B: STRUCTURED DATA TABLE (DETAILED LEDGER VIEW)
       =========================================================================== -->
  <div id="ordersTableViewContainer">
    <div class="table-responsive">
      <table class="table table-hover align-middle small mb-0" id="ordersMasterTable">
        <thead class="table-light">
          <tr>
            <th style="width: 38px;">
              <input type="checkbox" id="selectAllOrdersTable" class="form-check-input" title="Select all orders" style="cursor: pointer;">
            </th>
            <th><button type="button" class="btn btn-link p-0 text-dark text-decoration-none fw-bold" data-sort-column="1">Order <i class="bi bi-arrow-down-up small"></i></button></th>
            <th><button type="button" class="btn btn-link p-0 text-dark text-decoration-none fw-bold" data-sort-column="2">Customer <i class="bi bi-arrow-down-up small"></i></button></th>
            <th><button type="button" class="btn btn-link p-0 text-dark text-decoration-none fw-bold" data-sort-column="3">Delivery destination <i class="bi bi-arrow-down-up small"></i></button></th>
            <th><button type="button" class="btn btn-link p-0 text-dark text-decoration-none fw-bold" data-sort-column="4">Ordered dishes <i class="bi bi-arrow-down-up small"></i></button></th>
            <th><button type="button" class="btn btn-link p-0 text-dark text-decoration-none fw-bold" data-sort-column="5">Bill amount <i class="bi bi-arrow-down-up small"></i></button></th>
            <th><button type="button" class="btn btn-link p-0 text-dark text-decoration-none fw-bold" data-sort-column="6">Status <i class="bi bi-arrow-down-up small"></i></button></th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr>
              <td colspan="8" class="text-center py-4 text-muted">
                No orders found matching the filter criteria.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($orders as $order): ?>
              <?php
                $items = $orderItemsGrouped[$order['order_id']] ?? [];
                $itemNames = array_map(fn($it) => $it['item_name'], $items);
                $rowSearchData = strtolower("ZIP-{$order['order_id']} {$order['full_name']} {$order['phone_number']} {$order['email']} {$order['address_line']} {$order['pincode']} {$order['delivery_partner_name']} {$order['delivery_otp']} {$order['payment_mode']} {$order['order_status']} " . implode(' ', $itemNames));
                $rowRiskClass = '';
                $riskLabel = '';
                $elapsedMinutes = (int)($order['elapsed_minutes'] ?? 0);
                $rowMaxPrep = 15;
                if (!empty($items)) {
                  $preps = array_filter(array_column($items, 'prep_time_minutes'));
                  if (!empty($preps)) {
                    $rowMaxPrep = max($preps);
                  }
                }
                $rowSlaDiff = $elapsedMinutes - $rowMaxPrep;

                if (in_array($order['order_status'], ['CONFIRMED', 'COOKING']) && $rowSlaDiff > 15) {
                  $rowRiskClass = 'order-row-critical';
                  $riskLabel = '<span class="risk-chip danger"><i class="bi bi-exclamation-octagon-fill me-1"></i>Critical</span>';
                } elseif (in_array($order['order_status'], ['CONFIRMED', 'COOKING']) && $rowSlaDiff > 0) {
                  $rowRiskClass = 'order-row-warning';
                  $riskLabel = '<span class="risk-chip warning"><i class="bi bi-alarm-fill me-1"></i>Overdue</span>';
                } elseif ($order['payment_mode'] === 'COD' && ($order['payment_status'] ?? '') !== 'SUCCESSFUL') {
                  $rowRiskClass = 'order-row-warning';
                  $riskLabel = '<span class="risk-chip warning"><i class="bi bi-cash-stack me-1"></i>COD</span>';
                } elseif ($order['order_status'] === 'OUT_FOR_DELIVERY' && $elapsedMinutes > 45) {
                  $rowRiskClass = 'order-row-critical';
                  $riskLabel = '<span class="risk-chip danger"><i class="bi bi-bicycle me-1"></i>Delayed</span>';
                }
              ?>
              <tr class="order-table-row <?= $rowRiskClass ?>" 
                  data-order-id="<?= (int)$order['order_id'] ?>"
                  data-status="<?= e($order['order_status']) ?>"
                  data-payment="<?= e($order['payment_mode']) ?>"
                  data-search="<?= e($rowSearchData) ?>">
                
                <td style="width: 38px;">
                  <input type="checkbox" class="form-check-input order-select-checkbox" value="<?= (int)$order['order_id'] ?>" style="cursor: pointer;">
                </td>

                <td>
                  <div class="fw-bold font-monospace text-dark">#ZIP-<?= (int)$order['order_id'] ?></div>
                  <div class="small text-muted"><?= date('d M, h:i A', strtotime($order['placed_at'])) ?></div>
                  <?php if (!empty($order['delivery_otp'])): ?>
                    <span class="delivery-otp-pill mt-1" style="font-size:0.75rem; padding:2px 6px;">OTP: <?= e($order['delivery_otp']) ?></span>
                  <?php endif; ?>
                </td>

                <td>
                  <div class="fw-bold text-dark"><?= e($order['full_name']) ?></div>
                  <div class="small text-muted"><?= e($order['phone_number']) ?></div>
                  <div class="small text-muted"><?= e($order['email']) ?></div>
                </td>

                <td style="max-width: 220px;">
                  <div class="text-truncate text-dark fw-semibold" title="<?= e($order['address_line']) ?>">
                    <?= e($order['address_line'] ?: 'Indiranagar, Bangalore') ?>
                  </div>
                  <div class="small text-muted">PIN: <?= e($order['pincode'] ?: '560038') ?></div>
                  <?php if (!empty($order['delivery_partner_name'])): ?>
                    <div class="small text-primary mt-1">
                      <i class="bi bi-bicycle me-1"></i><?= e($order['delivery_partner_name']) ?>
                    </div>
                  <?php endif; ?>
                </td>

                <td style="max-width: 240px;">
                  <div class="d-flex flex-column gap-1">
                    <?php foreach ($items as $it): ?>
                      <div class="small text-truncate">
                        <span class="badge <?= $it['is_veg'] ? 'bg-success' : 'bg-danger' ?> px-1" style="font-size:0.6rem;">
                          <?= (int)$it['quantity'] ?>x
                        </span>
                        <span><?= e($it['item_name']) ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </td>

                <td>
                  <div class="fw-bold text-forest fs-6"><?= format_inr($order['total_amount']) ?></div>
                  <div class="mt-1">
                    <?php if ($order['payment_mode'] === 'COD'): ?>
                      <?php if ($order['payment_status'] === 'SUCCESSFUL'): ?>
                        <span class="badge bg-success text-white" style="font-size:0.68rem;">
                          <i class="bi bi-cash-stack me-1"></i>COD Cash Collected
                        </span>
                      <?php else: ?>
                        <span class="badge bg-warning text-dark border border-warning d-block mb-1" style="font-size:0.68rem;">
                          <i class="bi bi-hourglass-split me-1"></i>COD Pending
                        </span>
                        <form method="POST" action="<?= ADMIN_URL ?>/orders.php" class="d-inline m-0" onsubmit="return confirm('Mark cash of <?= format_inr($order['total_amount']) ?> as collected for #ZIP-<?= (int)$order['order_id'] ?>?');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="mark_cod_paid">
                          <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                          <button type="submit" class="btn btn-xs btn-outline-success py-0 px-2 rounded-pill fw-bold" style="font-size:0.65rem;">
                            Mark Paid
                          </button>
                        </form>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="badge bg-forest text-white" style="font-size:0.68rem;">
                        <i class="bi bi-shield-check me-1"></i>Online Paid (<?= e($order['payment_mode']) ?>)
                      </span>
                    <?php endif; ?>
                  </div>
                </td>

                <td>
                  <div class="d-flex flex-column gap-1">
                    <div class="status-badge-wrap"><?= get_status_badge($order['order_status']) ?></div>
                    <?php if (!empty($riskLabel)): ?>
                      <div class="status-risk-wrap"><?= $riskLabel ?></div>
                    <?php endif; ?>
                    <?php if (in_array($order['order_status'], ['CONFIRMED', 'COOKING'])): ?>
                      <?php if ($rowSlaDiff > 15): ?>
                        <span class="risk-chip danger mt-1"><i class="bi bi-exclamation-octagon-fill me-1"></i>+<?= $rowSlaDiff ?>m Critical</span>
                      <?php elseif ($rowSlaDiff > 0): ?>
                        <span class="risk-chip warning mt-1"><i class="bi bi-alarm-fill me-1"></i>+<?= $rowSlaDiff ?>m Overdue</span>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                </td>

                <td>
                  <div class="order-action-stack">
                    <?php if ($order['order_status'] === 'OUT_FOR_DELIVERY'): ?>
                      <button type="button" class="order-action-btn order-action-primary" onclick="openAdminGpsModal(<?= (int)$order['order_id'] ?>, '<?= e(addslashes($order['full_name'])) ?>', '<?= e(addslashes($order['delivery_partner_name'] ?: 'Rider')) ?>', '<?= e(addslashes($order['delivery_otp'] ?: '')) ?>')">
                        <i class="fa-solid fa-location-arrow me-1"></i> GPS
                      </button>
                    <?php endif; ?>
                    <button type="button" class="order-action-btn order-action-primary" data-bs-toggle="modal" data-bs-target="#statusModal<?= (int)$order['order_id'] ?>">
                      Status
                    </button>
                    <a href="<?= CUSTOMER_URL ?>/invoice.php?order_id=<?= (int)$order['order_id'] ?>" class="order-action-btn order-action-secondary" target="_blank">
                      Invoice
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Zero Results Message for Live Search -->
  <div class="text-center py-5 d-none" id="ordersZeroSearchState">
    <i class="bi bi-search fs-1 text-muted opacity-50 mb-2 d-block"></i>
    <h6 class="fw-bold text-dark">No orders match your search keyword</h6>
    <p class="small text-muted mb-3">Try searching by another term such as order ID (#ZIP-..), customer name, dish, or rider.</p>
    <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-3" id="resetOrdersSearchBtn">
      Reset Search Filter
    </button>
  </div>

</div>

<!-- ===========================================================================
     STATUS UPDATE MODALS (ONE FOR EACH ORDER)
     =========================================================================== -->
<?php foreach ($orders as $order): ?>
  <div class="modal fade" id="statusModal<?= (int)$order['order_id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-4 border-0 shadow-lg text-start">
        <div class="modal-header border-bottom">
          <div>
            <h5 class="modal-title fw-bold text-dark mb-0">Manage Order #ZIP-<?= (int)$order['order_id'] ?></h5>
            <span class="small text-muted">Customer: <?= e($order['full_name']) ?> (<?= e($order['phone_number']) ?>)</span>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <form method="POST" action="<?= ADMIN_URL ?>/orders.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_order_status">
          <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">

          <div class="modal-body p-4">
            
            <div class="row g-3 mb-3">
              <div class="col-6">
                <label class="form-label small fw-bold text-muted">Current Lifecycle Status</label>
                <div><?= get_status_badge($order['order_status']) ?></div>
              </div>
              <div class="col-6">
                <label class="form-label small fw-bold text-muted">Handshake OTP</label>
                <div>
                  <span class="delivery-otp-pill"><?= e($order['delivery_otp'] ?: '482910') ?></span>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold">Target Lifecycle Status *</label>
              <select name="new_status" class="form-select rounded-3">
                <option value="CONFIRMED" <?= ($order['order_status'] === 'CONFIRMED') ? 'selected' : '' ?>>CONFIRMED (Queued for Kitchen Prep)</option>
                <option value="COOKING" <?= ($order['order_status'] === 'COOKING') ? 'selected' : '' ?>>COOKING (Chef is actively preparing food)</option>
                <option value="READY" <?= ($order['order_status'] === 'READY') ? 'selected' : '' ?>>READY (Cooked, packaged & ready for pickup)</option>
                <option value="OUT_FOR_DELIVERY" <?= ($order['order_status'] === 'OUT_FOR_DELIVERY') ? 'selected' : '' ?>>OUT_FOR_DELIVERY (Courier dispatched on road)</option>
                <option value="DELIVERED" <?= ($order['order_status'] === 'DELIVERED') ? 'selected' : '' ?>>DELIVERED (Handshake OTP verified by courier)</option>
                <option value="CANCELLED" <?= ($order['order_status'] === 'CANCELLED') ? 'selected' : '' ?>>CANCELLED (Inventory restored & refund triggered)</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold">Assigned Courier Partner</label>
              <select name="rider_name" class="form-select rounded-3">
                <option value="">-- Select Courier Rider --</option>
                <?php foreach ($registeredRiders as $r): ?>
                  <?php
                    $isSelected = (stripos($order['delivery_partner_name'], $r['full_name']) !== false)
                               || ((int)($order['rider_id'] ?? 0) === (int)$r['user_id'])
                               || (empty($order['delivery_partner_name']) && $r['user_id'] == 5);
                    $isBusyOther = $r['is_busy'] && ((int)$r['active_order_id'] !== (int)$order['order_id']);
                  ?>
                  <option value="<?= e($r['full_name']) ?>" <?= $isSelected ? 'selected' : '' ?>>
                    <?= e($r['full_name']) ?> <?= $isBusyOther ? "🔴 [Busy on #ZIP-{$r['active_order_id']}]" : '🟢 [Available (0/1)]' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text small" style="font-size:0.75rem;">
                <i class="bi bi-shield-lock text-primary me-1"></i><strong>1 Driver, 1 Delivery Policy:</strong> Busy couriers cannot be dispatched on additional orders until active trip is verified.
              </div>
            </div>

            <div class="p-3 rounded-3 bg-light border small text-muted">
              <i class="bi bi-shield-check text-success me-1"></i>
              <strong>Compliance Notice:</strong> Every status transition triggers an immutable audit log record in <code>order_audit_trail</code>.
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
<?php endforeach; ?>

<!-- ===========================================================================
     PINNED FLOATING BATCH ACTIONS BAR
     Appears when 1 or more orders are selected via checkboxes
     =========================================================================== -->
<div id="batchActionBar" class="position-fixed bottom-0 start-50 translate-middle-x mb-4 p-3 bg-dark text-white rounded-pill shadow-lg d-none align-items-center gap-3 z-3 border border-secondary" style="background: #1C2B20 !important; border-color: rgba(206, 255, 220, 0.25) !important;">
  <div class="d-flex align-items-center gap-2 ps-2">
    <span class="badge rounded-pill bg-success text-white font-monospace px-2 py-1" id="selectedCountBadge" style="background:#2E6F40 !important;">0</span>
    <span class="small fw-semibold text-white">Selected</span>
  </div>

  <div class="vr bg-secondary opacity-50 d-none d-sm-block"></div>

  <!-- Form for Batch Status Update Submission -->
  <form id="batchStatusForm" method="POST" action="<?= ADMIN_URL ?>/orders.php" class="m-0 d-flex align-items-center gap-2">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="batch_status_update">
    <input type="hidden" name="new_status" id="batchTargetStatusInput" value="">
    <div id="batchSelectedIdsContainer"></div>

    <button type="button" class="btn btn-warning btn-sm rounded-pill fw-bold px-3 text-dark shadow-xs" onclick="triggerBatchStatus('COOKING')" title="Advance selected to Cooking">
      <i class="fa-solid fa-fire me-1"></i>Start Cook
    </button>
    <button type="button" class="btn btn-primary btn-sm rounded-pill fw-bold px-3 shadow-xs" onclick="triggerBatchStatus('READY')" title="Mark selected as Cooked & Ready">
      <i class="bi bi-box-seam me-1"></i>Mark Ready
    </button>
    <button type="button" class="btn btn-success btn-sm rounded-pill fw-bold px-3 shadow-xs" style="background:#2E6F40; border-color:#2E6F40;" onclick="triggerBatchStatus('OUT_FOR_DELIVERY')" title="Dispatch selected to couriers">
      <i class="bi bi-bicycle me-1"></i>Dispatch
    </button>
  </form>

  <div class="vr bg-secondary opacity-50 d-none d-sm-block"></div>

  <!-- Batch KOT Print Button -->
  <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-3 shadow-xs" onclick="triggerBatchKotPrint()" title="Print thermal KOT slips for all selected tickets">
    <i class="bi bi-printer me-1"></i>Print KOTs
  </button>

  <button type="button" class="btn btn-link text-white-50 btn-sm text-decoration-none p-0 ms-1 me-2" onclick="clearBatchSelections()" title="Cancel and deselect all">
    <i class="bi bi-x-circle fs-5"></i>
  </button>
</div>

<!-- ===========================================================================
     INTERACTIVE CLIENT-SIDE SEARCH, FILTER & VIEW SCRIPTS
     =========================================================================== -->
<script>
// =============================================================================
// BATCH OPERATIONS ENGINE (Multi-select status advance & batch KOT slips)
// =============================================================================
function updateBatchBarState() {
  const checkedBoxes = document.querySelectorAll('.order-select-checkbox:checked');
  const batchBar = document.getElementById('batchActionBar');
  const countBadge = document.getElementById('selectedCountBadge');
  const selectAllTable = document.getElementById('selectAllOrdersTable');

  const count = checkedBoxes.length;
  if (countBadge) countBadge.textContent = count;

  if (batchBar) {
    if (count > 0) {
      batchBar.classList.remove('d-none');
      batchBar.classList.add('d-flex');
    } else {
      batchBar.classList.add('d-none');
      batchBar.classList.remove('d-flex');
    }
  }

  const allVisible = document.querySelectorAll('.order-select-checkbox');
  if (selectAllTable && allVisible.length > 0) {
    selectAllTable.checked = (count === allVisible.length);
  }
}

function clearBatchSelections() {
  document.querySelectorAll('.order-select-checkbox').forEach(cb => { cb.checked = false; });
  const selectAllTable = document.getElementById('selectAllOrdersTable');
  if (selectAllTable) selectAllTable.checked = false;
  updateBatchBarState();
}

function triggerBatchStatus(targetStatus) {
  const checkedBoxes = document.querySelectorAll('.order-select-checkbox:checked');
  if (checkedBoxes.length === 0) return;

  const container = document.getElementById('batchSelectedIdsContainer');
  const statusInput = document.getElementById('batchTargetStatusInput');
  const form = document.getElementById('batchStatusForm');

  if (!container || !statusInput || !form) return;

  container.innerHTML = '';
  statusInput.value = targetStatus;

  checkedBoxes.forEach(cb => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'order_ids[]';
    input.value = cb.value;
    container.appendChild(input);
  });

  const statusLabel = targetStatus.replace(/_/g, ' ');
  if (confirm(`Advance ${checkedBoxes.length} selected order(s) to status: ${statusLabel}?`)) {
    form.submit();
  }
}

function triggerBatchKotPrint() {
  const checkedBoxes = document.querySelectorAll('.order-select-checkbox:checked');
  if (checkedBoxes.length === 0) return;

  const ids = Array.from(checkedBoxes).map(cb => cb.value).join(',');
  const url = '<?= KITCHEN_URL ?>/kot.php?order_ids=' + encodeURIComponent(ids);
  window.open(url, '_blank', 'width=420,height=650');
}
document.addEventListener('DOMContentLoaded', () => {
  // Multi-Select Checkbox Event Listeners
  document.addEventListener('change', (e) => {
    if (e.target && e.target.classList.contains('order-select-checkbox')) {
      updateBatchBarState();
    }
  });

  const selectAllTable = document.getElementById('selectAllOrdersTable');
  if (selectAllTable) {
    selectAllTable.addEventListener('change', () => {
      const isChecked = selectAllTable.checked;
      document.querySelectorAll('.order-table-row:not(.d-none) .order-select-checkbox').forEach(cb => {
        cb.checked = isChecked;
      });
      updateBatchBarState();
    });
  }

  const searchInput = document.getElementById('orderLiveSearchInput');
  const clearBtn = document.getElementById('clearOrderSearchBtn');
  const resetBtn = document.getElementById('resetOrdersSearchBtn');
  const paymentFilter = document.getElementById('paymentModeFilter');
  const zeroState = document.getElementById('ordersZeroSearchState');
  const counterSpan = document.getElementById('filteredOrdersCount');
  
  const tableRows = document.querySelectorAll('.order-table-row');

  document.querySelectorAll('[data-sort-column]').forEach(button => {
    button.addEventListener('click', () => {
      const column = Number(button.dataset.sortColumn);
      const direction = button.dataset.sortDirection === 'asc' ? -1 : 1;
      button.dataset.sortDirection = direction === 1 ? 'asc' : 'desc';
      button.setAttribute('aria-sort', direction === 1 ? 'ascending' : 'descending');

      const tableBody = document.querySelector('#ordersMasterTable tbody');
      const sortedRows = Array.from(tableRows).sort((a, b) => {
        const left = a.cells[column]?.innerText.trim() || '';
        const right = b.cells[column]?.innerText.trim() || '';
        return left.localeCompare(right, undefined, { numeric: true, sensitivity: 'base' }) * direction;
      });

      sortedRows.forEach(row => tableBody.appendChild(row));
    });
  });

  // Real-time search and payment filter for the canonical ledger.
  function runLiveFilter() {
    const q = (searchInput?.value || '').trim().toLowerCase();
    const p = (paymentFilter?.value || '').trim();
    let visibleCount = 0;

    // Toggle clear button
    if (clearBtn) {
      clearBtn.classList.toggle('d-none', q.length === 0);
    }

    tableRows.forEach(row => {
      const rowSearch = row.getAttribute('data-search') || '';
      const rowPayment = row.getAttribute('data-payment') || '';

      const matchQ = (q === '') || rowSearch.includes(q);
      const matchP = (p === '') || (rowPayment === p);

      const visible = matchQ && matchP;
      row.classList.toggle('d-none', !visible);
      if (visible) visibleCount++;
    });

    if (counterSpan) {
      counterSpan.textContent = visibleCount;
    }

    if (zeroState) {
      zeroState.classList.toggle('d-none', visibleCount > 0 || tableRows.length === 0);
    }
  }

  if (searchInput) {
    searchInput.addEventListener('input', runLiveFilter);
  }

  if (paymentFilter) {
    paymentFilter.addEventListener('change', runLiveFilter);
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      searchInput.value = '';
      runLiveFilter();
      searchInput.focus();
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      if (searchInput) searchInput.value = '';
      if (paymentFilter) paymentFilter.value = '';
      runLiveFilter();
    });
  }
});

// =============================================================================
// ADMIN LIVE GPS MODAL & BACKGROUND ORDERS STREAM CONTROLLER
// =============================================================================
let adminMap = null;
let adminRiderMarker = null;
let adminRouteLine = null;
let adminChimeEnabled = true;

function toggleAdminChime() {
  adminChimeEnabled = !adminChimeEnabled;
  const label = document.getElementById('adminChimeLabel');
  const icon = document.getElementById('adminChimeIcon');
  if (label) label.textContent = adminChimeEnabled ? 'ON' : 'OFF';
  if (icon) icon.className = adminChimeEnabled ? 'bi bi-bell-fill me-1' : 'bi bi-bell-slash-fill me-1';
  if (adminChimeEnabled) playAdminChime();
}

function playAdminChime() {
  if (!adminChimeEnabled) return;
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'triangle';
    osc.frequency.setValueAtTime(523.25, ctx.currentTime); // C5
    osc.frequency.exponentialRampToValueAtTime(783.99, ctx.currentTime + 0.12); // G5
    gain.gain.setValueAtTime(0.08, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.4);
  } catch(e) {}
}

async function openAdminGpsModal(orderId, custName, riderName, otp) {
  document.getElementById('adminGpsOrderTitle').textContent = '#ZIP-' + orderId;
  document.getElementById('adminGpsCustName').textContent = custName;
  document.getElementById('adminGpsRiderName').textContent = riderName;
  document.getElementById('adminGpsOtp').textContent = otp || '------';

  const modalEl = document.getElementById('adminGpsTrackingModal');
  const modal = new bootstrap.Modal(modalEl);
  modal.show();

  setTimeout(async () => {
    const container = document.getElementById('adminLiveMapContainer');
    if (!container || typeof L === 'undefined') return;

    try {
      if (!adminMap) {
        adminMap = L.map('adminLiveMapContainer', {
          zoomControl: true,
          scrollWheelZoom: false
        }).setView([12.974, 77.645], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; OpenStreetMap'
        }).addTo(adminMap);
      } else {
        adminMap.invalidateSize();
      }

      // Fetch live telemetry for order
      const res = await fetch(`../api/live-location.php?order_id=${orderId}`);
      const data = await res.json();
      if (data.success) {
        document.getElementById('adminGpsDist').textContent = `${data.route.distance_remaining_km} km`;
        document.getElementById('adminGpsEta').textContent = `${data.route.eta_minutes} mins`;

        if (adminRouteLine) adminMap.removeLayer(adminRouteLine);
        if (adminRiderMarker) adminMap.removeLayer(adminRiderMarker);

        const hub = [data.locations.restaurant.lat, data.locations.restaurant.lng];
        const cust = [data.locations.customer.lat, data.locations.customer.lng];
        const rider = [data.locations.rider.lat, data.locations.rider.lng];

        const waypoints = data.route.waypoints.map(w => [w.lat, w.lng]);
        adminRouteLine = L.polyline(waypoints, {
          color: '#2E6F40',
          weight: 4,
          opacity: 0.8
        }).addTo(adminMap);

        L.marker(hub).addTo(adminMap).bindPopup('<strong>Zippy Kitchen Hub (Indiranagar)</strong>');
        L.marker(cust).addTo(adminMap).bindPopup(`<strong>${custName}</strong><br>${data.locations.customer.address}`);

        const riderIcon = L.divIcon({
          className: 'custom-admin-rider',
          html: '<div style="background:#2E6F40; color:#fff; width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 3px 10px rgba(0,0,0,0.4); border:2px solid #fff;"><i class="fa-solid fa-motorcycle"></i></div>',
          iconSize: [34, 34],
          iconAnchor: [17, 17]
        });
        adminRiderMarker = L.marker(rider, { icon: riderIcon }).addTo(adminMap)
          .bindPopup(`<strong>${riderName}</strong><br>Speed: ${data.locations.rider.speed_kmh} km/h`);

        adminMap.fitBounds(L.latLngBounds([hub, cust]), { padding: [30, 30] });
      }
    } catch(err) {
      console.warn('Admin map error:', err);
    }
  }, 300);
}

// Background poller for Admin HUD (every 4 seconds)
let syncCountdown = 4;
let lastKnownLiveCount = <?= (int)$cntLive ?>;

setInterval(async () => {
  syncCountdown--;
  const timerSpan = document.getElementById('adminSyncTimer');
  if (timerSpan) timerSpan.textContent = syncCountdown + 's';

  if (syncCountdown <= 0) {
    syncCountdown = 4;
    try {
      const res = await fetch('../api/live-stream.php?portal=admin');
      if (!res.ok) return;
      const data = await res.json();
      if (data.success && data.metrics) {
        const liveCount = document.getElementById('ordersLiveCount');
        const readyCount = document.getElementById('ordersReadyCount');
        const transitCount = document.getElementById('ordersTransitCount');

        if (liveCount) liveCount.textContent = data.metrics.total_active;
        if (readyCount) readyCount.textContent = data.metrics.ready;
        if (transitCount) transitCount.textContent = data.metrics.out_for_delivery;

        // If new live orders arrived, alert the admin
        if (data.metrics.total_active > lastKnownLiveCount) {
          playAdminChime();
          lastKnownLiveCount = data.metrics.total_active;
        } else {
          lastKnownLiveCount = data.metrics.total_active;
        }
      }
    } catch(e) {}
  }
}, 1000);
</script>

<!-- ===========================================================================
     ADMIN LIVE GPS ROUTE & RIDER TRACKING MODAL
     =========================================================================== -->
<div class="modal fade" id="adminGpsTrackingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
      <div class="p-3 bg-dark text-white d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-success rounded-pill px-3 py-1">ADMIN LIVE GPS HUD</span>
          <span class="fw-bold font-monospace" id="adminGpsOrderTitle">#ZIP-1004</span>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="p-4 bg-light">
        <!-- Telemetry Summary -->
        <div class="card border-0 rounded-4 p-3 bg-white shadow-xs mb-3">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <div class="small text-muted">Assigned Fleet Courier:</div>
              <h6 class="fw-bold text-dark mb-0" id="adminGpsRiderName">Suresh Kumar</h6>
              <div class="small text-muted">Customer: <span id="adminGpsCustName">Customer Name</span></div>
            </div>
            <div class="d-flex gap-3 text-center">
              <div>
                <div class="small text-muted">Distance</div>
                <div class="fw-bold text-dark font-monospace fs-5" id="adminGpsDist">1.8 km</div>
              </div>
              <div class="vr"></div>
              <div>
                <div class="small text-muted">ETA</div>
                <div class="fw-bold text-success font-monospace fs-5" id="adminGpsEta">6 mins</div>
              </div>
              <div class="vr"></div>
              <div>
                <div class="small text-muted">Secret OTP</div>
                <div class="fw-bold text-forest font-monospace fs-5" id="adminGpsOtp">------</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Leaflet Map Container -->
        <div class="rounded-4 mb-3 overflow-hidden shadow-xs border" style="height: 320px; position: relative;">
          <div id="adminLiveMapContainer" style="width: 100%; height: 100%; background: #f8faf9;"></div>
        </div>

        <div class="d-flex justify-content-between align-items-center small text-muted">
          <span><span class="pulse-dot me-1"></span> Live GPS Stream from Rider Telemetry (Indiranagar Corridor)</span>
          <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3 py-1">
            Status: OUT_FOR_DELIVERY
          </span>
        </div>
      </div>

      <div class="p-3 bg-white border-top text-end">
        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close Map</button>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
