<?php
/**
 * Dedicated Kitchen Display System (KDS) & Operations Dashboard
 * Zippy - Online Food Ordering Platform
 * MCSP-232: MCA Major Project | Student: Pooja JP
 * 
 * Standalone Kitchen Operations Workspace (Monochrome Atelier Edition):
 * 1. Accept Order (Incoming -> Stoves/Cooking)
 * 2. Prepare Order (In Cooking -> Plated/Ready)
 * 3. Hand Over to Delivery Boy (Ready -> Out for Delivery)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/WSJFQueue.php';

// Enforce Kitchen or Admin Access
require_kitchen();
$currentUser = current_user();

// Handle Synchronous State Transitions (POST fallback)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid or expired session token.');
    } else {
        $action = $_POST['action'];

        // 1. Advance Order State in KDS Workflow
        if ($action === 'kds_advance') {
            $orderId = (int)($_POST['order_id'] ?? 0);
            $targetStatus = clean_input($_POST['target_status'] ?? '');
            $selectedRider = clean_input($_POST['rider_name'] ?? '');

            $cur = db_fetch_one("SELECT * FROM `orders` WHERE `order_id` = :id", [':id' => $orderId]);
            if ($cur) {
                $oldStatus = $cur['order_status'];

                // Update order
                db_query("UPDATE `orders` SET `order_status` = :st WHERE `order_id` = :id", [
                    ':st' => $targetStatus,
                    ':id' => $orderId
                ]);

                // Audit trail logging
                db_query("
                    INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`) 
                    VALUES (:oid, :old, :new, :uid, NOW())
                ", [
                    ':oid' => $orderId,
                    ':old' => $oldStatus,
                    ':new' => $targetStatus,
                    ':uid' => $currentUser['id'] ?? null
                ]);

                // Handle Handover to Delivery Boy
                if ($targetStatus === 'OUT_FOR_DELIVERY') {
                    $dispatch = db_fetch_one("SELECT * FROM `delivery_dispatches` WHERE `order_id` = :id", [':id' => $orderId]);
                    $finalRider = !empty($selectedRider) ? $selectedRider : ($dispatch['delivery_partner_name'] ?? 'Suresh Kumar (Rider)');

                    if ($dispatch) {
                        db_query("
                            UPDATE `delivery_dispatches` 
                            SET `delivery_partner_name` = :rname,
                                `dispatched_at` = NOW(),
                                `delivery_status` = 'IN_TRANSIT'
                            WHERE `order_id` = :id
                        ", [
                            ':rname' => $finalRider,
                            ':id' => $orderId
                        ]);
                    } else {
                        $newOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                        db_query("
                            INSERT INTO `delivery_dispatches` 
                            (`order_id`, `delivery_partner_name`, `delivery_partner_phone`, `delivery_otp`, `assigned_at`, `dispatched_at`, `delivery_status`)
                            VALUES (:id, :rname, '+91 96320 98765', :otp, NOW(), NOW(), 'IN_TRANSIT')
                        ", [
                            ':id' => $orderId,
                            ':rname' => $finalRider,
                            ':otp' => $newOtp
                        ]);
                    }

                    set_flash('success', "Order #ZIP-{$orderId} handed over to Delivery Boy ({$finalRider}) for fulfillment.");
                } elseif ($targetStatus === 'COOKING') {
                    set_flash('success', "Order #ZIP-{$orderId} accepted! Transferred to Stoves for cooking.");
                } elseif ($targetStatus === 'READY') {
                    set_flash('success', "Order #ZIP-{$orderId} plated & bagged! Ready at courier counter.");
                }

                header("Location: " . KITCHEN_URL . "/index.php");
                exit;
            }
        }

        // 2. Toggle Menu Item Stock (86-List)
        if ($action === 'toggle_stock') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $newStockState = (int)($_POST['is_available'] ?? 0);
            db_query("UPDATE `menu_items` SET `is_available` = :av WHERE `item_id` = :id", [
                ':av' => $newStockState,
                ':id' => $itemId
            ]);
            set_flash('info', "Item availability updated.");
            header("Location: " . KITCHEN_URL . "/index.php");
            exit;
        }
    }
}

// Fetch Active Orders Sorted by WSJF Priority
$allActiveOrders = WSJFQueue::getPrioritizedOrders();

// Group by Kanban Swimlanes
$laneNew = array_values(array_filter($allActiveOrders, fn($o) => in_array($o['order_status'], ['PLACED', 'CONFIRMED'])));
$laneCooking = array_values(array_filter($allActiveOrders, fn($o) => $o['order_status'] === 'COOKING'));
$laneReady = array_values(array_filter($allActiveOrders, fn($o) => $o['order_status'] === 'READY'));

// Fetch Dispatches and Riders details for Ready lane
$readyOrderIds = array_column($laneReady, 'order_id');
$dispatchesByOrder = [];
if (!empty($readyOrderIds)) {
    $placeholders = implode(',', array_fill(0, count($readyOrderIds), '?'));
    $dispatches = db_fetch_all("SELECT * FROM `delivery_dispatches` WHERE `order_id` IN ($placeholders)", $readyOrderIds);
    foreach ($dispatches as $d) {
        $dispatchesByOrder[$d['order_id']] = $d;
    }
}

// Fetch active riders list from users table (role_id = 4)
$ridersList = db_fetch_all("
    SELECT user_id, full_name, phone_number 
    FROM `users` 
    WHERE `role_id` = 4 AND `is_active` = 1
");

// Fetch Dispatched / Completed Orders Today
$recentHandedOver = db_fetch_all("
    SELECT o.*, u.full_name AS customer_name,
           d.delivery_partner_name, d.delivery_otp, d.dispatched_at, d.delivered_at
    FROM `orders` o
    JOIN `users` u ON o.user_id = u.user_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    WHERE o.order_status IN ('OUT_FOR_DELIVERY', 'DELIVERED')
    ORDER BY COALESCE(d.dispatched_at, o.placed_at) DESC
    LIMIT 20
");

// Fetch Menu Items for 86-List modal
$allMenuItems = db_fetch_all("
    SELECT m.*, c.category_name 
    FROM `menu_items` m
    JOIN `categories` c ON m.category_id = c.category_id
    ORDER BY c.display_order ASC, m.item_name ASC
");

// Count stats
$countNew = count($laneNew);
$countCooking = count($laneCooking);
$countReady = count($laneReady);
$countHandedOver = count($recentHandedOver);
$totalActive = $countNew + $countCooking + $countReady;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kitchen Atelier & KDS | Zippy Operations</title>

  <!-- Google Fonts: Plus Jakarta Sans & Outfit & Space Grotesk -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

  <!-- Bootstrap 5.3.3 & FontAwesome & Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

  <style>
    /* ==========================================================================
       CLASSY BLACK & WHITE MONOCHROME DESIGN SYSTEM
       High-Contrast Haute Cuisine Digital Expeditor
       ========================================================================== */
    :root {
      --noir-black: #000000;
      --noir-charcoal: #121212;
      --noir-card-border: #E4E4E7;
      --noir-border-strong: #000000;
      --noir-muted: #71717A;
      --noir-muted-light: #A1A1AA;
      --noir-surface: #FFFFFF;
      --noir-canvas: #FAFAFA;
      --noir-subtle: #F4F4F5;
      --noir-subtle-hover: #EBEBEF;
      --noir-badge-bg: #F4F4F5;
      --noir-badge-border: #E4E4E7;
      --noir-shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
      --noir-shadow-md: 0 8px 24px rgba(0, 0, 0, 0.06);
      --noir-shadow-lg: 0 16px 36px rgba(0, 0, 0, 0.09);
    }

    body {
      background-color: var(--noir-canvas);
      color: var(--noir-charcoal);
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-height: 100vh;
      overflow-x: hidden;
      letter-spacing: -0.01em;
    }

    .font-outfit { font-family: 'Outfit', sans-serif; }
    .font-mono { font-family: 'Space Grotesk', monospace; }

    /* Top Navigation Bar */
    .kds-navbar {
      background: #FFFFFF;
      border-bottom: 1px solid #E4E4E7;
      padding: 14px 28px;
      position: sticky;
      top: 0;
      z-index: 1030;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    }

    .brand-mark {
      font-family: 'Outfit', sans-serif;
      font-weight: 900;
      font-size: 1.35rem;
      letter-spacing: -0.03em;
      color: #000000;
      text-transform: uppercase;
      display: inline-flex;
      align-items: center;
      gap: 10px;
    }

    .brand-tag {
      background: #000000;
      color: #FFFFFF;
      font-size: 0.68rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      padding: 4px 10px;
      border-radius: 4px;
    }

    .live-dot {
      width: 7px;
      height: 7px;
      background-color: #000000;
      border-radius: 50%;
      display: inline-block;
      animation: pulseDot 1.8s infinite;
    }

    @keyframes pulseDot {
      0% { transform: scale(0.9); opacity: 1; }
      50% { transform: scale(1.4); opacity: 0.4; }
      100% { transform: scale(0.9); opacity: 1; }
    }

    /* KPI Summary Cards */
    .kpi-card {
      background: #FFFFFF;
      border: 1px solid var(--noir-card-border);
      border-radius: 14px;
      padding: 20px 24px;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: var(--noir-shadow-sm);
    }
    .kpi-card:hover {
      border-color: #000000;
      transform: translateY(-2px);
      box-shadow: var(--noir-shadow-md);
    }

    .kpi-number {
      font-family: 'Outfit', sans-serif;
      font-size: 2.5rem;
      font-weight: 800;
      line-height: 1;
      color: #000000;
      letter-spacing: -0.03em;
    }

    .kpi-label {
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--noir-muted);
    }

    /* Tab Controls */
    .nav-pills-kds .nav-link {
      background: #FFFFFF;
      color: var(--noir-muted);
      border: 1px solid var(--noir-card-border);
      border-radius: 100px;
      font-size: 0.85rem;
      font-weight: 600;
      padding: 8px 20px;
      transition: all 0.18s ease;
    }
    .nav-pills-kds .nav-link:hover {
      color: #000000;
      border-color: #A1A1AA;
    }
    .nav-pills-kds .nav-link.active {
      background: #000000 !important;
      color: #FFFFFF !important;
      border-color: #000000 !important;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    /* Swimlane Columns */
    .swimlane-col {
      background: #F4F4F6;
      border: 1px solid var(--noir-card-border);
      border-radius: 18px;
      padding: 22px;
      height: 100%;
      min-height: calc(100vh - 290px);
      display: flex;
      flex-direction: column;
    }

    .swimlane-header {
      padding-bottom: 16px;
      margin-bottom: 18px;
      border-bottom: 1px solid #E4E4E7;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .swimlane-title {
      font-family: 'Outfit', sans-serif;
      font-weight: 700;
      font-size: 0.95rem;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #000000;
      margin-bottom: 0;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .swimlane-counter {
      background: #000000;
      color: #FFFFFF;
      font-family: 'Space Grotesk', monospace;
      font-size: 0.8rem;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 100px;
    }

    /* Ticket Docket Card (Classy Michelin Docket) */
    .ticket-card {
      background: #FFFFFF;
      border: 1px solid var(--noir-card-border);
      border-radius: 16px;
      padding: 20px;
      margin-bottom: 16px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
      transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
      position: relative;
    }
    .ticket-card:hover {
      border-color: #000000;
      transform: translateY(-3px);
      box-shadow: var(--noir-shadow-md);
    }

    .ticket-card.card-lane-new {
      border-top: 4px solid #000000;
    }
    .ticket-card.card-lane-cooking {
      border-top: 4px solid #3F3F46;
    }
    .ticket-card.card-lane-ready {
      border-top: 4px solid #18181B;
    }

    .ticket-tag-pill {
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      padding: 4px 10px;
      border-radius: 100px;
      background: #F4F4F5;
      color: #18181B;
      border: 1px solid #E4E4E7;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    .ticket-tag-dark {
      background: #000000;
      color: #FFFFFF;
      border-color: #000000;
    }

    .ticket-tag-outline {
      background: #FFFFFF;
      color: #000000;
      border: 1px solid #000000;
    }

    .ticket-items-box {
      background: #FAFAFA;
      border: 1px solid #F0F0F2;
      border-radius: 12px;
      padding: 14px;
      margin: 14px 0;
    }

    /* Minimalist Veg / Non-Veg Mark */
    .monochrome-veg {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 14px;
      height: 14px;
      border: 1.5px solid #000000;
      border-radius: 2px;
      padding: 1px;
    }
    .monochrome-veg::after {
      content: '';
      width: 6px;
      height: 6px;
      background-color: #000000;
      border-radius: 50%;
    }

    .monochrome-nonveg {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 14px;
      height: 14px;
      border: 1.5px solid #000000;
      border-radius: 2px;
      padding: 1px;
    }
    .monochrome-nonveg::after {
      content: '';
      width: 0;
      height: 0;
      border-left: 4px solid transparent;
      border-right: 4px solid transparent;
      border-bottom: 6px solid #000000;
    }

    /* Checklist interaction */
    .dish-checkbox {
      width: 17px;
      height: 17px;
      border: 1.5px solid #71717A;
      cursor: pointer;
      accent-color: #000000;
    }
    .dish-checkbox:checked + label {
      text-decoration: line-through;
      color: #A1A1AA;
    }

    /* Classy Primary Buttons */
    .btn-noir-primary {
      background: #000000;
      color: #FFFFFF;
      font-weight: 700;
      font-size: 0.88rem;
      letter-spacing: 0.02em;
      border: 1px solid #000000;
      border-radius: 100px;
      padding: 11px 22px;
      transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
    }
    .btn-noir-primary:hover {
      background: #27272A;
      color: #FFFFFF;
      border-color: #27272A;
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }
    .btn-noir-primary:active {
      transform: translateY(0);
    }

    .btn-noir-outline {
      background: #FFFFFF;
      color: #000000;
      font-weight: 700;
      font-size: 0.85rem;
      border: 1.5px solid #000000;
      border-radius: 100px;
      padding: 9px 18px;
      transition: all 0.18s ease;
    }
    .btn-noir-outline:hover {
      background: #000000;
      color: #FFFFFF;
    }

    .btn-kot-print {
      background: #FFFFFF;
      color: #18181B;
      border: 1px solid #E4E4E7;
      border-radius: 100px;
      width: 42px;
      height: 42px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: all 0.18s ease;
    }
    .btn-kot-print:hover {
      background: #000000;
      color: #FFFFFF;
      border-color: #000000;
    }

    /* Modal Styling */
    .modal-content-noir {
      background: #FFFFFF;
      color: #18181B;
      border: 1px solid #000000;
      border-radius: 20px;
      box-shadow: var(--noir-shadow-lg);
    }
    .modal-header-noir {
      border-bottom: 1px solid #E4E4E7;
      padding: 20px 24px;
    }
    .modal-footer-noir {
      border-top: 1px solid #E4E4E7;
      padding: 16px 24px;
    }

    /* Search Input */
    .search-input-noir {
      background: #FFFFFF;
      border: 1px solid var(--noir-card-border);
      color: #000000;
      border-radius: 100px;
      padding: 8px 18px;
      font-size: 0.85rem;
      transition: all 0.18s ease;
    }
    .search-input-noir:focus {
      border-color: #000000;
      outline: none;
      box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.08);
    }

    /* Instruction Box */
    .instruction-callout {
      background: #000000;
      color: #FFFFFF;
      border-radius: 8px;
      padding: 10px 14px;
      margin-bottom: 12px;
      font-size: 0.82rem;
      font-weight: 600;
      letter-spacing: 0.01em;
    }

    /* Rider Box */
    .rider-dispatch-box {
      background: #F8F9FA;
      border: 1px solid #E4E4E7;
      border-radius: 12px;
      padding: 14px;
      margin-bottom: 14px;
    }
  </style>
</head>
<body>

  <!-- Top Navigation Bar -->
  <header class="kds-navbar">
    <div class="container-fluid px-2 px-md-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
      
      <!-- Brand & Status -->
      <div class="d-flex align-items-center gap-3">
        <a href="<?= KITCHEN_URL ?>/index.php" class="text-decoration-none">
          <div class="brand-mark">
            <span>ZIPPY<span style="font-weight:300;">.</span></span>
            <span class="brand-tag">ATELIER KDS</span>
          </div>
        </a>

        <div class="d-none d-sm-flex align-items-center gap-2 px-3 py-1 rounded-pill" style="background:#F4F4F5; border:1px solid #E4E4E7;">
          <span class="live-dot"></span>
          <span class="small font-mono fw-bold" style="font-size: 0.72rem; letter-spacing:0.06em; color:#000000;">STATION ONLINE</span>
        </div>
      </div>

      <!-- Controls & Profile -->
      <div class="d-flex align-items-center gap-2 gap-md-3">
        
        <!-- Live Station Clock -->
        <div class="d-none d-lg-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-light border border-secondary-subtle small font-mono">
          <i class="bi bi-clock-history text-dark"></i>
          <span id="kdsLiveClock" class="fw-bold text-dark">--:--:--</span>
        </div>

        <!-- Audio Alert Chime Toggle -->
        <button id="btnAudioToggle" class="btn btn-sm btn-noir-outline rounded-pill px-3" onclick="toggleAudioChime()" title="Toggle Web Audio Bell Chime">
          <i id="audioIcon" class="bi bi-bell-fill me-1"></i> <span id="audioText">SOUND: ON</span>
        </button>

        <!-- Auto-Refresh Toggle -->
        <button id="btnRefreshToggle" class="btn btn-sm btn-noir-outline rounded-pill px-3" onclick="toggleAutoRefresh()" title="Pause or Resume automated sync">
          <i class="bi bi-arrow-clockwise me-1"></i> <span id="refreshText">SYNC (12s)</span>
        </button>

        <!-- 86-List (Inventory Availability) -->
        <button class="btn btn-sm btn-noir-primary px-3 rounded-pill" data-bs-toggle="modal" data-bs-target="#modal86List">
          <i class="fa-solid fa-boxes-stacked me-1"></i> 86-LIST
        </button>

        <!-- Chef Account Dropdown -->
        <div class="dropdown">
          <button class="btn btn-sm bg-white rounded-pill px-3 py-2 d-flex align-items-center gap-2 border" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-color:#E4E4E7 !important;">
            <div class="rounded-circle bg-black text-white d-inline-flex align-items-center justify-content-center" style="width:24px; height:24px; font-size:0.7rem; font-weight:800;">
              CS
            </div>
            <span class="small fw-bold text-dark d-none d-sm-inline"><?= e($currentUser['name'] ?? 'Chef Sanjeev') ?></span>
            <i class="bi bi-chevron-down small text-muted"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-lg py-2" style="background:#FFFFFF; border:1px solid #000000; border-radius:12px; min-width: 220px;">
            <li class="px-3 py-2 border-bottom">
              <div class="fw-bold text-dark small font-outfit"><?= e($currentUser['name']) ?></div>
              <div class="text-muted" style="font-size:0.75rem;"><?= e($currentUser['email']) ?></div>
            </li>
            <li><a class="dropdown-item text-dark small py-2" href="<?= CUSTOMER_URL ?>/index.php" target="_blank"><i class="bi bi-shop me-2"></i> Customer Storefront</a></li>
            <li><a class="dropdown-item text-dark small py-2" href="<?= DELIVERY_URL ?>/index.php" target="_blank"><i class="bi bi-bicycle me-2"></i> Delivery Fleet App</a></li>
            <?php if (is_admin()): ?>
              <li><a class="dropdown-item text-dark small py-2" href="<?= ADMIN_URL ?>/index.php"><i class="bi bi-speedometer2 me-2"></i> Admin Console</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger fw-semibold small py-2" href="<?= KITCHEN_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Chef Sign Off</a></li>
          </ul>
        </div>

      </div>

    </div>
  </header>

  <!-- Main Container -->
  <main class="container-fluid px-3 px-lg-4 py-4">

    <!-- Flash Notifications -->
    <div id="flashContainer">
      <?php display_flash(); ?>
    </div>

    <!-- Live Audio Notification Banner -->
    <div id="newOrderBanner" class="alert d-none align-items-center justify-content-between rounded-4 py-3 px-4 mb-4 shadow-sm" style="background: #000000; color:#FFFFFF; border:1px solid #000000;">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle bg-white text-black p-2 d-inline-flex align-items-center justify-content-center" style="width:38px; height:38px;">
          <i class="fa-solid fa-bell"></i>
        </div>
        <div>
          <h6 class="fw-bold mb-0 font-outfit" id="newOrderBannerTitle">NEW ORDER TICKETS RECEIVED</h6>
          <span class="small text-white-50" id="newOrderBannerDesc">Fresh customer orders placed. Review incoming queue below.</span>
        </div>
      </div>
      <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-4 fw-bold" onclick="dismissOrderBanner()">
        Acknowledge
      </button>
    </div>

    <!-- KPI Summary Row -->
    <div class="row g-3 mb-4">
      
      <!-- Card 1: Incoming to Accept -->
      <div class="col-6 col-lg-3">
        <div class="kpi-card d-flex align-items-center justify-content-between">
          <div>
            <div class="kpi-label mb-1">01 / Incoming to Accept</div>
            <div class="kpi-number" id="statNew"><?= $countNew ?></div>
            <div class="small text-muted mt-1"><i class="bi bi-hourglass-split me-1 text-dark"></i>Awaiting Stoves</div>
          </div>
          <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-light border" style="width:52px; height:52px; font-size:1.3rem;">
            <i class="bi bi-inbox text-dark"></i>
          </div>
        </div>
      </div>

      <!-- Card 2: In Preparation -->
      <div class="col-6 col-lg-3">
        <div class="kpi-card d-flex align-items-center justify-content-between">
          <div>
            <div class="kpi-label mb-1">02 / Stoves Preparation</div>
            <div class="kpi-number" id="statCooking"><?= $countCooking ?></div>
            <div class="small text-muted mt-1"><i class="fa-solid fa-fire-burner me-1 text-dark"></i>Active Sauté & Bake</div>
          </div>
          <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-light border" style="width:52px; height:52px; font-size:1.3rem;">
            <i class="fa-solid fa-fire text-dark"></i>
          </div>
        </div>
      </div>

      <!-- Card 3: Ready for Handover -->
      <div class="col-6 col-lg-3">
        <div class="kpi-card d-flex align-items-center justify-content-between">
          <div>
            <div class="kpi-label mb-1">03 / Courier Handover</div>
            <div class="kpi-number" id="statReady"><?= $countReady ?></div>
            <div class="small text-muted mt-1"><i class="bi bi-bag-check me-1 text-dark"></i>Bagged & Plated</div>
          </div>
          <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-light border" style="width:52px; height:52px; font-size:1.3rem;">
            <i class="bi bi-bag-check text-dark"></i>
          </div>
        </div>
      </div>

      <!-- Card 4: Handed Over Today -->
      <div class="col-6 col-lg-3">
        <div class="kpi-card d-flex align-items-center justify-content-between">
          <div>
            <div class="kpi-label mb-1">04 / Dispatched Today</div>
            <div class="kpi-number" id="statHandedOver"><?= $countHandedOver ?></div>
            <div class="small text-muted mt-1"><i class="bi bi-bicycle me-1 text-dark"></i>With Delivery Fleet</div>
          </div>
          <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-light border" style="width:52px; height:52px; font-size:1.3rem;">
            <i class="bi bi-check2-all text-dark"></i>
          </div>
        </div>
      </div>

    </div>

    <!-- Filter & Station Navigation Toolbar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 p-3 rounded-4 bg-white border" style="border-color:#E4E4E7 !important; box-shadow: var(--noir-shadow-sm);">
      
      <!-- View Mode Tabs -->
      <ul class="nav nav-pills nav-pills-kds gap-2" id="kdsStationTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="tab-all-btn" data-bs-toggle="pill" data-bs-target="#tab-all" type="button" role="tab">
            <i class="fa-solid fa-table-columns me-1"></i> Kanban Swimlanes
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-new-btn" data-bs-toggle="pill" data-bs-target="#tab-new" type="button" role="tab">
            1. Incoming (<span id="tabCountNew"><?= $countNew ?></span>)
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-cooking-btn" data-bs-toggle="pill" data-bs-target="#tab-cooking" type="button" role="tab">
            2. Cooking (<span id="tabCountCooking"><?= $countCooking ?></span>)
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-ready-btn" data-bs-toggle="pill" data-bs-target="#tab-ready" type="button" role="tab">
            3. Courier (<span id="tabCountReady"><?= $countReady ?></span>)
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-history-btn" data-bs-toggle="pill" data-bs-target="#tab-history" type="button" role="tab">
            <i class="bi bi-clock-history me-1"></i> Dispatch Log
          </button>
        </li>
      </ul>

      <!-- Ticket Search & WSJF Indicator -->
      <div class="d-flex align-items-center gap-3">
        <div class="position-relative" style="max-width: 260px;">
          <input type="text" id="kdsSearchInput" class="search-input-noir w-100" placeholder="Filter docket # or dish..." onkeyup="filterTickets()">
        </div>
        <span class="ticket-tag-pill ticket-tag-dark d-none d-lg-inline-flex">
          <i class="fa-solid fa-arrow-down-short-wide me-1"></i> WSJF RANKED
        </span>
      </div>

    </div>

    <!-- Tab Contents -->
    <div class="tab-content" id="kdsStationTabContent">

      <!-- ====================================================================
           TAB 1: 3-SWIMLANE KANBAN (DEFAULT ATELIER VIEW)
           ==================================================================== -->
      <div class="tab-pane fade show active" id="tab-all" role="tabpanel">
        <div class="row g-4">
          
          <!-- Column 1: Incoming to Accept -->
          <div class="col-lg-4">
            <div class="swimlane-col">
              <div class="swimlane-header">
                <div class="swimlane-title">
                  <span class="badge bg-black rounded-circle p-1" style="width:8px; height:8px;"></span>
                  <span>1. Incoming Orders</span>
                </div>
                <span class="swimlane-counter" id="laneNewCountBadge"><?= $countNew ?></span>
              </div>

              <!-- New Order Cards -->
              <div class="d-flex flex-column gap-3" id="laneNewContainer">
                <?php if (empty($laneNew)): ?>
                  <div class="text-center py-5 text-muted">
                    <i class="bi bi-check2-circle fs-1 text-muted d-block mb-2"></i>
                    <div class="small fw-semibold">No pending incoming tickets.</div>
                    <div class="small text-muted">Station ready for new orders.</div>
                  </div>
                <?php else: ?>
                  <?php foreach ($laneNew as $t): ?>
                    <div class="ticket-card card-lane-new ticket-item" data-ticket-id="<?= $t['order_id'] ?>" data-search="<?= strtolower(e($t['customer_name'] . ' ' . $t['order_id'])) ?>">
                      
                      <!-- Ticket Header -->
                      <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                          <span class="font-mono fw-bold fs-6 text-dark">
                            #ZIP-<?= (int)$t['order_id'] ?>
                          </span>
                          <span class="ticket-tag-pill ticket-tag-dark ms-1">
                            <?= e($t['urgency_badge']['label']) ?>
                          </span>
                        </div>
                        <span class="ticket-tag-pill ticket-tag-outline">
                          WAIT: <?= (int)$t['elapsed_minutes'] ?>m
                        </span>
                      </div>

                      <!-- Customer Meta -->
                      <div class="d-flex justify-content-between small text-muted mb-2">
                        <span>Guest: <strong class="text-dark"><?= e($t['customer_name']) ?></strong></span>
                        <span class="font-mono text-dark fw-bold">WSJF: <?= $t['wsjf_score'] ?></span>
                      </div>

                      <!-- Customer Notes / Instructions -->
                      <?php if (!empty($t['order_notes'])): ?>
                        <div class="instruction-callout">
                          <i class="bi bi-chat-left-quote me-1"></i> <?= e($t['order_notes']) ?>
                        </div>
                      <?php endif; ?>

                      <!-- Line Items List -->
                      <div class="ticket-items-box">
                        <div class="text-muted small mb-2 fw-bold text-uppercase" style="font-size:0.68rem; letter-spacing:0.05em;">
                          Docket Items (<?= (int)$t['total_portions'] ?> Portions):
                        </div>
                        <?php foreach ($t['items'] as $it): ?>
                          <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle small">
                            <div class="d-flex align-items-center gap-2">
                              <span class="<?= $it['is_veg'] ? 'monochrome-veg' : 'monochrome-nonveg' ?>"></span>
                              <span class="fw-bold font-mono text-dark">[<?= (int)$it['quantity'] ?>x]</span>
                              <span class="text-dark fw-medium"><?= e($it['item_name']) ?></span>
                            </div>
                            <span class="text-muted font-mono" style="font-size:0.75rem;"><?= (int)$it['prep_time_minutes'] ?>m</span>
                          </div>
                        <?php endforeach; ?>
                      </div>

                      <!-- Action Buttons: Accept & Print KOT -->
                      <div class="d-flex gap-2">
                        <button type="button" class="btn-kot-print" onclick="openKOT(<?= (int)$t['order_id'] ?>)" title="Print Thermal KOT Slip">
                          <i class="bi bi-printer"></i>
                        </button>
                        <form method="POST" action="<?= KITCHEN_URL ?>/index.php" class="flex-grow-1 m-0" onsubmit="handleOrderTransition(event, <?= (int)$t['order_id'] ?>, 'COOKING')">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="kds_advance">
                          <input type="hidden" name="order_id" value="<?= (int)$t['order_id'] ?>">
                          <input type="hidden" name="target_status" value="COOKING">
                          <button type="submit" class="btn btn-noir-primary w-100">
                            Accept & Start Cooking &rarr;
                          </button>
                        </form>
                      </div>

                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Column 2: Cooking in Progress -->
          <div class="col-lg-4">
            <div class="swimlane-col">
              <div class="swimlane-header">
                <div class="swimlane-title">
                  <span class="badge bg-secondary rounded-circle p-1" style="width:8px; height:8px;"></span>
                  <span>2. In Preparation (Stoves)</span>
                </div>
                <span class="swimlane-counter" id="laneCookingCountBadge"><?= $countCooking ?></span>
              </div>

              <!-- Cooking Order Cards -->
              <div class="d-flex flex-column gap-3" id="laneCookingContainer">
                <?php if (empty($laneCooking)): ?>
                  <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-fire-burner fs-1 text-muted d-block mb-2"></i>
                    <div class="small fw-semibold">No dishes currently on stoves.</div>
                    <div class="small text-muted">Accept tickets from Column 1 to commence prep.</div>
                  </div>
                <?php else: ?>
                  <?php foreach ($laneCooking as $t): ?>
                    <div class="ticket-card card-lane-cooking ticket-item" data-ticket-id="<?= $t['order_id'] ?>" data-search="<?= strtolower(e($t['customer_name'] . ' ' . $t['order_id'])) ?>">
                      
                      <!-- Header -->
                      <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                          <span class="font-mono fw-bold fs-6 text-dark">
                            #ZIP-<?= (int)$t['order_id'] ?>
                          </span>
                          <span class="ticket-tag-pill ticket-tag-dark ms-1">
                            ON STOVE
                          </span>
                        </div>
                        <span class="ticket-tag-pill ticket-tag-outline">
                          EST: <?= (int)$t['job_duration'] ?>m
                        </span>
                      </div>

                      <div class="d-flex justify-content-between small text-muted mb-2">
                        <span>Guest: <strong class="text-dark"><?= e($t['customer_name']) ?></strong></span>
                        <span class="font-mono text-dark">Time: <?= (int)$t['elapsed_minutes'] ?>m</span>
                      </div>

                      <?php if (!empty($t['order_notes'])): ?>
                        <div class="instruction-callout">
                          <i class="bi bi-chat-left-quote me-1"></i> <?= e($t['order_notes']) ?>
                        </div>
                      <?php endif; ?>

                      <!-- Interactive Prep Checklist -->
                      <div class="ticket-items-box">
                        <div class="text-muted small mb-2 fw-bold text-uppercase" style="font-size:0.68rem; letter-spacing:0.05em;">
                          Line Prep Checklist (Check as plated):
                        </div>
                        <?php foreach ($t['items'] as $idx => $it): ?>
                          <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle small">
                            <div class="form-check d-flex align-items-center gap-2 m-0">
                              <input class="form-check-input dish-checkbox" type="checkbox" id="chk_<?= $t['order_id'] ?>_<?= $idx ?>">
                              <label class="form-check-label text-dark fw-medium" for="chk_<?= $t['order_id'] ?>_<?= $idx ?>">
                                <span class="<?= $it['is_veg'] ? 'monochrome-veg' : 'monochrome-nonveg' ?>"></span>
                                <span class="fw-bold font-mono">[<?= (int)$it['quantity'] ?>x]</span> <?= e($it['item_name']) ?>
                              </label>
                            </div>
                            <span class="text-muted font-mono" style="font-size:0.75rem;"><?= (int)$it['prep_time_minutes'] ?>m</span>
                          </div>
                        <?php endforeach; ?>
                      </div>

                      <!-- Action Button: Mark Prepared & Plated -->
                      <div class="d-flex gap-2">
                        <button type="button" class="btn-kot-print" onclick="openKOT(<?= (int)$t['order_id'] ?>)" title="Print Thermal KOT Slip">
                          <i class="bi bi-printer"></i>
                        </button>
                        <form method="POST" action="<?= KITCHEN_URL ?>/index.php" class="flex-grow-1 m-0" onsubmit="handleOrderTransition(event, <?= (int)$t['order_id'] ?>, 'READY')">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="kds_advance">
                          <input type="hidden" name="order_id" value="<?= (int)$t['order_id'] ?>">
                          <input type="hidden" name="target_status" value="READY">
                          <button type="submit" class="btn btn-noir-primary w-100">
                            <i class="bi bi-check2-circle me-1"></i> Order Plated & Ready &rarr;
                          </button>
                        </form>
                      </div>

                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Column 3: Ready for Delivery Handover -->
          <div class="col-lg-4">
            <div class="swimlane-col">
              <div class="swimlane-header">
                <div class="swimlane-title">
                  <span class="badge bg-black rounded-circle p-1" style="width:8px; height:8px;"></span>
                  <span>3. Courier Handover (Ready)</span>
                </div>
                <span class="swimlane-counter" id="laneReadyCountBadge"><?= $countReady ?></span>
              </div>

              <!-- Ready Order Cards -->
              <div class="d-flex flex-column gap-3" id="laneReadyContainer">
                <?php if (empty($laneReady)): ?>
                  <div class="text-center py-5 text-muted">
                    <i class="bi bi-bag-check fs-1 text-muted d-block mb-2"></i>
                    <div class="small fw-semibold">No bagged tickets waiting at window.</div>
                    <div class="small text-muted">Finished orders will appear here for courier dispatch.</div>
                  </div>
                <?php else: ?>
                  <?php foreach ($laneReady as $t): ?>
                    <?php 
                      $disp = $dispatchesByOrder[$t['order_id']] ?? null;
                      $riderName = $disp['delivery_partner_name'] ?? 'Suresh Kumar (Rider)';
                      $riderPhone = $disp['delivery_partner_phone'] ?? '+91 96320 98765';
                      $otp = $disp['delivery_otp'] ?? '';
                    ?>
                    <div class="ticket-card card-lane-ready ticket-item" data-ticket-id="<?= $t['order_id'] ?>" data-search="<?= strtolower(e($t['customer_name'] . ' ' . $t['order_id'])) ?>">
                      
                      <!-- Header -->
                      <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                          <span class="font-mono fw-bold fs-6 text-dark">
                            #ZIP-<?= (int)$t['order_id'] ?>
                          </span>
                          <span class="ticket-tag-pill ticket-tag-dark ms-1">
                            BAGGED & READY
                          </span>
                        </div>
                        <?php if (!empty($otp)): ?>
                          <span class="font-mono fw-bold text-dark px-2 py-1 rounded bg-light border small">
                            OTP: <?= e($otp) ?>
                          </span>
                        <?php endif; ?>
                      </div>

                      <div class="small text-muted mb-2">
                        Customer: <strong class="text-dark"><?= e($t['customer_name']) ?></strong> (<?= e($t['customer_phone']) ?>)
                      </div>

                      <!-- Packaging summary -->
                      <div class="ticket-items-box py-2">
                        <div class="text-muted small fw-bold" style="font-size:0.7rem; letter-spacing:0.05em; text-transform:uppercase;">Parcel Content:</div>
                        <div class="small text-dark fw-semibold">
                          <?= (int)$t['total_portions'] ?> portions across <?= count($t['items']) ?> line items (Thermal sealed)
                        </div>
                      </div>

                      <!-- Rider Assignment Station -->
                      <form method="POST" action="<?= KITCHEN_URL ?>/index.php" class="m-0" onsubmit="handleOrderTransition(event, <?= (int)$t['order_id'] ?>, 'OUT_FOR_DELIVERY')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="kds_advance">
                        <input type="hidden" name="order_id" value="<?= (int)$t['order_id'] ?>">
                        <input type="hidden" name="target_status" value="OUT_FOR_DELIVERY">

                        <div class="rider-dispatch-box">
                          <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-bold text-dark"><i class="fa-solid fa-motorcycle me-1"></i> Assign Courier Partner</span>
                            <span class="ticket-tag-pill ticket-tag-dark" style="font-size:0.65rem;">DISPATCH</span>
                          </div>
                          
                          <select name="rider_name" class="form-select form-select-sm bg-white text-dark border my-2" style="font-size:0.85rem; border-color:#000000 !important;">
                            <?php if (!empty($ridersList)): ?>
                              <?php foreach ($ridersList as $r): ?>
                                <option value="<?= e($r['full_name']) ?>" <?= (stripos($riderName, $r['full_name']) !== false) ? 'selected' : '' ?>>
                                  <?= e($r['full_name']) ?> (<?= e($r['phone_number']) ?>)
                                </option>
                              <?php endforeach; ?>
                            <?php else: ?>
                              <option value="Suresh Kumar (Rider)" selected>Suresh Kumar (Rider) - +91 96320 98765</option>
                            <?php endif; ?>
                          </select>

                          <div class="small text-muted" style="font-size:0.75rem;">
                            Verify sealed thermal bag and hand over to driver.
                          </div>
                        </div>

                        <!-- Action Button: Hand Over to Delivery Boy -->
                        <button type="submit" class="btn btn-noir-primary w-100 py-2">
                          <i class="bi bi-bicycle me-2"></i> Hand Over to Delivery Boy &rarr;
                        </button>
                      </form>

                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- ====================================================================
           TAB 2: INCOMING (FOCUSED VIEW)
           ==================================================================== -->
      <div class="tab-pane fade" id="tab-new" role="tabpanel">
        <div class="card p-4 modal-content-noir">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h4 class="fw-bold font-outfit text-dark mb-1">01 / Incoming Orders Pending Acceptance</h4>
              <p class="text-muted small mb-0">Accept freshly confirmed customer orders to allocate stove burners.</p>
            </div>
            <span class="badge bg-black text-white px-3 py-2 rounded-pill font-mono">COUNT: <?= $countNew ?></span>
          </div>

          <div class="row g-4" id="focusNewContainer">
            <?php if (empty($laneNew)): ?>
              <div class="col-12 text-center py-5 text-muted">No pending orders.</div>
            <?php else: ?>
              <?php foreach ($laneNew as $t): ?>
                <div class="col-md-6 col-lg-4">
                  <div class="ticket-card card-lane-new">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <span class="font-mono fw-bold fs-6 text-dark">#ZIP-<?= (int)$t['order_id'] ?></span>
                      <span class="ticket-tag-pill ticket-tag-outline">WAIT: <?= (int)$t['elapsed_minutes'] ?>m</span>
                    </div>
                    <div class="small text-muted mb-2">Customer: <strong class="text-dark"><?= e($t['customer_name']) ?></strong></div>
                    <div class="ticket-items-box small">
                      <?php foreach ($t['items'] as $it): ?>
                        <div class="d-flex justify-content-between py-1 border-bottom border-light-subtle">
                          <span><?= (int)$it['quantity'] ?>x <?= e($it['item_name']) ?></span>
                          <span class="text-muted font-mono"><?= (int)$it['prep_time_minutes'] ?>m</span>
                        </div>
                      <?php endforeach; ?>
                    </div>
                    <form method="POST" action="<?= KITCHEN_URL ?>/index.php" onsubmit="handleOrderTransition(event, <?= (int)$t['order_id'] ?>, 'COOKING')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="kds_advance">
                      <input type="hidden" name="order_id" value="<?= (int)$t['order_id'] ?>">
                      <input type="hidden" name="target_status" value="COOKING">
                      <button type="submit" class="btn btn-noir-primary w-100">
                        Accept & Start Cooking &rarr;
                      </button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ====================================================================
           TAB 3: COOKING (FOCUSED VIEW)
           ==================================================================== -->
      <div class="tab-pane fade" id="tab-cooking" role="tabpanel">
        <div class="card p-4 modal-content-noir">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h4 class="fw-bold font-outfit text-dark mb-1">02 / Stoves & Ovens Preparation</h4>
              <p class="text-muted small mb-0">Active culinary preparation. Check off items as they finish plating.</p>
            </div>
            <span class="badge bg-black text-white px-3 py-2 rounded-pill font-mono">COUNT: <?= $countCooking ?></span>
          </div>

          <div class="row g-4" id="focusCookingContainer">
            <?php if (empty($laneCooking)): ?>
              <div class="col-12 text-center py-5 text-muted">No dishes currently on the stoves.</div>
            <?php else: ?>
              <?php foreach ($laneCooking as $t): ?>
                <div class="col-md-6 col-lg-4">
                  <div class="ticket-card card-lane-cooking">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <span class="font-mono fw-bold fs-6 text-dark">#ZIP-<?= (int)$t['order_id'] ?></span>
                      <span class="ticket-tag-pill ticket-tag-dark">COOKING</span>
                    </div>
                    <div class="small text-muted mb-2">Customer: <strong class="text-dark"><?= e($t['customer_name']) ?></strong></div>
                    <div class="ticket-items-box small">
                      <?php foreach ($t['items'] as $it): ?>
                        <div class="d-flex justify-content-between py-1 border-bottom border-light-subtle">
                          <span><?= (int)$it['quantity'] ?>x <?= e($it['item_name']) ?></span>
                          <span class="text-muted font-mono"><?= (int)$it['prep_time_minutes'] ?>m</span>
                        </div>
                      <?php endforeach; ?>
                    </div>
                    <form method="POST" action="<?= KITCHEN_URL ?>/index.php" onsubmit="handleOrderTransition(event, <?= (int)$t['order_id'] ?>, 'READY')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="kds_advance">
                      <input type="hidden" name="order_id" value="<?= (int)$t['order_id'] ?>">
                      <input type="hidden" name="target_status" value="READY">
                      <button type="submit" class="btn btn-noir-primary w-100">
                        <i class="bi bi-check2-circle me-1"></i> Mark Plated & Ready &rarr;
                      </button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ====================================================================
           TAB 4: READY (FOCUSED VIEW)
           ==================================================================== -->
      <div class="tab-pane fade" id="tab-ready" role="tabpanel">
        <div class="card p-4 modal-content-noir">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h4 class="fw-bold font-outfit text-dark mb-1">03 / Courier Expedition Handover</h4>
              <p class="text-muted small mb-0">Packaged food parcels waiting for courier pickup and OTP handover.</p>
            </div>
            <span class="badge bg-black text-white px-3 py-2 rounded-pill font-mono">COUNT: <?= $countReady ?></span>
          </div>

          <div class="row g-4" id="focusReadyContainer">
            <?php if (empty($laneReady)): ?>
              <div class="col-12 text-center py-5 text-muted">No orders waiting for courier pickup.</div>
            <?php else: ?>
              <?php foreach ($laneReady as $t): ?>
                <?php 
                  $disp = $dispatchesByOrder[$t['order_id']] ?? null;
                  $riderName = $disp['delivery_partner_name'] ?? 'Suresh Kumar (Rider)';
                ?>
                <div class="col-md-6 col-lg-4">
                  <div class="ticket-card card-lane-ready">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <span class="font-mono fw-bold fs-6 text-dark">#ZIP-<?= (int)$t['order_id'] ?></span>
                      <span class="ticket-tag-pill ticket-tag-dark">BAGGED</span>
                    </div>
                    <div class="small text-muted mb-2">Customer: <strong class="text-dark"><?= e($t['customer_name']) ?></strong></div>
                    <div class="p-2 rounded-3 mb-3 bg-light border small text-dark">
                      <i class="fa-solid fa-motorcycle me-1"></i> Assigned: <strong><?= e($riderName) ?></strong>
                    </div>
                    <form method="POST" action="<?= KITCHEN_URL ?>/index.php" onsubmit="handleOrderTransition(event, <?= (int)$t['order_id'] ?>, 'OUT_FOR_DELIVERY')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="kds_advance">
                      <input type="hidden" name="order_id" value="<?= (int)$t['order_id'] ?>">
                      <input type="hidden" name="target_status" value="OUT_FOR_DELIVERY">
                      <input type="hidden" name="rider_name" value="<?= e($riderName) ?>">
                      <button type="submit" class="btn btn-noir-primary w-100">
                        <i class="bi bi-bicycle me-2"></i> Hand Over to Delivery Boy &rarr;
                      </button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ====================================================================
           TAB 5: DISPATCH LOG (HISTORY LOG)
           ==================================================================== -->
      <div class="tab-pane fade" id="tab-history" role="tabpanel">
        <div class="card p-4 modal-content-noir">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
              <h4 class="fw-bold font-outfit text-dark mb-1">Today's Atelier Handover Archive</h4>
              <p class="text-muted small mb-0">Record of food tickets handed over to delivery couriers</p>
            </div>
            <span class="badge bg-black text-white px-3 py-2 rounded-pill font-mono">
              TOTAL: <?= count($recentHandedOver) ?>
            </span>
          </div>

          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="background:transparent;">
              <thead class="text-muted small text-uppercase" style="border-bottom: 2px solid #000000;">
                <tr>
                  <th class="font-mono">Ticket</th>
                  <th>Customer</th>
                  <th>Delivery Partner</th>
                  <th>Dispatched At</th>
                  <th>Status</th>
                  <th class="text-end">Docket</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recentHandedOver)): ?>
                  <tr>
                    <td colspan="6" class="text-center py-5 text-muted">No dispatches recorded today.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($recentHandedOver as $h): ?>
                    <tr>
                      <td>
                        <span class="font-mono fw-bold text-dark">#ZIP-<?= (int)$h['order_id'] ?></span>
                      </td>
                      <td>
                        <div class="fw-bold text-dark"><?= e($h['customer_name']) ?></div>
                        <div class="text-muted small"><?= format_inr($h['total_amount']) ?></div>
                      </td>
                      <td>
                        <div class="fw-bold text-dark"><i class="fa-solid fa-motorcycle me-1"></i> <?= e($h['delivery_partner_name'] ?: 'Zippy Express Rider') ?></div>
                        <?php if (!empty($h['delivery_otp'])): ?>
                          <span class="font-mono text-muted small">OTP: <?= e($h['delivery_otp']) ?></span>
                        <?php endif; ?>
                      </td>
                      <td class="text-muted small font-mono">
                        <?= !empty($h['dispatched_at']) ? date('h:i A', strtotime($h['dispatched_at'])) : date('h:i A', strtotime($h['placed_at'])) ?>
                      </td>
                      <td>
                        <span class="ticket-tag-pill ticket-tag-dark" style="font-size:0.68rem;">
                          <?= e($h['order_status']) ?>
                        </span>
                      </td>
                      <td class="text-end">
                        <button type="button" class="btn btn-sm btn-noir-outline px-3" onclick="openKOT(<?= (int)$h['order_id'] ?>)">
                          Slip
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>

  </main>

  <!-- ========================================================================
       MODAL: 86-LIST & MENU DISH AVAILABILITY (OUT OF STOCK TOGGLE)
       ======================================================================== -->
  <div class="modal fade" id="modal86List" tabindex="-1" aria-labelledby="modal86ListLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content modal-content-noir">
        <div class="modal-header modal-header-noir">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle bg-black text-white p-2 d-inline-flex align-items-center justify-content-center" style="width:36px; height:36px;">
              <i class="fa-solid fa-boxes-stacked"></i>
            </div>
            <div>
              <h5 class="modal-title font-outfit fw-bold text-dark mb-0" id="modal86ListLabel">Kitchen 86-List (Inventory Availability)</h5>
              <span class="text-muted small">Instantly toggle dish stock state directly from the expeditor display</span>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="text-muted small text-uppercase" style="border-bottom: 2px solid #000000;">
                <tr>
                  <th>Dish Name</th>
                  <th>Category</th>
                  <th>Stock Reserve</th>
                  <th>Status</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($allMenuItems as $dish): ?>
                  <tr>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <span class="<?= $dish['is_veg'] ? 'monochrome-veg' : 'monochrome-nonveg' ?>"></span>
                        <strong class="text-dark"><?= e($dish['item_name']) ?></strong>
                      </div>
                    </td>
                    <td class="text-muted small"><?= e($dish['category_name']) ?></td>
                    <td>
                      <span class="font-mono text-dark fw-bold">
                        <?= (int)$dish['stock_quantity'] ?> portions
                      </span>
                    </td>
                    <td>
                      <span id="stockLabel_<?= $dish['item_id'] ?>" class="ticket-tag-pill <?= $dish['is_available'] ? 'ticket-tag-dark' : 'ticket-tag-outline' ?>">
                        <?= $dish['is_available'] ? 'IN STOCK' : '86 / SOLD OUT' ?>
                      </span>
                    </td>
                    <td class="text-end">
                      <button type="button" 
                              id="btnStockToggle_<?= $dish['item_id'] ?>"
                              class="btn btn-sm <?= $dish['is_available'] ? 'btn-noir-outline' : 'btn-noir-primary' ?> rounded-pill px-3" 
                              onclick="toggleStockItem(<?= (int)$dish['item_id'] ?>, <?= $dish['is_available'] ? 0 : 1 ?>)">
                        <?= $dish['is_available'] ? 'Mark 86 (Out of Stock)' : 'Mark In Stock' ?>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer modal-footer-noir">
          <button type="button" class="btn btn-noir-primary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // 1. Digital Clock
    function updateClock() {
      const now = new Date();
      const timeStr = now.toLocaleTimeString('en-US', { hour12: true });
      document.getElementById('kdsLiveClock').innerText = timeStr;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // 2. Synthesizer Kitchen Bell Chime (Web Audio API - Pure & Elegant)
    let audioContext = null;
    let audioEnabled = true;

    function initAudio() {
      if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
      }
    }

    function playKitchenChime() {
      if (!audioEnabled) return;
      try {
        initAudio();
        if (audioContext.state === 'suspended') {
          audioContext.resume();
        }

        const now = audioContext.currentTime;

        // Note 1: High crisp ding (880 Hz - A5)
        const osc1 = audioContext.createOscillator();
        const gain1 = audioContext.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(880, now);
        gain1.gain.setValueAtTime(0.25, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.8);
        osc1.connect(gain1);
        gain1.connect(audioContext.destination);
        osc1.start(now);
        osc1.stop(now + 0.8);

        // Note 2: Harmonic pleasant chord (1320 Hz - E6)
        const osc2 = audioContext.createOscillator();
        const gain2 = audioContext.createGain();
        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(1320, now + 0.15);
        gain2.gain.setValueAtTime(0.3, now + 0.15);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 1.2);
        osc2.connect(gain2);
        gain2.connect(audioContext.destination);
        osc2.start(now + 0.15);
        osc2.stop(now + 1.2);
      } catch (e) {
        console.warn('Audio chime error:', e);
      }
    }

    function toggleAudioChime() {
      audioEnabled = !audioEnabled;
      document.getElementById('audioIcon').className = audioEnabled ? 'bi bi-bell-fill me-1' : 'bi bi-bell-slash-fill me-1';
      document.getElementById('audioText').innerText = audioEnabled ? 'SOUND: ON' : 'SOUND: OFF';
      if (audioEnabled) {
        playKitchenChime();
      }
    }

    // 3. Auto Refresh Controller
    let autoRefreshActive = true;
    let refreshTimerSeconds = 12;
    let refreshInterval = null;

    function startAutoRefresh() {
      refreshInterval = setInterval(() => {
        if (!autoRefreshActive) return;
        refreshTimerSeconds--;
        document.getElementById('refreshText').innerText = `SYNC (${refreshTimerSeconds}s)`;

        if (refreshTimerSeconds <= 0) {
          refreshTimerSeconds = 12;
          pollKitchenOrders();
        }
      }, 1000);
    }
    startAutoRefresh();

    function toggleAutoRefresh() {
      autoRefreshActive = !autoRefreshActive;
      const btn = document.getElementById('btnRefreshToggle');
      if (autoRefreshActive) {
        btn.className = 'btn btn-sm btn-noir-outline rounded-pill px-3';
        document.getElementById('refreshText').innerText = `SYNC (${refreshTimerSeconds}s)`;
      } else {
        btn.className = 'btn btn-sm bg-light text-muted border rounded-pill px-3';
        document.getElementById('refreshText').innerText = 'PAUSED';
      }
    }

    // 4. Live Polling for New Orders
    let lastKnownIncomingId = <?= !empty($laneNew) ? max(array_column($laneNew, 'order_id')) : 0 ?>;

    async function pollKitchenOrders() {
      try {
        const res = await fetch('<?= KITCHEN_URL ?>/ajax/poll-orders.php');
        const data = await res.json();
        if (data.success) {
          // Update KPI counts
          document.getElementById('statNew').innerText = data.counts.new;
          document.getElementById('statCooking').innerText = data.counts.cooking;
          document.getElementById('statReady').innerText = data.counts.ready;
          document.getElementById('statHandedOver').innerText = data.counts.handed_over;

          document.getElementById('laneNewCountBadge').innerText = data.counts.new;
          document.getElementById('laneCookingCountBadge').innerText = data.counts.cooking;
          document.getElementById('laneReadyCountBadge').innerText = data.counts.ready;

          const tabCountNew = document.getElementById('tabCountNew');
          if (tabCountNew) tabCountNew.innerText = data.counts.new;
          const tabCountCooking = document.getElementById('tabCountCooking');
          if (tabCountCooking) tabCountCooking.innerText = data.counts.cooking;
          const tabCountReady = document.getElementById('tabCountReady');
          if (tabCountReady) tabCountReady.innerText = data.counts.ready;

          // Check if brand new unaccepted order arrived
          if (data.latest_incoming_id > lastKnownIncomingId) {
            lastKnownIncomingId = data.latest_incoming_id;
            playKitchenChime();
            showOrderBanner(data.latest_incoming_id);
            setTimeout(() => { window.location.reload(); }, 2200);
          }
        }
      } catch (err) {
        console.error('Polling error:', err);
      }
    }

    function showOrderBanner(orderId) {
      const banner = document.getElementById('newOrderBanner');
      document.getElementById('newOrderBannerTitle').innerText = `NEW TICKET #ZIP-${orderId} ARRIVED`;
      document.getElementById('newOrderBannerDesc').innerText = 'Customer order confirmed. Ready for kitchen stove allocation.';
      banner.classList.remove('d-none');
      banner.classList.add('d-flex');
    }

    function dismissOrderBanner() {
      const banner = document.getElementById('newOrderBanner');
      banner.classList.remove('d-flex');
      banner.classList.add('d-none');
    }

    // 5. AJAX Order Transition Handler
    async function handleOrderTransition(event, orderId, targetStatus) {
      event.preventDefault();
      const form = event.target;
      const formData = new FormData(form);

      const submitBtn = form.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
      submitBtn.disabled = true;

      try {
        const res = await fetch('<?= KITCHEN_URL ?>/ajax/update-status.php', {
          method: 'POST',
          body: formData
        });
        const result = await res.json();

        if (result.success) {
          playKitchenChime();
          window.location.reload();
        } else {
          alert('Notice: ' + result.message);
          submitBtn.innerHTML = originalText;
          submitBtn.disabled = false;
        }
      } catch (e) {
        form.submit();
      }
    }

    // 6. Quick 86-List Toggle
    async function toggleStockItem(itemId, targetState) {
      const btn = document.getElementById('btnStockToggle_' + itemId);
      const label = document.getElementById('stockLabel_' + itemId);
      btn.disabled = true;

      try {
        const res = await fetch('<?= KITCHEN_URL ?>/ajax/toggle-stock.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ item_id: itemId, is_available: targetState })
        });
        const data = await res.json();

        if (data.success) {
          if (data.is_available) {
            label.className = 'ticket-tag-pill ticket-tag-dark';
            label.innerText = 'IN STOCK';
            btn.className = 'btn btn-sm btn-noir-outline rounded-pill px-3';
            btn.innerText = 'Mark 86 (Out of Stock)';
            btn.setAttribute('onclick', `toggleStockItem(${itemId}, 0)`);
          } else {
            label.className = 'ticket-tag-pill ticket-tag-outline';
            label.innerText = '86 / SOLD OUT';
            btn.className = 'btn btn-sm btn-noir-primary rounded-pill px-3';
            btn.innerText = 'Mark In Stock';
            btn.setAttribute('onclick', `toggleStockItem(${itemId}, 1)`);
          }
        } else {
          alert('Update failed: ' + data.message);
        }
      } catch (err) {
        console.error(err);
      } finally {
        btn.disabled = false;
      }
    }

    // 7. Open Printable KOT
    function openKOT(orderId) {
      const url = '<?= KITCHEN_URL ?>/kot.php?order_id=' + orderId;
      window.open(url, 'KOT_Slip', 'width=450,height=650,toolbar=no,menubar=no,location=no,status=no');
    }

    // 8. Filter Tickets by Search
    function filterTickets() {
      const query = document.getElementById('kdsSearchInput').value.toLowerCase().trim();
      const tickets = document.querySelectorAll('.ticket-item');

      tickets.forEach(card => {
        const text = card.getAttribute('data-search') || '';
        if (text.includes(query)) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    }
  </script>
</body>
</html>
