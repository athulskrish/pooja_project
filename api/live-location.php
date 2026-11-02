<?php
/**
 * Real-Time Geolocation & Live Delivery Tracking API
 * Zippy - Online Food Ordering Platform
 * MCSP-232: MCA Major Project | Student: Pooja JP
 * 
 * Provides:
 * - Live coordinates of Restaurant Hub, Rider, and Customer Destination
 * - Segmented route waypoints for Leaflet map polylines
 * - Dynamic ETA & distance calculation
 * - Location push updates for rider GPS
 * - Auto-advancing simulation for demonstration and Selenium verification
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Restaurant Origin (Zippy Cloud Kitchen Hub - Indiranagar)
define('HUB_LAT', 12.97840000);
define('HUB_LNG', 77.64080000);
define('HUB_NAME', 'Zippy Kitchen Hub (Indiranagar 100ft Rd)');
define('HUB_ADDRESS', '#544, 100 Feet Road, HAL 2nd Stage, Bangalore 560038');

// Waypoints along primary arterial route from Indiranagar Hub to HAL 2nd Stage / 100ft Rd residences
$canonicalRoute = [
    ['lat' => 12.97840, 'lng' => 77.64080], // 0%: Kitchen Hub Gate
    ['lat' => 12.97650, 'lng' => 77.64150], // 20%: 100ft Rd - 12th Main Crossing
    ['lat' => 12.97480, 'lng' => 77.64250], // 40%: Double Road Flyover Approach
    ['lat' => 12.97320, 'lng' => 77.64420], // 60%: Defence Colony Signal
    ['lat' => 12.97150, 'lng' => 77.64680], // 80%: HAL 2nd Stage Avenue
    ['lat' => 12.96980, 'lng' => 77.64990]  // 100%: Customer Doorstep
];

/**
 * Interpolate latitude and longitude along a multi-segment route given a progress fraction [0, 1]
 */
function interpolateRoute(array $route, float $progress): array {
    $progress = max(0.0, min(1.0, $progress));
    $n = count($route);
    if ($n < 2) {
        return $route[0] ?? ['lat' => HUB_LAT, 'lng' => HUB_LNG];
    }

    $totalSegments = $n - 1;
    $scaled = $progress * $totalSegments;
    $idx = (int)floor($scaled);
    if ($idx >= $totalSegments) {
        return $route[$n - 1];
    }

    $segFraction = $scaled - $idx;
    $p1 = $route[$idx];
    $p2 = $route[$idx + 1];

    $lat = $p1['lat'] + ($p2['lat'] - $p1['lat']) * $segFraction;
    $lng = $p1['lng'] + ($p2['lng'] - $p1['lng']) * $segFraction;

    // Calculate heading angle
    $dLat = $p2['lat'] - $p1['lat'];
    $dLng = $p2['lng'] - $p1['lng'];
    $heading = (rad2deg(atan2($dLng, $dLat)) + 360) % 360;

    return [
        'lat' => round($lat, 6),
        'lng' => round($lng, 6),
        'heading' => round($heading, 1)
    ];
}

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : (isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0);

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid or missing order_id']);
    exit;
}

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// -----------------------------------------------------------------------------
// POST Request: Update or Simulate Rider Location
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_location';

    if ($action === 'simulate_step') {
        // Fetch current dispatch progress
        $disp = db_fetch_one("SELECT * FROM `delivery_dispatches` WHERE `order_id` = :id", [':id' => $orderId]);
        if (!$disp) {
            echo json_encode(['success' => false, 'error' => 'Dispatch not found']);
            exit;
        }

        $stepDelta = isset($_POST['delta']) ? (int)$_POST['delta'] : 15; // default 15% step
        $currentProg = (int)($disp['route_progress'] ?? 0);
        $newProg = min(100, $currentProg + $stepDelta);

        $pos = interpolateRoute($canonicalRoute, $newProg / 100.0);
        $remainingKm = max(0.05, round(2.40 * (1.0 - ($newProg / 100.0)), 2));
        $remainingMins = max(1, (int)round($remainingKm * 3.5));

        $updateStmt = $pdo->prepare("
            UPDATE `delivery_dispatches` 
            SET `current_latitude` = :lat,
                `current_longitude` = :lng,
                `route_progress` = :prog,
                `distance_remaining_km` = :dist,
                `estimated_minutes_remaining` = :mins,
                `delivery_status` = IF(:prog >= 100, 'IN_TRANSIT', 'IN_TRANSIT')
            WHERE `order_id` = :id
        ");
        $updateStmt->execute([
            ':lat' => $pos['lat'],
            ':lng' => $pos['lng'],
            ':prog' => $newProg,
            ':dist' => $remainingKm,
            ':mins' => $remainingMins,
            ':id' => $orderId
        ]);

        echo json_encode([
            'success' => true,
            'order_id' => $orderId,
            'route_progress' => $newProg,
            'current_latitude' => $pos['lat'],
            'current_longitude' => $pos['lng'],
            'distance_remaining_km' => $remainingKm,
            'estimated_minutes_remaining' => $remainingMins,
            'arrived_at_doorstep' => ($newProg >= 100)
        ]);
        exit;
    }

    if ($action === 'update_location') {
        $lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
        $lng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
        $prog = isset($_POST['progress']) ? (int)$_POST['progress'] : null;

        if ($lat !== null && $lng !== null) {
            $progVal = $prog !== null ? min(100, max(0, $prog)) : 50;
            $remainingKm = max(0.05, round(2.40 * (1.0 - ($progVal / 100.0)), 2));
            $remainingMins = max(1, (int)round($remainingKm * 3.5));

            $updateStmt = $pdo->prepare("
                UPDATE `delivery_dispatches` 
                SET `current_latitude` = :lat,
                    `current_longitude` = :lng,
                    `route_progress` = :prog,
                    `distance_remaining_km` = :dist,
                    `estimated_minutes_remaining` = :mins
                WHERE `order_id` = :id
            ");
            $updateStmt->execute([
                ':lat' => $lat,
                ':lng' => $lng,
                ':prog' => $progVal,
                ':dist' => $remainingKm,
                ':mins' => $remainingMins,
                ':id' => $orderId
            ]);

            echo json_encode([
                'success' => true,
                'order_id' => $orderId,
                'lat' => $lat,
                'lng' => $lng,
                'route_progress' => $progVal
            ]);
            exit;
        }
    }
}

// -----------------------------------------------------------------------------
// GET Request: Retrieve Full Live Location & Order Tracking Metadata
// -----------------------------------------------------------------------------
$order = db_fetch_one("
    SELECT o.order_id, o.order_number, o.order_status, o.placed_at, o.estimated_delivery_time,
           u.full_name AS customer_name, u.phone_number AS customer_phone,
           a.address_line, a.landmark, a.pincode,
           d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp, d.delivery_status,
           d.current_latitude, d.current_longitude, d.delivery_latitude, d.delivery_longitude,
           d.route_progress, d.distance_remaining_km, d.estimated_minutes_remaining,
           d.assigned_at, d.dispatched_at, d.delivered_at,
           p.payment_mode, p.payment_status
    FROM `orders` o
    JOIN `users` u ON o.user_id = u.user_id
    LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    LEFT JOIN `payments` p ON o.order_id = p.order_id
    WHERE o.order_id = :id
", [':id' => $orderId]);

if (!$order) {
    echo json_encode(['success' => false, 'error' => "Order #ZIP-{$orderId} not found"]);
    exit;
}

$status = strtoupper($order['order_status']);
$isDelivered = ($status === 'DELIVERED');
$isCancelled = ($status === 'CANCELLED');
$isOutForDelivery = ($status === 'OUT_FOR_DELIVERY');

// Progress percentage along the delivery lifecycle
$lifecycleStep = 0;
switch ($status) {
    case 'PLACED':
    case 'CONFIRMED':
        $lifecycleStep = 1;
        break;
    case 'COOKING':
        $lifecycleStep = 2;
        break;
    case 'READY':
        $lifecycleStep = 3;
        break;
    case 'OUT_FOR_DELIVERY':
        $lifecycleStep = 4;
        break;
    case 'DELIVERED':
        $lifecycleStep = 5;
        break;
}

// Auto-advance route simulation if OUT_FOR_DELIVERY and location not manually fixed
$routeProgress = (int)($order['route_progress'] ?? 0);
if ($isOutForDelivery) {
    // If dispatched over 15 seconds ago and progress is 0, initialize realistic movement
    $dispatchTs = !empty($order['dispatched_at']) ? strtotime($order['dispatched_at']) : time();
    $elapsedSec = max(0, time() - $dispatchTs);
    if ($routeProgress == 0 && $elapsedSec > 5) {
        $simProg = min(95, (int)($elapsedSec * 4)); // ~4% per second in demo mode
        $routeProgress = max(20, $simProg);
    }
} elseif ($isDelivered) {
    $routeProgress = 100;
}

// Compute rider position based on route progress
$customerLat = (float)($order['delivery_latitude'] ?: 12.96980000);
$customerLng = (float)($order['delivery_longitude'] ?: 77.64990000);

// Adapt route destination to match customer location
$adjustedRoute = $canonicalRoute;
$adjustedRoute[count($adjustedRoute) - 1] = ['lat' => $customerLat, 'lng' => $customerLng];

if ($routeProgress > 0 && $routeProgress <= 100) {
    $riderPos = interpolateRoute($adjustedRoute, $routeProgress / 100.0);
    $currentLat = $riderPos['lat'];
    $currentLng = $riderPos['lng'];
    $heading = $riderPos['heading'];
} else {
    $currentLat = (float)($order['current_latitude'] ?: HUB_LAT);
    $currentLng = (float)($order['current_longitude'] ?: HUB_LNG);
    $heading = 135;
}

$remainingKm = $isDelivered ? 0.0 : max(0.0, round(2.40 * (1.0 - ($routeProgress / 100.0)), 2));
$remainingMins = $isDelivered ? 0 : max(1, (int)round($remainingKm * 3.5));

// Human-friendly status texts
$statusHeading = 'Order Placed';
$statusSubtext = 'Your order is being sent to the kitchen';

if ($status === 'CONFIRMED') {
    $statusHeading = 'Order Confirmed & Queued';
    $statusSubtext = 'Kitchen has received your ticket and is preparing ingredients';
} elseif ($status === 'COOKING') {
    $statusHeading = 'Chef is Cooking Your Meal';
    $statusSubtext = 'Active sautéing, baking and seasoning on stoves';
} elseif ($status === 'READY') {
    $statusHeading = 'Order Plated & Packed';
    $statusSubtext = 'Freshly packed in insulated thermal bag at pickup counter';
} elseif ($status === 'OUT_FOR_DELIVERY') {
    $statusHeading = 'Rider is on the Way to Your Door';
    $statusSubtext = 'Live GPS transit active • Indiranagar delivery corridor';
} elseif ($status === 'DELIVERED') {
    $statusHeading = 'Delivered Successfully!';
    $statusSubtext = 'Handover completed at doorstep • Verified with OTP';
} elseif ($status === 'CANCELLED') {
    $statusHeading = 'Order Cancelled';
    $statusSubtext = 'Cancelled within permitted window • 100% refund processed';
}

echo json_encode([
    'success' => true,
    'order_id' => (int)$order['order_id'],
    'order_number' => $order['order_number'] ?: '#ZIP-' . $order['order_id'],
    'status' => $status,
    'lifecycle_step' => $lifecycleStep,
    'is_out_for_delivery' => $isOutForDelivery,
    'is_delivered' => $isDelivered,
    'is_cancelled' => $isCancelled,
    'status_heading' => $statusHeading,
    'status_subtext' => $statusSubtext,
    'badge_html' => get_status_badge($status),
    'delivery_otp' => $order['delivery_otp'] ?: '',
    'route' => [
        'progress_pct' => $routeProgress,
        'distance_remaining_km' => $remainingKm,
        'eta_minutes' => $remainingMins,
        'waypoints' => $adjustedRoute
    ],
    'locations' => [
        'restaurant' => [
            'name' => HUB_NAME,
            'address' => HUB_ADDRESS,
            'lat' => HUB_LAT,
            'lng' => HUB_LNG
        ],
        'customer' => [
            'name' => $order['customer_name'],
            'address' => ($order['address_line'] ?: 'Indiranagar 100ft Road') . ', Bangalore',
            'landmark' => $order['landmark'] ?: 'Near BDA Complex',
            'pincode' => $order['pincode'] ?: '560038',
            'lat' => $customerLat,
            'lng' => $customerLng
        ],
        'rider' => [
            'name' => $order['delivery_partner_name'] ?: 'Suresh Kumar (Zippy Express)',
            'phone' => $order['delivery_partner_phone'] ?: '+91 96320 98765',
            'status' => $order['delivery_status'] ?: 'ASSIGNED',
            'lat' => $currentLat,
            'lng' => $currentLng,
            'heading' => $heading,
            'speed_kmh' => $isOutForDelivery ? 24 : 0
        ]
    ]
]);
exit;
