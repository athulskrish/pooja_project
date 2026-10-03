<?php
/**
 * Dish Catalog & Live Stock Inventory Management (CRUD)
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Dish Catalog & Inventory Management';
$activePage = 'dishes';

// Bootstrap core libraries before HTML output (prevents "headers already sent")
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
$currentUser = current_user();

// Handle Add / Edit / Stock Update / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
    } elseif ($_POST['action'] === 'add_dish') {
        $name = clean_input($_POST['item_name']);
        $catId = (int)$_POST['category_id'];
        $price = (float)$_POST['price'];
        $stock = (int)$_POST['stock_quantity'];
        $prep = (int)$_POST['prep_time_minutes'];
        $isVeg = !empty($_POST['is_veg']) ? 1 : 0;
        $image = clean_input($_POST['image_url']);
        $desc = clean_input($_POST['description']);

        if (!empty($name) && $price > 0) {
            db_query("
                INSERT INTO `menu_items` (`category_id`, `item_name`, `description`, `price`, `stock_quantity`, `is_available`, `is_veg`, `prep_time_minutes`, `image_url`) 
                VALUES (:cid, :name, :desc, :price, :stock, 1, :veg, :prep, :img)
            ", [
                ':cid' => $catId,
                ':name' => $name,
                ':desc' => $desc,
                ':price' => $price,
                ':stock' => $stock,
                ':veg' => $isVeg,
                ':prep' => $prep,
                ':img' => $image
            ]);
            set_flash('success', "Dish '{$name}' added to catalog.");
            header("Location: " . ADMIN_URL . "/dishes.php");
            exit;
        }
    } elseif ($_POST['action'] === 'edit_dish') {
        $itemId = (int)$_POST['item_id'];
        $name = clean_input($_POST['item_name']);
        $catId = (int)$_POST['category_id'];
        $price = (float)$_POST['price'];
        $stock = (int)$_POST['stock_quantity'];
        $prep = (int)$_POST['prep_time_minutes'];
        $isVeg = !empty($_POST['is_veg']) ? 1 : 0;
        $isAvailable = !empty($_POST['is_available']) ? 1 : 0;
        $image = clean_input($_POST['image_url']);
        $desc = clean_input($_POST['description']);

        db_query("
            UPDATE `menu_items` 
            SET `category_id` = :cid, `item_name` = :name, `description` = :desc, 
                `price` = :price, `stock_quantity` = :stock, `is_available` = :avail, 
                `is_veg` = :veg, `prep_time_minutes` = :prep, `image_url` = :img 
            WHERE `item_id` = :id
        ", [
            ':cid' => $catId,
            ':name' => $name,
            ':desc' => $desc,
            ':price' => $price,
            ':stock' => $stock,
            ':avail' => $isAvailable,
            ':veg' => $isVeg,
            ':prep' => $prep,
            ':img' => $image,
            ':id' => $itemId
        ]);
        set_flash('success', "Dish '{$name}' updated successfully.");
        header("Location: " . ADMIN_URL . "/dishes.php");
        exit;
    } elseif ($_POST['action'] === 'quick_stock') {
        $itemId = (int)$_POST['item_id'];
        $delta = (int)$_POST['delta'];
        db_query("UPDATE `menu_items` SET `stock_quantity` = GREATEST(0, `stock_quantity` + :d), `is_available` = IF(`stock_quantity` + :d > 0, 1, 0) WHERE `item_id` = :id", [
            ':d' => $delta,
            ':id' => $itemId
        ]);
        set_flash('success', "Stock quantity adjusted.");
        header("Location: " . ADMIN_URL . "/dishes.php");
        exit;
    } elseif ($_POST['action'] === 'delete_dish') {
        $itemId = (int)$_POST['item_id'];
        db_query("DELETE FROM `menu_items` WHERE `item_id` = :id", [':id' => $itemId]);
        set_flash('success', "Dish removed from catalog.");
        header("Location: " . ADMIN_URL . "/dishes.php");
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';

// Fetch categories for filter and dropdowns
$categories = db_fetch_all("SELECT * FROM `categories` ORDER BY `display_order` ASC");

$filterCat = clean_input($_GET['category'] ?? '');
$where = "1=1";
$params = [];
if (!empty($filterCat)) {
    $where .= " AND m.category_id = :cid";
    $params[':cid'] = (int)$filterCat;
}

$dishes = db_fetch_all("
    SELECT m.*, c.category_name 
    FROM `menu_items` m
    JOIN `categories` c ON m.category_id = c.category_id
    WHERE $where
    ORDER BY m.category_id ASC, m.item_name ASC
", $params);
?>

<div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
  
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-3 mb-4">
    <div>
      <h4 class="fw-bold font-outfit mb-1 text-dark">Menu Catalog & Stock Reserves</h4>
      <p class="text-muted small mb-0">Synchronized with pessimistic concurrency locks on checkout</p>
    </div>

    <div class="d-flex align-items-center gap-2">
      <!-- Filter Category -->
      <form method="GET" action="<?= ADMIN_URL ?>/dishes.php" class="d-flex gap-2 m-0">
        <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['category_id'] ?>" <?= ($filterCat == $cat['category_id']) ? 'selected' : '' ?>>
              <?= e($cat['category_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>

      <button type="button" class="btn btn-success rounded-pill px-3 btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addDishModal" style="background:#2E6F40; border-color:#2E6F40;">
        <i class="bi bi-plus-lg me-1"></i> Add New Dish
      </button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle small mb-0">
      <thead class="table-light">
        <tr>
          <th>Dish Item</th>
          <th>Category</th>
          <th>Type</th>
          <th>Price</th>
          <th>Live Stock</th>
          <th>Prep Time</th>
          <th>Status</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($dishes as $dish): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-3">
                <img src="<?= e($dish['image_url']) ?>" alt="<?= e($dish['item_name']) ?>" class="rounded-3 object-fit-cover shadow-sm" style="width: 45px; height: 45px;">
                <div>
                  <div class="fw-bold text-dark"><?= e($dish['item_name']) ?></div>
                  <div class="small text-muted text-truncate" style="max-width: 250px;"><?= e($dish['description']) ?></div>
                </div>
              </div>
            </td>

            <td><?= e($dish['category_name']) ?></td>

            <td>
              <?php if ($dish['is_veg']): ?>
                <span class="badge bg-white text-success border border-success px-2 py-1" style="font-size:0.65rem;">VEG</span>
              <?php else: ?>
                <span class="badge bg-white text-danger border border-danger px-2 py-1" style="font-size:0.65rem;">NON-VEG</span>
              <?php endif; ?>
            </td>

            <td class="fw-bold text-forest fs-6"><?= format_inr($dish['price']) ?></td>

            <td>
              <div class="d-flex align-items-center gap-2">
                <span class="badge <?= ($dish['stock_quantity'] <= 15) ? 'bg-danger' : 'bg-light text-dark border' ?> fs-6 px-2 py-1">
                  <?= (int)$dish['stock_quantity'] ?> left
                </span>

                <!-- Quick Delta Buttons -->
                <form method="POST" action="<?= ADMIN_URL ?>/dishes.php" class="d-inline m-0">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="quick_stock">
                  <input type="hidden" name="item_id" value="<?= (int)$dish['item_id'] ?>">
                  <input type="hidden" name="delta" value="10">
                  <button type="submit" class="btn btn-outline-success btn-sm py-0 px-2" title="Add 10 portions">+10</button>
                </form>
              </div>
            </td>

            <td><i class="bi bi-clock me-1 text-muted"></i> <?= (int)$dish['prep_time_minutes'] ?>m</td>

            <td>
              <?php if ($dish['is_available'] && $dish['stock_quantity'] > 0): ?>
                <span class="badge bg-success bg-opacity-25 text-success rounded-pill px-2 py-1">In Stock</span>
              <?php else: ?>
                <span class="badge bg-danger rounded-pill px-2 py-1">Out of Stock</span>
              <?php endif; ?>
            </td>

            <td class="text-end">
              <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#editDishModal<?= (int)$dish['item_id'] ?>">
                  Edit
                </button>

                <form method="POST" action="<?= ADMIN_URL ?>/dishes.php" class="m-0" onsubmit="return confirm('Delete this dish from menu?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_dish">
                  <input type="hidden" name="item_id" value="<?= (int)$dish['item_id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2">
                    <i class="bi bi-trash3"></i>
                  </button>
                </form>
              </div>

              <!-- Edit Dish Modal -->
              <div class="modal fade" id="editDishModal<?= (int)$dish['item_id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered text-start">
                  <div class="modal-content rounded-4 border-0 shadow-lg">
                    <div class="modal-header border-bottom">
                      <h5 class="modal-title fw-bold">Edit Dish: <?= e($dish['item_name']) ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <form method="POST" action="<?= ADMIN_URL ?>/dishes.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="edit_dish">
                      <input type="hidden" name="item_id" value="<?= (int)$dish['item_id'] ?>">

                      <div class="modal-body p-4">
                        <div class="mb-3">
                          <label class="form-label small fw-bold">Dish Name *</label>
                          <input type="text" name="item_name" class="form-control rounded-3" value="<?= e($dish['item_name']) ?>" required>
                        </div>

                        <div class="row g-3 mb-3">
                          <div class="col-md-6">
                            <label class="form-label small fw-bold">Category *</label>
                            <select name="category_id" class="form-select rounded-3">
                              <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['category_id'] ?>" <?= ($dish['category_id'] == $c['category_id']) ? 'selected' : '' ?>>
                                  <?= e($c['category_name']) ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </div>

                          <div class="col-md-6">
                            <label class="form-label small fw-bold">Price (₹) *</label>
                            <input type="number" step="0.01" name="price" class="form-control rounded-3" value="<?= (float)$dish['price'] ?>" required>
                          </div>
                        </div>

                        <div class="row g-3 mb-3">
                          <div class="col-md-6">
                            <label class="form-label small fw-bold">Current Stock Quantity *</label>
                            <input type="number" name="stock_quantity" class="form-control rounded-3" value="<?= (int)$dish['stock_quantity'] ?>" required>
                          </div>

                          <div class="col-md-6">
                            <label class="form-label small fw-bold">Prep Time (Mins) *</label>
                            <input type="number" name="prep_time_minutes" class="form-control rounded-3" value="<?= (int)$dish['prep_time_minutes'] ?>" required>
                          </div>
                        </div>

                        <div class="mb-3">
                          <label class="form-label small fw-bold">Image URL</label>
                          <input type="url" name="image_url" class="form-control rounded-3" value="<?= e($dish['image_url']) ?>">
                        </div>

                        <div class="mb-3">
                          <label class="form-label small fw-bold">Description & Ingredients</label>
                          <textarea name="description" class="form-control rounded-3" rows="2"><?= e($dish['description']) ?></textarea>
                        </div>

                        <div class="row g-3">
                          <div class="col-6">
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="is_veg" value="1" id="vegE<?= (int)$dish['item_id'] ?>" <?= $dish['is_veg'] ? 'checked' : '' ?>>
                              <label class="form-check-label small" for="vegE<?= (int)$dish['item_id'] ?>">Vegetarian Dish</label>
                            </div>
                          </div>
                          <div class="col-6">
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="is_available" value="1" id="availE<?= (int)$dish['item_id'] ?>" <?= $dish['is_available'] ? 'checked' : '' ?>>
                              <label class="form-check-label small" for="availE<?= (int)$dish['item_id'] ?>">In Stock / Available</label>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="modal-footer border-top">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">Save Dish</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>

            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<!-- Add Dish Modal -->
<div class="modal fade" id="addDishModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold">Add New Dish to Catalog</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" action="<?= ADMIN_URL ?>/dishes.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_dish">

        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-bold">Dish Title *</label>
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
              <label class="form-label small fw-bold">Price (₹) *</label>
              <input type="number" step="0.01" name="price" class="form-control rounded-3" placeholder="249.00" required>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Initial Stock (Portions) *</label>
              <input type="number" name="stock_quantity" class="form-control rounded-3" value="40" required>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-bold">Estimated Prep Time (Mins) *</label>
              <input type="number" name="prep_time_minutes" class="form-control rounded-3" value="20" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Cover Image URL</label>
            <input type="url" name="image_url" class="form-control rounded-3" placeholder="https://images.unsplash.com/...">
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Description & Ingredients</label>
            <textarea name="description" class="form-control rounded-3" rows="2" placeholder="Culinary flavor profile..."></textarea>
          </div>

          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_veg" value="1" id="vegAdd" checked>
            <label class="form-check-label small" for="vegAdd">Vegetarian Dish</label>
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">Add Dish</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
