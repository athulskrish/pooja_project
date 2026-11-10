<?php
declare(strict_types=1);
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

// Prevent browser from caching dynamic order dashboard state
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Enforce Rider Authentication
requireRider();
$currentUser = getCurrentUser();

// Initialize or read rider duty state (default: online)
if (!isset($_SESSION['rider_on_duty'])) {
    $_SESSION['rider_on_duty'] = true;
}

// Handle AJAX or POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
              || (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1');

    if (!verifyRequestCsrf()) {
        http_response_code(403);
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF token.']);
            exit;
        }
        setFlash('danger', 'Invalid or expired CSRF token. Please refresh and try again.');
        die('Invalid or expired CSRF token.');
    }

    // 1. Toggle Duty Status
    if ($action === 'toggle_duty') {
        $newDuty = !empty($_POST['duty']) && $_POST['duty'] === '1';
        $res = FleetService::toggleRiderDuty($newDuty);
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'on_duty' => $res['on_duty']]);
            exit;
        }
        setFlash('info', $res['message']);
        header("Location: " . DELIVERY_URL . "/index.php");
        exit;
    }

    // 2. Claim / Pick Up Order from Kitchen
    if ($action === 'claim_order') {
        $claimResult = FleetService::claimOrder(
            (int)($_POST['order_id'] ?? 0),
            $currentUser,
            !empty($_SESSION['rider_on_duty'])
        );

        if ($claimResult['ok']) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $claimResult['message'],
                    'order_id' => $claimResult['order_id']
                ]);
                exit;
            }
            setFlash('success', "Order #ZIP-{$claimResult['order_id']} picked up. Please follow GPS to customer address.");
            header("Location: " . DELIVERY_URL . "/index.php");
            exit;
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $claimResult['message']]);
            exit;
        }
        setFlash($claimResult['flash_type'] ?? 'danger', $claimResult['message']);
        header("Location: " . DELIVERY_URL . "/index.php");
        exit;
    }

    // 3. Verify Customer OTP & Finish Delivery
    if ($action === 'verify_otp') {
        $verifyResult = FleetService::verifyDeliveryOtp(
            (int)($_POST['order_id'] ?? 0),
            (string)($_POST['otp'] ?? ''),
            $currentUser
        );

        if ($verifyResult['ok']) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $verifyResult['ajax_message']
                ]);
                exit;
            }
            setFlash($verifyResult['flash_type'], $verifyResult['flash_message']);
            header("Location: " . DELIVERY_URL . "/index.php");
            exit;
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $verifyResult['ajax_message']]);
            exit;
        }
        setFlash($verifyResult['flash_type'], $verifyResult['flash_message']);
        header("Location: " . DELIVERY_URL . "/index.php");
        exit;
    }
}

$riderData = FleetService::getRiderDashboardData($currentUser);
$activeOrders = $riderData['activeOrders'];
$activeOrderItems = $riderData['activeOrderItems'];
$allocatedOrder = $riderData['allocatedOrder'];
$readyOrders = $riderData['readyOrders'];
$historyOrders = $riderData['historyOrders'];
$earningsSummary = $riderData['earningsSummary'];
$deliveredCount = $riderData['deliveredCount'];
$baseFare = $riderData['baseFare'];
$todayEarnings = $riderData['todayEarnings'];
$totalTips = $riderData['totalTips'];
$totalEarned = $riderData['totalEarned'];
$totalCashedOut = $riderData['totalCashedOut'];
$availableBalance = $riderData['availableBalance'];
$cashoutHistory = $riderData['cashoutHistory'];

require_once __DIR__ . '/includes/header.php';
?>
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
            <span class="badge <?= !empty($activeOrders) ? 'bg-warning text-dark' : ($_SESSION['rider_on_duty'] ? 'bg-success' : 'bg-secondary') ?> rounded-pill px-2 py-1 small" id="dutyStatusPill">
              <?php if (!empty($activeOrders)): ?>
                <i class="bi bi-bicycle me-1"></i> Busy on Route (1/1 Active)
              <?php elseif ($_SESSION['rider_on_duty']): ?>
                <span class="pulse-dot me-1"></span> Available for Dispatch (0/1)
              <?php else: ?>
                Dispatches Paused
              <?php endif; ?>
            </span>
          </div>
          <div class="small text-muted">
            GPS Zone: <strong>Indiranagar Hub</strong> • 1 Delivery at a Time Policy
          </div>
        </div>
      </div>

      <form method="POST" action="<?= DELIVERY_URL ?>/index.php" id="dutyToggleForm" class="m-0">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="toggle_duty">
        <input type="hidden" name="duty" id="dutyHiddenInput" value="<?= $_SESSION['rider_on_duty'] ? '0' : '1' ?>">
        <div class="form-check form-switch fs-4 m-0">
          <input class="form-check-input cursor-pointer" type="checkbox" id="dutyToggleSwitch" <?= $_SESSION['rider_on_duty'] ? 'checked' : '' ?> onchange="toggleDutyAjax(this.checked);">
        </div>
      </form>
    </div>

    <!-- B. SHIFT STATS STRIP -->
    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3">
        <div class="card border-0 rounded-4 p-3 shadow-xs bg-white text-center">
          <div class="small text-muted fw-bold">Today's Earnings</div>
          <div class="fs-4 fw-bold text-success font-monospace"><?= formatInr($todayEarnings) ?></div>
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
          <div class="fs-4 fw-bold text-primary font-monospace"><?= formatInr($totalTips) ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 rounded-4 p-3 shadow-xs bg-white text-center">
          <div class="small text-muted fw-bold">Active Delivery</div>
          <div class="fs-4 fw-bold font-monospace <?= !empty($activeOrders) ? 'text-warning' : 'text-success' ?>">
            <?= count($activeOrders) ?> / 1
          </div>
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
        <div id="activeDeliverySection" class="mb-4">
          <?php if (!empty($activeOrders)): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-bold text-dark text-uppercase small mb-0">
                <i class="bi bi-bicycle text-success me-1"></i> Active Delivery In Progress (1/1 Max)
              </h6>
              <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3 py-1 small fw-bold">
                <i class="bi bi-shield-check me-1"></i> 1 Driver 1 Delivery Policy Active
              </span>
            </div>

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
                        <div class="d-flex gap-2 flex-wrap">
                          <a href="tel:<?= e($order['customer_phone'] ?: '+919876543210') ?>" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold flex-shrink-0">
                            <i class="bi bi-telephone-fill me-1"></i> Call Customer
                          </a>
                          <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold flex-shrink-0" onclick="openSupportModal(<?= (int)$order['order_id'] ?>)">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Issue / SOS
                          </button>
                        </div>
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
                            <i class="bi bi-cash me-1"></i> COD: Collect <?= formatInr($order['total_amount']) ?>
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

                  <!-- Action Buttons: GPS Navigation, Google Maps & OTP Verification -->
                  <div class="d-flex flex-column flex-sm-row gap-2 mb-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill py-2 px-3 fw-bold flex-grow-1" onclick="openGpsModal(<?= (int)$order['order_id'] ?>, <?= e(jsJson((string)($order['customer_name'] ?? ''))) ?>, <?= e(jsJson((string)($order['address_line'] ?: 'Indiranagar 100ft Road'))) ?>)">
                      <i class="fa-solid fa-location-arrow me-1 text-primary"></i> Live Route Simulator
                    </button>

                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode(($order['address_line'] ?: 'Indiranagar') . ', Bangalore, ' . ($order['pincode'] ?: '560038')) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-success rounded-pill py-2 px-3 fw-bold flex-grow-1 text-center d-flex align-items-center justify-content-center gap-1" title="Open native navigation in Google Maps">
                      <i class="bi bi-geo-alt-fill text-danger"></i> Google Maps
                    </a>
                    
                    <button type="button" class="btn btn-rider-primary py-2 px-3 fw-bold flex-grow-1 shadow-sm fs-6" onclick="openOtpModal(<?= (int)$order['order_id'] ?>, <?= e(jsJson((string)($order['delivery_otp'] ?? ''))) ?>, <?= e(jsJson((string)($order['customer_name'] ?? ''))) ?>, <?= e(jsJson((string)($order['payment_mode'] ?? 'ONLINE'))) ?>, <?= e(jsJson((string)formatInr($order['total_amount']))) ?>)">
                      <i class="bi bi-shield-check me-1"></i> Verify OTP & Finish
                    </button>
                  </div>

                  <!-- Discrete Viva / Demo Mode OTP Helper -->
                  <div class="d-flex justify-content-between align-items-center bg-light px-3 py-2 rounded-3 border small">
                    <div class="text-muted d-flex align-items-center gap-1">
                      <i class="bi bi-shield-lock-fill text-success"></i>
                      <span>Doorstep Handshake:</span>
                      <span class="badge bg-secondary bg-opacity-10 text-secondary border small">Customer holds secret OTP</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                      <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-2 py-0 small fw-bold" onclick="toggleDemoOtp(<?= (int)$order['order_id'] ?>)" id="btnRevealOtp<?= (int)$order['order_id'] ?>">
                        <i class="bi bi-eye me-1"></i> Reveal for Demo
                      </button>
                      <span class="font-monospace fw-bold fs-6 text-dark bg-white px-2 py-0 rounded border d-none" id="demoOtpBox<?= (int)$order['order_id'] ?>"><?= e($order['delivery_otp']) ?></span>
                    </div>
                  </div>

                </div>

              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Section 2: Orders Ready in Kitchen (System FCFS Allocation) -->
        <div id="readyOrdersSection" class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h6 class="fw-bold text-dark text-uppercase small mb-0">
                <i class="bi bi-cpu-fill text-success me-1"></i> System Parcel Allocation (FCFS Queue)
              </h6>
              <div class="text-muted" style="font-size:0.75rem;">
                Parcels allocated strictly in First-Come, First-Served order based on preparation completion.
              </div>
            </div>
            <button type="button" class="btn btn-sm btn-link text-success text-decoration-none p-0 fw-semibold" onclick="syncDeliveryApp(true)" title="Force sync ready food from kitchen">
              <i class="bi bi-arrow-clockwise me-1"></i> Sync Now
            </button>
          </div>

          <div id="policyAlertBox" class="<?= empty($activeOrders) ? 'd-none' : '' ?>">
            <!-- 1 Driver 1 Delivery Policy Alert -->
            <div class="alert alert-warning border-0 rounded-4 p-3 d-flex align-items-center gap-3 mb-3 shadow-xs">
              <div class="rounded-circle p-2 bg-warning bg-opacity-25 text-warning d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bi bi-shield-lock-fill fs-5"></i>
              </div>
              <div class="flex-grow-1">
                <div class="fw-bold text-dark mb-0">1 Driver, 1 Delivery Policy Active</div>
                <div class="small text-muted">You are currently fulfilling active delivery <strong id="policyActiveOrderId">#ZIP-<?= !empty($activeOrders) ? (int)$activeOrders[0]['order_id'] : '' ?></strong>. Complete doorstep OTP verification to free your slot. The system will then allocate your next parcel in First-Come, First-Served order.</div>
              </div>
            </div>
          </div>

          <div id="readyOrdersCardsContainer">
            <?php if (empty($activeOrders) && !empty($allocatedOrder)): ?>
              <!-- System Allocated Parcel (FCFS Head of Queue) -->
              <div class="card border-2 rounded-4 p-4 shadow-sm bg-white mb-3" style="border-color: #2E6F40 !important;" id="allocatedOrderCard">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                  <span class="badge rounded-pill px-3 py-1 fw-bold" style="background:#CEFFDC; color:#243E2C; font-size:0.75rem;">
                    <i class="bi bi-cpu-fill me-1"></i> SYSTEM ALLOCATED (FCFS #1 PREPARED)
                  </span>
                  <span class="fw-bold font-monospace text-dark fs-6">#ZIP-<?= (int)$allocatedOrder['order_id'] ?></span>
                </div>
                <div class="small text-muted mb-2">
                  <i class="bi bi-clock-history me-1 text-success"></i> Prepared at <strong><?= !empty($allocatedOrder['ready_at']) ? date('h:i A', (int)strtotime((string)$allocatedOrder['ready_at'])) : date('h:i A', (int)strtotime((string)$allocatedOrder['placed_at'])) ?></strong> • Allocated to you on First-Come, First-Served basis
                </div>
                <h5 class="fw-bold text-dark mb-1">Customer: <?= e($allocatedOrder['customer_name']) ?></h5>
                <p class="small text-muted mb-2">
                  <i class="bi bi-geo-alt me-1 text-danger"></i> <?= e($allocatedOrder['address_line'] ?: 'Indiranagar') ?> (PIN: <?= e($allocatedOrder['pincode'] ?: '560038') ?>)
                </p>
                <div class="small fw-semibold text-success mb-3">
                  <i class="bi bi-cash-coin me-1"></i> Est. Rider Earnings: <strong>₹55.00</strong>
                </div>

                <form method="POST" action="<?= DELIVERY_URL ?>/index.php" class="m-0 claim-order-form" onsubmit="event.preventDefault(); takeReadyFood(<?= (int)$allocatedOrder['order_id'] ?>);">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="claim_order">
                  <input type="hidden" name="order_id" value="<?= (int)$allocatedOrder['order_id'] ?>">
                  <button type="submit" id="btnClaimOrder<?= (int)$allocatedOrder['order_id'] ?>" class="btn btn-success w-100 rounded-pill fw-bold py-2 shadow-xs" style="background:#2E6F40; border-color:#2E6F40;">
                    <i class="bi bi-handbag me-1"></i> Accept &amp; Pick Up Allocated Order &rarr;
                  </button>
                </form>
              </div>

              <?php 
                $otherReady = array_filter($readyOrders, fn(array $ro): bool => (int)$ro['order_id'] !== (int)$allocatedOrder['order_id']); 
              ?>
              <?php if (!empty($otherReady)): ?>
                <div class="mt-3">
                  <div class="small fw-bold text-muted text-uppercase mb-2">
                    <i class="bi bi-list-ol me-1"></i> Upcoming Parcels in Kitchen FCFS Queue (<?= count($otherReady) ?>)
                  </div>
                  <div class="row g-2">
                    <?php $qRank = 2; foreach ($otherReady as $oro): ?>
                      <div class="col-md-6">
                        <div class="card border rounded-3 p-2 bg-light small">
                          <div class="d-flex justify-content-between align-items-center">
                            <span class="badge bg-secondary rounded-pill">Queue #<?= $qRank++ ?></span>
                            <span class="font-mono fw-bold">#ZIP-<?= (int)$oro['order_id'] ?></span>
                          </div>
                          <div class="text-truncate text-muted mt-1">Customer: <?= e($oro['customer_name']) ?></div>
                          <div class="text-muted" style="font-size:0.7rem;">Prepared: <?= !empty($oro['ready_at']) ? date('h:i A', (int)strtotime((string)$oro['ready_at'])) : 'Recently' ?></div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>

            <?php elseif (empty($activeOrders) && empty($allocatedOrder) && empty($readyOrders)): ?>
              <div class="card border-0 rounded-4 p-4 text-center bg-white shadow-xs" id="noReadyOrdersCard">
                <i class="bi bi-check2-circle fs-2 text-success mb-2"></i>
                <h6 class="fw-bold text-dark mb-1">No Orders Waiting for Pickup</h6>
                <p class="small text-muted mb-0">Kitchen orders will be automatically allocated to you in First-Come, First-Served order as soon as cooks mark them "Ready for Delivery".</p>
              </div>
            <?php elseif (!empty($activeOrders)): ?>
              <div class="card border-0 rounded-4 p-3 text-center bg-white shadow-xs text-muted small">
                <i class="bi bi-lock-fill text-warning fs-3 mb-1"></i>
                <div>Complete your active delivery #ZIP-<?= (int)$activeOrders[0]['order_id'] ?> to receive your next FCFS parcel allocation.</div>
              </div>
            <?php endif; ?>
          </div>
        </div>

      <?php endif; ?>

    </div>

    <!-- =======================================================================
         TAB 2: TRIP HISTORY
         ======================================================================= -->
    <div id="viewHistory" style="display: none;">
      
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0">Delivered Trips (<?= count($historyOrders) ?>)</h5>
        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill small"><i class="bi bi-check2-circle me-1"></i> Live Fleet Sync</span>
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
                <span class="fw-bold text-success font-monospace">Earned: <?= formatInr($baseFare) ?> + <?= formatInr($earningsSummary['distance_rate']) ?> Distance + <?= formatInr($earningsSummary['tip_rate']) ?> Tip = <?= formatInr($earningsSummary['trip_payout_rate']) ?></span>
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
            <div class="small text-muted text-uppercase fw-bold">Available Payout Balance</div>
            <div class="display-6 fw-bold text-success font-monospace" id="riderAvailableBalance" data-amount="<?= number_format($availableBalance, 2, '.', '') ?>"><?= formatInr($availableBalance) ?></div>
          </div>
          <button type="button" id="btnCashOutMain" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;" onclick="handleCashOut()" <?= $availableBalance <= 0 ? 'disabled' : '' ?>>
            <i class="bi bi-cash-stack me-1"></i> <?= $availableBalance > 0 ? 'Instant Payout / Cash Out' : 'All Earnings Settled' ?>
          </button>
        </div>

        <h6 class="fw-bold text-dark mb-3">Earnings Breakdown &amp; Settlement Ledger</h6>
        <div class="d-flex justify-content-between py-2 border-bottom small">
          <span class="text-muted">Base Delivery Fees (<?= (int)$deliveredCount ?> Trips @ <?= formatInr($baseFare) ?>)</span>
          <span class="fw-bold font-monospace"><?= formatInr($earningsSummary['base_fare_total']) ?></span>
        </div>
        <div class="d-flex justify-content-between py-2 border-bottom small">
          <span class="text-muted">Distance Surcharge (> 2.5 km @ <?= formatInr($earningsSummary['distance_rate']) ?>)</span>
          <span class="fw-bold font-monospace"><?= formatInr($earningsSummary['distance_total']) ?></span>
        </div>
        <div class="d-flex justify-content-between py-2 border-bottom small">
          <span class="text-muted">Direct Customer Tips (100% pass-through @ <?= formatInr($earningsSummary['tip_rate']) ?>)</span>
          <span class="fw-bold font-monospace text-primary"><?= formatInr($totalTips) ?></span>
        </div>
        <div class="d-flex justify-content-between py-2 border-bottom small">
          <span class="text-dark fw-semibold">Gross Accumulated Trip Earnings</span>
          <span class="fw-bold font-monospace text-dark"><?= formatInr($totalEarned) ?></span>
        </div>
        <div class="d-flex justify-content-between py-2 border-bottom small">
          <span class="text-muted">Less: Settled Instant IMPS Cashouts</span>
          <span class="fw-bold font-monospace text-danger" id="riderTotalCashedOut">-<?= formatInr($totalCashedOut) ?></span>
        </div>
        <div class="d-flex justify-content-between pt-3 fw-bold fs-6">
          <span>Net Available Payout Balance</span>
          <span class="text-success font-monospace" id="riderNetAvailableSummary"><?= formatInr($availableBalance) ?></span>
        </div>
      </div>

      <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-bank me-2 text-success"></i>Direct Deposit Bank Account</h6>
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="fw-bold text-dark">HDFC Bank Ltd (Savings A/C)</div>
            <div class="small text-muted">A/C: ••••••••••4019 • IFSC: HDFC0001234 • Status: <span class="badge bg-success">Verified</span></div>
          </div>
          <span class="badge bg-light text-dark border">Primary</span>
        </div>
      </div>

      <!-- Recent IMPS Cashouts Table -->
      <div class="card border-0 rounded-4 shadow-sm p-4 bg-white">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold text-dark mb-0"><i class="bi bi-receipt-cutoff me-2 text-success"></i>Recent Instant IMPS Cashouts</h6>
          <span class="badge bg-light text-dark border rounded-pill" id="cashoutCountBadge"><?= count($cashoutHistory) ?> Transfers</span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0 small" id="cashoutHistoryTable">
            <thead class="table-light text-muted">
              <tr>
                <th>Reference ID</th>
                <th>Date &amp; Time</th>
                <th>Bank Destination</th>
                <th class="text-end">Amount</th>
                <th class="text-end">Status</th>
              </tr>
            </thead>
            <tbody id="cashoutHistoryBody">
              <?php if (empty($cashoutHistory)): ?>
                <tr id="emptyCashoutRow">
                  <td colspan="5" class="text-center text-muted py-4">No instant IMPS cashouts initiated yet.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($cashoutHistory as $ch): ?>
                  <tr>
                    <td class="font-monospace fw-bold text-dark"><?= e($ch['transaction_reference']) ?></td>
                    <td class="text-muted"><?= date('d M Y, h:i A', strtotime($ch['paid_at'])) ?></td>
                    <td class="text-muted">HDFC Bank ••••4019</td>
                    <td class="text-end font-monospace fw-bold text-success"><?= formatInr((float)$ch['net_paid']) ?></td>
                    <td class="text-end">
                      <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2">
                        <i class="bi bi-check-circle-fill me-1"></i><?= e($ch['payout_status']) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
