<?php
declare(strict_types=1);

/**
 * User Model (RBAC Authentication & Profiles - Database-Backed)
 * MCSP-232: E-Commerce Platform for Food Order
 */
require_once __DIR__ . '/../config/database.php';

class User {
    public static function authenticate(string $email, string $password): ?array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT u.*, r.role_name 
                                   FROM users u 
                                   JOIN roles r ON u.role_id = r.role_id 
                                   WHERE LOWER(u.email) = :email AND u.is_active = 1");
            $stmt->execute([':email' => strtolower(trim($email))]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                unset($user['password_hash'], $user['security_answer_hash']);
                return $user;
            }
        } catch (PDOException $e) {
            error_log("models/User::authenticate error: " . $e->getMessage());
        }
        return null;
    }

    public static function register(string $fullName, string $email, string $phone, string $password): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (role_id, full_name, email, phone_number, password_hash) 
                                   VALUES (2, :name, :email, :phone, :hash)");
            return $stmt->execute([
                ':name'  => trim($fullName),
                ':email' => strtolower(trim($email)),
                ':phone' => trim($phone),
                ':hash'  => $hash
            ]);
        } catch (PDOException $e) {
            error_log("models/User::register error: " . $e->getMessage());
            return false;
        }
    }

    public static function getById(int $userId): ?array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if ($user) {
                unset($user['password_hash'], $user['security_answer_hash']);
                return $user;
            }
        } catch (PDOException $e) {
            error_log("models/User::getById error: " . $e->getMessage());
        }
        return null;
    }

    public static function updateProfile(int $userId, string $fullName, string $phone): bool {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone_number = ? WHERE user_id = ?");
            $ok = $stmt->execute([trim($fullName), trim($phone), $userId]);
            if ($ok && isset($_SESSION['user'])) {
                $_SESSION['user']['full_name'] = trim($fullName);
                $_SESSION['user']['phone_number'] = trim($phone);
            }
            return $ok;
        } catch (PDOException $e) {
            error_log("models/User::updateProfile error: " . $e->getMessage());
            return false;
        }
    }
}
