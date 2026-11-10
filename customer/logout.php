<?php
declare(strict_types=1);
/**
 * Customer Logout Action
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

logoutUser();
setFlash('info', 'You have been signed out safely. Come back soon!');
header("Location: " . CUSTOMER_URL . "/login.php");
exit;
