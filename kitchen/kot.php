<?php
/**
 * Printable Kitchen Order Ticket (KOT)
 * Zippy - Online Food Ordering Platform
 * MCSP-232 Major Project | Student: Pooja JP
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_kitchen();

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($orderId <= 0) {
    die("Invalid order ID.");
}

$order = db_fetch_one("
    SELECT o.*, u.full_name AS customer_name, u.phone_number AS customer_phone,
           a.address_line, a.landmark, a.pincode,
           d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp
    FROM `orders` o
    JOIN `users` u ON o.user_id = u.user_id
    LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    WHERE o.order_id = :id
", [':id' => $orderId]);

if (!$order) {
    die("Order ticket not found.");
}

$items = db_fetch_all("
    SELECT oi.*, m.item_name, m.is_veg, m.prep_time_minutes, m.kitchen_station
    FROM `order_items` oi
    JOIN `menu_items` m ON oi.item_id = m.item_id
    WHERE oi.order_id = :id
", [':id' => $orderId]);

$totalPortions = 0;
foreach ($items as $it) {
    $totalPortions += (int)$it['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>KOT Ticket #ZIP-<?= $orderId ?> - Zippy Kitchen</title>
  <style>
    @page {
      size: 80mm auto;
      margin: 0;
    }
    body {
      font-family: 'Courier New', Courier, monospace;
      width: 76mm;
      margin: 0 auto;
      padding: 10px;
      color: #000;
      background: #fff;
      font-size: 13px;
      line-height: 1.3;
    }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .bold { font-weight: bold; }
    .title { font-size: 18px; font-weight: bold; margin-bottom: 2px; }
    .divider { border-top: 1px dashed #000; margin: 8px 0; }
    .double-divider { border-top: 2px dashed #000; margin: 8px 0; }
    .table-items { width: 100%; border-collapse: collapse; margin: 6px 0; }
    .table-items th { text-align: left; border-bottom: 1px dashed #000; padding: 4px 0; font-size: 12px; }
    .table-items td { padding: 4px 0; vertical-align: top; }
    .qty { font-size: 15px; font-weight: bold; width: 35px; }
    .notes-box {
      border: 1px solid #000;
      padding: 6px;
      margin: 6px 0;
      font-weight: bold;
      background: #eee;
    }
    .badge {
      display: inline-block;
      padding: 2px 4px;
      border: 1px solid #000;
      font-size: 10px;
    }
    .no-print {
      margin-bottom: 15px;
      text-align: center;
      padding: 10px;
      background: #f8f9fa;
      border-radius: 6px;
    }
    @media print {
      .no-print { display: none; }
    }
  </style>
</head>
<body>

  <div class="no-print">
    <button onclick="window.print()" style="padding: 8px 16px; font-weight:bold; cursor:pointer; background:#2E6F40; color:#fff; border:none; border-radius:4px;">
      🖨️ Print KOT Slip
    </button>
    <button onclick="window.close()" style="padding: 8px 16px; margin-left:8px; cursor:pointer;">
      ✕ Close
    </button>
  </div>

  <div class="text-center">
    <div class="title">ZIPPY RESTAURANT</div>
    <div>*** KITCHEN ORDER TICKET (KOT) ***</div>
    <div class="bold" style="font-size: 16px; margin-top:4px;">TICKET #ZIP-<?= $orderId ?></div>
  </div>

  <div class="divider"></div>

  <div><strong>Date/Time:</strong> <?= date('d-M-Y h:i A', strtotime($order['placed_at'])) ?></div>
  <div><strong>Order Type:</strong> ONLINE DELIVERY (EXPRESS)</div>
  <div><strong>Status:</strong> <?= e($order['order_status']) ?></div>
  <div><strong>Customer:</strong> <?= e($order['customer_name']) ?> (<?= e($order['customer_phone']) ?>)</div>
  <div><strong>Assigned Rider:</strong> <?= e($order['delivery_partner_name'] ?? 'Pending Assignment') ?></div>
  <?php if (!empty($order['delivery_otp'])): ?>
    <div><strong>Doorstep OTP:</strong> <?= e($order['delivery_otp']) ?></div>
  <?php endif; ?>

  <?php if (!empty($order['order_notes'])): ?>
    <div class="notes-box">
      ⚠️ SPECIAL INSTRUCTIONS:<br>
      <?= nl2br(e($order['order_notes'])) ?>
    </div>
  <?php endif; ?>

  <div class="double-divider"></div>

  <table class="table-items">
    <thead>
      <tr>
        <th class="qty">QTY</th>
        <th>ITEM NAME</th>
        <th class="text-right">TIME</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td class="qty">[<?= (int)$it['quantity'] ?>x]</td>
          <td>
            <span class="bold"><?= e($it['item_name']) ?></span>
            <br>
            <span class="badge"><?= $it['is_veg'] ? 'VEG' : 'NON-VEG' ?></span>
            <span style="font-size:10px; color:#444;"><?= e($it['kitchen_station'] ?? 'Main Kitchen') ?></span>
          </td>
          <td class="text-right"><?= (int)$it['prep_time_minutes'] ?>m</td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="double-divider"></div>

  <div style="display: flex; justify-content: space-between;">
    <span><strong>Total Portions:</strong> <?= $totalPortions ?> items</span>
    <span><strong>Items Count:</strong> <?= count($items) ?></span>
  </div>

  <div class="divider"></div>

  <div class="text-center" style="margin-top: 15px; font-size:11px;">
    <div>Expeditor: ___________________</div>
    <div style="margin-top: 4px;">Zippy KDS Engine • Station MCSP-232</div>
  </div>

</body>
</html>
