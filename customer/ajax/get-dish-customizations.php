<?php
/**
 * Customer AJAX API: Fetch Dish Customisations & Options for Interactive Selection Modal
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$itemId = (int)($_GET['item_id'] ?? ($_GET['dish_id'] ?? 0));
if ($itemId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid dish ID']);
    exit;
}

$dish = db_fetch_one("
    SELECT m.item_id, m.item_name, m.price, m.is_veg, m.is_healthy, m.image_url, m.description, m.stock_quantity, m.is_available,
           c.category_name
    FROM `menu_items` m
    JOIN `categories` c ON m.category_id = c.category_id
    WHERE m.item_id = :id
", [':id' => $itemId]);

if (!$dish) {
    echo json_encode(['success' => false, 'error' => 'Dish not found']);
    exit;
}

$dish['display_image'] = get_dish_image_url($dish['image_url']);
$dish['price_formatted'] = format_inr($dish['price']);

// Fetch active customisation groups and available options
$groups = get_dish_customization_groups($itemId, true);

echo json_encode([
    'success' => true,
    'dish' => $dish,
    'has_customizations' => !empty($groups),
    'groups' => $groups
]);
exit;
