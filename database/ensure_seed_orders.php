<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    db_query("UPDATE `orders` SET `order_status` = 'COOKING' WHERE `order_id` IN (1003, 1006)");
    db_query("UPDATE `orders` SET `order_status` = 'CONFIRMED' WHERE `order_id` IN (1004, 1009)");
    echo "Orders updated: 1003, 1006 are COOKING; 1004, 1009 are CONFIRMED.\n";
} catch (Exception $e) {
    echo "Error updating orders: " . $e->getMessage() . "\n";
}
