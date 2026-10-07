<?php
/**
 * Dynamic FAQ & Help Desk Knowledge Base Management
 * Zippy - Online Food Ordering Platform
 * Student: Pooja JP | Course: MCSP-232
 */

$pageTitle = 'Dynamic FAQs & Help Desk';
$activePage = 'faqs';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
$currentUser = current_user();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
    } else {
        $action = $_POST['action'];

        // 1. Add New FAQ
        if ($action === 'add_faq') {
            $question = clean_input($_POST['question'] ?? '');
            $answer = clean_input($_POST['answer'] ?? '');
            $category = clean_input($_POST['category'] ?? 'General');
            $displayOrder = (int)($_POST['display_order'] ?? 0);

            if (!empty($question) && !empty($answer)) {
                db_query("
                    INSERT INTO `faqs` (`question`, `answer`, `category`, `display_order`, `is_active`)
                    VALUES (:q, :a, :c, :o, 1)
                ", [
                    ':q' => $question,
                    ':a' => $answer,
                    ':c' => $category,
                    ':o' => $displayOrder
                ]);
                set_flash('success', 'New FAQ topic published successfully.');
                header("Location: " . ADMIN_URL . "/faqs.php");
                exit;
            } else {
                set_flash('danger', 'Please provide both question and answer.');
            }
        }

        // 2. Edit Existing FAQ
        if ($action === 'edit_faq') {
            $faqId = (int)($_POST['faq_id'] ?? 0);
            $question = clean_input($_POST['question'] ?? '');
            $answer = clean_input($_POST['answer'] ?? '');
            $category = clean_input($_POST['category'] ?? 'General');
            $displayOrder = (int)($_POST['display_order'] ?? 0);

            if ($faqId > 0 && !empty($question) && !empty($answer)) {
                db_query("
                    UPDATE `faqs`
                    SET `question` = :q, `answer` = :a, `category` = :c, `display_order` = :o
                    WHERE `faq_id` = :id
                ", [
                    ':q' => $question,
                    ':a' => $answer,
                    ':c' => $category,
                    ':o' => $displayOrder,
                    ':id' => $faqId
                ]);
                set_flash('success', 'FAQ topic updated successfully.');
                header("Location: " . ADMIN_URL . "/faqs.php");
                exit;
            }
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
                set_flash('success', 'FAQ visibility status updated.');
                header("Location: " . ADMIN_URL . "/faqs.php");
                exit;
            }
        }

        // 4. Delete FAQ
        if ($action === 'delete_faq') {
            $faqId = (int)($_POST['faq_id'] ?? 0);
            if ($faqId > 0) {
                db_query("DELETE FROM `faqs` WHERE `faq_id` = :id", [':id' => $faqId]);
                set_flash('success', 'FAQ topic permanently deleted.');
                header("Location: " . ADMIN_URL . "/faqs.php");
                exit;
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';

// Fetch FAQs
$categoryFilter = clean_input($_GET['category'] ?? '');
$where = "1=1";
$params = [];
if (!empty($categoryFilter)) {
    $where .= " AND `category` = :cat";
    $params[':cat'] = $categoryFilter;
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

// Available categories
$categories = ['General', 'Orders & Cancellation', 'Delivery & Tracking', 'Billing & Payments', 'Account & Privacy'];
?>

<div class="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
  
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-3 mb-4">
    <div>
      <h4 class="fw-bold font-outfit mb-1 text-dark">Dynamic FAQs & Customer Help Desk</h4>
      <p class="text-muted small mb-0">Manage customer support topics and FAQs rendered dynamically in the storefront.</p>
    </div>

    <div class="d-flex align-items-center gap-2">
      <a href="<?= ADMIN_URL ?>/faqs.php" class="btn btn-sm rounded-pill <?= empty($categoryFilter) ? 'btn-dark' : 'btn-light border' ?>">All</a>
      <?php foreach ($categories as $cat): ?>
        <a href="<?= ADMIN_URL ?>/faqs.php?category=<?= urlencode($cat) ?>" class="btn btn-sm rounded-pill <?= ($categoryFilter === $cat) ? 'btn-success' : 'btn-light border' ?>">
          <?= e($cat) ?>
        </a>
      <?php endforeach; ?>

      <button type="button" class="btn btn-success btn-sm rounded-pill px-3 fw-bold ms-2" data-bs-toggle="modal" data-bs-target="#addFaqModal" style="background:#2E6F40; border-color:#2E6F40;">
        <i class="bi bi-plus-circle me-1"></i> Add New FAQ
      </button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle small mb-0">
      <thead class="table-light">
        <tr>
          <th style="width: 60px;">Order</th>
          <th style="width: 140px;">Category</th>
          <th>Question & Answer</th>
          <th style="width: 110px;">Status</th>
          <th style="width: 140px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($faqs)): ?>
          <tr>
            <td colspan="5" class="text-center py-4 text-muted">
              No FAQ records found. Click <strong>Add New FAQ</strong> to create one.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($faqs as $faq): ?>
            <tr>
              <td class="fw-bold font-monospace text-muted"><?= (int)$faq['display_order'] ?></td>
              <td>
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill fw-semibold">
                  <?= e($faq['category']) ?>
                </span>
              </td>
              <td>
                <div class="fw-bold text-dark mb-1"><?= e($faq['question']) ?></div>
                <div class="text-muted small text-truncate" style="max-width: 480px;"><?= strip_tags($faq['answer']) ?></div>
              </td>
              <td>
                <form method="POST" action="<?= ADMIN_URL ?>/faqs.php" class="m-0">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="faq_id" value="<?= (int)$faq['faq_id'] ?>">
                  <input type="hidden" name="is_active" value="<?= $faq['is_active'] ? '0' : '1' ?>">
                  <button type="submit" class="btn btn-sm badge <?= $faq['is_active'] ? 'bg-success' : 'bg-secondary' ?> rounded-pill border-0 px-2 py-1 cursor-pointer">
                    <?= $faq['is_active'] ? 'Published' : 'Draft' ?>
                  </button>
                </form>
              </td>
              <td>
                <div class="d-flex align-items-center gap-1">
                  <!-- Edit Modal Trigger -->
                  <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-0" data-bs-toggle="modal" data-bs-target="#editModal<?= (int)$faq['faq_id'] ?>">
                    <i class="bi bi-pencil-square"></i> Edit
                  </button>

                  <!-- Delete -->
                  <form method="POST" action="<?= ADMIN_URL ?>/faqs.php" class="m-0" onsubmit="return confirm('Delete this FAQ permanently?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_faq">
                    <input type="hidden" name="faq_id" value="<?= (int)$faq['faq_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-0">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>

                <!-- Edit FAQ Modal -->
                <div class="modal fade" id="editModal<?= (int)$faq['faq_id'] ?>" tabindex="-1">
                  <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content rounded-4 border-0 shadow-lg text-start">
                      <div class="modal-header border-bottom">
                        <h5 class="modal-title fw-bold">Edit FAQ Topic</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <form method="POST" action="<?= ADMIN_URL ?>/faqs.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="edit_faq">
                        <input type="hidden" name="faq_id" value="<?= (int)$faq['faq_id'] ?>">

                        <div class="modal-body p-4">
                          <div class="row g-3">
                            <div class="col-md-8">
                              <label class="form-label small fw-bold">Category</label>
                              <select name="category" class="form-select rounded-3">
                                <?php foreach ($categories as $cat): ?>
                                  <option value="<?= e($cat) ?>" <?= ($faq['category'] === $cat) ? 'selected' : '' ?>><?= e($cat) ?></option>
                                <?php endforeach; ?>
                              </select>
                            </div>
                            <div class="col-md-4">
                              <label class="form-label small fw-bold">Display Order</label>
                              <input type="number" name="display_order" class="form-control rounded-3" value="<?= (int)$faq['display_order'] ?>">
                            </div>
                            <div class="col-12">
                              <label class="form-label small fw-bold">Question *</label>
                              <input type="text" name="question" class="form-control rounded-3" value="<?= e($faq['question']) ?>" required>
                            </div>
                            <div class="col-12">
                              <label class="form-label small fw-bold">Answer (HTML allowed) *</label>
                              <textarea name="answer" class="form-control rounded-3" rows="4" required><?= e($faq['answer']) ?></textarea>
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

              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

<!-- Add FAQ Modal -->
<div class="modal fade" id="addFaqModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 border-0 shadow-lg text-start">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold">Add New FAQ Topic</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="<?= ADMIN_URL ?>/faqs.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_faq">

        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label small fw-bold">Category</label>
              <select name="category" class="form-select rounded-3">
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold">Display Order</label>
              <input type="number" name="display_order" class="form-control rounded-3" value="10">
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold">Question *</label>
              <input type="text" name="question" class="form-control rounded-3" placeholder="e.g. How does contactless delivery work?" required>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold">Answer *</label>
              <textarea name="answer" class="form-control rounded-3" rows="4" placeholder="Detailed explanation..." required></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background:#2E6F40; border-color:#2E6F40;">
            Publish FAQ
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
