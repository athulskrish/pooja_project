<?php
declare(strict_types=1);
/**
 * Pure-PHP Unit Verification Suite for V2 Features
 * Runs cleanly without requiring an active MySQL daemon (tests offline-resilient logic,
 * schema integrity, Indian coordinates/Haversine geofencing, distance fees, loyalty math,
 * dynamic coupon engine, dietary warning logic, and strict-type / file checks).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$passed = 0;
$failed = 0;

$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "[PASS] {$label}\n";
    } else {
        $failed++;
        echo "[FAIL] {$label}\n";
    }
};

// 1. Indian Coordinates & Bengaluru Pincode Geofencing (12 km radius from Indiranagar Hub)
$hubLat = (float)ZIPPY_HUB_LAT;
$hubLng = (float)ZIPPY_HUB_LNG;
$assert(abs($hubLat - 12.9784) < 0.01 && abs($hubLng - 77.6408) < 0.01, 'Indiranagar Hub coordinates configured in Karnataka/India');

$zones = getIndianServiceZones();
$assert(isset($zones['560038']) && isset($zones['560038']['zone']) && isset($zones['560038']['lat']) && isset($zones['560038']['lng']), 'getIndianServiceZones returns expected schema keys (pincode, zone, lat, lng)');

$distIndiranagar = calculateHaversineDistanceKm($hubLat, $hubLng, $zones['560038']['lat'], $zones['560038']['lng']);
$assert($distIndiranagar < 3.0, "Indiranagar 100ft Rd (560038) distance {$distIndiranagar} km <= 3.0 km");

$distWhitefield = calculateHaversineDistanceKm($hubLat, $hubLng, $zones['560066']['lat'], $zones['560066']['lng']);
$assert($distWhitefield > 10.0 && $distWhitefield <= 12.5, "Whitefield (560066) realistic distance {$distWhitefield} km");

$locOk = validateServiceableLocation('560034', null, null, 250.00);
$assert($locOk['serviceable'] === true && $locOk['delivery_fee'] > 0 && $locOk['latitude'] > 12.0 && $locOk['longitude'] > 77.0, 'Koramangala (560034) is serviceable with positive Bengaluru coordinates and fee');

$locOutOfRange = validateServiceableLocation('560038', 13.5000, 77.9000, 250.00);
$assert($locOutOfRange['serviceable'] === false, 'Coordinates far beyond 12 km Bengaluru geofence are rejected');

$locNonBlr = validateServiceableLocation('400001', null, null, 250.00);
$assert($locNonBlr['serviceable'] === false, 'Non-Bengaluru pincode (400001) rejected by geofence');

// 2. Distance-Based Tiered Delivery Fee Math
$assert(calculateDistanceDeliveryFee(2.1, 200.00) === 25.00, 'Tier <= 3 km charges ₹25.00 below ₹499');
$assert(calculateDistanceDeliveryFee(4.8, 200.00) === 40.00, 'Tier <= 6 km charges ₹40.00 below ₹499');
$assert(calculateDistanceDeliveryFee(8.5, 200.00) === 55.00, 'Tier <= 10 km charges ₹55.00 below ₹499');
$assert(calculateDistanceDeliveryFee(11.4, 200.00) === 70.00, 'Tier <= 12 km charges ₹70.00 below ₹499');
$assert(calculateDistanceDeliveryFee(11.4, 500.00) === 0.00, 'Free delivery applies at subtotal >= ₹499 regardless of distance');

// 3. Loyalty Points Earn & Redeem Calculations
$assert(calculateLoyaltyPointsEarned(420.00) === 21, 'Loyalty earn divisor awards 1 pt per ₹20 spent (420 -> 21 pts)');
$assert(calculateLoyaltyPointsEarned(19.99) === 0, 'Loyalty earn floors fractional ₹20 spends safely');

$cart = [
    ['price' => 250.00, 'qty' => 2], // 500.00 subtotal -> FREE delivery (>= 499)
];
$billWithLoyalty = calculateBillTotals($cart, 'LUSHFOREST', null, null, 500, 300);
// LUSHFOREST: 20% of 500 = 100 -> afterCoupon = 400 -> max 50% loyalty = 200 pts -> taxable = 200 -> 5% GST = 10 -> total = 200 + 10 + 0 + 5 = 215
$assert(abs($billWithLoyalty['discount'] - 100.00) < 0.01, 'LUSHFOREST discount caps cleanly at ₹100.00');
$assert($billWithLoyalty['loyalty_points_used'] === 200, 'Loyalty redemption caps at 50% of post-coupon food subtotal (200 pts of 400)');
$assert(abs($billWithLoyalty['loyalty_discount'] - 200.00) < 0.01, 'Loyalty rupee discount matches 1 pt = ₹1.00');
$assert(abs($billWithLoyalty['gst_amount'] - 10.00) < 0.01, '5% GST computed on net taxable food amount (₹200 -> ₹10)');
$assert(abs($billWithLoyalty['final_total'] - 215.00) < 0.01, 'Final total equals net food + GST + delivery + ₹5 platform fee');

$billOverrideFee = calculateBillTotals([['price' => 200.00, 'qty' => 1]], null, null, 25.00, 150, 120);
// afterCoupon=200, max loyalty 50% = 100 pts (balance 120 -> 100), taxable=100, gst=5, delivery=25, platform=5 => 135.00
$assert(abs($billOverrideFee['delivery_fee'] - 25.00) < 0.01 && abs($billOverrideFee['final_total'] - 135.00) < 0.01, 'Distance override delivery fee (₹25) integrates cleanly into bill calculation');

// 4. Coupon Validation Offline Fallback
$cpValid = validateCouponForSubtotal('ZIPPY50', 400.00, null);
$assert($cpValid['valid'] === true && abs($cpValid['discount'] - 120.00) < 0.01, 'ZIPPY50 fallback evaluates 50% capped at ₹120 on ₹400');

$cpMinFail = validateCouponForSubtotal('ZIPPY50', 250.00, null);
$assert($cpMinFail['valid'] === false && $cpMinFail['error'] !== null, 'ZIPPY50 rejects subtotals below ₹399 minimum');

// 5. Dietary Conflict & Nutrition Helpers
$dishConflict = [
    'item_name' => 'Spicy Paneer Cashew Masala',
    'is_veg' => 1,
    'calories_kcal' => 480,
    'protein_g' => 22,
    'carbs_g' => 36,
    'fat_g' => 26,
    'allergens' => 'dairy, nuts',
    'spice_level' => 'EXTRA_SPICY',
];
$dietEval = evaluateDishDietaryConflicts($dishConflict, ['dairy_free', 'nut_free', 'mild_spice', 'veg_only']);
$assert(count($dietEval['conflicts']) === 3, 'evaluateDishDietaryConflicts flags dairy, nuts, and spicy conflicts accurately for veg profile');
$metaHtml = renderDishDietaryMetadataHtml($dishConflict, ['dairy_free']);
$assert(str_contains($metaHtml, '480 kcal') && str_contains($metaHtml, '22g protein') && str_contains($metaHtml, 'Contains Dairy') && str_contains($metaHtml, 'Spicy'), 'renderDishDietaryMetadataHtml outputs calories, protein, EXTRA_SPICY badge, and dairy conflict warning');

// 6. Seed SQL Splitting Compatibility & Schema Presence
$schemaPath = __DIR__ . '/../database/schema.sql';
$schemaSql = (string)file_get_contents($schemaPath);
$requiredTables = [
    'support_ticket_replies',
    'coupons',
    'coupon_redemptions',
    'scheduled_slot_caps',
    'cart_items',
    'user_favorites',
    'loyalty_transactions',
];
foreach ($requiredTables as $tbl) {
    $assert(str_contains($schemaSql, "CREATE TABLE `{$tbl}`"), "schema.sql defines table `{$tbl}`");
}

// Verify seed.php DELIMITER strip + split does not choke on unexpected semicolons in seed INSERTs
$cleanSql = preg_replace('/DELIMITER\s+\$\$.*?DELIMITER\s+;/is', '', $schemaSql);
$statements = array_filter(array_map('trim', explode(';', (string)$cleanSql)));
$assert(count($statements) > 25, 'schema.sql splits cleanly for database/seed.php parser (' . count($statements) . ' statements)');

// 7. Key Files Existence Check
$expectedNewFiles = [
    __DIR__ . '/../database/migrate_v2_features.php',
    __DIR__ . '/../api/sse-stream.php',
    __DIR__ . '/../customer/ajax/validate-coupon.php',
    __DIR__ . '/../customer/ajax/slot-availability.php',
    __DIR__ . '/../customer/ajax/cart-sync.php',
    __DIR__ . '/../customer/ajax/toggle-favorite.php',
    __DIR__ . '/../kitchen/ajax/toggle-item-prepped.php',
    __DIR__ . '/../admin/support-tickets.php',
    __DIR__ . '/../admin/coupons.php',
];
foreach ($expectedNewFiles as $f) {
    $assert(is_file($f), 'Deliverable exists: ' . basename($f));
}

echo "\nSUMMARY: {$passed} passed, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);
