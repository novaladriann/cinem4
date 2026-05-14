<?php
session_start();
include "../../app/config/koneksi.php";
require '../../app/config/mail.php';

require '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set("Asia/Jakarta");

$email = $_POST['email'] ?? '';

if(empty($email)){
    $_SESSION['error'] = "Email wajib diisi.";
    header("Location: ../forgot_password.php");
    exit;
}

if (!mailIsConfigured()) {
    $_SESSION['error'] = "Konfigurasi email belum lengkap. Isi file .env terlebih dahulu.";
    header("Location: ../forgot_password.php");
    exit;
}

$stmt = $conn->prepare("SELECT id_user FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();

    $token   = bin2hex(random_bytes(32));
    $expired = date("Y-m-d H:i:s", strtotime("+1 hour"));

    $upd = $conn->prepare("UPDATE users SET reset_token = ?, reset_expired = ? WHERE email = ?");
    $upd->bind_param("sss", $token, $expired, $email);
    $upd->execute();
    $upd->close();

    /* URL otomatis mengikuti domain aktif (localhost, .test, dll) */
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$link     = $protocol . '://' . $host . $basePath . '/reset_password.php?token=' . $token;

    $mail = new PHPMailer(true);
    $mail->AddEmbeddedImage('../assets/img/logo-cinem4.png','logo_cinem4');

    try {

        configureMailer($mail);
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Reset Password CINEM4';

        $mail->isHTML(true);
$mail->Subject = 'Reset Password CINEM4';

$mail->Body = "
<div style='font-family:Arial;background:#0f172a;padding:40px'>

    <div style='max-width:520px;margin:auto;background:#1e293b;
    border-radius:14px;padding:35px;text-align:center;color:white;
    box-shadow:0 10px 30px rgba(0,0,0,0.4)'>

        <img src='cid:logo_cinem4' style='width:230px;margin-bottom:20px'>

        <h2 style='margin-top:5px'>Reset Password</h2>

        <p style='color:#cbd5f5;font-size:15px'>
        Kami menerima permintaan untuk mereset password akun CINEM4 Anda.
        Klik tombol di bawah ini untuk membuat password baru.
        </p>

        <a href='$link'
        style='
        display:inline-block;
        margin-top:25px;
        padding:14px 30px;
        background:#3b82f6;
        color:white;
        text-decoration:none;
        border-radius:8px;
        font-weight:bold;
        box-shadow:0 0 10px rgba(59,130,246,0.7);
        '>
        Reset Password
        </a>

        <p style='margin-top:25px;font-size:14px;color:#94a3b8'>
        Link ini hanya berlaku selama <b>1 jam</b>.
        </p>

        <hr style='border:none;border-top:1px solid #334155;margin:30px 0'>

        <p style='font-size:12px;color:#64748b'>
        Jika Anda tidak meminta reset password, abaikan email ini.
        </p>

        <p style='font-size:12px;color:#475569;margin-top:10px'>
        © ".date("Y")." CINEM4. All rights reserved.
        </p>

    </div>

</div>
";
        $mail->send();

        $_SESSION['success'] = "Link reset password berhasil dikirim ke email Anda.";
        header("Location: ../forgot_password.php");
        exit;

    } catch (Exception $e) {

        $_SESSION['error'] = "Email gagal dikirim. Silakan coba lagi.";
        header("Location: ../forgot_password.php");
        exit;

    }

} else {

    $_SESSION['error'] = "Email tidak ditemukan.";
    header("Location: ../forgot_password.php");
    exit;

}
?>