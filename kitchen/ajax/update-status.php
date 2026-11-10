<?php
declare(strict_types=1);
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

// Verify Kitchen or Admin credentials
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Authentication required.']);
    exit;
}

if (!isKitchen() && !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Kitchen Chef or Admin login required.']);
    exit;
}

$currentUser = getCurrentUser();

// Accept both JSON payload and standard POST
$rawInput = file_get_contents('php://input');
$json = json_decode($rawInput, true) ?: [];

if (!verifyRequestCsrf($json)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF token.']);
    exit;
}

$orderId = (int)($json['order_id'] ?? $_POST['order_id'] ?? 0);
$targetStatus = cleanInput($json['target_status'] ?? $_POST['target_status'] ?? '');
$riderName = cleanInput($json['rider_name'] ?? $_POST['rider_name'] ?? '');

if ($orderId <= 0 || empty($targetStatus)) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID or target status specified.']);
    exit;
}

// Fetch current order
$order = dbFetchOne("SELECT * FROM `orders` WHERE `order_id` = :id", [':id' => $orderId]);
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
} elseif ($oldStatus === 'READY' && $targetStatus === 'OUT_FOR_DELIVERY' && isAdmin()) {
    $allowed = true;
    $actionVerb = 'dispatched for doorstep fulfillment';
}

if (!$allowed) {
    echo json_encode(['success' => false, 'message' => "Invalid transition from {$oldStatus} to {$targetStatus}."]);
    exit;
}

// Transition via centralized state machine
$result = transitionOrderStatus($orderId, $targetStatus, $riderName, $currentUser['id'] ?? null);

if (!$result['success']) {
    echo json_encode([
        'success' => false,
        'message' => $result['error'] ?? 'Failed to update order status.'
    ]);
    exit;
}

// Return fresh counts
$countNew = (int)dbFetchVal("SELECT COUNT(*) FROM `orders` WHERE `order_status` IN ('PLACED', 'CONFIRMED')");
$countCooking = (int)dbFetchVal("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'COOKING'");
$countReady = (int)dbFetchVal("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'READY'");
$countHandedOver = (int)dbFetchVal("SELECT COUNT(*) FROM `orders` WHERE `order_status` IN ('OUT_FOR_DELIVERY', 'DELIVERED') AND DATE(`placed_at`) = CURDATE()");

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
