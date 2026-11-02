<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$orders = db_fetch_all("
    SELECT o.*, 
           COUNT(oi.order_item_id) AS total_items_count,
           d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp, d.delivery_status, d.delivered_at,
           p.payment_mode, p.payment_status, p.transaction_reference, p.amount_paid,
           ua.address_type, ua.address_line, ua.landmark, ua.pincode,
           r.food_rating, r.rider_rating, r.comments AS review_comments
    FROM `orders` o
    LEFT JOIN `order_items` oi ON o.order_id = oi.order_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    LEFT JOIN `user_addresses` ua ON o.address_id = ua.address_id
    LEFT JOIN `order_reviews` r ON o.order_id = r.order_id
    WHERE o.user_id = :uid
    GROUP BY o.order_id, d.dispatch_id, p.payment_id, ua.address_id, r.review_id
    ORDER BY o.placed_at DESC
", [':uid' => 2]);

// Fetch items
$orderIds = array_column($orders, 'order_id');
$itemsGrouped = [];
if (!empty($orderIds)) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemsRaw = db_fetch_all("
        SELECT oi.*, m.item_name, m.is_veg, m.price as current_price
        FROM `order_items` oi
        JOIN `menu_items` m ON oi.item_id = m.item_id
        WHERE oi.order_id IN ($placeholders)
    ", $orderIds);
    foreach ($itemsRaw as $item) {
        $itemsGrouped[$item['order_id']][] = $item;
    }
}

// Separate into tabs
$allOrders = $orders;
$ongoingOrders = array_filter($orders, fn($o) => in_array($o['order_status'], ['PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY']));
$completedOrders = array_filter($orders, fn($o) => in_array($o['order_status'], ['DELIVERED']));
$cancelledOrders = array_filter($orders, fn($o) => in_array($o['order_status'], ['CANCELLED']));

echo "Total: " . count($allOrders) . PHP_EOL;
echo "Ongoing: " . count($ongoingOrders) . PHP_EOL;
echo "Completed: " . count($completedOrders) . PHP_EOL;
echo "Cancelled: " . count($cancelledOrders) . PHP_EOL;
