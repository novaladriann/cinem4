<?php
session_start();
require 'auth.php';
require '../../app/config/koneksi.php';

$id        = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit    = $id > 0;
$title     = "CINEM4 Admin — " . ($isEdit ? "Edit Promosi" : "Tambah Promosi");
$pageTitle = $isEdit ? "Edit Promosi" : "Tambah Promosi";

function e($value): string {
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function makeSlugPromo(string $str): string {
  $str = strtolower(trim($str));
  $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
  $str = preg_replace('/[\s-]+/', '-', $str);
  return trim($str, '-') ?: 'promo-' . time();
}

function normalizePromoCode(string $code): string {
  $code = strtoupper(trim($code));
  $code = preg_replace('/\s+/', '', $code);
  return $code ?? '';
}

function parseDecimalInput($value): ?float {
  $value = trim((string) $value);
  if ($value === '') return null;
  $value = str_replace(',', '.', $value);
  return is_numeric($value) ? (float) $value : null;
}

function parseIntegerInput($value): ?int {
  $value = trim((string) $value);
  if ($value === '') return null;
  return ctype_digit($value) ? (int) $value : null;
}

function formatNumberInput($value): string {
  if ($value === null || $value === '') return '';
  return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
}

$promo = [];
if ($isEdit) {
  $stmt = $conn->prepare("SELECT * FROM promotions WHERE id_promotion = ? LIMIT 1");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $promo = $stmt->get_result()->fetch_assoc() ?? [];
  $stmt->close();

  if (!$promo) {
    header("Location: promotions.php");
    exit;
  }
}

$errors = [];
$old    = $promo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $old = $_POST;

  $titleVal      = trim($_POST['title'] ?? '');
  $slug          = makeSlugPromo($titleVal);
  $code          = normalizePromoCode($_POST['code'] ?? '');
  $description   = trim($_POST['description'] ?? '');
  $startDate     = trim($_POST['start_date'] ?? '') ?: null;
  $endDate       = trim($_POST['end_date'] ?? '') ?: null;
  $terms         = trim($_POST['terms'] ?? '');
  $discountType  = trim($_POST['discount_type'] ?? '');
  $discountValue = parseDecimalInput($_POST['discount_value'] ?? '');
  $maxDiscount   = parseDecimalInput($_POST['max_discount'] ?? '');
  $minPurchase   = parseDecimalInput($_POST['min_purchase'] ?? '0');
  $quota         = parseIntegerInput($_POST['quota'] ?? '');
  $isActive      = isset($_POST['is_active']) ? 1 : 0;
  $image         = trim($_POST['image'] ?? ($promo['image'] ?? ''));

  $old['code']           = $code;
  $old['discount_value'] = $_POST['discount_value'] ?? '';
  $old['max_discount']   = $_POST['max_discount'] ?? '';
  $old['min_purchase']   = $_POST['min_purchase'] ?? '';
  $old['quota']          = $_POST['quota'] ?? '';
  $old['image']          = $image;

  if ($titleVal === '') {
    $errors[] = 'Judul promosi wajib diisi.';
  }

  if ($code === '') {
    $errors[] = 'Kode promo wajib diisi.';
  } elseif (!preg_match('/^[A-Z0-9_-]{3,50}$/', $code)) {
    $errors[] = 'Kode promo hanya boleh berisi huruf besar, angka, underscore, atau strip, minimal 3 karakter.';
  }

  if (!in_array($discountType, ['percent', 'fixed'], true)) {
    $errors[] = 'Tipe diskon wajib dipilih.';
  }

  if ($discountValue === null || $discountValue <= 0) {
    $errors[] = 'Nilai diskon wajib diisi dan harus lebih dari 0.';
  } elseif ($discountType === 'percent' && $discountValue > 100) {
    $errors[] = 'Nilai diskon persen tidak boleh lebih dari 100%.';
  }

  if ($discountType === 'fixed') {
    $maxDiscount = null;
  } elseif ($maxDiscount !== null && $maxDiscount < 0) {
    $errors[] = 'Maksimal diskon tidak boleh kurang dari 0.';
  }

  if ($minPurchase === null || $minPurchase < 0) {
    $errors[] = 'Minimal pembelian wajib diisi dan tidak boleh kurang dari 0.';
  }

  if (trim((string) ($_POST['quota'] ?? '')) !== '' && $quota === null) {
    $errors[] = 'Kuota harus berupa angka bulat.';
  } elseif ($quota !== null && $quota < 0) {
    $errors[] = 'Kuota tidak boleh kurang dari 0.';
  }

  if ($startDate && $endDate && strtotime($startDate) > strtotime($endDate)) {
    $errors[] = 'Tanggal mulai tidak boleh lebih besar dari tanggal selesai.';
  }

  if ($isEdit && isset($promo['used_count']) && $quota !== null && $quota < (int) $promo['used_count']) {
    $errors[] = 'Kuota tidak boleh lebih kecil dari jumlah pemakaian saat ini (' . (int) $promo['used_count'] . ').';
  }

  /* Cek slug duplikat */
  if (empty($errors)) {
    $chk = $conn->prepare("SELECT id_promotion FROM promotions WHERE slug = ? AND id_promotion != ? LIMIT 1");
    $chk->bind_param("si", $slug, $id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
      $slug .= '-' . time();
    }
    $chk->close();
  }

  /* Cek kode promo duplikat */
  if (empty($errors)) {
    $chk = $conn->prepare("SELECT id_promotion FROM promotions WHERE code = ? AND id_promotion != ? LIMIT 1");
    $chk->bind_param("si", $code, $id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
      $errors[] = 'Kode promo sudah digunakan. Gunakan kode lain.';
    }
    $chk->close();
  }

  /* Upload gambar */
  if (!empty($_FILES['image_file']['name'])) {
    $fileError = $_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $fileSize  = (int) ($_FILES['image_file']['size'] ?? 0);
    $ext       = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
    $allowed   = ['jpg', 'jpeg', 'png', 'webp'];

    if ($fileError !== UPLOAD_ERR_OK) {
      $errors[] = 'Gagal upload gambar. Silakan coba lagi.';
    } elseif (!in_array($ext, $allowed, true)) {
      $errors[] = 'Format gambar tidak didukung. Gunakan jpg, jpeg, png, atau webp.';
    } elseif ($fileSize > 2 * 1024 * 1024) {
      $errors[] = 'Ukuran gambar maksimal 2MB.';
    } else {
      $uploadDir = '../assets/img/';
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
      }

      $filename = 'promo-' . $slug . '-' . time() . '.' . $ext;
      if (move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . $filename)) {
        $image = 'assets/img/' . $filename;
        $old['image'] = $image;
      } else {
        $errors[] = 'Gagal menyimpan gambar ke folder assets/img.';
      }
    }
  }

  if (empty($errors)) {
    if ($isEdit) {
      $stmt = $conn->prepare("
        UPDATE promotions SET
          title = ?,
          slug = ?,
          code = ?,
          description = ?,
          image = ?,
          start_date = ?,
          end_date = ?,
          terms = ?,
          discount_type = ?,
          discount_value = ?,
          max_discount = ?,
          min_purchase = ?,
          quota = ?,
          is_active = ?,
          updated_at = NOW()
        WHERE id_promotion = ?
      ");
      $stmt->bind_param(
        "sssssssssdddiii",
        $titleVal,
        $slug,
        $code,
        $description,
        $image,
        $startDate,
        $endDate,
        $terms,
        $discountType,
        $discountValue,
        $maxDiscount,
        $minPurchase,
        $quota,
        $isActive,
        $id
      );
    } else {
      $stmt = $conn->prepare("
        INSERT INTO promotions
          (title, slug, code, description, image, start_date, end_date, terms,
           discount_type, discount_value, max_discount, min_purchase, quota, used_count, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
      ");
      $stmt->bind_param(
        "sssssssssdddii",
        $titleVal,
        $slug,
        $code,
        $description,
        $image,
        $startDate,
        $endDate,
        $terms,
        $discountType,
        $discountValue,
        $maxDiscount,
        $minPurchase,
        $quota,
        $isActive
      );
    }

    if ($stmt->execute()) {
      header("Location: promotions.php?msg=saved");
      exit;
    }

    $errors[] = 'Gagal menyimpan promosi: ' . $stmt->error;
    $stmt->close();
  }
}

include 'partials/head.php';
include 'partials/sidebar.php';
?>

<div class="adm-main">
  <?php include 'partials/topbar.php'; ?>
  <div class="adm-content">

    <div class="mb-3" style="font-size:13px;color:rgba(255,255,255,.40);">
      <a href="promotions.php" style="color:rgba(255,255,255,.40);text-decoration:none;">Promosi</a>
      <span class="mx-2">/</span>
      <span style="color:rgba(255,255,255,.75);"><?= $isEdit ? 'Edit' : 'Tambah' ?></span>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="adm-alert adm-alert-danger mb-3">
        <i class="bi bi-exclamation-circle-fill"></i>
        <div><?= implode('<br>', array_map('e', $errors)) ?></div>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <div class="row g-3">

        <div class="col-12 col-lg-8">
          <div class="adm-card mb-3">
            <div class="adm-card-header">
              <div class="adm-card-title">Informasi Promosi</div>
            </div>
            <div class="adm-card-body">
              <div class="row g-3">

                <div class="col-12">
                  <label class="adm-form-label">Judul <span style="color:#ff8a95;">*</span></label>
                  <input type="text" name="title" class="adm-form-control" required
                    value="<?= e($old['title'] ?? '') ?>"
                    placeholder="Contoh: Weekend Vibes: Nonton Berdua Lebih Hemat">
                </div>

                <div class="col-12 col-md-6">
                  <label class="adm-form-label">Kode Promo <span style="color:#ff8a95;">*</span></label>
                  <input type="text" name="code" class="adm-form-control text-uppercase" required maxlength="50"
                    value="<?= e($old['code'] ?? '') ?>"
                    placeholder="Contoh: WEEKEND">
                  <div style="font-size:11px;color:rgba(255,255,255,.35);margin-top:4px;">
                    Digunakan user di halaman pembayaran. Contoh: BOGO, CASHBACK50.
                  </div>
                </div>

                <div class="col-12 col-md-6">
                  <label class="adm-form-label">Status</label>
                  <label class="d-flex align-items-center gap-2 adm-form-control" style="cursor:pointer;min-height:42px;">
                    <input type="checkbox" name="is_active" value="1"
                      <?= !empty($old['is_active']) || !$isEdit ? 'checked' : '' ?>
                      style="width:16px;height:16px;accent-color:var(--c4-primary);">
                    <span>Promosi aktif dan bisa digunakan</span>
                  </label>
                </div>

                <div class="col-12">
                  <label class="adm-form-label">Deskripsi</label>
                  <textarea name="description" class="adm-form-control" rows="3"
                    placeholder="Deskripsi singkat promo yang tampil di halaman promosi."><?= e($old['description'] ?? '') ?></textarea>
                </div>

                <div class="col-12 col-md-6">
                  <label class="adm-form-label">Tanggal Mulai</label>
                  <input type="date" name="start_date" class="adm-form-control"
                    value="<?= e($old['start_date'] ?? '') ?>">
                </div>

                <div class="col-12 col-md-6">
                  <label class="adm-form-label">Tanggal Selesai</label>
                  <input type="date" name="end_date" class="adm-form-control"
                    value="<?= e($old['end_date'] ?? '') ?>">
                </div>

                <div class="col-12">
                  <label class="adm-form-label">Syarat & Ketentuan</label>
                  <textarea name="terms" class="adm-form-control" rows="3"
                    placeholder="Contoh: Berlaku untuk transaksi minimal Rp90.000 dan kuota terbatas."><?= e($old['terms'] ?? '') ?></textarea>
                </div>

              </div>
            </div>
          </div>

          <div class="adm-card">
            <div class="adm-card-header">
              <div class="adm-card-title">Gambar Promosi</div>
            </div>
            <div class="adm-card-body">
              <?php if (!empty($old['image'])): ?>
                <div class="mb-3">
                  <img src="../<?= e($old['image']) ?>"
                    style="max-width:280px;width:100%;height:120px;border-radius:12px;object-fit:cover;border:1px solid rgba(255,255,255,.12);">
                </div>
              <?php endif; ?>
              <input type="file" name="image_file" class="adm-form-control" accept=".jpg,.jpeg,.png,.webp,image/*">
              <input type="hidden" name="image" value="<?= e($old['image'] ?? '') ?>">
              <div style="font-size:11px;color:rgba(255,255,255,.35);margin-top:6px;">
                Format: jpg, jpeg, png, webp. Maksimal 2MB. Ukuran disarankan 800×400px.
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-4">
          <div class="adm-card mb-3">
            <div class="adm-card-header">
              <div class="adm-card-title">Aturan Diskon</div>
            </div>
            <div class="adm-card-body">
              <div class="mb-3">
                <label class="adm-form-label">Tipe Diskon <span style="color:#ff8a95;">*</span></label>
                <select name="discount_type" id="discountType" class="adm-form-control" required>
                  <?php $selectedType = $old['discount_type'] ?? 'percent'; ?>
                  <option value="percent" <?= $selectedType === 'percent' ? 'selected' : '' ?>>Persen (%)</option>
                  <option value="fixed" <?= $selectedType === 'fixed' ? 'selected' : '' ?>>Nominal Tetap (Rp)</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="adm-form-label">Nilai Diskon <span style="color:#ff8a95;">*</span></label>
                <input type="number" name="discount_value" class="adm-form-control" min="0" step="0.01" required
                  value="<?= e(formatNumberInput($old['discount_value'] ?? '')) ?>"
                  placeholder="Contoh: 50 atau 15000">
                <div style="font-size:11px;color:rgba(255,255,255,.35);margin-top:4px;">
                  Jika tipe persen, isi 50 untuk diskon 50%. Jika nominal, isi 15000 untuk Rp15.000.
                </div>
              </div>

              <div class="mb-3" id="maxDiscountWrap">
                <label class="adm-form-label">Maksimal Diskon</label>
                <input type="number" name="max_discount" id="maxDiscount" class="adm-form-control" min="0" step="0.01"
                  value="<?= e(formatNumberInput($old['max_discount'] ?? '')) ?>"
                  placeholder="Contoh: 25000">
                <div style="font-size:11px;color:rgba(255,255,255,.35);margin-top:4px;">
                  Dipakai untuk diskon persen agar potongan tidak terlalu besar. Boleh kosong.
                </div>
              </div>

              <div class="mb-3">
                <label class="adm-form-label">Minimal Pembelian</label>
                <input type="number" name="min_purchase" class="adm-form-control" min="0" step="0.01"
                  value="<?= e(formatNumberInput($old['min_purchase'] ?? '0')) ?>"
                  placeholder="Contoh: 90000">
              </div>

              <div class="mb-3">
                <label class="adm-form-label">Kuota</label>
                <input type="number" name="quota" class="adm-form-control" min="0" step="1"
                  value="<?= e($old['quota'] ?? '') ?>"
                  placeholder="Kosongkan jika tanpa batas">
                <div style="font-size:11px;color:rgba(255,255,255,.35);margin-top:4px;">
                  Kosongkan jika promo tidak dibatasi kuota.
                </div>
              </div>

              <?php if ($isEdit): ?>
                <div style="font-size:12px;color:rgba(255,255,255,.55);background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:10px 12px;">
                  <div class="d-flex justify-content-between">
                    <span>Sudah digunakan</span>
                    <strong style="color:#fff;"><?= (int) ($promo['used_count'] ?? 0) ?> kali</strong>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="adm-card">
            <div class="adm-card-body">
              <button type="submit" class="adm-btn adm-btn-primary w-100 mb-2" style="justify-content:center;">
                <i class="bi bi-save"></i>
                <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Promosi' ?>
              </button>
              <a href="promotions.php" class="adm-btn adm-btn-outline w-100" style="justify-content:center;">Batal</a>
            </div>
          </div>
        </div>

      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const type = document.getElementById('discountType');
  const wrap = document.getElementById('maxDiscountWrap');
  const input = document.getElementById('maxDiscount');

  function toggleMaxDiscount() {
    const isFixed = type.value === 'fixed';
    wrap.style.display = isFixed ? 'none' : '';
    input.disabled = isFixed;
    if (isFixed) input.value = '';
  }

  if (type && wrap && input) {
    type.addEventListener('change', toggleMaxDiscount);
    toggleMaxDiscount();
  }
})();
</script>

<?php include 'partials/footer.php'; ?>
