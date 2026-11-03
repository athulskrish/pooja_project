<?php
/**
 * Dynamic FAQ & Help Desk Knowledge Base Management
 * Zippy - Online Food Ordering Platform
 * Design: Figma "Lush Forest" Palette (#Combination-4)
 */

$pageTitle = 'Dynamic FAQs & Help Desk';
$activePage = 'faqs';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$currentUser = current_user();

/**
 * Sanitize FAQ answer content allowing safe formatting tags
 */
function sanitize_faq_content(string $input): string {
    $trimmed = trim($input);
    // Allowed safe formatting tags for help center
    $allowed = '<p><br><br/><b><strong><i><em><u><ul><ol><li><code><pre><a><span>';
    $cleaned = strip_tags($trimmed, $allowed);
    
    // If input contains no HTML tags at all, auto-convert newlines to <br>
    if (!preg_match('/<(?:p|br|ul|ol|li)\b[^>]*>/i', $cleaned)) {
        $cleaned = nl2br($cleaned);
    }
    return $cleaned;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid or expired session token.');
        header("Location: " . ADMIN_URL . "/faqs.php");
        exit;
    }

    $action = $_POST['action'];

    // 1. Add New FAQ
    if ($action === 'add_faq') {
        $question = clean_input($_POST['question'] ?? '');
        $rawAnswer = $_POST['answer'] ?? '';
        $answer = sanitize_faq_content($rawAnswer);
        $category = clean_input($_POST['category'] ?? 'General');
        $displayOrder = (int)($_POST['display_order'] ?? 10);
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        if (!empty($question) && !empty($answer)) {
            db_query("
                INSERT INTO `faqs` (`question`, `answer`, `category`, `display_order`, `is_active`)
                VALUES (:q, :a, :c, :o, :act)
            ", [
                ':q' => $question,
                ':a' => $answer,
                ':c' => $category,
                ':o' => $displayOrder,
                ':act' => $isActive
            ]);
            set_flash('success', 'New FAQ topic published to knowledge base.');
        } else {
            set_flash('danger', 'Please provide both question and answer.');
        }
        header("Location: " . ADMIN_URL . "/faqs.php");
        exit;
    }

    // 2. Edit Existing FAQ
    if ($action === 'edit_faq') {
        $faqId = (int)($_POST['faq_id'] ?? 0);
        $question = clean_input($_POST['question'] ?? '');
        $rawAnswer = $_POST['answer'] ?? '';
        $answer = sanitize_faq_content($rawAnswer);
        $category = clean_input($_POST['category'] ?? 'General');
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        if ($faqId > 0 && !empty($question) && !empty($answer)) {
            db_query("
                UPDATE `faqs`
                SET `question` = :q, `answer` = :a, `category` = :c, `display_order` = :o, `is_active` = :act
                WHERE `faq_id` = :id
            ", [
                ':q' => $question,
                ':a' => $answer,
                ':c' => $category,
                ':o' => $displayOrder,
                ':act' => $isActive,
                ':id' => $faqId
            ]);
            set_flash('success', 'FAQ topic updated successfully.');
        } else {
            set_flash('danger', 'Valid question and answer are required.');
        }
        header("Location: " . ADMIN_URL . "/faqs.php");
        exit;
    }

    // 3. Toggle Active Status
    if ($action === 'toggle_status') {
        $faqId = (int)($_POST['faq_id'] ?? 0);
        $newStatus = (int)($_POST['is_active'] ?? 0);

        if ($faqId > 0) {
            db_query("UPDATE `faqs` SET `is_active` = :act WHERE `faq_id` = :id", [
                ':act' => $newStatus,
                ':id' => $faqId
            ]);
            set_flash('success', $newStatus ? 'FAQ topic is now live on storefront.' : 'FAQ topic moved to drafts.');
        }
        header("Location: " . ADMIN_URL . "/faqs.php");
        exit;
    }

    // 4. Delete FAQ
    if ($action === 'delete_faq') {
        $faqId = (int)($_POST['faq_id'] ?? 0);
        if ($faqId > 0) {
            db_query("DELETE FROM `faqs` WHERE `faq_id` = :id", [':id' => $faqId]);
            set_flash('success', 'FAQ topic permanently deleted.');
        }
        header("Location: " . ADMIN_URL . "/faqs.php");
        exit;
    }

    // 5. Quick Reorder (Nudge Up / Down)
    if ($action === 'reorder_faq') {
        $faqId = (int)($_POST['faq_id'] ?? 0);
        $direction = clean_input($_POST['direction'] ?? 'up');
        if ($faqId > 0) {
            if ($direction === 'up') {
                db_query("UPDATE `faqs` SET `display_order` = GREATEST(0, `display_order` - 1) WHERE `faq_id` = :id", [':id' => $faqId]);
            } else {
                db_query("UPDATE `faqs` SET `display_order` = `display_order` + 1 WHERE `faq_id` = :id", [':id' => $faqId]);
            }
            set_flash('success', 'Display order adjusted.');
        }
        header("Location: " . ADMIN_URL . "/faqs.php");
        exit;
    }

    // 6. Duplicate FAQ
    if ($action === 'duplicate_faq') {
        $faqId = (int)($_POST['faq_id'] ?? 0);
        if ($faqId > 0) {
            $existing = db_fetch_one("SELECT * FROM `faqs` WHERE `faq_id` = :id", [':id' => $faqId]);
            if ($existing) {
                db_query("
                    INSERT INTO `faqs` (`question`, `answer`, `category`, `display_order`, `is_active`)
                    VALUES (:q, :a, :c, :o, 0)
                ", [
                    ':q' => 'Copy of ' . $existing['question'],
                    ':a' => $existing['answer'],
                    ':c' => $existing['category'],
                    ':o' => (int)$existing['display_order'] + 1
                ]);
                set_flash('success', 'FAQ duplicated as draft. You can now tweak and publish it.');
            }
        }
        header("Location: " . ADMIN_URL . "/faqs.php");
        exit;
    }
}

// -----------------------------------------------------------------------------
// KPI Summary Calculations
// -----------------------------------------------------------------------------
$totalFaqs = (int)(db_fetch_val("SELECT COUNT(*) FROM `faqs`") ?: 0);
$publishedFaqs = (int)(db_fetch_val("SELECT COUNT(*) FROM `faqs` WHERE `is_active` = 1") ?: 0);
$draftFaqs = (int)(db_fetch_val("SELECT COUNT(*) FROM `faqs` WHERE `is_active` = 0") ?: 0);
$totalCategories = (int)(db_fetch_val("SELECT COUNT(DISTINCT `category`) FROM `faqs` WHERE `category` IS NOT NULL AND `category` != ''") ?: 0);
$maxOrder = (int)(db_fetch_val("SELECT MAX(`display_order`) FROM `faqs`") ?: 0);

// Default + Dynamic Categories
$defaultCategories = ['General', 'Orders & Cancellation', 'Delivery & Tracking', 'Billing & Payments', 'Account & Privacy'];
$dbCats = db_fetch_all("SELECT DISTINCT `category` FROM `faqs` WHERE `category` IS NOT NULL AND `category` != ''");
$existingCats = array_filter(array_column($dbCats, 'category'));
$categories = array_values(array_unique(array_merge($defaultCategories, $existingCats)));
sort($categories);

// Category Counts
$categoryCounts = [];
$countsRaw = db_fetch_all("SELECT `category`, COUNT(*) as cnt FROM `faqs` GROUP BY `category`");
foreach ($countsRaw as $cRow) {
    $categoryCounts[$cRow['category']] = (int)$cRow['cnt'];
}

// -----------------------------------------------------------------------------
// Filtering & Query Building
// -----------------------------------------------------------------------------
$categoryFilter = clean_input($_GET['category'] ?? '');
$statusFilter = clean_input($_GET['status'] ?? '');
$searchQuery = clean_input($_GET['q'] ?? '');

$where = "1=1";
$params = [];

if (!empty($categoryFilter)) {
    $where .= " AND `category` = :cat";
    $params[':cat'] = $categoryFilter;
}

if ($statusFilter === 'published') {
    $where .= " AND `is_active` = 1";
} elseif ($statusFilter === 'draft') {
    $where .= " AND `is_active` = 0";
}

if (!empty($searchQuery)) {
    $where .= " AND (`question` LIKE :q OR `answer` LIKE :q OR `category` LIKE :q)";
    $params[':q'] = "%{$searchQuery}%";
}

$faqs = [];
try {
    $faqs = db_fetch_all("
        SELECT * FROM `faqs`
        WHERE $where
        ORDER BY `display_order` ASC, `faq_id` ASC
    ", $params);
} catch (Exception $e) {
    $faqs = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- =======================================================================
     KPI SUMMARY METRICS ROW
     ======================================================================= -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 text-success bg-success bg-opacity-10 fs-4">
          <i class="bi bi-question-diamond-fill"></i>
        </div>
        <div>
          <div class="text-muted small fw-semibold">Total FAQ Topics</div>
          <div class="h4 fw-bold text-dark mb-0 font-outfit"><?= $totalFaqs ?></div>
          <span class="badge bg-success bg-opacity-10 text-success small">Self-Service Hub</span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 text-success bg-success bg-opacity-10 fs-4">
          <i class="bi bi-check2-circle"></i>
        </div>
        <div>
          <div class="text-muted small fw-semibold">Published Live</div>
          <div class="h4 fw-bold text-dark mb-0 font-outfit"><?= $publishedFaqs ?></div>
          <span class="badge bg-success text-white small">Customer Visible</span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 text-warning bg-warning bg-opacity-10 fs-4">
          <i class="bi bi-pencil-square"></i>
        </div>
        <div>
          <div class="text-muted small fw-semibold">Drafts & Inactive</div>
          <div class="h4 fw-bold text-dark mb-0 font-outfit"><?= $draftFaqs ?></div>
          <span class="badge bg-warning bg-opacity-25 text-dark small">Under Review</span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 rounded-4 shadow-sm p-3 bg-white h-100">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 text-primary bg-primary bg-opacity-10 fs-4">
          <i class="bi bi-folder2-open"></i>
        </div>
        <div>
          <div class="text-muted small fw-semibold">Categories</div>
          <div class="h4 fw-bold text-dark mb-0 font-outfit"><?= $totalCategories ?></div>
          <span class="badge bg-primary bg-opacity-10 text-primary small">Domain Taxonomy</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- =======================================================================
     MAIN CONTENT CARD: TOOLBAR + KNOWLEDGE BASE TABLE
     ======================================================================= -->
<div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
  
  <!-- Header: Title & Global Actions -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-3 mb-3">
    <div>
      <div class="d-flex align-items-center gap-2">
        <h4 class="fw-bold font-outfit mb-0 text-dark">Dynamic FAQs & Help Desk</h4>
        <span class="badge bg-light text-muted border rounded-pill small"><?= count($faqs) ?> of <?= $totalFaqs ?> Shown</span>
      </div>
      <p class="text-muted small mb-0 mt-1">Manage customer support topics and FAQs rendered dynamically in the storefront.</p>
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">
      <a href="<?= CUSTOMER_URL ?>/help.php" target="_blank" class="btn btn-outline-dark btn-sm rounded-pill px-3" title="Open storefront customer help center in new tab">
        <i class="bi bi-box-arrow-up-right me-1"></i> View on Storefront
      </a>

      <button type="button" class="btn btn-success btn-sm rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#addFaqModal" style="background:#2E6F40; border-color:#2E6F40;">
        <i class="bi bi-plus-circle me-1"></i> Add New FAQ
      </button>
    </div>
  </div>

  <!-- Search & Filter Controls Toolbar -->
  <div class="row g-2 align-items-center justify-content-between mb-3">
    <div class="col-lg-6 col-md-7">
      <form method="GET" action="<?= ADMIN_URL ?>/faqs.php" class="d-flex gap-2 m-0" id="filterForm">
        <?php if (!empty($categoryFilter)): ?>
          <input type="hidden" name="category" value="<?= e($categoryFilter) ?>">
        <?php endif; ?>

        <div class="input-group input-group-sm">
          <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
          <input type="text" name="q" id="faqSearchInput" class="form-control border-start-0 ps-0" placeholder="Search questions, answers, or categories..." value="<?= e($searchQuery) ?>" autocomplete="off">
        </div>

        <select name="status" class="form-select form-select-sm" style="max-width: 150px;" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <option value="published" <?= ($statusFilter === 'published') ? 'selected' : '' ?>>Published Only</option>
          <option value="draft" <?= ($statusFilter === 'draft') ? 'selected' : '' ?>>Drafts Only</option>
        </select>

        <?php if (!empty($searchQuery) || !empty($statusFilter) || !empty($categoryFilter)): ?>
          <a href="<?= ADMIN_URL ?>/faqs.php" class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Reset all search filters">
            <i class="bi bi-x-circle"></i>
          </a>
        <?php endif; ?>
      </form>
    </div>

    <div class="col-lg-6 col-md-5 text-md-end text-muted small">
      <span class="me-2"><i class="bi bi-info-circle me-1"></i>Questions display in ascending order on storefront.</span>
    </div>
  </div>

  <!-- Category Filter Pills (Horizontally Scrollable) -->
  <div class="d-flex align-items-center gap-2 overflow-x-auto pb-3 mb-2" style="scrollbar-width: thin;">
    <a href="<?= ADMIN_URL ?>/faqs.php<?= !empty($statusFilter) ? '?status=' . urlencode($statusFilter) : '' ?>" 
       class="btn btn-sm rounded-pill text-nowrap <?= empty($categoryFilter) ? 'btn-dark fw-bold' : 'btn-light border text-secondary' ?>">
      All Categories <span class="badge bg-secondary bg-opacity-25 text-dark ms-1"><?= $totalFaqs ?></span>
    </a>
    <?php foreach ($categories as $cat): ?>
      <?php $cCount = $categoryCounts[$cat] ?? 0; ?>
      <a href="<?= ADMIN_URL ?>/faqs.php?category=<?= urlencode($cat) ?><?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?>" 
         class="btn btn-sm rounded-pill text-nowrap <?= ($categoryFilter === $cat) ? 'btn-success fw-bold' : 'btn-light border text-secondary' ?>"
         style="<?= ($categoryFilter === $cat) ? 'background:#2E6F40; border-color:#2E6F40;' : '' ?>">
        <?= e($cat) ?> 
        <span class="badge <?= ($categoryFilter === $cat) ? 'bg-white text-success' : 'bg-light text-muted border' ?> ms-1"><?= $cCount ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Table View -->
  <div class="table-responsive">
    <table class="table table-hover align-middle small mb-0" id="faqsTable">
      <thead class="table-light">
        <tr>
          <th style="width: 90px;" class="text-center">Order</th>
          <th style="width: 160px;">Category</th>
          <th>Question & Formatted Answer</th>
          <th style="width: 120px;" class="text-center">Status</th>
          <th style="width: 160px;" class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($faqs)): ?>
          <tr>
            <td colspan="5" class="text-center py-5">
              <div class="py-4">
                <i class="bi bi-search text-muted display-6 d-block mb-3 opacity-50"></i>
                <h6 class="fw-bold text-dark mb-1">No FAQ records found</h6>
                <p class="text-muted small mb-3">No questions match your filter criteria or search keyword.</p>
                <div class="d-flex justify-content-center gap-2">
                  <?php if (!empty($searchQuery) || !empty($categoryFilter) || !empty($statusFilter)): ?>
                    <a href="<?= ADMIN_URL ?>/faqs.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                      <i class="bi bi-arrow-counterclockwise me-1"></i> Clear Filters
                    </a>
                  <?php endif; ?>
                  <button type="button" class="btn btn-sm btn-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addFaqModal" style="background:#2E6F40; border-color:#2E6F40;">
                    <i class="bi bi-plus-circle me-1"></i> Create New FAQ
                  </button>
                </div>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($faqs as $faq): ?>
            <tr class="faq-table-row" data-search-text="<?= strtolower(e($faq['question'] . ' ' . strip_tags($faq['answer']) . ' ' . $faq['category'])) ?>">
              <!-- Order Column with Quick Nudge Buttons -->
              <td class="text-center">
                <div class="d-flex align-items-center justify-content-center gap-1">
                  <span class="badge bg-light text-dark border font-monospace px-2 py-1"><?= (int)$faq['display_order'] ?></span>
                  <div class="d-flex flex-column">
                    <form method="POST" action="<?= ADMIN_URL ?>/faqs.php" class="m-0">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="reorder_faq">
                      <input type="hidden" name="faq_id" value="<?= (int)$faq['faq_id'] ?>">
                      <input type="hidden" name="direction" value="up">
                      <button type="submit" class="btn btn-link text-muted p-0 lh-1" title="Move Up" style="font-size: 0.65rem;">
                        <i class="bi bi-chevron-up"></i>
                      </button>
                    </form>
                    <form method="POST" action="<?= ADMIN_URL ?>/faqs.php" class="m-0">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="reorder_faq">
                      <input type="hidden" name="faq_id" value="<?= (int)$faq['faq_id'] ?>">
                      <input type="hidden" name="direction" value="down">
                      <button type="submit" class="btn btn-link text-muted p-0 lh-1" title="Move Down" style="font-size: 0.65rem;">
                        <i class="bi bi-chevron-down"></i>
                      </button>
                    </form>
                  </div>
                </div>
              </td>

              <!-- Category Badge -->
              <td>
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill fw-semibold border border-success border-opacity-25 px-2 py-1">
                  <?= e($faq['category']) ?>
                </span>
              </td>

              <!-- Question & Expandable Answer -->
              <td>
                <div class="fw-bold text-dark mb-1">
                  <?= e($faq['question']) ?>
                </div>
                
                <!-- Quick Answer Preview & Inline Accordion Collapse -->
                <div class="text-muted small mb-1">
                  <?= strip_tags($faq['answer']) ?>
                </div>

                <div class="mt-1">
                  <a class="text-decoration-none text-success small fw-semibold" data-bs-toggle="collapse" href="#fullAnswer<?= (int)$faq['faq_id'] ?>" role="button" aria-expanded="false">
                    <i class="bi bi-eye me-1"></i> Preview Formatted Answer
                  </a>
                </div>

                <div class="collapse mt-2" id="fullAnswer<?= (int)$faq['faq_id'] ?>">
                  <div class="card card-body bg-light border rounded-3 p-3 small text-dark">
                    <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                      <span class="badge bg-secondary bg-opacity-10 text-secondary font-monospace" style="font-size: 0.7rem;">STOREFRONT RENDER PREVIEW</span>
                      <span class="text-muted font-monospace" style="font-size: 0.7rem;">ID #<?= (int)$faq['faq_id'] ?></span>
                    </div>
                    <div>
                      <?= $faq['answer'] ?>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Status Toggle Button -->
              <td class="text-center">
                <form method="POST" action="<?= ADMIN_URL ?>/faqs.php" class="m-0">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="faq_id" value="<?= (int)$faq['faq_id'] ?>">
                  <input type="hidden" name="is_active" value="<?= $faq['is_active'] ? '0' : '1' ?>">
                  <button type="submit" class="btn btn-sm rounded-pill px-2 py-1 border-0 shadow-xs <?= $faq['is_active'] ? 'bg-success bg-opacity-10 text-success fw-bold' : 'bg-secondary bg-opacity-10 text-secondary fw-semibold' ?>" title="Click to toggle visibility status">
                    <i class="bi <?= $faq['is_active'] ? 'bi-check-circle-fill text-success' : 'bi-eye-slash-fill text-secondary' ?> me-1"></i>
                    <?= $faq['is_active'] ? 'Published' : 'Draft' ?>
                  </button>
                </form>
              </td>

              <!-- Action Buttons -->
              <td class="text-end">
                <div class="d-flex align-items-center justify-content-end gap-1">
                  <!-- Edit Modal Trigger -->
                  <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-1 btn-edit-faq"
                          data-bs-toggle="modal" 
                          data-bs-target="#editFaqModal"
                          data-id="<?= (int)$faq['faq_id'] ?>"
                          data-question="<?= e($faq['question']) ?>"
                          data-answer="<?= htmlspecialchars($faq['answer'], ENT_QUOTES, 'UTF-8') ?>"
                          data-category="<?= e($faq['category']) ?>"
                          data-order="<?= (int)$faq['display_order'] ?>"
                          data-active="<?= (int)$faq['is_active'] ?>"
                          title="Edit FAQ Topic">
                    <i class="bi bi-pencil-square"></i> Edit
                  </button>

                  <!-- Duplicate as Draft -->
                  <form method="POST" action="<?= ADMIN_URL ?>/faqs.php" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="duplicate_faq">
                    <input type="hidden" name="faq_id" value="<?= (int)$faq['faq_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-1" title="Duplicate as new Draft">
                      <i class="bi bi-copy"></i>
                    </button>
                  </form>

                  <!-- Delete Modal Trigger -->
                  <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-1 btn-delete-faq"
                          data-bs-toggle="modal"
                          data-bs-target="#deleteFaqModal"
                          data-id="<?= (int)$faq['faq_id'] ?>"
                          data-question="<?= e($faq['question']) ?>"
                          title="Delete FAQ Topic">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

<!-- =======================================================================
     ADD FAQ MODAL
     ======================================================================= -->
<div class="modal fade" id="addFaqModal" tabindex="-1" aria-labelledby="addFaqModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 border-0 shadow-lg text-start">
      <div class="modal-header border-bottom">
        <div>
          <h5 class="modal-title fw-bold" id="addFaqModalLabel">Add New FAQ Topic</h5>
          <p class="text-muted small mb-0">Publish a new self-service answer to the customer help center.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?= ADMIN_URL ?>/faqs.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_faq">

        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label small fw-bold">Topic Category *</label>
              <div class="input-group">
                <input type="text" name="category" list="categoryOptionsList" class="form-control rounded-3" placeholder="Select or type new category..." value="General" required>
                <datalist id="categoryOptionsList">
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>"></option>
                  <?php endforeach; ?>
                </datalist>
              </div>
              <div class="form-text small">Select from standard categories or type a custom topic name.</div>
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-bold">Display Order</label>
              <input type="number" name="display_order" class="form-control rounded-3" value="<?= $maxOrder + 10 ?>" min="0">
              <div class="form-text small">Lower numbers display first.</div>
            </div>

            <div class="col-md-2 d-flex flex-column justify-content-center pt-2">
              <label class="form-label small fw-bold mb-1">Status</label>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="addIsActive" checked>
                <label class="form-check-label small fw-semibold" for="addIsActive">Published</label>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label small fw-bold">Customer Question *</label>
              <input type="text" name="question" class="form-control rounded-3" placeholder="e.g. What is the cancellation policy on food orders?" required>
            </div>

            <div class="col-12">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <label class="form-label small fw-bold mb-0">Answer Body *</label>
                <!-- Formatting Helper Buttons -->
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-light btn-sm border" onclick="insertFormatTag('addAnswerTextarea', '<b>', '</b>')" title="Bold"><strong>B</strong></button>
                  <button type="button" class="btn btn-light btn-sm border" onclick="insertFormatTag('addAnswerTextarea', '<i>', '</i>')" title="Italic"><em>I</em></button>
                  <button type="button" class="btn btn-light btn-sm border" onclick="insertFormatTag('addAnswerTextarea', '<code>', '</code>')" title="Code/Reference">&lt;/&gt;</button>
                  <button type="button" class="btn btn-light btn-sm border" onclick="insertFormatTag('addAnswerTextarea', '<ul>\n  <li>', '</li>\n</ul>')" title="Bullet List"><i class="bi bi-list-ul"></i></button>
                  <button type="button" class="btn btn-light btn-sm border" onclick="insertFormatTag('addAnswerTextarea', '<br>', '')" title="Line Break">&para;</button>
                </div>
              </div>
              
              <!-- Tabbed Write / Preview -->
              <ul class="nav nav-tabs nav-tabs-sm border-bottom-0 mb-2" id="addAnswerTabs" role="tablist">
                <li class="nav-item">
                  <button class="nav-link active small py-1 px-3" id="addWriteTab" data-bs-toggle="tab" data-bs-target="#addWritePane" type="button">Write</button>
                </li>
                <li class="nav-item">
                  <button class="nav-link small py-1 px-3" id="addPreviewTab" data-bs-toggle="tab" data-bs-target="#addPreviewPane" type="button" onclick="renderTabPreview('addAnswerTextarea', 'addPreviewBox')">
                    <i class="bi bi-eye me-1"></i> Live Storefront Preview
                  </button>
                </li>
              </ul>

              <div class="tab-content border rounded-3 p-2 bg-light">
                <div class="tab-pane fade show active" id="addWritePane">
                  <textarea name="answer" id="addAnswerTextarea" class="form-control border-0 bg-transparent rounded-3" rows="5" placeholder="Detailed explanation for customers... You can use standard line breaks or bold tags." required></textarea>
                </div>
                <div class="tab-pane fade p-3 bg-white rounded-2" id="addPreviewPane">
                  <div class="text-muted small fst-italic mb-2 border-bottom pb-1">This is how your answer appears inside the customer accordion:</div>
                  <div id="addPreviewBox" class="text-muted small">Type in the Write tab to see preview...</div>
                </div>
              </div>
              <div class="form-text small">Plain text line breaks are automatically converted to paragraphs. Safe HTML formatting tags (b, i, u, code, ul, li) are fully supported.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
            <i class="bi bi-check-lg me-1"></i> Publish FAQ
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =======================================================================
     EDIT FAQ MODAL (Single Reusable Dynamic Modal)
     ======================================================================= -->
<div class="modal fade" id="editFaqModal" tabindex="-1" aria-labelledby="editFaqModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 border-0 shadow-lg text-start">
      <div class="modal-header border-bottom">
        <div>
          <h5 class="modal-title fw-bold" id="editFaqModalLabel">Edit FAQ Topic</h5>
          <p class="text-muted small mb-0">Update customer answer, category classification, or storefront order.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?= ADMIN_URL ?>/faqs.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit_faq">
        <input type="hidden" name="faq_id" id="editFaqId" value="">

        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label small fw-bold">Topic Category *</label>
              <input type="text" name="category" id="editCategory" list="categoryOptionsList" class="form-control rounded-3" required>
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-bold">Display Order</label>
              <input type="number" name="display_order" id="editDisplayOrder" class="form-control rounded-3" min="0">
            </div>

            <div class="col-md-2 d-flex flex-column justify-content-center pt-2">
              <label class="form-label small fw-bold mb-1">Status</label>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editIsActive">
                <label class="form-check-label small fw-semibold" for="editIsActive">Published</label>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label small fw-bold">Customer Question *</label>
              <input type="text" name="question" id="editQuestion" class="form-control rounded-3" required>
            </div>

            <div class="col-12">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <label class="form-label small fw-bold mb-0">Answer Body *</label>
                <!-- Formatting Helper Buttons -->
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-light btn-sm border" onclick="insertFormatTag('editAnswerTextarea', '<b>', '</b>')" title="Bold"><strong>B</strong></button>
                  <button type="button" class="btn btn-light btn-sm border" onclick="insertFormatTag('editAnswerTextarea', '<i>', '</i>')" title="Italic"><em>I</em></button>
                  <button type="button" class="btn btn-light btn-sm border" onclick="insertFormatTag('editAnswerTextarea', '<code>', '</code>')" title="Code/Reference">&lt;/&gt;</button>
                  <button type="button" class="btn btn-light btn-sm border" onclick="insertFormatTag('editAnswerTextarea', '<ul>\n  <li>', '</li>\n</ul>')" title="Bullet List"><i class="bi bi-list-ul"></i></button>
                  <button type="button" class="btn btn-light btn-sm border" onclick="insertFormatTag('editAnswerTextarea', '<br>', '')" title="Line Break">&para;</button>
                </div>
              </div>
              
              <!-- Tabbed Write / Preview -->
              <ul class="nav nav-tabs nav-tabs-sm border-bottom-0 mb-2" id="editAnswerTabs" role="tablist">
                <li class="nav-item">
                  <button class="nav-link active small py-1 px-3" id="editWriteTab" data-bs-toggle="tab" data-bs-target="#editWritePane" type="button">Write</button>
                </li>
                <li class="nav-item">
                  <button class="nav-link small py-1 px-3" id="editPreviewTab" data-bs-toggle="tab" data-bs-target="#editPreviewPane" type="button" onclick="renderTabPreview('editAnswerTextarea', 'editPreviewBox')">
                    <i class="bi bi-eye me-1"></i> Live Storefront Preview
                  </button>
                </li>
              </ul>

              <div class="tab-content border rounded-3 p-2 bg-light">
                <div class="tab-pane fade show active" id="editWritePane">
                  <textarea name="answer" id="editAnswerTextarea" class="form-control border-0 bg-transparent rounded-3" rows="5" required></textarea>
                </div>
                <div class="tab-pane fade p-3 bg-white rounded-2" id="editPreviewPane">
                  <div class="text-muted small fst-italic mb-2 border-bottom pb-1">This is how your answer appears inside the customer accordion:</div>
                  <div id="editPreviewBox" class="text-muted small">Loading preview...</div>
                </div>
              </div>
              <div class="form-text small">Changes will reflect instantly on the storefront help center accordion upon saving.</div>
            </div>
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

<!-- =======================================================================
     DELETE CONFIRMATION MODAL
     ======================================================================= -->
<div class="modal fade" id="deleteFaqModal" tabindex="-1" aria-labelledby="deleteFaqModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg text-start">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold text-danger" id="deleteFaqModalLabel">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete FAQ Topic
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?= ADMIN_URL ?>/faqs.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_faq">
        <input type="hidden" name="faq_id" id="deleteFaqId" value="">

        <div class="modal-body p-4">
          <p class="mb-2">Are you sure you want to permanently delete this FAQ topic?</p>
          <div class="p-3 bg-light rounded-3 border mb-3">
            <span class="text-muted small fw-bold d-block mb-1">Question:</span>
            <div id="deleteFaqQuestionText" class="fw-bold text-dark"></div>
          </div>
          <p class="text-muted small mb-0">This topic will immediately be removed from the storefront help center. This action cannot be undone.</p>
        </div>
        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">
            <i class="bi bi-trash me-1"></i> Confirm Delete
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Interactive Client-side Scripting -->
<script>
  // Format tag inserter helper
  function insertFormatTag(textareaId, openTag, closeTag) {
    const textarea = document.getElementById(textareaId);
    if (!textarea) return;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selected = textarea.value.substring(start, end);
    const replacement = openTag + selected + closeTag;
    textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    textarea.focus();
    textarea.setSelectionRange(start + openTag.length, start + openTag.length + selected.length);
  }

  // Live render preview
  function renderTabPreview(textareaId, previewBoxId) {
    const text = document.getElementById(textareaId)?.value || '';
    const box = document.getElementById(previewBoxId);
    if (!box) return;
    if (!text.trim()) {
      box.innerHTML = '<span class="text-muted fst-italic">No answer content written yet.</span>';
      return;
    }
    // Convert newlines if no html tags
    const hasHtml = /<(?:p|br|ul|ol|li)\b[^>]*>/i.test(text);
    box.innerHTML = hasHtml ? text : text.replace(/\n/g, '<br>');
  }

  // Populate dynamic Edit FAQ modal
  document.addEventListener('DOMContentLoaded', function () {
    const editModal = document.getElementById('editFaqModal');
    if (editModal) {
      editModal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        if (!btn) return;

        document.getElementById('editFaqId').value = btn.getAttribute('data-id') || '';
        document.getElementById('editQuestion').value = btn.getAttribute('data-question') || '';
        document.getElementById('editAnswerTextarea').value = btn.getAttribute('data-answer') || '';
        document.getElementById('editCategory').value = btn.getAttribute('data-category') || 'General';
        document.getElementById('editDisplayOrder').value = btn.getAttribute('data-order') || '0';
        document.getElementById('editIsActive').checked = (btn.getAttribute('data-active') === '1');

        // Reset to write tab
        const writeTab = document.getElementById('editWriteTab');
        if (writeTab) {
          bootstrap.Tab.getOrCreateInstance(writeTab).show();
        }
      });
    }

    // Populate Delete modal
    const deleteModal = document.getElementById('deleteFaqModal');
    if (deleteModal) {
      deleteModal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        if (!btn) return;
        document.getElementById('deleteFaqId').value = btn.getAttribute('data-id') || '';
        document.getElementById('deleteFaqQuestionText').textContent = btn.getAttribute('data-question') || '';
      });
    }

    // Instant client-side search filtering
    const searchInput = document.getElementById('faqSearchInput');
    const tableRows = document.querySelectorAll('.faq-table-row');
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        tableRows.forEach(row => {
          const text = row.getAttribute('data-search-text') || '';
          row.style.display = text.includes(q) ? '' : 'none';
        });
      });
    }
  });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>