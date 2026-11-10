<?php
declare(strict_types=1);

/**
 * MenuItem Model (Database-Backed)
 * MCSP-232: E-Commerce Platform for Food Order
 */
require_once __DIR__ . '/../config/database.php';

class MenuItem {
    public static function getAll(?int $categoryId = null, ?bool $vegOnly = null, ?string $search = null): array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }
        try {
            $sql = "SELECT m.*, c.category_name, c.category_slug 
                    FROM menu_items m 
                    JOIN categories c ON m.category_id = c.category_id 
                    WHERE m.is_available = 1";
            $params = [];

            if ($categoryId !== null) {
                $sql .= " AND m.category_id = :catId";
                $params[':catId'] = $categoryId;
            }
            if ($vegOnly === true) {
                $sql .= " AND m.is_veg = 1";
            }
            if (!empty($search)) {
                $sql .= " AND (m.item_name LIKE :search OR m.description LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }

            $sql .= " ORDER BY m.rating DESC, m.item_id ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("models/MenuItem::getAll error: " . $e->getMessage());
            return [];
        }
    }

    public static function getById(int $itemId): ?array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT m.*, c.category_name FROM menu_items m JOIN categories c ON m.category_id = c.category_id WHERE m.item_id = ?");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            return $item ?: null;
        } catch (PDOException $e) {
            error_log("models/MenuItem::getById error: " . $e->getMessage());
            return null;
        }
    }

    public static function getBestsellers(int $limit = 6): array {
        $items = self::getAll();
        return array_slice($items, 0, $limit);
    }

    public static function getGroupedByCategory(?bool $vegOnly = null): array {
        $items = self::getAll(null, $vegOnly);
        $grouped = [];
        foreach ($items as $item) {
            $catName = $item['category_name'] ?? 'Other';
            $grouped[$catName][] = $item;
        }
        return $grouped;
    }
}
