<?php
session_start();
require 'auth.php';
require '../../app/config/koneksi.php';

date_default_timezone_set('Asia/Jakarta');
@$conn->query("SET time_zone = '+07:00'");

if (function_exists('requirePermission')) {
    requirePermission('bookings.view', 'Anda tidak memiliki akses untuk melihat data booking.');
}

$title     = "CINEM4 Admin — Booking";
$pageTitle = "Manajemen Booking";

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
    $ts = strtotime((string) $date);
    return $ts ? date('d M Y', $ts) : '-';
}

function formatDateTimeId($date): string
{
    if (empty($date)) return '-';
    $ts = strtotime((string) $date);
    return $ts ? date('d M Y H:i', $ts) : '-';
}

function formatTimeId($time): string
{
    if (empty($time)) return '-';
    $ts = strtotime((string) $time);
    return $ts ? date('H:i', $ts) : '-';
}

function paymentBadgeClass(string $status): string
{
    return match ($status) {
        'paid' => 'adm-badge-green',
        'pending' => 'adm-badge-yellow',
        'expired', 'cancelled' => 'adm-badge-gray',
        'failed' => 'adm-badge-red',
        default => 'adm-badge-gray',
    };
}

function bookingBadgeClass(string $status): string
{
    return match ($status) {
        'completed' => 'adm-badge-green',
        'active' => 'adm-badge-blue',
        'cancelled' => 'adm-badge-gray',
        default => 'adm-badge-gray',
    };
}

function statusLabel(string $status): string
{
    return match ($status) {
        'paid' => 'Paid',
        'pending' => 'Pending',
        'failed' => 'Failed',
        'expired' => 'Expired',
        'cancelled' => 'Cancelled',
        'active' => 'Active',
        'completed' => 'Completed',
        default => ucfirst($status ?: '-'),
    };
}

function canUpdateBooking(): bool
{
    return function_exists('adminCan') ? adminCan('bookings.update_status') : true;
}

$flashSuccess = $_SESSION['booking_flash_success'] ?? '';
$flashError = $_SESSION['booking_flash_error'] ?? '';
unset($_SESSION['booking_flash_success'], $_SESSION['booking_flash_error']);

// Lepaskan kursi dari booking terminal agar kursi tidak terus terkunci.
@$conn->query("DELETE bs FROM booking_seats bs JOIN bookings b ON b.id_booking = bs.id_booking WHERE b.payment_status IN ('expired','cancelled','failed') AND b.booking_status = 'cancelled'");
@$conn->query("UPDATE bookings SET payment_status = 'expired', booking_status = 'cancelled', cancelled_at = COALESCE(cancelled_at, NOW()) WHERE payment_status = 'pending' AND expires_at IS NOT NULL AND expires_at < NOW()");
@$conn->query("DELETE bs FROM booking_seats bs JOIN bookings b ON b.id_booking = bs.id_booking WHERE b.payment_status IN ('expired','cancelled','failed') AND b.booking_status = 'cancelled'");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_booking') {
    if (!canUpdateBooking()) {
        $_SESSION['booking_flash_error'] = 'Role Anda tidak memiliki izin untuk membatalkan booking.';
        header('Location: bookings.php');
        exit;
    }

    $bookingId = (int) ($_POST['id_booking'] ?? 0);
    $redirect = trim((string) ($_POST['redirect'] ?? 'bookings.php'));

    if ($bookingId <= 0) {
        $_SESSION['booking_flash_error'] = 'Booking tidak valid.';
        header('Location: bookings.php');
        exit;
    }

    $stmt = $conn->prepare("SELECT id_booking, booking_code, payment_status FROM bookings WHERE id_booking = ? LIMIT 1");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        $_SESSION['booking_flash_error'] = 'Booking tidak ditemukan.';
    } elseif ($booking['payment_status'] !== 'pending') {
        $_SESSION['booking_flash_error'] = 'Hanya booking pending yang boleh dibatalkan dari admin.';
    } else {
        $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'cancelled', booking_status = 'cancelled', cancelled_at = NOW() WHERE id_booking = ? AND payment_status = 'pending'");
        $stmt->bind_param('i', $bookingId);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($ok && $affected > 0) {
            $stmt = $conn->prepare("DELETE FROM booking_seats WHERE id_booking = ?");
            $stmt->bind_param('i', $bookingId);
            $stmt->execute();
            $stmt->close();

            $payload = json_encode([
                'source' => 'admin_panel',
                'admin_id' => function_exists('currentAdminId') ? currentAdminId() : null,
                'booking_code' => $booking['booking_code'],
            ], JSON_UNESCAPED_UNICODE);
            $gateway = 'manual';
            $eventType = 'admin_booking_cancelled';
            $stmt = $conn->prepare("INSERT INTO payment_logs (id_booking, gateway, event_type, raw_payload) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('isss', $bookingId, $gateway, $eventType, $payload);
                $stmt->execute();
                $stmt->close();
            }

            $_SESSION['booking_flash_success'] = 'Booking pending berhasil dibatalkan dan kursi dilepas kembali.';
        } else {
            $_SESSION['booking_flash_error'] = 'Booking gagal dibatalkan.';
        }
    }

    header('Location: ' . ($redirect ?: 'bookings.php'));
    exit;
}

$search = trim((string) ($_GET['q'] ?? ''));
$paymentStatus = trim((string) ($_GET['payment_status'] ?? ''));
$bookingStatus = trim((string) ($_GET['booking_status'] ?? ''));
$movieId = (int) ($_GET['movie_id'] ?? 0);
$cinemaId = (int) ($_GET['cinema_id'] ?? 0);
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = "WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $where .= " AND (b.booking_code LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR m.title LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
    $types .= 'sssss';
}

if (in_array($paymentStatus, ['pending', 'paid', 'failed', 'expired', 'cancelled'], true)) {
    $where .= " AND b.payment_status = ?";
    $params[] = $paymentStatus;
    $types .= 's';
}

if (in_array($bookingStatus, ['active', 'completed', 'cancelled'], true)) {
    $where .= " AND b.booking_status = ?";
    $params[] = $bookingStatus;
    $types .= 's';
}

if ($movieId > 0) {
    $where .= " AND m.id_movie = ?";
    $params[] = $movieId;
    $types .= 'i';
}

if ($cinemaId > 0) {
    $where .= " AND c.id_cinema = ?";
    $params[] = $cinemaId;
    $types .= 'i';
}

if ($dateFrom !== '') {
    $where .= " AND DATE(b.booked_at) >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}

if ($dateTo !== '') {
    $where .= " AND DATE(b.booked_at) <= ?";
    $params[] = $dateTo;
    $types .= 's';
}

$baseFrom = "
  FROM bookings b
  JOIN users u ON u.id_user = b.id_user
  JOIN schedules s ON s.id_schedule = b.id_schedule
  JOIN movies m ON m.id_movie = s.id_movie
  JOIN cinemas c ON c.id_cinema = s.id_cinema
  LEFT JOIN (
    SELECT id_booking, GROUP_CONCAT(seat_code ORDER BY seat_code SEPARATOR ', ') AS seats
    FROM booking_seats
    GROUP BY id_booking
  ) seat_data ON seat_data.id_booking = b.id_booking
  $where
";

$countSql = "SELECT COUNT(*) AS total $baseFrom";
$stmt = $conn->prepare($countSql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalRows = (int) (($stmt->get_result()->fetch_assoc()['total'] ?? 0));
$stmt->close();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$listSql = "
  SELECT
    b.*,
    u.first_name, u.last_name, u.email, u.wa,
    m.title AS movie_title,
    s.show_date, s.show_time, s.studio_name,
    c.name AS cinema_name, c.city AS cinema_city,
    seat_data.seats
  $baseFrom
  ORDER BY b.booked_at DESC, b.id_booking DESC
  LIMIT ? OFFSET ?
";
$listParams = $params;
$listTypes = $types . 'ii';
$listParams[] = $perPage;
$listParams[] = $offset;
$stmt = $conn->prepare($listSql);
$stmt->bind_param($listTypes, ...$listParams);
$stmt->execute();
$bookings = $stmt->get_result();
$stmt->close();

$movies = $conn->query("SELECT id_movie, title FROM movies ORDER BY title ASC");
$cinemas = $conn->query("SELECT id_cinema, name, city FROM cinemas ORDER BY name ASC");

$queryBase = $_GET;
unset($queryBase['page']);
$queryString = http_build_query($queryBase);
$currentUrl = 'bookings.php' . ($queryString ? '?' . $queryString . '&page=' . $page : '?page=' . $page);

include 'partials/head.php';
include 'partials/sidebar.php';
?>

<div class="adm-main">
  <?php include 'partials/topbar.php'; ?>
  <div class="adm-content">

    <?php if ($flashSuccess): ?>
      <div class="adm-alert adm-alert-success"><i class="bi bi-check-circle"></i><?= e($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="adm-alert adm-alert-danger"><i class="bi bi-exclamation-triangle"></i><?= e($flashError) ?></div>
    <?php endif; ?>

    <div class="adm-card mb-3">
      <div class="adm-card-header">
        <div>
          <div class="adm-card-title">Filter Booking</div>
          <div class="booking-muted small mt-1">Cari berdasarkan kode booking, user, email, atau judul film.</div>
        </div>
        <?php if ($search || $paymentStatus || $bookingStatus || $movieId || $cinemaId || $dateFrom || $dateTo): ?>
          <a href="bookings.php" class="adm-btn adm-btn-outline adm-btn-sm"><i class="bi bi-x-circle"></i> Reset</a>
        <?php endif; ?>
      </div>
      <div class="adm-card-body">
        <form method="get" class="booking-filter-grid">
          <div class="filter-wide">
            <label class="adm-form-label">Keyword</label>
            <input type="text" name="q" class="adm-form-control" placeholder="Kode / user / email / film" value="<?= e($search) ?>">
          </div>
          <div>
            <label class="adm-form-label">Payment</label>
            <select name="payment_status" class="adm-form-control">
              <option value="">Semua</option>
              <?php foreach (['pending','paid','failed','expired','cancelled'] as $st): ?>
                <option value="<?= e($st) ?>" <?= $paymentStatus === $st ? 'selected' : '' ?>><?= e(statusLabel($st)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="adm-form-label">Booking</label>
            <select name="booking_status" class="adm-form-control">
              <option value="">Semua</option>
              <?php foreach (['active','completed','cancelled'] as $st): ?>
                <option value="<?= e($st) ?>" <?= $bookingStatus === $st ? 'selected' : '' ?>><?= e(statusLabel($st)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="adm-form-label">Film</label>
            <select name="movie_id" class="adm-form-control">
              <option value="0">Semua Film</option>
              <?php if ($movies): while ($m = $movies->fetch_assoc()): ?>
                <option value="<?= (int) $m['id_movie'] ?>" <?= $movieId === (int) $m['id_movie'] ? 'selected' : '' ?>><?= e($m['title']) ?></option>
              <?php endwhile; endif; ?>
            </select>
          </div>
          <div>
            <label class="adm-form-label">Cinema</label>
            <select name="cinema_id" class="adm-form-control">
              <option value="0">Semua Cinema</option>
              <?php if ($cinemas): while ($c = $cinemas->fetch_assoc()): ?>
                <option value="<?= (int) $c['id_cinema'] ?>" <?= $cinemaId === (int) $c['id_cinema'] ? 'selected' : '' ?>>
                  <?= e($c['name'] . (!empty($c['city']) ? ' - ' . $c['city'] : '')) ?>
                </option>
              <?php endwhile; endif; ?>
            </select>
          </div>
          <div>
            <label class="adm-form-label">Dari</label>
            <input type="date" name="date_from" class="adm-form-control" value="<?= e($dateFrom) ?>">
          </div>
          <div>
            <label class="adm-form-label">Sampai</label>
            <input type="date" name="date_to" class="adm-form-control" value="<?= e($dateTo) ?>">
          </div>
          <div class="filter-action">
            <button type="submit" class="adm-btn adm-btn-primary w-100 justify-content-center"><i class="bi bi-search"></i> Terapkan</button>
          </div>
        </form>
      </div>
    </div>

    <div class="adm-card">
      <div class="adm-card-header">
        <div>
          <div class="adm-card-title">Daftar Booking</div>
          <div class="booking-muted small mt-1">Menampilkan <?= e($totalRows) ?> booking sesuai filter.</div>
        </div>
      </div>

      <div class="booking-table-wrap">
        <table class="adm-table booking-admin-table" data-dt='{"searching":false,"paging":false,"info":false,"ordering":false}'>
          <thead>
            <tr>
              <th>Booking</th>
              <th>User</th>
              <th>Film & Jadwal</th>
              <th>Kursi</th>
              <th>Total</th>
              <th>Status</th>
              <th style="width:170px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($bookings && $bookings->num_rows > 0): ?>
            <?php while ($b = $bookings->fetch_assoc()): ?>
              <?php
                $pStatus = (string) ($b['payment_status'] ?? '');
                $bStatus = (string) ($b['booking_status'] ?? '');
                $seats = trim((string) ($b['seats'] ?? ''));
              ?>
              <tr>
                <td>
                  <div class="booking-code-text"><?= e($b['booking_code']) ?></div>
                  <div class="booking-muted">Dibuat <?= e(formatDateTimeId($b['booked_at'])) ?></div>
                </td>
                <td>
                  <div class="booking-main-text"><?= e(trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? ''))) ?></div>
                  <div class="booking-muted"><?= e($b['email']) ?></div>
                </td>
                <td>
                  <div class="booking-main-text"><?= e($b['movie_title']) ?></div>
                  <div class="booking-muted"><?= e($b['cinema_name']) ?><?= !empty($b['cinema_city']) ? ' • ' . e($b['cinema_city']) : '' ?></div>
                  <div class="booking-muted"><?= e(formatDateId($b['show_date'])) ?> • <?= e(formatTimeId($b['show_time'])) ?> • <?= e($b['studio_name']) ?></div>
                </td>
                <td>
                  <div class="booking-main-text"><?= e((int) $b['total_seats']) ?> kursi</div>
                  <div class="booking-muted"><?= e($seats ?: '-') ?></div>
                </td>
                <td>
                  <div class="booking-main-text"><?= e(rupiah($b['total_amount'])) ?></div>
                  <?php if ((float) $b['discount_amount'] > 0): ?>
                    <div class="booking-muted">Diskon <?= e(rupiah($b['discount_amount'])) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="d-flex flex-column gap-1 align-items-start">
                    <span class="adm-badge <?= e(paymentBadgeClass($pStatus)) ?>"><?= e(statusLabel($pStatus)) ?></span>
                    <span class="adm-badge <?= e(bookingBadgeClass($bStatus)) ?>"><?= e(statusLabel($bStatus)) ?></span>
                  </div>
                </td>
                <td>
                  <div class="booking-action-stack">
                    <a class="adm-btn adm-btn-primary adm-btn-sm" href="booking-detail.php?id=<?= (int) $b['id_booking'] ?>">
                      <i class="bi bi-eye"></i> Detail
                    </a>
                    <?php if (canUpdateBooking() && $pStatus === 'pending'): ?>
                      <form method="post" onsubmit="return confirm('Batalkan booking pending ini dan lepaskan kursinya?')">
                        <input type="hidden" name="action" value="cancel_booking">
                        <input type="hidden" name="id_booking" value="<?= (int) $b['id_booking'] ?>">
                        <input type="hidden" name="redirect" value="<?= e($currentUrl) ?>">
                        <button class="adm-btn adm-btn-outline adm-btn-sm" type="submit"><i class="bi bi-x-circle"></i> Batalkan</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" class="text-center" style="padding:34px;color:rgba(255,255,255,.42);">Tidak ada booking ditemukan.</td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
        <div class="booking-pagination">
          <?php
            $prevQuery = http_build_query(array_merge($queryBase, ['page' => max(1, $page - 1)]));
            $nextQuery = http_build_query(array_merge($queryBase, ['page' => min($totalPages, $page + 1)]));
          ?>
          <a class="adm-btn adm-btn-outline adm-btn-sm <?= $page <= 1 ? 'disabled' : '' ?>" href="bookings.php?<?= e($prevQuery) ?>">Sebelumnya</a>
          <span>Halaman <?= e($page) ?> dari <?= e($totalPages) ?></span>
          <a class="adm-btn adm-btn-outline adm-btn-sm <?= $page >= $totalPages ? 'disabled' : '' ?>" href="bookings.php?<?= e($nextQuery) ?>">Berikutnya</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<style>
.booking-filter-grid{display:grid;grid-template-columns:1.5fr repeat(3,minmax(140px,1fr));gap:14px;align-items:end;}
.filter-wide{grid-column:span 2;}
.filter-action{align-self:end;}
.booking-table-wrap{overflow-x:auto;}
.booking-admin-table{min-width:1040px;}
.booking-code-text{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;color:#fff;font-weight:800;font-size:.82rem;}
.booking-main-text{color:#fff;font-weight:700;}
.booking-muted{color:rgba(255,255,255,.44);font-size:.78rem;line-height:1.5;}
.booking-action-stack{display:flex;flex-wrap:wrap;gap:8px;align-items:center;}
.booking-action-stack form{margin:0;}
.booking-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:16px 20px;border-top:1px solid rgba(255,255,255,.08);color:rgba(255,255,255,.56);font-size:.86rem;}
.booking-pagination .disabled{pointer-events:none;opacity:.45;}
@media(max-width:1199.98px){.booking-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr));}.filter-wide{grid-column:span 2;}}
@media(max-width:575.98px){.booking-filter-grid{grid-template-columns:1fr;}.filter-wide{grid-column:span 1;}.booking-pagination{flex-direction:column;align-items:stretch;text-align:center;}}
</style>

<?php include 'partials/footer.php'; ?>
