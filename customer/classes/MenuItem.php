<?php
/**
 * =============================================================================
 * MENU ITEM MODEL & APRIORI RECOMMENDATION ENGINE
 * Course: MCSP-232 (MCA Major Project, IGNOU) | Student: Pooja JP
 * =============================================================================
 */

class MenuItem {
    private static array $mockItems = [
        [
            'item_id' => 1, 'category_id' => 1, 'item_name' => 'Meghana Special Chicken Biryani',
            'description' => 'Signature Andhra style spicy boneless chicken biryani served with spicy gravy and thick onion raita.',
            'price' => 330.00, 'stock_quantity' => 50, 'is_available' => 1, 'is_veg' => 0, 'prep_time_minutes' => 18,
            'image_url' => 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=500&auto=format&fit=crop&q=60',
            'rating' => 4.8, 'bestseller' => 1, 'category_name' => 'Biryani Special'
        ],
        [
            'item_id' => 2, 'category_id' => 1, 'item_name' => 'Hyderabadi Paneer Dum Biryani',
            'description' => 'Layers of fragrant basmati rice slow cooked on dum with marinated cottage cheese cubes and saffron.',
            'price' => 280.00, 'stock_quantity' => 40, 'is_available' => 1, 'is_veg' => 1, 'prep_time_minutes' => 16,
            'image_url' => 'https://images.unsplash.com/photo-1642821373181-696a54913e9a?w=500&auto=format&fit=crop&q=60',
            'rating' => 4.6, 'bestseller' => 1, 'category_name' => 'Biryani Special'
        ],
        [
            'item_id' => 3, 'category_id' => 1, 'item_name' => 'Royal Mutton Dum Biryani',
            'description' => 'Tender cuts of fresh goat meat cooked to perfection in rich spices with golden caramelized onions.',
            'price' => 420.00, 'stock_quantity' => 30, 'is_available' => 1, 'is_veg' => 0, 'prep_time_minutes' => 24,
            'image_url' => 'https://images.unsplash.com/photo-1589302168068-964664d93dc0?w=500&auto=format&fit=crop&q=60',
            'rating' => 4.9, 'bestseller' => 0, 'category_name' => 'Biryani Special'
        ],
        [
            'item_id' => 4, 'category_id' => 2, 'item_name' => 'Chicken 65 Andhra Classic',
            'description' => 'Tender chicken florets fried crisp with curry leaves, cracked black pepper, and green chilies.',
            'price' => 260.00, 'stock_quantity' => 45, 'is_available' => 1, 'is_veg' => 0, 'prep_time_minutes' => 12,
            'image_url' => 'https://images.unsplash.com/photo-1610057099443-fde8c4d50f91?w=500&auto=format&fit=crop&q=60',
            'rating' => 4.7, 'bestseller' => 1, 'category_name' => 'Starters & Grills'
        ],
        [
            'item_id' => 5, 'category_id' => 2, 'item_name' => 'Tandoori Paneer Tikka',
            'description' => 'Charcoal grilled cottage cheese chunks infused with hung curd marinade and roasted carom seeds.',
            'price' => 250.00, 'stock_quantity' => 35, 'is_available' => 1, 'is_veg' => 1, 'prep_time_minutes' => 15,
            'image_url' => 'https://images.unsplash.com/photo-1567188040759-fb8a883dc6d8?w=500&auto=format&fit=crop&q=60',
            'rating' => 4.6, 'bestseller' => 0, 'category_name' => 'Starters & Grills'
        ],
        [
            'item_id' => 6, 'category_id' => 2, 'item_name' => 'Crispy Corn Pepper Salt',
            'description' => 'Sweet corn kernels flash-fried with scallions, garlic, crushed black pepper, and spring onions.',
            'price' => 210.00, 'stock_quantity' => 50, 'is_available' => 1, 'is_veg' => 1, 'prep_time_minutes' => 10,
            'image_url' => 'https://images.unsplash.com/photo-1534422298391-e4f8c172dddb?w=500&auto=format&fit=crop&q=60',
            'rating' => 4.5, 'bestseller' => 0, 'category_name' => 'Starters & Grills'
        ],
        [
            'item_id' => 7, 'category_id' => 3, 'item_name' => 'Murgh Makhani (Butter Chicken)',
            'description' => 'Tender roasted chicken tikka pulled into a velvety satin tomato cream gravy finished with fenugreek.',
            'price' => 320.00, 'stock_quantity' => 60, 'is_available' => 1, 'is_veg' => 0, 'prep_time_minutes' => 16,
            'image_url' => 'https://images.unsplash.com/photo-1588166524941-3bf61a9c41db?w=500&auto=format&fit=crop&q=60',
            'rating' => 4.8, 'bestseller' => 1, 'category_name' => 'Main Course Curries'
        ],
        [
            'item_id' => 8, 'category_id' => 3, 'item_name' => 'Paneer Butter Masala',
            'description' => 'Fresh cottage cheese batons cooked in rich cashew-tomato gravy garnished with fresh cream.',
            'price' => 270.00, 'stock_quantity' => 50, 'is_available' => 1, 'is_veg' => 1, 'prep_time_minutes' => 15,
            'image_url' => 'https://images.unsplash.com/photo-1631452180519-c014fe946bc7?w=500&auto=format&fit=crop&q=60',
            'rating' => 4.7, 'bestseller' => 1, 'category_name' => 'Main Course Curries'
        ],
        [
            'item_id' => 9, 'category_id' => 3, 'item_name' => 'Dal Makhani Royal Bukhara',
            'description' => 'Overnight slow simmered whole black lentils with fresh cream, churned butter, and mild aromatic spices.',
            'price' => 230.00, 'stock_quantity' => 55, 'is_available' => 1, 'is_veg' => 1, 'prep_time_minutes' => 14,
            'image_url' => 'https://images.unsplash.com/photo-1546833999-b9f581a1996d?w=500&auto=format&fit=crop&q=60',
            'rating' => 4.8, 'bestseller' => 0, 'category_name' => 'Main Course Curries'
        ],
        [
            'item_id' => 10, 'category_id' => 4, 'item_name' => 'Butter Garlic Naan',
            'description' => 'Clay oven leavened bread slathered with roasted garlic butter and fresh coriander.',
            'price' => 65.00, 'stock_quantity' => 120, 'is_available' => 1, 'is_veg' => 1, 'prep_time_minutes' => 6,
            'image_url' => 'https://images.unsplash.com/photo-1601050690597-df0568f70950?w=500&auto=format&fit=crop&q=60',
            'rating' => 4.9, 'bestseller' => 1, 'category_name' => 'Breads & Rice'
        ],
        [
            'item_id' => 11, 'category_id' => 4, 'item_name' => 'Laccha Paratha',
            'description' => 'Multi-layered crispy flaky whole wheat bread baked in earthen tandoor with clarified ghee.',
            'price' => 55.00, 'stock_quantity' => 100, 'is_available' => 1, 'is_veg' => 1, 'prep_time_minutes' => 7,
            'image_url' => 'https://images.unsplash.com/photo-1626074353765-517a681e40be?w=500&auto=format&fit=crop&q=60',
            'rating' => 4.6, 'bestseller' => 0, 'category_name' => 'Breads & Rice'
        ],
        [
            'item_id' => 12, 'category_id' => 5, 'item_name' => 'Chilli Garlic Chicken Fried Rice',
            'description' => 'Wok tossed aromatic rice with shredded chicken, crunchy bell peppers, and crushed burnt garlic.',
            'price' => 240.00, 'stock_quantity' => 40, 'is_available' => 1, 'is_veg' => 0, 'prep_time_minutes' => 14,
            'image_url' => 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?w=500&auto=format&fit=crop&q=60',
            'rating' => 4.5, 'bestseller' => 0, 'category_name' => 'Chinese & Wok'
        ],
        [
            'item_id' => 13, 'category_id' => 6, 'item_name' => 'Shahi Gulab Jamun (2 Pcs)',
            'description' => 'Hot golden milk-solid dumplings soaked in rose water and cardamom saffron sugar syrup.',
            'price' => 90.00, 'stock_quantity' => 80, 'is_available' => 1, 'is_veg' => 1, 'prep_time_minutes' => 5,
            'image_url' => 'https://images.unsplash.com/photo-1601050690597-df0568f70950?w=500&auto=format&fit=crop&q=60',
            'rating' => 4.9, 'bestseller' => 1, 'category_name' => 'Desserts & Sweets'
        ],
        [
            'item_id' => 14, 'category_id' => 6, 'item_name' => 'Kolkata Rasmalai (2 Pcs)',
            'description' => 'Soft spongy chenna cakes soaked in saffron scented creamy rabri with crushed pistachios.',
            'price' => 110.00, 'stock_quantity' => 60, 'is_available' => 1, 'is_veg' => 1, 'prep_time_minutes' => 5,
            'image_url' => 'https://images.unsplash.com/photo-1589301760014-d929f3979dbc?w=500&auto=format&fit=crop&q=60',
            'rating' => 4.8, 'bestseller' => 0, 'category_name' => 'Desserts & Sweets'
        ],
        [
            'item_id' => 15, 'category_id' => 7, 'item_name' => 'Alphonso Mango Lassi',
            'description' => 'Rich whipped thick yogurt blended with real Ratnagiri Alphonso mango pulp and cardamom.',
            'price' => 100.00, 'stock_quantity' => 75, 'is_available' => 1, 'is_veg' => 1, 'prep_time_minutes' => 4,
            'image_url' => 'https://images.unsplash.com/photo-1546173159-315724a31696?w=500&auto=format&fit=crop&q=60',
            'rating' => 4.9, 'bestseller' => 1, 'category_name' => 'Beverages & Coolers'
        ],
        [
            'item_id' => 16, 'category_id' => 7, 'item_name' => 'Fresh Mint Lime Soda',
            'description' => 'Crisp effervescent soda with freshly squeezed lime, mint leaves, and roasted cumin rock salt.',
            'price' => 70.00, 'stock_quantity' => 90, 'is_available' => 1, 'is_veg' => 1, 'prep_time_minutes' => 3,
            'image_url' => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=500&auto=format&fit=crop&q=60',
            'rating' => 4.7, 'bestseller' => 0, 'category_name' => 'Beverages & Coolers'
        ]
    ];

    /**
     * Get all items with optional filters
     */
    public static function getAll(array $filters = []): array {
        $pdo = Database::getInstance();
        if ($pdo) {
            try {
                $sql = "SELECT m.*, c.category_name, c.category_slug 
                        FROM menu_items m 
                        JOIN categories c ON m.category_id = c.category_id 
                        WHERE m.is_available = 1";
                $params = [];

                if (!empty($filters['category_id'])) {
                    $sql .= " AND m.category_id = ?";
                    $params[] = (int)$filters['category_id'];
                }

                if (!empty($filters['category_slug'])) {
                    $sql .= " AND c.category_slug = ?";
                    $params[] = $filters['category_slug'];
                }

                if (isset($filters['is_veg']) && $filters['is_veg'] !== '') {
                    $sql .= " AND m.is_veg = ?";
                    $params[] = (int)$filters['is_veg'];
                }

                if (!empty($filters['search'])) {
                    $sql .= " AND (m.item_name LIKE ? OR m.description LIKE ?)";
                    $params[] = '%' . $filters['search'] . '%';
                    $params[] = '%' . $filters['search'] . '%';
                }

                if (!empty($filters['sort'])) {
                    switch ($filters['sort']) {
                        case 'price_low':
                            $sql .= " ORDER BY m.price ASC";
                            break;
                        case 'price_high':
                            $sql .= " ORDER BY m.price DESC";
                            break;
                        case 'rating':
                            $sql .= " ORDER BY m.rating DESC";
                            break;
                        default:
                            $sql .= " ORDER BY m.rating DESC, m.item_id ASC";
                    }
                } else {
                    $sql .= " ORDER BY m.rating DESC, m.item_id ASC";
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll();
            } catch (PDOException $e) {
                // fall back
            }
        }

        // Mock filtering
        $results = self::$mockItems;
        if (!empty($filters['category_slug'])) {
            $catMap = ['biryani' => 1, 'starters' => 2, 'main-course' => 3, 'breads-rice' => 4, 'chinese' => 5, 'desserts' => 6, 'beverages' => 7];
            $catId = $catMap[$filters['category_slug']] ?? null;
            if ($catId) {
                $results = array_filter($results, fn($i) => $i['category_id'] == $catId);
            }
        }
        if (isset($filters['is_veg']) && $filters['is_veg'] !== '') {
            $results = array_filter($results, fn($i) => $i['is_veg'] == (int)$filters['is_veg']);
        }
        if (!empty($filters['search'])) {
            $q = strtolower($filters['search']);
            $results = array_filter($results, fn($i) => strpos(strtolower($i['item_name']), $q) !== false || strpos(strtolower($i['description']), $q) !== false);
        }
        return array_values($results);
    }

    /**
     * Get item by ID
     */
    public static function getById(int $itemId): ?array {
        $pdo = Database::getInstance();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT m.*, c.category_name, c.category_slug 
                                       FROM menu_items m 
                                       JOIN categories c ON m.category_id = c.category_id 
                                       WHERE m.item_id = ? LIMIT 1");
                $stmt->execute([$itemId]);
                $item = $stmt->fetch();
                return $item ?: null;
            } catch (PDOException $e) {
                // fallback
            }
        }
        foreach (self::$mockItems as $i) {
            if ($i['item_id'] === $itemId) return $i;
        }
        return null;
    }

    /**
     * APRIORI MARKET BASKET RECOMMENDATION ALGORITHM
     * Evaluates co-occurrence transaction patterns from historical orders
     * Returns top complement dishes (e.g. Biryani -> Butter Naan, Chicken 65, Raita, Gulab Jamun)
     */
    public static function getAprioriRecommendations(array $currentCartItemIds, int $limit = 4): array {
        if (empty($currentCartItemIds)) {
            // Default cross-sell bestsellers
            return array_slice(array_filter(self::getAll(), fn($i) => in_array($i['item_id'], [4, 10, 13, 15])), 0, $limit);
        }

        $pdo = Database::getInstance();
        if ($pdo) {
            try {
                $inCartPlaceholders = implode(',', array_fill(0, count($currentCartItemIds), '?'));
                // Market Basket Association SQL:
                // Find items that frequently co-occur in past orders containing the current cart items
                $sql = "SELECT oi2.item_id, COUNT(DISTINCT oi2.order_id) as support_count
                        FROM order_items oi1
                        JOIN order_items oi2 ON oi1.order_id = oi2.order_id
                        WHERE oi1.item_id IN ($inCartPlaceholders)
                          AND oi2.item_id NOT IN ($inCartPlaceholders)
                        GROUP BY oi2.item_id
                        ORDER BY support_count DESC, oi2.item_id ASC
                        LIMIT ?";

                $params = array_merge($currentCartItemIds, $currentCartItemIds, [$limit]);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $recIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($recIds)) {
                    $recItems = [];
                    foreach ($recIds as $id) {
                        $item = self::getById((int)$id);
                        if ($item) $recItems[] = $item;
                    }
                    if (!empty($recItems)) return $recItems;
                }
            } catch (PDOException $e) {
                // fall back to heuristic association
            }
        }

        // Heuristic Association Rules Map (Apriori fallback):
        // Main dish -> Naan/Starters/Desserts/Coolers
        $associationRules = [
            1 => [4, 10, 13, 15],  // Chicken Biryani -> Chicken 65, Butter Naan, Gulab Jamun, Mango Lassi
            2 => [5, 10, 13, 15],  // Paneer Biryani -> Paneer Tikka, Butter Naan, Gulab Jamun, Mango Lassi
            3 => [4, 10, 15, 16],  // Mutton Biryani -> Chicken 65, Butter Naan, Mango Lassi, Mint Lime Soda
            7 => [10, 11, 13, 15], // Butter Chicken -> Butter Garlic Naan, Paratha, Gulab Jamun, Mango Lassi
            8 => [10, 11, 14, 15], // Paneer Butter Masala -> Butter Garlic Naan, Paratha, Rasmalai, Mango Lassi
            9 => [10, 11, 13, 15], // Dal Makhani -> Butter Garlic Naan, Paratha, Gulab Jamun, Mango Lassi
            12 => [4, 16, 15, 13]  // Fried Rice -> Chicken 65, Fresh Lime Soda, Mango Lassi, Gulab Jamun
        ];

        $candidateIds = [];
        foreach ($currentCartItemIds as $cartItemId) {
            if (isset($associationRules[$cartItemId])) {
                foreach ($associationRules[$cartItemId] as $cand) {
                    if (!in_array($cand, $currentCartItemIds) && !in_array($cand, $candidateIds)) {
                        $candidateIds[] = $cand;
                    }
                }
            }
        }

        // If no explicit rules match, pick popular cross-sells
        if (empty($candidateIds)) {
            $fallbackIds = [10, 13, 15, 4];
            foreach ($fallbackIds as $fid) {
                if (!in_array($fid, $currentCartItemIds)) {
                    $candidateIds[] = $fid;
                }
            }
        }

        $results = [];
        foreach (array_slice($candidateIds, 0, $limit) as $id) {
            $item = self::getById($id);
            if ($item) $results[] = $item;
        }
        return $results;
    }
}
