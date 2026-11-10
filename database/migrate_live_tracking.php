<?php
declare(strict_types=1);
/**
 * Migration: Live Location Tracking & Coordinate Streaming
 * Adds geolocation, route progress, and dynamic ETA tracking fields to delivery_dispatches
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDbConnection();
if (!$pdo) {
    die("Database connection failed.\n");
}

echo "Migrating delivery_dispatches for real-time live location tracking...\n";

$existingCols = [];
$res = $pdo->query("SHOW COLUMNS FROM `delivery_dispatches`")->fetchAll(PDO::FETCH_ASSOC);
foreach ($res as $r) {
    $existingCols[] = strtolower($r['Field']);
}

$columnsToAdd = [
    'current_latitude' => "DECIMAL(10, 8) NULL DEFAULT 12.97840000 COMMENT 'Current rider latitude (Hub: 12.9784)'",
    'current_longitude' => "DECIMAL(11, 8) NULL DEFAULT 77.64080000 COMMENT 'Current rider longitude (Hub: 77.6408)'",
    'delivery_latitude' => "DECIMAL(10, 8) NULL DEFAULT 12.96980000 COMMENT 'Customer drop latitude'",
    'delivery_longitude' => "DECIMAL(11, 8) NULL DEFAULT 77.64990000 COMMENT 'Customer drop longitude'",
    'route_progress' => "INT NOT NULL DEFAULT 0 COMMENT 'Route progress percentage (0-100)'",
    'distance_remaining_km' => "DECIMAL(5, 2) NOT NULL DEFAULT 2.40 COMMENT 'Remaining travel distance in km'",
    'estimated_minutes_remaining' => "INT NOT NULL DEFAULT 15 COMMENT 'Live dynamic ETA in minutes'",
    'rider_id' => "INT NULL COMMENT 'FK to users table for assigned rider'"
];

foreach ($columnsToAdd as $colName => $colDef) {
    $safeCol = assertSqlIdentifier((string)$colName, 'delivery_dispatches column');
    if (!in_array(strtolower($safeCol), $existingCols, true)) {
        try {
            $pdo->exec("ALTER TABLE `delivery_dispatches` ADD COLUMN `{$safeCol}` {$colDef}");
            echo " [+] Added column `{$safeCol}` to `delivery_dispatches`.\n";
        } catch (Exception $e) {
            echo " [!] Failed to add column `{$safeCol}`: " . $e->getMessage() . "\n";
        }
    } else {
        echo " [=] Column `{$safeCol}` already exists in `delivery_dispatches`.\n";
    }
}

// Populate default coordinates for existing dispatches if empty
$pdo->exec("
    UPDATE `delivery_dispatches`
    SET `current_latitude` = COALESCE(`current_latitude`, 12.97840000),
        `current_longitude` = COALESCE(`current_longitude`, 77.64080000),
        `delivery_latitude` = COALESCE(`delivery_latitude`, 12.96980000),
        `delivery_longitude` = COALESCE(`delivery_longitude`, 77.64990000),
        `distance_remaining_km` = COALESCE(`distance_remaining_km`, 2.40),
        `estimated_minutes_remaining` = COALESCE(`estimated_minutes_remaining`, 12)
    WHERE `current_latitude` IS NULL OR `delivery_latitude` IS NULL
");

echo "Live location tracking migration complete!\n";
