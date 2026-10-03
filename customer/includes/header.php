<?php
/**
 * Customer Portal Header & Navbar
 * Zippy - Online Food Ordering Platform
 * Design: Figma "Lush Forest" Palette (#Combination-4)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$currentUser = current_user();
$pageTitle = $pageTitle ?? 'Zippy - Online Food Delivery';
$activePage = $activePage ?? '';

// Check database status
$dbStatus = checkDBStatus();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> | Pooja Project</title>
  
  <!-- Online Google Fonts: Plus Jakarta Sans & Outfit -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5.3.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Bootstrap Icons & FontAwesome CDN -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  
  <!-- Custom Figma Lush Forest Palette Stylesheet -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
</head>
<body>

  <!-- DB Warning Banner if tables not seeded -->
  <?php if (!$dbStatus['connected'] || !$dbStatus['tables_exist']): ?>
    <div class="bg-warning text-dark py-2 px-3 small text-center fw-bold">
      <i class="bi bi-exclamation-triangle-fill me-1"></i>
      <?= e($dbStatus['error']) ?>
      <a href="<?= BASE_URL ?>/database/seed.php" class="btn btn-sm btn-dark ms-2 py-0">Run Seeder Now</a>
    </div>
  <?php endif; ?>

  <!-- =========================================================================
       HEADER / NAVBAR
       ========================================================================= -->
  <header class="zippy-navbar sticky-top">
    <div class="container-xl py-2">
      <div class="d-flex align-items-center justify-content-between">
        
        <!-- Left: Brand Logo & Location -->
        <div class="d-flex align-items-center gap-3 gap-xl-4 flex-shrink-0">
          <a href="<?= CUSTOMER_URL ?>/index.php" class="brand-logo text-decoration-none">
            <div class="brand-icon-box">
              <i class="fa-solid fa-utensils"></i>
            </div>
            <span class="brand-name">zippy<span class="brand-highlight">.</span></span>
            <span class="brand-badge d-none d-sm-inline-block">Lush Edition</span>
          </a>

          <!-- Location Selector -->
          <div class="location-selector d-none d-md-flex" data-bs-toggle="modal" data-bs-target="#locationModal" role="button">
            <i class="bi bi-geo-alt-fill text-success"></i>
            <div>
              <span class="loc-title">Indiranagar</span>
              <span class="loc-desc d-inline-block ms-1">Bangalore, Karnataka</span>
            </div>
            <i class="bi bi-chevron-down text-muted small ms-1"></i>
          </div>
        </div>

        <!-- Center: Quick Nav Links -->
        <div class="d-none d-lg-flex align-items-center gap-1">
          <a href="<?= CUSTOMER_URL ?>/search.php" class="nav-link-custom <?= ($activePage === 'search') ? 'active text-success' : '' ?>">
            <i class="bi bi-search text-success"></i>
            <span>Search</span>
          </a>
          <a href="<?= CUSTOMER_URL ?>/offers.php" class="nav-link-custom <?= ($activePage === 'offers') ? 'active text-success' : '' ?>">
            <i class="fa-solid fa-percent text-success"></i>
            <span>Offers</span>
            <span class="badge bg-mint text-dark rounded-pill fw-bold ms-1" style="font-size: 0.65rem;">NEW</span>
          </a>
          <a href="<?= CUSTOMER_URL ?>/help.php" class="nav-link-custom <?= ($activePage === 'help') ? 'active text-success' : '' ?>">
            <i class="bi bi-question-circle text-success"></i>
            <span>Help</span>
          </a>
          <a href="<?= DELIVERY_URL ?>/index.php" class="nav-link-custom text-muted small border-start ps-3 ms-2">
            <i class="bi bi-bicycle text-success"></i>
            <span>Fleet</span>
          </a>
          <a href="<?= ADMIN_URL ?>/index.php" class="nav-link-custom text-muted small">
            <i class="bi bi-speedometer2 text-success"></i>
            <span>Admin</span>
          </a>
        </div>

        <!-- Right: Cart & User Account -->
        <div class="d-flex align-items-center gap-2 gap-sm-3">
          
          <!-- Dedicated Cart Button (Triggers Offcanvas Drawer) -->
          <button class="cart-btn position-relative border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#cartDrawer" aria-controls="cartDrawer">
            <div class="cart-icon-wrapper">
              <i class="bi bi-bag-fill"></i>
              <span class="cart-count-badge">0</span>
            </div>
            <span class="cart-text d-none d-sm-inline">Cart</span>
          </button>

          <!-- User Account Actions -->
          <?php if ($currentUser): ?>
            <div class="dropdown">
              <button class="btn btn-outline-success dropdown-toggle d-flex align-items-center gap-2 rounded-pill px-3 py-2 fw-semibold" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-color: #68BA7F; color: #243E2C;">
                <i class="bi bi-person-circle fs-5 text-success"></i>
                <span class="d-none d-md-inline"><?= e($currentUser['name']) ?></span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 mt-2" style="min-width: 220px;">
                <li class="px-3 py-2 border-bottom">
                  <div class="fw-bold text-truncate"><?= e($currentUser['name']) ?></div>
                  <div class="small text-muted text-truncate"><?= e($currentUser['email']) ?></div>
                  <span class="badge bg-success bg-opacity-25 text-success mt-1 small"><?= e($currentUser['role_name']) ?></span>
                </li>
                <li><a class="dropdown-item py-2" href="<?= CUSTOMER_URL ?>/profile.php"><i class="bi bi-person me-2 text-success"></i>My Profile</a></li>
                <li><a class="dropdown-item py-2" href="<?= CUSTOMER_URL ?>/order-history.php"><i class="bi bi-receipt me-2 text-success"></i>Orders & History</a></li>
                <li><a class="dropdown-item py-2" href="<?= CUSTOMER_URL ?>/manage-addresses.php"><i class="bi bi-geo-alt me-2 text-success"></i>Saved Addresses</a></li>
                <?php if (is_admin()): ?>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item py-2 text-primary" href="<?= ADMIN_URL ?>/index.php"><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-2 text-danger" href="<?= CUSTOMER_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
              </ul>
            </div>
          <?php else: ?>
            <div class="d-flex align-items-center gap-2">
              <a href="<?= CUSTOMER_URL ?>/login.php" class="btn btn-outline-success rounded-pill px-3 py-2 fw-semibold" style="border-color: #2E6F40; color: #2E6F40;">
                <i class="bi bi-person me-1"></i> Sign In
              </a>
              <a href="<?= CUSTOMER_URL ?>/signup.php" class="btn btn-success d-none d-sm-inline-block rounded-pill px-3 py-2 fw-semibold" style="background:#2E6F40; border-color:#2E6F40;">
                Sign Up
              </a>
            </div>
          <?php endif; ?>

        </div>

      </div>
    </div>
  </header>

  <!-- Flash Messages Banner -->
  <div class="container-xl pt-3">
    <?php display_flash(); ?>
  </div>
