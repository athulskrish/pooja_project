<?php
declare(strict_types=1);
/**
 * AJAX Endpoint: Quick Dish Inventory Adjustment
 * Increments or decrements stock portions without full-page reloads.
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

$itemId = (int)($json['item_id'] ?? $_POST['item_id'] ?? 0);
$delta = (int)($json['delta'] ?? $_POST['delta'] ?? 0);

if ($itemId <= 0 || $delta === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID or delta adjustment parameter']);
    exit;
}

try {
    $result = dbTransactional(function () use ($itemId, $delta): array {
        $dish = dbFetchOne("SELECT item_id, item_name, is_available, stock_quantity FROM `menu_items` WHERE `item_id` = :id FOR UPDATE", [':id' => $itemId]);
        if (!$dish) {
            return ['found' => false];
        }

        $currentStock = (int)$dish['stock_quantity'];
        $newStock = max(0, $currentStock + $delta);

        // If stock reaches 0, auto 86-it (mark unavailable); if replenished from 0, auto make available
        $newAvailable = ($newStock > 0) ? 1 : 0;

        dbQuery("UPDATE `menu_items` SET `stock_quantity` = :sq, `is_available` = :av WHERE `item_id` = :id", [
            ':sq' => $newStock,
            ':av' => $newAvailable,
            ':id' => $itemId
        ]);

        return [
            'found' => true,
            'item_name' => $dish['item_name'],
            'stock_quantity' => $newStock,
            'is_available' => (bool)$newAvailable
        ];
    });
} catch (Throwable $e) {
    error_log("Stock adjustment transaction failed for item {$itemId}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update stock safely. Please try again.']);
    exit;
}

if (!$result['found']) {
    echo json_encode(['success' => false, 'message' => 'Menu item not found in catalog']);
    exit;
}

echo json_encode([
    'success' => true,
    'item_id' => $itemId,
    'item_name' => $result['item_name'],
    'stock_quantity' => $result['stock_quantity'],
    'is_available' => $result['is_available'],
    'delta' => $delta,
    'message' => "'{$result['item_name']}' stock adjusted by " . ($delta > 0 ? "+{$delta}" : "{$delta}") . " (Now: {$result['stock_quantity']} portions)."
]);
