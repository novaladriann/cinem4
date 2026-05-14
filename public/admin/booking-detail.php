<?php
session_start();
require 'auth.php';
require '../../app/config/koneksi.php';

date_default_timezone_set('Asia/Jakarta');
@$conn->query("SET time_zone = '+07:00'");

if (function_exists('requirePermission')) {
    requirePermission('bookings.view', 'Anda tidak memiliki akses untuk melihat detail booking.');
}

$title     = "CINEM4 Admin — Detail Booking";
$pageTitle = "Detail Booking";

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

function fetchBooking(mysqli $conn, int $bookingId, string $bookingCode = ''): ?array
{
    if ($bookingId > 0) {
        $stmt = $conn->prepare("\n            SELECT\n                b.*,\n                u.first_name, u.last_name, u.email, u.wa, u.is_verified,\n                s.show_date, s.show_time, s.studio_name, s.price AS schedule_price, s.status AS schedule_status,\n                m.title AS movie_title, m.slug AS movie_slug, m.poster, m.genre, m.duration_minute, m.rating_age,\n                c.name AS cinema_name, c.city AS cinema_city, c.address AS cinema_address,\n                seat_data.seats, seat_data.seat_detail\n            FROM bookings b\n            JOIN users u ON u.id_user = b.id_user\n            JOIN schedules s ON s.id_schedule = b.id_schedule\n            JOIN movies m ON m.id_movie = s.id_movie\n            JOIN cinemas c ON c.id_cinema = s.id_cinema\n            LEFT JOIN (\n                SELECT\n                    id_booking,\n                    GROUP_CONCAT(seat_code ORDER BY seat_code SEPARATOR ', ') AS seats,\n                    GROUP_CONCAT(CONCAT(seat_code, ':', FORMAT(seat_price, 0)) ORDER BY seat_code SEPARATOR ' | ') AS seat_detail\n                FROM booking_seats\n                GROUP BY id_booking\n            ) seat_data ON seat_data.id_booking = b.id_booking\n            WHERE b.id_booking = ?\n            LIMIT 1\n        ");
        $stmt->bind_param('i', $bookingId);
    } else {
        $stmt = $conn->prepare("\n            SELECT\n                b.*,\n                u.first_name, u.last_name, u.email, u.wa, u.is_verified,\n                s.show_date, s.show_time, s.studio_name, s.price AS schedule_price, s.status AS schedule_status,\n                m.title AS movie_title, m.slug AS movie_slug, m.poster, m.genre, m.duration_minute, m.rating_age,\n                c.name AS cinema_name, c.city AS cinema_city, c.address AS cinema_address,\n                seat_data.seats, seat_data.seat_detail\n            FROM bookings b\n            JOIN users u ON u.id_user = b.id_user\n            JOIN schedules s ON s.id_schedule = b.id_schedule\n            JOIN movies m ON m.id_movie = s.id_movie\n            JOIN cinemas c ON c.id_cinema = s.id_cinema\n            LEFT JOIN (\n                SELECT\n                    id_booking,\n                    GROUP_CONCAT(seat_code ORDER BY seat_code SEPARATOR ', ') AS seats,\n                    GROUP_CONCAT(CONCAT(seat_code, ':', FORMAT(seat_price, 0)) ORDER BY seat_code SEPARATOR ' | ') AS seat_detail\n                FROM booking_seats\n                GROUP BY id_booking\n            ) seat_data ON seat_data.id_booking = b.id_booking\n            WHERE b.booking_code = ?\n            LIMIT 1\n        ");
        $stmt->bind_param('s', $bookingCode);
    }

    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $booking ?: null;
}

$bookingId = (int) ($_GET['id'] ?? 0);
$bookingCode = trim((string) ($_GET['booking'] ?? ''));

$flashSuccess = $_SESSION['booking_detail_success'] ?? '';
$flashError = $_SESSION['booking_detail_error'] ?? '';
unset($_SESSION['booking_detail_success'], $_SESSION['booking_detail_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $postBookingId = (int) ($_POST['id_booking'] ?? 0);

    if ($action === 'cancel_booking') {
        if (!canUpdateBooking()) {
            $_SESSION['booking_detail_error'] = 'Role Anda tidak memiliki izin untuk membatalkan booking.';
            header('Location: booking-detail.php?id=' . $postBookingId);
            exit;
        }

        $booking = fetchBooking($conn, $postBookingId);
        if (!$booking) {
            $_SESSION['booking_detail_error'] = 'Booking tidak ditemukan.';
        } elseif ($booking['payment_status'] !== 'pending') {
            $_SESSION['booking_detail_error'] = 'Hanya booking pending yang boleh dibatalkan.';
        } else {
            $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'cancelled', booking_status = 'cancelled', cancelled_at = NOW() WHERE id_booking = ? AND payment_status = 'pending'");
            $stmt->bind_param('i', $postBookingId);
            $ok = $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($ok && $affected > 0) {
                $stmt = $conn->prepare("DELETE FROM booking_seats WHERE id_booking = ?");
                $stmt->bind_param('i', $postBookingId);
                $stmt->execute();
                $stmt->close();

                $payload = json_encode([
                    'source' => 'admin_booking_detail',
                    'admin_id' => function_exists('currentAdminId') ? currentAdminId() : null,
                    'booking_code' => $booking['booking_code'],
                ], JSON_UNESCAPED_UNICODE);
                $gateway = 'manual';
                $eventType = 'admin_booking_cancelled';
                $stmt = $conn->prepare("INSERT INTO payment_logs (id_booking, gateway, event_type, raw_payload) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('isss', $postBookingId, $gateway, $eventType, $payload);
                    $stmt->execute();
                    $stmt->close();
                }

                $_SESSION['booking_detail_success'] = 'Booking pending berhasil dibatalkan dan kursi dilepas.';
            } else {
                $_SESSION['booking_detail_error'] = 'Booking gagal dibatalkan.';
            }
        }

        header('Location: booking-detail.php?id=' . $postBookingId);
        exit;
    }

    if ($action === 'sync_midtrans') {
        if (!canUpdateBooking()) {
            $_SESSION['booking_detail_error'] = 'Role Anda tidak memiliki izin untuk sinkronisasi status pembayaran.';
            header('Location: booking-detail.php?id=' . $postBookingId);
            exit;
        }

        $booking = fetchBooking($conn, $postBookingId);
        if (!$booking) {
            $_SESSION['booking_detail_error'] = 'Booking tidak ditemukan.';
            header('Location: booking-detail.php?id=' . $postBookingId);
            exit;
        }

        $orderId = trim((string) ($booking['gateway_order_id'] ?: $booking['booking_code']));
        if ($orderId === '') {
            $_SESSION['booking_detail_error'] = 'Order ID Midtrans belum tersedia.';
            header('Location: booking-detail.php?id=' . $postBookingId);
            exit;
        }

        require_once '../../app/config/midtrans.php';
        require_once '../../app/helpers/midtrans_status.php';

        if (!function_exists('midtransConfig') || !function_exists('cinem4FetchMidtransStatus')) {
            $_SESSION['booking_detail_error'] = 'Helper Midtrans belum tersedia.';
            header('Location: booking-detail.php?id=' . $postBookingId);
            exit;
        }

        $config = midtransConfig();
        $statusResult = cinem4FetchMidtransStatus($orderId, $config);
        if (empty($statusResult['success'])) {
            $_SESSION['booking_detail_error'] = (string) ($statusResult['message'] ?? 'Gagal mengambil status Midtrans.');
        } else {
            $applyResult = cinem4ApplyMidtransPayload($conn, $statusResult['payload'], $config, 'admin_sync');
            if (!empty($applyResult['success'])) {
                $_SESSION['booking_detail_success'] = 'Status Midtrans berhasil disinkronkan. Status sekarang: ' . statusLabel((string) ($applyResult['payment_status'] ?? '')) . '.';
            } else {
                $_SESSION['booking_detail_error'] = (string) ($applyResult['message'] ?? 'Status Midtrans gagal diterapkan.');
            }
        }

        header('Location: booking-detail.php?id=' . $postBookingId);
        exit;
    }
}

$booking = fetchBooking($conn, $bookingId, $bookingCode);
$logs = null;
if ($booking) {
    $stmt = $conn->prepare("SELECT * FROM payment_logs WHERE id_booking = ? ORDER BY created_at DESC, id_payment_log DESC LIMIT 12");
    $idBooking = (int) $booking['id_booking'];
    $stmt->bind_param('i', $idBooking);
    $stmt->execute();
    $logs = $stmt->get_result();
    $stmt->close();
}

include 'partials/head.php';
include 'partials/sidebar.php';
?>

<div class="adm-main">
  <?php include 'partials/topbar.php'; ?>
  <div class="adm-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
      <a href="bookings.php" class="adm-btn adm-btn-outline"><i class="bi bi-arrow-left"></i> Kembali ke Booking</a>
      <?php if ($booking): ?>
        <button type="button" class="adm-btn adm-btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Cetak Detail</button>
      <?php endif; ?>
    </div>

    <?php if ($flashSuccess): ?>
      <div class="adm-alert adm-alert-success"><i class="bi bi-check-circle"></i><?= e($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="adm-alert adm-alert-danger"><i class="bi bi-exclamation-triangle"></i><?= e($flashError) ?></div>
    <?php endif; ?>

    <?php if (!$booking): ?>
      <div class="adm-card">
        <div class="adm-card-body text-center" style="padding:42px;color:rgba(255,255,255,.55);">
          <i class="bi bi-ticket-perforated" style="font-size:2.4rem;color:rgba(255,255,255,.28);"></i>
          <h2 class="h5 fw-bold mt-3 text-white">Booking tidak ditemukan</h2>
          <p class="mb-0">Data booking tidak tersedia atau parameter URL tidak valid.</p>
        </div>
      </div>
    <?php else: ?>
      <?php
        $pStatus = (string) ($booking['payment_status'] ?? '');
        $bStatus = (string) ($booking['booking_status'] ?? '');
        $seats = trim((string) ($booking['seats'] ?? ''));
        $poster = trim((string) ($booking['poster'] ?? ''));
      ?>

      <section class="booking-detail-hero">
        <div class="booking-poster">
          <?php if ($poster !== ''): ?>
            <img src="../<?= e(ltrim($poster, './')) ?>" alt="<?= e($booking['movie_title']) ?>" onerror="this.style.display='none';this.parentElement.classList.add('empty');">
          <?php else: ?>
            <i class="bi bi-film"></i>
          <?php endif; ?>
        </div>
        <div class="booking-hero-copy">
          <div class="d-flex flex-wrap gap-2 mb-2">
            <span class="adm-badge <?= e(paymentBadgeClass($pStatus)) ?>"><?= e(statusLabel($pStatus)) ?></span>
            <span class="adm-badge <?= e(bookingBadgeClass($bStatus)) ?>"><?= e(statusLabel($bStatus)) ?></span>
          </div>
          <div class="booking-detail-code"><?= e($booking['booking_code']) ?></div>
          <h1><?= e($booking['movie_title']) ?></h1>
          <p><?= e($booking['cinema_name']) ?><?= !empty($booking['cinema_city']) ? ' • ' . e($booking['cinema_city']) : '' ?> • <?= e($booking['studio_name']) ?></p>
          <p><?= e(formatDateId($booking['show_date'])) ?> • <?= e(formatTimeId($booking['show_time'])) ?></p>
        </div>
        <div class="booking-hero-actions no-print">
          <?php if (canUpdateBooking() && $pStatus === 'pending'): ?>
            <form method="post" onsubmit="return confirm('Batalkan booking pending ini dan lepaskan kursinya?')">
              <input type="hidden" name="action" value="cancel_booking">
              <input type="hidden" name="id_booking" value="<?= (int) $booking['id_booking'] ?>">
              <button class="adm-btn adm-btn-danger" type="submit"><i class="bi bi-x-circle"></i> Batalkan Booking</button>
            </form>
          <?php endif; ?>
          <?php if (canUpdateBooking() && !empty($booking['gateway_order_id'])): ?>
            <form method="post">
              <input type="hidden" name="action" value="sync_midtrans">
              <input type="hidden" name="id_booking" value="<?= (int) $booking['id_booking'] ?>">
              <button class="adm-btn adm-btn-outline" type="submit"><i class="bi bi-arrow-repeat"></i> Cek Midtrans</button>
            </form>
          <?php endif; ?>
        </div>
      </section>

      <div class="booking-detail-grid">
        <div class="adm-card">
          <div class="adm-card-header"><div class="adm-card-title">Detail Tiket</div></div>
          <div class="adm-card-body booking-info-list">
            <div><span>User</span><strong><?= e(trim(($booking['first_name'] ?? '') . ' ' . ($booking['last_name'] ?? ''))) ?></strong></div>
            <div><span>Email</span><strong><?= e($booking['email']) ?></strong></div>
            <div><span>WhatsApp</span><strong><?= e($booking['wa'] ?: '-') ?></strong></div>
            <div><span>Verifikasi User</span><strong><?= ((int) ($booking['is_verified'] ?? 0) === 1) ? 'Terverifikasi' : 'Belum verifikasi' ?></strong></div>
            <div><span>Cinema</span><strong><?= e($booking['cinema_name']) ?><?= !empty($booking['cinema_city']) ? ' - ' . e($booking['cinema_city']) : '' ?></strong></div>
            <div><span>Alamat</span><strong><?= e($booking['cinema_address'] ?: '-') ?></strong></div>
            <div><span>Jadwal</span><strong><?= e(formatDateId($booking['show_date'])) ?> • <?= e(formatTimeId($booking['show_time'])) ?></strong></div>
            <div><span>Studio</span><strong><?= e($booking['studio_name']) ?></strong></div>
            <div class="wide"><span>Kursi</span><strong class="seat-text"><?= e($seats ?: '-') ?></strong></div>
          </div>
        </div>

        <div class="adm-card">
          <div class="adm-card-header"><div class="adm-card-title">Ringkasan Pembayaran</div></div>
          <div class="adm-card-body booking-payment-list">
            <div><span>Harga per Kursi</span><strong><?= e(rupiah($booking['price_per_seat'])) ?></strong></div>
            <div><span>Jumlah Kursi</span><strong><?= e((int) $booking['total_seats']) ?> kursi</strong></div>
            <div><span>Subtotal</span><strong><?= e(rupiah($booking['subtotal_amount'])) ?></strong></div>
            <div><span>Promo</span><strong><?= e($booking['promo_code'] ?: '-') ?></strong></div>
            <div><span>Diskon</span><strong>- <?= e(rupiah($booking['discount_amount'])) ?></strong></div>
            <div class="total"><span>Total Bayar</span><strong><?= e(rupiah($booking['total_amount'])) ?></strong></div>
          </div>
        </div>
      </div>

      <div class="booking-detail-grid mt-3">
        <div class="adm-card">
          <div class="adm-card-header"><div class="adm-card-title">Status & Gateway</div></div>
          <div class="adm-card-body booking-info-list">
            <div><span>Payment Status</span><strong><span class="adm-badge <?= e(paymentBadgeClass($pStatus)) ?>"><?= e(statusLabel($pStatus)) ?></span></strong></div>
            <div><span>Booking Status</span><strong><span class="adm-badge <?= e(bookingBadgeClass($bStatus)) ?>"><?= e(statusLabel($bStatus)) ?></span></strong></div>
            <div><span>Payment Method</span><strong><?= e($booking['payment_method'] ?: '-') ?></strong></div>
            <div><span>Gateway</span><strong><?= e($booking['payment_gateway'] ?: '-') ?></strong></div>
            <div><span>Gateway Order ID</span><strong class="mono-text"><?= e($booking['gateway_order_id'] ?: '-') ?></strong></div>
            <div><span>Gateway Transaction ID</span><strong class="mono-text"><?= e($booking['gateway_transaction_id'] ?: '-') ?></strong></div>
            <div><span>Snap Token</span><strong class="mono-text small-token"><?= e($booking['snap_token'] ?: '-') ?></strong></div>
          </div>
        </div>

        <div class="adm-card">
          <div class="adm-card-header"><div class="adm-card-title">Timeline Booking</div></div>
          <div class="adm-card-body booking-timeline">
            <div><span></span><p><strong>Booking dibuat</strong><br><?= e(formatDateTimeId($booking['booked_at'])) ?></p></div>
            <div><span></span><p><strong>Batas pembayaran</strong><br><?= e(formatDateTimeId($booking['expires_at'])) ?></p></div>
            <div><span></span><p><strong>Dibayar</strong><br><?= e(formatDateTimeId($booking['paid_at'])) ?></p></div>
            <div><span></span><p><strong>Gagal</strong><br><?= e(formatDateTimeId($booking['failed_at'])) ?></p></div>
            <div><span></span><p><strong>Dibatalkan/expired</strong><br><?= e(formatDateTimeId($booking['cancelled_at'])) ?></p></div>
          </div>
        </div>
      </div>

      <div class="adm-card mt-3">
        <div class="adm-card-header">
          <div>
            <div class="adm-card-title">Payment Logs</div>
            <div class="booking-muted small mt-1">Riwayat event pembayaran dan perubahan status booking.</div>
          </div>
        </div>
        <div class="booking-log-list">
          <?php if ($logs && $logs->num_rows > 0): ?>
            <?php while ($log = $logs->fetch_assoc()): ?>
              <div class="booking-log-item">
                <div>
                  <strong><?= e($log['event_type']) ?></strong>
                  <span><?= e($log['gateway']) ?> • <?= e(formatDateTimeId($log['created_at'])) ?></span>
                </div>
                <?php if (!empty($log['raw_payload'])): ?>
                  <details>
                    <summary>Payload</summary>
                    <pre><?= e($log['raw_payload']) ?></pre>
                  </details>
                <?php endif; ?>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="text-center" style="padding:28px;color:rgba(255,255,255,.42);">Belum ada payment log.</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<style>
.booking-detail-hero{display:grid;grid-template-columns:120px minmax(0,1fr) auto;gap:20px;align-items:center;padding:22px;border-radius:22px;border:1px solid rgba(255,255,255,.10);background:linear-gradient(135deg,rgba(31,111,255,.12),rgba(255,255,255,.045));margin-bottom:18px;}
.booking-poster{width:120px;height:160px;border-radius:18px;overflow:hidden;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);display:grid;place-items:center;color:rgba(255,255,255,.35);font-size:2rem;}
.booking-poster img{width:100%;height:100%;object-fit:cover;display:block;}
.booking-poster.empty img{display:none!important;}
.booking-hero-copy{min-width:0;}
.booking-detail-code{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;color:#93c5fd;font-weight:800;letter-spacing:.04em;margin-bottom:8px;word-break:break-word;}
.booking-hero-copy h1{margin:0 0 8px;font-size:clamp(1.7rem,4vw,2.8rem);font-weight:900;letter-spacing:-.04em;line-height:1.02;}
.booking-hero-copy p{margin:0;color:rgba(255,255,255,.62);line-height:1.6;}
.booking-hero-actions{display:flex;flex-direction:column;gap:8px;align-items:stretch;}
.booking-hero-actions form{margin:0;}
.booking-detail-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(320px,.75fr);gap:16px;}
.booking-info-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;}
.booking-info-list>div,.booking-payment-list>div{padding:14px;border-radius:14px;background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.08);}
.booking-info-list .wide{grid-column:span 2;}
.booking-info-list span,.booking-payment-list span{display:block;color:rgba(255,255,255,.45);font-size:.78rem;margin-bottom:6px;}
.booking-info-list strong,.booking-payment-list strong{display:block;color:#fff;font-size:.96rem;line-height:1.45;word-break:break-word;}
.seat-text{color:#93c5fd!important;font-size:1.15rem!important;letter-spacing:.04em;}
.mono-text{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:.84rem!important;}
.small-token{font-size:.72rem!important;}
.booking-payment-list{display:grid;gap:10px;}
.booking-payment-list>div{display:flex;justify-content:space-between;align-items:center;gap:16px;}
.booking-payment-list>div span,.booking-payment-list>div strong{margin:0;}
.booking-payment-list .total{background:rgba(31,111,255,.14);border-color:rgba(96,165,250,.25);}
.booking-payment-list .total strong{font-size:1.35rem;color:#93c5fd;}
.booking-timeline{position:relative;display:grid;gap:14px;}
.booking-timeline>div{display:grid;grid-template-columns:20px 1fr;gap:10px;align-items:flex-start;}
.booking-timeline span{width:12px;height:12px;border-radius:50%;background:#1f6fff;box-shadow:0 0 0 5px rgba(31,111,255,.14);margin-top:5px;}
.booking-timeline p{margin:0;color:rgba(255,255,255,.58);line-height:1.5;}
.booking-timeline strong{color:#fff;}
.booking-log-list{display:grid;gap:0;}
.booking-log-item{display:grid;grid-template-columns:minmax(0,1fr) minmax(260px,.8fr);gap:14px;padding:14px 20px;border-bottom:1px solid rgba(255,255,255,.07);}
.booking-log-item:last-child{border-bottom:0;}
.booking-log-item strong{display:block;color:#fff;}
.booking-log-item span{display:block;color:rgba(255,255,255,.45);font-size:.8rem;margin-top:3px;}
.booking-log-item details{color:rgba(255,255,255,.62);}
.booking-log-item summary{cursor:pointer;color:#93c5fd;font-size:.85rem;font-weight:700;}
.booking-log-item pre{margin:8px 0 0;max-height:180px;overflow:auto;padding:12px;border-radius:12px;background:rgba(0,0,0,.25);color:rgba(255,255,255,.72);font-size:.75rem;white-space:pre-wrap;word-break:break-word;}
.booking-muted{color:rgba(255,255,255,.44);font-size:.78rem;line-height:1.5;}
@media(max-width:991.98px){.booking-detail-hero{grid-template-columns:90px minmax(0,1fr);}.booking-poster{width:90px;height:124px;border-radius:14px;}.booking-hero-actions{grid-column:1/-1;flex-direction:row;flex-wrap:wrap}.booking-detail-grid{grid-template-columns:1fr;}.booking-log-item{grid-template-columns:1fr;}}
@media(max-width:575.98px){.booking-detail-hero{grid-template-columns:1fr;padding:18px;}.booking-poster{width:100%;max-width:150px;height:200px;}.booking-info-list{grid-template-columns:1fr;}.booking-info-list .wide{grid-column:span 1;}.booking-payment-list>div{align-items:flex-start;flex-direction:column;gap:4px;}.booking-hero-actions{flex-direction:column;}.booking-hero-actions .adm-btn{justify-content:center;width:100%;}}
@media print{.no-print,.adm-sidebar,.adm-topbar,footer{display:none!important}.adm-main{margin-left:0!important}.adm-content{padding:0!important}.booking-detail-hero,.adm-card{box-shadow:none!important;background:#fff!important;color:#111!important;border:1px solid #ddd!important}.booking-hero-copy h1,.booking-info-list strong,.booking-payment-list strong,.booking-timeline strong,.booking-log-item strong{color:#111!important}.booking-hero-copy p,.booking-info-list span,.booking-payment-list span,.booking-timeline p,.booking-log-item span{color:#555!important}.booking-detail-grid{grid-template-columns:1fr 1fr}.booking-log-item details{display:none}}
</style>

<?php include 'partials/footer.php'; ?>
