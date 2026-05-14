<?php
$title = "CINEM4 - Promotions";
$active = "promotions";

require '../app/config/koneksi.php';
include '../app/views/partials/public/head.php';
include '../app/views/partials/public/navbar.php';

/**
 * Format tanggal promo
 */
function formatPromoValid($startDate, $endDate): string
{
    if (!empty($startDate) && !empty($endDate)) {
        return 'Valid until ' . date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate));
    }

    if (!empty($startDate)) {
        return 'Mulai ' . date('d/m/Y', strtotime($startDate));
    }

    if (!empty($endDate)) {
        return 'Valid until ' . date('d/m/Y', strtotime($endDate));
    }

    return 'Promo aktif';
}

$promos = [];

$result = $conn->query("
    SELECT *
    FROM promotions
    WHERE is_active = 1
    ORDER BY created_at DESC, id_promotion DESC
");

while ($row = $result->fetch_assoc()) {
    $promos[] = [
        'id'          => 'promo-' . ($row['id_promotion'] ?? 0),
        'title'       => $row['title'] ?? '',
        'description' => $row['description'] ?? '',
        'img'         => $row['image'] ?? '',
        'valid'       => formatPromoValid($row['start_date'] ?? null, $row['end_date'] ?? null),
        'cta_link'    => '#promo-' . ($row['id_promotion'] ?? 0),
    ];
}
?>

<div class="container py-5">
  <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
      <h1 class="display-4 fw-bold mb-1">Promotions</h1>
      <div class="text-secondary">Promo tiket & event pilihan CINEM4.</div>
    </div>
  </div>

  <?php if (count($promos) === 0): ?>
    <div class="alert alert-dark border-secondary">
      Belum ada promo yang aktif.
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <?php foreach ($promos as $p): ?>
      <div class="col-12 col-md-6 col-lg-4 reveal d1" id="<?= htmlspecialchars($p['id']) ?>">
        <a class="text-decoration-none" href="<?= htmlspecialchars($p['cta_link']) ?>">
          <div class="promo-card card-glass h-100">
            <div class="promo-thumb" style="background-image:url('<?= htmlspecialchars($p['img']) ?>')"></div>
            <div class="p-3">
              <div class="promo-title fw-bold text-light">
                <?= htmlspecialchars($p['title']) ?>
              </div>

              <?php if (!empty($p['description'])): ?>
                <div class="text-secondary small mt-2">
                  <?= htmlspecialchars($p['description']) ?>
                </div>
              <?php endif; ?>

              <div class="promo-valid text-secondary mt-2 small">
                <i class="bi bi-calendar2-week me-2"></i><?= htmlspecialchars($p['valid']) ?>
              </div>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include '../app/views/partials/public/footer.php'; ?>