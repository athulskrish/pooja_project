<?php
/**
 * Admin Delivery View: Fleet Presence, Vehicle Roster, COD Cash & Rider Salary Management
 * Zippy - Online Food Ordering Platform
 * MCSP-232: MCA Major Project | Student: Pooja JP
 */

$pageTitle = 'Delivery Fleet & Staff View';
$activePage = 'delivery_view';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$currentUser = current_user();

// Handle Rider Fleet Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
    } else {
        $action = $_POST['action'];

        // 1. Toggle Rider Presence / Duty Status
        if ($action === 'toggle_duty') {
            $profileId = (int)$_POST['profile_id'];
            $newStatus = clean_input($_POST['duty_status']);
            db_query("UPDATE `staff_profiles` SET `duty_status` = :st WHERE `profile_id` = :id", [
                ':st' => $newStatus,
                ':id' => $profileId
            ]);
            set_flash('success', "Delivery partner presence status updated to {$newStatus}.");
            header("Location: " . ADMIN_URL . "/delivery-view.php");
            exit;
        }

        // 2. Settle COD Cash in Hand to Store Cashier
        if ($action === 'settle_cod_cash') {
            $profileId = (int)$_POST['profile_id'];
            $riderProfile = db_fetch_one("
                SELECT sp.*, u.full_name, u.user_id 
                FROM `staff_profiles` sp 
                JOIN `users` u ON sp.user_id = u.user_id 
                WHERE sp.profile_id = :id
            ", [':id' => $profileId]);

            if ($riderProfile && (float)$riderProfile['cod_balance'] > 0) {
                $amountToSettle = (float)$riderProfile['cod_balance'];
                
                // Reset rider COD balance to 0
                db_query("UPDATE `staff_profiles` SET `cod_balance` = 0.00 WHERE `profile_id` = :id", [':id' => $profileId]);

                // Record into central money_transactions
                $ref = 'COD-SETTLE-' . date('Ym') . '-' . str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                record_money_transaction(
                    null,
                    $riderProfile['user_id'],
                    'COD_DEPOSIT_STORE',
                    'INWARD',
                    'COD_CASH',
                    $amountToSettle,
                    'SETTLED',
                    $ref,
                    'Rider: ' . $riderProfile['full_name'],
                    "Rider {$riderProfile['full_name']} deposited collected COD cash of " . format_inr($amountToSettle) . " to store cashier register"
                );

                set_flash('success', "COD Cash of " . format_inr($amountToSettle) . " from {$riderProfile['full_name']} successfully deposited to store cash register.");
            } else {
                set_flash('info', "No outstanding COD cash to settle for this rider.");
            }
            header("Location: " . ADMIN_URL . "/delivery-view.php");
            exit;
        }

        // 3. Disburse Rider Payout (Salary + Commission)
        if ($action === 'disburse_payout') {
            $userId = (int)$_POST['user_id'];
            $monthYear = clean_input($_POST['month_year'] ?? date('F Y'));
            $baseAmount = (float)$_POST['base_amount'];
            $incentives = (float)($_POST['incentives_amount'] ?? 0);
            $deductions = (float)($_POST['deductions_amount'] ?? 0);
            $netPaid = $baseAmount + $incentives - $deductions;
            $paymentMode = clean_input($_POST['payment_mode'] ?? 'UPI');
            $notes = clean_input($_POST['notes'] ?? 'Monthly delivery fleet stipend and trip commission payout');
            $ref = 'PAY-RID-' . date('Ym') . '-' . str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

            // Record into staff_salary_payouts
            db_query("
                INSERT INTO `staff_salary_payouts` 
                (`user_id`, `month_year`, `base_amount`, `incentives_amount`, `deductions_amount`, `net_paid`, `payment_mode`, `transaction_reference`, `payout_status`, `paid_at`, `notes`) 
                VALUES (:uid, :my, :base, :inc, :ded, :net, :mode, :ref, 'PAID', NOW(), :notes)
            ", [
                ':uid' => $userId,
                ':my' => $monthYear,
                ':base' => $baseAmount,
                ':inc' => $incentives,
                ':ded' => $deductions,
                ':net' => $netPaid,
                ':mode' => $paymentMode,
                ':ref' => $ref,
                ':notes' => $notes
            ]);

            // Record into central money_transactions
            $staffUser = db_fetch_one("SELECT full_name FROM `users` WHERE `user_id` = :id", [':id' => $userId]);
            $staffName = $staffUser['full_name'] ?? 'Delivery Rider';

            record_money_transaction(
                null,
                $userId,
                'STAFF_SALARY_PAYOUT',
                'OUTWARD',
                $paymentMode === 'UPI' ? 'UPI' : 'BANK_TRANSFER',
                $netPaid,
                'COMPLETED',
                $ref,
                'Store Finance & Logistics',
                "Delivery payout disbursed to {$staffName} for {$monthYear}"
            );

            set_flash('success', "Delivery payout of " . format_inr($netPaid) . " successfully disbursed to {$staffName} (Ref: {$ref}).");
            header("Location: " . ADMIN_URL . "/delivery-view.php");
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';

// Fetch all Delivery Staff Profiles
$deliveryFleet = db_fetch_all("
    SELECT sp.*, u.full_name, u.email, u.phone_number, u.created_at AS user_since
    FROM `staff_profiles` sp
    JOIN `users` u ON sp.user_id = u.user_id
    WHERE sp.department = 'DELIVERY'
    ORDER BY sp.base_salary DESC, sp.joined_date ASC
");

// Fetch Latest Payouts for Delivery Riders
$riderIds = array_column($deliveryFleet, 'user_id');
$latestPayoutsByUser = [];
$payoutHistory = [];

if (!empty($riderIds)) {
    $placeholders = implode(',', array_fill(0, count($riderIds), '?'));
    
    // History
    $payoutHistory = db_fetch_all("
        SELECT p.*, u.full_name, u.phone_number, sp.designation, sp.vehicle_details
        FROM `staff_salary_payouts` p
        JOIN `users` u ON p.user_id = u.user_id
        JOIN `staff_profiles` sp ON p.user_id = sp.user_id
        WHERE p.user_id IN ($placeholders)
        ORDER BY p.paid_at DESC
        LIMIT 25
    ", $riderIds);

    // Current month payouts map
    $currentMonthStr = date('F Y');
    $currentMonthPayouts = db_fetch_all("
        SELECT * FROM `staff_salary_payouts` 
        WHERE `user_id` IN ($placeholders) AND `month_year` = ?
    ", array_merge($riderIds, [$currentMonthStr]));

    foreach ($currentMonthPayouts as $cp) {
        $latestPayoutsByUser[$cp['user_id']] = $cp;
    }
}

// KPI Aggregations & 1 Driver 1 Delivery Capacity Tracking
$totalRiders = count($deliveryFleet);
$onlineRiders = count(array_filter($deliveryFleet, fn($r) => in_array($r['duty_status'], ['ON_DUTY', 'ON_BREAK'])));
$totalCodHeld = array_sum(array_column($deliveryFleet, 'cod_balance'));
$totalDeliveriesCompleted = (int)db_fetch_val("SELECT COUNT(*) FROM `delivery_dispatches` WHERE `delivery_status` = 'DELIVERED'");

// Map active trip for each rider
$activeTripsMap = [];
$busyRidersCount = 0;
$availableRidersCount = 0;
foreach ($deliveryFleet as $r) {
    $trip = get_driver_active_delivery((int)$r['user_id'], $r['full_name']);
    $activeTripsMap[$r['user_id']] = $trip;
    if ($r['duty_status'] === 'ON_DUTY') {
        if ($trip) {
            $busyRidersCount++;
        } else {
            $availableRidersCount++;
        }
    }
}
?>

<!-- ===========================================================================
     KPI METRICS ROW
     =========================================================================== -->
<div class="row g-3 mb-4">
  
  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-bold text-uppercase" style="letter-spacing:0.5px;">DELIVERY FLEET</span>
        <div class="rounded-3 p-2 text-info" style="background:#F0F9FF;">
          <i class="bi bi-bicycle fs-4"></i>
        </div>
      </div>
      <h3 class="fw-bold font-outfit text-dark mb-1"><?= $totalRiders ?> Active Couriers</h3>
      <span class="small text-muted">Dedicated Delivery Fleet</span>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100" style="border-top: 3px solid #3B82F6 !important;">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-bold text-uppercase" style="letter-spacing:0.5px;">RIDERS ONLINE</span>
        <div class="rounded-3 p-2 text-primary" style="background:#EFF6FF;">
          <span class="live-pulse-dot blue"></span>
        </div>
      </div>
      <h3 class="fw-bold font-outfit text-dark mb-1"><?= $onlineRiders ?> / <?= $totalRiders ?> Online</h3>
      <span class="small text-primary fw-bold"><i class="bi bi-bicycle me-1"></i><?= $availableRidersCount ?> Free • <?= $busyRidersCount ?> on Delivery</span>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100" style="border-top: 3px solid #F59E0B !important;">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-bold text-uppercase" style="letter-spacing:0.5px;">COD CASH IN FLEET</span>
        <div class="rounded-3 p-2 text-warning" style="background:#FFFBEB;">
          <i class="bi bi-cash-stack fs-4"></i>
        </div>
      </div>
      <h3 class="fw-bold font-outfit text-dark mb-1"><?= format_inr($totalCodHeld) ?></h3>
      <span class="small text-warning fw-bold"><i class="bi bi-exclamation-circle me-1"></i>Cash in Hand to Settle</span>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-bold text-uppercase" style="letter-spacing:0.5px;">TOTAL DELIVERIES</span>
        <div class="rounded-3 p-2 text-success" style="background:#EDFBF2;">
          <i class="bi bi-house-check fs-4"></i>
        </div>
      </div>
      <h3 class="fw-bold font-outfit text-dark mb-1"><?= number_format($totalDeliveriesCompleted) ?></h3>
      <span class="small text-success fw-bold"><i class="bi bi-shield-check me-1"></i>Verified Doorstep Deliveries</span>
    </div>
  </div>

</div>

<style>
  .rider-roster-card {
    transition: box-shadow 160ms ease, transform 160ms ease;
  }

  .rider-roster-card:hover {
    box-shadow: 0 0.5rem 1.25rem rgba(20, 40, 25, 0.08) !important;
    transform: translateY(-2px);
  }

  .rider-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
  }

  .rider-summary-item {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 0.2rem;
    min-width: 0;
    min-height: 60px;
    padding: 0.6rem 0.7rem;
    border-radius: 0.75rem;
    background: #f7f9f7;
    font-size: 0.82rem;
    overflow-wrap: anywhere;
  }

  .rider-summary-label {
    color: #6c757d;
    font-size: 0.72rem;
  }

  .rider-action-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
  }

  .rider-duty-action {
    grid-column: 1 / -1;
  }

  .fleet-map-controls {
    min-width: 0;
  }

  .fleet-map-canvas {
    height: 320px;
    width: 100%;
    z-index: 1;
  }

  @media (max-width: 767.98px) {
    .fleet-map-controls,
    .fleet-map-filters {
      width: 100%;
    }

    .fleet-map-canvas {
      height: 260px;
    }

    .fleet-map-filters > .btn {
      flex: 1 1 auto;
    }

    .fleet-map-utility {
      flex: 1 1 auto;
      justify-content: space-between;
    }

    .rider-summary-item {
      min-height: 56px;
    }

  }

  @media (prefers-reduced-motion: reduce) {
    .rider-roster-card {
      transition: none;
    }

    .rider-roster-card:hover {
      transform: none;
    }
  }
</style>

<!-- ===========================================================================
     LIVE MULTI-COURIER FLEET GPS TELEMETRY MAP
     =========================================================================== -->
<div class="card border-0 rounded-4 shadow-sm mb-4 overflow-hidden bg-white">
  <div class="p-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="d-flex align-items-center gap-2">
      <span class="live-pulse-dot blue"></span>
      <div>
        <h6 class="fw-bold font-outfit mb-0 text-dark">Live Courier GPS Telemetry & Active Routes</h6>
        <span class="small text-muted" style="font-size:0.75rem;">Live routes across Indiranagar and Koramangala</span>
      </div>
    </div>

    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-light text-muted border font-monospace small" id="fleetSyncTimer" style="font-size:0.7rem;">
        <i class="bi bi-arrow-repeat me-1 text-primary"></i>Sync: Waiting
      </span>
      <button class="btn btn-outline-primary btn-sm rounded-pill px-3" type="button"
              data-bs-toggle="collapse" data-bs-target="#fleetMapPanel"
              aria-expanded="false" aria-controls="fleetMapPanel">
        <i class="bi bi-map me-1"></i><span class="fleet-map-toggle-label">Show map</span>
      </button>
    </div>
  </div>

  <div class="collapse" id="fleetMapPanel">
    <div class="fleet-map-controls d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 border-top">
      <div class="btn-group btn-group-sm fleet-map-filters" role="group" aria-label="Fleet map filters">
        <button type="button" class="btn btn-outline-secondary active" id="mapFilterAllBtn" onclick="filterMapMarkers('ALL')">All Fleet</button>
        <button type="button" class="btn btn-outline-primary" id="mapFilterTransitBtn" onclick="filterMapMarkers('TRANSIT')">
          <i class="bi bi-bicycle me-1"></i>In Transit (<?= $busyRidersCount ?>)
        </button>
        <button type="button" class="btn btn-outline-success" id="mapFilterAvailableBtn" onclick="filterMapMarkers('AVAILABLE')">
          <i class="bi bi-check-circle me-1"></i>Available (<?= $availableRidersCount ?>)
        </button>
      </div>
      <div class="fleet-map-utility d-flex align-items-center gap-2">
        <button type="button" class="btn btn-light btn-sm border rounded-pill px-3 shadow-xs" onclick="recenterFleetMap()" title="Recenter to Zippy Hub">
          <i class="bi bi-crosshair me-1"></i> Hub
        </button>
      </div>
    </div>
    <div id="fleetMap" class="fleet-map-canvas"></div>
  </div>
</div>

<!-- ===========================================================================
     DELIVERY FLEET ROSTER & COD CASH RECONCILIATION
     =========================================================================== -->
<div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
  
  <div class="d-flex flex-wrap align-items-center justify-content-between border-bottom pb-3 mb-3 gap-2">
    <div>
      <h4 class="fw-bold font-outfit mb-0 text-dark">Active Delivery Couriers Roster</h4>
      <span class="small text-muted">Rider status, dispatch availability, cash and payouts.</span>
    </div>

    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-light text-muted border rounded-pill" id="riderResultCount" aria-live="polite"><?= $totalRiders ?> shown</span>
      <span class="small text-muted"><i class="bi bi-geo-alt me-1"></i>Indiranagar &amp; Koramangala</span>
    </div>
  </div>

  <div class="row g-2 align-items-end mb-4" aria-label="Filter delivery riders">
    <div class="col-lg-4">
      <label for="riderSearch" class="form-label small fw-semibold text-muted mb-1">Search riders</label>
      <div class="input-group">
        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
        <input type="search" class="form-control bg-light border-start-0" id="riderSearch"
               placeholder="Name, role or vehicle" autocomplete="off">
      </div>
    </div>
    <div class="col-sm-6 col-lg-2">
      <label for="riderDutyFilter" class="form-label small fw-semibold text-muted mb-1">Duty / availability</label>
      <select class="form-select bg-light" id="riderDutyFilter">
        <option value="">All statuses</option>
        <option value="available">Available for dispatch</option>
        <option value="busy">On active delivery</option>
        <option value="ON_BREAK">On break</option>
        <option value="OFF_DUTY">Offline</option>
        <option value="ON_LEAVE">On leave</option>
      </select>
    </div>
    <div class="col-sm-6 col-lg-2">
      <label for="riderTripFilter" class="form-label small fw-semibold text-muted mb-1">Active trip</label>
      <select class="form-select bg-light" id="riderTripFilter">
        <option value="">Any trip status</option>
        <option value="yes">On an active trip</option>
        <option value="no">No active trip</option>
      </select>
    </div>
    <div class="col-sm-6 col-lg-2">
      <label for="riderCodFilter" class="form-label small fw-semibold text-muted mb-1">COD cash</label>
      <select class="form-select bg-light" id="riderCodFilter">
        <option value="">All COD balances</option>
        <option value="due">Cash to settle</option>
        <option value="clear">No cash held</option>
      </select>
    </div>
    <div class="col-sm-6 col-lg-2">
      <label for="riderPayoutFilter" class="form-label small fw-semibold text-muted mb-1">Monthly payout</label>
      <select class="form-select bg-light" id="riderPayoutFilter">
        <option value="">All payout statuses</option>
        <option value="paid">Paid</option>
        <option value="due">Payment due</option>
      </select>
    </div>
  </div>

  <!-- Rider Cards Grid -->
  <div class="row g-3" id="riderRosterGrid">
    <?php foreach ($deliveryFleet as $rider): ?>
      <?php 
        $uid = (int)$rider['user_id'];
        $curPayout = $latestPayoutsByUser[$uid] ?? null;
        $isPaidThisMonth = !empty($curPayout) && $curPayout['payout_status'] === 'PAID';
        $activeTrip = $activeTripsMap[$uid] ?? null;
        $hasCodBalance = (float)$rider['cod_balance'] > 0;
        $availability = $activeTrip && $rider['duty_status'] === 'ON_DUTY'
          ? 'busy'
          : match($rider['duty_status']) {
            'ON_DUTY' => 'available',
            'ON_BREAK' => 'ON_BREAK',
            'ON_LEAVE' => 'ON_LEAVE',
            default => 'OFF_DUTY'
          };

        $dutyBadge = match(true) {
          !empty($activeTrip) => ['class' => 'bg-primary-subtle text-primary', 'icon' => 'bi-bicycle', 'label' => 'On delivery'],
          $rider['duty_status'] === 'ON_DUTY' => ['class' => 'bg-success-subtle text-success', 'icon' => 'bi-check-circle-fill', 'label' => 'Available'],
          $rider['duty_status'] === 'ON_BREAK' => ['class' => 'bg-warning-subtle text-warning-emphasis', 'icon' => 'bi-pause-circle-fill', 'label' => 'On break'],
          $rider['duty_status'] === 'ON_LEAVE' => ['class' => 'bg-danger-subtle text-danger', 'icon' => 'bi-x-circle-fill', 'label' => 'On leave'],
          default => ['class' => 'bg-secondary-subtle text-secondary', 'icon' => 'bi-moon-fill', 'label' => 'Offline']
        };
      ?>
      <div class="col-md-6 col-xl-4 rider-roster-item"
           data-search="<?= e($rider['full_name'] . ' ' . $rider['designation'] . ' ' . ($rider['vehicle_details'] ?: 'Electric Scooter')) ?>"
           data-duty="<?= e($availability) ?>"
           data-trip="<?= $activeTrip ? 'yes' : 'no' ?>"
           data-cod="<?= $hasCodBalance ? 'due' : 'clear' ?>"
           data-payout="<?= $isPaidThisMonth ? 'paid' : 'due' ?>">
        <div class="card border-0 rounded-4 p-3 p-lg-4 h-100 shadow-sm d-flex flex-column justify-content-between rider-roster-card">
          
          <div>
            <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
              <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white fs-5 flex-shrink-0" style="width: 48px; height: 48px; background: #1E40AF;">
                  <?= strtoupper(substr($rider['full_name'], 0, 1)) ?>
                </div>
                <div class="min-w-0">
                  <h6 class="fw-bold font-outfit text-dark mb-0"><?= e($rider['full_name']) ?></h6>
                  <span class="small text-muted"><?= e($rider['designation']) ?> · #RID-<?= str_pad((string)$rider['profile_id'], 3, '0', STR_PAD_LEFT) ?></span>
                </div>
              </div>

              <span class="badge <?= $dutyBadge['class'] ?> rounded-pill px-2 py-2 small flex-shrink-0">
                <i class="bi <?= $dutyBadge['icon'] ?> me-1"></i><?= $dutyBadge['label'] ?>
              </span>
            </div>

            <div class="p-2 px-3 rounded-3 mb-3 d-flex align-items-center justify-content-between gap-2 small <?= $activeTrip ? 'bg-primary-subtle' : ($rider['duty_status'] === 'ON_DUTY' ? 'bg-success-subtle' : 'bg-light') ?>">
              <div class="d-flex align-items-center gap-2">
                <i class="bi <?= $activeTrip ? 'bi-bicycle text-primary fs-5' : ($rider['duty_status'] === 'ON_DUTY' ? 'bi-check-circle-fill text-success fs-5' : 'bi-clock text-muted fs-5') ?>"></i>
                <div>
                  <div class="fw-bold text-dark" style="line-height:1.2;">
                    <?= $activeTrip ? 'Order #ZIP-' . (int)$activeTrip['order_id'] : ($rider['duty_status'] === 'ON_DUTY' ? 'Dispatch capacity' : 'Dispatch paused') ?>
                  </div>
                  <span class="text-muted" style="font-size:0.72rem;"><?= $activeTrip ? 'Delivery in progress · 1/1 slots in use' : ($rider['duty_status'] === 'ON_DUTY' ? '0/1 delivery slots in use' : 'Not accepting deliveries') ?></span>
                </div>
              </div>
            </div>

            <div class="rider-summary-grid mb-2">
              <div class="rider-summary-item">
                <span class="rider-summary-label"><i class="bi bi-clock me-1"></i>Shift</span>
                <strong class="font-monospace"><?= e($rider['shift_timings']) ?></strong>
              </div>
              <div class="rider-summary-item">
                <span class="rider-summary-label"><i class="bi bi-telephone me-1"></i>Contact</span>
                <a href="tel:<?= e($rider['phone_number']) ?>" class="text-primary text-decoration-none fw-semibold"><?= e($rider['phone_number']) ?></a>
              </div>
              <div class="rider-summary-item">
                <span class="rider-summary-label"><i class="bi bi-scooter me-1"></i>Vehicle</span>
                <strong title="<?= e($rider['vehicle_details']) ?>"><?= e($rider['vehicle_details'] ?: 'Electric Scooter') ?></strong>
              </div>
              <div class="rider-summary-item">
                <span class="rider-summary-label"><i class="bi bi-wallet2 me-1"></i>Base stipend</span>
                <strong><?= format_inr($rider['base_salary']) ?></strong>
              </div>
              <div class="rider-summary-item">
                <span class="rider-summary-label"><i class="bi bi-cash-stack me-1"></i>COD held</span>
                <strong class="<?= $hasCodBalance ? 'text-danger' : 'text-success' ?>"><?= format_inr($rider['cod_balance']) ?></strong>
              </div>
              <div class="rider-summary-item">
                <span class="rider-summary-label"><i class="bi bi-receipt me-1"></i><?= date('M Y') ?> payout</span>
                <?php if ($isPaidThisMonth): ?>
                  <span class="badge bg-success-subtle text-success rounded-pill"><?= format_inr($curPayout['net_paid']) ?> paid</span>
                <?php else: ?>
                  <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill">Payment due</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="small text-muted mb-3">
              <span class="me-3"><i class="bi bi-award me-1"></i><?= (int)$rider['total_orders_handled'] ?> deliveries · <?= number_format($rider['rating'], 1) ?> rating</span>
              <span><i class="bi bi-card-checklist me-1"></i>License: <?= e($rider['driving_license'] ?: 'Verified on file') ?></span>
            </div>
          </div>

          <div class="rider-action-grid pt-3 border-top">
            <div class="dropdown rider-duty-action">
              <button class="btn btn-outline-secondary btn-sm rounded-3 w-100 dropdown-toggle fw-semibold" type="button" data-bs-toggle="dropdown" aria-label="Update duty status for <?= e($rider['full_name']) ?>">
                <i class="bi bi-person-check me-1"></i>Update duty status
              </button>
              <ul class="dropdown-menu shadow-sm border-0 rounded-3 small">
                <li>
                  <form method="POST" action="<?= ADMIN_URL ?>/delivery-view.php" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_duty">
                    <input type="hidden" name="profile_id" value="<?= (int)$rider['profile_id'] ?>">
                    <input type="hidden" name="duty_status" value="ON_DUTY">
                    <button type="submit" class="dropdown-item py-2 text-primary fw-bold"><i class="bi bi-bicycle me-2"></i>Mark Online (Available)</button>
                  </form>
                </li>
                <li>
                  <form method="POST" action="<?= ADMIN_URL ?>/delivery-view.php" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_duty">
                    <input type="hidden" name="profile_id" value="<?= (int)$rider['profile_id'] ?>">
                    <input type="hidden" name="duty_status" value="ON_BREAK">
                    <button type="submit" class="dropdown-item py-2 text-warning fw-bold"><i class="bi bi-pause-circle-fill me-2"></i>Mark On Break</button>
                  </form>
                </li>
                <li>
                  <form method="POST" action="<?= ADMIN_URL ?>/delivery-view.php" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_duty">
                    <input type="hidden" name="profile_id" value="<?= (int)$rider['profile_id'] ?>">
                    <input type="hidden" name="duty_status" value="ON_LEAVE">
                    <button type="submit" class="dropdown-item py-2 text-danger"><i class="bi bi-x-circle-fill me-2"></i>Mark On Leave</button>
                  </form>
                </li>
                <li>
                  <form method="POST" action="<?= ADMIN_URL ?>/delivery-view.php" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_duty">
                    <input type="hidden" name="profile_id" value="<?= (int)$rider['profile_id'] ?>">
                    <input type="hidden" name="duty_status" value="OFF_DUTY">
                    <button type="submit" class="dropdown-item py-2 text-secondary"><i class="bi bi-moon-fill me-2"></i>Mark Offline</button>
                  </form>
                </li>
              </ul>
            </div>

            <?php if ($hasCodBalance): ?>
              <form method="POST" action="<?= ADMIN_URL ?>/delivery-view.php" class="m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="settle_cod_cash">
                <input type="hidden" name="profile_id" value="<?= (int)$rider['profile_id'] ?>">
                <button type="submit" class="btn btn-warning btn-sm rounded-3 w-100 fw-bold">
                  <i class="bi bi-box-arrow-in-down me-1"></i>Settle COD
                </button>
              </form>
            <?php else: ?>
              <span class="d-flex align-items-center justify-content-center bg-light rounded-3 small text-success fw-semibold" aria-label="No COD cash to settle">
                <i class="bi bi-check2-all me-1"></i>COD settled
              </span>
            <?php endif; ?>

            <button type="button" class="btn btn-sm <?= $isPaidThisMonth ? 'btn-outline-primary' : 'btn-primary' ?> rounded-3 px-3 fw-bold"
                    data-bs-toggle="modal" data-bs-target="#payRiderModal<?= (int)$rider['profile_id'] ?>">
              <i class="bi bi-cash-stack me-1"></i>Pay rider
            </button>

          </div>

        </div>
      </div>

      <!-- Modal: Disburse Rider Payout -->
      <div class="modal fade" id="payRiderModal<?= (int)$rider['profile_id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content rounded-4 border-0 shadow-lg text-start">
            <div class="modal-header border-bottom">
              <div>
                <h5 class="modal-title fw-bold text-dark mb-0">Disburse Payout: <?= e($rider['full_name']) ?></h5>
                <span class="small text-muted"><?= e($rider['designation']) ?> • Base Stipend: <?= format_inr($rider['base_salary']) ?></span>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" action="<?= ADMIN_URL ?>/delivery-view.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="disburse_payout">
              <input type="hidden" name="user_id" value="<?= (int)$rider['user_id'] ?>">

              <div class="modal-body p-4">
                <div class="mb-3">
                  <label class="form-label small fw-bold">Payout Period (Month & Year)</label>
                  <input type="text" name="month_year" class="form-control rounded-3" value="<?= date('F Y') ?>" required>
                </div>

                <div class="row g-3 mb-3">
                  <div class="col-6">
                    <label class="form-label small fw-bold">Base Stipend (INR)</label>
                    <input type="number" step="0.01" name="base_amount" class="form-control rounded-3" value="<?= (float)$rider['base_salary'] ?>" required>
                  </div>
                  <div class="col-6">
                    <label class="form-label small fw-bold">Trip Commission (INR)</label>
                    <input type="number" step="0.01" name="incentives_amount" class="form-control rounded-3" value="4350.00">
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label small fw-bold">Payment Mode</label>
                  <select name="payment_mode" class="form-select rounded-3">
                    <option value="UPI">UPI Direct to Rider VPA</option>
                    <option value="BANK_TRANSFER">Direct Bank Transfer (IMPS)</option>
                    <option value="CASH">Cash Voucher (Store Cashier)</option>
                  </select>
                </div>

                <div class="mb-3">
                  <label class="form-label small fw-bold">Transaction Remarks / Notes</label>
                  <input type="text" name="notes" class="form-control rounded-3" value="Delivery stipend + doorstep trip commissions for <?= date('F Y') ?>">
                </div>

                <div class="p-3 rounded-3 bg-light border small text-muted">
                  <i class="bi bi-info-circle me-1 text-primary"></i>
                  This payment will automatically be logged in <code>staff_salary_payouts</code> and recorded as an outward financial transaction in the Money Ledger.
                </div>
              </div>

              <div class="modal-footer border-top">
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                  Confirm & Disburse Payout
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

    <?php endforeach; ?>
  </div>
  <div class="text-center text-muted py-5" id="riderNoResults" <?= empty($deliveryFleet) ? '' : 'hidden' ?>>
    <i class="bi bi-bicycle fs-2 d-block mb-2"></i>
    <span><?= empty($deliveryFleet) ? 'No delivery rider profiles yet.' : 'No riders match these filters.' ?></span>
  </div>

</div>

<!-- ===========================================================================
     HISTORICAL RIDER PAYOUTS LEDGER
     =========================================================================== -->
<div class="card border-0 rounded-4 shadow-sm p-4 bg-white">
  
  <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
    <div>
      <h5 class="fw-bold font-outfit mb-0 text-dark">Delivery Fleet Payouts &amp; Stipend History</h5>
      <span class="small text-muted">Immutable audit records of all compensation payouts to delivery personnel</span>
    </div>
    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill small">
      <?= count($payoutHistory) ?> Payout Records
    </span>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle small mb-0">
      <thead class="table-light">
        <tr>
          <th>Reference #</th>
          <th>Courier Partner</th>
          <th>Month / Period</th>
          <th>Base Stipend</th>
          <th>Trip Comm.</th>
          <th>Net Paid</th>
          <th>Payment Mode</th>
          <th>Status</th>
          <th>Disbursed Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($payoutHistory)): ?>
          <tr>
            <td colspan="9" class="text-center py-4 text-muted">No rider payouts recorded yet.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($payoutHistory as $pay): ?>
            <tr>
              <td class="font-monospace fw-bold text-dark"><?= e($pay['transaction_reference']) ?></td>
              <td>
                <div class="fw-bold text-dark"><?= e($pay['full_name']) ?></div>
                <div class="small text-muted"><?= e($pay['designation']) ?></div>
              </td>
              <td><span class="badge bg-light text-dark border"><?= e($pay['month_year']) ?></span></td>
              <td><?= format_inr($pay['base_amount']) ?></td>
              <td class="text-success">+<?= format_inr($pay['incentives_amount']) ?></td>
              <td class="fw-bold text-primary fs-6"><?= format_inr($pay['net_paid']) ?></td>
              <td>
                <span class="badge bg-light text-dark border"><?= e($pay['payment_mode']) ?></span>
              </td>
              <td>
                <span class="badge bg-success bg-opacity-25 text-success border border-success px-2 py-1 rounded-pill">
                  <i class="bi bi-check-circle-fill me-1"></i><?= e($pay['payout_status']) ?>
                </span>
              </td>
              <td class="text-muted font-monospace"><?= date('d M Y, h:i A', strtotime($pay['paid_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

<!-- Leaflet Fleet Telemetry Interactive Controller -->
<script>
function filterRiderRoster() {
  const searchTerm = document.getElementById('riderSearch').value.trim().toLocaleLowerCase();
  const selectedDuty = document.getElementById('riderDutyFilter').value;
  const selectedTrip = document.getElementById('riderTripFilter').value;
  const selectedCod = document.getElementById('riderCodFilter').value;
  const selectedPayout = document.getElementById('riderPayoutFilter').value;
  const riderCards = document.querySelectorAll('.rider-roster-item');
  let visibleCount = 0;

  riderCards.forEach((card) => {
    const matchesSearch = card.dataset.search.toLocaleLowerCase().includes(searchTerm);
    const matchesDuty = !selectedDuty || card.dataset.duty === selectedDuty;
    const matchesTrip = !selectedTrip || card.dataset.trip === selectedTrip;
    const matchesCod = !selectedCod || card.dataset.cod === selectedCod;
    const matchesPayout = !selectedPayout || card.dataset.payout === selectedPayout;
    const isVisible = matchesSearch && matchesDuty && matchesTrip && matchesCod && matchesPayout;

    card.hidden = !isVisible;
    visibleCount += isVisible ? 1 : 0;
  });

  document.getElementById('riderResultCount').textContent = `${visibleCount} shown`;
  document.getElementById('riderNoResults').hidden = visibleCount > 0;
}

let fleetMap = null;
let mapLayers = {
  hub: null,
  riders: [],
  drops: [],
  polylines: []
};
let currentMapFilter = 'ALL';
const HUB_COORDS = [12.9716, 77.6412];

function initFleetMap() {
  const mapEl = document.getElementById('fleetMap');
  if (!mapEl || typeof L === 'undefined') return;

  // Initialize Map
  fleetMap = L.map('fleetMap', {
    center: HUB_COORDS,
    zoom: 13,
    zoomControl: true,
    scrollWheelZoom: false
  });

  // Modern OSM tiles
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
  }).addTo(fleetMap);

  // Plot Central Hub Marker
  const hubIcon = L.divIcon({
    className: 'custom-hub-icon',
    html: '<div class="map-hub-pin" title="Zippy Central Kitchen Hub"><i class="bi bi-shop"></i></div>',
    iconSize: [40, 40],
    iconAnchor: [20, 20]
  });

  mapLayers.hub = L.marker(HUB_COORDS, { icon: hubIcon })
    .addTo(fleetMap)
    .bindPopup(`
      <div class="p-2" style="min-width: 200px;">
        <span class="badge bg-success-subtle text-forest border border-success-subtle mb-1">CENTRAL DISPATCH HUB</span>
        <h6 class="fw-bold mb-1 font-outfit text-dark">Zippy Indiranagar Kitchen</h6>
        <p class="small text-muted mb-1 font-monospace">100ft Road, Indiranagar, Bengaluru</p>
        <span class="small text-muted"><i class="bi bi-geo-alt me-1"></i>Coordinates: 12.9716° N, 77.6412° E</span>
      </div>
    `);

  // Fetch telemetry and render initial markers
  fetchFleetTelemetry();

  // Auto-refresh every 10 seconds
  setInterval(fetchFleetTelemetry, 10000);
}

function clearMapLayers() {
  mapLayers.riders.forEach(m => fleetMap.removeLayer(m));
  mapLayers.drops.forEach(m => fleetMap.removeLayer(m));
  mapLayers.polylines.forEach(p => fleetMap.removeLayer(p));
  mapLayers.riders = [];
  mapLayers.drops = [];
  mapLayers.polylines = [];
}

async function fetchFleetTelemetry() {
  try {
    const res = await fetch('<?= ADMIN_URL ?>/ajax/get-fleet-telemetry.php');
    if (!res.ok) return;
    const data = await res.json();
    if (!data.success) return;

    renderFleetOnMap(data);

    const timerEl = document.getElementById('fleetSyncTimer');
    if (timerEl) {
      const now = new Date();
      timerEl.innerHTML = `<i class="bi bi-check2 text-success me-1"></i>Synced: ${now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'})}`;
    }
  } catch (err) {
    console.error('Fleet telemetry sync error:', err);
  }
}

function renderFleetOnMap(data) {
  clearMapLayers();

  data.riders.forEach(rider => {
    const isBusy = rider.has_active_trip && rider.trip;
    const isAvailable = rider.duty_status === 'ON_DUTY' && !isBusy;

    // Apply Filter
    if (currentMapFilter === 'TRANSIT' && !isBusy) return;
    if (currentMapFilter === 'AVAILABLE' && !isAvailable) return;

    const riderPinClass = isBusy ? 'busy' : (isAvailable ? 'available' : 'break');
    const riderIconHtml = `
      <div class="map-rider-pin ${riderPinClass}" title="${escapeHtml(rider.name)} (${rider.duty_status})">
        <i class="bi bi-bicycle"></i>
      </div>
    `;

    const riderIcon = L.divIcon({
      className: 'custom-rider-icon',
      html: riderIconHtml,
      iconSize: [36, 36],
      iconAnchor: [18, 18]
    });

    const riderMarker = L.marker([rider.lat, rider.lng], { icon: riderIcon }).addTo(fleetMap);

    let popupHtml = `
      <div class="p-2" style="min-width: 220px;">
        <div class="d-flex align-items-center justify-content-between mb-1">
          <span class="badge ${isBusy ? 'bg-primary' : (isAvailable ? 'bg-success' : 'bg-warning text-dark')} rounded-pill font-monospace" style="font-size:0.7rem;">
            ${isBusy ? 'IN TRANSIT' : rider.duty_status}
          </span>
          <span class="small text-muted font-monospace"><i class="bi bi-star-fill text-warning me-1"></i>${rider.rating}</span>
        </div>
        <h6 class="fw-bold mb-1 font-outfit text-dark">${escapeHtml(rider.name)}</h6>
        <div class="small text-muted mb-2">
          <div><i class="bi bi-scooter me-1"></i>${escapeHtml(rider.vehicle)}</div>
          <div><i class="bi bi-telephone me-1"></i><a href="tel:${escapeHtml(rider.phone)}" class="text-primary text-decoration-none">${escapeHtml(rider.phone)}</a></div>
        </div>
    `;

    if (isBusy) {
      popupHtml += `
        <div class="p-2 rounded bg-light border mb-2 small">
          <div class="d-flex justify-content-between font-monospace fw-bold text-dark">
            <span>${escapeHtml(rider.trip.order_number)}</span>
            <span class="text-primary">${rider.trip.eta_mins}m ETA</span>
          </div>
          <div class="text-muted text-truncate" style="font-size:0.75rem;"><i class="bi bi-geo-alt me-1"></i>${escapeHtml(rider.trip.address)}</div>
          <div class="mt-1 d-flex justify-content-between">
            <span class="badge bg-warning-subtle text-dark border border-warning-subtle">OTP: ${escapeHtml(rider.trip.otp)}</span>
            <span class="small text-muted">${rider.trip.distance_km} km away</span>
          </div>
        </div>
      `;

      // Plot Destination Marker
      const dropIcon = L.divIcon({
        className: 'custom-drop-icon',
        html: `<div class="map-drop-pin" title="Order Drop Destination: ${escapeHtml(rider.trip.order_number)}"><i class="bi bi-geo-alt-fill"></i></div>`,
        iconSize: [32, 32],
        iconAnchor: [16, 30]
      });

      const dropMarker = L.marker([rider.trip.dest_lat, rider.trip.dest_lng], { icon: dropIcon }).addTo(fleetMap);
      dropMarker.bindPopup(`
        <div class="p-2" style="min-width: 200px;">
          <span class="badge bg-danger-subtle text-danger border border-danger-subtle mb-1">DELIVERY DESTINATION</span>
          <h6 class="fw-bold mb-1 font-outfit text-dark">${escapeHtml(rider.trip.order_number)}</h6>
          <p class="small text-muted mb-1 font-monospace">${escapeHtml(rider.trip.customer_name)} &bull; ${escapeHtml(rider.trip.customer_phone)}</p>
          <div class="small text-muted mb-2">${escapeHtml(rider.trip.address)}</div>
          <span class="badge bg-dark text-white font-monospace">Doorstep OTP: ${escapeHtml(rider.trip.otp)}</span>
        </div>
      `);
      mapLayers.drops.push(dropMarker);

      // Draw dashed polyline connecting rider to drop
      const routePolyline = L.polyline([[rider.lat, rider.lng], [rider.trip.dest_lat, rider.trip.dest_lng]], {
        color: '#2563EB',
        weight: 3.5,
        opacity: 0.8,
        dashArray: '6, 8',
        lineCap: 'round'
      }).addTo(fleetMap);
      mapLayers.polylines.push(routePolyline);

    } else {
      popupHtml += `
        <div class="p-2 rounded bg-success-subtle text-forest border border-success-subtle small fw-semibold">
          <i class="bi bi-check-circle me-1"></i> Standby at Central Kitchen Hub
        </div>
      `;
    }

    popupHtml += `</div>`;
    riderMarker.bindPopup(popupHtml);
    mapLayers.riders.push(riderMarker);
  });
}

function recenterFleetMap() {
  if (fleetMap) {
    fleetMap.setView(HUB_COORDS, 13, { animate: true });
  }
}

function filterMapMarkers(filter) {
  currentMapFilter = filter;
  document.getElementById('mapFilterAllBtn')?.classList.toggle('active', filter === 'ALL');
  document.getElementById('mapFilterTransitBtn')?.classList.toggle('active', filter === 'TRANSIT');
  document.getElementById('mapFilterAvailableBtn')?.classList.toggle('active', filter === 'AVAILABLE');
  fetchFleetTelemetry();
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);
}

document.addEventListener('DOMContentLoaded', () => {
  const filterIds = ['riderSearch', 'riderDutyFilter', 'riderTripFilter', 'riderCodFilter', 'riderPayoutFilter'];
  filterIds.forEach((id) => {
    const filter = document.getElementById(id);
    filter.addEventListener(filter.tagName === 'INPUT' ? 'input' : 'change', filterRiderRoster);
  });
  filterRiderRoster();

  const mapPanel = document.getElementById('fleetMapPanel');
  const mapToggleLabel = document.querySelector('.fleet-map-toggle-label');

  mapPanel.addEventListener('shown.bs.collapse', () => {
    mapToggleLabel.textContent = 'Hide map';
    const needsInitialization = !fleetMap;
    if (needsInitialization) initFleetMap();
    window.requestAnimationFrame(() => {
      if (!fleetMap) return;
      fleetMap.invalidateSize();
      if (!needsInitialization) fetchFleetTelemetry();
    });
  });
  mapPanel.addEventListener('hidden.bs.collapse', () => {
    mapToggleLabel.textContent = 'Show map';
  });

  if (mapPanel.classList.contains('show')) initFleetMap();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
