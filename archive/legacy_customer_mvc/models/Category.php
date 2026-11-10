<?php
declare(strict_types=1);

/**
 * Category Model (Database-Backed)
 * MCSP-232: E-Commerce Platform for Food Order
 */
require_once __DIR__ . '/../config/database.php';

class Category {
    public int $category_id;
    public string $category_name;
    public string $category_description;
    public string $category_slug;
    public ?string $badge;
    public ?string $image_url;
    public bool $is_active;

    public static function getAll(): array {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }
        try {
            $stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order ASC, category_id ASC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Category::getAll error: " . $e->getMessage());
            return [];
        }
    }
}
