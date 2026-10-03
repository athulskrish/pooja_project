<?php
/**
 * AJAX Endpoint: Update Order Status & Trigger Audit Trail
 * Returns JSON: {success: bool, order_id: int, new_status: string}
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!is_admin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$orderId = (int)($data['order_id'] ?? $_POST['order_id'] ?? 0);
$newStatus = clean_input($data['new_status'] ?? $_POST['new_status'] ?? '');

if ($orderId <= 0 || empty($newStatus)) {
    echo json_encode(['success' => false, 'error' => 'Missing order ID or status']);
    exit;
}

$cur = db_fetch_one("SELECT order_status FROM `orders` WHERE `order_id` = :id", [':id' => $orderId]);
if (!$cur) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

$oldStatus = $cur['order_status'];

db_query("UPDATE `orders` SET `order_status` = :st WHERE `order_id` = :id", [
    ':st' => $newStatus,
    ':id' => $orderId
]);

db_query("
    INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`) 
    VALUES (:oid, :old, :new, :uid, NOW())
", [
    ':oid' => $orderId,
    ':old' => $oldStatus,
    ':new' => $newStatus,
    ':uid' => $_SESSION['user_id'] ?? null
]);

echo json_encode([
    'success' => true,
    'order_id' => $orderId,
    'new_status' => $newStatus,
    'badge_html' => get_status_badge($newStatus)
]);
exit;
