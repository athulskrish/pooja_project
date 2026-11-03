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
require_admin();
$currentUser = current_user();

// Handle Add / Edit / Delete / Toggle
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
    } elseif ($_POST['action'] === 'add_category') {
        $name = clean_input($_POST['category_name']);
        $slug = clean_input($_POST['category_slug']) ?: strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $name));
        $desc = clean_input($_POST['category_description']);
        $image = clean_input($_POST['image_url'] ?? '');
        $order = (int)($_POST['display_order'] ?? 0);
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

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
              <img src="<?= e(get_category_image_url($cat['image_url'])) ?>" alt="<?= e($cat['category_name']) ?>" class="rounded-3 object-fit-cover shadow-sm" style="width: 48px; height: 48px;">
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
                        data-active="<?= (int)$cat['is_active'] ?>">
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
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<!-- Single Parameterized Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered text-start">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-pencil-square me-2 text-forest"></i>Edit Category: <span id="editCatTitle"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" action="<?= ADMIN_URL ?>/categories.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit_category">
        <input type="hidden" name="category_id" id="editCatId" value="">

        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-bold">Category Name *</label>
            <input type="text" name="category_name" id="editCatName" class="form-control rounded-3" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Routing Slug *</label>
            <input type="text" name="category_slug" id="editCatSlug" class="form-control rounded-3 font-monospace" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Description</label>
            <textarea name="category_description" id="editCatDesc" class="form-control rounded-3" rows="2"></textarea>
          </div>

          <!-- Category Photo: Both Upload & URL Options Available -->
          <div class="mb-3">
            <label class="form-label small fw-bold d-flex align-items-center justify-content-between mb-2">
              <span><i class="bi bi-image me-1 text-forest"></i> Category Photo (Upload Photo or Photo URL)</span>
              <span class="text-muted fw-normal" style="font-size: 0.75rem;">JPG, PNG, WEBP, GIF (Max 5MB)</span>
            </label>

            <div class="p-3 rounded-3 border bg-light-subtle">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="position-relative flex-shrink-0">
                  <img id="editCatPreview" 
                       src="" 
                       alt="Category Preview" 
                       class="rounded-3 border object-fit-cover shadow-sm bg-white" 
                       style="width: 64px; height: 64px;">
                  <span class="position-absolute bottom-0 end-0 badge bg-dark bg-opacity-75 rounded-circle p-1" style="transform: translate(20%, 20%);" title="Category Photo Preview">
                    <i class="bi bi-camera-fill text-white" style="font-size: 0.65rem;"></i>
                  </span>
                </div>

                <div class="flex-grow-1">
                  <div class="small fw-bold text-dark mb-1">
                    <i class="bi bi-upload text-forest me-1"></i> Option 1: Upload Photo from Device
                  </div>
                  <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <label for="editCatPhotoInput" class="btn btn-outline-success btn-sm rounded-pill px-3 py-1 fw-bold shadow-sm" style="border-color:#2E6F40; color:#2E6F40; cursor:pointer;">
                      <i class="bi bi-folder2-open me-1"></i> Browse & Upload Photo
                    </label>
                    <span id="editCatPhotoName" class="small text-muted text-truncate" style="max-width: 200px;">
                      No file chosen
                    </span>
                  </div>
                  <input type="file" 
                         id="editCatPhotoInput" 
                         name="category_image" 
                         class="d-none cat-photo-upload" 
                         accept="image/png, image/jpeg, image/jpg, image/webp, image/gif"
                         data-preview-id="editCatPreview"
                         data-name-id="editCatPhotoName">
                  <div class="text-muted" style="font-size: 0.75rem;">
                    Select an image file from your computer.
                  </div>
                </div>
              </div>

              <!-- Option 2: Direct Photo URL -->
              <div class="border-top pt-2">
                <label class="form-label small fw-bold text-dark mb-1" for="editCatUrlInput">
                  <i class="bi bi-link-45deg text-forest me-1"></i> Option 2: Photo Web URL
                </label>
                <input type="url" 
                       name="image_url" 
                       id="editCatUrlInput" 
                       class="form-control form-control-sm rounded-3 cat-url-input" 
                       placeholder="https://images.unsplash.com/..."
                       data-preview-id="editCatPreview">
                <div class="form-text small text-muted" style="font-size:0.75rem;">
                  Paste any image URL directly. If a file is uploaded above, it takes precedence.
                </div>
              </div>
            </div>
          </div>

          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editCatActive">
            <label class="form-check-label small" for="editCatActive">
              Visible in customer storefront navigation
            </label>
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
            <i class="bi bi-check2-circle me-1"></i> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-plus-circle me-2 text-forest"></i>Create New Food Category
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" action="<?= ADMIN_URL ?>/categories.php" enctype="multipart/form-data">
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

          <!-- Category Photo: Both Upload & URL Options Available -->
          <div class="mb-3">
            <label class="form-label small fw-bold d-flex align-items-center justify-content-between mb-2">
              <span><i class="bi bi-image me-1 text-forest"></i> Category Photo (Upload Photo or Photo URL)</span>
              <span class="text-muted fw-normal" style="font-size: 0.75rem;">JPG, PNG, WEBP, GIF (Max 5MB)</span>
            </label>

            <div class="p-3 rounded-3 border bg-light-subtle">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="position-relative flex-shrink-0">
                  <img id="catPreview_add" 
                       src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300" 
                       alt="Category Preview" 
                       class="rounded-3 border object-fit-cover shadow-sm bg-white" 
                       style="width: 64px; height: 64px;">
                  <span class="position-absolute bottom-0 end-0 badge bg-dark bg-opacity-75 rounded-circle p-1" style="transform: translate(20%, 20%);" title="Category Photo Preview">
                    <i class="bi bi-camera-fill text-white" style="font-size: 0.65rem;"></i>
                  </span>
                </div>

                <div class="flex-grow-1">
                  <div class="small fw-bold text-dark mb-1">
                    <i class="bi bi-upload text-forest me-1"></i> Option 1: Upload Photo from Device
                  </div>
                  <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <label for="catPhotoInput_add" class="btn btn-outline-success btn-sm rounded-pill px-3 py-1 fw-bold shadow-sm" style="border-color:#2E6F40; color:#2E6F40; cursor:pointer;">
                      <i class="bi bi-folder2-open me-1"></i> Browse & Upload Photo
                    </label>
                    <span id="catPhotoName_add" class="small text-muted text-truncate" style="max-width: 200px;">
                      No file chosen
                    </span>
                  </div>
                  <input type="file" 
                         id="catPhotoInput_add" 
                         name="category_image" 
                         class="d-none cat-photo-upload" 
                         accept="image/png, image/jpeg, image/jpg, image/webp, image/gif"
                         data-preview-id="catPreview_add"
                         data-name-id="catPhotoName_add">
                  <div class="text-muted" style="font-size: 0.75rem;">
                    Select an image from your computer or leave for default image.
                  </div>
                </div>
              </div>

              <!-- Option 2: Direct Photo URL (Always Visible!) -->
              <div class="border-top pt-2">
                <label class="form-label small fw-bold text-dark mb-1" for="catUrlInput_add">
                  <i class="bi bi-link-45deg text-forest me-1"></i> Option 2: Photo Web URL
                </label>
                <input type="url" 
                       name="image_url" 
                       id="catUrlInput_add" 
                       class="form-control form-control-sm rounded-3 cat-url-input" 
                       placeholder="https://images.unsplash.com/..."
                       data-preview-id="catPreview_add">
                <div class="form-text small text-muted" style="font-size:0.75rem;">
                  Paste any direct image link. If a file is uploaded above, it will take precedence.
                </div>
              </div>
            </div>
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
          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
            <i class="bi bi-plus-lg me-1"></i> Create Category
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Populate Single Parameterized Edit Category Modal
  document.querySelectorAll('.btn-edit-category').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const d = this.dataset;
      document.getElementById('editCatId').value = d.id;
      document.getElementById('editCatTitle').textContent = d.name;
      document.getElementById('editCatName').value = d.name;
      document.getElementById('editCatSlug').value = d.slug;
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

  // File upload preview
  document.querySelectorAll('.cat-photo-upload').forEach(function(input) {
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

  // Direct URL input preview
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

  function escapeHtml(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
