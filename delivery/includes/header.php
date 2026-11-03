<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Delivery Partner Fleet App - Zippy Food Network</title>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5.3.3 & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  
  <!-- Leaflet Map CSS & JS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  
  <!-- Custom Figma Lush Forest Palette Stylesheet -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">

  <style>
    :root {
      --rider-forest: #2E6F40;
      --rider-pine: #243E2C;
      --rider-mint: #CEFFDC;
      --rider-mint-soft: #EDFBF2;
      --rider-surface: #FFFFFF;
      --rider-bg: #F5F8F6;
      --rider-border: #E1ECE5;
    }
    body {
      background-color: var(--rider-bg);
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: #1C2B20;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .rider-top-navbar {
      background: #FFFFFF;
      border-bottom: 2px solid var(--rider-forest);
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
      position: sticky;
      top: 0;
      z-index: 1020;
    }
    .brand-fleet-logo {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      color: var(--rider-pine);
    }
    .fleet-icon-badge {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      background: var(--rider-forest);
      color: #FFFFFF;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.15rem;
    }
    .rider-nav-tabs {
      display: flex;
      gap: 8px;
      background: #EBF3EE;
      padding: 4px;
      border-radius: 50px;
      margin: 12px 0 20px 0;
    }
    .rider-nav-tab-btn {
      flex: 1;
      border: none;
      background: transparent;
      padding: 10px 16px;
      border-radius: 50px;
      font-size: 0.88rem;
      font-weight: 700;
      color: var(--rider-pine);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.2s ease;
      cursor: pointer;
    }
    .rider-nav-tab-btn.active {
      background: #FFFFFF;
      color: var(--rider-forest);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }
    .duty-card {
      background: #FFFFFF;
      border-radius: 18px;
      padding: 16px 20px;
      border: 1px solid var(--rider-border);
      box-shadow: 0 2px 8px rgba(0,0,0,0.03);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }
    .active-trip-card {
      background: #FFFFFF;
      border-radius: 20px;
      border: 2px solid var(--rider-forest);
      box-shadow: 0 6px 20px rgba(46, 111, 64, 0.08);
      overflow: hidden;
      position: relative;
    }
    .active-trip-header {
      background: linear-gradient(135deg, #243E2C 0%, #2E6F40 100%);
      color: #FFFFFF;
      padding: 16px 20px;
    }
    .step-timeline-container {
      display: flex;
      justify-content: space-between;
      position: relative;
      margin: 20px 10px 24px 10px;
    }
    .step-timeline-container::before {
      content: '';
      position: absolute;
      top: 15px;
      left: 20px;
      right: 20px;
      height: 3px;
      background: #E2ECE5;
      z-index: 1;
    }
    .step-timeline-progress {
      position: absolute;
      top: 15px;
      left: 20px;
      height: 3px;
      background: var(--rider-forest);
      z-index: 2;
    }
    .step-node {
      position: relative;
      z-index: 3;
      text-align: center;
      flex: 1;
    }
    .step-circle {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #FFFFFF;
      border: 3px solid #CBD5E1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
      font-weight: 800;
      color: #64748B;
      transition: all 0.3s ease;
    }
    .step-node.completed .step-circle {
      background: var(--rider-forest);
      border-color: var(--rider-forest);
      color: #FFFFFF;
    }
    .step-node.active .step-circle {
      background: #FFFFFF;
      border-color: var(--rider-forest);
      color: var(--rider-forest);
      box-shadow: 0 0 0 4px var(--rider-mint);
    }
    .step-title {
      font-size: 0.72rem;
      font-weight: 700;
      color: #64748B;
      margin-top: 6px;
      display: block;
    }
    .step-node.completed .step-title,
    .step-node.active .step-title {
      color: var(--rider-pine);
    }
    .history-order-card {
      background: #FFFFFF;
      border-radius: 16px;
      border: 1px solid var(--rider-border);
      padding: 16px 20px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.02);
      transition: all 0.2s ease;
    }
    .history-order-card:hover {
      border-color: var(--rider-forest);
      box-shadow: 0 4px 14px rgba(46, 111, 64, 0.06);
    }
    .btn-rider-primary {
      background-color: var(--rider-forest);
      color: #FFFFFF;
      font-weight: 700;
      border: none;
      border-radius: 50px;
      transition: all 0.2s ease;
    }
    .btn-rider-primary:hover, .btn-rider-primary:focus {
      background-color: #235933;
      color: #FFFFFF;
      box-shadow: 0 4px 12px rgba(46, 111, 64, 0.25);
    }
    .pulse-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: #10B981;
      display: inline-block;
      box-shadow: 0 0 0 rgba(16, 185, 129, 0.4);
      animation: pulseRider 2s infinite;
    }
    @keyframes pulseRider {
      0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
      70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
      100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    @keyframes syncRotate {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    .sync-spinning {
      animation: syncRotate 0.8s linear infinite;
      display: inline-block;
    }
    .toast-ready-food {
      position: fixed;
      top: 70px;
      right: 20px;
      z-index: 1060;
      animation: slideInDown 0.4s ease-out;
    }
    @keyframes slideInDown {
      from { transform: translateY(-20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    /* Mobile Ergonomics: Bottom Sticky Navigation Bar */
    .rider-bottom-nav {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      height: 64px;
      background: #FFFFFF;
      border-top: 1px solid var(--rider-border);
      display: flex;
      justify-content: space-around;
      align-items: center;
      z-index: 1040;
      box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.05);
      padding: 4px 10px;
    }
    .bottom-nav-item {
      background: transparent;
      border: none;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: #64748B;
      font-size: 0.72rem;
      font-weight: 700;
      position: relative;
      padding: 6px 16px;
      cursor: pointer;
      border-radius: 12px;
      transition: all 0.2s ease;
      text-decoration: none;
    }
    .bottom-nav-item:hover, .bottom-nav-item.active {
      color: var(--rider-forest);
      background: rgba(46, 111, 64, 0.06);
    }
    .bottom-nav-item .bnav-badge {
      position: absolute;
      top: 2px;
      right: 10px;
      font-size: 0.65rem;
      padding: 2px 5px;
    }
    @media (max-width: 767.98px) {
      body {
        padding-bottom: 74px !important;
      }
    }

    /* High-Contrast / Night Mode for Outdoor Visibility */
    body.rider-dark-theme {
      --rider-bg: #101913;
      --rider-surface: #18251C;
      --rider-border: #2A3E30;
      --rider-pine: #CEFFDC;
      color: #E2ECE5;
      background-color: var(--rider-bg);
    }
    body.rider-dark-theme .rider-top-navbar,
    body.rider-dark-theme .duty-card,
    body.rider-dark-theme .card,
    body.rider-dark-theme .modal-content,
    body.rider-dark-theme .history-order-card,
    body.rider-dark-theme .rider-bottom-nav {
      background-color: var(--rider-surface) !important;
      color: #E2ECE5 !important;
      border-color: var(--rider-border) !important;
    }
    body.rider-dark-theme .text-dark,
    body.rider-dark-theme h1, body.rider-dark-theme h2,
    body.rider-dark-theme h3, body.rider-dark-theme h4,
    body.rider-dark-theme h5, body.rider-dark-theme h6 {
      color: #F1F7F3 !important;
    }
    body.rider-dark-theme .bg-white,
    body.rider-dark-theme .bg-light {
      background-color: #1F3325 !important;
      color: #E2ECE5 !important;
    }
    body.rider-dark-theme .rider-nav-tabs {
      background: #17271D;
    }
    body.rider-dark-theme .rider-nav-tab-btn {
      color: #A2C0AA;
    }
    body.rider-dark-theme .rider-nav-tab-btn.active {
      background: #1F3325;
      color: #CEFFDC;
    }
  </style>
</head>
<body class="delivery-partner-app">

  <!-- =========================================================================
       1. TOPBAR / HEADER
       ========================================================================= -->
  <header class="rider-top-navbar" id="riderTopNavbar">
    <div class="container-xl py-2">
      <div class="d-flex align-items-center justify-content-between">
        
        <!-- Fleet Brand -->
        <a href="<?= DELIVERY_URL ?>/index.php" class="brand-fleet-logo">
          <div class="fleet-icon-badge">
            <i class="fa-solid fa-bicycle"></i>
          </div>
          <div>
            <div class="fw-bold fs-5 text-pine mb-0" style="line-height: 1.1; font-family:'Outfit',sans-serif;">zippy<span style="color:#68BA7F;">.</span></div>
            <div class="small fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.5px;">FLEET PARTNER</div>
          </div>
        </a>

        <!-- Rider Profile & Shortcuts -->
        <div class="d-flex align-items-center gap-2">
          
          <!-- Auto Sync Indicator (similar to KDS) -->
          <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 d-flex align-items-center gap-2 font-monospace" id="btnRiderSyncToggle" onclick="toggleRiderAutoSync()" title="Auto Sync with Kitchen Dispatch Server (Click to sync now)">
            <i id="riderSyncIcon" class="bi bi-arrow-clockwise text-success"></i>
            <span id="riderSyncText" class="fw-bold" style="font-size: 0.78rem;">SYNC (5s)</span>
          </button>

          <!-- High-Contrast Night Mode Toggle -->
          <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-1 d-flex align-items-center gap-1" id="themeToggleBtn" onclick="toggleRiderTheme()" title="Toggle High-Contrast / Night Riding Mode">
            <i class="bi bi-moon-stars" id="themeToggleIcon"></i>
            <span class="d-none d-lg-inline small">Night Mode</span>
          </button>

          <!-- Session Scope Pill -->
          <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill small d-none d-md-inline-flex align-items-center gap-1 font-monospace" title="Authenticated Fleet Partner Session">
            <i class="bi bi-shield-check"></i> Fleet Verified
          </span>

          <div class="d-none d-sm-flex align-items-center gap-2 bg-light px-3 py-1 rounded-pill border">
            <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold" style="width: 26px; height: 26px; font-size: 0.75rem; background-color: var(--rider-forest) !important;">
              <?= strtoupper(substr($currentUser['name'] ?? 'R', 0, 1)) ?>
            </div>
            <span class="fw-bold small text-dark"><?= e($currentUser['name'] ?? 'Suresh Kumar') ?></span>
            <span class="badge bg-warning text-dark small"><i class="bi bi-star-fill me-1"></i>4.9</span>
          </div>

          <a href="<?= DELIVERY_URL ?>/logout.php" class="btn btn-sm btn-outline-danger rounded-pill fw-bold px-3 d-flex align-items-center gap-1" title="Log out from fleet account">
            <i class="bi bi-box-arrow-right"></i>
            <span class="d-none d-md-inline">Logout</span>
          </a>

        </div>

      </div>
    </div>
  </header>

  <!-- =========================================================================
       2. MAIN RIDER WORKSPACE
       ========================================================================= -->
  <main class="container-xl py-3" style="max-width: 920px;">

    <?php display_flash(); ?>

    <!-- A. ONLINE / OFFLINE DUTY BAR (UC-14) -->
