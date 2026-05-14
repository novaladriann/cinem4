<?php
session_start();
require 'auth.php';
requirePermission('reports.view', 'Laporan transaksi penuh hanya dapat diakses oleh Super Admin.');
require '../../app/config/koneksi.php';
@$conn->query("SET time_zone = '+07:00'");

$title     = "CINEM4 Admin — Laporan Transaksi";
$pageTitle = "Laporan Transaksi";

function rep_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rep_rupiah($value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function rep_short_rupiah($value): string
{
    $value = (float) $value;

    if ($value >= 1000000000) {
        return 'Rp ' . rtrim(rtrim(number_format($value / 1000000000, 1, ',', '.'), '0'), ',') . ' M';
    }

    if ($value >= 1000000) {
        return 'Rp ' . rtrim(rtrim(number_format($value / 1000000, 1, ',', '.'), '0'), ',') . ' jt';
    }

    if ($value >= 1000) {
        return 'Rp ' . rtrim(rtrim(number_format($value / 1000, 1, ',', '.'), '0'), ',') . ' rb';
    }

    return 'Rp ' . number_format($value, 0, ',', '.');
}

function rep_date($value): string
{
    if (!$value) {
        return '-';
    }
    $time = strtotime((string) $value);
    return $time ? date('d M Y', $time) : '-';
}

function rep_datetime($value): string
{
    if (!$value) {
        return '-';
    }
    $time = strtotime((string) $value);
    return $time ? date('d M Y H:i', $time) : '-';
}

function rep_badge_class(string $status): string
{
    return match ($status) {
        'paid' => 'adm-badge-green',
        'pending' => 'adm-badge-yellow',
        'expired', 'cancelled' => 'adm-badge-gray',
        'failed' => 'adm-badge-red',
        default => 'adm-badge-blue',
    };
}

function rep_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $rows = [];

    if ($types !== '' && $params) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function rep_one(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $rows = rep_rows($conn, $sql, $types, $params);
    return $rows[0] ?? [];
}

function rep_csv_cell($value): string
{
    return (string) $value;
}

$today = date('Y-m-d');
$defaultStart = date('Y-m-d', strtotime('-29 days'));

$startDate = trim((string) ($_GET['start'] ?? $defaultStart));
$endDate   = trim((string) ($_GET['end'] ?? $today));
$status    = trim((string) ($_GET['status'] ?? ''));
$movieId   = (int) ($_GET['movie'] ?? 0);
$cinemaId  = (int) ($_GET['cinema'] ?? 0);
$export    = trim((string) ($_GET['export'] ?? ''));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = $defaultStart;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = $today;
}
if (strtotime($startDate) > strtotime($endDate)) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$allowedStatuses = ['pending', 'paid', 'failed', 'expired', 'cancelled'];
if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$where = "WHERE DATE(COALESCE(b.paid_at, b.booked_at, b.created_at)) BETWEEN ? AND ?";
$params = [$startDate, $endDate];
$types = 'ss';

if ($status !== '') {
    $where .= " AND b.payment_status = ?";
    $params[] = $status;
    $types .= 's';
}
if ($movieId > 0) {
    $where .= " AND m.id_movie = ?";
    $params[] = $movieId;
    $types .= 'i';
}
if ($cinemaId > 0) {
    $where .= " AND c.id_cinema = ?";
    $params[] = $cinemaId;
    $types .= 'i';
}

$movieOptions = rep_rows($conn, "SELECT id_movie, title FROM movies ORDER BY title ASC");
$cinemaOptions = rep_rows($conn, "SELECT id_cinema, name, city FROM cinemas ORDER BY name ASC, city ASC");

$summary = rep_one($conn, "
    SELECT
      COUNT(*) AS total_bookings,
      COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN 1 ELSE 0 END),0) AS paid_bookings,
      COALESCE(SUM(CASE WHEN b.payment_status = 'pending' THEN 1 ELSE 0 END),0) AS pending_bookings,
      COALESCE(SUM(CASE WHEN b.payment_status IN ('failed','expired','cancelled') THEN 1 ELSE 0 END),0) AS closed_bookings,
      COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.total_seats ELSE 0 END),0) AS tickets_sold,
      COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.subtotal_amount ELSE 0 END),0) AS subtotal_paid,
      COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.discount_amount ELSE 0 END),0) AS discount_paid,
      COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.total_amount ELSE 0 END),0) AS revenue_paid
    FROM bookings b
    JOIN schedules s ON s.id_schedule = b.id_schedule
    JOIN movies m ON m.id_movie = s.id_movie
    JOIN cinemas c ON c.id_cinema = s.id_cinema
    $where
", $types, $params);

$totalBookings = (int) ($summary['total_bookings'] ?? 0);
$paidBookings  = (int) ($summary['paid_bookings'] ?? 0);
$conversionRate = $totalBookings > 0 ? round(($paidBookings / $totalBookings) * 100, 1) : 0;
$avgOrderValue = $paidBookings > 0 ? ((float) ($summary['revenue_paid'] ?? 0) / $paidBookings) : 0;

$detailRows = rep_rows($conn, "
    SELECT
      b.booking_code,
      b.payment_status,
      b.booking_status,
      b.total_seats,
      b.subtotal_amount,
      b.discount_amount,
      b.total_amount,
      b.promo_code,
      b.payment_method,
      b.payment_gateway,
      b.booked_at,
      b.paid_at,
      u.first_name,
      u.last_name,
      u.email,
      m.title AS movie_title,
      s.show_date,
      s.show_time,
      s.studio_name,
      c.name AS cinema_name,
      c.city AS cinema_city
    FROM bookings b
    JOIN users u ON u.id_user = b.id_user
    JOIN schedules s ON s.id_schedule = b.id_schedule
    JOIN movies m ON m.id_movie = s.id_movie
    JOIN cinemas c ON c.id_cinema = s.id_cinema
    $where
    ORDER BY COALESCE(b.paid_at, b.booked_at, b.created_at) DESC, b.id_booking DESC
    LIMIT 500
", $types, $params);

if ($export === 'csv') {
    $filename = 'laporan-transaksi-cinem4-' . $startDate . '-sd-' . $endDate . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'Kode Booking', 'Status Bayar', 'Status Booking', 'User', 'Email', 'Film', 'Cinema',
        'Studio', 'Tanggal Tayang', 'Jam Tayang', 'Jumlah Kursi', 'Subtotal', 'Diskon',
        'Total Bayar', 'Promo', 'Metode', 'Gateway', 'Booked At', 'Paid At'
    ]);

    foreach ($detailRows as $row) {
        fputcsv($out, [
            rep_csv_cell($row['booking_code']),
            rep_csv_cell($row['payment_status']),
            rep_csv_cell($row['booking_status']),
            trim((string) $row['first_name'] . ' ' . (string) $row['last_name']),
            rep_csv_cell($row['email']),
            rep_csv_cell($row['movie_title']),
            trim((string) $row['cinema_name'] . ' ' . (string) $row['cinema_city']),
            rep_csv_cell($row['studio_name']),
            rep_csv_cell($row['show_date']),
            rep_csv_cell($row['show_time']),
            rep_csv_cell($row['total_seats']),
            rep_csv_cell($row['subtotal_amount']),
            rep_csv_cell($row['discount_amount']),
            rep_csv_cell($row['total_amount']),
            rep_csv_cell($row['promo_code']),
            rep_csv_cell($row['payment_method']),
            rep_csv_cell($row['payment_gateway']),
            rep_csv_cell($row['booked_at']),
            rep_csv_cell($row['paid_at']),
        ]);
    }
    fclose($out);
    exit;
}

$salesTrend = rep_rows($conn, "
    SELECT
      DATE(COALESCE(b.paid_at, b.booked_at, b.created_at)) AS report_date,
      COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.total_amount ELSE 0 END),0) AS revenue,
      COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.total_seats ELSE 0 END),0) AS tickets,
      COUNT(*) AS total_bookings
    FROM bookings b
    JOIN schedules s ON s.id_schedule = b.id_schedule
    JOIN movies m ON m.id_movie = s.id_movie
    JOIN cinemas c ON c.id_cinema = s.id_cinema
    $where
    GROUP BY report_date
    ORDER BY report_date ASC
", $types, $params);

$maxRevenue = 0;
foreach ($salesTrend as $row) {
    $maxRevenue = max($maxRevenue, (float) $row['revenue']);
}

$trendChart = [
    'viewW' => 920,
    'viewH' => 320,
    'padL' => 82,
    'padR' => 28,
    'padT' => 32,
    'padB' => 58,
    'linePoints' => '',
    'areaPoints' => '',
    'points' => [],
    'xLabels' => [],
    'yLabels' => [],
    'scaleMax' => 0,
];

if ($salesTrend) {
    $count = count($salesTrend);
    $innerW = $trendChart['viewW'] - $trendChart['padL'] - $trendChart['padR'];
    $innerH = $trendChart['viewH'] - $trendChart['padT'] - $trendChart['padB'];
    $bottomY = $trendChart['viewH'] - $trendChart['padB'];
    $scaleMax = $maxRevenue > 0 ? ceil(($maxRevenue * 1.15) / 10000) * 10000 : 10000;
    $scaleMax = max(10000, $scaleMax);
    $trendChart['scaleMax'] = $scaleMax;

    $linePoints = [];
    $pointData = [];
    $xLabelData = [];

    foreach ($salesTrend as $i => $row) {
        $x = $count > 1
            ? $trendChart['padL'] + (($innerW / ($count - 1)) * $i)
            : $trendChart['padL'] + ($innerW / 2);
        $revenue = (float) ($row['revenue'] ?? 0);
        $y = $trendChart['padT'] + $innerH - (($revenue / $scaleMax) * $innerH);
        $x = round($x, 2);
        $y = round($y, 2);

        $linePoints[] = $x . ',' . $y;
        $pointData[] = [
            'x' => $x,
            'y' => $y,
            'date' => rep_date($row['report_date'] ?? ''),
            'label' => date('d M', strtotime((string) ($row['report_date'] ?? 'now'))),
            'revenue' => rep_rupiah($revenue),
            'tickets' => (int) ($row['tickets'] ?? 0),
            'bookings' => (int) ($row['total_bookings'] ?? 0),
        ];

        $showLabel = $count <= 8 || $i === 0 || $i === $count - 1 || $i % max(1, (int) ceil($count / 6)) === 0;
        if ($showLabel) {
            $xLabelData[] = [
                'x' => $x,
                'label' => date('d M', strtotime((string) ($row['report_date'] ?? 'now'))),
            ];
        }
    }

    $firstX = $pointData[0]['x'] ?? $trendChart['padL'];
    $lastX = $pointData[$count - 1]['x'] ?? ($trendChart['viewW'] - $trendChart['padR']);
    $trendChart['linePoints'] = implode(' ', $linePoints);
    $trendChart['areaPoints'] = $firstX . ',' . $bottomY . ' ' . implode(' ', $linePoints) . ' ' . $lastX . ',' . $bottomY;
    $trendChart['points'] = $pointData;
    $trendChart['xLabels'] = $xLabelData;

    for ($i = 0; $i <= 4; $i++) {
        $value = $scaleMax - (($scaleMax / 4) * $i);
        $trendChart['yLabels'][] = [
            'y' => round($trendChart['padT'] + (($innerH / 4) * $i), 2),
            'label' => rep_short_rupiah($value),
        ];
    }
}

$statusRows = rep_rows($conn, "
    SELECT b.payment_status, COUNT(*) AS total
    FROM bookings b
    JOIN schedules s ON s.id_schedule = b.id_schedule
    JOIN movies m ON m.id_movie = s.id_movie
    JOIN cinemas c ON c.id_cinema = s.id_cinema
    $where
    GROUP BY b.payment_status
", $types, $params);
$statusMap = ['pending' => 0, 'paid' => 0, 'failed' => 0, 'expired' => 0, 'cancelled' => 0];
foreach ($statusRows as $row) {
    $statusMap[(string) $row['payment_status']] = (int) $row['total'];
}

$topMovies = rep_rows($conn, "
    SELECT
      m.title,
      COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.total_seats ELSE 0 END),0) AS tickets,
      COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.total_amount ELSE 0 END),0) AS revenue
    FROM bookings b
    JOIN schedules s ON s.id_schedule = b.id_schedule
    JOIN movies m ON m.id_movie = s.id_movie
    JOIN cinemas c ON c.id_cinema = s.id_cinema
    $where
    GROUP BY m.id_movie, m.title
    HAVING tickets > 0 OR revenue > 0
    ORDER BY tickets DESC, revenue DESC
    LIMIT 5
", $types, $params);
$maxTopTickets = 0;
foreach ($topMovies as $movie) {
    $maxTopTickets = max($maxTopTickets, (int) $movie['tickets']);
}

$queryForExport = $_GET;
$queryForExport['export'] = 'csv';
$exportUrl = 'reports.php?' . http_build_query($queryForExport);

include 'partials/head.php';
include 'partials/sidebar.php';
?>

<div class="adm-main">
  <?php include 'partials/topbar.php'; ?>

  <div class="adm-content adm-report-page">

    <div class="adm-report-hero mb-4">
      <div>
        <div class="adm-eyebrow">Financial Report</div>
        <h4 class="fw-bold mb-1">Laporan Transaksi</h4>
        <div class="adm-muted-text">Pantau pendapatan, tiket terjual, status booking, dan performa film berdasarkan periode.</div>
      </div>
      <div class="d-flex flex-wrap gap-2 no-print">
        <a href="<?= rep_e($exportUrl) ?>" class="adm-btn adm-btn-primary"><i class="bi bi-filetype-csv"></i> Export CSV</a>
        <button type="button" class="adm-btn adm-btn-outline" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
      </div>
    </div>

    <div class="adm-card mb-4 no-print">
      <div class="adm-card-header">
        <div class="adm-card-title">Filter Laporan</div>
      </div>
      <div class="adm-card-body">
        <form method="get" class="adm-report-filter">
          <div>
            <label class="adm-filter-label">Tanggal Mulai</label>
            <input type="date" name="start" class="adm-form-control" value="<?= rep_e($startDate) ?>">
          </div>
          <div>
            <label class="adm-filter-label">Tanggal Selesai</label>
            <input type="date" name="end" class="adm-form-control" value="<?= rep_e($endDate) ?>">
          </div>
          <div>
            <label class="adm-filter-label">Status</label>
            <select name="status" class="adm-form-control">
              <option value="">Semua Status</option>
              <?php foreach ($allowedStatuses as $item): ?>
                <option value="<?= rep_e($item) ?>" <?= $status === $item ? 'selected' : '' ?>><?= rep_e(ucfirst($item)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="adm-filter-label">Film</label>
            <select name="movie" class="adm-form-control">
              <option value="0">Semua Film</option>
              <?php foreach ($movieOptions as $movie): ?>
                <option value="<?= (int) $movie['id_movie'] ?>" <?= $movieId === (int) $movie['id_movie'] ? 'selected' : '' ?>><?= rep_e($movie['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="adm-filter-label">Cinema</label>
            <select name="cinema" class="adm-form-control">
              <option value="0">Semua Cinema</option>
              <?php foreach ($cinemaOptions as $cinema): ?>
                <option value="<?= (int) $cinema['id_cinema'] ?>" <?= $cinemaId === (int) $cinema['id_cinema'] ? 'selected' : '' ?>>
                  <?= rep_e($cinema['name']) ?><?= !empty($cinema['city']) ? ' - ' . rep_e($cinema['city']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="adm-filter-actions">
            <button type="submit" class="adm-btn adm-btn-primary"><i class="bi bi-funnel"></i> Terapkan</button>
            <a href="reports.php" class="adm-btn adm-btn-outline">Reset</a>
          </div>
        </form>
      </div>
    </div>

    <div class="adm-print-title print-only">
      <h2>Laporan Transaksi CINEM4</h2>
      <p>Periode <?= rep_e(rep_date($startDate)) ?> sampai <?= rep_e(rep_date($endDate)) ?></p>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12 col-md-6 col-xl-3">
        <div class="adm-report-kpi primary">
          <span>Pendapatan Paid</span>
          <strong><?= rep_e(rep_rupiah($summary['revenue_paid'] ?? 0)) ?></strong>
          <small><?= (int) ($summary['tickets_sold'] ?? 0) ?> tiket terjual</small>
        </div>
      </div>
      <div class="col-12 col-md-6 col-xl-3">
        <div class="adm-report-kpi">
          <span>Booking Paid</span>
          <strong><?= (int) ($summary['paid_bookings'] ?? 0) ?></strong>
          <small>Conversion <?= rep_e($conversionRate) ?>%</small>
        </div>
      </div>
      <div class="col-12 col-md-6 col-xl-3">
        <div class="adm-report-kpi">
          <span>Pending Aktif</span>
          <strong><?= (int) ($summary['pending_bookings'] ?? 0) ?></strong>
          <small>Perlu dipantau</small>
        </div>
      </div>
      <div class="col-12 col-md-6 col-xl-3">
        <div class="adm-report-kpi">
          <span>Average Order</span>
          <strong><?= rep_e(rep_rupiah($avgOrderValue)) ?></strong>
          <small>Rata-rata booking paid</small>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12 col-xl-8">
        <div class="adm-card h-100">
          <div class="adm-card-header">
            <div>
              <div class="adm-card-title">Tren Pendapatan</div>
              <div class="adm-muted-text small">Pendapatan dari booking berstatus paid sesuai filter.</div>
            </div>
          </div>
          <div class="adm-card-body">
            <?php if ($salesTrend): ?>
              <div class="adm-trend-chart-wrap">
                <div class="adm-chart-summary-row">
                  <div>
                    <span>Total Pendapatan</span>
                    <strong><?= rep_e(rep_rupiah($summary['revenue_paid'] ?? 0)) ?></strong>
                  </div>
                  <div>
                    <span>Tiket Terjual</span>
                    <strong><?= (int) ($summary['tickets_sold'] ?? 0) ?></strong>
                  </div>
                  <div>
                    <span>Transaksi Paid</span>
                    <strong><?= (int) ($summary['paid_bookings'] ?? 0) ?></strong>
                  </div>
                </div>

                <div class="adm-trend-chart-scroll">
                  <svg class="adm-trend-svg" viewBox="0 0 <?= (int) $trendChart['viewW'] ?> <?= (int) $trendChart['viewH'] ?>" role="img" aria-label="Grafik tren pendapatan">
                    <defs>
                      <linearGradient id="reportAreaGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#60a5fa" stop-opacity="0.34" />
                        <stop offset="100%" stop-color="#60a5fa" stop-opacity="0.02" />
                      </linearGradient>
                    </defs>

                    <?php foreach ($trendChart['yLabels'] as $label): ?>
                      <line x1="<?= (float) $trendChart['padL'] ?>" y1="<?= rep_e($label['y']) ?>" x2="<?= (int) ($trendChart['viewW'] - $trendChart['padR']) ?>" y2="<?= rep_e($label['y']) ?>" class="adm-chart-grid-line" />
                      <text x="<?= (int) ($trendChart['padL'] - 12) ?>" y="<?= rep_e(((float) $label['y']) + 4) ?>" text-anchor="end" class="adm-chart-axis-text"><?= rep_e($label['label']) ?></text>
                    <?php endforeach; ?>

                    <?php foreach ($trendChart['xLabels'] as $label): ?>
                      <text x="<?= rep_e($label['x']) ?>" y="<?= (int) ($trendChart['viewH'] - 22) ?>" text-anchor="middle" class="adm-chart-axis-text"><?= rep_e($label['label']) ?></text>
                    <?php endforeach; ?>

                    <line x1="<?= (float) $trendChart['padL'] ?>" y1="<?= (int) ($trendChart['viewH'] - $trendChart['padB']) ?>" x2="<?= (int) ($trendChart['viewW'] - $trendChart['padR']) ?>" y2="<?= (int) ($trendChart['viewH'] - $trendChart['padB']) ?>" class="adm-chart-axis-line" />
                    <polygon points="<?= rep_e($trendChart['areaPoints']) ?>" fill="url(#reportAreaGradient)" />
                    <polyline points="<?= rep_e($trendChart['linePoints']) ?>" class="adm-chart-line" />

                    <?php foreach ($trendChart['points'] as $point): ?>
                      <g class="adm-chart-point-group">
                        <circle cx="<?= rep_e($point['x']) ?>" cy="<?= rep_e($point['y']) ?>" r="5" class="adm-chart-point" />
                        <title><?= rep_e($point['date'] . ' • ' . $point['revenue'] . ' • ' . $point['tickets'] . ' tiket') ?></title>
                      </g>
                    <?php endforeach; ?>
                  </svg>
                </div>

                <div class="adm-chart-note">
                  <i class="bi bi-info-circle"></i>
                  Grafik menampilkan pendapatan dari booking berstatus paid. Arahkan kursor ke titik grafik untuk melihat detail harian.
                </div>
              </div>
            <?php else: ?>
              <div class="adm-empty-state">Belum ada data pendapatan pada periode ini.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-12 col-xl-4">
        <div class="adm-card h-100">
          <div class="adm-card-header">
            <div class="adm-card-title">Status Booking</div>
          </div>
          <div class="adm-card-body">
            <div class="adm-status-list">
              <?php $maxStatus = max(1, ...array_values($statusMap)); ?>
              <?php foreach ($statusMap as $name => $total):
                $width = max(4, ($total / $maxStatus) * 100);
              ?>
                <div class="adm-status-row">
                  <div class="d-flex justify-content-between gap-2 mb-1">
                    <span><?= rep_e(ucfirst($name)) ?></span>
                    <strong><?= (int) $total ?></strong>
                  </div>
                  <div class="adm-status-track"><span style="width:<?= rep_e($width) ?>%"></span></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12 col-xl-5">
        <div class="adm-card h-100">
          <div class="adm-card-header">
            <div class="adm-card-title">Top Film Terlaris</div>
          </div>
          <div class="adm-card-body">
            <?php if ($topMovies): ?>
              <div class="adm-top-movie-list">
                <?php foreach ($topMovies as $movie):
                  $width = $maxTopTickets > 0 ? max(6, ((int) $movie['tickets'] / $maxTopTickets) * 100) : 6;
                ?>
                  <div class="adm-top-movie-item">
                    <div class="d-flex justify-content-between gap-3 mb-2">
                      <strong><?= rep_e($movie['title']) ?></strong>
                      <span><?= (int) $movie['tickets'] ?> tiket</span>
                    </div>
                    <div class="adm-status-track"><span style="width:<?= rep_e($width) ?>%"></span></div>
                    <small><?= rep_e(rep_rupiah($movie['revenue'])) ?></small>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="adm-empty-state">Belum ada film dengan transaksi paid pada filter ini.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-12 col-xl-7">
        <div class="adm-card h-100">
          <div class="adm-card-header">
            <div class="adm-card-title">Ringkasan Finansial</div>
          </div>
          <div class="adm-card-body">
            <div class="adm-finance-grid">
              <div><span>Total Booking</span><strong><?= (int) ($summary['total_bookings'] ?? 0) ?></strong></div>
              <div><span>Booking Closed</span><strong><?= (int) ($summary['closed_bookings'] ?? 0) ?></strong></div>
              <div><span>Subtotal Paid</span><strong><?= rep_e(rep_rupiah($summary['subtotal_paid'] ?? 0)) ?></strong></div>
              <div><span>Total Diskon</span><strong><?= rep_e(rep_rupiah($summary['discount_paid'] ?? 0)) ?></strong></div>
              <div class="span-2"><span>Net Revenue Paid</span><strong><?= rep_e(rep_rupiah($summary['revenue_paid'] ?? 0)) ?></strong></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="adm-card">
      <div class="adm-card-header">
        <div>
          <div class="adm-card-title">Detail Transaksi</div>
          <div class="adm-muted-text small">Maksimal 500 transaksi terbaru sesuai filter. Gunakan export CSV untuk arsip laporan.</div>
        </div>
      </div>
      <div style="overflow-x:auto;">
        <table class="adm-table" data-dt='{"searching":true,"pageLength":10,"buttons":[] }'>
          <thead>
            <tr>
              <th>Kode</th>
              <th>User</th>
              <th>Film</th>
              <th>Cinema</th>
              <th>Jadwal</th>
              <th>Tiket</th>
              <th>Total</th>
              <th>Status</th>
              <th>Paid At</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($detailRows as $row): ?>
              <tr>
                <td style="font-family:monospace;font-size:12px;color:#fff;"><?= rep_e($row['booking_code']) ?></td>
                <td>
                  <div style="color:#fff;"><?= rep_e(trim((string) $row['first_name'] . ' ' . (string) $row['last_name'])) ?></div>
                  <div style="font-size:11px;color:rgba(255,255,255,.35);"><?= rep_e($row['email']) ?></div>
                </td>
                <td><?= rep_e($row['movie_title']) ?></td>
                <td><?= rep_e($row['cinema_name']) ?><?= !empty($row['cinema_city']) ? '<br><span style="font-size:11px;color:rgba(255,255,255,.4);">' . rep_e($row['cinema_city']) . '</span>' : '' ?></td>
                <td><?= rep_e(rep_date($row['show_date'])) ?><br><span style="font-size:11px;color:rgba(255,255,255,.45);"><?= rep_e(date('H:i', strtotime($row['show_time']))) ?> • <?= rep_e($row['studio_name']) ?></span></td>
                <td><?= (int) $row['total_seats'] ?></td>
                <td><?= rep_e(rep_rupiah($row['total_amount'])) ?></td>
                <td><span class="adm-badge <?= rep_e(rep_badge_class((string) $row['payment_status'])) ?>"><?= rep_e(ucfirst((string) $row['payment_status'])) ?></span></td>
                <td><?= rep_e(rep_datetime($row['paid_at'] ?: $row['booked_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$detailRows): ?>
              <tr><td colspan="9" class="text-center" style="padding:32px;color:rgba(255,255,255,.35);">Tidak ada transaksi sesuai filter.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<style>
.adm-report-page .adm-eyebrow {
  font-size: 11px;
  letter-spacing: .16em;
  color: #60a5fa;
  text-transform: uppercase;
  font-weight: 800;
  margin-bottom: 6px;
}
.adm-muted-text { color: rgba(255,255,255,.48); }
.adm-report-hero {
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:16px;
  padding:22px;
  border-radius:20px;
  border:1px solid rgba(255,255,255,.10);
  background:radial-gradient(700px 260px at 0% 0%, rgba(31,111,255,.18), transparent 65%), rgba(255,255,255,.045);
}
.adm-report-filter {
  display:grid;
  grid-template-columns: repeat(6, minmax(0,1fr));
  gap:12px;
  align-items:end;
}
.adm-filter-label {
  display:block;
  color:rgba(255,255,255,.45);
  font-size:12px;
  margin-bottom:6px;
}
.adm-filter-actions {
  display:flex;
  gap:8px;
  flex-wrap:wrap;
}
.adm-report-kpi {
  min-height:130px;
  padding:20px;
  border-radius:18px;
  border:1px solid rgba(255,255,255,.10);
  background:rgba(255,255,255,.045);
}
.adm-report-kpi.primary {
  background:linear-gradient(135deg, rgba(31,111,255,.22), rgba(255,255,255,.045));
  border-color:rgba(96,165,250,.22);
}
.adm-report-kpi span {
  color:rgba(255,255,255,.50);
  font-size:13px;
}
.adm-report-kpi strong {
  display:block;
  margin:10px 0 6px;
  color:#fff;
  font-size:clamp(1.35rem, 2.4vw, 1.95rem);
  line-height:1.1;
}
.adm-report-kpi small {
  color:rgba(255,255,255,.42);
}
.adm-trend-chart-wrap {
  display:grid;
  gap:16px;
}
.adm-chart-summary-row {
  display:grid;
  grid-template-columns:repeat(3, minmax(0,1fr));
  gap:12px;
}
.adm-chart-summary-row > div {
  padding:14px 16px;
  border-radius:16px;
  border:1px solid rgba(255,255,255,.08);
  background:rgba(255,255,255,.035);
}
.adm-chart-summary-row span {
  display:block;
  color:rgba(255,255,255,.46);
  font-size:12px;
  margin-bottom:6px;
}
.adm-chart-summary-row strong {
  color:#fff;
  font-size:1.08rem;
}
.adm-trend-chart-scroll {
  overflow-x:auto;
  padding-bottom:4px;
}
.adm-trend-svg {
  width:100%;
  min-width:720px;
  min-height:290px;
  display:block;
}
.adm-chart-grid-line {
  stroke:rgba(255,255,255,.08);
  stroke-width:1;
}
.adm-chart-axis-line {
  stroke:rgba(255,255,255,.14);
  stroke-width:1.2;
}
.adm-chart-axis-text {
  fill:rgba(255,255,255,.48);
  font-size:12px;
  font-family:inherit;
}
.adm-chart-line {
  fill:none;
  stroke:#60a5fa;
  stroke-width:4;
  stroke-linecap:round;
  stroke-linejoin:round;
  filter:drop-shadow(0 8px 18px rgba(31,111,255,.28));
}
.adm-chart-point {
  fill:#0f172a;
  stroke:#93c5fd;
  stroke-width:3;
}
.adm-chart-point-group:hover .adm-chart-point {
  fill:#60a5fa;
  stroke:#bfdbfe;
}
.adm-chart-note {
  display:flex;
  align-items:center;
  gap:8px;
  color:rgba(255,255,255,.44);
  font-size:12px;
}
.adm-status-list,
.adm-top-movie-list {
  display:grid;
  gap:16px;
}
.adm-status-row span,
.adm-top-movie-item span,
.adm-top-movie-item small {
  color:rgba(255,255,255,.50);
}
.adm-status-track {
  height:9px;
  border-radius:999px;
  background:rgba(255,255,255,.065);
  overflow:hidden;
}
.adm-status-track span {
  display:block;
  height:100%;
  border-radius:999px;
  background:linear-gradient(90deg, #1f6fff, #38bdf8);
}
.adm-finance-grid {
  display:grid;
  grid-template-columns: repeat(2, minmax(0,1fr));
  gap:14px;
}
.adm-finance-grid > div {
  padding:16px;
  border-radius:16px;
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.08);
}
.adm-finance-grid .span-2 { grid-column:span 2; }
.adm-finance-grid span {
  display:block;
  color:rgba(255,255,255,.48);
  font-size:12px;
  margin-bottom:8px;
}
.adm-finance-grid strong {
  color:#fff;
  font-size:1.2rem;
}
.adm-empty-state {
  padding:38px 20px;
  text-align:center;
  color:rgba(255,255,255,.40);
}
.print-only { display:none; }
@media (max-width: 1199.98px) {
  .adm-report-filter { grid-template-columns: repeat(3, minmax(0,1fr)); }
}
@media (max-width: 767.98px) {
  .adm-report-hero { align-items:flex-start; flex-direction:column; }
  .adm-chart-summary-row { grid-template-columns: 1fr; }
  .adm-trend-svg { min-width:680px; }
  .adm-report-filter { grid-template-columns: 1fr; }
  .adm-filter-actions .adm-btn { flex:1; justify-content:center; }
  .adm-finance-grid { grid-template-columns: 1fr; }
  .adm-finance-grid .span-2 { grid-column:span 1; }
}
@media print {
  body { background:#fff !important; color:#111827 !important; }
  .adm-sidebar, .adm-topbar, .no-print, .dt-search, .dt-info, .dt-paging { display:none !important; }
  .adm-main { margin-left:0 !important; }
  .adm-content { padding:0 !important; }
  .print-only { display:block !important; margin-bottom:18px; }
  .adm-report-hero, .adm-card, .adm-report-kpi, .adm-finance-grid > div {
    background:#fff !important;
    border:1px solid #d1d5db !important;
    box-shadow:none !important;
    color:#111827 !important;
  }
  .adm-report-kpi strong, .adm-card-title, .adm-finance-grid strong, .adm-table td { color:#111827 !important; }
  .adm-muted-text, .adm-report-kpi span, .adm-report-kpi small, .adm-filter-label, .adm-chart-axis-text, .adm-chart-note { color:#4b5563 !important; fill:#4b5563 !important; }
  .adm-table th, .adm-table td { color:#111827 !important; border-color:#e5e7eb !important; }
  .adm-chart-summary-row > div { background:#fff !important; border-color:#d1d5db !important; }
  .adm-chart-summary-row strong { color:#111827 !important; }
}
</style>

<?php include 'partials/footer.php'; ?>
