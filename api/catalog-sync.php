<?php
/**
 * Real-Time Catalog Synchronization & Change Notification API
 * Zippy - Online Food Ordering Platform
 * 
 * Provides real-time synchronization and live notification detection for:
 * 1. Admin Categories: Notifies when dishes are added/updated/assigned, auto-updates counts & KPIs
 * 2. Admin Dishes: Notifies when categories or dishes are added/updated, syncs dropdowns & KPIs
 * 3. Customer Storefront: Detects menu updates & invalidates stale caches in real-time
 */

// Prevent any HTTP caching of this API response
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDBConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$clientVersion = clean_input($_GET['version'] ?? '');
$clientCatVer  = clean_input($_GET['cat_ver'] ?? '');
$clientDishVer = clean_input($_GET['dish_ver'] ?? '');
$portal        = clean_input($_GET['portal'] ?? 'admin');

// 1. Query all categories with their dish counts
$categories = db_fetch_all("
    SELECT c.category_id, c.category_name, c.category_slug, c.category_description,
           c.image_url, c.display_order, c.is_active,
           COUNT(m.item_id) AS total_dishes,
           SUM(CASE WHEN m.is_available = 1 THEN 1 ELSE 0 END) AS active_dishes
    FROM `categories` c
    LEFT JOIN `menu_items` m ON c.category_id = m.category_id
    GROUP BY c.category_id
    ORDER BY c.display_order ASC, c.category_id ASC
");

// 2. Query all menu items key summary attributes
$dishes = db_fetch_all("
    SELECT m.item_id, m.category_id, m.item_name, m.price, m.stock_quantity, 
           m.is_available, m.is_veg, m.is_healthy, m.mood, m.kitchen_station, m.image_url, m.created_at
    FROM `menu_items` m
    ORDER BY m.item_id ASC
");

// 3. Compute Metrics
$totalCategories = count($categories);
$activeCategories = 0;
$emptyCategories = 0;
$totalDishesMapped = 0;
$categoryDishMap = [];
$categoriesList = [];

foreach ($categories as $cat) {
    $cid = (int)$cat['category_id'];
    $dishesCount = (int)$cat['total_dishes'];
    $isActive = (int)$cat['is_active'];

    if ($isActive) {
        $activeCategories++;
    }
    if ($dishesCount === 0) {
        $emptyCategories++;
    }
    $totalDishesMapped += $dishesCount;

    $categoryDishMap[$cid] = [
        'category_id' => $cid,
        'name' => $cat['category_name'],
        'slug' => $cat['category_slug'],
        'total_dishes' => $dishesCount,
        'active_dishes' => (int)($cat['active_dishes'] ?? 0),
        'is_active' => $isActive,
        'display_order' => (int)$cat['display_order']
    ];

    $categoriesList[] = [
        'category_id' => $cid,
        'category_name' => $cat['category_name'],
        'category_slug' => $cat['category_slug'],
        'is_active' => $isActive,
        'display_order' => (int)$cat['display_order']
    ];
}

$totalDishes = count($dishes);
$availableDishes = 0;
$vegDishes = 0;
$nonVegDishes = 0;
$soldOutDishes = 0;

foreach ($dishes as $d) {
    if (!empty($d['is_available']) && (int)$d['stock_quantity'] > 0) {
        $availableDishes++;
    } else {
        $soldOutDishes++;
    }
    if (!empty($d['is_veg'])) {
        $vegDishes++;
    } else {
        $nonVegDishes++;
    }
}

// 4. Latest Added / Modified Items
$latestDish = db_fetch_one("
    SELECT m.item_id, m.item_name, m.category_id, c.category_name, m.price, m.is_available, m.created_at
    FROM `menu_items` m
    LEFT JOIN `categories` c ON m.category_id = c.category_id
    ORDER BY m.item_id DESC
    LIMIT 1
");

$latestCategory = db_fetch_one("
    SELECT category_id, category_name, category_slug, display_order, is_active
    FROM `categories`
    ORDER BY category_id DESC
    LIMIT 1
");

// 5. Build Sub-fingerprints
$catFingerprintData = array_map(function($c) {
    return [
        (int)$c['category_id'],
        $c['category_name'],
        (int)$c['display_order'],
        (int)$c['is_active'],
        (int)$c['total_dishes']
    ];
}, $categories);

$dishFingerprintData = array_map(function($d) {
    return [
        (int)$d['item_id'],
        (int)$d['category_id'],
        $d['item_name'],
        (float)$d['price'],
        (int)$d['stock_quantity'],
        (int)$d['is_available']
    ];
}, $dishes);

$catVer = md5(json_encode($catFingerprintData));
$dishVer = md5(json_encode($dishFingerprintData));
$combinedVer = md5($catVer . '_' . $dishVer);

// 6. Detect Changes
$catChanged = ($clientCatVer !== '' && $clientCatVer !== $catVer);
$dishChanged = ($clientDishVer !== '' && $clientDishVer !== $dishVer);
$hasChanged = ($clientVersion !== '' && $clientVersion !== $combinedVer);

if (!$hasChanged && ($catChanged || $dishChanged)) {
    $hasChanged = true;
}

// 7. Generate contextual update message
$changeMessage = '';
$changeType = 'none';

if ($dishChanged && $catChanged) {
    $changeType = 'both';
    $changeMessage = 'Dishes and Categories catalog updated.';
} elseif ($dishChanged) {
    $changeType = 'dish';
    if ($latestDish) {
        $changeMessage = "Dish update: '{$latestDish['item_name']}' under {$latestDish['category_name']}.";
    } else {
        $changeMessage = "Dishes catalog updated.";
    }
} elseif ($catChanged) {
    $changeType = 'category';
    if ($latestCategory) {
        $changeMessage = "Category update: '{$latestCategory['category_name']}' was updated.";
    } else {
        $changeMessage = "Categories catalog updated.";
    }
} elseif ($hasChanged) {
    $changeType = 'general';
    $changeMessage = "Menu offerings and inventory were updated.";
}

echo json_encode([
    'success' => true,
    'version' => $combinedVer,
    'cat_ver' => $catVer,
    'dish_ver' => $dishVer,
    'changed' => $hasChanged,
    'change_type' => $changeType,
    'message' => $changeMessage,
    'timestamp' => date('Y-m-d H:i:s'),
    'metrics' => [
        'total_categories' => $totalCategories,
        'active_categories' => $activeCategories,
        'empty_categories' => $emptyCategories,
        'total_dishes_mapped' => $totalDishesMapped,
        'total_dishes' => $totalDishes,
        'available_dishes' => $availableDishes,
        'veg_dishes' => $vegDishes,
        'nonveg_dishes' => $nonVegDishes,
        'sold_out_dishes' => $soldOutDishes
    ],
    'category_dish_counts' => $categoryDishMap,
    'categories_list' => $categoriesList,
    'latest_dish' => $latestDish,
    'latest_category' => $latestCategory
]);
