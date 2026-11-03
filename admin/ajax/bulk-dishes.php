<?php
/**
 * AJAX Endpoint: Bulk Operations on Menu Dishes
 * Handles batch availability toggle, stock additions, and archive actions.
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

$action = $json['action'] ?? $_POST['action'] ?? '';
$itemIds = $json['item_ids'] ?? $_POST['item_ids'] ?? [];

if (!is_array($itemIds) || empty($itemIds)) {
    echo json_encode(['success' => false, 'message' => 'No items selected for bulk operation']);
    exit;
}

// Clean and sanitize item IDs
$cleanIds = array_filter(array_map('intval', $itemIds), function($id) { return $id > 0; });
if (empty($cleanIds)) {
    echo json_encode(['success' => false, 'message' => 'Invalid item selection']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
$count = count($cleanIds);

switch ($action) {
    case 'mark_available':
        db_query("UPDATE `menu_items` SET `is_available` = 1, `stock_quantity` = IF(`stock_quantity` <= 0, 20, `stock_quantity`) WHERE `item_id` IN ($placeholders)", $cleanIds);
        echo json_encode(['success' => true, 'message' => "Successfully marked {$count} dish(es) as In Stock / Available."]);
        break;

    case 'mark_soldout':
        db_query("UPDATE `menu_items` SET `is_available` = 0 WHERE `item_id` IN ($placeholders)", $cleanIds);
        echo json_encode(['success' => true, 'message' => "Successfully 86-ed / marked {$count} dish(es) as Sold Out."]);
        break;

    case 'add_stock':
        $delta = (int)($json['delta'] ?? 10);
        if ($delta <= 0) $delta = 10;
        db_query("UPDATE `menu_items` SET `stock_quantity` = `stock_quantity` + ?, `is_available` = 1 WHERE `item_id` IN ($placeholders)", array_merge([$delta], $cleanIds));
        echo json_encode(['success' => true, 'message' => "Added +{$delta} portions to {$count} selected dish(es)."]);
        break;

    case 'archive_selected':
        db_query("UPDATE `menu_items` SET `is_available` = 0, `stock_quantity` = 0 WHERE `item_id` IN ($placeholders)", $cleanIds);
        echo json_encode(['success' => true, 'message' => "Successfully archived and hidden {$count} dish(es) from customer catalog."]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown bulk operation requested']);
        break;
}
