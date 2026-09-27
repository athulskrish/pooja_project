<?php
/**
 * API: Kitchen Action & WSJF State Advance
 * MCSP-232: E-Commerce Platform for Food Order
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Order.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$orderId = (int)($input['order_id'] ?? 0);
$action  = $input['action'] ?? '';

if ($orderId <= 0 || empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$newStatus = match ($action) {
    'start_cooking' => 'COOKING',
    'mark_ready'    => 'READY',
    'dispatch'      => 'OUT_FOR_DELIVERY',
    'deliver'       => 'DELIVERED',
    default         => null
};

if (!$newStatus) {
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

$success = Order::updateStatus($orderId, $newStatus);

echo json_encode([
    'success'   => $success,
    'orderId'   => $orderId,
    'newStatus' => $newStatus,
    'message'   => "Order #{$orderId} status advanced to {$newStatus}"
]);
