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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
