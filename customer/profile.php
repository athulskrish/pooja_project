<?php
declare(strict_types=1);
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
$currentUser = getCurrentUser();
requireLogin();

$userId = (int)$currentUser['id'];
$profileData = AdminService::getCustomerProfileData($userId, $currentUser);
$userRecord = $profileData['userRecord'];
$orderStats = $profileData['orderStats'];
$savedAddressesCount = $profileData['savedAddressesCount'];
$previousMeal = $profileData['previousMeal'];
$loyaltyPoints = (int)($profileData['loyaltyPoints'] ?? 0);
$loyaltyHistory = $profileData['loyaltyHistory'] ?? [];
$dietaryPreferences = $profileData['dietaryPreferences'] ?? [];
$favoriteCount = (int)($profileData['favoriteCount'] ?? 0);
$dietaryOptions = getDietaryPreferenceOptions();

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid session token.');
    } else {
        $res = AdminService::updateCustomerProfile(
            $userId,
            (string)($_POST['full_name'] ?? ''),
            (string)($_POST['phone_number'] ?? '')
        );
        setFlash($res['flash_type'], $res['message']);
        if (!empty($res['ok'])) {
            header("Location: " . CUSTOMER_URL . "/profile.php");
            exit;
        }
    }
}

// Handle Dietary & Allergen Preferences Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_dietary') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid session token.');
    } else {
        $prefList = isset($_POST['dietary_preferences']) && is_array($_POST['dietary_preferences'])
            ? $_POST['dietary_preferences']
            : [];
        $res = AdminService::updateCustomerDietaryPreferences($userId, $prefList);
        setFlash($res['flash_type'], $res['message']);
        header("Location: " . CUSTOMER_URL . "/profile.php#dietary-preferences");
        exit;
    }
}

// Handle Security Question Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_security_question') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid session token.');
    } else {
        $secQ = cleanInput($_POST['security_question'] ?? '');
        $secA = $_POST['security_answer'] ?? '';
        $res = updateUserSecurityQuestion((int)$userId, $secQ, $secA);
        setFlash($res['success'] ? 'success' : 'danger', $res['message']);
        header("Location: " . CUSTOMER_URL . "/profile.php");
        exit;
    }
}

$securityQuestionsList = getSecurityQuestions();

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
            <?= strtoupper(substr((string)($userRecord['full_name'] ?? 'U'), 0, 1)) ?>
          </div>
          <h4 class="fw-bold text-dark mb-1 font-outfit"><?= e($userRecord['full_name']) ?></h4>
          <div class="small text-muted mb-2"><?= e($userRecord['email']) ?></div>
          <div class="small text-muted mb-3"><i class="bi bi-telephone-fill text-success me-1"></i><?= e($userRecord['phone_number']) ?></div>
          
          <span class="badge rounded-pill px-3 py-1 fw-bold" style="background:#CEFFDC; color:#243E2C;">
            🌿 Zippy Member since <?= date('M Y', (int)(strtotime((string)($userRecord['created_at'] ?? '')) ?: time())) ?>
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

        <!-- Previous Meal 1-Tap Reorder Card -->
        <?php if (!empty($previousMeal) && !empty($previousMeal['items'])): 
          $profPmOrder = $previousMeal['order'];
          $profPmJson = htmlspecialchars($previousMeal['reorder_json'], ENT_QUOTES, 'UTF-8');
        ?>
          <div class="card border-0 rounded-4 shadow-sm p-3 bg-white mb-4" style="background: linear-gradient(135deg, #F0FAF3 0%, #E8F7EE 100%) !important; border: 1px solid #C4EED3 !important;">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="badge rounded-pill px-2 py-1 fw-bold" style="background: #243E2C; color: #CEFFDC; font-size: 0.68rem;">
                <i class="fa-solid fa-bolt text-mint me-1"></i>1-TAP REORDER
              </span>
              <span class="small text-muted">Order #ZIP-<?= (int)$profPmOrder['order_id'] ?></span>
            </div>
            <div class="small fw-semibold text-dark mb-1">Your Previous Meal</div>
            <div class="small text-muted mb-2 text-start" style="font-size: 0.78rem;">
              <?= implode(', ', array_map(fn(array $it): string => e($it['item_name']) . ' (x' . (int)$it['quantity'] . ')', $previousMeal['items'])) ?>
            </div>
            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
              <span class="fw-bold text-forest"><?= formatInr($profPmOrder['total_amount']) ?></span>
              <button type="button" class="btn btn-success btn-sm rounded-pill px-3 fw-bold" style="background:#2E6F40; border-color:#2E6F40;" onclick="oneTapReorderMeal(<?= $profPmJson ?>, <?= (int)$profPmOrder['order_id'] ?>, false, this)">
                <i class="fa-solid fa-rotate-right me-1"></i>Reorder
              </button>
            </div>
          </div>
        <?php endif; ?>

        <!-- Account Stats Card -->
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white">
          <h6 class="fw-bold text-dark mb-3">Order Statistics</h6>
          <div class="d-flex justify-content-between small text-muted mb-2">
            <span>Total Orders Placed:</span>
            <strong class="text-dark"><?= (int)$orderStats['total_orders'] ?> orders</strong>
          </div>
          <div class="d-flex justify-content-between small text-muted">
            <span>Total Amount Spent:</span>
            <strong class="text-forest"><?= formatInr($orderStats['total_spent']) ?></strong>
          </div>
        </div>
      </div>

      <!-- Right Column: Edit Profile Form -->
      <div class="col-lg-8">
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white">
          <h5 class="fw-bold text-dark border-bottom pb-3 mb-4">Personal Details</h5>
          
          <form method="POST" action="<?= CUSTOMER_URL ?>/profile.php">
            <?= csrfField() ?>
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

        <!-- Security Question Recovery Settings -->
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mt-4">
          <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
            <div>
              <h5 class="fw-bold text-dark mb-1"><i class="bi bi-shield-lock-fill text-success me-2"></i>Account Recovery Security Question</h5>
              <p class="text-muted small mb-0">Used for offline password reset if you forget your password</p>
            </div>
            <?php if (!empty($userRecord['security_question'])): ?>
              <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">Configured</span>
            <?php else: ?>
              <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1">Not Set</span>
            <?php endif; ?>
          </div>

          <form method="POST" action="<?= CUSTOMER_URL ?>/profile.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_security_question">

            <div class="row g-3 mb-4">
              <div class="col-md-7">
                <label class="form-label small fw-bold">Security Question *</label>
                <select name="security_question" class="form-select rounded-3" required>
                  <option value="">-- Select a security question --</option>
                  <?php foreach ($securityQuestionsList as $sq): ?>
                    <option value="<?= e($sq) ?>" <?= (($userRecord['security_question'] ?? '') === $sq) ? 'selected' : '' ?>>
                      <?= e($sq) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-5">
                <label class="form-label small fw-bold">New Secret Answer *</label>
                <input type="text" name="security_answer" class="form-control rounded-3" placeholder="Enter new answer" required>
              </div>
            </div>

            <div class="border-top pt-3">
              <button type="submit" class="btn btn-outline-success rounded-pill px-4 fw-bold">
                <i class="bi bi-key me-1"></i> Update Security Question
              </button>
            </div>
          </form>
        </div>

        <!-- Dietary & Allergen Profile Card -->
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mt-4" id="dietary-preferences">
          <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3 flex-wrap gap-2">
            <div>
              <h5 class="fw-bold text-dark mb-1"><i class="bi bi-shield-check text-success me-2"></i>Dietary & Allergen Profile</h5>
              <p class="text-muted small mb-0">Menu cards automatically display tailored warnings if a dish conflicts with your preferences</p>
            </div>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1"><?= count($dietaryPreferences) ?> Active</span>
          </div>

          <form method="POST" action="<?= CUSTOMER_URL ?>/profile.php#dietary-preferences">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_dietary">

            <div class="row g-2 mb-4">
              <?php foreach ($dietaryOptions as $optKey => $optInfo): ?>
                <?php $checked = in_array($optKey, $dietaryPreferences, true); ?>
                <div class="col-md-6">
                  <label class="d-flex align-items-center gap-2 p-2 rounded-3 border <?= $checked ? 'border-success bg-success-subtle bg-opacity-25' : 'bg-light' ?>" style="cursor:pointer;">
                    <input class="form-check-input m-0" type="checkbox" name="dietary_preferences[]" value="<?= e($optKey) ?>" <?= $checked ? 'checked' : '' ?>>
                    <i class="bi <?= e($optInfo['icon']) ?>"></i>
                    <span class="small fw-semibold text-dark"><?= e($optInfo['label']) ?></span>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
              <i class="bi bi-heart-pulse me-1"></i> Save Dietary Profile
            </button>
          </form>
        </div>

        <!-- Zippy Loyalty Points Balance & Ledger History -->
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mt-4" id="loyalty-points">
          <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3 flex-wrap gap-2">
            <div>
              <h5 class="fw-bold text-dark mb-1"><i class="bi bi-stars text-warning me-2"></i>Zippy Loyalty Points</h5>
              <p class="text-muted small mb-0">Earn 1 point per ₹20 spent on Delivered orders &bull; Redeem 1 pt = ₹1 (up to 50% off food at checkout)</p>
            </div>
            <div class="text-end">
              <div class="fs-4 fw-bold text-forest"><?= $loyaltyPoints ?> pts</div>
              <div class="small text-muted">Value: ₹<?= number_format((float)$loyaltyPoints * LOYALTY_POINT_RUPEE_VALUE, 2) ?> &bull; <?= $favoriteCount ?> Saved Favorites</div>
            </div>
          </div>

          <?php if (empty($loyaltyHistory)): ?>
            <div class="text-center text-muted py-4 small">
              <i class="bi bi-coin fs-3 d-block mb-2 opacity-50"></i>
              No loyalty transactions yet. Place and complete an order to earn points!
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="small text-muted text-uppercase">
                  <tr>
                    <th>Type</th>
                    <th>Activity</th>
                    <th class="text-end">Points</th>
                    <th class="text-end">Balance</th>
                    <th class="text-end">Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($loyaltyHistory as $lTxn): ?>
                    <?php
                      $tType = strtoupper((string)($lTxn['txn_type'] ?? 'EARNED'));
                      $pts = (int)($lTxn['points'] ?? 0);
                      $isPositive = $pts >= 0;
                      $badgeCls = match ($tType) {
                        'EARNED' => 'bg-success-subtle text-success border-success-subtle',
                        'REDEEMED' => 'bg-primary-subtle text-primary border-primary-subtle',
                        'REFUND_BONUS' => 'bg-info-subtle text-info border-info-subtle',
                        default => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                      };
                    ?>
                    <tr>
                      <td><span class="badge border <?= $badgeCls ?>" style="font-size:0.68rem;"><?= e($tType) ?></span></td>
                      <td class="small">
                        <?= e($lTxn['description'] ?? '') ?>
                        <?php if (!empty($lTxn['order_id'])): ?>
                          <span class="text-muted font-monospace ms-1">#ZIP-<?= (int)$lTxn['order_id'] ?></span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end fw-bold font-monospace <?= $isPositive ? 'text-success' : 'text-danger' ?>">
                        <?= $isPositive ? ('+' . $pts) : (string)$pts ?>
                      </td>
                      <td class="text-end small font-monospace text-dark"><?= (int)($lTxn['balance_after'] ?? 0) ?></td>
                      <td class="text-end small text-muted"><?= e(date('M j, g:i A', (int)(strtotime((string)$lTxn['created_at']) ?: time()))) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
