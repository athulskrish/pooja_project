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
          
          <div class="d-none d-sm-flex align-items-center gap-2 bg-light px-3 py-1 rounded-pill border">
            <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold" style="width: 26px; height: 26px; font-size: 0.75rem; background-color: var(--rider-forest) !important;">
              <?= strtoupper(substr($currentUser['name'] ?? 'R', 0, 1)) ?>
            </div>
            <span class="fw-bold small text-dark"><?= e($currentUser['name'] ?? 'Suresh Kumar') ?></span>
            <span class="badge bg-warning text-dark small"><i class="bi bi-star-fill me-1"></i>4.9</span>
          </div>

          <a href="<?= BASE_URL ?>/index.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-semibold">
            <i class="bi bi-grid-fill me-1"></i> Portals
          </a>

          <a href="<?= CUSTOMER_URL ?>/index.php" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold">
            <i class="bi bi-shop me-1"></i> Store
          </a>

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
