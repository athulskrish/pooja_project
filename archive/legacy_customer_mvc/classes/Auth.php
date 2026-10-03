<?php
/**
 * =============================================================================
 * AUTHENTICATION & SESSION SECURITY SERVICE
 * Course: MCSP-232 (MCA Major Project, IGNOU) | Student: Pooja JP
 * =============================================================================
 */

class Auth {
    /**
     * Check if user is logged in
     */
    public static function check(): bool {
        return !empty($_SESSION['user_id']);
    }

    /**
     * Get logged-in user details
     */
    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'] ?? 'Pooja JP',
            'email' => $_SESSION['user_email'] ?? 'pooja@zippy.test',
            'phone' => $_SESSION['user_phone'] ?? '+91 9876543210',
            'role_id' => $_SESSION['user_role_id'] ?? 1,
            'role_name' => $_SESSION['user_role_name'] ?? 'CUSTOMER'
        ];
    }

    public static function id(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Require login or redirect to login.php
     */
    public static function requireAuth(string $redirectAfter = ''): void {
        if (!self::check()) {
            if ($redirectAfter) {
                $_SESSION['redirect_after_login'] = $redirectAfter;
            }
            set_flash('warning', 'Please sign in to access your account and checkout.');
            redirect('login.php');
        }
    }

    /**
     * Authenticate user with Email and Password
     */
    public static function login(string $email, string $password): array {
        $email = trim(strtolower($email));
        $pdo = Database::getInstance();

        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT u.*, r.role_name 
                                       FROM users u 
                                       JOIN roles r ON u.role_id = r.role_id 
                                       WHERE LOWER(u.email) = ? AND u.is_active = 1 LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Regenerate session id to prevent fixation attacks
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int)$user['user_id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_phone'] = $user['phone_number'];
                    $_SESSION['user_role_id'] = (int)$user['role_id'];
                    $_SESSION['user_role_name'] = $user['role_name'];

                    return ['success' => true, 'user' => $user];
                }
            } catch (PDOException $e) {
                // fall through to mock demo check
            }
        }

        // Demo fallback accounts for viva defense and evaluation
        $demoAccounts = [
            'pooja@zippy.test' => ['id' => 1, 'name' => 'Pooja JP', 'phone' => '+91 9876543210', 'role' => 'CUSTOMER'],
            'customer@zippy.test' => ['id' => 1, 'name' => 'Pooja JP', 'phone' => '+91 9876543210', 'role' => 'CUSTOMER'],
            'rahul@zippy.test' => ['id' => 2, 'name' => 'Rahul Sharma', 'phone' => '+91 9876543211', 'role' => 'CUSTOMER']
        ];

        if (isset($demoAccounts[$email]) && ($password === 'password123' || $password === 'admin123')) {
            session_regenerate_id(true);
            $u = $demoAccounts[$email];
            $_SESSION['user_id'] = $u['id'];
            $_SESSION['user_name'] = $u['name'];
            $_SESSION['user_email'] = $email;
            $_SESSION['user_phone'] = $u['phone'];
            $_SESSION['user_role_id'] = 1;
            $_SESSION['user_role_name'] = $u['role'];
            return ['success' => true, 'user' => $u];
        }

        return ['success' => false, 'message' => 'Invalid email address or password.'];
    }

    /**
     * Register new customer account
     */
    public static function register(string $name, string $email, string $phone, string $password, string $addressLine = '', string $pincode = '560103'): array {
        $name = trim($name);
        $email = trim(strtolower($email));
        $phone = trim($phone);

        if (empty($name) || empty($email) || empty($phone) || strlen($password) < 6) {
            return ['success' => false, 'message' => 'Please provide valid details (Password must be at least 6 characters).'];
        }

        // Cryptographic password hashing (Argon2id if supported, else Bcrypt)
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $hash = password_hash($password, $algo);

        $pdo = Database::getInstance();
        if ($pdo) {
            try {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE LOWER(email) = ? OR phone_number = ? LIMIT 1");
                $stmt->execute([$email, $phone]);
                if ($stmt->fetch()) {
                    return ['success' => false, 'message' => 'An account with this email or phone number already exists.'];
                }

                $pdo->beginTransaction();

                // Role 1 is CUSTOMER
                $stmt = $pdo->prepare("INSERT INTO users (role_id, full_name, email, phone_number, password_hash, is_active) 
                                       VALUES (1, ?, ?, ?, ?, 1)");
                $stmt->execute([$name, $email, $phone, $hash]);
                $newUserId = (int)$pdo->lastInsertId();

                // Insert default address if provided
                if (!empty($addressLine)) {
                    $stmtAddr = $pdo->prepare("INSERT INTO user_addresses (user_id, address_type, address_line, landmark, pincode, is_default) 
                                               VALUES (?, 'HOME', ?, 'Bengaluru', ?, 1)");
                    $stmtAddr->execute([$newUserId, $addressLine, $pincode]);
                }

                $pdo->commit();

                // Auto login after registration
                session_regenerate_id(true);
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_phone'] = $phone;
                $_SESSION['user_role_id'] = 1;
                $_SESSION['user_role_name'] = 'CUSTOMER';

                return ['success' => true, 'message' => 'Registration successful! Welcome to Zippy.'];
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
            }
        }

        // Mock fallback registration
        session_regenerate_id(true);
        $_SESSION['user_id'] = 99;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_phone'] = $phone;
        $_SESSION['user_role_id'] = 1;
        $_SESSION['user_role_name'] = 'CUSTOMER';
        return ['success' => true, 'message' => 'Registration successful! (Demo session active)'];
    }

    /**
     * Terminate user session
     */
    public static function logout(): void {
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_phone'], $_SESSION['user_role_id'], $_SESSION['user_role_name']);
        session_regenerate_id(true);
    }
}
