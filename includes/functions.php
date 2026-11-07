<?php
/**
 * Shared Helper Functions & Business Logic
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Execute a query with parameters and return PDOStatement
 */
function db_query(string $sql, array $params = []): ?PDOStatement {
    $pdo = getDBConnection();
    if (!$pdo) {
        return null;
    }
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database Query Error: " . $e->getMessage() . " | SQL: " . $sql);
        return null;
    }
}

/**
 * Fetch all rows
 */
function db_fetch_all(string $sql, array $params = []): array {
    $stmt = db_query($sql, $params);
    return $stmt ? $stmt->fetchAll() : [];
}

/**
 * Fetch single row
 */
function db_fetch_one(string $sql, array $params = []): ?array {
    $stmt = db_query($sql, $params);
    $res = $stmt ? $stmt->fetch() : null;
    return $res ?: null;
}

/**
 * Fetch single scalar value
 */
function db_fetch_val(string $sql, array $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt ? $stmt->fetchColumn() : null;
}

/**
 * Get active coupon definitions
 */
function get_available_coupons(): array {
    return [
        'LUSHFOREST' => [
            'code' => 'LUSHFOREST',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'max_discount' => 100.00,
            'min_order' => 299.00,
            'description' => '20% OFF up to ₹100 on orders above ₹299 (Figma Lush Special)'
        ],
        'ZIPPY50' => [
            'code' => 'ZIPPY50',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'max_discount' => 120.00,
            'min_order' => 399.00,
            'description' => '50% OFF up to ₹120 on your order above ₹399'
        ],
        'WELCOME' => [
            'code' => 'WELCOME',
            'discount_type' => 'flat',
            'discount_value' => 50.00,
            'max_discount' => 50.00,
            'min_order' => 199.00,
            'description' => 'Flat ₹50 OFF for welcome customers on orders above ₹199'
        ]
    ];
}

/**
 * Calculate Cart Totals
 * 
 * @param array $cartItems Array of items with 'price' and 'qty'
 * @param string|null $couponCode
 * @return array
 */
function calculate_bill_totals(array $cartItems, ?string $couponCode = null): array {
    $subtotal = 0.00;
    $totalQty = 0;

    foreach ($cartItems as $item) {
        $price = (float)($item['price'] ?? 0);
        $qty = (int)($item['qty'] ?? 1);
        $subtotal += ($price * $qty);
        $totalQty += $qty;
    }

    // Delivery fee calculation
    $deliveryFee = ($subtotal >= FREE_DELIVERY_THRESHOLD || $subtotal == 0) ? 0.00 : DEFAULT_DELIVERY_FEE;
    
    // Platform fee
    $platformFee = ($subtotal > 0) ? PLATFORM_FEE : 0.00;

    // Coupon discount calculation
    $discount = 0.00;
    $couponApplied = false;
    $couponError = null;

    if (!empty($couponCode)) {
        $coupons = get_available_coupons();
        $code = strtoupper(trim($couponCode));
        if (isset($coupons[$code])) {
            $cp = $coupons[$code];
            if ($subtotal >= $cp['min_order']) {
                if ($cp['discount_type'] === 'percentage') {
                    $calc = ($subtotal * $cp['discount_value']) / 100;
                    $discount = min($calc, $cp['max_discount']);
                } else {
                    $discount = min($cp['discount_value'], $subtotal);
                }
                $couponApplied = true;
            } else {
                $couponError = "Minimum order of ₹{$cp['min_order']} required for coupon {$code}.";
            }
        } else {
            $couponError = "Invalid coupon code: {$code}.";
        }
    }

    // Tax calculation on discounted food subtotal (5% GST)
    $taxableSubtotal = max(0.00, $subtotal - $discount);
    $gstAmount = round(($taxableSubtotal * GST_PERCENT) / 100, 2);
    $cgst = round($gstAmount / 2, 2);
    $sgst = round($gstAmount / 2, 2);

    $finalTotal = max(0.00, $taxableSubtotal + $gstAmount + $deliveryFee + $platformFee);

    return [
        'subtotal' => round($subtotal, 2),
        'total_qty' => $totalQty,
        'discount' => round($discount, 2),
        'coupon_code' => $couponApplied ? strtoupper(trim($couponCode)) : null,
        'coupon_error' => $couponError,
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
function get_session_cart(): array {
    return $_SESSION['cart'] ?? [];
}

/**
 * Save session cart array
 */
function set_session_cart(array $cart): void {
    $_SESSION['cart'] = $cart;
}

/**
 * Clear session cart
 */
function clear_session_cart(): void {
    unset($_SESSION['cart']);
    unset($_SESSION['applied_coupon']);
}

/**
 * Resolves dish / food image URL safely across domains, ports, relative assets, and fallbacks.
 */
function get_dish_image_url(?string $url): string {
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
function get_category_image_url(?string $url): string {
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
function get_mood_image_url(?string $moodKeyOrUrl): string {
    if (empty($moodKeyOrUrl)) {
        return ASSETS_URL . '/images/moods/comfort.jpg';
    }

    $moods = get_food_moods(true);
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
function get_food_moods(bool $includeInactive = false): array {
    static $cacheActive = null;
    static $cacheAll = null;

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
        $rows = db_fetch_all($sql);
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
function handle_mood_photo_upload(string $fileKey = 'mood_image'): ?array {
    return handle_image_upload($fileKey, 'moods', 'mood');
}

/**
 * Count how many dishes are tagged with a specific food mood.
 */
function get_mood_dish_count(string $moodKey): int {
    $moodKey = trim($moodKey);
    if (empty($moodKey)) return 0;
    $count = db_fetch_val("
        SELECT COUNT(*) FROM `menu_items` 
        WHERE FIND_IN_SET(:key, REPLACE(`mood`, ' ', '')) > 0
    ", [':key' => $moodKey]);
    return (int)$count;
}

/**
 * Return all dishes assigned to a specific mood.
 */
function get_dishes_by_mood(string $moodKey): array {
    $moodKey = trim($moodKey);
    if (empty($moodKey)) return [];
    return db_fetch_all("
        SELECT m.*, c.category_name 
        FROM `menu_items` m
        LEFT JOIN `categories` c ON m.category_id = c.category_id
        WHERE FIND_IN_SET(:key, REPLACE(m.`mood`, ' ', '')) > 0
        ORDER BY m.item_name ASC
    ", [':key' => $moodKey]);
}

/**
 * Assign an exact list of dishes to a mood (updates menu_items.mood accordingly).
 */
function set_mood_dishes(string $moodKey, array $itemIds): bool {
    $moodKey = trim($moodKey);
    if (empty($moodKey)) return false;

    $allDishes = db_fetch_all("SELECT `item_id`, `mood` FROM `menu_items`");
    $selectedMap = array_flip(array_map('intval', $itemIds));

    foreach ($allDishes as $d) {
        $id = (int)$d['item_id'];
        $currentMoods = array_filter(array_map('trim', explode(',', $d['mood'] ?? '')));
        $hasMood = in_array($moodKey, $currentMoods, true);
        $shouldHave = isset($selectedMap[$id]);

        if ($shouldHave && !$hasMood) {
            $currentMoods[] = $moodKey;
            $newMoodStr = implode(',', array_unique($currentMoods));
            db_query("UPDATE `menu_items` SET `mood` = :m WHERE `item_id` = :id", [
                ':m' => $newMoodStr,
                ':id' => $id
            ]);
        } elseif (!$shouldHave && $hasMood) {
            $currentMoods = array_values(array_filter($currentMoods, fn($m) => $m !== $moodKey));
            $newMoodStr = !empty($currentMoods) ? implode(',', array_unique($currentMoods)) : 'comfort';
            db_query("UPDATE `menu_items` SET `mood` = :m WHERE `item_id` = :id", [
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
function add_dish_to_mood(int $itemId, string $moodKey): bool {
    $moodKey = trim($moodKey);
    if (empty($moodKey) || $itemId <= 0) return false;
    $d = db_fetch_one("SELECT `mood` FROM `menu_items` WHERE `item_id` = :id", [':id' => $itemId]);
    if (!$d) return false;
    $currentMoods = array_filter(array_map('trim', explode(',', $d['mood'] ?? '')));
    if (!in_array($moodKey, $currentMoods, true)) {
        $currentMoods[] = $moodKey;
        $newMoodStr = implode(',', array_unique($currentMoods));
        db_query("UPDATE `menu_items` SET `mood` = :m WHERE `item_id` = :id", [
            ':m' => $newMoodStr,
            ':id' => $itemId
        ]);
    }
    return true;
}

/**
 * Remove a single dish from a mood.
 */
function remove_dish_from_mood(int $itemId, string $moodKey): bool {
    $moodKey = trim($moodKey);
    if (empty($moodKey) || $itemId <= 0) return false;
    $d = db_fetch_one("SELECT `mood` FROM `menu_items` WHERE `item_id` = :id", [':id' => $itemId]);
    if (!$d) return false;
    $currentMoods = array_filter(array_map('trim', explode(',', $d['mood'] ?? '')));
    $currentMoods = array_values(array_filter($currentMoods, fn($m) => $m !== $moodKey));
    $newMoodStr = !empty($currentMoods) ? implode(',', array_unique($currentMoods)) : 'comfort';
    db_query("UPDATE `menu_items` SET `mood` = :m WHERE `item_id` = :id", [
        ':m' => $newMoodStr,
        ':id' => $itemId
    ]);
    return true;
}

/**
 * Get mood definition by key or comma-separated string (returns the first matching primary mood)
 */
function get_dish_primary_mood(?string $moodStr): ?array {
    if (empty($moodStr)) {
        return null;
    }
    $moods = get_food_moods();
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
function handle_image_upload(string $fileKey, string $subfolder = 'dishes', string $prefix = 'img'): ?array {
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
function handle_food_photo_upload(string $fileKey = 'dish_image'): ?array {
    return handle_image_upload($fileKey, 'dishes', 'dish');
}

/**
 * Safely handles category photo upload from HTML form multipart file input.
 * 
 * @param string $fileKey Key name in $_FILES (default: 'category_image')
 * @return array|null
 */
function handle_category_photo_upload(string $fileKey = 'category_image'): ?array {
    return handle_image_upload($fileKey, 'categories', 'cat');
}

/**
 * Render star icons HTML string (e.g. ⭐⭐⭐⭐⭐)
 * 
 * @param float $rating Rating value between 1 and 5
 * @param int $maxStars Maximum number of stars (default: 5)
 * @param string $starClass CSS class for active stars
 * @return string HTML containing star icons
 */
function render_star_rating_html(float $rating, int $maxStars = 5, string $starClass = 'text-warning'): string {
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
function get_dish_reviews(int $itemId, int $limit = 10): array {
    return db_fetch_all("
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
function format_time_ago($datetime): string {
    if (empty($datetime)) {
        return 'Just now';
    }
    $timestamp = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
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
function format_minutes_ago(int $minutes): string {
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
function record_money_transaction(
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
): int {
    $txnNum = 'TXN-' . date('Ym') . '-' . str_pad((string)random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
    db_query("
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
    return (int)db_fetch_val("SELECT LAST_INSERT_ID()");
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
function get_driver_active_delivery(int $riderId, ?string $riderName = null): ?array {
    $whereRider = "d.rider_id = :rid";
    $params = [':rid' => $riderId];

    if (!empty($riderName)) {
        $firstWord = trim(explode(' ', trim($riderName))[0]);
        if (strlen($firstWord) >= 3) {
            $whereRider = "(d.rider_id = :rid OR (d.rider_id IS NULL AND d.delivery_partner_name LIKE :rname))";
            $params[':rname'] = '%' . $firstWord . '%';
        }
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

    return db_fetch_one($sql, $params);
}

/**
 * Checks if a driver is currently busy with another active delivery.
 * Returns the active order if busy, or null if available.
 */
function is_driver_busy(int $riderId, ?string $riderName = null, ?int $excludeOrderId = null): ?array {
    $active = get_driver_active_delivery($riderId, $riderName);
    if ($active && $excludeOrderId && (int)$active['order_id'] === $excludeOrderId) {
        return null;
    }
    return $active;
}

/**
 * Fetch all registered delivery riders with their active delivery status
 */
function get_all_delivery_riders(): array {
    $riders = db_fetch_all("
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
        $active = get_driver_active_delivery((int)$r['user_id'], $r['full_name']);
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
function get_available_delivery_partner(): ?array {
    $allRiders = get_all_delivery_riders();
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
function get_kds_state_hash(): string {
    $countNew = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` IN ('PLACED', 'CONFIRMED')");
    $countCooking = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'COOKING'");
    $countReady = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'READY'");
    $countOutForDelivery = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'OUT_FOR_DELIVERY'");
    $countDelivered = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `order_status` = 'DELIVERED'");
    
    $latestAudit = db_fetch_one("SELECT MAX(audit_id) AS max_audit, MAX(change_timestamp) AS max_time FROM `order_audit_trail`");
    $latestDispatch = db_fetch_one("SELECT MAX(dispatch_id) AS max_disp, MAX(assigned_at) AS max_assigned, MAX(dispatched_at) AS max_dispatched, MAX(delivered_at) AS max_delivered FROM `delivery_dispatches`");

    // Fingerprint of ready orders and their assigned riders to detect allocation changes
    $readyAllocFingerprint = (string)db_fetch_val("
        SELECT GROUP_CONCAT(CONCAT(o.order_id, ':', COALESCE(d.rider_id, 0), ':', COALESCE(d.delivery_status, '')) ORDER BY o.order_id SEPARATOR '|')
        FROM `orders` o
        LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
        WHERE o.order_status = 'READY'
    ");

    return md5(json_encode([
        'new' => $countNew,
        'cooking' => $countCooking,
        'ready' => $countReady,
        'out' => $countOutForDelivery,
        'delivered' => $countDelivered,
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
function auto_allocate_ready_orders_fcfs(): array {
    // 1. Fetch all registered drivers with their busy/duty status
    $allRiders = get_all_delivery_riders();
    $availableRiders = [];
    $busyRiderIds = [];

    foreach ($allRiders as $r) {
        $rId = (int)$r['user_id'];
        if ($r['duty_status'] === 'ON_DUTY' && empty($r['is_busy'])) {
            $availableRiders[] = $r;
        } else {
            $busyRiderIds[] = $rId;
        }
    }

    // 2. Fetch READY orders sorted strictly by preparation time (first prepared first)
    $readyParcels = db_fetch_all("
        SELECT o.order_id, o.order_number, o.ready_at, o.placed_at,
               d.dispatch_id, d.rider_id, d.delivery_status
        FROM `orders` o
        LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
        WHERE o.order_status = 'READY'
        ORDER BY COALESCE(o.ready_at, o.placed_at) ASC, o.order_id ASC
    ");

    if (empty($readyParcels)) {
        return ['allocated' => 0, 'available_riders' => count($availableRiders), 'message' => 'No ready parcels waiting in queue.'];
    }

    // 3. Purge invalid allocations from READY parcels:
    // Any ready parcel currently assigned to a rider who is busy or offline must be unassigned
    $assignedRiderIds = [];
    foreach ($readyParcels as &$parcel) {
        $currentRiderId = !empty($parcel['rider_id']) ? (int)$parcel['rider_id'] : null;
        if ($currentRiderId) {
            // If rider is busy or off duty, or already assigned to another ready parcel earlier in the FCFS queue:
            if (in_array($currentRiderId, $busyRiderIds) || in_array($currentRiderId, $assignedRiderIds)) {
                db_query("
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
                // Legitimate assignment to an available free driver
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

        // If parcel already legitimately assigned to a free driver, keep it
        if ($currentRiderId && in_array($currentRiderId, $assignedRiderIds)) {
            continue;
        }

        // Find the next available free driver who has not yet been assigned a ready parcel
        $matchedRider = null;
        foreach ($availableRiders as $r) {
            $rId = (int)$r['user_id'];
            if (!in_array($rId, $assignedRiderIds)) {
                $matchedRider = $r;
                $assignedRiderIds[] = $rId;
                break;
            }
        }

        if (!$matchedRider) {
            // No more free drivers available
            break;
        }

        // Assign parcel to this matched rider in delivery_dispatches
        $existingDisp = db_fetch_one("SELECT dispatch_id FROM `delivery_dispatches` WHERE `order_id` = :oid", [':oid' => $parcel['order_id']]);
        if ($existingDisp) {
            db_query("
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
            db_query("
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
}

/**
 * Retrieve the specific parcel allocated to a driver in First-Come, First-Served basis.
 * Returns null if driver is already busy with an active in-transit delivery or no parcel is allocated.
 */
function get_driver_allocated_order(int $riderId): ?array {
    // 1. Check if rider is already busy with an active in-transit delivery (1 Driver 1 Delivery)
    if (is_driver_busy($riderId)) {
        return null;
    }

    // 2. Run allocation engine to ensure latest FCFS assignments
    auto_allocate_ready_orders_fcfs();

    // 3. Find order with order_status = 'READY' and dispatch.rider_id = $riderId
    $allocated = db_fetch_one("
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
        $allocated = db_fetch_one("
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
            $riderUser = db_fetch_one("SELECT * FROM `users` WHERE `user_id` = :uid", [':uid' => $riderId]);
            if ($riderUser) {
                if (!empty($allocated['dispatch_id'])) {
                    db_query("UPDATE `delivery_dispatches` SET `rider_id` = :rid, `delivery_partner_name` = :rname, `delivery_partner_phone` = :rphone, `assigned_at` = NOW(), `delivery_status` = 'ASSIGNED' WHERE `order_id` = :oid", [
                        ':rid' => $riderUser['user_id'],
                        ':rname' => $riderUser['full_name'],
                        ':rphone' => $riderUser['phone_number'],
                        ':oid' => $allocated['order_id']
                    ]);
                } else {
                    $newOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    db_query("INSERT INTO `delivery_dispatches` (`order_id`, `rider_id`, `delivery_partner_name`, `delivery_partner_phone`, `delivery_otp`, `assigned_at`, `delivery_status`) VALUES (:oid, :rid, :rname, :rphone, :otp, NOW(), 'ASSIGNED')", [
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
        $items = db_fetch_all("
            SELECT oi.*, m.item_name, m.is_veg 
            FROM `order_items` oi
            JOIN `menu_items` m ON oi.item_id = m.item_id
            WHERE oi.order_id = :oid
        ", [':oid' => $allocated['order_id']]);
        $allocated['items'] = $items;
    }

    return $allocated;
}

/**
 * Consolidated Single-Query Order Lifecycle KPIs & Counts
 * Retrieves all order status counters in a single optimized database roundtrip.
 *
 * @return array Associative array of status counts
 */
function get_order_kpis(): array {
    $row = db_fetch_one("
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
function find_rider_user($riderIdentifier = null, ?int $fallbackRiderId = null): ?array {
    $identifier = !empty($riderIdentifier) ? trim((string)$riderIdentifier) : '';
    $riderUser = null;

    if (!empty($identifier)) {
        // 1. If numeric ID passed:
        if (is_numeric($identifier) && (int)$identifier > 0) {
            $riderUser = db_fetch_one("SELECT user_id, full_name, phone_number FROM `users` WHERE role_id = 4 AND user_id = :uid", [
                ':uid' => (int)$identifier
            ]);
        }

        // 2. Look up by exact name or substring match
        if (!$riderUser) {
            $riderUser = db_fetch_one("SELECT user_id, full_name, phone_number FROM `users` WHERE role_id = 4 AND (full_name = :fn OR full_name LIKE :likefn)", [
                ':fn' => $identifier,
                ':likefn' => '%' . $identifier . '%'
            ]);
        }

        // 3. Look up by first word (e.g. "Rajesh" from "Rajesh Varma (Rider)")
        if (!$riderUser) {
            $firstWord = trim(explode(' ', $identifier)[0]);
            if (strlen($firstWord) >= 3) {
                $riderUser = db_fetch_one("SELECT user_id, full_name, phone_number FROM `users` WHERE role_id = 4 AND full_name LIKE :fn", [
                    ':fn' => '%' . $firstWord . '%'
                ]);
            }
        }
    }

    // 4. Fallback to existing dispatch rider if no rider was selected or found
    if (!$riderUser && !empty($fallbackRiderId) && $fallbackRiderId > 0) {
        $riderUser = db_fetch_one("SELECT user_id, full_name, phone_number FROM `users` WHERE role_id = 4 AND user_id = :uid", [
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
function transition_order_status(int $orderId, string $newStatus, ?string $assignedRider = null, ?int $actorUserId = null): array {
    $newStatus = strtoupper(trim($newStatus));
    if ($orderId <= 0 || empty($newStatus)) {
        return ['success' => false, 'error' => 'Missing or invalid order ID or target status.'];
    }

    $currentOrder = db_fetch_one("SELECT * FROM `orders` WHERE `order_id` = :id", [':id' => $orderId]);
    if (!$currentOrder) {
        return ['success' => false, 'error' => "Order #ZIP-{$orderId} not found."];
    }

    $oldStatus = $currentOrder['order_status'];

    // Find rider information & enforce 1 Driver 1 Delivery policy
    $dispatch = db_fetch_one("SELECT * FROM `delivery_dispatches` WHERE `order_id` = :id", [':id' => $orderId]);
    $fallbackId = !empty($dispatch['rider_id']) ? (int)$dispatch['rider_id'] : null;
    $fallbackName = $dispatch['delivery_partner_name'] ?? null;
    $riderUser = find_rider_user($assignedRider, $fallbackId);
    if (!$riderUser && !empty($fallbackName)) {
        $riderUser = find_rider_user($fallbackName, $fallbackId);
    }

    if ($newStatus === 'OUT_FOR_DELIVERY') {
        if ($riderUser) {
            $busyOrder = is_driver_busy((int)$riderUser['user_id'], $riderUser['full_name'], $orderId);
            if ($busyOrder) {
                return [
                    'success' => false,
                    'error' => "Handover Blocked (1 Driver, 1 Delivery Policy): Driver {$riderUser['full_name']} is currently busy delivering Order #ZIP-{$busyOrder['order_id']}. Each driver can only handle one delivery at a time."
                ];
            }
        }
    }

    // 1. Advance order status
    if ($newStatus === 'READY') {
        db_query("UPDATE `orders` SET `order_status` = :st, `ready_at` = COALESCE(ready_at, NOW()) WHERE `order_id` = :id", [
            ':st' => $newStatus,
            ':id' => $orderId
        ]);
    } else {
        db_query("UPDATE `orders` SET `order_status` = :st WHERE `order_id` = :id", [
            ':st' => $newStatus,
            ':id' => $orderId
        ]);
    }

    // 2. Insert into immutable order audit trail
    $auditUid = $actorUserId ?? ($_SESSION['user_id'] ?? null);
    db_query("
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
            db_query("
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
            db_query("
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
            db_query("UPDATE `delivery_dispatches` SET `delivery_partner_name` = :rname WHERE `order_id` = :oid", [
                ':rname' => $assignedRider,
                ':oid' => $orderId
            ]);
        } else {
            $newOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            db_query("
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
        $checkDisp = db_fetch_one("SELECT dispatch_id FROM `delivery_dispatches` WHERE `order_id` = :id", [':id' => $orderId]);
        if (!$checkDisp) {
            $newOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            db_query("
                INSERT INTO `delivery_dispatches`
                (`order_id`, `delivery_partner_name`, `delivery_otp`, `assigned_at`, `dispatched_at`, `delivery_status`)
                VALUES (:oid, 'Assigned Courier', :otp, NOW(), NOW(), 'IN_TRANSIT')
            ", [
                ':oid' => $orderId,
                ':otp' => $newOtp
            ]);
        }

        db_query("
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
        db_query("UPDATE `delivery_dispatches` SET `delivered_at` = NOW(), `delivery_status` = 'DELIVERED' WHERE `order_id` = :id", [':id' => $orderId]);

        // If COD, mark payment as SUCCESSFUL & complete ledger transaction
        $payRec = db_fetch_one("SELECT * FROM `payments` WHERE `order_id` = :id", [':id' => $orderId]);
        if ($payRec && $payRec['payment_mode'] === 'COD') {
            db_query("UPDATE `payments` SET `payment_status` = 'SUCCESSFUL', `payment_timestamp` = NOW() WHERE `order_id` = :id", [':id' => $orderId]);
            db_query("
                UPDATE `money_transactions` 
                SET `status` = 'COMPLETED', `handled_by` = :hby, `notes` = CONCAT(COALESCE(notes, ''), ' [Cash collected upon delivery completion]') 
                WHERE `order_id` = :id AND `txn_category` = 'CUSTOMER_PAYMENT_COD'
            ", [
                ':hby' => 'Store Admin / Courier',
                ':id'  => $orderId
            ]);
        }
    } elseif ($newStatus === 'CANCELLED') {
        // Restock inventory for cancelled order items
        db_query("
            UPDATE `menu_items` m
            JOIN `order_items` oi ON m.item_id = oi.item_id
            SET m.stock_quantity = m.stock_quantity + oi.quantity,
                m.is_available = 1
            WHERE oi.order_id = :id
        ", [':id' => $orderId]);

        // Mark payment as refunded
        db_query("
            UPDATE `payments` 
            SET `payment_status` = 'REFUNDED', `payment_timestamp` = NOW() 
            WHERE `order_id` = :id
        ", [':id' => $orderId]);
    }

    // Automatically invoke FCFS allocation engine when order is ready or delivered
    if (in_array($newStatus, ['READY', 'DELIVERED'])) {
        auto_allocate_ready_orders_fcfs();
    }

    return [
        'success' => true,
        'order_id' => $orderId,
        'old_status' => $oldStatus,
        'new_status' => $newStatus,
        'badge_html' => get_status_badge($newStatus),
        'message' => "Order #ZIP-{$orderId} status advanced to {$newStatus}."
    ];
}

/**
 * Retrieve a customer's most recent previous meal / completed order for 1-tap reordering.
 * Prioritizes delivered/completed orders, falling back to placed/confirmed if no delivered order exists.
 */
function get_customer_previous_meal(?int $userId): ?array {
    if (!$userId) {
        return null;
    }

    // 1. Fetch the most recent delivered/completed order
    $order = db_fetch_one("
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
        $order = db_fetch_one("
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
        $order = db_fetch_one("
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
    $items = db_fetch_all("
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
            'imageUrl' => get_dish_image_url($it['image_url'] ?? ''),
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
function get_dish_customization_groups(int $itemId, bool $onlyAvailable = false): array {
    $groups = db_fetch_all("
        SELECT * FROM `dish_customization_groups`
        WHERE `item_id` = :id
        ORDER BY `display_order` ASC, `group_id` ASC
    ", [':id' => $itemId]);

    if (empty($groups)) {
        return [];
    }

    $groupIds = array_column($groups, 'group_id');
    $inClause = implode(',', array_map('intval', $groupIds));
    $availFilter = $onlyAvailable ? " AND `is_available` = 1" : "";
    
    $options = db_fetch_all("
        SELECT * FROM `dish_customization_options`
        WHERE `group_id` IN ({$inClause}){$availFilter}
        ORDER BY `group_id` ASC, `display_order` ASC, `price` ASC, `option_id` ASC
    ");

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
function get_all_dishes_customization_counts(): array {
    $rows = db_fetch_all("
        SELECT g.item_id, COUNT(DISTINCT g.group_id) AS group_count, COUNT(o.option_id) AS option_count
        FROM `dish_customization_groups` g
        LEFT JOIN `dish_customization_options` o ON g.group_id = o.group_id
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
function format_customizations_html(?string $customizations): string {
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



