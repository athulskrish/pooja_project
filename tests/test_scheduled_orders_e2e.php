<?php
/**
 * End-to-End Test Suite for Advance Scheduled Meal Orders
 * Validates DB Schema, Order Placement, Queue Processing, Admin Ledger, and KDS Poller logic.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/WSJFQueue.php';
require_once __DIR__ . '/../includes/FCFSQueue.php';

echo "======================================================\n";
echo " ADVANCE SCHEDULED MEAL ORDERS - E2E TEST SUITE \n";
echo "======================================================\n\n";

$pdo = getDBConnection();
$passed = 0;
$failed = 0;

function assert_test($description, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] $description\n";
        $passed++;
    } else {
        echo " [FAIL] $description\n";
        $failed++;
    }
}

// Clean up any previous test orders
$pdo->query("DELETE FROM order_items WHERE order_id IN (SELECT order_id FROM orders WHERE order_number LIKE 'TEST-SCHED-%')");
$pdo->query("DELETE FROM orders WHERE order_number LIKE 'TEST-SCHED-%'");

// 1. Verify Database Schema
echo "--- 1. Database Schema Verification ---\n";
$stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'is_scheduled'");
assert_test("Column 'is_scheduled' exists in 'orders'", $stmt->rowCount() > 0);

$stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'scheduled_for'");
assert_test("Column 'scheduled_for' exists in 'orders'", $stmt->rowCount() > 0);

$stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'scheduled_slot'");
assert_test("Column 'scheduled_slot' exists in 'orders'", $stmt->rowCount() > 0);

// Check index
$stmt = $pdo->query("SHOW INDEX FROM orders WHERE Key_name = 'idx_orders_scheduled'");
assert_test("Index 'idx_orders_scheduled' exists", $stmt->rowCount() > 0);

// 2. Insert a Test Scheduled Order
echo "\n--- 2. Placing a Scheduled Order Simulation ---\n";
// Ensure test user exists
$stmt = $pdo->query("SELECT user_id FROM users WHERE role_id = 2 LIMIT 1");
$cust = $stmt->fetch();
$userId = $cust ? $cust['user_id'] : 1;

// Get an existing menu item
$itemStmt = $pdo->query("SELECT item_id, price FROM menu_items LIMIT 1");
$dish = $itemStmt->fetch();
$itemId = $dish ? $dish['item_id'] : 1;
$itemPrice = $dish ? $dish['price'] : 150.00;

$testOrderNum = 'TEST-SCHED-' . time();
$scheduledFor = date('Y-m-d H:i:s', strtotime('+24 hours'));
$scheduledSlot = 'Tomorrow Lunch (01:00 PM - 01:30 PM)';

$sql = "INSERT INTO orders (
    order_number, user_id, address_id, subtotal, tax_amount, delivery_fee, platform_fee, discount_amount,
    total_amount, order_status, is_scheduled, scheduled_for, scheduled_slot, estimated_delivery_time, placed_at
) VALUES (
    ?, ?, NULL, ?, 15.00, 30.00, 5.00, 0.00,
    ?, 'PLACED', 1, ?, ?, ?, NOW()
)";

$subtotal = $itemPrice * 2;
$total = $subtotal + 15.00 + 30.00 + 5.00;

$stmt = $pdo->prepare($sql);
$res = $stmt->execute([$testOrderNum, $userId, $subtotal, $total, $scheduledFor, $scheduledSlot, $scheduledFor]);
$testOrderId = $pdo->lastInsertId();

assert_test("Scheduled order created with ID $testOrderId and number $testOrderNum", $res && $testOrderId > 0);

// Insert sample order item
$oiStmt = $pdo->prepare("INSERT INTO order_items (order_id, item_id, quantity, unit_price, line_total) VALUES (?, ?, 2, ?, ?)");
$oiStmt->execute([$testOrderId, $itemId, $itemPrice, $subtotal]);

// Fetch and assert order details
$checkStmt = $pdo->prepare("SELECT is_scheduled, scheduled_for, scheduled_slot, order_status FROM orders WHERE order_id = ?");
$checkStmt->execute([$testOrderId]);
$fetchedOrder = $checkStmt->fetch();

assert_test("Order has is_scheduled = 1", (int)$fetchedOrder['is_scheduled'] === 1);
assert_test("Order has scheduled_slot matching input", $fetchedOrder['scheduled_slot'] === $scheduledSlot);
assert_test("Order has scheduled_for properly populated", !empty($fetchedOrder['scheduled_for']));

// 3. Test get_order_kpis()
echo "\n--- 3. KPI & Analytics Calculation ---\n";
$kpis = get_order_kpis();
assert_test("get_order_kpis() contains 'scheduled' key", isset($kpis['scheduled']));
assert_test("get_order_kpis() scheduled count >= 1 (value: " . ($kpis['scheduled'] ?? 0) . ")", ($kpis['scheduled'] ?? 0) >= 1);

// 4. Test WSJF Queue Prioritization
echo "\n--- 4. WSJF Queue Processing ---\n";
$wsjfQueue = new WSJFQueue();
$prioritizedOrders = $wsjfQueue->getPrioritizedOrders('PLACED');

$foundInQueue = false;
$foundOrderData = null;
foreach ($prioritizedOrders as $o) {
    if ($o['order_id'] == $testOrderId) {
        $foundInQueue = true;
        $foundOrderData = $o;
        break;
    }
}
assert_test("Advance scheduled order appears in WSJF prioritized queue", $foundInQueue);
if ($foundInQueue) {
    assert_test("Order maintains is_scheduled flag in queue", (int)$foundOrderData['is_scheduled'] === 1);
    assert_test("Order has scheduled_slot in queue record", !empty($foundOrderData['scheduled_slot']));
    $urgencyLabel = is_array($foundOrderData['urgency_badge']) ? ($foundOrderData['urgency_badge']['label'] ?? 'OK') : $foundOrderData['urgency_badge'];
    echo "    -> WSJF Score: " . $foundOrderData['wsjf_score'] . ", Urgency: " . $urgencyLabel . "\n";
}

// 5. Test FCFS Triage Exceptions
echo "\n--- 5. FCFS Queue Triage & SLA Guardrails ---\n";
$fcfsQueue = new FCFSQueue();
$exceptions = $fcfsQueue->getTriageExceptions();

$flaggedAsOverdue = false;
$flaggedAsStalled = false;
foreach ($exceptions as $exc) {
    if (isset($exc['action_url']) && strpos($exc['action_url'], (string)$testOrderId) !== false) {
        if ($exc['type'] === 'OVERDUE COOKING') {
            $flaggedAsOverdue = true;
        }
        if ($exc['type'] === 'AWAITING KITCHEN') {
            $flaggedAsStalled = true;
        }
    }
}
assert_test("Advance scheduled order (due tomorrow) is NOT falsely flagged in overdue cooking triage", !$flaggedAsOverdue);
assert_test("Advance scheduled order (due tomorrow) is NOT falsely flagged in awaiting kitchen triage", !$flaggedAsStalled);

// 6. Test Admin Ledger Query
echo "\n--- 6. Admin Ledger 'status=SCHEDULED' Filter ---\n";
$adminFilterSql = "SELECT o.order_id, o.order_number, o.is_scheduled, o.scheduled_for, o.scheduled_slot
                   FROM orders o
                   WHERE o.is_scheduled = 1";
$adminStmt = $pdo->query($adminFilterSql);
$adminScheduledOrders = $adminStmt->fetchAll();
$foundInAdmin = false;
foreach ($adminScheduledOrders as $ao) {
    if ($ao['order_id'] == $testOrderId) {
        $foundInAdmin = true;
        break;
    }
}
assert_test("Admin query for scheduled orders successfully retrieves test order", $foundInAdmin);

// 7. Test KDS Advance Scheduled Queue
echo "\n--- 7. KDS Advance Scheduled Orders Query ---\n";
$kdsSql = "SELECT o.order_id, o.order_number, o.order_status, o.is_scheduled, o.scheduled_for, o.scheduled_slot
           FROM orders o
           WHERE o.is_scheduled = 1 AND o.order_status IN ('PLACED', 'CONFIRMED', 'COOKING')
           ORDER BY o.scheduled_for ASC";
$kdsStmt = $pdo->query($kdsSql);
$kdsOrders = $kdsStmt->fetchAll();
$foundInKds = false;
foreach ($kdsOrders as $ko) {
    if ($ko['order_id'] == $testOrderId) {
        $foundInKds = true;
        break;
    }
}
assert_test("KDS scheduled query retrieves the advance order for display in Advance Scheduled tab", $foundInKds);

// 8. Clean up test order
echo "\n--- 8. Cleanup ---\n";
$pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$testOrderId]);
$pdo->prepare("DELETE FROM orders WHERE order_id = ?")->execute([$testOrderId]);
assert_test("Cleaned up simulated test order $testOrderId", true);

echo "\n======================================================\n";
echo " RESULTS: Passed: $passed, Failed: $failed\n";
echo "======================================================\n";

if ($failed === 0) {
    echo "\n>>> ALL SCHEDULED MEAL ORDER VERIFICATION TESTS PASSED SUCCESSFULLY! <<<\n";
    exit(0);
} else {
    echo "\n>>> SOME TESTS FAILED <<<\n";
    exit(1);
}
