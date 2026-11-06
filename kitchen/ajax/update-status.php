<?php
/**
 * AJAX Endpoint: Kitchen Station Order Lifecycle Transitions
 * Handles:
 * - Accept Order: PLACED/CONFIRMED -> COOKING
 * - Prepare Order: COOKING -> READY
 * - Hand Over to Delivery Boy: READY -> OUT_FOR_DELIVERY (with rider dispatch update)
 * Zippy - Online Food Ordering Platform
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verify Kitchen or Admin credentials
if (!is_kitchen() && !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Kitchen Chef or Admin login required.']);
    exit;
}

$currentUser = current_user();

// Accept both JSON payload and standard POST
$rawInput = file_get_contents('php://input');
$json = json_decode($rawInput, true);

$orderId = (int)($json['order_id'] ?? $_POST['order_id'] ?? 0);
$targetStatus = clean_input($json['target_status'] ?? $_POST['target_status'] ?? '');
$riderName = clean_input($json['rider_name'] ?? $_POST['rider_name'] ?? '');

if ($orderId <= 0 || empty($targetStatus)) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID or target status specified.']);
    exit;
}

// Fetch current order
$order = db_fetch_one("SELECT * FROM `orders` WHERE `order_id` = :id", [':id' => $orderId]);
if (!$order) {
    echo json_encode(['success' => false, 'message' => "Order #ZIP-{$orderId} not found."]);
    exit;
}

$oldStatus = $order['order_status'];

// Validate allowed status transitions
$allowed = false;
$actionVerb = '';

if (in_array($oldStatus, ['PLACED', 'CONFIRMED']) && $targetStatus === 'COOKING') {
    $allowed = true;
    $actionVerb = 'accepted and moved to Stoves (Cooking)';
} elseif ($oldStatus === 'COOKING' && $targetStatus === 'READY') {
    $allowed = true;
    $actionVerb = 'prepared and placed as Ready for Delivery';
} elseif ($oldStatus === 'READY' && $targetStatus === 'OUT_FOR_DELIVERY' && is_admin()) {
    $allowed = true;
    $actionVerb = 'dispatched for doorstep fulfillment';
}

if (!$allowed) {
    echo json_encode(['success' => false, 'message' => "Invalid transition from {$oldStatus} to {$targetStatus}."]);
    exit;
}

// Transition via centralized state machine
$result = transition_order_status($orderId, $targetStatus, $riderName, $currentUser['id'] ?? null);

if (!$result['success']) {
    echo json_encode([
        'success' => false,
        'message' => $result['error'] ?? 'Failed to update order status.'
    ]);
    exit;
}

// Return fresh counts
$countNew = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` IN ('PLACED', 'CONFIRMED')");
$countCooking = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'COOKING'");
$countReady = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'READY'");
$countHandedOver = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` IN ('OUT_FOR_DELIVERY', 'DELIVERED') AND DATE(`placed_at`) = CURDATE()");

echo json_encode([
    'success' => true,
    'order_id' => $orderId,
    'old_status' => $oldStatus,
    'new_status' => $targetStatus,
    'message' => "Ticket #ZIP-{$orderId} {$actionVerb}!",
    'counts' => [
        'new' => $countNew,
        'cooking' => $countCooking,
        'ready' => $countReady,
        'handed_over' => $countHandedOver,
    ]
]);
