<?php
/**
 * First-Come, First-Served (FCFS / FIFO) Queue Engine with Dynamic SLA Escalation
 * Zippy - Online Food Ordering Platform
 * MCSP-232: Major Project
 * 
 * Enforces fair chronological restaurant queue ordering (FIFO) while computing
 * real-time SLA variances to surface overdue tickets and operational exceptions.
 */

require_once __DIR__ . '/functions.php';

class FCFSQueue {

    /**
     * Get active kitchen orders sorted strictly by First-Come, First-Served (FIFO)
     * with real-time SLA breach calculations and queue positioning.
     *
     * @param string|null $filterStatus Optional status filter (e.g., 'COOKING', 'CONFIRMED', 'READY')
     * @return array Chronologically sorted orders enriched with SLA metrics
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
            ORDER BY o.placed_at ASC, o.order_id ASC
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
            ORDER BY oi.order_item_id ASC
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

        // Compute FCFS positions, SLA variances, and badges
        $position = 1;
        foreach ($orders as &$order) {
            $elapsed = max(0, (int)$order['elapsed_minutes']);
            $maxPrep = max(5, (int)$order['max_prep_time']);
            $portions = max(1, (int)$order['total_portions']);

            // Estimated Job Duration in minutes
            $jobDuration = $maxPrep + ($portions * 1.5);
            $slaDiff = $elapsed - $maxPrep;

            $order['fcfs_position'] = $position++;
            $order['sla_diff'] = $slaDiff;
            $order['job_duration'] = round($jobDuration);
            $order['items'] = $itemsByOrder[$order['order_id']] ?? [];

            // Compute backward-compatible WSJF score
            $urgencyWeight = match($order['order_status']) {
                'PLACED', 'CONFIRMED' => 1.5,
                'COOKING' => 1.2,
                'READY' => 0.5,
                default => 1.0
            };
            $costOfDelay = max(1, $elapsed) * $urgencyWeight;
            $order['wsjf_score'] = round($costOfDelay / max(1, $jobDuration), 2);
            $order['cost_of_delay'] = round($costOfDelay, 1);

            // Compute SLA Breach Escalation status
            if ($order['order_status'] === 'READY') {
                $order['sla_status'] = 'READY';
                $order['card_sla_class'] = '';
                $order['sla_badge'] = [
                    'label' => 'Packed & Ready',
                    'class' => 'bg-success-subtle text-success border border-success-subtle',
                    'icon' => 'bi-box-seam'
                ];
            } elseif ($slaDiff > 15) {
                $order['sla_status'] = 'CRITICAL';
                $order['card_sla_class'] = 'sla-breach-critical';
                $order['sla_badge'] = [
                    'label' => '+' . $slaDiff . 'm CRITICAL',
                    'class' => 'bg-danger text-white border border-danger',
                    'icon' => 'bi-exclamation-octagon-fill'
                ];
            } elseif ($slaDiff > 0) {
                $order['sla_status'] = 'OVERDUE';
                $order['card_sla_class'] = 'sla-breach-overdue';
                $order['sla_badge'] = [
                    'label' => '+' . $slaDiff . 'm OVERDUE',
                    'class' => 'bg-warning-subtle text-warning-emphasis border border-warning',
                    'icon' => 'bi-alarm-fill'
                ];
            } elseif ($slaDiff >= -5) {
                $order['sla_status'] = 'APPROACHING';
                $order['card_sla_class'] = 'sla-approaching';
                $order['sla_badge'] = [
                    'label' => abs($slaDiff) . 'm left',
                    'class' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                    'icon' => 'bi-hourglass-split'
                ];
            } else {
                $order['sla_status'] = 'ON_TRACK';
                $order['card_sla_class'] = '';
                $order['sla_badge'] = [
                    'label' => 'On Track (' . abs($slaDiff) . 'm left)',
                    'class' => 'bg-success-subtle text-success border border-success-subtle',
                    'icon' => 'bi-check-circle-fill'
                ];
            }
        }
        unset($order);

        return $orders;
    }

    /**
     * Real-time Management by Exception (MBE) scanner.
     * Detects operational anomalies across:
     * - Overdue Cooking
     * - Stalled Unconfirmed Orders (> 5 mins)
     * - Packed Orders Awaiting Courier Dispatch (> 5 mins)
     * - Couriers Holding High COD Cash Balances (>= ₹3,000)
     * - Critical Low Stock Inventory (<= 5 portions)
     *
     * @return array List of operational exceptions requiring admin intervention
     */
    public static function getTriageExceptions(): array {
        $exceptions = [];

        // 1. Overdue Cooking Orders (elapsed > max prep time)
        $overdueCooking = db_fetch_all("
            SELECT * FROM (
                SELECT o.order_id, u.full_name,
                       TIMESTAMPDIFF(MINUTE, o.placed_at, NOW()) AS elapsed,
                       COALESCE(MAX(m.prep_time_minutes), 15) AS max_prep
                FROM `orders` o
                JOIN `users` u ON o.user_id = u.user_id
                JOIN `order_items` oi ON o.order_id = oi.order_id
                JOIN `menu_items` m ON oi.item_id = m.item_id
                WHERE o.order_status = 'COOKING'
                  AND (o.is_scheduled = 0 OR o.scheduled_for <= NOW())
                GROUP BY o.order_id, u.full_name
            ) sub
            WHERE elapsed > max_prep
            ORDER BY (elapsed - max_prep) DESC
            LIMIT 5
        ");
        foreach ($overdueCooking as $oc) {
            $delay = (int)$oc['elapsed'] - (int)$oc['max_prep'];
            $exceptions[] = [
                'severity' => ($delay > 15) ? 'CRITICAL' : 'WARNING',
                'type' => 'OVERDUE COOKING',
                'title' => "#ZIP-{$oc['order_id']} ({$oc['full_name']}) +{$delay}m overdue",
                'badge_class' => ($delay > 15) ? 'bg-danger text-white' : 'bg-warning text-dark',
                'icon' => 'bi-fire',
                'action_url' => ADMIN_URL . "/orders.php?status=COOKING&search=" . urlencode((string)$oc['order_id'])
            ];
        }

        // 2. Stalled Unconfirmed Orders (placed > 5 mins ago and still in PLACED or CONFIRMED)
        $stalledPrep = db_fetch_all("
            SELECT o.order_id, u.full_name,
                   TIMESTAMPDIFF(MINUTE, o.placed_at, NOW()) AS elapsed
            FROM `orders` o
            JOIN `users` u ON o.user_id = u.user_id
            WHERE o.order_status IN ('PLACED', 'CONFIRMED')
              AND (o.is_scheduled = 0 OR o.scheduled_for <= NOW())
              AND TIMESTAMPDIFF(MINUTE, o.placed_at, NOW()) >= 5
            ORDER BY elapsed DESC
            LIMIT 4
        ");
        foreach ($stalledPrep as $sp) {
            $exceptions[] = [
                'severity' => 'WARNING',
                'type' => 'AWAITING KITCHEN',
                'title' => "#ZIP-{$sp['order_id']} waiting {$sp['elapsed']}m to start cooking",
                'badge_class' => 'bg-warning text-dark',
                'icon' => 'bi-clock-history',
                'action_url' => ADMIN_URL . "/orders.php?status=PREPARING&search=" . urlencode((string)$sp['order_id'])
            ];
        }

        // 3. Stalled Ready Orders (Ready > 5 mins without being dispatched)
        $stalledReady = db_fetch_all("
            SELECT o.order_id, u.full_name,
                   TIMESTAMPDIFF(MINUTE, o.placed_at, NOW()) AS elapsed
            FROM `orders` o
            JOIN `users` u ON o.user_id = u.user_id
            WHERE o.order_status = 'READY'
              AND TIMESTAMPDIFF(MINUTE, o.placed_at, NOW()) >= 8
            ORDER BY elapsed DESC
            LIMIT 4
        ");
        foreach ($stalledReady as $sr) {
            $exceptions[] = [
                'severity' => 'INFO',
                'type' => 'AWAITING DISPATCH',
                'title' => "#ZIP-{$sr['order_id']} packed and ready for courier pickup",
                'badge_class' => 'bg-primary text-white',
                'icon' => 'bi-box-seam',
                'action_url' => ADMIN_URL . "/orders.php?status=READY&search=" . urlencode((string)$sr['order_id'])
            ];
        }

        // 4. Couriers Holding High COD Cash (>= ₹3,000)
        $highCodRiders = db_fetch_all("
            SELECT sp.profile_id, sp.cod_balance, u.full_name
            FROM `staff_profiles` sp
            JOIN `users` u ON sp.user_id = u.user_id
            WHERE sp.department = 'DELIVERY' AND sp.cod_balance >= 3000
            ORDER BY sp.cod_balance DESC
            LIMIT 3
        ");
        foreach ($highCodRiders as $hr) {
            $exceptions[] = [
                'severity' => 'WARNING',
                'type' => 'HIGH COD IN FLEET',
                'title' => "Rider {$hr['full_name']} holds " . format_inr($hr['cod_balance']) . " cash",
                'badge_class' => 'bg-warning text-dark',
                'icon' => 'bi-cash-stack',
                'action_url' => ADMIN_URL . "/delivery-view.php"
            ];
        }

        // 5. Critical Stockouts (<= 5 portions)
        $lowStockDishes = db_fetch_all("
            SELECT item_id, item_name, stock_quantity
            FROM `menu_items`
            WHERE stock_quantity <= 5 AND is_available = 1
            ORDER BY stock_quantity ASC
            LIMIT 4
        ");
        foreach ($lowStockDishes as $ls) {
            $exceptions[] = [
                'severity' => 'CRITICAL',
                'type' => 'STOCKOUT RISK',
                'title' => "{$ls['item_name']}: only {$ls['stock_quantity']} left",
                'badge_class' => 'bg-danger text-white',
                'icon' => 'bi-exclamation-triangle-fill',
                'action_url' => ADMIN_URL . "/dishes.php"
            ];
        }

        return $exceptions;
    }
}
