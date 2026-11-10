<?php
declare(strict_types=1);

/**
 * =============================================================================
 * CATEGORY MODEL (CANONICAL DATABASE-BACKED)
 * =============================================================================
 */

class Category {
    public static function getAll(): array {
        $pdo = Database::getInstance();
        if (!$pdo) {
            return [];
        }
        try {
            $stmt = $pdo->query("SELECT c.*, COUNT(m.item_id) as dish_count 
                                 FROM categories c 
                                 LEFT JOIN menu_items m ON c.category_id = m.category_id AND m.is_available = 1 
                                 WHERE c.is_active = 1 
                                 GROUP BY c.category_id 
                                 ORDER BY c.display_order ASC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Category::getAll error: " . $e->getMessage());
            return [];
        }
    }

    public static function getBySlug(string $slug): ?array {
        $pdo = Database::getInstance();
        if (!$pdo) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM categories WHERE category_slug = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$slug]);
            $cat = $stmt->fetch();
            return $cat ?: null;
        } catch (PDOException $e) {
            error_log("Category::getBySlug error: " . $e->getMessage());
            return null;
        }
    }
}
