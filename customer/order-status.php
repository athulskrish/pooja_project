<?php
/**
 * Live Order Progress Tracker & Courier Dispatch
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Live Order Status Tracker';
$activePage = 'orders';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
$currentUser = current_user();

// Handle Customer Order Cancellation (within 60s of placement - Statechart 4.1 & UC-09)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid or expired session token.');
    } else {
        $cancelOrderId = (int)($_POST['order_id'] ?? 0);
        $orderToCancel = db_fetch_one("SELECT * FROM `orders` WHERE `order_id` = :id", [':id' => $cancelOrderId]);

        if ($orderToCancel) {
            $placedTs = strtotime($orderToCancel['placed_at']);
            $elapsedSec = time() - $placedTs;

            if ($elapsedSec <= 60 && in_array($orderToCancel['order_status'], ['PLACED', 'CONFIRMED'])) {
                $pdo = getDBConnection();
                if ($pdo) {
                    try {
                        $pdo->beginTransaction();

                        // 1. Mark status as CANCELLED
                        $pdo->prepare("UPDATE `orders` SET `order_status` = 'CANCELLED' WHERE `order_id` = :id")->execute([':id' => $cancelOrderId]);

                        // 2. Restore inventory portions
                        $pdo->prepare("
                            UPDATE `menu_items` m
                            JOIN `order_items` oi ON m.item_id = oi.item_id
                            SET m.stock_quantity = m.stock_quantity + oi.quantity,
                                m.is_available = 1
                            WHERE oi.order_id = :id
                        ")->execute([':id' => $cancelOrderId]);

                        // 3. Mark payment as REFUNDED
                        $pdo->prepare("UPDATE `payments` SET `payment_status` = 'REFUNDED', `payment_timestamp` = NOW() WHERE `order_id` = :id")->execute([':id' => $cancelOrderId]);

                        // 4. Audit trail entry
                        $pdo->prepare("
                            INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`)
                            VALUES (:oid, :old, 'CANCELLED', :uid, NOW())
                        ")->execute([
                            ':oid' => $cancelOrderId,
                            ':old' => $orderToCancel['order_status'],
                            ':uid' => $currentUser['id'] ?? $orderToCancel['user_id']
                        ]);

                        $pdo->commit();
                        set_flash('success', "Order #ZIP-{$cancelOrderId} cancelled within 60s. 100% refund has been credited to your payment method and inventory restored.");
                        header("Location: " . CUSTOMER_URL . "/order-status.php?order_id=" . $cancelOrderId);
                        exit;
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        set_flash('danger', 'Cancellation error: ' . $e->getMessage());
                    }
                }
            } else {
                if ($elapsedSec > 60) {
                    set_flash('danger', 'Cancellation window expired: orders can only be cancelled within 60s of placement. The kitchen has already started cooking.');
                } else {
                    set_flash('danger', 'Order cannot be cancelled as kitchen preparation has already begun.');
                }
                header("Location: " . CUSTOMER_URL . "/order-status.php?order_id=" . $cancelOrderId);
                exit;
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';

$uid = $currentUser['id'] ?? null;
if (!isset($_GET['order_id'])) {
    if ($uid) {
        // Look for the user's latest in-progress order first
        $activeOrder = db_fetch_one("SELECT order_id FROM `orders` WHERE `user_id` = :uid AND `order_status` IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY') ORDER BY `order_id` DESC LIMIT 1", [':uid' => $uid]);
        if ($activeOrder) {
            $orderId = (int)$activeOrder['order_id'];
        } else {
            $latestUserOrder = db_fetch_one("SELECT order_id FROM `orders` WHERE `user_id` = :uid ORDER BY `order_id` DESC LIMIT 1", [':uid' => $uid]);
            $orderId = $latestUserOrder ? (int)$latestUserOrder['order_id'] : 1004;
        }
    } else {
        $latestAny = db_fetch_one("SELECT order_id FROM `orders` ORDER BY `order_id` DESC LIMIT 1");
        $orderId = $latestAny ? (int)$latestAny['order_id'] : 1004;
    }
} else {
    $orderId = (int)$_GET['order_id'];
}

$order = db_fetch_one("
    SELECT o.*, u.full_name, u.phone_number,
           a.address_line, a.pincode,
           p.payment_mode, p.payment_status, p.transaction_reference,
           d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp, d.delivery_status
    FROM `orders` o
    JOIN `users` u ON o.user_id = u.user_id
    LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    WHERE o.order_id = :id
", [':id' => $orderId]);

if (!$order) {
    // Fallback to latest order
    $order = db_fetch_one("SELECT * FROM `orders` ORDER BY `order_id` DESC LIMIT 1");
    if (!$order) {
        echo "<div class='container py-5 text-center'><h4>No active orders found</h4><a href='" . CUSTOMER_URL . "/index.php' class='btn btn-success rounded-pill mt-3'>Browse Menu</a></div>";
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }
}

// Check if user has other active in-progress orders
$otherActiveOrders = [];
if ($uid) {
    $otherActiveOrders = db_fetch_all("
        SELECT order_id, order_status 
        FROM `orders` 
        WHERE `user_id` = :uid AND `order_status` IN ('CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY') AND `order_id` <> :curId
        ORDER BY `order_id` DESC
    ", [':uid' => $uid, ':curId' => $order['order_id']]);
}

// Fetch line items
$items = db_fetch_all("
    SELECT oi.*, m.item_name, m.is_veg 
    FROM `order_items` oi
    JOIN `menu_items` m ON oi.item_id = m.item_id
    WHERE oi.order_id = :id
", [':id' => $order['order_id']]);

// Map statuses to step index (0 to 3)
$statusMap = [
    'PLACED' => 0,
    'CONFIRMED' => 1,
    'COOKING' => 1,
    'READY' => 2,
    'OUT_FOR_DELIVERY' => 2,
    'DELIVERED' => 3
];
$currentStep = $statusMap[strtoupper($order['order_status'])] ?? 1;

$isDelivered = (strtoupper($order['order_status']) === 'DELIVERED');
$isCancelled = (strtoupper($order['order_status']) === 'CANCELLED');

// Calculate initial ETA countdown
$etaTimestamp = !empty($order['estimated_delivery_time']) ? strtotime($order['estimated_delivery_time']) : (strtotime($order['placed_at']) + 25 * 60);
$currentTimestamp = time();
$remainingSeconds = max(0, $etaTimestamp - $currentTimestamp);
if ($remainingSeconds <= 0 && !$isDelivered && !$isCancelled) {
    $remainingSeconds = 15 * 60; // 15 mins fallback for active order
}
$initMins = floor($remainingSeconds / 60);
$initSecs = $remainingSeconds % 60;
$initialCountdownText = sprintf('%02d:%02d', $initMins, $initSecs);
$existingReview = db_fetch_one("SELECT review_id FROM `order_reviews` WHERE `order_id` = :id", [':id' => $order['order_id']]);
$hasReviewed = !empty($existingReview);

$placedTs = strtotime($order['placed_at']);
$cancellationSecondsLeft = max(0, 60 - (time() - $placedTs));
$canCancel = ($cancellationSecondsLeft > 0 && in_array(strtoupper($order['order_status']), ['PLACED', 'CONFIRMED']));
?>

<main class="py-4">
  <div class="container-xl">
    
    <?php if ($isCancelled): ?>
      <div class="alert alert-danger rounded-4 p-3 mb-4 d-flex align-items-center gap-3 border-danger shadow-xs">
        <i class="bi bi-x-octagon-fill fs-2 text-danger"></i>
        <div>
          <h5 class="fw-bold mb-1 text-danger">Order Cancelled & 100% Refund Issued</h5>
          <p class="small mb-0 text-dark">This order was cancelled within the permissible 60-second window. A full refund of <strong><?= format_inr($order['total_amount']) ?></strong> has been initiated (Status: <strong>REFUNDED</strong>) and all ordered items have been restored to restaurant inventory.</p>
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
          <?= csrf_field() ?>
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
        <?= get_status_badge($order['order_status']) ?>
      </div>
    </div>

    <div class="row g-4">
      
      <!-- Left Column: Status Stepper & Courier Card -->
      <div class="col-lg-8">
        
        <!-- Live Countdown & Stepper Card -->
        <div class="card border-0 rounded-4 shadow-sm p-4 p-md-5 bg-white mb-4">
          
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
                <div class="text-muted small" id="statusSubtext">Estimated Delivery: <strong><?= date('h:i A', strtotime($order['estimated_delivery_time'] ?: '+25 minutes')) ?></strong></div>
              <?php endif; ?>
            </div>

            <!-- Countdown Timer Box -->
            <div class="text-center p-3 rounded-4 shadow-sm" id="countdownBox" style="background: #243E2C; color: #fff; min-width: 140px;">
              <div class="small text-white-50 text-uppercase fw-semibold" id="countdownLabel" style="font-size: 0.65rem; letter-spacing: 0.5px;">
                <?= ($isDelivered || $isCancelled) ? 'ORDER STATUS' : 'ESTIMATED TIME' ?>
              </div>
              <div class="fs-2 fw-bold font-monospace text-mint" id="live-eta-countdown" data-status="<?= e($order['order_status']) ?>" data-no-simulation="true">
                <?php if ($isDelivered): ?>
                  DELIVERED
                <?php elseif ($isCancelled): ?>
                  CANCELLED
                <?php else: ?>
                  <?= $initialCountdownText ?>
                <?php endif; ?>
              </div>
              <div class="small text-white-50" id="countdownSubtext" style="font-size: 0.7rem;">
                <?php if ($isDelivered): ?>
                  ORDER COMPLETED
                <?php elseif ($isCancelled): ?>
                  CLOSED
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
              <div class="d-flex justify-content-between align-items-center small">
                <div>
                  <span class="badge <?= $item['is_veg'] ? 'bg-success' : 'bg-danger' ?> px-1 me-1" style="font-size:0.6rem;"><?= (int)$item['quantity'] ?>x</span>
                  <span><?= e($item['item_name']) ?></span>
                </div>
                <span class="fw-bold"><?= format_inr($item['line_total']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="border-top pt-2">
            <div class="d-flex justify-content-between small text-muted mb-1">
              <span>Subtotal</span>
              <span><?= format_inr($order['subtotal']) ?></span>
            </div>
            <div class="d-flex justify-content-between small text-muted mb-1">
              <span>GST & Delivery</span>
              <span><?= format_inr($order['tax_amount'] + $order['delivery_fee']) ?></span>
            </div>
            <?php if ($order['discount_amount'] > 0): ?>
              <div class="d-flex justify-content-between small text-success mb-1">
                <span>Discount</span>
                <span>-<?= format_inr($order['discount_amount']) ?></span>
              </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between fw-bold fs-6 text-dark border-top pt-2 mt-2">
              <span>Total Paid</span>
              <span class="text-forest"><?= format_inr($order['total_amount']) ?></span>
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
<div class="modal fade" id="autoRatingPromptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
    <div class="modal-content border-0 rounded-4 shadow-lg text-center p-4">
      <div class="rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width:70px; height:70px; background:#CEFFDC; color:#2E6F40;">
        <i class="bi bi-star-fill fs-2"></i>
      </div>
      <h4 class="fw-bold font-outfit text-dark mb-1">Order Delivered!</h4>
      <p class="text-muted small mb-3">Your meal for Order #ZIP-<?= (int)$order['order_id'] ?> has arrived safely. Please take 10 seconds to rate food quality and courier service.</p>
      <div class="d-flex justify-content-center gap-2 mb-4 text-warning fs-3">
        <i class="bi bi-star-fill"></i>
        <i class="bi bi-star-fill"></i>
        <i class="bi bi-star-fill"></i>
        <i class="bi bi-star-fill"></i>
        <i class="bi bi-star-fill"></i>
      </div>
      <div class="d-flex justify-content-center gap-2">
        <button type="button" class="btn btn-light rounded-pill px-4 text-muted" data-bs-dismiss="modal">Later</button>
        <a href="<?= CUSTOMER_URL ?>/rate-order.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
          Rate Meal & Tip &rarr;
        </a>
      </div>
    </div>
  </div>
</div>

<script>
// Mark tracking active so generic app.js simulation does not conflict or run
window.orderTrackingActive = true;

(function() {
  const orderId = <?= (int)$order['order_id'] ?>;
  let orderStatus = '<?= addslashes(strtoupper($order['order_status'])) ?>';
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

  let timerInterval = null;
  let pollInterval = null;

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

  // Poll for status updates
  pollInterval = setInterval(async () => {
    try {
      const res = await fetch(`ajax/order-status-api.php?order_id=${orderId}`);
      if (!res.ok) return;
      const data = await res.json();
      if (data.success && data.status) {
        const newStatus = data.status.toUpperCase();
        if (newStatus === 'DELIVERED') {
          handleDelivered(data.delivery_otp);
        } else if (newStatus === 'OUT_FOR_DELIVERY' && orderStatus !== 'OUT_FOR_DELIVERY') {
          orderStatus = newStatus;
          if (statusHeading) statusHeading.textContent = 'Rider is on the way to your door';
          setStepActive(2);
          if (topBadgeContainer && data.badge_html) topBadgeContainer.innerHTML = data.badge_html;
        } else if (newStatus === 'COOKING' && orderStatus !== 'COOKING') {
          orderStatus = newStatus;
          if (statusHeading) statusHeading.textContent = 'Chef is cooking your hot meal';
          setStepActive(1);
          if (topBadgeContainer && data.badge_html) topBadgeContainer.innerHTML = data.badge_html;
        }
      }
    } catch (e) {
      // silent network catch
    }
})();

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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
