<?php
/**
 * Delivery Partner Web App - Redirect Bridge
 * Redirects to the standalone Fleet Portal at /delivery/index.php
 */
require_once __DIR__ . '/../config/config.php';
header("Location: " . DELIVERY_URL . "/index.php");
exit;
