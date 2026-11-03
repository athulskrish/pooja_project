<?php
/**
 * Admin Console Footer
 * Zippy - Online Food Ordering Platform
 */
?>
      </div><!-- /.admin-content -->
    </div><!-- /.admin-main -->
  </div><!-- /.admin-layout -->

  <!-- Material 3 Mobile Bottom Navigation Bar (< 992px) -->
  <nav class="m3-mobile-bottom-nav d-lg-none" aria-label="Mobile Bottom Navigation">
    <a href="<?= ADMIN_URL ?>/index.php" class="m3-bottom-item <?= ($activePage === 'dashboard') ? 'active' : '' ?>">
      <div class="m3-bottom-icon-pill">
        <i class="bi bi-speedometer2"></i>
      </div>
      <span class="m3-bottom-label">Dashboard</span>
    </a>
    <a href="<?= ADMIN_URL ?>/orders.php" class="m3-bottom-item <?= ($activePage === 'orders') ? 'active' : '' ?>">
      <div class="m3-bottom-icon-pill">
        <i class="bi bi-receipt"></i>
      </div>
      <span class="m3-bottom-label">Orders</span>
    </a>
    <a href="<?= ADMIN_URL ?>/kitchen-view.php" class="m3-bottom-item <?= ($activePage === 'kitchen_view') ? 'active' : '' ?>">
      <div class="m3-bottom-icon-pill">
        <i class="fa-solid fa-users-gear"></i>
      </div>
      <span class="m3-bottom-label">Kitchen</span>
    </a>
    <a href="<?= ADMIN_URL ?>/delivery-view.php" class="m3-bottom-item <?= ($activePage === 'delivery_view') ? 'active' : '' ?>">
      <div class="m3-bottom-icon-pill">
        <i class="bi bi-bicycle"></i>
      </div>
      <span class="m3-bottom-label">Delivery</span>
    </a>
    <button type="button" class="m3-bottom-item" onclick="toggleSidebar()" aria-label="Open Navigation Drawer">
      <div class="m3-bottom-icon-pill">
        <i class="bi bi-grid-fill"></i>
      </div>
      <span class="m3-bottom-label">Menu</span>
    </button>
  </nav>

  <!-- Bootstrap 5.3.3 JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Admin Interactive Scripts -->
  <script>
    // Responsive sidebar toggle for mobile drawer
    const toggleBtn = document.getElementById('adminSidebarToggle');
    const sidebar = document.querySelector('.admin-sidebar');
    const backdrop = document.getElementById('adminSidebarBackdrop');
    
    function toggleSidebar() {
      if (sidebar) {
        sidebar.classList.toggle('show-mobile');
        if (backdrop) backdrop.classList.toggle('show');
      }
    }
    
    if (toggleBtn) {
      toggleBtn.addEventListener('click', toggleSidebar);
    }
    if (backdrop) {
      backdrop.addEventListener('click', toggleSidebar);
    }

    // Helper toast notification
    function showAdminToast(message, type = 'success') {
      const icon = type === 'success' ? 'bi-check2-circle text-success' : (type === 'danger' ? 'bi-exclamation-triangle text-danger' : 'bi-info-circle text-info');
      const toast = document.createElement('div');
      toast.className = 'position-fixed bottom-0 end-0 m-3 p-3 bg-dark text-white rounded-4 shadow-lg border border-secondary border-opacity-25 d-flex align-items-center gap-2';
      toast.style.zIndex = '99999';
      toast.innerHTML = `<i class="bi ${icon} fs-5"></i> <div>${message}</div>`;
      document.body.appendChild(toast);
      setTimeout(() => {
        toast.style.transition = 'opacity 0.4s ease';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 400);
      }, 3500);
    }
  </script>

  <!-- ===========================================================================
       UNIVERSAL COMMAND PALETTE (CTRL + K)
       =========================================================================== -->
  <div class="modal fade command-palette-modal" id="commandPaletteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 620px;">
      <div class="modal-content border-0 shadow-lg overflow-hidden">
        
        <!-- Search Bar Header -->
        <div class="p-3 px-4 border-bottom d-flex align-items-center gap-3 bg-white">
          <i class="bi bi-search text-forest fs-5"></i>
          <input type="text" id="paletteSearchInput" class="command-palette-input w-100 fs-6 text-dark" placeholder="Search orders, dishes, couriers, or jump to any page..." autocomplete="off">
          <span class="badge bg-light text-muted border font-monospace px-2 py-1" style="font-size:0.7rem;">ESC</span>
        </div>

        <!-- Filter Chips -->
        <div class="px-4 py-2 border-bottom bg-light bg-opacity-50 d-flex align-items-center gap-2 overflow-x-auto">
          <span class="text-muted small fw-semibold" style="font-size:0.75rem;">Filter:</span>
          <button type="button" class="btn btn-xs rounded-pill px-2 py-0 fw-bold btn-success palette-filter-chip active" data-filter="ALL" style="font-size:0.72rem; background:#2E6F40;">All</button>
          <button type="button" class="btn btn-xs rounded-pill px-2 py-0 fw-bold btn-light border palette-filter-chip" data-filter="Navigation" style="font-size:0.72rem;">Pages</button>
          <button type="button" class="btn btn-xs rounded-pill px-2 py-0 fw-bold btn-light border palette-filter-chip" data-filter="Orders" style="font-size:0.72rem;">Orders</button>
          <button type="button" class="btn btn-xs rounded-pill px-2 py-0 fw-bold btn-light border palette-filter-chip" data-filter="Dishes" style="font-size:0.72rem;">Dishes</button>
          <button type="button" class="btn btn-xs rounded-pill px-2 py-0 fw-bold btn-light border palette-filter-chip" data-filter="Staff & Couriers" style="font-size:0.72rem;">Couriers</button>
        </div>

        <!-- Results Container -->
        <div class="p-3" id="paletteResultsContainer" style="max-height: 380px; overflow-y: auto;">
          <div class="text-center py-4 text-muted small">
            <div class="spinner-border spinner-border-sm text-forest mb-2" role="status"></div>
            <div>Loading command directory...</div>
          </div>
        </div>

        <!-- Footer Shortcuts -->
        <div class="p-2 px-4 border-top bg-light d-flex align-items-center justify-content-between text-muted" style="font-size:0.72rem;">
          <div class="d-flex align-items-center gap-3">
            <span><kbd class="bg-white border px-1">↑</kbd> <kbd class="bg-white border px-1">↓</kbd> to navigate</span>
            <span><kbd class="bg-white border px-1">↵</kbd> to select</span>
            <span><kbd class="bg-white border px-1">ESC</kbd> to close</span>
          </div>
          <span class="text-forest fw-semibold"><i class="bi bi-lightning-charge-fill me-1"></i>Zippy Quick Actions</span>
        </div>

      </div>
    </div>
  </div>

  <script>
  let paletteModalInstance = null;
  let paletteActiveIndex = 0;
  let paletteResults = [];
  let paletteFilter = 'ALL';
  let paletteDebounceTimer = null;

  function openCommandPalette() {
    const el = document.getElementById('commandPaletteModal');
    if (!el) return;
    if (!paletteModalInstance && typeof bootstrap !== 'undefined') {
      paletteModalInstance = new bootstrap.Modal(el);
    }
    paletteModalInstance.show();
    setTimeout(() => {
      const input = document.getElementById('paletteSearchInput');
      if (input) {
        input.value = '';
        input.focus();
        fetchPaletteResults('');
      }
    }, 200);
  }

  // Keyboard shortcut listener: Ctrl+K, Cmd+K, or slash (/)
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      openCommandPalette();
    } else if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
      e.preventDefault();
      openCommandPalette();
    }
  });

  // Palette input listener
  document.getElementById('paletteSearchInput')?.addEventListener('input', (e) => {
    clearTimeout(paletteDebounceTimer);
    const q = e.target.value;
    paletteDebounceTimer = setTimeout(() => {
      fetchPaletteResults(q);
    }, 180);
  });

  // Filter chips listener
  document.querySelectorAll('.palette-filter-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('.palette-filter-chip').forEach(c => {
        c.classList.remove('btn-success', 'active');
        c.classList.add('btn-light', 'border');
        c.style.background = '';
      });
      chip.classList.add('btn-success', 'active');
      chip.classList.remove('btn-light', 'border');
      chip.style.background = '#2E6F40';
      paletteFilter = chip.getAttribute('data-filter') || 'ALL';
      renderPaletteItems();
    });
  });

  // Up/Down/Enter keyboard navigation inside palette
  document.getElementById('commandPaletteModal')?.addEventListener('keydown', (e) => {
    const items = document.querySelectorAll('.command-palette-item');
    if (!items.length) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      paletteActiveIndex = (paletteActiveIndex + 1) % items.length;
      highlightPaletteItem(items);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      paletteActiveIndex = (paletteActiveIndex - 1 + items.length) % items.length;
      highlightPaletteItem(items);
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (items[paletteActiveIndex]) {
        items[paletteActiveIndex].click();
      }
    }
  });

  function highlightPaletteItem(items) {
    items.forEach((it, idx) => {
      if (idx === paletteActiveIndex) {
        it.classList.add('active');
        it.scrollIntoView({ block: 'nearest' });
      } else {
        it.classList.remove('active');
      }
    });
  }

  async function fetchPaletteResults(q) {
    try {
      const res = await fetch('<?= ADMIN_URL ?>/ajax/command-search.php?q=' + encodeURIComponent(q));
      if (!res.ok) return;
      const data = await res.json();
      if (data.success) {
        paletteResults = data.results || [];
        renderPaletteItems();
      }
    } catch (err) {
      console.error('Palette search error:', err);
    }
  }

  function renderPaletteItems() {
    const container = document.getElementById('paletteResultsContainer');
    if (!container) return;

    let filtered = paletteResults;
    if (paletteFilter !== 'ALL') {
      filtered = paletteResults.filter(r => r.category === paletteFilter);
    }

    if (!filtered.length) {
      container.innerHTML = `
        <div class="text-center py-4 text-muted small">
          <i class="bi bi-search fs-3 opacity-50 mb-2 d-block"></i>
          <div>No matching commands, orders, or menu items found.</div>
          <div class="text-muted" style="font-size:0.75rem;">Try searching for "#ZIP", "Biryani", "Ramesh", or a page name.</div>
        </div>
      `;
      return;
    }

    paletteActiveIndex = 0;
    let html = '';
    filtered.forEach((item, idx) => {
      const isActive = idx === 0 ? 'active' : '';
      const catBadgeClass = item.category === 'Orders' ? 'bg-primary-subtle text-primary border border-primary-subtle' :
                           (item.category === 'Dishes' ? 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' :
                           (item.category === 'Staff & Couriers' ? 'bg-info-subtle text-info border border-info-subtle' : 'bg-success-subtle text-forest border border-success-subtle'));
      
      html += `
        <a href="${item.url}" class="command-palette-item ${isActive}" data-idx="${idx}">
          <div class="d-flex align-items-center gap-3 text-truncate">
            <div class="rounded-3 p-2 text-forest d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background:#EDFBF2;">
              <i class="bi ${item.icon || 'bi-arrow-right'} fs-6"></i>
            </div>
            <div class="text-truncate">
              <div class="fw-bold font-outfit small text-dark mb-0">${escapePaletteHtml(item.title)}</div>
              <span class="text-muted" style="font-size:0.75rem;">${escapePaletteHtml(item.subtitle)}</span>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="badge ${catBadgeClass} rounded-pill font-monospace" style="font-size:0.68rem;">${item.category}</span>
            <i class="bi bi-chevron-right text-muted small"></i>
          </div>
        </a>
      `;
    });

    container.innerHTML = html;

    // Click hover sync
    container.querySelectorAll('.command-palette-item').forEach((it, idx) => {
      it.addEventListener('mouseenter', () => {
        paletteActiveIndex = idx;
        highlightPaletteItem(container.querySelectorAll('.command-palette-item'));
      });
    });
  }

  function escapePaletteHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);
  }
  </script>


</body>
</html>
