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
                        SELECT oi.*, m.item_name, m.prep_time_minutes, m.is_veg
                        FROM order_items oi
                        JOIN menu_items m ON oi.item_id = m.item_id
                        WHERE oi.order_id = ?
                    ");
                    $stmtItems->execute([$ord['order_id']]);
                    $ord['items'] = $stmtItems->fetchAll();
                    $rawOrders[] = $ord;
                }
            } catch (PDOException $e) {
                error_log("KitchenQueue::getKdsBoardData error: " . $e->getMessage());
            }
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

            // Urgency factor (1.8x if waiting > 15 mins)
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
}
