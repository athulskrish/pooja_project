<?php
declare(strict_types=1);
/**
 * Database Migration: Add Advance Meal Order Scheduling Support
 * Zippy - Online Food Ordering Platform
 * MCSP-232 Major Project | Student: Pooja JP
 */

require_once __DIR__ . '/../config/database.php';

echo "Running Migration: Advance Meal Order Scheduling...\n";

$pdo = getDbConnection();
if (!$pdo) {
    die("Error: Could not connect to database.\n");
}

try {
    // 1. Check if column exists, then add
    $columns = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'is_scheduled'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("
            ALTER TABLE `orders` 
            ADD COLUMN `is_scheduled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `order_status`,
            ADD COLUMN `scheduled_for` DATETIME NULL DEFAULT NULL AFTER `is_scheduled`,
            ADD COLUMN `scheduled_slot` VARCHAR(100) NULL DEFAULT NULL AFTER `scheduled_for`,
            ADD INDEX `idx_orders_scheduled` (`is_scheduled`, `scheduled_for`)
        ");
        echo "Successfully added `is_scheduled`, `scheduled_for`, `scheduled_slot` to `orders` table.\n";
    } else {
        echo "`is_scheduled` column already exists in `orders` table.\n";
    }

    // 2. Seed a sample scheduled advance order if none exists yet, so KDS and Admin can test immediately
    $scheduledCount = (int)$pdo->query("SELECT COUNT(*) FROM `orders` WHERE `is_scheduled` = 1")->fetchColumn();
    if ($scheduledCount === 0) {
        // Set Order #1002 as a scheduled advance order for tonight / tomorrow
        $tomorrowLunch = date('Y-m-d 13:00:00', strtotime('+1 day'));
        $stmt = $pdo->prepare("
            UPDATE `orders` 
            SET `is_scheduled` = 1,
                `scheduled_for` = :scheduled_for,
                `scheduled_slot` = 'Tomorrow Lunch (01:00 PM - 01:30 PM)',
                `order_notes` = CONCAT(COALESCE(`order_notes`, ''), ' [ADVANCE SCHEDULED: Deliver for tomorrow team lunch]')
            WHERE `order_id` = 1002
        ");
        $stmt->execute([':scheduled_for' => $tomorrowLunch]);
        echo "Updated demo order #1002 as an advance scheduled order for tomorrow lunch.\n";
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
    exit(1);
}
