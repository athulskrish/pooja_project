<?php
/**
 * User Profile & Account Settings
 * MCSP-232: E-Commerce Platform for Food Order
 */
$pageTitle = 'My Profile & Account - Zippy';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/models/User.php';

$userId = $_SESSION['user']['user_id'] ?? 1;
$user = User::getById($userId) ?? $_SESSION['user'];

// Handle profile update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone    = trim($_POST['phone_number'] ?? '');
    if (!empty($fullName) && !empty($phone)) {
        User::updateProfile($userId, $fullName, $phone);
        $user['full_name'] = $fullName;
        $user['phone_number'] = $phone;
        setFlash('success', 'Profile updated successfully!');
    }
}
?>

  <main class="container-xl py-4" style="max-width: 860px;">
    
    <!-- Profile Header Card -->
    <div class="p-4 bg-white rounded-4 border shadow-sm mb-4">
      <div class="d-flex align-items-center gap-4 flex-wrap">
        <div class="spec-icon rounded-circle bg-success text-white d-flex align-items-center justify-content-center shadow-xs" style="width: 80px; height: 80px; font-size: 2.5rem; background-color: var(--lush-forest) !important;">
          <i class="bi bi-person-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="d-flex align-items-center gap-2 mb-1">
            <h3 class="fw-bold text-pine mb-0"><?= sanitize($user['full_name'] ?? 'Pooja JP') ?></h3>
            <span class="badge bg-warning text-dark fw-bold small"><i class="bi bi-star-fill me-1"></i> Zippy One Gold</span>
          </div>
          <p class="text-muted small mb-2"><?= sanitize($user['email'] ?? 'pooja.mca2026@ignou.ac.in') ?> • <?= sanitize($user['phone_number'] ?? '+91 98765 43210') ?></p>
          <span class="badge bg-success bg-opacity-10 text-success small border border-success border-opacity-25">
            <i class="bi bi-mortarboard me-1"></i> IGNOU MCA Student • Enr: 2500051247
          </span>
        </div>
      </div>
    </div>

    <!-- Account Statistics Grid -->
    <div class="row g-3 mb-4">
      <div class="col-sm-4">
        <div class="p-3 bg-white rounded-4 border shadow-xs text-center">
          <div class="fw-extrabold text-pine fs-3 mb-1">18</div>
          <div class="small text-muted fw-bold text-uppercase">Total Orders Placed</div>
        </div>
      </div>
      <div class="col-sm-4">
        <div class="p-3 bg-white rounded-4 border shadow-xs text-center">
          <div class="fw-extrabold text-success fs-3 mb-1">₹1,420</div>
          <div class="small text-muted fw-bold text-uppercase">Total Savings via Coupons</div>
        </div>
      </div>
      <div class="col-sm-4">
        <div class="p-3 bg-white rounded-4 border shadow-xs text-center">
          <div class="fw-extrabold text-pine fs-3 mb-1">2</div>
          <div class="small text-muted fw-bold text-uppercase">Saved Delivery Addresses</div>
        </div>
      </div>
    </div>

    <!-- Edit Profile & Dietary Preferences -->
    <div class="row g-4 mb-4">
      
      <!-- Edit Profile Form -->
      <div class="col-md-7">
        <div class="p-4 bg-white rounded-4 border shadow-xs h-100">
          <h5 class="fw-bold text-pine mb-3">Edit Profile Details</h5>
          <form method="POST" action="profile.php">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="mb-3">
              <label class="form-label small fw-bold text-pine">Full Name</label>
              <input type="text" name="full_name" class="form-control" value="<?= sanitize($user['full_name'] ?? 'Pooja JP') ?>" required>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold text-pine">Email Address (Read-only)</label>
              <input type="email" class="form-control bg-light" value="<?= sanitize($user['email'] ?? 'pooja.mca2026@ignou.ac.in') ?>" readonly>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold text-pine">Phone Number</label>
              <input type="text" name="phone_number" class="form-control" value="<?= sanitize($user['phone_number'] ?? '+91 98765 43210') ?>" required>
            </div>

            <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-xs" style="background-color: var(--lush-forest);">
              Save Profile Changes
            </button>
          </form>
        </div>
      </div>

      <!-- Quick Navigation & Dietary Preferences -->
      <div class="col-md-5">
        <div class="p-4 bg-white rounded-4 border shadow-xs h-100 d-flex flex-column justify-content-between">
          <div>
            <h5 class="fw-bold text-pine mb-3">Dietary Preferences</h5>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="prefVeg">
              <label class="form-check-label small fw-bold text-pine" for="prefVeg">Prefer Vegetarian Dishes by default</label>
            </div>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="prefSpice" checked>
              <label class="form-check-label small fw-bold text-pine" for="prefSpice">Medium / Mild Spice Level Alert</label>
            </div>
          </div>

          <div class="list-group">
            <a href="manage-addresses.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center small fw-bold">
              <span><i class="bi bi-geo-alt me-2 text-success"></i> Manage Addresses</span>
              <i class="bi bi-chevron-right text-muted"></i>
            </a>
            <a href="order-history.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center small fw-bold">
              <span><i class="bi bi-clock-history me-2 text-success"></i> Order History</span>
              <i class="bi bi-chevron-right text-muted"></i>
            </a>
            <a href="logout.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center small fw-bold text-danger">
              <span><i class="bi bi-box-arrow-right me-2"></i> Log Out</span>
              <i class="bi bi-chevron-right text-muted"></i>
            </a>
          </div>
        </div>
      </div>

    </div>

  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
