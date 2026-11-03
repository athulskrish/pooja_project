<?php
/**
 * AJAX Endpoint: Update Order Status & Trigger Audit Trail
 * Centralized via transition_order_status() in includes/functions.php
 * Returns JSON: {success: bool, order_id: int, new_status: string, ...}
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

$result = transition_order_status($orderId, $newStatus, $riderName, $_SESSION['user_id'] ?? null);
echo json_encode($result);
exit;
