<?php
/**
 * Customer Sign In Portal
 * Zippy - Online Food Ordering Platform
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// If already logged in, redirect
if (is_logged_in()) {
    header("Location: " . CUSTOMER_URL . "/index.php");
    exit;
}

$redirectTarget = clean_input($_GET['redirect'] ?? '');
$loginError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $loginError = 'Session expired. Please try again.';
    } else {
        $email = clean_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $authResult = attempt_login($email, $password);

        if ($authResult['success']) {
            set_flash('success', 'Welcome back, ' . $authResult['user']['full_name'] . '!');
            if ($redirectTarget === 'checkout') {
                header("Location: " . CUSTOMER_URL . "/checkout.php");
            } else {
                header("Location: " . CUSTOMER_URL . "/index.php");
            }
            exit;
        } else {
            $loginError = $authResult['message'];
        }
    }
}

$pageTitle = 'Customer Sign In';
$activePage = 'login';
require_once __DIR__ . '/includes/header.php';
?>

<main class="py-5">
  <div class="container-xl">
    <div class="row justify-content-center">
      <div class="col-md-7 col-lg-5">
        
        <div class="card border-0 rounded-4 shadow-sm p-4 p-md-5 bg-white">
          
          <div class="text-center mb-4">
            <div class="brand-icon-box mx-auto mb-3" style="width: 48px; height: 48px; background:#2E6F40; color:#fff;">
              <i class="fa-solid fa-utensils fs-4"></i>
            </div>
            <h3 class="fw-bold font-outfit text-dark mb-1">Welcome Back!</h3>
            <p class="text-muted small">Sign in to your Zippy customer account to continue</p>
          </div>

          <?php if ($loginError): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4 rounded-3 small">
              <i class="bi bi-exclamation-octagon-fill fs-5"></i>
              <div><?= e($loginError) ?></div>
            </div>
          <?php endif; ?>

          <!-- 1-Click Demo Customer Account Button -->
          <div class="p-3 rounded-3 mb-4 text-center" style="background: #EDFBF2; border: 1px dashed #2E6F40;">
            <div class="small fw-bold text-forest mb-1">Quick Demo Access (Customer)</div>
            <button type="button" class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-bold" onclick="document.getElementById('loginEmail').value='pooja@example.com'; document.getElementById('loginPassword').value='password123';" style="background:#2E6F40; border-color:#2E6F40;">
              Fill Customer: pooja@example.com
            </button>
          </div>

          <form method="POST" action="<?= CUSTOMER_URL ?>/login.php<?= !empty($redirectTarget) ? '?redirect=' . urlencode($redirectTarget) : '' ?>">
            <?= csrf_field() ?>

            <div class="mb-3">
              <label class="form-label small fw-bold">Email Address</label>
              <div class="input-group">
                <span class="input-group-text bg-white text-muted"><i class="bi bi-envelope"></i></span>
                <input type="email" name="email" id="loginEmail" class="form-control rounded-end-3 py-2" placeholder="pooja@example.com" required>
              </div>
            </div>

            <div class="mb-4">
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label small fw-bold mb-0">Password</label>
                <a href="<?= CUSTOMER_URL ?>/help.php" class="text-success small text-decoration-none">Forgot password?</a>
              </div>
              <div class="input-group mt-1">
                <span class="input-group-text bg-white text-muted"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" id="loginPassword" class="form-control rounded-end-3 py-2" placeholder="••••••••" required>
              </div>
            </div>

            <button type="submit" class="btn btn-success w-100 rounded-pill py-3 fw-bold shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
              Sign In to Zippy
            </button>
          </form>

          <div class="text-center mt-4 pt-3 border-top small text-muted">
            Don't have an account yet? 
            <a href="<?= CUSTOMER_URL ?>/signup.php" class="text-success fw-bold text-decoration-none ms-1">Sign Up Free</a>
          </div>

          <div class="text-center mt-3 pt-2 border-top small text-muted">
            Are you a Store Administrator? 
            <a href="<?= ADMIN_URL ?>/login.php" class="text-dark fw-bold text-decoration-none ms-1">Admin Portal &rarr;</a>
          </div>

        </div>

      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
