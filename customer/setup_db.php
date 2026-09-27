<?php
require_once __DIR__ . '/config/db.php';

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = Database::setupDatabase();
    if ($result['success']) {
        $message = $result['message'];
    } else {
        $error = $result['message'];
    }
}

$isMock = Database::isMockMode();
$connError = Database::getConnectionError();
$pageTitle = "Database Installer & Health Monitor";
require_once __DIR__ . '/includes/header.php';
?>

<main class="container-xl py-5" style="min-height: 75vh;">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      
      <!-- Academic & Engineering Badge Header -->
      <div class="card border-0 rounded-4 shadow-sm p-4 p-md-5 bg-white mb-4">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="p-3 rounded-4 bg-success-subtle text-success">
            <i class="fa-solid fa-database fs-2"></i>
          </div>
          <div>
            <h3 class="fw-bold text-pine mb-1">RDBMS Engineering & Database Setup</h3>
            <p class="text-muted small mb-0">MCA Major Project (MCSP-232, IGNOU) • 10 Tables Normalized to 3NF</p>
          </div>
        </div>

        <?php if ($message): ?>
          <div class="alert alert-success rounded-3 shadow-sm d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= e($message) ?></div>
          </div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="alert alert-danger rounded-3 shadow-sm d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?= e($error) ?></div>
          </div>
        <?php endif; ?>

        <!-- Database Status Card -->
        <div class="p-3 rounded-3 mb-4 <?= $isMock ? 'bg-warning-subtle border border-warning' : 'bg-success-subtle border border-success' ?>">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <span class="fw-bold <?= $isMock ? 'text-warning-emphasis' : 'text-success-emphasis' ?>">
                <i class="bi <?= $isMock ? 'bi-info-circle-fill' : 'bi-shield-check' ?> me-1"></i>
                Connection Status: <?= $isMock ? 'Mock Fallback Active (MySQL Offline)' : 'Connected to MySQL 8.0 (' . DB_NAME . ')' ?>
              </span>
              <div class="small text-muted mt-1">
                <?= $isMock ? 'Error details: ' . e($connError ?: 'Could not reach MySQL on ' . DB_HOST . ':' . DB_PORT) : 'Host: ' . DB_HOST . ':' . DB_PORT . ' | InnoDB Engine with ACID transactions & row-level locking enabled.' ?>
              </div>
            </div>
            <span class="badge <?= $isMock ? 'bg-warning text-dark' : 'bg-success text-white' ?> px-3 py-2 rounded-pill">
              <?= $isMock ? 'Simulated' : 'Live MySQL' ?>
            </span>
          </div>
        </div>

        <p class="text-muted small mb-4">
          Click below to create the <code><?= DB_NAME ?></code> database, run the complete 10-table 3NF schema DDL script, and populate realistic menu items, categories, demo users, orders, and addresses.
        </p>

        <form method="POST">
          <button type="submit" class="btn btn-forest rounded-pill px-4 py-2 fw-bold shadow-sm d-flex align-items-center gap-2">
            <i class="bi bi-arrow-repeat"></i>
            Initialize / Reset Database & Seed Data
          </button>
        </form>
      </div>

      <!-- Demo Accounts & Academic Schema Summary -->
      <div class="row g-4">
        <div class="col-md-6">
          <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
            <h6 class="fw-bold text-pine mb-3"><i class="bi bi-key-fill text-success me-2"></i> Demo Test Accounts</h6>
            <div class="table-responsive">
              <table class="table table-sm small align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Password</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><span class="badge bg-success-subtle text-success">Customer</span></td>
                    <td><code>pooja@zippy.test</code></td>
                    <td><code>password123</code></td>
                  </tr>
                  <tr>
                    <td><span class="badge bg-primary-subtle text-primary">Customer 2</span></td>
                    <td><code>rahul@zippy.test</code></td>
                    <td><code>password123</code></td>
                  </tr>
                  <tr>
                    <td><span class="badge bg-warning-subtle text-warning-emphasis">Chef/Kitchen</span></td>
                    <td><code>chef@zippy.test</code></td>
                    <td><code>password123</code></td>
                  </tr>
                  <tr>
                    <td><span class="badge bg-info-subtle text-info-emphasis">Courier</span></td>
                    <td><code>rider@zippy.test</code></td>
                    <td><code>password123</code></td>
                  </tr>
                  <tr>
                    <td><span class="badge bg-danger-subtle text-danger">Admin</span></td>
                    <td><code>admin@zippy.test</code></td>
                    <td><code>password123</code></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
            <h6 class="fw-bold text-pine mb-3"><i class="bi bi-diagram-3-fill text-success me-2"></i> 10 Normalized 3NF Tables</h6>
            <div class="d-flex flex-wrap gap-1">
              <span class="badge bg-light text-dark border">roles</span>
              <span class="badge bg-light text-dark border">users</span>
              <span class="badge bg-light text-dark border">user_addresses</span>
              <span class="badge bg-light text-dark border">categories</span>
              <span class="badge bg-light text-dark border">menu_items</span>
              <span class="badge bg-light text-dark border">orders</span>
              <span class="badge bg-light text-dark border">order_items</span>
              <span class="badge bg-light text-dark border">payments</span>
              <span class="badge bg-light text-dark border">delivery_dispatches</span>
              <span class="badge bg-light text-dark border">order_audit_trail</span>
            </div>
            <div class="mt-4 pt-2 border-top">
              <a href="index.php" class="btn btn-outline-forest btn-sm rounded-pill w-100 fw-semibold">
                Go to Zippy Customer Home &rarr;
              </a>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
