<?php
/**
 * Weighted Shortest Job First (WSJF) Kitchen Priority Scheduling
 * Dynamic Priority Calculation for Kitchen Display System (KDS)
 * Student: Pooja JP | MCSP-232
 */

require_once __DIR__ . '/functions.php';

class WSJFQueue {

    /**
     * Compute prioritized kitchen queue
     * 
     * @param string|null $filterStatus Optional filter ('CONFIRMED', 'COOKING', etc.)
     * @return array Orders sorted by descending WSJF priority score
     */
    public static function getPrioritizedOrders(?string $filterStatus = null): array {
        $statusClause = $filterStatus 
            ? "AND o.order_status = :status" 
            : "AND o.order_status IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY')";

        $sql = "
            SELECT 
                o.*,
                u.full_name AS customer_name,
                u.phone_number AS customer_phone,
                TIMESTAMPDIFF(MINUTE, o.placed_at, NOW()) AS elapsed_minutes,
                COALESCE(MAX(m.prep_time_minutes), 15) AS max_prep_time,
                COUNT(oi.order_item_id) AS item_count,
                SUM(oi.quantity) AS total_portions
            FROM `orders` o
            JOIN `users` u ON o.user_id = u.user_id
            JOIN `order_items` oi ON o.order_id = oi.order_id
            JOIN `menu_items` m ON oi.item_id = m.item_id
            WHERE 1=1 $statusClause
            GROUP BY o.order_id
        ";

        $params = $filterStatus ? [':status' => $filterStatus] : [];
        $orders = db_fetch_all($sql, $params);

        if (empty($orders)) {
            return [];
        }

        // Fetch line items for each order
        $orderIds = array_column($orders, 'order_id');
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        
        $itemSql = "
            SELECT oi.*, m.item_name, m.is_veg, m.prep_time_minutes
            FROM `order_items` oi
            JOIN `menu_items` m ON oi.item_id = m.item_id
            WHERE oi.order_id IN ($placeholders)
        ";
        
        $pdo = getDBConnection();
        $itemsByOrder = [];
        if ($pdo) {
            $itemStmt = $pdo->prepare($itemSql);
            $itemStmt->execute($orderIds);
            while ($row = $itemStmt->fetch()) {
                $itemsByOrder[$row['order_id']][] = $row;
            }
        }

        // Calculate WSJF score for each order
        foreach ($orders as &$order) {
            $elapsed = max(1, (int)$order['elapsed_minutes']);
            $maxPrep = max(5, (int)$order['max_prep_time']);
            $portions = max(1, (int)$order['total_portions']);

            // Estimated Job Duration in minutes
            $jobDuration = $maxPrep + ($portions * 1.5);

            // Cost of Delay (CoD): customer waiting urgency increases non-linearly
            $urgencyWeight = match($order['order_status']) {
                'PLACED', 'CONFIRMED' => 1.5,
                'COOKING' => 1.2,
                'READY' => 0.5,
                default => 1.0
            };

            $costOfDelay = $elapsed * $urgencyWeight;
            $wsjfScore = round($costOfDelay / $jobDuration, 2);

            $order['job_duration'] = round($jobDuration);
            $order['cost_of_delay'] = round($costOfDelay, 1);
            $order['wsjf_score'] = $wsjfScore;
            $order['items'] = $itemsByOrder[$order['order_id']] ?? [];

            // Urgency tag
            if ($wsjfScore >= 2.0 || $elapsed > 20) {
                $order['urgency_badge'] = ['label' => 'CRITICAL DELAY', 'class' => 'bg-danger'];
            } elseif ($wsjfScore >= 1.0 || $elapsed > 10) {
                $order['urgency_badge'] = ['label' => 'HIGH PRIORITY', 'class' => 'bg-warning text-dark'];
            } else {
                $order['urgency_badge'] = ['label' => 'NORMAL', 'class' => 'bg-success'];
            }
        }
        unset($order);

        // Sort descending by WSJF score
        usort($orders, function($a, $b) {
            return $b['wsjf_score'] <=> $a['wsjf_score'];
        });

        return $orders;
    }
}
