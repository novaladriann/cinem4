<?php
require_once __DIR__ . '/env.php';

if (!function_exists('midtransConfig')) {
    function midtransConfig(): array
    {
        $isProduction = cinem4_env_bool('MIDTRANS_IS_PRODUCTION', false);

        $clientKey = trim((string) cinem4_env('MIDTRANS_CLIENT_KEY', ''));
        $serverKey = trim((string) cinem4_env('MIDTRANS_SERVER_KEY', ''));

        return [
            'is_production' => $isProduction,
            'client_key' => $clientKey,
            'server_key' => $serverKey,
            'snap_api_url' => $isProduction
                ? 'https://app.midtrans.com/snap/v1/transactions'
                : 'https://app.sandbox.midtrans.com/snap/v1/transactions',
            'snap_js_url' => $isProduction
                ? 'https://app.midtrans.com/snap/snap.js'
                : 'https://app.sandbox.midtrans.com/snap/snap.js',
        ];
    }
}

if (!function_exists('midtransIsConfigured')) {
    function midtransIsConfigured(): bool
    {
        $config = midtransConfig();

        $clientKey = trim((string) ($config['client_key'] ?? ''));
        $serverKey = trim((string) ($config['server_key'] ?? ''));

        if ($clientKey === '' || $serverKey === '') {
            return false;
        }

        $invalidMarkers = ['ISI_CLIENT_KEY', 'ISI_SERVER_KEY', 'YOUR_CLIENT_KEY', 'YOUR_SERVER_KEY', 'PASTE_', 'CHANGE_ME'];

        foreach ($invalidMarkers as $marker) {
            if (str_contains($clientKey, $marker) || str_contains($serverKey, $marker)) {
                return false;
            }
        }

        $validClient = str_starts_with($clientKey, 'Mid-client-') || str_starts_with($clientKey, 'SB-Mid-client-');
        $validServer = str_starts_with($serverKey, 'Mid-server-') || str_starts_with($serverKey, 'SB-Mid-server-');

        return $validClient && $validServer;
    }
}

if (!function_exists('midtransMaskKey')) {
    function midtransMaskKey(string $key): string
    {
        $key = trim($key);

        if ($key === '') {
            return '(empty)';
        }

        if (strlen($key) <= 12) {
            return substr($key, 0, 3) . str_repeat('*', max(0, strlen($key) - 6)) . substr($key, -3);
        }

        return substr($key, 0, 8) . str_repeat('*', max(8, strlen($key) - 14)) . substr($key, -6);
    }
}
