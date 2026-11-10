<?php
declare(strict_types=1);
/**
 * Migration: Create and Seed food_moods Table for Dynamic Admin Mood Management
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDbConnection();
if (!$pdo) {
    die("Database connection failed.\n");
}

echo "======================================================\n";
echo "1. Creating food_moods Table...\n";
echo "======================================================\n";

$sql = "
CREATE TABLE IF NOT EXISTS `food_moods` (
    `mood_id` INT AUTO_INCREMENT PRIMARY KEY,
    `mood_key` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `short_name` VARCHAR(50) NOT NULL,
    `emoji` VARCHAR(20) NOT NULL DEFAULT '🍽️',
    `icon` VARCHAR(50) NOT NULL DEFAULT 'fa-solid fa-utensils',
    `image_url` VARCHAR(255) NOT NULL DEFAULT '',
    `tagline` VARCHAR(255) NOT NULL DEFAULT '',
    `badge_bg` VARCHAR(20) NOT NULL DEFAULT '#FFF3CD',
    `badge_color` VARCHAR(20) NOT NULL DEFAULT '#856404',
    `border_color` VARCHAR(20) NOT NULL DEFAULT '#FFE69C',
    `accent` VARCHAR(20) NOT NULL DEFAULT '#FFC107',
    `display_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$pdo->exec($sql);
echo "   -> Table `food_moods` created/verified successfully.\n";

echo "\n2. Seeding Initial 6 Food Moods...\n";

$initialMoods = [
    [
        'mood_key'      => 'comfort',
        'name'          => 'Comfort & Soul',
        'short_name'    => 'Comfort',
        'emoji'         => '🛋️',
        'icon'          => 'fa-solid fa-mug-hot',
        'image_url'     => 'assets/images/moods/comfort.jpg',
        'tagline'       => 'Warm, soulful curries, rich gravies & buttery breads to unwind & destress',
        'badge_bg'      => '#FFF3CD',
        'badge_color'   => '#856404',
        'border_color'  => '#FFE69C',
        'accent'        => '#FFC107',
        'display_order' => 1,
        'is_active'     => 1
    ],
    [
        'mood_key'      => 'party',
        'name'          => 'Party & Celebration',
        'short_name'    => 'Party Feast',
        'emoji'         => '🎉',
        'icon'          => 'fa-solid fa-champagne-glasses',
        'image_url'     => 'assets/images/moods/party.jpg',
        'tagline'       => 'Royal aromatic biryanis & loaded gourmet feast burgers for celebrations',
        'badge_bg'      => '#E8E7FD',
        'badge_color'   => '#3B28CC',
        'border_color'  => '#C5C1F9',
        'accent'        => '#6F42C1',
        'display_order' => 2,
        'is_active'     => 1
    ],
    [
        'mood_key'      => 'healthy',
        'name'          => 'Energetic & Fit',
        'short_name'    => 'Healthy / Fit',
        'emoji'         => '⚡',
        'icon'          => 'fa-solid fa-leaf',
        'image_url'     => 'assets/images/moods/healthy.jpg',
        'tagline'       => 'Nutrient-dense superfoods, high-protein bowls & cold-pressed detox juices',
        'badge_bg'      => '#CEFFDC',
        'badge_color'   => '#1C2B20',
        'border_color'  => '#68BA7F',
        'accent'        => '#28A745',
        'display_order' => 3,
        'is_active'     => 1
    ],
    [
        'mood_key'      => 'quick_bite',
        'name'          => 'Lazy & Quick Bites',
        'short_name'    => 'Quick Bites',
        'emoji'         => '🍿',
        'icon'          => 'fa-solid fa-bolt',
        'image_url'     => 'assets/images/moods/quick_bite.jpg',
        'tagline'       => 'Crispy kathi rolls, quick snacks, dosas & sodas delivered in under 15 mins',
        'badge_bg'      => '#D1ECF1',
        'badge_color'   => '#0C5460',
        'border_color'  => '#BEE5EB',
        'accent'        => '#17A2B8',
        'display_order' => 4,
        'is_active'     => 1
    ],
    [
        'mood_key'      => 'spicy',
        'name'          => 'Fiery & Spicy',
        'short_name'    => 'Fiery Spicy',
        'emoji'         => '🔥',
        'icon'          => 'fa-solid fa-fire',
        'image_url'     => 'assets/images/moods/spicy.jpg',
        'tagline'       => 'Authentic Andhra spices, fiery red chutneys & bold thrilling masalas',
        'badge_bg'      => '#F8D7DA',
        'badge_color'   => '#721C24',
        'border_color'  => '#F5C6CB',
        'accent'        => '#DC3545',
        'display_order' => 5,
        'is_active'     => 1
    ],
    [
        'mood_key'      => 'sweet',
        'name'          => 'Sweet Tooth Craving',
        'short_name'    => 'Sweet Craving',
        'emoji'         => '💖',
        'icon'          => 'fa-solid fa-heart',
        'image_url'     => 'assets/images/moods/sweet.jpg',
        'tagline'       => 'Royal rasmalai, hot gulab jamun with rabri & chilled Alphonso mango lassi',
        'badge_bg'      => '#FCE4EC',
        'badge_color'   => '#880E4F',
        'border_color'  => '#F8BBD0',
        'accent'        => '#E83E8C',
        'display_order' => 6,
        'is_active'     => 1
    ]
];

$stmt = $pdo->prepare("
    INSERT INTO `food_moods` 
    (`mood_key`, `name`, `short_name`, `emoji`, `icon`, `image_url`, `tagline`, `badge_bg`, `badge_color`, `border_color`, `accent`, `display_order`, `is_active`)
    VALUES (:key, :name, :short_name, :emoji, :icon, :image_url, :tagline, :badge_bg, :badge_color, :border_color, :accent, :display_order, :is_active)
    ON DUPLICATE KEY UPDATE 
        `name` = VALUES(`name`),
        `short_name` = VALUES(`short_name`),
        `emoji` = VALUES(`emoji`),
        `icon` = VALUES(`icon`),
        `image_url` = VALUES(`image_url`),
        `tagline` = VALUES(`tagline`),
        `badge_bg` = VALUES(`badge_bg`),
        `badge_color` = VALUES(`badge_color`),
        `border_color` = VALUES(`border_color`),
        `accent` = VALUES(`accent`),
        `display_order` = VALUES(`display_order`),
        `is_active` = VALUES(`is_active`)
");

foreach ($initialMoods as $m) {
    $stmt->execute([
        ':key'           => $m['mood_key'],
        ':name'          => $m['name'],
        ':short_name'    => $m['short_name'],
        ':emoji'         => $m['emoji'],
        ':icon'          => $m['icon'],
        ':image_url'     => $m['image_url'],
        ':tagline'       => $m['tagline'],
        ':badge_bg'      => $m['badge_bg'],
        ':badge_color'   => $m['badge_color'],
        ':border_color'  => $m['border_color'],
        ':accent'        => $m['accent'],
        ':display_order' => $m['display_order'],
        ':is_active'     => $m['is_active']
    ]);
    echo "   -> Synced mood: {$m['name']} ({$m['mood_key']})\n";
}

echo "\nMigration completed successfully!\n";
