<?php
/**
 * Administrator & Staff Login Console
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Admin Portal Login';
$activePage = 'login';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// If already logged in as admin/staff, redirect
if (is_admin()) {
    header("Location: " . ADMIN_URL . "/index.php");
    exit;
}

$loginError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $loginError = 'Session expired. Please try again.';
    } else {
        $email = clean_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $authResult = attempt_login($email, $password);

        if ($authResult['success']) {
            $roleName = $authResult['user']['role_name'] ?? '';
            if ($roleName === 'ADMIN') {
                set_flash('success', 'Authenticated as ' . $roleName . '. Welcome to Zippy Operations.');
                header("Location: " . ADMIN_URL . "/index.php");
                exit;
            } elseif ($roleName === 'KITCHEN') {
                set_flash('success', 'Authenticated as Kitchen Chef. Redirected to Kitchen Station.');
                header("Location: " . KITCHEN_URL . "/index.php");
                exit;
            } elseif ($roleName === 'RIDER') {
                set_flash('success', 'Authenticated as Delivery Rider. Redirected to Fleet App.');
                header("Location: " . DELIVERY_URL . "/index.php");
                exit;
            } else {
                logout_user();
                $loginError = 'Access denied. You do not possess staff or administrator credentials.';
            }
        } else {
            $loginError = $authResult['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin & Staff Login | Zippy Operations</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
  <style>
    body {
      background: #1C2B20;
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .admin-login-card {
      max-width: 460px;
      width: 100%;
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.3);
      padding: 40px;
    }
  </style>
</head>
<body>

<div class="admin-login-card">
  
  <div class="text-center mb-4">
    <div class="brand-icon-box mx-auto mb-3" style="width: 52px; height: 52px; background:#243E2C; color:#CEFFDC; font-size:1.4rem;">
      <i class="fa-solid fa-utensils"></i>
    </div>
    <div class="fw-bold fs-3 text-dark font-outfit" style="letter-spacing: -0.5px;">
      zippy<span style="color:#2E6F40;">.</span>
    </div>
    <span class="badge bg-success bg-opacity-25 text-success rounded-pill px-3 py-1 fw-bold small">
      EXECUTIVE OPERATIONS CONSOLE
    </span>
  </div>

  <?php if ($loginError): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4 rounded-3 small">
      <i class="bi bi-exclamation-octagon-fill fs-5"></i>
      <div><?= e($loginError) ?></div>
    </div>
  <?php endif; ?>

  <!-- 1-Click Demo Fill Buttons -->
  <div class="p-3 rounded-3 mb-4 text-center bg-light border">
    <div class="small fw-bold text-muted mb-2">Instant Demo Staff Login:</div>
    <div class="d-flex gap-2 justify-content-center">
      <button type="button" class="btn btn-sm btn-dark rounded-pill px-3 py-1" onclick="document.getElementById('adminEmail').value='admin@zippy.com'; document.getElementById('adminPassword').value='password123';">
        Fill Admin
      </button>
      <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3 py-1 text-dark" onclick="document.getElementById('adminEmail').value='chef@zippy.com'; document.getElementById('adminPassword').value='password123';">
        Fill Kitchen Chef
      </button>
    </div>
  </div>

  <form method="POST" action="<?= ADMIN_URL ?>/login.php">
    <?= csrf_field() ?>

    <div class="mb-3">
      <label class="form-label small fw-bold">Administrator / Staff Email</label>
      <div class="input-group">
        <span class="input-group-text bg-white text-muted"><i class="bi bi-person-badge"></i></span>
        <input type="email" name="email" id="adminEmail" class="form-control rounded-end-3 py-2" placeholder="admin@zippy.com" required>
      </div>
    </div>

    <div class="mb-4">
      <label class="form-label small fw-bold">Password</label>
      <div class="input-group">
        <span class="input-group-text bg-white text-muted"><i class="bi bi-lock"></i></span>
        <input type="password" name="password" id="adminPassword" class="form-control rounded-end-3 py-2" placeholder="••••••••" required>
      </div>
    </div>

    <button type="submit" class="btn btn-dark w-100 rounded-pill py-3 fw-bold shadow-sm" style="background:#243E2C; font-size:1.05rem;">
      <i class="bi bi-box-arrow-in-right me-1"></i> Access Admin Console
    </button>
  </form>

  <div class="text-center mt-4 pt-3 border-top small text-muted">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <a href="<?= KITCHEN_URL ?>/login.php" class="text-warning fw-bold text-decoration-none">
        <i class="fa-solid fa-fire-burner me-1"></i> Kitchen KDS Portal &rarr;
      </a>
      <a href="<?= DELIVERY_URL ?>/login.php" class="text-mint fw-bold text-decoration-none" style="color:#2E6F40;">
        <i class="fa-solid fa-motorcycle me-1"></i> Delivery App &rarr;
      </a>
    </div>
    <div>
      Are you a customer? 
      <a href="<?= CUSTOMER_URL ?>/index.php" class="text-success fw-bold text-decoration-none ms-1">Customer Storefront &rarr;</a>
    </div>
  </div>

</div>

</body>
</html>
