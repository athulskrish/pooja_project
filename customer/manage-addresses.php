<?php
declare(strict_types=1);
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
$currentUser = getCurrentUser();
requireLogin();

$userId = (int)$currentUser['id'];

// Handle Add / Set Default / Soft Delete Address Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid session token.');
    } elseif ($_POST['action'] === 'add_address') {
        $res = OrderService::addCustomerAddress($userId, $_POST);
        setFlash($res['type'], $res['message']);
        if (!empty($res['should_redirect'])) {
            header("Location: " . CUSTOMER_URL . "/manage-addresses.php");
            exit;
        }
    } elseif ($_POST['action'] === 'set_default') {
        $res = OrderService::setDefaultCustomerAddress($userId, (int)($_POST['address_id'] ?? 0));
        setFlash($res['type'], $res['message']);
        header("Location: " . CUSTOMER_URL . "/manage-addresses.php");
        exit;
    } elseif ($_POST['action'] === 'delete_address') {
        $res = OrderService::softDeleteCustomerAddress($userId, (int)($_POST['address_id'] ?? 0));
        setFlash($res['type'], $res['message']);
        header("Location: " . CUSTOMER_URL . "/manage-addresses.php");
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';

// Fetch active saved addresses
$addresses = OrderService::getCustomerAddresses($userId);
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
                <?php
                  $aLat = isset($addr['latitude']) && $addr['latitude'] !== null ? (float)$addr['latitude'] : ZIPPY_HUB_LAT;
                  $aLng = isset($addr['longitude']) && $addr['longitude'] !== null ? (float)$addr['longitude'] : ZIPPY_HUB_LNG;
                  $aDist = isset($addr['distance_km']) && $addr['distance_km'] !== null
                    ? (float)$addr['distance_km']
                    : calculateHaversineDistanceKm(ZIPPY_HUB_LAT, ZIPPY_HUB_LNG, $aLat, $aLng);
                  $aFee = calculateDistanceDeliveryFee($aDist, 100.00);
                ?>
                <div class="small text-muted">PIN: <?= e($addr['pincode']) ?> | Bengaluru</div>
                <div class="small text-success fw-semibold mt-1">
                  <i class="bi bi-signpost-split me-1"></i><?= number_format($aDist, 1) ?> km from Indiranagar Hub &bull; Est. Delivery ₹<?= number_format($aFee, 0) ?>
                </div>
              </div>

              <div class="border-top pt-3 mt-3 d-flex align-items-center justify-content-between">
                <?php if (!$addr['is_default']): ?>
                  <form method="POST" action="<?= CUSTOMER_URL ?>/manage-addresses.php" class="m-0">
                    <?= csrfField() ?>
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
                  <?= csrfField() ?>
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

<!-- Add Address Modal (Interactive Leaflet Indian Coordinates Pin-Drop & 12km Geofence) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<div class="modal fade" id="addAddressModal" tabindex="-1" aria-labelledby="addAddressModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold font-outfit" id="addAddressModalLabel"><i class="bi bi-geo-alt-fill text-success me-2"></i>Add Delivery Destination (Bengaluru)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <form method="POST" action="<?= CUSTOMER_URL ?>/manage-addresses.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_address">
        <input type="hidden" name="latitude" id="addrLatInput" value="<?= e((string)ZIPPY_HUB_LAT) ?>">
        <input type="hidden" name="longitude" id="addrLngInput" value="<?= e((string)ZIPPY_HUB_LNG) ?>">
        
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
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
              <label class="form-label small fw-bold mb-0"><i class="bi bi-pin-map-fill text-danger me-1"></i>Drop Pin on Bengaluru Map (12 km Radius)</label>
              <span class="badge bg-success-subtle text-success border border-success-subtle" id="pinDistanceBadge">0.0 km from Hub &bull; ₹25 Delivery</span>
            </div>
            <div class="d-flex flex-wrap gap-1 mb-2">
              <?php foreach (array_slice(getIndianServiceZones(), 0, 6, true) as $zPin => $zInfo): ?>
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill py-0 px-2 quick-zone-btn" style="font-size:0.72rem;"
                        data-pin="<?= e($zPin) ?>" data-lat="<?= e((string)$zInfo['lat']) ?>" data-lng="<?= e((string)$zInfo['lng']) ?>">
                  <?= e($zInfo['zone']) ?> (<?= e($zPin) ?>)
                </button>
              <?php endforeach; ?>
            </div>
            <div id="addressPinMap" class="rounded-3 border" style="height: 220px; z-index: 1;"></div>
            <div class="form-text small">Drag the green pin or click anywhere on the Bengaluru map. Deliveries outside <?= (int)MAX_DELIVERY_RADIUS_KM ?> km from Indiranagar Hub are restricted.</div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Complete Street Address *</label>
            <textarea name="address_line" class="form-control rounded-3" rows="2" placeholder="Door No, Apartment / Building Name, Street / Layout" required></textarea>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Nearby Landmark</label>
              <input type="text" name="landmark" class="form-control rounded-3" placeholder="Opposite Metro Station">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Pincode (560xxx) *</label>
              <input type="text" name="pincode" id="addrPincodeInput" class="form-control rounded-3" placeholder="560038" value="560038" required>
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
          <button type="submit" id="saveAddressSubmitBtn" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" style="background:#2E6F40; border-color:#2E6F40;">Save Address</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const HUB_LAT = <?= json_encode((float)ZIPPY_HUB_LAT) ?>;
  const HUB_LNG = <?= json_encode((float)ZIPPY_HUB_LNG) ?>;
  const MAX_RADIUS = <?= json_encode((float)MAX_DELIVERY_RADIUS_KM) ?>;
  const ZONES = <?= json_encode(getIndianServiceZones()) ?>;

  const modalEl = document.getElementById('addAddressModal');
  const latInput = document.getElementById('addrLatInput');
  const lngInput = document.getElementById('addrLngInput');
  const pinInput = document.getElementById('addrPincodeInput');
  const distBadge = document.getElementById('pinDistanceBadge');
  const submitBtn = document.getElementById('saveAddressSubmitBtn');

  let pinMap = null;
  let pinMarker = null;

  function haversineKm(lat1, lng1, lat2, lng2) {
    const rad = d => (d * Math.PI) / 180;
    const R = 6371;
    const dLat = rad(lat2 - lat1);
    const dLng = rad(lng2 - lng1);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(dLng / 2) ** 2;
    return Math.round(R * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))) * 100) / 100;
  }

  function updatePinMetrics(lat, lng) {
    if (latInput) latInput.value = lat.toFixed(6);
    if (lngInput) lngInput.value = lng.toFixed(6);
    const d = haversineKm(HUB_LAT, HUB_LNG, lat, lng);
    const fee = d <= 3 ? 25 : (d <= 6 ? 40 : (d <= 10 ? 55 : 70));
    const inRange = d <= MAX_RADIUS;
    if (distBadge) {
      if (inRange) {
        distBadge.className = 'badge bg-success-subtle text-success border border-success-subtle';
        distBadge.textContent = `${d.toFixed(1)} km from Hub • Delivery ₹${fee}`;
      } else {
        distBadge.className = 'badge bg-danger text-white';
        distBadge.textContent = `${d.toFixed(1)} km (Outside ${MAX_RADIUS} km geofence!)`;
      }
    }
    if (submitBtn) submitBtn.disabled = !inRange;
  }

  function setPinLocation(lat, lng, pan = true) {
    if (pinMarker) pinMarker.setLatLng([lat, lng]);
    if (pinMap && pan) pinMap.setView([lat, lng], 13);
    updatePinMetrics(lat, lng);
  }

  if (modalEl && typeof L !== 'undefined') {
    modalEl.addEventListener('shown.bs.modal', () => {
      if (!pinMap) {
        pinMap = L.map('addressPinMap').setView([HUB_LAT, HUB_LNG], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '&copy; OpenStreetMap'
        }).addTo(pinMap);

        L.circleMarker([HUB_LAT, HUB_LNG], {
          radius: 7,
          color: '#2E6F40',
          fillColor: '#2E6F40',
          fillOpacity: 1
        }).bindPopup('Zippy Kitchen Hub (Indiranagar)').addTo(pinMap);

        L.circle([HUB_LAT, HUB_LNG], {
          radius: MAX_RADIUS * 1000,
          color: '#2E6F40',
          weight: 1.5,
          fillColor: '#CEFFDC',
          fillOpacity: 0.12
        }).addTo(pinMap);

        pinMarker = L.marker([HUB_LAT, HUB_LNG], { draggable: true }).addTo(pinMap);
        pinMarker.on('dragend', () => {
          const pos = pinMarker.getLatLng();
          updatePinMetrics(pos.lat, pos.lng);
        });
        pinMap.on('click', (evt) => {
          setPinLocation(evt.latlng.lat, evt.latlng.lng, false);
        });
        updatePinMetrics(HUB_LAT, HUB_LNG);
      }
      setTimeout(() => pinMap && pinMap.invalidateSize(), 150);
    });
  }

  document.querySelectorAll('.quick-zone-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const lat = parseFloat(btn.dataset.lat);
      const lng = parseFloat(btn.dataset.lng);
      const pin = btn.dataset.pin || '';
      if (pinInput && pin) pinInput.value = pin;
      if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
        setPinLocation(lat, lng, true);
      }
    });
  });

  if (pinInput) {
    pinInput.addEventListener('input', () => {
      const p = pinInput.value.trim();
      if (ZONES[p]) {
        setPinLocation(Number(ZONES[p].lat), Number(ZONES[p].lng), true);
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
