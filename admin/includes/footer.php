<?php
/**
 * Admin Console Footer
 * Zippy - Online Food Ordering Platform
 */
?>
      </div><!-- /.admin-content -->
    </div><!-- /.admin-main -->
  </div><!-- /.admin-layout -->

  <!-- Bootstrap 5.3.3 JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Admin Interactive Scripts -->
  <script>
    // Responsive sidebar toggle for mobile
    const toggleBtn = document.getElementById('adminSidebarToggle');
    const sidebar = document.querySelector('.admin-sidebar');
    if (toggleBtn && sidebar) {
      toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('show');
      });
    }

    // Helper toast function
    function showAdminToast(message) {
      const toast = document.createElement('div');
      toast.className = 'position-fixed bottom-0 end-0 m-3 p-3 bg-dark text-white rounded-3 shadow-lg';
      toast.style.zIndex = '9999';
      toast.innerHTML = `<i class="bi bi-info-circle-fill text-success me-2"></i> ${message}`;
      document.body.appendChild(toast);
      setTimeout(() => toast.remove(), 3500);
    }
  </script>

</body>
</html>
