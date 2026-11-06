<?php
/**
 * AJAX Endpoint: Kitchen Station Order Poller & New Order Listener
 * Returns JSON for live polling & audio notification triggering
 * Zippy - Online Food Ordering Platform
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!is_kitchen() && !is_admin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Automatically ensure ready orders are allocated in FCFS order
auto_allocate_ready_orders_fcfs();

// Counts
$countNew = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` IN ('PLACED', 'CONFIRMED')");
$countCooking = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'COOKING'");
$countReady = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'READY'");
$countScheduled = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `is_scheduled` = 1 AND `order_status` IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY')");
$countHandedOver = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` IN ('OUT_FOR_DELIVERY', 'DELIVERED') AND DATE(`placed_at`) = CURDATE()");
$countDeliveredToday = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'DELIVERED' AND DATE(`placed_at`) = CURDATE()");

// Latest unaccepted order ID
$latestIncomingId = (int)db_fetch_val("
    SELECT MAX(order_id) FROM `orders` 
    WHERE `order_status` IN ('PLACED', 'CONFIRMED')
");

// Total active in kitchen
$totalActive = $countNew + $countCooking + $countReady;

// Comprehensive State Fingerprint
// Detects state transitions: Cooking -> Ready -> Out for delivery -> Delivered
$latestAudit = db_fetch_one("SELECT MAX(audit_id) AS max_audit, MAX(change_timestamp) AS max_time FROM `order_audit_trail`");
$latestDispatch = db_fetch_one("SELECT MAX(assigned_at) AS max_assigned, MAX(dispatched_at) AS max_dispatched, MAX(delivered_at) AS max_delivered FROM `delivery_dispatches`");

$stateHash = md5(json_encode([
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

echo json_encode([
    'success' => true,
    'timestamp' => time(),
    'state_hash' => $stateHash,
    'counts' => [
        'new' => $countNew,
        'cooking' => $countCooking,
        'ready' => $countReady,
        'scheduled' => $countScheduled,
        'handed_over' => $countHandedOver,
        'delivered' => $countDeliveredToday,
        'total_active' => $totalActive
    ],
    'latest_incoming_id' => $latestIncomingId
]);
exit;
