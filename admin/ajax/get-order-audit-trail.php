<?php
/**
 * AJAX Endpoint: Retrieve Complete Lifecycle Audit Trail for an Order
 * Zippy - Online Food Ordering Platform
 * MCSP-232: Major Project | Student: Pooja JP
 * Implements: UC-22 (Audit System Logs & Trails - Order Timeline Drill-down)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Administrator access required.']);
    exit;
}

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Please provide a valid Order ID.']);
    exit;
}

// 1. Fetch Order details
$order = db_fetch_one("
    SELECT o.*, 
           u.full_name AS customer_name, 
           u.phone_number AS customer_phone, 
           u.email AS customer_email,
           p.payment_mode, 
           p.payment_status, 
           p.amount_paid,
           d.delivery_partner_name, 
           d.delivery_partner_phone, 
           d.delivery_otp, 
           d.delivery_status
    FROM `orders` o
    LEFT JOIN `users` u ON o.user_id = u.user_id
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    WHERE o.order_id = :oid
", [':oid' => $orderId]);

if (!$order) {
    echo json_encode(['success' => false, 'error' => "Order #ZIP-{$orderId} was not found."]);
    exit;
}

// 2. Fetch full chronological audit trail
$trails = db_fetch_all("
    SELECT a.*, 
           u.full_name AS actor_name, 
           r.role_name AS actor_role
    FROM `order_audit_trail` a
    LEFT JOIN `users` u ON a.updated_by_user_id = u.user_id
    LEFT JOIN `roles` r ON u.role_id = r.role_id
    WHERE a.order_id = :oid
    ORDER BY a.change_timestamp ASC, a.audit_id ASC
", [':oid' => $orderId]);

$timeline = [];
$prevTime = !empty($order['placed_at']) ? strtotime($order['placed_at']) : null;
$firstTime = $prevTime;
$lastTime = null;

foreach ($trails as $idx => $t) {
    $curTime = strtotime($t['change_timestamp']);
    if ($firstTime === null) {
        $firstTime = $curTime;
    }
    $lastTime = $curTime;

    $dwellSeconds = ($prevTime !== null) ? max(0, $curTime - $prevTime) : 0;
    $prevTime = $curTime;

    // Format dwell duration
    $dwellFormatted = 'Instant transition';
    if ($dwellSeconds >= 60) {
        $mins = (int)floor($dwellSeconds / 60);
        $hrs = (int)floor($mins / 60);
        $remMins = $mins % 60;
        if ($hrs > 0) {
            $dwellFormatted = "{$hrs}h " . ($remMins > 0 ? "{$remMins}m" : "") . " elapsed";
        } else {
            $dwellFormatted = "{$mins} min" . ($mins > 1 ? 's' : '') . " elapsed";
        }
    } elseif ($dwellSeconds > 0) {
        $dwellFormatted = "{$dwellSeconds}s elapsed";
    }

    $actorRole = $t['actor_role'] ?: 'SYSTEM';
    $actorName = $t['actor_name'] ?: 'System Trigger';

    $timeline[] = [
        'audit_id' => (int)$t['audit_id'],
        'old_status' => $t['old_status'] ?: 'PLACED',
        'new_status' => $t['new_status'],
        'actor_role' => $actorRole,
        'actor_name' => $actorName,
        'timestamp' => date('d M Y, h:i:s A', $curTime),
        'time_ago' => format_time_ago($curTime),
        'dwell_seconds' => $dwellSeconds,
        'dwell_formatted' => $dwellFormatted,
        'old_badge' => get_status_badge($t['old_status'] ?: 'PLACED'),
        'new_badge' => get_status_badge($t['new_status'])
    ];
}

$turnaroundFormatted = 'In progress';
if ($firstTime && $lastTime) {
    $totalSec = max(0, $lastTime - $firstTime);
    $totMins = (int)floor($totalSec / 60);
    $totHrs = (int)floor($totMins / 60);
    $totRemM = $totMins % 60;
    if ($totHrs > 0) {
        $turnaroundFormatted = "{$totHrs}h " . ($totRemM > 0 ? "{$totRemM}m" : "");
    } elseif ($totMins > 0) {
        $turnaroundFormatted = "{$totMins} mins";
    } else {
        $turnaroundFormatted = "{$totalSec}s";
    }
}

echo json_encode([
    'success' => true,
    'order' => [
        'order_id' => (int)$order['order_id'],
        'order_number' => $order['order_number'] ?: ('ZIP-' . $order['order_id']),
        'order_status' => $order['order_status'],
        'status_badge' => get_status_badge($order['order_status']),
        'total_amount' => format_inr($order['total_amount']),
        'payment_mode' => $order['payment_mode'] ?: 'N/A',
        'payment_status' => $order['payment_status'] ?: 'PENDING',
        'customer_name' => $order['customer_name'] ?: 'Guest Customer',
        'customer_phone' => $order['customer_phone'] ?: '',
        'placed_at' => !empty($order['placed_at']) ? date('d M Y, h:i A', strtotime($order['placed_at'])) : '—',
        'placed_time_ago' => !empty($order['placed_at']) ? format_time_ago(strtotime($order['placed_at'])) : '—',
        'rider_name' => $order['delivery_partner_name'] ?: null,
        'rider_phone' => $order['delivery_partner_phone'] ?: null,
        'delivery_otp' => $order['delivery_otp'] ?: null,
        'turnaround' => $turnaroundFormatted
    ],
    'timeline' => $timeline,
    'total_transitions' => count($timeline)
]);
