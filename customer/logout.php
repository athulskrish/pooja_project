<?php
/**
 * Customer Logout Action
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

logout_user();
set_flash('info', 'You have been signed out safely. Come back soon!');
header("Location: " . CUSTOMER_URL . "/login.php");
exit;
