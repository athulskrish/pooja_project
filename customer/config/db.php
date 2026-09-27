<?php
/**
 * =============================================================================
 * ZIPPY CUSTOMER APPLICATION - CONFIGURATION & BOOTSTRAPPER
 * Course: MCSP-232 (MCA Major Project, IGNOU) | Student: Pooja JP
 * =============================================================================
 */

// Start session securely if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Database Connection Constants (XAMPP / Local MySQL Default)
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'zippy_food');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application Constants
define('APP_NAME', 'Zippy');
define('APP_TAGLINE', 'Lush Forest Edition');
define('APP_CURRENCY', '₹');
define('GST_RATE', 0.05); // 5% GST on restaurant food services
define('FREE_DELIVERY_THRESHOLD', 500.00);
define('DEFAULT_DELIVERY_FEE', 40.00);

// Base URL helper
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
define('BASE_URL', rtrim($protocol . $host . $scriptDir, '/'));

// Autoload Classes
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Flash message helpers
function set_flash(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

// CSRF Token Management
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// XSS Prevention Sanitizer
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Currency Formatter
function format_price(float $amount): string {
    return APP_CURRENCY . number_format($amount, 2);
}

// Safe Redirect
function redirect(string $path): void {
    header("Location: " . $path);
    exit;
}
