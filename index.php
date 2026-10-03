<?php
/**
 * Zippy - Online Food Ordering Platform
 * Main Portals Launchpad & Architecture Overview
 * Student: Pooja JP | Course: MCSP-232 (MCA Major Project) | IGNOU
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$dbStatus = checkDBStatus();
$dishCount = $dbStatus['tables_exist'] ? db_fetch_val("SELECT COUNT(*) FROM `menu_items`") : 0;
$orderCount = $dbStatus['tables_exist'] ? db_fetch_val("SELECT COUNT(*) FROM `orders`") : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Zippy - Online Food Ordering Platform | MCSP-232</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">
  
  <style>
    body {
      background: #F7FAF8;
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: #1C2B20;
    }
    .hero-banner {
      background: linear-gradient(135deg, #1C2B20 0%, #243E2C 50%, #2E6F40 100%);
      color: #fff;
      padding: 60px 0 50px;
      border-bottom: 4px solid #68BA7F;
    }
    .portal-card {
      background: #fff;
      border-radius: 20px;
      border: 1px solid #E2ECE5;
      box-shadow: 0 10px 30px rgba(36,62,44,0.06);
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .portal-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 18px 40px rgba(36,62,44,0.12);
      border-color: #68BA7F;
    }
    .icon-box {
      width: 60px;
      height: 60px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
    }
  </style>
</head>
<body>

  <!-- Top Hero Header -->
  <header class="hero-banner">
    <div class="container-xl">
      <div class="row align-items-center g-4">
        <div class="col-lg-8">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge rounded-pill px-3 py-1 fw-bold" style="background:#CEFFDC; color:#243E2C;">
              🌿 FIGMA "LUSH FOREST" PALETTE
            </span>
            <span class="badge bg-secondary rounded-pill px-3 py-1">PHP 8.2 & MySQL 8.0</span>
          </div>

          <h1 class="display-5 fw-bold font-outfit text-white mb-2">
            zippy<span style="color:#68BA7F;">.</span> Food Ordering Platform
          </h1>
          <p class="fs-6 text-white-50 mb-4" style="max-width: 650px;">
            MCA Major Project (Course: <strong>MCSP-232</strong>) | Student: <strong>Pooja JP</strong> (Enrollment: <code>2500051247</code>)  
            Engineered with 3NF database normalization, ACID concurrency control, Apriori market basket analysis, WSJF kitchen queue scheduling, and SES demand forecasting.
          </p>

          <div class="d-flex flex-wrap gap-3">
            <a href="<?= CUSTOMER_URL ?>/index.php" class="btn btn-mint fw-bold rounded-pill px-4 py-2" style="background:#CEFFDC; color:#243E2C;">
              <i class="bi bi-shop me-2"></i> Launch Customer Storefront
            </a>
            <a href="<?= KITCHEN_URL ?>/index.php" class="btn btn-warning fw-bold rounded-pill px-4 py-2 text-dark">
              <i class="fa-solid fa-fire-burner me-2"></i> Launch Kitchen KDS
            </a>
            <a href="<?= DELIVERY_URL ?>/index.php" class="btn btn-light fw-bold rounded-pill px-4 py-2 text-dark">
              <i class="bi bi-bicycle me-2"></i> Launch Delivery Fleet
            </a>
            <a href="<?= ADMIN_URL ?>/index.php" class="btn btn-outline-light rounded-pill px-4 py-2">
              <i class="bi bi-speedometer2 me-2"></i> Launch Admin Console
            </a>
            <a href="<?= BASE_URL ?>/database/seed.php" class="btn btn-sm btn-outline-success text-mint rounded-pill px-3 py-2 border-success align-self-center">
              <i class="bi bi-database-check me-1"></i> Database Seeder
            </a>
          </div>
        </div>

        <div class="col-lg-4 text-center d-none d-lg-block">
          <div class="p-4 rounded-4" style="background: rgba(255,255,255,0.08); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.15);">
            <div class="small text-white-50 text-uppercase mb-2" style="letter-spacing:1px;">SYSTEM STATUS</div>
            <?php if ($dbStatus['connected'] && $dbStatus['tables_exist']): ?>
              <div class="badge bg-success rounded-pill px-3 py-2 mb-2 fs-6">
                <i class="bi bi-check-circle-fill me-1"></i> MySQL Connected & Seeded
              </div>
              <div class="text-white-50 small mt-2">
                <strong><?= (int)$dishCount ?></strong> Menu Items | <strong><?= (int)$orderCount ?></strong> Orders
              </div>
            <?php else: ?>
              <div class="badge bg-warning text-dark rounded-pill px-3 py-2 mb-2 fs-6">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> Database Setup Required
              </div>
              <div class="text-white-50 small mt-2">
                <a href="<?= BASE_URL ?>/database/seed.php" class="btn btn-sm btn-mint mt-1">Run Seed Script Now</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Main Portals Grid -->
  <main class="py-5">
    <div class="container-xl">
      
      <div class="text-center mb-5">
        <h2 class="fw-bold font-outfit text-dark mb-1">Select an Operational Portal</h2>
        <p class="text-muted small">All portals share a centralized MySQL 8.0 database engine with real-time transactional synchronization</p>
      </div>

      <div class="row g-4 mb-5">
        
        <!-- Portal 1: Customer Storefront -->
        <div class="col-md-6 col-lg">
          <div class="card h-100 p-4 portal-card text-center d-flex flex-column justify-content-between">
            <div>
              <div class="icon-box mx-auto mb-3" style="background: #EDFBF2; color: #2E6F40;">
                <i class="fa-solid fa-basket-shopping"></i>
              </div>
              <h5 class="fw-bold font-outfit text-dark mb-2">Customer Storefront</h5>
              <p class="text-muted small mb-3">
                Full customer journey: browsing menu dishes, veg filters, cart calculations, coupon codes, and 1-click checkout.
              </p>
            </div>
            <a href="<?= CUSTOMER_URL ?>/index.php" class="btn btn-success w-100 rounded-pill fw-bold py-2 shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
              Open Customer App &rarr;
            </a>
          </div>
        </div>

        <!-- Portal 2: Delivery Partner Fleet App -->
        <div class="col-md-6 col-lg">
          <div class="card h-100 p-4 portal-card text-center d-flex flex-column justify-content-between border-success">
            <div>
              <div class="icon-box mx-auto mb-3" style="background: #CEFFDC; color: #243E2C;">
                <i class="fa-solid fa-motorcycle"></i>
              </div>
              <h5 class="fw-bold font-outfit text-dark mb-2">Delivery Partner</h5>
              <p class="text-muted small mb-3">
                Rider app: online/offline duty switch, turn-by-turn route GPS navigation, and 6-digit doorstep secret OTP verification.
              </p>
            </div>
            <a href="<?= DELIVERY_URL ?>/index.php" class="btn btn-mint w-100 rounded-pill fw-bold py-2 shadow-sm" style="background:#CEFFDC; color:#243E2C; border: 1px solid #68BA7F;">
              Open Delivery App &rarr;
            </a>
          </div>
        </div>

        <!-- Portal 3: Kitchen Display System (KDS) -->
        <div class="col-md-6 col-lg">
          <div class="card h-100 p-4 portal-card text-center d-flex flex-column justify-content-between border-warning">
            <div>
              <div class="icon-box mx-auto mb-3" style="background: #fffbe6; color: #E5A100;">
                <i class="fa-solid fa-fire-burner"></i>
              </div>
              <h5 class="fw-bold font-outfit text-dark mb-2">Kitchen Station (KDS)</h5>
              <p class="text-muted small mb-3">
                Dedicated independent workspace: accept customer orders, manage stove prep with WSJF priorities, and hand over bagged parcels to delivery riders.
              </p>
            </div>
            <a href="<?= KITCHEN_URL ?>/index.php" class="btn btn-warning text-dark w-100 rounded-pill fw-bold py-2 shadow-sm">
              Open Kitchen Station &rarr;
            </a>
          </div>
        </div>

        <!-- Portal 4: Admin Operations -->
        <div class="col-md-6 col-lg">
          <div class="card h-100 p-4 portal-card text-center d-flex flex-column justify-content-between">
            <div>
              <div class="icon-box mx-auto mb-3" style="background: #eef4ff; color: #243E2C;">
                <i class="bi bi-speedometer2"></i>
              </div>
              <h5 class="fw-bold font-outfit text-dark mb-2">Admin Console</h5>
              <p class="text-muted small mb-3">
                Store admin management: KPI sales overview, food categories CRUD, dish stock reserves, and user RBAC permissions.
              </p>
            </div>
            <a href="<?= ADMIN_URL ?>/index.php" class="btn btn-dark w-100 rounded-pill fw-bold py-2 shadow-sm" style="background:#243E2C;">
              Open Admin Console &rarr;
            </a>
          </div>
        </div>

        <!-- Portal 5: Executive BI Reports -->
        <div class="col-md-6 col-lg">
          <div class="card h-100 p-4 portal-card text-center d-flex flex-column justify-content-between">
            <div>
              <div class="icon-box mx-auto mb-3" style="background: #fdf2f8; color: #be185d;">
                <i class="bi bi-graph-up-arrow"></i>
              </div>
              <h5 class="fw-bold font-outfit text-dark mb-2">BI Reports</h5>
              <p class="text-muted small mb-3">
                Interactive Chart.js visualizations and Single Exponential Smoothing (SES) demand forecasting for inventory procurement.
              </p>
            </div>
            <a href="<?= ADMIN_URL ?>/reports.php" class="btn btn-outline-dark w-100 rounded-pill fw-bold py-2 shadow-sm">
              View BI Analytics &rarr;
            </a>
          </div>
        </div>

      </div>

      <!-- Demo Accounts & Credentials Reference Table -->
      <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-5">
        <h5 class="fw-bold font-outfit text-dark mb-3"><i class="bi bi-key-fill text-success me-2"></i>Standard Pre-Seeded Demo Credentials</h5>
        <div class="table-responsive">
          <table class="table table-bordered align-middle small mb-0">
            <thead class="table-light">
              <tr>
                <th>Role</th>
                <th>Full Name</th>
                <th>Email Address</th>
                <th>Password</th>
                <th>Direct Portal Route</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><span class="badge bg-danger">ADMIN</span></td>
                <td class="fw-bold">Pooja JP (Admin)</td>
                <td><code>admin@zippy.com</code></td>
                <td><code class="text-dark">password123</code></td>
                <td><a href="<?= ADMIN_URL ?>/index.php" class="text-success fw-bold">Admin Console</a></td>
              </tr>
              <tr>
                <td><span class="badge bg-success">CUSTOMER</span></td>
                <td class="fw-bold">Pooja JP (Customer)</td>
                <td><code>pooja@example.com</code></td>
                <td><code class="text-dark">password123</code></td>
                <td><a href="<?= CUSTOMER_URL ?>/index.php" class="text-success fw-bold">Customer Storefront</a></td>
              </tr>
              <tr>
                <td><span class="badge bg-warning text-dark">KITCHEN</span></td>
                <td class="fw-bold">Chef Sanjeev (Head Cook)</td>
                <td><code>chef@zippy.com</code></td>
                <td><code class="text-dark">password123</code></td>
                <td><a href="<?= KITCHEN_URL ?>/index.php" class="text-warning text-dark fw-bold">Kitchen Station KDS</a></td>
              </tr>
              <tr>
                <td><span class="badge bg-info text-dark">RIDER</span></td>
                <td class="fw-bold">Suresh Kumar (Rider)</td>
                <td><code>rider@zippy.com</code></td>
                <td><code class="text-dark">password123</code></td>
                <td><a href="<?= DELIVERY_URL ?>/index.php" class="text-success fw-bold">Delivery Partner Fleet App</a></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Academic Specifications & Viva Voce Quick Cards -->
      <div class="row g-4">
        
        <div class="col-md-4">
          <div class="card h-100 p-4 border-0 rounded-4 shadow-sm bg-white">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="badge" style="background:#2E6F40; color:#CEFFDC;">ALGORITHM 1</span>
              <h6 class="fw-bold mb-0 text-dark">Apriori Engine</h6>
            </div>
            <p class="small text-muted mb-0">
              Scans historical <code>order_items</code> pairings using support and confidence metrics to generate real-time complementary cross-sell recommendations (Biryani + Garlic Naan + Mango Lassi).
            </p>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card h-100 p-4 border-0 rounded-4 shadow-sm bg-white">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="badge" style="background:#2E6F40; color:#CEFFDC;">ALGORITHM 2</span>
              <h6 class="fw-bold mb-0 text-dark">WSJF Queue Scheduling</h6>
            </div>
            <p class="small text-muted mb-0">
              Dynamically prioritizes kitchen tickets using <code>WSJF = Cost of Delay / Job Duration</code>, ensuring delayed tickets bubble to the top of the cooking queue automatically.
            </p>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card h-100 p-4 border-0 rounded-4 shadow-sm bg-white">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="badge" style="background:#2E6F40; color:#CEFFDC;">ALGORITHM 3</span>
              <h6 class="fw-bold mb-0 text-dark">SES Demand Forecast</h6>
            </div>
            <p class="small text-muted mb-0">
              Applies Single Exponential Smoothing (<code>α = 0.3</code>) across historical dish consumption to predict upcoming weekend order volume and flag dishes at risk of stockouts.
            </p>
          </div>
        </div>

      </div>

    </div>
  </main>

  <footer class="py-4 text-center text-muted small border-top" style="background:#fff;">
    <div class="container-xl">
      &copy; 2026 Zippy Online Food Ordering Platform. MCA Major Project MCSP-232 | IGNOU | Student: Pooja JP
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
