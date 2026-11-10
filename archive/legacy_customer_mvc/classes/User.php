<?php
declare(strict_types=1);

/**
 * =============================================================================
 * USER & ADDRESS MODEL (3NF NORMALIZATION, DATABASE-BACKED)
 * Course: MCSP-232 (MCA Major Project, IGNOU) | Student: Pooja JP
 * =============================================================================
 */

class User {
    /**
     * Get user profile details from database
     */
    public static function getProfile(int $userId): ?array {
        $pdo = Database::getInstance();
        if (!$pdo) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT u.*, r.role_name 
                                   FROM users u 
                                   JOIN roles r ON u.role_id = r.role_id 
                                   WHERE u.user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if ($user) {
                unset($user['password_hash'], $user['security_answer_hash']);
                return $user;
            }
        } catch (PDOException $e) {
            error_log("User::getProfile error: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Update user profile in database
     */
    public static function updateProfile(int $userId, string $fullName, string $phone): bool {
        $pdo = Database::getInstance();
        if (!$pdo) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone_number = ? WHERE user_id = ?");
            $ok = $stmt->execute([trim($fullName), trim($phone), $userId]);
            if ($ok) {
                $_SESSION['user_name'] = trim($fullName);
                $_SESSION['user_phone'] = trim($phone);
            }
            return $ok;
        } catch (PDOException $e) {
            error_log("User::updateProfile error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all addresses for a user from database
     */
    public static function getAddresses(int $userId): array {
        $pdo = Database::getInstance();
        if (!$pdo) {
            return [];
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, address_id DESC");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("User::getAddresses error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add new delivery address
     */
    public static function addAddress(int $userId, string $type, string $line, string $landmark, string $pincode, bool $isDefault = false): bool {
        $pdo = Database::getInstance();
        if (!$pdo) {
            return false;
        }
        try {
            if ($isDefault) {
                $reset = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
                $reset->execute([$userId]);
            }
            $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, address_type, address_line, landmark, pincode, is_default) 
                                   VALUES (?, ?, ?, ?, ?, ?)");
            return $stmt->execute([$userId, $type, trim($line), trim($landmark), trim($pincode), $isDefault ? 1 : 0]);
        } catch (PDOException $e) {
            error_log("User::addAddress error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete delivery address
     */
    public static function deleteAddress(int $addressId, int $userId): bool {
        $pdo = Database::getInstance();
        if (!$pdo) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM user_addresses WHERE address_id = ? AND user_id = ?");
            return $stmt->execute([$addressId, $userId]);
        } catch (PDOException $e) {
            error_log("User::deleteAddress error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set address as default
     */
    public static function setDefaultAddress(int $addressId, int $userId): bool {
        $pdo = Database::getInstance();
        if (!$pdo) {
            return false;
        }
        try {
            $pdo->beginTransaction();
            $reset = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
            $reset->execute([$userId]);
            $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE address_id = ? AND user_id = ?");
            $stmt->execute([$addressId, $userId]);
            $pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("User::setDefaultAddress error: " . $e->getMessage());
            return false;
        }
    }
}
