<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user'])) {
  header("Location: join-us.php?mode=login");
  exit;
}

require '../app/config/koneksi.php';

// Samakan timezone PHP dan MySQL supaya booking pending tidak langsung terbaca expired.
@$conn->query("SET time_zone = '+07:00'");

$title  = "CINEM4 - Booking";
$active = "movies";

function e($value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
  return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function nowJakarta(): DateTimeImmutable
{
  return new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
}

function parseDateJakarta($datetime): ?DateTimeImmutable
{
  if (empty($datetime)) {
    return null;
  }

  try {
    return new DateTimeImmutable((string) $datetime, new DateTimeZone('Asia/Jakarta'));
  } catch (Throwable $e) {
    return null;
  }
}

function formatDateId($date): string
{
  if (empty($date)) {
    return '-';
  }

  $timestamp = strtotime((string) $date);
  return $timestamp ? date('D, d M Y', $timestamp) : '-';
}

function formatTimeId($time): string
{
  if (empty($time)) {
    return '-';
  }

  $timestamp = strtotime((string) $time);
  return $timestamp ? date('H:i', $timestamp) : '-';
}

function expireOldBookingsForSchedule(mysqli $conn, int $scheduleId): void
{
  if ($scheduleId <= 0) {
    return;
  }

  $now = nowJakarta()->format('Y-m-d H:i:s');

  // Ubah booking pending yang sudah lewat batas bayar menjadi expired.
  $stmt = $conn->prepare("
    UPDATE bookings
    SET payment_status = 'expired',
        booking_status = 'cancelled',
        cancelled_at = COALESCE(cancelled_at, ?)
    WHERE id_schedule = ?
      AND payment_status = 'pending'
      AND expires_at IS NOT NULL
      AND expires_at <= ?
  ");
  $stmt->bind_param("sis", $now, $scheduleId, $now);
  $stmt->execute();
  $stmt->close();

  // Release seat lock dari booking yang sudah tidak aktif.
  $stmt = $conn->prepare("
    DELETE bs
    FROM booking_seats bs
    JOIN bookings b ON b.id_booking = bs.id_booking
    WHERE bs.id_schedule = ?
      AND (
        b.payment_status IN ('expired', 'cancelled', 'failed')
        OR b.booking_status = 'cancelled'
      )
  ");
  $stmt->bind_param("i", $scheduleId);
  $stmt->execute();
  $stmt->close();
}

function reservedSeatStatuses(mysqli $conn, int $scheduleId): array
{
  $reserved = [];

  if ($scheduleId <= 0) {
    return $reserved;
  }

  $now = nowJakarta()->format('Y-m-d H:i:s');

  $stmt = $conn->prepare("
    SELECT DISTINCT bs.seat_code, b.payment_status
    FROM booking_seats bs
    JOIN bookings b ON b.id_booking = bs.id_booking
    WHERE bs.id_schedule = ?
      AND (
        b.payment_status = 'paid'
        OR (
          b.payment_status = 'pending'
          AND b.expires_at IS NOT NULL
          AND b.expires_at > ?
        )
      )
  ");
  $stmt->bind_param("is", $scheduleId, $now);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    $seatCode = (string) $row['seat_code'];
    $reserved[$seatCode] = $row['payment_status'] === 'paid' ? 'booked' : 'held';
  }

  $stmt->close();

  return $reserved;
}

function generateSeatRows(int $capacity, int $cols = 8): array
{
  $capacity = max(0, $capacity);
  $rowCount = (int) ceil($capacity / $cols);
  $rows = [];
  $seatNumber = 0;

  for ($i = 0; $i < $rowCount; $i++) {
    $rowLabel = chr(65 + $i);
    $rows[$rowLabel] = [];

    for ($col = 1; $col <= $cols; $col++) {
      $seatNumber++;

      if ($seatNumber > $capacity) {
        break;
      }

      $rows[$rowLabel][] = $rowLabel . $col;
    }
  }

  return $rows;
}

function isScheduleClosedForBooking(?array $schedule): bool
{
  if (!$schedule) {
    return true;
  }

  if (($schedule['status'] ?? '') !== 'open' || (int) ($schedule['is_active'] ?? 0) !== 1) {
    return true;
  }

  $showDateTime = parseDateJakarta(($schedule['show_date'] ?? '') . ' ' . ($schedule['show_time'] ?? ''));
  $closeMinutes = (int) ($schedule['booking_close_minutes'] ?? 30);

  if ($showDateTime && nowJakarta()->getTimestamp() >= ($showDateTime->getTimestamp() - ($closeMinutes * 60))) {
    return true;
  }

  return false;
}

/* ── Ambil schedule dari DB ── */
$scheduleId = (int) ($_GET['schedule'] ?? 0);
$slug       = trim((string) ($_GET['slug'] ?? ''));

$schedule = null;
$movie    = null;

if ($scheduleId > 0) {
  $stmt = $conn->prepare("
    SELECT s.*, m.title, m.slug, m.genre, m.duration_minute,
           m.poster, m.backdrop, m.rating_age,
           c.name AS cinema_name, c.city AS cinema_city
    FROM schedules s
    JOIN movies m ON m.id_movie = s.id_movie
    JOIN cinemas c ON c.id_cinema = s.id_cinema
    WHERE s.id_schedule = ?
      AND s.is_active = 1
    LIMIT 1
  ");
  $stmt->bind_param("i", $scheduleId);
  $stmt->execute();
  $schedule = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($schedule) {
    $movie = $schedule;
    $slug  = $schedule['slug'];
  }
}

/* Fallback: ambil dari slug jika schedule tidak ditemukan */
if (!$movie && $slug !== '') {
  $stmt = $conn->prepare("SELECT * FROM movies WHERE slug = ? AND is_active = 1 LIMIT 1");
  $stmt->bind_param("s", $slug);
  $stmt->execute();
  $movie = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

include '../app/views/partials/public/head.php';
include '../app/views/partials/public/navbar.php';

if (!$movie || !$schedule) {
  ?>
  <div class="container py-5">
    <div class="card-glass p-4 p-md-5 text-center">
      <div class="display-6 mb-3"><i class="bi bi-ticket-perforated"></i></div>
      <h1 class="h4 fw-bold mb-2">Jadwal tidak ditemukan</h1>
      <p class="text-secondary mb-4">Silakan kembali ke halaman film dan pilih jadwal tayang yang masih tersedia.</p>
      <a href="movies.php" class="btn btn-primary rounded-pill px-4">Lihat Film</a>
    </div>
  </div>
  <?php
  include '../app/views/partials/public/footer.php';
  exit;
}

/* ── Info tampilan ── */
$titleM   = $movie['title'] ?? '';
$genre    = $movie['genre'] ?? '';
$poster   = $movie['poster'] ?? '';
$backdrop = !empty($movie['backdrop']) ? $movie['backdrop'] : $poster;
$rating   = $movie['rating_age'] ?? '';

$durationText = '';
if (!empty($movie['duration_minute']) && (int) $movie['duration_minute'] > 0) {
  $h = floor((int) $movie['duration_minute'] / 60);
  $m = (int) $movie['duration_minute'] % 60;
  $durationText = $h > 0 ? "{$h}j {$m}m" : "{$m}m";
}

$cinemaName = $schedule['cinema_name'] ?? '-';
$cinemaCity = $schedule['cinema_city'] ?? '';
$studioName = $schedule['studio_name'] ?? '-';
$showDate   = formatDateId($schedule['show_date'] ?? null);
$showTime   = formatTimeId($schedule['show_time'] ?? null);
$price      = (float) ($schedule['price'] ?? 0);
$capacity   = (int) ($schedule['seat_capacity'] ?? 40);
$closeMinutes = (int) ($schedule['booking_close_minutes'] ?? 30);

/* ── Seat lock booking ── */
expireOldBookingsForSchedule($conn, $scheduleId);
$seatStatusMap = reservedSeatStatuses($conn, $scheduleId);
$seatRows = generateSeatRows($capacity, 8);
$bookedCount = 0;
$heldCount = 0;
foreach ($seatStatusMap as $status) {
  if ($status === 'booked') {
    $bookedCount++;
  } elseif ($status === 'held') {
    $heldCount++;
  }
}
$availableCount = max(0, $capacity - count($seatStatusMap));
$isBookingClosed = isScheduleClosedForBooking($schedule);
$maxSelectableSeats = 10;
?>

<section class="booking-pro-hero">
  <div class="booking-pro-bg" style="background-image:url('<?= e($backdrop) ?>')"></div>
  <div class="booking-pro-shade"></div>

  <div class="container position-relative">
    <div class="booking-stepper mb-4">
      <div class="step-item is-active">
        <span>1</span>
        <div>Pilih Kursi</div>
      </div>
      <div class="step-line"></div>
      <div class="step-item">
        <span>2</span>
        <div>Checkout</div>
      </div>
      <div class="step-line"></div>
      <div class="step-item">
        <span>3</span>
        <div>Pembayaran</div>
      </div>
    </div>

    <div class="row g-4 align-items-end">
      <div class="col-lg-8">
        <a href="movie-detail.php?slug=<?= urlencode($slug) ?>" class="booking-back-link mb-3">
          <i class="bi bi-arrow-left"></i> Kembali ke detail film
        </a>
        <h1 class="display-6 fw-black text-uppercase mb-2"><?= e($titleM) ?></h1>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <?php if ($genre): ?><span class="booking-chip"><?= e(strtoupper($genre)) ?></span><?php endif; ?>
          <?php if ($durationText): ?><span class="booking-chip"><i class="bi bi-clock"></i> <?= e($durationText) ?></span><?php endif; ?>
          <?php if ($rating): ?><span class="booking-chip"><i class="bi bi-shield-check"></i> <?= e($rating) ?></span><?php endif; ?>
        </div>
        <div class="booking-info-grid">
          <div>
            <span>Cinema</span>
            <strong><?= e($cinemaName) ?><?= $cinemaCity ? ' • ' . e($cinemaCity) : '' ?></strong>
          </div>
          <div>
            <span>Studio</span>
            <strong><?= e($studioName) ?></strong>
          </div>
          <div>
            <span>Jadwal</span>
            <strong><?= e($showDate) ?> • <?= e($showTime) ?></strong>
          </div>
          <div>
            <span>Harga</span>
            <strong><?= e(rupiah($price)) ?> / kursi</strong>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="booking-mini-card">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-secondary small">Ketersediaan Kursi</span>
            <span class="badge bg-primary rounded-pill"><?= (int) $capacity ?> kursi</span>
          </div>
          <div class="availability-bars">
            <div>
              <strong><?= (int) $availableCount ?></strong>
              <span>Tersedia</span>
            </div>
            <div>
              <strong><?= (int) $heldCount ?></strong>
              <span>Ditahan</span>
            </div>
            <div>
              <strong><?= (int) $bookedCount ?></strong>
              <span>Terjual</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<main class="container booking-seat-page py-4 pb-5">
  <?php if ($isBookingClosed): ?>
    <div class="alert alert-warning border-0 rounded-4 mb-4">
      <div class="d-flex gap-2">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>
          <strong>Booking untuk jadwal ini sudah ditutup.</strong>
          <div class="small">Booking biasanya ditutup <?= (int) $closeMinutes ?> menit sebelum film mulai. Pilih jadwal lain yang masih tersedia.</div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="row g-4 align-items-start">
    <div class="col-xl-8">
      <div class="card-glass booking-seat-card p-3 p-md-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
          <div>
            <div class="text-secondary small mb-1">Seat Map</div>
            <h2 class="h5 fw-bold mb-1">Pilih kursi favorit Anda</h2>
            <p class="text-secondary small mb-0">Maksimal <?= (int) $maxSelectableSeats ?> kursi dalam satu transaksi.</p>
          </div>
          <button type="button" class="btn btn-sm btn-outline-light border-secondary rounded-pill px-3" id="clearBtn">
            <i class="bi bi-x-circle me-1"></i> Reset Pilihan
          </button>
        </div>

        <div class="cinema-screen">
          <div class="screen-glow"></div>
          <img src="assets/ui/screen-3.png" alt="Cinema Screen">
          <span>SCREEN</span>
        </div>

        <div class="seat-map-scroller">
          <div class="seat-wrap-pro">
            <?php foreach ($seatRows as $rowLabel => $seatsInRow): ?>
              <div class="seat-row-pro">
                <span class="row-label"><?= e($rowLabel) ?></span>
                <div class="seat-buttons">
                  <?php foreach ($seatsInRow as $code): ?>
                    <?php
                      $status = $seatStatusMap[$code] ?? 'available';
                      $isUnavailable = in_array($status, ['booked', 'held'], true) || $isBookingClosed;
                      $class = 'seat';
                      if ($status === 'booked') {
                        $class .= ' is-booked';
                      } elseif ($status === 'held') {
                        $class .= ' is-held';
                      }
                    ?>
                    <button type="button"
                      class="<?= e($class) ?>"
                      data-seat="<?= e($code) ?>"
                      data-status="<?= e($status) ?>"
                      title="<?= e($code) ?><?= $status === 'booked' ? ' - Terjual' : ($status === 'held' ? ' - Sedang ditahan' : '') ?>"
                      <?= $isUnavailable ? 'disabled' : '' ?>>
                      <?= e($code) ?>
                    </button>
                  <?php endforeach; ?>
                </div>
                <span class="row-label row-label-right"><?= e($rowLabel) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="seat-legend-pro mt-4">
          <span><i class="legend-dot available"></i> Tersedia</span>
          <span><i class="legend-dot selected"></i> Dipilih</span>
          <span><i class="legend-dot held"></i> Ditahan sementara</span>
          <span><i class="legend-dot booked"></i> Terjual</span>
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <aside class="booking-summary-card card-glass p-3 p-md-4">
        <div class="d-flex align-items-center gap-3 mb-4">
          <?php if ($poster): ?>
            <img src="<?= e($poster) ?>" alt="<?= e($titleM) ?>" class="summary-poster">
          <?php endif; ?>
          <div>
            <div class="text-secondary small">Ringkasan Booking</div>
            <h3 class="h6 fw-bold mb-1"><?= e($titleM) ?></h3>
            <div class="small text-secondary"><?= e($studioName) ?> • <?= e($showTime) ?></div>
          </div>
        </div>

        <div class="summary-list mb-3">
          <div>
            <span>Cinema</span>
            <strong><?= e($cinemaName) ?></strong>
          </div>
          <div>
            <span>Tanggal</span>
            <strong><?= e($showDate) ?></strong>
          </div>
          <div>
            <span>Harga / Kursi</span>
            <strong><?= e(rupiah($price)) ?></strong>
          </div>
        </div>

        <div class="selected-box mb-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-secondary small">Kursi Dipilih</span>
            <span class="badge bg-dark border border-secondary" id="selectedCount">0</span>
          </div>
          <div id="selectedChips" class="selected-chips">
            <span class="text-secondary small">Belum ada kursi dipilih.</span>
          </div>
        </div>

        <div class="summary-total mb-4">
          <span>Subtotal</span>
          <strong id="subtotalText">Rp 0</strong>
        </div>

        <a class="btn btn-primary rounded-pill w-100 py-3 disabled" id="nextBtn" aria-disabled="true"
          href="payment.php?schedule=<?= (int) $scheduleId ?>&slug=<?= urlencode($slug) ?>">
          Pilih kursi terlebih dahulu
        </a>

        <div class="small text-secondary mt-3 d-flex gap-2">
          <i class="bi bi-info-circle"></i>
          <span>Promo akan dimasukkan pada tahap checkout sebelum booking dikonfirmasi.</span>
        </div>
      </aside>
    </div>
  </div>
</main>

<style>
.fw-black { font-weight: 900; }
.booking-pro-hero {
  position: relative;
  overflow: hidden;
  padding: 42px 0 48px;
  border-bottom: 1px solid rgba(255,255,255,.1);
}
.booking-pro-bg {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  transform: scale(1.04);
  filter: blur(2px);
  opacity: .58;
}
.booking-pro-shade {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(900px 420px at 18% 12%, rgba(31,111,255,.32), transparent 60%),
    linear-gradient(90deg, rgba(7,11,20,.98), rgba(7,11,20,.72) 54%, rgba(7,11,20,.86));
}
.booking-stepper {
  display: flex;
  align-items: center;
  gap: 12px;
  width: min(680px, 100%);
}
.step-item {
  display: flex;
  align-items: center;
  gap: 9px;
  color: rgba(255,255,255,.55);
  font-size: 13px;
  white-space: nowrap;
}
.step-item span {
  width: 30px;
  height: 30px;
  display: grid;
  place-items: center;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,.2);
  background: rgba(255,255,255,.06);
  font-weight: 800;
}
.step-item.is-active { color: #fff; }
.step-item.is-active span {
  background: var(--c4-primary);
  border-color: var(--c4-primary);
  box-shadow: 0 0 24px rgba(31,111,255,.55);
}
.step-line {
  height: 1px;
  flex: 1;
  background: linear-gradient(90deg, rgba(31,111,255,.9), rgba(255,255,255,.16));
}
.booking-back-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: rgba(255,255,255,.78);
  text-decoration: none;
  font-size: 14px;
}
.booking-back-link:hover { color: #fff; }
.booking-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid rgba(255,255,255,.14);
  background: rgba(255,255,255,.08);
  border-radius: 999px;
  padding: 7px 12px;
  font-size: 12px;
  font-weight: 700;
  color: rgba(255,255,255,.9);
}
.booking-info-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}
.booking-info-grid > div,
.booking-mini-card {
  border: 1px solid rgba(255,255,255,.13);
  background: rgba(255,255,255,.07);
  border-radius: 18px;
  padding: 14px 16px;
  backdrop-filter: blur(10px);
}
.booking-info-grid span,
.summary-list span,
.summary-total span { color: rgba(255,255,255,.56); font-size: 12px; display: block; }
.booking-info-grid strong,
.summary-list strong { display: block; margin-top: 4px; font-size: 14px; }
.availability-bars {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}
.availability-bars div {
  border-radius: 14px;
  background: rgba(0,0,0,.24);
  padding: 12px;
  text-align: center;
}
.availability-bars strong { display: block; font-size: 22px; }
.availability-bars span { display: block; color: rgba(255,255,255,.58); font-size: 12px; }
.booking-seat-card { overflow: hidden; }
.cinema-screen {
  position: relative;
  display: grid;
  place-items: center;
  margin: 6px auto 34px;
  min-height: 90px;
}
.cinema-screen .screen-glow {
  position: absolute;
  width: min(560px, 92%);
  height: 58px;
  border-radius: 50%;
  background: radial-gradient(ellipse, rgba(31,111,255,.33), rgba(31,111,255,.08) 52%, transparent 72%);
  filter: blur(18px);
}
.cinema-screen img {
  position: relative;
  z-index: 1;
  width: min(500px, 94%);
  filter: drop-shadow(0 0 20px rgba(31,111,255,.45));
}
.cinema-screen span {
  position: absolute;
  bottom: 8px;
  z-index: 2;
  letter-spacing: 8px;
  color: rgba(255,255,255,.42);
  font-size: 11px;
  font-weight: 800;
}
.seat-map-scroller { overflow-x: auto; padding-bottom: 8px; }
.seat-wrap-pro { width: max-content; min-width: min(100%, 560px); margin: 0 auto; }
.seat-row-pro {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-bottom: 10px;
}
.row-label {
  width: 24px;
  height: 24px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.12);
  color: rgba(255,255,255,.6);
  font-size: 11px;
  font-weight: 800;
  flex: 0 0 auto;
}
.seat-buttons {
  display: flex;
  justify-content: center;
  gap: 10px;
}
.seat-buttons .seat:nth-child(4) { margin-right: 22px; }
.booking-seat-page .seat.is-held {
  cursor: not-allowed;
  opacity: .78;
  color: rgba(255,255,255,.54);
  --seat-fill: rgba(255,193,7,.28);
  --seat-stroke: rgba(255,193,7,.85);
}
.booking-seat-page .seat:disabled { pointer-events: none; }
.seat-legend-pro {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 14px 22px;
  color: rgba(255,255,255,.76);
  font-size: 13px;
}
.seat-legend-pro span { display: inline-flex; align-items: center; gap: 8px; }
.legend-dot {
  width: 15px;
  height: 15px;
  border-radius: 999px;
  display: inline-block;
  border: 1px solid rgba(255,255,255,.28);
  background: rgba(255,255,255,.1);
}
.legend-dot.selected { background: var(--c4-primary); border-color: rgba(255,255,255,.75); box-shadow: 0 0 12px rgba(31,111,255,.65); }
.legend-dot.held { background: rgba(255,193,7,.7); border-color: rgba(255,193,7,.9); }
.legend-dot.booked { background: rgba(255,255,255,.22); opacity: .55; }
.booking-summary-card {
  position: sticky;
  top: 92px;
}
.summary-poster {
  width: 68px;
  height: 92px;
  object-fit: cover;
  border-radius: 14px;
  border: 1px solid rgba(255,255,255,.14);
}
.summary-list {
  display: grid;
  gap: 12px;
}
.summary-list > div {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(255,255,255,.08);
}
.summary-list strong { text-align: right; }
.selected-box {
  border: 1px solid rgba(255,255,255,.11);
  background: rgba(0,0,0,.18);
  border-radius: 16px;
  padding: 14px;
}
.selected-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  min-height: 28px;
}
.selected-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border-radius: 999px;
  padding: 6px 10px;
  background: rgba(31,111,255,.16);
  border: 1px solid rgba(31,111,255,.42);
  color: #fff;
  font-size: 12px;
  font-weight: 800;
}
.summary-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid rgba(255,255,255,.1);
  padding-top: 16px;
}
.summary-total strong { font-size: 24px; color: var(--c4-primary); }
#nextBtn.disabled { pointer-events: none; opacity: .55; }
@media (max-width: 1199.98px) {
  .booking-summary-card { position: static; }
}
@media (max-width: 991.98px) {
  .booking-info-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .booking-stepper { width: 100%; }
}
@media (max-width: 575.98px) {
  .booking-pro-hero { padding: 28px 0 34px; }
  .booking-stepper { gap: 7px; }
  .step-item div { display: none; }
  .step-line { min-width: 24px; }
  .booking-info-grid { grid-template-columns: 1fr; }
  .seat-buttons { gap: 7px; }
  .seat-buttons .seat:nth-child(4) { margin-right: 14px; }
  .row-label-right { display: none; }
}
</style>

<script>
(function () {
  const price = <?= json_encode($price) ?>;
  const scheduleId = <?= json_encode($scheduleId) ?>;
  const slug = <?= json_encode($slug) ?>;
  const maxSeats = <?= json_encode($maxSelectableSeats) ?>;
  const bookingClosed = <?= $isBookingClosed ? 'true' : 'false' ?>;
  const selected = new Set();

  const selectedCount = document.getElementById('selectedCount');
  const selectedChips = document.getElementById('selectedChips');
  const subtotalText = document.getElementById('subtotalText');
  const nextBtn = document.getElementById('nextBtn');
  const clearBtn = document.getElementById('clearBtn');

  function rupiah(value) {
    return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
  }

  function sortedSeats() {
    return Array.from(selected).sort((a, b) => a.localeCompare(b, 'en', { numeric: true }));
  }

  function renderSelectedChips(seats) {
    selectedChips.innerHTML = '';

    if (seats.length === 0) {
      const empty = document.createElement('span');
      empty.className = 'text-secondary small';
      empty.textContent = 'Belum ada kursi dipilih.';
      selectedChips.appendChild(empty);
      return;
    }

    seats.forEach(function (seat) {
      const chip = document.createElement('span');
      chip.className = 'selected-chip';
      chip.innerHTML = '<i class="bi bi-ticket-perforated"></i>' + seat;
      selectedChips.appendChild(chip);
    });
  }

  function sync() {
    const seats = sortedSeats();
    const subtotal = seats.length * price;

    selectedCount.textContent = seats.length;
    renderSelectedChips(seats);
    subtotalText.textContent = rupiah(subtotal);

    const disabled = seats.length === 0 || bookingClosed;
    nextBtn.classList.toggle('disabled', disabled);
    nextBtn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    nextBtn.textContent = bookingClosed ? 'Booking sudah ditutup' : (disabled ? 'Pilih kursi terlebih dahulu' : 'Lanjut ke Checkout');

    if (!disabled) {
      nextBtn.href = 'payment.php?schedule=' + encodeURIComponent(scheduleId) +
        '&slug=' + encodeURIComponent(slug) +
        '&seats=' + encodeURIComponent(seats.join(','));
    }
  }

  document.querySelectorAll('.booking-seat-page .seat:not(:disabled)').forEach(function (button) {
    button.addEventListener('click', function () {
      const code = button.getAttribute('data-seat');

      if (selected.has(code)) {
        selected.delete(code);
        button.classList.remove('is-selected');
        sync();
        return;
      }

      if (selected.size >= maxSeats) {
        alert('Maksimal ' + maxSeats + ' kursi dalam satu transaksi.');
        return;
      }

      selected.add(code);
      button.classList.add('is-selected');
      sync();
    });
  });

  clearBtn.addEventListener('click', function () {
    selected.clear();
    document.querySelectorAll('.booking-seat-page .seat.is-selected').forEach(function (button) {
      button.classList.remove('is-selected');
    });
    sync();
  });

  sync();
}());
</script>

<?php include '../app/views/partials/public/footer.php'; ?>
