<?php
/**
 * Migration: Add Healthy Food Category, Dishes, and is_healthy Column
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDBConnection();
if (!$pdo) {
    die("Database connection failed.\n");
}

echo "1. Checking menu_items table schema...\n";
$cols = db_fetch_all("SHOW COLUMNS FROM `menu_items` LIKE 'is_healthy'");
if (empty($cols)) {
    $pdo->exec("ALTER TABLE `menu_items` ADD COLUMN `is_healthy` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_veg`");
    echo "   -> Added `is_healthy` column to `menu_items`.\n";
} else {
    echo "   -> Column `is_healthy` already exists.\n";
}

echo "2. Checking 'Healthy & Fit' category...\n";
$cat = db_fetch_one("SELECT * FROM `categories` WHERE `category_slug` = 'healthy' OR `category_name` = 'Healthy & Fit'");
if (!$cat) {
    // Update display orders of existing categories to place Healthy nicely at position 4
    $pdo->exec("UPDATE `categories` SET `display_order` = `display_order` + 1 WHERE `display_order` >= 4");
    
    $stmt = $pdo->prepare("
        INSERT INTO `categories` (`category_name`, `category_description`, `category_slug`, `image_url`, `display_order`, `is_active`) 
        VALUES (:name, :desc, :slug, :img, :ord, 1)
    ");
    $stmt->execute([
        ':name' => 'Healthy & Fit',
        ':desc' => 'Nutrient-dense superfood bowls, fresh organic salads, high-protein keto & detox meals',
        ':slug' => 'healthy',
        ':img'  => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=240&q=80',
        ':ord'  => 4
    ]);
    $catId = (int)$pdo->lastInsertId();
    echo "   -> Inserted 'Healthy & Fit' category (ID: {$catId}).\n";
} else {
    $catId = (int)$cat['category_id'];
    echo "   -> 'Healthy & Fit' category exists (ID: {$catId}).\n";
}

echo "3. Seeding Healthy Food Dishes...\n";
$healthyDishes = [
    [
        'name' => 'Mediterranean Quinoa & Avocado Bowl',
        'desc' => 'Wholesome tricolor organic quinoa, hass avocado, cherry tomatoes, cucumbers, roasted chickpeas, feta cheese, and citrus lemon-herb vinaigrette.',
        'price' => 249.00,
        'stock' => 35,
        'is_veg' => 1,
        'is_healthy' => 1,
        'prep' => 15,
        'station' => 'Salad & Cold Bar',
        'image' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=600&q=80'
    ],
    [
        'name' => 'Grilled Herb Chicken & Broccoli Bowl',
        'desc' => 'Lean rosemary chargrilled chicken breast paired with steamed broccoli florets, brown basmati rice, edamame, and cold-pressed olive-herb dressing.',
        'price' => 299.00,
        'stock' => 25,
        'is_veg' => 0,
        'is_healthy' => 1,
        'prep' => 18,
        'station' => 'Grill Station',
        'image' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=600&q=80'
    ],
    [
        'name' => 'Sprouted Moong & Paneer Super-Salad',
        'desc' => 'Organic sprouted green moong, lightly roasted malai paneer cubes, pomegranate pearls, baby spinach, roasted pumpkin seeds, and tangy mint-amchur dressing.',
        'price' => 189.00,
        'stock' => 40,
        'is_veg' => 1,
        'is_healthy' => 1,
        'prep' => 12,
        'station' => 'Salad & Cold Bar',
        'image' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=600&q=80'
    ],
    [
        'name' => 'Cold-Pressed Green Detox Glow Juice',
        'desc' => '100% pure raw cold-pressed celery, cucumber, baby spinach, green apple, ginger, and Meyer lemon. Zero added sugar or preservatives.',
        'price' => 129.00,
        'stock' => 50,
        'is_veg' => 1,
        'is_healthy' => 1,
        'prep' => 5,
        'station' => 'Beverage Bar',
        'image' => 'https://images.unsplash.com/photo-1613478223719-2ab802602423?auto=format&fit=crop&w=600&q=80'
    ],
    [
        'name' => 'Roasted Sweet Potato & Hummus Power Bowl',
        'desc' => 'Smoked paprika sweet potato chunks, house-made garlic hummus, baby arugula, kalamata olives, toasted almonds, and tahini drizzle.',
        'price' => 219.00,
        'stock' => 30,
        'is_veg' => 1,
        'is_healthy' => 1,
        'prep' => 15,
        'station' => 'Salad & Cold Bar',
        'image' => 'https://images.unsplash.com/photo-1543339308-43e59d6b73a6?auto=format&fit=crop&w=600&q=80'
    ]
];

$insertStmt = $pdo->prepare("
    INSERT INTO `menu_items` 
    (`category_id`, `item_name`, `description`, `price`, `stock_quantity`, `is_available`, `is_veg`, `is_healthy`, `prep_time_minutes`, `kitchen_station`, `image_url`) 
    VALUES (:cid, :name, :desc, :price, :stock, 1, :veg, :healthy, :prep, :station, :img)
");

foreach ($healthyDishes as $dish) {
    $existing = db_fetch_one("SELECT `item_id` FROM `menu_items` WHERE `item_name` = :name", [':name' => $dish['name']]);
    if ($existing) {
        $pdo->prepare("UPDATE `menu_items` SET `category_id` = :cid, `is_healthy` = 1, `price` = :price, `stock_quantity` = :stock, `image_url` = :img WHERE `item_id` = :id")
            ->execute([
                ':cid' => $catId,
                ':price' => $dish['price'],
                ':stock' => $dish['stock'],
                ':img' => $dish['image'],
                ':id' => $existing['item_id']
            ]);
        echo "   -> Updated dish: {$dish['name']} (ID: {$existing['item_id']})\n";
    } else {
        $insertStmt->execute([
            ':cid' => $catId,
            ':name' => $dish['name'],
            ':desc' => $dish['desc'],
            ':price' => $dish['price'],
            ':stock' => $dish['stock'],
            ':veg' => $dish['is_veg'],
            ':healthy' => $dish['is_healthy'],
            ':prep' => $dish['prep'],
            ':station' => $dish['station'],
            ':img' => $dish['image']
        ]);
        $newId = $pdo->lastInsertId();
        echo "   -> Added dish: {$dish['name']} (ID: {$newId})\n";
    }
}

echo "Migration completed successfully!\n";
