<?php
/**
 * =============================================================================
 * SHOPPING CART SERVICE
 * Course: MCSP-232 (MCA Major Project, IGNOU) | Student: Pooja JP
 * =============================================================================
 */

class Cart {
    public static function init(): void {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        if (!isset($_SESSION['applied_coupon'])) {
            $_SESSION['applied_coupon'] = null;
        }
    }

    public static function add(int $itemId, int $qty = 1): bool {
        self::init();
        if ($qty <= 0) return false;
        
        $item = MenuItem::getById($itemId);
        if (!$item || empty($item['is_available'])) {
            return false;
        }

        if (isset($_SESSION['cart'][$itemId])) {
            $_SESSION['cart'][$itemId] += $qty;
        } else {
            $_SESSION['cart'][$itemId] = $qty;
        }
        return true;
    }

    public static function update(int $itemId, int $qty): bool {
        self::init();
        if ($qty <= 0) {
            return self::remove($itemId);
        }
        $item = MenuItem::getById($itemId);
        if (!$item) return false;

        $_SESSION['cart'][$itemId] = $qty;
        return true;
    }

    public static function remove(int $itemId): bool {
        self::init();
        if (isset($_SESSION['cart'][$itemId])) {
            unset($_SESSION['cart'][$itemId]);
            return true;
        }
        return false;
    }

    public static function clear(): void {
        $_SESSION['cart'] = [];
        $_SESSION['applied_coupon'] = null;
    }

    public static function getRaw(): array {
        self::init();
        return $_SESSION['cart'];
    }

    public static function getItems(): array {
        self::init();
        $items = [];
        foreach ($_SESSION['cart'] as $itemId => $qty) {
            $item = MenuItem::getById((int)$itemId);
            if ($item) {
                $lineTotal = round($item['price'] * $qty, 2);
                $items[] = array_merge($item, [
                    'qty' => $qty,
                    'line_total' => $lineTotal
                ]);
            }
        }
        return $items;
    }

    public static function getItemCount(): int {
        self::init();
        return array_sum($_SESSION['cart']);
    }

    public static function getSubtotal(): float {
        $subtotal = 0.0;
        foreach (self::getItems() as $item) {
            $subtotal += $item['line_total'];
        }
        return round($subtotal, 2);
    }

    public static function getTax(): float {
        $subtotal = self::getSubtotal();
        return round($subtotal * GST_RATE, 2);
    }

    public static function getDeliveryFee(): float {
        $subtotal = self::getSubtotal();
        if ($subtotal == 0 || $subtotal >= FREE_DELIVERY_THRESHOLD) {
            return 0.0;
        }
        if (isset($_SESSION['applied_coupon']) && $_SESSION['applied_coupon']['code'] === 'WELCOME') {
            return 0.0;
        }
        return DEFAULT_DELIVERY_FEE;
    }

    public static function applyCoupon(string $code): array {
        self::init();
        $code = strtoupper(trim($code));
        $subtotal = self::getSubtotal();

        if ($subtotal <= 0) {
            return ['success' => false, 'message' => 'Cart is empty. Add delicious food to apply coupon.'];
        }

        switch ($code) {
            case 'LUSHFOREST':
                $discount = min(round($subtotal * 0.20, 2), 150.00); // 20% off up to ₹150
                $_SESSION['applied_coupon'] = [
                    'code' => 'LUSHFOREST',
                    'title' => '20% OFF (Lush Forest Edition)',
                    'discount' => $discount
                ];
                return ['success' => true, 'message' => "Coupon 'LUSHFOREST' applied! You saved " . format_price($discount)];

            case 'ZIPPY50':
                if ($subtotal < 299) {
                    return ['success' => false, 'message' => "Coupon 'ZIPPY50' requires a minimum order of ₹299."];
                }
                $discount = 50.00;
                $_SESSION['applied_coupon'] = [
                    'code' => 'ZIPPY50',
                    'title' => 'Flat ₹50 OFF',
                    'discount' => $discount
                ];
                return ['success' => true, 'message' => "Coupon 'ZIPPY50' applied! You saved ₹50."];

            case 'WELCOME':
                $_SESSION['applied_coupon'] = [
                    'code' => 'WELCOME',
                    'title' => 'Free Delivery Special',
                    'discount' => DEFAULT_DELIVERY_FEE
                ];
                return ['success' => true, 'message' => "Coupon 'WELCOME' applied! Delivery is on us."];

            default:
                return ['success' => false, 'message' => "Invalid coupon code '$code'. Try LUSHFOREST, ZIPPY50, or WELCOME."];
        }
    }

    public static function removeCoupon(): void {
        self::init();
        $_SESSION['applied_coupon'] = null;
    }

    public static function getDiscount(): float {
        self::init();
        if (!empty($_SESSION['applied_coupon']['discount'])) {
            return (float)$_SESSION['applied_coupon']['discount'];
        }
        return 0.0;
    }

    public static function getAppliedCoupon(): ?array {
        self::init();
        return $_SESSION['applied_coupon'];
    }

    public static function getTotal(): float {
        $subtotal = self::getSubtotal();
        if ($subtotal <= 0) return 0.0;
        $tax = self::getTax();
        $delivery = self::getDeliveryFee();
        $discount = self::getDiscount();
        $total = $subtotal + $tax + $delivery - $discount;
        return max(round($total, 2), 0.0);
    }

    public static function getDetails(): array {
        return [
            'items' => self::getItems(),
            'count' => self::getItemCount(),
            'subtotal' => self::getSubtotal(),
            'tax' => self::getTax(),
            'delivery_fee' => self::getDeliveryFee(),
            'discount' => self::getDiscount(),
            'coupon' => self::getAppliedCoupon(),
            'total' => self::getTotal(),
            'free_delivery_progress' => min(100, round((self::getSubtotal() / FREE_DELIVERY_THRESHOLD) * 100))
        ];
    }
}
