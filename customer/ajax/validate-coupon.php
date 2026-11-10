<?php
declare(strict_types=1);
/**
 * Customer AJAX API: Validate Dynamic Database-Backed Promo Coupon
 * Zippy - Online Food Ordering Platform
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$code = strtoupper(trim((string)($_REQUEST['code'] ?? '')));
$subtotal = max(0.0, (float)($_REQUEST['subtotal'] ?? 0));
$currentUser = getCurrentUser();
$userId = $currentUser ? (int)$currentUser['id'] : null;

if ($code === '') {
    echo json_encode([
        'success' => false,
        'valid' => false,
        'discount' => 0.00,
        'error' => 'Please enter a coupon code.'
    ]);
    exit;
}

$res = validateCouponForSubtotal($code, $subtotal, $userId);
echo json_encode([
    'success' => $res['valid'],
    'valid' => $res['valid'],
    'code' => $code,
    'discount' => $res['discount'],
    'error' => $res['error'],
    'coupon' => $res['coupon'],
]);
exit;
