<?php
/**
 * API: Order Status Polling Endpoint
 * MCSP-232: E-Commerce Platform for Food Order
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Order.php';

$orderNumber = $_GET['order'] ?? '';

if (empty($orderNumber)) {
    echo json_encode(['success' => false, 'message' => 'Order number required']);
    exit;
}

$order = Order::getByOrderNumber($orderNumber);

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

// Calculate status step (1 = Placed, 2 = In Kitchen / Cooking, 3 = Out for Delivery, 4 = Delivered)
$status = strtoupper($order['order_status']);
$stepIndex = 1;
$statusText = 'Order Confirmed';
$progressPercent = 25;

switch ($status) {
    case 'PLACED':
    case 'CONFIRMED':
        $stepIndex = 1;
        $statusText = 'Order Confirmed & Sent to Kitchen';
        $progressPercent = 25;
        break;
    case 'COOKING':
        $stepIndex = 2;
        $statusText = 'Cooking in Kitchen (Handi Station)';
        $progressPercent = 50;
        break;
    case 'READY':
        $stepIndex = 2.5;
        $statusText = 'Food Packed & Ready for Courier';
        $progressPercent = 65;
        break;
    case 'OUT_FOR_DELIVERY':
        $stepIndex = 3;
        $statusText = 'Rider is on the Way with your Food';
        $progressPercent = 85;
        break;
    case 'DELIVERED':
        $stepIndex = 4;
        $statusText = 'Delivered & Handed Over';
        $progressPercent = 100;
        break;
}

echo json_encode([
    'success'         => true,
    'orderNumber'     => $order['order_number'],
    'status'          => $status,
    'statusText'      => $statusText,
    'stepIndex'       => $stepIndex,
    'progressPercent' => $progressPercent,
    'deliveryOtp'     => $order['delivery_otp'] ?? '839210',
    'riderName'       => $order['delivery_partner_name'] ?? 'Ramesh Kumar',
    'riderPhone'      => $order['delivery_partner_phone'] ?? '+91 98450 12345',
    'totalAmount'     => formatPrice($order['total_amount'] ?? 0),
]);
