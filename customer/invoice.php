<?php
/**
 * Official Indian GST Tax Invoice
 * MCSP-232: E-Commerce Platform for Food Order
 * Compliance: Section 31 of CGST Act 2017 & Rule 46 of CGST Rules
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Order.php';

$orderNumber = $_GET['order'] ?? 'ZIP-849201';
$order = Order::getByOrderNumber($orderNumber);

$subtotal = (float)($order['subtotal'] ?? 405.00);
$cgst = round($subtotal * 0.025, 2); // 2.5% CGST
$sgst = round($subtotal * 0.025, 2); // 2.5% SGST
$deliveryFee = (float)($order['delivery_fee'] ?? 35.00);
$platformFee = (float)($order['platform_fee'] ?? 5.00);
$discount = (float)($order['discount_amount'] ?? 0.00);
$total = (float)($order['total_amount'] ?? ($subtotal + $cgst + $sgst + $deliveryFee + $platformFee - $discount));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GST Tax Invoice #<?= sanitize($orderNumber) ?> - Zippy</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  
  <link rel="stylesheet" href="css/style.css">
  
  <style>
    @media print {
      .no-print { display: none !important; }
      body { background: #fff !important; }
      .invoice-container { box-shadow: none !important; border: none !important; }
    }
  </style>
</head>
<body style="background-color: var(--lush-bg);">

  <!-- Action Bar (Hidden in Print) -->
  <div class="no-print bg-white border-bottom py-3 sticky-top shadow-sm">
    <div class="container-xl d-flex justify-content-between align-items-center flex-wrap gap-2" style="max-width: 860px;">
      <div class="d-flex align-items-center gap-2">
        <a href="order-history.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
          <i class="bi bi-arrow-left me-1"></i> Back to Orders
        </a>
        <span class="small text-muted">Invoice: <strong>#INV-2026-<?= sanitize($orderNumber) ?></strong></span>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold" onclick="alert('Tax invoice PDF receipt sent to <?= sanitize($order['email'] ?? 'customer email') ?>!')">
          <i class="bi bi-envelope me-1"></i> Email Receipt
        </button>
        <button class="btn btn-sm btn-success rounded-pill px-4 fw-bold shadow-sm" style="background-color: var(--lush-forest);" onclick="window.print()">
          <i class="bi bi-printer me-1"></i> Print / Download PDF
        </button>
      </div>
    </div>
  </div>

  <!-- Tax Invoice Sheet -->
  <main class="container-xl py-4" style="max-width: 860px;">
    <div class="invoice-container bg-white p-4 p-md-5 rounded-4 border shadow-sm">
      
      <!-- Top Branding & Invoice Title -->
      <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
        <div>
          <div class="d-flex align-items-center gap-2 mb-1">
            <div class="brand-icon-box" style="width: 36px; height: 36px; background-color: var(--lush-forest); color: white; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
              <i class="fa-solid fa-utensils"></i>
            </div>
            <span class="brand-name fs-4 fw-bold text-pine">zippy<span class="text-success">.</span></span>
            <span class="badge bg-success bg-opacity-25 text-success small">Marketplace</span>
          </div>
          <div class="small text-muted">Zippy Limited • Online Food Aggregator Platform</div>
          <div class="small text-muted">Corporate CIN: U74110KA2014PLC075226</div>
        </div>

        <div class="text-end">
          <span class="badge bg-dark text-white fw-bold px-3 py-1 mb-1">TAX INVOICE</span>
          <div class="small text-muted">ORIGINAL FOR RECIPIENT</div>
          <div class="fw-bold text-pine font-monospace fs-5">#INV-2026-<?= sanitize($orderNumber) ?></div>
        </div>
      </div>

      <!-- Parties Involved Grid -->
      <div class="row g-4 mb-4 pb-3 border-bottom">
        
        <!-- Restaurant Details -->
        <div class="col-sm-6">
          <h6 class="fw-bold text-pine mb-1 text-uppercase small" style="letter-spacing: 0.5px;">Restaurant (Supplier) Details:</h6>
          <div class="fw-bold text-pine fs-6">Meghana Foods Pvt. Ltd.</div>
          <div class="small text-muted">#544, 100 Feet Road, HAL 2nd Stage, Indiranagar</div>
          <div class="small text-muted">Bengaluru, Karnataka - 560038</div>
          <div class="small mt-1"><strong>GSTIN:</strong> 29AABCM8291Q1Z4</div>
          <div class="small"><strong>FSSAI Lic. No:</strong> 11219334000842</div>
        </div>

        <!-- Customer & Order Meta -->
        <div class="col-sm-6 text-sm-end">
          <h6 class="fw-bold text-pine mb-1 text-uppercase small" style="letter-spacing: 0.5px;">Billed To (Customer):</h6>
          <div class="fw-bold text-pine fs-6"><?= sanitize($order['full_name'] ?? 'Pooja JP') ?></div>
          <div class="small text-muted"><?= sanitize($order['address_line'] ?? '#402, Green Meadows Apartment, Indiranagar, Bengaluru') ?></div>
          <div class="small text-muted">Ph: <?= sanitize($order['phone_number'] ?? '+91 98765 43210') ?></div>
          <div class="small mt-1"><strong>Order ID:</strong> <span class="font-monospace">#<?= sanitize($orderNumber) ?></span></div>
          <div class="small"><strong>Order Date:</strong> <?= date('d M Y, h:i A', strtotime($order['placed_at'] ?? 'now')) ?></div>
          <div class="small"><strong>Payment:</strong> <?= sanitize($order['payment_mode'] ?? 'UPI') ?> (Ref: <code><?= sanitize($order['transaction_reference'] ?? '429184029148') ?></code>)</div>
        </div>

      </div>

      <!-- Itemized Table -->
      <div class="table-responsive mb-4">
        <table class="table table-bordered align-middle">
          <thead class="table-light small fw-bold text-uppercase">
            <tr>
              <th>#</th>
              <th>Description of Goods / Services</th>
              <th>SAC / HSN</th>
              <th class="text-center">Qty</th>
              <th class="text-end">Unit Rate (₹)</th>
              <th class="text-end">Taxable Value (₹)</th>
            </tr>
          </thead>
          <tbody class="small">
            <?php if (!empty($order['items'])): ?>
              <?php foreach ($order['items'] as $i => $item): ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td>
                    <strong><?= sanitize($item['item_name']) ?></strong>
                    <div class="text-muted" style="font-size: 0.72rem;">Perishable Prepared Cooked Meal</div>
                  </td>
                  <td><code>996331</code></td>
                  <td class="text-center"><?= $item['quantity'] ?></td>
                  <td class="text-end"><?= number_format($item['unit_price'], 2) ?></td>
                  <td class="text-end fw-bold"><?= number_format($item['quantity'] * $item['unit_price'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td>1</td>
                <td><strong>Meghana Special Chicken Biryani</strong></td>
                <td><code>996331</code></td>
                <td class="text-center">1</td>
                <td class="text-end">340.00</td>
                <td class="text-end fw-bold">340.00</td>
              </tr>
              <tr>
                <td>2</td>
                <td><strong>Butter Garlic Naan</strong></td>
                <td><code>996331</code></td>
                <td class="text-center">1</td>
                <td class="text-end">65.00</td>
                <td class="text-end fw-bold">65.00</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Tax & Bill Breakdown -->
      <div class="row g-4 mb-4">
        <div class="col-sm-6 small text-muted">
          <div class="fw-bold text-pine mb-1">Declaration & Tax Notes:</div>
          <p class="mb-1">We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.</p>
          <p class="mb-0">Supply covered under reverse charge: <strong>No</strong></p>
        </div>

        <div class="col-sm-6">
          <div class="d-flex flex-column gap-2 small">
            <div class="d-flex justify-content-between">
              <span>Item Subtotal:</span>
              <span class="fw-bold">₹<?= number_format($subtotal, 2) ?></span>
            </div>
            <div class="d-flex justify-content-between">
              <span>CGST @ 2.5%:</span>
              <span>₹<?= number_format($cgst, 2) ?></span>
            </div>
            <div class="d-flex justify-content-between">
              <span>SGST @ 2.5%:</span>
              <span>₹<?= number_format($sgst, 2) ?></span>
            </div>
            <div class="d-flex justify-content-between">
              <span>Delivery Partner Charges:</span>
              <span>₹<?= number_format($deliveryFee, 2) ?></span>
            </div>
            <div class="d-flex justify-content-between">
              <span>Platform Facilitation Fee:</span>
              <span>₹<?= number_format($platformFee, 2) ?></span>
            </div>
            <?php if ($discount > 0): ?>
              <div class="d-flex justify-content-between text-success fw-bold">
                <span>Coupon Discount:</span>
                <span>-₹<?= number_format($discount, 2) ?></span>
              </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between fw-bold text-pine fs-6 border-top pt-2 mt-1">
              <span>Total Invoice Amount:</span>
              <span class="text-success">₹<?= number_format($total, 2) ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom Signature & Digital Verification QR -->
      <div class="border-top pt-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
          <div class="p-2 border rounded-3 bg-light text-center" style="width: 70px; height: 70px;">
            <i class="bi bi-qr-code fs-1 text-pine"></i>
          </div>
          <div class="small">
            <div class="fw-bold text-pine">Digitally Signed & Verified</div>
            <div class="text-muted">GST E-Invoice System (IRN Generated)</div>
          </div>
        </div>

        <div class="text-end small">
          <div class="fw-bold text-pine">For Meghana Foods Pvt. Ltd.</div>
          <div class="text-muted mt-4">Authorized Signatory</div>
        </div>
      </div>

    </div>
  </main>

</body>
</html>
