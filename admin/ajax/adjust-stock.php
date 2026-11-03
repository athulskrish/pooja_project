<?php
/**
 * AJAX Endpoint: Quick Dish Inventory Adjustment
 * Increments or decrements stock portions without full-page reloads.
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
$delta = (int)($json['delta'] ?? $_POST['delta'] ?? 0);

if ($itemId <= 0 || $delta === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID or delta adjustment parameter']);
    exit;
}

$dish = db_fetch_one("SELECT item_id, item_name, is_available, stock_quantity FROM `menu_items` WHERE `item_id` = :id", [':id' => $itemId]);
if (!$dish) {
    echo json_encode(['success' => false, 'message' => 'Menu item not found in catalog']);
    exit;
}

$currentStock = (int)$dish['stock_quantity'];
$newStock = max(0, $currentStock + $delta);

// If stock reaches 0, auto 86-it (mark unavailable); if replenished from 0, auto make available
$newAvailable = ($newStock > 0) ? 1 : 0;

db_query("UPDATE `menu_items` SET `stock_quantity` = :sq, `is_available` = :av WHERE `item_id` = :id", [
    ':sq' => $newStock,
    ':av' => $newAvailable,
    ':id' => $itemId
]);

echo json_encode([
    'success' => true,
    'item_id' => $itemId,
    'item_name' => $dish['item_name'],
    'stock_quantity' => $newStock,
    'is_available' => (bool)$newAvailable,
    'delta' => $delta,
    'message' => "'{$dish['item_name']}' stock adjusted by " . ($delta > 0 ? "+{$delta}" : "{$delta}") . " (Now: {$newStock} portions)."
]);
