<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    header("Location: ../join-us.php?mode=login");
    exit;
}

require '../../app/config/koneksi.php';

function redirectEdit(string $type, string $message): void
{
    $_SESSION[$type] = $message;
    header("Location: ../edit_profile.php");
    exit;
}

function rotateCsrfToken(): void
{
    $_SESSION['profile_csrf_token'] = bin2hex(random_bytes(32));
}

function normalizeWhatsappForDb($number): string
{
    $number = preg_replace('/\D+/', '', (string) $number);

    if ($number === '') {
        return '';
    }

    if (substr($number, 0, 2) === '62') {
        $number = substr($number, 2);
    }

    if (substr($number, 0, 1) === '0') {
        $number = substr($number, 1);
    }

    return $number;
}

$userId = (int) $_SESSION['user_id'];

$csrfToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['profile_csrf_token'] ?? '';

if ($csrfToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
    redirectEdit('profile_error', 'Sesi tidak valid. Silakan coba lagi.');
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$wa = normalizeWhatsappForDb($_POST['wa'] ?? '');

if ($firstName === '' || $lastName === '' || $wa === '') {
    redirectEdit('profile_error', 'Nama dan nomor WhatsApp wajib diisi.');
}

if (strlen($firstName) > 50 || strlen($lastName) > 50) {
    redirectEdit('profile_error', 'Nama maksimal 50 karakter.');
}

if (!preg_match('/^8[0-9]{8,12}$/', $wa)) {
    redirectEdit('profile_error', 'Format nomor WhatsApp tidak valid. Gunakan contoh 081234567890.');
}

$stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, wa = ? WHERE id_user = ?");
$stmt->bind_param("sssi", $firstName, $lastName, $wa, $userId);

if (!$stmt->execute()) {
    $stmt->close();
    redirectEdit('profile_error', 'Gagal memperbarui profil. Silakan coba lagi.');
}

$stmt->close();

$_SESSION['name'] = trim($firstName . ' ' . $lastName);

rotateCsrfToken();

redirectEdit('profile_success', 'Profil berhasil diperbarui.');