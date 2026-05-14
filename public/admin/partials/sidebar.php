<?php
$adm       = currentAdmin();
$initials  = strtoupper(substr($adm['name'] ?? 'A', 0, 1));
$adminPage = basename($_SERVER['PHP_SELF']);
$roleLabel = adminRoleLabel($adm['role'] ?? 'admin');
?>

<!-- Overlay mobile -->
<div class="adm-overlay" id="admOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<aside class="adm-sidebar" id="admSidebar">

  <!-- Logo -->
  <a class="adm-logo" href="index.php">
    <img src="../assets/img/logo-cinem4.png" alt="CINEM4" height="32">
    <span class="adm-logo-badge">Admin</span>
  </a>

  <!-- Nav -->
  <nav class="adm-nav">

    <div class="adm-nav-label">Main</div>
    <a href="index.php"
      class="adm-nav-link <?= $adminPage === 'index.php' ? 'active' : '' ?>">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <div class="adm-nav-label mt-2">Konten</div>
    <?php if (adminCan('movies.manage')): ?>
      <a href="movies.php"
        class="adm-nav-link <?= in_array($adminPage, ['movies.php', 'movie-form.php'], true) ? 'active' : '' ?>">
        <i class="bi bi-film"></i> Film
      </a>
    <?php endif; ?>

    <?php if (adminCan('schedules.manage')): ?>
      <a href="schedules.php"
        class="adm-nav-link <?= in_array($adminPage, ['schedules.php', 'schedule-form.php'], true) ? 'active' : '' ?>">
        <i class="bi bi-calendar3"></i> Jadwal
      </a>
    <?php endif; ?>

    <?php if (adminCan('cinemas.manage')): ?>
      <a href="cinemas.php"
        class="adm-nav-link <?= $adminPage === 'cinemas.php' ? 'active' : '' ?>">
        <i class="bi bi-building"></i> Bioskop
      </a>
    <?php endif; ?>

    <?php if (adminCan('promotions.manage')): ?>
      <a href="promotions.php"
        class="adm-nav-link <?= in_array($adminPage, ['promotions.php', 'promo-form.php'], true) ? 'active' : '' ?>">
        <i class="bi bi-tag"></i> Promosi
      </a>
    <?php endif; ?>

    <?php if (adminCan('bookings.view')): ?>
      <div class="adm-nav-label mt-2">Transaksi</div>
      <a href="bookings.php"
        class="adm-nav-link <?= $adminPage === 'bookings.php' ? 'active' : '' ?>">
        <i class="bi bi-ticket-perforated"></i> Booking
      </a>
      <?php if (adminCan('reports.view')): ?>
        <a href="reports.php"
          class="adm-nav-link <?= $adminPage === 'reports.php' ? 'active' : '' ?>">
          <i class="bi bi-graph-up-arrow"></i> Laporan
        </a>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (adminCan('users.view')): ?>
      <div class="adm-nav-label mt-2">Pengguna</div>
      <a href="users.php"
        class="adm-nav-link <?= $adminPage === 'users.php' ? 'active' : '' ?>">
        <i class="bi bi-people"></i> Users
      </a>
    <?php endif; ?>

    <?php if (adminCan('admins.manage')): ?>
      <div class="adm-nav-label mt-2">Pengaturan</div>
      <a href="admins.php"
        class="adm-nav-link <?= $adminPage === 'admins.php' ? 'active' : '' ?>">
        <i class="bi bi-shield-lock"></i> Admin
      </a>
    <?php endif; ?>

    <!-- Link ke halaman publik -->
    <div class="adm-nav-label mt-2">Lainnya</div>
    <a href="../index.php" target="_blank" class="adm-nav-link">
      <i class="bi bi-box-arrow-up-right"></i> Lihat Website
    </a>

  </nav>

  <!-- Footer sidebar: info user + logout -->
  <div class="adm-sidebar-footer">
    <div class="adm-user-info">
      <div class="adm-user-avatar"><?= htmlspecialchars($initials) ?></div>
      <div>
        <div class="adm-user-name"><?= htmlspecialchars($adm['name'] ?? 'Admin') ?></div>
        <div class="adm-user-role"><?= htmlspecialchars($roleLabel) ?></div>
      </div>
    </div>
    <a href="logout.php" class="adm-logout">
      <i class="bi bi-box-arrow-left"></i> Keluar
    </a>
  </div>

</aside>