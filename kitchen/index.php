<?php
/**
 * Dedicated Kitchen Display System (KDS) & Operations Dashboard
 * Zippy - Online Food Ordering Platform
 * MCSP-232: MCA Major Project | Student: Pooja JP
 * 
 * Standalone Kitchen Operations Workspace:
 * Design System: Figma "Lush Forest" & Material Design 3 (M3)
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

// Format Wait Time cleanly to eliminate raw ugly minute strings
if (!function_exists('format_kds_wait_time')) {
    function format_kds_wait_time(int $minutes, ?string $placedAt = null): array {
        if ($minutes < 1) {
            return ['label' => '< 1m', 'class' => 'tag-wait-fresh'];
        } elseif ($minutes < 60) {
            $cls = ($minutes >= 20) ? 'tag-wait-alert' : (($minutes >= 10) ? 'tag-wait-warm' : 'tag-wait-fresh');
            return ['label' => "{$minutes}m", 'class' => $cls];
        } elseif ($minutes < 1440) {
            $h = floor($minutes / 60);
            $m = $minutes % 60;
            $txt = $m > 0 ? "{$h}h {$m}m" : "{$h}h";
            return ['label' => "{$txt} DELAY", 'class' => 'tag-wait-alert'];
        } else {
            return ['label' => 'OVERDUE', 'class' => 'tag-wait-alert'];
        }
    }
}

// Clean human-friendly priority badge
if (!function_exists('get_kds_priority_badge')) {
    function get_kds_priority_badge(array $ticket): array {
        $badge = $ticket['urgency_badge'] ?? null;
        $label = $badge['label'] ?? 'NORMAL';
        if ($label === 'CRITICAL DELAY') {
            return ['label' => 'RUSH', 'class' => 'tag-priority-critical', 'icon' => 'bi-fire'];
        } elseif ($label === 'HIGH PRIORITY') {
            return ['label' => 'PRIORITY', 'class' => 'tag-priority-high', 'icon' => 'bi-lightning-charge-fill'];
        }
        return ['label' => 'STANDARD', 'class' => 'tag-priority-normal', 'icon' => 'bi-check-circle'];
    }
}

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

            // Execute transition via centralized order lifecycle state machine
            $result = transition_order_status($orderId, $targetStatus, $selectedRider, $currentUser['id'] ?? null);

            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                      || (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1');

            if (!$result['success']) {
                $blockMsg = $result['error'] ?? 'Failed to update order status.';
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $blockMsg]);
                    exit;
                }
                set_flash('danger', $blockMsg);
                header("Location: " . KITCHEN_URL . "/index.php");
                exit;
            }

            if ($targetStatus === 'OUT_FOR_DELIVERY') {
                $finalRider = !empty($selectedRider) ? $selectedRider : 'Delivery Partner';
                $msg = "Order #ZIP-{$orderId} handed over to Delivery Boy ({$finalRider}) for fulfillment.";
            } elseif ($targetStatus === 'COOKING') {
                $msg = "Order #ZIP-{$orderId} accepted! Transferred to Stoves for cooking.";
            } elseif ($targetStatus === 'READY') {
                $msg = "Order #ZIP-{$orderId} plated & bagged! Ready at courier counter.";
            } else {
                $msg = $result['message'];
            }

            set_flash('success', $msg);

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'target_status' => $targetStatus, 'order_id' => $orderId, 'message' => $msg]);
                exit;
            }

            header("Location: " . KITCHEN_URL . "/index.php");
            exit;
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

// Automatically ensure ready orders are allocated in FCFS order
auto_allocate_ready_orders_fcfs();

// Sort Ready orders strictly by First-Come First-Served based on food preparation time (ready_at)
usort($laneReady, function($a, $b) {
    $timeA = !empty($a['ready_at']) ? strtotime($a['ready_at']) : strtotime($a['placed_at']);
    $timeB = !empty($b['ready_at']) ? strtotime($b['ready_at']) : strtotime($b['placed_at']);
    if ($timeA === $timeB) {
        return (int)$a['order_id'] <=> (int)$b['order_id'];
    }
    return $timeA <=> $timeB;
});

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

// Fetch active riders list with 1-driver-1-delivery availability
$ridersList = get_all_delivery_riders();

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

// Active Advance Scheduled Orders
$scheduledOrders = array_values(array_filter($allActiveOrders, fn($o) => !empty($o['is_scheduled'])));
$countScheduled = count($scheduledOrders);

// Count stats
$countNew = count($laneNew);
$countCooking = count($laneCooking);
$countReady = count($laneReady);
$countHandedOver = count($recentHandedOver);
$totalActive = $countNew + $countCooking + $countReady;

// State fingerprint for live synchronization
$latestAudit = db_fetch_one("SELECT MAX(audit_id) AS max_audit, MAX(change_timestamp) AS max_time FROM `order_audit_trail`");
$latestDispatch = db_fetch_one("SELECT MAX(assigned_at) AS max_assigned, MAX(dispatched_at) AS max_dispatched, MAX(delivered_at) AS max_delivered FROM `delivery_dispatches`");
$countDeliveredToday = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'DELIVERED' AND DATE(`placed_at`) = CURDATE()");
$kdsInitialStateHash = md5(json_encode([
    'new' => $countNew,
    'cooking' => $countCooking,
    'ready' => $countReady,
    'handed_over' => $countHandedOver,
    'delivered' => $countDeliveredToday,
    'max_audit' => $latestAudit['max_audit'] ?? 0,
    'audit_time' => $latestAudit['max_time'] ?? '',
    'disp_assigned' => $latestDispatch['max_assigned'] ?? '',
    'disp_dispatched' => $latestDispatch['max_dispatched'] ?? '',
    'disp_delivered' => $latestDispatch['max_delivered'] ?? ''
]));

require_once __DIR__ . '/includes/header.php';
?>

    <!-- Tab Contents (Kanban & Focused Station Views) -->
    <div class="tab-content" id="kdsStationTabContent">

      <!-- ====================================================================
           TAB 1: 3-SWIMLANE KANBAN (RESPONSIVE ATELIER VIEW)
           ==================================================================== -->
      <div class="tab-pane fade show active" id="tab-all" role="tabpanel">
        <div class="row g-2 g-xl-3">
          
          <!-- Column 1: Incoming to Accept -->
          <div class="col-12 col-lg-4">
            <div class="swimlane-col">
              <div class="swimlane-header">
                <div class="d-flex align-items-center gap-2">
                  <span class="rounded-circle d-inline-block" style="width:9px; height:9px; background:#2563EB;"></span>
                  <div>
                    <div class="swimlane-title font-outfit">1. Incoming Orders</div>
                    <div class="small text-muted" style="font-size:0.68rem;">Awaiting Stoves • WSJF Ranked</div>
                  </div>
                </div>
                <span class="swimlane-counter badge-lane-new" id="laneNewCountBadge"><?= $countNew ?></span>
              </div>

              <!-- New Order Cards -->
              <div class="swimlane-scroll-body" id="laneNewContainer">
                <?php if (empty($laneNew)): ?>
                  <div class="text-center py-5 text-muted">
                    <i class="bi bi-check2-circle fs-1 d-block mb-2 text-success" style="opacity:0.7;"></i>
                    <div class="small fw-bold" style="color:var(--lush-pine);">No incoming orders waiting.</div>
                    <div class="small text-muted">Station is caught up and ready.</div>
                  </div>
                <?php else: ?>
                  <?php foreach ($laneNew as $t): ?>
                    <div class="ticket-card card-lane-new ticket-item" data-ticket-id="<?= $t['order_id'] ?>" data-search="<?= strtolower(e($t['customer_name'] . ' ' . $t['order_id'])) ?>">
                      
                      <!-- Ticket Header -->
                      <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-1 flex-wrap">
                          <span class="font-mono fw-bold fs-6" style="color:var(--lush-pine);">
                            #ZIP-<?= (int)$t['order_id'] ?>
                          </span>
                          <?php if (!empty($t['is_scheduled'])): ?>
                            <span class="ticket-tag-pill" style="background:#F3E8FF; color:#6B21A8; border:1px solid #D8B4FE; font-weight:700;">
                              <i class="bi bi-calendar2-check-fill"></i> ADVANCE: <?= e($t['scheduled_slot'] ?: date('d M, h:i A', strtotime($t['scheduled_for']))) ?>
                            </span>
                          <?php endif; ?>
                          <?php $pBadge = get_kds_priority_badge($t); ?>
                          <?php if ($pBadge['label'] !== 'STANDARD' && empty($t['is_scheduled'])): ?>
                            <span class="ticket-tag-pill <?= $pBadge['class'] ?>">
                              <i class="bi <?= $pBadge['icon'] ?>"></i> <?= e($pBadge['label']) ?>
                            </span>
                          <?php endif; ?>
                        </div>
                        <?php if (!empty($t['is_scheduled'])): ?>
                          <span class="ticket-tag-pill" style="background:#FAF5FF; color:#7C3AED; border:1px solid #E9D5FF;" title="Scheduled Target Time">
                            <i class="bi bi-alarm"></i> <?= date('d M h:i A', strtotime($t['scheduled_for'])) ?>
                          </span>
                        <?php else: ?>
                          <?php $wait = format_kds_wait_time((int)$t['elapsed_minutes'], $t['placed_at'] ?? null); ?>
                          <span class="ticket-tag-pill <?= $wait['class'] ?>">
                            <i class="bi bi-stopwatch"></i> <?= e($wait['label']) ?>
                          </span>
                        <?php endif; ?>
                      </div>

                      <!-- Customer Meta -->
                      <div class="d-flex justify-content-between align-items-center small text-muted mb-2">
                        <span>Guest: <strong style="color:var(--lush-pine);"><?= e(trim(str_ireplace('(Customer)', '', $t['customer_name']))) ?></strong></span>
                        <span class="font-mono" style="color:var(--lush-forest); font-weight:600;"><i class="fa-solid fa-fire me-1"></i>Est: <?= (int)$t['job_duration'] ?>m</span>
                      </div>

                      <!-- Customer Notes / Instructions -->
                      <?php if (!empty($t['order_notes'])): ?>
                        <div class="kds-card-note mb-2">
                          <i class="bi bi-chat-quote-fill"></i>
                          <span class="text-truncate"><strong>Note:</strong> <?= e($t['order_notes']) ?></span>
                        </div>
                      <?php endif; ?>

                      <!-- Line Items List -->
                      <div class="ticket-items-box mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1 text-muted" style="font-size:0.68rem; text-transform:uppercase; font-weight:700; letter-spacing:0.04em;">
                          <span>Docket (<?= (int)$t['total_portions'] ?> portions)</span>
                          <span class="badge" style="background:var(--lush-mint-soft); color:var(--lush-forest); font-size:0.65rem;"><?= count($t['items']) ?> items</span>
                        </div>
                        <?php foreach ($t['items'] as $it): ?>
                          <div class="py-1 border-bottom border-light-subtle small">
                            <div class="d-flex justify-content-between align-items-center">
                              <div class="d-flex align-items-center gap-2">
                                <span class="<?= $it['is_veg'] ? 'badge-veg-icon' : 'badge-nonveg-icon' ?>"></span>
                                <span class="item-qty-pill"><?= (int)$it['quantity'] ?>×</span>
                                <span class="fw-semibold" style="color:var(--lush-text-main); font-size:0.83rem;"><?= e($it['item_name']) ?></span>
                              </div>
                              <span class="text-muted font-mono" style="font-size:0.72rem;"><?= (int)$it['prep_time_minutes'] ?>m</span>
                            </div>
                            <?php if (!empty($it['customizations'])): ?>
                              <div class="ps-4 mt-1">
                                <span class="badge bg-warning-subtle text-dark border border-warning" style="font-size:0.68rem; font-weight:500;">
                                  <i class="bi bi-sliders me-1 text-warning-emphasis"></i><?= e($it['customizations']) ?>
                                </span>
                              </div>
                            <?php endif; ?>
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
                          <button type="submit" class="btn-action-primary w-100">
                            <span>Accept & Start Cooking</span>
                            <i class="bi bi-arrow-right ms-1"></i>
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
          <div class="col-12 col-lg-4">
            <div class="swimlane-col">
              <div class="swimlane-header">
                <div class="d-flex align-items-center gap-2">
                  <span class="rounded-circle d-inline-block" style="width:9px; height:9px; background:#D97706;"></span>
                  <div>
                    <div class="swimlane-title font-outfit">2. In Preparation (Stoves)</div>
                    <div class="small text-muted" style="font-size:0.68rem;">Active on Stoves & Ovens</div>
                  </div>
                </div>
                <span class="swimlane-counter badge-lane-cooking" id="laneCookingCountBadge"><?= $countCooking ?></span>
              </div>

              <!-- Cooking Order Cards -->
              <div class="swimlane-scroll-body" id="laneCookingContainer">
                <?php if (empty($laneCooking)): ?>
                  <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-fire-burner fs-1 d-block mb-2 text-warning" style="opacity:0.7;"></i>
                    <div class="small fw-bold" style="color:var(--lush-pine);">No dishes currently on stoves.</div>
                    <div class="small text-muted">Accept tickets from Column 1 to commence prep.</div>
                  </div>
                <?php else: ?>
                  <?php foreach ($laneCooking as $t): ?>
                    <div class="ticket-card card-lane-cooking ticket-item" data-ticket-id="<?= $t['order_id'] ?>" data-search="<?= strtolower(e($t['customer_name'] . ' ' . $t['order_id'])) ?>">
                      
                      <!-- Header -->
                      <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-1 flex-wrap">
                          <span class="font-mono fw-bold fs-6" style="color:var(--lush-pine);">
                            #ZIP-<?= (int)$t['order_id'] ?>
                          </span>
                          <?php if (!empty($t['is_scheduled'])): ?>
                            <span class="ticket-tag-pill" style="background:#F3E8FF; color:#6B21A8; border:1px solid #D8B4FE; font-weight:700;">
                              <i class="bi bi-calendar2-check-fill"></i> ADVANCE: <?= e($t['scheduled_slot'] ?: date('d M, h:i A', strtotime($t['scheduled_for']))) ?>
                            </span>
                          <?php endif; ?>
                          <span class="ticket-tag-pill" style="background:#FFF7ED; color:#C2410C; border:1px solid #FED7AA;">
                            <i class="fa-solid fa-fire-burner"></i> ON STOVE
                          </span>
                        </div>
                        <?php if (!empty($t['is_scheduled'])): ?>
                          <span class="ticket-tag-pill" style="background:#FAF5FF; color:#7C3AED; border:1px solid #E9D5FF;" title="Scheduled Target Time">
                            <i class="bi bi-alarm"></i> <?= date('d M h:i A', strtotime($t['scheduled_for'])) ?>
                          </span>
                        <?php else: ?>
                          <?php $wait = format_kds_wait_time((int)$t['elapsed_minutes'], $t['placed_at'] ?? null); ?>
                          <span class="ticket-tag-pill <?= $wait['class'] ?>">
                            <i class="bi bi-clock-history"></i> <?= e($wait['label']) ?>
                          </span>
                        <?php endif; ?>
                      </div>

                      <div class="d-flex justify-content-between align-items-center small text-muted mb-2">
                        <span>Guest: <strong style="color:var(--lush-pine);"><?= e(trim(str_ireplace('(Customer)', '', $t['customer_name']))) ?></strong></span>
                        <span class="font-mono" style="color:var(--lush-forest); font-weight:600;">Est: <?= (int)$t['job_duration'] ?>m</span>
                      </div>

                      <?php if (!empty($t['order_notes'])): ?>
                        <div class="kds-card-note mb-2">
                          <i class="bi bi-chat-quote-fill"></i>
                          <span class="text-truncate"><strong>Note:</strong> <?= e($t['order_notes']) ?></span>
                        </div>
                      <?php endif; ?>

                      <!-- Interactive Prep Checklist -->
                      <div class="ticket-items-box mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1 text-muted" style="font-size:0.68rem; text-transform:uppercase; font-weight:700; letter-spacing:0.04em;">
                          <span>Prep Checklist (<?= count($t['items']) ?> items)</span>
                          <span class="badge" style="background:#FEF3C7; color:#B45309; font-size:0.65rem;">Tap when plated</span>
                        </div>
                        <?php foreach ($t['items'] as $idx => $it): ?>
                          <div class="py-1 border-bottom border-light-subtle small">
                            <div class="d-flex justify-content-between align-items-center">
                              <div class="form-check d-flex align-items-center gap-2 m-0">
                                <input class="form-check-input dish-checkbox" type="checkbox" id="chk_<?= $t['order_id'] ?>_<?= $idx ?>">
                                <label class="form-check-label fw-semibold" for="chk_<?= $t['order_id'] ?>_<?= $idx ?>" style="cursor:pointer; font-size:0.83rem;">
                                  <span class="<?= $it['is_veg'] ? 'badge-veg-icon' : 'badge-nonveg-icon' ?>"></span>
                                  <span class="item-qty-pill"><?= (int)$it['quantity'] ?>×</span>
                                  <span><?= e($it['item_name']) ?></span>
                                </label>
                              </div>
                              <span class="text-muted font-mono" style="font-size:0.72rem;"><?= (int)$it['prep_time_minutes'] ?>m</span>
                            </div>
                            <?php if (!empty($it['customizations'])): ?>
                              <div class="ps-4 mt-1">
                                <span class="badge bg-warning-subtle text-dark border border-warning" style="font-size:0.68rem; font-weight:500;">
                                  <i class="bi bi-sliders me-1 text-warning-emphasis"></i><?= e($it['customizations']) ?>
                                </span>
                              </div>
                            <?php endif; ?>
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
                          <button type="submit" class="btn-action-ready w-100">
                            <i class="bi bi-check2-circle me-1"></i>
                            <span>Mark Ready for Delivery</span>
                            <i class="bi bi-arrow-right ms-1"></i>
                          </button>
                        </form>
                      </div>

                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Column 3: Ready for Delivery (System FCFS Allocation) -->
          <div class="col-12 col-lg-4">
            <div class="swimlane-col">
              <div class="swimlane-header">
                <div class="d-flex align-items-center gap-2">
                  <span class="rounded-circle d-inline-block" style="width:9px; height:9px; background:#16A34A;"></span>
                  <div>
                    <div class="swimlane-title font-outfit">3. Ready for Delivery</div>
                    <div class="small text-muted" style="font-size:0.68rem;">Bagged & Sealed • FCFS Driver Queue</div>
                  </div>
                </div>
                <span class="swimlane-counter badge-lane-ready" id="laneReadyCountBadge"><?= $countReady ?></span>
              </div>

              <!-- Ready Order Cards -->
              <div class="swimlane-scroll-body" id="laneReadyContainer">
                <?php if (empty($laneReady)): ?>
                  <div class="text-center py-5 text-muted">
                    <i class="bi bi-bag-check fs-1 d-block mb-2 text-success" style="opacity:0.7;"></i>
                    <div class="small fw-bold" style="color:var(--lush-pine);">No bagged tickets waiting at window.</div>
                    <div class="small text-muted">Finished orders will appear here for courier dispatch.</div>
                  </div>
                <?php else: ?>
                  <?php foreach ($laneReady as $readyIdx => $t): ?>
                    <?php 
                      $disp = $dispatchesByOrder[$t['order_id']] ?? null;
                      $riderName = $disp['delivery_partner_name'] ?? 'Assigned Courier';
                      $riderPhone = $disp['delivery_partner_phone'] ?? '';
                      $otp = $disp['delivery_otp'] ?? '';
                      $fcfsRank = $readyIdx + 1;
                    ?>
                    <div class="ticket-card card-lane-ready ticket-item" data-ticket-id="<?= $t['order_id'] ?>" data-search="<?= strtolower(e($t['customer_name'] . ' ' . $t['order_id'])) ?>">
                      
                      <!-- Header -->
                      <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-1 flex-wrap">
                          <span class="font-mono fw-bold fs-6" style="color:var(--lush-pine);">
                            #ZIP-<?= (int)$t['order_id'] ?>
                          </span>
                          <?php if (!empty($t['is_scheduled'])): ?>
                            <span class="ticket-tag-pill" style="background:#F3E8FF; color:#6B21A8; border:1px solid #D8B4FE; font-weight:700;">
                              <i class="bi bi-calendar2-check-fill"></i> ADVANCE: <?= e($t['scheduled_slot'] ?: date('d M, h:i A', strtotime($t['scheduled_for']))) ?>
                            </span>
                          <?php endif; ?>
                          <span class="ticket-tag-pill" style="background:#ECFDF5; color:#047857; border:1px solid #A7F3D0;">
                            <i class="bi bi-bag-check-fill"></i> BAGGED & READY
                          </span>
                        </div>
                        <?php if (!empty($otp)): ?>
                          <span class="font-mono fw-bold px-2 py-0.5 rounded-pill small" style="background:var(--lush-mint-soft); border:1.5px solid var(--lush-forest); color:var(--lush-forest); font-size:0.75rem;">
                            OTP: <?= e($otp) ?>
                          </span>
                        <?php endif; ?>
                      </div>

                      <div class="small text-muted mb-2">
                        Customer: <strong style="color:var(--lush-pine);"><?= e(trim(str_ireplace('(Customer)', '', $t['customer_name']))) ?></strong> <span class="font-mono text-muted" style="font-size:0.75rem;">(<?= e($t['customer_phone']) ?>)</span>
                      </div>

                      <!-- Packaging summary -->
                      <div class="ticket-items-box py-1 px-2 mb-2">
                        <div class="small fw-semibold text-truncate" style="color:var(--lush-text-main); font-size:0.8rem;">
                          <i class="bi bi-box-seam me-1 text-success"></i> <?= (int)$t['total_portions'] ?> portions across <?= count($t['items']) ?> items (Thermal Sealed)
                        </div>
                      </div>

                      <!-- System FCFS Dispatch Status Box -->
                      <div class="p-2 rounded-3 border mb-1" style="background: <?= !empty($disp['rider_id']) ? '#F0FDF4; border-color:#BBF7D0 !important;' : '#FFFBEB; border-color:#FDE68A !important;' ?>">
                        <?php if (!empty($disp['rider_id'])): ?>
                          <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="badge rounded-pill text-white" style="background:var(--lush-forest); font-size:0.68rem;">
                              <i class="bi bi-cpu me-1"></i> SYSTEM ALLOCATED (FCFS #<?= $fcfsRank ?>)
                            </span>
                            <span class="small font-mono text-muted" style="font-size:0.72rem;">
                              <?= !empty($t['ready_at']) ? date('h:i A', strtotime($t['ready_at'])) : 'Prepared' ?>
                            </span>
                          </div>
                          <div class="d-flex align-items-center gap-2 small">
                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width:26px; height:26px; background:var(--lush-forest); font-size:0.75rem;">
                              <i class="fa-solid fa-motorcycle"></i>
                            </div>
                            <div class="text-truncate flex-grow-1">
                              <div class="fw-bold" style="color:var(--lush-pine); font-size:0.83rem;"><?= e($riderName) ?></div>
                              <div class="text-muted" style="font-size:0.7rem;">
                                <i class="bi bi-clock me-1 text-success"></i>Awaiting Driver Counter Pickup
                              </div>
                            </div>
                          </div>
                        <?php else: ?>
                          <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="badge bg-warning text-dark rounded-pill" style="font-size:0.68rem;">
                              <i class="bi bi-hourglass-split me-1"></i> FCFS QUEUE #<?= $fcfsRank ?> (FIRST PREPARED)
                            </span>
                            <span class="small font-mono text-muted" style="font-size:0.72rem;">
                              <?= !empty($t['ready_at']) ? date('h:i A', strtotime($t['ready_at'])) : 'Prepared' ?>
                            </span>
                          </div>
                          <div class="small text-muted" style="font-size:0.75rem;">
                            <i class="bi bi-shield-check text-success me-1"></i>Ready for Delivery • System will auto-assign to next free driver.
                          </div>
                        <?php endif; ?>
                      </div>

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
        <div class="card p-3 p-md-4 modal-content-lush">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h4 class="fw-bold font-outfit mb-1" style="color:var(--lush-pine);">1. Incoming Orders (Pending Acceptance)</h4>
              <p class="text-muted small mb-0">Accept incoming customer orders into the kitchen and allocate stove burners.</p>
            </div>
            <span class="badge rounded-pill font-mono px-3 py-2 badge-lane-new">QUEUE: <?= $countNew ?></span>
          </div>

          <div class="row g-3" id="focusNewContainer">
            <?php if (empty($laneNew)): ?>
              <div class="col-12 text-center py-5 text-muted">
                <i class="bi bi-check2-circle fs-1 d-block mb-2 text-success" style="opacity:0.7;"></i>
                <div class="small fw-bold" style="color:var(--lush-pine);">No pending incoming orders.</div>
                <div class="small text-muted">All current tickets have been accepted onto the stoves.</div>
              </div>
            <?php else: ?>
              <?php foreach ($laneNew as $t): ?>
                <div class="col-md-6 col-xl-4">
                  <div class="ticket-card card-lane-new">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <div class="d-flex align-items-center gap-1">
                        <span class="font-mono fw-bold fs-6" style="color:var(--lush-pine);">#ZIP-<?= (int)$t['order_id'] ?></span>
                        <?php $pBadge = get_kds_priority_badge($t); ?>
                        <?php if ($pBadge['label'] !== 'STANDARD'): ?>
                          <span class="ticket-tag-pill <?= $pBadge['class'] ?>"><?= e($pBadge['label']) ?></span>
                        <?php endif; ?>
                      </div>
                      <?php $wait = format_kds_wait_time((int)$t['elapsed_minutes'], $t['placed_at'] ?? null); ?>
                      <span class="ticket-tag-pill <?= $wait['class'] ?>"><i class="bi bi-stopwatch"></i> <?= e($wait['label']) ?></span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center small text-muted mb-2">
                      <span>Guest: <strong style="color:var(--lush-pine);"><?= e($t['customer_name']) ?></strong></span>
                      <span class="font-mono" style="color:var(--lush-forest); font-weight:600;"><i class="fa-solid fa-fire me-1"></i>Est: <?= (int)$t['job_duration'] ?>m</span>
                    </div>

                    <?php if (!empty($t['order_notes'])): ?>
                      <div class="kds-card-note mb-2">
                        <i class="bi bi-chat-quote-fill"></i>
                        <span class="text-truncate"><strong>Note:</strong> <?= e($t['order_notes']) ?></span>
                      </div>
                    <?php endif; ?>

                    <div class="ticket-items-box mb-2">
                      <?php foreach ($t['items'] as $it): ?>
                        <div class="d-flex justify-content-between py-1 border-bottom border-light-subtle small">
                          <div class="d-flex align-items-center gap-2">
                            <span class="<?= $it['is_veg'] ? 'badge-veg-icon' : 'badge-nonveg-icon' ?>"></span>
                            <span class="item-qty-pill"><?= (int)$it['quantity'] ?>×</span>
                            <span class="fw-semibold" style="color:var(--lush-text-main);"><?= e($it['item_name']) ?></span>
                          </div>
                          <span class="text-muted font-mono" style="font-size:0.72rem;"><?= (int)$it['prep_time_minutes'] ?>m</span>
                        </div>
                      <?php endforeach; ?>
                    </div>

                    <div class="d-flex gap-2">
                      <button type="button" class="btn-kot-print" onclick="openKOT(<?= (int)$t['order_id'] ?>)" title="Print Thermal KOT Slip">
                        <i class="bi bi-printer"></i>
                      </button>
                      <form method="POST" action="<?= KITCHEN_URL ?>/index.php" class="flex-grow-1 m-0" onsubmit="handleOrderTransition(event, <?= (int)$t['order_id'] ?>, 'COOKING')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="kds_advance">
                        <input type="hidden" name="order_id" value="<?= (int)$t['order_id'] ?>">
                        <input type="hidden" name="target_status" value="COOKING">
                        <button type="submit" class="btn-action-primary w-100">
                          <span>Accept & Start Cooking</span>
                          <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                      </form>
                    </div>

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
        <div class="card p-3 p-md-4 modal-content-lush">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h4 class="fw-bold font-outfit mb-1" style="color:var(--lush-pine);">2. Stoves & Ovens Active Preparation</h4>
              <p class="text-muted small mb-0">Active culinary preparation on stoves. Check off items as they finish plating.</p>
            </div>
            <span class="badge rounded-pill font-mono px-3 py-2 badge-lane-cooking">COOKING: <?= $countCooking ?></span>
          </div>

          <div class="row g-3" id="focusCookingContainer">
            <?php if (empty($laneCooking)): ?>
              <div class="col-12 text-center py-5 text-muted">
                <i class="fa-solid fa-fire-burner fs-1 d-block mb-2 text-warning" style="opacity:0.7;"></i>
                <div class="small fw-bold" style="color:var(--lush-pine);">No dishes currently on the stoves.</div>
              </div>
            <?php else: ?>
              <?php foreach ($laneCooking as $t): ?>
                <div class="col-md-6 col-xl-4">
                  <div class="ticket-card card-lane-cooking">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <div class="d-flex align-items-center gap-1">
                        <span class="font-mono fw-bold fs-6" style="color:var(--lush-pine);">#ZIP-<?= (int)$t['order_id'] ?></span>
                        <span class="ticket-tag-pill" style="background:#FFF7ED; color:#C2410C; border:1px solid #FED7AA;"><i class="fa-solid fa-fire-burner"></i> ON STOVE</span>
                      </div>
                      <?php $wait = format_kds_wait_time((int)$t['elapsed_minutes'], $t['placed_at'] ?? null); ?>
                      <span class="ticket-tag-pill <?= $wait['class'] ?>"><i class="bi bi-clock-history"></i> <?= e($wait['label']) ?></span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center small text-muted mb-2">
                      <span>Guest: <strong style="color:var(--lush-pine);"><?= e($t['customer_name']) ?></strong></span>
                      <span class="font-mono" style="color:var(--lush-forest); font-weight:600;">Est: <?= (int)$t['job_duration'] ?>m</span>
                    </div>

                    <?php if (!empty($t['order_notes'])): ?>
                      <div class="kds-card-note mb-2">
                        <i class="bi bi-chat-quote-fill"></i>
                        <span class="text-truncate"><strong>Note:</strong> <?= e($t['order_notes']) ?></span>
                      </div>
                    <?php endif; ?>

                    <div class="ticket-items-box mb-2">
                      <?php foreach ($t['items'] as $idx => $it): ?>
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle small">
                          <div class="form-check d-flex align-items-center gap-2 m-0">
                            <input class="form-check-input dish-checkbox" type="checkbox" id="chk_foc_<?= $t['order_id'] ?>_<?= $idx ?>">
                            <label class="form-check-label fw-semibold" for="chk_foc_<?= $t['order_id'] ?>_<?= $idx ?>" style="color:var(--lush-text-main); cursor:pointer;">
                              <span class="<?= $it['is_veg'] ? 'badge-veg-icon' : 'badge-nonveg-icon' ?>"></span>
                              <span class="item-qty-pill"><?= (int)$it['quantity'] ?>×</span>
                              <span><?= e($it['item_name']) ?></span>
                            </label>
                          </div>
                          <span class="text-muted font-mono" style="font-size:0.72rem;"><?= (int)$it['prep_time_minutes'] ?>m</span>
                        </div>
                      <?php endforeach; ?>
                    </div>

                    <div class="d-flex gap-2">
                      <button type="button" class="btn-kot-print" onclick="openKOT(<?= (int)$t['order_id'] ?>)" title="Print Thermal KOT Slip">
                        <i class="bi bi-printer"></i>
                      </button>
                      <form method="POST" action="<?= KITCHEN_URL ?>/index.php" class="flex-grow-1 m-0" onsubmit="handleOrderTransition(event, <?= (int)$t['order_id'] ?>, 'READY')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="kds_advance">
                        <input type="hidden" name="order_id" value="<?= (int)$t['order_id'] ?>">
                        <input type="hidden" name="target_status" value="READY">
                        <button type="submit" class="btn-action-ready w-100">
                          <i class="bi bi-check2-circle me-1"></i>
                          <span>Mark Ready for Delivery</span>
                          <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                      </form>
                    </div>

                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ====================================================================
           TAB 4: READY FOR DELIVERY (FOCUSED VIEW - SYSTEM FCFS ALLOCATION)
           ==================================================================== -->
      <div class="tab-pane fade" id="tab-ready" role="tabpanel">
        <div class="card p-3 p-md-4 modal-content-lush">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h4 class="fw-bold font-outfit mb-1" style="color:var(--lush-pine);">3. Ready for Delivery</h4>
              <p class="text-muted small mb-0">Packaged food parcels waiting for system driver allocation and courier pickup.</p>
            </div>
            <span class="badge rounded-pill font-mono px-3 py-2 badge-lane-ready">READY: <?= $countReady ?></span>
          </div>

          <div class="row g-3" id="focusReadyContainer">
            <?php if (empty($laneReady)): ?>
              <div class="col-12 text-center py-5 text-muted">No orders waiting for courier pickup.</div>
            <?php else: ?>
              <?php foreach ($laneReady as $readyIdx => $t): ?>
                <?php 
                  $disp = $dispatchesByOrder[$t['order_id']] ?? null;
                  $riderName = $disp['delivery_partner_name'] ?? 'Assigned Courier';
                  $otp = $disp['delivery_otp'] ?? '';
                  $fcfsRank = $readyIdx + 1;
                ?>
                <div class="col-md-6 col-xl-4">
                  <div class="ticket-card card-lane-ready">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <div class="d-flex align-items-center gap-1">
                        <span class="font-mono fw-bold fs-6" style="color:var(--lush-pine);">#ZIP-<?= (int)$t['order_id'] ?></span>
                        <span class="ticket-tag-pill" style="background:#ECFDF5; color:#047857; border:1px solid #A7F3D0;">BAGGED</span>
                      </div>
                      <?php if (!empty($otp)): ?>
                        <span class="font-mono fw-bold px-2 py-0.5 rounded-pill small" style="background:var(--lush-mint-soft); border:1.5px solid var(--lush-forest); color:var(--lush-forest); font-size:0.75rem;">
                          OTP: <?= e($otp) ?>
                        </span>
                      <?php endif; ?>
                    </div>

                    <div class="small text-muted mb-2">Customer: <strong style="color:var(--lush-pine);"><?= e($t['customer_name']) ?></strong></div>

                    <div class="ticket-items-box py-1 px-2 mb-2">
                      <div class="small fw-semibold text-truncate" style="color:var(--lush-text-main); font-size:0.8rem;">
                        <i class="bi bi-box-seam me-1 text-success"></i> <?= (int)$t['total_portions'] ?> portions across <?= count($t['items']) ?> items
                      </div>
                    </div>

                    <!-- System FCFS Dispatch Status Box -->
                    <div class="p-2 rounded-3 border mb-1" style="background: <?= !empty($disp['rider_id']) ? '#F0FDF4; border-color:#BBF7D0 !important;' : '#FFFBEB; border-color:#FDE68A !important;' ?>">
                      <?php if (!empty($disp['rider_id'])): ?>
                        <div class="d-flex align-items-center justify-content-between mb-1">
                          <span class="badge rounded-pill text-white" style="background:var(--lush-forest); font-size:0.68rem;">
                            <i class="bi bi-cpu me-1"></i> SYSTEM ALLOCATED (FCFS #<?= $fcfsRank ?>)
                          </span>
                          <span class="small font-mono text-muted" style="font-size:0.72rem;">
                            <?= !empty($t['ready_at']) ? date('h:i A', strtotime($t['ready_at'])) : 'Prepared' ?>
                          </span>
                        </div>
                        <div class="d-flex align-items-center gap-2 small">
                          <div class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width:26px; height:26px; background:var(--lush-forest); font-size:0.75rem;">
                            <i class="fa-solid fa-motorcycle"></i>
                          </div>
                          <div class="text-truncate flex-grow-1">
                            <div class="fw-bold" style="color:var(--lush-pine); font-size:0.83rem;"><?= e($riderName) ?></div>
                            <div class="text-muted" style="font-size:0.7rem;">
                              <i class="bi bi-clock me-1 text-success"></i>Awaiting Driver Counter Pickup
                            </div>
                          </div>
                        </div>
                      <?php else: ?>
                        <div class="d-flex align-items-center justify-content-between mb-1">
                          <span class="badge bg-warning text-dark rounded-pill" style="font-size:0.68rem;">
                            <i class="bi bi-hourglass-split me-1"></i> FCFS QUEUE #<?= $fcfsRank ?> (FIRST PREPARED)
                          </span>
                          <span class="small font-mono text-muted" style="font-size:0.72rem;">
                            <?= !empty($t['ready_at']) ? date('h:i A', strtotime($t['ready_at'])) : 'Prepared' ?>
                          </span>
                        </div>
                        <div class="small text-muted" style="font-size:0.75rem;">
                          <i class="bi bi-shield-check text-success me-1"></i>Ready for Delivery • System will auto-assign to next free driver.
                        </div>
                      <?php endif; ?>
                    </div>

                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ====================================================================
           TAB: ADVANCE SCHEDULED MEAL ORDERS (CULINARY PRE-ORDER QUEUE)
           ==================================================================== -->
      <div class="tab-pane fade" id="tab-scheduled" role="tabpanel">
        <div class="card p-3 p-md-4 modal-content-lush">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
              <h4 class="fw-bold font-outfit mb-1" style="color:#6B21A8;">
                <i class="bi bi-calendar2-check-fill me-2" style="color:#7C3AED;"></i>Advance Scheduled Meal Orders
              </h4>
              <p class="text-muted small mb-0">Pre-booked customer orders scheduled for advance meal slots. Plan batch preparation and mise-en-place ahead of time.</p>
            </div>
            
            <div class="d-flex align-items-center gap-2">
              <span class="badge rounded-pill text-white px-3 py-2 font-mono" style="background:#7C3AED; font-size:0.85rem;">
                <i class="bi bi-calendar-event me-1"></i> ACTIVE PRE-ORDERS: <?= $countScheduled ?>
              </span>
            </div>
          </div>

          <?php if (empty($scheduledOrders)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-calendar-check fs-1 d-block mb-3" style="color:#7C3AED; opacity:0.6;"></i>
              <h5 class="fw-bold font-outfit" style="color:var(--lush-pine);">No advance scheduled orders currently queued.</h5>
              <p class="small text-muted mb-0">When customers choose "Schedule in Advance" during checkout, their tickets appear here for advance kitchen staging.</p>
            </div>
          <?php else: ?>
            <div class="row g-3">
              <?php foreach ($scheduledOrders as $t): ?>
                <div class="col-12 col-lg-6 col-xl-4">
                  <div class="ticket-card card-lane-new ticket-item h-100 d-flex flex-column" data-ticket-id="<?= $t['order_id'] ?>" data-search="<?= strtolower(e($t['customer_name'] . ' ' . $t['order_id'])) ?>" style="border-top: 4px solid #7C3AED !important;">
                    
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="font-mono fw-bold fs-6" style="color:var(--lush-pine);">
                          #ZIP-<?= (int)$t['order_id'] ?>
                        </span>
                        <span class="badge rounded-pill text-white px-2 py-1" style="background:#7C3AED; font-size:0.72rem;">
                          <i class="bi bi-calendar2-check-fill me-1"></i> <?= e($t['scheduled_slot'] ?: date('d M, h:i A', strtotime($t['scheduled_for']))) ?>
                        </span>
                      </div>
                      <span class="badge bg-light text-dark border small">
                        <?= e($t['order_status']) ?>
                      </span>
                    </div>

                    <!-- Target Time Callout -->
                    <div class="p-2 rounded-3 mb-2 small" style="background:#FAF5FF; border:1px solid #E9D5FF; color:#581C87;">
                      <div class="d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-clock-history me-1"></i> Target Delivery:</span>
                        <strong class="font-mono"><?= date('d-M-Y h:i A', strtotime($t['scheduled_for'])) ?></strong>
                      </div>
                    </div>

                    <!-- Customer Meta -->
                    <div class="d-flex justify-content-between align-items-center small text-muted mb-2">
                      <span>Customer: <strong style="color:var(--lush-pine);"><?= e(trim(str_ireplace('(Customer)', '', $t['customer_name']))) ?></strong></span>
                      <span class="font-mono" style="color:var(--lush-forest); font-weight:600;"><i class="fa-solid fa-fire me-1"></i>Prep: <?= (int)$t['job_duration'] ?>m</span>
                    </div>

                    <!-- Customer Notes -->
                    <?php if (!empty($t['order_notes'])): ?>
                      <div class="kds-card-note mb-2">
                        <i class="bi bi-chat-quote-fill"></i>
                        <span class="text-truncate"><strong>Note:</strong> <?= e($t['order_notes']) ?></span>
                      </div>
                    <?php endif; ?>

                    <!-- Items List -->
                    <div class="ticket-items-box mb-3 flex-grow-1">
                      <div class="d-flex justify-content-between align-items-center mb-1 text-muted" style="font-size:0.68rem; text-transform:uppercase; font-weight:700;">
                        <span>Docket (<?= (int)$t['total_portions'] ?> portions)</span>
                        <span class="badge" style="background:var(--lush-mint-soft); color:var(--lush-forest); font-size:0.65rem;"><?= count($t['items']) ?> items</span>
                      </div>
                      <?php foreach ($t['items'] as $it): ?>
                        <div class="py-1 border-bottom border-light-subtle small">
                          <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                              <span class="<?= $it['is_veg'] ? 'badge-veg-icon' : 'badge-nonveg-icon' ?>"></span>
                              <span class="item-qty-pill"><?= (int)$it['quantity'] ?>×</span>
                              <span class="fw-semibold text-truncate" style="color:var(--lush-text-main); font-size:0.83rem; max-width:180px;"><?= e($it['item_name']) ?></span>
                            </div>
                            <span class="text-muted font-mono" style="font-size:0.72rem;"><?= (int)$it['prep_time_minutes'] ?>m</span>
                          </div>
                          <?php if (!empty($it['customizations'])): ?>
                            <div class="ps-4 mt-1">
                              <span class="badge bg-warning-subtle text-dark border border-warning" style="font-size:0.68rem; font-weight:500;">
                                <i class="bi bi-sliders me-1 text-warning-emphasis"></i><?= e($it['customizations']) ?>
                              </span>
                            </div>
                          <?php endif; ?>
                        </div>
                      <?php endforeach; ?>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex gap-2 pt-2 border-top">
                      <button type="button" class="btn-kot-print" onclick="openKOT(<?= (int)$t['order_id'] ?>)" title="Print Thermal KOT Slip">
                        <i class="bi bi-printer"></i>
                      </button>
                      <?php if (in_array($t['order_status'], ['PLACED', 'CONFIRMED'])): ?>
                        <form method="POST" action="<?= KITCHEN_URL ?>/index.php" class="flex-grow-1 m-0" onsubmit="handleOrderTransition(event, <?= (int)$t['order_id'] ?>, 'COOKING')">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="kds_advance">
                          <input type="hidden" name="order_id" value="<?= (int)$t['order_id'] ?>">
                          <input type="hidden" name="target_status" value="COOKING">
                          <button type="submit" class="btn-action-primary w-100">
                            <span>Accept & Cook Now</span>
                            <i class="bi bi-arrow-right ms-1"></i>
                          </button>
                        </form>
                      <?php elseif ($t['order_status'] === 'COOKING'): ?>
                        <form method="POST" action="<?= KITCHEN_URL ?>/index.php" class="flex-grow-1 m-0" onsubmit="handleOrderTransition(event, <?= (int)$t['order_id'] ?>, 'READY')">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="kds_advance">
                          <input type="hidden" name="order_id" value="<?= (int)$t['order_id'] ?>">
                          <input type="hidden" name="target_status" value="READY">
                          <button type="submit" class="btn-action-ready w-100">
                            <i class="bi bi-check2-circle me-1"></i>
                            <span>Mark Plated & Ready</span>
                          </button>
                        </form>
                      <?php elseif ($t['order_status'] === 'READY'): ?>
                        <span class="badge bg-success w-100 py-2 d-flex align-items-center justify-content-center">
                          <i class="bi bi-bag-check-fill me-1"></i> Plated & Ready at Counter
                        </span>
                      <?php endif; ?>
                    </div>

                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ====================================================================
           TAB 5: ORDER HISTORY & FULFILLED DOCKET ARCHIVE
           ==================================================================== -->
      <div class="tab-pane fade" id="tab-history" role="tabpanel">
        <div class="card p-3 p-md-4 modal-content-lush">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
              <h4 class="fw-bold font-outfit mb-1" style="color:var(--lush-pine);">
                <i class="bi bi-clock-history me-2" style="color:var(--lush-forest);"></i>Culinary Order Preparation History
              </h4>
              <p class="text-muted small mb-0">Complete historical archive of all cooked, plated, and dispatched food tickets.</p>
            </div>
            
            <div class="d-flex align-items-center gap-2">
              <span class="badge rounded-pill font-mono px-3 py-2" style="background:var(--lush-mint-soft); color:var(--lush-forest); border:1px solid var(--lush-border);">
                FULFILLED: <?= count($orderHistory) ?>
              </span>
              <div class="position-relative" style="width: 230px;">
                <input type="text" id="kdsHistorySearchInput" class="form-control form-control-sm rounded-pill" placeholder="Search history..." onkeyup="filterHistoryTable()" style="border:1.5px solid var(--lush-border); padding-left:14px;">
              </div>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="kdsHistoryTable" style="background:transparent;">
              <thead class="text-muted small text-uppercase" style="border-bottom: 2px solid var(--lush-border);">
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
                        <div class="font-mono fw-bold fs-6" style="color:var(--lush-pine);">#ZIP-<?= (int)$h['order_id'] ?></div>
                        <div class="text-muted small font-mono" style="font-size:0.75rem;">
                          <?= !empty($h['dispatched_at']) ? date('d M, h:i A', strtotime($h['dispatched_at'])) : date('d M, h:i A', strtotime($h['placed_at'])) ?>
                        </div>
                      </td>

                      <td>
                        <div class="fw-bold" style="color:var(--lush-pine);"><?= e($h['customer_name']) ?></div>
                        <div class="text-muted small font-mono"><?= e($h['customer_phone'] ?: 'N/A') ?></div>
                      </td>

                      <td style="max-width: 260px;">
                        <div class="d-flex flex-column gap-1">
                          <?php foreach ($hItems as $it): ?>
                            <div class="small">
                              <div class="d-flex align-items-center gap-1">
                                <span class="<?= $it['is_veg'] ? 'badge-veg-icon' : 'badge-nonveg-icon' ?>"></span>
                                <span class="item-qty-pill" style="font-size:0.7rem;"><?= (int)$it['quantity'] ?>x</span>
                                <span class="text-truncate"><?= e($it['item_name']) ?></span>
                              </div>
                              <?php if (!empty($it['customizations'])): ?>
                                <div class="text-muted ms-3" style="font-size:0.68rem;">
                                  <i class="bi bi-sliders me-1"></i><?= e($it['customizations']) ?>
                                </div>
                              <?php endif; ?>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      </td>

                      <td>
                        <span class="badge border font-mono" style="background:var(--lush-mint-soft); color:var(--lush-forest); border-color:var(--lush-border) !important;">
                          <i class="bi bi-stopwatch me-1"></i><?= (int)$h['prep_minutes'] ?>m prep
                        </span>
                      </td>

                      <td>
                        <div class="fw-bold small" style="color:var(--lush-pine);"><i class="fa-solid fa-motorcycle me-1" style="color:var(--lush-forest);"></i> <?= e($h['delivery_partner_name'] ?: 'Express Fleet Rider') ?></div>
                        <?php if (!empty($h['delivery_otp'])): ?>
                          <span class="font-mono text-muted small" style="font-size:0.75rem;">OTP: <strong><?= e($h['delivery_otp']) ?></strong></span>
                        <?php endif; ?>
                      </td>

                      <td>
                        <div class="fw-bold font-mono" style="color:var(--lush-pine);"><?= format_inr($h['total_amount']) ?></div>
                        <?php if ($h['payment_mode'] === 'COD'): ?>
                          <span class="badge" style="background:#FEF3C7; color:#92400E; border:1px solid #FDE68A; font-size:0.65rem;">
                            <?= ($h['payment_status'] === 'SUCCESSFUL') ? 'COD CASH PAID' : 'COD PENDING' ?>
                          </span>
                        <?php else: ?>
                          <span class="badge" style="background:var(--lush-mint-soft); color:var(--lush-forest); border:1px solid var(--lush-border); font-size:0.65rem;">
                            ONLINE PAID
                          </span>
                        <?php endif; ?>
                      </td>

                      <td>
                        <span class="ticket-tag-pill tag-priority-normal" style="font-size:0.68rem;">
                          <?= e($h['order_status']) ?>
                        </span>
                      </td>

                      <td class="text-end">
                        <button type="button" class="btn btn-sm btn-kds-pill px-3" onclick="openKOT(<?= (int)$h['order_id'] ?>)" title="Reprint Kitchen Order Ticket Slip">
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
          <div class="card p-3 p-md-4 modal-content-lush mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
              <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold fs-3 shadow-sm" style="width:62px; height:62px; background:linear-gradient(135deg, var(--lush-pine), var(--lush-forest));">
                  <i class="fa-solid fa-utensils"></i>
                </div>
                <div>
                  <div class="d-flex align-items-center gap-2">
                    <h4 class="fw-bold font-outfit mb-0" style="color:var(--lush-pine);"><?= e($chefProfile['full_name']) ?></h4>
                    <span class="badge <?= ($chefProfile['duty_status'] === 'ON_DUTY') ? 'bg-success' : 'bg-secondary' ?> text-white rounded-pill px-2 py-1">
                      <?= e($chefProfile['duty_status']) ?>
                    </span>
                  </div>
                  <div class="text-muted small mt-1">
                    <span class="fw-bold" style="color:var(--lush-forest);"><?= e($chefProfile['designation']) ?></span> &bull; 
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
                  <div class="fs-4 fw-bold font-mono" style="color:var(--lush-pine);"><?= format_inr($chefProfile['base_salary']) ?></div>
                  <div class="small text-muted" style="font-size:0.7rem;">/ month</div>
                </div>
                <div class="text-end border-end pe-3">
                  <div class="small text-muted text-uppercase fw-bold" style="font-size:0.68rem;">Per Docket Rate</div>
                  <div class="fs-4 fw-bold font-mono" style="color:var(--lush-pine);"><?= format_inr($chefProfile['hourly_or_order_rate']) ?></div>
                  <div class="small text-muted" style="font-size:0.7rem;">incentive</div>
                </div>
                <div class="text-end">
                  <div class="small text-muted text-uppercase fw-bold" style="font-size:0.68rem;">Orders Prepared</div>
                  <div class="fs-4 fw-bold font-mono" style="color:var(--lush-pine);"><?= (int)$chefProfile['total_orders_handled'] ?></div>
                  <div class="small text-warning" style="font-size:0.7rem;">★ <?= number_format((float)$chefProfile['rating'], 2) ?> Rating</div>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Salary Payouts History Ledger -->
        <div class="card p-3 p-md-4 modal-content-lush">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
              <h4 class="fw-bold font-outfit mb-1" style="color:var(--lush-pine);">
                <i class="bi bi-wallet2 me-2" style="color:var(--lush-forest);"></i>Disbursed Salary & Payout History
              </h4>
              <p class="text-muted small mb-0">Record of monthly remuneration, culinary incentives, and digital payslips.</p>
            </div>
            <span class="badge rounded-pill font-mono px-3 py-2" style="background:var(--lush-mint-soft); color:var(--lush-forest); border:1px solid var(--lush-border);">
              RECORDS: <?= count($chefPayouts) ?>
            </span>
          </div>

          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="background:transparent;">
              <thead class="text-muted small text-uppercase" style="border-bottom: 2px solid var(--lush-border);">
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
                        <span class="font-mono fw-bold" style="color:var(--lush-pine);">#PAY-<?= (int)$p['payout_id'] ?></span>
                        <div class="font-mono text-muted small text-truncate" style="max-width:140px; font-size:0.7rem;">
                          <?= e($p['transaction_reference'] ?: 'N/A') ?>
                        </div>
                      </td>

                      <td>
                        <div class="fw-bold" style="color:var(--lush-pine);"><?= e($p['month_year']) ?></div>
                      </td>

                      <td class="font-mono"><?= format_inr($p['base_amount']) ?></td>
                      <td class="font-mono text-success">+<?= format_inr($p['incentives_amount']) ?></td>
                      <td class="font-mono text-danger">-<?= format_inr($p['deductions_amount']) ?></td>

                      <td>
                        <div class="fw-bold font-mono fs-6" style="color:var(--lush-forest);"><?= format_inr($p['net_paid']) ?></div>
                      </td>

                      <td>
                        <span class="badge border font-mono" style="background:var(--lush-bg); color:var(--lush-text-main); border-color:var(--lush-border) !important; font-size:0.72rem;">
                          <?= e($p['payment_mode']) ?>
                        </span>
                      </td>

                      <td class="text-muted small font-mono">
                        <?= date('d M Y, h:i A', strtotime($p['paid_at'])) ?>
                      </td>

                      <td>
                        <span class="badge rounded-pill px-2 py-1 font-mono" style="background:var(--lush-mint); color:var(--lush-pine); font-size:0.68rem;">
                          <?= e($p['payout_status']) ?>
                        </span>
                      </td>

                      <td class="text-end">
                        <button type="button" class="btn btn-sm btn-kds-pill px-3" data-bs-toggle="modal" data-bs-target="#payslipModal<?= (int)$p['payout_id'] ?>">
                          <i class="bi bi-file-earmark-text me-1" style="color:var(--lush-forest);"></i> Payslip
                        </button>
                      </td>
                    </tr>

                    <!-- Payslip Modal -->
                    <div class="modal fade" id="payslipModal<?= (int)$p['payout_id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content modal-content-lush">
                          <div class="modal-header modal-header-lush">
                            <div>
                              <h5 class="modal-title font-outfit fw-bold mb-0" style="color:var(--lush-pine);">Zippy Operations | Culinary Payslip</h5>
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

                            <hr style="border-color:var(--lush-border);">

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

                            <hr style="border-color:var(--lush-border);">

                            <div class="d-flex justify-content-between mb-2 fs-6">
                              <span class="fw-bold">Net Disbursed Amount:</span>
                              <span class="fw-bold" style="color:var(--lush-forest);"><?= format_inr($p['net_paid']) ?></span>
                            </div>

                            <div class="p-3 rounded-3 mt-3" style="background:var(--lush-mint-soft); border:1px solid var(--lush-border); font-size:0.75rem;">
                              <div>Mode: <strong><?= e($p['payment_mode']) ?></strong></div>
                              <div>Ref: <strong><?= e($p['transaction_reference']) ?></strong></div>
                              <div>Paid At: <strong><?= date('d M Y, h:i A', strtotime($p['paid_at'])) ?></strong></div>
                              <?php if (!empty($p['notes'])): ?>
                                <div class="mt-1 text-muted">Notes: <?= e($p['notes']) ?></div>
                              <?php endif; ?>
                            </div>
                          </div>
                          <div class="modal-footer modal-footer-lush">
                            <button type="button" class="btn btn-kds-pill px-3" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-kds-primary px-4" onclick="window.print()">
                              <i class="bi bi-printer"></i>
                              <span>Print Payslip</span>
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
