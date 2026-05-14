<?php
session_start();

/* Kalau sudah login sebagai admin, langsung ke dashboard */
if (isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

require '../../app/config/koneksi.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } else {
        $stmt = $conn->prepare("
            SELECT id_admin, name, email, password, role
            FROM admins
            WHERE email = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($admin && password_verify($password, $admin['password'])) {
            /* Simpan session admin — terpisah dari session user */
            $_SESSION['admin'] = [
                'id'    => $admin['id_admin'],
                'name'  => $admin['name'],
                'email' => $admin['email'],
                'role'  => $admin['role'],
            ];

            /* Update last_login */
            $upd = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE id_admin = ?");
            $upd->bind_param("i", $admin['id_admin']);
            $upd->execute();
            $upd->close();

            header('Location: index.php');
            exit;
        } else {
            $error = 'Email atau password salah.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CINEM4 Admin — Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    :root {
      --c4-bg      : #070b14;
      --c4-nav     : #071a33;
      --c4-primary : #1f6fff;
      --c4-card    : rgba(255,255,255,.06);
    }

    *, *::before, *::after { box-sizing: border-box; }

    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background:
        radial-gradient(1200px 600px at 50% -100px, rgba(31,111,255,.20), transparent 60%),
        var(--c4-bg);
      color: #fff;
      font-family: system-ui, sans-serif;
    }

    .login-wrap {
      width: 100%;
      max-width: 420px;
      padding: 24px 16px;
    }

    /* Logo */
    .login-logo {
      display: flex;
      align-items: center;
      gap: 10px;
      justify-content: center;
      margin-bottom: 32px;
    }
    .login-logo-badge {
      font-size: 11px; font-weight: 700;
      letter-spacing: 1px;
      color: rgba(255,255,255,.5);
      background: rgba(255,255,255,.08);
      border: 1px solid rgba(255,255,255,.14);
      border-radius: 999px;
      padding: 2px 10px;
      text-transform: uppercase;
    }

    /* Card */
    .login-card {
      background: var(--c4-card);
      border: 1px solid rgba(255,255,255,.12);
      backdrop-filter: blur(12px);
      border-radius: 20px;
      padding: 32px 28px;
    }

    .login-card h5 {
      font-weight: 800;
      font-size: 18px;
      margin-bottom: 4px;
    }
    .login-card .subtitle {
      font-size: 13px;
      color: rgba(255,255,255,.45);
      margin-bottom: 24px;
    }

    /* Form */
    .form-label {
      font-size: 13px;
      font-weight: 600;
      color: rgba(255,255,255,.75);
      margin-bottom: 6px;
    }
    .form-control {
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.14);
      border-radius: 12px;
      color: #fff;
      padding: 10px 14px;
      font-size: 14px;
      transition: border-color .2s ease, box-shadow .2s ease;
    }
    .form-control:focus {
      background: rgba(255,255,255,.08);
      border-color: rgba(31,111,255,.55);
      box-shadow: 0 0 0 3px rgba(31,111,255,.15);
      color: #fff;
      outline: none;
    }
    .form-control::placeholder { color: rgba(255,255,255,.30); }

    /* Password toggle */
    .pass-wrap { position: relative; }
    .pass-toggle {
      position: absolute;
      top: 50%; right: 14px;
      transform: translateY(-50%);
      background: none; border: none; padding: 0;
      color: rgba(255,255,255,.40);
      cursor: pointer; font-size: 16px;
      transition: color .2s;
    }
    .pass-toggle:hover { color: rgba(255,255,255,.75); }

    /* Submit */
    .btn-login {
      width: 100%;
      padding: 11px;
      border-radius: 12px;
      border: none;
      background: var(--c4-primary);
      color: #fff;
      font-weight: 700;
      font-size: 15px;
      cursor: pointer;
      transition: all .2s ease;
      box-shadow: 0 8px 24px rgba(31,111,255,.35);
      margin-top: 8px;
    }
    .btn-login:hover {
      background: #1a5fd4;
      transform: translateY(-1px);
      box-shadow: 0 12px 28px rgba(31,111,255,.45);
    }
    .btn-login:active { transform: translateY(0); }

    /* Error */
    .alert-admin {
      background: rgba(220,53,69,.15);
      border: 1px solid rgba(220,53,69,.35);
      border-radius: 12px;
      color: #ff8a95;
      font-size: 13px;
      padding: 10px 14px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* Footer note */
    .login-note {
      text-align: center;
      font-size: 12px;
      color: rgba(255,255,255,.25);
      margin-top: 20px;
    }
  </style>
</head>
<body>

<div class="login-wrap">

  <!-- Logo -->
  <div class="login-logo">
    <img src="../assets/img/logo-cinem4.png" alt="CINEM4" height="38">
    <span class="login-logo-badge">Admin</span>
  </div>

  <!-- Card -->
  <div class="login-card">
    <h5>Selamat Datang</h5>
    <div class="subtitle">Masuk ke panel admin CINEM4</div>

    <?php if ($error !== ''): ?>
      <div class="alert-admin">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">

      <!-- Email -->
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control"
          placeholder="admin@cinem4.com"
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
          required autofocus>
      </div>

      <!-- Password -->
      <div class="mb-4">
        <label class="form-label">Password</label>
        <div class="pass-wrap">
          <input type="password" name="password" id="passInput"
            class="form-control" placeholder="••••••••" required
            style="padding-right:42px;">
          <button type="button" class="pass-toggle" onclick="togglePass()">
            <i class="bi bi-eye" id="passIcon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-login">
        <i class="bi bi-box-arrow-in-right me-2"></i>Masuk
      </button>

    </form>
  </div>

  <div class="login-note">
    &copy; <?= date('Y') ?> CINEM4 &mdash; Admin Panel
  </div>

</div>

<script>
function togglePass() {
  var inp  = document.getElementById('passInput');
  var icon = document.getElementById('passIcon');
  if (inp.type === 'password') {
    inp.type  = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    inp.type  = 'password';
    icon.className = 'bi bi-eye';
  }
}
</script>

</body>
</html>