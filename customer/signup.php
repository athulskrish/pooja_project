<?php
/**
 * Customer Registration Page
 * MCSP-232: E-Commerce Platform for Food Order
 */
$pageTitle = 'Sign Up & Create Account - Zippy';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/controllers/AuthController.php';

// Handle registration POST
AuthController::handleRegister();
?>

  <main class="container-xl py-5" style="max-width: 540px;">
    
    <div class="p-4 p-md-5 bg-white rounded-4 border shadow-sm">
      
      <div class="text-center mb-4">
        <div class="brand-icon-box mx-auto mb-3" style="width: 55px; height: 55px; font-size: 1.5rem; background-color: var(--lush-forest); color: white; display: flex; align-items: center; justify-content: center; border-radius: 14px;">
          <i class="fa-solid fa-utensils"></i>
        </div>
        <h3 class="fw-bold text-pine mb-1">Create Your Account</h3>
        <p class="text-muted small">Join Zippy to order food, track orders live, and earn gold rewards</p>
      </div>

      <form method="POST" action="signup.php">
        
        <div class="mb-3">
          <label class="form-label small fw-bold text-pine">Full Name</label>
          <input type="text" name="full_name" class="form-control" placeholder="Pooja JP" required>
        </div>

        <div class="mb-3">
          <label class="form-label small fw-bold text-pine">Email Address</label>
          <input type="email" name="email" class="form-control" placeholder="pooja@example.com" required>
        </div>

        <div class="mb-3">
          <label class="form-label small fw-bold text-pine">Mobile Phone Number</label>
          <input type="tel" name="phone_number" class="form-control" placeholder="+91 98765 43210" required>
        </div>

        <div class="mb-3">
          <label class="form-label small fw-bold text-pine">Create Password</label>
          <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required minlength="6">
        </div>

        <div class="form-check mb-4">
          <input class="form-check-input" type="checkbox" id="termsCheck" required checked>
          <label class="form-check-label small text-muted" for="termsCheck">
            I agree to the <a href="terms.php" class="text-success text-decoration-none">Terms of Service</a> & <a href="privacy.php" class="text-success text-decoration-none">Privacy Policy</a>
          </label>
        </div>

        <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold py-3 shadow-xs" style="background-color: var(--lush-forest);">
          Create My Zippy Account
        </button>

      </form>

      <div class="text-center mt-4 pt-3 border-top small text-muted">
        Already have an account? <a href="login.php" class="text-success fw-bold text-decoration-none">Sign In Here</a>
      </div>

    </div>

  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
