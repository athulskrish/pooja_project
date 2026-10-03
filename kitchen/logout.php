<?php
/**
 * Kitchen Staff Logout Handler
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

logout_user();

set_flash('info', 'Chef signed off successfully. Have a great rest!');
header("Location: " . KITCHEN_URL . "/login.php");
exit;
