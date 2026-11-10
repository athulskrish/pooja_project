<?php
declare(strict_types=1);
/**
 * Regulatory & Operations Audit Trail Viewer
 * Zippy - Online Food Ordering Platform
 * Student: Pooja JP | Course: MCSP-232 (MCA Major Project)
 * Implements: UC-22 (Audit System Logs & Trails)
 */

$pageTitle = 'Order Audit Trail Logs';
$activePage = 'audit_logs';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$currentUser = getCurrentUser();

// -----------------------------------------------------------------------------
// CSV Export Stream Handler (Before sending HTML headers)
// -----------------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $records = AdminService::getAuditLogsForExport($_GET);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=zippy_order_audit_trail_' . date('Ymd_His') . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Log ID', 'Order ID', 'Order Number', 'Previous Status', 'New Status', 'Actor Role', 'Actor Name', 'Order Total (INR)', 'Logged Timestamp (IST)']);

    foreach ($records as $row) {
        fputcsv($output, [
            $row['audit_id'],
            $row['order_id'],
            $row['order_number'] ?: ('#ZIP-' . $row['order_id']),
            $row['old_status'],
            $row['new_status'],
            $row['actor_role'],
            $row['actor_name'],
            number_format((float)($row['total_amount'] ?? 0), 2, '.', ''),
            date('d M Y, h:i:s A', strtotime($row['change_timestamp']))
        ]);
    }

    fclose($output);
    exit;
}

$auditData = AdminService::getAuditLogsData($_GET);
$searchQuery = $auditData['searchQuery'];
$searchOrderId = $auditData['searchOrderId'];
$filterStatus = $auditData['filterStatus'];
$filterRole = $auditData['filterRole'];
$datePreset = $auditData['datePreset'];
$dateFrom = $auditData['dateFrom'];
$dateTo = $auditData['dateTo'];
$pageSize = $auditData['pageSize'];
$totalLogs = $auditData['totalLogs'];
$totalPages = $auditData['totalPages'];
$currentPage = $auditData['currentPage'];
$offset = $auditData['offset'];
$firstResult = $auditData['firstResult'];
$lastResult = $auditData['lastResult'];
$auditLogs = $auditData['auditLogs'];
$totalAuditsCount = $auditData['totalAuditsCount'];
$uniqueOrdersAudited = $auditData['uniqueOrdersAudited'];
$transitionsToday = $auditData['transitionsToday'];
$activeActorsCount = $auditData['activeActorsCount'];
$singleOrderDetails = $auditData['singleOrderDetails'];
$singleOrderLifecycle = $auditData['singleOrderLifecycle'];
$currentQueryParams = $auditData['currentQueryParams'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="audit-logs-page mb-4">

  <!-- =======================================================================
       1. PAGE HEADER & ACTIONS
       ======================================================================= -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <h3 class="fw-bold font-outfit mb-0 text-dark">Immutable Order Audit Trail</h3>
        <span class="badge rounded-pill bg-success bg-opacity-10 text-forest fw-bold px-3 py-1 border border-success border-opacity-20">
          <i class="bi bi-shield-check me-1"></i> UC-22 Compliance
        </span>
        <span class="badge rounded-pill bg-light text-muted fw-semibold border px-2 py-1 small">
          <i class="bi bi-lock-fill text-secondary me-1"></i> Tamper-Evident Ledger
        </span>
      </div>
      <p class="text-muted small mb-0 mt-1">
        Comprehensive chronological log of transactional order state transitions across all 4 system actors (Customer, Admin, Kitchen &amp; Courier).
      </p>
    </div>

    <!-- Compliance Actions (Export CSV & Print) -->
    <div class="d-flex align-items-center gap-2 flex-wrap no-print">
      <?php 
        $exportParams = $currentQueryParams;
        $exportParams['export'] = 'csv';
      ?>
      <a href="<?= ADMIN_URL ?>/audit-logs.php?<?= e(http_build_query($exportParams)) ?>" class="btn btn-sm btn-white border rounded-pill px-3 shadow-xs text-dark fw-semibold d-flex align-items-center gap-2">
        <i class="bi bi-file-earmark-spreadsheet text-success fs-6"></i>
        <span>Export CSV</span>
      </a>

      <button type="button" class="btn btn-sm btn-white border rounded-pill px-3 shadow-xs text-dark fw-semibold d-flex align-items-center gap-2" onclick="window.print()">
        <i class="bi bi-printer text-muted fs-6"></i>
        <span>Print Report</span>
      </button>

      <a href="<?= ADMIN_URL ?>/orders.php" class="btn btn-sm btn-forest rounded-pill px-3 shadow-xs fw-semibold text-white d-flex align-items-center gap-2">
        <i class="bi bi-receipt"></i>
        <span>Live Orders Hub</span>
      </a>
    </div>
  </div>

  <!-- Print-Only Official Compliance Header -->
  <div class="d-none d-print-block mb-4 p-3 border-bottom">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h4 class="fw-bold mb-0 text-dark">Zippy Food Delivery - Order Audit Trail Ledger</h4>
        <div class="small text-muted">UC-22 Compliance Audit Report | Generated on: <?= date('d M Y, h:i:s A') ?> (IST)</div>
      </div>
      <div class="text-end small">
        <div><strong>Administrator:</strong> <?= e($currentUser['name'] ?? 'System Admin') ?></div>
        <div class="text-muted">Total Filtered Records: <?= $totalLogs ?></div>
      </div>
    </div>
  </div>

  <!-- =======================================================================
       2. KPI METRIC SUMMARY CARDS
       ======================================================================= -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
      <div class="card border rounded-4 shadow-xs p-3 bg-white h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="small text-muted fw-bold text-uppercase">Total Transitions</span>
          <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-dark" style="width: 32px; height: 32px;">
            <i class="bi bi-arrow-repeat fs-6"></i>
          </div>
        </div>
        <div class="fs-4 fw-bold font-monospace text-dark"><?= number_format($totalAuditsCount) ?></div>
        <div class="small text-muted mt-1">Lifecycle state updates recorded</div>
      </div>
    </div>

    <div class="col-6 col-xl-3">
      <div class="card border rounded-4 shadow-xs p-3 bg-white h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="small text-muted fw-bold text-uppercase">Orders Audited</span>
          <div class="rounded-circle bg-mint-soft text-forest d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
            <i class="bi bi-receipt fs-6"></i>
          </div>
        </div>
        <div class="fs-4 fw-bold font-monospace text-forest"><?= number_format($uniqueOrdersAudited) ?></div>
        <div class="small text-muted mt-1">Distinct orders with full trail</div>
      </div>
    </div>

    <div class="col-6 col-xl-3">
      <div class="card border rounded-4 shadow-xs p-3 bg-white h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="small text-muted fw-bold text-uppercase">Activity Today</span>
          <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-primary" style="width: 32px; height: 32px;">
            <i class="bi bi-calendar-event fs-6"></i>
          </div>
        </div>
        <div class="fs-4 fw-bold font-monospace text-dark"><?= number_format($transitionsToday) ?></div>
        <div class="small text-muted mt-1">Transitions logged today</div>
      </div>
    </div>

    <div class="col-6 col-xl-3">
      <div class="card border rounded-4 shadow-xs p-3 bg-white h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="small text-muted fw-bold text-uppercase">Staff Actors Active</span>
          <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-warning" style="width: 32px; height: 32px;">
            <i class="bi bi-people-fill fs-6"></i>
          </div>
        </div>
        <div class="fs-4 fw-bold font-monospace text-dark"><?= number_format($activeActorsCount) ?></div>
        <div class="small text-muted mt-1">Admins, chefs &amp; couriers logged</div>
      </div>
    </div>
  </div>

  <!-- =======================================================================
       3. SINGLE ORDER LIFECYCLE HERO (Visible when an order is filtered)
       ======================================================================= -->
  <?php if ($singleOrderDetails && !empty($singleOrderLifecycle)): ?>
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4 border-start border-4 border-success">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-3 mb-3">
        <div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <h5 class="fw-bold font-outfit mb-0 text-dark">
              Order #<?= e($singleOrderDetails['order_number'] ?: ('ZIP-' . $singleOrderDetails['order_id'])) ?> Lifecycle Journey
            </h5>
            <?= getStatusBadge($singleOrderDetails['order_status']) ?>
            <span class="badge bg-light text-dark border font-monospace"><?= formatInr($singleOrderDetails['total_amount']) ?></span>
          </div>
          <p class="text-muted small mb-0 mt-1">
            Customer: <strong class="text-dark"><?= e($singleOrderDetails['customer_name'] ?: 'Customer') ?></strong> &bull; 
            Placed: <?= date('d M Y, h:i A', strtotime($singleOrderDetails['placed_at'])) ?> (<?= formatTimeAgo($singleOrderDetails['placed_at']) ?>) &bull;
            Payment: <span class="badge bg-light text-muted border"><?= e($singleOrderDetails['payment_mode'] ?: 'COD') ?> (<?= e($singleOrderDetails['payment_status'] ?: 'PENDING') ?>)</span>
          </p>
        </div>

        <div class="d-flex align-items-center gap-2">
          <a href="<?= ADMIN_URL ?>/orders.php?status=&order_id=<?= (int)$singleOrderDetails['order_id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
            <i class="bi bi-box-arrow-up-right me-1"></i> Open Order Details
          </a>
          <a href="<?= ADMIN_URL ?>/audit-logs.php" class="btn btn-sm btn-light rounded-pill px-3">
            <i class="bi bi-x-circle me-1"></i> Close Order Focus
          </a>
        </div>
      </div>

      <!-- Chronological Stepper Progression -->
      <div class="order-stepper-container pt-2">
        <div class="row g-3">
          <?php 
            $prevStepTime = strtotime($singleOrderDetails['placed_at']);
            foreach ($singleOrderLifecycle as $idx => $step): 
              $curStepTime = strtotime($step['change_timestamp']);
              $dwellSec = max(0, $curStepTime - $prevStepTime);
              $prevStepTime = $curStepTime;

              $dwellDisplay = 'Instant';
              if ($dwellSec >= 60) {
                  $m = (int)floor($dwellSec / 60);
                  $h = (int)floor($m / 60);
                  $remM = $m % 60;
                  $dwellDisplay = ($h > 0) ? "{$h}h {$remM}m" : "{$m} mins";
              } elseif ($dwellSec > 0) {
                  $dwellDisplay = "{$dwellSec}s";
              }
          ?>
            <div class="col-12 col-md-6 col-xl-4">
              <div class="p-3 rounded-3 bg-light border position-relative h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <span class="badge rounded-pill bg-white border text-dark font-monospace small">Step #<?= $idx + 1 ?></span>
                  <span class="badge bg-secondary bg-opacity-10 text-secondary border small">
                    <i class="bi bi-hourglass-split me-1"></i><?= $dwellDisplay ?>
                  </span>
                </div>

                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                  <span class="badge bg-secondary bg-opacity-25 text-dark border small"><?= e($step['old_status'] ?: 'PLACED') ?></span>
                  <i class="bi bi-arrow-right text-muted small"></i>
                  <?= getStatusBadge($step['new_status']) ?>
                </div>

                <div class="small text-muted mb-1 d-flex align-items-center gap-1">
                  <i class="bi bi-person-circle"></i>
                  <span class="fw-semibold text-dark"><?= e($step['actor_name'] ?: 'System Trigger') ?></span>
                  <span class="badge rounded-pill <?php
                    switch ($step['actor_role']) {
                        case 'ADMIN': echo 'bg-danger'; break;
                        case 'KITCHEN': echo 'bg-warning text-dark'; break;
                        case 'RIDER': echo 'bg-info text-dark'; break;
                        default: echo 'bg-success'; break;
                    }
                  ?>" style="font-size:0.65rem;"><?= e($step['actor_role'] ?: 'SYSTEM') ?></span>
                </div>

                <div class="small text-muted font-monospace" style="font-size: 0.75rem;">
                  <i class="bi bi-clock me-1"></i><?= date('d M Y, h:i:s A', $curStepTime) ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- =======================================================================
       4. UNIFIED SEARCH & FILTER CONTROL BAR
       ======================================================================= -->
  <div class="card border rounded-4 shadow-xs p-3 p-xl-4 bg-white mb-4 no-print" id="auditFilterCard">
    <form method="GET" action="<?= ADMIN_URL ?>/audit-logs.php" class="m-0" id="auditFilterForm">
      
      <div class="row g-2 align-items-center">
        <!-- Search Keyword / Order ID / Log ID -->
        <div class="col-12 col-md-4 col-xl-3">
          <label class="form-label small fw-bold text-muted mb-1">Search Identifier / Actor</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" name="search" class="form-control border-start-0" placeholder="Order #, Actor, or Log ID..." value="<?= e($searchQuery) ?>" autocomplete="off">
            <?php if (!empty($searchQuery)): ?>
              <button class="btn btn-light border border-start-0 text-muted" type="button" onclick="this.form.search.value=''; this.form.submit();" title="Clear Search">
                <i class="bi bi-x"></i>
              </button>
            <?php endif; ?>
          </div>
        </div>

        <!-- Target Transition Status -->
        <div class="col-6 col-md-3 col-xl-2">
          <label class="form-label small fw-bold text-muted mb-1">Status State</label>
          <select name="status" class="form-select form-select-sm rounded-pill" onchange="this.form.submit()">
            <option value="ALL" <?= ($filterStatus === 'ALL' || empty($filterStatus)) ? 'selected' : '' ?>>All Statuses</option>
            <option value="PLACED" <?= ($filterStatus === 'PLACED') ? 'selected' : '' ?>>Placed</option>
            <option value="CONFIRMED" <?= ($filterStatus === 'CONFIRMED') ? 'selected' : '' ?>>Confirmed</option>
            <option value="COOKING" <?= ($filterStatus === 'COOKING') ? 'selected' : '' ?>>In Kitchen (Cooking)</option>
            <option value="READY" <?= ($filterStatus === 'READY') ? 'selected' : '' ?>>Ready for Pickup</option>
            <option value="OUT_FOR_DELIVERY" <?= ($filterStatus === 'OUT_FOR_DELIVERY') ? 'selected' : '' ?>>Out for Delivery</option>
            <option value="DELIVERED" <?= ($filterStatus === 'DELIVERED') ? 'selected' : '' ?>>Delivered</option>
            <option value="CANCELLED" <?= ($filterStatus === 'CANCELLED') ? 'selected' : '' ?>>Cancelled</option>
          </select>
        </div>

        <!-- Initiated By (Role) -->
        <div class="col-6 col-md-3 col-xl-2">
          <label class="form-label small fw-bold text-muted mb-1">Initiating Actor</label>
          <select name="role" class="form-select form-select-sm rounded-pill" onchange="this.form.submit()">
            <option value="ALL" <?= ($filterRole === 'ALL' || empty($filterRole)) ? 'selected' : '' ?>>All Roles</option>
            <option value="ADMIN" <?= ($filterRole === 'ADMIN') ? 'selected' : '' ?>>Administrator</option>
            <option value="KITCHEN" <?= ($filterRole === 'KITCHEN') ? 'selected' : '' ?>>Kitchen Chef</option>
            <option value="RIDER" <?= ($filterRole === 'RIDER') ? 'selected' : '' ?>>Delivery Courier</option>
            <option value="SYSTEM" <?= ($filterRole === 'SYSTEM') ? 'selected' : '' ?>>System / Automated</option>
          </select>
        </div>

        <!-- Date Preset Filter -->
        <div class="col-6 col-md-2 col-xl-2">
          <label class="form-label small fw-bold text-muted mb-1">Timeframe</label>
          <select name="date_preset" id="datePresetSelect" class="form-select form-select-sm rounded-pill" onchange="handleDatePresetChange(this)">
            <option value="ALL" <?= ($datePreset === 'ALL') ? 'selected' : '' ?>>All Time</option>
            <option value="TODAY" <?= ($datePreset === 'TODAY') ? 'selected' : '' ?>>Today</option>
            <option value="YESTERDAY" <?= ($datePreset === 'YESTERDAY') ? 'selected' : '' ?>>Yesterday</option>
            <option value="7D" <?= ($datePreset === '7D') ? 'selected' : '' ?>>Last 7 Days</option>
            <option value="30D" <?= ($datePreset === '30D') ? 'selected' : '' ?>>Last 30 Days</option>
            <option value="CUSTOM" <?= ($datePreset === 'CUSTOM' || !empty($dateFrom) || !empty($dateTo)) ? 'selected' : '' ?>>Custom Dates</option>
          </select>
        </div>

        <!-- Page Limit -->
        <div class="col-6 col-md-2 col-xl-1">
          <label class="form-label small fw-bold text-muted mb-1">Per Page</label>
          <select name="limit" class="form-select form-select-sm rounded-pill" onchange="this.form.submit()">
            <option value="25" <?= ($pageSize === 25) ? 'selected' : '' ?>>25</option>
            <option value="50" <?= ($pageSize === 50) ? 'selected' : '' ?>>50</option>
            <option value="100" <?= ($pageSize === 100) ? 'selected' : '' ?>>100</option>
          </select>
        </div>

        <!-- Submit & Reset Action Buttons -->
        <div class="col-12 col-xl-2 d-flex align-items-end gap-2 mt-2 mt-xl-0">
          <button type="submit" class="btn btn-sm btn-forest rounded-pill px-3 fw-bold flex-grow-1">
            <i class="bi bi-funnel me-1"></i> Filter
          </button>
          <?php if (!empty($searchQuery) || ($filterStatus !== 'ALL' && !empty($filterStatus)) || ($filterRole !== 'ALL' && !empty($filterRole)) || $datePreset !== 'ALL' || !empty($dateFrom) || !empty($dateTo)): ?>
            <a href="<?= ADMIN_URL ?>/audit-logs.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3" title="Clear all active filters">
              <i class="bi bi-arrow-counterclockwise"></i>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Collapsible Custom Date Range Picker -->
      <div id="customDateRangeBox" class="row g-2 mt-2 pt-2 border-top <?= ($datePreset === 'CUSTOM' || (!empty($dateFrom) && $datePreset !== 'TODAY')) ? '' : 'd-none' ?>">
        <div class="col-12 col-sm-5 col-md-4">
          <div class="d-flex align-items-center gap-2">
            <span class="small text-muted fw-bold">From:</span>
            <input type="date" name="date_from" class="form-control form-control-sm rounded-3" value="<?= e($dateFrom) ?>">
          </div>
        </div>
        <div class="col-12 col-sm-5 col-md-4">
          <div class="d-flex align-items-center gap-2">
            <span class="small text-muted fw-bold">To:</span>
            <input type="date" name="date_to" class="form-control form-control-sm rounded-3" value="<?= e($dateTo) ?>">
          </div>
        </div>
        <div class="col-12 col-sm-2 col-md-4 d-flex align-items-center">
          <button type="submit" class="btn btn-sm btn-outline-forest rounded-pill px-3">Apply Date Range</button>
        </div>
      </div>

    </form>

    <!-- 1-Click Status Quick Filter Pills -->
    <div class="d-flex flex-wrap align-items-center gap-1 mt-3 pt-3 border-top">
      <span class="small fw-bold text-muted me-2"><i class="bi bi-filter-circle me-1"></i>Status Quick Pills:</span>
      
      <?php 
        $pillParams = $currentQueryParams;
        unset($pillParams['status']);
      ?>
      <a href="<?= ADMIN_URL ?>/audit-logs.php?<?= e(http_build_query(array_merge($pillParams, ['status' => 'ALL']))) ?>" 
         class="btn btn-xs rounded-pill <?= ($filterStatus === 'ALL' || empty($filterStatus)) ? 'btn-dark' : 'btn-light border' ?>">
        All Statuses
      </a>

      <a href="<?= ADMIN_URL ?>/audit-logs.php?<?= e(http_build_query(array_merge($pillParams, ['status' => 'CONFIRMED']))) ?>" 
         class="btn btn-xs rounded-pill <?= ($filterStatus === 'CONFIRMED') ? 'btn-info text-dark fw-bold' : 'btn-light border' ?>">
        Confirmed
      </a>

      <a href="<?= ADMIN_URL ?>/audit-logs.php?<?= e(http_build_query(array_merge($pillParams, ['status' => 'COOKING']))) ?>" 
         class="btn btn-xs rounded-pill <?= ($filterStatus === 'COOKING') ? 'btn-warning text-dark fw-bold' : 'btn-light border' ?>">
        In Kitchen
      </a>

      <a href="<?= ADMIN_URL ?>/audit-logs.php?<?= e(http_build_query(array_merge($pillParams, ['status' => 'READY']))) ?>" 
         class="btn btn-xs rounded-pill <?= ($filterStatus === 'READY') ? 'btn-primary text-white fw-bold' : 'btn-light border' ?>">
        Ready
      </a>

      <a href="<?= ADMIN_URL ?>/audit-logs.php?<?= e(http_build_query(array_merge($pillParams, ['status' => 'OUT_FOR_DELIVERY']))) ?>" 
         class="btn btn-xs rounded-pill <?= ($filterStatus === 'OUT_FOR_DELIVERY') ? 'btn-warning text-dark fw-bold' : 'btn-light border' ?>">
        Out for Delivery
      </a>

      <a href="<?= ADMIN_URL ?>/audit-logs.php?<?= e(http_build_query(array_merge($pillParams, ['status' => 'DELIVERED']))) ?>" 
         class="btn btn-xs rounded-pill <?= ($filterStatus === 'DELIVERED') ? 'btn-success text-white fw-bold' : 'btn-light border' ?>">
        Delivered
      </a>

      <a href="<?= ADMIN_URL ?>/audit-logs.php?<?= e(http_build_query(array_merge($pillParams, ['status' => 'CANCELLED']))) ?>" 
         class="btn btn-xs rounded-pill <?= ($filterStatus === 'CANCELLED') ? 'btn-danger text-white fw-bold' : 'btn-light border' ?>">
        Cancelled
      </a>
    </div>

  </div>

  <!-- =======================================================================
       5. AUDIT LOGS DATA TABLE
       ======================================================================= -->
  <div class="card border rounded-4 shadow-xs bg-white overflow-hidden mb-3">
    
    <!-- Table Header Info Bar -->
    <div class="p-3 px-4 border-bottom bg-light bg-opacity-50 d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div class="small text-muted">
        Showing <strong class="text-dark"><?= $firstResult ?> - <?= $lastResult ?></strong> of <strong class="text-dark"><?= number_format($totalLogs) ?></strong> transition log records
        <?php if ($totalLogs < $totalAuditsCount): ?>
          <span class="badge bg-secondary bg-opacity-10 text-secondary ms-1">Filtered from <?= number_format($totalAuditsCount) ?></span>
        <?php endif; ?>
      </div>

      <div class="small text-muted font-monospace">
        <i class="bi bi-clock-history me-1"></i>Timezone: Asia/Kolkata (IST)
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle small mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 80px;" class="ps-4">Log ID</th>
            <th style="width: 130px;">Order #</th>
            <th>Lifecycle Status Transition</th>
            <th>Initiated By (Actor)</th>
            <th style="width: 120px;">Order Total</th>
            <th style="width: 200px;">Logged Timestamp</th>
            <th style="width: 100px;" class="text-end pe-4 no-print">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($auditLogs)): ?>
            <tr>
              <td colspan="7" class="text-center py-5">
                <div class="py-4">
                  <div class="rounded-circle bg-light d-inline-flex p-3 text-muted mb-3">
                    <i class="bi bi-journal-x fs-1"></i>
                  </div>
                  <h6 class="fw-bold text-dark mb-1">No Audit Trail Records Found</h6>
                  <p class="text-muted small mb-3">No state transitions matched your query and filter criteria.</p>
                  <a href="<?= ADMIN_URL ?>/audit-logs.php" class="btn btn-sm btn-outline-forest rounded-pill px-4">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filters
                  </a>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($auditLogs as $log): 
              $logTime = strtotime($log['change_timestamp']);
            ?>
              <tr>
                <!-- Log ID -->
                <td class="ps-4">
                  <span class="font-monospace text-muted fw-bold">#<?= (int)$log['audit_id'] ?></span>
                </td>

                <!-- Order Number & Deep Link -->
                <td>
                  <div class="d-flex align-items-center gap-1">
                    <a href="<?= ADMIN_URL ?>/orders.php?status=&order_id=<?= (int)$log['order_id'] ?>" class="fw-bold font-monospace text-decoration-none text-dark hover-forest" title="Open in Orders Management">
                      <?= e($log['order_number'] ?: ('#ZIP-' . $log['order_id'])) ?>
                    </a>
                  </div>
                  <div class="small text-muted" style="font-size:0.7rem;">ID: <?= (int)$log['order_id'] ?></div>
                </td>

                <!-- Lifecycle Status Transition -->
                <td>
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-secondary bg-opacity-15 text-dark border">
                      <?= e($log['old_status'] ?: 'PLACED') ?>
                    </span>
                    <i class="bi bi-arrow-right text-muted" style="font-size: 0.8rem;"></i>
                    <?= getStatusBadge($log['new_status']) ?>
                  </div>
                </td>

                <!-- Initiated By (Actor Role & Full Name) -->
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-pill <?php
                      switch ($log['actor_role']) {
                          case 'ADMIN': echo 'bg-danger text-white'; break;
                          case 'KITCHEN': echo 'bg-warning text-dark'; break;
                          case 'RIDER': echo 'bg-info text-dark'; break;
                          case 'CUSTOMER': echo 'bg-primary text-white'; break;
                          default: echo 'bg-success text-white'; break;
                      }
                    ?>" style="font-size: 0.7rem; padding: 4px 8px;">
                      <?= e($log['actor_role'] ?: 'SYSTEM') ?>
                    </span>
                    <span class="fw-semibold text-dark text-truncate" style="max-width: 150px;" title="<?= e($log['actor_name'] ?: 'System Trigger') ?>">
                      <?= e($log['actor_name'] ?: 'System Trigger') ?>
                    </span>
                  </div>
                </td>

                <!-- Order Total -->
                <td>
                  <span class="fw-bold font-monospace text-dark">
                    <?= !empty($log['total_amount']) ? formatInr($log['total_amount']) : '—' ?>
                  </span>
                </td>

                <!-- Logged Timestamp (Relative + Absolute) -->
                <td>
                  <div class="d-flex align-items-center gap-1">
                    <span class="fw-semibold text-dark"><?= formatTimeAgo($logTime) ?></span>
                  </div>
                  <div class="small text-muted font-monospace" style="font-size: 0.75rem;">
                    <?= date('d M Y, h:i:s A', $logTime) ?>
                  </div>
                </td>

                <!-- Action: Open Timeline Drawer/Modal -->
                <td class="text-end pe-4 no-print">
                  <button type="button" class="btn btn-xs btn-outline-forest rounded-pill px-2 py-1 shadow-none" onclick="openOrderTimeline(<?= (int)$log['order_id'] ?>)" title="View complete lifecycle timeline for this order">
                    <i class="bi bi-clock-history me-1"></i> Trail
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Table Footer with Pagination Controls -->
    <?php if ($totalPages > 1): ?>
      <div class="p-3 border-top bg-light bg-opacity-25 d-flex flex-wrap align-items-center justify-content-between gap-3 no-print">
        <div class="small text-muted">
          Page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong>
        </div>

        <nav aria-label="Audit log navigation">
          <ul class="pagination pagination-sm justify-content-center m-0 flex-wrap gap-1">
            
            <!-- Previous Page -->
            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
              <?php 
                $prevParams = $currentQueryParams;
                $prevParams['page'] = max(1, $currentPage - 1);
              ?>
              <a class="page-link rounded-pill px-3" href="<?= ADMIN_URL ?>/audit-logs.php?<?= e(http_build_query($prevParams)) ?>" aria-label="Previous">
                &laquo; Prev
              </a>
            </li>

            <!-- Page Number Snippets -->
            <?php
              $startPage = max(1, $currentPage - 2);
              $endPage = min($totalPages, $currentPage + 2);
              if ($startPage > 1) {
                  $firstP = $currentQueryParams;
                  $firstP['page'] = 1;
                  echo '<li class="page-item"><a class="page-link rounded-pill" href="' . ADMIN_URL . '/audit-logs.php?' . e(http_build_query($firstP)) . '">1</a></li>';
                  if ($startPage > 2) {
                      echo '<li class="page-item disabled"><span class="page-link border-0">...</span></li>';
                  }
              }

              for ($p = $startPage; $p <= $endPage; $p++) {
                  $pageParams = $currentQueryParams;
                  $pageParams['page'] = $p;
                  $isActive = ($p === $currentPage) ? 'active' : '';
                  echo '<li class="page-item ' . $isActive . '"><a class="page-link rounded-pill" href="' . ADMIN_URL . '/audit-logs.php?' . e(http_build_query($pageParams)) . '">' . $p . '</a></li>';
              }

              if ($endPage < $totalPages) {
                  if ($endPage < $totalPages - 1) {
                      echo '<li class="page-item disabled"><span class="page-link border-0">...</span></li>';
                  }
                  $lastP = $currentQueryParams;
                  $lastP['page'] = $totalPages;
                  echo '<li class="page-item"><a class="page-link rounded-pill" href="' . ADMIN_URL . '/audit-logs.php?' . e(http_build_query($lastP)) . '">' . $totalPages . '</a></li>';
              }
            ?>

            <!-- Next Page -->
            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
              <?php 
                $nextParams = $currentQueryParams;
                $nextParams['page'] = min($totalPages, $currentPage + 1);
              ?>
              <a class="page-link rounded-pill px-3" href="<?= ADMIN_URL ?>/audit-logs.php?<?= e(http_build_query($nextParams)) ?>" aria-label="Next">
                Next &raquo;
              </a>
            </li>

          </ul>
        </nav>
      </div>
    <?php endif; ?>

  </div>

</div>

<!-- =======================================================================
     6. ORDER LIFECYCLE TIMELINE MODAL (DRILL-DOWN)
     ======================================================================= -->
<div class="modal fade" id="orderTimelineModal" tabindex="-1" aria-labelledby="orderTimelineModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
      
      <div class="modal-header border-bottom p-3 px-4 bg-light">
        <div class="d-flex align-items-center gap-2">
          <div class="rounded-circle bg-mint-soft text-forest d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
            <i class="bi bi-clock-history fs-5"></i>
          </div>
          <div>
            <h6 class="modal-title fw-bold font-outfit text-dark mb-0" id="orderTimelineModalLabel">Order Lifecycle Audit Trail</h6>
            <div class="small text-muted" id="timelineModalSubtitle">Chronological tamper-evident transition history</div>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-4" id="orderTimelineModalBody">
        <!-- Spinner while loading -->
        <div class="text-center py-5" id="timelineLoadingSpinner">
          <div class="spinner-border text-forest mb-2" role="status">
            <span class="visually-hidden">Loading audit trail...</span>
          </div>
          <div class="small text-muted">Retrieving immutable audit records...</div>
        </div>

        <!-- Rendered Timeline Container -->
        <div id="timelineContentArea" class="d-none">
          
          <!-- Summary Header Strip -->
          <div class="p-3 rounded-3 bg-light border mb-4 d-flex flex-wrap align-items-center justify-content-between gap-2" id="timelineOrderHeaderStrip">
            <!-- Injected via JS -->
          </div>

          <!-- Vertical Timeline Steps -->
          <div class="audit-timeline-flow position-relative ps-4" id="timelineStepsList">
            <!-- Injected via JS -->
          </div>

        </div>
      </div>

      <div class="modal-footer border-top p-3 px-4 bg-light d-flex justify-content-between">
        <span class="small text-muted" id="timelineTurnaroundBadge"></span>
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>

<style>
/* Custom Audit Trail Stepper Styling */
.hover-forest:hover {
  color: var(--lush-forest, #2E6F40) !important;
  text-decoration: underline !important;
}

.btn-xs {
  font-size: 0.75rem;
  padding: 0.2rem 0.55rem;
}

.audit-timeline-flow {
  border-left: 2px dashed #CFDFD5;
  margin-left: 10px;
}

.audit-timeline-item {
  position: relative;
  margin-bottom: 1.5rem;
}

.audit-timeline-item:last-child {
  margin-bottom: 0;
}

.audit-timeline-item::before {
  content: '';
  position: absolute;
  left: -29px;
  top: 4px;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background-color: #2E6F40;
  border: 3px solid #EDFBF2;
  box-shadow: 0 0 0 1px #2E6F40;
}

@media print {
  .admin-sidebar, 
  .admin-topbar, 
  .admin-sidebar-backdrop, 
  #adminSidebarToggle, 
  .no-print {
    display: none !important;
  }
  .admin-main, .admin-content {
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
  }
  body {
    background: #fff !important;
    color: #000 !important;
  }
  .card {
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
  }
  .table {
    border: 1px solid #dee2e6 !important;
  }
}
</style>

<script>
function handleDatePresetChange(selectEl) {
  const customBox = document.getElementById('customDateRangeBox');
  if (selectEl.value === 'CUSTOM') {
    customBox.classList.remove('d-none');
  } else {
    customBox.classList.add('d-none');
    selectEl.form.submit();
  }
}

function openOrderTimeline(orderId) {
  const modalEl = document.getElementById('orderTimelineModal');
  const spinner = document.getElementById('timelineLoadingSpinner');
  const contentArea = document.getElementById('timelineContentArea');
  const subtitleEl = document.getElementById('timelineModalSubtitle');
  const headerStrip = document.getElementById('timelineOrderHeaderStrip');
  const stepsList = document.getElementById('timelineStepsList');
  const turnaroundBadge = document.getElementById('timelineTurnaroundBadge');

  if (!modalEl || !orderId) return;

  subtitleEl.textContent = `Order #ZIP-${orderId} &bull; Full Lifecycle Journey`;
  spinner.classList.remove('d-none');
  contentArea.classList.add('d-none');

  const bsModal = new bootstrap.Modal(modalEl);
  bsModal.show();

  fetch(`<?= ADMIN_URL ?>/ajax/get-order-audit-trail.php?order_id=${encodeURIComponent(orderId)}`)
    .then(res => res.json())
    .then(data => {
      spinner.classList.add('d-none');
      contentArea.classList.remove('d-none');

      if (!data.success) {
        stepsList.innerHTML = `<div class="alert alert-warning small mb-0">${data.error || 'Failed to load order lifecycle.'}</div>`;
        return;
      }

      const ord = data.order;
      headerStrip.innerHTML = `
        <div>
          <div class="fw-bold font-outfit text-dark fs-6 d-flex align-items-center gap-2">
            <span>#${ord.order_number}</span>
            ${ord.status_badge}
            <span class="badge bg-white border text-dark font-monospace">${ord.total_amount}</span>
          </div>
          <div class="small text-muted mt-1">
            Customer: <strong>${ord.customer_name}</strong> &bull; Placed: ${ord.placed_at} (${ord.placed_time_ago})
          </div>
        </div>
        <div class="text-end small">
          <div>Payment: <strong>${ord.payment_mode}</strong> (${ord.payment_status})</div>
          ${ord.rider_name ? `<div class="text-success"><i class="bi bi-bicycle me-1"></i>Courier: ${ord.rider_name}</div>` : ''}
        </div>
      `;

      turnaroundBadge.innerHTML = `<i class="bi bi-stopwatch text-forest me-1"></i> Total Turnaround Duration: <strong>${ord.turnaround}</strong>`;

      if (!data.timeline || data.timeline.length === 0) {
        stepsList.innerHTML = `<div class="text-muted small py-3">No state transitions recorded for this order.</div>`;
        return;
      }

      let stepsHtml = '';
      data.timeline.forEach((step, idx) => {
        let roleBg = 'bg-success';
        if (step.actor_role === 'ADMIN') roleBg = 'bg-danger';
        else if (step.actor_role === 'KITCHEN') roleBg = 'bg-warning text-dark';
        else if (step.actor_role === 'RIDER') roleBg = 'bg-info text-dark';

        stepsHtml += `
          <div class="audit-timeline-item">
            <div class="p-3 bg-white border rounded-3 shadow-xs">
              <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-1">
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-secondary bg-opacity-25 text-dark border small">${step.old_status}</span>
                  <i class="bi bi-arrow-right text-muted small"></i>
                  ${step.new_badge}
                </div>
                <span class="badge bg-light text-secondary border small font-monospace">
                  <i class="bi bi-hourglass-split me-1"></i>${step.dwell_formatted}
                </span>
              </div>
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 small text-muted">
                <div class="d-flex align-items-center gap-1">
                  <span class="badge rounded-pill ${roleBg}" style="font-size:0.65rem;">${step.actor_role}</span>
                  <span class="fw-semibold text-dark">${step.actor_name}</span>
                </div>
                <div class="font-monospace text-muted" style="font-size:0.75rem;">
                  <i class="bi bi-clock me-1"></i>${step.timestamp} (${step.time_ago})
                </div>
              </div>
            </div>
          </div>
        `;
      });

      stepsList.innerHTML = stepsHtml;
    })
    .catch(err => {
      spinner.classList.add('d-none');
      contentArea.classList.remove('d-none');
      stepsList.innerHTML = `<div class="alert alert-danger small mb-0">Error fetching lifecycle audit details: ${err.message}</div>`;
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
