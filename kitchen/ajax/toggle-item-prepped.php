<?php
declare(strict_types=1);
/**
 * Kitchen AJAX API: Toggle Individual Dish Preparation Status (`order_items.is_prepped`)
 * Zippy - Online Food Ordering Platform
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isKitchen() && !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Kitchen role required.']);
    exit;
}

$raw = (string)file_get_contents('php://input');
$jsonBody = json_decode($raw, true);
if (!is_array($jsonBody)) {
    $jsonBody = [];
}

if (!verifyRequestCsrf($jsonBody)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF token.']);
    exit;
}

$orderItemId = (int)($_POST['order_item_id'] ?? $jsonBody['order_item_id'] ?? 0);
$isPrepped = (int)($_POST['is_prepped'] ?? $jsonBody['is_prepped'] ?? 0);

$res = OrderService::toggleOrderItemPrepped($orderItemId, $isPrepped);
echo json_encode([
    'success' => $res['ok'],
    'order_id' => $res['order_id'],
    'order_item_id' => $res['order_item_id'],
    'is_prepped' => $res['is_prepped'],
    'prepped_count' => $res['prepped_count'],
    'total_count' => $res['total_count'],
    'message' => $res['message'] ?? 'Prep state updated.',
]);
exit;
