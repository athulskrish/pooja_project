<?php
declare(strict_types=1);
/**
 * Interactive Menu & Dish Search
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Search Dishes, Cuisines & Restaurants';
$activePage = 'search';

require_once __DIR__ . '/includes/header.php';

$query = cleanInput($_GET['q'] ?? '');
$categorySlug = cleanInput($_GET['category'] ?? '');
$vegOnly = !empty($_GET['veg_only']);
$healthyOnly = !empty($_GET['healthy_only']);
$highProtein = !empty($_GET['high_protein']);
$allergenFree = strtolower(cleanInput($_GET['allergen_free'] ?? ''));
$moodFilter = cleanInput($_GET['mood'] ?? '');
$sort = cleanInput($_GET['sort'] ?? 'recommended');

$activeUser = getCurrentUser();
$uid = ($activeUser && !empty($activeUser['id'])) ? (int)$activeUser['id'] : null;

$searchResult = CatalogService::searchDishes($query, $categorySlug, $vegOnly, $healthyOnly, $moodFilter, $sort, $allergenFree, $highProtein, $uid);
$dishes = $searchResult['dishes'];
$categories = $searchResult['categories'];
$foodMoods = $searchResult['foodMoods'];
$customSummaryMap = $searchResult['customSummaryMap'];
$favoriteIds = $searchResult['favoriteIds'] ?? [];
$userDietaryPrefs = getUserDietaryPreferences($uid);

$allergenOptions = getDishAllergenOptions();
$buildSearchUrl = static function (array $overrides = []) use ($query, $categorySlug, $vegOnly, $healthyOnly, $highProtein, $allergenFree, $moodFilter, $sort): string {
    $params = array_merge([
        'q' => $query,
        'category' => $categorySlug,
        'veg_only' => $vegOnly ? '1' : '0',
        'healthy_only' => $healthyOnly ? '1' : '0',
        'high_protein' => $highProtein ? '1' : '0',
        'allergen_free' => $allergenFree,
        'mood' => $moodFilter,
        'sort' => $sort,
    ], $overrides);
    return CUSTOMER_URL . '/search.php?' . http_build_query($params);
};
?>

<main class="py-4">
  <div class="container-xl">
    
    <!-- Search Bar Hero -->
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
      <form method="GET" action="<?= CUSTOMER_URL ?>/search.php" class="row g-2 align-items-center">
        <div class="col-lg-5">
          <div class="input-group">
            <span class="input-group-text bg-white border-end-0 text-success"><i class="bi bi-search fs-5"></i></span>
            <input type="text" name="q" class="form-control border-start-0 py-3 ps-0" placeholder="Search for Biryani, Quinoa Bowls, Healthy Salads, Butter Chicken, Dosa..." value="<?= e($query) ?>">
          </div>
        </div>

        <div class="col-lg-3 col-md-4">
          <select name="category" class="form-select py-3">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= e($cat['category_slug']) ?>" <?= ($categorySlug === $cat['category_slug']) ? 'selected' : '' ?>>
                <?= e($cat['category_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-lg-2 col-md-4">
          <select name="mood" class="form-select py-3">
            <option value="">🎭 All Moods</option>
            <?php foreach ($foodMoods as $mKey => $mInfo): ?>
              <option value="<?= e($mKey) ?>" <?= ($moodFilter === $mKey) ? 'selected' : '' ?>>
                <?= e($mInfo['emoji']) ?> <?= e($mInfo['short_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-lg-2 col-md-4">
          <button type="submit" class="btn btn-success w-100 py-3 fw-bold rounded-3 shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
            Search Menu
          </button>
        </div>

        <!-- Filter Chips Sub-row -->
        <div class="col-12 mt-3 pt-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="small fw-bold text-muted">Quick Filters:</span>
            
            <a href="<?= e($buildSearchUrl(['veg_only' => $vegOnly ? '0' : '1'])) ?>" 
               class="btn btn-sm rounded-pill <?= $vegOnly ? 'btn-success text-white' : 'btn-outline-success' ?> d-flex align-items-center gap-1">
              <span style="width:8px; height:8px; border-radius:50%; background:<?= $vegOnly ? '#fff' : '#229947' ?>; display:inline-block;"></span>
              Pure Veg <?= $vegOnly ? '✓' : '' ?>
            </a>

            <a href="<?= e($buildSearchUrl(['healthy_only' => $healthyOnly ? '0' : '1'])) ?>" 
               class="btn btn-sm rounded-pill <?= $healthyOnly ? 'btn-success text-white' : 'btn-outline-success' ?> d-flex align-items-center gap-1">
              <i class="fa-solid fa-leaf"></i>
              Healthy Food <?= $healthyOnly ? '✓' : '' ?>
            </a>

            <a href="<?= e($buildSearchUrl(['high_protein' => $highProtein ? '0' : '1'])) ?>" 
               class="btn btn-sm rounded-pill <?= $highProtein ? 'btn-primary text-white' : 'btn-outline-primary' ?> d-flex align-items-center gap-1">
              <i class="bi bi-lightning-charge-fill"></i>
              High Protein <?= $highProtein ? '✓' : '' ?>
            </a>

            <!-- Allergen-Free Filter Dropdown -->
            <div class="dropdown d-inline-block">
              <button class="btn btn-sm rounded-pill <?= $allergenFree !== '' ? 'btn-warning text-dark fw-semibold' : 'btn-outline-secondary' ?> dropdown-toggle d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-shield-check"></i>
                <span><?= $allergenFree !== '' ? ('Free of ' . e(ucfirst($allergenFree))) : 'Avoid Allergen' ?></span>
              </button>
              <ul class="dropdown-menu shadow-sm border-0 rounded-4 mt-1">
                <li><a class="dropdown-item small <?= $allergenFree === '' ? 'active fw-bold' : '' ?>" href="<?= e($buildSearchUrl(['allergen_free' => ''])) ?>">Show All Allergens</a></li>
                <li><hr class="dropdown-divider my-1"></li>
                <?php foreach ($allergenOptions as $aKey => $aLabel): ?>
                  <li>
                    <a class="dropdown-item small <?= ($allergenFree === $aKey) ? 'active fw-bold' : '' ?>" href="<?= e($buildSearchUrl(['allergen_free' => $aKey])) ?>">
                      Avoid <?= e($aLabel) ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>

            <!-- Mood Filter Dropdown Pill -->
            <div class="dropdown d-inline-block">
              <button class="btn btn-sm rounded-pill <?= !empty($moodFilter) ? 'btn-success text-white' : 'btn-outline-secondary' ?> dropdown-toggle d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown">
                <span>🎭 <?= !empty($moodFilter) && isset($foodMoods[$moodFilter]) ? e($foodMoods[$moodFilter]['emoji']) . ' ' . e($foodMoods[$moodFilter]['short_name']) : 'Filter by Mood' ?></span>
              </button>
              <ul class="dropdown-menu shadow-sm border-0 rounded-4 mt-1">
                <li><a class="dropdown-item small" href="<?= e($buildSearchUrl(['mood' => ''])) ?>">🎭 All Moods</a></li>
                <li><hr class="dropdown-divider my-1"></li>
                <?php foreach ($foodMoods as $mKey => $mInfo): ?>
                  <li>
                    <a class="dropdown-item small d-flex align-items-center gap-2 <?= ($moodFilter === $mKey) ? 'active fw-bold' : '' ?>" href="<?= e($buildSearchUrl(['mood' => $mKey])) ?>">
                      <img src="<?= e(getMoodImageUrl($mKey)) ?>" alt="" style="width: 18px; height: 18px; border-radius: 50%; object-fit: cover;">
                      <span><?= e($mInfo['emoji']) ?> <?= e($mInfo['name']) ?></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>

            <a href="<?= e($buildSearchUrl(['sort' => 'price_asc'])) ?>" 
               class="btn btn-sm rounded-pill <?= ($sort === 'price_asc') ? 'btn-dark text-white' : 'btn-light border' ?>">
              Price: Low to High
            </a>

            <a href="<?= e($buildSearchUrl(['sort' => 'protein_desc'])) ?>" 
               class="btn btn-sm rounded-pill <?= ($sort === 'protein_desc') ? 'btn-dark text-white' : 'btn-light border' ?>">
              Highest Protein
            </a>

            <a href="<?= e($buildSearchUrl(['sort' => 'prep_time'])) ?>" 
               class="btn btn-sm rounded-pill <?= ($sort === 'prep_time') ? 'btn-dark text-white' : 'btn-light border' ?>">
              Fastest Prep Time (< 15m)
            </a>
          </div>

          <div class="small text-muted">
            Found <strong><?= count($dishes) ?></strong> delicious matches
          </div>
        </div>
      </form>
    </div>

    <!-- Search Results Grid -->
    <div class="row g-3 g-md-4">
      <?php if (!empty($dishes)): ?>
        <?php foreach ($dishes as $dish): ?>
          <?php 
            $isHealthyDish = (!empty($dish['is_healthy']) || ($dish['category_slug'] ?? '') === 'healthy' || stripos($dish['category_name'] ?? '', 'healthy') !== false || stripos($dish['item_name'] ?? '', 'salad') !== false || stripos($dish['item_name'] ?? '', 'quinoa') !== false);
            $dishPrimaryMood = getDishPrimaryMood($dish['mood'] ?? 'comfort');
            $isFav = in_array((int)$dish['item_id'], $favoriteIds, true);
          ?>
          <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
            <div class="card h-100 border-0 rounded-4 shadow-sm overflow-hidden bg-white dish-catalog-card hover-lift m3-card" data-mood="<?= e($dish['mood'] ?? 'comfort') ?>">
              
              <div class="position-relative" style="height: 180px;">
                <img src="<?= e(getDishImageUrl($dish['image_url'])) ?>" alt="<?= e($dish['item_name']) ?>" class="w-100 h-100 object-fit-cover">
                
                <div class="position-absolute top-0 start-0 m-2 d-flex flex-column gap-1 align-items-start">
                  <span class="badge bg-white text-<?= $dish['is_veg'] ? 'success border-success' : 'danger border-danger' ?> border px-2 py-1 shadow-sm" style="font-size:0.65rem;">
                    <?= $dish['is_veg'] ? 'VEG' : 'NON-VEG' ?>
                  </span>
                  <?php if ($isHealthyDish): ?>
                    <span class="badge shadow-sm px-2 py-1" style="background:#CEFFDC; color:#1C2B20; font-size:0.65rem; border:1px solid #68BA7F;">
                      <i class="fa-solid fa-leaf me-1 text-success"></i> HEALTHY
                    </span>
                  <?php endif; ?>
                  <?php if ($dishPrimaryMood): ?>
                    <span class="badge shadow-sm px-2 py-1" style="background:<?= e($dishPrimaryMood['badge_bg']) ?>; color:<?= e($dishPrimaryMood['badge_color']) ?>; font-size:0.65rem; border:1px solid <?= e($dishPrimaryMood['border_color']) ?>;" title="Mood: <?= e($dishPrimaryMood['name']) ?>">
                      <?= e($dishPrimaryMood['emoji']) ?> <?= e($dishPrimaryMood['short_name']) ?>
                    </span>
                  <?php endif; ?>
                </div>

                <div class="position-absolute top-0 end-0 m-2 d-flex align-items-center gap-1">
                  <span class="badge bg-dark bg-opacity-75 text-white shadow-sm px-2 py-1" style="font-size:0.65rem;">
                    <i class="bi bi-clock me-1 text-mint"></i> <?= (int)$dish['prep_time_minutes'] ?>m
                  </span>
                  <?= renderDishFavoriteButtonHtml((int)$dish['item_id'], $isFav) ?>
                </div>
              </div>

              <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div>
                  <div class="small text-muted text-uppercase mb-1" style="font-size:0.65rem;"><?= e($dish['category_name']) ?></div>
                  <h6 class="fw-bold mb-1 text-dark text-truncate" title="<?= e($dish['item_name']) ?>"><?= e($dish['item_name']) ?></h6>
                  
                  <!-- Material Design 3 Star Rating & Reviews Link -->
                  <div class="d-flex align-items-center gap-2 mb-2">
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

                  <p class="text-muted small mb-2 dish-desc-clamp" style="min-height:36px;"><?= e($dish['description']) ?></p>
                  <?= renderDishDietaryMetadataHtml($dish, $userDietaryPrefs) ?>
                </div>

                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                  <div>
                    <span class="fw-bold fs-5 text-forest"><?= formatInr($dish['price']) ?></span>
                    <?php if (!empty($customSummaryMap[$dish['item_id']]['group_count'])): ?>
                      <div class="text-success small fw-medium" style="font-size:0.68rem;"><i class="bi bi-sliders me-1"></i>Customisable</div>
                    <?php endif; ?>
                  </div>
                  
                  <button 
                    type="button" 
                    class="btn m3-add-btn rounded-pill px-3 py-1 fw-bold shadow-sm"
                    onclick="addToCart(<?= (int)$dish['item_id'] ?>, <?= e(jsJson((string)$dish['item_name'])) ?>, <?= (float)$dish['price'] ?>, <?= $dish['is_veg'] ? 1 : 0 ?>, 'Meghana Foods', <?= e(jsJson((string)getDishImageUrl($dish['image_url']))) ?>)"
                  >
                    ADD <i class="bi bi-plus-lg ms-1"></i>
                  </button>
                </div>

              </div>

            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="col-12 text-center py-5">
          <i class="bi bi-search text-muted opacity-50 mb-3" style="font-size:3.5rem;"></i>
          <h4 class="fw-bold text-dark">No dishes matched your search</h4>
          <p class="text-muted small mb-4">Try searching for keywords like "Biryani", "Paneer", "Roll", or clear your filter parameters.</p>
          <a href="<?= CUSTOMER_URL ?>/search.php" class="btn btn-success rounded-pill px-4" style="background:#2E6F40; border-color:#2E6F40;">Reset All Filters</a>
        </div>
      <?php endif; ?>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
