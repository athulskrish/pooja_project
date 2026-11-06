<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pm = get_customer_previous_meal(2);
if (!$pm) {
    echo "NO PREVIOUS MEAL FOUND\n";
    exit(1);
}

echo "SUCCESS: Order ID #" . $pm['order']['order_id'] . "\n";
echo "Status: " . $pm['order']['order_status'] . "\n";
echo "Total Amount: " . $pm['order']['total_amount'] . "\n";
echo "Items count: " . $pm['items_count'] . "\n";
foreach ($pm['items'] as $it) {
    echo " - " . $it['item_name'] . " (x" . $it['quantity'] . ") @ Rs." . $it['current_price'] . "\n";
}
echo "Payload JSON: " . $pm['reorder_json'] . "\n";
