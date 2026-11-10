<?php
declare(strict_types=1);
/**
 * Delivery Partner Logout
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

logoutUser();
setFlash('info', 'You have been successfully signed out from the Delivery Fleet App.');
session_write_close();
header("Location: " . DELIVERY_URL . "/login.php");
exit;
