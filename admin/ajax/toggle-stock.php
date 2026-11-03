<?php
/**
 * AJAX Endpoint: Instant Menu Item 86-List Toggle (Admin Console)
 * Allows administrators to instantly mark dishes Available or 86 / Sold Out
 * Zippy - Online Food Ordering Platform
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized administrator access required']);
    exit;
}

$rawInput = file_get_contents('php://input');
$json = json_decode($rawInput, true);

$itemId = (int)($json['item_id'] ?? $_POST['item_id'] ?? 0);
$isAvailable = isset($json['is_available']) ? (int)$json['is_available'] : (isset($_POST['is_available']) ? (int)$_POST['is_available'] : null);

if ($itemId <= 0 || $isAvailable === null) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID or availability status parameter']);
    exit;
}

$dish = db_fetch_one("SELECT item_id, item_name, is_available, stock_quantity FROM `menu_items` WHERE `item_id` = :id", [':id' => $itemId]);
if (!$dish) {
    echo json_encode(['success' => false, 'message' => 'Menu item not found in catalog']);
    exit;
}

$newAvailability = $isAvailable ? 1 : 0;
$stockQuantity = (int)$dish['stock_quantity'];

// If marking available but current stock is 0, auto-replenish with default operational reserve (20 portions)
if ($newAvailability === 1 && $stockQuantity <= 0) {
    $stockQuantity = 20;
    db_query("UPDATE `menu_items` SET `is_available` = 1, `stock_quantity` = :sq WHERE `item_id` = :id", [
        ':sq' => $stockQuantity,
        ':id' => $itemId
    ]);
} else {
    db_query("UPDATE `menu_items` SET `is_available` = :av WHERE `item_id` = :id", [
        ':av' => $newAvailability,
        ':id' => $itemId
    ]);
}

echo json_encode([
    'success' => true,
    'item_id' => $itemId,
    'item_name' => $dish['item_name'],
    'is_available' => (bool)$newAvailability,
    'stock_quantity' => $stockQuantity,
    'message' => "'{$dish['item_name']}' is now " . ($newAvailability ? 'AVAILABLE (In Stock)' : '86 / SOLD OUT (Unavailable)')
]);
exit;
