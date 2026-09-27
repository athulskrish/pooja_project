<?php
/**
 * Privacy Policy Page
 * MCSP-232: E-Commerce Platform for Food Order
 */
$pageTitle = 'Privacy Policy - Zippy';
require_once __DIR__ . '/includes/header.php';
?>

  <main class="container-xl py-4" style="max-width: 860px;">
    
    <div class="p-4 p-md-5 bg-white rounded-4 border shadow-sm">
      <div class="border-bottom pb-3 mb-4">
        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1 rounded-pill mb-2">DATA PRIVACY (DPDPA 2023)</span>
        <h2 class="fw-bold text-pine mb-1">Privacy Policy & Data Security</h2>
        <p class="text-muted small mb-0">Compliant with the Digital Personal Data Protection Act (DPDPA) 2023</p>
      </div>

      <div class="small text-muted" style="line-height: 1.8;">
        <h5 class="fw-bold text-pine mt-4 mb-2">1. Data Minimization Principle</h5>
        <p>Zippy collects only essential digital artifacts necessary to process food delivery: customer name, delivery street address, phone number for courier contact, and email for tax invoicing.</p>

        <h5 class="fw-bold text-pine mt-4 mb-2">2. Cryptographic Security Standards</h5>
        <p>User passwords are encrypted using one-way Argon2id/Bcrypt hashes. Plaintext passwords are never logged, stored, or exposed to restaurant cooks or delivery partners.</p>

        <h5 class="fw-bold text-pine mt-4 mb-2">3. Secret Delivery OTP Mechanism</h5>
        <p>To prevent fraudulent delivery claims or unauthorized interception, customer orders are protected by a random 6-digit secret OTP that must be validated before an order can be marked DELIVERED in the system.</p>

        <h5 class="fw-bold text-pine mt-4 mb-2">4. User Rights & Data Deletion</h5>
        <p>Registered users maintain the right to view, modify, or erase their stored delivery addresses via the Address Management section at any time.</p>
      </div>
    </div>

  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
