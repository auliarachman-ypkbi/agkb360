<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin','admin','foundation']);

$period = getPeriod();
$pid = $period['id'] ?? 0;

// Stats
$stats = [
  'users'      => Database::fetchOne("SELECT COUNT(*) c FROM users WHERE is_active=1")['c'],
  'leaders'    => Database::fetchOne("SELECT COUNT(*) c FROM users WHERE role='leader' AND is_active=1")['c'],
  'teachers'   => Database::fetchOne("SELECT COUNT(*) c FROM users WHERE role='teacher' AND is_active=1")['c'],
  'students'   => Database::fetchOne("SELECT COUNT(*) c FROM users WHERE role='student' AND is_active=1")['c'],
  'completed'  => Database::fetchOne("SELECT COUNT(*) c FROM assignments WHERE period_id=? AND status='completed'",[$pid])['c'],
  'pending'    => Database::fetchOne("SELECT COUNT(*) c FROM assignments WHERE period_id=? AND status='pending'",[$pid])['c'],
  'total_a'    => Database::fetchOne("SELECT COUNT(*) c FROM assignments WHERE period_id=?",[$pid])['c'],
  'packages'   => Database::fetchOne("SELECT COUNT(*) c FROM packages")['c'],
  'questions'  => Database::fetchOne("SELECT COUNT(*) c FROM questions")['c'],
];

// Completion % per evaluatee type
$leaderProgress = Database::fetchAll("
    SELECT u.name, 
           SUM(CASE WHEN a.status='completed' THEN 1 ELSE 0 END) as done,
           COUNT(*) as total
    FROM users u LEFT JOIN assignments a ON a.evaluatee_id=u.id AND a.period_id=?
    WHERE u.role='leader' AND u.is_active=1
    GROUP BY u.id, u.name
", [$pid]);

$teacherProgress = Database::fetchAll("
    SELECT u.name, 
           SUM(CASE WHEN a.status='completed' THEN 1 ELSE 0 END) as done,
           COUNT(*) as total
    FROM users u LEFT JOIN assignments a ON a.evaluatee_id=u.id AND a.period_id=?
    WHERE u.role='teacher' AND u.is_active=1
    GROUP BY u.id, u.name LIMIT 10
", [$pid]);

ob_start(); ?>
<!-- TOP STATS -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat-card position-relative">
    <div class="stat-label">Total Pengguna</div>
    <div class="stat-number"><?= $stats['users'] ?></div>
    <div class="small text-muted"><?= $stats['leaders'] ?> leader, <?= $stats['teachers'] ?> guru, <?= $stats['students'] ?> siswa</div>
    <i class="bi bi-people stat-icon"></i>
  </div></div>
  <div class="col-6 col-md-3"><div class="stat-card gold position-relative">
    <div class="stat-label">Total Penugasan</div>
    <div class="stat-number"><?= $stats['total_a'] ?></div>
    <div class="small text-muted">Periode aktif</div>
    <i class="bi bi-clipboard stat-icon"></i>
  </div></div>
  <div class="col-6 col-md-3"><div class="stat-card green position-relative">
    <div class="stat-label">Selesai</div>
    <div class="stat-number"><?= $stats['completed'] ?></div>
    <div class="small text-muted"><?= $stats['total_a'] > 0 ? round($stats['completed']/$stats['total_a']*100) : 0 ?>% completion rate</div>
    <i class="bi bi-check-circle stat-icon"></i>
  </div></div>
  <div class="col-6 col-md-3"><div class="stat-card red position-relative">
    <div class="stat-label">Belum Mulai</div>
    <div class="stat-number"><?= $stats['pending'] ?></div>
    <div class="small text-muted"><?= $stats['packages'] ?> paket, <?= $stats['questions'] ?> soal</div>
    <i class="bi bi-clock stat-icon"></i>
  </div></div>
</div>

<!-- QUICK LINKS -->
<div class="row g-3 mb-4">
  <?php $quickLinks = [
    ['Kelola Pengguna',APP_URL.'/admin/users.php','bi-people-fill','navy'],
    ['Penugasan',APP_URL.'/admin/assignments.php','bi-send-fill','gold'],
    ['Laporan Kinerja',APP_URL.'/admin/reports.php','bi-bar-chart-fill','green'],
    ['Pengaturan & Reset',APP_URL.'/admin/settings.php','bi-sliders','red'],
  ]; foreach ($quickLinks as [$label,$href,$icon,$color]): ?>
  <div class="col-6 col-md-3">
    <a href="<?= $href ?>" class="text-decoration-none">
      <div class="stat-card <?= $color ?> text-center py-3 cursor-pointer">
        <i class="bi <?= $icon ?> display-6" style="color:var(--ktb-navy)"></i>
        <div class="fw-semibold mt-2 small"><?= $label ?></div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-4">
  <!-- Leader Progress -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-person-badge me-2"></i>Progress Evaluasi — Pimpinan</div>
      <div class="card-body">
        <?php foreach ($leaderProgress as $lp): ?>
        <div class="mb-3">
          <div class="d-flex justify-content-between small mb-1">
            <span class="fw-semibold"><?= h($lp['name']) ?></span>
            <span><?= $lp['done'] ?>/<?= $lp['total'] ?></span>
          </div>
          <div class="progress">
            <?php $pct = $lp['total'] > 0 ? round($lp['done']/$lp['total']*100) : 0; ?>
            <div class="progress-bar navy" style="width:<?= $pct ?>%"><?= $pct ?>%</div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <!-- Teacher Progress -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-mortarboard me-2"></i>Progress Evaluasi — Guru (Top 10)</div>
      <div class="card-body">
        <?php foreach ($teacherProgress as $tp): ?>
        <div class="mb-2">
          <div class="d-flex justify-content-between small mb-1">
            <span><?= h($tp['name']) ?></span>
            <span><?= $tp['done'] ?>/<?= $tp['total'] ?></span>
          </div>
          <div class="progress" style="height:8px">
            <?php $pct = $tp['total'] > 0 ? round($tp['done']/$tp['total']*100) : 0; ?>
            <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $pct>=80?'#198754':($pct>=50?'#0d6efd':'#fd7e14') ?>"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php $content = ob_get_clean(); pageWrapper('Admin Dashboard', $content); ?>