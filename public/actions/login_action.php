<?php
session_start();
require '../../app/config/koneksi.php';

function redirect_login(string $error): void
{
    $_SESSION['error'] = $error;
    header("Location: ../join-us.php?mode=login");
    exit;
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$captcha  = strtoupper(trim($_POST['captcha'] ?? ''));

if ($email === '' || $password === '') {
    redirect_login('empty');
}

if ($captcha === '') {
    redirect_login('captcha_empty');
}

if (
    empty($_SESSION['login_captcha_code']) ||
    empty($_SESSION['login_captcha_expires']) ||
    time() > (int) $_SESSION['login_captcha_expires']
) {
    unset($_SESSION['login_captcha_code'], $_SESSION['login_captcha_expires']);
    redirect_login('captcha_expired');
}

$expectedCaptcha = strtoupper((string) $_SESSION['login_captcha_code']);

if (!hash_equals($expectedCaptcha, $captcha)) {
    unset($_SESSION['login_captcha_code'], $_SESSION['login_captcha_expires']);
    redirect_login('captcha_wrong');
}

// Captcha hanya boleh dipakai sekali.
unset($_SESSION['login_captcha_code'], $_SESSION['login_captcha_expires']);

$stmt = $conn->prepare("SELECT id_user, first_name, last_name, email, password, is_verified FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect_login('email_not_found');
}

$user = $result->fetch_assoc();

if ((int)$user['is_verified'] !== 1) {
    redirect_login('not_verified');
}

if (!password_verify($password, $user['password'])) {
    redirect_login('wrong_password');
}

session_regenerate_id(true);

$_SESSION['user']    = true;
$_SESSION['user_id'] = $user['id_user'];
$_SESSION['name']    = trim($user['first_name'] . ' ' . $user['last_name']);
$_SESSION['email']   = $user['email'];

header("Location: ../index.php");
exit;
