<?php
/**
 * =============================================================================
 * COUPON REST API ENDPOINT
 * =============================================================================
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;

$action = $data['action'] ?? 'apply';
$code = $data['code'] ?? '';

if ($action === 'apply') {
    $result = Cart::applyCoupon($code);
    $result['cart'] = Cart::getDetails();
    echo json_encode($result);
} elseif ($action === 'remove') {
    Cart::removeCoupon();
    echo json_encode(['success' => true, 'message' => 'Coupon removed.', 'cart' => Cart::getDetails()]);
} else {
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
