<?php
/**
 * Admin Logout Action
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

logout_user();
set_flash('info', 'Admin session terminated safely.');
header("Location: " . ADMIN_URL . "/login.php");
exit;
