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
 * - Activate/Suspend User Accounts with anti-lockout safeguards
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

// Handle RBAC Form Actions: Add Staff / Toggle Status / Update Role
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

        if (empty($name) || empty($email) || empty($pass)) {
            set_flash('danger', 'Please provide name, email, and password for new staff.');
            header("Location: " . ADMIN_URL . "/users.php");
            exit;
        }

        // Validate role exists
        $roleInfo = db_fetch_one("SELECT * FROM `roles` WHERE `role_id` = :rid", [':rid' => $roleId]);
        if (!$roleInfo) {
            set_flash('danger', 'Invalid staff role specified.');
            header("Location: " . ADMIN_URL . "/users.php");
            exit;
        }

        // Check duplicate email
        $existing = db_fetch_val("SELECT COUNT(*) FROM `users` WHERE LOWER(email) = :email", [':email' => $email]);
        if ($existing > 0) {
            set_flash('danger', "An account with email '{$email}' already exists.");
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
            ':phone' => $phone ?: '+91 99000 00000',
            ':hash' => $hash
        ]);

        set_flash('success', "New staff member '{$name}' created with role privileges: {$roleInfo['role_name']}.");
        header("Location: " . ADMIN_URL . "/users.php");
        exit;
    } 
    
    // 2. Toggle User Account Status (Active / Suspended)
    elseif ($_POST['action'] === 'toggle_active') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $newStatus = (int)($_POST['is_active'] ?? 0);

        // Guard: Prevent self-deactivation by active administrator to prevent system lockout (UC-20)
        if ($uid === (int)($currentUser['id'] ?? 0) && $newStatus === 0) {
            set_flash('danger', 'Security Restriction: Self-deactivation by the active administrator is prohibited to prevent lockout.');
            header("Location: " . ADMIN_URL . "/users.php");
            exit;
        }

        db_query("UPDATE `users` SET `is_active` = :act WHERE `user_id` = :id", [
            ':act' => $newStatus,
            ':id' => $uid
        ]);
        set_flash('success', "User account status updated successfully.");
        header("Location: " . ADMIN_URL . "/users.php");
        exit;
    }

    // 3. Update Existing User Role (RBAC Privilege Reassignment)
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

// Role Filtering & Query
$roleFilter = clean_input($_GET['role'] ?? '');
$searchQuery = clean_input($_GET['q'] ?? '');
$activeTab = clean_input($_GET['tab'] ?? 'directory');

$where = "1=1";
$params = [];
if (!empty($roleFilter)) {
    $where .= " AND r.role_name = :rname";
    $params[':rname'] = strtoupper($roleFilter);
}
if (!empty($searchQuery)) {
    $where .= " AND (u.full_name LIKE :q OR u.email LIKE :q OR u.phone_number LIKE :q)";
    $params[':q'] = "%{$searchQuery}%";
}

$users = db_fetch_all("
    SELECT u.*, r.role_name, r.description AS role_desc
    FROM `users` u
    JOIN `roles` r ON u.role_id = r.role_id
    WHERE $where
    ORDER BY u.role_id ASC, u.user_id ASC
", $params);

$roles = db_fetch_all("SELECT * FROM `roles` ORDER BY `role_id` ASC");

// Role counts for tabs & metrics
$totalUsersCount = (int)db_fetch_val("SELECT COUNT(*) FROM `users`") ?: 0;
$adminCount = (int)db_fetch_val("SELECT COUNT(*) FROM `users` WHERE `role_id` = 1") ?: 0;
$kitchenCount = (int)db_fetch_val("SELECT COUNT(*) FROM `users` WHERE `role_id` = 3") ?: 0;
$riderCount = (int)db_fetch_val("SELECT COUNT(*) FROM `users` WHERE `role_id` = 4") ?: 0;
$customerCount = (int)db_fetch_val("SELECT COUNT(*) FROM `users` WHERE `role_id` = 2") ?: 0;
?>

<div class="row g-4 mb-4">
  <div class="col-md-3">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 text-danger bg-danger bg-opacity-10 fs-4">
          <i class="bi bi-shield-lock-fill"></i>
        </div>
        <div>
          <div class="text-muted small fw-semibold">Administrators</div>
          <div class="h4 fw-bold text-dark mb-0 font-outfit"><?= $adminCount ?></div>
          <span class="badge bg-danger bg-opacity-25 text-danger small">Role 1: Full Access</span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 text-warning bg-warning bg-opacity-10 fs-4">
          <i class="fa-solid fa-fire-burner"></i>
        </div>
        <div>
          <div class="text-muted small fw-semibold">Kitchen Chefs</div>
          <div class="h4 fw-bold text-dark mb-0 font-outfit"><?= $kitchenCount ?></div>
          <span class="badge bg-warning bg-opacity-25 text-dark small">Role 3: KDS & Prep</span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 text-info bg-info bg-opacity-10 fs-4">
          <i class="bi bi-bicycle"></i>
        </div>
        <div>
          <div class="text-muted small fw-semibold">Delivery Fleet</div>
          <div class="h4 fw-bold text-dark mb-0 font-outfit"><?= $riderCount ?></div>
          <span class="badge bg-info bg-opacity-25 text-dark small">Role 4: Logistics & OTP</span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 text-success bg-success bg-opacity-10 fs-4">
          <i class="bi bi-person-fill"></i>
        </div>
        <div>
          <div class="text-muted small fw-semibold">Registered Customers</div>
          <div class="h4 fw-bold text-dark mb-0 font-outfit"><?= $customerCount ?></div>
          <span class="badge bg-success bg-opacity-25 text-success small">Role 2: Storefront</span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 rounded-4 shadow-sm bg-white mb-4">
  
  <!-- Navigation Tabs: Directory vs Permissions Matrix -->
  <div class="card-header bg-white border-bottom p-4 pb-0">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
      <div>
        <h4 class="fw-bold font-outfit mb-1 text-dark">Role-Based Access Control (RBAC) Management</h4>
        <p class="text-muted small mb-0">Manage actor privileges, role assignments, and system security controls</p>
      </div>

      <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-success btn-sm rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#addStaffModal" style="background:#2E6F40; border-color:#2E6F40;">
          <i class="bi bi-person-plus-fill me-1"></i> Provision Staff Account
        </button>
      </div>
    </div>

    <ul class="nav nav-tabs border-bottom-0 gap-2">
      <li class="nav-item">
        <a class="nav-link fw-semibold rounded-top-3 px-4 <?= ($activeTab !== 'matrix') ? 'active bg-light border-bottom-0' : 'text-muted' ?>" href="<?= ADMIN_URL ?>/users.php?tab=directory">
          <i class="bi bi-people me-2"></i>Staff & User Directory (<?= $totalUsersCount ?>)
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link fw-semibold rounded-top-3 px-4 <?= ($activeTab === 'matrix') ? 'active bg-light border-bottom-0' : 'text-muted' ?>" href="<?= ADMIN_URL ?>/users.php?tab=matrix">
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
        <h5 class="fw-bold font-outfit mb-1 text-dark">System Capability & Access Control Grid</h5>
        <p class="text-muted small mb-3">
          Granular permission claims enforced across session middleware, HTTP verbs, and operational routes.
        </p>

        <div class="table-responsive">
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
                <td><strong>Executive BI Analytics & SES Forecasts</strong><br><span class="text-muted small">Chart.js visualizations and demand modeling</span></td>
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
        <div class="d-flex flex-wrap align-items-center gap-2">
          <!-- Role Filter Pills -->
          <a href="<?= ADMIN_URL ?>/users.php?tab=directory" class="btn btn-sm rounded-pill <?= empty($roleFilter) ? 'btn-dark' : 'btn-light border' ?>">
            All (<?= $totalUsersCount ?>)
          </a>
          <a href="<?= ADMIN_URL ?>/users.php?role=ADMIN&tab=directory" class="btn btn-sm rounded-pill <?= ($roleFilter === 'ADMIN') ? 'btn-danger' : 'btn-light border' ?>">
            <i class="bi bi-shield-lock-fill me-1"></i>Admin (<?= $adminCount ?>)
          </a>
          <a href="<?= ADMIN_URL ?>/users.php?role=KITCHEN&tab=directory" class="btn btn-sm rounded-pill <?= ($roleFilter === 'KITCHEN') ? 'btn-warning text-dark' : 'btn-light border' ?>">
            <i class="fa-solid fa-fire-burner me-1"></i>Kitchen (<?= $kitchenCount ?>)
          </a>
          <a href="<?= ADMIN_URL ?>/users.php?role=RIDER&tab=directory" class="btn btn-sm rounded-pill <?= ($roleFilter === 'RIDER') ? 'btn-info text-dark' : 'btn-light border' ?>">
            <i class="bi bi-bicycle me-1"></i>Rider (<?= $riderCount ?>)
          </a>
          <a href="<?= ADMIN_URL ?>/users.php?role=CUSTOMER&tab=directory" class="btn btn-sm rounded-pill <?= ($roleFilter === 'CUSTOMER') ? 'btn-success' : 'btn-light border' ?>">
            <i class="bi bi-person me-1"></i>Customer (<?= $customerCount ?>)
          </a>
        </div>

        <form method="GET" action="<?= ADMIN_URL ?>/users.php" class="d-flex align-items-center gap-2">
          <input type="hidden" name="tab" value="directory">
          <?php if (!empty($roleFilter)): ?>
            <input type="hidden" name="role" value="<?= e($roleFilter) ?>">
          <?php endif; ?>
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" name="q" class="form-control bg-light border-start-0" placeholder="Search user, email, phone..." value="<?= e($searchQuery) ?>" style="min-width: 220px;">
          </div>
          <button type="submit" class="btn btn-sm btn-dark rounded-pill px-3">Filter</button>
          <?php if (!empty($searchQuery) || !empty($roleFilter)): ?>
            <a href="<?= ADMIN_URL ?>/users.php?tab=directory" class="btn btn-sm btn-light border rounded-pill px-2" title="Clear Filters"><i class="bi bi-x-lg"></i></a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Users Table -->
      <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0">
          <thead class="table-light">
            <tr>
              <th>User ID</th>
              <th>Full Name</th>
              <th>Contact & Email</th>
              <th>Assigned Role</th>
              <th>Status</th>
              <th>Registered</th>
              <th class="text-end">RBAC Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                  <i class="bi bi-people fs-2 d-block mb-2 text-muted"></i>
                  No users found matching current filter criteria.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td class="font-monospace fw-bold">#USR-<?= (int)$u['user_id'] ?></td>
                  <td>
                    <div class="fw-bold text-dark"><?= e($u['full_name']) ?></div>
                    <?php if ((int)$u['user_id'] === (int)$currentUser['id']): ?>
                      <span class="badge bg-primary bg-opacity-25 text-primary small px-2 py-0">You (Current Admin)</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div><?= e($u['email']) ?></div>
                    <div class="small text-muted"><?= e($u['phone_number']) ?></div>
                  </td>
                  <td>
                    <?= get_role_badge($u['role_name']) ?>
                  </td>
                  <td>
                    <?php if ($u['is_active']): ?>
                      <span class="badge bg-success px-2 py-1 rounded-pill">Active</span>
                    <?php else: ?>
                      <span class="badge bg-secondary px-2 py-1 rounded-pill">Suspended</span>
                    <?php endif; ?>
                  </td>
                  <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                  <td class="text-end">
                    <div class="d-inline-flex align-items-center gap-2">
                      <!-- Change Role Button -->
                      <button type="button" 
                              class="btn btn-sm btn-outline-primary rounded-pill px-3 change-role-btn" 
                              data-bs-toggle="modal" 
                              data-bs-target="#changeRoleModal"
                              data-user-id="<?= (int)$u['user_id'] ?>"
                              data-user-name="<?= e($u['full_name']) ?>"
                              data-current-role-id="<?= (int)$u['role_id'] ?>"
                              data-current-role-name="<?= e($u['role_name']) ?>">
                        <i class="bi bi-shield-shaded me-1"></i> Change Role
                      </button>

                      <!-- Toggle Active Form -->
                      <form method="POST" action="<?= ADMIN_URL ?>/users.php" class="d-inline m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                        <input type="hidden" name="is_active" value="<?= $u['is_active'] ? 0 : 1 ?>">
                        
                        <?php if ($u['is_active']): ?>
                          <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3" <?= ($u['user_id'] == $currentUser['id']) ? 'disabled title="Cannot suspend own account"' : '' ?>>
                            Deactivate
                          </button>
                        <?php else: ?>
                          <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-3">
                            Reactivate
                          </button>
                        <?php endif; ?>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- =========================================================================
     MODAL 1: PROVISION NEW STAFF ACCOUNT
     ========================================================================= -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold">Provision New Staff Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" action="<?= ADMIN_URL ?>/users.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_staff">

        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-bold">Staff Role & Authorization Level *</label>
            <select name="role_id" class="form-select rounded-3" required>
              <option value="3">Role 3: KITCHEN (Chef / Cook KDS access)</option>
              <option value="4">Role 4: RIDER (Courier Delivery Partner)</option>
              <option value="1">Role 1: ADMIN (Operations Administrator)</option>
            </select>
            <div class="form-text small">Each role grants isolated portal routing and permissions.</div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Staff Full Name *</label>
            <input type="text" name="full_name" class="form-control rounded-3" placeholder="Sanjeev Kapoor" required>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Official Email *</label>
              <input type="email" name="email" class="form-control rounded-3" placeholder="chef2@zippy.com" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Phone Number *</label>
              <input type="tel" name="phone_number" class="form-control rounded-3" placeholder="+91 98765 00000" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Password *</label>
            <input type="password" name="password" class="form-control rounded-3" placeholder="Temporary login password" minlength="6" required>
            <div class="form-text small">Securely salted with Argon2id / Bcrypt cryptographic hash.</div>
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
            <i class="bi bi-shield-check me-1"></i> Provision Staff Account
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =========================================================================
     MODAL 2: MODIFY USER ROLE (RBAC ASSIGNMENT)
     ========================================================================= -->
<div class="modal fade" id="changeRoleModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold"><i class="bi bi-shield-shaded me-2 text-primary"></i>Modify Role Privileges</h5>
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

          <div class="mb-3">
            <label class="form-label small fw-bold">Select New Role *</label>
            <select name="role_id" id="modalRoleSelect" class="form-select rounded-3" required>
              <option value="1">Role 1: ADMIN (Operations Administrator - Full Control)</option>
              <option value="2">Role 2: CUSTOMER (Storefront End-User / Checkout)</option>
              <option value="3">Role 3: KITCHEN (Chef / KDS Kitchen Station)</option>
              <option value="4">Role 4: RIDER (Delivery Partner / Fleet Logistics)</option>
            </select>
          </div>

          <div class="small text-muted border-top pt-2">
            <i class="bi bi-shield-exclamation text-warning me-1"></i>
            <strong>Security Safeguards:</strong> Self-demotion by the active administrator and demoting the last active admin are automatically blocked to prevent lockout.
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
            <i class="bi bi-check2-circle me-1"></i> Update User Role
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const roleButtons = document.querySelectorAll('.change-role-btn');
  const targetUserId = document.getElementById('modalTargetUserId');
  const targetUserName = document.getElementById('modalTargetUserName');
  const currentRoleBadge = document.getElementById('modalCurrentRoleBadge');
  const roleSelect = document.getElementById('modalRoleSelect');

  roleButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      const uid = this.getAttribute('data-user-id');
      const name = this.getAttribute('data-user-name');
      const rid = this.getAttribute('data-current-role-id');
      const rname = this.getAttribute('data-current-role-name');

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
    });
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
