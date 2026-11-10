<?php
declare(strict_types=1);
/**
 * Dedicated Shopping Cart Page
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Shopping Cart & Order Summary';
$activePage = 'cart';

require_once __DIR__ . '/includes/header.php';

$coupons = getAvailableCoupons();
$previousMeal = null;
if ($currentUser && !empty($currentUser['id'])) {
    $previousMeal = getCustomerPreviousMeal((int)$currentUser['id']);
}
?>

<main class="py-4">
  <div class="container-xl">
    
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h2 class="fw-bold mb-0 font-outfit text-dark"><i class="bi bi-bag-check-fill text-success me-2"></i>Review Your Cart</h2>
        <p class="text-muted small mb-0">Delivery to Indiranagar, Bangalore &bull; Express (25-30m) or 📅 Schedule in Advance</p>
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

      <?php if (!empty($previousMeal) && !empty($previousMeal['items'])): 
        $cartPmOrder = $previousMeal['order'];
        $cartPmPayloadJson = htmlspecialchars($previousMeal['reorder_json'], ENT_QUOTES, 'UTF-8');
      ?>
        <div class="mt-4 p-3 rounded-4 mx-auto text-start border shadow-xs" style="max-width: 520px; background: #F2FAF5; border-color: #C8EBD4 !important;">
          <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
            <span class="badge rounded-pill px-2 py-1 fw-bold" style="background: #243E2C; color: #CEFFDC; font-size: 0.7rem;">
              <i class="fa-solid fa-bolt text-mint me-1"></i>1-TAP REORDER
            </span>
            <span class="small text-muted">Previous Meal (#ZIP-<?= (int)$cartPmOrder['order_id'] ?>)</span>
          </div>
          <div class="small fw-semibold text-dark mb-2">
            <?= implode(' &bull; ', array_map(fn(array $i): string => e($i['item_name']) . ' &times;' . (int)$i['quantity'], $previousMeal['items'])) ?>
          </div>
          <div class="d-flex align-items-center justify-content-between pt-2 border-top">
            <div>
              <span class="small text-muted d-block" style="font-size: 0.72rem;">Previous Bill</span>
              <span class="fw-bold text-forest fs-6"><?= formatInr($cartPmOrder['total_amount']) ?></span>
            </div>
            <button type="button" class="btn btn-success btn-sm rounded-pill px-3 py-2 fw-bold shadow-sm d-flex align-items-center gap-1" style="background:#2E6F40; border-color:#2E6F40;" onclick="oneTapReorderMeal(<?= $cartPmPayloadJson ?>, <?= (int)$cartPmOrder['order_id'] ?>, false, this)">
              <i class="fa-solid fa-rotate-right me-1"></i>1-Tap Reorder Previous Meal
            </button>
          </div>
        </div>
      <?php endif; ?>
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

        <!-- =======================================================================
             ALGORITHM 1: APRIORI CROSS-SELLING (FREQUENTLY ORDERED TOGETHER)
             ======================================================================= -->
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4" id="cartAprioriCard">
          <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
            <div>
              <span class="badge rounded-pill px-2 py-1 fw-bold" style="background:#CEFFDC; color:#243E2C; font-size:0.7rem;">
                <i class="fa-solid fa-brain me-1"></i> APRIORI ALGORITHM
              </span>
              <h6 class="fw-bold text-dark mb-0 mt-1">Frequently Ordered Together (Complete Your Meal)</h6>
              <div class="small text-muted" id="cartAprioriSubtitle">Complementary dishes recommended by Market Basket Analysis</div>
            </div>
            <span class="badge bg-light text-success border px-2 py-1 small">
              <i class="bi bi-stars me-1"></i> Smart Pairings
            </span>
          </div>

          <div class="row g-3" id="cartAprioriContainer">
            <!-- Dynamically populated via loadCartAprioriRecommendations() -->
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

          <!-- Quick Coupon Pills (DB-Backed Dynamic Coupons) -->
          <div class="d-flex flex-column gap-2 mt-2">
            <?php if (!empty($coupons)): ?>
              <?php foreach ($coupons as $cp): ?>
                <?php
                  $cpCode = (string)($cp['code'] ?? '');
                  $cpBadge = ($cp['discount_type'] ?? '') === 'percentage'
                    ? ((int)$cp['discount_value'] . '% OFF')
                    : ('FLAT ₹' . (int)$cp['discount_value']);
                  $cpDesc = (string)($cp['description'] ?? '');
                ?>
                <div class="p-2 rounded-3 bg-light border d-flex justify-content-between align-items-center gap-2">
                  <div>
                    <span class="badge bg-mint text-dark fw-bold"><?= e($cpCode) ?></span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size:0.65rem;"><?= e($cpBadge) ?></span>
                    <span class="small text-muted d-block mt-1"><?= e($cpDesc) ?></span>
                  </div>
                  <button class="btn btn-sm btn-link text-success p-0 fw-bold text-decoration-none flex-shrink-0" type="button" onclick="document.getElementById('cartCouponInput').value=<?= e(jsJson($cpCode)) ?>; document.getElementById('applyCouponBtn').click();">APPLY</button>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="small text-muted">No active promo codes at the moment.</div>
            <?php endif; ?>
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
    const isVeg = (item.isVeg === true || item.isVeg === 1 || item.isVeg === '1' || item.isVeg === 'true');
    const safeName = (item.name || '').replace(/'/g, "\\'");
    const itemImg = item.imageUrl || (window.DISH_IMAGES_MAP && window.DISH_IMAGES_MAP[item.id]) || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300';
    const itemTotal = (item.price * item.qty).toFixed(2);

    const itemRow = document.createElement('div');
    itemRow.className = 'd-flex justify-content-between align-items-center py-3 border-bottom cart-dish-item-row';
    itemRow.innerHTML = `
      <div class="d-flex align-items-center gap-3">
        <!-- Dish Thumbnail with Veg/Non-Veg Indicator Dot -->
        <div class="position-relative flex-shrink-0" style="width: 64px; height: 64px;">
          <img src="${itemImg}" 
               alt="${item.name}" 
               class="rounded-3 w-100 h-100 object-fit-cover shadow-sm border"
               onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300'">
          <span class="position-absolute top-0 start-0 m-1 food-badge-dot ${isVeg ? 'veg' : 'nonveg'}" 
                style="width: 10px; height: 10px; border-radius: 50%; background: ${isVeg ? '#229947' : '#D32F2F'}; border: 1.5px solid #fff; display: inline-block;"
                title="${isVeg ? 'Pure Veg' : 'Non-Veg'}"></span>
        </div>

        <div>
          <h6 class="fw-bold mb-1 text-dark" style="font-size: 0.98rem;">${item.name}</h6>
          ${item.customizationText ? `<div class="small text-muted fst-italic mb-1" style="font-size:0.75rem;"><i class="bi bi-sliders text-success me-1"></i>${item.customizationText}</div>` : ''}
          <span class="small text-muted fw-semibold">₹${Number(item.price).toFixed(2)} each</span>
        </div>
      </div>

      <div class="d-flex align-items-center gap-3">
        <!-- Material 3 Stepper Quantity Controls -->
        <div class="m3-stepper-control rounded-pill shadow-sm" role="group">
          <button type="button" class="m3-stepper-btn" onclick="decrementCartItem('${item.cartItemId || item.id}'); renderDedicatedCart();" aria-label="Decrease quantity">
            <i class="bi bi-dash"></i>
          </button>
          <span class="m3-stepper-qty">${item.qty}</span>
          <button type="button" class="m3-stepper-btn" onclick="incrementCartItem('${item.cartItemId || item.id}'); renderDedicatedCart();" aria-label="Increase quantity">
            <i class="bi bi-plus"></i>
          </button>
        </div>

        <span class="fw-bold text-dark fs-6" style="min-width: 80px; text-align: right;">₹${itemTotal}</span>
      </div>
    `;
    container.appendChild(itemRow);
  });

  // Calculate bill totals using centralized dynamic calculator
  const totals = calculateCartTotals();
  const discount = totals.discount;
  const deliveryFee = totals.deliveryFee;
  const platformFee = totals.platformFee;
  const gst = totals.taxes;
  const grandTotal = totals.finalTotal;

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

  // Load Apriori Market Basket Recommendations
  loadCartAprioriRecommendations();
}

function loadCartAprioriRecommendations() {
  const container = document.getElementById('cartAprioriContainer');
  if (!container) return;
  const cartIds = (cart && cart.length > 0) ? cart.map(i => i.id).join(',') : '';
  fetch('<?= CUSTOMER_URL ?>/ajax/apriori-api.php?item_ids=' + encodeURIComponent(cartIds) + '&limit=4')
    .then(r => r.json())
    .then(data => {
      if (data && data.recommendations && data.recommendations.length > 0) {
        const subtitle = document.getElementById('cartAprioriSubtitle');
        if (subtitle && cart && cart.length > 0) {
          subtitle.textContent = `Apriori basket analysis calculated from ${cart.length} item(s) in your cart`;
        }
        container.innerHTML = data.recommendations.map(rec => `
          <div class="col-sm-6 col-md-3">
            <div class="card h-100 border rounded-3 p-2 bg-light bg-opacity-50 text-center d-flex flex-column justify-content-between">
              <div class="position-relative mb-2">
                <img src="${rec.image_url || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300'}" 
                     alt="${rec.item_name}" 
                     class="rounded-3 w-100 object-fit-cover" 
                     style="height: 90px;">
                <span class="position-absolute top-0 start-0 m-1 badge bg-white text-dark shadow-sm" style="font-size:0.6rem;">
                  ${rec.is_veg == 1 ? '🟢 VEG' : '🔴 NON-VEG'}
                </span>
              </div>
              <h6 class="fw-bold text-dark small mb-1 text-truncate" title="${rec.item_name}">${rec.item_name}</h6>
              <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                <span class="dish-rating-badge" style="font-size:0.65rem; padding: 2px 6px;">
                  <i class="bi bi-star-fill rating-star"></i>
                  <span class="rating-val">${parseFloat(rec.rating || 4.8).toFixed(1)}</span>
                </span>
              </div>
              <div class="text-muted small mb-2">₹${parseFloat(rec.price).toFixed(2)}</div>
              <button type="button" 
                      class="btn btn-sm btn-outline-success rounded-pill fw-bold w-100 py-1"
                      onclick="addToCart(${rec.item_id}, '${rec.item_name.replace(/'/g, "\\'")}', ${rec.price}, ${rec.is_veg == 1}, 'Meghana Foods', '${rec.image_url || ''}'); renderDedicatedCart();">
                <i class="bi bi-plus-lg me-1"></i> Add
              </button>
            </div>
          </div>
        `).join('');
      } else {
        const card = document.getElementById('cartAprioriCard');
        if (card) card.style.display = 'none';
      }
    })
    .catch(err => console.log('Cart Apriori error:', err));
}

document.addEventListener('DOMContentLoaded', () => {
  renderDedicatedCart();

  const applyBtn = document.getElementById('applyCouponBtn');
  if (applyBtn) {
    applyBtn.addEventListener('click', async () => {
      const code = document.getElementById('cartCouponInput').value.trim().toUpperCase();
      const msgDiv = document.getElementById('couponMessage');
      if (!code) {
        activeCoupon = null;
        window.ZIPPY_VALIDATED_COUPON = null;
        localStorage.removeItem('pooja_active_coupon');
        msgDiv.className = 'small text-muted fw-semibold';
        msgDiv.textContent = 'Coupon cleared.';
        saveCart();
        return;
      }

      const subtotal = cart.reduce((sum, item) => sum + (Number(item.price) * Number(item.qty)), 0);
      try {
        const res = await fetch(`${window.CUSTOMER_URL}/ajax/validate-coupon.php?code=${encodeURIComponent(code)}&subtotal=${encodeURIComponent(subtotal)}`);
        const data = await res.json();
        if (data && data.valid) {
          activeCoupon = code;
          window.ZIPPY_VALIDATED_COUPON = data.coupon || null;
          localStorage.setItem('pooja_active_coupon', code);
          msgDiv.className = 'small text-success fw-bold';
          msgDiv.textContent = `Coupon ${code} applied! Saving ₹${Number(data.discount || 0).toFixed(2)}.`;
          saveCart();
          return;
        }
        msgDiv.className = 'small text-danger fw-bold';
        msgDiv.textContent = (data && data.error) ? data.error : 'Invalid coupon code.';
      } catch (err) {
        const fallback = evaluateDynamicCoupon(code, subtotal);
        if (fallback > 0) {
          activeCoupon = code;
          localStorage.setItem('pooja_active_coupon', code);
          msgDiv.className = 'small text-success fw-bold';
          msgDiv.textContent = `Coupon ${code} applied!`;
          saveCart();
        } else {
          msgDiv.className = 'small text-danger fw-bold';
          msgDiv.textContent = 'Coupon validation failed or minimum order not met.';
        }
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
