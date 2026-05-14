<?php
session_start();
require 'auth.php';
require '../../app/config/koneksi.php';

$title     = "CINEM4 Admin — Jadwal";
$pageTitle = "Manajemen Jadwal";

/* ── Hapus ── */
if (isset($_GET['delete'])) {
  $id   = (int)$_GET['delete'];
  $stmt = $conn->prepare("UPDATE schedules SET is_active = 0 WHERE id_schedule = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
  header("Location: schedules.php?msg=deleted");
  exit;
}

/* ── Filter ── */
$search  = trim($_GET['q']       ?? '');
$movieId = (int)($_GET['movie']  ?? 0);
$dateF   = trim($_GET['date']    ?? '');

$where  = "WHERE s.is_active = 1";
$params = [];
$types  = "";

if ($movieId > 0) {
  $where   .= " AND s.id_movie = ?";
  $params[] = $movieId;
  $types   .= "i";
}
if ($dateF !== '') {
  $where   .= " AND s.show_date = ?";
  $params[] = $dateF;
  $types   .= "s";
}
if ($search !== '') {
  $where   .= " AND (m.title LIKE ? OR c.name LIKE ? OR s.studio_name LIKE ?)";
  $like     = "%$search%";
  $params[] = $like; $params[] = $like; $params[] = $like;
  $types   .= "sss";
}

$sql = "
  SELECT s.*, m.title AS movie_title, c.name AS cinema_name
  FROM schedules s
  JOIN movies m ON m.id_movie = s.id_movie
  JOIN cinemas c ON c.id_cinema = s.id_cinema
  $where
  ORDER BY s.show_date DESC, s.show_time ASC
";

if (count($params) > 0) {
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $schedules = $stmt->get_result();
  $stmt->close();
} else {
  $schedules = $conn->query($sql);
}

/* Daftar film untuk filter dropdown */
$movieList = $conn->query("SELECT id_movie, title FROM movies WHERE is_active=1 ORDER BY title");

include 'partials/head.php';
include 'partials/sidebar.php';
?>

<div class="adm-main">
  <?php include 'partials/topbar.php'; ?>
  <div class="adm-content">

    <?php if (isset($_GET['msg'])): ?>
      <div class="adm-alert <?= $_GET['msg']==='deleted'?'adm-alert-danger':'adm-alert-success' ?>">
        <i class="bi bi-<?= $_GET['msg']==='deleted'?'trash':'check-circle' ?>-fill"></i>
        <?= $_GET['msg']==='deleted' ? 'Jadwal berhasil dihapus.' : 'Jadwal berhasil disimpan.' ?>
      </div>
    <?php endif; ?>

    <div class="adm-card">
      <div class="adm-card-header">
        <div class="adm-card-title">Daftar Jadwal</div>
        <a href="schedule-form.php" class="adm-btn adm-btn-primary">
          <i class="bi bi-plus-lg"></i> Tambah Jadwal
        </a>
      </div>

      <!-- Filter -->
      <div class="adm-card-body d-none" style="padding-bottom:0;">
        <form class="d-flex flex-wrap gap-2" method="get">
          <input type="text" name="q" class="adm-form-control"
            placeholder="Cari film / bioskop / studio..."
            value="<?= htmlspecialchars($search) ?>" style="max-width:220px;">
          <select name="movie" class="adm-form-control" style="max-width:200px;">
            <option value="">Semua Film</option>
            <?php while ($mv = $movieList->fetch_assoc()): ?>
              <option value="<?= $mv['id_movie'] ?>" <?= $movieId===$mv['id_movie']?'selected':'' ?>>
                <?= htmlspecialchars($mv['title']) ?>
              </option>
            <?php endwhile; ?>
          </select>
          <input type="date" name="date" class="adm-form-control"
            value="<?= htmlspecialchars($dateF) ?>" style="max-width:160px;">
          <button type="submit" class="adm-btn adm-btn-primary">
            <i class="bi bi-search"></i> Cari
          </button>
          <?php if ($search || $movieId || $dateF): ?>
            <a href="schedules.php" class="adm-btn adm-btn-outline">Reset</a>
          <?php endif; ?>
        </form>
      </div>

      <div style="overflow-x:auto; margin-top:12px;">
        <table class="adm-table" data-dt='{}'>
          <thead>
            <tr>
              <th>#</th>
              <th>Film</th>
              <th>Bioskop</th>
              <th>Studio</th>
              <th>Tanggal</th>
              <th>Jam</th>
              <th>Harga</th>
              <th>Status</th>
              <th style="width:100px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($schedules && $schedules->num_rows > 0):
              $no = 1;
              while ($s = $schedules->fetch_assoc()):
                $badgeClass = match($s['status']) {
                  'open'      => 'adm-badge-green',
                  'closed'    => 'adm-badge-gray',
                  'finished'  => 'adm-badge-blue',
                  'cancelled' => 'adm-badge-red',
                  default     => 'adm-badge-gray',
                };
            ?>
            <tr>
              <td style="color:rgba(255,255,255,.35);"><?= $no++ ?></td>
              <td class="fw-semibold" style="color:#fff;"><?= htmlspecialchars($s['movie_title']) ?></td>
              <td><?= htmlspecialchars($s['cinema_name']) ?></td>
              <td><?= htmlspecialchars($s['studio_name']) ?></td>
              <td><?= date('d M Y', strtotime($s['show_date'])) ?></td>
              <td><?= date('H:i', strtotime($s['show_time'])) ?></td>
              <td>Rp <?= number_format((float)$s['price'], 0, ',', '.') ?></td>
              <td><span class="adm-badge <?= $badgeClass ?>"><?= ucfirst($s['status']) ?></span></td>
              <td>
                <div class="d-flex gap-2">
                  <a href="schedule-form.php?id=<?= $s['id_schedule'] ?>"
                     class="adm-btn adm-btn-outline adm-btn-sm">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <a href="schedules.php?delete=<?= $s['id_schedule'] ?>"
                     class="adm-btn adm-btn-danger adm-btn-sm"
                     onclick="return confirm('Hapus jadwal ini?')">
                    <i class="bi bi-trash"></i>
                  </a>
                </div>
              </td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
              <td colspan="9" class="text-center"
                style="padding:32px;color:rgba(255,255,255,.35);">
                Tidak ada jadwal ditemukan.
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