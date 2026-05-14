<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
  header("Location: join-us.php?mode=login");
  exit;
}

require '../app/config/koneksi.php';

// Samakan timezone session MySQL dengan PHP supaya expires_at tidak langsung terbaca expired.
@$conn->query("SET time_zone = '+07:00'");

$title  = "CINEM4 - Payment";
$active = "movies";

$userId = (int) $_SESSION['user_id'];

function e($value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
  return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function redirectPayment(string $url): void
{
  header("Location: {$url}");
  exit;
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

function formatDateTimeId($datetime): string
{
  $date = parseDateJakarta($datetime);
  return $date ? $date->format('d M Y H:i') : '-';
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

function generateBookingCode(): string
{
  return 'C4-' . nowJakarta()->format('Ymd-His') . '-' . random_int(100, 999);
}

function normalizeSeats(string $raw): array
{
  $parts = explode(',', strtoupper($raw));
  $seats = [];

  foreach ($parts as $seat) {
    $seat = trim($seat);

    if ($seat === '') {
      continue;
    }

    if (!preg_match('/^[A-Z][0-9]{1,2}$/', $seat)) {
      continue;
    }

    $seats[] = $seat;
  }

  $seats = array_values(array_unique($seats));
  sort($seats, SORT_NATURAL);

  return $seats;
}

function generateSeatMap(int $capacity): array
{
  $cols = 8;
  $rowCount = (int) ceil($capacity / $cols);
  $rows = array_slice(range('A', 'Z'), 0, $rowCount);
  $valid = [];
  $count = 0;

  foreach ($rows as $row) {
    for ($col = 1; $col <= $cols; $col++) {
      $count++;

      if ($count > $capacity) {
        break 2;
      }

      $valid[] = $row . $col;
    }
  }

  return $valid;
}

function normalizePromoCode($code): string
{
  $code = strtoupper(trim((string) $code));
  $code = preg_replace('/\s+/', '', $code);
  $code = preg_replace('/[^A-Z0-9_-]/', '', $code);

  return substr($code, 0, 50);
}

function isBookingExpired(array $booking): bool
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

function resolvePaymentStatus(array $booking): string
{
  if (($booking['payment_status'] ?? '') === 'paid') {
    return 'paid';
  }

  if (isBookingExpired($booking)) {
    return 'expired';
  }

  return (string) ($booking['payment_status'] ?? 'pending');
}

function statusClass(string $status): string
{
  return match ($status) {
    'paid' => 'success',
    'pending' => 'warning',
    'expired', 'cancelled' => 'secondary',
    default => 'danger',
  };
}

function canChangePromo(array $booking): bool
{
  if (resolvePaymentStatus($booking) !== 'pending') {
    return false;
  }

  if (!empty($booking['snap_token']) || !empty($booking['gateway_order_id'])) {
    return false;
  }

  return true;
}

function logPaymentEvent(mysqli $conn, int $bookingId, string $eventType, array $payload = [], string $gateway = 'manual'): void
{
  $rawPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

  try {
    $stmt = $conn->prepare("
      INSERT INTO payment_logs (id_booking, gateway, event_type, raw_payload)
      VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isss", $bookingId, $gateway, $eventType, $rawPayload);
    $stmt->execute();
    $stmt->close();
  } catch (Throwable $e) {
    // Jangan gagalkan flow utama hanya karena logging gagal.
  }
}

function expireOldBookings(mysqli $conn): void
{
  $now = nowJakarta()->format('Y-m-d H:i:s');

  $stmt = $conn->prepare("
    UPDATE bookings
    SET payment_status = 'expired',
        booking_status = 'cancelled',
        cancelled_at = COALESCE(cancelled_at, ?)
    WHERE payment_status = 'pending'
      AND expires_at IS NOT NULL
      AND expires_at <= ?
  ");
  $stmt->bind_param("ss", $now, $now);
  $stmt->execute();
  $stmt->close();

  // Release kursi dari booking yang sudah tidak aktif supaya kursi bisa dipilih ulang.
  $conn->query("
    DELETE bs FROM booking_seats bs
    JOIN bookings b ON b.id_booking = bs.id_booking
    WHERE b.payment_status IN ('expired', 'cancelled', 'failed')
      AND b.booking_status = 'cancelled'
  ");
}

function getSchedule(mysqli $conn, int $scheduleId): ?array
{
  if ($scheduleId <= 0) {
    return null;
  }

  $stmt = $conn->prepare("
    SELECT s.*, m.title, m.slug, m.poster, m.rating_age, m.duration_minute,
           c.name AS cinema_name, c.city AS cinema_city
    FROM schedules s
    JOIN movies m ON m.id_movie = s.id_movie
    JOIN cinemas c ON c.id_cinema = s.id_cinema
    WHERE s.id_schedule = ?
      AND s.is_active = 1
      AND s.status = 'open'
    LIMIT 1
  ");
  $stmt->bind_param("i", $scheduleId);
  $stmt->execute();
  $schedule = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  return $schedule ?: null;
}

function getBooking(mysqli $conn, int $userId, string $bookingCode): ?array
{
  $stmt = $conn->prepare("
    SELECT
      b.*,
      s.show_date, s.show_time, s.studio_name,
      m.title AS movie_title, m.slug, m.poster,
      c.name AS cinema_name, c.city AS cinema_city,
      GROUP_CONCAT(bs.seat_code ORDER BY bs.seat_code SEPARATOR ', ') AS seats
    FROM bookings b
    JOIN schedules s ON s.id_schedule = b.id_schedule
    JOIN movies m ON m.id_movie = s.id_movie
    JOIN cinemas c ON c.id_cinema = s.id_cinema
    LEFT JOIN booking_seats bs ON bs.id_booking = b.id_booking
    WHERE b.booking_code = ?
      AND b.id_user = ?
    GROUP BY b.id_booking
    LIMIT 1
  ");
  $stmt->bind_param("si", $bookingCode, $userId);
  $stmt->execute();
  $booking = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  return $booking ?: null;
}

function activeReservedSeats(mysqli $conn, int $scheduleId): array
{
  $reserved = [];
  $now = nowJakarta()->format('Y-m-d H:i:s');

  $stmt = $conn->prepare("
    SELECT bs.seat_code
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
    $reserved[] = $row['seat_code'];
  }

  $stmt->close();

  return $reserved;
}

function getPromotionByCode(mysqli $conn, string $code): ?array
{
  if ($code === '') {
    return null;
  }

  $stmt = $conn->prepare("
    SELECT id_promotion, title, code, discount_type, discount_value, max_discount,
           min_purchase, quota, used_count, start_date, end_date, is_active
    FROM promotions
    WHERE code = ?
      AND is_active = 1
    LIMIT 1
  ");
  $stmt->bind_param("s", $code);
  $stmt->execute();
  $promo = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  return $promo ?: null;
}

function validateAndCalculatePromo(mysqli $conn, string $code, float $subtotal): array
{
  $code = normalizePromoCode($code);

  if ($code === '') {
    return [
      'valid' => false,
      'code' => '',
      'discount' => 0.00,
      'message' => 'Masukkan kode promo terlebih dahulu.',
      'promo' => null,
    ];
  }

  $promo = getPromotionByCode($conn, $code);

  if (!$promo) {
    return [
      'valid' => false,
      'code' => $code,
      'discount' => 0.00,
      'message' => 'Kode promo tidak ditemukan atau tidak aktif.',
      'promo' => null,
    ];
  }

  $today = nowJakarta()->format('Y-m-d');

  if (!empty($promo['start_date']) && $today < $promo['start_date']) {
    return [
      'valid' => false,
      'code' => $code,
      'discount' => 0.00,
      'message' => 'Kode promo belum berlaku.',
      'promo' => $promo,
    ];
  }

  if (!empty($promo['end_date']) && $today > $promo['end_date']) {
    return [
      'valid' => false,
      'code' => $code,
      'discount' => 0.00,
      'message' => 'Kode promo sudah berakhir.',
      'promo' => $promo,
    ];
  }

  $quota = $promo['quota'] === null ? null : (int) $promo['quota'];
  $usedCount = (int) ($promo['used_count'] ?? 0);

  if ($quota !== null && $quota > 0 && $usedCount >= $quota) {
    return [
      'valid' => false,
      'code' => $code,
      'discount' => 0.00,
      'message' => 'Kuota kode promo sudah habis.',
      'promo' => $promo,
    ];
  }

  $minPurchase = (float) ($promo['min_purchase'] ?? 0);

  if ($subtotal < $minPurchase) {
    return [
      'valid' => false,
      'code' => $code,
      'discount' => 0.00,
      'message' => 'Minimal transaksi untuk promo ini adalah ' . rupiah($minPurchase) . '.',
      'promo' => $promo,
    ];
  }

  $discountType = (string) ($promo['discount_type'] ?? '');
  $discountValue = (float) ($promo['discount_value'] ?? 0);
  $discount = 0.00;

  if ($discountType === 'percent') {
    $discount = $subtotal * ($discountValue / 100);
    if ($promo['max_discount'] !== null) {
      $discount = min($discount, (float) $promo['max_discount']);
    }
  } elseif ($discountType === 'fixed') {
    $discount = $discountValue;
  } else {
    return [
      'valid' => false,
      'code' => $code,
      'discount' => 0.00,
      'message' => 'Tipe promo tidak valid.',
      'promo' => $promo,
    ];
  }

  $discount = min($discount, $subtotal);
  $discount = max(0, round($discount, 0));

  return [
    'valid' => true,
    'code' => $code,
    'discount' => $discount,
    'message' => 'Kode promo berhasil digunakan.',
    'promo' => $promo,
  ];
}

function updateBookingPromo(mysqli $conn, array $booking, string $promoCode): array
{
  if (!canChangePromo($booking)) {
    return [
      'success' => false,
      'message' => 'Promo tidak bisa diubah karena booking sudah expired, dibayar, atau transaksi gateway sudah dibuat.',
    ];
  }

  $subtotal = (float) ($booking['subtotal_amount'] ?? $booking['total_amount']);
  $promoResult = validateAndCalculatePromo($conn, $promoCode, $subtotal);

  if (!$promoResult['valid']) {
    return [
      'success' => false,
      'message' => $promoResult['message'],
    ];
  }

  $discount = (float) $promoResult['discount'];
  $total = max(0, $subtotal - $discount);
  $code = $promoResult['code'];
  $bookingId = (int) $booking['id_booking'];

  $stmt = $conn->prepare("
    UPDATE bookings
    SET promo_code = ?,
        discount_amount = ?,
        total_amount = ?
    WHERE id_booking = ?
      AND payment_status = 'pending'
  ");
  $stmt->bind_param("sddi", $code, $discount, $total, $bookingId);
  $stmt->execute();
  $stmt->close();

  logPaymentEvent($conn, $bookingId, 'promo_applied', [
    'promo_code' => $code,
    'subtotal_amount' => $subtotal,
    'discount_amount' => $discount,
    'total_amount' => $total,
  ]);

  return [
    'success' => true,
    'message' => 'Promo berhasil diterapkan.',
  ];
}

function removeBookingPromo(mysqli $conn, array $booking): array
{
  if (!canChangePromo($booking)) {
    return [
      'success' => false,
      'message' => 'Promo tidak bisa dihapus karena booking sudah expired, dibayar, atau transaksi gateway sudah dibuat.',
    ];
  }

  $subtotal = (float) ($booking['subtotal_amount'] ?? $booking['total_amount']);
  $bookingId = (int) $booking['id_booking'];

  $stmt = $conn->prepare("
    UPDATE bookings
    SET promo_code = NULL,
        discount_amount = 0.00,
        total_amount = ?
    WHERE id_booking = ?
      AND payment_status = 'pending'
  ");
  $stmt->bind_param("di", $subtotal, $bookingId);
  $stmt->execute();
  $stmt->close();

  logPaymentEvent($conn, $bookingId, 'promo_removed', [
    'subtotal_amount' => $subtotal,
    'total_amount' => $subtotal,
  ]);

  return [
    'success' => true,
    'message' => 'Promo berhasil dihapus.',
  ];
}

function markBookingExpiredIfNeeded(mysqli $conn, int $userId, array $booking): array
{
  if (!isBookingExpired($booking)) {
    return $booking;
  }

  $now = nowJakarta()->format('Y-m-d H:i:s');
  $bookingId = (int) $booking['id_booking'];

  $stmt = $conn->prepare("
    UPDATE bookings
    SET payment_status = 'expired',
        booking_status = 'cancelled',
        cancelled_at = COALESCE(cancelled_at, ?)
    WHERE id_booking = ?
      AND payment_status = 'pending'
  ");
  $stmt->bind_param("si", $now, $bookingId);
  $stmt->execute();
  $stmt->close();

  $stmt = $conn->prepare("DELETE FROM booking_seats WHERE id_booking = ?");
  $stmt->bind_param("i", $bookingId);
  $stmt->execute();
  $stmt->close();

  logPaymentEvent($conn, $bookingId, 'booking_expired', [
    'booking_code' => $booking['booking_code'] ?? '',
    'expired_at' => $now,
  ]);

  $freshBooking = getBooking($conn, $userId, (string) $booking['booking_code']);

  return $freshBooking ?: $booking;
}

function validateScheduleAndSeats(?array $schedule, array $seats, array &$errors): void
{
  if (!$schedule) {
    $errors[] = 'Jadwal tidak ditemukan, sudah ditutup, atau tidak aktif.';
    return;
  }

  if (count($seats) === 0) {
    $errors[] = 'Pilih minimal satu kursi terlebih dahulu.';
    return;
  }

  $validSeats = generateSeatMap((int) $schedule['seat_capacity']);

  foreach ($seats as $seat) {
    if (!in_array($seat, $validSeats, true)) {
      $errors[] = "Kursi {$seat} tidak valid untuk studio ini.";
      break;
    }
  }

  $showDateTime = parseDateJakarta($schedule['show_date'] . ' ' . $schedule['show_time']);
  $closeMinutes = (int) ($schedule['booking_close_minutes'] ?? 30);

  if ($showDateTime && nowJakarta()->getTimestamp() >= ($showDateTime->getTimestamp() - ($closeMinutes * 60))) {
    $errors[] = 'Booking untuk jadwal ini sudah ditutup.';
  }
}

expireOldBookings($conn);

$errors = [];
$successMessage = $_SESSION['payment_success'] ?? '';
$errorMessage = $_SESSION['payment_error'] ?? '';
unset($_SESSION['payment_success'], $_SESSION['payment_error']);

$action = $_POST['action'] ?? '';
$bookingCodeParam = trim($_GET['booking'] ?? $_POST['booking_code'] ?? '');
$booking = null;

if ($bookingCodeParam !== '') {
  $booking = getBooking($conn, $userId, $bookingCodeParam);

  if (!$booking) {
    $errorMessage = 'Booking tidak ditemukan atau bukan milik akun Anda.';
  } else {
    $booking = markBookingExpiredIfNeeded($conn, $userId, $booking);
  }
}

/*
|--------------------------------------------------------------------------
| Apply / Remove promo untuk booking yang sudah dibuat
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $booking) {
  if ($action === 'apply_promo_booking') {
    $result = updateBookingPromo($conn, $booking, $_POST['promo_code'] ?? '');

    if ($result['success']) {
      $_SESSION['payment_success'] = $result['message'];
    } else {
      $_SESSION['payment_error'] = $result['message'];
    }

    redirectPayment('payment.php?booking=' . urlencode((string) $booking['booking_code']));
  }

  if ($action === 'remove_promo_booking') {
    $result = removeBookingPromo($conn, $booking);

    if ($result['success']) {
      $_SESSION['payment_success'] = $result['message'];
    } else {
      $_SESSION['payment_error'] = $result['message'];
    }

    redirectPayment('payment.php?booking=' . urlencode((string) $booking['booking_code']));
  }
}

/*
|--------------------------------------------------------------------------
| Flow booking baru dari halaman pilih kursi
|--------------------------------------------------------------------------
*/
$scheduleId = (int) ($_GET['schedule'] ?? $_POST['schedule'] ?? 0);
$slug       = trim($_GET['slug'] ?? $_POST['slug'] ?? '');
$seatsRaw   = (string) ($_GET['seats'] ?? $_POST['seats'] ?? '');
$seats      = normalizeSeats($seatsRaw);
$schedule   = $booking ? null : getSchedule($conn, $scheduleId);

$price = (float) ($schedule['price'] ?? 0);
$subtotal = $price * count($seats);
$previewPromoCode = '';
$previewDiscount = 0.00;
$promoMessage = '';
$promoMessageType = '';

if (!$booking) {
  validateScheduleAndSeats($schedule, $seats, $errors);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$booking && $schedule && count($errors) === 0) {
  if ($action === 'apply_promo') {
    $promoResult = validateAndCalculatePromo($conn, $_POST['promo_code'] ?? '', $subtotal);
    $promoMessage = $promoResult['message'];
    $promoMessageType = $promoResult['valid'] ? 'success' : 'danger';

    if ($promoResult['valid']) {
      $previewPromoCode = $promoResult['code'];
      $previewDiscount = (float) $promoResult['discount'];
    }
  }

  if ($action === 'remove_promo') {
    $previewPromoCode = '';
    $previewDiscount = 0.00;
    $promoMessage = 'Promo berhasil dihapus dari ringkasan pembayaran.';
    $promoMessageType = 'success';
  }

  if ($action === 'confirm_booking' || $action === '') {
    $promoCodeForDb = null;
    $discountAmount = 0.00;

    $postedPromoCode = normalizePromoCode($_POST['promo_code'] ?? '');

    if ($postedPromoCode !== '') {
      $promoResult = validateAndCalculatePromo($conn, $postedPromoCode, $subtotal);

      if (!$promoResult['valid']) {
        $errors[] = $promoResult['message'];
        $previewPromoCode = $postedPromoCode;
      } else {
        $promoCodeForDb = $promoResult['code'];
        $discountAmount = (float) $promoResult['discount'];
        $previewPromoCode = $promoCodeForDb;
        $previewDiscount = $discountAmount;
      }
    }

    if (count($errors) === 0) {
      $pricePerSeat = (float) $schedule['price'];
      $totalSeats = count($seats);
      $subtotalAmount = $pricePerSeat * $totalSeats;
      $totalAmount = max(0, $subtotalAmount - $discountAmount);
      $paymentStatus = 'pending';
      $bookingStatus = 'active';
      $bookingCode = generateBookingCode();
      $bookedAt = nowJakarta()->format('Y-m-d H:i:s');
      $expiresAt = nowJakarta()->modify('+15 minutes')->format('Y-m-d H:i:s');

      $conn->begin_transaction();

      try {
        expireOldBookings($conn);

        $reservedSeats = activeReservedSeats($conn, $scheduleId);

        foreach ($seats as $seat) {
          if (in_array($seat, $reservedSeats, true)) {
            throw new Exception("Kursi {$seat} sudah dipilih orang lain. Silakan pilih kursi lain.");
          }
        }

        $stmt = $conn->prepare("
          INSERT INTO bookings
            (id_user, id_schedule, booking_code, total_seats, price_per_seat, subtotal_amount,
             discount_amount, promo_code, total_amount, payment_status, booking_status, booked_at, expires_at)
          VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
          "iisidddsdssss",
          $userId,
          $scheduleId,
          $bookingCode,
          $totalSeats,
          $pricePerSeat,
          $subtotalAmount,
          $discountAmount,
          $promoCodeForDb,
          $totalAmount,
          $paymentStatus,
          $bookingStatus,
          $bookedAt,
          $expiresAt
        );
        $stmt->execute();
        $bookingId = $stmt->insert_id;
        $stmt->close();

        $seatStmt = $conn->prepare("
          INSERT INTO booking_seats (id_booking, id_schedule, seat_code, seat_price)
          VALUES (?, ?, ?, ?)
        ");

        foreach ($seats as $seat) {
          $seatStmt->bind_param("iisd", $bookingId, $scheduleId, $seat, $pricePerSeat);
          $seatStmt->execute();
        }

        $seatStmt->close();

        logPaymentEvent($conn, $bookingId, 'booking_created', [
          'source' => 'manual_booking_flow',
          'booking_code' => $bookingCode,
          'subtotal_amount' => $subtotalAmount,
          'discount_amount' => $discountAmount,
          'promo_code' => $promoCodeForDb,
          'total_amount' => $totalAmount,
          'seats' => $seats,
          'booked_at' => $bookedAt,
          'expires_at' => $expiresAt,
        ]);

        if ($promoCodeForDb !== null) {
          logPaymentEvent($conn, $bookingId, 'promo_applied_on_create', [
            'promo_code' => $promoCodeForDb,
            'discount_amount' => $discountAmount,
          ]);
        }

        $conn->commit();

        $_SESSION['payment_success'] = 'Booking berhasil dibuat. Kursi Anda ditahan sementara selama 15 menit.';
        redirectPayment('payment.php?booking=' . urlencode($bookingCode));
      } catch (Throwable $e) {
        $conn->rollback();
        $errors[] = $e->getMessage();
      }
    }
  }
}

$total = max(0, $subtotal - $previewDiscount);

include '../app/views/partials/public/head.php';
include '../app/views/partials/public/navbar.php';
?>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-9">
      <div class="card-glass p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
          <div>
            <div class="text-secondary small mb-1">Ringkasan Pembayaran</div>
            <h1 class="h3 fw-bold mb-0">
              <?= e($booking['movie_title'] ?? $schedule['title'] ?? 'Payment') ?>
            </h1>
          </div>

          <?php if ($booking): ?>
            <a href="dashboard.php" class="btn btn-outline-light border-secondary rounded-pill px-3">
              Dashboard
            </a>
          <?php else: ?>
            <a href="booking.php?slug=<?= urlencode($slug ?: ($schedule['slug'] ?? '')) ?>&schedule=<?= (int) $scheduleId ?>"
              class="btn btn-outline-light border-secondary rounded-pill px-3">
              Kembali
            </a>
          <?php endif; ?>
        </div>

        <?php foreach ($errors as $err): ?>
          <div class="alert alert-danger"><?= e($err) ?></div>
        <?php endforeach; ?>

        <?php if ($errorMessage): ?>
          <div class="alert alert-danger"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <?php if ($promoMessage): ?>
          <div class="alert alert-<?= e($promoMessageType) ?>"><?= e($promoMessage) ?></div>
        <?php endif; ?>

        <?php if ($booking): ?>
          <?php
            $status = resolvePaymentStatus($booking);
            $statusClass = statusClass($status);
          ?>

          <?php if ($successMessage && $status === 'pending'): ?>
            <div class="alert alert-success"><?= e($successMessage) ?></div>
          <?php elseif ($successMessage && $status !== 'expired'): ?>
            <div class="alert alert-success"><?= e($successMessage) ?></div>
          <?php endif; ?>

          <?php if ($status === 'expired'): ?>
            <div class="alert alert-warning">
              Booking sudah expired karena melewati batas pembayaran. Silakan pilih kursi dan booking ulang.
            </div>
          <?php elseif ($status === 'paid'): ?>
            <div class="alert alert-success">
              Pembayaran berhasil. Booking Anda sudah dikonfirmasi.
            </div>
          <?php endif; ?>

          <div class="alert alert-<?= e($statusClass) ?> d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
              Kode booking: <strong><?= e($booking['booking_code']) ?></strong>
            </div>
            <div>
              Status: <strong><?= e(ucfirst($status)) ?></strong>
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <div class="text-secondary small">Cinema</div>
              <div class="fw-semibold">
                <?= e($booking['cinema_name']) ?><?= !empty($booking['cinema_city']) ? ' (' . e($booking['cinema_city']) . ')' : '' ?>
              </div>
            </div>

            <div class="col-md-6">
              <div class="text-secondary small">Jadwal</div>
              <div class="fw-semibold">
                <?= e(formatDateId($booking['show_date'])) ?>
                • <?= e(formatTimeId($booking['show_time'])) ?>
                • <?= e($booking['studio_name']) ?>
              </div>
            </div>

            <div class="col-md-6">
              <div class="text-secondary small">Kursi</div>
              <div class="fw-semibold"><?= e($booking['seats'] ?: '-') ?></div>
            </div>

            <div class="col-md-6">
              <div class="text-secondary small">Batas Pembayaran</div>
              <div class="fw-semibold"><?= e(formatDateTimeId($booking['expires_at'] ?? null)) ?></div>
            </div>
          </div>

          <div class="border border-secondary rounded-4 p-3 p-md-4 mb-4">
            <?php if (canChangePromo($booking)): ?>
              <form method="post" action="payment.php" class="mb-4">
                <input type="hidden" name="action" value="apply_promo_booking">
                <input type="hidden" name="booking_code" value="<?= e($booking['booking_code']) ?>">

                <label class="form-label text-light">Kode Promo</label>
                <div class="input-group">
                  <input type="text"
                    name="promo_code"
                    value="<?= e($booking['promo_code'] ?? '') ?>"
                    class="form-control bg-transparent text-light border-secondary text-uppercase"
                    placeholder="Contoh: CASHBACK50"
                    maxlength="50">

                  <button type="submit" class="btn btn-primary">
                    Apply
                  </button>

                  <?php if (!empty($booking['promo_code'])): ?>
                    <button type="submit"
                      name="action"
                      value="remove_promo_booking"
                      class="btn btn-outline-light border-secondary">
                      Hapus
                    </button>
                  <?php endif; ?>
                </div>
              </form>
            <?php elseif (!empty($booking['promo_code'])): ?>
              <div class="alert alert-dark border-secondary mb-4">
                Promo sudah terkunci karena status booking tidak lagi bisa diubah.
              </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between mb-2">
              <span class="text-secondary">Subtotal</span>
              <strong><?= e(rupiah($booking['subtotal_amount'])) ?></strong>
            </div>

            <?php if (!empty($booking['promo_code'])): ?>
              <div class="d-flex justify-content-between mb-2">
                <span class="text-secondary">Kode Promo</span>
                <strong><?= e($booking['promo_code']) ?></strong>
              </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between mb-2">
              <span class="text-secondary">Diskon Promo</span>
              <strong>- <?= e(rupiah($booking['discount_amount'])) ?></strong>
            </div>

            <hr class="border-secondary opacity-25">

            <div class="d-flex justify-content-between align-items-center">
              <span class="fw-semibold">Total Bayar</span>
              <strong class="fs-4 text-primary"><?= e(rupiah($booking['total_amount'])) ?></strong>
            </div>
          </div>

          <?php if ($status === 'pending'): ?>
            <div class="alert alert-dark border-secondary">
              Struktur pembayaran dan promo sudah siap. Tahap berikutnya tombol ini akan dihubungkan ke Midtrans Sandbox Snap.
            </div>
            <button type="button" class="btn btn-primary rounded-pill px-4" disabled>
              Bayar dengan Midtrans - tahap berikutnya
            </button>
          <?php elseif ($status === 'paid'): ?>
            <a href="dashboard.php" class="btn btn-primary rounded-pill px-4">Lihat Tiket Saya</a>
          <?php else: ?>
            <a href="movies.php" class="btn btn-primary rounded-pill px-4">Booking Ulang</a>
          <?php endif; ?>

        <?php elseif ($schedule): ?>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <div class="text-secondary small">Cinema</div>
              <div class="fw-semibold">
                <?= e($schedule['cinema_name']) ?><?= !empty($schedule['cinema_city']) ? ' (' . e($schedule['cinema_city']) . ')' : '' ?>
              </div>
            </div>

            <div class="col-md-6">
              <div class="text-secondary small">Jadwal</div>
              <div class="fw-semibold">
                <?= e(formatDateId($schedule['show_date'])) ?>
                • <?= e(formatTimeId($schedule['show_time'])) ?>
                • <?= e($schedule['studio_name']) ?>
              </div>
            </div>

            <div class="col-md-6">
              <div class="text-secondary small">Kursi</div>
              <div class="fw-semibold"><?= e(implode(', ', $seats)) ?></div>
            </div>

            <div class="col-md-6">
              <div class="text-secondary small">Harga per Kursi</div>
              <div class="fw-semibold"><?= e(rupiah($price)) ?></div>
            </div>
          </div>

          <div class="border border-secondary rounded-4 p-3 p-md-4 mb-4">
            <form method="post" action="payment.php" class="mb-4">
              <input type="hidden" name="action" value="apply_promo">
              <input type="hidden" name="schedule" value="<?= (int) $scheduleId ?>">
              <input type="hidden" name="slug" value="<?= e($slug ?: ($schedule['slug'] ?? '')) ?>">
              <input type="hidden" name="seats" value="<?= e(implode(',', $seats)) ?>">

              <label class="form-label text-light">Kode Promo</label>
              <div class="input-group">
                <input type="text"
                  name="promo_code"
                  value="<?= e($previewPromoCode) ?>"
                  class="form-control bg-transparent text-light border-secondary text-uppercase"
                  placeholder="Contoh: CASHBACK50"
                  maxlength="50">

                <button type="submit" class="btn btn-primary">
                  Apply
                </button>

                <?php if ($previewPromoCode !== ''): ?>
                  <button type="submit"
                    name="action"
                    value="remove_promo"
                    class="btn btn-outline-light border-secondary">
                    Hapus
                  </button>
                <?php endif; ?>
              </div>
            </form>

            <div class="d-flex justify-content-between mb-2">
              <span class="text-secondary">Subtotal</span>
              <strong><?= e(rupiah($subtotal)) ?></strong>
            </div>

            <?php if ($previewPromoCode !== '' && $previewDiscount > 0): ?>
              <div class="d-flex justify-content-between mb-2">
                <span class="text-secondary">Kode Promo</span>
                <strong><?= e($previewPromoCode) ?></strong>
              </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between mb-2">
              <span class="text-secondary">Diskon Promo</span>
              <strong>- <?= e(rupiah($previewDiscount)) ?></strong>
            </div>

            <hr class="border-secondary opacity-25">

            <div class="d-flex justify-content-between align-items-center">
              <span class="fw-semibold">Total Bayar</span>
              <strong class="fs-4 text-primary"><?= e(rupiah($total)) ?></strong>
            </div>
          </div>

          <form method="post" action="payment.php">
            <input type="hidden" name="action" value="confirm_booking">
            <input type="hidden" name="schedule" value="<?= (int) $scheduleId ?>">
            <input type="hidden" name="slug" value="<?= e($slug ?: ($schedule['slug'] ?? '')) ?>">
            <input type="hidden" name="seats" value="<?= e(implode(',', $seats)) ?>">
            <input type="hidden" name="promo_code" value="<?= e($previewPromoCode) ?>">

            <button type="submit" class="btn btn-primary rounded-pill px-4" <?= count($errors) ? 'disabled' : '' ?>>
              Konfirmasi Booking
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include '../app/views/partials/public/footer.php'; ?>
