<?php
/**
 * User & Staff Role-Based Access Control (RBAC) Management
 * Zippy - Online Food Ordering Platform
 * MCSP-232: MCA Major Project | Student: Pooja JP
 * 
 * Implements:
 * - UC-20: Manage User RBAC Permissions
 * - Provision new Staff Accounts (Admin, Kitchen Chef, Delivery Rider)
 * - Modify User Roles (Promote/Demote with self-demotion & last-admin lockout guards)
 * - Edit User Contact Profiles & Reset Passwords
 * - Activate/Suspend User Accounts with anti-lockout safeguards
 * - Server-side pagination and CSV Data Export
 * - Real-time User Details & Activity Offcanvas Inspection
 * - Interactive RBAC Permissions Matrix across all 4 operational roles
 */

$pageTitle = 'User & Staff RBAC';
$activePage = 'users';

// Bootstrap core libraries before HTML output (prevents "headers already sent")
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Role-Based Access Control: Administrator privileges required (Role 1: ADMIN)
require_admin();
$currentUser = current_user();

// =============================================================================
// CSV EXPORT HANDLER
// =============================================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $roleFilter = clean_input($_GET['role'] ?? '');
    $statusFilter = clean_input($_GET['status'] ?? '');
    $searchQuery = clean_input($_GET['q'] ?? '');

    $where = "1=1";
    $params = [];
    if (!empty($roleFilter)) {
        $where .= " AND r.role_name = :rname";
        $params[':rname'] = strtoupper($roleFilter);
    }
    if ($statusFilter === 'active') {
        $where .= " AND u.is_active = 1";
    } elseif ($statusFilter === 'suspended') {
        $where .= " AND u.is_active = 0";
    }
    if (!empty($searchQuery)) {
        $cleanQ = preg_replace('/^#?USR-?/i', '', $searchQuery);
        if (is_numeric($cleanQ)) {
            $where .= " AND (u.user_id = :exact_id OR u.full_name LIKE :q OR u.email LIKE :q OR u.phone_number LIKE :q)";
            $params[':exact_id'] = (int)$cleanQ;
            $params[':q'] = "%{$searchQuery}%";
        } else {
            $where .= " AND (u.full_name LIKE :q OR u.email LIKE :q OR u.phone_number LIKE :q)";
            $params[':q'] = "%{$searchQuery}%";
        }
    }

    $exportUsers = db_fetch_all("
        SELECT u.*, r.role_name 
        FROM `users` u
        JOIN `roles` r ON u.role_id = r.role_id
        WHERE $where
        ORDER BY u.role_id ASC, u.user_id DESC
    ", $params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=zippy_users_directory_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['User ID', 'Full Name', 'Role', 'Email', 'Phone Number', 'Account Status', 'Created At']);
    foreach ($exportUsers as $u) {
        fputcsv($out, [
            '#USR-' . $u['user_id'],
            $u['full_name'],
            $u['role_name'],
            $u['email'],
            $u['phone_number'],
            $u['is_active'] ? 'Active' : 'Suspended',
            $u['created_at']
        ]);
    }
    fclose($out);
    exit;
}

// =============================================================================
// AJAX ENDPOINT: GET USER DETAILS & ACTIVITY STATS
// =============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_user_details') {
    header('Content-Type: application/json; charset=utf-8');
    $uid = (int)($_GET['user_id'] ?? 0);
    $user = db_fetch_one("
        SELECT u.user_id, u.role_id, u.full_name, u.email, u.phone_number, u.is_active, u.created_at,
               r.role_name, r.description AS role_desc
        FROM `users` u
        JOIN `roles` r ON u.role_id = r.role_id
        WHERE u.user_id = :id
    ", [':id' => $uid]);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User account not found.']);
        exit;
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
            'created_at' => date('d M Y, h:i A', strtotime($user['created_at'])),
            'role_badge' => get_role_badge($user['role_name'])
        ],
        'stats' => []
    ];

    if ($user['role_name'] === 'CUSTOMER') {
        $totalOrders = (int)db_fetch_val("SELECT COUNT(*) FROM `orders` WHERE `user_id` = :uid", [':uid' => $uid]) ?: 0;
        $totalSpend = (float)db_fetch_val("SELECT COALESCE(SUM(total_amount), 0) FROM `orders` WHERE `user_id` = :uid AND `order_status` = 'DELIVERED'", [':uid' => $uid]) ?: 0.0;
        $addressesCount = (int)db_fetch_val("SELECT COUNT(*) FROM `user_addresses` WHERE `user_id` = :uid", [':uid' => $uid]) ?: 0;
        $recentOrders = db_fetch_all("
            SELECT order_id, order_number, total_amount, order_status, placed_at 
            FROM `orders` 
            WHERE `user_id` = :uid 
            ORDER BY placed_at DESC 
            LIMIT 3
        ", [':uid' => $uid]);

        $data['stats'] = [
            'type' => 'customer',
            'total_orders' => $totalOrders,
            'total_spend' => format_inr($totalSpend),
            'addresses_count' => $addressesCount,
            'recent_orders' => $recentOrders
        ];
    } elseif ($user['role_name'] === 'RIDER') {
        $completed = (int)db_fetch_val("SELECT COUNT(*) FROM `delivery_dispatches` WHERE `rider_id` = :uid AND `delivery_status` = 'DELIVERED'", [':uid' => $uid]) ?: 0;
        $active = (int)db_fetch_val("SELECT COUNT(*) FROM `delivery_dispatches` WHERE `rider_id` = :uid AND `delivery_status` IN ('ASSIGNED', 'PICKED_UP', 'IN_TRANSIT')", [':uid' => $uid]) ?: 0;
        $data['stats'] = [
            'type' => 'rider',
            'completed_deliveries' => $completed,
            'active_dispatches' => $active
        ];
    } else {
        $data['stats'] = [
            'type' => 'staff'
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// =============================================================================
// HANDLE RBAC FORM ACTIONS (POST)
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid or expired session token. Please try again.');
        header("Location: " . ADMIN_URL . "/users.php");
        exit;
    } 
    
    // 1. Provision New Staff Account
    if ($_POST['action'] === 'add_staff') {
        $name = clean_input($_POST['full_name'] ?? '');
        $email = clean_input(strtolower($_POST['email'] ?? ''));
        $phone = clean_input($_POST['phone_number'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $pass = $_POST['password'] ?? '';

        if (empty($name) || empty($email) || empty($phone) || empty($pass)) {
            set_flash('danger', 'Please provide name, official email, phone number, and temporary password.');
            header("Location: " . ADMIN_URL . "/users.php");
            exit;
        }

        if (strlen($pass) < 6) {
            set_flash('danger', 'Password must be at least 6 characters long.');
            header("Location: " . ADMIN_URL . "/users.php");
            exit;
        }

        // Validate role exists and is NOT customer (Role 2)
        $roleInfo = db_fetch_one("SELECT * FROM `roles` WHERE `role_id` = :rid", [':rid' => $roleId]);
        if (!$roleInfo || (int)$roleInfo['role_id'] === 2) {
            set_flash('danger', 'Invalid staff role specified. Customers cannot be provisioned as staff.');
            header("Location: " . ADMIN_URL . "/users.php");
            exit;
        }

        // Check duplicate email
        $existingEmail = db_fetch_val("SELECT COUNT(*) FROM `users` WHERE LOWER(email) = :email", [':email' => $email]);
        if ($existingEmail > 0) {
            set_flash('danger', "An account with email '{$email}' already exists.");
            header("Location: " . ADMIN_URL . "/users.php");
            exit;
        }

        // Check duplicate phone
        $existingPhone = db_fetch_val("SELECT COUNT(*) FROM `users` WHERE `phone_number` = :phone", [':phone' => $phone]);
        if ($existingPhone > 0) {
            set_flash('danger', "An account with phone number '{$phone}' already exists.");
            header("Location: " . ADMIN_URL . "/users.php");
            exit;
        }

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        db_query("
            INSERT INTO `users` (`role_id`, `full_name`, `email`, `phone_number`, `password_hash`, `is_active`) 
            VALUES (:rid, :name, :email, :phone, :hash, 1)
        ", [
            ':rid' => $roleId,
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':hash' => $hash
        ]);

        set_flash('success', "New staff member '{$name}' created with role privileges: {$roleInfo['role_name']}.");
        header("Location: " . ADMIN_URL . "/users.php");
        exit;
    }

    // 2. Edit User Profile Details
    elseif ($_POST['action'] === 'edit_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $name = clean_input($_POST['full_name'] ?? '');
        $email = clean_input(strtolower($_POST['email'] ?? ''));
        $phone = clean_input($_POST['phone_number'] ?? '');

        if (empty($name) || empty($email) || empty($phone)) {
            set_flash('danger', 'Full name, email, and phone number cannot be blank.');
            header("Location: " . ADMIN_URL . "/users.php");
            exit;
        }

        $target = db_fetch_one("SELECT * FROM `users` WHERE `user_id` = :id", [':id' => $uid]);
        if (!$target) {
            set_flash('danger', 'User account not found.');
            header("Location: " . ADMIN_URL . "/users.php");
            exit;
        }

        // Check duplicate email (excluding this user)
        $existingEmail = db_fetch_val("SELECT COUNT(*) FROM `users` WHERE LOWER(email) = :email AND `user_id` != :id", [':email' => $email, ':id' => $uid]);
        if ($existingEmail > 0) {
            set_flash('danger', "Email '{$email}' is already in use by another account.");
            header("Location: " . ADMIN_URL . "/users.php");
            exit;
        }

        // Check duplicate phone (excluding this user)
        $existingPhone = db_fetch_val("SELECT COUNT(*) FROM `users` WHERE `phone_number` = :phone AND `user_id` != :id", [':phone' => $phone, ':id' => $uid]);
        if ($existingPhone > 0) {
            set_flash('danger', "Phone number '{$phone}' is already in use by another account.");
            header("Location: " . ADMIN_URL . "/users.php");
            exit;
        }

        db_query("UPDATE `users` SET `full_name` = :name, `email` = :email, `phone_number` = :phone WHERE `user_id` = :id", [
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':id' => $uid
        ]);

        set_flash('success', "Profile details for '{$name}' (#USR-{$uid}) updated successfully.");
        header("Location: " . ADMIN_URL . "/users.php");
        exit;
    }

    // 3. Reset User Password
    elseif ($_POST['action'] === 'reset_password') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $newPass = $_POST['new_password'] ?? '';

        if (strlen($newPass) < 6) {
            set_flash('danger', 'Temporary password must be at least 6 characters long.');
            header("Location: " . ADMIN_URL . "/users.php");
            exit;
        }

        $target = db_fetch_one("SELECT * FROM `users` WHERE `user_id` = :id", [':id' => $uid]);
        if (!$target) {
            set_flash('danger', 'User account not found.');
            header("Location: " . ADMIN_URL . "/users.php");
            exit;
        }

        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        db_query("UPDATE `users` SET `password_hash` = :hash WHERE `user_id` = :id", [
            ':hash' => $hash,
            ':id' => $uid
        ]);

        set_flash('success', "Password successfully reset for '{$target['full_name']}'.");
        header("Location: " . ADMIN_URL . "/users.php");
        exit;
    }
    
    // 4. Toggle User Account Status (Active / Suspended)
    elseif ($_POST['action'] === 'toggle_active') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $newStatus = (int)($_POST['is_active'] ?? 0);

        $target = db_fetch_one("SELECT * FROM `users` WHERE `user_id` = :id", [':id' => $uid]);
        if (!$target) {
            set_flash('danger', 'User account not found.');
            header("Location: " . ADMIN_URL . "/users.php");
            exit;
        }

        // Guard 1: Prevent self-deactivation by active administrator (UC-20)
        if ($uid === (int)($currentUser['id'] ?? 0) && $newStatus === 0) {
            set_flash('danger', 'Security Restriction: Self-deactivation by the active administrator is prohibited to prevent lockout.');
            header("Location: " . ADMIN_URL . "/users.php");
            exit;
        }

        // Guard 2: Prevent deactivating the last active administrator
        if ((int)$target['role_id'] === 1 && $newStatus === 0) {
            $otherAdmins = (int)db_fetch_val("
                SELECT COUNT(*) FROM `users` 
                WHERE `role_id` = 1 AND `is_active` = 1 AND `user_id` != :id
            ", [':id' => $uid]);
            if ($otherAdmins <= 0) {
                set_flash('danger', 'Security Restriction: Cannot suspend the last remaining active Administrator.');
                header("Location: " . ADMIN_URL . "/users.php");
                exit;
            }
        }

        db_query("UPDATE `users` SET `is_active` = :act WHERE `user_id` = :id", [
            ':act' => $newStatus,
            ':id' => $uid
        ]);

        $statusLabel = $newStatus ? 'reactivated' : 'suspended';
        set_flash('success', "User account for '{$target['full_name']}' (#USR-{$uid}) has been {$statusLabel}.");
        header("Location: " . ADMIN_URL . "/users.php");
        exit;
    }

    // 5. Update Existing User Role (RBAC Privilege Reassignment)
    elseif ($_POST['action'] === 'update_role') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $newRoleId = (int)($_POST['role_id'] ?? 0);

        $result = update_user_role($uid, $newRoleId);
        if ($result['success']) {
            set_flash('success', $result['message']);
        } else {
            set_flash('danger', $result['message']);
        }
        header("Location: " . ADMIN_URL . "/users.php");
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';

// Helper: Calculate user initials
function get_user_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    if (!empty($parts[0])) {
        $initials .= mb_strtoupper(mb_substr($parts[0], 0, 1));
    }
    if (count($parts) > 1 && !empty(end($parts))) {
        $initials .= mb_strtoupper(mb_substr(end($parts), 0, 1));
    }
    return $initials ?: 'U';
}

// Role Filtering, Status Filtering & Pagination Query Setup
$roleFilter = clean_input($_GET['role'] ?? '');
$statusFilter = clean_input($_GET['status'] ?? '');
$searchQuery = clean_input($_GET['q'] ?? '');
$activeTab = clean_input($_GET['tab'] ?? 'directory');

$where = "1=1";
$params = [];
if (!empty($roleFilter)) {
    $where .= " AND r.role_name = :rname";
    $params[':rname'] = strtoupper($roleFilter);
}
if ($statusFilter === 'active') {
    $where .= " AND u.is_active = 1";
} elseif ($statusFilter === 'suspended') {
    $where .= " AND u.is_active = 0";
}
if (!empty($searchQuery)) {
    $cleanQ = preg_replace('/^#?USR-?/i', '', $searchQuery);
    if (is_numeric($cleanQ)) {
        $where .= " AND (u.user_id = :exact_id OR u.full_name LIKE :q OR u.email LIKE :q OR u.phone_number LIKE :q)";
        $params[':exact_id'] = (int)$cleanQ;
        $params[':q'] = "%{$searchQuery}%";
    } else {
        $where .= " AND (u.full_name LIKE :q OR u.email LIKE :q OR u.phone_number LIKE :q)";
        $params[':q'] = "%{$searchQuery}%";
    }
}

// Server-side pagination parameters
$perPage = 15;
$totalFiltered = (int)db_fetch_val("
    SELECT COUNT(*) 
    FROM `users` u
    JOIN `roles` r ON u.role_id = r.role_id
    WHERE $where
", $params) ?: 0;

$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
$currentPage = min($totalPages, max(1, (int)($_GET['page'] ?? 1)));
$offset = ($currentPage - 1) * $perPage;

$users = db_fetch_all("
    SELECT u.*, r.role_name, r.description AS role_desc
    FROM `users` u
    JOIN `roles` r ON u.role_id = r.role_id
    WHERE $where
    ORDER BY u.role_id ASC, u.user_id DESC
    LIMIT $offset, $perPage
", $params);

// Fetch dynamic roles from DB
$roles = db_fetch_all("SELECT * FROM `roles` ORDER BY `role_id` ASC");

// Role metrics & active counts
$totalUsersCount = (int)db_fetch_val("SELECT COUNT(*) FROM `users`") ?: 0;
$adminCount = (int)db_fetch_val("SELECT COUNT(*) FROM `users` WHERE `role_id` = 1") ?: 0;
$kitchenCount = (int)db_fetch_val("SELECT COUNT(*) FROM `users` WHERE `role_id` = 3") ?: 0;
$riderCount = (int)db_fetch_val("SELECT COUNT(*) FROM `users` WHERE `role_id` = 4") ?: 0;
$customerCount = (int)db_fetch_val("SELECT COUNT(*) FROM `users` WHERE `role_id` = 2") ?: 0;

$inactiveUsersCount = (int)db_fetch_val("SELECT COUNT(*) FROM `users` WHERE `is_active` = 0") ?: 0;

// Reusable query string builder for filters & pagination
$paginationParams = ['tab' => 'directory'];
if (!empty($roleFilter)) $paginationParams['role'] = $roleFilter;
if (!empty($statusFilter)) $paginationParams['status'] = $statusFilter;
if (!empty($searchQuery)) $paginationParams['q'] = $searchQuery;
?>

<!-- ===========================================================================
     INTERACTIVE ROLE METRIC CARDS (CLICK TO FILTER)
     =========================================================================== -->
<div class="row g-3 mb-4">
  <!-- 1. Administrators -->
  <div class="col-12 col-sm-6 col-xl-3">
    <a href="<?= ADMIN_URL ?>/users.php?role=ADMIN&tab=directory" class="text-decoration-none">
      <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100 transition-card <?= ($roleFilter === 'ADMIN') ? 'border border-2 border-danger' : '' ?>">
        <div class="d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-danger bg-danger bg-opacity-10 fs-4">
              <i class="bi bi-shield-lock-fill"></i>
            </div>
            <div>
              <div class="text-muted small fw-semibold">Administrators</div>
              <div class="h3 fw-bold text-dark mb-0 font-outfit"><?= $adminCount ?></div>
            </div>
          </div>
          <span class="badge bg-danger bg-opacity-15 text-danger small px-2 py-1">Role 1</span>
        </div>
        <div class="mt-2 pt-2 border-top d-flex align-items-center justify-content-between text-muted small">
          <span>Full Operations Access</span>
          <i class="bi bi-arrow-right-short text-danger"></i>
        </div>
      </div>
    </a>
  </div>

  <!-- 2. Kitchen Chefs -->
  <div class="col-12 col-sm-6 col-xl-3">
    <a href="<?= ADMIN_URL ?>/users.php?role=KITCHEN&tab=directory" class="text-decoration-none">
      <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100 transition-card <?= ($roleFilter === 'KITCHEN') ? 'border border-2 border-warning' : '' ?>">
        <div class="d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-warning bg-warning bg-opacity-10 fs-4">
              <i class="fa-solid fa-fire-burner"></i>
            </div>
            <div>
              <div class="text-muted small fw-semibold">Kitchen Chefs</div>
              <div class="h3 fw-bold text-dark mb-0 font-outfit"><?= $kitchenCount ?></div>
            </div>
          </div>
          <span class="badge bg-warning bg-opacity-15 text-dark small px-2 py-1">Role 3</span>
        </div>
        <div class="mt-2 pt-2 border-top d-flex align-items-center justify-content-between text-muted small">
          <span>KDS & Order Prep</span>
          <i class="bi bi-arrow-right-short text-warning"></i>
        </div>
      </div>
    </a>
  </div>

  <!-- 3. Delivery Fleet -->
  <div class="col-12 col-sm-6 col-xl-3">
    <a href="<?= ADMIN_URL ?>/users.php?role=RIDER&tab=directory" class="text-decoration-none">
      <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100 transition-card <?= ($roleFilter === 'RIDER') ? 'border border-2 border-info' : '' ?>">
        <div class="d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-info bg-info bg-opacity-10 fs-4">
              <i class="bi bi-bicycle"></i>
            </div>
            <div>
              <div class="text-muted small fw-semibold">Delivery Fleet</div>
              <div class="h3 fw-bold text-dark mb-0 font-outfit"><?= $riderCount ?></div>
            </div>
          </div>
          <span class="badge bg-info bg-opacity-15 text-dark small px-2 py-1">Role 4</span>
        </div>
        <div class="mt-2 pt-2 border-top d-flex align-items-center justify-content-between text-muted small">
          <span>Logistics & OTP</span>
          <i class="bi bi-arrow-right-short text-info"></i>
        </div>
      </div>
    </a>
  </div>

  <!-- 4. Registered Customers -->
  <div class="col-12 col-sm-6 col-xl-3">
    <a href="<?= ADMIN_URL ?>/users.php?role=CUSTOMER&tab=directory" class="text-decoration-none">
      <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100 transition-card <?= ($roleFilter === 'CUSTOMER') ? 'border border-2 border-success' : '' ?>">
        <div class="d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-3 text-success bg-success bg-opacity-10 fs-4">
              <i class="bi bi-person-fill"></i>
            </div>
            <div>
              <div class="text-muted small fw-semibold">Customers</div>
              <div class="h3 fw-bold text-dark mb-0 font-outfit"><?= $customerCount ?></div>
            </div>
          </div>
          <span class="badge bg-success bg-opacity-15 text-success small px-2 py-1">Role 2</span>
        </div>
        <div class="mt-2 pt-2 border-top d-flex align-items-center justify-content-between text-muted small">
          <span>Storefront & Orders</span>
          <i class="bi bi-arrow-right-short text-success"></i>
        </div>
      </div>
    </a>
  </div>
</div>

<!-- ===========================================================================
     MAIN CARD CONTAINER (TABS & TOOLBAR)
     =========================================================================== -->
<div class="card border-0 rounded-4 shadow-sm bg-white mb-4">
  
  <!-- Navigation Header: Title & Action CTAs -->
  <div class="card-header bg-white border-bottom p-4 pb-0">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
      <div>
        <h4 class="fw-bold font-outfit mb-1 text-dark">Role-Based Access Control (RBAC) Management</h4>
        <p class="text-muted small mb-0">Manage user authorization claims, staff provisioning, credential maintenance, and security controls.</p>
      </div>

      <div class="d-flex align-items-center gap-2">
        <!-- Export CSV Button -->
        <a href="<?= ADMIN_URL ?>/users.php?export=csv<?= !empty($roleFilter) ? '&role=' . urlencode($roleFilter) : '' ?><?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?><?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?>" 
           class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs">
          <i class="bi bi-file-earmark-arrow-down me-1"></i> Export Directory (CSV)
        </a>

        <!-- Provision Staff Button -->
        <button type="button" class="btn btn-success btn-sm rounded-pill px-3 fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#addStaffModal" style="background:#2E6F40; border-color:#2E6F40;">
          <i class="bi bi-person-plus-fill me-1"></i> Provision Staff Account
        </button>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs border-bottom-0 gap-2">
      <li class="nav-item">
        <a class="nav-link fw-semibold rounded-top-3 px-4 <?= ($activeTab !== 'matrix') ? 'active bg-light border-bottom-0 text-dark' : 'text-muted' ?>" href="<?= ADMIN_URL ?>/users.php?tab=directory">
          <i class="bi bi-people me-2"></i>Staff & User Directory (<?= $totalUsersCount ?>)
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link fw-semibold rounded-top-3 px-4 <?= ($activeTab === 'matrix') ? 'active bg-light border-bottom-0 text-dark' : 'text-muted' ?>" href="<?= ADMIN_URL ?>/users.php?tab=matrix">
          <i class="bi bi-shield-check me-2"></i>RBAC Permissions Matrix
        </a>
      </li>
    </ul>
  </div>

  <div class="card-body p-4">
    <?php if ($activeTab === 'matrix'): ?>
      <!-- =====================================================================
           TAB 2: RBAC PERMISSIONS MATRIX
           ===================================================================== -->
      <div class="mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <h5 class="fw-bold font-outfit mb-1 text-dark">System Capability & Access Control Grid</h5>
            <p class="text-muted small mb-0">
              Granular permission claims enforced across session middleware, HTTP verbs, and operational routes.
            </p>
          </div>
          <!-- Legend -->
          <div class="d-flex align-items-center gap-3 small bg-light p-2 px-3 rounded-pill border">
            <span><i class="bi bi-check-circle-fill text-success me-1"></i> Full Access</span>
            <span><span class="badge bg-success bg-opacity-25 text-success py-0 px-2 me-1">Scoped</span> Own/Assigned</span>
            <span><i class="bi bi-x-circle text-muted me-1"></i> Prohibited (403)</span>
          </div>
        </div>

        <div class="table-responsive rounded-3 border">
          <table class="table table-bordered align-middle small mb-0">
            <thead class="table-light">
              <tr class="text-center align-middle">
                <th class="text-start" style="width: 32%;">System Capability & Module</th>
                <th style="width: 20%;">Route / Enforcement</th>
                <th style="width: 12%;" class="bg-danger bg-opacity-10 text-danger">
                  <div class="fw-bold">ADMIN</div>
                  <div class="small fw-normal">Role 1</div>
                </th>
                <th style="width: 12%;" class="bg-warning bg-opacity-10 text-dark">
                  <div class="fw-bold">KITCHEN</div>
                  <div class="small fw-normal">Role 3</div>
                </th>
                <th style="width: 12%;" class="bg-info bg-opacity-10 text-dark">
                  <div class="fw-bold">RIDER</div>
                  <div class="small fw-normal">Role 4</div>
                </th>
                <th style="width: 12%;" class="bg-success bg-opacity-10 text-success">
                  <div class="fw-bold">CUSTOMER</div>
                  <div class="small fw-normal">Role 2</div>
                </th>
              </tr>
            </thead>
            <tbody>
              <!-- Group 1: Admin Console -->
              <tr class="table-secondary fw-bold">
                <td colspan="6"><i class="bi bi-gear-fill me-2 text-dark"></i>Restaurant Administration & Executive Control</td>
              </tr>
              <tr>
                <td><strong>Admin Operations Dashboard</strong><br><span class="text-muted small">Live KPIs, revenue aggregates, WSJF snapshot</span></td>
                <td><code>/admin/index.php</code></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
              </tr>
              <tr>
                <td><strong>Dish Catalog & Inventory CRUD</strong><br><span class="text-muted small">Create, edit, price, and adjust stock quantities</span></td>
                <td><code>/admin/dishes.php</code></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
              </tr>
              <tr>
                <td><strong>Food Category Taxonomy</strong><br><span class="text-muted small">Category slugs, display ordering, hero banners</span></td>
                <td><code>/admin/categories.php</code></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
              </tr>
              <tr>
                <td><strong>User & Staff RBAC Administration</strong><br><span class="text-muted small">Role assignments, provisioning, account suspension</span></td>
                <td><code>/admin/users.php</code></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
              </tr>
              <tr>
                <td><strong>Executive BI Analytics & Demand Forecasts</strong><br><span class="text-muted small">Visualizations, revenue charts, and demand modeling</span></td>
                <td><code>/admin/reports.php</code></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
              </tr>
              <tr>
                <td><strong>Order Lifecycle Audit Trails</strong><br><span class="text-muted small">Immutable chronological logs with actor ID stamps</span></td>
                <td><code>/admin/audit-logs.php</code></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
              </tr>

              <!-- Group 2: Kitchen KDS -->
              <tr class="table-secondary fw-bold">
                <td colspan="6"><i class="fa-solid fa-fire-burner me-2 text-dark"></i>Kitchen Display System (KDS) & Cooking Station</td>
              </tr>
              <tr>
                <td><strong>Kitchen Workspace & WSJF Queue</strong><br><span class="text-muted small">Prioritized preparation lanes (Incoming, Cooking, Ready)</span></td>
                <td><code>/kitchen/index.php</code></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
              </tr>
              <tr>
                <td><strong>Order Status Advance (Cooking / Ready / Handover)</strong><br><span class="text-muted small">Live state machine transitions with dispatch updates</span></td>
                <td><code>/kitchen/ajax/update-status.php</code></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
              </tr>
              <tr>
                <td><strong>86-ing Stock / Instant Availability Toggle</strong><br><span class="text-muted small">Kitchen toggle to mark dishes Sold Out / In Stock</span></td>
                <td><code>/kitchen/ajax/toggle-stock.php</code></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
              </tr>
              <tr>
                <td><strong>Thermal KOT (Kitchen Order Ticket) 80mm Slip</strong><br><span class="text-muted small">Cook station physical receipt formatting</span></td>
                <td><code>/kitchen/kot.php</code></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
              </tr>

              <!-- Group 3: Delivery Fleet -->
              <tr class="table-secondary fw-bold">
                <td colspan="6"><i class="bi bi-bicycle me-2 text-dark"></i>Delivery Fleet & Doorstep Handshake Logistics</td>
              </tr>
              <tr>
                <td><strong>Rider Fleet Dashboard & Earnings Ledger</strong><br><span class="text-muted small">Shift metrics, wallet balance, assigned dispatches</span></td>
                <td><code>/delivery/index.php</code></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
              </tr>
              <tr>
                <td><strong>Claim Delivery & GPS Navigation Guidance</strong><br><span class="text-muted small">Claim ready order to transit, customer dialer bridge</span></td>
                <td><code>/delivery/index.php</code></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
              </tr>
              <tr>
                <td><strong>6-Digit Doorstep Cryptographic OTP Verification</strong><br><span class="text-muted small">Customer handshake authentication to mark Delivered</span></td>
                <td><code>/delivery/index.php</code></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
              </tr>

              <!-- Group 4: Customer Storefront -->
              <tr class="table-secondary fw-bold">
                <td colspan="6"><i class="bi bi-shop me-2 text-dark"></i>Customer Storefront & Order Lifecycle</td>
              </tr>
              <tr>
                <td><strong>Menu Browsing & Dietary Filtering</strong><br><span class="text-muted small">Veg/Non-veg, Healthy tags, Apriori recommendations</span></td>
                <td><code>/customer/index.php</code></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
              </tr>
              <tr>
                <td><strong>ACID Checkout & Concurrency Locking</strong><br><span class="text-muted small">Row-level locking checkout with pessimistic lock</span></td>
                <td><code>/customer/checkout.php</code></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
              </tr>
              <tr>
                <td><strong>Live Order Status & Dynamic Countdown Timer</strong><br><span class="text-muted small">Polling tracker, OTP access, 60s cancellation</span></td>
                <td><code>/customer/order-status.php</code></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><span class="badge bg-success bg-opacity-25 text-success">Own Orders</span></td>
              </tr>
              <tr>
                <td><strong>Order History & 1-Click Reorder Drawer</strong><br><span class="text-muted small">Ongoing, completed, and cancelled tabs</span></td>
                <td><code>/customer/order-history.php</code></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><span class="badge bg-success bg-opacity-25 text-success">Own Orders</span></td>
              </tr>
              <tr>
                <td><strong>Official GST Tax Invoices (SAC 996331)</strong><br><span class="text-muted small">Downloadable tax receipts with 5% CGST/SGST</span></td>
                <td><code>/customer/invoice.php</code></td>
                <td class="text-center"><i class="bi bi-check-circle-fill text-success fs-5"></i></td>
                <td class="text-center"><i class="bi bi-x-circle text-muted fs-5"></i></td>
                <td class="text-center"><span class="badge bg-info bg-opacity-25 text-dark">Assigned</span></td>
                <td class="text-center"><span class="badge bg-success bg-opacity-25 text-success">Own Orders</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    <?php else: ?>
      <!-- =====================================================================
           TAB 1: STAFF & USER DIRECTORY
           ===================================================================== -->
      
      <!-- Filters and Search Toolbar -->
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-3 mb-4">
        
        <!-- Role Filter Pills -->
        <div class="d-flex flex-wrap align-items-center gap-2">
          <a href="<?= ADMIN_URL ?>/users.php?tab=directory<?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?>" 
             class="btn btn-sm rounded-pill <?= empty($roleFilter) ? 'btn-dark' : 'btn-light border' ?>">
            All (<?= $totalUsersCount ?>)
          </a>
          <a href="<?= ADMIN_URL ?>/users.php?role=ADMIN&tab=directory<?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?>" 
             class="btn btn-sm rounded-pill <?= ($roleFilter === 'ADMIN') ? 'btn-danger' : 'btn-light border' ?>">
            <i class="bi bi-shield-lock-fill me-1"></i>Admin (<?= $adminCount ?>)
          </a>
          <a href="<?= ADMIN_URL ?>/users.php?role=KITCHEN&tab=directory<?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?>" 
             class="btn btn-sm rounded-pill <?= ($roleFilter === 'KITCHEN') ? 'btn-warning text-dark' : 'btn-light border' ?>">
            <i class="fa-solid fa-fire-burner me-1"></i>Kitchen (<?= $kitchenCount ?>)
          </a>
          <a href="<?= ADMIN_URL ?>/users.php?role=RIDER&tab=directory<?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?>" 
             class="btn btn-sm rounded-pill <?= ($roleFilter === 'RIDER') ? 'btn-info text-dark' : 'btn-light border' ?>">
            <i class="bi bi-bicycle me-1"></i>Rider (<?= $riderCount ?>)
          </a>
          <a href="<?= ADMIN_URL ?>/users.php?role=CUSTOMER&tab=directory<?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?>" 
             class="btn btn-sm rounded-pill <?= ($roleFilter === 'CUSTOMER') ? 'btn-success' : 'btn-light border' ?>">
            <i class="bi bi-person me-1"></i>Customer (<?= $customerCount ?>)
          </a>
        </div>

        <!-- Search & Status Form -->
        <form method="GET" action="<?= ADMIN_URL ?>/users.php" class="d-flex align-items-center gap-2 flex-wrap">
          <input type="hidden" name="tab" value="directory">
          <?php if (!empty($roleFilter)): ?>
            <input type="hidden" name="role" value="<?= e($roleFilter) ?>">
          <?php endif; ?>

          <!-- Status Dropdown -->
          <select name="status" class="form-select form-select-sm bg-light border" style="width: auto;" onchange="this.form.submit()">
            <option value="" <?= empty($statusFilter) ? 'selected' : '' ?>>All Status</option>
            <option value="active" <?= ($statusFilter === 'active') ? 'selected' : '' ?>>Active Only</option>
            <option value="suspended" <?= ($statusFilter === 'suspended') ? 'selected' : '' ?>>Suspended (<?= $inactiveUsersCount ?>)</option>
          </select>

          <!-- Search Input -->
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" name="q" class="form-control bg-light border-start-0" placeholder="Search user, email, phone, #USR..." value="<?= e($searchQuery) ?>" style="min-width: 200px;">
          </div>
          <button type="submit" class="btn btn-sm btn-dark rounded-pill px-3">Filter</button>
          
          <?php if (!empty($searchQuery) || !empty($roleFilter) || !empty($statusFilter)): ?>
            <a href="<?= ADMIN_URL ?>/users.php?tab=directory" class="btn btn-sm btn-light border rounded-pill px-2" title="Clear Filters"><i class="bi bi-x-lg"></i></a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Users Table -->
      <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0">
          <thead class="table-light">
            <tr>
              <th style="min-width: 240px;">User & Identifier</th>
              <th style="min-width: 220px;">Contact Information</th>
              <th style="min-width: 150px;">Assigned Role</th>
              <th style="min-width: 110px;">Status</th>
              <th style="min-width: 130px;">Registered</th>
              <th style="min-width: 140px;" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr>
                <td colspan="6" class="text-center py-5 text-muted">
                  <i class="bi bi-people fs-1 d-block mb-2 text-muted opacity-50"></i>
                  <div class="fw-bold fs-6">No users found matching current filters.</div>
                  <p class="small text-muted mb-3">Try adjusting your role selection or search keywords.</p>
                  <a href="<?= ADMIN_URL ?>/users.php?tab=directory" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filters
                  </a>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($users as $u): ?>
                <?php
                  $isSelf = ((int)$u['user_id'] === (int)$currentUser['id']);
                  $initials = get_user_initials($u['full_name']);
                  $avatarColor = match($u['role_name']) {
                    'ADMIN' => 'bg-danger text-white',
                    'KITCHEN' => 'bg-warning text-dark',
                    'RIDER' => 'bg-info text-dark',
                    'CUSTOMER' => 'bg-success text-white',
                    default => 'bg-secondary text-white'
                  };
                ?>
                <tr>
                  <!-- User & ID -->
                  <td>
                    <div class="d-flex align-items-center gap-3">
                      <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-xs <?= $avatarColor ?>" 
                           style="width: 38px; height: 38px; font-size: 0.85rem; flex-shrink: 0;">
                        <?= e($initials) ?>
                      </div>
                      <div>
                        <div class="d-flex align-items-center gap-2">
                          <span class="fw-bold text-dark fs-6"><?= e($u['full_name']) ?></span>
                          <?php if ($isSelf): ?>
                            <span class="badge bg-primary bg-opacity-25 text-primary small px-2 py-0">You</span>
                          <?php endif; ?>
                        </div>
                        <div class="font-monospace text-muted small" style="font-size: 0.75rem;">
                          #USR-<?= (int)$u['user_id'] ?>
                        </div>
                      </div>
                    </div>
                  </td>

                  <!-- Contact Info -->
                  <td>
                    <div>
                      <a href="mailto:<?= e($u['email']) ?>" class="text-dark text-decoration-none">
                        <i class="bi bi-envelope text-muted me-1"></i><?= e($u['email']) ?>
                      </a>
                    </div>
                    <div class="small text-muted mt-1">
                      <a href="tel:<?= e($u['phone_number']) ?>" class="text-muted text-decoration-none">
                        <i class="bi bi-telephone me-1"></i><?= e($u['phone_number']) ?>
                      </a>
                    </div>
                  </td>

                  <!-- Assigned Role -->
                  <td>
                    <?= get_role_badge($u['role_name']) ?>
                  </td>

                  <!-- Status -->
                  <td>
                    <?php if ($u['is_active']): ?>
                      <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-2 py-1 rounded-pill">
                        <i class="bi bi-check-circle-fill me-1"></i>Active
                      </span>
                    <?php else: ?>
                      <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle px-2 py-1 rounded-pill">
                        <i class="bi bi-pause-circle-fill me-1"></i>Suspended
                      </span>
                    <?php endif; ?>
                  </td>

                  <!-- Registered -->
                  <td>
                    <div class="text-dark"><?= date('d M Y', strtotime($u['created_at'])) ?></div>
                    <div class="text-muted small" style="font-size:0.75rem;"><?= date('h:i A', strtotime($u['created_at'])) ?></div>
                  </td>

                  <!-- Actions Group -->
                  <td class="text-end">
                    <div class="d-inline-flex align-items-center gap-1">
                      <!-- Quick Role Button -->
                      <button type="button" 
                              class="btn btn-sm btn-outline-primary rounded-pill px-2 py-1 change-role-btn shadow-xs" 
                              data-bs-toggle="modal" 
                              data-bs-target="#changeRoleModal"
                              data-user-id="<?= (int)$u['user_id'] ?>"
                              data-user-name="<?= e($u['full_name']) ?>"
                              data-current-role-id="<?= (int)$u['role_id'] ?>"
                              data-current-role-name="<?= e($u['role_name']) ?>"
                              data-is-self="<?= $isSelf ? '1' : '0' ?>"
                              title="Modify Role">
                        <i class="bi bi-shield-shaded me-1"></i> Role
                      </button>

                      <!-- Three-Dots Action Dropdown Menu -->
                      <div class="dropdown">
                        <button class="btn btn-sm btn-light border rounded-circle p-1 shadow-xs" 
                                type="button" 
                                data-bs-toggle="dropdown" 
                                aria-expanded="false" 
                                style="width: 30px; height: 30px;"
                                title="Account Options">
                          <i class="bi bi-three-dots-vertical text-muted"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 small">
                          <!-- View Details & Activity Offcanvas -->
                          <li>
                            <button class="dropdown-item py-2 view-details-btn" 
                                    type="button"
                                    data-bs-toggle="offcanvas" 
                                    data-bs-target="#userDetailsOffcanvas"
                                    data-user-id="<?= (int)$u['user_id'] ?>">
                              <i class="bi bi-person-lines-fill text-primary me-2"></i> View Profile & Activity
                            </button>
                          </li>
                          
                          <!-- Edit Profile Contact Info -->
                          <li>
                            <button class="dropdown-item py-2 edit-user-btn" 
                                    type="button"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editUserModal"
                                    data-user-id="<?= (int)$u['user_id'] ?>"
                                    data-full-name="<?= e($u['full_name']) ?>"
                                    data-email="<?= e($u['email']) ?>"
                                    data-phone="<?= e($u['phone_number']) ?>">
                              <i class="bi bi-pencil-square text-secondary me-2"></i> Edit Contact Info
                            </button>
                          </li>

                          <!-- Reset Login Password -->
                          <li>
                            <button class="dropdown-item py-2 reset-pass-btn" 
                                    type="button"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#resetPasswordModal"
                                    data-user-id="<?= (int)$u['user_id'] ?>"
                                    data-user-name="<?= e($u['full_name']) ?>">
                              <i class="bi bi-key text-warning me-2"></i> Reset Password
                            </button>
                          </li>

                          <li><hr class="dropdown-divider my-1"></li>

                          <!-- Deactivate / Reactivate with Confirmation Modal -->
                          <li>
                            <button class="dropdown-item py-2 toggle-active-btn <?= $u['is_active'] ? 'text-danger' : 'text-success' ?>" 
                                    type="button"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#toggleActiveModal"
                                    data-user-id="<?= (int)$u['user_id'] ?>"
                                    data-user-name="<?= e($u['full_name']) ?>"
                                    data-is-active="<?= (int)$u['is_active'] ?>"
                                    data-role-name="<?= e($u['role_name']) ?>"
                                    data-is-self="<?= $isSelf ? '1' : '0' ?>">
                              <?php if ($u['is_active']): ?>
                                <i class="bi bi-slash-circle text-danger me-2"></i> Suspend Account
                              <?php else: ?>
                                <i class="bi bi-check-circle text-success me-2"></i> Reactivate Account
                              <?php endif; ?>
                            </button>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination Toolbar -->
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-top pt-3 mt-3">
        <div class="small text-muted">
          Showing <strong><?= empty($users) ? 0 : ($offset + 1) ?></strong> to 
          <strong><?= min($offset + $perPage, $totalFiltered) ?></strong> of 
          <strong><?= $totalFiltered ?></strong> registered records
        </div>

        <?php if ($totalPages > 1): ?>
          <nav aria-label="Users pagination">
            <ul class="pagination pagination-sm mb-0">
              <!-- Previous Page -->
              <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                <?php $paginationParams['page'] = max(1, $currentPage - 1); ?>
                <a class="page-link" href="<?= ADMIN_URL ?>/users.php?<?= e(http_build_query($paginationParams)) ?>">
                  <i class="bi bi-chevron-left"></i>
                </a>
              </li>

              <!-- Page Numbers -->
              <?php
                $startPage = max(1, $currentPage - 2);
                $endPage = min($totalPages, $currentPage + 2);
                if ($startPage > 1) {
                    $paginationParams['page'] = 1;
                    echo '<li class="page-item"><a class="page-link" href="' . ADMIN_URL . '/users.php?' . e(http_build_query($paginationParams)) . '">1</a></li>';
                    if ($startPage > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
                for ($p = $startPage; $p <= $endPage; $p++):
                    $paginationParams['page'] = $p;
              ?>
                <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                  <a class="page-link" href="<?= ADMIN_URL ?>/users.php?<?= e(http_build_query($paginationParams)) ?>"><?= $p ?></a>
                </li>
              <?php 
                endfor;
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    $paginationParams['page'] = $totalPages;
                    echo '<li class="page-item"><a class="page-link" href="' . ADMIN_URL . '/users.php?' . e(http_build_query($paginationParams)) . '">' . $totalPages . '</a></li>';
                }
              ?>

              <!-- Next Page -->
              <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                <?php $paginationParams['page'] = min($totalPages, $currentPage + 1); ?>
                <a class="page-link" href="<?= ADMIN_URL ?>/users.php?<?= e(http_build_query($paginationParams)) ?>">
                  <i class="bi bi-chevron-right"></i>
                </a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>
      </div>

    <?php endif; ?>
  </div>
</div>

<!-- =========================================================================
     MODAL 1: PROVISION NEW STAFF ACCOUNT
     ========================================================================= -->
<div class="modal fade" id="addStaffModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold font-outfit">
          <i class="bi bi-person-plus-fill me-2 text-success"></i>Provision New Staff Account
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" action="<?= ADMIN_URL ?>/users.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_staff">

        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-bold">Staff Role & Authorization Level *</label>
            <select name="role_id" class="form-select rounded-3" required>
              <?php foreach ($roles as $r): ?>
                <?php if ((int)$r['role_id'] !== 2): // Exclude Customer ?>
                  <option value="<?= (int)$r['role_id'] ?>" <?= ((int)$r['role_id'] === 3) ? 'selected' : '' ?>>
                    Role <?= (int)$r['role_id'] ?>: <?= e($r['role_name']) ?> (<?= e($r['description']) ?>)
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
            <div class="form-text small">Each role grants isolated portal routing, session middleware, and permissions.</div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Staff Full Name *</label>
            <input type="text" name="full_name" class="form-control rounded-3" placeholder="Sanjeev Kapoor" required>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Official Email *</label>
              <input type="email" name="email" class="form-control rounded-3" placeholder="chef@zippy.com" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Phone Number *</label>
              <input type="tel" name="phone_number" class="form-control rounded-3" placeholder="+91 98765 00000" required>
            </div>
          </div>

          <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between mb-1">
              <label class="form-label small fw-bold mb-0">Temporary Password *</label>
              <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small text-success fw-bold" onclick="generateStaffPassword('newStaffPassword')">
                <i class="bi bi-magic me-1"></i>Generate Password
              </button>
            </div>
            <div class="input-group">
              <input type="password" name="password" id="newStaffPassword" class="form-control rounded-start-3" placeholder="Temporary login password" minlength="6" required>
              <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility('newStaffPassword', this)" title="Show/Hide Password">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <div class="form-text small">Password will be cryptographically hashed using Argon2id / Bcrypt.</div>
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
            <i class="bi bi-shield-check me-1"></i> Provision Account
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =========================================================================
     MODAL 2: MODIFY USER ROLE (RBAC REASSIGNMENT)
     ========================================================================= -->
<div class="modal fade" id="changeRoleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold font-outfit">
          <i class="bi bi-shield-shaded me-2 text-primary"></i>Modify Role Privileges
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" action="<?= ADMIN_URL ?>/users.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_role">
        <input type="hidden" name="user_id" id="modalTargetUserId" value="">

        <div class="modal-body p-4">
          <div class="alert alert-light border rounded-3 p-3 mb-3">
            <div class="small text-muted">Selected User Account:</div>
            <div class="fw-bold text-dark fs-6" id="modalTargetUserName">-</div>
            <div class="small mt-1">Current Role: <span id="modalCurrentRoleBadge" class="badge bg-secondary">-</span></div>
          </div>

          <!-- Self-Modification Warning Banner -->
          <div id="modalSelfDemotionAlert" class="alert alert-danger small d-none mb-3">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <strong>Security Restriction:</strong> You are currently logged in as this administrator. Modifying your own role is locked to prevent administrative lockout.
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Select New Role *</label>
            <select name="role_id" id="modalRoleSelect" class="form-select rounded-3" required onchange="handleRoleSelectionChange(this)">
              <?php foreach ($roles as $r): ?>
                <option value="<?= (int)$r['role_id'] ?>" data-role-name="<?= e($r['role_name']) ?>">
                  Role <?= (int)$r['role_id'] ?>: <?= e($r['role_name']) ?> (<?= e($r['description']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- High-Privilege Notice when elevating to Admin -->
          <div id="modalAdminPromotionNotice" class="alert alert-warning small d-none mb-2">
            <i class="bi bi-shield-lock-fill me-1 text-danger"></i>
            <strong>Executive Elevation:</strong> Assigning Role 1 (ADMIN) grants full write access to financial logs, user permissions, and dish inventory.
          </div>

          <div class="small text-muted border-top pt-2">
            <i class="bi bi-shield-exclamation text-warning me-1"></i>
            <strong>Security Safeguards:</strong> Self-demotion and demoting the last active administrator are automatically guarded.
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" id="modalSubmitRoleBtn" class="btn btn-primary rounded-pill px-4 fw-bold">
            <i class="bi bi-check2-circle me-1"></i> Update Role
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =========================================================================
     MODAL 3: EDIT USER CONTACT PROFILE
     ========================================================================= -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold font-outfit">
          <i class="bi bi-pencil-square me-2 text-secondary"></i>Edit Contact Information
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" action="<?= ADMIN_URL ?>/users.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit_user">
        <input type="hidden" name="user_id" id="editModalUserId" value="">

        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-bold">Full Name *</label>
            <input type="text" name="full_name" id="editModalFullName" class="form-control rounded-3" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Email Address *</label>
            <input type="email" name="email" id="editModalEmail" class="form-control rounded-3" required>
            <div class="form-text small">Used for portal authentication and transactional communications.</div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Phone Number *</label>
            <input type="tel" name="phone_number" id="editModalPhone" class="form-control rounded-3" required>
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-dark rounded-pill px-4 fw-bold">
            <i class="bi bi-save me-1"></i> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =========================================================================
     MODAL 4: RESET USER PASSWORD
     ========================================================================= -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold font-outfit">
          <i class="bi bi-key-fill me-2 text-warning"></i>Reset User Password
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" action="<?= ADMIN_URL ?>/users.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="user_id" id="resetModalUserId" value="">

        <div class="modal-body p-4">
          <div class="alert alert-light border rounded-3 p-3 mb-3">
            <div class="small text-muted">User Account:</div>
            <div class="fw-bold text-dark fs-6" id="resetModalUserName">-</div>
          </div>

          <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between mb-1">
              <label class="form-label small fw-bold mb-0">New Temporary Password *</label>
              <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small text-warning fw-bold" onclick="generateStaffPassword('resetModalPassword')">
                <i class="bi bi-magic me-1"></i>Generate Random
              </button>
            </div>
            <div class="input-group">
              <input type="password" name="new_password" id="resetModalPassword" class="form-control rounded-start-3" placeholder="Enter new password" minlength="6" required>
              <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility('resetModalPassword', this)" title="Show/Hide Password">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <div class="form-text small">Minimum 6 characters. Advise the user to change password after initial sign-in.</div>
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning text-dark rounded-pill px-4 fw-bold">
            <i class="bi bi-check2-circle me-1"></i> Reset Password
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =========================================================================
     MODAL 5: CONFIRM SUSPENSION / REACTIVATION (SAFE TOGGLE)
     ========================================================================= -->
<div class="modal fade" id="toggleActiveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold font-outfit" id="toggleModalTitle">Confirm Account Status Change</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" action="<?= ADMIN_URL ?>/users.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_active">
        <input type="hidden" name="user_id" id="toggleModalUserId" value="">
        <input type="hidden" name="is_active" id="toggleModalNewStatus" value="">

        <div class="modal-body p-4">
          <div class="alert alert-light border rounded-3 p-3 mb-3">
            <div class="small text-muted">Target Account:</div>
            <div class="fw-bold text-dark fs-6" id="toggleModalUserName">-</div>
          </div>

          <div id="toggleModalMessage" class="alert p-3 mb-0">
            <!-- Dynamic Alert Body -->
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" id="toggleModalSubmitBtn" class="btn rounded-pill px-4 fw-bold">
            Confirm
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =========================================================================
     OFFCANVAS: USER DETAILS & ACTIVITY INSPECTION
     ========================================================================= -->
<div class="offcanvas offcanvas-end rounded-start-4 border-0 shadow-lg" tabindex="-1" id="userDetailsOffcanvas" style="width: 420px;">
  <div class="offcanvas-header border-bottom p-4">
    <h5 class="offcanvas-title fw-bold font-outfit" id="userDetailsOffcanvasLabel">
      <i class="bi bi-person-badge text-primary me-2"></i>User Profile & Activity
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>

  <div class="offcanvas-body p-4" id="userDetailsContent">
    <div class="text-center py-5">
      <div class="spinner-border text-forest" role="status"></div>
      <div class="text-muted small mt-2">Loading user activity and profile...</div>
    </div>
  </div>
</div>

<!-- =========================================================================
     CLIENT-SIDE INTERACTIVE JAVASCRIPT
     ========================================================================= -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  
  // 1. Change Role Modal Setup
  const roleButtons = document.querySelectorAll('.change-role-btn');
  const targetUserId = document.getElementById('modalTargetUserId');
  const targetUserName = document.getElementById('modalTargetUserName');
  const currentRoleBadge = document.getElementById('modalCurrentRoleBadge');
  const roleSelect = document.getElementById('modalRoleSelect');
  const selfAlert = document.getElementById('modalSelfDemotionAlert');
  const submitRoleBtn = document.getElementById('modalSubmitRoleBtn');

  roleButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      const uid = this.getAttribute('data-user-id');
      const name = this.getAttribute('data-user-name');
      const rid = this.getAttribute('data-current-role-id');
      const rname = this.getAttribute('data-current-role-name');
      const isSelf = this.getAttribute('data-is-self') === '1';

      targetUserId.value = uid;
      targetUserName.textContent = name + ' (#USR-' + uid + ')';
      currentRoleBadge.textContent = rname;
      roleSelect.value = rid;

      // Color current role badge
      currentRoleBadge.className = 'badge ' + (
        rname === 'ADMIN' ? 'bg-danger' : 
        rname === 'KITCHEN' ? 'bg-warning text-dark' : 
        rname === 'RIDER' ? 'bg-info text-dark' : 'bg-success'
      );

      // Handle Self-demotion safeguards in UI
      if (isSelf) {
        selfAlert.classList.remove('d-none');
        roleSelect.disabled = true;
        submitRoleBtn.disabled = true;
      } else {
        selfAlert.classList.add('d-none');
        roleSelect.disabled = false;
        submitRoleBtn.disabled = false;
      }

      handleRoleSelectionChange(roleSelect);
    });
  });

  // 2. Edit Profile Modal Setup
  const editButtons = document.querySelectorAll('.edit-user-btn');
  const editModalUserId = document.getElementById('editModalUserId');
  const editModalFullName = document.getElementById('editModalFullName');
  const editModalEmail = document.getElementById('editModalEmail');
  const editModalPhone = document.getElementById('editModalPhone');

  editButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      editModalUserId.value = this.getAttribute('data-user-id');
      editModalFullName.value = this.getAttribute('data-full-name');
      editModalEmail.value = this.getAttribute('data-email');
      editModalPhone.value = this.getAttribute('data-phone');
    });
  });

  // 3. Reset Password Modal Setup
  const resetButtons = document.querySelectorAll('.reset-pass-btn');
  const resetModalUserId = document.getElementById('resetModalUserId');
  const resetModalUserName = document.getElementById('resetModalUserName');
  const resetModalPassword = document.getElementById('resetModalPassword');

  resetButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      resetModalUserId.value = this.getAttribute('data-user-id');
      resetModalUserName.textContent = this.getAttribute('data-user-name') + ' (#USR-' + this.getAttribute('data-user-id') + ')';
      resetModalPassword.value = '';
    });
  });

  // 4. Safe Suspension / Reactivation Confirmation Modal Setup
  const toggleButtons = document.querySelectorAll('.toggle-active-btn');
  const toggleModalTitle = document.getElementById('toggleModalTitle');
  const toggleModalUserId = document.getElementById('toggleModalUserId');
  const toggleModalNewStatus = document.getElementById('toggleModalNewStatus');
  const toggleModalUserName = document.getElementById('toggleModalUserName');
  const toggleModalMessage = document.getElementById('toggleModalMessage');
  const toggleModalSubmitBtn = document.getElementById('toggleModalSubmitBtn');

  toggleButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      const uid = this.getAttribute('data-user-id');
      const name = this.getAttribute('data-user-name');
      const isActive = parseInt(this.getAttribute('data-is-active'));
      const isSelf = this.getAttribute('data-is-self') === '1';

      toggleModalUserId.value = uid;
      toggleModalUserName.textContent = name + ' (#USR-' + uid + ')';
      toggleModalNewStatus.value = isActive ? '0' : '1';

      if (isSelf && isActive) {
        toggleModalTitle.textContent = 'Action Restricted';
        toggleModalMessage.className = 'alert alert-danger';
        toggleModalMessage.innerHTML = '<i class="bi bi-shield-lock-fill me-2"></i><strong>Security Restriction:</strong> You cannot deactivate your own active Administrator account to prevent system lockout.';
        toggleModalSubmitBtn.disabled = true;
        toggleModalSubmitBtn.className = 'btn btn-secondary rounded-pill px-4 fw-bold';
        toggleModalSubmitBtn.textContent = 'Locked';
      } else if (isActive) {
        toggleModalTitle.textContent = 'Suspend User Account';
        toggleModalMessage.className = 'alert alert-danger bg-danger bg-opacity-10 text-danger border-0';
        toggleModalMessage.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Caution:</strong> This user will be immediately logged out and blocked from signing in to the platform.';
        toggleModalSubmitBtn.disabled = false;
        toggleModalSubmitBtn.className = 'btn btn-danger rounded-pill px-4 fw-bold';
        toggleModalSubmitBtn.textContent = 'Confirm Suspension';
      } else {
        toggleModalTitle.textContent = 'Reactivate User Account';
        toggleModalMessage.className = 'alert alert-success bg-success bg-opacity-10 text-success border-0';
        toggleModalMessage.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>This will restore access and allow the user to log in and access authorized features.';
        toggleModalSubmitBtn.disabled = false;
        toggleModalSubmitBtn.className = 'btn btn-success rounded-pill px-4 fw-bold';
        toggleModalSubmitBtn.textContent = 'Confirm Reactivation';
      }
    });
  });

  // 5. User Details Offcanvas Fetch
  const detailButtons = document.querySelectorAll('.view-details-btn');
  const offcanvasBody = document.getElementById('userDetailsContent');

  detailButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      const uid = this.getAttribute('data-user-id');
      offcanvasBody.innerHTML = `
        <div class="text-center py-5">
          <div class="spinner-border text-success" role="status"></div>
          <div class="text-muted small mt-2">Loading user activity and profile...</div>
        </div>
      `;

      fetch('<?= ADMIN_URL ?>/users.php?action=get_user_details&user_id=' + uid)
        .then(res => res.json())
        .then(response => {
          if (!response.success) {
            offcanvasBody.innerHTML = `<div class="alert alert-danger">${response.message || 'Error loading profile'}</div>`;
            return;
          }

          const u = response.data.user;
          const stats = response.data.stats;
          
          let statsHtml = '';
          if (stats.type === 'customer') {
            let recentOrdersList = '';
            if (stats.recent_orders && stats.recent_orders.length > 0) {
              recentOrdersList = stats.recent_orders.map(o => `
                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                  <div>
                    <div class="fw-bold font-monospace text-dark">${o.order_number || ('#ORD-' + o.order_id)}</div>
                    <div class="text-muted" style="font-size:0.75rem;">${new Date(o.placed_at).toLocaleDateString()}</div>
                  </div>
                  <div class="text-end">
                    <div class="fw-bold text-dark">₹${parseFloat(o.total_amount).toFixed(2)}</div>
                    <span class="badge bg-light text-dark border" style="font-size:0.65rem;">${o.order_status}</span>
                  </div>
                </li>
              `).join('');
            } else {
              recentOrdersList = '<li class="list-group-item px-0 text-muted small">No order history available.</li>';
            }

            statsHtml = `
              <div class="row g-2 mb-3">
                <div class="col-6">
                  <div class="p-3 bg-light rounded-3 text-center border">
                    <div class="text-muted small">Total Orders</div>
                    <div class="h4 fw-bold text-dark mb-0 font-outfit">${stats.total_orders}</div>
                  </div>
                </div>
                <div class="col-6">
                  <div class="p-3 bg-light rounded-3 text-center border">
                    <div class="text-muted small">Total Spend</div>
                    <div class="h4 fw-bold text-success mb-0 font-outfit">${stats.total_spend}</div>
                  </div>
                </div>
              </div>
              <div class="mb-3">
                <span class="text-muted small"><i class="bi bi-geo-alt me-1"></i>Saved Delivery Addresses: <strong>${stats.addresses_count}</strong></span>
              </div>
              <div class="fw-bold text-dark small mb-2">Recent Order History</div>
              <ul class="list-group list-group-flush mb-3">${recentOrdersList}</ul>
            `;
          } else if (stats.type === 'rider') {
            statsHtml = `
              <div class="row g-2 mb-3">
                <div class="col-6">
                  <div class="p-3 bg-light rounded-3 text-center border">
                    <div class="text-muted small">Completed</div>
                    <div class="h4 fw-bold text-success mb-0 font-outfit">${stats.completed_deliveries}</div>
                  </div>
                </div>
                <div class="col-6">
                  <div class="p-3 bg-light rounded-3 text-center border">
                    <div class="text-muted small">Active Shifts</div>
                    <div class="h4 fw-bold text-info mb-0 font-outfit">${stats.active_dispatches}</div>
                  </div>
                </div>
              </div>
            `;
          } else {
            statsHtml = `
              <div class="p-3 bg-light rounded-3 mb-3 border">
                <div class="small fw-bold text-dark mb-1"><i class="bi bi-shield-check text-success me-1"></i>Operational Privileges</div>
                <p class="text-muted small mb-0">${u.role_desc || 'Authorized internal personnel privileges.'}</p>
              </div>
            `;
          }

          offcanvasBody.innerHTML = `
            <div class="text-center mb-4">
              <div class="rounded-circle d-inline-flex align-items-center justify-content-center fw-bold shadow-sm mb-2 bg-dark text-white" 
                   style="width: 58px; height: 58px; font-size: 1.25rem;">
                ${u.full_name.charAt(0).toUpperCase()}
              </div>
              <h5 class="fw-bold text-dark mb-1">${u.full_name}</h5>
              <div class="mb-2">${u.role_badge}</div>
              <span class="badge ${u.is_active ? 'bg-success' : 'bg-secondary'} rounded-pill px-3 py-1">
                ${u.is_active ? 'Active Account' : 'Suspended Account'}
              </span>
            </div>

            <div class="card border-0 bg-light rounded-3 p-3 mb-3">
              <div class="d-flex justify-content-between py-1 border-bottom small">
                <span class="text-muted">User ID:</span>
                <span class="font-monospace fw-bold">#USR-${u.id}</span>
              </div>
              <div class="d-flex justify-content-between py-1 border-bottom small">
                <span class="text-muted">Email:</span>
                <span class="fw-semibold text-truncate" style="max-width:200px;">${u.email}</span>
              </div>
              <div class="d-flex justify-content-between py-1 border-bottom small">
                <span class="text-muted">Phone:</span>
                <span class="fw-semibold">${u.phone_number}</span>
              </div>
              <div class="d-flex justify-content-between py-1 small">
                <span class="text-muted">Registered:</span>
                <span>${u.created_at}</span>
              </div>
            </div>

            ${statsHtml}
          `;
        })
        .catch(err => {
          offcanvasBody.innerHTML = '<div class="alert alert-danger">Network error loading user details.</div>';
        });
    });
  });

});

// Helper: Role selection alert display
function handleRoleSelectionChange(selectElem) {
  const notice = document.getElementById('modalAdminPromotionNotice');
  if (parseInt(selectElem.value) === 1) {
    notice.classList.remove('d-none');
  } else {
    notice.classList.add('d-none');
  }
}

// Helper: Password visibility toggle
function togglePasswordVisibility(inputId, btn) {
  const input = document.getElementById(inputId);
  const icon = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
}

// Helper: Random password generator
function generateStaffPassword(targetInputId) {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$';
  let pass = '';
  for (let i = 0; i < 9; i++) {
    pass += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  const input = document.getElementById(targetInputId);
  input.value = pass;
  input.type = 'text'; // Make visible so admin can record it
}
</script>

<style>
.transition-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.transition-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 .5rem 1rem rgba(0,0,0,.08) !important;
}
.shadow-xs {
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
