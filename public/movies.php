<?php
$title = "CINEM4 - Movies";
$active = "movies";

require '../app/config/koneksi.php';
include '../app/views/partials/public/head.php';
include '../app/views/partials/public/navbar.php';

$q = strtolower(trim($_GET['q'] ?? ''));

/* ubah menit ke format lama: 1h 55m */
function formatDurationMovies(?int $minutes): string
{
  if (!$minutes || $minutes <= 0) return '';

  $hours = floor($minutes / 60);
  $mins  = $minutes % 60;

  if ($hours > 0 && $mins > 0) return $hours . 'h ' . $mins . 'm';
  if ($hours > 0) return $hours . 'h';
  return $mins . 'm';
}

/* mapping row DB -> shape mock lama */
function mapMovieMoviesPage(array $row): array
{
  return [
    'slug'      => $row['slug'] ?? '',
    'title'     => $row['title'] ?? '',
    'genre'     => $row['genre'] ?? '',
    'dur'       => formatDurationMovies(isset($row['duration_minute']) ? (int)$row['duration_minute'] : 0),
    'format'    => '2D', // ubah kalau nanti kamu punya kolom format sendiri
    'rating'    => '',
    'age'       => $row['rating_age'] ?? '',
    'poster'    => $row['poster'] ?? '',
    'trailer'   => $row['trailer_url'] ?? '',
    'featured'  => !empty($row['is_featured']),
    'status'    => (($row['status'] ?? '') === 'coming_soon') ? 'upcoming' : 'now',
    'synopsis'  => $row['synopsis'] ?? '',
    'backdrop'  => $row['backdrop'] ?? '',
  ];
}

$movies = [];

if ($q !== '') {
  $stmt = $conn->prepare("
    SELECT *
    FROM movies
    WHERE is_active = 1
      AND LOWER(title) LIKE ?
    ORDER BY is_featured DESC, release_date DESC, id_movie DESC
  ");
  $search = '%' . $q . '%';
  $stmt->bind_param("s", $search);
  $stmt->execute();
  $result = $stmt->get_result();
} else {
  $result = $conn->query("
    SELECT *
    FROM movies
    WHERE is_active = 1
    ORDER BY is_featured DESC, release_date DESC, id_movie DESC
  ");
}

while ($row = $result->fetch_assoc()) {
  $movies[] = mapMovieMoviesPage($row);
}

/* safety: jika ada film tanpa slug */
foreach ($movies as &$m) {
  if (empty($m['slug'])) {
    $m['slug'] = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $m['title'] ?? 'movie')));
  }
}
unset($m);
?>

<div class="container py-5">
  <div class="d-flex flex-wrap gap-2 align-items-end justify-content-between mb-4">
    <div>
      <h1 class="fw-bold mb-1">Movies</h1>
      <div class="text-secondary">Cari film & klik untuk detail</div>
    </div>
    <form class="d-flex gap-2" method="get">
      <input class="form-control bg-dark text-light border-secondary" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Search title...">
      <button class="btn btn-outline-light border-secondary" type="submit">Search</button>
    </form>
  </div>

  <?php if (count($movies) === 0): ?>
    <div class="alert alert-dark border-secondary">Film tidak ditemukan.</div>
  <?php endif; ?>

  <div class="row g-3">
    <?php foreach ($movies as $m): ?>
      <?php
      $slug    = $m['slug'] ?? '';
      $titleM  = $m['title'] ?? '';
      $poster  = $m['poster'] ?? '';
      $genre   = $m['genre'] ?? '';
      $dur     = $m['dur'] ?? '';
      $format  = $m['format'] ?? '';
      $rating  = $m['rating'] ?? '';
      $age     = $m['age'] ?? '';
      $trailer = $m['trailer'] ?? '';
      $status  = $m['status'] ?? 'now';
      ?>
      <div class="col-6 col-md-4 col-lg-3 reveal d2">
        <div class="movie-wrap" role="button"
          onclick="window.location='movie-detail.php?slug=<?= urlencode($slug) ?>'">

          <div class="poster-card poster-card--grid">
            <div class="poster-media">
              <img src="<?= htmlspecialchars($poster) ?>" alt="<?= htmlspecialchars($titleM) ?>">
            </div>

            <?php if ($status === 'upcoming'): ?>
              <div class="coming-ribbon">COMING SOON</div>
            <?php endif; ?>

            <?php if ($age !== ''): ?>
              <div class="poster-badge"><?= htmlspecialchars($age) ?></div>
            <?php endif; ?>

            <div class="poster-overlay">
              <div class="poster-actions">
                <?php if (trim($trailer) !== ''): ?>
                  <button type="button"
                    class="btn btn-light btn-sm rounded-pill px-3 watch-trailer"
                    data-bs-toggle="modal"
                    data-bs-target="#trailerModal"
                    data-trailer="<?= htmlspecialchars($trailer) ?>">
                    <i class="bi bi-play-fill me-1"></i> Trailer
                  </button>
                <?php endif; ?>

                <?php if ($status !== 'upcoming'): ?>
                  <a href="movie-detail.php?slug=<?= urlencode($slug) ?>#jadwal"
                    class="btn btn-dark btn-sm rounded-pill px-3 btn-ticket">
                    <i class="bi bi-ticket-perforated me-1"></i> Tiket
                  </a>
                <?php endif; ?>
              </div>

              <?php if ($format !== '' || $rating !== '' || $dur !== ''): ?>
                <div class="poster-meta">
                  <?php if ($format !== ''): ?>
                    <span class="badge rounded-pill bg-dark text-light border border-secondary"><?= htmlspecialchars($format) ?></span>
                  <?php endif; ?>

                  <?php if ($rating !== '' && $status !== 'upcoming'): ?>
                    <span class="badge rounded-pill bg-danger"><?= htmlspecialchars($rating) ?></span>
                  <?php endif; ?>

                  <?php if ($dur !== ''): ?>
                    <span class="badge rounded-pill bg-dark text-light border border-secondary"><?= htmlspecialchars($dur) ?></span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="mt-2 fw-semibold text-light text-truncate"><?= htmlspecialchars($titleM) ?></div>
          <div class="small text-secondary">
            <?= htmlspecialchars($genre) ?>
            <?php if ($dur !== ''): ?> • <?= htmlspecialchars($dur) ?><?php endif; ?>
          </div>

        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="modal fade" id="trailerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-body p-0 position-relative">
        <button type="button" class="btn-close btn-close-white position-absolute end-0 m-3 z-3" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="ratio ratio-16x9">
          <iframe id="trailerFrame" src="" title="Trailer" allow="autoplay; encrypted-media" allowfullscreen></iframe>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  const trailerModal = document.getElementById('trailerModal');
  const trailerFrame = document.getElementById('trailerFrame');

  document.addEventListener('click', function(e) {
    const btn = e.target.closest('.watch-trailer');
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();

    const url = btn.getAttribute('data-trailer') || '';
    trailerFrame.src = url ? (url + (url.includes('?') ? '&' : '?') + 'autoplay=1') : '';
  }, true);

  trailerModal.addEventListener('hidden.bs.modal', function() {
    trailerFrame.src = '';
  });
</script>

<?php include '../app/views/partials/public/footer.php'; ?>