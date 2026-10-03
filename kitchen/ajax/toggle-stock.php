<?php
/**
 * AJAX Endpoint: Kitchen 86-List (Menu Item Availability Toggle)
 * Allows kitchen chefs to instantly mark dishes In Stock or 86 / Sold Out
 * Zippy - Online Food Ordering Platform
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!is_kitchen() && !is_admin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$rawInput = file_get_contents('php://input');
$json = json_decode($rawInput, true);

$itemId = (int)($json['item_id'] ?? $_POST['item_id'] ?? 0);
$isAvailable = isset($json['is_available']) ? (int)$json['is_available'] : (isset($_POST['is_available']) ? (int)$_POST['is_available'] : null);

if ($itemId <= 0 || $isAvailable === null) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID or availability status']);
    exit;
}

$dish = db_fetch_one("SELECT item_id, item_name, is_available FROM `menu_items` WHERE `item_id` = :id", [':id' => $itemId]);
if (!$dish) {
    echo json_encode(['success' => false, 'message' => 'Menu item not found']);
    exit;
}

db_query("UPDATE `menu_items` SET `is_available` = :av WHERE `item_id` = :id", [
    ':av' => $isAvailable ? 1 : 0,
    ':id' => $itemId
]);

echo json_encode([
    'success' => true,
    'item_id' => $itemId,
    'item_name' => $dish['item_name'],
    'is_available' => (bool)$isAvailable,
    'message' => "'{$dish['item_name']}' is now " . ($isAvailable ? 'IN STOCK (Available)' : '86 / SOLD OUT (Unavailable)')
]);
exit;
