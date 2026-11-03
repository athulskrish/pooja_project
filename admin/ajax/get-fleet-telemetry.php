<?php
/**
 * AJAX Endpoint: Live Delivery Fleet GPS Telemetry & Active Routes
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Require Admin or Kitchen authorization
if (!is_admin() && !is_kitchen()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Zippy Central Dispatch Hub coordinates (Indiranagar, Bangalore)
$hub = [
    'name' => 'Zippy Central Kitchen & Dispatch Hub',
    'address' => '100ft Road, Indiranagar, Bengaluru, KA 560038',
    'lat' => 12.9716,
    'lng' => 77.6412
];

// Fetch all delivery staff
$deliveryStaff = db_fetch_all("
    SELECT sp.profile_id, sp.user_id, sp.designation, sp.duty_status, 
           sp.vehicle_details, sp.driving_license, sp.cod_balance, sp.rating, 
           sp.total_orders_handled, u.full_name, u.phone_number, u.email
    FROM `staff_profiles` sp
    JOIN `users` u ON sp.user_id = u.user_id
    WHERE sp.department = 'DELIVERY'
    ORDER BY sp.duty_status ASC, u.full_name ASC
");

$ridersTelemetry = [];
$activeTrips = [];

foreach ($deliveryStaff as $idx => $staff) {
    $uid = (int)$staff['user_id'];
    $activeTrip = get_driver_active_delivery($uid, $staff['full_name']);

    $riderData = [
        'user_id' => $uid,
        'profile_id' => (int)$staff['profile_id'],
        'name' => $staff['full_name'],
        'phone' => $staff['phone_number'],
        'duty_status' => $staff['duty_status'] ?? 'ON_DUTY',
        'vehicle' => $staff['vehicle_details'] ?: 'Electric Scooter',
        'rating' => (float)($staff['rating'] ?: 4.8),
        'cod_balance' => (float)($staff['cod_balance'] ?: 0.00),
        'has_active_trip' => !empty($activeTrip)
    ];

    if ($activeTrip) {
        $curLat = (float)($activeTrip['current_latitude'] ?? 0);
        $curLng = (float)($activeTrip['current_longitude'] ?? 0);
        $destLat = (float)($activeTrip['delivery_latitude'] ?? 0);
        $destLng = (float)($activeTrip['delivery_longitude'] ?? 0);

        // Fallbacks if coords are 0
        if ($curLat == 0 || $curLng == 0) {
            $curLat = 12.9784 + ($idx * 0.003);
            $curLng = 77.6408 + ($idx * 0.002);
        }
        if ($destLat == 0 || $destLng == 0) {
            $destLat = 12.9698 - ($idx * 0.002);
            $destLng = 77.6499 + ($idx * 0.003);
        }

        $riderData['lat'] = $curLat;
        $riderData['lng'] = $curLng;
        $riderData['trip'] = [
            'order_id' => (int)$activeTrip['order_id'],
            'order_number' => $activeTrip['order_number'] ?? ('#ZIP-' . $activeTrip['order_id']),
            'customer_name' => $activeTrip['customer_name'] ?? 'Customer',
            'customer_phone' => $activeTrip['customer_phone'] ?? '',
            'address' => trim(($activeTrip['address_line'] ?? '') . ', ' . ($activeTrip['pincode'] ?? '')),
            'otp' => $activeTrip['delivery_otp'] ?? '',
            'dest_lat' => $destLat,
            'dest_lng' => $destLng,
            'distance_km' => (float)($activeTrip['distance_remaining_km'] ?: 2.4),
            'eta_mins' => (int)($activeTrip['estimated_minutes_remaining'] ?: 15),
            'dispatched_at' => $activeTrip['dispatched_at'] ?? ''
        ];

        $activeTrips[] = $riderData['trip'];
    } else {
        // Parked / standby location near central kitchen
        $offsetAngle = ($idx * 60) * (M_PI / 180);
        $radius = 0.0015; // ~150 meters
        $riderData['lat'] = round($hub['lat'] + ($radius * cos($offsetAngle)), 6);
        $riderData['lng'] = round($hub['lng'] + ($radius * sin($offsetAngle)), 6);
        $riderData['trip'] = null;
    }

    $ridersTelemetry[] = $riderData;
}

echo json_encode([
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'hub' => $hub,
    'riders' => $ridersTelemetry,
    'active_trips_count' => count($activeTrips)
], JSON_PRETTY_PRINT);
