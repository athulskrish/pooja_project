<?php
/**
 * Admin Kitchen Operations - Redirect to Admin Live Orders
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';

// Redirect to Admin Live Orders preparation section
header("Location: " . ADMIN_URL . "/orders.php?status=COOKING");
exit;
