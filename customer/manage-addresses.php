<?php
/**
 * Customer Address Management
 * MCSP-232: E-Commerce Platform for Food Order
 */
$pageTitle = 'Manage Delivery Addresses - Zippy';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/models/Address.php';

$userId = $_SESSION['user']['user_id'] ?? 1;

// Handle add address POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_address') {
    $type      = $_POST['address_type'] ?? 'HOME';
    $title     = trim($_POST['title'] ?? 'Home');
    $line      = trim($_POST['address_line'] ?? '');
    $landmark  = trim($_POST['landmark'] ?? '');
    $pincode   = trim($_POST['pincode'] ?? '');
    $isDefault = isset($_POST['is_default']);

    if (!empty($line) && !empty($pincode)) {
        Address::add($userId, $type, $title, $line, $landmark, $pincode, $isDefault);
        setFlash('success', 'New address added successfully!');
    } else {
        setFlash('danger', 'Address line and pincode are required.');
    }
}

$addresses = Address::getAllByUserId($userId);
?>

  <main class="container-xl py-4" style="max-width: 860px;">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="fw-bold text-pine mb-1">Manage Delivery Addresses</h2>
        <p class="text-muted small mb-0">Saved locations mapped to <code>user_addresses</code> relational table (3NF).</p>
      </div>
      <button class="btn btn-sm btn-success rounded-pill px-4 fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#addAddressModal" style="background-color: var(--lush-forest);">
        + Add New Address
      </button>
    </div>

    <div class="row g-4 mb-4">
      <?php foreach ($addresses as $addr): ?>
        <div class="col-md-6">
          <div class="p-4 bg-white rounded-4 border shadow-xs h-100 d-flex flex-column justify-content-between position-relative <?= $addr['is_default'] ? 'border-success' : '' ?>">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge bg-success bg-opacity-10 text-success fw-bold">
                  <i class="bi bi-<?= strtolower($addr['address_type']) === 'home' ? 'house-door' : 'briefcase' ?> me-1"></i>
                  <?= sanitize($addr['address_type']) ?>
                </span>
                <?php if ($addr['is_default']): ?>
                  <span class="badge bg-success text-white small">DEFAULT DESTINATION</span>
                <?php endif; ?>
              </div>
              <h5 class="fw-bold text-pine mb-1"><?= sanitize($addr['title']) ?></h5>
              <p class="small text-muted mb-2"><?= sanitize($addr['address_line']) ?></p>
              <?php if (!empty($addr['landmark'])): ?>
                <div class="small text-muted mb-1"><i class="bi bi-geo-alt text-success me-1"></i> Landmark: <?= sanitize($addr['landmark']) ?></div>
              <?php endif; ?>
              <div class="small text-muted"><strong>Pincode:</strong> <?= sanitize($addr['pincode']) ?></div>
            </div>

            <div class="pt-3 border-top mt-3 d-flex justify-content-between align-items-center">
              <span class="badge bg-light text-muted border small">25 Mins Delivery Zone</span>
              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="alert('Address editing modal opened for #<?= $addr['address_id'] ?>')">
                  <i class="bi bi-pencil me-1"></i> Edit
                </button>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Add Address Modal -->
    <div class="modal fade" id="addAddressModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold text-pine">Add New Delivery Address</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-4">
            <form method="POST" action="manage-addresses.php">
              <input type="hidden" name="action" value="add_address">

              <div class="mb-3">
                <label class="form-label small fw-bold text-pine">Address Tag / Type</label>
                <div class="d-flex gap-2">
                  <input type="radio" class="btn-check" name="address_type" id="typeHome" value="HOME" checked>
                  <label class="btn btn-outline-success btn-sm rounded-pill px-3" for="typeHome">Home</label>

                  <input type="radio" class="btn-check" name="address_type" id="typeWork" value="WORK">
                  <label class="btn btn-outline-success btn-sm rounded-pill px-3" for="typeWork">Work / Office</label>

                  <input type="radio" class="btn-check" name="address_type" id="typeOther" value="OTHER">
                  <label class="btn btn-outline-success btn-sm rounded-pill px-3" for="typeOther">Other</label>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label small fw-bold text-pine">Address Nickname</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. My Apartment, Studio, Office" required>
              </div>

              <div class="mb-3">
                <label class="form-label small fw-bold text-pine">Complete Address Line</label>
                <textarea name="address_line" class="form-control" rows="2" placeholder="House/Flat No., Building Name, Street, Area..." required></textarea>
              </div>

              <div class="row g-2 mb-3">
                <div class="col-6">
                  <label class="form-label small fw-bold text-pine">Landmark</label>
                  <input type="text" name="landmark" class="form-control" placeholder="Nearby Metro, Mall...">
                </div>
                <div class="col-6">
                  <label class="form-label small fw-bold text-pine">Postal Pincode</label>
                  <input type="text" name="pincode" class="form-control" placeholder="e.g. 560038" required maxlength="6">
                </div>
              </div>

              <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="is_default" id="setDefaultCheck">
                <label class="form-check-label small fw-bold text-pine" for="setDefaultCheck">
                  Make this my default delivery address
                </label>
              </div>

              <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold py-2 shadow-xs" style="background-color: var(--lush-forest);">
                Save Address
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
