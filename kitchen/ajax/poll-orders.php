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

// Counts
$countNew = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` IN ('PLACED', 'CONFIRMED')");
$countCooking = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'COOKING'");
$countReady = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'READY'");
$countHandedOver = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` IN ('OUT_FOR_DELIVERY', 'DELIVERED') AND DATE(`placed_at`) = CURDATE()");

// Latest unaccepted order ID
$latestIncomingId = (int)db_fetch_val("
    SELECT MAX(order_id) FROM `orders` 
    WHERE `order_status` IN ('PLACED', 'CONFIRMED')
");

// Total active in kitchen
$totalActive = $countNew + $countCooking + $countReady;

echo json_encode([
    'success' => true,
    'timestamp' => time(),
    'counts' => [
        'new' => $countNew,
        'cooking' => $countCooking,
        'ready' => $countReady,
        'handed_over' => $countHandedOver,
        'total_active' => $totalActive
    ],
    'latest_incoming_id' => $latestIncomingId
]);
exit;
