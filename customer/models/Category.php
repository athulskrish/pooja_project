<?php
/**
 * Category Model
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
        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY category_id ASC");
                return $stmt->fetchAll();
            } catch (PDOException $e) {
                // fall through to mock
            }
        }

        // Fallback Mock Categories
        return [
            [
                'category_id' => 1,
                'category_name' => 'Biryani',
                'category_description' => 'Authentic dum biryanis, aromatic spices and slow-cooked tender meat',
                'category_slug' => 'biryani',
                'badge' => 'Popular',
                'image_url' => 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?auto=format&fit=crop&w=400&q=80'
            ],
            [
                'category_id' => 2,
                'category_name' => 'Starters & Kebabs',
                'category_description' => 'Crispy, smoky appetisers grilled in traditional charcoal tandoors',
                'category_slug' => 'starters',
                'badge' => 'Top Rated',
                'image_url' => 'https://images.unsplash.com/photo-1599488615731-7e5c2823ff28?auto=format&fit=crop&w=400&q=80'
            ],
            [
                'category_id' => 3,
                'category_name' => 'Main Course & Curries',
                'category_description' => 'Rich and aromatic curries, gravies, and slow-simmered stews',
                'category_slug' => 'curries',
                'badge' => 'Chef Choice',
                'image_url' => 'https://images.unsplash.com/photo-1588166524941-3bf61a9c41db?auto=format&fit=crop&w=400&q=80'
            ],
            [
                'category_id' => 4,
                'category_name' => 'Breads & Rice',
                'category_description' => 'Freshly baked tandoori naans, rotis, and fragrant jeera rice',
                'category_slug' => 'breads',
                'badge' => null,
                'image_url' => 'https://images.unsplash.com/photo-1601050690597-df0568f70950?auto=format&fit=crop&w=400&q=80'
            ],
            [
                'category_id' => 5,
                'category_name' => 'Desserts & Sweets',
                'category_description' => 'Decadent traditional Indian sweets and classic cold treats',
                'category_slug' => 'desserts',
                'badge' => 'Sweet Tooth',
                'image_url' => 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?auto=format&fit=crop&w=400&q=80'
            ],
            [
                'category_id' => 6,
                'category_name' => 'Beverages',
                'category_description' => 'Refreshing fruit lassis, cold sodas, and coolers',
                'category_slug' => 'beverages',
                'badge' => 'Chilled',
                'image_url' => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=400&q=80'
            ],
        ];
    }
}
