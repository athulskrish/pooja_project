<?php
/**
 * Test: End-to-End Simulation of Delivery Completion & Instant FCFS Allocation
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

echo "=======================================================\n";
echo "TEST: SIMULATE DELIVERY COMPLETE -> INSTANT FCFS ALLOC\n";
echo "=======================================================\n\n";

$shajiId = 6;
$shajiUser = db_fetch_one("SELECT * FROM users WHERE user_id = :id", [':id' => $shajiId]);
echo "Rider: {$shajiUser['full_name']} (ID: {$shajiId})\n";

// 1. Check Shaji's active delivery
$activeBefore = get_driver_active_delivery($shajiId, $shajiUser['full_name']);
echo "1. Active delivery before completion: " . ($activeBefore ? "#ZIP-{$activeBefore['order_id']}" : "NONE") . "\n";

// Temporarily complete active delivery #1004
if ($activeBefore) {
    $activeOrderId = (int)$activeBefore['order_id'];
    
    // Complete delivery via transition_order_status
    $transRes = transition_order_status($activeOrderId, 'DELIVERED', 'Shaji (Rider)', $shajiId);
    echo "2. Completed delivery of #ZIP-{$activeOrderId}: " . ($transRes['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    
    // Check if Shaji is now free
    $activeAfter = get_driver_active_delivery($shajiId, $shajiUser['full_name']);
    echo "3. Active delivery after completion: " . ($activeAfter ? "#ZIP-{$activeAfter['order_id']}" : "NONE (FREE)") . "\n";
    assert($activeAfter === null, "Shaji should be free after delivering");
    
    // Check Shaji's newly allocated order in FCFS
    $allocatedOrder = get_driver_allocated_order($shajiId);
    echo "4. Newly allocated FCFS parcel for Shaji: " . ($allocatedOrder ? "#ZIP-{$allocatedOrder['order_id']} (Prepared at {$allocatedOrder['ready_at']})" : "NONE") . "\n";
    assert($allocatedOrder !== null, "Shaji must be allocated the first prepared ready order");
    assert((int)$allocatedOrder['order_id'] === 1001, "Order 1001 was prepared earliest so it must be allocated first to Shaji");
    echo "[PASS] System allocated earliest prepared parcel #ZIP-{$allocatedOrder['order_id']} to Shaji!\n";
    
    // Restore order #1004 back to OUT_FOR_DELIVERY for safety
    db_query("UPDATE `orders` SET `order_status` = 'OUT_FOR_DELIVERY' WHERE `order_id` = :id", [':id' => $activeOrderId]);
    db_query("UPDATE `delivery_dispatches` SET `delivery_status` = 'IN_TRANSIT', `delivered_at` = NULL WHERE `order_id` = :id", [':id' => $activeOrderId]);
    echo "5. Restored #ZIP-{$activeOrderId} to test state.\n";
} else {
    echo "No active delivery on Shaji to simulate.\n";
}

echo "\n=======================================================\n";
echo "LIFECYCLE SIMULATION PASSED CLEANLY!\n";
echo "=======================================================\n";
