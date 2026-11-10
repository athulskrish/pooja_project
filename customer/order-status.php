<?php
declare(strict_types=1);
/**
 * Live Order Progress Tracker & Courier Dispatch
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Live Order Status Tracker';
$activePage = 'orders';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireCustomer();
$currentUser = getCurrentUser();

// Handle Customer Order Cancellation (within 60s of placement - Statechart 4.1 & UC-09)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid or expired session token.');
    } else {
        $cancelOrderId = (int)($_POST['order_id'] ?? 0);
        $cancelRes = OrderService::cancelCustomerOrder($cancelOrderId, (int)$currentUser['id'], isAdmin());
        setFlash($cancelRes['flash_type'], $cancelRes['message']);
        header("Location: " . CUSTOMER_URL . "/order-status.php?order_id=" . $cancelOrderId);
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<!-- Leaflet Map CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<style>
  .leaflet-popup-content-wrapper { border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
  .leaflet-popup-content { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.85rem; font-weight: 500; }
  .pulse-dot { display: inline-block; width: 8px; height: 8px; background-color: #2E6F40; border-radius: 50%; animation: pulse-ring 1.4s infinite ease-out; }
  @keyframes pulse-ring { 0% { transform: scale(0.9); opacity: 1; } 50% { transform: scale(1.6); opacity: 0.4; } 100% { transform: scale(0.9); opacity: 1; } }
  .custom-rider-marker { text-align: center; }
  .rider-bike-icon { background: #2E6F40; color: #fff; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(46,111,64,0.4); border: 2px solid #fff; font-size: 15px; }
</style>
<?php

$uid = (int)$currentUser['id'];
$requestedOrderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;
$statusData = OrderService::getOrderStatusData($requestedOrderId, $uid);

if (empty($statusData['found'])) {
    echo "<div class='container py-5 text-center'><h4>No active orders found</h4><a href='" . CUSTOMER_URL . "/index.php' class='btn btn-success rounded-pill mt-3'>Browse Menu</a></div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if (empty($statusData['authorized'])) {
    echo "<div class='container py-5 text-center'><h4>Access Denied</h4><p class='text-muted small'>You do not have permission to view this order.</p><a href='" . CUSTOMER_URL . "/order-history.php' class='btn btn-success rounded-pill mt-2'>View Your Orders</a></div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$order = $statusData['order'];
$orderId = $statusData['orderId'];
$otherActiveOrders = $statusData['otherActiveOrders'];
$items = $statusData['items'];
$statusReorderJson = $statusData['statusReorderJson'];
$currentStep = $statusData['currentStep'];
$isDelivered = $statusData['isDelivered'];
$isCancelled = $statusData['isCancelled'];
$remainingSeconds = $statusData['remainingSeconds'];
$initialCountdownText = $statusData['initialCountdownText'];
$hasReviewed = $statusData['hasReviewed'];
$cancellationSecondsLeft = $statusData['cancellationSecondsLeft'];
$canCancel = $statusData['canCancel'];
?>

<main class="py-4">
  <div class="container-xl">
    
    <?php if ($isCancelled): ?>
      <div class="alert alert-danger rounded-4 p-3 mb-4 d-flex align-items-center gap-3 border-danger shadow-xs">
        <i class="bi bi-x-octagon-fill fs-2 text-danger"></i>
        <div>
          <h5 class="fw-bold mb-1 text-danger">Order Cancelled & 100% Refund Issued</h5>
          <p class="small mb-0 text-dark">This order was cancelled within the permissible 60-second window. A full refund of <strong><?= formatInr($order['total_amount']) ?></strong> has been initiated (Status: <strong>REFUNDED</strong>) and all ordered items have been restored to restaurant inventory.</p>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($canCancel): ?>
      <div id="cancellationBanner" class="alert alert-warning rounded-4 p-3 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3 border-warning shadow-xs">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-clock-history fs-3 text-warning"></i>
          <div>
            <h6 class="fw-bold mb-0 text-dark">Changed your mind? Cancel within <span id="cancelSecondsRemaining" class="badge bg-danger font-monospace fs-6"><?= $cancellationSecondsLeft ?>s</span></h6>
            <span class="small text-muted">100% full refund before kitchen preparation locks in (FSSAI 60-Second Policy).</span>
          </div>
        </div>
        <form method="POST" action="<?= CUSTOMER_URL ?>/order-status.php?order_id=<?= (int)$order['order_id'] ?>" onsubmit="return confirm('Are you sure you want to cancel Order #ZIP-<?= (int)$order['order_id'] ?>? You will receive a 100% instant refund.');" class="m-0">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="cancel_order">
          <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
          <button type="submit" id="btnCancelOrder" class="btn btn-danger rounded-pill px-4 fw-bold shadow-xs">
            <i class="bi bi-x-circle me-1"></i> Cancel Order (100% Refund)
          </button>
        </form>
      </div>
    <?php endif; ?>

    <?php if (!empty($otherActiveOrders)): ?>
      <div class="alert alert-warning py-2 px-3 rounded-4 mb-4 d-flex justify-content-between align-items-center shadow-xs">
        <div>
          <i class="bi bi-bicycle me-1 fs-5"></i>
          <span>You also have Order <strong>#ZIP-<?= (int)$otherActiveOrders[0]['order_id'] ?></strong> in progress (<?= e($otherActiveOrders[0]['order_status']) ?>).</span>
        </div>
        <a href="<?= CUSTOMER_URL ?>/order-status.php?order_id=<?= (int)$otherActiveOrders[0]['order_id'] ?>" class="btn btn-sm btn-dark rounded-pill px-3 fw-bold">
          Track #ZIP-<?= (int)$otherActiveOrders[0]['order_id'] ?> &rarr;
        </a>
      </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
      <div>
        <h2 class="fw-bold mb-0 font-outfit text-dark"><i class="bi bi-geo-alt-fill text-success me-2"></i>Live Order Tracking</h2>
        <p class="text-muted small mb-0">Tracking Order <strong class="fs-6 font-monospace text-dark">#ZIP-<?= (int)$order['order_id'] ?></strong> | Placed on <?= date('d M Y, h:i A', strtotime($order['placed_at'])) ?></p>
      </div>
      <div id="topBadgeContainer">
        <?= getStatusBadge($order['order_status']) ?>
      </div>
    </div>

    <div class="row g-4">
      
      <!-- Left Column: Status Stepper & Courier Card -->
      <div class="col-lg-8">
        
        <!-- Live Countdown & Stepper Card -->
        <div class="card border-0 rounded-4 shadow-sm p-4 p-md-5 bg-white mb-4">
          
          <?php if (!empty($order['is_scheduled'])): ?>
            <div class="alert alert-info d-flex align-items-center gap-3 p-3 rounded-4 mb-4 border-0" style="background:#F3E8FF; color:#5B21B6;">
              <i class="bi bi-calendar2-check-fill fs-3 flex-shrink-0"></i>
              <div>
                <div class="fw-bold fs-6">Advance Scheduled Meal Order</div>
                <div class="small">
                  This meal is booked in advance for <strong><?= e($order['scheduled_slot'] ?: date('D, d M h:i A', strtotime($order['scheduled_for']))) ?></strong>. The kitchen team has this ticket logged on their Kitchen Display System (KDS) and will commence preparation right before delivery to guarantee peak flavor & warmth!
                </div>
              </div>
            </div>
          <?php endif; ?>

          <div class="d-flex flex-wrap align-items-center justify-content-between border-bottom pb-4 mb-4 gap-3">
            <div>
              <?php if ($isDelivered): ?>
                <span class="badge rounded-pill px-3 py-1 mb-1 bg-success text-white" id="scheduleBadge">
                  <i class="bi bi-check-circle-fill me-1"></i> DELIVERED
                </span>
                <h3 class="fw-bold font-outfit mb-0 text-dark" id="statusHeading">Order Delivered Successfully!</h3>
                <div class="text-muted small" id="statusSubtext"><i class="bi bi-patch-check-fill text-success me-1"></i>Delivered on time • Doorstep handover completed</div>
              <?php elseif ($isCancelled): ?>
                <span class="badge rounded-pill px-3 py-1 mb-1 bg-danger text-white" id="scheduleBadge">
                  <i class="bi bi-x-circle-fill me-1"></i> CANCELLED
                </span>
                <h3 class="fw-bold font-outfit mb-0 text-dark" id="statusHeading">Order Was Cancelled</h3>
                <div class="text-muted small" id="statusSubtext">This order has been cancelled and closed</div>
              <?php elseif (!empty($order['is_scheduled'])): ?>
                <span class="badge rounded-pill px-3 py-1 mb-1 text-white shadow-xs" id="scheduleBadge" style="background:#7C3AED;">
                  <i class="bi bi-calendar2-check-fill me-1"></i> ADVANCE PRE-ORDER
                </span>
                <h3 class="fw-bold font-outfit mb-0 text-dark" id="statusHeading">
                  Scheduled for <?= e($order['scheduled_slot'] ?: (!empty($order['scheduled_for']) ? date('D, d M h:i A', (int)strtotime((string)$order['scheduled_for'])) : 'Scheduled')) ?>
                </h3>
                <div class="text-muted small" id="statusSubtext">Target Delivery: <strong><?= !empty($order['scheduled_for']) ? date('d M Y, h:i A', (int)strtotime((string)$order['scheduled_for'])) : 'TBD' ?></strong> • Queued in Kitchen KDS</div>
              <?php else: ?>
                <span class="badge rounded-pill px-3 py-1 mb-1" id="scheduleBadge" style="background:#CEFFDC; color:#243E2C;">
                  ⚡ ON SCHEDULE
                </span>
                <h3 class="fw-bold font-outfit mb-0 text-dark" id="statusHeading">
                  <?php if ($order['order_status'] === 'OUT_FOR_DELIVERY'): ?>
                    Rider is on the way to your door
                  <?php elseif ($order['order_status'] === 'COOKING'): ?>
                    Chef is cooking your hot meal
                  <?php else: ?>
                    Order confirmed & queued in kitchen
                  <?php endif; ?>
                </h3>
                <div class="text-muted small" id="statusSubtext">Estimated Delivery: <strong><?= date('h:i A', (int)strtotime((string)($order['estimated_delivery_time'] ?: '+25 minutes'))) ?></strong></div>
              <?php endif; ?>
            </div>

            <!-- Countdown Timer Box -->
            <div class="text-center p-3 rounded-4 shadow-sm" id="countdownBox" style="background: #243E2C; color: #fff; min-width: 140px;">
              <div class="small text-white-50 text-uppercase fw-semibold" id="countdownLabel" style="font-size: 0.65rem; letter-spacing: 0.5px;">
                <?= ($isDelivered || $isCancelled) ? 'ORDER STATUS' : (!empty($order['is_scheduled']) ? 'SCHEDULED TARGET' : 'ESTIMATED TIME') ?>
              </div>
              <div class="fs-2 fw-bold font-monospace text-mint" id="live-eta-countdown" 
                   data-status="<?= e($order['order_status']) ?>" 
                   data-order-id="<?= (int)$order['order_id'] ?>"
                   data-remaining-seconds="<?= max(0, (int)$remainingSeconds) ?>"
                   data-est-time="<?= !empty($order['estimated_delivery_time']) ? date('c', (int)strtotime((string)$order['estimated_delivery_time'])) : '' ?>"
                   data-placed-at="<?= !empty($order['placed_at']) ? date('c', (int)strtotime((string)$order['placed_at'])) : '' ?>">
                <?php if ($isDelivered): ?>
                  DELIVERED
                <?php elseif ($isCancelled): ?>
                  CANCELLED
                <?php elseif (!empty($order['is_scheduled']) && !empty($order['scheduled_for'])): ?>
                  <?= date('h:i A', (int)strtotime((string)$order['scheduled_for'])) ?>
                <?php elseif (!empty($order['is_scheduled'])): ?>
                  SCHEDULED
                <?php else: ?>
                  <?= $initialCountdownText ?>
                <?php endif; ?>
              </div>
              <div class="small text-white-50" id="countdownSubtext" style="font-size: 0.7rem;">
                <?php if ($isDelivered): ?>
                  ORDER COMPLETED
                <?php elseif ($isCancelled): ?>
                  CLOSED
                <?php elseif (!empty($order['is_scheduled']) && !empty($order['scheduled_for'])): ?>
                  <?= date('d-M', (int)strtotime((string)$order['scheduled_for'])) ?>
                <?php elseif (!empty($order['is_scheduled'])): ?>
                  Arrival Window
                <?php else: ?>
                  MINS REMAINING
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- 4-Stage Progress Stepper -->
          <div class="position-relative my-4">
            <div class="progress" style="height: 4px;">
              <div class="progress-bar bg-success" role="progressbar" style="width: <?= ($currentStep / 3) * 100 ?>%;" id="stepperProgressBar"></div>
            </div>

            <div class="d-flex justify-content-between position-relative mt-n3" style="top: -18px;">
              
              <!-- Step 1: Placed -->
              <div class="text-center">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm <?= $currentStep >= 0 ? 'bg-success text-white' : 'bg-light text-muted border' ?>" style="width: 36px; height: 36px;" id="stepNodePlaced">
                  <i class="bi bi-receipt"></i>
                </div>
                <div class="small fw-bold mt-2 d-none d-sm-block">Placed</div>
              </div>

              <!-- Step 2: Cooking -->
              <div class="text-center">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm <?= $currentStep >= 1 ? 'bg-success text-white' : 'bg-light text-muted border' ?>" style="width: 36px; height: 36px;" id="stepNodeCooking">
                  <i class="bi bi-fire"></i>
                </div>
                <div class="small fw-bold mt-2 d-none d-sm-block">Cooking</div>
              </div>

              <!-- Step 3: Out for Delivery -->
              <div class="text-center">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm <?= $currentStep >= 2 ? 'bg-success text-white' : 'bg-light text-muted border' ?>" style="width: 36px; height: 36px;" id="stepNodeTransit">
                  <i class="bi bi-bicycle"></i>
                </div>
                <div class="small fw-bold mt-2 d-none d-sm-block">Out for Delivery</div>
              </div>

              <!-- Step 4: Delivered -->
              <div class="text-center">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm <?= $currentStep >= 3 ? 'bg-success text-white' : 'bg-light text-muted border' ?>" style="width: 36px; height: 36px;" id="stepNodeDelivered">
                  <i class="bi bi-house-check"></i>
                </div>
                <div class="small fw-bold mt-2 d-none d-sm-block">Delivered</div>
              </div>

            </div>
          </div>

          <!-- Secret Delivery OTP Notice -->
          <div id="otpContainer">
            <?php if ($isDelivered): ?>
            <div class="p-3 rounded-4 mt-4 text-start d-flex align-items-center justify-content-between bg-light border border-success">
              <div>
                <strong class="text-success d-block"><i class="bi bi-patch-check-fill me-1"></i>Delivery Completed & Verified</strong>
                <span class="small text-muted">Doorstep OTP was successfully verified by rider</span>
              </div>
              <div class="fs-5 fw-bold font-monospace text-muted px-3 py-1 bg-white rounded border text-decoration-line-through">
                <?= e($order['delivery_otp'] ?: '482194') ?>
              </div>
            </div>
            <?php else: ?>
            <div class="p-3 rounded-4 mt-4 text-start d-flex align-items-center justify-content-between" style="background: #EDFBF2; border: 1px dashed #2E6F40;">
              <div>
                <strong class="text-forest d-block"><i class="bi bi-shield-check me-1"></i>Delivery Handshake OTP:</strong>
                <span class="small text-muted">Provide this code to your rider at doorstep</span>
              </div>
              <div class="fs-3 fw-bold font-monospace text-dark px-3 py-1 bg-white rounded border">
                <?= e($order['delivery_otp'] ?: '482194') ?>
              </div>
            </div>
            <?php endif; ?>
          </div>

        </div>

        <!-- Live Interactive GPS Delivery Map Card -->
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4" id="liveMapCard">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="d-flex align-items-center gap-2">
              <div class="rounded-circle d-flex align-items-center justify-content-center text-white shadow-xs" style="width: 40px; height: 40px; background: #2E6F40;">
                <i class="bi bi-geo-alt-fill fs-5"></i>
              </div>
              <div>
                <h5 class="fw-bold text-dark mb-0 font-outfit">Live Delivery Route & GPS Tracking</h5>
                <div class="small text-muted" id="mapLiveStatusText">
                  <?php if ($order['order_status'] === 'OUT_FOR_DELIVERY'): ?>
                    <span class="pulse-dot me-1"></span> Rider is in transit on Indiranagar 100ft corridor
                  <?php elseif ($order['order_status'] === 'COOKING'): ?>
                    <i class="bi bi-fire text-warning me-1"></i> Food being prepared at Zippy Kitchen Hub
                  <?php elseif ($order['order_status'] === 'READY'): ?>
                    <i class="bi bi-bag-check-fill text-primary me-1"></i> Food bagged at counter, assigning courier
                  <?php elseif ($order['order_status'] === 'DELIVERED'): ?>
                    <i class="bi bi-check-circle-fill text-success me-1"></i> Food delivered at your doorstep
                  <?php else: ?>
                    <i class="bi bi-clock me-1"></i> Order queued at restaurant kitchen
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-light text-dark border px-3 py-2 font-monospace shadow-xs" id="liveTelemetryBadge">
                <i class="bi bi-speedometer2 text-success me-1"></i>
                <span id="telemetryDist"><?= $isDelivered ? '0.0 km' : (number_format((float)($order['distance_remaining_km'] ?: 2.40), 1) . ' km') ?></span> •
                <span id="telemetryEta"><?= $isDelivered ? 'Arrived' : (($order['estimated_minutes_remaining'] ?: 12) . 'm away') ?></span>
              </span>
              <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 fw-bold" id="btnCenterMap" title="Center on Rider">
                <i class="bi bi-crosshair"></i> Focus
              </button>
            </div>
          </div>

          <!-- Leaflet Interactive Map Container -->
          <div id="customerLiveMap" style="height: 310px; border-radius: 14px; width: 100%; border: 1px solid #d8e5dc; position: relative; overflow: hidden; background: #f8faf9;">
            <div id="mapFallbackContainer" class="d-none h-100 d-flex flex-column align-items-center justify-content-center text-center p-3 text-muted">
              <i class="bi bi-map fs-1 text-success mb-2"></i>
              <div class="fw-bold text-dark">Live GPS Map Simulator Active</div>
              <div class="small">Zippy Kitchen Hub (Indiranagar) &rarr; <?= e($order['address_line'] ?: 'Customer Address') ?></div>
            </div>
          </div>

          <!-- Route Highlights Bar -->
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-3 mt-3 border-top small">
            <div class="d-flex align-items-center gap-3 text-muted">
              <span><i class="fa-solid fa-store text-success me-1"></i> <strong>Kitchen:</strong> Indiranagar Hub</span>
              <span><i class="fa-solid fa-motorcycle text-primary me-1"></i> <strong>Rider:</strong> <?= e($order['delivery_partner_name'] ?: 'Express Rider') ?></span>
              <span><i class="bi bi-house-door-fill text-danger me-1"></i> <strong>Destination:</strong> <?= e($order['pincode'] ?: '560038') ?></span>
            </div>
            <div class="text-success fw-semibold">
              <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-2 py-1">
                <i class="bi bi-shield-check me-1"></i> GPS Safe Transit
              </span>
            </div>
          </div>
        </div>

        <!-- Assigned Delivery Partner Card -->
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
          <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
              <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center fs-4" style="width: 55px; height: 55px; background: #2E6F40 !important;">
                <i class="bi bi-person-fill"></i>
              </div>
              <div>
                <h5 class="fw-bold mb-0 text-dark"><?= e($order['delivery_partner_name'] ?: 'Suresh Kumar (Zippy Express)') ?></h5>
                <div class="small text-muted"><i class="bi bi-star-fill text-warning me-1"></i>4.9 rating | 1,200+ deliveries</div>
                <div class="small text-success"><i class="bi bi-check-circle-fill me-1"></i>Vaccinated & Temperature Checked</div>
              </div>
            </div>

            <a href="tel:<?= e($order['delivery_partner_phone'] ?: '+919632098765') ?>" class="btn btn-outline-success rounded-pill px-3 py-2 fw-bold">
              <i class="bi bi-telephone-fill me-1"></i> Call Rider
            </a>
          </div>
        </div>

      </div>

      <!-- Right Column: Order Items & Delivery Location -->
      <div class="col-lg-4">
        
        <!-- Delivery Destination -->
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
          <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-geo-alt-fill text-danger me-2"></i>Delivery Address</h6>
          <div class="small fw-semibold text-dark mb-1"><?= e($order['full_name']) ?></div>
          <div class="small text-muted mb-2"><?= e($order['address_line'] ?: 'Indiranagar 100ft Road, Bangalore') ?></div>
          <div class="small text-muted">PIN: <?= e($order['pincode'] ?: '560038') ?></div>
        </div>

        <!-- Order Items Breakdown -->
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
          <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-receipt text-success me-2"></i>Order Summary</h6>
          
          <div class="d-flex flex-column gap-2 mb-3">
            <?php foreach ($items as $item): ?>
              <div class="d-flex justify-content-between align-items-start small pb-2 border-bottom">
                <div>
                  <div class="d-flex align-items-center">
                    <span class="badge <?= $item['is_veg'] ? 'bg-success' : 'bg-danger' ?> px-1 me-1" style="font-size:0.6rem;"><?= (int)$item['quantity'] ?>x</span>
                    <span class="fw-semibold text-dark"><?= e($item['item_name']) ?></span>
                  </div>
                  <?php if (!empty($item['customizations'])): ?>
                    <div class="ps-3 mt-1">
                      <?= formatCustomizationsHtml($item['customizations']) ?>
                    </div>
                  <?php endif; ?>
                </div>
                <span class="fw-bold text-dark"><?= formatInr($item['line_total']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="border-top pt-2">
            <div class="d-flex justify-content-between small text-muted mb-1">
              <span>Subtotal</span>
              <span><?= formatInr($order['subtotal']) ?></span>
            </div>
            <div class="d-flex justify-content-between small text-muted mb-1">
              <span>GST & Delivery</span>
              <span><?= formatInr($order['tax_amount'] + $order['delivery_fee']) ?></span>
            </div>
            <?php if ($order['discount_amount'] > 0): ?>
              <div class="d-flex justify-content-between small text-success mb-1">
                <span>Discount</span>
                <span>-<?= formatInr($order['discount_amount']) ?></span>
              </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between fw-bold fs-6 text-dark border-top pt-2 mt-2">
              <span>Total Paid</span>
              <span class="text-forest"><?= formatInr($order['total_amount']) ?></span>
            </div>
            <div class="d-flex justify-content-between small text-muted mt-2 pt-1 border-top">
              <span>Payment Mode:</span>
              <span class="fw-semibold text-dark"><?= e($order['payment_mode']) ?></span>
            </div>
            <div class="d-flex justify-content-between small text-muted">
              <span>Payment Status:</span>
              <span class="badge <?= ($order['payment_status'] ?? '') === 'SUCCESSFUL' ? 'bg-success' : 'bg-warning text-dark' ?>">
                <?= e($order['payment_status'] ?: 'PENDING') ?>
              </span>
            </div>
            <?php if (!empty($order['transaction_reference'])): ?>
              <div class="small text-muted text-truncate mt-1" style="font-size:0.75rem;" title="<?= e($order['transaction_reference']) ?>">
                Ref: <code class="text-dark"><?= e($order['transaction_reference']) ?></code>
              </div>
            <?php endif; ?>
          </div>

          <div class="mt-4 d-grid gap-2">
            <?php if ($isDelivered): ?>
              <button type="button" class="btn btn-success btn-sm rounded-pill py-2 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" style="background:#2E6F40; border-color:#2E6F40;" onclick="oneTapReorderMeal(<?= $statusReorderJson ?>, <?= (int)$order['order_id'] ?>, false, this)">
                <i class="fa-solid fa-rotate-right"></i> 1-Tap Reorder This Meal
              </button>
            <?php endif; ?>
            <a href="<?= CUSTOMER_URL ?>/invoice.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-outline-success btn-sm rounded-pill py-2 fw-semibold" target="_blank">
              <i class="bi bi-file-earmark-text me-1"></i> Download Tax Invoice
            </a>
            <a href="<?= CUSTOMER_URL ?>/rate-order.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-light btn-sm rounded-pill py-2 border text-muted">
              <i class="bi bi-star me-1 text-warning"></i> Rate Food & Courier
            </a>
          </div>
        </div>

      </div>

  </div>
</main>

<!-- Automatic Rating Prompt Modal (Sequence Diagram 4.4-B) -->
<div class="modal fade" id="autoRatingPromptModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
    <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
      
      <!-- Modal Header Banner -->
      <div class="p-3 text-center text-white" style="background: linear-gradient(135deg, #1C3322 0%, #2E6F40 100%);">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-20 p-2 mb-2" style="width: 52px; height: 52px;">
          <i class="bi bi-star-fill fs-3 text-warning"></i>
        </div>
        <h5 class="fw-bold mb-0">Food Delivered! How Was Your Meal?</h5>
        <p class="small text-white-50 mb-0">Order #ZIP-<?= (int)$order['order_id'] ?> &bull; Tell us how we did</p>
      </div>

      <!-- Rating Form Body -->
      <div class="p-4" id="modalRatingFormBody">
        <form id="quickRatingForm" onsubmit="event.preventDefault(); submitQuickRatingModal();">
          <input type="hidden" name="action" value="submit_rating">
          <input type="hidden" name="is_ajax" value="1">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
          <input type="hidden" name="food_rating" id="modalFoodRating" value="5">
          <input type="hidden" name="rider_rating" id="modalRiderRating" value="5">
          <input type="hidden" name="food_tags" id="modalFoodTags" value="">

          <!-- 1. Food Taste & Quality Stars -->
          <div class="text-center mb-3 p-3 rounded-3" style="background:#F7FAF8; border:1px solid #E2ECE5;">
            <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size:0.75rem;">1. Rate Food Taste &amp; Freshness</div>
            <div class="d-flex justify-content-center gap-2 text-warning fs-2" id="modalFoodStarContainer" style="cursor:pointer;">
              <i class="bi bi-star-fill modal-star" data-type="food" data-val="1"></i>
              <i class="bi bi-star-fill modal-star" data-type="food" data-val="2"></i>
              <i class="bi bi-star-fill modal-star" data-type="food" data-val="3"></i>
              <i class="bi bi-star-fill modal-star" data-type="food" data-val="4"></i>
              <i class="bi bi-star-fill modal-star" data-type="food" data-val="5"></i>
            </div>
            <div class="small fw-bold text-success mt-1" id="modalFoodRatingLabel">Outstanding &amp; Delicious! 🌟</div>
          </div>

          <!-- 2. Delivery Partner Rating -->
          <div class="text-center mb-3 p-2 rounded-3 bg-light border">
            <div class="d-flex justify-content-between align-items-center px-2">
              <div class="text-start">
                <div class="small fw-bold text-dark mb-0">Delivery Courier</div>
                <div class="text-muted" style="font-size:0.72rem;"><?= e($order['delivery_partner_name'] ?: 'Zippy Express Rider') ?></div>
              </div>
              <div class="d-flex gap-1 text-warning fs-5" id="modalRiderStarContainer" style="cursor:pointer;">
                <i class="bi bi-star-fill modal-rider-star" data-type="rider" data-val="1"></i>
                <i class="bi bi-star-fill modal-rider-star" data-type="rider" data-val="2"></i>
                <i class="bi bi-star-fill modal-rider-star" data-type="rider" data-val="3"></i>
                <i class="bi bi-star-fill modal-rider-star" data-type="rider" data-val="4"></i>
                <i class="bi bi-star-fill modal-rider-star" data-type="rider" data-val="5"></i>
              </div>
            </div>
          </div>

          <!-- 3. Compliment Tags -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-muted text-uppercase mb-1" style="font-size:0.72rem;">What did you like?</label>
            <div class="d-flex flex-wrap gap-1" id="modalComplimentChips">
              <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill py-0 px-2 small modal-chip" data-tag="♨️ Piping Hot" onclick="toggleModalChip(this, '♨️ Piping Hot')">♨️ Piping Hot</button>
              <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill py-0 px-2 small modal-chip" data-tag="⚡ Fast Delivery" onclick="toggleModalChip(this, '⚡ Fast Delivery')">⚡ Fast Delivery</button>
              <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill py-0 px-2 small modal-chip" data-tag="📦 Neat Packaging" onclick="toggleModalChip(this, '📦 Neat Packaging')">📦 Neat Packaging</button>
              <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill py-0 px-2 small modal-chip" data-tag="🌶️ Great Taste" onclick="toggleModalChip(this, '🌶️ Great Taste')">🌶️ Great Taste</button>
              <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill py-0 px-2 small modal-chip" data-tag="😊 Polite Courier" onclick="toggleModalChip(this, '😊 Polite Courier')">😊 Polite Courier</button>
            </div>
          </div>

          <!-- 4. Comments Input -->
          <div class="mb-3">
            <textarea name="comments" id="modalReviewComments" class="form-control rounded-3 small" rows="2" placeholder="Write a short compliment or review (optional)..."></textarea>
          </div>

          <!-- Submit Button -->
          <button type="submit" class="btn btn-success w-100 rounded-pill py-2 fw-bold shadow-sm" id="btnSubmitModalReview" style="background:#2E6F40; border-color:#2E6F40;">
            <i class="bi bi-star-fill me-1 text-warning"></i> Submit Review &amp; Rating
          </button>

          <div class="d-flex justify-content-between align-items-center mt-2 px-1">
            <a href="<?= CUSTOMER_URL ?>/rate-order.php?order_id=<?= (int)$order['order_id'] ?>" class="small text-success fw-semibold text-decoration-none">
              <i class="bi bi-pencil-square me-1"></i>Detailed Review &amp; Tip Rider &rarr;
            </a>
            <button type="button" class="btn btn-sm btn-link text-muted text-decoration-none small" data-bs-dismiss="modal">
              Later
            </button>
          </div>
        </form>
      </div>

      <!-- Success Thank You View (Hidden until submitted) -->
      <div class="p-4 text-center d-none" id="modalRatingSuccessBody">
        <div class="rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm mx-auto mb-3" style="width:70px; height:70px; background:#CEFFDC; color:#2E6F40;">
          <i class="bi bi-check-circle-fill fs-1 text-success"></i>
        </div>
        <h4 class="fw-bold font-outfit text-dark mb-1">Thank You For Your Review! ⭐</h4>
        <p class="text-muted small mb-3">Your rating has been recorded with 5-star precision. Your feedback helps our kitchen chefs and riders maintain top culinary standards.</p>
        <button type="button" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;" data-bs-dismiss="modal">
          Done
        </button>
      </div>

    </div>
  </div>
</div>

<script>
// Mark tracking active so generic app.js simulation does not conflict or run
window.orderTrackingActive = true;

// Gentle Web Audio API status transition chime (zero external dependencies)
function playStatusChime() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
    osc.frequency.exponentialRampToValueAtTime(880.00, ctx.currentTime + 0.15); // A5
    gain.gain.setValueAtTime(0.08, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.35);
  } catch(e) {}
}

(function() {
  const orderId = <?= (int)$order['order_id'] ?>;
  let orderStatus = <?= jsJson(strtoupper($order['order_status'])) ?>;
  let secondsLeft = <?= (int)$remainingSeconds ?>;
  window.__setSecondsLeft = function(s) { secondsLeft = s; };
  const countdownElem = document.getElementById('live-eta-countdown');
  const countdownLabel = document.getElementById('countdownLabel');
  const countdownSubtext = document.getElementById('countdownSubtext');
  const scheduleBadge = document.getElementById('scheduleBadge');
  const statusHeading = document.getElementById('statusHeading');
  const statusSubtext = document.getElementById('statusSubtext');
  const stepperBar = document.getElementById('stepperProgressBar');
  const stepNodes = {
    placed: document.getElementById('stepNodePlaced'),
    cooking: document.getElementById('stepNodeCooking'),
    transit: document.getElementById('stepNodeTransit'),
    delivered: document.getElementById('stepNodeDelivered')
  };
  const otpContainer = document.getElementById('otpContainer');
  const topBadgeContainer = document.getElementById('topBadgeContainer');
  const mapLiveStatusText = document.getElementById('mapLiveStatusText');
  const telemetryDist = document.getElementById('telemetryDist');
  const telemetryEta = document.getElementById('telemetryEta');

  let timerInterval = null;
  let pollInterval = null;

  // ---------------------------------------------------------------------------
  // Leaflet Live Geolocation Map Initialization
  // ---------------------------------------------------------------------------
  let liveMap = null;
  let riderMarker = null;
  let routePolyline = null;
  const hubCoords = [12.9784, 77.6408];
  const customerCoords = [<?= (float)($order['delivery_latitude'] ?: 12.9698) ?>, <?= (float)($order['delivery_longitude'] ?: 77.6499) ?>];

  function initCustomerMap() {
    const mapEl = document.getElementById('customerLiveMap');
    if (!mapEl || typeof L === 'undefined') {
      const fb = document.getElementById('mapFallbackContainer');
      if (fb) fb.classList.remove('d-none');
      return;
    }

    try {
      liveMap = L.map('customerLiveMap', {
        zoomControl: true,
        scrollWheelZoom: false
      }).setView(hubCoords, 14);

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(liveMap);

      // Restaurant Marker
      const storeIcon = L.divIcon({
        className: 'custom-hub-marker',
        html: '<div style="background:#2E6F40; color:#fff; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 3px 8px rgba(0,0,0,0.3); border:2px solid #fff;"><i class="fa-solid fa-store" style="font-size:14px;"></i></div>',
        iconSize: [32, 32],
        iconAnchor: [16, 16]
      });
      L.marker(hubCoords, { icon: storeIcon }).addTo(liveMap)
        .bindPopup('<strong>Zippy Kitchen Hub</strong><br>#544, 100 Feet Rd, Indiranagar');

      // Customer Marker
      const homeIcon = L.divIcon({
        className: 'custom-home-marker',
        html: '<div style="background:#dc3545; color:#fff; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 3px 8px rgba(0,0,0,0.3); border:2px solid #fff;"><i class="bi bi-house-door-fill" style="font-size:15px;"></i></div>',
        iconSize: [32, 32],
        iconAnchor: [16, 16]
      });
      L.marker(customerCoords, { icon: homeIcon }).addTo(liveMap)
        .bindPopup('<strong>Delivery Destination</strong><br>' + <?= jsJson(e($order['address_line'] ?: 'Customer Address')) ?>);

      // Route Path
      const defaultRoute = [
        hubCoords,
        [12.9765, 77.6415],
        [12.9748, 77.6425],
        [12.9732, 77.6442],
        [12.9715, 77.6468],
        customerCoords
      ];
      routePolyline = L.polyline(defaultRoute, {
        color: '#2E6F40',
        weight: 4,
        opacity: 0.8,
        dashArray: '8, 8'
      }).addTo(liveMap);

      // Rider Marker
      const riderIcon = L.divIcon({
        className: 'custom-rider-marker',
        html: '<div class="rider-bike-icon"><i class="fa-solid fa-motorcycle"></i></div>',
        iconSize: [34, 34],
        iconAnchor: [17, 17]
      });

      const initialRiderPos = (orderStatus === 'DELIVERED') ? customerCoords : hubCoords;
      const escapedStatusText = String(orderStatus).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
      riderMarker = L.marker(initialRiderPos, { icon: riderIcon }).addTo(liveMap)
        .bindPopup('<strong>' + <?= jsJson(e($order['delivery_partner_name'] ?: 'Zippy Express Rider')) ?> + '</strong><br>Status: ' + escapedStatusText);

      // Fit map bounds to encompass restaurant and customer
      const bounds = L.latLngBounds([hubCoords, customerCoords]);
      liveMap.fitBounds(bounds, { padding: [40, 40] });

      const btnCenter = document.getElementById('btnCenterMap');
      if (btnCenter) {
        btnCenter.addEventListener('click', () => {
          if (riderMarker) {
            liveMap.setView(riderMarker.getLatLng(), 15, { animate: true });
          } else {
            liveMap.fitBounds(bounds, { padding: [40, 40] });
          }
        });
      }
    } catch(err) {
      console.warn('Leaflet initialization warning:', err);
      const fb = document.getElementById('mapFallbackContainer');
      if (fb) fb.classList.remove('d-none');
    }
  }

  // Initialize Map after DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCustomerMap);
  } else {
    initCustomerMap();
  }

  function updateLiveMapTelemetry(locData) {
    if (!locData || !locData.success) return;

    if (telemetryDist && locData.route) {
      telemetryDist.textContent = (locData.route.distance_remaining_km !== undefined) ? `${locData.route.distance_remaining_km} km` : '2.1 km';
    }
    if (telemetryEta && locData.route) {
      telemetryEta.textContent = (locData.route.eta_minutes !== undefined) ? `${locData.route.eta_minutes}m away` : '10m away';
    }

    if (locData.locations && locData.locations.rider && riderMarker && liveMap) {
      const rLat = locData.locations.rider.lat;
      const rLng = locData.locations.rider.lng;
      if (rLat && rLng) {
        riderMarker.setLatLng([rLat, rLng]);
      }
    }

    if (mapLiveStatusText) {
      if (locData.status === 'OUT_FOR_DELIVERY') {
        mapLiveStatusText.innerHTML = '<span class="pulse-dot me-1"></span> Rider is in transit • Moving along Indiranagar corridor';
      } else if (locData.status === 'COOKING') {
        mapLiveStatusText.innerHTML = '<i class="bi bi-fire text-warning me-1"></i> Food being prepared at Zippy Kitchen Hub';
      } else if (locData.status === 'READY') {
        mapLiveStatusText.innerHTML = '<i class="bi bi-bag-check-fill text-primary me-1"></i> Food bagged at counter, assigning courier';
      } else if (locData.status === 'DELIVERED') {
        mapLiveStatusText.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i> Food delivered at your doorstep';
      }
    }
  }

  function setStepActive(stepNum) {
    if (stepperBar) stepperBar.style.width = `${(stepNum / 3) * 100}%`;
    const nodes = [stepNodes.placed, stepNodes.cooking, stepNodes.transit, stepNodes.delivered];
    nodes.forEach((node, idx) => {
      if (!node) return;
      if (idx <= stepNum) {
        node.className = 'rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm bg-success text-white';
      } else {
        node.className = 'rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm bg-light text-muted border';
      }
    });
  }

  function handleDelivered(deliveryOtp) {
    if (timerInterval) {
      clearInterval(timerInterval);
      timerInterval = null;
    }
    if (pollInterval) {
      clearInterval(pollInterval);
      pollInterval = null;
    }
    orderStatus = 'DELIVERED';

    if (countdownElem) {
      countdownElem.textContent = 'DELIVERED';
      countdownElem.setAttribute('data-status', 'DELIVERED');
    }
    if (countdownLabel) countdownLabel.textContent = 'ORDER STATUS';
    if (countdownSubtext) countdownSubtext.textContent = 'ORDER COMPLETED';

    if (scheduleBadge) {
      scheduleBadge.className = 'badge rounded-pill px-3 py-1 mb-1 bg-success text-white';
      scheduleBadge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> DELIVERED';
    }
    if (statusHeading) {
      statusHeading.textContent = 'Order Delivered Successfully!';
    }
    if (statusSubtext) {
      statusSubtext.innerHTML = '<i class="bi bi-patch-check-fill text-success me-1"></i>Delivered on time • Doorstep handover completed';
    }

    setStepActive(3);

    if (topBadgeContainer) {
      topBadgeContainer.innerHTML = '<span class="badge bg-success px-3 py-2 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>Delivered</span>';
    }

    if (telemetryDist) telemetryDist.textContent = '0.0 km';
    if (telemetryEta) telemetryEta.textContent = 'Arrived';
    if (mapLiveStatusText) {
      mapLiveStatusText.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i> Handover completed at customer doorstep';
    }
    if (riderMarker && customerCoords) {
      riderMarker.setLatLng(customerCoords);
    }

    if (otpContainer) {
      otpContainer.innerHTML = `
        <div class="p-3 rounded-4 mt-4 text-start d-flex align-items-center justify-content-between bg-light border border-success">
          <div>
            <strong class="text-success d-block"><i class="bi bi-patch-check-fill me-1"></i>Delivery Completed & Verified</strong>
            <span class="small text-muted">Doorstep OTP was successfully verified by rider</span>
          </div>
          <div class="fs-5 fw-bold font-monospace text-muted px-3 py-1 bg-white rounded border text-decoration-line-through">
            ${deliveryOtp || '<?= e($order['delivery_otp'] ?: '482194') ?>'}
          </div>
        </div>
      `;
    }

    playStatusChime();

    // Prompt automatic rating modal (Sequence Diagram 4.4-B Step 15)
    setTimeout(() => {
      const modalEl = document.getElementById('autoRatingPromptModal');
      if (modalEl && typeof bootstrap !== 'undefined') {
        const rateModal = new bootstrap.Modal(modalEl);
        rateModal.show();
      }
    }, 1200);
  }

  // If already delivered or cancelled, do not start countdown timer or polling
  if (orderStatus === 'DELIVERED') {
    handleDelivered('<?= e($order['delivery_otp'] ?: '482194') ?>');
    return;
  }
  if (orderStatus === 'CANCELLED') {
    if (countdownElem) {
      countdownElem.textContent = 'CANCELLED';
      countdownElem.setAttribute('data-status', 'CANCELLED');
    }
    if (countdownLabel) countdownLabel.textContent = 'ORDER STATUS';
    if (countdownSubtext) countdownSubtext.textContent = 'CLOSED';
    return;
  }

  // Active order: run countdown timer until delivery
  timerInterval = setInterval(() => {
    if (orderStatus === 'DELIVERED' || orderStatus === 'CANCELLED') {
      clearInterval(timerInterval);
      return;
    }
    if (secondsLeft <= 0) {
      if (countdownElem) countdownElem.textContent = '00:00';
      if (countdownSubtext) countdownSubtext.textContent = 'ARRIVING NOW';
      return;
    }
    secondsLeft--;
    const mins = Math.floor(secondsLeft / 60);
    const secs = secondsLeft % 60;
    if (countdownElem) {
      countdownElem.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }
  }, 1000);

  // Poll for status & live location updates
  pollInterval = setInterval(async () => {
    try {
      // 1. Fetch live coordinates and routing status
      const locRes = await fetch(`../api/live-location.php?portal=customer&order_id=${encodeURIComponent(orderId)}`);
      if (locRes.ok) {
        const data = await locRes.json();
        if (data.success && data.status) {
          const newStatus = data.status.toUpperCase();
          updateLiveMapTelemetry(data);

          if (newStatus === 'DELIVERED') {
            handleDelivered(data.delivery_otp);
          } else if (newStatus === 'OUT_FOR_DELIVERY' && orderStatus !== 'OUT_FOR_DELIVERY') {
            orderStatus = newStatus;
            playStatusChime();
            if (statusHeading) statusHeading.textContent = 'Rider is on the way to your door';
            if (statusSubtext) statusSubtext.textContent = 'Live GPS transit active • Indiranagar delivery corridor';
            setStepActive(2);
            if (topBadgeContainer && data.badge_html) topBadgeContainer.innerHTML = data.badge_html;
          } else if (newStatus === 'READY' && orderStatus !== 'READY') {
            orderStatus = newStatus;
            playStatusChime();
            if (statusHeading) statusHeading.textContent = 'Order Plated & Packed!';
            if (statusSubtext) statusSubtext.textContent = 'Packed in thermal bag at kitchen • Awaiting courier departure';
            setStepActive(2);
            if (topBadgeContainer && data.badge_html) topBadgeContainer.innerHTML = data.badge_html;
          } else if (newStatus === 'COOKING' && orderStatus !== 'COOKING') {
            orderStatus = newStatus;
            playStatusChime();
            if (statusHeading) statusHeading.textContent = 'Chef is cooking your hot meal';
            if (statusSubtext) statusSubtext.textContent = 'Active preparation on stoves at Zippy Kitchen Hub';
            setStepActive(1);
            if (topBadgeContainer && data.badge_html) topBadgeContainer.innerHTML = data.badge_html;
          }
        }
      }
    } catch (e) {
      // silent network catch
    }
  }, 3000);

  // Live 60-Second Cancellation Countdown Timer
  let cancelSecs = <?= (int)($cancellationSecondsLeft ?? 0) ?>;
  if (cancelSecs > 0) {
    const cancelTimerInterval = setInterval(() => {
      cancelSecs--;
      const el = document.getElementById('cancelSecondsRemaining');
      if (el) el.textContent = cancelSecs + 's';
      if (cancelSecs <= 0) {
        clearInterval(cancelTimerInterval);
        const banner = document.getElementById('cancellationBanner');
        if (banner) {
          banner.className = 'alert alert-light border rounded-4 p-3 mb-4 text-muted small';
          banner.innerHTML = '<i class="bi bi-info-circle me-1"></i> 60-second cancellation window closed. Order is locked and being prepared in the kitchen.';
        }
      }
    }, 1000);
  }

  // Automatic Rating Prompt on Page Load if already DELIVERED and not yet reviewed
  <?php if ($isDelivered && !$hasReviewed): ?>
  window.addEventListener('load', () => {
    setTimeout(() => {
      const modalEl = document.getElementById('autoRatingPromptModal');
      if (modalEl && typeof bootstrap !== 'undefined') {
        const rateModal = new bootstrap.Modal(modalEl);
        rateModal.show();
      }
    }, 1500);
  });
  <?php endif; ?>
})();

// Interactive Quick Rating Modal Controller
let selectedModalTags = [];
const foodRatingLabels = {
  1: 'Poor / Needs Attention 😞',
  2: 'Below Average 😐',
  3: 'Good Taste 👍',
  4: 'Very Good & Fresh 😊',
  5: 'Outstanding & Delicious! 🌟'
};

document.addEventListener('DOMContentLoaded', () => {
  // Food star click handlers
  document.querySelectorAll('.modal-star').forEach(star => {
    star.addEventListener('click', function() {
      const val = parseInt(this.getAttribute('data-val'));
      document.getElementById('modalFoodRating').value = val;
      document.querySelectorAll('.modal-star').forEach(s => {
        const sVal = parseInt(s.getAttribute('data-val'));
        if (sVal <= val) {
          s.className = 'bi bi-star-fill text-warning modal-star';
        } else {
          s.className = 'bi bi-star text-muted opacity-50 modal-star';
        }
      });
      const lbl = document.getElementById('modalFoodRatingLabel');
      if (lbl) lbl.textContent = foodRatingLabels[val] || 'Outstanding! 🌟';
    });
  });

  // Rider star click handlers
  document.querySelectorAll('.modal-rider-star').forEach(star => {
    star.addEventListener('click', function() {
      const val = parseInt(this.getAttribute('data-val'));
      document.getElementById('modalRiderRating').value = val;
      document.querySelectorAll('.modal-rider-star').forEach(s => {
        const sVal = parseInt(s.getAttribute('data-val'));
        if (sVal <= val) {
          s.className = 'bi bi-star-fill text-warning modal-rider-star';
        } else {
          s.className = 'bi bi-star text-muted opacity-50 modal-rider-star';
        }
      });
    });
  });
});

function toggleModalChip(btn, tag) {
  const idx = selectedModalTags.indexOf(tag);
  if (idx > -1) {
    selectedModalTags.splice(idx, 1);
    btn.className = 'btn btn-outline-secondary btn-sm rounded-pill py-0 px-2 small modal-chip';
  } else {
    selectedModalTags.push(tag);
    btn.className = 'btn btn-success text-white btn-sm rounded-pill py-0 px-2 small modal-chip shadow-xs';
  }
  const input = document.getElementById('modalFoodTags');
  if (input) input.value = selectedModalTags.join(', ');
}

async function submitQuickRatingModal() {
  const form = document.getElementById('quickRatingForm');
  const btn = document.getElementById('btnSubmitModalReview');
  if (!form || !btn) return;

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Recording Review...';

  try {
    const formData = new FormData(form);
    const res = await fetch('<?= CUSTOMER_URL ?>/rate-order.php?order_id=<?= (int)$order['order_id'] ?>', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: formData
    });
    const data = await res.json();
    if (data.success) {
      document.getElementById('modalRatingFormBody').classList.add('d-none');
      document.getElementById('modalRatingSuccessBody').classList.remove('d-none');
    } else {
      alert(data.message || 'Failed to submit review.');
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-star-fill me-1 text-warning"></i> Submit Review & Rating';
    }
  } catch (err) {
    console.error('Rating submit error:', err);
    // Fallback: regular submit
    form.submit();
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
