<?php
declare(strict_types=1);
/**
 * Food Moods & Vibe Curation Management
 * Zippy - Online Food Ordering Platform
 * Design: Figma "Lush Forest" Palette (#2E6F40, #68BA7F, #CEFFDC, #243E2C)
 */

$pageTitle = 'Food Moods & Vibe Curation';
$activePage = 'moods';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$currentUser = getCurrentUser();

// Handle All Form Submissions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid session security token. Please try again.');
    } else {
        $res = CatalogService::handleMoodAction($_POST);
        setFlash($res['type'], $res['message']);
    }
    header("Location: " . ADMIN_URL . "/moods.php");
    exit;
}

$moodsData = CatalogService::getAdminMoodsData();
$moods = $moodsData['moods'];
$totalMoods = $moodsData['totalMoods'];
$activeMoods = $moodsData['activeMoods'];
$totalDishesInMoods = $moodsData['totalDishesInMoods'];
$allDishes = $moodsData['allDishes'];

include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-4 py-3">

  <!-- Header Banner -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <span class="badge rounded-pill px-3 py-1 fw-bold" style="background:#243E2C; color:#CEFFDC; font-size:0.75rem;">
          <i class="fa-solid fa-masks-theater me-1"></i> STOREFRONT VIBE ENGINE
        </span>
        <span class="badge rounded-pill bg-light text-dark border px-2 py-1 small">Dynamic Curation</span>
      </div>
      <h2 class="fw-bold font-outfit text-dark mb-1">Food Moods & Vibe Curation</h2>
      <p class="text-muted small mb-0">Create, customize, and curate emotional & physical food moods, photos, and linked menu dishes.</p>
    </div>

    <div class="d-flex align-items-center gap-2">
      <a href="<?= CUSTOMER_URL ?>/#moodSection" target="_blank" class="btn btn-outline-success rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs">
        <i class="bi bi-box-arrow-up-right me-1"></i> View Live Storefront
      </a>
      <button type="button" class="btn btn-success rounded-pill px-4 py-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-2" style="background:#2E6F40; border-color:#2E6F40;" data-bs-toggle="modal" data-bs-target="#addMoodModal">
        <i class="bi bi-plus-lg"></i>
        <span>Add Food Mood</span>
      </button>
    </div>
  </div>

  <!-- KPI Metric Cards -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card border-0 rounded-4 shadow-xs p-3 bg-white h-100 border-start border-4 border-success">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold">Total Moods</div>
            <div class="fs-3 fw-bold text-dark font-outfit"><?= (int)$totalMoods ?></div>
          </div>
          <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px; height:48px; background:#EDFBF2; color:#2E6F40; font-size:1.4rem;">
            🎭
          </div>
        </div>
        <div class="small text-muted mt-2">Active vibe personas in system</div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card border-0 rounded-4 shadow-xs p-3 bg-white h-100 border-start border-4 border-primary">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold">Live on Storefront</div>
            <div class="fs-3 fw-bold text-dark font-outfit"><?= (int)$activeMoods ?></div>
          </div>
          <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px; height:48px; background:#E8E7FD; color:#3B28CC; font-size:1.4rem;">
            ✓
          </div>
        </div>
        <div class="small text-muted mt-2">Visible to customers right now</div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card border-0 rounded-4 shadow-xs p-3 bg-white h-100 border-start border-4 border-warning">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold">Mood Dish Links</div>
            <div class="fs-3 fw-bold text-dark font-outfit"><?= (int)$totalDishesInMoods ?></div>
          </div>
          <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px; height:48px; background:#FFF3CD; color:#856404; font-size:1.4rem;">
            🍲
          </div>
        </div>
        <div class="small text-muted mt-2">Dishes curated into mood vibes</div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card border-0 rounded-4 shadow-xs p-3 bg-white h-100 border-start border-4 border-info">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-semibold">Menu Coverage</div>
            <?php 
              $totalDishesCount = count($allDishes);
              $coverage = $totalDishesCount > 0 ? round(($totalDishesInMoods / $totalDishesCount) * 100) : 0;
            ?>
            <div class="fs-3 fw-bold text-dark font-outfit"><?= $coverage ?>%</div>
          </div>
          <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px; height:48px; background:#D1ECF1; color:#0C5460; font-size:1.4rem;">
            📊
          </div>
        </div>
        <div class="small text-muted mt-2">Dishes with emotional vibe tags</div>
      </div>
    </div>
  </div>

  <!-- Moods Catalog Table -->
  <div class="card border-0 rounded-4 shadow-sm bg-white overflow-hidden mb-4">
    <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <i class="fa-solid fa-list-check text-success"></i>
        <h5 class="fw-bold mb-0 text-dark font-outfit">Curated Moods Catalog & Items</h5>
      </div>
      <div class="small text-muted">
        Drag or use order buttons to arrange display priority on storefront
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-4" style="width: 50px;">#</th>
            <th style="width: 90px;">Photo</th>
            <th style="min-width: 170px;">Mood Name & Key</th>
            <th style="min-width: 200px;">Tagline / Vibe Description</th>
            <th style="min-width: 140px;">Badge Style</th>
            <th style="min-width: 140px;">Curated Dishes</th>
            <th style="width: 110px;">Order</th>
            <th style="width: 120px;">Status</th>
            <th class="text-end pe-4" style="min-width: 180px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($moods)): ?>
            <?php foreach ($moods as $idx => $m): ?>
              <tr>
                <td class="ps-4 fw-bold text-muted"><?= $idx + 1 ?></td>
                <td>
                  <div class="position-relative" style="width: 60px; height: 60px;">
                    <img src="<?= e(getMoodImageUrl($m['mood_key'])) ?>" alt="<?= e($m['name']) ?>" class="w-100 h-100 rounded-circle object-fit-cover shadow-xs border border-2 border-white">
                    <span class="position-absolute bottom-0 end-0 rounded-circle shadow-xs bg-white px-1 d-flex align-items-center justify-content-center" style="width: 22px; height: 22px; font-size: 0.75rem;">
                      <?= e($m['emoji']) ?>
                    </span>
                  </div>
                </td>
                <td>
                  <div class="fw-bold text-dark font-outfit fs-6"><?= e($m['name']) ?></div>
                  <div class="d-flex align-items-center gap-1 mt-1">
                    <code class="small text-muted px-2 py-0 bg-light rounded border"><?= e($m['mood_key']) ?></code>
                    <span class="badge bg-light text-secondary border small px-2 py-0"><?= e($m['short_name']) ?></span>
                  </div>
                </td>
                <td>
                  <p class="small text-muted mb-0" style="max-width: 280px; line-height: 1.35;">
                    <?= e($m['tagline']) ?>
                  </p>
                </td>
                <td>
                  <span class="badge px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs" style="background:<?= e($m['badge_bg']) ?>; color:<?= e($m['badge_color']) ?>; border:1px solid <?= e($m['border_color']) ?>;">
                    <?= e($m['emoji']) ?> <?= e($m['short_name']) ?>
                  </span>
                  <div class="small text-muted mt-1" style="font-size:0.7rem;">
                    Accent: <span class="d-inline-block rounded-circle" style="width:10px; height:10px; background:<?= e($m['accent']) ?>; vertical-align:middle;"></span> <?= e($m['accent']) ?>
                  </div>
                </td>
                <td>
                  <button type="button" class="btn btn-sm rounded-pill px-3 py-1 fw-bold d-inline-flex align-items-center gap-1 border shadow-xs" style="background:#EDFBF2; color:#2E6F40; border-color:#68BA7F !important;" onclick="openManageDishesModal(<?= e(jsJson((string)$m['mood_key'])) ?>, <?= e(jsJson((string)$m['name'])) ?>, <?= e(jsJson((string)getMoodImageUrl($m['mood_key']))) ?>)">
                    <i class="fa-solid fa-bowl-food"></i>
                    <span><?= (int)$m['dish_count'] ?> dishes</span>
                    <i class="bi bi-gear-fill small ms-1 text-success"></i>
                  </button>
                </td>
                <td>
                  <div class="d-flex align-items-center gap-1">
                    <span class="fw-bold small text-muted px-1"><?= (int)$m['display_order'] ?></span>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                      <input type="hidden" name="action" value="update_order">
                      <input type="hidden" name="mood_id" value="<?= (int)$m['mood_id'] ?>">
                      <input type="hidden" name="direction" value="up">
                      <button type="submit" class="btn btn-sm btn-outline-secondary p-0 px-1 rounded" title="Move Up"><i class="bi bi-chevron-up"></i></button>
                    </form>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                      <input type="hidden" name="action" value="update_order">
                      <input type="hidden" name="mood_id" value="<?= (int)$m['mood_id'] ?>">
                      <input type="hidden" name="direction" value="down">
                      <button type="submit" class="btn btn-sm btn-outline-secondary p-0 px-1 rounded" title="Move Down"><i class="bi bi-chevron-down"></i></button>
                    </form>
                  </div>
                </td>
                <td>
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="mood_id" value="<?= (int)$m['mood_id'] ?>">
                    <?php if ($m['is_active']): ?>
                      <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-bold shadow-xs" style="background:#2E6F40; border-color:#2E6F40;" title="Click to hide from storefront">
                        <i class="bi bi-check-circle me-1"></i>Active
                      </button>
                    <?php else: ?>
                      <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-bold" title="Click to show on storefront">
                        <i class="bi bi-eye-slash me-1"></i>Hidden
                      </button>
                    <?php endif; ?>
                  </form>
                </td>
                <td class="text-end pe-4">
                  <div class="btn-group shadow-xs">
                    <button type="button" class="btn btn-sm btn-outline-forest fw-semibold" onclick="openEditMoodModal(<?= e(jsJson($m)) ?>)">
                      <i class="bi bi-pencil me-1"></i>Edit
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success fw-semibold" onclick="openManageDishesModal(<?= e(jsJson((string)$m['mood_key'])) ?>, <?= e(jsJson((string)$m['name'])) ?>, <?= e(jsJson((string)getMoodImageUrl($m['mood_key']))) ?>)">
                      <i class="fa-solid fa-utensils me-1"></i>Items
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDeleteMood(<?= (int)$m['mood_id'] ?>, <?= e(jsJson((string)$m['name'])) ?>)">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="9" class="text-center py-5 text-muted">
                <i class="fa-solid fa-masks-theater fs-1 d-block mb-3 text-secondary"></i>
                <div class="fw-bold fs-5">No Food Moods Defined Yet</div>
                <p class="small text-muted mb-3">Add your first emotional vibe persona to elevate customer ordering.</p>
                <button type="button" class="btn btn-success rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addMoodModal">
                  Add Mood Now
                </button>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- =========================================================================
     MODAL 1: ADD FOOD MOOD
     ========================================================================= -->
<div class="modal fade" id="addMoodModal" tabindex="-1" aria-labelledby="addMoodModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="add_mood">

        <div class="modal-header border-bottom py-3 px-4" style="background: linear-gradient(135deg, #F0FAF3 0%, #E5F7EC 100%);">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:38px; height:38px; background:#2E6F40; color:#fff;">
              <i class="fa-solid fa-masks-theater"></i>
            </div>
            <div>
              <h5 class="modal-title fw-bold text-dark font-outfit mb-0" id="addMoodModalLabel">Create New Food Mood</h5>
              <div class="small text-muted">Add a human vibe persona with picture and taglines</div>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
          <div class="row g-3">
            
            <!-- Mood Photo & Preview -->
            <div class="col-12">
              <label class="form-label small fw-bold text-dark">Mood Food Picture <span class="text-danger">*</span></label>
              <div class="d-flex align-items-center gap-3 p-3 rounded-3 bg-light border">
                <div class="position-relative" style="width: 72px; height: 72px; flex-shrink: 0;">
                  <img id="addMoodPreviewImg" src="<?= BASE_URL ?>/assets/images/moods/comfort.jpg" alt="Preview" class="w-100 h-100 rounded-circle object-fit-cover shadow-xs border border-2 border-white">
                  <span id="addMoodPreviewEmoji" class="position-absolute bottom-0 end-0 rounded-circle shadow-xs bg-white px-1" style="font-size:0.75rem;">🍽️</span>
                </div>
                <div class="flex-grow-1">
                  <div class="mb-2">
                    <label class="small fw-semibold text-muted d-block">Upload Picture from Device:</label>
                    <input type="file" name="mood_image" class="form-control form-control-sm" accept="image/*" onchange="previewMoodImage(this, 'addMoodPreviewImg')">
                  </div>
                  <div>
                    <label class="small fw-semibold text-muted d-block">Or enter Image URL from Net:</label>
                    <input type="text" name="image_url" id="addMoodImageUrl" class="form-control form-control-sm" placeholder="https://images.unsplash.com/... or assets/images/moods/..." oninput="updateUrlPreview(this.value, 'addMoodPreviewImg')">
                  </div>
                </div>
              </div>
            </div>

            <!-- Mood Name & Slug -->
            <div class="col-md-6">
              <label class="form-label small fw-bold">Mood Full Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control rounded-3" placeholder="e.g. Midnight Cravings" required oninput="autoFillSlug(this.value, 'addMoodKey', 'addMoodShort')">
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-bold">Mood Identifier Key (Slug) <span class="text-danger">*</span></label>
              <input type="text" name="mood_key" id="addMoodKey" class="form-control rounded-3" placeholder="e.g. midnight_craving" required pattern="[A-Za-z0-9_]+">
              <div class="form-text small">Unique code used for dish tagging & URL filtering.</div>
            </div>

            <!-- Short Name & Emoji -->
            <div class="col-md-4">
              <label class="form-label small fw-bold">Short Display Name</label>
              <input type="text" name="short_name" id="addMoodShort" class="form-control rounded-3" placeholder="e.g. Midnight Crave">
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-bold">Emoji Icon</label>
              <input type="text" name="emoji" id="addMoodEmoji" class="form-control rounded-3" value="🌙" maxlength="10" oninput="document.getElementById('addMoodPreviewEmoji').textContent = this.value || '🍽️'">
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-bold">FontAwesome Icon</label>
              <input type="text" name="icon" class="form-control rounded-3" value="fa-solid fa-moon">
            </div>

            <!-- Tagline -->
            <div class="col-12">
              <label class="form-label small fw-bold">Storefront Tagline & Description <span class="text-danger">*</span></label>
              <textarea name="tagline" class="form-control rounded-3" rows="2" placeholder="e.g. Cheesy pizzas, loaded fries & hot desserts for your late night cravings" required></textarea>
            </div>

            <!-- Badge Styling Colors -->
            <div class="col-md-3">
              <label class="form-label small fw-bold">Badge Background</label>
              <input type="color" name="badge_bg" class="form-control form-control-color w-100" value="#FFF3CD">
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-bold">Badge Text Color</label>
              <input type="color" name="badge_color" class="form-control form-control-color w-100" value="#856404">
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-bold">Border Color</label>
              <input type="color" name="border_color" class="form-control form-control-color w-100" value="#FFE69C">
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-bold">Accent Color</label>
              <input type="color" name="accent" class="form-control form-control-color w-100" value="#FFC107">
            </div>

            <!-- Order & Active -->
            <div class="col-md-6">
              <label class="form-label small fw-bold">Display Priority Order</label>
              <input type="number" name="display_order" class="form-control rounded-3" value="<?= $totalMoods + 1 ?>" min="1">
            </div>

            <div class="col-md-6 d-flex align-items-center mt-4">
              <div class="form-check form-switch fs-5">
                <input class="form-check-input" type="checkbox" name="is_active" id="addMoodIsActive" value="1" checked>
                <label class="form-check-label fs-6 fw-semibold ms-2" for="addMoodIsActive">Active & Visible on Storefront</label>
              </div>
            </div>

          </div>
        </div>

        <div class="modal-footer border-top py-3 px-4 bg-light">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
            <i class="bi bi-check2-circle me-1"></i> Save Food Mood
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =========================================================================
     MODAL 2: EDIT FOOD MOOD & PHOTO
     ========================================================================= -->
<div class="modal fade" id="editMoodModal" tabindex="-1" aria-labelledby="editMoodModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="edit_mood">
        <input type="hidden" name="mood_id" id="editMoodId">

        <div class="modal-header border-bottom py-3 px-4" style="background: linear-gradient(135deg, #F0FAF3 0%, #E5F7EC 100%);">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:38px; height:38px; background:#2E6F40; color:#fff;">
              <i class="bi bi-pencil-square"></i>
            </div>
            <div>
              <h5 class="modal-title fw-bold text-dark font-outfit mb-0" id="editMoodModalLabel">Edit Food Mood & Photo</h5>
              <div class="small text-muted">Update image, persona vibes, and badge aesthetics</div>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
          <div class="row g-3">
            
            <!-- Mood Photo & Preview -->
            <div class="col-12">
              <label class="form-label small fw-bold text-dark">Mood Food Picture</label>
              <div class="d-flex align-items-center gap-3 p-3 rounded-3 bg-light border">
                <div class="position-relative" style="width: 76px; height: 76px; flex-shrink: 0;">
                  <img id="editMoodPreviewImg" src="" alt="Preview" class="w-100 h-100 rounded-circle object-fit-cover shadow-xs border border-2 border-white">
                  <span id="editMoodPreviewEmoji" class="position-absolute bottom-0 end-0 rounded-circle shadow-xs bg-white px-1" style="font-size:0.8rem;">🍽️</span>
                </div>
                <div class="flex-grow-1">
                  <div class="mb-2">
                    <label class="small fw-semibold text-muted d-block">Upload New Picture from Device:</label>
                    <input type="file" name="mood_image" class="form-control form-control-sm" accept="image/*" onchange="previewMoodImage(this, 'editMoodPreviewImg')">
                  </div>
                  <div>
                    <label class="small fw-semibold text-muted d-block">Or update Image URL from Net:</label>
                    <input type="text" name="image_url" id="editMoodImageUrl" class="form-control form-control-sm" placeholder="https://... or assets/images/moods/..." oninput="updateUrlPreview(this.value, 'editMoodPreviewImg')">
                  </div>
                </div>
              </div>
            </div>

            <!-- Mood Name & Slug -->
            <div class="col-md-6">
              <label class="form-label small fw-bold">Mood Full Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="editMoodName" class="form-control rounded-3" required>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-bold">Identifier Key (Slug)</label>
              <input type="text" id="editMoodKeyDisplay" class="form-control rounded-3 bg-light" readonly disabled>
              <div class="form-text small">Identifier key is fixed to maintain existing dish linkages.</div>
            </div>

            <!-- Short Name & Emoji -->
            <div class="col-md-4">
              <label class="form-label small fw-bold">Short Display Name</label>
              <input type="text" name="short_name" id="editMoodShort" class="form-control rounded-3">
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-bold">Emoji Icon</label>
              <input type="text" name="emoji" id="editMoodEmoji" class="form-control rounded-3" maxlength="10" oninput="document.getElementById('editMoodPreviewEmoji').textContent = this.value || '🍽️'">
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-bold">FontAwesome Icon</label>
              <input type="text" name="icon" id="editMoodIcon" class="form-control rounded-3">
            </div>

            <!-- Tagline -->
            <div class="col-12">
              <label class="form-label small fw-bold">Storefront Tagline & Description <span class="text-danger">*</span></label>
              <textarea name="tagline" id="editMoodTagline" class="form-control rounded-3" rows="2" required></textarea>
            </div>

            <!-- Badge Styling Colors -->
            <div class="col-md-3">
              <label class="form-label small fw-bold">Badge Background</label>
              <input type="color" name="badge_bg" id="editMoodBadgeBg" class="form-control form-control-color w-100">
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-bold">Badge Text Color</label>
              <input type="color" name="badge_color" id="editMoodBadgeColor" class="form-control form-control-color w-100">
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-bold">Border Color</label>
              <input type="color" name="border_color" id="editMoodBorderColor" class="form-control form-control-color w-100">
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-bold">Accent Color</label>
              <input type="color" name="accent" id="editMoodAccent" class="form-control form-control-color w-100">
            </div>

            <!-- Order & Active -->
            <div class="col-md-6">
              <label class="form-label small fw-bold">Display Priority Order</label>
              <input type="number" name="display_order" id="editMoodOrder" class="form-control rounded-3" min="1">
            </div>

            <div class="col-md-6 d-flex align-items-center mt-4">
              <div class="form-check form-switch fs-5">
                <input class="form-check-input" type="checkbox" name="is_active" id="editMoodIsActive" value="1">
                <label class="form-check-label fs-6 fw-semibold ms-2" for="editMoodIsActive">Active & Visible on Storefront</label>
              </div>
            </div>

          </div>
        </div>

        <div class="modal-footer border-top py-3 px-4 bg-light">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
            <i class="bi bi-check2-circle me-1"></i> Update Food Mood
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =========================================================================
     MODAL 3: MANAGE DISHES IN MOOD (ITEM CURATION & ASSIGNMENT)
     ========================================================================= -->
<div class="modal fade" id="manageDishesModal" tabindex="-1" aria-labelledby="manageDishesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="manage_mood_dishes">
        <input type="hidden" name="mood_key" id="manageDishesMoodKey">

        <div class="modal-header border-bottom py-3 px-4" style="background: linear-gradient(135deg, #F0FAF3 0%, #E5F7EC 100%);">
          <div class="d-flex align-items-center gap-3">
            <img id="manageDishesMoodPhoto" src="" alt="Mood" class="rounded-circle object-fit-cover shadow-xs border border-2 border-white" style="width: 48px; height: 48px;">
            <div>
              <div class="d-flex align-items-center gap-2">
                <h5 class="modal-title fw-bold text-dark font-outfit mb-0" id="manageDishesModalLabel">Manage Curated Dishes</h5>
                <span class="badge rounded-pill bg-success px-3 py-1 fw-bold" id="manageDishesMoodBadge"></span>
              </div>
              <div class="small text-muted">Select dishes from the menu catalog that belong to this emotional vibe persona</div>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
          <!-- Search & Filter Controls -->
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 p-3 bg-light rounded-3 border">
            <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 450px;">
              <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="dishSearchFilter" class="form-control border-start-0" placeholder="Filter dishes by name or category in real-time..." oninput="filterDishRows(this.value)">
              </div>
            </div>
            <div class="d-flex align-items-center gap-2">
              <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="toggleAllDishCheckboxes(true)">
                <i class="bi bi-check-all me-1"></i>Select All
              </button>
              <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="toggleAllDishCheckboxes(false)">
                <i class="bi bi-x-lg me-1"></i>Deselect All
              </button>
              <span class="badge bg-white text-dark border px-3 py-2 fw-semibold" id="selectedDishCounter">
                0 dishes selected
              </span>
            </div>
          </div>

          <!-- Dishes Checklist Table -->
          <div class="table-responsive" style="max-height: 480px;">
            <table class="table table-hover align-middle mb-0" id="manageDishesTable">
              <thead class="table-light sticky-top">
                <tr>
                  <th style="width: 45px;">
                    <input type="checkbox" class="form-check-input" id="masterDishCheckbox" onchange="toggleAllDishCheckboxes(this.checked)">
                  </th>
                  <th style="width: 60px;">Dish</th>
                  <th>Item Name & Description</th>
                  <th style="min-width: 140px;">Category</th>
                  <th style="width: 100px;">Price</th>
                  <th style="min-width: 150px;">Current Moods</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($allDishes as $dish): ?>
                  <tr class="dish-item-tr" data-dish-id="<?= (int)$dish['item_id'] ?>" data-name="<?= strtolower(e($dish['item_name'])) ?>" data-category="<?= strtolower(e($dish['category_name'] ?? '')) ?>" data-current-moods="<?= e($dish['mood'] ?? '') ?>">
                    <td>
                      <input type="checkbox" name="item_ids[]" value="<?= (int)$dish['item_id'] ?>" class="form-check-input dish-checkbox" onchange="updateSelectedDishCount()">
                    </td>
                    <td>
                      <img src="<?= e(getDishImageUrl($dish['image_url'])) ?>" alt="<?= e($dish['item_name']) ?>" class="rounded-3 object-fit-cover shadow-xs border" style="width: 44px; height: 44px;">
                    </td>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <?php if ($dish['is_veg']): ?>
                          <span class="food-badge-dot veg" title="Vegetarian" style="width:9px; height:9px; border-radius:50%; background:#229947; display:inline-block;"></span>
                        <?php else: ?>
                          <span class="food-badge-dot non-veg" title="Non-Vegetarian" style="width:9px; height:9px; border-radius:50%; background:#D32F2F; display:inline-block;"></span>
                        <?php endif; ?>
                        <span class="fw-bold text-dark font-outfit"><?= e($dish['item_name']) ?></span>
                      </div>
                    </td>
                    <td>
                      <span class="badge bg-light text-dark border small px-2 py-1">
                        <?= e($dish['category_name'] ?? 'Uncategorized') ?>
                      </span>
                    </td>
                    <td>
                      <span class="fw-bold text-dark font-outfit">₹<?= number_format((float)$dish['price'], 2) ?></span>
                    </td>
                    <td>
                      <?php 
                        $currParts = array_filter(array_map('trim', explode(',', $dish['mood'] ?? '')));
                        if (!empty($currParts)):
                          foreach ($currParts as $cp):
                      ?>
                        <span class="badge rounded-pill bg-light text-secondary border small px-2 py-0 me-1"><?= e($cp) ?></span>
                      <?php 
                          endforeach;
                        else:
                      ?>
                        <span class="text-muted small">None</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="modal-footer border-top py-3 px-4 bg-light d-flex justify-content-between align-items-center">
          <div class="small text-muted">
            <i class="bi bi-info-circle me-1"></i> Saving updates storefront filtering and search instantly.
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">
              <i class="bi bi-check2-circle me-1"></i> Save Curated Dishes
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =========================================================================
     MODAL 4: DELETE CONFIRMATION
     ========================================================================= -->
<div class="modal fade" id="deleteMoodModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
    <div class="modal-content rounded-4 border-0 shadow-lg text-center p-4">
      <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; background: #FDE8E8; color: #E02424; font-size: 1.5rem;">
        <i class="bi bi-exclamation-triangle"></i>
      </div>
      <h5 class="fw-bold font-outfit text-dark mb-1">Delete Food Mood?</h5>
      <p class="text-muted small mb-4" id="deleteMoodMsg"></p>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="delete_mood">
        <input type="hidden" name="mood_id" id="deleteMoodId">
        <div class="d-flex justify-content-center gap-2">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">Delete Mood</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Live Image File Preview
function previewMoodImage(input, targetImgId) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = document.getElementById(targetImgId);
      if (img) img.src = e.target.result;
    };
    reader.readAsDataURL(input.files[0]);
  }
}

// Live Image URL Preview
function updateUrlPreview(url, targetImgId) {
  if (url && url.trim().length > 5) {
    const img = document.getElementById(targetImgId);
    if (img) img.src = url.trim();
  }
}

// Auto-fill slug
function autoFillSlug(name, targetKeyId, targetShortId) {
  const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
  const keyEl = document.getElementById(targetKeyId);
  const shortEl = document.getElementById(targetShortId);
  if (keyEl && (!keyEl.value || keyEl.getAttribute('data-touched') !== 'true')) {
    keyEl.value = slug;
  }
  if (shortEl && (!shortEl.value || shortEl.getAttribute('data-touched') !== 'true')) {
    shortEl.value = name;
  }
}

// Open Edit Mood Modal
function openEditMoodModal(mood) {
  document.getElementById('editMoodId').value = mood.mood_id;
  document.getElementById('editMoodKeyDisplay').value = mood.mood_key;
  document.getElementById('editMoodName').value = mood.name || '';
  document.getElementById('editMoodShort').value = mood.short_name || '';
  document.getElementById('editMoodEmoji').value = mood.emoji || '🍽️';
  document.getElementById('editMoodIcon').value = mood.icon || 'fa-solid fa-utensils';
  document.getElementById('editMoodTagline').value = mood.tagline || '';
  document.getElementById('editMoodBadgeBg').value = mood.badge_bg || '#FFF3CD';
  document.getElementById('editMoodBadgeColor').value = mood.badge_color || '#856404';
  document.getElementById('editMoodBorderColor').value = mood.border_color || '#FFE69C';
  document.getElementById('editMoodAccent').value = mood.accent || '#FFC107';
  document.getElementById('editMoodOrder').value = mood.display_order || 1;
  document.getElementById('editMoodIsActive').checked = (parseInt(mood.is_active) === 1);
  document.getElementById('editMoodImageUrl').value = mood.image_url || '';

  const preview = document.getElementById('editMoodPreviewImg');
  if (preview) {
    let src = mood.image_url;
    if (!src || !src.startsWith('http')) {
      src = '<?= BASE_URL ?>/' + (src ? src.replace(/^\//, '') : 'assets/images/moods/comfort.jpg');
    }
    preview.src = src;
  }
  document.getElementById('editMoodPreviewEmoji').textContent = mood.emoji || '🍽️';

  const modal = new bootstrap.Modal(document.getElementById('editMoodModal'));
  modal.show();
}

// Open Manage Dishes Modal
function openManageDishesModal(moodKey, moodName, moodPhoto) {
  document.getElementById('manageDishesMoodKey').value = moodKey;
  document.getElementById('manageDishesMoodBadge').textContent = moodName;
  const photoEl = document.getElementById('manageDishesMoodPhoto');
  if (photoEl) photoEl.src = moodPhoto;

  // Clear search filter
  const searchEl = document.getElementById('dishSearchFilter');
  if (searchEl) searchEl.value = '';
  filterDishRows('');

  // Check / Uncheck boxes based on data-current-moods
  let selectedCount = 0;
  document.querySelectorAll('.dish-item-tr').forEach(row => {
    const moodsStr = row.getAttribute('data-current-moods') || '';
    const parts = moodsStr.split(',').map(s => s.trim().toLowerCase());
    const cb = row.querySelector('.dish-checkbox');
    if (cb) {
      if (parts.includes(moodKey.toLowerCase())) {
        cb.checked = true;
        selectedCount++;
      } else {
        cb.checked = false;
      }
    }
  });

  updateSelectedDishCount();

  const modal = new bootstrap.Modal(document.getElementById('manageDishesModal'));
  modal.show();
}

// Update selected dish count in modal header
function updateSelectedDishCount() {
  const checked = document.querySelectorAll('.dish-checkbox:checked').length;
  const counter = document.getElementById('selectedDishCounter');
  if (counter) {
    counter.textContent = checked + ' dishes selected';
  }
}

// Toggle all checkboxes
function toggleAllDishCheckboxes(check) {
  document.querySelectorAll('.dish-item-tr').forEach(row => {
    if (row.style.display !== 'none') {
      const cb = row.querySelector('.dish-checkbox');
      if (cb) cb.checked = check;
    }
  });
  updateSelectedDishCount();
}

// Real-time Search Filter for Dishes in Modal
function filterDishRows(q) {
  q = (q || '').toLowerCase().trim();
  document.querySelectorAll('.dish-item-tr').forEach(row => {
    const name = row.getAttribute('data-name') || '';
    const cat = row.getAttribute('data-category') || '';
    if (!q || name.includes(q) || cat.includes(q)) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}

// Confirm Delete Mood
function confirmDeleteMood(moodId, moodName) {
  document.getElementById('deleteMoodId').value = moodId;
  document.getElementById('deleteMoodMsg').textContent = `Are you sure you want to remove the mood "${moodName}"? Linked dishes will be unlinked automatically.`;
  const modal = new bootstrap.Modal(document.getElementById('deleteMoodModal'));
  modal.show();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
