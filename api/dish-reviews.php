<?php
/**
 * Dish Food Reviews & Star Rating API Endpoint
 * Zippy - Online Food Ordering Platform
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$action = $_REQUEST['action'] ?? 'get_reviews';
$itemId = isset($_REQUEST['dish_id']) ? (int)$_REQUEST['dish_id'] : (int)($_REQUEST['item_id'] ?? 0);

if ($itemId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid dish ID provided.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $action === 'submit_review') {
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $reviewText = clean_input($_POST['review_text'] ?? '');
    $tags = clean_input($_POST['tags'] ?? '');
    $currentUser = current_user();
    $userId = $currentUser ? $currentUser['id'] : 2; // Default to demo customer

    $res = db_query("
        INSERT INTO `dish_reviews` (`item_id`, `user_id`, `rating`, `review_text`, `tags`)
        VALUES (:iid, :uid, :r, :t, :tags)
    ", [
        ':iid' => $itemId,
        ':uid' => $userId,
        ':r' => $rating,
        ':t' => $reviewText,
        ':tags' => $tags
    ]);

    if ($res) {
        // Recalculate average rating & increment total_reviews
        $stats = db_fetch_one("
            SELECT AVG(rating) AS avg_rating, COUNT(*) AS count_reviews 
            FROM `dish_reviews` 
            WHERE `item_id` = :iid
        ", [':iid' => $itemId]);

        $newRating = round((float)($stats['avg_rating'] ?? $rating), 1);
        $newCount = (int)($stats['count_reviews'] ?? 1);

        db_query("
            UPDATE `menu_items` 
            SET `rating` = :r, `total_reviews` = GREATEST(`total_reviews` + 1, :cnt) 
            WHERE `item_id` = :iid
        ", [
            ':r' => $newRating,
            ':cnt' => $newCount,
            ':iid' => $itemId
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Review and star rating submitted successfully! ⭐',
            'rating' => $newRating,
            'total_reviews' => $newCount
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to record dish review in database.']);
        exit;
    }
}

// Default: GET reviews for dish
$dish = db_fetch_one("
    SELECT m.*, c.category_name 
    FROM `menu_items` m
    JOIN `categories` c ON m.category_id = c.category_id
    WHERE m.item_id = :id
", [':id' => $itemId]);

if (!$dish) {
    echo json_encode(['success' => false, 'error' => 'Dish not found.']);
    exit;
}

$reviews = get_dish_reviews($itemId, 20);

// Rating distribution calculation
$distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($reviews as $rev) {
    $r = (int)$rev['rating'];
    if (isset($distribution[$r])) {
        $distribution[$r]++;
    }
}

echo json_encode([
    'success' => true,
    'dish' => [
        'item_id' => (int)$dish['item_id'],
        'item_name' => $dish['item_name'],
        'category_name' => $dish['category_name'],
        'price' => (float)$dish['price'],
        'image_url' => $dish['image_url'],
        'is_veg' => (bool)$dish['is_veg'],
        'rating' => (float)($dish['rating'] ?? 4.5),
        'total_reviews' => (int)($dish['total_reviews'] ?? count($reviews))
    ],
    'distribution' => $distribution,
    'reviews' => array_map(function($r) {
        return [
            'review_id' => (int)$r['dish_review_id'],
            'customer_name' => $r['customer_name'] ?? 'Food Lover',
            'rating' => (int)$r['rating'],
            'review_text' => $r['review_text'] ?? '',
            'tags' => !empty($r['tags']) ? explode(',', $r['tags']) : [],
            'date' => date('d M Y', strtotime($r['created_at']))
        ];
    }, $reviews)
]);
