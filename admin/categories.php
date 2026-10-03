<?php
/**
 * Food Categories CRUD Management (3NF Schema)
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Food Categories Management';
$activePage = 'categories';

// Bootstrap core libraries before HTML output (prevents "headers already sent")
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
$currentUser = current_user();

// Handle Add / Edit / Delete / Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
    } elseif ($_POST['action'] === 'add_category') {
        $name = clean_input($_POST['category_name']);
        $slug = clean_input($_POST['category_slug']) ?: strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $name));
        $desc = clean_input($_POST['category_description']);
        $image = clean_input($_POST['image_url']);
        $order = (int)($_POST['display_order'] ?? 0);
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        if (!empty($name)) {
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
            set_flash('success', "Category '{$name}' created successfully.");
            header("Location: " . ADMIN_URL . "/categories.php");
            exit;
        }
    } elseif ($_POST['action'] === 'edit_category') {
        $cid = (int)$_POST['category_id'];
        $name = clean_input($_POST['category_name']);
        $slug = clean_input($_POST['category_slug']);
        $desc = clean_input($_POST['category_description']);
        $image = clean_input($_POST['image_url']);
        $order = (int)($_POST['display_order'] ?? 0);
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

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
    } elseif ($_POST['action'] === 'delete_category') {
        $cid = (int)$_POST['category_id'];
        $dishCount = db_fetch_val("SELECT COUNT(*) FROM `menu_items` WHERE `category_id` = :id", [':id' => $cid]);
        if ($dishCount > 0) {
            set_flash('danger', "Cannot delete category because it contains {$dishCount} active menu dishes. Reassign or delete dishes first.");
        } else {
            db_query("DELETE FROM `categories` WHERE `category_id` = :id", [':id' => $cid]);
            set_flash('success', "Category deleted successfully.");
        }
        header("Location: " . ADMIN_URL . "/categories.php");
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';

// Fetch all categories with dish counts
$categories = db_fetch_all("
    SELECT c.*, COUNT(m.item_id) AS total_dishes
    FROM `categories` c
    LEFT JOIN `menu_items` m ON c.category_id = m.category_id
    GROUP BY c.category_id
    ORDER BY c.display_order ASC, c.category_id ASC
");
?>

<div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
  
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-3 mb-4">
    <div>
      <h4 class="fw-bold font-outfit mb-1 text-dark">Food Categories Catalog</h4>
      <p class="text-muted small mb-0">Managed relational taxonomy in <code>categories</code> table</p>
    </div>

    <button type="button" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal" style="background:#2E6F40; border-color:#2E6F40;">
      <i class="bi bi-plus-lg me-1"></i> Add Category
    </button>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle small mb-0">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>Preview</th>
          <th>Category Name</th>
          <th>Slug</th>
          <th>Dishes Count</th>
          <th>Status</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $cat): ?>
          <tr>
            <td class="font-monospace fw-bold">#<?= (int)$cat['category_id'] ?></td>
            <td>
              <img src="<?= e($cat['image_url']) ?>" alt="<?= e($cat['category_name']) ?>" class="rounded-3 object-fit-cover shadow-sm" style="width: 48px; height: 48px;">
            </td>
            <td>
              <div class="fw-bold text-dark fs-6"><?= e($cat['category_name']) ?></div>
              <div class="small text-muted text-truncate" style="max-width: 280px;"><?= e($cat['category_description']) ?></div>
            </td>
            <td class="font-monospace text-muted"><?= e($cat['category_slug']) ?></td>
            <td>
              <span class="badge bg-light text-dark border fs-6 px-2 py-1"><?= (int)$cat['total_dishes'] ?> items</span>
            </td>
            <td>
              <?php if ($cat['is_active']): ?>
                <span class="badge bg-success bg-opacity-25 text-success px-2 py-1 rounded-pill">Active</span>
              <?php else: ?>
                <span class="badge bg-secondary px-2 py-1 rounded-pill">Hidden</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#editCategoryModal<?= (int)$cat['category_id'] ?>">
                  <i class="bi bi-pencil me-1"></i> Edit
                </button>

                <form method="POST" action="<?= ADMIN_URL ?>/categories.php" class="m-0" onsubmit="return confirm('Are you sure you want to delete this category?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_category">
                  <input type="hidden" name="category_id" value="<?= (int)$cat['category_id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2">
                    <i class="bi bi-trash3"></i>
                  </button>
                </form>
              </div>

              <!-- Edit Modal -->
              <div class="modal fade" id="editCategoryModal<?= (int)$cat['category_id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered text-start">
                  <div class="modal-content rounded-4 border-0 shadow-lg">
                    <div class="modal-header border-bottom">
                      <h5 class="modal-title fw-bold">Edit Category: <?= e($cat['category_name']) ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <form method="POST" action="<?= ADMIN_URL ?>/categories.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="edit_category">
                      <input type="hidden" name="category_id" value="<?= (int)$cat['category_id'] ?>">

                      <div class="modal-body p-4">
                        <div class="mb-3">
                          <label class="form-label small fw-bold">Category Name *</label>
                          <input type="text" name="category_name" class="form-control rounded-3" value="<?= e($cat['category_name']) ?>" required>
                        </div>

                        <div class="mb-3">
                          <label class="form-label small fw-bold">Routing Slug *</label>
                          <input type="text" name="category_slug" class="form-control rounded-3 font-monospace" value="<?= e($cat['category_slug']) ?>" required>
                        </div>

                        <div class="mb-3">
                          <label class="form-label small fw-bold">Description</label>
                          <textarea name="category_description" class="form-control rounded-3" rows="2"><?= e($cat['category_description']) ?></textarea>
                        </div>

                        <div class="mb-3">
                          <label class="form-label small fw-bold">Image URL</label>
                          <input type="url" name="image_url" class="form-control rounded-3" value="<?= e($cat['image_url']) ?>">
                        </div>

                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" name="is_active" value="1" id="actEdit<?= (int)$cat['category_id'] ?>" <?= $cat['is_active'] ? 'checked' : '' ?>>
                          <label class="form-check-label small" for="actEdit<?= (int)$cat['category_id'] ?>">
                            Visible in customer storefront navigation
                          </label>
                        </div>
                      </div>

                      <div class="modal-footer border-top">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">Save Changes</button>
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

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold">Create New Food Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" action="<?= ADMIN_URL ?>/categories.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_category">

        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-bold">Category Title *</label>
            <input type="text" name="category_name" class="form-control rounded-3" placeholder="E.g. Chaat & Street Food" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">URL-Safe Routing Slug</label>
            <input type="text" name="category_slug" class="form-control rounded-3 font-monospace" placeholder="E.g. chaat-street-food">
            <div class="form-text small">Leave blank to auto-generate from title.</div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Description</label>
            <textarea name="category_description" class="form-control rounded-3" rows="2" placeholder="Summary of items in this category..."></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Category Cover Image URL</label>
            <input type="url" name="image_url" class="form-control rounded-3" placeholder="https://images.unsplash.com/...">
          </div>

          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="actAdd" checked>
            <label class="form-check-label small" for="actAdd">
              Make active immediately on customer storefront
            </label>
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">Create Category</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
