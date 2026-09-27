<?php
/**
 * Shared Header Component
 * MCSP-232: E-Commerce Platform for Food Order
 * Figma Design System: "Lush Forest" Color Palette (#Combination-4)
 */
require_once __DIR__ . '/../config/database.php';
$currentUser = $_SESSION['user'] ?? null;
$pageTitle = $pageTitle ?? 'Zippy - Online Food Delivery';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= sanitize($pageTitle) ?> | Pooja Project</title>
  
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
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <!-- Top System Notification (Academic Rigor & DB Status) -->
  <div class="py-1 px-3 bg-dark text-white text-center small d-none d-md-block" style="background-color: var(--lush-pine) !important; font-size: 0.76rem; letter-spacing: 0.3px;">
    <span><i class="bi bi-mortarboard-fill text-mint me-1"></i> <strong>MCSP-232 Major Project</strong> • Student: Pooja JP (IGNOU) • PHP 8.2 & MySQL 8.0 (InnoDB)</span>
    <span class="mx-2 opacity-50">•</span>
    <a href="kitchen-display.php" class="text-mint text-decoration-none fw-bold"><i class="fa-solid fa-fire-burner me-1"></i> Open Kitchen KDS Display</a>
    <span class="mx-2 opacity-50">•</span>
    <a href="portals.php" class="text-mint text-decoration-none fw-bold"><i class="bi bi-grid-fill me-1"></i> Multi-Portal Gateway</a>
  </div>

  <!-- Main Navigation Bar -->
  <header class="zippy-navbar sticky-top">
    <div class="container-xl py-2">
      <div class="d-flex align-items-center justify-content-between">
        
        <!-- Left: Brand Logo & Location -->
        <div class="d-flex align-items-center gap-3 gap-xl-4 flex-shrink-0">
          <a href="index.php" class="brand-logo text-decoration-none">
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
          <a href="restaurant.php" class="nav-link-custom">
            <i class="fa-solid fa-bowl-food text-success"></i>
            <span>Menu</span>
          </a>
          <a href="search.php" class="nav-link-custom">
            <i class="bi bi-search text-success"></i>
            <span>Search</span>
          </a>
          <a href="offers.php" class="nav-link-custom">
            <i class="fa-solid fa-percent text-success"></i>
            <span>Offers</span>
            <span class="badge bg-danger rounded-pill" style="font-size: 0.65rem;">NEW</span>
          </a>
          <a href="order-history.php" class="nav-link-custom">
            <i class="bi bi-clock-history text-success"></i>
            <span>Orders</span>
          </a>
          <a href="kitchen-display.php" class="nav-link-custom">
            <i class="fa-solid fa-fire-burner text-warning"></i>
            <span>Kitchen</span>
            <span class="badge bg-warning text-dark rounded-pill" style="font-size: 0.65rem;">KDS</span>
          </a>
          <a href="help.php" class="nav-link-custom">
            <i class="bi bi-question-circle text-success"></i>
            <span>Help</span>
          </a>
        </div>

        <!-- Right: Profile & Cart -->
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
          <?php if ($currentUser): ?>
            <a href="#" class="nav-link-custom" data-bs-toggle="modal" data-bs-target="#profileModal">
              <i class="bi bi-person-circle text-success"></i>
              <span class="d-none d-sm-inline"><?= sanitize(explode(' ', $currentUser['full_name'])[0]) ?></span>
            </a>
          <?php else: ?>
            <a href="login.php" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold">
              <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
            </a>
          <?php endif; ?>

          <!-- Cart Trigger Button -->
          <a href="cart.php" class="btn nav-link-custom cart-pill text-decoration-none">
            <i class="bi bi-cart3"></i>
            <span class="d-none d-md-inline">Cart</span>
            <span class="cart-count-badge">0</span>
          </a>
        </div>

      </div>

      <!-- Mobile Search Bar -->
      <div class="d-lg-none mt-2 pt-2 border-top">
        <a href="search.php" class="text-decoration-none">
          <div class="input-group">
            <span class="input-group-text bg-white border-end-0 rounded-start-pill text-muted ps-3">
              <i class="bi bi-search"></i>
            </span>
            <input type="text" class="form-control border-start-0 rounded-end-pill py-2" placeholder="Search for biryani, chicken 65, butter naan..." readonly>
          </div>
        </a>
      </div>

    </div>
  </header>

  <!-- Flash Message Banner -->
  <?php $flash = getFlash(); if ($flash): ?>
    <div class="container-xl mt-3">
      <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show rounded-3 shadow-xs" role="alert">
        <?= sanitize($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    </div>
  <?php endif; ?>

  <!-- Location Modal -->
  <div class="modal fade" id="locationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-4 border-0 shadow">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold text-pine">Select Delivery Location</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="input-group mb-3">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" class="form-control border-start-0" placeholder="Search street, area or pincode...">
          </div>
          <div class="d-flex align-items-center gap-3 p-3 border rounded-3 bg-light cursor-pointer mb-3" data-bs-dismiss="modal">
            <i class="bi bi-crosshair text-success fs-4"></i>
            <div>
              <div class="fw-bold text-pine">Detect Current Location</div>
              <div class="small text-muted">Using GPS / Geolocation API</div>
            </div>
          </div>
          <h6 class="small text-muted fw-bold text-uppercase mb-2">Saved Addresses</h6>
          <div class="list-group">
            <a href="#" class="list-group-item list-group-item-action" data-bs-dismiss="modal">
              <div class="fw-bold text-pine"><i class="bi bi-house-door-fill text-success me-1"></i> Home (Default)</div>
              <div class="small text-muted">#402, Green Meadows Apartment, Indiranagar, Bangalore - 560038</div>
            </a>
            <a href="#" class="list-group-item list-group-item-action" data-bs-dismiss="modal">
              <div class="fw-bold text-pine"><i class="bi bi-briefcase-fill text-success me-1"></i> Office</div>
              <div class="small text-muted">RMZ Infinity, Old Madras Road, Bennigana Halli, Bangalore - 560016</div>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- User Profile Modal -->
  <?php if ($currentUser): ?>
  <div class="modal fade" id="profileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-4 border-0 shadow">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold text-pine">User Profile</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4 text-center">
          <div class="spec-icon mx-auto mb-3" style="width: 70px; height: 70px; font-size: 2rem;">
            <i class="bi bi-person-fill"></i>
          </div>
          <h5 class="fw-bold text-pine"><?= sanitize($currentUser['full_name']) ?></h5>
          <p class="text-muted small mb-2"><?= sanitize($currentUser['email']) ?> • <?= sanitize($currentUser['phone_number']) ?></p>
          <span class="badge bg-success bg-opacity-25 text-success fw-bold px-3 py-1 rounded-pill mb-4"><?= sanitize($currentUser['membership_tier'] ?? 'Gold Member') ?></span>
          
          <div class="list-group text-start">
            <a href="profile.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
              <span><i class="bi bi-person-circle me-2 text-success"></i> My Profile & Account</span>
              <i class="bi bi-chevron-right text-muted small"></i>
            </a>
            <a href="manage-addresses.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
              <span><i class="bi bi-geo-alt me-2 text-success"></i> Manage Delivery Addresses</span>
              <i class="bi bi-chevron-right text-muted small"></i>
            </a>
            <a href="order-history.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
              <span><i class="bi bi-clock-history me-2 text-success"></i> My Orders & History</span>
              <i class="bi bi-chevron-right text-muted small"></i>
            </a>
            <a href="order-status.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
              <span><i class="fa-solid fa-location-crosshairs me-2 text-success"></i> Live Order Tracking</span>
              <i class="bi bi-chevron-right text-muted small"></i>
            </a>
            <a href="offers.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
              <span><i class="fa-solid fa-percent me-2 text-success"></i> Offers & Coupons</span>
              <i class="bi bi-chevron-right text-muted small"></i>
            </a>
            <a href="help.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
              <span><i class="bi bi-question-circle me-2 text-success"></i> Help & Support</span>
              <i class="bi bi-chevron-right text-muted small"></i>
            </a>
            <a href="logout.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-danger">
              <span><i class="bi bi-box-arrow-right me-2"></i> Sign Out</span>
              <i class="bi bi-chevron-right text-muted small"></i>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
