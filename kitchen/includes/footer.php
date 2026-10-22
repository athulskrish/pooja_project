  </main>

  <!-- ========================================================================
       MODAL: 86-LIST & MENU DISH AVAILABILITY (OUT OF STOCK TOGGLE)
       ======================================================================== -->
  <div class="modal fade" id="modal86List" tabindex="-1" aria-labelledby="modal86ListLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content modal-content-noir">
        <div class="modal-header modal-header-noir">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle bg-black text-white p-2 d-inline-flex align-items-center justify-content-center" style="width:36px; height:36px;">
              <i class="fa-solid fa-boxes-stacked"></i>
            </div>
            <div>
              <h5 class="modal-title font-outfit fw-bold text-dark mb-0" id="modal86ListLabel">Kitchen 86-List (Inventory Availability)</h5>
              <span class="text-muted small">Instantly toggle dish stock state directly from the expeditor display</span>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="text-muted small text-uppercase" style="border-bottom: 2px solid #000000;">
                <tr>
                  <th>Dish Name</th>
                  <th>Category</th>
                  <th>Stock Reserve</th>
                  <th>Status</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($allMenuItems as $dish): ?>
                  <tr>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <span class="<?= $dish['is_veg'] ? 'monochrome-veg' : 'monochrome-nonveg' ?>"></span>
                        <strong class="text-dark"><?= e($dish['item_name']) ?></strong>
                      </div>
                    </td>
                    <td class="text-muted small"><?= e($dish['category_name']) ?></td>
                    <td>
                      <span class="font-mono text-dark fw-bold">
                        <?= (int)$dish['stock_quantity'] ?> portions
                      </span>
                    </td>
                    <td>
                      <span id="stockLabel_<?= $dish['item_id'] ?>" class="ticket-tag-pill <?= $dish['is_available'] ? 'ticket-tag-dark' : 'ticket-tag-outline' ?>">
                        <?= $dish['is_available'] ? 'IN STOCK' : '86 / SOLD OUT' ?>
                      </span>
                    </td>
                    <td class="text-end">
                      <button type="button" 
                              id="btnStockToggle_<?= $dish['item_id'] ?>"
                              class="btn btn-sm <?= $dish['is_available'] ? 'btn-noir-outline' : 'btn-noir-primary' ?> rounded-pill px-3" 
                              onclick="toggleStockItem(<?= (int)$dish['item_id'] ?>, <?= $dish['is_available'] ? 0 : 1 ?>)">
                        <?= $dish['is_available'] ? 'Mark 86 (Out of Stock)' : 'Mark In Stock' ?>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer modal-footer-noir">
          <button type="button" class="btn btn-noir-primary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // 1. Digital Clock
    function updateClock() {
      const now = new Date();
      const timeStr = now.toLocaleTimeString('en-US', { hour12: true });
      document.getElementById('kdsLiveClock').innerText = timeStr;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // 2. Synthesizer Kitchen Bell Chime (Web Audio API - Pure & Elegant)
    let audioContext = null;
    let audioEnabled = true;

    function initAudio() {
      if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
      }
    }

    function playKitchenChime() {
      if (!audioEnabled) return;
      try {
        initAudio();
        if (audioContext.state === 'suspended') {
          audioContext.resume();
        }

        const now = audioContext.currentTime;

        // Note 1: High crisp ding (880 Hz - A5)
        const osc1 = audioContext.createOscillator();
        const gain1 = audioContext.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(880, now);
        gain1.gain.setValueAtTime(0.25, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.8);
        osc1.connect(gain1);
        gain1.connect(audioContext.destination);
        osc1.start(now);
        osc1.stop(now + 0.8);

        // Note 2: Harmonic pleasant chord (1320 Hz - E6)
        const osc2 = audioContext.createOscillator();
        const gain2 = audioContext.createGain();
        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(1320, now + 0.15);
        gain2.gain.setValueAtTime(0.3, now + 0.15);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 1.2);
        osc2.connect(gain2);
        gain2.connect(audioContext.destination);
        osc2.start(now + 0.15);
        osc2.stop(now + 1.2);
      } catch (e) {
        console.warn('Audio chime error:', e);
      }
    }

    function toggleAudioChime() {
      audioEnabled = !audioEnabled;
      document.getElementById('audioIcon').className = audioEnabled ? 'bi bi-bell-fill me-1' : 'bi bi-bell-slash-fill me-1';
      document.getElementById('audioText').innerText = audioEnabled ? 'SOUND: ON' : 'SOUND: OFF';
      if (audioEnabled) {
        playKitchenChime();
      }
    }

    // 3. Auto Refresh Controller
    let autoRefreshActive = true;
    let refreshTimerSeconds = 12;
    let refreshInterval = null;

    function startAutoRefresh() {
      refreshInterval = setInterval(() => {
        if (!autoRefreshActive) return;
        refreshTimerSeconds--;
        document.getElementById('refreshText').innerText = `SYNC (${refreshTimerSeconds}s)`;

        if (refreshTimerSeconds <= 0) {
          refreshTimerSeconds = 12;
          pollKitchenOrders();
        }
      }, 1000);
    }
    startAutoRefresh();

    function toggleAutoRefresh() {
      autoRefreshActive = !autoRefreshActive;
      const btn = document.getElementById('btnRefreshToggle');
      if (autoRefreshActive) {
        btn.className = 'btn btn-sm btn-noir-outline rounded-pill px-3';
        document.getElementById('refreshText').innerText = `SYNC (${refreshTimerSeconds}s)`;
      } else {
        btn.className = 'btn btn-sm bg-light text-muted border rounded-pill px-3';
        document.getElementById('refreshText').innerText = 'PAUSED';
      }
    }

    // 4. Live Polling for New Orders
    let lastKnownIncomingId = <?= !empty($laneNew) ? max(array_column($laneNew, 'order_id')) : 0 ?>;

    async function pollKitchenOrders() {
      try {
        const res = await fetch('<?= KITCHEN_URL ?>/ajax/poll-orders.php');
        const data = await res.json();
        if (data.success) {
          // Update KPI counts
          document.getElementById('statNew').innerText = data.counts.new;
          document.getElementById('statCooking').innerText = data.counts.cooking;
          document.getElementById('statReady').innerText = data.counts.ready;
          document.getElementById('statHandedOver').innerText = data.counts.handed_over;

          document.getElementById('laneNewCountBadge').innerText = data.counts.new;
          document.getElementById('laneCookingCountBadge').innerText = data.counts.cooking;
          document.getElementById('laneReadyCountBadge').innerText = data.counts.ready;

          const tabCountNew = document.getElementById('tabCountNew');
          if (tabCountNew) tabCountNew.innerText = data.counts.new;
          const tabCountCooking = document.getElementById('tabCountCooking');
          if (tabCountCooking) tabCountCooking.innerText = data.counts.cooking;
          const tabCountReady = document.getElementById('tabCountReady');
          if (tabCountReady) tabCountReady.innerText = data.counts.ready;

          // Check if brand new unaccepted order arrived
          if (data.latest_incoming_id > lastKnownIncomingId) {
            lastKnownIncomingId = data.latest_incoming_id;
            playKitchenChime();
            showOrderBanner(data.latest_incoming_id);
            setTimeout(() => { window.location.reload(); }, 2200);
          }
        }
      } catch (err) {
        console.error('Polling error:', err);
      }
    }

    function showOrderBanner(orderId) {
      const banner = document.getElementById('newOrderBanner');
      document.getElementById('newOrderBannerTitle').innerText = `NEW TICKET #ZIP-${orderId} ARRIVED`;
      document.getElementById('newOrderBannerDesc').innerText = 'Customer order confirmed. Ready for kitchen stove allocation.';
      banner.classList.remove('d-none');
      banner.classList.add('d-flex');
    }

    function dismissOrderBanner() {
      const banner = document.getElementById('newOrderBanner');
      banner.classList.remove('d-flex');
      banner.classList.add('d-none');
    }

    // 5. AJAX Order Transition Handler
    async function handleOrderTransition(event, orderId, targetStatus) {
      event.preventDefault();
      const form = event.target;
      const formData = new FormData(form);

      const submitBtn = form.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
      submitBtn.disabled = true;

      try {
        const res = await fetch('<?= KITCHEN_URL ?>/ajax/update-status.php', {
          method: 'POST',
          body: formData
        });
        const result = await res.json();

        if (result.success) {
          playKitchenChime();
          window.location.reload();
        } else {
          alert('Notice: ' + result.message);
          submitBtn.innerHTML = originalText;
          submitBtn.disabled = false;
        }
      } catch (e) {
        form.submit();
      }
    }

    // 6. Quick 86-List Toggle
    async function toggleStockItem(itemId, targetState) {
      const btn = document.getElementById('btnStockToggle_' + itemId);
      const label = document.getElementById('stockLabel_' + itemId);
      btn.disabled = true;

      try {
        const res = await fetch('<?= KITCHEN_URL ?>/ajax/toggle-stock.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ item_id: itemId, is_available: targetState })
        });
        const data = await res.json();

        if (data.success) {
          if (data.is_available) {
            label.className = 'ticket-tag-pill ticket-tag-dark';
            label.innerText = 'IN STOCK';
            btn.className = 'btn btn-sm btn-noir-outline rounded-pill px-3';
            btn.innerText = 'Mark 86 (Out of Stock)';
            btn.setAttribute('onclick', `toggleStockItem(${itemId}, 0)`);
          } else {
            label.className = 'ticket-tag-pill ticket-tag-outline';
            label.innerText = '86 / SOLD OUT';
            btn.className = 'btn btn-sm btn-noir-primary rounded-pill px-3';
            btn.innerText = 'Mark In Stock';
            btn.setAttribute('onclick', `toggleStockItem(${itemId}, 1)`);
          }
        } else {
          alert('Update failed: ' + data.message);
        }
      } catch (err) {
        console.error(err);
      } finally {
        btn.disabled = false;
      }
    }

    // 7. Open Printable KOT
    function openKOT(orderId) {
      const url = '<?= KITCHEN_URL ?>/kot.php?order_id=' + orderId;
      window.open(url, 'KOT_Slip', 'width=450,height=650,toolbar=no,menubar=no,location=no,status=no');
    }

    // 8. Filter Tickets by Search
    function filterTickets() {
      const query = document.getElementById('kdsSearchInput').value.toLowerCase().trim();
      const tickets = document.querySelectorAll('.ticket-item');

      tickets.forEach(card => {
        const text = card.getAttribute('data-search') || '';
        if (text.includes(query)) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    }
  </script>
</body>
</html>
