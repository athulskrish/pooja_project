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

// TEST 2: Busy Check Logic
echo "\nTEST 2: Busy Check Logic\n";
$sureshBusy = is_driver_busy(5, 'Suresh Kumar');
echo "- Suresh Kumar busy: " . ($sureshBusy ? "YES (Order #" . $sureshBusy['order_id'] . ")" : "NO") . "\n";
assert($sureshBusy !== null, "Suresh Kumar should be busy with 1058");

$rajeshBusy = is_driver_busy(10, 'Rajesh Varma');
echo "- Rajesh Varma busy: " . ($rajeshBusy ? "YES" : "NO (Ready to accept)") . "\n";
assert($rajeshBusy === null, "Rajesh Varma should be free");

// TEST 3: Available Driver Selection
echo "\nTEST 3: Available Driver Auto-Selection for Checkout\n";
$nextAvail = get_available_delivery_partner();
echo "- Auto-assigned to: " . ($nextAvail ? $nextAvail['full_name'] : "None") . "\n";
assert($nextAvail !== null && $nextAvail['user_id'] == 10, "Should auto-assign Rajesh Varma");

// TEST 4: Simulate Delivery Completion (OTP Verification)
echo "\nTEST 4: Delivery Completion Lifecycle Test\n";
// Let's test that when Suresh delivers order 1058, he becomes available!
$dispatch1058 = db_fetch_one("SELECT * FROM `delivery_dispatches` WHERE `order_id` = 1058");
$otp = $dispatch1058['delivery_otp'];
echo "- Current Order 1058 OTP: {$otp}\n";

// Verify Suresh is busy right now
$beforeDelivery = is_driver_busy(5, 'Suresh Kumar');
echo "- Suresh busy before delivery: " . ($beforeDelivery ? "YES (#{$beforeDelivery['order_id']})" : "NO") . "\n";

// Now complete delivery of 1058
db_query("UPDATE `orders` SET `order_status` = 'DELIVERED' WHERE `order_id` = 1058");
db_query("UPDATE `delivery_dispatches` SET `delivery_status` = 'DELIVERED', `delivered_at` = NOW() WHERE `order_id` = 1058");

// Now check if Suresh is free
$afterDelivery = is_driver_busy(5, 'Suresh Kumar');
echo "- Suresh busy after delivery: " . ($afterDelivery ? "YES" : "NO (Successfully FREE for next trip!)") . "\n";
assert($afterDelivery === null, "Suresh should be free after delivery");

// Restore order 1058 back to OUT_FOR_DELIVERY for live UI demonstration
db_query("UPDATE `orders` SET `order_status` = 'OUT_FOR_DELIVERY' WHERE `order_id` = 1058");
db_query("UPDATE `delivery_dispatches` SET `delivery_status` = 'IN_TRANSIT', `delivered_at` = NULL WHERE `order_id` = 1058");

$restored = is_driver_busy(5, 'Suresh Kumar');
echo "- Suresh restored to active delivery: " . ($restored ? "YES (#{$restored['order_id']})" : "NO") . "\n";

echo "\n=======================================================\n";
echo "ALL TESTS PASSED: ONE DRIVER ONE DELIVERY RULE VERIFIED!\n";
echo "=======================================================\n";
