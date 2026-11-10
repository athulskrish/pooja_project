<?php
declare(strict_types=1);
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

requireCustomer();
$currentUser = getCurrentUser();
$userId = (int)$currentUser['id'];

// -----------------------------------------------------------------------------
// Handle Customer Order Cancellation (within 60s of placement)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid or expired session token.');
        header("Location: " . CUSTOMER_URL . "/order-history.php");
        exit;
    }

    $cancelOrderId = (int)($_POST['order_id'] ?? 0);
    $cancelRes = OrderService::cancelCustomerOrder($cancelOrderId, $userId, isAdmin());
    setFlash($cancelRes['flash_type'], $cancelRes['message']);
    header("Location: " . CUSTOMER_URL . "/order-history.php?tab=" . $cancelRes['tab']);
    exit;
}

$historyData = OrderService::getCustomerOrderHistoryData($userId);
$allOrders = $historyData['allOrders'];
$ongoingOrders = $historyData['ongoingOrders'];
$scheduledOrders = $historyData['scheduledOrders'];
$completedOrders = $historyData['completedOrders'];
$cancelledOrders = $historyData['cancelledOrders'];
$orderItemsGrouped = $historyData['orderItemsGrouped'];
$totalOrdersCount = $historyData['totalOrdersCount'];
$ongoingCount = $historyData['ongoingCount'];
$scheduledCount = $historyData['scheduledCount'];
$completedCount = $historyData['completedCount'];
$cancelledCount = $historyData['cancelledCount'];
$totalDeliveredSpend = $historyData['totalDeliveredSpend'];

// Determine active tab from URL query
$validTabs = ['all', 'ongoing', 'scheduled', 'completed', 'cancelled'];
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

    <!-- 1-Tap Quick Reorder Previous Meal Banner -->
    <?php
    $latestCompleted = !empty($completedOrders) ? $completedOrders[0] : (!empty($allOrders) ? $allOrders[0] : null);
    if ($latestCompleted && !empty($orderItemsGrouped[$latestCompleted['order_id']])):
        $prevOrderItems = $orderItemsGrouped[$latestCompleted['order_id']];
        $prevReorderPayload = array_map(function (array $it): array {
            return [
                'id' => (int)$it['item_id'],
                'name' => $it['item_name'],
                'price' => (float)$it['unit_price'],
                'isVeg' => (bool)$it['is_veg'],
                'restaurant' => 'Meghana Foods',
                'imageUrl' => getDishImageUrl($it['image_url'] ?? ''),
                'qty' => (int)$it['quantity'],
                'customizationText' => $it['customizations'] ?? '',
                'customizationTotal' => (float)($it['customization_total'] ?? 0),
                'cartItemId' => !empty($it['customizations']) ? ($it['item_id'] . '_' . md5($it['customizations'])) : (string)$it['item_id']
            ];
        }, $prevOrderItems);
        $prevReorderJson = htmlspecialchars(json_encode($prevReorderPayload) ?: '[]', ENT_QUOTES, 'UTF-8');
    ?>
    <div class="card border-0 rounded-4 shadow-sm p-3 p-md-4 mb-4" style="background: linear-gradient(135deg, #F0FAF3 0%, #E2F6EB 100%); border-left: 6px solid #2E6F40 !important;">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0 shadow-sm" style="width: 50px; height: 50px; background: #2E6F40;">
            <i class="fa-solid fa-rotate-right fs-4"></i>
          </div>
          <div>
            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
              <span class="badge rounded-pill px-2 py-1 fw-bold" style="background: #243E2C; color: #CEFFDC; font-size: 0.7rem;">
                <i class="fa-solid fa-bolt text-mint me-1"></i>1-TAP REORDER
              </span>
              <span class="badge bg-white text-forest border rounded-pill fw-semibold small">
                Order #ZIP-<?= (int)$latestCompleted['order_id'] ?>
              </span>
              <span class="text-muted small"><?= date('d M Y, h:i A', (int)(strtotime((string)$latestCompleted['placed_at']) ?: time())) ?></span>
            </div>
            <h6 class="fw-bold mb-1 text-dark font-outfit">Repeat your previous meal in one tap</h6>
            <div class="small text-muted d-flex align-items-center gap-2 flex-wrap">
              <span><?= implode(' &bull; ', array_map(fn(array $it): string => e($it['item_name']) . ' &times;' . (int)$it['quantity'], $prevOrderItems)) ?></span>
              <strong class="text-forest">&bull; <?= formatInr($latestCompleted['total_amount']) ?></strong>
            </div>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button type="button" class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm d-flex align-items-center gap-2" style="background:#2E6F40; border-color:#2E6F40;" onclick="oneTapReorderMeal(<?= $prevReorderJson ?>, <?= (int)$latestCompleted['order_id'] ?>, false, this)">
            <i class="fa-solid fa-rotate-right"></i>
            <span>1-Tap Reorder (<?= formatInr($latestCompleted['total_amount']) ?>)</span>
          </button>
          <button type="button" class="btn btn-outline-forest rounded-pill px-3 py-2 fw-semibold bg-white d-none d-sm-inline-flex align-items-center gap-1" style="border-color:#2E6F40; color:#2E6F40;" onclick="oneTapReorderMeal(<?= $prevReorderJson ?>, <?= (int)$latestCompleted['order_id'] ?>, true, this)" title="Reorder and jump directly to checkout">
            <i class="fa-solid fa-bolt text-warning"></i>
            <span>Checkout Now</span>
          </button>
        </div>
      </div>
    </div>
    <?php endif; ?>

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

        <!-- Tab 3: Scheduled Orders -->
        <li class="nav-item flex-fill text-center" role="presentation">
          <button class="nav-link w-100 <?= $activeTab === 'scheduled' ? 'active' : '' ?>" id="tab-scheduled-btn" data-bs-toggle="pill" data-bs-target="#tab-scheduled-pane" type="button" role="tab" aria-controls="tab-scheduled-pane" aria-selected="<?= $activeTab === 'scheduled' ? 'true' : 'false' ?>" data-tab-name="scheduled">
            <i class="bi bi-calendar2-check-fill"></i>
            <span>Scheduled</span>
            <span class="badge rounded-pill ms-1 text-white" style="background:#7C3AED;" id="badge-count-scheduled"><?= $scheduledCount ?></span>
          </button>
        </li>

        <!-- Tab 4: Completed Orders -->
        <li class="nav-item flex-fill text-center" role="presentation">
          <button class="nav-link w-100 <?= $activeTab === 'completed' ? 'active' : '' ?>" id="tab-completed-btn" data-bs-toggle="pill" data-bs-target="#tab-completed-pane" type="button" role="tab" aria-controls="tab-completed-pane" aria-selected="<?= $activeTab === 'completed' ? 'true' : 'false' ?>" data-tab-name="completed">
            <i class="bi bi-check2-circle"></i>
            <span>Completed</span>
            <span class="badge rounded-pill ms-1 bg-success text-white" id="badge-count-completed"><?= $completedCount ?></span>
          </button>
        </li>

        <!-- Tab 5: Cancelled Orders -->
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
         TABS PANES CONTENT
         ======================================================================= -->
    <div class="tab-content" id="orderHistoryTabsContent">
      
      <!-- TAB 1: ALL ORDERS -->
      <div class="tab-pane fade <?= $activeTab === 'all' ? 'show active' : '' ?>" id="tab-all-pane" role="tabpanel" aria-labelledby="tab-all-btn" tabindex="0">
        <?php if (empty($allOrders)): ?>
          <?php renderEmptyState('all'); ?>
        <?php else: ?>
          <div class="row g-0 orders-list-container">
            <?php foreach ($allOrders as $order): ?>
              <?php renderOrderCard($order, $orderItemsGrouped[$order['order_id']] ?? []); ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- TAB 2: ONGOING ORDERS -->
      <div class="tab-pane fade <?= $activeTab === 'ongoing' ? 'show active' : '' ?>" id="tab-ongoing-pane" role="tabpanel" aria-labelledby="tab-ongoing-btn" tabindex="0">
        <?php if (empty($ongoingOrders)): ?>
          <?php renderEmptyState('ongoing'); ?>
        <?php else: ?>
          <div class="row g-0 orders-list-container">
            <?php foreach ($ongoingOrders as $order): ?>
              <?php renderOrderCard($order, $orderItemsGrouped[$order['order_id']] ?? []); ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- TAB: SCHEDULED ORDERS -->
      <div class="tab-pane fade <?= $activeTab === 'scheduled' ? 'show active' : '' ?>" id="tab-scheduled-pane" role="tabpanel" aria-labelledby="tab-scheduled-btn" tabindex="0">
        <?php if (empty($scheduledOrders)): ?>
          <?php renderEmptyState('scheduled'); ?>
        <?php else: ?>
          <div class="row g-0 orders-list-container">
            <?php foreach ($scheduledOrders as $order): ?>
              <?php renderOrderCard($order, $orderItemsGrouped[$order['order_id']] ?? []); ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- TAB 3: COMPLETED ORDERS -->
      <div class="tab-pane fade <?= $activeTab === 'completed' ? 'show active' : '' ?>" id="tab-completed-pane" role="tabpanel" aria-labelledby="tab-completed-btn" tabindex="0">
        <?php if (empty($completedOrders)): ?>
          <?php renderEmptyState('completed'); ?>
        <?php else: ?>
          <div class="row g-0 orders-list-container">
            <?php foreach ($completedOrders as $order): ?>
              <?php renderOrderCard($order, $orderItemsGrouped[$order['order_id']] ?? []); ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- TAB 4: CANCELLED ORDERS -->
      <div class="tab-pane fade <?= $activeTab === 'cancelled' ? 'show active' : '' ?>" id="tab-cancelled-pane" role="tabpanel" aria-labelledby="tab-cancelled-btn" tabindex="0">
        <?php if (empty($cancelledOrders)): ?>
          <?php renderEmptyState('cancelled'); ?>
        <?php else: ?>
          <div class="row g-0 orders-list-container">
            <?php foreach ($cancelledOrders as $order): ?>
              <?php renderOrderCard($order, $orderItemsGrouped[$order['order_id']] ?? []); ?>
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
        <?= csrfField() ?>
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
 * Delegates to centralized oneTapReorderMeal in app.js
 */
function reorderItems(items, orderId) {
  if (typeof oneTapReorderMeal === 'function') {
    oneTapReorderMeal(items, orderId, false, null);
    return;
  }
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
        imageUrl: it.imageUrl || (window.DISH_IMAGES_MAP && window.DISH_IMAGES_MAP[it.id]) || '',
        qty: Number(it.qty || 1)
      });
    }
  });

  // Save to localStorage
  localStorage.setItem('pooja_zippy_cart', JSON.stringify(currentCart));
  if (typeof cart !== 'undefined') cart = currentCart;

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