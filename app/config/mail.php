<?php
require_once __DIR__ . '/env.php';

if (!function_exists('mailConfig')) {
    function mailConfig(): array
    {
        $username = trim((string) cinem4_env('MAIL_USERNAME', ''));
        $fromAddress = trim((string) cinem4_env('MAIL_FROM_ADDRESS', $username));

        return [
            'host' => trim((string) cinem4_env('MAIL_HOST', 'smtp.gmail.com')),
            'port' => (int) cinem4_env('MAIL_PORT', 587),
            'username' => $username,
            'password' => (string) cinem4_env('MAIL_PASSWORD', ''),
            'encryption' => strtolower(trim((string) cinem4_env('MAIL_ENCRYPTION', 'tls'))),
            'smtp_auth' => cinem4_env_bool('MAIL_SMTP_AUTH', true),
            'from_address' => $fromAddress,
            'from_name' => trim((string) cinem4_env('MAIL_FROM_NAME', 'CINEM4')),
        ];
    }
}

if (!function_exists('mailIsConfigured')) {
    function mailIsConfigured(): bool
    {
        $config = mailConfig();

        if ($config['host'] === '' || $config['from_address'] === '') {
            return false;
        }

        if ($config['smtp_auth']) {
            return $config['username'] !== '' && $config['password'] !== '';
        }

        return true;
    }
}

if (!function_exists('configureMailer')) {
    function configureMailer($mail, ?array $config = null): void
    {
        $config = $config ?: mailConfig();

        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = (bool) $config['smtp_auth'];

        if ($mail->SMTPAuth) {
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
        }

        if ($config['encryption'] !== '' && $config['encryption'] !== 'none') {
            $mail->SMTPSecure = $config['encryption'];
        }

        $mail->Port = (int) $config['port'];
        $mail->setFrom($config['from_address'], $config['from_name']);
    }
}
