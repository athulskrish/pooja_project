<?php
declare(strict_types=1);
/**
 * Administration, Governance, RBAC & Knowledge Base Domain Service
 * Encapsulates dashboard KPI telemetry, order audit trail queries,
 * customer/dish review analytics, FAQ management (with soft delete),
 * user RBAC provisioning/filtering, and customer profile account updates.
 * Zippy - Online Food Ordering Platform
 */

class AdminService
{
    /**
     * Fetch Executive Operations Dashboard metrics (`admin/index.php`).
     *
     * @return array<string, mixed>
     */
    public static function getOperationsDashboardData(): array
    {
        $triageExceptions = class_exists('FCFSQueue') ? FCFSQueue::getTriageExceptions() : [];
        usort($triageExceptions, static function (array $a, array $b): int {
            $rank = ['CRITICAL' => 3, 'WARNING' => 2, 'INFO' => 1];
            return ($rank[$b['severity']] ?? 0) <=> ($rank[$a['severity']] ?? 0);
        });

        $lowStockCount = (int)(dbFetchVal(
            "SELECT COUNT(*) FROM `menu_items` WHERE `stock_quantity` <= 15 AND `is_available` = 1 AND `is_active` = 1"
        ) ?: 0);

        $liveOrdersCount = (int)(dbFetchVal(
            "SELECT COUNT(*) FROM `orders` WHERE `order_status` IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY', 'DELIVERING')"
        ) ?: 0);

        $ordersAtRisk = (int)(dbFetchVal(
            "SELECT COUNT(*) FROM `orders` WHERE `order_status` IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY') AND TIMESTAMPDIFF(MINUTE, `placed_at`, NOW()) > 30"
        ) ?: 0);

        $codPending = (int)(dbFetchVal(
            "SELECT COUNT(*) FROM `money_transactions` WHERE `txn_category` = 'CUSTOMER_PAYMENT_COD' AND `status` != 'COMPLETED'"
        ) ?: 0);

        $deliveryDelays = (int)(dbFetchVal(
            "SELECT COUNT(*) FROM `orders` WHERE `order_status` IN ('OUT_FOR_DELIVERY', 'DELIVERING') AND TIMESTAMPDIFF(MINUTE, `placed_at`, NOW()) > 45"
        ) ?: 0);

        $scheduledOrdersCount = (int)(dbFetchVal(
            "SELECT COUNT(*) FROM `orders` WHERE `is_scheduled` = 1 AND `order_status` NOT IN ('DELIVERED', 'CANCELLED')"
        ) ?: 0);

        $upcomingScheduledList = dbFetchAll("
            SELECT o.order_id, o.scheduled_for, o.scheduled_slot, o.order_status, u.full_name,
                   TIMESTAMPDIFF(MINUTE, NOW(), o.scheduled_for) AS minutes_until
            FROM `orders` o
            JOIN `users` u ON o.user_id = u.user_id
            WHERE o.is_scheduled = 1 AND o.order_status NOT IN ('DELIVERED', 'CANCELLED')
            ORDER BY o.scheduled_for ASC
            LIMIT 4
        ");

        $triageSeverityCounts = ['CRITICAL' => 0, 'WARNING' => 0, 'INFO' => 0];
        foreach ($triageExceptions as $exception) {
            $sev = (string)($exception['severity'] ?? 'INFO');
            $triageSeverityCounts[$sev] = ($triageSeverityCounts[$sev] ?? 0) + 1;
        }

        $hourlyOrderCounts = dbFetchAll(
            "SELECT HOUR(`placed_at`) AS hour_label, COUNT(*) AS order_count FROM `orders` WHERE DATE(`placed_at`) = CURDATE() AND `order_status` != 'CANCELLED' GROUP BY HOUR(`placed_at`) ORDER BY HOUR(`placed_at`) ASC"
        );
        $hourLabels = [];
        $hourValues = [];
        $hours = [];
        for ($i = 0; $i <= 23; $i++) {
            $hours[$i] = 0;
            $hourLabels[] = sprintf('%02d:00', $i);
        }
        foreach ($hourlyOrderCounts as $item) {
            $hour = (int)($item['hour_label'] ?? 0);
            $hours[$hour] = (int)($item['order_count'] ?? 0);
        }
        foreach ($hours as $count) {
            $hourValues[] = $count;
        }

        $revenueToday = (float)(dbFetchVal(
            "SELECT COALESCE(SUM(`total_amount`), 0) FROM `orders` WHERE DATE(`placed_at`) = CURDATE() AND `order_status` != 'CANCELLED'"
        ) ?: 0.0);

        $stockRiskTrend = (int)(dbFetchVal(
            "SELECT COUNT(*) FROM `menu_items` WHERE `stock_quantity` <= 10 AND `is_available` = 1 AND `is_active` = 1"
        ) ?: 0);

        $slaPerformance = (int)(dbFetchVal(
            "SELECT COUNT(*) FROM `orders` WHERE `order_status` IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY') AND TIMESTAMPDIFF(MINUTE, `placed_at`, NOW()) <= 25"
        ) ?: 0);

        return [
            'triageExceptions' => $triageExceptions,
            'lowStockCount' => $lowStockCount,
            'liveOrdersCount' => $liveOrdersCount,
            'ordersAtRisk' => $ordersAtRisk,
            'codPending' => $codPending,
            'deliveryDelays' => $deliveryDelays,
            'scheduledOrdersCount' => $scheduledOrdersCount,
            'upcomingScheduledList' => $upcomingScheduledList,
            'triageSeverityCounts' => $triageSeverityCounts,
            'hourLabels' => $hourLabels,
            'hourValues' => $hourValues,
            'revenueToday' => $revenueToday,
            'stockRiskTrend' => $stockRiskTrend,
            'slaPerformance' => $slaPerformance,
        ];
    }

    /**
     * Build shared parameterized filter SQL and bindings for Order Audit Trail queries.
     *
     * @param array<string, mixed> $query
     * @return array{whereClause: string, params: array<string, mixed>, searchOrderId: int, searchQuery: string, filterStatus: string, filterRole: string, datePreset: string, dateFrom: string, dateTo: string, pageSize: int}
     */
    private static function buildAuditFilter(array $query): array
    {
        $searchQuery = cleanInput((string)($query['search'] ?? ''));
        $searchOrderId = (int)($query['order_id'] ?? 0);
        if ($searchOrderId <= 0 && is_numeric($searchQuery)) {
            $searchOrderId = (int)$searchQuery;
        }

        $filterStatus = cleanInput((string)($query['status'] ?? 'ALL'));
        $filterRole = cleanInput((string)($query['role'] ?? 'ALL'));
        $datePreset = cleanInput((string)($query['date_preset'] ?? 'ALL'));
        $dateFrom = cleanInput((string)($query['date_from'] ?? ''));
        $dateTo = cleanInput((string)($query['date_to'] ?? ''));
        $pageSize = max(10, min(100, (int)($query['limit'] ?? 25)));

        $conditions = [];
        $params = [];

        if ($searchOrderId > 0) {
            $conditions[] = 'a.order_id = :oid';
            $params[':oid'] = $searchOrderId;
        } elseif ($searchQuery !== '') {
            $conditions[] = '(u.full_name LIKE :sq_name OR r.role_name LIKE :sq_role OR a.old_status LIKE :sq_old OR a.new_status LIKE :sq_new)';
            $sqLike = '%' . $searchQuery . '%';
            $params[':sq_name'] = $sqLike;
            $params[':sq_role'] = $sqLike;
            $params[':sq_old'] = $sqLike;
            $params[':sq_new'] = $sqLike;
        }

        if ($filterStatus !== '' && $filterStatus !== 'ALL') {
            $conditions[] = 'a.new_status = :st';
            $params[':st'] = $filterStatus;
        }

        if ($filterRole !== '' && $filterRole !== 'ALL') {
            if ($filterRole === 'SYSTEM') {
                $conditions[] = '(a.updated_by_user_id IS NULL OR r.role_name IS NULL)';
            } else {
                $conditions[] = 'r.role_name = :role';
                $params[':role'] = $filterRole;
            }
        }

        if ($datePreset === 'TODAY') {
            $conditions[] = 'DATE(a.change_timestamp) = CURDATE()';
        } elseif ($datePreset === 'YESTERDAY') {
            $conditions[] = 'DATE(a.change_timestamp) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)';
        } elseif ($datePreset === '7D') {
            $conditions[] = 'a.change_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        } elseif ($datePreset === '30D') {
            $conditions[] = 'a.change_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        } elseif ($dateFrom !== '' || $dateTo !== '') {
            if ($dateFrom !== '') {
                $conditions[] = 'a.change_timestamp >= :dfrom';
                $params[':dfrom'] = $dateFrom . ' 00:00:00';
            }
            if ($dateTo !== '') {
                $conditions[] = 'a.change_timestamp <= :dto';
                $params[':dto'] = $dateTo . ' 23:59:59';
            }
        }

        return [
            'whereClause' => !empty($conditions) ? ('WHERE ' . implode(' AND ', $conditions)) : '',
            'params' => $params,
            'searchOrderId' => $searchOrderId,
            'searchQuery' => $searchQuery,
            'filterStatus' => $filterStatus,
            'filterRole' => $filterRole,
            'datePreset' => $datePreset,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'pageSize' => $pageSize,
        ];
    }

    /**
     * Fetch filtered order audit trail rows for CSV export (`admin/audit-logs.php`).
     *
     * @param array<string, mixed> $query
     * @return array<int, array<string, mixed>>
     */
    public static function getAuditLogsForExport(array $query): array
    {
        $f = self::buildAuditFilter($query);
        $whereClause = $f['whereClause'];
        return dbFetchAll("
            SELECT a.audit_id,
                   a.order_id,
                   o.order_number,
                   COALESCE(a.old_status, 'PLACED') AS old_status,
                   a.new_status,
                   COALESCE(r.role_name, 'SYSTEM') AS actor_role,
                   COALESCE(u.full_name, 'System Trigger') AS actor_name,
                   o.total_amount,
                   a.change_timestamp
            FROM `order_audit_trail` a
            LEFT JOIN `users` u ON a.updated_by_user_id = u.user_id
            LEFT JOIN `roles` r ON u.role_id = r.role_id
            LEFT JOIN `orders` o ON a.order_id = o.order_id
            {$whereClause}
            ORDER BY a.change_timestamp DESC, a.audit_id DESC
        ", $f['params']);
    }

    /**
     * Fetch paginated audit logs, compliance KPIs, and single-order lifecycle timeline (`admin/audit-logs.php`).
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public static function getAuditLogsData(array $query): array
    {
        $f = self::buildAuditFilter($query);
        $whereClause = $f['whereClause'];

        $totalLogs = (int)dbFetchVal("
            SELECT COUNT(*)
            FROM `order_audit_trail` a
            LEFT JOIN `users` u ON a.updated_by_user_id = u.user_id
            LEFT JOIN `roles` r ON u.role_id = r.role_id
            LEFT JOIN `orders` o ON a.order_id = o.order_id
            {$whereClause}
        ", $f['params']);

        $pageSize = $f['pageSize'];
        $totalPages = max(1, (int)ceil($totalLogs / $pageSize));
        $currentPage = min(max(1, (int)($query['page'] ?? 1)), $totalPages);
        $offset = ($currentPage - 1) * $pageSize;

        $auditLogs = [];
        try {
            $auditParams = array_merge($f['params'], [':limit' => $pageSize, ':offset' => $offset]);
            $auditLogs = dbFetchAll("
                SELECT a.*,
                       u.full_name AS actor_name,
                       r.role_name AS actor_role,
                       o.total_amount,
                       o.placed_at,
                       o.order_number,
                       o.order_status
                FROM `order_audit_trail` a
                LEFT JOIN `users` u ON a.updated_by_user_id = u.user_id
                LEFT JOIN `roles` r ON u.role_id = r.role_id
                LEFT JOIN `orders` o ON a.order_id = o.order_id
                {$whereClause}
                ORDER BY a.change_timestamp DESC, a.audit_id DESC
                LIMIT :limit OFFSET :offset
            ", $auditParams);
        } catch (Throwable $e) {
            $auditLogs = [];
        }

        $totalAuditsCount = (int)dbFetchVal("SELECT COUNT(*) FROM `order_audit_trail`") ?: 0;
        $uniqueOrdersAudited = (int)dbFetchVal("SELECT COUNT(DISTINCT `order_id`) FROM `order_audit_trail`") ?: 0;
        $transitionsToday = (int)dbFetchVal("SELECT COUNT(*) FROM `order_audit_trail` WHERE DATE(`change_timestamp`) = CURDATE()") ?: 0;
        $activeActorsCount = (int)dbFetchVal("SELECT COUNT(DISTINCT `updated_by_user_id`) FROM `order_audit_trail` WHERE `updated_by_user_id` IS NOT NULL") ?: 0;

        $singleOrderLifecycle = null;
        $singleOrderDetails = null;
        if ($f['searchOrderId'] > 0) {
            $singleOrderDetails = dbFetchOne("
                SELECT o.*, u.full_name AS customer_name, p.payment_mode, p.payment_status
                FROM `orders` o
                LEFT JOIN `users` u ON o.user_id = u.user_id
                LEFT JOIN `payments` p ON o.order_id = p.order_id
                WHERE o.order_id = :oid
            ", [':oid' => $f['searchOrderId']]);

            if ($singleOrderDetails) {
                $singleOrderLifecycle = dbFetchAll("
                    SELECT a.*, u.full_name AS actor_name, r.role_name AS actor_role
                    FROM `order_audit_trail` a
                    LEFT JOIN `users` u ON a.updated_by_user_id = u.user_id
                    LEFT JOIN `roles` r ON u.role_id = r.role_id
                    WHERE a.order_id = :oid
                    ORDER BY a.change_timestamp ASC, a.audit_id ASC
                ", [':oid' => $f['searchOrderId']]);
            }
        }

        $currentQueryParams = $query;
        unset($currentQueryParams['page'], $currentQueryParams['export']);

        return [
            'searchQuery' => $f['searchQuery'],
            'searchOrderId' => $f['searchOrderId'],
            'filterStatus' => $f['filterStatus'],
            'filterRole' => $f['filterRole'],
            'datePreset' => $f['datePreset'],
            'dateFrom' => $f['dateFrom'],
            'dateTo' => $f['dateTo'],
            'pageSize' => $pageSize,
            'totalLogs' => $totalLogs,
            'totalPages' => $totalPages,
            'currentPage' => $currentPage,
            'offset' => $offset,
            'firstResult' => $totalLogs > 0 ? $offset + 1 : 0,
            'lastResult' => min($offset + $pageSize, $totalLogs),
            'auditLogs' => $auditLogs,
            'totalAuditsCount' => $totalAuditsCount,
            'uniqueOrdersAudited' => $uniqueOrdersAudited,
            'transitionsToday' => $transitionsToday,
            'activeActorsCount' => $activeActorsCount,
            'singleOrderDetails' => $singleOrderDetails,
            'singleOrderLifecycle' => $singleOrderLifecycle,
            'currentQueryParams' => $currentQueryParams,
        ];
    }

    /**
     * Handle Admin FAQ knowledge base CRUD and soft-delete archival actions (`admin/faqs.php`).
     *
     * @param array<string, mixed> $post
     * @return array{type: string, message: string}
     */
    public static function handleFaqAction(array $post): array
    {
        $action = (string)($post['action'] ?? '');

        if ($action === 'add_faq') {
            $question = cleanInput((string)($post['question'] ?? ''));
            $answer = sanitizeFaqContent((string)($post['answer'] ?? ''));
            $category = cleanInput((string)($post['category'] ?? 'General'));
            $displayOrder = (int)($post['display_order'] ?? 10);
            $isActive = !empty($post['is_active']) ? 1 : 0;

            if ($question !== '' && $answer !== '') {
                dbQuery("
                    INSERT INTO `faqs` (`question`, `answer`, `category`, `display_order`, `is_active`)
                    VALUES (:q, :a, :c, :o, :act)
                ", [
                    ':q' => $question,
                    ':a' => $answer,
                    ':c' => $category,
                    ':o' => $displayOrder,
                    ':act' => $isActive,
                ]);
                return ['type' => 'success', 'message' => 'New FAQ topic published to knowledge base.'];
            }
            return ['type' => 'danger', 'message' => 'Please provide both question and answer.'];
        }

        if ($action === 'edit_faq') {
            $faqId = (int)($post['faq_id'] ?? 0);
            $question = cleanInput((string)($post['question'] ?? ''));
            $answer = sanitizeFaqContent((string)($post['answer'] ?? ''));
            $category = cleanInput((string)($post['category'] ?? 'General'));
            $displayOrder = (int)($post['display_order'] ?? 0);
            $isActive = !empty($post['is_active']) ? 1 : 0;

            if ($faqId > 0 && $question !== '' && $answer !== '') {
                dbQuery("
                    UPDATE `faqs`
                    SET `question` = :q, `answer` = :a, `category` = :c, `display_order` = :o, `is_active` = :act
                    WHERE `faq_id` = :id
                ", [
                    ':q' => $question,
                    ':a' => $answer,
                    ':c' => $category,
                    ':o' => $displayOrder,
                    ':act' => $isActive,
                    ':id' => $faqId,
                ]);
                return ['type' => 'success', 'message' => 'FAQ topic updated successfully.'];
            }
            return ['type' => 'danger', 'message' => 'Valid question and answer are required.'];
        }

        if ($action === 'toggle_status') {
            $faqId = (int)($post['faq_id'] ?? 0);
            $newStatus = (int)($post['is_active'] ?? 0);
            if ($faqId > 0) {
                dbQuery("UPDATE `faqs` SET `is_active` = :act WHERE `faq_id` = :id", [
                    ':act' => $newStatus,
                    ':id' => $faqId,
                ]);
                return ['type' => 'success', 'message' => $newStatus ? 'FAQ topic is now live on storefront.' : 'FAQ topic moved to drafts.'];
            }
            return ['type' => 'danger', 'message' => 'Invalid FAQ ID.'];
        }

        if ($action === 'delete_faq') {
            $faqId = (int)($post['faq_id'] ?? 0);
            if ($faqId > 0) {
                dbQuery("UPDATE `faqs` SET `is_active` = 0 WHERE `faq_id` = :id", [':id' => $faqId]);
                return ['type' => 'success', 'message' => 'FAQ topic archived safely (removed from customer help desk).'];
            }
            return ['type' => 'danger', 'message' => 'Invalid FAQ ID.'];
        }

        if ($action === 'reorder_faq') {
            $faqId = (int)($post['faq_id'] ?? 0);
            $direction = cleanInput((string)($post['direction'] ?? 'up'));
            if ($faqId > 0) {
                if ($direction === 'up') {
                    dbQuery("UPDATE `faqs` SET `display_order` = GREATEST(0, `display_order` - 1) WHERE `faq_id` = :id", [':id' => $faqId]);
                } else {
                    dbQuery("UPDATE `faqs` SET `display_order` = `display_order` + 1 WHERE `faq_id` = :id", [':id' => $faqId]);
                }
                return ['type' => 'success', 'message' => 'Display order adjusted.'];
            }
            return ['type' => 'danger', 'message' => 'Invalid FAQ ID.'];
        }

        if ($action === 'duplicate_faq') {
            $faqId = (int)($post['faq_id'] ?? 0);
            if ($faqId > 0) {
                $existing = dbFetchOne("SELECT * FROM `faqs` WHERE `faq_id` = :id", [':id' => $faqId]);
                if ($existing) {
                    dbQuery("
                        INSERT INTO `faqs` (`question`, `answer`, `category`, `display_order`, `is_active`)
                        VALUES (:q, :a, :c, :o, 0)
                    ", [
                        ':q' => 'Copy of ' . $existing['question'],
                        ':a' => $existing['answer'],
                        ':c' => $existing['category'],
                        ':o' => (int)$existing['display_order'] + 1,
                    ]);
                    return ['type' => 'success', 'message' => 'FAQ duplicated as draft. You can now tweak and publish it.'];
                }
            }
            return ['type' => 'danger', 'message' => 'FAQ topic not found.'];
        }

        return ['type' => 'danger', 'message' => 'Unknown FAQ action.'];
    }

    /**
     * Fetch Admin FAQ overview dataset, categories, counts, and filtered list (`admin/faqs.php`).
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public static function getAdminFaqsData(array $query): array
    {
        $totalFaqs = (int)(dbFetchVal("SELECT COUNT(*) FROM `faqs`") ?: 0);
        $publishedFaqs = (int)(dbFetchVal("SELECT COUNT(*) FROM `faqs` WHERE `is_active` = 1") ?: 0);
        $draftFaqs = (int)(dbFetchVal("SELECT COUNT(*) FROM `faqs` WHERE `is_active` = 0") ?: 0);
        $totalCategories = (int)(dbFetchVal("SELECT COUNT(DISTINCT `category`) FROM `faqs` WHERE `category` IS NOT NULL AND `category` != ''") ?: 0);
        $maxOrder = (int)(dbFetchVal("SELECT MAX(`display_order`) FROM `faqs`") ?: 0);

        $defaultCategories = ['General', 'Orders & Cancellation', 'Delivery & Tracking', 'Billing & Payments', 'Account & Privacy'];
        $dbCats = dbFetchAll("SELECT DISTINCT `category` FROM `faqs` WHERE `category` IS NOT NULL AND `category` != ''");
        $existingCats = array_filter(array_column($dbCats, 'category'));
        $categories = array_values(array_unique(array_merge($defaultCategories, $existingCats)));
        sort($categories);

        $categoryCounts = [];
        $countsRaw = dbFetchAll("SELECT `category`, COUNT(*) AS cnt FROM `faqs` GROUP BY `category`");
        foreach ($countsRaw as $cRow) {
            $categoryCounts[$cRow['category']] = (int)$cRow['cnt'];
        }

        $categoryFilter = cleanInput((string)($query['category'] ?? ''));
        $statusFilter = cleanInput((string)($query['status'] ?? ''));
        $searchQuery = cleanInput((string)($query['q'] ?? ''));

        $conditions = [];
        $params = [];

        if ($categoryFilter !== '') {
            $conditions[] = '`category` = :cat';
            $params[':cat'] = $categoryFilter;
        }
        if ($statusFilter === 'published') {
            $conditions[] = '`is_active` = 1';
        } elseif ($statusFilter === 'draft') {
            $conditions[] = '`is_active` = 0';
        }
        if ($searchQuery !== '') {
            $conditions[] = '(`question` LIKE :q_question OR `answer` LIKE :q_answer OR `category` LIKE :q_cat)';
            $qLike = "%{$searchQuery}%";
            $params[':q_question'] = $qLike;
            $params[':q_answer'] = $qLike;
            $params[':q_cat'] = $qLike;
        }

        $whereClause = !empty($conditions) ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        try {
            $faqs = dbFetchAll("
                SELECT * FROM `faqs`
                {$whereClause}
                ORDER BY `display_order` ASC, `faq_id` ASC
            ", $params);
        } catch (Throwable $e) {
            $faqs = [];
        }

        return [
            'totalFaqs' => $totalFaqs,
            'publishedFaqs' => $publishedFaqs,
            'draftFaqs' => $draftFaqs,
            'totalCategories' => $totalCategories,
            'maxOrder' => $maxOrder,
            'categories' => $categories,
            'categoryCounts' => $categoryCounts,
            'categoryFilter' => $categoryFilter,
            'statusFilter' => $statusFilter,
            'searchQuery' => $searchQuery,
            'faqs' => $faqs,
        ];
    }

    /**
     * Fetch dish reviews for Admin CSV export (`admin/reviews.php`).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getDishReviewsForExport(): array
    {
        return dbFetchAll("
            SELECT dr.*, m.item_name, m.price, m.is_veg, c.category_name,
                   COALESCE(u.full_name, 'Guest Customer') AS customer_name,
                   COALESCE(u.email, 'N/A') AS customer_email
            FROM `dish_reviews` dr
            JOIN `menu_items` m ON dr.item_id = m.item_id
            LEFT JOIN `categories` c ON m.category_id = c.category_id
            LEFT JOIN `users` u ON dr.user_id = u.user_id
            ORDER BY dr.created_at DESC
        ");
    }

    /**
     * Fetch order reviews for Admin CSV export (`admin/reviews.php`).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getOrderReviewsForExport(): array
    {
        return dbFetchAll("
            SELECT r.*,
                   COALESCE(u.full_name, 'Guest Customer') AS customer_name,
                   COALESCE(u.email, 'N/A') AS customer_email,
                   o.total_amount,
                   COALESCE(d.delivery_partner_name, 'Unassigned Rider') AS delivery_partner_name,
                   (
                       SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', mi.item_name) SEPARATOR ', ')
                       FROM `order_items` oi
                       JOIN `menu_items` mi ON oi.item_id = mi.item_id
                       WHERE oi.order_id = r.order_id
                   ) AS items_summary
            FROM `order_reviews` r
            LEFT JOIN `users` u ON r.user_id = u.user_id
            LEFT JOIN `orders` o ON r.order_id = o.order_id
            LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
            ORDER BY r.created_at DESC
        ");
    }

    /**
     * Fetch order & dish review KPIs, histograms, and filtered rows (`admin/reviews.php`).
     *
     * @return array<string, mixed>
     */
    public static function getAdminReviewsData(
        string $viewTab,
        int $starFilter,
        string $sentimentFilter,
        string $searchQuery
    ): array
    {
        $totalOrderReviews = (int)dbFetchVal("SELECT COUNT(*) FROM `order_reviews`") ?: 0;
        $avgFoodRating = $totalOrderReviews > 0 ? (float)dbFetchVal("SELECT AVG(`food_rating`) FROM `order_reviews`") : 0.0;
        $avgRiderRating = $totalOrderReviews > 0 ? (float)dbFetchVal("SELECT AVG(`rider_rating`) FROM `order_reviews`") : 0.0;
        $fiveStarFoodCount = (int)dbFetchVal("SELECT COUNT(*) FROM `order_reviews` WHERE `food_rating` = 5") ?: 0;
        $criticalReviewsCount = (int)dbFetchVal("SELECT COUNT(*) FROM `order_reviews` WHERE `food_rating` <= 2 OR `rider_rating` <= 2") ?: 0;
        $totalTips = (float)dbFetchVal("SELECT COALESCE(SUM(`rider_tip`), 0.00) FROM `order_reviews`") ?: 0.00;

        $totalDishReviews = (int)dbFetchVal("SELECT COUNT(*) FROM `dish_reviews`") ?: 0;
        $avgDishRating = $totalDishReviews > 0 ? (float)dbFetchVal("SELECT AVG(`rating`) FROM `dish_reviews`") : 0.0;
        $criticalDishReviewsCount = (int)dbFetchVal("SELECT COUNT(*) FROM `dish_reviews` WHERE `rating` <= 2") ?: 0;
        $avgAppearance = $totalDishReviews > 0 ? (float)dbFetchVal("SELECT AVG(`appearance_rating`) FROM `dish_reviews`") : 0.0;
        $avgTaste = $totalDishReviews > 0 ? (float)dbFetchVal("SELECT AVG(`taste_rating`) FROM `dish_reviews`") : 0.0;
        $avgPortion = $totalDishReviews > 0 ? (float)dbFetchVal("SELECT AVG(`portion_rating`) FROM `dish_reviews`") : 0.0;
        $avgValue = $totalDishReviews > 0 ? (float)dbFetchVal("SELECT AVG(`value_rating`) FROM `dish_reviews`") : 0.0;

        $orderStarDist = [];
        $dishStarDist = [];
        for ($s = 5; $s >= 1; $s--) {
            $orderStarDist[$s] = (int)dbFetchVal("SELECT COUNT(*) FROM `order_reviews` WHERE `food_rating` = :s", [':s' => $s]) ?: 0;
            $dishStarDist[$s] = (int)dbFetchVal("SELECT COUNT(*) FROM `dish_reviews` WHERE `rating` = :s", [':s' => $s]) ?: 0;
        }

        $orderConditions = [];
        $orderParams = [];
        if ($starFilter >= 1 && $starFilter <= 5) {
            $orderConditions[] = 'r.food_rating = :sf';
            $orderParams[':sf'] = $starFilter;
        }
        if ($sentimentFilter === 'critical') {
            $orderConditions[] = '(r.food_rating <= 2 OR r.rider_rating <= 2)';
        } elseif ($sentimentFilter === 'positive') {
            $orderConditions[] = 'r.food_rating >= 4';
        } elseif ($sentimentFilter === 'with_comments') {
            $orderConditions[] = "r.comments IS NOT NULL AND TRIM(r.comments) != ''";
        }
        if ($searchQuery !== '') {
            $orderConditions[] = '(
                u.full_name LIKE :sq_name
                OR u.email LIKE :sq_email
                OR r.comments LIKE :sq_comments
                OR d.delivery_partner_name LIKE :sq_rider
                OR CAST(r.order_id AS CHAR) LIKE :sq_order
            )';
            $orderLike = "%{$searchQuery}%";
            $orderParams[':sq_name'] = $orderLike;
            $orderParams[':sq_email'] = $orderLike;
            $orderParams[':sq_comments'] = $orderLike;
            $orderParams[':sq_rider'] = $orderLike;
            $orderParams[':sq_order'] = $orderLike;
        }

        $orderWhereClause = !empty($orderConditions) ? ('WHERE ' . implode(' AND ', $orderConditions)) : '';
        $orderReviewsList = dbFetchAll("
            SELECT r.*,
                   COALESCE(u.full_name, 'Guest Customer') AS customer_name,
                   COALESCE(u.email, 'N/A') AS customer_email,
                   COALESCE(u.phone_number, '') AS customer_phone,
                   o.total_amount, o.order_status, o.placed_at,
                   COALESCE(d.delivery_partner_name, 'Unassigned Rider') AS delivery_partner_name,
                   d.delivery_partner_phone,
                   (
                       SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', mi.item_name) SEPARATOR ', ')
                       FROM `order_items` oi
                       JOIN `menu_items` mi ON oi.item_id = mi.item_id
                       WHERE oi.order_id = r.order_id
                   ) AS items_summary
            FROM `order_reviews` r
            LEFT JOIN `users` u ON r.user_id = u.user_id
            LEFT JOIN `orders` o ON r.order_id = o.order_id
            LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
            {$orderWhereClause}
            ORDER BY r.created_at DESC
        ", $orderParams);

        $dishConditions = [];
        $dishParams = [];
        if ($starFilter >= 1 && $starFilter <= 5) {
            $dishConditions[] = 'dr.rating = :sf';
            $dishParams[':sf'] = $starFilter;
        }
        if ($sentimentFilter === 'critical') {
            $dishConditions[] = 'dr.rating <= 2';
        } elseif ($sentimentFilter === 'positive') {
            $dishConditions[] = 'dr.rating >= 4';
        } elseif ($sentimentFilter === 'with_comments') {
            $dishConditions[] = "dr.review_text IS NOT NULL AND TRIM(dr.review_text) != ''";
        }
        if ($searchQuery !== '') {
            $dishConditions[] = '(
                m.item_name LIKE :sq_item
                OR u.full_name LIKE :sq_name
                OR u.email LIKE :sq_email
                OR dr.review_text LIKE :sq_text
                OR dr.tags LIKE :sq_tags
                OR CAST(dr.order_id AS CHAR) LIKE :sq_order
            )';
            $dishLike = "%{$searchQuery}%";
            $dishParams[':sq_item'] = $dishLike;
            $dishParams[':sq_name'] = $dishLike;
            $dishParams[':sq_email'] = $dishLike;
            $dishParams[':sq_text'] = $dishLike;
            $dishParams[':sq_tags'] = $dishLike;
            $dishParams[':sq_order'] = $dishLike;
        }

        $dishWhereClause = !empty($dishConditions) ? ('WHERE ' . implode(' AND ', $dishConditions)) : '';
        $dishReviewsList = dbFetchAll("
            SELECT dr.*,
                   m.item_name, m.image_url, m.price, m.is_veg,
                   c.category_name,
                   COALESCE(u.full_name, 'Guest Customer') AS customer_name,
                   COALESCE(u.email, 'N/A') AS customer_email,
                   COALESCE(u.phone_number, '') AS customer_phone
            FROM `dish_reviews` dr
            JOIN `menu_items` m ON dr.item_id = m.item_id
            LEFT JOIN `categories` c ON m.category_id = c.category_id
            LEFT JOIN `users` u ON dr.user_id = u.user_id
            {$dishWhereClause}
            ORDER BY dr.created_at DESC
        ", $dishParams);

        return [
            'totalOrderReviews' => $totalOrderReviews,
            'avgFoodRating' => $avgFoodRating,
            'avgRiderRating' => $avgRiderRating,
            'fiveStarFoodCount' => $fiveStarFoodCount,
            'criticalReviewsCount' => $criticalReviewsCount,
            'totalTips' => $totalTips,
            'totalDishReviews' => $totalDishReviews,
            'avgDishRating' => $avgDishRating,
            'criticalDishReviewsCount' => $criticalDishReviewsCount,
            'avgAppearance' => $avgAppearance,
            'avgTaste' => $avgTaste,
            'avgPortion' => $avgPortion,
            'avgValue' => $avgValue,
            'orderStarDist' => $orderStarDist,
            'dishStarDist' => $dishStarDist,
            'activeDist' => ($viewTab === 'dishes') ? $dishStarDist : $orderStarDist,
            'activeTotal' => ($viewTab === 'dishes') ? $totalDishReviews : $totalOrderReviews,
            'orderReviewsList' => $orderReviewsList,
            'dishReviewsList' => $dishReviewsList,
            'hasActiveFilters' => ($starFilter > 0 || $sentimentFilter !== 'all' || $searchQuery !== ''),
        ];
    }

    /**
     * Build parameterized WHERE clause and named bindings for user directory & CSV filters.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public static function buildUserFilter(string $roleFilter, string $statusFilter, string $searchQuery): array
    {
        $conditions = [];
        $params = [];

        if ($roleFilter !== '') {
            $conditions[] = 'r.role_name = :rname';
            $params[':rname'] = strtoupper($roleFilter);
        }
        if ($statusFilter === 'active') {
            $conditions[] = 'u.is_active = 1';
        } elseif ($statusFilter === 'suspended') {
            $conditions[] = 'u.is_active = 0';
        }
        if ($searchQuery !== '') {
            $cleanQ = preg_replace('/^#?USR-?/i', '', $searchQuery);
            $searchLike = "%{$searchQuery}%";
            if (is_numeric($cleanQ)) {
                $conditions[] = '(u.user_id = :exact_id OR u.full_name LIKE :q_name OR u.email LIKE :q_email OR u.phone_number LIKE :q_phone)';
                $params[':exact_id'] = (int)$cleanQ;
            } else {
                $conditions[] = '(u.full_name LIKE :q_name OR u.email LIKE :q_email OR u.phone_number LIKE :q_phone)';
            }
            $params[':q_name'] = $searchLike;
            $params[':q_email'] = $searchLike;
            $params[':q_phone'] = $searchLike;
        }

        $whereClause = !empty($conditions) ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        return [$whereClause, $params];
    }

    /**
     * Fetch filtered user list for CSV export (`admin/users.php`).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getUsersForExport(string $roleFilter, string $statusFilter, string $searchQuery): array
    {
        [$whereClause, $params] = self::buildUserFilter($roleFilter, $statusFilter, $searchQuery);

        return dbFetchAll("
            SELECT u.*, r.role_name
            FROM `users` u
            JOIN `roles` r ON u.role_id = r.role_id
            {$whereClause}
            ORDER BY u.role_id ASC, u.user_id DESC
        ", $params);
    }

    /**
     * Fetch user profile + role-specific activity metrics for AJAX drawer (`admin/users.php`).
     *
     * @return array<string, mixed>|null
     */
    public static function getUserDetailStats(int $uid): ?array
    {
        $user = dbFetchOne("
            SELECT u.user_id, u.role_id, u.full_name, u.email, u.phone_number, u.is_active, u.created_at,
                   r.role_name, r.description AS role_desc
            FROM `users` u
            JOIN `roles` r ON u.role_id = r.role_id
            WHERE u.user_id = :id
        ", [':id' => $uid]);

        if (!$user) {
            return null;
        }

        $data = [
            'user' => [
                'id' => (int)$user['user_id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'phone_number' => $user['phone_number'],
                'role_id' => (int)$user['role_id'],
                'role_name' => $user['role_name'],
                'role_desc' => $user['role_desc'],
                'is_active' => (int)$user['is_active'],
                'created_at' => date('d M Y, h:i A', (int)strtotime((string)$user['created_at'])),
                'role_badge' => getRoleBadge((string)$user['role_name']),
            ],
            'stats' => ['type' => 'staff'],
        ];

        if ($user['role_name'] === 'CUSTOMER') {
            $totalOrders = (int)dbFetchVal("SELECT COUNT(*) FROM `orders` WHERE `user_id` = :uid", [':uid' => $uid]) ?: 0;
            $totalSpend = (float)dbFetchVal("SELECT COALESCE(SUM(`total_amount`), 0) FROM `orders` WHERE `user_id` = :uid AND `order_status` = 'DELIVERED'", [':uid' => $uid]) ?: 0.0;
            $addressesCount = (int)dbFetchVal("SELECT COUNT(*) FROM `user_addresses` WHERE `user_id` = :uid AND `is_active` = 1", [':uid' => $uid]) ?: 0;
            $recentOrders = dbFetchAll("
                SELECT `order_id`, `order_number`, `total_amount`, `order_status`, `placed_at`
                FROM `orders`
                WHERE `user_id` = :uid
                ORDER BY `placed_at` DESC
                LIMIT 3
            ", [':uid' => $uid]);

            $data['stats'] = [
                'type' => 'customer',
                'total_orders' => $totalOrders,
                'total_spend' => formatInr($totalSpend),
                'addresses_count' => $addressesCount,
                'recent_orders' => $recentOrders,
            ];
        } elseif ($user['role_name'] === 'RIDER') {
            $completed = (int)dbFetchVal("SELECT COUNT(*) FROM `delivery_dispatches` WHERE `rider_id` = :uid AND `delivery_status` = 'DELIVERED'", [':uid' => $uid]) ?: 0;
            $active = (int)dbFetchVal("SELECT COUNT(*) FROM `delivery_dispatches` WHERE `rider_id` = :uid AND `delivery_status` IN ('ASSIGNED', 'PICKED_UP', 'IN_TRANSIT')", [':uid' => $uid]) ?: 0;
            $data['stats'] = [
                'type' => 'rider',
                'completed_deliveries' => $completed,
                'active_dispatches' => $active,
            ];
        }

        return $data;
    }

    /**
     * Handle Admin User & Staff RBAC mutations (`add_staff`, `edit_user`, `reset_password`, `toggle_active`, `update_role`).
     *
     * @param array<string, mixed> $post
     * @return array{type: string, message: string}
     */
    public static function handleUserRbacAction(array $post, int $actorUserId): array
    {
        $action = (string)($post['action'] ?? '');

        if ($action === 'add_staff') {
            $name = cleanInput((string)($post['full_name'] ?? ''));
            $email = cleanInput(strtolower((string)($post['email'] ?? '')));
            $phone = cleanInput((string)($post['phone_number'] ?? ''));
            $roleId = (int)($post['role_id'] ?? 0);
            $pass = (string)($post['password'] ?? '');

            if ($name === '' || $email === '' || $phone === '' || $pass === '') {
                return ['type' => 'danger', 'message' => 'Please provide name, official email, phone number, and temporary password.'];
            }
            if (strlen($pass) < 6) {
                return ['type' => 'danger', 'message' => 'Password must be at least 6 characters long.'];
            }

            $roleInfo = dbFetchOne("SELECT * FROM `roles` WHERE `role_id` = :rid", [':rid' => $roleId]);
            if (!$roleInfo || (int)$roleInfo['role_id'] === 2) {
                return ['type' => 'danger', 'message' => 'Invalid staff role specified. Customers cannot be provisioned as staff.'];
            }

            if ((int)dbFetchVal("SELECT COUNT(*) FROM `users` WHERE LOWER(`email`) = :email", [':email' => $email]) > 0) {
                return ['type' => 'danger', 'message' => "An account with email '{$email}' already exists."];
            }
            if ((int)dbFetchVal("SELECT COUNT(*) FROM `users` WHERE `phone_number` = :phone", [':phone' => $phone]) > 0) {
                return ['type' => 'danger', 'message' => "An account with phone number '{$phone}' already exists."];
            }

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            dbQuery("
                INSERT INTO `users` (`role_id`, `full_name`, `email`, `phone_number`, `password_hash`, `is_active`)
                VALUES (:rid, :name, :email, :phone, :hash, 1)
            ", [
                ':rid' => $roleId,
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':hash' => $hash,
            ]);

            return ['type' => 'success', 'message' => "New staff member '{$name}' created with role privileges: {$roleInfo['role_name']}."];
        }

        if ($action === 'edit_user') {
            $uid = (int)($post['user_id'] ?? 0);
            $name = cleanInput((string)($post['full_name'] ?? ''));
            $email = cleanInput(strtolower((string)($post['email'] ?? '')));
            $phone = cleanInput((string)($post['phone_number'] ?? ''));

            if ($name === '' || $email === '' || $phone === '') {
                return ['type' => 'danger', 'message' => 'Full name, email, and phone number cannot be blank.'];
            }

            $target = dbFetchOne("SELECT * FROM `users` WHERE `user_id` = :id", [':id' => $uid]);
            if (!$target) {
                return ['type' => 'danger', 'message' => 'User account not found.'];
            }

            if ((int)dbFetchVal("SELECT COUNT(*) FROM `users` WHERE LOWER(`email`) = :email AND `user_id` != :id", [':email' => $email, ':id' => $uid]) > 0) {
                return ['type' => 'danger', 'message' => "Email '{$email}' is already in use by another account."];
            }
            if ((int)dbFetchVal("SELECT COUNT(*) FROM `users` WHERE `phone_number` = :phone AND `user_id` != :id", [':phone' => $phone, ':id' => $uid]) > 0) {
                return ['type' => 'danger', 'message' => "Phone number '{$phone}' is already in use by another account."];
            }

            dbQuery("UPDATE `users` SET `full_name` = :name, `email` = :email, `phone_number` = :phone WHERE `user_id` = :id", [
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':id' => $uid,
            ]);

            return ['type' => 'success', 'message' => "Profile details for '{$name}' (#USR-{$uid}) updated successfully."];
        }

        if ($action === 'reset_password') {
            $uid = (int)($post['user_id'] ?? 0);
            $newPass = (string)($post['new_password'] ?? '');
            if (strlen($newPass) < 6) {
                return ['type' => 'danger', 'message' => 'Temporary password must be at least 6 characters long.'];
            }

            $target = dbFetchOne("SELECT * FROM `users` WHERE `user_id` = :id", [':id' => $uid]);
            if (!$target) {
                return ['type' => 'danger', 'message' => 'User account not found.'];
            }

            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            dbQuery("UPDATE `users` SET `password_hash` = :hash WHERE `user_id` = :id", [
                ':hash' => $hash,
                ':id' => $uid,
            ]);

            return ['type' => 'success', 'message' => "Password successfully reset for '{$target['full_name']}'."];
        }

        if ($action === 'toggle_active') {
            $uid = (int)($post['user_id'] ?? 0);
            $newStatus = (int)($post['is_active'] ?? 0);

            $target = dbFetchOne("SELECT * FROM `users` WHERE `user_id` = :id", [':id' => $uid]);
            if (!$target) {
                return ['type' => 'danger', 'message' => 'User account not found.'];
            }

            if ($uid === $actorUserId && $newStatus === 0) {
                return ['type' => 'danger', 'message' => 'Security Restriction: Self-deactivation by the active administrator is prohibited to prevent lockout.'];
            }

            if ((int)$target['role_id'] === 1 && $newStatus === 0) {
                $otherAdmins = (int)dbFetchVal(
                    "SELECT COUNT(*) FROM `users` WHERE `role_id` = 1 AND `is_active` = 1 AND `user_id` != :id",
                    [':id' => $uid]
                );
                if ($otherAdmins <= 0) {
                    return ['type' => 'danger', 'message' => 'Security Restriction: Cannot suspend the last remaining active Administrator.'];
                }
            }

            dbQuery("UPDATE `users` SET `is_active` = :act WHERE `user_id` = :id", [
                ':act' => $newStatus,
                ':id' => $uid,
            ]);
            $statusLabel = $newStatus ? 'reactivated' : 'suspended';

            return ['type' => 'success', 'message' => "User account for '{$target['full_name']}' (#USR-{$uid}) has been {$statusLabel}."];
        }

        if ($action === 'update_role') {
            $uid = (int)($post['user_id'] ?? 0);
            $newRoleId = (int)($post['role_id'] ?? 0);
            $result = updateUserRole($uid, $newRoleId);

            return [
                'type' => !empty($result['success']) ? 'success' : 'danger',
                'message' => (string)$result['message'],
            ];
        }

        return ['type' => 'danger', 'message' => 'Unknown user management action.'];
    }

    /**
     * Fetch paginated Admin Users Directory, roles, and KPI counts (`admin/users.php`).
     *
     * @return array<string, mixed>
     */
    public static function getUsersDirectoryData(
        string $roleFilter,
        string $statusFilter,
        string $searchQuery,
        int $requestedPage
    ): array
    {
        [$whereClause, $params] = self::buildUserFilter($roleFilter, $statusFilter, $searchQuery);

        $perPage = 15;
        $totalFiltered = (int)dbFetchVal("
            SELECT COUNT(*)
            FROM `users` u
            JOIN `roles` r ON u.role_id = r.role_id
            {$whereClause}
        ", $params) ?: 0;

        $totalPages = max(1, (int)ceil($totalFiltered / $perPage));
        $currentPage = min($totalPages, max(1, $requestedPage));
        $offset = ($currentPage - 1) * $perPage;

        $userParams = array_merge($params, [':limit' => $perPage, ':offset' => $offset]);
        $users = dbFetchAll("
            SELECT u.*, r.role_name, r.description AS role_desc
            FROM `users` u
            JOIN `roles` r ON u.role_id = r.role_id
            {$whereClause}
            ORDER BY u.role_id ASC, u.user_id DESC
            LIMIT :limit OFFSET :offset
        ", $userParams);

        $roles = dbFetchAll("SELECT * FROM `roles` ORDER BY `role_id` ASC");
        $totalUsersCount = (int)dbFetchVal("SELECT COUNT(*) FROM `users`") ?: 0;
        $adminCount = (int)dbFetchVal("SELECT COUNT(*) FROM `users` WHERE `role_id` = 1") ?: 0;
        $kitchenCount = (int)dbFetchVal("SELECT COUNT(*) FROM `users` WHERE `role_id` = 3") ?: 0;
        $riderCount = (int)dbFetchVal("SELECT COUNT(*) FROM `users` WHERE `role_id` = 4") ?: 0;
        $customerCount = (int)dbFetchVal("SELECT COUNT(*) FROM `users` WHERE `role_id` = 2") ?: 0;
        $inactiveUsersCount = (int)dbFetchVal("SELECT COUNT(*) FROM `users` WHERE `is_active` = 0") ?: 0;

        $paginationParams = ['tab' => 'directory'];
        if ($roleFilter !== '') {
            $paginationParams['role'] = $roleFilter;
        }
        if ($statusFilter !== '') {
            $paginationParams['status'] = $statusFilter;
        }
        if ($searchQuery !== '') {
            $paginationParams['q'] = $searchQuery;
        }

        return [
            'perPage' => $perPage,
            'totalFiltered' => $totalFiltered,
            'totalPages' => $totalPages,
            'currentPage' => $currentPage,
            'offset' => $offset,
            'users' => $users,
            'roles' => $roles,
            'totalUsersCount' => $totalUsersCount,
            'adminCount' => $adminCount,
            'kitchenCount' => $kitchenCount,
            'riderCount' => $riderCount,
            'customerCount' => $customerCount,
            'inactiveUsersCount' => $inactiveUsersCount,
            'paginationParams' => $paginationParams,
        ];
    }

    /**
     * Fetch customer profile record, order stats, and active address count (`customer/profile.php`).
     *
     * @param array<string, mixed> $currentUser
     * @return array{userRecord: array<string, mixed>, orderStats: array<string, mixed>, savedAddressesCount: int, previousMeal: array<string, mixed>|null}
     */
    public static function getCustomerProfileData(int $userId, array $currentUser): array
    {
        $userRecord = dbFetchOne("SELECT * FROM `users` WHERE `user_id` = :id", [':id' => $userId]) ?: [
            'full_name' => $currentUser['name'] ?? 'User',
            'email' => $currentUser['email'] ?? '',
            'phone_number' => $currentUser['phone'] ?? '',
            'created_at' => '',
            'security_question' => '',
        ];

        $orderStats = dbFetchOne("
            SELECT COUNT(*) AS total_orders, COALESCE(SUM(`total_amount`), 0) AS total_spent
            FROM `orders`
            WHERE `user_id` = :id
        ", [':id' => $userId]) ?: ['total_orders' => 0, 'total_spent' => 0];

        $savedAddressesCount = (int)dbFetchVal(
            "SELECT COUNT(*) FROM `user_addresses` WHERE `user_id` = :id AND `is_active` = 1",
            [':id' => $userId]
        );

        return [
            'userRecord' => $userRecord,
            'orderStats' => $orderStats,
            'savedAddressesCount' => $savedAddressesCount,
            'previousMeal' => getCustomerPreviousMeal($userId),
        ];
    }

    /**
     * Update customer profile contact details and synchronize session state.
     *
     * @return array{ok: bool, flash_type: string, message: string}
     */
    public static function updateCustomerProfile(int $userId, string $fullName, string $phone): array
    {
        $fullName = cleanInput($fullName);
        $phone = cleanInput($phone);

        if ($fullName === '' || $phone === '') {
            return ['ok' => false, 'flash_type' => 'danger', 'message' => 'Please fill in all required fields.'];
        }

        dbQuery("UPDATE `users` SET `full_name` = :name, `phone_number` = :phone WHERE `user_id` = :id", [
            ':name' => $fullName,
            ':phone' => $phone,
            ':id' => $userId,
        ]);
        $_SESSION['user_name'] = $fullName;
        $_SESSION['user_phone'] = $phone;

        return ['ok' => true, 'flash_type' => 'success', 'message' => 'Profile information updated successfully.'];
    }
}
