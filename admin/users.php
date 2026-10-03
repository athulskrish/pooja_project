<?php
/**
 * User & Staff Role-Based Access Control (RBAC) Management
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'User & Staff RBAC';
$activePage = 'users';

// Bootstrap core libraries before HTML output (prevents "headers already sent")
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
$currentUser = current_user();

// Handle Add Staff / Toggle Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
    } elseif ($_POST['action'] === 'add_staff') {
        $name = clean_input($_POST['full_name']);
        $email = clean_input(strtolower($_POST['email']));
        $phone = clean_input($_POST['phone_number']);
        $roleId = (int)$_POST['role_id'];
        $pass = $_POST['password'];

        if (!empty($name) && !empty($email) && !empty($pass)) {
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
            set_flash('success', "Staff member '{$name}' created with role privileges.");
            header("Location: " . ADMIN_URL . "/users.php");
            exit;
        }
    } elseif ($_POST['action'] === 'toggle_active') {
        $uid = (int)$_POST['user_id'];
        $newStatus = (int)$_POST['is_active'];
        db_query("UPDATE `users` SET `is_active` = :act WHERE `user_id` = :id", [
            ':act' => $newStatus,
            ':id' => $uid
        ]);
        set_flash('success', "User account status updated.");
        header("Location: " . ADMIN_URL . "/users.php");
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';

$roleFilter = clean_input($_GET['role'] ?? '');
$where = "1=1";
$params = [];
if (!empty($roleFilter)) {
    $where .= " AND r.role_name = :rname";
    $params[':rname'] = $roleFilter;
}

$users = db_fetch_all("
    SELECT u.*, r.role_name, r.description AS role_desc
    FROM `users` u
    JOIN `roles` r ON u.role_id = r.role_id
    WHERE $where
    ORDER BY u.role_id ASC, u.user_id ASC
", $params);

$roles = db_fetch_all("SELECT * FROM `roles` ORDER BY `role_id` ASC");
?>

<div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
  
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-3 mb-4">
    <div>
      <h4 class="fw-bold font-outfit mb-1 text-dark">Staff & Customer RBAC Directory</h4>
      <p class="text-muted small mb-0">Role privileges secured across <code>roles</code> and <code>users</code></p>
    </div>

    <div class="d-flex align-items-center gap-2">
      <!-- Role Filters -->
      <a href="<?= ADMIN_URL ?>/users.php" class="btn btn-sm rounded-pill <?= empty($roleFilter) ? 'btn-dark' : 'btn-light border' ?>">All</a>
      <a href="<?= ADMIN_URL ?>/users.php?role=ADMIN" class="btn btn-sm rounded-pill <?= ($roleFilter === 'ADMIN') ? 'btn-danger' : 'btn-light border' ?>">Admin</a>
      <a href="<?= ADMIN_URL ?>/users.php?role=KITCHEN" class="btn btn-sm rounded-pill <?= ($roleFilter === 'KITCHEN') ? 'btn-warning text-dark' : 'btn-light border' ?>">Kitchen</a>
      <a href="<?= ADMIN_URL ?>/users.php?role=RIDER" class="btn btn-sm rounded-pill <?= ($roleFilter === 'RIDER') ? 'btn-info text-dark' : 'btn-light border' ?>">Rider</a>
      <a href="<?= ADMIN_URL ?>/users.php?role=CUSTOMER" class="btn btn-sm rounded-pill <?= ($roleFilter === 'CUSTOMER') ? 'btn-success' : 'btn-light border' ?>">Customer</a>

      <button type="button" class="btn btn-success btn-sm rounded-pill px-3 fw-bold ms-2" data-bs-toggle="modal" data-bs-target="#addStaffModal" style="background:#2E6F40; border-color:#2E6F40;">
        <i class="bi bi-person-plus-fill me-1"></i> Add Staff User
      </button>
    </div>
  </div>

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
          <th class="text-end">Account Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td class="font-monospace fw-bold">#USR-<?= (int)$u['user_id'] ?></td>
            <td>
              <div class="fw-bold text-dark"><?= e($u['full_name']) ?></div>
            </td>
            <td>
              <div><?= e($u['email']) ?></div>
              <div class="small text-muted"><?= e($u['phone_number']) ?></div>
            </td>
            <td>
              <?php if ($u['role_name'] === 'ADMIN'): ?>
                <span class="badge bg-danger">ADMIN</span>
              <?php elseif ($u['role_name'] === 'KITCHEN'): ?>
                <span class="badge bg-warning text-dark">KITCHEN CHEF</span>
              <?php elseif ($u['role_name'] === 'RIDER'): ?>
                <span class="badge bg-info text-dark">DELIVERY RIDER</span>
              <?php else: ?>
                <span class="badge bg-success bg-opacity-25 text-success">CUSTOMER</span>
              <?php endif; ?>
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
              <form method="POST" action="<?= ADMIN_URL ?>/users.php" class="d-inline m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                <input type="hidden" name="is_active" value="<?= $u['is_active'] ? 0 : 1 ?>">
                
                <?php if ($u['is_active']): ?>
                  <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3" <?= ($u['user_id'] == $currentUser['id']) ? 'disabled' : '' ?>>
                    Deactivate
                  </button>
                <?php else: ?>
                  <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-3">
                    Reactivate
                  </button>
                <?php endif; ?>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<!-- Add Staff Modal -->
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
            <label class="form-label small fw-bold">Staff Role *</label>
            <select name="role_id" class="form-select rounded-3">
              <option value="3">KITCHEN (Chef / Cook KDS access)</option>
              <option value="4">RIDER (Courier Delivery Partner)</option>
              <option value="1">ADMIN (Operations Administrator)</option>
            </select>
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
            <div class="form-text small">Encrypted with PHP native Argon2id / standard cryptographic hashing.</div>
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">Create Staff User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
