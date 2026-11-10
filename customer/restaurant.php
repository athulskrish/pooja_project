<?php
declare(strict_types=1);
/**
 * Restaurant Menu & Ordering Page
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Meghana Foods - Menu & Online Ordering';
$activePage = 'restaurant';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/AprioriEngine.php';

$activeUser = getCurrentUser();
$catalogData = CatalogService::getRestaurantCatalog(($activeUser && !empty($activeUser['id'])) ? (int)$activeUser['id'] : null);
$aprioriRecs = $catalogData['aprioriRecs'];
$categories = $catalogData['categories'];
$itemsByCategory = $catalogData['itemsByCategory'];
$customSummaryMap = $catalogData['customSummaryMap'];
$favoriteIds = $catalogData['favoriteIds'] ?? [];
$userDietaryPrefs = getUserDietaryPreferences(($activeUser && !empty($activeUser['id'])) ? (int)$activeUser['id'] : null);
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
        <div class="card border-0 rounded-4 shadow-sm p-3 mb-4 bg-white d-flex flex-wrap flex-row justify-content-between align-items-center gap-2">
          <div class="fw-bold text-dark">
            <i class="fa-solid fa-book-open text-success me-2"></i> Menu Catalog
          </div>
          
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
              <input class="form-check-input" type="checkbox" role="switch" id="vegOnlySwitch" style="cursor: pointer;">
              <label class="form-check-label fw-bold small text-success mb-0" for="vegOnlySwitch" style="cursor: pointer;">
                Pure Veg Only
              </label>
            </div>
            <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
              <input class="form-check-input" type="checkbox" role="switch" id="healthyOnlySwitch" style="cursor: pointer;">
              <label class="form-check-label fw-bold small text-success mb-0" for="healthyOnlySwitch" style="cursor: pointer;">
                <i class="fa-solid fa-leaf text-success me-1"></i>Healthy Only
              </label>
            </div>
            <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
              <input class="form-check-input" type="checkbox" role="switch" id="restaurantFavoritesOnlySwitch" style="cursor: pointer;">
              <label class="form-check-label fw-bold small text-danger mb-0" for="restaurantFavoritesOnlySwitch" style="cursor: pointer;">
                <i class="bi bi-heart-fill me-1"></i>Favorites
              </label>
            </div>
            <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
              <input class="form-check-input" type="checkbox" role="switch" id="restaurantAllergenFreeSwitch" style="cursor: pointer;">
              <label class="form-check-label fw-bold small text-dark mb-0" for="restaurantAllergenFreeSwitch" style="cursor: pointer;">
                <i class="bi bi-shield-check text-success me-1"></i>Allergen-Free
              </label>
            </div>
            <div class="d-flex align-items-center gap-1">
              <label for="restaurantMoodSelect" class="small fw-bold text-muted d-none d-sm-inline">Mood:</label>
              <select class="form-select form-select-sm rounded-pill border-success" id="restaurantMoodSelect" style="width: auto; font-size: 0.8rem;">
                <option value="all">🎭 All Moods</option>
                <option value="comfort">🛋️ Comfort & Soul</option>
                <option value="party">🎉 Party & Celebration</option>
                <option value="healthy">⚡ Energetic & Fit</option>
                <option value="quick_bite">🍿 Quick Bites</option>
                <option value="spicy">🔥 Fiery & Spicy</option>
                <option value="sweet">💖 Sweet Craving</option>
              </select>
            </div>
          </div>
        </div>

        <!-- =======================================================================
             ALGORITHM 1: APRIORI MARKET BASKET ANALYSIS (FREQUENTLY ORDERED TOGETHER)
             ======================================================================= -->
        <div class="card border-0 rounded-4 shadow-sm p-4 mb-4 bg-white" id="aprioriRecommendationsCard">
          <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
            <div>
              <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill px-2 py-1 fw-bold" style="background:#CEFFDC; color:#243E2C; font-size:0.7rem;">
                  <i class="fa-solid fa-brain me-1"></i> APRIORI ALGORITHM
                </span>
                <span class="text-muted small">Market Basket Analysis</span>
              </div>
              <h5 class="fw-bold mb-0 text-dark mt-1">Frequently Ordered Together</h5>
              <div class="small text-muted" id="aprioriSubtitle">Complementary dishes frequently paired with items in your basket</div>
            </div>
            <span class="badge bg-light text-success border px-2 py-1 small">
              <i class="bi bi-stars me-1"></i> Smart Pairings
            </span>
          </div>

          <div class="row g-3" id="aprioriItemsContainer">
            <?php foreach ($aprioriRecs as $rec): ?>
              <div class="col-sm-6 col-md-3">
                <div class="card h-100 border rounded-3 p-2 bg-light bg-opacity-50 text-center d-flex flex-column justify-content-between">
                  <div class="position-relative mb-2">
                    <img src="<?= e(getDishImageUrl($rec['image_url'] ?? '')) ?>" 
                         alt="<?= e($rec['item_name']) ?>" 
                         class="rounded-3 w-100 object-fit-cover" 
                         style="height: 100px;">
                    <span class="position-absolute top-0 start-0 m-1 badge bg-white text-dark shadow-sm" style="font-size:0.65rem;">
                      <?= $rec['is_veg'] ? '🟢 VEG' : '🔴 NON-VEG' ?>
                    </span>
                  </div>
                  <h6 class="fw-bold text-dark small mb-1 text-truncate" title="<?= e($rec['item_name']) ?>"><?= e($rec['item_name']) ?></h6>
                  <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                    <span class="dish-rating-badge" style="font-size: 0.68rem; padding: 2px 7px;" title="<?= number_format((float)(!empty($rec['rating']) ? $rec['rating'] : 4.8), 1) ?> stars">
                      <i class="bi bi-star-fill rating-star"></i>
                      <span class="rating-val"><?= number_format((float)(!empty($rec['rating']) ? $rec['rating'] : 4.8), 1) ?></span>
                    </span>
                  </div>
                  <div class="text-muted small mb-1"><?= formatInr($rec['price']) ?></div>
                  <?php if (!empty($customSummaryMap[$rec['item_id']]['group_count'])): ?>
                    <div class="text-success small fw-medium mb-2" style="font-size:0.65rem;"><i class="bi bi-sliders me-1"></i>Customisable</div>
                  <?php else: ?>
                    <div class="mb-2" style="height: 15px;"></div>
                  <?php endif; ?>
                  <button type="button" 
                          class="btn btn-sm btn-outline-success rounded-pill fw-bold w-100 py-1"
                          onclick="addToCart(<?= (int)$rec['item_id'] ?>, <?= e(jsJson((string)$rec['item_name'])) ?>, <?= (float)$rec['price'] ?>, <?= $rec['is_veg'] ? 'true' : 'false' ?>, 'Meghana Foods', <?= e(jsJson((string)getDishImageUrl($rec['image_url'] ?? ''))) ?>); if (typeof refreshAprioriRecommendations === 'function') refreshAprioriRecommendations();">
                    <i class="bi bi-plus-lg me-1"></i> Add
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
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
                <?php 
                  $isHealthyDish = (!empty($dish['is_healthy']) || ($cat['category_slug'] ?? '') === 'healthy' || stripos($cat['category_name'] ?? '', 'healthy') !== false || stripos($dish['item_name'] ?? '', 'salad') !== false || stripos($dish['item_name'] ?? '', 'quinoa') !== false);
                  $dishPrimaryMood = getDishPrimaryMood($dish['mood'] ?? 'comfort');
                  $isFav = in_array((int)$dish['item_id'], $favoriteIds, true);
                  $dietMeta = evaluateDishDietaryConflicts($dish, $userDietaryPrefs);
                ?>
                <div class="d-flex justify-content-between align-items-start gap-3 pb-3 border-bottom dish-item-row" data-item-id="<?= (int)$dish['item_id'] ?>" data-favorite="<?= $isFav ? 'true' : 'false' ?>" data-allergens="<?= e(implode(',', $dietMeta['allergens'])) ?>" data-veg="<?= $dish['is_veg'] ? 'true' : 'false' ?>" data-is-veg="<?= $dish['is_veg'] ? 'true' : 'false' ?>" data-healthy="<?= $isHealthyDish ? 'true' : 'false' ?>" data-mood="<?= e($dish['mood'] ?? 'comfort') ?>">
                  
                  <!-- Dish Info -->
                  <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                      <?php if ($dish['is_veg']): ?>
                        <span class="badge bg-white text-success border border-success d-flex align-items-center gap-1 px-2 py-0" style="font-size:0.65rem;">
                          <span style="width:6px; height:6px; border-radius:50%; background:#229947; display:inline-block;"></span> VEG
                        </span>
                      <?php else: ?>
                        <span class="badge bg-white text-danger border border-danger d-flex align-items-center gap-1 px-2 py-0" style="font-size:0.65rem;">
                          <span style="width:6px; height:6px; border-radius:50%; background:#D32F2F; display:inline-block;"></span> NON-VEG
                        </span>
                      <?php endif; ?>

                      <?php if ($isHealthyDish): ?>
                        <span class="badge px-2 py-0 fw-semibold" style="background:#CEFFDC; color:#243E2C; font-size:0.65rem; border:1px solid #68BA7F;">
                          <i class="fa-solid fa-leaf me-1 text-success"></i> HEALTHY
                        </span>
                      <?php endif; ?>

                      <?php if ($dishPrimaryMood): ?>
                        <span class="badge px-2 py-0 fw-semibold d-inline-flex align-items-center gap-1" style="background:<?= e($dishPrimaryMood['badge_bg']) ?>; color:<?= e($dishPrimaryMood['badge_color']) ?>; font-size:0.65rem; border:1px solid <?= e($dishPrimaryMood['border_color']) ?>;">
                          <img src="<?= e(getMoodImageUrl($dishPrimaryMood['id'])) ?>" alt="" style="width: 12px; height: 12px; border-radius: 50%; object-fit: cover;">
                          <?= e($dishPrimaryMood['emoji']) ?> <?= e($dishPrimaryMood['short_name']) ?>
                        </span>
                      <?php endif; ?>

                      <span class="badge bg-light text-muted small"><i class="bi bi-clock me-1"></i><?= (int)$dish['prep_time_minutes'] ?>m</span>
                    </div>

                    <h5 class="fw-bold mb-1 text-dark"><?= e($dish['item_name']) ?></h5>
                    
                    <!-- Star Rating & Review Count -->
                    <div class="d-flex align-items-center gap-2 mb-1">
                      <span class="dish-rating-badge" title="<?= number_format((float)(!empty($dish['rating']) ? $dish['rating'] : 4.8), 1) ?> out of 5 stars">
                        <i class="bi bi-star-fill rating-star"></i>
                        <span class="rating-val"><?= number_format((float)(!empty($dish['rating']) ? $dish['rating'] : 4.8), 1) ?></span>
                      </span>
                      <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-muted small open-dish-reviews-btn" 
                              style="font-size: 0.72rem;" 
                              onclick="openDishReviewsModal(<?= (int)$dish['item_id'] ?>, <?= e(jsJson((string)$dish['item_name'])) ?>, <?= (float)(!empty($dish['rating']) ? $dish['rating'] : 4.8) ?>, <?= (int)($dish['total_reviews'] ?? 120) ?>)">
                        (<?= (int)($dish['total_reviews'] ?? 120) ?> reviews)
                      </button>
                    </div>

                    <div class="d-flex align-items-center gap-2 mb-1">
                      <span class="fw-bold text-forest fs-5"><?= formatInr($dish['price']) ?></span>
                      <?php if (!empty($customSummaryMap[$dish['item_id']]['group_count'])): ?>
                        <span class="badge rounded-pill text-success bg-success-subtle border border-success-subtle" style="font-size: 0.68rem; font-weight: 500;">
                          <i class="bi bi-sliders me-1"></i>Customisable
                        </span>
                      <?php endif; ?>
                    </div>
                    <p class="text-muted small mb-2" style="max-width: 500px;">
                      <?= e($dish['description']) ?>
                    </p>
                    <?= renderDishDietaryMetadataHtml($dish, $userDietaryPrefs) ?>
                  </div>

                  <!-- Dish Image & ADD Control -->
                  <div class="position-relative text-center flex-shrink-0" style="width: 130px;">
                    <img src="<?= e(getDishImageUrl($dish['image_url'])) ?>" alt="<?= e($dish['item_name']) ?>" class="rounded-4 object-fit-cover shadow-sm w-100" style="height: 100px;">
                    <div class="position-absolute top-0 end-0 m-1">
                      <?= renderDishFavoriteButtonHtml((int)$dish['item_id'], $isFav) ?>
                    </div>
                    
                    <div class="position-absolute bottom-0 start-50 translate-middle-x dish-qty-control-slot" data-dish-id="<?= (int)$dish['item_id'] ?>" data-dish-name="<?= e($dish['item_name']) ?>" data-dish-price="<?= (float)$dish['price'] ?>" data-dish-veg="<?= $dish['is_veg'] ? 'true' : 'false' ?>" data-dish-img="<?= e(getDishImageUrl($dish['image_url'])) ?>" style="margin-bottom: -14px;">
                      <button 
                        type="button" 
                        class="btn m3-add-btn rounded-pill shadow-sm px-3 py-1 fw-bold"
                        onclick="addToCart(<?= (int)$dish['item_id'] ?>, <?= e(jsJson((string)$dish['item_name'])) ?>, <?= (float)$dish['price'] ?>, <?= $dish['is_veg'] ? 1 : 0 ?>, 'Meghana Foods', <?= e(jsJson((string)getDishImageUrl($dish['image_url']))) ?>)"
                      >
                        ADD <i class="bi bi-plus-lg ms-1"></i>
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

  // Dynamically update Apriori cross-sell recommendations based on active cart
  if (typeof cart !== 'undefined' && cart.length > 0) {
    refreshAprioriRecommendations();
  }
});

function refreshAprioriRecommendations() {
  if (typeof cart === 'undefined' || cart.length === 0) return;
  const cartIds = cart.map(i => i.id).join(',');
  fetch('<?= CUSTOMER_URL ?>/ajax/apriori-api.php?item_ids=' + encodeURIComponent(cartIds) + '&limit=4')
    .then(r => r.json())
    .then(data => {
      if (data && data.recommendations && data.recommendations.length > 0) {
        const container = document.getElementById('aprioriItemsContainer');
        const subtitle = document.getElementById('aprioriSubtitle');
        if (!container) return;
        if (subtitle) {
          subtitle.textContent = 'Apriori recommendations calculated from ' + cart.length + ' item(s) in your cart';
        }
        container.innerHTML = data.recommendations.map(rec => `
          <div class="col-sm-6 col-md-3">
            <div class="card h-100 border rounded-3 p-2 bg-light bg-opacity-50 text-center d-flex flex-column justify-content-between">
              <div class="position-relative mb-2">
                <img src="${rec.image_url || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300'}" 
                     alt="${rec.item_name}" 
                     class="rounded-3 w-100 object-fit-cover" 
                     style="height: 100px;">
                <span class="position-absolute top-0 start-0 m-1 badge bg-white text-dark shadow-sm" style="font-size:0.65rem;">
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
                      onclick="addToCart(${rec.item_id}, '${rec.item_name.replace(/'/g, "\\'")}', ${rec.price}, ${rec.is_veg == 1}, 'Meghana Foods', '${rec.image_url || ''}'); refreshAprioriRecommendations();">
                <i class="bi bi-plus-lg me-1"></i> Add
              </button>
            </div>
          </div>
        `).join('');
      }
    })
    .catch(err => console.log('Apriori refresh error:', err));
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
