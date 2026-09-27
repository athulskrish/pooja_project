<?php
/**
 * Custom 404 Error Page
 * MCSP-232: E-Commerce Platform for Food Order
 */
$pageTitle = 'Page Not Found (404) - Zippy';
require_once __DIR__ . '/includes/header.php';
?>

  <main class="container-xl py-5 text-center" style="max-width: 600px;">
    
    <div class="p-5 bg-white rounded-4 border shadow-sm">
      <div class="display-1 fw-bold text-success mb-2" style="color: var(--lush-forest) !important;">404</div>
      <h3 class="fw-bold text-pine mb-2">Oops! This dish isn't on our menu.</h3>
      <p class="text-muted small mb-4">
        The page you are looking for might have been devoured, removed, or temporarily moved to another station.
      </p>

      <div class="d-flex justify-content-center gap-2">
        <a href="index.php" class="btn btn-success rounded-pill px-4 fw-bold" style="background-color: var(--lush-forest);">
          <i class="bi bi-house-door me-1"></i> Back to Home
        </a>
        <a href="restaurant.php" class="btn btn-outline-success rounded-pill px-4 fw-bold">
          <i class="fa-solid fa-bowl-food me-1"></i> View Menu
        </a>
      </div>
    </div>

  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
