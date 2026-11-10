<?php
declare(strict_types=1);
/**
 * Customer AJAX API: Toggle Dish Favorite / Wishlist (`user_favorites`)
 * Zippy - Online Food Ordering Platform
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please sign in to save favorite dishes.']);
    exit;
}

$currentUser = getCurrentUser();
$userId = (int)$currentUser['id'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    echo json_encode([
        'success' => true,
        'favorites' => CatalogService::getUserFavoriteItemIds($userId),
    ]);
    exit;
}

$raw = (string)file_get_contents('php://input');
$jsonBody = json_decode($raw, true);
if (!is_array($jsonBody)) {
    $jsonBody = [];
}

if (!verifyRequestCsrf($jsonBody)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired CSRF token.']);
    exit;
}

$itemId = (int)($_POST['item_id'] ?? $jsonBody['item_id'] ?? 0);
$res = CatalogService::toggleUserFavorite($userId, $itemId);

echo json_encode([
    'success' => $res['ok'],
    'favorited' => $res['favorited'],
    'count' => $res['count'],
    'message' => $res['message'],
]);
exit;
