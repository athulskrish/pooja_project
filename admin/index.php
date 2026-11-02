<?php
/**
 * Executive Admin Dashboard & Live Operations Center
 * Zippy - Online Food Ordering Platform
 * Student: Pooja JP | MCSP-232
 * 
 * Redesigned using Material Design 3 (M3) Principles:
 * - Minimalist, low-fatigue visual hierarchy
 * - Tonal card surfaces and gentle elevation
 * - Integrated Segmented Control for Cards, Ledger, and Kitchen Cook-Line
 * - Fast 1-touch accessible controls on Desktop and Mobile
 */

$pageTitle = 'Operations & Kitchen Dashboard';
$activePage = 'dashboard';

// Bootstrap core libraries before HTML output
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/WSJFQueue.php';
$currentUser = current_user();

// Enforce Administrator privileges
require_admin();

// Quick Status Update POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_status_update') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
    } else {
        $orderId = (int)$_POST['order_id'];
        $newStatus = clean_input($_POST['new_status']);
        $assignedRider = clean_input($_POST['rider_name'] ?? '');
        
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

            // If rider assigned
            if (!empty($assignedRider)) {
                db_query("UPDATE `delivery_dispatches` SET `delivery_partner_name` = :rname WHERE `order_id` = :oid", [
                    ':rname' => $assignedRider,
                    ':oid' => $orderId
                ]);
            }

            // Status transitions handling
            if ($newStatus === 'OUT_FOR_DELIVERY') {
                db_query("
                    UPDATE `delivery_dispatches` 
                    SET `dispatched_at` = COALESCE(dispatched_at, NOW()), 
                        `delivery_status` = 'IN_TRANSIT',
                        `current_latitude` = COALESCE(current_latitude, 12.97840000),
                        `current_longitude` = COALESCE(current_longitude, 77.64080000),
                        `route_progress` = COALESCE(route_progress, 0),
                        `distance_remaining_km` = COALESCE(distance_remaining_km, 2.40),
                        `estimated_minutes_remaining` = COALESCE(estimated_minutes_remaining, 15)
                    WHERE `order_id` = :id
                ", [':id' => $orderId]);
            } elseif ($newStatus === 'DELIVERED') {
                db_query("UPDATE `delivery_dispatches` SET `delivered_at` = NOW(), `delivery_status` = 'DELIVERED' WHERE `order_id` = :id", [':id' => $orderId]);
                
                // If COD, mark payment as SUCCESSFUL & update financial ledger
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
                // Restore inventory stock
                db_query("
                    UPDATE `menu_items` m
                    JOIN `order_items` oi ON m.item_id = oi.item_id
                    SET m.stock_quantity = m.stock_quantity + oi.quantity,
                        m.is_available = 1
                    WHERE oi.order_id = :id
                ", [':id' => $orderId]);

                db_query("
                    UPDATE `payments` 
                    SET `payment_status` = 'REFUNDED', `payment_timestamp` = NOW() 
                    WHERE `order_id` = :id
                ", [':id' => $orderId]);
            }

            set_flash('success', "Order #ZIP-{$orderId} status advanced to {$newStatus}.");
            header("Location: " . ADMIN_URL . "/index.php");
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';

// 1. KPI Aggregations
$totalRevenue = db_fetch_val("SELECT COALESCE(SUM(total_amount), 0) FROM `orders` WHERE order_status != 'CANCELLED'") ?: 0;
$totalOrders = db_fetch_val("SELECT COUNT(*) FROM `orders`") ?: 0;
$activeKitchen = (int)(db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE order_status IN ('CONFIRMED', 'COOKING')") ?: 0);
$activeDelivering = (int)(db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE order_status = 'OUT_FOR_DELIVERY'") ?: 0);
$activeReady = (int)(db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE order_status = 'READY'") ?: 0);
$lowStockCount = (int)(db_fetch_val("SELECT COUNT(*) FROM `menu_items` WHERE stock_quantity <= 15 AND is_available = 1") ?: 0);

// 2. Active Kitchen Preparation Cook-Load Aggregation
$cookLoadSummary = db_fetch_all("
    SELECT m.item_id, m.item_name, m.is_veg, SUM(oi.quantity) as total_qty
    FROM `order_items` oi
    JOIN `orders` o ON oi.order_id = o.order_id
    JOIN `menu_items` m ON oi.item_id = m.item_id
    WHERE o.order_status IN ('CONFIRMED', 'COOKING')
    GROUP BY oi.item_id, m.item_name, m.is_veg
    ORDER BY total_qty DESC
");

// 3. WSJF Prioritized Orders in Preparation (CONFIRMED, COOKING, READY)
$prepOrders = WSJFQueue::getPrioritizedOrders();

// 4. Active Orders in Delivery Transit (OUT_FOR_DELIVERY)
$deliveryOrders = db_fetch_all("
    SELECT o.*, u.full_name AS customer_name, u.phone_number AS customer_phone, u.email AS customer_email,
           a.address_line, a.landmark, a.pincode,
           p.payment_mode, p.payment_status, p.amount_paid,
           d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp, d.delivery_status, d.dispatched_at,
           TIMESTAMPDIFF(MINUTE, COALESCE(d.dispatched_at, o.placed_at), NOW()) AS transit_minutes
    FROM `orders` o
    JOIN `users` u ON o.user_id = u.user_id
    LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    WHERE o.order_status = 'OUT_FOR_DELIVERY'
    ORDER BY o.placed_at ASC
");

// Fetch line items for delivery orders
if (!empty($deliveryOrders)) {
    $delOrderIds = array_column($deliveryOrders, 'order_id');
    $placeholders = implode(',', array_fill(0, count($delOrderIds), '?'));
    $delItemsRaw = db_fetch_all("
        SELECT oi.*, m.item_name, m.is_veg 
        FROM `order_items` oi
        JOIN `menu_items` m ON oi.item_id = m.item_id
        WHERE oi.order_id IN ($placeholders)
    ", $delOrderIds);

    $delItemsByOrder = [];
    foreach ($delItemsRaw as $dit) {
        $delItemsByOrder[$dit['order_id']][] = $dit;
    }
    foreach ($deliveryOrders as &$dOrder) {
        $dOrder['items'] = $delItemsByOrder[$dOrder['order_id']] ?? [];
    }
    unset($dOrder);
}

// 5. Recent Transactions
$recentOrders = db_fetch_all("
    SELECT o.*, u.full_name, u.phone_number, p.payment_mode, p.payment_status,
           d.delivery_partner_name, d.delivery_otp
    FROM `orders` o
    JOIN `users` u ON o.user_id = u.user_id
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    ORDER BY o.placed_at DESC
    LIMIT 10
");
?>

<!-- ===========================================================================
     MATERIAL 3 KPI METRIC CHIPS
     Clean, low-fatigue operational metrics row
     =========================================================================== -->
<div class="row g-3 mb-4">
  
  <!-- KPI 1: Revenue -->
  <div class="col-6 col-lg-3">
    <div class="card border-0 rounded-4 shadow-sm p-3 p-xl-4 bg-white h-100 transition-hover">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-bold text-uppercase" style="letter-spacing:0.5px;">TOTAL REVENUE</span>
        <div class="rounded-circle p-2 text-success d-flex align-items-center justify-content-center" style="width:40px; height:40px; background:#EDFBF2;">
          <i class="bi bi-currency-rupee fs-5"></i>
        </div>
      </div>
      <h3 class="fw-bold font-outfit text-dark mb-0"><?= format_inr($totalRevenue) ?></h3>
      <span class="small text-forest fw-semibold" style="font-size:0.75rem;"><i class="bi bi-arrow-up-right me-1"></i>Settled Revenue</span>
    </div>
  </div>

  <!-- KPI 2: Total Orders -->
  <div class="col-6 col-lg-3">
    <div class="card border-0 rounded-4 shadow-sm p-3 p-xl-4 bg-white h-100 transition-hover">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-bold text-uppercase" style="letter-spacing:0.5px;">TOTAL ORDERS</span>
        <div class="rounded-circle p-2 text-primary d-flex align-items-center justify-content-center" style="width:40px; height:40px; background:#EEF4FF;">
          <i class="bi bi-bag-check fs-5"></i>
        </div>
      </div>
      <h3 class="fw-bold font-outfit text-dark mb-0"><?= (int)$totalOrders ?> Orders</h3>
      <span class="small text-muted" style="font-size:0.75rem;">Lifetime volume</span>
    </div>
  </div>

  <!-- KPI 3: Kitchen Queue -->
  <div class="col-6 col-lg-3">
    <div class="card border-0 rounded-4 shadow-sm p-3 p-xl-4 bg-white h-100 transition-hover" style="border-top: 3px solid #F59E0B !important;">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-bold text-uppercase" style="letter-spacing:0.5px;">KITCHEN QUEUE</span>
        <div class="rounded-circle p-2 text-warning d-flex align-items-center justify-content-center" style="width:40px; height:40px; background:#FFFBEB;">
          <i class="fa-solid fa-fire-burner fs-5"></i>
        </div>
      </div>
      <h3 class="fw-bold font-outfit text-dark mb-0"><?= (int)$activeKitchen ?> Active</h3>
      <div class="small text-warning fw-bold d-flex align-items-center gap-1" style="font-size:0.75rem;">
        <span class="live-pulse-dot amber"></span>
        <span>Cooking in Kitchen</span>
      </div>
    </div>
  </div>

  <!-- KPI 4: In Transit -->
  <div class="col-6 col-lg-3">
    <div class="card border-0 rounded-4 shadow-sm p-3 p-xl-4 bg-white h-100 transition-hover" style="border-top: 3px solid #3B82F6 !important;">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-bold text-uppercase" style="letter-spacing:0.5px;">IN TRANSIT</span>
        <div class="rounded-circle p-2 text-primary d-flex align-items-center justify-content-center" style="width:40px; height:40px; background:#EFF6FF;">
          <i class="bi bi-bicycle fs-5"></i>
        </div>
      </div>
      <h3 class="fw-bold font-outfit text-dark mb-0"><?= (int)$activeDelivering ?> Delivering</h3>
      <div class="small text-primary fw-bold d-flex align-items-center gap-1" style="font-size:0.75rem;">
        <span class="live-pulse-dot blue"></span>
        <span>With Courier Fleet</span>
      </div>
    </div>
  </div>

</div>

<!-- ===========================================================================
     MATERIAL 3 STREAMLINED OPERATIONS HUB
     Integrated Segmented Control (Live Cards vs Master Table vs Cook-Line)
     =========================================================================== -->
<div class="card border-0 rounded-4 shadow-sm bg-white mb-4 overflow-hidden">
  
  <!-- Header Bar with Integrated M3 Segmented Navigation -->
  <div class="p-3 p-xl-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="badge rounded-pill px-3 py-1 fw-bold" style="background:#EDFBF2; color:#2E6F40; border:1px solid #CEFFDC; font-size:0.7rem;">
          <span class="live-pulse-dot green me-1"></span> LIVE OPERATIONS DISPATCH
        </span>
        <span class="small text-muted font-monospace" style="font-size:0.75rem;">
          <i class="bi bi-shield-check text-forest me-1"></i>WSJF Prioritized Live Queue
        </span>
      </div>
      <h4 class="fw-bold font-outfit mb-0 text-dark">Live Order Execution & Dispatch</h4>
    </div>

    <!-- M3 Segmented Control Tabs -->
    <div class="m3-segmented-nav" role="tablist">
      <button class="m3-segmented-btn active" id="tabBtnStream" data-bs-toggle="pill" data-bs-target="#tabPaneStream" type="button" role="tab">
        <i class="bi bi-grid-fill me-1"></i> Live Stream (<?= count($prepOrders) + count($deliveryOrders) ?>)
      </button>
      <button class="m3-segmented-btn" id="tabBtnLedger" data-bs-toggle="pill" data-bs-target="#tabPaneLedger" type="button" role="tab">
        <i class="bi bi-table me-1"></i> Recent Table
      </button>
      <button class="m3-segmented-btn" id="tabBtnCookline" data-bs-toggle="pill" data-bs-target="#tabPaneCookline" type="button" role="tab">
        <i class="fa-solid fa-fire me-1"></i> Kitchen Cook-Line (<?= count($cookLoadSummary) ?>)
      </button>
    </div>
  </div>

  <div class="tab-content p-3 p-xl-4">
    
    <!-- =========================================================================
         TAB 1: LIVE ORDER CARDS (DEFAULT OPERATIONAL STREAM)
         ========================================================================= -->
    <div class="tab-pane fade show active" id="tabPaneStream" role="tabpanel">
      
      <!-- Minimalist Cook-Line Micro-Bar -->
      <div class="p-2 px-3 rounded-pill mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2" style="background:#FAFBF9; border: 1px solid #E2ECE5;">
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-warning text-dark px-2 py-1 rounded-pill fw-bold" style="font-size:0.68rem;">
            <i class="fa-solid fa-fire me-1"></i> STOVE DEMAND
          </span>
          <span class="small text-muted fw-bold" style="font-size:0.75rem;">Currently on Cook-Line:</span>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-1">
          <?php if (empty($cookLoadSummary)): ?>
            <span class="cook-load-badge text-muted" style="border-style:dashed;">
              <span class="badge-count">0x</span>
              <span>All tickets plated</span>
            </span>
          <?php else: ?>
            <?php foreach (array_slice($cookLoadSummary, 0, 5) as $item): ?>
              <span class="cook-load-badge">
                <span class="badge-count"><?= (int)$item['total_qty'] ?>x</span>
                <span><?= e($item['item_name']) ?></span>
              </span>
            <?php endforeach; ?>
            <?php if (count($cookLoadSummary) > 5): ?>
              <span class="small text-muted fw-bold">+<?= count($cookLoadSummary) - 5 ?> more</span>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Controls: M3 Search & Stage Filter Chips -->
      <div class="row g-2 align-items-center mb-3">
        <!-- Search Input -->
        <div class="col-12 col-md-5">
          <div class="admin-search-box d-flex align-items-center gap-2 rounded-pill px-3 py-1 bg-light border">
            <i class="bi bi-search text-muted"></i>
            <input type="text" id="dashboardLiveSearch" class="border-0 bg-transparent w-100 small" placeholder="Search by #ZIP, customer, dish, or rider..." autocomplete="off">
            <button type="button" class="btn btn-link text-muted p-0 d-none" id="clearDashboardSearch" title="Clear search">
              <i class="bi bi-x-circle-fill"></i>
            </button>
          </div>
        </div>

        <!-- Filter Chips -->
        <div class="col-12 col-md-7">
          <div class="d-flex flex-wrap align-items-center justify-content-md-end gap-1" id="dashboardStageFilters">
            <button type="button" class="m3-chip active filter-stage-btn" data-stage="ALL">
              All Active (<?= count($prepOrders) + count($deliveryOrders) ?>)
            </button>
            <button type="button" class="m3-chip filter-stage-btn" data-stage="PREPARING">
              <i class="bi bi-fire text-warning"></i> Being Prepared (<?= count($prepOrders) ?>)
            </button>
            <button type="button" class="m3-chip filter-stage-btn" data-stage="DELIVERING">
              <i class="bi bi-bicycle text-primary"></i> Out for Delivery (<?= count($deliveryOrders) ?>)
            </button>
            <button type="button" class="m3-chip filter-stage-btn" data-stage="READY">
              <i class="bi bi-box-seam text-success"></i> Ready (<?= $activeReady ?>)
            </button>
          </div>
        </div>
      </div>

      <!-- Results Count -->
      <div class="small text-muted mb-2 d-none" id="searchResultsCounter">
        Showing <strong id="visibleCardCount">0</strong> matching orders
      </div>

      <!-- Live Cards Grid -->
      <?php if (empty($prepOrders) && empty($deliveryOrders)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-check2-all fs-1 text-success opacity-75 mb-2 d-block"></i>
          <h6 class="fw-bold">All live orders fulfilled! No active preparation or delivery tickets.</h6>
          <div class="small">New orders placed by customers populate here automatically.</div>
        </div>
      <?php else: ?>
        <div class="row g-3" id="liveOrderCardsContainer">
          
          <!-- STAGE A: ORDERS IN KITCHEN PREPARATION -->
          <?php foreach ($prepOrders as $order): ?>
            <?php 
              $isReady = ($order['order_status'] === 'READY');
              $stageCategory = $isReady ? 'READY' : 'PREPARING';
              $cardBorderClass = $isReady ? 'stage-ready' : 'stage-preparing';
              $itemSearchStrings = array_map(fn($it) => $it['item_name'], $order['items'] ?? []);
              $combinedSearchText = strtolower("ZIP-{$order['order_id']} {$order['customer_name']} {$order['customer_phone']} " . implode(' ', $itemSearchStrings) . " {$order['order_status']} " . ($order['order_notes'] ?? ''));
            ?>
            <div class="col-md-6 col-xl-4 live-order-card-col" 
                 data-stage="<?= $stageCategory ?>"
                 data-search="<?= e($combinedSearchText) ?>">
              <div class="order-live-card p-3 <?= $cardBorderClass ?>">
                
                <!-- Card Header -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-dark fw-bold font-monospace">#ZIP-<?= (int)$order['order_id'] ?></span>
                    <?php if ($isReady): ?>
                      <span class="badge bg-success text-white rounded-pill"><i class="bi bi-box-seam me-1"></i>Ready</span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark rounded-pill"><i class="bi bi-fire me-1"></i>Prep Queue</span>
                    <?php endif; ?>
                  </div>
                  <span class="small text-muted font-monospace" style="font-size:0.75rem;">
                    <?= (int)$order['elapsed_minutes'] ?>m ago &bull; WSJF: <?= $order['wsjf_score'] ?? '1.0' ?>
                  </span>
                </div>

                <!-- Customer Tag -->
                <div class="d-flex align-items-center justify-content-between small px-2 py-1 bg-light rounded-3 mb-2">
                  <span class="fw-bold text-dark text-truncate">
                    <i class="bi bi-person me-1 text-muted"></i><?= e($order['customer_name']) ?>
                  </span>
                  <a href="tel:<?= e($order['customer_phone']) ?>" class="text-success text-decoration-none font-monospace small">
                    <?= e($order['customer_phone']) ?>
                  </a>
                </div>

                <!-- Dishes List (Compact) -->
                <div class="p-2 rounded-3 bg-light border mb-2 small flex-grow-1">
                  <div class="d-flex flex-column gap-1">
                    <?php foreach (array_slice(($order['items'] ?? []), 0, 3) as $it): ?>
                      <div class="d-flex align-items-center justify-content-between">
                        <span class="text-truncate">
                          <span class="badge <?= $it['is_veg'] ? 'bg-success' : 'bg-danger' ?> px-1 me-1" style="font-size:0.6rem;">
                            <?= (int)$it['quantity'] ?>x
                          </span>
                          <span class="text-dark fw-semibold"><?= e($it['item_name']) ?></span>
                        </span>
                        <span class="text-muted" style="font-size:0.7rem;"><?= (int)($it['prep_time_minutes'] ?? 15) ?>m</span>
                      </div>
                    <?php endforeach; ?>
                    <?php if (count($order['items'] ?? []) > 3): ?>
                      <div class="small text-muted fw-bold text-center pt-1">+<?= count($order['items']) - 3 ?> more items</div>
                    <?php endif; ?>
                  </div>

                  <?php if (!empty($order['order_notes'])): ?>
                    <div class="mt-1 pt-1 border-top text-warning fw-semibold text-truncate" style="font-size:0.72rem;">
                      <i class="bi bi-chat-left-text me-1"></i><?= e($order['order_notes']) ?>
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Action Footer -->
                <div class="pt-2 border-top d-flex align-items-center justify-content-between gap-2 mt-auto">
                  <div>
                    <div class="fw-bold text-forest fs-6"><?= format_inr($order['total_amount']) ?></div>
                    <span class="badge bg-light text-dark border" style="font-size:0.65rem;"><?= e($order['payment_mode'] ?? 'UPI') ?></span>
                  </div>

                  <form method="POST" action="<?= ADMIN_URL ?>/index.php" class="m-0 text-end">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="quick_status_update">
                    <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">

                    <?php if ($order['order_status'] === 'CONFIRMED'): ?>
                      <input type="hidden" name="new_status" value="COOKING">
                      <button type="submit" class="btn btn-warning btn-sm rounded-pill fw-bold px-3">
                        <i class="fa-solid fa-fire me-1"></i> Start Cooking
                      </button>
                    <?php elseif ($order['order_status'] === 'COOKING'): ?>
                      <input type="hidden" name="new_status" value="READY">
                      <button type="submit" class="btn btn-primary btn-sm rounded-pill fw-bold px-3">
                        <i class="bi bi-box-seam me-1"></i> Mark as Ready
                      </button>
                    <?php elseif ($order['order_status'] === 'READY'): ?>
                      <input type="hidden" name="new_status" value="OUT_FOR_DELIVERY">
                      <button type="submit" class="btn btn-success btn-sm rounded-pill fw-bold px-3" style="background:#2E6F40; border-color:#2E6F40;">
                        <i class="bi bi-bicycle me-1"></i> Dispatch
                      </button>
                    <?php endif; ?>
                  </form>
                </div>

              </div>
            </div>
          <?php endforeach; ?>

          <!-- STAGE B: ORDERS OUT FOR DELIVERY -->
          <?php foreach ($deliveryOrders as $dOrder): ?>
            <?php 
              $itemSearchStrings = array_map(fn($it) => $it['item_name'], $dOrder['items'] ?? []);
              $combinedSearchText = strtolower("ZIP-{$dOrder['order_id']} {$dOrder['customer_name']} {$dOrder['customer_phone']} {$dOrder['delivery_partner_name']} {$dOrder['address_line']} {$dOrder['pincode']} {$dOrder['delivery_otp']} " . implode(' ', $itemSearchStrings));
            ?>
            <div class="col-md-6 col-xl-4 live-order-card-col" 
                 data-stage="DELIVERING"
                 data-search="<?= e($combinedSearchText) ?>">
              <div class="order-live-card p-3 stage-delivering">
                
                <!-- Card Header -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-dark fw-bold font-monospace">#ZIP-<?= (int)$dOrder['order_id'] ?></span>
                    <span class="badge bg-primary text-white rounded-pill">
                      <i class="bi bi-bicycle me-1"></i> In Transit
                    </span>
                  </div>
                  <span class="small text-muted fw-bold">
                    <?= (int)$dOrder['transit_minutes'] ?>m transit
                  </span>
                </div>

                <!-- Courier Details -->
                <div class="p-2 rounded-3 bg-light border mb-2 small">
                  <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="fw-bold text-dark text-truncate">
                      <i class="bi bi-bicycle me-1 text-primary"></i><?= e($dOrder['delivery_partner_name'] ?: 'Express Courier') ?>
                    </span>
                    <a href="tel:<?= e($dOrder['delivery_partner_phone'] ?: '+919876543210') ?>" class="text-primary text-decoration-none fw-bold small">Call</a>
                  </div>
                  <div class="text-muted text-truncate" style="font-size:0.75rem;">
                    To: <?= e($dOrder['customer_name']) ?> (<?= e($dOrder['address_line'] ?: 'Indiranagar') ?>)
                  </div>
                </div>

                <!-- Dishes In Transit -->
                <div class="p-2 rounded-3 bg-white border mb-2 small flex-grow-1">
                  <div class="d-flex flex-column gap-1">
                    <?php foreach (array_slice(($dOrder['items'] ?? []), 0, 3) as $it): ?>
                      <div class="d-flex align-items-center justify-content-between">
                        <span class="text-truncate">
                          <span class="badge <?= $it['is_veg'] ? 'bg-success' : 'bg-danger' ?> px-1 me-1" style="font-size:0.6rem;"><?= (int)$it['quantity'] ?>x</span>
                          <span class="text-dark"><?= e($it['item_name']) ?></span>
                        </span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>

                <!-- OTP & Payment Status Box -->
                <div class="d-flex align-items-center justify-content-between p-2 rounded-3 mb-2" style="background:#FFFBEB; border:1px dashed #F59E0B;">
                  <div>
                    <span class="small fw-bold text-muted d-block" style="font-size:0.68rem;">HANDSHAKE OTP:</span>
                    <span class="delivery-otp-pill"><?= e($dOrder['delivery_otp'] ?: '123456') ?></span>
                  </div>
                  <div class="text-end">
                    <?php if (($dOrder['payment_mode'] ?? 'UPI') === 'COD'): ?>
                      <?php if (($dOrder['payment_status'] ?? '') === 'SUCCESSFUL'): ?>
                        <span class="badge bg-success text-white" style="font-size:0.7rem;"><i class="bi bi-cash-stack me-1"></i>COD Paid</span>
                      <?php else: ?>
                        <span class="badge bg-warning text-dark border border-warning" style="font-size:0.7rem;"><i class="bi bi-hourglass-split me-1"></i>Collect COD</span>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="badge bg-forest text-white" style="font-size:0.7rem;"><i class="bi bi-shield-check me-1"></i>Online Paid</span>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Footer -->
                <div class="pt-2 border-top d-flex align-items-center justify-content-between gap-2 mt-auto">
                  <div class="fw-bold text-forest">
                    <?= format_inr($dOrder['total_amount']) ?>
                  </div>

                  <form method="POST" action="<?= ADMIN_URL ?>/index.php" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="quick_status_update">
                    <input type="hidden" name="order_id" value="<?= (int)$dOrder['order_id'] ?>">
                    <input type="hidden" name="new_status" value="DELIVERED">
                    <button type="submit" class="btn btn-success btn-sm rounded-pill fw-bold px-3">
                      <i class="bi bi-check2-circle me-1"></i> Delivered
                    </button>
                  </form>
                </div>

              </div>
            </div>
          <?php endforeach; ?>

        </div>

        <!-- Empty Search Zero State -->
        <div class="text-center py-5 d-none" id="zeroSearchState">
          <i class="bi bi-search fs-1 text-muted opacity-50 mb-2 d-block"></i>
          <h6 class="fw-bold text-dark">No live orders match your search keyword</h6>
          <p class="small text-muted mb-3">Try clearing search or switching to another filter chip.</p>
          <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-3" id="resetDashboardSearchBtn">
            Reset Search
          </button>
        </div>

      <?php endif; ?>

    </div>

    <!-- =========================================================================
         TAB 2: RECENT TRANSACTIONS LEDGER (MASTER TABLE VIEW)
         ========================================================================= -->
    <div class="tab-pane fade" id="tabPaneLedger" role="tabpanel">
      <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0" id="recentTransactionsTable">
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
              <tr class="transaction-row" data-search="<?= strtolower(e("ZIP-{$ro['order_id']} {$ro['full_name']} {$ro['phone_number']} {$ro['order_status']} {$ro['payment_mode']}")) ?>">
                <td class="fw-bold font-monospace text-dark">
                  #ZIP-<?= (int)$ro['order_id'] ?>
                  <?php if (!empty($ro['delivery_otp'])): ?>
                    <div class="small text-muted font-monospace" style="font-size:0.7rem;">OTP: <?= e($ro['delivery_otp']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="fw-bold text-dark"><?= e($ro['full_name']) ?></div>
                  <div class="small text-muted font-monospace"><?= e($ro['phone_number']) ?></div>
                </td>
                <td class="text-muted"><?= date('d M, h:i A', strtotime($ro['placed_at'])) ?></td>
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
                    
                    <select name="new_status" class="form-select form-select-sm rounded-3" style="max-width: 130px;">
                      <option value="CONFIRMED" <?= ($ro['order_status'] === 'CONFIRMED') ? 'selected' : '' ?>>Confirmed</option>
                      <option value="COOKING" <?= ($ro['order_status'] === 'COOKING') ? 'selected' : '' ?>>Cooking</option>
                      <option value="READY" <?= ($ro['order_status'] === 'READY') ? 'selected' : '' ?>>Ready</option>
                      <option value="OUT_FOR_DELIVERY" <?= ($ro['order_status'] === 'OUT_FOR_DELIVERY') ? 'selected' : '' ?>>In Transit</option>
                      <option value="DELIVERED" <?= ($ro['order_status'] === 'DELIVERED') ? 'selected' : '' ?>>Delivered</option>
                      <option value="CANCELLED" <?= ($ro['order_status'] === 'CANCELLED') ? 'selected' : '' ?>>Cancelled</option>
                    </select>

                    <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-2">Update</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- =========================================================================
         TAB 3: KITCHEN COOK-LINE DEMAND (DETAILED PREPARATION SUMMARY)
         ========================================================================= -->
    <div class="tab-pane fade" id="tabPaneCookline" role="tabpanel">
      <div class="p-3 bg-light rounded-4 mb-3 border">
        <h6 class="fw-bold font-outfit text-dark mb-1">
          <i class="fa-solid fa-fire text-warning me-2"></i>Active Kitchen Cook-Line Demand
        </h6>
        <p class="text-muted small mb-0">
          Real-time aggregated portion demand currently on stoves/ovens across all active kitchen orders. Prioritized via WSJF algorithm.
        </p>
      </div>

      <div class="row g-3">
        <?php if (empty($cookLoadSummary)): ?>
          <div class="col-12 text-center py-5 text-muted">
            <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
            All incoming tickets have been cooked and plated!
          </div>
        <?php else: ?>
          <?php foreach ($cookLoadSummary as $item): ?>
            <div class="col-md-4 col-xl-3">
              <div class="p-3 rounded-4 bg-white border shadow-sm h-100 d-flex align-items-center justify-content-between">
                <div>
                  <div class="d-flex align-items-center gap-1 mb-1">
                    <?= $item['is_veg'] ? '<i class="bi bi-circle-fill text-success" style="font-size:0.6rem;"></i>' : '<i class="bi bi-triangle-fill text-danger" style="font-size:0.6rem;"></i>' ?>
                    <strong class="text-dark"><?= e($item['item_name']) ?></strong>
                  </div>
                  <span class="small text-muted">Active Cook Demand</span>
                </div>
                <div class="fs-4 fw-bold font-outfit text-forest">
                  <?= (int)$item['total_qty'] ?>x
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<!-- Interactive Client-side Search and Stage Filter Scripts -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('dashboardLiveSearch');
  const clearBtn = document.getElementById('clearDashboardSearch');
  const resetBtn = document.getElementById('resetDashboardSearchBtn');
  const stageBtns = document.querySelectorAll('.filter-stage-btn');
  const cardCols = document.querySelectorAll('.live-order-card-col');
  const zeroState = document.getElementById('zeroSearchState');
  const counterBox = document.getElementById('searchResultsCounter');
  const countSpan = document.getElementById('visibleCardCount');
  const tableRows = document.querySelectorAll('#recentTransactionsTable tbody tr');

  let currentStage = 'ALL';

  function filterCards() {
    const q = (searchInput?.value || '').trim().toLowerCase();
    let visibleCount = 0;

    if (clearBtn) {
      clearBtn.classList.toggle('d-none', q.length === 0);
    }

    cardCols.forEach(col => {
      const colStage = col.getAttribute('data-stage');
      const colSearch = col.getAttribute('data-search') || '';

      const matchesStage = (currentStage === 'ALL') || (colStage === currentStage);
      const matchesQuery = (q === '') || colSearch.includes(q);

      if (matchesStage && matchesQuery) {
        col.classList.remove('d-none');
        visibleCount++;
      } else {
        col.classList.add('d-none');
      }
    });

    tableRows.forEach(row => {
      const rowSearch = row.getAttribute('data-search') || '';
      const matchesQuery = (q === '') || rowSearch.includes(q);
      row.classList.toggle('d-none', !matchesQuery);
    });

    if (zeroState) {
      zeroState.classList.toggle('d-none', visibleCount > 0 || cardCols.length === 0);
    }

    if (counterBox && countSpan) {
      if (q.length > 0 || currentStage !== 'ALL') {
        counterBox.classList.remove('d-none');
        countSpan.textContent = visibleCount;
      } else {
        counterBox.classList.add('d-none');
      }
    }
  }

  if (searchInput) {
    searchInput.addEventListener('input', filterCards);
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      searchInput.value = '';
      filterCards();
      searchInput.focus();
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      if (searchInput) searchInput.value = '';
      currentStage = 'ALL';
      stageBtns.forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-stage') === 'ALL');
      });
      filterCards();
    });
  }

  stageBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      stageBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentStage = btn.getAttribute('data-stage');
      filterCards();
    });
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
