document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.getElementById('sidebarToggle');
  var sidebar = document.getElementById('appSidebar');

  if (toggle && sidebar) {
    var backdrop = document.createElement('div');
    backdrop.className = 'sidebar-backdrop';
    document.body.appendChild(backdrop);

    function openSidebar() {
      sidebar.classList.add('show');
      backdrop.classList.add('show');
      document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
      sidebar.classList.remove('show');
      backdrop.classList.remove('show');
      document.body.style.overflow = '';
    }

    toggle.addEventListener('click', function (e) {
      e.preventDefault();
      if (sidebar.classList.contains('show')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });

    // Tutup saat area gelap diklik atau tombol Esc ditekan
    backdrop.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        closeSidebar();
      }
    });

    // Kembali ke layout desktop: pastikan sidebar/backdrop direset
    window.addEventListener('resize', function () {
      if (window.innerWidth >= 992) {
        closeSidebar();
      }
    });
  }

  document.querySelectorAll('.alert-dismissible').forEach(function (alertEl) {
    setTimeout(function () {
      var instance = bootstrap.Alert.getOrCreateInstance(alertEl);
      instance.close();
    }, 6000);
  });
});
