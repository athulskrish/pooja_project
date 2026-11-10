<?php
declare(strict_types=1);
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
function getCurrentPortal(): string
{
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
        $cookieMap = [
            'admin' => 'ZIPPY_ADMIN_SESS',
            'kitchen' => 'ZIPPY_KITCHEN_SESS',
            'delivery' => 'ZIPPY_RIDER_SESS',
            'customer' => 'ZIPPY_CUSTOMER_SESS',
        ];
        $presentPortals = [];
        foreach ($cookieMap as $portalName => $cookieName) {
            if (!empty($_COOKIE[$cookieName])) {
                $presentPortals[] = $portalName;
            }
        }

        // Unauthenticated client
        if (empty($presentPortals)) {
            return 'customer';
        }

        // Single authenticated portal cookie: unambiguously use that session for RBAC & CSRF checks
        if (count($presentPortals) === 1) {
            return $presentPortals[0];
        }

        // Multiple portal cookies active in same browser: disambiguate via portal hint or same-host Referer
        $hint = strtolower(trim((string)($_GET['portal'] ?? $_POST['portal'] ?? $_SERVER['HTTP_X_PORTAL'] ?? '')));
        $hintPortal = match (true) {
            in_array($hint, ['admin', 'categories', 'dishes'], true) => 'admin',
            $hint === 'kitchen' => 'kitchen',
            in_array($hint, ['delivery', 'rider'], true) => 'delivery',
            $hint === 'customer' => 'customer',
            default => null,
        };
        if ($hintPortal !== null && in_array($hintPortal, $presentPortals, true)) {
            return $hintPortal;
        }

        $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer !== '') {
            $refHost = strtolower((string)(parse_url($referer, PHP_URL_HOST) ?? ''));
            $curHost = strtolower((string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
            $refPath = strtolower(str_replace('\\', '/', (string)(parse_url($referer, PHP_URL_PATH) ?? '')));
            if ($refHost === '' || $curHost === '' || $refHost === $curHost) {
                foreach (['admin', 'kitchen', 'delivery', 'customer'] as $candidate) {
                    if (str_contains($refPath, "/{$candidate}/") && in_array($candidate, $presentPortals, true)) {
                        return $candidate;
                    }
                }
            }
        }

        return in_array('customer', $presentPortals, true) ? 'customer' : $presentPortals[0];
    }

    return 'customer'; // Default storefront / master launchpad
}

/**
 * Retrieve Active User Info Across All 4 Portal Scopes
 * Allows the Master Gateway (index.php) to monitor multi-actor presentation state
 */
function getAllPortalActors(): array
{
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
    $portal = getCurrentPortal();
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

// Load Database (also loads local .env overrides)
require_once __DIR__ . '/database.php';

// Razorpay Payment Gateway Settings (Test Mode Sandbox)
define('RAZORPAY_KEY_ID', getenv('RAZORPAY_KEY_ID') ?: 'rzp_test_1DP5mmOlF5G5ag');
define('RAZORPAY_KEY_SECRET', getenv('RAZORPAY_KEY_SECRET') ?: 'dummy_secret_key');
define('RAZORPAY_WEBHOOK_SECRET', (string)(getenv('RAZORPAY_WEBHOOK_SECRET') ?: ($_ENV['RAZORPAY_WEBHOOK_SECRET'] ?? '')));
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

// Opt-in Demo Showcase Mode (?demo=1 quick login & demo credential UI hints)
if (!defined('APP_DEMO_MODE')) {
    $demoEnv = getenv('APP_DEMO_MODE');
    if ($demoEnv === false && isset($_ENV['APP_DEMO_MODE'])) {
        $demoEnv = (string)$_ENV['APP_DEMO_MODE'];
    }
    define('APP_DEMO_MODE', filter_var($demoEnv !== false ? $demoEnv : 'false', FILTER_VALIDATE_BOOLEAN));
}

/**
 * HTML Escaping shorthand
 */
function e(?string $string): string
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize User Input
 */
function cleanInput(string|array|null $data): string|array
{
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    return trim(htmlspecialchars((string)($data ?? ''), ENT_QUOTES, 'UTF-8'));
}

/**
 * Format Indian Rupee Currency
 */
function formatInr(float|int|string|null $amount): string
{
    return CURRENCY_SYMBOL . number_format((float)($amount ?? 0), 2);
}

/**
 * CSRF Token Management
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrfToken(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validate CSRF token from form POST body, X-CSRF-TOKEN header, or JSON payload
 */
function verifyRequestCsrf(?array $jsonPayload = null): bool
{
    if (!empty($_POST['csrf_token'])) {
        $submittedToken = (string)$_POST['csrf_token'];
    } elseif (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $submittedToken = (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
    } elseif (!empty($jsonPayload['csrf_token'])) {
        $submittedToken = (string)$jsonPayload['csrf_token'];
    } else {
        $submittedToken = '';
    }
    return verifyCsrfToken($submittedToken);
}

/**
 * Context-safe JSON literal encoder for inline JavaScript and HTML attribute handlers
 */
function jsJson(mixed $value): string
{
    $encoded = json_encode(
        $value,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
    );
    return $encoded !== false ? $encoded : 'null';
}

/**
 * Flash Notification System
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function displayFlash(): void
{
    $flash = getFlash();
    if ($flash) {
        $type = e($flash['type']);
        $icon = match ($type) {
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
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function getCurrentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }
    $userName = (string)($_SESSION['user_name'] ?? 'User');
    $userPhone = (string)($_SESSION['user_phone'] ?? '');
    return [
        'id' => (int)$_SESSION['user_id'],
        'name' => $userName,
        'full_name' => $userName,
        'email' => (string)($_SESSION['user_email'] ?? ''),
        'phone' => $userPhone,
        'phone_number' => $userPhone,
        'role_id' => (int)($_SESSION['role_id'] ?? 2),
        'role_name' => strtoupper((string)($_SESSION['role_name'] ?? 'CUSTOMER'))
    ];
}

function hasRole(string|array $roles): bool
{
    if (!isLoggedIn()) {
        return false;
    }
    $currentRole = strtoupper($_SESSION['role_name'] ?? '');
    if (is_array($roles)) {
        $normalized = array_map('strtoupper', $roles);
        return in_array($currentRole, $normalized, true);
    }
    return $currentRole === strtoupper($roles);
}

function hasPermission(string $permission): bool
{
    if (!isLoggedIn()) {
        return false;
    }
    $role = strtoupper($_SESSION['role_name'] ?? '');
    return in_array($permission, RBAC_PERMISSIONS[$role] ?? [], true);
}

function checkPermission(string $permission): bool
{
    return hasPermission($permission);
}

function isAdmin(): bool
{
    return isLoggedIn() && (($_SESSION['role_name'] ?? '') === 'ADMIN' || ($_SESSION['role_id'] ?? 0) == 1);
}

function isCustomer(): bool
{
    return isLoggedIn() && (($_SESSION['role_name'] ?? '') === 'CUSTOMER' || ($_SESSION['role_id'] ?? 0) == 2);
}

function isRider(): bool
{
    return isLoggedIn() && (($_SESSION['role_name'] ?? '') === 'RIDER' || ($_SESSION['role_id'] ?? 0) == 4);
}

function isKitchen(): bool
{
    return isLoggedIn() && (($_SESSION['role_name'] ?? '') === 'KITCHEN' || ($_SESSION['role_id'] ?? 0) == 3);
}

function requireLogin(?string $redirect = null): void
{
    if (!isLoggedIn()) {
        setFlash('warning', 'Please sign in to access that page.');
        $target = $redirect ?: CUSTOMER_URL . '/login.php';
        header("Location: " . $target);
        exit;
    }
}

function requireRole(string|array $roles, ?string $redirect = null): void
{
    if (!isLoggedIn()) {
        setFlash('warning', 'Please sign in to access that protected portal.');
        $target = $redirect ?: CUSTOMER_URL . '/login.php';
        header("Location: " . $target);
        exit;
    }
    
    if (!hasRole($roles)) {
        $allowedList = is_array($roles) ? implode(', ', $roles) : $roles;
        setFlash('danger', "Access Denied: Your account role does not have permission to access that area. (Required: {$allowedList})");
        
        $userRole = strtoupper($_SESSION['role_name'] ?? '');
        $home = match ($userRole) {
            'ADMIN' => ADMIN_URL . '/index.php',
            'KITCHEN' => KITCHEN_URL . '/index.php',
            'RIDER' => DELIVERY_URL . '/index.php',
            default => CUSTOMER_URL . '/index.php'
        };
        header("Location: " . $home);
        exit;
    }
}

function requireAdmin(): void
{
    requireRole('ADMIN', ADMIN_URL . '/login.php');
}

function requireRider(?string $redirect = null): void
{
    requireRole(['RIDER', 'ADMIN'], $redirect ?: DELIVERY_URL . '/login.php');
}

function requireKitchen(?string $redirect = null): void
{
    requireRole(['KITCHEN', 'ADMIN'], $redirect ?: KITCHEN_URL . '/login.php');
}

function requireCustomer(?string $redirect = null): void
{
    requireRole(['CUSTOMER', 'ADMIN'], $redirect ?: CUSTOMER_URL . '/login.php');
}

/**
 * Standardized Role Badge HTML Generator
 */
function getRoleBadge(string $roleName): string
{
    $role = strtoupper(trim($roleName));
    return match ($role) {
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
function canAccessOrder(int|array $orderOrId, ?int $userId = null): bool
{
    if (isAdmin() || isKitchen() || isRider()) {
        return true;
    }
    if ($userId === null) {
        $cur = getCurrentUser();
        if (!$cur) {
            return false;
        }
        $userId = (int)$cur['id'];
    }

    $ownerId = null;
    if (is_array($orderOrId)) {
        $ownerId = (int)($orderOrId['user_id'] ?? 0);
    } else {
        $ownerId = (int)dbFetchVal("SELECT `user_id` FROM `orders` WHERE `order_id` = :id", [':id' => (int)$orderOrId]);
    }

    return $ownerId === (int)$userId;
}

/**
 * Status Badge Formatter for Orders
 */
function getStatusBadge(string $status): string
{
    $status = strtoupper($status);
    $config = match ($status) {
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
