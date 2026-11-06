  </main>

  <!-- =========================================================================
       FIXED BOTTOM NAVIGATION FOR MOBILE VIEWPORTS (<= 768px)
       ========================================================================= -->
  <nav class="rider-bottom-nav d-md-none" aria-label="Mobile Bottom Navigation">
    <button type="button" class="bottom-nav-item active" id="bNavOrders" onclick="switchTab('orders')">
      <i class="bi bi-box-seam fs-5"></i>
      <span>Active & Ready</span>
      <span class="badge bg-success rounded-pill bnav-badge" id="bNavBadgeOrders"><?= count($activeOrders) + count($readyOrders) ?></span>
    </button>
    <button type="button" class="bottom-nav-item" id="bNavHistory" onclick="switchTab('history')">
      <i class="bi bi-clock-history fs-5"></i>
      <span>History</span>
      <span class="badge bg-secondary rounded-pill bnav-badge" id="bNavBadgeHistory"><?= count($historyOrders) ?></span>
    </button>
    <button type="button" class="bottom-nav-item" id="bNavEarnings" onclick="switchTab('earnings')">
      <i class="bi bi-wallet2 fs-5"></i>
      <span>Earnings</span>
    </button>
  </nav>

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
            </div>

            <!-- Enhanced Cash on Delivery Handshake Alert -->
            <div id="modalCodNotice" class="alert alert-warning border-warning rounded-3 p-3 mb-3 d-none text-start">
              <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-cash-stack fs-4 text-warning"></i>
                <div>
                  <strong class="d-block text-dark">Cash on Delivery (COD) Order</strong>
                  <span class="small text-muted">Collect <strong class="text-dark fs-6 font-monospace" id="modalCodAmount">₹0.00</strong> before handing over food parcel.</span>
                </div>
              </div>
              
              <!-- Quick Change Calculator -->
              <div class="bg-white p-2 rounded-2 border small mb-2">
                <div class="fw-bold text-muted mb-1"><i class="bi bi-calculator me-1"></i> Quick Tender / Return Change:</div>
                <div class="d-flex gap-1 flex-wrap align-items-center">
                  <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="calcTenderChange(500)">Paid ₹500</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="calcTenderChange(1000)">Paid ₹1000</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="calcTenderChange(2000)">Paid ₹2000</button>
                  <span class="fw-bold text-success font-monospace ms-auto" id="calcChangeOutput"></span>
                </div>
              </div>

              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="checkCashReceived" onchange="toggleCodConfirmState()">
                <label class="form-check-label fw-bold text-dark small" for="checkCashReceived">
                  I have received full cash in hand
                </label>
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
              <div class="form-text small text-muted"><i class="bi bi-shield-check text-success me-1"></i> Verified via 6-digit one-time doorstep handshake</div>
            </div>

            <button type="submit" class="btn btn-rider-primary w-100 py-3 fw-bold fs-6 mb-2 shadow-sm" id="btnOtpSubmit">
              <i class="bi bi-check-circle-fill me-1"></i> Confirm & Complete Delivery
            </button>
            <button type="button" class="btn btn-light w-100 rounded-pill py-2 text-muted" data-bs-dismiss="modal">
              Cancel
            </button>
          </form>
        </div>
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
          <!-- Route Visual Header (Clean single instance with real-time ETA & distance) -->
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

  <!-- =========================================================================
       MODAL 3: RIDER SUPPORT & ISSUE SOS (UC-16 Exception Handling)
       ========================================================================= -->
  <div class="modal fade" id="riderSupportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
      <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
        <div class="p-3 bg-danger text-white d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <span class="fw-bold">Rider Support & Issue SOS</span>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="p-4 bg-light">
          <p class="small text-muted mb-3">Report delivery difficulty for active order <strong id="supportOrderDisplay">#ZIP-XXXX</strong>. Our hub dispatcher will assist immediately.</p>
          <div class="d-flex flex-column gap-2 mb-3">
            <label class="card border rounded-3 p-2 bg-white cursor-pointer d-flex flex-row align-items-center gap-2 mb-0">
              <input type="radio" name="supportReason" value="unreachable" checked>
              <span class="small fw-bold text-dark">Customer not answering phone (Attempt logged)</span>
            </label>
            <label class="card border rounded-3 p-2 bg-white cursor-pointer d-flex flex-row align-items-center gap-2 mb-0">
              <input type="radio" name="supportReason" value="wrong_address">
              <span class="small fw-bold text-dark">Incorrect address / Gate security denied entry</span>
            </label>
            <label class="card border rounded-3 p-2 bg-white cursor-pointer d-flex flex-row align-items-center gap-2 mb-0">
              <input type="radio" name="supportReason" value="breakdown">
              <span class="small fw-bold text-dark">Vehicle breakdown / Flat tire</span>
            </label>
            <label class="card border rounded-3 p-2 bg-white cursor-pointer d-flex flex-row align-items-center gap-2 mb-0">
              <input type="radio" name="supportReason" value="spill">
              <span class="small fw-bold text-dark">Food container damaged / spilled in transit</span>
            </label>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary rounded-pill w-50 py-2" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger rounded-pill w-50 py-2 fw-bold" onclick="submitSupportIssue()">
              <i class="bi bi-send me-1"></i> Dispatch SOS
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- =========================================================================
       MODAL 4: INSTANT PAYOUT CASH OUT (UC-17 Direct Bank Transfer)
       ========================================================================= -->
  <div class="modal fade" id="cashOutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
      <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
        <div class="p-4 text-center text-white" style="background: linear-gradient(135deg, #1C3322 0%, #2E6F40 100%);">
          <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-20 p-3 mb-2" style="width: 58px; height: 58px;">
            <i class="bi bi-bank fs-3 text-white"></i>
          </div>
          <h5 class="fw-bold mb-1">Instant Bank Payout</h5>
          <p class="small text-white-50 mb-0">Direct 24x7 IMPS Payout to Verified Savings Account</p>
        </div>
        <div class="p-4 bg-light" id="cashOutInitialView">
          <div class="card border-0 rounded-3 p-3 bg-white shadow-xs text-center mb-3">
            <div class="small text-muted fw-bold">Transferrable Fleet Balance</div>
            <div class="display-6 fw-bold text-success font-monospace" id="cashOutModalBalance">₹0.00</div>
            <div class="badge bg-success bg-opacity-10 text-success rounded-pill mx-auto mt-1 small">Zero IMPS Transfer Fee</div>
          </div>
          <div class="card border-0 rounded-3 p-3 bg-white shadow-xs mb-3 small">
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted">Direct Deposit Bank:</span>
              <strong class="text-dark">HDFC Bank Ltd</strong>
            </div>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted">Account Number:</span>
              <strong class="font-monospace text-dark">••••••••4019</strong>
            </div>
            <div class="d-flex justify-content-between">
              <span class="text-muted">IFSC Code:</span>
              <strong class="font-monospace text-dark">HDFC0001234</strong>
            </div>
          </div>
          <button type="button" class="btn btn-rider-primary w-100 py-3 fw-bold shadow-sm" id="btnConfirmCashOut" onclick="executeCashOut()">
            <i class="bi bi-check-circle-fill me-1"></i> Transfer to Bank Now
          </button>
        </div>
        <!-- Payout Success Confirmation View -->
        <div class="p-4 bg-light text-center d-none" id="cashOutSuccessView">
          <div class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center p-3 mb-3" style="width: 70px; height: 70px;">
            <i class="bi bi-check2-circle fs-1"></i>
          </div>
          <h5 class="fw-bold text-dark mb-1">Payout Transfer Initiated!</h5>
          <p class="small text-muted mb-3">Your funds are being transferred to HDFC Bank A/C ••••4019.<br><strong>Reference ID: IMPS-7824901</strong></p>
          <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Tab switching: synchronizes desktop pill tabs and mobile bottom bar
    function switchTab(tabId) {
      document.getElementById('viewOrders').style.display = (tabId === 'orders') ? 'block' : 'none';
      document.getElementById('viewHistory').style.display = (tabId === 'history') ? 'block' : 'none';
      document.getElementById('viewEarnings').style.display = (tabId === 'earnings') ? 'block' : 'none';

      const tabIds = ['orders', 'history', 'earnings'];
      tabIds.forEach(t => {
        const topBtn = document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
        if (topBtn) topBtn.classList.toggle('active', t === tabId);

        const bNavBtn = document.getElementById('bNav' + t.charAt(0).toUpperCase() + t.slice(1));
        if (bNavBtn) bNavBtn.classList.toggle('active', t === tabId);
      });
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    let activeGpsOrderId = 0;
    let riderMap = null;
    let riderMarker = null;
    let riderRouteLine = null;
    let currentRiderProgress = 0;
    let activeCodAmountNum = 0;
    let isCurrentOrderCod = false;

    // Open OTP Verification Modal with COD Enforcement
    function openOtpModal(orderId, otp, custName, payMode, totalAmount) {
      document.getElementById('modalOrderId').value = orderId;
      document.getElementById('modalOrderDisplay').textContent = '#ZIP-' + orderId;
      document.getElementById('modalCustomerName').textContent = custName;
      document.getElementById('modalOtpHint').textContent = otp;
      document.getElementById('inputOtp').value = '';

      const codNotice = document.getElementById('modalCodNotice');
      const submitBtn = document.getElementById('btnOtpSubmit');
      const checkCash = document.getElementById('checkCashReceived');
      const changeOut = document.getElementById('calcChangeOutput');
      if (changeOut) changeOut.textContent = '';

      isCurrentOrderCod = (payMode === 'COD');
      // Parse numeric amount
      const cleanAmt = totalAmount.replace(/[^0-9.]/g, '');
      activeCodAmountNum = parseFloat(cleanAmt) || 0;

      if (isCurrentOrderCod) {
        codNotice.classList.remove('d-none');
        document.getElementById('modalCodAmount').textContent = totalAmount;
        if (checkCash) checkCash.checked = false;
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.classList.add('opacity-50');
        }
      } else {
        codNotice.classList.add('d-none');
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.classList.remove('opacity-50');
        }
      }

      const modal = new bootstrap.Modal(document.getElementById('otpVerifyModal'));
      modal.show();
      setTimeout(() => document.getElementById('inputOtp').focus(), 400);
    }

    // Toggle COD Submit Button State
    function toggleCodConfirmState() {
      const checkCash = document.getElementById('checkCashReceived');
      const submitBtn = document.getElementById('btnOtpSubmit');
      if (!isCurrentOrderCod) return;

      if (checkCash && checkCash.checked) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50');
      } else {
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-50');
      }
    }

    // Quick Change Calculator for COD
    function calcTenderChange(tendered) {
      const changeOut = document.getElementById('calcChangeOutput');
      if (!changeOut) return;
      if (tendered < activeCodAmountNum) {
        changeOut.className = 'fw-bold text-danger font-monospace ms-auto';
        changeOut.textContent = 'Insufficient (Short ₹' + (activeCodAmountNum - tendered).toFixed(2) + ')';
      } else {
        const change = tendered - activeCodAmountNum;
        changeOut.className = 'fw-bold text-success font-monospace ms-auto';
        changeOut.textContent = 'Return Change: ₹' + change.toFixed(2);
      }
    }

    // Toggle Demo OTP Reveal for Viva / Examiners
    function toggleDemoOtp(orderId) {
      const box = document.getElementById('demoOtpBox' + orderId);
      const btn = document.getElementById('btnRevealOtp' + orderId);
      if (!box || !btn) return;

      if (box.classList.contains('d-none')) {
        box.classList.remove('d-none');
        btn.innerHTML = '<i class="bi bi-eye-slash me-1"></i> Hide OTP';
      } else {
        box.classList.add('d-none');
        btn.innerHTML = '<i class="bi bi-eye me-1"></i> Reveal for Demo';
      }
    }

    // Asynchronous Duty Toggle (Zero Page Reload)
    async function toggleDutyAjax(isChecked) {
      const form = document.getElementById('dutyToggleForm');
      if (!form) return;

      const dutyVal = isChecked ? '1' : '0';
      const formData = new FormData(form);
      formData.set('duty', dutyVal);
      formData.set('is_ajax', '1');

      try {
        const res = await fetch('<?= DELIVERY_URL ?>/index.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          const heading = document.getElementById('dutyStatusHeading');
          const pill = document.getElementById('dutyStatusPill');
          const iconBox = document.getElementById('dutyIconBox');
          const hiddenInput = document.getElementById('dutyHiddenInput');
          const activeCards = document.querySelectorAll('.active-trip-card');

          if (hiddenInput) hiddenInput.value = data.on_duty ? '0' : '1';

          if (data.on_duty) {
            if (heading) heading.textContent = 'You are ONLINE';
            if (iconBox) {
              iconBox.style.background = 'var(--rider-mint)';
              iconBox.innerHTML = '<i class="bi bi-broadcast fs-4"></i>';
            }
            if (pill) {
              if (activeCards.length > 0) {
                pill.className = 'badge bg-warning text-dark rounded-pill px-2 py-1 small';
                pill.innerHTML = '<i class="bi bi-bicycle me-1"></i> Busy on Route (1/1 Active)';
              } else {
                pill.className = 'badge bg-success rounded-pill px-2 py-1 small';
                pill.innerHTML = '<span class="pulse-dot me-1"></span> Available for Dispatch (0/1)';
              }
            }
          } else {
            if (heading) heading.textContent = 'You are OFFLINE';
            if (iconBox) {
              iconBox.style.background = '#E2ECE5';
              iconBox.innerHTML = '<i class="bi bi-moon-stars fs-4"></i>';
            }
            if (pill) {
              pill.className = 'badge bg-secondary rounded-pill px-2 py-1 small';
              pill.textContent = 'Dispatches Paused';
            }
          }
        }
      } catch (e) {
        console.warn('Duty toggle error, falling back to standard submit:', e);
        form.submit();
      }
    }

    // High Contrast / Night Mode Toggle
    function toggleRiderTheme() {
      const isDark = document.body.classList.toggle('rider-dark-theme');
      const icon = document.getElementById('themeToggleIcon');
      if (icon) {
        icon.className = isDark ? 'bi bi-sun-fill text-warning' : 'bi bi-moon-stars';
      }
      localStorage.setItem('zippy_rider_theme', isDark ? 'dark' : 'light');
    }

    // Restore saved theme on load
    if (localStorage.getItem('zippy_rider_theme') === 'dark') {
      document.body.classList.add('rider-dark-theme');
      const icon = document.getElementById('themeToggleIcon');
      if (icon) icon.className = 'bi bi-sun-fill text-warning';
    }

    // Rider Support & Issue Modal
    function openSupportModal(orderId) {
      document.getElementById('supportOrderDisplay').textContent = '#ZIP-' + orderId;
      const modal = new bootstrap.Modal(document.getElementById('riderSupportModal'));
      modal.show();
    }

    function submitSupportIssue() {
      const selected = document.querySelector('input[name="supportReason"]:checked')?.value || 'Issue';
      const modalEl = document.getElementById('riderSupportModal');
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
      alert('Dispatch SOS Alert transmitted to Indiranagar Hub supervisor: [' + selected.toUpperCase() + ']. A support agent will contact you shortly.');
    }

    // Instant Cash Out Modal
    function handleCashOut() {
      const balEl = document.querySelector('#viewEarnings .display-6');
      const balText = balEl ? balEl.textContent.trim() : '₹0.00';
      document.getElementById('cashOutModalBalance').textContent = balText;
      document.getElementById('cashOutInitialView').classList.remove('d-none');
      document.getElementById('cashOutSuccessView').classList.add('d-none');

      const modal = new bootstrap.Modal(document.getElementById('cashOutModal'));
      modal.show();
    }

    function executeCashOut() {
      const btn = document.getElementById('btnConfirmCashOut');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing IMPS Transfer...';
      }
      setTimeout(() => {
        document.getElementById('cashOutInitialView').classList.add('d-none');
        document.getElementById('cashOutSuccessView').classList.remove('d-none');
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Transfer to Bank Now';
        }
      }, 1000);
    }

    // Dual-Tone Audio Chime for Ready Orders
    function playOrderChime() {
      try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, ctx.currentTime); // Note D5
        osc.frequency.setValueAtTime(880.00, ctx.currentTime + 0.12); // Note A5

        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.45);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start();
        osc.stop(ctx.currentTime + 0.48);

        if ('vibrate' in navigator) {
          navigator.vibrate([150, 80, 150]);
        }
      } catch (e) {}
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

        const res = await fetch(`../api/live-location.php?order_id=${orderId}`);
        const data = await res.json();
        if (data.success) {
          const hub = [data.locations.restaurant.lat, data.locations.restaurant.lng];
          const cust = [data.locations.customer.lat, data.locations.customer.lng];
          const rider = [data.locations.rider.lat, data.locations.rider.lng];
          currentRiderProgress = data.route.progress_pct || 0;

          document.getElementById('riderDistanceRemaining').textContent = `${data.route.distance_remaining_km} km`;
          document.getElementById('riderEtaRemaining').textContent = `${data.route.eta_minutes} mins`;

          if (riderRouteLine) riderMap.removeLayer(riderRouteLine);
          if (riderMarker) riderMap.removeLayer(riderMarker);

          const waypoints = data.route.waypoints.map(w => [w.lat, w.lng]);
          riderRouteLine = L.polyline(waypoints, {
            color: '#2E6F40',
            weight: 5,
            opacity: 0.8
          }).addTo(riderMap);

          L.marker(hub).addTo(riderMap).bindPopup('<strong>Zippy Hub</strong>');
          L.marker(cust).addTo(riderMap).bindPopup('<strong>Customer Doorstep</strong>');

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
        formData.append('delta', '25');

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

    // =========================================================================
    // DEDICATED AUTO-SYNC CONTROLLER & TAKE READY FOOD (SIMILAR TO KDS)
    // =========================================================================
    let riderAutoSyncActive = true;
    let riderSyncCountdown = 5;
    let riderSyncInterval = null;
    let knownReadyOrderIds = new Set();
    let isFirstSync = true;
    const currentRiderId = <?= (int)($currentUser['id'] ?? 0) ?>;

    function escapeHtml(str) {
      if (!str) return '';
      return String(str).replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
      }[m]));
    }

    function escapeJs(str) {
      if (!str) return '';
      return String(str).replace(/['"\\]/g, '\\$&').replace(/\n/g, '\\n').replace(/\r/g, '\\r');
    }

    // Floating notification toast for newly ready orders
    function showReadyOrderNotification(orderId, customerName) {
      let toastContainer = document.getElementById('readyToastContainer');
      if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'readyToastContainer';
        toastContainer.className = 'toast-ready-food';
        document.body.appendChild(toastContainer);
      }

      const toast = document.createElement('div');
      toast.className = 'card border-0 rounded-4 shadow-lg p-3 mb-2 bg-white text-start d-flex flex-row align-items-center gap-3 border-start border-4 border-warning';
      toast.style.minWidth = '320px';
      toast.style.maxWidth = '400px';
      toast.innerHTML = `
        <div class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width:42px; height:42px; background:#2E6F40;">
          <i class="fa-solid fa-fire-burner text-warning fs-5"></i>
        </div>
        <div class="flex-grow-1">
          <div class="d-flex justify-content-between align-items-center">
            <strong class="text-dark small">Food Ready at Kitchen!</strong>
            <button type="button" class="btn-close btn-sm" onclick="this.closest('.card').remove()"></button>
          </div>
          <div class="small text-muted" style="font-size:0.75rem;">Order <strong>#ZIP-${orderId}</strong> for ${escapeHtml(customerName)} is packed &amp; ready.</div>
          <button type="button" class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-bold mt-2 shadow-xs" style="font-size:0.75rem; background:#2E6F40; border-color:#2E6F40;" onclick="takeReadyFood(${orderId}); this.closest('.card').remove();">
            <i class="bi bi-handbag me-1"></i> Accept &amp; Pick Up
          </button>
        </div>
      `;
      toastContainer.appendChild(toast);
      setTimeout(() => { if (toast.parentNode) toast.remove(); }, 6000);
    }

    // 1. Ticking Countdown Sync Loop (similar to KDS)
    function startRiderAutoSync() {
      if (riderSyncInterval) clearInterval(riderSyncInterval);
      riderSyncInterval = setInterval(() => {
        if (!riderAutoSyncActive) return;
        riderSyncCountdown--;
        const textEl = document.getElementById('riderSyncText');
        if (textEl) {
          textEl.textContent = `SYNC (${riderSyncCountdown}s)`;
        }

        if (riderSyncCountdown <= 0) {
          riderSyncCountdown = 5;
          syncDeliveryApp();
        }
      }, 1000);
    }
    startRiderAutoSync();

    // 2. Manual Sync Trigger / Toggle
    function toggleRiderAutoSync() {
      riderSyncCountdown = 5;
      const textEl = document.getElementById('riderSyncText');
      if (textEl) textEl.textContent = 'SYNCING...';
      syncDeliveryApp(true);
    }

    // 3. Central Sync Function: Fetches Ready Orders and Active In-Transit Trip
    async function syncDeliveryApp(force = false) {
      const icon = document.getElementById('riderSyncIcon');
      if (icon) icon.classList.add('sync-spinning');

      try {
        const res = await fetch(`../api/live-stream.php?portal=delivery&rider_id=${currentRiderId}`);
        if (!res.ok) return;
        const data = await res.json();

        if (data.success) {
          const readyOrders = data.ready_orders || [];
          const activeOrder = data.active_order || null;
          const riderHasActive = !!data.rider_has_active;
          const currentOrderIds = new Set(readyOrders.map(o => o.order_id));

          // Detect newly ready food arriving at kitchen window
          if (!isFirstSync) {
            readyOrders.forEach(o => {
              if (!knownReadyOrderIds.has(o.order_id)) {
                playOrderChime();
                showReadyOrderNotification(o.order_id, o.customer_name);
              }
            });
          }
          knownReadyOrderIds = currentOrderIds;
          isFirstSync = false;

          // Update count badges
          const readyCount = readyOrders.length;
          const activeCount = riderHasActive ? 1 : 0;
          const readyBadge = document.getElementById('readyOrdersCountBadge');
          if (readyBadge) readyBadge.textContent = readyCount;

          const tabOrdersBadge = document.querySelector('#tabBtnOrders .badge');
          if (tabOrdersBadge) tabOrdersBadge.textContent = readyCount + activeCount;

          const bNavOrdersBadge = document.getElementById('bNavBadgeOrders');
          if (bNavOrdersBadge) bNavOrdersBadge.textContent = readyCount + activeCount;

          // Update Duty Pill
          const dutyPill = document.getElementById('dutyStatusPill');
          if (dutyPill) {
            if (riderHasActive) {
              dutyPill.className = 'badge bg-warning text-dark rounded-pill px-2 py-1 small';
              dutyPill.innerHTML = '<i class="bi bi-bicycle me-1"></i> Busy on Route (1/1 Active)';
            } else {
              dutyPill.className = 'badge bg-success rounded-pill px-2 py-1 small';
              dutyPill.innerHTML = '<span class="pulse-dot me-1"></span> Available for Dispatch (0/1)';
            }
          }

          // Update Policy Alert Box
          const policyAlert = document.getElementById('policyAlertBox');
          const policyOrderNum = document.getElementById('policyActiveOrderId');
          if (policyAlert) {
            if (riderHasActive && activeOrder) {
              policyAlert.classList.remove('d-none');
              if (policyOrderNum) policyOrderNum.textContent = `#ZIP-${activeOrder.order_id}`;
            } else {
              policyAlert.classList.add('d-none');
            }
          }

          // Render Active Delivery Section
          const activeSection = document.getElementById('activeDeliverySection');
          if (activeSection) {
            if (activeOrder) {
              // Only render if not already rendered or id changed
              const existingCard = document.getElementById(`orderCard${activeOrder.order_id}`);
              if (!existingCard) {
                activeSection.innerHTML = renderActiveOrderCard(activeOrder);
              }
            } else {
              activeSection.innerHTML = '';
            }
          }

          // Render Ready Orders Container
          const readyContainer = document.getElementById('readyOrdersCardsContainer');
          if (readyContainer) {
            if (riderHasActive) {
              readyContainer.innerHTML = `
                <div class="card border-0 rounded-4 p-3 text-center bg-white shadow-xs text-muted small">
                  <i class="bi bi-lock-fill text-warning fs-3 mb-1"></i>
                  <div>Complete your active delivery #ZIP-${activeOrder ? activeOrder.order_id : ''} to receive your next FCFS parcel allocation.</div>
                </div>
              `;
            } else if (data.allocated_order) {
              const alloc = data.allocated_order;
              const otherReady = readyOrders.filter(ro => parseInt(ro.order_id) !== parseInt(alloc.order_id));
              let html = `
                <div class="card border-2 rounded-4 p-4 shadow-sm bg-white mb-3" style="border-color: #2E6F40 !important;" id="allocatedOrderCard">
                  <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <span class="badge rounded-pill px-3 py-1 fw-bold" style="background:#CEFFDC; color:#243E2C; font-size:0.75rem;">
                      <i class="bi bi-cpu-fill me-1"></i> SYSTEM ALLOCATED (FCFS #1 PREPARED)
                    </span>
                    <span class="fw-bold font-monospace text-dark fs-6">#ZIP-${alloc.order_id}</span>
                  </div>
                  <div class="small text-muted mb-2">
                    <i class="bi bi-clock-history me-1 text-success"></i> Prepared at <strong>${escapeHtml(alloc.ready_at ? alloc.ready_at.substring(11, 16) : 'Recently')}</strong> • Allocated to you on First-Come, First-Served basis
                  </div>
                  <h5 class="fw-bold text-dark mb-1">Customer: ${escapeHtml(alloc.customer_name)}</h5>
                  <p class="small text-muted mb-2">
                    <i class="bi bi-geo-alt me-1 text-danger"></i> ${escapeHtml(alloc.address_display || alloc.address_line || 'Indiranagar')}
                  </p>
                  <div class="small fw-semibold text-success mb-3">
                    <i class="bi bi-cash-coin me-1"></i> Est. Rider Earnings: <strong>₹55.00</strong>
                  </div>
                  <button type="button" class="btn btn-success w-100 rounded-pill fw-bold py-2 shadow-xs" onclick="takeReadyFood(${alloc.order_id})" id="btnClaimOrder${alloc.order_id}" style="background:#2E6F40; border-color:#2E6F40;">
                    <i class="bi bi-handbag me-1"></i> Accept &amp; Pick Up Allocated Order &rarr;
                  </button>
                </div>
              `;
              if (otherReady.length > 0) {
                html += `
                  <div class="mt-3">
                    <div class="small fw-bold text-muted text-uppercase mb-2">
                      <i class="bi bi-list-ol me-1"></i> Upcoming Parcels in Kitchen FCFS Queue (${otherReady.length})
                    </div>
                    <div class="row g-2">
                `;
                let qRank = 2;
                otherReady.forEach(oro => {
                  html += `
                    <div class="col-md-6">
                      <div class="card border rounded-3 p-2 bg-light small">
                        <div class="d-flex justify-content-between align-items-center">
                          <span class="badge bg-secondary rounded-pill">Queue #${qRank++}</span>
                          <span class="font-mono fw-bold">#ZIP-${oro.order_id}</span>
                        </div>
                        <div class="text-truncate text-muted mt-1">Customer: ${escapeHtml(oro.customer_name)}</div>
                        <div class="text-muted" style="font-size:0.7rem;">Prepared: ${escapeHtml(oro.ready_at ? oro.ready_at.substring(11, 16) : 'Recently')}</div>
                      </div>
                    </div>
                  `;
                });
                html += `</div></div>`;
              }
              readyContainer.innerHTML = html;
            } else if (readyCount === 0) {
              readyContainer.innerHTML = `
                <div class="card border-0 rounded-4 p-4 text-center bg-white shadow-xs" id="noReadyOrdersCard">
                  <i class="bi bi-check2-circle fs-2 text-success mb-2"></i>
                  <h6 class="fw-bold text-dark mb-1">No Orders Waiting for Pickup</h6>
                  <p class="small text-muted mb-0">Kitchen orders will be automatically allocated to you in First-Come, First-Served order as soon as cooks mark them "Ready for Delivery".</p>
                </div>
              `;
            }
          }
        }
      } catch (e) {
        console.warn('Delivery sync error:', e);
      } finally {
        setTimeout(() => {
          if (icon) icon.classList.remove('sync-spinning');
        }, 400);
      }
    }

    // 4. Render Active Order Card Template in JavaScript
    function renderActiveOrderCard(order) {
      const items = order.items || [];
      let itemsHtml = items.map(it => `
        <span class="badge bg-white text-dark border p-2">
          <span class="badge ${parseInt(it.is_veg) ? 'bg-success' : 'bg-danger'} p-1 me-1">${parseInt(it.is_veg) ? 'V' : 'N'}</span>
          ${parseInt(it.quantity)}x ${escapeHtml(it.item_name)}
        </span>
      `).join('');

      const payModeBadge = (order.payment_mode === 'COD')
        ? `<span class="badge bg-warning text-dark border fw-bold"><i class="bi bi-cash me-1"></i> COD: Collect ₹${parseFloat(order.total_amount).toFixed(2)}</span>`
        : `<span class="badge bg-success bg-opacity-10 text-success border border-success small fw-bold"><i class="bi bi-check-circle me-1"></i> Paid Online (${escapeHtml(order.payment_mode || 'ONLINE')})</span>`;

      return `
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold text-dark text-uppercase small mb-0">
            <i class="bi bi-bicycle text-success me-1"></i> Active Delivery In Progress (1/1 Max)
          </h6>
          <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3 py-1 small fw-bold">
            <i class="bi bi-shield-check me-1"></i> 1 Driver 1 Delivery Policy Active
          </span>
        </div>

        <div class="active-trip-card mb-4" id="orderCard${order.order_id}">
          <div class="active-trip-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
              <span class="badge rounded-pill fw-bold px-3 py-1" style="background:#CEFFDC; color:#243E2C;">
                <i class="bi bi-geo-alt-fill me-1"></i> IN TRANSIT
              </span>
              <span class="fw-bold font-monospace fs-6 text-white">#ZIP-${order.order_id}</span>
            </div>
            <div class="text-end">
              <span class="small opacity-75">Trip Payout:</span>
              <span class="fs-5 fw-bold font-monospace text-mint ms-1" style="color:#CEFFDC;">₹55.00</span>
            </div>
          </div>

          <div class="p-3 bg-light border-bottom">
            <div class="step-timeline-container">
              <div class="step-timeline-progress" style="width: 75%;"></div>
              <div class="step-node completed">
                <div class="step-circle"><i class="bi bi-check-lg"></i></div>
                <span class="step-title">Assigned</span>
              </div>
              <div class="step-node completed">
                <div class="step-circle"><i class="bi bi-check-lg"></i></div>
                <span class="step-title">At Outlet</span>
              </div>
              <div class="step-node active">
                <div class="step-circle"><i class="fa-solid fa-motorcycle"></i></div>
                <span class="step-title">In Transit</span>
              </div>
              <div class="step-node">
                <div class="step-circle"><i class="bi bi-house-door"></i></div>
                <span class="step-title">Delivered</span>
              </div>
            </div>
          </div>

          <div class="p-4">
            <div class="d-flex gap-3 mb-3">
              <div class="text-center pt-1" style="width: 32px;">
                <div class="rounded-circle text-white d-flex align-items-center justify-content-center shadow-xs" style="width: 28px; height: 28px; font-size: 0.8rem; background-color: var(--rider-forest) !important;">
                  <i class="fa-solid fa-store"></i>
                </div>
                <div class="vr my-1" style="height: 38px; border-left: 2px dashed #94A3B8;"></div>
              </div>
              <div>
                <span class="badge bg-success bg-opacity-10 text-success fw-bold small mb-1">PICKED UP FROM</span>
                <h6 class="fw-bold text-dark mb-0">Zippy Kitchen - Indiranagar Hub</h6>
                <p class="small text-muted mb-0">#544, 100 Feet Road, HAL 2nd Stage, Bangalore</p>
              </div>
            </div>

            <div class="d-flex gap-3 mb-4">
              <div class="text-center pt-1" style="width: 32px;">
                <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center shadow-xs" style="width: 28px; height: 28px; font-size: 0.8rem;">
                  <i class="bi bi-geo-alt-fill"></i>
                </div>
              </div>
              <div class="flex-grow-1">
                <span class="badge bg-danger bg-opacity-10 text-danger fw-bold small mb-1">DELIVER TO CUSTOMER</span>
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                  <div>
                    <h6 class="fw-bold text-dark mb-0">${escapeHtml(order.customer_name || 'Customer')}</h6>
                    <p class="small text-muted mb-1">${escapeHtml(order.address_line || 'Indiranagar 100ft Road')} (PIN: ${escapeHtml(order.pincode || '560038')})</p>
                    ${order.order_notes ? `<div class="small fw-semibold text-success"><i class="bi bi-chat-left-text me-1"></i> Note: ${escapeHtml(order.order_notes)}</div>` : ''}
                  </div>
                  <div class="d-flex gap-2 flex-wrap">
                    <a href="tel:${escapeHtml(order.customer_phone || '+919876543210')}" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold flex-shrink-0">
                      <i class="bi bi-telephone-fill me-1"></i> Call Customer
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold flex-shrink-0" onclick="openSupportModal(${order.order_id})">
                      <i class="bi bi-exclamation-triangle-fill me-1"></i> Issue / SOS
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="p-3 bg-light rounded-3 mb-4 border">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="small fw-bold text-dark"><i class="bi bi-bag-check-fill text-success me-1"></i> Order Items (${items.length} items):</div>
                <div>${payModeBadge}</div>
              </div>
              <div class="d-flex flex-wrap gap-2 small">
                ${itemsHtml || '<span class="text-muted small">Standard Meal Parcel</span>'}
              </div>
            </div>

            <div class="d-flex flex-column flex-sm-row gap-2 mb-3">
              <button type="button" class="btn btn-outline-secondary rounded-pill py-2 px-3 fw-bold flex-grow-1" onclick="openGpsModal(${order.order_id}, '${escapeJs(order.customer_name)}', '${escapeJs(order.address_line || 'Indiranagar 100ft Road')}')">
                <i class="fa-solid fa-location-arrow me-1 text-primary"></i> Live Route Simulator
              </button>
              <a href="https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent((order.address_line || 'Indiranagar') + ', Bangalore, ' + (order.pincode || '560038'))}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-success rounded-pill py-2 px-3 fw-bold flex-grow-1 text-center d-flex align-items-center justify-content-center gap-1">
                <i class="bi bi-geo-alt-fill text-danger"></i> Google Maps
              </a>
              <button type="button" class="btn btn-rider-primary py-2 px-3 fw-bold flex-grow-1 shadow-sm fs-6" onclick="openOtpModal(${order.order_id}, '${escapeJs(order.delivery_otp)}', '${escapeJs(order.customer_name)}', '${escapeJs(order.payment_mode)}', '₹${parseFloat(order.total_amount).toFixed(2)}')">
                <i class="bi bi-shield-check me-1"></i> Verify OTP & Finish
              </button>
            </div>

            <div class="d-flex justify-content-between align-items-center bg-light px-3 py-2 rounded-3 border small">
              <div class="text-muted d-flex align-items-center gap-1">
                <i class="bi bi-shield-lock-fill text-success"></i>
                <span>Doorstep Handshake:</span>
                <span class="badge bg-secondary bg-opacity-10 text-secondary border small">Customer holds secret OTP</span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-2 py-0 small fw-bold" onclick="toggleDemoOtp(${order.order_id})" id="btnRevealOtp${order.order_id}">
                  <i class="bi bi-eye me-1"></i> Reveal for Demo
                </button>
                <span class="font-monospace fw-bold fs-6 text-dark bg-white px-2 py-0 rounded border d-none" id="demoOtpBox${order.order_id}">${escapeHtml(order.delivery_otp)}</span>
              </div>
            </div>
          </div>
        </div>
      `;
    }

    // 5. Asynchronous Sync Action to Claim and Pick Up Ready Food
    async function takeReadyFood(orderId) {
      const btn = document.getElementById(`btnClaimOrder${orderId}`);
      const originalText = btn ? btn.innerHTML : '';
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Claiming Food...';
      }

      try {
        const formData = new FormData();
        formData.append('action', 'claim_order');
        formData.append('order_id', orderId);
        formData.append('is_ajax', '1');
        formData.append('csrf_token', '<?= csrf_token() ?>');

        const res = await fetch('<?= DELIVERY_URL ?>/index.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData
        });
        const data = await res.json();

        if (data.success) {
          playOrderChime();
          // Instant Sync Refresh
          await syncDeliveryApp(true);
        } else {
          alert(data.message || 'Could not claim order.');
          if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
          }
        }
      } catch (err) {
        console.error('Take ready food error:', err);
        // Fallback: standard form submit
        const form = document.querySelector(`form[action*="index.php"] input[value="${orderId}"]`)?.closest('form');
        if (form) form.submit();
      }
    }
  </script>
</body>
</html>
