<?php
declare(strict_types=1);
/**
 * Order Lifecycle Domain Service
 * Encapsulates checkout transactions, customer order history/tracking/cancellation,
 * tax invoices, order reviews, saved delivery addresses (with soft delete),
 * support tickets, Kitchen Display System (KDS/KOT), and Admin order dispatch ledger.
 * Zippy - Online Food Ordering Platform
 */

class OrderService
{
    /**
     * Fetch lightweight counts for the public landing launchpad (`index.php`).
     *
     * @return array{dish_count: int, order_count: int}
     */
    public static function getPlatformOverviewCounts(bool $tablesExist): array
    {
        if (!$tablesExist) {
            return ['dish_count' => 0, 'order_count' => 0];
        }

        return [
            'dish_count' => (int)dbFetchVal("SELECT COUNT(*) FROM `menu_items` WHERE `is_active` = 1"),
            'order_count' => (int)dbFetchVal("SELECT COUNT(*) FROM `orders`"),
        ];
    }

    /**
     * Fetch the customer's most recent unreviewed delivered order for floating prompt banner.
     *
     * @return array<string, mixed>|null
     */
    public static function getCustomerUnreviewedDeliveredOrder(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        return dbFetchOne("
            SELECT o.order_id, o.order_number, o.placed_at, o.total_amount,
                   d.delivered_at, d.delivery_partner_name
            FROM `orders` o
            LEFT JOIN `order_reviews` r ON o.order_id = r.order_id
            LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
            WHERE o.user_id = :uid
              AND o.order_status = 'DELIVERED'
              AND r.review_id IS NULL
            ORDER BY COALESCE(d.delivered_at, o.placed_at) DESC
            LIMIT 1
        ", [':uid' => $userId]);
    }

    /**
     * Fetch active FAQs for customer help hub (`customer/help.php`).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getActiveFaqs(): array
    {
        try {
            $dbFaqs = dbFetchAll("SELECT * FROM `faqs` WHERE `is_active` = 1 ORDER BY `display_order` ASC, `faq_id` ASC");
        } catch (Throwable $e) {
            $dbFaqs = [];
        }

        if (!empty($dbFaqs)) {
            return $dbFaqs;
        }

        return [
            [
                'faq_id' => 1,
                'question' => 'What is the cancellation policy on food orders?',
                'answer' => 'Orders can be cancelled with 100% refund within 60 seconds of placement. Once the restaurant kitchen accepts the ticket and marks status as <code>COOKING</code>, cancellations are non-refundable as fresh meals are perishable under FSSAI rules.',
                'category' => 'Orders & Cancellation',
            ],
            [
                'faq_id' => 2,
                'question' => 'How does the 6-Digit Delivery OTP work?',
                'answer' => 'Every order generates an immutable 6-digit cryptographic OTP in the <code>delivery_dispatches</code> table. The courier cannot mark the delivery complete without receiving and submitting this code, guaranteeing secure doorstep delivery handshakes.',
                'category' => 'Delivery & Tracking',
            ],
            [
                'faq_id' => 3,
                'question' => 'How are delivery ETAs calculated?',
                'answer' => 'Our platform utilizes a multi-factor heuristic: <code>ETA = Buffer + Max(Prep Time) + Concurrency Factor + Distance Zone Travel Time</code>. In addition, the kitchen prioritizes tickets via the Weighted Shortest Job First (WSJF) algorithm.',
                'category' => 'Delivery & Tracking',
            ],
            [
                'faq_id' => 4,
                'question' => 'Is GST calculated accurately on my bill?',
                'answer' => 'Yes! Every order generates an itemized GST Tax Invoice containing 2.5% CGST and 2.5% SGST under Indian Restaurant Service Code SAC 996331. You can print or download invoices anytime from Order History.',
                'category' => 'Payments & Refunds',
            ],
        ];
    }

    /**
     * Submit a customer support inquiry/ticket in `customer/help.php`.
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed>|null $currentUser
     * @return array{submitted: bool, flash_type: string, message: string}
     */
    public static function submitSupportTicket(array $post, ?array $currentUser): array
    {
        $category = cleanInput((string)($post['category'] ?? 'General'));
        $orderRef = cleanInput((string)($post['order_id'] ?? ''));
        $description = cleanInput((string)($post['description'] ?? ''));

        if ($description === '') {
            return [
                'submitted' => false,
                'flash_type' => 'danger',
                'message' => 'Please provide details for your support ticket.',
            ];
        }

        $userId = isset($currentUser['id']) ? (int)$currentUser['id'] : null;
        $custName = (string)($currentUser['name'] ?? 'Customer');
        $custEmail = (string)($currentUser['email'] ?? 'support@zippy.com');
        $subject = ($orderRef !== '' ? "[{$orderRef}] " : '') . ucfirst(str_replace('_', ' ', $category));

        $pdo = getDbConnection();
        if (!$pdo) {
            return [
                'submitted' => true,
                'flash_type' => 'success',
                'message' => 'Your inquiry has been registered. An executive will contact you shortly.',
            ];
        }

        try {
            $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `support_tickets` (
                        `ticket_id` INT AUTO_INCREMENT PRIMARY KEY,
                        `user_id` INT NULL,
                        `customer_name` VARCHAR(120) NOT NULL,
                        `customer_email` VARCHAR(150) NOT NULL,
                        `subject` VARCHAR(200) NOT NULL,
                        `message` TEXT NOT NULL,
                        `ticket_status` ENUM('OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED') NOT NULL DEFAULT 'OPEN',
                        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                $stmt = $pdo->prepare("
                    INSERT INTO `support_tickets` (`user_id`, `customer_name`, `customer_email`, `subject`, `message`, `ticket_status`, `created_at`)
                    VALUES (:uid, :cname, :cemail, :subj, :msg, 'OPEN', NOW())
                ");
            } else {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `support_tickets` (
                        `ticket_id` INTEGER PRIMARY KEY AUTOINCREMENT,
                        `user_id` INTEGER NULL,
                        `customer_name` TEXT NOT NULL,
                        `customer_email` TEXT NOT NULL,
                        `subject` TEXT NOT NULL,
                        `message` TEXT NOT NULL,
                        `ticket_status` TEXT NOT NULL DEFAULT 'OPEN',
                        `created_at` TEXT DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                $stmt = $pdo->prepare("
                    INSERT INTO `support_tickets` (`user_id`, `customer_name`, `customer_email`, `subject`, `message`, `ticket_status`)
                    VALUES (:uid, :cname, :cemail, :subj, :msg, 'OPEN')
                ");
            }

            $stmt->execute([
                ':uid' => $userId,
                ':cname' => $custName,
                ':cemail' => $custEmail,
                ':subj' => $subject,
                ':msg' => $description,
            ]);
            $ticketId = $pdo->lastInsertId();

            return [
                'submitted' => true,
                'flash_type' => 'success',
                'message' => "Your support ticket #TKT-{$ticketId} has been registered and stored in the system. An executive will contact you shortly.",
            ];
        } catch (Throwable $e) {
            return [
                'submitted' => true,
                'flash_type' => 'success',
                'message' => 'Your inquiry has been registered. An executive will contact you shortly.',
            ];
        }
    }

    /**
     * Fetch active saved delivery addresses for a customer (`is_active = 1`).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getCustomerAddresses(int $userId): array
    {
        return dbFetchAll("
            SELECT * FROM `user_addresses`
            WHERE `user_id` = :uid AND `is_active` = 1
            ORDER BY `is_default` DESC, `address_id` DESC
        ", [':uid' => $userId]);
    }

    /**
     * Create a new delivery address for a customer.
     *
     * @param array<string, mixed> $post
     * @return array{type: string, message: string, should_redirect: bool}
     */
    public static function addCustomerAddress(int $userId, array $post): array
    {
        $type = cleanInput((string)($post['address_type'] ?? 'HOME'));
        $line = cleanInput((string)($post['address_line'] ?? ''));
        $landmark = cleanInput((string)($post['landmark'] ?? ''));
        $pincode = cleanInput((string)($post['pincode'] ?? '560038'));
        $isDefault = !empty($post['is_default']) ? 1 : 0;

        if ($line === '') {
            return ['type' => 'danger', 'message' => 'Please enter a complete address line.', 'should_redirect' => false];
        }

        try {
            dbTransactional(static function () use ($userId, $type, $line, $landmark, $pincode, $isDefault): void {
                dbFetchAll(
                    "SELECT `address_id` FROM `user_addresses` WHERE `user_id` = :uid AND `is_active` = 1 FOR UPDATE",
                    [':uid' => $userId]
                );
                if ($isDefault) {
                    dbQuery("UPDATE `user_addresses` SET `is_default` = 0 WHERE `user_id` = :uid", [':uid' => $userId]);
                }
                dbQuery("
                    INSERT INTO `user_addresses` (`user_id`, `address_type`, `address_line`, `landmark`, `pincode`, `is_default`, `is_active`)
                    VALUES (:uid, :type, :line, :lm, :pin, :def, 1)
                ", [
                    ':uid' => $userId,
                    ':type' => $type,
                    ':line' => $line,
                    ':lm' => $landmark,
                    ':pin' => $pincode,
                    ':def' => $isDefault,
                ]);
            });

            return ['type' => 'success', 'message' => 'New delivery address added successfully.', 'should_redirect' => true];
        } catch (Throwable $e) {
            error_log("add_address failed for user {$userId}: " . $e->getMessage());
            return ['type' => 'danger', 'message' => 'Failed to save delivery address. Please try again.', 'should_redirect' => true];
        }
    }

    /**
     * Mark an active address as default for the customer.
     *
     * @return array{type: string, message: string}
     */
    public static function setDefaultCustomerAddress(int $userId, int $addressId): array
    {
        try {
            $updated = dbTransactional(static function () use ($userId, $addressId): bool {
                dbFetchAll(
                    "SELECT `address_id` FROM `user_addresses` WHERE `user_id` = :uid AND `is_active` = 1 FOR UPDATE",
                    [':uid' => $userId]
                );
                $target = dbFetchOne(
                    "SELECT `address_id` FROM `user_addresses` WHERE `address_id` = :aid AND `user_id` = :uid AND `is_active` = 1 FOR UPDATE",
                    [':aid' => $addressId, ':uid' => $userId]
                );
                if (!$target) {
                    return false;
                }
                dbQuery("UPDATE `user_addresses` SET `is_default` = 0 WHERE `user_id` = :uid", [':uid' => $userId]);
                dbQuery(
                    "UPDATE `user_addresses` SET `is_default` = 1 WHERE `address_id` = :aid AND `user_id` = :uid",
                    [':aid' => $addressId, ':uid' => $userId]
                );
                return true;
            });

            return [
                'type' => $updated ? 'success' : 'danger',
                'message' => $updated ? 'Default delivery destination updated.' : 'Address not found.',
            ];
        } catch (Throwable $e) {
            error_log("set_default address failed for user {$userId}: " . $e->getMessage());
            return ['type' => 'danger', 'message' => 'Failed to update default address. Please try again.'];
        }
    }

    /**
     * Soft-archive a saved delivery address (`is_active = 0`, `is_default = 0`)
     * so historical `orders.address_id` snapshots stay intact.
     *
     * @return array{type: string, message: string}
     */
    public static function softDeleteCustomerAddress(int $userId, int $addressId): array
    {
        try {
            dbTransactional(static function () use ($userId, $addressId): void {
                dbFetchAll(
                    "SELECT `address_id` FROM `user_addresses` WHERE `user_id` = :uid AND `is_active` = 1 FOR UPDATE",
                    [':uid' => $userId]
                );
                dbQuery(
                    "UPDATE `user_addresses` SET `is_active` = 0, `is_default` = 0 WHERE `address_id` = :aid AND `user_id` = :uid",
                    [':aid' => $addressId, ':uid' => $userId]
                );
            });

            return ['type' => 'success', 'message' => 'Address archived and removed from your address book.'];
        } catch (Throwable $e) {
            error_log("delete_address failed for user {$userId}: " . $e->getMessage());
            return ['type' => 'danger', 'message' => 'Failed to remove address. Please try again.'];
        }
    }

    /**
     * Atomically place a customer order inside an ACID database transaction.
     *
     * @param array<string, mixed> $post
     * @return array{ok: bool, order_id?: int, error?: string}
     */
    public static function processCheckout(int $userId, array $post): array
    {
        $rawCartJson = (string)($post['cart_json'] ?? '[]');
        $cartItems = json_decode($rawCartJson, true);
        $couponCode = cleanInput((string)($post['coupon_code'] ?? ''));
        $paymentMode = cleanInput((string)($post['payment_mode'] ?? 'UPI'));
        $razorpayPaymentId = cleanInput((string)($post['razorpay_payment_id'] ?? ''));
        $orderNotes = cleanInput((string)($post['order_notes'] ?? ''));
        $selectedAddressId = !empty($post['address_id']) ? (int)$post['address_id'] : null;

        $deliveryType = cleanInput((string)($post['delivery_type'] ?? 'now'));
        $isScheduled = ($deliveryType === 'schedule') ? 1 : 0;
        $scheduledFor = null;
        $scheduledSlot = null;

        if ($isScheduled) {
            $scheduledDate = cleanInput((string)($post['scheduled_date'] ?? date('Y-m-d')));
            $scheduledTime = cleanInput((string)($post['scheduled_time'] ?? '13:00'));
            $scheduledSlotInput = cleanInput((string)($post['scheduled_slot'] ?? ''));

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduledDate)) {
                $scheduledDate = date('Y-m-d');
            }
            if (!preg_match('/^\d{2}:\d{2}$/', $scheduledTime)) {
                $scheduledTime = '13:00';
            }

            $scheduledTimestamp = strtotime($scheduledDate . ' ' . $scheduledTime . ':00');
            if (!$scheduledTimestamp || $scheduledTimestamp < time() + 1200) {
                $scheduledTimestamp = strtotime('+1 hour');
            }

            $scheduledFor = date('Y-m-d H:i:s', $scheduledTimestamp);
            if ($scheduledSlotInput !== '') {
                $scheduledSlot = $scheduledSlotInput;
            } else {
                $dateLabel = (date('Y-m-d', $scheduledTimestamp) === date('Y-m-d'))
                    ? 'Today'
                    : ((date('Y-m-d', $scheduledTimestamp) === date('Y-m-d', strtotime('+1 day'))) ? 'Tomorrow' : date('D, d M', $scheduledTimestamp));
                $scheduledSlot = $dateLabel . ' (' . date('h:i A', $scheduledTimestamp) . ')';
            }

            $orderNotes = trim($orderNotes . " [ADVANCE ORDER: Scheduled for {$scheduledSlot}]");
        }

        $newAddressLine = cleanInput((string)($post['new_address_line'] ?? ''));
        $newPincode = cleanInput((string)($post['new_pincode'] ?? '560038'));

        if (empty($cartItems) || !is_array($cartItems)) {
            return ['ok' => false, 'error' => 'Your cart is empty. Please add items before checking out.'];
        }

        $pdo = getDbConnection();
        if (!$pdo) {
            return ['ok' => false, 'error' => 'Database connection failed. Cannot process checkout.'];
        }

        try {
            $pdo->beginTransaction();

            if ($selectedAddressId) {
                $addrCheckStmt = $pdo->prepare("
                    SELECT `address_id` FROM `user_addresses`
                    WHERE `address_id` = :aid AND `user_id` = :uid AND `is_active` = 1
                    LIMIT 1
                ");
                $addrCheckStmt->execute([':aid' => $selectedAddressId, ':uid' => $userId]);
                if (!$addrCheckStmt->fetchColumn()) {
                    throw new Exception('Selected delivery address does not belong to your account or is archived.');
                }
            } else {
                if ($newAddressLine === '') {
                    throw new Exception('Please select or enter a delivery address.');
                }
                $addrStmt = $pdo->prepare("
                    INSERT INTO `user_addresses` (`user_id`, `address_type`, `address_line`, `pincode`, `is_default`, `is_active`)
                    VALUES (:uid, 'HOME', :line, :pin, 1, 1)
                ");
                $addrStmt->execute([
                    ':uid' => $userId,
                    ':line' => $newAddressLine,
                    ':pin' => $newPincode,
                ]);
                $selectedAddressId = (int)$pdo->lastInsertId();
            }

            $verifiedItems = [];
            $subtotal = 0.00;
            $maxPrepTime = 0;

            $lockStmt = $pdo->prepare("
                SELECT `item_id`, `item_name`, `price`, `stock_quantity`, `is_available`, `prep_time_minutes`
                FROM `menu_items`
                WHERE `item_id` = :id AND `is_active` = 1
                FOR UPDATE
            ");

            foreach ($cartItems as $item) {
                $itemId = (int)($item['id'] ?? $item['item_id'] ?? 0);
                $qty = max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));

                $lockStmt->execute([':id' => $itemId]);
                $dish = $lockStmt->fetch();

                if (!$dish) {
                    throw new Exception("Dish item #{$itemId} not found in active catalog.");
                }
                if (!$dish['is_available'] || (int)$dish['stock_quantity'] < $qty) {
                    throw new Exception("Insufficient stock for '{$dish['item_name']}'. Only {$dish['stock_quantity']} portions remaining.");
                }

                $basePrice = (float)$dish['price'];
                $addonTotal = 0.00;
                $customParts = [];

                if (!empty($item['customizations']) && is_array($item['customizations'])) {
                    $optIds = [];
                    foreach ($item['customizations'] as $c) {
                        if (!empty($c['optionId'])) {
                            $optIds[] = (int)$c['optionId'];
                        } elseif (!empty($c['option_id'])) {
                            $optIds[] = (int)$c['option_id'];
                        }
                    }

                    $cleanOptIds = array_values(array_unique(array_filter(array_map('intval', $optIds), static fn(int $oid): bool => $oid > 0)));
                    if (!empty($cleanOptIds)) {
                        $optPlaceholders = implode(',', array_fill(0, count($cleanOptIds), '?'));
                        $validOptions = dbFetchAll("
                            SELECT o.*, g.group_name
                            FROM `dish_customization_options` o
                            JOIN `dish_customization_groups` g ON o.group_id = g.group_id
                            WHERE o.option_id IN ({$optPlaceholders})
                              AND g.item_id = ?
                              AND o.is_available = 1
                              AND o.is_active = 1
                              AND g.is_active = 1
                        ", array_merge($cleanOptIds, [$itemId]));

                        foreach ($validOptions as $vo) {
                            $optPrice = (float)$vo['price'];
                            $addonTotal += $optPrice;
                            $priceTag = ($optPrice > 0) ? ' (+₹' . number_format($optPrice, 2) . ')' : '';
                            $customParts[] = $vo['option_name'] . $priceTag;
                        }
                    }
                }

                $customText = !empty($customParts)
                    ? implode(', ', $customParts)
                    : (!empty($item['customizationText']) ? cleanInput((string)$item['customizationText']) : null);
                $unitPrice = $basePrice + $addonTotal;
                $lineTotal = round($unitPrice * $qty, 2);
                $subtotal += $lineTotal;
                $maxPrepTime = max($maxPrepTime, (int)$dish['prep_time_minutes']);

                $verifiedItems[] = [
                    'item_id' => $dish['item_id'],
                    'item_name' => $dish['item_name'],
                    'price' => $unitPrice,
                    'base_price' => $basePrice,
                    'customization_total' => $addonTotal,
                    'customizations' => $customText,
                    'qty' => $qty,
                    'line_total' => $lineTotal,
                ];
            }

            $bill = calculateBillTotals($verifiedItems, $couponCode);
            $taxAmount = $bill['gst_amount'];
            $deliveryFee = $bill['delivery_fee'];
            $discountAmount = $bill['discount'];
            $totalAmount = $bill['final_total'];
            $etaMinutes = $maxPrepTime + 15;

            $orderStmt = $pdo->prepare("
                INSERT INTO `orders` (
                    `user_id`, `address_id`, `subtotal`, `tax_amount`,
                    `delivery_fee`, `platform_fee`, `discount_amount`, `total_amount`,
                    `coupon_code`, `order_status`, `is_scheduled`, `scheduled_for`, `scheduled_slot`,
                    `order_notes`, `placed_at`, `estimated_delivery_time`
                ) VALUES (
                    :uid, :aid, :sub, :tax,
                    :del, :plat, :disc, :tot,
                    :coupon, 'CONFIRMED', :is_sched, :sched_for, :sched_slot,
                    :notes, NOW(), :est_delivery
                )
            ");
            $appliedCoupon = !empty($bill['coupon_code']) ? $bill['coupon_code'] : ($couponCode !== '' ? strtoupper(trim($couponCode)) : null);
            $platformFee = (float)($bill['platform_fee'] ?? 5.00);
            $estimatedDelivery = $scheduledFor ?: date('Y-m-d H:i:s', strtotime("+{$etaMinutes} minutes"));

            $orderStmt->execute([
                ':uid' => $userId,
                ':aid' => $selectedAddressId,
                ':sub' => $subtotal,
                ':tax' => $taxAmount,
                ':del' => $deliveryFee,
                ':plat' => $platformFee,
                ':disc' => $discountAmount,
                ':tot' => $totalAmount,
                ':coupon' => $appliedCoupon,
                ':is_sched' => $isScheduled,
                ':sched_for' => $scheduledFor,
                ':sched_slot' => $scheduledSlot,
                ':notes' => $orderNotes,
                ':est_delivery' => $estimatedDelivery,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $orderNumber = '#ZIP-' . $orderId;
            $pdo->prepare("UPDATE `orders` SET `order_number` = :onum WHERE `order_id` = :oid")
                ->execute([':onum' => $orderNumber, ':oid' => $orderId]);

            $itemInsertStmt = $pdo->prepare("
                INSERT INTO `order_items` (`order_id`, `item_id`, `quantity`, `unit_price`, `line_total`, `customizations`, `customization_total`)
                VALUES (:oid, :iid, :qty, :price, :ltot, :custom, :ctot)
            ");
            $stockUpdateStmt = $pdo->prepare("
                UPDATE `menu_items`
                SET `stock_quantity` = `stock_quantity` - :qty
                WHERE `item_id` = :iid
            ");

            foreach ($verifiedItems as $vItem) {
                $itemInsertStmt->execute([
                    ':oid' => $orderId,
                    ':iid' => $vItem['item_id'],
                    ':qty' => $vItem['qty'],
                    ':price' => $vItem['price'],
                    ':ltot' => $vItem['line_total'],
                    ':custom' => $vItem['customizations'] ?? null,
                    ':ctot' => $vItem['customization_total'] ?? 0.00,
                ]);
                $stockUpdateStmt->execute([
                    ':qty' => $vItem['qty'],
                    ':iid' => $vItem['item_id'],
                ]);
            }

            $validModes = ['UPI', 'CREDIT_CARD', 'DEBIT_CARD', 'NET_BANKING', 'COD'];
            if (!in_array($paymentMode, $validModes, true)) {
                $paymentMode = 'UPI';
            }

            if ($paymentMode === 'COD') {
                $txRef = 'COD-' . strtoupper(bin2hex(random_bytes(5)));
                $payStatus = 'PENDING';
            } else {
                $txRef = $razorpayPaymentId !== ''
                    ? $razorpayPaymentId
                    : 'pay_test_' . substr(bin2hex(random_bytes(7)), 0, 14);
                $payStatus = 'SUCCESSFUL';
            }

            $payStmt = $pdo->prepare("
                INSERT INTO `payments` (`order_id`, `payment_mode`, `transaction_reference`, `amount_paid`, `payment_status`, `payment_timestamp`)
                VALUES (:oid, :mode, :tx, :amt, :pstat, NOW())
            ");
            $payStmt->execute([
                ':oid' => $orderId,
                ':mode' => $paymentMode,
                ':tx' => $txRef,
                ':amt' => $totalAmount,
                ':pstat' => $payStatus,
            ]);

            $availableRider = getAvailableDeliveryPartner();
            $assignedRiderId = $availableRider ? (int)$availableRider['user_id'] : null;
            $assignedRiderName = $availableRider ? $availableRider['full_name'] : 'Zippy Fleet Courier Pool';
            $assignedRiderPhone = $availableRider ? ($availableRider['phone_number'] ?? '+91 96320 98765') : '+91 96320 98765';

            $deliveryOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $dispStmt = $pdo->prepare("
                INSERT INTO `delivery_dispatches` (
                    `order_id`, `rider_id`, `delivery_partner_name`, `delivery_partner_phone`,
                    `delivery_otp`, `assigned_at`, `delivery_status`,
                    `current_latitude`, `current_longitude`, `delivery_latitude`, `delivery_longitude`,
                    `route_progress`, `distance_remaining_km`, `estimated_minutes_remaining`
                ) VALUES (
                    :oid, :rid, :rname, :rphone,
                    :otp, NOW(), 'ASSIGNED',
                    12.97840000, 77.64080000, 12.96980000, 77.64990000,
                    0, 2.40, :eta
                )
            ");
            $dispStmt->execute([
                ':oid' => $orderId,
                ':rid' => $assignedRiderId,
                ':rname' => $assignedRiderName,
                ':rphone' => $assignedRiderPhone,
                ':otp' => $deliveryOtp,
                ':eta' => $etaMinutes,
            ]);

            $auditNewStatus = 'CONFIRMED';
            $pdo->prepare("
                INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`)
                VALUES (:oid, 'PLACED', :astat, :uid, NOW())
            ")->execute([
                ':oid' => $orderId,
                ':astat' => $auditNewStatus,
                ':uid' => $userId,
            ]);

            $pdo->commit();

            try {
                if ($paymentMode === 'COD') {
                    recordMoneyTransaction(
                        $orderId,
                        $userId,
                        'CUSTOMER_PAYMENT_COD',
                        'INWARD',
                        'COD_CASH',
                        (float)$totalAmount,
                        'PENDING',
                        $txRef,
                        'Pending Delivery Courier',
                        "Order {$orderNumber} Cash on Delivery pending doorstep collection."
                    );
                } else {
                    $mappedMethod = in_array($paymentMode, ['CREDIT_CARD', 'DEBIT_CARD', 'NET_BANKING'], true) ? $paymentMode : 'UPI';
                    recordMoneyTransaction(
                        $orderId,
                        $userId,
                        'CUSTOMER_PAYMENT_ONLINE',
                        'INWARD',
                        $mappedMethod,
                        (float)$totalAmount,
                        'COMPLETED',
                        $txRef,
                        'Razorpay Gateway / Online Pay',
                        "Order {$orderNumber} online prepayment received from customer."
                    );
                }
            } catch (Throwable $te) {
                error_log("Money transaction recording error for Order #{$orderId}: " . $te->getMessage());
            }

            return ['ok' => true, 'order_id' => $orderId];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => 'Transaction Rolled Back: ' . $e->getMessage()];
        }
    }

    /**
     * Fetch order and line items for GST tax invoice (`customer/invoice.php`).
     *
     * @return array{order_id: int, order: array<string, mixed>|null, items: array<int, array<string, mixed>>, authorized: bool, cgst: float, sgst: float}
     */
    public static function getOrderInvoiceData(int $requestedOrderId, int $viewerUserId): array
    {
        $orderId = $requestedOrderId;
        if ($orderId <= 0) {
            $latestUserOrder = dbFetchOne("SELECT `order_id` FROM `orders` WHERE `user_id` = :uid ORDER BY `order_id` DESC LIMIT 1", [':uid' => $viewerUserId]);
            $orderId = $latestUserOrder ? (int)$latestUserOrder['order_id'] : 0;
        }

        $order = $orderId > 0 ? dbFetchOne("
            SELECT o.*, u.full_name, u.phone_number, u.email,
                   a.address_line, a.pincode,
                   p.payment_mode, p.transaction_reference, p.payment_status, p.payment_timestamp
            FROM `orders` o
            JOIN `users` u ON o.user_id = u.user_id
            LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
            LEFT JOIN `payments` p ON o.order_id = p.order_id
            WHERE o.order_id = :id
        ", [':id' => $orderId]) : null;

        if (!$order) {
            return ['order_id' => $orderId, 'order' => null, 'items' => [], 'authorized' => true, 'cgst' => 0.0, 'sgst' => 0.0];
        }

        if (!canAccessOrder($order, $viewerUserId)) {
            return ['order_id' => $orderId, 'order' => $order, 'items' => [], 'authorized' => false, 'cgst' => 0.0, 'sgst' => 0.0];
        }

        $items = dbFetchAll("
            SELECT oi.*, m.item_name
            FROM `order_items` oi
            JOIN `menu_items` m ON oi.item_id = m.item_id
            WHERE oi.order_id = :id
        ", [':id' => $orderId]);

        return [
            'order_id' => $orderId,
            'order' => $order,
            'items' => $items,
            'authorized' => true,
            'cgst' => round((float)$order['tax_amount'] / 2, 2),
            'sgst' => round((float)$order['tax_amount'] / 2, 2),
        ];
    }

    /**
     * Fetch confirmation summary for `customer/order-success.php`.
     *
     * @return array{order_id: int, order: array<string, mixed>|null, order_items: array<int, array<string, mixed>>}
     */
    public static function getOrderSuccessData(int $requestedOrderId, int $viewerUserId): array
    {
        $orderId = $requestedOrderId;
        if ($orderId <= 0) {
            $latestUserOrder = dbFetchOne("SELECT `order_id` FROM `orders` WHERE `user_id` = :uid ORDER BY `order_id` DESC LIMIT 1", [':uid' => $viewerUserId]);
            $orderId = $latestUserOrder ? (int)$latestUserOrder['order_id'] : 0;
        }

        $order = $orderId > 0 ? dbFetchOne("
            SELECT o.*, u.full_name, u.phone_number, u.email,
                   a.address_line, a.pincode,
                   p.payment_mode, p.transaction_reference, p.payment_status,
                   d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp, d.delivery_status
            FROM `orders` o
            JOIN `users` u ON o.user_id = u.user_id
            LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
            LEFT JOIN `payments` p ON o.order_id = p.order_id
            LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
            WHERE o.order_id = :id
        ", [':id' => $orderId]) : null;

        if (!$order || !canAccessOrder($order, $viewerUserId)) {
            return ['order_id' => $orderId, 'order' => null, 'order_items' => []];
        }

        $orderItems = dbFetchAll("
            SELECT oi.*, m.item_name, m.is_veg, m.image_url
            FROM `order_items` oi
            JOIN `menu_items` m ON oi.item_id = m.item_id
            WHERE oi.order_id = :id
        ", [':id' => $orderId]);

        return [
            'order_id' => $orderId,
            'order' => $order,
            'order_items' => $orderItems,
        ];
    }

    /**
     * Cancel an order within the 60-second grace window atomically and restock menu items.
     *
     * @return array{ok: bool, flash_type: string, message: string, tab: string}
     */
    public static function cancelCustomerOrder(int $orderId, int $actorUserId, bool $isAdmin = false): array
    {
        $orderToCancel = dbFetchOne("SELECT * FROM `orders` WHERE `order_id` = :id", [':id' => $orderId]);
        if (!$orderToCancel) {
            return ['ok' => false, 'flash_type' => 'danger', 'message' => 'Order not found or unauthorized.', 'tab' => 'all'];
        }

        if ((int)$orderToCancel['user_id'] !== $actorUserId && !$isAdmin) {
            return [
                'ok' => false,
                'flash_type' => 'danger',
                'message' => 'Access Denied: You do not have permission to cancel orders belonging to other accounts.',
                'tab' => 'all',
            ];
        }

        $placedTs = (int)strtotime((string)$orderToCancel['placed_at']);
        $elapsedSec = time() - $placedTs;
        if ($elapsedSec > 60) {
            return [
                'ok' => false,
                'flash_type' => 'danger',
                'message' => 'Cancellation window expired: orders can only be cancelled within 60s of placement. The kitchen has already started cooking.',
                'tab' => 'ongoing',
            ];
        }

        if (!in_array($orderToCancel['order_status'], ['PLACED', 'CONFIRMED'], true)) {
            return [
                'ok' => false,
                'flash_type' => 'danger',
                'message' => 'Order cannot be cancelled as kitchen preparation has already begun.',
                'tab' => 'ongoing',
            ];
        }

        try {
            dbTransactional(static function () use ($orderId, $actorUserId): void {
                $lockedOrder = dbFetchOne("SELECT `order_id`, `order_status`, `user_id` FROM `orders` WHERE `order_id` = :id FOR UPDATE", [':id' => $orderId]);
                if (!$lockedOrder || !in_array($lockedOrder['order_status'], ['PLACED', 'CONFIRMED'], true)) {
                    throw new RuntimeException('Order state changed before cancellation completed.');
                }

                dbQuery("UPDATE `orders` SET `order_status` = 'CANCELLED' WHERE `order_id` = :id", [':id' => $orderId]);

                // Lock menu_items in ascending item_id order before restoring stock quantities
                $orderItems = dbFetchAll(
                    "SELECT `item_id`, `quantity` FROM `order_items` WHERE `order_id` = :id ORDER BY `item_id` ASC",
                    [':id' => $orderId]
                );
                if (!empty($orderItems)) {
                    $itemIds = array_values(array_unique(array_map(static fn(array $r): int => (int)$r['item_id'], $orderItems)));
                    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
                    dbFetchAll("SELECT `item_id` FROM `menu_items` WHERE `item_id` IN ({$placeholders}) ORDER BY `item_id` ASC FOR UPDATE", $itemIds);

                    foreach ($orderItems as $oi) {
                        dbQuery(
                            "UPDATE `menu_items` SET `stock_quantity` = `stock_quantity` + :qty, `is_available` = 1 WHERE `item_id` = :iid",
                            [':qty' => (int)$oi['quantity'], ':iid' => (int)$oi['item_id']]
                        );
                    }
                }

                dbQuery("UPDATE `payments` SET `payment_status` = 'REFUNDED', `payment_timestamp` = NOW() WHERE `order_id` = :id", [':id' => $orderId]);

                dbQuery("
                    INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`)
                    VALUES (:oid, :old, 'CANCELLED', :uid, NOW())
                ", [
                    ':oid' => $orderId,
                    ':old' => $lockedOrder['order_status'],
                    ':uid' => $actorUserId > 0 ? $actorUserId : (int)$lockedOrder['user_id'],
                ]);
            });

            return [
                'ok' => true,
                'flash_type' => 'success',
                'message' => "Order #ZIP-{$orderId} cancelled within 60s. 100% refund has been credited to your payment method and inventory restored.",
                'tab' => 'cancelled',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'flash_type' => 'danger',
                'message' => 'Cancellation error: ' . $e->getMessage(),
                'tab' => 'ongoing',
            ];
        }
    }

    /**
     * Fetch categorized order history and aggregate metrics for `customer/order-history.php`.
     *
     * @return array<string, mixed>
     */
    public static function getCustomerOrderHistoryData(int $userId): array
    {
        $orders = dbFetchAll("
            SELECT o.*,
                   COUNT(oi.order_item_id) AS total_items_count,
                   d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp, d.delivery_status, d.delivered_at,
                   p.payment_mode, p.payment_status, p.transaction_reference, p.amount_paid,
                   ua.address_type, ua.address_line, ua.landmark, ua.pincode,
                   r.food_rating, r.rider_rating, r.comments AS review_comments
            FROM `orders` o
            LEFT JOIN `order_items` oi ON o.order_id = oi.order_id
            LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
            LEFT JOIN `payments` p ON o.order_id = p.order_id
            LEFT JOIN `user_addresses` ua ON o.address_id = ua.address_id
            LEFT JOIN `order_reviews` r ON o.order_id = r.order_id
            WHERE o.user_id = :uid
            GROUP BY o.order_id, d.dispatch_id, p.payment_id, ua.address_id, r.review_id
            ORDER BY o.placed_at DESC
        ", [':uid' => $userId]);

        $orderItemsGrouped = [];
        if (!empty($orders)) {
            $orderIds = array_column($orders, 'order_id');
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $itemsRaw = dbFetchAll("
                SELECT oi.*, m.item_name, m.is_veg, m.price AS current_price, m.image_url
                FROM `order_items` oi
                JOIN `menu_items` m ON oi.item_id = m.item_id
                WHERE oi.order_id IN ({$placeholders})
                ORDER BY oi.order_item_id ASC
            ", $orderIds);

            foreach ($itemsRaw as $item) {
                $orderItemsGrouped[$item['order_id']][] = $item;
            }
        }

        $ongoingStatuses = ['PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY'];
        $allOrders = $orders;
        $ongoingOrders = array_values(array_filter($orders, static fn(array $o): bool => in_array($o['order_status'], $ongoingStatuses, true)));
        $scheduledOrders = array_values(array_filter($orders, static fn(array $o): bool => !empty($o['is_scheduled'])));
        $completedOrders = array_values(array_filter($orders, static fn(array $o): bool => $o['order_status'] === 'DELIVERED'));
        $cancelledOrders = array_values(array_filter($orders, static fn(array $o): bool => $o['order_status'] === 'CANCELLED'));

        $totalDeliveredSpend = array_reduce(
            $completedOrders,
            static fn(float $sum, array $o): float => $sum + (float)$o['total_amount'],
            0.0
        );

        return [
            'allOrders' => $allOrders,
            'ongoingOrders' => $ongoingOrders,
            'scheduledOrders' => $scheduledOrders,
            'completedOrders' => $completedOrders,
            'cancelledOrders' => $cancelledOrders,
            'orderItemsGrouped' => $orderItemsGrouped,
            'totalOrdersCount' => count($allOrders),
            'ongoingCount' => count($ongoingOrders),
            'scheduledCount' => count($scheduledOrders),
            'completedCount' => count($completedOrders),
            'cancelledCount' => count($cancelledOrders),
            'totalDeliveredSpend' => $totalDeliveredSpend,
        ];
    }

    /**
     * Fetch live order status tracker dataset for `customer/order-status.php`.
     *
     * @return array<string, mixed>
     */
    public static function getOrderStatusData(?int $requestedOrderId, int $uid): array
    {
        $orderId = 0;
        if ($requestedOrderId === null) {
            $activeOrder = dbFetchOne(
                "SELECT `order_id` FROM `orders` WHERE `user_id` = :uid AND `order_status` IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY') ORDER BY `order_id` DESC LIMIT 1",
                [':uid' => $uid]
            );
            if ($activeOrder) {
                $orderId = (int)$activeOrder['order_id'];
            } else {
                $latestUserOrder = dbFetchOne(
                    "SELECT `order_id` FROM `orders` WHERE `user_id` = :uid ORDER BY `order_id` DESC LIMIT 1",
                    [':uid' => $uid]
                );
                $orderId = $latestUserOrder ? (int)$latestUserOrder['order_id'] : 0;
            }
        } else {
            $orderId = $requestedOrderId;
        }

        $order = $orderId > 0 ? dbFetchOne("
            SELECT o.*, u.full_name, u.phone_number,
                   a.address_line, a.pincode,
                   p.payment_mode, p.payment_status, p.transaction_reference,
                   d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp, d.delivery_status,
                   d.current_latitude, d.current_longitude, d.delivery_latitude, d.delivery_longitude,
                   d.route_progress, d.distance_remaining_km, d.estimated_minutes_remaining
            FROM `orders` o
            JOIN `users` u ON o.user_id = u.user_id
            LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
            LEFT JOIN `payments` p ON o.order_id = p.order_id
            LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
            WHERE o.order_id = :id
        ", [':id' => $orderId]) : null;

        if (!$order) {
            return ['found' => false, 'authorized' => true, 'order' => null];
        }

        if (!canAccessOrder($order, $uid)) {
            return ['found' => true, 'authorized' => false, 'order' => $order];
        }

        $otherActiveOrders = [];
        if ($uid > 0) {
            $otherActiveOrders = dbFetchAll("
                SELECT `order_id`, `order_status`
                FROM `orders`
                WHERE `user_id` = :uid AND `order_status` IN ('CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY') AND `order_id` <> :curId
                ORDER BY `order_id` DESC
            ", [':uid' => $uid, ':curId' => $order['order_id']]);
        }

        $items = dbFetchAll("
            SELECT oi.*, m.item_name, m.is_veg, m.image_url
            FROM `order_items` oi
            JOIN `menu_items` m ON oi.item_id = m.item_id
            WHERE oi.order_id = :id
        ", [':id' => $order['order_id']]);

        $statusReorderPayload = array_map(static fn(array $it): array => [
            'id' => (int)$it['item_id'],
            'name' => $it['item_name'],
            'price' => (float)$it['unit_price'],
            'isVeg' => (bool)$it['is_veg'],
            'restaurant' => 'Meghana Foods',
            'imageUrl' => getDishImageUrl($it['image_url'] ?? ''),
            'qty' => (int)$it['quantity'],
        ], $items);
        $statusReorderJson = htmlspecialchars((string)(json_encode($statusReorderPayload) ?: '[]'), ENT_QUOTES, 'UTF-8');

        $statusMap = [
            'PLACED' => 0,
            'CONFIRMED' => 1,
            'COOKING' => 1,
            'READY' => 2,
            'OUT_FOR_DELIVERY' => 2,
            'DELIVERED' => 3,
        ];
        $statusUpper = strtoupper((string)$order['order_status']);
        $currentStep = $statusMap[$statusUpper] ?? 1;
        $isDelivered = ($statusUpper === 'DELIVERED');
        $isCancelled = ($statusUpper === 'CANCELLED');

        $placedTs = (int)(strtotime((string)$order['placed_at']) ?: time());
        $etaTimestamp = !empty($order['estimated_delivery_time'])
            ? (int)(strtotime((string)$order['estimated_delivery_time']) ?: ($placedTs + 25 * 60))
            : ($placedTs + 25 * 60);
        $remainingSeconds = max(0, $etaTimestamp - time());
        if ($remainingSeconds <= 0 && !$isDelivered && !$isCancelled) {
            $remainingSeconds = 15 * 60;
        }
        $initialCountdownText = sprintf('%02d:%02d', (int)floor($remainingSeconds / 60), $remainingSeconds % 60);

        $existingReview = dbFetchOne("SELECT `review_id` FROM `order_reviews` WHERE `order_id` = :id", [':id' => $order['order_id']]);
        $hasReviewed = !empty($existingReview);
        $cancellationSecondsLeft = max(0, 60 - (time() - $placedTs));
        $canCancel = ($cancellationSecondsLeft > 0 && in_array($statusUpper, ['PLACED', 'CONFIRMED'], true));

        return [
            'found' => true,
            'authorized' => true,
            'order' => $order,
            'orderId' => (int)$order['order_id'],
            'otherActiveOrders' => $otherActiveOrders,
            'items' => $items,
            'statusReorderJson' => $statusReorderJson,
            'currentStep' => $currentStep,
            'isDelivered' => $isDelivered,
            'isCancelled' => $isCancelled,
            'remainingSeconds' => $remainingSeconds,
            'initialCountdownText' => $initialCountdownText,
            'hasReviewed' => $hasReviewed,
            'cancellationSecondsLeft' => $cancellationSecondsLeft,
            'canCancel' => $canCancel,
        ];
    }

    /**
     * Resolve order, ordered items, and existing reviews for `customer/rate-order.php`.
     *
     * @return array{order_id: int, order: array<string, mixed>|null, forbidden: bool, order_items: array<int, array<string, mixed>>, existing_review: array<string, mixed>|null, existing_dish_reviews: array<int|string, array<string, mixed>>}
     */
    public static function getRateOrderData(int $requestedOrderId, int $uid): array
    {
        $orderId = $requestedOrderId;
        if ($orderId <= 0) {
            $recentOrder = dbFetchOne("SELECT `order_id` FROM `orders` WHERE `user_id` = :uid ORDER BY `order_id` DESC LIMIT 1", [':uid' => $uid]);
            if ($recentOrder) {
                $orderId = (int)$recentOrder['order_id'];
            }
        }

        $order = $orderId > 0 ? dbFetchOne("
            SELECT o.*, d.delivery_partner_name, d.delivery_partner_phone
            FROM `orders` o
            LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
            WHERE o.order_id = :id
        ", [':id' => $orderId]) : null;

        if ($order && !canAccessOrder($order, $uid)) {
            return [
                'order_id' => $orderId,
                'order' => null,
                'forbidden' => true,
                'order_items' => [],
                'existing_review' => null,
                'existing_dish_reviews' => [],
            ];
        }

        $orderItems = $order ? dbFetchAll("
            SELECT oi.*, m.item_name, m.image_url, m.is_veg, m.price, m.rating
            FROM `order_items` oi
            JOIN `menu_items` m ON oi.item_id = m.item_id
            WHERE oi.order_id = :oid
        ", [':oid' => $orderId]) : [];

        $existingReview = $order ? dbFetchOne("SELECT * FROM `order_reviews` WHERE `order_id` = :id", [':id' => $orderId]) : null;

        $existingDishReviews = [];
        if ($order) {
            $dishRevRows = dbFetchAll("SELECT * FROM `dish_reviews` WHERE `order_id` = :oid", [':oid' => $orderId]);
            foreach ($dishRevRows as $dr) {
                $existingDishReviews[$dr['item_id']] = $dr;
            }
        }

        return [
            'order_id' => $orderId,
            'order' => $order,
            'forbidden' => false,
            'order_items' => $orderItems,
            'existing_review' => $existingReview,
            'existing_dish_reviews' => $existingDishReviews,
        ];
    }

    /**
     * Persist order and per-dish ratings inside an atomic transaction.
     *
     * @param array<string, mixed> $order
     * @param array<int, array<string, mixed>> $orderItems
     * @param array<string, mixed> $post
     * @param array<string, mixed>|null $existingReview
     * @return array{ok: bool, food_rating?: int, rider_rating?: int, existing_review?: array<string, mixed>|null, message: string}
     */
    public static function submitOrderRating(
        array $order,
        array $orderItems,
        array $post,
        ?array $existingReview
    ): array
    {
        $orderId = (int)$order['order_id'];
        $userId = (int)$order['user_id'];
        $foodRating = max(1, min(5, (int)($post['food_rating'] ?? 5)));
        $riderRating = max(1, min(5, (int)($post['rider_rating'] ?? 5)));
        $tip = max(0.00, (float)($post['tip'] ?? ($existingReview['rider_tip'] ?? 0)));
        $comments = cleanInput((string)($post['comments'] ?? ''));

        $pdo = getDbConnection();
        if (!$pdo) {
            return ['ok' => false, 'message' => 'Failed to save your rating safely. Please try again.'];
        }

        try {
            dbTransactional(static function (PDO $conn) use ($orderId, $userId, $foodRating, $riderRating, $tip, $comments, $orderItems, $post): void {
                $driver = (string)$conn->getAttribute(PDO::ATTR_DRIVER_NAME);
                if ($driver === 'sqlite') {
                    dbQuery("
                        INSERT INTO `order_reviews` (`order_id`, `user_id`, `food_rating`, `rider_rating`, `rider_tip`, `comments`)
                        VALUES (:oid, :uid, :frating, :rrating, :tip, :comm)
                        ON CONFLICT(`order_id`) DO UPDATE SET
                            `food_rating` = excluded.`food_rating`,
                            `rider_rating` = excluded.`rider_rating`,
                            `rider_tip` = excluded.`rider_tip`,
                            `comments` = excluded.`comments`
                    ", [
                        ':oid' => $orderId,
                        ':uid' => $userId,
                        ':frating' => $foodRating,
                        ':rrating' => $riderRating,
                        ':tip' => $tip,
                        ':comm' => $comments,
                    ]);
                } else {
                    dbQuery("
                        INSERT INTO `order_reviews` (`order_id`, `user_id`, `food_rating`, `rider_rating`, `rider_tip`, `comments`)
                        VALUES (:oid, :uid, :frating, :rrating, :tip, :comm)
                        ON DUPLICATE KEY UPDATE
                            `food_rating` = VALUES(`food_rating`),
                            `rider_rating` = VALUES(`rider_rating`),
                            `rider_tip` = VALUES(`rider_tip`),
                            `comments` = VALUES(`comments`)
                    ", [
                        ':oid' => $orderId,
                        ':uid' => $userId,
                        ':frating' => $foodRating,
                        ':rrating' => $riderRating,
                        ':tip' => $tip,
                        ':comm' => $comments,
                    ]);
                }

                if (!empty($orderItems)) {
                    $sortedItems = $orderItems;
                    usort($sortedItems, static fn(array $a, array $b): int => ((int)$a['item_id']) <=> ((int)$b['item_id']));

                    foreach ($sortedItems as $item) {
                        $itemId = (int)$item['item_id'];
                        $dishApp = isset($post['dish_appearance_' . $itemId]) ? max(1, min(5, (int)$post['dish_appearance_' . $itemId])) : $foodRating;
                        $dishTaste = isset($post['dish_taste_' . $itemId]) ? max(1, min(5, (int)$post['dish_taste_' . $itemId])) : $foodRating;
                        $dishPortion = isset($post['dish_portion_' . $itemId]) ? max(1, min(5, (int)$post['dish_portion_' . $itemId])) : $foodRating;
                        $dishValue = isset($post['dish_value_' . $itemId]) ? max(1, min(5, (int)$post['dish_value_' . $itemId])) : $foodRating;
                        $itemRating = isset($post['dish_rating_' . $itemId])
                            ? max(1, min(5, (int)$post['dish_rating_' . $itemId]))
                            : (int)round(($dishApp + $dishTaste + $dishPortion + $dishValue) / 4);

                        dbFetchOne("SELECT `item_id` FROM `menu_items` WHERE `item_id` = :iid FOR UPDATE", [':iid' => $itemId]);

                        $existingDishRev = dbFetchOne("
                            SELECT `dish_review_id` FROM `dish_reviews`
                            WHERE `item_id` = :iid AND `order_id` = :oid AND `user_id` = :uid
                        ", [
                            ':iid' => $itemId,
                            ':oid' => $orderId,
                            ':uid' => $userId,
                        ]);

                        if ($existingDishRev) {
                            dbQuery("
                                UPDATE `dish_reviews`
                                SET `rating` = :r,
                                    `appearance_rating` = :a,
                                    `taste_rating` = :t,
                                    `portion_rating` = :p,
                                    `value_rating` = :v,
                                    `review_text` = :txt
                                WHERE `dish_review_id` = :drid
                            ", [
                                ':r' => $itemRating,
                                ':a' => $dishApp,
                                ':t' => $dishTaste,
                                ':p' => $dishPortion,
                                ':v' => $dishValue,
                                ':txt' => $comments,
                                ':drid' => $existingDishRev['dish_review_id'],
                            ]);
                        } else {
                            dbQuery("
                                INSERT INTO `dish_reviews` (`item_id`, `order_id`, `user_id`, `rating`, `appearance_rating`, `taste_rating`, `portion_rating`, `value_rating`, `review_text`, `tags`)
                                VALUES (:iid, :oid, :uid, :r, :a, :t, :p, :v, :txt, :tags)
                            ", [
                                ':iid' => $itemId,
                                ':oid' => $orderId,
                                ':uid' => $userId,
                                ':r' => $itemRating,
                                ':a' => $dishApp,
                                ':t' => $dishTaste,
                                ':p' => $dishPortion,
                                ':v' => $dishValue,
                                ':txt' => $comments,
                                ':tags' => 'Order Verified',
                            ]);
                        }

                        $stats = dbFetchOne("
                            SELECT AVG(`rating`) AS avg_rating, COUNT(*) AS count_reviews
                            FROM `dish_reviews`
                            WHERE `item_id` = :iid
                        ", [':iid' => $itemId]);

                        if ($stats && $stats['count_reviews']) {
                            $cnt = (int)$stats['count_reviews'];
                            $avgRating = round((float)$stats['avg_rating'], 1);
                            if ($driver === 'sqlite') {
                                dbQuery("
                                    UPDATE `menu_items`
                                    SET `rating` = :r, `total_reviews` = MAX(`total_reviews` + 1, :cnt)
                                    WHERE `item_id` = :iid
                                ", [':r' => $avgRating, ':cnt' => $cnt, ':iid' => $itemId]);
                            } else {
                                dbQuery("
                                    UPDATE `menu_items`
                                    SET `rating` = :r, `total_reviews` = GREATEST(`total_reviews` + 1, :cnt)
                                    WHERE `item_id` = :iid
                                ", [':r' => $avgRating, ':cnt' => $cnt, ':iid' => $itemId]);
                            }
                        }
                    }
                }
            });

            $refreshed = dbFetchOne("SELECT * FROM `order_reviews` WHERE `order_id` = :id", [':id' => $orderId]);
            return [
                'ok' => true,
                'food_rating' => $foodRating,
                'rider_rating' => $riderRating,
                'existing_review' => $refreshed,
                'message' => 'Thank you! Your rating has been recorded successfully. ⭐',
            ];
        } catch (Throwable $e) {
            error_log("Order review transaction failed for order {$orderId}: " . $e->getMessage());
            return ['ok' => false, 'message' => 'Failed to save your rating safely. Please try again.'];
        }
    }

    /**
     * Toggle active dish 86/availability state from Kitchen KDS.
     */
    public static function toggleKitchenDishAvailability(int $itemId, int $isAvailable): void
    {
        dbQuery(
            "UPDATE `menu_items` SET `is_available` = :av WHERE `item_id` = :id AND `is_active` = 1",
            [':av' => $isAvailable, ':id' => $itemId]
        );
    }

    /**
     * Fetch Kitchen Display System swimlanes, rider dispatches, history, chef payouts, and 86 menu.
     *
     * @param array<string, mixed> $currentUser
     * @return array<string, mixed>
     */
    public static function getKitchenDashboardData(array $currentUser): array
    {
        $allActiveOrders = class_exists('WSJFQueue') ? WSJFQueue::getPrioritizedOrders() : [];

        $laneNew = array_values(array_filter($allActiveOrders, static fn(array $o): bool => in_array($o['order_status'], ['PLACED', 'CONFIRMED'], true)));
        $laneCooking = array_values(array_filter($allActiveOrders, static fn(array $o): bool => $o['order_status'] === 'COOKING'));
        $laneReady = array_values(array_filter($allActiveOrders, static fn(array $o): bool => $o['order_status'] === 'READY'));

        autoAllocateReadyOrdersFcfs();

        usort($laneReady, static function (array $a, array $b): int {
            $timeA = (int)(!empty($a['ready_at']) ? strtotime((string)$a['ready_at']) : strtotime((string)$a['placed_at']));
            $timeB = (int)(!empty($b['ready_at']) ? strtotime((string)$b['ready_at']) : strtotime((string)$b['placed_at']));
            if ($timeA === $timeB) {
                return (int)$a['order_id'] <=> (int)$b['order_id'];
            }
            return $timeA <=> $timeB;
        });

        $readyOrderIds = array_column($laneReady, 'order_id');
        $dispatchesByOrder = [];
        if (!empty($readyOrderIds)) {
            $placeholders = implode(',', array_fill(0, count($readyOrderIds), '?'));
            $dispatches = dbFetchAll("SELECT * FROM `delivery_dispatches` WHERE `order_id` IN ({$placeholders})", $readyOrderIds);
            foreach ($dispatches as $d) {
                $dispatchesByOrder[$d['order_id']] = $d;
            }
        }

        $ridersList = getAllDeliveryRiders();

        $orderHistory = dbFetchAll("
            SELECT o.*, u.full_name AS customer_name, u.phone_number AS customer_phone,
                   d.delivery_partner_name, d.delivery_otp, d.dispatched_at, d.delivered_at,
                   p.payment_mode, p.payment_status,
                   TIMESTAMPDIFF(MINUTE, o.placed_at, COALESCE(d.dispatched_at, o.placed_at)) AS prep_minutes
            FROM `orders` o
            JOIN `users` u ON o.user_id = u.user_id
            LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
            LEFT JOIN `payments` p ON o.order_id = p.order_id
            WHERE o.order_status IN ('OUT_FOR_DELIVERY', 'DELIVERED')
            ORDER BY COALESCE(d.dispatched_at, o.placed_at) DESC
            LIMIT 60
        ");

        $historyOrderIds = array_column($orderHistory, 'order_id');
        $historyItemsGrouped = [];
        if (!empty($historyOrderIds)) {
            $pl = implode(',', array_fill(0, count($historyOrderIds), '?'));
            $hItems = dbFetchAll("
                SELECT oi.*, m.item_name, m.is_veg
                FROM `order_items` oi
                JOIN `menu_items` m ON oi.item_id = m.item_id
                WHERE oi.order_id IN ({$pl})
            ", $historyOrderIds);
            foreach ($hItems as $hit) {
                $historyItemsGrouped[$hit['order_id']][] = $hit;
            }
        }

        $chefProfile = dbFetchOne("
            SELECT sp.*, u.full_name, u.email, u.phone_number
            FROM `staff_profiles` sp
            JOIN `users` u ON sp.user_id = u.user_id
            WHERE sp.user_id = :uid
        ", [':uid' => (int)($currentUser['id'] ?? 0)]);

        if (!$chefProfile) {
            $chefProfile = dbFetchOne("
                SELECT sp.*, u.full_name, u.email, u.phone_number
                FROM `staff_profiles` sp
                JOIN `users` u ON sp.user_id = u.user_id
                WHERE sp.department = 'KITCHEN'
                ORDER BY sp.profile_id ASC
                LIMIT 1
            ");
        }

        $chefPayouts = [];
        if ($chefProfile) {
            $chefPayouts = dbFetchAll(
                "SELECT * FROM `staff_salary_payouts` WHERE `user_id` = :uid ORDER BY `paid_at` DESC",
                [':uid' => $chefProfile['user_id']]
            );
        }

        $allMenuItems = dbFetchAll("
            SELECT m.*, c.category_name
            FROM `menu_items` m
            JOIN `categories` c ON m.category_id = c.category_id
            WHERE m.is_active = 1
            ORDER BY c.display_order ASC, m.item_name ASC
        ");

        $scheduledOrders = array_values(array_filter($allActiveOrders, static fn(array $o): bool => !empty($o['is_scheduled'])));
        $countNew = count($laneNew);
        $countCooking = count($laneCooking);
        $countReady = count($laneReady);

        return [
            'allActiveOrders' => $allActiveOrders,
            'laneNew' => $laneNew,
            'laneCooking' => $laneCooking,
            'laneReady' => $laneReady,
            'dispatchesByOrder' => $dispatchesByOrder,
            'ridersList' => $ridersList,
            'orderHistory' => $orderHistory,
            'historyItemsGrouped' => $historyItemsGrouped,
            'chefProfile' => $chefProfile,
            'chefPayouts' => $chefPayouts,
            'recentHandedOver' => $orderHistory,
            'allMenuItems' => $allMenuItems,
            'scheduledOrders' => $scheduledOrders,
            'countScheduled' => count($scheduledOrders),
            'countNew' => $countNew,
            'countCooking' => $countCooking,
            'countReady' => $countReady,
            'countHandedOver' => count($orderHistory),
            'totalActive' => $countNew + $countCooking + $countReady,
            'kdsInitialStateHash' => getKdsStateHash(),
        ];
    }

    /**
     * Fetch printable KOT slip dataset (`kitchen/kot.php`).
     *
     * @param array<int, int> $targetOrderIds
     * @return array{ordersList: array<int, array<string, mixed>>, itemsByOrder: array<int|string, array<int, array<string, mixed>>>}
     */
    public static function getKitchenKotData(array $targetOrderIds): array
    {
        if (empty($targetOrderIds)) {
            return ['ordersList' => [], 'itemsByOrder' => []];
        }

        $placeholders = implode(',', array_fill(0, count($targetOrderIds), '?'));
        $ordersList = dbFetchAll("
            SELECT o.*, u.full_name AS customer_name, u.phone_number AS customer_phone,
                   a.address_line, a.landmark, a.pincode,
                   d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp
            FROM `orders` o
            JOIN `users` u ON o.user_id = u.user_id
            LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
            LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
            WHERE o.order_id IN ({$placeholders})
            ORDER BY o.placed_at ASC
        ", $targetOrderIds);

        if (empty($ordersList)) {
            return ['ordersList' => [], 'itemsByOrder' => []];
        }

        $allItems = dbFetchAll("
            SELECT oi.*, m.item_name, m.is_veg, m.prep_time_minutes, m.kitchen_station
            FROM `order_items` oi
            JOIN `menu_items` m ON oi.item_id = m.item_id
            WHERE oi.order_id IN ({$placeholders})
            ORDER BY oi.order_item_id ASC
        ", $targetOrderIds);

        $itemsByOrder = [];
        foreach ($allItems as $it) {
            $itemsByOrder[$it['order_id']][] = $it;
        }

        return [
            'ordersList' => $ordersList,
            'itemsByOrder' => $itemsByOrder,
        ];
    }

    /**
     * Batch transition selected orders inside an atomic ascending-order row-lock transaction.
     *
     * @param array<int, int> $orderIds
     * @return array{type: string, message: string, warning?: string}
     */
    public static function batchUpdateOrderStatus(
        array $orderIds,
        string $newStatus,
        string $assignedRider,
        ?int $actorUserId
    ): array
    {
        $cleanIds = array_values(array_unique(array_filter(array_map('intval', $orderIds), static fn(int $id): bool => $id > 0)));
        sort($cleanIds);

        if (empty($cleanIds) || $newStatus === '') {
            return ['type' => 'info', 'message' => 'Please select at least one order and target status for batch update.'];
        }

        try {
            $batchOutcome = dbTransactional(static function () use ($cleanIds, $newStatus, $assignedRider, $actorUserId): array {
                $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
                dbFetchAll("SELECT `order_id` FROM `orders` WHERE `order_id` IN ({$placeholders}) ORDER BY `order_id` ASC FOR UPDATE", $cleanIds);

                $successCount = 0;
                $failedOrders = [];
                foreach ($cleanIds as $oid) {
                    $res = transitionOrderStatus($oid, $newStatus, $assignedRider, $actorUserId);
                    if (!empty($res['success'])) {
                        $successCount++;
                    } else {
                        $failedOrders[] = "#ZIP-{$oid}";
                    }
                }

                return ['successCount' => $successCount, 'failedOrders' => $failedOrders];
            });

            $res = [
                'type' => $batchOutcome['successCount'] > 0 ? 'success' : 'warning',
                'message' => $batchOutcome['successCount'] > 0
                    ? "Batch action completed: {$batchOutcome['successCount']} order(s) successfully transitioned to {$newStatus}."
                    : "Notice: " . count($batchOutcome['failedOrders']) . " order(s) could not transition to {$newStatus}.",
            ];
            if ($batchOutcome['successCount'] > 0 && !empty($batchOutcome['failedOrders'])) {
                $res['warning'] = 'Notice: ' . count($batchOutcome['failedOrders']) . ' order(s) could not transition to ' . $newStatus . ' (' . implode(', ', array_slice($batchOutcome['failedOrders'], 0, 4)) . ').';
            }

            return $res;
        } catch (Throwable $e) {
            error_log('Batch status update failed: ' . $e->getMessage());
            return ['type' => 'danger', 'message' => 'Batch status update failed safely and was rolled back. Please try again.'];
        }
    }

    /**
     * Atomically mark a COD order payment as collected/settled by Admin.
     *
     * @return array{ok: bool, flash_type: string, message: string}
     */
    public static function markOrderCodPaid(int $orderId, string $handledByName): array
    {
        try {
            $collectedOrder = dbTransactional(static function () use ($orderId, $handledByName): ?array {
                $order = dbFetchOne("SELECT * FROM `orders` WHERE `order_id` = :id FOR UPDATE", [':id' => $orderId]);
                if (!$order) {
                    return null;
                }

                dbFetchOne("SELECT `payment_id` FROM `payments` WHERE `order_id` = :id FOR UPDATE", [':id' => $orderId]);
                dbQuery("
                    UPDATE `payments`
                    SET `payment_status` = 'SUCCESSFUL', `payment_timestamp` = NOW()
                    WHERE `order_id` = :id
                ", [':id' => $orderId]);

                $existingTx = dbFetchOne("
                    SELECT * FROM `money_transactions`
                    WHERE `order_id` = :id AND `txn_category` = 'CUSTOMER_PAYMENT_COD'
                    FOR UPDATE
                ", [':id' => $orderId]);

                if ($existingTx) {
                    if ($existingTx['status'] !== 'COMPLETED') {
                        dbQuery("
                            UPDATE `money_transactions`
                            SET `status` = 'COMPLETED',
                                `handled_by` = :hby,
                                `notes` = CONCAT(COALESCE(notes, ''), ' [Cash verified and marked collected by Store Admin]')
                            WHERE `txn_id` = :tid
                        ", [
                            ':hby' => $handledByName ?: 'Store Admin',
                            ':tid' => $existingTx['txn_id'],
                        ]);
                    }
                } else {
                    recordMoneyTransaction(
                        $orderId,
                        (int)$order['user_id'],
                        'CUSTOMER_PAYMENT_COD',
                        'INWARD',
                        'COD_CASH',
                        (float)$order['total_amount'],
                        'COMPLETED',
                        'COD-DIRECT-' . $orderId,
                        $handledByName ?: 'Store Admin',
                        "Order #ZIP-{$orderId} Cash on Delivery collected by Store Admin."
                    );
                }

                return $order;
            });

            if ($collectedOrder) {
                return [
                    'ok' => true,
                    'flash_type' => 'success',
                    'message' => 'Cash payment of ' . formatInr($collectedOrder['total_amount']) . " for Order #ZIP-{$orderId} marked as COLLECTED.",
                ];
            }

            return ['ok' => false, 'flash_type' => 'danger', 'message' => "Order #ZIP-{$orderId} not found."];
        } catch (Throwable $e) {
            error_log("mark_cod_paid failed for order {$orderId}: " . $e->getMessage());
            return ['ok' => false, 'flash_type' => 'danger', 'message' => 'Failed to mark COD payment as collected safely. Please try again.'];
        }
    }

    /**
     * Fetch Admin Orders ledger, KPIs, rider dropdowns, and grouped items (`admin/orders.php`).
     *
     * @param array<string, mixed> $orderKpis
     * @return array<string, mixed>
     */
    public static function getAdminOrdersData(
        string $filterStatus,
        string $searchQuery,
        string $paymentFilter,
        array $orderKpis
    ): array
    {
        $conditions = [];
        $params = [];

        $statusConditionMap = [
            'LIVE'             => "o.order_status IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY')",
            'SCHEDULED'        => 'o.is_scheduled = 1',
            'PREPARING'        => "o.order_status IN ('CONFIRMED', 'COOKING')",
            'COOKING'          => "o.order_status = 'COOKING'",
            'CONFIRMED'        => "o.order_status = 'CONFIRMED'",
            'READY'            => "o.order_status = 'READY'",
            'OUT_FOR_DELIVERY' => "o.order_status = 'OUT_FOR_DELIVERY'",
            'DELIVERING'       => "o.order_status = 'OUT_FOR_DELIVERY'",
            'DELIVERED'        => "o.order_status = 'DELIVERED'",
            'CANCELLED'        => "o.order_status = 'CANCELLED'",
        ];
        if (isset($statusConditionMap[$filterStatus])) {
            $conditions[] = $statusConditionMap[$filterStatus];
        }

        if ($paymentFilter !== '') {
            $conditions[] = 'p.payment_mode = :pmode';
            $params[':pmode'] = $paymentFilter;
        }

        if ($searchQuery !== '') {
            $conditions[] = "(
                o.order_id = :sq_id
                OR u.full_name LIKE :sq_name
                OR u.phone_number LIKE :sq_phone
                OR u.email LIKE :sq_email
                OR a.address_line LIKE :sq_addr
                OR a.pincode LIKE :sq_pin
                OR d.delivery_partner_name LIKE :sq_rider
                OR d.delivery_otp LIKE :sq_otp
            )";
            $likeVal = '%' . $searchQuery . '%';
            $params[':sq_id'] = (int)preg_replace('/[^0-9]/', '', $searchQuery);
            $params[':sq_name'] = $likeVal;
            $params[':sq_phone'] = $likeVal;
            $params[':sq_email'] = $likeVal;
            $params[':sq_addr'] = $likeVal;
            $params[':sq_pin'] = $likeVal;
            $params[':sq_rider'] = $likeVal;
            $params[':sq_otp'] = $likeVal;
        }

        $whereClause = !empty($conditions) ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $orders = dbFetchAll("
            SELECT o.*,
                   u.full_name, u.phone_number, u.email,
                   a.address_line, a.landmark, a.pincode,
                   p.payment_mode, p.payment_status, p.transaction_reference,
                   d.rider_id, d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp, d.delivery_status, d.dispatched_at,
                   TIMESTAMPDIFF(MINUTE, o.placed_at, NOW()) AS elapsed_minutes,
                   TIMESTAMPDIFF(MINUTE, COALESCE(d.dispatched_at, o.placed_at), NOW()) AS transit_minutes
            FROM `orders` o
            JOIN `users` u ON o.user_id = u.user_id
            LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
            LEFT JOIN `payments` p ON o.order_id = p.order_id
            LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
            {$whereClause}
            ORDER BY o.placed_at DESC
        ", $params);

        $codPendingOrders = (int)(dbFetchVal(
            "SELECT COUNT(*) FROM `orders` o LEFT JOIN `payments` p ON o.order_id = p.order_id WHERE p.payment_mode = 'COD' AND (p.payment_status IS NULL OR p.payment_status != 'SUCCESSFUL') AND o.order_status NOT IN ('DELIVERED', 'CANCELLED')"
        ) ?: 0);

        $delayedOrders = (int)(dbFetchVal(
            "SELECT COUNT(*) FROM `orders` WHERE order_status IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY') AND TIMESTAMPDIFF(MINUTE, placed_at, NOW()) > 45"
        ) ?: 0);

        $registeredRiders = getAllDeliveryRiders();

        $orderItemsGrouped = [];
        if (!empty($orders)) {
            $orderIds = array_column($orders, 'order_id');
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $itemsRaw = dbFetchAll("
                SELECT oi.*, m.item_name, m.is_veg, m.prep_time_minutes
                FROM `order_items` oi
                JOIN `menu_items` m ON oi.item_id = m.item_id
                WHERE oi.order_id IN ({$placeholders})
            ", $orderIds);

            foreach ($itemsRaw as $it) {
                $orderItemsGrouped[$it['order_id']][] = $it;
            }
        }

        return [
            'cntAll' => $orderKpis['total'],
            'cntLive' => $orderKpis['live'],
            'cntPreparing' => $orderKpis['preparing'],
            'cntReady' => $orderKpis['ready'],
            'cntDelivering' => $orderKpis['delivering'],
            'cntDelivered' => $orderKpis['delivered'],
            'cntCancelled' => $orderKpis['cancelled'],
            'cntScheduled' => $orderKpis['scheduled'] ?? 0,
            'orders' => $orders,
            'codPendingOrders' => $codPendingOrders,
            'delayedOrders' => $delayedOrders,
            'registeredRiders' => $registeredRiders,
            'orderItemsGrouped' => $orderItemsGrouped,
        ];
    }
}
