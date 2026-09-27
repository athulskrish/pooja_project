<?php
/**
 * API: Cart Calculations & Session Synchronization
 * MCSP-232: E-Commerce Platform for Food Order
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$cartItems = $input['cart'] ?? $_SESSION['cart'] ?? [];
$couponCode = strtoupper(trim($input['coupon'] ?? ''));

$subtotal = 0.0;
$itemsCount = 0;

foreach ($cartItems as $item) {
    $qty = (int)($item['qty'] ?? 1);
    $price = (float)($item['price'] ?? 0);
    $subtotal += ($price * $qty);
    $itemsCount += $qty;
}

$taxAmount = round($subtotal * (GST_PERCENT / 100), 2);
$deliveryFee = $subtotal > 0 ? DELIVERY_FEE : 0.0;
$platformFee = $subtotal > 0 ? PLATFORM_FEE : 0.0;

$discountAmount = 0.0;
$discountMsg = '';

if (!empty($couponCode) && $subtotal > 0) {
    if ($couponCode === 'LUSHFOREST') {
        $discountAmount = min(100.0, round($subtotal * 0.20, 2));
        $discountMsg = '20% Lush Forest discount applied!';
    } elseif ($couponCode === 'ZIPPY50') {
        $discountAmount = min(100.0, round($subtotal * 0.50, 2));
        $discountMsg = '50% First Order discount applied!';
    } elseif ($couponCode === 'STEALDEAL') {
        if ($subtotal >= 349) {
            $discountAmount = 125.00;
            $discountMsg = 'Flat ₹125 STEALDEAL discount applied!';
        } else {
            $discountMsg = 'STEALDEAL requires minimum order of ₹349.';
        }
    } elseif ($couponCode === 'WELCOME20') {
        $discountAmount = min(80.0, round($subtotal * 0.20, 2));
        $discountMsg = '₹80 Welcome discount applied!';
    } else {
        $discountMsg = 'Invalid promo coupon code.';
    }
}

$finalTotal = max(0.0, round($subtotal + $taxAmount + $deliveryFee + $platformFee - $discountAmount, 2));

// Update PHP session
$_SESSION['cart'] = $cartItems;

echo json_encode([
    'success'         => true,
    'itemsCount'      => $itemsCount,
    'subtotal'        => $subtotal,
    'formattedSubtotal' => formatPrice($subtotal),
    'taxAmount'       => $taxAmount,
    'formattedTax'    => formatPrice($taxAmount),
    'deliveryFee'     => $deliveryFee,
    'formattedDelivery' => formatPrice($deliveryFee),
    'platformFee'     => $platformFee,
    'formattedPlatform' => formatPrice($platformFee),
    'discountAmount'  => $discountAmount,
    'formattedDiscount' => formatPrice($discountAmount),
    'discountMessage' => $discountMsg,
    'finalTotal'      => $finalTotal,
    'formattedTotal'  => formatPrice($finalTotal),
]);
