<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kitchen Atelier & KDS | Zippy Operations</title>

  <!-- Google Fonts: Plus Jakarta Sans & Outfit & Space Grotesk -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

  <!-- Bootstrap 5.3.3 & FontAwesome & Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

  <style>
    /* ==========================================================================
       CLASSY BLACK & WHITE MONOCHROME DESIGN SYSTEM
       High-Contrast Haute Cuisine Digital Expeditor
       ========================================================================== */
    :root {
      --noir-black: #000000;
      --noir-charcoal: #121212;
      --noir-card-border: #E4E4E7;
      --noir-border-strong: #000000;
      --noir-muted: #71717A;
      --noir-muted-light: #A1A1AA;
      --noir-surface: #FFFFFF;
      --noir-canvas: #FAFAFA;
      --noir-subtle: #F4F4F5;
      --noir-subtle-hover: #EBEBEF;
      --noir-badge-bg: #F4F4F5;
      --noir-badge-border: #E4E4E7;
      --noir-shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
      --noir-shadow-md: 0 8px 24px rgba(0, 0, 0, 0.06);
      --noir-shadow-lg: 0 16px 36px rgba(0, 0, 0, 0.09);
    }

    body {
      background-color: var(--noir-canvas);
      color: var(--noir-charcoal);
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-height: 100vh;
      overflow-x: hidden;
      letter-spacing: -0.01em;
    }

    .font-outfit { font-family: 'Outfit', sans-serif; }
    .font-mono { font-family: 'Space Grotesk', monospace; }

    /* Top Navigation Bar */
    .kds-navbar {
      background: #FFFFFF;
      border-bottom: 1px solid #E4E4E7;
      padding: 14px 28px;
      position: sticky;
      top: 0;
      z-index: 1030;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    }

    .brand-mark {
      font-family: 'Outfit', sans-serif;
      font-weight: 900;
      font-size: 1.35rem;
      letter-spacing: -0.03em;
      color: #000000;
      text-transform: uppercase;
      display: inline-flex;
      align-items: center;
      gap: 10px;
    }

    .brand-tag {
      background: #000000;
      color: #FFFFFF;
      font-size: 0.68rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      padding: 4px 10px;
      border-radius: 4px;
    }

    .live-dot {
      width: 7px;
      height: 7px;
      background-color: #000000;
      border-radius: 50%;
      display: inline-block;
      animation: pulseDot 1.8s infinite;
    }

    @keyframes pulseDot {
      0% { transform: scale(0.9); opacity: 1; }
      50% { transform: scale(1.4); opacity: 0.4; }
      100% { transform: scale(0.9); opacity: 1; }
    }

    /* KPI Summary Cards */
    .kpi-card {
      background: #FFFFFF;
      border: 1px solid var(--noir-card-border);
      border-radius: 14px;
      padding: 20px 24px;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: var(--noir-shadow-sm);
    }
    .kpi-card:hover {
      border-color: #000000;
      transform: translateY(-2px);
      box-shadow: var(--noir-shadow-md);
    }

    .kpi-number {
      font-family: 'Outfit', sans-serif;
      font-size: 2.5rem;
      font-weight: 800;
      line-height: 1;
      color: #000000;
      letter-spacing: -0.03em;
    }

    .kpi-label {
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--noir-muted);
    }

    /* Tab Controls */
    .nav-pills-kds .nav-link {
      background: #FFFFFF;
      color: var(--noir-muted);
      border: 1px solid var(--noir-card-border);
      border-radius: 100px;
      font-size: 0.85rem;
      font-weight: 600;
      padding: 8px 20px;
      transition: all 0.18s ease;
    }
    .nav-pills-kds .nav-link:hover {
      color: #000000;
      border-color: #A1A1AA;
    }
    .nav-pills-kds .nav-link.active {
      background: #000000 !important;
      color: #FFFFFF !important;
      border-color: #000000 !important;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    /* Swimlane Columns */
    .swimlane-col {
      background: #F4F4F6;
      border: 1px solid var(--noir-card-border);
      border-radius: 18px;
      padding: 22px;
      height: 100%;
      min-height: calc(100vh - 290px);
      display: flex;
      flex-direction: column;
    }

    .swimlane-header {
      padding-bottom: 16px;
      margin-bottom: 18px;
      border-bottom: 1px solid #E4E4E7;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .swimlane-title {
      font-family: 'Outfit', sans-serif;
      font-weight: 700;
      font-size: 0.95rem;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #000000;
      margin-bottom: 0;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .swimlane-counter {
      background: #000000;
      color: #FFFFFF;
      font-family: 'Space Grotesk', monospace;
      font-size: 0.8rem;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 100px;
    }

    /* Ticket Docket Card (Classy Michelin Docket) */
    .ticket-card {
      background: #FFFFFF;
      border: 1px solid var(--noir-card-border);
      border-radius: 16px;
      padding: 20px;
      margin-bottom: 16px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
      transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
      position: relative;
    }
    .ticket-card:hover {
      border-color: #000000;
      transform: translateY(-3px);
      box-shadow: var(--noir-shadow-md);
    }

    .ticket-card.card-lane-new {
      border-top: 4px solid #000000;
    }
    .ticket-card.card-lane-cooking {
      border-top: 4px solid #3F3F46;
    }
    .ticket-card.card-lane-ready {
      border-top: 4px solid #18181B;
    }

    .ticket-tag-pill {
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      padding: 4px 10px;
      border-radius: 100px;
      background: #F4F4F5;
      color: #18181B;
      border: 1px solid #E4E4E7;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    .ticket-tag-dark {
      background: #000000;
      color: #FFFFFF;
      border-color: #000000;
    }

    .ticket-tag-outline {
      background: #FFFFFF;
      color: #000000;
      border: 1px solid #000000;
    }

    .ticket-items-box {
      background: #FAFAFA;
      border: 1px solid #F0F0F2;
      border-radius: 12px;
      padding: 14px;
      margin: 14px 0;
    }

    /* Minimalist Veg / Non-Veg Mark */
    .monochrome-veg {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 14px;
      height: 14px;
      border: 1.5px solid #000000;
      border-radius: 2px;
      padding: 1px;
    }
    .monochrome-veg::after {
      content: '';
      width: 6px;
      height: 6px;
      background-color: #000000;
      border-radius: 50%;
    }

    .monochrome-nonveg {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 14px;
      height: 14px;
      border: 1.5px solid #000000;
      border-radius: 2px;
      padding: 1px;
    }
    .monochrome-nonveg::after {
      content: '';
      width: 0;
      height: 0;
      border-left: 4px solid transparent;
      border-right: 4px solid transparent;
      border-bottom: 6px solid #000000;
    }

    /* Checklist interaction */
    .dish-checkbox {
      width: 17px;
      height: 17px;
      border: 1.5px solid #71717A;
      cursor: pointer;
      accent-color: #000000;
    }
    .dish-checkbox:checked + label {
      text-decoration: line-through;
      color: #A1A1AA;
    }

    /* Classy Primary Buttons */
    .btn-noir-primary {
      background: #000000;
      color: #FFFFFF;
      font-weight: 700;
      font-size: 0.88rem;
      letter-spacing: 0.02em;
      border: 1px solid #000000;
      border-radius: 100px;
      padding: 11px 22px;
      transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
    }
    .btn-noir-primary:hover {
      background: #27272A;
      color: #FFFFFF;
      border-color: #27272A;
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }
    .btn-noir-primary:active {
      transform: translateY(0);
    }

    .btn-noir-outline {
      background: #FFFFFF;
      color: #000000;
      font-weight: 700;
      font-size: 0.85rem;
      border: 1.5px solid #000000;
      border-radius: 100px;
      padding: 9px 18px;
      transition: all 0.18s ease;
    }
    .btn-noir-outline:hover {
      background: #000000;
      color: #FFFFFF;
    }

    .btn-kot-print {
      background: #FFFFFF;
      color: #18181B;
      border: 1px solid #E4E4E7;
      border-radius: 100px;
      width: 42px;
      height: 42px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: all 0.18s ease;
    }
    .btn-kot-print:hover {
      background: #000000;
      color: #FFFFFF;
      border-color: #000000;
    }

    /* Modal Styling */
    .modal-content-noir {
      background: #FFFFFF;
      color: #18181B;
      border: 1px solid #000000;
      border-radius: 20px;
      box-shadow: var(--noir-shadow-lg);
    }
    .modal-header-noir {
      border-bottom: 1px solid #E4E4E7;
      padding: 20px 24px;
    }
    .modal-footer-noir {
      border-top: 1px solid #E4E4E7;
      padding: 16px 24px;
    }

    /* Search Input */
    .search-input-noir {
      background: #FFFFFF;
      border: 1px solid var(--noir-card-border);
      color: #000000;
      border-radius: 100px;
      padding: 8px 18px;
      font-size: 0.85rem;
      transition: all 0.18s ease;
    }
    .search-input-noir:focus {
      border-color: #000000;
      outline: none;
      box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.08);
    }

    /* Instruction Box */
    .instruction-callout {
      background: #000000;
      color: #FFFFFF;
      border-radius: 8px;
      padding: 10px 14px;
      margin-bottom: 12px;
      font-size: 0.82rem;
      font-weight: 600;
      letter-spacing: 0.01em;
    }

    /* Rider Box */
    .rider-dispatch-box {
      background: #F8F9FA;
      border: 1px solid #E4E4E7;
      border-radius: 12px;
      padding: 14px;
      margin-bottom: 14px;
    }
  </style>
</head>
<body>

  <!-- Top Navigation Bar -->
  <header class="kds-navbar">
    <div class="container-fluid px-2 px-md-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
      
      <!-- Brand & Status -->
      <div class="d-flex align-items-center gap-3">
        <a href="<?= KITCHEN_URL ?>/index.php" class="text-decoration-none">
          <div class="brand-mark">
            <span>ZIPPY<span style="font-weight:300;">.</span></span>
            <span class="brand-tag">ATELIER KDS</span>
          </div>
        </a>

        <div class="d-none d-sm-flex align-items-center gap-2 px-3 py-1 rounded-pill" style="background:#F4F4F5; border:1px solid #E4E4E7;">
          <span class="live-dot"></span>
          <span class="small font-mono fw-bold" style="font-size: 0.72rem; letter-spacing:0.06em; color:#000000;">STATION ONLINE</span>
        </div>
      </div>

      <!-- Controls & Profile -->
      <div class="d-flex align-items-center gap-2 gap-md-3">
        
        <!-- Live Station Clock -->
        <div class="d-none d-lg-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-light border border-secondary-subtle small font-mono">
          <i class="bi bi-clock-history text-dark"></i>
          <span id="kdsLiveClock" class="fw-bold text-dark">--:--:--</span>
        </div>

        <!-- Audio Alert Chime Toggle -->
        <button id="btnAudioToggle" class="btn btn-sm btn-noir-outline rounded-pill px-3" onclick="toggleAudioChime()" title="Toggle Web Audio Bell Chime">
          <i id="audioIcon" class="bi bi-bell-fill me-1"></i> <span id="audioText">SOUND: ON</span>
        </button>

        <!-- Auto-Refresh Toggle -->
        <button id="btnRefreshToggle" class="btn btn-sm btn-noir-outline rounded-pill px-3" onclick="toggleAutoRefresh()" title="Pause or Resume automated sync">
          <i class="bi bi-arrow-clockwise me-1"></i> <span id="refreshText">SYNC (12s)</span>
        </button>

        <!-- 86-List (Inventory Availability) -->
        <button class="btn btn-sm btn-noir-primary px-3 rounded-pill" data-bs-toggle="modal" data-bs-target="#modal86List">
          <i class="fa-solid fa-boxes-stacked me-1"></i> 86-LIST
        </button>

        <!-- Chef Account Dropdown -->
        <div class="dropdown">
          <button class="btn btn-sm bg-white rounded-pill px-3 py-2 d-flex align-items-center gap-2 border" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-color:#E4E4E7 !important;">
            <div class="rounded-circle bg-black text-white d-inline-flex align-items-center justify-content-center" style="width:24px; height:24px; font-size:0.7rem; font-weight:800;">
              CS
            </div>
            <span class="small fw-bold text-dark d-none d-sm-inline"><?= e($currentUser['name'] ?? 'Chef Sanjeev') ?></span>
            <i class="bi bi-chevron-down small text-muted"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-lg py-2" style="background:#FFFFFF; border:1px solid #000000; border-radius:12px; min-width: 220px;">
            <li class="px-3 py-2 border-bottom">
              <div class="fw-bold text-dark small font-outfit"><?= e($currentUser['name']) ?></div>
              <div class="text-muted" style="font-size:0.75rem;"><?= e($currentUser['email']) ?></div>
            </li>
            <li><a class="dropdown-item text-dark small py-2" href="<?= CUSTOMER_URL ?>/index.php" target="_blank"><i class="bi bi-shop me-2"></i> Customer Storefront</a></li>
            <li><a class="dropdown-item text-dark small py-2" href="<?= DELIVERY_URL ?>/index.php" target="_blank"><i class="bi bi-bicycle me-2"></i> Delivery Fleet App</a></li>
            <?php if (is_admin()): ?>
              <li><a class="dropdown-item text-dark small py-2" href="<?= ADMIN_URL ?>/index.php"><i class="bi bi-speedometer2 me-2"></i> Admin Console</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger fw-semibold small py-2" href="<?= KITCHEN_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Chef Sign Off</a></li>
          </ul>
        </div>

      </div>

    </div>
  </header>

  <!-- Main Container -->
  <main class="container-fluid px-3 px-lg-4 py-4">

    <!-- Flash Notifications -->
    <div id="flashContainer">
      <?php display_flash(); ?>
    </div>

    <!-- Live Audio Notification Banner -->
    <div id="newOrderBanner" class="alert d-none align-items-center justify-content-between rounded-4 py-3 px-4 mb-4 shadow-sm" style="background: #000000; color:#FFFFFF; border:1px solid #000000;">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle bg-white text-black p-2 d-inline-flex align-items-center justify-content-center" style="width:38px; height:38px;">
          <i class="fa-solid fa-bell"></i>
        </div>
        <div>
          <h6 class="fw-bold mb-0 font-outfit" id="newOrderBannerTitle">NEW ORDER TICKETS RECEIVED</h6>
          <span class="small text-white-50" id="newOrderBannerDesc">Fresh customer orders placed. Review incoming queue below.</span>
        </div>
      </div>
      <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-4 fw-bold" onclick="dismissOrderBanner()">
        Acknowledge
      </button>
    </div>
