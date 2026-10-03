<?php
/**
 * =============================================================================
 * ORDER SERVICE - ACID TRANSACTIONS & PESSIMISTIC CONCURRENCY CONTROL
 * Course: MCSP-232 (MCA Major Project, IGNOU) | Student: Pooja JP
 * =============================================================================
 */

class Order {
    /**
     * Compute Dynamic Delivery ETA via Multi-Factor Heuristic Formula
     * ETA = Buffer (10m) + Max(Prep Time) + (Active Orders * 2m) + Travel Time (12m)
     */
    public static function calculateEstimatedDeliveryMinutes(array $cartItems, int $activeKitchenOrders = 3): int {
        $bufferMinutes = 10;
        $transitMinutes = 12;
        $maxPrepMinutes = 15;

        foreach ($cartItems as $item) {
            $prep = (int)($item['prep_time_minutes'] ?? 15);
            if ($prep > $maxPrepMinutes) {
                $maxPrepMinutes = $prep;
            }
        }

        $concurrencyDelay = min($activeKitchenOrders * 2, 20);
        return $bufferMinutes + $maxPrepMinutes + $concurrencyDelay + $transitMinutes;
    }

    /**
     * Place Order with ACID Database Transaction and Row-Level Locking
     */
    public static function placeOrder(int $userId, int $addressId, string $paymentMode, ?string $notes = ''): array {
        $items = Cart::getItems();
        if (empty($items)) {
            return ['success' => false, 'message' => 'Cart is empty.'];
        }

        $subtotal = Cart::getSubtotal();
        $tax = Cart::getTax();
        $deliveryFee = Cart::getDeliveryFee();
        $discount = Cart::getDiscount();
        $total = Cart::getTotal();

        // Calculate Dynamic ETA
        $etaMinutes = self::calculateEstimatedDeliveryMinutes($items);
        $etaTimestamp = date('Y-m-d H:i:s', strtotime("+$etaMinutes minutes"));
        $otp = (string)random_int(1000, 9999);
        $txnRef = strtoupper($paymentMode) . '-TXN-' . date('YmdHis') . '-' . random_int(100, 999);

        $pdo = Database::getInstance();
        if ($pdo) {
            try {
                // START ACID TRANSACTION
                $pdo->beginTransaction();

                // PESSIMISTIC CONCURRENCY LOCKING: Check and lock stock for each item
                $lockStmt = $pdo->prepare("SELECT item_id, stock_quantity, item_name FROM menu_items WHERE item_id = ? FOR UPDATE");
                $updateStockStmt = $pdo->prepare("UPDATE menu_items SET stock_quantity = stock_quantity - ? WHERE item_id = ?");

                foreach ($items as $item) {
                    $lockStmt->execute([$item['item_id']]);
                    $row = $lockStmt->fetch();

                    if (!$row || $row['stock_quantity'] < $item['qty']) {
                        $dishTitle = $row ? $row['item_name'] : 'Dish';
                        throw new Exception("Stockout: '$dishTitle' is no longer available in the requested portion.");
                    }

                    // Decrement stock
                    $updateStockStmt->execute([$item['qty'], $item['item_id']]);
                }

                // Insert into `orders`
                $orderStmt = $pdo->prepare("INSERT INTO orders 
                    (user_id, address_id, subtotal, tax_amount, delivery_fee, discount_amount, total_amount, order_status, order_notes, placed_at, estimated_delivery_time) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'PLACED', ?, NOW(), ?)");
                $orderStmt->execute([
                    $userId, $addressId, $subtotal, $tax, $deliveryFee, $discount, $total, trim($notes ?? ''), $etaTimestamp
                ]);
                $orderId = (int)$pdo->lastInsertId();

                // Insert into `order_items`
                $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, item_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
                foreach ($items as $item) {
                    $itemStmt->execute([
                        $orderId, $item['item_id'], $item['qty'], $item['price'], $item['line_total']
                    ]);
                }

                // Insert into `payments`
                $payStatus = ($paymentMode === 'COD') ? 'PENDING' : 'SUCCESSFUL';
                $payStmt = $pdo->prepare("INSERT INTO payments (order_id, payment_mode, transaction_reference, amount_paid, payment_status, payment_timestamp) 
                                          VALUES (?, ?, ?, ?, ?, NOW())");
                $payStmt->execute([$orderId, $paymentMode, $txnRef, $total, $payStatus]);

                // Insert into `delivery_dispatches`
                $dispatchStmt = $pdo->prepare("INSERT INTO delivery_dispatches 
                    (order_id, delivery_partner_name, delivery_partner_phone, delivery_otp, assigned_at, delivery_status) 
                    VALUES (?, 'Rajesh Kumar', '+91 9876543213', ?, NOW(), 'ASSIGNED')");
                $dispatchStmt->execute([$orderId, $otp]);

                // Insert into `order_audit_trail`
                $auditStmt = $pdo->prepare("INSERT INTO order_audit_trail (order_id, old_status, new_status, updated_by_user_id, change_timestamp) 
                                            VALUES (?, NULL, 'PLACED', ?, NOW())");
                $auditStmt->execute([$orderId, $userId]);

                // COMMIT TRANSACTION
                $pdo->commit();

                // Clear cart after successful checkout
                Cart::clear();

                return [
                    'success' => true,
                    'order_id' => $orderId,
                    'total' => $total,
                    'eta_minutes' => $etaMinutes,
                    'delivery_otp' => $otp,
                    'txn_ref' => $txnRef
                ];
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return ['success' => false, 'message' => 'Checkout failed: ' . $e->getMessage()];
            }
        }

        // Mock Fallback Session Storage
        $orderId = random_int(1010, 9999);
        if (!isset($_SESSION['mock_orders'])) {
            $_SESSION['mock_orders'] = [];
        }

        $newOrder = [
            'order_id' => $orderId,
            'user_id' => $userId,
            'address_id' => $addressId,
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'delivery_fee' => $deliveryFee,
            'discount_amount' => $discount,
            'total_amount' => $total,
            'order_status' => 'PLACED',
            'order_notes' => $notes,
            'placed_at' => date('Y-m-d H:i:s'),
            'estimated_delivery_time' => $etaTimestamp,
            'delivery_otp' => $otp,
            'payment_mode' => $paymentMode,
            'transaction_reference' => $txnRef,
            'delivery_partner_name' => 'Rajesh Kumar',
            'delivery_partner_phone' => '+91 9876543213',
            'items' => $items
        ];

        $_SESSION['mock_orders'][$orderId] = $newOrder;
        Cart::clear();

        return [
            'success' => true,
            'order_id' => $orderId,
            'total' => $total,
            'eta_minutes' => $etaMinutes,
            'delivery_otp' => $otp,
            'txn_ref' => $txnRef
        ];
    }

    /**
     * Get orders list for a customer
     */
    public static function getUserOrders(int $userId): array {
        $pdo = Database::getInstance();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT o.*, 
                                              p.payment_mode, p.payment_status, 
                                              d.delivery_partner_name, d.delivery_otp,
                                              a.address_line, a.address_type,
                                              COUNT(oi.order_item_id) as total_items_count
                                       FROM orders o 
                                       LEFT JOIN payments p ON o.order_id = p.order_id
                                       LEFT JOIN delivery_dispatches d ON o.order_id = d.order_id
                                       LEFT JOIN user_addresses a ON o.address_id = a.address_id
                                       LEFT JOIN order_items oi ON o.order_id = oi.order_id
                                       WHERE o.user_id = ?
                                       GROUP BY o.order_id
                                       ORDER BY o.placed_at DESC");
                $stmt->execute([$userId]);
                $orders = $stmt->fetchAll();
                
                // Fetch items for each order
                foreach ($orders as &$ord) {
                    $itemStmt = $pdo->prepare("SELECT oi.*, m.item_name, m.is_veg, m.image_url 
                                               FROM order_items oi 
                                               JOIN menu_items m ON oi.item_id = m.item_id 
                                               WHERE oi.order_id = ?");
                    $itemStmt->execute([$ord['order_id']]);
                    $ord['items'] = $itemStmt->fetchAll();
                }
                return $orders;
            } catch (PDOException $e) {
                // fallback
            }
        }

        // Return mock orders
        $mock = [
            [
                'order_id' => 1001,
                'user_id' => $userId,
                'subtotal' => 650.00,
                'tax_amount' => 32.50,
                'delivery_fee' => 40.00,
                'discount_amount' => 50.00,
                'total_amount' => 672.50,
                'order_status' => 'OUT_FOR_DELIVERY',
                'order_notes' => 'Extra raita please',
                'placed_at' => date('Y-m-d H:i:s', strtotime('-25 minutes')),
                'estimated_delivery_time' => date('Y-m-d H:i:s', strtotime('+15 minutes')),
                'payment_mode' => 'UPI',
                'payment_status' => 'SUCCESSFUL',
                'delivery_partner_name' => 'Rajesh Kumar',
                'delivery_partner_phone' => '+91 9876543213',
                'delivery_otp' => '7482',
                'address_line' => 'Flat 402, Green Glen Layout, 3rd Cross, Bellandur',
                'items' => [
                    ['item_name' => 'Meghana Special Chicken Biryani', 'quantity' => 1, 'unit_price' => 330.00, 'line_total' => 330.00, 'is_veg' => 0],
                    ['item_name' => 'Chicken 65 Andhra Classic', 'quantity' => 1, 'unit_price' => 260.00, 'line_total' => 260.00, 'is_veg' => 0],
                    ['item_name' => 'Butter Garlic Naan', 'quantity' => 1, 'unit_price' => 65.00, 'line_total' => 65.00, 'is_veg' => 1]
                ]
            ],
            [
                'order_id' => 1002,
                'user_id' => $userId,
                'subtotal' => 385.00,
                'tax_amount' => 19.25,
                'delivery_fee' => 0.00,
                'discount_amount' => 0.00,
                'total_amount' => 404.25,
                'order_status' => 'DELIVERED',
                'order_notes' => 'Ring bell once',
                'placed_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'estimated_delivery_time' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'payment_mode' => 'CREDIT_CARD',
                'payment_status' => 'SUCCESSFUL',
                'delivery_partner_name' => 'Suresh Nair',
                'delivery_partner_phone' => '+91 9876543215',
                'delivery_otp' => '3195',
                'address_line' => 'Flat 402, Green Glen Layout, 3rd Cross, Bellandur',
                'items' => [
                    ['item_name' => 'Murgh Makhani (Butter Chicken)', 'quantity' => 1, 'unit_price' => 320.00, 'line_total' => 320.00, 'is_veg' => 0],
                    ['item_name' => 'Butter Garlic Naan', 'quantity' => 1, 'unit_price' => 65.00, 'line_total' => 65.00, 'is_veg' => 1]
                ]
            ]
        ];

        if (!empty($_SESSION['mock_orders'])) {
            foreach ($_SESSION['mock_orders'] as $mo) {
                array_unshift($mock, $mo);
            }
        }

        return $mock;
    }

    /**
     * Get single order details
     */
    public static function getOrderDetails(int $orderId, ?int $userId = null): ?array {
        $pdo = Database::getInstance();
        if ($pdo) {
            try {
                $sql = "SELECT o.*, 
                               u.full_name as customer_name, u.email as customer_email, u.phone_number as customer_phone,
                               p.payment_mode, p.payment_status, p.transaction_reference,
                               d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp, d.delivery_status,
                               a.address_line, a.landmark, a.pincode, a.address_type
                        FROM orders o 
                        JOIN users u ON o.user_id = u.user_id
                        LEFT JOIN payments p ON o.order_id = p.order_id
                        LEFT JOIN delivery_dispatches d ON o.order_id = d.order_id
                        LEFT JOIN user_addresses a ON o.address_id = a.address_id
                        WHERE o.order_id = ?";
                $params = [$orderId];
                if ($userId !== null) {
                    $sql .= " AND o.user_id = ?";
                    $params[] = $userId;
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $order = $stmt->fetch();

                if ($order) {
                    $itemStmt = $pdo->prepare("SELECT oi.*, m.item_name, m.is_veg, m.image_url 
                                               FROM order_items oi 
                                               JOIN menu_items m ON oi.item_id = m.item_id 
                                               WHERE oi.order_id = ?");
                    $itemStmt->execute([$orderId]);
                    $order['items'] = $itemStmt->fetchAll();
                    return $order;
                }
            } catch (PDOException $e) {
                // fallback
            }
        }

        // Check mock orders
        $orders = self::getUserOrders($userId ?? 1);
        foreach ($orders as $o) {
            if ($o['order_id'] == $orderId) {
                return array_merge($o, [
                    'customer_name' => $_SESSION['user_name'] ?? 'Pooja JP',
                    'customer_email' => $_SESSION['user_email'] ?? 'pooja@zippy.test',
                    'customer_phone' => $_SESSION['user_phone'] ?? '+91 9876543210',
                    'transaction_reference' => $o['transaction_reference'] ?? 'TXN-' . $orderId,
                    'landmark' => 'Near Sobha Silicon Oasis',
                    'pincode' => '560103'
                ]);
            }
        }
        return null;
    }

    /**
     * Cancel Order within 60-second grace window
     */
    public static function cancelOrder(int $orderId, int $userId): array {
        $pdo = Database::getInstance();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT order_id, order_status, placed_at, TIMESTAMPDIFF(SECOND, placed_at, NOW()) as elapsed_seconds 
                                       FROM orders WHERE order_id = ? AND user_id = ?");
                $stmt->execute([$orderId, $userId]);
                $order = $stmt->fetch();

                if (!$order) {
                    return ['success' => false, 'message' => 'Order not found.'];
                }

                if ($order['order_status'] !== 'PLACED') {
                    return ['success' => false, 'message' => 'Order cannot be cancelled after the kitchen begins food preparation.'];
                }

                if ($order['elapsed_seconds'] > 60) {
                    return ['success' => false, 'message' => 'Grace cancellation window (60s) has expired.'];
                }

                $pdo->beginTransaction();

                // Restore stock
                $itemStmt = $pdo->prepare("SELECT item_id, quantity FROM order_items WHERE order_id = ?");
                $itemStmt->execute([$orderId]);
                $items = $itemStmt->fetchAll();

                $restoreStmt = $pdo->prepare("UPDATE menu_items SET stock_quantity = stock_quantity + ? WHERE item_id = ?");
                foreach ($items as $it) {
                    $restoreStmt->execute([$it['quantity'], $it['item_id']]);
                }

                // Update status
                $updateStmt = $pdo->prepare("UPDATE orders SET order_status = 'CANCELLED' WHERE order_id = ?");
                $updateStmt->execute([$orderId]);

                // Refund payment status
                $payStmt = $pdo->prepare("UPDATE payments SET payment_status = 'REFUNDED' WHERE order_id = ?");
                $payStmt->execute([$orderId]);

                // Audit log
                $auditStmt = $pdo->prepare("INSERT INTO order_audit_trail (order_id, old_status, new_status, updated_by_user_id) VALUES (?, 'PLACED', 'CANCELLED', ?)");
                $auditStmt->execute([$orderId, $userId]);

                $pdo->commit();
                return ['success' => true, 'message' => 'Order cancelled successfully. 100% refund initiated to source payment method.'];
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                return ['success' => false, 'message' => 'Cancellation failed: ' . $e->getMessage()];
            }
        }

        return ['success' => true, 'message' => 'Order cancelled successfully. (Demo simulated)'];
    }
}
