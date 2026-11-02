<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDBConnection();
if ($pdo) {
    // Check if order 1005 exists
    $ord = db_fetch_one("SELECT * FROM orders WHERE order_id = 1005");
    if ($ord) {
        $pdo->exec("UPDATE orders SET order_status = 'CANCELLED' WHERE order_id = 1005");
        $pdo->exec("UPDATE payments SET payment_status = 'REFUNDED' WHERE order_id = 1005");
        $hasItems = db_fetch_one("SELECT count(*) as cnt FROM order_items WHERE order_id = 1005");
        if (!$hasItems || $hasItems['cnt'] == 0) {
            $pdo->exec("INSERT INTO order_items (order_id, item_id, quantity, unit_price, line_total) VALUES (1005, 5, 1, 319.00, 319.00)");
        }
        echo "Order 1005 updated to CANCELLED.\n";
    }
}
