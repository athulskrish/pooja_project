<?php
declare(strict_types=1);
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
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Authentication required.']);
    exit;
}

$currentUser = getCurrentUser();
$loggedInUserId = (int)($currentUser['id'] ?? 0);

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

$rawInput = '';
$jsonBody = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $rawInput = (string)file_get_contents('php://input');
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $jsonBody = $decoded;
    }
}

$orderId = isset($_GET['order_id'])
    ? (int)$_GET['order_id']
    : (int)($_POST['order_id'] ?? $jsonBody['order_id'] ?? 0);

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing order_id']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!isAdmin() && !isRider()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden. Rider or Administrator role required.']);
        exit;
    }

    if (!verifyRequestCsrf($jsonBody)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired CSRF token.']);
        exit;
    }
}

$pdo = getDbConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// -----------------------------------------------------------------------------
// POST Request: Update or Simulate Rider Location (ADMIN or assigned RIDER + CSRF)
// -----------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (isRider() && !isAdmin() && !isRiderAssignedToOrder($orderId, $loggedInUserId, $currentUser['name'] ?? null)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden. You are not assigned to this order dispatch.']);
        exit;
    }

    $action = (string)($_POST['action'] ?? $jsonBody['action'] ?? 'update_location');

    if ($action === 'simulate_step') {
        // Fetch current dispatch progress
        $disp = dbFetchOne("SELECT * FROM `delivery_dispatches` WHERE `order_id` = :id", [':id' => $orderId]);
        if (!$disp) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Dispatch not found']);
            exit;
        }

        $stepDelta = isset($_POST['delta']) ? (int)$_POST['delta'] : (int)($jsonBody['delta'] ?? 15);
        $currentProg = (int)($disp['route_progress'] ?? 0);
        $newProg = min(100, $currentProg + $stepDelta);

        $destLat = (float)($disp['delivery_latitude'] ?: 12.96980000);
        $destLng = (float)($disp['delivery_longitude'] ?: 77.64990000);
        $routeTotalKm = max(0.5, calculateHaversineDistanceKm(HUB_LAT, HUB_LNG, $destLat, $destLng));
        if ($routeTotalKm < 1.0 && abs($destLat - 12.9698) < 0.001) {
            $routeTotalKm = 2.40;
        }
        $dynamicRoute = [];
        for ($i = 0; $i <= 5; $i++) {
            $t = $i / 5;
            $curveOffset = ($i > 0 && $i < 5) ? (sin($t * M_PI) * 0.0012) : 0.0;
            $dynamicRoute[] = [
                'lat' => round(HUB_LAT + (($destLat - HUB_LAT) * $t) + $curveOffset, 6),
                'lng' => round(HUB_LNG + (($destLng - HUB_LNG) * $t) - ($curveOffset * 0.5), 6),
            ];
        }

        $pos = FleetService::interpolateRoute($dynamicRoute, $newProg / 100.0);
        $remainingKm = max(0.05, round($routeTotalKm * (1.0 - ($newProg / 100.0)), 2));
        $remainingMins = max(1, (int)round($remainingKm * 3.5));

        $updateStmt = $pdo->prepare("
            UPDATE `delivery_dispatches` 
            SET `current_latitude` = :lat,
                `current_longitude` = :lng,
                `route_progress` = :prog,
                `distance_remaining_km` = :dist,
                `estimated_minutes_remaining` = :mins,
                `delivery_status` = 'IN_TRANSIT'
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
        $rawLat = $_POST['lat'] ?? $jsonBody['lat'] ?? null;
        $rawLng = $_POST['lng'] ?? $jsonBody['lng'] ?? null;
        $rawProg = $_POST['progress'] ?? $jsonBody['progress'] ?? null;

        if ($rawLat === null || $rawLng === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing lat or lng coordinates']);
            exit;
        }

        $lat = (float)$rawLat;
        $lng = (float)$rawLng;
        $progVal = $rawProg !== null ? min(100, max(0, (int)$rawProg)) : 50;
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

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unsupported location update action']);
    exit;
}

// -----------------------------------------------------------------------------
// GET Request: Retrieve Full Live Location & Order Tracking Metadata
// -----------------------------------------------------------------------------
$order = dbFetchOne("
    SELECT o.order_id, o.user_id, o.order_number, o.order_status, o.placed_at, o.estimated_delivery_time,
           u.full_name AS customer_name, u.phone_number AS customer_phone,
           a.address_line, a.landmark, a.pincode,
           d.rider_id, d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp, d.delivery_status,
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
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => "Order #ZIP-{$orderId} not found"]);
    exit;
}

$canViewOrder = isAdmin()
    || (isRider() && isRiderAssignedToOrder($orderId, $loggedInUserId, $currentUser['name'] ?? null))
    || (!isRider() && !isKitchen() && canAccessOrder($order, $loggedInUserId));

if (!$canViewOrder) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden. You do not have permission to view telemetry for this order.']);
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
    $dispatchTs = !empty($order['dispatched_at']) ? (int)(strtotime((string)$order['dispatched_at']) ?: time()) : time();
    $elapsedSec = max(0, time() - $dispatchTs);
    if ($routeProgress == 0 && $elapsedSec > 5) {
        $simProg = min(95, (int)($elapsedSec * 4)); // ~4% per second in demo mode
        $routeProgress = max(20, $simProg);
    }
} elseif ($isDelivered) {
    $routeProgress = 100;
}

// Compute rider position based on route progress using real Indian coordinates
$customerLat = (float)($order['delivery_latitude'] ?: 12.96980000);
$customerLng = (float)($order['delivery_longitude'] ?: 77.64990000);

$totalRouteKm = max(0.5, calculateHaversineDistanceKm(HUB_LAT, HUB_LNG, $customerLat, $customerLng));
if ($totalRouteKm < 1.0 && abs($customerLat - 12.9698) < 0.001) {
    $totalRouteKm = 2.40;
}

// Build smooth 6-point Indian arterial route from Indiranagar Hub to customer destination
$adjustedRoute = [];
$steps = 5;
for ($i = 0; $i <= $steps; $i++) {
    $t = $i / $steps;
    // Slight realistic curve offset for middle segments so path follows arterial roads
    $curveOffset = ($i > 0 && $i < $steps) ? (sin($t * M_PI) * 0.0012) : 0.0;
    $adjustedRoute[] = [
        'lat' => round(HUB_LAT + (($customerLat - HUB_LAT) * $t) + $curveOffset, 6),
        'lng' => round(HUB_LNG + (($customerLng - HUB_LNG) * $t) - ($curveOffset * 0.5), 6),
    ];
}

if ($routeProgress > 0 && $routeProgress <= 100) {
    $riderPos = FleetService::interpolateRoute($adjustedRoute, $routeProgress / 100.0);
    $currentLat = $riderPos['lat'];
    $currentLng = $riderPos['lng'];
    $heading = $riderPos['heading'] ?? 135;
} else {
    $currentLat = (float)($order['current_latitude'] ?: HUB_LAT);
    $currentLng = (float)($order['current_longitude'] ?: HUB_LNG);
    $heading = 135;
}

$remainingKm = $isDelivered ? 0.0 : max(0.0, round($totalRouteKm * (1.0 - ($routeProgress / 100.0)), 2));
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
    'badge_html' => getStatusBadge($status),
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
