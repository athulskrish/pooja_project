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
    $ticketSubmitted = true;
    set_flash('success', 'Your inquiry has been registered. An executive will contact you shortly.');
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
        Browse through commonly asked questions or raise an issue regarding your recent food order.
      </p>
    </div>

    <div class="row g-4 justify-content-center">
      
      <!-- Left Column: FAQ Accordion -->
      <div class="col-lg-7">
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
          <h5 class="fw-bold text-dark border-bottom pb-3 mb-3"><i class="bi bi-question-circle-fill text-success me-2"></i>Frequently Asked Questions</h5>
          
          <div class="accordion accordion-flush" id="faqAccordion">
            
            <div class="accordion-item">
              <h2 class="accordion-header" id="faqOne">
                <button class="accordion-button fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true">
                  What is the cancellation policy on food orders?
                </button>
              </h2>
              <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                <div class="accordion-body text-muted small">
                  Orders can be cancelled with 100% refund within 60 seconds of placement. Once the restaurant kitchen accepts the ticket and marks status as <code>COOKING</code>, cancellations are non-refundable as fresh meals are perishable under FSSAI rules.
                </div>
              </div>
            </div>

            <div class="accordion-item">
              <h2 class="accordion-header" id="faqTwo">
                <button class="accordion-button collapsed fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                  How does the 6-Digit Delivery OTP work?
                </button>
              </h2>
              <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body text-muted small">
                  Every order generates an immutable 6-digit cryptographic OTP in the <code>delivery_dispatches</code> table. The courier cannot mark the delivery complete without receiving and submitting this code, guaranteeing secure doorstep delivery handshakes.
                </div>
              </div>
            </div>

            <div class="accordion-item">
              <h2 class="accordion-header" id="faqThree">
                <button class="accordion-button collapsed fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                  How are delivery ETAs calculated?
                </button>
              </h2>
              <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body text-muted small">
                  Our platform utilizes a multi-factor heuristic: <code>ETA = Buffer + Max(Prep Time) + Concurrency Factor + Distance Zone Travel Time</code>. In addition, the kitchen prioritizes tickets via the Weighted Shortest Job First (WSJF) algorithm.
                </div>
              </div>
            </div>

            <div class="accordion-item">
              <h2 class="accordion-header" id="faqFour">
                <button class="accordion-button collapsed fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour">
                  Are GST charges included in the final bill?
                </button>
              </h2>
              <div id="collapseFour" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body text-muted small">
                  Yes, 5% GST is split equally into 2.5% CGST and 2.5% SGST under SAC code 996331. An official printable GST Tax Invoice is generated for every completed transaction.
                </div>
              </div>
            </div>

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
