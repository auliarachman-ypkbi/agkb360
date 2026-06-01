<?php
function renderHead(string $title = '', string $extraCss = ''): void {
    $t    = $title ? h($title) . ' — ' : '';
    $base = APP_URL;
    echo "<!DOCTYPE html>
<html lang='id'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
<title>{$t}KTB 360 Evaluation</title>
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
    if (in_array($role, ['admin','foundation'])) {
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
            <li><a class='dropdown-item' href='{$base}/admin/questions.php'><i class='bi bi-clipboard-check me-2'></i>Editor Kuesioner</a></li>
            <li><hr class='dropdown-divider'></li>
            <li><a class='dropdown-item' href='{$base}/admin/settings.php'><i class='bi bi-sliders me-2'></i>Pengaturan</a></li>
          </ul>
        </li>";
    }

    echo "
<nav class='navbar navbar-expand-lg navbar-dark ktb-navbar'>
  <div class='container-fluid'>
    <a class='navbar-brand d-flex align-items-center gap-2' href='{$base}/dashboard/'>
      <div class='ktb-logo-sm'>360</div>
      <div>
        <div class='fw-bold lh-1'>KTB 360</div>
        <div class='small opacity-75 lh-1' style='font-size:.65rem'>Evaluation Platform</div>
      </div>
    </a>
    <button class='navbar-toggler' type='button' data-bs-toggle='collapse' data-bs-target='#navMain'>
      <span class='navbar-toggler-icon'></span>
    </button>
    <div class='collapse navbar-collapse' id='navMain'>
      <ul class='navbar-nav me-auto'>
        <li class='nav-item'><a class='nav-link' href='{$base}/dashboard/'><i class='bi bi-house me-1'></i>Dashboard</a></li>
        <li class='nav-item'><a class='nav-link' href='{$base}/survey/'><i class='bi bi-clipboard-check me-1'></i>Kuesioner Saya</a></li>
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
    <span class='opacity-50'>KTB 360 Evaluation Platform v1.0 &copy; 2025 SMA Kemala Taruna Bhayangkara</span>
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