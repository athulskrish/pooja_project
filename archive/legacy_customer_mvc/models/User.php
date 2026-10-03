<?php
/**
 * User Model (RBAC Authentication & Profiles)
 * MCSP-232: E-Commerce Platform for Food Order
 */
require_once __DIR__ . '/../config/database.php';

class User {
    public static function authenticate(string $email, string $password): ?array {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT u.*, r.role_name 
                                        FROM users u 
                                        JOIN roles r ON u.role_id = r.role_id 
                                        WHERE u.email = :email AND u.is_active = 1");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();
                if ($user) {
                    // Check password hash (or fallback for demo 'pooja123')
                    if (password_verify($password, $user['password_hash']) || $password === 'pooja123') {
                        unset($user['password_hash']);
                        return $user;
                    }
                }
            } catch (PDOException $e) {}
        }

        // Demo fallback accounts when database offline
        $demoUsers = [
            'pooja.mca2026@ignou.ac.in' => [
                'user_id' => 1, 'role_id' => 1, 'role_name' => 'CUSTOMER',
                'full_name' => 'Pooja JP', 'email' => 'pooja.mca2026@ignou.ac.in',
                'phone_number' => '+91 98765 43210', 'membership_tier' => 'Zippy One Gold'
            ],
            'kitchen@zippy.in' => [
                'user_id' => 2, 'role_id' => 2, 'role_name' => 'KITCHEN',
                'full_name' => 'Chef Sanjeev (Head Cook)', 'email' => 'kitchen@zippy.in',
                'phone_number' => '+91 98450 11223', 'membership_tier' => 'Kitchen Staff'
            ],
            'rider@zippy.in' => [
                'user_id' => 3, 'role_id' => 3, 'role_name' => 'RIDER',
                'full_name' => 'Ramesh Kumar (Courier)', 'email' => 'rider@zippy.in',
                'phone_number' => '+91 98450 44556', 'membership_tier' => 'Delivery Partner'
            ],
            'admin@zippy.in' => [
                'user_id' => 4, 'role_id' => 4, 'role_name' => 'ADMIN',
                'full_name' => 'Pooja JP (Administrator)', 'email' => 'admin@zippy.in',
                'phone_number' => '+91 98765 00000', 'membership_tier' => 'Super Admin'
            ]
        ];

        if (isset($demoUsers[$email]) && ($password === 'pooja123' || $password === 'admin123')) {
            return $demoUsers[$email];
        }

        return null;
    }

    public static function register(string $fullName, string $email, string $phone, string $password): bool {
        $pdo = Database::getConnection();
        $hash = password_hash($password, PASSWORD_BCRYPT);

        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO users (role_id, full_name, email, phone_number, password_hash) 
                                       VALUES (1, :name, :email, :phone, :hash)");
                return $stmt->execute([
                    ':name'  => $fullName,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':hash'  => $hash
                ]);
            } catch (PDOException $e) {
                return false;
            }
        }

        // Mock registration success
        $_SESSION['user'] = [
            'user_id' => rand(10, 999),
            'role_id' => 1,
            'role_name' => 'CUSTOMER',
            'full_name' => $fullName,
            'email' => $email,
            'phone_number' => $phone,
            'membership_tier' => 'Zippy Member'
        ];
        return true;
    }

    public static function getById(int $userId): ?array {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                if ($user) {
                    unset($user['password_hash']);
                    return $user;
                }
            } catch (PDOException $e) {}
        }

        return $_SESSION['user'] ?? null;
    }

    public static function updateProfile(int $userId, string $fullName, string $phone): bool {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone_number = ? WHERE user_id = ?");
                $stmt->execute([$fullName, $phone, $userId]);
            } catch (PDOException $e) {}
        }

        if (isset($_SESSION['user'])) {
            $_SESSION['user']['full_name'] = $fullName;
            $_SESSION['user']['phone_number'] = $phone;
        }
        return true;
    }
}
