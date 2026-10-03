<?php
/**
 * Delivery Partner Fleet Web App
 * Zippy - Online Food Ordering Platform
 * MCSP-232: MCA Major Project | Student: Pooja JP
 * 
 * Implements:
 * - UC-14: Toggle Online/Offline Duty
 * - UC-15: Accept Delivery Assignment / Claim Order
 * - UC-16: Turn-by-Turn GPS Navigation & Customer Dialing
 * - UC-17: Doorstep 6-Digit OTP Verification & Payout Credit
 * - 3NF Real-Time Sync with `orders`, `delivery_dispatches`, `order_audit_trail`
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce Rider Authentication
require_rider();
$currentUser = current_user();

// Initialize or read rider duty state (default: online)
if (!isset($_SESSION['rider_on_duty'])) {
    $_SESSION['rider_on_duty'] = true;
}

// Handle AJAX or POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
              || (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1');

    // 1. Toggle Duty Status
    if ($action === 'toggle_duty') {
        $newDuty = !empty($_POST['duty']) && $_POST['duty'] === '1';
        $_SESSION['rider_on_duty'] = $newDuty;
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'on_duty' => $newDuty]);
            exit;
        }
        set_flash('info', $newDuty ? 'You are now ONLINE and accepting dispatches.' : 'You are now OFFLINE. Dispatches paused.');
        header("Location: " . DELIVERY_URL . "/index.php");
        exit;
    }

    // 2. Claim / Pick Up Order from Kitchen
    if ($action === 'claim_order') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $order = db_fetch_one("SELECT * FROM `orders` WHERE `order_id` = :id", [':id' => $orderId]);

        if ($order && in_array($order['order_status'], ['READY', 'CONFIRMED', 'COOKING'])) {
            $riderName = $currentUser['name'] ?? 'Suresh Kumar (Rider)';
            $riderPhone = $_SESSION['user_phone'] ?? '+91 96320 98765';

            // Advance status to OUT_FOR_DELIVERY
            db_query("UPDATE `orders` SET `order_status` = 'OUT_FOR_DELIVERY' WHERE `order_id` = :id", [':id' => $orderId]);

            // Ensure dispatch record exists & update
            $dispatch = db_fetch_one("SELECT * FROM `delivery_dispatches` WHERE `order_id` = :id", [':id' => $orderId]);
            if ($dispatch) {
                db_query("
                    UPDATE `delivery_dispatches` 
                    SET `delivery_partner_name` = :rname,
                        `delivery_partner_phone` = :rphone,
                        `dispatched_at` = NOW(),
                        `delivery_status` = 'IN_TRANSIT'
                    WHERE `order_id` = :id
                ", [
                    ':rname' => $riderName,
                    ':rphone' => $riderPhone,
                    ':id' => $orderId
                ]);
            } else {
                $newOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                db_query("
                    INSERT INTO `delivery_dispatches` 
                    (`order_id`, `delivery_partner_name`, `delivery_partner_phone`, `delivery_otp`, `assigned_at`, `dispatched_at`, `delivery_status`)
                    VALUES (:id, :rname, :rphone, :otp, NOW(), NOW(), 'IN_TRANSIT')
                ", [
                    ':id' => $orderId,
                    ':rname' => $riderName,
                    ':rphone' => $riderPhone,
                    ':otp' => $newOtp
                ]);
            }

            // Audit Trail
            db_query("
                INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`)
                VALUES (:oid, :old, 'OUT_FOR_DELIVERY', :uid, NOW())
            ", [
                ':oid' => $orderId,
                ':old' => $order['order_status'],
                ':uid' => $currentUser['id']
            ]);

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => "Order #ZIP-{$orderId} claimed and picked up!"]);
                exit;
            }
            set_flash('success', "Order #ZIP-{$orderId} picked up. Please follow GPS to customer address.");
            header("Location: " . DELIVERY_URL . "/index.php");
            exit;
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Order is not ready for pickup yet.']);
                exit;
            }
            set_flash('danger', 'Order is not ready for pickup.');
            header("Location: " . DELIVERY_URL . "/index.php");
            exit;
        }
    }

    // 3. Verify Customer OTP & Finish Delivery
    if ($action === 'verify_otp') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $enteredOtp = trim($_POST['otp'] ?? '');

        $dispatch = db_fetch_one("SELECT * FROM `delivery_dispatches` WHERE `order_id` = :id", [':id' => $orderId]);
        $expectedOtp = $dispatch['delivery_otp'] ?? '';

        if (!empty($expectedOtp) && $enteredOtp === $expectedOtp) {
            // Update order to DELIVERED
            db_query("UPDATE `orders` SET `order_status` = 'DELIVERED' WHERE `order_id` = :id", [':id' => $orderId]);

            // Update dispatch
            db_query("
                UPDATE `delivery_dispatches` 
                SET `delivery_status` = 'DELIVERED', `delivered_at` = NOW() 
                WHERE `order_id` = :id
            ", [':id' => $orderId]);

            // If COD, mark payment as SUCCESSFUL
            db_query("
                UPDATE `payments` 
                SET `payment_status` = 'SUCCESSFUL', `payment_timestamp` = NOW() 
                WHERE `order_id` = :id AND `payment_status` = 'PENDING'
            ", [':id' => $orderId]);

            // Insert into audit trail
            db_query("
                INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`)
                VALUES (:oid, 'OUT_FOR_DELIVERY', 'DELIVERED', :uid, NOW())
            ", [
                ':oid' => $orderId,
                ':uid' => $currentUser['id']
            ]);

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true, 
                    'message' => "OTP verified! Order #ZIP-{$orderId} delivered successfully. ₹55.00 credited to your fleet wallet."
                ]);
                exit;
            }
            set_flash('success', "Order #ZIP-{$orderId} successfully DELIVERED! ₹55.00 payout credited.");
            header("Location: " . DELIVERY_URL . "/index.php");
            exit;
        } else {
            // Intelligent error message: check if the entered OTP belongs to another order
            $otherDispatch = db_fetch_one("
                SELECT d.*, o.order_status, o.order_id 
                FROM `delivery_dispatches` d 
                JOIN `orders` o ON d.order_id = o.order_id 
                WHERE d.delivery_otp = :otp
            ", [':otp' => $enteredOtp]);

            if ($otherDispatch) {
                if ($otherDispatch['order_id'] != $orderId) {
                    if ($otherDispatch['order_status'] === 'DELIVERED') {
                        $deliveredTime = !empty($otherDispatch['delivered_at']) ? date('h:i A', strtotime($otherDispatch['delivered_at'])) : 'earlier';
                        $msg = "Incorrect OTP for Order #ZIP-{$orderId}! You entered '{$enteredOtp}', which belongs to Order #ZIP-{$otherDispatch['order_id']} (which was already delivered at {$deliveredTime}). The correct OTP for Order #ZIP-{$orderId} is '{$expectedOtp}'.";
                    } else {
                        $msg = "Notice: The entered OTP '{$enteredOtp}' belongs to Order #ZIP-{$otherDispatch['order_id']} (Status: {$otherDispatch['order_status']}). The correct OTP for Order #ZIP-{$orderId} is '{$expectedOtp}'.";
                    }
                } else {
                    $msg = "Incorrect OTP code. The OTP for Order #ZIP-{$orderId} is '{$expectedOtp}'.";
                }
            } else {
                $msg = "Incorrect OTP code '{$enteredOtp}'. The correct OTP for Order #ZIP-{$orderId} is '{$expectedOtp}'. Please check the customer screen for Order #ZIP-{$orderId}.";
            }

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            set_flash('danger', $msg);
            header("Location: " . DELIVERY_URL . "/index.php");
            exit;
        }
    }
}

// Fetch Active In-Transit Orders (Assigned or Out for delivery)
$activeOrders = db_fetch_all("
    SELECT o.*, u.full_name AS customer_name, u.phone_number AS customer_phone,
           a.address_line, a.landmark, a.pincode,
           p.payment_mode, p.payment_status, p.amount_paid,
           d.dispatch_id, d.delivery_otp, d.delivery_status, d.dispatched_at
    FROM `orders` o
    JOIN `users` u ON o.user_id = u.user_id
    LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    WHERE o.order_status = 'OUT_FOR_DELIVERY'
    ORDER BY o.placed_at DESC
");

// Fetch Line Items for Active Orders
$activeOrderItems = [];
if (!empty($activeOrders)) {
    $orderIds = array_column($activeOrders, 'order_id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $rawItems = db_fetch_all("
        SELECT oi.*, m.item_name, m.is_veg 
        FROM `order_items` oi
        JOIN `menu_items` m ON oi.item_id = m.item_id
        WHERE oi.order_id IN ($placeholders)
    ", $orderIds);

    foreach ($rawItems as $it) {
        $activeOrderItems[$it['order_id']][] = $it;
    }
}

// Fetch Available Orders Waiting for Pickup (Status: READY)
$readyOrders = db_fetch_all("
    SELECT o.*, u.full_name AS customer_name, u.phone_number AS customer_phone,
           a.address_line, a.landmark, a.pincode,
           p.payment_mode,
           d.delivery_otp
    FROM `orders` o
    JOIN `users` u ON o.user_id = u.user_id
    LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    WHERE o.order_status = 'READY'
    ORDER BY o.placed_at ASC
");

// Fetch Delivered Trip History
$historyOrders = db_fetch_all("
    SELECT o.*, u.full_name AS customer_name,
           a.address_line, a.pincode,
           p.payment_mode, p.amount_paid,
           d.delivered_at, d.delivery_otp
    FROM `orders` o
    JOIN `users` u ON o.user_id = u.user_id
    LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    WHERE o.order_status = 'DELIVERED'
    ORDER BY d.delivered_at DESC, o.placed_at DESC
    LIMIT 20
");

// Calculate shift earnings
$deliveredCount = count($historyOrders);
$baseFare = 45.00;
$todayEarnings = ($deliveredCount * $baseFare) + ($deliveredCount * 15.00); // base + distance incentive
$totalTips = $deliveredCount * 20.00;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Delivery Partner Fleet App - Zippy Food Network</title>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5.3.3 & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  
  <!-- Custom Figma Lush Forest Palette Stylesheet -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">

  <style>
    :root {
      --rider-forest: #2E6F40;
      --rider-pine: #243E2C;
      --rider-mint: #CEFFDC;
      --rider-mint-soft: #EDFBF2;
      --rider-surface: #FFFFFF;
      --rider-bg: #F5F8F6;
      --rider-border: #E1ECE5;
    }
    body {
      background-color: var(--rider-bg);
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: #1C2B20;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .rider-top-navbar {
      background: #FFFFFF;
      border-bottom: 2px solid var(--rider-forest);
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
      position: sticky;
      top: 0;
      z-index: 1020;
    }
    .brand-fleet-logo {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      color: var(--rider-pine);
    }
    .fleet-icon-badge {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      background: var(--rider-forest);
      color: #FFFFFF;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.15rem;
    }
    .rider-nav-tabs {
      display: flex;
      gap: 8px;
      background: #EBF3EE;
      padding: 4px;
      border-radius: 50px;
      margin: 12px 0 20px 0;
    }
    .rider-nav-tab-btn {
      flex: 1;
      border: none;
      background: transparent;
      padding: 10px 16px;
      border-radius: 50px;
      font-size: 0.88rem;
      font-weight: 700;
      color: var(--rider-pine);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.2s ease;
      cursor: pointer;
    }
    .rider-nav-tab-btn.active {
      background: #FFFFFF;
      color: var(--rider-forest);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }
    .duty-card {
      background: #FFFFFF;
      border-radius: 18px;
      padding: 16px 20px;
      border: 1px solid var(--rider-border);
      box-shadow: 0 2px 8px rgba(0,0,0,0.03);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }
    .active-trip-card {
      background: #FFFFFF;
      border-radius: 20px;
      border: 2px solid var(--rider-forest);
      box-shadow: 0 6px 20px rgba(46, 111, 64, 0.08);
      overflow: hidden;
      position: relative;
    }
    .active-trip-header {
      background: linear-gradient(135deg, #243E2C 0%, #2E6F40 100%);
      color: #FFFFFF;
      padding: 16px 20px;
    }
    .step-timeline-container {
      display: flex;
      justify-content: space-between;
      position: relative;
      margin: 20px 10px 24px 10px;
    }
    .step-timeline-container::before {
      content: '';
      position: absolute;
      top: 15px;
      left: 20px;
      right: 20px;
      height: 3px;
      background: #E2ECE5;
      z-index: 1;
    }
    .step-timeline-progress {
      position: absolute;
      top: 15px;
      left: 20px;
      height: 3px;
      background: var(--rider-forest);
      z-index: 2;
    }
    .step-node {
      position: relative;
      z-index: 3;
      text-align: center;
      flex: 1;
    }
    .step-circle {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #FFFFFF;
      border: 3px solid #CBD5E1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
      font-weight: 800;
      color: #64748B;
      transition: all 0.3s ease;
    }
    .step-node.completed .step-circle {
      background: var(--rider-forest);
      border-color: var(--rider-forest);
      color: #FFFFFF;
    }
    .step-node.active .step-circle {
      background: #FFFFFF;
      border-color: var(--rider-forest);
      color: var(--rider-forest);
      box-shadow: 0 0 0 4px var(--rider-mint);
    }
    .step-title {
      font-size: 0.72rem;
      font-weight: 700;
      color: #64748B;
      margin-top: 6px;
      display: block;
    }
    .step-node.completed .step-title,
    .step-node.active .step-title {
      color: var(--rider-pine);
    }
    .history-order-card {
      background: #FFFFFF;
      border-radius: 16px;
      border: 1px solid var(--rider-border);
      padding: 16px 20px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.02);
      transition: all 0.2s ease;
    }
    .history-order-card:hover {
      border-color: var(--rider-forest);
      box-shadow: 0 4px 14px rgba(46, 111, 64, 0.06);
    }
    .btn-rider-primary {
      background-color: var(--rider-forest);
      color: #FFFFFF;
      font-weight: 700;
      border: none;
      border-radius: 50px;
      transition: all 0.2s ease;
    }
    .btn-rider-primary:hover, .btn-rider-primary:focus {
      background-color: #235933;
      color: #FFFFFF;
      box-shadow: 0 4px 12px rgba(46, 111, 64, 0.25);
    }
    .pulse-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: #10B981;
      display: inline-block;
      box-shadow: 0 0 0 rgba(16, 185, 129, 0.4);
      animation: pulseRider 2s infinite;
    }
    @keyframes pulseRider {
      0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
      70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
      100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
  </style>
</head>
<body class="delivery-partner-app">

  <!-- =========================================================================
       1. TOPBAR / HEADER
       ========================================================================= -->
  <header class="rider-top-navbar" id="riderTopNavbar">
    <div class="container-xl py-2">
      <div class="d-flex align-items-center justify-content-between">
        
        <!-- Fleet Brand -->
        <a href="<?= DELIVERY_URL ?>/index.php" class="brand-fleet-logo">
          <div class="fleet-icon-badge">
            <i class="fa-solid fa-bicycle"></i>
          </div>
          <div>
            <div class="fw-bold fs-5 text-pine mb-0" style="line-height: 1.1; font-family:'Outfit',sans-serif;">zippy<span style="color:#68BA7F;">.</span></div>
            <div class="small fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.5px;">FLEET PARTNER</div>
          </div>
        </a>

        <!-- Rider Profile & Shortcuts -->
        <div class="d-flex align-items-center gap-2">
          
          <div class="d-none d-sm-flex align-items-center gap-2 bg-light px-3 py-1 rounded-pill border">
            <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold" style="width: 26px; height: 26px; font-size: 0.75rem; background-color: var(--rider-forest) !important;">
              <?= strtoupper(substr($currentUser['name'] ?? 'R', 0, 1)) ?>
            </div>
            <span class="fw-bold small text-dark"><?= e($currentUser['name'] ?? 'Suresh Kumar') ?></span>
            <span class="badge bg-warning text-dark small"><i class="bi bi-star-fill me-1"></i>4.9</span>
          </div>

          <a href="<?= BASE_URL ?>/index.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-semibold">
            <i class="bi bi-grid-fill me-1"></i> Portals
          </a>

          <a href="<?= CUSTOMER_URL ?>/index.php" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold">
            <i class="bi bi-shop me-1"></i> Store
          </a>

          <a href="<?= DELIVERY_URL ?>/logout.php" class="btn btn-sm btn-outline-danger rounded-pill fw-bold px-3 d-flex align-items-center gap-1" title="Log out from fleet account">
            <i class="bi bi-box-arrow-right"></i>
            <span class="d-none d-md-inline">Logout</span>
          </a>

        </div>

      </div>
    </div>
  </header>

  <!-- =========================================================================
       2. MAIN RIDER WORKSPACE
       ========================================================================= -->
  <main class="container-xl py-3" style="max-width: 920px;">

    <?php display_flash(); ?>

    <!-- A. ONLINE / OFFLINE DUTY BAR (UC-14) -->
    <div class="duty-card mb-3">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: <?= $_SESSION['rider_on_duty'] ? 'var(--rider-mint)' : '#E2ECE5' ?>; color: var(--rider-forest);" id="dutyIconBox">
          <i class="bi <?= $_SESSION['rider_on_duty'] ? 'bi-broadcast' : 'bi-moon-stars' ?> fs-4"></i>
        </div>
        <div>
          <div class="d-flex align-items-center gap-2">
            <h5 class="fw-bold text-dark mb-0" id="dutyStatusHeading">
              <?= $_SESSION['rider_on_duty'] ? 'You are ONLINE' : 'You are OFFLINE' ?>
            </h5>
            <span class="badge <?= $_SESSION['rider_on_duty'] ? 'bg-success' : 'bg-secondary' ?> rounded-pill px-2 py-1 small" id="dutyStatusPill">
              <?php if ($_SESSION['rider_on_duty']): ?>
                <span class="pulse-dot me-1"></span> Accepting Dispatches
              <?php else: ?>
                Dispatches Paused
              <?php endif; ?>
            </span>
          </div>
          <div class="small text-muted">
            GPS Zone: <strong>Indiranagar Hub</strong> • High Signal Accuracy (±4m)
          </div>
        </div>
      </div>

      <form method="POST" action="<?= DELIVERY_URL ?>/index.php" id="dutyToggleForm" class="m-0">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_duty">
        <input type="hidden" name="duty" id="dutyHiddenInput" value="<?= $_SESSION['rider_on_duty'] ? '0' : '1' ?>">
        <div class="form-check form-switch fs-4 m-0">
          <input class="form-check-input cursor-pointer" type="checkbox" id="dutyToggleSwitch" <?= $_SESSION['rider_on_duty'] ? 'checked' : '' ?> onchange="document.getElementById('dutyToggleForm').submit();">
        </div>
      </form>
    </div>

    <!-- B. SHIFT STATS STRIP -->
    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3">
        <div class="card border-0 rounded-4 p-3 shadow-xs bg-white text-center">
          <div class="small text-muted fw-bold">Today's Earnings</div>
          <div class="fs-4 fw-bold text-success font-monospace"><?= format_inr($todayEarnings) ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 rounded-4 p-3 shadow-xs bg-white text-center">
          <div class="small text-muted fw-bold">Trips Delivered</div>
          <div class="fs-4 fw-bold text-dark font-monospace"><?= (int)$deliveredCount ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 rounded-4 p-3 shadow-xs bg-white text-center">
          <div class="small text-muted fw-bold">Customer Tips</div>
          <div class="fs-4 fw-bold text-primary font-monospace"><?= format_inr($totalTips) ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 rounded-4 p-3 shadow-xs bg-white text-center">
          <div class="small text-muted fw-bold">Active Orders</div>
          <div class="fs-4 fw-bold text-warning font-monospace"><?= count($activeOrders) ?></div>
        </div>
      </div>
    </div>

    <!-- C. WORK NAVIGATION TABS: ORDERS | HISTORY | EARNINGS -->
    <nav class="rider-nav-tabs" aria-label="Rider Navigation">
      <button type="button" class="rider-nav-tab-btn active" id="tabBtnOrders" onclick="switchTab('orders')">
        <i class="bi bi-box-seam"></i>
        <span>Active & Ready</span>
        <span class="badge bg-success rounded-pill px-2"><?= count($activeOrders) + count($readyOrders) ?></span>
      </button>
      <button type="button" class="rider-nav-tab-btn" id="tabBtnHistory" onclick="switchTab('history')">
        <i class="bi bi-clock-history"></i>
        <span>Trip History</span>
        <span class="badge bg-secondary rounded-pill px-2"><?= count($historyOrders) ?></span>
      </button>
      <button type="button" class="rider-nav-tab-btn" id="tabBtnEarnings" onclick="switchTab('earnings')">
        <i class="bi bi-wallet2"></i>
        <span>Earnings & Payouts</span>
      </button>
    </nav>

    <!-- =======================================================================
         TAB 1: ACTIVE DISPATCHES & ORDERS (UC-15, UC-16, UC-17)
         ======================================================================= -->
    <div id="viewOrders">
      
      <?php if (!$_SESSION['rider_on_duty']): ?>
        <div class="alert alert-secondary border rounded-4 text-center py-4 mb-4">
          <i class="bi bi-moon-stars fs-3 text-muted d-block mb-2"></i>
          <h5 class="fw-bold text-dark">You are currently OFFLINE</h5>
          <p class="small text-muted mb-3">Turn on the duty toggle above to accept live customer dispatches and earn trip payouts.</p>
          <button class="btn btn-sm btn-success rounded-pill px-4 fw-bold" onclick="document.getElementById('dutyToggleSwitch').checked = true; document.getElementById('dutyToggleForm').submit();">
            Go Online Now
          </button>
        </div>
      <?php else: ?>

        <!-- Section 1: Active In-Transit Orders -->
        <?php if (!empty($activeOrders)): ?>
          <div class="mb-4">
            <h6 class="fw-bold text-dark text-uppercase small mb-3">
              <i class="bi bi-bicycle text-success me-1"></i> Active Dispatches In Progress (<?= count($activeOrders) ?>)
            </h6>

            <?php foreach ($activeOrders as $order): ?>
              <?php $items = $activeOrderItems[$order['order_id']] ?? []; ?>
              <div class="active-trip-card mb-4" id="orderCard<?= $order['order_id'] ?>">
                
                <!-- Trip Header -->
                <div class="active-trip-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-pill fw-bold px-3 py-1" style="background:#CEFFDC; color:#243E2C;">
                      <i class="bi bi-geo-alt-fill me-1"></i> IN TRANSIT
                    </span>
                    <span class="fw-bold font-monospace fs-6 text-white">#ZIP-<?= (int)$order['order_id'] ?></span>
                  </div>
                  <div class="text-end">
                    <span class="small opacity-75">Trip Payout:</span>
                    <span class="fs-5 fw-bold font-monospace text-mint ms-1" style="color:#CEFFDC;">₹55.00</span>
                  </div>
                </div>

                <!-- Stepper Progress Timeline -->
                <div class="p-3 bg-light border-bottom">
                  <div class="step-timeline-container">
                    <div class="step-timeline-progress" style="width: 75%;"></div>
                    
                    <div class="step-node completed">
                      <div class="step-circle"><i class="bi bi-check-lg"></i></div>
                      <span class="step-title">Assigned</span>
                    </div>
                    <div class="step-node completed">
                      <div class="step-circle"><i class="bi bi-check-lg"></i></div>
                      <span class="step-title">At Outlet</span>
                    </div>
                    <div class="step-node active">
                      <div class="step-circle"><i class="fa-solid fa-motorcycle"></i></div>
                      <span class="step-title">In Transit</span>
                    </div>
                    <div class="step-node">
                      <div class="step-circle"><i class="bi bi-house-door"></i></div>
                      <span class="step-title">Delivered</span>
                    </div>
                  </div>
                </div>

                <!-- Route Details -->
                <div class="p-4">
                  
                  <!-- Pickup Outlet -->
                  <div class="d-flex gap-3 mb-3">
                    <div class="text-center pt-1" style="width: 32px;">
                      <div class="rounded-circle text-white d-flex align-items-center justify-content-center shadow-xs" style="width: 28px; height: 28px; font-size: 0.8rem; background-color: var(--rider-forest) !important;">
                        <i class="fa-solid fa-store"></i>
                      </div>
                      <div class="vr my-1" style="height: 38px; border-left: 2px dashed #94A3B8;"></div>
                    </div>
                    <div>
                      <span class="badge bg-success bg-opacity-10 text-success fw-bold small mb-1">PICKED UP FROM</span>
                      <h6 class="fw-bold text-dark mb-0">Zippy Kitchen - Indiranagar Hub</h6>
                      <p class="small text-muted mb-0">#544, 100 Feet Road, HAL 2nd Stage, Bangalore</p>
                    </div>
                  </div>

                  <!-- Drop Location -->
                  <div class="d-flex gap-3 mb-4">
                    <div class="text-center pt-1" style="width: 32px;">
                      <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center shadow-xs" style="width: 28px; height: 28px; font-size: 0.8rem;">
                        <i class="bi bi-geo-alt-fill"></i>
                      </div>
                    </div>
                    <div class="flex-grow-1">
                      <span class="badge bg-danger bg-opacity-10 text-danger fw-bold small mb-1">DELIVER TO CUSTOMER</span>
                      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                          <h6 class="fw-bold text-dark mb-0"><?= e($order['customer_name']) ?></h6>
                          <p class="small text-muted mb-1"><?= e($order['address_line'] ?: 'Indiranagar 100ft Road') ?> (PIN: <?= e($order['pincode'] ?: '560038') ?>)</p>
                          <?php if (!empty($order['order_notes'])): ?>
                            <div class="small fw-semibold text-success"><i class="bi bi-chat-left-text me-1"></i> Note: <?= e($order['order_notes']) ?></div>
                          <?php endif; ?>
                        </div>
                        <a href="tel:<?= e($order['customer_phone'] ?: '+919876543210') ?>" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold flex-shrink-0">
                          <i class="bi bi-telephone-fill me-1"></i> Call Customer
                        </a>
                      </div>
                    </div>
                  </div>

                  <!-- Parcel Items Summary -->
                  <div class="p-3 bg-light rounded-3 mb-4 border">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <div class="small fw-bold text-dark"><i class="bi bi-bag-check-fill text-success me-1"></i> Order Items (<?= count($items) ?> items):</div>
                      <div>
                        <?php if ($order['payment_mode'] === 'COD'): ?>
                          <span class="badge bg-warning text-dark border fw-bold">
                            <i class="bi bi-cash me-1"></i> COD: Collect <?= format_inr($order['total_amount']) ?>
                          </span>
                        <?php else: ?>
                          <span class="badge bg-success bg-opacity-10 text-success border border-success small fw-bold">
                            <i class="bi bi-check-circle me-1"></i> Paid Online (<?= e($order['payment_mode']) ?>)
                          </span>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 small">
                      <?php foreach ($items as $it): ?>
                        <span class="badge bg-white text-dark border p-2">
                          <span class="badge <?= $it['is_veg'] ? 'bg-success' : 'bg-danger' ?> p-1 me-1"><?= $it['is_veg'] ? 'V' : 'N' ?></span>
                          <?= (int)$it['quantity'] ?>x <?= e($it['item_name']) ?>
                        </span>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <!-- Action Buttons: GPS Navigation & OTP Verification -->
                  <div class="d-flex flex-column flex-sm-row gap-3 mb-2">
                    <button type="button" class="btn btn-outline-secondary rounded-pill py-2 px-3 fw-bold flex-grow-1" onclick="openGpsModal(<?= (int)$order['order_id'] ?>, '<?= e(addslashes($order['customer_name'])) ?>', '<?= e(addslashes($order['address_line'] ?: 'Indiranagar 100ft Road')) ?>')">
                      <i class="fa-solid fa-location-arrow me-1 text-primary"></i> Turn-by-Turn GPS Map
                    </button>
                    
                    <button type="button" class="btn btn-rider-primary py-2 px-4 fw-bold flex-grow-1 shadow-sm fs-6" onclick="openOtpModal(<?= (int)$order['order_id'] ?>, '<?= e($order['delivery_otp']) ?>', '<?= e(addslashes($order['customer_name'])) ?>', '<?= $order['payment_mode'] ?>', '<?= format_inr($order['total_amount']) ?>')">
                      <i class="bi bi-shield-check me-2"></i> Verify Customer OTP & Finish
                    </button>
                  </div>

                  <div class="d-flex justify-content-between align-items-center bg-light px-3 py-2 rounded-3 border small">
                    <span class="text-muted"><i class="bi bi-shield-lock-fill text-success me-1"></i> Customer OTP for Order #ZIP-<?= (int)$order['order_id'] ?>:</span>
                    <span class="font-monospace fw-bold fs-6 text-dark bg-white px-2 py-0 rounded border"><?= e($order['delivery_otp']) ?></span>
                  </div>

                </div>

              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Section 2: Orders Ready in Kitchen for Courier Pickup -->
        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold text-dark text-uppercase small mb-0">
              <i class="fa-solid fa-fire-burner text-warning me-1"></i> Bagged & Ready for Pickup at Kitchen (<?= count($readyOrders) ?>)
            </h6>
            <a href="<?= DELIVERY_URL ?>/index.php" class="btn btn-sm btn-link text-success text-decoration-none p-0">
              <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </a>
          </div>

          <?php if (empty($readyOrders)): ?>
            <div class="card border-0 rounded-4 p-4 text-center bg-white shadow-xs">
              <i class="bi bi-check2-circle fs-2 text-success mb-2"></i>
              <h6 class="fw-bold text-dark mb-1">No Orders Waiting for Pickup</h6>
              <p class="small text-muted mb-0">Kitchen orders will automatically pop up here as soon as cooks mark them "Ready".</p>
            </div>
          <?php else: ?>
            <div class="row g-3">
              <?php foreach ($readyOrders as $ro): ?>
                <div class="col-md-6">
                  <div class="card border rounded-4 p-3 shadow-sm bg-white h-100 d-flex flex-column justify-content-between">
                    <div>
                      <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-warning text-dark fw-bold">READY IN KITCHEN</span>
                        <span class="fw-bold font-monospace text-dark">#ZIP-<?= (int)$ro['order_id'] ?></span>
                      </div>
                      <h6 class="fw-bold text-dark mb-1">Customer: <?= e($ro['customer_name']) ?></h6>
                      <p class="small text-muted mb-2">
                        <i class="bi bi-geo-alt me-1 text-danger"></i> <?= e($ro['address_line'] ?: 'Indiranagar') ?> (PIN: <?= e($ro['pincode'] ?: '560038') ?>)
                      </p>
                      <div class="small fw-semibold text-success mb-3">
                        <i class="bi bi-cash-coin me-1"></i> Est. Rider Earnings: <strong>₹55.00</strong>
                      </div>
                    </div>

                    <form method="POST" action="<?= DELIVERY_URL ?>/index.php" class="m-0">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="claim_order">
                      <input type="hidden" name="order_id" value="<?= (int)$ro['order_id'] ?>">
                      <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold py-2 shadow-xs" style="background:#2E6F40; border-color:#2E6F40;">
                        <i class="bi bi-handbag me-1"></i> Accept & Pick Up Order
                      </button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

      <?php endif; ?>

    </div>

    <!-- =======================================================================
         TAB 2: TRIP HISTORY
         ======================================================================= -->
    <div id="viewHistory" style="display: none;">
      
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0">Delivered Trips (<?= count($historyOrders) ?>)</h5>
        <span class="text-muted small">Updated from MySQL Database</span>
      </div>

      <?php if (empty($historyOrders)): ?>
        <div class="card border-0 rounded-4 p-5 text-center bg-white shadow-xs">
          <i class="bi bi-clock-history fs-1 text-muted mb-2"></i>
          <h6 class="fw-bold text-dark">No Completed Deliveries Yet</h6>
          <p class="small text-muted mb-0">Complete your first active trip to log your delivery record here.</p>
        </div>
      <?php else: ?>
        <div class="d-flex flex-column gap-3">
          <?php foreach ($historyOrders as $ho): ?>
            <div class="history-order-card">
              <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <div>
                  <span class="fw-bold text-dark">#ZIP-<?= (int)$ho['order_id'] ?></span>
                  <span class="text-muted small ms-2">• Customer: <?= e($ho['customer_name']) ?></span>
                </div>
                <span class="badge bg-success bg-opacity-10 text-success fw-bold px-2 py-1 rounded-pill">
                  <i class="bi bi-check-circle-fill me-1"></i> Delivered on <?= date('d M, h:i A', strtotime($ho['delivered_at'] ?: $ho['placed_at'])) ?>
                </span>
              </div>
              <div class="small text-muted mb-2">
                <i class="bi bi-geo-alt me-1 text-danger"></i> <?= e($ho['address_line'] ?: 'Indiranagar') ?> (PIN: <?= e($ho['pincode'] ?: '560038') ?>)
              </div>
              <div class="d-flex justify-content-between align-items-center border-top pt-2 small flex-wrap gap-1">
                <span class="text-muted">Payment: <?= e($ho['payment_mode']) ?> • OTP Verified (Code: <?= e($ho['delivery_otp']) ?>)</span>
                <span class="fw-bold text-success font-monospace">Earned: ₹45.00 + ₹15 Distance = ₹60.00</span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>

    <!-- =======================================================================
         TAB 3: EARNINGS & PAYOUTS
         ======================================================================= -->
    <div id="viewEarnings" style="display: none;">
      
      <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 pb-3 border-bottom">
          <div>
            <div class="small text-muted text-uppercase fw-bold">Current Cycle Balance</div>
            <div class="display-6 fw-bold text-success font-monospace"><?= format_inr($todayEarnings + $totalTips) ?></div>
          </div>
          <button class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;" onclick="handleCashOut()">
            <i class="bi bi-cash-stack me-1"></i> Instant Payout / Cash Out
          </button>
        </div>

        <h6 class="fw-bold text-dark mb-3">Earnings Breakdown for Today</h6>
        <div class="d-flex justify-content-between py-2 border-bottom small">
          <span class="text-muted">Base Delivery Fees (<?= (int)$deliveredCount ?> Trips @ ₹45.00)</span>
          <span class="fw-bold font-monospace"><?= format_inr($deliveredCount * 45.00) ?></span>
        </div>
        <div class="d-flex justify-content-between py-2 border-bottom small">
          <span class="text-muted">Distance Surcharge (> 2.5 km)</span>
          <span class="fw-bold font-monospace"><?= format_inr($deliveredCount * 15.00) ?></span>
        </div>
        <div class="d-flex justify-content-between py-2 border-bottom small">
          <span class="text-muted">Direct Customer Tips (100% pass-through)</span>
          <span class="fw-bold font-monospace text-primary"><?= format_inr($totalTips) ?></span>
        </div>
        <div class="d-flex justify-content-between pt-3 fw-bold fs-6">
          <span>Net Payable Total</span>
          <span class="text-success font-monospace"><?= format_inr($todayEarnings + $totalTips) ?></span>
        </div>
      </div>

      <div class="card border-0 rounded-4 shadow-sm p-4 bg-white">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-bank me-2 text-success"></i>Direct Deposit Bank Account</h6>
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="fw-bold text-dark">HDFC Bank Ltd (Savings A/C)</div>
            <div class="small text-muted">A/C: ••••••••••4019 • IFSC: HDFC0001234 • Status: <span class="badge bg-success">Verified</span></div>
          </div>
          <span class="badge bg-light text-dark border">Primary</span>
        </div>
      </div>

    </div>

  </main>

  <!-- =========================================================================
       MODAL 1: DOORSTEP OTP VERIFICATION MODAL (UC-17)
       ========================================================================= -->
  <div class="modal fade" id="otpVerifyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
      <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
        
        <div class="p-4 text-center text-white" style="background: linear-gradient(135deg, #1C3322 0%, #2E6F40 100%);">
          <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-20 p-3 mb-2" style="width: 58px; height: 58px;">
            <i class="bi bi-shield-lock-fill fs-3 text-white"></i>
          </div>
          <h5 class="fw-bold mb-1">Verify Doorstep Handshake</h5>
          <p class="small text-white-50 mb-0">Ask the customer for their 6-digit Secret OTP</p>
        </div>

        <div class="p-4">
          <form method="POST" action="<?= DELIVERY_URL ?>/index.php" id="otpForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="verify_otp">
            <input type="hidden" name="order_id" id="modalOrderId" value="">

            <div class="text-center mb-3">
              <div class="small text-muted mb-1 text-uppercase fw-bold" style="letter-spacing:1px;">Delivering Active Order</div>
              <div class="badge bg-dark fs-5 px-3 py-2 rounded-pill font-monospace mb-2" id="modalOrderDisplay">#ZIP-1004</div>
              <div class="fw-bold text-dark fs-5" id="modalCustomerName">Customer Name</div>
              <div id="modalCodNotice" class="badge bg-warning text-dark mt-2 d-none">
                <i class="bi bi-cash-stack me-1"></i> Cash on Delivery: Collect <span id="modalCodAmount">₹0.00</span>
              </div>
            </div>

            <!-- Prominent Demo OTP Banner with Auto-Fill Button -->
            <div class="alert alert-info py-2 px-3 small rounded-3 mb-3 d-flex align-items-center justify-content-between" style="background:#EDFBF2; border-color:#68BA7F; color:#243E2C;">
              <div class="text-start">
                <div class="small text-muted">Customer Secret OTP:</div>
                <div class="fs-4 fw-bold font-monospace text-dark" id="modalOtpHint">------</div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-success rounded-pill fw-bold bg-white shadow-xs" onclick="document.getElementById('inputOtp').value = document.getElementById('modalOtpHint').textContent.trim();">
                <i class="bi bi-magic me-1"></i> 1-Click Fill
              </button>
            </div>

            <div class="mb-3 text-center">
              <label class="form-label small fw-bold text-dark">Enter 6-Digit OTP</label>
              <input type="text" name="otp" id="inputOtp" class="form-control form-control-lg text-center font-monospace fw-bold fs-3" maxlength="6" placeholder="••••••" style="letter-spacing: 8px;" required autofocus>
              <div class="form-text small">Guaranteed by 3NF <code>delivery_dispatches</code> schema</div>
            </div>

            <button type="submit" class="btn btn-rider-primary w-100 py-3 fw-bold fs-6 mb-2 shadow-sm">
              <i class="bi bi-check-circle-fill me-1"></i> Confirm & Complete Delivery
            </button>
            <button type="button" class="btn btn-light w-100 rounded-pill py-2 text-muted" data-bs-dismiss="modal">
              Cancel
            </button>
          </form>
      </div>
    </div>
  </div>

  <!-- =========================================================================
       MODAL 2: TURN-BY-TURN GPS NAVIGATION (UC-16)
       ========================================================================= -->
  <div class="modal fade" id="gpsMapModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
        
        <div class="p-3 bg-dark text-white d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success rounded-pill px-3 py-1">GPS ROUTE SIMULATOR</span>
            <span class="fw-bold font-monospace" id="gpsOrderNum">#ZIP-1002</span>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="p-4 bg-light">
          <!-- Route Visual Header -->
          <div class="card border-0 rounded-4 p-3 bg-white shadow-xs mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <div class="small text-muted">Destination:</div>
                <h6 class="fw-bold text-dark mb-0" id="gpsCustName">Customer Address</h6>
                <div class="small text-muted" id="gpsAddress">Indiranagar 100ft Road</div>
              </div>
              <div class="d-flex gap-3 text-center">
                <div>
                  <div class="small text-muted">Distance</div>
                  <div class="fw-bold text-dark font-monospace fs-5">2.2 km</div>
                </div>
                <div class="vr"></div>
                <div>
                  <div class="small text-muted">ETA</div>
                  <div class="fw-bold text-success font-monospace fs-5">7 mins</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Simulated Map Visual Container -->
          <div class="rounded-4 p-4 text-center position-relative mb-3 overflow-hidden shadow-xs" style="background: #1C2B20; color: #fff; min-height: 220px;">
            <div class="d-flex flex-column justify-content-center align-items-center h-100 py-3">
              <i class="fa-solid fa-map-location-dot fs-1 text-mint mb-2" style="color:#CEFFDC;"></i>
              <h5 class="fw-bold mb-1">Live Turn-by-Turn Route Navigation</h5>
              <p class="small text-white-50 mb-3" style="max-width: 480px;">
                Heading North on 100ft Road towards Metro Pillar 84. Turn right onto 12th Main Road.
              </p>
              <div class="d-flex align-items-center gap-2 bg-white bg-opacity-10 px-3 py-2 rounded-pill small">
                <span class="pulse-dot"></span>
                <span>Speed: 28 km/h • GPS Signal: High Precision</span>
              </div>
            </div>
          </div>

          <!-- Turn Instructions List -->
          <div class="card border-0 rounded-4 p-3 bg-white shadow-xs">
            <h6 class="fw-bold text-dark small text-uppercase mb-2">Turn Instructions</h6>
            <div class="d-flex align-items-center gap-3 py-2 border-bottom small">
              <i class="bi bi-arrow-up-circle-fill text-success fs-5"></i>
              <div>Depart from Zippy Kitchen Hub onto 100 Feet Road (Continue 1.1 km)</div>
            </div>
            <div class="d-flex align-items-center gap-3 py-2 border-bottom small">
              <i class="bi bi-arrow-right-circle-fill text-primary fs-5"></i>
              <div>Turn RIGHT at Indiranagar Metro Station onto 12th Main (600m)</div>
            </div>
            <div class="d-flex align-items-center gap-3 py-2 small">
              <i class="bi bi-geo-alt-fill text-danger fs-5"></i>
              <div>Arrive at Customer Doorstep (Flat / Villa entrance)</div>
            </div>
          </div>
        </div>

        <div class="p-3 bg-white border-top text-end">
          <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close Navigation</button>
        </div>

      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Tab switching
    function switchTab(tabId) {
      document.getElementById('viewOrders').style.display = (tabId === 'orders') ? 'block' : 'none';
      document.getElementById('viewHistory').style.display = (tabId === 'history') ? 'block' : 'none';
      document.getElementById('viewEarnings').style.display = (tabId === 'earnings') ? 'block' : 'none';

      document.getElementById('tabBtnOrders').classList.toggle('active', tabId === 'orders');
      document.getElementById('tabBtnHistory').classList.toggle('active', tabId === 'history');
      document.getElementById('tabBtnEarnings').classList.toggle('active', tabId === 'earnings');
    }

    // Open OTP Verification Modal
    function openOtpModal(orderId, otp, custName, payMode, totalAmount) {
      document.getElementById('modalOrderId').value = orderId;
      document.getElementById('modalOrderDisplay').textContent = '#ZIP-' + orderId;
      document.getElementById('modalCustomerName').textContent = custName;
      document.getElementById('modalOtpHint').textContent = otp;
      document.getElementById('inputOtp').value = '';

      const codNotice = document.getElementById('modalCodNotice');
      if (payMode === 'COD') {
        codNotice.classList.remove('d-none');
        document.getElementById('modalCodAmount').textContent = totalAmount;
      } else {
        codNotice.classList.add('d-none');
      }

      const modal = new bootstrap.Modal(document.getElementById('otpVerifyModal'));
      modal.show();
      setTimeout(() => document.getElementById('inputOtp').focus(), 400);
    }

    // Open GPS Modal
    function openGpsModal(orderId, custName, address) {
      document.getElementById('gpsOrderNum').textContent = '#ZIP-' + orderId;
      document.getElementById('gpsCustName').textContent = custName;
      document.getElementById('gpsAddress').textContent = address;
      const modal = new bootstrap.Modal(document.getElementById('gpsMapModal'));
      modal.show();
    }

    // Instant Payout Simulation
    function handleCashOut() {
      alert('Instant Direct Bank Transfer initiated to HDFC A/C ••••4019 via IMPS (Ref: IMPS-7824901). Payout will reflect within 5-10 minutes!');
    }
  </script>
</body>
</html>
