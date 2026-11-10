<?php
declare(strict_types=1);
/**
 * AJAX Endpoint: Bulk Operations on Menu Dishes
 * Handles batch availability toggle, stock additions, and archive actions.
 * Zippy - Online Food Ordering Platform
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Authentication required.']);
    exit;
}

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized administrator access required']);
    exit;
}

$rawInput = file_get_contents('php://input');
$json = json_decode($rawInput, true) ?: [];

if (!verifyRequestCsrf($json)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF token.']);
    exit;
}

$action = $json['action'] ?? $_POST['action'] ?? '';
$itemIds = $json['item_ids'] ?? $_POST['item_ids'] ?? [];

if (!is_array($itemIds) || empty($itemIds)) {
    echo json_encode(['success' => false, 'message' => 'No items selected for bulk operation']);
    exit;
}

// Clean, deduplicate, and sort IDs ascending so row locks follow canonical order (prevents deadlocks)
$cleanIds = array_values(array_unique(array_filter(array_map('intval', $itemIds), static function (int $id): bool {
    return $id > 0;
})));
sort($cleanIds);

if (empty($cleanIds)) {
    echo json_encode(['success' => false, 'message' => 'Invalid item selection']);
    exit;
}

$allowedActions = ['mark_available', 'mark_soldout', 'add_stock', 'archive_selected'];
if (!in_array($action, $allowedActions, true)) {
    echo json_encode(['success' => false, 'message' => 'Unknown bulk operation requested']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
$delta = (int)($json['delta'] ?? 10);
if ($delta <= 0) {
    $delta = 10;
}

try {
    $count = dbTransactional(function () use ($action, $cleanIds, $placeholders, $delta): int {
        // Acquire InnoDB locks in deterministic ascending item_id order
        $lockedRows = dbFetchAll(
            "SELECT `item_id` FROM `menu_items` WHERE `item_id` IN ($placeholders) ORDER BY `item_id` ASC FOR UPDATE",
            $cleanIds
        );
        if (empty($lockedRows)) {
            return 0;
        }

        switch ($action) {
            case 'mark_available':
                dbQuery("UPDATE `menu_items` SET `is_available` = 1, `stock_quantity` = IF(`stock_quantity` <= 0, 20, `stock_quantity`) WHERE `item_id` IN ($placeholders)", $cleanIds);
                break;

            case 'mark_soldout':
                dbQuery("UPDATE `menu_items` SET `is_available` = 0 WHERE `item_id` IN ($placeholders)", $cleanIds);
                break;

            case 'add_stock':
                dbQuery("UPDATE `menu_items` SET `stock_quantity` = `stock_quantity` + ?, `is_available` = 1 WHERE `item_id` IN ($placeholders)", array_merge([$delta], $cleanIds));
                break;

            case 'archive_selected':
                dbQuery("UPDATE `menu_items` SET `is_available` = 0, `stock_quantity` = 0, `is_active` = 0 WHERE `item_id` IN ($placeholders)", $cleanIds);
                break;
        }

        return count($lockedRows);
    });
} catch (Throwable $e) {
    error_log("Bulk dish action '{$action}' failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Bulk menu update failed safely. Please try again.']);
    exit;
}

$messages = [
    'mark_available' => "Successfully marked {$count} dish(es) as In Stock / Available.",
    'mark_soldout' => "Successfully 86-ed / marked {$count} dish(es) as Sold Out.",
    'add_stock' => "Added +{$delta} portions to {$count} selected dish(es).",
    'archive_selected' => "Successfully archived and hidden {$count} dish(es) from customer catalog.",
];

echo json_encode(['success' => true, 'message' => $messages[$action]]);
