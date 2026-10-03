<?php
/**
 * ZIPPY FOOD NETWORK - DATABASE CONFIGURATION & CONNECTION (PDO)
 * MCSP-232 Major Project - Master of Computer Applications
 * Student: Pooja JP | University: IGNOU
 *
 * Implements PDO Singleton pattern with automated self-installation:
 * If 'zippy_db' database does not exist, automatically executes schema.sql.
 * If MySQL server is offline (e.g. XAMPP not started), provides transparent fallback
 * so the frontend remains fully interactive with mock data for viva demonstrations.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Global Configuration
defined('APP_NAME') or define('APP_NAME', 'Zippy');
defined('APP_TAGLINE') or define('APP_TAGLINE', 'Online Food Ordering & Cloud Kitchen Platform');
defined('STUDENT_NAME') or define('STUDENT_NAME', 'Pooja JP');
defined('COURSE_CODE') or define('COURSE_CODE', 'MCSP-232 (MCA Major Project)');
defined('CURRENCY_SYMBOL') or define('CURRENCY_SYMBOL', '₹');
defined('GST_PERCENT') or define('GST_PERCENT', 5.0); // 5% GST (2.5% CGST + 2.5% SGST)
defined('PLATFORM_FEE') or define('PLATFORM_FEE', 5.00);
defined('DELIVERY_FEE') or define('DELIVERY_FEE', 35.00);

if (!class_exists('Database')) {
class Database {
    private static ?PDO $instance = null;
    private static bool $isOffline = false;
    private static string $errorMessage = '';

    private const DB_HOST = '127.0.0.1';
    private const DB_PORT = '3306';
    private const DB_NAME = 'zippy_db';
    private const DB_USER = 'root';
    private const DB_PASS = '';

    /**
     * Get singleton PDO connection
     */
    public static function getConnection(): ?PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        if (self::$isOffline) {
            return null;
        }

        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', self::DB_HOST, self::DB_PORT, self::DB_NAME);
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            self::$instance = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
            return self::$instance;
        } catch (PDOException $e) {
            // Database might not exist yet; attempt to create and import schema
            if ($e->getCode() == 1049) { // 1049 = Unknown database
                return self::autoInstallDatabase();
            }

            // MySQL server might not be running or different credentials
            self::$isOffline = true;
            self::$errorMessage = $e->getMessage();
            return null;
        }
    }

    /**
     * Automatically create database and run schema.sql if first-time run on XAMPP
     */
    private static function autoInstallDatabase(): ?PDO {
        try {
            $rootDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', self::DB_HOST, self::DB_PORT);
            $rootPdo = new PDO($rootDsn, self::DB_USER, self::DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . self::DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Connect to newly created database
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', self::DB_HOST, self::DB_PORT, self::DB_NAME);
            self::$instance = new PDO($dsn, self::DB_USER, self::DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // Execute canonical schema file if available
            $schemaFile = __DIR__ . '/../../database/schema.sql';
            if (!file_exists($schemaFile)) {
                $schemaFile = __DIR__ . '/schema.sql';
            }
            if (file_exists($schemaFile)) {
                $sql = file_get_contents($schemaFile);
                self::$instance->exec($sql);
            }

            return self::$instance;
        } catch (PDOException $ex) {
            self::$isOffline = true;
            self::$errorMessage = $ex->getMessage();
            return null;
        }
    }

    public static function getInstance(): ?PDO {
        return self::getConnection();
    }

    public static function isMockMode(): bool {
        return !self::isConnected();
    }

    public static function getConnectionError(): ?string {
        return self::$errorMessage;
    }

    public static function setupDatabase(): array {
        $pdo = self::autoInstallDatabase();
        if ($pdo) {
            return ['success' => true, 'message' => 'Database zippy_db initialized successfully with 10 tables!'];
        }
        return ['success' => false, 'message' => 'Setup failed: ' . self::$errorMessage];
    }
}
} // end if (!class_exists('Database'))

// Initialize active user session if not set (Default Demo Customer: Pooja JP)
if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = [
        'user_id'         => 1,
        'role_id'         => 1,
        'role_name'       => 'CUSTOMER',
        'full_name'       => 'Pooja JP',
        'email'           => 'pooja.mca2026@ignou.ac.in',
        'phone_number'    => '+91 98765 43210',
        'membership_tier' => 'Zippy One Gold'
    ];
}

// Initialize session cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

/**
 * Format currency with Indian Rupee symbol
 */
function formatPrice(float|int|string $amount): string {
    return CURRENCY_SYMBOL . number_format((float)$amount, 2);
}

/**
 * Sanitize output for HTML
 */
function sanitize(string|null $str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Flash message helper
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
