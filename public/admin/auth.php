<?php
/**
 * admin/auth.php
 * Dipakai di setiap halaman admin untuk memastikan user sudah login sebagai admin
 * dan menyediakan helper role/permission agar proteksi tidak hanya bergantung pada menu sidebar.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin']) || !is_array($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

if (!function_exists('currentAdmin')) {
    function currentAdmin(): array
    {
        return $_SESSION['admin'] ?? [];
    }
}

if (!function_exists('currentAdminId')) {
    function currentAdminId(): int
    {
        return (int) ($_SESSION['admin']['id'] ?? 0);
    }
}

if (!function_exists('currentAdminRole')) {
    function currentAdminRole(): string
    {
        return (string) ($_SESSION['admin']['role'] ?? 'admin');
    }
}

if (!function_exists('isSuperAdmin')) {
    function isSuperAdmin(): bool
    {
        return currentAdminRole() === 'superadmin';
    }
}

if (!function_exists('adminRoleLabel')) {
    function adminRoleLabel(?string $role = null): string
    {
        $role = $role ?? currentAdminRole();

        return $role === 'superadmin' ? 'Super Admin' : 'Admin Operasional';
    }
}

if (!function_exists('adminCan')) {
    function adminCan(string $permission): bool
    {
        if (isSuperAdmin()) {
            return true;
        }

        $adminPermissions = [
            'dashboard.view',
            'movies.manage',
            'cinemas.manage',
            'schedules.manage',
            'promotions.manage',
            'bookings.view',
            'bookings.update_status',
            'users.view',
            'users.verify',
        ];

        return in_array($permission, $adminPermissions, true);
    }
}

if (!function_exists('denyAdminAccess')) {
    function denyAdminAccess(string $message = 'Anda tidak memiliki akses ke halaman ini.'): void
    {
        http_response_code(403);
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Akses Ditolak — CINEM4 Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body{min-height:100vh;margin:0;display:grid;place-items:center;background:radial-gradient(900px 420px at 50% -80px,rgba(31,111,255,.18),transparent 65%),#070b14;color:#fff;font-family:system-ui,sans-serif;padding:24px;}
    .card-denied{width:min(100%,480px);padding:34px;border-radius:24px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);box-shadow:0 24px 70px rgba(0,0,0,.35);text-align:center;}
    .icon{width:72px;height:72px;margin:0 auto 18px;border-radius:22px;display:grid;place-items:center;background:rgba(220,53,69,.14);color:#fca5a5;font-size:2rem;border:1px solid rgba(220,53,69,.28);}
    p{color:rgba(255,255,255,.62);margin-bottom:24px;}
    .btn-primary{border-radius:999px;padding:10px 20px;font-weight:700;}
  </style>
</head>
<body>
  <div class="card-denied">
    <div class="icon"><i class="bi bi-shield-lock"></i></div>
    <h1 class="h4 fw-bold mb-2">Akses Ditolak</h1>
    <p>' . $safeMessage . '</p>
    <a href="index.php" class="btn btn-primary"><i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard</a>
  </div>
</body>
</html>';
        exit;
    }
}

if (!function_exists('requirePermission')) {
    function requirePermission(string $permission, string $message = 'Aksi ini hanya dapat dilakukan oleh role yang memiliki izin.'): void
    {
        if (!adminCan($permission)) {
            denyAdminAccess($message);
        }
    }
}

if (!function_exists('requireSuperAdmin')) {
    function requireSuperAdmin(string $message = 'Halaman ini hanya dapat diakses oleh Super Admin.'): void
    {
        if (!isSuperAdmin()) {
            denyAdminAccess($message);
        }
    }
}
