<?php
session_start();
require 'auth.php';
require '../../app/config/koneksi.php';

$title     = "CINEM4 Admin — Promosi";
$pageTitle = "Manajemen Promosi";

function e($value): string {
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiahAdmin($value): string {
  if ($value === null || $value === '') return '-';
  return 'Rp' . number_format((float) $value, 0, ',', '.');
}

function promoDiscountLabel(array $promo): string {
  $type  = $promo['discount_type'] ?? '';
  $value = (float) ($promo['discount_value'] ?? 0);

  if ($type === 'percent') {
    $label = rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',') . '%';
    if ($promo['max_discount'] !== null && $promo['max_discount'] !== '') {
      $label .= ' maks. ' . rupiahAdmin($promo['max_discount']);
    }
    return $label;
  }

  if ($type === 'fixed') {
    return rupiahAdmin($value);
  }

  return '-';
}

function promoPeriodLabel(array $promo): string {
  $start = $promo['start_date'] ?? null;
  $end   = $promo['end_date'] ?? null;

  if ($start && $end) {
    return date('d M Y', strtotime($start)) . ' — ' . date('d M Y', strtotime($end));
  }

  if ($start) {
    return 'Mulai ' . date('d M Y', strtotime($start));
  }

  if ($end) {
    return 'Sampai ' . date('d M Y', strtotime($end));
  }

  return '-';
}

function promoStatusBadge(array $promo): string {
  $today     = date('Y-m-d');
  $isActive  = (int) ($promo['is_active'] ?? 0) === 1;
  $startDate = $promo['start_date'] ?? null;
  $endDate   = $promo['end_date'] ?? null;
  $quota     = $promo['quota'];
  $usedCount = (int) ($promo['used_count'] ?? 0);

  if (!$isActive) {
    return '<span class="adm-badge adm-badge-gray">Nonaktif</span>';
  }

  if ($startDate && $today < $startDate) {
    return '<span class="adm-badge adm-badge-yellow">Belum Mulai</span>';
  }

  if ($endDate && $today > $endDate) {
    return '<span class="adm-badge adm-badge-red">Expired</span>';
  }

  if ($quota !== null && $quota !== '' && $usedCount >= (int) $quota) {
    return '<span class="adm-badge adm-badge-red">Kuota Habis</span>';
  }

  return '<span class="adm-badge adm-badge-green">Aktif</span>';
}

/* Soft delete: promosi dinonaktifkan agar histori booking tetap aman */
if (isset($_GET['delete'])) {
  $id   = (int) $_GET['delete'];
  $stmt = $conn->prepare("UPDATE promotions SET is_active = 0, updated_at = NOW() WHERE id_promotion = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
  header("Location: promotions.php?msg=deleted");
  exit;
}

$promos = $conn->query("SELECT * FROM promotions ORDER BY id_promotion DESC");

include 'partials/head.php';
include 'partials/sidebar.php';
?>

<div class="adm-main">
  <?php include 'partials/topbar.php'; ?>
  <div class="adm-content">

    <?php if (isset($_GET['msg'])): ?>
      <?php
        $isDeleted = $_GET['msg'] === 'deleted';
        $alertClass = $isDeleted ? 'adm-alert-danger' : 'adm-alert-success';
        $icon = $isDeleted ? 'slash-circle' : 'check-circle';
        $message = $isDeleted ? 'Promosi berhasil dinonaktifkan.' : 'Promosi berhasil disimpan.';
      ?>
      <div class="adm-alert <?= $alertClass ?> mb-3">
        <i class="bi bi-<?= $icon ?>-fill"></i>
        <?= e($message) ?>
      </div>
    <?php endif; ?>

    <div class="adm-card">
      <div class="adm-card-header">
        <div>
          <div class="adm-card-title">Daftar Promosi</div>
          <div style="font-size:12px;color:rgba(255,255,255,.40);margin-top:3px;">
            Kelola promo tampilan sekaligus kode diskon yang dipakai user di halaman pembayaran.
          </div>
        </div>
        <a href="promo-form.php" class="adm-btn adm-btn-primary">
          <i class="bi bi-plus-lg"></i> Tambah Promosi
        </a>
      </div>
      <div style="overflow-x:auto;">
        <table class="adm-table" data-dt='{}'>
          <thead>
            <tr>
              <th>#</th>
              <th>Gambar</th>
              <th>Promosi</th>
              <th>Kode</th>
              <th>Diskon</th>
              <th>Min. Beli</th>
              <th>Kuota</th>
              <th>Periode</th>
              <th>Status</th>
              <th style="width:100px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($promos && $promos->num_rows > 0):
              $no = 1;
              while ($p = $promos->fetch_assoc()):
                $quotaText = ($p['quota'] === null || $p['quota'] === '')
                  ? 'Tanpa batas'
                  : ((int) $p['used_count'] . '/' . (int) $p['quota']);
            ?>
            <tr>
              <td style="color:rgba(255,255,255,.35);"><?= $no++ ?></td>
              <td>
                <?php if (!empty($p['image'])): ?>
                  <img src="../<?= e($p['image']) ?>"
                    style="height:44px;width:80px;object-fit:cover;border-radius:8px;">
                <?php else: ?>
                  <div style="width:80px;height:44px;background:rgba(255,255,255,.06);border-radius:8px;display:grid;place-items:center;color:rgba(255,255,255,.25);">
                    <i class="bi bi-image"></i>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <div class="fw-semibold" style="color:#fff;"><?= e($p['title']) ?></div>
                <div style="font-size:11px;color:rgba(255,255,255,.35);max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                  <?= e($p['description'] ?: '-') ?>
                </div>
              </td>
              <td>
                <?php if (!empty($p['code'])): ?>
                  <span style="font-family:ui-monospace, SFMono-Regular, Menlo, monospace;background:rgba(31,111,255,.14);border:1px solid rgba(31,111,255,.28);padding:4px 8px;border-radius:999px;color:#9fc0ff;font-size:12px;font-weight:700;">
                    <?= e($p['code']) ?>
                  </span>
                <?php else: ?>
                  <span style="color:rgba(255,255,255,.30);">-</span>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;white-space:nowrap;">
                <?= e(promoDiscountLabel($p)) ?>
              </td>
              <td style="font-size:12px;white-space:nowrap;">
                <?= e(rupiahAdmin($p['min_purchase'] ?? 0)) ?>
              </td>
              <td style="font-size:12px;white-space:nowrap;">
                <?= e($quotaText) ?>
              </td>
              <td style="font-size:12px;white-space:nowrap;">
                <?= e(promoPeriodLabel($p)) ?>
              </td>
              <td><?= promoStatusBadge($p) ?></td>
              <td>
                <div class="d-flex gap-2">
                  <a href="promo-form.php?id=<?= (int) $p['id_promotion'] ?>" class="adm-btn adm-btn-outline adm-btn-sm" title="Edit">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <a href="promotions.php?delete=<?= (int) $p['id_promotion'] ?>"
                     class="adm-btn adm-btn-danger adm-btn-sm"
                     title="Nonaktifkan"
                     onclick="return confirm('Nonaktifkan promosi ini? Promosi tidak akan bisa digunakan user, tetapi data histori tetap aman.')">
                    <i class="bi bi-slash-circle"></i>
                  </a>
                </div>
              </td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
              <td colspan="10" class="text-center" style="padding:32px;color:rgba(255,255,255,.35);">
                Belum ada promosi.
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
