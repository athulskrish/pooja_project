<?php
/**
 * Restaurant Menu & Ordering Page
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Meghana Foods - Menu & Online Ordering';
$activePage = 'restaurant';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/AprioriEngine.php';

// Fetch Categories and Menu Items
$categories = db_fetch_all("
    SELECT c.*, COUNT(m.item_id) AS total_items
    FROM `categories` c
    JOIN `menu_items` m ON c.category_id = m.category_id
    WHERE c.is_active = 1 AND m.is_available = 1
    GROUP BY c.category_id
    ORDER BY c.display_order ASC
");

// Group menu items by category
$itemsByCategory = [];
foreach ($categories as $cat) {
    $itemsByCategory[$cat['category_id']] = db_fetch_all("
        SELECT * FROM `menu_items` 
        WHERE `category_id` = :cid AND `is_available` = 1 
        ORDER BY `price` ASC
    ", [':cid' => $cat['category_id']]);
}

$recommendations = AprioriEngine::getRecommendations([], 3);
?>

<main class="py-4">
  <div class="container-xl">

    <!-- =======================================================================
         RESTAURANT HERO HEADER
         ======================================================================= -->
    <div class="card border-0 rounded-4 shadow-sm p-4 mb-4 bg-white">
      <div class="row align-items-center g-4">
        
        <div class="col-md-8">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge bg-success bg-opacity-25 text-success fw-bold px-2 py-1">OPEN NOW</span>
            <span class="badge bg-light text-dark border small"><i class="bi bi-shield-check text-success"></i> FSSAI Lic #112233440055</span>
          </div>

          <h2 class="fw-bold mb-1 font-outfit text-dark">Meghana Foods</h2>
          <p class="text-muted small mb-2">Authentic Andhra Dum Biryani, North Indian Curries, Tandoori Starters</p>
          <div class="text-muted small mb-3">
            <i class="bi bi-geo-alt-fill text-danger me-1"></i> 100 Feet Road, Indiranagar, Bangalore (1.8 km away)
          </div>

          <!-- Quick Metrics Bar -->
          <div class="d-flex flex-wrap align-items-center gap-3 pt-3 border-top">
            <div class="d-flex align-items-center gap-1">
              <span class="badge bg-success rounded-pill px-2 py-1 fs-6"><i class="bi bi-star-fill text-warning me-1"></i> 4.5</span>
              <span class="small text-muted ms-1">(1,420+ ratings)</span>
            </div>
            <div class="border-start ps-3 small text-muted">
              <i class="bi bi-clock-history text-success me-1"></i> <strong>25-30 mins</strong> ETA
            </div>
            <div class="border-start ps-3 small text-muted">
              <i class="bi bi-currency-rupee text-success"></i> <strong>₹400</strong> for two
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <!-- Discount Offer Card inside Restaurant -->
          <div class="p-3 rounded-4" style="background: #EDFBF2; border: 1px dashed #2E6F40;">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="badge" style="background:#2E6F40; color:#CEFFDC;">OFFER</span>
              <strong class="text-forest small">20% OFF UP TO ₹100</strong>
            </div>
            <p class="small text-muted mb-2">Valid on orders above ₹299. Applied at checkout automatically.</p>
            <div class="d-flex align-items-center justify-content-between bg-white px-3 py-1 rounded-pill border">
              <code class="fw-bold text-forest">LUSHFOREST</code>
              <button class="btn btn-link btn-sm text-success p-0 fw-bold text-decoration-none" onclick="navigator.clipboard.writeText('LUSHFOREST'); showToast('Coupon copied to clipboard!');">COPY</button>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- =======================================================================
         MENU LAYOUT (CATEGORY NAV + DISHES + STICKY CART)
         ======================================================================= -->
    <div class="row g-4">
      
      <!-- Left Column: Menu Items by Category -->
      <div class="col-lg-8">
        
        <!-- Controls Bar: Search & Veg Only Filter -->
        <div class="card border-0 rounded-4 shadow-sm p-3 mb-4 bg-white d-flex flex-row justify-content-between align-items-center">
          <div class="fw-bold text-dark">
            <i class="fa-solid fa-book-open text-success me-2"></i> Menu Catalog
          </div>
          
          <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
            <input class="form-check-input" type="checkbox" role="switch" id="vegOnlySwitch" style="cursor: pointer;">
            <label class="form-check-label fw-bold small text-success mb-0" for="vegOnlySwitch" style="cursor: pointer;">
              Pure Veg Only
            </label>
          </div>
        </div>

        <!-- Category Sections -->
        <?php foreach ($categories as $cat): ?>
          <?php $catItems = $itemsByCategory[$cat['category_id']] ?? []; ?>
          <?php if (empty($catItems)) continue; ?>

          <div class="card border-0 rounded-4 shadow-sm p-4 mb-4 bg-white category-menu-block" id="category-<?= e($cat['category_slug']) ?>">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
              <div>
                <h4 class="fw-bold mb-0 text-dark"><?= e($cat['category_name']) ?></h4>
                <div class="small text-muted"><?= count($catItems) ?> items in this category</div>
              </div>
            </div>

            <!-- Dish Items List -->
            <div class="d-flex flex-column gap-4">
              <?php foreach ($catItems as $dish): ?>
                <div class="d-flex justify-content-between align-items-start gap-3 pb-3 border-bottom dish-item-row" data-veg="<?= $dish['is_veg'] ? 'true' : 'false' ?>">
                  
                  <!-- Dish Info -->
                  <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-1">
                      <?php if ($dish['is_veg']): ?>
                        <span class="badge bg-white text-success border border-success d-flex align-items-center gap-1 px-2 py-0" style="font-size:0.65rem;">
                          <span style="width:6px; height:6px; border-radius:50%; background:#229947; display:inline-block;"></span> VEG
                        </span>
                      <?php else: ?>
                        <span class="badge bg-white text-danger border border-danger d-flex align-items-center gap-1 px-2 py-0" style="font-size:0.65rem;">
                          <span style="width:6px; height:6px; border-radius:50%; background:#D32F2F; display:inline-block;"></span> NON-VEG
                        </span>
                      <?php endif; ?>

                      <span class="badge bg-light text-muted small"><i class="bi bi-clock me-1"></i><?= (int)$dish['prep_time_minutes'] ?>m</span>
                    </div>

                    <h5 class="fw-bold mb-1 text-dark"><?= e($dish['item_name']) ?></h5>
                    <div class="fw-bold text-forest fs-5 mb-1"><?= format_inr($dish['price']) ?></div>
                    <p class="text-muted small mb-0" style="max-width: 500px;">
                      <?= e($dish['description']) ?>
                    </p>
                  </div>

                  <!-- Dish Image & ADD Control -->
                  <div class="position-relative text-center flex-shrink-0" style="width: 130px;">
                    <img src="<?= e($dish['image_url']) ?>" alt="<?= e($dish['item_name']) ?>" class="rounded-4 object-fit-cover shadow-sm w-100" style="height: 100px;">
                    
                    <div class="position-absolute bottom-0 start-50 translate-middle-x dish-qty-control-slot" data-dish-id="<?= (int)$dish['item_id'] ?>" data-dish-name="<?= addslashes(e($dish['item_name'])) ?>" data-dish-price="<?= (float)$dish['price'] ?>" data-dish-veg="<?= $dish['is_veg'] ? 'true' : 'false' ?>" style="margin-bottom: -14px;">
                      <button 
                        type="button" 
                        class="btn btn-white btn-sm rounded-pill shadow px-3 py-1 fw-bold text-success border add-to-cart-btn"
                        onclick="addToCart(<?= (int)$dish['item_id'] ?>, '<?= addslashes(e($dish['item_name'])) ?>', <?= (float)$dish['price'] ?>, <?= $dish['is_veg'] ? 1 : 0 ?>, 'Meghana Foods')"
                      >
                        ADD +
                      </button>
                    </div>
                  </div>

                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

      </div>

      <!-- Right Column: Sticky Cart Sidebar -->
      <div class="col-lg-4 d-none d-lg-block">
        <div class="card border-0 rounded-4 shadow-sm p-4 bg-white sticky-top" style="top: 90px;">
          <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-bag-check-fill text-success me-2"></i>Order Summary</h5>
            <span class="badge bg-mint text-dark rounded-pill cart-count-badge">0</span>
          </div>

          <!-- Cart Items Container (Synced via JS app.js) -->
          <div id="sidebarCartItems" class="mb-3" style="max-height: 320px; overflow-y: auto;">
            <!-- Rendered via JS -->
            <div class="text-center py-4 text-muted">
              <i class="bi bi-cart3 fs-2 d-block mb-2 text-secondary opacity-50"></i>
              <div class="small">Cart is currently empty</div>
              <div class="small text-muted">Add items from the menu to start order</div>
            </div>
          </div>

          <!-- Bill Summary Preview -->
          <div class="border-top pt-3">
            <div class="d-flex justify-content-between small text-muted mb-1">
              <span>Subtotal</span>
              <span class="fw-bold text-dark" id="sidebarSubtotal">₹0.00</span>
            </div>
            <div class="d-flex justify-content-between small text-muted mb-1">
              <span>GST (5%)</span>
              <span class="fw-semibold text-dark" id="sidebarGst">₹0.00</span>
            </div>
            <div class="d-flex justify-content-between small text-muted mb-2">
              <span>Delivery Fee</span>
              <span class="fw-semibold text-success" id="sidebarDelivery">₹0.00</span>
            </div>
            <div class="d-flex justify-content-between py-2 border-top mb-3">
              <span class="fw-bold">Payable Total</span>
              <span class="fw-bold text-forest fs-5" id="sidebarTotal">₹0.00</span>
            </div>

            <a href="<?= CUSTOMER_URL ?>/cart.php" class="btn btn-success w-100 rounded-pill fw-bold py-2 shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
              View Cart & Checkout <i class="bi bi-arrow-right ms-1"></i>
            </a>
          </div>

        </div>
      </div>

    </div>

  </div>
</main>

<script>
// Filter veg switch on restaurant page
document.addEventListener('DOMContentLoaded', () => {
  const vegSwitch = document.getElementById('vegOnlySwitch');
  if (vegSwitch) {
    vegSwitch.addEventListener('change', () => {
      const isVegOnly = vegSwitch.checked;
      const rows = document.querySelectorAll('.dish-item-row');
      rows.forEach(row => {
        if (isVegOnly && row.getAttribute('data-veg') !== 'true') {
          row.style.display = 'none';
        } else {
          row.style.display = 'flex';
        }
      });
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
