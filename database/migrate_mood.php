<?php
declare(strict_types=1);
/**
 * Migration: Add Mood-Based Food Selection Column and Curate Mood Tags for Dishes
 * Zippy - Online Food Ordering Platform
 * Student: Pooja JP | Course: MCSP-232
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDbConnection();
if (!$pdo) {
    die("Database connection failed.\n");
}

echo "======================================================\n";
echo "1. Checking menu_items table schema for 'mood' column...\n";
echo "======================================================\n";

$cols = dbFetchAll("SHOW COLUMNS FROM `menu_items` LIKE 'mood'");
if (empty($cols)) {
    $pdo->exec("ALTER TABLE `menu_items` ADD COLUMN `mood` VARCHAR(100) NOT NULL DEFAULT 'comfort' AFTER `is_healthy`");
    echo "   -> Added `mood` column to `menu_items`.\n";
} else {
    echo "   -> Column `mood` already exists in `menu_items`.\n";
}

echo "\n2. Updating Dish Catalog with Curated Mood Associations...\n";

$moodMappings = [
    1  => 'party,spicy',       // Meghana Special Chicken Biryani
    2  => 'party,comfort',     // Paneer Tikka Dum Biryani
    3  => 'party,comfort',     // Mutton Dum Biryani (Lucknowi)
    4  => 'comfort',           // Butter Chicken Delhi Style
    5  => 'comfort',           // Paneer Butter Masala
    6  => 'comfort',           // Dal Makhani Slow Handi
    7  => 'comfort,quick_bite',// Garlic Butter Naan (2 Pcs)
    8  => 'quick_bite,spicy',  // Mysore Masala Dosa
    9  => 'quick_bite,comfort',// Steamed Ghee Podi Idli (4 Pcs)
    10 => 'quick_bite,spicy',  // Double Egg Chicken Kathi Roll
    11 => 'quick_bite',        // Crispy Paneer Tikka Roll
    12 => 'party,quick_bite',  // Lush Forest Gourmet Burger
    13 => 'party,quick_bite',  // Crispy Fried Chicken Burger
    14 => 'sweet,comfort',     // Gulab Jamun with Rabri (2 Pcs)
    15 => 'sweet',             // Royal Kesar Rasmalai (2 Pcs)
    16 => 'sweet,comfort',     // Fresh Alphonso Mango Lassi
    17 => 'quick_bite',        // Mint Lime Cooler Soda
    18 => 'healthy',           // Mediterranean Quinoa & Avocado Bowl
    19 => 'healthy',           // Grilled Herb Chicken & Broccoli Bowl
    20 => 'healthy',           // Sprouted Moong & Paneer Super-Salad
    21 => 'healthy',           // Cold-Pressed Green Detox Glow Juice
    22 => 'healthy',           // Roasted Sweet Potato & Hummus Power Bowl
    23 => 'quick_bite,spicy',  // Peri Peri Cheesy Fries
    24 => 'comfort,quick_bite',// Classic Ghee Roast Dosa
    25 => 'sweet,quick_bite'   // Cold Brew Hazelnut Frappe
];

$updateStmt = $pdo->prepare("UPDATE `menu_items` SET `mood` = :mood WHERE `item_id` = :id");

foreach ($moodMappings as $itemId => $mood) {
    $item = dbFetchOne("SELECT item_name FROM `menu_items` WHERE item_id = :id", [':id' => $itemId]);
    if ($item) {
        $updateStmt->execute([':mood' => $mood, ':id' => $itemId]);
        echo "   -> [ID {$itemId}] {$item['item_name']} => Mood: {$mood}\n";
    }
}

// Ensure any extra or new items default gracefully to comfort if empty
$pdo->exec("UPDATE `menu_items` SET `mood` = 'comfort' WHERE `mood` IS NULL OR `mood` = ''");

echo "\nMigration for Mood-Based Food Selection completed successfully!\n";
