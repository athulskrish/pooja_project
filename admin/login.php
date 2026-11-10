<?php
declare(strict_types=1);
/**
 * Administrator & Staff Login Console
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Admin Portal Login';
$activePage = 'login';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// If already logged in as admin/staff, redirect
if (isAdmin()) {
    header("Location: " . ADMIN_URL . "/index.php");
    exit;
}

$loginError = null;

// 1-Click Presentation Access Mode (?demo=1), gated behind opt-in APP_DEMO_MODE
if (isset($_GET['demo']) && $_GET['demo'] == '1') {
    if (defined('APP_DEMO_MODE') && APP_DEMO_MODE) {
        $authResult = loginUser('admin@zippy.com', 'password123', 'ADMIN');
        if ($authResult['success']) {
            setFlash('success', 'Authenticated as Admin (Pooja) in dedicated Admin session.');
            session_write_close();
            header("Location: " . ADMIN_URL . "/index.php");
            exit;
        }
        $loginError = $authResult['message'] ?? 'Demo login failed.';
    } else {
        setFlash('warning', 'Demo quick-login is disabled in this environment. Please sign in with your credentials.');
        header("Location: " . ADMIN_URL . "/login.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $loginError = 'Session expired. Please try again.';
    } else {
        $email = cleanInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $authResult = loginUser($email, $password);

        if ($authResult['success']) {
            $roleName = $authResult['user']['role_name'] ?? '';
            session_write_close();
            if ($roleName === 'ADMIN') {
                setFlash('success', 'Authenticated as ' . $roleName . '. Welcome to Zippy Operations.');
                header("Location: " . ADMIN_URL . "/index.php");
                exit;
            } elseif ($roleName === 'KITCHEN') {
                setFlash('success', 'Authenticated as Kitchen Chef. Redirected to Kitchen Station.');
                header("Location: " . KITCHEN_URL . "/index.php");
                exit;
            } elseif ($roleName === 'RIDER') {
                setFlash('success', 'Authenticated as Delivery Rider. Redirected to Fleet App.');
                header("Location: " . DELIVERY_URL . "/index.php");
                exit;
            } else {
                logoutUser();
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

  <?php displayFlash(); ?>

  <?php if ($loginError): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4 rounded-3 small">
      <i class="bi bi-exclamation-octagon-fill fs-5"></i>
      <div><?= e($loginError) ?></div>
    </div>
  <?php endif; ?>

  <?php if (defined('APP_DEMO_MODE') && APP_DEMO_MODE): ?>
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
  <?php endif; ?>

  <form method="POST" action="<?= ADMIN_URL ?>/login.php">
    <?= csrfField() ?>

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
