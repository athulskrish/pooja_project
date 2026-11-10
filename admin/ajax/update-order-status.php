<?php
declare(strict_types=1);
/**
 * AJAX Endpoint: Update Order Status & Trigger Audit Trail
 * Centralized via transitionOrderStatus() in includes/functions.php
 * Returns JSON: {success: bool, order_id: int, new_status: string, ...}
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed. Use POST.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Authentication required.']);
    exit;
}

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

if (!verifyRequestCsrf($data)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired CSRF token.']);
    exit;
}

$orderId = (int)($data['order_id'] ?? $_POST['order_id'] ?? 0);
$newStatus = cleanInput($data['new_status'] ?? $_POST['new_status'] ?? '');
$riderName = cleanInput($data['rider_name'] ?? $_POST['rider_name'] ?? '');

$result = transitionOrderStatus($orderId, $newStatus, $riderName, isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
echo json_encode($result);
exit;
