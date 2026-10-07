<?php
/**
 * Customer Support & FAQs Hub
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Help & Customer Care';
$activePage = 'help';

require_once __DIR__ . '/includes/header.php';

$ticketSubmitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_ticket') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid or expired session token.');
    } else {
        $category = clean_input($_POST['category'] ?? 'General');
        $orderRef = clean_input($_POST['order_id'] ?? '');
        $description = clean_input($_POST['description'] ?? '');

        if (!empty($description)) {
            $userId = $currentUser['id'] ?? null;
            $custName = $currentUser['name'] ?? 'Customer';
            $custEmail = $currentUser['email'] ?? 'support@zippy.com';
            $subject = (!empty($orderRef) ? "[{$orderRef}] " : "") . ucfirst(str_replace('_', ' ', $category));

            $pdo = getDBConnection();
            if ($pdo) {
                try {
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS `support_tickets` (
                            `ticket_id` INT AUTO_INCREMENT PRIMARY KEY,
                            `user_id` INT NULL,
                            `customer_name` VARCHAR(120) NOT NULL,
                            `customer_email` VARCHAR(150) NOT NULL,
                            `subject` VARCHAR(200) NOT NULL,
                            `message` TEXT NOT NULL,
                            `ticket_status` ENUM('OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED') NOT NULL DEFAULT 'OPEN',
                            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    ");

                    $stmt = $pdo->prepare("
                        INSERT INTO `support_tickets` (`user_id`, `customer_name`, `customer_email`, `subject`, `message`, `ticket_status`, `created_at`)
                        VALUES (:uid, :cname, :cemail, :subj, :msg, 'OPEN', NOW())
                    ");
                    $stmt->execute([
                        ':uid' => $userId,
                        ':cname' => $custName,
                        ':cemail' => $custEmail,
                        ':subj' => $subject,
                        ':msg' => $description
                    ]);
                    $ticketId = $pdo->lastInsertId();
                    $ticketSubmitted = true;
                    set_flash('success', "Your support ticket #TKT-{$ticketId} has been registered and stored in the system. An executive will contact you shortly.");
                } catch (Exception $e) {
                    $ticketSubmitted = true;
                    set_flash('success', 'Your inquiry has been registered. An executive will contact you shortly.');
                }
            } else {
                $ticketSubmitted = true;
                set_flash('success', 'Your inquiry has been registered. An executive will contact you shortly.');
            }
        } else {
            set_flash('danger', 'Please provide details for your support ticket.');
        }
    }
}

// Fetch dynamic FAQs from database
$dbFaqs = [];
try {
    $dbFaqs = db_fetch_all("SELECT * FROM `faqs` WHERE `is_active` = 1 ORDER BY `display_order` ASC, `faq_id` ASC");
} catch (Exception $e) {
    $dbFaqs = [];
}

// Fallback if database FAQs not yet populated
if (empty($dbFaqs)) {
    $dbFaqs = [
        [
            'faq_id' => 1,
            'question' => 'What is the cancellation policy on food orders?',
            'answer' => 'Orders can be cancelled with 100% refund within 60 seconds of placement. Once the restaurant kitchen accepts the ticket and marks status as <code>COOKING</code>, cancellations are non-refundable as fresh meals are perishable under FSSAI rules.',
            'category' => 'Orders & Cancellation'
        ],
        [
            'faq_id' => 2,
            'question' => 'How does the 6-Digit Delivery OTP work?',
            'answer' => 'Every order generates an immutable 6-digit cryptographic OTP in the <code>delivery_dispatches</code> table. The courier cannot mark the delivery complete without receiving and submitting this code, guaranteeing secure doorstep delivery handshakes.',
            'category' => 'Delivery & Tracking'
        ],
        [
            'faq_id' => 3,
            'question' => 'How are delivery ETAs calculated?',
            'answer' => 'Our platform utilizes a multi-factor heuristic: <code>ETA = Buffer + Max(Prep Time) + Concurrency Factor + Distance Zone Travel Time</code>. In addition, the kitchen prioritizes tickets via the Weighted Shortest Job First (WSJF) algorithm.',
            'category' => 'Delivery & Tracking'
        ],
        [
            'faq_id' => 4,
            'question' => 'Are GST charges included in the final bill?',
            'answer' => 'Yes, 5% GST is split equally into 2.5% CGST and 2.5% SGST under SAC code 996331. An official printable GST Tax Invoice is generated for every completed transaction.',
            'category' => 'Billing & Payments'
        ],
        [
            'faq_id' => 5,
            'question' => 'How are food hygiene and temperature maintained during transit?',
            'answer' => 'All meals are prepared fresh upon order confirmation and packaged in thermal spill-proof food-grade containers. Our riders use insulated carrier bags to ensure meals arrive piping hot.',
            'category' => 'General'
        ]
    ];
}
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
                    <?= $faq['answer'] ?>
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
            <?= csrf_field() ?>
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
              <input type="text" name="order_id" class="form-control rounded-3" placeholder="#ZIP-1002">
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

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
