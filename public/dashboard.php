<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    header("Location: join-us.php?mode=login");
    exit;
}

require '../app/config/koneksi.php';

// Samakan timezone PHP dan MySQL agar status pending/expired konsisten.
@$conn->query("SET time_zone = '+07:00'");

$title = "CINEM4 - Profile";
$active = "";
$extra_css = ['assets/css/profile.css'];

$userId = (int) $_SESSION['user_id'];

// Filter riwayat booking sekarang berjalan client-side agar halaman tidak reload.
$activeFilter = 'all';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function formatDateId($date): string
{
    if (empty($date)) return '-';
    $timestamp = strtotime((string) $date);
    return $timestamp ? date('d M Y', $timestamp) : '-';
}

function formatTimeId($time): string
{
    if (empty($time)) return '-';
    $timestamp = strtotime((string) $time);
    return $timestamp ? date('H:i', $timestamp) : '-';
}


function formatDateTimeId($datetime): string
{
    if (empty($datetime)) return '-';
    $timestamp = strtotime((string) $datetime);
    return $timestamp ? date('d M Y H:i', $timestamp) : '-';
}

function nowJakarta(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
}

function parseDateJakarta($datetime): ?DateTimeImmutable
{
    if (empty($datetime)) return null;

    try {
        return new DateTimeImmutable((string) $datetime, new DateTimeZone('Asia/Jakarta'));
    } catch (Throwable $e) {
        return null;
    }
}

function isBookingExpiredDashboard(array $booking): bool
{
    if (($booking['payment_status'] ?? '') !== 'pending') {
        return false;
    }

    $expiresAt = parseDateJakarta($booking['expires_at'] ?? null);
    if (!$expiresAt) {
        return false;
    }

    return nowJakarta() >= $expiresAt;
}

function resolveDashboardStatus(array $booking): string
{
    if (($booking['payment_status'] ?? '') === 'paid') {
        return 'paid';
    }

    if (isBookingExpiredDashboard($booking)) {
        return 'expired';
    }

    return (string) ($booking['payment_status'] ?? 'pending');
}

function expireOldBookings(mysqli $conn): void
{
    $now = nowJakarta()->format('Y-m-d H:i:s');

    $stmt = $conn->prepare("\n        UPDATE bookings\n        SET payment_status = 'expired',\n            booking_status = 'cancelled',\n            cancelled_at = COALESCE(cancelled_at, ?)\n        WHERE payment_status = 'pending'\n          AND expires_at IS NOT NULL\n          AND expires_at <= ?\n    ");
    $stmt->bind_param("ss", $now, $now);
    $stmt->execute();
    $stmt->close();

    // Lepaskan kursi dari booking pending yang sudah expired/cancelled/failed,
    // sehingga kursi bisa dipilih ulang oleh user lain.
    $conn->query("\n        DELETE bs FROM booking_seats bs\n        JOIN bookings b ON b.id_booking = bs.id_booking\n        WHERE b.payment_status IN ('expired', 'cancelled', 'failed')\n          AND b.booking_status = 'cancelled'\n    ");
}

function statusBadgeClass(string $status): string
{
    return match ($status) {
        'paid' => 'profile-badge profile-badge--success',
        'pending' => 'profile-badge profile-badge--warning',
        'cancelled', 'expired' => 'profile-badge profile-badge--muted',
        default => 'profile-badge profile-badge--danger',
    };
}

function formatWhatsappLocal($number): string
{
    $number = preg_replace('/\D+/', '', (string) $number);

    if ($number === '') {
        return '-';
    }

    // Database CINEM4 menyimpan nomor dari form +62, biasanya dimulai dari 8.
    // Untuk tampilan lokal Indonesia, ubah menjadi 08xxxx.
    if (str_starts_with($number, '62')) {
        return '0' . substr($number, 2);
    }

    if (str_starts_with($number, '0')) {
        return $number;
    }

    if (str_starts_with($number, '8')) {
        return '0' . $number;
    }

    return $number;
}

function formatWhatsappLink($number): string
{
    $number = preg_replace('/\D+/', '', (string) $number);

    if ($number === '') {
        return '';
    }

    // Link wa.me wajib memakai kode negara tanpa tanda + dan tanpa angka 0 depan.
    if (str_starts_with($number, '0')) {
        return '62' . substr($number, 1);
    }

    if (str_starts_with($number, '62')) {
        return $number;
    }

    if (str_starts_with($number, '8')) {
        return '62' . $number;
    }

    return $number;
}

/* Ambil data user terbaru dari database */
$stmt = $conn->prepare("SELECT id_user, first_name, last_name, email, wa, is_verified, created_at FROM users WHERE id_user = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: join-us.php?mode=login");
    exit;
}

$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$initials = strtoupper(substr((string) ($user['first_name'] ?? 'U'), 0, 1) . substr((string) ($user['last_name'] ?? ''), 0, 1));
if (trim($initials) === '') $initials = 'U';

$_SESSION['name'] = $fullName;
$_SESSION['email'] = $user['email'];

$whatsappLocal = formatWhatsappLocal($user['wa'] ?? '');
$whatsappLink = formatWhatsappLink($user['wa'] ?? '');

expireOldBookings($conn);

/* Statistik booking */
$stats = [
    'total_booking' => 0,
    'paid_booking' => 0,
    'pending_booking' => 0,
    'total_seats' => 0,
    'total_spent' => 0,
];

$stmt = $conn->prepare("\n  SELECT\n    COUNT(*) AS total_booking,\n    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) AS paid_booking,\n    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) AS pending_booking,\n    COALESCE(SUM(total_seats), 0) AS total_seats,\n    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) AS total_spent\n  FROM bookings\n  WHERE id_user = ?\n");
$stmt->bind_param("i", $userId);
$stmt->execute();
$statsRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($statsRow) {
    $stats = array_merge($stats, $statsRow);
}


/* Booking pending aktif paling dekat expired */
$stmt = $conn->prepare("\n  SELECT\n    b.id_booking, b.booking_code, b.total_seats, b.total_amount, b.payment_status, b.booking_status, b.booked_at, b.expires_at,\n    s.show_date, s.show_time, s.studio_name,\n    m.title AS movie_title, m.poster, m.slug,\n    c.name AS cinema_name, c.city AS cinema_city,\n    GROUP_CONCAT(bs.seat_code ORDER BY bs.seat_code SEPARATOR ', ') AS seats\n  FROM bookings b\n  JOIN schedules s ON s.id_schedule = b.id_schedule\n  JOIN movies m ON m.id_movie = s.id_movie\n  JOIN cinemas c ON c.id_cinema = s.id_cinema\n  LEFT JOIN booking_seats bs ON bs.id_booking = b.id_booking\n  WHERE b.id_user = ?\n    AND b.payment_status = 'pending'\n    AND (b.expires_at IS NULL OR b.expires_at > NOW())\n  GROUP BY b.id_booking\n  ORDER BY b.expires_at ASC, b.id_booking DESC\n  LIMIT 1\n");
$stmt->bind_param("i", $userId);
$stmt->execute();
$latestPendingBooking = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* Riwayat booking user: ambil beberapa riwayat terbaru, filter dan tampilkan bertahap via JavaScript. */
$statusWhere = '';

$sqlHistory = "
  SELECT
    b.id_booking, b.booking_code, b.total_seats, b.total_amount, b.payment_status, b.booking_status, b.booked_at, b.expires_at,
    s.show_date, s.show_time, s.studio_name,
    m.title AS movie_title, m.poster, m.slug,
    c.name AS cinema_name, c.city AS cinema_city,
    GROUP_CONCAT(bs.seat_code ORDER BY bs.seat_code SEPARATOR ', ') AS seats
  FROM bookings b
  JOIN schedules s ON s.id_schedule = b.id_schedule
  JOIN movies m ON m.id_movie = s.id_movie
  JOIN cinemas c ON c.id_cinema = s.id_cinema
  LEFT JOIN booking_seats bs ON bs.id_booking = b.id_booking
  WHERE b.id_user = ?
  $statusWhere
  GROUP BY b.id_booking
  ORDER BY b.id_booking DESC
  LIMIT 50
";
$stmt = $conn->prepare($sqlHistory);
$stmt->bind_param("i", $userId);
$stmt->execute();
$recentBookings = $stmt->get_result();
$stmt->close();

$filterLabels = [
    'all' => 'Semua',
    'pending' => 'Pending',
    'paid' => 'Paid',
    'inactive' => 'Selesai/Batal',
];

include '../app/views/partials/public/head.php';
include '../app/views/partials/public/navbar.php';
?>

<main class="profile-page flex-grow-1">
    <section class="profile-hero">
        <div class="profile-hero__glow profile-hero__glow--one"></div>
        <div class="profile-hero__glow profile-hero__glow--two"></div>

        <div class="container position-relative">
            <div class="profile-hero-card">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-7">
                        <div class="d-flex align-items-center gap-3 gap-md-4">
                            <div class="profile-avatar">
                                <?= e($initials) ?>
                            </div>

                            <div class="min-width-0">
                                <div class="profile-eyebrow mb-2">
                                    <i class="bi bi-stars me-1"></i> Member Dashboard
                                </div>
                                <h1 class="profile-title mb-2">
                                    Halo, <?= e($user['first_name']) ?>
                                </h1>
                                <p class="profile-subtitle mb-0">
                                    Pantau pesanan aktif, lanjutkan pembayaran, dan buka e-ticket dari satu tempat.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="profile-member-card ms-lg-auto">
                            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                                <div>
                                    <div class="text-secondary small">Status Akun</div>
                                    <?php if ((int) $user['is_verified'] === 1): ?>
                                        <span class="profile-badge profile-badge--success mt-2">
                                            <i class="bi bi-patch-check-fill me-1"></i> Verified
                                        </span>
                                    <?php else: ?>
                                        <span class="profile-badge profile-badge--warning mt-2">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Pending
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="profile-ticket-icon">
                                    <i class="bi bi-ticket-perforated"></i>
                                </div>
                            </div>

                            <div class="profile-member-name"><?= e($fullName) ?></div>
                            <div class="profile-member-email text-truncate"><?= e($user['email']) ?></div>
                            <div class="profile-member-line"></div>
                            <div class="d-flex justify-content-between gap-3 small">
                                <span class="text-secondary">Member since</span>
                                <strong><?= e(formatDateId($user['created_at'] ?? null)) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="profile-content">
        <div class="container">
            <?php if ($latestPendingBooking): ?>
                <div class="profile-resume-card mb-4">
                    <div class="profile-resume-icon">
                        <i class="bi bi-hourglass-split"></i>
                    </div>

                    <div class="profile-resume-main">
                        <div class="profile-section-kicker mb-1">Pending Payment</div>
                        <h2 class="profile-resume-title mb-1">Lanjutkan pembayaran booking Anda</h2>
                        <p class="profile-resume-desc mb-0">
                            <?= e($latestPendingBooking['movie_title']) ?> •
                            <?= e($latestPendingBooking['cinema_name']) ?><?= !empty($latestPendingBooking['cinema_city']) ? ' - ' . e($latestPendingBooking['cinema_city']) : '' ?> •
                            Kursi <?= e($latestPendingBooking['seats'] ?: ($latestPendingBooking['total_seats'] . ' kursi')) ?>
                        </p>
                        <div class="profile-resume-meta mt-2">
                            <span><i class="bi bi-receipt me-1"></i>#<?= e($latestPendingBooking['booking_code']) ?></span>
                            <span><i class="bi bi-clock me-1"></i>Bayar sebelum <?= e(formatDateTimeId($latestPendingBooking['expires_at'] ?? null)) ?></span>
                            <span><i class="bi bi-cash-stack me-1"></i><?= e(rupiah($latestPendingBooking['total_amount'])) ?></span>
                        </div>
                    </div>

                    <div class="profile-resume-action">
                        <a href="payment.php?booking=<?= urlencode((string) $latestPendingBooking['booking_code']) ?>"
                            class="btn btn-primary rounded-pill px-4">
                            <i class="bi bi-credit-card me-1"></i> Lanjutkan Pembayaran
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row g-3 g-lg-4 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="profile-stat-card">
                        <div class="profile-stat-icon profile-stat-icon--blue">
                            <i class="bi bi-bag-check"></i>
                        </div>
                        <div class="profile-stat-value"><?= (int) $stats['total_booking'] ?></div>
                        <div class="profile-stat-label">Total Booking</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="profile-stat-card">
                        <div class="profile-stat-icon profile-stat-icon--green">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                        <div class="profile-stat-value"><?= (int) $stats['paid_booking'] ?></div>
                        <div class="profile-stat-label">Paid Booking</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="profile-stat-card">
                        <div class="profile-stat-icon profile-stat-icon--yellow">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div class="profile-stat-value"><?= (int) $stats['pending_booking'] ?></div>
                        <div class="profile-stat-label">Pending</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="profile-stat-card">
                        <div class="profile-stat-icon profile-stat-icon--purple">
                            <i class="bi bi-person-seat"></i>
                        </div>
                        <div class="profile-stat-value"><?= (int) $stats['total_seats'] ?></div>
                        <div class="profile-stat-label">Total Kursi</div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="profile-panel h-100">
                        <div class="profile-panel-header">
                            <div>
                                <div class="profile-section-kicker">Account</div>
                                <h2 class="profile-section-title">Detail Profil</h2>
                            </div>
                            <i class="bi bi-person-vcard profile-panel-header-icon"></i>
                        </div>

                        <div class="profile-info-list">
                            <div class="profile-info-item">
                                <div class="profile-info-icon"><i class="bi bi-person"></i></div>
                                <div>
                                    <div class="profile-info-label">Nama Lengkap</div>
                                    <div class="profile-info-value"><?= e($fullName) ?></div>
                                </div>
                            </div>

                            <div class="profile-info-item">
                                <div class="profile-info-icon"><i class="bi bi-envelope"></i></div>
                                <div class="min-width-0">
                                    <div class="profile-info-label">Email</div>
                                    <div class="profile-info-value text-truncate"><?= e($user['email']) ?></div>
                                </div>
                            </div>

                            <div class="profile-info-item">
                                <div class="profile-info-icon"><i class="bi bi-whatsapp"></i></div>
                                <div class="min-width-0">
                                    <div class="profile-info-label">WhatsApp</div>

                                    <?php if ($whatsappLink !== ''): ?>
                                        <a href="https://wa.me/<?= e($whatsappLink) ?>"
                                            target="_blank"
                                            rel="noopener"
                                            class="profile-info-value profile-info-link">
                                            <?= e($whatsappLocal) ?>
                                        </a>
                                    <?php else: ?>
                                        <div class="profile-info-value">-</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="profile-info-item">
                                <div class="profile-info-icon"><i class="bi bi-calendar2-check"></i></div>
                                <div>
                                    <div class="profile-info-label">Tanggal Bergabung</div>
                                    <div class="profile-info-value"><?= e(formatDateId($user['created_at'] ?? null)) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="profile-total-card mt-4">
                            <div class="text-secondary small mb-1">Total transaksi sukses</div>
                            <div class="profile-total-value"><?= e(rupiah($stats['total_spent'])) ?></div>
                            <div class="profile-total-caption">Dihitung dari booking berstatus paid.</div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <a href="edit_profile.php" class="btn btn-outline-light border-secondary rounded-pill py-2">
                                <i class="bi bi-pencil-square me-1"></i> Edit Profil
                            </a>
                            <a href="movies.php" class="btn btn-primary rounded-pill py-2">
                                <i class="bi bi-film me-1"></i> Booking Film Lagi
                            </a>
                            <a href="promotions.php" class="btn btn-outline-light border-secondary rounded-pill py-2">
                                <i class="bi bi-tags me-1"></i> Lihat Promo
                            </a>
                            <a href="logout.php" class="btn btn-dark border-secondary rounded-pill py-2">
                                <i class="bi bi-box-arrow-right me-1"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="profile-panel">
                        <div class="profile-panel-header align-items-start profile-history-header">
                            <div>
                                <div class="profile-section-kicker">My Tickets</div>
                                <h2 class="profile-section-title">Riwayat Booking</h2>
                                <p class="profile-section-desc mb-0">Lanjut bayar untuk pesanan pending atau buka e-ticket untuk booking yang sudah paid.</p>
                            </div>
                            <a href="movies.php" class="btn btn-sm btn-outline-light border-secondary rounded-pill px-3 d-none d-md-inline-flex">
                                Booking Film Lagi
                            </a>
                        </div>

                        <div class="profile-filter-tabs mb-3" role="tablist" aria-label="Filter riwayat booking">
                            <?php foreach ($filterLabels as $filterKey => $filterLabel): ?>
                                <button type="button"
                                    class="profile-filter-tab <?= $filterKey === 'all' ? 'active' : '' ?>"
                                    data-history-filter="<?= e($filterKey) ?>">
                                    <?= e($filterLabel) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($recentBookings && $recentBookings->num_rows > 0): ?>
                            <div class="profile-booking-list" id="bookingHistoryList" data-initial-visible="4" data-step="4">
                                <?php while ($booking = $recentBookings->fetch_assoc()): ?>
                                    <?php
                                    $bookingStatus = resolveDashboardStatus($booking);
                                    $filterGroup = $bookingStatus === 'paid' ? 'paid' : ($bookingStatus === 'pending' ? 'pending' : 'inactive');
                                    $resumeUrl = 'payment.php?booking=' . urlencode((string) $booking['booking_code']);
                                    $movieUrl = !empty($booking['slug']) ? 'movie-detail.php?slug=' . urlencode((string) $booking['slug']) : 'movies.php';
                                    ?>
                                    <article class="profile-booking-card" data-booking-card data-status="<?= e($filterGroup) ?>">
                                        <div class="profile-booking-poster">
                                            <?php if (!empty($booking['poster'])): ?>
                                                <img src="<?= e($booking['poster']) ?>" alt="<?= e($booking['movie_title']) ?>">
                                            <?php else: ?>
                                                <i class="bi bi-film"></i>
                                            <?php endif; ?>
                                        </div>

                                        <div class="profile-booking-main">
                                            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                                                <div class="min-width-0">
                                                    <div class="profile-booking-code">#<?= e($booking['booking_code']) ?></div>
                                                    <h3 class="profile-booking-title text-truncate mb-0"><?= e($booking['movie_title']) ?></h3>
                                                </div>
                                                <span class="<?= e(statusBadgeClass($bookingStatus)) ?>">
                                                    <?= e(ucfirst($bookingStatus)) ?>
                                                </span>
                                            </div>

                                            <div class="profile-booking-meta">
                                                <span><i class="bi bi-building me-1"></i><?= e($booking['cinema_name']) ?><?= !empty($booking['cinema_city']) ? ' - ' . e($booking['cinema_city']) : '' ?></span>
                                                <span><i class="bi bi-calendar-event me-1"></i><?= e(formatDateId($booking['show_date'])) ?></span>
                                                <span><i class="bi bi-clock me-1"></i><?= e(formatTimeId($booking['show_time'])) ?></span>
                                                <span><i class="bi bi-display me-1"></i><?= e($booking['studio_name']) ?></span>
                                                <?php if ($bookingStatus === 'pending'): ?>
                                                    <span><i class="bi bi-hourglass-bottom me-1"></i>Bayar sebelum <?= e(formatDateTimeId($booking['expires_at'] ?? null)) ?></span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="profile-booking-footer">
                                                <div>
                                                    <div class="profile-booking-small-label">Kursi</div>
                                                    <div class="profile-booking-seat"><?= e($booking['seats'] ?: ($booking['total_seats'] . ' kursi')) ?></div>
                                                </div>
                                                <div class="profile-booking-footer-right">
                                                    <div class="text-md-end">
                                                        <div class="profile-booking-small-label">Total</div>
                                                        <div class="profile-booking-price"><?= e(rupiah($booking['total_amount'])) ?></div>
                                                    </div>

                                                    <?php if ($bookingStatus === 'pending'): ?>
                                                        <a href="<?= e($resumeUrl) ?>" class="btn btn-sm btn-primary rounded-pill px-3">
                                                            <i class="bi bi-credit-card me-1"></i> Bayar
                                                        </a>
                                                    <?php elseif ($bookingStatus === 'paid'): ?>
                                                        <a href="e-ticket.php?booking=<?= urlencode((string) $booking['booking_code']) ?>" class="btn btn-sm btn-outline-light border-secondary rounded-pill px-3">
                                                            <i class="bi bi-ticket-perforated me-1"></i> E-Ticket
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="<?= e($movieUrl) ?>" class="btn btn-sm btn-outline-light border-secondary rounded-pill px-3">
                                                            Booking Ulang
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                <?php endwhile; ?>
                            </div>

                            <div id="bookingHistoryEmpty" class="profile-empty-state profile-empty-state--compact d-none">
                                <div class="profile-empty-icon">
                                    <i class="bi bi-ticket-perforated"></i>
                                </div>
                                <h3>Belum ada data pada filter ini</h3>
                                <p>Pilih filter lain atau mulai booking film baru.</p>
                            </div>

                            <div class="profile-history-more mt-3 text-center">
                                <button type="button" id="bookingHistoryMore" class="btn btn-outline-light border-secondary rounded-pill px-4">
                                    <i class="bi bi-chevron-down me-1"></i> Lihat lainnya
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="profile-empty-state">
                                <div class="profile-empty-icon">
                                    <i class="bi bi-ticket-perforated"></i>
                                </div>
                                <h3>Belum ada data pada filter ini</h3>
                                <p>Pilih filter lain atau mulai booking film baru untuk melihat riwayat tiket Anda di sini.</p>
                                <a href="movies.php" class="btn btn-primary rounded-pill px-4">
                                    Cari Film
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        const list = document.getElementById('bookingHistoryList');
        if (!list) return;

        const cards = Array.from(list.querySelectorAll('[data-booking-card]'));
        const filterButtons = Array.from(document.querySelectorAll('[data-history-filter]'));
        const moreButton = document.getElementById('bookingHistoryMore');
        const emptyState = document.getElementById('bookingHistoryEmpty');
        const initialVisible = Number.parseInt(list.dataset.initialVisible || '4', 10);
        const step = Number.parseInt(list.dataset.step || '4', 10);
        let activeFilter = 'all';
        let visibleLimit = initialVisible;

        function getFilteredCards() {
            return cards.filter(function(card) {
                return activeFilter === 'all' || card.dataset.status === activeFilter;
            });
        }

        function renderHistory() {
            const filteredCards = getFilteredCards();

            cards.forEach(function(card) {
                card.classList.add('d-none');
            });

            filteredCards.slice(0, visibleLimit).forEach(function(card) {
                card.classList.remove('d-none');
            });

            if (emptyState) {
                emptyState.classList.toggle('d-none', filteredCards.length > 0);
            }

            if (moreButton) {
                const shouldShowMore = filteredCards.length > visibleLimit;
                moreButton.classList.toggle('d-none', !shouldShowMore);
            }
        }

        filterButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                activeFilter = button.dataset.historyFilter || 'all';
                visibleLimit = initialVisible;

                filterButtons.forEach(function(btn) {
                    btn.classList.toggle('active', btn === button);
                });

                renderHistory();
            });
        });

        if (moreButton) {
            moreButton.addEventListener('click', function() {
                visibleLimit += step;
                renderHistory();
            });
        }

        renderHistory();
    });
</script>

<style>
    .profile-filter-tab {
        border: 0;
        background: transparent;
        cursor: pointer;
    }

    .profile-history-more .btn.d-none {
        display: none !important;
    }

    .profile-empty-state--compact {
        margin-top: 14px;
        padding: 28px 18px;
    }

    @media (max-width: 767.98px) {
        .profile-booking-list {
            display: grid;
            gap: 14px;
        }
    }

    .profile-resume-card {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: 18px;
        align-items: center;
        padding: 20px;
        border-radius: 24px;
        background:
            radial-gradient(420px 170px at 10% 0%, rgba(31, 111, 255, .32), transparent 72%),
            linear-gradient(135deg, rgba(31, 111, 255, .15), rgba(15, 23, 42, .72));
        border: 1px solid rgba(96, 165, 250, .28);
        box-shadow: 0 24px 70px rgba(0, 0, 0, .32);
    }

    .profile-resume-icon {
        width: 58px;
        height: 58px;
        border-radius: 20px;
        display: grid;
        place-items: center;
        color: #bfdbfe;
        background: rgba(31, 111, 255, .18);
        border: 1px solid rgba(96, 165, 250, .28);
        font-size: 26px;
    }

    .profile-resume-title {
        color: #fff;
        font-size: 22px;
        font-weight: 900;
        letter-spacing: -.03em;
    }

    .profile-resume-desc {
        color: rgba(226, 232, 240, .72);
        font-size: 14px;
    }

    .profile-resume-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 14px;
        color: rgba(226, 232, 240, .62);
        font-size: 12px;
    }

    .profile-resume-meta span {
        display: inline-flex;
        align-items: center;
    }

    .profile-booking-footer-right {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 12px;
    }

    .profile-history-header {
        margin-bottom: 16px;
    }

    .profile-filter-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 8px;
        border-radius: 999px;
        background: rgba(2, 6, 23, .28);
        border: 1px solid rgba(255, 255, 255, .08);
        width: fit-content;
        max-width: 100%;
    }

    .profile-filter-tab {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 36px;
        padding: 8px 14px;
        border-radius: 999px;
        color: rgba(226, 232, 240, .68);
        text-decoration: none;
        font-size: 13px;
        font-weight: 800;
        transition: .2s ease;
    }

    .profile-filter-tab:hover {
        color: #fff;
        background: rgba(255, 255, 255, .06);
    }

    .profile-filter-tab.active {
        color: #fff;
        background: linear-gradient(135deg, #1f6fff, #1554d1);
        box-shadow: 0 12px 28px rgba(31, 111, 255, .28);
    }

    .profile-booking-card {
        align-items: stretch;
    }

    .profile-booking-footer .btn {
        white-space: nowrap;
    }

    .profile-resume-action .btn,
    .profile-booking-footer-right .btn {
        box-shadow: 0 10px 26px rgba(31, 111, 255, .18);
    }

    @media (max-width: 767.98px) {
        .profile-filter-tabs {
            width: 100%;
            border-radius: 18px;
        }

        .profile-filter-tab {
            flex: 1 1 calc(50% - 8px);
        }
    }

    @media (max-width: 767.98px) {
        .profile-resume-card {
            grid-template-columns: 1fr;
            align-items: start;
        }

        .profile-resume-action .btn {
            width: 100%;
        }

        .profile-booking-footer-right {
            width: 100%;
            justify-content: space-between;
        }
    }
</style>

<?php include '../app/views/partials/public/footer.php'; ?>