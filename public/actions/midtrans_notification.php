<?php
/**
 * Endpoint webhook Midtrans.
 * Pasang URL publik file ini di Dashboard Midtrans sebagai Payment Notification URL.
 * Contoh lokal via ngrok: https://xxxx.ngrok-free.app/cinem4/public/actions/midtrans_notification.php
 */

date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=UTF-8');

require '../../app/config/koneksi.php';
require_once '../../app/config/midtrans.php';
require_once '../../app/helpers/midtrans_status.php';

@$conn->query("SET time_zone = '+07:00'");

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    cinem4MidtransJson(false, 'Payload notification tidak valid.', [], 400);
}

$config = midtransConfig();
$result = cinem4ApplyMidtransPayload($conn, $payload, $config, 'notification');

cinem4MidtransJson(
    (bool) $result['success'],
    (string) $result['message'],
    [
        'booking_code' => $result['booking_code'] ?? null,
        'order_id' => $result['order_id'] ?? ($payload['order_id'] ?? null),
        'payment_status' => $result['payment_status'] ?? null,
        'transaction_status' => $result['transaction_status'] ?? ($payload['transaction_status'] ?? null),
    ],
    (int) ($result['status_code'] ?? 200)
);
