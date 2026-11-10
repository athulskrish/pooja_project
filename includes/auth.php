<?php
declare(strict_types=1);
/**
 * Authentication & RBAC Access Control Engine
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/functions.php';

/**
 * Predefined account recovery security questions
 * 
 * @return array<int, string>
 */
function getSecurityQuestions(): array
{
    return [
        'What was the name of your first school?',
        'What was the name of your first pet?',
        'In which city were you born?',
        "What is your mother's maiden name?",
        'What is your favorite childhood food?',
        'What was your childhood nickname?'
    ];
}

/**
 * Normalize security question answer for case-insensitive matching
 */
function normalizeSecurityAnswer(string $answer): string
{
    return strtolower(trim((string)preg_replace('/\s+/', ' ', $answer)));
}

/**
 * Ensure `security_question` and `security_answer_hash` columns exist in `users`
 * Provides automatic plug-and-play schema compatibility on XAMPP setups.
 */
function ensureSecurityQuestionColumns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'security_question'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("
                ALTER TABLE `users`
                ADD COLUMN `security_question` VARCHAR(255) NULL DEFAULT NULL AFTER `password_hash`,
                ADD COLUMN `security_answer_hash` VARCHAR(255) NULL DEFAULT NULL AFTER `security_question`
            ");
        }
        $checked = true;
    } catch (Throwable $e) {
        error_log("Security columns check failed: " . $e->getMessage());
    }
}

/**
 * Resolve convenient demo email aliases to canonical database email
 */
function resolveEmailAlias(string $email): string
{
    $lookupEmail = trim(strtolower($email));
    if (!defined('APP_DEMO_MODE') || !APP_DEMO_MODE) {
        return $lookupEmail;
    }
    $aliases = [
        'pooja@zippy.com'    => 'admin@zippy.com',
        'sajeev@zippy.com'   => 'chef@zippy.com',
        'rider@zippy.com'    => 'shaji@zippy.com',
        'customer@zippy.com' => 'athul@example.com',
        'athul@zippy.com'    => 'athul@example.com',
        'amal@zippy.com'     => 'amal@example.com',
        'abhijith@zippy.com' => 'abhijith@example.com',
    ];
    return $aliases[$lookupEmail] ?? $lookupEmail;
}

/**
 * Attempt user authentication
 * 
 * @param string $email
 * @param string $password
 * @param string|null $requiredRole Optional required role name (e.g. 'ADMIN' or 'CUSTOMER')
 * @return array ['success' => bool, 'message' => string, 'user' => array|null]
 */
function loginUser(string $email, string $password, ?string $requiredRole = null): array
{
    $lookupEmail = resolveEmailAlias($email);
    
    $sql = "SELECT u.*, r.role_name 
            FROM `users` u 
            JOIN `roles` r ON u.role_id = r.role_id 
            WHERE LOWER(u.email) = :email 
            LIMIT 1";
    
    $user = dbFetchOne($sql, [':email' => $lookupEmail]);

    if (!$user) {
        return ['success' => false, 'message' => 'No account found with this email address.', 'user' => null];
    }

    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'This account has been deactivated. Please contact support.', 'user' => null];
    }

    // Role check if enforced
    if ($requiredRole !== null && strtoupper($user['role_name']) !== strtoupper($requiredRole)) {
        return ['success' => false, 'message' => "Access denied. This login is reserved for {$requiredRole}s.", 'user' => null];
    }

    // Verify Password (Argon2id / Bcrypt with transparent algorithm rehash)
    $passwordValid = password_verify($password, (string)$user['password_hash']);
    if ($passwordValid && password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        dbQuery("UPDATE `users` SET `password_hash` = :h WHERE `user_id` = :id", [
            ':h' => $newHash,
            ':id' => (int)$user['user_id']
        ]);
    }

    if (!$passwordValid) {
        return ['success' => false, 'message' => 'Incorrect password entered.', 'user' => null];
    }

    // Successful login - Regenerate session ID for anti-fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['user_id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_phone'] = $user['phone_number'];
    $_SESSION['role_id'] = (int)$user['role_id'];
    $_SESSION['role_name'] = strtoupper($user['role_name']);

    // Ensure rider is online by default on duty
    if (strtoupper($user['role_name']) === 'RIDER') {
        $_SESSION['rider_on_duty'] = true;
    }

    // Set a portal companion cookie for multi-actor status tracking on launchpad
    $portal = getCurrentPortal();
    $actorCookie = 'ZIPPY_ACTOR_' . strtoupper($portal);
    $actorData = json_encode([
        'id' => (int)$user['user_id'],
        'name' => $user['full_name'],
        'email' => $user['email'],
        'role_name' => strtoupper($user['role_name'])
    ]);
    if (PHP_VERSION_ID >= 70300) {
        setcookie($actorCookie, $actorData, [
            'expires' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
    } else {
        setcookie($actorCookie, $actorData, 0, '/; samesite=Lax', '', false, false);
    }

    return ['success' => true, 'message' => 'Login successful.', 'user' => $user];
}

/**
 * Register a new customer with offline recovery security question
 */
function registerCustomer(
    string $fullName,
    string $email,
    string $phone,
    string $password,
    ?string $address = null,
    ?string $pincode = null,
    ?string $securityQuestion = null,
    ?string $securityAnswer = null
): array
{
    $fullName = trim($fullName);
    $email = trim(strtolower($email));
    $phone = trim($phone);
    $securityQuestion = $securityQuestion !== null ? trim($securityQuestion) : '';
    $securityAnswer = $securityAnswer !== null ? normalizeSecurityAnswer($securityAnswer) : '';

    if (empty($fullName) || empty($email) || empty($phone) || empty($password)) {
        return ['success' => false, 'message' => 'Please fill in all required fields.'];
    }

    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters long.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.'];
    }

    // Check if email already exists
    $existing = dbFetchVal("SELECT COUNT(*) FROM `users` WHERE LOWER(email) = :email", [':email' => $email]);
    if ($existing > 0) {
        return ['success' => false, 'message' => 'An account with this email address already exists.'];
    }

    // Check if phone already exists
    $existingPhone = dbFetchVal("SELECT COUNT(*) FROM `users` WHERE phone_number = :phone", [':phone' => $phone]);
    if ($existingPhone > 0) {
        return ['success' => false, 'message' => 'An account with this phone number already exists.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $secAnswerHash = !empty($securityAnswer) ? password_hash($securityAnswer, PASSWORD_DEFAULT) : null;
    $secQuestionVal = !empty($securityQuestion) && !empty($secAnswerHash) ? $securityQuestion : null;

    $pdo = getDbConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }

    ensureSecurityQuestionColumns($pdo);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO `users` (`role_id`, `full_name`, `email`, `phone_number`, `password_hash`, `security_question`, `security_answer_hash`, `is_active`) 
            VALUES (2, :name, :email, :phone, :hash, :sq, :sa, 1)
        ");
        $stmt->execute([
            ':name'  => $fullName,
            ':email' => $email,
            ':phone' => $phone,
            ':hash'  => $hash,
            ':sq'    => $secQuestionVal,
            ':sa'    => $secAnswerHash
        ]);

        $userId = (int)$pdo->lastInsertId();

        // If initial address provided, save to user_addresses
        if (!empty($address)) {
            $addrStmt = $pdo->prepare("INSERT INTO `user_addresses` (`user_id`, `address_type`, `address_line`, `pincode`, `is_default`) VALUES (:uid, 'HOME', :line, :pin, 1)");
            $addrStmt->execute([
                ':uid' => $userId,
                ':line' => $address,
                ':pin' => $pincode ?: '560001'
            ]);
        }

        $pdo->commit();

        // Auto login
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $fullName;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_phone'] = $phone;
        $_SESSION['role_id'] = 2;
        $_SESSION['role_name'] = 'CUSTOMER';

        // Companion cookie
        $actorData = json_encode([
            'id' => $userId,
            'name' => $fullName,
            'email' => $email,
            'role_name' => 'CUSTOMER'
        ]);
        setcookie('ZIPPY_ACTOR_CUSTOMER', $actorData, 0, '/');

        return ['success' => true, 'message' => 'Account created successfully! Welcome to Zippy.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Registration error: ' . $e->getMessage()];
    }
}

/**
 * Step 1 of Offline Password Reset: Look up user and return their recovery question
 */
function lookupUserSecurityQuestion(string $email): array
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }
    ensureSecurityQuestionColumns($pdo);

    $lookupEmail = resolveEmailAlias($email);
    if (empty($lookupEmail) || !filter_var($lookupEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid registered email address.'];
    }

    $user = dbFetchOne("
        SELECT `user_id`, `full_name`, `email`, `security_question`, `security_answer_hash`, `is_active`
        FROM `users`
        WHERE LOWER(`email`) = :email
        LIMIT 1
    ", [':email' => $lookupEmail]);

    if (!$user) {
        return ['success' => false, 'message' => 'No account found with that email address.'];
    }

    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'This account is inactive. Please contact support.'];
    }

    if (empty($user['security_question']) || empty($user['security_answer_hash'])) {
        return ['success' => false, 'message' => 'No security question is configured for this account. Please sign in and set one under My Profile, or contact an Administrator.'];
    }

    return [
        'success'           => true,
        'user_id'           => (int)$user['user_id'],
        'full_name'         => $user['full_name'],
        'email'             => $user['email'],
        'security_question' => $user['security_question']
    ];
}

/**
 * Step 2 of Offline Password Reset: Verify security answer and update password hash atomically
 */
function resetPasswordWithSecurityAnswer(
    string $email,
    string $answer,
    string $newPassword,
    string $confirmPassword
): array
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }
    ensureSecurityQuestionColumns($pdo);

    $lookupEmail = resolveEmailAlias($email);
    $normalizedAnswer = normalizeSecurityAnswer($answer);

    if ($normalizedAnswer === '') {
        return ['success' => false, 'message' => 'Please enter the answer to your security question.'];
    }

    if (strlen($newPassword) < 6) {
        return ['success' => false, 'message' => 'New password must be at least 6 characters long.'];
    }

    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'message' => 'New password and confirmation password do not match.'];
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT `user_id`, `security_question`, `security_answer_hash`, `is_active`
            FROM `users`
            WHERE LOWER(`email`) = :email
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([':email' => $lookupEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !$user['is_active']) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Account not found or inactive.'];
        }

        if (empty($user['security_answer_hash']) || !password_verify($normalizedAnswer, $user['security_answer_hash'])) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Incorrect security answer. Please check spelling and try again.'];
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updStmt = $pdo->prepare("UPDATE `users` SET `password_hash` = :hash WHERE `user_id` = :id");
        $updStmt->execute([
            ':hash' => $newHash,
            ':id'   => (int)$user['user_id']
        ]);

        $pdo->commit();

        return [
            'success' => true,
            'message' => 'Your password has been reset successfully! Please sign in with your new password.'
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Password reset error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Unable to reset password right now. Please try again.'];
    }
}

/**
 * Update a logged-in user's recovery security question and answer
 */
function updateUserSecurityQuestion(int $userId, string $question, string $answer): array
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }
    ensureSecurityQuestionColumns($pdo);

    $question = trim($question);
    $normalizedAnswer = normalizeSecurityAnswer($answer);

    if ($question === '' || $normalizedAnswer === '') {
        return ['success' => false, 'message' => 'Please select a security question and provide an answer.'];
    }

    $answerHash = password_hash($normalizedAnswer, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE `users` SET `security_question` = :sq, `security_answer_hash` = :sa WHERE `user_id` = :uid");
    $stmt->execute([
        ':sq'  => $question,
        ':sa'  => $answerHash,
        ':uid' => $userId
    ]);

    return ['success' => true, 'message' => 'Account recovery security question updated successfully.'];
}

/**
 * Logout and clear session
 */
function logoutUser(): void
{
    $portal = getCurrentPortal();
    $actorCookie = 'ZIPPY_ACTOR_' . strtoupper($portal);
    setcookie($actorCookie, '', time() - 3600, '/');

    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Get role details for a given user ID
 */
function getUserRole(int $userId): ?array
{
    return dbFetchOne("
        SELECT r.role_id, r.role_name, r.description 
        FROM `users` u 
        JOIN `roles` r ON u.role_id = r.role_id 
        WHERE u.user_id = :id
    ", [':id' => $userId]);
}

/**
 * Update user role with safety verification
 */
function updateUserRole(int $userId, int $newRoleId): array
{
    $cur = getCurrentUser();
    if (!isAdmin()) {
        return ['success' => false, 'message' => 'Administrator privileges required to update user roles.'];
    }

    $validRole = dbFetchOne("SELECT * FROM `roles` WHERE `role_id` = :rid", [':rid' => $newRoleId]);
    if (!$validRole) {
        return ['success' => false, 'message' => 'Specified role does not exist.'];
    }

    $targetUser = dbFetchOne("
        SELECT u.*, r.role_name 
        FROM `users` u 
        JOIN `roles` r ON u.role_id = r.role_id 
        WHERE u.user_id = :id
    ", [':id' => $userId]);

    if (!$targetUser) {
        return ['success' => false, 'message' => 'Target user account not found.'];
    }

    // Safety 1: Prevent self-demotion from ADMIN to prevent lockout
    if ($userId === (int)($cur['id'] ?? 0) && $newRoleId !== 1) {
        return ['success' => false, 'message' => 'Security Restriction: Self-demotion from Administrator role is prohibited to prevent system lockout.'];
    }

    // Safety 2: Ensure at least one active administrator remains
    if ((int)$targetUser['role_id'] === 1 && $newRoleId !== 1) {
        $otherAdmins = (int)dbFetchVal("
            SELECT COUNT(*) FROM `users` 
            WHERE `role_id` = 1 AND `is_active` = 1 AND `user_id` != :id
        ", [':id' => $userId]);
        if ($otherAdmins <= 0) {
            return ['success' => false, 'message' => 'Security Restriction: Cannot demote the last remaining active Administrator.'];
        }
    }

    $updated = dbQuery("UPDATE `users` SET `role_id` = :rid WHERE `user_id` = :id", [
        ':rid' => $newRoleId,
        ':id' => $userId
    ]);

    if ($updated) {
        return [
            'success' => true,
            'message' => "User '{$targetUser['full_name']}' role updated from {$targetUser['role_name']} to {$validRole['role_name']}."
        ];
    }

    return ['success' => false, 'message' => 'Failed to update user role in database.'];
}
