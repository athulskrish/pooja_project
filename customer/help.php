<?php
declare(strict_types=1);
/**
 * Customer Support & FAQs Hub
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Help & Customer Care';
$activePage = 'help';

require_once __DIR__ . '/includes/header.php';

$ticketSubmitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid or expired session token.');
    } elseif ($_POST['action'] === 'submit_ticket') {
        $ticketRes = OrderService::submitSupportTicket($_POST, $currentUser);
        $ticketSubmitted = $ticketRes['submitted'];
        setFlash($ticketRes['flash_type'], $ticketRes['message']);
    } elseif ($_POST['action'] === 'reply_ticket' && $currentUser && !empty($currentUser['id'])) {
        $replyRes = OrderService::addCustomerTicketReply(
            (int)($_POST['ticket_id'] ?? 0),
            (int)$currentUser['id'],
            (string)($currentUser['name'] ?? 'Customer'),
            (string)($_POST['message'] ?? '')
        );
        setFlash($replyRes['flash_type'], $replyRes['message']);
        header("Location: " . CUSTOMER_URL . "/help.php#my-tickets");
        exit;
    }
}

$dbFaqs = OrderService::getActiveFaqs();
$myTickets = ($currentUser && !empty($currentUser['id']))
    ? OrderService::getCustomerSupportTickets((int)$currentUser['id'])
    : [];
?>

<main class="py-4">
  <div class="container-xl">
    
    <div class="text-center mb-5">
      <span class="badge rounded-pill px-3 py-1 mb-2 fw-semibold" style="background:#CEFFDC; color:#243E2C;">
        24x7 CUSTOMER ASSISTANCE
      </span>
      <h2 class="fw-bold font-outfit text-dark mb-2">How can we help you today?</h2>
      <p class="text-muted small" style="max-width: 500px; margin: 0 auto;">
        Browse through commonly asked questions managed by our operations desk or send a direct inquiry.
      </p>
    </div>

    <div class="row g-4 justify-content-center">
      
      <!-- Left Column: FAQ Accordion -->
      <div class="col-lg-7">
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
          <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-question-circle-fill text-success me-2"></i>Frequently Asked Questions</h5>
            <span class="badge bg-light text-muted border rounded-pill small"><?= count($dbFaqs) ?> Topics</span>
          </div>
          
          <div class="accordion accordion-flush" id="faqAccordion">
            <?php foreach ($dbFaqs as $idx => $faq): ?>
              <?php $isFirst = ($idx === 0); ?>
              <div class="accordion-item border-bottom py-1">
                <h2 class="accordion-header" id="faqHeading<?= (int)$faq['faq_id'] ?>">
                  <button class="accordion-button <?= $isFirst ? '' : 'collapsed' ?> fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= (int)$faq['faq_id'] ?>" aria-expanded="<?= $isFirst ? 'true' : 'false' ?>">
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill me-2 small" style="font-size:0.7rem;">
                      <?= e($faq['category'] ?? 'General') ?>
                    </span>
                    <?= e($faq['question']) ?>
                  </button>
                </h2>
                <div id="collapse<?= (int)$faq['faq_id'] ?>" class="accordion-collapse collapse <?= $isFirst ? 'show' : '' ?>" data-bs-parent="#faqAccordion">
                  <div class="accordion-body text-muted small pt-2">
                    <?= sanitizeFaqContent((string)$faq['answer']) ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Right Column: Support Ticket Form -->
      <div class="col-lg-5">
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white">
          <h5 class="fw-bold text-dark border-bottom pb-3 mb-3"><i class="bi bi-envelope-paper-fill text-success me-2"></i>Send an Inquiry</h5>
          
          <form method="POST" action="<?= CUSTOMER_URL ?>/help.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="submit_ticket">

            <div class="mb-3">
              <label class="form-label small fw-bold">Issue Category</label>
              <select name="category" class="form-select rounded-3">
                <option value="order_delay">Order Delay / Live ETA Inquiry</option>
                <option value="missing_item">Missing or Incorrect Item</option>
                <option value="payment_refund">Payment Deducted / Refund Status</option>
                <option value="invoice">GST Invoice Query</option>
                <option value="other">Other Inquiry</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold">Order ID (If applicable)</label>
              <input type="text" name="order_id" class="form-control rounded-3" placeholder="#ZIP-1002" value="<?= e($_GET['order_id'] ?? '') ?>">
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold">Describe Your Concern *</label>
              <textarea name="description" class="form-control rounded-3" rows="4" placeholder="Please provide details of your issue..." required></textarea>
            </div>

            <button type="submit" class="btn btn-success w-100 rounded-pill py-3 fw-bold shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
              Submit Support Ticket
            </button>
          </form>
        </div>
      </div>

    </div>

    <?php if ($currentUser && !empty($currentUser['id'])): ?>
      <!-- Customer Support Ticket Tracker & Threaded Conversation -->
      <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mt-4" id="my-tickets">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3 flex-wrap gap-2">
          <div>
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-chat-square-text-fill text-success me-2"></i>My Support Tickets</h5>
            <div class="small text-muted">Track resolution status and reply directly to Zippy Customer Care</div>
          </div>
          <span class="badge bg-light text-dark border rounded-pill px-3 py-1"><?= count($myTickets) ?> Ticket<?= count($myTickets) === 1 ? '' : 's' ?></span>
        </div>

        <?php if (empty($myTickets)): ?>
          <div class="text-center text-muted py-4 small">
            <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
            You haven't submitted any support inquiries yet.
          </div>
        <?php else: ?>
          <div class="d-flex flex-column gap-3">
            <?php foreach ($myTickets as $t): ?>
              <?php
                $tId = (int)$t['ticket_id'];
                $tStatus = strtoupper((string)($t['ticket_status'] ?? 'OPEN'));
                $statusCls = match ($tStatus) {
                  'RESOLVED' => 'bg-success-subtle text-success border-success-subtle',
                  'IN_PROGRESS' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                  'CLOSED' => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                  default => 'bg-danger-subtle text-danger border-danger-subtle',
                };
              ?>
              <div class="border rounded-4 p-3 bg-light bg-opacity-50">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                  <div>
                    <span class="badge bg-dark me-1">#TKT-<?= $tId ?></span>
                    <strong class="text-dark"><?= e($t['subject']) ?></strong>
                  </div>
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge border <?= $statusCls ?>"><?= e($tStatus) ?></span>
                    <span class="small text-muted"><?= e(date('M j, g:i A', (int)(strtotime((string)$t['created_at']) ?: time()))) ?></span>
                  </div>
                </div>
                <p class="small text-dark mb-2 bg-white p-2 rounded-3 border"><?= nl2br(e($t['message'])) ?></p>

                <?php if (!empty($t['replies'])): ?>
                  <div class="d-flex flex-column gap-2 ms-md-3 mb-2">
                    <?php foreach ($t['replies'] as $rep): ?>
                      <?php $isAdminRep = strtoupper((string)($rep['sender_role'] ?? '')) === 'ADMIN'; ?>
                      <div class="p-2 rounded-3 small <?= $isAdminRep ? 'bg-success-subtle border border-success-subtle' : 'bg-white border' ?>">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                          <strong class="<?= $isAdminRep ? 'text-success' : 'text-dark' ?>">
                            <?= $isAdminRep ? '<i class="bi bi-headset me-1"></i>' : '<i class="bi bi-person me-1"></i>' ?>
                            <?= e($rep['sender_name']) ?> (<?= e($rep['sender_role']) ?>)
                          </strong>
                          <span class="text-muted" style="font-size:0.72rem;"><?= e(date('M j, g:i A', (int)(strtotime((string)$rep['created_at']) ?: time()))) ?></span>
                        </div>
                        <div><?= nl2br(e($rep['reply_message'])) ?></div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <?php if ($tStatus !== 'CLOSED'): ?>
                  <form method="POST" action="<?= CUSTOMER_URL ?>/help.php#my-tickets" class="d-flex gap-2 mt-2">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reply_ticket">
                    <input type="hidden" name="ticket_id" value="<?= $tId ?>">
                    <input type="text" name="message" class="form-control form-control-sm rounded-pill" placeholder="Write a follow-up reply on #TKT-<?= $tId ?>..." required>
                    <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold flex-shrink-0">Reply</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
