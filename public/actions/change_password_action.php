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

$userId = (int) $_SESSION['user_id'];

$csrfToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['profile_csrf_token'] ?? '';

if ($csrfToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
    redirectEdit('profile_error', 'Sesi tidak valid. Silakan coba lagi.');
}

$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    redirectEdit('profile_error', 'Semua field password wajib diisi.');
}

if (strlen($newPassword) < 8) {
    redirectEdit('profile_error', 'Password baru minimal 8 karakter.');
}

if ($newPassword !== $confirmPassword) {
    redirectEdit('profile_error', 'Konfirmasi password baru tidak sama.');
}

$stmt = $conn->prepare("SELECT password FROM users WHERE id_user = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: ../join-us.php?mode=login");
    exit;
}

if (!password_verify($currentPassword, $user['password'])) {
    redirectEdit('profile_error', 'Password saat ini salah.');
}

if (password_verify($newPassword, $user['password'])) {
    redirectEdit('profile_error', 'Password baru tidak boleh sama dengan password lama.');
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id_user = ?");
$stmt->bind_param("si", $newHash, $userId);

if (!$stmt->execute()) {
    $stmt->close();
    redirectEdit('profile_error', 'Gagal mengganti password. Silakan coba lagi.');
}

$stmt->close();

session_regenerate_id(true);
rotateCsrfToken();

redirectEdit('profile_success', 'Password berhasil diperbarui.');