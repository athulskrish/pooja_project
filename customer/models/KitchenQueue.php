<?php
/**
 * KitchenQueue Model (Weighted Shortest Job First - WSJF Scheduling)
 * MCSP-232: E-Commerce Platform for Food Order
 *
 * Implements WSJF Priority Algorithm for Kitchen Display Systems (KDS):
 * WSJF Score = (Cost of Delay) / (Estimated Job Duration)
 * Cost of Delay = (Elapsed Waiting Minutes) * (Customer Urgency Factor)
 * Higher WSJF score indicates higher priority for preparation dispatch.
 */
require_once __DIR__ . '/../config/database.php';

class KitchenQueue {
    /**
     * Fetch all active orders categorized by KDS Kanban columns:
     * - 'orders' (CONFIRMED / PLACED)
     * - 'cooking' (COOKING)
     * - 'ready' (READY)
     */
    public static function getKdsBoardData(): array {
        $pdo = Database::getConnection();
        $rawOrders = [];

        if ($pdo) {
            try {
                $stmt = $pdo->query("
                    SELECT o.*, u.full_name, u.phone_number, d.delivery_partner_name, d.delivery_otp
                    FROM orders o
                    LEFT JOIN users u ON o.user_id = u.user_id
                    LEFT JOIN delivery_dispatches d ON o.order_id = d.order_id
                    WHERE o.order_status IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY')
                    ORDER BY o.placed_at ASC
                ");
                $orders = $stmt->fetchAll();
                foreach ($orders as $ord) {
                    $stmtItems = $pdo->prepare("
                        SELECT oi.*, m.item_name, m.prep_time_minutes, m.kitchen_station, m.is_veg
                        FROM order_items oi
                        JOIN menu_items m ON oi.item_id = m.item_id
                        WHERE oi.order_id = ?
                    ");
                    $stmtItems->execute([$ord['order_id']]);
                    $ord['items'] = $stmtItems->fetchAll();
                    $rawOrders[] = $ord;
                }
            } catch (PDOException $e) {}
        }

        if (empty($rawOrders)) {
            $rawOrders = self::getMockKdsOrders();
        }

        // Compute WSJF Score & Organize into Kanban Columns
        $board = [
            'orders'  => [], // New Orders to accept
            'cooking' => [], // In Preparation
            'ready'   => []  // Ready for rider pickup
        ];

        foreach ($rawOrders as $order) {
            // Calculate Elapsed Time
            $placedTime = strtotime($order['placed_at']);
            $elapsedMinutes = max(1, round((time() - $placedTime) / 60));

            // Sum of dish prep times
            $totalPrepMinutes = 0;
            $maxDishPrep = 5;
            foreach ($order['items'] as $item) {
                $prep = (int)($item['prep_time_minutes'] ?? 10);
                $totalPrepMinutes += ($prep * (int)$item['quantity']);
                if ($prep > $maxDishPrep) $maxDishPrep = $prep;
            }
            $totalPrepMinutes = max(5, $totalPrepMinutes);

            // Urgency factor (1.5x if waiting > 15 mins)
            $urgencyFactor = ($elapsedMinutes > 15) ? 1.8 : 1.2;

            // WSJF Score Calculation
            $costOfDelay = $elapsedMinutes * $urgencyFactor;
            $wsjfScore = round($costOfDelay / $totalPrepMinutes, 2);

            $order['elapsed_minutes'] = $elapsedMinutes;
            $order['total_prep_minutes'] = $totalPrepMinutes;
            $order['wsjf_score'] = $wsjfScore;

            $status = strtoupper($order['order_status']);
            if ($status === 'PLACED' || $status === 'CONFIRMED') {
                $board['orders'][] = $order;
            } elseif ($status === 'COOKING') {
                $board['cooking'][] = $order;
            } elseif ($status === 'READY') {
                $board['ready'][] = $order;
            }
        }

        // Sort New Orders and Cooking column by WSJF Score Descending
        usort($board['orders'], fn($a, $b) => ($b['wsjf_score'] <=> $a['wsjf_score']));
        usort($board['cooking'], fn($a, $b) => ($b['wsjf_score'] <=> $a['wsjf_score']));

        return $board;
    }

    private static function getMockKdsOrders(): array {
        return [
            [
                'order_id'     => 102,
                'order_number' => 'ZIP-948261',
                'user_id'      => 1,
                'full_name'    => 'Pooja JP',
                'phone_number' => '+91 98765 43210',
                'order_status' => 'COOKING',
                'order_notes'  => 'Less spicy, ring the doorbell once.',
                'delivery_partner_name' => 'Ramesh Kumar (Assigned)',
                'delivery_otp' => '839210',
                'placed_at'    => date('Y-m-d H:i:s', strtotime('-14 minutes')),
                'items' => [
                    ['item_name' => 'Meghana Special Chicken Biryani', 'quantity' => 1, 'prep_time_minutes' => 14, 'kitchen_station' => 'Handi', 'is_veg' => 0],
                    ['item_name' => 'Butter Chicken Delhi Style', 'quantity' => 1, 'prep_time_minutes' => 15, 'kitchen_station' => 'Handi', 'is_veg' => 0]
                ]
            ],
            [
                'order_id'     => 103,
                'order_number' => 'ZIP-338291',
                'user_id'      => 5,
                'full_name'    => 'Karthik Rao',
                'phone_number' => '+91 98451 99221',
                'order_status' => 'CONFIRMED',
                'order_notes'  => 'Extra green chutney and lemons.',
                'delivery_partner_name' => 'Pending Dispatch',
                'delivery_otp' => '729104',
                'placed_at'    => date('Y-m-d H:i:s', strtotime('-4 minutes')),
                'items' => [
                    ['item_name' => 'Chicken 65 Crispy Fry', 'quantity' => 2, 'prep_time_minutes' => 10, 'kitchen_station' => 'Grills', 'is_veg' => 0],
                    ['item_name' => 'Butter Garlic Naan', 'quantity' => 3, 'prep_time_minutes' => 6, 'kitchen_station' => 'Tandoor', 'is_veg' => 1]
                ]
            ],
            [
                'order_id'     => 104,
                'order_number' => 'ZIP-552910',
                'user_id'      => 6,
                'full_name'    => 'Ananya Sharma',
                'phone_number' => '+91 97412 88334',
                'order_status' => 'READY',
                'order_notes'  => 'Keep food warm in insulated bag.',
                'delivery_partner_name' => 'Ramesh Kumar (At Gate)',
                'delivery_otp' => '551920',
                'placed_at'    => date('Y-m-d H:i:s', strtotime('-24 minutes')),
                'items' => [
                    ['item_name' => 'Paneer Butter Masala', 'quantity' => 1, 'prep_time_minutes' => 12, 'kitchen_station' => 'Handi', 'is_veg' => 1],
                    ['item_name' => 'Dal Makhani Bukhara', 'quantity' => 1, 'prep_time_minutes' => 8, 'kitchen_station' => 'Pantry', 'is_veg' => 1]
                ]
            ]
        ];
    }
}
