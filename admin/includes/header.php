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

// Enforce strict Role-Based Access Control: Administrator privileges required (Role 1: ADMIN)
if (!str_contains($_SERVER['SCRIPT_NAME'], 'login.php')) {
    require_admin();
}

// Live Operations counts
$liveOrdersCount = (int)(db_fetch_val("
    SELECT COUNT(*) FROM `orders` 
    WHERE `order_status` IN ('PLACED', 'CONFIRMED', 'COOKING', 'READY', 'OUT_FOR_DELIVERY')
") ?: 0);

$preparingOrdersCount = (int)(db_fetch_val("
    SELECT COUNT(*) FROM `orders` 
    WHERE `order_status` IN ('CONFIRMED', 'COOKING')
") ?: 0);

$deliveringOrdersCount = (int)(db_fetch_val("
    SELECT COUNT(*) FROM `orders` 
    WHERE `order_status` = 'OUT_FOR_DELIVERY'
") ?: 0);

$readyOrdersCount = (int)(db_fetch_val("
    SELECT COUNT(*) FROM `orders` 
    WHERE `order_status` = 'READY'
") ?: 0);

// Backward compatibility alias
$activeOrdersCount = $liveOrdersCount;
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

  <!-- Leaflet Map CSS & JS for Live Rider Telemetry -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
</head>
<body>

  <div class="admin-layout">
    
    <!-- Mobile Sidebar Backdrop -->
    <div class="admin-sidebar-backdrop" id="adminSidebarBackdrop"></div>
    
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
        <li class="m3-nav-subheader">Operations Hub</li>
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
            <?php if ($liveOrdersCount > 0): ?>
              <span class="badge bg-danger rounded-pill ms-auto" title="<?= (int)$preparingOrdersCount ?> preparing, <?= (int)$deliveringOrdersCount ?> delivering">
                <?= (int)$liveOrdersCount ?>
              </span>
            <?php endif; ?>
          </a>
        </li>

        <li class="admin-nav-item">
          <a href="<?= ADMIN_URL ?>/kitchen-view.php" class="admin-nav-link <?= ($activePage === 'kitchen_view') ? 'active' : '' ?>">
            <i class="fa-solid fa-users-gear text-warning"></i>
            <span>Kitchen View</span>
            <span class="badge bg-warning text-dark rounded-pill ms-auto" style="font-size:0.65rem;">Staff & Salary</span>
          </a>
        </li>

        <li class="admin-nav-item">
          <a href="<?= ADMIN_URL ?>/delivery-view.php" class="admin-nav-link <?= ($activePage === 'delivery_view') ? 'active' : '' ?>">
            <i class="bi bi-bicycle text-info"></i>
            <span>Delivery View</span>
            <span class="badge bg-info text-dark rounded-pill ms-auto" style="font-size:0.65rem;">Fleet & Salary</span>
          </a>
        </li>

        <li class="m3-nav-subheader">Commerce & Finance</li>
        <li class="admin-nav-item">
          <a href="<?= ADMIN_URL ?>/financial-transactions.php" class="admin-nav-link <?= ($activePage === 'finance') ? 'active' : '' ?>">
            <i class="bi bi-cash-coin text-success"></i>
            <span>Money & COD Ledger</span>
          </a>
        </li>

        <li class="admin-nav-item">
          <a href="<?= ADMIN_URL ?>/dishes.php" class="admin-nav-link <?= ($activePage === 'dishes') ? 'active' : '' ?>">
            <i class="fa-solid fa-bowl-food"></i>
            <span>Dishes & Stock</span>
          </a>
        </li>

        <li class="admin-nav-item">
          <a href="<?= ADMIN_URL ?>/categories.php" class="admin-nav-link <?= ($activePage === 'categories') ? 'active' : '' ?>">
            <i class="bi bi-tags"></i>
            <span>Food Categories</span>
          </a>
        </li>

        <li class="m3-nav-subheader">Insights & Support</li>
        <li class="admin-nav-item">
          <a href="<?= ADMIN_URL ?>/reports.php" class="admin-nav-link <?= ($activePage === 'reports') ? 'active' : '' ?>">
            <i class="bi bi-graph-up-arrow"></i>
            <span>Executive BI Reports</span>
          </a>
        </li>

        <li class="admin-nav-item">
          <a href="<?= ADMIN_URL ?>/users.php" class="admin-nav-link <?= ($activePage === 'users') ? 'active' : '' ?>">
            <i class="bi bi-people"></i>
            <span>User & Staff RBAC</span>
          </a>
        </li>

        <li class="admin-nav-item">
          <a href="<?= ADMIN_URL ?>/audit-logs.php" class="admin-nav-link <?= ($activePage === 'audit_logs') ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i>
            <span>Order Audit Trail</span>
          </a>
        </li>

        <li class="admin-nav-item">
          <a href="<?= ADMIN_URL ?>/reviews.php" class="admin-nav-link <?= ($activePage === 'reviews') ? 'active' : '' ?>">
            <i class="bi bi-star-fill text-warning"></i>
            <span>Food Reviews ⭐</span>
          </a>
        </li>

        <li class="admin-nav-item">
          <a href="<?= ADMIN_URL ?>/faqs.php" class="admin-nav-link <?= ($activePage === 'faqs') ? 'active' : '' ?>">
            <i class="bi bi-question-diamond"></i>
            <span>Dynamic FAQs Desk</span>
          </a>
        </li>

        <li class="admin-nav-item mt-3 pt-3 border-top border-secondary border-opacity-25">
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
          <button class="btn btn-light d-lg-none" type="button" id="adminSidebarToggle" aria-label="Toggle Navigation">
            <i class="bi bi-list fs-5"></i>
          </button>
          <div class="fw-bold fs-5 text-dark font-outfit"><?= e($pageTitle) ?></div>
        </div>

        <div class="d-flex align-items-center gap-3">
          <!-- Multi-Actor Session Scope Pill -->
          <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill small d-none d-sm-inline-flex align-items-center gap-1 font-monospace" title="Isolated Session: ZIPPY_ADMIN_SESS">
            <i class="bi bi-shield-lock-fill"></i> Admin Portal Session
          </span>

          <!-- Live Store Operations Status Indicator -->
          <div class="d-none d-md-flex align-items-center gap-2 px-3 py-1 rounded-pill small fw-bold" style="background:#EDFBF2; color:#2E6F40; border:1px solid #CEFFDC;">
            <span class="live-pulse-dot green"></span>
            <span>Store Open & Live</span>
          </div>

          <!-- Live Operations Alerts Dropdown -->
          <div class="dropdown">
            <button class="btn btn-light btn-sm rounded-circle d-flex align-items-center justify-content-center p-2 position-relative" type="button" data-bs-toggle="dropdown" title="Live Operations Alerts">
              <i class="bi bi-bell fs-5 text-muted"></i>
              <?php if ($liveOrdersCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle">
                  <span class="visually-hidden">Active orders</span>
                </span>
              <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 small p-2" style="min-width: 270px;">
              <li class="dropdown-header fw-bold text-dark border-bottom pb-2 mb-2 d-flex align-items-center justify-content-between">
                <span><i class="bi bi-broadcast text-danger me-1"></i> Live Operations</span>
                <span class="badge bg-danger rounded-pill"><?= (int)$liveOrdersCount ?> Active</span>
              </li>
              <li>
                <a class="dropdown-item rounded-3 py-2 d-flex align-items-center justify-content-between" href="<?= ADMIN_URL ?>/orders.php?status=COOKING">
                  <span class="d-flex align-items-center gap-2">
                    <span class="p-1 rounded-circle bg-warning bg-opacity-25 text-warning"><i class="bi bi-fire"></i></span>
                    <span>Being Prepared</span>
                  </span>
                  <span class="badge bg-warning text-dark rounded-pill"><?= (int)$preparingOrdersCount ?></span>
                </a>
              </li>
              <li>
                <a class="dropdown-item rounded-3 py-2 d-flex align-items-center justify-content-between" href="<?= ADMIN_URL ?>/orders.php?status=OUT_FOR_DELIVERY">
                  <span class="d-flex align-items-center gap-2">
                    <span class="p-1 rounded-circle bg-primary bg-opacity-25 text-primary"><i class="bi bi-bicycle"></i></span>
                    <span>Out for Delivery</span>
                  </span>
                  <span class="badge bg-primary text-white rounded-pill"><?= (int)$deliveringOrdersCount ?></span>
                </a>
              </li>
              <?php if ($readyOrdersCount > 0): ?>
              <li>
                <a class="dropdown-item rounded-3 py-2 d-flex align-items-center justify-content-between" href="<?= ADMIN_URL ?>/orders.php?status=READY">
                  <span class="d-flex align-items-center gap-2">
                    <span class="p-1 rounded-circle bg-success bg-opacity-25 text-success"><i class="bi bi-box-seam"></i></span>
                    <span>Ready for Dispatch</span>
                  </span>
                  <span class="badge bg-success text-white rounded-pill"><?= (int)$readyOrdersCount ?></span>
                </a>
              </li>
              <?php endif; ?>
              <li class="border-top mt-2 pt-2">
                <a class="dropdown-item rounded-3 text-center fw-bold text-success py-2" href="<?= ADMIN_URL ?>/orders.php">
                  Manage Live Orders Hub &rarr;
                </a>
              </li>
            </ul>
          </div>
        </div>
      </header>

      <!-- Main Body Container -->
      <div class="admin-content p-4">
        <?php display_flash(); ?>
