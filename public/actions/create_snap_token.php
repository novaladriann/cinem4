<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Sesi login sudah berakhir. Silakan login kembali.',
    ]);
    exit;
}

require '../../app/config/koneksi.php';
require_once '../../app/config/midtrans.php';

@$conn->query("SET time_zone = '+07:00'");

$userId = (int) $_SESSION['user_id'];

function jsonResponse(bool $success, string $message, array $extra = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
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

function isBookingExpiredForSnap(array $booking): bool
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

function logPaymentEvent(mysqli $conn, int $bookingId, string $eventType, array $payload = [], string $gateway = 'midtrans'): void
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
        // Logging tidak boleh menggagalkan proses utama.
    }
}

function formatPhoneForMidtrans($phone): string
{
    $phone = preg_replace('/\D+/', '', (string) $phone);

    if ($phone === '') {
        return '';
    }

    if (str_starts_with($phone, '0')) {
        return '62' . substr($phone, 1);
    }

    if (str_starts_with($phone, '8')) {
        return '62' . $phone;
    }

    return $phone;
}

function publicUrl(string $path): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Dari /public/actions/create_snap_token.php ke /public.
    $publicBase = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');

    return $scheme . '://' . $host . $publicBase . '/' . ltrim($path, '/');
}

function fetchBookingForSnap(mysqli $conn, int $userId, string $bookingCode): ?array
{
    $stmt = $conn->prepare("
        SELECT
            b.*,
            u.first_name,
            u.last_name,
            u.email,
            u.wa,
            s.show_date,
            s.show_time,
            s.studio_name,
            m.title AS movie_title,
            c.name AS cinema_name,
            c.city AS cinema_city,
            GROUP_CONCAT(bs.seat_code ORDER BY bs.seat_code SEPARATOR ', ') AS seats
        FROM bookings b
        JOIN users u ON u.id_user = b.id_user
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

function markBookingExpiredForSnap(mysqli $conn, array $booking): void
{
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

    logPaymentEvent($conn, $bookingId, 'booking_expired_before_midtrans', [
        'booking_code' => $booking['booking_code'] ?? '',
        'expired_at' => $now,
    ]);
}

function requestSnapToken(array $payload, array $midtransConfig): array
{
    if (!function_exists('curl_init')) {
        return [
            'success' => false,
            'message' => 'Ekstensi PHP cURL belum aktif. Aktifkan cURL terlebih dahulu untuk menghubungi Midtrans.',
            'raw' => null,
        ];
    }

    $ch = curl_init($midtransConfig['snap_api_url']);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($midtransConfig['server_key'] . ':'),
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($rawResponse === false) {
        return [
            'success' => false,
            'message' => 'Gagal terhubung ke Midtrans: ' . $curlError,
            'raw' => null,
        ];
    }

    $decoded = json_decode($rawResponse, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = $decoded['error_messages'][0] ?? $decoded['message'] ?? 'Midtrans menolak request transaksi.';
        return [
            'success' => false,
            'message' => $message,
            'raw' => $decoded ?: $rawResponse,
        ];
    }

    if (empty($decoded['token'])) {
        return [
            'success' => false,
            'message' => 'Response Midtrans tidak berisi Snap Token.',
            'raw' => $decoded ?: $rawResponse,
        ];
    }

    return [
        'success' => true,
        'token' => $decoded['token'],
        'redirect_url' => $decoded['redirect_url'] ?? '',
        'raw' => $decoded,
    ];
}

if (!midtransIsConfigured()) {
    jsonResponse(false, 'Konfigurasi Midtrans belum lengkap. Isi MIDTRANS_CLIENT_KEY dan MIDTRANS_SERVER_KEY di file .env.', [], 500);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    $input = $_POST;
}

$bookingCode = trim((string) ($input['booking_code'] ?? ''));

if ($bookingCode === '') {
    jsonResponse(false, 'Kode booking wajib dikirim.', [], 422);
}

$booking = fetchBookingForSnap($conn, $userId, $bookingCode);

if (!$booking) {
    jsonResponse(false, 'Booking tidak ditemukan atau bukan milik akun Anda.', [], 404);
}

if (($booking['payment_status'] ?? '') === 'paid') {
    jsonResponse(false, 'Booking ini sudah dibayar.', [], 409);
}

if (($booking['payment_status'] ?? '') !== 'pending') {
    jsonResponse(false, 'Booking tidak dalam status pending.', [], 409);
}

if (isBookingExpiredForSnap($booking)) {
    markBookingExpiredForSnap($conn, $booking);
    jsonResponse(false, 'Booking sudah expired. Silakan booking ulang.', [], 409);
}

if (!empty($booking['snap_token'])) {
    jsonResponse(true, 'Snap Token sudah tersedia.', [
        'snap_token' => $booking['snap_token'],
        'booking_code' => $booking['booking_code'],
        'order_id' => $booking['gateway_order_id'] ?: $booking['booking_code'],
    ]);
}

$totalAmount = (int) round((float) $booking['total_amount']);

if ($totalAmount < 1) {
    jsonResponse(false, 'Total pembayaran harus lebih dari Rp0 untuk diproses melalui Midtrans.', [], 422);
}

$bookingId = (int) $booking['id_booking'];
$orderId = $booking['booking_code'];
$seats = $booking['seats'] ?: ($booking['total_seats'] . ' kursi');
$itemName = 'Tiket ' . ($booking['movie_title'] ?? 'CINEM4') . ' - ' . $seats;

if (strlen($itemName) > 50) {
    $itemName = substr($itemName, 0, 47) . '...';
}

$expiresAt = parseDateJakarta($booking['expires_at'] ?? null);
$remainingMinutes = 15;

if ($expiresAt) {
    $remainingSeconds = max(60, $expiresAt->getTimestamp() - nowJakarta()->getTimestamp());
    $remainingMinutes = max(1, (int) ceil($remainingSeconds / 60));
}

$payload = [
    'transaction_details' => [
        'order_id' => $orderId,
        'gross_amount' => $totalAmount,
    ],
    'customer_details' => [
        'first_name' => (string) ($booking['first_name'] ?? ''),
        'last_name' => (string) ($booking['last_name'] ?? ''),
        'email' => (string) ($booking['email'] ?? ''),
        'phone' => formatPhoneForMidtrans($booking['wa'] ?? ''),
    ],
    'item_details' => [
        [
            'id' => 'CINEM4-' . $bookingId,
            'price' => $totalAmount,
            'quantity' => 1,
            'name' => $itemName,
        ],
    ],
    'custom_expiry' => [
        'expiry_duration' => $remainingMinutes,
        'unit' => 'minute',
    ],
    'callbacks' => [
        'finish' => publicUrl('payment.php?booking=' . urlencode((string) $booking['booking_code'])),
    ],
];

$midtransConfig = midtransConfig();

logPaymentEvent($conn, $bookingId, 'midtrans_snap_request', [
    'order_id' => $orderId,
    'gross_amount' => $totalAmount,
    'promo_code' => $booking['promo_code'] ?? null,
    'discount_amount' => $booking['discount_amount'] ?? 0,
    'expires_at' => $booking['expires_at'] ?? null,
]);

$result = requestSnapToken($payload, $midtransConfig);

if (!$result['success']) {
    logPaymentEvent($conn, $bookingId, 'midtrans_snap_request_failed', [
        'order_id' => $orderId,
        'message' => $result['message'],
        'raw' => $result['raw'],
    ]);

    jsonResponse(false, $result['message'], [], 502);
}

$snapToken = $result['token'];
$redirectUrl = $result['redirect_url'] ?? '';
$gateway = 'midtrans';
$method = 'snap';

$stmt = $conn->prepare("
    UPDATE bookings
    SET payment_gateway = ?,
        payment_method = ?,
        gateway_order_id = ?,
        snap_token = ?
    WHERE id_booking = ?
      AND payment_status = 'pending'
");
$stmt->bind_param("ssssi", $gateway, $method, $orderId, $snapToken, $bookingId);
$stmt->execute();
$stmt->close();

logPaymentEvent($conn, $bookingId, 'midtrans_snap_token_created', [
    'order_id' => $orderId,
    'snap_token' => $snapToken,
    'redirect_url' => $redirectUrl,
]);

jsonResponse(true, 'Snap Token berhasil dibuat.', [
    'snap_token' => $snapToken,
    'redirect_url' => $redirectUrl,
    'booking_code' => $booking['booking_code'],
    'order_id' => $orderId,
]);
