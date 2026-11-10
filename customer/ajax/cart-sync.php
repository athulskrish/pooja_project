<?php
declare(strict_types=1);
/**
 * Customer AJAX API: Server-Side Persistent Cart Synchronization (`cart_items`)
 * Zippy - Online Food Ordering Platform
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'authenticated' => false, 'items' => []]);
    exit;
}

$currentUser = getCurrentUser();
$userId = (int)$currentUser['id'];
$pdo = getDbConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database unavailable']);
    exit;
}

$fetchHydratedCart = static function (int $uid): array {
    $rows = dbFetchAll("
        SELECT ci.*, m.item_name, m.price AS base_price, m.is_veg, m.image_url, m.is_available, m.is_active
        FROM `cart_items` ci
        JOIN `menu_items` m ON ci.item_id = m.item_id
        WHERE ci.user_id = :uid
          AND m.is_active = 1
          AND m.is_available = 1
        ORDER BY ci.updated_at ASC, ci.cart_item_id ASC
    ", [':uid' => $uid]);

    $items = [];
    foreach ($rows as $r) {
        $customs = !empty($r['customizations_json']) ? json_decode((string)$r['customizations_json'], true) : [];
        if (!is_array($customs)) {
            $customs = [];
        }
        $addonSum = 0.0;
        foreach ($customs as $c) {
            $addonSum += (float)($c['price'] ?? 0);
        }
        $unitPrice = (float)$r['base_price'] + $addonSum;
        $items[] = [
            'id' => (int)$r['item_id'],
            'cartItemId' => (string)$r['cart_key'],
            'name' => (string)$r['item_name'],
            'price' => round($unitPrice, 2),
            'basePrice' => (float)$r['base_price'],
            'isVeg' => (bool)$r['is_veg'],
            'restaurant' => 'Meghana Foods',
            'imageUrl' => getDishImageUrl((string)($r['image_url'] ?? '')),
            'qty' => max(1, (int)$r['quantity']),
            'customizations' => $customs,
            'customizationText' => (string)($r['customization_text'] ?? ''),
        ];
    }
    return $items;
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    try {
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'items' => $fetchHydratedCart($userId),
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'authenticated' => true, 'items' => []]);
    }
    exit;
}

$raw = (string)file_get_contents('php://input');
$jsonBody = json_decode($raw, true);
if (!is_array($jsonBody)) {
    $jsonBody = [];
}

if (!verifyRequestCsrf($jsonBody)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$action = (string)($jsonBody['action'] ?? 'replace');
$incomingItems = isset($jsonBody['items']) && is_array($jsonBody['items']) ? $jsonBody['items'] : [];

try {
    $syncedItems = dbTransactional(static function () use ($userId, $action, $incomingItems, $fetchHydratedCart): array {
        if ($action === 'clear') {
            dbQuery("DELETE FROM `cart_items` WHERE `user_id` = :uid", [':uid' => $userId]);
            return [];
        }

        if ($action === 'replace') {
            dbQuery("DELETE FROM `cart_items` WHERE `user_id` = :uid", [':uid' => $userId]);
        }

        foreach ($incomingItems as $item) {
            $itemId = (int)($item['id'] ?? $item['item_id'] ?? 0);
            $qty = max(1, (int)($item['qty'] ?? 1));
            $cartKey = substr(cleanInput((string)($item['cartItemId'] ?? $itemId)), 0, 120);
            if ($itemId <= 0 || $cartKey === '') {
                continue;
            }
            $dishExists = dbFetchVal("SELECT `price` FROM `menu_items` WHERE `item_id` = :iid AND `is_active` = 1", [':iid' => $itemId]);
            if ($dishExists === false || $dishExists === null) {
                continue;
            }
            $customs = isset($item['customizations']) && is_array($item['customizations']) ? $item['customizations'] : [];
            $customJson = !empty($customs) ? json_encode($customs, JSON_UNESCAPED_UNICODE) : null;
            $customText = cleanInput((string)($item['customizationText'] ?? ''));
            $unitPrice = (float)($item['price'] ?? $dishExists);

            if ($action === 'merge') {
                $existing = dbFetchOne(
                    "SELECT `cart_item_id`, `quantity` FROM `cart_items` WHERE `user_id` = :uid AND `cart_key` = :ck FOR UPDATE",
                    [':uid' => $userId, ':ck' => $cartKey]
                );
                if ($existing) {
                    $newQty = max((int)$existing['quantity'], $qty);
                    dbQuery(
                        "UPDATE `cart_items` SET `quantity` = :q, `unit_price` = :p WHERE `cart_item_id` = :cid",
                        [':q' => $newQty, ':p' => $unitPrice, ':cid' => (int)$existing['cart_item_id']]
                    );
                    continue;
                }
            }

            dbQuery("
                INSERT INTO `cart_items` (`user_id`, `item_id`, `cart_key`, `quantity`, `customizations_json`, `customization_text`, `unit_price`)
                VALUES (:uid, :iid, :ck, :qty, :cjson, :ctxt, :price)
                ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`), `customizations_json` = VALUES(`customizations_json`), `customization_text` = VALUES(`customization_text`), `unit_price` = VALUES(`unit_price`)
            ", [
                ':uid' => $userId,
                ':iid' => $itemId,
                ':ck' => $cartKey,
                ':qty' => $qty,
                ':cjson' => $customJson,
                ':ctxt' => $customText !== '' ? $customText : null,
                ':price' => $unitPrice,
            ]);
        }

        return $fetchHydratedCart($userId);
    });

    echo json_encode([
        'success' => true,
        'authenticated' => true,
        'items' => $syncedItems,
    ]);
} catch (Throwable $e) {
    error_log("cart-sync failed for user {$userId}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to synchronize cart']);
}
exit;
