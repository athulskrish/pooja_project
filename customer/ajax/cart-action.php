<?php
/**
 * AJAX Endpoint: Synchronize or Update Cart
 * Returns JSON: {success: bool, cart: array, totals: array}
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$action = $_POST['action'] ?? $_GET['action'] ?? 'get';
$response = ['success' => false];

if ($action === 'sync') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (is_array($data)) {
        set_session_cart($data['cart'] ?? []);
        $coupon = $data['coupon'] ?? null;
        $totals = calculate_bill_totals(get_session_cart(), $coupon);
        $response = [
            'success' => true,
            'cart' => get_session_cart(),
            'totals' => $totals
        ];
    }
} elseif ($action === 'calculate') {
    $cart = get_session_cart();
    $coupon = $_GET['coupon'] ?? $_POST['coupon'] ?? null;
    $totals = calculate_bill_totals($cart, $coupon);
    $response = [
        'success' => true,
        'totals' => $totals
    ];
}

echo json_encode($response);
exit;
