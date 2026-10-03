<?php
/**
 * Delivery Partner Logout
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

logout_user();
set_flash('info', 'You have been successfully signed out from the Delivery Fleet App.');
header("Location: " . DELIVERY_URL . "/login.php");
exit;
