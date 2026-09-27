<?php
/**
 * =============================================================================
 * CATEGORY MODEL
 * =============================================================================
 */

class Category {
    private static array $mockCategories = [
        ['category_id' => 1, 'category_name' => 'Biryani Special', 'category_slug' => 'biryani', 'icon_class' => 'fa-solid fa-bowl-rice', 'category_description' => 'Slow-cooked aromatic dum basmati rice delicacies', 'dish_count' => 3],
        ['category_id' => 2, 'category_name' => 'Starters & Grills', 'category_slug' => 'starters', 'icon_class' => 'fa-solid fa-fire', 'category_description' => 'Tantalizing tandoori appetizers and sizzling kebabs', 'dish_count' => 3],
        ['category_id' => 3, 'category_name' => 'Main Course Curries', 'category_slug' => 'main-course', 'icon_class' => 'fa-solid fa-utensils', 'category_description' => 'Rich slow-simmered gravies and curries', 'dish_count' => 3],
        ['category_id' => 4, 'category_name' => 'Breads & Rice', 'category_slug' => 'breads-rice', 'icon_class' => 'fa-solid fa-bread-slice', 'category_description' => 'Freshly baked butter tandoori naans and rotis', 'dish_count' => 2],
        ['category_id' => 5, 'category_name' => 'Chinese & Wok', 'category_slug' => 'chinese', 'icon_class' => 'fa-solid fa-bowl-food', 'category_description' => 'Smoky stir-fried noodles and fried rice', 'dish_count' => 1],
        ['category_id' => 6, 'category_name' => 'Desserts & Sweets', 'category_slug' => 'desserts', 'icon_class' => 'fa-solid fa-ice-cream', 'category_description' => 'Traditional Indian milk delicacies and sweet dishes', 'dish_count' => 2],
        ['category_id' => 7, 'category_name' => 'Beverages & Coolers', 'category_slug' => 'beverages', 'icon_class' => 'fa-solid fa-glass-water', 'category_description' => 'Refreshing chilled lassis and spiced buttermilk', 'dish_count' => 2],
    ];

    public static function getAll(): array {
        $pdo = Database::getInstance();
        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT c.*, COUNT(m.item_id) as dish_count 
                                     FROM categories c 
                                     LEFT JOIN menu_items m ON c.category_id = m.category_id AND m.is_available = 1 
                                     WHERE c.is_active = 1 
                                     GROUP BY c.category_id 
                                     ORDER BY c.display_order ASC");
                return $stmt->fetchAll();
            } catch (PDOException $e) {
                // fall back to mock
            }
        }
        return self::$mockCategories;
    }

    public static function getBySlug(string $slug): ?array {
        $pdo = Database::getInstance();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM categories WHERE category_slug = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$slug]);
                $cat = $stmt->fetch();
                return $cat ?: null;
            } catch (PDOException $e) {
                // fallback
            }
        }
        foreach (self::$mockCategories as $c) {
            if ($c['category_slug'] === $slug) return $c;
        }
        return null;
    }
}
