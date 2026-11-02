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

</body>
</html>
