<?php
/**
 * Live Order Tracking & Dynamic ETA
 * MCSP-232: E-Commerce Platform for Food Order
 */
$pageTitle = 'Live Order Tracking - Zippy';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/models/Order.php';

$orderNumber = $_GET['order'] ?? 'ZIP-948261';
$order = Order::getByOrderNumber($orderNumber);
$status = strtoupper($order['order_status'] ?? 'COOKING');

// Map status to progress step
$stepIndex = 2;
if ($status === 'PLACED' || $status === 'CONFIRMED') $stepIndex = 1;
if ($status === 'COOKING') $stepIndex = 2;
if ($status === 'READY') $stepIndex = 2.5;
if ($status === 'OUT_FOR_DELIVERY') $stepIndex = 3;
if ($status === 'DELIVERED') $stepIndex = 4;
?>

  <main class="container-xl py-4" style="max-width: 960px;">
    
    <!-- Top Tracking Banner Card -->
    <div class="p-4 bg-white rounded-4 border shadow-sm mb-4">
      
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 pb-3 border-bottom mb-4">
        <div>
          <span class="badge bg-success text-white fw-bold px-3 py-1 rounded-pill mb-2 font-monospace">
            ORDER #<?= sanitize($order['order_number'] ?? $orderNumber) ?>
          </span>
          <h3 class="fw-bold text-pine mb-1" id="live-status-title">
            <?php if ($status === 'DELIVERED'): ?>
              Food Delivered! Enjoy your meal.
            <?php elseif ($status === 'OUT_FOR_DELIVERY'): ?>
              Rider is on the way to your door!
            <?php elseif ($status === 'READY'): ?>
              Food is packed and ready for pickup!
            <?php else: ?>
              Your food is being prepared!
            <?php endif; ?>
          </h3>
          <p class="small text-muted mb-0">Meghana Foods (Indiranagar) • Handi & Tandoor Station</p>
        </div>

        <div class="text-end">
          <div class="small text-muted fw-bold text-uppercase">ESTIMATED DELIVERY</div>
          <div class="fw-extrabold text-success font-monospace fs-3" id="live-eta-countdown">
            <?= $status === 'DELIVERED' ? 'ARRIVED' : '18:40 mins' ?>
          </div>
        </div>
      </div>

      <!-- 4-Stage Stepper Progress Bar -->
      <div class="progress-stepper position-relative py-3">
        <div class="progress-stepper-line position-absolute top-50 start-0 end-0 translate-middle-y bg-light" style="height: 4px; z-index: 1;">
          <div class="bg-success h-100 transition-all" id="stepper-fill-bar" style="width: <?= min(100, $stepIndex * 25) ?>%; background-color: var(--lush-forest) !important;"></div>
        </div>
        
        <div class="d-flex justify-content-between position-relative" style="z-index: 2;">
          
          <!-- Step 1: Placed -->
          <div class="step-node text-center <?= $stepIndex >= 1 ? 'completed' : '' ?>">
            <div class="step-circle mx-auto mb-2 rounded-circle d-flex align-items-center justify-content-center shadow-xs bg-success text-white" style="width: 38px; height: 38px;">
              <i class="bi bi-check-lg"></i>
            </div>
            <div class="fw-bold small text-pine">Order Placed</div>
            <div class="text-muted" style="font-size: 0.72rem;">Confirmed</div>
          </div>

          <!-- Step 2: Cooking (In Kitchen) -->
          <div class="step-node text-center <?= $stepIndex >= 2 ? ($stepIndex == 2 ? 'active' : 'completed') : '' ?>">
            <div class="step-circle mx-auto mb-2 rounded-circle d-flex align-items-center justify-content-center shadow-xs <?= $stepIndex >= 2 ? 'bg-success text-white' : 'bg-light text-muted' ?>" style="width: 38px; height: 38px;">
              <i class="fa-solid fa-fire-burner"></i>
            </div>
            <div class="fw-bold small text-pine">In Kitchen</div>
            <div class="text-success fw-bold" style="font-size: 0.72rem;">Cooking Now</div>
          </div>

          <!-- Step 3: Out for Delivery -->
          <div class="step-node text-center <?= $stepIndex >= 3 ? ($stepIndex == 3 ? 'active' : 'completed') : '' ?>">
            <div class="step-circle mx-auto mb-2 rounded-circle d-flex align-items-center justify-content-center shadow-xs <?= $stepIndex >= 3 ? 'bg-success text-white' : 'bg-light text-muted' ?>" style="width: 38px; height: 38px;">
              <i class="fa-solid fa-motorcycle"></i>
            </div>
            <div class="fw-bold small text-pine">On the Way</div>
            <div class="text-muted" style="font-size: 0.72rem;">Rider Dispatched</div>
          </div>

          <!-- Step 4: Delivered -->
          <div class="step-node text-center <?= $stepIndex >= 4 ? 'completed' : '' ?>">
            <div class="step-circle mx-auto mb-2 rounded-circle d-flex align-items-center justify-content-center shadow-xs <?= $stepIndex >= 4 ? 'bg-success text-white' : 'bg-light text-muted' ?>" style="width: 38px; height: 38px;">
              <i class="bi bi-house-door-fill"></i>
            </div>
            <div class="fw-bold small text-pine">Delivered</div>
            <div class="text-muted" style="font-size: 0.72rem;">At Doorstep</div>
          </div>

        </div>
      </div>

    </div>

    <div class="row g-4">
      
      <!-- LEFT: Simulated Route Map & Live Courier Card -->
      <div class="col-lg-7">
        
        <!-- Courier Card -->
        <div class="p-4 bg-white rounded-4 border shadow-xs mb-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-pine mb-0">Delivery Partner</h5>
            <span class="badge bg-success bg-opacity-10 text-success fw-bold">ON DUTY</span>
          </div>

          <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
              <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center text-success fs-3" style="width: 55px; height: 55px;">
                <i class="bi bi-person-badge-fill"></i>
              </div>
              <div>
                <h6 class="fw-bold text-pine mb-0"><?= sanitize($order['delivery_partner_name'] ?? 'Ramesh Kumar') ?></h6>
                <div class="small text-muted">TVS Apache • <i class="bi bi-star-fill text-warning"></i> 4.9 Rating (2.4k Deliveries)</div>
                <div class="small text-muted">Vaccinated • Temperature 98.4°F</div>
              </div>
            </div>

            <a href="tel:<?= sanitize($order['delivery_partner_phone'] ?? '+919845012345') ?>" class="btn btn-outline-success rounded-pill px-3 fw-bold small">
              <i class="bi bi-telephone-fill me-1"></i> Call Rider
            </a>
          </div>

          <!-- Secret OTP Box -->
          <div class="p-3 mt-3 rounded-3 d-flex justify-content-between align-items-center" style="background-color: var(--lush-mint-soft); border: 1px dashed var(--lush-forest);">
            <div>
              <span class="small text-muted fw-bold text-uppercase d-block">Secret Verification OTP:</span>
              <span class="small text-muted">Share with rider only when order is handed over</span>
            </div>
            <span class="fw-extrabold text-pine font-monospace fs-4" style="letter-spacing: 2px;">
              <?= sanitize($order['delivery_otp'] ?? '839210') ?>
            </span>
          </div>
        </div>

        <!-- Simulated GPS Route Map -->
        <div class="p-4 bg-white rounded-4 border shadow-xs">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold text-pine mb-0"><i class="fa-solid fa-map-location-dot text-success me-2"></i> Live Courier GPS Tracking</h6>
            <span class="badge bg-light text-muted border">Simulation Mode</span>
          </div>

          <div class="position-relative rounded-3 overflow-hidden border" style="height: 240px; background: #e5ece7;">
            <!-- Stylized simulated road canvas / map background -->
            <div class="w-100 h-100 d-flex flex-column justify-content-center align-items-center text-center p-3" style="background: radial-gradient(#d1e4d7 15%, transparent 16%) 0 0, radial-gradient(#d1e4d7 15%, transparent 16%) 8px 8px, radial-gradient(rgba(255,255,255,.1) 15%, transparent 20%) 0 1px, radial-gradient(rgba(255,255,255,.1) 15%, transparent 20%) 8px 9px; background-color:#edf5f0; background-size:16px 16px;">
              
              <div class="d-flex align-items-center gap-4 bg-white p-3 rounded-4 shadow-sm border">
                <div class="text-center">
                  <i class="fa-solid fa-store text-danger fs-4 mb-1"></i>
                  <div class="small fw-bold text-pine" style="font-size: 0.72rem;">Meghana Foods</div>
                </div>
                
                <div class="d-flex align-items-center gap-1 text-success">
                  <div class="spinner-grow spinner-grow-sm" role="status"></div>
                  <i class="fa-solid fa-motorcycle fs-5"></i>
                  <div class="small fw-bold">1.2 km away</div>
                </div>

                <div class="text-center">
                  <i class="bi bi-geo-alt-fill text-success fs-4 mb-1"></i>
                  <div class="small fw-bold text-pine" style="font-size: 0.72rem;">Indiranagar Flat</div>
                </div>
              </div>

              <div class="small text-muted mt-3">Rider is en route via 100 Feet Road • Minimal traffic</div>
            </div>
          </div>
        </div>

      </div>

      <!-- RIGHT: Order Summary & Quick Actions -->
      <div class="col-lg-5">
        <div class="p-4 bg-white rounded-4 border shadow-xs mb-4">
          <h5 class="fw-bold text-pine pb-2 border-bottom mb-3">Order Details</h5>
          
          <div class="d-flex flex-column gap-2 mb-3">
            <?php if (!empty($order['items'])): ?>
              <?php foreach ($order['items'] as $it): ?>
                <div class="d-flex justify-content-between small">
                  <div>
                    <span class="food-type-icon <?= !empty($it['is_veg']) ? 'veg' : 'non-veg' ?> me-1"></span>
                    <?= sanitize($it['item_name']) ?> &times; <?= $it['quantity'] ?>
                  </div>
                  <span class="fw-bold text-pine"><?= formatPrice($it['line_total'] ?? ($it['unit_price'] * $it['quantity'])) ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="border-top pt-2 d-flex justify-content-between fw-bold text-pine">
            <span>Bill Total</span>
            <span class="text-success"><?= formatPrice($order['total_amount'] ?? 0) ?></span>
          </div>

          <div class="mt-4 d-flex flex-column gap-2">
            <a href="invoice.php?order=<?= urlencode($orderNumber) ?>" class="btn btn-outline-secondary rounded-pill fw-bold w-100">
              <i class="bi bi-printer me-1"></i> View GST Tax Invoice
            </a>
            <a href="kitchen-display.php" class="btn btn-warning rounded-pill fw-bold text-dark w-100">
              <i class="fa-solid fa-fire-burner me-1"></i> Open Kitchen KDS (Advance Order)
            </a>
            <a href="help.php" class="btn btn-outline-success rounded-pill fw-bold w-100">
              <i class="bi bi-headset me-1"></i> Need Help with this Order?
            </a>
          </div>

        </div>
      </div>

    </div>

  </main>

  <!-- Real-Time Polling Script for Live Order Updates -->
  <script>
    const currentOrderNum = '<?= urlencode($orderNumber) ?>';
    
    // Poll API every 5 seconds for status changes
    setInterval(async () => {
      try {
        const res = await fetch(`api/order-status.php?order=${currentOrderNum}`);
        const data = await res.json();
        if (data.success) {
          const fillBar = document.getElementById('stepper-fill-bar');
          if (fillBar) fillBar.style.width = `${data.progressPercent}%`;
          
          const title = document.getElementById('live-status-title');
          if (title) title.textContent = data.statusText;
          
          if (data.status === 'DELIVERED') {
            document.getElementById('live-eta-countdown').textContent = 'ARRIVED';
          }
        }
      } catch (e) {
        // network polling silent catch
      }
    }, 5000);
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
