<?php
/**
 * Customer Portal Footer, Modals & Drawers
 * Zippy - Online Food Ordering Platform
 */
?>

  <!-- =========================================================================
       OFFCANVAS SHOPPING CART DRAWER
       ========================================================================= -->
  <div class="offcanvas offcanvas-end cart-drawer" tabindex="-1" id="cartDrawer" aria-labelledby="cartDrawerLabel">
    <div class="offcanvas-header border-bottom">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-bag-check-fill text-success fs-4"></i>
        <h5 class="offcanvas-title fw-bold" id="cartDrawerLabel">My Cart</h5>
        <span class="badge bg-mint text-dark rounded-pill cart-count-badge">0</span>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column p-0">
      
      <!-- Cart Item List Container (Populated via JS) -->
      <div id="cartItemsList" class="flex-grow-1 p-3 overflow-y-auto">
        <!-- Rendered by app.js -->
        <div class="text-center py-5 text-muted">
          <i class="bi bi-cart-x fs-1 d-block mb-3 text-secondary opacity-50"></i>
          <h6>Your cart is empty</h6>
          <p class="small text-muted mb-4">Explore our delicious dishes and add your favorites!</p>
          <a href="<?= CUSTOMER_URL ?>/index.php#dishes" class="btn btn-outline-success btn-sm rounded-pill px-4" data-bs-dismiss="offcanvas">Explore Menu</a>
        </div>
      </div>

      <!-- Free Delivery Progress Bar -->
      <div class="px-3 py-2 bg-light border-top">
        <div class="d-flex justify-content-between small mb-1">
          <span class="fw-semibold text-muted" id="freeDeliveryText"><i class="bi bi-truck me-1"></i>Free delivery above ₹<?= FREE_DELIVERY_THRESHOLD ?></span>
          <span class="fw-bold text-success" id="freeDeliveryGap">₹<?= FREE_DELIVERY_THRESHOLD ?> away</span>
        </div>
        <div class="progress" style="height: 6px;">
          <div class="progress-bar bg-success" id="freeDeliveryBar" role="progressbar" style="width: 0%"></div>
        </div>
      </div>

      <!-- Cart Drawer Footer (Totals & Checkout) -->
      <div class="p-3 border-top bg-white">
        <div class="d-flex justify-content-between mb-1 small text-muted">
          <span>Subtotal</span>
          <span class="fw-bold text-dark" id="cartDrawerSubtotal">₹0.00</span>
        </div>
        <div class="d-flex justify-content-between mb-1 small text-muted">
          <span>Taxes & GST (5%)</span>
          <span class="fw-semibold text-dark" id="cartDrawerGst">₹0.00</span>
        </div>
        <div class="d-flex justify-content-between mb-2 small text-muted">
          <span>Delivery Partner Fee</span>
          <span class="fw-semibold text-success" id="cartDrawerDelivery">₹0.00</span>
        </div>
        <div class="d-flex justify-content-between py-2 border-top mb-3">
          <span class="fw-bold fs-6">Grand Total</span>
          <span class="fw-bold fs-5 text-forest" id="cartDrawerTotal">₹0.00</span>
        </div>

        <div class="d-grid gap-2">
          <a href="<?= CUSTOMER_URL ?>/cart.php" class="btn btn-outline-success rounded-pill fw-semibold py-2">
            View Dedicated Cart Page
          </a>
          <a href="<?= CUSTOMER_URL ?>/checkout.php" class="btn btn-success rounded-pill fw-bold py-2 shadow-sm" style="background:#2E6F40; border-color:#2E6F40;" id="cartDrawerCheckoutBtn">
            Proceed to Checkout <i class="bi bi-arrow-right ms-1"></i>
          </a>
        </div>
      </div>

    </div>
  </div>

  <!-- =========================================================================
       LOCATION SELECTOR MODAL
       ========================================================================= -->
  <div class="modal fade" id="locationModal" tabindex="-1" aria-labelledby="locationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-4 border-0 shadow-lg">
        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold" id="locationModalLabel"><i class="bi bi-geo-alt-fill text-success me-2"></i>Select Delivery Area</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-semibold text-muted">CURRENT CITY: BANGALORE</label>
            <div class="list-group">
              <button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between active py-3 rounded-3 mb-2" data-bs-dismiss="modal">
                <div>
                  <div class="fw-bold">Indiranagar (100ft Road / 12th Main)</div>
                  <div class="small opacity-75">ETA: 25-30 mins | Fast Delivery Available</div>
                </div>
                <i class="bi bi-check-circle-fill fs-5"></i>
              </button>
              <button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3 rounded-3 mb-2" data-bs-dismiss="modal">
                <div>
                  <div class="fw-bold">Koramangala (4th & 5th Block)</div>
                  <div class="small text-muted">ETA: 30-35 mins</div>
                </div>
                <i class="bi bi-chevron-right text-muted"></i>
              </button>
              <button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3 rounded-3 mb-2" data-bs-dismiss="modal">
                <div>
                  <div class="fw-bold">Bellandur & Green Glen Layout</div>
                  <div class="small text-muted">ETA: 20-25 mins</div>
                </div>
                <i class="bi bi-chevron-right text-muted"></i>
              </button>
              <button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3 rounded-3" data-bs-dismiss="modal">
                <div>
                  <div class="fw-bold">HSR Layout (Sector 1 & 2)</div>
                  <div class="small text-muted">ETA: 25-30 mins</div>
                </div>
                <i class="bi bi-chevron-right text-muted"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- =========================================================================
       FLOATING MOBILE CART BAR (Visible only on mobile screens when cart > 0)
       ========================================================================= -->
  <div id="mobileCartBar" class="mobile-cart-bar d-lg-none" style="display:none;">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-bag-check-fill text-mint fs-5"></i>
      <div>
        <div class="fw-bold text-white small" id="mobileCartItemsCount">1 ITEM</div>
        <div class="text-mint fw-bold" id="mobileCartTotal">₹0.00</div>
      </div>
    </div>
    <div class="d-flex align-items-center gap-1">
      <button type="button" class="btn btn-mint btn-sm rounded-pill fw-bold px-3" data-bs-toggle="offcanvas" data-bs-target="#cartDrawer" aria-controls="cartDrawer">
        View Cart <i class="bi bi-chevron-right"></i>
      </button>
      <button type="button" class="btn btn-link text-white-50 p-1 text-decoration-none" onclick="dismissMobileCartBar()" title="Dismiss bar" style="font-size:1.15rem; line-height:1;">
        <i class="bi bi-x-circle-fill"></i>
      </button>
    </div>
  </div>

  <!-- =========================================================================
       MOBILE BOTTOM NAVIGATION
       ========================================================================= -->
  <nav class="mobile-bottom-nav d-lg-none">
    <div class="container-fluid d-flex justify-content-around py-2">
      <a href="<?= CUSTOMER_URL ?>/index.php" class="mobile-nav-link <?= ($activePage === 'home') ? 'active' : '' ?>">
        <i class="bi bi-house-door-fill"></i>
        <span>Home</span>
      </a>
      <a href="<?= CUSTOMER_URL ?>/search.php" class="mobile-nav-link <?= ($activePage === 'search') ? 'active' : '' ?>">
        <i class="bi bi-search"></i>
        <span>Search</span>
      </a>
      <a href="<?= CUSTOMER_URL ?>/cart.php" class="mobile-nav-link position-relative <?= ($activePage === 'cart') ? 'active' : '' ?>">
        <i class="bi bi-bag"></i>
        <span class="mobile-nav-badge position-absolute top-0 start-50 translate-middle badge rounded-pill bg-danger" style="display:none;">0</span>
        <span>Cart</span>
      </a>
      <a href="<?= CUSTOMER_URL ?>/order-history.php" class="mobile-nav-link <?= ($activePage === 'orders') ? 'active' : '' ?>">
        <i class="bi bi-receipt"></i>
        <span>Orders</span>
      </a>
      <a href="<?= $currentUser ? CUSTOMER_URL . '/profile.php' : CUSTOMER_URL . '/login.php' ?>" class="mobile-nav-link <?= ($activePage === 'profile' || $activePage === 'login') ? 'active' : '' ?>">
        <i class="bi bi-person"></i>
        <span>Account</span>
      </a>
    </div>
  </nav>

  <!-- =========================================================================
       MAIN FOOTER
       ========================================================================= -->
  <footer class="zippy-footer text-white pt-5 pb-4 mt-5" style="background: #243E2C;">
    <div class="container-xl">
      <div class="row g-4 mb-5">
        
        <!-- Col 1: Brand & Academic Context -->
        <div class="col-lg-4 col-md-6">
          <div class="d-flex align-items-center gap-2 mb-3">
            <div class="brand-icon-box" style="background:#2E6F40; width:36px; height:36px;">
              <i class="fa-solid fa-utensils text-white"></i>
            </div>
            <span class="fs-4 fw-bold font-outfit">zippy<span style="color:#68BA7F;">.</span></span>
          </div>
          <p class="text-white-50 small mb-3">
            Zippy is a high-performance food ordering and restaurant fulfillment platform developed for <strong>MCSP-232 (MCA Major Project)</strong> at Indira Gandhi National Open University (IGNOU).
          </p>
          <div class="d-flex align-items-center gap-2">
            <span class="badge" style="background:#2E6F40; color:#CEFFDC;">Figma Lush Forest</span>
            <span class="badge bg-secondary">PHP 8.2 & MySQL 3NF</span>
          </div>
        </div>

        <!-- Col 2: Customer Links -->
        <div class="col-lg-2 col-md-3 col-6">
          <h6 class="fw-bold text-uppercase text-mint mb-3" style="color: #CEFFDC; font-size: 0.8rem; letter-spacing: 0.5px;">Customer App</h6>
          <ul class="list-unstyled small mb-0 d-flex flex-column gap-2 text-white-50">
            <li><a href="<?= CUSTOMER_URL ?>/index.php" class="text-white-50 text-decoration-none">Home Storefront</a></li>
            <li><a href="<?= CUSTOMER_URL ?>/search.php" class="text-white-50 text-decoration-none">Search Menu</a></li>
            <li><a href="<?= CUSTOMER_URL ?>/offers.php" class="text-white-50 text-decoration-none">Discount Offers</a></li>
            <li><a href="<?= CUSTOMER_URL ?>/cart.php" class="text-white-50 text-decoration-none">Shopping Cart</a></li>
            <li><a href="<?= CUSTOMER_URL ?>/order-history.php" class="text-white-50 text-decoration-none">Track Past Orders</a></li>
          </ul>
        </div>

        <!-- Col 3: Admin & Portals -->
        <div class="col-lg-2 col-md-3 col-6">
          <h6 class="fw-bold text-uppercase text-mint mb-3" style="color: #CEFFDC; font-size: 0.8rem; letter-spacing: 0.5px;">Management</h6>
          <ul class="list-unstyled small mb-0 d-flex flex-column gap-2 text-white-50">
            <li><a href="<?= ADMIN_URL ?>/index.php" class="text-white-50 text-decoration-none">Admin Console</a></li>
            <li><a href="<?= ADMIN_URL ?>/orders.php" class="text-white-50 text-decoration-none">Live Orders</a></li>
            <li><a href="<?= KITCHEN_URL ?>/index.php" class="text-white-50 text-decoration-none">Kitchen KDS Portal</a></li>
            <li><a href="<?= ADMIN_URL ?>/dishes.php" class="text-white-50 text-decoration-none">Menu Catalog CRUD</a></li>
            <li><a href="<?= ADMIN_URL ?>/reports.php" class="text-white-50 text-decoration-none">BI Analytics & SES</a></li>
          </ul>
        </div>

        <!-- Col 4: Academic Viva Defense -->
        <div class="col-lg-4 col-md-6">
          <h6 class="fw-bold text-uppercase text-mint mb-3" style="color: #CEFFDC; font-size: 0.8rem; letter-spacing: 0.5px;">MCA Project Credentials</h6>
          <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);">
            <div class="small text-white-50 mb-1">Student: <strong class="text-white">Pooja JP</strong></div>
            <div class="small text-white-50 mb-1">Course: <span class="text-white">MCSP-232 | MCA Project</span></div>
            <div class="small text-white-50 mb-1">Enrollment No: <span class="text-white font-monospace">2500051247</span></div>
            <div class="small text-white-50 mb-2">Algorithms: <span class="badge bg-success bg-opacity-50 text-mint">Apriori</span> <span class="badge bg-info bg-opacity-50 text-white">WSJF</span> <span class="badge bg-warning bg-opacity-50 text-white">SES</span></div>
            <a href="<?= BASE_URL ?>/database/seed.php" class="btn btn-sm btn-outline-success w-100 rounded-pill py-1 mt-1 text-mint border-success">
              <i class="bi bi-database me-1"></i> Reset / Re-seed Database
            </a>
          </div>
        </div>

      </div>

      <div class="border-top pt-3 d-flex flex-wrap align-items-center justify-content-between small text-white-50" style="border-color: rgba(255,255,255,0.1) !important;">
        <div>&copy; 2026 Zippy Food Ordering Platform. All rights reserved.</div>
        <div class="d-flex gap-3">
          <a href="<?= CUSTOMER_URL ?>/help.php" class="text-white-50 text-decoration-none">Help & Support</a>
          <a href="<?= CUSTOMER_URL ?>/privacy.php" class="text-white-50 text-decoration-none">Privacy Policy</a>
          <a href="<?= CUSTOMER_URL ?>/terms.php" class="text-white-50 text-decoration-none">Terms of Service</a>
        </div>
      </div>
    </div>
  </footer>

  <!-- Bootstrap 5.3.3 JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Custom Zippy JS Engine -->
  <script src="<?= ASSETS_URL ?>/js/app.js?v=<?= time() ?>"></script>

</body>
</html>
