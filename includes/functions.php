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

    // If it's an uploaded category photo
    if (preg_match('#/assets/uploads/categories/([^/\?\#]+)$#', $url, $m)) {
        return ASSETS_URL . '/uploads/categories/' . $m[1];
    }

    // If it's an absolute URL (e.g. Unsplash, CDN, etc.)
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }

    // If relative path
    $clean = ltrim($url, '/');
    if (str_starts_with($clean, 'assets/')) {
        return BASE_URL . '/' . $clean;
    }

    return ASSETS_URL . '/' . $clean;
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

