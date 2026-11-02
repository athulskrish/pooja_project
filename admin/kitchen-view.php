<?php
/**
 * Admin Kitchen View: Staff Attendance, Station Roster & Salary Management
 * Zippy - Online Food Ordering Platform
 * MCSP-232: MCA Major Project | Student: Pooja JP
 */

$pageTitle = 'Kitchen Operations & Staff View';
$activePage = 'kitchen_view';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$currentUser = current_user();

// Handle Staff Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
    } else {
        $action = $_POST['action'];

        // 1. Toggle Attendance / Duty Status
        if ($action === 'toggle_duty') {
            $profileId = (int)$_POST['profile_id'];
            $newStatus = clean_input($_POST['duty_status']);
            db_query("UPDATE `staff_profiles` SET `duty_status` = :st WHERE `profile_id` = :id", [
                ':st' => $newStatus,
                ':id' => $profileId
            ]);
            set_flash('success', "Kitchen staff presence status updated to {$newStatus}.");
            header("Location: " . ADMIN_URL . "/kitchen-view.php");
            exit;
        }

        // 2. Disburse Salary Payout
        if ($action === 'disburse_salary') {
            $userId = (int)$_POST['user_id'];
            $monthYear = clean_input($_POST['month_year'] ?? date('F Y'));
            $baseAmount = (float)$_POST['base_amount'];
            $incentives = (float)($_POST['incentives_amount'] ?? 0);
            $deductions = (float)($_POST['deductions_amount'] ?? 0);
            $netPaid = $baseAmount + $incentives - $deductions;
            $paymentMode = clean_input($_POST['payment_mode'] ?? 'BANK_TRANSFER');
            $notes = clean_input($_POST['notes'] ?? 'Monthly kitchen culinary salary disbursement');
            $ref = 'SAL-KIT-' . date('Ym') . '-' . str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

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
            $staffName = $staffUser['full_name'] ?? 'Kitchen Staff';
            
            record_money_transaction(
                null,
                $userId,
                'STAFF_SALARY_PAYOUT',
                'OUTWARD',
                $paymentMode === 'UPI' ? 'UPI' : 'BANK_TRANSFER',
                $netPaid,
                'COMPLETED',
                $ref,
                'Store Finance & HR',
                "Salary payout disbursed to {$staffName} for {$monthYear}"
            );

            set_flash('success', "Salary of " . format_inr($netPaid) . " successfully disbursed to {$staffName} (Ref: {$ref}).");
            header("Location: " . ADMIN_URL . "/kitchen-view.php");
            exit;
        }

        // 3. Update Staff Details
        if ($action === 'update_details') {
            $profileId = (int)$_POST['profile_id'];
            $desig = clean_input($_POST['designation']);
            $spec = clean_input($_POST['specialization']);
            $shift = clean_input($_POST['shift_timings']);
            $salary = (float)$_POST['base_salary'];

            db_query("
                UPDATE `staff_profiles` 
                SET `designation` = :desig, `specialization` = :spec, `shift_timings` = :shift, `base_salary` = :sal 
                WHERE `profile_id` = :id
            ", [
                ':desig' => $desig,
                ':spec'  => $spec,
                ':shift' => $shift,
                ':sal'   => $salary,
                ':id'    => $profileId
            ]);
            set_flash('success', "Staff details updated successfully.");
            header("Location: " . ADMIN_URL . "/kitchen-view.php");
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';

// Fetch all Kitchen Staff Profiles
$kitchenStaff = db_fetch_all("
    SELECT sp.*, u.full_name, u.email, u.phone_number, u.created_at AS user_since
    FROM `staff_profiles` sp
    JOIN `users` u ON sp.user_id = u.user_id
    WHERE sp.department = 'KITCHEN'
    ORDER BY sp.base_salary DESC, sp.joined_date ASC
");

// Fetch Latest Salary Payouts for Kitchen Staff
$staffIds = array_column($kitchenStaff, 'user_id');
$latestPayoutsByUser = [];
$payoutHistory = [];

if (!empty($staffIds)) {
    $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
    
    // All history
    $payoutHistory = db_fetch_all("
        SELECT p.*, u.full_name, u.phone_number, sp.designation
        FROM `staff_salary_payouts` p
        JOIN `users` u ON p.user_id = u.user_id
        JOIN `staff_profiles` sp ON p.user_id = sp.user_id
        WHERE p.user_id IN ($placeholders)
        ORDER BY p.paid_at DESC
        LIMIT 25
    ", $staffIds);

    // Current month payouts map
    $currentMonthStr = date('F Y');
    $currentMonthPayouts = db_fetch_all("
        SELECT * FROM `staff_salary_payouts` 
        WHERE `user_id` IN ($placeholders) AND `month_year` = ?
    ", array_merge($staffIds, [$currentMonthStr]));

    foreach ($currentMonthPayouts as $cp) {
        $latestPayoutsByUser[$cp['user_id']] = $cp;
    }
}

// KPI Aggregations
$totalStaff = count($kitchenStaff);
$presentStaff = count(array_filter($kitchenStaff, fn($s) => $s['duty_status'] === 'ON_DUTY'));
$totalMonthlyPayroll = array_sum(array_column($kitchenStaff, 'base_salary'));
$totalDishesCooked = (int)db_fetch_val("SELECT COALESCE(SUM(quantity), 0) FROM `order_items` oi JOIN `orders` o ON oi.order_id = o.order_id WHERE o.order_status IN ('READY', 'OUT_FOR_DELIVERY', 'DELIVERED')");
?>

<!-- ===========================================================================
     KPI METRICS ROW
     =========================================================================== -->
<div class="row g-3 mb-4">
  
  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-bold text-uppercase" style="letter-spacing:0.5px;">KITCHEN ROSTER</span>
        <div class="rounded-3 p-2 text-warning" style="background:#FFFBEB;">
          <i class="fa-solid fa-users-gear fs-4"></i>
        </div>
      </div>
      <h3 class="fw-bold font-outfit text-dark mb-1"><?= $totalStaff ?> Chefs & Cooks</h3>
      <span class="small text-muted">Dedicated Culinary Team</span>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100" style="border-top: 3px solid #10B981 !important;">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-bold text-uppercase" style="letter-spacing:0.5px;">WHO IS PRESENT</span>
        <div class="rounded-3 p-2 text-success" style="background:#EDFBF2;">
          <span class="live-pulse-dot green"></span>
        </div>
      </div>
      <h3 class="fw-bold font-outfit text-dark mb-1"><?= $presentStaff ?> / <?= $totalStaff ?> Present</h3>
      <span class="small text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i>Active on Stoves & Line</span>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-bold text-uppercase" style="letter-spacing:0.5px;">MONTHLY PAYROLL</span>
        <div class="rounded-3 p-2 text-primary" style="background:#EFF6FF;">
          <i class="bi bi-wallet2 fs-4"></i>
        </div>
      </div>
      <h3 class="fw-bold font-outfit text-dark mb-1"><?= format_inr($totalMonthlyPayroll) ?></h3>
      <span class="small text-muted">Base Monthly Kitchen Payroll</span>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white h-100">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-bold text-uppercase" style="letter-spacing:0.5px;">DISHES PREPARED</span>
        <div class="rounded-3 p-2 text-forest" style="background:#EDFBF2;">
          <i class="fa-solid fa-bowl-food fs-4"></i>
        </div>
      </div>
      <h3 class="fw-bold font-outfit text-dark mb-1"><?= number_format($totalDishesCooked) ?></h3>
      <span class="small text-success fw-bold"><i class="bi bi-graph-up me-1"></i>Fulfilled by Culinary Staff</span>
    </div>
  </div>

</div>

<!-- ===========================================================================
     KITCHEN STAFF ROSTER & ATTENDANCE MANAGEMENT
     =========================================================================== -->
<div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
  
  <div class="d-flex flex-wrap align-items-center justify-content-between border-bottom pb-3 mb-4 gap-2">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="badge rounded-pill px-3 py-1" style="background:#FFFBEB; color:#B45309; border: 1px solid #FDE68A;">
          <i class="fa-solid fa-kitchen-set me-1"></i> CULINARY ATTENDANCE & SHIFTS
        </span>
        <span class="small text-muted fw-bold">Station Presence, Contact Details & Salary</span>
      </div>
      <h4 class="fw-bold font-outfit mb-0 text-dark">Kitchen Staff Roster</h4>
    </div>

    <div class="d-flex align-items-center gap-2">
      <span class="small text-muted">Current Shift Cycle: <strong>General Kitchen Shift</strong></span>
    </div>
  </div>

  <!-- Staff Cards Grid -->
  <div class="row g-4">
    <?php foreach ($kitchenStaff as $staff): ?>
      <?php 
        $uid = (int)$staff['user_id'];
        $curPayout = $latestPayoutsByUser[$uid] ?? null;
        $isPaidThisMonth = !empty($curPayout) && $curPayout['payout_status'] === 'PAID';
        
        $dutyBadge = match($staff['duty_status']) {
          'ON_DUTY' => ['class' => 'bg-success text-white', 'icon' => 'bi-check-circle-fill', 'label' => 'Present & On Duty'],
          'ON_BREAK' => ['class' => 'bg-warning text-dark', 'icon' => 'bi-pause-circle-fill', 'label' => 'On Break'],
          'ON_LEAVE' => ['class' => 'bg-danger text-white', 'icon' => 'bi-x-circle-fill', 'label' => 'On Leave'],
          default => ['class' => 'bg-secondary text-white', 'icon' => 'bi-moon-fill', 'label' => 'Off Duty']
        };
      ?>
      <div class="col-md-6 col-xl-4">
        <div class="card border rounded-4 p-4 h-100 shadow-sm d-flex flex-column justify-content-between position-relative" style="border-color: <?= ($staff['duty_status'] === 'ON_DUTY') ? '#A7F3D0' : '#E5E7EB' ?> !important;">
          
          <div>
            <!-- Staff Profile Header -->
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white fs-5" style="width: 52px; height: 52px; background: #2E6F40;">
                  <?= strtoupper(substr($staff['full_name'], 0, 1)) ?>
                </div>
                <div>
                  <h6 class="fw-bold font-outfit text-dark mb-0"><?= e($staff['full_name']) ?></h6>
                  <span class="small text-forest fw-semibold"><?= e($staff['designation']) ?></span>
                  <div class="small text-muted" style="font-size:0.75rem;">Staff ID: #KIT-<?= str_pad((string)$staff['profile_id'], 3, '0', STR_PAD_LEFT) ?></div>
                </div>
              </div>

              <span class="badge <?= $dutyBadge['class'] ?> rounded-pill px-2 py-1 small">
                <i class="bi <?= $dutyBadge['icon'] ?> me-1"></i><?= $dutyBadge['label'] ?>
              </span>
            </div>

            <!-- Details Box -->
            <div class="p-3 rounded-3 bg-light border mb-3 small">
              <div class="d-flex justify-content-between py-1 border-bottom">
                <span class="text-muted"><i class="bi bi-clock me-1"></i>Shift Hours:</span>
                <strong class="text-dark font-monospace"><?= e($staff['shift_timings']) ?></strong>
              </div>
              <div class="d-flex justify-content-between py-1 border-bottom">
                <span class="text-muted"><i class="bi bi-telephone me-1"></i>Contact:</span>
                <a href="tel:<?= e($staff['phone_number']) ?>" class="text-forest text-decoration-none fw-bold"><?= e($staff['phone_number']) ?></a>
              </div>
              <div class="d-flex justify-content-between py-1 border-bottom">
                <span class="text-muted"><i class="bi bi-award me-1"></i>Performance:</span>
                <span class="fw-bold text-dark"><?= (int)$staff['total_orders_handled'] ?> orders • ⭐ <?= number_format($staff['rating'], 1) ?></span>
              </div>
              <div class="pt-2 text-muted" style="font-size:0.75rem;">
                <strong>Specialization:</strong> <?= e($staff['specialization'] ?: 'General Gourmet Preparation') ?>
              </div>
            </div>

            <!-- Salary & Compensation Box -->
            <div class="p-3 rounded-3 mb-3" style="background:#F9FAFB; border: 1px dashed #D1D5DB;">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small text-muted fw-bold text-uppercase" style="font-size:0.7rem;">MONTHLY SALARY:</span>
                <span class="fw-bold font-outfit fs-6 text-dark"><?= format_inr($staff['base_salary']) ?></span>
              </div>
              
              <div class="d-flex justify-content-between align-items-center">
                <span class="small text-muted" style="font-size:0.75rem;">Status (<?= date('M Y') ?>):</span>
                <?php if ($isPaidThisMonth): ?>
                  <span class="badge bg-success bg-opacity-25 text-success border border-success rounded-pill px-2" style="font-size:0.7rem;">
                    <i class="bi bi-check-circle-fill me-1"></i>PAID (<?= format_inr($curPayout['net_paid']) ?>)
                  </span>
                <?php else: ?>
                  <span class="badge bg-warning bg-opacity-25 text-dark border border-warning rounded-pill px-2" style="font-size:0.7rem;">
                    <i class="bi bi-clock-history me-1"></i>PAYMENT DUE
                  </span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="d-flex gap-2 pt-2 border-top">
            
            <!-- Attendance Dropdown -->
            <div class="dropdown flex-grow-1">
              <button class="btn btn-outline-secondary btn-sm rounded-pill w-100 dropdown-toggle fw-semibold" type="button" data-bs-toggle="dropdown">
                Change Duty Status
              </button>
              <ul class="dropdown-menu shadow-sm border-0 rounded-3 small">
                <li>
                  <form method="POST" action="<?= ADMIN_URL ?>/kitchen-view.php" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_duty">
                    <input type="hidden" name="profile_id" value="<?= (int)$staff['profile_id'] ?>">
                    <input type="hidden" name="duty_status" value="ON_DUTY">
                    <button type="submit" class="dropdown-item py-2 text-success fw-bold"><i class="bi bi-check-circle-fill me-2"></i>Mark Present (On Duty)</button>
                  </form>
                </li>
                <li>
                  <form method="POST" action="<?= ADMIN_URL ?>/kitchen-view.php" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_duty">
                    <input type="hidden" name="profile_id" value="<?= (int)$staff['profile_id'] ?>">
                    <input type="hidden" name="duty_status" value="ON_BREAK">
                    <button type="submit" class="dropdown-item py-2 text-warning fw-bold"><i class="bi bi-pause-circle-fill me-2"></i>Mark On Break</button>
                  </form>
                </li>
                <li>
                  <form method="POST" action="<?= ADMIN_URL ?>/kitchen-view.php" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_duty">
                    <input type="hidden" name="profile_id" value="<?= (int)$staff['profile_id'] ?>">
                    <input type="hidden" name="duty_status" value="OFF_DUTY">
                    <button type="submit" class="dropdown-item py-2 text-secondary"><i class="bi bi-moon-fill me-2"></i>Mark Off Duty</button>
                  </form>
                </li>
                <li>
                  <form method="POST" action="<?= ADMIN_URL ?>/kitchen-view.php" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_duty">
                    <input type="hidden" name="profile_id" value="<?= (int)$staff['profile_id'] ?>">
                    <input type="hidden" name="duty_status" value="ON_LEAVE">
                    <button type="submit" class="dropdown-item py-2 text-danger"><i class="bi bi-x-circle-fill me-2"></i>Mark On Leave</button>
                  </form>
                </li>
              </ul>
            </div>

            <!-- Disburse Salary Button -->
            <button type="button" class="btn btn-sm <?= $isPaidThisMonth ? 'btn-outline-success' : 'btn-success' ?> rounded-pill px-3 fw-bold" 
                    data-bs-toggle="modal" data-bs-target="#paySalaryModal<?= (int)$staff['profile_id'] ?>" style="<?= !$isPaidThisMonth ? 'background:#2E6F40; border-color:#2E6F40;' : '' ?>">
              <i class="bi bi-cash-stack me-1"></i>Pay Salary
            </button>

          </div>

        </div>
      </div>

      <!-- Modal: Disburse Salary -->
      <div class="modal fade" id="paySalaryModal<?= (int)$staff['profile_id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content rounded-4 border-0 shadow-lg text-start">
            <div class="modal-header border-bottom">
              <div>
                <h5 class="modal-title fw-bold text-dark mb-0">Disburse Salary: <?= e($staff['full_name']) ?></h5>
                <span class="small text-muted"><?= e($staff['designation']) ?> • Base: <?= format_inr($staff['base_salary']) ?></span>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" action="<?= ADMIN_URL ?>/kitchen-view.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="disburse_salary">
              <input type="hidden" name="user_id" value="<?= (int)$staff['user_id'] ?>">

              <div class="modal-body p-4">
                <div class="mb-3">
                  <label class="form-label small fw-bold">Salary Period (Month & Year)</label>
                  <input type="text" name="month_year" class="form-control rounded-3" value="<?= date('F Y') ?>" required>
                </div>

                <div class="row g-3 mb-3">
                  <div class="col-6">
                    <label class="form-label small fw-bold">Base Salary (INR)</label>
                    <input type="number" step="0.01" name="base_amount" class="form-control rounded-3" value="<?= (float)$staff['base_salary'] ?>" required>
                  </div>
                  <div class="col-6">
                    <label class="form-label small fw-bold">Performance Bonus (INR)</label>
                    <input type="number" step="0.01" name="incentives_amount" class="form-control rounded-3" value="2500.00">
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label small fw-bold">Payment Mode</label>
                  <select name="payment_mode" class="form-select rounded-3">
                    <option value="BANK_TRANSFER">Direct Bank Transfer (NEFT / IMPS)</option>
                    <option value="UPI">UPI Instant Transfer</option>
                    <option value="CASH">Cash Voucher (Store Register)</option>
                  </select>
                </div>

                <div class="mb-3">
                  <label class="form-label small fw-bold">Transaction Remarks / Notes</label>
                  <input type="text" name="notes" class="form-control rounded-3" value="Monthly culinary salary for <?= date('F Y') ?>">
                </div>

                <div class="p-3 rounded-3 bg-light border small text-muted">
                  <i class="bi bi-info-circle me-1 text-primary"></i>
                  This payment will automatically be logged in <code>staff_salary_payouts</code> and recorded as an outward financial transaction in the Money Ledger.
                </div>
              </div>

              <div class="modal-footer border-top">
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
                  Confirm & Disburse Salary
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

    <?php endforeach; ?>
  </div>

</div>

<!-- ===========================================================================
     HISTORICAL KITCHEN SALARY DISBURSEMENTS LEDGER
     =========================================================================== -->
<div class="card border-0 rounded-4 shadow-sm p-4 bg-white">
  
  <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
    <div>
      <h5 class="fw-bold font-outfit mb-0 text-dark">Kitchen Salary & Payroll Disbursements History</h5>
      <span class="small text-muted">Immutable audit records of all compensation payouts to culinary personnel</span>
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
          <th>Staff Member</th>
          <th>Month / Period</th>
          <th>Base Pay</th>
          <th>Incentive</th>
          <th>Net Paid</th>
          <th>Payment Mode</th>
          <th>Status</th>
          <th>Disbursed Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($payoutHistory)): ?>
          <tr>
            <td colspan="9" class="text-center py-4 text-muted">No salary payouts recorded yet.</td>
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
              <td class="fw-bold text-forest fs-6"><?= format_inr($pay['net_paid']) ?></td>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
