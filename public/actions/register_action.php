<?php
session_start();
require '../../app/config/koneksi.php';
require '../../app/config/mail.php';
require '../../vendor/autoload.php';

date_default_timezone_set("Asia/Jakarta");

use PHPMailer\PHPMailer\PHPMailer;

$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name'] ?? '');
$email      = trim($_POST['email'] ?? '');
$wa         = trim($_POST['wa'] ?? '');
$password   = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

if (
    $first_name === '' ||
    $last_name === '' ||
    $email === '' ||
    $wa === '' ||
    $password === '' ||
    $password_confirm === ''
) {
    $_SESSION['error'] = 'register_empty';
    header("Location: ../join-us.php?mode=register");
    exit;
}

if ($password !== $password_confirm) {
    $_SESSION['error'] = 'password_not_match';
    header("Location: ../join-us.php?mode=register");
    exit;
}

if (!mailIsConfigured()) {
    $_SESSION['error'] = 'mail_config_missing';
    header("Location: ../join-us.php?mode=register");
    exit;
}

/* cek email sudah terdaftar atau belum */
$checkEmail = $conn->prepare("SELECT id_user FROM users WHERE email = ? LIMIT 1");
$checkEmail->bind_param("s", $email);
$checkEmail->execute();
$checkResult = $checkEmail->get_result();

if ($checkResult->num_rows > 0) {
    $_SESSION['error'] = 'email_exists';
    header("Location: ../join-us.php?mode=register");
    exit;
}

/* Hash password */
$hash = password_hash($password, PASSWORD_DEFAULT);

/* Generate OTP */
$verification_code = rand(100000, 999999);
$expired = date("Y-m-d H:i:s", strtotime("+5 minutes"));

/* Simpan ke database */
$stmt = $conn->prepare("INSERT INTO users
(first_name, last_name, email, wa, password, verification_code, verification_expired)
VALUES (?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param(
    "sssssss",
    $first_name,
    $last_name,
    $email,
    $wa,
    $hash,
    $verification_code,
    $expired
);

/*
  tetap tangani kemungkinan race condition:
  misalnya 2 request masuk hampir bersamaan dan UNIQUE email di DB yang menangkap
*/
if (!$stmt->execute()) {
    if ($conn->errno == 1062) {
        $_SESSION['error'] = 'email_exists';
        header("Location: ../join-us.php?mode=register");
        exit;
    }

    $_SESSION['error'] = 'register_failed';
    header("Location: ../join-us.php?mode=register");
    exit;
}

$newUserId = (int) $conn->insert_id;

/* ================== KIRIM EMAIL ================== */
$mail = new PHPMailer(true);
$mail->CharSet = 'UTF-8';
$mail->Encoding = 'base64';
$mail->isHTML(true);

$logoPath = __DIR__ . '/../assets/img/logo-cinem4.png';
if (file_exists($logoPath)) {
    $mail->AddEmbeddedImage($logoPath, 'logo_cinem4');
}

$mailConfig = mailConfig();
configureMailer($mail, $mailConfig);
$mail->addAddress($email);

$mail->Subject = 'Kode Verifikasi CINEM4';

$safeFirstName = htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8');
$safeCode = htmlspecialchars((string)$verification_code, ENT_QUOTES, 'UTF-8');
$currentYear = date('Y');

$mail->Body = <<<HTML
<!doctype html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    @media only screen and (max-width: 480px) {
      .email-outer { padding: 16px 10px !important; }
      .email-card { border-radius: 18px !important; }
      .email-card-inner { padding: 28px 18px !important; }
      .email-logo { width: 168px !important; max-width: 72% !important; margin-bottom: 20px !important; }
      .email-title { font-size: 24px !important; line-height: 1.25 !important; }
      .email-text { font-size: 15px !important; line-height: 1.55 !important; }
      .otp-box { font-size: 32px !important; letter-spacing: 6px !important; padding: 16px 6px !important; }
    }

    @media only screen and (max-width: 360px) {
      .otp-box { font-size: 28px !important; letter-spacing: 4px !important; }
    }
  </style>
</head>
<body style="margin:0; padding:0; background:#0f172a;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; background:#0f172a; margin:0; padding:0;">
    <tr>
      <td class="email-outer" align="center" style="padding:24px 12px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="email-card" style="width:100%; max-width:520px; background:#1e293b; border-radius:22px; text-align:center; color:#ffffff; border:1px solid rgba(255,255,255,0.08); box-shadow:0 14px 38px rgba(0,0,0,0.35);">
          <tr>
            <td class="email-card-inner" align="center" style="font-family:Arial, Helvetica, sans-serif; padding:34px 28px;">
              <img src="cid:logo_cinem4" alt="CINEM4" width="190" class="email-logo" style="display:block; width:190px; max-width:76%; height:auto; margin:0 auto 24px;">

              <h1 class="email-title" style="margin:0 0 14px; font-family:Arial, Helvetica, sans-serif; font-size:28px; line-height:1.25; color:#ffffff; font-weight:800;">
                Verifikasi Akun
              </h1>

              <p class="email-text" style="margin:0 auto; max-width:390px; font-family:Arial, Helvetica, sans-serif; color:#cbd5e1; font-size:16px; line-height:1.65;">
                Halo <strong style="color:#ffffff;">{$safeFirstName}</strong>, terima kasih telah mendaftar di CINEM4. Gunakan kode berikut untuk memverifikasi akun Anda.
              </p>

              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; max-width:360px; margin:28px auto 0;">
                <tr>
                  <td class="otp-box" align="center" style="font-family:Arial, Helvetica, sans-serif; background:#0f172a; border:1px solid rgba(59,130,246,0.70); border-radius:14px; padding:18px 8px; color:#ffffff; font-size:38px; line-height:1; font-weight:800; letter-spacing:8px; white-space:nowrap; box-shadow:0 0 18px rgba(59,130,246,0.35);">
                    {$safeCode}
                  </td>
                </tr>
              </table>

              <p style="margin:24px 0 0; font-family:Arial, Helvetica, sans-serif; color:#94a3b8; font-size:14px; line-height:1.6;">
                Kode ini berlaku selama <strong style="color:#dbeafe;">5 menit</strong>.
              </p>

              <div style="height:1px; line-height:1px; background:#334155; margin:30px 0;"></div>

              <p style="margin:0 auto; max-width:360px; font-family:Arial, Helvetica, sans-serif; color:#94a3b8; font-size:12px; line-height:1.6;">
                Jika Anda tidak merasa membuat akun CINEM4, abaikan email ini.
              </p>

              <p style="margin:16px 0 0; font-family:Arial, Helvetica, sans-serif; color:#64748b; font-size:12px; line-height:1.5;">
                © {$currentYear} CINEM4. All rights reserved.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

$mail->AltBody = "Halo {$first_name}, kode verifikasi CINEM4 Anda adalah {$verification_code}. Kode ini berlaku selama 5 menit.";

try {
    $mail->send();
} catch (Throwable $e) {
    if (!empty($newUserId)) {
        $delete = $conn->prepare("DELETE FROM users WHERE id_user = ? AND is_verified = 0 LIMIT 1");
        $delete->bind_param("i", $newUserId);
        $delete->execute();
        $delete->close();
    }

    $_SESSION['error'] = 'mail_send_failed';
    header("Location: ../join-us.php?mode=register");
    exit;
}

header("Location: ../verify.php?email=" . urlencode($email));
exit;
?>