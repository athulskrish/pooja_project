<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$orders = db_fetch_all("
    SELECT o.order_id, o.order_status, o.placed_at, o.ready_at,
           d.rider_id, d.delivery_partner_name, d.delivery_status, d.dispatched_at, d.delivered_at
    FROM `orders` o 
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id 
    ORDER BY o.order_id DESC 
    LIMIT 20
");

foreach ($orders as $o) {
    echo sprintf(
        "#%-4d | Status: %-16s | Ready: %-19s | Rider: %-10s (ID: %s) | DispStatus: %-11s | Delivered: %s\n",
        $o['order_id'],
        $o['order_status'],
        $o['ready_at'] ?? 'NULL',
        $o['delivery_partner_name'] ?? 'None',
        $o['rider_id'] ?? 'NULL',
        $o['delivery_status'] ?? 'None',
        $o['delivered_at'] ?? 'NULL'
    );
}

echo "\n--- RIDERS STATUS ---\n";
$riders = get_all_delivery_riders();
foreach ($riders as $r) {
    echo sprintf(
        "Rider: %-10s (ID: %d) | Duty: %-7s | Busy: %-5s | ActiveOrder: %s\n",
        $r['full_name'],
        $r['user_id'],
        $r['duty_status'],
        $r['is_busy'] ? 'YES' : 'NO',
        $r['active_order_id'] ?? 'None'
    );
}
