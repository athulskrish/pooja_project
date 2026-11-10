<?php declare(strict_types=1); ?>
  </main>

  <!-- ========================================================================
       MODAL: 86-LIST & MENU DISH AVAILABILITY (OUT OF STOCK TOGGLE)
       ======================================================================== -->
  <div class="modal fade" id="modal86List" tabindex="-1" aria-labelledby="modal86ListLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content modal-content-lush">
        <div class="modal-header modal-header-lush">
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle text-white p-2 d-inline-flex align-items-center justify-content-center shadow-sm" style="width:40px; height:40px; background:var(--lush-forest);">
              <i class="fa-solid fa-boxes-stacked"></i>
            </div>
            <div>
              <h5 class="modal-title font-outfit fw-bold mb-0" id="modal86ListLabel" style="color:var(--lush-pine);">Kitchen 86-List (Inventory Availability)</h5>
              <span class="text-muted small">Instantly toggle dish stock state directly from the expeditor display</span>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        
        <!-- Live Search within 86-List Modal -->
        <div class="px-4 pt-3 pb-2 bg-white border-bottom" style="border-color:var(--lush-border) !important;">
          <div class="position-relative">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" id="modal86SearchInput" class="search-input-lush w-100" placeholder="Search dishes in menu..." onkeyup="filter86ModalTable()">
          </div>
        </div>

        <div class="modal-body p-3 p-md-4">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="table86List">
              <thead class="text-muted small text-uppercase" style="border-bottom: 2px solid var(--lush-border);">
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
                  <tr class="dish-86-row" data-search="<?= strtolower(e($dish['item_name'] . ' ' . $dish['category_name'])) ?>">
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <span class="<?= $dish['is_veg'] ? 'badge-veg-icon' : 'badge-nonveg-icon' ?>"></span>
                        <strong style="color:var(--lush-pine);"><?= e($dish['item_name']) ?></strong>
                      </div>
                    </td>
                    <td class="text-muted small"><?= e($dish['category_name']) ?></td>
                    <td>
                      <span class="font-mono fw-bold" style="color:var(--lush-pine);">
                        <?= (int)$dish['stock_quantity'] ?> portions
                      </span>
                    </td>
                    <td>
                      <span id="stockLabel_<?= $dish['item_id'] ?>" class="ticket-tag-pill <?= $dish['is_available'] ? 'tag-priority-normal' : 'tag-priority-critical' ?>">
                        <i class="bi <?= $dish['is_available'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                        <?= $dish['is_available'] ? 'IN STOCK' : '86 / SOLD OUT' ?>
                      </span>
                    </td>
                    <td class="text-end">
                      <button type="button" 
                              id="btnStockToggle_<?= $dish['item_id'] ?>"
                              class="btn btn-sm <?= $dish['is_available'] ? 'btn-kds-pill' : 'btn-kds-primary' ?> px-3" 
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
        <div class="modal-footer modal-footer-lush">
          <button type="button" class="btn btn-kds-primary px-4" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap Bundle Script -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // 1. Digital Station Clock
    function updateClock() {
      const now = new Date();
      const timeStr = now.toLocaleTimeString('en-US', { hour12: true });
      const clockEl = document.getElementById('kdsLiveClock');
      if (clockEl) clockEl.innerText = timeStr;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // 2. Fullscreen Toggle for Kitchen Kiosk / Tablets
    function toggleKdsFullscreen() {
      const icon = document.getElementById('fullscreenIcon');
      if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().then(() => {
          if (icon) icon.className = 'bi bi-fullscreen-exit';
        }).catch(err => {
          console.warn('Fullscreen access:', err);
        });
      } else {
        if (document.exitFullscreen) {
          document.exitFullscreen().then(() => {
            if (icon) icon.className = 'bi bi-fullscreen';
          }).catch(err => console.warn(err));
        }
      }
    }

    // 3. Acoustic Harmonious Bell Chime (Web Audio API - Calming Major Triad)
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

        // Note 1: Warm Root (523.25 Hz - C5)
        const osc1 = audioContext.createOscillator();
        const gain1 = audioContext.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(523.25, now);
        gain1.gain.setValueAtTime(0.20, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.9);
        osc1.connect(gain1);
        gain1.connect(audioContext.destination);
        osc1.start(now);
        osc1.stop(now + 0.9);

        // Note 2: Melodic Third (659.25 Hz - E5)
        const osc2 = audioContext.createOscillator();
        const gain2 = audioContext.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(659.25, now + 0.12);
        gain2.gain.setValueAtTime(0.22, now + 0.12);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 1.1);
        osc2.connect(gain2);
        gain2.connect(audioContext.destination);
        osc2.start(now + 0.12);
        osc2.stop(now + 1.1);

        // Note 3: Shimmering Fifth (783.99 Hz - G5)
        const osc3 = audioContext.createOscillator();
        const gain3 = audioContext.createGain();
        osc3.type = 'triangle';
        osc3.frequency.setValueAtTime(783.99, now + 0.24);
        gain3.gain.setValueAtTime(0.24, now + 0.24);
        gain3.gain.exponentialRampToValueAtTime(0.001, now + 1.3);
        osc3.connect(gain3);
        gain3.connect(audioContext.destination);
        osc3.start(now + 0.24);
        osc3.stop(now + 1.3);
      } catch (e) {
        console.warn('Audio chime error:', e);
      }
    }

    function toggleAudioChime() {
      audioEnabled = !audioEnabled;
      const icon = document.getElementById('audioIcon');
      const text = document.getElementById('audioText');
      if (icon) {
        icon.className = audioEnabled ? 'bi bi-bell-fill' : 'bi bi-bell-slash-fill';
        icon.style.color = audioEnabled ? 'var(--lush-forest)' : 'var(--lush-text-light)';
      }
      if (text) {
        text.innerText = audioEnabled ? 'SOUND: ON' : 'SOUND: OFF';
      }
      if (audioEnabled) {
        playKitchenChime();
      }
    }

    // 4. Auto Refresh Controller
    let autoRefreshActive = true;
    let refreshTimerSeconds = 6;
    let refreshInterval = null;

    function startAutoRefresh() {
      refreshInterval = setInterval(() => {
        if (!autoRefreshActive) return;
        refreshTimerSeconds--;
        const refreshText = document.getElementById('refreshText');
        if (refreshText) {
          refreshText.innerText = `SYNC (${refreshTimerSeconds}s)`;
        }

        if (refreshTimerSeconds <= 0) {
          refreshTimerSeconds = (typeof kdsSseConnected !== 'undefined' && kdsSseConnected) ? 20 : 6;
          pollKitchenOrders();
        }
      }, 1000);
    }
    startAutoRefresh();

    function toggleAutoRefresh() {
      autoRefreshActive = !autoRefreshActive;
      const btn = document.getElementById('btnRefreshToggle');
      const refreshText = document.getElementById('refreshText');
      if (autoRefreshActive) {
        if (btn) btn.className = 'btn btn-sm btn-kds-pill';
        if (refreshText) refreshText.innerText = `SYNC (${refreshTimerSeconds}s)`;
      } else {
        if (btn) btn.className = 'btn btn-sm btn-kds-pill text-muted';
        if (refreshText) refreshText.innerText = 'PAUSED';
      }
    }

    // 5. Live Polling for New Orders & Real-time State Synchronization
    let lastKnownIncomingId = <?= !empty($laneNew) ? max(array_column($laneNew, 'order_id')) : 0 ?>;
    let lastKnownReadyCount = <?= (int)($countReady ?? 0) ?>;
    let lastKnownStateHash = <?= jsJson($kdsInitialStateHash ?? '') ?>;
    const kitchenCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    async function pollKitchenOrders() {
      try {
        const res = await fetch('<?= KITCHEN_URL ?>/ajax/poll-orders.php');
        const data = await res.json();
        if (data.success) {
          // Update KPI counts smoothly
          const statNew = document.getElementById('statNew');
          if (statNew) statNew.innerText = data.counts.new;
          const statCooking = document.getElementById('statCooking');
          if (statCooking) statCooking.innerText = data.counts.cooking;
          const statReady = document.getElementById('statReady');
          if (statReady) statReady.innerText = data.counts.ready;
          const statHandedOver = document.getElementById('statHandedOver');
          if (statHandedOver) statHandedOver.innerText = data.counts.handed_over;
          const statActiveTotal = document.getElementById('statActiveTotal');
          if (statActiveTotal) statActiveTotal.innerText = data.counts.total_active;
          const tabCountTotal = document.getElementById('tabCountTotal');
          if (tabCountTotal) tabCountTotal.innerText = data.counts.total_active;

          const laneNewCountBadge = document.getElementById('laneNewCountBadge');
          if (laneNewCountBadge) laneNewCountBadge.innerText = data.counts.new;
          const laneCookingCountBadge = document.getElementById('laneCookingCountBadge');
          if (laneCookingCountBadge) laneCookingCountBadge.innerText = data.counts.cooking;
          const laneReadyCountBadge = document.getElementById('laneReadyCountBadge');
          if (laneReadyCountBadge) laneReadyCountBadge.innerText = data.counts.ready;

          const tabCountNew = document.getElementById('tabCountNew');
          if (tabCountNew) tabCountNew.innerText = data.counts.new;
          const tabCountCooking = document.getElementById('tabCountCooking');
          if (tabCountCooking) tabCountCooking.innerText = data.counts.cooking;
          const tabCountReady = document.getElementById('tabCountReady');
          if (tabCountReady) tabCountReady.innerText = data.counts.ready;

          // Check if system state changed (e.g. driver delivered dish, order claimed, or new order marked ready)
          if (data.state_hash && lastKnownStateHash && data.state_hash !== lastKnownStateHash) {
            lastKnownStateHash = data.state_hash;
            if (window._kdsLocalToggleTs && (Date.now() - window._kdsLocalToggleTs) < 4000) {
              return;
            }
            console.log('KDS state changed! Syncing atelier view...');
            if (data.latest_incoming_id > lastKnownIncomingId) {
              lastKnownIncomingId = data.latest_incoming_id;
              playKitchenChime();
              showOrderBanner(data.latest_incoming_id);
            }
            setTimeout(() => { window.location.reload(); }, 600);
            return;
          }
          if (!lastKnownStateHash && data.state_hash) {
            lastKnownStateHash = data.state_hash;
          }

          // Check if brand new unaccepted order arrived
          if (data.latest_incoming_id > lastKnownIncomingId) {
            lastKnownIncomingId = data.latest_incoming_id;
            playKitchenChime();
            showOrderBanner(data.latest_incoming_id);
            setTimeout(() => { window.location.reload(); }, 1500);
          } else if (lastKnownReadyCount !== -1 && data.counts.ready !== lastKnownReadyCount) {
            // Courier took ready food or kitchen marked food ready: sync swimlanes
            lastKnownReadyCount = data.counts.ready;
            setTimeout(() => { window.location.reload(); }, 1000);
          }
          lastKnownReadyCount = data.counts.ready;
        }
      } catch (err) {
        console.error('Polling error:', err);
      }
    }

    function showOrderBanner(orderId) {
      const banner = document.getElementById('newOrderBanner');
      if (!banner) return;
      document.getElementById('newOrderBannerTitle').innerText = `NEW TICKET #ZIP-${orderId} ARRIVED`;
      document.getElementById('newOrderBannerDesc').innerText = 'Customer order confirmed. Ready for kitchen stove allocation.';
      banner.classList.remove('d-none');
      banner.classList.add('d-flex');
    }

    function dismissOrderBanner() {
      const banner = document.getElementById('newOrderBanner');
      if (!banner) return;
      banner.classList.remove('d-flex');
      banner.classList.add('d-none');
    }

    // 6. AJAX Order Transition Handler
    async function handleOrderTransition(event, orderId, targetStatus) {
      event.preventDefault();
      const form = event.target;
      const formData = new FormData(form);

      const submitBtn = form.querySelector('button[type="submit"]');
      const originalHtml = submitBtn.innerHTML;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Updating...';
      submitBtn.disabled = true;

      try {
        const res = await fetch('<?= KITCHEN_URL ?>/ajax/update-status.php', {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': kitchenCsrfToken },
          body: formData
        });
        const result = await res.json();

        if (res.ok && result.success) {
          playKitchenChime();
          window.location.reload();
        } else {
          alert('Notice: ' + (result.message || result.error || 'Update failed.'));
          submitBtn.innerHTML = originalHtml;
          submitBtn.disabled = false;
        }
      } catch (e) {
        form.submit();
      }
    }

    // 7. Quick 86-List Toggle
    async function toggleStockItem(itemId, targetState) {
      const btn = document.getElementById('btnStockToggle_' + itemId);
      const label = document.getElementById('stockLabel_' + itemId);
      if (btn) btn.disabled = true;

      try {
        const res = await fetch('<?= KITCHEN_URL ?>/ajax/toggle-stock.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': kitchenCsrfToken
          },
          body: JSON.stringify({ item_id: itemId, is_available: targetState, csrf_token: kitchenCsrfToken })
        });
        const data = await res.json();

        if (data.success) {
          if (data.is_available) {
            if (label) {
              label.className = 'ticket-tag-pill tag-priority-normal';
              label.innerHTML = '<i class="bi bi-check-circle-fill"></i> IN STOCK';
            }
            if (btn) {
              btn.className = 'btn btn-sm btn-kds-pill px-3';
              btn.innerText = 'Mark 86 (Out of Stock)';
              btn.setAttribute('onclick', `toggleStockItem(${itemId}, 0)`);
            }
          } else {
            if (label) {
              label.className = 'ticket-tag-pill tag-priority-critical';
              label.innerHTML = '<i class="bi bi-x-circle-fill"></i> 86 / SOLD OUT';
            }
            if (btn) {
              btn.className = 'btn btn-sm btn-kds-primary px-3';
              btn.innerText = 'Mark In Stock';
              btn.setAttribute('onclick', `toggleStockItem(${itemId}, 1)`);
            }
          }
        } else {
          alert('Update failed: ' + data.message);
        }
      } catch (err) {
        console.error(err);
      } finally {
        if (btn) btn.disabled = false;
      }
    }

    // 8. Filter 86-List Modal Search
    function filter86ModalTable() {
      const q = document.getElementById('modal86SearchInput').value.toLowerCase().trim();
      const rows = document.querySelectorAll('.dish-86-row');
      rows.forEach(r => {
        const text = r.getAttribute('data-search') || '';
        r.style.display = (!q || text.includes(q)) ? '' : 'none';
      });
    }

    // 9. Open Printable KOT
    function openKOT(orderId) {
      const url = '<?= KITCHEN_URL ?>/kot.php?order_id=' + orderId;
      window.open(url, 'KOT_Slip', 'width=450,height=650,toolbar=no,menubar=no,location=no,status=no');
    }

    // 10. Filter Tickets by Search & Kitchen Station
    let activeKitchenStation = 'all';

    function filterKitchenStation(station, btnEl) {
      activeKitchenStation = (station || 'all').toLowerCase();
      document.querySelectorAll('.station-pill-btn').forEach(b => {
        b.classList.remove('active');
        b.style.background = '';
        b.style.color = '';
        b.style.borderColor = '';
        if (!b.classList.contains('btn-outline-success')) {
          b.classList.add('btn-outline-success');
        }
      });
      if (btnEl) {
        btnEl.classList.add('active');
        btnEl.classList.remove('btn-outline-success');
        btnEl.style.background = 'var(--lush-forest)';
        btnEl.style.color = '#fff';
        btnEl.style.borderColor = 'var(--lush-forest)';
      }
      filterTickets();
    }

    function filterTickets() {
      const searchInput = document.getElementById('kdsSearchInput');
      const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
      const tickets = document.querySelectorAll('.ticket-item');

      tickets.forEach(card => {
        const text = (card.getAttribute('data-search') || '').toLowerCase();
        const stations = (card.getAttribute('data-stations') || '').split('|').map(s => s.trim()).filter(Boolean);
        const matchesSearch = !query || text.includes(query);
        const matchesStation = activeKitchenStation === 'all' || stations.includes(activeKitchenStation);
        card.style.display = (matchesSearch && matchesStation) ? 'block' : 'none';
      });
    }

    // 10b. Toggle Individual Dish Preparation State (`order_items.is_prepped`)
    async function toggleDishPrepped(orderItemId, orderId, checkboxEl) {
      const isPrepped = checkboxEl && checkboxEl.checked ? 1 : 0;
      const lbl = checkboxEl ? checkboxEl.closest('.form-check')?.querySelector('label') : null;
      if (lbl) lbl.style.textDecoration = isPrepped ? 'line-through' : '';

      try {
        const res = await fetch('<?= KITCHEN_URL ?>/ajax/toggle-item-prepped.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': kitchenCsrfToken
          },
          body: JSON.stringify({
            order_item_id: orderItemId,
            is_prepped: isPrepped,
            csrf_token: kitchenCsrfToken
          })
        });
        const data = await res.json();
        if (data && data.success) {
          const badge = document.getElementById(`prepCountBadge_${orderId}`);
          if (badge) {
            badge.textContent = `${data.prepped_count}/${data.total_count} prepped`;
          }
          window._kdsLocalToggleTs = Date.now();
        }
      } catch (e) {
        console.warn('Unable to sync dish prep state:', e);
      }
    }

    // 10c. True Server-Sent Events (SSE) Push Stream for KDS
    let kdsSseConnected = false;
    if (typeof EventSource !== 'undefined') {
      try {
        const kdsSse = new EventSource('<?= BASE_URL ?>/api/sse-stream.php?portal=kitchen');
        kdsSse.addEventListener('connected', () => { kdsSseConnected = true; });
        kdsSse.addEventListener('kds_update', (evt) => {
          try {
            const payload = JSON.parse(evt.data || '{}');
            if (payload.hash && lastKnownStateHash && payload.hash !== lastKnownStateHash) {
              if (window._kdsLocalToggleTs && (Date.now() - window._kdsLocalToggleTs) < 4000) {
                lastKnownStateHash = payload.hash;
                return;
              }
              pollKitchenOrders();
            }
          } catch (err) {
            pollKitchenOrders();
          }
        });
        kdsSse.onerror = () => { kdsSseConnected = false; };
      } catch (err) {
        kdsSseConnected = false;
      }
    }

    // 11. Quick Show Chef Salary & Payslips Tab
    function showSalaryTab() {
      const btn = document.getElementById('tab-salary-btn');
      if (btn) {
        bootstrap.Tab.getOrCreateInstance(btn).show();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    }

    // 12. Quick Show Order History Tab
    function showHistoryTab() {
      const btn = document.getElementById('tab-history-btn');
      if (btn) {
        bootstrap.Tab.getOrCreateInstance(btn).show();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    }
  </script>
</body>
</html>
