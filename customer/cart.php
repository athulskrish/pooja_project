<?php
/**
 * Dedicated Shopping Cart Page
 * MCSP-232: E-Commerce Platform for Food Order
 */
$pageTitle = 'Shopping Cart - Zippy';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/models/AprioriEngine.php';

$aprioriRecs = AprioriEngine::getRecommendations([['id' => 1, 'name' => 'Biryani']]);
?>

  <main class="container-xl py-4" style="max-width: 980px;">
    
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="index.php" class="text-success text-decoration-none">Home</a></li>
        <li class="breadcrumb-item"><a href="restaurant.php" class="text-success text-decoration-none">Meghana Foods</a></li>
        <li class="breadcrumb-item active text-muted fw-bold" aria-current="page">Shopping Cart</li>
      </ol>
    </nav>

    <div class="row g-4" id="cart-main-container">
      
      <!-- LEFT COLUMN: Cart Items & Cooking Notes -->
      <div class="col-lg-7 col-xl-8">
        
        <!-- Cart Items Box -->
        <div class="p-4 bg-white rounded-4 border shadow-xs mb-4">
          <div class="d-flex justify-content-between align-items-center pb-3 border-bottom mb-3">
            <div>
              <h4 class="fw-bold text-pine mb-0"><i class="bi bi-cart-check text-success me-2"></i> Your Selected Dishes</h4>
              <span class="small text-muted">From <strong>Meghana Foods</strong> (Indiranagar Outlet)</span>
            </div>
            <button class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="clearCart()">
              <i class="bi bi-trash me-1"></i> Clear Cart
            </button>
          </div>

          <!-- Dynamic List of Items rendered via app.js -->
          <div id="cart-page-items-list" class="d-flex flex-column gap-3">
            <!-- Injected by JavaScript -->
          </div>

          <!-- Empty Cart Message (shown when 0 items) -->
          <div id="cart-empty-state" class="text-center py-5" style="display: none;">
            <i class="bi bi-cart-x text-muted" style="font-size: 3.5rem;"></i>
            <h5 class="fw-bold text-pine mt-3">Your cart is currently empty!</h5>
            <p class="text-muted small">Good food is always just a few clicks away. Explore our chef specials.</p>
            <a href="restaurant.php" class="btn btn-success rounded-pill px-4 fw-bold mt-2" style="background-color: var(--lush-forest);">
              Browse Restaurant Menu
            </a>
          </div>
        </div>

        <!-- Cooking Instructions -->
        <div class="p-4 bg-white rounded-4 border shadow-xs mb-4">
          <h6 class="fw-bold text-pine mb-2"><i class="fa-solid fa-kitchen-set text-success me-2"></i> Special Cooking or Delivery Notes</h6>
          <p class="small text-muted mb-2">Tell the kitchen chef your spice preferences, allergy notes, or contactless instructions.</p>
          <textarea id="cartOrderNotes" class="form-control" rows="2" placeholder="e.g. Less spicy, extra salan/raita, please do not ring the bell..."></textarea>
        </div>

        <!-- Algorithmic Cross-Sell Recommendation Strip (Apriori Engine) -->
        <div class="p-4 rounded-4 text-white shadow-xs mb-4" style="background: linear-gradient(135deg, #1C3322 0%, #2E6F40 100%);">
          <span class="badge bg-mint text-pine fw-bold mb-2">
            <i class="fa-solid fa-network-wired me-1"></i> Apriori Cross-Sell Recommendations
          </span>
          <h5 class="fw-bold mb-1">Frequently Paired with Your Order</h5>
          <p class="small text-white-50 mb-3">Add these complementary side dishes popular with Biryani & Curries:</p>

          <div class="row g-3">
            <?php foreach ($aprioriRecs as $rec): ?>
              <div class="col-md-4">
                <div class="p-3 bg-white bg-opacity-10 rounded-3 border border-white border-opacity-15 h-100 d-flex flex-column justify-content-between">
                  <div>
                    <div class="fw-bold small text-white"><?= sanitize($rec['name']) ?></div>
                    <div class="small text-mint fw-bold"><?= formatPrice($rec['price']) ?></div>
                  </div>
                  <button class="btn btn-sm btn-light rounded-pill fw-bold text-success px-3 mt-2 w-100" 
                          onclick="addToCart(<?= $rec['item_id'] ?>, '<?= addslashes($rec['name']) ?>', <?= $rec['price'] ?>, <?= !empty($rec['is_veg']) ? 'true' : 'false' ?>)">
                    + Add to Cart
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div>

      <!-- RIGHT COLUMN: Bill Summary & Checkout CTA -->
      <div class="col-lg-5 col-xl-4">
        <div class="p-4 bg-white rounded-4 border shadow-sm sticky-top" style="top: 80px;">
          
          <h5 class="fw-bold text-pine pb-2 border-bottom mb-3">Bill Details</h5>

          <!-- Coupon Input -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-pine">Have a Promo Coupon?</label>
            <div class="input-group mb-2">
              <input type="text" id="couponCodeInput" class="form-control font-monospace" placeholder="CODE (e.g. LUSHFOREST)">
              <button class="btn btn-success fw-bold px-3" type="button" onclick="applyCoupon()" style="background-color: var(--lush-forest);">
                APPLY
              </button>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <span class="badge bg-light text-success border cursor-pointer font-monospace" onclick="document.getElementById('couponCodeInput').value='LUSHFOREST'; applyCoupon();">LUSHFOREST</span>
              <span class="badge bg-light text-success border cursor-pointer font-monospace" onclick="document.getElementById('couponCodeInput').value='ZIPPY50'; applyCoupon();">ZIPPY50</span>
              <span class="badge bg-light text-success border cursor-pointer font-monospace" onclick="document.getElementById('couponCodeInput').value='STEALDEAL'; applyCoupon();">STEALDEAL</span>
            </div>
            <div id="coupon-feedback-msg" class="small mt-1 text-success fw-bold" style="display: none;"></div>
          </div>

          <!-- Bill Items Breakdown -->
          <div class="d-flex flex-column gap-2 border-top pt-3 text-muted small">
            <div class="d-flex justify-content-between">
              <span>Item Total</span>
              <span class="fw-bold text-pine" id="bill-item-total">₹0</span>
            </div>
            <div class="d-flex justify-content-between">
              <span>Delivery Fee (2.4 km)</span>
              <span class="fw-bold text-pine" id="bill-delivery-fee">₹35</span>
            </div>
            <div class="d-flex justify-content-between">
              <span>Platform Fee</span>
              <span class="fw-bold text-pine" id="bill-platform-fee">₹5</span>
            </div>
            <div class="d-flex justify-content-between">
              <span>GST & Restaurant Taxes (5%)</span>
              <span class="fw-bold text-pine" id="bill-taxes">₹0</span>
            </div>

            <!-- Discount Row -->
            <div class="d-flex justify-content-between text-success fw-bold" id="bill-discount-row" style="display: none;">
              <span>Coupon Savings</span>
              <span id="bill-discount-amount">-₹0</span>
            </div>
          </div>

          <!-- Final Payable Total -->
          <div class="d-flex justify-content-between align-items-center pt-3 mt-3 border-top border-2">
            <span class="fw-bold text-pine fs-6">TO PAY</span>
            <span class="fw-bold text-success fs-5" id="bill-final-total">₹0</span>
          </div>

          <!-- Proceed to Checkout Button -->
          <a href="checkout.php" class="btn btn-success w-100 rounded-pill fw-bold py-3 mt-4 shadow-sm" style="background-color: var(--lush-forest);">
            Proceed to Checkout <i class="bi bi-arrow-right ms-2"></i>
          </a>

          <div class="text-center small text-muted mt-3" style="font-size: 0.72rem;">
            <i class="bi bi-shield-check text-success me-1"></i> Concurrency-safe ACID checkout & FSSAI certified packing.
          </div>

        </div>
      </div>

    </div>
  </main>

  <script>
    // Specialized Cart Page Renderer
    document.addEventListener('DOMContentLoaded', () => {
      renderCartPage();
    });

    function renderCartPage() {
      const container = document.getElementById('cart-page-items-list');
      const emptyState = document.getElementById('cart-empty-state');
      if (!container) return;

      if (!cart || cart.length === 0) {
        container.style.display = 'none';
        emptyState.style.display = 'block';
        return;
      }

      emptyState.style.display = 'none';
      container.style.display = 'flex';
      container.innerHTML = '';

      cart.forEach((item, index) => {
        const itemRow = document.createElement('div');
        itemRow.className = 'p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center gap-3';
        itemRow.innerHTML = `
          <div class="d-flex align-items-center gap-3 flex-grow-1">
            <span class="food-type-icon ${item.isVeg ? 'veg' : 'non-veg'}"></span>
            <div>
              <h6 class="fw-bold text-pine mb-0">${item.name}</h6>
              <span class="small text-muted">₹${item.price} each</span>
            </div>
          </div>
          <div class="d-flex align-items-center gap-3">
            <div class="cart-qty-pill d-flex align-items-center gap-2 bg-white px-2 py-1 rounded-pill border">
              <button class="btn btn-sm p-0 text-success fw-bold" onclick="decrementCartItem(${item.id})">&minus;</button>
              <span class="fw-bold small px-2">${item.qty}</span>
              <button class="btn btn-sm p-0 text-success fw-bold" onclick="addToCart(${item.id}, '${item.name}', ${item.price}, ${item.isVeg})">&plus;</button>
            </div>
            <div class="fw-bold text-pine" style="min-width: 70px; text-align: right;">₹${(item.price * item.qty).toFixed(2)}</div>
          </div>
        `;
        container.appendChild(itemRow);
      });

      // Recalculate bill
      updateBillSummary();
    }

    function clearCart() {
      cart = [];
      saveCart();
      renderCartPage();
      showToast('Shopping cart cleared.');
    }
  </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
