<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

echo "=================================================================\n";
echo "TEST: COMPREHENSIVE FCFS ALLOCATION & KDS STATE LIFECYCLE\n";
echo "=================================================================\n\n";

// Ensure order 1004 is OUT_FOR_DELIVERY for setup so Shaji starts as BUSY
db_query("UPDATE `orders` SET `order_status` = 'OUT_FOR_DELIVERY' WHERE `order_id` = 1004");
db_query("UPDATE `delivery_dispatches` SET `delivery_status` = 'IN_TRANSIT', `delivered_at` = NULL WHERE `order_id` = 1004");

echo "1. VERIFY ALL 3 RIDERS ARE CURRENTLY BUSY:\n";
$allRiders = get_all_delivery_riders();
foreach ($allRiders as $r) {
    echo "  - {$r['full_name']} (ID: {$r['user_id']}): Busy = " . ($r['is_busy'] ? 'YES' : 'NO') . " (Active: {$r['active_order_id']})\n";
    assert($r['is_busy'] === true, "Rider {$r['full_name']} should be busy");
}

echo "\n2. RUN FCFS ALLOCATION WHILE RIDERS ARE BUSY:\n";
$allocRes = auto_allocate_ready_orders_fcfs();
echo "  Allocation response: " . json_encode($allocRes) . "\n";

$readyParcels = db_fetch_all("
    SELECT o.order_id, o.ready_at, d.rider_id, d.delivery_partner_name, d.delivery_status
    FROM orders o
    LEFT JOIN delivery_dispatches d ON o.order_id = d.order_id
    WHERE o.order_status = 'READY'
    ORDER BY COALESCE(o.ready_at, o.placed_at) ASC, o.order_id ASC
");

echo "  Ready Parcels in DB:\n";
foreach ($readyParcels as $idx => $p) {
    echo sprintf(
        "    FCFS Rank #%d | Order #%-4d | ReadyAt: %s | RiderID: %s | Status: %s\n",
        $idx + 1,
        $p['order_id'],
        $p['ready_at'],
        $p['rider_id'] ?? 'NULL (Unassigned)',
        $p['delivery_status'] ?? 'NULL'
    );
    assert(empty($p['rider_id']), "Order #{$p['order_id']} must have NULL rider because all riders are busy!");
}
echo "  [PASS] All ready parcels have rider_id = NULL because all riders are busy!\n";

$kdsHashInitial = get_kds_state_hash();
echo "\n3. KDS STATE HASH (Initial): {$kdsHashInitial}\n";

echo "\n4. SIMULATE SHAJI DELIVERING ORDER #1004 VIA OTP HANDSHAKE:\n";
$transRes = transition_order_status(1004, 'DELIVERED', 'Shaji (Rider)', 6);
echo "  Delivered Order #1004 result: " . ($transRes['success'] ? 'SUCCESS' : 'FAILED') . "\n";

$kdsHashAfterDelivery = get_kds_state_hash();
echo "  KDS STATE HASH (After Delivery): {$kdsHashAfterDelivery}\n";
echo "  Did KDS hash change? " . ($kdsHashInitial !== $kdsHashAfterDelivery ? "YES (KDS Auto-Reload Triggered!)" : "NO") . "\n";
assert($kdsHashInitial !== $kdsHashAfterDelivery, "State hash MUST change when an order is delivered!");

echo "\n5. VERIFY SHAJI IS NOW FREE AND SYSTEM ALLOCATED FIRST PREPARED PARCEL:\n";
$shajiActive = get_driver_active_delivery(6);
echo "  Shaji Active Delivery: " . ($shajiActive ? "#ZIP-{$shajiActive['order_id']}" : "NONE (FREE)") . "\n";
assert($shajiActive === null, "Shaji must now be free");

$shajiAllocatedOrder = get_driver_allocated_order(6);
echo "  Shaji Allocated Parcel: " . ($shajiAllocatedOrder ? "#ZIP-{$shajiAllocatedOrder['order_id']} (Prepared at {$shajiAllocatedOrder['ready_at']})" : "NONE") . "\n";
assert($shajiAllocatedOrder !== null, "Shaji must be allocated a parcel");
assert((int)$shajiAllocatedOrder['order_id'] === 1001, "Order #1001 was prepared first, so it must be allocated to Shaji!");

echo "\n6. VERIFY REMAINING READY ORDERS QUEUE:\n";
$readyParcelsAfter = db_fetch_all("
    SELECT o.order_id, o.ready_at, d.rider_id, d.delivery_partner_name, d.delivery_status
    FROM orders o
    LEFT JOIN delivery_dispatches d ON o.order_id = d.order_id
    WHERE o.order_status = 'READY'
    ORDER BY COALESCE(o.ready_at, o.placed_at) ASC, o.order_id ASC
");
foreach ($readyParcelsAfter as $idx => $p) {
    echo sprintf(
        "    FCFS Rank #%d | Order #%-4d | ReadyAt: %s | Rider: %s (ID: %s)\n",
        $idx + 1,
        $p['order_id'],
        $p['ready_at'],
        $p['delivery_partner_name'] ?? 'Unassigned',
        $p['rider_id'] ?? 'NULL'
    );
}

// Exactly Order 1001 is assigned to Shaji (ID 6)
assert((int)$readyParcelsAfter[0]['order_id'] === 1001 && (int)$readyParcelsAfter[0]['rider_id'] === 6, "Rank 1 parcel #1001 must belong to Shaji");
// Ranks 2-6 must have rider_id = NULL
for ($i = 1; $i < count($readyParcelsAfter); $i++) {
    assert(empty($readyParcelsAfter[$i]['rider_id']), "Rank " . ($i + 1) . " parcel #{$readyParcelsAfter[$i]['order_id']} must have NULL rider");
}

echo "\n7. SIMULATE SHAJI CLAIMING & PICKING UP ALLOCATED PARCEL #1001:\n";
// Shaji claims order 1001
$claimRes = transition_order_status(1001, 'OUT_FOR_DELIVERY', 'Shaji (Rider)', 6);
echo "  Claim Order #1001 result: " . ($claimRes['success'] ? 'SUCCESS' : 'FAILED') . "\n";

$kdsHashAfterClaim = get_kds_state_hash();
echo "  KDS STATE HASH (After Claim): {$kdsHashAfterClaim}\n";
echo "  Did KDS hash change? " . ($kdsHashAfterDelivery !== $kdsHashAfterClaim ? "YES (KDS Auto-Reload Triggered!)" : "NO") . "\n";
assert($kdsHashAfterDelivery !== $kdsHashAfterClaim, "State hash MUST change when an order is claimed!");

$shajiActiveNow = get_driver_active_delivery(6);
echo "  Shaji Active Delivery Now: " . ($shajiActiveNow ? "#ZIP-{$shajiActiveNow['order_id']}" : "NONE") . "\n";
assert($shajiActiveNow !== null && (int)$shajiActiveNow['order_id'] === 1001, "Shaji must now be actively delivering #1001");

// Shaji is busy again, so allocated ready parcels should be 0
$shajiNewAlloc = get_driver_allocated_order(6);
echo "  Shaji Allocated Ready Parcel (When Busy): " . ($shajiNewAlloc ? "#ZIP-{$shajiNewAlloc['order_id']}" : "NONE (1 Driver 1 Delivery Policy Enforced)") . "\n";
assert($shajiNewAlloc === null, "Busy driver must NOT have any allocated ready orders!");

echo "\n=================================================================\n";
echo "ALL TESTS PASSED! FCFS SYSTEM ALLOCATION & KDS SYNC FULLY VERIFIED!\n";
echo "=================================================================\n";
