<?php
/**
 * Multi-Role Authentication Page
 * MCSP-232: E-Commerce Platform for Food Order
 */
$pageTitle = 'Sign In - Zippy';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/controllers/AuthController.php';

// Handle login post
AuthController::handleLogin();
?>

  <main class="container-xl py-5" style="max-width: 520px;">
    
    <div class="p-4 p-md-5 bg-white rounded-4 border shadow-sm">
      
      <div class="text-center mb-4">
        <div class="brand-icon-box mx-auto mb-3" style="width: 55px; height: 55px; font-size: 1.5rem; background-color: var(--lush-forest); color: white; display: flex; align-items: center; justify-content: center; border-radius: 14px;">
          <i class="fa-solid fa-utensils"></i>
        </div>
        <h3 class="fw-bold text-pine mb-1">Welcome to Zippy</h3>
        <p class="text-muted small">Sign in to your food ordering account or portal</p>
      </div>

      <!-- Quick Demo Credentials Selector -->
      <div class="p-3 bg-light rounded-3 border mb-4">
        <div class="small fw-bold text-pine mb-2"><i class="bi bi-key-fill text-success me-1"></i> Quick 1-Click Demo Login:</div>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-sm btn-outline-success rounded-pill" onclick="fillCredentials('pooja.mca2026@ignou.ac.in', 'pooja123')">
            👤 Customer
          </button>
          <button type="button" class="btn btn-sm btn-outline-warning rounded-pill text-dark" onclick="fillCredentials('kitchen@zippy.in', 'pooja123')">
            👨‍🍳 Kitchen
          </button>
          <button type="button" class="btn btn-sm btn-outline-info rounded-pill" onclick="fillCredentials('rider@zippy.in', 'pooja123')">
            🚴 Rider
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="fillCredentials('admin@zippy.in', 'pooja123')">
            👨‍💼 Admin
          </button>
        </div>
      </div>

      <!-- Login Form -->
      <form method="POST" action="login.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
        
        <div class="mb-3">
          <label class="form-label small fw-bold text-pine">Email Address</label>
          <div class="input-group">
            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" id="loginEmail" class="form-control border-start-0" placeholder="name@example.com" value="pooja.mca2026@ignou.ac.in" required>
          </div>
        </div>

        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label small fw-bold text-pine mb-0">Password</label>
            <a href="#" class="small text-success text-decoration-none">Forgot password?</a>
          </div>
          <div class="input-group">
            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" id="loginPassword" class="form-control border-start-0 border-end-0" placeholder="••••••••" value="pooja123" required>
            <span class="input-group-text bg-white border-start-0 cursor-pointer text-muted" onclick="togglePasswordVisibility()">
              <i class="bi bi-eye" id="togglePasswordIcon"></i>
            </span>
          </div>
        </div>

        <div class="form-check mb-4">
          <input class="form-check-input" type="checkbox" id="rememberMe" checked>
          <label class="form-check-label small text-muted" for="rememberMe">Remember me on this browser</label>
        </div>

        <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold py-3 shadow-xs" style="background-color: var(--lush-forest);">
          Sign In to Zippy
        </button>

      </form>

      <div class="text-center mt-4 pt-3 border-top small text-muted">
        Don't have an account yet? <a href="signup.php" class="text-success fw-bold text-decoration-none">Create New Account</a>
      </div>

    </div>

  </main>

  <script>
    function fillCredentials(email, pass) {
      document.getElementById('loginEmail').value = email;
      document.getElementById('loginPassword').value = pass;
      showToast(`Credentials filled for ${email}`);
    }

    function togglePasswordVisibility() {
      const pass = document.getElementById('loginPassword');
      const icon = document.getElementById('togglePasswordIcon');
      if (pass.type === 'password') {
        pass.type = 'text';
        icon.className = 'bi bi-eye-slash';
      } else {
        pass.type = 'password';
        icon.className = 'bi bi-eye';
      }
    }
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
