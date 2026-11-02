<?php
/**
 * Global Application Configuration & Common Helpers
 * Zippy - Online Food Ordering Platform
 * Student: Pooja JP | Course: MCSP-232 (MCA Major Project)
 */

// Set default timezone to Indian Standard Time (IST) to align PHP with MariaDB system time
date_default_timezone_set('Asia/Kolkata');

// Disable implicit flushing and start output buffering for clean HTTP headers and redirects
ini_set('implicit_flush', '0');
if (!ob_get_level()) {
    ob_start();
}

/**
 * Detect Current Operational Portal Context
 * Enables Multi-Actor Isolated Sessions in the SAME browser window.
 * Allows Customer, Admin, Delivery Rider, and Kitchen Chef to operate concurrently in separate tabs.
 */
function get_current_portal(): string {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    $uri = str_replace('\\', '/', $_SERVER['REQUEST_URI'] ?? '');
    $combined = strtolower($script . ' ' . $uri);

    if (str_contains($combined, '/admin/')) {
        return 'admin';
    }
    if (str_contains($combined, '/kitchen/')) {
        return 'kitchen';
    }
    if (str_contains($combined, '/delivery/')) {
        return 'delivery';
    }
    if (str_contains($combined, '/customer/')) {
        return 'customer';
    }
    if (str_contains($combined, '/api/')) {
        return 'customer';
    }

    return 'customer'; // Default storefront / master launchpad
}

/**
 * Retrieve Active User Info Across All 4 Portal Scopes
 * Allows the Master Gateway (index.php) to monitor multi-actor presentation state
 */
function get_all_portal_actors(): array {
    $portals = ['customer', 'kitchen', 'delivery', 'admin'];
    $actors = [];
    foreach ($portals as $p) {
        $actorCookie = 'ZIPPY_ACTOR_' . strtoupper($p);
        $cookieVal = $_COOKIE[$actorCookie] ?? null;
        if (!empty($cookieVal)) {
            $decoded = json_decode($cookieVal, true);
            if (is_array($decoded) && !empty($decoded['id'])) {
                $actors[$p] = $decoded;
                continue;
            }
        }
        $actors[$p] = null;
    }
    return $actors;
}

// Start session if not started with Isolated Session Cookie per Portal
if (session_status() === PHP_SESSION_NONE) {
    // Multi-Actor Session Isolation: Map portal context to dedicated session cookie
    $portal = get_current_portal();
    $sessionNames = [
        'admin' => 'ZIPPY_ADMIN_SESS',
        'kitchen' => 'ZIPPY_KITCHEN_SESS',
        'delivery' => 'ZIPPY_RIDER_SESS',
        'customer' => 'ZIPPY_CUSTOMER_SESS',
    ];
    $activeSessionName = $sessionNames[$portal] ?? 'ZIPPY_CUSTOMER_SESS';
    session_name($activeSessionName);

    // Secure session cookie settings across all portals and paths
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', $isSecure, true);
    }
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

// Razorpay Payment Gateway Settings (Test Mode Sandbox)
define('RAZORPAY_KEY_ID', getenv('RAZORPAY_KEY_ID') ?: 'rzp_test_1DP5mmOlF5G5ag');
define('RAZORPAY_KEY_SECRET', getenv('RAZORPAY_KEY_SECRET') ?: 'dummy_secret_key');
define('RAZORPAY_MERCHANT_NAME', 'Zippy Foods');

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
    if (!empty($projectBase) && !str_starts_with($projectBase, '/')) {
        $projectBase = '/' . $projectBase;
    }
    
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
 * Role-Based Access Control (RBAC) Permissions Matrix
 * Standardizes granular privileges across:
 * - Role 1: ADMIN (Full operational and data authority)
 * - Role 2: CUSTOMER (End-user storefront and order lifecycle)
 * - Role 3: KITCHEN (KDS queue, prep advances, 86-ing stock, KOT)
 * - Role 4: RIDER (Fleet dispatches, GPS routing, doorstep OTP handshake)
 */
const RBAC_PERMISSIONS = [
    'ADMIN' => [
        'view_admin_dashboard',
        'manage_dishes',
        'manage_categories',
        'manage_users',
        'update_user_roles',
        'view_reports',
        'view_audit_trail',
        'manage_faqs',
        'manage_orders',
        'view_kitchen',
        'advance_kitchen_orders',
        'toggle_dish_stock',
        'print_kot',
        'view_delivery',
        'claim_delivery',
        'verify_otp',
        'browse_catalog',
        'manage_cart',
        'place_orders',
        'view_all_orders',
        'manage_addresses'
    ],
    'KITCHEN' => [
        'view_kitchen',
        'advance_kitchen_orders',
        'toggle_dish_stock',
        'print_kot',
        'browse_catalog'
    ],
    'RIDER' => [
        'view_delivery',
        'claim_delivery',
        'verify_otp',
        'toggle_rider_duty',
        'browse_catalog'
    ],
    'CUSTOMER' => [
        'browse_catalog',
        'manage_cart',
        'place_orders',
        'view_own_orders',
        'track_own_orders',
        'cancel_own_orders',
        'rate_own_orders',
        'manage_addresses'
    ]
];

/**
 * Authentication & RBAC Helpers
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    $userName = $_SESSION['user_name'] ?? 'User';
    $userPhone = $_SESSION['user_phone'] ?? '';
    return [
        'id' => $_SESSION['user_id'],
        'name' => $userName,
        'full_name' => $userName,
        'email' => $_SESSION['user_email'] ?? '',
        'phone' => $userPhone,
        'phone_number' => $userPhone,
        'role_id' => $_SESSION['role_id'] ?? 2,
        'role_name' => strtoupper($_SESSION['role_name'] ?? 'CUSTOMER')
    ];
}

function has_role(string|array $roles): bool {
    if (!is_logged_in()) {
        return false;
    }
    $currentRole = strtoupper($_SESSION['role_name'] ?? '');
    if (is_array($roles)) {
        $normalized = array_map('strtoupper', $roles);
        return in_array($currentRole, $normalized, true);
    }
    return $currentRole === strtoupper($roles);
}

function has_permission(string $permission): bool {
    if (!is_logged_in()) {
        return false;
    }
    $role = strtoupper($_SESSION['role_name'] ?? '');
    return in_array($permission, RBAC_PERMISSIONS[$role] ?? [], true);
}

function check_permission(string $permission): bool {
    return has_permission($permission);
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

function require_role(string|array $roles, ?string $redirect = null): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Please sign in to access that protected portal.');
        $target = $redirect ?: CUSTOMER_URL . '/login.php';
        header("Location: " . $target);
        exit;
    }
    
    if (!has_role($roles)) {
        $allowedList = is_array($roles) ? implode(', ', $roles) : $roles;
        set_flash('danger', "Access Denied: Your account role does not have permission to access that area. (Required: {$allowedList})");
        
        $userRole = strtoupper($_SESSION['role_name'] ?? '');
        $home = match($userRole) {
            'ADMIN' => ADMIN_URL . '/index.php',
            'KITCHEN' => KITCHEN_URL . '/index.php',
            'RIDER' => DELIVERY_URL . '/index.php',
            default => CUSTOMER_URL . '/index.php'
        };
        header("Location: " . $home);
        exit;
    }
}

function require_admin(): void {
    require_role('ADMIN', ADMIN_URL . '/login.php');
}

function require_rider(string $redirect = null): void {
    require_role(['RIDER', 'ADMIN'], $redirect ?: DELIVERY_URL . '/login.php');
}

function require_kitchen(string $redirect = null): void {
    require_role(['KITCHEN', 'ADMIN'], $redirect ?: KITCHEN_URL . '/login.php');
}

function require_customer(string $redirect = null): void {
    require_role(['CUSTOMER', 'ADMIN'], $redirect ?: CUSTOMER_URL . '/login.php');
}

/**
 * Standardized Role Badge HTML Generator
 */
function get_role_badge(string $roleName): string {
    $role = strtoupper(trim($roleName));
    return match($role) {
        'ADMIN' => '<span class="badge bg-danger px-2 py-1 rounded-pill"><i class="bi bi-shield-lock-fill me-1"></i>ADMIN</span>',
        'KITCHEN' => '<span class="badge bg-warning text-dark px-2 py-1 rounded-pill"><i class="fa-solid fa-fire-burner me-1"></i>KITCHEN CHEF</span>',
        'RIDER' => '<span class="badge bg-info text-dark px-2 py-1 rounded-pill"><i class="bi bi-bicycle me-1"></i>DELIVERY RIDER</span>',
        'CUSTOMER' => '<span class="badge bg-success bg-opacity-25 text-success px-2 py-1 rounded-pill"><i class="bi bi-person me-1"></i>CUSTOMER</span>',
        default => '<span class="badge bg-secondary px-2 py-1 rounded-pill">' . e($role) . '</span>'
    };
}

/**
 * Role-Based Order Resource Authorization Check
 * Prevents Insecure Direct Object Reference (IDOR)
 */
function can_access_order(int|array $orderOrId, ?int $userId = null): bool {
    if (is_admin() || is_kitchen() || is_rider()) {
        return true;
    }
    if ($userId === null) {
        $cur = current_user();
        if (!$cur) {
            return false;
        }
        $userId = (int)$cur['id'];
    }

    $ownerId = null;
    if (is_array($orderOrId)) {
        $ownerId = (int)($orderOrId['user_id'] ?? 0);
    } else {
        $ownerId = (int)db_fetch_val("SELECT `user_id` FROM `orders` WHERE `order_id` = :id", [':id' => (int)$orderOrId]);
    }

    return $ownerId === (int)$userId;
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
