<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    header("Location: join-us.php?mode=login");
    exit;
}

require '../app/config/koneksi.php';

$title = "CINEM4 - Edit Profile";
$active = "";
$extra_css = ['assets/css/profile.css'];

$userId = (int) $_SESSION['user_id'];

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatWhatsappLocal($number): string
{
    $number = preg_replace('/\D+/', '', (string) $number);

    if ($number === '') {
        return '';
    }

    if (substr($number, 0, 2) === '62') {
        return '0' . substr($number, 2);
    }

    if (substr($number, 0, 1) === '0') {
        return $number;
    }

    if (substr($number, 0, 1) === '8') {
        return '0' . $number;
    }

    return $number;
}

if (empty($_SESSION['profile_csrf_token'])) {
    $_SESSION['profile_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['profile_csrf_token'];

$stmt = $conn->prepare("SELECT id_user, first_name, last_name, email, wa, is_verified, created_at FROM users WHERE id_user = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: join-us.php?mode=login");
    exit;
}

$success = $_SESSION['profile_success'] ?? '';
$error = $_SESSION['profile_error'] ?? '';

unset($_SESSION['profile_success'], $_SESSION['profile_error']);

$waLocal = formatWhatsappLocal($user['wa'] ?? '');

include '../app/views/partials/public/head.php';
include '../app/views/partials/public/navbar.php';
?>

<main class="profile-page flex-grow-1">
    <section class="profile-content profile-edit-content">
        <div class="container">

            <div class="profile-edit-header mb-4">
                <a href="dashboard.php" class="profile-back-link">
                    <i class="bi bi-arrow-left"></i>
                    Kembali ke Dashboard
                </a>

                <div class="mt-3">
                    <div class="profile-section-kicker">Account Settings</div>
                    <h1 class="profile-edit-title">Edit Profil</h1>
                    <p class="profile-section-desc mb-0">
                        Perbarui informasi akun dan keamanan password Anda.
                    </p>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="profile-alert profile-alert--success mb-4">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?= e($success) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="profile-alert profile-alert--danger mb-4">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="profile-panel h-100">
                        <div class="profile-panel-header">
                            <div>
                                <div class="profile-section-kicker">Profile</div>
                                <h2 class="profile-section-title">Informasi Akun</h2>
                                <p class="profile-section-desc mb-0">
                                    Data ini akan tampil di dashboard profile Anda.
                                </p>
                            </div>
                            <i class="bi bi-person-gear profile-panel-header-icon"></i>
                        </div>

                        <form method="post" action="actions/update_profile_action.php" class="profile-form">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="profile-form-label">First Name</label>
                                    <input type="text"
                                        name="first_name"
                                        class="form-control profile-input"
                                        value="<?= e($user['first_name']) ?>"
                                        maxlength="50"
                                        required>
                                </div>

                                <div class="col-md-6">
                                    <label class="profile-form-label">Last Name</label>
                                    <input type="text"
                                        name="last_name"
                                        class="form-control profile-input"
                                        value="<?= e($user['last_name']) ?>"
                                        maxlength="50"
                                        required>
                                </div>

                                <div class="col-12">
                                    <label class="profile-form-label">Email</label>
                                    <input type="email"
                                        class="form-control profile-input profile-input--readonly"
                                        value="<?= e($user['email']) ?>"
                                        readonly>

                                    <div class="profile-form-help">
                                        Email tidak diedit langsung karena terhubung dengan verifikasi akun.
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="profile-form-label">WhatsApp</label>
                                    <input type="text"
                                        name="wa"
                                        class="form-control profile-input"
                                        value="<?= e($waLocal) ?>"
                                        placeholder="Contoh: 081234567890"
                                        inputmode="numeric"
                                        maxlength="15"
                                        required>

                                    <div class="profile-form-help">
                                        Boleh isi dengan format 0812xxxx atau 812xxxx. Sistem akan merapikan formatnya otomatis.
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                                <button type="submit" class="btn btn-primary rounded-pill px-4 py-2">
                                    <i class="bi bi-save me-1"></i> Simpan Perubahan
                                </button>

                                <a href="dashboard.php" class="btn btn-outline-light border-secondary rounded-pill px-4 py-2">
                                    Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="profile-panel">
                        <div class="profile-panel-header">
                            <div>
                                <div class="profile-section-kicker">Security</div>
                                <h2 class="profile-section-title">Ganti Password</h2>
                                <p class="profile-section-desc mb-0">
                                    Gunakan password yang kuat dan tidak mudah ditebak.
                                </p>
                            </div>
                            <i class="bi bi-shield-lock profile-panel-header-icon"></i>
                        </div>

                        <form method="post" action="actions/change_password_action.php" class="profile-form">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                            <div class="mb-3">
                                <label class="profile-form-label">Password Saat Ini</label>
                                <input type="password"
                                    name="current_password"
                                    class="form-control profile-input"
                                    required>
                            </div>

                            <div class="mb-3">
                                <label class="profile-form-label">Password Baru</label>
                                <input type="password"
                                    name="new_password"
                                    class="form-control profile-input"
                                    minlength="8"
                                    required>

                                <div class="profile-form-help">
                                    Minimal 8 karakter.
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="profile-form-label">Konfirmasi Password Baru</label>
                                <input type="password"
                                    name="confirm_password"
                                    class="form-control profile-input"
                                    minlength="8"
                                    required>
                            </div>

                            <button type="submit" class="btn btn-primary rounded-pill w-100 py-2">
                                <i class="bi bi-key me-1"></i> Update Password
                            </button>
                        </form>
                    </div>

                    <div class="profile-security-note mt-4">
                        <div class="profile-security-note__icon">
                            <i class="bi bi-info-circle"></i>
                        </div>
                        <div>
                            <strong>Saran:</strong>
                            jangan gunakan password yang sama dengan akun lain.
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</main>

<?php include '../app/views/partials/public/footer.php'; ?>