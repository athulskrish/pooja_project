<?php
/**
 * Database Seed & Installation Utility
 * Zippy - Online Food Ordering Platform
 * Run via browser: http://localhost/pooja_project/database/seed.php
 * Or via CLI: php database/seed.php
 */

require_once __DIR__ . '/../config/config.php';

$isCli = (php_sapi_name() === 'cli');
$messages = [];
$success = false;

// Credentials to test
$demoPassword = 'password123';
$hashedPassword = password_hash($demoPassword, PASSWORD_DEFAULT);

try {
    // Connect to MySQL server (without selecting DB first, to ensure DB can be created)
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `" . DB_NAME . "`;");
    $messages[] = "Database `" . DB_NAME . "` created / verified successfully.";

    // Read schema.sql
    $sqlFile = __DIR__ . '/schema.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("schema.sql file not found at " . $sqlFile);
    }

    $sqlContent = file_get_contents($sqlFile);

    // Remove trigger definitions with DELIMITER for simplified multi-query execution or handle triggers separately
    // We execute table creation and initial inserts
    // Extract delimiter blocks if any
    $cleanSql = preg_replace('/DELIMITER \$\$[\s\S]*?DELIMITER ;/i', '', $sqlContent);

    // Split statements by semicolon
    $statements = array_filter(array_map('trim', explode(';', $cleanSql)));

    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt);
        }
    }
    $messages[] = "All 10 Relational Tables (3NF) & Seed records imported successfully.";

    // Update demo user passwords with fresh system bcrypt hash
    $updateStmt = $pdo->prepare("UPDATE `users` SET `password_hash` = :hash");
    $updateStmt->execute([':hash' => $hashedPassword]);
    $messages[] = "Standardized demo accounts with active password hash: '{$demoPassword}'";

    // Attempt to register triggers
    try {
        $pdo->exec("DROP TRIGGER IF EXISTS `trg_after_order_status_update`;");
        $pdo->exec("
            CREATE TRIGGER `trg_after_order_status_update`
            AFTER UPDATE ON `orders`
            FOR EACH ROW
            BEGIN
                IF OLD.order_status <> NEW.order_status THEN
                    INSERT INTO `order_audit_trail` (`order_id`, `old_status`, `new_status`, `updated_by_user_id`, `change_timestamp`)
                    VALUES (NEW.order_id, OLD.order_status, NEW.order_status, NEW.user_id, NOW());
                END IF;
            END;
        ");

        $pdo->exec("DROP TRIGGER IF EXISTS `trg_after_menu_stock_update`;");
        $pdo->exec("
            CREATE TRIGGER `trg_after_menu_stock_update`
            BEFORE UPDATE ON `menu_items`
            FOR EACH ROW
            BEGIN
                IF NEW.stock_quantity <= 0 THEN
                    SET NEW.is_available = 0;
                    SET NEW.stock_quantity = 0;
                END IF;
            END;
        ");
        $messages[] = "ACID Audit triggers installed successfully.";
    } catch (Exception $trgEx) {
        $messages[] = "Notice: Triggers skipped (database user may lack SUPER/TRIGGER privileges): " . $trgEx->getMessage();
    }

    $success = true;
} catch (Exception $e) {
    $messages[] = "Error: " . $e->getMessage();
    $success = false;
}

if ($isCli) {
    echo "\n=== ZIPPY DATABASE SEEDER ===\n";
    foreach ($messages as $msg) {
        echo " - " . $msg . "\n";
    }
    echo $success ? "\nSetup Completed Successfully!\n" : "\nSetup Failed.\n";
    exit($success ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Zippy Database Setup & Seeder | MCSP-232</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&family=Outfit:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body {
      background: #F7FAF8;
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: #1C2B20;
      padding: 40px 15px;
    }
    .card-box {
      max-width: 700px;
      margin: 0 auto;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(36,62,44,0.08);
      border: 1px solid #E2ECE5;
      overflow: hidden;
    }
    .header-box {
      background: #243E2C;
      color: #fff;
      padding: 24px 30px;
    }
    .badge-forest {
      background: #2E6F40;
      color: #CEFFDC;
    }
  </style>
</head>
<body>

<div class="card-box">
  <div class="header-box">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <h3 class="fw-bold mb-1 font-outfit" style="font-family: 'Outfit', sans-serif;">zippy<span style="color:#68BA7F;">.</span> Database Setup</h3>
        <p class="mb-0 text-white-50 small">MCSP-232 MCA Major Project | Automated Relational Seeder</p>
      </div>
      <span class="badge badge-forest px-3 py-2 rounded-pill">MySQL 8.0 InnoDB</span>
    </div>
  </div>

  <div class="p-4 p-md-5">
    <?php if ($success): ?>
      <div class="alert alert-success d-flex align-items-center gap-3 mb-4 rounded-3">
        <i class="bi bi-check-circle-fill fs-3 text-success"></i>
        <div>
          <h5 class="fw-bold mb-0">Database Successfully Initialized!</h5>
          <div class="small text-muted">All 10 tables, triggers, relations, and seed records are ready.</div>
        </div>
      </div>
    <?php else: ?>
      <div class="alert alert-danger d-flex align-items-center gap-3 mb-4 rounded-3">
        <i class="bi bi-exclamation-octagon-fill fs-3 text-danger"></i>
        <div>
          <h5 class="fw-bold mb-0">Database Initialization Encountered an Error</h5>
          <div class="small text-muted">Please verify your MySQL service in XAMPP / MariaDB.</div>
        </div>
      </div>
    <?php endif; ?>

    <h6 class="fw-bold text-uppercase text-muted mb-3" style="font-size: 0.75rem; letter-spacing: 0.5px;">Execution Log:</h6>
    <div class="bg-light p-3 rounded-3 mb-4 font-monospace small border">
      <?php foreach ($messages as $msg): ?>
        <div class="mb-1 text-<?= str_contains($msg, 'Error') ? 'danger fw-bold' : (str_contains($msg, 'Notice') ? 'warning' : 'success') ?>">
          <i class="bi bi-arrow-right-short"></i> <?= e($msg) ?>
        </div>
      <?php endforeach; ?>
    </div>

    <h6 class="fw-bold text-uppercase text-muted mb-3" style="font-size: 0.75rem; letter-spacing: 0.5px;">Standard Demo Credentials:</h6>
    <div class="table-responsive mb-4">
      <table class="table table-bordered align-middle small mb-0">
        <thead class="table-light">
          <tr>
            <th>Role</th>
            <th>Email</th>
            <th>Password</th>
            <th>Portal Link</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><span class="badge bg-danger">ADMIN</span></td>
            <td class="fw-bold">admin@zippy.com</td>
            <td><code>password123</code></td>
            <td><a href="<?= ADMIN_URL ?>/index.php" class="btn btn-sm btn-outline-success">Admin Console</a></td>
          </tr>
          <tr>
            <td><span class="badge bg-success">CUSTOMER</span></td>
            <td class="fw-bold">pooja@example.com</td>
            <td><code>password123</code></td>
            <td><a href="<?= CUSTOMER_URL ?>/index.php" class="btn btn-sm btn-success">Customer App</a></td>
          </tr>
          <tr>
            <td><span class="badge bg-warning text-dark">KITCHEN</span></td>
            <td class="fw-bold">chef@zippy.com</td>
            <td><code>password123</code></td>
            <td><a href="<?= KITCHEN_URL ?>/index.php" class="btn btn-sm btn-outline-warning">Kitchen Station</a></td>
          </tr>
          <tr>
            <td><span class="badge bg-primary">RIDER</span></td>
            <td class="fw-bold">rider@zippy.com</td>
            <td><code>password123</code></td>
            <td><a href="<?= DELIVERY_URL ?>/index.php" class="btn btn-sm btn-outline-primary">Delivery Fleet</a></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="d-flex flex-wrap gap-2 justify-content-between pt-3 border-top">
      <a href="<?= CUSTOMER_URL ?>/index.php" class="btn btn-success px-4" style="background:#2E6F40; border-color:#2E6F40;">
        <i class="bi bi-shop me-1"></i> Open Customer Storefront
      </a>
      <a href="<?= ADMIN_URL ?>/index.php" class="btn btn-dark px-4" style="background:#243E2C;">
        <i class="bi bi-speedometer2 me-1"></i> Open Admin Console
      </a>
    </div>
  </div>
</div>

</body>
</html>
