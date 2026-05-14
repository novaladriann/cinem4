<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!mailIsConfigured()) {
    die("Konfigurasi email belum lengkap. Copy .env.example menjadi .env lalu isi MAIL_USERNAME dan MAIL_PASSWORD.\n");
}

try {
    $mail = new PHPMailer(true);
    configureMailer($mail);

    $to = $argv[1] ?? mailConfig()['from_address'];

    $mail->addAddress($to);
    $mail->isHTML(true);
    $mail->Subject = 'CINEM4 SMTP Test';
    $mail->Body = '<h2>CINEM4 SMTP berhasil</h2><p>Email ini dikirim menggunakan konfigurasi dari file .env.</p>';
    $mail->AltBody = 'CINEM4 SMTP berhasil. Email ini dikirim menggunakan konfigurasi dari file .env.';

    $mail->send();
    echo "Email test berhasil dikirim ke {$to}\n";
} catch (Exception $e) {
    echo "Gagal kirim email: {$mail->ErrorInfo}\n";
}
