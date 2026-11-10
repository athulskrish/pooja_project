<?php
declare(strict_types=1);
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
requireAdmin();
$currentUser = getCurrentUser();

// Handle Staff Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid session token.');
    } else {
        $res = FinanceService::handleAdminKitchenAction($_POST);
        setFlash($res['type'], $res['message']);
        header("Location: " . ADMIN_URL . "/kitchen-view.php");
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';

$kitchenData = FinanceService::getAdminKitchenViewData();
$kitchenStaff = $kitchenData['kitchenStaff'];
$latestPayoutsByUser = $kitchenData['latestPayoutsByUser'];
$payoutHistory = $kitchenData['payoutHistory'];
$totalStaff = $kitchenData['totalStaff'];
$presentStaff = $kitchenData['presentStaff'];
$totalMonthlyPayroll = $kitchenData['totalMonthlyPayroll'];
$totalDishesCooked = $kitchenData['totalDishesCooked'];
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
      <h3 class="fw-bold font-outfit text-dark mb-1"><?= formatInr($totalMonthlyPayroll) ?></h3>
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

<style>
  .kitchen-roster-card {
    background: #fff;
    transition: box-shadow 160ms ease, transform 160ms ease;
  }

  .kitchen-roster-card:hover {
    box-shadow: 0 0.5rem 1.25rem rgba(20, 40, 25, 0.08) !important;
    transform: translateY(-2px);
  }

  .kitchen-staff-summary {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
  }

  .kitchen-summary-item {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 0.2rem;
    min-width: 0;
    min-height: 64px;
    padding: 0.65rem 0.75rem;
    border-radius: 0.75rem;
    background: #f7f9f7;
    font-size: 0.82rem;
    overflow-wrap: anywhere;
  }

  .kitchen-summary-label {
    color: #6c757d;
    font-size: 0.72rem;
  }

  @media (prefers-reduced-motion: reduce) {
    .kitchen-roster-card {
      transition: none;
    }

    .kitchen-roster-card:hover {
      transform: none;
    }
  }
</style>

<!-- ===========================================================================
     KITCHEN STAFF ROSTER & ATTENDANCE MANAGEMENT
     =========================================================================== -->
<div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
  
  <div class="d-flex flex-wrap align-items-center justify-content-between border-bottom pb-3 mb-3 gap-3">
    <div>
      <h4 class="fw-bold font-outfit mb-0 text-dark">Kitchen Staff Roster</h4>
      <span class="small text-muted">Attendance, shift details and monthly payroll at a glance.</span>
    </div>

    <span class="small text-muted"><i class="bi bi-clock me-1"></i>General kitchen shift</span>
  </div>

  <div class="row g-2 align-items-end mb-4" aria-label="Filter kitchen staff">
    <div class="col-lg-5">
      <label for="kitchenStaffSearch" class="form-label small fw-semibold text-muted mb-1">Search staff</label>
      <div class="input-group">
        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
        <input type="search" class="form-control bg-light border-start-0" id="kitchenStaffSearch"
               placeholder="Name or designation" autocomplete="off">
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <label for="kitchenDutyFilter" class="form-label small fw-semibold text-muted mb-1">Duty status</label>
      <select class="form-select bg-light" id="kitchenDutyFilter">
        <option value="">All duty statuses</option>
        <option value="ON_DUTY">Present & on duty</option>
        <option value="ON_BREAK">On break</option>
        <option value="OFF_DUTY">Off duty</option>
        <option value="ON_LEAVE">On leave</option>
      </select>
    </div>
    <div class="col-sm-6 col-lg-3">
      <label for="kitchenPaymentFilter" class="form-label small fw-semibold text-muted mb-1">Monthly payment</label>
      <select class="form-select bg-light" id="kitchenPaymentFilter">
        <option value="">All payment statuses</option>
        <option value="paid">Paid</option>
        <option value="due">Payment due</option>
      </select>
    </div>
    <div class="col-lg-1 text-lg-end">
      <span class="small text-muted d-block py-2" id="kitchenStaffCount" aria-live="polite"><?= $totalStaff ?> shown</span>
    </div>
  </div>

  <!-- Staff Cards Grid -->
  <div class="row g-3" id="kitchenStaffGrid">
    <?php foreach ($kitchenStaff as $staff): ?>
      <?php 
        $uid = (int)$staff['user_id'];
        $curPayout = $latestPayoutsByUser[$uid] ?? null;
        $isPaidThisMonth = !empty($curPayout) && $curPayout['payout_status'] === 'PAID';
        
        $dutyBadge = match ($staff['duty_status']) {
          'ON_DUTY' => ['class' => 'bg-success-subtle text-success', 'icon' => 'bi-check-circle-fill', 'label' => 'On duty'],
          'ON_BREAK' => ['class' => 'bg-warning-subtle text-warning-emphasis', 'icon' => 'bi-pause-circle-fill', 'label' => 'On break'],
          'ON_LEAVE' => ['class' => 'bg-danger-subtle text-danger', 'icon' => 'bi-x-circle-fill', 'label' => 'On leave'],
          default => ['class' => 'bg-secondary-subtle text-secondary', 'icon' => 'bi-moon-fill', 'label' => 'Off duty']
        };
      ?>
      <div class="col-md-6 col-xl-4 kitchen-staff-item"
           data-search="<?= e($staff['full_name'] . ' ' . $staff['designation']) ?>"
           data-duty="<?= e($staff['duty_status']) ?>"
           data-payment="<?= $isPaidThisMonth ? 'paid' : 'due' ?>">
        <div class="card border-0 rounded-4 p-3 p-lg-4 h-100 shadow-sm d-flex flex-column justify-content-between kitchen-roster-card">
          
          <div>
            <!-- Staff Profile Header -->
            <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
              <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white fs-5 flex-shrink-0" style="width: 48px; height: 48px; background: #2E6F40;">
                  <?= strtoupper(substr((string)($staff['full_name'] ?? 'S'), 0, 1)) ?>
                </div>
                <div class="min-w-0">
                  <h6 class="fw-bold font-outfit text-dark mb-0"><?= e($staff['full_name']) ?></h6>
                  <span class="small text-muted"><?= e($staff['designation']) ?> · #KIT-<?= str_pad((string)$staff['profile_id'], 3, '0', STR_PAD_LEFT) ?></span>
                </div>
              </div>

              <span class="badge <?= $dutyBadge['class'] ?> rounded-pill px-2 py-2 small flex-shrink-0">
                <i class="bi <?= $dutyBadge['icon'] ?> me-1"></i><?= $dutyBadge['label'] ?>
              </span>
            </div>

            <div class="kitchen-staff-summary mb-3">
              <div class="kitchen-summary-item">
                <span class="kitchen-summary-label"><i class="bi bi-clock me-1"></i>Shift</span>
                <strong class="font-monospace"><?= e($staff['shift_timings']) ?></strong>
              </div>
              <div class="kitchen-summary-item">
                <span class="kitchen-summary-label"><i class="bi bi-telephone me-1"></i>Contact</span>
                <a href="tel:<?= e($staff['phone_number']) ?>" class="text-forest text-decoration-none fw-semibold"><?= e($staff['phone_number']) ?></a>
              </div>
              <div class="kitchen-summary-item">
                <span class="kitchen-summary-label"><i class="bi bi-wallet2 me-1"></i>Monthly pay</span>
                <strong><?= formatInr($staff['base_salary']) ?></strong>
              </div>
              <div class="kitchen-summary-item">
                <span class="kitchen-summary-label"><i class="bi bi-receipt me-1"></i><?= date('M Y') ?> payout</span>
                <?php if ($isPaidThisMonth): ?>
                  <span class="badge bg-success-subtle text-success rounded-pill"><?= formatInr($curPayout['net_paid']) ?> paid</span>
                <?php else: ?>
                  <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill">Payment due</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="small text-muted mb-3">
              <span class="me-3"><i class="bi bi-award me-1"></i><?= (int)$staff['total_orders_handled'] ?> orders · <?= number_format((float)($staff['rating'] ?? 4.8), 1) ?> rating</span>
              <span><i class="bi bi-stars me-1"></i><?= e($staff['specialization'] ?: 'General preparation') ?></span>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="d-flex flex-wrap gap-2 pt-3 border-top">
            
            <!-- Attendance Dropdown -->
            <div class="dropdown flex-grow-1">
              <button class="btn btn-outline-secondary btn-sm rounded-3 w-100 dropdown-toggle fw-semibold" type="button" data-bs-toggle="dropdown" aria-label="Update duty status for <?= e($staff['full_name']) ?>">
                <i class="bi bi-person-check me-1"></i>Update status
              </button>
              <ul class="dropdown-menu shadow-sm border-0 rounded-3 small">
                <li>
                  <form method="POST" action="<?= ADMIN_URL ?>/kitchen-view.php" class="m-0">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_duty">
                    <input type="hidden" name="profile_id" value="<?= (int)$staff['profile_id'] ?>">
                    <input type="hidden" name="duty_status" value="ON_DUTY">
                    <button type="submit" class="dropdown-item py-2 text-success fw-bold"><i class="bi bi-check-circle-fill me-2"></i>Mark Present (On Duty)</button>
                  </form>
                </li>
                <li>
                  <form method="POST" action="<?= ADMIN_URL ?>/kitchen-view.php" class="m-0">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_duty">
                    <input type="hidden" name="profile_id" value="<?= (int)$staff['profile_id'] ?>">
                    <input type="hidden" name="duty_status" value="ON_BREAK">
                    <button type="submit" class="dropdown-item py-2 text-warning fw-bold"><i class="bi bi-pause-circle-fill me-2"></i>Mark On Break</button>
                  </form>
                </li>
                <li>
                  <form method="POST" action="<?= ADMIN_URL ?>/kitchen-view.php" class="m-0">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_duty">
                    <input type="hidden" name="profile_id" value="<?= (int)$staff['profile_id'] ?>">
                    <input type="hidden" name="duty_status" value="OFF_DUTY">
                    <button type="submit" class="dropdown-item py-2 text-secondary"><i class="bi bi-moon-fill me-2"></i>Mark Off Duty</button>
                  </form>
                </li>
                <li>
                  <form method="POST" action="<?= ADMIN_URL ?>/kitchen-view.php" class="m-0">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_duty">
                    <input type="hidden" name="profile_id" value="<?= (int)$staff['profile_id'] ?>">
                    <input type="hidden" name="duty_status" value="ON_LEAVE">
                    <button type="submit" class="dropdown-item py-2 text-danger"><i class="bi bi-x-circle-fill me-2"></i>Mark On Leave</button>
                  </form>
                </li>
              </ul>
            </div>

            <!-- Disburse Salary Button -->
            <button type="button" class="btn btn-sm <?= $isPaidThisMonth ? 'btn-outline-success' : 'btn-success' ?> rounded-3 px-3 fw-bold"
                    data-bs-toggle="modal" data-bs-target="#paySalaryModal<?= (int)$staff['profile_id'] ?>" style="<?= !$isPaidThisMonth ? 'background:#2E6F40; border-color:#2E6F40;' : '' ?>">
              <i class="bi bi-cash-stack me-1"></i>Pay salary
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
                <span class="small text-muted"><?= e($staff['designation']) ?> • Base: <?= formatInr($staff['base_salary']) ?></span>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" action="<?= ADMIN_URL ?>/kitchen-view.php">
              <?= csrfField() ?>
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
  <div class="text-center text-muted py-5" id="kitchenStaffNoResults" <?= empty($kitchenStaff) ? '' : 'hidden' ?>>
    <i class="bi bi-people fs-2 d-block mb-2"></i>
    <span><?= empty($kitchenStaff) ? 'No kitchen staff profiles yet.' : 'No staff match these filters.' ?></span>
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
              <td><?= formatInr($pay['base_amount']) ?></td>
              <td class="text-success">+<?= formatInr($pay['incentives_amount']) ?></td>
              <td class="fw-bold text-forest fs-6"><?= formatInr($pay['net_paid']) ?></td>
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

<script>
  (() => {
    const searchInput = document.getElementById('kitchenStaffSearch');
    const dutyFilter = document.getElementById('kitchenDutyFilter');
    const paymentFilter = document.getElementById('kitchenPaymentFilter');
    const staffCards = Array.from(document.querySelectorAll('.kitchen-staff-item'));
    const resultCount = document.getElementById('kitchenStaffCount');
    const noResults = document.getElementById('kitchenStaffNoResults');

    const filterStaff = () => {
      const searchTerm = searchInput.value.trim().toLocaleLowerCase();
      const selectedDuty = dutyFilter.value;
      const selectedPayment = paymentFilter.value;
      let visibleCount = 0;

      staffCards.forEach((card) => {
        const matchesSearch = card.dataset.search.toLocaleLowerCase().includes(searchTerm);
        const matchesDuty = !selectedDuty || card.dataset.duty === selectedDuty;
        const matchesPayment = !selectedPayment || card.dataset.payment === selectedPayment;
        const isVisible = matchesSearch && matchesDuty && matchesPayment;

        card.hidden = !isVisible;
        visibleCount += isVisible ? 1 : 0;
      });

      resultCount.textContent = `${visibleCount} shown`;
      noResults.hidden = visibleCount > 0;
    };

    searchInput.addEventListener('input', filterStaff);
    dutyFilter.addEventListener('change', filterStaff);
    paymentFilter.addEventListener('change', filterStaff);
  })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
