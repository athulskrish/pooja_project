<?php
declare(strict_types=1);
/**
 * Dish Catalog & Live Stock Inventory Management (CRUD)
 * Zippy - Online Food Ordering Platform
 * Design: Figma "Lush Forest" Palette (#Combination-4)
 */

$pageTitle = 'Dish Catalog & Inventory Management';
$activePage = 'dishes';

// Bootstrap core libraries before HTML output (prevents "headers already sent")
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$currentUser = getCurrentUser();

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=zippy_dishes_catalog_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Dish ID', 'Dish Name', 'Category', 'Kitchen Station', 'Diet Type', 'Healthy', 'Mood', 'Price (INR)', 'Stock Quantity', 'Prep Time (Mins)', 'Availability', 'Rating', 'Total Reviews', 'Created At']);

    $exportDishes = CatalogService::getDishesForExport();

    foreach ($exportDishes as $d) {
        fputcsv($output, [
            $d['item_id'],
            $d['item_name'],
            $d['category_name'],
            $d['kitchen_station'] ?? 'Main Kitchen',
            $d['is_veg'] ? 'Vegetarian' : 'Non-Vegetarian',
            !empty($d['is_healthy']) ? 'Yes' : 'No',
            $d['mood'] ?? 'comfort',
            $d['price'],
            $d['stock_quantity'],
            $d['prep_time_minutes'],
            $d['is_available'] ? 'Available' : 'Sold Out / 86',
            $d['rating'],
            $d['total_reviews'],
            $d['created_at']
        ]);
    }
    fclose($output);
    exit;
}

// Available Kitchen Stations
$kitchenStations = CatalogService::KITCHEN_STATIONS;

// Handle Add / Edit / Stock Update / Delete
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid session token.');
    } elseif ($_POST['action'] === 'add_dish') {
        $res = CatalogService::createDish($_POST);
        setFlash($res['type'], $res['message']);
        if (!empty($res['should_redirect'])) {
            header("Location: " . ADMIN_URL . "/dishes.php");
            exit;
        }
    } elseif ($_POST['action'] === 'edit_dish') {
        $res = CatalogService::updateDish($_POST);
        setFlash($res['type'], $res['message']);
        header("Location: " . ADMIN_URL . "/dishes.php");
        exit;
    } elseif ($_POST['action'] === 'quick_stock') {
        $res = CatalogService::adjustDishStock((int)($_POST['item_id'] ?? 0), (int)($_POST['delta'] ?? 0));
        setFlash($res['type'], $res['message']);
        header("Location: " . ADMIN_URL . "/dishes.php");
        exit;
    } elseif ($_POST['action'] === 'delete_dish') {
        $res = CatalogService::softDeleteDish((int)($_POST['item_id'] ?? 0));
        setFlash($res['type'], $res['message']);
        header("Location: " . ADMIN_URL . "/dishes.php");
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';

$dishesData = CatalogService::getAdminDishesData($_GET);
$categories = $dishesData['categories'];
$initialCatVer = $dishesData['initialCatVer'];
$initialDishVer = $dishesData['initialDishVer'];
$initialCombinedVer = $dishesData['initialCombinedVer'];
$kpiTotalDishes = $dishesData['kpiTotalDishes'];
$kpiAvailableDishes = $dishesData['kpiAvailableDishes'];
$kpiLowStockDishes = $dishesData['kpiLowStockDishes'];
$kpiSoldOutDishes = $dishesData['kpiSoldOutDishes'];
$kpiVegDishes = $dishesData['kpiVegDishes'];
$kpiNonVegDishes = $dishesData['kpiNonVegDishes'];
$kpiAvgPrice = $dishesData['kpiAvgPrice'];
$searchQuery = $dishesData['searchQuery'];
$filterCat = $dishesData['filterCat'];
$filterStation = $dishesData['filterStation'];
$filterDiet = $dishesData['filterDiet'];
$filterMood = $dishesData['filterMood'];
$filterStock = $dishesData['filterStock'];
$sortBy = $dishesData['sortBy'];
$foodMoods = $dishesData['foodMoods'];
$dishes = $dishesData['dishes'];
$customSummaryMap = $dishesData['customSummaryMap'];
$hasActiveFilters = $dishesData['hasActiveFilters'];
?>

<style>
  /* Custom UI Styles for Dishes Catalog */
  .dish-table-thumb {
    width: 48px;
    height: 48px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    cursor: pointer;
  }
  .dish-table-thumb:hover {
    transform: scale(1.6);
    z-index: 10;
    box-shadow: 0 8px 20px rgba(0,0,0,0.2) !important;
  }
  .diet-icon-fssai {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 17px;
    height: 17px;
    border-radius: 3px;
    background: #ffffff;
    vertical-align: middle;
  }
  .diet-icon-fssai.veg {
    border: 1.5px solid #229947;
  }
  .diet-icon-fssai.veg .diet-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #229947;
  }
  .diet-icon-fssai.nonveg {
    border: 1.5px solid #D32F2F;
  }
  .diet-icon-fssai.nonveg .diet-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #D32F2F;
  }
  .floating-bulk-bar {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%) translateY(120px);
    z-index: 1050;
    transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    background: #243E2C;
    box-shadow: 0 12px 30px rgba(36, 62, 44, 0.35);
  }
  .floating-bulk-bar.show {
    transform: translateX(-50%) translateY(0);
  }
</style>

<!-- Header & Quick Actions -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <h4 class="fw-bold font-outfit mb-1 text-dark">
      <i class="fa-solid fa-utensils text-forest me-2"></i>Menu Catalog & Live Inventory
    </h4>
    <p class="text-muted small mb-0">
      Manage live menu offerings, portion reserves, dietary tags, kitchen stations, and pricing.
    </p>
  </div>

  <div class="d-flex align-items-center gap-2 flex-wrap">
    <span class="badge bg-light text-forest border px-3 py-2 rounded-pill font-monospace shadow-xs d-inline-flex align-items-center gap-1" id="dishesLiveSyncBadge">
      <span class="live-pulse-dot green me-1"></span> Live Sync: <span id="dishesSyncTimer">5s</span>
    </span>

    <a href="<?= ADMIN_URL ?>/dishes.php?export=csv" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs">
      <i class="bi bi-file-earmark-arrow-down me-1"></i> Export CSV
    </a>

    <button type="button" class="btn btn-success rounded-pill px-3 btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addDishModal" style="background:#2E6F40; border-color:#2E6F40;">
      <i class="bi bi-plus-lg me-1"></i> Add New Dish
    </button>
  </div>
</div>

<!-- Real-time Catalog Alert Banner -->
<div id="dishesLiveAlert" class="alert alert-success border-2 shadow-sm rounded-4 d-none align-items-center justify-content-between p-3 mb-4" role="alert" style="background:#EDFBF2; border-color:#2E6F40;">
  <div class="d-flex align-items-center gap-2">
    <i class="fa-solid fa-bell text-forest fs-5"></i>
    <div>
      <strong class="text-forest" id="dishesLiveAlertTitle">Catalog Update Detected!</strong>
      <div class="small text-dark" id="dishesLiveAlertMsg">Menu items or category classifications were updated.</div>
    </div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-bold text-white shadow-xs" onclick="window.location.reload();" style="background:#2E6F40; border:none;">
      <i class="bi bi-arrow-clockwise me-1"></i> Refresh Catalog
    </button>
    <button type="button" class="btn-close" onclick="document.getElementById('dishesLiveAlert').classList.add('d-none'); document.getElementById('dishesLiveAlert').classList.remove('d-flex');"></button>
  </div>
</div>

<!-- Executive KPI Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-xs p-3 bg-white h-100 border-start border-4 border-success">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <span class="text-muted small fw-semibold">Total Catalog</span>
          <h3 class="fw-bold font-outfit text-dark mb-0 mt-1" id="kpiTotalDishes"><?= $kpiTotalDishes ?></h3>
          <div class="small text-muted mt-1" style="font-size:0.75rem;">
            <span class="text-success fw-bold"><?= $kpiVegDishes ?> Veg</span> • <span class="text-danger fw-bold"><?= $kpiNonVegDishes ?> Non-Veg</span>
          </div>
        </div>
        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:44px; height:44px; background:#EDFBF2; color:#2E6F40;">
          <i class="fa-solid fa-bowl-food fs-5"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-xs p-3 bg-white h-100 border-start border-4 border-primary">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <span class="text-muted small fw-semibold">Active & Serving</span>
          <h3 class="fw-bold font-outfit text-dark mb-0 mt-1"><?= $kpiAvailableDishes ?></h3>
          <div class="small text-muted mt-1" style="font-size:0.75rem;">
            Avg Price: <strong class="text-dark">₹<?= number_format($kpiAvgPrice, 0) ?></strong>
          </div>
        </div>
        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:44px; height:44px; background:#EBF3FF; color:#0D6EFD;">
          <i class="bi bi-check-circle-fill fs-5"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-xs p-3 bg-white h-100 border-start border-4 border-warning">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <span class="text-muted small fw-semibold">Low Stock Alert</span>
          <h3 class="fw-bold font-outfit text-warning mb-0 mt-1"><?= $kpiLowStockDishes ?></h3>
          <div class="small text-muted mt-1" style="font-size:0.75rem;">
            ≤ 15 portions remaining
          </div>
        </div>
        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:44px; height:44px; background:#FFF8E5; color:#E5A100;">
          <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-xs p-3 bg-white h-100 border-start border-4 border-danger">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <span class="text-muted small fw-semibold">86-List / Sold Out</span>
          <h3 class="fw-bold font-outfit text-danger mb-0 mt-1" id="kpiSoldOutDishes"><?= $kpiSoldOutDishes ?></h3>
          <div class="small text-muted mt-1" style="font-size:0.75rem;">
            Disabled or 0 portions
          </div>
        </div>
        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:44px; height:44px; background:#FDECE8; color:#D32F2F;">
          <i class="bi bi-slash-circle fs-5"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Search & Multi-Criteria Filter Bar -->
<div class="card border-0 rounded-4 shadow-sm p-3 bg-white mb-4">
  <form method="GET" action="<?= ADMIN_URL ?>/dishes.php" class="row g-2 align-items-center">
    <!-- Search Query -->
    <div class="col-12 col-md-3">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-light border-end-0 text-muted">
          <i class="bi bi-search"></i>
        </span>
        <input type="text" 
               name="search" 
               class="form-control border-start-0 bg-light" 
               placeholder="Search dish, description..." 
               value="<?= e($searchQuery) ?>">
      </div>
    </div>

    <!-- Category Filter -->
    <div class="col-6 col-md-2">
      <select name="category" class="form-select form-select-sm">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= (int)$cat['category_id'] ?>" <?= ($filterCat == $cat['category_id']) ? 'selected' : '' ?>>
            <?= e($cat['category_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Kitchen Station Filter -->
    <div class="col-6 col-md-2">
      <select name="station" class="form-select form-select-sm">
        <option value="">All Stations</option>
        <?php foreach ($kitchenStations as $stn): ?>
          <option value="<?= e($stn) ?>" <?= ($filterStation === $stn) ? 'selected' : '' ?>>
            <?= e($stn) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Diet Filter -->
    <div class="col-6 col-md-1">
      <select name="diet" class="form-select form-select-sm">
        <option value="">Diet: All</option>
        <option value="veg" <?= ($filterDiet === 'veg') ? 'selected' : '' ?>>Veg Only</option>
        <option value="non_veg" <?= ($filterDiet === 'non_veg') ? 'selected' : '' ?>>Non-Veg</option>
        <option value="healthy" <?= ($filterDiet === 'healthy') ? 'selected' : '' ?>>🌿 Healthy</option>
      </select>
    </div>

    <!-- Mood Filter -->
    <div class="col-6 col-md-2">
      <select name="mood" class="form-select form-select-sm">
        <option value="">Mood: All</option>
        <?php foreach ($foodMoods as $mKey => $mInfo): ?>
          <option value="<?= e($mKey) ?>" <?= ($filterMood === $mKey) ? 'selected' : '' ?>>
            <?= e($mInfo['emoji']) ?> <?= e($mInfo['short_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Stock Status Filter -->
    <div class="col-6 col-md-1">
      <select name="stock_status" class="form-select form-select-sm">
        <option value="">Stock: All</option>
        <option value="in_stock" <?= ($filterStock === 'in_stock') ? 'selected' : '' ?>>In Stock</option>
        <option value="low_stock" <?= ($filterStock === 'low_stock') ? 'selected' : '' ?>>Low Stock</option>
        <option value="out_of_stock" <?= ($filterStock === 'out_of_stock') ? 'selected' : '' ?>>Sold Out</option>
      </select>
    </div>

    <!-- Sort By -->
    <div class="col-6 col-md-1">
      <select name="sort" class="form-select form-select-sm">
        <option value="category" <?= ($sortBy === 'category') ? 'selected' : '' ?>>Default</option>
        <option value="name_asc" <?= ($sortBy === 'name_asc') ? 'selected' : '' ?>>Name A-Z</option>
        <option value="price_low" <?= ($sortBy === 'price_low') ? 'selected' : '' ?>>Price: Low-High</option>
        <option value="price_high" <?= ($sortBy === 'price_high') ? 'selected' : '' ?>>Price: High-Low</option>
        <option value="stock_low" <?= ($sortBy === 'stock_low') ? 'selected' : '' ?>>Stock: Low First</option>
        <option value="stock_high" <?= ($sortBy === 'stock_high') ? 'selected' : '' ?>>Stock: High First</option>
        <option value="rating" <?= ($sortBy === 'rating') ? 'selected' : '' ?>>Top Rated</option>
        <option value="prep_time" <?= ($sortBy === 'prep_time') ? 'selected' : '' ?>>Fastest Prep</option>
      </select>
    </div>

    <!-- Actions -->
    <div class="col-6 col-md-1 d-flex gap-1">
      <button type="submit" class="btn btn-success btn-sm w-100 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
        Filter
      </button>
      <?php if ($hasActiveFilters): ?>
        <a href="<?= ADMIN_URL ?>/dishes.php" class="btn btn-outline-secondary btn-sm" title="Clear All Filters">
          <i class="bi bi-x-lg"></i>
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Main Dishes Catalog Table Card -->
<div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div class="small text-muted">
      Showing <strong class="text-dark"><?= count($dishes) ?></strong> food items
      <?php if ($hasActiveFilters): ?>
        <span class="badge bg-light text-forest border ms-2">Filtered View</span>
      <?php endif; ?>
    </div>
    <div class="small text-muted">
      <i class="bi bi-info-circle me-1"></i> Use switches for instant 86-listing; stepper buttons adjust reserves without reload.
    </div>
  </div>

  <?php if (empty($dishes)): ?>
    <!-- Empty State -->
    <div class="text-center py-5">
      <div class="mb-3 text-muted opacity-50">
        <i class="fa-solid fa-utensils display-3"></i>
      </div>
      <h5 class="fw-bold text-dark">No dishes found</h5>
      <p class="text-muted small mb-3">We couldn't find any dishes matching your current search or filter criteria.</p>
      <div class="d-flex justify-content-center gap-2">
        <?php if ($hasActiveFilters): ?>
          <a href="<?= ADMIN_URL ?>/dishes.php" class="btn btn-outline-secondary rounded-pill px-3 btn-sm">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Clear Filters
          </a>
        <?php endif; ?>
        <button type="button" class="btn btn-success rounded-pill px-3 btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addDishModal" style="background:#2E6F40;">
          <i class="bi bi-plus-lg me-1"></i> Add New Dish
        </button>
      </div>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle small mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 38px;">
              <input class="form-check-input" type="checkbox" id="selectAllDishes" title="Select All Visible Dishes">
            </th>
            <th>Dish Item</th>
            <th>Category & Station</th>
            <th>Diet Type</th>
            <th>Price</th>
            <th>Live Stock</th>
            <th>Prep Time</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($dishes as $dish): ?>
            <?php
              $stock = (int)$dish['stock_quantity'];
              $isAvail = (bool)$dish['is_available'];
            ?>
            <tr id="dishRow<?= (int)$dish['item_id'] ?>">
              <!-- Checkbox -->
              <td>
                <input class="form-check-input dish-row-checkbox" 
                       type="checkbox" 
                       value="<?= (int)$dish['item_id'] ?>" 
                       data-name="<?= e($dish['item_name']) ?>">
              </td>

              <!-- Dish Item -->
              <td>
                <div class="d-flex align-items-center gap-3">
                  <div class="position-relative flex-shrink-0">
                    <img src="<?= e(getDishImageUrl($dish['image_url'])) ?>" 
                         alt="<?= e($dish['item_name']) ?>" 
                         class="rounded-3 object-fit-cover shadow-sm dish-table-thumb" 
                         onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300'">
                  </div>
                  <div>
                    <div class="fw-bold text-dark fs-6"><?= e($dish['item_name']) ?></div>
                    <div class="d-flex align-items-center gap-1 small text-warning mb-1">
                      <a href="<?= ADMIN_URL ?>/reviews.php?tab=dishes&search=<?= urlencode($dish['item_name']) ?>" 
                         class="text-decoration-none text-warning d-inline-flex align-items-center gap-1"
                         title="View customer feedback on this dish in Reviews console">
                        <i class="bi bi-star-fill"></i>
                        <span class="fw-bold text-dark"><?= number_format((float)($dish['rating'] ?? 4.5), 1) ?></span>
                        <span class="text-muted" style="font-size:0.75rem;">(<?= (int)($dish['total_reviews'] ?? 0) ?>)</span>
                      </a>
                    </div>
                    <?php if (!empty($dish['avg_taste'])): ?>
                      <div class="d-flex align-items-center gap-1 mb-1 flex-wrap" style="font-size:0.65rem;">
                        <span class="badge bg-light text-primary border py-0 px-1" title="Appearance Score">🎨 <?= e((string)$dish['avg_appearance']) ?>★</span>
                        <span class="badge bg-light text-danger border py-0 px-1" title="Taste Score">🍲 <?= e((string)$dish['avg_taste']) ?>★</span>
                        <span class="badge bg-light text-warning text-dark border py-0 px-1" title="Portion Size Score">🥣 <?= e((string)$dish['avg_portion']) ?>★</span>
                        <span class="badge bg-light text-success border py-0 px-1" title="Value Score">🏷️ <?= e((string)$dish['avg_value']) ?>★</span>
                      </div>
                    <?php endif; ?>
                    <div class="small text-muted text-truncate" style="max-width: 250px;" title="<?= e($dish['description']) ?>">
                      <?= e($dish['description']) ?>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Category & Station -->
              <td>
                <div class="fw-bold text-dark"><?= e($dish['category_name']) ?></div>
                <div class="small text-muted mt-1">
                  <i class="fa-solid fa-kitchen-set me-1 text-forest" style="font-size:0.75rem;"></i>
                  <span><?= e($dish['kitchen_station'] ?? 'Main Kitchen') ?></span>
                </div>
              </td>

              <!-- Diet Type -->
              <td>
                <div class="d-flex flex-column gap-1 align-items-start">
                  <?php if ($dish['is_veg']): ?>
                    <div class="d-inline-flex align-items-center gap-1">
                      <span class="diet-icon-fssai veg" title="Pure Vegetarian"><span class="diet-dot"></span></span>
                      <span class="text-success fw-bold" style="font-size:0.7rem;">VEG</span>
                    </div>
                  <?php else: ?>
                    <div class="d-inline-flex align-items-center gap-1">
                      <span class="diet-icon-fssai nonveg" title="Non-Vegetarian"><span class="diet-dot"></span></span>
                      <span class="text-danger fw-bold" style="font-size:0.7rem;">NON-VEG</span>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($dish['is_healthy'])): ?>
                    <span class="badge rounded-pill border px-2 py-1" style="background:#CEFFDC; color:#243E2C; font-size:0.65rem; border-color:#68BA7F !important;">
                      <i class="fa-solid fa-leaf me-1"></i>HEALTHY
                    </span>
                  <?php endif; ?>

                  <?php 
                    $dishMood = getDishPrimaryMood($dish['mood'] ?? 'comfort');
                    if ($dishMood):
                  ?>
                    <span class="badge rounded-pill border px-2 py-1 d-inline-flex align-items-center gap-1" style="background:<?= e($dishMood['badge_bg']) ?>; color:<?= e($dishMood['badge_color']) ?>; font-size:0.65rem; border-color:<?= e($dishMood['border_color']) ?> !important;" title="Mood: <?= e($dish['mood'] ?? '') ?>">
                      <img src="<?= e(getMoodImageUrl($dishMood['id'])) ?>" alt="" style="width: 12px; height: 12px; border-radius: 50%; object-fit: cover;">
                      <?= e($dishMood['emoji']) ?> <?= e($dishMood['short_name']) ?>
                    </span>
                  <?php endif; ?>
                </div>
              </td>

              <!-- Price -->
              <td class="fw-bold text-forest fs-6"><?= formatInr($dish['price']) ?></td>

              <!-- Live Stock (Tri-State Badge + AJAX Stepper) -->
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span id="stockBadge<?= (int)$dish['item_id'] ?>" 
                        class="badge rounded-pill px-2 py-1 fs-6 <?= ($stock <= 0) ? 'bg-danger text-white' : (($stock <= 15) ? 'bg-warning-subtle text-dark border border-warning' : 'bg-light text-dark border') ?>">
                    <?php if ($stock <= 0): ?>
                      0 (Sold Out)
                    <?php elseif ($stock <= 15): ?>
                      <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i><?= $stock ?> left
                    <?php else: ?>
                      <?= $stock ?> in stock
                    <?php endif; ?>
                  </span>

                  <!-- AJAX Stock Steppers -->
                  <div class="btn-group btn-group-sm" role="group">
                    <button type="button" 
                            class="btn btn-outline-secondary py-0 px-2 btn-stock-stepper" 
                            data-item-id="<?= (int)$dish['item_id'] ?>" 
                            data-delta="-5" 
                            title="Deduct 5 portions">
                      -5
                    </button>
                    <button type="button" 
                            class="btn btn-outline-success py-0 px-2 btn-stock-stepper" 
                            data-item-id="<?= (int)$dish['item_id'] ?>" 
                            data-delta="5" 
                            title="Add 5 portions">
                      +5
                    </button>
                    <button type="button" 
                            class="btn btn-outline-success py-0 px-2 btn-stock-stepper" 
                            data-item-id="<?= (int)$dish['item_id'] ?>" 
                            data-delta="10" 
                            title="Add 10 portions">
                      +10
                    </button>
                  </div>
                </div>
              </td>

              <!-- Prep Time -->
              <td><i class="bi bi-clock me-1 text-muted"></i> <?= (int)$dish['prep_time_minutes'] ?>m</td>

              <!-- Status (Instant 86-List AJAX Toggle) -->
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="form-check form-switch m-0" title="Click to instantly toggle dish availability (86-list)">
                    <input class="form-check-input dish-stock-toggle" 
                           type="checkbox" 
                           role="switch" 
                           id="dishSwitch<?= (int)$dish['item_id'] ?>"
                           data-item-id="<?= (int)$dish['item_id'] ?>"
                           data-item-name="<?= e($dish['item_name']) ?>"
                           <?= ($isAvail && $stock > 0) ? 'checked' : '' ?>
                           style="cursor: pointer; width: 2.2em; height: 1.2em;">
                  </div>
                  <span class="badge <?= ($isAvail && $stock > 0) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?> rounded-pill px-2 py-1 small dish-status-pill-<?= (int)$dish['item_id'] ?>">
                    <?= ($isAvail && $stock > 0) ? 'In Stock' : '86 / Sold Out' ?>
                  </span>
                </div>
              </td>

              <!-- Actions -->
              <td class="text-end">
                <div class="d-flex justify-content-end gap-2">
                  <a href="dish-customizations.php?item_id=<?= (int)$dish['item_id'] ?>" 
                     class="btn btn-sm btn-outline-success rounded-pill px-2 d-inline-flex align-items-center" 
                     title="Manage Customisations (Spice, Toppings, Cheese)">
                    <i class="bi bi-sliders2 me-1"></i>
                    <span>Customise</span>
                    <?php $gCount = (int)($customSummaryMap[$dish['item_id']]['group_count'] ?? 0); ?>
                    <?php if ($gCount > 0): ?>
                      <span class="badge bg-success text-white rounded-pill ms-1" style="font-size:0.65rem;"><?= $gCount ?></span>
                    <?php endif; ?>
                  </a>

                  <button type="button" 
                          class="btn btn-sm btn-outline-primary rounded-pill px-3 btn-edit-dish" 
                          data-bs-toggle="modal" 
                          data-bs-target="#editDishModal"
                          data-id="<?= (int)$dish['item_id'] ?>"
                          data-name="<?= e($dish['item_name']) ?>"
                          data-category-id="<?= (int)$dish['category_id'] ?>"
                          data-station="<?= e($dish['kitchen_station'] ?? 'Main Kitchen') ?>"
                          data-price="<?= (float)$dish['price'] ?>"
                          data-stock="<?= (int)$dish['stock_quantity'] ?>"
                          data-prep="<?= (int)$dish['prep_time_minutes'] ?>"
                          data-veg="<?= (int)$dish['is_veg'] ?>"
                          data-healthy="<?= (int)($dish['is_healthy'] ?? 0) ?>"
                          data-mood="<?= e($dish['mood'] ?? 'comfort') ?>"
                          data-available="<?= (int)$dish['is_available'] ?>"
                          data-calories="<?= (int)($dish['calories_kcal'] ?? 0) ?>"
                          data-protein="<?= (float)($dish['protein_g'] ?? 0) ?>"
                          data-carbs="<?= (float)($dish['carbs_g'] ?? 0) ?>"
                          data-fat="<?= (float)($dish['fat_g'] ?? 0) ?>"
                          data-spice="<?= e($dish['spice_level'] ?? 'MEDIUM') ?>"
                          data-allergens="<?= e($dish['allergens'] ?? '') ?>"
                          data-image="<?= e($dish['image_url'] ?? '') ?>"
                          data-display-image="<?= e(getDishImageUrl($dish['image_url'])) ?>"
                          data-description="<?= e($dish['description'] ?? '') ?>">
                    <i class="bi bi-pencil me-1"></i> Edit
                  </button>

                  <button type="button" 
                          class="btn btn-sm btn-outline-danger rounded-pill px-2 btn-delete-dish"
                          data-id="<?= (int)$dish['item_id'] ?>"
                          data-name="<?= e($dish['item_name']) ?>"
                          title="Delete or Archive Dish">
                    <i class="bi bi-trash3"></i>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Floating Bulk Actions Toolbar (Appears when 1+ rows selected) -->
<div class="floating-bulk-bar rounded-pill px-4 py-2 text-white d-flex align-items-center gap-3" id="bulkActionBar">
  <div>
    <span class="badge bg-white text-dark rounded-pill px-2 py-1 me-2 fw-bold" id="bulkSelectedCount">0</span>
    <span class="small">dishes selected</span>
  </div>
  <div class="vr bg-light opacity-50 my-1"></div>
  <div class="d-flex align-items-center gap-2">
    <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3" onclick="triggerBulkAction('mark_available')">
      <i class="bi bi-check2-circle me-1"></i> Mark In Stock
    </button>
    <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3" onclick="triggerBulkAction('add_stock')">
      <i class="bi bi-plus-lg me-1"></i> Add +10 Stock
    </button>
    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="triggerBulkAction('mark_soldout')">
      <i class="bi bi-slash-circle me-1"></i> 86 / Sold Out
    </button>
    <button type="button" class="btn btn-sm btn-link text-white text-decoration-none px-2" onclick="clearBulkSelection()">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>
</div>

<?php
$allergenOptionsList = getDishAllergenOptions();
$renderNutritionFields = static function (string $prefix) use ($allergenOptionsList): void {
?>
  <div class="p-3 bg-light-subtle rounded-3 border mt-3">
    <div class="small fw-bold text-dark mb-2">
      <i class="bi bi-heart-pulse text-success me-1"></i>Nutrition Macros, Spice Level &amp; Allergens
    </div>
    <div class="row g-2 mb-2">
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1" for="<?= e($prefix) ?>Calories">Calories (kcal)</label>
        <input type="number" min="0" name="calories_kcal" id="<?= e($prefix) ?>Calories" class="form-control form-control-sm rounded-3" placeholder="e.g. 420">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1" for="<?= e($prefix) ?>Protein">Protein (g)</label>
        <input type="number" step="0.1" min="0" name="protein_g" id="<?= e($prefix) ?>Protein" class="form-control form-control-sm rounded-3" placeholder="e.g. 24">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1" for="<?= e($prefix) ?>Carbs">Carbs (g)</label>
        <input type="number" step="0.1" min="0" name="carbs_g" id="<?= e($prefix) ?>Carbs" class="form-control form-control-sm rounded-3" placeholder="e.g. 48">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1" for="<?= e($prefix) ?>Fat">Fat (g)</label>
        <input type="number" step="0.1" min="0" name="fat_g" id="<?= e($prefix) ?>Fat" class="form-control form-control-sm rounded-3" placeholder="e.g. 14">
      </div>
    </div>
    <div class="row g-2 align-items-center">
      <div class="col-md-4">
        <label class="form-label small mb-1" for="<?= e($prefix) ?>Spice">Spice Level</label>
        <select name="spice_level" id="<?= e($prefix) ?>Spice" class="form-select form-select-sm rounded-3">
          <option value="MILD">Mild</option>
          <option value="MEDIUM" selected>Medium</option>
          <option value="SPICY">Spicy</option>
          <option value="EXTRA_SPICY">Extra Spicy</option>
        </select>
      </div>
      <div class="col-md-8">
        <label class="form-label small mb-1 d-block">Contains Allergens</label>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($allergenOptionsList as $algKey => $algLabel): ?>
            <div class="form-check form-check-inline m-0 me-2">
              <input class="form-check-input <?= e($prefix) ?>AllergenCheck" type="checkbox" name="allergens[]" value="<?= e($algKey) ?>" id="<?= e($prefix . 'Alg_' . $algKey) ?>">
              <label class="form-check-label small" for="<?= e($prefix . 'Alg_' . $algKey) ?>"><?= e(ucfirst($algKey)) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
<?php
};
?>

<!-- Parameterized Edit Food / Dish Modal -->
<div class="modal fade" id="editDishModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered text-start">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-pencil-square me-2 text-forest"></i>Edit Food: <span id="editDishTitle"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" action="<?= ADMIN_URL ?>/dishes.php" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit_dish">
        <input type="hidden" name="item_id" id="editDishId" value="">

        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-bold">Food Name *</label>
            <input type="text" name="item_name" id="editDishName" class="form-control rounded-3" required>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Category *</label>
              <select name="category_id" id="editDishCategory" class="form-select rounded-3">
                <?php foreach ($categories as $c): ?>
                  <option value="<?= (int)$c['category_id'] ?>">
                    <?= e($c['category_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-bold">Kitchen Station *</label>
              <select name="kitchen_station" id="editDishStation" class="form-select rounded-3">
                <?php foreach ($kitchenStations as $stn): ?>
                  <option value="<?= e($stn) ?>"><?= e($stn) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label small fw-bold">Price *</label>
              <div class="input-group">
                <span class="input-group-text bg-light">₹</span>
                <input type="number" step="0.01" min="1" name="price" id="editDishPrice" class="form-control" required>
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-bold">Stock Reserve *</label>
              <div class="input-group">
                <input type="number" min="0" name="stock_quantity" id="editDishStock" class="form-control" required>
                <span class="input-group-text bg-light small">qty</span>
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-bold">Prep Time *</label>
              <div class="input-group">
                <input type="number" min="1" name="prep_time_minutes" id="editDishPrep" class="form-control" required>
                <span class="input-group-text bg-light small">mins</span>
              </div>
            </div>
          </div>

          <!-- Food Photo: Both Upload & URL Options Available -->
          <div class="mb-3">
            <label class="form-label small fw-bold d-flex align-items-center justify-content-between mb-2">
              <span><i class="bi bi-camera me-1 text-forest"></i> Food Photo (Upload Photo or Photo URL)</span>
              <span class="text-muted fw-normal" style="font-size: 0.75rem;">JPG, PNG, WEBP, GIF (Max 5MB)</span>
            </label>

            <div class="p-3 rounded-3 border bg-light-subtle">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="position-relative flex-shrink-0">
                  <img id="editDishPreview" 
                       src="" 
                       alt="Food Preview" 
                       class="rounded-3 border object-fit-cover shadow-sm bg-white" 
                       style="width: 72px; height: 72px;">
                  <span class="position-absolute bottom-0 end-0 badge bg-dark bg-opacity-75 rounded-circle p-1" style="transform: translate(20%, 20%);" title="Food Photo Preview">
                    <i class="bi bi-camera-fill text-white" style="font-size: 0.65rem;"></i>
                  </span>
                </div>

                <div class="flex-grow-1">
                  <div class="small fw-bold text-dark mb-1">
                    <i class="bi bi-upload text-forest me-1"></i> Option 1: Upload Photo from Device
                  </div>
                  <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <label for="editDishPhotoInput" class="btn btn-outline-success btn-sm rounded-pill px-3 py-1 fw-bold shadow-sm" style="border-color:#2E6F40; color:#2E6F40; cursor:pointer;">
                      <i class="bi bi-folder2-open me-1"></i> Browse & Upload Photo
                    </label>
                    <span id="editDishPhotoName" class="small text-muted text-truncate" style="max-width: 200px;">
                      No file chosen
                    </span>
                  </div>
                  <input type="file" 
                         id="editDishPhotoInput" 
                         name="dish_image" 
                         class="d-none dish-photo-upload" 
                         accept="image/png, image/jpeg, image/jpg, image/webp, image/gif"
                         data-preview-id="editDishPreview"
                         data-name-id="editDishPhotoName">
                  <div class="text-muted" style="font-size: 0.75rem;">
                    Select an image file from your computer.
                  </div>
                </div>
              </div>

              <!-- Option 2: Direct Photo URL -->
              <div class="border-top pt-2">
                <label class="form-label small fw-bold text-dark mb-1" for="editDishUrlInput">
                  <i class="bi bi-link-45deg text-forest me-1"></i> Option 2: Photo Web URL
                </label>
                <input type="url" 
                       name="image_url" 
                       id="editDishUrlInput" 
                       class="form-control form-control-sm rounded-3 dish-url-input" 
                       placeholder="https://images.unsplash.com/..."
                       data-preview-id="editDishPreview">
                <div class="form-text small text-muted" style="font-size:0.75rem;">
                  Paste any image URL directly. If a file is uploaded above, it takes precedence.
                </div>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Description & Ingredients</label>
            <textarea name="description" id="editDishDesc" class="form-control rounded-3" rows="2"></textarea>
          </div>

          <div class="row g-3 mb-2">
            <div class="col-12 col-md-6">
              <label class="form-label small fw-bold">🎭 Curated Food Mood</label>
              <select name="mood" id="editDishMood" class="form-select form-select-sm rounded-3">
                <?php foreach ($foodMoods as $mKey => $mInfo): ?>
                  <option value="<?= e($mKey) ?>"><?= e($mInfo['emoji']) ?> <?= e($mInfo['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-6 d-flex align-items-center gap-3 pt-md-4">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_veg" value="1" id="editDishVeg">
                <label class="form-check-label small" for="editDishVeg">Pure Veg</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_healthy" value="1" id="editDishHealthy">
                <label class="form-check-label small" for="editDishHealthy">🌿 Healthy</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_available" value="1" id="editDishAvail">
                <label class="form-check-label small" for="editDishAvail">In Stock</label>
              </div>
            </div>
          </div>

          <?php $renderNutritionFields('editDish'); ?>

          <div class="p-3 bg-light rounded-3 d-flex align-items-center justify-content-between mt-3 border">
            <div>
              <div class="fw-bold small text-dark"><i class="bi bi-sliders2 text-success me-1"></i>Dish Customisations</div>
              <div class="small text-muted" style="font-size:0.75rem;">Manage spice levels, extra cheese, toppings & add-ons for this dish.</div>
            </div>
            <a href="#" id="editDishCustomLink" class="btn btn-sm btn-outline-success rounded-pill px-3">
              Configure Customisations <i class="bi bi-arrow-right ms-1"></i>
            </a>
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Dish Modal -->
<div class="modal fade" id="addDishModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-plus-circle me-2 text-forest"></i>Add New Food to Catalog
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" action="<?= ADMIN_URL ?>/dishes.php" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_dish">

        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-bold">Food Title *</label>
            <input type="text" name="item_name" class="form-control rounded-3" placeholder="E.g. Tandoori Paneer Tikka" required>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Category *</label>
              <select name="category_id" class="form-select rounded-3">
                <?php foreach ($categories as $c): ?>
                  <option value="<?= (int)$c['category_id'] ?>"><?= e($c['category_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-bold">Kitchen Station *</label>
              <select name="kitchen_station" class="form-select rounded-3">
                <?php foreach ($kitchenStations as $stn): ?>
                  <option value="<?= e($stn) ?>"><?= e($stn) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label small fw-bold">Price *</label>
              <div class="input-group">
                <span class="input-group-text bg-light">₹</span>
                <input type="number" step="0.01" min="1" name="price" class="form-control" placeholder="249.00" required>
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-bold">Initial Stock *</label>
              <div class="input-group">
                <input type="number" min="0" name="stock_quantity" class="form-control" value="40" required>
                <span class="input-group-text bg-light small">qty</span>
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-bold">Prep Time *</label>
              <div class="input-group">
                <input type="number" min="1" name="prep_time_minutes" class="form-control" value="20" required>
                <span class="input-group-text bg-light small">mins</span>
              </div>
            </div>
          </div>

          <!-- Food Photo: Both Upload & URL Options Available -->
          <div class="mb-3">
            <label class="form-label small fw-bold d-flex align-items-center justify-content-between mb-2">
              <span><i class="bi bi-camera me-1 text-forest"></i> Food Photo (Upload Photo or Photo URL)</span>
              <span class="text-muted fw-normal" style="font-size: 0.75rem;">JPG, PNG, WEBP, GIF (Max 5MB)</span>
            </label>

            <div class="p-3 rounded-3 border bg-light-subtle">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="position-relative flex-shrink-0">
                  <img id="dishPreview_add" 
                       src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300" 
                       alt="Food Preview" 
                       class="rounded-3 border object-fit-cover shadow-sm bg-white" 
                       style="width: 72px; height: 72px;">
                  <span class="position-absolute bottom-0 end-0 badge bg-dark bg-opacity-75 rounded-circle p-1" style="transform: translate(20%, 20%);" title="Food Photo Preview">
                    <i class="bi bi-camera-fill text-white" style="font-size: 0.65rem;"></i>
                  </span>
                </div>

                <div class="flex-grow-1">
                  <div class="small fw-bold text-dark mb-1">
                    <i class="bi bi-upload text-forest me-1"></i> Option 1: Upload Photo from Device
                  </div>
                  <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <label for="dishPhotoInput_add" class="btn btn-outline-success btn-sm rounded-pill px-3 py-1 fw-bold shadow-sm" style="border-color:#2E6F40; color:#2E6F40; cursor:pointer;">
                      <i class="bi bi-folder2-open me-1"></i> Browse & Upload Photo
                    </label>
                    <span id="dishPhotoName_add" class="small text-muted text-truncate" style="max-width: 200px;">
                      No file chosen
                    </span>
                  </div>
                  <input type="file" 
                         id="dishPhotoInput_add" 
                         name="dish_image" 
                         class="d-none dish-photo-upload" 
                         accept="image/png, image/jpeg, image/jpg, image/webp, image/gif"
                         data-preview-id="dishPreview_add"
                         data-name-id="dishPhotoName_add">
                  <div class="text-muted" style="font-size: 0.75rem;">
                    Select a food photo from your device or leave for default image.
                  </div>
                </div>
              </div>

              <!-- Option 2: Direct Photo URL -->
              <div class="border-top pt-2">
                <label class="form-label small fw-bold text-dark mb-1" for="dishUrlInput_add">
                  <i class="bi bi-link-45deg text-forest me-1"></i> Option 2: Photo Web URL
                </label>
                <input type="url" 
                       name="image_url" 
                       id="dishUrlInput_add" 
                       class="form-control form-control-sm rounded-3 dish-url-input" 
                       placeholder="https://images.unsplash.com/..."
                       data-preview-id="dishPreview_add">
                <div class="form-text small text-muted" style="font-size:0.75rem;">
                  Paste any image URL directly. If a file is uploaded above, it takes precedence.
                </div>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Description & Ingredients</label>
            <textarea name="description" class="form-control rounded-3" rows="2" placeholder="Culinary flavor profile, key ingredients..."></textarea>
          </div>

          <div class="row g-3 mb-2">
            <div class="col-12 col-md-6">
              <label class="form-label small fw-bold">🎭 Curated Food Mood</label>
              <select name="mood" class="form-select form-select-sm rounded-3">
                <?php foreach ($foodMoods as $mKey => $mInfo): ?>
                  <option value="<?= e($mKey) ?>"><?= e($mInfo['emoji']) ?> <?= e($mInfo['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-6 d-flex align-items-center gap-3 pt-md-4">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_veg" value="1" id="vegAdd" checked>
                <label class="form-check-label small" for="vegAdd">Vegetarian Dish</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_healthy" value="1" id="healthyAdd">
                <label class="form-check-label small" for="healthyAdd">🌿 Healthy</label>
              </div>
            </div>
          </div>

          <?php $renderNutritionFields('addDish'); ?>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
            <i class="bi bi-plus-lg me-1"></i> Add Dish
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Safe Delete / Archive Confirmation Modal -->
<div class="modal fade" id="deleteDishModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold text-danger">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>Remove Dish from Menu
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" action="<?= ADMIN_URL ?>/dishes.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="delete_dish">
        <input type="hidden" name="item_id" id="deleteDishId" value="">

        <div class="modal-body p-4 text-start">
          <p class="mb-2">Are you sure you want to remove <strong id="deleteDishName" class="text-dark"></strong> from the active catalog?</p>
          <div class="alert alert-warning border-0 small rounded-3 mb-0">
            <i class="bi bi-shield-lock-fill me-1"></i>
            <strong>Audit Protection:</strong> If this dish exists in past customer order receipts, it will be safely <strong>archived and hidden</strong> rather than hard-deleted to preserve legal tax and financial records.
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">
            Confirm Remove
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Populate Single Parameterized Edit Food Modal
  document.querySelectorAll('.btn-edit-dish').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const d = this.dataset;
      document.getElementById('editDishId').value = d.id;
      document.getElementById('editDishTitle').textContent = d.name;
      document.getElementById('editDishName').value = d.name;
      document.getElementById('editDishCategory').value = d.categoryId;
      document.getElementById('editDishStation').value = d.station || 'Main Kitchen';
      document.getElementById('editDishPrice').value = d.price;
      document.getElementById('editDishStock').value = d.stock;
      document.getElementById('editDishPrep').value = d.prep;
      document.getElementById('editDishUrlInput').value = d.image || '';
      document.getElementById('editDishPreview').src = d.displayImage || '';
      document.getElementById('editDishDesc').value = d.description || '';
      document.getElementById('editDishVeg').checked = (d.veg === '1');
      document.getElementById('editDishHealthy').checked = (d.healthy === '1');
      document.getElementById('editDishAvail').checked = (d.available === '1');
      if (document.getElementById('editDishCalories')) document.getElementById('editDishCalories').value = d.calories && d.calories !== '0' ? d.calories : '';
      if (document.getElementById('editDishProtein')) document.getElementById('editDishProtein').value = d.protein && d.protein !== '0' ? d.protein : '';
      if (document.getElementById('editDishCarbs')) document.getElementById('editDishCarbs').value = d.carbs && d.carbs !== '0' ? d.carbs : '';
      if (document.getElementById('editDishFat')) document.getElementById('editDishFat').value = d.fat && d.fat !== '0' ? d.fat : '';
      if (document.getElementById('editDishSpice')) document.getElementById('editDishSpice').value = (d.spice || 'MEDIUM').toUpperCase();
      const algList = (d.allergens || '').split(',').map(s => s.trim().toLowerCase()).filter(Boolean);
      document.querySelectorAll('.editDishAllergenCheck').forEach(chk => {
        chk.checked = algList.includes(chk.value.toLowerCase());
      });
      if (document.getElementById('editDishMood')) {
        document.getElementById('editDishMood').value = d.mood ? d.mood.split(',')[0].trim() : 'comfort';
      }
      const customLink = document.getElementById('editDishCustomLink');
      if (customLink) {
        customLink.href = 'dish-customizations.php?item_id=' + d.id;
      }
      const photoName = document.getElementById('editDishPhotoName');
      if (photoName) photoName.textContent = 'No file chosen';
      const fileInput = document.getElementById('editDishPhotoInput');
      if (fileInput) fileInput.value = '';
    });
  });

  // Populate Delete / Archive Modal
  document.querySelectorAll('.btn-delete-dish').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const name = this.getAttribute('data-name');
      document.getElementById('deleteDishId').value = id;
      document.getElementById('deleteDishName').textContent = name;
      const modal = new bootstrap.Modal(document.getElementById('deleteDishModal'));
      modal.show();
    });
  });

  // Food Photo Upload Preview & File Size Display
  document.querySelectorAll('.dish-photo-upload').forEach(function(input) {
    input.addEventListener('change', function(e) {
      const file = e.target.files && e.target.files[0];
      if (!file) return;

      const previewId = input.getAttribute('data-preview-id');
      const nameId = input.getAttribute('data-name-id');
      const previewImg = document.getElementById(previewId);
      const nameLabel = document.getElementById(nameId);

      if (nameLabel) {
        const sizeKb = (file.size / 1024).toFixed(0);
        nameLabel.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check2-circle me-1"></i>' + escapeHtml(file.name) + '</span> (' + sizeKb + ' KB)';
      }

      if (previewImg && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(evt) {
          previewImg.src = evt.target.result;
        };
        reader.readAsDataURL(file);
      }
    });
  });

  // Live URL input preview on typing / pasting
  document.querySelectorAll('.dish-url-input').forEach(function(input) {
    input.addEventListener('input', function() {
      const url = input.value.trim();
      const previewId = input.getAttribute('data-preview-id');
      const previewImg = document.getElementById(previewId);
      if (previewImg && url.match(/^https?:\/\//i)) {
        previewImg.src = url;
      }
    });
  });

  const adminCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // Instant 86-List AJAX Toggle Switch
  document.querySelectorAll('.dish-stock-toggle').forEach(function(toggle) {
    toggle.addEventListener('change', function() {
      const itemId = toggle.getAttribute('data-item-id');
      const isAvailable = toggle.checked ? 1 : 0;
      const statusPill = document.querySelector('.dish-status-pill-' + itemId);
      const stockBadge = document.getElementById('stockBadge' + itemId);

      toggle.disabled = true;

      fetch('<?= ADMIN_URL ?>/ajax/toggle-stock.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': adminCsrfToken
        },
        body: JSON.stringify({ item_id: itemId, is_available: isAvailable, csrf_token: adminCsrfToken })
      })
      .then(response => response.json())
      .then(data => {
        toggle.disabled = false;
        if (data.success) {
          if (statusPill) {
            if (data.is_available) {
              statusPill.className = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small dish-status-pill-' + itemId;
              statusPill.textContent = 'In Stock';
            } else {
              statusPill.className = 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 small dish-status-pill-' + itemId;
              statusPill.textContent = '86 / Sold Out';
            }
          }
          if (stockBadge && typeof data.stock_quantity !== 'undefined') {
            updateStockBadgeUI(stockBadge, data.stock_quantity);
          }
          if (typeof showAdminToast === 'function') {
            showAdminToast(data.message, data.is_available ? 'success' : 'danger');
          }
        } else {
          toggle.checked = !toggle.checked;
          if (typeof showAdminToast === 'function') {
            showAdminToast(data.message || 'Failed to update dish status.', 'danger');
          }
        }
      })
      .catch(err => {
        toggle.disabled = false;
        toggle.checked = !toggle.checked;
        if (typeof showAdminToast === 'function') {
          showAdminToast('Network error while toggling dish stock.', 'danger');
        }
      });
    });
  });

  // Instant AJAX Quick-Stock Steppers (-5, +5, +10)
  document.querySelectorAll('.btn-stock-stepper').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const itemId = this.getAttribute('data-item-id');
      const delta = parseInt(this.getAttribute('data-delta'), 10);
      const stockBadge = document.getElementById('stockBadge' + itemId);
      const toggle = document.getElementById('dishSwitch' + itemId);
      const statusPill = document.querySelector('.dish-status-pill-' + itemId);

      this.disabled = true;

      fetch('<?= ADMIN_URL ?>/ajax/adjust-stock.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': adminCsrfToken
        },
        body: JSON.stringify({ item_id: itemId, delta: delta, csrf_token: adminCsrfToken })
      })
      .then(response => response.json())
      .then(data => {
        this.disabled = false;
        if (data.success) {
          if (stockBadge) {
            updateStockBadgeUI(stockBadge, data.stock_quantity);
          }
          if (toggle) {
            toggle.checked = data.is_available;
          }
          if (statusPill) {
            if (data.is_available) {
              statusPill.className = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small dish-status-pill-' + itemId;
              statusPill.textContent = 'In Stock';
            } else {
              statusPill.className = 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 small dish-status-pill-' + itemId;
              statusPill.textContent = '86 / Sold Out';
            }
          }
          if (typeof showAdminToast === 'function') {
            showAdminToast(data.message, 'success');
          }
        } else {
          if (typeof showAdminToast === 'function') {
            showAdminToast(data.message || 'Failed to adjust stock.', 'danger');
          }
        }
      })
      .catch(err => {
        this.disabled = false;
        if (typeof showAdminToast === 'function') {
          showAdminToast('Network error while adjusting stock.', 'danger');
        }
      });
    });
  });

  // Bulk Checkbox Selection & Floating Action Bar
  const selectAll = document.getElementById('selectAllDishes');
  const rowCheckboxes = document.querySelectorAll('.dish-row-checkbox');
  const bulkBar = document.getElementById('bulkActionBar');
  const countLabel = document.getElementById('bulkSelectedCount');

  function updateBulkBar() {
    const checked = document.querySelectorAll('.dish-row-checkbox:checked');
    if (countLabel) countLabel.textContent = checked.length;
    if (checked.length > 0) {
      bulkBar.classList.add('show');
    } else {
      bulkBar.classList.remove('show');
    }
  }

  if (selectAll) {
    selectAll.addEventListener('change', function() {
      rowCheckboxes.forEach(cb => cb.checked = selectAll.checked);
      updateBulkBar();
    });
  }

  rowCheckboxes.forEach(cb => {
    cb.addEventListener('change', function() {
      if (!this.checked && selectAll) selectAll.checked = false;
      updateBulkBar();
    });
  });

  window.clearBulkSelection = function() {
    if (selectAll) selectAll.checked = false;
    rowCheckboxes.forEach(cb => cb.checked = false);
    updateBulkBar();
  };

  window.triggerBulkAction = function(action) {
    const checked = Array.from(document.querySelectorAll('.dish-row-checkbox:checked')).map(cb => cb.value);
    if (checked.length === 0) return;

    fetch('<?= ADMIN_URL ?>/ajax/bulk-dishes.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': adminCsrfToken
      },
      body: JSON.stringify({ action: action, item_ids: checked, csrf_token: adminCsrfToken })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        if (typeof showAdminToast === 'function') {
          showAdminToast(data.message, 'success');
        }
        setTimeout(() => location.reload(), 800);
      } else {
        if (typeof showAdminToast === 'function') {
          showAdminToast(data.message || 'Bulk operation failed', 'danger');
        }
      }
    })
    .catch(() => {
      if (typeof showAdminToast === 'function') {
        showAdminToast('Network error during bulk operation', 'danger');
      }
    });
  };

  // Real-Time Catalog Synchronization & Live Change Detection
  let dishesSyncCountdown = 5;
  let currentCatVer = <?= jsJson($initialCatVer ?? '') ?>;
  let currentDishVer = <?= jsJson($initialDishVer ?? '') ?>;
  let currentCombinedVer = <?= jsJson($initialCombinedVer ?? '') ?>;

  setInterval(async () => {
    dishesSyncCountdown--;
    const timerSpan = document.getElementById('dishesSyncTimer');
    if (timerSpan) timerSpan.textContent = dishesSyncCountdown + 's';

    if (dishesSyncCountdown <= 0) {
      dishesSyncCountdown = 5;
      try {
        const res = await fetch('../api/catalog-sync.php?portal=dishes&version=' + encodeURIComponent(currentCombinedVer) + '&cat_ver=' + encodeURIComponent(currentCatVer) + '&dish_ver=' + encodeURIComponent(currentDishVer), { cache: 'no-store' });
        if (!res.ok) return;
        const data = await res.json();
        if (data.success && data.changed) {
          currentCombinedVer = data.version;
          currentCatVer = data.cat_ver;
          currentDishVer = data.dish_ver;

          // Surface Notification Alert Banner
          const alertBox = document.getElementById('dishesLiveAlert');
          const alertTitle = document.getElementById('dishesLiveAlertTitle');
          const alertMsg = document.getElementById('dishesLiveAlertMsg');
          if (alertBox && alertMsg) {
            alertTitle.textContent = (data.change_type === 'category') ? '🔔 Category Update Detected!' : ((data.change_type === 'dish') ? '🔔 Dishes Inventory Update!' : '🔔 Menu Catalog Updated!');
            alertMsg.textContent = data.message || 'Categories or menu offerings were updated. Dropdowns and metrics synced.';
            alertBox.classList.remove('d-none');
            alertBox.classList.add('d-flex');
          }

          // If categories changed, dynamically synchronize dropdowns
          if (data.categories_list && Array.isArray(data.categories_list)) {
            const filterSelect = document.querySelector('select[name="category"]');
            const addSelect = document.querySelector('#addDishModal select[name="category_id"]');
            const editSelect = document.getElementById('editDishCategory');

            const syncSelectOptions = (sel, includeAll = false) => {
              if (!sel) return;
              const curVal = sel.value;
              sel.innerHTML = '';
              if (includeAll) {
                const optAll = document.createElement('option');
                optAll.value = '';
                optAll.textContent = 'All Categories';
                sel.appendChild(optAll);
              }
              data.categories_list.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.category_id;
                opt.textContent = c.category_name;
                if (String(c.category_id) === String(curVal)) opt.selected = true;
                sel.appendChild(opt);
              });
            };

            syncSelectOptions(filterSelect, true);
            syncSelectOptions(addSelect, false);
            syncSelectOptions(editSelect, false);
          }

          // Update KPI metrics
          if (data.metrics) {
            const kpiTotal = document.getElementById('kpiTotalDishes');
            const kpiSoldOut = document.getElementById('kpiSoldOutDishes');
            if (kpiTotal) kpiTotal.textContent = data.metrics.total_dishes;
            if (kpiSoldOut) kpiSoldOut.textContent = data.metrics.sold_out_dishes;
          }
        }
      } catch (err) {
        // Silently catch background errors
      }
    }
  }, 1000);

  function updateStockBadgeUI(badgeElem, qty) {
    const quantity = parseInt(qty, 10);
    if (quantity <= 0) {
      badgeElem.className = 'badge rounded-pill px-2 py-1 fs-6 bg-danger text-white';
      badgeElem.textContent = '0 (Sold Out)';
    } else if (quantity <= 15) {
      badgeElem.className = 'badge rounded-pill px-2 py-1 fs-6 bg-warning-subtle text-dark border border-warning';
      badgeElem.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>' + quantity + ' left';
    } else {
      badgeElem.className = 'badge rounded-pill px-2 py-1 fs-6 bg-light text-dark border';
      badgeElem.textContent = quantity + ' in stock';
    }
  }

  function escapeHtml(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
