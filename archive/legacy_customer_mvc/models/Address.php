<?php
declare(strict_types=1);

/**
 * Address Model (3NF Customer Delivery Addresses - Database-Backed)
 * MCSP-232: E-Commerce Platform for Food Order
 */
require_once __DIR__ . '/../config/database.php';

class Address {
    public static function getAllByUserId(int $userId): array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, address_id DESC");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Address::getAllByUserId error: " . $e->getMessage());
            return [];
        }
    }

    public static function getById(int $addressId): ?array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE address_id = ?");
            $stmt->execute([$addressId]);
            $address = $stmt->fetch();
            return $address ?: null;
        } catch (PDOException $e) {
            error_log("Address::getById error: " . $e->getMessage());
            return null;
        }
    }

    public static function add(int $userId, string $type, string $title, string $line, string $landmark, string $pincode, bool $isDefault = false): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }
        try {
            if ($isDefault) {
                $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
            }
            $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, address_type, address_line, landmark, pincode, is_default) 
                                   VALUES (?, ?, ?, ?, ?, ?)");
            return $stmt->execute([$userId, $type, trim($line), trim($landmark), trim($pincode), $isDefault ? 1 : 0]);
        } catch (PDOException $e) {
            error_log("Address::add error: " . $e->getMessage());
            return false;
        }
    }
}
