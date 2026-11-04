<?php
/**
 * Dedicated Kitchen Station & Atelier Authentication Portal
 * Zippy - Online Food Ordering Platform
 * MCSP-232: MCA Major Project | Student: Pooja JP
 * Design System: Figma "Lush Forest" & Material Design 3
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in as a Kitchen Chef or Admin
if (is_kitchen() || is_admin()) {
    header("Location: " . KITCHEN_URL . "/index.php");
    exit;
}

// 1-Click Presentation Access Mode (?demo=1)
if (isset($_GET['demo']) && $_GET['demo'] == '1') {
    $result = attempt_login('chef@zippy.com', 'password123');
    if ($result['success']) {
        set_flash('success', 'Authenticated as Kitchen Chef (Chef Sajeev) in dedicated Kitchen session.');
        header("Location: " . KITCHEN_URL . "/index.php");
        exit;
    }
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
                    session_write_close();
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
  
  <!-- Google Fonts: Plus Jakarta Sans & Outfit & Space Grotesk -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5.3.3 & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  
  <style>
    :root {
      --lush-pine: #243E2C;
      --lush-forest: #2E6F40;
      --lush-forest-hover: #235933;
      --lush-sage: #68BA7F;
      --lush-mint: #CEFFDC;
      --lush-mint-soft: #EDFBF2;
      --lush-bg: #F7FAF8;
      --lush-card: #FFFFFF;
      --lush-border: #E2ECE5;
      --lush-border-subtle: #EEF4F0;
      --lush-text-main: #1C2B20;
      --lush-text-muted: #576E5F;
    }
    
    body {
      background: radial-gradient(circle at 10% 20%, rgba(206, 255, 220, 0.25) 0%, rgba(247, 250, 248, 1) 90%);
      background-color: var(--lush-bg);
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--lush-text-main);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 24px 16px;
      margin: 0;
    }
    
    .kds-auth-card {
      background: var(--lush-card);
      border-radius: 24px;
      border: 1px solid var(--lush-border);
      box-shadow: 0 12px 36px rgba(36, 62, 44, 0.08), 0 2px 8px rgba(36, 62, 44, 0.04);
      max-width: 440px;
      width: 100%;
      margin: auto;
      overflow: hidden;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .kds-auth-header {
      background: linear-gradient(135deg, var(--lush-pine) 0%, var(--lush-forest) 100%);
      padding: 34px 28px 28px;
      text-align: center;
      color: #FFFFFF;
      position: relative;
    }
    
    .kds-auth-icon {
      width: 58px;
      height: 58px;
      background: rgba(255, 255, 255, 0.16);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255, 255, 255, 0.25);
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.45rem;
      color: #FFFFFF;
      margin-bottom: 14px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .kds-brand-logo {
      font-family: 'Outfit', sans-serif;
      font-weight: 800;
      font-size: 1.55rem;
      letter-spacing: -0.02em;
      margin-bottom: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
    
    .kds-brand-badge {
      display: inline-block;
      background: rgba(206, 255, 220, 0.2);
      border: 1px solid rgba(206, 255, 220, 0.4);
      color: var(--lush-mint);
      font-size: 0.68rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      padding: 3px 12px;
      border-radius: 100px;
      margin-top: 4px;
    }
    
    .form-control-lush {
      background: #FFFFFF;
      border: 1.5px solid var(--lush-border);
      color: var(--lush-text-main);
      padding: 12px 16px;
      border-radius: 12px;
      font-size: 0.92rem;
      transition: all 0.2s ease;
    }
    
    .form-control-lush:focus {
      background: #FFFFFF;
      border-color: var(--lush-forest);
      color: var(--lush-text-main);
      outline: none;
      box-shadow: 0 0 0 4px rgba(46, 111, 64, 0.12);
    }
    
    .btn-lush-primary {
      background: var(--lush-forest);
      color: #FFFFFF;
      font-weight: 700;
      font-size: 0.95rem;
      border: none;
      border-radius: 100px;
      padding: 13px 24px;
      letter-spacing: 0.01em;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 4px 14px rgba(46, 111, 64, 0.28);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    
    .btn-lush-primary:hover {
      background: var(--lush-forest-hover);
      color: #FFFFFF;
      transform: translateY(-1px);
      box-shadow: 0 6px 18px rgba(46, 111, 64, 0.35);
    }
    
    .btn-lush-primary:active {
      transform: translateY(0);
    }
    
    .btn-demo-quick {
      background: var(--lush-mint-soft);
      color: var(--lush-forest);
      border: 1.5px solid rgba(46, 111, 64, 0.2);
      border-radius: 100px;
      font-size: 0.88rem;
      font-weight: 700;
      padding: 11px 18px;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
    
    .btn-demo-quick:hover {
      background: var(--lush-mint);
      color: var(--lush-pine);
      border-color: var(--lush-forest);
      transform: translateY(-1px);
    }
    
    .instruction-pill {
      font-size: 0.75rem;
      color: var(--lush-text-muted);
      background: var(--lush-mint-soft);
      border: 1px solid var(--lush-border);
      border-radius: 100px;
      padding: 4px 12px;
    }
  </style>
</head>
<body>

  <div class="kds-auth-card">
    <!-- Header -->
    <div class="kds-auth-header">
      <div class="kds-auth-icon">
        <i class="fa-solid fa-utensils"></i>
      </div>
      <div class="kds-brand-logo">
        <span>ZIPPY</span>
        <span style="font-weight: 300; opacity: 0.8;">|</span>
        <span style="font-weight: 600;">KDS</span>
      </div>
      <span class="kds-brand-badge">Kitchen Display & Expeditor Station</span>
      <p class="small text-white-50 mt-2 mb-0" style="font-size:0.82rem;">Accept orders, manage stove prep, and coordinate delivery handover</p>
    </div>

    <!-- Form Body -->
    <div class="p-4 p-md-4">
      <?php display_flash(); ?>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 small rounded-3 mb-3 d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-circle-fill"></i>
          <div><?= e($error) ?></div>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?= KITCHEN_URL ?>/login.php">
        <?= csrf_field() ?>

        <div class="mb-3">
          <label class="form-label small fw-bold text-uppercase" style="color: var(--lush-pine); font-size:0.72rem; letter-spacing:0.05em;">Staff Identifier / Email</label>
          <input type="email" name="email" id="kdsEmail" class="form-control form-control-lush" placeholder="chef@zippy.com" value="<?= e($_POST['email'] ?? 'chef@zippy.com') ?>" required>
        </div>

        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label small fw-bold text-uppercase mb-0" style="color: var(--lush-pine); font-size:0.72rem; letter-spacing:0.05em;">Password</label>
            <span class="text-muted small" style="font-size: 0.72rem;">Default: <code class="text-success">password123</code></span>
          </div>
          <input type="password" name="password" id="kdsPassword" class="form-control form-control-lush" placeholder="••••••••" value="password123" required>
        </div>

        <button type="submit" class="btn btn-lush-primary w-100 mb-3">
          <span>Enter Kitchen Station</span>
          <i class="bi bi-arrow-right"></i>
        </button>

        <!-- 1-Click Demo Login -->
        <div class="p-3 rounded-4 border text-center mb-3" style="background: var(--lush-bg); border-color: var(--lush-border) !important;">
          <div class="small fw-bold text-uppercase mb-2" style="color: var(--lush-text-muted); font-size:0.68rem; letter-spacing:0.06em;">
            1-Click Quick Demo Sign-In:
          </div>
          <button type="button" class="btn btn-demo-quick w-100 shadow-sm" onclick="quickLoginChef()">
            <i class="fa-solid fa-hat-chef"></i>
            <span>Sign In as Chef Sajeev</span>
          </button>
        </div>

        <div class="d-flex justify-content-between align-items-center small text-muted pt-2 border-top" style="border-color: var(--lush-border) !important;">
          <a href="<?= BASE_URL ?>/index.php" class="text-decoration-none text-muted">
            <i class="bi bi-arrow-left me-1"></i> Portals Hub
          </a>
          <a href="<?= CUSTOMER_URL ?>/index.php" class="text-decoration-none fw-bold" style="color: var(--lush-forest);">
            Customer Storefront &rarr;
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
