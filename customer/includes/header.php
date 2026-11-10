<?php
declare(strict_types=1);
/**
 * Customer Portal Header & Navbar
 * Zippy - Online Food Ordering Platform
 * Design: Figma "Lush Forest" Palette (#Combination-4)
 */

// Send strict anti-caching HTTP headers to ensure fresh catalog data
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$currentUser = getCurrentUser();
$pageTitle = $pageTitle ?? 'Zippy - Online Food Delivery';
$activePage = $activePage ?? '';

// Check database status
$dbStatus = checkDbStatus();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= csrfToken() ?>">
  
  <!-- Anti-Cache Meta Directives for Stale Prevention -->
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">

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
  
  <!-- Custom Figma Lush Forest Palette Stylesheet with cache-busting -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/style.css') ?>">

  <script>
    <?php
    $headerCatalog = CatalogService::getHeaderCatalogState();
    $dishImageMap = $headerCatalog['dish_image_map'];
    $initialCatalogVer = $headerCatalog['catalog_version'];
    ?>
    window.POOJA_DISH_IMAGES = <?= jsJson($dishImageMap) ?>;
    window.DISH_IMAGES_MAP = <?= jsJson($dishImageMap) ?>;
    window.POOJA_CATALOG_VER = <?= jsJson($initialCatalogVer) ?>;
    window.API_URL = <?= jsJson(BASE_URL . '/api') ?>;
    window.CUSTOMER_URL = <?= jsJson(CUSTOMER_URL) ?>;
    window.BASE_URL = <?= jsJson(BASE_URL) ?>;
  </script>
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
          <a href="<?= CUSTOMER_URL ?>/search.php?category=healthy" class="nav-link-custom">
            <i class="fa-solid fa-leaf text-success"></i>
            <span>Healthy</span>
          </a>
          <a href="<?= CUSTOMER_URL ?>/index.php#moodSection" class="nav-link-custom" title="Order food matching your mood vibe">
            <i class="fa-solid fa-masks-theater text-warning"></i>
            <span>Mood Food</span>
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
        </div>

        <!-- Right: Cart & User Account -->
        <div class="d-flex align-items-center gap-2 gap-sm-3 flex-shrink-0">
          
          <!-- Dedicated Material Design 3 Cart Button (Triggers Offcanvas Drawer) -->
          <button class="btn m3-cart-btn rounded-pill position-relative d-flex align-items-center gap-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#cartDrawer" aria-controls="cartDrawer" aria-label="Open Shopping Cart">
            <i class="bi bi-bag-fill fs-6"></i>
            <span class="d-none d-sm-inline">Cart</span>
            <span class="badge rounded-pill cart-count-badge">0</span>
          </button>

          <!-- User Account Actions -->
          <?php if ($currentUser): ?>
            <div class="dropdown">
              <button class="btn btn-outline-success dropdown-toggle d-flex align-items-center gap-2 rounded-pill px-3 py-2 fw-semibold" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-color: #68BA7F; color: #243E2C;">
                <i class="bi bi-person-circle fs-5 text-success"></i>
                <span class="d-none d-sm-inline text-truncate" style="max-width: 120px;"><?= e($currentUser['name']) ?></span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 mt-2" style="min-width: 230px;">
                <li class="px-3 py-2 border-bottom bg-light bg-opacity-50">
                  <div class="fw-bold text-dark text-truncate"><?= e($currentUser['name']) ?></div>
                  <div class="small text-muted text-truncate mb-1"><?= e($currentUser['email']) ?></div>
                  <div class="d-flex align-items-center gap-1 flex-wrap">
                    <?= getRoleBadge($currentUser['role_name'] ?? 'CUSTOMER') ?>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 font-monospace" style="font-size: 0.65rem;" title="Isolated Session: ZIPPY_CUSTOMER_SESS">Customer Scope</span>
                  </div>
                </li>
                <li><a class="dropdown-item py-2" href="<?= CUSTOMER_URL ?>/profile.php"><i class="bi bi-person me-2 text-success"></i>My Profile</a></li>
                <li><a class="dropdown-item py-2" href="<?= CUSTOMER_URL ?>/order-history.php"><i class="bi bi-receipt me-2 text-success"></i>Orders & History</a></li>
                <li><a class="dropdown-item py-2" href="<?= CUSTOMER_URL ?>/manage-addresses.php"><i class="bi bi-geo-alt me-2 text-success"></i>Saved Addresses</a></li>
                <?php if (isAdmin()): ?>
                  <li><hr class="dropdown-divider"></li>
                  <li><h6 class="dropdown-header small text-uppercase fw-bold text-muted" style="font-size:0.7rem;">Staff & Ops Portals</h6></li>
                  <li><a class="dropdown-item py-2 text-danger fw-semibold" href="<?= ADMIN_URL ?>/index.php"><i class="bi bi-speedometer2 me-2"></i>Admin Console</a></li>
                  <li><a class="dropdown-item py-2 text-warning fw-semibold" href="<?= KITCHEN_URL ?>/index.php"><i class="fa-solid fa-fire-burner me-2"></i>Kitchen KDS</a></li>
                  <li><a class="dropdown-item py-2 text-info fw-semibold" href="<?= DELIVERY_URL ?>/index.php"><i class="bi bi-bicycle me-2"></i>Delivery Fleet</a></li>
                <?php elseif (isKitchen()): ?>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item py-2 text-warning fw-semibold" href="<?= KITCHEN_URL ?>/index.php"><i class="fa-solid fa-fire-burner me-2"></i>Kitchen Station KDS</a></li>
                <?php elseif (isRider()): ?>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item py-2 text-info fw-semibold" href="<?= DELIVERY_URL ?>/index.php"><i class="bi bi-bicycle me-2"></i>Delivery Fleet App</a></li>
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
    <?php displayFlash(); ?>
  </div>

  <?php
  // Check for any unreviewed delivered orders for logged-in customer
  $unreviewedDeliveredOrder = null;
  if (!empty($currentUser['id']) && basename($_SERVER['PHP_SELF']) !== 'rate-order.php' && basename($_SERVER['PHP_SELF']) !== 'order-status.php') {
      $unreviewedDeliveredOrder = OrderService::getCustomerUnreviewedDeliveredOrder((int)$currentUser['id']);
  }
  ?>

  <?php if ($unreviewedDeliveredOrder): ?>
    <!-- Floating Review Request Toast for Delivered Order -->
    <div id="unreviewedOrderPrompt" class="position-fixed bottom-0 end-0 m-3 p-3 bg-white rounded-4 shadow-lg border border-success d-flex align-items-center gap-3" style="z-index: 1050; max-width: 420px;">
      <div class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width: 48px; height: 48px; background: #2E6F40;">
        <i class="bi bi-star-fill text-warning fs-5"></i>
      </div>
      <div class="flex-grow-1">
        <div class="fw-bold text-dark small mb-0">How was your meal?</div>
        <div class="text-muted small" style="font-size: 0.76rem;">Order #ZIP-<?= (int)$unreviewedDeliveredOrder['order_id'] ?> was delivered! Please rate your food &amp; delivery rider.</div>
        <div class="d-flex align-items-center gap-2 mt-2">
          <a href="<?= CUSTOMER_URL ?>/rate-order.php?order_id=<?= (int)$unreviewedDeliveredOrder['order_id'] ?>" class="btn btn-sm btn-success rounded-pill px-3 fw-bold" style="background:#2E6F40; border-color:#2E6F40; font-size: 0.75rem;">
            <i class="bi bi-star-fill me-1 text-warning"></i> Rate Food ⭐
          </a>
          <button type="button" class="btn btn-sm btn-light rounded-pill px-2 text-muted" style="font-size: 0.75rem;" onclick="dismissReviewToast(<?= (int)$unreviewedDeliveredOrder['order_id'] ?>)">
            Later
          </button>
        </div>
      </div>
      <button type="button" class="btn-close btn-sm align-self-start" onclick="dismissReviewToast(<?= (int)$unreviewedDeliveredOrder['order_id'] ?>)"></button>
    </div>
    <script>
      if (sessionStorage.getItem('dismiss_review_<?= (int)$unreviewedDeliveredOrder['order_id'] ?>') === '1') {
        const el = document.getElementById('unreviewedOrderPrompt');
        if (el) el.style.display = 'none';
      }
      function dismissReviewToast(oid) {
        sessionStorage.setItem('dismiss_review_' + oid, '1');
        const el = document.getElementById('unreviewedOrderPrompt');
        if (el) el.style.display = 'none';
      }
    </script>
  <?php endif; ?>
