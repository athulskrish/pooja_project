<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    // 2 reached KDS (including 1002 as advance scheduled order)
    dbQuery("UPDATE `orders` SET `order_status` = 'CONFIRMED' WHERE `order_id` IN (1001, 1002)");
    dbQuery("UPDATE `orders` SET `is_scheduled` = 1, `scheduled_for` = DATE_ADD(NOW(), INTERVAL 18 HOUR), `scheduled_slot` = 'Tomorrow Lunch (01:00 PM - 01:30 PM)' WHERE `order_id` = 1002");
    // 3 cooking
    dbQuery("UPDATE `orders` SET `order_status` = 'COOKING' WHERE `order_id` IN (1003, 1004, 1005)");
    // 2 ready to dispatch
    dbQuery("UPDATE `orders` SET `order_status` = 'READY' WHERE `order_id` IN (1006, 1007)");
    // 3 completed orders
    dbQuery("UPDATE `orders` SET `order_status` = 'DELIVERED' WHERE `order_id` IN (1008, 1009, 1010)");

    echo "Orders verified: 1001-1002 CONFIRMED, 1003-1005 COOKING, 1006-1007 READY, 1008-1010 DELIVERED.\n";
} catch (Exception $e) {
    echo "Error updating orders: " . $e->getMessage() . "\n";
}
