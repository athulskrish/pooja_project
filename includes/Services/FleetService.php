<?php
declare(strict_types=1);
/**
 * Delivery Fleet & Telemetry Domain Service
 * Encapsulates rider duty state, order claims, 6-digit OTP verification,
 * route interpolation telemetry, and admin fleet COD settlement & payouts.
 * Zippy - Online Food Ordering Platform
 */

class FleetService
{
    /**
     * Interpolate latitude and longitude along a multi-segment route given a progress fraction [0, 1].
     *
     * @param array<int, array{lat: float, lng: float}> $route
     * @return array{lat: float, lng: float, heading: float}
     */
    public static function interpolateRoute(array $route, float $progress): array
    {
        $progress = max(0.0, min(1.0, $progress));
        $n = count($route);
        if ($n < 2) {
            $pt = $route[0] ?? [
                'lat' => defined('HUB_LAT') ? (float)HUB_LAT : 12.9784,
                'lng' => defined('HUB_LNG') ? (float)HUB_LNG : 77.6408,
            ];
            return [
                'lat' => round((float)$pt['lat'], 6),
                'lng' => round((float)$pt['lng'], 6),
                'heading' => 135.0,
            ];
        }

        $totalSegments = $n - 1;
        $scaled = $progress * $totalSegments;
        $idx = (int)floor($scaled);
        if ($idx >= $totalSegments) {
            $prev = $route[$n - 2];
            $last = $route[$n - 1];
            $heading = fmod(rad2deg(atan2($last['lng'] - $prev['lng'], $last['lat'] - $prev['lat'])) + 360.0, 360.0);
            return [
                'lat' => round((float)$last['lat'], 6),
                'lng' => round((float)$last['lng'], 6),
                'heading' => round($heading, 1),
            ];
        }

        $segFraction = $scaled - $idx;
        $p1 = $route[$idx];
        $p2 = $route[$idx + 1];

        $lat = $p1['lat'] + ($p2['lat'] - $p1['lat']) * $segFraction;
        $lng = $p1['lng'] + ($p2['lng'] - $p1['lng']) * $segFraction;

        $dLat = $p2['lat'] - $p1['lat'];
        $dLng = $p2['lng'] - $p1['lng'];
        $heading = fmod(rad2deg(atan2($dLng, $dLat)) + 360.0, 360.0);

        return [
            'lat' => round($lat, 6),
            'lng' => round($lng, 6),
            'heading' => round($heading, 1),
        ];
    }

    /**
     * Toggle rider online duty status and trigger FCFS auto-allocation when coming online.
     *
     * @return array{on_duty: bool, message: string}
     */
    public static function toggleRiderDuty(bool $newDuty): array
    {
        $_SESSION['rider_on_duty'] = $newDuty;
        if ($newDuty) {
            autoAllocateReadyOrdersFcfs();
        }

        return [
            'on_duty' => $newDuty,
            'message' => $newDuty
                ? 'You are now ONLINE and accepting dispatches.'
                : 'You are now OFFLINE. Dispatches paused.',
        ];
    }

    /**
     * Atomically claim and pick up an order from kitchen under 1-Driver-1-Delivery policy.
     *
     * @param array<string, mixed> $currentUser
     * @return array{ok: bool, flash_type?: string, message: string, order_id?: int}
     */
    public static function claimOrder(int $orderId, array $currentUser, bool $onDuty): array
    {
        if (!$onDuty) {
            return [
                'ok' => false,
                'flash_type' => 'danger',
                'message' => 'You are currently OFFLINE. Please toggle duty to ONLINE before claiming orders.',
            ];
        }

        $riderId = (int)($currentUser['id'] ?? 0);
        if ($orderId <= 0) {
            $alloc = getDriverAllocatedOrder($riderId);
            if ($alloc) {
                $orderId = (int)$alloc['order_id'];
            }
        }

        if ($orderId <= 0) {
            return [
                'ok' => false,
                'flash_type' => 'danger',
                'message' => 'Order is not ready for pickup yet.',
            ];
        }

        try {
            $claimResult = dbTransactional(static function () use ($orderId, $currentUser, $riderId): array {
                $order = dbFetchOne("SELECT * FROM `orders` WHERE `order_id` = :id FOR UPDATE", [':id' => $orderId]);
                if (!$order || !in_array($order['order_status'], ['READY', 'CONFIRMED', 'COOKING'], true)) {
                    return [
                        'ok' => false,
                        'flash_type' => 'danger',
                        'message' => 'Order is not ready for pickup yet.',
                    ];
                }

                $dispatch = dbFetchOne("SELECT * FROM `delivery_dispatches` WHERE `order_id` = :id FOR UPDATE", [':id' => $orderId]);

                dbFetchOne("SELECT `user_id` FROM `users` WHERE `user_id` = :uid FOR UPDATE", [':uid' => $riderId]);
                $activeExisting = isDriverBusy($riderId, (string)($currentUser['name'] ?? ''), null, true);
                if ($activeExisting) {
                    return [
                        'ok' => false,
                        'flash_type' => 'warning',
                        'message' => "Policy Restriction (1 Driver, 1 Delivery): You are already fulfilling active delivery #ZIP-{$activeExisting['order_id']}. Please complete your current delivery before accepting another order.",
                    ];
                }

                $riderName = (string)($currentUser['name'] ?? 'Suresh Kumar (Rider)');
                $riderPhone = (string)($_SESSION['user_phone'] ?? '+91 96320 98765');

                dbQuery("UPDATE `orders` SET `order_status` = 'OUT_FOR_DELIVERY' WHERE `order_id` = :id", [':id' => $orderId]);

                if ($dispatch) {
                    dbQuery("
                        UPDATE `delivery_dispatches`
                        SET `rider_id` = :rid,
                            `delivery_partner_name` = :rname,
                            `delivery_partner_phone` = :rphone,
                            `dispatched_at` = NOW(),
                            `delivery_status` = 'IN_TRANSIT',
                            `current_latitude` = 12.97840000,
                            `current_longitude` = 77.64080000,
                            `route_progress` = 0,
                            `distance_remaining_km` = 2.40,
                            `estimated_minutes_remaining` = 15
                        WHERE `order_id` = :id
                    ", [
                        ':rid' => $riderId,
                        ':rname' => $riderName,
                        ':rphone' => $riderPhone,
                        ':id' => $orderId,
                    ]);
                } else {
                    $newOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    dbQuery("
                        INSERT INTO `delivery_dispatches`
                        (`order_id`, `rider_id`, `delivery_partner_name`, `delivery_partner_phone`, `delivery_otp`, `assigned_at`, `dispatched_at`, `delivery_status`, `current_latitude`, `current_longitude`, `route_progress`, `distance_remaining_km`, `estimated_minutes_remaining`)
                        VALUES (:id, :rid, :rname, :rphone, :otp, NOW(), NOW(), 'IN_TRANSIT', 12.97840000, 77.64080000, 0, 2.40, 15)
                    ", [
                        ':id' => $orderId,
                        ':rid' => $riderId,
                        ':rname' => $riderName,
                        ':rphone' => $riderPhone,
                        ':otp' => $newOtp,
                    ]);
                }

                dbQuery("
                    INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`)
                    VALUES (:oid, :old, 'OUT_FOR_DELIVERY', :uid, NOW())
                ", [
                    ':oid' => $orderId,
                    ':old' => $order['order_status'],
                    ':uid' => $riderId,
                ]);

                autoAllocateReadyOrdersFcfs();

                return [
                    'ok' => true,
                    'order_id' => $orderId,
                    'message' => "Order #ZIP-{$orderId} claimed and picked up!",
                ];
            });

            return $claimResult;
        } catch (Throwable $e) {
            error_log("Driver claim_order failed for order {$orderId}: " . $e->getMessage());
            return [
                'ok' => false,
                'flash_type' => 'danger',
                'message' => 'Failed to claim order safely. Please try again.',
            ];
        }
    }

    /**
     * Verify 6-digit delivery OTP, complete delivery, settle COD balances, and advance FCFS queue.
     *
     * @param array<string, mixed> $currentUser
     * @return array{ok: bool, flash_type: string, ajax_message: string, flash_message: string}
     */
    public static function verifyDeliveryOtp(int $orderId, string $enteredOtp, array $currentUser): array
    {
        $enteredOtp = trim($enteredOtp);
        $riderId = (int)($currentUser['id'] ?? 0);

        if ($orderId <= 0) {
            return [
                'ok' => false,
                'flash_type' => 'danger',
                'ajax_message' => 'Invalid order ID.',
                'flash_message' => 'Invalid order ID.',
            ];
        }

        try {
            $verifyResult = dbTransactional(static function () use ($orderId, $enteredOtp, $currentUser, $riderId): array {
                $order = dbFetchOne("SELECT `order_id`, `order_status` FROM `orders` WHERE `order_id` = :id FOR UPDATE", [':id' => $orderId]);
                if (!$order) {
                    return ['ok' => false, 'message' => "Order #ZIP-{$orderId} not found."];
                }
                if ($order['order_status'] === 'DELIVERED') {
                    return ['ok' => false, 'message' => "Order #ZIP-{$orderId} is already marked as DELIVERED."];
                }
                if ($order['order_status'] === 'CANCELLED') {
                    return ['ok' => false, 'message' => "Order #ZIP-{$orderId} has been cancelled and cannot be delivered."];
                }

                $dispatch = dbFetchOne("SELECT * FROM `delivery_dispatches` WHERE `order_id` = :id FOR UPDATE", [':id' => $orderId]);
                $expectedOtp = (string)($dispatch['delivery_otp'] ?? '');

                if ($expectedOtp === '' || $enteredOtp !== $expectedOtp) {
                    return ['ok' => false, 'otp_mismatch' => true, 'expected_otp' => $expectedOtp];
                }

                dbQuery("UPDATE `orders` SET `order_status` = 'DELIVERED' WHERE `order_id` = :id", [':id' => $orderId]);
                dbQuery("
                    UPDATE `delivery_dispatches`
                    SET `delivery_status` = 'DELIVERED', `delivered_at` = NOW()
                    WHERE `order_id` = :id
                ", [':id' => $orderId]);

                $payRec = dbFetchOne("SELECT * FROM `payments` WHERE `order_id` = :id FOR UPDATE", [':id' => $orderId]);
                dbQuery("
                    UPDATE `payments`
                    SET `payment_status` = 'SUCCESSFUL', `payment_timestamp` = NOW()
                    WHERE `order_id` = :id AND `payment_status` = 'PENDING'
                ", [':id' => $orderId]);

                dbFetchOne("SELECT `profile_id` FROM `staff_profiles` WHERE `user_id` = :uid FOR UPDATE", [':uid' => $riderId]);

                if ($payRec && $payRec['payment_mode'] === 'COD') {
                    $orderTotal = (float)($payRec['amount_paid'] ?? 0);
                    dbFetchAll("SELECT `txn_id` FROM `money_transactions` WHERE `order_id` = :id AND `txn_category` = 'CUSTOMER_PAYMENT_COD' FOR UPDATE", [':id' => $orderId]);
                    dbQuery("
                        UPDATE `money_transactions`
                        SET `status` = 'COMPLETED', `handled_by` = :hby, `notes` = CONCAT(COALESCE(notes, ''), ' [Cash collected by rider at doorstep]')
                        WHERE `order_id` = :id AND `txn_category` = 'CUSTOMER_PAYMENT_COD'
                    ", [
                        ':hby' => 'Rider: ' . ($currentUser['name'] ?? 'Delivery Partner'),
                        ':id'  => $orderId,
                    ]);
                    dbQuery("
                        UPDATE `staff_profiles`
                        SET `cod_balance` = `cod_balance` + :amt, `total_orders_handled` = `total_orders_handled` + 1
                        WHERE `user_id` = :uid
                    ", [
                        ':amt' => $orderTotal,
                        ':uid' => $riderId,
                    ]);
                } else {
                    dbQuery("
                        UPDATE `staff_profiles`
                        SET `total_orders_handled` = `total_orders_handled` + 1
                        WHERE `user_id` = :uid
                    ", [':uid' => $riderId]);
                }

                dbQuery("
                    INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`)
                    VALUES (:oid, :old, 'DELIVERED', :uid, NOW())
                ", [
                    ':oid' => $orderId,
                    ':old' => $order['order_status'],
                    ':uid' => $riderId,
                ]);

                $awardedPoints = awardLoyaltyPointsForOrder($orderId);

                autoAllocateReadyOrdersFcfs();

                return ['ok' => true, 'points_awarded' => $awardedPoints];
            });
        } catch (Throwable $e) {
            error_log("Driver verify_otp failed for order {$orderId}: " . $e->getMessage());
            return [
                'ok' => false,
                'flash_type' => 'danger',
                'ajax_message' => 'Failed to complete delivery safely. Please try again.',
                'flash_message' => 'Failed to complete delivery safely. Please try again.',
            ];
        }

        if (!empty($verifyResult['ok'])) {
            $earningsInfo = getRiderEarningsSummary($riderId, (string)($currentUser['name'] ?? ''));
            $tripPayoutText = formatInr($earningsInfo['trip_payout_rate']);
            $ptsNote = !empty($verifyResult['points_awarded']) ? " Customer earned {$verifyResult['points_awarded']} loyalty points." : '';

            return [
                'ok' => true,
                'flash_type' => 'success',
                'ajax_message' => "OTP verified! Order #ZIP-{$orderId} delivered successfully. {$tripPayoutText} credited to your fleet wallet.{$ptsNote}",
                'flash_message' => "Order #ZIP-{$orderId} successfully DELIVERED! {$tripPayoutText} payout credited.",
            ];
        }

        if (!empty($verifyResult['otp_mismatch'])) {
            $expectedOtp = (string)$verifyResult['expected_otp'];
            $otherDispatch = dbFetchOne("
                SELECT d.*, o.order_status, o.order_id
                FROM `delivery_dispatches` d
                JOIN `orders` o ON d.order_id = o.order_id
                WHERE d.delivery_otp = :otp
            ", [':otp' => $enteredOtp]);

            if ($otherDispatch) {
                if ((int)$otherDispatch['order_id'] !== $orderId) {
                    if ($otherDispatch['order_status'] === 'DELIVERED') {
                        $deliveredTime = !empty($otherDispatch['delivered_at']) ? date('h:i A', (int)strtotime((string)$otherDispatch['delivered_at'])) : 'earlier';
                        $msg = "Incorrect OTP for Order #ZIP-{$orderId}! You entered '{$enteredOtp}', which belongs to Order #ZIP-{$otherDispatch['order_id']} (which was already delivered at {$deliveredTime}). The correct OTP for Order #ZIP-{$orderId} is '{$expectedOtp}'.";
                    } else {
                        $msg = "Notice: The entered OTP '{$enteredOtp}' belongs to Order #ZIP-{$otherDispatch['order_id']} (Status: {$otherDispatch['order_status']}). The correct OTP for Order #ZIP-{$orderId} is '{$expectedOtp}'.";
                    }
                } else {
                    $msg = "Incorrect OTP code. The OTP for Order #ZIP-{$orderId} is '{$expectedOtp}'.";
                }
            } else {
                $msg = "Incorrect OTP code '{$enteredOtp}'. The correct OTP for Order #ZIP-{$orderId} is '{$expectedOtp}'. Please check the customer screen for Order #ZIP-{$orderId}.";
            }
        } else {
            $msg = (string)($verifyResult['message'] ?? 'OTP verification failed.');
        }

        return [
            'ok' => false,
            'flash_type' => 'danger',
            'ajax_message' => $msg,
            'flash_message' => $msg,
        ];
    }

    /**
     * Fetch complete dashboard state for delivery rider portal (`delivery/index.php`).
     *
     * @param array<string, mixed> $currentUser
     * @return array<string, mixed>
     */
    public static function getRiderDashboardData(array $currentUser): array
    {
        $riderId = (int)($currentUser['id'] ?? 0);
        $riderName = (string)($currentUser['name'] ?? '');

        $activeDelivery = getDriverActiveDelivery($riderId, $riderName);
        $activeOrders = $activeDelivery ? [$activeDelivery] : [];

        $activeOrderItems = [];
        if (!empty($activeOrders)) {
            $orderIds = array_column($activeOrders, 'order_id');
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $rawItems = dbFetchAll("
                SELECT oi.*, m.item_name, m.is_veg
                FROM `order_items` oi
                JOIN `menu_items` m ON oi.item_id = m.item_id
                WHERE oi.order_id IN ($placeholders)
            ", $orderIds);

            foreach ($rawItems as $it) {
                $activeOrderItems[$it['order_id']][] = $it;
            }
        }

        autoAllocateReadyOrdersFcfs();
        $allocatedOrder = empty($activeOrders) ? getDriverAllocatedOrder($riderId) : null;

        $readyOrders = dbFetchAll("
            SELECT o.*, u.full_name AS customer_name, u.phone_number AS customer_phone,
                   a.address_line, a.landmark, a.pincode,
                   p.payment_mode,
                   d.delivery_otp, d.rider_id, d.delivery_status
            FROM `orders` o
            JOIN `users` u ON o.user_id = u.user_id
            LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
            LEFT JOIN `payments` p ON o.order_id = p.order_id
            LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
            WHERE o.order_status = 'READY'
            ORDER BY COALESCE(o.ready_at, o.placed_at) ASC, o.order_id ASC
        ");

        $firstWord = trim(explode(' ', trim($riderName ?: 'Rider'))[0]);
        $historyOrders = dbFetchAll("
            SELECT o.*, u.full_name AS customer_name,
                   a.address_line, a.pincode,
                   p.payment_mode, p.amount_paid,
                   d.delivered_at, d.delivery_otp
            FROM `orders` o
            JOIN `users` u ON o.user_id = u.user_id
            LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
            LEFT JOIN `payments` p ON o.order_id = p.order_id
            LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
            WHERE o.order_status = 'DELIVERED'
              AND (d.rider_id = :rid OR (d.rider_id IS NULL AND d.delivery_partner_name LIKE :rname))
            ORDER BY d.delivered_at DESC, o.placed_at DESC
            LIMIT 20
        ", [
            ':rid' => $riderId,
            ':rname' => '%' . $firstWord . '%',
        ]);

        $earningsSummary = getRiderEarningsSummary($riderId, $riderName);

        return [
            'activeOrders' => $activeOrders,
            'activeOrderItems' => $activeOrderItems,
            'allocatedOrder' => $allocatedOrder,
            'readyOrders' => $readyOrders,
            'historyOrders' => $historyOrders,
            'earningsSummary' => $earningsSummary,
            'deliveredCount' => $earningsSummary['delivered_count'],
            'baseFare' => $earningsSummary['base_rate'],
            'todayEarnings' => $earningsSummary['base_fare_total'] + $earningsSummary['distance_total'],
            'totalTips' => $earningsSummary['tips_total'],
            'totalEarned' => $earningsSummary['total_earned'],
            'totalCashedOut' => $earningsSummary['total_cashed_out'],
            'availableBalance' => $earningsSummary['available_balance'],
            'cashoutHistory' => $earningsSummary['cashout_history'],
        ];
    }

    /**
     * Handle Admin Delivery Fleet management actions (`toggle_duty`, `settle_cod_cash`, `disburse_payout`).
     *
     * @param array<string, mixed> $post
     * @return array{type: string, message: string}
     */
    public static function handleAdminFleetAction(array $post): array
    {
        $action = (string)($post['action'] ?? '');

        if ($action === 'toggle_duty') {
            $profileId = (int)($post['profile_id'] ?? 0);
            $newStatus = cleanInput((string)($post['duty_status'] ?? 'ON_DUTY'));
            dbQuery("UPDATE `staff_profiles` SET `duty_status` = :st WHERE `profile_id` = :id", [
                ':st' => $newStatus,
                ':id' => $profileId,
            ]);
            return ['type' => 'success', 'message' => "Delivery partner presence status updated to {$newStatus}."];
        }

        if ($action === 'settle_cod_cash') {
            $profileId = (int)($post['profile_id'] ?? 0);
            try {
                $settledInfo = dbTransactional(static function () use ($profileId): ?array {
                    $riderProfile = dbFetchOne("
                        SELECT sp.*, u.full_name, u.user_id
                        FROM `staff_profiles` sp
                        JOIN `users` u ON sp.user_id = u.user_id
                        WHERE sp.profile_id = :id
                        FOR UPDATE
                    ", [':id' => $profileId]);

                    if (!$riderProfile || (float)$riderProfile['cod_balance'] <= 0) {
                        return null;
                    }

                    $amountToSettle = (float)$riderProfile['cod_balance'];
                    dbQuery("UPDATE `staff_profiles` SET `cod_balance` = 0.00 WHERE `profile_id` = :id", [':id' => $profileId]);

                    $ref = 'COD-SETTLE-' . date('Ym') . '-' . str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                    recordMoneyTransaction(
                        null,
                        (int)$riderProfile['user_id'],
                        'COD_DEPOSIT_STORE',
                        'INWARD',
                        'COD_CASH',
                        $amountToSettle,
                        'SETTLED',
                        $ref,
                        'Rider: ' . $riderProfile['full_name'],
                        "Rider {$riderProfile['full_name']} deposited collected COD cash of " . formatInr($amountToSettle) . " to store cashier register"
                    );

                    return [
                        'amount' => $amountToSettle,
                        'full_name' => $riderProfile['full_name'],
                    ];
                });

                if ($settledInfo) {
                    return [
                        'type' => 'success',
                        'message' => "COD Cash of " . formatInr($settledInfo['amount']) . " from {$settledInfo['full_name']} successfully deposited to store cash register.",
                    ];
                }
                return ['type' => 'info', 'message' => 'No outstanding COD cash to settle for this rider.'];
            } catch (Throwable $e) {
                error_log("COD settle failed for profile {$profileId}: " . $e->getMessage());
                return ['type' => 'danger', 'message' => 'Failed to settle COD cash safely. Please try again.'];
            }
        }

        if ($action === 'disburse_payout') {
            $userId = (int)($post['user_id'] ?? 0);
            $monthYear = cleanInput((string)($post['month_year'] ?? date('F Y')));
            $baseAmount = (float)($post['base_amount'] ?? 0);
            $incentives = (float)($post['incentives_amount'] ?? 0);
            $deductions = (float)($post['deductions_amount'] ?? 0);
            $netPaid = $baseAmount + $incentives - $deductions;
            $paymentMode = cleanInput((string)($post['payment_mode'] ?? 'UPI'));
            $notes = cleanInput((string)($post['notes'] ?? 'Monthly delivery fleet stipend and trip commission payout'));
            $ref = 'PAY-RID-' . date('Ym') . '-' . str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

            try {
                $staffName = dbTransactional(static function () use ($userId, $monthYear, $baseAmount, $incentives, $deductions, $netPaid, $paymentMode, $notes, $ref): string {
                    $staffUser = dbFetchOne("SELECT `full_name` FROM `users` WHERE `user_id` = :id FOR UPDATE", [':id' => $userId]);
                    $staffName = (string)($staffUser['full_name'] ?? 'Delivery Rider');

                    dbQuery("
                        INSERT INTO `staff_salary_payouts`
                        (`user_id`, `month_year`, `base_amount`, `incentives_amount`, `deductions_amount`, `net_paid`, `payment_mode`, `transaction_reference`, `payout_status`, `paid_at`, `notes`)
                        VALUES (:uid, :my, :base, :inc, :ded, :net, :mode, :ref, 'PAID', NOW(), :notes)
                    ", [
                        ':uid' => $userId,
                        ':my' => $monthYear,
                        ':base' => $baseAmount,
                        ':inc' => $incentives,
                        ':ded' => $deductions,
                        ':net' => $netPaid,
                        ':mode' => $paymentMode,
                        ':ref' => $ref,
                        ':notes' => $notes,
                    ]);

                    recordMoneyTransaction(
                        null,
                        $userId,
                        'STAFF_SALARY_PAYOUT',
                        'OUTWARD',
                        $paymentMode === 'UPI' ? 'UPI' : 'BANK_TRANSFER',
                        $netPaid,
                        'COMPLETED',
                        $ref,
                        'Store Finance & Logistics',
                        "Delivery payout disbursed to {$staffName} for {$monthYear}"
                    );

                    return $staffName;
                });

                return ['type' => 'success', 'message' => "Delivery payout of " . formatInr($netPaid) . " successfully disbursed to {$staffName} (Ref: {$ref})."];
            } catch (Throwable $e) {
                error_log("Rider payout disbursement failed for user {$userId}: " . $e->getMessage());
                return ['type' => 'danger', 'message' => 'Failed to disburse rider payout safely. Please try again.'];
            }
        }

        return ['type' => 'danger', 'message' => 'Unknown fleet operation.'];
    }

    /**
     * Fetch Admin Delivery Fleet Overview data (roster, payout history, live active trips).
     *
     * @return array<string, mixed>
     */
    public static function getAdminDeliveryOverviewData(): array
    {
        $deliveryFleet = dbFetchAll("
            SELECT sp.*, u.full_name, u.email, u.phone_number, u.created_at AS user_since
            FROM `staff_profiles` sp
            JOIN `users` u ON sp.user_id = u.user_id
            WHERE sp.department = 'DELIVERY'
            ORDER BY sp.base_salary DESC, sp.joined_date ASC
        ");

        $riderIds = array_column($deliveryFleet, 'user_id');
        $latestPayoutsByUser = [];
        $payoutHistory = [];

        if (!empty($riderIds)) {
            $placeholders = implode(',', array_fill(0, count($riderIds), '?'));
            $payoutHistory = dbFetchAll("
                SELECT p.*, u.full_name, u.phone_number, sp.designation, sp.vehicle_details
                FROM `staff_salary_payouts` p
                JOIN `users` u ON p.user_id = u.user_id
                JOIN `staff_profiles` sp ON p.user_id = sp.user_id
                WHERE p.user_id IN ($placeholders)
                ORDER BY p.paid_at DESC
                LIMIT 25
            ", $riderIds);

            $currentMonthStr = date('F Y');
            $currentMonthPayouts = dbFetchAll("
                SELECT * FROM `staff_salary_payouts`
                WHERE `user_id` IN ($placeholders) AND `month_year` = ?
            ", array_merge($riderIds, [$currentMonthStr]));

            foreach ($currentMonthPayouts as $cp) {
                $latestPayoutsByUser[$cp['user_id']] = $cp;
            }
        }

        $totalRiders = count($deliveryFleet);
        $onlineRiders = count(array_filter($deliveryFleet, static fn(array $r): bool => in_array($r['duty_status'], ['ON_DUTY', 'ON_BREAK'], true)));
        $totalCodHeld = array_sum(array_column($deliveryFleet, 'cod_balance'));
        $totalDeliveriesCompleted = (int)dbFetchVal("SELECT COUNT(*) FROM `delivery_dispatches` WHERE `delivery_status` = 'DELIVERED'");

        $activeTripsMap = [];
        $busyRidersCount = 0;
        $availableRidersCount = 0;
        foreach ($deliveryFleet as $r) {
            $trip = getDriverActiveDelivery((int)$r['user_id'], (string)$r['full_name']);
            $activeTripsMap[$r['user_id']] = $trip;
            if ($r['duty_status'] === 'ON_DUTY') {
                if ($trip) {
                    $busyRidersCount++;
                } else {
                    $availableRidersCount++;
                }
            }
        }

        $failedDispatches = dbFetchAll("
            SELECT d.*, o.order_status, o.total_amount, u.full_name AS customer_name, u.phone_number AS customer_phone,
                   a.address_line, a.pincode
            FROM `delivery_dispatches` d
            JOIN `orders` o ON d.order_id = o.order_id
            JOIN `users` u ON o.user_id = u.user_id
            LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
            WHERE d.delivery_status = 'FAILED'
              AND o.order_status NOT IN ('DELIVERED', 'CANCELLED')
            ORDER BY d.failed_at DESC
        ");

        return [
            'deliveryFleet' => $deliveryFleet,
            'latestPayoutsByUser' => $latestPayoutsByUser,
            'payoutHistory' => $payoutHistory,
            'totalRiders' => $totalRiders,
            'onlineRiders' => $onlineRiders,
            'totalCodHeld' => $totalCodHeld,
            'totalDeliveriesCompleted' => $totalDeliveriesCompleted,
            'activeTripsMap' => $activeTripsMap,
            'busyRidersCount' => $busyRidersCount,
            'availableRidersCount' => $availableRidersCount,
            'failedDispatches' => $failedDispatches,
        ];
    }

    /**
     * Report a Rider SOS / Delivery Failure on an assigned active delivery.
     * Marks dispatch `FAILED`, releases rider from 1-Driver-1-Delivery lock, logs audit trail,
     * and optionally returns breakdown orders to READY pool for FCFS re-allocation.
     *
     * @param array<string, mixed> $currentUser
     * @return array{ok: bool, flash_type: string, message: string}
     */
    public static function reportDeliveryIssue(
        int $orderId,
        string $reasonCode,
        string $notes,
        array $currentUser
    ): array
    {
        $riderId = (int)($currentUser['id'] ?? 0);
        if ($orderId <= 0 || $riderId <= 0) {
            return ['ok' => false, 'flash_type' => 'danger', 'message' => 'Invalid order or rider session.'];
        }

        $reasonMap = [
            'unreachable' => 'Customer not answering phone (Attempt logged)',
            'wrong_address' => 'Incorrect address / Gate security denied entry',
            'breakdown' => 'Vehicle breakdown / Flat tire',
            'spill' => 'Food container damaged / spilled in transit',
        ];
        $cleanCode = strtolower(trim($reasonCode));
        $reasonLabel = $reasonMap[$cleanCode] ?? cleanInput($reasonCode !== '' ? $reasonCode : 'Delivery Exception');
        $cleanNotes = cleanInput($notes);

        try {
            $res = dbTransactional(static function () use ($orderId, $riderId, $cleanCode, $reasonLabel, $cleanNotes, $currentUser): array {
                $order = dbFetchOne("SELECT `order_id`, `order_status` FROM `orders` WHERE `order_id` = :id FOR UPDATE", [':id' => $orderId]);
                if (!$order) {
                    return ['ok' => false, 'message' => "Order #ZIP-{$orderId} not found."];
                }
                if (in_array($order['order_status'], ['DELIVERED', 'CANCELLED'], true)) {
                    return ['ok' => false, 'message' => "Order #ZIP-{$orderId} is already {$order['order_status']}."];
                }

                $dispatch = dbFetchOne("SELECT * FROM `delivery_dispatches` WHERE `order_id` = :id FOR UPDATE", [':id' => $orderId]);
                if (!$dispatch) {
                    return ['ok' => false, 'message' => "No dispatch record found for #ZIP-{$orderId}."];
                }

                if (!isAdmin() && !isRiderAssignedToOrder($orderId, $riderId, (string)($currentUser['name'] ?? ''))) {
                    return ['ok' => false, 'message' => "Forbidden: You are not assigned to Order #ZIP-{$orderId}."];
                }

                dbQuery("
                    UPDATE `delivery_dispatches`
                    SET `delivery_status` = 'FAILED',
                        `failure_reason` = :reason,
                        `issue_notes` = :notes,
                        `failed_at` = NOW()
                    WHERE `order_id` = :oid
                ", [
                    ':reason' => $reasonLabel,
                    ':notes' => $cleanNotes,
                    ':oid' => $orderId,
                ]);

                // If rider had a vehicle breakdown, pause rider on break, clear rider_id, return order to READY FCFS pool
                $auditTarget = 'DELIVERY_FAILED (' . strtoupper($cleanCode) . ')';
                if ($cleanCode === 'breakdown') {
                    dbQuery("UPDATE `orders` SET `order_status` = 'READY', `ready_at` = COALESCE(`ready_at`, NOW()) WHERE `order_id` = :oid", [':oid' => $orderId]);
                    dbQuery("
                        UPDATE `delivery_dispatches`
                        SET `rider_id` = NULL,
                            `delivery_partner_name` = 'Zippy Express Rider',
                            `delivery_partner_phone` = '+91 98765 43210',
                            `delivery_status` = 'ASSIGNED'
                        WHERE `order_id` = :oid
                    ", [':oid' => $orderId]);
                    dbQuery("UPDATE `staff_profiles` SET `duty_status` = 'ON_BREAK' WHERE `user_id` = :uid", [':uid' => $riderId]);
                    $_SESSION['rider_on_duty'] = false;
                    $auditTarget = 'READY (REQUEUED: BREAKDOWN)';
                }

                dbQuery("
                    INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`)
                    VALUES (:oid, :old, :new, :uid, NOW())
                ", [
                    ':oid' => $orderId,
                    ':old' => $order['order_status'],
                    ':new' => $auditTarget,
                    ':uid' => $riderId,
                ]);

                if ($cleanCode === 'breakdown') {
                    autoAllocateReadyOrdersFcfs();
                }

                return ['ok' => true, 'requeued' => ($cleanCode === 'breakdown')];
            });

            if (empty($res['ok'])) {
                return ['ok' => false, 'flash_type' => 'danger', 'message' => (string)($res['message'] ?? 'Unable to report SOS.')];
            }

            $extraMsg = !empty($res['requeued'])
                ? ' Order returned to READY FCFS dispatch pool for immediate pickup by another free rider.'
                : ' Hub operations triage has been alerted and your active slot is released.';

            return [
                'ok' => true,
                'flash_type' => 'warning',
                'message' => "SOS recorded for #ZIP-{$orderId}: {$reasonLabel}.{$extraMsg}",
            ];
        } catch (Throwable $e) {
            error_log("reportDeliveryIssue failed for order {$orderId}: " . $e->getMessage());
            return ['ok' => false, 'flash_type' => 'danger', 'message' => 'Failed to transmit delivery SOS safely. Please try again.'];
        }
    }

    /**
     * Admin action: Re-queue a failed delivery dispatch back into READY FCFS pool.
     *
     * @return array{ok: bool, flash_type: string, message: string}
     */
    public static function requeueFailedDispatch(int $orderId, int $adminUserId): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'flash_type' => 'danger', 'message' => 'Invalid order ID.'];
        }

        try {
            $ok = dbTransactional(static function () use ($orderId, $adminUserId): bool {
                $order = dbFetchOne("SELECT `order_id`, `order_status` FROM `orders` WHERE `order_id` = :oid FOR UPDATE", [':oid' => $orderId]);
                if (!$order || in_array($order['order_status'], ['DELIVERED', 'CANCELLED'], true)) {
                    return false;
                }

                dbFetchOne("SELECT `dispatch_id` FROM `delivery_dispatches` WHERE `order_id` = :oid FOR UPDATE", [':oid' => $orderId]);
                dbQuery("UPDATE `orders` SET `order_status` = 'READY', `ready_at` = COALESCE(`ready_at`, NOW()) WHERE `order_id` = :oid", [':oid' => $orderId]);
                dbQuery("
                    UPDATE `delivery_dispatches`
                    SET `rider_id` = NULL,
                        `delivery_partner_name` = 'Zippy Express Rider',
                        `delivery_partner_phone` = '+91 98765 43210',
                        `delivery_status` = 'ASSIGNED',
                        `failure_reason` = NULL,
                        `issue_notes` = NULL,
                        `failed_at` = NULL
                    WHERE `order_id` = :oid
                ", [':oid' => $orderId]);

                dbQuery("
                    INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`)
                    VALUES (:oid, :old, 'READY', :uid, NOW())
                ", [
                    ':oid' => $orderId,
                    ':old' => $order['order_status'],
                    ':uid' => $adminUserId > 0 ? $adminUserId : null,
                ]);

                autoAllocateReadyOrdersFcfs();
                return true;
            });

            return [
                'ok' => $ok,
                'flash_type' => $ok ? 'success' : 'danger',
                'message' => $ok
                    ? "Order #ZIP-{$orderId} reset to READY and re-entered into FCFS dispatch queue."
                    : "Unable to re-queue order #ZIP-{$orderId}.",
            ];
        } catch (Throwable $e) {
            error_log("requeueFailedDispatch error for #{$orderId}: " . $e->getMessage());
            return ['ok' => false, 'flash_type' => 'danger', 'message' => 'Failed to re-dispatch order safely.'];
        }
    }
}
