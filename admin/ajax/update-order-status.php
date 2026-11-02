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
$data = json_decode($raw, true) ?: [];

$orderId = (int)($data['order_id'] ?? $_POST['order_id'] ?? 0);
$newStatus = clean_input($data['new_status'] ?? $_POST['new_status'] ?? '');
$riderName = clean_input($data['rider_name'] ?? $_POST['rider_name'] ?? '');

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

// If rider name provided, update dispatch record
if (!empty($riderName)) {
    db_query("UPDATE `delivery_dispatches` SET `delivery_partner_name` = :rname WHERE `order_id` = :oid", [
        ':rname' => $riderName,
        ':oid' => $orderId
    ]);
}

// Lifecycle transitions
if ($newStatus === 'OUT_FOR_DELIVERY') {
    db_query("
        UPDATE `delivery_dispatches` 
        SET `dispatched_at` = COALESCE(dispatched_at, NOW()), `delivery_status` = 'IN_TRANSIT' 
        WHERE `order_id` = :id
    ", [':id' => $orderId]);
} elseif ($newStatus === 'DELIVERED') {
    db_query("
        UPDATE `delivery_dispatches` 
        SET `delivered_at` = NOW(), `delivery_status` = 'DELIVERED' 
        WHERE `order_id` = :id
    ", [':id' => $orderId]);

    // If COD, mark payment as SUCCESSFUL & update money_transactions
    $payRec = db_fetch_one("SELECT * FROM `payments` WHERE `order_id` = :id", [':id' => $orderId]);
    if ($payRec && $payRec['payment_mode'] === 'COD') {
        db_query("UPDATE `payments` SET `payment_status` = 'SUCCESSFUL', `payment_timestamp` = NOW() WHERE `order_id` = :id", [':id' => $orderId]);
        db_query("
            UPDATE `money_transactions` 
            SET `status` = 'COMPLETED', `handled_by` = :hby, `notes` = CONCAT(COALESCE(notes, ''), ' [Cash collected upon delivery completion]') 
            WHERE `order_id` = :id AND `txn_category` = 'CUSTOMER_PAYMENT_COD'
        ", [
            ':hby' => 'Store Admin / Courier',
            ':id'  => $orderId
        ]);
    }
} elseif ($newStatus === 'CANCELLED') {
    // Restore inventory stock
    db_query("
        UPDATE `menu_items` m
        JOIN `order_items` oi ON m.item_id = oi.item_id
        SET m.stock_quantity = m.stock_quantity + oi.quantity,
            m.is_available = 1
        WHERE oi.order_id = :id
    ", [':id' => $orderId]);

    // Refund payment
    db_query("
        UPDATE `payments` 
        SET `payment_status` = 'REFUNDED', `payment_timestamp` = NOW() 
        WHERE `order_id` = :id
    ", [':id' => $orderId]);
}

// Insert audit trail
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
    'old_status' => $oldStatus,
    'new_status' => $newStatus,
    'badge_html' => get_status_badge($newStatus),
    'message' => "Order #ZIP-{$orderId} status changed to {$newStatus}"
]);
exit;
