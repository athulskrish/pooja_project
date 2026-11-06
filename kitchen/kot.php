<?php
/**
 * Printable Kitchen Order Ticket (KOT) — Single & Batch Support
 * Zippy - Online Food Ordering Platform
 * MCSP-232 Major Project | Student: Pooja JP
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_kitchen();

// Support both comma-separated batch order_ids or single order_id
$orderIdsRaw = $_GET['order_ids'] ?? ($_GET['order_id'] ?? '');
$targetOrderIds = array_filter(array_map('intval', explode(',', (string)$orderIdsRaw)));

if (empty($targetOrderIds)) {
    die("Invalid order ID(s) provided for KOT slip generation.");
}

$placeholders = implode(',', array_fill(0, count($targetOrderIds), '?'));
$ordersList = db_fetch_all("
    SELECT o.*, u.full_name AS customer_name, u.phone_number AS customer_phone,
           a.address_line, a.landmark, a.pincode,
           d.delivery_partner_name, d.delivery_partner_phone, d.delivery_otp
    FROM `orders` o
    JOIN `users` u ON o.user_id = u.user_id
    LEFT JOIN `user_addresses` a ON o.address_id = a.address_id
    LEFT JOIN `delivery_dispatches` d ON o.order_id = d.order_id
    WHERE o.order_id IN ($placeholders)
    ORDER BY o.placed_at ASC
", $targetOrderIds);

if (empty($ordersList)) {
    die("Order ticket(s) not found.");
}

$allItems = db_fetch_all("
    SELECT oi.*, m.item_name, m.is_veg, m.prep_time_minutes, m.kitchen_station
    FROM `order_items` oi
    JOIN `menu_items` m ON oi.item_id = m.item_id
    WHERE oi.order_id IN ($placeholders)
    ORDER BY oi.order_item_id ASC
", $targetOrderIds);

$itemsByOrder = [];
foreach ($allItems as $it) {
    $itemsByOrder[$it['order_id']][] = $it;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>KOT Ticket<?= count($ordersList) > 1 ? 's (Batch of ' . count($ordersList) . ')' : ' #ZIP-' . (int)$ordersList[0]['order_id'] ?> - Zippy Kitchen</title>
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
    .kot-ticket-wrapper {
      margin-bottom: 20px;
    }
    @media print {
      .no-print { display: none; }
      .page-break-after { page-break-after: always; }
      .kot-ticket-wrapper { margin-bottom: 0; }
    }
  </style>
</head>
<body>

  <div class="no-print">
    <button onclick="window.print()" style="padding: 8px 16px; font-weight:bold; cursor:pointer; background:#2E6F40; color:#fff; border:none; border-radius:4px;">
      🖨️ Print <?= count($ordersList) > 1 ? count($ordersList) . ' Batch KOT Slips' : 'KOT Slip' ?>
    </button>
    <button onclick="window.close()" style="padding: 8px 16px; margin-left:8px; cursor:pointer;">
      ✕ Close
    </button>
  </div>

  <?php foreach ($ordersList as $idx => $order): ?>
    <?php 
      $items = $itemsByOrder[$order['order_id']] ?? [];
      $totalPortions = array_sum(array_column($items, 'quantity'));
      $isLast = ($idx === count($ordersList) - 1);
    ?>
    <div class="kot-ticket-wrapper <?= !$isLast ? 'page-break-after' : '' ?>">
      <div class="text-center">
        <div class="title">ZIPPY RESTAURANT</div>
        <div>*** KITCHEN ORDER TICKET (KOT) ***</div>
        <div class="bold" style="font-size: 16px; margin-top:4px;">TICKET #ZIP-<?= (int)$order['order_id'] ?></div>
      </div>

      <?php if (!empty($order['is_scheduled'])): ?>
        <div style="border: 2px dashed #000; padding: 6px; margin: 8px 0; text-align: center; font-weight: bold; background: #eee;">
          *** ADVANCE SCHEDULED MEAL ***
          <br><span style="font-size: 14px;">TARGET DUE: <?= date('d-M-Y h:i A', strtotime($order['scheduled_for'])) ?></span>
          <br><?= e($order['scheduled_slot'] ?? '') ?>
        </div>
      <?php endif; ?>

      <div class="divider"></div>

      <div><strong>Date/Time:</strong> <?= date('d-M-Y h:i A', strtotime($order['placed_at'])) ?></div>
      <div><strong>Order Type:</strong> <?= !empty($order['is_scheduled']) ? 'ADVANCE PRE-ORDER (SCHEDULED)' : 'ONLINE DELIVERY (EXPRESS)' ?></div>
      <?php if (!empty($order['is_scheduled'])): ?>
        <div><strong>Target Delivery:</strong> <span class="bold"><?= date('d-M-Y h:i A', strtotime($order['scheduled_for'])) ?></span></div>
      <?php endif; ?>
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
                <?php if (!empty($it['customizations'])): ?>
                  <div style="font-size:11px; font-weight:bold; margin-top:2px; color:#111;">
                    &gt;&gt; <?= e($it['customizations']) ?>
                  </div>
                <?php endif; ?>
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
    </div>
  <?php endforeach; ?>

</body>
</html>
