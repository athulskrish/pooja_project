<?php
declare(strict_types=1);
/**
 * Admin Support Ticket Desk (Threaded Customer Care Inbox)
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Customer Support Ticket Desk';
$activePage = 'support_tickets';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();
$currentUser = getCurrentUser();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid or expired CSRF token.');
    } else {
        $res = AdminService::handleSupportTicketAction($_POST, $currentUser);
        setFlash($res['type'], $res['message']);
        $redirectQs = '';
        if (!empty($_GET['status'])) {
            $redirectQs = '?status=' . urlencode((string)$_GET['status']);
        }
        header('Location: ' . ADMIN_URL . '/support-tickets.php' . $redirectQs);
        exit;
    }
}

$data = AdminService::getAdminSupportTicketsData($_GET);
$tickets = $data['tickets'];
$repliesByTicket = $data['repliesByTicket'];
$counts = $data['counts'];
$totalCount = (int)$data['totalCount'];
$statusFilter = (string)$data['statusFilter'];
$search = (string)$data['search'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <span class="small fw-bold text-success text-uppercase">Customer Care Desk</span>
    <h1 class="h3 fw-bold font-outfit text-dark mb-1"><i class="bi bi-headset text-success me-2"></i>Support Ticket Inbox</h1>
    <p class="text-muted mb-0">Respond to customer inquiries, update ticket statuses, and maintain complete audit history.</p>
  </div>
</div>

<!-- Status Filter Pills & Search Bar -->
<div class="card border-0 rounded-4 shadow-sm p-3 bg-white mb-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <a href="<?= ADMIN_URL ?>/support-tickets.php?status=ALL<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
         class="btn btn-sm rounded-pill <?= $statusFilter === 'ALL' ? 'btn-dark' : 'btn-light border' ?>">
        All Tickets (<?= $totalCount ?>)
      </a>
      <a href="<?= ADMIN_URL ?>/support-tickets.php?status=OPEN<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
         class="btn btn-sm rounded-pill <?= $statusFilter === 'OPEN' ? 'btn-danger' : 'btn-light border' ?>">
        Open (<?= (int)($counts['OPEN'] ?? 0) ?>)
      </a>
      <a href="<?= ADMIN_URL ?>/support-tickets.php?status=IN_PROGRESS<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
         class="btn btn-sm rounded-pill <?= $statusFilter === 'IN_PROGRESS' ? 'btn-warning text-dark' : 'btn-light border' ?>">
        In Progress (<?= (int)($counts['IN_PROGRESS'] ?? 0) ?>)
      </a>
      <a href="<?= ADMIN_URL ?>/support-tickets.php?status=RESOLVED<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
         class="btn btn-sm rounded-pill <?= $statusFilter === 'RESOLVED' ? 'btn-success' : 'btn-light border' ?>">
        Resolved (<?= (int)($counts['RESOLVED'] ?? 0) ?>)
      </a>
      <a href="<?= ADMIN_URL ?>/support-tickets.php?status=CLOSED<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
         class="btn btn-sm rounded-pill <?= $statusFilter === 'CLOSED' ? 'btn-secondary' : 'btn-light border' ?>">
        Closed (<?= (int)($counts['CLOSED'] ?? 0) ?>)
      </a>
    </div>

    <form method="GET" action="<?= ADMIN_URL ?>/support-tickets.php" class="d-flex gap-2">
      <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
      <input type="search" name="search" class="form-control form-control-sm rounded-pill" placeholder="Search #ID, email, subject..." value="<?= e($search) ?>">
      <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold">Search</button>
    </form>
  </div>
</div>

<!-- Tickets List -->
<?php if (empty($tickets)): ?>
  <div class="card border-0 rounded-4 shadow-sm p-5 text-center bg-white">
    <i class="bi bi-inbox fs-1 text-muted opacity-50 mb-2"></i>
    <h5 class="fw-bold text-dark">No Support Tickets Found</h5>
    <p class="text-muted small mb-0">No customer tickets match the active filter criteria.</p>
  </div>
<?php else: ?>
  <div class="d-flex flex-column gap-3">
    <?php foreach ($tickets as $t): ?>
      <?php
        $tid = (int)$t['ticket_id'];
        $tStatus = strtoupper((string)($t['ticket_status'] ?? 'OPEN'));
        $tReplies = $repliesByTicket[$tid] ?? [];
        $badgeCls = match ($tStatus) {
          'RESOLVED' => 'bg-success-subtle text-success border-success-subtle',
          'IN_PROGRESS' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
          'CLOSED' => 'bg-secondary-subtle text-secondary border-secondary-subtle',
          default => 'bg-danger-subtle text-danger border-danger-subtle',
        };
      ?>
      <div class="card border-0 rounded-4 shadow-sm p-4 bg-white">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 border-bottom pb-3 mb-3">
          <div>
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
              <span class="badge bg-dark font-monospace">#TKT-<?= $tid ?></span>
              <span class="badge border <?= $badgeCls ?>"><?= e($tStatus) ?></span>
              <h6 class="fw-bold text-dark mb-0"><?= e($t['subject']) ?></h6>
            </div>
            <div class="small text-muted">
              <i class="bi bi-person me-1"></i><strong><?= e($t['customer_name']) ?></strong>
              &bull; <?= e($t['customer_email']) ?>
              <?php if (!empty($t['phone_number'])): ?>
                &bull; <i class="bi bi-telephone me-1"></i><?= e($t['phone_number']) ?>
              <?php endif; ?>
              <?php if (!empty($t['assigned_admin_name'])): ?>
                &bull; Assigned: <strong class="text-success"><?= e($t['assigned_admin_name']) ?></strong>
              <?php endif; ?>
            </div>
          </div>

          <div class="d-flex align-items-center gap-2">
            <span class="small text-muted font-monospace"><?= e(date('M j, Y g:i A', (int)(strtotime((string)$t['created_at']) ?: time()))) ?></span>
            <?php if ($tStatus !== 'RESOLVED' && $tStatus !== 'CLOSED'): ?>
              <form method="POST" action="<?= ADMIN_URL ?>/support-tickets.php?status=<?= urlencode($statusFilter) ?>" class="m-0">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="ticket_id" value="<?= $tid ?>">
                <input type="hidden" name="ticket_status" value="RESOLVED">
                <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold">
                  <i class="bi bi-check2-circle me-1"></i>Mark Resolved
                </button>
              </form>
            <?php endif; ?>
            <?php if ($tStatus !== 'CLOSED'): ?>
              <form method="POST" action="<?= ADMIN_URL ?>/support-tickets.php?status=<?= urlencode($statusFilter) ?>" class="m-0">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="ticket_id" value="<?= $tid ?>">
                <input type="hidden" name="ticket_status" value="CLOSED">
                <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Close</button>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <div class="p-3 rounded-3 bg-light border mb-3 small text-dark">
          <?= nl2br(e($t['message'])) ?>
        </div>

        <?php if (!empty($tReplies)): ?>
          <div class="d-flex flex-column gap-2 mb-3 ms-md-4">
            <?php foreach ($tReplies as $rep): ?>
              <?php $isAdminRep = strtoupper((string)($rep['sender_role'] ?? '')) === 'ADMIN'; ?>
              <div class="p-2 px-3 rounded-3 small <?= $isAdminRep ? 'bg-success-subtle border border-success-subtle' : 'bg-light border' ?>">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <strong class="<?= $isAdminRep ? 'text-success' : 'text-dark' ?>">
                    <?= e($rep['sender_name']) ?> (<?= e($rep['sender_role']) ?>)
                  </strong>
                  <span class="text-muted" style="font-size:0.72rem;"><?= e(date('M j, g:i A', (int)(strtotime((string)$rep['created_at']) ?: time()))) ?></span>
                </div>
                <div><?= nl2br(e($rep['reply_message'])) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Admin Threaded Reply Box -->
        <form method="POST" action="<?= ADMIN_URL ?>/support-tickets.php?status=<?= urlencode($statusFilter) ?>" class="row g-2 align-items-center">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="reply_ticket">
          <input type="hidden" name="ticket_id" value="<?= $tid ?>">
          <div class="col-md-7">
            <input type="text" name="reply_message" class="form-control form-control-sm rounded-pill" placeholder="Type official response to customer..." required>
          </div>
          <div class="col-md-3">
            <select name="ticket_status" class="form-select form-select-sm rounded-pill">
              <option value="IN_PROGRESS" <?= $tStatus === 'IN_PROGRESS' ? 'selected' : '' ?>>Set Status: In Progress</option>
              <option value="RESOLVED" <?= $tStatus === 'RESOLVED' ? 'selected' : '' ?>>Set Status: Resolved</option>
              <option value="CLOSED">Set Status: Closed</option>
              <option value="OPEN">Keep Open</option>
            </select>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-success w-100 rounded-pill fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
              <i class="bi bi-send me-1"></i>Send Reply
            </button>
          </div>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
