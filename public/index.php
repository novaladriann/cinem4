<?php
$title = "CINEM4 - Home";
$active = "home";
include '../app/views/partials/public/head.php';
include '../app/views/partials/public/navbar.php';

require '../app/config/koneksi.php';

/* helper durasi: 115 -> 1h 55m */
function formatDurationHome(?int $minutes): string
{
  if (!$minutes || $minutes <= 0) return '';

  $hours = floor($minutes / 60);
  $mins  = $minutes % 60;

  if ($hours > 0 && $mins > 0) return $hours . 'h ' . $mins . 'm';
  if ($hours > 0) return $hours . 'h';
  return $mins . 'm';
}

/* mapping movies DB -> shape mock lama */
function mapMovieHome(array $row): array
{
  return [
    'slug'      => $row['slug'] ?? '',
    'title'     => $row['title'] ?? '',
    'genre'     => $row['genre'] ?? '',
    'dur'       => formatDurationHome(isset($row['duration_minute']) ? (int)$row['duration_minute'] : 0),
    'format'    => '2D',
    'rating'    => '',
    'age'       => $row['rating_age'] ?? '',
    'poster'    => $row['poster'] ?? '',
    'banner'    => !empty($row['backdrop']) ? $row['backdrop'] : ($row['poster'] ?? ''),
    'trailer'   => $row['trailer_url'] ?? '',
    'featured'  => !empty($row['is_featured']),
    'status'    => (($row['status'] ?? '') === 'coming_soon') ? 'upcoming' : 'now',
    'synopsis'  => $row['synopsis'] ?? '',
    'featured'     => !empty($row['is_featured']),
    'show_in_hero' => !empty($row['show_in_hero']),
    'hero_order'   => (int)($row['hero_order'] ?? 0),
  ];
}

/* helper valid promo */
function formatPromoValidHome($startDate, $endDate): string
{
  if (!empty($startDate) && !empty($endDate)) {
    return 'Valid ' . date('d M Y', strtotime($startDate)) . ' - ' . date('d M Y', strtotime($endDate));
  }
  if (!empty($startDate)) {
    return 'Mulai ' . date('d M Y', strtotime($startDate));
  }
  if (!empty($endDate)) {
    return 'Valid until ' . date('d M Y', strtotime($endDate));
  }
  return 'Promo aktif';
}

/* mapping promotions DB -> shape mock lama */
function mapPromoHome(array $row): array
{
  return [
    'id'          => $row['id_promotion'] ?? 0,
    'title'       => $row['title'] ?? '',
    'category'    => 'Promotion',
    'valid'       => formatPromoValidHome($row['start_date'] ?? null, $row['end_date'] ?? null),
    'img'         => $row['image'] ?? '',
    'featured'    => true,
    'hero'        => true, // supaya promo bisa ikut hero
    'cta_link'    => 'promotions.php',
    'created_at'  => $row['created_at'] ?? null,
  ];
}

/* MOVIES dari DB */
$MOVIES = [];
$qMovies = $conn->query("
    SELECT *
    FROM movies
    WHERE is_active = 1
    ORDER BY show_in_hero DESC, hero_order ASC, is_featured DESC, release_date DESC, id_movie DESC
");

while ($row = $qMovies->fetch_assoc()) {
  $MOVIES[] = mapMovieHome($row);
}

/* PROMOTIONS dari DB */
$PROMOS = [];
$qPromos = $conn->query("
    SELECT *
    FROM promotions
    WHERE is_active = 1
    ORDER BY created_at DESC, id_promotion DESC
");

while ($row = $qPromos->fetch_assoc()) {
  $PROMOS[] = mapPromoHome($row);
}

/* contoh film hits (banner) - kamu ganti img sesuai punya kamu */
$HERO_FILMS = [];

/*
|----------------------------------------------------------
| HERO MOVIES
| Prioritas:
| 1. featured + now showing
| 2. featured + upcoming
| 3. fallback film lain kalau kurang
| Target: 2 slide film seperti tampilan lama
|----------------------------------------------------------
*/
$heroMoviePool = array_values(array_filter($MOVIES, function ($m) {
  return !empty($m['show_in_hero']);
}));

/* fallback kalau featured kurang */
if (count($heroMoviePool) < 2) {
  $fallbackMovies = array_values(array_filter($MOVIES, function ($m) {
    return !empty($m['slug']);
  }));

  foreach ($fallbackMovies as $fm) {
    $exists = false;
    foreach ($heroMoviePool as $hm) {
      if (($hm['slug'] ?? '') === ($fm['slug'] ?? '')) {
        $exists = true;
        break;
      }
    }

    if (!$exists) {
      $heroMoviePool[] = $fm;
    }

    if (count($heroMoviePool) >= 2) {
      break;
    }
  }
}

$heroMoviePool = array_slice($heroMoviePool, 0, 6);

foreach ($heroMoviePool as $m) {
  $status = $m['status'] ?? 'now';
  $title  = $m['title'] ?? '';
  $genre  = $m['genre'] ?? '';
  $dur    = $m['dur'] ?? '';
  $slug   = $m['slug'] ?? '';
  $img    = !empty($m['banner']) ? $m['banner'] : ($m['poster'] ?? '');

  $metaParts = [];
  if ($genre !== '') $metaParts[] = strtoupper($genre);
  if ($dur !== '') $metaParts[] = $dur;

  $HERO_FILMS[] = [
    "type"  => "film",
    "title" => $title,
    "meta"  => implode(" • ", $metaParts),
    "img"   => $img,
    "cta1"  => ($status === 'upcoming') ? "Coming Soon" : "Book Now",
    "cta2"  => "Details",
    "link1" => ($status === 'upcoming')
      ? "movie-detail.php?slug=" . urlencode($slug)
      : "movie-detail.php?slug=" . urlencode($slug),
    "link2" => "movie-detail.php?slug=" . urlencode($slug),
  ];
}

/*
|----------------------------------------------------------
| HERO PROMOS
| Ambil 2 promo terbaru untuk ikut slider hero
|----------------------------------------------------------
*/
$HERO_PROMOS = array_slice($PROMOS, 0, 2);

foreach ($HERO_PROMOS as $p) {
  $HERO_FILMS[] = [
    "type"  => "promo",
    "title" => $p["title"] ?? '',
    "meta"  => strtoupper($p["category"] ?? 'PROMOTION') . " • " . ($p["valid"] ?? ''),
    "img"   => $p["img"] ?? '',
    "cta1"  => "See Promo",
    "cta2"  => "All Promotions",
    "link1" => $p["cta_link"] ?? 'promotions.php',
    "link2" => "promotions.php",
  ];
}

/* batasi total slide hero seperti versi lama */
$heroSlides = array_slice($HERO_FILMS, 0, 6);

$featuredNow = array_values(array_filter(
  $MOVIES,
  fn($m) => (($m['status'] ?? 'now') === 'now') && (($m['featured'] ?? false) === true)
));

$featuredUp = array_values(array_filter(
  $MOVIES,
  fn($m) => (($m['status'] ?? '') === 'upcoming') && (($m['featured'] ?? false) === true)
));
?>

<!-- HERO -->
<section class="hero">
  <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-touch="true"
    data-bs-interval="5000">
    <div class="carousel-indicators">
      <?php foreach ($heroSlides as $i => $s): ?>
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>"></button>
      <?php endforeach; ?>
    </div>

    <div class="carousel-inner">
      <?php foreach ($heroSlides as $i => $s): ?>
        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>" style="background-image:url('<?= $s['img'] ?>')">
          <div class="hero-overlay"></div>
          <div class="container hero-content">
            <div class="row">
              <div class="col-lg-7">
                <div class="text-uppercase text-secondary small mb-2"><?= htmlspecialchars($s['meta']) ?></div>
                <h1 class="display-4 fw-bold mb-3"><?= htmlspecialchars($s['title']) ?></h1>
                <div class="d-flex gap-2 flex-wrap">
                  <a href="<?= htmlspecialchars($s['link1'] ?? 'movies.php') ?>" class="btn btn-primary btn-lg rounded-pill px-4">
                    <?= htmlspecialchars($s['cta1']) ?>
                  </a>
                  <a href="<?= htmlspecialchars($s['link2'] ?? '#') ?>" class="btn btn-outline-light btn-lg rounded-pill px-4">
                    <?= htmlspecialchars($s['cta2']) ?>
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
      <span class="carousel-control-prev-icon"></span>
      <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
      <span class="carousel-control-next-icon"></span>
      <span class="visually-hidden">Next</span>
    </button>
  </div>
</section>

<!-- SEARCH BAR (besar seperti contoh) -->
<section class="hero-search">
  <div class="container">
    <div class="card-glass p-3 p-md-4">
      <form class="row g-2 align-items-center" action="movies.php" method="get">
        <div class="col-12 col-md">
          <div class="input-group input-group-lg">
            <span class="input-group-text bg-transparent text-secondary border-secondary">
              <i class="bi bi-search"></i>
            </span>
            <input class="form-control bg-transparent text-light border-secondary"
              name="q" placeholder="Search Movie, Cinema, City...">
          </div>
        </div>
        <div class="col-12 col-md-auto d-grid">
          <button class="btn btn-primary btn-lg rounded-3 px-4" type="submit">
            <i class="bi bi-arrow-right-circle"></i>
          </button>
        </div>
      </form>
    </div>
  </div>
</section>

<!-- CHOOSE YOUR MOVIE -->
<section class="py-5">
  <div class="container">
    <div class="text-center mb-4 reveal">
      <h2 class="section-title display-6 mb-2">Choose Your Movie</h2>
      <div class="text-secondary">Now Showing & Upcoming</div>
    </div>

    <!-- Tabs -->
    <ul class="nav justify-content-center gap-2 tab-soft mb-4" id="movieTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabNow" type="button">Now Showing</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabUp" type="button">Upcoming</button>
      </li>
    </ul>

    <div class="tab-content">
      <!-- NOW SHOWING -->
      <div class="tab-pane fade show active" id="tabNow">
        <?php include '../app/views/components/poster_carousel.php'; ?>
        <?php if (count($featuredNow) === 0): ?>
          <div class="text-center text-secondary py-4">Belum ada film featured.</div>
        <?php else: ?>
          <?php renderPosterCarousel("nowCarousel", $featuredNow, "now"); ?>
        <?php endif; ?>
      </div>

      <!-- UPCOMING -->
      <div class="tab-pane fade" id="tabUp">
        <?php renderPosterCarousel("upcomingCarousel", $featuredUp, "upcoming"); ?>
      </div>
    </div>
  </div>
</section>

<?php
$promoFeatured = array_values(array_filter($PROMOS, fn($p) => !empty($p['featured'])));
$promoFeatured = array_slice($promoFeatured, 0, 6);
?>

<section class="py-5">
  <div class="container">
    <div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <h3 class="section-title mb-1">Featured Promotions</h3>
        <div class="text-secondary">Promo pilihan minggu ini.</div>
      </div>
      <a class="btn btn-outline-light border-secondary rounded-pill" href="promotions.php">View all</a>
    </div>

    <div class="row g-3">
      <?php foreach ($promoFeatured as $p): ?>
        <div class="col-12 col-md-6 col-lg-4">
          <a class="text-decoration-none" href="<?= htmlspecialchars($p['cta_link']) ?>">
            <div class="promo-card card-glass h-100">
              <div class="promo-thumb" style="background-image:url('<?= htmlspecialchars($p['img']) ?>')"></div>
              <div class="p-3">
                <div class="fw-bold text-light"><?= htmlspecialchars($p['title']) ?></div>
                <div class="text-secondary small mt-1"><?= htmlspecialchars($p['valid']) ?></div>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    function enableDesktopDragCarousel(carouselId) {
      const el = document.getElementById(carouselId);
      if (!el) return;

      const carousel = bootstrap.Carousel.getOrCreateInstance(el);
      let isDown = false;
      let startX = 0;

      el.addEventListener('mousedown', function(e) {
        isDown = true;
        startX = e.clientX;
        el.classList.add('is-dragging');
      });

      window.addEventListener('mouseup', function(e) {
        if (!isDown) return;

        const diff = e.clientX - startX;
        isDown = false;
        el.classList.remove('is-dragging');

        if (Math.abs(diff) > 50) {
          if (diff < 0) {
            carousel.next();
          } else {
            carousel.prev();
          }
        }
      });

      el.addEventListener('mouseleave', function() {
        isDown = false;
        el.classList.remove('is-dragging');
      });

      el.addEventListener('dragstart', function(e) {
        e.preventDefault();
      });
    }

    enableDesktopDragCarousel('heroCarousel');
    enableDesktopDragCarousel('nowCarousel');
    enableDesktopDragCarousel('upcomingCarousel');
  });
</script>

<?php include '../app/views/partials/public/footer.php'; ?>