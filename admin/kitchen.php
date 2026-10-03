<?php
/**
 * Kitchen Display System (KDS) - Redirect to Dedicated Portal
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';

// Redirect to dedicated standalone Kitchen operations portal
header("Location: " . KITCHEN_URL . "/index.php");
exit;
