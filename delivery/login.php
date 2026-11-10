<?php
declare(strict_types=1);
/**
 * Delivery Partner Fleet App - Rider Authentication
 * Zippy - Online Food Ordering Platform
 * MCSP-232: MCA Major Project | Student: Pooja JP
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Prevent browser from caching auth state
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Redirect if already logged in as a Rider or Admin
if (isRider() || isAdmin()) {
    session_write_close();
    header("Location: " . DELIVERY_URL . "/index.php");
    exit;
}

$error = '';

// 1-Click Presentation Access Mode (?demo=1), gated behind opt-in APP_DEMO_MODE
if (isset($_GET['demo']) && $_GET['demo'] == '1') {
    if (defined('APP_DEMO_MODE') && APP_DEMO_MODE) {
        $result = loginUser('shaji@zippy.com', 'password123', 'RIDER');
        if ($result['success']) {
            $_SESSION['rider_on_duty'] = true;
            setFlash('success', 'Authenticated as Delivery Rider (Shaji) in dedicated Fleet session.');
            session_write_close();
            header("Location: " . DELIVERY_URL . "/index.php");
            exit;
        }
        $error = $result['message'] ?? 'Demo login failed.';
    } else {
        setFlash('warning', 'Demo quick-login is disabled in this environment. Please sign in with your credentials.');
        header("Location: " . DELIVERY_URL . "/login.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyRequestCsrf()) {
        http_response_code(403);
        $error = 'Invalid or expired CSRF token. Please refresh and try again.';
    } else {
        $email = cleanInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please provide both your rider email and password.';
        } else {
            $result = loginUser($email, $password);
            if ($result['success']) {
                $role = strtoupper($result['user']['role_name'] ?? '');
                if ($role === 'RIDER' || $role === 'ADMIN') {
                    $_SESSION['rider_on_duty'] = true;
                    setFlash('success', "Welcome back, {$result['user']['full_name']}! You are on duty.");
                    session_write_close();
                    header("Location: " . DELIVERY_URL . "/index.php");
                    exit;
                } else {
                    logoutUser();
                    $error = "Access denied. The '{$role}' account does not possess Delivery Partner privileges.";
                }
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Delivery Partner Login - Zippy Fleet</title>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5.3.3 & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  
  <style>
    :root {
      --rider-forest: #2E6F40;
      --rider-pine: #243E2C;
      --rider-mint: #CEFFDC;
      --rider-bg: #F5F8F6;
      --rider-border: #E1ECE5;
    }
    body {
      background-color: var(--rider-bg);
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: #1C2B20;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 20px 10px;
    }
    .login-box-card {
      background: #FFFFFF;
      border-radius: 24px;
      border: 1px solid var(--rider-border);
      box-shadow: 0 12px 35px rgba(36, 62, 44, 0.08);
      max-width: 440px;
      width: 100%;
      margin: auto;
      overflow: hidden;
    }
    .btn-rider-primary {
      background-color: var(--rider-forest);
      color: #FFFFFF;
      font-weight: 700;
      border: none;
      border-radius: 50px;
      padding: 12px 20px;
      transition: all 0.2s ease;
    }
    .btn-rider-primary:hover {
      background-color: #235933;
      color: #FFFFFF;
      box-shadow: 0 4px 12px rgba(46, 111, 64, 0.25);
    }
  </style>
</head>
<body>

  <div class="login-box-card">
    <!-- Header Banner -->
    <div class="p-4 text-center text-white" style="background: linear-gradient(135deg, #1C3322 0%, #2E6F40 100%);">
      <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-20 p-3 mb-2" style="width: 64px; height: 64px;">
        <i class="fa-solid fa-motorcycle fs-2 text-white"></i>
      </div>
      <h3 class="fw-bold mb-1 font-outfit" style="font-family:'Outfit',sans-serif;">zippy<span style="color:#68BA7F;">.</span> Fleet</h3>
      <p class="small text-white-50 mb-0">Delivery Boy & Partner Operations Portal</p>
    </div>

    <!-- Login Body -->
    <div class="p-4">
      <?php displayFlash(); ?>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 small rounded-3 mb-3 d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-octagon-fill fs-5"></i>
          <div><?= e($error) ?></div>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?= DELIVERY_URL ?>/login.php">
        <?= csrfField() ?>

        <div class="mb-3">
          <label class="form-label small fw-bold text-dark">Rider Email or User ID</label>
          <div class="input-group">
            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
            <input type="email" name="email" id="inputEmail" class="form-control border-start-0" placeholder="shaji@zippy.com" value="<?= e($_POST['email'] ?? ((defined('APP_DEMO_MODE') && APP_DEMO_MODE) ? 'rider@zippy.com' : '')) ?>" required>
          </div>
        </div>

        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center">
            <label class="form-label small fw-bold text-dark">Password</label>
            <?php if (defined('APP_DEMO_MODE') && APP_DEMO_MODE): ?>
              <span class="small text-muted" style="font-size: 0.75rem;">Default: <code>password123</code></span>
            <?php endif; ?>
          </div>
          <div class="input-group">
            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-key"></i></span>
            <input type="password" name="password" id="inputPassword" class="form-control border-start-0" placeholder="••••••••" value="<?= (defined('APP_DEMO_MODE') && APP_DEMO_MODE) ? 'password123' : '' ?>" required>
          </div>
        </div>

        <button type="submit" class="btn btn-rider-primary w-100 mb-3 shadow-sm">
          <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Fleet App
        </button>

        <?php if (defined('APP_DEMO_MODE') && APP_DEMO_MODE): ?>
        <!-- 1-Click Quick Demo Login -->
        <div class="p-3 bg-light rounded-3 border text-center mb-3">
          <div class="small fw-bold text-dark mb-2">
            <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Quick 1-Click Demo Login (3 Fleet Drivers):
          </div>
          <div class="d-grid gap-2">
            <button type="button" class="btn btn-sm btn-white border rounded-pill w-100 fw-bold shadow-xs py-2 text-dark bg-white" onclick="quickFillAndSubmit('shaji@zippy.com')">
              <i class="fa-solid fa-motorcycle text-success me-1"></i> Shaji (Rider) &bull; <span class="text-muted small">shaji@zippy.com</span>
            </button>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-white border rounded-pill flex-fill fw-semibold shadow-xs py-1 text-dark bg-white" onclick="quickFillAndSubmit('kichu@zippy.com')">
                <i class="fa-solid fa-id-badge text-info me-1"></i> Kichu
              </button>
              <button type="button" class="btn btn-sm btn-white border rounded-pill flex-fill fw-semibold shadow-xs py-1 text-dark bg-white" onclick="quickFillAndSubmit('abhi@zippy.com')">
                <i class="fa-solid fa-id-badge text-primary me-1"></i> Abhi
              </button>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center small text-muted pt-2 border-top">
          <a href="<?= BASE_URL ?>/index.php" class="text-decoration-none text-muted">
            <i class="bi bi-arrow-left me-1"></i> All Portals
          </a>
          <a href="<?= CUSTOMER_URL ?>/index.php" class="text-decoration-none text-success fw-semibold">
            Customer App <i class="bi bi-arrow-right ms-1"></i>
          </a>
        </div>
      </form>
    </div>
  </div>

  <?php if (defined('APP_DEMO_MODE') && APP_DEMO_MODE): ?>
  <script>
    function quickFillAndSubmit(email) {
      document.getElementById('inputEmail').value = email || 'shaji@zippy.com';
      document.getElementById('inputPassword').value = 'password123';
      document.forms[0].submit();
    }
  </script>
  <?php endif; ?>
</body>
</html>
