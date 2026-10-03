<?php
/**
 * Interactive Menu & Dish Search
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Search Dishes, Cuisines & Restaurants';
$activePage = 'search';

require_once __DIR__ . '/includes/header.php';

$query = clean_input($_GET['q'] ?? '');
$categorySlug = clean_input($_GET['category'] ?? '');
$vegOnly = !empty($_GET['veg_only']) ? true : false;
$sort = clean_input($_GET['sort'] ?? 'recommended');

// Build SQL
$conditions = ["m.is_available = 1"];
$params = [];

if (!empty($query)) {
    $conditions[] = "(LOWER(m.item_name) LIKE :q OR LOWER(m.description) LIKE :q OR LOWER(c.category_name) LIKE :q)";
    $params[':q'] = '%' . strtolower($query) . '%';
}

if (!empty($categorySlug)) {
    $conditions[] = "c.category_slug = :slug";
    $params[':slug'] = $categorySlug;
}

if ($vegOnly) {
    $conditions[] = "m.is_veg = 1";
}

$orderBy = match($sort) {
    'price_asc' => "m.price ASC",
    'price_desc' => "m.price DESC",
    'prep_time' => "m.prep_time_minutes ASC",
    default => "m.stock_quantity DESC, m.item_id ASC"
};

$whereSql = implode(" AND ", $conditions);
$sql = "
    SELECT m.*, c.category_name, c.category_slug 
    FROM `menu_items` m
    JOIN `categories` c ON m.category_id = c.category_id
    WHERE $whereSql
    ORDER BY $orderBy
";

$dishes = db_fetch_all($sql, $params);
$categories = db_fetch_all("SELECT * FROM `categories` WHERE `is_active` = 1 ORDER BY `display_order` ASC");
?>

<main class="py-4">
  <div class="container-xl">
    
    <!-- Search Bar Hero -->
    <div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
      <form method="GET" action="<?= CUSTOMER_URL ?>/search.php" class="row g-2 align-items-center">
        <div class="col-lg-7">
          <div class="input-group">
            <span class="input-group-text bg-white border-end-0 text-success"><i class="bi bi-search fs-5"></i></span>
            <input type="text" name="q" class="form-control border-start-0 py-3 ps-0" placeholder="Search for Biryani, Butter Chicken, Naan, Burger, Dosa..." value="<?= e($query) ?>">
          </div>
        </div>

        <div class="col-lg-3 col-md-6">
          <select name="category" class="form-select py-3">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= e($cat['category_slug']) ?>" <?= ($categorySlug === $cat['category_slug']) ? 'selected' : '' ?>>
                <?= e($cat['category_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-lg-2 col-md-6">
          <button type="submit" class="btn btn-success w-100 py-3 fw-bold rounded-3 shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
            Search Menu
          </button>
        </div>

        <!-- Filter Chips Sub-row -->
        <div class="col-12 mt-3 pt-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="small fw-bold text-muted">Quick Filters:</span>
            
            <a href="<?= CUSTOMER_URL ?>/search.php?q=<?= urlencode($query) ?>&category=<?= urlencode($categorySlug) ?>&veg_only=<?= $vegOnly ? '0' : '1' ?>&sort=<?= urlencode($sort) ?>" 
               class="btn btn-sm rounded-pill <?= $vegOnly ? 'btn-success text-white' : 'btn-outline-success' ?> d-flex align-items-center gap-1">
              <span style="width:8px; height:8px; border-radius:50%; background:<?= $vegOnly ? '#fff' : '#229947' ?>; display:inline-block;"></span>
              Pure Veg <?= $vegOnly ? '✓' : '' ?>
            </a>

            <a href="<?= CUSTOMER_URL ?>/search.php?q=<?= urlencode($query) ?>&category=<?= urlencode($categorySlug) ?>&veg_only=<?= $vegOnly ? '1' : '0' ?>&sort=price_asc" 
               class="btn btn-sm rounded-pill <?= ($sort === 'price_asc') ? 'btn-dark text-white' : 'btn-light border' ?>">
              Price: Low to High
            </a>

            <a href="<?= CUSTOMER_URL ?>/search.php?q=<?= urlencode($query) ?>&category=<?= urlencode($categorySlug) ?>&veg_only=<?= $vegOnly ? '1' : '0' ?>&sort=prep_time" 
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
    <div class="row g-4">
      <?php if (!empty($dishes)): ?>
        <?php foreach ($dishes as $dish): ?>
          <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card h-100 border-0 rounded-4 shadow-sm overflow-hidden bg-white dish-catalog-card hover-lift">
              
              <div class="position-relative" style="height: 180px;">
                <img src="<?= e($dish['image_url']) ?>" alt="<?= e($dish['item_name']) ?>" class="w-100 h-100 object-fit-cover">
                
                <div class="position-absolute top-0 start-0 m-2">
                  <span class="badge bg-white text-<?= $dish['is_veg'] ? 'success border-success' : 'danger border-danger' ?> border px-2 py-1 shadow-sm" style="font-size:0.65rem;">
                    <?= $dish['is_veg'] ? 'VEG' : 'NON-VEG' ?>
                  </span>
                </div>

                <div class="position-absolute top-0 end-0 m-2">
                  <span class="badge bg-dark bg-opacity-75 text-white shadow-sm px-2 py-1" style="font-size:0.65rem;">
                    <i class="bi bi-clock me-1 text-mint"></i> <?= (int)$dish['prep_time_minutes'] ?>m
                  </span>
                </div>
              </div>

              <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div>
                  <div class="small text-muted text-uppercase mb-1" style="font-size:0.65rem;"><?= e($dish['category_name']) ?></div>
                  <h6 class="fw-bold mb-1 text-dark text-truncate" title="<?= e($dish['item_name']) ?>"><?= e($dish['item_name']) ?></h6>
                  <p class="text-muted small mb-3 dish-desc-clamp" style="min-height:36px;"><?= e($dish['description']) ?></p>
                </div>

                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                  <span class="fw-bold fs-5 text-forest"><?= format_inr($dish['price']) ?></span>
                  
                  <button 
                    type="button" 
                    class="btn btn-outline-success btn-sm rounded-pill px-3 fw-bold add-to-cart-btn"
                    onclick="addToCart(<?= (int)$dish['item_id'] ?>, '<?= addslashes(e($dish['item_name'])) ?>', <?= (float)$dish['price'] ?>, <?= $dish['is_veg'] ? 1 : 0 ?>, 'Meghana Foods')"
                  >
                    ADD +
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
