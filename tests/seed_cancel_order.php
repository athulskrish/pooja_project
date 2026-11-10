<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDbConnection();
if ($pdo) {
    // Check if order 1005 exists
    $ord = dbFetchOne("SELECT * FROM orders WHERE order_id = 1005");
    if ($ord) {
        $pdo->exec("UPDATE orders SET order_status = 'CANCELLED' WHERE order_id = 1005");
        $pdo->exec("UPDATE payments SET payment_status = 'REFUNDED' WHERE order_id = 1005");
        $hasItems = dbFetchOne("SELECT count(*) as cnt FROM order_items WHERE order_id = 1005");
        if (!$hasItems || $hasItems['cnt'] == 0) {
            $pdo->exec("INSERT INTO order_items (order_id, item_id, quantity, unit_price, line_total) VALUES (1005, 5, 1, 319.00, 319.00)");
        }
        echo "Order 1005 updated to CANCELLED.\n";
    }
}
