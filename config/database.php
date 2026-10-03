<?php
/**
 * Database Connection & PDO Configuration
 * Zippy - Online Food Ordering Platform
 * Student: Pooja JP | Course: MCSP-232
 */

// Auto-load local .env file if present in project root
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if (getenv($key) === false) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
            }
        }
    }
}

// Database Credentials
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'zippy_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a shared PDO instance
 * 
 * @return PDO|null
 */
function getDBConnection(): ?PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error or keep null for fallback notification
            error_log("Database Connection Error: " . $e->getMessage());
            return null;
        }
    }

    return $pdo;
}

/**
 * Check if the database is reachable and schema exists
 * 
 * @return array ['connected' => bool, 'tables_exist' => bool, 'error' => string|null]
 */
function checkDBStatus(): array {
    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            return [
                'connected' => false,
                'tables_exist' => false,
                'error' => 'Cannot connect to MySQL server at ' . DB_HOST . ':' . DB_PORT . '. Please ensure MySQL is running (e.g. XAMPP MySQL).'
            ];
        }

        // Check if `orders` or `users` table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        $exists = $stmt->rowCount() > 0;

        return [
            'connected' => true,
            'tables_exist' => $exists,
            'error' => $exists ? null : 'Connected to MySQL, but database tables are not yet created. Run database/seed.php or import schema.sql.'
        ];
    } catch (PDOException $e) {
        return [
            'connected' => false,
            'tables_exist' => false,
            'error' => $e->getMessage()
        ];
    }
}
