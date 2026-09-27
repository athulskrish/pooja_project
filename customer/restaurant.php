<?php
/**
 * Restaurant Menu & Ordering Page
 * MCSP-232: E-Commerce Platform for Food Order
 * Features: Apriori Cross-sell Engine, Veg Toggle, Category Navigation, Live Cart Sync
 */
$pageTitle = 'Meghana Foods - Menu & Online Ordering';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/controllers/MenuController.php';

$data = MenuController::getRestaurantPageData();
$categories  = $data['categories'];
$menuGrouped = $data['menuGrouped'];
$aprioriRecs = $data['aprioriRecs'];
$vegOnly     = $data['vegOnly'];
$search      = $data['search'];
?>

  <!-- =========================================================================
       MAIN RESTAURANT CONTENT
       ========================================================================= -->
  <main class="container-xl py-4" style="max-width: 980px;">
    
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="index.php" class="text-success text-decoration-none">Home</a></li>
        <li class="breadcrumb-item"><a href="index.php" class="text-success text-decoration-none">Bangalore</a></li>
        <li class="breadcrumb-item"><a href="index.php" class="text-success text-decoration-none">Indiranagar</a></li>
        <li class="breadcrumb-item active text-muted fw-bold" aria-current="page">Meghana Foods</li>
      </ol>
    </nav>

    <!-- Restaurant Hero Header Card -->
    <section class="res-detail-header p-4 bg-white rounded-4 border shadow-sm mb-4">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
          <h1 class="res-main-title mb-1 fw-bold text-pine">Meghana Foods</h1>
          <p class="text-muted mb-2">Biryani, Andhra Special, South Indian, North Indian, Seafood</p>
          <div class="d-flex align-items-center gap-2 small text-muted">
            <i class="bi bi-geo-alt text-success"></i>
            <span>100 Feet Road, HAL 2nd Stage, Indiranagar • 2.4 km away</span>
          </div>
        </div>
        
        <!-- Outlet Star Rating Card -->
        <div class="p-3 bg-light rounded-3 border text-center" style="min-width: 100px;">
          <div class="d-flex align-items-center justify-content-center gap-1 text-success fw-bold fs-5">
            <i class="bi bi-star-fill text-warning"></i>
            <span>4.5</span>
          </div>
          <div class="border-top mt-1 pt-1 text-muted" style="font-size: 0.72rem; font-weight: 700;">
            15K+ RATINGS
          </div>
        </div>
      </div>

      <!-- Quick Specs / Meta Ribbon -->
      <div class="res-specs-card mt-3 pt-3 border-top d-flex flex-wrap gap-4 align-items-center">
        <div class="spec-item d-flex align-items-center gap-2">
          <div class="spec-icon bg-light text-success p-2 rounded-circle">
            <i class="bi bi-clock-history fs-5"></i>
          </div>
          <div>
            <div class="spec-title small text-muted fw-bold">DELIVERY TIME</div>
            <div class="spec-val fw-bold text-pine">20-25 MINS</div>
          </div>
        </div>

        <div class="vr opacity-25 d-none d-sm-block"></div>

        <div class="spec-item d-flex align-items-center gap-2">
          <div class="spec-icon bg-light text-success p-2 rounded-circle">
            <i class="bi bi-wallet2 fs-5"></i>
          </div>
          <div>
            <div class="spec-title small text-muted fw-bold">AVERAGE COST</div>
            <div class="spec-val fw-bold text-pine">₹400 FOR TWO</div>
          </div>
        </div>

        <div class="vr opacity-25 d-none d-sm-block"></div>

        <div class="spec-item d-flex align-items-center gap-2">
          <div class="spec-icon p-2 rounded-circle" style="background-color: var(--lush-mint);">
            <i class="bi bi-shield-check text-success fs-5"></i>
          </div>
          <div>
            <div class="spec-title small text-muted fw-bold">HYGIENE RATING</div>
            <div class="spec-val text-success fw-bold">4.8 / 5.0 (FSSAI)</div>
          </div>
        </div>
      </div>

      <!-- Live Offers Carousel -->
      <div class="mt-4 pt-3 border-top">
        <h6 class="fw-bold text-pine small text-uppercase mb-2"><i class="fa-solid fa-gift text-success me-1"></i> Available Discounts & Offers</h6>
        <div class="d-flex gap-3 overflow-x-auto pb-2 scrollbar-thin">
          <div class="p-2 px-3 border rounded-3 bg-white shadow-xs flex-shrink-0" style="min-width: 220px; border-left: 4px solid var(--lush-forest) !important;">
            <div class="fw-bold text-pine small"><i class="fa-solid fa-percent text-success me-1"></i> FLAT ₹125 OFF</div>
            <div class="text-muted" style="font-size: 0.75rem;">USE CODE: <strong>STEALDEAL</strong> | Above ₹349</div>
          </div>
          <div class="p-2 px-3 border rounded-3 bg-white shadow-xs flex-shrink-0" style="min-width: 220px; border-left: 4px solid var(--lush-sage) !important;">
            <div class="fw-bold text-pine small"><i class="fa-solid fa-tag text-success me-1"></i> 20% OFF UPTO ₹100</div>
            <div class="text-muted" style="font-size: 0.75rem;">USE CODE: <strong>LUSHFOREST</strong> | No minimum</div>
          </div>
          <div class="p-2 px-3 border rounded-3 bg-white shadow-xs flex-shrink-0" style="min-width: 220px; border-left: 4px solid var(--lush-pine) !important;">
            <div class="fw-bold text-pine small"><i class="fa-solid fa-bolt text-warning me-1"></i> 50% OFF ON FIRST ORDER</div>
            <div class="text-muted" style="font-size: 0.75rem;">USE CODE: <strong>ZIPPY50</strong> | Max ₹100</div>
          </div>
        </div>
      </div>
    </section>

    <!-- =========================================================================
         ALGORITHMIC SPOTLIGHT (MCA PROJECT REQUIREMENT)
         Apriori Market Basket Association Rule Recommendation
         ========================================================================= -->
    <section class="algo-recommendation-box p-4 rounded-4 shadow-sm mb-4" style="background: linear-gradient(135deg, #1C3322 0%, #243E2C 100%); color: white;">
      <span class="badge bg-mint text-pine fw-bold mb-2">
        <i class="fa-solid fa-network-wired me-1"></i> Algorithmic Feature: Market Basket Analysis (Apriori)
      </span>
      <h4 class="fw-bold mb-1">Frequently Ordered Together with Biryani</h4>
      <p class="small text-white-50 mb-3">Derived from historical association rule mining (Confidence: 84%, Support: 0.42):</p>
      
      <div class="row g-3">
        <?php foreach ($aprioriRecs as $rec): ?>
          <div class="col-md-4">
            <div class="p-3 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-15 h-100 d-flex flex-column justify-content-between">
              <div class="d-flex align-items-center gap-3 mb-2">
                <img src="<?= sanitize($rec['image_url']) ?>" class="rounded-3 object-fit-cover shadow-xs" style="width: 55px; height: 55px;" alt="<?= sanitize($rec['name']) ?>">
                <div>
                  <span class="food-type-icon <?= !empty($rec['is_veg']) ? 'veg' : 'non-veg' ?>"></span>
                  <div class="fw-bold text-white small"><?= sanitize($rec['name']) ?></div>
                  <div class="text-mint fw-bold small"><?= formatPrice($rec['price']) ?></div>
                </div>
              </div>
              <div class="d-flex align-items-center justify-content-between pt-2 border-top border-white border-opacity-10">
                <span class="badge bg-white bg-opacity-10 text-white-50" style="font-size: 0.7rem;">Conf: <?= round($rec['confidence'] * 100) ?>%</span>
                <button class="btn btn-sm btn-light rounded-pill fw-bold text-success px-3" onclick="addToCart(<?= $rec['item_id'] ?>, '<?= addslashes($rec['name']) ?>', <?= $rec['price'] ?>, <?= !empty($rec['is_veg']) ? 'true' : 'false' ?>)">
                  + Add
                </button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- =========================================================================
         FILTER CONTROLS & MENU NAVIGATION
         ========================================================================= -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 py-3 border-bottom mb-4 bg-white px-3 rounded-3">
      <!-- Veg Only Toggle -->
      <form method="GET" action="restaurant.php" id="vegFilterForm" class="d-flex align-items-center gap-2 m-0">
        <?php if (!empty($search)): ?>
          <input type="hidden" name="search" value="<?= sanitize($search) ?>">
        <?php endif; ?>
        <div class="form-check form-switch d-flex align-items-center gap-2 p-0 m-0">
          <label class="form-check-label fw-bold small text-pine cursor-pointer" for="vegOnlySwitch">
            <span class="food-type-icon veg me-1"></span> VEG ONLY
          </label>
          <input class="form-check-input ms-2 cursor-pointer" type="checkbox" name="veg" value="1" id="vegOnlySwitch" style="width: 2.8em; height: 1.4em;" <?= $vegOnly ? 'checked' : '' ?> onchange="document.getElementById('vegFilterForm').submit()">
        </div>
      </form>

      <!-- Category Filter Pills -->
      <div class="d-flex align-items-center gap-2 overflow-x-auto scrollbar-none">
        <a href="#category-bestsellers" class="btn btn-sm btn-outline-success rounded-pill fw-bold px-3">Recommended</a>
        <?php foreach ($categories as $cat): ?>
          <a href="#cat-<?= sanitize($cat['category_slug']) ?>" class="btn btn-sm btn-outline-success rounded-pill fw-bold px-3">
            <?= sanitize($cat['category_name']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- =========================================================================
         MENU CATEGORIES & DISH LISTINGS
         ========================================================================= -->
    <?php if (empty($menuGrouped)): ?>
      <div class="text-center py-5 bg-white rounded-4 border">
        <i class="bi bi-search text-muted fs-1 mb-2"></i>
        <h5 class="fw-bold text-pine">No dishes found</h5>
        <p class="text-muted small">Try turning off Veg Only filter or search for another item.</p>
        <a href="restaurant.php" class="btn btn-sm btn-success rounded-pill px-4 fw-bold">Reset Filters</a>
      </div>
    <?php endif; ?>

    <?php foreach ($menuGrouped as $categoryName => $items): ?>
      <?php $catSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $categoryName)); ?>
      <section class="menu-category-section mb-5" id="cat-<?= $catSlug ?>">
        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
          <h4 class="fw-bold text-pine mb-0"><?= sanitize($categoryName) ?> (<?= count($items) ?>)</h4>
          <span class="badge bg-light text-muted border"><?= sanitize($categoryName) ?></span>
        </div>

        <div class="d-flex flex-column gap-3">
          <?php foreach ($items as $dish): ?>
            <div class="menu-dish-card p-3 p-md-4 bg-white rounded-4 border shadow-xs d-flex justify-content-between align-items-center gap-3">
              <!-- Dish Details -->
              <div class="flex-grow-1" style="max-width: 650px;">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="food-type-icon <?= $dish['is_veg'] ? 'veg' : 'non-veg' ?>"></span>
                  <?php if (!empty($dish['is_bestseller'])): ?>
                    <span class="badge bg-warning bg-opacity-25 text-dark fw-bold small"><i class="bi bi-star-fill text-warning me-1"></i> Bestseller</span>
                  <?php endif; ?>
                  <span class="badge bg-light text-muted border small"><i class="bi bi-clock me-1"></i> <?= (int)$dish['prep_time_minutes'] ?>m prep</span>
                </div>
                <h5 class="fw-bold text-pine mb-1"><?= sanitize($dish['item_name']) ?></h5>
                <div class="fw-bold text-pine fs-6 mb-2"><?= formatPrice($dish['price']) ?></div>
                <p class="small text-muted mb-0 dish-desc-clamp"><?= sanitize($dish['description']) ?></p>
              </div>

              <!-- Dish Image & Add Button -->
              <div class="text-center flex-shrink-0" style="width: 140px;">
                <div class="position-relative mb-2">
                  <img src="<?= sanitize($dish['image_url']) ?>" class="rounded-4 object-fit-cover shadow-xs" style="width: 130px; height: 110px;" alt="<?= sanitize($dish['item_name']) ?>">
                </div>
                <button class="btn btn-sm btn-success rounded-pill px-4 fw-bold shadow-xs dish-add-btn" 
                        data-dish-id="<?= $dish['item_id'] ?>"
                        onclick="addToCart(<?= $dish['item_id'] ?>, '<?= addslashes($dish['item_name']) ?>', <?= $dish['price'] ?>, <?= $dish['is_veg'] ? 'true' : 'false' ?>)" 
                        style="background-color: var(--lush-forest);">
                  ADD
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>

  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
