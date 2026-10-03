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
$riderPhone = clean_input($json['rider_phone'] ?? $_POST['rider_phone'] ?? '');

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
    $actionVerb = 'prepared, plated and ready at expeditor counter';
} elseif ($oldStatus === 'READY' && $targetStatus === 'OUT_FOR_DELIVERY') {
    $allowed = true;
    $actionVerb = 'handed over to delivery boy for doorstep fulfillment';
}

if (!$allowed) {
    echo json_encode(['success' => false, 'message' => "Invalid transition from {$oldStatus} to {$targetStatus}."]);
    exit;
}

// Update order status
db_query("UPDATE `orders` SET `order_status` = :st WHERE `order_id` = :id", [
    ':st' => $targetStatus,
    ':id' => $orderId
]);

// If handing over to delivery boy: update or create delivery_dispatches
if ($targetStatus === 'OUT_FOR_DELIVERY') {
    $dispatch = db_fetch_one("SELECT * FROM `delivery_dispatches` WHERE `order_id` = :id", [':id' => $orderId]);

    // Default rider fallback if empty
    $finalRiderName = !empty($riderName) ? $riderName : ($dispatch['delivery_partner_name'] ?? 'Suresh Kumar (Rider)');
    $finalRiderPhone = !empty($riderPhone) ? $riderPhone : ($dispatch['delivery_partner_phone'] ?? '+91 96320 98765');

    if ($dispatch) {
        db_query("
            UPDATE `delivery_dispatches` 
            SET `delivery_partner_name` = :rname,
                `delivery_partner_phone` = :rphone,
                `dispatched_at` = NOW(),
                `delivery_status` = 'IN_TRANSIT'
            WHERE `order_id` = :id
        ", [
            ':rname' => $finalRiderName,
            ':rphone' => $finalRiderPhone,
            ':id' => $orderId
        ]);
    } else {
        $newOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        db_query("
            INSERT INTO `delivery_dispatches` 
            (`order_id`, `delivery_partner_name`, `delivery_partner_phone`, `delivery_otp`, `assigned_at`, `dispatched_at`, `delivery_status`)
            VALUES (:id, :rname, :rphone, :otp, NOW(), NOW(), 'IN_TRANSIT')
        ", [
            ':id' => $orderId,
            ':rname' => $finalRiderName,
            ':rphone' => $finalRiderPhone,
            ':otp' => $newOtp
        ]);
    }
}

// Audit trail
db_query("
    INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`) 
    VALUES (:oid, :old, :new, :uid, NOW())
", [
    ':oid' => $orderId,
    ':old' => $oldStatus,
    ':new' => $targetStatus,
    ':uid' => $currentUser['id'] ?? null
]);

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
        'handed_over' => $countHandedOver
    ]
]);
exit;
