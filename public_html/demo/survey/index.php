<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
$user = currentUser();
$period = getPeriod();

$assignments = Database::fetchAll("
    SELECT a.*, u_ee.name as evaluatee_name, u_ee.role as evaluatee_role,
           p.name as pkg_name, p.code as pkg_code, p.is_self_reflection,
           p.respondent_type,
           (SELECT COUNT(*) FROM package_questions pq WHERE pq.package_id = a.package_id) as total_q,
           (SELECT COUNT(*) FROM responses r WHERE r.assignment_id = a.id) as answered_q
    FROM assignments a
    JOIN users u_ee ON a.evaluatee_id = u_ee.id
    JOIN packages p ON a.package_id = p.id
    WHERE a.evaluator_id = ? AND a.period_id = ?
    ORDER BY a.status ASC, a.created_at DESC
", [$user['id'], $period['id'] ?? 0]);

$pending   = array_filter($assignments, fn($a) => $a['status'] === 'pending');
$progress  = array_filter($assignments, fn($a) => $a['status'] === 'in_progress');
$completed = array_filter($assignments, fn($a) => $a['status'] === 'completed');

ob_start();
?>
<?php if ($period): ?>
<div class="alert alert-info d-flex align-items-center gap-2 mb-4 py-2">
  <i class="bi bi-calendar-event"></i>
  <span>Periode Aktif: <strong><?= h($period['name']) ?></strong> &nbsp;|&nbsp; 
  Deadline: <strong><?= date('d M Y', strtotime($period['end_date'])) ?></strong></span>
</div>
<?php endif; ?>

<!-- PROGRESS OVERVIEW -->
<div class="row g-3 mb-4">
  <div class="col-4"><div class="stat-card red position-relative text-center">
    <div class="stat-number"><?= count($pending) ?></div>
    <div class="stat-label">Menunggu</div>
  </div></div>
  <div class="col-4"><div class="stat-card gold position-relative text-center">
    <div class="stat-number"><?= count($progress) ?></div>
    <div class="stat-label">Sedang Diisi</div>
  </div></div>
  <div class="col-4"><div class="stat-card green position-relative text-center">
    <div class="stat-number"><?= count($completed) ?></div>
    <div class="stat-label">Selesai</div>
  </div></div>
</div>

<!-- SURVEY CARDS -->
<?php foreach (['pending' => $pending, 'in_progress' => $progress, 'completed' => $completed] as $status => $group): ?>
<?php if (empty($group)) continue; ?>
<div class="mb-4">
  <h6 class="fw-bold text-muted mb-3 text-uppercase small letter-spacing-1">
    <?= ['pending'=>'Menunggu Diisi','in_progress'=>'Sedang Diisi','completed'=>'Selesai'][$status] ?>
    <span class="badge bg-secondary ms-1"><?= count($group) ?></span>
  </h6>
  <div class="row g-3">
    <?php foreach ($group as $a): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card h-100 <?= $status === 'completed' ? 'border-success' : ($status === 'in_progress' ? 'border-warning' : '') ?>" style="border-width:2px">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="badge badge-navy"><?= h($a['pkg_code']) ?></span>
            <?= statusBadge($a['status']) ?>
          </div>
          <h6 class="fw-bold text-navy mb-1"><?= h($a['evaluatee_name']) ?></h6>
          <p class="small text-muted mb-2"><?= h($a['pkg_name']) ?></p>
          <?php if ($a['is_self_reflection']): ?>
          <span class="badge bg-info text-dark small mb-2">Refleksi Mandiri</span>
          <?php endif; ?>
          <?php if ($a['total_q'] > 0): ?>
          <div class="mb-2">
            <div class="d-flex justify-content-between small mb-1">
              <span class="text-muted">Progres</span>
              <span><?= $a['answered_q'] ?>/<?= $a['total_q'] ?> pertanyaan</span>
            </div>
            <div class="progress">
              <div class="progress-bar navy" style="width:<?= ($a['answered_q']/$a['total_q'])*100 ?>%"></div>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <div class="card-footer bg-transparent">
          <?php if ($status === 'completed'): ?>
          <a href="<?= APP_URL ?>/survey/fill.php?id=<?= $a['id'] ?>&view=1" class="btn btn-sm btn-outline-secondary w-100">
            <i class="bi bi-eye me-1"></i>Lihat Jawaban
          </a>
          <?php else: ?>
          <a href="<?= APP_URL ?>/survey/fill.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-navy w-100">
            <i class="bi bi-pencil-square me-1"></i>
            <?= $status === 'pending' ? 'Mulai Mengisi' : 'Lanjutkan' ?>
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<?php if (empty($assignments)): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-clipboard-x display-4"></i>
  <p class="mt-3">Belum ada kuesioner yang ditugaskan kepada Anda.</p>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
pageWrapper('Kuesioner Saya', $content);
