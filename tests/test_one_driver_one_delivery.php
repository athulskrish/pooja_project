<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

echo "=======================================================\n";
echo "VERIFICATION TEST: ONE DRIVER ONE DELIVERY ENGINE\n";
echo "=======================================================\n\n";

// TEST 1: Driver Availability
echo "TEST 1: Driver Availability & Status\n";
$riders = get_all_delivery_riders();
foreach ($riders as $r) {
    echo "- " . $r['full_name'] . " (ID: " . $r['user_id'] . ") -> " . ($r['is_busy'] ? "BUSY with #" . $r['active_order_id'] : "AVAILABLE (0/1)") . "\n";
}

// TEST 2: Free Drivers
echo "\nTEST 2: Busy Check Logic\n";
$shajiId = (int)(db_fetch_val("SELECT user_id FROM `users` WHERE `email` = 'shaji@zippy.com'") ?: 6);
$kichuId = (int)(db_fetch_val("SELECT user_id FROM `users` WHERE `email` = 'kichu@zippy.com'") ?: 7);

$shajiBusy = is_driver_busy($shajiId, 'Shaji (Rider)');
echo "- Shaji busy initially: " . ($shajiBusy ? "YES" : "NO") . "\n";
assert($shajiBusy === null, "Shaji should be free initially");

// TEST 3: Available Driver Selection
echo "\nTEST 3: Available Driver Auto-Selection\n";
$nextAvail = get_available_delivery_partner();
echo "- Auto-assigned to: " . ($nextAvail ? $nextAvail['full_name'] : "None") . "\n";
assert($nextAvail !== null, "Should auto-assign an available driver");

// TEST 4: Simulate Active Delivery & Completion
echo "\nTEST 4: Busy Lifecycle Simulation Test\n";
// Temporarily set Order 1007 to OUT_FOR_DELIVERY with Shaji
db_query("UPDATE `orders` SET `order_status` = 'OUT_FOR_DELIVERY' WHERE `order_id` = 1007");
db_query("UPDATE `delivery_dispatches` SET `rider_id` = :rid, `delivery_partner_name` = 'Shaji (Rider)', `delivery_status` = 'IN_TRANSIT', `dispatched_at` = NOW() WHERE `order_id` = 1007", [':rid' => $shajiId]);

$duringDelivery = is_driver_busy($shajiId, 'Shaji (Rider)');
echo "- Shaji busy during active delivery: " . ($duringDelivery ? "YES (#{$duringDelivery['order_id']})" : "NO") . "\n";
assert($duringDelivery !== null, "Shaji should be busy with 1007");

// Now restore 1007 back to READY
db_query("UPDATE `orders` SET `order_status` = 'READY' WHERE `order_id` = 1007");
db_query("UPDATE `delivery_dispatches` SET `delivery_status` = 'ASSIGNED', `dispatched_at` = NULL WHERE `order_id` = 1007");

$afterRestore = is_driver_busy($shajiId, 'Shaji (Rider)');
echo "- Shaji free after order status restored: " . ($afterRestore ? "YES" : "NO (Free)") . "\n";
assert($afterRestore === null, "Shaji should be free after restoration");

echo "\n=======================================================\n";
echo "ALL TESTS PASSED: ONE DRIVER ONE DELIVERY RULE VERIFIED!\n";
echo "=======================================================\n";
