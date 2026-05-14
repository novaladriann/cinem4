<?php
session_start();
$title  = "CINEM4 - Join Us";
$active = "";
include '../app/views/partials/public/head.php';
include '../app/views/partials/public/navbar.php';

$mode = $_GET['mode'] ?? 'register';
$isLogin = ($mode === 'login');
?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 rounded-3 mb-3">
        <?php
        $err = $_SESSION['error'];

        if ($err == "empty"): ?>
            <i class="bi bi-exclamation-circle"></i>
            <span>Email dan password wajib diisi.</span>

        <?php elseif ($err == "not_verified"): ?>
            <i class="bi bi-envelope-exclamation"></i>
            <span>Akun belum diverifikasi. Silakan cek email.</span>

        <?php elseif ($err == "wrong_password"): ?>
            <i class="bi bi-shield-lock"></i>
            <span>Password salah.</span>

        <?php elseif ($err == "email_not_found"): ?>
            <i class="bi bi-person-x"></i>
            <span>Email tidak ditemukan.</span>

        <?php elseif ($err == "captcha_empty"): ?>
            <i class="bi bi-shield-exclamation"></i>
            <span>Kode captcha wajib diisi.</span>

        <?php elseif ($err == "captcha_wrong"): ?>
            <i class="bi bi-shield-x"></i>
            <span>Kode captcha salah. Silakan coba lagi.</span>

        <?php elseif ($err == "captcha_expired"): ?>
            <i class="bi bi-clock-history"></i>
            <span>Kode captcha sudah kedaluwarsa. Silakan masukkan kode baru.</span>

        <?php elseif ($err == "register_empty"): ?>
            <i class="bi bi-exclamation-circle"></i>
            <span>Semua field register wajib diisi.</span>

        <?php elseif ($err == "password_not_match"): ?>
            <i class="bi bi-shield-exclamation"></i>
            <span>Password dan konfirmasi password tidak sama.</span>

        <?php elseif ($err == "email_exists"): ?>
            <i class="bi bi-envelope-x"></i>
            <span>Email sudah terdaftar. Silakan gunakan email lain.</span>

        <?php elseif ($err == "register_failed"): ?>
            <i class="bi bi-x-circle"></i>
            <span>Registrasi gagal. Silakan coba lagi.</span>

        <?php elseif ($err == "mail_config_missing"): ?>
            <i class="bi bi-envelope-x"></i>
            <span>Konfigurasi email belum lengkap. Isi file <code>.env</code> terlebih dahulu.</span>

        <?php elseif ($err == "mail_send_failed"): ?>
            <i class="bi bi-envelope-exclamation"></i>
            <span>Email verifikasi gagal dikirim. Periksa konfigurasi SMTP di file <code>.env</code>.</span>

        <?php endif; ?>
    </div>
<?php
    unset($_SESSION['error']);
endif;
?>


<link rel="stylesheet" href="assets/css/auth.css">

<div class="container py-5">

    <div class="auth-shell <?= $isLogin ? 'is-login' : 'is-register' ?>">

        <div class="auth-body">

            <!-- ================= REGISTER ================= -->
            <div class="auth-pane auth-pane--register">
                <div class="auth-pane-inner">

                    <h2 class="auth-title text-light text-center mb-3">
                        Create your CINEM4 Account
                    </h2>

                    <div class="text-secondary text-center mb-4">
                        Sudah punya akun?
                        <a class="auth-link" href="?mode=login" data-auth="login">Log in</a>
                    </div>

                    <form class="row g-3" method="post" action="actions/register_action.php">

                        <div class="col-md-6">
                            <label class="form-label text-light">First Name</label>
                            <input class="form-control bg-dark text-light border-secondary"
                                name="first_name"
                                placeholder="First Name"
                                required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-light">Last Name</label>
                            <input class="form-control bg-dark text-light border-secondary"
                                name="last_name"
                                placeholder="Last Name"
                                required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-light">Email</label>
                            <input type="email"
                                class="form-control bg-dark text-light border-secondary"
                                name="email"
                                placeholder="you@email.com"
                                required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-light">Whatsapp</label>

                            <div class="input-group">
                                <span class="input-group-text bg-dark text-light border-secondary">
                                    +62
                                </span>

                                <input class="form-control bg-dark text-light border-secondary"
                                    name="wa"
                                    placeholder="812xxxxxxx"
                                    required>
                            </div>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label text-light">Password</label>

                            <div class="input-group">

                                <input type="password"
                                    class="form-control bg-dark text-light border-secondary"
                                    name="password"
                                    required>

                                <button class="btn btn-dark border-secondary"
                                    type="button"
                                    data-toggle-pass>

                                    <i class="bi bi-eye"></i>

                                </button>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label text-light">Confirm Password</label>

                            <div class="input-group">

                                <input type="password"
                                    class="form-control bg-dark text-light border-secondary"
                                    name="password_confirm"
                                    required>

                                <button class="btn btn-dark border-secondary"
                                    type="button"
                                    data-toggle-pass>

                                    <i class="bi bi-eye"></i>

                                </button>

                            </div>

                        </div>

                        <div class="col-12">

                            <div class="form-check">

                                <input class="form-check-input"
                                    type="checkbox"
                                    id="tos"
                                    required>

                                <label class="form-check-label text-light" for="tos">

                                    Saya setuju dengan syarat & ketentuan

                                </label>

                            </div>

                        </div>

                        <div class="col-12">

                            <button class="btn btn-light w-100 py-2 fw-semibold rounded-4">

                                Create Account

                            </button>

                        </div>

                    </form>

                </div>
            </div>


            <!-- ================= LOGIN ================= -->
            <div class="auth-pane auth-pane--login">
                <div class="auth-pane-inner">

                    <h2 class="auth-title text-light text-center mb-3">
                        Login to CINEM4
                    </h2>

                    <div class="text-secondary text-center mb-4">
                        Belum punya akun?
                        <a class="auth-link" href="?mode=register" data-auth="register">Daftar di sini</a>
                    </div>

                    <form method="post" action="actions/login_action.php" class="row g-3">

                        <div class="col-12">

                            <label class="form-label text-light">Email</label>

                            <input type="email"
                                class="form-control bg-dark text-light border-secondary"
                                name="email"
                                placeholder="you@email.com"
                                required>

                        </div>

                        <div class="col-12">

                            <label class="form-label text-light">Password</label>

                            <div class="input-group">

                                <input type="password"
                                    class="form-control bg-dark text-light border-secondary"
                                    name="password"
                                    required>

                                <button class="btn btn-dark border-secondary"
                                    type="button"
                                    data-toggle-pass>

                                    <i class="bi bi-eye"></i>

                                </button>

                            </div>

                        </div>

                        <div class="col-12">

                            <label class="form-label text-light">Captcha</label>

                            <div class="captcha-preview mb-2">
                                <img id="loginCaptcha"
                                    class="captcha-img"
                                    src="actions/captcha_image.php?ts=<?= time() ?>"
                                    alt="Kode captcha login">

                                <button class="btn btn-dark border-secondary captcha-refresh"
                                    type="button"
                                    data-refresh-captcha
                                    aria-label="Refresh captcha">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>

                            <input type="text"
                                class="form-control bg-dark text-light border-secondary text-uppercase"
                                name="captcha"
                                maxlength="5"
                                placeholder="Masukkan kode captcha"
                                autocomplete="off"
                                required>

                            <div class="form-text text-secondary mt-2">
                                Masukkan 5 karakter yang tampil pada gambar.
                            </div>

                        </div>

                        <div class="col-12 d-flex justify-content-end">

                            <a href="forgot_password.php" class="auth-link">
                                Lupa Password?
                            </a>

                        </div>

                        <div class="col-12">

                            <button class="btn btn-light w-100 py-2 fw-semibold rounded-4">

                                Log In

                            </button>

                        </div>

                    </form>

                </div>
            </div>


            <!-- ================= COVER ================= -->
            <div class="auth-cover">
                <div class="auth-cover-inner">

                    <div class="auth-big auth-big--register">
                        Join CINEM4
                    </div>

                    <div class="auth-big auth-big--login">
                        Welcome Back
                    </div>

                    <div class="auth-sub auth-sub--register">

                        Buat akun CINEM4 untuk pengalaman booking
                        film yang lebih cepat dan mudah.

                    </div>

                    <div class="auth-sub auth-sub--login">

                        Login untuk melanjutkan booking
                        film favorit kamu.

                    </div>

                </div>
            </div>

        </div>
    </div>
</div>


<script>
    // switch login register
    document.addEventListener('click', (e) => {
        const link = e.target.closest('[data-auth]');
        if (!link) return;

        e.preventDefault();

        const mode = link.getAttribute('data-auth');
        const shell = document.querySelector('.auth-shell');

        shell.classList.toggle('is-login', mode === 'login');
        shell.classList.toggle('is-register', mode !== 'login');

        const url = new URL(window.location);
        url.searchParams.set('mode', mode);
        history.pushState({}, '', url);
    });


    // toggle password
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-toggle-pass]');
        if (!btn) return;

        const input = btn.parentElement.querySelector('input');

        input.type = input.type === 'password' ? 'text' : 'password';

        const icon = btn.querySelector('i');
        icon.className = input.type === 'password' ?
            'bi bi-eye' :
            'bi bi-eye-slash';

    });

    // refresh captcha login
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-refresh-captcha]');
        if (!btn) return;

        const captchaImg = document.getElementById('loginCaptcha');
        if (!captchaImg) return;

        captchaImg.src = 'actions/captcha_image.php?ts=' + Date.now();
    });
</script>

<?php include '../app/views/partials/public/footer.php'; ?>