<?php
/**
 * Food Categories Catalog Management
 * Zippy - Online Food Ordering Platform
 * Design: Figma "Lush Forest" Palette (#2E6F40, #68BA7F, #CEFFDC, #243E2C)
 */

$pageTitle = 'Food Categories Management';
$activePage = 'categories';

// Bootstrap core libraries before HTML output
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$currentUser = current_user();

// Handle Add / Edit / Delete / Toggle Status / Update Order
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session security token. Please try again.');
    } elseif ($_POST['action'] === 'add_category') {
        $name = clean_input($_POST['category_name']);
        $slug = clean_input($_POST['category_slug']) ?: strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $name));
        $desc = clean_input($_POST['category_description']);
        $image = clean_input($_POST['image_url'] ?? '');
        $order = isset($_POST['display_order']) && $_POST['display_order'] !== '' ? (int)$_POST['display_order'] : null;
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        // Auto-assign next display order if not specified
        if ($order === null || $order <= 0) {
            $maxOrd = (int)db_fetch_val("SELECT MAX(`display_order`) FROM `categories`");
            $order = $maxOrd + 1;
        }

        // Check if category photo was uploaded
        $upload = handle_category_photo_upload('category_image');
        if ($upload !== null) {
            if (!empty($upload['error'])) {
                set_flash('danger', 'Photo upload failed: ' . $upload['error']);
                header("Location: " . ADMIN_URL . "/categories.php");
                exit;
            } elseif (!empty($upload['url'])) {
                $image = $upload['url'];
            }
        }

        if (empty($image)) {
            $image = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300';
        }

        if (!empty($name)) {
            // Check for duplicate name or slug
            $existing = db_fetch_val("SELECT COUNT(*) FROM `categories` WHERE `category_name` = :name OR `category_slug` = :slug", [
                ':name' => $name,
                ':slug' => $slug
            ]);

            if ($existing > 0) {
                set_flash('warning', "A category with the name '{$name}' or slug '{$slug}' already exists.");
            } else {
                db_query("
                    INSERT INTO `categories` (`category_name`, `category_slug`, `category_description`, `image_url`, `display_order`, `is_active`) 
                    VALUES (:name, :slug, :desc, :img, :ord, :act)
                ", [
                    ':name' => $name,
                    ':slug' => $slug,
                    ':desc' => $desc,
                    ':img' => $image,
                    ':ord' => $order,
                    ':act' => $isActive
                ]);
                set_flash('success', "Category '{$name}' created successfully with display order #{$order}.");
            }
        }
        header("Location: " . ADMIN_URL . "/categories.php");
        exit;

    } elseif ($_POST['action'] === 'edit_category') {
        $cid = (int)$_POST['category_id'];
        $name = clean_input($_POST['category_name']);
        $slug = clean_input($_POST['category_slug']);
        $desc = clean_input($_POST['category_description']);
        $image = clean_input($_POST['image_url'] ?? '');
        $order = (int)($_POST['display_order'] ?? 0);
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        // Check if a new category photo was uploaded
        $upload = handle_category_photo_upload('category_image');
        if ($upload !== null) {
            if (!empty($upload['error'])) {
                set_flash('danger', 'Photo upload failed: ' . $upload['error']);
                header("Location: " . ADMIN_URL . "/categories.php");
                exit;
            } elseif (!empty($upload['url'])) {
                $image = $upload['url'];
            }
        }

        // If no new file uploaded and image_url is empty, retain current existing image from DB
        if (empty($image)) {
            $existing = db_fetch_val("SELECT `image_url` FROM `categories` WHERE `category_id` = :id", [':id' => $cid]);
            if ($existing) {
                $image = $existing;
            }
        }

        db_query("
            UPDATE `categories` 
            SET `category_name` = :name, `category_slug` = :slug, `category_description` = :desc, 
                `image_url` = :img, `display_order` = :ord, `is_active` = :act
            WHERE `category_id` = :id
        ", [
            ':name' => $name,
            ':slug' => $slug,
            ':desc' => $desc,
            ':img' => $image,
            ':ord' => $order,
            ':act' => $isActive,
            ':id' => $cid
        ]);
        set_flash('success', "Category '{$name}' updated successfully.");
        header("Location: " . ADMIN_URL . "/categories.php");
        exit;

    } elseif ($_POST['action'] === 'toggle_status') {
        $cid = (int)$_POST['category_id'];
        $cat = db_fetch_one("SELECT `category_name`, `is_active` FROM `categories` WHERE `category_id` = :id", [':id' => $cid]);
        if ($cat) {
            $newStatus = $cat['is_active'] ? 0 : 1;
            db_query("UPDATE `categories` SET `is_active` = :status WHERE `category_id` = :id", [
                ':status' => $newStatus,
                ':id' => $cid
            ]);
            $statusLabel = $newStatus ? 'now visible live on storefront' : 'now hidden from storefront';
            set_flash('success', "Category '{$cat['category_name']}' is {$statusLabel}.");
        }
        header("Location: " . ADMIN_URL . "/categories.php");
        exit;

    } elseif ($_POST['action'] === 'update_order') {
        $cid = (int)$_POST['category_id'];
        $direction = clean_input($_POST['direction'] ?? '');
        $currentOrder = (int)db_fetch_val("SELECT `display_order` FROM `categories` WHERE `category_id` = :id", [':id' => $cid]);
        
        if ($direction === 'up') {
            $newOrder = max(1, $currentOrder - 1);
        } elseif ($direction === 'down') {
            $newOrder = $currentOrder + 1;
        } else {
            $newOrder = (int)($_POST['new_order'] ?? $currentOrder);
        }

        db_query("UPDATE `categories` SET `display_order` = :ord WHERE `category_id` = :id", [
            ':ord' => $newOrder,
            ':id' => $cid
        ]);
        set_flash('success', "Display priority updated.");
        header("Location: " . ADMIN_URL . "/categories.php");
        exit;

    } elseif ($_POST['action'] === 'delete_category') {
        $cid = (int)$_POST['category_id'];
        $dishCount = (int)db_fetch_val("SELECT COUNT(*) FROM `menu_items` WHERE `category_id` = :id", [':id' => $cid]);
        if ($dishCount > 0) {
            set_flash('danger', "Cannot delete category because it contains {$dishCount} active menu dishes. Reassign or delete dishes first.");
        } else {
            $catName = db_fetch_val("SELECT `category_name` FROM `categories` WHERE `category_id` = :id", [':id' => $cid]);
            db_query("DELETE FROM `categories` WHERE `category_id` = :id", [':id' => $cid]);
            set_flash('success', "Category '{$catName}' deleted successfully.");
        }
        header("Location: " . ADMIN_URL . "/categories.php");
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';

// Fetch all categories with dish counts and ordering
$categories = db_fetch_all("
    SELECT c.*, COUNT(m.item_id) AS total_dishes
    FROM `categories` c
    LEFT JOIN `menu_items` m ON c.category_id = m.category_id
    GROUP BY c.category_id
    ORDER BY c.display_order ASC, c.category_id ASC
");

// Calculate real-time summary metrics
$totalCategories = count($categories);
$activeCount = 0;
$hiddenCount = 0;
$emptyCount = 0;
$totalDishesMapped = 0;

foreach ($categories as $cat) {
    if (!empty($cat['is_active'])) {
        $activeCount++;
    } else {
        $hiddenCount++;
    }
    if ((int)$cat['total_dishes'] === 0) {
        $emptyCount++;
    }
    $totalDishesMapped += (int)$cat['total_dishes'];
}

$maxOrder = !empty($categories) ? max(array_column($categories, 'display_order')) : 0;
$suggestedNextOrder = $maxOrder + 1;

// Compute initial version fingerprints for live change detection & notifications
$catFingerprintData = array_map(function($c) {
    return [(int)$c['category_id'], $c['category_name'], (int)$c['display_order'], (int)$c['is_active'], (int)$c['total_dishes']];
}, $categories);
$dishFingerprintData = db_fetch_all("SELECT item_id, category_id, item_name, price, stock_quantity, is_available FROM `menu_items` ORDER BY item_id ASC");
$initialCatVer = md5(json_encode($catFingerprintData));
$initialDishVer = md5(json_encode($dishFingerprintData));
$initialCombinedVer = md5($initialCatVer . '_' . $initialDishVer);
?>

<style>
  /* Assigned Dishes Pill Badge Styling */
  .assigned-dishes-badge {
    background-color: #EDFBF2 !important;
    color: #2E6F40 !important;
    border: 1px solid rgba(46, 111, 64, 0.25) !important;
    font-size: 0.82rem;
    font-weight: 600;
    line-height: 1.4;
    transition: all 0.2s ease;
  }
  .assigned-dishes-badge i.fa-bowl-food {
    color: #2E6F40 !important;
  }
  .assigned-dishes-badge span {
    color: #1C2B20 !important;
  }
  .assigned-dishes-badge i.bi-arrow-up-right {
    color: #2E6F40 !important;
    opacity: 0.75;
    transition: transform 0.2s ease, opacity 0.2s ease;
  }
  .assigned-dishes-badge:hover {
    background-color: #CEFFDC !important;
    border-color: #2E6F40 !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(46, 111, 64, 0.16) !important;
  }
  .assigned-dishes-badge:hover i.bi-arrow-up-right {
    transform: translate(1px, -1px);
    opacity: 1;
  }

  .empty-dishes-badge {
    background-color: #FFF8E1 !important;
    color: #6D4C00 !important;
    border: 1px solid #FFE082 !important;
    font-size: 0.82rem;
    font-weight: 600;
    line-height: 1.4;
    transition: all 0.2s ease;
  }
  .empty-dishes-badge i {
    color: #E5A100 !important;
  }
  .empty-dishes-badge span {
    color: #6D4C00 !important;
  }
  .empty-dishes-badge:hover {
    background-color: #FFECB3 !important;
    border-color: #FFB300 !important;
    transform: translateY(-1px);
  }

  /* Storefront Status Toggle Pill Styling */
  .status-btn-live {
    background-color: #EDFBF2 !important;
    color: #2E6F40 !important;
    border: 1px solid rgba(46, 111, 64, 0.3) !important;
    font-size: 0.82rem;
    transition: all 0.2s ease;
  }
  .status-btn-live:hover {
    background-color: #DCF6E6 !important;
    border-color: #2E6F40 !important;
    color: #243E2C !important;
  }

  .status-btn-hidden {
    background-color: #F8F9FA !important;
    color: #6C757D !important;
    border: 1px solid #DEE2E6 !important;
    font-size: 0.82rem;
    transition: all 0.2s ease;
  }
  .status-btn-hidden:hover {
    background-color: #E9ECEF !important;
    border-color: #ADB5BD !important;
    color: #495057 !important;
  }
</style>

<!-- ===========================================================================
     TOP KPI METRIC CARDS
     =========================================================================== -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100 position-relative overflow-hidden">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 text-forest bg-success bg-opacity-10 fs-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
          <i class="bi bi-tags-fill"></i>
        </div>
        <div>
          <div class="text-muted small fw-semibold">Total Categories</div>
          <div class="fs-4 fw-bold font-outfit text-dark" id="kpiTotalCategories"><?= $totalCategories ?></div>
          <div class="text-muted" id="kpiDishesMapped" style="font-size: 0.72rem;"><?= $totalDishesMapped ?> dishes mapped</div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6 col-lg-3">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100 position-relative overflow-hidden">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 text-success bg-success bg-opacity-15 fs-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
          <i class="bi bi-eye-fill"></i>
        </div>
        <div>
          <div class="text-muted small fw-semibold">Live on Storefront</div>
          <div class="fs-4 fw-bold font-outfit text-success" id="kpiActiveCount"><?= $activeCount ?></div>
          <div class="text-muted" style="font-size: 0.72rem;">Customer navigable</div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6 col-lg-3">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100 position-relative overflow-hidden">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 text-secondary bg-secondary bg-opacity-10 fs-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
          <i class="bi bi-eye-slash-fill"></i>
        </div>
        <div>
          <div class="text-muted small fw-semibold">Hidden / Offline</div>
          <div class="fs-4 fw-bold font-outfit text-secondary" id="kpiHiddenCount"><?= $hiddenCount ?></div>
          <div class="text-muted" style="font-size: 0.72rem;">Draft or seasonal</div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6 col-lg-3">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100 position-relative overflow-hidden">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 text-warning bg-warning bg-opacity-15 fs-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
          <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
        <div>
          <div class="text-muted small fw-semibold">Empty Sections</div>
          <div class="fs-4 fw-bold font-outfit text-warning" id="kpiEmptyCount"><?= $emptyCount ?></div>
          <div class="text-muted" style="font-size: 0.72rem;">Needs menu dishes</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ===========================================================================
     MAIN CARD: FOOD CATEGORIES CATALOG & MANAGEMENT
     =========================================================================== -->
<div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
  
  <!-- Header Bar -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-3 mb-3">
    <div>
      <h4 class="fw-bold font-outfit mb-1 text-dark d-flex align-items-center gap-2">
        <i class="bi bi-collection-fill text-forest"></i> Food Categories Catalog
      </h4>
      <p class="text-muted small mb-0">
        Organize menu sections, storefront display sequences, and dish classifications.
      </p>
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">
      <span class="badge bg-light text-forest border px-3 py-2 rounded-pill font-monospace shadow-xs d-inline-flex align-items-center gap-1" id="catLiveSyncBadge">
        <span class="live-pulse-dot green me-1"></span> Live Sync: <span id="catSyncTimer">5s</span>
      </span>

      <button type="button" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addCategoryModal" style="background:#2E6F40; border-color:#2E6F40;">
        <i class="bi bi-plus-lg"></i>
        <span>Add New Category</span>
      </button>
    </div>
  </div>

  <!-- Real-time Catalog Alert Banner -->
  <div id="catLiveAlert" class="alert alert-success border-2 shadow-sm rounded-4 d-none align-items-center justify-content-between p-3 mb-3" role="alert" style="background:#EDFBF2; border-color:#2E6F40;">
    <div class="d-flex align-items-center gap-2">
      <i class="fa-solid fa-bell text-forest fs-5"></i>
      <div>
        <strong class="text-forest" id="catLiveAlertTitle">Catalog Update Detected!</strong>
        <div class="small text-dark" id="catLiveAlertMsg">Menu items or category classifications were updated.</div>
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-bold text-white shadow-xs" onclick="window.location.reload();" style="background:#2E6F40; border:none;">
        <i class="bi bi-arrow-clockwise me-1"></i> Refresh Table
      </button>
      <button type="button" class="btn-close" onclick="document.getElementById('catLiveAlert').classList.add('d-none'); document.getElementById('catLiveAlert').classList.remove('d-flex');"></button>
    </div>
  </div>

  <!-- Real-time Filter & Search Toolbar -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 bg-light rounded-4 p-3 mb-3 border">
    
    <!-- Search Input -->
    <div class="position-relative flex-grow-1" style="max-width: 380px;">
      <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
      <input type="text" id="categorySearchInput" class="form-control form-control-sm rounded-pill ps-5 bg-white border" placeholder="Search categories by name, slug or description...">
      <button type="button" id="clearSearchBtn" class="btn btn-sm position-absolute top-50 end-0 translate-middle-y me-2 text-muted d-none" style="background:none; border:none;">
        <i class="bi bi-x-circle-fill"></i>
      </button>
    </div>

    <!-- Filter Chips -->
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <span class="small text-muted fw-semibold me-1"><i class="bi bi-funnel me-1"></i>Filter:</span>
      <button type="button" class="btn btn-sm btn-dark rounded-pill px-3 py-1 filter-chip active" data-filter="all">
        All (<?= $totalCategories ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 filter-chip" data-filter="active">
        Live (<?= $activeCount ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 filter-chip" data-filter="hidden">
        Hidden (<?= $hiddenCount ?>)
      </button>
      <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3 py-1 filter-chip" data-filter="empty">
        Empty (<?= $emptyCount ?>)
      </button>
    </div>
  </div>

  <!-- Categories Table -->
  <div class="table-responsive">
    <table class="table table-hover align-middle small mb-0" id="categoriesTable">
      <thead class="table-light">
        <tr>
          <th style="width: 100px;">Priority</th>
          <th>Category Details</th>
          <th style="width: 170px;">Assigned Dishes</th>
          <th style="width: 140px;">Storefront Status</th>
          <th class="text-end" style="width: 150px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($categories)): ?>
          <tr id="emptyDbRow">
            <td colspan="5" class="text-center py-5">
              <div class="py-4">
                <i class="bi bi-tags text-muted display-4 mb-3 d-block"></i>
                <h5 class="fw-bold text-dark">No Food Categories Found</h5>
                <p class="text-muted small mb-3">Get started by creating your first food category.</p>
                <button type="button" class="btn btn-success rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#addCategoryModal" style="background:#2E6F40; border-color:#2E6F40;">
                  <i class="bi bi-plus-lg me-1"></i> Add Category
                </button>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($categories as $cat): ?>
            <tr class="category-row" 
                id="catRow_<?= (int)$cat['category_id'] ?>"
                data-id="<?= (int)$cat['category_id'] ?>"
                data-name="<?= strtolower(e($cat['category_name'])) ?>"
                data-slug="<?= strtolower(e($cat['category_slug'])) ?>"
                data-description="<?= strtolower(e($cat['category_description'] ?? '')) ?>"
                data-status="<?= $cat['is_active'] ? 'active' : 'hidden' ?>"
                data-empty="<?= ((int)$cat['total_dishes'] === 0) ? 'empty' : 'has-dishes' ?>">
              
              <!-- Display Order & Priority Adjustment -->
              <td>
                <div class="d-flex align-items-center gap-1">
                  <span class="badge bg-light text-dark border font-monospace px-2 py-1 fw-bold fs-6" title="Menu sequence rank">
                    #<?= (int)$cat['display_order'] ?>
                  </span>
                  
                  <div class="d-flex flex-column ms-1">
                    <form method="POST" action="<?= ADMIN_URL ?>/categories.php" class="m-0">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update_order">
                      <input type="hidden" name="category_id" value="<?= (int)$cat['category_id'] ?>">
                      <input type="hidden" name="direction" value="up">
                      <button type="submit" class="btn btn-sm btn-link p-0 text-muted lh-1" title="Move Up in Menu" style="font-size: 0.65rem;">
                        <i class="bi bi-chevron-up"></i>
                      </button>
                    </form>
                    <form method="POST" action="<?= ADMIN_URL ?>/categories.php" class="m-0">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update_order">
                      <input type="hidden" name="category_id" value="<?= (int)$cat['category_id'] ?>">
                      <input type="hidden" name="direction" value="down">
                      <button type="submit" class="btn btn-sm btn-link p-0 text-muted lh-1" title="Move Down in Menu" style="font-size: 0.65rem;">
                        <i class="bi bi-chevron-down"></i>
                      </button>
                    </form>
                  </div>
                </div>
              </td>

              <!-- Category Details: Thumbnail, Name, Slug, Description -->
              <td>
                <div class="d-flex align-items-center gap-3">
                  <img src="<?= e(get_category_image_url($cat['image_url'])) ?>" 
                       alt="<?= e($cat['category_name']) ?>" 
                       class="rounded-3 object-fit-cover shadow-sm border bg-white flex-shrink-0" 
                       style="width: 50px; height: 50px;"
                       onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300';">
                  
                  <div class="overflow-hidden">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                      <span class="fw-bold text-dark fs-6 category-title-text"><?= e($cat['category_name']) ?></span>
                      <span class="badge bg-light text-muted border font-monospace px-2 py-0" style="font-size: 0.7rem;" title="Routing slug">
                        <i class="bi bi-link-45deg"></i>/<?= e($cat['category_slug']) ?>
                      </span>
                    </div>

                    <?php if (!empty($cat['category_description'])): ?>
                      <div class="small text-muted text-truncate" style="max-width: 380px;" title="<?= e($cat['category_description']) ?>">
                        <?= e($cat['category_description']) ?>
                      </div>
                    <?php else: ?>
                      <div class="small text-muted fst-italic" style="font-size:0.75rem;">No description provided</div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>

              <!-- Assigned Dishes Count (Clickable Deep-Link to Dishes Page) -->
              <td>
                <div class="cat-dishes-cell" id="catDishesCell_<?= (int)$cat['category_id'] ?>" data-category-id="<?= (int)$cat['category_id'] ?>">
                  <?php if ((int)$cat['total_dishes'] > 0): ?>
                    <a href="<?= ADMIN_URL ?>/dishes.php?category=<?= (int)$cat['category_id'] ?>" 
                       class="badge assigned-dishes-badge text-decoration-none px-3 py-2 rounded-pill d-inline-flex align-items-center gap-2" 
                       title="View and manage dishes under this category">
                      <i class="fa-solid fa-bowl-food"></i>
                      <span class="fw-bold"><?= (int)$cat['total_dishes'] ?> <?= ((int)$cat['total_dishes'] === 1) ? 'dish' : 'dishes' ?></span>
                      <i class="bi bi-arrow-up-right small"></i>
                    </a>
                  <?php else: ?>
                    <a href="<?= ADMIN_URL ?>/dishes.php" 
                       class="badge empty-dishes-badge text-decoration-none px-3 py-2 rounded-pill d-inline-flex align-items-center gap-1.5" 
                       title="Empty section! Click to assign dishes in Dish Catalog.">
                      <i class="bi bi-exclamation-triangle-fill"></i>
                      <span class="fw-bold">0 dishes (Empty)</span>
                    </a>
                  <?php endif; ?>
                </div>
              </td>

              <!-- 1-Click Status Toggle -->
              <td>
                <form method="POST" action="<?= ADMIN_URL ?>/categories.php" class="m-0 d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="category_id" value="<?= (int)$cat['category_id'] ?>">
                  
                  <?php if ($cat['is_active']): ?>
                    <button type="submit" 
                            class="btn btn-sm rounded-pill px-3 py-1 fw-bold d-inline-flex align-items-center gap-2 status-btn-live shadow-xs" 
                            title="Active on customer app. Click to hide.">
                      <span class="live-pulse-dot green"></span> Live
                    </button>
                  <?php else: ?>
                    <button type="submit" 
                            class="btn btn-sm rounded-pill px-3 py-1 fw-bold d-inline-flex align-items-center gap-1 status-btn-hidden shadow-xs" 
                            title="Hidden from storefront. Click to make live.">
                      <i class="bi bi-eye-slash-fill me-1"></i> Hidden
                    </button>
                  <?php endif; ?>
                </form>
              </td>

              <!-- Actions: Parameterized Edit & Guarded Delete -->
              <td class="text-end">
                <div class="d-flex justify-content-end align-items-center gap-2">
                  <button type="button" 
                          class="btn btn-sm btn-outline-primary rounded-pill px-3 btn-edit-category" 
                          data-bs-toggle="modal" 
                          data-bs-target="#editCategoryModal"
                          data-id="<?= (int)$cat['category_id'] ?>"
                          data-name="<?= e($cat['category_name']) ?>"
                          data-slug="<?= e($cat['category_slug']) ?>"
                          data-description="<?= e($cat['category_description'] ?? '') ?>"
                          data-image="<?= e($cat['image_url'] ?? '') ?>"
                          data-display-image="<?= e(get_category_image_url($cat['image_url'])) ?>"
                          data-order="<?= (int)$cat['display_order'] ?>"
                          data-active="<?= (int)$cat['is_active'] ?>"
                          title="Edit Category Details">
                    <i class="bi bi-pencil me-1"></i> Edit
                  </button>

                  <?php if ((int)$cat['total_dishes'] > 0): ?>
                    <!-- Disabled Delete Button with Explanatory Tooltip -->
                    <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="Cannot delete: contains <?= (int)$cat['total_dishes'] ?> active dishes. Reassign them first.">
                      <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 opacity-50" disabled>
                        <i class="bi bi-trash3"></i>
                      </button>
                    </span>
                  <?php else: ?>
                    <!-- Active Delete Button triggering Confirmation Modal -->
                    <button type="button" 
                            class="btn btn-sm btn-outline-danger rounded-pill px-2 btn-delete-category" 
                            data-bs-toggle="modal" 
                            data-bs-target="#deleteCategoryModal"
                            data-id="<?= (int)$cat['category_id'] ?>"
                            data-name="<?= e($cat['category_name']) ?>"
                            title="Delete this empty category">
                      <i class="bi bi-trash3"></i>
                    </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>

        <!-- No Results Row for Client Search -->
        <tr id="noResultsRow" class="d-none">
          <td colspan="5" class="text-center py-5 text-muted">
            <i class="bi bi-search fs-3 d-block mb-2 text-secondary opacity-50"></i>
            <div class="fw-bold fs-6 text-dark mb-1">No matching categories found</div>
            <div class="small mb-3">Try adjusting your search terms or filter selection.</div>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="resetCategoryFilters()">
              Clear Search & Filters
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ===========================================================================
     MODAL 1: ADD CATEGORY MODAL (With Live Auto-Slug & Display Order)
     =========================================================================== -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold" id="addCategoryModalLabel">
          <i class="bi bi-plus-circle me-2 text-forest"></i>Create Food Category
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form method="POST" action="<?= ADMIN_URL ?>/categories.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_category">

        <div class="modal-body p-4">
          <!-- Category Title -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Category Title *</label>
            <input type="text" 
                   name="category_name" 
                   id="addCatTitleInput" 
                   class="form-control rounded-3" 
                   placeholder="E.g. Tandoori & Kebabs" 
                   required 
                   autocomplete="off">
          </div>

          <!-- Routing Slug with Live Auto-Generation -->
          <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between mb-1">
              <label class="form-label small fw-bold text-dark mb-0">URL Routing Slug *</label>
              <button type="button" id="toggleSlugManual" class="btn btn-link p-0 text-forest text-decoration-none small" style="font-size:0.75rem;">
                <i class="bi bi-pencil me-1"></i>Edit manually
              </button>
            </div>
            <div class="input-group">
              <span class="input-group-text bg-light text-muted small font-monospace">/category/</span>
              <input type="text" 
                     name="category_slug" 
                     id="addCatSlugInput" 
                     class="form-control font-monospace" 
                     placeholder="tandoori-kebabs" 
                     required>
            </div>
            <div class="form-text small text-muted" style="font-size:0.72rem;">
              Auto-generated from title. Used for storefront URL routing.
            </div>
          </div>

          <!-- Display Order / Priority -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Display Sequence / Rank *</label>
            <div class="input-group">
              <span class="input-group-text bg-light text-muted small"><i class="bi bi-sort-numeric-down"></i></span>
              <input type="number" 
                     name="display_order" 
                     id="addCatOrderInput" 
                     class="form-control" 
                     value="<?= $suggestedNextOrder ?>" 
                     min="1" 
                     required>
            </div>
            <div class="form-text small text-muted" style="font-size: 0.72rem;">
              Lower numbers appear first on the customer storefront menu.
            </div>
          </div>

          <!-- Description -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Description</label>
            <textarea name="category_description" 
                      class="form-control rounded-3" 
                      rows="2" 
                      placeholder="Summary of dishes in this section..."></textarea>
          </div>

          <!-- Category Photo: Dual Upload or Web URL -->
          <div class="mb-3">
            <label class="form-label small fw-bold d-flex align-items-center justify-content-between mb-2">
              <span><i class="bi bi-image me-1 text-forest"></i> Category Photo</span>
              <span class="text-muted fw-normal" style="font-size: 0.75rem;">JPG, PNG, WEBP (Max 5MB)</span>
            </label>

            <div class="p-3 rounded-3 border bg-light-subtle">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="position-relative flex-shrink-0">
                  <img id="catPreview_add" 
                       src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300" 
                       alt="Category Preview" 
                       class="rounded-3 border object-fit-cover shadow-sm bg-white" 
                       style="width: 64px; height: 64px;">
                  <span class="position-absolute bottom-0 end-0 badge bg-dark bg-opacity-75 rounded-circle p-1" style="transform: translate(20%, 20%);">
                    <i class="bi bi-camera-fill text-white" style="font-size: 0.65rem;"></i>
                  </span>
                </div>

                <div class="flex-grow-1">
                  <div class="small fw-bold text-dark mb-1">
                    Option 1: Upload from Device
                  </div>
                  <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <label for="catPhotoInput_add" class="btn btn-outline-success btn-sm rounded-pill px-3 py-1 fw-bold shadow-sm" style="border-color:#2E6F40; color:#2E6F40; cursor:pointer;">
                      <i class="bi bi-folder2-open me-1"></i> Browse Photo
                    </label>
                    <span id="catPhotoName_add" class="small text-muted text-truncate" style="max-width: 200px;">
                      No file chosen
                    </span>
                  </div>
                  <input type="file" 
                         id="catPhotoInput_add" 
                         name="category_image" 
                         class="d-none cat-photo-upload" 
                         accept="image/png, image/jpeg, image/jpg, image/webp"
                         data-preview-id="catPreview_add"
                         data-name-id="catPhotoName_add">
                  <div id="catPhotoError_add" class="small text-danger d-none mt-1"></div>
                </div>
              </div>

              <!-- Option 2: Direct Photo URL -->
              <div class="border-top pt-2">
                <label class="form-label small fw-bold text-dark mb-1" for="catUrlInput_add">
                  Option 2: Direct Image URL
                </label>
                <input type="url" 
                       name="image_url" 
                       id="catUrlInput_add" 
                       class="form-control form-control-sm rounded-3 cat-url-input" 
                       placeholder="https://images.unsplash.com/..."
                       data-preview-id="catPreview_add">
                <div class="form-text small text-muted" style="font-size:0.72rem;">
                  Paste an image link. Local upload takes precedence if selected.
                </div>
              </div>
            </div>
          </div>

          <!-- Storefront Visibility -->
          <div class="form-check form-switch p-0 ps-5 mt-3">
            <input class="form-check-input ms-n5" type="checkbox" name="is_active" value="1" id="actAdd" checked style="cursor:pointer;">
            <label class="form-check-label small fw-semibold text-dark" for="actAdd" style="cursor:pointer;">
              Make visible immediately in customer storefront menu
            </label>
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
            <i class="bi bi-plus-lg me-1"></i> Create Category
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===========================================================================
     MODAL 2: EDIT CATEGORY MODAL (Including display_order fix!)
     =========================================================================== -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered text-start">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold" id="editCategoryModalLabel">
          <i class="bi bi-pencil-square me-2 text-forest"></i>Edit Category: <span id="editCatTitle"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form method="POST" action="<?= ADMIN_URL ?>/categories.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit_category">
        <input type="hidden" name="category_id" id="editCatId" value="">

        <div class="modal-body p-4">
          <!-- Name -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Category Name *</label>
            <input type="text" name="category_name" id="editCatName" class="form-control rounded-3" required>
          </div>

          <!-- Routing Slug -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Routing Slug *</label>
            <div class="input-group">
              <span class="input-group-text bg-light text-muted small font-monospace">/category/</span>
              <input type="text" name="category_slug" id="editCatSlug" class="form-control font-monospace" required>
            </div>
          </div>

          <!-- Display Order / Priority (CRITICAL BUG FIXED) -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Display Sequence / Rank *</label>
            <div class="input-group">
              <span class="input-group-text bg-light text-muted small"><i class="bi bi-sort-numeric-down"></i></span>
              <input type="number" name="display_order" id="editCatOrder" class="form-control" min="1" required>
            </div>
            <div class="form-text small text-muted" style="font-size:0.72rem;">
              Controls position on storefront navigation (lower numbers appear first).
            </div>
          </div>

          <!-- Description -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Description</label>
            <textarea name="category_description" id="editCatDesc" class="form-control rounded-3" rows="2"></textarea>
          </div>

          <!-- Category Photo: Dual Upload or Web URL -->
          <div class="mb-3">
            <label class="form-label small fw-bold d-flex align-items-center justify-content-between mb-2">
              <span><i class="bi bi-image me-1 text-forest"></i> Category Photo</span>
              <span class="text-muted fw-normal" style="font-size: 0.75rem;">JPG, PNG, WEBP (Max 5MB)</span>
            </label>

            <div class="p-3 rounded-3 border bg-light-subtle">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="position-relative flex-shrink-0">
                  <img id="editCatPreview" 
                       src="" 
                       alt="Category Preview" 
                       class="rounded-3 border object-fit-cover shadow-sm bg-white" 
                       style="width: 64px; height: 64px;">
                  <span class="position-absolute bottom-0 end-0 badge bg-dark bg-opacity-75 rounded-circle p-1" style="transform: translate(20%, 20%);">
                    <i class="bi bi-camera-fill text-white" style="font-size: 0.65rem;"></i>
                  </span>
                </div>

                <div class="flex-grow-1">
                  <div class="small fw-bold text-dark mb-1">
                    Option 1: Upload from Device
                  </div>
                  <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <label for="editCatPhotoInput" class="btn btn-outline-success btn-sm rounded-pill px-3 py-1 fw-bold shadow-sm" style="border-color:#2E6F40; color:#2E6F40; cursor:pointer;">
                      <i class="bi bi-folder2-open me-1"></i> Browse Photo
                    </label>
                    <span id="editCatPhotoName" class="small text-muted text-truncate" style="max-width: 200px;">
                      No file chosen
                    </span>
                  </div>
                  <input type="file" 
                         id="editCatPhotoInput" 
                         name="category_image" 
                         class="d-none cat-photo-upload" 
                         accept="image/png, image/jpeg, image/jpg, image/webp"
                         data-preview-id="editCatPreview"
                         data-name-id="editCatPhotoName">
                  <div id="editCatPhotoError" class="small text-danger d-none mt-1"></div>
                </div>
              </div>

              <!-- Option 2: Direct Photo URL -->
              <div class="border-top pt-2">
                <label class="form-label small fw-bold text-dark mb-1" for="editCatUrlInput">
                  Option 2: Direct Image URL
                </label>
                <input type="url" 
                       name="image_url" 
                       id="editCatUrlInput" 
                       class="form-control form-control-sm rounded-3 cat-url-input" 
                       placeholder="https://images.unsplash.com/..."
                       data-preview-id="editCatPreview">
                <div class="form-text small text-muted" style="font-size:0.72rem;">
                  Paste an image link. If left unchanged, existing category image is retained.
                </div>
              </div>
            </div>
          </div>

          <!-- Storefront Visibility -->
          <div class="form-check form-switch p-0 ps-5 mt-3">
            <input class="form-check-input ms-n5" type="checkbox" name="is_active" value="1" id="editCatActive" style="cursor:pointer;">
            <label class="form-check-label small fw-semibold text-dark" for="editCatActive" style="cursor:pointer;">
              Visible in customer storefront navigation
            </label>
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
            <i class="bi bi-check2-circle me-1"></i> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===========================================================================
     MODAL 3: DELETE CONFIRMATION MODAL (Replaces browser confirm)
     =========================================================================== -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-body p-4 text-center">
        <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px; font-size: 1.6rem;">
          <i class="bi bi-trash3-fill"></i>
        </div>
        <h5 class="fw-bold text-dark mb-2">Delete Category?</h5>
        <p class="text-muted small mb-3">
          Are you sure you want to delete <strong id="deleteCatName" class="text-dark"></strong>? This action cannot be undone.
        </p>

        <form method="POST" action="<?= ADMIN_URL ?>/categories.php" class="m-0">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_category">
          <input type="hidden" name="category_id" id="deleteCatId" value="">

          <div class="d-flex justify-content-center gap-2 mt-4">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">
              <i class="bi bi-trash3 me-1"></i> Confirm Delete
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ===========================================================================
     CLIENT JAVASCRIPT: REAL-TIME SEARCH, FILTERS, VALIDATION & MODAL SYNC
     =========================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  
  // ---------------------------------------------------------------------------
  // 1. Live Title-to-Slug Auto Generator
  // ---------------------------------------------------------------------------
  const addTitleInput = document.getElementById('addCatTitleInput');
  const addSlugInput = document.getElementById('addCatSlugInput');
  let manualSlugEdit = false;

  const toggleManualBtn = document.getElementById('toggleSlugManual');
  if (toggleManualBtn && addSlugInput) {
    toggleManualBtn.addEventListener('click', function() {
      manualSlugEdit = true;
      addSlugInput.focus();
      addSlugInput.select();
      toggleManualBtn.textContent = 'Auto-sync enabled';
    });
  }

  if (addTitleInput && addSlugInput) {
    addTitleInput.addEventListener('input', function() {
      if (!manualSlugEdit) {
        addSlugInput.value = generateSlug(this.value);
      }
    });
  }

  function generateSlug(text) {
    return text.toString().toLowerCase()
      .trim()
      .replace(/[\s\W-]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  // ---------------------------------------------------------------------------
  // 2. Populate Single Parameterized Edit Category Modal
  // ---------------------------------------------------------------------------
  document.querySelectorAll('.btn-edit-category').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const d = this.dataset;
      document.getElementById('editCatId').value = d.id;
      document.getElementById('editCatTitle').textContent = d.name;
      document.getElementById('editCatName').value = d.name;
      document.getElementById('editCatSlug').value = d.slug;
      document.getElementById('editCatOrder').value = d.order || 1;
      document.getElementById('editCatDesc').value = d.description || '';
      document.getElementById('editCatUrlInput').value = d.image || '';
      document.getElementById('editCatPreview').src = d.displayImage || '';
      document.getElementById('editCatActive').checked = (d.active === '1');
      
      const photoName = document.getElementById('editCatPhotoName');
      if (photoName) photoName.textContent = 'No file chosen';
      const fileInput = document.getElementById('editCatPhotoInput');
      if (fileInput) fileInput.value = '';
    });
  });

  // ---------------------------------------------------------------------------
  // 3. Populate Delete Confirmation Modal
  // ---------------------------------------------------------------------------
  document.querySelectorAll('.btn-delete-category').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const d = this.dataset;
      document.getElementById('deleteCatId').value = d.id;
      document.getElementById('deleteCatName').textContent = d.name;
    });
  });

  // ---------------------------------------------------------------------------
  // 4. File Upload Client-side Preview & Size Validation (Max 5MB)
  // ---------------------------------------------------------------------------
  document.querySelectorAll('.cat-photo-upload').forEach(function(input) {
    input.addEventListener('change', function(e) {
      const file = e.target.files && e.target.files[0];
      if (!file) return;

      const previewId = input.getAttribute('data-preview-id');
      const nameId = input.getAttribute('data-name-id');
      const previewImg = document.getElementById(previewId);
      const nameLabel = document.getElementById(nameId);

      // Validation: 5MB limit
      const maxBytes = 5 * 1024 * 1024;
      if (file.size > maxBytes) {
        alert('File size exceeds 5MB limit. Please select a smaller image.');
        input.value = '';
        if (nameLabel) nameLabel.textContent = 'No file chosen';
        return;
      }

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

  // ---------------------------------------------------------------------------
  // 5. Direct URL Input Live Preview
  // ---------------------------------------------------------------------------
  document.querySelectorAll('.cat-url-input').forEach(function(input) {
    input.addEventListener('input', function() {
      const url = input.value.trim();
      const previewId = input.getAttribute('data-preview-id');
      const previewImg = document.getElementById(previewId);
      if (previewImg && url.match(/^https?:\/\//i)) {
        previewImg.src = url;
      }
    });
  });

  // ---------------------------------------------------------------------------
  // 6. Real-time Search and Filter Chips Engine
  // ---------------------------------------------------------------------------
  const searchInput = document.getElementById('categorySearchInput');
  const clearBtn = document.getElementById('clearSearchBtn');
  const filterChips = document.querySelectorAll('.filter-chip');
  const rows = document.querySelectorAll('.category-row');
  const noResultsRow = document.getElementById('noResultsRow');

  let activeFilter = 'all';

  function applyFilters() {
    const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
    let visibleCount = 0;

    if (clearBtn) {
      if (query.length > 0) {
        clearBtn.classList.remove('d-none');
      } else {
        clearBtn.classList.add('d-none');
      }
    }

    rows.forEach(function(row) {
      const name = row.getAttribute('data-name') || '';
      const slug = row.getAttribute('data-slug') || '';
      const desc = row.getAttribute('data-description') || '';
      const status = row.getAttribute('data-status') || '';
      const isEmpty = row.getAttribute('data-empty') || '';

      const matchesSearch = query === '' || name.includes(query) || slug.includes(query) || desc.includes(query);
      
      let matchesFilter = true;
      if (activeFilter === 'active') {
        matchesFilter = (status === 'active');
      } else if (activeFilter === 'hidden') {
        matchesFilter = (status === 'hidden');
      } else if (activeFilter === 'empty') {
        matchesFilter = (isEmpty === 'empty');
      }

      if (matchesSearch && matchesFilter) {
        row.classList.remove('d-none');
        visibleCount++;
      } else {
        row.classList.add('d-none');
      }
    });

    if (noResultsRow) {
      if (visibleCount === 0 && rows.length > 0) {
        noResultsRow.classList.remove('d-none');
      } else {
        noResultsRow.classList.add('d-none');
      }
    }
  }

  if (searchInput) {
    searchInput.addEventListener('input', applyFilters);
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', function() {
      searchInput.value = '';
      applyFilters();
      searchInput.focus();
    });
  }

  filterChips.forEach(function(chip) {
    chip.addEventListener('click', function() {
      filterChips.forEach(c => {
        c.classList.remove('active', 'btn-dark');
        if (c.getAttribute('data-filter') === 'active') c.className = 'btn btn-sm btn-outline-success rounded-pill px-3 py-1 filter-chip';
        else if (c.getAttribute('data-filter') === 'hidden') c.className = 'btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 filter-chip';
        else if (c.getAttribute('data-filter') === 'empty') c.className = 'btn btn-sm btn-outline-warning rounded-pill px-3 py-1 filter-chip';
        else c.className = 'btn btn-sm btn-outline-dark rounded-pill px-3 py-1 filter-chip';
      });

      chip.classList.add('active');
      chip.classList.remove('btn-outline-dark', 'btn-outline-success', 'btn-outline-secondary', 'btn-outline-warning');
      chip.classList.add('btn-dark');

      activeFilter = chip.getAttribute('data-filter');
      applyFilters();
    });
  });

  window.resetCategoryFilters = function() {
    if (searchInput) searchInput.value = '';
    const allChip = document.querySelector('.filter-chip[data-filter="all"]');
    if (allChip) allChip.click();
    else applyFilters();
  };

  // ---------------------------------------------------------------------------
  // 7. Initialize Bootstrap Tooltips
  // ---------------------------------------------------------------------------
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function(el) {
    return new bootstrap.Tooltip(el);
  });

  // ---------------------------------------------------------------------------
  // 8. Real-Time Live Sync & Change Detection
  // ---------------------------------------------------------------------------
  let catSyncCountdown = 5;
  let currentCatVer = <?= json_encode($initialCatVer ?? '') ?>;
  let currentDishVer = <?= json_encode($initialDishVer ?? '') ?>;
  let currentCombinedVer = <?= json_encode($initialCombinedVer ?? '') ?>;

  setInterval(async () => {
    catSyncCountdown--;
    const timerEl = document.getElementById('catSyncTimer');
    if (timerEl) timerEl.textContent = catSyncCountdown + 's';

    if (catSyncCountdown <= 0) {
      catSyncCountdown = 5;
      try {
        const res = await fetch('../api/catalog-sync.php?portal=categories&version=' + encodeURIComponent(currentCombinedVer) + '&cat_ver=' + encodeURIComponent(currentCatVer) + '&dish_ver=' + encodeURIComponent(currentDishVer), { cache: 'no-store' });
        if (!res.ok) return;
        const data = await res.json();
        if (data.success && data.changed) {
          currentCombinedVer = data.version;
          currentCatVer = data.cat_ver;
          currentDishVer = data.dish_ver;

          // Show Alert Banner
          const alertBox = document.getElementById('catLiveAlert');
          const alertMsg = document.getElementById('catLiveAlertMsg');
          const alertTitle = document.getElementById('catLiveAlertTitle');
          if (alertBox && alertMsg) {
            alertTitle.textContent = data.change_type === 'dish' ? '🔔 New Dish / Inventory Update!' : (data.change_type === 'category' ? '🔔 Category Update Detected!' : '🔔 Menu Catalog Updated!');
            alertMsg.textContent = data.message || 'Assigned dish counts and catalog metrics have been synchronized.';
            alertBox.classList.remove('d-none');
            alertBox.classList.add('d-flex');
          }

          // Real-time update KPI cards
          if (data.metrics) {
            const elTotalCats = document.getElementById('kpiTotalCategories');
            const elDishesMapped = document.getElementById('kpiDishesMapped');
            const elActive = document.getElementById('kpiActiveCount');
            const elHidden = document.getElementById('kpiHiddenCount');
            const elEmpty = document.getElementById('kpiEmptyCount');

            if (elTotalCats) elTotalCats.textContent = data.metrics.total_categories;
            if (elDishesMapped) elDishesMapped.textContent = data.metrics.total_dishes_mapped + ' dishes mapped';
            if (elActive) elActive.textContent = data.metrics.active_categories;
            if (elHidden) elHidden.textContent = (data.metrics.total_categories - data.metrics.active_categories);
            if (elEmpty) elEmpty.textContent = data.metrics.empty_categories;
          }

          // Real-time update Assigned Dishes pill badges across table rows
          if (data.category_dish_counts) {
            for (const [cid, cData] of Object.entries(data.category_dish_counts)) {
              const cell = document.getElementById('catDishesCell_' + cid);
              if (cell) {
                const count = cData.total_dishes;
                if (count > 0) {
                  cell.innerHTML = `
                    <a href="<?= ADMIN_URL ?>/dishes.php?category=${cid}" 
                       class="badge assigned-dishes-badge text-decoration-none px-3 py-2 rounded-pill d-inline-flex align-items-center gap-2" 
                       title="View and manage dishes under this category">
                      <i class="fa-solid fa-bowl-food"></i>
                      <span class="fw-bold">${count} ${count === 1 ? 'dish' : 'dishes'}</span>
                      <i class="bi bi-arrow-up-right small"></i>
                    </a>
                  `;
                } else {
                  cell.innerHTML = `
                    <a href="<?= ADMIN_URL ?>/dishes.php" 
                       class="badge empty-dishes-badge text-decoration-none px-3 py-2 rounded-pill d-inline-flex align-items-center gap-1.5" 
                       title="Empty section! Click to assign dishes in Dish Catalog.">
                      <i class="bi bi-exclamation-triangle-fill"></i>
                      <span class="fw-bold">0 dishes (Empty)</span>
                    </a>
                  `;
                }
                const row = document.getElementById('catRow_' + cid);
                if (row) {
                  row.dataset.empty = (count === 0) ? 'empty' : 'has-dishes';
                }
              }
            }
          }
        }
      } catch (err) {
        // Silently catch background errors
      }
    }
  }, 1000);

  function escapeHtml(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
