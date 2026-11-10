<?php
declare(strict_types=1);

/**
 * =============================================================================
 * DATABASE MANAGER CLASS (CANONICAL DELEGATION ADAPTER)
 * Course: MCSP-232 (MCA Major Project, IGNOU) | Student: Pooja JP
 * =============================================================================
 */

require_once __DIR__ . '/../../../config/database.php';

if (!class_exists('Database')) {
class Database {
    private static ?PDO $instance = null;
    private static ?string $connectionError = null;

    /**
     * Get Singleton PDO Instance from canonical configuration
     */
    public static function getInstance(): ?PDO {
        if (self::$instance === null) {
            try {
                self::$instance = getDBConnection();
            } catch (Throwable $e) {
                self::$connectionError = $e->getMessage();
                self::$instance = null;
            }
        }
        return self::$instance;
    }

    /**
     * Legacy compatibility method: mock mode is permanently disabled
     */
    public static function isMockMode(): bool {
        return false;
    }

    /**
     * Get connection error message if any
     */
    public static function getConnectionError(): ?string {
        return self::$connectionError;
    }

    /**
     * Initialize / Reinstall Database from canonical schema.sql
     */
    public static function setupDatabase(): array {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4", DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . DB_NAME . "`");

            $schemaFile = __DIR__ . '/../../../database/schema.sql';
            if (file_exists($schemaFile)) {
                $schemaSql = file_get_contents($schemaFile);
                $cleanedSql = preg_replace('/DELIMITER\s+[\/\/;]/i', '', (string)$schemaSql);
                $cleanedSql = str_replace('//', ';', (string)$cleanedSql);
                $statements = array_filter(array_map('trim', explode(';', (string)$cleanedSql)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt)) {
                        try {
                            $pdo->exec($stmt);
                        } catch (PDOException $ex) {
                            // Continue on non-fatal trigger delimiters
                        }
                    }
                }
            }

            self::$instance = null;
            self::getInstance();

            return ['success' => true, 'message' => 'Database ' . DB_NAME . ' initialized with canonical 3NF schema.'];
        } catch (PDOException $e) {
            self::$connectionError = $e->getMessage();
            return ['success' => false, 'message' => 'Setup failed: ' . $e->getMessage()];
        }
    }
}
}
