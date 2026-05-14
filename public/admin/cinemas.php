<?php
session_start();
require 'auth.php';
require '../../app/config/koneksi.php';

$title     = "CINEM4 Admin — Bioskop";
$pageTitle = "Manajemen Bioskop";

/* ── Proses form inline (tambah/edit/hapus) ── */
/* Cek apakah kolom image sudah ada, kalau belum auto ALTER */
$checkCol = $conn->query("SHOW COLUMNS FROM cinemas LIKE 'image'");
if ($checkCol->num_rows === 0) {
  $conn->query("ALTER TABLE cinemas ADD COLUMN image varchar(255) DEFAULT NULL AFTER address");
}

$editData = null;
$editId   = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

/* Hapus */
if (isset($_GET['delete'])) {
  $id   = (int)$_GET['delete'];
  $stmt = $conn->prepare("UPDATE cinemas SET is_active = 0 WHERE id_cinema = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
  header("Location: cinemas.php?msg=deleted");
  exit;
}

/* Ambil data edit */
if ($editId > 0) {
  $stmt = $conn->prepare("SELECT * FROM cinemas WHERE id_cinema = ? LIMIT 1");
  $stmt->bind_param("i", $editId);
  $stmt->execute();
  $editData = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

/* Simpan */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postId  = (int)($_POST['id']      ?? 0);
  $name    = trim($_POST['name']     ?? '');
  $city    = trim($_POST['city']     ?? '');
  $address = trim($_POST['address']  ?? '');
  $active  = isset($_POST['is_active']) ? 1 : 0;
  $image   = trim($_POST['image']    ?? '');

  if ($name === '') $errors[] = 'Nama bioskop wajib diisi.';

  /* Upload foto */
  if (!empty($_FILES['image_file']['name'])) {
    $ext     = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp'];
    if (!in_array($ext, $allowed)) {
      $errors[] = 'Format gambar tidak didukung.';
    } else {
      $slug     = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
      $filename = 'cinema-' . $slug . '.' . $ext;
      if (move_uploaded_file($_FILES['image_file']['tmp_name'], '../assets/img/' . $filename)) {
        $image = 'assets/img/' . $filename;
      } else {
        $errors[] = 'Gagal upload foto.';
      }
    }
  }

  if (empty($errors)) {
    if ($postId > 0) {
      $stmt = $conn->prepare("UPDATE cinemas SET name=?,city=?,address=?,image=?,is_active=? WHERE id_cinema=?");
      $stmt->bind_param("ssssii", $name, $city, $address, $image, $active, $postId);
    } else {
      $stmt = $conn->prepare("INSERT INTO cinemas (name,city,address,image,is_active) VALUES (?,?,?,?,1)");
      $stmt->bind_param("ssss", $name, $city, $address, $image);
    }
    $stmt->execute();
    $stmt->close();
    header("Location: cinemas.php?msg=saved");
    exit;
  }
}

$cinemas = $conn->query("SELECT * FROM cinemas ORDER BY id_cinema DESC");

include 'partials/head.php';
include 'partials/sidebar.php';
?>

<div class="adm-main">
  <?php include 'partials/topbar.php'; ?>
  <div class="adm-content">

    <?php if (isset($_GET['msg'])): ?>
      <div class="adm-alert <?= $_GET['msg']==='deleted'?'adm-alert-danger':'adm-alert-success' ?>">
        <i class="bi bi-<?= $_GET['msg']==='deleted'?'trash':'check-circle' ?>-fill"></i>
        <?= $_GET['msg']==='deleted' ? 'Bioskop berhasil dihapus.' : 'Bioskop berhasil disimpan.' ?>
      </div>
    <?php endif; ?>

    <div class="row g-3">

      <!-- Form tambah/edit -->
      <div class="col-12 col-lg-4">
        <div class="adm-card">
          <div class="adm-card-header">
            <div class="adm-card-title">
              <?= $editData ? 'Edit Bioskop' : 'Tambah Bioskop' ?>
            </div>
          </div>
          <div class="adm-card-body">

            <?php if (!empty($errors)): ?>
              <div class="adm-alert adm-alert-danger mb-3">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
              </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="id" value="<?= $editData['id_cinema'] ?? 0 ?>">

              <div class="mb-3">
                <label class="adm-form-label">Nama Bioskop <span style="color:#ff8a95;">*</span></label>
                <input type="text" name="name" class="adm-form-control" required
                  value="<?= htmlspecialchars($editData['name'] ?? $_POST['name'] ?? '') ?>">
              </div>
              <div class="mb-3">
                <label class="adm-form-label">Kota</label>
                <input type="text" name="city" class="adm-form-control"
                  value="<?= htmlspecialchars($editData['city'] ?? $_POST['city'] ?? '') ?>">
              </div>
              <div class="mb-3">
                <label class="adm-form-label">Alamat</label>
                <textarea name="address" class="adm-form-control" rows="3"
                  ><?= htmlspecialchars($editData['address'] ?? $_POST['address'] ?? '') ?></textarea>
              </div>
              <div class="mb-3">
                <label class="adm-form-label">Foto Bioskop</label>
                <?php $imgVal = $editData['image'] ?? $_POST['image'] ?? ''; ?>
                <?php if (!empty($imgVal)): ?>
                  <div class="mb-2">
                    <img src="../<?= htmlspecialchars($imgVal) ?>"
                      style="height:70px;border-radius:8px;object-fit:cover;width:100%;">
                  </div>
                <?php endif; ?>
                <input type="file" name="image_file" class="adm-form-control" accept="image/*">
                <input type="hidden" name="image" value="<?= htmlspecialchars($imgVal) ?>">
                <div style="font-size:11px;color:rgba(255,255,255,.35);margin-top:4px;">
                  Format: jpg/png/webp.
                </div>
              </div>
              <?php if ($editData): ?>
              <div class="mb-3">
                <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                  <input type="checkbox" name="is_active" value="1"
                    <?= ($editData['is_active'] ?? 1) ? 'checked' : '' ?>
                    style="width:16px;height:16px;accent-color:var(--c4-primary);">
                  <span class="adm-form-label mb-0">Aktif</span>
                </label>
              </div>
              <?php endif; ?>

              <div class="d-flex gap-2">
                <button type="submit" class="adm-btn adm-btn-primary">
                  <i class="bi bi-save"></i>
                  <?= $editData ? 'Simpan' : 'Tambah' ?>
                </button>
                <?php if ($editData): ?>
                  <a href="cinemas.php" class="adm-btn adm-btn-outline">Batal</a>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Tabel bioskop -->
      <div class="col-12 col-lg-8">
        <div class="adm-card">
          <div class="adm-card-header">
            <div class="adm-card-title">Daftar Bioskop</div>
          </div>
          <div style="overflow-x:auto;">
            <table class="adm-table" data-dt='{}'>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Nama</th>
                  <th>Kota</th>
                  <th>Status</th>
                  <th style="width:100px;">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($cinemas && $cinemas->num_rows > 0):
                  $no = 1;
                  while ($c = $cinemas->fetch_assoc()): ?>
                <tr>
                  <td style="color:rgba(255,255,255,.35);"><?= $no++ ?></td>
                  <td>
                    <div class="fw-semibold" style="color:#fff;"><?= htmlspecialchars($c['name']) ?></div>
                    <?php if ($c['address']): ?>
                      <div style="font-size:11px;color:rgba(255,255,255,.35);">
                        <?= htmlspecialchars($c['address']) ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($c['city'] ?? '-') ?></td>
                  <td>
                    <?php if ($c['is_active']): ?>
                      <span class="adm-badge adm-badge-green">Aktif</span>
                    <?php else: ?>
                      <span class="adm-badge adm-badge-gray">Nonaktif</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="d-flex gap-2">
                      <a href="cinemas.php?edit=<?= $c['id_cinema'] ?>"
                         class="adm-btn adm-btn-outline adm-btn-sm">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <a href="cinemas.php?delete=<?= $c['id_cinema'] ?>"
                         class="adm-btn adm-btn-danger adm-btn-sm"
                         onclick="return confirm('Hapus bioskop ini?')">
                        <i class="bi bi-trash"></i>
                      </a>
                    </div>
                  </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                  <td colspan="5" class="text-center"
                    style="padding:32px;color:rgba(255,255,255,.35);">
                    Belum ada bioskop.
                  </td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php include 'partials/footer.php'; ?>