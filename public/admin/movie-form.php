<?php
session_start();
require 'auth.php';
require '../../app/config/koneksi.php';

$id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit  = $id > 0;
$title   = "CINEM4 Admin — " . ($isEdit ? "Edit Film" : "Tambah Film");
$pageTitle = $isEdit ? "Edit Film" : "Tambah Film";

/* ── Ambil data jika edit ── */
$movie = [];
if ($isEdit) {
  $stmt = $conn->prepare("SELECT * FROM movies WHERE id_movie = ? AND is_active = 1 LIMIT 1");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $movie = $stmt->get_result()->fetch_assoc() ?? [];
  $stmt->close();
  if (!$movie) {
    header("Location: movies.php");
    exit;
  }
}

/* ── Helper: buat slug ── */
function makeSlug(string $str): string {
  $str = strtolower(trim($str));
  $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
  $str = preg_replace('/[\s-]+/', '-', $str);
  return trim($str, '-');
}

$errors = [];
$old    = $movie; // nilai awal dari DB (edit) atau kosong (tambah)

/* ── Proses form ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $old = $_POST;

  $titleVal    = trim($_POST['title']           ?? '');
  $slug        = makeSlug($titleVal);
  $genre       = trim($_POST['genre']           ?? '');
  $duration    = (int)($_POST['duration_minute'] ?? 0);
  $rating_age  = trim($_POST['rating_age']      ?? '');
  $release     = trim($_POST['release_date']    ?? '') ?: null;
  $status      = trim($_POST['status']          ?? 'coming_soon');
  $synopsis    = trim($_POST['synopsis']        ?? '');
  $poster      = trim($_POST['poster']          ?? ($movie['poster'] ?? ''));
  $backdrop    = trim($_POST['backdrop']        ?? ($movie['backdrop'] ?? ''));
  $trailer_url = trim($_POST['trailer_url']     ?? '');
  $is_featured = isset($_POST['is_featured'])   ? 1 : 0;
  $show_in_hero= isset($_POST['show_in_hero'])  ? 1 : 0;
  $hero_order  = (int)($_POST['hero_order']     ?? 0);

  /* Validasi */
  if ($titleVal === '') $errors[] = 'Judul film wajib diisi.';
  if ($genre    === '') $errors[] = 'Genre wajib diisi.';

  /* Cek slug duplikat (kecuali diri sendiri saat edit) */
  if (empty($errors)) {
    $chk = $conn->prepare("SELECT id_movie FROM movies WHERE slug = ? AND id_movie != ? LIMIT 1");
    $chk->bind_param("si", $slug, $id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
      /* Tambah angka di belakang slug agar unik */
      $slug = $slug . '-' . time();
    }
    $chk->close();
  }

  /* Upload poster (jika ada file baru) */
  if (!empty($_FILES['poster_file']['name'])) {
    $ext     = strtolower(pathinfo($_FILES['poster_file']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp'];
    if (!in_array($ext, $allowed)) {
      $errors[] = 'Format poster tidak didukung (jpg/jpeg/png/webp).';
    } else {
      $uploadDir = '../assets/img/';
      $filename  = 'p-' . $slug . '.' . $ext;
      if (move_uploaded_file($_FILES['poster_file']['tmp_name'], $uploadDir . $filename)) {
        $poster = 'assets/img/' . $filename;
      } else {
        $errors[] = 'Gagal upload poster.';
      }
    }
  }

  /* Upload backdrop (jika ada file baru) */
  if (!empty($_FILES['backdrop_file']['name'])) {
    $ext     = strtolower(pathinfo($_FILES['backdrop_file']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp'];
    if (!in_array($ext, $allowed)) {
      $errors[] = 'Format backdrop tidak didukung (jpg/jpeg/png/webp).';
    } else {
      $uploadDir = '../assets/img/';
      $filename  = 'banner-' . $slug . '.' . $ext;
      if (move_uploaded_file($_FILES['backdrop_file']['tmp_name'], $uploadDir . $filename)) {
        $backdrop = 'assets/img/' . $filename;
      } else {
        $errors[] = 'Gagal upload backdrop.';
      }
    }
  }

  if (empty($errors)) {
    if ($isEdit) {
      $stmt = $conn->prepare("
        UPDATE movies SET
          title=?, slug=?, genre=?, duration_minute=?, rating_age=?,
          release_date=?, status=?, synopsis=?, poster=?, backdrop=?,
          trailer_url=?, is_featured=?, show_in_hero=?, hero_order=?,
          updated_at=NOW()
        WHERE id_movie=?
      ");
      $stmt->bind_param("sssisssssssiiii",
        $titleVal, $slug, $genre, $duration, $rating_age,
        $release, $status, $synopsis, $poster, $backdrop,
        $trailer_url, $is_featured, $show_in_hero, $hero_order,
        $id
      );
    } else {
      $stmt = $conn->prepare("
        INSERT INTO movies
          (title, slug, genre, duration_minute, rating_age,
           release_date, status, synopsis, poster, backdrop,
           trailer_url, is_featured, show_in_hero, hero_order, is_active)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)
      ");
      $stmt->bind_param("sssisssssssiii",
        $titleVal, $slug, $genre, $duration, $rating_age,
        $release, $status, $synopsis, $poster, $backdrop,
        $trailer_url, $is_featured, $show_in_hero, $hero_order
      );
    }

    if ($stmt->execute()) {
      header("Location: movies.php?msg=saved");
      exit;
    } else {
      $errors[] = 'Gagal menyimpan: ' . $conn->error;
    }
    $stmt->close();
  }
}

include 'partials/head.php';
include 'partials/sidebar.php';
?>

<div class="adm-main">
  <?php include 'partials/topbar.php'; ?>

  <div class="adm-content">

    <!-- Breadcrumb -->
    <div class="mb-3" style="font-size:13px; color:rgba(255,255,255,.40);">
      <a href="movies.php" style="color:rgba(255,255,255,.40); text-decoration:none;">Film</a>
      <span class="mx-2">/</span>
      <span style="color:rgba(255,255,255,.75);"><?= $isEdit ? 'Edit' : 'Tambah' ?></span>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="adm-alert adm-alert-danger mb-3">
        <i class="bi bi-exclamation-circle-fill"></i>
        <div><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <div class="row g-3">

        <!-- Kolom kiri -->
        <div class="col-12 col-lg-8">
          <div class="adm-card mb-3">
            <div class="adm-card-header"><div class="adm-card-title">Informasi Film</div></div>
            <div class="adm-card-body">
              <div class="row g-3">

                <div class="col-12">
                  <label class="adm-form-label">Judul Film <span style="color:#ff8a95;">*</span></label>
                  <input type="text" name="title" class="adm-form-control"
                    value="<?= htmlspecialchars($old['title'] ?? '') ?>" required>
                </div>

                <div class="col-12 col-md-6">
                  <label class="adm-form-label">Genre <span style="color:#ff8a95;">*</span></label>
                  <input type="text" name="genre" class="adm-form-control"
                    placeholder="Horror, Drama, dll"
                    value="<?= htmlspecialchars($old['genre'] ?? '') ?>" required>
                </div>

                <div class="col-6 col-md-3">
                  <label class="adm-form-label">Durasi (menit)</label>
                  <input type="number" name="duration_minute" class="adm-form-control"
                    min="0" value="<?= (int)($old['duration_minute'] ?? 0) ?>">
                </div>

                <div class="col-6 col-md-3">
                  <label class="adm-form-label">Rating Usia</label>
                  <select name="rating_age" class="adm-form-control">
                    <option value="">— Pilih —</option>
                    <?php foreach (['SU','D13','R13','D17','D21','21'] as $r): ?>
                      <option value="<?= $r ?>" <?= ($old['rating_age'] ?? '')===$r?'selected':'' ?>>
                        <?= $r ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-12 col-md-6">
                  <label class="adm-form-label">Status</label>
                  <select name="status" class="adm-form-control">
                    <option value="now_showing" <?= ($old['status']??'')==='now_showing'?'selected':'' ?>>Now Showing</option>
                    <option value="coming_soon" <?= ($old['status']??'')==='coming_soon'?'selected':'' ?>>Coming Soon</option>
                  </select>
                </div>

                <div class="col-12 col-md-6">
                  <label class="adm-form-label">Tanggal Rilis</label>
                  <input type="date" name="release_date" class="adm-form-control"
                    value="<?= htmlspecialchars($old['release_date'] ?? '') ?>">
                </div>

                <div class="col-12">
                  <label class="adm-form-label">Sinopsis</label>
                  <textarea name="synopsis" class="adm-form-control" rows="5"
                    ><?= htmlspecialchars($old['synopsis'] ?? '') ?></textarea>
                </div>

                <div class="col-12">
                  <label class="adm-form-label">URL Trailer (YouTube embed)</label>
                  <input type="text" name="trailer_url" class="adm-form-control"
                    placeholder="https://www.youtube.com/embed/..."
                    value="<?= htmlspecialchars($old['trailer_url'] ?? '') ?>">
                </div>

              </div>
            </div>
          </div>

          <!-- Gambar -->
          <div class="adm-card">
            <div class="adm-card-header"><div class="adm-card-title">Gambar</div></div>
            <div class="adm-card-body">
              <div class="row g-3">

                <!-- Poster -->
                <div class="col-12 col-md-6">
                  <label class="adm-form-label">Poster</label>
                  <?php if (!empty($old['poster'])): ?>
                    <div class="mb-2">
                      <img src="../<?= htmlspecialchars($old['poster']) ?>"
                        style="height:80px;border-radius:8px;object-fit:cover;">
                    </div>
                  <?php endif; ?>
                  <input type="file" name="poster_file" class="adm-form-control"
                    accept="image/*">
                  <div style="font-size:11px;color:rgba(255,255,255,.35);margin-top:4px;">
                    Upload file baru untuk mengganti. Format: jpg/png/webp
                  </div>
                  <input type="hidden" name="poster" value="<?= htmlspecialchars($old['poster'] ?? '') ?>">
                </div>

                <!-- Backdrop -->
                <div class="col-12 col-md-6">
                  <label class="adm-form-label">Backdrop (Banner Hero)</label>
                  <?php if (!empty($old['backdrop'])): ?>
                    <div class="mb-2">
                      <img src="../<?= htmlspecialchars($old['backdrop']) ?>"
                        style="height:80px;border-radius:8px;object-fit:cover;width:100%;">
                    </div>
                  <?php endif; ?>
                  <input type="file" name="backdrop_file" class="adm-form-control"
                    accept="image/*">
                  <div style="font-size:11px;color:rgba(255,255,255,.35);margin-top:4px;">
                    Gambar landscape untuk hero section. Format: jpg/png/webp
                  </div>
                  <input type="hidden" name="backdrop" value="<?= htmlspecialchars($old['backdrop'] ?? '') ?>">
                </div>

              </div>
            </div>
          </div>
        </div>

        <!-- Kolom kanan -->
        <div class="col-12 col-lg-4">
          <div class="adm-card mb-3">
            <div class="adm-card-header"><div class="adm-card-title">Pengaturan Tampilan</div></div>
            <div class="adm-card-body">

              <div class="mb-3">
                <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                  <input type="checkbox" name="is_featured" value="1"
                    <?= !empty($old['is_featured']) ? 'checked' : '' ?>
                    style="width:16px;height:16px;accent-color:var(--c4-primary);">
                  <span class="adm-form-label mb-0">Tampilkan sebagai Featured</span>
                </label>
                <div style="font-size:11px;color:rgba(255,255,255,.35);margin-top:4px;padding-left:24px;">
                  Film akan muncul di carousel Now Showing / Upcoming halaman home.
                </div>
              </div>

              <div class="mb-3">
                <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                  <input type="checkbox" name="show_in_hero" value="1"
                    <?= !empty($old['show_in_hero']) ? 'checked' : '' ?>
                    style="width:16px;height:16px;accent-color:var(--c4-primary);">
                  <span class="adm-form-label mb-0">Tampilkan di Hero Slider</span>
                </label>
                <div style="font-size:11px;color:rgba(255,255,255,.35);margin-top:4px;padding-left:24px;">
                  Film akan muncul di slider besar bagian atas halaman home.
                </div>
              </div>

              <div>
                <label class="adm-form-label">Urutan Hero</label>
                <input type="number" name="hero_order" class="adm-form-control"
                  min="0" value="<?= (int)($old['hero_order'] ?? 0) ?>">
                <div style="font-size:11px;color:rgba(255,255,255,.35);margin-top:4px;">
                  Angka lebih kecil tampil lebih dahulu.
                </div>
              </div>

            </div>
          </div>

          <!-- Aksi -->
          <div class="adm-card">
            <div class="adm-card-body">
              <button type="submit" class="adm-btn adm-btn-primary w-100 mb-2"
                style="justify-content:center;">
                <i class="bi bi-save"></i>
                <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Film' ?>
              </button>
              <a href="movies.php" class="adm-btn adm-btn-outline w-100"
                style="justify-content:center;">
                Batal
              </a>
            </div>
          </div>
        </div>

      </div>
    </form>
  </div>
</div>

<?php include 'partials/footer.php'; ?>