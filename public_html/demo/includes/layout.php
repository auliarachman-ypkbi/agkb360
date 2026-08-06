<?php
// Navigasi perlu tahu apakah pengguna ini anggota unit penanganan,
// dan layout dimuat di semua halaman — termasuk yang tidak memuat
// modul feedback. require_once membuatnya aman dipanggil berulang.
require_once __DIR__ . '/feedback.php';
// Lencana pengajuan akun di menu admin butuh pubHitungPengajuanMenunggu().
require_once __DIR__ . '/publik.php';

function renderHead(string $title = '', string $extraCss = ''): void {
    $t    = $title ? h($title) . ' — ' : '';
    $base = APP_URL;
    // Cache-busting: paksa browser ambil CSS/JS baru setiap file berubah
    $appRoot = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
    $cssV = @filemtime($appRoot . '/assets/css/style.css') ?: time();
    echo "<!DOCTYPE html>
<html lang='id'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
<title>{$t}AGKB 360°</title>
<meta name='theme-color' content='#040136'>
<link rel='icon' type='image/svg+xml' href='{$base}/assets/img/brand/favicon.svg'>
<link rel='icon' type='image/png' sizes='32x32' href='{$base}/assets/img/brand/favicon-32.png'>
<link rel='apple-touch-icon' href='{$base}/assets/img/brand/favicon-180.png'>
<link rel='preconnect' href='https://fonts.googleapis.com'>
<link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
<link rel='stylesheet' href='https://fonts.googleapis.com/css2?family=Host+Grotesk:ital,wght@0,300..800;1,300..800&display=swap'>
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css'>
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css'>
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/dataTables.bootstrap5.min.css'>
<link rel='stylesheet' href='{$base}/assets/css/style.css?v={$cssV}'>
<style>
/* Pengaman: ukuran brand tetap benar walau style.css gagal/terlambat dimuat */
.agkb-brand{gap:.65rem}
.agkb-brand img{height:34px;width:auto;max-height:34px;display:block}
</style>
{$extraCss}
</head>
<body>";
}

function renderNav(): void {
    $role     = $_SESSION['user_role'] ?? '';
    $name     = h($_SESSION['user_name'] ?? '');
    $initials = h(avatarInitials($_SESSION['user_name'] ?? 'U'));
    $base     = APP_URL;

    $adminMenu = '';
    // Tester banner
    $testerBanner = $role === 'tester' ? "
<div class='agkb-banner agkb-banner-tester'>
  <span><i class='bi bi-bug-fill me-1'></i>MODE TESTER — Aktivitas tidak dihitung dalam evaluasi</span>
</div>" : '';

    // View As banner
    $viewAsBanner = isViewingAs() ? "
<div class='agkb-banner agkb-banner-viewas'>
  <span><i class='bi bi-eye-fill me-1'></i>MODE PREVIEW — Anda sedang melihat sebagai Guru</span>
  <a href='{$base}/admin/view_as.php?action=exit'>✕ Kembali ke Admin</a>
</div>" : '';

    // Superadmin extra menu
    $superAdminExtra = $role === 'superadmin' ? "
        <li><hr class='dropdown-divider'></li>
        <li><a class='dropdown-item text-warning fw-semibold' href='{$base}/admin/hard_reset.php'><i class='bi bi-radiation me-2'></i>Hard Reset</a></li>" : '';

    // Lencana jumlah pengajuan akun yang menunggu. Dibungkus try
    // supaya navigasi tidak ikut mati kalau migrasi 017 belum jalan.
    $lencanaDaftar = '';
    if (in_array($role, ['superadmin','admin'], true) && function_exists('pubHitungPengajuanMenunggu')) {
        try {
            $n = pubHitungPengajuanMenunggu();
            if ($n > 0) $lencanaDaftar = " <span class='badge rounded-pill bg-warning text-dark ms-1'>{$n}</span>";
        } catch (Throwable $e) { /* tabel belum ada — abaikan */ }
    }

    if (in_array($role, ['superadmin','admin','foundation'])) {
        $adminMenu = "
        <li class='nav-item dropdown'>
          <a class='nav-link dropdown-toggle' href='#' data-bs-toggle='dropdown'>
            <i class='bi bi-gear-fill me-1'></i>Admin CMS
          </a>
          <ul class='dropdown-menu dropdown-menu-dark' style='max-height:80vh;overflow-y:auto'>
            <li><a class='dropdown-item' href='{$base}/admin/'><i class='bi bi-speedometer2 me-2'></i>Admin Dashboard</a></li>

            <li><hr class='dropdown-divider'></li>
            <li><h6 class='dropdown-header'>Data Dasar</h6></li>
            <li><a class='dropdown-item' href='{$base}/admin/users.php'><i class='bi bi-people me-2'></i>Pengguna</a></li>
            <li><a class='dropdown-item' href='{$base}/admin/classes.php'><i class='bi bi-building me-2'></i>Kelas & Mapping Guru</a></li>

            <li><hr class='dropdown-divider'></li>
            <li><h6 class='dropdown-header'>Evaluasi 360°</h6></li>
            <li><a class='dropdown-item' href='{$base}/admin/periods.php'><i class='bi bi-calendar3 me-2'></i>Periode Evaluasi</a></li>
            <li><a class='dropdown-item' href='{$base}/admin/assignments.php'><i class='bi bi-send me-2'></i>Penugasan</a></li>
            <li><a class='dropdown-item' href='{$base}/admin/reports.php'><i class='bi bi-bar-chart me-2'></i>Laporan</a></li>
            <li><a class='dropdown-item' href='{$base}/admin/foundation.php'><i class='bi bi-diagram-2 me-2'></i>Domain / Standard / Trait</a></li>
            <li><a class='dropdown-item' href='{$base}/admin/matrix.php'><i class='bi bi-grid me-2'></i>Matriks Mapping</a></li>
            <li><a class='dropdown-item' href='{$base}/admin/questions_master.php'><i class='bi bi-clipboard-check me-2'></i>Master Pertanyaan</a></li>
            <li><a class='dropdown-item' href='{$base}/admin/questions_packages.php'><i class='bi bi-folder me-2'></i>Paket Pertanyaan</a></li>

            <li><hr class='dropdown-divider'></li>
            <li><h6 class='dropdown-header'>Feedback &amp; Penanganan</h6></li>
            <li><a class='dropdown-item' href='{$base}/admin/feedback.php'><i class='bi bi-inbox me-2'></i>Inbox Tiket</a></li>
            <li><a class='dropdown-item' href='{$base}/admin/feedback_dashboard.php'><i class='bi bi-graph-up me-2'></i>Dashboard Feedback</a></li>
            <li><a class='dropdown-item' href='{$base}/admin/feedback_units.php'><i class='bi bi-diagram-3 me-2'></i>Unit Penanganan</a></li>
            <li><a class='dropdown-item' href='{$base}/admin/feedback_categories.php'><i class='bi bi-tags me-2'></i>Kategori & Eskalasi</a></li>
            <li><a class='dropdown-item' href='{$base}/admin/pendaftaran.php'><i class='bi bi-person-plus me-2'></i>Pengajuan Akun{$lencanaDaftar}</a></li>

            <li><hr class='dropdown-divider'></li>
            <li><h6 class='dropdown-header'>Lainnya</h6></li>
            <li><a class='dropdown-item' href='{$base}/admin/blast_email.php'><i class='bi bi-send-fill me-2'></i>Blast Email</a></li>
            <li><a class='dropdown-item' href='{$base}/admin/settings.php'><i class='bi bi-sliders me-2'></i>Pengaturan</a></li>
            <li><a class='dropdown-item text-warning' href='{$base}/admin/view_as.php?action=activate'><i class='bi bi-eye me-2'></i>Preview sebagai Guru</a></li>
            {$superAdminExtra}
          </ul>
        </li>";
    }

    echo $viewAsBanner . $testerBanner . "
<nav class='navbar navbar-expand-lg navbar-dark ktb-navbar'>
  <div class='container-fluid'>
    <a class='navbar-brand d-flex align-items-center agkb-brand' href='{$base}/dashboard/'>
      <img src='{$base}/assets/img/brand/agkb-mark.svg' alt='' width='34' height='34'
           style='height:34px;width:auto;display:block'
           onerror='this.style.display=\"none\";this.nextElementSibling.style.display=\"flex\"'>
      <div class='ktb-logo-sm' style='display:none'>360</div>
      <div class='agkb-brand-text'>
        <div class='agkb-brand-name'>AGKB <span class='deg'>360°</span></div>
        <div class='agkb-brand-tag'>Platform Evaluasi Kinerja</div>
      </div>
    </a>
    <button class='navbar-toggler' type='button' data-bs-toggle='collapse' data-bs-target='#navMain'>
      <span class='navbar-toggler-icon'></span>
    </button>
    <div class='collapse navbar-collapse' id='navMain'>
      <ul class='navbar-nav me-auto'>
        <li class='nav-item'><a class='nav-link' href='{$base}/dashboard/'><i class='bi bi-house me-1'></i>Dashboard</a></li>
        " . (in_array($role, ['superadmin','admin','foundation','leader']) ? "
        <li class='nav-item'><a class='nav-link' href='{$base}/admin/reports.php'><i class='bi bi-bar-chart me-1'></i>Laporan</a></li>
        <li class='nav-item'><a class='nav-link' href='{$base}/admin/progress.php'><i class='bi bi-activity me-1'></i>Progress</a></li>
        " : '') . "
        " . ($role === 'tester' ? "
        <li class='nav-item'><a class='nav-link' href='{$base}/tester/'><i class='bi bi-eye me-1'></i>Preview Kuesioner</a></li>
        " : (in_array($role, ['foundation','leader','teacher','parent','student']) ? "
        <li class='nav-item'><a class='nav-link' href='{$base}/survey/'><i class='bi bi-clipboard-check me-1'></i>Kuesioner Saya</a></li>
        " : '')) . "
        " . (in_array($role, ['teacher','leader']) ? "
        <li class='nav-item'><a class='nav-link' href='{$base}/survey/my_report.php'><i class='bi bi-bar-chart-line me-1'></i>Laporan Kinerja</a></li>
        " : '') . "
        <li class='nav-item dropdown'>
          <a class='nav-link dropdown-toggle' href='#' data-bs-toggle='dropdown' style='color:var(--agkb-catalyst)'>
            <i class='bi bi-chat-heart me-1'></i>Feedback
          </a>
          <ul class='dropdown-menu dropdown-menu-dark'>
            <li><a class='dropdown-item' href='{$base}/feedback/'><i class='bi bi-plus-circle me-2'></i>Kirim Feedback</a></li>
            <li><a class='dropdown-item' href='{$base}/feedback/my.php'><i class='bi bi-list-ul me-2'></i>Laporan Saya</a></li>
          </ul>
        </li>
        " . (in_array($role, ['superadmin','admin','foundation','leader'])
             ? "<li class='nav-item'><a class='nav-link' href='{$base}/admin/feedback.php'><i class='bi bi-inbox me-1'></i>Inbox Tiket</a></li>"
             : (function_exists('fbIsHandler') && fbIsHandler()
                ? "<li class='nav-item'><a class='nav-link' href='{$base}/admin/feedback.php?status=antrean'><i class='bi bi-inbox me-1'></i>Antrean Unit Saya</a></li>"
                : '')) . "
        {$adminMenu}
      </ul>
      <ul class='navbar-nav'>
        <li class='nav-item dropdown'>
          <a class='nav-link dropdown-toggle d-flex align-items-center gap-2' href='#' data-bs-toggle='dropdown'>
            <div class='avatar-sm'>{$initials}</div>
            <span>{$name}</span>
          </a>
          <ul class='dropdown-menu dropdown-menu-end dropdown-menu-dark'>
            <li class='dropdown-item-text small opacity-75'>{$role}</li>
            <li><hr class='dropdown-divider'></li>
            <li><a class='dropdown-item' href='{$base}/profile.php'><i class='bi bi-person me-2'></i>Profil</a></li>
            <li><a class='dropdown-item text-danger' href='{$base}/logout.php'><i class='bi bi-box-arrow-right me-2'></i>Keluar</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>";
}

function renderFooter(): void {
    $base = APP_URL;
    $appRoot = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
    $jsV  = @filemtime($appRoot . '/assets/js/app.js') ?: time();
    $year = date('Y');
    echo "
<footer class='ktb-footer mt-auto py-3'>
  <div class='container-fluid text-center small d-flex flex-wrap align-items-center justify-content-center gap-2'>
    <img src='{$base}/assets/img/brand/agkb-mark.svg' alt='' width='18' height='18' style='height:18px;width:auto;opacity:.9'>
    <span>AGKB 360° — Platform Evaluasi Kinerja 360 Derajat</span>
    <span class='opacity-50'>·</span>
    <span class='opacity-50'>© {$year} AGKB</span>
  </div>
</footer>
<script src='https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/dataTables.bootstrap5.min.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.10.1/sweetalert2.all.min.js'></script>
<script src='{$base}/assets/js/app.js?v={$jsV}'></script>
</body></html>";
}

/**
 * Bingkai untuk halaman yang dibuka tanpa login.
 *
 * Sengaja tidak memakai renderNav(): navigasi itu membaca peran dari
 * sesi, dan pada halaman publik tidak ada sesi. Yang tampil hanya
 * logo dan satu tautan masuk, supaya pengunjung tidak melihat menu
 * yang tidak bisa ia buka.
 */
function publicWrapper(string $title, string $content, string $extraCss = ''): void {
    renderHead($title, $extraCss);
    $base = APP_URL;
    echo "
<nav class='navbar navbar-dark' style='background:#040136'>
  <div class='container-fluid px-3 px-md-4 py-2 d-flex align-items-center justify-content-between'>
    <a class='navbar-brand agkb-brand d-flex align-items-center' href='{$base}/publik/'>
      <img src='{$base}/assets/img/brand/agkb-lockup-white.svg' alt='AGKB 360°'
           onerror=\"this.onerror=null;this.src='{$base}/assets/img/brand/agkb-mark-white.svg'\">
    </a>
    <a href='{$base}/login.php' class='btn btn-sm btn-outline-light rounded-pill px-3'
       style='font-size:12.5px'>
      <i class='bi bi-box-arrow-in-right me-1'></i>Masuk
    </a>
  </div>
</nav>
<div class='container-fluid py-4 flex-grow-1'>";
    echo $content;
    echo '</div>';
    renderFooter();
}

function pageWrapper(string $title, string $content, string $extraCss = ''): void {
    renderHead($title, $extraCss);
    renderNav();
    echo '<div class="container-fluid py-4 flex-grow-1">';
    echo '<div class="d-flex justify-content-between align-items-center mb-4">';
    echo '<h4 class="mb-0 fw-bold text-ink d-flex align-items-center gap-2">'
       . '<span style="display:inline-block;width:4px;height:22px;border-radius:2px;background:var(--agkb-catalyst)"></span>'
       . h($title) . '</h4>';
    echo '</div>';
    echo showFlash();
    echo $content;
    echo '</div>';
    renderFooter();
}