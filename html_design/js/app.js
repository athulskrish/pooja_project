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
 * Save Cart to LocalStorage and trigger UI updates
 */
function saveCart() {
  localStorage.setItem('pooja_zippy_cart', JSON.stringify(cart));
  updateCartBadge();
  renderCartDrawer();
  renderDishQtyControls();
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
    if (totalCount > 0) {
      mobileCartBar.style.display = 'flex';
      const totalAmount = calculateCartTotals().finalTotal;
      document.getElementById('mobileCartItemsCount').textContent = `${totalCount} ITEM${totalCount > 1 ? 'S' : ''}`;
      document.getElementById('mobileCartTotal').textContent = `₹${totalAmount}`;
    } else {
      mobileCartBar.style.display = 'none';
    }
  }
}

/**
 * Add or Increment Item in Cart
 */
function addToCart(id, name, price, isVeg, restaurant = 'Meghana Foods') {
  const existingItem = cart.find(item => item.id === id);
  if (existingItem) {
    existingItem.qty += 1;
  } else {
    cart.push({
      id,
      name,
      price: Number(price),
      isVeg: Boolean(isVeg),
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
  const itemIndex = cart.findIndex(item => item.id === id);
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
  cart = cart.filter(item => item.id !== id);
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
 * Calculate Cart Totals
 */
function calculateCartTotals() {
  const itemTotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
  const deliveryFee = itemTotal > 500 || itemTotal === 0 ? 0 : 35;
  const platformFee = itemTotal > 0 ? 5 : 0;
  const taxes = Math.round(itemTotal * 0.05); // 5% GST
  
  let discount = 0;
  if (activeCoupon === 'LUSHFOREST') {
    discount = Math.round(itemTotal * 0.20); // 20% OFF
  } else if (activeCoupon === 'ZIPPY50') {
    discount = Math.min(Math.round(itemTotal * 0.50), 100); // 50% up to 100
  }

  const finalTotal = Math.max(0, itemTotal + deliveryFee + platformFee + taxes - discount);

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
  const container = document.getElementById('cartDrawerItems');
  const footerContainer = document.getElementById('cartDrawerFooter');
  if (!container) return;

  if (cart.length === 0) {
    container.innerHTML = `
      <div class="text-center py-5">
        <i class="bi bi-cart-x display-3 text-muted"></i>
        <h5 class="mt-3 fw-bold text-pine">Your cart is empty</h5>
        <p class="text-muted small">Good food is always just a few clicks away! Explore delicious dishes from top restaurants.</p>
        <a href="index.html" class="btn btn-outline-success rounded-pill px-4 fw-bold">Explore Food</a>
      </div>
    `;
    if (footerContainer) footerContainer.style.display = 'none';
    return;
  }

  if (footerContainer) footerContainer.style.display = 'block';

  let html = `
    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
      <span class="fw-bold small text-muted text-uppercase">Ordered Items (${cart.length})</span>
      <button class="btn btn-sm text-danger p-0 fw-bold small" onclick="clearCart()">Clear All</button>
    </div>
  `;

  cart.forEach(item => {
    html += `
      <div class="cart-item-card">
        <div class="d-flex align-items-center gap-2">
          <span class="food-type-icon ${item.isVeg ? 'veg' : 'non-veg'}"></span>
          <div>
            <h6 class="mb-0 fw-bold" style="font-size: 0.92rem;">${item.name}</h6>
            <span class="text-muted small">₹${item.price} each</span>
          </div>
        </div>
        <div class="d-flex align-items-center gap-3">
          <div class="dish-qty-control" style="width: 80px; padding: 2px 6px;">
            <button onclick="decrementCartItem('${item.id}')">-</button>
            <span class="qty-number">${item.qty}</span>
            <button onclick="addToCart('${item.id}', '${item.name}', ${item.price}, ${item.isVeg})">+</button>
          </div>
          <span class="fw-bold text-pine" style="min-width: 55px; text-align: right;">₹${item.price * item.qty}</span>
        </div>
      </div>
    `;
  });

  const totals = calculateCartTotals();
  html += `
    <div class="card p-3 border-0 bg-white rounded-3 shadow-sm mt-3">
      <h6 class="fw-bold text-pine mb-2">Bill Summary</h6>
      <div class="d-flex justify-content-between small text-muted py-1">
        <span>Item Total</span>
        <span>₹${totals.itemTotal}</span>
      </div>
      <div class="d-flex justify-content-between small text-muted py-1">
        <span>Delivery Fee</span>
        <span>${totals.deliveryFee === 0 ? '<span class="text-success fw-bold">FREE</span>' : '₹' + totals.deliveryFee}</span>
      </div>
      <div class="d-flex justify-content-between small text-muted py-1">
        <span>Platform Fee</span>
        <span>₹${totals.platformFee}</span>
      </div>
      <div class="d-flex justify-content-between small text-muted py-1">
        <span>GST & Restaurant Charges</span>
        <span>₹${totals.taxes}</span>
      </div>
      ${totals.discount > 0 ? `
      <div class="d-flex justify-content-between small text-success py-1 fw-bold">
        <span>Coupon Discount (${activeCoupon})</span>
        <span>-₹${totals.discount}</span>
      </div>
      ` : ''}
      <div class="d-flex justify-content-between fw-bold text-pine pt-2 mt-2 border-top">
        <span>To Pay</span>
        <span class="fs-5 text-success">₹${totals.finalTotal}</span>
      </div>
    </div>
  `;

  container.innerHTML = html;
}

/**
 * Render Dish Add / Quantity Controls on Menu Pages
 */
function renderDishQtyControls() {
  const wraps = document.querySelectorAll('.dish-add-btn-wrap');
  wraps.forEach(wrap => {
    const id = wrap.getAttribute('data-dish-id');
    const name = wrap.getAttribute('data-dish-name');
    const price = wrap.getAttribute('data-dish-price');
    const isVeg = wrap.getAttribute('data-dish-veg') === 'true';

    const item = cart.find(c => c.id === id);

    if (item && item.qty > 0) {
      wrap.innerHTML = `
        <div class="dish-qty-control">
          <button onclick="decrementCartItem('${id}')">-</button>
          <span class="qty-number">${item.qty}</span>
          <button onclick="addToCart('${id}', '${name}', ${price}, ${isVeg})">+</button>
        </div>
      `;
    } else {
      wrap.innerHTML = `
        <button class="dish-btn-add" onclick="addToCart('${id}', '${name}', ${price}, ${isVeg})">ADD</button>
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

  if (cart.length === 0) {
    cart = [
      { id: 'mgh-1', name: 'Meghana Special Chicken Biryani', price: 360, isVeg: false, restaurant: 'Meghana Foods', qty: 1 },
      { id: 'mgh-5', name: 'Clay Oven Butter Garlic Naan', price: 65, isVeg: true, restaurant: 'Meghana Foods', qty: 1 },
      { id: 'mgh-6', name: 'Shahi Gulab Jamun (2 Pcs)', price: 85, isVeg: true, restaurant: 'Meghana Foods', qty: 1 }
    ];
    saveCart();
  }

  document.getElementById('checkout-pay-btn').classList.remove('disabled');

  let itemsHtml = '';
  cart.forEach(item => {
    itemsHtml += `
      <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
        <div class="d-flex align-items-center gap-2">
          <span class="food-type-icon ${item.isVeg ? 'veg' : 'non-veg'}"></span>
          <div>
            <h6 class="mb-0 fw-bold small">${item.name}</h6>
            <span class="text-muted" style="font-size: 0.75rem;">₹${item.price} each</span>
          </div>
        </div>
        <div class="d-flex align-items-center gap-3">
          <div class="dish-qty-control" style="width: 75px; padding: 2px 4px;">
            <button onclick="decrementCartItem('${item.id}')">-</button>
            <span class="qty-number">${item.qty}</span>
            <button onclick="addToCart('${item.id}', '${item.name}', ${item.price}, ${item.isVeg})">+</button>
          </div>
          <span class="fw-bold text-pine" style="min-width: 60px; text-align: right;">₹${item.price * item.qty}</span>
        </div>
      </div>
    `;
  });

  container.innerHTML = itemsHtml;

  const totals = calculateCartTotals();
  document.getElementById('bill-item-total').textContent = `₹${totals.itemTotal}`;
  document.getElementById('bill-delivery-fee').innerHTML = totals.deliveryFee === 0 ? '<span class="text-success fw-bold">FREE</span>' : `₹${totals.deliveryFee}`;
  document.getElementById('bill-platform-fee').textContent = `₹${totals.platformFee}`;
  document.getElementById('bill-taxes').textContent = `₹${totals.taxes}`;
  
  const discountRow = document.getElementById('bill-discount-row');
  if (discountRow) {
    if (totals.discount > 0) {
      discountRow.style.display = 'flex';
      document.getElementById('bill-discount-amount').textContent = `-₹${totals.discount}`;
    } else {
      discountRow.style.display = 'none';
    }
  }

  document.getElementById('bill-final-total').textContent = `₹${totals.finalTotal}`;
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

  const currentPath = window.location.pathname.toLowerCase();
  const isHome = currentPath.endsWith('index.html') || currentPath.endsWith('/') || currentPath.endsWith('pooja_project');
  const isSearch = currentPath.includes('search.html');
  const isOffers = currentPath.includes('offers.html');
  const isOrders = currentPath.includes('order-history.html') || currentPath.includes('order-status.html');
  const isProfile = currentPath.includes('profile.html') || currentPath.includes('manage-addresses.html');

  const nav = document.createElement('nav');
  nav.className = 'mobile-bottom-nav d-lg-none';
  nav.setAttribute('aria-label', 'Mobile Bottom Navigation');

  nav.innerHTML = `
    <a href="index.html" class="mobile-nav-btn ${isHome ? 'active' : ''}">
      <i class="bi bi-house-door-fill"></i>
      <span>Home</span>
    </a>
    <a href="search.html" class="mobile-nav-btn ${isSearch ? 'active' : ''}">
      <i class="bi bi-search"></i>
      <span>Search</span>
    </a>
    <a href="offers.html" class="mobile-nav-btn ${isOffers ? 'active' : ''}">
      <i class="fa-solid fa-percent"></i>
      <span>Offers</span>
    </a>
    <a href="order-history.html" class="mobile-nav-btn ${isOrders ? 'active' : ''}">
      <i class="bi bi-clock-history"></i>
      <span>Orders</span>
    </a>
    <a href="profile.html" class="mobile-nav-btn ${isProfile ? 'active' : ''}">
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
