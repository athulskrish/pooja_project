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
            COUNT(CASE WHEN order_status = 'CANCELLED' THEN 1 END) AS cancelled
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
    db_query("UPDATE `orders` SET `order_status` = :st WHERE `order_id` = :id", [
        ':st' => $newStatus,
        ':id' => $orderId
    ]);

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

    return [
        'success' => true,
        'order_id' => $orderId,
        'old_status' => $oldStatus,
        'new_status' => $newStatus,
        'badge_html' => get_status_badge($newStatus),
        'message' => "Order #ZIP-{$orderId} status advanced to {$newStatus}."
    ];
}


