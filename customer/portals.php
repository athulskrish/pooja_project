<?php
/**
 * Multi-Portal Gateway Hub
 * MCSP-232: E-Commerce Platform for Food Order
 */
$pageTitle = 'Multi-Portal Gateway Hub - Zippy';
require_once __DIR__ . '/includes/header.php';
?>

  <!-- Hero Section -->
  <section class="py-5 text-white" style="background: linear-gradient(135deg, #1C3322 0%, #243E2C 50%, #2E6F40 100%);">
    <div class="container-xl">
      <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-white bg-opacity-15 text-mint small fw-bold mb-3 border border-white border-opacity-25">
        <i class="bi bi-layers-fill"></i> 4 Separate Role-Based Web Applications • 1 Relational Database
      </div>
      <h1 class="display-5 fw-extrabold mb-3">Zippy Food Network Platform</h1>
      <p class="lead text-white-50 mb-4" style="max-width: 680px;">
        Engineered for IGNOU MCA Major Project (MCSP-232). A decoupled ecosystem featuring dedicated, standalone applications for <strong>Customers</strong>, <strong>Kitchen Cooks (KDS)</strong>, <strong>Delivery Couriers</strong>, and <strong>Restaurant Administrators</strong>.
      </p>
      <div class="d-flex flex-wrap gap-2">
        <a href="#portalCards" class="btn btn-light rounded-pill px-4 py-2 fw-bold text-pine shadow-sm">
          <i class="bi bi-grid-3x3-gap-fill me-2 text-success"></i> Explore 4 Portals
        </a>
        <a href="login.php" class="btn btn-outline-light rounded-pill px-4 py-2 fw-bold">
          <i class="bi bi-key-fill me-2"></i> Universal Login
        </a>
      </div>
    </div>
  </section>

  <!-- 4 Portal Cards Section -->
  <section class="py-5" id="portalCards">
    <div class="container-xl">
      
      <div class="text-center mb-5">
        <h2 class="fw-bold text-pine">Select a Dedicated Application Portal</h2>
        <p class="text-muted">Each portal operates as an independent web application with tailored layouts and role authorization</p>
      </div>

      <div class="row g-4">
        
        <!-- PORTAL 1: CUSTOMER STOREFRONT -->
        <div class="col-12 col-md-6 col-xl-3">
          <div class="p-4 bg-white rounded-4 border shadow-sm h-100 d-flex flex-column justify-content-between hover-lift">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge bg-light text-muted border small"><i class="bi bi-phone"></i> Consumer App</span>
                <span class="badge bg-success bg-opacity-10 text-success fw-bold">PORTAL 01</span>
              </div>
              <div class="spec-icon bg-success bg-opacity-10 text-success rounded-3 p-3 d-inline-flex mb-3">
                <i class="fa-solid fa-utensils fs-4"></i>
              </div>
              <h4 class="fw-bold text-pine mb-1">Customer Storefront</h4>
              <p class="small text-muted mb-3">Food Ordering, Tracking, and Invoicing in PHP.</p>
              
              <ul class="list-unstyled small text-muted d-flex flex-column gap-2 mb-4">
                <li><i class="bi bi-check2 text-success me-1"></i> Multi-cuisine catalog & sticky filters</li>
                <li><i class="bi bi-check2 text-success me-1"></i> Apriori "Frequently Ordered Together"</li>
                <li><i class="bi bi-check2 text-success me-1"></i> Persistent cart with 5% GST breakdown</li>
                <li><i class="bi bi-check2 text-success me-1"></i> Real-time animated order tracker</li>
                <li><i class="bi bi-check2 text-success me-1"></i> Address book & printable GST invoice</li>
              </ul>
            </div>

            <div>
              <a href="index.php" class="btn btn-success w-100 rounded-pill fw-bold py-2 shadow-xs mb-2" style="background-color: var(--lush-forest);">
                <i class="bi bi-box-arrow-up-right me-1"></i> Launch Customer App
              </a>
            </div>
          </div>
        </div>

        <!-- PORTAL 2: KITCHEN DISPLAY SYSTEM (KDS) -->
        <div class="col-12 col-md-6 col-xl-3">
          <div class="p-4 bg-white rounded-4 border shadow-sm h-100 d-flex flex-column justify-content-between hover-lift">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge bg-light text-muted border small"><i class="bi bi-tablet"></i> Kitchen Station</span>
                <span class="badge bg-warning text-dark fw-bold">PORTAL 02</span>
              </div>
              <div class="spec-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3 d-inline-flex mb-3">
                <i class="fa-solid fa-fire-burner fs-4"></i>
              </div>
              <h4 class="fw-bold text-pine mb-1">Kitchen Display (KDS)</h4>
              <p class="small text-muted mb-3">Chef preparation swimlanes with dynamic WSJF priority.</p>
              
              <ul class="list-unstyled small text-muted d-flex flex-column gap-2 mb-4">
                <li><i class="bi bi-check2 text-warning me-1"></i> 3 Kanban swimlanes (New, Cooking, Ready)</li>
                <li><i class="bi bi-check2 text-warning me-1"></i> Algorithmic WSJF priority calculations</li>
                <li><i class="bi bi-check2 text-warning me-1"></i> Touch item checkboxes for station cooks</li>
                <li><i class="bi bi-check2 text-warning me-1"></i> Audio alert tone engine</li>
                <li><i class="bi bi-check2 text-warning me-1"></i> Handi, Tandoor, Grills routing</li>
              </ul>
            </div>

            <div>
              <a href="kitchen-display.php" class="btn btn-warning w-100 rounded-pill fw-bold py-2 text-dark shadow-xs mb-2">
                <i class="bi bi-box-arrow-up-right me-1"></i> Launch Kitchen KDS
              </a>
            </div>
          </div>
        </div>

        <!-- PORTAL 3: DELIVERY PARTNER APP -->
        <div class="col-12 col-md-6 col-xl-3">
          <div class="p-4 bg-white rounded-4 border shadow-sm h-100 d-flex flex-column justify-content-between hover-lift">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge bg-light text-muted border small"><i class="bi bi-bicycle"></i> Courier Partner</span>
                <span class="badge bg-info text-white fw-bold">PORTAL 03</span>
              </div>
              <div class="spec-icon bg-info bg-opacity-10 text-info rounded-3 p-3 d-inline-flex mb-3">
                <i class="fa-solid fa-motorcycle fs-4"></i>
              </div>
              <h4 class="fw-bold text-pine mb-1">Delivery Partner</h4>
              <p class="small text-muted mb-3">Driver workflow with GPS routes and customer secret OTP verification.</p>
              
              <ul class="list-unstyled small text-muted d-flex flex-column gap-2 mb-4">
                <li><i class="bi bi-check2 text-info me-1"></i> Online / Offline duty toggle</li>
                <li><i class="bi bi-check2 text-info me-1"></i> Daily earnings tracker & payouts</li>
                <li><i class="bi bi-check2 text-info me-1"></i> Simulated turn-by-turn navigation</li>
                <li><i class="bi bi-check2 text-info me-1"></i> Doorstep 6-digit OTP verification</li>
                <li><i class="bi bi-check2 text-info me-1"></i> Direct customer phone dialer</li>
              </ul>
            </div>

            <div>
              <a href="../html_design/delivery-partner.html" target="_blank" class="btn btn-info text-white w-100 rounded-pill fw-bold py-2 shadow-xs mb-2">
                <i class="bi bi-box-arrow-up-right me-1"></i> Launch Courier App
              </a>
            </div>
          </div>
        </div>

        <!-- PORTAL 4: RESTAURANT ADMIN CONSOLE -->
        <div class="col-12 col-md-6 col-xl-3">
          <div class="p-4 bg-white rounded-4 border shadow-sm h-100 d-flex flex-column justify-content-between hover-lift">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge bg-light text-muted border small"><i class="bi bi-speedometer2"></i> Executive Admin</span>
                <span class="badge bg-secondary text-white fw-bold">PORTAL 04</span>
              </div>
              <div class="spec-icon bg-secondary bg-opacity-10 text-secondary rounded-3 p-3 d-inline-flex mb-3">
                <i class="fa-solid fa-chart-line fs-4"></i>
              </div>
              <h4 class="fw-bold text-pine mb-1">Admin Console</h4>
              <p class="small text-muted mb-3">Business intelligence, SES item demand forecasting, and menu CRUD.</p>
              
              <ul class="list-unstyled small text-muted d-flex flex-column gap-2 mb-4">
                <li><i class="bi bi-check2 text-secondary me-1"></i> Revenue & order KPI metrics</li>
                <li><i class="bi bi-check2 text-secondary me-1"></i> Dish catalog CRUD modal</li>
                <li><i class="bi bi-check2 text-secondary me-1"></i> SES predictive demand forecasts</li>
                <li><i class="bi bi-check2 text-secondary me-1"></i> RBAC role permissions manager</li>
                <li><i class="bi bi-check2 text-secondary me-1"></i> Category slug maintenance</li>
              </ul>
            </div>

            <div>
              <a href="../html_design/admin-dashboard.html" target="_blank" class="btn btn-secondary w-100 rounded-pill fw-bold py-2 shadow-xs mb-2">
                <i class="bi bi-box-arrow-up-right me-1"></i> Launch Admin Console
              </a>
            </div>
          </div>
        </div>

      </div>

    </div>
  </section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
