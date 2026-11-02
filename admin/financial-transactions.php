<?php
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

require_admin();
$currentUser = current_user();

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=zippy_money_transactions_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Txn ID', 'Txn Number', 'Order ID', 'Category', 'Flow', 'Payment Method', 'Amount (INR)', 'Status', 'Reference ID', 'Handled By', 'Notes', 'Created At']);

    $allTxns = db_fetch_all("
        SELECT * FROM `money_transactions` ORDER BY `created_at` DESC
    ");
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
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
    } else {
        $action = $_POST['action'];

        // 1. Mark COD Cash as Collected by Admin
        if ($action === 'mark_cod_collected') {
            $txnId = (int)($_POST['txn_id'] ?? 0);
            $orderId = (int)($_POST['order_id'] ?? 0);

            $tx = db_fetch_one("SELECT * FROM `money_transactions` WHERE `txn_id` = :id", [':id' => $txnId]);
            if ($tx) {
                db_query("
                    UPDATE `money_transactions` 
                    SET `status` = 'COMPLETED',
                        `handled_by` = :hby,
                        `notes` = CONCAT(COALESCE(notes, ''), ' [Cash verified and marked collected by Store Admin]')
                    WHERE `txn_id` = :id
                ", [
                    ':hby' => $currentUser['name'] ?? 'Store Admin',
                    ':id'  => $txnId
                ]);

                if ($orderId > 0) {
                    db_query("
                        UPDATE `payments` 
                        SET `payment_status` = 'SUCCESSFUL', `payment_timestamp` = NOW() 
                        WHERE `order_id` = :oid
                    ", [':oid' => $orderId]);
                }

                set_flash('success', "Transaction {$tx['txn_number']} marked as COMPLETED. COD cash of " . format_inr($tx['amount']) . " collected into store balance.");
            }
            header("Location: " . ADMIN_URL . "/financial-transactions.php");
            exit;
        }

        // 2. Deposit Rider COD Cash to Store Drawer
        if ($action === 'settle_rider_deposit') {
            $riderUserId = (int)($_POST['rider_user_id'] ?? 0);
            $amount = (float)($_POST['settle_amount'] ?? 0);

            if ($riderUserId > 0 && $amount > 0) {
                $riderProfile = db_fetch_one("
                    SELECT sp.*, u.full_name 
                    FROM `staff_profiles` sp 
                    JOIN `users` u ON sp.user_id = u.user_id 
                    WHERE sp.user_id = :uid
                ", [':uid' => $riderUserId]);

                if ($riderProfile) {
                    $ref = 'DEP-STORE-' . strtoupper(bin2hex(random_bytes(4)));
                    record_money_transaction(
                        null,
                        $riderUserId,
                        'COD_DEPOSIT_STORE',
                        'INWARD',
                        'COD_CASH',
                        $amount,
                        'COMPLETED',
                        $ref,
                        $currentUser['name'] ?? 'Store Cashier',
                        "Cash settled into store vault by rider {$riderProfile['full_name']}."
                    );

                    db_query("
                        UPDATE `staff_profiles` 
                        SET `cod_balance` = GREATEST(0, `cod_balance` - :amt) 
                        WHERE `user_id` = :uid
                    ", [
                        ':amt' => $amount,
                        ':uid' => $riderUserId
                    ]);

                    set_flash('success', "Cash deposit of " . format_inr($amount) . " from rider {$riderProfile['full_name']} settled into store vault.");
                }
            }
            header("Location: " . ADMIN_URL . "/financial-transactions.php");
            exit;
        }
    }
}

// Filter params
$filterCat = clean_input($_GET['category'] ?? 'ALL');
$filterFlow = clean_input($_GET['flow'] ?? 'ALL');
$filterStatus = clean_input($_GET['status'] ?? 'ALL');
$searchQuery = clean_input($_GET['search'] ?? '');
$dateFrom = clean_input($_GET['date_from'] ?? '');
$dateTo = clean_input($_GET['date_to'] ?? '');

// Financial KPIs & Summaries
$kpiTotalInward = (float)db_fetch_val("
    SELECT SUM(amount) FROM `money_transactions` 
    WHERE `flow_direction` = 'INWARD' AND `status` = 'COMPLETED'
");

$kpiOnlinePaid = (float)db_fetch_val("
    SELECT SUM(amount) FROM `money_transactions` 
    WHERE `txn_category` = 'CUSTOMER_PAYMENT_ONLINE' AND `status` = 'COMPLETED'
");

$kpiCodCollected = (float)db_fetch_val("
    SELECT SUM(amount) FROM `money_transactions` 
    WHERE `txn_category` = 'CUSTOMER_PAYMENT_COD' AND `status` = 'COMPLETED'
");

$kpiCodPending = (float)db_fetch_val("
    SELECT SUM(amount) FROM `money_transactions` 
    WHERE `txn_category` = 'CUSTOMER_PAYMENT_COD' AND `status` = 'PENDING'
");

$kpiSalaryOutward = (float)db_fetch_val("
    SELECT SUM(amount) FROM `money_transactions` 
    WHERE `txn_category` = 'STAFF_SALARY_PAYOUT'
");

$kpiRefundOutward = (float)db_fetch_val("
    SELECT SUM(amount) FROM `money_transactions` 
    WHERE `txn_category` = 'CUSTOMER_REFUND'
");

// Build dynamic WHERE clause
$where = "1=1";
$params = [];

if ($filterCat !== 'ALL') {
    $where .= " AND mt.txn_category = :cat";
    $params[':cat'] = $filterCat;
}

if ($filterFlow !== 'ALL') {
    $where .= " AND mt.flow_direction = :flow";
    $params[':flow'] = $filterFlow;
}

if ($filterStatus !== 'ALL') {
    $where .= " AND mt.status = :st";
    $params[':st'] = $filterStatus;
}

if (!empty($dateFrom)) {
    $where .= " AND DATE(mt.created_at) >= :dfrom";
    $params[':dfrom'] = $dateFrom;
}

if (!empty($dateTo)) {
    $where .= " AND DATE(mt.created_at) <= :dto";
    $params[':dto'] = $dateTo;
}

if (!empty($searchQuery)) {
    $where .= " AND (
        mt.txn_number LIKE :sq
        OR mt.reference_id LIKE :sq
        OR mt.handled_by LIKE :sq
        OR mt.notes LIKE :sq
        OR u.full_name LIKE :sq
        OR mt.order_id = :sq_id
    )";
    $params[':sq'] = "%" . $searchQuery . "%";
    $params[':sq_id'] = (int)preg_replace('/[^0-9]/', '', $searchQuery);
}

// Query transactions with customer / staff counterparty info
$transactions = db_fetch_all("
    SELECT mt.*, 
           u.full_name AS counterparty_name, 
           u.phone_number AS counterparty_phone, 
           u.email AS counterparty_email,
           o.order_status,
           o.total_amount AS order_total_bill
    FROM `money_transactions` mt
    LEFT JOIN `users` u ON mt.user_id = u.user_id
    LEFT JOIN `orders` o ON mt.order_id = o.order_id
    WHERE $where
    ORDER BY mt.created_at DESC, mt.txn_id DESC
    LIMIT 250
", $params);

// Fetch list of riders with outstanding COD cash balance for settlement modal
$ridersWithCod = db_fetch_all("
    SELECT sp.*, u.full_name, u.phone_number 
    FROM `staff_profiles` sp 
    JOIN `users` u ON sp.user_id = u.user_id 
    WHERE sp.department = 'DELIVERY' AND sp.cod_balance > 0
    ORDER BY sp.cod_balance DESC
");

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
      <h4 class="fw-bold font-outfit text-forest mb-0"><?= format_inr($kpiTotalInward) ?></h4>
      <span class="small text-success" style="font-size:0.72rem;"><i class="bi bi-arrow-down-left"></i> Completed Inward</span>
    </div>
  </div>

  <!-- KPI 2: Online Digital Prepayments -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100" style="border-left: 4px solid #3B82F6 !important;">
      <span class="small text-muted fw-bold text-uppercase d-block mb-1" style="font-size:0.7rem;">Online Digital</span>
      <h4 class="fw-bold font-outfit text-primary mb-0"><?= format_inr($kpiOnlinePaid) ?></h4>
      <span class="small text-muted" style="font-size:0.72rem;">UPI / Cards / NetBank</span>
    </div>
  </div>

  <!-- KPI 3: COD Cash Collected -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100" style="border-left: 4px solid #10B981 !important;">
      <span class="small text-muted fw-bold text-uppercase d-block mb-1" style="font-size:0.7rem;">COD Cash Collected</span>
      <h4 class="fw-bold font-outfit text-success mb-0"><?= format_inr($kpiCodCollected) ?></h4>
      <span class="small text-success" style="font-size:0.72rem;"><i class="bi bi-check-circle"></i> In Store / Dispatched</span>
    </div>
  </div>

  <!-- KPI 4: Pending COD Cash -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100" style="border-left: 4px solid #F59E0B !important;">
      <span class="small text-muted fw-bold text-uppercase d-block mb-1" style="font-size:0.7rem;">Pending COD Cash</span>
      <h4 class="fw-bold font-outfit text-warning mb-0"><?= format_inr($kpiCodPending) ?></h4>
      <span class="small text-warning" style="font-size:0.72rem;"><i class="bi bi-hourglass-split"></i> Awaiting Doorstep Pay</span>
    </div>
  </div>

  <!-- KPI 5: Staff Salary Disbursed -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100" style="border-left: 4px solid #8B5CF6 !important;">
      <span class="small text-muted fw-bold text-uppercase d-block mb-1" style="font-size:0.7rem;">Staff Salaries Paid</span>
      <h4 class="fw-bold font-outfit text-dark mb-0"><?= format_inr($kpiSalaryOutward) ?></h4>
      <span class="small text-danger" style="font-size:0.72rem;"><i class="bi bi-arrow-up-right"></i> Payroll Disbursed</span>
    </div>
  </div>

  <!-- KPI 6: Customer Refunds Disbursed -->
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100" style="border-left: 4px solid #EF4444 !important;">
      <span class="small text-muted fw-bold text-uppercase d-block mb-1" style="font-size:0.7rem;">Customer Refunds</span>
      <h4 class="fw-bold font-outfit text-danger mb-0"><?= format_inr($kpiRefundOutward) ?></h4>
      <span class="small text-danger" style="font-size:0.72rem;">Reversed Orders</span>
    </div>
  </div>

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
          All Entries (<?= count($transactions) ?>)
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
        <?php if ($filterCat !== 'ALL'): ?>
          <input type="hidden" name="category" value="<?= e($filterCat) ?>">
        <?php endif; ?>
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
          <input type="text" name="search" id="liveFinanceSearchInput" class="form-control border-start-0" 
                 placeholder="Search Txn #, ZIP ID, name..." value="<?= e($searchQuery) ?>" onkeyup="filterFinanceTable()">
          <?php if (!empty($searchQuery)): ?>
            <a href="<?= ADMIN_URL ?>/financial-transactions.php" class="btn btn-outline-secondary">Clear</a>
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
      Showing <?= count($transactions) ?> Entries
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
              $categoryLabel = match($t['txn_category']) {
                  'CUSTOMER_PAYMENT_ONLINE' => 'Customer Online Payment',
                  'CUSTOMER_PAYMENT_COD'    => 'Customer Cash on Delivery',
                  'COD_DEPOSIT_STORE'       => 'Rider Cash Store Deposit',
                  'CUSTOMER_REFUND'         => 'Customer Refund',
                  'STAFF_SALARY_PAYOUT'     => 'Staff Salary Payout',
                  default                   => $t['txn_category']
              };

              $searchRowData = strtolower("{$t['txn_number']} ZIP-{$t['order_id']} {$t['counterparty_name']} {$t['counterparty_phone']} {$t['payment_method']} {$t['txn_category']} {$t['status']} {$t['handled_by']} {$t['notes']}");
            ?>
            <tr class="finance-table-row" data-search="<?= e($searchRowData) ?>">
              
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
                  <?= $isInward ? '+' : '-' ?><?= format_inr($t['amount']) ?>
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
                    <form method="POST" action="<?= ADMIN_URL ?>/financial-transactions.php" class="m-0" onsubmit="return confirm('Confirm that Cash of <?= format_inr($t['amount']) ?> has been received into store balance?');">
                      <?= csrf_field() ?>
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
                          <h2 class="fw-bold font-outfit text-forest mb-0"><?= format_inr($t['amount']) ?></h2>
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

<!-- ===========================================================================
     MODAL: SETTLE RIDER COD CASH TO STORE DRAWER
     =========================================================================== -->
<?php if (!empty($ridersWithCod)): ?>
  <div class="modal fade" id="settleRiderCodModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-4 border-0 shadow">
        <form method="POST" action="<?= ADMIN_URL ?>/financial-transactions.php">
          <?= csrf_field() ?>
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
                    <?= e($r['full_name']) ?> (Outstanding: <?= format_inr($r['cod_balance']) ?>)
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

<script>
  // Real-time table filter
  function filterFinanceTable() {
    const q = document.getElementById('liveFinanceSearchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.finance-table-row');
    rows.forEach(r => {
      const searchData = r.getAttribute('data-search') || '';
      if (!q || searchData.includes(q)) {
        r.style.display = '';
      } else {
        r.style.display = 'none';
      }
    });
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
