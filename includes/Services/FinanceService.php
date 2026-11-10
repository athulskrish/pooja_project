<?php
declare(strict_types=1);
/**
 * Finance, Payroll & Executive BI Domain Service
 * Encapsulates monetary ledger transactions, COD settlement, Z-report closures,
 * kitchen staff roster & salary payroll disbursements, and executive BI reporting.
 * Zippy - Online Food Ordering Platform
 */

class FinanceService
{
    /**
     * Fetch all money transactions sorted chronologically descending for CSV export.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getMoneyTransactionsForExport(): array
    {
        return dbFetchAll("SELECT * FROM `money_transactions` ORDER BY `created_at` DESC");
    }

    /**
     * Shared daily register metrics helper (used by both live preview and Z-Report seal).
     *
     * @return array{orders_summary: array<string, mixed>, total_orders: int, gross_sales: float, online_payments: float, expected_cod: float}
     */
    private static function getDailyDrawerMetrics(string $closureDate): array
    {
        $ordSummary = dbFetchOne("
            SELECT COUNT(*) AS total_orders, COALESCE(SUM(total_amount), 0.00) AS gross_sales
            FROM `orders`
            WHERE DATE(`placed_at`) = :dt AND `order_status` != 'CANCELLED'
        ", [':dt' => $closureDate]) ?: ['total_orders' => 0, 'gross_sales' => 0.00];

        $onlinePayments = (float)dbFetchVal("
            SELECT COALESCE(SUM(`amount`), 0.00)
            FROM `money_transactions`
            WHERE DATE(`created_at`) = :dt AND `txn_category` = 'CUSTOMER_PAYMENT_ONLINE' AND `status` = 'COMPLETED'
        ", [':dt' => $closureDate]);

        $expectedCodCash = (float)dbFetchVal("
            SELECT COALESCE(SUM(`amount`), 0.00)
            FROM `money_transactions`
            WHERE DATE(`created_at`) = :dt AND `txn_category` IN ('CUSTOMER_PAYMENT_COD', 'COD_DEPOSIT_STORE') AND `status` = 'COMPLETED'
        ", [':dt' => $closureDate]);

        if ($expectedCodCash <= 0) {
            $expectedCodCash = (float)dbFetchVal("
                SELECT COALESCE(SUM(p.`amount_paid`), 0.00)
                FROM `payments` p
                JOIN `orders` o ON p.`order_id` = o.`order_id`
                WHERE DATE(o.`placed_at`) = :dt AND p.`payment_mode` = 'COD' AND p.`payment_status` = 'SUCCESSFUL'
            ", [':dt' => $closureDate]);
        }

        return [
            'orders_summary' => $ordSummary,
            'total_orders' => (int)($ordSummary['total_orders'] ?? 0),
            'gross_sales' => (float)($ordSummary['gross_sales'] ?? 0.00),
            'online_payments' => $onlinePayments,
            'expected_cod' => $expectedCodCash,
        ];
    }

    /**
     * Handle Admin Financial Transactions POST actions (`mark_cod_collected`, `settle_rider_deposit`, `close_cash_register`).
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $currentUser
     * @return array{type: string, message: string, zreport_id?: int}
     */
    public static function handleFinancialAction(array $post, array $currentUser): array
    {
        $action = (string)($post['action'] ?? '');

        if ($action === 'mark_cod_collected') {
            $txnId = (int)($post['txn_id'] ?? 0);
            $orderId = (int)($post['order_id'] ?? 0);

            try {
                $txResult = dbTransactional(static function () use ($txnId, $orderId, $currentUser): ?array {
                    $tx = dbFetchOne("SELECT * FROM `money_transactions` WHERE `txn_id` = :id FOR UPDATE", [':id' => $txnId]);
                    if (!$tx) {
                        return null;
                    }
                    if ($tx['status'] === 'COMPLETED') {
                        return ['already_completed' => true, 'tx' => $tx];
                    }

                    dbQuery("
                        UPDATE `money_transactions`
                        SET `status` = 'COMPLETED',
                            `handled_by` = :hby,
                            `notes` = CONCAT(COALESCE(`notes`, ''), ' [Cash verified and marked collected by Store Admin]')
                        WHERE `txn_id` = :id
                    ", [
                        ':hby' => $currentUser['name'] ?? 'Store Admin',
                        ':id' => $txnId,
                    ]);

                    if ($orderId > 0) {
                        dbFetchOne("SELECT `payment_id` FROM `payments` WHERE `order_id` = :oid FOR UPDATE", [':oid' => $orderId]);
                        dbQuery("
                            UPDATE `payments`
                            SET `payment_status` = 'SUCCESSFUL', `payment_timestamp` = NOW()
                            WHERE `order_id` = :oid
                        ", [':oid' => $orderId]);
                    }

                    return ['already_completed' => false, 'tx' => $tx];
                });

                if ($txResult) {
                    $tx = $txResult['tx'];
                    if (!empty($txResult['already_completed'])) {
                        return ['type' => 'info', 'message' => "Transaction {$tx['txn_number']} was already marked COMPLETED."];
                    }
                    return ['type' => 'success', 'message' => "Transaction {$tx['txn_number']} marked as COMPLETED. COD cash of " . formatInr($tx['amount']) . ' collected into store balance.'];
                }
                return ['type' => 'danger', 'message' => 'Transaction not found.'];
            } catch (Throwable $e) {
                error_log("mark_cod_collected failed for txn {$txnId}: " . $e->getMessage());
                return ['type' => 'danger', 'message' => 'Failed to mark COD cash as collected safely. Please try again.'];
            }
        }

        if ($action === 'settle_rider_deposit') {
            $riderUserId = (int)($post['rider_user_id'] ?? 0);
            $amount = (float)($post['settle_amount'] ?? 0);

            if ($riderUserId <= 0 || $amount <= 0) {
                return ['type' => 'danger', 'message' => 'Invalid rider or settlement amount specified.'];
            }

            try {
                $depositInfo = dbTransactional(static function () use ($riderUserId, $amount, $currentUser): ?array {
                    $riderProfile = dbFetchOne("
                        SELECT sp.*, u.full_name
                        FROM `staff_profiles` sp
                        JOIN `users` u ON sp.user_id = u.user_id
                        WHERE sp.user_id = :uid
                        FOR UPDATE
                    ", [':uid' => $riderUserId]);

                    if (!$riderProfile) {
                        return null;
                    }

                    $currentCod = (float)$riderProfile['cod_balance'];
                    if ($currentCod <= 0) {
                        return ['empty' => true, 'full_name' => $riderProfile['full_name']];
                    }

                    $settleAmount = min($amount, $currentCod);
                    $ref = 'DEP-STORE-' . strtoupper(bin2hex(random_bytes(4)));
                    recordMoneyTransaction(
                        null,
                        $riderUserId,
                        'COD_DEPOSIT_STORE',
                        'INWARD',
                        'COD_CASH',
                        $settleAmount,
                        'COMPLETED',
                        $ref,
                        (string)($currentUser['name'] ?? 'Store Cashier'),
                        "Cash settled into store vault by rider {$riderProfile['full_name']}."
                    );

                    dbQuery("
                        UPDATE `staff_profiles`
                        SET `cod_balance` = GREATEST(0, `cod_balance` - :amt)
                        WHERE `user_id` = :uid
                    ", [
                        ':amt' => $settleAmount,
                        ':uid' => $riderUserId,
                    ]);

                    return [
                        'empty' => false,
                        'amount' => $settleAmount,
                        'full_name' => $riderProfile['full_name'],
                    ];
                });

                if ($depositInfo) {
                    if (!empty($depositInfo['empty'])) {
                        return ['type' => 'info', 'message' => "No outstanding COD balance to settle for rider {$depositInfo['full_name']}."];
                    }
                    return ['type' => 'success', 'message' => 'Cash deposit of ' . formatInr($depositInfo['amount']) . " from rider {$depositInfo['full_name']} settled into store vault."];
                }
                return ['type' => 'danger', 'message' => 'Rider profile not found.'];
            } catch (Throwable $e) {
                error_log("settle_rider_deposit failed for rider {$riderUserId}: " . $e->getMessage());
                return ['type' => 'danger', 'message' => 'Failed to settle rider cash deposit safely. Please try again.'];
            }
        }

        if ($action === 'close_cash_register') {
            $closureDate = date('Y-m-d');
            $physicalCash = (float)($post['physical_cash'] ?? 0);
            $discrepancyReason = cleanInput((string)($post['discrepancy_notes'] ?? ''));

            try {
                $closureId = (int)dbTransactional(static function (PDO $pdo) use ($closureDate, $physicalCash, $discrepancyReason, $currentUser): int {
                    $metrics = self::getDailyDrawerMetrics($closureDate);
                    $discrepancy = round($physicalCash - $metrics['expected_cod'], 2);

                    dbQuery("
                        INSERT INTO `cash_register_closures`
                        (`closure_date`, `closing_admin_id`, `closing_admin_name`, `total_orders_count`, `gross_sales`, `online_payments_total`, `expected_cod_cash`, `actual_physical_cash`, `discrepancy_amount`, `discrepancy_reason`, `status`, `closed_at`)
                        VALUES
                        (:dt, :aid, :aname, :tot_ord, :gross, :online, :exp_cod, :act_cod, :disc, :reason, 'SEALED', NOW())
                    ", [
                        ':dt' => $closureDate,
                        ':aid' => (int)($currentUser['id'] ?? 1),
                        ':aname' => $currentUser['name'] ?? 'Store Manager',
                        ':tot_ord' => $metrics['total_orders'],
                        ':gross' => $metrics['gross_sales'],
                        ':online' => $metrics['online_payments'],
                        ':exp_cod' => $metrics['expected_cod'],
                        ':act_cod' => $physicalCash,
                        ':disc' => $discrepancy,
                        ':reason' => $discrepancyReason,
                    ]);
                    return (int)$pdo->lastInsertId();
                });

                return [
                    'type' => 'success',
                    'message' => "Cash drawer successfully closed & sealed for {$closureDate}. Z-Report generated (Ref #ZREP-{$closureId}).",
                    'zreport_id' => $closureId,
                ];
            } catch (Throwable $e) {
                error_log("close_cash_register failed for {$closureDate}: " . $e->getMessage());
                return ['type' => 'danger', 'message' => 'Failed to generate and seal Z-Report safely. Please try again.'];
            }
        }

        return ['type' => 'danger', 'message' => 'Unknown financial operation.'];
    }

    /**
     * Fetch Admin Financial Ledger KPIs, paginated transactions, rider COD balances, and Z-Report preview.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public static function getAdminFinancialLedgerData(array $query): array
    {
        $filterCat = cleanInput((string)($query['category'] ?? 'ALL'));
        $filterFlow = cleanInput((string)($query['flow'] ?? 'ALL'));
        $filterStatus = cleanInput((string)($query['status'] ?? 'ALL'));
        $searchQuery = cleanInput((string)($query['search'] ?? ''));
        $dateFrom = cleanInput((string)($query['date_from'] ?? ''));
        $dateTo = cleanInput((string)($query['date_to'] ?? ''));

        $kpiTotalInward = (float)dbFetchVal("SELECT SUM(`amount`) FROM `money_transactions` WHERE `flow_direction` = 'INWARD' AND `status` = 'COMPLETED'");
        $kpiOnlinePaid = (float)dbFetchVal("SELECT SUM(`amount`) FROM `money_transactions` WHERE `txn_category` = 'CUSTOMER_PAYMENT_ONLINE' AND `status` = 'COMPLETED'");
        $kpiCodCollected = (float)dbFetchVal("SELECT SUM(`amount`) FROM `money_transactions` WHERE `txn_category` = 'CUSTOMER_PAYMENT_COD' AND `status` = 'COMPLETED'");
        $kpiCodPending = (float)dbFetchVal("SELECT SUM(`amount`) FROM `money_transactions` WHERE `txn_category` = 'CUSTOMER_PAYMENT_COD' AND `status` = 'PENDING'");
        $kpiSalaryOutward = (float)dbFetchVal("SELECT SUM(`amount`) FROM `money_transactions` WHERE `txn_category` = 'STAFF_SALARY_PAYOUT'");
        $kpiRefundOutward = (float)dbFetchVal("SELECT SUM(`amount`) FROM `money_transactions` WHERE `txn_category` = 'CUSTOMER_REFUND'");

        $conditions = [];
        $params = [];

        if ($filterCat !== 'ALL') {
            $conditions[] = 'mt.txn_category = :cat';
            $params[':cat'] = $filterCat;
        }
        if ($filterFlow !== 'ALL') {
            $conditions[] = 'mt.flow_direction = :flow';
            $params[':flow'] = $filterFlow;
        }
        if ($filterStatus !== 'ALL') {
            $conditions[] = 'mt.status = :st';
            $params[':st'] = $filterStatus;
        }
        if ($dateFrom !== '') {
            $conditions[] = 'DATE(mt.created_at) >= :dfrom';
            $params[':dfrom'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $conditions[] = 'DATE(mt.created_at) <= :dto';
            $params[':dto'] = $dateTo;
        }
        if ($searchQuery !== '') {
            $conditions[] = "(
                mt.txn_number LIKE :search_txn
                OR mt.reference_id LIKE :search_reference
                OR mt.handled_by LIKE :search_handler
                OR mt.notes LIKE :search_notes
                OR u.full_name LIKE :search_name
                OR u.phone_number LIKE :search_phone
                OR mt.payment_method LIKE :search_method
                OR mt.txn_category LIKE :search_category
                OR mt.status LIKE :search_status
                OR mt.order_id = :search_order_id
            )";
            $searchTerm = '%' . $searchQuery . '%';
            $params[':search_txn'] = $searchTerm;
            $params[':search_reference'] = $searchTerm;
            $params[':search_handler'] = $searchTerm;
            $params[':search_notes'] = $searchTerm;
            $params[':search_name'] = $searchTerm;
            $params[':search_phone'] = $searchTerm;
            $params[':search_method'] = $searchTerm;
            $params[':search_category'] = $searchTerm;
            $params[':search_status'] = $searchTerm;
            $params[':search_order_id'] = (int)preg_replace('/[^0-9]/', '', $searchQuery);
        }

        $whereClause = !empty($conditions) ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $totalTransactions = (int)dbFetchVal("
            SELECT COUNT(*)
            FROM `money_transactions` mt
            LEFT JOIN `users` u ON mt.user_id = u.user_id
            {$whereClause}
        ", $params);

        $pageSize = 50;
        $totalPages = max(1, (int)ceil($totalTransactions / $pageSize));
        $currentPage = min(max(1, (int)($query['page'] ?? 1)), $totalPages);
        $offset = ($currentPage - 1) * $pageSize;
        $firstResult = $totalTransactions > 0 ? $offset + 1 : 0;
        $lastResult = min($offset + $pageSize, $totalTransactions);

        $queryParams = array_merge($params, [':limit' => $pageSize, ':offset' => $offset]);
        $transactions = dbFetchAll("
            SELECT mt.*,
                   u.full_name AS counterparty_name,
                   u.phone_number AS counterparty_phone,
                   u.email AS counterparty_email,
                   o.order_status,
                   o.total_amount AS order_total_bill
            FROM `money_transactions` mt
            LEFT JOIN `users` u ON mt.user_id = u.user_id
            LEFT JOIN `orders` o ON mt.order_id = o.order_id
            {$whereClause}
            ORDER BY mt.created_at DESC, mt.txn_id DESC
            LIMIT :limit OFFSET :offset
        ", $queryParams);

        $paginationParams = $query;
        unset($paginationParams['page'], $paginationParams['zreport'], $paginationParams['export']);

        $ridersWithCod = dbFetchAll("
            SELECT sp.*, u.full_name, u.phone_number
            FROM `staff_profiles` sp
            JOIN `users` u ON sp.user_id = u.user_id
            WHERE sp.department = 'DELIVERY' AND sp.cod_balance > 0
            ORDER BY sp.cod_balance DESC
        ");

        $recentClosures = dbFetchAll("SELECT * FROM `cash_register_closures` ORDER BY `closed_at` DESC LIMIT 6");

        $todayClosureDate = date('Y-m-d');
        $todayMetrics = self::getDailyDrawerMetrics($todayClosureDate);

        $activeZReport = null;
        if (isset($query['zreport'])) {
            $activeZReport = dbFetchOne("SELECT * FROM `cash_register_closures` WHERE `closure_id` = :id", [':id' => (int)$query['zreport']]);
        }

        return [
            'filterCat' => $filterCat,
            'filterFlow' => $filterFlow,
            'filterStatus' => $filterStatus,
            'searchQuery' => $searchQuery,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'kpiTotalInward' => $kpiTotalInward,
            'kpiOnlinePaid' => $kpiOnlinePaid,
            'kpiCodCollected' => $kpiCodCollected,
            'kpiCodPending' => $kpiCodPending,
            'kpiSalaryOutward' => $kpiSalaryOutward,
            'kpiRefundOutward' => $kpiRefundOutward,
            'totalTransactions' => $totalTransactions,
            'pageSize' => $pageSize,
            'totalPages' => $totalPages,
            'currentPage' => $currentPage,
            'offset' => $offset,
            'firstResult' => $firstResult,
            'lastResult' => $lastResult,
            'transactions' => $transactions,
            'paginationParams' => $paginationParams,
            'searchFormParams' => [
                'category' => $filterCat,
                'flow' => $filterFlow,
                'status' => $filterStatus,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'ridersWithCod' => $ridersWithCod,
            'recentClosures' => $recentClosures,
            'todayClosureDate' => $todayClosureDate,
            'todayOrdersSummary' => $todayMetrics['orders_summary'],
            'todayOnlinePayments' => $todayMetrics['online_payments'],
            'todayExpectedCod' => $todayMetrics['expected_cod'],
            'activeZReport' => $activeZReport,
        ];
    }

    /**
     * Handle Admin Kitchen Roster & Salary Payroll actions (`toggle_duty`, `disburse_salary`, `update_details`).
     *
     * @param array<string, mixed> $post
     * @return array{type: string, message: string}
     */
    public static function handleAdminKitchenAction(array $post): array
    {
        $action = (string)($post['action'] ?? '');

        if ($action === 'toggle_duty') {
            $profileId = (int)($post['profile_id'] ?? 0);
            $newStatus = cleanInput((string)($post['duty_status'] ?? 'ON_DUTY'));
            dbQuery("UPDATE `staff_profiles` SET `duty_status` = :st WHERE `profile_id` = :id", [
                ':st' => $newStatus,
                ':id' => $profileId,
            ]);
            return ['type' => 'success', 'message' => "Kitchen staff presence status updated to {$newStatus}."];
        }

        if ($action === 'disburse_salary') {
            $userId = (int)($post['user_id'] ?? 0);
            $monthYear = cleanInput((string)($post['month_year'] ?? date('F Y')));
            $baseAmount = (float)($post['base_amount'] ?? 0);
            $incentives = (float)($post['incentives_amount'] ?? 0);
            $deductions = (float)($post['deductions_amount'] ?? 0);
            $netPaid = $baseAmount + $incentives - $deductions;
            $paymentMode = cleanInput((string)($post['payment_mode'] ?? 'BANK_TRANSFER'));
            $notes = cleanInput((string)($post['notes'] ?? 'Monthly kitchen culinary salary disbursement'));
            $ref = 'SAL-KIT-' . date('Ym') . '-' . str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

            try {
                $staffName = dbTransactional(static function () use ($userId, $monthYear, $baseAmount, $incentives, $deductions, $netPaid, $paymentMode, $notes, $ref): string {
                    $staffUser = dbFetchOne("SELECT `full_name` FROM `users` WHERE `user_id` = :id FOR UPDATE", [':id' => $userId]);
                    $staffName = (string)($staffUser['full_name'] ?? 'Kitchen Staff');

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
                        'Store Finance & HR',
                        "Salary payout disbursed to {$staffName} for {$monthYear}"
                    );

                    return $staffName;
                });

                return ['type' => 'success', 'message' => 'Salary of ' . formatInr($netPaid) . " successfully disbursed to {$staffName} (Ref: {$ref})."];
            } catch (Throwable $e) {
                error_log("Kitchen salary disbursement failed for user {$userId}: " . $e->getMessage());
                return ['type' => 'danger', 'message' => 'Failed to disburse kitchen salary safely. Please try again.'];
            }
        }

        if ($action === 'update_details') {
            $profileId = (int)($post['profile_id'] ?? 0);
            $desig = cleanInput((string)($post['designation'] ?? ''));
            $spec = cleanInput((string)($post['specialization'] ?? ''));
            $shift = cleanInput((string)($post['shift_timings'] ?? ''));
            $salary = (float)($post['base_salary'] ?? 0);

            dbQuery("
                UPDATE `staff_profiles`
                SET `designation` = :desig, `specialization` = :spec, `shift_timings` = :shift, `base_salary` = :sal
                WHERE `profile_id` = :id
            ", [
                ':desig' => $desig,
                ':spec' => $spec,
                ':shift' => $shift,
                ':sal' => $salary,
                ':id' => $profileId,
            ]);

            return ['type' => 'success', 'message' => 'Staff details updated successfully.'];
        }

        return ['type' => 'danger', 'message' => 'Unknown kitchen roster action.'];
    }

    /**
     * Fetch Admin Kitchen Roster & Payroll overview dataset (`admin/kitchen-view.php`).
     *
     * @return array<string, mixed>
     */
    public static function getAdminKitchenViewData(): array
    {
        $kitchenStaff = dbFetchAll("
            SELECT sp.*, u.full_name, u.email, u.phone_number, u.created_at AS user_since
            FROM `staff_profiles` sp
            JOIN `users` u ON sp.user_id = u.user_id
            WHERE sp.department = 'KITCHEN'
            ORDER BY sp.base_salary DESC, sp.joined_date ASC
        ");

        $staffIds = array_column($kitchenStaff, 'user_id');
        $latestPayoutsByUser = [];
        $payoutHistory = [];

        if (!empty($staffIds)) {
            $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
            $payoutHistory = dbFetchAll("
                SELECT p.*, u.full_name, u.phone_number, sp.designation
                FROM `staff_salary_payouts` p
                JOIN `users` u ON p.user_id = u.user_id
                JOIN `staff_profiles` sp ON p.user_id = sp.user_id
                WHERE p.user_id IN ({$placeholders})
                ORDER BY p.paid_at DESC
                LIMIT 25
            ", $staffIds);

            $currentMonthStr = date('F Y');
            $currentMonthPayouts = dbFetchAll("
                SELECT * FROM `staff_salary_payouts`
                WHERE `user_id` IN ({$placeholders}) AND `month_year` = ?
            ", array_merge($staffIds, [$currentMonthStr]));

            foreach ($currentMonthPayouts as $cp) {
                $latestPayoutsByUser[$cp['user_id']] = $cp;
            }
        }

        return [
            'kitchenStaff' => $kitchenStaff,
            'latestPayoutsByUser' => $latestPayoutsByUser,
            'payoutHistory' => $payoutHistory,
            'totalStaff' => count($kitchenStaff),
            'presentStaff' => count(array_filter($kitchenStaff, static fn(array $s): bool => $s['duty_status'] === 'ON_DUTY')),
            'totalMonthlyPayroll' => array_sum(array_column($kitchenStaff, 'base_salary')),
            'totalDishesCooked' => (int)dbFetchVal("
                SELECT COALESCE(SUM(oi.quantity), 0)
                FROM `order_items` oi
                JOIN `orders` o ON oi.order_id = o.order_id
                WHERE o.order_status IN ('READY', 'OUT_FOR_DELIVERY', 'DELIVERED')
            "),
        ];
    }

    /**
     * Build Executive BI Sales & Operations Report metrics (`admin/reports.php`).
     *
     * @return array<string, mixed>
     */
    public static function getExecutiveReportData(string $period, string $rawStart, string $rawEnd): array
    {
        $now = new DateTime('now');
        $todayStart = (clone $now)->setTime(0, 0, 0);
        $todayEnd = (clone $now)->setTime(23, 59, 59);
        $isSingleDay = false;

        switch ($period) {
            case 'today':
                $startDate = $todayStart->format('Y-m-d H:i:s');
                $endDate = $todayEnd->format('Y-m-d H:i:s');
                $prevStartDate = (clone $todayStart)->modify('-1 day')->format('Y-m-d H:i:s');
                $prevEndDate = (clone $todayEnd)->modify('-1 day')->format('Y-m-d H:i:s');
                $periodLabel = 'Today (' . $now->format('M j, Y') . ')';
                $comparisonLabel = 'vs yesterday';
                $isSingleDay = true;
                break;

            case 'yesterday':
                $yStart = (clone $todayStart)->modify('-1 day');
                $yEnd = (clone $todayEnd)->modify('-1 day');
                $startDate = $yStart->format('Y-m-d H:i:s');
                $endDate = $yEnd->format('Y-m-d H:i:s');
                $prevStartDate = (clone $yStart)->modify('-1 day')->format('Y-m-d H:i:s');
                $prevEndDate = (clone $yEnd)->modify('-1 day')->format('Y-m-d H:i:s');
                $periodLabel = 'Yesterday (' . $yStart->format('M j, Y') . ')';
                $comparisonLabel = 'vs day before';
                $isSingleDay = true;
                break;

            case '30d':
                $dStart = (clone $todayStart)->modify('-29 days');
                $startDate = $dStart->format('Y-m-d H:i:s');
                $endDate = $todayEnd->format('Y-m-d H:i:s');
                $prevStartDate = (clone $dStart)->modify('-30 days')->format('Y-m-d H:i:s');
                $prevEndDate = (clone $dStart)->modify('-1 second')->format('Y-m-d H:i:s');
                $periodLabel = 'Last 30 Days (' . $dStart->format('M j') . ' - ' . $now->format('M j, Y') . ')';
                $comparisonLabel = 'vs prior 30 days';
                break;

            case 'this_month':
                $mStart = (clone $todayStart)->modify('first day of this month');
                $startDate = $mStart->format('Y-m-d H:i:s');
                $endDate = $todayEnd->format('Y-m-d H:i:s');
                $prevStartDate = (clone $mStart)->modify('first day of previous month')->format('Y-m-d H:i:s');
                $prevEndDate = (clone $mStart)->modify('-1 second')->format('Y-m-d H:i:s');
                $periodLabel = 'This Month (' . $now->format('F Y') . ')';
                $comparisonLabel = 'vs previous month';
                break;

            case 'custom':
                $dStartObj = DateTime::createFromFormat('Y-m-d', $rawStart);
                $dEndObj = DateTime::createFromFormat('Y-m-d', $rawEnd);
                if ($dStartObj && $dEndObj && $dStartObj <= $dEndObj) {
                    $dStartObj->setTime(0, 0, 0);
                    $dEndObj->setTime(23, 59, 59);
                    $startDate = $dStartObj->format('Y-m-d H:i:s');
                    $endDate = $dEndObj->format('Y-m-d H:i:s');
                    $diffDays = (int)$dStartObj->diff($dEndObj)->format('%a') + 1;
                    $prevStartDate = (clone $dStartObj)->modify("-{$diffDays} days")->format('Y-m-d H:i:s');
                    $prevEndDate = (clone $dStartObj)->modify('-1 second')->format('Y-m-d H:i:s');
                    $periodLabel = $dStartObj->format('M j, Y') . ' - ' . $dEndObj->format('M j, Y');
                    $comparisonLabel = "vs prior {$diffDays} days";
                    $isSingleDay = ($diffDays === 1);
                    break;
                }
                // Intentional fallthrough on invalid custom range
            case '7d':
            default:
                $period = '7d';
                $dStart = (clone $todayStart)->modify('-6 days');
                $startDate = $dStart->format('Y-m-d H:i:s');
                $endDate = $todayEnd->format('Y-m-d H:i:s');
                $prevStartDate = (clone $dStart)->modify('-7 days')->format('Y-m-d H:i:s');
                $prevEndDate = (clone $dStart)->modify('-1 second')->format('Y-m-d H:i:s');
                $periodLabel = 'Last 7 Days (' . $dStart->format('M j') . ' - ' . $now->format('M j, Y') . ')';
                $comparisonLabel = 'vs prior 7 days';
                break;
        }

        $kpiSummary = dbFetchOne("
            SELECT
                COUNT(*) AS total_orders,
                COALESCE(SUM(CASE WHEN order_status = 'DELIVERED' THEN 1 ELSE 0 END), 0) AS delivered_orders,
                COALESCE(SUM(CASE WHEN order_status = 'CANCELLED' THEN 1 ELSE 0 END), 0) AS cancelled_orders,
                COALESCE(SUM(CASE WHEN order_status NOT IN ('CANCELLED') THEN 1 ELSE 0 END), 0) AS valid_orders,
                COALESCE(SUM(CASE WHEN order_status NOT IN ('CANCELLED') THEN total_amount ELSE 0 END), 0) AS gross_revenue,
                COALESCE(SUM(CASE WHEN order_status NOT IN ('CANCELLED') THEN subtotal ELSE 0 END), 0) AS net_food_sales,
                COALESCE(SUM(CASE WHEN order_status NOT IN ('CANCELLED') THEN tax_amount ELSE 0 END), 0) AS total_gst,
                COALESCE(SUM(CASE WHEN order_status NOT IN ('CANCELLED') THEN delivery_fee ELSE 0 END), 0) AS total_delivery_fee,
                COALESCE(SUM(CASE WHEN order_status NOT IN ('CANCELLED') THEN discount_amount ELSE 0 END), 0) AS total_discounts,
                COALESCE(SUM(CASE WHEN order_status = 'CANCELLED' THEN total_amount ELSE 0 END), 0) AS cancelled_loss,
                COALESCE(AVG(CASE WHEN order_status NOT IN ('CANCELLED') THEN total_amount ELSE NULL END), 0) AS avg_order_value
            FROM `orders`
            WHERE placed_at >= :start_date AND placed_at <= :end_date
        ", [':start_date' => $startDate, ':end_date' => $endDate]) ?: [];

        $kpiGrossRevenue = (float)($kpiSummary['gross_revenue'] ?? 0);
        $kpiNetFoodSales = (float)($kpiSummary['net_food_sales'] ?? 0);
        $kpiTotalOrders = (int)($kpiSummary['total_orders'] ?? 0);
        $kpiDelivered = (int)($kpiSummary['delivered_orders'] ?? 0);
        $kpiCancelled = (int)($kpiSummary['cancelled_orders'] ?? 0);
        $kpiValidOrders = (int)($kpiSummary['valid_orders'] ?? 0);
        $kpiTotalGst = (float)($kpiSummary['total_gst'] ?? 0);
        $kpiDeliveryFee = (float)($kpiSummary['total_delivery_fee'] ?? 0);
        $kpiDiscounts = (float)($kpiSummary['total_discounts'] ?? 0);
        $kpiCancelledLoss = (float)($kpiSummary['cancelled_loss'] ?? 0);
        $kpiAov = (float)($kpiSummary['avg_order_value'] ?? 0);

        $deliveryRate = $kpiValidOrders > 0 ? round(($kpiDelivered / $kpiValidOrders) * 100, 1) : 0;
        $cancellationRate = $kpiTotalOrders > 0 ? round(($kpiCancelled / $kpiTotalOrders) * 100, 1) : 0;

        $prevGrossRevenue = (float)(dbFetchVal("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM `orders`
            WHERE order_status != 'CANCELLED'
              AND placed_at >= :prev_start
              AND placed_at <= :prev_end
        ", [':prev_start' => $prevStartDate, ':prev_end' => $prevEndDate]) ?: 0.0);

        $revenueGrowthPct = $prevGrossRevenue > 0
            ? (($kpiGrossRevenue - $prevGrossRevenue) / $prevGrossRevenue) * 100
            : ($kpiGrossRevenue > 0 ? 100.0 : 0.0);

        $trendLabels = [];
        $revenueValues = [];
        $orderValues = [];
        $tableRows = [];

        if ($isSingleDay) {
            $hourlyData = dbFetchAll("
                SELECT
                    HOUR(placed_at) AS time_slot,
                    COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN total_amount ELSE 0 END), 0) AS revenue,
                    COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN subtotal ELSE 0 END), 0) AS food_sales,
                    COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN tax_amount ELSE 0 END), 0) AS gst_amount,
                    COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN delivery_fee ELSE 0 END), 0) AS delivery_fee,
                    COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN discount_amount ELSE 0 END), 0) AS discounts,
                    COUNT(*) AS total_orders,
                    COALESCE(SUM(CASE WHEN order_status = 'DELIVERED' THEN 1 ELSE 0 END), 0) AS delivered_count,
                    COALESCE(SUM(CASE WHEN order_status = 'CANCELLED' THEN 1 ELSE 0 END), 0) AS cancelled_count,
                    COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN 1 ELSE 0 END), 0) AS valid_order_count
                FROM `orders`
                WHERE placed_at >= :start_date AND placed_at <= :end_date
                GROUP BY HOUR(placed_at)
                ORDER BY time_slot ASC
            ", [':start_date' => $startDate, ':end_date' => $endDate]);

            $hourlyBySlot = [];
            foreach ($hourlyData as $row) {
                $hourlyBySlot[(int)$row['time_slot']] = $row;
            }

            for ($h = 0; $h < 24; $h++) {
                $slotLabel = sprintf('%02d:00', $h);
                $trendLabels[] = $slotLabel;
                $row = $hourlyBySlot[$h] ?? null;
                $rev = (float)($row['revenue'] ?? 0);
                $orders = (int)($row['valid_order_count'] ?? 0);
                $revenueValues[] = $rev;
                $orderValues[] = $orders;

                if ($row && $row['total_orders'] > 0) {
                    $tableRows[] = [
                        'label' => $slotLabel . ' - ' . sprintf('%02d:59', $h),
                        'total_orders' => (int)$row['total_orders'],
                        'delivered_count' => (int)$row['delivered_count'],
                        'cancelled_count' => (int)$row['cancelled_count'],
                        'food_sales' => (float)$row['food_sales'],
                        'gst_amount' => (float)$row['gst_amount'],
                        'delivery_fee' => (float)$row['delivery_fee'],
                        'discounts' => (float)$row['discounts'],
                        'revenue' => $rev,
                        'aov' => $orders > 0 ? ($rev / $orders) : 0.0,
                    ];
                }
            }
        } else {
            $dailyData = dbFetchAll("
                SELECT
                    DATE(placed_at) AS sales_date,
                    COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN total_amount ELSE 0 END), 0) AS revenue,
                    COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN subtotal ELSE 0 END), 0) AS food_sales,
                    COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN tax_amount ELSE 0 END), 0) AS gst_amount,
                    COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN delivery_fee ELSE 0 END), 0) AS delivery_fee,
                    COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN discount_amount ELSE 0 END), 0) AS discounts,
                    COUNT(*) AS total_orders,
                    COALESCE(SUM(CASE WHEN order_status = 'DELIVERED' THEN 1 ELSE 0 END), 0) AS delivered_count,
                    COALESCE(SUM(CASE WHEN order_status = 'CANCELLED' THEN 1 ELSE 0 END), 0) AS cancelled_count,
                    COALESCE(SUM(CASE WHEN order_status != 'CANCELLED' THEN 1 ELSE 0 END), 0) AS valid_order_count
                FROM `orders`
                WHERE placed_at >= :start_date AND placed_at <= :end_date
                GROUP BY DATE(placed_at)
                ORDER BY sales_date ASC
            ", [':start_date' => $startDate, ':end_date' => $endDate]);

            $dailyByDate = [];
            foreach ($dailyData as $row) {
                $dailyByDate[$row['sales_date']] = $row;
            }

            $curIter = new DateTime($startDate);
            $endIter = new DateTime($endDate);

            while ($curIter <= $endIter) {
                $dateKey = $curIter->format('Y-m-d');
                $trendLabels[] = $curIter->format('M j');
                $row = $dailyByDate[$dateKey] ?? null;
                $rev = (float)($row['revenue'] ?? 0);
                $orders = (int)($row['valid_order_count'] ?? 0);
                $revenueValues[] = $rev;
                $orderValues[] = $orders;

                if ($row && $row['total_orders'] > 0) {
                    $tableRows[] = [
                        'label' => $curIter->format('D, M j, Y'),
                        'total_orders' => (int)$row['total_orders'],
                        'delivered_count' => (int)$row['delivered_count'],
                        'cancelled_count' => (int)$row['cancelled_count'],
                        'food_sales' => (float)$row['food_sales'],
                        'gst_amount' => (float)$row['gst_amount'],
                        'delivery_fee' => (float)$row['delivery_fee'],
                        'discounts' => (float)$row['discounts'],
                        'revenue' => $rev,
                        'aov' => $orders > 0 ? ($rev / $orders) : 0.0,
                    ];
                }
                $curIter->modify('+1 day');
            }
        }

        $displayTableRows = array_reverse($tableRows);

        $categorySales = dbFetchAll("
            SELECT c.category_id,
                   c.category_name,
                   COALESCE(SUM(oi.line_total), 0) AS category_revenue,
                   COALESCE(SUM(oi.quantity), 0) AS items_sold
            FROM `categories` c
            JOIN `menu_items` m ON c.category_id = m.category_id
            JOIN `order_items` oi ON m.item_id = oi.item_id
            JOIN `orders` o ON oi.order_id = o.order_id
            WHERE o.order_status != 'CANCELLED'
              AND o.placed_at >= :start_date AND o.placed_at <= :end_date
            GROUP BY c.category_id, c.category_name
            ORDER BY category_revenue DESC
        ", [':start_date' => $startDate, ':end_date' => $endDate]);

        $totalCategoryRevenue = 0.0;
        $categoryLabels = [];
        $categoryValues = [];
        foreach ($categorySales as $cs) {
            $rev = (float)$cs['category_revenue'];
            $totalCategoryRevenue += $rev;
            $categoryLabels[] = $cs['category_name'];
            $categoryValues[] = $rev;
        }

        $topDishes = dbFetchAll("
            SELECT m.item_id,
                   m.item_name,
                   c.category_name,
                   m.is_veg,
                   m.price,
                   m.rating,
                   COALESCE(SUM(oi.quantity), 0) AS total_units_sold,
                   COALESCE(SUM(oi.line_total), 0) AS total_revenue
            FROM `order_items` oi
            JOIN `orders` o ON oi.order_id = o.order_id
            JOIN `menu_items` m ON oi.item_id = m.item_id
            JOIN `categories` c ON m.category_id = c.category_id
            WHERE o.order_status != 'CANCELLED'
              AND o.placed_at >= :start_date AND o.placed_at <= :end_date
            GROUP BY m.item_id, m.item_name, c.category_name, m.is_veg, m.price, m.rating
            ORDER BY total_revenue DESC
            LIMIT 5
        ", [':start_date' => $startDate, ':end_date' => $endDate]);

        $paymentModes = dbFetchAll("
            SELECT COALESCE(p.payment_mode, 'COD') AS payment_mode,
                   COUNT(*) AS payment_count,
                   COALESCE(SUM(p.amount_paid), 0) AS total_amount
            FROM `payments` p
            JOIN `orders` o ON p.order_id = o.order_id
            WHERE o.order_status != 'CANCELLED'
              AND o.placed_at >= :start_date AND o.placed_at <= :end_date
            GROUP BY p.payment_mode
            ORDER BY total_amount DESC
        ", [':start_date' => $startDate, ':end_date' => $endDate]);

        $paymentModeNames = [
            'UPI' => 'UPI / QR Scan',
            'COD' => 'Cash on Delivery (COD)',
            'CREDIT_CARD' => 'Credit Card',
            'DEBIT_CARD' => 'Debit Card',
            'NET_BANKING' => 'Net Banking',
        ];
        $paymentColorPalette = [
            'UPI' => '#2E6F40',
            'COD' => '#E5A100',
            'CREDIT_CARD' => '#2563EB',
            'DEBIT_CARD' => '#0D9488',
            'NET_BANKING' => '#7C3AED',
        ];

        $totalPaymentAmount = 0.0;
        $paymentLabels = [];
        $paymentValues = [];
        $paymentChartColors = [];
        foreach ($paymentModes as $pm) {
            $mode = $pm['payment_mode'];
            $amt = (float)$pm['total_amount'];
            $totalPaymentAmount += $amt;
            $paymentLabels[] = $paymentModeNames[$mode] ?? $mode;
            $paymentValues[] = $amt;
            $paymentChartColors[] = $paymentColorPalette[$mode] ?? '#68BA7F';
        }

        return [
            'period' => $period,
            'periodLabel' => $periodLabel,
            'comparisonLabel' => $comparisonLabel,
            'isSingleDay' => $isSingleDay,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'prevStartDate' => $prevStartDate,
            'prevEndDate' => $prevEndDate,
            'kpiGrossRevenue' => $kpiGrossRevenue,
            'kpiNetFoodSales' => $kpiNetFoodSales,
            'kpiTotalOrders' => $kpiTotalOrders,
            'kpiDelivered' => $kpiDelivered,
            'kpiCancelled' => $kpiCancelled,
            'kpiValidOrders' => $kpiValidOrders,
            'kpiTotalGst' => $kpiTotalGst,
            'kpiDeliveryFee' => $kpiDeliveryFee,
            'kpiDiscounts' => $kpiDiscounts,
            'kpiCancelledLoss' => $kpiCancelledLoss,
            'kpiAov' => $kpiAov,
            'deliveryRate' => $deliveryRate,
            'cancellationRate' => $cancellationRate,
            'prevGrossRevenue' => $prevGrossRevenue,
            'revenueGrowthPct' => $revenueGrowthPct,
            'trendLabels' => $trendLabels,
            'revenueValues' => $revenueValues,
            'orderValues' => $orderValues,
            'tableRows' => $tableRows,
            'displayTableRows' => $displayTableRows,
            'categorySales' => $categorySales,
            'totalCategoryRevenue' => $totalCategoryRevenue,
            'categoryLabels' => $categoryLabels,
            'categoryValues' => $categoryValues,
            'categoryColors' => ['#2E6F40', '#3b8a52', '#4ea666', '#68BA7F', '#82ca96', '#9ed9af', '#b9e8c7', '#CEFFDC'],
            'topDishes' => $topDishes,
            'paymentModes' => $paymentModes,
            'totalPaymentAmount' => $totalPaymentAmount,
            'paymentLabels' => $paymentLabels,
            'paymentValues' => $paymentValues,
            'paymentModeNames' => $paymentModeNames,
            'paymentColorPalette' => $paymentColorPalette,
            'paymentChartColors' => $paymentChartColors,
        ];
    }
}
