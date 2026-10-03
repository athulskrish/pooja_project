<?php
/**
 * Customer Saved Addresses Management (3NF Relational Model)
 * Zippy - Online Food Ordering Platform
 */

$pageTitle = 'Manage Delivery Addresses';
$activePage = 'profile';

// Bootstrap core libraries before HTML output (prevents "headers already sent")
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
$currentUser = current_user();
require_login();

$userId = $currentUser['id'];

// Handle Add Address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
    } elseif ($_POST['action'] === 'add_address') {
        $type = clean_input($_POST['address_type'] ?? 'HOME');
        $line = clean_input($_POST['address_line'] ?? '');
        $landmark = clean_input($_POST['landmark'] ?? '');
        $pincode = clean_input($_POST['pincode'] ?? '560038');
        $isDefault = !empty($_POST['is_default']) ? 1 : 0;

        if (!empty($line)) {
            if ($isDefault) {
                db_query("UPDATE `user_addresses` SET `is_default` = 0 WHERE `user_id` = :uid", [':uid' => $userId]);
            }
            db_query("
                INSERT INTO `user_addresses` (`user_id`, `address_type`, `address_line`, `landmark`, `pincode`, `is_default`) 
                VALUES (:uid, :type, :line, :lm, :pin, :def)
            ", [
                ':uid' => $userId,
                ':type' => $type,
                ':line' => $line,
                ':lm' => $landmark,
                ':pin' => $pincode,
                ':def' => $isDefault
            ]);
            set_flash('success', 'New delivery address added successfully.');
            header("Location: " . CUSTOMER_URL . "/manage-addresses.php");
            exit;
        } else {
            set_flash('danger', 'Please enter a complete address line.');
        }
    } elseif ($_POST['action'] === 'set_default') {
        $addressId = (int)$_POST['address_id'];
        db_query("UPDATE `user_addresses` SET `is_default` = 0 WHERE `user_id` = :uid", [':uid' => $userId]);
        db_query("UPDATE `user_addresses` SET `is_default` = 1 WHERE `address_id` = :aid AND `user_id` = :uid", [
            ':aid' => $addressId,
            ':uid' => $userId
        ]);
        set_flash('success', 'Default delivery destination updated.');
        header("Location: " . CUSTOMER_URL . "/manage-addresses.php");
        exit;
    } elseif ($_POST['action'] === 'delete_address') {
        $addressId = (int)$_POST['address_id'];
        db_query("DELETE FROM `user_addresses` WHERE `address_id` = :aid AND `user_id` = :uid", [
            ':aid' => $addressId,
            ':uid' => $userId
        ]);
        set_flash('success', 'Address removed from your address book.');
        header("Location: " . CUSTOMER_URL . "/manage-addresses.php");
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';

// Fetch all addresses
$addresses = db_fetch_all("
    SELECT * FROM `user_addresses` 
    WHERE `user_id` = :uid 
    ORDER BY `is_default` DESC, `address_id` DESC
", [':uid' => $userId]);
?>

<main class="py-4">
  <div class="container-xl">
    
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h2 class="fw-bold mb-0 font-outfit text-dark"><i class="bi bi-geo-alt-fill text-success me-2"></i>Saved Delivery Addresses</h2>
        <p class="text-muted small mb-0">Addresses stored in 3NF normalized <code>user_addresses</code> table</p>
      </div>
      <button type="button" class="btn btn-success btn-sm rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#addAddressModal" style="background:#2E6F40; border-color:#2E6F40;">
        <i class="bi bi-plus-lg me-1"></i> Add New Address
      </button>
    </div>

    <div class="row g-4">
      <?php if (empty($addresses)): ?>
        <div class="col-12">
          <div class="card border-0 rounded-4 shadow-sm p-5 text-center bg-white">
            <i class="bi bi-geo text-muted opacity-50 mb-3" style="font-size: 3rem;"></i>
            <h5 class="fw-bold text-dark">No saved addresses found</h5>
            <p class="text-muted small mb-3">Add your home or office address for faster 1-click checkout.</p>
            <div>
              <button class="btn btn-success rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#addAddressModal" style="background:#2E6F40; border-color:#2E6F40;">
                Add First Address
              </button>
            </div>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($addresses as $addr): ?>
          <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 rounded-4 shadow-sm p-4 bg-white d-flex flex-column justify-content-between <?= $addr['is_default'] ? 'border-start border-success border-4' : '' ?>">
              <div>
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <span class="badge bg-dark px-2 py-1 small"><?= e($addr['address_type']) ?></span>
                  <?php if ($addr['is_default']): ?>
                    <span class="badge bg-mint text-dark fw-bold px-2 py-1"><i class="bi bi-star-fill text-warning me-1"></i>DEFAULT</span>
                  <?php endif; ?>
                </div>

                <div class="fw-semibold text-dark mb-1 fs-6"><?= e($addr['address_line']) ?></div>
                <?php if (!empty($addr['landmark'])): ?>
                  <div class="small text-muted mb-1"><i class="bi bi-pin-map me-1 text-danger"></i>Near <?= e($addr['landmark']) ?></div>
                <?php endif; ?>
                <div class="small text-muted">PIN: <?= e($addr['pincode']) ?> | Bangalore</div>
              </div>

              <div class="border-top pt-3 mt-3 d-flex align-items-center justify-content-between">
                <?php if (!$addr['is_default']): ?>
                  <form method="POST" action="<?= CUSTOMER_URL ?>/manage-addresses.php" class="m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="set_default">
                    <input type="hidden" name="address_id" value="<?= (int)$addr['address_id'] ?>">
                    <button type="submit" class="btn btn-link text-success btn-sm p-0 fw-bold text-decoration-none">
                      Set as Default
                    </button>
                  </form>
                <?php else: ?>
                  <span class="small text-muted"><i class="bi bi-check-circle-fill text-success me-1"></i>Current Default</span>
                <?php endif; ?>

                <form method="POST" action="<?= CUSTOMER_URL ?>/manage-addresses.php" class="m-0" onsubmit="return confirm('Are you sure you want to delete this address?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_address">
                  <input type="hidden" name="address_id" value="<?= (int)$addr['address_id'] ?>">
                  <button type="submit" class="btn btn-link text-danger btn-sm p-0 text-decoration-none">
                    <i class="bi bi-trash3"></i> Delete
                  </button>
                </form>
              </div>

            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</main>

<!-- Add Address Modal -->
<div class="modal fade" id="addAddressModal" tabindex="-1" aria-labelledby="addAddressModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold font-outfit" id="addAddressModalLabel"><i class="bi bi-geo-alt-fill text-success me-2"></i>Add Delivery Destination</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <form method="POST" action="<?= CUSTOMER_URL ?>/manage-addresses.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_address">
        
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label small fw-bold">Address Type</label>
            <div class="btn-group w-100" role="group">
              <input type="radio" class="btn-check" name="address_type" id="typeHome" value="HOME" checked>
              <label class="btn btn-outline-success" for="typeHome"><i class="bi bi-house-door me-1"></i> HOME</label>

              <input type="radio" class="btn-check" name="address_type" id="typeWork" value="WORK">
              <label class="btn btn-outline-success" for="typeWork"><i class="bi bi-briefcase me-1"></i> WORK</label>

              <input type="radio" class="btn-check" name="address_type" id="typeOther" value="OTHER">
              <label class="btn btn-outline-success" for="typeOther"><i class="bi bi-geo-alt me-1"></i> OTHER</label>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Complete Street Address *</label>
            <textarea name="address_line" class="form-control rounded-3" rows="3" placeholder="Door No, Apartment / Building Name, Street / Layout" required></textarea>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Nearby Landmark</label>
              <input type="text" name="landmark" class="form-control rounded-3" placeholder="Opposite Metro Station">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Pincode *</label>
              <input type="text" name="pincode" class="form-control rounded-3" placeholder="560038" value="560038" required>
            </div>
          </div>

          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_default" value="1" id="defaultCheck" checked>
            <label class="form-check-label small text-muted" for="defaultCheck">
              Set as primary default address for food deliveries
            </label>
          </div>
        </div>

        <div class="modal-footer border-top">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">Save Address</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
