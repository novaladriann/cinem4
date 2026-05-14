<?php
/**
 * Sinkronisasi status manual dari browser setelah Snap callback.
 * Berguna saat development localhost karena webhook Midtrans tidak bisa masuk ke localhost.
 */

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
require_once '../../app/helpers/midtrans_status.php';

@$conn->query("SET time_zone = '+07:00'");

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$bookingCode = trim((string) ($input['booking_code'] ?? ''));
$userId = (int) $_SESSION['user_id'];

if ($bookingCode === '') {
    cinem4MidtransJson(false, 'Kode booking wajib dikirim.', [], 422);
}

$stmt = $conn->prepare("\n    SELECT *\n    FROM bookings\n    WHERE booking_code = ?\n      AND id_user = ?\n    LIMIT 1\n");
$stmt->bind_param('si', $bookingCode, $userId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    cinem4MidtransJson(false, 'Booking tidak ditemukan atau bukan milik akun Anda.', [], 404);
}

$orderId = trim((string) ($booking['gateway_order_id'] ?? ''));
if ($orderId === '') {
    $orderId = trim((string) ($booking['booking_code'] ?? ''));
}

if ($orderId === '') {
    cinem4MidtransJson(false, 'Order ID Midtrans belum tersedia.', [], 409);
}

$config = midtransConfig();
$statusResult = cinem4FetchMidtransStatus($orderId, $config);

if (!$statusResult['success']) {
    cinem4LogPaymentEvent($conn, (int) $booking['id_booking'], 'midtrans_status_sync_failed', [
        'order_id' => $orderId,
        'message' => $statusResult['message'] ?? '',
        'raw' => $statusResult['raw'] ?? null,
    ]);

    cinem4MidtransJson(false, (string) $statusResult['message'], [
        'order_id' => $orderId,
    ], (int) ($statusResult['status_code'] ?? 502));
}

$applyResult = cinem4ApplyMidtransPayload($conn, $statusResult['payload'], $config, 'sync');

cinem4MidtransJson(
    (bool) $applyResult['success'],
    (string) $applyResult['message'],
    [
        'booking_code' => $applyResult['booking_code'] ?? $bookingCode,
        'order_id' => $applyResult['order_id'] ?? $orderId,
        'payment_status' => $applyResult['payment_status'] ?? null,
        'transaction_status' => $applyResult['transaction_status'] ?? null,
    ],
    (int) ($applyResult['status_code'] ?? 200)
);
