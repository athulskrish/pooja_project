<?php
/**
 * Dedicated Kitchen Station & Atelier Authentication Portal
 * Zippy - Online Food Ordering Platform
 * MCSP-232: MCA Major Project | Student: Pooja JP
 * Monochrome Luxury Edition
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in as a Kitchen Chef or Admin
if (is_kitchen() || is_admin()) {
    header("Location: " . KITCHEN_URL . "/index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Session expired. Please try again.';
    } else {
        $email = clean_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter your kitchen staff email and password.';
        } else {
            $result = attempt_login($email, $password);
            if ($result['success']) {
                $role = strtoupper($result['user']['role_name'] ?? '');
                if ($role === 'KITCHEN' || $role === 'ADMIN') {
                    set_flash('success', "Welcome back, Chef {$result['user']['full_name']}! Kitchen station ready.");
                    header("Location: " . KITCHEN_URL . "/index.php");
                    exit;
                } else {
                    logout_user();
                    $error = "Access denied. The '{$role}' account does not have Kitchen Station permissions.";
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
  <title>Kitchen Station Access | Zippy Atelier KDS</title>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5.3.3 & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  
  <style>
    :root {
      --noir-black: #000000;
      --noir-border: #E4E4E7;
      --noir-muted: #71717A;
    }
    body {
      background-color: #FAFAFA;
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: #000000;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 24px 14px;
    }
    .kds-auth-card {
      background: #FFFFFF;
      border-radius: 20px;
      border: 1px solid var(--noir-border);
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.06);
      max-width: 440px;
      width: 100%;
      margin: auto;
      overflow: hidden;
      transition: all 0.2s ease;
    }
    .kds-auth-header {
      background: #000000;
      padding: 36px 28px 30px;
      text-align: center;
      color: #FFFFFF;
      position: relative;
    }
    .kds-brand-logo {
      font-family: 'Outfit', sans-serif;
      font-weight: 900;
      font-size: 1.45rem;
      letter-spacing: -0.02em;
      text-transform: uppercase;
      margin-bottom: 4px;
    }
    .kds-brand-badge {
      display: inline-block;
      border: 1px solid rgba(255, 255, 255, 0.4);
      color: #FFFFFF;
      font-size: 0.68rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      padding: 3px 10px;
      border-radius: 100px;
      margin-top: 6px;
    }
    .form-control-noir {
      background: #FFFFFF;
      border: 1px solid var(--noir-border);
      color: #000000;
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 0.92rem;
      transition: all 0.18s ease;
    }
    .form-control-noir:focus {
      background: #FFFFFF;
      border-color: #000000;
      color: #000000;
      outline: none;
      box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.08);
    }
    .btn-noir-submit {
      background: #000000;
      color: #FFFFFF;
      font-weight: 700;
      font-size: 0.92rem;
      border: 1px solid #000000;
      border-radius: 100px;
      padding: 13px 24px;
      letter-spacing: 0.02em;
      transition: all 0.18s ease;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    .btn-noir-submit:hover {
      background: #27272A;
      color: #FFFFFF;
      border-color: #27272A;
      transform: translateY(-1px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.22);
    }
    .btn-demo-quick {
      background: #FAFAFA;
      color: #000000;
      border: 1px solid #E4E4E7;
      border-radius: 100px;
      font-size: 0.85rem;
      font-weight: 700;
      padding: 10px 16px;
      transition: all 0.18s ease;
    }
    .btn-demo-quick:hover {
      background: #000000;
      color: #FFFFFF;
      border-color: #000000;
    }
  </style>
</head>
<body>

  <div class="kds-auth-card">
    <!-- Header -->
    <div class="kds-auth-header">
      <div class="rounded-circle bg-white text-black p-3 d-inline-flex align-items-center justify-content-center mb-3" style="width:58px; height:58px; font-size:1.4rem;">
        <i class="fa-solid fa-utensils"></i>
      </div>
      <div class="kds-brand-logo">ZIPPY<span style="font-weight:300;">.</span> KDS</div>
      <span class="kds-brand-badge">CHEF & EXPEDITOR STATION</span>
      <p class="small text-white-50 mt-2 mb-0" style="font-size:0.8rem;">Independent kitchen workflow: accept, prepare, and handover</p>
    </div>

    <!-- Form Body -->
    <div class="p-4 p-md-5">
      <?php display_flash(); ?>

      <?php if (!empty($error)): ?>
        <div class="alert alert-dark py-2 small rounded-3 mb-3 d-flex align-items-center gap-2" style="background:#000; color:#FFF; border:none;">
          <i class="bi bi-exclamation-circle-fill"></i>
          <div><?= e($error) ?></div>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?= KITCHEN_URL ?>/login.php">
        <?= csrf_field() ?>

        <div class="mb-3">
          <label class="form-label small fw-bold text-dark text-uppercase" style="font-size:0.7rem; letter-spacing:0.06em;">Staff Identifier / Email</label>
          <input type="email" name="email" id="kdsEmail" class="form-control form-control-noir" placeholder="chef@zippy.com" value="<?= e($_POST['email'] ?? 'chef@zippy.com') ?>" required>
        </div>

        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label small fw-bold text-dark text-uppercase mb-0" style="font-size:0.7rem; letter-spacing:0.06em;">Password</label>
            <span class="text-muted small" style="font-size: 0.72rem;">Default: <code>password123</code></span>
          </div>
          <input type="password" name="password" id="kdsPassword" class="form-control form-control-noir" placeholder="••••••••" value="password123" required>
        </div>

        <button type="submit" class="btn btn-noir-submit w-100 mb-3">
          Enter Kitchen Station &rarr;
        </button>

        <!-- 1-Click Demo Login -->
        <div class="p-3 bg-light rounded-4 border text-center mb-3">
          <div class="small fw-bold text-muted text-uppercase mb-2" style="font-size:0.68rem; letter-spacing:0.06em;">
            1-Click Demo Credential:
          </div>
          <button type="button" class="btn btn-demo-quick w-100 shadow-sm" onclick="quickLoginChef()">
            <i class="fa-solid fa-hat-chef me-1"></i> Sign In as Chef Sanjeev
          </button>
        </div>

        <div class="d-flex justify-content-between align-items-center small text-muted pt-3 border-top">
          <a href="<?= BASE_URL ?>/index.php" class="text-decoration-none text-muted">
            <i class="bi bi-arrow-left me-1"></i> Portals
          </a>
          <a href="<?= CUSTOMER_URL ?>/index.php" class="text-decoration-none text-dark fw-bold">
            Customer App &rarr;
          </a>
        </div>
      </form>
    </div>
  </div>

  <script>
    function quickLoginChef() {
      document.getElementById('kdsEmail').value = 'chef@zippy.com';
      document.getElementById('kdsPassword').value = 'password123';
      document.forms[0].submit();
    }
  </script>
</body>
</html>
