<?php
declare(strict_types=1);
/**
 * AJAX Endpoint: Kitchen Station Order Poller & New Order Listener
 * Returns JSON for live polling & audio notification triggering
 * Zippy - Online Food Ordering Platform
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isKitchen() && !isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Automatically ensure ready orders are allocated in FCFS order
autoAllocateReadyOrdersFcfs();

// Counts
$countNew = (int)dbFetchVal("SELECT COUNT(*) FROM `orders` WHERE `order_status` IN ('PLACED', 'CONFIRMED')");
$countCooking = (int)dbFetchVal("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'COOKING'");
$countReady = (int)dbFetchVal("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'READY'");
$countScheduled = (int)dbFetchVal("SELECT COUNT(*) FROM `orders` WHERE `is_scheduled` = 1 AND `order_status` IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY')");
$countHandedOver = (int)dbFetchVal("SELECT COUNT(*) FROM `orders` WHERE `order_status` IN ('OUT_FOR_DELIVERY', 'DELIVERED') AND DATE(`placed_at`) = CURDATE()");
$countDeliveredToday = (int)dbFetchVal("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'DELIVERED' AND DATE(`placed_at`) = CURDATE()");

// Latest unaccepted order ID
$latestIncomingId = (int)dbFetchVal("
    SELECT MAX(order_id) FROM `orders` 
    WHERE `order_status` IN ('PLACED', 'CONFIRMED')
");

// Total active in kitchen
$totalActive = $countNew + $countCooking + $countReady;

// Comprehensive Canonical State Fingerprint
$stateHash = getKdsStateHash();

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
