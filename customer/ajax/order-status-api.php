<?php
/**
 * AJAX Endpoint: Live Order Status Poller & Synchronizer
 * Returns JSON: {success: bool, order_id: int, status: string, badge_html: string, delivery_otp: string, rider: array, location: array}
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit;
}

$order = db_fetch_one("
    SELECT o.order_id, o.order_number, o.order_status, o.placed_at, o.estimated_delivery_time,
           u.full_name AS customer_name,
           d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp, d.delivery_status,
           d.current_latitude, d.current_longitude, d.delivery_latitude, d.delivery_longitude,
           d.route_progress, d.distance_remaining_km, d.estimated_minutes_remaining,
           p.payment_mode, p.payment_status
    FROM `orders` o
    JOIN `users` u ON o.user_id = u.user_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    WHERE o.order_id = :id
", [':id' => $orderId]);

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

$status = strtoupper($order['order_status']);
$stepIndex = 1;
switch ($status) {
    case 'PLACED':
    case 'CONFIRMED':
        $stepIndex = 0;
        break;
    case 'COOKING':
        $stepIndex = 1;
        break;
    case 'READY':
        $stepIndex = 2;
        break;
    case 'OUT_FOR_DELIVERY':
        $stepIndex = 2;
        break;
    case 'DELIVERED':
        $stepIndex = 3;
        break;
}

echo json_encode([
    'success' => true,
    'order_id' => (int)$order['order_id'],
    'order_number' => $order['order_number'] ?: '#ZIP-' . $order['order_id'],
    'status' => $status,
    'step_index' => $stepIndex,
    'badge_html' => get_status_badge($status),
    'delivery_otp' => $order['delivery_otp'] ?? '',
    'payment_status' => $order['payment_status'] ?? 'PENDING',
    'rider' => [
        'name' => $order['delivery_partner_name'] ?: 'Suresh Kumar (Zippy Express)',
        'phone' => $order['delivery_partner_phone'] ?: '+91 96320 98765',
        'status' => $order['delivery_status'] ?: 'ASSIGNED',
        'lat' => (float)($order['current_latitude'] ?: 12.9784),
        'lng' => (float)($order['current_longitude'] ?: 77.6408),
        'progress_pct' => (int)($order['route_progress'] ?: 0),
        'distance_km' => (float)($order['distance_remaining_km'] ?: 2.4),
        'eta_mins' => (int)($order['estimated_minutes_remaining'] ?: 12)
    ]
]);
exit;
