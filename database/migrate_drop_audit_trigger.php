<?php
declare(strict_types=1);
/**
 * Database Migration: Drop Duplicate Order Status Audit Trigger
 * Removes trg_after_order_status_update so order_audit_trail entries are written
 * exclusively by application-level transitions with full actor attribution.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDbConnection();
if (!$pdo) {
    fwrite(STDERR, "ERROR: Unable to connect to database.\n");
    exit(1);
}

echo "=== Running Migration: Drop Duplicate Order Audit Trigger ===\n";

try {
    $pdo->exec("DROP TRIGGER IF EXISTS `trg_after_order_status_update`;");
    echo "Dropped trigger `trg_after_order_status_update` (if present).\n";
    echo "Migration completed successfully!\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Migration Error: " . $e->getMessage() . "\n");
    exit(1);
}
