<?php
declare(strict_types=1);
/**
 * Official GST Tax Invoice (Indian E-Commerce Compliance)
 * Zippy - Online Food Ordering Platform
 * SAC Code: 996331 | 5% GST (2.5% CGST + 2.5% SGST)
 * Student: Pooja JP | MCSP-232
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireCustomer();
$currentUser = getCurrentUser();
$uid = (int)$currentUser['id'];

$requestedOrderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$invoiceData = OrderService::getOrderInvoiceData($requestedOrderId, $uid);
$orderId = $invoiceData['order_id'];
$order = $invoiceData['order'];

if (!$order) {
    http_response_code(404);
    die("Tax Invoice not found.");
}

if (!$invoiceData['authorized']) {
    http_response_code(403);
    die("Access Denied: You do not have permission to inspect invoices for other customer accounts.");
}

$items = $invoiceData['items'];
$cgst = $invoiceData['cgst'];
$sgst = $invoiceData['sgst'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GST Tax Invoice #ZIP-<?= $orderId ?> | Zippy</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body {
      background: #f4f7f5;
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: #1C2B20;
      padding: 30px 15px;
    }
    .invoice-card {
      max-width: 820px;
      margin: 0 auto;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      border: 1px solid #E2ECE5;
      padding: 40px;
    }
    @media print {
      body { background: #fff; padding: 0; }
      .invoice-card { box-shadow: none; border: none; padding: 0; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>

<div class="invoice-card">
  
  <!-- Print Controls -->
  <div class="d-flex justify-content-between align-items-center mb-4 no-print border-bottom pb-3">
    <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
      <i class="bi bi-arrow-left me-1"></i> Back
    </a>
    <button onclick="window.print()" class="btn btn-success btn-sm rounded-pill px-4 fw-bold shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
      <i class="bi bi-printer-fill me-1"></i> Print / Save as PDF
    </button>
  </div>

  <!-- Invoice Header -->
  <div class="row align-items-start mb-4">
    <div class="col-sm-7">
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="fs-3 fw-bold" style="color: #243E2C; font-family: 'Outfit', sans-serif;">zippy<span style="color:#2E6F40;">.</span></span>
        <span class="badge bg-dark small">TAX INVOICE</span>
      </div>
      <div class="small text-muted"><strong>Zippy Food Delivery Technologies Pvt Ltd</strong></div>
      <div class="small text-muted">Indiranagar 100ft Road, Bangalore, Karnataka - 560038</div>
      <div class="small text-muted">GSTIN: <strong>29AABCU9603R1ZM</strong> | State Code: 29</div>
      <div class="small text-muted">FSSAI Central License No: 11223344005566</div>
    </div>
    <div class="col-sm-5 text-sm-end mt-3 mt-sm-0">
      <h5 class="fw-bold mb-1 text-dark">INVOICE: #INV-ZIP-<?= (int)$order['order_id'] ?></h5>
      <div class="small text-muted">Order ID: <strong>#ZIP-<?= (int)$order['order_id'] ?></strong></div>
      <div class="small text-muted">Date: <?= date('d M Y, h:i A', (int)(strtotime((string)$order['placed_at']) ?: time())) ?></div>
      <div class="small text-muted">SAC Code: <strong>996331</strong> (Restaurant Services)</div>
      <?php if (!empty($order['is_scheduled'])): ?>
        <div class="small fw-bold" style="color:#7C3AED;"><i class="bi bi-calendar2-check-fill me-1"></i> Advance Order: <?= e($order['scheduled_slot'] ?: (!empty($order['scheduled_for']) ? date('d-M-Y h:i A', (int)strtotime((string)$order['scheduled_for'])) : 'Scheduled')) ?></div>
      <?php endif; ?>
      <div class="small text-muted">Payment: <span class="badge bg-success bg-opacity-25 text-success"><?= e($order['payment_mode']) ?> - <?= e($order['payment_status'] ?: 'PAID') ?></span></div>
      <?php if (!empty($order['transaction_reference'])): ?>
        <div class="small text-muted" style="font-size:0.75rem;">Txn Ref: <code class="text-dark"><?= e($order['transaction_reference']) ?></code></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Customer & Delivery Info -->
  <div class="p-3 rounded-3 bg-light border mb-4">
    <div class="row g-2">
      <div class="col-sm-6">
        <div class="small text-muted text-uppercase fw-bold" style="font-size:0.65rem;">Billed To / Customer:</div>
        <div class="fw-bold text-dark"><?= e($order['full_name']) ?></div>
        <div class="small text-muted">Phone: <?= e($order['phone_number']) ?></div>
        <div class="small text-muted">Email: <?= e($order['email']) ?></div>
      </div>
      <div class="col-sm-6 text-sm-end">
        <div class="small text-muted text-uppercase fw-bold" style="font-size:0.65rem;">Delivery Address:</div>
        <div class="small text-dark fw-semibold"><?= e($order['address_line'] ?: 'Indiranagar, Bangalore') ?></div>
        <div class="small text-muted">PIN Code: <?= e($order['pincode'] ?: '560038') ?></div>
      </div>
    </div>
  </div>

  <!-- Line Items Table -->
  <div class="table-responsive mb-4">
    <table class="table table-bordered align-middle small mb-0">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Description of Dish / Food Item</th>
          <th>SAC</th>
          <th class="text-center">Qty</th>
          <th class="text-end">Unit Price</th>
          <th class="text-end">Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $idx => $item): ?>
          <tr>
            <td><?= $idx + 1 ?></td>
            <td>
              <div class="fw-bold text-dark"><?= e($item['item_name']) ?></div>
              <?php if (!empty($item['customizations'])): ?>
                <div class="small text-muted fst-italic" style="font-size: 0.75rem;">
                  <i class="bi bi-sliders me-1"></i><?= e($item['customizations']) ?>
                </div>
              <?php endif; ?>
            </td>
            <td class="font-monospace">996331</td>
            <td class="text-center fw-bold"><?= (int)$item['quantity'] ?></td>
            <td class="text-end"><?= formatInr($item['unit_price']) ?></td>
            <td class="text-end fw-bold"><?= formatInr($item['line_total']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Taxes & Calculations Table -->
  <div class="row justify-content-end mb-4">
    <div class="col-sm-6">
      <table class="table table-sm table-borderless small mb-0">
        <tr>
          <td class="text-muted">Item Subtotal:</td>
          <td class="text-end fw-semibold"><?= formatInr($order['subtotal']) ?></td>
        </tr>
        <tr>
          <td class="text-muted">CGST (2.5%):</td>
          <td class="text-end"><?= formatInr($cgst) ?></td>
        </tr>
        <tr>
          <td class="text-muted">SGST (2.5%):</td>
          <td class="text-end"><?= formatInr($sgst) ?></td>
        </tr>
        <tr>
          <td class="text-muted">Delivery Partner Fee:</td>
          <td class="text-end"><?= formatInr($order['delivery_fee']) ?></td>
        </tr>
        <?php if ($order['discount_amount'] > 0): ?>
          <tr class="text-success">
            <td>Coupon Savings:</td>
            <td class="text-end fw-bold">-<?= formatInr($order['discount_amount']) ?></td>
          </tr>
        <?php endif; ?>
        <tr class="border-top">
          <td class="fw-bold fs-6 pt-2">Total Amount (INR):</td>
          <td class="text-end fw-bold fs-6 pt-2" style="color:#2E6F40;"><?= formatInr($order['total_amount']) ?></td>
        </tr>
      </table>
    </div>
  </div>

  <!-- Footer Declaration & Terms -->
  <div class="border-top pt-3 small text-muted">
    <p class="mb-1"><strong>Declaration:</strong> This is a computer-generated GST tax invoice and does not require a physical signature under Rule 48 of the CGST Rules, 2017.</p>
    <p class="mb-0 text-white-50" style="color:#8A9E91 !important;">For any invoice disputes, please reach out to care@zippy.com referencing Invoice #INV-ZIP-<?= (int)$order['order_id'] ?>.</p>
  </div>

</div>

</body>
</html>
