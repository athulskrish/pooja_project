<?php
declare(strict_types=1);
/**
 * Executive Money & COD Financial Transactions Ledger
 * Zippy - Online Food Ordering Platform
 * MCSP-232: Major Project | Student: Pooja JP
 * 
 * Records, tracks, and audits all monetary flows between:
 * - Customers and Store (Online Digital Payments & Doorstep COD Cash)
 * - Delivery Riders and Store Cashier (COD Cash Handover / Deposits)
 * - Store and Culinary / Delivery Staff (Salaries & Incentives Disbursed)
 * - Store and Customers (Order Cancellation Refunds)
 */

$pageTitle = 'Money & COD Financial Ledger';
$activePage = 'finance';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();
$currentUser = getCurrentUser();

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=zippy_money_transactions_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Txn ID', 'Txn Number', 'Order ID', 'Category', 'Flow', 'Payment Method', 'Amount (INR)', 'Status', 'Reference ID', 'Handled By', 'Notes', 'Created At']);

    $allTxns = FinanceService::getMoneyTransactionsForExport();
    foreach ($allTxns as $t) {
        fputcsv($output, [
            $t['txn_id'],
            $t['txn_number'],
            $t['order_id'] ? '#ZIP-' . $t['order_id'] : 'N/A',
            $t['txn_category'],
            $t['flow_direction'],
            $t['payment_method'],
            $t['amount'],
            $t['status'],
            $t['reference_id'],
            $t['handled_by'],
            $t['notes'],
            $t['created_at']
        ]);
    }
    fclose($output);
    exit;
}

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid session token.');
    } else {
        $res = FinanceService::handleFinancialAction($_POST, $currentUser);
        setFlash($res['type'], $res['message']);
        $zParam = !empty($res['zreport_id']) ? ('?zreport=' . (int)$res['zreport_id']) : '';
        header("Location: " . ADMIN_URL . "/financial-transactions.php" . $zParam);
        exit;
    }
}

$finData = FinanceService::getAdminFinancialLedgerData($_GET);
$filterCat = $finData['filterCat'];
$filterFlow = $finData['filterFlow'];
$filterStatus = $finData['filterStatus'];
$searchQuery = $finData['searchQuery'];
$dateFrom = $finData['dateFrom'];
$dateTo = $finData['dateTo'];
$kpiTotalInward = $finData['kpiTotalInward'];
$kpiOnlinePaid = $finData['kpiOnlinePaid'];
$kpiCodCollected = $finData['kpiCodCollected'];
$kpiCodPending = $finData['kpiCodPending'];
$kpiSalaryOutward = $finData['kpiSalaryOutward'];
$kpiRefundOutward = $finData['kpiRefundOutward'];
$totalTransactions = $finData['totalTransactions'];
$pageSize = $finData['pageSize'];
$totalPages = $finData['totalPages'];
$currentPage = $finData['currentPage'];
$offset = $finData['offset'];
$firstResult = $finData['firstResult'];
$lastResult = $finData['lastResult'];
$transactions = $finData['transactions'];
$paginationParams = $finData['paginationParams'];
$searchFormParams = $finData['searchFormParams'];
$ridersWithCod = $finData['ridersWithCod'];
$recentClosures = $finData['recentClosures'];
$todayClosureDate = $finData['todayClosureDate'];
$todayOrdersSummary = $finData['todayOrdersSummary'];
$todayOnlinePayments = $finData['todayOnlinePayments'];
$todayExpectedCod = $finData['todayExpectedCod'];
$activeZReport = $finData['activeZReport'];

require_once __DIR__ . '/includes/header.php';
?>

<!-- ===========================================================================
     PAGE HEADER & ACTIONS
     =========================================================================== -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <h2 class="fw-bold font-outfit text-dark mb-1">
      <i class="bi bi-cash-coin text-success me-2"></i>Money & COD Financial Ledger
    </h2>
    <p class="text-muted small mb-0">
      Real-time double-entry audit of all monetary flows between Customer, Store, Riders, and Culinary Staff.
    </p>
  </div>

  <div class="d-flex align-items-center gap-2">
    <button type="button" class="btn btn-success rounded-pill px-3 fw-bold btn-sm shadow-sm text-white d-flex align-items-center gap-2" style="background:#2E6F40; border-color:#2E6F40;" data-bs-toggle="modal" data-bs-target="#closeDrawerModal">
      <i class="bi bi-safe2-fill"></i>
      <span>Close Drawer &amp; Z-Report</span>
    </button>

    <?php if (!empty($ridersWithCod)): ?>
      <button type="button" class="btn btn-warning rounded-pill px-3 fw-bold btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#settleRiderCodModal">
        <i class="bi bi-safe me-1"></i> Settle Rider Cash (<?= count($ridersWithCod) ?>)
      </button>
    <?php endif; ?>

    <a href="<?= ADMIN_URL ?>/financial-transactions.php?export=csv" class="btn btn-outline-forest rounded-pill px-3 fw-bold btn-sm shadow-sm" style="border-color:#2E6F40; color:#2E6F40;">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Ledger CSV
    </a>
  </div>
</div>

<!-- ===========================================================================
     FINANCIAL SUMMARY KPI METRICS
     =========================================================================== -->
<div class="row g-3 mb-4">
  
  <!-- KPI 1: Gross Inward Collections -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100" style="border-left: 4px solid #2E6F40 !important;">
      <span class="small text-muted fw-bold text-uppercase d-block mb-1" style="font-size:0.7rem;">Gross Inward</span>
      <h4 class="fw-bold font-outfit text-forest mb-0"><?= formatInr($kpiTotalInward) ?></h4>
      <span class="small text-success" style="font-size:0.72rem;"><i class="bi bi-arrow-down-left"></i> Completed Inward</span>
    </div>
  </div>

  <!-- KPI 2: Online Digital Prepayments -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100" style="border-left: 4px solid #3B82F6 !important;">
      <span class="small text-muted fw-bold text-uppercase d-block mb-1" style="font-size:0.7rem;">Online Digital</span>
      <h4 class="fw-bold font-outfit text-primary mb-0"><?= formatInr($kpiOnlinePaid) ?></h4>
      <span class="small text-muted" style="font-size:0.72rem;">UPI / Cards / NetBank</span>
    </div>
  </div>

  <!-- KPI 3: COD Cash Collected -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100" style="border-left: 4px solid #10B981 !important;">
      <span class="small text-muted fw-bold text-uppercase d-block mb-1" style="font-size:0.7rem;">COD Cash Collected</span>
      <h4 class="fw-bold font-outfit text-success mb-0"><?= formatInr($kpiCodCollected) ?></h4>
      <span class="small text-success" style="font-size:0.72rem;"><i class="bi bi-check-circle"></i> In Store / Dispatched</span>
    </div>
  </div>

  <!-- KPI 4: Pending COD Cash -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100" style="border-left: 4px solid #F59E0B !important;">
      <span class="small text-muted fw-bold text-uppercase d-block mb-1" style="font-size:0.7rem;">Pending COD Cash</span>
      <h4 class="fw-bold font-outfit text-warning mb-0"><?= formatInr($kpiCodPending) ?></h4>
      <span class="small text-warning" style="font-size:0.72rem;"><i class="bi bi-hourglass-split"></i> Awaiting Doorstep Pay</span>
    </div>
  </div>

  <!-- KPI 5: Staff Salary Disbursed -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100" style="border-left: 4px solid #8B5CF6 !important;">
      <span class="small text-muted fw-bold text-uppercase d-block mb-1" style="font-size:0.7rem;">Staff Salaries Paid</span>
      <h4 class="fw-bold font-outfit text-dark mb-0"><?= formatInr($kpiSalaryOutward) ?></h4>
      <span class="small text-danger" style="font-size:0.72rem;"><i class="bi bi-arrow-up-right"></i> Payroll Disbursed</span>
    </div>
  </div>

  <!-- KPI 6: Customer Refunds Disbursed -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100" style="border-left: 4px solid #EF4444 !important;">
      <span class="small text-muted fw-bold text-uppercase d-block mb-1" style="font-size:0.7rem;">Customer Refunds</span>
      <h4 class="fw-bold font-outfit text-danger mb-0"><?= formatInr($kpiRefundOutward) ?></h4>
      <span class="small text-danger" style="font-size:0.72rem;">Reversed Orders</span>
    </div>
  </div>

</div>

<!-- ===========================================================================
     DAILY REGISTER CLOSURES & END-OF-DAY Z-REPORTS AUDIT
     =========================================================================== -->
<div class="card border-0 rounded-4 shadow-sm mb-4 bg-white overflow-hidden">
  <div class="p-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2 bg-light bg-opacity-50">
    <div class="d-flex align-items-center gap-2">
      <div class="rounded-circle p-2 text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: #2E6F40;">
        <i class="bi bi-safe2-fill fs-6"></i>
      </div>
      <div>
        <h6 class="fw-bold font-outfit text-dark mb-0">Daily Register Closures &amp; End-of-Day Z-Reports</h6>
        <span class="small text-muted" style="font-size:0.75rem;">Audited physical cash reconciliations, over/short variances, and sealed drawer logs</span>
      </div>
    </div>

    <div class="d-flex align-items-center gap-2">
      <button type="button" class="btn btn-success btn-sm rounded-pill px-3 fw-bold shadow-xs d-flex align-items-center gap-1" style="background:#2E6F40; border-color:#2E6F40;" data-bs-toggle="modal" data-bs-target="#closeDrawerModal">
        <i class="bi bi-plus-circle me-1"></i> New Drawer Close
      </button>
    </div>
  </div>

  <?php if (empty($recentClosures)): ?>
    <div class="p-4 text-center">
      <div class="rounded-circle p-3 text-muted bg-light d-inline-flex align-items-center justify-content-center mb-2" style="width: 52px; height: 52px;">
        <i class="bi bi-receipt fs-4 text-forest"></i>
      </div>
      <h6 class="fw-bold text-dark mb-1">No Daily Register Closures Sealed Yet</h6>
      <p class="small text-muted mb-3" style="max-width: 480px; margin: 0 auto;">
        At the end of each shift or business day, perform a cash count and seal the drawer to generate an audited Z-Report balancing expected COD handover against physical cash in drawer.
      </p>
      <button type="button" class="btn btn-outline-forest btn-sm rounded-pill px-4 fw-bold" style="border-color:#2E6F40; color:#2E6F40;" data-bs-toggle="modal" data-bs-target="#closeDrawerModal">
        Perform Today's Drawer Count (<?= formatInr($todayExpectedCod) ?> Expected)
      </button>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle small mb-0">
        <thead class="table-light">
          <tr>
            <th>Report #</th>
            <th>Closure Date</th>
            <th>Manager</th>
            <th>Orders</th>
            <th>Gross Sales</th>
            <th>Expected COD</th>
            <th>Physical Count</th>
            <th>Variance</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentClosures as $cl): ?>
            <?php 
              $variance = (float)$cl['discrepancy_amount'];
              $varBadge = match (true) {
                $variance == 0.0 => ['class' => 'bg-success-subtle text-success border border-success-subtle', 'text' => 'Balanced (₹0)'],
                $variance > 0.0  => ['class' => 'bg-info-subtle text-info-emphasis border border-info-subtle', 'text' => '+' . formatInr($variance) . ' Over'],
                default          => ['class' => 'bg-danger-subtle text-danger border border-danger-subtle', 'text' => '-' . formatInr(abs($variance)) . ' Short']
              };
            ?>
            <tr>
              <td class="font-monospace fw-bold text-dark">#ZREP-<?= (int)$cl['closure_id'] ?></td>
              <td>
                <div class="fw-bold text-dark"><?= date('d M Y', strtotime($cl['closure_date'])) ?></div>
                <div class="text-muted" style="font-size:0.75rem;"><?= date('h:i A', strtotime($cl['closed_at'])) ?></div>
              </td>
              <td><?= e($cl['closing_admin_name']) ?></td>
              <td><span class="badge bg-light text-dark border"><?= (int)$cl['total_orders_count'] ?> orders</span></td>
              <td class="fw-bold text-dark"><?= formatInr($cl['gross_sales']) ?></td>
              <td><?= formatInr($cl['expected_cod_cash']) ?></td>
              <td class="fw-bold text-forest"><?= formatInr($cl['actual_physical_cash']) ?></td>
              <td>
                <span class="badge <?= $varBadge['class'] ?> rounded-pill px-2 py-1 font-monospace">
                  <?= $varBadge['text'] ?>
                </span>
              </td>
              <td>
                <span class="badge bg-dark bg-opacity-75 text-white rounded-pill px-2 py-1 font-monospace" style="font-size:0.7rem;">
                  <i class="bi bi-shield-lock me-1"></i><?= e($cl['status']) ?>
                </span>
              </td>
              <td>
                <a href="<?= ADMIN_URL ?>/financial-transactions.php?zreport=<?= (int)$cl['closure_id'] ?>" class="btn btn-outline-forest btn-sm rounded-pill px-3 py-1 shadow-xs d-inline-flex align-items-center gap-1" style="border-color:#2E6F40; color:#2E6F40; font-size:0.75rem;">
                  <i class="bi bi-printer"></i>
                  <span>Print Z-Report</span>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ===========================================================================
     TRANSACTION FILTER TABS & SEARCH CONTROLS
     =========================================================================== -->
<div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
  <div class="row g-3 align-items-center">
    
    <!-- Category Pills -->
    <div class="col-12 col-xl-8">
      <div class="d-flex flex-wrap gap-2">
        <a href="<?= ADMIN_URL ?>/financial-transactions.php?category=ALL" 
           class="btn btn-sm rounded-pill fw-bold <?= ($filterCat === 'ALL') ? 'btn-dark' : 'btn-light border' ?>">
          All Entries
        </a>
        <a href="<?= ADMIN_URL ?>/financial-transactions.php?category=CUSTOMER_PAYMENT_ONLINE" 
           class="btn btn-sm rounded-pill fw-bold <?= ($filterCat === 'CUSTOMER_PAYMENT_ONLINE') ? 'btn-primary' : 'btn-light border' ?>">
          <i class="bi bi-credit-card me-1"></i> Online Customer Prepayments
        </a>
        <a href="<?= ADMIN_URL ?>/financial-transactions.php?category=CUSTOMER_PAYMENT_COD" 
           class="btn btn-sm rounded-pill fw-bold <?= ($filterCat === 'CUSTOMER_PAYMENT_COD') ? 'btn-success' : 'btn-light border' ?>">
          <i class="bi bi-cash-stack me-1"></i> Cash on Delivery (COD)
        </a>
        <a href="<?= ADMIN_URL ?>/financial-transactions.php?category=COD_DEPOSIT_STORE" 
           class="btn btn-sm rounded-pill fw-bold <?= ($filterCat === 'COD_DEPOSIT_STORE') ? 'btn-warning text-dark' : 'btn-light border' ?>">
          <i class="bi bi-safe me-1"></i> Rider Cash Deposits
        </a>
        <a href="<?= ADMIN_URL ?>/financial-transactions.php?category=STAFF_SALARY_PAYOUT" 
           class="btn btn-sm rounded-pill fw-bold <?= ($filterCat === 'STAFF_SALARY_PAYOUT') ? 'btn-secondary' : 'btn-light border' ?>">
          <i class="bi bi-people me-1"></i> Staff Salary Payouts
        </a>
        <a href="<?= ADMIN_URL ?>/financial-transactions.php?category=CUSTOMER_REFUND" 
           class="btn btn-sm rounded-pill fw-bold <?= ($filterCat === 'CUSTOMER_REFUND') ? 'btn-danger' : 'btn-light border' ?>">
          <i class="bi bi-arrow-counterclockwise me-1"></i> Refunds
        </a>
      </div>
    </div>

    <!-- Quick Search Box -->
    <div class="col-12 col-xl-4">
      <form method="GET" action="<?= ADMIN_URL ?>/financial-transactions.php" class="d-flex gap-2 m-0">
        <?php foreach ($searchFormParams as $name => $value): ?>
          <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
        <?php endforeach; ?>
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
          <input type="text" name="search" class="form-control border-start-0"
                 placeholder="Search transaction, order, party..." value="<?= e($searchQuery) ?>">
          <button type="submit" class="btn btn-outline-forest" style="border-color:#2E6F40; color:#2E6F40;">Search</button>
          <?php if (!empty($searchQuery)): ?>
            <?php
              $clearSearchParams = $_GET;
              unset($clearSearchParams['search'], $clearSearchParams['page'], $clearSearchParams['zreport']);
            ?>
            <a href="<?= ADMIN_URL ?>/financial-transactions.php<?= $clearSearchParams ? '?' . e(http_build_query($clearSearchParams)) : '' ?>" class="btn btn-outline-secondary">Clear</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

  </div>
</div>

<!-- ===========================================================================
     FINANCIAL AUDIT LEDGER TABLE
     =========================================================================== -->
<div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden">
  <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between">
    <div class="fw-bold font-outfit text-dark fs-6">
      <i class="bi bi-list-columns-reverse me-2 text-forest"></i>Audited Monetary Ledger Records
    </div>
    <span class="badge bg-light text-dark border font-monospace">
      Showing <?= $firstResult ?>–<?= $lastResult ?> of <?= $totalTransactions ?> entries
    </span>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 small" id="financialLedgerTable">
      <thead class="table-light">
        <tr>
          <th>Txn # & Date</th>
          <th>Order / Reference</th>
          <th>Party Details</th>
          <th>Category & Direction</th>
          <th>Method</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Handled By</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($transactions)): ?>
          <tr>
            <td colspan="9" class="text-center py-5 text-muted">
              <i class="bi bi-receipt fs-1 text-muted opacity-50 d-block mb-2"></i>
              No financial transactions match the selected filters.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($transactions as $t): ?>
            <?php 
              $isInward = ($t['flow_direction'] === 'INWARD');
              $categoryLabel = match ($t['txn_category']) {
                  'CUSTOMER_PAYMENT_ONLINE' => 'Customer Online Payment',
                  'CUSTOMER_PAYMENT_COD'    => 'Customer Cash on Delivery',
                  'COD_DEPOSIT_STORE'       => 'Rider Cash Store Deposit',
                  'CUSTOMER_REFUND'         => 'Customer Refund',
                  'STAFF_SALARY_PAYOUT'     => 'Staff Salary Payout',
                  default                   => $t['txn_category']
              };

            ?>
            <tr>
              
              <!-- Txn # and Date -->
              <td>
                <div class="fw-bold font-monospace text-dark"><?= e($t['txn_number']) ?></div>
                <div class="small text-muted" style="font-size:0.75rem;">
                  <?= date('d M Y, h:i A', strtotime($t['created_at'])) ?>
                </div>
              </td>

              <!-- Order / Ref -->
              <td>
                <?php if (!empty($t['order_id'])): ?>
                  <a href="<?= ADMIN_URL ?>/orders.php?search=<?= (int)$t['order_id'] ?>" class="text-decoration-none fw-bold font-monospace badge bg-dark text-white">
                    #ZIP-<?= (int)$t['order_id'] ?>
                  </a>
                <?php else: ?>
                  <span class="badge bg-light text-muted border">N/A</span>
                <?php endif; ?>
                <?php if (!empty($t['reference_id'])): ?>
                  <div class="font-monospace text-muted mt-1 text-truncate" style="font-size:0.7rem; max-width:130px;" title="<?= e($t['reference_id']) ?>">
                    Ref: <?= e($t['reference_id']) ?>
                  </div>
                <?php endif; ?>
              </td>

              <!-- Counterparty -->
              <td>
                <div class="fw-bold text-dark text-truncate" style="max-width:150px;">
                  <?= e($t['counterparty_name'] ?: ($t['user_id'] ? 'User #' . $t['user_id'] : 'Store Guest')) ?>
                </div>
                <?php if (!empty($t['counterparty_phone'])): ?>
                  <div class="small text-muted font-monospace" style="font-size:0.75rem;">
                    <?= e($t['counterparty_phone']) ?>
                  </div>
                <?php endif; ?>
              </td>

              <!-- Category & Direction -->
              <td>
                <div class="fw-semibold text-dark" style="font-size:0.8rem;"><?= e($categoryLabel) ?></div>
                <?php if ($isInward): ?>
                  <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:0.68rem;">
                    <i class="bi bi-arrow-down-left me-1"></i>INWARD (Customer &rarr; Store)
                  </span>
                <?php else: ?>
                  <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size:0.68rem;">
                    <i class="bi bi-arrow-up-right me-1"></i>OUTWARD (Store &rarr; Staff/Cust)
                  </span>
                <?php endif; ?>
              </td>

              <!-- Payment Method -->
              <td>
                <span class="badge bg-light text-dark border font-monospace" style="font-size:0.75rem;">
                  <?= e($t['payment_method']) ?>
                </span>
              </td>

              <!-- Amount -->
              <td>
                <div class="fw-bold fs-6 font-outfit <?= $isInward ? 'text-forest' : 'text-danger' ?>">
                  <?= $isInward ? '+' : '-' ?><?= formatInr($t['amount']) ?>
                </div>
              </td>

              <!-- Status -->
              <td>
                <?php if ($t['status'] === 'COMPLETED'): ?>
                  <span class="badge bg-success text-white rounded-pill"><i class="bi bi-check-circle me-1"></i>COMPLETED</span>
                <?php elseif ($t['status'] === 'PENDING'): ?>
                  <span class="badge bg-warning text-dark rounded-pill"><i class="bi bi-hourglass-split me-1"></i>PENDING</span>
                <?php elseif ($t['status'] === 'SETTLED'): ?>
                  <span class="badge bg-info text-dark rounded-pill"><i class="bi bi-safe me-1"></i>SETTLED</span>
                <?php elseif ($t['status'] === 'REFUNDED'): ?>
                  <span class="badge bg-danger text-white rounded-pill"><i class="bi bi-arrow-counterclockwise me-1"></i>REFUNDED</span>
                <?php else: ?>
                  <span class="badge bg-secondary"><?= e($t['status']) ?></span>
                <?php endif; ?>
              </td>

              <!-- Handled By & Notes -->
              <td style="max-width:180px;">
                <div class="text-dark fw-semibold text-truncate small" title="<?= e($t['handled_by']) ?>">
                  <i class="bi bi-person-check me-1 text-muted"></i><?= e($t['handled_by'] ?: 'System Engine') ?>
                </div>
                <?php if (!empty($t['notes'])): ?>
                  <div class="text-muted text-truncate" style="font-size:0.7rem;" title="<?= e($t['notes']) ?>">
                    <?= e($t['notes']) ?>
                  </div>
                <?php endif; ?>
              </td>

              <!-- Actions -->
              <td class="text-end">
                <div class="d-flex align-items-center justify-content-end gap-1">
                  
                  <!-- If COD Pending, allow 1-Click "Mark Collected" -->
                  <?php if ($t['txn_category'] === 'CUSTOMER_PAYMENT_COD' && $t['status'] === 'PENDING'): ?>
                    <form method="POST" action="<?= ADMIN_URL ?>/financial-transactions.php" class="m-0" onsubmit="return confirm('Confirm that Cash of <?= formatInr($t['amount']) ?> has been received into store balance?');">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="mark_cod_collected">
                      <input type="hidden" name="txn_id" value="<?= (int)$t['txn_id'] ?>">
                      <input type="hidden" name="order_id" value="<?= (int)$t['order_id'] ?>">
                      <button type="submit" class="btn btn-sm btn-success rounded-pill px-2 py-1 fw-bold" style="font-size:0.75rem;">
                        <i class="bi bi-cash-stack me-1"></i>Mark Paid
                      </button>
                    </form>
                  <?php endif; ?>

                  <!-- Receipt Details Modal Trigger -->
                  <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-1" 
                          data-bs-toggle="modal" data-bs-target="#txnModal<?= (int)$t['txn_id'] ?>" title="View Audit Details">
                    <i class="bi bi-receipt"></i>
                  </button>
                </div>

                <!-- Audit Modal -->
                <div class="modal fade" id="txnModal<?= (int)$t['txn_id'] ?>" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content rounded-4 border-0 shadow">
                      <div class="modal-header border-0 pb-0">
                        <h6 class="modal-title fw-bold font-outfit text-dark">
                          <i class="bi bi-shield-check text-forest me-2"></i>Monetary Audit Record
                        </h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body text-start">
                        <div class="p-3 rounded-3 mb-3 text-center" style="background:#F0FDF4; border:1px solid #CEFFDC;">
                          <div class="small text-muted fw-bold text-uppercase">Transaction Amount</div>
                          <h2 class="fw-bold font-outfit text-forest mb-0"><?= formatInr($t['amount']) ?></h2>
                          <span class="badge bg-success mt-1"><?= e($t['status']) ?></span>
                        </div>

                        <ul class="list-group list-group-flush small">
                          <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Transaction Number:</span>
                            <span class="font-monospace fw-bold"><?= e($t['txn_number']) ?></span>
                          </li>
                          <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Associated Order:</span>
                            <span class="font-monospace fw-bold"><?= $t['order_id'] ? '#ZIP-' . $t['order_id'] : 'Direct Cash Operation' ?></span>
                          </li>
                          <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Category:</span>
                            <span class="fw-semibold"><?= e($categoryLabel) ?></span>
                          </li>
                          <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Flow Direction:</span>
                            <span class="fw-bold <?= $isInward ? 'text-success' : 'text-danger' ?>"><?= e($t['flow_direction']) ?></span>
                          </li>
                          <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Payment Method:</span>
                            <span class="font-monospace"><?= e($t['payment_method']) ?></span>
                          </li>
                          <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Handled By:</span>
                            <span class="fw-semibold"><?= e($t['handled_by'] ?: 'Automated Gateway') ?></span>
                          </li>
                          <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">External Reference:</span>
                            <span class="font-monospace text-truncate" style="max-width:200px;"><?= e($t['reference_id'] ?: 'N/A') ?></span>
                          </li>
                          <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Timestamp:</span>
                            <span class="font-monospace"><?= date('d M Y, h:i:s A', strtotime($t['created_at'])) ?></span>
                          </li>
                          <?php if (!empty($t['notes'])): ?>
                            <li class="list-group-item px-0 pt-2">
                              <span class="text-muted d-block mb-1">Notes / Narration:</span>
                              <div class="p-2 bg-light rounded text-dark fst-italic"><?= e($t['notes']) ?></div>
                            </li>
                          <?php endif; ?>
                        </ul>
                      </div>
                      <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary rounded-pill btn-sm px-4" data-bs-dismiss="modal">Close</button>
                      </div>
                    </div>
                  </div>
                </div>

              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
  <nav class="mt-3" aria-label="Transaction pages">
    <ul class="pagination pagination-sm justify-content-center flex-wrap">
      <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
        <?php $paginationParams['page'] = max(1, $currentPage - 1); ?>
        <a class="page-link" href="<?= ADMIN_URL ?>/financial-transactions.php?<?= e(http_build_query($paginationParams)) ?>" aria-label="Previous page">Previous</a>
      </li>
      <li class="page-item disabled">
        <span class="page-link">Page <?= $currentPage ?> of <?= $totalPages ?></span>
      </li>
      <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
        <?php $paginationParams['page'] = min($totalPages, $currentPage + 1); ?>
        <a class="page-link" href="<?= ADMIN_URL ?>/financial-transactions.php?<?= e(http_build_query($paginationParams)) ?>" aria-label="Next page">Next</a>
      </li>
    </ul>
  </nav>
<?php endif; ?>

<!-- ===========================================================================
     MODAL: SETTLE RIDER COD CASH TO STORE DRAWER
     =========================================================================== -->
<?php if (!empty($ridersWithCod)): ?>
  <div class="modal fade" id="settleRiderCodModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-4 border-0 shadow">
        <form method="POST" action="<?= ADMIN_URL ?>/financial-transactions.php">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="settle_rider_deposit">

          <div class="modal-header border-bottom pb-3">
            <h5 class="modal-title fw-bold font-outfit text-dark">
              <i class="bi bi-safe text-warning me-2"></i>Settle Rider COD Cash to Store
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <p class="small text-muted mb-3">
              Transfer doorstep COD cash collected by active delivery riders into the store cash drawer/vault.
            </p>

            <div class="mb-3">
              <label class="form-label small fw-bold text-dark">Select Delivery Partner</label>
              <select name="rider_user_id" id="modalRiderSelect" class="form-select rounded-3" required onchange="updateSettleAmount(this)">
                <option value="">-- Choose Rider --</option>
                <?php foreach ($ridersWithCod as $r): ?>
                  <option value="<?= (int)$r['user_id'] ?>" data-balance="<?= (float)$r['cod_balance'] ?>">
                    <?= e($r['full_name']) ?> (Outstanding: <?= formatInr($r['cod_balance']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold text-dark">Amount to Deposit (₹)</label>
              <input type="number" step="0.01" name="settle_amount" id="modalSettleAmount" class="form-control rounded-3" placeholder="0.00" required>
            </div>

            <div class="p-3 rounded-3 bg-light border small text-muted">
              <i class="bi bi-info-circle text-primary me-1"></i>
              Upon submission, a <code>COD_DEPOSIT_STORE</code> inward entry will be created and the rider's cash in hand balance will be credited.
            </div>
          </div>

          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold">
              <i class="bi bi-check2-circle me-1"></i> Confirm Cash Deposit
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    function updateSettleAmount(selectEl) {
      const selected = selectEl.options[selectEl.selectedIndex];
      const bal = selected.getAttribute('data-balance');
      if (bal) {
        document.getElementById('modalSettleAmount').value = parseFloat(bal).toFixed(2);
      }
    }
  </script>
<?php endif; ?>

<!-- ===========================================================================
     MODAL: END-OF-DAY CASH DRAWER CLOSING & SEALING WIZARD
     =========================================================================== -->
<div class="modal fade" id="closeDrawerModal" tabindex="-1" aria-labelledby="closeDrawerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 540px;">
    <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
      
      <div class="modal-header border-bottom p-3 px-4 bg-light">
        <div class="d-flex align-items-center gap-2">
          <div class="rounded-circle p-2 text-white d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: #2E6F40;">
            <i class="bi bi-safe2-fill"></i>
          </div>
          <div>
            <h6 class="modal-title fw-bold font-outfit text-dark mb-0" id="closeDrawerModalLabel">End-of-Day Cash Drawer Closing</h6>
            <span class="small text-muted" style="font-size:0.75rem;">Audit physical cash handover &amp; seal daily register</span>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form method="POST" action="<?= ADMIN_URL ?>/financial-transactions.php" id="closeDrawerForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="close_cash_register">
        <input type="hidden" id="systemExpectedCodVal" value="<?= (float)$todayExpectedCod ?>">

        <div class="modal-body p-4">
          
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Register Closure Business Date</label>
            <input type="text" class="form-control rounded-3 bg-light" value="<?= date('d M Y', (int)(strtotime((string)$todayClosureDate) ?: time())) ?>" readonly>
            <input type="hidden" name="closure_date" value="<?= e($todayClosureDate) ?>">
          </div>

          <!-- Live System Financial Reading -->
          <div class="p-3 rounded-3 mb-3 border bg-light">
            <span class="small text-muted fw-bold text-uppercase d-block mb-2" style="font-size:0.7rem; letter-spacing:0.5px;">SYSTEM COMPUTED LEDGER (TODAY)</span>
            
            <div class="d-flex justify-content-between py-1 border-bottom small">
              <span class="text-muted">Orders Placed &amp; Completed:</span>
              <span class="fw-bold text-dark font-monospace"><?= (int)($todayOrdersSummary['total_orders'] ?? 0) ?> orders</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom small">
              <span class="text-muted">Gross Transaction Value:</span>
              <span class="fw-bold text-dark font-monospace"><?= formatInr($todayOrdersSummary['gross_sales'] ?? 0) ?></span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom small">
              <span class="text-muted">Digital UPI / Card Prepayments:</span>
              <span class="fw-bold text-primary font-monospace"><?= formatInr($todayOnlinePayments) ?></span>
            </div>
            <div class="d-flex justify-content-between pt-2 small">
              <span class="fw-bold text-dark">Expected Cash in Drawer:</span>
              <span class="fw-bold text-forest fs-6 font-monospace" id="expectedCodDisplay"><?= formatInr($todayExpectedCod) ?></span>
            </div>
          </div>

          <!-- Physical Cash Count Input -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark d-flex justify-content-between">
              <span>Actual Physical Cash Counted (INR) <span class="text-danger">*</span></span>
              <span class="text-muted small fw-normal">Count bills &amp; coins in drawer</span>
            </label>
            <div class="input-group">
              <span class="input-group-text bg-white fw-bold">₹</span>
              <input type="number" step="0.01" min="0" name="physical_cash" id="physicalCashInput" class="form-control form-control-lg rounded-end-3 font-outfit fs-4 fw-bold" placeholder="0.00" required oninput="recalcDrawerVariance()">
            </div>
          </div>

          <!-- Dynamic Variance Box -->
          <div id="varianceBox" class="p-3 rounded-3 mb-3 border d-flex align-items-center justify-content-between" style="background:#F9FAFB;">
            <div>
              <div class="small fw-bold text-dark" id="varianceTitle">Awaiting Cash Count</div>
              <span class="text-muted" style="font-size:0.75rem;" id="varianceSubtext">Enter actual counted cash to calculate drawer variance</span>
            </div>
            <span class="badge bg-secondary rounded-pill px-3 py-2 font-monospace fs-6" id="varianceBadge">₹0.00</span>
          </div>

          <!-- Discrepancy Remarks -->
          <div class="mb-2">
            <label class="form-label small fw-bold text-dark" id="discrepancyLabel">Discrepancy Notes / Remarks</label>
            <textarea name="discrepancy_notes" id="discrepancyNotesInput" class="form-control rounded-3 small" rows="2" placeholder="Required if physical count differs from expected amount..."></textarea>
          </div>

        </div>

        <div class="modal-footer border-top p-3 px-4 bg-light">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-xs" style="background:#2E6F40; border-color:#2E6F40;">
            <i class="bi bi-shield-lock-fill me-1"></i> Seal Drawer &amp; Print Z-Report
          </button>
        </div>

      </form>

    </div>
  </div>
</div>

<!-- ===========================================================================
     MODAL: 80MM THERMAL PRINTABLE Z-REPORT VIEW
     =========================================================================== -->
<?php if (!empty($activeZReport)): ?>
<div class="modal fade" id="zReportModal" tabindex="-1" aria-labelledby="zReportModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
    <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
      
      <div class="modal-header border-bottom p-3 px-4 bg-light">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-receipt-cutoff text-forest fs-5"></i>
          <h6 class="modal-title fw-bold font-outfit text-dark mb-0">Daily Register Z-Report</h6>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-4 bg-light bg-opacity-25">
        
        <!-- Printable 80mm Thermal Slip Area -->
        <div id="zReportPrintArea" class="zreport-paper mx-auto">
          <div class="text-center mb-3">
            <div class="fw-bold fs-6">ZIPPY CLOUD KITCHEN</div>
            <div class="small">100ft Road, Indiranagar, Bengaluru</div>
            <div class="small text-muted font-monospace">GSTIN: 29AAAAZ0000A1Z5</div>
            <div class="zreport-divider-double"></div>
            <div class="fw-bold font-monospace">*** DAILY REGISTER Z-REPORT ***</div>
            <div class="small text-muted">AUDITED END-OF-DAY REGISTER CLOSE</div>
          </div>

          <div class="zreport-divider"></div>

          <div class="d-flex justify-content-between">
            <span>Z-REPORT REF:</span>
            <strong>#ZREP-<?= (int)$activeZReport['closure_id'] ?></strong>
          </div>
          <div class="d-flex justify-content-between">
            <span>CLOSURE DATE:</span>
            <span><?= date('d-M-Y', strtotime($activeZReport['closure_date'])) ?></span>
          </div>
          <div class="d-flex justify-content-between">
            <span>SEALED TIME:</span>
            <span><?= date('h:i:s A', strtotime($activeZReport['closed_at'])) ?></span>
          </div>
          <div class="d-flex justify-content-between">
            <span>MANAGER:</span>
            <span><?= e($activeZReport['closing_admin_name']) ?></span>
          </div>

          <div class="zreport-divider"></div>

          <div class="fw-bold mb-1">FINANCIAL RECONCILIATION</div>
          <div class="d-flex justify-content-between py-1">
            <span>TOTAL ORDERS:</span>
            <span><?= (int)$activeZReport['total_orders_count'] ?></span>
          </div>
          <div class="d-flex justify-content-between py-1">
            <span>GROSS ORDER VALUE:</span>
            <span><?= formatInr($activeZReport['gross_sales']) ?></span>
          </div>
          <div class="d-flex justify-content-between py-1">
            <span>ONLINE PREPAYMENTS:</span>
            <span><?= formatInr($activeZReport['online_payments_total']) ?></span>
          </div>
          <div class="d-flex justify-content-between py-1 border-top pt-1">
            <span>EXPECTED COD CASH:</span>
            <strong><?= formatInr($activeZReport['expected_cod_cash']) ?></strong>
          </div>
          <div class="d-flex justify-content-between py-1">
            <span>ACTUAL PHYSICAL COUNT:</span>
            <strong><?= formatInr($activeZReport['actual_physical_cash']) ?></strong>
          </div>

          <div class="zreport-divider-double"></div>

          <?php 
            $zVar = (float)$activeZReport['discrepancy_amount'];
          ?>
          <div class="d-flex justify-content-between fw-bold fs-6">
            <span>CASH VARIANCE:</span>
            <span><?= ($zVar >= 0 ? '+' : '') . formatInr($zVar) ?></span>
          </div>
          <div class="d-flex justify-content-between small text-muted">
            <span>DRAWER STATUS:</span>
            <strong><?= ($zVar == 0 ? 'BALANCED' : ($zVar > 0 ? 'CASH OVERAGE' : 'CASH SHORTAGE')) ?></strong>
          </div>

          <?php if (!empty($activeZReport['discrepancy_reason'])): ?>
            <div class="mt-2 p-2 bg-light border rounded small">
              <strong>Audit Remarks:</strong><br>
              <?= nl2br(e($activeZReport['discrepancy_reason'])) ?>
            </div>
          <?php endif; ?>

          <div class="zreport-divider"></div>

          <div class="mt-4 pt-3 border-top d-flex justify-content-between text-center small text-muted">
            <div style="width: 45%;">
              <div class="border-top pt-1">Cashier Sign</div>
            </div>
            <div style="width: 45%;">
              <div class="border-top pt-1">Manager Sign</div>
            </div>
          </div>

          <div class="text-center mt-3 small text-muted font-monospace" style="font-size:0.7rem;">
            * SYSTEM GENERATED AUDIT RECORD *
          </div>
        </div>

      </div>

      <div class="modal-footer border-top p-3 px-4 bg-light d-flex justify-content-between">
        <button type="button" class="btn btn-outline-secondary rounded-pill px-3 btn-sm" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-success rounded-pill px-4 btn-sm fw-bold shadow-xs" style="background:#2E6F40; border-color:#2E6F40;" onclick="window.print()">
          <i class="bi bi-printer-fill me-1"></i> Print 80mm Slip
        </button>
      </div>

    </div>
  </div>
</div>
<?php endif; ?>

<script>
function recalcDrawerVariance() {
  const expected = parseFloat(document.getElementById('systemExpectedCodVal')?.value || 0);
  const physicalInput = document.getElementById('physicalCashInput');
  const physical = parseFloat(physicalInput?.value || 0);
  const variance = physical - expected;

  const box = document.getElementById('varianceBox');
  const badge = document.getElementById('varianceBadge');
  const title = document.getElementById('varianceTitle');
  const subtext = document.getElementById('varianceSubtext');
  const notes = document.getElementById('discrepancyNotesInput');

  if (physicalInput.value === '') {
    box.style.background = '#F9FAFB';
    box.style.borderColor = '#E5E7EB';
    badge.className = 'badge bg-secondary rounded-pill px-3 py-2 font-monospace fs-6';
    badge.textContent = '₹0.00';
    title.textContent = 'Awaiting Cash Count';
    subtext.textContent = 'Enter actual counted cash to calculate drawer variance';
    if (notes) notes.required = false;
    return;
  }

  if (Math.abs(variance) < 0.01) {
    box.style.background = '#EDFBF2';
    box.style.borderColor = '#68BA7F';
    badge.className = 'badge bg-success rounded-pill px-3 py-2 font-monospace fs-6';
    badge.textContent = '₹0.00 (Balanced)';
    title.textContent = 'Perfect Balance';
    subtext.textContent = 'Physical cash matches system expected register handover perfectly';
    if (notes) notes.required = false;
  } else if (variance > 0) {
    box.style.background = '#EFF6FF';
    box.style.borderColor = '#93C5FD';
    badge.className = 'badge bg-primary rounded-pill px-3 py-2 font-monospace fs-6';
    badge.textContent = `+₹${variance.toFixed(2)} (Over)`;
    title.textContent = 'Cash Surplus / Overage';
    subtext.textContent = `Physical cash is higher than expected by ₹${variance.toFixed(2)}`;
    if (notes) notes.required = false;
  } else {
    box.style.background = '#FFF1F2';
    box.style.borderColor = '#FDA4AF';
    badge.className = 'badge bg-danger rounded-pill px-3 py-2 font-monospace fs-6';
    badge.textContent = `-₹${Math.abs(variance).toFixed(2)} (Short)`;
    title.textContent = 'Cash Shortage Warning';
    subtext.textContent = `Physical cash is short by ₹${Math.abs(variance).toFixed(2)}. Please record explanation below.`;
    if (notes) notes.required = true;
  }
}

// Auto-open Z-Report modal if launched from URL
document.addEventListener('DOMContentLoaded', () => {
  const zModalEl = document.getElementById('zReportModal');
  if (zModalEl && typeof bootstrap !== 'undefined') {
    const m = new bootstrap.Modal(zModalEl);
    m.show();
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
