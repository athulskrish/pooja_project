<?php
declare(strict_types=1);
/**
 * Idempotent Database Migration: Soft-Delete Governance (`is_active`)
 * Ensures menu_items, dish_customization_groups, dish_customization_options,
 * user_addresses, categories, faqs, and food_moods have `is_active` TINYINT(1) NOT NULL DEFAULT 1.
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../includes/auth.php';
    requireAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}

$pdo = getDbConnection();
if (!$pdo) {
    fwrite(STDERR, "Migration aborted: unable to connect to database.\n");
    exit(1);
}

/**
 * Target tables and the column after which `is_active` should be placed if missing.
 */
$targets = [
    'categories'                => 'display_order',
    'food_moods'                => 'display_order',
    'faqs'                      => 'display_order',
    'user_addresses'            => 'is_default',
    'menu_items'                => 'is_available',
    'dish_customization_groups' => 'display_order',
    'dish_customization_options' => 'is_available',
];

$driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$dbName = defined('DB_NAME') ? DB_NAME : '';

foreach ($targets as $table => $afterCol) {
    assertSqlIdentifier($table, 'soft-delete migration table');
    assertSqlIdentifier($afterCol, 'soft-delete migration column');

    $exists = false;
    if ($driver === 'mysql') {
        $colCount = (int)dbFetchVal(
            "SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = :schema AND `TABLE_NAME` = :tbl AND `COLUMN_NAME` = 'is_active'",
            [':schema' => $dbName, ':tbl' => $table]
        );
        $exists = $colCount > 0;
    } else {
        $cols = dbFetchAll("PRAGMA table_info(`{$table}`)");
        foreach ($cols as $col) {
            if (($col['name'] ?? '') === 'is_active') {
                $exists = true;
                break;
            }
        }
    }

    if ($exists) {
        echo "[OK] `{$table}` already has `is_active`.\n";
        continue;
    }

    if ($driver === 'mysql') {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `{$afterCol}`");
    } else {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `is_active` INTEGER NOT NULL DEFAULT 1");
    }
    echo "[MIGRATED] Added `is_active` to `{$table}`.\n";
}

echo "Soft-delete migration completed cleanly.\n";
