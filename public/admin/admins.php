<?php
session_start();
require 'auth.php';
requireSuperAdmin();
require '../../app/config/koneksi.php';

$title     = "CINEM4 Admin — Kelola Admin";
$pageTitle = "Kelola Admin";

function h($value): string
{
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectAdmins(string $msg): void
{
  header("Location: admins.php?msg=" . urlencode($msg));
  exit;
}

function activeSuperadminCount(mysqli $conn): int
{
  $result = $conn->query("SELECT COUNT(*) AS total FROM admins WHERE role='superadmin' AND is_active=1");
  $row = $result ? $result->fetch_assoc() : ['total' => 0];
  return (int)($row['total'] ?? 0);
}

function findAdminById(mysqli $conn, int $id): ?array
{
  $stmt = $conn->prepare("SELECT * FROM admins WHERE id_admin = ? LIMIT 1");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $admin = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  return $admin ?: null;
}

$me = currentAdminId();
$errors = [];

/* ── Nonaktifkan admin, bukan hapus permanen ── */
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];

  if ($id === $me) {
    redirectAdmins('self_delete');
  }

  $target = findAdminById($conn, $id);
  if (!$target) {
    redirectAdmins('not_found');
  }

  if (($target['role'] ?? '') === 'superadmin' && (int)($target['is_active'] ?? 0) === 1 && activeSuperadminCount($conn) <= 1) {
    redirectAdmins('last_superadmin');
  }

  $stmt = $conn->prepare("UPDATE admins SET is_active = 0 WHERE id_admin = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
  redirectAdmins('disabled');
}

/* ── Toggle aktif/nonaktif ── */
if (isset($_GET['toggle'])) {
  $id = (int)$_GET['toggle'];

  if ($id === $me) {
    redirectAdmins('self_toggle');
  }

  $target = findAdminById($conn, $id);
  if (!$target) {
    redirectAdmins('not_found');
  }

  if (($target['role'] ?? '') === 'superadmin' && (int)($target['is_active'] ?? 0) === 1 && activeSuperadminCount($conn) <= 1) {
    redirectAdmins('last_superadmin');
  }

  $stmt = $conn->prepare("UPDATE admins SET is_active = IF(is_active=1,0,1) WHERE id_admin=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
  redirectAdmins('status_changed');
}

/* ── Ambil data edit ── */
$editId   = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editData = $editId > 0 ? findAdminById($conn, $editId) : null;

if ($editId > 0 && !$editData) {
  redirectAdmins('not_found');
}

/* ── Proses form ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postId   = (int)($_POST['id']      ?? 0);
  $name     = trim($_POST['name']     ?? '');
  $email    = trim($_POST['email']    ?? '');
  $role     = trim($_POST['role']     ?? 'admin');
  $password = trim($_POST['password'] ?? '');
  $isActive = isset($_POST['is_active']) ? 1 : 0;

  if (!in_array($role, ['admin', 'superadmin'], true)) {
    $errors[] = 'Role admin tidak valid.';
  }
  if ($name === '')  $errors[] = 'Nama wajib diisi.';
  if ($email === '') $errors[] = 'Email wajib diisi.';
  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
  if ($postId === 0 && $password === '') $errors[] = 'Password wajib diisi untuk admin baru.';
  if ($password !== '' && strlen($password) < 6) $errors[] = 'Password minimal 6 karakter.';

  $existingAdmin = $postId > 0 ? findAdminById($conn, $postId) : null;
  if ($postId > 0 && !$existingAdmin) {
    $errors[] = 'Data admin yang diedit tidak ditemukan.';
  }

  /* Akun sendiri tidak boleh didowngrade/nonaktif agar tidak terkunci dari panel. */
  if ($postId > 0 && $postId === $me) {
    $role = 'superadmin';
    $isActive = 1;
  }

  /* Cegah menghilangkan superadmin aktif terakhir. */
  if ($postId > 0 && $existingAdmin && (int)$postId !== $me) {
    $wasActiveSuper = (($existingAdmin['role'] ?? '') === 'superadmin') && ((int)($existingAdmin['is_active'] ?? 0) === 1);
    $willRemainActiveSuper = ($role === 'superadmin') && ($isActive === 1);

    if ($wasActiveSuper && !$willRemainActiveSuper && activeSuperadminCount($conn) <= 1) {
      $errors[] = 'Tidak boleh menonaktifkan atau mengubah role superadmin aktif terakhir.';
    }
  }

  /* Cek email duplikat */
  if (empty($errors)) {
    $chk = $conn->prepare("SELECT id_admin FROM admins WHERE email = ? AND id_admin != ? LIMIT 1");
    $chk->bind_param("si", $email, $postId);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) $errors[] = 'Email sudah digunakan admin lain.';
    $chk->close();
  }

  if (empty($errors)) {
    if ($postId > 0) {
      if ($password !== '') {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE admins SET name=?,email=?,role=?,password=?,is_active=? WHERE id_admin=?");
        $stmt->bind_param("ssssii", $name, $email, $role, $hash, $isActive, $postId);
      } else {
        $stmt = $conn->prepare("UPDATE admins SET name=?,email=?,role=?,is_active=? WHERE id_admin=?");
        $stmt->bind_param("sssii", $name, $email, $role, $isActive, $postId);
      }
    } else {
      $hash = password_hash($password, PASSWORD_BCRYPT);
      $stmt = $conn->prepare("INSERT INTO admins (name,email,password,role,is_active) VALUES (?,?,?,?,?)");
      $stmt->bind_param("ssssi", $name, $email, $hash, $role, $isActive);
    }

    if ($stmt->execute()) {
      if ($postId === $me) {
        $_SESSION['admin']['name'] = $name;
        $_SESSION['admin']['email'] = $email;
        $_SESSION['admin']['role'] = $role;
      }
      $stmt->close();
      redirectAdmins('saved');
    } else {
      $errors[] = 'Gagal menyimpan: ' . $conn->error;
      $stmt->close();
    }
  }
}

$totalAdmins = (int)($conn->query("SELECT COUNT(*) FROM admins")->fetch_row()[0] ?? 0);
$activeAdmins = (int)($conn->query("SELECT COUNT(*) FROM admins WHERE is_active=1")->fetch_row()[0] ?? 0);
$superAdmins = (int)($conn->query("SELECT COUNT(*) FROM admins WHERE role='superadmin'")->fetch_row()[0] ?? 0);
$inactiveAdmins = max(0, $totalAdmins - $activeAdmins);
$admins = $conn->query("SELECT * FROM admins ORDER BY role DESC, is_active DESC, id_admin ASC");

include 'partials/head.php';
include 'partials/sidebar.php';
?>

<div class="adm-main">
  <?php include 'partials/topbar.php'; ?>
  <div class="adm-content">

    <?php if (isset($_GET['msg'])): ?>
      <?php
        $msgMap = [
          'saved'           => ['success', 'check-circle',      'Admin berhasil disimpan.'],
          'disabled'        => ['danger',  'pause-circle',      'Admin berhasil dinonaktifkan.'],
          'status_changed'  => ['success', 'check-circle',      'Status admin berhasil diperbarui.'],
          'self_delete'     => ['danger',  'exclamation-circle', 'Anda tidak bisa menonaktifkan akun sendiri.'],
          'self_toggle'     => ['danger',  'exclamation-circle', 'Anda tidak bisa mengubah status akun sendiri.'],
          'last_superadmin' => ['danger',  'shield-lock',        'Harus ada minimal satu superadmin aktif.'],
          'not_found'       => ['danger',  'exclamation-circle', 'Data admin tidak ditemukan.'],
        ];
        [$type, $icon, $text] = $msgMap[$_GET['msg']] ?? ['success','check-circle','OK'];
      ?>
      <div class="adm-alert adm-alert-<?= h($type) ?> mb-3">
        <i class="bi bi-<?= h($icon) ?>-fill"></i> <?= h($text) ?>
      </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="adm-stat">
          <div class="adm-stat-icon" style="background:rgba(31,111,255,.15);color:var(--c4-primary);"><i class="bi bi-people"></i></div>
          <div><div class="adm-stat-val"><?= $totalAdmins ?></div><div class="adm-stat-label">Total Admin</div></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="adm-stat">
          <div class="adm-stat-icon" style="background:rgba(25,135,84,.15);color:#6ee7b7;"><i class="bi bi-check-circle"></i></div>
          <div><div class="adm-stat-val"><?= $activeAdmins ?></div><div class="adm-stat-label">Aktif</div></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="adm-stat">
          <div class="adm-stat-icon" style="background:rgba(31,111,255,.15);color:#93c5fd;"><i class="bi bi-shield-lock"></i></div>
          <div><div class="adm-stat-val"><?= $superAdmins ?></div><div class="adm-stat-label">Super Admin</div></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="adm-stat">
          <div class="adm-stat-icon" style="background:rgba(220,53,69,.15);color:#fca5a5;"><i class="bi bi-pause-circle"></i></div>
          <div><div class="adm-stat-val"><?= $inactiveAdmins ?></div><div class="adm-stat-label">Nonaktif</div></div>
        </div>
      </div>
    </div>

    <div class="adm-alert adm-alert-success mb-4" style="background:rgba(31,111,255,.10);border-color:rgba(31,111,255,.25);color:#bfdbfe;">
      <i class="bi bi-info-circle-fill"></i>
      <div>
        <strong>Role akses:</strong> Admin operasional mengelola konten dan transaksi. Super Admin memiliki akses tambahan untuk mengelola akun admin serta aksi sensitif.
      </div>
    </div>

    <div class="row g-3">

      <!-- Form tambah/edit -->
      <div class="col-12 col-lg-4">
        <div class="adm-card">
          <div class="adm-card-header">
            <div class="adm-card-title">
              <?= $editData ? 'Edit Admin' : 'Tambah Admin' ?>
            </div>
          </div>
          <div class="adm-card-body">

            <?php if (!empty($errors)): ?>
              <div class="adm-alert adm-alert-danger mb-3">
                <i class="bi bi-exclamation-circle-fill"></i>
                <div><?= implode('<br>', array_map('h', $errors)) ?></div>
              </div>
            <?php endif; ?>

            <?php if ($editData && (int)$editData['id_admin'] === $me): ?>
              <div class="adm-alert adm-alert-success mb-3" style="background:rgba(255,193,7,.12);border-color:rgba(255,193,7,.30);color:#fde68a;">
                <i class="bi bi-shield-exclamation"></i>
                <div>Akun Anda sendiri tidak dapat diubah menjadi nonaktif atau diturunkan dari Super Admin.</div>
              </div>
            <?php endif; ?>

            <form method="post">
              <input type="hidden" name="id" value="<?= h($editData['id_admin'] ?? 0) ?>">

              <div class="mb-3">
                <label class="adm-form-label">Nama <span style="color:#ff8a95;">*</span></label>
                <input type="text" name="name" class="adm-form-control" required
                  value="<?= h($editData['name'] ?? $_POST['name'] ?? '') ?>">
              </div>

              <div class="mb-3">
                <label class="adm-form-label">Email <span style="color:#ff8a95;">*</span></label>
                <input type="email" name="email" class="adm-form-control" required
                  value="<?= h($editData['email'] ?? $_POST['email'] ?? '') ?>">
              </div>

              <div class="mb-3">
                <label class="adm-form-label">Role</label>
                <?php $selectedRole = $editData['role'] ?? $_POST['role'] ?? 'admin'; ?>
                <select name="role" class="adm-form-control" <?= ($editData && (int)$editData['id_admin'] === $me) ? 'disabled' : '' ?>>
                  <option value="admin" <?= $selectedRole === 'admin' ? 'selected' : '' ?>>Admin Operasional</option>
                  <option value="superadmin" <?= $selectedRole === 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
                </select>
                <?php if ($editData && (int)$editData['id_admin'] === $me): ?>
                  <input type="hidden" name="role" value="superadmin">
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <label class="adm-form-label">
                  Password
                  <?php if ($editData): ?>
                    <span style="color:rgba(255,255,255,.35);font-weight:400;">(kosongkan jika tidak diubah)</span>
                  <?php else: ?>
                    <span style="color:#ff8a95;">*</span>
                  <?php endif; ?>
                </label>
                <div style="position:relative;">
                  <input type="password" name="password" id="passInput"
                    class="adm-form-control" autocomplete="new-password"
                    placeholder="<?= $editData ? '••••••••' : 'Min. 6 karakter' ?>"
                    style="padding-right:42px;">
                  <button type="button" onclick="togglePass()"
                    style="position:absolute;top:50%;right:12px;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,.4);cursor:pointer;">
                    <i class="bi bi-eye" id="passIcon"></i>
                  </button>
                </div>
              </div>

              <div class="mb-3">
                <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                  <?php $checkedActive = (int)($editData['is_active'] ?? $_POST['is_active'] ?? 1) === 1; ?>
                  <input type="checkbox" name="is_active" value="1"
                    <?= $checkedActive ? 'checked' : '' ?>
                    <?= ($editData && (int)$editData['id_admin'] === $me) ? 'disabled' : '' ?>
                    style="width:16px;height:16px;accent-color:var(--c4-primary);">
                  <span class="adm-form-label mb-0">Akun Aktif</span>
                </label>
                <?php if ($editData && (int)$editData['id_admin'] === $me): ?>
                  <input type="hidden" name="is_active" value="1">
                <?php endif; ?>
              </div>

              <div class="d-flex gap-2 flex-wrap">
                <button type="submit" class="adm-btn adm-btn-primary">
                  <i class="bi bi-save"></i>
                  <?= $editData ? 'Simpan' : 'Tambah' ?>
                </button>
                <?php if ($editData): ?>
                  <a href="admins.php" class="adm-btn adm-btn-outline">Batal</a>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Tabel admin -->
      <div class="col-12 col-lg-8">
        <div class="adm-card">
          <div class="adm-card-header">
            <div>
              <div class="adm-card-title">Daftar Admin</div>
              <div style="font-size:12px;color:rgba(255,255,255,.45);margin-top:3px;">Aksi hapus diganti menjadi nonaktif agar data historis tetap aman.</div>
            </div>
          </div>
          <div style="overflow-x:auto;">
            <table class="adm-table" data-dt='{}'>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Nama</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Last Login</th>
                  <th>Status</th>
                  <th style="width:130px;">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($admins && $admins->num_rows > 0):
                  $no = 1;
                  while ($a = $admins->fetch_assoc()):
                    $isMe = (int)$a['id_admin'] === $me;
                ?>
                <tr>
                  <td style="color:rgba(255,255,255,.35);"><?= $no++ ?></td>
                  <td>
                    <div class="fw-semibold" style="color:#fff;">
                      <?= h($a['name']) ?>
                      <?php if ($isMe): ?>
                        <span class="adm-badge adm-badge-blue ms-1">Anda</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td style="font-size:13px;"><?= h($a['email']) ?></td>
                  <td>
                    <?php if ($a['role'] === 'superadmin'): ?>
                      <span class="adm-badge adm-badge-blue">Super Admin</span>
                    <?php else: ?>
                      <span class="adm-badge adm-badge-gray">Admin Operasional</span>
                    <?php endif; ?>
                  </td>
                  <td style="font-size:12px;color:rgba(255,255,255,.45);">
                    <?= $a['last_login'] ? date('d M Y H:i', strtotime($a['last_login'])) : '—' ?>
                  </td>
                  <td>
                    <?php if ($a['is_active']): ?>
                      <span class="adm-badge adm-badge-green">Aktif</span>
                    <?php else: ?>
                      <span class="adm-badge adm-badge-red">Nonaktif</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="d-flex gap-2">
                      <a href="admins.php?edit=<?= (int)$a['id_admin'] ?>" class="adm-btn adm-btn-outline adm-btn-sm" title="Edit">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <?php if (!$isMe): ?>
                        <a href="admins.php?toggle=<?= (int)$a['id_admin'] ?>" class="adm-btn adm-btn-outline adm-btn-sm" title="<?= $a['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                          <i class="bi bi-<?= $a['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i>
                        </a>
                        <a href="admins.php?delete=<?= (int)$a['id_admin'] ?>" class="adm-btn adm-btn-danger adm-btn-sm" title="Nonaktifkan" onclick="return confirm('Nonaktifkan admin ini? Data tidak akan dihapus permanen.')">
                          <i class="bi bi-slash-circle"></i>
                        </a>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                  <td colspan="7" class="text-center" style="padding:32px;color:rgba(255,255,255,.35);">Belum ada admin.</td>
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

<script>
function togglePass() {
  var inp  = document.getElementById('passInput');
  var icon = document.getElementById('passIcon');
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    inp.type = 'password';
    icon.className = 'bi bi-eye';
  }
}
</script>

<?php include 'partials/footer.php'; ?>
