<?php
/**
 * Shared Helper Functions & Business Logic
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Execute a query with parameters and return PDOStatement
 */
function db_query(string $sql, array $params = []): ?PDOStatement {
    $pdo = getDBConnection();
    if (!$pdo) {
        return null;
    }
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database Query Error: " . $e->getMessage() . " | SQL: " . $sql);
        return null;
    }
}

/**
 * Fetch all rows
 */
function db_fetch_all(string $sql, array $params = []): array {
    $stmt = db_query($sql, $params);
    return $stmt ? $stmt->fetchAll() : [];
}

/**
 * Fetch single row
 */
function db_fetch_one(string $sql, array $params = []): ?array {
    $stmt = db_query($sql, $params);
    $res = $stmt ? $stmt->fetch() : null;
    return $res ?: null;
}

/**
 * Fetch single scalar value
 */
function db_fetch_val(string $sql, array $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt ? $stmt->fetchColumn() : null;
}

/**
 * Get active coupon definitions
 */
function get_available_coupons(): array {
    return [
        'LUSHFOREST' => [
            'code' => 'LUSHFOREST',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'max_discount' => 100.00,
            'min_order' => 299.00,
            'description' => '20% OFF up to ₹100 on orders above ₹299 (Figma Lush Special)'
        ],
        'ZIPPY50' => [
            'code' => 'ZIPPY50',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'max_discount' => 120.00,
            'min_order' => 399.00,
            'description' => '50% OFF up to ₹120 on your order above ₹399'
        ],
        'WELCOME' => [
            'code' => 'WELCOME',
            'discount_type' => 'flat',
            'discount_value' => 50.00,
            'max_discount' => 50.00,
            'min_order' => 199.00,
            'description' => 'Flat ₹50 OFF for welcome customers on orders above ₹199'
        ]
    ];
}

/**
 * Calculate Cart Totals
 * 
 * @param array $cartItems Array of items with 'price' and 'qty'
 * @param string|null $couponCode
 * @return array
 */
function calculate_bill_totals(array $cartItems, ?string $couponCode = null): array {
    $subtotal = 0.00;
    $totalQty = 0;

    foreach ($cartItems as $item) {
        $price = (float)($item['price'] ?? 0);
        $qty = (int)($item['qty'] ?? 1);
        $subtotal += ($price * $qty);
        $totalQty += $qty;
    }

    // Delivery fee calculation
    $deliveryFee = ($subtotal >= FREE_DELIVERY_THRESHOLD || $subtotal == 0) ? 0.00 : DEFAULT_DELIVERY_FEE;
    
    // Platform fee
    $platformFee = ($subtotal > 0) ? PLATFORM_FEE : 0.00;

    // Coupon discount calculation
    $discount = 0.00;
    $couponApplied = false;
    $couponError = null;

    if (!empty($couponCode)) {
        $coupons = get_available_coupons();
        $code = strtoupper(trim($couponCode));
        if (isset($coupons[$code])) {
            $cp = $coupons[$code];
            if ($subtotal >= $cp['min_order']) {
                if ($cp['discount_type'] === 'percentage') {
                    $calc = ($subtotal * $cp['discount_value']) / 100;
                    $discount = min($calc, $cp['max_discount']);
                } else {
                    $discount = min($cp['discount_value'], $subtotal);
                }
                $couponApplied = true;
            } else {
                $couponError = "Minimum order of ₹{$cp['min_order']} required for coupon {$code}.";
            }
        } else {
            $couponError = "Invalid coupon code: {$code}.";
        }
    }

    // Tax calculation on discounted food subtotal (5% GST)
    $taxableSubtotal = max(0.00, $subtotal - $discount);
    $gstAmount = round(($taxableSubtotal * GST_PERCENT) / 100, 2);
    $cgst = round($gstAmount / 2, 2);
    $sgst = round($gstAmount / 2, 2);

    $finalTotal = max(0.00, $taxableSubtotal + $gstAmount + $deliveryFee + $platformFee);

    return [
        'subtotal' => round($subtotal, 2),
        'total_qty' => $totalQty,
        'discount' => round($discount, 2),
        'coupon_code' => $couponApplied ? strtoupper(trim($couponCode)) : null,
        'coupon_error' => $couponError,
        'gst_amount' => $gstAmount,
        'cgst' => $cgst,
        'sgst' => $sgst,
        'delivery_fee' => round($deliveryFee, 2),
        'platform_fee' => round($platformFee, 2),
        'final_total' => round($finalTotal, 2)
    ];
}

/**
 * Get active session cart array
 */
function get_session_cart(): array {
    return $_SESSION['cart'] ?? [];
}

/**
 * Save session cart array
 */
function set_session_cart(array $cart): void {
    $_SESSION['cart'] = $cart;
}

/**
 * Clear session cart
 */
function clear_session_cart(): void {
    unset($_SESSION['cart']);
    unset($_SESSION['applied_coupon']);
}
