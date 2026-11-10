<?php
declare(strict_types=1);

/**
 * Order Model (ACID Transactions & Order Management - Database-Backed)
 * MCSP-232: E-Commerce Platform for Food Order
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/MenuItem.php';

class Order {
    /**
     * Create new order using ACID Transaction with concurrency control (row-level locking)
     */
    public static function createOrder(
        int $userId,
        int $addressId,
        array $cartItems,
        string $paymentMode = 'UPI',
        ?string $couponCode = null,
        ?string $notes = null
    ): array {
        if (empty($cartItems)) {
            return ['success' => false, 'message' => 'Cart is empty.'];
        }

        // Generate Order Number & Secret Delivery OTP
        $orderNumber = 'ZIP-' . random_int(100000, 999999);
        $deliveryOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $txnRef      = strtoupper($paymentMode) . '-TXN-' . time() . random_int(1000, 9999);

        // Calculate Totals
        $subtotal = 0.0;
        foreach ($cartItems as $item) {
            $subtotal += ((float)$item['price'] * (int)$item['qty']);
        }

        $taxAmount = round($subtotal * (GST_PERCENT / 100), 2);
        $deliveryFee = DELIVERY_FEE;
        $platformFee = PLATFORM_FEE;

        // Discount logic
        $discountAmount = 0.0;
        if (!empty($couponCode)) {
            $code = strtoupper(trim($couponCode));
            if ($code === 'LUSHFOREST') {
                $discountAmount = min(100.0, round($subtotal * 0.20, 2));
            } elseif ($code === 'ZIPPY50') {
                $discountAmount = min(100.0, round($subtotal * 0.50, 2));
            } elseif ($code === 'STEALDEAL' && $subtotal >= 349) {
                $discountAmount = 125.00;
            } elseif ($code === 'WELCOME20') {
                $discountAmount = min(80.0, round($subtotal * 0.20, 2));
            }
        }

        $totalAmount = max(0.0, $subtotal + $taxAmount + $deliveryFee + $platformFee - $discountAmount);

        $pdo = Database::getConnection();
        if (!$pdo) {
            return ['success' => false, 'message' => 'Database connection unavailable. Cannot create order.'];
        }

        try {
            $pdo->beginTransaction();

            // Pessimistic Concurrency Lock: Check & reserve stock
            foreach ($cartItems as $ci) {
                $stmtStock = $pdo->prepare("SELECT stock_quantity, is_available FROM menu_items WHERE item_id = ? FOR UPDATE");
                $stmtStock->execute([$ci['id']]);
                $stockRow = $stmtStock->fetch();
                if (!$stockRow || $stockRow['stock_quantity'] < (int)$ci['qty']) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => "Insufficient stock for item ID: {$ci['id']}"];
                }
            }

            // 1. Insert Order Header
            $stmtOrder = $pdo->prepare("
                INSERT INTO orders (order_number, user_id, address_id, subtotal, tax_amount, delivery_fee, platform_fee, discount_amount, total_amount, coupon_code, order_status, order_notes, placed_at, estimated_delivery_time)
                VALUES (:order_num, :user_id, :addr_id, :subtotal, :tax, :deliv, :platform, :discount, :total, :coupon, 'CONFIRMED', :notes, NOW(), NOW() + INTERVAL 25 MINUTE)
            ");
            $stmtOrder->execute([
                ':order_num' => $orderNumber,
                ':user_id'   => $userId,
                ':addr_id'   => $addressId,
                ':subtotal'  => $subtotal,
                ':tax'       => $taxAmount,
                ':deliv'     => $deliveryFee,
                ':platform'  => $platformFee,
                ':discount'  => $discountAmount,
                ':total'     => $totalAmount,
                ':coupon'    => $couponCode ?: null,
                ':notes'     => $notes ?: null
            ]);
            $orderId = (int)$pdo->lastInsertId();

            // 2. Insert Order Items & Deduct Stock
            $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, item_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
            $stmtDeduct = $pdo->prepare("UPDATE menu_items SET stock_quantity = stock_quantity - ? WHERE item_id = ?");

            foreach ($cartItems as $ci) {
                $qty = (int)$ci['qty'];
                $price = (float)$ci['price'];
                $lineTotal = round($qty * $price, 2);
                $stmtItem->execute([$orderId, $ci['id'], $qty, $price, $lineTotal]);
                $stmtDeduct->execute([$qty, $ci['id']]);
            }

            // 3. Insert Payment
            $stmtPay = $pdo->prepare("
                INSERT INTO payments (order_id, payment_mode, transaction_reference, amount_paid, payment_status, payment_timestamp)
                VALUES (?, ?, ?, ?, 'SUCCESSFUL', NOW())
            ");
            $stmtPay->execute([$orderId, $paymentMode, $txnRef, $totalAmount]);

            // 4. Insert Delivery Dispatch with 6-digit OTP
            $stmtDisp = $pdo->prepare("
                INSERT INTO delivery_dispatches (order_id, delivery_partner_name, delivery_partner_phone, delivery_otp, assigned_at, delivery_status)
                VALUES (?, 'Zippy Fleet Pool', '+91 96320 98765', ?, NOW(), 'ASSIGNED')
            ");
            $stmtDisp->execute([$orderId, $deliveryOtp]);

            // 5. Insert Audit Trail
            $stmtAudit = $pdo->prepare("INSERT INTO order_audit_trail (order_id, old_status, new_status, updated_by_user_id, change_timestamp) VALUES (?, NULL, 'CONFIRMED', ?, NOW())");
            $stmtAudit->execute([$orderId, $userId]);

            $pdo->commit();

            return [
                'success'      => true,
                'order_id'     => $orderId,
                'order_number' => $orderNumber,
                'delivery_otp' => $deliveryOtp,
                'total_amount' => $totalAmount,
                'status'       => 'CONFIRMED'
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()];
        }
    }

    public static function getByOrderNumber(string $orderNumber): ?array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("
                SELECT o.*, 
                       p.payment_mode, p.transaction_reference, p.amount_paid, p.payment_status,
                       d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp, d.delivery_status,
                       u.full_name, u.email, u.phone_number,
                       a.address_type, a.address_line, a.landmark, a.pincode
                FROM orders o
                LEFT JOIN payments p ON o.order_id = p.order_id
                LEFT JOIN delivery_dispatches d ON o.order_id = d.order_id
                LEFT JOIN users u ON o.user_id = u.user_id
                LEFT JOIN user_addresses a ON o.address_id = a.address_id
                WHERE o.order_number = ?
            ");
            $stmt->execute([$orderNumber]);
            $order = $stmt->fetch();
            if ($order) {
                $stmtItems = $pdo->prepare("
                    SELECT oi.*, m.item_name, m.is_veg, m.image_url, m.prep_time_minutes, m.kitchen_station
                    FROM order_items oi
                    JOIN menu_items m ON oi.item_id = m.item_id
                    WHERE oi.order_id = ?
                ");
                $stmtItems->execute([$order['order_id']]);
                $order['items'] = $stmtItems->fetchAll();
                return $order;
            }
        } catch (PDOException $e) {
            error_log("models/Order::getByOrderNumber error: " . $e->getMessage());
        }
        return null;
    }

    public static function getUserOrders(int $userId): array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }
        try {
            $stmt = $pdo->prepare("
                SELECT o.*, p.payment_mode, d.delivery_otp, d.delivery_partner_name 
                FROM orders o
                LEFT JOIN payments p ON o.order_id = p.order_id
                LEFT JOIN delivery_dispatches d ON o.order_id = d.order_id
                WHERE o.user_id = ?
                ORDER BY o.order_id DESC
            ");
            $stmt->execute([$userId]);
            $orders = $stmt->fetchAll();
            foreach ($orders as &$ord) {
                $stmtItems = $pdo->prepare("SELECT oi.*, m.item_name, m.is_veg FROM order_items oi JOIN menu_items m ON oi.item_id = m.item_id WHERE oi.order_id = ?");
                $stmtItems->execute([$ord['order_id']]);
                $ord['items'] = $stmtItems->fetchAll();
            }
            return $orders;
        } catch (PDOException $e) {
            error_log("models/Order::getUserOrders error: " . $e->getMessage());
            return [];
        }
    }

    public static function updateStatus(int $orderId, string $newStatus, int $userId = 2): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }
        try {
            $stmtOld = $pdo->prepare("SELECT order_status FROM orders WHERE order_id = ?");
            $stmtOld->execute([$orderId]);
            $oldStatus = $stmtOld->fetchColumn() ?: 'CONFIRMED';

            $stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
            $res = $stmt->execute([$newStatus, $orderId]);

            $stmtAudit = $pdo->prepare("INSERT INTO order_audit_trail (order_id, old_status, new_status, updated_by_user_id, change_timestamp) VALUES (?, ?, ?, ?, NOW())");
            $stmtAudit->execute([$orderId, $oldStatus, $newStatus, $userId]);

            return $res;
        } catch (PDOException $e) {
            error_log("models/Order::updateStatus error: " . $e->getMessage());
            return false;
        }
    }
}
