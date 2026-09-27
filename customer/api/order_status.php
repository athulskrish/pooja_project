<?php
/**
 * =============================================================================
 * LIVE ORDER STATUS API ENDPOINT
 * =============================================================================
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

$order = Order::getOrderDetails($orderId);
if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

// Compute remaining ETA minutes
$placedTime = strtotime($order['placed_at']);
$elapsedMinutes = max(0, round((time() - $placedTime) / 60));
$totalEta = 35; // typical baseline
$remainingEta = max(5, $totalEta - $elapsedMinutes);

echo json_encode([
    'success' => true,
    'order_id' => $order['order_id'],
    'order_status' => $order['order_status'],
    'remaining_eta_minutes' => $remainingEta,
    'delivery_otp' => $order['delivery_otp'] ?? '7482',
    'delivery_partner' => [
        'name' => $order['delivery_partner_name'] ?? 'Rajesh Kumar',
        'phone' => $order['delivery_partner_phone'] ?? '+91 9876543213',
        'rating' => '4.9'
    ]
]);
