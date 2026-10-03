<?php
/**
 * AJAX Endpoint: Live Order Status Poller
 * Returns JSON: {success: bool, order_id: int, status: string, eta_minutes: int, rider: array}
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit;
}

$order = db_fetch_one("
    SELECT o.order_id, o.order_status, o.placed_at, o.estimated_delivery_time,
           d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp, d.delivery_status
    FROM `orders` o
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    WHERE o.order_id = :id
", [':id' => $orderId]);

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'order_id' => (int)$order['order_id'],
    'status' => $order['order_status'],
    'badge_html' => get_status_badge($order['order_status']),
    'delivery_otp' => $order['delivery_otp'] ?? '',
    'rider' => [
        'name' => $order['delivery_partner_name'] ?: 'Zippy Express Rider',
        'phone' => $order['delivery_partner_phone'] ?: '+91 96320 98765',
        'status' => $order['delivery_status'] ?: 'ASSIGNED'
    ]
]);
exit;
