<?php

if (!function_exists('cinem4MidtransJson')) {
    function cinem4MidtransJson(bool $success, string $message, array $extra = [], int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message,
        ], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('cinem4MidtransStatusApiBase')) {
    function cinem4MidtransStatusApiBase(array $config): string
    {
        return !empty($config['is_production'])
            ? 'https://api.midtrans.com/v2/'
            : 'https://api.sandbox.midtrans.com/v2/';
    }
}

if (!function_exists('cinem4ColumnExists')) {
    function cinem4ColumnExists(mysqli $conn, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $stmt = $conn->prepare("\n                SELECT COUNT(*) AS total\n                FROM INFORMATION_SCHEMA.COLUMNS\n                WHERE TABLE_SCHEMA = DATABASE()\n                  AND TABLE_NAME = ?\n                  AND COLUMN_NAME = ?\n            ");
            $stmt->bind_param('ss', $table, $column);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $cache[$key] = ((int) ($row['total'] ?? 0)) > 0;
            return $cache[$key];
        } catch (Throwable $e) {
            $cache[$key] = false;
            return false;
        }
    }
}

if (!function_exists('cinem4LogPaymentEvent')) {
    function cinem4LogPaymentEvent(mysqli $conn, ?int $bookingId, string $eventType, array $payload = [], string $gateway = 'midtrans'): void
    {
        if ($bookingId === null || $bookingId <= 0) {
            return;
        }

        try {
            $rawPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $stmt = $conn->prepare("\n                INSERT INTO payment_logs (id_booking, gateway, event_type, raw_payload)\n                VALUES (?, ?, ?, ?)\n            ");
            $stmt->bind_param('isss', $bookingId, $gateway, $eventType, $rawPayload);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            // Logging tidak boleh menggagalkan proses utama.
        }
    }
}

if (!function_exists('cinem4FindBookingByOrderId')) {
    function cinem4FindBookingByOrderId(mysqli $conn, string $orderId): ?array
    {
        $stmt = $conn->prepare("\n            SELECT *\n            FROM bookings\n            WHERE gateway_order_id = ?\n               OR booking_code = ?\n            LIMIT 1\n        ");
        $stmt->bind_param('ss', $orderId, $orderId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $booking ?: null;
    }
}

if (!function_exists('cinem4VerifyMidtransSignature')) {
    function cinem4VerifyMidtransSignature(array $payload, string $serverKey): bool
    {
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signatureKey = (string) ($payload['signature_key'] ?? '');

        if ($orderId === '' || $statusCode === '' || $grossAmount === '' || $signatureKey === '') {
            return false;
        }

        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        return hash_equals($expected, $signatureKey);
    }
}

if (!function_exists('cinem4MoneyToCents')) {
    function cinem4MoneyToCents($value): int
    {
        return (int) round(((float) $value) * 100);
    }
}

if (!function_exists('cinem4MapMidtransStatus')) {
    function cinem4MapMidtransStatus(array $payload): string
    {
        $transactionStatus = strtolower(trim((string) ($payload['transaction_status'] ?? '')));
        $fraudStatus = strtolower(trim((string) ($payload['fraud_status'] ?? '')));

        if ($transactionStatus === 'capture') {
            return ($fraudStatus === '' || $fraudStatus === 'accept') ? 'paid' : 'failed';
        }

        if ($transactionStatus === 'settlement') {
            return 'paid';
        }

        if ($transactionStatus === 'pending') {
            return 'pending';
        }

        if ($transactionStatus === 'expire') {
            return 'expired';
        }

        if ($transactionStatus === 'cancel') {
            return 'cancelled';
        }

        if (in_array($transactionStatus, ['deny', 'failure'], true)) {
            return 'failed';
        }

        // Status seperti authorize/refund/partial_refund tidak ada di enum aplikasi saat ini.
        // Supaya aman, jangan ubah ke paid/failed tanpa keputusan eksplisit.
        return 'pending';
    }
}

if (!function_exists('cinem4BookingStatusForPayment')) {
    function cinem4BookingStatusForPayment(string $paymentStatus, string $currentBookingStatus): string
    {
        if ($paymentStatus === 'paid') {
            return 'completed';
        }

        if ($paymentStatus === 'pending') {
            return 'active';
        }

        return 'cancelled';
    }
}

if (!function_exists('cinem4UpdateBookingColumns')) {
    function cinem4UpdateBookingColumns(mysqli $conn, int $bookingId, array $columns): bool
    {
        $assignments = [];
        $values = [];
        $types = '';

        foreach ($columns as $column => $value) {
            if (!cinem4ColumnExists($conn, 'bookings', $column)) {
                continue;
            }

            $assignments[] = $column . ' = ?';
            $values[] = $value;
            $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
        }

        if (!$assignments) {
            return false;
        }

        $sql = 'UPDATE bookings SET ' . implode(', ', $assignments) . ' WHERE id_booking = ?';
        $values[] = $bookingId;
        $types .= 'i';

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('cinem4ReleaseBookingSeatsIfTerminal')) {
    function cinem4ReleaseBookingSeatsIfTerminal(mysqli $conn, int $bookingId, string $paymentStatus): void
    {
        if (!in_array($paymentStatus, ['failed', 'expired', 'cancelled'], true)) {
            return;
        }

        try {
            $stmt = $conn->prepare('DELETE FROM booking_seats WHERE id_booking = ?');
            $stmt->bind_param('i', $bookingId);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            // Jangan gagalkan update status hanya karena release kursi gagal.
        }
    }
}

if (!function_exists('cinem4IncrementPromoUsage')) {
    function cinem4IncrementPromoUsage(mysqli $conn, string $promoCode, int $bookingId): void
    {
        $promoCode = trim($promoCode);

        if ($promoCode === '') {
            return;
        }

        try {
            $stmt = $conn->prepare("
                UPDATE promotions
                SET used_count = used_count + 1
                WHERE UPPER(code) = UPPER(?)
                  AND is_active = 1
                LIMIT 1
            ");
            $stmt->bind_param('s', $promoCode);
            $stmt->execute();
            $affectedRows = $stmt->affected_rows;
            $stmt->close();

            cinem4LogPaymentEvent($conn, $bookingId, 'promo_usage_incremented', [
                'promo_code' => $promoCode,
                'affected_rows' => $affectedRows,
            ]);
        } catch (Throwable $e) {
            cinem4LogPaymentEvent($conn, $bookingId, 'promo_usage_increment_failed', [
                'promo_code' => $promoCode,
                'message' => $e->getMessage(),
            ]);
        }
    }
}

if (!function_exists('cinem4ApplyMidtransPayload')) {
    function cinem4ApplyMidtransPayload(mysqli $conn, array $payload, array $config, string $source = 'notification'): array
    {
        $orderId = trim((string) ($payload['order_id'] ?? ''));

        if ($orderId === '') {
            return [
                'success' => false,
                'message' => 'Payload Midtrans tidak memiliki order_id.',
                'status_code' => 422,
            ];
        }

        if (!cinem4VerifyMidtransSignature($payload, (string) ($config['server_key'] ?? ''))) {
            cinem4LogPaymentEvent($conn, null, 'midtrans_invalid_signature', $payload);
            return [
                'success' => false,
                'message' => 'Signature Midtrans tidak valid.',
                'status_code' => 403,
            ];
        }

        $booking = cinem4FindBookingByOrderId($conn, $orderId);

        if (!$booking) {
            return [
                'success' => false,
                'message' => 'Booking dengan order_id tersebut tidak ditemukan.',
                'status_code' => 404,
                'order_id' => $orderId,
            ];
        }

        $bookingId = (int) $booking['id_booking'];
        $payloadGross = cinem4MoneyToCents($payload['gross_amount'] ?? 0);
        $bookingGross = cinem4MoneyToCents($booking['total_amount'] ?? 0);

        if ($payloadGross !== $bookingGross) {
            cinem4LogPaymentEvent($conn, $bookingId, 'midtrans_amount_mismatch', [
                'payload' => $payload,
                'booking_total_amount' => $booking['total_amount'] ?? null,
            ]);

            return [
                'success' => false,
                'message' => 'Nominal pembayaran tidak sesuai dengan total booking.',
                'status_code' => 409,
                'booking_code' => $booking['booking_code'] ?? '',
            ];
        }

        $currentPaymentStatus = (string) ($booking['payment_status'] ?? 'pending');
        $newPaymentStatus = cinem4MapMidtransStatus($payload);

        // Jangan downgrade transaksi yang sudah paid menjadi pending/failed karena notifikasi lama/terlambat.
        if ($currentPaymentStatus === 'paid' && $newPaymentStatus !== 'paid') {
            $newPaymentStatus = 'paid';
        }

        $currentBookingStatus = (string) ($booking['booking_status'] ?? 'active');
        $newBookingStatus = cinem4BookingStatusForPayment($newPaymentStatus, $currentBookingStatus);
        $paymentType = trim((string) ($payload['payment_type'] ?? 'snap'));
        $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');

        $columns = [
            'payment_status' => $newPaymentStatus,
            'booking_status' => $newBookingStatus,
            'payment_gateway' => 'midtrans',
            'payment_method' => $paymentType !== '' ? $paymentType : 'snap',
            'gateway_order_id' => $orderId,
        ];

        if (!empty($payload['transaction_id'])) {
            $columns['gateway_transaction_id'] = (string) $payload['transaction_id'];
        }

        if ($newPaymentStatus === 'paid') {
            $columns['paid_at'] = (string) ($payload['settlement_time'] ?? $payload['transaction_time'] ?? $now);
        }

        if ($newPaymentStatus === 'failed') {
            $columns['failed_at'] = $now;
        }

        if (in_array($newPaymentStatus, ['expired', 'cancelled'], true)) {
            $columns['cancelled_at'] = $now;
        }

        cinem4UpdateBookingColumns($conn, $bookingId, $columns);

        if ($currentPaymentStatus !== 'paid' && $newPaymentStatus === 'paid' && !empty($booking['promo_code'])) {
            cinem4IncrementPromoUsage($conn, (string) $booking['promo_code'], $bookingId);
        }

        cinem4ReleaseBookingSeatsIfTerminal($conn, $bookingId, $newPaymentStatus);

        cinem4LogPaymentEvent($conn, $bookingId, 'midtrans_status_' . $source, [
            'old_payment_status' => $currentPaymentStatus,
            'new_payment_status' => $newPaymentStatus,
            'payload' => $payload,
        ]);

        return [
            'success' => true,
            'message' => 'Status booking berhasil disinkronkan.',
            'status_code' => 200,
            'booking_code' => $booking['booking_code'] ?? '',
            'order_id' => $orderId,
            'payment_status' => $newPaymentStatus,
            'transaction_status' => $payload['transaction_status'] ?? '',
        ];
    }
}

if (!function_exists('cinem4FetchMidtransStatus')) {
    function cinem4FetchMidtransStatus(string $orderId, array $config): array
    {
        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'message' => 'Ekstensi PHP cURL belum aktif.',
                'status_code' => 500,
            ];
        }

        $url = cinem4MidtransStatusApiBase($config) . rawurlencode($orderId) . '/status';
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode((string) $config['server_key'] . ':'),
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $rawResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($rawResponse === false) {
            return [
                'success' => false,
                'message' => 'Gagal menghubungi Midtrans: ' . $curlError,
                'status_code' => 502,
            ];
        }

        $decoded = json_decode($rawResponse, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            return [
                'success' => false,
                'message' => $decoded['status_message'] ?? $decoded['message'] ?? 'Status transaksi belum tersedia di Midtrans.',
                'status_code' => $httpCode ?: 502,
                'raw' => $decoded ?: $rawResponse,
            ];
        }

        if (!is_array($decoded)) {
            return [
                'success' => false,
                'message' => 'Response status Midtrans tidak valid.',
                'status_code' => 502,
            ];
        }

        return [
            'success' => true,
            'message' => 'Status transaksi ditemukan.',
            'status_code' => 200,
            'payload' => $decoded,
        ];
    }
}
