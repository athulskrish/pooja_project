<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    // 2 reached KDS
    db_query("UPDATE `orders` SET `order_status` = 'CONFIRMED' WHERE `order_id` IN (1001, 1002)");
    // 3 cooking
    db_query("UPDATE `orders` SET `order_status` = 'COOKING' WHERE `order_id` IN (1003, 1004, 1005)");
    // 2 ready to dispatch
    db_query("UPDATE `orders` SET `order_status` = 'READY' WHERE `order_id` IN (1006, 1007)");
    // 3 completed orders
    db_query("UPDATE `orders` SET `order_status` = 'DELIVERED' WHERE `order_id` IN (1008, 1009, 1010)");

    echo "Orders verified: 1001-1002 CONFIRMED, 1003-1005 COOKING, 1006-1007 READY, 1008-1010 DELIVERED.\n";
} catch (Exception $e) {
    echo "Error updating orders: " . $e->getMessage() . "\n";
}
