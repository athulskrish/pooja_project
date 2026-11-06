<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Kitchen Atelier & KDS | Zippy Operations</title>

  <!-- Google Fonts: Outfit & Plus Jakarta Sans & Space Grotesk -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

  <!-- Bootstrap 5.3.3 & FontAwesome & Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

  <style>
    /* ==========================================================================
       POOJA PROJECT - KITCHEN DISPLAY SYSTEM (KDS)
       Design System: Figma "Lush Forest" Palette & Material Design 3 (M3)
       Ergonomic, High-Legibility, Eye-Strain Free Culinary Workflow
       ========================================================================== */
    :root {
      /* Figma Lush Forest Core Palette */
      --lush-pine: #243E2C;
      --lush-forest: #2E6F40;
      --lush-forest-hover: #235933;
      --lush-sage: #68BA7F;
      --lush-mint: #CEFFDC;
      --lush-mint-soft: #EDFBF2;
      --lush-mint-glow: rgba(206, 255, 220, 0.45);
      
      /* Surfaces & Canvases */
      --lush-bg: #F7FAF8;
      --lush-card: #FFFFFF;
      --lush-card-alt: #FAFCFA;
      --lush-border: #E2ECE5;
      --lush-border-subtle: #EEF4F0;
      
      /* Typography */
      --lush-text-main: #1C2B20;
      --lush-text-muted: #576E5F;
      --lush-text-light: #8A9E91;

      /* Material 3 Elevation & Shadows */
      --elevation-0: none;
      --elevation-1: 0 1px 3px rgba(36, 62, 44, 0.05), 0 2px 6px rgba(36, 62, 44, 0.03);
      --elevation-2: 0 4px 14px rgba(36, 62, 44, 0.08), 0 1px 3px rgba(36, 62, 44, 0.04);
      --elevation-3: 0 10px 28px rgba(36, 62, 44, 0.11), 0 3px 8px rgba(36, 62, 44, 0.05);

      /* Semantic Status Colors (Gentle & Functional) */
      --status-incoming-bg: #E8F4FD;
      --status-incoming-text: #1565C0;
      --status-incoming-border: #BBDEFB;

      --status-cooking-bg: #FFF7ED;
      --status-cooking-text: #C2410C;
      --status-cooking-border: #FED7AA;

      --status-ready-bg: #ECFDF5;
      --status-ready-text: #047857;
      --status-ready-border: #A7F3D0;

      --status-urgent-bg: #FEF2F2;
      --status-urgent-text: #B91C1C;
      --status-urgent-border: #FECACA;

      --status-note-bg: #FEFCE8;
      --status-note-text: #854D0E;
      --status-note-border: #FEF08A;

      /* Dietary Standards */
      --food-veg: #229947;
      --food-nonveg: #D32F2F;

      /* Transitions */
      --motion-standard: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
      box-sizing: border-box;
    }

    body {
      background-color: var(--lush-bg);
      color: var(--lush-text-main);
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      min-height: 100vh;
      overflow-x: hidden;
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
    }

    .font-outfit { font-family: 'Outfit', sans-serif; }
    .font-mono { font-family: 'Space Grotesk', monospace; }

    /* Custom Scrollbars */
    ::-webkit-scrollbar {
      width: 7px;
      height: 7px;
    }
    ::-webkit-scrollbar-track {
      background: var(--lush-bg);
    }
    ::-webkit-scrollbar-thumb {
      background: #C8DCD0;
      border-radius: 6px;
    }
    ::-webkit-scrollbar-thumb:hover {
      background: var(--lush-sage);
    }

    /* ==========================================================================
       MATERIAL APP BAR (TOP NAVIGATION - SINGLE ROW, 52PX, ZERO WRAP)
       ========================================================================== */
    .kds-navbar {
      background: #FFFFFF;
      border-bottom: 1.5px solid var(--lush-border);
      padding: 0 16px;
      height: 52px;
      min-height: 52px;
      position: sticky;
      top: 0;
      z-index: 1030;
      box-shadow: 0 1px 6px rgba(36, 62, 44, 0.04);
      display: flex;
      align-items: center;
    }

    .kds-nav-container {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: nowrap;
    }

    .kds-brand-cluster {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-shrink: 0;
    }

    .brand-mark {
      font-family: 'Outfit', sans-serif;
      font-weight: 800;
      font-size: 1.25rem;
      letter-spacing: -0.02em;
      color: var(--lush-pine);
      display: inline-flex;
      align-items: center;
      gap: 6px;
      line-height: 1;
    }

    .brand-tag {
      background: var(--lush-forest);
      color: #FFFFFF;
      font-size: 0.62rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      padding: 2px 6px;
      border-radius: 4px;
      line-height: 1.2;
    }

    .live-dot {
      width: 7px;
      height: 7px;
      background-color: var(--food-veg);
      border-radius: 50%;
      display: inline-block;
      box-shadow: 0 0 0 2px rgba(34, 153, 71, 0.2);
      animation: pulseGreen 1.8s infinite;
      flex-shrink: 0;
    }

    @keyframes pulseGreen {
      0% { transform: scale(0.9); opacity: 1; box-shadow: 0 0 0 0 rgba(34, 153, 71, 0.5); }
      70% { transform: scale(1.2); opacity: 0.8; box-shadow: 0 0 0 6px rgba(34, 153, 71, 0); }
      100% { transform: scale(0.9); opacity: 1; box-shadow: 0 0 0 0 rgba(34, 153, 71, 0); }
    }

    /* Segmented Navigation Tab Cluster */
    .kds-tabs-cluster {
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      min-width: 0;
    }

    .nav-pills-kds {
      background: #F1F6F3;
      border: 1px solid var(--lush-border);
      border-radius: 100px;
      padding: 2px 3px;
      display: inline-flex;
      align-items: center;
      gap: 2px;
      flex-wrap: nowrap !important;
    }

    .nav-pills-kds .nav-link {
      background: transparent;
      color: var(--lush-text-muted);
      border: none;
      border-radius: 100px;
      font-size: 0.72rem;
      font-weight: 700;
      padding: 3px 8px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: var(--motion-standard);
      white-space: nowrap;
      cursor: pointer;
      line-height: 1.35;
      flex-shrink: 0;
    }
    .nav-pills-kds .nav-link:hover {
      color: var(--lush-forest);
      background: rgba(255, 255, 255, 0.7);
    }
    .nav-pills-kds .nav-link.active {
      background: #FFFFFF !important;
      color: var(--lush-forest) !important;
      box-shadow: 0 1px 4px rgba(36, 62, 44, 0.12);
    }
    .nav-pills-kds .nav-link.active .badge-count {
      background: var(--lush-mint-soft) !important;
      color: var(--lush-forest) !important;
    }

    .badge-count {
      background: #E2ECE5;
      color: var(--lush-pine);
      font-family: 'Space Grotesk', monospace;
      font-size: 0.68rem;
      font-weight: 800;
      padding: 1px 5px;
      border-radius: 100px;
      transition: var(--motion-standard);
    }

    /* Right Action Cluster */
    .kds-actions-cluster {
      display: flex;
      align-items: center;
      gap: 5px;
      flex-shrink: 0;
    }

    .kds-search-box {
      position: relative;
      width: 95px;
      transition: width 0.25s ease;
    }
    .kds-search-box:focus-within {
      width: 155px;
    }
    .kds-search-input {
      background: #F7FAF8;
      border: 1px solid var(--lush-border);
      border-radius: 100px;
      padding: 3px 8px 3px 26px;
      font-size: 0.74rem;
      color: var(--lush-text-main);
      width: 100%;
      height: 30px;
      transition: var(--motion-standard);
    }
    .kds-search-input:focus {
      background: #FFFFFF;
      border-color: var(--lush-forest);
      outline: none;
      box-shadow: 0 0 0 2px rgba(46, 111, 64, 0.12);
    }

    .btn-kds-icon {
      background: #FFFFFF;
      color: var(--lush-text-muted);
      border: 1px solid var(--lush-border);
      border-radius: 50%;
      width: 30px;
      height: 30px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.82rem;
      padding: 0;
      transition: var(--motion-standard);
      flex-shrink: 0;
    }
    .btn-kds-icon:hover {
      background: var(--lush-mint-soft);
      border-color: var(--lush-sage);
      color: var(--lush-forest);
      transform: translateY(-1px);
    }

    .btn-kds-86 {
      background: var(--lush-forest);
      color: #FFFFFF;
      border: none;
      border-radius: 100px;
      font-size: 0.72rem;
      font-weight: 800;
      padding: 4px 9px;
      height: 30px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: var(--motion-standard);
      box-shadow: 0 1px 4px rgba(46, 111, 64, 0.2);
      white-space: nowrap;
    }
      font-size: 0.76rem;
      font-weight: 800;
      padding: 5px 12px;
      height: 32px;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      transition: var(--motion-standard);
      box-shadow: 0 1px 4px rgba(46, 111, 64, 0.2);
      white-space: nowrap;
    }
    .btn-kds-86:hover {
      background: var(--lush-forest-hover);
      color: #FFFFFF;
      transform: translateY(-1px);
      box-shadow: 0 2px 8px rgba(46, 111, 64, 0.3);
    }

    /* Swimlanes full viewport */
    .swimlane-col {
      background: #F4F8F5;
      border: 1px solid var(--lush-border);
      border-radius: 16px;
      padding: 10px 12px;
      height: calc(100vh - 78px);
      min-height: 480px;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      box-shadow: 0 1px 4px rgba(36, 62, 44, 0.04);
    }

    .swimlane-header {
      padding: 4px 2px 10px;
      margin-bottom: 8px;
      border-bottom: 1.5px solid var(--lush-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
    }

    .swimlane-title {
      font-family: 'Outfit', sans-serif;
      font-weight: 800;
      font-size: 0.94rem;
      letter-spacing: 0.01em;
      color: var(--lush-pine);
      margin-bottom: 0;
      display: flex;
      align-items: center;
      gap: 7px;
    }

    .swimlane-counter {
      font-family: 'Space Grotesk', monospace;
      font-size: 0.78rem;
      font-weight: 800;
      padding: 2px 9px;
      border-radius: 100px;
    }

    .badge-lane-new {
      background: #EBF5FF;
      color: #1D4ED8;
      border: 1px solid #BFDBFE;
    }
    .badge-lane-cooking {
      background: #FEF3C7;
      color: #B45309;
      border: 1px solid #FDE68A;
    }
    .badge-lane-ready {
      background: var(--lush-mint-soft);
      color: var(--lush-forest);
      border: 1px solid var(--lush-border);
    }

    .swimlane-scroll-body {
      flex: 1;
      overflow-y: auto;
      padding-right: 3px;
      display: flex;
      flex-direction: column;
      gap: 9px;
      scrollbar-width: thin;
      scrollbar-color: #C8DCD0 transparent;
    }
    .swimlane-scroll-body::-webkit-scrollbar {
      width: 5px;
    }
    .swimlane-scroll-body::-webkit-scrollbar-thumb {
      background: #C8DCD0;
      border-radius: 4px;
    }

    /* ==========================================================================
       MATERIAL ORDER TICKET DOCKET CARD (COMPACT & SCANNABLE)
       ========================================================================== */
    .ticket-card {
      background: var(--lush-card);
      border: 1px solid var(--lush-border);
      border-radius: 12px;
      padding: 12px 13px;
      margin-bottom: 0;
      box-shadow: 0 1px 3px rgba(36, 62, 44, 0.05);
      transition: var(--motion-standard);
      position: relative;
      flex-shrink: 0;
    }
    .ticket-card:hover {
      border-color: var(--lush-sage);
      box-shadow: 0 3px 10px rgba(36, 62, 44, 0.08);
      transform: translateY(-1px);
    }

    .ticket-card.card-lane-new {
      border-left: 4.5px solid #2563EB;
      border-top: 1px solid var(--lush-border);
    }
    .ticket-card.card-lane-cooking {
      border-left: 4.5px solid #D97706;
      border-top: 1px solid var(--lush-border);
    }
    .ticket-card.card-lane-ready {
      border-left: 4.5px solid #16A34A;
      border-top: 1px solid var(--lush-border);
    }

    /* Compact Prep Note */
    .kds-card-note {
      background: #FEFCE8;
      border: 1px solid #FEF08A;
      color: #854D0E;
      border-radius: 8px;
      padding: 5px 9px;
      font-size: 0.77rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    /* Tag & Status Pills */
    .ticket-tag-pill {
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.03em;
      text-transform: uppercase;
      padding: 3px 8px;
      border-radius: 100px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .tag-wait-fresh {
      background: var(--lush-mint-soft);
      color: var(--lush-forest);
      border: 1px solid var(--lush-border);
    }
    .tag-wait-warm {
      background: #FEF3C7;
      color: #92400E;
      border: 1px solid #FDE68A;
    }
    .tag-wait-alert {
      background: var(--status-urgent-bg);
      color: var(--status-urgent-text);
      border: 1px solid var(--status-urgent-border);
      animation: pulseAlert 2.2s infinite;
    }
      background: #FEF3C7;
      color: #92400E;
      border: 1px solid #FDE68A;
    }
    .tag-wait-alert {
      background: var(--status-urgent-bg);
      color: var(--status-urgent-text);
      border: 1px solid var(--status-urgent-border);
      animation: pulseAlert 2.2s infinite;
    }

    @keyframes pulseAlert {
      0% { opacity: 1; }
      50% { opacity: 0.75; }
      100% { opacity: 1; }
    }

    .tag-priority-critical {
      background: #FEF2F2;
      color: #B91C1C;
      border: 1px solid #FECACA;
    }
    .tag-priority-high {
      background: #FFFBEB;
      color: #B45309;
      border: 1px solid #FDE68A;
    }
    .tag-priority-normal {
      background: var(--lush-mint-soft);
      color: var(--lush-pine);
      border: 1px solid var(--lush-border);
    }

    /* Attentive Customer Order Note */
    .chef-note-box {
      background: var(--status-note-bg);
      border: 1px solid var(--status-note-border);
      color: var(--status-note-text);
      border-radius: 10px;
      padding: 9px 12px;
      margin-bottom: 12px;
      font-size: 0.82rem;
      font-weight: 600;
      display: flex;
      align-items: flex-start;
      gap: 8px;
    }
    .chef-note-box i {
      font-size: 0.95rem;
      margin-top: 2px;
      color: #D97706;
    }

    /* Ticket Line Items */
    .ticket-items-box {
      background: var(--lush-bg);
      border: 1px solid var(--lush-border-subtle);
      border-radius: 14px;
      padding: 12px 14px;
      margin: 12px 0 14px;
    }

    .item-qty-pill {
      background: var(--lush-mint);
      color: var(--lush-pine);
      font-family: 'Space Grotesk', monospace;
      font-size: 0.78rem;
      font-weight: 700;
      padding: 2px 7px;
      border-radius: 6px;
      border: 1px solid rgba(46, 111, 64, 0.15);
    }

    /* Indian Culinary Veg & Non-Veg Icons */
    .badge-veg-icon {
      width: 15px;
      height: 15px;
      border: 1.5px solid var(--food-veg);
      border-radius: 3px;
      background: #FFFFFF;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .badge-veg-icon::after {
      content: '';
      width: 6px;
      height: 6px;
      background-color: var(--food-veg);
      border-radius: 50%;
    }

    .badge-nonveg-icon {
      width: 15px;
      height: 15px;
      border: 1.5px solid var(--food-nonveg);
      border-radius: 3px;
      background: #FFFFFF;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .badge-nonveg-icon::after {
      content: '';
      width: 0;
      height: 0;
      border-left: 3.5px solid transparent;
      border-right: 3.5px solid transparent;
      border-bottom: 6px solid var(--food-nonveg);
    }

    /* Checklist interaction */
    .dish-checkbox {
      width: 17px;
      height: 17px;
      border: 1.5px solid var(--lush-sage);
      border-radius: 4px;
      cursor: pointer;
      accent-color: var(--lush-forest);
      margin-top: 0;
    }
    .dish-checkbox:checked + label {
      text-decoration: line-through;
      color: var(--lush-text-light) !important;
      opacity: 0.6;
    }

    /* Docket Primary Action Buttons */
    .btn-action-primary {
      background: var(--lush-forest);
      color: #FFFFFF;
      font-weight: 700;
      font-size: 0.9rem;
      letter-spacing: 0.01em;
      border: none;
      border-radius: 12px;
      padding: 11px 18px;
      min-height: 46px;
      transition: var(--motion-standard);
      box-shadow: 0 2px 8px rgba(46, 111, 64, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
    .btn-action-primary:hover {
      background: var(--lush-forest-hover);
      color: #FFFFFF;
      transform: translateY(-1px);
      box-shadow: 0 4px 14px rgba(46, 111, 64, 0.3);
    }
    .btn-action-primary:active {
      transform: translateY(0);
    }

    .btn-action-ready {
      background: #059669;
      color: #FFFFFF;
      font-weight: 700;
      font-size: 0.9rem;
      border: none;
      border-radius: 12px;
      padding: 11px 18px;
      min-height: 46px;
      transition: var(--motion-standard);
      box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
    .btn-action-ready:hover {
      background: #047857;
      color: #FFFFFF;
      transform: translateY(-1px);
      box-shadow: 0 4px 14px rgba(5, 150, 105, 0.3);
    }

    .btn-action-dispatch {
      background: var(--lush-pine);
      color: #FFFFFF;
      font-weight: 700;
      font-size: 0.9rem;
      border: none;
      border-radius: 12px;
      padding: 11px 18px;
      min-height: 46px;
      transition: var(--motion-standard);
      box-shadow: 0 2px 8px rgba(36, 62, 44, 0.25);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
    .btn-action-dispatch:hover {
      background: #17281C;
      color: #FFFFFF;
      transform: translateY(-1px);
      box-shadow: 0 4px 14px rgba(36, 62, 44, 0.35);
    }

    .btn-kot-print {
      background: #FFFFFF;
      color: var(--lush-text-main);
      border: 1.5px solid var(--lush-border);
      border-radius: 12px;
      width: 46px;
      height: 46px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
      transition: var(--motion-standard);
      flex-shrink: 0;
    }
    .btn-kot-print:hover {
      background: var(--lush-mint-soft);
      border-color: var(--lush-sage);
      color: var(--lush-forest);
      transform: translateY(-1px);
    }

    /* Modal Styling */
    .modal-content-lush {
      background: #FFFFFF;
      color: var(--lush-text-main);
      border: 1px solid var(--lush-border);
      border-radius: 22px;
      box-shadow: var(--elevation-3);
      overflow: hidden;
    }
    .modal-header-lush {
      background: var(--lush-mint-soft);
      border-bottom: 1px solid var(--lush-border);
      padding: 18px 22px;
    }
    .modal-footer-lush {
      border-top: 1px solid var(--lush-border);
      padding: 14px 22px;
    }

    /* Search Input */
    .search-input-lush {
      background: #FFFFFF;
      border: 1.5px solid var(--lush-border);
      color: var(--lush-text-main);
      border-radius: 100px;
      padding: 8px 16px 8px 36px;
      font-size: 0.85rem;
      transition: var(--motion-standard);
    }
    .search-input-lush:focus {
      border-color: var(--lush-forest);
      outline: none;
      box-shadow: 0 0 0 3px rgba(46, 111, 64, 0.12);
    }

    /* Rider Dispatch Selection Card */
    .rider-dispatch-card {
      background: var(--lush-mint-soft);
      border: 1px solid var(--lush-border);
      border-radius: 14px;
      padding: 12px 14px;
      margin-bottom: 12px;
    }

    /* Responsive Media Queries */
    @media (max-width: 1280px) {
      .kds-navbar {
        padding: 0 8px;
      }
      .kds-nav-container {
        gap: 4px;
      }
      .brand-mark {
        font-size: 1.1rem;
      }
      .brand-tag {
        font-size: 0.58rem;
        padding: 1px 4px;
      }
      .nav-pills-kds {
        padding: 2px;
        gap: 1px;
      }
      .nav-pills-kds .nav-link {
        padding: 3px 5px;
        font-size: 0.69rem;
        gap: 3px;
      }
      .badge-count {
        font-size: 0.65rem;
        padding: 0 4px;
      }
      .kds-actions-cluster {
        gap: 4px;
      }
      .kds-search-box {
        width: 72px;
      }
      .kds-search-box:focus-within {
        width: 130px;
      }
      .btn-kds-86 {
        padding: 3px 6px;
        font-size: 0.68rem;
      }
      .btn-kds-icon {
        width: 26px;
        height: 26px;
        font-size: 0.74rem;
      }
    }

    @media (max-width: 991.98px) {
      .kds-navbar {
        padding: 6px 10px;
      }
      .brand-mark {
        font-size: 1.1rem;
      }
      .swimlane-col {
        min-height: auto;
        margin-bottom: 20px;
        padding: 14px;
      }
    }

    @media (max-width: 767.98px) {
      .kpi-card {
        padding: 12px 14px;
        border-radius: 14px;
      }
      .kpi-number {
        font-size: 1.6rem !important;
      }
      .kpi-label {
        font-size: 0.65rem;
        margin-bottom: 2px;
      }
      .kpi-icon-wrap {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        font-size: 1rem;
      }
      .chip-bar-container {
        padding: 8px 10px;
        border-radius: 14px;
        margin-bottom: 16px;
      }
      .nav-pills-kds .nav-link {
        font-size: 0.78rem;
        padding: 6px 14px;
      }
    }
  </style>
</head>
<body>

  <!-- Top Navigation Bar (Material 3 App Bar - Single Unified Row) -->
  <header class="kds-navbar">
    <div class="kds-nav-container">
      
      <!-- Left: Brand & Station Status -->
      <div class="kds-brand-cluster">
        <a href="<?= KITCHEN_URL ?>/index.php" class="text-decoration-none">
          <div class="brand-mark">
            <span>ZIPPY<span style="color:var(--lush-forest);">.</span></span>
            <span class="brand-tag">ATELIER KDS</span>
          </div>
        </a>
        <div class="d-none d-xxl-inline-flex align-items-center gap-1 px-2 py-0.5 rounded-pill" style="background:var(--lush-mint-soft); border:1px solid var(--lush-border); font-size:0.68rem; font-weight:800; color:var(--lush-pine);">
          <span class="live-dot"></span>
          <span>ONLINE</span>
        </div>
        <span class="live-dot d-inline-block d-xxl-none ms-1" title="Station Online"></span>
      </div>

      <!-- Center: Segmented Navigation Tab Cluster -->
      <div class="kds-tabs-cluster">
        <ul class="nav nav-pills nav-pills-kds mb-0 flex-nowrap" id="kdsStationTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-all-btn" data-bs-toggle="pill" data-bs-target="#tab-all" type="button" role="tab" title="Kanban 3-Swimlane View">
              <i class="fa-solid fa-table-columns"></i>
              <span class="d-none d-xl-inline">All Swimlanes</span>
              <span class="d-inline d-xl-none">All</span>
              <span class="badge-count" id="tabCountTotal"><?= $totalActive ?? 0 ?></span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-new-btn" data-bs-toggle="pill" data-bs-target="#tab-new" type="button" role="tab" title="Incoming Orders Pending Acceptance">
              <i class="bi bi-inbox"></i>
              <span class="d-none d-xxl-inline">1. </span><span>Incoming</span>
              <span class="badge-count" id="tabCountNew"><?= $countNew ?? 0 ?></span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-cooking-btn" data-bs-toggle="pill" data-bs-target="#tab-cooking" type="button" role="tab" title="Active on Stoves & Ovens">
              <i class="fa-solid fa-fire"></i>
              <span class="d-none d-xxl-inline">2. </span><span>Cooking</span>
              <span class="badge-count" id="tabCountCooking"><?= $countCooking ?? 0 ?></span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-ready-btn" data-bs-toggle="pill" data-bs-target="#tab-ready" type="button" role="tab" title="Bagged & Ready for Delivery">
              <i class="bi bi-bag-check"></i>
              <span class="d-none d-xxl-inline">3. </span><span>Ready for Delivery</span>
              <span class="badge-count" id="tabCountReady"><?= $countReady ?? 0 ?></span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-scheduled-btn" data-bs-toggle="pill" data-bs-target="#tab-scheduled" type="button" role="tab" title="Advance Scheduled Meal Orders">
              <i class="bi bi-calendar2-check-fill" style="color:#7C3AED;"></i>
              <span class="d-none d-xxl-inline">Advance </span><span>Scheduled</span>
              <span class="badge-count text-white" style="background:#7C3AED;" id="tabCountScheduled"><?= $countScheduled ?? 0 ?></span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-history-btn" data-bs-toggle="pill" data-bs-target="#tab-history" type="button" role="tab" title="Historical Fulfilled Orders">
              <i class="bi bi-clock-history text-primary"></i>
              <span>Order History</span>
              <span class="badge-count text-primary bg-white border" id="tabCountHistory"><?= isset($orderHistory) ? count($orderHistory) : 0 ?></span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-salary-btn" data-bs-toggle="pill" data-bs-target="#tab-salary" type="button" role="tab" title="Chef Salary & Payouts Ledger">
              <i class="bi bi-wallet2"></i>
              <span>Salary</span>
            </button>
          </li>
        </ul>
      </div>

      <!-- Right: Action Controls & Chef Profile -->
      <div class="kds-actions-cluster">
        <!-- Search -->
        <div class="kds-search-box">
          <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-2 text-muted" style="pointer-events:none; font-size:0.72rem;"></i>
          <input type="text" id="kdsSearchInput" class="kds-search-input" placeholder="Search..." onkeyup="filterTickets()" title="Filter tickets by docket or item">
        </div>

        <!-- 86-List -->
        <button class="btn-kds-86" data-bs-toggle="modal" data-bs-target="#modal86List" title="86-List: Manage Inventory Availability">
          <i class="fa-solid fa-boxes-stacked"></i>
          <span>86-LIST</span>
        </button>

        <!-- Sound Alert Toggle -->
        <button id="btnAudioToggle" class="btn-kds-icon" onclick="toggleAudioChime()" title="Toggle Kitchen Alert Chime">
          <i id="audioIcon" class="bi bi-bell-fill" style="color:var(--lush-forest);"></i>
          <span id="audioText" class="d-none">SOUND: ON</span>
        </button>

        <!-- Auto Sync Toggle -->
        <button id="btnRefreshToggle" class="btn-kds-icon" onclick="toggleAutoRefresh()" title="Auto Sync with Kitchen Server">
          <i id="refreshIcon" class="bi bi-arrow-clockwise" style="color:var(--lush-forest);"></i>
          <span id="refreshText" class="d-none">SYNC (6s)</span>
        </button>

        <!-- Fullscreen Toggle -->
        <button id="btnFullscreenToggle" class="btn-kds-icon" onclick="toggleKdsFullscreen()" title="Toggle Fullscreen Kiosk Mode">
          <i id="fullscreenIcon" class="bi bi-fullscreen"></i>
          <span class="d-none">FULLSCREEN</span>
        </button>

        <!-- Live Clock -->
        <span id="kdsLiveClock" class="d-none d-xxl-inline-block small font-mono fw-bold text-muted ps-1 pe-2" style="font-size:0.74rem;">--:--:--</span>

        <!-- Chef Profile Dropdown -->
        <div class="dropdown">
          <button class="btn btn-sm bg-white rounded-pill px-2 py-1 d-flex align-items-center gap-1 border shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-color:var(--lush-border) !important; height:30px;">
            <div class="rounded-circle text-white d-inline-flex align-items-center justify-content-center" style="width:22px; height:22px; font-size:0.65rem; font-weight:800; background:var(--lush-forest);">
              CS
            </div>
            <i class="bi bi-chevron-down text-muted" style="font-size:0.65rem;"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-lg py-2 border-0 rounded-4" style="background:#FFFFFF; min-width: 230px; box-shadow: var(--elevation-3);">
            <li class="px-3 py-2 border-bottom">
              <div class="fw-bold small font-outfit" style="color:var(--lush-pine);"><?= e($currentUser['name'] ?? 'Chef Sanjeev') ?></div>
              <div class="text-muted" style="font-size:0.72rem;"><?= e($currentUser['email'] ?? 'chef@zippy.com') ?> &bull; <strong>Expeditor</strong></div>
            </li>
            <li><a class="dropdown-item small py-2 fw-semibold" href="javascript:void(0)" onclick="showHistoryTab()"><i class="bi bi-clock-history me-2 text-primary"></i> Order History &amp; Fulfilled Dockets</a></li>
            <li><a class="dropdown-item small py-2" href="javascript:void(0)" onclick="showSalaryTab()"><i class="bi bi-wallet2 me-2 text-success"></i> Chef Salary &amp; Payslips</a></li>
            <?php if (is_admin()): ?>
              <li><a class="dropdown-item small py-2" href="<?= ADMIN_URL ?>/index.php"><i class="bi bi-speedometer2 me-2 text-warning"></i> Admin Console</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger fw-semibold small py-2" href="<?= KITCHEN_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Chef Sign Off</a></li>
          </ul>
        </div>

      </div>

      <!-- Preserved DOM IDs for JS Polling & Automated Tests -->
      <div class="d-none">
        <span id="statNew"><?= $countNew ?? 0 ?></span>
        <span id="statCooking"><?= $countCooking ?? 0 ?></span>
        <span id="statReady"><?= $countReady ?? 0 ?></span>
        <span id="statHandedOver"><?= $countHandedOver ?? 0 ?></span>
        <span id="statActiveTotal"><?= $totalActive ?? 0 ?></span>
      </div>

    </div>
  </header>

  <!-- Main Container -->
  <main class="container-fluid px-2 px-md-3 py-2">

    <!-- Flash Notifications -->
    <div id="flashContainer">
      <?php display_flash(); ?>
    </div>

    <!-- Live Audio Notification Banner -->
    <div id="newOrderBanner" class="alert d-none align-items-center justify-content-between rounded-4 py-3 px-3 px-md-4 mb-4 shadow-sm" style="background: linear-gradient(135deg, var(--lush-pine) 0%, var(--lush-forest) 100%); color:#FFFFFF; border:none;">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle p-2 d-inline-flex align-items-center justify-content-center" style="width:40px; height:40px; background:rgba(255,255,255,0.2); color:#FFFFFF;">
          <i class="fa-solid fa-bell"></i>
        </div>
        <div>
          <h6 class="fw-bold mb-0 font-outfit" id="newOrderBannerTitle">NEW ORDER TICKETS RECEIVED</h6>
          <span class="small text-white-50" id="newOrderBannerDesc">Fresh customer orders placed. Review incoming queue below.</span>
        </div>
      </div>
      <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 px-md-4 fw-bold" onclick="dismissOrderBanner()">
        Acknowledge
      </button>
    </div>
