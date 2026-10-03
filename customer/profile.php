<?php
/**
 * Customer Profile & Account Management
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'My Profile & Account';
$activePage = 'profile';

// Bootstrap core libraries before HTML output (prevents "headers already sent")
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
$currentUser = current_user();
require_login();

$userId = $currentUser['id'];
$userRecord = db_fetch_one("SELECT * FROM `users` WHERE `user_id` = :id", [':id' => $userId]);

// Stats
$orderStats = db_fetch_one("
    SELECT COUNT(*) AS total_orders, COALESCE(SUM(total_amount), 0) AS total_spent 
    FROM `orders` 
    WHERE `user_id` = :id
", [':id' => $userId]);

$savedAddressesCount = db_fetch_val("SELECT COUNT(*) FROM `user_addresses` WHERE `user_id` = :id", [':id' => $userId]);

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
    } else {
        $fullName = clean_input($_POST['full_name'] ?? '');
        $phone = clean_input($_POST['phone_number'] ?? '');

        if (!empty($fullName) && !empty($phone)) {
            db_query("UPDATE `users` SET `full_name` = :name, `phone_number` = :phone WHERE `user_id` = :id", [
                ':name' => $fullName,
                ':phone' => $phone,
                ':id' => $userId
            ]);
            $_SESSION['user_name'] = $fullName;
            $_SESSION['user_phone'] = $phone;
            set_flash('success', 'Profile information updated successfully.');
            header("Location: " . CUSTOMER_URL . "/profile.php");
            exit;
        } else {
            set_flash('danger', 'Please fill in all required fields.');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<main class="py-4">
  <div class="container-xl">
    
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h2 class="fw-bold mb-0 font-outfit text-dark"><i class="bi bi-person-circle text-success me-2"></i>My Profile</h2>
        <p class="text-muted small mb-0">Manage your contact details, dietary preferences, and addresses</p>
      </div>
    </div>

    <div class="row g-4">
      
      <!-- Left Column: User Summary Card -->
      <div class="col-lg-4">
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white text-center mb-4">
          <div class="rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3 shadow-sm text-white fs-1" style="width: 90px; height: 90px; background: #2E6F40;">
            <?= strtoupper(substr($userRecord['full_name'], 0, 1)) ?>
          </div>
          <h4 class="fw-bold text-dark mb-1 font-outfit"><?= e($userRecord['full_name']) ?></h4>
          <div class="small text-muted mb-2"><?= e($userRecord['email']) ?></div>
          <div class="small text-muted mb-3"><i class="bi bi-telephone-fill text-success me-1"></i><?= e($userRecord['phone_number']) ?></div>
          
          <span class="badge rounded-pill px-3 py-1 fw-bold" style="background:#CEFFDC; color:#243E2C;">
            🌿 Zippy Member since <?= date('M Y', strtotime($userRecord['created_at'])) ?>
          </span>

          <div class="border-top pt-3 mt-4 text-start d-flex flex-column gap-2">
            <a href="<?= CUSTOMER_URL ?>/manage-addresses.php" class="btn btn-light rounded-pill text-start border d-flex justify-content-between align-items-center">
              <span><i class="bi bi-geo-alt-fill text-success me-2"></i>Saved Addresses</span>
              <span class="badge bg-secondary"><?= (int)$savedAddressesCount ?></span>
            </a>
            <a href="<?= CUSTOMER_URL ?>/order-history.php" class="btn btn-light rounded-pill text-start border d-flex justify-content-between align-items-center">
              <span><i class="bi bi-receipt text-success me-2"></i>Order History</span>
              <span class="badge bg-secondary"><?= (int)$orderStats['total_orders'] ?></span>
            </a>
            <a href="<?= CUSTOMER_URL ?>/logout.php" class="btn btn-outline-danger rounded-pill text-start">
              <i class="bi bi-box-arrow-right me-2"></i>Sign Out
            </a>
          </div>
        </div>

        <!-- Account Stats Card -->
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white">
          <h6 class="fw-bold text-dark mb-3">Order Statistics</h6>
          <div class="d-flex justify-content-between small text-muted mb-2">
            <span>Total Orders Placed:</span>
            <strong class="text-dark"><?= (int)$orderStats['total_orders'] ?> orders</strong>
          </div>
          <div class="d-flex justify-content-between small text-muted">
            <span>Total Amount Spent:</span>
            <strong class="text-forest"><?= format_inr($orderStats['total_spent']) ?></strong>
          </div>
        </div>
      </div>

      <!-- Right Column: Edit Profile Form -->
      <div class="col-lg-8">
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white">
          <h5 class="fw-bold text-dark border-bottom pb-3 mb-4">Personal Details</h5>
          
          <form method="POST" action="<?= CUSTOMER_URL ?>/profile.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">

            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label small fw-bold">Full Name *</label>
                <input type="text" name="full_name" class="form-control rounded-3" value="<?= e($userRecord['full_name']) ?>" required>
              </div>

              <div class="col-md-6">
                <label class="form-label small fw-bold">Email Address</label>
                <input type="email" class="form-control rounded-3 bg-light" value="<?= e($userRecord['email']) ?>" readonly>
                <div class="form-text small">Email cannot be changed once verified.</div>
              </div>

              <div class="col-md-6">
                <label class="form-label small fw-bold">Phone Number *</label>
                <input type="tel" name="phone_number" class="form-control rounded-3" value="<?= e($userRecord['phone_number']) ?>" required>
              </div>

              <div class="col-md-6">
                <label class="form-label small fw-bold">Account Role</label>
                <input type="text" class="form-control rounded-3 bg-light" value="<?= e($currentUser['role_name']) ?>" readonly>
              </div>
            </div>

            <div class="border-top pt-3">
              <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
                <i class="bi bi-check-lg me-1"></i> Save Profile Changes
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
