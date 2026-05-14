<?php
session_start();
require 'auth.php';
require '../../app/config/koneksi.php';

$id        = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit    = $id > 0;
$title     = "CINEM4 Admin — " . ($isEdit ? "Edit Jadwal" : "Tambah Jadwal");
$pageTitle = $isEdit ? "Edit Jadwal" : "Tambah Jadwal";

/* ── Ambil data jika edit ── */
$schedule = [];
if ($isEdit) {
  $stmt = $conn->prepare("SELECT * FROM schedules WHERE id_schedule = ? AND is_active = 1 LIMIT 1");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $schedule = $stmt->get_result()->fetch_assoc() ?? [];
  $stmt->close();
  if (!$schedule) {
    header("Location: schedules.php");
    exit;
  }
}

/* Daftar film & bioskop */
/*
  Jadwal hanya boleh dibuat untuk film yang sudah tayang.
  Film coming_soon sengaja tidak ditampilkan di dropdown agar admin tidak membuat jadwal tayang untuk film yang belum rilis/aktif.
*/
$movieList  = $conn->query("SELECT id_movie, title FROM movies WHERE is_active=1 AND status='now_showing' ORDER BY title");
$cinemaList = $conn->query("SELECT id_cinema, name, city FROM cinemas WHERE is_active=1 ORDER BY name");

$errors = [];
$old    = $schedule;

/* ── Proses form ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $old      = $_POST;
  $idMovie  = (int)($_POST['id_movie']              ?? 0);
  $idCinema = (int)($_POST['id_cinema']             ?? 0);
  $studio   = trim($_POST['studio_name']            ?? '');
  $showDate = trim($_POST['show_date']              ?? '');
  $showTime = trim($_POST['show_time']              ?? '');
  $price    = (float)str_replace(',', '', $_POST['price'] ?? 0);
  $capacity = (int)($_POST['seat_capacity']         ?? 40);
  $closeMin = (int)($_POST['booking_close_minutes'] ?? 30);
  $status   = trim($_POST['status']                 ?? 'open');

  if (!$idMovie) {
    $errors[] = 'Film wajib dipilih.';
  } else {
    $stmtMovie = $conn->prepare("SELECT title, status FROM movies WHERE id_movie = ? AND is_active = 1 LIMIT 1");
    $stmtMovie->bind_param("i", $idMovie);
    $stmtMovie->execute();
    $movieCheck = $stmtMovie->get_result()->fetch_assoc();
    $stmtMovie->close();

    if (!$movieCheck) {
      $errors[] = 'Film yang dipilih tidak ditemukan atau sudah nonaktif.';
    } elseif (($movieCheck['status'] ?? '') !== 'now_showing') {
      $errors[] = 'Film "' . $movieCheck['title'] . '" masih berstatus Coming Soon, sehingga belum bisa dibuatkan jadwal tayang.';
    }
  }

  if (!$idCinema)  $errors[] = 'Bioskop wajib dipilih.';
  if ($studio === '') $errors[] = 'Nama studio wajib diisi.';
  if ($showDate === '') $errors[] = 'Tanggal tayang wajib diisi.';
  if ($showTime === '') $errors[] = 'Jam tayang wajib diisi.';
  if ($price <= 0) $errors[] = 'Harga wajib diisi.';

  if (empty($errors)) {
    if ($isEdit) {
      $stmt = $conn->prepare("
        UPDATE schedules SET
          id_movie=?, id_cinema=?, studio_name=?,
          show_date=?, show_time=?, price=?,
          seat_capacity=?, booking_close_minutes=?, status=?,
          updated_at=NOW()
        WHERE id_schedule=?
      ");
      $stmt->bind_param(
        "iisssdiisi",
        $idMovie,
        $idCinema,
        $studio,
        $showDate,
        $showTime,
        $price,
        $capacity,
        $closeMin,
        $status,
        $id
      );
    } else {
      $stmt = $conn->prepare("
        INSERT INTO schedules
          (id_movie, id_cinema, studio_name,
           show_date, show_time, price,
           seat_capacity, booking_close_minutes, status, is_active)
        VALUES (?,?,?,?,?,?,?,?,?,1)
      ");
      $stmt->bind_param(
        "iisssdiis",
        $idMovie,
        $idCinema,
        $studio,
        $showDate,
        $showTime,
        $price,
        $capacity,
        $closeMin,
        $status
      );
    }

    if ($stmt->execute()) {
      header("Location: schedules.php?msg=saved");
      exit;
    } else {
      $errors[] = 'Gagal menyimpan: ' . $conn->error;
    }
    $stmt->close();
  }
}


$currentMovie = null;
$currentMovieId = (int)($old['id_movie'] ?? 0);
if ($currentMovieId > 0) {
  $stmtCurrentMovie = $conn->prepare("SELECT id_movie, title, status FROM movies WHERE id_movie = ? LIMIT 1");
  $stmtCurrentMovie->bind_param("i", $currentMovieId);
  $stmtCurrentMovie->execute();
  $currentMovie = $stmtCurrentMovie->get_result()->fetch_assoc() ?: null;
  $stmtCurrentMovie->close();
}

include 'partials/head.php';
include 'partials/sidebar.php';
?>

<div class="adm-main">
  <?php include 'partials/topbar.php'; ?>
  <div class="adm-content">

    <div class="mb-3" style="font-size:13px; color:rgba(255,255,255,.40);">
      <a href="schedules.php" style="color:rgba(255,255,255,.40); text-decoration:none;">Jadwal</a>
      <span class="mx-2">/</span>
      <span style="color:rgba(255,255,255,.75);"><?= $isEdit ? 'Edit' : 'Tambah' ?></span>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="adm-alert adm-alert-danger mb-3">
        <i class="bi bi-exclamation-circle-fill"></i>
        <div><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($currentMovie && ($currentMovie['status'] ?? '') !== 'now_showing'): ?>
      <div class="adm-alert mb-3" style="border:1px solid rgba(250,204,21,.25);background:rgba(250,204,21,.10);color:#fde68a;">
        <i class="bi bi-info-circle-fill"></i>
        <div>
          Film <strong><?= htmlspecialchars($currentMovie['title']) ?></strong> saat ini berstatus
          <strong><?= htmlspecialchars(str_replace('_', ' ', ucwords($currentMovie['status'] ?? '-'))) ?></strong>.
          Untuk menyimpan jadwal, pilih film lain yang berstatus <strong>Now Showing</strong>.
        </div>
      </div>
    <?php endif; ?>

    <form method="post">
      <div class="row g-3">

        <div class="col-12 col-lg-8">
          <div class="adm-card">
            <div class="adm-card-header">
              <div class="adm-card-title">Detail Jadwal</div>
            </div>
            <div class="adm-card-body">
              <div class="row g-3">

                <!-- Film -->
                <div class="col-12">
                  <label class="adm-form-label">Film <span style="color:#ff8a95;">*</span></label>
                  <select name="id_movie" class="adm-form-control" required>
                    <?php if ($currentMovie && ($currentMovie['status'] ?? '') !== 'now_showing'): ?>
                      <option value="" selected disabled>
                        <?= htmlspecialchars($currentMovie['title']) ?> — Coming Soon, pilih film Now Showing
                      </option>
                    <?php else: ?>
                      <option value="">— Pilih Film Now Showing —</option>
                    <?php endif; ?>

                    <?php
                    $movieList->data_seek(0);
                    while ($mv = $movieList->fetch_assoc()):
                      $selected = ((int)($old['id_movie'] ?? 0) === (int)$mv['id_movie']);
                    ?>
                      <option value="<?= (int)$mv['id_movie'] ?>" <?= $selected ? 'selected' : '' ?>>
                        <?= htmlspecialchars($mv['title']) ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                  <div style="font-size:11px;color:rgba(255,255,255,.35);margin-top:4px;">
                    Hanya film dengan status <strong>Now Showing</strong> yang bisa dibuatkan jadwal tayang.
                  </div>
                </div>

                <!-- Bioskop -->
                <div class="col-12 col-md-6">
                  <label class="adm-form-label">Bioskop <span style="color:#ff8a95;">*</span></label>
                  <select name="id_cinema" class="adm-form-control" required>
                    <option value="">— Pilih Bioskop —</option>
                    <?php while ($c = $cinemaList->fetch_assoc()): ?>
                      <option value="<?= $c['id_cinema'] ?>"
                        <?= ((int)($old['id_cinema'] ?? 0) === $c['id_cinema']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                        <?php if ($c['city']): ?>(<?= htmlspecialchars($c['city']) ?>)<?php endif; ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>

                <!-- Studio -->
                <div class="col-12 col-md-6">
                  <label class="adm-form-label">Nama Studio <span style="color:#ff8a95;">*</span></label>
                  <input type="text" name="studio_name" class="adm-form-control"
                    placeholder="Studio 1, REGULAR, IMAX, dll"
                    value="<?= htmlspecialchars($old['studio_name'] ?? '') ?>" required>
                </div>

                <!-- Tanggal & Jam -->
                <div class="col-12 col-md-6">
                  <label class="adm-form-label">Tanggal Tayang <span style="color:#ff8a95;">*</span></label>
                  <input type="date" name="show_date" class="adm-form-control"
                    value="<?= htmlspecialchars($old['show_date'] ?? '') ?>" required>
                </div>

                <div class="col-12 col-md-6">
                  <label class="adm-form-label">Jam Tayang <span style="color:#ff8a95;">*</span></label>
                  <input type="time" name="show_time" class="adm-form-control"
                    value="<?= htmlspecialchars($old['show_time'] ?? '') ?>" required>
                </div>

                <!-- Harga -->
                <div class="col-12 col-md-4">
                  <label class="adm-form-label">Harga (Rp) <span style="color:#ff8a95;">*</span></label>
                  <input type="number" name="price" class="adm-form-control"
                    min="0" step="500"
                    value="<?= (float)($old['price'] ?? 0) ?>" required>
                </div>

                <!-- Kapasitas -->
                <div class="col-12 col-md-4">
                  <label class="adm-form-label">Kapasitas Kursi</label>
                  <input type="number" name="seat_capacity" class="adm-form-control"
                    min="1" value="<?= (int)($old['seat_capacity'] ?? 40) ?>">
                </div>

                <!-- Batas booking -->
                <div class="col-12 col-md-4">
                  <label class="adm-form-label">Tutup Booking (menit sebelum)</label>
                  <input type="number" name="booking_close_minutes" class="adm-form-control"
                    min="0" value="<?= (int)($old['booking_close_minutes'] ?? 30) ?>">
                  <div style="font-size:11px;color:rgba(255,255,255,.35);margin-top:4px;">
                    Misal: 30 = booking tutup 30 menit sebelum tayang.
                  </div>
                </div>

                <!-- Status -->
                <div class="col-12 col-md-6">
                  <label class="adm-form-label">Status</label>
                  <select name="status" class="adm-form-control">
                    <?php foreach (['open', 'closed', 'finished', 'cancelled'] as $st): ?>
                      <option value="<?= $st ?>"
                        <?= ($old['status'] ?? 'open') === $st ? 'selected' : '' ?>>
                        <?= ucfirst($st) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

              </div>
            </div>
          </div>
        </div>

        <!-- Kolom kanan: aksi -->
        <div class="col-12 col-lg-4">
          <div class="adm-card">
            <div class="adm-card-body">
              <button type="submit" class="adm-btn adm-btn-primary w-100 mb-2"
                style="justify-content:center;">
                <i class="bi bi-save"></i>
                <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Jadwal' ?>
              </button>
              <a href="schedules.php" class="adm-btn adm-btn-outline w-100"
                style="justify-content:center;">Batal</a>
            </div>
          </div>
        </div>

      </div>
    </form>
  </div>
</div>

<?php include 'partials/footer.php'; ?>