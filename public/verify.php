<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../app/config/koneksi.php';
date_default_timezone_set("Asia/Jakarta");

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$email = $_GET['email'] ?? '';
$message = "";
$type = ""; // success / danger

if (!$email) {
  $message = "Email tidak ditemukan di URL!";
  $type = "danger";
}

if (isset($_POST['kode'])) {

  $kode = trim($_POST['kode']);

  $stmt = $conn->prepare("SELECT * FROM users 
        WHERE email=? 
        AND verification_code=? 
        AND verification_expired > NOW()");

  $stmt->bind_param("ss", $email, $kode);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {

    $update = $conn->prepare("UPDATE users SET is_verified=1 WHERE email=?");
    $update->bind_param("s", $email);
    $update->execute();

    $message = "Akun berhasil diverifikasi!";
    $type = "success";
  } else {
    $message = "Kode salah atau sudah kadaluarsa!";
    $type = "danger";
  }
}
?>

<?php
$title = "CINEM4 - Verifikasi Email";
$active = "";
include '../app/views/partials/public/head.php';
include '../app/views/partials/public/navbar.php';
?>

<main class="verify-page flex-grow-1 d-flex align-items-center py-4 py-md-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-5 col-md-7">

        <div class="card-glass verify-card p-4 p-lg-5">

          <div class="text-center mb-4">
            <h3 class="fw-bold text-light">Verifikasi Email</h3>
            <div class="text-secondary small">
              Masukkan 6 digit kode yang dikirim ke email Anda
            </div>
          </div>

          <?php if ($message): ?>
            <div class="alert alert-<?= $type === 'success' ? 'success' : 'danger' ?> text-center">
              <?= $message ?>

              <?php if ($type === 'success'): ?>
                <div class="mt-2">
                  <a href="join-us.php?mode=login" class="btn btn-primary rounded-pill px-4">
                    Login Sekarang
                  </a>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if ($type !== 'success'): ?>
            <form method="post">
              <div class="mb-3">
                <label class="form-label text-light">Kode Verifikasi</label>
                <input
                  type="text"
                  name="kode"
                  inputmode="numeric"
                  pattern="[0-9]{6}"
                  autocomplete="one-time-code"
                  maxlength="6"
                  class="form-control form-control-lg bg-transparent text-light border-secondary"
                  placeholder="Contoh: 483920"
                  required>
              </div>

              <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg rounded-pill">
                  Verifikasi
                </button>
              </div>
            </form>
          <?php endif; ?>

        </div>

      </div>
    </div>
  </div>
</main>

<?php include '../app/views/partials/public/footer.php'; ?>