<?php
declare(strict_types=1);
/**
 * Customer Authentication Guard
 * Enforces an authenticated customer (or admin) session before loading protected views.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireCustomer(CUSTOMER_URL . '/login.php');
