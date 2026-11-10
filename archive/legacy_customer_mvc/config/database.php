<?php
declare(strict_types=1);

/**
 * ZIPPY FOOD NETWORK - DATABASE CONFIGURATION & CONNECTION ADAPTER (PDO)
 * MCSP-232 Major Project - Master of Computer Applications
 * Student: Pooja JP | University: IGNOU
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';

// Global Configuration
defined('APP_NAME') or define('APP_NAME', 'Zippy');
defined('APP_TAGLINE') or define('APP_TAGLINE', 'Online Food Ordering & Cloud Kitchen Platform');
defined('STUDENT_NAME') or define('STUDENT_NAME', 'Pooja JP');
defined('COURSE_CODE') or define('COURSE_CODE', 'MCSP-232 (MCA Major Project)');
defined('CURRENCY_SYMBOL') or define('CURRENCY_SYMBOL', '₹');
defined('GST_PERCENT') or define('GST_PERCENT', 5.0);
defined('PLATFORM_FEE') or define('PLATFORM_FEE', 5.00);
defined('DELIVERY_FEE') or define('DELIVERY_FEE', 35.00);

if (!class_exists('Database')) {
class Database {
    private static ?PDO $instance = null;
    private static string $errorMessage = '';

    /**
     * Get singleton PDO connection via canonical config/database.php
     */
    public static function getConnection(): ?PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }
        try {
            self::$instance = getDBConnection();
            return self::$instance;
        } catch (Throwable $e) {
            self::$errorMessage = $e->getMessage();
            return null;
        }
    }

    public static function getInstance(): ?PDO {
        return self::getConnection();
    }

    public static function isConnected(): bool {
        return self::getConnection() !== null;
    }

    public static function isMockMode(): bool {
        return false;
    }

    public static function getConnectionError(): ?string {
        return self::$errorMessage;
    }
}
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

function formatPrice(float|int|string $amount): string {
    return CURRENCY_SYMBOL . number_format((float)$amount, 2);
}

function sanitize(?string $str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

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
