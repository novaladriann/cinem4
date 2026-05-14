<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    header('Location: join-us.php?mode=login');
    exit;
}

require '../app/config/koneksi.php';
@$conn->query("SET time_zone = '+07:00'");

$title = 'CINEM4 - E-Ticket';
$active = '';
$userId = (int) $_SESSION['user_id'];
$bookingCode = trim((string) ($_GET['booking'] ?? ''));

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatDateId($date): string
{
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime((string) $date);
    return $timestamp ? date('d M Y', $timestamp) : '-';
}

function formatTimeId($time): string
{
    if (empty($time)) {
        return '-';
    }

    $timestamp = strtotime((string) $time);
    return $timestamp ? date('H:i', $timestamp) : '-';
}

function durationText($minutes): string
{
    $minutes = (int) $minutes;

    if ($minutes <= 0) {
        return '-';
    }

    $hours = intdiv($minutes, 60);
    $mins = $minutes % 60;

    if ($hours > 0 && $mins > 0) {
        return $hours . 'j ' . $mins . 'm';
    }

    if ($hours > 0) {
        return $hours . 'j';
    }

    return $mins . 'm';
}

function code39Svg(string $value): string
{
    $patterns = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn', 'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw',
        'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn',
        'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn',
        'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn', 'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw',
        'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '$' => 'nwnwnwnnn',
        '/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn', '%' => 'nnnwnwnwn', '*' => 'nwnnwnwnn',
    ];

    $value = strtoupper($value);
    $value = preg_replace('/[^0-9A-Z\-\. \$\/\+\%]/', '-', $value);
    $value = '*' . $value . '*';

    $narrow = 2;
    $wide = 5;
    $height = 62;
    $x = 16;
    $rects = [];

    foreach (str_split($value) as $char) {
        $pattern = $patterns[$char] ?? $patterns['-'];
        $isBar = true;

        foreach (str_split($pattern) as $unit) {
            $width = $unit === 'w' ? $wide : $narrow;

            if ($isBar) {
                $rects[] = '<rect x="' . $x . '" y="8" width="' . $width . '" height="' . $height . '" rx="1" />';
            }

            $x += $width;
            $isBar = !$isBar;
        }

        $x += $narrow;
    }

    $svgWidth = $x + 16;
    $label = e(trim($value, '*'));

    return '<svg class="ticket-barcode-svg" viewBox="0 0 ' . $svgWidth . ' 90" role="img" aria-label="Barcode tiket ' . $label . '" xmlns="http://www.w3.org/2000/svg">'
        . '<rect width="100%" height="100%" fill="#ffffff" rx="5" />'
        . '<g fill="#111827">' . implode('', $rects) . '</g>'
        . '<text x="50%" y="84" text-anchor="middle" font-family="Arial, sans-serif" font-size="11" font-weight="700" fill="#111827">' . $label . '</text>'
        . '</svg>';
}

function fetchTicket(mysqli $conn, int $userId, string $bookingCode): ?array
{
    $stmt = $conn->prepare("\n        SELECT\n            b.*,\n            s.show_date, s.show_time, s.studio_name,\n            m.title AS movie_title, m.poster, m.genre, m.duration_minute, m.rating_age,\n            c.name AS cinema_name, c.city AS cinema_city,\n            seat_data.seats\n        FROM bookings b\n        JOIN schedules s ON s.id_schedule = b.id_schedule\n        JOIN movies m ON m.id_movie = s.id_movie\n        JOIN cinemas c ON c.id_cinema = s.id_cinema\n        LEFT JOIN (\n            SELECT id_booking, GROUP_CONCAT(seat_code ORDER BY seat_code SEPARATOR ', ') AS seats\n            FROM booking_seats\n            GROUP BY id_booking\n        ) seat_data ON seat_data.id_booking = b.id_booking\n        WHERE b.booking_code = ?\n          AND b.id_user = ?\n        LIMIT 1\n    ");
    $stmt->bind_param('si', $bookingCode, $userId);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $ticket ?: null;
}

$ticket = null;
$errorMessage = '';

if ($bookingCode === '') {
    $errorMessage = 'Kode booking tidak ditemukan.';
} else {
    $ticket = fetchTicket($conn, $userId, $bookingCode);

    if (!$ticket) {
        $errorMessage = 'Booking tidak ditemukan atau bukan milik akun Anda.';
    }
}

$paymentStatus = $ticket ? (string) ($ticket['payment_status'] ?? '') : '';
$isPaid = $paymentStatus === 'paid';
$posterSrc = $ticket ? trim((string) ($ticket['poster'] ?? '')) : '';
$seats = $ticket ? trim((string) ($ticket['seats'] ?? '')) : '';
$cinemaLine = $ticket ? trim((string) ($ticket['cinema_name'] ?? '')) : '-';
if ($ticket && !empty($ticket['cinema_city'])) {
    $cinemaLine .= ' • ' . (string) $ticket['cinema_city'];
}

$movieMeta = [];
if ($ticket && !empty($ticket['duration_minute'])) {
    $movieMeta[] = durationText($ticket['duration_minute']);
}
if ($ticket && !empty($ticket['rating_age'])) {
    $movieMeta[] = (string) $ticket['rating_age'];
}
if ($ticket && !empty($ticket['genre'])) {
    $movieMeta[] = (string) $ticket['genre'];
}
$movieMetaText = $movieMeta ? implode(' • ', $movieMeta) : 'Movie Ticket';

require '../app/views/partials/public/head.php';
require '../app/views/partials/public/navbar.php';
?>

<main class="eticket-page py-4 py-lg-5">
    <div class="container ticket-container">
        <div class="ticket-toolbar no-print">
            <a href="dashboard.php" class="btn btn-outline-light border-secondary rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <div class="toolbar-title">
                <span>CINEM4</span>
                <h1>E-Ticket</h1>
            </div>
            <?php if ($isPaid): ?>
                <button type="button" class="btn btn-primary rounded-pill px-3" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
            <?php endif; ?>
        </div>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger rounded-4 border-0"><?= e($errorMessage) ?></div>
        <?php elseif (!$isPaid): ?>
            <div class="eticket-state-card">
                <div class="eticket-state-icon"><i class="bi bi-exclamation-triangle"></i></div>
                <h2 class="h4 fw-bold mb-2">E-ticket belum tersedia</h2>
                <?php if ($paymentStatus === 'pending'): ?>
                    <p class="text-secondary mb-4">Booking ini masih menunggu pembayaran.</p>
                    <a href="payment.php?booking=<?= urlencode((string) $ticket['booking_code']) ?>" class="btn btn-primary rounded-pill px-4">
                        <i class="bi bi-credit-card me-1"></i> Lanjutkan Pembayaran
                    </a>
                <?php else: ?>
                    <p class="text-secondary mb-4">Booking ini berstatus <?= e($paymentStatus ?: '-') ?>.</p>
                    <a href="movies.php" class="btn btn-primary rounded-pill px-4">Booking Ulang</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <section class="minimal-ticket">
                <div class="ticket-header">
                    <div class="poster-box <?= $posterSrc !== '' ? '' : 'empty' ?>">
                        <?php if ($posterSrc !== ''): ?>
                            <img src="<?= e($posterSrc) ?>" alt="<?= e($ticket['movie_title']) ?>">
                        <?php else: ?>
                            <i class="bi bi-film"></i>
                        <?php endif; ?>
                    </div>

                    <div class="movie-info">
                        <div class="ticket-label-row">
                            <span>CINEM4 E-TICKET</span>
                            <strong>PAID</strong>
                        </div>
                        <h2><?= e($ticket['movie_title']) ?></h2>
                        <p><?= e($movieMetaText) ?></p>
                    </div>
                </div>

                <div class="ticket-details">
                    <div>
                        <span>Cinema</span>
                        <strong><?= e($cinemaLine) ?></strong>
                    </div>
                    <div>
                        <span>Studio</span>
                        <strong><?= e($ticket['studio_name'] ?: '-') ?></strong>
                    </div>
                    <div>
                        <span>Tanggal</span>
                        <strong><?= e(formatDateId($ticket['show_date'])) ?></strong>
                    </div>
                    <div>
                        <span>Jam</span>
                        <strong><?= e(formatTimeId($ticket['show_time'])) ?></strong>
                    </div>
                    <div class="seat-cell">
                        <span>Kursi</span>
                        <strong><?= e($seats ?: ($ticket['total_seats'] . ' kursi')) ?></strong>
                    </div>
                    <div>
                        <span>Jumlah</span>
                        <strong><?= e((int) $ticket['total_seats']) ?> Ticket</strong>
                    </div>
                </div>

                <div class="ticket-code-row">
                    <span>Booking Code</span>
                    <strong><?= e($ticket['booking_code']) ?></strong>
                </div>

                <div class="barcode-section">
                    <?= code39Svg((string) $ticket['booking_code']) ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>

<style>
.eticket-page {
  min-height: calc(100vh - 120px);
  background:
    radial-gradient(760px 320px at 16% 0%, rgba(31,111,255,.18), transparent 70%),
    radial-gradient(720px 280px at 90% 8%, rgba(14,165,233,.10), transparent 64%);
}
.ticket-container {
  max-width: 860px;
}
.ticket-toolbar {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 16px;
  margin-bottom: 22px;
}
.ticket-toolbar .btn {
  width: auto;
  min-width: 118px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;
}
.ticket-toolbar > a {
  justify-self: start;
}
.ticket-toolbar > button {
  justify-self: end;
}
.toolbar-title {
  text-align: center;
}
.toolbar-title span {
  display: block;
  color: #60a5fa;
  font-size: .72rem;
  font-weight: 800;
  letter-spacing: .18em;
}
.toolbar-title h1 {
  margin: 0;
  font-size: 1.85rem;
  font-weight: 900;
}
.minimal-ticket {
  overflow: hidden;
  border-radius: 28px;
  border: 1px solid rgba(255,255,255,.13);
  background: rgba(15,23,42,.88);
  box-shadow: 0 24px 70px rgba(0,0,0,.34);
  backdrop-filter: blur(14px);
}
.ticket-header {
  display: grid;
  grid-template-columns: 116px minmax(0, 1fr);
  gap: 18px;
  align-items: center;
  padding: 24px;
}
.poster-box {
  width: 116px;
  height: 154px;
  overflow: hidden;
  border-radius: 20px;
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.11);
}
.poster-box img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.poster-box.empty {
  display: grid;
  place-items: center;
  color: rgba(255,255,255,.38);
  font-size: 2rem;
}
.ticket-label-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  margin-bottom: 12px;
}
.ticket-label-row span,
.ticket-label-row strong {
  border-radius: 999px;
  padding: 7px 11px;
  font-size: .72rem;
  font-weight: 800;
  letter-spacing: .08em;
}
.ticket-label-row span {
  color: #93c5fd;
  background: rgba(31,111,255,.16);
  border: 1px solid rgba(96,165,250,.20);
}
.ticket-label-row strong {
  color: #bbf7d0;
  background: rgba(34,197,94,.14);
  border: 1px solid rgba(74,222,128,.20);
}
.movie-info h2 {
  margin: 0;
  font-size: clamp(1.8rem, 5vw, 2.8rem);
  font-weight: 900;
  line-height: 1.05;
  letter-spacing: -.04em;
}
.movie-info p {
  margin: 9px 0 0;
  color: rgba(255,255,255,.62);
}
.ticket-details {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px 24px;
  padding: 20px 24px;
  background: rgba(255,255,255,.035);
  border-top: 1px solid rgba(255,255,255,.06);
  border-bottom: 1px dashed rgba(255,255,255,.16);
}
.ticket-details > div {
  min-width: 0;
  padding: 0;
  background: transparent;
}
.ticket-details span,
.ticket-code-row span {
  display: block;
  color: rgba(255,255,255,.48);
  font-size: .76rem;
  margin-bottom: 6px;
}
.ticket-details strong,
.ticket-code-row strong {
  color: #fff;
  font-size: 1rem;
  font-weight: 800;
}
.ticket-details .seat-cell strong {
  color: #93c5fd;
  font-size: 1.16rem;
  letter-spacing: .06em;
}
.ticket-code-row {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  align-items: center;
  padding: 16px 24px 10px;
}
.ticket-code-row strong {
  letter-spacing: .05em;
  word-break: break-word;
  text-align: right;
}
.barcode-section {
  padding: 10px 24px 24px;
}
.ticket-barcode-svg {
  width: 100%;
  max-height: 128px;
  display: block;
  border-radius: 8px;
  overflow: visible;
}
.eticket-state-card {
  max-width: 580px;
  margin: 32px auto;
  text-align: center;
  padding: 36px;
  border-radius: 28px;
  background: rgba(255,255,255,.045);
  border: 1px solid rgba(255,255,255,.12);
}
.eticket-state-icon {
  width: 68px;
  height: 68px;
  margin: 0 auto 18px;
  display: grid;
  place-items: center;
  border-radius: 22px;
  color: #facc15;
  background: rgba(250,204,21,.12);
  font-size: 1.8rem;
}
@media (max-width: 767.98px) {
  .ticket-toolbar {
    grid-template-columns: 1fr 1fr;
  }
  .toolbar-title {
    grid-column: 1 / -1;
    grid-row: 1;
    margin-bottom: 4px;
  }
  .ticket-toolbar > a {
    grid-column: 1;
    grid-row: 2;
  }
  .ticket-toolbar > button {
    grid-column: 2;
    grid-row: 2;
    justify-self: end;
  }
  .ticket-header {
    grid-template-columns: 88px minmax(0, 1fr);
    padding: 18px;
    gap: 14px;
  }
  .poster-box {
    width: 88px;
    height: 122px;
    border-radius: 16px;
  }
  .ticket-label-row span,
  .ticket-label-row strong {
    padding: 6px 9px;
    font-size: .64rem;
  }
  .ticket-details {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    padding: 18px;
  }
  .ticket-code-row {
    display: block;
    padding: 15px 18px 8px;
  }
  .ticket-code-row strong {
    display: block;
    margin-top: 6px;
    text-align: left;
  }
  .barcode-section {
    padding: 10px 18px 18px;
    overflow: visible;
  }
  .ticket-barcode-svg {
    border-radius: 6px;
    max-height: none;
  }
}
@media (max-width: 430px) {
  .ticket-header {
    grid-template-columns: 1fr;
  }
  .poster-box {
    width: 100%;
    max-width: 132px;
    height: 178px;
  }
  .movie-info h2 {
    font-size: 1.7rem;
  }
  .ticket-details {
    grid-template-columns: 1fr;
    gap: 14px;
  }
  .barcode-section {
    padding-left: 14px;
    padding-right: 14px;
  }
  .ticket-barcode-svg {
    border-radius: 5px;
  }
}
@media print {
  @page {
    size: A4 portrait;
    margin: 10mm;
  }
  html,
  body {
    width: 210mm;
    min-height: 0 !important;
    background: #fff !important;
    color: #111827 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .no-print,
  .cinem4-nav,
  nav,
  footer,
  #trailerModal {
    display: none !important;
  }
  .eticket-page {
    min-height: 0 !important;
    padding: 0 !important;
    background: #fff !important;
  }
  .container,
  .ticket-container {
    max-width: none !important;
    width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
  }
  .minimal-ticket {
    width: 100% !important;
    max-width: 184mm !important;
    margin: 0 auto !important;
    border-radius: 12px !important;
    border: 1px solid #d1d5db !important;
    background: #fff !important;
    color: #111827 !important;
    box-shadow: none !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    overflow: hidden !important;
  }
  .ticket-header {
    grid-template-columns: 72px minmax(0, 1fr) !important;
    gap: 12px !important;
    padding: 12px 14px !important;
  }
  .poster-box {
    width: 72px !important;
    height: 96px !important;
    border-radius: 8px !important;
    border-color: #d1d5db !important;
    background: #f3f4f6 !important;
  }
  .ticket-label-row {
    margin-bottom: 7px !important;
  }
  .ticket-label-row span,
  .ticket-label-row strong {
    padding: 4px 8px !important;
    font-size: 9px !important;
    border-color: #d1d5db !important;
    background: #fff !important;
    color: #111827 !important;
  }
  .movie-info h2 {
    font-size: 22px !important;
    color: #111827 !important;
    line-height: 1.05 !important;
  }
  .movie-info p {
    margin-top: 4px !important;
    font-size: 11px !important;
    color: #4b5563 !important;
  }
  .ticket-details {
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    gap: 10px 16px !important;
    padding: 10px 14px !important;
    background: #f9fafb !important;
    border-top: 1px solid #e5e7eb !important;
    border-bottom: 1px dashed #d1d5db !important;
  }
  .ticket-details > div {
    padding: 0 !important;
    background: transparent !important;
  }
  .ticket-details span,
  .ticket-code-row span {
    margin-bottom: 4px !important;
    font-size: 9px !important;
    color: #6b7280 !important;
  }
  .ticket-details strong,
  .ticket-code-row strong,
  .ticket-details .seat-cell strong {
    font-size: 12px !important;
    color: #111827 !important;
  }
  .ticket-code-row {
    padding: 10px 14px 5px !important;
  }
  .ticket-code-row strong {
    font-size: 13px !important;
  }
  .barcode-section {
    padding: 6px 14px 12px !important;
  }
  .ticket-barcode-svg {
    max-height: 92px !important;
    border: 1px solid #e5e7eb !important;
    border-radius: 8px !important;
  }
}
</style>

<?php require '../app/views/partials/public/footer.php'; ?>
