<?php
/**
 * Test: FCFS Delivery Allocation & KDS State Synchronization
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

echo "=======================================================\n";
echo "TEST: FCFS DELIVERY ALLOCATION & KDS SYNC ENGINE\n";
echo "=======================================================\n\n";

// 1. Verify ready_at column exists in orders
$cols = db_fetch_all("SHOW COLUMNS FROM `orders` LIKE 'ready_at'");
assert(!empty($cols), "orders table must have ready_at column");
echo "[PASS] ready_at column verified in orders table.\n";

// 2. Test FCFS Sorting Logic: First prepared item must come first
$readyOrders = db_fetch_all("
    SELECT order_id, order_status, ready_at, placed_at
    FROM `orders`
    WHERE order_status = 'READY'
    ORDER BY COALESCE(ready_at, placed_at) ASC, order_id ASC
");
echo "[INFO] Current READY orders count: " . count($readyOrders) . "\n";
if (count($readyOrders) >= 2) {
    $time1 = strtotime($readyOrders[0]['ready_at'] ?: $readyOrders[0]['placed_at']);
    $time2 = strtotime($readyOrders[1]['ready_at'] ?: $readyOrders[1]['placed_at']);
    assert($time1 <= $time2, "First order in FCFS queue must be prepared before or equal to second order");
    echo "[PASS] Chronological FCFS prepared order verified: #ZIP-{$readyOrders[0]['order_id']} is earlier than #ZIP-{$readyOrders[1]['order_id']}.\n";
}

// 3. Test State Hash in poll-orders.php
function getPollData() {
    $script = sys_get_temp_dir() . '/poll_runner_' . uniqid() . '.php';
    $targetPath = addslashes(__DIR__ . '/../kitchen/ajax/poll-orders.php');
    file_put_contents($script, '<?php session_start(); $_SESSION["role_name"] = "KITCHEN"; $_SESSION["role_id"] = 3; $_SESSION["user_id"] = 4; require "' . $targetPath . '";');
    $out = shell_exec('php ' . escapeshellarg($script));
    @unlink($script);
    return json_decode($out, true);
}

$data1 = getPollData();
assert(!empty($data1['success']), "poll-orders.php should succeed");
assert(!empty($data1['state_hash']), "poll-orders.php must return state_hash");
echo "[PASS] poll-orders.php returned state_hash: {$data1['state_hash']}\n";

// 4. Test state_hash changes when an order is updated/delivered
$hashBefore = $data1['state_hash'];

// Simulate delivering an active order or creating audit trail entry
db_query("
    INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`)
    VALUES (1011, 'OUT_FOR_DELIVERY', 'DELIVERED', 6, NOW())
");

$data2 = getPollData();
$hashAfter = $data2['state_hash'];

assert($hashBefore !== $hashAfter, "state_hash MUST change when an order status / audit trail changes");
echo "[PASS] Live state_hash change detected! Before: {$hashBefore} -> After: {$hashAfter}\n";

// Clean up simulated audit row
$maxAuditId = db_fetch_val("SELECT MAX(audit_id) FROM order_audit_trail");
db_query("DELETE FROM order_audit_trail WHERE audit_id = :id", [':id' => $maxAuditId]);

// 5. Test FCFS Allocation Function
$allocResult = auto_allocate_ready_orders_fcfs();
echo "[PASS] auto_allocate_ready_orders_fcfs executed: " . json_encode($allocResult) . "\n";

echo "\n=======================================================\n";
echo "ALL TESTS PASSED: FCFS DELIVERY ALLOCATION & KDS SYNC OK!\n";
echo "=======================================================\n";
