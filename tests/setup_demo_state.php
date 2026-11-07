<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Set #1004 back to OUT_FOR_DELIVERY with Shaji (OTP: 610482)
db_query("UPDATE `orders` SET `order_status` = 'OUT_FOR_DELIVERY' WHERE `order_id` = 1004");
db_query("
    UPDATE `delivery_dispatches` 
    SET `rider_id` = 6, 
        `delivery_partner_name` = 'Shaji (Rider)', 
        `delivery_partner_phone` = '+91 96320 98765', 
        `delivery_otp` = '610482', 
        `dispatched_at` = NOW(), 
        `delivered_at` = NULL, 
        `delivery_status` = 'IN_TRANSIT' 
    WHERE `order_id` = 1004
");

// Set #1001 back to READY
db_query("UPDATE `orders` SET `order_status` = 'READY' WHERE `order_id` = 1001");
db_query("
    UPDATE `delivery_dispatches` 
    SET `rider_id` = NULL, 
        `delivery_partner_name` = 'Zippy Express Rider', 
        `delivery_partner_phone` = '+91 98765 43210', 
        `delivered_at` = NULL, 
        `delivery_status` = 'ASSIGNED' 
    WHERE `order_id` = 1001
");

// Run FCFS allocation
auto_allocate_ready_orders_fcfs();

echo "Database successfully set up for user presentation and live verification!\n";
