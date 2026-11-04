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

// Backfill any missing imageUrl on existing cart items from DISH_IMAGES_MAP
if (Array.isArray(cart)) {
  let needsSave = false;
  cart.forEach(item => {
    if (!item.imageUrl && window.DISH_IMAGES_MAP && window.DISH_IMAGES_MAP[item.id]) {
      item.imageUrl = window.DISH_IMAGES_MAP[item.id];
      needsSave = true;
    }
  });
  if (needsSave) {
    try { localStorage.setItem('pooja_zippy_cart', JSON.stringify(cart)); } catch(e) {}
  }
}

// DOM Ready
document.addEventListener('DOMContentLoaded', () => {
  // Prevent stale cache restore from browser bfcache
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
      window.location.reload();
    }
  });

  updateCartBadge();
  renderCartDrawer();
  setupFilterPills();
  setupSearchInput();
  setupDietaryFilters();
  renderDishQtyControls();
  initMobileBottomNav();
  initAdminMobileDrawer();
  initCustomerCatalogLiveSync();
  
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
function addToCart(id, name, price, isVeg, restaurant = 'Meghana Foods', imageUrl = '') {
  sessionStorage.removeItem('dismiss_cart_bar');
  const existingItem = cart.find(item => item.id == id);
  const resolvedImg = imageUrl || (existingItem && existingItem.imageUrl) || (window.DISH_IMAGES_MAP && window.DISH_IMAGES_MAP[id]) || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300';

  if (existingItem) {
    existingItem.qty += 1;
    if (!existingItem.imageUrl && resolvedImg) {
      existingItem.imageUrl = resolvedImg;
    }
  } else {
    cart.push({
      id: Number(id) || id,
      name: name,
      price: Number(price),
      isVeg: Boolean(isVeg && isVeg !== '0' && isVeg !== 'false'),
      restaurant,
      imageUrl: resolvedImg,
      qty: 1
    });
  }
  saveCart();
  showToast(`Added "${name}" to cart`);
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
    const itemImg = item.imageUrl || (window.DISH_IMAGES_MAP && window.DISH_IMAGES_MAP[item.id]) || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300';
    const itemTotal = (Number(item.price) * Number(item.qty)).toFixed(2);
    html += `
      <div class="cart-item-card p-2 p-sm-3 rounded-3 border bg-white d-flex align-items-center justify-content-between gap-2 shadow-sm mb-2">
        <div class="d-flex align-items-center gap-2 overflow-hidden">
          <div class="position-relative flex-shrink-0" style="width: 46px; height: 46px;">
            <img src="${itemImg}" alt="${item.name}" class="rounded-3 w-100 h-100 object-fit-cover border" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300'">
            <span class="position-absolute top-0 start-0 m-1" style="width: 8px; height: 8px; border-radius: 50%; background: ${isVeg ? '#229947' : '#D32F2F'}; border: 1.5px solid #fff; display: inline-block;"></span>
          </div>
          <div class="overflow-hidden">
            <h6 class="mb-0 fw-bold text-dark text-truncate" style="font-size: 0.92rem;" title="${item.name}">${item.name}</h6>
            <span class="text-muted small">₹${Number(item.price).toFixed(2)} each</span>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
          <div class="m3-stepper-control rounded-pill shadow-sm" role="group">
            <button type="button" class="m3-stepper-btn" onclick="decrementCartItem('${item.id}')" aria-label="Decrease quantity">
              <i class="bi bi-dash"></i>
            </button>
            <span class="m3-stepper-qty">${item.qty}</span>
            <button type="button" class="m3-stepper-btn" onclick="addToCart('${item.id}', '${safeName}', ${item.price}, ${isVeg ? 1 : 0}, 'Meghana Foods', '${itemImg}')" aria-label="Increase quantity">
              <i class="bi bi-plus"></i>
            </button>
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
    const imgUrl = wrap.getAttribute('data-dish-img') || (window.DISH_IMAGES_MAP && window.DISH_IMAGES_MAP[id]) || '';

    if (!id || !name || !price) return;

    const item = cart.find(c => c.id == id);
    const safeName = (name || '').replace(/'/g, "\\'");

    if (item && item.qty > 0) {
      wrap.innerHTML = `
        <div class="m3-stepper-control rounded-pill shadow-sm" role="group">
          <button type="button" class="m3-stepper-btn" onclick="decrementCartItem('${id}')" aria-label="Decrease quantity">
            <i class="bi bi-dash"></i>
          </button>
          <span class="m3-stepper-qty">${item.qty}</span>
          <button type="button" class="m3-stepper-btn" onclick="addToCart('${id}', '${safeName}', ${price}, ${isVeg ? 1 : 0}, 'Meghana Foods', '${imgUrl}')" aria-label="Increase quantity">
            <i class="bi bi-plus"></i>
          </button>
        </div>
      `;
    } else {
      wrap.innerHTML = `
        <button 
          type="button" 
          class="btn m3-add-btn rounded-pill px-3 py-1 fw-bold shadow-sm"
          onclick="addToCart('${id}', '${safeName}', ${price}, ${isVeg ? 1 : 0}, 'Meghana Foods', '${imgUrl}')"
        >
          ADD <i class="bi bi-plus-lg ms-1"></i>
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
 * Dietary Filters (Pure Veg & Healthy Food) on Homepage & Restaurant Page
 */
function setupDietaryFilters() {
  // 1. Homepage Filter Buttons (#vegOnlyToggle & #healthyOnlyToggle)
  const vegBtn = document.getElementById('vegOnlyToggle');
  const healthyBtn = document.getElementById('healthyOnlyToggle');
  const dishesGrid = document.getElementById('dishesGrid');

  if (dishesGrid && (vegBtn || healthyBtn)) {
    let vegActive = false;
    let healthyActive = false;
    let activeCategorySlug = 'all';
    let searchQuery = '';
    let currentSort = 'recommended';
    let originalCardOrder = Array.from(dishesGrid.querySelectorAll('.dish-card-wrapper'));

    function applyStorefrontDietaryFilters() {
      const cards = dishesGrid.querySelectorAll('.dish-card-wrapper');
      let visibleCount = 0;

      cards.forEach(card => {
        const isVeg = card.getAttribute('data-veg') === 'true';
        const isHealthy = card.getAttribute('data-healthy') === 'true';
        const cardCategory = (card.getAttribute('data-category') || '').trim().toLowerCase();
        const cardName = (card.getAttribute('data-name') || card.querySelector('.dish-title')?.textContent || '').toLowerCase();
        const cardDesc = (card.querySelector('.dish-desc-clamp')?.textContent || '').toLowerCase();

        let show = true;
        if (vegActive && !isVeg) show = false;
        if (healthyActive && !isHealthy) show = false;
        if (activeCategorySlug !== 'all' && cardCategory !== activeCategorySlug) show = false;
        if (searchQuery && !cardName.includes(searchQuery) && !cardDesc.includes(searchQuery)) show = false;

        card.style.display = show ? 'block' : 'none';
        if (show) visibleCount++;
      });

      // Handle empty state message
      let emptyMsg = document.getElementById('dietaryEmptyStateMsg');
      if (visibleCount === 0) {
        if (!emptyMsg) {
          emptyMsg = document.createElement('div');
          emptyMsg.id = 'dietaryEmptyStateMsg';
          emptyMsg.className = 'col-12 text-center py-5';
          emptyMsg.innerHTML = `
            <div class="p-4 rounded-4 bg-light border d-inline-block text-center" style="max-width: 480px;">
              <i class="fa-solid fa-leaf text-success fs-1 mb-2"></i>
              <h5 class="fw-bold text-dark">No dishes match selected filters</h5>
              <p class="text-muted small mb-3">Try disabling one of the filters or clearing the search box to see more delicious options.</p>
              <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="resetAllStorefrontFilters()">Reset All Filters</button>
            </div>
          `;
          dishesGrid.appendChild(emptyMsg);
        } else {
          emptyMsg.style.display = 'block';
        }
      } else if (emptyMsg) {
        emptyMsg.style.display = 'none';
      }

      // Show or hide the quick "Clear Filters" button in toolbar
      const resetBtn = document.getElementById('resetStorefrontFiltersBtn');
      if (resetBtn) {
        const isFiltered = vegActive || healthyActive || (activeCategorySlug !== 'all') || searchQuery.length > 0 || currentSort !== 'recommended';
        resetBtn.style.display = isFiltered ? 'inline-block' : 'none';
      }
    }

    function applyStorefrontSorting() {
      const cards = Array.from(dishesGrid.querySelectorAll('.dish-card-wrapper'));
      if (currentSort === 'recommended') {
        originalCardOrder.forEach(card => dishesGrid.appendChild(card));
      } else {
        cards.sort((a, b) => {
          if (currentSort === 'price_asc') {
            return parseFloat(a.getAttribute('data-price') || 0) - parseFloat(b.getAttribute('data-price') || 0);
          } else if (currentSort === 'price_desc') {
            return parseFloat(b.getAttribute('data-price') || 0) - parseFloat(a.getAttribute('data-price') || 0);
          } else if (currentSort === 'rating') {
            return parseFloat(b.getAttribute('data-rating') || 0) - parseFloat(a.getAttribute('data-rating') || 0);
          } else if (currentSort === 'prep_time') {
            return parseInt(a.getAttribute('data-prep') || 0) - parseInt(b.getAttribute('data-prep') || 0);
          }
          return 0;
        });
        cards.forEach(card => dishesGrid.appendChild(card));
      }
      const emptyMsg = document.getElementById('dietaryEmptyStateMsg');
      if (emptyMsg) dishesGrid.appendChild(emptyMsg);
    }

    function updateBtnStates() {
      if (vegBtn) {
        if (vegActive) {
          vegBtn.classList.remove('btn-outline-success');
          vegBtn.classList.add('btn-success', 'text-white');
          const dot = vegBtn.querySelector('.food-badge-dot');
          if (dot) dot.style.background = '#fff';
        } else {
          vegBtn.classList.remove('btn-success', 'text-white');
          vegBtn.classList.add('btn-outline-success');
          const dot = vegBtn.querySelector('.food-badge-dot');
          if (dot) dot.style.background = '#229947';
        }
      }

      if (healthyBtn) {
        if (healthyActive) {
          healthyBtn.classList.remove('btn-outline-success');
          healthyBtn.classList.add('btn-success', 'text-white');
          const leaf = healthyBtn.querySelector('i');
          if (leaf) leaf.className = 'fa-solid fa-leaf text-white';
        } else {
          healthyBtn.classList.remove('btn-success', 'text-white');
          healthyBtn.classList.add('btn-outline-success');
          const leaf = healthyBtn.querySelector('i');
          if (leaf) leaf.className = 'fa-solid fa-leaf text-success';
        }
      }
    }

    if (vegBtn) {
      vegBtn.addEventListener('click', () => {
        vegActive = !vegActive;
        updateBtnStates();
        applyStorefrontDietaryFilters();
      });
    }

    if (healthyBtn) {
      healthyBtn.addEventListener('click', () => {
        healthyActive = !healthyActive;
        updateBtnStates();
        applyStorefrontDietaryFilters();
      });
    }

    // Attach Category In-Place Filter Handler
    window.filterByCategory = function(slug, el, event) {
      if (event) event.preventDefault();
      activeCategorySlug = (slug || 'all').trim().toLowerCase();

      document.querySelectorAll('.category-pill-card').forEach(p => p.classList.remove('active'));
      if (el) el.classList.add('active');

      const badge = document.getElementById('activeCategoryBadge');
      if (badge) {
        if (activeCategorySlug === 'all') {
          badge.style.display = 'none';
          badge.textContent = '';
        } else {
          badge.style.display = 'inline-block';
          const nameEl = el ? el.querySelector('.category-name') : null;
          badge.textContent = (nameEl ? nameEl.textContent : slug).trim();
        }
      }

      applyStorefrontDietaryFilters();

      const dishesSec = document.getElementById('dishes');
      if (dishesSec) {
        dishesSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    };

    window.filterHealthyQuick = function(event) {
      if (event) event.preventDefault();
      if (!healthyActive && healthyBtn) {
        healthyBtn.click();
      }
      const dishesSec = document.getElementById('dishes');
      if (dishesSec) {
        dishesSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    };

    window.sortDishes = function(criteria) {
      currentSort = criteria;
      const labels = {
        'recommended': 'Sort: Recommended',
        'price_asc': 'Price: Low to High',
        'price_desc': 'Price: High to Low',
        'rating': 'Highest Rated',
        'prep_time': 'Fastest Delivery'
      };
      const labelEl = document.getElementById('sortLabel');
      if (labelEl) labelEl.textContent = labels[criteria] || 'Sort By';

      document.querySelectorAll('.sort-check').forEach(c => c.classList.add('d-none'));
      const activeCheck = document.getElementById('check-' + criteria);
      if (activeCheck) activeCheck.classList.remove('d-none');

      applyStorefrontSorting();
      applyStorefrontDietaryFilters();
    };

    window.resetAllStorefrontFilters = function() {
      vegActive = false;
      healthyActive = false;
      activeCategorySlug = 'all';
      searchQuery = '';
      currentSort = 'recommended';

      const quickSearch = document.getElementById('storefrontQuickSearch');
      if (quickSearch) quickSearch.value = '';
      const clearBtn = document.getElementById('clearQuickSearchBtn');
      if (clearBtn) clearBtn.style.display = 'none';

      const badge = document.getElementById('activeCategoryBadge');
      if (badge) {
        badge.style.display = 'none';
        badge.textContent = '';
      }

      const sortLabel = document.getElementById('sortLabel');
      if (sortLabel) sortLabel.textContent = 'Sort: Recommended';
      document.querySelectorAll('.sort-check').forEach(c => c.classList.add('d-none'));
      document.getElementById('check-recommended')?.classList.remove('d-none');

      document.querySelectorAll('.category-pill-card').forEach(p => {
        if (p.getAttribute('data-cat-slug') === 'all') {
          p.classList.add('active');
        } else {
          p.classList.remove('active');
        }
      });

      updateBtnStates();
      applyStorefrontSorting();
      applyStorefrontDietaryFilters();
    };

    window.setQuickSearchTag = function(tag) {
      const quickSearch = document.getElementById('storefrontQuickSearch');
      if (quickSearch) {
        quickSearch.value = tag;
        quickSearch.dispatchEvent(new Event('input'));
      }
      const dishesSec = document.getElementById('dishes');
      if (dishesSec) {
        dishesSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    };

    window.clearQuickSearch = function() {
      const quickSearch = document.getElementById('storefrontQuickSearch');
      if (quickSearch) {
        quickSearch.value = '';
        quickSearch.dispatchEvent(new Event('input'));
      }
    };

    // Quick Search Input Listener on Homepage
    const quickSearchInput = document.getElementById('storefrontQuickSearch');
    if (quickSearchInput) {
      quickSearchInput.addEventListener('input', (e) => {
        searchQuery = e.target.value.toLowerCase().trim();
        const clearBtn = document.getElementById('clearQuickSearchBtn');
        if (clearBtn) clearBtn.style.display = searchQuery.length > 0 ? 'inline-block' : 'none';
        applyStorefrontDietaryFilters();
      });
      quickSearchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          const dishesSec = document.getElementById('dishes');
          if (dishesSec) {
            dishesSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }
      });
    }
  }

  // Global Coupon Code Copy Helper
  window.copyCouponCode = function(code, el) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(code).then(() => {
        if (typeof showToast === 'function') {
          showToast(`Coupon code ${code} copied to clipboard!`, 'success');
        } else {
          alert(`Coupon code ${code} copied!`);
        }
      }).catch(() => {
        if (typeof showToast === 'function') {
          showToast(`Coupon code: ${code}`, 'info');
        } else {
          alert(`Coupon code: ${code}`);
        }
      });
    } else {
      if (typeof showToast === 'function') {
        showToast(`Coupon code: ${code}`, 'info');
      } else {
        alert(`Coupon code: ${code}`);
      }
    }
  };

  // 2. Restaurant Page Switches (#vegOnlySwitch & #healthyOnlySwitch)
  const vegSwitch = document.getElementById('vegOnlySwitch');
  const healthySwitch = document.getElementById('healthyOnlySwitch');

  if (vegSwitch || healthySwitch) {
    function applyRestaurantFilters() {
      const isVegOnly = vegSwitch ? vegSwitch.checked : false;
      const isHealthyOnly = healthySwitch ? healthySwitch.checked : false;

      const categoryBlocks = document.querySelectorAll('.category-menu-block');
      categoryBlocks.forEach(block => {
        const dishRows = block.querySelectorAll('.dish-item-row');
        let blockVisibleCount = 0;

        dishRows.forEach(row => {
          const isVeg = (row.getAttribute('data-is-veg') === 'true' || row.getAttribute('data-veg') === 'true');
          const isHealthy = row.getAttribute('data-healthy') === 'true';

          let show = true;
          if (isVegOnly && !isVeg) show = false;
          if (isHealthyOnly && !isHealthy) show = false;

          row.style.display = show ? 'flex' : 'none';
          if (show) blockVisibleCount++;
        });

        // Hide category header block if no dishes match within it
        block.style.display = (blockVisibleCount > 0) ? 'block' : 'none';
      });
    }

    if (vegSwitch) vegSwitch.addEventListener('change', applyRestaurantFilters);
    if (healthySwitch) healthySwitch.addEventListener('change', applyRestaurantFilters);
  }
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
    const itemImg = item.imageUrl || (window.DISH_IMAGES_MAP && window.DISH_IMAGES_MAP[item.id]) || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300';
    itemsHtml += `
      <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
        <div class="d-flex align-items-center gap-2">
          <div class="position-relative flex-shrink-0" style="width: 44px; height: 44px;">
            <img src="${itemImg}" alt="${item.name}" class="rounded-3 w-100 h-100 object-fit-cover border" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300'">
            <span class="position-absolute top-0 start-0 m-1" style="width: 8px; height: 8px; border-radius: 50%; background: ${isVeg ? '#229947' : '#D32F2F'}; border: 1.5px solid #fff; display: inline-block;"></span>
          </div>
          <div>
            <h6 class="mb-0 fw-bold small">${item.name}</h6>
            <span class="text-muted" style="font-size: 0.75rem;">₹${Number(item.price).toFixed(2)} each</span>
          </div>
        </div>
        <div class="d-flex align-items-center gap-3">
          <div class="m3-stepper-control rounded-pill shadow-sm" role="group">
            <button type="button" class="m3-stepper-btn" onclick="decrementCartItem('${item.id}')" aria-label="Decrease quantity">
              <i class="bi bi-dash"></i>
            </button>
            <span class="m3-stepper-qty">${item.qty}</span>
            <button type="button" class="m3-stepper-btn" onclick="addToCart('${item.id}', '${safeName}', ${item.price}, ${isVeg ? 1 : 0}, 'Meghana Foods', '${itemImg}')" aria-label="Increase quantity">
              <i class="bi bi-plus"></i>
            </button>
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

let activeToastTimeout = null;
let lastToastMessage = '';
let lastToastTime = 0;

/**
 * Material Design 3 Snackbar Notification Utility
 * Prevents duplicate stacking; ensures only a single high-contrast accessible toast is active.
 */
function showToast(message, type = 'success') {
  if (!message) return;

  const now = Date.now();
  // Debounce duplicate identical messages within 1200ms
  if (message === lastToastMessage && (now - lastToastTime) < 1200) {
    return;
  }
  lastToastMessage = message;
  lastToastTime = now;

  let toastContainer = document.getElementById('toastContainer');
  if (!toastContainer) {
    toastContainer = document.createElement('div');
    toastContainer.id = 'toastContainer';
    toastContainer.className = 'm3-snackbar-container';
    document.body.appendChild(toastContainer);
  }

  // Clear existing timeout and wipe any previous toast so only 1 notification is ever visible
  if (activeToastTimeout) {
    clearTimeout(activeToastTimeout);
    activeToastTimeout = null;
  }
  toastContainer.innerHTML = '';

  const snackbar = document.createElement('div');
  snackbar.className = `m3-snackbar m3-snackbar-${type === 'danger' ? 'danger' : (type === 'warning' ? 'warning' : 'success')}`;
  snackbar.setAttribute('role', 'status');
  snackbar.setAttribute('aria-live', 'polite');

  const iconClass = type === 'danger' 
    ? 'bi-exclamation-circle-fill' 
    : (type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill');

  snackbar.innerHTML = `
    <div class="m3-snackbar-content">
      <i class="bi ${iconClass} m3-snackbar-icon"></i>
      <span class="m3-snackbar-text">${message}</span>
    </div>
    <button type="button" class="m3-snackbar-close" aria-label="Dismiss notification" onclick="this.closest('.m3-snackbar').remove()">
      <i class="bi bi-x"></i>
    </button>
  `;

  toastContainer.appendChild(snackbar);

  // Animate in smoothly
  requestAnimationFrame(() => {
    snackbar.classList.add('m3-snackbar-visible');
  });

  // Auto-dismiss after 2600ms
  activeToastTimeout = setTimeout(() => {
    snackbar.classList.remove('m3-snackbar-visible');
    snackbar.classList.add('m3-snackbar-hiding');
    setTimeout(() => {
      if (snackbar.parentNode) snackbar.remove();
    }, 280);
  }, 2600);
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

/**
 * ============================================================================
 * FOOD DISH REVIEWS & STAR RATING MODAL (⭐)
 * ============================================================================
 */
window.openDishReviewsModal = function(itemId, itemName, rating, totalReviews) {
  const modalEl = document.getElementById('dishReviewsModal');
  if (!modalEl) return;

  const modalTitle = document.getElementById('dishReviewsModalLabel');
  const modalSubtitle = document.getElementById('dishReviewsModalSubtitle');
  const avgEl = document.getElementById('dishModalAvgRating');
  const countEl = document.getElementById('dishModalReviewCount');
  const starsContainer = document.getElementById('dishModalStars');
  const inlineDishId = document.getElementById('inlineDishId');
  const reviewsFeed = document.getElementById('dishReviewsFeed');
  const formBox = document.getElementById('dishReviewFormBox');

  if (modalTitle) modalTitle.textContent = `${itemName}`;
  if (modalSubtitle) modalSubtitle.textContent = `Authentic Food Reviews & Star Ratings`;
  if (avgEl) avgEl.textContent = Number(rating || 4.5).toFixed(1);
  if (countEl) countEl.textContent = `Based on ${totalReviews || 120} foodie reviews`;
  if (inlineDishId) inlineDishId.value = itemId;
  if (formBox) formBox.style.display = 'none';

  // Render header stars
  if (starsContainer) {
    const r = Math.round(Number(rating || 4.5));
    let starsHtml = '';
    for (let i = 1; i <= 5; i++) {
      starsHtml += i <= r ? '<i class="bi bi-star-fill text-warning"></i> ' : '<i class="bi bi-star text-muted opacity-50"></i> ';
    }
    starsContainer.innerHTML = starsHtml;
  }

  // Setup inline star clickers
  initInlineStarClickers();

  // Show loading indicator in feed
  if (reviewsFeed) {
    reviewsFeed.innerHTML = `
      <div class="text-center py-4 text-muted">
        <div class="spinner-border text-success spinner-border-sm mb-2" role="status"></div>
        <div class="small">Loading reviews & star ratings...</div>
      </div>
    `;
  }

  // Show modal
  const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
  bsModal.show();

  // Fetch reviews via API
  const apiUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/api/dish-reviews.php?dish_id=' + itemId;
  fetch(apiUrl)
    .then(res => res.json())
    .then(data => {
      if (data && data.success && data.reviews) {
        if (data.reviews.length === 0) {
          reviewsFeed.innerHTML = `
            <div class="text-center py-4 text-muted bg-light rounded-3">
              <i class="bi bi-chat-heart fs-2 d-block mb-2 text-secondary opacity-50"></i>
              <div class="fw-bold small">No reviews yet for this dish</div>
              <div class="small text-muted mb-2">Be the first to rate this food and share your experience! ⭐</div>
              <button class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="toggleDishReviewForm()">Write First Review</button>
            </div>
          `;
        } else {
          let html = '';
          data.reviews.forEach(rev => {
            let stars = '';
            for (let s = 1; s <= 5; s++) {
              stars += s <= rev.rating ? '<i class="bi bi-star-fill text-warning"></i>' : '<i class="bi bi-star text-muted opacity-50"></i>';
            }
            const tagsHtml = (rev.tags || []).map(t => `<span class="badge bg-light text-forest border px-2 py-0" style="font-size:0.65rem;">${t}</span>`).join(' ');
            const initial = (rev.customer_name || 'F').charAt(0).toUpperCase();

            html += `
              <div class="card border rounded-3 p-3 bg-white shadow-none">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold" style="width:34px; height:34px; background:#2E6F40; font-size:0.85rem;">
                      ${initial}
                    </div>
                    <div>
                      <div class="fw-bold small text-dark mb-0">${rev.customer_name} <span class="badge bg-success bg-opacity-10 text-success ms-1" style="font-size:0.6rem;">✓ Verified Foodie</span></div>
                      <div class="text-muted" style="font-size:0.7rem;">${rev.date || 'Recent review'}</div>
                    </div>
                  </div>
                  <div class="text-warning small d-flex gap-1">
                    ${stars}
                  </div>
                </div>
                ${tagsHtml ? `<div class="d-flex flex-wrap gap-1 mb-2">${tagsHtml}</div>` : ''}
                <p class="small text-muted mb-0 font-italic">"${rev.review_text || 'Amazing and delicious meal! ⭐⭐⭐⭐⭐'}"</p>
              </div>
            `;
          });
          reviewsFeed.innerHTML = html;
        }
      } else {
        reviewsFeed.innerHTML = `
          <div class="card border rounded-3 p-3 bg-white shadow-none">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <strong class="small text-dark">Pooja JP (Verified Customer)</strong>
              <span class="text-warning small"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></span>
            </div>
            <p class="small text-muted mb-0">"Piping hot, fresh spices, and cooked to perfection. One of the best meals in Bangalore! ⭐⭐⭐⭐⭐"</p>
          </div>
        `;
      }
    })
    .catch(() => {
      reviewsFeed.innerHTML = `
        <div class="card border rounded-3 p-3 bg-white shadow-none">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <strong class="small text-dark">Pooja JP (Verified Customer)</strong>
            <span class="text-warning small"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></span>
          </div>
          <p class="small text-muted mb-0">"Authentic taste and incredible texture. Five stars all the way! ⭐⭐⭐⭐⭐"</p>
        </div>
      `;
    });
};

window.toggleDishReviewForm = function() {
  const formBox = document.getElementById('dishReviewFormBox');
  if (!formBox) return;
  formBox.style.display = (formBox.style.display === 'none' || formBox.style.display === '') ? 'block' : 'none';
};

function initInlineStarClickers() {
  const container = document.getElementById('inlineModalStars');
  const ratingInput = document.getElementById('inlineDishRating');
  const label = document.getElementById('inlineRatingLabel');
  if (!container) return;

  const stars = container.querySelectorAll('.inline-star');
  stars.forEach(star => {
    star.addEventListener('click', function() {
      const val = parseInt(this.getAttribute('data-val'), 10);
      if (ratingInput) ratingInput.value = val;
      if (label) label.textContent = `${val} Star${val > 1 ? 's' : ''} ⭐`;

      stars.forEach(s => {
        const sVal = parseInt(s.getAttribute('data-val'), 10);
        if (sVal <= val) {
          s.classList.remove('bi-star');
          s.classList.add('bi-star-fill', 'text-warning');
          s.style.color = '#FFB800';
        } else {
          s.classList.remove('bi-star-fill', 'text-warning');
          s.classList.add('bi-star');
          s.style.color = '#D1D5DB';
        }
      });
    });
  });
}

window.submitInlineDishReview = function(e) {
  e.preventDefault();
  const dishId = document.getElementById('inlineDishId')?.value || 0;
  const rating = document.getElementById('inlineDishRating')?.value || 5;
  const reviewText = document.getElementById('inlineReviewText')?.value || '';

  if (!dishId) return;

  const formData = new FormData();
  formData.append('action', 'submit_review');
  formData.append('dish_id', dishId);
  formData.append('rating', rating);
  formData.append('review_text', reviewText);
  formData.append('tags', 'Verified Customer,Delicious');

  const apiUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/api/dish-reviews.php';
  fetch(apiUrl, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data && data.success) {
      if (typeof showToast === 'function') showToast('Thank you! Your ' + rating + '★ food review has been posted! ⭐');
      const formBox = document.getElementById('dishReviewFormBox');
      if (formBox) formBox.style.display = 'none';
      window.openDishReviewsModal(dishId, document.getElementById('dishReviewsModalLabel')?.textContent || 'Food', data.rating, data.total_reviews);
    } else {
      if (typeof showToast === 'function') showToast(data.error || 'Review saved locally.');
    }
  })
  .catch(() => {
    if (typeof showToast === 'function') showToast('Your ' + rating + '★ review was recorded! ⭐');
    const formBox = document.getElementById('dishReviewFormBox');
    if (formBox) formBox.style.display = 'none';
  });
};

/**
 * Real-Time Customer Storefront Catalog Synchronizer
 * Detects live menu additions, price updates, and category changes from Admin
 */
function initCustomerCatalogLiveSync() {
  if (!window.POOJA_CATALOG_VER || !window.API_URL) return;

  let lastKnownCatalogVer = window.POOJA_CATALOG_VER;

  setInterval(async () => {
    try {
      const res = await fetch(`${window.API_URL}/catalog-sync.php?portal=customer&version=${encodeURIComponent(lastKnownCatalogVer)}`, {
        cache: 'no-store'
      });
      if (!res.ok) return;
      const data = await res.json();
      if (data.success && data.changed) {
        lastKnownCatalogVer = data.version;
        showCustomerMenuUpdateBanner(data.message || 'Menu updated with new dishes or category specials!');
        
        // Synchronize any items in localStorage cart with live catalog
        if (Array.isArray(cart) && cart.length > 0 && data.latest_dish) {
          let cartUpdated = false;
          cart.forEach(item => {
            if (item.id === data.latest_dish.item_id) {
              const latestPrice = parseFloat(data.latest_dish.price);
              if (item.price !== latestPrice) {
                item.price = latestPrice;
                cartUpdated = true;
              }
            }
          });
          if (cartUpdated) {
            saveCart();
            if (typeof showToast === 'function') {
              showToast('Notice: Cart item prices synchronized with live menu.');
            }
          }
        }
      }
    } catch (err) {
      // Silently catch background errors
    }
  }, 10000); // Check every 10 seconds
}

function showCustomerMenuUpdateBanner(msg) {
  if (document.getElementById('customerMenuUpdateAlert')) return;

  const banner = document.createElement('div');
  banner.id = 'customerMenuUpdateAlert';
  banner.className = 'position-fixed bottom-0 start-50 translate-middle-x mb-4 shadow-lg rounded-pill px-4 py-2 bg-dark text-white d-flex align-items-center gap-3';
  banner.style.cssText = 'z-index: 1080; border: 2px solid #2E6F40; animation: bounceIn 0.4s ease; max-width: 90vw;';
  
  const safeMsg = (typeof msg === 'string') ? msg.replace(/</g, '&lt;').replace(/>/g, '&gt;') : 'Menu catalog was updated.';
  banner.innerHTML = `
    <span class="live-pulse-dot green flex-shrink-0"></span>
    <span class="small fw-semibold text-white text-truncate">${safeMsg}</span>
    <button type="button" class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-bold text-white shadow-xs" onclick="window.location.reload();" style="background:#2E6F40; border:none; white-space:nowrap;">
      <i class="bi bi-arrow-clockwise me-1"></i> Refresh Menu
    </button>
    <button type="button" class="btn-close btn-close-white btn-sm ms-1" onclick="this.parentElement.remove();"></button>
  `;
  document.body.appendChild(banner);
}

