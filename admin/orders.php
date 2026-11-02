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
$viewMode = clean_input($_GET['view'] ?? 'cards'); // 'cards' or 'table'

// Handle Status Update POST
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

            if ($newStatus === 'OUT_FOR_DELIVERY') {
                db_query("UPDATE `delivery_dispatches` SET `dispatched_at` = COALESCE(dispatched_at, NOW()), `delivery_status` = 'IN_TRANSIT' WHERE `order_id` = :id", [':id' => $orderId]);
            } elseif ($newStatus === 'DELIVERED') {
                db_query("UPDATE `delivery_dispatches` SET `delivered_at` = NOW(), `delivery_status` = 'DELIVERED' WHERE `order_id` = :id", [':id' => $orderId]);
                // If COD, mark payment as SUCCESSFUL & update money_transactions
                $payRec = db_fetch_one("SELECT * FROM `payments` WHERE `order_id` = :id", [':id' => $orderId]);
                if ($payRec && $payRec['payment_mode'] === 'COD') {
                    db_query("UPDATE `payments` SET `payment_status` = 'SUCCESSFUL', `payment_timestamp` = NOW() WHERE `order_id` = :id", [':id' => $orderId]);
                    db_query("
                        UPDATE `money_transactions` 
                        SET `status` = 'COMPLETED', `handled_by` = :hby, `notes` = CONCAT(COALESCE(notes, ''), ' [Cash collected upon delivery completion]') 
                        WHERE `order_id` = :id AND `txn_category` = 'CUSTOMER_PAYMENT_COD'
                    ", [
                        ':hby' => 'Store Admin / Courier',
                        ':id'  => $orderId
                    ]);
                }
            } elseif ($newStatus === 'CANCELLED') {
                // Restore inventory stock for cancelled order line items
                db_query("
                    UPDATE `menu_items` m
                    JOIN `order_items` oi ON m.item_id = oi.item_id
                    SET m.stock_quantity = m.stock_quantity + oi.quantity,
                        m.is_available = 1
                    WHERE oi.order_id = :id
                ", [':id' => $orderId]);

                // Record payment settlement status as REFUNDED
                db_query("
                    UPDATE `payments` 
                    SET `payment_status` = 'REFUNDED', `payment_timestamp` = NOW() 
                    WHERE `order_id` = :id
                ", [':id' => $orderId]);
            }

            set_flash('success', "Order #ZIP-{$orderId} status advanced to {$newStatus}.");
            $redirectUrl = ADMIN_URL . "/orders.php" . (!empty($filterStatus) ? "?status=" . urlencode($filterStatus) : '');
            if (!empty($searchQuery)) $redirectUrl .= "&search=" . urlencode($searchQuery);
            header("Location: " . $redirectUrl);
            exit;
        }
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

// 1. Live Counters
$cntAll = (int)db_fetch_val("SELECT COUNT(*) FROM `orders`");
$cntLive = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE order_status IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY')");
$cntPreparing = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE order_status IN ('CONFIRMED', 'COOKING')");
$cntReady = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE order_status = 'READY'");
$cntDelivering = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE order_status = 'OUT_FOR_DELIVERY'");
$cntDelivered = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE order_status = 'DELIVERED'");
$cntCancelled = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE order_status = 'CANCELLED'");

// 2. Kitchen Cook-Load Aggregation (What is being prepared across the entire kitchen right now)
$cookLoadItems = db_fetch_all("
    SELECT m.item_name, m.is_veg, SUM(oi.quantity) as total_qty
    FROM `order_items` oi
    JOIN `orders` o ON oi.order_id = o.order_id
    JOIN `menu_items` m ON oi.item_id = m.item_id
    WHERE o.order_status IN ('CONFIRMED', 'COOKING')
    GROUP BY oi.item_id, m.item_name, m.is_veg
    ORDER BY total_qty DESC
");

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
           d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp, d.delivery_status, d.dispatched_at,
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
     OPERATIONAL STAGE METRICS CARDS & REAL-TIME STREAM HUD
     =========================================================================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
  <div>
    <h4 class="fw-bold text-dark font-outfit mb-0"><i class="bi bi-broadcast text-success me-2"></i>Live Orders Command Center</h4>
    <div class="small text-muted">Real-time sync across Customer, Kitchen, Delivery Fleet, and Financial Ledger</div>
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

<div class="row g-3 mb-4">
  
  <div class="col-6 col-md-3 col-xl">
    <a href="<?= ADMIN_URL ?>/orders.php?status=LIVE" class="text-decoration-none">
      <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100 transition-hover <?= ($filterStatus === 'LIVE') ? 'border-2 border-forest' : '' ?>">
        <div class="d-flex align-items-center justify-content-between mb-1">
          <span class="small fw-bold text-muted text-uppercase">⚡ All Live Orders</span>
          <span class="live-pulse-dot green"></span>
        </div>
        <h3 class="fw-bold font-outfit text-dark mb-0" id="adminKpiLive"><?= $cntLive ?></h3>
        <span class="small text-forest fw-semibold">Active in Pipeline</span>
      </div>
    </a>
  </div>

  <div class="col-6 col-md-3 col-xl">
    <a href="<?= ADMIN_URL ?>/orders.php?status=PREPARING" class="text-decoration-none">
      <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100 transition-hover <?= ($filterStatus === 'PREPARING' || $filterStatus === 'COOKING') ? 'border-2 border-warning' : '' ?>" style="border-top: 3px solid #F59E0B !important;">
        <div class="d-flex align-items-center justify-content-between mb-1">
          <span class="small fw-bold text-muted text-uppercase">🍳 Being Prepared</span>
          <span class="live-pulse-dot amber"></span>
        </div>
        <h3 class="fw-bold font-outfit text-dark mb-0" id="adminKpiCooking"><?= $cntPreparing ?></h3>
        <span class="small text-warning fw-semibold">Cooking in Kitchen</span>
      </div>
    </a>
  </div>

  <div class="col-6 col-md-3 col-xl">
    <a href="<?= ADMIN_URL ?>/orders.php?status=READY" class="text-decoration-none">
      <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100 transition-hover <?= ($filterStatus === 'READY') ? 'border-2 border-success' : '' ?>" style="border-top: 3px solid #10B981 !important;">
        <div class="d-flex align-items-center justify-content-between mb-1">
          <span class="small fw-bold text-muted text-uppercase">📦 Ready for Pickup</span>
          <i class="bi bi-box-seam text-success"></i>
        </div>
        <h3 class="fw-bold font-outfit text-dark mb-0" id="adminKpiReady"><?= $cntReady ?></h3>
        <span class="small text-success fw-semibold">Cooked & Packed</span>
      </div>
    </a>
  </div>

  <div class="col-6 col-md-3 col-xl">
    <a href="<?= ADMIN_URL ?>/orders.php?status=OUT_FOR_DELIVERY" class="text-decoration-none">
      <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100 transition-hover <?= ($filterStatus === 'OUT_FOR_DELIVERY') ? 'border-2 border-primary' : '' ?>" style="border-top: 3px solid #3B82F6 !important;">
        <div class="d-flex align-items-center justify-content-between mb-1">
          <span class="small fw-bold text-muted text-uppercase">🚴 Out for Delivery</span>
          <span class="live-pulse-dot blue"></span>
        </div>
        <h3 class="fw-bold font-outfit text-dark mb-0" id="adminKpiTransit"><?= $cntDelivering ?></h3>
        <span class="small text-primary fw-semibold">Courier In Transit</span>
      </div>
    </a>
  </div>

  <div class="col-6 col-md-3 col-xl">
    <a href="<?= ADMIN_URL ?>/orders.php?status=DELIVERED" class="text-decoration-none">
      <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100 transition-hover <?= ($filterStatus === 'DELIVERED') ? 'border-2 border-success' : '' ?>">
        <div class="d-flex align-items-center justify-content-between mb-1">
          <span class="small fw-bold text-muted text-uppercase">✅ Delivered</span>
          <i class="bi bi-house-check text-success"></i>
        </div>
        <h3 class="fw-bold font-outfit text-dark mb-0" id="adminKpiDelivered"><?= $cntDelivered ?></h3>
        <span class="small text-muted">Completed Orders</span>
      </div>
    </a>
  </div>

</div>

<!-- ===========================================================================
     WHAT IS BEING PREPARED: LIVE KITCHEN COOK-LINE STRIP
     =========================================================================== -->
<div class="card border-0 rounded-4 shadow-sm p-3 bg-white mb-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold">
        <i class="fa-solid fa-fire-burner me-1"></i> ACTIVE KITCHEN COOK-LINE
      </span>
      <span class="small text-muted fw-bold">Live aggregated dishes currently being prepared across all active tickets:</span>
    </div>
    <span class="small text-forest fw-bold">
      <?= count($cookLoadItems) ?> distinct dishes on cook-line
    </span>
  </div>

  <?php if (empty($cookLoadItems)): ?>
    <div class="text-muted small py-2">
      <i class="bi bi-check-circle-fill text-success me-1"></i> Kitchen cook-line is currently clear! No active dishes in preparation.
    </div>
  <?php else: ?>
    <div class="d-flex flex-wrap align-items-center gap-2 pt-1">
      <?php foreach ($cookLoadItems as $item): ?>
        <div class="cook-load-badge">
          <span class="badge-count"><?= (int)$item['total_qty'] ?>x</span>
          <span>
            <?= $item['is_veg'] ? '<i class="bi bi-circle-fill text-success me-1" style="font-size:0.6rem;"></i>' : '<i class="bi bi-triangle-fill text-danger me-1" style="font-size:0.6rem;"></i>' ?>
            <?= e($item['item_name']) ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ===========================================================================
     MAIN LIVE ORDERS MANAGEMENT CONSOLE
     =========================================================================== -->
<div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
  
  <!-- Filter Pills Bar & View Mode Toggle -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    
    <!-- Stage Filter Pills -->
    <div class="d-flex flex-wrap align-items-center gap-2">
      <span class="small fw-bold text-muted me-1">Filter Stage:</span>
      <a href="<?= ADMIN_URL ?>/orders.php?status=LIVE<?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" 
         class="btn btn-sm rounded-pill <?= ($filterStatus === 'LIVE') ? 'btn-dark' : 'btn-light border' ?>">
        ⚡ Live Active (<?= $cntLive ?>)
      </a>
      <a href="<?= ADMIN_URL ?>/orders.php?status=PREPARING<?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" 
         class="btn btn-sm rounded-pill <?= ($filterStatus === 'PREPARING') ? 'btn-warning text-dark' : 'btn-light border' ?>">
        🍳 Being Prepared (<?= $cntPreparing ?>)
      </a>
      <a href="<?= ADMIN_URL ?>/orders.php?status=READY<?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" 
         class="btn btn-sm rounded-pill <?= ($filterStatus === 'READY') ? 'btn-success' : 'btn-light border' ?>">
        📦 Ready for Pickup (<?= $cntReady ?>)
      </a>
      <a href="<?= ADMIN_URL ?>/orders.php?status=OUT_FOR_DELIVERY<?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" 
         class="btn btn-sm rounded-pill <?= ($filterStatus === 'OUT_FOR_DELIVERY') ? 'btn-primary' : 'btn-light border' ?>">
        🚴 Out for Delivery (<?= $cntDelivering ?>)
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

    <!-- View Switcher & Action Tools -->
    <div class="d-flex align-items-center gap-2">
      <!-- Cards vs Table View Toggle -->
      <div class="btn-group btn-group-sm p-1 rounded-pill bg-light border" role="group">
        <button type="button" class="btn rounded-pill px-3 fw-bold <?= ($viewMode !== 'table') ? 'btn-dark shadow-sm' : 'btn-light' ?>" id="btnViewCards">
          <i class="bi bi-grid-fill me-1"></i> Cards View
        </button>
        <button type="button" class="btn rounded-pill px-3 fw-bold <?= ($viewMode === 'table') ? 'btn-dark shadow-sm' : 'btn-light' ?>" id="btnViewTable">
          <i class="bi bi-table me-1"></i> Table View
        </button>
      </div>

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

  <!-- ===========================================================================
       VIEW A: INTERACTIVE VISUAL CARDS (KANBAN / OPERATIONAL VIEW)
       =========================================================================== -->
  <div id="ordersCardsViewContainer" class="<?= ($viewMode === 'table') ? 'd-none' : '' ?>">
    
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
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-dark fw-bold font-monospace">#ZIP-<?= (int)$order['order_id'] ?></span>
                  <?= get_status_badge($status) ?>
                </div>
                <span class="small text-muted">
                  <?= date('h:i A', strtotime($order['placed_at'])) ?> (<?= (int)$order['elapsed_minutes'] ?>m ago)
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

  <!-- ===========================================================================
       VIEW B: STRUCTURED DATA TABLE (DETAILED LEDGER VIEW)
       =========================================================================== -->
  <div id="ordersTableViewContainer" class="<?= ($viewMode === 'table') ? '' : 'd-none' ?>">
    <div class="table-responsive">
      <table class="table table-hover align-middle small mb-0" id="ordersMasterTable">
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
              <?php 
                $items = $orderItemsGrouped[$order['order_id']] ?? [];
                $itemNames = array_map(fn($it) => $it['item_name'], $items);
                $rowSearchData = strtolower("ZIP-{$order['order_id']} {$order['full_name']} {$order['phone_number']} {$order['email']} {$order['address_line']} {$order['pincode']} {$order['delivery_partner_name']} {$order['delivery_otp']} {$order['payment_mode']} {$order['order_status']} " . implode(' ', $itemNames));
              ?>
              <tr class="order-table-row" 
                  data-order-id="<?= (int)$order['order_id'] ?>"
                  data-status="<?= e($order['order_status']) ?>"
                  data-payment="<?= e($order['payment_mode']) ?>"
                  data-search="<?= e($rowSearchData) ?>">
                
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
                  <?= get_status_badge($order['order_status']) ?>
                </td>

                <td>
                  <div class="d-flex flex-column gap-1">
                    <?php if ($order['order_status'] === 'OUT_FOR_DELIVERY'): ?>
                      <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-1" onclick="openAdminGpsModal(<?= (int)$order['order_id'] ?>, '<?= e(addslashes($order['full_name'])) ?>', '<?= e(addslashes($order['delivery_partner_name'] ?: 'Rider')) ?>', '<?= e(addslashes($order['delivery_otp'] ?: '')) ?>')">
                        <i class="fa-solid fa-location-arrow me-1"></i> Live GPS
                      </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill" data-bs-toggle="modal" data-bs-target="#statusModal<?= (int)$order['order_id'] ?>">
                      Advance Status
                    </button>
                    <a href="<?= CUSTOMER_URL ?>/invoice.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-sm btn-light border rounded-pill text-muted" target="_blank">
                      Tax Invoice
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
              <input type="text" name="rider_name" class="form-control rounded-3" 
                     value="<?= e($order['delivery_partner_name'] ?: 'Suresh Kumar (Zippy Express)') ?>"
                     placeholder="e.g. Suresh Kumar (Zippy Express)">
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
     INTERACTIVE CLIENT-SIDE SEARCH, FILTER & VIEW SCRIPTS
     =========================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('orderLiveSearchInput');
  const clearBtn = document.getElementById('clearOrderSearchBtn');
  const resetBtn = document.getElementById('resetOrdersSearchBtn');
  const paymentFilter = document.getElementById('paymentModeFilter');
  const zeroState = document.getElementById('ordersZeroSearchState');
  const counterSpan = document.getElementById('filteredOrdersCount');
  
  const btnCards = document.getElementById('btnViewCards');
  const btnTable = document.getElementById('btnViewTable');
  const cardsContainer = document.getElementById('ordersCardsViewContainer');
  const tableContainer = document.getElementById('ordersTableViewContainer');

  const cardItems = document.querySelectorAll('.order-card-item-col');
  const tableRows = document.querySelectorAll('.order-table-row');

  // 1. View Switcher Logic
  function setView(view) {
    if (view === 'table') {
      cardsContainer.classList.add('d-none');
      tableContainer.classList.remove('d-none');
      btnTable.classList.add('btn-dark', 'shadow-sm');
      btnTable.classList.remove('btn-light');
      btnCards.classList.remove('btn-dark', 'shadow-sm');
      btnCards.classList.add('btn-light');
      localStorage.setItem('zippy_admin_orders_view', 'table');
    } else {
      cardsContainer.classList.remove('d-none');
      tableContainer.classList.add('d-none');
      btnCards.classList.add('btn-dark', 'shadow-sm');
      btnCards.classList.remove('btn-light');
      btnTable.classList.remove('btn-dark', 'shadow-sm');
      btnTable.classList.add('btn-light');
      localStorage.setItem('zippy_admin_orders_view', 'cards');
    }
  }

  // Restore saved view preference
  const savedView = localStorage.getItem('zippy_admin_orders_view');
  if (savedView) {
    setView(savedView);
  }

  if (btnCards) btnCards.addEventListener('click', () => setView('cards'));
  if (btnTable) btnTable.addEventListener('click', () => setView('table'));

  // 2. Real-time Live Search & Payment Filter
  function runLiveFilter() {
    const q = (searchInput?.value || '').trim().toLowerCase();
    const p = (paymentFilter?.value || '').trim();
    let visibleCount = 0;

    // Toggle clear button
    if (clearBtn) {
      clearBtn.classList.toggle('d-none', q.length === 0);
    }

    // Filter cards
    cardItems.forEach(card => {
      const cardSearch = card.getAttribute('data-search') || '';
      const cardPayment = card.getAttribute('data-payment') || '';

      const matchQ = (q === '') || cardSearch.includes(q);
      const matchP = (p === '') || (cardPayment === p);

      if (matchQ && matchP) {
        card.classList.remove('d-none');
        visibleCount++;
      } else {
        card.classList.add('d-none');
      }
    });

    // Filter table rows
    tableRows.forEach(row => {
      const rowSearch = row.getAttribute('data-search') || '';
      const rowPayment = row.getAttribute('data-payment') || '';

      const matchQ = (q === '') || rowSearch.includes(q);
      const matchP = (p === '') || (rowPayment === p);

      row.classList.toggle('d-none', !(matchQ && matchP));
    });

    if (counterSpan) {
      counterSpan.textContent = visibleCount;
    }

    if (zeroState) {
      const hasAnyVisible = (visibleCount > 0) || (cardItems.length === 0 && tableRows.length === 0);
      zeroState.classList.toggle('d-none', hasAnyVisible);
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
        const kpiLive = document.getElementById('adminKpiLive');
        const kpiCooking = document.getElementById('adminKpiCooking');
        const kpiReady = document.getElementById('adminKpiReady');
        const kpiTransit = document.getElementById('adminKpiTransit');
        const kpiDelivered = document.getElementById('adminKpiDelivered');

        if (kpiLive) kpiLive.textContent = data.metrics.total_active;
        if (kpiCooking) kpiCooking.textContent = data.metrics.cooking;
        if (kpiReady) kpiReady.textContent = data.metrics.ready;
        if (kpiTransit) kpiTransit.textContent = data.metrics.out_for_delivery;
        if (kpiDelivered) kpiDelivered.textContent = data.metrics.delivered_today;

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
