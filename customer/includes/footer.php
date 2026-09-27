<?php
/**
 * Shared Footer Component
 * MCSP-232: E-Commerce Platform for Food Order
 */
?>
  <!-- =========================================================================
       FOOTER
       ========================================================================= -->
  <footer class="zippy-footer mt-5 pt-5 pb-4">
    <div class="container-xl">
      <div class="row g-4 mb-5">
        
        <!-- Col 1: Brand & Academic Context -->
        <div class="col-lg-4 col-md-6">
          <a href="index.php" class="brand-logo text-decoration-none mb-3 d-inline-flex">
            <div class="brand-icon-box" style="background-color: var(--lush-forest); color: white;">
              <i class="fa-solid fa-utensils"></i>
            </div>
            <span class="brand-name text-white">zippy<span class="brand-highlight">.</span></span>
          </a>
          <p class="small text-white-50 mb-3" style="line-height: 1.7;">
            Next-generation Cloud Kitchen & Food Delivery Platform designed for university major project evaluation (MCSP-232). Built with clean OOP/MVC PHP backend and normalized MySQL 8.0 InnoDB database.
          </p>
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-white bg-opacity-10 text-mint border border-white border-opacity-25 px-3 py-2 rounded-pill small">
              <i class="bi bi-mortarboard me-1"></i> IGNOU MCA Project • 2026
            </span>
          </div>
        </div>

        <!-- Col 2: Customer Ordering Links -->
        <div class="col-lg-2 col-md-6 col-6">
          <h6 class="text-white fw-bold mb-3 small text-uppercase" style="letter-spacing: 0.5px;">Navigation</h6>
          <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
            <li><a href="index.php" class="text-white-50 text-decoration-none hover-white"><i class="bi bi-chevron-right me-1 text-mint"></i> Home</a></li>
            <li><a href="restaurant.php" class="text-white-50 text-decoration-none hover-white"><i class="bi bi-chevron-right me-1 text-mint"></i> Restaurant Menu</a></li>
            <li><a href="search.php" class="text-white-50 text-decoration-none hover-white"><i class="bi bi-chevron-right me-1 text-mint"></i> Live Search</a></li>
            <li><a href="offers.php" class="text-white-50 text-decoration-none hover-white"><i class="bi bi-chevron-right me-1 text-mint"></i> Offers & Coupons</a></li>
            <li><a href="cart.php" class="text-white-50 text-decoration-none hover-white"><i class="bi bi-chevron-right me-1 text-mint"></i> Shopping Cart</a></li>
            <li><a href="order-history.php" class="text-white-50 text-decoration-none hover-white"><i class="bi bi-chevron-right me-1 text-mint"></i> My Orders</a></li>
          </ul>
        </div>

        <!-- Col 3: Cloud Kitchen & Portals -->
        <div class="col-lg-3 col-md-6 col-6">
          <h6 class="text-white fw-bold mb-3 small text-uppercase" style="letter-spacing: 0.5px;">Portals & Operations</h6>
          <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
            <li><a href="kitchen-display.php" class="text-white-50 text-decoration-none hover-white"><i class="fa-solid fa-fire-burner me-1 text-warning"></i> Kitchen Display (KDS)</a></li>
            <li><a href="portals.php" class="text-white-50 text-decoration-none hover-white"><i class="bi bi-grid-fill me-1 text-mint"></i> Multi-Portal Gateway</a></li>
            <li><a href="order-status.php" class="text-white-50 text-decoration-none hover-white"><i class="bi bi-geo-alt-fill me-1 text-mint"></i> Live Order Tracker</a></li>
            <li><a href="manage-addresses.php" class="text-white-50 text-decoration-none hover-white"><i class="bi bi-bookmark me-1 text-mint"></i> Saved Addresses</a></li>
            <li><a href="profile.php" class="text-white-50 text-decoration-none hover-white"><i class="bi bi-person me-1 text-mint"></i> Profile Center</a></li>
            <li><a href="help.php" class="text-white-50 text-decoration-none hover-white"><i class="bi bi-headset me-1 text-mint"></i> Help & Support</a></li>
          </ul>
        </div>

        <!-- Col 4: Algorithmic Rigor (MCA Evaluation) -->
        <div class="col-lg-3 col-md-6">
          <h6 class="text-white fw-bold mb-3 small text-uppercase" style="letter-spacing: 0.5px;">Academic Rigor</h6>
          <div class="p-3 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-15 mb-3">
            <div class="small fw-bold text-mint mb-1"><i class="fa-solid fa-network-wired me-1"></i> Apriori Cross-Selling</div>
            <div class="text-white-50" style="font-size: 0.75rem;">Association rule mining on transaction pairs (Support &ge; 0.40, Confidence &ge; 80%).</div>
          </div>
          <div class="p-3 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-15">
            <div class="small fw-bold text-warning mb-1"><i class="fa-solid fa-clock-rotate-left me-1"></i> WSJF Priority Queue</div>
            <div class="text-white-50" style="font-size: 0.75rem;">Dynamic kitchen queue calculates cost-of-delay divided by dish prep durations.</div>
          </div>
        </div>

      </div>

      <!-- Legal & Copyright Bar -->
      <div class="border-top border-white border-opacity-15 pt-4 d-flex justify-content-between align-items-center flex-wrap gap-2 text-white-50 small">
        <div>
          &copy; <?= date('Y') ?> <strong>Zippy Food Platform</strong>. Developed by <strong>Pooja JP</strong> (MCA Project MCSP-232).
        </div>
        <div class="d-flex gap-3">
          <a href="terms.php" class="text-white-50 text-decoration-none hover-white">Terms of Service</a>
          <span>•</span>
          <a href="privacy.php" class="text-white-50 text-decoration-none hover-white">Privacy Policy</a>
          <span>•</span>
          <a href="help.php" class="text-white-50 text-decoration-none hover-white">Support & FAQs</a>
        </div>
      </div>
    </div>
  </footer>

  <!-- Floating Mobile Cart Bar -->
  <a href="cart.php" class="mobile-cart-bar" id="mobileCartBar" style="display: none;">
    <div>
      <div class="fw-bold" id="mobileCartItemsCount">0 ITEMS</div>
      <div class="small text-white-50" id="mobileCartTotal">₹0</div>
    </div>
    <div class="d-flex align-items-center gap-2 fw-bold">
      <span>VIEW CART</span>
      <i class="bi bi-arrow-right"></i>
    </div>
  </a>

  <!-- Offcanvas Cart Drawer -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="cartDrawer" aria-labelledby="cartDrawerLabel" style="width: 380px;">
    <div class="offcanvas-header border-bottom">
      <h5 class="offcanvas-title fw-bold text-pine" id="cartDrawerLabel">
        <i class="bi bi-bag-check-fill text-success me-2"></i> Your Cart
      </h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-0">
      <div id="cartDrawerItems" class="p-3 flex-grow-1 overflow-y-auto">
        <!-- Rendered via JS -->
      </div>
      <div class="p-3 border-top bg-light">
        <div class="d-flex justify-content-between fw-bold mb-2">
          <span>Subtotal</span>
          <span id="cartDrawerSubtotal">₹0</span>
        </div>
        <a href="checkout.php" class="btn btn-success w-100 rounded-pill fw-bold py-2 shadow-sm" style="background-color: var(--lush-forest);">
          Proceed to Checkout <i class="bi bi-arrow-right ms-1"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- Toast Container -->
  <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
    <div id="zippyToast" class="toast align-items-center text-white bg-dark border-0 rounded-3 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body d-flex align-items-center gap-2" id="zippyToastMsg">
          <i class="bi bi-check-circle-fill text-success fs-5"></i> Item updated!
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5 Bundle JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  
  <!-- Zippy Core Application JS Engine -->
  <script src="js/app.js"></script>

</body>
</html>
