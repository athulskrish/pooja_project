<?php
/**
 * Terms of Service Page
 * MCSP-232: E-Commerce Platform for Food Order
 */
$pageTitle = 'Terms of Service - Zippy';
require_once __DIR__ . '/includes/header.php';
?>

  <main class="container-xl py-4" style="max-width: 860px;">
    
    <div class="p-4 p-md-5 bg-white rounded-4 border shadow-sm">
      <div class="border-bottom pb-3 mb-4">
        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1 rounded-pill mb-2">LEGAL COMPLIANCE</span>
        <h2 class="fw-bold text-pine mb-1">Terms of Service & Usage Agreement</h2>
        <p class="text-muted small mb-0">Last updated: September 2026 • Governing Law: Information Technology Act, 2000 (India)</p>
      </div>

      <div class="small text-muted" style="line-height: 1.8;">
        <h5 class="fw-bold text-pine mt-4 mb-2">1. Platform Nature & Facilitation Service</h5>
        <p>Zippy acts as an intermediary online food aggregator facilitating transactions between verified commercial cloud kitchens (e.g., Meghana Foods) and registered end-customers. Zippy provides ordering workflows, kitchen queue dispatch (WSJF), and courier dispatch coordination.</p>

        <h5 class="fw-bold text-pine mt-4 mb-2">2. Order Placement & ACID Concurrency</h5>
        <p>All orders placed via <code>checkout.php</code> are committed using transactional database semantics. Dishes are subject to live portion availability. Once a customer verifies the transaction, a unique Order ID and secret 6-digit delivery OTP are provisioned.</p>

        <h5 class="fw-bold text-pine mt-4 mb-2">3. Food Quality, Hygiene & Allergies</h5>
        <p>All preparation, culinary seasoning, ingredient sourcing, and packaging are handled directly by the restaurant partner under FSSAI license compliance. Customers with severe food allergies must provide explicit cooking instructions in the notes field before placing orders.</p>

        <h5 class="fw-bold text-pine mt-4 mb-2">4. Perishable Goods Cancellation Policy</h5>
        <p>Because prepared food is strictly perishable and prepared on demand, cancellation is only permitted within 60 seconds of placement. Once preparation starts on the Kitchen Display System (KDS), orders cannot be cancelled or refunded.</p>
      </div>
    </div>

  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
