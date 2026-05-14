<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=UTF-8');

require_once '../../app/config/midtrans.php';

function response(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('curl_init')) {
    response([
        'success' => false,
        'message' => 'PHP cURL belum aktif.',
    ], 500);
}

$config = midtransConfig();
$serverKey = trim((string) $config['server_key']);
$clientKey = trim((string) $config['client_key']);

if (!midtransIsConfigured()) {
    response([
        'success' => false,
        'message' => 'Konfigurasi Midtrans belum valid. Isi MIDTRANS_CLIENT_KEY dan MIDTRANS_SERVER_KEY di file .env dahulu.',
        'debug' => [
            'is_production' => $config['is_production'],
            'snap_api_url' => $config['snap_api_url'],
            'client_key_masked' => function_exists('midtransMaskKey') ? midtransMaskKey($clientKey) : '(masked)',
            'server_key_masked' => function_exists('midtransMaskKey') ? midtransMaskKey($serverKey) : '(masked)',
        ],
    ], 500);
}

$orderId = 'CINEM4-CHECK-' . date('YmdHis') . '-' . random_int(100, 999);
$payload = [
    'transaction_details' => [
        'order_id' => $orderId,
        'gross_amount' => 10000,
    ],
    'customer_details' => [
        'first_name' => 'CINEM4',
        'last_name' => 'Test',
        'email' => 'sandbox@example.com',
    ],
    'item_details' => [
        [
            'id' => 'CHECK-1',
            'price' => 10000,
            'quantity' => 1,
            'name' => 'CINEM4 Sandbox Check',
        ],
    ],
];

$ch = curl_init($config['snap_api_url']);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($serverKey . ':'),
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => 30,
]);

$raw = curl_exec($ch);
$error = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($raw === false) {
    response([
        'success' => false,
        'message' => 'Gagal menghubungi Midtrans: ' . $error,
        'debug' => [
            'snap_api_url' => $config['snap_api_url'],
            'client_key_masked' => function_exists('midtransMaskKey') ? midtransMaskKey($clientKey) : '(masked)',
            'server_key_masked' => function_exists('midtransMaskKey') ? midtransMaskKey($serverKey) : '(masked)',
        ],
    ], 500);
}

$decoded = json_decode($raw, true);

response([
    'success' => $httpCode >= 200 && $httpCode < 300 && !empty($decoded['token']),
    'http_code' => $httpCode,
    'message' => $decoded['error_messages'][0] ?? $decoded['message'] ?? ($decoded['token'] ? 'Snap token berhasil dibuat.' : 'Response tidak berisi token.'),
    'debug' => [
        'environment' => $config['is_production'] ? 'production' : 'sandbox',
        'snap_api_url' => $config['snap_api_url'],
        'client_key_masked' => function_exists('midtransMaskKey') ? midtransMaskKey($clientKey) : '(masked)',
        'server_key_masked' => function_exists('midtransMaskKey') ? midtransMaskKey($serverKey) : '(masked)',
        'order_id' => $orderId,
    ],
    'midtrans_response' => $httpCode >= 200 && $httpCode < 300
        ? [
            'token_exists' => !empty($decoded['token']),
            'redirect_url_exists' => !empty($decoded['redirect_url']),
        ]
        : $decoded,
], $httpCode >= 200 && $httpCode < 300 ? 200 : 502);
