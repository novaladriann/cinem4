<?php
session_start();

$title  = "CINEM4 - Movie Detail";
$active = "movies";
include '../app/views/partials/public/head.php';
include '../app/views/partials/public/navbar.php';
include '../app/config/koneksi.php';

/* ── Ambil film ── */
$slug  = $_GET['slug'] ?? '';
$movie = null;

$stmt = $conn->prepare("SELECT * FROM movies WHERE slug = ? LIMIT 1");
if ($stmt) {
  $stmt->bind_param("s", $slug);
  $stmt->execute();
  $movie = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

$isLoggedIn = isset($_SESSION['user']);

function formatDur(?int $m): string {
  if (!$m || $m <= 0) return '';
  $h = floor($m / 60); $s = $m % 60;
  if ($h && $s) return "{$h}h {$s}m";
  return $h ? "{$h}h" : "{$s}m";
}

if ($movie) {
  $genre      = trim($movie['genre']           ?? '');
  $dur        = formatDur((int)($movie['duration_minute'] ?? 0));
  $rating     = trim($movie['rating_age']      ?? '');
  $poster     = trim($movie['poster']          ?? '');
  $backdrop   = trim(!empty($movie['backdrop']) ? $movie['backdrop'] : ($movie['poster'] ?? ''));
  $trailer    = trim($movie['trailer_url']     ?? '');
  $synopsis   = trim($movie['synopsis']        ?? '');
  $isUpcoming = ($movie['status'] ?? '') === 'coming_soon';
}

/* ── Tanggal tersedia dari DB ── */
$availableDates = [];
$cinemaList     = [];

if ($movie) {
  $q = $conn->prepare("
    SELECT DISTINCT show_date FROM schedules
    WHERE id_movie = ? AND is_active = 1 AND status = 'open' AND show_date >= CURDATE()
    ORDER BY show_date ASC
  ");
  $q->bind_param("i", $movie['id_movie']);
  $q->execute();
  $r = $q->get_result();
  while ($row = $r->fetch_assoc()) $availableDates[] = $row['show_date'];
  $q->close();
}

$selectedDate = $_GET['date'] ?? '';
if (!in_array($selectedDate, $availableDates))
  $selectedDate = count($availableDates) > 0 ? $availableDates[0] : '';

if ($movie && $selectedDate !== '') {
  $q = $conn->prepare("
    SELECT DISTINCT c.id_cinema, c.name, c.city
    FROM schedules s JOIN cinemas c ON c.id_cinema = s.id_cinema
    WHERE s.id_movie = ? AND s.show_date = ? AND s.is_active = 1 AND s.status = 'open'
    ORDER BY c.name
  ");
  $q->bind_param("is", $movie['id_movie'], $selectedDate);
  $q->execute();
  $r = $q->get_result();
  while ($row = $r->fetch_assoc()) $cinemaList[] = $row;
  $q->close();
}

$selectedCinema = $_GET['cinema'] ?? '';
$validIds = array_column($cinemaList, 'id_cinema');
if (!in_array($selectedCinema, $validIds))
  $selectedCinema = count($cinemaList) > 0 ? $cinemaList[0]['id_cinema'] : '';

/* ── Jadwal ── */
$scheduleGroups = [];
if ($movie && $selectedDate !== '') {
  $params = [$movie['id_movie'], $selectedDate];
  $types  = "is";
  $extra  = "";
  if ($selectedCinema !== '') {
    $extra   = " AND s.id_cinema = ?";
    $types  .= "i";
    $params[] = (int)$selectedCinema;
  }
  $q = $conn->prepare("
    SELECT s.*, c.name AS cinema_name, c.city AS cinema_city
    FROM schedules s JOIN cinemas c ON c.id_cinema = s.id_cinema
    WHERE s.id_movie = ? AND s.show_date = ?
      AND s.is_active = 1 AND s.status = 'open'
      AND TIMESTAMPADD(MINUTE, -s.booking_close_minutes,
            TIMESTAMP(s.show_date, s.show_time)) > NOW()
      $extra
    ORDER BY c.name, s.studio_name, s.show_time
  ");
  $q->bind_param($types, ...$params);
  $q->execute();
  $r = $q->get_result();
  while ($row = $r->fetch_assoc()) {
    $cid = $row['id_cinema']; $std = $row['studio_name'];
    if (!isset($scheduleGroups[$cid]))
      $scheduleGroups[$cid] = ['cinema_name'=>$row['cinema_name'],'cinema_city'=>$row['cinema_city'],'studios'=>[]];
    if (!isset($scheduleGroups[$cid]['studios'][$std]))
      $scheduleGroups[$cid]['studios'][$std] = ['price'=>$row['price'],'schedules'=>[]];
    $scheduleGroups[$cid]['studios'][$std]['schedules'][] = $row;
  }
  $q->close();
}

function datePill(string $date): array {
  $ts = strtotime($date);
  return ['value'=>$date,'month'=>strtoupper(date('M',$ts)),'day'=>date('d',$ts),'dow'=>strtoupper(date('D',$ts))];
}
?>

<?php if (!$movie): ?>
<div class="container py-5">
  <div class="alert alert-dark border-secondary">
    Film tidak ditemukan. <a class="text-decoration-none" href="movies.php">Kembali ke Movies</a>
  </div>
</div>
<?php else: ?>

<!-- ═══════════════════ HERO ═══════════════════ -->
<section class="md-hero">
  <div class="md-hero-bg" style="background-image:url('<?= htmlspecialchars($backdrop) ?>')"></div>
  <div class="md-hero-overlay"></div>

  <div class="container md-hero-body">
    <!-- Tombol Back pojok kanan atas -->
    <div class="text-end mb-3">
      <a href="movies.php" class="md-btn-back">
        <i class="bi bi-arrow-left me-1"></i>Back
      </a>
    </div>
    <div class="md-hero-inner">

      <!-- Poster -->
      <div class="md-poster-col">
        <div class="md-poster-frame">
          <img src="<?= htmlspecialchars($poster) ?>" alt="<?= htmlspecialchars($movie['title']) ?>">
          <?php if ($rating !== ''): ?>
            <span class="md-poster-badge"><?= htmlspecialchars($rating) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Info -->
      <div class="md-info-col">
        <div class="md-meta-top">
          <?php if ($genre !== ''): ?><span><?= htmlspecialchars(strtoupper($genre)) ?></span><?php endif; ?>
          <?php if ($dur !== ''): ?><span class="md-dot">•</span><span><?= htmlspecialchars($dur) ?></span><?php endif; ?>
        </div>

        <h1 class="md-title"><?= htmlspecialchars($movie['title']) ?></h1>

        <div class="md-tags">
          <?php if ($rating !== ''): ?>
            <span class="md-tag md-tag-age"><?= htmlspecialchars($rating) ?></span>
          <?php endif; ?>
          <?php if ($isUpcoming): ?>
            <span class="md-tag md-tag-soon">Coming Soon</span>
          <?php endif; ?>
        </div>

        <!-- Synopsis scrollable -->
        <?php if ($synopsis !== ''): ?>
        <div class="md-synopsis-box" id="synBox">
          <p class="md-syn-text"><?= nl2br(htmlspecialchars($synopsis)) ?></p>
        </div>
        <button class="md-syn-toggle" id="synBtn" onclick="toggleSyn()">See More</button>
        <?php endif; ?>

        <!-- Tombol -->
        <div class="md-actions">
          <?php if ($trailer !== ''): ?>
            <button type="button" class="md-btn-trailer watch-trailer"
              data-bs-toggle="modal" data-bs-target="#trailerModal"
              data-trailer="<?= htmlspecialchars($trailer) ?>">
              <i class="bi bi-play-circle-fill me-2"></i>Trailer
            </button>
          <?php endif; ?>

          <?php if ($isUpcoming): ?>
            <button class="md-btn-soon" disabled>
              <i class="bi bi-clock me-2"></i>Coming Soon
            </button>
          <?php else: ?>
            <a href="#jadwal" class="md-btn-book">
              <i class="bi bi-ticket-perforated-fill me-2"></i>Book Now
            </a>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ═══════════════════ FILTER BAR (overlap) ═══════════════════ -->
<?php if ($isUpcoming): ?>
<!-- ═══ COMING SOON CARD ═══ -->
<div class="container py-4 pb-5">
  <div class="card-glass p-4 text-center mx-auto" style="max-width:480px">
    <div class="mb-2">
      <span style="
        display:inline-flex; align-items:center; justify-content:center;
        width:52px; height:52px; border-radius:999px;
        background:rgba(31,111,255,.12);
        border:1px solid rgba(31,111,255,.30);
        font-size:22px; color:var(--c4-primary);
      "><i class="bi bi-clock"></i></span>
    </div>
    <div class="fw-bold text-light mb-1" style="font-size:16px;">Coming Soon</div>
    <div class="text-secondary" style="font-size:13px;">
      Jadwal tayang film ini belum tersedia.<br>Pantau terus untuk info terbaru!
    </div>
    <a href="movies.php" class="btn btn-outline-light border-secondary rounded-pill px-4 mt-3"
       style="font-size:13px;">
      <i class="bi bi-arrow-left me-1"></i> Lihat Film Lainnya
    </a>
  </div>
</div>
<?php endif; ?>

<?php if (!$isUpcoming): ?>
<div class="md-filter-overlap">
  <div class="container">

    <?php if (count($availableDates) > 0): ?>
    <div class="card-glass md-filter-bar">
      <form class="md-filter-inner" method="get" id="filterForm">
        <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">

        <?php if (count($cinemaList) > 1): ?>
          <div class="sched-cinema-wrap">
            <i class="bi bi-geo-alt"></i>
            <select name="cinema" class="sched-select"
              onchange="document.getElementById('filterForm').submit()">
              <?php foreach ($cinemaList as $c): ?>
                <option value="<?= $c['id_cinema'] ?>"
                  <?= ((string)$selectedCinema===(string)$c['id_cinema'])?'selected':'' ?>>
                  <?= htmlspecialchars($c['name']) ?>
                  <?php if ($c['city']): ?>(<?= htmlspecialchars($c['city']) ?>)<?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
            <i class="bi bi-chevron-down sched-chevron"></i>
          </div>
          <div class="sched-vdivider"></div>

        <?php elseif (count($cinemaList) === 1): ?>
          <div class="sched-cinema-static">
            <i class="bi bi-geo-alt"></i>
            <span><?= htmlspecialchars($cinemaList[0]['name']) ?>
              <?php if ($cinemaList[0]['city']): ?>(<?= htmlspecialchars($cinemaList[0]['city']) ?>)<?php endif; ?>
            </span>
            <input type="hidden" name="cinema" value="<?= $cinemaList[0]['id_cinema'] ?>">
          </div>
          <div class="sched-vdivider"></div>
        <?php endif; ?>

        <!-- Date pills -->
        <div class="sched-date-scroll">
          <?php foreach ($availableDates as $d):
            $p = datePill($d); ?>
            <button type="submit" name="date" value="<?= $p['value'] ?>"
              class="sched-date-btn <?= ($selectedDate===$p['value'])?'active':'' ?>">
              <span class="sched-date-m"><?= $p['month'] ?></span>
              <span class="sched-date-d"><?= $p['day'] ?></span>
              <span class="sched-date-w"><?= $p['dow'] ?></span>
            </button>
          <?php endforeach; ?>
        </div>
      </form>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- ═══════════════════ JADWAL ═══════════════════ -->
<div id="jadwal" class="container pb-5"
     style="<?= count($availableDates) > 0 ? 'padding-top: 24px;' : 'padding-top: 48px;' ?>">

  <?php if (count($availableDates) === 0): ?>
    <div class="sched-empty card-glass mx-auto" style="max-width:480px">
      <i class="bi bi-calendar-x fs-1 mb-2"></i>
      <div>Belum ada jadwal tayang untuk film ini.</div>
    </div>

  <?php elseif (count($scheduleGroups) === 0): ?>
    <div class="sched-empty card-glass mx-auto" style="max-width:480px">
      <i class="bi bi-clock-history fs-1 mb-2"></i>
      <div>Semua jadwal untuk hari ini sudah tidak tersedia.</div>
      <div class="mt-1" style="font-size:12px;opacity:.6;">Coba pilih tanggal lain.</div>
    </div>

  <?php else: ?>
    <?php foreach ($scheduleGroups as $cid => $group): ?>
      <div class="sched-group card-glass mb-3">
        <div class="sched-group-header"
          onclick="this.closest('.sched-group').classList.toggle('collapsed')">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-building" style="color:var(--c4-primary)"></i>
            <span class="sched-gname"><?= htmlspecialchars($group['cinema_name']) ?></span>
            <?php if ($group['cinema_city']): ?>
              <span class="sched-gcity"><?= htmlspecialchars($group['cinema_city']) ?></span>
            <?php endif; ?>
          </div>
          <i class="bi bi-chevron-up sched-caret"></i>
        </div>
        <div class="sched-group-body">
          <?php $keys = array_keys($group['studios']); $last = count($keys)-1; ?>
          <?php foreach ($keys as $si => $sname): ?>
            <?php $sd = $group['studios'][$sname]; ?>
            <div class="sched-row">
              <div class="sched-row-label">
                <span class="sched-sname"><?= htmlspecialchars(strtoupper($sname)) ?>&nbsp;2D</span>
                <span class="sched-sprice">&nbsp;•&nbsp; Rp <?= number_format((float)$sd['price'],0,',','.') ?></span>
              </div>
              <div class="sched-times">
                <?php foreach ($sd['schedules'] as $sch): ?>
                  <?php
                    $t   = date('H:i', strtotime($sch['show_time']));
                    $url = "booking.php?slug=".urlencode($slug)."&schedule=".urlencode($sch['id_schedule']);
                    if (!$isLoggedIn) $url = "join-us.php?mode=login&next=".urlencode($url);
                  ?>
                  <a href="<?= htmlspecialchars($url) ?>" class="sched-time-btn"><?= htmlspecialchars($t) ?></a>
                <?php endforeach; ?>
              </div>
            </div>
            <?php if ($si < $last): ?><hr class="sched-row-hr"><?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>
<?php endif; /* !isUpcoming */ ?>

<?php endif; /* $movie */ ?>

<!-- Trailer Modal -->
<div class="modal fade" id="trailerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-body p-0 position-relative">
        <button type="button" class="btn-close btn-close-white position-absolute end-0 m-3"
          data-bs-dismiss="modal"></button>
        <div class="ratio ratio-16x9">
          <iframe id="trailerFrame" src="" allow="autoplay; encrypted-media" allowfullscreen></iframe>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  /* Synopsis toggle → scrollable */
  window.toggleSyn = function () {
    var box = document.getElementById('synBox');
    var btn = document.getElementById('synBtn');
    if (!box) return;
    box.classList.toggle('expanded');
    btn.textContent = box.classList.contains('expanded') ? 'See Less' : 'See More';
  };

  /* Trailer */
  var trailerModal = document.getElementById('trailerModal');
  var trailerFrame = document.getElementById('trailerFrame');
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.watch-trailer');
    if (!btn) return;
    e.preventDefault();
    var url = btn.getAttribute('data-trailer') || '';
    trailerFrame.src = url ? url + (url.includes('?') ? '&' : '?') + 'autoplay=1' : '';
  }, true);
  if (trailerModal) {
    trailerModal.addEventListener('hidden.bs.modal', function () { trailerFrame.src = ''; });
  }
}());
</script>

<?php include '../app/views/partials/public/footer.php'; ?>