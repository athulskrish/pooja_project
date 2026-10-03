<?php
/**
 * Customer Registration Portal
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// If already logged in, redirect
if (is_logged_in()) {
    header("Location: " . CUSTOMER_URL . "/index.php");
    exit;
}

$registerError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $registerError = 'Session expired. Please try again.';
    } else {
        $fullName = clean_input($_POST['full_name'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        $phone = clean_input($_POST['phone_number'] ?? '');
        $password = $_POST['password'] ?? '';
        $address = clean_input($_POST['address_line'] ?? '');
        $pincode = clean_input($_POST['pincode'] ?? '560038');

        $regResult = register_customer($fullName, $email, $phone, $password, $address, $pincode);

        if ($regResult['success']) {
            set_flash('success', $regResult['message']);
            header("Location: " . CUSTOMER_URL . "/index.php");
            exit;
        } else {
            $registerError = $regResult['message'];
        }
    }
}

$pageTitle = 'Create a Customer Account';
$activePage = 'signup';
require_once __DIR__ . '/includes/header.php';
?>

<main class="py-5">
  <div class="container-xl">
    <div class="row justify-content-center">
      <div class="col-md-8 col-lg-6">
        
        <div class="card border-0 rounded-4 shadow-sm p-4 p-md-5 bg-white">
          
          <div class="text-center mb-4">
            <div class="brand-icon-box mx-auto mb-3" style="width: 48px; height: 48px; background:#2E6F40; color:#fff;">
              <i class="fa-solid fa-utensils fs-4"></i>
            </div>
            <h3 class="fw-bold font-outfit text-dark mb-1">Create Your Account</h3>
            <p class="text-muted small">Join Zippy for express delivery and members-only food discounts</p>
          </div>

          <?php if ($registerError): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4 rounded-3 small">
              <i class="bi bi-exclamation-octagon-fill fs-5"></i>
              <div><?= e($registerError) ?></div>
            </div>
          <?php endif; ?>

          <form method="POST" action="<?= CUSTOMER_URL ?>/signup.php">
            <?= csrf_field() ?>

            <div class="row g-3 mb-3">
              <div class="col-12">
                <label class="form-label small fw-bold">Full Name *</label>
                <input type="text" name="full_name" class="form-control rounded-3" placeholder="Pooja JP" required>
              </div>

              <div class="col-md-6">
                <label class="form-label small fw-bold">Email Address *</label>
                <input type="email" name="email" class="form-control rounded-3" placeholder="pooja@example.com" required>
              </div>

              <div class="col-md-6">
                <label class="form-label small fw-bold">Phone Number *</label>
                <input type="tel" name="phone_number" class="form-control rounded-3" placeholder="+91 98860 12345" required>
              </div>

              <div class="col-12">
                <label class="form-label small fw-bold">Create Password *</label>
                <input type="password" name="password" class="form-control rounded-3" placeholder="Minimum 6 characters" minlength="6" required>
              </div>

              <div class="col-12">
                <label class="form-label small fw-bold">Delivery Address Line (Optional)</label>
                <textarea name="address_line" class="form-control rounded-3" rows="2" placeholder="Flat No, Apartment, Street Name, Indiranagar"></textarea>
              </div>

              <div class="col-md-6">
                <label class="form-label small fw-bold">Pincode</label>
                <input type="text" name="pincode" class="form-control rounded-3" placeholder="560038" value="560038">
              </div>

              <div class="col-md-6">
                <label class="form-label small fw-bold">City</label>
                <input type="text" class="form-control rounded-3 bg-light" value="Bangalore" readonly>
              </div>
            </div>

            <div class="form-check mb-4">
              <input class="form-check-input" type="checkbox" id="termsAgree" required checked>
              <label class="form-check-label small text-muted" for="termsAgree">
                I agree to the <a href="<?= CUSTOMER_URL ?>/terms.php" class="text-success" target="_blank">Terms of Service</a> & <a href="<?= CUSTOMER_URL ?>/privacy.php" class="text-success" target="_blank">Privacy Policy</a>
              </label>
            </div>

            <button type="submit" class="btn btn-success w-100 rounded-pill py-3 fw-bold shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
              Create Free Account
            </button>
          </form>

          <div class="text-center mt-4 pt-3 border-top small text-muted">
            Already have an account? 
            <a href="<?= CUSTOMER_URL ?>/login.php" class="text-success fw-bold text-decoration-none ms-1">Sign In</a>
          </div>

        </div>

      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
