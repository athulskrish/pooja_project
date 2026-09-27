<?php
/**
 * Live Search Page
 * MCSP-232: E-Commerce Platform for Food Order
 */
$pageTitle = 'Search Dishes & Cuisines - Zippy';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/models/MenuItem.php';

$query   = trim($_GET['q'] ?? '');
$vegOnly = isset($_GET['veg']) && $_GET['veg'] === '1';
$results = [];

if (!empty($query)) {
    $results = MenuItem::getAll(null, $vegOnly ? true : null, $query);
}
?>

  <main class="container-xl py-4" style="max-width: 860px;">
    
    <!-- Search Header -->
    <div class="mb-4">
      <h2 class="fw-bold text-pine mb-2">Search Dishes & Cuisines</h2>
      <p class="text-muted small">Find your favorite biryani, kebabs, curries, and drinks across all kitchens.</p>

      <form action="search.php" method="GET" class="mb-3">
        <div class="input-group input-group-lg shadow-sm rounded-pill overflow-hidden border">
          <span class="input-group-text bg-white border-0 ps-3 text-success">
            <i class="bi bi-search"></i>
          </span>
          <input type="text" name="q" class="form-control border-0 py-3" placeholder="Try 'Chicken Biryani', 'Butter Naan', 'Paneer'..." value="<?= sanitize($query) ?>" autofocus>
          <?php if ($vegOnly): ?>
            <input type="hidden" name="veg" value="1">
          <?php endif; ?>
          <button class="btn btn-success px-4 fw-bold" type="submit" style="background-color: var(--lush-forest);">
            Search
          </button>
        </div>
      </form>

      <!-- Veg Only Filter Toggle -->
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-2">
        <div class="d-flex align-items-center gap-2">
          <span class="small text-muted fw-bold">Popular Searches:</span>
          <a href="search.php?q=Biryani" class="badge bg-light text-pine border text-decoration-none">Biryani</a>
          <a href="search.php?q=Chicken" class="badge bg-light text-pine border text-decoration-none">Chicken 65</a>
          <a href="search.php?q=Naan" class="badge bg-light text-pine border text-decoration-none">Garlic Naan</a>
          <a href="search.php?q=Paneer" class="badge bg-light text-pine border text-decoration-none">Paneer</a>
          <a href="search.php?q=Jamun" class="badge bg-light text-pine border text-decoration-none">Gulab Jamun</a>
        </div>

        <form method="GET" action="search.php" class="m-0">
          <input type="hidden" name="q" value="<?= sanitize($query) ?>">
          <div class="form-check form-switch d-flex align-items-center gap-2 p-0 m-0">
            <label class="form-check-label fw-bold small text-pine cursor-pointer" for="searchVegSwitch">
              <span class="food-type-icon veg me-1"></span> VEG ONLY
            </label>
            <input class="form-check-input ms-2 cursor-pointer" type="checkbox" name="veg" value="1" id="searchVegSwitch" style="width: 2.6em; height: 1.3em;" <?= $vegOnly ? 'checked' : '' ?> onchange="this.form.submit()">
          </div>
        </form>
      </div>
    </div>

    <!-- Results Display -->
    <?php if (!empty($query)): ?>
      <div class="mb-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold text-pine mb-0">
          Search Results for "<?= sanitize($query) ?>" (<?= count($results) ?>)
        </h5>
        <?php if ($vegOnly): ?>
          <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Filtered: Veg Only</span>
        <?php endif; ?>
      </div>

      <?php if (empty($results)): ?>
        <div class="text-center py-5 bg-white rounded-4 border">
          <i class="bi bi-emoji-frown text-muted fs-1 mb-2"></i>
          <h5 class="fw-bold text-pine">No matches found for "<?= sanitize($query) ?>"</h5>
          <p class="text-muted small">Try checking for typos or searching a broader term like 'Biryani' or 'Rice'.</p>
          <a href="restaurant.php" class="btn btn-sm btn-success rounded-pill px-4 fw-bold">Browse Complete Menu</a>
        </div>
      <?php else: ?>
        <div class="d-flex flex-column gap-3">
          <?php foreach ($results as $item): ?>
            <div class="p-3 bg-white rounded-4 border shadow-xs d-flex justify-content-between align-items-center gap-3">
              <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="food-type-icon <?= $item['is_veg'] ? 'veg' : 'non-veg' ?>"></span>
                  <span class="badge bg-light text-muted border small"><?= sanitize($item['category_name'] ?? 'Dish') ?></span>
                  <span class="small text-success fw-bold"><i class="bi bi-star-fill text-warning me-1"></i><?= $item['rating'] ?></span>
                </div>
                <h6 class="fw-bold text-pine mb-1"><?= sanitize($item['item_name']) ?></h6>
                <div class="fw-bold text-pine small mb-1"><?= formatPrice($item['price']) ?></div>
                <p class="small text-muted mb-0 dish-desc-clamp" style="font-size: 0.78rem;"><?= sanitize($item['description']) ?></p>
              </div>
              <div class="text-center flex-shrink-0" style="width: 120px;">
                <img src="<?= sanitize($item['image_url']) ?>" class="rounded-3 object-fit-cover shadow-xs mb-2" style="width: 100px; height: 80px;" alt="<?= sanitize($item['item_name']) ?>">
                <button class="btn btn-sm btn-success rounded-pill px-3 fw-bold w-100 shadow-xs" 
                        onclick="addToCart(<?= $item['item_id'] ?>, '<?= addslashes($item['item_name']) ?>', <?= $item['price'] ?>, <?= $item['is_veg'] ? 'true' : 'false' ?>)"
                        style="background-color: var(--lush-forest);">
                  + ADD
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <!-- Initial State: Categories Inspiration -->
      <div class="text-center py-5 bg-white rounded-4 border">
        <i class="bi bi-search text-success fs-1 mb-3"></i>
        <h4 class="fw-bold text-pine mb-2">What are you craving today?</h4>
        <p class="text-muted small mb-4">Enter a dish or ingredient name above to search our fresh kitchen menu.</p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
          <a href="search.php?q=Biryani" class="btn btn-outline-success rounded-pill px-4 fw-bold">🍗 Chicken Biryani</a>
          <a href="search.php?q=Naan" class="btn btn-outline-success rounded-pill px-4 fw-bold">🫓 Garlic Naan</a>
          <a href="search.php?q=Paneer" class="btn btn-outline-success rounded-pill px-4 fw-bold">🧀 Paneer Tikka</a>
          <a href="search.php?q=Lassi" class="btn btn-outline-success rounded-pill px-4 fw-bold">🥭 Mango Lassi</a>
        </div>
      </div>
    <?php endif; ?>

  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
