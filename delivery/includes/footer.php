  </main>

  <!-- =========================================================================
       MODAL 1: DOORSTEP OTP VERIFICATION MODAL (UC-17)
       ========================================================================= -->
  <div class="modal fade" id="otpVerifyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
      <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
        
        <div class="p-4 text-center text-white" style="background: linear-gradient(135deg, #1C3322 0%, #2E6F40 100%);">
          <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-20 p-3 mb-2" style="width: 58px; height: 58px;">
            <i class="bi bi-shield-lock-fill fs-3 text-white"></i>
          </div>
          <h5 class="fw-bold mb-1">Verify Doorstep Handshake</h5>
          <p class="small text-white-50 mb-0">Ask the customer for their 6-digit Secret OTP</p>
        </div>

        <div class="p-4">
          <form method="POST" action="<?= DELIVERY_URL ?>/index.php" id="otpForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="verify_otp">
            <input type="hidden" name="order_id" id="modalOrderId" value="">

            <div class="text-center mb-3">
              <div class="small text-muted mb-1 text-uppercase fw-bold" style="letter-spacing:1px;">Delivering Active Order</div>
              <div class="badge bg-dark fs-5 px-3 py-2 rounded-pill font-monospace mb-2" id="modalOrderDisplay">#ZIP-1004</div>
              <div class="fw-bold text-dark fs-5" id="modalCustomerName">Customer Name</div>
              <div id="modalCodNotice" class="badge bg-warning text-dark mt-2 d-none">
                <i class="bi bi-cash-stack me-1"></i> Cash on Delivery: Collect <span id="modalCodAmount">₹0.00</span>
              </div>
            </div>

            <!-- Prominent Demo OTP Banner with Auto-Fill Button -->
            <div class="alert alert-info py-2 px-3 small rounded-3 mb-3 d-flex align-items-center justify-content-between" style="background:#EDFBF2; border-color:#68BA7F; color:#243E2C;">
              <div class="text-start">
                <div class="small text-muted">Customer Secret OTP:</div>
                <div class="fs-4 fw-bold font-monospace text-dark" id="modalOtpHint">------</div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-success rounded-pill fw-bold bg-white shadow-xs" onclick="document.getElementById('inputOtp').value = document.getElementById('modalOtpHint').textContent.trim();">
                <i class="bi bi-magic me-1"></i> 1-Click Fill
              </button>
            </div>

            <div class="mb-3 text-center">
              <label class="form-label small fw-bold text-dark">Enter 6-Digit OTP</label>
              <input type="text" name="otp" id="inputOtp" class="form-control form-control-lg text-center font-monospace fw-bold fs-3" maxlength="6" placeholder="••••••" style="letter-spacing: 8px;" required autofocus>
              <div class="form-text small">Guaranteed by 3NF <code>delivery_dispatches</code> schema</div>
            </div>

            <button type="submit" class="btn btn-rider-primary w-100 py-3 fw-bold fs-6 mb-2 shadow-sm">
              <i class="bi bi-check-circle-fill me-1"></i> Confirm & Complete Delivery
            </button>
            <button type="button" class="btn btn-light w-100 rounded-pill py-2 text-muted" data-bs-dismiss="modal">
              Cancel
            </button>
          </form>
      </div>
    </div>
  </div>

  <!-- =========================================================================
       MODAL 2: TURN-BY-TURN GPS NAVIGATION (UC-16)
       ========================================================================= -->
  <div class="modal fade" id="gpsMapModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
        
        <div class="p-3 bg-dark text-white d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success rounded-pill px-3 py-1">GPS ROUTE SIMULATOR</span>
            <span class="fw-bold font-monospace" id="gpsOrderNum">#ZIP-1002</span>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="p-4 bg-light">
          <!-- Route Visual Header -->
          <div class="card border-0 rounded-4 p-3 bg-white shadow-xs mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <div class="small text-muted">Destination:</div>
                <h6 class="fw-bold text-dark mb-0" id="gpsCustName">Customer Address</h6>
                <div class="small text-muted" id="gpsAddress">Indiranagar 100ft Road</div>
              </div>
              <div class="d-flex gap-3 text-center">
                <div>
                  <div class="small text-muted">Distance</div>
                  <div class="fw-bold text-dark font-monospace fs-5">2.2 km</div>
                </div>
                <div class="vr"></div>
                <div>
                  <div class="small text-muted">ETA</div>
                  <div class="fw-bold text-success font-monospace fs-5">7 mins</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Route Visual Header -->
          <div class="card border-0 rounded-4 p-3 bg-white shadow-xs mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <div class="small text-muted">Destination:</div>
                <h6 class="fw-bold text-dark mb-0" id="gpsCustName">Customer Address</h6>
                <div class="small text-muted" id="gpsAddress">Indiranagar 100ft Road</div>
              </div>
              <div class="d-flex gap-3 text-center">
                <div>
                  <div class="small text-muted">Distance</div>
                  <div class="fw-bold text-dark font-monospace fs-5" id="riderDistanceRemaining">2.2 km</div>
                </div>
                <div class="vr"></div>
                <div>
                  <div class="small text-muted">ETA</div>
                  <div class="fw-bold text-success font-monospace fs-5" id="riderEtaRemaining">7 mins</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Interactive Turn-by-Turn Leaflet Map Container -->
          <div class="rounded-4 mb-3 overflow-hidden shadow-xs border" style="height: 280px; position: relative;">
            <div id="riderGpsMap" style="width: 100%; height: 100%; background: #1C2B20;"></div>
            
            <!-- Floating Navigation Controls overlay -->
            <div class="position-absolute bottom-0 start-0 end-0 p-3 bg-dark bg-opacity-75 text-white d-flex justify-content-between align-items-center flex-wrap gap-2" style="z-index: 1000;">
              <div>
                <span class="pulse-dot me-1"></span>
                <span class="small font-monospace fw-bold" id="riderGpsTelemetry">Speed: 24 km/h • GPS Signal High</span>
              </div>
              <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-bold shadow-xs" id="btnSimulateRideStep" onclick="simulateRiderStep()">
                <i class="fa-solid fa-motorcycle me-1"></i> Drive Along Route &rarr;
              </button>
            </div>
          </div>

          <!-- Doorstep Arrival Notification Bar (Shown when route complete) -->
          <div id="doorstepArrivalBanner" class="alert alert-success rounded-3 p-3 mb-3 d-none align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-geo-alt-fill fs-4 text-success"></i>
              <div>
                <strong class="d-block text-dark">You have arrived at the customer doorstep!</strong>
                <span class="small text-muted">Please ring bell and ask customer for their 6-digit Secret OTP.</span>
              </div>
            </div>
            <button type="button" class="btn btn-sm btn-dark rounded-pill px-3 fw-bold" id="btnArrivalEnterOtp" onclick="triggerOtpFromGps()">
              <i class="bi bi-shield-lock me-1"></i> Enter OTP
            </button>
          </div>

          <!-- Turn Instructions List -->
          <div class="card border-0 rounded-4 p-3 bg-white shadow-xs">
            <h6 class="fw-bold text-dark small text-uppercase mb-2">Turn Instructions</h6>
            <div class="d-flex align-items-center gap-3 py-2 border-bottom small">
              <i class="bi bi-arrow-up-circle-fill text-success fs-5"></i>
              <div>Depart from Zippy Kitchen Hub onto 100 Feet Road (Continue 1.1 km)</div>
            </div>
            <div class="d-flex align-items-center gap-3 py-2 border-bottom small">
              <i class="bi bi-arrow-right-circle-fill text-primary fs-5"></i>
              <div>Turn RIGHT at Indiranagar Metro Station onto 12th Main (600m)</div>
            </div>
            <div class="d-flex align-items-center gap-3 py-2 small">
              <i class="bi bi-geo-alt-fill text-danger fs-5"></i>
              <div>Arrive at Customer Doorstep (Flat / Villa entrance)</div>
            </div>
          </div>
        </div>

        <div class="p-3 bg-white border-top text-end">
          <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close Navigation</button>
        </div>

      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Tab switching
    function switchTab(tabId) {
      document.getElementById('viewOrders').style.display = (tabId === 'orders') ? 'block' : 'none';
      document.getElementById('viewHistory').style.display = (tabId === 'history') ? 'block' : 'none';
      document.getElementById('viewEarnings').style.display = (tabId === 'earnings') ? 'block' : 'none';

      document.getElementById('tabBtnOrders').classList.toggle('active', tabId === 'orders');
      document.getElementById('tabBtnHistory').classList.toggle('active', tabId === 'history');
      document.getElementById('tabBtnEarnings').classList.toggle('active', tabId === 'earnings');
    }

    let activeGpsOrderId = 0;
    let riderMap = null;
    let riderMarker = null;
    let riderRouteLine = null;
    let currentRiderProgress = 0;

    // Open OTP Verification Modal
    function openOtpModal(orderId, otp, custName, payMode, totalAmount) {
      document.getElementById('modalOrderId').value = orderId;
      document.getElementById('modalOrderDisplay').textContent = '#ZIP-' + orderId;
      document.getElementById('modalCustomerName').textContent = custName;
      document.getElementById('modalOtpHint').textContent = otp;
      document.getElementById('inputOtp').value = '';

      const codNotice = document.getElementById('modalCodNotice');
      if (payMode === 'COD') {
        codNotice.classList.remove('d-none');
        document.getElementById('modalCodAmount').textContent = totalAmount;
      } else {
        codNotice.classList.add('d-none');
      }

      const modal = new bootstrap.Modal(document.getElementById('otpVerifyModal'));
      modal.show();
      setTimeout(() => document.getElementById('inputOtp').focus(), 400);
    }

    // Open GPS Modal with Real-time Leaflet Map
    function openGpsModal(orderId, custName, address) {
      activeGpsOrderId = orderId;
      document.getElementById('gpsOrderNum').textContent = '#ZIP-' + orderId;
      document.getElementById('gpsCustName').textContent = custName;
      document.getElementById('gpsAddress').textContent = address;
      const arrivalBanner = document.getElementById('doorstepArrivalBanner');
      if (arrivalBanner) arrivalBanner.classList.add('d-none');

      const modalEl = document.getElementById('gpsMapModal');
      const modal = new bootstrap.Modal(modalEl);
      modal.show();

      // Initialize map after modal is shown so dimensions calculate correctly
      setTimeout(() => {
        initRiderGpsMap(orderId);
      }, 300);
    }

    async function initRiderGpsMap(orderId) {
      const mapContainer = document.getElementById('riderGpsMap');
      if (!mapContainer || typeof L === 'undefined') return;

      try {
        if (!riderMap) {
          riderMap = L.map('riderGpsMap', {
            zoomControl: true,
            scrollWheelZoom: false
          }).setView([12.974, 77.645], 14);

          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
          }).addTo(riderMap);
        } else {
          riderMap.invalidateSize();
        }

        // Fetch location details from API
        const res = await fetch(`../api/live-location.php?order_id=${orderId}`);
        const data = await res.json();
        if (data.success) {
          const hub = [data.locations.restaurant.lat, data.locations.restaurant.lng];
          const cust = [data.locations.customer.lat, data.locations.customer.lng];
          const rider = [data.locations.rider.lat, data.locations.rider.lng];
          currentRiderProgress = data.route.progress_pct || 0;

          document.getElementById('riderDistanceRemaining').textContent = `${data.route.distance_remaining_km} km`;
          document.getElementById('riderEtaRemaining').textContent = `${data.route.eta_minutes} mins`;

          // Clear previous layers if any
          if (riderRouteLine) riderMap.removeLayer(riderRouteLine);
          if (riderMarker) riderMap.removeLayer(riderMarker);

          // Route polyline
          const waypoints = data.route.waypoints.map(w => [w.lat, w.lng]);
          riderRouteLine = L.polyline(waypoints, {
            color: '#2E6F40',
            weight: 5,
            opacity: 0.8
          }).addTo(riderMap);

          // Markers
          L.marker(hub).addTo(riderMap).bindPopup('<strong>Zippy Hub</strong>');
          L.marker(cust).addTo(riderMap).bindPopup('<strong>Customer Doorstep</strong>');

          // Rider Bike Icon
          const bikeIcon = L.divIcon({
            className: 'custom-rider-bike',
            html: '<div style="background:#2E6F40; color:#fff; width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 3px 10px rgba(0,0,0,0.4); border:2px solid #fff;"><i class="fa-solid fa-motorcycle"></i></div>',
            iconSize: [34, 34],
            iconAnchor: [17, 17]
          });
          riderMarker = L.marker(rider, { icon: bikeIcon }).addTo(riderMap).bindPopup('<strong>Your Position</strong>');

          riderMap.fitBounds(L.latLngBounds([hub, cust]), { padding: [30, 30] });

          if (currentRiderProgress >= 100) {
            const arr = document.getElementById('doorstepArrivalBanner');
            if (arr) {
              arr.classList.remove('d-none');
              arr.classList.add('d-flex');
            }
          }
        }
      } catch (err) {
        console.warn('Rider map error:', err);
      }
    }

    async function simulateRiderStep() {
      if (!activeGpsOrderId) return;
      const btn = document.getElementById('btnSimulateRideStep');
      if (btn) btn.disabled = true;

      try {
        const formData = new FormData();
        formData.append('order_id', activeGpsOrderId);
        formData.append('action', 'simulate_step');
        formData.append('delta', '25'); // advance 25%

        const res = await fetch('../api/live-location.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          currentRiderProgress = data.route_progress;
          document.getElementById('riderDistanceRemaining').textContent = `${data.distance_remaining_km} km`;
          document.getElementById('riderEtaRemaining').textContent = `${data.estimated_minutes_remaining} mins`;

          if (riderMarker) {
            riderMarker.setLatLng([data.current_latitude, data.current_longitude]);
            riderMap.panTo([data.current_latitude, data.current_longitude]);
          }

          if (data.arrived_at_doorstep) {
            document.getElementById('riderGpsTelemetry').textContent = 'Speed: 0 km/h • ARRIVED AT DOORSTEP';
            const arr = document.getElementById('doorstepArrivalBanner');
            if (arr) {
              arr.classList.remove('d-none');
              arr.classList.add('d-flex');
            }
          } else {
            document.getElementById('riderGpsTelemetry').textContent = `Speed: 26 km/h • Progress: ${currentRiderProgress}%`;
          }
        }
      } catch (e) {
        console.error('Simulation step error:', e);
      } finally {
        if (btn) btn.disabled = false;
      }
    }

    function triggerOtpFromGps() {
      const gpsModalEl = document.getElementById('gpsMapModal');
      const gpsModal = bootstrap.Modal.getInstance(gpsModalEl);
      if (gpsModal) gpsModal.hide();

      // Find the card for this order
      const card = document.getElementById('orderCard' + activeGpsOrderId);
      if (card) {
        const btnVerify = card.querySelector('button[onclick*="openOtpModal"]');
        if (btnVerify) {
          btnVerify.click();
          return;
        }
      }
      openOtpModal(activeGpsOrderId, '------', 'Customer', 'ONLINE', '₹0.00');
    }

    // Background poller for delivery fleet: checks for ready orders every 4 seconds
    setInterval(async () => {
      try {
        const res = await fetch('../api/live-stream.php?portal=delivery&rider_id=<?= (int)($currentUser['id'] ?? 0) ?>');
        if (!res.ok) return;
        const data = await res.json();
        if (data.success && data.counts) {
          const readyCount = data.counts.ready_for_pickup;
          const riderActive = data.rider_has_active ? 1 : (document.querySelectorAll('.active-trip-card').length > 0 ? 1 : 0);
          // Tab badge update
          const tabBtn = document.getElementById('tabBtnOrders');
          if (tabBtn) {
            const badge = tabBtn.querySelector('.badge');
            if (badge) badge.textContent = readyCount + riderActive;
          }
        }
      } catch(e) {}
    }, 4000);

    // Instant Payout Simulation
    function handleCashOut() {
      alert('Instant Direct Bank Transfer initiated to HDFC A/C ••••4019 via IMPS (Ref: IMPS-7824901). Payout will reflect within 5-10 minutes!');
    }
  </script>
</body>
</html>
