<?php
/**
 * Post-Delivery Rating & Review System
 * MCSP-232: E-Commerce Platform for Food Order
 */
$pageTitle = 'Rate Your Order - Zippy';
require_once __DIR__ . '/includes/header.php';

$orderNumber = $_GET['order'] ?? 'ZIP-849201';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    setFlash('success', 'Thank you for your valuable feedback and rating!');
    header('Location: order-history.php');
    exit;
}
?>

  <main class="container-xl py-4" style="max-width: 680px;">
    
    <div class="p-4 p-md-5 bg-white rounded-4 border shadow-sm">
      
      <div class="text-center pb-3 border-bottom mb-4">
        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1 rounded-pill mb-2 font-monospace">
          ORDER #<?= sanitize($orderNumber) ?> DELIVERED
        </span>
        <h3 class="fw-bold text-pine mb-1">How was your meal from Meghana Foods?</h3>
        <p class="text-muted small mb-0">Your ratings help us improve food hygiene, kitchen prep, and courier speed.</p>
      </div>

      <form method="POST" action="rate-order.php">
        
        <!-- Food Rating (5 Stars) -->
        <div class="text-center mb-4">
          <label class="form-label fw-bold text-pine d-block mb-2">Rate Food Taste & Temperature</label>
          <div class="d-flex justify-content-center gap-2 fs-2 text-warning cursor-pointer">
            <i class="bi bi-star-fill"></i>
            <i class="bi bi-star-fill"></i>
            <i class="bi bi-star-fill"></i>
            <i class="bi bi-star-fill"></i>
            <i class="bi bi-star-fill"></i>
          </div>
          <span class="small text-muted fw-bold">5.0 • Loved It!</span>
        </div>

        <!-- Food Feedback Chips -->
        <div class="mb-4">
          <label class="form-label small fw-bold text-pine d-block mb-2">What did you enjoy most?</label>
          <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-light text-pine border p-2 cursor-pointer border-success text-success"><i class="bi bi-check2 me-1"></i> Piping Hot</span>
            <span class="badge bg-light text-pine border p-2 cursor-pointer border-success text-success"><i class="bi bi-check2 me-1"></i> Authentic Dum Flavors</span>
            <span class="badge bg-light text-pine border p-2 cursor-pointer">Generous Portions</span>
            <span class="badge bg-light text-pine border p-2 cursor-pointer border-success text-success"><i class="bi bi-check2 me-1"></i> Spill-Proof Packing</span>
          </div>
        </div>

        <!-- Courier Rating -->
        <div class="p-3 bg-light rounded-3 border mb-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-person-badge-fill text-success fs-4"></i>
              <div>
                <div class="fw-bold small text-pine">Rate Courier: Ramesh Kumar</div>
                <div class="text-muted" style="font-size: 0.72rem;">Delivered on time with insulated bag</div>
              </div>
            </div>
            <div class="text-warning fs-5">
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
              <i class="bi bi-star-fill"></i>
            </div>
          </div>

          <!-- Rider Tip Selector -->
          <div class="mt-3 pt-2 border-top">
            <label class="small fw-bold text-pine d-block mb-2"><i class="bi bi-heart-fill text-danger me-1"></i> Add a tip for Ramesh?</label>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold">₹20</button>
              <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-bold" style="background-color: var(--lush-forest);">₹30</button>
              <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold">₹50</button>
              <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Other</button>
            </div>
          </div>
        </div>

        <!-- Comments Textarea -->
        <div class="mb-4">
          <label class="form-label small fw-bold text-pine">Detailed Review (Optional)</label>
          <textarea class="form-control" rows="3" placeholder="Tell us more about the biryani tenderness, spices, or delivery experience..."></textarea>
        </div>

        <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold py-3 shadow-xs" style="background-color: var(--lush-forest);">
          Submit Review & Ratings
        </button>

      </form>

    </div>

  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
