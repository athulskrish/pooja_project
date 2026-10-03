/**
 * POOJA PROJECT - ZIPPY JAVASCRIPT ENGINE
 * Handles:
 *  - Persistent Cart State (localStorage) across all pages
 *  - Dynamic Cart Calculations (Items total, Taxes, Platform fee, Discounts)
 *  - Category & Veg/Non-Veg Filtering
 *  - Real-time Search
 *  - Offcanvas Cart Drawer Rendering
 *  - Interactive Toasts & Feedback
 */

// Initialize Cart from localStorage
let cart = JSON.parse(localStorage.getItem('pooja_zippy_cart')) || [];
let activeCoupon = localStorage.getItem('pooja_active_coupon') || null;

// DOM Ready
document.addEventListener('DOMContentLoaded', () => {
  updateCartBadge();
  renderCartDrawer();
  setupFilterPills();
  setupSearchInput();
  setupVegOnlyFilter();
  renderDishQtyControls();
  initMobileBottomNav();
  initAdminMobileDrawer();
  
  // Refresh drawer and hide mobile bar whenever offcanvas is shown
  const drawer = document.getElementById('cartDrawer');
  if (drawer) {
    drawer.addEventListener('show.bs.offcanvas', () => {
      renderCartDrawer();
      const bar = document.getElementById('mobileCartBar');
      if (bar) {
        bar.classList.add('drawer-open');
        bar.style.display = 'none';
      }
      const bNav = document.querySelector('.mobile-bottom-nav');
      if (bNav) bNav.classList.add('drawer-open');
    });

    drawer.addEventListener('hidden.bs.offcanvas', () => {
      const bar = document.getElementById('mobileCartBar');
      if (bar) bar.classList.remove('drawer-open');
      const bNav = document.querySelector('.mobile-bottom-nav');
      if (bNav) bNav.classList.remove('drawer-open');
      updateCartBadge();
    });
  }

  // Handle modals (e.g. location modal) hiding the floating bar
  document.addEventListener('show.bs.modal', () => {
    const bar = document.getElementById('mobileCartBar');
    if (bar) bar.style.display = 'none';
  });
  document.addEventListener('hidden.bs.modal', () => {
    updateCartBadge();
  });

  // Responsive resize handler
  window.addEventListener('resize', updateCartBadge);

  // Checkout page specific initializer
  if (document.getElementById('checkout-items-container')) {
    renderCheckoutPage();
  }

  // Order status page specific initializer (only if not handled by dedicated tracker)
  if (document.getElementById('live-eta-countdown') && !window.orderTrackingActive) {
    initOrderStatusSimulation();
  }
});

/**
 * Dismiss Floating Mobile Cart Bar
 */
function dismissMobileCartBar() {
  const bar = document.getElementById('mobileCartBar');
  if (bar) bar.style.display = 'none';
  sessionStorage.setItem('dismiss_cart_bar', '1');
}

/**
 * Save Cart to LocalStorage and trigger UI updates
 */
function saveCart() {
  localStorage.setItem('pooja_zippy_cart', JSON.stringify(cart));
  updateCartBadge();
  renderCartDrawer();
  renderDishQtyControls();
  if (typeof renderDedicatedCart === 'function') {
    renderDedicatedCart();
  }
  if (document.getElementById('checkout-items-container')) {
    renderCheckoutPage();
  }
}

/**
 * Update Cart Badge Count on Header and Mobile Bar
 */
function updateCartBadge() {
  const totalCount = cart.reduce((sum, item) => sum + item.qty, 0);
  const badges = document.querySelectorAll('.cart-count-badge, .mobile-nav-badge');
  badges.forEach(badge => {
    badge.textContent = totalCount;
    if (badge.classList.contains('mobile-nav-badge')) {
      badge.style.display = totalCount > 0 ? 'inline-block' : 'none';
    }
  });

  const mobileCartBar = document.getElementById('mobileCartBar');
  if (mobileCartBar) {
    const isDesktop = window.innerWidth >= 992;
    const isCartOrCheckout = window.location.pathname.includes('cart.php') || window.location.pathname.includes('checkout.php');
    const drawer = document.getElementById('cartDrawer');
    const isDrawerOpen = (drawer && (drawer.classList.contains('show') || drawer.classList.contains('showing'))) || mobileCartBar.classList.contains('drawer-open');
    const isDismissed = sessionStorage.getItem('dismiss_cart_bar') === '1';

    // Strictly visible ONLY on mobile screens (<992px), when cart has items, outside cart/checkout pages, and when drawer is closed
    if (totalCount > 0 && !isDesktop && !isCartOrCheckout && !isDrawerOpen && !isDismissed) {
      mobileCartBar.style.display = 'flex';
      const totals = calculateCartTotals();
      const countEl = document.getElementById('mobileCartItemsCount');
      if (countEl) countEl.textContent = `${totalCount} ITEM${totalCount > 1 ? 'S' : ''}`;
      const totalEl = document.getElementById('mobileCartTotal');
      if (totalEl) totalEl.textContent = `₹${totals.finalTotal.toFixed(2)}`;
    } else {
      mobileCartBar.style.display = 'none';
    }
  }
}

/**
 * Add or Increment Item in Cart
 */
function addToCart(id, name, price, isVeg, restaurant = 'Meghana Foods') {
  sessionStorage.removeItem('dismiss_cart_bar');
  const existingItem = cart.find(item => item.id == id);
  if (existingItem) {
    existingItem.qty += 1;
  } else {
    cart.push({
      id: Number(id) || id,
      name: name,
      price: Number(price),
      isVeg: Boolean(isVeg && isVeg !== '0' && isVeg !== 'false'),
      restaurant,
      qty: 1
    });
  }
  saveCart();
  showToast(`Added "${name}" to your cart!`);
}

/**
 * Decrement or Remove Item from Cart
 */
function decrementCartItem(id) {
  const itemIndex = cart.findIndex(item => item.id == id);
  if (itemIndex > -1) {
    if (cart[itemIndex].qty > 1) {
      cart[itemIndex].qty -= 1;
    } else {
      const removedName = cart[itemIndex].name;
      cart.splice(itemIndex, 1);
      showToast(`Removed "${removedName}" from cart`);
    }
    saveCart();
  }
}

/**
 * Remove Item Completely
 */
function removeCartItem(id) {
  cart = cart.filter(item => item.id != id);
  saveCart();
  showToast('Item removed from cart');
}

/**
 * Clear the entire Cart
 */
function clearCart() {
  cart = [];
  localStorage.removeItem('pooja_zippy_cart');
  localStorage.removeItem('pooja_active_coupon');
  activeCoupon = null;
  saveCart();
  showToast('Cart cleared');
}

/**
 * Calculate Cart Totals (Matches Backend Logic Exactly)
 */
function calculateCartTotals() {
  const itemTotal = cart.reduce((sum, item) => sum + (Number(item.price) * Number(item.qty)), 0);
  const deliveryFee = (itemTotal >= 499 || itemTotal === 0) ? 0 : 40;
  const platformFee = itemTotal > 0 ? 5 : 0;
  
  let discount = 0;
  if (activeCoupon === 'LUSHFOREST' && itemTotal >= 299) {
    discount = Math.min(Math.round(itemTotal * 0.20), 100);
  } else if (activeCoupon === 'ZIPPY50' && itemTotal >= 399) {
    discount = Math.min(Math.round(itemTotal * 0.50), 120);
  } else if (activeCoupon === 'WELCOME' && itemTotal >= 199) {
    discount = 50;
  }

  const taxable = Math.max(0, itemTotal - discount);
  const taxes = Math.round(taxable * 0.05 * 100) / 100; // 5% GST
  const finalTotal = Math.max(0, Math.round((taxable + taxes + deliveryFee + platformFee) * 100) / 100);

  return {
    itemTotal,
    deliveryFee,
    platformFee,
    taxes,
    discount,
    finalTotal
  };
}

/**
 * Render Cart Items inside Offcanvas Drawer
 */
function renderCartDrawer() {
  const container = document.getElementById('cartItemsList') || document.getElementById('cartDrawerItems');
  if (!container) return;

  const subtotalEl = document.getElementById('cartDrawerSubtotal');
  const gstEl = document.getElementById('cartDrawerGst');
  const deliveryEl = document.getElementById('cartDrawerDelivery');
  const totalEl = document.getElementById('cartDrawerTotal');
  const freeDeliveryGap = document.getElementById('freeDeliveryGap');
  const freeDeliveryBar = document.getElementById('freeDeliveryBar');
  const checkoutBtn = document.getElementById('cartDrawerCheckoutBtn');

  if (!cart || cart.length === 0) {
    container.innerHTML = `
      <div class="text-center py-5 text-muted">
        <i class="bi bi-cart-x fs-1 d-block mb-3 text-secondary opacity-50"></i>
        <h6 class="fw-bold text-dark">Your cart is empty</h6>
        <p class="small text-muted mb-4">Explore our delicious dishes and add your favorites!</p>
        <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-4" data-bs-dismiss="offcanvas">Explore Menu</button>
      </div>
    `;
    if (subtotalEl) subtotalEl.textContent = '₹0.00';
    if (gstEl) gstEl.textContent = '₹0.00';
    if (deliveryEl) deliveryEl.textContent = '₹0.00';
    if (totalEl) totalEl.textContent = '₹0.00';
    if (freeDeliveryGap) freeDeliveryGap.textContent = '₹499 away';
    if (freeDeliveryBar) freeDeliveryBar.style.width = '0%';
    if (checkoutBtn) {
      checkoutBtn.classList.add('disabled');
      checkoutBtn.style.pointerEvents = 'none';
      checkoutBtn.style.opacity = '0.6';
    }
    return;
  }

  if (checkoutBtn) {
    checkoutBtn.classList.remove('disabled');
    checkoutBtn.style.pointerEvents = 'auto';
    checkoutBtn.style.opacity = '1';
  }

  const totalQty = cart.reduce((sum, item) => sum + item.qty, 0);

  let html = `
    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
      <span class="fw-bold small text-muted text-uppercase">Items in Cart (${totalQty})</span>
      <button type="button" class="btn btn-sm text-danger p-0 fw-bold small text-decoration-none" onclick="clearCart()">
        <i class="bi bi-trash3 me-1"></i>Clear All
      </button>
    </div>
    <div class="d-flex flex-column gap-2 mb-3">
  `;

  cart.forEach(item => {
    const isVeg = (item.isVeg === true || item.isVeg === 1 || item.isVeg === '1' || item.isVeg === 'true');
    const safeName = (item.name || '').replace(/'/g, "\\'");
    const itemTotal = (Number(item.price) * Number(item.qty)).toFixed(2);
    html += `
      <div class="cart-item-card p-3 rounded-3 border bg-white d-flex align-items-center justify-content-between gap-2 shadow-sm mb-2">
        <div class="d-flex align-items-center gap-2 overflow-hidden">
          <span class="food-type-icon ${isVeg ? 'veg' : 'non-veg'}" style="flex-shrink:0;"></span>
          <div class="overflow-hidden">
            <h6 class="mb-0 fw-bold text-dark text-truncate" style="font-size: 0.92rem;" title="${item.name}">${item.name}</h6>
            <span class="text-muted small">₹${Number(item.price).toFixed(2)} each</span>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
          <div class="btn-group btn-group-sm rounded-pill border overflow-hidden" role="group">
            <button type="button" class="btn btn-light px-2 py-0 fw-bold text-success" onclick="decrementCartItem('${item.id}')">-</button>
            <span class="btn btn-white px-2 py-0 fw-bold disabled text-dark" style="font-size: 0.85rem;">${item.qty}</span>
            <button type="button" class="btn btn-light px-2 py-0 fw-bold text-success" onclick="addToCart('${item.id}', '${safeName}', ${item.price}, ${isVeg ? 1 : 0})">+</button>
          </div>
          <span class="fw-bold text-dark text-end" style="min-width: 65px;">₹${itemTotal}</span>
        </div>
      </div>
    `;
  });

  html += `</div>`;
  container.innerHTML = html;

  const totals = calculateCartTotals();
  if (subtotalEl) subtotalEl.textContent = `₹${totals.itemTotal.toFixed(2)}`;
  if (gstEl) gstEl.textContent = `₹${totals.taxes.toFixed(2)}`;
  if (deliveryEl) deliveryEl.innerHTML = totals.deliveryFee === 0 ? '<span class="text-success fw-bold">FREE</span>' : `₹${totals.deliveryFee.toFixed(2)}`;
  if (totalEl) totalEl.textContent = `₹${totals.finalTotal.toFixed(2)}`;

  if (freeDeliveryGap && freeDeliveryBar) {
    if (totals.itemTotal >= 499) {
      freeDeliveryGap.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i>Unlocked!</span>';
      freeDeliveryBar.style.width = '100%';
      freeDeliveryBar.className = 'progress-bar bg-success';
    } else {
      const remaining = (499 - totals.itemTotal).toFixed(0);
      freeDeliveryGap.textContent = `₹${remaining} away`;
      const pct = Math.min(100, Math.max(5, Math.round((totals.itemTotal / 499) * 100)));
      freeDeliveryBar.style.width = `${pct}%`;
      freeDeliveryBar.className = 'progress-bar bg-success';
    }
  }

  // Synchronize sticky sidebar if present on restaurant.php
  const sidebarContainer = document.getElementById('sidebarCartItems');
  if (sidebarContainer) {
    if (!cart || cart.length === 0) {
      sidebarContainer.innerHTML = `
        <div class="text-center py-4 text-muted">
          <i class="bi bi-cart3 fs-2 d-block mb-2 text-secondary opacity-50"></i>
          <div class="small">Cart is currently empty</div>
          <div class="small text-muted">Add items from the menu to start order</div>
        </div>
      `;
      const sSub = document.getElementById('sidebarSubtotal');
      const sGst = document.getElementById('sidebarGst');
      const sDel = document.getElementById('sidebarDelivery');
      const sTot = document.getElementById('sidebarTotal');
      if (sSub) sSub.textContent = '₹0.00';
      if (sGst) sGst.textContent = '₹0.00';
      if (sDel) sDel.textContent = '₹0.00';
      if (sTot) sTot.textContent = '₹0.00';
    } else {
      let sHtml = '<div class="d-flex flex-column gap-2">';
      cart.forEach(item => {
        const isVeg = (item.isVeg === true || item.isVeg === 1 || item.isVeg === '1' || item.isVeg === 'true');
        const safeName = (item.name || '').replace(/'/g, "\\'");
        sHtml += `
          <div class="d-flex align-items-center justify-content-between small py-1 border-bottom">
            <div class="d-flex align-items-center gap-1 overflow-hidden me-2">
              <span class="food-type-icon ${isVeg ? 'veg' : 'non-veg'}" style="width:12px; height:12px; flex-shrink:0;"></span>
              <span class="text-truncate" title="${item.name}">${item.name}</span>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
              <div class="btn-group btn-group-sm rounded-pill border overflow-hidden" role="group">
                <button type="button" class="btn btn-light px-1 py-0 fw-bold" onclick="decrementCartItem('${item.id}')">-</button>
                <span class="btn btn-white px-2 py-0 fw-bold disabled text-dark" style="font-size:0.75rem;">${item.qty}</span>
                <button type="button" class="btn btn-light px-1 py-0 fw-bold" onclick="addToCart('${item.id}', '${safeName}', ${item.price}, ${isVeg ? 1 : 0})">+</button>
              </div>
              <span class="fw-bold" style="min-width: 50px; text-align: right;">₹${(item.price * item.qty).toFixed(2)}</span>
            </div>
          </div>
        `;
      });
      sHtml += '</div>';
      sidebarContainer.innerHTML = sHtml;

      const sSub = document.getElementById('sidebarSubtotal');
      const sGst = document.getElementById('sidebarGst');
      const sDel = document.getElementById('sidebarDelivery');
      const sTot = document.getElementById('sidebarTotal');
      if (sSub) sSub.textContent = `₹${totals.itemTotal.toFixed(2)}`;
      if (sGst) sGst.textContent = `₹${totals.taxes.toFixed(2)}`;
      if (sDel) sDel.innerHTML = totals.deliveryFee === 0 ? '<span class="text-success fw-bold">FREE</span>' : `₹${totals.deliveryFee.toFixed(2)}`;
      if (sTot) sTot.textContent = `₹${totals.finalTotal.toFixed(2)}`;
    }
  }
}

/**
 * Render Dish Add / Quantity Controls on Menu Pages
 */
function renderDishQtyControls() {
  const wraps = document.querySelectorAll('.dish-add-btn-wrap, .dish-qty-control-slot');
  wraps.forEach(wrap => {
    const id = wrap.getAttribute('data-dish-id');
    const name = wrap.getAttribute('data-dish-name');
    const price = wrap.getAttribute('data-dish-price');
    const isVeg = wrap.getAttribute('data-dish-veg') === 'true';

    if (!id || !name || !price) return;

    const item = cart.find(c => c.id == id);
    const safeName = (name || '').replace(/'/g, "\\'");

    if (item && item.qty > 0) {
      wrap.innerHTML = `
        <div class="dish-qty-control btn-group btn-group-sm rounded-pill shadow-sm border bg-white overflow-hidden" role="group">
          <button type="button" class="btn btn-light px-2 py-1 text-success fw-bold" onclick="decrementCartItem('${id}')">-</button>
          <span class="btn btn-white px-2 py-1 fw-bold disabled text-dark" style="font-size:0.85rem;">${item.qty}</span>
          <button type="button" class="btn btn-light px-2 py-1 text-success fw-bold" onclick="addToCart('${id}', '${safeName}', ${price}, ${isVeg ? 1 : 0})">+</button>
        </div>
      `;
    } else {
      wrap.innerHTML = `
        <button 
          type="button" 
          class="btn btn-outline-success btn-sm rounded-pill px-3 fw-bold add-to-cart-btn shadow-sm"
          style="border-color: #2E6F40; color: #2E6F40; background: #fff;"
          onclick="addToCart('${id}', '${safeName}', ${price}, ${isVeg ? 1 : 0})"
        >
          ADD <i class="bi bi-plus"></i>
        </button>
      `;
    }
  });
}

/**
 * Filter Pills (Fast Delivery, Rating 4.0+, Pure Veg, Offers)
 */
function setupFilterPills() {
  const pills = document.querySelectorAll('.filter-btn');
  pills.forEach(pill => {
    pill.addEventListener('click', () => {
      pill.classList.toggle('active');
      applyFilters();
    });
  });
}

/**
 * Apply filters across restaurant cards
 */
function applyFilters() {
  const cards = document.querySelectorAll('.restaurant-card-col');
  if (cards.length === 0) return;

  const activeFilters = Array.from(document.querySelectorAll('.filter-btn.active')).map(btn => btn.getAttribute('data-filter'));

  cards.forEach(col => {
    let show = true;
    const rating = parseFloat(col.getAttribute('data-rating') || '0');
    const isVeg = col.getAttribute('data-veg') === 'true';
    const isFast = col.getAttribute('data-fast') === 'true';
    const hasOffer = col.getAttribute('data-offer') === 'true';

    if (activeFilters.includes('rating') && rating < 4.2) show = false;
    if (activeFilters.includes('veg') && !isVeg) show = false;
    if (activeFilters.includes('fast') && !isFast) show = false;
    if (activeFilters.includes('offer') && !hasOffer) show = false;

    col.style.display = show ? 'block' : 'none';
  });
}

/**
 * Veg-only filter switch on Restaurant Page
 */
function setupVegOnlyFilter() {
  const vegSwitch = document.getElementById('vegOnlySwitch');
  if (!vegSwitch) return;

  vegSwitch.addEventListener('change', () => {
    const isChecked = vegSwitch.checked;
    const dishRows = document.querySelectorAll('.dish-item-row');
    dishRows.forEach(row => {
      const isVeg = row.getAttribute('data-is-veg') === 'true';
      if (isChecked && !isVeg) {
        row.style.display = 'none';
      } else {
        row.style.display = 'flex';
      }
    });
  });
}

/**
 * Real-time Search in Dish Menu or Homepage
 */
function setupSearchInput() {
  const searchInput = document.getElementById('mainSearchInput') || document.getElementById('menuSearchInput');
  if (!searchInput) return;

  searchInput.addEventListener('input', (e) => {
    const query = e.target.value.toLowerCase().trim();

    // If on homepage
    const resCards = document.querySelectorAll('.restaurant-card-col');
    if (resCards.length > 0) {
      resCards.forEach(card => {
        const title = card.querySelector('.res-title')?.textContent.toLowerCase() || '';
        const cuisines = card.querySelector('.res-cuisines')?.textContent.toLowerCase() || '';
        if (title.includes(query) || cuisines.includes(query)) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    }

    // If on restaurant menu page
    const dishRows = document.querySelectorAll('.dish-item-row');
    if (dishRows.length > 0) {
      dishRows.forEach(row => {
        const title = row.querySelector('.dish-title')?.textContent.toLowerCase() || '';
        const desc = row.querySelector('.dish-desc')?.textContent.toLowerCase() || '';
        if (title.includes(query) || desc.includes(query)) {
          row.style.display = 'flex';
        } else {
          row.style.display = 'none';
        }
      });
    }
  });
}

/**
 * Render Full Checkout Page
 */
function renderCheckoutPage() {
  const container = document.getElementById('checkout-items-container');
  if (!container) return;

  const payBtn = document.getElementById('checkout-pay-btn');

  if (!cart || cart.length === 0) {
    container.innerHTML = '<div class="text-center text-muted small py-4"><i class="bi bi-cart-x fs-2 d-block mb-2 text-secondary opacity-50"></i>Your cart is empty. Please add items from menu.</div>';
    if (payBtn) {
      payBtn.classList.add('disabled');
      payBtn.style.pointerEvents = 'none';
      payBtn.style.opacity = '0.6';
    }
    return;
  }

  if (payBtn) {
    payBtn.classList.remove('disabled');
    payBtn.style.pointerEvents = 'auto';
    payBtn.style.opacity = '1';
  }

  let itemsHtml = '';
  cart.forEach(item => {
    const isVeg = (item.isVeg === true || item.isVeg === 1 || item.isVeg === '1' || item.isVeg === 'true');
    const safeName = (item.name || '').replace(/'/g, "\\'");
    itemsHtml += `
      <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
        <div class="d-flex align-items-center gap-2">
          <span class="food-type-icon ${isVeg ? 'veg' : 'non-veg'}"></span>
          <div>
            <h6 class="mb-0 fw-bold small">${item.name}</h6>
            <span class="text-muted" style="font-size: 0.75rem;">₹${Number(item.price).toFixed(2)} each</span>
          </div>
        </div>
        <div class="d-flex align-items-center gap-3">
          <div class="dish-qty-control" style="width: 75px; padding: 2px 4px;">
            <button onclick="decrementCartItem('${item.id}')">-</button>
            <span class="qty-number">${item.qty}</span>
            <button onclick="addToCart('${item.id}', '${safeName}', ${item.price}, ${isVeg ? 1 : 0})">+</button>
          </div>
          <span class="fw-bold text-pine" style="min-width: 60px; text-align: right;">₹${(item.price * item.qty).toFixed(2)}</span>
        </div>
      </div>
    `;
  });

  container.innerHTML = itemsHtml;

  const totals = calculateCartTotals();
  const itemTotalEl = document.getElementById('bill-item-total');
  if (itemTotalEl) itemTotalEl.textContent = `₹${totals.itemTotal.toFixed(2)}`;
  const deliveryEl = document.getElementById('bill-delivery-fee');
  if (deliveryEl) deliveryEl.innerHTML = totals.deliveryFee === 0 ? '<span class="text-success fw-bold">FREE</span>' : `₹${totals.deliveryFee.toFixed(2)}`;
  const platformEl = document.getElementById('bill-platform-fee');
  if (platformEl) platformEl.textContent = `₹${totals.platformFee.toFixed(2)}`;
  const taxesEl = document.getElementById('bill-taxes');
  if (taxesEl) taxesEl.textContent = `₹${totals.taxes.toFixed(2)}`;
  
  const discountRow = document.getElementById('bill-discount-row');
  if (discountRow) {
    if (totals.discount > 0) {
      discountRow.style.display = 'flex';
      const discAmt = document.getElementById('bill-discount-amount');
      if (discAmt) discAmt.textContent = `-₹${totals.discount.toFixed(2)}`;
    } else {
      discountRow.style.display = 'none';
    }
  }

  const finalTotalEl = document.getElementById('bill-final-total');
  if (finalTotalEl) finalTotalEl.textContent = `₹${totals.finalTotal.toFixed(2)}`;
}

/**
 * Apply Coupon Code
 */
function applyCoupon() {
  const input = document.getElementById('couponCodeInput');
  if (!input) return;

  const code = input.value.trim().toUpperCase();
  if (code === 'LUSHFOREST') {
    activeCoupon = 'LUSHFOREST';
    localStorage.setItem('pooja_active_coupon', activeCoupon);
    showToast('Success! 20% discount applied with code LUSHFOREST 🎉');
  } else if (code === 'ZIPPY50') {
    activeCoupon = 'ZIPPY50';
    localStorage.setItem('pooja_active_coupon', activeCoupon);
    showToast('Success! 50% discount applied with code ZIPPY50 🎉');
  } else {
    showToast('Invalid Coupon Code. Try LUSHFOREST or ZIPPY50', 'danger');
  }

  saveCart();
}

/**
 * Simple Toast Notification Utility
 */
function showToast(message, type = 'success') {
  let toastContainer = document.getElementById('toastContainer');
  if (!toastContainer) {
    toastContainer = document.createElement('div');
    toastContainer.id = 'toastContainer';
    toastContainer.style.position = 'fixed';
    toastContainer.style.bottom = '24px';
    toastContainer.style.right = '24px';
    toastContainer.style.zIndex = '9999';
    document.body.appendChild(toastContainer);
  }

  const toastEl = document.createElement('div');
  toastEl.className = `alert alert-${type === 'danger' ? 'danger' : 'success'} shadow-lg py-2 px-3 rounded-pill d-flex align-items-center gap-2 mb-2`;
  toastEl.style.transition = 'all 0.3s ease';
  toastEl.innerHTML = `
    <i class="bi bi-${type === 'danger' ? 'exclamation-circle' : 'check-circle-fill'}"></i>
    <span class="small fw-bold">${message}</span>
  `;

  toastContainer.appendChild(toastEl);
  setTimeout(() => {
    toastEl.style.opacity = '0';
    toastEl.style.transform = 'translateY(10px)';
    setTimeout(() => toastEl.remove(), 300);
  }, 2500);
}

/**
 * Order Tracking Simulated ETA Countdown (for static prototypes or fallback)
 */
function initOrderStatusSimulation() {
  const countdownEl = document.getElementById('live-eta-countdown');
  if (!countdownEl) return;

  // Don't run simulation if handled by order-status page or if order is completed/delivered/cancelled
  const status = (countdownEl.getAttribute('data-status') || '').toUpperCase();
  if (status === 'DELIVERED' || status === 'CANCELLED' || countdownEl.getAttribute('data-no-simulation') === 'true' || window.orderTrackingActive) {
    return;
  }

  const initialText = countdownEl.textContent.trim().toUpperCase();
  if (initialText.includes('DELIVERED') || initialText.includes('ARRIVED') || initialText.includes('CANCELLED')) {
    return;
  }

  let minutes = 19;
  let seconds = 45;

  const simTimer = setInterval(() => {
    const currentStatus = (countdownEl.getAttribute('data-status') || '').toUpperCase();
    if (currentStatus === 'DELIVERED' || currentStatus === 'CANCELLED' || countdownEl.getAttribute('data-no-simulation') === 'true' || window.orderTrackingActive) {
      clearInterval(simTimer);
      return;
    }
    if (seconds > 0) {
      seconds--;
    } else {
      if (minutes > 0) {
        minutes--;
        seconds = 59;
      }
    }
    const formattedSec = seconds < 10 ? '0' + seconds : seconds;
    countdownEl.textContent = `${minutes}:${formattedSec} mins`;
  }, 1000);
}

/**
 * Mobile Bottom App Navigation Bar (Zippy App Style)
 */
function initMobileBottomNav() {
  // Do not inject on dedicated Admin portal, KDS fullscreen view, or Delivery Partner Fleet app
  const path = window.location.pathname.toLowerCase();
  if (document.querySelector('.admin-layout') || document.querySelector('.kds-board-container') || document.querySelector('.delivery-partner-app') || path.includes('delivery')) {
    document.body.classList.add('is-admin-page');
    return;
  }

  if (document.querySelector('.mobile-bottom-nav')) return;

  const ext = currentPath.includes('.html') ? '.html' : '.php';
  const isHome = currentPath.endsWith('index' + ext) || currentPath.endsWith('/') || currentPath.endsWith('customer');
  const isSearch = currentPath.includes('search');
  const isOffers = currentPath.includes('offers');
  const isOrders = currentPath.includes('order-history') || currentPath.includes('order-status');
  const isProfile = currentPath.includes('profile') || currentPath.includes('manage-addresses');

  const nav = document.createElement('nav');
  nav.className = 'mobile-bottom-nav d-lg-none';
  nav.setAttribute('aria-label', 'Mobile Bottom Navigation');

  nav.innerHTML = `
    <a href="index${ext}" class="mobile-nav-btn ${isHome ? 'active' : ''}">
      <i class="bi bi-house-door-fill"></i>
      <span>Home</span>
    </a>
    <a href="search${ext}" class="mobile-nav-btn ${isSearch ? 'active' : ''}">
      <i class="bi bi-search"></i>
      <span>Search</span>
    </a>
    <a href="offers${ext}" class="mobile-nav-btn ${isOffers ? 'active' : ''}">
      <i class="fa-solid fa-percent"></i>
      <span>Offers</span>
    </a>
    <a href="order-history${ext}" class="mobile-nav-btn ${isOrders ? 'active' : ''}">
      <i class="bi bi-clock-history"></i>
      <span>Orders</span>
    </a>
    <a href="profile${ext}" class="mobile-nav-btn ${isProfile ? 'active' : ''}">
      <i class="bi bi-person-circle"></i>
      <span>Profile</span>
    </a>
  `;

  document.body.appendChild(nav);
  updateCartBadge();
}

/**
 * Mobile Admin Drawer & Hamburger Management
 */
function initAdminMobileDrawer() {
  const adminLayout = document.querySelector('.admin-layout');
  if (!adminLayout) return;

  document.body.classList.add('is-admin-page');

  const topbar = document.querySelector('.admin-topbar');
  if (topbar && !topbar.querySelector('.admin-hamburger-btn')) {
    const hamburger = document.createElement('button');
    hamburger.type = 'button';
    hamburger.className = 'btn btn-sm btn-light rounded-circle me-2 d-lg-none admin-hamburger-btn shadow-sm';
    hamburger.setAttribute('aria-label', 'Toggle Navigation Menu');
    hamburger.innerHTML = '<i class="bi bi-list fs-5"></i>';
    hamburger.onclick = toggleAdminSidebar;

    const firstChild = topbar.firstElementChild;
    if (firstChild) {
      firstChild.insertBefore(hamburger, firstChild.firstChild);
    } else {
      topbar.prepend(hamburger);
    }
  }

  if (!document.querySelector('.admin-sidebar-backdrop')) {
    const backdrop = document.createElement('div');
    backdrop.className = 'admin-sidebar-backdrop';
    backdrop.onclick = toggleAdminSidebar;
    adminLayout.appendChild(backdrop);
  }
}

/**
 * Global Admin Sidebar Toggle
 */
window.toggleAdminSidebar = function() {
  const sidebar = document.querySelector('.admin-sidebar');
  const backdrop = document.querySelector('.admin-sidebar-backdrop');
  if (!sidebar) return;

  const isOpen = sidebar.classList.contains('show-mobile');
  if (isOpen) {
    sidebar.classList.remove('show-mobile');
    if (backdrop) backdrop.classList.remove('show');
  } else {
    sidebar.classList.add('show-mobile');
    if (backdrop) backdrop.classList.add('show');
  }
};
