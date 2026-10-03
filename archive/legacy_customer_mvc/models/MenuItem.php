<?php
/**
 * MenuItem Model
 * MCSP-232: E-Commerce Platform for Food Order
 */
require_once __DIR__ . '/../config/database.php';

class MenuItem {
    public static function getAll(?int $categoryId = null, ?bool $vegOnly = null, ?string $search = null): array {
        $pdo = Database::getConnection();
        if ($pdo) {
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

                $sql .= " ORDER BY m.is_bestseller DESC, m.rating DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll();
            } catch (PDOException $e) {
                // fall through to mock
            }
        }

        // Fallback Mock Items
        $items = self::getMockItems();

        if ($categoryId !== null) {
            $items = array_filter($items, fn($i) => $i['category_id'] === $categoryId);
        }
        if ($vegOnly === true) {
            $items = array_filter($items, fn($i) => (bool)$i['is_veg'] === true);
        }
        if (!empty($search)) {
            $searchLower = strtolower($search);
            $items = array_filter($items, function($i) use ($searchLower) {
                return str_contains(strtolower($i['item_name']), $searchLower) ||
                       str_contains(strtolower($i['description']), $searchLower);
            });
        }

        return array_values($items);
    }

    public static function getById(int $itemId): ?array {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT m.*, c.category_name FROM menu_items m JOIN categories c ON m.category_id = c.category_id WHERE m.item_id = ?");
                $stmt->execute([$itemId]);
                $item = $stmt->fetch();
                if ($item) return $item;
            } catch (PDOException $e) {}
        }

        foreach (self::getMockItems() as $item) {
            if ($item['item_id'] === $itemId) return $item;
        }
        return null;
    }

    public static function getBestsellers(int $limit = 6): array {
        $items = self::getAll();
        $bestsellers = array_filter($items, fn($i) => !empty($i['is_bestseller']));
        return array_slice($bestsellers, 0, $limit);
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

    public static function getMockItems(): array {
        return [
            [
                'item_id' => 1,
                'category_id' => 1,
                'category_name' => 'Biryani',
                'category_slug' => 'biryani',
                'item_name' => 'Meghana Special Chicken Biryani',
                'description' => 'Our signature spicy Andhra-style chicken biryani cooked with secret spices, served with salan and chilled raita.',
                'price' => 340.00,
                'stock_quantity' => 45,
                'is_available' => 1,
                'is_veg' => 0,
                'is_bestseller' => 1,
                'rating' => 4.8,
                'rating_count' => 1240,
                'prep_time_minutes' => 14,
                'kitchen_station' => 'Handi',
                'image_url' => 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?auto=format&fit=crop&w=400&q=80'
            ],
            [
                'item_id' => 2,
                'category_id' => 1,
                'category_name' => 'Biryani',
                'category_slug' => 'biryani',
                'item_name' => 'Hyderabadi Mutton Dum Biryani',
                'description' => 'Tender fall-off-the-bone mutton marinated in aged herbs and slow-cooked in traditional clay pot dum.',
                'price' => 420.00,
                'stock_quantity' => 30,
                'is_available' => 1,
                'is_veg' => 0,
                'is_bestseller' => 1,
                'rating' => 4.9,
                'rating_count' => 890,
                'prep_time_minutes' => 18,
                'kitchen_station' => 'Handi',
                'image_url' => 'https://images.unsplash.com/photo-1589302168068-964664d93dc0?auto=format&fit=crop&w=400&q=80'
            ],
            [
                'item_id' => 3,
                'category_id' => 1,
                'category_name' => 'Biryani',
                'category_slug' => 'biryani',
                'item_name' => 'Paneer Tikka Dum Biryani',
                'description' => 'Cottage cheese cubes marinated in tandoori spices layered with fragrant aged basmati rice.',
                'price' => 280.00,
                'stock_quantity' => 38,
                'is_available' => 1,
                'is_veg' => 1,
                'is_bestseller' => 0,
                'rating' => 4.6,
                'rating_count' => 620,
                'prep_time_minutes' => 12,
                'kitchen_station' => 'Handi',
                'image_url' => 'https://images.unsplash.com/photo-1642821373181-696a54913e93?auto=format&fit=crop&w=400&q=80'
            ],
            [
                'item_id' => 4,
                'category_id' => 2,
                'category_name' => 'Starters & Kebabs',
                'category_slug' => 'starters',
                'item_name' => 'Chicken 65 Crispy Fry',
                'description' => 'Tender chicken bites tossed with south Indian spices, curry leaves, and green chillies.',
                'price' => 260.00,
                'stock_quantity' => 50,
                'is_available' => 1,
                'is_veg' => 0,
                'is_bestseller' => 1,
                'rating' => 4.7,
                'rating_count' => 780,
                'prep_time_minutes' => 10,
                'kitchen_station' => 'Grills',
                'image_url' => 'https://images.unsplash.com/photo-1599488615731-7e5c2823ff28?auto=format&fit=crop&w=400&q=80'
            ],
            [
                'item_id' => 5,
                'category_id' => 2,
                'category_name' => 'Starters & Kebabs',
                'category_slug' => 'starters',
                'item_name' => 'Tandoori Malai Broccoli',
                'description' => 'Charred fresh broccoli florets marinated in spiced cream, cheese, and roasted cardamom.',
                'price' => 240.00,
                'stock_quantity' => 40,
                'is_available' => 1,
                'is_veg' => 1,
                'is_bestseller' => 0,
                'rating' => 4.5,
                'rating_count' => 410,
                'prep_time_minutes' => 12,
                'kitchen_station' => 'Tandoor',
                'image_url' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=400&q=80'
            ],
            [
                'item_id' => 6,
                'category_id' => 2,
                'category_name' => 'Starters & Kebabs',
                'category_slug' => 'starters',
                'item_name' => 'Apollo Fish Fry',
                'description' => 'Boneless fresh catch fillets seasoned with Andhra special masala and pan fried to golden crisp.',
                'price' => 310.00,
                'stock_quantity' => 25,
                'is_available' => 1,
                'is_veg' => 0,
                'is_bestseller' => 0,
                'rating' => 4.6,
                'rating_count' => 320,
                'prep_time_minutes' => 11,
                'kitchen_station' => 'Grills',
                'image_url' => 'https://images.unsplash.com/photo-1534422298391-e4f8c172dddb?auto=format&fit=crop&w=400&q=80'
            ],
            [
                'item_id' => 7,
                'category_id' => 3,
                'category_name' => 'Main Course & Curries',
                'category_slug' => 'curries',
                'item_name' => 'Butter Chicken Delhi Style',
                'description' => 'Boneless roasted tandoori chicken simmered in rich creamy tomato cashew gravy with fenugreek.',
                'price' => 320.00,
                'stock_quantity' => 50,
                'is_available' => 1,
                'is_veg' => 0,
                'is_bestseller' => 1,
                'rating' => 4.8,
                'rating_count' => 1100,
                'prep_time_minutes' => 15,
                'kitchen_station' => 'Handi',
                'image_url' => 'https://images.unsplash.com/photo-1588166524941-3bf61a9c41db?auto=format&fit=crop&w=400&q=80'
            ],
            [
                'item_id' => 8,
                'category_id' => 3,
                'category_name' => 'Main Course & Curries',
                'category_slug' => 'curries',
                'item_name' => 'Paneer Butter Masala',
                'description' => 'Fresh cottage cheese batons cooked in velvety makhani gravy enriched with pure butter.',
                'price' => 270.00,
                'stock_quantity' => 45,
                'is_available' => 1,
                'is_veg' => 1,
                'is_bestseller' => 1,
                'rating' => 4.7,
                'rating_count' => 950,
                'prep_time_minutes' => 12,
                'kitchen_station' => 'Handi',
                'image_url' => 'https://images.unsplash.com/photo-1631452180519-c014fe946bc7?auto=format&fit=crop&w=400&q=80'
            ],
            [
                'item_id' => 9,
                'category_id' => 3,
                'category_name' => 'Main Course & Curries',
                'category_slug' => 'curries',
                'item_name' => 'Dal Makhani Bukhara',
                'description' => 'Overnight slow-simmered black urad lentils churned with churned cream and clarified butter.',
                'price' => 230.00,
                'stock_quantity' => 60,
                'is_available' => 1,
                'is_veg' => 1,
                'is_bestseller' => 0,
                'rating' => 4.6,
                'rating_count' => 540,
                'prep_time_minutes' => 8,
                'kitchen_station' => 'Pantry',
                'image_url' => 'https://images.unsplash.com/photo-1546833999-b9f581a1996d?auto=format&fit=crop&w=400&q=80'
            ],
            [
                'item_id' => 10,
                'category_id' => 4,
                'category_name' => 'Breads & Rice',
                'category_slug' => 'breads',
                'item_name' => 'Butter Garlic Naan',
                'description' => 'Clay oven leavened bread brushed with melted butter and sprinkled with minced toasted garlic.',
                'price' => 65.00,
                'stock_quantity' => 100,
                'is_available' => 1,
                'is_veg' => 1,
                'is_bestseller' => 1,
                'rating' => 4.7,
                'rating_count' => 1850,
                'prep_time_minutes' => 6,
                'kitchen_station' => 'Tandoor',
                'image_url' => 'https://images.unsplash.com/photo-1601050690597-df0568f70950?auto=format&fit=crop&w=400&q=80'
            ],
            [
                'item_id' => 11,
                'category_id' => 4,
                'category_name' => 'Breads & Rice',
                'category_slug' => 'breads',
                'item_name' => 'Special Boondi Raita',
                'description' => 'Churned chilled yogurt whisked with spiced boondi crisps, roasted cumin, and black salt.',
                'price' => 50.00,
                'stock_quantity' => 90,
                'is_available' => 1,
                'is_veg' => 1,
                'is_bestseller' => 0,
                'rating' => 4.5,
                'rating_count' => 920,
                'prep_time_minutes' => 4,
                'kitchen_station' => 'Pantry',
                'image_url' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=400&q=80'
            ],
            [
                'item_id' => 12,
                'category_id' => 5,
                'category_name' => 'Desserts & Sweets',
                'category_slug' => 'desserts',
                'item_name' => 'Gulab Jamun with Rabdi (2 Pcs)',
                'description' => 'Melt-in-mouth milk solid dumplings dipped in rose saffron syrup served warm with thick rabdi.',
                'price' => 90.00,
                'stock_quantity' => 50,
                'is_available' => 1,
                'is_veg' => 1,
                'is_bestseller' => 1,
                'rating' => 4.8,
                'rating_count' => 760,
                'prep_time_minutes' => 5,
                'kitchen_station' => 'Pantry',
                'image_url' => 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?auto=format&fit=crop&w=400&q=80'
            ],
            [
                'item_id' => 13,
                'category_id' => 6,
                'category_name' => 'Beverages',
                'category_slug' => 'beverages',
                'item_name' => 'Fresh Mint Lime Soda',
                'description' => 'Freshly squeezed lemon juice, crushed mint sprigs, and chilled effervescent soda.',
                'price' => 55.00,
                'stock_quantity' => 80,
                'is_available' => 1,
                'is_veg' => 1,
                'is_bestseller' => 0,
                'rating' => 4.6,
                'rating_count' => 480,
                'prep_time_minutes' => 3,
                'kitchen_station' => 'Pantry',
                'image_url' => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=400&q=80'
            ],
            [
                'item_id' => 14,
                'category_id' => 6,
                'category_name' => 'Beverages',
                'category_slug' => 'beverages',
                'item_name' => 'Royal Mango Lassi',
                'description' => 'Thick creamy churned yogurt blended with Alphonso mango pulp and cardamom.',
                'price' => 85.00,
                'stock_quantity' => 65,
                'is_available' => 1,
                'is_veg' => 1,
                'is_bestseller' => 1,
                'rating' => 4.8,
                'rating_count' => 640,
                'prep_time_minutes' => 4,
                'kitchen_station' => 'Pantry',
                'image_url' => 'https://images.unsplash.com/photo-1528740561666-dc2479dc08ab?auto=format&fit=crop&w=400&q=80'
            ],
        ];
    }
}
