<?php
/**
 * Global Application Configuration & Common Helpers
 * Zippy - Online Food Ordering Platform
 * Student: Pooja JP | Course: MCSP-232 (MCA Major Project)
 */

// Disable implicit flushing and start output buffering for clean HTTP headers and redirects
ini_set('implicit_flush', '0');
if (!ob_get_level()) {
    ob_start();
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    // Secure session cookie settings where possible
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// App Constants
define('APP_NAME', 'Zippy');
define('APP_TAGLINE', 'Lush Forest Edition');
define('APP_VERSION', '2.0.0-MCA');
define('CURRENCY_SYMBOL', '₹');
define('GST_PERCENT', 5.0); // 5% GST on Restaurant Services (2.5% CGST + 2.5% SGST)
define('DEFAULT_DELIVERY_FEE', 40.00);
define('FREE_DELIVERY_THRESHOLD', 499.00);
define('PLATFORM_FEE', 5.00);

// Base URL Auto-detection
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    
    // Normalize path separators
    $scriptDir = str_replace('\\', '/', $scriptDir);
    
    // Resolve base folder relative to project root
    // If inside /customer or /admin or /delivery or /kitchen or /database, step one folder up
    $projectBase = preg_replace('#/(customer|admin|delivery|kitchen|database|config|includes|assets)(/.*)?$#', '', $scriptDir);
    $projectBase = rtrim($projectBase, '/');
    
    define('BASE_URL', $protocol . $host . $projectBase);
    define('CUSTOMER_URL', BASE_URL . '/customer');
    define('ADMIN_URL', BASE_URL . '/admin');
    define('DELIVERY_URL', BASE_URL . '/delivery');
    define('KITCHEN_URL', BASE_URL . '/kitchen');
    define('ASSETS_URL', BASE_URL . '/assets');
}

// Load Database
require_once __DIR__ . '/database.php';

/**
 * HTML Escaping shorthand
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize User Input
 */
function clean_input($data) {
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    return trim(htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8'));
}

/**
 * Format Indian Rupee Currency
 */
function format_inr($amount): string {
    return CURRENCY_SYMBOL . number_format((float)$amount, 2);
}

/**
 * CSRF Token Management
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash Notification System
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function display_flash(): void {
    $flash = get_flash();
    if ($flash) {
        $type = e($flash['type']);
        $icon = match($type) {
            'success' => 'bi-check-circle-fill',
            'danger' => 'bi-exclamation-octagon-fill',
            'warning' => 'bi-exclamation-triangle-fill',
            default => 'bi-info-circle-fill'
        };
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show d-flex align-items-center gap-2 mb-4 rounded-3 shadow-sm" role="alert">
            <i class="bi ' . $icon . ' fs-5"></i>
            <div>' . e($flash['message']) . '</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
}

/**
 * Authentication Helpers
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'role_id' => $_SESSION['role_id'] ?? 2,
        'role_name' => $_SESSION['role_name'] ?? 'CUSTOMER'
    ];
}

function is_admin(): bool {
    return is_logged_in() && (($_SESSION['role_name'] ?? '') === 'ADMIN' || ($_SESSION['role_id'] ?? 0) == 1);
}

function is_customer(): bool {
    return is_logged_in() && (($_SESSION['role_name'] ?? '') === 'CUSTOMER' || ($_SESSION['role_id'] ?? 0) == 2);
}

function is_rider(): bool {
    return is_logged_in() && (($_SESSION['role_name'] ?? '') === 'RIDER' || ($_SESSION['role_id'] ?? 0) == 4);
}

function is_kitchen(): bool {
    return is_logged_in() && (($_SESSION['role_name'] ?? '') === 'KITCHEN' || ($_SESSION['role_id'] ?? 0) == 3);
}

function require_login(string $redirect = null): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Please sign in to access that page.');
        $target = $redirect ?: CUSTOMER_URL . '/login.php';
        header("Location: " . $target);
        exit;
    }
}

function require_admin(): void {
    if (!is_admin()) {
        set_flash('danger', 'Access denied. Administrator privileges required.');
        header("Location: " . ADMIN_URL . '/login.php');
        exit;
    }
}

function require_rider(string $redirect = null): void {
    if (!is_rider()) {
        set_flash('warning', 'Please sign in with a Delivery Partner / Rider account.');
        $target = $redirect ?: DELIVERY_URL . '/login.php';
        header("Location: " . $target);
        exit;
    }
}

function require_kitchen(string $redirect = null): void {
    if (!is_kitchen() && !is_admin()) {
        set_flash('warning', 'Please sign in with a Kitchen Chef or Admin account.');
        $target = $redirect ?: KITCHEN_URL . '/login.php';
        header("Location: " . $target);
        exit;
    }
}

/**
 * Status Badge Formatter for Orders
 */
function get_status_badge(string $status): string {
    $status = strtoupper($status);
    $config = match($status) {
        'PLACED' => ['bg' => 'bg-secondary', 'text' => 'text-white', 'icon' => 'bi-clock-history', 'label' => 'Order Placed'],
        'CONFIRMED' => ['bg' => 'bg-info', 'text' => 'text-dark', 'icon' => 'bi-check2-circle', 'label' => 'Confirmed'],
        'COOKING' => ['bg' => 'bg-warning', 'text' => 'text-dark', 'icon' => 'bi-fire', 'label' => 'In Kitchen'],
        'READY' => ['bg' => 'bg-primary', 'text' => 'text-white', 'icon' => 'bi-bag-check', 'label' => 'Ready for Pickup'],
        'OUT_FOR_DELIVERY' => ['bg' => 'bg-warning', 'text' => 'text-dark', 'icon' => 'bi-bicycle', 'label' => 'Out for Delivery'],
        'DELIVERED' => ['bg' => 'bg-success', 'text' => 'text-white', 'icon' => 'bi-house-check', 'label' => 'Delivered'],
        'CANCELLED' => ['bg' => 'bg-danger', 'text' => 'text-white', 'icon' => 'bi-x-circle', 'label' => 'Cancelled'],
        default => ['bg' => 'bg-secondary', 'text' => 'text-white', 'icon' => 'bi-question-circle', 'label' => $status]
    };

    return '<span class="badge ' . $config['bg'] . ' ' . $config['text'] . ' px-2 py-1 d-inline-flex align-items-center gap-1 rounded-pill">' .
           '<i class="bi ' . $config['icon'] . '"></i> ' . e($config['label']) . '</span>';
}
