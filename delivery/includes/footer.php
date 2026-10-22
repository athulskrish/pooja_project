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

          <!-- Simulated Map Visual Container -->
          <div class="rounded-4 p-4 text-center position-relative mb-3 overflow-hidden shadow-xs" style="background: #1C2B20; color: #fff; min-height: 220px;">
            <div class="d-flex flex-column justify-content-center align-items-center h-100 py-3">
              <i class="fa-solid fa-map-location-dot fs-1 text-mint mb-2" style="color:#CEFFDC;"></i>
              <h5 class="fw-bold mb-1">Live Turn-by-Turn Route Navigation</h5>
              <p class="small text-white-50 mb-3" style="max-width: 480px;">
                Heading North on 100ft Road towards Metro Pillar 84. Turn right onto 12th Main Road.
              </p>
              <div class="d-flex align-items-center gap-2 bg-white bg-opacity-10 px-3 py-2 rounded-pill small">
                <span class="pulse-dot"></span>
                <span>Speed: 28 km/h • GPS Signal: High Precision</span>
              </div>
            </div>
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

    // Open GPS Modal
    function openGpsModal(orderId, custName, address) {
      document.getElementById('gpsOrderNum').textContent = '#ZIP-' + orderId;
      document.getElementById('gpsCustName').textContent = custName;
      document.getElementById('gpsAddress').textContent = address;
      const modal = new bootstrap.Modal(document.getElementById('gpsMapModal'));
      modal.show();
    }

    // Instant Payout Simulation
    function handleCashOut() {
      alert('Instant Direct Bank Transfer initiated to HDFC A/C ••••4019 via IMPS (Ref: IMPS-7824901). Payout will reflect within 5-10 minutes!');
    }
  </script>
</body>
</html>
