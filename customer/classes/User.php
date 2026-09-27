<?php
/**
 * =============================================================================
 * USER & ADDRESS MODEL (3NF NORMALIZATION)
 * Course: MCSP-232 (MCA Major Project, IGNOU) | Student: Pooja JP
 * =============================================================================
 */

class User {
    private static array $mockAddresses = [
        [
            'address_id' => 1,
            'user_id' => 1,
            'address_type' => 'HOME',
            'address_line' => 'Flat 402, Green Glen Layout, 3rd Cross, Bellandur',
            'landmark' => 'Near Sobha Silicon Oasis',
            'pincode' => '560103',
            'is_default' => 1
        ],
        [
            'address_id' => 2,
            'user_id' => 1,
            'address_type' => 'WORK',
            'address_line' => '5th Floor, Salarpuria Softzone, Outer Ring Road, Bellandur',
            'landmark' => 'Opposite Intel Campus',
            'pincode' => '560103',
            'is_default' => 0
        ]
    ];

    /**
     * Get user profile details
     */
    public static function getProfile(int $userId): ?array {
        $pdo = Database::getInstance();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT u.*, r.role_name 
                                       FROM users u 
                                       JOIN roles r ON u.role_id = r.role_id 
                                       WHERE u.user_id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                if ($user) return $user;
            } catch (PDOException $e) {
                // fallback
            }
        }
        return [
            'user_id' => $userId,
            'full_name' => $_SESSION['user_name'] ?? 'Pooja JP',
            'email' => $_SESSION['user_email'] ?? 'pooja@zippy.test',
            'phone_number' => $_SESSION['user_phone'] ?? '+91 9876543210',
            'role_name' => 'CUSTOMER',
            'created_at' => date('Y-m-d H:i:s', strtotime('-30 days'))
        ];
    }

    /**
     * Update user profile
     */
    public static function updateProfile(int $userId, string $fullName, string $phone): bool {
        $pdo = Database::getInstance();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone_number = ? WHERE user_id = ?");
                $stmt->execute([trim($fullName), trim($phone), $userId]);
                $_SESSION['user_name'] = trim($fullName);
                $_SESSION['user_phone'] = trim($phone);
                return true;
            } catch (PDOException $e) {
                // fallback
            }
        }
        $_SESSION['user_name'] = trim($fullName);
        $_SESSION['user_phone'] = trim($phone);
        return true;
    }

    /**
     * Get all addresses for a user
     */
    public static function getAddresses(int $userId): array {
        $pdo = Database::getInstance();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, address_id DESC");
                $stmt->execute([$userId]);
                return $stmt->fetchAll();
            } catch (PDOException $e) {
                // fallback
            }
        }
        return self::$mockAddresses;
    }

    /**
     * Add new delivery address
     */
    public static function addAddress(int $userId, string $type, string $line, string $landmark, string $pincode, bool $isDefault = false): bool {
        $pdo = Database::getInstance();
        if ($pdo) {
            try {
                if ($isDefault) {
                    $reset = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
                    $reset->execute([$userId]);
                }
                $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, address_type, address_line, landmark, pincode, is_default) 
                                       VALUES (?, ?, ?, ?, ?, ?)");
                return $stmt->execute([$userId, $type, trim($line), trim($landmark), trim($pincode), $isDefault ? 1 : 0]);
            } catch (PDOException $e) {
                // fallback
            }
        }
        self::$mockAddresses[] = [
            'address_id' => count(self::$mockAddresses) + 1,
            'user_id' => $userId,
            'address_type' => $type,
            'address_line' => $line,
            'landmark' => $landmark,
            'pincode' => $pincode,
            'is_default' => $isDefault ? 1 : 0
        ];
        return true;
    }

    /**
     * Delete delivery address
     */
    public static function deleteAddress(int $addressId, int $userId): bool {
        $pdo = Database::getInstance();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM user_addresses WHERE address_id = ? AND user_id = ?");
                return $stmt->execute([$addressId, $userId]);
            } catch (PDOException $e) {
                // fallback
            }
        }
        self::$mockAddresses = array_filter(self::$mockAddresses, fn($a) => $a['address_id'] !== $addressId);
        return true;
    }

    /**
     * Set address as default
     */
    public static function setDefaultAddress(int $addressId, int $userId): bool {
        $pdo = Database::getInstance();
        if ($pdo) {
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
            }
        }
        return true;
    }
}
