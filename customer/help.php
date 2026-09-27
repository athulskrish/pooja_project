<?php
/**
 * Customer Help & Support Hub with AI Chatbot Simulator
 * MCSP-232: E-Commerce Platform for Food Order
 */
$pageTitle = 'Help & Support - Zippy';
require_once __DIR__ . '/includes/header.php';
?>

  <main class="container-xl py-4" style="max-width: 920px;">
    
    <!-- Hero Banner -->
    <div class="p-4 p-md-5 bg-white rounded-4 border shadow-sm mb-4 text-center">
      <div class="spec-icon mx-auto mb-3 bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; font-size: 2rem;">
        <i class="bi bi-headset"></i>
      </div>
      <h2 class="fw-bold text-pine mb-1">Zippy Customer Help Center</h2>
      <p class="text-muted small mb-4" style="max-width: 520px; margin: 0 auto;">
        Have a question about your live order, payment refund, or restaurant menu? We're here to assist 24x7.
      </p>

      <div class="d-flex justify-content-center gap-3 flex-wrap">
        <a href="#faqAccordion" class="btn btn-sm btn-outline-success rounded-pill px-4 fw-bold">
          <i class="bi bi-question-circle me-1"></i> Browse FAQs
        </a>
        <a href="#chatbotSection" class="btn btn-sm btn-success rounded-pill px-4 fw-bold" style="background-color: var(--lush-forest);">
          <i class="bi bi-robot me-1"></i> Ask Zippy AI Bot
        </a>
      </div>
    </div>

    <!-- 24x7 Interactive AI Chatbot Simulator -->
    <section class="p-4 bg-white rounded-4 border shadow-xs mb-5" id="chatbotSection">
      <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
        <div class="d-flex align-items-center gap-2">
          <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
            <i class="bi bi-robot"></i>
          </div>
          <div>
            <h6 class="fw-bold text-pine mb-0">Zippy AI Virtual Assistant</h6>
            <span class="badge bg-success bg-opacity-10 text-success" style="font-size: 0.65rem;">Always Online</span>
          </div>
        </div>
        <span class="small text-muted">Automated Resolution Simulator</span>
      </div>

      <!-- Chat Log Container -->
      <div class="p-3 bg-light rounded-3 border mb-3 overflow-y-auto" id="chatMessageLog" style="height: 220px;">
        <div class="d-flex gap-2 mb-2">
          <div class="badge bg-success text-white p-2 rounded-circle align-self-start"><i class="bi bi-robot"></i></div>
          <div class="p-2 px-3 bg-white rounded-3 shadow-xs small text-pine" style="max-width: 80%;">
            Hello Pooja! I am your Zippy automated assistant. How can I help you today? You can ask about order status, cancellation, or tax invoices.
          </div>
        </div>
      </div>

      <!-- Quick Prompt Chips -->
      <div class="d-flex gap-2 flex-wrap mb-3">
        <span class="badge bg-white text-pine border p-2 cursor-pointer shadow-xs" onclick="sendChatPrompt('Where is my live order?')">
          <i class="bi bi-geo-alt text-success me-1"></i> Where is my live order?
        </span>
        <span class="badge bg-white text-pine border p-2 cursor-pointer shadow-xs" onclick="sendChatPrompt('How do I download my GST tax invoice?')">
          <i class="bi bi-receipt text-success me-1"></i> Download GST Tax Invoice
        </span>
        <span class="badge bg-white text-pine border p-2 cursor-pointer shadow-xs" onclick="sendChatPrompt('What is your refund policy?')">
          <i class="bi bi-arrow-counterclockwise text-success me-1"></i> Refund Policy
        </span>
      </div>

      <!-- Chat Input Form -->
      <form id="chatSimulatorForm" onsubmit="handleChatSubmit(event)">
        <div class="input-group">
          <input type="text" id="chatInputText" class="form-control" placeholder="Type your question here (e.g. Can I cancel my order?)..." required>
          <button class="btn btn-success px-4 fw-bold" type="submit" style="background-color: var(--lush-forest);">
            Send <i class="bi bi-send ms-1"></i>
          </button>
        </div>
      </form>
    </section>

    <!-- Frequently Asked Questions Accordion -->
    <section class="mb-5" id="faqAccordion">
      <h4 class="fw-bold text-pine mb-3">Frequently Asked Questions</h4>

      <div class="accordion" id="helpFaq">
        
        <!-- FAQ 1 -->
        <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
          <h2 class="accordion-header">
            <button class="accordion-button fw-bold text-pine" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
              How does the 25-minute delivery time guarantee work?
            </button>
          </h2>
          <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#helpFaq">
            <div class="accordion-body small text-muted">
              Zippy uses dynamic <strong>Weighted Shortest Job First (WSJF)</strong> kitchen queuing to calculate dish preparation minutes. Our nearby couriers are dispatched in parallel as dishes simmer, ensuring meals arrive fresh and piping hot within 25 minutes of confirmation.
            </div>
          </div>
        </div>

        <!-- FAQ 2 -->
        <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed fw-bold text-pine" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
              Can I cancel my order after it has been confirmed?
            </button>
          </h2>
          <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#helpFaq">
            <div class="accordion-body small text-muted">
              Because food is fresh and perishable, cancellation is permitted only within 60 seconds of order placement. Once the kitchen displays the status as <strong>COOKING</strong>, food prep has commenced and cancellation cannot be processed.
            </div>
          </div>
        </div>

        <!-- FAQ 3 -->
        <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed fw-bold text-pine" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
              Where can I find the official GST Tax Invoice for my order?
            </button>
          </h2>
          <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#helpFaq">
            <div class="accordion-body small text-muted">
              You can view and print your GST Tax Invoice by visiting the <strong>My Orders</strong> tab and clicking "Invoice", or directly browsing to <code>invoice.php?order=YOUR_ORDER_ID</code>. The invoice includes complete HSN/SAC codes (996331) and 5% food GST breakdowns.
            </div>
          </div>
        </div>

        <!-- FAQ 4 -->
        <div class="accordion-item border rounded-3 mb-2 overflow-hidden">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed fw-bold text-pine" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
              What is the secret 6-digit Delivery OTP?
            </button>
          </h2>
          <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#helpFaq">
            <div class="accordion-body small text-muted">
              The secret delivery OTP is a cryptographically generated one-time code generated upon order confirmation. When your courier partner arrives at your doorstep, verbally provide the code to ensure verified handover and prevent misdeliveries.
            </div>
          </div>
        </div>

      </div>
    </section>

  </main>

  <script>
    function sendChatPrompt(text) {
      document.getElementById('chatInputText').value = text;
      handleChatSubmit(new Event('submit'));
    }

    function handleChatSubmit(e) {
      e.preventDefault();
      const input = document.getElementById('chatInputText');
      const text = input.value.trim();
      if (!text) return;

      const log = document.getElementById('chatMessageLog');

      // Append User Message
      const userDiv = document.createElement('div');
      userDiv.className = 'd-flex justify-content-end mb-2';
      userDiv.innerHTML = `<div class="p-2 px-3 bg-success text-white rounded-3 small shadow-xs" style="max-width: 80%; background-color: var(--lush-forest) !important;">${text}</div>`;
      log.appendChild(userDiv);
      input.value = '';
      log.scrollTop = log.scrollHeight;

      // Bot Response Simulation
      setTimeout(() => {
        let reply = "I am happy to assist! For immediate order issues, you can also reach our 24x7 toll-free support line.";
        const lower = text.toLowerCase();
        if (lower.includes('order') || lower.includes('where') || lower.includes('track')) {
          reply = "Your order #ZIP-948261 is currently in preparation at Meghana Foods kitchen. Current estimated arrival time is within 18 minutes. Click 'Orders' > 'Track Live' to see the courier GPS map.";
        } else if (lower.includes('invoice') || lower.includes('gst') || lower.includes('tax')) {
          reply = "Your printable GST tax invoice is available in the 'My Orders' section. It contains SAC code 996331 with 2.5% CGST and 2.5% SGST breakdowns.";
        } else if (lower.includes('cancel') || lower.includes('refund')) {
          reply = "Orders can be cancelled free of charge within 60 seconds of placement. Once cooking begins in the kitchen, cancellations cannot be processed for food safety reasons.";
        }

        const botDiv = document.createElement('div');
        botDiv.className = 'd-flex gap-2 mb-2';
        botDiv.innerHTML = `
          <div class="badge bg-success text-white p-2 rounded-circle align-self-start"><i class="bi bi-robot"></i></div>
          <div class="p-2 px-3 bg-white rounded-3 shadow-xs small text-pine" style="max-width: 80%;">${reply}</div>
        `;
        log.appendChild(botDiv);
        log.scrollTop = log.scrollHeight;
      }, 500);
    }
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
