<?php
declare(strict_types=1);
/**
 * Shared Helper Functions & Business Logic
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Execute a query with parameters and return PDOStatement
 */
function dbQuery(string $sql, array $params = []): ?PDOStatement
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return null;
    }
    try {
        $stmt = $pdo->prepare($sql);
        $pos = 1;
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif (is_null($value)) {
                $type = PDO::PARAM_NULL;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_INT;
                $value = $value ? 1 : 0;
            } else {
                $type = PDO::PARAM_STR;
            }

            if (is_int($key)) {
                $stmt->bindValue($pos++, $value, $type);
            } else {
                $stmt->bindValue($key, $value, $type);
            }
        }
        $stmt->execute();
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database Query Error: " . $e->getMessage() . " | SQL: " . $sql);
        if ($pdo->inTransaction()) {
            throw $e;
        }
        return null;
    }
}

/**
 * Execute a callback within an ACID database transaction.
 * Supports safe nesting by reusing an existing active transaction when present.
 *
 * @template T
 * @param callable(PDO): T $callback
 * @return T
 */
function dbTransactional(callable $callback): mixed
{
    $pdo = getDbConnection();
    if (!$pdo) {
        throw new RuntimeException('Database connection unavailable.');
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $result = $callback($pdo);
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }
        return $result;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Validate an unquoted SQL identifier (database, table, or column name).
 */
function isValidSqlIdentifier(string $identifier): bool
{
    return (bool)preg_match('/^[a-zA-Z0-9_]+$/', $identifier);
}

/**
 * Validate a SQL identifier and return it, or throw InvalidArgumentException.
 */
function assertSqlIdentifier(string $identifier, string $context = 'SQL identifier'): string
{
    if (!isValidSqlIdentifier($identifier)) {
        throw new InvalidArgumentException("Invalid {$context}: {$identifier}");
    }
    return $identifier;
}

/**
 * Fetch all rows
 */
function dbFetchAll(string $sql, array $params = []): array
{
    $stmt = dbQuery($sql, $params);
    return $stmt ? $stmt->fetchAll() : [];
}

/**
 * Fetch single row
 */
function dbFetchOne(string $sql, array $params = []): ?array
{
    $stmt = dbQuery($sql, $params);
    $res = $stmt ? $stmt->fetch() : null;
    return $res ?: null;
}

/**
 * Fetch single scalar value
 */
function dbFetchVal(string $sql, array $params = []): mixed
{
    $stmt = dbQuery($sql, $params);
    return $stmt ? $stmt->fetchColumn() : null;
}

/**
 * Get active (or all) coupon definitions from database with graceful static fallback
 */
function getAvailableCoupons(bool $includeInactive = false): array
{
    static $fallbackCoupons = [
        'LUSHFOREST' => [
            'coupon_id' => 1,
            'code' => 'LUSHFOREST',
            'title' => 'Lush Forest Special',
            'discount_type' => 'percentage',
            'discount_value' => 20.00,
            'max_discount' => 100.00,
            'min_order' => 299.00,
            'usage_limit' => 0,
            'per_user_limit' => 0,
            'first_order_only' => 0,
            'used_count' => 0,
            'is_active' => 1,
            'description' => '20% OFF up to ₹100 on orders above ₹299 (Figma Lush Special)'
        ],
        'ZIPPY50' => [
            'coupon_id' => 2,
            'code' => 'ZIPPY50',
            'title' => 'Zippy Mega Feast',
            'discount_type' => 'percentage',
            'discount_value' => 50.00,
            'max_discount' => 120.00,
            'min_order' => 399.00,
            'usage_limit' => 0,
            'per_user_limit' => 0,
            'first_order_only' => 0,
            'used_count' => 0,
            'is_active' => 1,
            'description' => '50% OFF up to ₹120 on your order above ₹399'
        ],
        'WELCOME' => [
            'coupon_id' => 3,
            'code' => 'WELCOME',
            'title' => 'Welcome Bonus',
            'discount_type' => 'flat',
            'discount_value' => 50.00,
            'max_discount' => 50.00,
            'min_order' => 199.00,
            'usage_limit' => 0,
            'per_user_limit' => 0,
            'first_order_only' => 0,
            'used_count' => 0,
            'is_active' => 1,
            'description' => 'Flat ₹50 OFF for welcome customers on orders above ₹199'
        ]
    ];

    try {
        $pdo = getDbConnection();
        if (!$pdo) {
            return $fallbackCoupons;
        }
        $where = $includeInactive ? "1=1" : "`is_active` = 1 AND (`valid_from` IS NULL OR `valid_from` <= NOW()) AND (`valid_until` IS NULL OR `valid_until` >= NOW())";
        $rows = dbFetchAll("SELECT * FROM `coupons` WHERE {$where} ORDER BY `coupon_id` ASC");
        if (empty($rows)) {
            return $includeInactive ? [] : $fallbackCoupons;
        }
        $mapped = [];
        foreach ($rows as $row) {
            $code = strtoupper(trim((string)$row['code']));
            $mapped[$code] = [
                'coupon_id' => (int)$row['coupon_id'],
                'code' => $code,
                'title' => (string)($row['title'] ?? $code),
                'description' => (string)($row['description'] ?? ''),
                'discount_type' => (string)$row['discount_type'],
                'discount_value' => (float)$row['discount_value'],
                'max_discount' => (float)$row['max_discount'],
                'min_order' => (float)$row['min_order'],
                'valid_from' => $row['valid_from'] ?? null,
                'valid_until' => $row['valid_until'] ?? null,
                'usage_limit' => (int)($row['usage_limit'] ?? 0),
                'per_user_limit' => (int)($row['per_user_limit'] ?? 0),
                'first_order_only' => (int)($row['first_order_only'] ?? 0),
                'used_count' => (int)($row['used_count'] ?? 0),
                'is_active' => (int)($row['is_active'] ?? 1),
            ];
        }
        return $mapped;
    } catch (Throwable $e) {
        return $fallbackCoupons;
    }
}

/**
 * Validate a promo coupon against subtotal, active dates, and per-user/global usage limits.
 *
 * @return array{valid: bool, coupon: array<string, mixed>|null, discount: float, error: ?string}
 */
function validateCouponForSubtotal(string $couponCode, float $subtotal, ?int $userId = null): array
{
    $code = strtoupper(trim($couponCode));
    if ($code === '') {
        return ['valid' => false, 'coupon' => null, 'discount' => 0.00, 'error' => null];
    }

    $allCoupons = getAvailableCoupons(true);
    if (!isset($allCoupons[$code])) {
        return ['valid' => false, 'coupon' => null, 'discount' => 0.00, 'error' => "Invalid coupon code: {$code}."];
    }

    $cp = $allCoupons[$code];
    if (empty($cp['is_active'])) {
        return ['valid' => false, 'coupon' => null, 'discount' => 0.00, 'error' => "Coupon {$code} is currently inactive."];
    }

    $now = time();
    if (!empty($cp['valid_from']) && strtotime((string)$cp['valid_from']) > $now) {
        return ['valid' => false, 'coupon' => null, 'discount' => 0.00, 'error' => "Coupon {$code} is not active yet."];
    }
    if (!empty($cp['valid_until']) && strtotime((string)$cp['valid_until']) < $now) {
        return ['valid' => false, 'coupon' => null, 'discount' => 0.00, 'error' => "Coupon {$code} has expired."];
    }

    if ((int)($cp['usage_limit'] ?? 0) > 0 && (int)($cp['used_count'] ?? 0) >= (int)$cp['usage_limit']) {
        return ['valid' => false, 'coupon' => null, 'discount' => 0.00, 'error' => "Coupon {$code} has reached its maximum redemption limit."];
    }

    if ($userId !== null && $userId > 0) {
        try {
            if (!empty($cp['first_order_only'])) {
                $priorOrders = (int)dbFetchVal(
                    "SELECT COUNT(*) FROM `orders` WHERE `user_id` = :uid AND `order_status` != 'CANCELLED'",
                    [':uid' => $userId]
                );
                if ($priorOrders > 0) {
                    return ['valid' => false, 'coupon' => null, 'discount' => 0.00, 'error' => "Coupon {$code} is valid on your first order only."];
                }
            }
            if ((int)($cp['per_user_limit'] ?? 0) > 0 && !empty($cp['coupon_id'])) {
                $userUsed = (int)dbFetchVal(
                    "SELECT COUNT(*) FROM `coupon_redemptions` WHERE `coupon_id` = :cid AND `user_id` = :uid",
                    [':cid' => (int)$cp['coupon_id'], ':uid' => $userId]
                );
                if ($userUsed >= (int)$cp['per_user_limit']) {
                    return ['valid' => false, 'coupon' => null, 'discount' => 0.00, 'error' => "You have already reached the usage limit for coupon {$code}."];
                }
            }
        } catch (Throwable $e) {
            // Ignore when optional redemption tables do not exist yet
        }
    }

    if ($subtotal < (float)$cp['min_order']) {
        return [
            'valid' => false,
            'coupon' => null,
            'discount' => 0.00,
            'error' => "Minimum order of ₹" . number_format((float)$cp['min_order'], 0) . " required for coupon {$code}."
        ];
    }

    if ($cp['discount_type'] === 'percentage') {
        $calc = ($subtotal * (float)$cp['discount_value']) / 100.0;
        $maxCap = (float)$cp['max_discount'] > 0 ? (float)$cp['max_discount'] : $calc;
        $discount = min($calc, $maxCap);
    } else {
        $discount = min((float)$cp['discount_value'], $subtotal);
    }

    return [
        'valid' => true,
        'coupon' => $cp,
        'discount' => round($discount, 2),
        'error' => null,
    ];
}

/**
 * Calculate Cart Totals (coupons, distance delivery fee, loyalty points, GST)
 *
 * @param array $cartItems Array of items with 'price' and 'qty'
 * @param string|null $couponCode
 * @param int|null $userId
 * @param float|null $overrideDeliveryFee
 * @param int $loyaltyPointsRequested
 * @param int|null $userLoyaltyBalance
 * @return array
 */
function calculateBillTotals(
    array $cartItems,
    ?string $couponCode = null,
    ?int $userId = null,
    ?float $overrideDeliveryFee = null,
    int $loyaltyPointsRequested = 0,
    ?int $userLoyaltyBalance = null
): array
{
    $subtotal = 0.00;
    $totalQty = 0;

    foreach ($cartItems as $item) {
        $price = (float)($item['price'] ?? 0);
        $qty = (int)($item['qty'] ?? 1);
        $subtotal += ($price * $qty);
        $totalQty += $qty;
    }

    // Delivery fee calculation (supports Haversine override while maintaining free threshold)
    if ($subtotal >= FREE_DELIVERY_THRESHOLD || $subtotal == 0) {
        $deliveryFee = 0.00;
    } elseif ($overrideDeliveryFee !== null) {
        $deliveryFee = max(0.00, round($overrideDeliveryFee, 2));
    } else {
        $deliveryFee = DEFAULT_DELIVERY_FEE;
    }

    // Platform fee
    $platformFee = ($subtotal > 0) ? PLATFORM_FEE : 0.00;

    // Coupon discount calculation
    $discount = 0.00;
    $couponApplied = false;
    $couponError = null;
    $appliedCouponMeta = null;

    if (!empty($couponCode)) {
        $cpCheck = validateCouponForSubtotal($couponCode, $subtotal, $userId);
        if ($cpCheck['valid']) {
            $discount = $cpCheck['discount'];
            $couponApplied = true;
            $appliedCouponMeta = $cpCheck['coupon'];
        } else {
            $couponError = $cpCheck['error'];
        }
    }

    // Loyalty Points redemption (1 Point = ₹1.00, max 50% of post-coupon food subtotal)
    $afterCouponSubtotal = max(0.00, $subtotal - $discount);
    $loyaltyPointsUsed = 0;
    $loyaltyDiscount = 0.00;
    if ($loyaltyPointsRequested > 0 && $afterCouponSubtotal > 0) {
        $maxRedeemableRupees = floor($afterCouponSubtotal * LOYALTY_MAX_DISCOUNT_RATIO);
        $maxPointsByRatio = (int)floor($maxRedeemableRupees / LOYALTY_POINT_RUPEE_VALUE);
        $balanceLimit = $userLoyaltyBalance !== null ? max(0, $userLoyaltyBalance) : $loyaltyPointsRequested;
        $loyaltyPointsUsed = max(0, min($loyaltyPointsRequested, $balanceLimit, $maxPointsByRatio));
        $loyaltyDiscount = round($loyaltyPointsUsed * LOYALTY_POINT_RUPEE_VALUE, 2);
    }

    // Tax calculation on net food subtotal (5% GST)
    $taxableSubtotal = max(0.00, $afterCouponSubtotal - $loyaltyDiscount);
    $gstAmount = round(($taxableSubtotal * GST_PERCENT) / 100, 2);
    $cgst = round($gstAmount / 2, 2);
    $sgst = round($gstAmount / 2, 2);

    $finalTotal = max(0.00, $taxableSubtotal + $gstAmount + $deliveryFee + $platformFee);

    return [
        'subtotal' => round($subtotal, 2),
        'total_qty' => $totalQty,
        'discount' => round($discount, 2),
        'coupon_code' => $couponApplied ? strtoupper(trim((string)$couponCode)) : null,
        'coupon_meta' => $appliedCouponMeta,
        'coupon_error' => $couponError,
        'loyalty_points_used' => $loyaltyPointsUsed,
        'loyalty_discount' => $loyaltyDiscount,
        'gst_amount' => $gstAmount,
        'cgst' => $cgst,
        'sgst' => $sgst,
        'delivery_fee' => round($deliveryFee, 2),
        'platform_fee' => round($platformFee, 2),
        'final_total' => round($finalTotal, 2)
    ];
}

/**
 * Get active session cart array
 */
function getSessionCart(): array
{
    return $_SESSION['cart'] ?? [];
}

/**
 * Save session cart array
 */
function setSessionCart(array $cart): void
{
    $_SESSION['cart'] = $cart;
}

/**
 * Clear session cart
 */
function clearSessionCart(): void
{
    unset($_SESSION['cart']);
    unset($_SESSION['applied_coupon']);
}

/**
 * Resolves dish / food image URL safely across domains, ports, relative assets, and fallbacks.
 */
function getDishImageUrl(?string $url): string
{
    if (empty($url)) {
        return 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300';
    }

    // If it's an uploaded dish photo that has a full URL or relative path matching uploads/dishes
    if (preg_match('#/assets/uploads/dishes/([^/\?\#]+)$#', $url, $m)) {
        return ASSETS_URL . '/uploads/dishes/' . $m[1];
    }

    // If it's an absolute URL (e.g. Unsplash, CDN, etc.)
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }

    // If relative path like 'assets/uploads/dishes/...' or '/assets/...'
    $clean = ltrim($url, '/');
    if (str_starts_with($clean, 'assets/')) {
        return BASE_URL . '/' . $clean;
    }

    return ASSETS_URL . '/' . $clean;
}


/**
 * Resolves category image URL safely across domains, ports, relative assets, and fallbacks.
 */
function getCategoryImageUrl(?string $url): string
{
    if (empty($url)) {
        return 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300';
    }

    if (preg_match('#/assets/uploads/categories/([^/\?\#]+)$#', $url, $m)) {
        return ASSETS_URL . '/uploads/categories/' . $m[1];
    }

    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }

    $clean = ltrim($url, '/');
    if (str_starts_with($clean, 'assets/')) {
        return BASE_URL . '/' . $clean;
    }

    return ASSETS_URL . '/' . $clean;
}

/**
 * Resolves mood photo image URL safely across assets, relative paths, and fallbacks.
 */
function getMoodImageUrl(?string $moodKeyOrUrl): string
{
    if (empty($moodKeyOrUrl)) {
        return ASSETS_URL . '/images/moods/comfort.jpg';
    }

    $moods = getFoodMoods(true);
    if (isset($moods[$moodKeyOrUrl]) && !empty($moods[$moodKeyOrUrl]['image'])) {
        $img = $moods[$moodKeyOrUrl]['image'];
        if (preg_match('#^https?://#i', $img)) {
            return $img;
        }
        $clean = ltrim($img, '/');
        if (str_starts_with($clean, 'assets/')) {
            return BASE_URL . '/' . $clean;
        }
        return ASSETS_URL . '/' . $clean;
    }

    if (preg_match('#^https?://#i', $moodKeyOrUrl)) {
        return $moodKeyOrUrl;
    }

    $clean = ltrim($moodKeyOrUrl, '/');
    if (str_starts_with($clean, 'assets/')) {
        return BASE_URL . '/' . $clean;
    }

    return ASSETS_URL . '/' . $clean;
}

/**
 * Return all defined food mood states for the Mood-Based Food Selection engine.
 * Maps human emotional & physical states to curated culinary attributes.
 * Reads dynamically from `food_moods` database table with graceful static fallback.
 * 
 * @param bool $includeInactive If true, returns all moods including disabled ones
 * @return array
 */
function getFoodMoods(bool $includeInactive = false, bool $refreshCache = false): array
{
    static $cacheActive = null;
    static $cacheAll = null;

    if ($refreshCache) {
        $cacheActive = null;
        $cacheAll = null;
    }

    if ($includeInactive && $cacheAll !== null) {
        return $cacheAll;
    }
    if (!$includeInactive && $cacheActive !== null) {
        return $cacheActive;
    }

    try {
        $sql = "SELECT * FROM `food_moods`";
        if (!$includeInactive) {
            $sql .= " WHERE `is_active` = 1";
        }
        $sql .= " ORDER BY `display_order` ASC, `mood_id` ASC";
        $rows = dbFetchAll($sql);
        if (!empty($rows)) {
            $moods = [];
            foreach ($rows as $r) {
                $key = $r['mood_key'];
                $moods[$key] = [
                    'id'           => $key,
                    'mood_id'      => (int)$r['mood_id'],
                    'mood_key'     => $key,
                    'name'         => $r['name'],
                    'short_name'   => $r['short_name'],
                    'emoji'        => $r['emoji'],
                    'icon'         => $r['icon'],
                    'image'        => $r['image_url'],
                    'image_url'    => $r['image_url'],
                    'tagline'      => $r['tagline'],
                    'badge_bg'     => $r['badge_bg'],
                    'badge_color'  => $r['badge_color'],
                    'border_color' => $r['border_color'],
                    'accent'       => $r['accent'],
                    'display_order'=> (int)$r['display_order'],
                    'is_active'    => (int)$r['is_active']
                ];
            }
            if ($includeInactive) {
                $cacheAll = $moods;
                return $cacheAll;
            } else {
                $cacheActive = $moods;
                return $cacheActive;
            }
        }
    } catch (\Throwable $e) {
        // Fallback to static defaults
    }

    $defaults = [
        'comfort' => [
            'id' => 'comfort',
            'name' => 'Comfort & Soul',
            'short_name' => 'Comfort',
            'emoji' => '🛋️',
            'icon' => 'fa-solid fa-mug-hot',
            'image' => 'assets/images/moods/comfort.jpg',
            'tagline' => 'Warm, soulful curries, rich gravies & buttery breads to unwind & destress',
            'badge_bg' => '#FFF3CD',
            'badge_color' => '#856404',
            'border_color' => '#FFE69C',
            'accent' => '#FFC107'
        ],
        'party' => [
            'id' => 'party',
            'name' => 'Party & Celebration',
            'short_name' => 'Party Feast',
            'emoji' => '🎉',
            'icon' => 'fa-solid fa-champagne-glasses',
            'image' => 'assets/images/moods/party.jpg',
            'tagline' => 'Royal aromatic biryanis & loaded gourmet feast burgers for celebrations',
            'badge_bg' => '#E8E7FD',
            'badge_color' => '#3B28CC',
            'border_color' => '#C5C1F9',
            'accent' => '#6F42C1'
        ],
        'healthy' => [
            'id' => 'healthy',
            'name' => 'Energetic & Fit',
            'short_name' => 'Healthy / Fit',
            'emoji' => '⚡',
            'icon' => 'fa-solid fa-leaf',
            'image' => 'assets/images/moods/healthy.jpg',
            'tagline' => 'Nutrient-dense superfoods, high-protein bowls & cold-pressed detox juices',
            'badge_bg' => '#CEFFDC',
            'badge_color' => '#1C2B20',
            'border_color' => '#68BA7F',
            'accent' => '#28A745'
        ],
        'quick_bite' => [
            'id' => 'quick_bite',
            'name' => 'Lazy & Quick Bites',
            'short_name' => 'Quick Bites',
            'emoji' => '🍿',
            'icon' => 'fa-solid fa-bolt',
            'image' => 'assets/images/moods/quick_bite.jpg',
            'tagline' => 'Crispy kathi rolls, quick snacks, dosas & sodas delivered in under 15 mins',
            'badge_bg' => '#D1ECF1',
            'badge_color' => '#0C5460',
            'border_color' => '#BEE5EB',
            'accent' => '#17A2B8'
        ],
        'spicy' => [
            'id' => 'spicy',
            'name' => 'Fiery & Spicy',
            'short_name' => 'Fiery Spicy',
            'emoji' => '🔥',
            'icon' => 'fa-solid fa-fire',
            'image' => 'assets/images/moods/spicy.jpg',
            'tagline' => 'Authentic Andhra spices, fiery red chutneys & bold thrilling masalas',
            'badge_bg' => '#F8D7DA',
            'badge_color' => '#721C24',
            'border_color' => '#F5C6CB',
            'accent' => '#DC3545'
        ],
        'sweet' => [
            'id' => 'sweet',
            'name' => 'Sweet Tooth Craving',
            'short_name' => 'Sweet Craving',
            'emoji' => '💖',
            'icon' => 'fa-solid fa-heart',
            'image' => 'assets/images/moods/sweet.jpg',
            'tagline' => 'Royal rasmalai, hot gulab jamun with rabri & chilled Alphonso mango lassi',
            'badge_bg' => '#FCE4EC',
            'badge_color' => '#880E4F',
            'border_color' => '#F8BBD0',
            'accent' => '#E83E8C'
        ]
    ];

    return $defaults;
}

/**
 * Upload mood photo from multipart form data.
 */
function handleMoodPhotoUpload(string $fileKey = 'mood_image'): ?array
{
    return handleImageUpload($fileKey, 'moods', 'mood');
}

/**
 * Count how many dishes are tagged with a specific food mood.
 */
function getMoodDishCount(string $moodKey): int
{
    $moodKey = trim($moodKey);
    if (empty($moodKey)) return 0;
    $count = dbFetchVal("
        SELECT COUNT(*) FROM `menu_items` 
        WHERE `is_active` = 1
          AND FIND_IN_SET(:key, REPLACE(`mood`, ' ', '')) > 0
    ", [':key' => $moodKey]);
    return (int)$count;
}

/**
 * Return all dishes assigned to a specific mood.
 */
function getDishesByMood(string $moodKey): array
{
    $moodKey = trim($moodKey);
    if (empty($moodKey)) return [];
    return dbFetchAll("
        SELECT m.*, c.category_name 
        FROM `menu_items` m
        LEFT JOIN `categories` c ON m.category_id = c.category_id
        WHERE m.is_active = 1
          AND FIND_IN_SET(:key, REPLACE(m.`mood`, ' ', '')) > 0
        ORDER BY m.item_name ASC
    ", [':key' => $moodKey]);
}

/**
 * Assign an exact list of dishes to a mood (updates menu_items.mood accordingly).
 */
function setMoodDishes(string $moodKey, array $itemIds): bool
{
    $moodKey = trim($moodKey);
    if (empty($moodKey)) return false;

    $allDishes = dbFetchAll("SELECT `item_id`, `mood` FROM `menu_items` WHERE `is_active` = 1");
    $selectedMap = array_flip(array_map('intval', $itemIds));

    foreach ($allDishes as $d) {
        $id = (int)$d['item_id'];
        $currentMoods = array_filter(array_map('trim', explode(',', $d['mood'] ?? '')));
        $hasMood = in_array($moodKey, $currentMoods, true);
        $shouldHave = isset($selectedMap[$id]);

        if ($shouldHave && !$hasMood) {
            $currentMoods[] = $moodKey;
            $newMoodStr = implode(',', array_unique($currentMoods));
            dbQuery("UPDATE `menu_items` SET `mood` = :m WHERE `item_id` = :id", [
                ':m' => $newMoodStr,
                ':id' => $id
            ]);
        } elseif (!$shouldHave && $hasMood) {
            $currentMoods = array_values(array_filter($currentMoods, fn(string $m): bool => $m !== $moodKey));
            $newMoodStr = !empty($currentMoods) ? implode(',', array_unique($currentMoods)) : 'comfort';
            dbQuery("UPDATE `menu_items` SET `mood` = :m WHERE `item_id` = :id", [
                ':m' => $newMoodStr,
                ':id' => $id
            ]);
        }
    }
    return true;
}

/**
 * Add a single dish to a mood.
 */
function addDishToMood(int $itemId, string $moodKey): bool
{
    $moodKey = trim($moodKey);
    if (empty($moodKey) || $itemId <= 0) return false;
    $d = dbFetchOne("SELECT `mood` FROM `menu_items` WHERE `item_id` = :id", [':id' => $itemId]);
    if (!$d) return false;
    $currentMoods = array_filter(array_map('trim', explode(',', $d['mood'] ?? '')));
    if (!in_array($moodKey, $currentMoods, true)) {
        $currentMoods[] = $moodKey;
        $newMoodStr = implode(',', array_unique($currentMoods));
        dbQuery("UPDATE `menu_items` SET `mood` = :m WHERE `item_id` = :id", [
            ':m' => $newMoodStr,
            ':id' => $itemId
        ]);
    }
    return true;
}

/**
 * Remove a single dish from a mood.
 */
function removeDishFromMood(int $itemId, string $moodKey): bool
{
    $moodKey = trim($moodKey);
    if (empty($moodKey) || $itemId <= 0) return false;
    $d = dbFetchOne("SELECT `mood` FROM `menu_items` WHERE `item_id` = :id", [':id' => $itemId]);
    if (!$d) return false;
    $currentMoods = array_filter(array_map('trim', explode(',', $d['mood'] ?? '')));
    $currentMoods = array_values(array_filter($currentMoods, fn(string $m): bool => $m !== $moodKey));
    $newMoodStr = !empty($currentMoods) ? implode(',', array_unique($currentMoods)) : 'comfort';
    dbQuery("UPDATE `menu_items` SET `mood` = :m WHERE `item_id` = :id", [
        ':m' => $newMoodStr,
        ':id' => $itemId
    ]);
    return true;
}

/**
 * Get mood definition by key or comma-separated string (returns the first matching primary mood)
 */
function getDishPrimaryMood(?string $moodStr): ?array
{
    if (empty($moodStr)) {
        return null;
    }
    $moods = getFoodMoods();
    $parts = explode(',', $moodStr);
    foreach ($parts as $p) {
        $p = trim($p);
        if (isset($moods[$p])) {
            return $moods[$p];
        }
    }
    return null;
}


/**
 * Safely handles image photo upload for food dishes or categories from multipart file input.
 * 
 * @param string $fileKey Key name in $_FILES
 * @param string $subfolder Subdirectory in assets/uploads/ ('dishes' or 'categories')
 * @param string $prefix Filename prefix ('dish' or 'cat')
 * @return array|null Returns ['success' => true, 'url' => ..., 'filename' => ...] on success, 
 *                    ['error' => ...] on validation failure, 
 *                    or null if no file was uploaded.
 */
function handleImageUpload(string $fileKey, string $subfolder = 'dishes', string $prefix = 'img'): ?array
{
    if (!isset($_FILES[$fileKey]) || empty($_FILES[$fileKey]['name'])) {
        return null;
    }

    $file = $_FILES[$fileKey];

    // Check for upload error codes
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded photo exceeds the server upload limit (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded photo exceeds the form MAX_FILE_SIZE limit.',
            UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially received.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk on server.',
            UPLOAD_ERR_EXTENSION  => 'A server extension stopped the file upload.'
        ];
        return ['error' => $uploadErrors[$file['error']] ?? 'File upload error occurred (code ' . $file['error'] . ').'];
    }

    // Size limit: 5MB
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['error' => 'Photo file size exceeds the 5MB limit. Please upload an image under 5MB.'];
    }

    // Extension validation
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowedExts, true)) {
        return ['error' => 'Invalid file format (.' . $ext . '). Allowed image formats: JPG, JPEG, PNG, WEBP, GIF.'];
    }

    // MIME type check
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $detectedMime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } elseif (function_exists('mime_content_type')) {
        $detectedMime = mime_content_type($file['tmp_name']);
    }

    if ($detectedMime && !in_array(strtolower($detectedMime), $allowedMimes, true)) {
        return ['error' => 'Invalid image content (' . htmlspecialchars($detectedMime) . '). Please upload a valid image file.'];
    }

    // Verify it is a valid image using getimagesize
    $imgInfo = @getimagesize($file['tmp_name']);
    if ($imgInfo === false) {
        return ['error' => 'The uploaded file is not a valid image.'];
    }

    // Sanitize subfolder name
    $subfolder = preg_replace('/[^a-zA-Z0-9_-]/', '', $subfolder) ?: 'dishes';

    // Destination directory: pooja_project/assets/uploads/<subfolder>/
    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $subfolder . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return ['error' => 'Could not create uploads directory on the server.'];
        }
    }

    // Generate safe, collision-resistant unique filename
    $uniqueName = $prefix . '_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $targetPath = $uploadDir . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['error' => 'Failed to save uploaded photo to storage.'];
    }

    $publicUrl = ASSETS_URL . '/uploads/' . $subfolder . '/' . $uniqueName;
    return [
        'success' => true,
        'url' => $publicUrl,
        'filename' => $uniqueName
    ];
}

/**
 * Safely handles food / dish photo upload from HTML form multipart file input.
 * 
 * @param string $fileKey Key name in $_FILES (default: 'dish_image')
 * @return array|null
 */
function handleFoodPhotoUpload(string $fileKey = 'dish_image'): ?array
{
    return handleImageUpload($fileKey, 'dishes', 'dish');
}

/**
 * Safely handles category photo upload from HTML form multipart file input.
 * 
 * @param string $fileKey Key name in $_FILES (default: 'category_image')
 * @return array|null
 */
function handleCategoryPhotoUpload(string $fileKey = 'category_image'): ?array
{
    return handleImageUpload($fileKey, 'categories', 'cat');
}

/**
 * Render star icons HTML string (e.g. ⭐⭐⭐⭐⭐)
 * 
 * @param float $rating Rating value between 1 and 5
 * @param int $maxStars Maximum number of stars (default: 5)
 * @param string $starClass CSS class for active stars
 * @return string HTML containing star icons
 */
function renderStarRatingHtml(float $rating, int $maxStars = 5, string $starClass = 'text-warning'): string
{
    $fullStars = (int)floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.35 ? 1 : 0;
    $emptyStars = max(0, $maxStars - $fullStars - $halfStar);

    $html = '<span class="star-rating-display d-inline-flex align-items-center gap-1" title="' . number_format($rating, 1) . ' out of 5 stars">';
    for ($i = 0; $i < $fullStars; $i++) {
        $html .= '<i class="bi bi-star-fill ' . $starClass . '"></i>';
    }
    if ($halfStar) {
        $html .= '<i class="bi bi-star-half ' . $starClass . '"></i>';
    }
    for ($i = 0; $i < $emptyStars; $i++) {
        $html .= '<i class="bi bi-star text-muted opacity-50"></i>';
    }
    $html .= '</span>';
    return $html;
}

/**
 * Fetch customer reviews for a specific dish
 *
 * @param int $itemId Dish item ID
 * @param int $limit Maximum number of reviews
 * @return array
 */
function getDishReviews(int $itemId, int $limit = 10): array
{
    return dbFetchAll("
        SELECT r.*, COALESCE(u.full_name, 'Foodie Customer') AS customer_name 
        FROM `dish_reviews` r
        LEFT JOIN `users` u ON r.user_id = u.user_id
        WHERE r.item_id = :iid
        ORDER BY r.created_at DESC
        LIMIT :limit
    ", [':iid' => $itemId, ':limit' => $limit]);
}

/**
 * Format relative elapsed time into human-friendly string (e.g. "5m ago", "2h ago", "3d ago")
 * avoids showing raw numbers like "26792m ago" which cause cognitive fatigue.
 */
function formatTimeAgo(int|string|null $datetime): string
{
    if (empty($datetime)) {
        return 'Just now';
    }
    $timestamp = is_numeric($datetime) ? (int)$datetime : (int)(strtotime((string)$datetime) ?: 0);
    if ($timestamp <= 0) {
        return 'Recently';
    }
    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'Just now';
    }
    $minutes = (int)round($diff / 60);
    if ($minutes < 60) {
        return $minutes . 'm ago';
    }
    $hours = (int)floor($minutes / 60);
    if ($hours < 24) {
        return $hours . 'h ago';
    }
    $days = (int)floor($hours / 24);
    if ($days < 30) {
        return $days . 'd ago';
    }
    return date('d M Y', $timestamp);
}

/**
 * Format elapsed minutes into clean human-friendly string
 */
function formatMinutesAgo(int $minutes): string
{
    if ($minutes <= 0) {
        return 'Just now';
    }
    if ($minutes < 60) {
        return $minutes . 'm ago';
    }
    $hours = (int)floor($minutes / 60);
    $remMin = $minutes % 60;
    if ($hours < 24) {
        return $remMin > 0 ? "{$hours}h {$remMin}m ago" : "{$hours}h ago";
    }
    $days = (int)floor($hours / 24);
    return "{$days}d ago";
}

/**
 * Record a money transaction between customer and store (Online, COD, Refund, Salary)
 */
function recordMoneyTransaction(
    ?int $orderId,
    ?int $userId,
    string $category,
    string $flowDirection,
    string $paymentMethod,
    float $amount,
    string $status = 'COMPLETED',
    ?string $referenceId = null,
    ?string $handledBy = null,
    ?string $notes = null
): int
{
    $txnNum = 'TXN-' . date('Ym') . '-' . str_pad((string)random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
    dbQuery("
        INSERT INTO `money_transactions` 
        (`txn_number`, `order_id`, `user_id`, `txn_category`, `flow_direction`, `payment_method`, `amount`, `status`, `reference_id`, `handled_by`, `notes`, `created_at`) 
        VALUES (:tnum, :oid, :uid, :cat, :flow, :method, :amt, :st, :ref, :hby, :notes, NOW())
    ", [
        ':tnum'   => $txnNum,
        ':oid'    => $orderId,
        ':uid'    => $userId,
        ':cat'    => $category,
        ':flow'   => $flowDirection,
        ':method' => $paymentMethod,
        ':amt'    => $amount,
        ':st'     => $status,
        ':ref'    => $referenceId,
        ':hby'    => $handledBy,
        ':notes'  => $notes
    ]);
    return (int)dbFetchVal("SELECT LAST_INSERT_ID()");
}

/**
 * =============================================================================
 * FLEET LOGISTICS: ONE DRIVER ONE DELIVERY ENGINE
 * =============================================================================
 */

/**
 * Get active in-transit delivery for a specific rider (Enforces: 1 driver 1 delivery)
 * Returns the single active order record or null if rider is free.
 */
function buildDriverDispatchFilter(int $riderId, ?string $riderName = null): array
{
    $whereRider = "d.rider_id = :rid";
    $params = [':rid' => $riderId];

    if (!empty($riderName)) {
        $firstWord = trim(explode(' ', trim($riderName))[0]);
        if (strlen($firstWord) >= 3) {
            $whereRider = "(d.rider_id = :rid OR (d.rider_id IS NULL AND d.delivery_partner_name LIKE :rname))";
            $params[':rname'] = '%' . $firstWord . '%';
        }
    }

    return [$whereRider, $params];
}

function getDriverActiveDelivery(int $riderId, ?string $riderName = null, bool $forUpdate = false): ?array
{
    [$whereRider, $params] = buildDriverDispatchFilter($riderId, $riderName);

    if ($forUpdate) {
        $sql = "
            SELECT o.order_id, o.order_number, o.order_status,
                   d.dispatch_id, d.rider_id, d.delivery_partner_name, d.delivery_status
            FROM `orders` o
            JOIN `delivery_dispatches` d ON o.order_id = d.order_id
            WHERE o.order_status = 'OUT_FOR_DELIVERY'
              AND d.delivery_status = 'IN_TRANSIT'
              AND {$whereRider}
            ORDER BY d.dispatched_at DESC
            LIMIT 1
            FOR UPDATE
        ";
        return dbFetchOne($sql, $params);
    }

    $sql = "
        SELECT o.*, u.full_name AS customer_name, u.phone_number AS customer_phone,
               a.address_line, a.landmark, a.pincode,
               p.payment_mode, p.payment_status, p.amount_paid,
               d.dispatch_id, d.rider_id, d.delivery_partner_name, d.delivery_partner_phone,
               d.delivery_otp, d.delivery_status, d.dispatched_at,
               d.current_latitude, d.current_longitude, d.route_progress, d.distance_remaining_km, d.estimated_minutes_remaining
        FROM `orders` o
        JOIN `users` u ON o.user_id = u.user_id
        LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
        LEFT JOIN `payments` p ON o.order_id = p.order_id
        JOIN `delivery_dispatches` d ON o.order_id = d.order_id
        WHERE o.order_status = 'OUT_FOR_DELIVERY'
          AND d.delivery_status = 'IN_TRANSIT'
          AND {$whereRider}
        ORDER BY d.dispatched_at DESC
        LIMIT 1
    ";

    return dbFetchOne($sql, $params);
}

/**
 * Checks if a driver is currently busy with another active delivery.
 * Returns the active order if busy, or null if available.
 */
function isDriverBusy(int $riderId, ?string $riderName = null, ?int $excludeOrderId = null, bool $forUpdate = false): ?array
{
    $active = getDriverActiveDelivery($riderId, $riderName, $forUpdate);
    if ($active && $excludeOrderId && (int)$active['order_id'] === $excludeOrderId) {
        return null;
    }
    return $active;
}

/**
 * Fetch all registered delivery riders with their active delivery status
 */
function getAllDeliveryRiders(): array
{
    $riders = dbFetchAll("
        SELECT u.user_id, u.full_name, u.phone_number, u.email,
               COALESCE(sp.duty_status, 'ON_DUTY') AS duty_status,
               COALESCE(sp.vehicle_details, 'Electric Bike') AS vehicle_details,
               COALESCE(sp.rating, 4.8) AS rating,
               COALESCE(sp.cod_balance, 0.00) AS cod_balance
        FROM `users` u
        LEFT JOIN `staff_profiles` sp ON u.user_id = sp.user_id
        WHERE u.role_id = 4
        ORDER BY u.user_id ASC
    ");

    foreach ($riders as &$r) {
        $active = getDriverActiveDelivery((int)$r['user_id'], $r['full_name']);
        if ($active) {
            $r['is_busy'] = true;
            $r['active_order_id'] = (int)$active['order_id'];
            $r['active_order_number'] = $active['order_number'] ?? ('#ZIP-' . $active['order_id']);
        } else {
            $r['is_busy'] = false;
            $r['active_order_id'] = null;
            $r['active_order_number'] = null;
        }
    }
    unset($r);

    return $riders;
}

/**
 * Find the next available on-duty delivery rider (0 active deliveries)
 */
function getAvailableDeliveryPartner(): ?array
{
    $allRiders = getAllDeliveryRiders();
    foreach ($allRiders as $r) {
        if ($r['duty_status'] === 'ON_DUTY' && empty($r['is_busy'])) {
            return $r;
        }
    }
    return null;
}

/**
 * Compute the canonical state fingerprint for Kitchen Display System (KDS) & Delivery live synchronization
 * Guarantees identical hash calculation across kitchen/index.php, kitchen/ajax/poll-orders.php, and api/live-stream.php.
 */
function getKdsStateHash(): string
{
    $countNew = (int)dbFetchVal("SELECT COUNT(*) FROM `orders` WHERE `order_status` IN ('PLACED', 'CONFIRMED')");
    $countCooking = (int)dbFetchVal("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'COOKING'");
    $countReady = (int)dbFetchVal("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'READY'");
    $countOutForDelivery = (int)dbFetchVal("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'OUT_FOR_DELIVERY'");
    $countDelivered = (int)dbFetchVal("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'DELIVERED'");
    
    $latestAudit = dbFetchOne("SELECT MAX(audit_id) AS max_audit, MAX(change_timestamp) AS max_time FROM `order_audit_trail`");
    $latestDispatch = dbFetchOne("SELECT MAX(dispatch_id) AS max_disp, MAX(assigned_at) AS max_assigned, MAX(dispatched_at) AS max_dispatched, MAX(delivered_at) AS max_delivered FROM `delivery_dispatches`");

    // Fingerprint of ready orders and their assigned riders to detect allocation changes
    $readyAllocFingerprint = (string)dbFetchVal("
        SELECT GROUP_CONCAT(CONCAT(o.order_id, ':', COALESCE(d.rider_id, 0), ':', COALESCE(d.delivery_status, '')) ORDER BY o.order_id SEPARATOR '|')
        FROM `orders` o
        LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
        WHERE o.order_status = 'READY'
    ");

    $failedDispatches = (int)dbFetchVal("SELECT COUNT(*) FROM `delivery_dispatches` WHERE `delivery_status` = 'FAILED'");
    $preppedItemsCount = 0;
    try {
        $preppedItemsCount = (int)dbFetchVal("
            SELECT COALESCE(SUM(oi.is_prepped), 0)
            FROM `order_items` oi
            JOIN `orders` o ON oi.order_id = o.order_id
            WHERE o.order_status IN ('PLACED', 'CONFIRMED', 'COOKING')
        ");
    } catch (Throwable $e) {
        $preppedItemsCount = 0;
    }

    return md5(json_encode([
        'new' => $countNew,
        'cooking' => $countCooking,
        'ready' => $countReady,
        'out' => $countOutForDelivery,
        'delivered' => $countDelivered,
        'failed_disp' => $failedDispatches,
        'prepped_items' => $preppedItemsCount,
        'max_audit' => $latestAudit['max_audit'] ?? 0,
        'audit_time' => $latestAudit['max_time'] ?? '',
        'max_disp' => $latestDispatch['max_disp'] ?? 0,
        'disp_assigned' => $latestDispatch['max_assigned'] ?? '',
        'disp_dispatched' => $latestDispatch['max_dispatched'] ?? '',
        'disp_delivered' => $latestDispatch['max_delivered'] ?? '',
        'ready_alloc' => $readyAllocFingerprint
    ]));
}

/**
 * System Automated Parcel Allocation Engine:
 * Allocates READY parcels to available delivery drivers strictly on a First-Come, First-Served (FCFS / FIFO) basis
 * based on when the food preparation was finished (`ready_at`).
 *
 * Rules:
 * 1. "first item that is prepared should be first allocated"
 * 2. 1 Driver 1 Delivery Policy: only drivers who are currently free (0 active deliveries) can be allocated.
 * 3. Driver must be ON_DUTY.
 * 4. A busy or offline driver CANNOT hold an allocated ready parcel; invalid assignments are reset.
 * 5. Each available free driver is allocated at most 1 ready parcel at a time.
 */
function autoAllocateReadyOrdersFcfs(): array
{
    $pdo = getDbConnection();
    $inOuterTx = $pdo && $pdo->inTransaction();

    try {
        return dbTransactional(function (): array {
            // 1. Acquire locks on READY orders in deterministic PK order to prevent deadlocks
            $readyRows = dbFetchAll("
                SELECT `order_id`, `order_number`, `ready_at`, `placed_at`
                FROM `orders`
                WHERE `order_status` = 'READY'
                ORDER BY `order_id` ASC
                FOR UPDATE
            ");

            if (empty($readyRows)) {
                $allRiders = getAllDeliveryRiders();
                $availCount = 0;
                foreach ($allRiders as $r) {
                    if ($r['duty_status'] === 'ON_DUTY' && empty($r['is_busy'])) {
                        $availCount++;
                    }
                }
                return ['allocated' => 0, 'available_riders' => $availCount, 'message' => 'No ready parcels waiting in queue.'];
            }

            // Lock existing dispatch records for those READY orders in ascending order_id order
            $readyIds = array_values(array_map('intval', array_column($readyRows, 'order_id')));
            $readyPlaceholders = implode(',', array_fill(0, count($readyIds), '?'));
            $dispatchRows = dbFetchAll(
                "SELECT `order_id`, `dispatch_id`, `rider_id`, `delivery_status` FROM `delivery_dispatches` WHERE `order_id` IN ({$readyPlaceholders}) ORDER BY `order_id` ASC FOR UPDATE",
                $readyIds
            );

            $dispatchByOrder = [];
            foreach ($dispatchRows as $dr) {
                $dispatchByOrder[(int)$dr['order_id']] = $dr;
            }

            $readyParcels = [];
            foreach ($readyRows as $rr) {
                $oid = (int)$rr['order_id'];
                $disp = $dispatchByOrder[$oid] ?? null;
                $readyParcels[] = [
                    'order_id' => $oid,
                    'order_number' => $rr['order_number'],
                    'ready_at' => $rr['ready_at'],
                    'placed_at' => $rr['placed_at'],
                    'dispatch_id' => $disp['dispatch_id'] ?? null,
                    'rider_id' => $disp['rider_id'] ?? null,
                    'delivery_status' => $disp['delivery_status'] ?? null,
                ];
            }

            // Re-sort in strict FCFS order (first ready first) after deterministic PK lock acquisition
            usort($readyParcels, static function (array $a, array $b): int {
                $tA = $a['ready_at'] ?: $a['placed_at'];
                $tB = $b['ready_at'] ?: $b['placed_at'];
                $cmp = strcmp((string)$tA, (string)$tB);
                return $cmp !== 0 ? $cmp : ($a['order_id'] <=> $b['order_id']);
            });

            // 2. Lock delivery rider rows and evaluate live busy status under transaction locks
            dbFetchAll("SELECT `user_id` FROM `users` WHERE `role_id` = 4 ORDER BY `user_id` ASC FOR UPDATE");
            $riders = dbFetchAll("
                SELECT u.user_id, u.full_name, u.phone_number, u.email,
                       COALESCE(sp.duty_status, 'ON_DUTY') AS duty_status
                FROM `users` u
                LEFT JOIN `staff_profiles` sp ON u.user_id = sp.user_id
                WHERE u.role_id = 4
                ORDER BY u.user_id ASC
            ");

            $availableRiders = [];
            $busyRiderIds = [];
            foreach ($riders as $r) {
                $rId = (int)$r['user_id'];
                $active = getDriverActiveDelivery($rId, $r['full_name'], true);
                if ($r['duty_status'] === 'ON_DUTY' && !$active) {
                    $availableRiders[] = $r;
                } else {
                    $busyRiderIds[] = $rId;
                }
            }

            // 3. Purge invalid allocations from READY parcels
            $assignedRiderIds = [];
            foreach ($readyParcels as &$parcel) {
                $currentRiderId = !empty($parcel['rider_id']) ? (int)$parcel['rider_id'] : null;
                if ($currentRiderId) {
                    if (in_array($currentRiderId, $busyRiderIds, true) || in_array($currentRiderId, $assignedRiderIds, true)) {
                        dbQuery("
                            UPDATE `delivery_dispatches` 
                            SET `rider_id` = NULL, 
                                `delivery_partner_name` = 'Zippy Express Rider', 
                                `delivery_partner_phone` = '+91 98765 43210', 
                                `delivery_status` = 'ASSIGNED' 
                            WHERE `order_id` = :oid
                        ", [':oid' => $parcel['order_id']]);
                        $parcel['rider_id'] = null;
                        $parcel['delivery_status'] = 'ASSIGNED';
                    } else {
                        $assignedRiderIds[] = $currentRiderId;
                    }
                }
            }
            unset($parcel);

            // 4. If no available free drivers, all remaining ready parcels stay in FCFS queue
            if (empty($availableRiders)) {
                return ['allocated' => count($assignedRiderIds), 'available_riders' => 0, 'message' => 'All riders are busy. Parcels queued in FCFS order.'];
            }

            $allocatedCount = 0;
            $allocations = [];

            // 5. Match unallocated ready parcels to remaining free available drivers in FCFS order
            foreach ($readyParcels as $parcel) {
                $currentRiderId = !empty($parcel['rider_id']) ? (int)$parcel['rider_id'] : null;

                if ($currentRiderId && in_array($currentRiderId, $assignedRiderIds, true)) {
                    continue;
                }

                $matchedRider = null;
                foreach ($availableRiders as $r) {
                    $rId = (int)$r['user_id'];
                    if (!in_array($rId, $assignedRiderIds, true)) {
                        $matchedRider = $r;
                        $assignedRiderIds[] = $rId;
                        break;
                    }
                }

                if (!$matchedRider) {
                    break;
                }

                if (!empty($parcel['dispatch_id'])) {
                    dbQuery("
                        UPDATE `delivery_dispatches`
                        SET `rider_id` = :rid,
                            `delivery_partner_name` = :rname,
                            `delivery_partner_phone` = :rphone,
                            `assigned_at` = NOW(),
                            `delivery_status` = 'ASSIGNED'
                        WHERE `order_id` = :oid
                    ", [
                        ':rid' => $matchedRider['user_id'],
                        ':rname' => $matchedRider['full_name'],
                        ':rphone' => $matchedRider['phone_number'],
                        ':oid' => $parcel['order_id']
                    ]);
                } else {
                    $newOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    dbQuery("
                        INSERT INTO `delivery_dispatches`
                        (`order_id`, `rider_id`, `delivery_partner_name`, `delivery_partner_phone`, `delivery_otp`, `assigned_at`, `delivery_status`)
                        VALUES (:oid, :rid, :rname, :rphone, :otp, NOW(), 'ASSIGNED')
                    ", [
                        ':oid' => $parcel['order_id'],
                        ':rid' => $matchedRider['user_id'],
                        ':rname' => $matchedRider['full_name'],
                        ':rphone' => $matchedRider['phone_number'],
                        ':otp' => $newOtp
                    ]);
                }

                $allocatedCount++;
                $allocations[] = [
                    'order_id' => $parcel['order_id'],
                    'rider_id' => $matchedRider['user_id'],
                    'rider_name' => $matchedRider['full_name'],
                    'ready_at' => $parcel['ready_at']
                ];
            }

            return [
                'allocated' => $allocatedCount,
                'allocations' => $allocations
            ];
        });
    } catch (Throwable $e) {
        if ($inOuterTx) {
            throw $e;
        }
        error_log("autoAllocateReadyOrdersFcfs failed: " . $e->getMessage());
        return ['allocated' => 0, 'allocations' => [], 'error' => 'Allocation engine error.'];
    }
}

/**
 * Retrieve the specific parcel allocated to a driver in First-Come, First-Served basis.
 * Returns null if driver is already busy with an active in-transit delivery or no parcel is allocated.
 */
function getDriverAllocatedOrder(int $riderId): ?array
{
    $pdo = getDbConnection();
    $inOuterTx = $pdo && $pdo->inTransaction();

    try {
        return dbTransactional(function () use ($riderId): ?array {
            // 1. Run allocation engine inside the same transaction so READY orders + riders are locked cleanly
            autoAllocateReadyOrdersFcfs();

            // 2. Enforce 1 Driver 1 Delivery using transactional locks
            if (isDriverBusy($riderId, null, null, true)) {
                return null;
            }

            // 3. Find order with order_status = 'READY' and dispatch.rider_id = $riderId
            $allocated = dbFetchOne("
                SELECT o.*, u.full_name AS customer_name, u.phone_number AS customer_phone,
                       a.address_line, a.landmark, a.pincode,
                       p.payment_mode, p.amount_paid,
                       d.dispatch_id, d.delivery_otp, d.assigned_at, d.delivery_status
                FROM `orders` o
                JOIN `users` u ON o.user_id = u.user_id
                LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
                LEFT JOIN `payments` p ON o.order_id = p.order_id
                JOIN `delivery_dispatches` d ON o.order_id = d.order_id
                WHERE o.order_status = 'READY'
                  AND d.rider_id = :rid
                ORDER BY COALESCE(o.ready_at, o.placed_at) ASC, o.order_id ASC
                LIMIT 1
            ", [':rid' => $riderId]);

            // If none assigned specifically to this rider but unassigned ready orders exist, pick top of FCFS queue
            if (!$allocated) {
                $allocated = dbFetchOne("
                    SELECT o.*, u.full_name AS customer_name, u.phone_number AS customer_phone,
                           a.address_line, a.landmark, a.pincode,
                           p.payment_mode, p.amount_paid,
                           d.dispatch_id, d.delivery_otp, d.assigned_at, d.delivery_status
                    FROM `orders` o
                    JOIN `users` u ON o.user_id = u.user_id
                    LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
                    LEFT JOIN `payments` p ON o.order_id = p.order_id
                    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
                    WHERE o.order_status = 'READY'
                      AND (d.rider_id IS NULL OR d.rider_id = 0)
                    ORDER BY COALESCE(o.ready_at, o.placed_at) ASC, o.order_id ASC
                    LIMIT 1
                ");
                if ($allocated) {
                    $riderUser = dbFetchOne("SELECT * FROM `users` WHERE `user_id` = :uid FOR UPDATE", [':uid' => $riderId]);
                    if ($riderUser) {
                        if (!empty($allocated['dispatch_id'])) {
                            dbQuery("UPDATE `delivery_dispatches` SET `rider_id` = :rid, `delivery_partner_name` = :rname, `delivery_partner_phone` = :rphone, `assigned_at` = NOW(), `delivery_status` = 'ASSIGNED' WHERE `order_id` = :oid", [
                                ':rid' => $riderUser['user_id'],
                                ':rname' => $riderUser['full_name'],
                                ':rphone' => $riderUser['phone_number'],
                                ':oid' => $allocated['order_id']
                            ]);
                        } else {
                            $newOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                            dbQuery("INSERT INTO `delivery_dispatches` (`order_id`, `rider_id`, `delivery_partner_name`, `delivery_partner_phone`, `delivery_otp`, `assigned_at`, `delivery_status`) VALUES (:oid, :rid, :rname, :rphone, :otp, NOW(), 'ASSIGNED')", [
                                ':oid' => $allocated['order_id'],
                                ':rid' => $riderUser['user_id'],
                                ':rname' => $riderUser['full_name'],
                                ':rphone' => $riderUser['phone_number'],
                                ':otp' => $newOtp
                            ]);
                        }
                    }
                }
            }

            if ($allocated) {
                $items = dbFetchAll("
                    SELECT oi.*, m.item_name, m.is_veg 
                    FROM `order_items` oi
                    JOIN `menu_items` m ON oi.item_id = m.item_id
                    WHERE oi.order_id = :oid
                ", [':oid' => $allocated['order_id']]);
                $allocated['items'] = $items;
            }

            return $allocated;
        });
    } catch (Throwable $e) {
        if ($inOuterTx) {
            throw $e;
        }
        error_log("getDriverAllocatedOrder failed for rider {$riderId}: " . $e->getMessage());
        return null;
    }
}

/**
 * Consolidated Single-Query Order Lifecycle KPIs & Counts
 * Retrieves all order status counters in a single optimized database roundtrip.
 *
 * @return array Associative array of status counts
 */
function getOrderKpis(): array
{
    $row = dbFetchOne("
        SELECT 
            COUNT(*) AS total,
            COUNT(CASE WHEN order_status IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY') THEN 1 END) AS live,
            COUNT(CASE WHEN order_status IN ('CONFIRMED', 'COOKING') THEN 1 END) AS preparing,
            COUNT(CASE WHEN order_status = 'READY' THEN 1 END) AS ready,
            COUNT(CASE WHEN order_status = 'OUT_FOR_DELIVERY' THEN 1 END) AS delivering,
            COUNT(CASE WHEN order_status = 'DELIVERED' THEN 1 END) AS delivered,
            COUNT(CASE WHEN order_status = 'CANCELLED' THEN 1 END) AS cancelled,
            COUNT(CASE WHEN is_scheduled = 1 THEN 1 END) AS scheduled,
            COUNT(CASE WHEN is_scheduled = 1 AND order_status NOT IN ('DELIVERED', 'CANCELLED') THEN 1 END) AS scheduled_active
        FROM `orders`
    ");
    return [
        'total' => (int)($row['total'] ?? 0),
        'live' => (int)($row['live'] ?? 0),
        'preparing' => (int)($row['preparing'] ?? 0),
        'ready' => (int)($row['ready'] ?? 0),
        'delivering' => (int)($row['delivering'] ?? 0),
        'delivered' => (int)($row['delivered'] ?? 0),
        'cancelled' => (int)($row['cancelled'] ?? 0),
        'scheduled' => (int)($row['scheduled'] ?? 0),
        'scheduled_active' => (int)($row['scheduled_active'] ?? 0),
    ];
}

/**
 * Resolves a delivery rider user record by name, partial name, or user ID.
 * Accurately prioritizes the selected rider over any existing/fallback dispatch rider.
 *
 * @param string|int|null $riderIdentifier Selected rider full name, substring, or user ID
 * @param int|null $fallbackRiderId Optional fallback rider_id from dispatch if no selection
 * @return array|null Rider user record or null
 */
function findRiderUser(int|string|null $riderIdentifier = null, ?int $fallbackRiderId = null): ?array
{
    $identifier = !empty($riderIdentifier) ? trim((string)$riderIdentifier) : '';
    $riderUser = null;

    if (!empty($identifier)) {
        // 1. If numeric ID passed:
        if (is_numeric($identifier) && (int)$identifier > 0) {
            $riderUser = dbFetchOne("SELECT user_id, full_name, phone_number FROM `users` WHERE role_id = 4 AND user_id = :uid", [
                ':uid' => (int)$identifier
            ]);
        }

        // 2. Look up by exact name or substring match
        if (!$riderUser) {
            $riderUser = dbFetchOne("SELECT user_id, full_name, phone_number FROM `users` WHERE role_id = 4 AND (full_name = :fn OR full_name LIKE :likefn)", [
                ':fn' => $identifier,
                ':likefn' => '%' . $identifier . '%'
            ]);
        }

        // 3. Look up by first word (e.g. "Rajesh" from "Rajesh Varma (Rider)")
        if (!$riderUser) {
            $firstWord = trim(explode(' ', $identifier)[0]);
            if (strlen($firstWord) >= 3) {
                $riderUser = dbFetchOne("SELECT user_id, full_name, phone_number FROM `users` WHERE role_id = 4 AND full_name LIKE :fn", [
                    ':fn' => '%' . $firstWord . '%'
                ]);
            }
        }
    }

    // 4. Fallback to existing dispatch rider if no rider was selected or found
    if (!$riderUser && !empty($fallbackRiderId) && $fallbackRiderId > 0) {
        $riderUser = dbFetchOne("SELECT user_id, full_name, phone_number FROM `users` WHERE role_id = 4 AND user_id = :uid", [
            ':uid' => (int)$fallbackRiderId
        ]);
    }

    return $riderUser;
}

/**
 * Centralized Order Lifecycle State Machine Transition
 * Handles status updates, rider busy enforcement (One Driver One Delivery),
 * dispatch updates, financial ledger sync, stock replenishment, and immutable audit logs.
 *
 * @param int $orderId Order ID
 * @param string $newStatus Target order status
 * @param string|null $assignedRider Rider name or identifier
 * @param int|null $actorUserId ID of user triggering transition
 * @return array ['success' => bool, 'message' => string, ...]
 */
function transitionOrderStatus(int $orderId, string $newStatus, ?string $assignedRider = null, ?int $actorUserId = null): array
{
    $newStatus = strtoupper(trim($newStatus));
    if ($orderId <= 0 || empty($newStatus)) {
        return ['success' => false, 'error' => 'Missing or invalid order ID or target status.'];
    }

    $pdo = getDbConnection();
    $inOuterTx = $pdo && $pdo->inTransaction();

    try {
        return dbTransactional(function () use ($orderId, $newStatus, $assignedRider, $actorUserId): array {
            // Canonical lock order: orders -> delivery_dispatches -> users (rider)
            $currentOrder = dbFetchOne("SELECT * FROM `orders` WHERE `order_id` = :id FOR UPDATE", [':id' => $orderId]);
            if (!$currentOrder) {
                return ['success' => false, 'error' => "Order #ZIP-{$orderId} not found."];
            }

            $oldStatus = $currentOrder['order_status'];

            // Guard against duplicate transitions (e.g., double-cancel restock or double-delivery)
            if ($oldStatus === $newStatus) {
                return [
                    'success' => true,
                    'already_in_status' => true,
                    'order_id' => $orderId,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'badge_html' => getStatusBadge($newStatus),
                    'message' => "Order #ZIP-{$orderId} is already {$newStatus}."
                ];
            }

            // Find & lock dispatch record; enforce 1 Driver 1 Delivery policy
            $dispatch = dbFetchOne("SELECT * FROM `delivery_dispatches` WHERE `order_id` = :id FOR UPDATE", [':id' => $orderId]);
            $fallbackId = !empty($dispatch['rider_id']) ? (int)$dispatch['rider_id'] : null;
            $fallbackName = $dispatch['delivery_partner_name'] ?? null;
            $riderUser = findRiderUser($assignedRider, $fallbackId);
            if (!$riderUser && !empty($fallbackName)) {
                $riderUser = findRiderUser($fallbackName, $fallbackId);
            }

            if ($newStatus === 'OUT_FOR_DELIVERY' && $riderUser) {
                dbFetchOne("SELECT `user_id` FROM `users` WHERE `user_id` = :uid FOR UPDATE", [':uid' => (int)$riderUser['user_id']]);
                $busyOrder = isDriverBusy((int)$riderUser['user_id'], $riderUser['full_name'], $orderId, true);
                if ($busyOrder) {
                    return [
                        'success' => false,
                        'error' => "Handover Blocked (1 Driver, 1 Delivery Policy): Driver {$riderUser['full_name']} is currently busy delivering Order #ZIP-{$busyOrder['order_id']}. Each driver can only handle one delivery at a time."
                    ];
                }
            }

            // 1. Advance order status
            if ($newStatus === 'READY') {
                dbQuery("UPDATE `orders` SET `order_status` = :st, `ready_at` = COALESCE(ready_at, NOW()) WHERE `order_id` = :id", [
                    ':st' => $newStatus,
                    ':id' => $orderId
                ]);
            } else {
                dbQuery("UPDATE `orders` SET `order_status` = :st WHERE `order_id` = :id", [
                    ':st' => $newStatus,
                    ':id' => $orderId
                ]);
            }

            // 2. Insert into immutable order audit trail
            $auditUid = $actorUserId ?? (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
            dbQuery("
                INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`) 
                VALUES (:oid, :old, :new, :uid, NOW())
            ", [
                ':oid' => $orderId,
                ':old' => $oldStatus,
                ':new' => $newStatus,
                ':uid' => $auditUid
            ]);

            // 3. Update delivery partner dispatch record
            if ($riderUser) {
                if ($dispatch) {
                    dbQuery("
                        UPDATE `delivery_dispatches` 
                        SET `rider_id` = :rid,
                            `delivery_partner_name` = :rname,
                            `delivery_partner_phone` = :rphone 
                        WHERE `order_id` = :oid
                    ", [
                        ':rid' => $riderUser['user_id'],
                        ':rname' => $riderUser['full_name'],
                        ':rphone' => $riderUser['phone_number'],
                        ':oid' => $orderId
                    ]);
                } else {
                    $newOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    dbQuery("
                        INSERT INTO `delivery_dispatches`
                        (`order_id`, `rider_id`, `delivery_partner_name`, `delivery_partner_phone`, `delivery_otp`, `assigned_at`, `delivery_status`)
                        VALUES (:oid, :rid, :rname, :rphone, :otp, NOW(), 'ASSIGNED')
                    ", [
                        ':oid' => $orderId,
                        ':rid' => $riderUser['user_id'],
                        ':rname' => $riderUser['full_name'],
                        ':rphone' => $riderUser['phone_number'],
                        ':otp' => $newOtp
                    ]);
                }
            } elseif (!empty($assignedRider)) {
                if ($dispatch) {
                    dbQuery("UPDATE `delivery_dispatches` SET `delivery_partner_name` = :rname WHERE `order_id` = :oid", [
                        ':rname' => $assignedRider,
                        ':oid' => $orderId
                    ]);
                } else {
                    $newOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    dbQuery("
                        INSERT INTO `delivery_dispatches`
                        (`order_id`, `delivery_partner_name`, `delivery_otp`, `assigned_at`, `delivery_status`)
                        VALUES (:oid, :rname, :otp, NOW(), 'ASSIGNED')
                    ", [
                        ':oid' => $orderId,
                        ':rname' => $assignedRider,
                        ':otp' => $newOtp
                    ]);
                }
            }

            // 4. Lifecycle-specific transition handlers
            if ($newStatus === 'OUT_FOR_DELIVERY') {
                $checkDisp = dbFetchOne("SELECT `dispatch_id` FROM `delivery_dispatches` WHERE `order_id` = :id FOR UPDATE", [':id' => $orderId]);
                if (!$checkDisp) {
                    $newOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    dbQuery("
                        INSERT INTO `delivery_dispatches`
                        (`order_id`, `delivery_partner_name`, `delivery_otp`, `assigned_at`, `dispatched_at`, `delivery_status`)
                        VALUES (:oid, 'Assigned Courier', :otp, NOW(), NOW(), 'IN_TRANSIT')
                    ", [
                        ':oid' => $orderId,
                        ':otp' => $newOtp
                    ]);
                }

                dbQuery("
                    UPDATE `delivery_dispatches` 
                    SET `dispatched_at` = NOW(), 
                        `delivery_status` = 'IN_TRANSIT',
                        `current_latitude` = COALESCE(current_latitude, 12.97840000),
                        `current_longitude` = COALESCE(current_longitude, 77.64080000),
                        `route_progress` = COALESCE(route_progress, 0),
                        `distance_remaining_km` = COALESCE(distance_remaining_km, 2.40),
                        `estimated_minutes_remaining` = COALESCE(estimated_minutes_remaining, 15)
                    WHERE `order_id` = :id
                ", [':id' => $orderId]);
            } elseif ($newStatus === 'DELIVERED') {
                dbQuery("UPDATE `delivery_dispatches` SET `delivered_at` = NOW(), `delivery_status` = 'DELIVERED' WHERE `order_id` = :id", [':id' => $orderId]);

                // Lock payment & COD ledger rows before marking settled
                $payRec = dbFetchOne("SELECT * FROM `payments` WHERE `order_id` = :id FOR UPDATE", [':id' => $orderId]);
                if ($payRec && $payRec['payment_mode'] === 'COD') {
                    dbFetchAll("SELECT `txn_id` FROM `money_transactions` WHERE `order_id` = :id AND `txn_category` = 'CUSTOMER_PAYMENT_COD' FOR UPDATE", [':id' => $orderId]);
                    dbQuery("UPDATE `payments` SET `payment_status` = 'SUCCESSFUL', `payment_timestamp` = NOW() WHERE `order_id` = :id", [':id' => $orderId]);
                    dbQuery("
                        UPDATE `money_transactions` 
                        SET `status` = 'COMPLETED', `handled_by` = :hby, `notes` = CONCAT(COALESCE(notes, ''), ' [Cash collected upon delivery completion]') 
                        WHERE `order_id` = :id AND `txn_category` = 'CUSTOMER_PAYMENT_COD'
                    ", [
                        ':hby' => 'Store Admin / Courier',
                        ':id'  => $orderId
                    ]);
                }

                awardLoyaltyPointsForOrder($orderId);
            } elseif ($newStatus === 'CANCELLED') {
                // Lock menu_items in ascending item_id order before restocking cancelled order items
                dbFetchAll("
                    SELECT DISTINCT m.item_id
                    FROM `menu_items` m
                    JOIN `order_items` oi ON m.item_id = oi.item_id
                    WHERE oi.order_id = :id
                    ORDER BY m.item_id ASC
                    FOR UPDATE
                ", [':id' => $orderId]);

                dbQuery("
                    UPDATE `menu_items` m
                    JOIN `order_items` oi ON m.item_id = oi.item_id
                    SET m.stock_quantity = m.stock_quantity + oi.quantity,
                        m.is_available = 1
                    WHERE oi.order_id = :id
                ", [':id' => $orderId]);

                // Lock and mark payment as refunded
                dbFetchOne("SELECT `payment_id` FROM `payments` WHERE `order_id` = :id FOR UPDATE", [':id' => $orderId]);
                dbQuery("
                    UPDATE `payments` 
                    SET `payment_status` = 'REFUNDED', `payment_timestamp` = NOW() 
                    WHERE `order_id` = :id
                ", [':id' => $orderId]);

                refundLoyaltyPointsForOrder($orderId);
            }

            // Automatically invoke FCFS allocation engine when order is ready or delivered
            if (in_array($newStatus, ['READY', 'DELIVERED'], true)) {
                autoAllocateReadyOrdersFcfs();
            }

            return [
                'success' => true,
                'order_id' => $orderId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'badge_html' => getStatusBadge($newStatus),
                'message' => "Order #ZIP-{$orderId} status advanced to {$newStatus}."
            ];
        });
    } catch (Throwable $e) {
        if ($inOuterTx) {
            throw $e;
        }
        error_log("transitionOrderStatus failed for order {$orderId} -> {$newStatus}: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Failed to update order status safely. Please try again.'
        ];
    }
}

/**
 * Retrieve a customer's most recent previous meal / completed order for 1-tap reordering.
 * Prioritizes delivered/completed orders, falling back to placed/confirmed if no delivered order exists.
 */
function getCustomerPreviousMeal(?int $userId): ?array
{
    if (!$userId) {
        return null;
    }

    // 1. Fetch the most recent delivered/completed order
    $order = dbFetchOne("
        SELECT o.*, 
               p.payment_mode, p.payment_status,
               ua.address_line, ua.landmark
        FROM `orders` o
        LEFT JOIN `payments` p ON o.order_id = p.order_id
        LEFT JOIN `user_addresses` ua ON o.address_id = ua.address_id
        WHERE o.user_id = :uid AND o.order_status = 'DELIVERED'
        ORDER BY o.placed_at DESC, o.order_id DESC
        LIMIT 1
    ", [':uid' => $userId]);

    // 2. Fallback to any non-cancelled order
    if (!$order) {
        $order = dbFetchOne("
            SELECT o.*, 
                   p.payment_mode, p.payment_status,
                   ua.address_line, ua.landmark
            FROM `orders` o
            LEFT JOIN `payments` p ON o.order_id = p.order_id
            LEFT JOIN `user_addresses` ua ON o.address_id = ua.address_id
            WHERE o.user_id = :uid AND o.order_status != 'CANCELLED'
            ORDER BY o.placed_at DESC, o.order_id DESC
            LIMIT 1
        ", [':uid' => $userId]);
    }

    // 3. Fallback to any past order if only cancelled orders exist
    if (!$order) {
        $order = dbFetchOne("
            SELECT o.*, 
                   p.payment_mode, p.payment_status,
                   ua.address_line, ua.landmark
            FROM `orders` o
            LEFT JOIN `payments` p ON o.order_id = p.order_id
            LEFT JOIN `user_addresses` ua ON o.address_id = ua.address_id
            WHERE o.user_id = :uid
            ORDER BY o.placed_at DESC, o.order_id DESC
            LIMIT 1
        ", [':uid' => $userId]);
    }

    if (!$order) {
        return null;
    }

    // Fetch order line items with live menu item details
    $items = dbFetchAll("
        SELECT oi.order_item_id, oi.order_id, oi.item_id, oi.quantity, oi.unit_price, oi.line_total,
               m.item_name, m.is_veg, m.price AS current_price, m.image_url, m.is_available, m.stock_quantity,
               c.category_name
        FROM `order_items` oi
        JOIN `menu_items` m ON oi.item_id = m.item_id
        LEFT JOIN `categories` c ON m.category_id = c.category_id
        WHERE oi.order_id = :oid
        ORDER BY oi.order_item_id ASC
    ", [':oid' => (int)$order['order_id']]);

    if (empty($items)) {
        return null;
    }

    $allInStock = true;
    $reorderPayload = [];
    foreach ($items as $it) {
        $inStock = ($it['is_available'] == 1 && $it['stock_quantity'] > 0);
        if (!$inStock) {
            $allInStock = false;
        }
        $reorderPayload[] = [
            'id' => (int)$it['item_id'],
            'name' => $it['item_name'],
            'price' => (float)$it['current_price'],
            'isVeg' => (bool)$it['is_veg'],
            'restaurant' => 'Meghana Foods',
            'imageUrl' => getDishImageUrl($it['image_url'] ?? ''),
            'qty' => (int)$it['quantity'],
            'inStock' => $inStock
        ];
    }

    return [
        'order' => $order,
        'items' => $items,
        'items_count' => count($items),
        'total_qty' => array_sum(array_column($items, 'quantity')),
        'all_in_stock' => $allInStock,
        'reorder_payload' => $reorderPayload,
        'reorder_json' => json_encode($reorderPayload)
    ];
}

/**
 * Retrieve all Customization Groups and active Options for a specific Dish
 */
function getDishCustomizationGroups(int $itemId, bool $onlyAvailable = false): array
{
    $groups = dbFetchAll("
        SELECT * FROM `dish_customization_groups`
        WHERE `item_id` = :id AND `is_active` = 1
        ORDER BY `display_order` ASC, `group_id` ASC
    ", [':id' => $itemId]);

    if (empty($groups)) {
        return [];
    }

    $groupIds = array_values(array_unique(array_filter(array_map('intval', array_column($groups, 'group_id')), static fn(int $gid): bool => $gid > 0)));
    if (empty($groupIds)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    $availFilter = $onlyAvailable ? " AND `is_available` = 1" : "";

    $options = dbFetchAll("
        SELECT * FROM `dish_customization_options`
        WHERE `group_id` IN ({$placeholders}) AND `is_active` = 1{$availFilter}
        ORDER BY `group_id` ASC, `display_order` ASC, `price` ASC, `option_id` ASC
    ", $groupIds);

    $optionsByGroup = [];
    foreach ($options as $opt) {
        $optionsByGroup[$opt['group_id']][] = $opt;
    }

    foreach ($groups as &$grp) {
        $grp['options'] = $optionsByGroup[$grp['group_id']] ?? [];
    }
    unset($grp);

    return $groups;
}

/**
 * Get map of customisation counts (groups & options) for all dishes
 */
function getAllDishesCustomizationCounts(): array
{
    $rows = dbFetchAll("
        SELECT g.item_id, COUNT(DISTINCT g.group_id) AS group_count, COUNT(o.option_id) AS option_count
        FROM `dish_customization_groups` g
        LEFT JOIN `dish_customization_options` o ON g.group_id = o.group_id AND o.is_active = 1
        WHERE g.is_active = 1
        GROUP BY g.item_id
    ");
    $map = [];
    foreach ($rows as $r) {
        $map[(int)$r['item_id']] = [
            'group_count' => (int)$r['group_count'],
            'option_count' => (int)$r['option_count']
        ];
    }
    return $map;
}

/**
 * Format customizations string into neat HTML badges
 */
function formatCustomizationsHtml(?string $customizations): string
{
    if (empty($customizations)) {
        return '';
    }
    $parts = explode(',', $customizations);
    $html = '<div class="d-flex flex-wrap gap-1 mt-1">';
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $isAddon = str_contains($p, '+₹');
        $badgeClass = $isAddon ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-light text-secondary border';
        $html .= '<span class="badge ' . $badgeClass . ' fw-medium" style="font-size: 0.7rem;"><i class="bi bi-sliders me-1"></i>' . e($p) . '</span>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Compute real-time Rider Earnings & Instant IMPS Payout Balance
 * Aggregates completed trips, rider contract base fare, distance surcharges, tips,
 * and subtracts disbursed Instant IMPS cashouts from `staff_salary_payouts`.
 */
function getRiderEarningsSummary(int $riderId, string $riderName = ''): array
{
    $firstWord = trim(explode(' ', trim($riderName !== '' ? $riderName : 'Rider'))[0]);

    // Fetch rider contract per-order base rate from staff_profiles
    $profile = dbFetchOne(
        "SELECT `hourly_or_order_rate` FROM `staff_profiles` WHERE `user_id` = :uid LIMIT 1",
        [':uid' => $riderId]
    );
    $baseRate = ($profile && (float)$profile['hourly_or_order_rate'] > 0)
        ? (float)$profile['hourly_or_order_rate']
        : 45.00;
    $distanceRate = 15.00;
    $tipRate = 20.00;

    // Count all completed deliveries assigned to this rider
    $deliveredCount = (int)(dbFetchVal("
        SELECT COUNT(*)
        FROM `orders` o
        LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
        WHERE o.order_status = 'DELIVERED'
          AND (d.rider_id = :rid OR (d.rider_id IS NULL AND d.delivery_partner_name LIKE :rname))
    ", [
        ':rid'   => $riderId,
        ':rname' => '%' . $firstWord . '%'
    ]) ?: 0);

    $baseFareTotal   = round($deliveredCount * $baseRate, 2);
    $distanceTotal   = round($deliveredCount * $distanceRate, 2);
    $tipsTotal       = round($deliveredCount * $tipRate, 2);
    $totalEarned     = round($baseFareTotal + $distanceTotal + $tipsTotal, 2);

    // Sum already settled Instant IMPS cashouts
    $totalCashedOut = (float)(dbFetchVal("
        SELECT COALESCE(SUM(`net_paid`), 0)
        FROM `staff_salary_payouts`
        WHERE `user_id` = :uid
          AND `payout_status` = 'PAID'
          AND (`transaction_reference` LIKE 'IMPS-%' OR `notes` LIKE '%Instant IMPS%')
    ", [':uid' => $riderId]) ?: 0.00);
    $totalCashedOut = round($totalCashedOut, 2);

    $availableBalance = max(0.00, round($totalEarned - $totalCashedOut, 2));

    $cashoutHistory = dbFetchAll("
        SELECT `payout_id`, `month_year`, `base_amount`, `incentives_amount`, `net_paid`,
               `payment_mode`, `transaction_reference`, `payout_status`, `paid_at`, `notes`
        FROM `staff_salary_payouts`
        WHERE `user_id` = :uid
          AND (`transaction_reference` LIKE 'IMPS-%' OR `notes` LIKE '%Instant IMPS%')
        ORDER BY `paid_at` DESC, `payout_id` DESC
        LIMIT 15
    ", [':uid' => $riderId]);

    return [
        'delivered_count'   => $deliveredCount,
        'base_rate'         => $baseRate,
        'distance_rate'     => $distanceRate,
        'tip_rate'          => $tipRate,
        'trip_payout_rate'  => $baseRate + $distanceRate + $tipRate,
        'base_fare_total'   => $baseFareTotal,
        'distance_total'    => $distanceTotal,
        'tips_total'        => $tipsTotal,
        'total_earned'      => $totalEarned,
        'total_cashed_out'  => $totalCashedOut,
        'available_balance' => $availableBalance,
        'cashout_history'   => $cashoutHistory
    ];
}

/**
 * Determine whether a delivery rider is legitimately assigned to the given order.
 */
function isRiderAssignedToOrder(int $orderId, int $riderId, ?string $riderName = null): bool
{
    if ($orderId <= 0 || $riderId <= 0) {
        return false;
    }

    $disp = dbFetchOne(
        "SELECT `rider_id`, `delivery_partner_name` FROM `delivery_dispatches` WHERE `order_id` = :oid LIMIT 1",
        [':oid' => $orderId]
    );
    if (!$disp) {
        return false;
    }

    $assignedId = (int)($disp['rider_id'] ?? 0);
    if ($assignedId > 0) {
        return $assignedId === $riderId;
    }

    if (!empty($riderName)) {
        $firstWord = trim(explode(' ', trim($riderName))[0]);
        if (strlen($firstWord) >= 3 && stripos((string)($disp['delivery_partner_name'] ?? ''), $firstWord) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Sanitize FAQ answer markup using a DOMDocument tag & attribute allowlist.
 * Strips script/iframe/svg elements, inline styles, on* event attributes, and unsafe URL schemes.
 */
function sanitizeFaqContent(string $input): string
{
    $trimmed = trim($input);
    if ($trimmed === '') {
        return '';
    }

    if (!preg_match('/<(?:p|br|ul|ol|li)\b[^>]*>/i', $trimmed)) {
        $trimmed = nl2br($trimmed, false);
    }

    if (!class_exists('DOMDocument')) {
        return nl2br(e(strip_tags($trimmed)), false);
    }

    $allowedTags = ['p', 'br', 'b', 'strong', 'i', 'em', 'u', 'ul', 'ol', 'li', 'code', 'pre', 'a', 'span'];
    $dropEntirely = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'noscript', 'template', 'link', 'meta'];

    $prevErrors = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="zippy-faq-root">' . $trimmed . '</div></body></html>';
    $doc->loadHTML($wrapped);
    libxml_clear_errors();
    libxml_use_internal_errors($prevErrors);

    $root = $doc->getElementById('zippy-faq-root');
    if (!$root) {
        return nl2br(e(strip_tags($trimmed)), false);
    }

    $sanitizeNode = function (DOMNode $node) use (&$sanitizeNode, $allowedTags, $dropEntirely): void {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                continue;
            }
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                $node->removeChild($child);
                continue;
            }

            /** @var DOMElement $child */
            $tag = strtolower($child->nodeName);
            if (in_array($tag, $dropEntirely, true)) {
                $node->removeChild($child);
                continue;
            }

            $sanitizeNode($child);

            if (!in_array($tag, $allowedTags, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            $attrNames = [];
            foreach ($child->attributes as $attr) {
                $attrNames[] = $attr->nodeName;
            }
            foreach ($attrNames as $attrName) {
                $lowerAttr = strtolower($attrName);
                if ($tag === 'a' && $lowerAttr === 'href') {
                    $rawHref = trim(html_entity_decode($child->getAttribute($attrName), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    $cleanHref = (string)preg_replace('/[\x00-\x20]+/', '', $rawHref);
                    if (!preg_match('#^(?:https?://|mailto:|/(?!/))#i', $cleanHref)) {
                        $child->removeAttribute($attrName);
                    } else {
                        $child->setAttribute('href', $rawHref);
                    }
                    continue;
                }
                if ($tag === 'a' && $lowerAttr === 'title') {
                    continue;
                }
                if ($tag === 'a' && $lowerAttr === 'target' && strtolower($child->getAttribute($attrName)) === '_blank') {
                    $child->setAttribute('rel', 'noopener noreferrer');
                    continue;
                }
                $child->removeAttribute($attrName);
            }
            if ($tag === 'a' && $child->hasAttribute('href') && !$child->hasAttribute('rel')) {
                $child->setAttribute('rel', 'noopener noreferrer');
            }
        }
    };

    $sanitizeNode($root);

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return trim($out);
}

/**
 * Dummy Indian Serviceable Zones (Bengaluru Neighborhood Pincodes & Coordinates)
 * Centered around Zippy Kitchen Hub in Indiranagar (12.9784, 77.6408)
 *
 * @return array<string, array{pincode: string, zone: string, lat: float, lng: float}>
 */
function getIndianServiceZones(): array
{
    return [
        '560038' => ['pincode' => '560038', 'zone' => 'Indiranagar 100ft Rd', 'lat' => 12.97180000, 'lng' => 77.64120000],
        '560008' => ['pincode' => '560008', 'zone' => 'Halasuru / Ulsoor Lake', 'lat' => 12.98170000, 'lng' => 77.62850000],
        '560071' => ['pincode' => '560071', 'zone' => 'Domlur / EGL Tech Park', 'lat' => 12.96100000, 'lng' => 77.63870000],
        '560075' => ['pincode' => '560075', 'zone' => 'New Thippasandra', 'lat' => 12.97350000, 'lng' => 77.65400000],
        '560017' => ['pincode' => '560017', 'zone' => 'HAL Old Airport Rd', 'lat' => 12.95920000, 'lng' => 77.65660000],
        '560042' => ['pincode' => '560042', 'zone' => 'Ulsoor / Sivanchetti Gardens', 'lat' => 12.98540000, 'lng' => 77.61940000],
        '560001' => ['pincode' => '560001', 'zone' => 'MG Road / Cubbon Park', 'lat' => 12.97560000, 'lng' => 77.60660000],
        '560025' => ['pincode' => '560025', 'zone' => 'Richmond Town', 'lat' => 12.96550000, 'lng' => 77.60120000],
        '560034' => ['pincode' => '560034', 'zone' => 'Koramangala 4th Block', 'lat' => 12.93520000, 'lng' => 77.62450000],
        '560095' => ['pincode' => '560095', 'zone' => 'Koramangala 6th Block', 'lat' => 12.93890000, 'lng' => 77.62210000],
        '560068' => ['pincode' => '560068', 'zone' => 'HSR Layout Sector 1', 'lat' => 12.91210000, 'lng' => 77.64460000],
        '560103' => ['pincode' => '560103', 'zone' => 'Bellandur / EcoSpace', 'lat' => 12.92790000, 'lng' => 77.67620000],
        '560087' => ['pincode' => '560087', 'zone' => 'Kadubeesanahalli / ORR', 'lat' => 12.93690000, 'lng' => 77.69140000],
        '560066' => ['pincode' => '560066', 'zone' => 'Whitefield / Palm Meadows', 'lat' => 12.96980000, 'lng' => 77.74990000],
        '560004' => ['pincode' => '560004', 'zone' => 'Basavanagudi', 'lat' => 12.94060000, 'lng' => 77.57380000],
        '560037' => ['pincode' => '560037', 'zone' => 'Marathahalli', 'lat' => 12.95690000, 'lng' => 77.70110000],
    ];
}

/**
 * Calculate great-circle Haversine distance in kilometers between two coordinates.
 */
function calculateHaversineDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earthRadiusKm = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat / 2.0) * sin($dLat / 2.0)
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
        * sin($dLng / 2.0) * sin($dLng / 2.0);
    $c = 2.0 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));

    return round($earthRadiusKm * $c, 2);
}

/**
 * Calculate distance-tiered delivery fee in INR.
 */
function calculateDistanceDeliveryFee(float $distanceKm, float $subtotal = 0.0): float
{
    if ($subtotal >= FREE_DELIVERY_THRESHOLD) {
        return 0.00;
    }
    if ($distanceKm <= 3.0) {
        return 25.00;
    }
    if ($distanceKm <= 6.0) {
        return 40.00;
    }
    if ($distanceKm <= 10.0) {
        return 55.00;
    }
    return 70.00;
}

/**
 * Validate Indian pincode / Leaflet coordinates against the Bangalore hub geofence.
 *
 * @return array{serviceable: bool, latitude: float, longitude: float, distance_km: float, delivery_fee: float, eta_minutes: int, zone_name: string, message: string}
 */
function validateServiceableLocation(?string $pincode, ?float $lat = null, ?float $lng = null, float $subtotal = 0.0): array
{
    $cleanPin = preg_replace('/\D+/', '', (string)($pincode ?? ''));
    $zones = getIndianServiceZones();
    $hubLat = defined('ZIPPY_HUB_LAT') ? (float)ZIPPY_HUB_LAT : 12.97840000;
    $hubLng = defined('ZIPPY_HUB_LNG') ? (float)ZIPPY_HUB_LNG : 77.64080000;
    $maxRadius = defined('MAX_DELIVERY_RADIUS_KM') ? (float)MAX_DELIVERY_RADIUS_KM : 12.0;

    $resolvedZone = $zones[$cleanPin] ?? null;
    if ($lat !== null && $lng !== null && abs($lat) > 0.01 && abs($lng) > 0.01) {
        $useLat = round($lat, 8);
        $useLng = round($lng, 8);
        $zoneName = $resolvedZone['zone'] ?? ($cleanPin !== '' ? "Bengaluru PIN {$cleanPin}" : 'Custom Map Pin');
    } elseif ($resolvedZone !== null) {
        $useLat = $resolvedZone['lat'];
        $useLng = $resolvedZone['lng'];
        $zoneName = $resolvedZone['zone'];
    } elseif (preg_match('/^560\d{3}$/', $cleanPin)) {
        // Fallback deterministic Bengaluru dummy offset for valid 560xxx pincodes
        $offsetSeed = ((int)substr($cleanPin, -3)) % 25;
        $useLat = round(12.9500 + ($offsetSeed * 0.0018), 8);
        $useLng = round(77.6200 + ($offsetSeed * 0.0016), 8);
        $zoneName = "Bengaluru Zone ({$cleanPin})";
    } else {
        return [
            'serviceable' => false,
            'latitude' => 12.96980000,
            'longitude' => 77.64990000,
            'distance_km' => 99.0,
            'delivery_fee' => DEFAULT_DELIVERY_FEE,
            'eta_minutes' => 0,
            'zone_name' => 'Outside Service Zone',
            'message' => 'Sorry! We currently deliver only within Bengaluru Indian serviceable pincodes (560xxx) and within 12 km of Indiranagar Hub.'
        ];
    }

    // Ensure coordinates fall within realistic Indian geographic bounding box (Lat 6..38, Lng 68..98)
    if ($useLat < 6.0 || $useLat > 38.0 || $useLng < 68.0 || $useLng > 98.0) {
        return [
            'serviceable' => false,
            'latitude' => $useLat,
            'longitude' => $useLng,
            'distance_km' => 99.0,
            'delivery_fee' => DEFAULT_DELIVERY_FEE,
            'eta_minutes' => 0,
            'zone_name' => 'Outside India Service Area',
            'message' => 'Please drop your pin within an Indian serviceable neighborhood (Bengaluru zone).'
        ];
    }

    $distanceKm = max(0.4, calculateHaversineDistanceKm($hubLat, $hubLng, $useLat, $useLng));
    if ($distanceKm > $maxRadius) {
        return [
            'serviceable' => false,
            'latitude' => $useLat,
            'longitude' => $useLng,
            'distance_km' => $distanceKm,
            'delivery_fee' => DEFAULT_DELIVERY_FEE,
            'eta_minutes' => 0,
            'zone_name' => $zoneName,
            'message' => "Selected location is {$distanceKm} km from Indiranagar Hub, exceeding our {$maxRadius} km fresh-delivery geofence."
        ];
    }

    $fee = calculateDistanceDeliveryFee($distanceKm, $subtotal);
    $etaMins = max(12, (int)round(12 + ($distanceKm * 2.5)));

    return [
        'serviceable' => true,
        'latitude' => $useLat,
        'longitude' => $useLng,
        'distance_km' => $distanceKm,
        'delivery_fee' => $fee,
        'eta_minutes' => $etaMins,
        'zone_name' => $zoneName,
        'message' => "Serviceable ({$zoneName} • {$distanceKm} km from hub)"
    ];
}

/**
 * Compute loyalty points earned for a delivered order.
 */
function calculateLoyaltyPointsEarned(float $eligibleAmount): int
{
    $divisor = defined('LOYALTY_EARN_DIVISOR') ? (float)LOYALTY_EARN_DIVISOR : 20.0;
    return max(0, (int)floor($eligibleAmount / max(1.0, $divisor)));
}

/**
 * Atomically credit loyalty points when an order reaches DELIVERED (idempotent per order).
 */
function awardLoyaltyPointsForOrder(int $orderId): int
{
    if ($orderId <= 0) {
        return 0;
    }

    try {
        $existingEarned = (int)(dbFetchVal(
            "SELECT COUNT(*) FROM `loyalty_transactions` WHERE `order_id` = :oid AND `txn_type` = 'EARNED'",
            [':oid' => $orderId]
        ) ?: 0);
        if ($existingEarned > 0) {
            return 0;
        }

        $order = dbFetchOne(
            "SELECT `order_id`, `user_id`, `subtotal`, `discount_amount`, `loyalty_discount`, `order_status` FROM `orders` WHERE `order_id` = :oid FOR UPDATE",
            [':oid' => $orderId]
        );
        if (!$order) {
            return 0;
        }

        $netFoodPaid = max(0.0, (float)$order['subtotal'] - (float)$order['discount_amount'] - (float)($order['loyalty_discount'] ?? 0));
        $pointsEarned = calculateLoyaltyPointsEarned($netFoodPaid);
        if ($pointsEarned <= 0) {
            return 0;
        }

        $userId = (int)$order['user_id'];
        $userRow = dbFetchOne("SELECT `loyalty_points` FROM `users` WHERE `user_id` = :uid FOR UPDATE", [':uid' => $userId]);
        $currentPts = (int)($userRow['loyalty_points'] ?? 0);
        $newBalance = $currentPts + $pointsEarned;

        dbQuery("UPDATE `users` SET `loyalty_points` = :pts WHERE `user_id` = :uid", [':pts' => $newBalance, ':uid' => $userId]);
        dbQuery("
            INSERT INTO `loyalty_transactions` (`user_id`, `order_id`, `txn_type`, `points`, `balance_after`, `description`)
            VALUES (:uid, :oid, 'EARNED', :pts, :bal, :desc)
        ", [
            ':uid' => $userId,
            ':oid' => $orderId,
            ':pts' => $pointsEarned,
            ':bal' => $newBalance,
            ':desc' => "Earned {$pointsEarned} points on delivered order #ZIP-{$orderId}"
        ]);

        return $pointsEarned;
    } catch (Throwable $e) {
        error_log("awardLoyaltyPointsForOrder error for order {$orderId}: " . $e->getMessage());
        return 0;
    }
}

/**
 * Atomically restore redeemed loyalty points when an order is CANCELLED (idempotent).
 */
function refundLoyaltyPointsForOrder(int $orderId): int
{
    if ($orderId <= 0) {
        return 0;
    }

    try {
        $alreadyRefunded = (int)(dbFetchVal(
            "SELECT COUNT(*) FROM `loyalty_transactions` WHERE `order_id` = :oid AND `txn_type` = 'REFUND_BONUS'",
            [':oid' => $orderId]
        ) ?: 0);
        if ($alreadyRefunded > 0) {
            return 0;
        }

        $order = dbFetchOne(
            "SELECT `order_id`, `user_id`, `loyalty_points_used` FROM `orders` WHERE `order_id` = :oid FOR UPDATE",
            [':oid' => $orderId]
        );
        $ptsUsed = (int)($order['loyalty_points_used'] ?? 0);
        if (!$order || $ptsUsed <= 0) {
            return 0;
        }

        $userId = (int)$order['user_id'];
        $userRow = dbFetchOne("SELECT `loyalty_points` FROM `users` WHERE `user_id` = :uid FOR UPDATE", [':uid' => $userId]);
        $currentPts = (int)($userRow['loyalty_points'] ?? 0);
        $newBalance = $currentPts + $ptsUsed;

        dbQuery("UPDATE `users` SET `loyalty_points` = :pts WHERE `user_id` = :uid", [':pts' => $newBalance, ':uid' => $userId]);
        dbQuery("
            INSERT INTO `loyalty_transactions` (`user_id`, `order_id`, `txn_type`, `points`, `balance_after`, `description`)
            VALUES (:uid, :oid, 'REFUND_BONUS', :pts, :bal, :desc)
        ", [
            ':uid' => $userId,
            ':oid' => $orderId,
            ':pts' => $ptsUsed,
            ':bal' => $newBalance,
            ':desc' => "Restored {$ptsUsed} loyalty points from cancelled order #ZIP-{$orderId}"
        ]);

        return $ptsUsed;
    } catch (Throwable $e) {
        error_log("refundLoyaltyPointsForOrder error for order {$orderId}: " . $e->getMessage());
        return 0;
    }
}

/**
 * Supported catalog allergen identifiers & human-friendly labels.
 *
 * @return array<string, string>
 */
function getDishAllergenOptions(): array
{
    return [
        'dairy' => 'Dairy / Milk',
        'nuts' => 'Tree Nuts / Cashews / Almonds',
        'gluten' => 'Gluten / Wheat',
        'egg' => 'Egg',
        'soy' => 'Soy'
    ];
}

/**
 * Supported customer dietary preference flags.
 *
 * @return array<string, array{label: string, badge: string, icon: string}>
 */
function getDietaryPreferenceOptions(): array
{
    return [
        'veg_only' => ['label' => 'Pure Vegetarian Only', 'badge' => 'Veg Only', 'icon' => 'bi-circle-fill text-success'],
        'high_protein' => ['label' => 'High Protein (>= 20g)', 'badge' => 'High Protein', 'icon' => 'bi-lightning-charge-fill text-warning'],
        'low_calorie' => ['label' => 'Calorie-Conscious (<= 400 kcal)', 'badge' => 'Low Cal', 'icon' => 'bi-heart-pulse-fill text-danger'],
        'dairy_free' => ['label' => 'Dairy-Free (Avoid Dairy)', 'badge' => 'Dairy-Free', 'icon' => 'bi-droplet-half text-info'],
        'nut_free' => ['label' => 'Nut-Free (Avoid Nuts)', 'badge' => 'Nut-Free', 'icon' => 'bi-shield-check text-success'],
        'gluten_free' => ['label' => 'Gluten-Free (Avoid Gluten)', 'badge' => 'Gluten-Free', 'icon' => 'bi-slash-circle text-primary'],
        'mild_spice' => ['label' => 'Mild Spice Preferred', 'badge' => 'Mild Spice', 'icon' => 'bi-thermometer-low text-success'],
    ];
}

/**
 * Fetch saved dietary/allergen preference keys for a customer account.
 *
 * @return array<int, string>
 */
function getUserDietaryPreferences(?int $userId): array
{
    if ($userId === null || $userId <= 0) {
        return [];
    }
    try {
        $raw = (string)(dbFetchVal("SELECT `dietary_preferences` FROM `users` WHERE `user_id` = :uid", [':uid' => $userId]) ?? '');
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    } catch (Throwable $e) {
        return [];
    }
}

require_once __DIR__ . '/view_helpers.php';
require_once __DIR__ . '/Services/CatalogService.php';
require_once __DIR__ . '/Services/OrderService.php';
require_once __DIR__ . '/Services/FleetService.php';
require_once __DIR__ . '/Services/FinanceService.php';
require_once __DIR__ . '/Services/AdminService.php';




