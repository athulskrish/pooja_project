<?php
/**
 * Admin Console Header & Responsive Sidebar
 * Zippy - Online Food Ordering Platform
 * Design: Figma "Lush Forest" Palette (#Combination-4)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$currentUser = current_user();
$pageTitle = $pageTitle ?? 'Admin Console - Zippy';
$activePage = $activePage ?? 'dashboard';

// Enforce Admin / Staff Access (allow roles: ADMIN, KITCHEN, RIDER)
if (!is_logged_in() || (!is_admin() && ($currentUser['role_name'] ?? '') !== 'KITCHEN')) {
    // If not logged in, redirect to admin login
    if (!str_contains($_SERVER['SCRIPT_NAME'], 'login.php')) {
        header("Location: " . ADMIN_URL . "/login.php");
        exit;
    }
}

// Active Kitchen Tickets count
$activeOrdersCount = db_fetch_val("
    SELECT COUNT(*) FROM `orders` 
    WHERE `order_status` IN ('CONFIRMED', 'COOKING')
") ?: 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> | Zippy Admin</title>
  
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

  <!-- Chart.js CDN for Analytics -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
</head>
<body>

  <div class="admin-layout">
    
    <!-- =======================================================================
         ADMIN SIDEBAR
         ======================================================================= -->
    <aside class="admin-sidebar">
      <div class="admin-sidebar-header">
        <div class="brand-icon-box" style="width: 38px; height: 38px; font-size: 1.1rem; background:#2E6F40;">
          <i class="fa-solid fa-utensils text-white"></i>
        </div>
        <div>
          <div class="fw-bold text-white fs-5" style="letter-spacing: -0.5px; font-family: 'Outfit', sans-serif;">
            zippy<span style="color:#68BA7F;">.</span>
          </div>
          <span class="badge bg-success bg-opacity-50 text-mint" style="font-size: 0.65rem;">ADMIN OPERATIONS</span>
        </div>
      </div>

      <ul class="admin-nav-list">
        <li class="admin-nav-item">
          <a href="<?= ADMIN_URL ?>/index.php" class="admin-nav-link <?= ($activePage === 'dashboard') ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
          </a>
        </li>

        <li class="admin-nav-item">
          <a href="<?= ADMIN_URL ?>/orders.php" class="admin-nav-link <?= ($activePage === 'orders') ? 'active' : '' ?>">
            <i class="bi bi-receipt"></i>
            <span>Live Orders</span>
            <?php if ($activeOrdersCount > 0): ?>
              <span class="badge bg-danger rounded-pill ms-auto"><?= (int)$activeOrdersCount ?></span>
            <?php endif; ?>
          </a>
        </li>

        <li class="admin-nav-item">
          <a href="<?= KITCHEN_URL ?>/index.php" class="admin-nav-link" target="_blank">
            <i class="fa-solid fa-fire-burner"></i>
            <span>Kitchen KDS Portal</span>
            <span class="badge bg-warning text-dark rounded-pill ms-auto">LIVE</span>
          </a>
        </li>

        <li class="admin-nav-item">
          <a href="<?= ADMIN_URL ?>/categories.php" class="admin-nav-link <?= ($activePage === 'categories') ? 'active' : '' ?>">
            <i class="bi bi-tags"></i>
            <span>Food Categories</span>
          </a>
        </li>

        <li class="admin-nav-item">
          <a href="<?= ADMIN_URL ?>/dishes.php" class="admin-nav-link <?= ($activePage === 'dishes') ? 'active' : '' ?>">
            <i class="fa-solid fa-bowl-food"></i>
            <span>Dish Catalog & Stock</span>
          </a>
        </li>

        <li class="admin-nav-item">
          <a href="<?= ADMIN_URL ?>/users.php" class="admin-nav-link <?= ($activePage === 'users') ? 'active' : '' ?>">
            <i class="bi bi-people"></i>
            <span>User & Staff RBAC</span>
          </a>
        </li>

        <li class="admin-nav-item">
          <a href="<?= ADMIN_URL ?>/reports.php" class="admin-nav-link <?= ($activePage === 'reports') ? 'active' : '' ?>">
            <i class="bi bi-graph-up-arrow"></i>
            <span>Executive BI Reports</span>
          </a>
        </li>

        <li class="admin-nav-item mt-3 pt-3 border-top border-secondary border-opacity-25">
          <a href="<?= CUSTOMER_URL ?>/index.php" class="admin-nav-link text-mint">
            <i class="bi bi-shop"></i>
            <span>Customer Storefront</span>
          </a>
        </li>

        <li class="admin-nav-item">
          <a href="<?= DELIVERY_URL ?>/index.php" class="admin-nav-link text-info">
            <i class="bi bi-bicycle"></i>
            <span>Delivery Fleet App</span>
          </a>
        </li>

        <li class="admin-nav-item">
          <a href="<?= BASE_URL ?>/database/seed.php" class="admin-nav-link text-white-50">
            <i class="bi bi-database-check"></i>
            <span>Database Seeder</span>
          </a>
        </li>

        <li class="admin-nav-item">
          <a href="<?= ADMIN_URL ?>/logout.php" class="admin-nav-link text-danger">
            <i class="bi bi-box-arrow-right"></i>
            <span>Sign Out</span>
          </a>
        </li>
      </ul>

      <!-- Bottom Admin User Tag -->
      <div class="p-3 mt-auto border-top border-secondary border-opacity-25 d-flex align-items-center gap-2">
        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.8rem; background: #2E6F40 !important;">
          <?= strtoupper(substr($currentUser['name'] ?? 'A', 0, 1)) ?>
        </div>
        <div class="overflow-hidden">
          <div class="text-white small fw-bold text-truncate"><?= e($currentUser['name'] ?? 'Administrator') ?></div>
          <div class="text-white-50" style="font-size: 0.65rem;"><?= e($currentUser['role_name'] ?? 'ADMIN') ?></div>
        </div>
      </div>
    </aside>

    <!-- =======================================================================
         MAIN ADMIN CONTENT CONTAINER
         ======================================================================= -->
    <div class="admin-main">
      
      <!-- Top Bar -->
      <header class="admin-topbar d-flex align-items-center justify-content-between px-4 py-3 bg-white border-bottom shadow-sm">
        <div class="d-flex align-items-center gap-3">
          <button class="btn btn-light d-lg-none" type="button" id="adminSidebarToggle">
            <i class="bi bi-list fs-5"></i>
          </button>
          <div class="fw-bold fs-5 text-dark font-outfit"><?= e($pageTitle) ?></div>
        </div>

        <div class="d-flex align-items-center gap-3">
          <a href="<?= CUSTOMER_URL ?>/index.php" class="btn btn-outline-success btn-sm rounded-pill px-3" target="_blank">
            <i class="bi bi-box-arrow-up-right me-1"></i> Live Storefront
          </a>

          <div class="dropdown">
            <button class="btn btn-light btn-sm rounded-circle d-flex align-items-center justify-content-center p-2" type="button" data-bs-toggle="dropdown">
              <i class="bi bi-bell fs-5 text-muted"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 small">
              <li class="dropdown-header fw-bold">Live Kitchen Notifications</li>
              <li><a class="dropdown-item" href="<?= ADMIN_URL ?>/orders.php"><?= (int)$activeOrdersCount ?> orders currently in cooking stage</a></li>
            </ul>
          </div>
        </div>
      </header>

      <!-- Main Body Container -->
      <div class="admin-content p-4">
        <?php display_flash(); ?>
