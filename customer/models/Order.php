<?php
/**
 * Order Model (ACID Transactions & Order Management)
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
        $orderNumber = 'ZIP-' . rand(100000, 999999);
        $deliveryOtp = str_pad((string)rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $txnRef      = strtoupper($paymentMode) . '-TXN-' . time() . rand(1000, 9999);

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
        if ($pdo) {
            try {
                // Begin ACID Transaction
                $pdo->beginTransaction();

                // Pessimistic Concurrency Lock: Check & reserve stock
                foreach ($cartItems as $ci) {
                    $stmtStock = $pdo->prepare("SELECT stock_quantity, is_available FROM menu_items WHERE item_id = ? FOR UPDATE");
                    $stmtStock->execute([$ci['id']]);
                    $stockRow = $stmtStock->fetch();
                    if ($stockRow && $stockRow['stock_quantity'] < (int)$ci['qty']) {
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
                    VALUES (?, 'Ramesh Kumar', '+91 98450 12345', ?, NOW(), 'ASSIGNED')
                ");
                $stmtDisp->execute([$orderId, $deliveryOtp]);

                // 5. Insert Audit Trail
                $stmtAudit = $pdo->prepare("INSERT INTO order_audit_trail (order_id, old_status, new_status, updated_by_user_id, change_timestamp) VALUES (?, NULL, 'CONFIRMED', ?, NOW())");
                $stmtAudit->execute([$orderId, $userId]);

                // Commit Transaction
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

        // Offline / Mock fallback order creation
        $orderId = rand(103, 999);
        $mockOrder = [
            'order_id'     => $orderId,
            'order_number' => $orderNumber,
            'user_id'      => $userId,
            'address_id'   => $addressId,
            'subtotal'     => $subtotal,
            'tax_amount'   => $taxAmount,
            'delivery_fee' => $deliveryFee,
            'platform_fee' => $platformFee,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'coupon_code'  => $couponCode,
            'order_status' => 'CONFIRMED',
            'order_notes'  => $notes,
            'delivery_otp' => $deliveryOtp,
            'payment_mode' => $paymentMode,
            'txn_ref'      => $txnRef,
            'items'        => $cartItems,
            'placed_at'    => date('Y-m-d H:i:s'),
            'eta_minutes'  => 22
        ];

        // Store in session mock orders
        if (!isset($_SESSION['mock_orders'])) {
            $_SESSION['mock_orders'] = [];
        }
        $_SESSION['mock_orders'][$orderNumber] = $mockOrder;

        return [
            'success'      => true,
            'order_id'     => $orderId,
            'order_number' => $orderNumber,
            'delivery_otp' => $deliveryOtp,
            'total_amount' => $totalAmount,
            'status'       => 'CONFIRMED'
        ];
    }

    public static function getByOrderNumber(string $orderNumber): ?array {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("
                    SELECT o.*, 
                           p.payment_mode, p.transaction_reference, p.amount_paid, p.payment_status,
                           d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp, d.delivery_status,
                           u.full_name, u.email, u.phone_number,
                           a.address_type, a.title AS address_title, a.address_line, a.landmark, a.pincode
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
                    // Fetch line items
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
            } catch (PDOException $e) {}
        }

        // Check session mock orders
        if (isset($_SESSION['mock_orders'][$orderNumber])) {
            return $_SESSION['mock_orders'][$orderNumber];
        }

        // Default demo order #ZIP-948261 / #ZIP-849201
        return self::getMockOrder($orderNumber);
    }

    public static function getUserOrders(int $userId): array {
        $pdo = Database::getConnection();
        if ($pdo) {
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
                if (!empty($orders)) {
                    foreach ($orders as &$ord) {
                        $stmtItems = $pdo->prepare("SELECT oi.*, m.item_name, m.is_veg FROM order_items oi JOIN menu_items m ON oi.item_id = m.item_id WHERE oi.order_id = ?");
                        $stmtItems->execute([$ord['order_id']]);
                        $ord['items'] = $stmtItems->fetchAll();
                    }
                    return $orders;
                }
            } catch (PDOException $e) {}
        }

        // Return session mock orders + default demo orders
        $list = [];
        if (!empty($_SESSION['mock_orders'])) {
            foreach ($_SESSION['mock_orders'] as $mo) {
                if ($mo['user_id'] == $userId) {
                    $list[] = $mo;
                }
            }
        }

        $list[] = [
            'order_id'     => 102,
            'order_number' => 'ZIP-948261',
            'user_id'      => 1,
            'subtotal'     => 620.00,
            'tax_amount'   => 31.00,
            'delivery_fee' => 35.00,
            'platform_fee' => 5.00,
            'discount_amount' => 68.00,
            'total_amount' => 623.00,
            'coupon_code'  => 'LUSHFOREST',
            'order_status' => 'COOKING',
            'delivery_otp' => '839210',
            'placed_at'    => date('Y-m-d H:i:s', strtotime('-12 minutes')),
            'items'        => [
                ['item_name' => 'Meghana Special Chicken Biryani', 'quantity' => 1, 'unit_price' => 340.00, 'is_veg' => 0],
                ['item_name' => 'Butter Chicken Delhi Style', 'quantity' => 1, 'unit_price' => 320.00, 'is_veg' => 0]
            ]
        ];

        $list[] = [
            'order_id'     => 101,
            'order_number' => 'ZIP-849201',
            'user_id'      => 1,
            'subtotal'     => 405.00,
            'tax_amount'   => 20.25,
            'delivery_fee' => 35.00,
            'platform_fee' => 5.00,
            'discount_amount' => 0.00,
            'total_amount' => 465.25,
            'coupon_code'  => null,
            'order_status' => 'DELIVERED',
            'delivery_otp' => '482910',
            'placed_at'    => date('Y-m-d H:i:s', strtotime('-2 days')),
            'items'        => [
                ['item_name' => 'Meghana Special Chicken Biryani', 'quantity' => 1, 'unit_price' => 340.00, 'is_veg' => 0],
                ['item_name' => 'Butter Garlic Naan', 'quantity' => 1, 'unit_price' => 65.00, 'is_veg' => 1]
            ]
        ];

        return $list;
    }

    public static function updateStatus(int $orderId, string $newStatus, int $userId = 2): bool {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmtOld = $pdo->prepare("SELECT order_status FROM orders WHERE order_id = ?");
                $stmtOld->execute([$orderId]);
                $oldStatus = $stmtOld->fetchColumn() ?: 'CONFIRMED';

                $stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
                $res = $stmt->execute([$newStatus, $orderId]);

                // Insert into audit trail
                $stmtAudit = $pdo->prepare("INSERT INTO order_audit_trail (order_id, old_status, new_status, updated_by_user_id, change_timestamp) VALUES (?, ?, ?, ?, NOW())");
                $stmtAudit->execute([$orderId, $oldStatus, $newStatus, $userId]);

                return $res;
            } catch (PDOException $e) {}
        }

        // Mock update in session
        if (!empty($_SESSION['mock_orders'])) {
            foreach ($_SESSION['mock_orders'] as &$mo) {
                if ($mo['order_id'] == $orderId) {
                    $mo['order_status'] = $newStatus;
                    return true;
                }
            }
        }
        return true;
    }

    private static function getMockOrder(string $orderNumber): array {
        return [
            'order_id'                => 102,
            'order_number'            => $orderNumber ?: 'ZIP-948261',
            'user_id'                 => 1,
            'full_name'               => 'Pooja JP',
            'email'                   => 'pooja.mca2026@ignou.ac.in',
            'phone_number'            => '+91 98765 43210',
            'address_title'           => 'Indiranagar Flat',
            'address_line'            => '#402, Green Meadows Apartment, 100 Feet Road, Indiranagar, Bengaluru',
            'landmark'                => 'Near Metro Pillar 84',
            'pincode'                 => '560038',
            'subtotal'                => 620.00,
            'tax_amount'              => 31.00,
            'delivery_fee'            => 35.00,
            'platform_fee'            => 5.00,
            'discount_amount'         => 68.00,
            'total_amount'            => 623.00,
            'coupon_code'             => 'LUSHFOREST',
            'order_status'            => 'COOKING',
            'order_notes'             => 'Less spicy, ring the doorbell once.',
            'placed_at'               => date('Y-m-d H:i:s', strtotime('-12 minutes')),
            'estimated_delivery_time' => date('Y-m-d H:i:s', strtotime('+15 minutes')),
            'payment_mode'            => 'UPI',
            'transaction_reference'   => 'UPI-TXN-773910284912',
            'amount_paid'             => 623.00,
            'delivery_partner_name'   => 'Ramesh Kumar',
            'delivery_partner_phone'  => '+91 98450 12345',
            'delivery_otp'            => '839210',
            'items' => [
                [
                    'item_id' => 1,
                    'item_name' => 'Meghana Special Chicken Biryani',
                    'quantity' => 1,
                    'unit_price' => 340.00,
                    'line_total' => 340.00,
                    'is_veg' => 0,
                    'kitchen_station' => 'Handi'
                ],
                [
                    'item_id' => 7,
                    'item_name' => 'Butter Chicken Delhi Style',
                    'quantity' => 1,
                    'unit_price' => 320.00,
                    'line_total' => 320.00,
                    'is_veg' => 0,
                    'kitchen_station' => 'Handi'
                ]
            ]
        ];
    }
}
