<?php
function renderHead(string $title = '', string $extraCss = ''): void {
    $t    = $title ? h($title) . ' — ' : '';
    $base = APP_URL;
    echo "<!DOCTYPE html>
<html lang='id'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
<title>{$t}AKGB 360</title>
<link rel='preconnect' href='https://fonts.googleapis.com'>
<link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
<link rel='stylesheet' href='https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&display=swap'>
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css'>
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css'>
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/dataTables.bootstrap5.min.css'>
<link rel='stylesheet' href='{$base}/assets/css/style.css'>
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
<div style='background:#7c3aed;color:white;text-align:center;padding:6px;font-size:12px;font-weight:600;letter-spacing:.05em'>
  <i class='bi bi-bug-fill me-1'></i>MODE TESTER — Aktivitas tidak dihitung dalam evaluasi
</div>" : '';

    // View As banner
    $viewAsBanner = isViewingAs() ? "
<div style='background:#d97706;color:white;text-align:center;padding:8px 16px;font-size:12px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:12px'>
  <i class='bi bi-eye-fill'></i>
  MODE PREVIEW — Anda sedang melihat sebagai Guru
  <a href='{$base}/admin/view_as.php?action=exit'
     style='background:rgba(255,255,255,.2);color:white;text-decoration:none;padding:3px 12px;border-radius:20px;font-size:11px;margin-left:8px'>
    ✕ Kembali ke Admin
  </a>
</div>" : '';

    // Superadmin extra menu
    $superAdminExtra = $role === 'superadmin' ? "
        <li><hr class='dropdown-divider'></li>
        <li><a class='dropdown-item text-warning fw-semibold' href='{$base}/admin/hard_reset.php'><i class='bi bi-radiation me-2'></i>Hard Reset</a></li>" : '';

    if (in_array($role, ['superadmin','admin','foundation'])) {
        $adminMenu = "
        <li class='nav-item dropdown'>
          <a class='nav-link dropdown-toggle' href='#' data-bs-toggle='dropdown'>
            <i class='bi bi-gear-fill me-1'></i>Admin CMS
          </a>
          <ul class='dropdown-menu dropdown-menu-dark'>
            <li><a class='dropdown-item' href='{$base}/admin/'><i class='bi bi-speedometer2 me-2'></i>Admin Dashboard</a></li>
            <li><hr class='dropdown-divider'></li>
            <li><a class='dropdown-item' href='{$base}/admin/users.php'><i class='bi bi-people me-2'></i>Pengguna</a></li>
            <li><a class='dropdown-item' href='{$base}/admin/classes.php'><i class='bi bi-building me-2'></i>Kelas & Mapping Guru</a></li>
            <li><hr class='dropdown-divider'></li>
            <li><a class='dropdown-item' href='{$base}/admin/periods.php'><i class='bi bi-calendar3 me-2'></i>Periode Evaluasi</a></li>
            <li><a class='dropdown-item' href='{$base}/admin/assignments.php'><i class='bi bi-send me-2'></i>Penugasan</a></li>
            <li><a class='dropdown-item' href='{$base}/admin/reports.php'><i class='bi bi-bar-chart me-2'></i>Laporan</a></li>
            <li><hr class='dropdown-divider'></li>
            <li><a class='dropdown-item' href='{$base}/admin/foundation.php'><i class='bi bi-diagram-3 me-2'></i>Domain / Standard / Trait</a></li>
            <li><a class='dropdown-item' href='{$base}/admin/matrix.php'><i class='bi bi-grid me-2'></i>Matriks Mapping</a></li>
            <li><a class='dropdown-item' href='{$base}/admin/questions_master.php'><i class='bi bi-clipboard-check me-2'></i>Master Pertanyaan</a></li>
            <li><a class='dropdown-item' href='{$base}/admin/questions_packages.php'><i class='bi bi-folder me-2'></i>Paket Pertanyaan</a></li>
            <li><hr class='dropdown-divider'></li>
            <li><a class='dropdown-item' href='{$base}/admin/settings.php'><i class='bi bi-sliders me-2'></i>Pengaturan</a></li>
            <li><hr class='dropdown-divider'></li>
            <li><a class='dropdown-item' href='{$base}/admin/feedback.php'><i class='bi bi-chat-heart me-2'></i>Inbox Feedback</a></li>
	    <li><a class='dropdown-item' href='{$base}/admin/blast_email.php'><i class='bi bi-send-fill me-2'></i>Blast Email</a></li>
            <li><hr class='dropdown-divider'></li>
            <li><a class='dropdown-item text-warning' href='{$base}/admin/view_as.php?action=activate'><i class='bi bi-eye me-2'></i>Preview sebagai Guru</a></li>
            {$superAdminExtra}
          </ul>
        </li>";
    }

    echo $viewAsBanner . $testerBanner . "
<nav class='navbar navbar-expand-lg navbar-dark ktb-navbar'>
  <div class='container-fluid'>
    <a class='navbar-brand d-flex align-items-center gap-2' href='{$base}/dashboard/'>
      <img src='{$base}/assets/img/logoAKGB360.png' alt='AKGB 360'
           style='height:36px;width:auto;object-fit:contain;mix-blend-mode:screen;filter:brightness(1.2)'
           onerror='this.style.display=\"none\";this.nextElementSibling.style.display=\"flex\"'>
      <div class='ktb-logo-sm' style='display:none'>360</div>
      <div>
        <div class='fw-bold lh-1'>AKGB <span style='color:var(--ktb-gold)'>360°</span></div>
        <div class='small opacity-75 lh-1' style='font-size:.6rem'>Platform Evaluasi Kinerja</div>
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
        " . ($role !== 'tester' ? "
        <li class='nav-item'><a class='nav-link' href='{$base}/feedback/' style='color:#ffc901'><i class='bi bi-chat-heart me-1'></i>Feedback</a></li>
        " : "") . "
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
    echo "
<footer class='ktb-footer mt-auto py-3'>
  <div class='container-fluid text-center small'>
    <span class='opacity-50'>AKGB 360 — Platform Evaluasi Kinerja 360 Derajat</span>
  </div>
</footer>
<script src='https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/dataTables.bootstrap5.min.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.10.1/sweetalert2.all.min.js'></script>
<script src='{$base}/assets/js/app.js'></script>
</body></html>";
}

function pageWrapper(string $title, string $content, string $extraCss = ''): void {
    renderHead($title, $extraCss);
    renderNav();
    echo '<div class="container-fluid py-4 flex-grow-1">';
    echo '<div class="d-flex justify-content-between align-items-center mb-4">';
    echo '<h4 class="mb-0 fw-bold text-navy">' . h($title) . '</h4>';
    echo '</div>';
    echo showFlash();
    echo $content;
    echo '</div>';
    renderFooter();
}