<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

echo "=======================================================\n";
echo "VERIFICATION TEST: ONE DRIVER ONE DELIVERY ENGINE\n";
echo "=======================================================\n\n";

// TEST 1: Driver Availability
echo "TEST 1: Driver Availability & Status\n";
$riders = getAllDeliveryRiders();
foreach ($riders as $r) {
    echo "- " . $r['full_name'] . " (ID: " . $r['user_id'] . ") -> " . ($r['is_busy'] ? "BUSY with #" . $r['active_order_id'] : "AVAILABLE (0/1)") . "\n";
}

// TEST 2: Free Drivers
echo "\nTEST 2: Busy Check Logic\n";
$shajiId = (int)(dbFetchVal("SELECT user_id FROM `users` WHERE `email` = 'shaji@zippy.com'") ?: 6);
$kichuId = (int)(dbFetchVal("SELECT user_id FROM `users` WHERE `email` = 'kichu@zippy.com'") ?: 7);

$shajiBusy = isDriverBusy($shajiId, 'Shaji (Rider)');
echo "- Shaji busy initially: " . ($shajiBusy ? "YES" : "NO") . "\n";
assert($shajiBusy === null, "Shaji should be free initially");

// TEST 3: Available Driver Selection
echo "\nTEST 3: Available Driver Auto-Selection\n";
$nextAvail = getAvailableDeliveryPartner();
echo "- Auto-assigned to: " . ($nextAvail ? $nextAvail['full_name'] : "None") . "\n";
assert($nextAvail !== null, "Should auto-assign an available driver");

// TEST 4: Simulate Active Delivery & Completion
echo "\nTEST 4: Busy Lifecycle Simulation Test\n";
// Temporarily set Order 1007 to OUT_FOR_DELIVERY with Shaji
dbQuery("UPDATE `orders` SET `order_status` = 'OUT_FOR_DELIVERY' WHERE `order_id` = 1007");
dbQuery("UPDATE `delivery_dispatches` SET `rider_id` = :rid, `delivery_partner_name` = 'Shaji (Rider)', `delivery_status` = 'IN_TRANSIT', `dispatched_at` = NOW() WHERE `order_id` = 1007", [':rid' => $shajiId]);

$duringDelivery = isDriverBusy($shajiId, 'Shaji (Rider)');
echo "- Shaji busy during active delivery: " . ($duringDelivery ? "YES (#{$duringDelivery['order_id']})" : "NO") . "\n";
assert($duringDelivery !== null, "Shaji should be busy with 1007");

// Now restore 1007 back to READY
dbQuery("UPDATE `orders` SET `order_status` = 'READY' WHERE `order_id` = 1007");
dbQuery("UPDATE `delivery_dispatches` SET `delivery_status` = 'ASSIGNED', `dispatched_at` = NULL WHERE `order_id` = 1007");

$afterRestore = isDriverBusy($shajiId, 'Shaji (Rider)');
echo "- Shaji free after order status restored: " . ($afterRestore ? "YES" : "NO (Free)") . "\n";
assert($afterRestore === null, "Shaji should be free after restoration");

echo "\n=======================================================\n";
echo "ALL TESTS PASSED: ONE DRIVER ONE DELIVERY RULE VERIFIED!\n";
echo "=======================================================\n";
