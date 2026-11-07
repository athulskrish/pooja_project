<?php
/**
 * Real-Time Portal Stream & Multi-Actor Lifecycle Synchronization API
 * Zippy - Online Food Ordering Platform
 * MCSP-232: MCA Major Project | Student: Pooja JP
 * 
 * Streams real-time updates for:
 * 1. Customer: Live order processing stages, OTP, rider live coordinates
 * 2. Kitchen KDS: Incoming orders, cooking dockets, WSJF priority, elapsed timers
 * 3. Delivery Fleet: Ready dockets for pickup, active in-transit GPS dispatches
 * 4. Admin Command Center: Real-time orders ticker, revenue, station load, alerts
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$portal = clean_input($_GET['portal'] ?? 'admin');
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// -----------------------------------------------------------------------------
// 1. KITCHEN PORTAL STREAM
// -----------------------------------------------------------------------------
if ($portal === 'kitchen') {
    require_once __DIR__ . '/../includes/WSJFQueue.php';
    auto_allocate_ready_orders_fcfs();
    $prioritizedOrders = WSJFQueue::getPrioritizedOrders();

    $laneNew = [];
    $laneCooking = [];
    $laneReady = [];

    foreach ($prioritizedOrders as $o) {
        $st = strtoupper($o['order_status']);
        $placedTs = strtotime($o['placed_at']);
        $elapsedSec = max(0, time() - $placedTs);
        $o['elapsed_minutes'] = floor($elapsedSec / 60);
        $o['elapsed_seconds'] = $elapsedSec;

        if (in_array($st, ['PLACED', 'CONFIRMED'])) {
            $laneNew[] = $o;
        } elseif ($st === 'COOKING') {
            $laneCooking[] = $o;
        } elseif ($st === 'READY') {
            $laneReady[] = $o;
        }
    }

    // Sort ready orders strictly in FCFS order by food preparation time
    usort($laneReady, function($a, $b) {
        $timeA = !empty($a['ready_at']) ? strtotime($a['ready_at']) : strtotime($a['placed_at']);
        $timeB = !empty($b['ready_at']) ? strtotime($b['ready_at']) : strtotime($b['placed_at']);
        if ($timeA === $timeB) {
            return (int)$a['order_id'] <=> (int)$b['order_id'];
        }
        return $timeA <=> $timeB;
    });

    $countNew = count($laneNew);
    $countCooking = count($laneCooking);
    $countReady = count($laneReady);
    $stateHash = get_kds_state_hash();

    echo json_encode([
        'success' => true,
        'portal' => 'kitchen',
        'timestamp' => date('Y-m-d H:i:s'),
        'state_hash' => $stateHash,
        'counts' => [
            'new' => $countNew,
            'cooking' => $countCooking,
            'ready' => $countReady,
            'total_active' => $countNew + $countCooking + $countReady
        ],
        'lanes' => [
            'new' => array_slice($laneNew, 0, 15),
            'cooking' => array_slice($laneCooking, 0, 15),
            'ready' => array_slice($laneReady, 0, 15)
        ]
    ]);
    exit;
}

// -----------------------------------------------------------------------------
// 2. DELIVERY FLEET STREAM
// -----------------------------------------------------------------------------
if ($portal === 'delivery') {
    // 1. Run automatic FCFS parcel allocation
    auto_allocate_ready_orders_fcfs();

    // 2. Orders ready for pickup, sorted strictly in FCFS order by food preparation time
    $readyOrders = db_fetch_all("
        SELECT o.order_id, o.order_number, o.total_amount, o.ready_at, o.placed_at,
               u.full_name AS customer_name, u.phone_number AS customer_phone,
               a.address_line, a.landmark, a.pincode,
               p.payment_mode, d.delivery_otp, d.rider_id, d.delivery_status
        FROM `orders` o
        JOIN `users` u ON o.user_id = u.user_id
        LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
        LEFT JOIN `payments` p ON o.order_id = p.order_id
        LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
        WHERE o.order_status = 'READY'
        ORDER BY COALESCE(o.ready_at, o.placed_at) ASC, o.order_id ASC
    ");

    // Orders currently in transit
    $inTransitOrders = db_fetch_all("
        SELECT o.order_id, o.order_number, o.total_amount, o.order_notes,
               u.full_name AS customer_name, u.phone_number AS customer_phone,
               a.address_line, a.pincode,
               p.payment_mode, p.payment_status,
               d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp,
               d.current_latitude, d.current_longitude, d.route_progress,
               d.distance_remaining_km, d.estimated_minutes_remaining, d.dispatched_at
        FROM `orders` o
        JOIN `users` u ON o.user_id = u.user_id
        LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
        LEFT JOIN `payments` p ON o.order_id = p.order_id
        LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
        WHERE o.order_status = 'OUT_FOR_DELIVERY'
        ORDER BY d.dispatched_at DESC
    ");

    foreach ($readyOrders as &$ro) {
        $ro['formatted_amount'] = format_inr($ro['total_amount']);
        $ro['address_display'] = ($ro['address_line'] ?: 'Indiranagar') . ($ro['pincode'] ? ' (PIN: ' . $ro['pincode'] . ')' : '');
    }
    unset($ro);

    $riderIdParam = isset($_GET['rider_id']) ? (int)$_GET['rider_id'] : 0;
    $riderHasActive = false;
    $activeDelivery = null;
    $allocatedOrder = null;

    if ($riderIdParam > 0) {
        $activeDelivery = get_driver_active_delivery($riderIdParam);
        $riderHasActive = !empty($activeDelivery);
        if ($activeDelivery) {
            $items = db_fetch_all("
                SELECT oi.*, m.item_name, m.is_veg 
                FROM `order_items` oi
                JOIN `menu_items` m ON oi.item_id = m.item_id
                WHERE oi.order_id = :oid
            ", [':oid' => $activeDelivery['order_id']]);
            $activeDelivery['items'] = $items;
            $activeDelivery['formatted_amount'] = format_inr($activeDelivery['total_amount']);
        } else {
            // If rider has no active delivery, get the parcel allocated to them
            $allocatedOrder = get_driver_allocated_order($riderIdParam);
            if ($allocatedOrder) {
                $allocatedOrder['formatted_amount'] = format_inr($allocatedOrder['total_amount']);
                $allocatedOrder['address_display'] = ($allocatedOrder['address_line'] ?: 'Indiranagar') . ($allocatedOrder['pincode'] ? ' (PIN: ' . $allocatedOrder['pincode'] . ')' : '');
            }
        }
    }

    echo json_encode([
        'success' => true,
        'portal' => 'delivery',
        'timestamp' => date('Y-m-d H:i:s'),
        'counts' => [
            'ready_for_pickup' => count($readyOrders),
            'in_transit' => count($inTransitOrders)
        ],
        'rider_has_active' => $riderHasActive,
        'active_order' => $activeDelivery,
        'allocated_order' => $allocatedOrder,
        'max_concurrent_deliveries' => 1,
        'ready_orders' => $readyOrders,
        'in_transit_orders' => $inTransitOrders
    ]);
    exit;
}

// -----------------------------------------------------------------------------
// 3. ADMIN OPERATIONS CONTROL STREAM
// -----------------------------------------------------------------------------
// Count live orders by each stage
$stageCounts = db_fetch_one("
    SELECT 
        SUM(CASE WHEN `order_status` IN ('PLACED', 'CONFIRMED') THEN 1 ELSE 0 END) AS count_incoming,
        SUM(CASE WHEN `order_status` = 'COOKING' THEN 1 ELSE 0 END) AS count_cooking,
        SUM(CASE WHEN `order_status` = 'READY' THEN 1 ELSE 0 END) AS count_ready,
        SUM(CASE WHEN `order_status` = 'OUT_FOR_DELIVERY' THEN 1 ELSE 0 END) AS count_transit,
        SUM(CASE WHEN `order_status` = 'DELIVERED' AND DATE(`placed_at`) = CURDATE() THEN 1 ELSE 0 END) AS count_delivered_today,
        SUM(CASE WHEN `order_status` IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY') THEN 1 ELSE 0 END) AS count_total_active
    FROM `orders`
");

// Fetch the 12 most recent live orders across the system
$recentLiveOrders = db_fetch_all("
    SELECT o.order_id, o.order_number, o.order_status, o.total_amount, o.placed_at, o.estimated_delivery_time,
           u.full_name AS customer_name, u.phone_number AS customer_phone,
           a.address_line, a.pincode,
           p.payment_mode, p.payment_status,
           d.delivery_partner_name, d.delivery_otp, d.delivery_status,
           d.current_latitude, d.current_longitude, d.route_progress, d.distance_remaining_km, d.estimated_minutes_remaining,
           TIMESTAMPDIFF(MINUTE, o.placed_at, NOW()) AS elapsed_minutes
    FROM `orders` o
    JOIN `users` u ON o.user_id = u.user_id
    LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    WHERE o.order_status IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY')
    ORDER BY o.placed_at DESC
    LIMIT 12
");

// Relative time formatting is already provided by format_time_ago() in includes/functions.php
if (!function_exists('format_time_ago')) {
    function format_time_ago($datetime): string {
        $ts = strtotime($datetime);
        $diff = time() - $ts;
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        return date('d M, h:i A', $ts);
    }
}

foreach ($recentLiveOrders as &$rOrder) {
    $rOrder['badge_html'] = get_status_badge($rOrder['order_status']);
    $rOrder['formatted_total'] = format_inr($rOrder['total_amount']);
    $rOrder['time_ago'] = format_time_ago($rOrder['placed_at']);
}
unset($rOrder);

// Active on-duty riders & 1 Driver 1 Delivery metrics
$allRiders = get_all_delivery_riders();
$onDutyRiders = array_filter($allRiders, fn($r) => $r['duty_status'] === 'ON_DUTY');
$busyRiders = array_filter($onDutyRiders, fn($r) => !empty($r['is_busy']));
$availableRiders = array_filter($onDutyRiders, fn($r) => empty($r['is_busy']));

echo json_encode([
    'success' => true,
    'portal' => 'admin',
    'timestamp' => date('Y-m-d H:i:s'),
    'metrics' => [
        'incoming' => (int)($stageCounts['count_incoming'] ?? 0),
        'cooking' => (int)($stageCounts['count_cooking'] ?? 0),
        'ready' => (int)($stageCounts['count_ready'] ?? 0),
        'out_for_delivery' => (int)($stageCounts['count_transit'] ?? 0),
        'delivered_today' => (int)($stageCounts['count_delivered_today'] ?? 0),
        'total_active' => (int)($stageCounts['count_total_active'] ?? 0),
        'on_duty_riders' => count($onDutyRiders),
        'busy_riders' => count($busyRiders),
        'available_riders' => count($availableRiders)
    ],
    'live_orders' => $recentLiveOrders
]);
exit;
