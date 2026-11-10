<?php
declare(strict_types=1);
/**
 * Catalog Domain Service
 * Encapsulates storefront catalog queries, mood curation, category governance,
 * dish inventory management, soft-delete archival, and customization options.
 * Zippy - Online Food Ordering Platform
 */

class CatalogService
{
    /** @var array<int, string> */
    public const KITCHEN_STATIONS = [
        'Main Kitchen',
        'Tandoor & Grills',
        'Curry & Biryani',
        'Salad & Cold Bar',
        'Fryer & Snacks',
        'Beverage Bar',
        'Dessert Bar',
    ];

    /**
     * Build shared header dish image lookup map and active storefront catalog version hash.
     *
     * @return array{dish_image_map: array<int|string, string>, catalog_version: string}
     */
    public static function getHeaderCatalogState(): array
    {
        $catalogRows = dbFetchAll("SELECT `item_id`, `image_url` FROM `menu_items` WHERE `is_active` = 1");
        $dishImageMap = [];
        foreach ($catalogRows as $cr) {
            $dishImageMap[$cr['item_id']] = getDishImageUrl($cr['image_url'] ?? '');
        }

        $catVerRows = dbFetchAll("
            SELECT `category_id`, `category_name`, `display_order`
            FROM `categories`
            WHERE `is_active` = 1
            ORDER BY `category_id` ASC
        ");
        $dishVerRows = dbFetchAll("
            SELECT m.item_id, m.category_id, m.item_name, m.price
            FROM `menu_items` m
            JOIN `categories` c ON m.category_id = c.category_id
            WHERE c.is_active = 1 AND m.is_active = 1 AND m.is_available = 1
            ORDER BY m.item_id ASC
        ");

        $catFingerprintData = array_map(static fn(array $c): array => [
            (int)$c['category_id'],
            $c['category_name'],
            (int)$c['display_order'],
        ], $catVerRows);

        $dishFingerprintData = array_map(static fn(array $d): array => [
            (int)$d['item_id'],
            (int)$d['category_id'],
            $d['item_name'],
            (float)$d['price'],
        ], $dishVerRows);

        $initialCatalogVer = md5(
            md5((string)json_encode($catFingerprintData)) . '_' . md5((string)json_encode($dishFingerprintData))
        );

        return [
            'dish_image_map' => $dishImageMap,
            'catalog_version' => $initialCatalogVer,
        ];
    }

    /**
     * Fetch data required by the customer storefront home page.
     *
     * @param int|null $customerUserId Logged-in customer user ID (or null for guest)
     * @return array<string, mixed>
     */
    public static function getStorefrontHomeData(?int $customerUserId = null): array
    {
        $categories = dbFetchAll("
            SELECT c.*, COUNT(m.item_id) AS total_dishes
            FROM `categories` c
            LEFT JOIN `menu_items` m ON c.category_id = m.category_id AND m.is_active = 1 AND m.is_available = 1
            WHERE c.is_active = 1
            GROUP BY c.category_id
            ORDER BY c.display_order ASC
        ");

        $menuItems = dbFetchAll("
            SELECT m.*, c.category_name, c.category_slug
            FROM `menu_items` m
            JOIN `categories` c ON m.category_id = c.category_id
            WHERE m.is_active = 1 AND m.is_available = 1 AND c.is_active = 1
            ORDER BY m.stock_quantity DESC, m.item_id ASC
        ");

        $customSummaryMap = getAllDishesCustomizationCounts();
        $foodMoods = getFoodMoods();
        $aprioriCombos = class_exists('AprioriEngine') ? AprioriEngine::getRecommendations([], 4) : [];

        $activeOrder = null;
        $statusBadge = null;
        $previousMeal = null;
        $favoriteIds = [];
        $userDietaryPrefs = [];

        if ($customerUserId !== null && $customerUserId > 0) {
            $activeOrder = dbFetchOne("
                SELECT o.*, d.delivery_partner_name, d.delivery_partner_phone
                FROM `orders` o
                LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
                WHERE o.user_id = :uid
                  AND o.order_status IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY')
                ORDER BY o.order_id DESC
                LIMIT 1
            ", [':uid' => $customerUserId]);

            if ($activeOrder) {
                $statusBadge = match ($activeOrder['order_status']) {
                    'PLACED' => ['badge' => 'bg-secondary', 'icon' => 'bi-hourglass-split', 'text' => 'Order Received', 'desc' => 'Waiting for kitchen team confirmation'],
                    'CONFIRMED' => ['badge' => 'bg-info text-dark', 'icon' => 'bi-check2-circle', 'text' => 'Order Confirmed', 'desc' => 'Kitchen has queued your order for cooking'],
                    'COOKING' => ['badge' => 'bg-warning text-dark', 'icon' => 'fa-solid fa-fire-burner', 'text' => 'Cooking on Fire', 'desc' => 'Chef is preparing your meal with fresh ingredients'],
                    'READY' => ['badge' => 'bg-primary', 'icon' => 'bi-box-seam', 'text' => 'Ready for Pickup', 'desc' => 'Meal packed hot and waiting for delivery partner pickup'],
                    'OUT_FOR_DELIVERY' => ['badge' => 'bg-success', 'icon' => 'bi-bicycle', 'text' => 'Out for Delivery', 'desc' => 'Delivery partner is en route to your doorstep'],
                    default => ['badge' => 'bg-success', 'icon' => 'bi-bag-check', 'text' => 'In Progress', 'desc' => 'Your order is being handled'],
                };
            }

            $previousMeal = getCustomerPreviousMeal($customerUserId);
            $favoriteIds = self::getUserFavoriteItemIds($customerUserId);
            $userDietaryPrefs = getUserDietaryPreferences($customerUserId);
        }

        return [
            'categories' => $categories,
            'menuItems' => $menuItems,
            'customSummaryMap' => $customSummaryMap,
            'foodMoods' => $foodMoods,
            'aprioriCombos' => $aprioriCombos,
            'activeOrder' => $activeOrder,
            'statusBadge' => $statusBadge,
            'previousMeal' => $previousMeal,
            'favoriteIds' => $favoriteIds,
            'userDietaryPrefs' => $userDietaryPrefs,
        ];
    }

    /**
     * Fetch user's bookmarked/favorite menu item IDs.
     *
     * @return array<int, int>
     */
    public static function getUserFavoriteItemIds(?int $userId): array
    {
        if ($userId === null || $userId <= 0) {
            return [];
        }
        try {
            $rows = dbFetchAll("SELECT `item_id` FROM `user_favorites` WHERE `user_id` = :uid", [':uid' => $userId]);
            return array_map(static fn(array $r): int => (int)$r['item_id'], $rows);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Toggle a menu item in customer's favorites (`user_favorites`).
     *
     * @return array{ok: bool, favorited: bool, count: int, message: string}
     */
    public static function toggleUserFavorite(int $userId, int $itemId): array
    {
        if ($userId <= 0 || $itemId <= 0) {
            return ['ok' => false, 'favorited' => false, 'count' => 0, 'message' => 'Invalid user or dish.'];
        }

        try {
            return dbTransactional(static function () use ($userId, $itemId): array {
                $exists = dbFetchOne(
                    "SELECT `favorite_id` FROM `user_favorites` WHERE `user_id` = :uid AND `item_id` = :iid FOR UPDATE",
                    [':uid' => $userId, ':iid' => $itemId]
                );
                if ($exists) {
                    dbQuery("DELETE FROM `user_favorites` WHERE `favorite_id` = :fid", [':fid' => (int)$exists['favorite_id']]);
                    $favorited = false;
                    $msg = 'Removed from your Favorites.';
                } else {
                    dbQuery("INSERT INTO `user_favorites` (`user_id`, `item_id`) VALUES (:uid, :iid)", [':uid' => $userId, ':iid' => $itemId]);
                    $favorited = true;
                    $msg = 'Saved to your Favorites!';
                }
                $cnt = (int)dbFetchVal("SELECT COUNT(*) FROM `user_favorites` WHERE `user_id` = :uid", [':uid' => $userId]);
                return ['ok' => true, 'favorited' => $favorited, 'count' => $cnt, 'message' => $msg];
            });
        } catch (Throwable $e) {
            error_log("toggleUserFavorite error: " . $e->getMessage());
            return ['ok' => false, 'favorited' => false, 'count' => 0, 'message' => 'Unable to update favorites right now.'];
        }
    }

    /**
     * Fetch grouped menu catalog and Apriori cross-sell items for restaurant menu page.
     *
     * @return array{aprioriRecs: array<int, array<string, mixed>>, categories: array<int, array<string, mixed>>, itemsByCategory: array<int, array<int, array<string, mixed>>>, customSummaryMap: array<int, array{group_count: int, option_count: int}>, favoriteIds: array<int, int>}
     */
    public static function getRestaurantCatalog(?int $customerUserId = null): array
    {
        $aprioriRecs = class_exists('AprioriEngine') ? AprioriEngine::getRecommendations([1], 4) : [];

        $categories = dbFetchAll("
            SELECT c.*, COUNT(m.item_id) AS total_items
            FROM `categories` c
            JOIN `menu_items` m ON c.category_id = m.category_id
            WHERE c.is_active = 1 AND m.is_active = 1 AND m.is_available = 1
            GROUP BY c.category_id
            ORDER BY c.display_order ASC
        ");

        $allItems = dbFetchAll("
            SELECT m.*
            FROM `menu_items` m
            JOIN `categories` c ON m.category_id = c.category_id
            WHERE c.is_active = 1 AND m.is_active = 1 AND m.is_available = 1
            ORDER BY m.price ASC
        ");

        $itemsByCategory = [];
        foreach ($categories as $cat) {
            $itemsByCategory[(int)$cat['category_id']] = [];
        }
        foreach ($allItems as $item) {
            $cid = (int)$item['category_id'];
            if (isset($itemsByCategory[$cid])) {
                $itemsByCategory[$cid][] = $item;
            }
        }

        return [
            'aprioriRecs' => $aprioriRecs,
            'categories' => $categories,
            'itemsByCategory' => $itemsByCategory,
            'customSummaryMap' => getAllDishesCustomizationCounts(),
            'favoriteIds' => self::getUserFavoriteItemIds($customerUserId),
        ];
    }

    /**
     * Search active storefront dishes by text, category slug, diet flags, mood, allergen exclusions, and sort order.
     *
     * @return array{dishes: array<int, array<string, mixed>>, categories: array<int, array<string, mixed>>, foodMoods: array<string, array<string, mixed>>, customSummaryMap: array<int, array{group_count: int, option_count: int}>, favoriteIds: array<int, int>}
     */
    public static function searchDishes(
        string $query,
        string $categorySlug,
        bool $vegOnly,
        bool $healthyOnly,
        string $moodFilter,
        string $sort,
        string $allergenFree = '',
        bool $highProtein = false,
        ?int $customerUserId = null
    ): array
    {
        $conditions = ["m.is_active = 1", "m.is_available = 1", "c.is_active = 1"];
        $params = [];

        if ($query !== '') {
            $qLike = '%' . strtolower($query) . '%';
            $conditions[] = "(LOWER(m.item_name) LIKE :q_name OR LOWER(m.description) LIKE :q_desc OR LOWER(c.category_name) LIKE :q_cat)";
            $params[':q_name'] = $qLike;
            $params[':q_desc'] = $qLike;
            $params[':q_cat'] = $qLike;
        }

        if ($categorySlug !== '') {
            $conditions[] = "c.category_slug = :slug";
            $params[':slug'] = $categorySlug;
        }

        if ($vegOnly) {
            $conditions[] = "m.is_veg = 1";
        }

        if ($healthyOnly) {
            $conditions[] = "(c.category_slug = 'healthy' OR m.is_healthy = 1 OR LOWER(c.category_name) LIKE '%healthy%' OR LOWER(m.item_name) LIKE '%salad%' OR LOWER(m.item_name) LIKE '%quinoa%' OR LOWER(m.item_name) LIKE '%detox%')";
        }

        if ($highProtein) {
            $conditions[] = "COALESCE(m.protein_g, 0) >= 20";
        }

        if ($allergenFree !== '') {
            $allowedAllergens = array_keys(getDishAllergenOptions());
            $requestedExclusions = array_values(array_intersect($allowedAllergens, array_map('trim', explode(',', strtolower($allergenFree)))));
            foreach ($requestedExclusions as $idx => $alg) {
                $pKey = ":alg_excl_{$idx}";
                $conditions[] = "(m.allergens IS NULL OR m.allergens = '' OR FIND_IN_SET({$pKey}, LOWER(m.allergens)) = 0)";
                $params[$pKey] = $alg;
            }
        }

        if ($moodFilter !== '' && $moodFilter !== 'all') {
            $conditions[] = "(FIND_IN_SET(:mood, m.mood) > 0 OR m.mood LIKE :mood_like)";
            $params[':mood'] = $moodFilter;
            $params[':mood_like'] = '%' . $moodFilter . '%';
        }

        $orderBy = match ($sort) {
            'price_asc' => "m.price ASC",
            'price_desc' => "m.price DESC",
            'prep_time' => "m.prep_time_minutes ASC",
            'protein_desc' => "COALESCE(m.protein_g, 0) DESC",
            default => "m.stock_quantity DESC, m.item_id ASC",
        };

        $whereSql = implode(" AND ", $conditions);
        $dishes = dbFetchAll("
            SELECT m.*, c.category_name, c.category_slug
            FROM `menu_items` m
            JOIN `categories` c ON m.category_id = c.category_id
            WHERE {$whereSql}
            ORDER BY {$orderBy}
        ", $params);

        $categories = dbFetchAll("SELECT * FROM `categories` WHERE `is_active` = 1 ORDER BY `display_order` ASC");

        return [
            'dishes' => $dishes,
            'categories' => $categories,
            'foodMoods' => getFoodMoods(),
            'customSummaryMap' => getAllDishesCustomizationCounts(),
            'favoriteIds' => self::getUserFavoriteItemIds($customerUserId),
        ];
    }

    /**
     * Create a menu category in Admin.
     *
     * @param array<string, mixed> $post
     * @return array{type: string, message: string}
     */
    public static function createCategory(array $post): array
    {
        $name = cleanInput((string)($post['category_name'] ?? ''));
        $rawSlug = cleanInput((string)($post['category_slug'] ?? ''));
        $slug = $rawSlug ?: strtolower((string)preg_replace('/[^A-Za-z0-9-]+/', '-', $name));
        $desc = cleanInput((string)($post['category_description'] ?? ''));
        $image = cleanInput((string)($post['image_url'] ?? ''));
        $order = isset($post['display_order']) && $post['display_order'] !== '' ? (int)$post['display_order'] : null;
        $isActive = !empty($post['is_active']) ? 1 : 0;

        if ($order === null || $order <= 0) {
            $maxOrd = (int)dbFetchVal("SELECT MAX(`display_order`) FROM `categories`");
            $order = $maxOrd + 1;
        }

        $upload = handleCategoryPhotoUpload('category_image');
        if ($upload !== null) {
            if (!empty($upload['error'])) {
                return ['type' => 'danger', 'message' => 'Photo upload failed: ' . $upload['error']];
            }
            if (!empty($upload['url'])) {
                $image = $upload['url'];
            }
        }

        if ($image === '') {
            $image = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300';
        }

        if ($name === '') {
            return ['type' => 'danger', 'message' => 'Category name is required.'];
        }

        $existing = (int)dbFetchVal(
            "SELECT COUNT(*) FROM `categories` WHERE `category_name` = :name OR `category_slug` = :slug",
            [':name' => $name, ':slug' => $slug]
        );

        if ($existing > 0) {
            return ['type' => 'warning', 'message' => "A category with the name '{$name}' or slug '{$slug}' already exists."];
        }

        dbQuery("
            INSERT INTO `categories` (`category_name`, `category_slug`, `category_description`, `image_url`, `display_order`, `is_active`)
            VALUES (:name, :slug, :desc, :img, :ord, :act)
        ", [
            ':name' => $name,
            ':slug' => $slug,
            ':desc' => $desc,
            ':img' => $image,
            ':ord' => $order,
            ':act' => $isActive,
        ]);

        return ['type' => 'success', 'message' => "Category '{$name}' created successfully with display order #{$order}."];
    }

    /**
     * Update an existing menu category.
     *
     * @param array<string, mixed> $post
     * @return array{type: string, message: string}
     */
    public static function updateCategory(array $post): array
    {
        $cid = (int)($post['category_id'] ?? 0);
        $name = cleanInput((string)($post['category_name'] ?? ''));
        $slug = cleanInput((string)($post['category_slug'] ?? ''));
        $desc = cleanInput((string)($post['category_description'] ?? ''));
        $image = cleanInput((string)($post['image_url'] ?? ''));
        $order = (int)($post['display_order'] ?? 0);
        $isActive = !empty($post['is_active']) ? 1 : 0;

        $upload = handleCategoryPhotoUpload('category_image');
        if ($upload !== null) {
            if (!empty($upload['error'])) {
                return ['type' => 'danger', 'message' => 'Photo upload failed: ' . $upload['error']];
            }
            if (!empty($upload['url'])) {
                $image = $upload['url'];
            }
        }

        if ($image === '') {
            $existing = dbFetchVal("SELECT `image_url` FROM `categories` WHERE `category_id` = :id", [':id' => $cid]);
            if ($existing) {
                $image = (string)$existing;
            }
        }

        dbQuery("
            UPDATE `categories`
            SET `category_name` = :name, `category_slug` = :slug, `category_description` = :desc,
                `image_url` = :img, `display_order` = :ord, `is_active` = :act
            WHERE `category_id` = :id
        ", [
            ':name' => $name,
            ':slug' => $slug,
            ':desc' => $desc,
            ':img' => $image,
            ':ord' => $order,
            ':act' => $isActive,
            ':id' => $cid,
        ]);

        return ['type' => 'success', 'message' => "Category '{$name}' updated successfully."];
    }

    /**
     * Toggle category active status.
     *
     * @return array{type: string, message: string}|null
     */
    public static function toggleCategoryStatus(int $cid): ?array
    {
        $cat = dbFetchOne("SELECT `category_name`, `is_active` FROM `categories` WHERE `category_id` = :id", [':id' => $cid]);
        if (!$cat) {
            return null;
        }

        $newStatus = $cat['is_active'] ? 0 : 1;
        dbQuery("UPDATE `categories` SET `is_active` = :status WHERE `category_id` = :id", [
            ':status' => $newStatus,
            ':id' => $cid,
        ]);
        $statusLabel = $newStatus ? 'now visible live on storefront' : 'now hidden from storefront';

        return ['type' => 'success', 'message' => "Category '{$cat['category_name']}' is {$statusLabel}."];
    }

    /**
     * Adjust category display order up/down or explicitly.
     *
     * @return array{type: string, message: string}
     */
    public static function updateCategoryOrder(int $cid, string $direction, ?int $explicitOrder = null): array
    {
        $currentOrder = (int)dbFetchVal("SELECT `display_order` FROM `categories` WHERE `category_id` = :id", [':id' => $cid]);

        if ($direction === 'up') {
            $newOrder = max(1, $currentOrder - 1);
        } elseif ($direction === 'down') {
            $newOrder = $currentOrder + 1;
        } else {
            $newOrder = $explicitOrder ?? $currentOrder;
        }

        dbQuery("UPDATE `categories` SET `display_order` = :ord WHERE `category_id` = :id", [
            ':ord' => $newOrder,
            ':id' => $cid,
        ]);

        return ['type' => 'success', 'message' => 'Display priority updated.'];
    }

    /**
     * Soft-archive a category (is_active = 0) when it has no active menu items.
     *
     * @return array{type: string, message: string}
     */
    public static function softDeleteCategory(int $cid): array
    {
        $dishCount = (int)dbFetchVal(
            "SELECT COUNT(*) FROM `menu_items` WHERE `category_id` = :id AND `is_active` = 1",
            [':id' => $cid]
        );
        if ($dishCount > 0) {
            return [
                'type' => 'danger',
                'message' => "Cannot archive category because it contains {$dishCount} active menu dishes. Reassign or archive dishes first.",
            ];
        }

        $catName = (string)(dbFetchVal("SELECT `category_name` FROM `categories` WHERE `category_id` = :id", [':id' => $cid]) ?? 'Category');
        dbQuery("UPDATE `categories` SET `is_active` = 0 WHERE `category_id` = :id", [':id' => $cid]);

        return ['type' => 'success', 'message' => "Category '{$catName}' archived safely (hidden from storefront)."];
    }

    /**
     * Fetch full Admin Categories overview dataset + live sync fingerprint hashes.
     *
     * @return array<string, mixed>
     */
    public static function getAdminCategoriesData(): array
    {
        $categories = dbFetchAll("
            SELECT c.*, COUNT(m.item_id) AS total_dishes
            FROM `categories` c
            LEFT JOIN `menu_items` m ON c.category_id = m.category_id AND m.is_active = 1
            GROUP BY c.category_id
            ORDER BY c.display_order ASC, c.category_id ASC
        ");

        $totalCategories = count($categories);
        $activeCount = 0;
        $hiddenCount = 0;
        $emptyCount = 0;
        $totalDishesMapped = 0;

        foreach ($categories as $cat) {
            if (!empty($cat['is_active'])) {
                $activeCount++;
            } else {
                $hiddenCount++;
            }
            if ((int)$cat['total_dishes'] === 0) {
                $emptyCount++;
            }
            $totalDishesMapped += (int)$cat['total_dishes'];
        }

        $maxOrder = !empty($categories) ? (int)max(array_column($categories, 'display_order')) : 0;
        $suggestedNextOrder = $maxOrder + 1;

        $catFingerprintData = array_map(static fn(array $c): array => [
            (int)$c['category_id'],
            $c['category_name'],
            (int)$c['display_order'],
            (int)$c['is_active'],
            (int)$c['total_dishes'],
        ], $categories);

        $dishFingerprintData = dbFetchAll("
            SELECT `item_id`, `category_id`, `item_name`, `price`, `stock_quantity`, `is_available`
            FROM `menu_items`
            WHERE `is_active` = 1
            ORDER BY `item_id` ASC
        ");

        $initialCatVer = md5((string)json_encode($catFingerprintData));
        $initialDishVer = md5((string)json_encode($dishFingerprintData));

        return [
            'categories' => $categories,
            'totalCategories' => $totalCategories,
            'activeCount' => $activeCount,
            'hiddenCount' => $hiddenCount,
            'emptyCount' => $emptyCount,
            'totalDishesMapped' => $totalDishesMapped,
            'suggestedNextOrder' => $suggestedNextOrder,
            'initialCatVer' => $initialCatVer,
            'initialDishVer' => $initialDishVer,
            'initialCombinedVer' => md5($initialCatVer . '_' . $initialDishVer),
        ];
    }

    /**
     * Fetch active menu items for CSV export in Admin Dishes.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getDishesForExport(): array
    {
        return dbFetchAll("
            SELECT m.*, c.category_name
            FROM `menu_items` m
            JOIN `categories` c ON m.category_id = c.category_id
            WHERE m.is_active = 1
            ORDER BY m.category_id ASC, m.item_name ASC
        ");
    }

    /**
     * Create a new dish in Admin.
     *
     * @param array<string, mixed> $post
     * @return array{type: string, message: string, should_redirect: bool}
     */
    public static function createDish(array $post): array
    {
        $name = cleanInput((string)($post['item_name'] ?? ''));
        $catId = (int)($post['category_id'] ?? 0);
        $station = cleanInput((string)($post['kitchen_station'] ?? 'Main Kitchen')) ?: 'Main Kitchen';
        $price = (float)($post['price'] ?? 0);
        $stock = (int)($post['stock_quantity'] ?? 0);
        $prep = (int)($post['prep_time_minutes'] ?? 20);
        $isVeg = !empty($post['is_veg']) ? 1 : 0;
        $isHealthy = !empty($post['is_healthy']) ? 1 : 0;
        $mood = cleanInput((string)($post['mood'] ?? 'comfort'));
        $calories = isset($post['calories_kcal']) && $post['calories_kcal'] !== '' ? max(0, (int)$post['calories_kcal']) : null;
        $protein = isset($post['protein_g']) && $post['protein_g'] !== '' ? max(0.0, (float)$post['protein_g']) : null;
        $carbs = isset($post['carbs_g']) && $post['carbs_g'] !== '' ? max(0.0, (float)$post['carbs_g']) : null;
        $fat = isset($post['fat_g']) && $post['fat_g'] !== '' ? max(0.0, (float)$post['fat_g']) : null;
        $rawAllergens = $post['allergens'] ?? [];
        $allergenList = is_array($rawAllergens) ? $rawAllergens : explode(',', (string)$rawAllergens);
        $allowedAlg = array_keys(getDishAllergenOptions());
        $cleanAllergens = implode(',', array_values(array_intersect($allowedAlg, array_map('trim', array_map('strtolower', $allergenList)))));
        $allergensVal = $cleanAllergens !== '' ? $cleanAllergens : null;
        $spiceLevel = strtoupper(cleanInput((string)($post['spice_level'] ?? 'MEDIUM')));
        if (!in_array($spiceLevel, ['MILD', 'MEDIUM', 'SPICY', 'EXTRA_SPICY'], true)) {
            $spiceLevel = 'MEDIUM';
        }
        $image = cleanInput((string)($post['image_url'] ?? ''));
        $desc = cleanInput((string)($post['description'] ?? ''));

        $upload = handleFoodPhotoUpload('dish_image');
        if ($upload !== null) {
            if (!empty($upload['error'])) {
                return ['type' => 'danger', 'message' => 'Photo upload failed: ' . $upload['error'], 'should_redirect' => true];
            }
            if (!empty($upload['url'])) {
                $image = $upload['url'];
            }
        }

        if ($image === '') {
            $image = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300';
        }

        if ($name === '' || $price <= 0) {
            return ['type' => 'danger', 'message' => 'Dish name and a valid positive price are required.', 'should_redirect' => false];
        }

        dbQuery("
            INSERT INTO `menu_items` (`category_id`, `item_name`, `description`, `price`, `stock_quantity`, `is_available`, `is_active`, `is_veg`, `is_healthy`, `mood`, `prep_time_minutes`, `kitchen_station`, `calories_kcal`, `protein_g`, `carbs_g`, `fat_g`, `allergens`, `spice_level`, `image_url`)
            VALUES (:cid, :name, :desc, :price, :stock, 1, 1, :veg, :healthy, :mood, :prep, :station, :cal, :pro, :carb, :fat, :alg, :sp, :img)
        ", [
            ':cid' => $catId,
            ':name' => $name,
            ':desc' => $desc,
            ':price' => $price,
            ':stock' => $stock,
            ':veg' => $isVeg,
            ':healthy' => $isHealthy,
            ':mood' => $mood,
            ':prep' => $prep,
            ':station' => $station,
            ':cal' => $calories,
            ':pro' => $protein,
            ':carb' => $carbs,
            ':fat' => $fat,
            ':alg' => $allergensVal,
            ':sp' => $spiceLevel,
            ':img' => $image,
        ]);

        return ['type' => 'success', 'message' => "Food item '{$name}' added to catalog under {$station}.", 'should_redirect' => true];
    }

    /**
     * Update an existing dish in Admin.
     *
     * @param array<string, mixed> $post
     * @return array{type: string, message: string}
     */
    public static function updateDish(array $post): array
    {
        $itemId = (int)($post['item_id'] ?? 0);
        $name = cleanInput((string)($post['item_name'] ?? ''));
        $catId = (int)($post['category_id'] ?? 0);
        $station = cleanInput((string)($post['kitchen_station'] ?? 'Main Kitchen')) ?: 'Main Kitchen';
        $price = (float)($post['price'] ?? 0);
        $stock = (int)($post['stock_quantity'] ?? 0);
        $prep = (int)($post['prep_time_minutes'] ?? 20);
        $isVeg = !empty($post['is_veg']) ? 1 : 0;
        $isHealthy = !empty($post['is_healthy']) ? 1 : 0;
        $mood = cleanInput((string)($post['mood'] ?? 'comfort'));
        $isAvailable = !empty($post['is_available']) ? 1 : 0;
        $calories = isset($post['calories_kcal']) && $post['calories_kcal'] !== '' ? max(0, (int)$post['calories_kcal']) : null;
        $protein = isset($post['protein_g']) && $post['protein_g'] !== '' ? max(0.0, (float)$post['protein_g']) : null;
        $carbs = isset($post['carbs_g']) && $post['carbs_g'] !== '' ? max(0.0, (float)$post['carbs_g']) : null;
        $fat = isset($post['fat_g']) && $post['fat_g'] !== '' ? max(0.0, (float)$post['fat_g']) : null;
        $rawAllergens = $post['allergens'] ?? [];
        $allergenList = is_array($rawAllergens) ? $rawAllergens : explode(',', (string)$rawAllergens);
        $allowedAlg = array_keys(getDishAllergenOptions());
        $cleanAllergens = implode(',', array_values(array_intersect($allowedAlg, array_map('trim', array_map('strtolower', $allergenList)))));
        $allergensVal = $cleanAllergens !== '' ? $cleanAllergens : null;
        $spiceLevel = strtoupper(cleanInput((string)($post['spice_level'] ?? 'MEDIUM')));
        if (!in_array($spiceLevel, ['MILD', 'MEDIUM', 'SPICY', 'EXTRA_SPICY'], true)) {
            $spiceLevel = 'MEDIUM';
        }
        $image = cleanInput((string)($post['image_url'] ?? ''));
        $desc = cleanInput((string)($post['description'] ?? ''));

        $upload = handleFoodPhotoUpload('dish_image');
        if ($upload !== null) {
            if (!empty($upload['error'])) {
                return ['type' => 'danger', 'message' => 'Photo upload failed: ' . $upload['error']];
            }
            if (!empty($upload['url'])) {
                $image = $upload['url'];
            }
        }

        if ($image === '') {
            $existing = dbFetchVal("SELECT `image_url` FROM `menu_items` WHERE `item_id` = :id", [':id' => $itemId]);
            if ($existing) {
                $image = (string)$existing;
            }
        }

        dbQuery("
            UPDATE `menu_items`
            SET `category_id` = :cid, `item_name` = :name, `description` = :desc,
                `price` = :price, `stock_quantity` = :stock, `is_available` = :avail,
                `is_veg` = :veg, `is_healthy` = :healthy, `mood` = :mood, `prep_time_minutes` = :prep,
                `kitchen_station` = :station, `calories_kcal` = :cal, `protein_g` = :pro,
                `carbs_g` = :carb, `fat_g` = :fat, `allergens` = :alg, `spice_level` = :sp,
                `image_url` = :img
            WHERE `item_id` = :id AND `is_active` = 1
        ", [
            ':cid' => $catId,
            ':name' => $name,
            ':desc' => $desc,
            ':price' => $price,
            ':stock' => $stock,
            ':avail' => $isAvailable,
            ':veg' => $isVeg,
            ':healthy' => $isHealthy,
            ':mood' => $mood,
            ':prep' => $prep,
            ':station' => $station,
            ':cal' => $calories,
            ':pro' => $protein,
            ':carb' => $carbs,
            ':fat' => $fat,
            ':alg' => $allergensVal,
            ':sp' => $spiceLevel,
            ':img' => $image,
            ':id' => $itemId,
        ]);

        return ['type' => 'success', 'message' => "Food item '{$name}' updated successfully."];
    }

    /**
     * Increment or decrement dish stock quantity atomically.
     *
     * @return array{type: string, message: string}
     */
    public static function adjustDishStock(int $itemId, int $delta): array
    {
        dbTransactional(static function () use ($itemId, $delta): void {
            $row = dbFetchOne(
                "SELECT `stock_quantity` FROM `menu_items` WHERE `item_id` = :id AND `is_active` = 1 FOR UPDATE",
                [':id' => $itemId]
            );
            if (!$row) {
                return;
            }
            $newStock = max(0, (int)$row['stock_quantity'] + $delta);
            $newAvail = $newStock > 0 ? 1 : 0;
            dbQuery(
                "UPDATE `menu_items` SET `stock_quantity` = :sq, `is_available` = :av WHERE `item_id` = :id",
                [':sq' => $newStock, ':av' => $newAvail, ':id' => $itemId]
            );
        });

        return ['type' => 'success', 'message' => 'Stock quantity adjusted.'];
    }

    /**
     * Soft-archive a dish (is_active = 0, is_available = 0, stock_quantity = 0)
     * preserving historical order_items references.
     *
     * @return array{type: string, message: string}
     */
    public static function softDeleteDish(int $itemId): array
    {
        $dish = dbFetchOne("SELECT `item_name` FROM `menu_items` WHERE `item_id` = :id", [':id' => $itemId]);
        $dishName = $dish ? (string)$dish['item_name'] : 'Dish';
        $orderCount = (int)dbFetchVal("SELECT COUNT(*) FROM `order_items` WHERE `item_id` = :id", [':id' => $itemId]);

        dbQuery(
            "UPDATE `menu_items` SET `is_active` = 0, `is_available` = 0, `stock_quantity` = 0 WHERE `item_id` = :id",
            [':id' => $itemId]
        );

        if ($orderCount > 0) {
            return [
                'type' => 'warning',
                'message' => "Dish '{$dishName}' exists in {$orderCount} past customer order(s). It has been softly archived (is_active = 0) and removed from customer menus while preserving order receipts.",
            ];
        }

        return ['type' => 'success', 'message' => "Dish '{$dishName}' archived safely from active catalog."];
    }

    /**
     * Fetch filtered dishes, KPIs, categories, and catalog sync hashes for Admin Dishes page.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public static function getAdminDishesData(array $filters): array
    {
        $categories = dbFetchAll("SELECT * FROM `categories` WHERE `is_active` = 1 ORDER BY `display_order` ASC");

        $catFingerprintData = array_map(static fn(array $c): array => [
            (int)$c['category_id'],
            $c['category_name'],
            (int)$c['display_order'],
            (int)$c['is_active'],
        ], $categories);

        $dishFingerprintData = dbFetchAll("
            SELECT `item_id`, `category_id`, `item_name`, `price`, `stock_quantity`, `is_available`
            FROM `menu_items`
            WHERE `is_active` = 1
            ORDER BY `item_id` ASC
        ");

        $initialCatVer = md5((string)json_encode($catFingerprintData));
        $initialDishVer = md5((string)json_encode($dishFingerprintData));

        $kpiTotalDishes = (int)dbFetchVal("SELECT COUNT(*) FROM `menu_items` WHERE `is_active` = 1");
        $kpiAvailableDishes = (int)dbFetchVal("SELECT COUNT(*) FROM `menu_items` WHERE `is_active` = 1 AND `is_available` = 1 AND `stock_quantity` > 0");
        $kpiLowStockDishes = (int)dbFetchVal("SELECT COUNT(*) FROM `menu_items` WHERE `is_active` = 1 AND `is_available` = 1 AND `stock_quantity` > 0 AND `stock_quantity` <= 15");
        $kpiSoldOutDishes = (int)dbFetchVal("SELECT COUNT(*) FROM `menu_items` WHERE `is_active` = 1 AND (`is_available` = 0 OR `stock_quantity` <= 0)");
        $kpiVegDishes = (int)dbFetchVal("SELECT COUNT(*) FROM `menu_items` WHERE `is_active` = 1 AND `is_veg` = 1");
        $kpiNonVegDishes = (int)dbFetchVal("SELECT COUNT(*) FROM `menu_items` WHERE `is_active` = 1 AND `is_veg` = 0");
        $kpiAvgPrice = (float)dbFetchVal("SELECT AVG(`price`) FROM `menu_items` WHERE `is_active` = 1");

        $searchQuery = cleanInput((string)($filters['search'] ?? ''));
        $filterCat = cleanInput((string)($filters['category'] ?? ''));
        $filterStation = cleanInput((string)($filters['station'] ?? ''));
        $filterDiet = cleanInput((string)($filters['diet'] ?? ''));
        $filterMood = cleanInput((string)($filters['mood'] ?? ''));
        $filterStock = cleanInput((string)($filters['stock_status'] ?? ''));
        $sortBy = cleanInput((string)($filters['sort'] ?? 'category'));

        $conditions = ['m.is_active = 1'];
        $params = [];

        if ($searchQuery !== '') {
            $conditions[] = "(m.item_name LIKE :search_name OR m.description LIKE :search_desc)";
            $params[':search_name'] = '%' . $searchQuery . '%';
            $params[':search_desc'] = '%' . $searchQuery . '%';
        }
        if ($filterCat !== '') {
            $conditions[] = "m.category_id = :cid";
            $params[':cid'] = (int)$filterCat;
        }
        if ($filterStation !== '') {
            $conditions[] = "m.kitchen_station = :station";
            $params[':station'] = $filterStation;
        }
        if ($filterDiet === 'veg') {
            $conditions[] = "m.is_veg = 1";
        } elseif ($filterDiet === 'non_veg') {
            $conditions[] = "m.is_veg = 0";
        } elseif ($filterDiet === 'healthy') {
            $conditions[] = "m.is_healthy = 1";
        }
        if ($filterMood !== '' && $filterMood !== 'all') {
            $conditions[] = "(FIND_IN_SET(:mood, m.mood) > 0 OR m.mood LIKE :mood_like)";
            $params[':mood'] = $filterMood;
            $params[':mood_like'] = '%' . $filterMood . '%';
        }
        if ($filterStock === 'in_stock') {
            $conditions[] = "m.is_available = 1 AND m.stock_quantity > 0";
        } elseif ($filterStock === 'low_stock') {
            $conditions[] = "m.stock_quantity > 0 AND m.stock_quantity <= 15";
        } elseif ($filterStock === 'out_of_stock') {
            $conditions[] = "(m.is_available = 0 OR m.stock_quantity <= 0)";
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $sortMap = [
            'name_asc'   => 'm.item_name ASC',
            'price_low'  => 'm.price ASC',
            'price_high' => 'm.price DESC',
            'stock_low'  => 'm.stock_quantity ASC',
            'stock_high' => 'm.stock_quantity DESC',
            'prep_time'  => 'm.prep_time_minutes ASC',
            'rating'     => 'm.rating DESC, m.total_reviews DESC',
            'category'   => 'm.category_id ASC, m.item_name ASC',
        ];
        $orderBy = $sortMap[$sortBy] ?? $sortMap['category'];

        $dishes = dbFetchAll("
            SELECT m.*, c.category_name,
                   (SELECT ROUND(AVG(dr.appearance_rating), 1) FROM `dish_reviews` dr WHERE dr.item_id = m.item_id) AS avg_appearance,
                   (SELECT ROUND(AVG(dr.taste_rating), 1) FROM `dish_reviews` dr WHERE dr.item_id = m.item_id) AS avg_taste,
                   (SELECT ROUND(AVG(dr.portion_rating), 1) FROM `dish_reviews` dr WHERE dr.item_id = m.item_id) AS avg_portion,
                   (SELECT ROUND(AVG(dr.value_rating), 1) FROM `dish_reviews` dr WHERE dr.item_id = m.item_id) AS avg_value
            FROM `menu_items` m
            JOIN `categories` c ON m.category_id = c.category_id
            {$whereClause}
            ORDER BY {$orderBy}
        ", $params);

        return [
            'categories' => $categories,
            'initialCatVer' => $initialCatVer,
            'initialDishVer' => $initialDishVer,
            'initialCombinedVer' => md5($initialCatVer . '_' . $initialDishVer),
            'kpiTotalDishes' => $kpiTotalDishes,
            'kpiAvailableDishes' => $kpiAvailableDishes,
            'kpiLowStockDishes' => $kpiLowStockDishes,
            'kpiSoldOutDishes' => $kpiSoldOutDishes,
            'kpiVegDishes' => $kpiVegDishes,
            'kpiNonVegDishes' => $kpiNonVegDishes,
            'kpiAvgPrice' => $kpiAvgPrice,
            'searchQuery' => $searchQuery,
            'filterCat' => $filterCat,
            'filterStation' => $filterStation,
            'filterDiet' => $filterDiet,
            'filterMood' => $filterMood,
            'filterStock' => $filterStock,
            'sortBy' => $sortBy,
            'foodMoods' => getFoodMoods(),
            'dishes' => $dishes,
            'customSummaryMap' => getAllDishesCustomizationCounts(),
            'hasActiveFilters' => ($searchQuery !== '' || $filterCat !== '' || $filterStation !== '' || $filterDiet !== '' || $filterMood !== '' || $filterStock !== '' || ($sortBy !== 'category')),
        ];
    }

    /**
     * Fetch active dishes catalog for customization selector dropdown.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getCustomizationDishList(): array
    {
        return dbFetchAll("
            SELECT m.item_id, m.item_name, m.price, m.is_veg, m.is_healthy, m.image_url, m.stock_quantity, m.is_available,
                   c.category_name, c.category_slug
            FROM `menu_items` m
            JOIN `categories` c ON m.category_id = c.category_id
            WHERE m.is_active = 1
            ORDER BY c.display_order ASC, m.item_name ASC
        ");
    }

    /**
     * Handle Admin Dish Customization POST actions (groups, options, soft deletes, quick templates).
     *
     * @param array<string, mixed> $post
     * @return array{type: string, message: string}
     */
    public static function handleCustomizationAction(array $post): array
    {
        $action = (string)($post['action'] ?? '');

        if ($action === 'add_group') {
            $itemId = (int)($post['item_id'] ?? 0);
            $name = cleanInput((string)($post['group_name'] ?? ''));
            $type = in_array($post['selection_type'] ?? '', ['radio', 'checkbox'], true) ? (string)$post['selection_type'] : 'checkbox';
            $required = !empty($post['is_required']) ? 1 : 0;
            $order = (int)($post['display_order'] ?? 1);

            if ($name !== '' && $itemId > 0) {
                dbQuery("
                    INSERT INTO `dish_customization_groups` (`item_id`, `group_name`, `selection_type`, `is_required`, `min_selection`, `max_selection`, `display_order`, `is_active`)
                    VALUES (:iid, :name, :stype, :req, :min_sel, :max_sel, :disp, 1)
                ", [
                    ':iid' => $itemId,
                    ':name' => $name,
                    ':stype' => $type,
                    ':req' => $required,
                    ':min_sel' => $required ? 1 : 0,
                    ':max_sel' => ($type === 'radio') ? 1 : 0,
                    ':disp' => $order,
                ]);
                return ['type' => 'success', 'message' => "Customisation group '{$name}' created successfully."];
            }
            return ['type' => 'danger', 'message' => 'Group name is required.'];
        }

        if ($action === 'edit_group') {
            $groupId = (int)($post['group_id'] ?? 0);
            $name = cleanInput((string)($post['group_name'] ?? ''));
            $type = in_array($post['selection_type'] ?? '', ['radio', 'checkbox'], true) ? (string)$post['selection_type'] : 'checkbox';
            $required = !empty($post['is_required']) ? 1 : 0;
            $order = (int)($post['display_order'] ?? 1);

            if ($name !== '' && $groupId > 0) {
                dbQuery("
                    UPDATE `dish_customization_groups`
                    SET `group_name` = :name, `selection_type` = :stype, `is_required` = :req,
                        `min_selection` = :min_sel, `max_selection` = :max_sel, `display_order` = :disp
                    WHERE `group_id` = :gid AND `is_active` = 1
                ", [
                    ':name' => $name,
                    ':stype' => $type,
                    ':req' => $required,
                    ':min_sel' => $required ? 1 : 0,
                    ':max_sel' => ($type === 'radio') ? 1 : 0,
                    ':disp' => $order,
                    ':gid' => $groupId,
                ]);
                return ['type' => 'success', 'message' => "Group '{$name}' updated."];
            }
            return ['type' => 'danger', 'message' => 'Group name is required.'];
        }

        if ($action === 'delete_group') {
            $groupId = (int)($post['group_id'] ?? 0);
            if ($groupId > 0) {
                dbTransactional(static function () use ($groupId): void {
                    dbQuery("UPDATE `dish_customization_groups` SET `is_active` = 0 WHERE `group_id` = :gid", [':gid' => $groupId]);
                    dbQuery("UPDATE `dish_customization_options` SET `is_active` = 0 WHERE `group_id` = :gid", [':gid' => $groupId]);
                });
                return ['type' => 'success', 'message' => 'Customisation group and its options archived safely.'];
            }
            return ['type' => 'danger', 'message' => 'Invalid group ID.'];
        }

        if ($action === 'add_option') {
            $groupId = (int)($post['group_id'] ?? 0);
            $name = cleanInput((string)($post['option_name'] ?? ''));
            $price = max(0.0, (float)($post['price'] ?? 0.0));
            $isDefault = !empty($post['is_default']) ? 1 : 0;
            $isAvailable = !empty($post['is_available']) ? 1 : 0;
            $order = (int)($post['display_order'] ?? 1);

            if ($name !== '' && $groupId > 0) {
                if ($isDefault) {
                    dbQuery("UPDATE `dish_customization_options` SET `is_default` = 0 WHERE `group_id` = :gid AND `is_active` = 1", [':gid' => $groupId]);
                }
                dbQuery("
                    INSERT INTO `dish_customization_options` (`group_id`, `option_name`, `price`, `is_default`, `is_available`, `is_active`, `display_order`)
                    VALUES (:gid, :name, :price, :isdef, :isavail, 1, :disp)
                ", [
                    ':gid' => $groupId,
                    ':name' => $name,
                    ':price' => $price,
                    ':isdef' => $isDefault,
                    ':isavail' => $isAvailable,
                    ':disp' => $order,
                ]);
                return ['type' => 'success', 'message' => "Option '{$name}' added (+₹{$price})."];
            }
            return ['type' => 'danger', 'message' => 'Option name is required.'];
        }

        if ($action === 'edit_option') {
            $optionId = (int)($post['option_id'] ?? 0);
            $groupId = (int)($post['group_id'] ?? 0);
            $name = cleanInput((string)($post['option_name'] ?? ''));
            $price = max(0.0, (float)($post['price'] ?? 0.0));
            $isDefault = !empty($post['is_default']) ? 1 : 0;
            $isAvailable = !empty($post['is_available']) ? 1 : 0;
            $order = (int)($post['display_order'] ?? 1);

            if ($name !== '' && $optionId > 0) {
                if ($isDefault) {
                    dbQuery("UPDATE `dish_customization_options` SET `is_default` = 0 WHERE `group_id` = :gid AND `is_active` = 1", [':gid' => $groupId]);
                }
                dbQuery("
                    UPDATE `dish_customization_options`
                    SET `option_name` = :name, `price` = :price, `is_default` = :isdef,
                        `is_available` = :isavail, `display_order` = :disp
                    WHERE `option_id` = :oid AND `is_active` = 1
                ", [
                    ':name' => $name,
                    ':price' => $price,
                    ':isdef' => $isDefault,
                    ':isavail' => $isAvailable,
                    ':disp' => $order,
                    ':oid' => $optionId,
                ]);
                return ['type' => 'success', 'message' => "Option '{$name}' updated."];
            }
            return ['type' => 'danger', 'message' => 'Option name is required.'];
        }

        if ($action === 'delete_option') {
            $optionId = (int)($post['option_id'] ?? 0);
            if ($optionId > 0) {
                dbQuery("UPDATE `dish_customization_options` SET `is_active` = 0 WHERE `option_id` = :oid", [':oid' => $optionId]);
                return ['type' => 'success', 'message' => 'Customisation option archived safely.'];
            }
            return ['type' => 'danger', 'message' => 'Invalid option ID.'];
        }

        if ($action === 'toggle_option_availability') {
            $optionId = (int)($post['option_id'] ?? 0);
            if ($optionId > 0) {
                dbQuery(
                    "UPDATE `dish_customization_options` SET `is_available` = 1 - `is_available` WHERE `option_id` = :oid AND `is_active` = 1",
                    [':oid' => $optionId]
                );
                return ['type' => 'success', 'message' => 'Option availability toggled.'];
            }
            return ['type' => 'danger', 'message' => 'Invalid option ID.'];
        }

        if ($action === 'quick_template') {
            $template = (string)($post['template_type'] ?? '');
            $itemId = (int)($post['item_id'] ?? 0);

            try {
                dbTransactional(static function (PDO $pdo) use ($template, $itemId): void {
                    if ($template === 'spice') {
                        dbQuery("
                            INSERT INTO `dish_customization_groups` (`item_id`, `group_name`, `selection_type`, `is_required`, `min_selection`, `max_selection`, `display_order`, `is_active`)
                            VALUES (:iid, 'Spice Level', 'radio', 1, 1, 1, 1, 1)
                        ", [':iid' => $itemId]);
                        $newGid = (int)$pdo->lastInsertId();
                        dbQuery("
                            INSERT INTO `dish_customization_options` (`group_id`, `option_name`, `price`, `is_default`, `is_available`, `is_active`, `display_order`) VALUES
                            (:gid1, 'Mild Spice', 0.00, 0, 1, 1, 1),
                            (:gid2, 'Medium Spicy', 0.00, 1, 1, 1, 2),
                            (:gid3, 'Extra Fiery Hot', 0.00, 0, 1, 1, 3)
                        ", [':gid1' => $newGid, ':gid2' => $newGid, ':gid3' => $newGid]);
                    } elseif ($template === 'cheese_toppings') {
                        dbQuery("
                            INSERT INTO `dish_customization_groups` (`item_id`, `group_name`, `selection_type`, `is_required`, `min_selection`, `max_selection`, `display_order`, `is_active`)
                            VALUES (:iid, 'Toppings & Extra Cheese', 'checkbox', 0, 0, 0, 2, 1)
                        ", [':iid' => $itemId]);
                        $newGid = (int)$pdo->lastInsertId();
                        dbQuery("
                            INSERT INTO `dish_customization_options` (`group_id`, `option_name`, `price`, `is_default`, `is_available`, `is_active`, `display_order`) VALUES
                            (:gid1, 'Extra Cheese', 35.00, 0, 1, 1, 1),
                            (:gid2, 'Extra Crispy Fried Onions', 25.00, 0, 1, 1, 2),
                            (:gid3, 'Pickled Jalapeno Slices', 20.00, 0, 1, 1, 3),
                            (:gid4, 'Garlic Herb Dip', 25.00, 0, 1, 1, 4)
                        ", [':gid1' => $newGid, ':gid2' => $newGid, ':gid3' => $newGid, ':gid4' => $newGid]);
                    }
                });

                if ($template === 'spice') {
                    return ['type' => 'success', 'message' => 'Quick Spice Level group added.'];
                }
                if ($template === 'cheese_toppings') {
                    return ['type' => 'success', 'message' => 'Quick Toppings & Extra Cheese group added.'];
                }
            } catch (Throwable $e) {
                error_log("Quick customization template failed for item {$itemId}: " . $e->getMessage());
                return ['type' => 'danger', 'message' => 'Failed to apply customization template. Please try again.'];
            }
        }

        return ['type' => 'danger', 'message' => 'Invalid customization action requested.'];
    }

    /**
     * Handle Admin Food Moods POST actions and return flash metadata.
     *
     * @param array<string, mixed> $post
     * @return array{type: string, message: string}
     */
    public static function handleMoodAction(array $post): array
    {
        $action = (string)($post['action'] ?? '');

        if ($action === 'add_mood') {
            $name = trim(strip_tags((string)($post['name'] ?? '')));
            $key = trim(strip_tags((string)($post['mood_key'] ?? '')));
            if ($key === '') {
                $key = strtolower((string)preg_replace('/[^A-Za-z0-9_]+/', '_', trim($name)));
            }
            $key = trim($key, '_');
            $shortName = trim(strip_tags((string)($post['short_name'] ?? ''))) ?: $name;
            $emoji = trim((string)($post['emoji'] ?? '🍽️'));
            $icon = trim(strip_tags((string)($post['icon'] ?? 'fa-solid fa-utensils')));
            $tagline = trim(strip_tags((string)($post['tagline'] ?? '')));
            $badgeBg = trim((string)($post['badge_bg'] ?? '#FFF3CD'));
            $badgeColor = trim((string)($post['badge_color'] ?? '#856404'));
            $borderColor = trim((string)($post['border_color'] ?? '#FFE69C'));
            $accent = trim((string)($post['accent'] ?? '#FFC107'));
            $order = isset($post['display_order']) && $post['display_order'] !== '' ? (int)$post['display_order'] : null;
            $isActive = !empty($post['is_active']) ? 1 : 0;
            $imageUrl = trim((string)($post['image_url'] ?? ''));

            if ($order === null || $order <= 0) {
                $maxOrd = (int)dbFetchVal("SELECT MAX(`display_order`) FROM `food_moods`");
                $order = $maxOrd + 1;
            }

            $upload = handleMoodPhotoUpload('mood_image');
            if ($upload !== null) {
                if (!empty($upload['error'])) {
                    return ['type' => 'danger', 'message' => 'Photo upload failed: ' . $upload['error']];
                }
                if (!empty($upload['url'])) {
                    $imageUrl = $upload['url'];
                }
            }

            if ($imageUrl === '') {
                $imageUrl = 'assets/images/moods/comfort.jpg';
            }

            if ($name === '' || $key === '') {
                return ['type' => 'danger', 'message' => 'Mood name and identifier key are required.'];
            }

            $exists = (int)dbFetchVal("SELECT COUNT(*) FROM `food_moods` WHERE `mood_key` = :k", [':k' => $key]);
            if ($exists > 0) {
                return ['type' => 'warning', 'message' => "A food mood with key '{$key}' already exists. Please choose a distinct key."];
            }

            dbQuery("
                INSERT INTO `food_moods`
                (`mood_key`, `name`, `short_name`, `emoji`, `icon`, `image_url`, `tagline`, `badge_bg`, `badge_color`, `border_color`, `accent`, `display_order`, `is_active`)
                VALUES (:k, :n, :sn, :em, :ic, :img, :tag, :bg, :bc, :brd, :acc, :ord, :act)
            ", [
                ':k'   => $key,
                ':n'   => $name,
                ':sn'  => $shortName,
                ':em'  => $emoji,
                ':ic'  => $icon,
                ':img' => $imageUrl,
                ':tag' => $tagline,
                ':bg'  => $badgeBg,
                ':bc'  => $badgeColor,
                ':brd' => $borderColor,
                ':acc' => $accent,
                ':ord' => $order,
                ':act' => $isActive,
            ]);

            return ['type' => 'success', 'message' => "Food Mood '{$name}' created successfully with photo!"];
        }

        if ($action === 'edit_mood') {
            $id = (int)($post['mood_id'] ?? 0);
            $name = trim(strip_tags((string)($post['name'] ?? '')));
            $shortName = trim(strip_tags((string)($post['short_name'] ?? '')));
            $emoji = trim((string)($post['emoji'] ?? '🍽️'));
            $icon = trim(strip_tags((string)($post['icon'] ?? 'fa-solid fa-utensils')));
            $tagline = trim(strip_tags((string)($post['tagline'] ?? '')));
            $badgeBg = trim((string)($post['badge_bg'] ?? '#FFF3CD'));
            $badgeColor = trim((string)($post['badge_color'] ?? '#856404'));
            $borderColor = trim((string)($post['border_color'] ?? '#FFE69C'));
            $accent = trim((string)($post['accent'] ?? '#FFC107'));
            $order = (int)($post['display_order'] ?? 0);
            $isActive = !empty($post['is_active']) ? 1 : 0;
            $imageUrl = trim((string)($post['image_url'] ?? ''));

            $upload = handleMoodPhotoUpload('mood_image');
            if ($upload !== null) {
                if (!empty($upload['error'])) {
                    return ['type' => 'danger', 'message' => 'Photo upload failed: ' . $upload['error']];
                }
                if (!empty($upload['url'])) {
                    $imageUrl = $upload['url'];
                }
            }

            if ($imageUrl === '') {
                $existingImg = dbFetchVal("SELECT `image_url` FROM `food_moods` WHERE `mood_id` = :id", [':id' => $id]);
                if ($existingImg) {
                    $imageUrl = (string)$existingImg;
                }
            }

            dbQuery("
                UPDATE `food_moods`
                SET `name` = :n, `short_name` = :sn, `emoji` = :em, `icon` = :ic,
                    `image_url` = :img, `tagline` = :tag, `badge_bg` = :bg, `badge_color` = :bc,
                    `border_color` = :brd, `accent` = :acc, `display_order` = :ord, `is_active` = :act
                WHERE `mood_id` = :id
            ", [
                ':n'   => $name,
                ':sn'  => $shortName,
                ':em'  => $emoji,
                ':ic'  => $icon,
                ':img' => $imageUrl,
                ':tag' => $tagline,
                ':bg'  => $badgeBg,
                ':bc'  => $badgeColor,
                ':brd' => $borderColor,
                ':acc' => $accent,
                ':ord' => $order,
                ':act' => $isActive,
                ':id'  => $id,
            ]);

            return ['type' => 'success', 'message' => "Food Mood '{$name}' updated successfully."];
        }

        if ($action === 'manage_mood_dishes') {
            $moodKey = cleanInput((string)($post['mood_key'] ?? ''));
            $selectedItems = isset($post['item_ids']) && is_array($post['item_ids']) ? $post['item_ids'] : [];
            if ($moodKey !== '') {
                setMoodDishes($moodKey, $selectedItems);
                $count = count($selectedItems);
                return ['type' => 'success', 'message' => "Assigned {$count} dishes to '{$moodKey}' mood successfully."];
            }
            return ['type' => 'warning', 'message' => 'Invalid mood selection.'];
        }

        if ($action === 'quick_add_dish') {
            $moodKey = cleanInput((string)($post['mood_key'] ?? ''));
            $itemId = (int)($post['item_id'] ?? 0);
            if ($itemId > 0 && $moodKey !== '') {
                addDishToMood($itemId, $moodKey);
                $dishName = (string)(dbFetchVal("SELECT `item_name` FROM `menu_items` WHERE `item_id` = :id", [':id' => $itemId]) ?? 'Dish');
                return ['type' => 'success', 'message' => "Added '{$dishName}' to '{$moodKey}' mood."];
            }
            return ['type' => 'warning', 'message' => 'Invalid dish or mood selection.'];
        }

        if ($action === 'quick_remove_dish') {
            $moodKey = cleanInput((string)($post['mood_key'] ?? ''));
            $itemId = (int)($post['item_id'] ?? 0);
            if ($itemId > 0 && $moodKey !== '') {
                removeDishFromMood($itemId, $moodKey);
                $dishName = (string)(dbFetchVal("SELECT `item_name` FROM `menu_items` WHERE `item_id` = :id", [':id' => $itemId]) ?? 'Dish');
                return ['type' => 'success', 'message' => "Removed '{$dishName}' from '{$moodKey}' mood."];
            }
            return ['type' => 'warning', 'message' => 'Invalid dish or mood selection.'];
        }

        if ($action === 'toggle_status') {
            $id = (int)($post['mood_id'] ?? 0);
            $m = dbFetchOne("SELECT `name`, `is_active` FROM `food_moods` WHERE `mood_id` = :id", [':id' => $id]);
            if ($m) {
                $newStatus = $m['is_active'] ? 0 : 1;
                dbQuery("UPDATE `food_moods` SET `is_active` = :st WHERE `mood_id` = :id", [
                    ':st' => $newStatus,
                    ':id' => $id,
                ]);
                getFoodMoods(false, true);
                $lbl = $newStatus ? 'now active on storefront' : 'now hidden from storefront';
                return ['type' => 'success', 'message' => "Mood '{$m['name']}' is {$lbl}."];
            }
            return ['type' => 'danger', 'message' => 'Mood not found.'];
        }

        if ($action === 'update_order') {
            $id = (int)($post['mood_id'] ?? 0);
            $direction = cleanInput((string)($post['direction'] ?? ''));
            $cur = (int)dbFetchVal("SELECT `display_order` FROM `food_moods` WHERE `mood_id` = :id", [':id' => $id]);
            if ($direction === 'up') {
                $newOrder = max(1, $cur - 1);
            } elseif ($direction === 'down') {
                $newOrder = $cur + 1;
            } else {
                $newOrder = $cur;
            }
            dbQuery("UPDATE `food_moods` SET `display_order` = :ord WHERE `mood_id` = :id", [
                ':ord' => $newOrder,
                ':id'  => $id,
            ]);
            getFoodMoods(false, true);
            return ['type' => 'success', 'message' => 'Display order updated.'];
        }

        if ($action === 'delete_mood') {
            $id = (int)($post['mood_id'] ?? 0);
            $m = dbFetchOne("SELECT `mood_key`, `name` FROM `food_moods` WHERE `mood_id` = :id", [':id' => $id]);
            if ($m) {
                $dishes = dbFetchAll(
                    "SELECT `item_id`, `mood` FROM `menu_items` WHERE `is_active` = 1 AND FIND_IN_SET(:k, REPLACE(`mood`, ' ', '')) > 0",
                    [':k' => $m['mood_key']]
                );
                foreach ($dishes as $d) {
                    removeDishFromMood((int)$d['item_id'], (string)$m['mood_key']);
                }
                dbQuery("UPDATE `food_moods` SET `is_active` = 0 WHERE `mood_id` = :id", [':id' => $id]);
                getFoodMoods(false, true);
                return ['type' => 'success', 'message' => "Mood '{$m['name']}' archived and unlinked from dishes."];
            }
            return ['type' => 'danger', 'message' => 'Mood not found.'];
        }

        return ['type' => 'danger', 'message' => 'Unknown mood action.'];
    }

    /**
     * Fetch Admin Moods curation overview dataset.
     *
     * @return array<string, mixed>
     */
    public static function getAdminMoodsData(): array
    {
        $moods = dbFetchAll("SELECT * FROM `food_moods` ORDER BY `display_order` ASC, `mood_id` ASC");

        $totalMoods = count($moods);
        $activeMoods = 0;
        $totalDishesInMoods = 0;

        foreach ($moods as &$m) {
            if (!empty($m['is_active'])) {
                $activeMoods++;
            }
            $m['dish_count'] = getMoodDishCount((string)$m['mood_key']);
            $totalDishesInMoods += $m['dish_count'];
        }
        unset($m);

        $allDishes = dbFetchAll("
            SELECT m.item_id, m.item_name, m.price, m.is_veg, m.image_url, m.mood, c.category_name, c.category_slug
            FROM `menu_items` m
            LEFT JOIN `categories` c ON m.category_id = c.category_id
            WHERE m.is_active = 1
            ORDER BY c.category_name ASC, m.item_name ASC
        ");

        return [
            'moods' => $moods,
            'totalMoods' => $totalMoods,
            'activeMoods' => $activeMoods,
            'totalDishesInMoods' => $totalDishesInMoods,
            'allDishes' => $allDishes,
        ];
    }
}
