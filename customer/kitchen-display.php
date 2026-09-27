<?php
/**
 * Kitchen Display System (KDS) & WSJF Priority Scheduling
 * MCSP-232: E-Commerce Platform for Food Order
 * Features: 3-Column Kanban Board, WSJF Algorithmic Priority, Station Routing, Audio Alerts
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/KitchenQueue.php';

$board = KitchenQueue::getKdsBoardData();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Kitchen Display System (KDS) - Zippy Kitchen | Pooja Project</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700;800&family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  
  <link rel="stylesheet" href="css/style.css">

  <style>
    body {
      background-color: #0D1612 !important;
      color: #F2F7F4 !important;
      font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .kds-navbar {
      background: #122119;
      border-bottom: 1px solid #1E3827;
      padding: 10px 20px;
    }
    .kds-lane {
      background: #15241C;
      border-radius: 16px;
      border: 1px solid rgba(104, 186, 127, 0.16);
      padding: 16px;
      min-height: calc(100vh - 120px);
      display: flex;
      flex-direction: column;
    }
    .kds-ticket-card {
      background: #1C3025;
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 12px;
      padding: 14px;
      margin-bottom: 14px;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
      transition: all 0.2s ease;
    }
    .kds-ticket-card:hover {
      border-color: #68BA7F;
    }
    .wsjf-pill {
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.72rem;
      padding: 2px 8px;
      border-radius: 6px;
      background: rgba(245, 158, 11, 0.2);
      color: #FDE68A;
      border: 1px solid rgba(245, 158, 11, 0.4);
    }
    .kds-dish-item {
      padding: 6px 0;
      border-bottom: 1px dashed rgba(255, 255, 255, 0.08);
    }
    .kds-dish-item:last-child {
      border-bottom: none;
    }
  </style>
</head>
<body>

  <!-- KDS Header Bar -->
  <header class="kds-navbar d-flex justify-content-between align-items-center flex-wrap gap-2 sticky-top">
    <div class="d-flex align-items-center gap-3">
      <a href="index.php" class="text-decoration-none d-flex align-items-center gap-2">
        <div class="bg-success text-white p-2 rounded-3" style="background-color: #2E6F40 !important;">
          <i class="fa-solid fa-fire-burner fs-5"></i>
        </div>
        <div>
          <span class="fs-5 fw-bold text-white">ZIPPY <span class="text-success">KDS</span></span>
          <span class="badge bg-warning text-dark font-monospace small ms-2">STATION 01: MAIN KITCHEN</span>
        </div>
      </a>
      <span class="vr bg-secondary d-none d-md-block" style="height: 24px;"></span>
      <span class="small text-white-50 d-none d-md-inline-block">
        <i class="bi bi-cpu text-warning me-1"></i> WSJF Dynamic Dispatch Engine Active
      </span>
    </div>

    <div class="d-flex align-items-center gap-2">
      <a href="index.php" class="btn btn-sm btn-outline-light rounded-pill px-3">
        <i class="bi bi-arrow-left me-1"></i> Customer Storefront
      </a>
      <button class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="location.reload()">
        <i class="bi bi-arrow-clockwise me-1"></i> Refresh Queue
      </button>
      <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 text-white" onclick="toggleFullscreen()">
        <i class="bi bi-fullscreen me-1"></i> Fullscreen
      </button>
    </div>
  </header>

  <!-- 3-Column Kanban Board -->
  <main class="container-fluid p-3 p-md-4">
    <div class="row g-3">
      
      <!-- COLUMN 1: NEW CONFIRMED ORDERS -->
      <div class="col-lg-4">
        <div class="kds-lane" style="border-top: 4px solid #E11D48;">
          <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary border-opacity-25">
            <h5 class="fw-bold text-white mb-0">
              <i class="bi bi-bell-fill text-danger me-2"></i> New Orders
            </h5>
            <span class="badge bg-danger rounded-pill px-3 py-1 font-monospace" id="count-orders">
              <?= count($board['orders']) ?>
            </span>
          </div>

          <div class="kds-cards-container flex-grow-1 overflow-y-auto" id="col-orders">
            <?php if (empty($board['orders'])): ?>
              <div class="text-center py-5 text-white-50 small">
                <i class="bi bi-inbox fs-2 mb-2 d-block opacity-50"></i>
                No pending orders waiting.
              </div>
            <?php endif; ?>

            <?php foreach ($board['orders'] as $ord): ?>
              <div class="kds-ticket-card" id="ticket-<?= $ord['order_id'] ?>">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="fw-bold font-monospace text-white fs-6">#<?= sanitize($ord['order_number']) ?></span>
                  <span class="wsjf-pill" title="Cost of Delay / Prep Time">WSJF: <?= $ord['wsjf_score'] ?? '1.20' ?></span>
                </div>
                
                <div class="small text-white-50 mb-2">
                  <i class="bi bi-person me-1"></i> <?= sanitize($ord['full_name'] ?? 'Customer') ?> • 
                  <i class="bi bi-clock me-1"></i> <?= $ord['elapsed_minutes'] ?? 2 ?>m ago
                </div>

                <?php if (!empty($ord['order_notes'])): ?>
                  <div class="p-2 mb-2 rounded-2 small" style="background: rgba(245, 158, 11, 0.15); color: #FDE68A; border-left: 3px solid #F59E0B;">
                    <strong>Note:</strong> <?= sanitize($ord['order_notes']) ?>
                  </div>
                <?php endif; ?>

                <!-- Dish items list -->
                <div class="kds-dish-list mb-3">
                  <?php foreach ($ord['items'] as $item): ?>
                    <div class="kds-dish-item d-flex justify-content-between align-items-center small">
                      <div>
                        <span class="badge bg-success bg-opacity-25 text-mint me-1"><?= $item['quantity'] ?>&times;</span>
                        <strong class="text-white"><?= sanitize($item['item_name']) ?></strong>
                      </div>
                      <span class="badge bg-dark text-white-50 font-monospace small"><?= sanitize($item['kitchen_station'] ?? 'Handi') ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>

                <button class="btn btn-danger w-100 rounded-pill fw-bold py-2 shadow-xs" 
                        onclick="advanceKdsOrder(<?= $ord['order_id'] ?>, 'start_cooking')">
                  <i class="fa-solid fa-fire-burner me-1"></i> Start Cooking
                </button>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- COLUMN 2: IN PREPARATION / COOKING -->
      <div class="col-lg-4">
        <div class="kds-lane" style="border-top: 4px solid #F59E0B;">
          <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary border-opacity-25">
            <h5 class="fw-bold text-white mb-0">
              <i class="fa-solid fa-fire-burner text-warning me-2"></i> In Preparation
            </h5>
            <span class="badge bg-warning text-dark rounded-pill px-3 py-1 font-monospace" id="count-cooking">
              <?= count($board['cooking']) ?>
            </span>
          </div>

          <div class="kds-cards-container flex-grow-1 overflow-y-auto" id="col-cooking">
            <?php if (empty($board['cooking'])): ?>
              <div class="text-center py-5 text-white-50 small">
                <i class="bi bi-cup-hot fs-2 mb-2 d-block opacity-50"></i>
                No orders currently on the stoves.
              </div>
            <?php endif; ?>

            <?php foreach ($board['cooking'] as $ord): ?>
              <div class="kds-ticket-card" id="ticket-<?= $ord['order_id'] ?>" style="border-left: 4px solid #F59E0B;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="fw-bold font-monospace text-white fs-6">#<?= sanitize($ord['order_number']) ?></span>
                  <span class="badge bg-warning bg-opacity-25 text-warning font-monospace">COOKING</span>
                </div>

                <div class="small text-white-50 mb-2">
                  <i class="bi bi-person me-1"></i> <?= sanitize($ord['full_name'] ?? 'Customer') ?> • 
                  <i class="bi bi-clock me-1"></i> <?= $ord['elapsed_minutes'] ?? 10 ?>m elapsed
                </div>

                <?php if (!empty($ord['order_notes'])): ?>
                  <div class="p-2 mb-2 rounded-2 small" style="background: rgba(245, 158, 11, 0.15); color: #FDE68A;">
                    <strong>Note:</strong> <?= sanitize($ord['order_notes']) ?>
                  </div>
                <?php endif; ?>

                <!-- Dish items with touch checkboxes -->
                <div class="kds-dish-list mb-3">
                  <?php foreach ($ord['items'] as $item): ?>
                    <div class="kds-dish-item d-flex justify-content-between align-items-center small">
                      <div class="form-check m-0">
                        <input class="form-check-input" type="checkbox" id="chk-<?= $item['order_item_id'] ?? rand(1,999) ?>">
                        <label class="form-check-label text-white ms-1 cursor-pointer" for="chk-<?= $item['order_item_id'] ?? rand(1,999) ?>">
                          <strong><?= $item['quantity'] ?>&times; <?= sanitize($item['item_name']) ?></strong>
                        </label>
                      </div>
                      <span class="badge bg-dark text-white-50 font-monospace small"><?= sanitize($item['kitchen_station'] ?? 'Handi') ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>

                <button class="btn btn-warning w-100 rounded-pill fw-bold py-2 text-dark shadow-xs" 
                        onclick="advanceKdsOrder(<?= $ord['order_id'] ?>, 'mark_ready')">
                  <i class="bi bi-check-all me-1"></i> Mark All Items Ready
                </button>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- COLUMN 3: READY TO DISPATCH -->
      <div class="col-lg-4">
        <div class="kds-lane" style="border-top: 4px solid #10B981;">
          <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary border-opacity-25">
            <h5 class="fw-bold text-white mb-0">
              <i class="bi bi-box-seam-fill text-success me-2"></i> Ready for Pickup
            </h5>
            <span class="badge bg-success rounded-pill px-3 py-1 font-monospace" id="count-ready">
              <?= count($board['ready']) ?>
            </span>
          </div>

          <div class="kds-cards-container flex-grow-1 overflow-y-auto" id="col-ready">
            <?php if (empty($board['ready'])): ?>
              <div class="text-center py-5 text-white-50 small">
                <i class="bi bi-bag-check fs-2 mb-2 d-block opacity-50"></i>
                No packed bags awaiting courier pickup.
              </div>
            <?php endif; ?>

            <?php foreach ($board['ready'] as $ord): ?>
              <div class="kds-ticket-card" id="ticket-<?= $ord['order_id'] ?>" style="border-left: 4px solid #10B981;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="fw-bold font-monospace text-white fs-6">#<?= sanitize($ord['order_number']) ?></span>
                  <span class="badge bg-success font-monospace">PACKED & READY</span>
                </div>

                <div class="small text-white-50 mb-2">
                  <i class="bi bi-person me-1"></i> <?= sanitize($ord['full_name'] ?? 'Customer') ?> • 
                  <i class="bi bi-bicycle me-1"></i> <?= sanitize($ord['delivery_partner_name'] ?? 'Ramesh Kumar') ?>
                </div>

                <div class="p-2 mb-3 rounded-2 text-center" style="background: rgba(16, 185, 129, 0.15); border: 1px dashed #10B981;">
                  <span class="small text-white-50 d-block">Handover Secret OTP:</span>
                  <span class="fw-bold font-monospace text-mint fs-5"><?= sanitize($ord['delivery_otp'] ?? '839210') ?></span>
                </div>

                <button class="btn btn-success w-100 rounded-pill fw-bold py-2 shadow-xs" 
                        onclick="advanceKdsOrder(<?= $ord['order_id'] ?>, 'dispatch')">
                  <i class="bi bi-bicycle me-1"></i> Handover to Courier
                </button>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

    </div>
  </main>

  <!-- KDS Audio Alert & State Advance Engine -->
  <script>
    async function advanceKdsOrder(orderId, action) {
      try {
        const res = await fetch('api/kitchen-action.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ order_id: orderId, action: action })
        });
        const data = await res.json();
        if (data.success) {
          // Play short confirmation beep
          playBeep();
          // Reload board to show updated WSJF ordering
          location.reload();
        } else {
          alert('Could not update order status.');
        }
      } catch (e) {
        location.reload();
      }
    }

    function playBeep() {
      try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.frequency.value = 880;
        gain.gain.value = 0.1;
        osc.start();
        setTimeout(() => { osc.stop(); }, 150);
      } catch (e) {}
    }

    function toggleFullscreen() {
      if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {});
      } else {
        document.exitFullscreen().catch(() => {});
      }
    }
  </script>

</body>
</html>
