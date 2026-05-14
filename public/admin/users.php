<?php
session_start();
require 'auth.php';
require '../../app/config/koneksi.php';

$title     = "CINEM4 Admin — Users";
$pageTitle = "Manajemen Users";

/* ── Toggle verified ── */
if (isset($_GET['toggle_verified'])) {
  $id   = (int)$_GET['toggle_verified'];
  $stmt = $conn->prepare("UPDATE users SET is_verified = IF(is_verified=1,0,1) WHERE id_user=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
  header("Location: users.php");
  exit;
}

/* ── Filter ── */
$search = trim($_GET['q'] ?? '');
$where  = "WHERE 1=1";
$params = [];
$types  = "";

if ($search !== '') {
  $where   .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR wa LIKE ?)";
  $like     = "%$search%";
  $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
  $types   .= "ssss";
}

$sql = "SELECT * FROM users $where ORDER BY id_user DESC";
if (count($params) > 0) {
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $users = $stmt->get_result();
  $stmt->close();
} else {
  $users = $conn->query($sql);
}

$totalUsers = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0] ?? 0;
$verified   = $conn->query("SELECT COUNT(*) FROM users WHERE is_verified=1")->fetch_row()[0] ?? 0;

include 'partials/head.php';
include 'partials/sidebar.php';
?>

<div class="adm-main">
  <?php include 'partials/topbar.php'; ?>
  <div class="adm-content">

    <!-- Stat mini -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="adm-stat">
          <div class="adm-stat-icon" style="background:rgba(31,111,255,.15);color:var(--c4-primary);">
            <i class="bi bi-people"></i>
          </div>
          <div>
            <div class="adm-stat-val"><?= $totalUsers ?></div>
            <div class="adm-stat-label">Total Users</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="adm-stat">
          <div class="adm-stat-icon" style="background:rgba(25,135,84,.15);color:#6ee7b7;">
            <i class="bi bi-patch-check"></i>
          </div>
          <div>
            <div class="adm-stat-val"><?= $verified ?></div>
            <div class="adm-stat-label">Terverifikasi</div>
          </div>
        </div>
      </div>
    </div>

    <div class="adm-card">
      <div class="adm-card-header">
        <div class="adm-card-title">Daftar Users</div>
      </div>

      <!-- Filter -->
      <div class="adm-card-body d-none" style="padding-bottom:0;">
        <form class="d-flex flex-wrap gap-2" method="get">
          <input type="text" name="q" class="adm-form-control"
            placeholder="Nama / email / no. WA..."
            value="<?= htmlspecialchars($search) ?>" style="max-width:260px;">
          <button type="submit" class="adm-btn adm-btn-primary">
            <i class="bi bi-search"></i> Cari
          </button>
          <?php if ($search): ?>
            <a href="users.php" class="adm-btn adm-btn-outline">Reset</a>
          <?php endif; ?>
        </form>
      </div>

      <div style="overflow-x:auto; margin-top:12px;">
        <table class="adm-table" data-dt='{}'>
          <thead>
            <tr>
              <th>#</th>
              <th>Nama</th>
              <th>Email</th>
              <th>No. WA</th>
              <th>Bergabung</th>
              <th>Verifikasi</th>
              <th>Booking</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($users && $users->num_rows > 0):
              $no = 1;
              while ($u = $users->fetch_assoc()):
                /* Hitung total booking user ini */
                $bCount = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE id_user=?");
                $bCount->bind_param("i", $u['id_user']);
                $bCount->execute();
                $bTotal = $bCount->get_result()->fetch_row()[0] ?? 0;
                $bCount->close();
            ?>
            <tr>
              <td style="color:rgba(255,255,255,.35);"><?= $no++ ?></td>
              <td>
                <div class="fw-semibold" style="color:#fff;">
                  <?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?>
                </div>
              </td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><?= htmlspecialchars($u['wa'] ?? '-') ?></td>
              <td style="font-size:12px;">
                <?= date('d M Y', strtotime($u['created_at'])) ?>
              </td>
              <td>
                <?php if ($u['is_verified']): ?>
                  <span class="adm-badge adm-badge-green">Verified</span>
                <?php else: ?>
                  <span class="adm-badge adm-badge-yellow">Pending</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="adm-badge adm-badge-blue"><?= $bTotal ?> booking</span>
              </td>
              <td>
                <a href="users.php?toggle_verified=<?= $u['id_user'] ?>"
                   class="adm-btn adm-btn-outline adm-btn-sm"
                   title="<?= $u['is_verified'] ? 'Batalkan verifikasi' : 'Verifikasi manual' ?>">
                  <i class="bi bi-<?= $u['is_verified'] ? 'x-circle' : 'patch-check' ?>"></i>
                </a>
              </td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
              <td colspan="8" class="text-center"
                style="padding:32px;color:rgba(255,255,255,.35);">
                Tidak ada user ditemukan.
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