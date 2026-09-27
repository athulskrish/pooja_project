<?php
/**
 * Address Model (3NF Customer Delivery Addresses)
 * MCSP-232: E-Commerce Platform for Food Order
 */
require_once __DIR__ . '/../config/database.php';

class Address {
    public static function getAllByUserId(int $userId): array {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, address_id DESC");
                $stmt->execute([$userId]);
                $addresses = $stmt->fetchAll();
                if (!empty($addresses)) return $addresses;
            } catch (PDOException $e) {}
        }

        // Fallback Mock Addresses
        return [
            [
                'address_id'   => 1,
                'user_id'      => $userId,
                'address_type' => 'HOME',
                'title'        => 'Indiranagar Flat',
                'address_line' => '#402, Green Meadows Apartment, 100 Feet Road, Indiranagar, Bengaluru',
                'landmark'     => 'Near Metro Pillar 84',
                'pincode'      => '560038',
                'is_default'   => 1
            ],
            [
                'address_id'   => 2,
                'user_id'      => $userId,
                'address_type' => 'WORK',
                'title'        => 'Tech Park Office',
                'address_line' => 'Tower B, 4th Floor, RMZ Infinity, Old Madras Road, Bennigana Halli, Bengaluru',
                'landmark'     => 'Opposite Gopalan Mall',
                'pincode'      => '560016',
                'is_default'   => 0
            ]
        ];
    }

    public static function getById(int $addressId): ?array {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE address_id = ?");
                $stmt->execute([$addressId]);
                $address = $stmt->fetch();
                if ($address) return $address;
            } catch (PDOException $e) {}
        }

        foreach (self::getAllByUserId(1) as $addr) {
            if ($addr['address_id'] === $addressId) return $addr;
        }
        return null;
    }

    public static function add(int $userId, string $type, string $title, string $line, string $landmark, string $pincode, bool $isDefault = false): bool {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                if ($isDefault) {
                    $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
                }
                $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, address_type, title, address_line, landmark, pincode, is_default) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?)");
                return $stmt->execute([$userId, $type, $title, $line, $landmark, $pincode, $isDefault ? 1 : 0]);
            } catch (PDOException $e) {
                return false;
            }
        }
        return true;
    }
}
