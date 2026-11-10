<?php
declare(strict_types=1);
/**
 * Database Migration: V2 Feature Suite
 * - FCFS Scheduled Slot Capacity Throttling (`scheduled_slot_caps`)
 * - Rider SOS / Failed Delivery fields on `delivery_dispatches`
 * - Support Ticket Replies (`support_ticket_replies`) & ticket assignment
 * - Dynamic DB Coupons (`coupons`, `coupon_redemptions`)
 * - Kitchen Station Prep Checklists (`order_items.is_prepped`, `prepped_at`)
 * - Persistent Cart (`cart_items`) & Favorites (`user_favorites`)
 * - Loyalty Points (`users.loyalty_points`, `orders.loyalty_*`, `loyalty_transactions`)
 * - Indian Coordinates & Distance Pricing (`user_addresses.latitude/longitude/distance_km`, `orders.distance_km`)
 * - Allergen/Nutrition & Dietary Preferences (`menu_items` macros/allergens/spice, `users.dietary_preferences`)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDbConnection();
if (!$pdo) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

$addColumnIfMissing = static function (PDO $conn, string $table, string $column, string $definition): bool {
    $safeTable = assertSqlIdentifier($table, 'migration table');
    $safeCol = assertSqlIdentifier($column, 'migration column');
    $exists = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeCol}'")->fetchAll();
    if (empty($exists)) {
        $conn->exec("ALTER TABLE `{$safeTable}` ADD COLUMN {$definition}");
        return true;
    }
    return false;
};

echo "Running Zippy V2 Features Migration...\n";

// 1. Users: loyalty_points & dietary_preferences
if ($addColumnIfMissing($pdo, 'users', 'loyalty_points', "`loyalty_points` INT NOT NULL DEFAULT 0 AFTER `security_answer_hash`")) {
    $pdo->exec("UPDATE `users` SET `loyalty_points` = 150 WHERE `user_id` = 2 AND `loyalty_points` = 0");
    $pdo->exec("UPDATE `users` SET `loyalty_points` = 100 WHERE `user_id` = 3 AND `loyalty_points` = 0");
    $pdo->exec("UPDATE `users` SET `loyalty_points` = 80 WHERE `user_id` = 4 AND `loyalty_points` = 0");
    echo " + Added users.loyalty_points\n";
}
if ($addColumnIfMissing($pdo, 'users', 'dietary_preferences', "`dietary_preferences` VARCHAR(255) NULL DEFAULT NULL AFTER `loyalty_points`")) {
    echo " + Added users.dietary_preferences\n";
}

// 2. User addresses: Indian lat/lng/distance_km
$addedLat = $addColumnIfMissing($pdo, 'user_addresses', 'latitude', "`latitude` DECIMAL(10, 8) NULL DEFAULT NULL AFTER `pincode`");
$addedLng = $addColumnIfMissing($pdo, 'user_addresses', 'longitude', "`longitude` DECIMAL(11, 8) NULL DEFAULT NULL AFTER `latitude`");
$addedDist = $addColumnIfMissing($pdo, 'user_addresses', 'distance_km', "`distance_km` DECIMAL(5, 2) NULL DEFAULT NULL AFTER `longitude`");
if ($addedLat || $addedLng || $addedDist) {
    $seedCoords = [
        1 => [12.92790000, 77.67620000, 6.80],
        2 => [12.93690000, 77.69140000, 7.15],
        3 => [12.97180000, 77.64120000, 0.74],
        4 => [12.96100000, 77.63870000, 1.95],
        5 => [12.96980000, 77.74990000, 11.86],
        6 => [12.92550000, 77.68100000, 7.31],
    ];
    $stmt = $pdo->prepare("UPDATE `user_addresses` SET `latitude` = :lat, `longitude` = :lng, `distance_km` = :dist WHERE `address_id` = :id AND `latitude` IS NULL");
    foreach ($seedCoords as $aid => [$lat, $lng, $dist]) {
        $stmt->execute([':lat' => $lat, ':lng' => $lng, ':dist' => $dist, ':id' => $aid]);
    }
    echo " + Added Indian geolocation columns to user_addresses\n";
}

// 3. Menu items: Nutrition & Allergens
$nutriAdded = false;
$nutriAdded = $addColumnIfMissing($pdo, 'menu_items', 'calories_kcal', "`calories_kcal` INT NULL DEFAULT NULL AFTER `kitchen_station`") || $nutriAdded;
$nutriAdded = $addColumnIfMissing($pdo, 'menu_items', 'protein_g', "`protein_g` DECIMAL(5,1) NULL DEFAULT NULL AFTER `calories_kcal`") || $nutriAdded;
$nutriAdded = $addColumnIfMissing($pdo, 'menu_items', 'carbs_g', "`carbs_g` DECIMAL(5,1) NULL DEFAULT NULL AFTER `protein_g`") || $nutriAdded;
$nutriAdded = $addColumnIfMissing($pdo, 'menu_items', 'fat_g', "`fat_g` DECIMAL(5,1) NULL DEFAULT NULL AFTER `carbs_g`") || $nutriAdded;
$nutriAdded = $addColumnIfMissing($pdo, 'menu_items', 'allergens', "`allergens` VARCHAR(255) NULL DEFAULT NULL AFTER `fat_g`") || $nutriAdded;
$nutriAdded = $addColumnIfMissing($pdo, 'menu_items', 'spice_level', "`spice_level` ENUM('MILD', 'MEDIUM', 'SPICY', 'EXTRA_SPICY') NOT NULL DEFAULT 'MEDIUM' AFTER `allergens`") || $nutriAdded;
if ($nutriAdded) {
    $nutritionSeed = [
        1 => [680, 36.0, 74.0, 24.0, 'dairy', 'SPICY'],
        2 => [610, 24.0, 70.0, 23.0, 'dairy,nuts', 'MEDIUM'],
        3 => [740, 38.0, 72.0, 30.0, 'dairy', 'MEDIUM'],
        4 => [560, 34.0, 18.0, 36.0, 'dairy,nuts', 'MILD'],
        5 => [520, 20.0, 22.0, 34.0, 'dairy,nuts', 'MILD'],
        6 => [440, 18.0, 48.0, 18.0, 'dairy', 'MILD'],
        7 => [310, 8.0, 52.0, 8.0, 'gluten,dairy', 'MILD'],
        8 => [380, 9.0, 62.0, 11.0, null, 'SPICY'],
        9 => [290, 8.5, 48.0, 7.0, 'dairy', 'MEDIUM'],
        10 => [540, 29.0, 50.0, 22.0, 'gluten,egg,dairy', 'SPICY'],
        11 => [460, 19.0, 48.0, 19.0, 'gluten,dairy', 'MEDIUM'],
        12 => [620, 21.0, 64.0, 28.0, 'gluten,dairy,soy', 'MEDIUM'],
        13 => [670, 32.0, 58.0, 31.0, 'gluten,dairy,egg', 'SPICY'],
        14 => [390, 7.0, 54.0, 16.0, 'dairy,gluten,nuts', 'MILD'],
        15 => [340, 11.0, 42.0, 14.0, 'dairy,nuts', 'MILD'],
        16 => [240, 6.0, 38.0, 6.5, 'dairy', 'MILD'],
        17 => [85, 0.5, 21.0, 0.0, null, 'MILD'],
        18 => [390, 16.0, 44.0, 17.0, 'dairy', 'MILD'],
        19 => [430, 41.0, 36.0, 12.0, 'soy', 'MILD'],
        20 => [320, 22.0, 28.0, 12.0, 'dairy', 'MILD'],
        21 => [95, 2.0, 22.0, 0.5, null, 'MILD'],
        22 => [410, 14.0, 52.0, 16.0, 'nuts', 'MILD'],
        23 => [450, 8.0, 54.0, 22.0, 'dairy', 'SPICY'],
        24 => [410, 9.5, 58.0, 15.0, 'dairy', 'MEDIUM'],
        25 => [260, 5.5, 36.0, 9.5, 'dairy,nuts', 'MILD'],
    ];
    $nStmt = $pdo->prepare("
        UPDATE `menu_items`
        SET `calories_kcal` = :cal, `protein_g` = :pro, `carbs_g` = :carb, `fat_g` = :fat, `allergens` = :alg, `spice_level` = :sp
        WHERE `item_id` = :id AND `calories_kcal` IS NULL
    ");
    foreach ($nutritionSeed as $iid => [$cal, $pro, $carb, $fat, $alg, $sp]) {
        $nStmt->execute([':cal' => $cal, ':pro' => $pro, ':carb' => $carb, ':fat' => $fat, ':alg' => $alg, ':sp' => $sp, ':id' => $iid]);
    }
    echo " + Added nutrition & allergen metadata to menu_items\n";
}

// 4. Orders & Order Items
$addColumnIfMissing($pdo, 'orders', 'loyalty_points_used', "`loyalty_points_used` INT NOT NULL DEFAULT 0 AFTER `discount_amount`");
$addColumnIfMissing($pdo, 'orders', 'loyalty_discount', "`loyalty_discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `loyalty_points_used`");
$addColumnIfMissing($pdo, 'orders', 'distance_km', "`distance_km` DECIMAL(5,2) NULL DEFAULT NULL AFTER `loyalty_discount`");
$addColumnIfMissing($pdo, 'order_items', 'is_prepped', "`is_prepped` TINYINT(1) NOT NULL DEFAULT 0 AFTER `customization_total`");
$addColumnIfMissing($pdo, 'order_items', 'prepped_at', "`prepped_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_prepped`");

// 5. Delivery Dispatches: Failure fields
$addColumnIfMissing($pdo, 'delivery_dispatches', 'failure_reason', "`failure_reason` VARCHAR(255) NULL DEFAULT NULL AFTER `delivery_status`");
$addColumnIfMissing($pdo, 'delivery_dispatches', 'issue_notes', "`issue_notes` TEXT NULL DEFAULT NULL AFTER `failure_reason`");
$addColumnIfMissing($pdo, 'delivery_dispatches', 'failed_at', "`failed_at` TIMESTAMP NULL DEFAULT NULL AFTER `issue_notes`");

// 6. Support Tickets enhancements & replies table
$addColumnIfMissing($pdo, 'support_tickets', 'assigned_admin_id', "`assigned_admin_id` INT NULL DEFAULT NULL AFTER `ticket_status`");
$addColumnIfMissing($pdo, 'support_tickets', 'updated_at', "`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `support_ticket_replies` (
        `reply_id` INT AUTO_INCREMENT PRIMARY KEY,
        `ticket_id` INT NOT NULL,
        `user_id` INT NULL,
        `sender_role` ENUM('CUSTOMER', 'ADMIN') NOT NULL DEFAULT 'ADMIN',
        `sender_name` VARCHAR(120) NOT NULL,
        `reply_message` TEXT NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_ticket_replies_ticket` (`ticket_id`),
        CONSTRAINT `fk_ticket_replies_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`ticket_id`) ON DELETE CASCADE,
        CONSTRAINT `fk_ticket_replies_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 7. Coupons & Coupon Redemptions
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `coupons` (
        `coupon_id` INT AUTO_INCREMENT PRIMARY KEY,
        `code` VARCHAR(50) NOT NULL UNIQUE,
        `title` VARCHAR(120) NOT NULL,
        `description` VARCHAR(255) NOT NULL,
        `discount_type` ENUM('percentage', 'flat') NOT NULL DEFAULT 'percentage',
        `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `max_discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `min_order` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `valid_from` DATETIME NULL DEFAULT NULL,
        `valid_until` DATETIME NULL DEFAULT NULL,
        `usage_limit` INT NOT NULL DEFAULT 0,
        `per_user_limit` INT NOT NULL DEFAULT 0,
        `first_order_only` TINYINT(1) NOT NULL DEFAULT 0,
        `used_count` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `coupon_redemptions` (
        `redemption_id` INT AUTO_INCREMENT PRIMARY KEY,
        `coupon_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `order_id` INT NOT NULL,
        `discount_applied` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `redeemed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_coupon_user` (`coupon_id`, `user_id`),
        CONSTRAINT `fk_redemption_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`coupon_id`) ON DELETE CASCADE,
        CONSTRAINT `fk_redemption_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
        CONSTRAINT `fk_redemption_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$couponCnt = (int)$pdo->query("SELECT COUNT(*) FROM `coupons`")->fetchColumn();
if ($couponCnt === 0) {
    $pdo->exec("
        INSERT INTO `coupons` (`code`, `title`, `description`, `discount_type`, `discount_value`, `max_discount`, `min_order`, `valid_until`, `usage_limit`, `per_user_limit`, `first_order_only`, `used_count`, `is_active`) VALUES
        ('LUSHFOREST', 'Lush Forest Special', '20% OFF up to ₹100 on orders above ₹299 (Figma Lush Special)', 'percentage', 20.00, 100.00, 299.00, DATE_ADD(NOW(), INTERVAL 365 DAY), 0, 0, 0, 1, 1),
        ('ZIPPY50', 'Zippy Mega Feast', '50% OFF up to ₹120 on your order above ₹399', 'percentage', 50.00, 120.00, 399.00, DATE_ADD(NOW(), INTERVAL 365 DAY), 0, 0, 0, 2, 1),
        ('WELCOME', 'Welcome Bonus', 'Flat ₹50 OFF for welcome customers on orders above ₹199', 'flat', 50.00, 50.00, 199.00, DATE_ADD(NOW(), INTERVAL 365 DAY), 0, 0, 0, 1, 1),
        ('FIRSTBITE', 'First Order Treat', 'Flat ₹75 OFF on your very first Zippy order above ₹249', 'flat', 75.00, 75.00, 249.00, DATE_ADD(NOW(), INTERVAL 365 DAY), 500, 1, 1, 0, 1)
    ");
    echo " + Seeded initial dynamic coupons\n";
}

// 8. Scheduled Slot Caps (FCFS)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `scheduled_slot_caps` (
        `slot_key` VARCHAR(120) PRIMARY KEY,
        `slot_date` DATE NOT NULL,
        `slot_label` VARCHAR(100) NOT NULL,
        `max_capacity` INT NOT NULL DEFAULT 5,
        `booked_count` INT NOT NULL DEFAULT 0,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_slot_date` (`slot_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 9. Persistent Cart & Favorites
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `cart_items` (
        `cart_item_id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `item_id` INT NOT NULL,
        `cart_key` VARCHAR(120) NOT NULL,
        `quantity` INT NOT NULL DEFAULT 1,
        `customizations_json` TEXT NULL,
        `customization_text` VARCHAR(500) NULL,
        `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_user_cart_key` (`user_id`, `cart_key`),
        CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
        CONSTRAINT `fk_cart_item` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `user_favorites` (
        `favorite_id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `item_id` INT NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_user_favorite` (`user_id`, `item_id`),
        CONSTRAINT `fk_fav_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
        CONSTRAINT `fk_fav_item` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 10. Loyalty Transactions
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `loyalty_transactions` (
        `loyalty_txn_id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `order_id` INT NULL,
        `txn_type` ENUM('EARNED', 'REDEEMED', 'REFUND_BONUS', 'ADMIN_ADJUST') NOT NULL,
        `points` INT NOT NULL,
        `balance_after` INT NOT NULL,
        `description` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_loyalty_user` (`user_id`),
        CONSTRAINT `fk_loyalty_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
        CONSTRAINT `fk_loyalty_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "V2 Features Migration completed successfully!\n";
