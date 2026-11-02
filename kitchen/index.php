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
                                `delivery_status` = 'IN_TRANSIT',
                                `current_latitude` = 12.97840000,
                                `current_longitude` = 77.64080000,
                                `route_progress` = 0,
                                `distance_remaining_km` = 2.40,
                                `estimated_minutes_remaining` = 15
                            WHERE `order_id` = :id
                        ", [
                            ':rname' => $finalRider,
                            ':id' => $orderId
                        ]);
                    } else {
                        $newOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                        db_query("
                            INSERT INTO `delivery_dispatches` 
                            (`order_id`, `delivery_partner_name`, `delivery_partner_phone`, `delivery_otp`, `assigned_at`, `dispatched_at`, `delivery_status`, `current_latitude`, `current_longitude`, `route_progress`, `distance_remaining_km`, `estimated_minutes_remaining`)
                            VALUES (:id, :rname, '+91 96320 98765', :otp, NOW(), NOW(), 'IN_TRANSIT', 12.97840000, 77.64080000, 0, 2.40, 15)
                        ", [
                            ':id' => $orderId,
                            ':rname' => $finalRider,
                            ':otp' => $newOtp
                        ]);
                    }

                    $msg = "Order #ZIP-{$orderId} handed over to Delivery Boy ({$finalRider}) for fulfillment.";
                    set_flash('success', $msg);
                } elseif ($targetStatus === 'COOKING') {
                    $msg = "Order #ZIP-{$orderId} accepted! Transferred to Stoves for cooking.";
                    set_flash('success', $msg);
                } elseif ($targetStatus === 'READY') {
                    $msg = "Order #ZIP-{$orderId} plated & bagged! Ready at courier counter.";
                    set_flash('success', $msg);
                }

                $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                          || (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1');
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'target_status' => $targetStatus, 'order_id' => $orderId, 'message' => $msg ?? 'Order updated']);
                    exit;
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

// Fetch Complete Fulfilled Kitchen Order History
$orderHistory = db_fetch_all("
    SELECT o.*, u.full_name AS customer_name, u.phone_number AS customer_phone,
           d.delivery_partner_name, d.delivery_otp, d.dispatched_at, d.delivered_at,
           p.payment_mode, p.payment_status,
           TIMESTAMPDIFF(MINUTE, o.placed_at, COALESCE(d.dispatched_at, o.placed_at)) AS prep_minutes
    FROM `orders` o
    JOIN `users` u ON o.user_id = u.user_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    WHERE o.order_status IN ('OUT_FOR_DELIVERY', 'DELIVERED')
    ORDER BY COALESCE(d.dispatched_at, o.placed_at) DESC
    LIMIT 60
");

// Group line items for history dockets
$historyOrderIds = array_column($orderHistory, 'order_id');
$historyItemsGrouped = [];
if (!empty($historyOrderIds)) {
    $pl = implode(',', array_fill(0, count($historyOrderIds), '?'));
    $hItems = db_fetch_all("
        SELECT oi.*, m.item_name, m.is_veg 
        FROM `order_items` oi 
        JOIN `menu_items` m ON oi.item_id = m.item_id 
        WHERE oi.order_id IN ($pl)
    ", $historyOrderIds);
    foreach ($hItems as $hit) {
        $historyItemsGrouped[$hit['order_id']][] = $hit;
    }
}

// Fetch Staff Profile for Payments & Salary Tab
$chefProfile = db_fetch_one("
    SELECT sp.*, u.full_name, u.email, u.phone_number 
    FROM `staff_profiles` sp 
    JOIN `users` u ON sp.user_id = u.user_id 
    WHERE sp.user_id = :uid
", [':uid' => $currentUser['id']]);

if (!$chefProfile) {
    $chefProfile = db_fetch_one("
        SELECT sp.*, u.full_name, u.email, u.phone_number 
        FROM `staff_profiles` sp 
        JOIN `users` u ON sp.user_id = u.user_id 
        WHERE sp.department = 'KITCHEN' 
        ORDER BY sp.profile_id ASC 
        LIMIT 1
    ");
}

// Fetch Salary Payouts History
$chefPayouts = [];
if ($chefProfile) {
    $chefPayouts = db_fetch_all("
        SELECT * FROM `staff_salary_payouts` 
        WHERE `user_id` = :uid 
        ORDER BY `paid_at` DESC
    ", [':uid' => $chefProfile['user_id']]);
}
$recentHandedOver = $orderHistory;

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

require_once __DIR__ . '/includes/header.php';
?>

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
            <i class="bi bi-clock-history me-1"></i> Order History (<span id="tabCountHistory"><?= count($orderHistory) ?></span>)
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-salary-btn" data-bs-toggle="pill" data-bs-target="#tab-salary" type="button" role="tab">
            <i class="bi bi-wallet2 me-1"></i> Payments (Salary)
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
            <!-- ====================================================================
           TAB 5: ORDER HISTORY & FULFILLED DOCKET ARCHIVE
           ==================================================================== -->
      <div class="tab-pane fade" id="tab-history" role="tabpanel">
        <div class="card p-4 modal-content-noir">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
              <h4 class="fw-bold font-outfit text-dark mb-1">
                <i class="bi bi-clock-history me-2 text-dark"></i>Culinary Order Preparation History
              </h4>
              <p class="text-muted small mb-0">Complete historical archive of all cooked, plated, and dispatched food tickets.</p>
            </div>
            
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-black text-white px-3 py-2 rounded-pill font-mono">
                FULFILLED: <?= count($orderHistory) ?>
              </span>
              <div class="position-relative" style="width: 250px;">
                <input type="text" id="kdsHistorySearchInput" class="form-control form-control-sm rounded-pill border-dark" placeholder="Search history tickets..." onkeyup="filterHistoryTable()">
              </div>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="kdsHistoryTable" style="background:transparent;">
              <thead class="text-muted small text-uppercase" style="border-bottom: 2px solid #000000;">
                <tr>
                  <th class="font-mono">Docket</th>
                  <th>Customer Info</th>
                  <th>Dishes Cooked & Portions</th>
                  <th>Prep Time</th>
                  <th>Courier / OTP</th>
                  <th>Bill & Payment</th>
                  <th>Status</th>
                  <th class="text-end">KOT Slip</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($orderHistory)): ?>
                  <tr>
                    <td colspan="8" class="text-center py-5 text-muted">No historical dispatches recorded.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($orderHistory as $h): ?>
                    <?php 
                      $hItems = $historyItemsGrouped[$h['order_id']] ?? [];
                      $itemNames = array_map(fn($it) => $it['item_name'], $hItems);
                      $searchRowText = strtolower("ZIP-{$h['order_id']} {$h['customer_name']} {$h['customer_phone']} {$h['delivery_partner_name']} {$h['delivery_otp']} {$h['order_status']} " . implode(' ', $itemNames));
                    ?>
                    <tr class="history-table-row" data-search="<?= e($searchRowText) ?>">
                      <td>
                        <div class="font-mono fw-bold text-dark fs-6">#ZIP-<?= (int)$h['order_id'] ?></div>
                        <div class="text-muted small font-mono" style="font-size:0.75rem;">
                          <?= !empty($h['dispatched_at']) ? date('d M, h:i A', strtotime($h['dispatched_at'])) : date('d M, h:i A', strtotime($h['placed_at'])) ?>
                        </div>
                      </td>

                      <td>
                        <div class="fw-bold text-dark"><?= e($h['customer_name']) ?></div>
                        <div class="text-muted small font-mono"><?= e($h['customer_phone'] ?: 'N/A') ?></div>
                      </td>

                      <td style="max-width: 260px;">
                        <div class="d-flex flex-column gap-1">
                          <?php foreach ($hItems as $it): ?>
                            <div class="small d-flex align-items-center gap-1">
                              <span class="badge <?= $it['is_veg'] ? 'bg-success' : 'bg-danger' ?> px-1" style="font-size:0.6rem;">
                                <?= (int)$it['quantity'] ?>x
                              </span>
                              <span class="text-truncate"><?= e($it['item_name']) ?></span>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      </td>

                      <td>
                        <span class="badge bg-light text-dark border font-mono">
                          <i class="bi bi-stopwatch me-1"></i><?= (int)$h['prep_minutes'] ?>m prep
                        </span>
                      </td>

                      <td>
                        <div class="fw-bold text-dark small"><i class="fa-solid fa-motorcycle me-1"></i> <?= e($h['delivery_partner_name'] ?: 'Express Fleet Rider') ?></div>
                        <?php if (!empty($h['delivery_otp'])): ?>
                          <span class="font-mono text-muted small" style="font-size:0.75rem;">OTP: <strong><?= e($h['delivery_otp']) ?></strong></span>
                        <?php endif; ?>
                      </td>

                      <td>
                        <div class="fw-bold font-mono text-dark"><?= format_inr($h['total_amount']) ?></div>
                        <?php if ($h['payment_mode'] === 'COD'): ?>
                          <span class="badge bg-warning text-dark border border-warning" style="font-size:0.65rem;">
                            <?= ($h['payment_status'] === 'SUCCESSFUL') ? 'COD CASH PAID' : 'COD PENDING' ?>
                          </span>
                        <?php else: ?>
                          <span class="badge bg-dark text-white" style="font-size:0.65rem;">
                            ONLINE PAID
                          </span>
                        <?php endif; ?>
                      </td>

                      <td>
                        <span class="ticket-tag-pill ticket-tag-dark" style="font-size:0.68rem;">
                          <?= e($h['order_status']) ?>
                        </span>
                      </td>

                      <td class="text-end">
                        <button type="button" class="btn btn-sm btn-noir-outline px-3" onclick="openKOT(<?= (int)$h['order_id'] ?>)" title="Reprint Kitchen Order Ticket Slip">
                          <i class="bi bi-printer me-1"></i> KOT
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

      <!-- ====================================================================
           TAB 6: KITCHEN STAFF PAYMENTS & SALARY
           ==================================================================== -->
      <div class="tab-pane fade" id="tab-salary" role="tabpanel">
        
        <!-- Chef Profile Banner -->
        <?php if ($chefProfile): ?>
          <div class="card p-4 modal-content-noir mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
              <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-black text-white d-flex align-items-center justify-content-center fw-bold fs-3" style="width:64px; height:64px;">
                  <i class="fa-solid fa-utensils"></i>
                </div>
                <div>
                  <div class="d-flex align-items-center gap-2">
                    <h4 class="fw-bold font-outfit text-dark mb-0"><?= e($chefProfile['full_name']) ?></h4>
                    <span class="badge <?= ($chefProfile['duty_status'] === 'ON_DUTY') ? 'bg-success' : 'bg-secondary' ?> text-white rounded-pill px-2 py-1">
                      <?= e($chefProfile['duty_status']) ?>
                    </span>
                  </div>
                  <div class="text-muted small mt-1">
                    <span class="fw-bold text-dark"><?= e($chefProfile['designation']) ?></span> &bull; 
                    <?= e($chefProfile['specialization']) ?>
                  </div>
                  <div class="text-muted small" style="font-size:0.75rem;">
                    Shift: <strong><?= e($chefProfile['shift_timings']) ?></strong> &bull; 
                    Joined: <strong><?= date('M Y', strtotime($chefProfile['joined_date'])) ?></strong>
                  </div>
                </div>
              </div>

              <div class="d-flex flex-wrap gap-3">
                <div class="text-end border-end pe-3">
                  <div class="small text-muted text-uppercase fw-bold" style="font-size:0.68rem;">Base Salary</div>
                  <div class="fs-4 fw-bold font-mono text-dark"><?= format_inr($chefProfile['base_salary']) ?></div>
                  <div class="small text-muted" style="font-size:0.7rem;">/ month</div>
                </div>
                <div class="text-end border-end pe-3">
                  <div class="small text-muted text-uppercase fw-bold" style="font-size:0.68rem;">Per Docket Rate</div>
                  <div class="fs-4 fw-bold font-mono text-dark"><?= format_inr($chefProfile['hourly_or_order_rate']) ?></div>
                  <div class="small text-muted" style="font-size:0.7rem;">incentive</div>
                </div>
                <div class="text-end">
                  <div class="small text-muted text-uppercase fw-bold" style="font-size:0.68rem;">Orders Prepared</div>
                  <div class="fs-4 fw-bold font-mono text-dark"><?= (int)$chefProfile['total_orders_handled'] ?></div>
                  <div class="small text-warning" style="font-size:0.7rem;">★ <?= number_format((float)$chefProfile['rating'], 2) ?> Rating</div>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Salary Payouts History Ledger -->
        <div class="card p-4 modal-content-noir">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
              <h4 class="fw-bold font-outfit text-dark mb-1">
                <i class="bi bi-wallet2 me-2 text-dark"></i>Disbursed Salary & Payout History
              </h4>
              <p class="text-muted small mb-0">Record of monthly remuneration, culinary incentives, and digital payslips.</p>
            </div>
            <span class="badge bg-black text-white px-3 py-2 rounded-pill font-mono">
              RECORDS: <?= count($chefPayouts) ?>
            </span>
          </div>

          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="background:transparent;">
              <thead class="text-muted small text-uppercase" style="border-bottom: 2px solid #000000;">
                <tr>
                  <th class="font-mono">Payout Ref</th>
                  <th>Period</th>
                  <th>Base Stipend</th>
                  <th>Incentives</th>
                  <th>Deductions</th>
                  <th>Net Paid</th>
                  <th>Method</th>
                  <th>Disbursed Date</th>
                  <th>Status</th>
                  <th class="text-end">Payslip</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($chefPayouts)): ?>
                  <tr>
                    <td colspan="10" class="text-center py-5 text-muted">No salary payouts recorded for this chef profile yet.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($chefPayouts as $p): ?>
                    <tr>
                      <td>
                        <span class="font-mono fw-bold text-dark">#PAY-<?= (int)$p['payout_id'] ?></span>
                        <div class="font-mono text-muted small text-truncate" style="max-width:140px; font-size:0.7rem;">
                          <?= e($p['transaction_reference'] ?: 'N/A') ?>
                        </div>
                      </td>

                      <td>
                        <div class="fw-bold text-dark"><?= e($p['month_year']) ?></div>
                      </td>

                      <td class="font-mono"><?= format_inr($p['base_amount']) ?></td>
                      <td class="font-mono text-success">+<?= format_inr($p['incentives_amount']) ?></td>
                      <td class="font-mono text-danger">-<?= format_inr($p['deductions_amount']) ?></td>

                      <td>
                        <div class="fw-bold font-mono text-dark fs-6"><?= format_inr($p['net_paid']) ?></div>
                      </td>

                      <td>
                        <span class="badge bg-light text-dark border font-mono" style="font-size:0.72rem;">
                          <?= e($p['payment_mode']) ?>
                        </span>
                      </td>

                      <td class="text-muted small font-mono">
                        <?= date('d M Y, h:i A', strtotime($p['paid_at'])) ?>
                      </td>

                      <td>
                        <span class="badge bg-black text-white rounded-pill px-2 py-1 font-mono" style="font-size:0.68rem;">
                          <?= e($p['payout_status']) ?>
                        </span>
                      </td>

                      <td class="text-end">
                        <button type="button" class="btn btn-sm btn-noir-outline px-3" data-bs-toggle="modal" data-bs-target="#payslipModal<?= (int)$p['payout_id'] ?>">
                          <i class="bi bi-file-earmark-text me-1"></i> Payslip
                        </button>
                      </td>
                    </tr>

                    <!-- Payslip Modal -->
                    <div class="modal fade" id="payslipModal<?= (int)$p['payout_id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content modal-content-noir border-2 border-dark">
                          <div class="modal-header border-bottom border-dark pb-3">
                            <div>
                              <h5 class="modal-title font-outfit fw-bold text-dark mb-0">Zippy Operations | Culinary Payslip</h5>
                              <span class="text-muted small font-mono">Disbursement Advice &bull; <?= e($p['month_year']) ?></span>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body p-4 text-start font-mono small">
                            <div class="d-flex justify-content-between mb-2">
                              <span class="text-muted">Employee:</span>
                              <strong><?= e($chefProfile['full_name'] ?? 'Chef') ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                              <span class="text-muted">Designation:</span>
                              <span><?= e($chefProfile['designation'] ?? 'Kitchen Staff') ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                              <span class="text-muted">Department:</span>
                              <span>Kitchen Atelier / WSJF</span>
                            </div>

                            <hr class="border-dark">

                            <div class="d-flex justify-content-between mb-2">
                              <span>Base Salary:</span>
                              <strong><?= format_inr($p['base_amount']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2 text-success">
                              <span>Cooking & Volume Incentives:</span>
                              <strong>+<?= format_inr($p['incentives_amount']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2 text-danger">
                              <span>Deductions:</span>
                              <strong>-<?= format_inr($p['deductions_amount']) ?></strong>
                            </div>

                            <hr class="border-dark">

                            <div class="d-flex justify-content-between mb-2 fs-6">
                              <span class="fw-bold">Net Disbursed Amount:</span>
                              <span class="fw-bold text-dark"><?= format_inr($p['net_paid']) ?></span>
                            </div>

                            <div class="p-2 bg-light border border-dark rounded mt-3" style="font-size:0.75rem;">
                              <div>Mode: <strong><?= e($p['payment_mode']) ?></strong></div>
                              <div>Ref: <strong><?= e($p['transaction_reference']) ?></strong></div>
                              <div>Paid At: <strong><?= date('d M Y, h:i A', strtotime($p['paid_at'])) ?></strong></div>
                              <?php if (!empty($p['notes'])): ?>
                                <div class="mt-1 text-muted">Notes: <?= e($p['notes']) ?></div>
                              <?php endif; ?>
                            </div>
                          </div>
                          <div class="modal-footer border-top border-dark pt-3">
                            <button type="button" class="btn btn-noir-outline rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-noir-primary rounded-pill px-4" onclick="window.print()">
                              <i class="bi bi-printer me-1"></i> Print Payslip
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>

                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <script>
        function filterHistoryTable() {
          const q = document.getElementById('kdsHistorySearchInput').value.toLowerCase().trim();
          const rows = document.querySelectorAll('.history-table-row');
          rows.forEach(r => {
            const s = r.getAttribute('data-search') || '';
            r.style.display = (!q || s.includes(q)) ? '' : 'none';
          });
        }
      </script>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
