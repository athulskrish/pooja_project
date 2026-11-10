<?php
declare(strict_types=1);
/**
 * Admin Logout Action
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

logoutUser();
setFlash('info', 'Admin session terminated safely.');
header("Location: " . ADMIN_URL . "/login.php");
exit;
