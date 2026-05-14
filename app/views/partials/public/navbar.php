<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$active = $active ?? 'home';
?>

<nav class="navbar navbar-expand-lg navbar-dark cinem4-nav border-bottom border-secondary sticky-top">
  <div class="container-fluid px-3 px-lg-4">

    <!-- Brand -->
  <a class="navbar-brand d-flex align-items-center gap-2 me-lg-4" href="index.php">
    <img src="assets/img/logo-cinem4.png" alt="CINEM4" height="40">
  </a>

    <!-- Toggler (mobile) -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="topNav">

      <!-- MENU -->
      <ul class="navbar-nav me-auto gap-lg-3 mt-3 mt-lg-0 align-items-lg-center">
        <li class="nav-item">
          <a class="nav-link <?= $active==='home'?'active':'' ?>" href="index.php">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active==='movies'?'active':'' ?>" href="movies.php">Movies</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active==='promotions'?'active':'' ?>" href="promotions.php">Promotions</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $active==='cinema'?'active':'' ?>" href="cinema.php">Cinema</a>
        </li>
      </ul>

      <!-- RIGHT SIDE -->
      <div class="d-flex align-items-center gap-2 gap-lg-3 mt-3 mt-lg-0">

        <!-- Location -->
        <div class="dropdown">
          <button class="btn btn-dark border border-secondary dropdown-toggle rounded-pill px-3"
                  data-bs-toggle="dropdown">
            <i class="bi bi-geo-alt me-1"></i> Cirebon
          </button>
          <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
            <li><a class="dropdown-item" href="#">Cirebon</a></li>
          </ul>
        </div>

        <!-- LOGIN / USER -->
        <?php if (isset($_SESSION['user'])): ?>

          <div class="dropdown">
            <button class="btn btn-primary rounded-pill px-3 dropdown-toggle"
                    data-bs-toggle="dropdown">
              <i class="bi bi-person-circle me-1"></i>
              <?= htmlspecialchars($_SESSION['name']) ?>
            </button>

            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
              <li>
                <a class="dropdown-item" href="dashboard.php">
                  <i class="bi bi-person me-2"></i> Profile
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="logout.php">
                  <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
              </li>
            </ul>
          </div>

        <?php else: ?>

          <a class="btn btn-primary rounded-pill px-3" href="join-us.php">
            <i class="bi bi-person-circle me-1"></i> Join Us
          </a>

        <?php endif; ?>

      </div>

    </div>
  </div>
</nav>