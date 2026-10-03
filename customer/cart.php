<?php
/**
 * Dedicated Shopping Cart Page
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Shopping Cart & Order Summary';
$activePage = 'cart';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/AprioriEngine.php';

$coupons = get_available_coupons();
$recommendations = AprioriEngine::getRecommendations([], 4);
?>

<main class="py-4">
  <div class="container-xl">
    
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h2 class="fw-bold mb-0 font-outfit text-dark"><i class="bi bi-bag-check-fill text-success me-2"></i>Review Your Cart</h2>
        <p class="text-muted small mb-0">Delivery to Indiranagar, Bangalore (25-30 mins)</p>
      </div>
      <a href="<?= CUSTOMER_URL ?>/index.php#dishes" class="btn btn-outline-success btn-sm rounded-pill px-3">
        <i class="bi bi-plus-circle me-1"></i> Add More Dishes
      </a>
    </div>

    <!-- Empty Cart Notice (Shown by JS if cart is empty) -->
    <div id="cartEmptyState" class="card border-0 rounded-4 shadow-sm p-5 text-center bg-white" style="display: none;">
      <i class="bi bi-cart-x text-muted opacity-50" style="font-size: 4rem;"></i>
      <h4 class="fw-bold mt-3 mb-2 text-dark">Your cart is empty</h4>
      <p class="text-muted small mb-4" style="max-width: 450px; margin: 0 auto;">
        Looks like you haven't added anything to your cart yet. Explore our top chef-crafted biryanis, curries, rolls and desserts!
      </p>
      <div>
        <a href="<?= CUSTOMER_URL ?>/index.php#dishes" class="btn btn-success rounded-pill px-4 py-2 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
          Explore Menu Now
        </a>
      </div>
    </div>

    <!-- Active Cart Layout -->
    <div class="row g-4" id="cartActiveState">
      
      <!-- Left Column: Cart Items & Cooking Notes -->
      <div class="col-lg-8">
        
        <!-- Cart Items Container (Populated via JS) -->
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
          <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
            <h5 class="fw-bold mb-0 text-dark">Ordered Items</h5>
            <button type="button" class="btn btn-link text-danger text-decoration-none small p-0" onclick="cart = []; saveCart(); showToast('Cart cleared.'); location.reload();">
              <i class="bi bi-trash3 me-1"></i> Clear Cart
            </button>
          </div>

          <div id="dedicatedCartItemsList" class="d-flex flex-column gap-3">
            <!-- Dynamically populated by JS renderDedicatedCart() -->
          </div>
        </div>

        <!-- Cooking Instructions & Delivery Notes -->
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
          <h6 class="fw-bold text-dark mb-3"><i class="bi bi-pencil-square text-success me-2"></i>Cooking & Delivery Preferences</h6>
          <div class="mb-3">
            <input type="text" class="form-control rounded-3" id="cartOrderNotes" placeholder="E.g. Less spicy, keep mirchi ka salan separate, don't ring bell">
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="noCutleryCheck" checked>
            <label class="form-check-label small text-muted" for="noCutleryCheck">
              <strong>Opt-out of disposable plastic cutlery</strong> (Help us keep our forests lush & green 🌿)
            </label>
          </div>
        </div>

        <!-- Apriori Combos & Add-ons -->
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge" style="background:#2E6F40; color:#CEFFDC;">APRIORI RECOMMENDATIONS</span>
            <h6 class="fw-bold mb-0 text-dark">Complete Your Meal with Popular Add-ons</h6>
          </div>
          <div class="row g-3">
            <?php foreach ($recommendations as $rec): ?>
              <div class="col-md-6">
                <div class="p-3 rounded-3 border d-flex align-items-center justify-content-between gap-2">
                  <div class="d-flex align-items-center gap-2 overflow-hidden">
                    <img src="<?= e($rec['image_url']) ?>" alt="<?= e($rec['item_name']) ?>" class="rounded-2 object-fit-cover flex-shrink-0" style="width: 50px; height: 50px;">
                    <div class="overflow-hidden">
                      <div class="fw-bold small text-truncate text-dark" title="<?= e($rec['item_name']) ?>"><?= e($rec['item_name']) ?></div>
                      <div class="text-forest small fw-bold"><?= format_inr($rec['price']) ?></div>
                    </div>
                  </div>
                  <button 
                    type="button" 
                    class="btn btn-outline-success btn-sm rounded-pill px-3 py-1 fw-bold flex-shrink-0"
                    onclick="addToCart(<?= (int)$rec['item_id'] ?>, '<?= addslashes(e($rec['item_name'])) ?>', <?= (float)$rec['price'] ?>, <?= $rec['is_veg'] ? 1 : 0 ?>, 'Meghana Foods'); renderDedicatedCart();"
                  >
                    + ADD
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div>

      <!-- Right Column: Coupons & Bill Breakdown -->
      <div class="col-lg-4">
        
        <!-- Coupons Card -->
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
          <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-percent text-success me-2"></i>Apply Coupon Code</h6>
          
          <div class="input-group mb-3">
            <input type="text" class="form-control text-uppercase font-monospace fw-bold" id="cartCouponInput" placeholder="ENTER CODE" value="<?= e($_SESSION['applied_coupon'] ?? '') ?>">
            <button class="btn btn-success fw-bold px-3" type="button" id="applyCouponBtn" style="background:#2E6F40; border-color:#2E6F40;">
              APPLY
            </button>
          </div>

          <div id="couponMessage" class="small mb-2"></div>

          <!-- Quick Coupon Pills -->
          <div class="d-flex flex-column gap-2 mt-2">
            <div class="p-2 rounded-3 bg-light border d-flex justify-content-between align-items-center">
              <div>
                <span class="badge bg-mint text-dark fw-bold">LUSHFOREST</span>
                <span class="small text-muted d-block mt-1">20% off above ₹299 (max ₹100)</span>
              </div>
              <button class="btn btn-sm btn-link text-success p-0 fw-bold text-decoration-none" onclick="document.getElementById('cartCouponInput').value='LUSHFOREST'; document.getElementById('applyCouponBtn').click();">APPLY</button>
            </div>

            <div class="p-2 rounded-3 bg-light border d-flex justify-content-between align-items-center">
              <div>
                <span class="badge bg-warning text-dark fw-bold">ZIPPY50</span>
                <span class="small text-muted d-block mt-1">50% off above ₹399 (max ₹120)</span>
              </div>
              <button class="btn btn-sm btn-link text-success p-0 fw-bold text-decoration-none" onclick="document.getElementById('cartCouponInput').value='ZIPPY50'; document.getElementById('applyCouponBtn').click();">APPLY</button>
            </div>
          </div>
        </div>

        <!-- Bill Details Card -->
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white">
          <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Bill Details</h6>
          
          <div class="d-flex justify-content-between small text-muted mb-2">
            <span>Item Total</span>
            <span class="fw-bold text-dark" id="billSubtotal">₹0.00</span>
          </div>

          <div class="d-flex justify-content-between small text-muted mb-2">
            <span>Delivery Partner Fee</span>
            <span class="fw-semibold text-success" id="billDelivery">₹0.00</span>
          </div>

          <div class="d-flex justify-content-between small text-muted mb-2">
            <span>Platform Service Fee</span>
            <span class="fw-semibold text-dark">₹<?= number_format(PLATFORM_FEE, 2) ?></span>
          </div>

          <div class="d-flex justify-content-between small text-muted mb-2">
            <span>GST & Restaurant Taxes (5%)</span>
            <span class="fw-semibold text-dark" id="billGst">₹0.00</span>
          </div>

          <div class="d-flex justify-content-between small text-success mb-2" id="billDiscountRow" style="display:none !important;">
            <span>Coupon Discount</span>
            <span class="fw-bold" id="billDiscount">-₹0.00</span>
          </div>

          <div class="border-top pt-3 mt-2 mb-4 d-flex justify-content-between align-items-center">
            <div>
              <span class="fw-bold fs-6 text-dark d-block">TO PAY</span>
              <span class="small text-muted">Inclusive of all taxes</span>
            </div>
            <span class="fw-bold fs-4 text-forest" id="billTotal">₹0.00</span>
          </div>

          <a href="<?= CUSTOMER_URL ?>/checkout.php" class="btn btn-success w-100 rounded-pill fw-bold py-3 shadow" style="background:#2E6F40; border-color:#2E6F40; font-size:1.05rem;">
            Proceed to Checkout <i class="bi bi-arrow-right ms-2"></i>
          </a>
        </div>

      </div>

    </div>

  </div>
</main>

<script>
// Render dedicated cart page items & sync calculations
function renderDedicatedCart() {
  const container = document.getElementById('dedicatedCartItemsList');
  const emptyState = document.getElementById('cartEmptyState');
  const activeState = document.getElementById('cartActiveState');
  
  if (!container) return;

  if (!cart || cart.length === 0) {
    if (emptyState) emptyState.style.display = 'block';
    if (activeState) activeState.style.display = 'none';
    return;
  }

  if (emptyState) emptyState.style.display = 'none';
  if (activeState) activeState.style.display = 'flex';

  container.innerHTML = '';
  let subtotal = 0;

  cart.forEach((item, index) => {
    subtotal += item.price * item.qty;
    const itemRow = document.createElement('div');
    itemRow.className = 'd-flex justify-content-between align-items-center py-2 border-bottom';
    itemRow.innerHTML = `
      <div class="d-flex align-items-center gap-3">
        <span class="food-badge-dot ${item.isVeg ? 'veg' : 'nonveg'}" style="width:12px; height:12px; border-radius:50%; background:${item.isVeg ? '#229947' : '#D32F2F'}; display:inline-block;"></span>
        <div>
          <h6 class="fw-bold mb-0 text-dark">${item.name}</h6>
          <span class="small text-muted">₹${item.price} each</span>
        </div>
      </div>

      <div class="d-flex align-items-center gap-3">
        <!-- Qty Controls -->
        <div class="btn-group btn-group-sm rounded-pill border overflow-hidden" role="group">
          <button type="button" class="btn btn-light px-2" onclick="decrementCartItem(${item.id}); renderDedicatedCart();">-</button>
          <span class="btn btn-white px-3 fw-bold disabled text-dark">${item.qty}</span>
          <button type="button" class="btn btn-light px-2" onclick="addToCart(${item.id}, '${item.name.replace(/'/g, "\\'")}', ${item.price}, ${item.isVeg ? 1 : 0}); renderDedicatedCart();">+</button>
        </div>

        <span class="fw-bold text-dark" style="min-width: 70px; text-align: right;">₹${(item.price * item.qty).toFixed(2)}</span>
      </div>
    `;
    container.appendChild(itemRow);
  });

  // Calculate bill totals
  const coupon = activeCoupon;
  let discount = 0;
  if (coupon === 'LUSHFOREST' && subtotal >= 299) {
    discount = Math.min(subtotal * 0.20, 100);
  } else if (coupon === 'ZIPPY50' && subtotal >= 399) {
    discount = Math.min(subtotal * 0.50, 120);
  } else if (coupon === 'WELCOME' && subtotal >= 199) {
    discount = 50;
  }

  const deliveryFee = subtotal >= 499 ? 0 : 40;
  const platformFee = 5;
  const taxable = Math.max(0, subtotal - discount);
  const gst = taxable * 0.05;
  const grandTotal = taxable + gst + deliveryFee + platformFee;

  document.getElementById('billSubtotal').textContent = `₹${subtotal.toFixed(2)}`;
  document.getElementById('billDelivery').textContent = deliveryFee === 0 ? 'FREE' : `₹${deliveryFee.toFixed(2)}`;
  document.getElementById('billGst').textContent = `₹${gst.toFixed(2)}`;
  document.getElementById('billTotal').textContent = `₹${grandTotal.toFixed(2)}`;

  const discountRow = document.getElementById('billDiscountRow');
  if (discount > 0) {
    discountRow.style.setProperty('display', 'flex', 'important');
    document.getElementById('billDiscount').textContent = `-₹${discount.toFixed(2)}`;
  } else {
    discountRow.style.setProperty('display', 'none', 'important');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  renderDedicatedCart();

  const applyBtn = document.getElementById('applyCouponBtn');
  if (applyBtn) {
    applyBtn.addEventListener('click', () => {
      const code = document.getElementById('cartCouponInput').value.trim().toUpperCase();
      const msgDiv = document.getElementById('couponMessage');
      if (code === 'LUSHFOREST' || code === 'ZIPPY50' || code === 'WELCOME') {
        activeCoupon = code;
        localStorage.setItem('pooja_active_coupon', code);
        msgDiv.className = 'small text-success fw-bold';
        msgDiv.textContent = `Coupon ${code} applied successfully!`;
        renderDedicatedCart();
      } else {
        msgDiv.className = 'small text-danger fw-bold';
        msgDiv.textContent = 'Invalid coupon code. Try LUSHFOREST or ZIPPY50.';
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
