<?php
/**
 * AJAX Endpoint: Universal Command Palette Global Search (Ctrl + K)
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_admin() && !is_kitchen()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$query = trim($_GET['q'] ?? '');
$cleanQuery = strtolower($query);
$numQuery = (int)preg_replace('/[^0-9]/', '', $cleanQuery);

$results = [];

// 1. Navigation Pages
$navigationPages = [
    ['title' => 'Admin Executive Dashboard', 'subtitle' => 'Live operations cockpit & FCFS bottleneck triage', 'url' => ADMIN_URL . '/index.php', 'icon' => 'bi-speedometer2', 'category' => 'Navigation', 'tags' => 'home cockpit kpi triage queue summary'],
    ['title' => 'Live Orders Command Center', 'subtitle' => 'Master transaction ledger & multi-select batch dispatch', 'url' => ADMIN_URL . '/orders.php', 'icon' => 'bi-receipt', 'category' => 'Navigation', 'tags' => 'orders tickets kot kitchen dispatch batch table'],
    ['title' => 'Kitchen Operations & Staff Roster', 'subtitle' => 'Culinary cook station, prep times & shifts', 'url' => ADMIN_URL . '/kitchen-view.php', 'icon' => 'bi-fire', 'category' => 'Navigation', 'tags' => 'kitchen chef cook food prep roster'],
    ['title' => 'Delivery Fleet & GPS Telemetry', 'subtitle' => 'Live multi-rider spatial map & COD handovers', 'url' => ADMIN_URL . '/delivery-view.php', 'icon' => 'bi-bicycle', 'category' => 'Navigation', 'tags' => 'riders couriers fleet map gps delivery bikes'],
    ['title' => 'Money & COD Financial Ledger', 'subtitle' => 'Cash drawer closing, daily Z-reports & reconciliation', 'url' => ADMIN_URL . '/financial-transactions.php', 'icon' => 'bi-cash-coin', 'category' => 'Navigation', 'tags' => 'money cash cod transactions zreport ledger finance revenue'],
    ['title' => 'Dishes & Stock Reserves', 'subtitle' => 'Instant 86-list stock toggle & portion reserves', 'url' => ADMIN_URL . '/dishes.php', 'icon' => 'bi-basket', 'category' => 'Navigation', 'tags' => 'menu dishes stock inventory 86 toggle price food'],
    ['title' => 'Food Menu Categories', 'subtitle' => 'Organize categories & display sequences', 'url' => ADMIN_URL . '/categories.php', 'icon' => 'bi-tags', 'category' => 'Navigation', 'tags' => 'categories menu groups food sections'],
    ['title' => 'Executive BI Reports & Analytics', 'subtitle' => 'Revenue forecasting, gross margins & peak hours', 'url' => ADMIN_URL . '/reports.php', 'icon' => 'bi-graph-up-arrow', 'category' => 'Navigation', 'tags' => 'reports bi charts analytics trends sales revenue'],
    ['title' => 'User & Staff RBAC Management', 'subtitle' => 'System roles, permissions, passwords & profiles', 'url' => ADMIN_URL . '/users.php', 'icon' => 'bi-people', 'category' => 'Navigation', 'tags' => 'users staff rbac roles permissions accounts admin'],
    ['title' => 'Order Audit Trail & Logs', 'subtitle' => 'Immutable audit history of status changes & actor events', 'url' => ADMIN_URL . '/audit-logs.php', 'icon' => 'bi-shield-check', 'category' => 'Navigation', 'tags' => 'audit log history events security compliance'],
    ['title' => 'Customer Food Reviews & Ratings', 'subtitle' => 'Customer feedback, star ratings & dish sentiment', 'url' => ADMIN_URL . '/reviews.php', 'icon' => 'bi-star', 'category' => 'Navigation', 'tags' => 'reviews ratings stars feedback customers comments'],
    ['title' => 'Dynamic Customer FAQs Desk', 'subtitle' => 'Self-service helpdesk knowledgebase editor', 'url' => ADMIN_URL . '/faqs.php', 'icon' => 'bi-question-circle', 'category' => 'Navigation', 'tags' => 'faqs helpdesk questions answers knowledgebase support']
];

// If query is empty, return default top navigation shortcuts
if ($cleanQuery === '') {
    foreach (array_slice($navigationPages, 0, 6) as $p) {
        $results[] = $p;
    }
} else {
    // Match navigation pages
    foreach ($navigationPages as $p) {
        if (str_contains(strtolower($p['title']), $cleanQuery) || 
            str_contains(strtolower($p['subtitle']), $cleanQuery) || 
            str_contains($p['tags'], $cleanQuery)) {
            $results[] = $p;
        }
    }

    // Match Orders (Limit 4)
    $orderWhere = "o.order_id = :nid OR u.full_name LIKE :sq1 OR u.phone_number LIKE :sq2";
    $orderParams = [
        ':nid' => $numQuery > 0 ? $numQuery : -1,
        ':sq1' => "%{$cleanQuery}%",
        ':sq2' => "%{$cleanQuery}%"
    ];
    $orders = db_fetch_all("
        SELECT o.order_id, o.order_status, o.total_amount, o.placed_at, u.full_name, u.phone_number
        FROM `orders` o
        JOIN `users` u ON o.user_id = u.user_id
        WHERE $orderWhere
        ORDER BY o.placed_at DESC
        LIMIT 4
    ", $orderParams);

    foreach ($orders as $ord) {
        $results[] = [
            'title' => "#ZIP-{$ord['order_id']} • {$ord['full_name']}",
            'subtitle' => format_inr($ord['total_amount']) . " • Status: {$ord['order_status']}",
            'url' => ADMIN_URL . "/orders.php?search=" . $ord['order_id'],
            'icon' => 'bi-receipt-cutoff',
            'category' => 'Orders'
        ];
    }

    // Match Menu Dishes (Limit 4)
    $dishes = db_fetch_all("
        SELECT m.item_id, m.item_name, m.price, m.is_available, m.stock_quantity, c.category_name
        FROM `menu_items` m
        LEFT JOIN `categories` c ON m.category_id = c.category_id
        WHERE m.item_name LIKE :sq1 OR c.category_name LIKE :sq2
        ORDER BY m.item_name ASC
        LIMIT 4
    ", [
        ':sq1' => "%{$cleanQuery}%",
        ':sq2' => "%{$cleanQuery}%"
    ]);

    foreach ($dishes as $dish) {
        $stockStatus = $dish['is_available'] ? "{$dish['stock_quantity']} in stock" : "86 / SOLD OUT";
        $results[] = [
            'title' => $dish['item_name'],
            'subtitle' => format_inr($dish['price']) . " • " . ($dish['category_name'] ?: 'Catalog') . " • {$stockStatus}",
            'url' => ADMIN_URL . "/dishes.php?search=" . urlencode($dish['item_name']),
            'icon' => 'bi-basket2',
            'category' => 'Dishes'
        ];
    }

    // Match Riders / Staff (Limit 3)
    $riders = db_fetch_all("
        SELECT sp.profile_id, sp.designation, sp.duty_status, u.full_name, u.phone_number
        FROM `staff_profiles` sp
        JOIN `users` u ON sp.user_id = u.user_id
        WHERE u.full_name LIKE :sq1 OR u.phone_number LIKE :sq2
        LIMIT 3
    ", [
        ':sq1' => "%{$cleanQuery}%",
        ':sq2' => "%{$cleanQuery}%"
    ]);

    foreach ($riders as $r) {
        $results[] = [
            'title' => "{$r['full_name']} ({$r['designation']})",
            'subtitle' => "Status: {$r['duty_status']} • Contact: {$r['phone_number']}",
            'url' => ADMIN_URL . "/delivery-view.php",
            'icon' => 'bi-person-badge',
            'category' => 'Staff & Couriers'
        ];
    }
}

echo json_encode([
    'success' => true,
    'query' => $query,
    'count' => count($results),
    'results' => array_slice($results, 0, 10)
], JSON_PRETTY_PRINT);
