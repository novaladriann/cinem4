<?php
session_start();
require 'auth.php';
require '../../app/config/koneksi.php';

$title     = "CINEM4 Admin — Dashboard";
$pageTitle = "Dashboard";

function adm_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function adm_count(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_row();
    return (int) ($row[0] ?? 0);
}

function adm_sum(mysqli $conn, string $sql): float
{
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_row();
    return (float) ($row[0] ?? 0);
}

function adm_rows(mysqli $conn, string $sql): array
{
    $rows = [];
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function adm_rupiah($value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function adm_badge_class(string $status): string
{
    return match ($status) {
        'paid', 'completed', 'now_showing', 'open' => 'adm-badge-green',
        'pending', 'coming_soon' => 'adm-badge-yellow',
        'expired', 'cancelled' => 'adm-badge-gray',
        'failed', 'closed', 'inactive' => 'adm-badge-red',
        default => 'adm-badge-blue',
    };
}

$admin = currentAdmin();
$isSuper = isSuperAdmin();

/* ── KPI utama ─────────────────────────────────────────────────────────── */
$totalMovies    = adm_count($conn, "SELECT COUNT(*) FROM movies WHERE is_active = 1");
$nowShowing     = adm_count($conn, "SELECT COUNT(*) FROM movies WHERE status = 'now_showing' AND is_active = 1");
$comingSoon     = adm_count($conn, "SELECT COUNT(*) FROM movies WHERE status = 'coming_soon' AND is_active = 1");
$totalSchedules = adm_count($conn, "SELECT COUNT(*) FROM schedules WHERE is_active = 1 AND status = 'open' AND show_date >= CURDATE()");
$todaySchedules = adm_count($conn, "SELECT COUNT(*) FROM schedules WHERE is_active = 1 AND status = 'open' AND show_date = CURDATE()");
$totalBookings  = adm_count($conn, "SELECT COUNT(*) FROM bookings");
$pendingBooking = adm_count($conn, "SELECT COUNT(*) FROM bookings WHERE payment_status = 'pending' AND (expires_at IS NULL OR expires_at > NOW())");
$paidBookings   = adm_count($conn, "SELECT COUNT(*) FROM bookings WHERE payment_status = 'paid'");
$totalUsers     = adm_count($conn, "SELECT COUNT(*) FROM users");
$totalPromos    = adm_count($conn, "SELECT COUNT(*) FROM promotions WHERE is_active = 1");
$ticketsSold    = adm_sum($conn, "SELECT COALESCE(SUM(total_seats),0) FROM bookings WHERE payment_status = 'paid'");
$totalRevenue   = adm_sum($conn, "SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE payment_status = 'paid'");
$monthRevenue   = adm_sum($conn, "
    SELECT COALESCE(SUM(total_amount),0)
    FROM bookings
    WHERE payment_status = 'paid'
      AND YEAR(COALESCE(paid_at, booked_at, created_at)) = YEAR(CURDATE())
      AND MONTH(COALESCE(paid_at, booked_at, created_at)) = MONTH(CURDATE())
");
$todayRevenue   = adm_sum($conn, "
    SELECT COALESCE(SUM(total_amount),0)
    FROM bookings
    WHERE payment_status = 'paid'
      AND DATE(COALESCE(paid_at, booked_at, created_at)) = CURDATE()
");

$conversionRate = $totalBookings > 0 ? round(($paidBookings / $totalBookings) * 100, 1) : 0;
$avgOrderValue  = $paidBookings > 0 ? $totalRevenue / $paidBookings : 0;

/* ── Data grafik penjualan 7 hari ─────────────────────────────────────── */
$salesMap = [];
for ($i = 6; $i >= 0; $i--) {
    $dateKey = date('Y-m-d', strtotime("-$i days"));
    $salesMap[$dateKey] = [
        'date' => date('d M', strtotime($dateKey)),
        'revenue' => 0,
        'tickets' => 0,
        'bookings' => 0,
    ];
}

$salesRows = adm_rows($conn, "
    SELECT DATE(COALESCE(paid_at, booked_at, created_at)) AS sale_date,
           COALESCE(SUM(total_amount),0) AS revenue,
           COALESCE(SUM(total_seats),0) AS tickets,
           COUNT(*) AS bookings
    FROM bookings
    WHERE payment_status = 'paid'
      AND DATE(COALESCE(paid_at, booked_at, created_at)) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
    GROUP BY sale_date
    ORDER BY sale_date ASC
");
foreach ($salesRows as $row) {
    $key = (string) $row['sale_date'];
    if (isset($salesMap[$key])) {
        $salesMap[$key]['revenue'] = (float) $row['revenue'];
        $salesMap[$key]['tickets'] = (int) $row['tickets'];
        $salesMap[$key]['bookings'] = (int) $row['bookings'];
    }
}
$salesChart = array_values($salesMap);

/* ── Status booking ───────────────────────────────────────────────────── */
$statusBase = [
    'pending' => 0,
    'paid' => 0,
    'expired' => 0,
    'cancelled' => 0,
    'failed' => 0,
];
$statusRows = adm_rows($conn, "SELECT payment_status, COUNT(*) AS total FROM bookings GROUP BY payment_status");
foreach ($statusRows as $row) {
    $statusBase[(string) $row['payment_status']] = (int) $row['total'];
}
$statusChart = [
    ['label' => 'Pending', 'value' => $statusBase['pending']],
    ['label' => 'Paid', 'value' => $statusBase['paid']],
    ['label' => 'Expired', 'value' => $statusBase['expired']],
    ['label' => 'Cancelled', 'value' => $statusBase['cancelled']],
    ['label' => 'Failed', 'value' => $statusBase['failed']],
];

/* ── Top film ─────────────────────────────────────────────────────────── */
$topMovies = adm_rows($conn, "
    SELECT m.title,
           COALESCE(SUM(b.total_seats),0) AS tickets,
           COALESCE(SUM(b.total_amount),0) AS revenue
    FROM bookings b
    JOIN schedules s ON s.id_schedule = b.id_schedule
    JOIN movies m ON m.id_movie = s.id_movie
    WHERE b.payment_status = 'paid'
    GROUP BY m.id_movie, m.title
    ORDER BY tickets DESC, revenue DESC
    LIMIT 5
");

/* ── Tabel ringkas ────────────────────────────────────────────────────── */
$recentBookings = adm_rows($conn, "
    SELECT b.booking_code, b.total_amount, b.payment_status, b.booked_at,
           u.first_name, u.last_name,
           m.title AS movie_title
    FROM bookings b
    JOIN users u ON u.id_user = b.id_user
    JOIN schedules s ON s.id_schedule = b.id_schedule
    JOIN movies m ON m.id_movie = s.id_movie
    ORDER BY b.id_booking DESC
    LIMIT 7
");

$todayScheduleRows = adm_rows($conn, "
    SELECT s.show_time, s.studio_name, s.price, m.title AS movie_title, c.name AS cinema_name
    FROM schedules s
    JOIN movies m ON m.id_movie = s.id_movie
    JOIN cinemas c ON c.id_cinema = s.id_cinema
    WHERE s.is_active = 1
      AND s.status = 'open'
      AND s.show_date = CURDATE()
    ORDER BY s.show_time ASC
    LIMIT 6
");

include 'partials/head.php';
include 'partials/sidebar.php';
?>

<div class="adm-main">
  <?php include 'partials/topbar.php'; ?>

  <div class="adm-content adm-dashboard-page">

    <!-- Header dashboard -->
    <div class="adm-dashboard-hero mb-4">
      <div>
        <div class="adm-eyebrow">Admin Overview</div>
        <h4 class="fw-bold mb-1">Halo, <?= adm_e($admin['name'] ?? 'Admin') ?>! 👋</h4>
        <div class="adm-muted-text">
          <?= date('l, d F Y') ?> — Pantau penjualan tiket, jadwal aktif, dan aktivitas booking CINEM4.
        </div>
      </div>
      <div class="adm-hero-actions">
        <a href="bookings.php" class="adm-btn adm-btn-primary"><i class="bi bi-receipt"></i> Kelola Booking</a>
        <a href="schedules.php" class="adm-btn adm-btn-outline"><i class="bi bi-calendar-plus"></i> Jadwal</a>
      </div>
    </div>

    <!-- KPI utama -->
    <div class="row g-3 mb-4">
      <div class="col-12 col-md-6 col-xl-3">
        <div class="adm-kpi-card primary">
          <div class="adm-kpi-top">
            <span>Pendapatan Bulan Ini</span>
            <i class="bi bi-cash-stack"></i>
          </div>
          <div class="adm-kpi-value"><?= adm_e(adm_rupiah($monthRevenue)) ?></div>
          <div class="adm-kpi-sub">Hari ini: <?= adm_e(adm_rupiah($todayRevenue)) ?></div>
        </div>
      </div>
      <div class="col-12 col-md-6 col-xl-3">
        <div class="adm-kpi-card">
          <div class="adm-kpi-top">
            <span>Tiket Terjual</span>
            <i class="bi bi-ticket-perforated"></i>
          </div>
          <div class="adm-kpi-value"><?= number_format((int) $ticketsSold, 0, ',', '.') ?></div>
          <div class="adm-kpi-sub"><?= $paidBookings ?> booking paid</div>
        </div>
      </div>
      <div class="col-12 col-md-6 col-xl-3">
        <div class="adm-kpi-card warning">
          <div class="adm-kpi-top">
            <span>Pending Payment</span>
            <i class="bi bi-hourglass-split"></i>
          </div>
          <div class="adm-kpi-value"><?= $pendingBooking ?></div>
          <div class="adm-kpi-sub">Booking aktif menunggu bayar</div>
        </div>
      </div>
      <div class="col-12 col-md-6 col-xl-3">
        <div class="adm-kpi-card success">
          <div class="adm-kpi-top">
            <span>Conversion Paid</span>
            <i class="bi bi-graph-up-arrow"></i>
          </div>
          <div class="adm-kpi-value"><?= $conversionRate ?>%</div>
          <div class="adm-kpi-sub">AOV: <?= adm_e(adm_rupiah($avgOrderValue)) ?></div>
        </div>
      </div>
    </div>

    <!-- Snapshot kecil -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="adm-mini-stat"><span>Total Film</span><strong><?= $totalMovies ?></strong><small><?= $nowShowing ?> now showing</small></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="adm-mini-stat"><span>Jadwal Aktif</span><strong><?= $totalSchedules ?></strong><small><?= $todaySchedules ?> jadwal hari ini</small></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="adm-mini-stat"><span>Users</span><strong><?= $totalUsers ?></strong><small>Total akun customer</small></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="adm-mini-stat"><span>Promo Aktif</span><strong><?= $totalPromos ?></strong><small><?= $comingSoon ?> film coming soon</small></div>
      </div>
    </div>

    <!-- Grafik -->
    <div class="row g-3 mb-4">
      <div class="col-12 col-xl-8">
        <div class="adm-card adm-chart-card">
          <div class="adm-card-header">
            <div>
              <div class="adm-card-title">Grafik Penjualan 7 Hari</div>
              <div class="adm-card-subtitle">Pendapatan dari booking berstatus paid.</div>
            </div>
            <span class="adm-badge adm-badge-blue">Revenue</span>
          </div>
          <div class="adm-card-body">
            <canvas id="salesChart" height="120"></canvas>
          </div>
        </div>
      </div>
      <div class="col-12 col-xl-4">
        <div class="adm-card adm-chart-card h-100">
          <div class="adm-card-header">
            <div>
              <div class="adm-card-title">Status Booking</div>
              <div class="adm-card-subtitle">Komposisi seluruh booking.</div>
            </div>
          </div>
          <div class="adm-card-body">
            <canvas id="statusChart" height="210"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12 col-xl-7">
        <div class="adm-card adm-chart-card">
          <div class="adm-card-header">
            <div>
              <div class="adm-card-title">Top Film Terlaris</div>
              <div class="adm-card-subtitle">Berdasarkan jumlah tiket paid.</div>
            </div>
            <a href="movies.php" class="adm-btn adm-btn-outline adm-btn-sm">Film</a>
          </div>
          <div class="adm-card-body">
            <?php if (!empty($topMovies)): ?>
              <canvas id="topMovieChart" height="170"></canvas>
            <?php else: ?>
              <div class="adm-empty-state">Belum ada data penjualan film.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-12 col-xl-5">
        <div class="adm-card h-100">
          <div class="adm-card-header">
            <div>
              <div class="adm-card-title">Operasional Hari Ini</div>
              <div class="adm-card-subtitle">Jadwal aktif tanggal <?= date('d M Y') ?>.</div>
            </div>
            <a href="schedules.php" class="adm-btn adm-btn-outline adm-btn-sm">Lihat Jadwal</a>
          </div>
          <div class="adm-list-group">
            <?php if (!empty($todayScheduleRows)): ?>
              <?php foreach ($todayScheduleRows as $schedule): ?>
                <div class="adm-list-item">
                  <div>
                    <strong><?= adm_e($schedule['movie_title']) ?></strong>
                    <span><?= adm_e($schedule['cinema_name']) ?> • <?= adm_e($schedule['studio_name']) ?></span>
                  </div>
                  <div class="text-end">
                    <strong><?= adm_e(date('H:i', strtotime($schedule['show_time']))) ?></strong>
                    <span><?= adm_e(adm_rupiah($schedule['price'])) ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="adm-empty-state">Tidak ada jadwal aktif hari ini.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabel dan role insight -->
    <div class="row g-3">
      <div class="col-12 col-xl-8">
        <div class="adm-card">
          <div class="adm-card-header">
            <div>
              <div class="adm-card-title">Booking Terbaru</div>
              <div class="adm-card-subtitle">Aktivitas pemesanan terbaru dari user.</div>
            </div>
            <a href="bookings.php" class="adm-btn adm-btn-outline adm-btn-sm">Lihat Semua</a>
          </div>
          <div style="overflow-x:auto;">
            <table class="adm-table" data-dt='{"paging":false,"searching":false,"info":false,"ordering":false,"buttons":[]}'>
              <thead>
                <tr>
                  <th>Kode</th>
                  <th>User</th>
                  <th>Film</th>
                  <th>Total</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($recentBookings)): ?>
                  <?php foreach ($recentBookings as $row): ?>
                    <?php $ps = (string) $row['payment_status']; ?>
                    <tr>
                      <td style="font-family:monospace; font-size:12px;"><?= adm_e($row['booking_code']) ?></td>
                      <td><?= adm_e(trim($row['first_name'] . ' ' . $row['last_name'])) ?></td>
                      <td><?= adm_e($row['movie_title']) ?></td>
                      <td><?= adm_e(adm_rupiah($row['total_amount'])) ?></td>
                      <td><span class="adm-badge <?= adm_badge_class($ps) ?>"><?= adm_e(ucfirst($ps)) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="5" class="text-center" style="color:rgba(255,255,255,.35); padding:24px;">Belum ada booking.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-4">
        <div class="adm-card h-100">
          <div class="adm-card-header">
            <div>
              <div class="adm-card-title">Hak Akses Anda</div>
              <div class="adm-card-subtitle">Role: <?= adm_e(ucfirst((string)($admin['role'] ?? 'admin'))) ?></div>
            </div>
          </div>
          <div class="adm-role-card">
            <?php if ($isSuper): ?>
              <div class="adm-role-icon super"><i class="bi bi-shield-check"></i></div>
              <h6>Super Admin</h6>
              <p>Dapat mengelola akun admin, melihat seluruh laporan, dan memiliki akses penuh ke pengaturan operasional.</p>
              <a href="admins.php" class="adm-btn adm-btn-primary adm-btn-sm"><i class="bi bi-shield-lock"></i> Kelola Admin</a>
            <?php else: ?>
              <div class="adm-role-icon"><i class="bi bi-person-check"></i></div>
              <h6>Admin Operasional</h6>
              <p>Fokus mengelola film, jadwal, bioskop, promo, user, dan booking. Pengaturan akun admin hanya untuk super admin.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /adm-content -->
</div><!-- /adm-main -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const c4SalesData = <?= json_encode($salesChart, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
const c4StatusData = <?= json_encode($statusChart, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
const c4TopMovieData = <?= json_encode($topMovies, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;

const rupiahCompact = (value) => {
  const number = Number(value || 0);
  if (number >= 1000000) return 'Rp ' + (number / 1000000).toFixed(number >= 10000000 ? 0 : 1).replace('.0', '') + ' jt';
  if (number >= 1000) return 'Rp ' + (number / 1000).toFixed(0) + ' rb';
  return 'Rp ' + number.toLocaleString('id-ID');
};

const chartGrid = 'rgba(255,255,255,.08)';
const chartText = 'rgba(255,255,255,.62)';

if (window.Chart) {
  Chart.defaults.color = chartText;
  Chart.defaults.borderColor = chartGrid;
  Chart.defaults.font.family = 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';

  const salesCtx = document.getElementById('salesChart');
  if (salesCtx) {
    new Chart(salesCtx, {
      type: 'line',
      data: {
        labels: c4SalesData.map(item => item.date),
        datasets: [{
          label: 'Pendapatan',
          data: c4SalesData.map(item => item.revenue),
          borderWidth: 3,
          tension: .38,
          fill: true,
          pointRadius: 4,
          pointHoverRadius: 6,
          borderColor: '#60a5fa',
          backgroundColor: 'rgba(31,111,255,.16)',
          pointBackgroundColor: '#60a5fa'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => rupiahCompact(ctx.parsed.y) } }
        },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, ticks: { callback: value => rupiahCompact(value) } }
        }
      }
    });
  }

  const statusCtx = document.getElementById('statusChart');
  if (statusCtx) {
    new Chart(statusCtx, {
      type: 'doughnut',
      data: {
        labels: c4StatusData.map(item => item.label),
        datasets: [{
          data: c4StatusData.map(item => item.value),
          borderWidth: 0,
          backgroundColor: ['#facc15', '#22c55e', '#94a3b8', '#64748b', '#ef4444']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '66%',
        plugins: {
          legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } }
        }
      }
    });
  }

  const movieCtx = document.getElementById('topMovieChart');
  if (movieCtx) {
    new Chart(movieCtx, {
      type: 'bar',
      data: {
        labels: c4TopMovieData.map(item => item.title),
        datasets: [{
          label: 'Tiket',
          data: c4TopMovieData.map(item => item.tickets),
          borderWidth: 0,
          borderRadius: 10,
          backgroundColor: 'rgba(96,165,250,.72)'
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { beginAtZero: true, ticks: { precision: 0 } },
          y: { grid: { display: false } }
        }
      }
    });
  }
}
</script>

<style>
.adm-dashboard-page {
  background:
    radial-gradient(900px 360px at 4% 0%, rgba(31,111,255,.10), transparent 62%),
    radial-gradient(720px 320px at 95% 12%, rgba(14,165,233,.08), transparent 58%);
}
.adm-dashboard-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  padding: 22px;
  border-radius: 22px;
  border: 1px solid rgba(255,255,255,.10);
  background: linear-gradient(135deg, rgba(31,111,255,.12), rgba(255,255,255,.035));
}
.adm-eyebrow {
  margin-bottom: 6px;
  color: #60a5fa;
  font-size: .74rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .16em;
}
.adm-muted-text,
.adm-card-subtitle {
  color: rgba(255,255,255,.48);
  font-size: 13px;
}
.adm-hero-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-end;
}
.adm-kpi-card,
.adm-mini-stat {
  height: 100%;
  border: 1px solid rgba(255,255,255,.10);
  border-radius: 18px;
  background: rgba(255,255,255,.055);
  padding: 18px;
}
.adm-kpi-card.primary { background: linear-gradient(135deg, rgba(31,111,255,.20), rgba(255,255,255,.04)); border-color: rgba(96,165,250,.24); }
.adm-kpi-card.warning { background: linear-gradient(135deg, rgba(250,204,21,.14), rgba(255,255,255,.04)); border-color: rgba(250,204,21,.22); }
.adm-kpi-card.success { background: linear-gradient(135deg, rgba(34,197,94,.14), rgba(255,255,255,.04)); border-color: rgba(74,222,128,.18); }
.adm-kpi-top {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  color: rgba(255,255,255,.54);
  font-size: 13px;
  font-weight: 700;
}
.adm-kpi-top i { color: #93c5fd; font-size: 20px; }
.adm-kpi-value {
  margin-top: 12px;
  color: #fff;
  font-size: clamp(1.55rem, 3vw, 2.05rem);
  line-height: 1.05;
  font-weight: 900;
  letter-spacing: -.03em;
}
.adm-kpi-sub {
  margin-top: 8px;
  color: rgba(255,255,255,.42);
  font-size: 12px;
}
.adm-mini-stat span,
.adm-mini-stat small {
  display: block;
  color: rgba(255,255,255,.45);
  font-size: 12px;
}
.adm-mini-stat strong {
  display: block;
  margin: 6px 0 2px;
  color: #fff;
  font-size: 1.6rem;
  line-height: 1;
  font-weight: 900;
}
.adm-chart-card canvas {
  min-height: 260px;
}
.adm-list-group {
  padding: 8px 16px 16px;
}
.adm-list-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 13px 0;
  border-bottom: 1px solid rgba(255,255,255,.07);
}
.adm-list-item:last-child { border-bottom: 0; }
.adm-list-item strong {
  display: block;
  color: #fff;
  font-size: 14px;
}
.adm-list-item span {
  display: block;
  margin-top: 3px;
  color: rgba(255,255,255,.45);
  font-size: 12px;
}
.adm-empty-state {
  padding: 26px;
  text-align: center;
  color: rgba(255,255,255,.42);
  font-size: 13px;
}
.adm-role-card {
  padding: 24px;
}
.adm-role-icon {
  width: 58px;
  height: 58px;
  display: grid;
  place-items: center;
  border-radius: 20px;
  background: rgba(31,111,255,.16);
  color: #93c5fd;
  font-size: 1.8rem;
  margin-bottom: 16px;
}
.adm-role-icon.super {
  background: rgba(34,197,94,.15);
  color: #86efac;
}
.adm-role-card h6 {
  font-weight: 800;
  margin-bottom: 8px;
}
.adm-role-card p {
  color: rgba(255,255,255,.55);
  font-size: 13px;
  line-height: 1.7;
}
@media (max-width: 991px) {
  .adm-dashboard-hero {
    flex-direction: column;
    align-items: flex-start;
  }
  .adm-hero-actions {
    justify-content: flex-start;
    width: 100%;
  }
  .adm-hero-actions .adm-btn {
    flex: 1 1 auto;
    justify-content: center;
  }
  .adm-chart-card canvas {
    min-height: 240px;
  }
}
@media (max-width: 575px) {
  .adm-dashboard-hero,
  .adm-kpi-card,
  .adm-mini-stat {
    border-radius: 16px;
  }
  .adm-hero-actions .adm-btn {
    width: 100%;
  }
}
</style>

<?php include 'partials/footer.php'; ?>
