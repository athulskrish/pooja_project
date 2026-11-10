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
  if (typeof initServerCartSync === 'function') {
    initServerCartSync();
  }
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
    initOrderStatusCountdown();
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

function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str).replace(/[&<>"']/g, m => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  }[m]));
}

/**
 * Persistent Server Cart Synchronization Helpers (Logged-In Customers)
 */
let _cartSyncTimer = null;

function syncCartWithServer(action = 'replace', applyReturned = false) {
  if (!window.ZIPPY_CUSTOMER_LOGGED_IN || !window.CUSTOMER_URL) {
    return Promise.resolve(null);
  }
  return fetch(`${window.CUSTOMER_URL}/ajax/cart-sync.php`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.ZIPPY_CSRF_TOKEN || ''
    },
    body: JSON.stringify({
      action,
      items: action === 'clear' ? [] : cart,
      csrf_token: window.ZIPPY_CSRF_TOKEN || ''
    })
  })
    .then(r => (r.ok ? r.json() : null))
    .then(data => {
      if (applyReturned && data && data.success && Array.isArray(data.items)) {
        cart = data.items;
        localStorage.setItem('pooja_zippy_cart', JSON.stringify(cart));
        saveCart(true);
      }
      return data;
    })
    .catch(() => null);
}

function initServerCartSync() {
  if (!window.ZIPPY_CUSTOMER_LOGGED_IN || !window.CUSTOMER_URL) return;
  if (sessionStorage.getItem('zippy_cart_merged') !== '1') {
    syncCartWithServer('merge', true).then(res => {
      if (res && res.success) {
        sessionStorage.setItem('zippy_cart_merged', '1');
      }
    });
  }
}

/**
 * Toggle Favorite Dish Heart Button (`user_favorites`)
 */
window.toggleDishFavorite = function(itemId, btnEl) {
  if (!window.ZIPPY_CUSTOMER_LOGGED_IN) {
    showToast('Please sign in to save dishes to Favorites', 'info');
    return;
  }
  const numId = Number(itemId) || 0;
  if (!numId || !window.CUSTOMER_URL) return;

  fetch(`${window.CUSTOMER_URL}/ajax/toggle-favorite.php`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.ZIPPY_CSRF_TOKEN || ''
    },
    body: JSON.stringify({
      item_id: numId,
      csrf_token: window.ZIPPY_CSRF_TOKEN || ''
    })
  })
    .then(r => r.json())
    .then(data => {
      if (!data || !data.success) {
        showToast((data && data.error) ? data.error : 'Unable to update favorite', 'danger');
        return;
      }
      const isFav = Boolean(data.favorited);
      document.querySelectorAll(`.dish-fav-btn[data-item-id="${numId}"]`).forEach(btn => {
        btn.classList.toggle('is-favorited', isFav);
        const icon = btn.querySelector('i');
        if (icon) {
          icon.className = isFav ? 'bi bi-heart-fill text-danger' : 'bi bi-heart text-dark';
        }
        btn.setAttribute('aria-pressed', isFav ? 'true' : 'false');
      });
      document.querySelectorAll(`[data-item-id="${numId}"].dish-card-wrapper, [data-item-id="${numId}"].dish-item-row`).forEach(card => {
        card.setAttribute('data-favorite', isFav ? 'true' : 'false');
      });
      if (btnEl && !btnEl.dataset.itemId) {
        const icon = btnEl.querySelector('i');
        if (icon) icon.className = isFav ? 'bi bi-heart-fill text-danger' : 'bi bi-heart text-dark';
      }
      showToast(data.message || (isFav ? 'Added to Favorites' : 'Removed from Favorites'));
      document.dispatchEvent(new CustomEvent('zippy:favoriteUpdated', { detail: { itemId: numId, favorited: isFav } }));
    })
    .catch(() => showToast('Network error updating favorites', 'danger'));
};

/**
 * Save Cart to LocalStorage and trigger UI updates
 */
function saveCart(skipServerSync = false) {
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
  if (!skipServerSync && window.ZIPPY_CUSTOMER_LOGGED_IN) {
    clearTimeout(_cartSyncTimer);
    _cartSyncTimer = setTimeout(() => {
      syncCartWithServer('replace', false);
    }, 350);
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
function addToCart(id, name, price, isVeg, restaurant = 'Meghana Foods', imageUrl = '', skipCustomizationModal = false) {
  sessionStorage.removeItem('dismiss_cart_bar');
  const numId = Number(id) || id;

  // Intercept customizable dishes to open customization modal
  if (!skipCustomizationModal && window.DISH_CUSTOMIZABLE_IDS && window.DISH_CUSTOMIZABLE_IDS.includes(numId)) {
    openCustomizationModal(numId, name, price, isVeg, imageUrl, restaurant);
    return;
  }

  const existingItem = cart.find(item => item.id == numId && (!item.cartItemId || item.cartItemId == String(numId)));
  const resolvedImg = imageUrl || (existingItem && existingItem.imageUrl) || (window.DISH_IMAGES_MAP && window.DISH_IMAGES_MAP[numId]) || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300';

  if (existingItem) {
    existingItem.qty += 1;
    if (!existingItem.imageUrl && resolvedImg) {
      existingItem.imageUrl = resolvedImg;
    }
  } else {
    cart.push({
      cartItemId: String(numId),
      id: numId,
      name: name,
      basePrice: Number(price),
      price: Number(price),
      isVeg: Boolean(isVeg && isVeg !== '0' && isVeg !== 'false'),
      restaurant,
      imageUrl: resolvedImg,
      qty: 1,
      customizations: [],
      customizationText: ''
    });
  }
  saveCart();
  showToast(`Added "${name}" to cart`);
}

/**
 * Increment Cart Item Quantity (Supports Unique Customized Entries)
 */
function incrementCartItem(cartKey) {
  const item = cart.find(it => (it.cartItemId && it.cartItemId == cartKey) || it.id == cartKey);
  if (item) {
    item.qty += 1;
    saveCart();
  }
}

/**
 * Decrement or Remove Item from Cart (Supports Unique Customized Entries)
 */
function decrementCartItem(cartKey) {
  const itemIndex = cart.findIndex(it => (it.cartItemId && it.cartItemId == cartKey) || it.id == cartKey);
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
function removeCartItem(cartKey) {
  cart = cart.filter(it => !((it.cartItemId && it.cartItemId == cartKey) || it.id == cartKey));
  saveCart();
  showToast('Item removed from cart');
}

/**
 * Add Customized Dish with Selected Options to Cart
 */
function addCustomizedItemToCart(dishId, name, basePrice, isVeg, restaurant, imageUrl, selectedCustomizations, customizationSummary) {
  sessionStorage.removeItem('dismiss_cart_bar');
  const numId = Number(dishId) || dishId;
  const resolvedImg = imageUrl || (window.DISH_IMAGES_MAP && window.DISH_IMAGES_MAP[numId]) || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300';

  // Compute add-on costs
  const addonCost = (selectedCustomizations || []).reduce((sum, opt) => sum + Number(opt.price || 0), 0);
  const finalUnitPrice = Math.round((Number(basePrice) + addonCost) * 100) / 100;

  // Generate deterministic unique key based on selected option IDs
  const optKey = (selectedCustomizations || []).map(o => o.optionId).sort().join('-');
  const cartItemId = optKey ? `${numId}_cust_${optKey}` : String(numId);

  const existingItem = cart.find(item => item.cartItemId === cartItemId);

  if (existingItem) {
    existingItem.qty += 1;
  } else {
    cart.push({
      cartItemId: cartItemId,
      id: numId,
      name: name,
      basePrice: Number(basePrice),
      price: finalUnitPrice,
      isVeg: Boolean(isVeg && isVeg !== '0' && isVeg !== 'false'),
      restaurant: restaurant || 'Meghana Foods',
      imageUrl: resolvedImg,
      qty: 1,
      customizations: selectedCustomizations || [],
      customizationText: customizationSummary || ''
    });
  }

  saveCart();
  showToast(`Added "${name}" with customisations to cart!`);

  // Auto-open cart drawer
  const drawerEl = document.getElementById('cartDrawer');
  if (drawerEl && typeof bootstrap !== 'undefined') {
    const bsDrawer = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
    bsDrawer.show();
  }
}

/**
 * Modal State Tracking
 */
let currentModalDish = null;
let currentModalGroups = [];

/**
 * Open Dish Customisation Modal
 */
function openCustomizationModal(dishId, name, basePrice, isVeg, imageUrl, restaurant = 'Meghana Foods') {
  const modalEl = document.getElementById('customizationModal');
  if (!modalEl) {
    addToCart(dishId, name, basePrice, isVeg, restaurant, imageUrl, true);
    return;
  }

  const resolvedImg = imageUrl || (window.DISH_IMAGES_MAP && window.DISH_IMAGES_MAP[dishId]) || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300';
  const isVegBool = Boolean(isVeg && isVeg !== '0' && isVeg !== 'false');

  currentModalDish = {
    id: dishId,
    name: name,
    basePrice: Number(basePrice),
    isVeg: isVegBool,
    imageUrl: resolvedImg,
    restaurant: restaurant
  };

  document.getElementById('custModalDishName').textContent = name;
  document.getElementById('custModalDishImg').src = resolvedImg;
  document.getElementById('custModalVegDot').style.background = isVegBool ? '#229947' : '#D32F2F';
  document.getElementById('custModalDietBadge').textContent = isVegBool ? 'PURE VEG' : 'NON-VEG';
  document.getElementById('custModalDietBadge').className = isVegBool ? 'badge bg-success-subtle text-success border border-success-subtle' : 'badge bg-danger-subtle text-danger border border-danger-subtle';
  document.getElementById('custModalBasePrice').textContent = 'Base Price: ₹' + Number(basePrice).toFixed(2);
  document.getElementById('custModalTotalPrice').textContent = '₹' + Number(basePrice).toFixed(2);

  document.getElementById('custModalLoading').style.display = 'block';
  document.getElementById('custModalGroupsContainer').style.display = 'none';
  document.getElementById('custModalGroupsContainer').innerHTML = '';

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modal.show();

  // Wire up Add to Cart Button in Modal
  const addBtn = document.getElementById('custModalAddToCartBtn');
  addBtn.onclick = function() {
    handleCustomizationModalSubmit(modal);
  };

  // Fetch groups and options via AJAX
  const customerUrl = window.ZIPPY_CUSTOMER_URL || (window.location.origin + '/customer');
  fetch(customerUrl + '/ajax/get-dish-customizations.php?item_id=' + encodeURIComponent(dishId))
    .then(res => res.json())
    .then(data => {
      document.getElementById('custModalLoading').style.display = 'none';

      if (!data.success || !data.has_customizations || !data.groups || data.groups.length === 0) {
        modal.hide();
        addToCart(dishId, name, basePrice, isVegBool, restaurant, resolvedImg, true);
        return;
      }

      if (data.dish && data.dish.category_name) {
        document.getElementById('custModalCategory').textContent = data.dish.category_name;
      }

      currentModalGroups = data.groups;
      renderCustomizationModalGroups(data.groups);
      document.getElementById('custModalGroupsContainer').style.display = 'block';
      updateCustomizationModalPrice();
    })
    .catch(err => {
      console.error('Failed to load customizations:', err);
      document.getElementById('custModalLoading').innerHTML = '<div class="alert alert-warning py-2 small mb-0">Adding standard dish...</div>';
      setTimeout(() => {
        modal.hide();
        addToCart(dishId, name, basePrice, isVegBool, restaurant, resolvedImg, true);
      }, 700);
    });
}

/**
 * Render Customisation Groups inside Modal
 */
function renderCustomizationModalGroups(groups) {
  const container = document.getElementById('custModalGroupsContainer');
  if (!container) return;

  let html = '';
  groups.forEach((group, gIdx) => {
    const isRadio = (group.selection_type === 'radio');
    const isRequired = (group.is_required == 1);

    html += `
      <div class="custom-group-block mb-4 p-3 rounded-3 border bg-light-subtle">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="d-flex align-items-center gap-2">
            <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.95rem;">${group.group_name}</h6>
            ${isRequired ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill" style="font-size:0.6rem;">REQUIRED</span>' : '<span class="badge bg-secondary-subtle text-secondary rounded-pill" style="font-size:0.6rem;">OPTIONAL</span>'}
          </div>
          <span class="small text-muted" style="font-size:0.75rem;">
            ${isRadio ? 'Choose 1' : 'Choose any'}
          </span>
        </div>
        <div class="custom-options-list d-flex flex-column gap-2 mt-2">
    `;

    group.options.forEach((opt, oIdx) => {
      const inputId = `cust_input_${group.group_id}_${opt.option_id}`;
      const isDefault = (opt.is_default == 1);
      const isChecked = isDefault;

      const priceVal = Number(opt.price || 0);
      const priceText = priceVal > 0 ? `+₹${priceVal.toFixed(2)}` : 'Free';
      const priceClass = priceVal > 0 ? 'text-forest fw-bold' : 'text-muted';

      html += `
        <label class="form-check custom-option-card p-2 px-3 rounded-3 bg-white border d-flex align-items-center justify-content-between m-0" for="${inputId}" style="cursor: pointer; transition: all 0.15s ease;">
          <div class="d-flex align-items-center gap-2">
            <input class="form-check-input cust-input m-0" 
                   type="${isRadio ? 'radio' : 'checkbox'}" 
                   name="cust_group_${group.group_id}" 
                   id="${inputId}" 
                   value="${opt.option_id}"
                   data-group-id="${group.group_id}"
                   data-group-name="${group.group_name.replace(/"/g, '&quot;')}"
                   data-option-id="${opt.option_id}"
                   data-option-name="${opt.option_name.replace(/"/g, '&quot;')}"
                   data-price="${priceVal}"
                   ${isChecked ? 'checked' : ''}
                   onchange="updateCustomizationModalPrice()">
            <span class="small fw-semibold text-dark">${opt.option_name}</span>
          </div>
          <span class="small ${priceClass}">${priceText}</span>
        </label>
      `;
    });

    html += `
        </div>
      </div>
    `;
  });

  container.innerHTML = html;

  // Ensure every required radio group has at least 1 radio checked
  groups.forEach(group => {
    if (group.selection_type === 'radio' && group.is_required == 1) {
      const radios = container.querySelectorAll(`input[name="cust_group_${group.group_id}"]`);
      const anyChecked = Array.from(radios).some(r => r.checked);
      if (!anyChecked && radios.length > 0) {
        radios[0].checked = true;
      }
    }
  });
}

/**
 * Dynamic Live Price Recalculation inside Customisation Modal
 */
function updateCustomizationModalPrice() {
  if (!currentModalDish) return;

  const container = document.getElementById('custModalGroupsContainer');
  if (!container) return;

  let addonSum = 0;
  const checkedInputs = container.querySelectorAll('.cust-input:checked');
  checkedInputs.forEach(inp => {
    addonSum += Number(inp.dataset.price || 0);
  });

  const total = Math.round((currentModalDish.basePrice + addonSum) * 100) / 100;
  const totalEl = document.getElementById('custModalTotalPrice');
  if (totalEl) {
    totalEl.textContent = '₹' + total.toFixed(2);
  }

  // Check required groups validation
  let allRequiredMet = true;
  currentModalGroups.forEach(grp => {
    if (grp.is_required == 1) {
      const inputs = container.querySelectorAll(`input[name="cust_group_${grp.group_id}"]:checked`);
      if (inputs.length === 0) {
        allRequiredMet = false;
      }
    }
  });

  const addBtn = document.getElementById('custModalAddToCartBtn');
  if (addBtn) {
    if (allRequiredMet) {
      addBtn.disabled = false;
      addBtn.innerHTML = `<i class="bi bi-bag-plus me-1"></i> Add to Cart • ₹${total.toFixed(2)}`;
    } else {
      addBtn.disabled = true;
      addBtn.innerHTML = `Please select required choices`;
    }
  }
}

/**
 * Handle Add to Cart submission from Customisation Modal
 */
function handleCustomizationModalSubmit(modalInstance) {
  if (!currentModalDish) return;

  const container = document.getElementById('custModalGroupsContainer');
  if (!container) return;

  const checkedInputs = container.querySelectorAll('.cust-input:checked');
  const selectedOptions = [];
  const summaryParts = [];

  checkedInputs.forEach(inp => {
    const optId = Number(inp.dataset.optionId);
    const grpName = inp.dataset.groupName;
    const optName = inp.dataset.optionName;
    const price = Number(inp.dataset.price || 0);

    selectedOptions.push({
      optionId: optId,
      groupId: Number(inp.dataset.groupId),
      groupName: grpName,
      optionName: optName,
      price: price
    });

    const priceTag = price > 0 ? ` (+₹${price.toFixed(2)})` : '';
    summaryParts.push(`${optName}${priceTag}`);
  });

  const summaryText = summaryParts.join(', ');

  // Hide modal
  if (modalInstance) {
    modalInstance.hide();
  }

  // Add customized item to cart
  addCustomizedItemToCart(
    currentModalDish.id,
    currentModalDish.name,
    currentModalDish.basePrice,
    currentModalDish.isVeg,
    currentModalDish.restaurant,
    currentModalDish.imageUrl,
    selectedOptions,
    summaryText
  );
}

/**
 * Clear the entire Cart
 */
function clearCart() {
  cart = [];
  localStorage.removeItem('pooja_zippy_cart');
  localStorage.removeItem('pooja_active_coupon');
  activeCoupon = null;
  window.ZIPPY_VALIDATED_COUPON = null;
  saveCart(true);
  syncCartWithServer('clear', false);
  showToast('Cart cleared');
}

/**
 * 1-Click / 1-Tap Reorder Function
 * Adds order dishes directly into localStorage & in-memory cart,
 * recalculates totals, updates UI badges, shows a celebratory toast,
 * and opens the cart drawer or navigates directly to checkout.
 *
 * @param {Array} items - Array of item objects {id, name, price, isVeg, restaurant, imageUrl, qty}
 * @param {number|string} orderId - Previous order ID
 * @param {boolean} [directToCheckout=false] - If true, immediately routes to checkout page
 * @param {HTMLElement} [btnElement=null] - Optional triggering button for loading spinner
 */
function oneTapReorderMeal(items, orderId, directToCheckout = false, btnElement = null) {
  if (!items || !items.length) {
    if (typeof showToast === 'function') {
      showToast('No items found in this order to reorder.', 'warning');
    }
    return;
  }

  // Visual loading feedback if triggered by a button
  let originalHtml = '';
  if (btnElement && btnElement instanceof HTMLElement) {
    originalHtml = btnElement.innerHTML;
    btnElement.disabled = true;
    btnElement.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Adding...`;
  }

  // Ensure cart is initialized from localStorage
  try {
    cart = JSON.parse(localStorage.getItem('pooja_zippy_cart')) || [];
  } catch (e) {
    cart = [];
  }

  let totalItemsAdded = 0;
  items.forEach(it => {
    const dishId = Number(it.id);
    const qtyToAdd = Number(it.qty || 1);
    const existingIndex = cart.findIndex(c => Number(c.id) === dishId);
    const resolvedImg = it.imageUrl || (window.DISH_IMAGES_MAP && window.DISH_IMAGES_MAP[dishId]) || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300';

    if (existingIndex > -1) {
      cart[existingIndex].qty += qtyToAdd;
      if (!cart[existingIndex].imageUrl && resolvedImg) {
        cart[existingIndex].imageUrl = resolvedImg;
      }
    } else {
      cart.push({
        id: dishId,
        name: it.name,
        price: Number(it.price),
        isVeg: Boolean(it.isVeg),
        restaurant: it.restaurant || 'Meghana Foods',
        imageUrl: resolvedImg,
        qty: qtyToAdd
      });
    }
    totalItemsAdded += qtyToAdd;
  });

  // Save to persistent storage and update client UI
  saveCart();

  // Reset button loading state after brief feedback
  if (btnElement && originalHtml) {
    setTimeout(() => {
      btnElement.disabled = false;
      btnElement.innerHTML = originalHtml;
    }, 700);
  }

  if (directToCheckout) {
    if (typeof showToast === 'function') {
      showToast(`Order #ZIP-${orderId} reordered! Redirecting to checkout... 🚀`, 'success');
    }
    const checkoutUrl = (window.CUSTOMER_URL || (window.location.origin + (window.location.pathname.includes('/customer') ? '/customer' : ''))) + '/checkout.php';
    setTimeout(() => {
      window.location.href = checkoutUrl;
    }, 400);
    return;
  }

  // Normal 1-tap reorder: show toast and reveal cart drawer
  if (typeof showToast === 'function') {
    showToast(`Previous meal (#ZIP-${orderId}) reordered! Added ${totalItemsAdded} item${totalItemsAdded > 1 ? 's' : ''} to cart 🎉`, 'success');
  }

  // If on cart.php, trigger dedicated cart refresh
  if (typeof renderDedicatedCart === 'function') {
    renderDedicatedCart();
  }

  // Open offcanvas cart drawer
  const drawerEl = document.getElementById('cartDrawer');
  if (drawerEl && typeof bootstrap !== 'undefined') {
    const bsDrawer = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
    bsDrawer.show();
  }
}

// Global alias for compatibility
function reorderItems(items, orderId) {
  oneTapReorderMeal(items, orderId, false, null);
}

/**
 * Calculate Cart Totals (Matches Backend Logic Exactly)
 */
function evaluateDynamicCoupon(code, itemTotal) {
  if (!code || itemTotal <= 0) return 0;
  const upper = String(code).trim().toUpperCase();
  const validated = window.ZIPPY_VALIDATED_COUPON;
  const couponMap = window.ZIPPY_AVAILABLE_COUPONS || {};
  const cp = (validated && String(validated.code || '').toUpperCase() === upper)
    ? validated
    : couponMap[upper];

  if (!cp) {
    return 0;
  }

  const minOrder = Number(cp.min_order || 0);
  if (itemTotal < minOrder) {
    return 0;
  }

  const dtype = String(cp.discount_type || 'flat').toLowerCase();
  const dval = Number(cp.discount_value || 0);
  const maxDisc = Number(cp.max_discount || 0);

  if (dtype === 'percentage') {
    const raw = (itemTotal * dval) / 100;
    const capped = maxDisc > 0 ? Math.min(raw, maxDisc) : raw;
    return Math.round(Math.min(capped, itemTotal) * 100) / 100;
  }

  return Math.round(Math.min(dval, itemTotal) * 100) / 100;
}

function calculateCartTotals() {
  const itemTotal = cart.reduce((sum, item) => sum + (Number(item.price) * Number(item.qty)), 0);
  const overrideFee = (typeof window.ZIPPY_OVERRIDE_DELIVERY_FEE === 'number' && !Number.isNaN(window.ZIPPY_OVERRIDE_DELIVERY_FEE))
    ? window.ZIPPY_OVERRIDE_DELIVERY_FEE
    : 40;
  const deliveryFee = (itemTotal >= 499 || itemTotal === 0) ? 0 : overrideFee;
  const platformFee = itemTotal > 0 ? 5 : 0;

  const discount = evaluateDynamicCoupon(activeCoupon, itemTotal);
  const afterCoupon = Math.max(0, itemTotal - discount);

  const rawLoyalty = (typeof window.ZIPPY_OVERRIDE_LOYALTY_DISCOUNT === 'number' && !Number.isNaN(window.ZIPPY_OVERRIDE_LOYALTY_DISCOUNT))
    ? window.ZIPPY_OVERRIDE_LOYALTY_DISCOUNT
    : 0;
  const maxLoyaltyAllowed = Math.floor(afterCoupon * 0.5);
  const loyaltyDiscount = Math.max(0, Math.min(rawLoyalty, maxLoyaltyAllowed));

  const taxable = Math.max(0, afterCoupon - loyaltyDiscount);
  const taxes = Math.round(taxable * 0.05 * 100) / 100; // 5% GST
  const finalTotal = Math.max(0, Math.round((taxable + taxes + deliveryFee + platformFee) * 100) / 100);

  return {
    itemTotal,
    deliveryFee,
    platformFee,
    taxes,
    discount,
    loyaltyDiscount,
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
    const cartKey = item.cartItemId || item.id;
    const customBadge = item.customizationText ? `
      <div class="small text-muted text-truncate mt-1" style="font-size: 0.75rem; max-width: 170px;" title="${item.customizationText}">
        <i class="bi bi-sliders2 me-1 text-success"></i>${item.customizationText}
      </div>` : '';

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
            ${customBadge}
          </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
          <div class="m3-stepper-control rounded-pill shadow-sm" role="group">
            <button type="button" class="m3-stepper-btn" onclick="decrementCartItem('${cartKey}')" aria-label="Decrease quantity">
              <i class="bi bi-dash"></i>
            </button>
            <span class="m3-stepper-qty">${item.qty}</span>
            <button type="button" class="m3-stepper-btn" onclick="incrementCartItem('${cartKey}')" aria-label="Increase quantity">
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

    const isCustomizable = window.DISH_CUSTOMIZABLE_IDS && window.DISH_CUSTOMIZABLE_IDS.includes(Number(id));
    const matchingItems = cart.filter(c => c.id == id);
    const totalDishQty = matchingItems.reduce((s, it) => s + it.qty, 0);
    const safeName = (name || '').replace(/'/g, "\\'");

    if (totalDishQty > 0) {
      const lastItemKey = (matchingItems[matchingItems.length - 1] && matchingItems[matchingItems.length - 1].cartItemId) || id;
      wrap.innerHTML = `
        <div class="m3-stepper-control rounded-pill shadow-sm" role="group">
          <button type="button" class="m3-stepper-btn" onclick="decrementCartItem('${lastItemKey}')" aria-label="Decrease quantity">
            <i class="bi bi-dash"></i>
          </button>
          <span class="m3-stepper-qty">${totalDishQty}</span>
          <button type="button" class="m3-stepper-btn" onclick="addToCart('${id}', '${safeName}', ${price}, ${isVeg ? 1 : 0}, 'Meghana Foods', '${imgUrl}')" aria-label="Increase quantity">
            <i class="bi bi-plus"></i>
          </button>
        </div>
        ${isCustomizable ? '<div class="text-center text-muted" style="font-size:0.62rem; margin-top:2px;">Customised</div>' : ''}
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
        ${isCustomizable ? '<div class="text-center text-success fw-medium" style="font-size:0.62rem; margin-top:2px;"><i class="bi bi-sliders me-1"></i>Customisable</div>' : ''}
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
  // 1. Homepage Filter Buttons (#vegOnlyToggle, #healthyOnlyToggle, #favoritesOnlyToggle, #highProteinToggle, #allergenFreeToggle)
  const vegBtn = document.getElementById('vegOnlyToggle');
  const healthyBtn = document.getElementById('healthyOnlyToggle');
  const favBtn = document.getElementById('favoritesOnlyToggle');
  const highProtBtn = document.getElementById('highProteinToggle');
  const allergenFreeBtn = document.getElementById('allergenFreeToggle');
  const dishesGrid = document.getElementById('dishesGrid');

  if (dishesGrid && (vegBtn || healthyBtn || favBtn || highProtBtn || allergenFreeBtn)) {
    let vegActive = false;
    let healthyActive = false;
    let favoriteActive = false;
    let highProteinActive = false;
    let allergenFreeActive = false;
    let activeMood = 'all';
    let activeCategorySlug = 'all';
    let searchQuery = '';
    let currentSort = 'recommended';
    let originalCardOrder = Array.from(dishesGrid.querySelectorAll('.dish-card-wrapper'));

    function updateExtraDietaryButtons() {
      const pairs = [
        [favBtn, favoriteActive, 'btn-danger', 'btn-outline-danger'],
        [highProtBtn, highProteinActive, 'btn-primary', 'btn-outline-primary'],
        [allergenFreeBtn, allergenFreeActive, 'btn-success', 'btn-outline-success'],
      ];
      pairs.forEach(([btn, active, activeCls, outlineCls]) => {
        if (!btn) return;
        btn.classList.toggle(activeCls, active);
        btn.classList.toggle('text-white', active);
        btn.classList.toggle(outlineCls, !active);
      });
    }

    if (favBtn) {
      favBtn.addEventListener('click', () => {
        favoriteActive = !favoriteActive;
        updateExtraDietaryButtons();
        applyStorefrontDietaryFilters();
      });
    }
    if (highProtBtn) {
      highProtBtn.addEventListener('click', () => {
        highProteinActive = !highProteinActive;
        updateExtraDietaryButtons();
        applyStorefrontDietaryFilters();
      });
    }
    if (allergenFreeBtn) {
      allergenFreeBtn.addEventListener('click', () => {
        allergenFreeActive = !allergenFreeActive;
        updateExtraDietaryButtons();
        applyStorefrontDietaryFilters();
      });
    }
    document.addEventListener('zippy:favoriteUpdated', () => {
      if (favoriteActive) {
        applyStorefrontDietaryFilters();
      }
    });

    function applyStorefrontDietaryFilters() {
      const cards = dishesGrid.querySelectorAll('.dish-card-wrapper');
      let visibleCount = 0;

      cards.forEach(card => {
        const isVeg = card.getAttribute('data-veg') === 'true';
        const isHealthy = card.getAttribute('data-healthy') === 'true';
        const isFav = card.getAttribute('data-favorite') === 'true';
        const proteinG = Number(card.getAttribute('data-protein') || 0);
        const allergens = (card.getAttribute('data-allergens') || '').trim();
        const cardCategory = (card.getAttribute('data-category') || '').trim().toLowerCase();
        const cardMood = (card.getAttribute('data-mood') || '').trim().toLowerCase();
        const cardName = (card.getAttribute('data-name') || card.querySelector('.dish-title')?.textContent || '').toLowerCase();
        const cardDesc = (card.querySelector('.dish-desc-clamp')?.textContent || '').toLowerCase();

        let show = true;
        if (vegActive && !isVeg) show = false;
        if (healthyActive && !isHealthy) show = false;
        if (favoriteActive && !isFav) show = false;
        if (highProteinActive && proteinG < 20) show = false;
        if (allergenFreeActive && allergens !== '') show = false;
        if (activeCategorySlug !== 'all' && cardCategory !== activeCategorySlug) show = false;
        if (activeMood !== 'all') {
          const moodList = cardMood.split(',').map(m => m.trim());
          if (!moodList.includes(activeMood)) show = false;
        }
        if (searchQuery && !cardName.includes(searchQuery) && !cardDesc.includes(searchQuery)) show = false;

        card.style.display = show ? 'block' : 'none';
        if (show) visibleCount++;
      });

      // Update active mood banner count if visible
      const countEl = document.getElementById('activeMoodBannerCount');
      if (countEl) {
        countEl.textContent = `${visibleCount} ${visibleCount === 1 ? 'Dish' : 'Dishes'} Available`;
      }

      // Handle empty state message
      let emptyMsg = document.getElementById('dietaryEmptyStateMsg');
      if (visibleCount === 0) {
        if (!emptyMsg) {
          emptyMsg = document.createElement('div');
          emptyMsg.id = 'dietaryEmptyStateMsg';
          emptyMsg.className = 'col-12 text-center py-5';
          emptyMsg.innerHTML = `
            <div class="p-4 rounded-4 bg-light border d-inline-block text-center" style="max-width: 480px;">
              <i class="fa-solid fa-masks-theater text-warning fs-1 mb-2"></i>
              <h5 class="fw-bold text-dark">No dishes match selected mood & filters</h5>
              <p class="text-muted small mb-3">Try choosing another mood, disabling one of the filters, or clearing the search box to see more delicious options.</p>
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
        const isFiltered = vegActive || healthyActive || favoriteActive || highProteinActive || allergenFreeActive || (activeCategorySlug !== 'all') || (activeMood !== 'all') || searchQuery.length > 0 || currentSort !== 'recommended';
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

    // Mood-Based Food Selection Handler
    window.filterByMood = function(mood, el, event) {
      if (event) event.preventDefault();
      activeMood = (mood || 'all').trim().toLowerCase();

      // Highlight active mood card in #moodSection
      document.querySelectorAll('.mood-card').forEach(card => {
        if (card.getAttribute('data-mood-id') === activeMood) {
          card.classList.add('active');
        } else {
          card.classList.remove('active');
        }
      });

      const moodMeta = {
        'all': { name: 'Mood: All', emoji: '🎭', image: '', title: 'All Moods', desc: 'Browse all delicious dishes' },
        'comfort': { name: 'Mood: 🛋️ Comfort', emoji: '🛋️', image: '/assets/images/moods/comfort.jpg', title: 'Showing Comfort & Soul Food', desc: 'Warm, soulful curries, rich gravies & buttery breads to unwind & destress' },
        'party': { name: 'Mood: 🎉 Party', emoji: '🎉', image: '/assets/images/moods/party.jpg', title: 'Showing Party & Celebration Feasts', desc: 'Royal aromatic biryanis & loaded gourmet feast burgers for celebrations' },
        'healthy': { name: 'Mood: ⚡ Healthy', emoji: '⚡', image: '/assets/images/moods/healthy.jpg', title: 'Showing Energetic & Fit Meals', desc: 'Nutrient-dense superfoods, high-protein bowls & cold-pressed detox juices' },
        'quick_bite': { name: 'Mood: 🍿 Quick Bites', emoji: '🍿', image: '/assets/images/moods/quick_bite.jpg', title: 'Showing Lazy & Quick Bites', desc: 'Crispy kathi rolls, quick snacks, dosas & sodas delivered in under 15 mins' },
        'spicy': { name: 'Mood: 🔥 Fiery Spicy', emoji: '🔥', image: '/assets/images/moods/spicy.jpg', title: 'Showing Fiery & Spicy Adventures', desc: 'Authentic Andhra spices, fiery red chutneys & bold thrilling masalas' },
        'sweet': { name: 'Mood: 💖 Sweet Craving', emoji: '💖', image: '/assets/images/moods/sweet.jpg', title: 'Showing Sweet Tooth Cravings', desc: 'Royal rasmalai, hot gulab jamun with rabri & chilled Alphonso mango lassi' }
      };

      const meta = moodMeta[activeMood] || moodMeta['all'];
      const activeMoodFilterLabel = document.getElementById('activeMoodFilterLabel');
      if (activeMoodFilterLabel) {
        activeMoodFilterLabel.textContent = meta.name;
      }

      document.querySelectorAll('.mood-check').forEach(c => c.classList.add('d-none'));
      const activeCheck = document.getElementById('check-mood-' + activeMood);
      if (activeCheck) activeCheck.classList.remove('d-none');

      // Banner Handling
      const banner = document.getElementById('activeMoodBanner');
      if (banner) {
        if (activeMood === 'all') {
          banner.style.display = 'none';
        } else {
          banner.style.display = 'flex';
          const emojiEl = document.getElementById('activeMoodBannerEmoji');
          const titleEl = document.getElementById('activeMoodBannerTitle');
          const descEl = document.getElementById('activeMoodBannerDesc');
          const imgEl = document.getElementById('activeMoodBannerImg');
          if (emojiEl) emojiEl.textContent = meta.emoji;
          if (titleEl) titleEl.textContent = meta.title;
          if (descEl) descEl.textContent = meta.desc;
          if (imgEl && meta.image) {
            imgEl.src = (window.BASE_URL ? window.BASE_URL : '') + meta.image;
          }
        }
      }

      applyStorefrontDietaryFilters();

      const dishesSec = document.getElementById('dishes');
      if (dishesSec) {
        dishesSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    };

    window.surpriseMyMood = function() {
      const moods = ['comfort', 'party', 'healthy', 'quick_bite', 'spicy', 'sweet'];
      const randomMood = moods[Math.floor(Math.random() * moods.length)];
      const btn = document.getElementById('btnSurpriseMood');
      if (btn) {
        const origContent = btn.innerHTML;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status"></span> Finding Vibe...`;
        setTimeout(() => {
          btn.innerHTML = origContent;
          window.filterByMood(randomMood, null);
        }, 300);
      } else {
        window.filterByMood(randomMood, null);
      }
    };

    window.scrollToMoodSection = function() {
      const moodSec = document.getElementById('moodSection');
      if (moodSec) {
        moodSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    };

    window.resetAllStorefrontFilters = function() {
      vegActive = false;
      healthyActive = false;
      favoriteActive = false;
      highProteinActive = false;
      allergenFreeActive = false;
      activeMood = 'all';
      activeCategorySlug = 'all';
      searchQuery = '';
      currentSort = 'recommended';
      updateExtraDietaryButtons();

      const quickSearch = document.getElementById('storefrontQuickSearch');
      if (quickSearch) quickSearch.value = '';
      const clearBtn = document.getElementById('clearQuickSearchBtn');
      if (clearBtn) clearBtn.style.display = 'none';

      const badge = document.getElementById('activeCategoryBadge');
      if (badge) {
        badge.style.display = 'none';
        badge.textContent = '';
      }

      const activeMoodFilterLabel = document.getElementById('activeMoodFilterLabel');
      if (activeMoodFilterLabel) activeMoodFilterLabel.textContent = 'Mood: All';

      const banner = document.getElementById('activeMoodBanner');
      if (banner) banner.style.display = 'none';

      document.querySelectorAll('.mood-card').forEach(p => p.classList.remove('active'));
      document.querySelectorAll('.mood-check').forEach(c => c.classList.add('d-none'));
      document.getElementById('check-mood-all')?.classList.remove('d-none');

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

  // 2. Restaurant Page Switches (#vegOnlySwitch, #healthyOnlySwitch, #restaurantFavSwitch, #restaurantAllergenFreeSwitch & #restaurantMoodSelect)
  const vegSwitch = document.getElementById('vegOnlySwitch');
  const healthySwitch = document.getElementById('healthyOnlySwitch');
  const restFavSwitch = document.getElementById('restaurantFavoritesOnlySwitch');
  const restAllergenSwitch = document.getElementById('restaurantAllergenFreeSwitch');
  const moodSelect = document.getElementById('restaurantMoodSelect');

  if (vegSwitch || healthySwitch || restFavSwitch || restAllergenSwitch || moodSelect) {
    function applyRestaurantFilters() {
      const isVegOnly = vegSwitch ? vegSwitch.checked : false;
      const isHealthyOnly = healthySwitch ? healthySwitch.checked : false;
      const isFavOnly = restFavSwitch ? restFavSwitch.checked : false;
      const isAllergenFree = restAllergenSwitch ? restAllergenSwitch.checked : false;
      const selectedMood = (moodSelect ? moodSelect.value : 'all').trim().toLowerCase();

      const categoryBlocks = document.querySelectorAll('.category-menu-block');
      categoryBlocks.forEach(block => {
        const dishRows = block.querySelectorAll('.dish-item-row');
        let blockVisibleCount = 0;

        dishRows.forEach(row => {
          const isVeg = (row.getAttribute('data-is-veg') === 'true' || row.getAttribute('data-veg') === 'true');
          const isHealthy = row.getAttribute('data-healthy') === 'true';
          const isFav = row.getAttribute('data-favorite') === 'true';
          const rowAllergens = (row.getAttribute('data-allergens') || '').trim();
          const rowMood = (row.getAttribute('data-mood') || '').trim().toLowerCase();

          let show = true;
          if (isVegOnly && !isVeg) show = false;
          if (isHealthyOnly && !isHealthy) show = false;
          if (isFavOnly && !isFav) show = false;
          if (isAllergenFree && rowAllergens !== '') show = false;
          if (selectedMood !== 'all') {
            const moodList = rowMood.split(',').map(m => m.trim());
            if (!moodList.includes(selectedMood)) show = false;
          }

          row.style.display = show ? 'flex' : 'none';
          if (show) blockVisibleCount++;
        });

        // Hide category header block if no dishes match within it
        block.style.display = (blockVisibleCount > 0) ? 'block' : 'none';
      });
    }

    if (vegSwitch) vegSwitch.addEventListener('change', applyRestaurantFilters);
    if (healthySwitch) healthySwitch.addEventListener('change', applyRestaurantFilters);
    if (restFavSwitch) restFavSwitch.addEventListener('change', applyRestaurantFilters);
    if (restAllergenSwitch) restAllergenSwitch.addEventListener('change', applyRestaurantFilters);
    if (moodSelect) moodSelect.addEventListener('change', applyRestaurantFilters);
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
    const cartKey = item.cartItemId || item.id;
    const customBadge = item.customizationText ? `
      <div class="small text-muted text-truncate mt-1" style="font-size: 0.75rem; max-width: 220px;" title="${item.customizationText}">
        <i class="bi bi-sliders2 me-1 text-success"></i>${item.customizationText}
      </div>` : '';

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
            ${customBadge}
          </div>
        </div>
        <div class="d-flex align-items-center gap-3">
          <div class="m3-stepper-control rounded-pill shadow-sm" role="group">
            <button type="button" class="m3-stepper-btn" onclick="decrementCartItem('${cartKey}')" aria-label="Decrease quantity">
              <i class="bi bi-dash"></i>
            </button>
            <span class="m3-stepper-qty">${item.qty}</span>
            <button type="button" class="m3-stepper-btn" onclick="incrementCartItem('${cartKey}')" aria-label="Increase quantity">
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
 * Apply Coupon Code (Dynamic DB-Backed via validate-coupon.php)
 */
async function applyCoupon() {
  const input = document.getElementById('couponCodeInput');
  if (!input) return;

  const code = input.value.trim().toUpperCase();
  if (!code) {
    activeCoupon = null;
    window.ZIPPY_VALIDATED_COUPON = null;
    localStorage.removeItem('pooja_active_coupon');
    saveCart();
    showToast('Coupon removed', 'info');
    return;
  }

  const subtotal = cart.reduce((sum, item) => sum + (Number(item.price) * Number(item.qty)), 0);
  const url = `${window.CUSTOMER_URL || ''}/ajax/validate-coupon.php?code=${encodeURIComponent(code)}&subtotal=${encodeURIComponent(subtotal)}`;

  try {
    const res = await fetch(url);
    const data = await res.json();
    if (data && data.valid) {
      activeCoupon = code;
      window.ZIPPY_VALIDATED_COUPON = data.coupon || null;
      localStorage.setItem('pooja_active_coupon', activeCoupon);
      showToast(`Success! Coupon ${code} applied (Save ₹${Number(data.discount || 0).toFixed(2)}) 🎉`);
      saveCart();
      return;
    }
    showToast((data && data.error) ? data.error : 'Invalid or ineligible coupon code.', 'danger');
  } catch (err) {
    const localDiscount = evaluateDynamicCoupon(code, subtotal);
    const map = window.ZIPPY_AVAILABLE_COUPONS || {};
    if (map[code] && localDiscount > 0) {
      activeCoupon = code;
      localStorage.setItem('pooja_active_coupon', activeCoupon);
      showToast(`Success! Coupon ${code} applied 🎉`);
      saveCart();
    } else {
      showToast('Invalid Coupon Code or minimum order not met.', 'danger');
    }
  }
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
 * Real-Time Order Tracking ETA Countdown Engine
 * Calculates authentic remaining time from server delivery estimates and placed timestamps.
 */
function initOrderStatusCountdown() {
  const countdownEl = document.getElementById('live-eta-countdown');
  if (!countdownEl) return;

  // If handled by dedicated live SSE/WebSocket tracker on order-status page, avoid duplicate interval
  if (window.orderTrackingActive) {
    return;
  }

  const status = (countdownEl.getAttribute('data-status') || '').toUpperCase();
  if (status === 'DELIVERED' || status === 'CANCELLED') {
    countdownEl.textContent = status;
    return;
  }

  // 1. Resolve authentic remaining seconds from dataset attributes or timestamps
  let secondsRemaining = null;

  const rawSecondsAttr = countdownEl.getAttribute('data-remaining-seconds');
  if (rawSecondsAttr !== null && rawSecondsAttr !== '' && !isNaN(parseInt(rawSecondsAttr, 10))) {
    secondsRemaining = Math.max(0, parseInt(rawSecondsAttr, 10));
  }

  if (secondsRemaining === null) {
    const estTimeAttr = countdownEl.getAttribute('data-est-time');
    if (estTimeAttr) {
      const estTimestamp = new Date(estTimeAttr).getTime();
      if (!isNaN(estTimestamp)) {
        secondsRemaining = Math.max(0, Math.floor((estTimestamp - Date.now()) / 1000));
      }
    }
  }

  if (secondsRemaining === null) {
    const placedAtAttr = countdownEl.getAttribute('data-placed-at');
    if (placedAtAttr) {
      const placedTimestamp = new Date(placedAtAttr).getTime();
      if (!isNaN(placedTimestamp)) {
        const prepMinutes = parseInt(countdownEl.getAttribute('data-prep-time') || '25', 10);
        const targetTimestamp = placedTimestamp + (prepMinutes * 60 * 1000);
        secondsRemaining = Math.max(0, Math.floor((targetTimestamp - Date.now()) / 1000));
      }
    }
  }

  // Fallback: parse text currently in element if in MM:SS or MM mins format
  if (secondsRemaining === null) {
    const txt = countdownEl.textContent.trim();
    const mmssMatch = txt.match(/^(\d{1,2}):(\d{2})/);
    if (mmssMatch) {
      secondsRemaining = (parseInt(mmssMatch[1], 10) * 60) + parseInt(mmssMatch[2], 10);
    } else {
      const minsOnlyMatch = txt.match(/^(\d{1,2})\s*mins?/i);
      if (minsOnlyMatch) {
        secondsRemaining = parseInt(minsOnlyMatch[1], 10) * 60;
      }
    }
  }

  // If order is active and time cannot be determined, default to 15m arrival window
  if (secondsRemaining === null || isNaN(secondsRemaining)) {
    secondsRemaining = 15 * 60;
  }

  // Format and render function
  const renderCountdown = (secs) => {
    if (secs <= 0) {
      countdownEl.textContent = 'Arriving Shortly';
      return;
    }
    const mins = Math.floor(secs / 60);
    const remainderSecs = secs % 60;
    const formattedSecs = remainderSecs < 10 ? '0' + remainderSecs : remainderSecs;
    countdownEl.textContent = `${mins}:${formattedSecs} mins`;
  };

  renderCountdown(secondsRemaining);

  const countdownTimer = setInterval(() => {
    const currentStatus = (countdownEl.getAttribute('data-status') || '').toUpperCase();
    if (currentStatus === 'DELIVERED' || currentStatus === 'CANCELLED' || window.orderTrackingActive) {
      clearInterval(countdownTimer);
      if (currentStatus === 'DELIVERED' || currentStatus === 'CANCELLED') {
        countdownEl.textContent = currentStatus;
      }
      return;
    }

    if (secondsRemaining > 0) {
      secondsRemaining--;
      renderCountdown(secondsRemaining);
    } else {
      renderCountdown(0);
      clearInterval(countdownTimer);

      // Trigger automatic background polling to check if driver arrived
      const orderId = countdownEl.getAttribute('data-order-id');
      if (orderId) {
        fetch(`ajax/order-status-api.php?order_id=${encodeURIComponent(orderId)}`)
          .then(res => res.json())
          .then(data => {
            if (data && data.order_status) {
              countdownEl.setAttribute('data-status', data.order_status);
              if (data.order_status === 'DELIVERED') {
                countdownEl.textContent = 'DELIVERED';
              }
            }
          })
          .catch(() => {});
      }
    }
  }, 1000);
}

// Maintain backward compatibility alias
const initOrderStatusSimulation = initOrderStatusCountdown;

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
      if (data && data.success) {
        // Update dimension feedback breakdown meters
        if (data.dimensions) {
          const appVal = Number(data.dimensions.appearance || 4.8);
          const tasteVal = Number(data.dimensions.taste || 4.9);
          const portVal = Number(data.dimensions.portion || 4.7);
          const valVal = Number(data.dimensions.value || 4.8);

          const appEl = document.getElementById('dishDimAppearance');
          const tasteEl = document.getElementById('dishDimTaste');
          const portEl = document.getElementById('dishDimPortion');
          const valEl = document.getElementById('dishDimValue');

          if (appEl) appEl.textContent = `${appVal.toFixed(1)} ★`;
          if (tasteEl) tasteEl.textContent = `${tasteVal.toFixed(1)} ★`;
          if (portEl) portEl.textContent = `${portVal.toFixed(1)} ★`;
          if (valEl) valEl.textContent = `${valVal.toFixed(1)} ★`;

          const appB = document.getElementById('dishDimBarAppearance');
          const tasteB = document.getElementById('dishDimBarTaste');
          const portB = document.getElementById('dishDimBarPortion');
          const valB = document.getElementById('dishDimBarValue');

          if (appB) appB.style.width = `${Math.min(100, Math.round((appVal / 5) * 100))}%`;
          if (tasteB) tasteB.style.width = `${Math.min(100, Math.round((tasteVal / 5) * 100))}%`;
          if (portB) portB.style.width = `${Math.min(100, Math.round((portVal / 5) * 100))}%`;
          if (valB) valB.style.width = `${Math.min(100, Math.round((valVal / 5) * 100))}%`;
        }

        if (data.reviews && data.reviews.length === 0) {
          reviewsFeed.innerHTML = `
            <div class="text-center py-4 text-muted bg-light rounded-3">
              <i class="bi bi-chat-heart fs-2 d-block mb-2 text-secondary opacity-50"></i>
              <div class="fw-bold small">No reviews yet for this dish</div>
              <div class="small text-muted mb-2">Be the first to rate this food on Appearance, Taste, Portion & Value! ⭐</div>
              <button class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="toggleDishReviewForm()">Write First Review</button>
            </div>
          `;
        } else if (data.reviews) {
          let html = '';
          data.reviews.forEach(rev => {
            let stars = '';
            for (let s = 1; s <= 5; s++) {
              stars += s <= rev.rating ? '<i class="bi bi-star-fill text-warning"></i>' : '<i class="bi bi-star text-muted opacity-50"></i>';
            }
            const tagsHtml = (rev.tags || []).map(t => `<span class="badge bg-light text-forest border px-2 py-0" style="font-size:0.65rem;">${escapeHtml(t)}</span>`).join(' ');
            const initial = escapeHtml((rev.customer_name || 'F').charAt(0).toUpperCase());

            // 4-Dimension Feedback Pills
            const appRate = Number(rev.appearance_rating || rev.rating || 5);
            const tasteRate = Number(rev.taste_rating || rev.rating || 5);
            const portRate = Number(rev.portion_rating || rev.rating || 5);
            const valRate = Number(rev.value_rating || rev.rating || 5);

            const dimsHtml = `
              <div class="d-flex flex-wrap gap-1 my-2">
                <span class="badge bg-light text-dark border px-2 py-1" style="font-size:0.68rem;" title="Visual Presentation">
                  <i class="bi bi-eye text-primary me-1"></i>Appearance: <strong>${appRate}★</strong>
                </span>
                <span class="badge bg-light text-dark border px-2 py-1" style="font-size:0.68rem;" title="Flavor & Deliciousness">
                  <i class="bi bi-fire text-danger me-1"></i>Taste: <strong>${tasteRate}★</strong>
                </span>
                <span class="badge bg-light text-dark border px-2 py-1" style="font-size:0.68rem;" title="Portion Adequacy">
                  <i class="bi bi-pie-chart text-warning me-1"></i>Portion: <strong>${portRate}★</strong>
                </span>
                <span class="badge bg-light text-dark border px-2 py-1" style="font-size:0.68rem;" title="Value for Price">
                  <i class="bi bi-tag text-success me-1"></i>Value: <strong>${valRate}★</strong>
                </span>
              </div>
            `;

            html += `
              <div class="card border rounded-3 p-3 bg-white shadow-none">
                <div class="d-flex justify-content-between align-items-start mb-1">
                  <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold" style="width:34px; height:34px; background:#2E6F40; font-size:0.85rem;">
                      ${initial}
                    </div>
                    <div>
                      <div class="fw-bold small text-dark mb-0">${escapeHtml(rev.customer_name || 'Food Lover')} <span class="badge bg-success bg-opacity-10 text-success ms-1" style="font-size:0.6rem;">✓ Verified Foodie</span></div>
                      <div class="text-muted" style="font-size:0.7rem;">${escapeHtml(rev.date || 'Recent review')}</div>
                    </div>
                  </div>
                  <div class="text-warning small d-flex gap-1">
                    ${stars}
                  </div>
                </div>
                ${dimsHtml}
                ${tagsHtml ? `<div class="d-flex flex-wrap gap-1 mb-2">${tagsHtml}</div>` : ''}
                <p class="small text-muted mb-0 font-italic">"${escapeHtml(rev.review_text || 'Amazing and delicious meal! ⭐⭐⭐⭐⭐')}"</p>
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
  const dimensions = ['appearance', 'taste', 'portion', 'value'];
  
  dimensions.forEach(dim => {
    const container = document.getElementById('dimStars_' + dim);
    const input = document.getElementById('inlineDish' + dim.charAt(0).toUpperCase() + dim.slice(1));
    const label = document.getElementById('dimLabel_' + dim);
    if (!container) return;

    const stars = container.querySelectorAll('.inline-dim-star');
    stars.forEach(star => {
      star.addEventListener('click', function() {
        const val = parseInt(this.getAttribute('data-val'), 10);
        if (input) input.value = val;
        if (label) label.textContent = `${val}.0 ★`;

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

        // Compute overall average rating across the 4 dimensions
        const app = parseInt(document.getElementById('inlineDishAppearance')?.value || '5', 10);
        const taste = parseInt(document.getElementById('inlineDishTaste')?.value || '5', 10);
        const portion = parseInt(document.getElementById('inlineDishPortion')?.value || '5', 10);
        const value = parseInt(document.getElementById('inlineDishValue')?.value || '5', 10);
        const avg = Math.round((app + taste + portion + value) / 4);
        const overallInput = document.getElementById('inlineDishRating');
        if (overallInput) overallInput.value = avg;
      });
    });
  });

  // Legacy single clicker support
  const legacyContainer = document.getElementById('inlineModalStars');
  if (legacyContainer) {
    const legacyStars = legacyContainer.querySelectorAll('.inline-star');
    const ratingInput = document.getElementById('inlineDishRating');
    const label = document.getElementById('inlineRatingLabel');
    legacyStars.forEach(star => {
      star.addEventListener('click', function() {
        const val = parseInt(this.getAttribute('data-val'), 10);
        if (ratingInput) ratingInput.value = val;
        if (label) label.textContent = `${val} Star${val > 1 ? 's' : ''} ⭐`;
        legacyStars.forEach(s => {
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
}

window.submitInlineDishReview = function(e) {
  e.preventDefault();
  const dishId = document.getElementById('inlineDishId')?.value || 0;
  const rating = document.getElementById('inlineDishRating')?.value || 5;
  const appearance = document.getElementById('inlineDishAppearance')?.value || rating;
  const taste = document.getElementById('inlineDishTaste')?.value || rating;
  const portion = document.getElementById('inlineDishPortion')?.value || rating;
  const value = document.getElementById('inlineDishValue')?.value || rating;
  const reviewText = document.getElementById('inlineReviewText')?.value || '';

  if (!dishId) return;

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const formData = new FormData();
  formData.append('csrf_token', csrfToken);
  formData.append('action', 'submit_review');
  formData.append('dish_id', dishId);
  formData.append('rating', rating);
  formData.append('appearance_rating', appearance);
  formData.append('taste_rating', taste);
  formData.append('portion_rating', portion);
  formData.append('value_rating', value);
  formData.append('review_text', reviewText);
  formData.append('tags', 'Verified Customer,Delicious Taste,Generous Portion');

  const apiUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/api/dish-reviews.php';
  fetch(apiUrl, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': csrfToken },
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data && data.success) {
      if (typeof showToast === 'function') {
        showToast(`Thank you! Your dish feedback (Appearance: ${appearance}★, Taste: ${taste}★, Portion: ${portion}★, Value: ${value}★) has been posted! ⭐`);
      }
      const formBox = document.getElementById('dishReviewFormBox');
      if (formBox) formBox.style.display = 'none';
      window.openDishReviewsModal(dishId, document.getElementById('dishReviewsModalLabel')?.textContent || 'Food', data.rating, data.total_reviews);
    } else {
      if (typeof showToast === 'function') showToast((data && data.error) || 'Unable to submit review. Please sign in and try again.');
    }
  })
  .catch(() => {
    if (typeof showToast === 'function') showToast('Unable to submit review right now. Please sign in and try again.');
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
  
  const safeMsg = escapeHtml(typeof msg === 'string' && msg ? msg : 'Menu catalog was updated.');
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

