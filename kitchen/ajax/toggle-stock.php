<?php
declare(strict_types=1);
/**
 * AJAX Endpoint: Kitchen 86-List (Menu Item Availability Toggle)
 * Allows kitchen chefs to instantly mark dishes In Stock or 86 / Sold Out
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
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!isKitchen() && !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
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
$isAvailable = isset($json['is_available']) ? (int)$json['is_available'] : (isset($_POST['is_available']) ? (int)$_POST['is_available'] : null);

if ($itemId <= 0 || $isAvailable === null) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID or availability status']);
    exit;
}

try {
    $result = dbTransactional(function () use ($itemId, $isAvailable): array {
        $dish = dbFetchOne("SELECT item_id, item_name, is_available FROM `menu_items` WHERE `item_id` = :id FOR UPDATE", [':id' => $itemId]);
        if (!$dish) {
            return ['found' => false];
        }

        $avail = $isAvailable ? 1 : 0;
        dbQuery("UPDATE `menu_items` SET `is_available` = :av WHERE `item_id` = :id", [
            ':av' => $avail,
            ':id' => $itemId
        ]);

        return [
            'found' => true,
            'item_name' => $dish['item_name'],
            'is_available' => (bool)$avail
        ];
    });
} catch (Throwable $e) {
    error_log("Kitchen toggle stock transaction failed for item {$itemId}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to toggle availability safely. Please try again.']);
    exit;
}

if (!$result['found']) {
    echo json_encode(['success' => false, 'message' => 'Menu item not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'item_id' => $itemId,
    'item_name' => $result['item_name'],
    'is_available' => $result['is_available'],
    'message' => "'{$result['item_name']}' is now " . ($result['is_available'] ? 'IN STOCK (Available)' : '86 / SOLD OUT (Unavailable)')
]);
exit;
