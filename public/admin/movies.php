<?php
session_start();
require 'auth.php';
require '../../app/config/koneksi.php';

$title     = "CINEM4 Admin — Film";
$pageTitle = "Manajemen Film";

/* ── Hapus film ── */
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  // soft delete — is_active = 0
  $stmt = $conn->prepare("UPDATE movies SET is_active = 0 WHERE id_movie = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
  header("Location: movies.php?msg=deleted");
  exit;
}

/* ── Toggle featured ── */
if (isset($_GET['toggle_featured'])) {
  $id   = (int)$_GET['toggle_featured'];
  $stmt = $conn->prepare("UPDATE movies SET is_featured = IF(is_featured=1,0,1) WHERE id_movie = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
  header("Location: movies.php");
  exit;
}

/* ── Filter & Search ── */
$search = trim($_GET['q']      ?? '');
$status = trim($_GET['status'] ?? '');

$where  = "WHERE is_active = 1";
$params = [];
$types  = "";

if ($search !== '') {
  $where   .= " AND (title LIKE ? OR genre LIKE ?)";
  $like     = "%$search%";
  $params[] = $like;
  $params[] = $like;
  $types   .= "ss";
}
if ($status !== '') {
  $where   .= " AND status = ?";
  $params[] = $status;
  $types   .= "s";
}

$sql = "SELECT * FROM movies $where ORDER BY id_movie DESC";
if (count($params) > 0) {
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $movies = $stmt->get_result();
  $stmt->close();
} else {
  $movies = $conn->query($sql);
}

include 'partials/head.php';
include 'partials/sidebar.php';
?>

<div class="adm-main">
  <?php include 'partials/topbar.php'; ?>

  <div class="adm-content">

    <?php if (isset($_GET['msg'])): ?>
      <div class="adm-alert <?= $_GET['msg']==='deleted' ? 'adm-alert-danger' : 'adm-alert-success' ?>">
        <i class="bi bi-<?= $_GET['msg']==='deleted' ? 'trash' : 'check-circle' ?>-fill"></i>
        <?= $_GET['msg']==='deleted' ? 'Film berhasil dihapus.' : 'Film berhasil disimpan.' ?>
      </div>
    <?php endif; ?>

    <div class="adm-card">
      <!-- Header -->
      <div class="adm-card-header">
        <div class="adm-card-title">Daftar Film</div>
        <a href="movie-form.php" class="adm-btn adm-btn-primary">
          <i class="bi bi-plus-lg"></i> Tambah Film
        </a>
      </div>

      <!-- Filter -->
      <div class="adm-card-body d-none" style="padding-bottom:0;">
        <form class="d-flex flex-wrap gap-2" method="get">
          <input type="text" name="q" class="adm-form-control"
            placeholder="Cari judul / genre..." value="<?= htmlspecialchars($search) ?>"
            style="max-width:260px;">
          <select name="status" class="adm-form-control" style="max-width:180px;">
            <option value="">Semua Status</option>
            <option value="now_showing"  <?= $status==='now_showing'  ?'selected':'' ?>>Now Showing</option>
            <option value="coming_soon"  <?= $status==='coming_soon'  ?'selected':'' ?>>Coming Soon</option>
          </select>
          <button type="submit" class="adm-btn adm-btn-primary">
            <i class="bi bi-search"></i> Cari
          </button>
          <?php if ($search || $status): ?>
            <a href="movies.php" class="adm-btn adm-btn-outline">Reset</a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Table -->
      <div style="overflow-x:auto; margin-top:12px;">
        <table class="adm-table" data-dt='{}'>
          <thead>
            <tr>
              <th style="width:50px;">#</th>
              <th>Film</th>
              <th>Genre</th>
              <th>Durasi</th>
              <th>Status</th>
              <th>Featured</th>
              <th style="width:130px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($movies && $movies->num_rows > 0):
              $no = 1;
              while ($m = $movies->fetch_assoc()):
                $statusBadge = $m['status'] === 'now_showing'
                  ? '<span class="adm-badge adm-badge-green">Now Showing</span>'
                  : '<span class="adm-badge adm-badge-yellow">Coming Soon</span>';
                $dur = '';
                if ($m['duration_minute'] > 0) {
                  $h = floor($m['duration_minute']/60);
                  $s = $m['duration_minute'] % 60;
                  $dur = $h > 0 ? "{$h}h {$s}m" : "{$s}m";
                }
            ?>
            <tr>
              <td style="color:rgba(255,255,255,.35);"><?= $no++ ?></td>
              <td>
                <div class="d-flex align-items-center gap-3">
                  <?php if (!empty($m['poster'])): ?>
                    <img src="../<?= htmlspecialchars($m['poster']) ?>"
                      style="width:36px;height:50px;object-fit:cover;border-radius:6px;flex-shrink:0;">
                  <?php endif; ?>
                  <div>
                    <div class="fw-semibold" style="color:#fff;"><?= htmlspecialchars($m['title']) ?></div>
                    <div style="font-size:11px;color:rgba(255,255,255,.35);"><?= htmlspecialchars($m['slug']) ?></div>
                  </div>
                </div>
              </td>
              <td><?= htmlspecialchars($m['genre']) ?></td>
              <td><?= $dur ?: '-' ?></td>
              <td><?= $statusBadge ?></td>
              <td>
                <a href="movies.php?toggle_featured=<?= $m['id_movie'] ?>"
                  title="Toggle featured"
                  style="color:<?= $m['is_featured'] ? '#fde68a' : 'rgba(255,255,255,.25)' ?>; font-size:18px; text-decoration:none;">
                  <i class="bi bi-star<?= $m['is_featured'] ? '-fill' : '' ?>"></i>
                </a>
              </td>
              <td>
                <div class="d-flex gap-2">
                  <a href="movie-form.php?id=<?= $m['id_movie'] ?>"
                     class="adm-btn adm-btn-outline adm-btn-sm" title="Edit">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <a href="movies.php?delete=<?= $m['id_movie'] ?>"
                     class="adm-btn adm-btn-danger adm-btn-sm"
                     title="Hapus"
                     onclick="return confirm('Hapus film ini?')">
                    <i class="bi bi-trash"></i>
                  </a>
                </div>
              </td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
              <td colspan="7" class="text-center" style="padding:32px;color:rgba(255,255,255,.35);">
                Tidak ada film ditemukan.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>

<?php include 'partials/footer.php'; ?>