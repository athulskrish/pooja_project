<?php
/**
 * Customer Order History & Tax Invoices
 * Zippy - Online Food Ordering Platform
 * Design: Figma "Lush Forest" Palette (#Combination-4)
 * Features:
 *   - Categorized Tabs: All Orders, Ongoing, Completed, Cancelled
 *   - Dynamic Badge Counts & Status Indicators
 *   - Real-time Client Search & Filter by Dish, Order #, Status, Rider
 *   - 1-Click "Reorder" directly to cart
 *   - 60-Second Order Cancellation Window with Instant Refund
 *   - GST Tax Invoice & Live Order Tracking integrations
 */

$pageTitle = 'Order History & Receipts';
$activePage = 'orders';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$currentUser = current_user();
$userId = $currentUser ? (int)$currentUser['id'] : 2; // Default to demo customer Athul if guest

// -----------------------------------------------------------------------------
// Handle Customer Order Cancellation (within 60s of placement)
// -----------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid or expired session token.');
        header("Location: " . CUSTOMER_URL . "/order-history.php");
        exit;
    }

    $cancelOrderId = (int)($_POST['order_id'] ?? 0);
    $orderToCancel = db_fetch_one("SELECT * FROM `orders` WHERE `order_id` = :id AND `user_id` = :uid", [
        ':id' => $cancelOrderId,
        ':uid' => $userId
    ]);

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
                        ':uid' => $userId
                    ]);

                    $pdo->commit();
                    set_flash('success', "Order #ZIP-{$cancelOrderId} cancelled successfully. 100% refund has been processed to your original payment mode.");
                    header("Location: " . CUSTOMER_URL . "/order-history.php?tab=cancelled");
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
            header("Location: " . CUSTOMER_URL . "/order-history.php?tab=ongoing");
            exit;
        }
    } else {
        set_flash('danger', 'Order not found or unauthorized.');
        header("Location: " . CUSTOMER_URL . "/order-history.php");
        exit;
    }
}

// -----------------------------------------------------------------------------
// Fetch Orders with Joined Details
// -----------------------------------------------------------------------------
$orders = db_fetch_all("
    SELECT o.*, 
           COUNT(oi.order_item_id) AS total_items_count,
           d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp, d.delivery_status, d.delivered_at,
           p.payment_mode, p.payment_status, p.transaction_reference, p.amount_paid,
           ua.address_type, ua.address_line, ua.landmark, ua.pincode,
           r.food_rating, r.rider_rating, r.comments AS review_comments
    FROM `orders` o
    LEFT JOIN `order_items` oi ON o.order_id = oi.order_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    LEFT JOIN `user_addresses` ua ON o.address_id = ua.address_id
    LEFT JOIN `order_reviews` r ON o.order_id = r.order_id
    WHERE o.user_id = :uid
    GROUP BY o.order_id, d.dispatch_id, p.payment_id, ua.address_id, r.review_id
    ORDER BY o.placed_at DESC
", [':uid' => $userId]);

// Fetch item details for these orders
$orderItemsGrouped = [];
if (!empty($orders)) {
    $orderIds = array_column($orders, 'order_id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemsRaw = db_fetch_all("
        SELECT oi.*, m.item_name, m.is_veg, m.price AS current_price, m.image_url
        FROM `order_items` oi
        JOIN `menu_items` m ON oi.item_id = m.item_id
        WHERE oi.order_id IN ($placeholders)
        ORDER BY oi.order_item_id ASC
    ", $orderIds);

    foreach ($itemsRaw as $item) {
        $orderItemsGrouped[$item['order_id']][] = $item;
    }
}

// -----------------------------------------------------------------------------
// Categorize Orders into Tabs
// -----------------------------------------------------------------------------
$ongoingStatuses = ['PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY'];
$completedStatuses = ['DELIVERED'];
$cancelledStatuses = ['CANCELLED'];

$allOrders = $orders;
$ongoingOrders = array_values(array_filter($orders, fn($o) => in_array($o['order_status'], $ongoingStatuses)));
$completedOrders = array_values(array_filter($orders, fn($o) => in_array($o['order_status'], $completedStatuses)));
$cancelledOrders = array_values(array_filter($orders, fn($o) => in_array($o['order_status'], $cancelledStatuses)));

// Calculate stats
$totalOrdersCount = count($allOrders);
$ongoingCount = count($ongoingOrders);
$completedCount = count($completedOrders);
$cancelledCount = count($cancelledOrders);

$totalDeliveredSpend = array_reduce($completedOrders, fn($sum, $o) => $sum + (float)$o['total_amount'], 0.0);

// Determine active tab from URL query
$validTabs = ['all', 'ongoing', 'completed', 'cancelled'];
$activeTab = in_array(strtolower($_GET['tab'] ?? ''), $validTabs) ? strtolower($_GET['tab']) : 'all';

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* Order History Specialized Styles */
.custom-order-pills {
  background: #ffffff;
  border: 1px solid var(--lush-border);
  padding: 6px;
  border-radius: var(--radius-pill);
}

.custom-order-pills .nav-link {
  color: var(--lush-pine);
  border-radius: var(--radius-pill);
  font-weight: 600;
  font-size: 0.92rem;
  padding: 10px 22px;
  transition: all 0.25s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: 1px solid transparent;
}

.custom-order-pills .nav-link:hover {
  background: var(--lush-mint-soft);
  color: var(--lush-forest);
}

.custom-order-pills .nav-link.active {
  background: var(--lush-forest) !important;
  color: #ffffff !important;
  box-shadow: 0 4px 14px rgba(46, 111, 64, 0.28);
}

.custom-order-pills .nav-link .badge {
  font-size: 0.75rem;
  font-weight: 700;
  transition: all 0.25s ease;
}

.order-stat-card {
  background: #ffffff;
  border: 1px solid var(--lush-border);
  border-radius: 16px;
  padding: 16px 20px;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.order-stat-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.stat-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}

.pulsing-beacon {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #28a745;
  box-shadow: 0 0 0 rgba(40, 167, 69, 0.4);
  animation: pulse-beacon 1.5s infinite;
}

@keyframes pulse-beacon {
  0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.6); }
  70% { box-shadow: 0 0 0 8px rgba(40, 167, 69, 0); }
  100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
}

.veg-box {
  width: 15px;
  height: 15px;
  border: 1.5px solid #229947;
  border-radius: 3px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.veg-box .dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #229947;
}

.nonveg-box {
  width: 15px;
  height: 15px;
  border: 1.5px solid #D32F2F;
  border-radius: 3px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.nonveg-box .triangle {
  width: 0;
  height: 0;
  border-left: 4px solid transparent;
  border-right: 4px solid transparent;
  border-bottom: 7px solid #D32F2F;
}

.order-card {
  border: 1px solid var(--lush-border);
  border-radius: 20px;
  background: #ffffff;
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.order-card:hover {
  box-shadow: 0 8px 24px rgba(36, 62, 44, 0.08);
  border-color: #c9ded0;
}

.order-card.is-ongoing {
  border-left: 5px solid #E5A100;
}

.order-card.is-delivered {
  border-left: 5px solid var(--lush-forest);
}

.order-card.is-cancelled {
  border-left: 5px solid #dc3545;
}

.order-item-row:not(:last-child) {
  border-bottom: 1px dashed #eef4f0;
  padding-bottom: 8px;
  margin-bottom: 8px;
}
</style>

<main class="py-4">
  <div class="container-xl">
    
    <!-- Header Title & Action -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
      <div>
        <nav aria-label="breadcrumb" class="mb-1">
          <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="<?= CUSTOMER_URL ?>/index.php" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item active text-forest fw-semibold" aria-current="page">Order History</li>
          </ol>
        </nav>
        <h2 class="fw-bold mb-0 font-outfit text-dark d-flex align-items-center gap-2">
          <i class="bi bi-clock-history text-forest"></i> Customer Order History
        </h2>
        <p class="text-muted small mb-0">Showing orders and receipts for <strong><?= e($currentUser ? $currentUser['name'] : 'Athul') ?></strong></p>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a href="<?= CUSTOMER_URL ?>/index.php#dishes" class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm d-inline-flex align-items-center gap-2" style="background:#2E6F40; border-color:#2E6F40;">
          <i class="bi bi-plus-circle"></i> Browse Menu
        </a>
      </div>
    </div>

    <!-- Quick Stats Cards Row -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="order-stat-card d-flex align-items-center gap-3">
          <div class="stat-icon-box bg-mint text-forest">
            <i class="bi bi-receipt-cutoff"></i>
          </div>
          <div>
            <div class="small text-muted fw-semibold">Total Orders</div>
            <div class="fs-4 fw-bold text-dark font-outfit"><?= $totalOrdersCount ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="order-stat-card d-flex align-items-center gap-3">
          <div class="stat-icon-box bg-warning bg-opacity-25 text-warning-emphasis">
            <i class="bi bi-bicycle"></i>
          </div>
          <div>
            <div class="small text-muted fw-semibold">Ongoing</div>
            <div class="fs-4 fw-bold text-warning font-outfit d-flex align-items-center gap-2">
              <?= $ongoingCount ?>
              <?php if ($ongoingCount > 0): ?>
                <span class="pulsing-beacon"></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="order-stat-card d-flex align-items-center gap-3">
          <div class="stat-icon-box bg-success bg-opacity-25 text-success">
            <i class="bi bi-bag-check-fill"></i>
          </div>
          <div>
            <div class="small text-muted fw-semibold">Completed</div>
            <div class="fs-4 fw-bold text-success font-outfit"><?= $completedCount ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="order-stat-card d-flex align-items-center gap-3">
          <div class="stat-icon-box bg-danger bg-opacity-10 text-danger">
            <i class="bi bi-x-circle"></i>
          </div>
          <div>
            <div class="small text-muted fw-semibold">Cancelled</div>
            <div class="fs-4 fw-bold text-danger font-outfit"><?= $cancelledCount ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="row align-items-center mb-4 g-2">
      <div class="col-md-8 col-lg-9">
        <div class="position-relative">
          <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
          <input type="text" id="orderSearchInput" class="form-control rounded-pill ps-5 pe-5 py-2 border-0 shadow-sm" placeholder="Search by Order #ZIP, dish name, status, or delivery partner...">
          <button type="button" id="clearSearchBtn" class="btn btn-sm position-absolute top-50 end-0 translate-middle-y me-2 text-muted border-0 bg-transparent d-none" title="Clear Search">
            <i class="bi bi-x-circle-fill"></i>
          </button>
        </div>
      </div>
      <div class="col-md-4 col-lg-3 text-md-end">
        <div class="text-muted small">
          <span id="visibleOrdersCount"><?= $totalOrdersCount ?></span> orders displayed
        </div>
      </div>
    </div>

    <!-- Categorized Tabs Navigation -->
    <div class="mb-4">
      <ul class="nav nav-pills custom-order-pills d-flex flex-wrap gap-1" id="orderHistoryTabs" role="tablist">
        
        <!-- Tab 1: All Orders -->
        <li class="nav-item flex-fill text-center" role="presentation">
          <button class="nav-link w-100 <?= $activeTab === 'all' ? 'active' : '' ?>" id="tab-all-btn" data-bs-toggle="pill" data-bs-target="#tab-all-pane" type="button" role="tab" aria-controls="tab-all-pane" aria-selected="<?= $activeTab === 'all' ? 'true' : 'false' ?>" data-tab-name="all">
            <i class="bi bi-collection-fill"></i>
            <span>All Orders</span>
            <span class="badge rounded-pill ms-1 <?= $activeTab === 'all' ? 'bg-light text-dark' : 'bg-secondary bg-opacity-25 text-dark' ?>" id="badge-count-all"><?= $totalOrdersCount ?></span>
          </button>
        </li>

        <!-- Tab 2: Ongoing Orders -->
        <li class="nav-item flex-fill text-center" role="presentation">
          <button class="nav-link w-100 <?= $activeTab === 'ongoing' ? 'active' : '' ?>" id="tab-ongoing-btn" data-bs-toggle="pill" data-bs-target="#tab-ongoing-pane" type="button" role="tab" aria-controls="tab-ongoing-pane" aria-selected="<?= $activeTab === 'ongoing' ? 'true' : 'false' ?>" data-tab-name="ongoing">
            <i class="bi bi-bicycle"></i>
            <span>Ongoing</span>
            <span class="badge rounded-pill ms-1 bg-warning text-dark" id="badge-count-ongoing"><?= $ongoingCount ?></span>
          </button>
        </li>

        <!-- Tab 3: Completed Orders -->
        <li class="nav-item flex-fill text-center" role="presentation">
          <button class="nav-link w-100 <?= $activeTab === 'completed' ? 'active' : '' ?>" id="tab-completed-btn" data-bs-toggle="pill" data-bs-target="#tab-completed-pane" type="button" role="tab" aria-controls="tab-completed-pane" aria-selected="<?= $activeTab === 'completed' ? 'true' : 'false' ?>" data-tab-name="completed">
            <i class="bi bi-check2-circle"></i>
            <span>Completed</span>
            <span class="badge rounded-pill ms-1 bg-success text-white" id="badge-count-completed"><?= $completedCount ?></span>
          </button>
        </li>

        <!-- Tab 4: Cancelled Orders -->
        <li class="nav-item flex-fill text-center" role="presentation">
          <button class="nav-link w-100 <?= $activeTab === 'cancelled' ? 'active' : '' ?>" id="tab-cancelled-btn" data-bs-toggle="pill" data-bs-target="#tab-cancelled-pane" type="button" role="tab" aria-controls="tab-cancelled-pane" aria-selected="<?= $activeTab === 'cancelled' ? 'true' : 'false' ?>" data-tab-name="cancelled">
            <i class="bi bi-x-circle"></i>
            <span>Cancelled</span>
            <span class="badge rounded-pill ms-1 bg-danger text-white" id="badge-count-cancelled"><?= $cancelledCount ?></span>
          </button>
        </li>

      </ul>
    </div>

    <!-- =======================================================================
         HELPER RENDERING FUNCTIONS
         ======================================================================= -->
    <?php
    /**
     * Render an individual order card with full details and contextual actions
     */
    function render_order_card(array $order, array $items): void {
        $status = strtoupper($order['order_status']);
        $isOngoing = in_array($status, ['PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY']);
        $isDelivered = ($status === 'DELIVERED');
        $isCancelled = ($status === 'CANCELLED');

        // Check 60-second cancellation window
        $placedTs = strtotime($order['placed_at']);
        $elapsedSec = time() - $placedTs;
        $canCancel = ($elapsedSec <= 60 && in_array($status, ['PLACED', 'CONFIRMED']));
        $remainingSec = max(0, 60 - $elapsedSec);

        // Card border classification
        $borderClass = $isOngoing ? 'is-ongoing' : ($isDelivered ? 'is-delivered' : 'is-cancelled');

        // Build search string for client-side filtering
        $itemNames = array_map(fn($it) => $it['item_name'], $items);
        $searchCorpus = strtolower(
            "ZIP-" . $order['order_id'] . " " .
            $order['order_status'] . " " .
            implode(' ', $itemNames) . " " .
            ($order['delivery_partner_name'] ?? '') . " " .
            ($order['order_notes'] ?? '')
        );

        // JSON payload for 1-click Reorder feature
        $reorderPayload = array_map(function($it) {
            return [
                'id' => (int)$it['item_id'],
                'name' => $it['item_name'],
                'price' => (float)$it['unit_price'],
                'isVeg' => (bool)$it['is_veg'],
                'restaurant' => 'Meghana Foods',
                'qty' => (int)$it['quantity']
            ];
        }, $items);
        $reorderJson = htmlspecialchars(json_encode($reorderPayload), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="col-12 order-card-wrapper" data-search="<?= e($searchCorpus) ?>" data-status="<?= e(strtolower($status)) ?>">
          <div class="order-card p-4 shadow-sm mb-4 <?= $borderClass ?>">
            
            <!-- Card Header: Order ID, Date, Status -->
            <div class="d-flex flex-wrap align-items-center justify-content-between border-bottom pb-3 mb-3 gap-2">
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="fw-bold fs-5 text-dark font-outfit">Order #ZIP-<?= (int)$order['order_id'] ?></span>
                <span class="text-muted small">
                  <i class="bi bi-calendar3 me-1"></i><?= date('d M Y, h:i A', strtotime($order['placed_at'])) ?>
                </span>
                
                <?php if ($isOngoing): ?>
                  <span class="badge bg-mint text-forest fw-semibold px-2 py-1 rounded-pill small d-inline-flex align-items-center gap-1">
                    <span class="pulsing-beacon"></span> Active Order
                  </span>
                <?php endif; ?>

                <?php if ($canCancel): ?>
                  <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle rounded-pill px-2 py-1 small cancel-timer-badge" data-seconds="<?= $remainingSec ?>" data-order-id="<?= (int)$order['order_id'] ?>">
                    <i class="bi bi-stopwatch me-1"></i> <span class="timer-countdown"><?= $remainingSec ?>s</span> left to cancel
                  </span>
                <?php endif; ?>
              </div>

              <div class="d-flex align-items-center gap-2">
                <?= get_status_badge($order['order_status']) ?>
                <span class="fs-5 fw-bold text-forest font-outfit"><?= format_inr($order['total_amount']) ?></span>
              </div>
            </div>

            <!-- Card Body: Grid of Items, Details, Actions -->
            <div class="row g-4 align-items-start">
              
              <!-- Column 1: Ordered Items -->
              <div class="col-lg-5 col-md-6">
                <div class="small fw-bold text-muted text-uppercase mb-2" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                  <i class="bi bi-basket2 me-1 text-success"></i> Ordered Dishes (<?= count($items) ?>):
                </div>

                <div class="d-flex flex-column">
                  <?php foreach ($items as $item): ?>
                    <div class="order-item-row d-flex align-items-center gap-2 small">
                      <div class="<?= $item['is_veg'] ? 'veg-box' : 'nonveg-box' ?>" title="<?= $item['is_veg'] ? 'Vegetarian' : 'Non-Vegetarian' ?>">
                        <?php if ($item['is_veg']): ?>
                          <div class="dot"></div>
                        <?php else: ?>
                          <div class="triangle"></div>
                        <?php endif; ?>
                      </div>
                      <span class="badge bg-light text-dark border px-2 py-1 fw-bold"><?= (int)$item['quantity'] ?>x</span>
                      <span class="text-dark fw-semibold text-truncate" style="max-width: 220px;"><?= e($item['item_name']) ?></span>
                      <span class="text-muted ms-auto fw-medium"><?= format_inr($item['line_total']) ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>

                <?php if (!empty($order['order_notes'])): ?>
                  <div class="mt-2 p-2 rounded-3 bg-light border border-light-subtle small text-muted">
                    <i class="bi bi-chat-left-dots text-forest me-1"></i>
                    <strong>Cooking Note:</strong> <?= e($order['order_notes']) ?>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Column 2: Delivery & Payment Details -->
              <div class="col-lg-4 col-md-6 border-start-lg ps-lg-4">
                
                <!-- Delivery Location -->
                <div class="small fw-bold text-muted text-uppercase mb-2" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                  <i class="bi bi-geo-alt me-1 text-success"></i> Delivery Address:
                </div>
                <div class="small mb-3">
                  <span class="badge bg-light text-dark border mb-1"><?= e($order['address_type'] ?? 'HOME') ?></span>
                  <div class="text-dark fw-medium text-truncate">
                    <?= e($order['address_line'] ?? 'Indiranagar') ?>
                    <?= !empty($order['landmark']) ? ', ' . e($order['landmark']) : '' ?>
                  </div>
                  <div class="text-muted">Bangalore - <?= e($order['pincode'] ?? '560038') ?></div>
                </div>

                <!-- Payment Details -->
                <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                  <i class="bi bi-credit-card me-1 text-success"></i> Payment Summary:
                </div>
                <div class="small d-flex flex-wrap align-items-center gap-1 mb-2">
                  <span class="badge bg-light text-dark border"><?= e($order['payment_mode'] ?? 'COD') ?></span>
                  <?php if (($order['payment_status'] ?? '') === 'SUCCESSFUL'): ?>
                    <span class="badge bg-success bg-opacity-25 text-success">Paid</span>
                  <?php elseif (($order['payment_status'] ?? '') === 'REFUNDED'): ?>
                    <span class="badge bg-danger bg-opacity-25 text-danger"><i class="bi bi-arrow-counterclockwise"></i> 100% Refunded</span>
                  <?php else: ?>
                    <span class="badge bg-warning bg-opacity-25 text-dark"><?= e($order['payment_status'] ?? 'Pending') ?></span>
                  <?php endif; ?>
                </div>

                <!-- Rider & OTP (For Ongoing) -->
                <?php if ($isOngoing && !empty($order['delivery_otp'])): ?>
                  <div class="p-2 rounded-3 bg-mint-soft border border-mint d-flex align-items-center justify-content-between mt-2">
                    <div>
                      <span class="small text-muted d-block" style="font-size: 0.72rem;">Share Delivery OTP:</span>
                      <strong class="font-monospace fs-5 text-dark"><?= e($order['delivery_otp']) ?></strong>
                    </div>
                    <div>
                      <i class="bi bi-shield-check text-forest fs-4"></i>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if (!empty($order['delivery_partner_name']) && $order['delivery_partner_name'] !== 'Zippy Express Rider'): ?>
                  <div class="small text-muted mt-2">
                    <i class="bi bi-person-badge text-forest me-1"></i> Rider: <strong><?= e($order['delivery_partner_name']) ?></strong>
                    <?php if (!empty($order['delivery_partner_phone'])): ?>
                      <a href="tel:<?= e($order['delivery_partner_phone']) ?>" class="text-decoration-none text-forest ms-1">
                        <i class="bi bi-telephone-fill"></i>
                      </a>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <!-- Customer Review Display (For Completed) -->
                <?php if ($isDelivered && !empty($order['food_rating'])): ?>
                  <div class="p-2 rounded-3 bg-light border mt-2 small">
                    <div class="text-warning fw-bold">
                      <?php for ($s = 1; $s <= 5; $s++): ?>
                        <i class="bi bi-star<?= $s <= (int)$order['food_rating'] ? '-fill' : '' ?>"></i>
                      <?php endfor; ?>
                      <span class="text-dark ms-1"><?= (int)$order['food_rating'] ?>.0</span>
                    </div>
                    <?php if (!empty($order['review_comments'])): ?>
                      <div class="text-muted fst-italic mt-1 text-truncate">"<?= e($order['review_comments']) ?>"</div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <!-- Cancellation Reason (For Cancelled) -->
                <?php if ($isCancelled): ?>
                  <div class="p-2 rounded-3 bg-danger bg-opacity-10 border border-danger-subtle text-danger small mt-2">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    Order cancelled. Refund of <?= format_inr($order['total_amount']) ?> initiated to original source.
                  </div>
                <?php endif; ?>

              </div>

              <!-- Column 3: Smart Action Buttons -->
              <div class="col-lg-3 text-lg-end border-start-lg ps-lg-4">
                <div class="d-flex flex-column gap-2">
                  
                  <!-- Ongoing Actions -->
                  <?php if ($isOngoing): ?>
                    <a href="<?= CUSTOMER_URL ?>/order-status.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-success btn-sm rounded-pill fw-semibold py-2 shadow-sm d-flex align-items-center justify-content-center gap-2" style="background:#2E6F40; border-color:#2E6F40;">
                      <i class="bi bi-geo-alt-fill"></i> Live Tracker
                    </a>

                    <?php if ($canCancel): ?>
                      <button type="button" class="btn btn-outline-danger btn-sm rounded-pill py-2 fw-semibold cancel-order-trigger-btn" data-order-id="<?= (int)$order['order_id'] ?>" data-bs-toggle="modal" data-bs-target="#cancelConfirmModal">
                        <i class="bi bi-x-circle me-1"></i> Cancel Order
                      </button>
                    <?php endif; ?>

                    <a href="<?= CUSTOMER_URL ?>/invoice.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-light btn-sm rounded-pill border py-2 text-muted d-flex align-items-center justify-content-center gap-1" target="_blank">
                      <i class="bi bi-file-earmark-text text-forest"></i> Tax Invoice
                    </a>

                    <a href="<?= CUSTOMER_URL ?>/help.php?order_id=%23ZIP-<?= (int)$order['order_id'] ?>" class="btn btn-light btn-sm rounded-pill border py-2 text-muted d-flex align-items-center justify-content-center gap-1">
                      <i class="bi bi-question-circle text-primary"></i> Need Help?
                    </a>
                  <?php endif; ?>

                  <!-- Completed Actions -->
                  <?php if ($isDelivered): ?>
                    <button type="button" class="btn btn-success btn-sm rounded-pill fw-semibold py-2 shadow-sm d-flex align-items-center justify-content-center gap-2" style="background:#2E6F40; border-color:#2E6F40;" onclick="reorderItems(<?= $reorderJson ?>, <?= (int)$order['order_id'] ?>)">
                      <i class="bi bi-arrow-repeat"></i> Reorder Food
                    </button>

                    <?php if (empty($order['food_rating'])): ?>
                      <a href="<?= CUSTOMER_URL ?>/rate-order.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-warning btn-sm rounded-pill py-2 text-dark fw-bold d-flex align-items-center justify-content-center gap-1 shadow-sm" style="background:#FFC107; border-color:#FFC107;">
                        <i class="bi bi-star-fill text-dark"></i> Rate Food ⭐
                      </a>
                    <?php else: ?>
                      <div class="p-2 rounded-3 bg-light border text-center">
                        <div class="small fw-bold text-muted mb-1" style="font-size:0.7rem;">YOUR FOOD RATING</div>
                        <div class="d-flex align-items-center justify-content-center gap-1 text-warning mb-1 fs-6">
                          <?= str_repeat('<i class="bi bi-star-fill"></i>', (int)$order['food_rating']) . str_repeat('<i class="bi bi-star text-muted opacity-50"></i>', 5 - (int)$order['food_rating']) ?>
                          <span class="text-dark fw-bold ms-1" style="font-size:0.75rem;"><?= (int)$order['food_rating'] ?>/5</span>
                        </div>
                        <a href="<?= CUSTOMER_URL ?>/rate-order.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-sm btn-link p-0 text-success text-decoration-none fw-semibold" style="font-size:0.72rem;">
                          <i class="bi bi-pencil-square me-1"></i>Edit Review ⭐
                        </a>
                      </div>
                    <?php endif; ?>

                    <a href="<?= CUSTOMER_URL ?>/invoice.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-light btn-sm rounded-pill border py-2 text-muted d-flex align-items-center justify-content-center gap-1" target="_blank">
                      <i class="bi bi-receipt text-forest"></i> Tax Invoice
                    </a>

                    <a href="<?= CUSTOMER_URL ?>/help.php?order_id=%23ZIP-<?= (int)$order['order_id'] ?>" class="btn btn-light btn-sm rounded-pill border py-2 text-muted d-flex align-items-center justify-content-center gap-1">
                      <i class="bi bi-chat-left-heart text-success"></i> Feedback & Support
                    </a>
                  <?php endif; ?>

                  <!-- Cancelled Actions -->
                  <?php if ($isCancelled): ?>
                    <button type="button" class="btn btn-outline-success btn-sm rounded-pill fw-semibold py-2 d-flex align-items-center justify-content-center gap-2" onclick="reorderItems(<?= $reorderJson ?>, <?= (int)$order['order_id'] ?>)">
                      <i class="bi bi-arrow-repeat"></i> Order Again
                    </button>

                    <a href="<?= CUSTOMER_URL ?>/invoice.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-light btn-sm rounded-pill border py-2 text-muted d-flex align-items-center justify-content-center gap-1" target="_blank">
                      <i class="bi bi-file-earmark-text text-secondary"></i> Cancellation Receipt
                    </a>

                    <a href="<?= CUSTOMER_URL ?>/help.php?order_id=%23ZIP-<?= (int)$order['order_id'] ?>" class="btn btn-light btn-sm rounded-pill border py-2 text-muted d-flex align-items-center justify-content-center gap-1">
                      <i class="bi bi-headset text-danger"></i> Refund Support
                    </a>
                  <?php endif; ?>

                </div>
              </div>

            </div>

          </div>
        </div>
        <?php
    }

    /**
     * Render tailored empty state for a tab
     */
    function render_empty_state(string $tabType): void {
        $states = [
            'all' => [
                'icon' => 'bi-receipt',
                'title' => 'No orders placed yet',
                'desc' => 'Explore our delicious biryanis, curries, and rolls to place your first feast.',
                'btnText' => 'Browse Dishes',
                'btnLink' => CUSTOMER_URL . '/index.php#dishes'
            ],
            'ongoing' => [
                'icon' => 'bi-bicycle',
                'title' => 'No ongoing orders right now',
                'desc' => 'You do not have any active meals cooking in the kitchen or out for delivery. Hungry?',
                'btnText' => 'Order Food Now',
                'btnLink' => CUSTOMER_URL . '/index.php#dishes'
            ],
            'completed' => [
                'icon' => 'bi-check2-circle',
                'title' => 'No completed orders yet',
                'desc' => 'Your delivered feasts, GST tax receipts, and customer feedback will be preserved here.',
                'btnText' => 'Explore Menu',
                'btnLink' => CUSTOMER_URL . '/index.php#dishes'
            ],
            'cancelled' => [
                'icon' => 'bi-shield-check',
                'title' => 'No cancelled orders',
                'desc' => 'Great news! You have a pristine record with zero cancellations.',
                'btnText' => 'Order Food',
                'btnLink' => CUSTOMER_URL . '/index.php#dishes'
            ]
        ];

        $info = $states[$tabType] ?? $states['all'];
        ?>
        <div class="card border-0 rounded-4 shadow-sm p-5 text-center bg-white my-3">
          <div class="mb-3">
            <i class="bi <?= $info['icon'] ?> text-muted opacity-50" style="font-size: 3.5rem;"></i>
          </div>
          <h5 class="fw-bold text-dark font-outfit"><?= e($info['title']) ?></h5>
          <p class="text-muted small mb-4" style="max-width: 420px; margin: 0 auto;"><?= e($info['desc']) ?></p>
          <div>
            <a href="<?= e($info['btnLink']) ?>" class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
              <?= e($info['btnText']) ?>
            </a>
          </div>
        </div>
        <?php
    }
    ?>

    <!-- =======================================================================
         TABS PANES CONTENT
         ======================================================================= -->
    <div class="tab-content" id="orderHistoryTabsContent">
      
      <!-- TAB 1: ALL ORDERS -->
      <div class="tab-pane fade <?= $activeTab === 'all' ? 'show active' : '' ?>" id="tab-all-pane" role="tabpanel" aria-labelledby="tab-all-btn" tabindex="0">
        <?php if (empty($allOrders)): ?>
          <?php render_empty_state('all'); ?>
        <?php else: ?>
          <div class="row g-0 orders-list-container">
            <?php foreach ($allOrders as $order): ?>
              <?php render_order_card($order, $orderItemsGrouped[$order['order_id']] ?? []); ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- TAB 2: ONGOING ORDERS -->
      <div class="tab-pane fade <?= $activeTab === 'ongoing' ? 'show active' : '' ?>" id="tab-ongoing-pane" role="tabpanel" aria-labelledby="tab-ongoing-btn" tabindex="0">
        <?php if (empty($ongoingOrders)): ?>
          <?php render_empty_state('ongoing'); ?>
        <?php else: ?>
          <div class="row g-0 orders-list-container">
            <?php foreach ($ongoingOrders as $order): ?>
              <?php render_order_card($order, $orderItemsGrouped[$order['order_id']] ?? []); ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- TAB 3: COMPLETED ORDERS -->
      <div class="tab-pane fade <?= $activeTab === 'completed' ? 'show active' : '' ?>" id="tab-completed-pane" role="tabpanel" aria-labelledby="tab-completed-btn" tabindex="0">
        <?php if (empty($completedOrders)): ?>
          <?php render_empty_state('completed'); ?>
        <?php else: ?>
          <div class="row g-0 orders-list-container">
            <?php foreach ($completedOrders as $order): ?>
              <?php render_order_card($order, $orderItemsGrouped[$order['order_id']] ?? []); ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- TAB 4: CANCELLED ORDERS -->
      <div class="tab-pane fade <?= $activeTab === 'cancelled' ? 'show active' : '' ?>" id="tab-cancelled-pane" role="tabpanel" aria-labelledby="tab-cancelled-btn" tabindex="0">
        <?php if (empty($cancelledOrders)): ?>
          <?php render_empty_state('cancelled'); ?>
        <?php else: ?>
          <div class="row g-0 orders-list-container">
            <?php foreach ($cancelledOrders as $order): ?>
              <?php render_order_card($order, $orderItemsGrouped[$order['order_id']] ?? []); ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Search No Results Fallback -->
      <div id="searchNoResults" class="card border-0 rounded-4 shadow-sm p-5 text-center bg-white d-none my-3">
        <i class="bi bi-search text-muted opacity-50 mb-3" style="font-size: 3rem;"></i>
        <h5 class="fw-bold text-dark font-outfit">No matching orders found</h5>
        <p class="text-muted small mb-3">We could not find any order matching "<span id="searchQueryEcho" class="fw-semibold text-dark"></span>".</p>
        <div>
          <button type="button" class="btn btn-outline-success rounded-pill px-4 py-2" onclick="clearSearchFilter()">Clear Search</button>
        </div>
      </div>

    </div>

  </div>
</main>

<!-- =========================================================================
     ORDER CANCELLATION CONFIRMATION MODAL
     ========================================================================= -->
<div class="modal fade" id="cancelConfirmModal" tabindex="-1" aria-labelledby="cancelConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold font-outfit text-danger d-flex align-items-center gap-2" id="cancelConfirmModalLabel">
          <i class="bi bi-exclamation-triangle-fill"></i> Cancel Order
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?= CUSTOMER_URL ?>/order-history.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel_order">
        <input type="hidden" name="order_id" id="modalCancelOrderId" value="">

        <div class="modal-body py-3">
          <p class="text-dark mb-2">
            Are you sure you want to cancel <strong id="modalCancelOrderText">this order</strong>?
          </p>
          <div class="alert alert-warning rounded-3 small mb-2">
            <i class="bi bi-info-circle-fill me-1"></i>
            Orders can only be cancelled within <strong>60 seconds</strong> of placement before the kitchen starts cooking.
          </div>
          <div class="text-muted small">
            A <strong>100% full refund</strong> will be immediately credited back to your original payment method, and inventory stock will be released.
          </div>
        </div>

        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Keep Order</button>
          <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">Confirm Cancellation</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =========================================================================
     CLIENT-SIDE TAB SWITCHING, REORDER, SEARCH, AND COUNTDOWN SCRIPT
     ========================================================================= -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  
  // 1. Tab switching with URL parameter synchronization
  const tabButtons = document.querySelectorAll('#orderHistoryTabs button[data-bs-toggle="pill"]');
  tabButtons.forEach(btn => {
    btn.addEventListener('shown.bs.tab', function(e) {
      const tabName = btn.getAttribute('data-tab-name');
      if (tabName) {
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.replaceState({}, '', url);
      }
      // Reapply search filter in active tab
      filterOrders();
    });
  });

  // Handle browser back/forward buttons
  window.addEventListener('popstate', function() {
    const url = new URL(window.location);
    const tabParam = url.searchParams.get('tab') || 'all';
    const targetBtn = document.querySelector(`#orderHistoryTabs button[data-tab-name="${tabParam}"]`);
    if (targetBtn && typeof bootstrap !== 'undefined') {
      const tabInstance = bootstrap.Tab.getOrCreateInstance(targetBtn);
      tabInstance.show();
    }
  });

  // 2. Real-time Search Filtering
  const searchInput = document.getElementById('orderSearchInput');
  const clearBtn = document.getElementById('clearSearchBtn');
  const visibleCountEl = document.getElementById('visibleOrdersCount');
  const searchNoResults = document.getElementById('searchNoResults');
  const searchQueryEcho = document.getElementById('searchQueryEcho');

  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const query = this.value.trim().toLowerCase();
      if (query.length > 0) {
        clearBtn.classList.remove('d-none');
      } else {
        clearBtn.classList.add('d-none');
      }
      filterOrders();
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', function() {
      clearSearchFilter();
    });
  }

  function filterOrders() {
    const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
    const activePane = document.querySelector('.tab-pane.active');
    if (!activePane) return;

    const cards = activePane.querySelectorAll('.order-card-wrapper');
    let visibleCount = 0;

    cards.forEach(card => {
      const searchData = card.getAttribute('data-search') || '';
      if (!query || searchData.includes(query)) {
        card.classList.remove('d-none');
        visibleCount++;
      } else {
        card.classList.add('d-none');
      }
    });

    if (visibleCountEl) {
      visibleCountEl.textContent = visibleCount;
    }

    if (cards.length > 0 && visibleCount === 0 && query.length > 0) {
      if (searchNoResults) {
        searchNoResults.classList.remove('d-none');
        if (searchQueryEcho) searchQueryEcho.textContent = query;
      }
    } else {
      if (searchNoResults) {
        searchNoResults.classList.add('d-none');
      }
    }
  }

  window.clearSearchFilter = function() {
    if (searchInput) {
      searchInput.value = '';
      clearBtn.classList.add('d-none');
      filterOrders();
      searchInput.focus();
    }
  };

  // 3. Setup Cancel Modal Data Hook
  const cancelTriggers = document.querySelectorAll('.cancel-order-trigger-btn');
  cancelTriggers.forEach(btn => {
    btn.addEventListener('click', function() {
      const oid = this.getAttribute('data-order-id');
      const modalIdInput = document.getElementById('modalCancelOrderId');
      const modalText = document.getElementById('modalCancelOrderText');
      if (modalIdInput) modalIdInput.value = oid;
      if (modalText) modalText.textContent = `Order #ZIP-${oid}`;
    });
  });

  // 4. Live 60-Second Cancellation Countdown Timer
  const timerBadges = document.querySelectorAll('.cancel-timer-badge');
  timerBadges.forEach(badge => {
    let remaining = parseInt(badge.getAttribute('data-seconds'), 10) || 0;
    const orderId = badge.getAttribute('data-order-id');
    const countdownSpan = badge.querySelector('.timer-countdown');

    if (remaining > 0) {
      const interval = setInterval(function() {
        remaining--;
        if (countdownSpan) countdownSpan.textContent = remaining + 's';
        if (remaining <= 0) {
          clearInterval(interval);
          badge.classList.remove('bg-danger', 'text-danger');
          badge.classList.add('bg-secondary', 'text-white');
          badge.innerHTML = '<i class="bi bi-clock-history me-1"></i> Cooking Started';
          
          // Disable cancel button
          const cancelBtn = document.querySelector(`.cancel-order-trigger-btn[data-order-id="${orderId}"]`);
          if (cancelBtn) {
            cancelBtn.remove();
          }
        }
      }, 1000);
    }
  });

});

/**
 * 1-Click Reorder Function
 * Adds order dishes directly into localStorage cart, calculates totals,
 * shows a toast notification and opens the offcanvas cart drawer.
 */
function reorderItems(items, orderId) {
  if (!items || !items.length) {
    if (typeof showToast === 'function') {
      showToast('No items found in this order to reorder.', 'warning');
    }
    return;
  }

  // Load existing cart
  let currentCart = JSON.parse(localStorage.getItem('pooja_zippy_cart')) || [];

  items.forEach(it => {
    const existingIndex = currentCart.findIndex(c => c.id == it.id);
    if (existingIndex > -1) {
      currentCart[existingIndex].qty += Number(it.qty || 1);
    } else {
      currentCart.push({
        id: Number(it.id),
        name: it.name,
        price: Number(it.price),
        isVeg: Boolean(it.isVeg),
        restaurant: it.restaurant || 'Meghana Foods',
        qty: Number(it.qty || 1)
      });
    }
  });

  // Save to localStorage
  localStorage.setItem('pooja_zippy_cart', JSON.stringify(currentCart));

  // Trigger app.js update helpers if available
  if (typeof updateCartBadge === 'function') updateCartBadge();
  if (typeof renderCartDrawer === 'function') renderCartDrawer();
  if (typeof showToast === 'function') {
    showToast(`Items from Order #ZIP-${orderId} added to cart! 🎉`);
  }

  // Open cart drawer
  const drawerEl = document.getElementById('cartDrawer');
  if (drawerEl && typeof bootstrap !== 'undefined') {
    const bsDrawer = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
    bsDrawer.show();
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>