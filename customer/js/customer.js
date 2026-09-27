/**
 * =============================================================================
 * ZIPPY CUSTOMER JS CONTROLLER
 * Asynchronous Fetch API client for shopping cart, coupons, and live polling
 * =============================================================================
 */

document.addEventListener('DOMContentLoaded', () => {
    initAddToCartButtons();
    initQtyControls();
    initCouponForm();
});

/**
 * Toast Notification Helper
 */
function showToast(message, type = 'success') {
    let container = document.getElementById('zippy-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'zippy-toast-container';
        container.style.position = 'fixed';
        container.style.bottom = '24px';
        container.style.right = '24px';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `alert alert-${type} shadow-lg rounded-3 py-2 px-3 mb-2 d-flex align-items-center gap-2 border-0 text-white`;
    toast.style.background = type === 'success' ? '#2E6F40' : (type === 'danger' ? '#D32F2F' : '#243E2C');
    toast.style.minWidth = '260px';
    toast.innerHTML = `
        <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill'}"></i>
        <div class="small fw-semibold flex-grow-1">${message}</div>
    `;

    container.appendChild(toast);
    setTimeout(() => {
        toast.style.transition = 'opacity 0.4s ease';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 400);
    }, 2800);
}

/**
 * Update Header Cart Count Badge
 */
function updateHeaderBadge(count) {
    const badges = document.querySelectorAll('.cart-count-badge');
    badges.forEach(b => {
        b.textContent = count;
        b.classList.add('scale-bump');
        setTimeout(() => b.classList.remove('scale-bump'), 300);
    });
}

/**
 * Handle Add to Cart button clicks
 */
function initAddToCartButtons() {
    document.querySelectorAll('.btn-add-cart').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const itemId = btn.getAttribute('data-item-id');
            const itemName = btn.getAttribute('data-item-name') || 'Item';

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

            try {
                const response = await fetch('api/cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'add', item_id: parseInt(itemId), qty: 1 })
                });
                const data = await response.json();

                if (data.success) {
                    showToast(`Added "${itemName}" to cart!`, 'success');
                    updateHeaderBadge(data.cart.count);

                    // Switch button to quantity stepper if in restaurant menu
                    const parentCard = btn.closest('.dish-card');
                    if (parentCard) {
                        btn.style.display = 'none';
                        const qtyBox = parentCard.querySelector('.dish-qty-stepper');
                        if (qtyBox) {
                            qtyBox.style.display = 'flex';
                            const countSpan = qtyBox.querySelector('.qty-value');
                            if (countSpan) countSpan.textContent = '1';
                        }
                    } else {
                        btn.innerHTML = '<i class="bi bi-check2"></i> Added';
                        setTimeout(() => {
                            btn.disabled = false;
                            btn.innerHTML = '+ ADD';
                        }, 1200);
                    }
                } else {
                    showToast(data.message || 'Could not add item', 'danger');
                    btn.disabled = false;
                    btn.innerHTML = '+ ADD';
                }
            } catch (err) {
                console.error(err);
                showToast('Failed to connect to cart service', 'danger');
                btn.disabled = false;
                btn.innerHTML = '+ ADD';
            }
        });
    });
}

/**
 * Handle Quantity Increment / Decrement
 */
function initQtyControls() {
    // Stepper Decrement
    document.querySelectorAll('.btn-qty-minus').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const itemId = parseInt(btn.getAttribute('data-item-id'));
            const qtySpan = btn.parentElement.querySelector('.qty-value');
            let currentQty = parseInt(qtySpan.textContent);
            let newQty = currentQty - 1;

            await updateCartItemQty(itemId, newQty, btn);
        });
    });

    // Stepper Increment
    document.querySelectorAll('.btn-qty-plus').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const itemId = parseInt(btn.getAttribute('data-item-id'));
            const qtySpan = btn.parentElement.querySelector('.qty-value');
            let currentQty = parseInt(qtySpan.textContent);
            let newQty = currentQty + 1;

            await updateCartItemQty(itemId, newQty, btn);
        });
    });

    // Cart Remove item button
    document.querySelectorAll('.btn-remove-item').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const itemId = parseInt(btn.getAttribute('data-item-id'));
            await updateCartItemQty(itemId, 0, btn);
        });
    });
}

async function updateCartItemQty(itemId, newQty, triggerBtn) {
    try {
        const response = await fetch('api/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update', item_id: itemId, qty: newQty })
        });
        const data = await response.json();

        if (data.success) {
            updateHeaderBadge(data.cart.count);

            // If on cart.php page, refresh the page or update summary elements
            if (window.location.pathname.endsWith('cart.php')) {
                window.location.reload();
                return;
            }

            const stepper = triggerBtn.closest('.dish-qty-stepper');
            if (stepper) {
                if (newQty <= 0) {
                    stepper.style.display = 'none';
                    const parentCard = stepper.closest('.dish-card');
                    if (parentCard) {
                        const addBtn = parentCard.querySelector('.btn-add-cart');
                        if (addBtn) addBtn.style.display = 'inline-block';
                    }
                } else {
                    const countSpan = stepper.querySelector('.qty-value');
                    if (countSpan) countSpan.textContent = newQty;
                }
            }
        }
    } catch (err) {
        console.error(err);
    }
}

/**
 * Handle Coupon Form submission
 */
function initCouponForm() {
    const couponForm = document.getElementById('coupon-apply-form');
    if (couponForm) {
        couponForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const input = document.getElementById('coupon-input');
            const code = input ? input.value.trim() : '';

            if (!code) {
                showToast('Please enter a coupon code', 'warning');
                return;
            }

            try {
                const response = await fetch('api/coupon.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'apply', code: code })
                });
                const data = await response.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    showToast(data.message, 'danger');
                }
            } catch (err) {
                showToast('Error applying coupon', 'danger');
            }
        });
    }
}
