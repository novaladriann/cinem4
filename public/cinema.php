<?php
$title  = "CINEM4 - Cinema";
$active = "cinema";
include '../app/views/partials/public/head.php';
include '../app/views/partials/public/navbar.php';
require '../app/config/koneksi.php';

/* ── Ambil daftar kota dari DB ── */
$cityResult = $conn->query("SELECT DISTINCT city FROM cinemas WHERE is_active = 1 AND city IS NOT NULL AND city != '' ORDER BY city");
$CITIES = [];
while ($row = $cityResult->fetch_assoc()) {
  $CITIES[] = $row['city'];
}

/* Cek apakah ada cinema tanpa kota */
$noCityCount = $conn->query("SELECT COUNT(*) FROM cinemas WHERE is_active = 1 AND (city IS NULL OR city = '')")->fetch_row()[0] ?? 0;
if ($noCityCount > 0) $CITIES[] = 'Lainnya';

/* Default kota pertama jika belum dipilih */
$city = $_GET['city'] ?? ($CITIES[0] ?? '');

/* ── Ambil cinema sesuai kota ── */
$cinemaList = [];
if ($city === 'Lainnya') {
  $r = $conn->query("SELECT * FROM cinemas WHERE is_active = 1 AND (city IS NULL OR city = '') ORDER BY name");
  while ($row = $r->fetch_assoc()) $cinemaList[] = $row;
} elseif ($city !== '') {
  $stmt = $conn->prepare("SELECT * FROM cinemas WHERE is_active = 1 AND city = ? ORDER BY name");
  $stmt->bind_param("s", $city);
  $stmt->execute();
  $r = $stmt->get_result();
  while ($row = $r->fetch_assoc()) $cinemaList[] = $row;
  $stmt->close();
} else {
  /* Tidak ada kota sama sekali — tampilkan semua */
  $r = $conn->query("SELECT * FROM cinemas WHERE is_active = 1 ORDER BY name");
  while ($row = $r->fetch_assoc()) $cinemaList[] = $row;
}
?>

<!-- HERO BANNER -->
<section class="cinema-hero">
  <div class="cinema-hero-bg" style="background-image:url('assets/img/cinema-cirebon.png')"></div>
  <div class="cinema-hero-overlay"></div>
  <div class="container cinema-hero-content">
    <div class="row">
      <div class="col-lg-7">
        <h1 class="display-5 fw-bold mb-2">Cinema</h1>
        <div class="text-secondary">Pilih kota dan temukan cinema di mall terdekat.</div>
      </div>
    </div>
  </div>
</section>

<!-- SEARCH FLOATING -->
<section class="cinema-search">
  <div class="container">
    <div class="card-glass p-3">
      <form class="row g-2 align-items-center" method="get" action="cinema.php">
        <div class="col-12 col-md-10">
          <div class="input-group input-group-lg">
            <span class="input-group-text bg-transparent text-secondary border-secondary">
              <i class="bi bi-geo-alt"></i>
            </span>
            <select class="form-select bg-dark text-light border-secondary" name="city">
              <?php if (count($CITIES) === 0): ?>
                <option value="">Belum ada kota</option>
              <?php else: ?>
                <?php foreach ($CITIES as $ct): ?>
                  <option value="<?= htmlspecialchars($ct) ?>"
                    <?= $ct === $city ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ct) ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>
        </div>
        <div class="col-12 col-md-2 d-grid">
          <button class="btn btn-primary btn-lg rounded-3" type="submit">
            <i class="bi bi-search"></i>
          </button>
        </div>
      </form>
    </div>
  </div>
</section>

<!-- LIST CINEMA -->
<section class="py-5">
  <div class="container">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
      <div>
        <h2 class="fw-bold mb-1"><?= htmlspecialchars($city) ?></h2>
        <div class="text-secondary">Menampilkan <?= count($cinemaList) ?> cinema</div>
      </div>
    </div>

    <?php if (count($cinemaList) === 0): ?>
      <div class="alert alert-dark border-secondary">
        Tidak ada cinema untuk kota ini.
      </div>
    <?php endif; ?>

    <div class="row g-4">
      <?php foreach ($cinemaList as $c): ?>
        <div class="col-12 col-md-6 col-lg-4 reveal d1">
          <div class="card-glass cinema-card h-100">

            <!-- Foto bioskop -->
            <div class="cinema-thumb" style="background-image:url('<?=
              !empty($c['image'])
                ? htmlspecialchars($c['image'])
                : 'assets/img/cinema-default.png'
            ?>')"></div>

            <div class="p-3">
              <div class="fw-bold text-light fs-5"><?= htmlspecialchars($c['name']) ?></div>
              <?php if (!empty($c['address'])): ?>
                <div class="text-secondary small mt-1">
                  <i class="bi bi-geo me-1"></i><?= htmlspecialchars($c['address']) ?>
                </div>
              <?php endif; ?>
              <?php if (!empty($c['city'])): ?>
                <div class="text-secondary small mt-1">
                  <i class="bi bi-building me-1"></i><?= htmlspecialchars($c['city']) ?>
                </div>
              <?php endif; ?>

              <div class="d-flex gap-2 mt-3">
                <a class="btn btn-primary rounded-pill px-3 btn-sm"
                   href="movies.php">
                  Lihat Film
                </a>
              </div>
            </div>

          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>

<?php include '../app/views/partials/public/footer.php'; ?>