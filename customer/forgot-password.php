<?php
declare(strict_types=1);

/**
 * Offline Account Recovery & Password Reset Portal
 * Zippy - Online Food Ordering Platform
 * 
 * Implements 2-Step Security Question verification for password recovery
 * without requiring external SMTP/SMS servers (ideal for local XAMPP deployment).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    header("Location: " . CUSTOMER_URL . "/index.php");
    exit;
}

$step = 1;
$errorMsg = null;
$targetEmail = '';
$targetName = '';
$securityQuestion = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Session expired. Please refresh and try again.';
    } else {
        $action = cleanInput($_POST['action'] ?? 'lookup_email');

        if ($action === 'lookup_email') {
            $email = cleanInput($_POST['email'] ?? '');
            $lookup = lookupUserSecurityQuestion($email);
            if ($lookup['success']) {
                $step = 2;
                $targetEmail = $lookup['email'];
                $targetName = $lookup['full_name'];
                $securityQuestion = $lookup['security_question'];
            } else {
                $errorMsg = $lookup['message'];
                $targetEmail = $email;
            }
        } elseif ($action === 'reset_password') {
            $email = cleanInput($_POST['email'] ?? '');
            $answer = $_POST['security_answer'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            // Keep user on Step 2 if validation fails
            $lookup = lookupUserSecurityQuestion($email);
            if (!$lookup['success']) {
                $step = 1;
                $errorMsg = $lookup['message'];
            } else {
                $step = 2;
                $targetEmail = $lookup['email'];
                $targetName = $lookup['full_name'];
                $securityQuestion = $lookup['security_question'];

                $resetResult = resetPasswordWithSecurityAnswer($email, $answer, $newPassword, $confirmPassword);
                if ($resetResult['success']) {
                    setFlash('success', $resetResult['message']);
                    header("Location: " . CUSTOMER_URL . "/login.php");
                    exit;
                } else {
                    $errorMsg = $resetResult['message'];
                }
            }
        }
    }
}

$pageTitle = 'Reset Password';
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
              <i class="bi bi-shield-lock-fill fs-4"></i>
            </div>
            <h3 class="fw-bold font-outfit text-dark mb-1">Reset Your Password</h3>
            <p class="text-muted small">
              <?= ($step === 1) ? 'Enter your registered email to verify your security question' : 'Answer your secret security question to set a new password' ?>
            </p>
          </div>

          <!-- Progress Stepper -->
          <div class="d-flex align-items-center justify-content-center gap-2 mb-4 small">
            <span class="badge rounded-pill px-3 py-1 <?= ($step === 1) ? 'bg-success' : 'bg-success-subtle text-success border border-success-subtle' ?>">
              1. Find Account
            </span>
            <i class="bi bi-chevron-right text-muted"></i>
            <span class="badge rounded-pill px-3 py-1 <?= ($step === 2) ? 'bg-success' : 'bg-light text-muted border' ?>">
              2. Security Question &amp; Reset
            </span>
          </div>

          <?php if ($errorMsg): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4 rounded-3 small">
              <i class="bi bi-exclamation-octagon-fill fs-5"></i>
              <div><?= e($errorMsg) ?></div>
            </div>
          <?php endif; ?>

          <?php if ($step === 1): ?>
            <!-- STEP 1: EMAIL LOOKUP -->
            <form method="POST" action="<?= CUSTOMER_URL ?>/forgot-password.php">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="lookup_email">

              <div class="mb-4">
                <label class="form-label small fw-bold">Registered Email Address</label>
                <div class="input-group">
                  <span class="input-group-text bg-white text-muted"><i class="bi bi-envelope"></i></span>
                  <input type="email" name="email" class="form-control rounded-end-3 py-2" placeholder="athul@example.com" value="<?= e($targetEmail) ?>" required autofocus>
                </div>
                <div class="form-text small">Works completely offline using your chosen security question.</div>
              </div>

              <button type="submit" class="btn btn-success w-100 rounded-pill py-3 fw-bold shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
                Continue to Security Question <i class="bi bi-arrow-right ms-1"></i>
              </button>
            </form>

            <?php if (defined('APP_DEMO_MODE') && APP_DEMO_MODE): ?>
            <!-- Demo Helper Hint -->
            <div class="p-3 rounded-3 mt-4 text-center small" style="background: #EDFBF2; border: 1px dashed #2E6F40;">
              <div class="fw-bold text-forest mb-1"><i class="bi bi-info-circle me-1"></i> Demo Account Note</div>
              <div class="text-muted" style="font-size: 0.78rem;">
                Seeded demo accounts (e.g. <code>athul@example.com</code>) use the default security answer: <code>zippy</code>
              </div>
            </div>
            <?php endif; ?>
          <?php else: ?>
            <!-- STEP 2: SECURITY QUESTION VERIFICATION & NEW PASSWORD -->
            <div class="p-3 rounded-3 mb-4 border" style="background: #F5FAF6; border-color: #D2E8D8 !important;">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small text-muted fw-semibold">Account: <strong><?= e($targetName) ?></strong></span>
                <a href="<?= CUSTOMER_URL ?>/forgot-password.php" class="small text-success text-decoration-none fw-bold">Change</a>
              </div>
              <div class="small text-muted mb-2"><?= e($targetEmail) ?></div>
              <div class="pt-2 border-top">
                <div class="small text-uppercase fw-bold text-forest" style="font-size: 0.7rem;">Your Security Question</div>
                <div class="fw-bold text-dark mt-1"><?= e($securityQuestion) ?></div>
              </div>
            </div>

            <form method="POST" action="<?= CUSTOMER_URL ?>/forgot-password.php">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="reset_password">
              <input type="hidden" name="email" value="<?= e($targetEmail) ?>">

              <div class="mb-3">
                <label class="form-label small fw-bold">Your Security Answer *</label>
                <div class="input-group">
                  <span class="input-group-text bg-white text-muted"><i class="bi bi-key"></i></span>
                  <input type="text" name="security_answer" class="form-control rounded-end-3 py-2" placeholder="Enter your answer (case-insensitive)" required autofocus>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label small fw-bold">New Password *</label>
                <div class="input-group">
                  <span class="input-group-text bg-white text-muted"><i class="bi bi-lock"></i></span>
                  <input type="password" name="new_password" class="form-control rounded-end-3 py-2" placeholder="Minimum 6 characters" minlength="6" required>
                </div>
              </div>

              <div class="mb-4">
                <label class="form-label small fw-bold">Confirm New Password *</label>
                <div class="input-group">
                  <span class="input-group-text bg-white text-muted"><i class="bi bi-lock-fill"></i></span>
                  <input type="password" name="confirm_password" class="form-control rounded-end-3 py-2" placeholder="Re-enter new password" minlength="6" required>
                </div>
              </div>

              <button type="submit" class="btn btn-success w-100 rounded-pill py-3 fw-bold shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
                <i class="bi bi-check-circle me-1"></i> Verify &amp; Reset Password
              </button>
            </form>
          <?php endif; ?>

          <div class="text-center mt-4 pt-3 border-top small text-muted">
            Remember your password? 
            <a href="<?= CUSTOMER_URL ?>/login.php" class="text-success fw-bold text-decoration-none ms-1">Back to Sign In</a>
          </div>

        </div>

      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
