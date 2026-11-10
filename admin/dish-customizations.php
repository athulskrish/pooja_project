<?php
declare(strict_types=1);
/**
 * Dish Customisation Management (Spice Levels, Toppings, Extra Cheese, Add-ons)
 * Zippy - Online Food Ordering Platform
 * Design: Figma "Lush Forest" Palette (#Combination-4)
 */

$pageTitle = 'Dish Customisations Manager';
$activePage = 'dish_customizations';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$currentUser = getCurrentUser();

// Fetch all active dishes for the selector dropdown
$allDishes = CatalogService::getCustomizationDishList();

// Determine selected dish (default to first dish or GET parameter)
$selectedItemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : (int)($allDishes[0]['item_id'] ?? 1);

// Find selected dish details
$currentDish = null;
foreach ($allDishes as $d) {
    if ((int)$d['item_id'] === $selectedItemId) {
        $currentDish = $d;
        break;
    }
}
if (!$currentDish && !empty($allDishes)) {
    $currentDish = $allDishes[0];
    $selectedItemId = (int)$currentDish['item_id'];
}

// Handle Form Actions (Add Group, Edit Group, Soft Delete Group, Add Option, Edit Option, Soft Delete Option, Quick Template)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token. Please try again.');
        header("Location: " . ADMIN_URL . "/dish-customizations.php?item_id=" . $selectedItemId);
        exit;
    }

    $res = CatalogService::handleCustomizationAction($_POST);
    setFlash($res['type'], $res['message']);
    $redirectItemId = (int)($_POST['item_id'] ?? $selectedItemId);
    header("Location: " . ADMIN_URL . "/dish-customizations.php?item_id=" . $redirectItemId);
    exit;
}

// Fetch customization groups and options for the selected dish
$customGroups = getDishCustomizationGroups($selectedItemId, false);

// Total customisation metrics
$totalDishesCount = count($allDishes);
$customSummaryMap = getAllDishesCustomizationCounts();
$totalCustomizableDishes = count(array_filter($customSummaryMap, fn(array $c): bool => $c['group_count'] > 0));

require_once __DIR__ . '/includes/header.php';
?>

<div class="content-body pb-5">
  <div class="container-fluid px-3 px-md-4">

    <!-- Breadcrumb & Top Bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
      <div>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-1 small">
            <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/index.php" class="text-decoration-none text-muted">Admin</a></li>
            <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/dishes.php" class="text-decoration-none text-muted">Dishes & Stock</a></li>
            <li class="breadcrumb-item active text-forest fw-bold" aria-current="page">Customisations</li>
          </ol>
        </nav>
        <h2 class="fw-bold mb-0 font-outfit" style="color: #1C2B20;">
          <i class="bi bi-sliders2 me-2 text-success"></i>Dish Customisations Manager
        </h2>
        <div class="text-muted small">
          Configure authentic spice levels, extra cheese, gourmet toppings & add-ons for customer selection.
        </div>
      </div>

      <div class="d-flex align-items-center gap-2">
        <a href="<?= ADMIN_URL ?>/dishes.php" class="btn btn-outline-secondary rounded-pill px-3">
          <i class="bi bi-arrow-left me-1"></i> Back to Dish Catalog
        </a>
        <button type="button" class="btn btn-success rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addGroupModal" style="background:#2E6F40; border-color:#2E6F40;">
          <i class="bi bi-plus-circle me-1"></i> Add Custom Group
        </button>
      </div>
    </div>

    <!-- Dish Selector & Stats Strip -->
    <div class="row g-3 mb-4">
      <div class="col-lg-8">
        <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100">
          <label class="form-label fw-bold text-uppercase small text-muted mb-2">
            <i class="bi bi-search me-1"></i>Select Dish to Manage Customisations
          </label>
          <div class="row g-2 align-items-center">
            <div class="col-md-9">
              <select class="form-select form-select-lg rounded-3 shadow-none border-success" onchange="window.location.href = '<?= ADMIN_URL ?>/dish-customizations.php?item_id=' + this.value;">
                <?php foreach ($allDishes as $d): ?>
                  <?php 
                    $dSummary = $customSummaryMap[$d['item_id']] ?? ['group_count' => 0, 'option_count' => 0];
                    $isSelected = ($d['item_id'] == $selectedItemId);
                  ?>
                  <option value="<?= (int)$d['item_id'] ?>" <?= $isSelected ? 'selected' : '' ?>>
                    #<?= $d['item_id'] ?>: <?= e($d['item_name']) ?> (<?= e($d['category_name']) ?> - <?= formatInr($d['price']) ?>) 
                    [<?= $dSummary['group_count'] ?> Groups, <?= $dSummary['option_count'] ?> Options]
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <div class="d-grid">
                <a href="<?= CUSTOMER_URL ?>/index.php" target="_blank" class="btn btn-outline-forest rounded-3 py-2 small" style="border-color: #2E6F40; color: #2E6F40;">
                  <i class="bi bi-shop me-1"></i> View Live Store
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100 d-flex flex-column justify-content-center">
          <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="small text-muted fw-semibold">Catalog Coverage:</span>
            <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1 fw-bold"><?= $totalCustomizableDishes ?> / <?= $totalDishesCount ?> Dishes Active</span>
          </div>
          <div class="progress mb-2" style="height: 7px;">
            <div class="progress-bar bg-success" style="width: <?= ($totalDishesCount > 0) ? round(($totalCustomizableDishes / $totalDishesCount) * 100) : 0 ?>%;"></div>
          </div>
          <div class="small text-muted d-flex justify-content-between">
            <span>Groups for this Dish: <strong><?= count($customGroups) ?></strong></span>
            <span>Total Options: <strong><?= array_sum(array_map(fn(array $g): int => count($g['options'] ?? []), $customGroups)) ?></strong></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Active Dish Info Banner -->
    <?php if ($currentDish): ?>
      <div class="card border-0 rounded-4 shadow-sm mb-4 bg-white overflow-hidden border-start border-4 border-success">
        <div class="p-3 p-md-4">
          <div class="row align-items-center g-3">
            <div class="col-auto">
              <div class="position-relative" style="width: 76px; height: 76px;">
                <img src="<?= e(getDishImageUrl($currentDish['image_url'])) ?>" 
                     alt="<?= e($currentDish['item_name']) ?>" 
                     class="rounded-4 w-100 h-100 object-fit-cover shadow-sm border"
                     onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300'">
                <span class="position-absolute top-0 start-0 m-1" 
                      style="width: 10px; height: 10px; border-radius: 50%; background: <?= $currentDish['is_veg'] ? '#229947' : '#D32F2F' ?>; border: 1.5px solid #fff;" 
                      title="<?= $currentDish['is_veg'] ? 'Vegetarian' : 'Non-Vegetarian' ?>"></span>
              </div>
            </div>
            
            <div class="col">
              <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="badge <?= $currentDish['is_veg'] ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?> rounded-pill px-2 py-1" style="font-size: 0.65rem;">
                  <?= $currentDish['is_veg'] ? 'PURE VEG' : 'NON-VEG' ?>
                </span>
                <span class="badge bg-light text-dark border rounded-pill px-2 py-1" style="font-size: 0.65rem;">
                  <?= e($currentDish['category_name']) ?>
                </span>
                <?php if (!empty($currentDish['is_healthy'])): ?>
                  <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1" style="font-size: 0.65rem;">
                    <i class="bi bi-heart-pulse-fill me-1"></i>HEALTHY FIT
                  </span>
                <?php endif; ?>
                <span class="badge <?= ($currentDish['is_available'] && $currentDish['stock_quantity'] > 0) ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> rounded-pill px-2 py-1" style="font-size: 0.65rem;">
                  <?= ($currentDish['is_available'] && $currentDish['stock_quantity'] > 0) ? 'In Stock (' . $currentDish['stock_quantity'] . ')' : 'Sold Out' ?>
                </span>
              </div>
              
              <h4 class="fw-bold mb-1 font-outfit" style="color: #1C2B20;"><?= e($currentDish['item_name']) ?></h4>
              <div class="d-flex align-items-center gap-3">
                <span class="fs-5 fw-bold text-forest"><?= formatInr($currentDish['price']) ?></span>
                <span class="text-muted small">Dish ID: #<?= $currentDish['item_id'] ?></span>
              </div>
            </div>

            <div class="col-12 col-md-auto d-flex flex-wrap gap-2">
              <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addGroupModal">
                <i class="bi bi-plus-lg me-1"></i> New Group
              </button>
              <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                  <i class="bi bi-magic me-1"></i> Quick Templates
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3">
                  <li>
                    <form method="POST" action="<?= ADMIN_URL ?>/dish-customizations.php?item_id=<?= $selectedItemId ?>">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="quick_template">
                      <input type="hidden" name="template_type" value="spice">
                      <input type="hidden" name="item_id" value="<?= $selectedItemId ?>">
                      <button type="submit" class="dropdown-item py-2 small">
                        <i class="bi bi-fire text-danger me-2"></i>Add Spice Level Template (Mild, Medium, Fiery)
                      </button>
                    </form>
                  </li>
                  <li>
                    <form method="POST" action="<?= ADMIN_URL ?>/dish-customizations.php?item_id=<?= $selectedItemId ?>">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="quick_template">
                      <input type="hidden" name="template_type" value="cheese_toppings">
                      <input type="hidden" name="item_id" value="<?= $selectedItemId ?>">
                      <button type="submit" class="dropdown-item py-2 small">
                        <i class="bi bi-layers text-warning me-2"></i>Add Extra Cheese & Toppings Template
                      </button>
                    </form>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Customisation Groups List -->
    <?php if (empty($customGroups)): ?>
      <div class="card border-0 rounded-4 shadow-sm p-5 text-center bg-white">
        <i class="bi bi-sliders2 fs-1 text-muted opacity-50 mb-3 d-block"></i>
        <h5 class="fw-bold text-dark">No customisations configured yet for this dish</h5>
        <p class="text-muted small mx-auto" style="max-width: 480px;">
          Add spice levels, choice of dressings, extra cheese slices, or gourmet toppings so customers can personalize this food item before ordering.
        </p>
        <div class="d-flex justify-content-center gap-2 mt-2">
          <button type="button" class="btn btn-success rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addGroupModal">
            <i class="bi bi-plus-circle me-1"></i> Add Custom Group
          </button>
          <form method="POST" action="<?= ADMIN_URL ?>/dish-customizations.php?item_id=<?= $selectedItemId ?>" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="quick_template">
            <input type="hidden" name="template_type" value="spice">
            <input type="hidden" name="item_id" value="<?= $selectedItemId ?>">
            <button type="submit" class="btn btn-outline-danger rounded-pill px-3">
              <i class="bi bi-fire me-1"></i> Quick Spice Level
            </button>
          </form>
          <form method="POST" action="<?= ADMIN_URL ?>/dish-customizations.php?item_id=<?= $selectedItemId ?>" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="quick_template">
            <input type="hidden" name="template_type" value="cheese_toppings">
            <input type="hidden" name="item_id" value="<?= $selectedItemId ?>">
            <button type="submit" class="btn btn-outline-warning rounded-pill px-3">
              <i class="bi bi-layers me-1"></i> Quick Cheese & Toppings
            </button>
          </form>
        </div>
      </div>
    <?php else: ?>

      <div class="row g-4">
        <?php foreach ($customGroups as $group): ?>
          <div class="col-12">
            <div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden">
              
              <!-- Group Header -->
              <div class="card-header bg-light border-bottom p-3 p-md-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                  <div class="rounded-3 p-2 text-white" style="background:#243E2C;">
                    <i class="bi bi-<?= $group['selection_type'] === 'radio' ? 'ui-radios-grid' : 'ui-checks-grid' ?> fs-5"></i>
                  </div>
                  <div>
                    <div class="d-flex align-items-center gap-2">
                      <h5 class="fw-bold mb-0 text-dark"><?= e($group['group_name']) ?></h5>
                      <span class="badge <?= $group['selection_type'] === 'radio' ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-warning-subtle text-dark border border-warning-subtle' ?> rounded-pill px-2 py-1" style="font-size: 0.65rem;">
                        <?= $group['selection_type'] === 'radio' ? 'RADIO (SINGLE CHOICE)' : 'CHECKBOX (MULTI-SELECT)' ?>
                      </span>
                      <span class="badge <?= $group['is_required'] ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-secondary-subtle text-secondary' ?> rounded-pill px-2 py-1" style="font-size: 0.65rem;">
                        <?= $group['is_required'] ? 'REQUIRED' : 'OPTIONAL' ?>
                      </span>
                    </div>
                    <div class="text-muted small mt-1">
                      Display Order: <?= (int)$group['display_order'] ?> &bull; Options Count: <?= count($group['options']) ?>
                    </div>
                  </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                  <button type="button" 
                          class="btn btn-sm btn-outline-success rounded-pill px-3" 
                          data-bs-toggle="modal" 
                          data-bs-target="#addOptionModal"
                          data-group-id="<?= (int)$group['group_id'] ?>"
                          data-group-name="<?= e($group['group_name']) ?>">
                    <i class="bi bi-plus-lg me-1"></i> Add Option
                  </button>

                  <button type="button" 
                          class="btn btn-sm btn-outline-primary rounded-pill px-3 btn-edit-group"
                          data-bs-toggle="modal" 
                          data-bs-target="#editGroupModal"
                          data-group-id="<?= (int)$group['group_id'] ?>"
                          data-group-name="<?= e($group['group_name']) ?>"
                          data-selection-type="<?= e($group['selection_type']) ?>"
                          data-is-required="<?= (int)$group['is_required'] ?>"
                          data-display-order="<?= (int)$group['display_order'] ?>">
                    <i class="bi bi-pencil me-1"></i> Edit Group
                  </button>

                  <form method="POST" action="<?= ADMIN_URL ?>/dish-customizations.php?item_id=<?= $selectedItemId ?>" onsubmit="return confirm('Delete this group and all its options?');" class="d-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_group">
                    <input type="hidden" name="group_id" value="<?= (int)$group['group_id'] ?>">
                    <input type="hidden" name="item_id" value="<?= $selectedItemId ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Delete Group">
                      <i class="bi bi-trash3"></i>
                    </button>
                  </form>
                </div>
              </div>

              <!-- Options Table -->
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light small text-uppercase text-muted">
                    <tr>
                      <th class="ps-4" style="width: 50px;">Order</th>
                      <th>Option Name</th>
                      <th>Price Adjustment</th>
                      <th>Default Selection</th>
                      <th>Status</th>
                      <th class="text-end pe-4">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($group['options'])): ?>
                      <tr>
                        <td colspan="6" class="text-center py-4 text-muted small">
                          No options added to this group yet. Click <strong>Add Option</strong> above to add choices.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($group['options'] as $opt): ?>
                        <tr>
                          <td class="ps-4 fw-bold text-muted small">#<?= (int)$opt['display_order'] ?></td>
                          <td>
                            <div class="fw-bold text-dark"><?= e($opt['option_name']) ?></div>
                          </td>
                          <td>
                            <?php if ((float)$opt['price'] > 0): ?>
                              <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 fw-bold">
                                +<?= formatInr($opt['price']) ?>
                              </span>
                            <?php else: ?>
                              <span class="badge bg-light text-muted border rounded-pill px-2 py-1">
                                Free (₹0.00)
                              </span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <?php if (!empty($opt['is_default'])): ?>
                              <span class="badge bg-primary text-white rounded-pill px-2 py-1 small">
                                <i class="bi bi-check2-circle me-1"></i>Pre-selected
                              </span>
                            <?php else: ?>
                              <span class="text-muted small">&mdash;</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <form method="POST" action="<?= ADMIN_URL ?>/dish-customizations.php?item_id=<?= $selectedItemId ?>" class="d-inline">
                              <?= csrfField() ?>
                              <input type="hidden" name="action" value="toggle_option_availability">
                              <input type="hidden" name="option_id" value="<?= (int)$opt['option_id'] ?>">
                              <input type="hidden" name="item_id" value="<?= $selectedItemId ?>">
                              <button type="submit" class="btn btn-sm <?= !empty($opt['is_available']) ? 'btn-outline-success' : 'btn-outline-danger' ?> rounded-pill px-2 py-0" style="font-size: 0.75rem;">
                                <?= !empty($opt['is_available']) ? '<i class="bi bi-check-circle me-1"></i>Active' : '<i class="bi bi-x-circle me-1"></i>Disabled' ?>
                              </button>
                            </form>
                          </td>
                          <td class="text-end pe-4">
                            <div class="d-flex justify-content-end gap-2">
                              <button type="button" 
                                      class="btn btn-sm btn-outline-primary rounded-pill px-2 btn-edit-option"
                                      data-bs-toggle="modal" 
                                      data-bs-target="#editOptionModal"
                                      data-option-id="<?= (int)$opt['option_id'] ?>"
                                      data-group-id="<?= (int)$group['group_id'] ?>"
                                      data-option-name="<?= e($opt['option_name']) ?>"
                                      data-price="<?= (float)$opt['price'] ?>"
                                      data-is-default="<?= (int)$opt['is_default'] ?>"
                                      data-is-available="<?= (int)$opt['is_available'] ?>"
                                      data-display-order="<?= (int)$opt['display_order'] ?>">
                                <i class="bi bi-pencil me-1"></i> Edit
                              </button>

                              <form method="POST" action="<?= ADMIN_URL ?>/dish-customizations.php?item_id=<?= $selectedItemId ?>" onsubmit="return confirm('Remove option ' + <?= e(jsJson((string)$opt['option_name'])) ?> + '?');" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_option">
                                <input type="hidden" name="option_id" value="<?= (int)$opt['option_id'] ?>">
                                <input type="hidden" name="item_id" value="<?= $selectedItemId ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Delete Option">
                                  <i class="bi bi-trash3"></i>
                                </button>
                              </form>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

            </div>
          </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>

  </div>
</div>

<!-- =========================================================================
     MODAL: ADD CUSTOMISATION GROUP
     ========================================================================= -->
<div class="modal fade" id="addGroupModal" tabindex="-1" aria-labelledby="addGroupModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <form method="POST" action="<?= ADMIN_URL ?>/dish-customizations.php?item_id=<?= $selectedItemId ?>">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_group">
        <input type="hidden" name="item_id" value="<?= $selectedItemId ?>">

        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold" id="addGroupModalLabel">
            <i class="bi bi-folder-plus text-success me-2"></i>Add Customisation Group
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-bold text-muted">TARGET DISH</label>
            <input type="text" class="form-control bg-light rounded-3" value="<?= e($currentDish['item_name'] ?? '') ?>" disabled>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Group Name <span class="text-danger">*</span></label>
            <input type="text" name="group_name" class="form-control rounded-3" placeholder="e.g. Spice Level, Extra Cheese & Toppings, Crust Choice" required>
            <div class="form-text small">e.g., "Spice Level", "Toppings & Extra Cheese", "Sweetness & Ice", "Dressing Style"</div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Selection Type <span class="text-danger">*</span></label>
            <select name="selection_type" class="form-select rounded-3">
              <option value="radio">Single Choice (Radio - e.g. Spice Level: Mild, Medium, Hot)</option>
              <option value="checkbox" selected>Multiple Choice (Checkbox - e.g. Extra Cheese, Toppings)</option>
            </select>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Display Order</label>
              <input type="number" name="display_order" class="form-control rounded-3" value="<?= count($customGroups) + 1 ?>" min="1">
            </div>
            <div class="col-6 d-flex align-items-center">
              <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="is_required" value="1" id="addIsRequired">
                <label class="form-check-label small fw-bold" for="addIsRequired">
                  Required Choice
                </label>
                <div class="form-text small" style="font-size:0.7rem;">Customer MUST pick at least 1</div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer border-top bg-light">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4" style="background:#2E6F40; border-color:#2E6F40;">Create Group</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =========================================================================
     MODAL: EDIT CUSTOMISATION GROUP
     ========================================================================= -->
<div class="modal fade" id="editGroupModal" tabindex="-1" aria-labelledby="editGroupModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <form method="POST" action="<?= ADMIN_URL ?>/dish-customizations.php?item_id=<?= $selectedItemId ?>">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit_group">
        <input type="hidden" name="group_id" id="editGroupId">
        <input type="hidden" name="item_id" value="<?= $selectedItemId ?>">

        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold" id="editGroupModalLabel">
            <i class="bi bi-pencil-square text-primary me-2"></i>Edit Customisation Group
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-bold">Group Name <span class="text-danger">*</span></label>
            <input type="text" name="group_name" id="editGroupName" class="form-control rounded-3" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Selection Type <span class="text-danger">*</span></label>
            <select name="selection_type" id="editSelectionType" class="form-select rounded-3">
              <option value="radio">Single Choice (Radio - e.g. Spice Level)</option>
              <option value="checkbox">Multiple Choice (Checkbox - e.g. Extra Cheese & Toppings)</option>
            </select>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Display Order</label>
              <input type="number" name="display_order" id="editDisplayOrder" class="form-control rounded-3" min="1">
            </div>
            <div class="col-6 d-flex align-items-center">
              <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="is_required" value="1" id="editIsRequired">
                <label class="form-check-label small fw-bold" for="editIsRequired">
                  Required Choice
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer border-top bg-light">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary rounded-pill px-4">Update Group</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =========================================================================
     MODAL: ADD OPTION
     ========================================================================= -->
<div class="modal fade" id="addOptionModal" tabindex="-1" aria-labelledby="addOptionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <form method="POST" action="<?= ADMIN_URL ?>/dish-customizations.php?item_id=<?= $selectedItemId ?>">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_option">
        <input type="hidden" name="group_id" id="addOptionGroupId">
        <input type="hidden" name="item_id" value="<?= $selectedItemId ?>">

        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold" id="addOptionModalLabel">
            <i class="bi bi-tag-fill text-success me-2"></i>Add Customisation Option
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-bold text-muted">GROUP</label>
            <input type="text" id="addOptionGroupNameDisplay" class="form-control bg-light rounded-3" disabled>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Option Name <span class="text-danger">*</span></label>
            <input type="text" name="option_name" class="form-control rounded-3" placeholder="e.g. Extra Cheese, Mild Spice, Grilled Mushrooms" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Additional Price (INR ₹)</label>
            <div class="input-group">
              <span class="input-group-text bg-light">₹</span>
              <input type="number" name="price" class="form-control rounded-end-3" step="0.50" min="0" value="0.00" placeholder="0.00">
            </div>
            <div class="form-text small">Enter 0.00 for free options (e.g. Spice levels or dressing choices).</div>
          </div>

          <div class="row g-3 mb-2">
            <div class="col-6">
              <label class="form-label small fw-bold">Display Order</label>
              <input type="number" name="display_order" class="form-control rounded-3" value="1" min="1">
            </div>
            <div class="col-6">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="addOptDefault">
                <label class="form-check-label small fw-bold" for="addOptDefault">
                  Pre-selected Default
                </label>
              </div>
            </div>
          </div>

          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="is_available" value="1" id="addOptAvail" checked>
            <label class="form-check-label small" for="addOptAvail">
              Available in Restaurant Kitchen
            </label>
          </div>
        </div>

        <div class="modal-footer border-top bg-light">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4" style="background:#2E6F40; border-color:#2E6F40;">Save Option</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =========================================================================
     MODAL: EDIT OPTION
     ========================================================================= -->
<div class="modal fade" id="editOptionModal" tabindex="-1" aria-labelledby="editOptionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <form method="POST" action="<?= ADMIN_URL ?>/dish-customizations.php?item_id=<?= $selectedItemId ?>">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit_option">
        <input type="hidden" name="option_id" id="editOptionId">
        <input type="hidden" name="group_id" id="editOptionGroupId">
        <input type="hidden" name="item_id" value="<?= $selectedItemId ?>">

        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold" id="editOptionModalLabel">
            <i class="bi bi-pencil-square text-primary me-2"></i>Edit Customisation Option
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-bold">Option Name <span class="text-danger">*</span></label>
            <input type="text" name="option_name" id="editOptionName" class="form-control rounded-3" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Additional Price (INR ₹)</label>
            <div class="input-group">
              <span class="input-group-text bg-light">₹</span>
              <input type="number" name="price" id="editOptionPrice" class="form-control rounded-end-3" step="0.50" min="0" required>
            </div>
          </div>

          <div class="row g-3 mb-2">
            <div class="col-6">
              <label class="form-label small fw-bold">Display Order</label>
              <input type="number" name="display_order" id="editOptionDisplayOrder" class="form-control rounded-3" min="1">
            </div>
            <div class="col-6">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="editOptDefault">
                <label class="form-check-label small fw-bold" for="editOptDefault">
                  Pre-selected Default
                </label>
              </div>
            </div>
          </div>

          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="is_available" value="1" id="editOptAvail">
            <label class="form-check-label small" for="editOptAvail">
              Available in Restaurant Kitchen
            </label>
          </div>
        </div>

        <div class="modal-footer border-top bg-light">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary rounded-pill px-4">Update Option</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Populate Add Option Modal
  const addOptionModal = document.getElementById('addOptionModal');
  if (addOptionModal) {
    addOptionModal.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      if (button) {
        const gid = button.getAttribute('data-group-id');
        const gname = button.getAttribute('data-group-name');
        document.getElementById('addOptionGroupId').value = gid || '';
        document.getElementById('addOptionGroupNameDisplay').value = gname || '';
      }
    });
  }

  // Populate Edit Group Modal
  const editGroupModal = document.getElementById('editGroupModal');
  if (editGroupModal) {
    editGroupModal.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      if (button) {
        document.getElementById('editGroupId').value = button.getAttribute('data-group-id') || '';
        document.getElementById('editGroupName').value = button.getAttribute('data-group-name') || '';
        document.getElementById('editSelectionType').value = button.getAttribute('data-selection-type') || 'checkbox';
        document.getElementById('editIsRequired').checked = (button.getAttribute('data-is-required') == '1');
        document.getElementById('editDisplayOrder').value = button.getAttribute('data-display-order') || '1';
      }
    });
  }

  // Populate Edit Option Modal
  const editOptionModal = document.getElementById('editOptionModal');
  if (editOptionModal) {
    editOptionModal.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      if (button) {
        document.getElementById('editOptionId').value = button.getAttribute('data-option-id') || '';
        document.getElementById('editOptionGroupId').value = button.getAttribute('data-group-id') || '';
        document.getElementById('editOptionName').value = button.getAttribute('data-option-name') || '';
        document.getElementById('editOptionPrice').value = button.getAttribute('data-price') || '0.00';
        document.getElementById('editOptDefault').checked = (button.getAttribute('data-is-default') == '1');
        document.getElementById('editOptAvail').checked = (button.getAttribute('data-is-available') == '1');
        document.getElementById('editOptionDisplayOrder').value = button.getAttribute('data-display-order') || '1';
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
