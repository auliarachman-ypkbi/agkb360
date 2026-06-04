<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin','admin','foundation']);

// Buat periode baru → langsung ke wizard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_period'])) {
    $name  = trim($_POST['name'] ?? '');
    $year  = (int)($_POST['year'] ?? date('Y'));
    $start = $_POST['start_date'] ?? '';
    $end   = $_POST['end_date']   ?? '';

    // Cek tanggal tidak overlap dengan periode sebelumnya
    $lastPeriod = Database::fetchOne("
        SELECT * FROM eval_periods
        WHERE status IN ('active','closed')
        ORDER BY end_date DESC, id DESC
        LIMIT 1
    ");

    $dateError = '';
    if ($lastPeriod && $lastPeriod['end_date'] && $start) {
        if ($start <= $lastPeriod['end_date']) {
            $dateError = 'Tanggal mulai harus setelah periode terakhir selesai ('
                . date('d M Y', strtotime($lastPeriod['end_date']))
                . ' — ' . h($lastPeriod['name']) . ').';
        }
    }

    if ($name && !$dateError) {
        $pid = Database::insert('eval_periods', [
            'name'       => $name,
            'year'       => $year,
            'start_date' => $start ?: null,
            'end_date'   => $end   ?: null,
            'status'     => 'draft',
            'wizard_step'=> 0,
            'is_active'  => 0,
        ]);
        header('Location: ' . APP_URL . '/admin/period_wizard.php?id=' . $pid);
        exit;
    } elseif ($dateError) {
        flash($dateError, 'danger');
    }
}

// Hapus periode draft
if (isset($_GET['delete']) && isset($_GET['confirm'])) {
    $pid = (int)$_GET['delete'];
    $p   = Database::fetchOne("SELECT status FROM eval_periods WHERE id=?", [$pid]);
    if ($p && $p['status'] === 'draft') {
        // Hapus data terkait
        Database::query("DELETE FROM period_evaluatees WHERE period_id=?", [$pid]);
        Database::query("DELETE FROM period_evaluators WHERE period_id=?", [$pid]);
        Database::query("DELETE FROM assignments WHERE period_id=?", [$pid]);
        Database::query("DELETE FROM packages WHERE period_id=?", [$pid]);
        Database::query("DELETE FROM eval_periods WHERE id=?", [$pid]);
        flash('Periode draft berhasil dihapus.', 'success');
    } else {
        flash('Hanya periode draft yang bisa dihapus.', 'danger');
    }
    header('Location: ' . APP_URL . '/admin/periods.php');
    exit;
}

// Tutup periode — dengan warning Opsi C
if (isset($_GET['close'])) {
    $pid = (int)$_GET['close'];

    // Cek assignment yang belum selesai
    $incomplete = Database::fetchAll("
        SELECT a.id, a.status,
               u.name as evaluator_name,
               ue.name as evaluatee_name,
               (SELECT COUNT(*) FROM responses r WHERE r.assignment_id = a.id) as answered,
               (SELECT COUNT(*) FROM package_questions pq WHERE pq.package_id = a.package_id) as total
        FROM assignments a
        JOIN users u  ON u.id  = a.evaluator_id
        JOIN users ue ON ue.id = a.evaluatee_id
        WHERE a.period_id = ? AND a.status IN ('pending','in_progress')
        ORDER BY a.status DESC, u.name
        LIMIT 50
    ", [$pid]);

    // Kalau belum konfirmasi dan ada yang incomplete → tampilkan warning
    if (!empty($incomplete) && !isset($_GET['force'])) {
        // Simpan ke session untuk ditampilkan di halaman
        $_SESSION['close_warning'] = [
            'pid'        => $pid,
            'incomplete' => $incomplete,
        ];
        header('Location: ' . APP_URL . '/admin/periods.php?warn_close=' . $pid);
        exit;
    }

    // Tutup periode
    Database::update('eval_periods', ['status'=>'closed','is_active'=>0], 'id=?', [$pid]);
    unset($_SESSION['close_warning']);
    flash('Periode ditutup. Data yang belum di-submit tidak dihitung.', 'info');
    header('Location: ' . APP_URL . '/admin/periods.php');
    exit;
}

// Edit nama periode
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_name'])) {
    $pid  = (int)$_POST['period_id'];
    $name = trim($_POST['name'] ?? '');
    if ($name) {
        Database::update('eval_periods', ['name' => $name], 'id=?', [$pid]);
        flash('Nama periode berhasil diperbarui.', 'success');
    }
    header('Location: ' . APP_URL . '/admin/periods.php');
    exit;
}

// Cek apakah ada periode aktif
$activePeriod = Database::fetchOne("SELECT * FROM eval_periods WHERE status='active' LIMIT 1");

$periods = Database::fetchAll("
    SELECT ep.*,
      (SELECT COUNT(*) FROM assignments a WHERE a.period_id = ep.id) as total_assign,
      (SELECT COUNT(*) FROM assignments a WHERE a.period_id = ep.id AND a.status='completed') as completed_assign
    FROM eval_periods ep
    ORDER BY
      CASE ep.status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END,
      ep.year DESC, ep.id DESC
");

$statusConfig = [
    'draft'  => ['bg'=>'#6b7280','label'=>'Draft',  'icon'=>'bi-pencil'],
    'active' => ['bg'=>'#16a34a','label'=>'Aktif',  'icon'=>'bi-play-circle-fill'],
    'closed' => ['bg'=>'#374151','label'=>'Selesai','icon'=>'bi-archive-fill'],
];

ob_start(); ?>

<?= showFlash() ?>

<!-- Warning: ada assignment belum selesai sebelum tutup -->
<?php
$warnClose = $_SESSION['close_warning'] ?? null;
if ($warnClose && isset($_GET['warn_close'])):
  $incomplete = $warnClose['incomplete'];
  $closePid   = $warnClose['pid'];
?>
<div class="card border-danger mb-4">
  <div class="card-header bg-danger text-white d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <strong>Peringatan — Ada <?= count($incomplete) ?> Kuesioner Belum Selesai!</strong>
  </div>
  <div class="card-body">
    <p class="text-muted small mb-3">
      Kalau periode ditutup sekarang, data kuesioner yang belum di-submit akan
      <strong>hangus dan tidak dihitung</strong>.
    </p>
    <div class="table-responsive mb-3">
      <table class="table table-sm small">
        <thead>
          <tr style="background:#fef2f2">
            <th>Penilai</th>
            <th>Yang Dinilai</th>
            <th>Status</th>
            <th class="text-center">Progress</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($incomplete as $inc): ?>
          <tr>
            <td><?= h($inc['evaluator_name']) ?></td>
            <td><?= h($inc['evaluatee_name']) ?></td>
            <td>
              <?php if ($inc['status'] === 'in_progress'): ?>
              <span class="badge bg-warning text-dark">Sedang Diisi</span>
              <?php else: ?>
              <span class="badge bg-secondary">Belum Mulai</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <?= $inc['answered'] ?>/<?= $inc['total'] ?> soal
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= APP_URL ?>/admin/periods.php"
         class="btn btn-outline-secondary"
         onclick="<?php unset($_SESSION['close_warning']); ?>">
        <i class="bi bi-arrow-left me-1"></i>Batal — Beri Waktu Tambahan
      </a>
      <a href="?close=<?= $closePid ?>&force=1"
         class="btn btn-danger"
         onclick="return confirm('Yakin tutup? Data yang belum di-submit akan hangus.')">
        <i class="bi bi-archive me-1"></i>Tutup Tetap — Data Hangus
      </a>
    </div>
  </div>
</div>
<?php
  unset($_SESSION['close_warning']);
endif;
?>

<!-- Warning kalau ada periode aktif -->
<?php if ($activePeriod): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
  <i class="bi bi-exclamation-triangle-fill"></i>
  <div>
    <strong><?= h($activePeriod['name']) ?></strong> sedang aktif.
    Tutup periode ini terlebih dahulu sebelum mengaktifkan periode baru.
    <a href="?close=<?= $activePeriod['id'] ?>"
       class="btn btn-sm btn-warning ms-2">
      <i class="bi bi-archive me-1"></i>Tutup Sekarang
    </a>
  </div>
</div>
<?php endif; ?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <p class="text-muted small mb-0">Setiap periode baru akan melalui wizard setup 6 langkah.</p>
  </div>
  <button class="btn btn-navy" data-bs-toggle="modal" data-bs-target="#modalCreate">
    <i class="bi bi-plus-lg me-2"></i>Buat Periode Baru
  </button>
</div>

<!-- Daftar Periode -->
<?php if (empty($periods)): ?>
<div class="card">
  <div class="card-body text-center py-5 text-muted">
    <i class="bi bi-calendar-x display-4 mb-3 d-block"></i>
    <p>Belum ada periode evaluasi.</p>
    <button class="btn btn-navy" data-bs-toggle="modal" data-bs-target="#modalCreate">
      Buat Periode Pertama →
    </button>
  </div>
</div>
<?php else: ?>

<div class="row g-3">
  <?php foreach ($periods as $p):
    $sc  = $statusConfig[$p['status']] ?? $statusConfig['draft'];
    $pct = $p['total_assign'] > 0
         ? round($p['completed_assign'] / $p['total_assign'] * 100) : 0;
  ?>
  <div class="col-md-6 col-lg-4">
    <div class="card h-100 border-0 shadow-sm">
      <!-- Header warna sesuai status -->
      <div class="card-header d-flex justify-content-between align-items-center py-2"
           style="background:<?= $sc['bg'] ?>;color:white">
        <div class="d-flex align-items-center gap-2">
          <i class="bi <?= $sc['icon'] ?>"></i>
          <strong style="font-size:.85rem"><?= $sc['label'] ?></strong>
        </div>
        <span class="badge bg-white" style="color:<?= $sc['bg'] ?>;font-size:.7rem">
          <?= h($p['year']) ?>
        </span>
      </div>

      <div class="card-body">
        <h6 class="fw-bold text-navy mb-1"><?= h($p['name']) ?></h6>
        <?php if ($p['start_date']): ?>
        <p class="text-muted small mb-2">
          <i class="bi bi-calendar me-1"></i>
          <?= date('d M Y', strtotime($p['start_date'])) ?>
          <?php if ($p['end_date']): ?>
          → <?= date('d M Y', strtotime($p['end_date'])) ?>
          <?php endif; ?>
        </p>
        <?php endif; ?>

        <!-- Wizard progress untuk draft -->
        <?php if ($p['status'] === 'draft'): ?>
        <div class="mb-3">
          <div class="d-flex justify-content-between small mb-1">
            <span class="text-muted">Setup wizard</span>
            <span class="fw-semibold"><?= $p['wizard_step'] ?>/6</span>
          </div>
          <div class="progress" style="height:6px">
            <div class="progress-bar" style="width:<?= ($p['wizard_step']/6)*100 ?>%;background:var(--ktb-navy)"></div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Progress penugasan untuk aktif -->
        <?php if ($p['status'] === 'active' && $p['total_assign'] > 0): ?>
        <div class="mb-3">
          <div class="d-flex justify-content-between small mb-1">
            <span class="text-muted">Kuesioner selesai</span>
            <span class="fw-semibold"><?= $p['completed_assign'] ?>/<?= $p['total_assign'] ?></span>
          </div>
          <div class="progress" style="height:6px">
            <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <div class="card-footer bg-transparent d-flex gap-2">
        <?php if ($p['status'] === 'draft'): ?>
        <a href="<?= APP_URL ?>/admin/period_wizard.php?id=<?= $p['id'] ?>"
           class="btn btn-sm btn-navy flex-grow-1">
          <i class="bi bi-arrow-right me-1"></i>
          <?= $p['wizard_step'] > 0 ? 'Lanjut Setup' : 'Mulai Setup' ?>
        </a>
        <a href="?delete=<?= $p['id'] ?>&confirm=1"
           class="btn btn-sm btn-outline-danger"
           onclick="return confirm('Hapus periode draft ini? Semua data setup akan hilang.')"
           title="Hapus draft">
          <i class="bi bi-trash"></i>
        </a>
        <?php elseif ($p['status'] === 'active'): ?>
        <a href="<?= APP_URL ?>/admin/reports.php"
           class="btn btn-sm btn-outline-primary flex-grow-1">
          <i class="bi bi-bar-chart me-1"></i>Laporan
        </a>
        <a href="?close=<?= $p['id'] ?>"
           class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-archive me-1"></i>Tutup
        </a>
        <?php else: ?>
        <a href="<?= APP_URL ?>/admin/reports.php?period_id=<?= $p['id'] ?>"
           class="btn btn-sm btn-outline-secondary flex-grow-1">
          <i class="bi bi-bar-chart me-1"></i>Lihat Laporan
        </a>
        <?php endif; ?>
        <button class="btn btn-sm btn-outline-secondary"
          data-bs-toggle="modal" data-bs-target="#editName<?= $p['id'] ?>"
          title="Edit nama periode">
          <i class="bi bi-pencil"></i>
        </button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

<!-- Modals: Edit Nama Periode -->
<?php foreach ($periods as $p): ?>
<div class="modal fade" id="editName<?= $p['id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header py-2" style="background:var(--ktb-navy);color:white">
        <h6 class="modal-title small fw-bold"><i class="bi bi-pencil me-2"></i>Edit Nama</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="edit_name" value="1">
        <input type="hidden" name="period_id" value="<?= $p['id'] ?>">
        <div class="modal-body">
          <label class="form-label small fw-semibold">Nama Periode</label>
          <input type="text" name="name" class="form-control form-control-sm" required
            value="<?= h($p['name']) ?>">
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-sm btn-navy">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

<!-- Modal: Buat Periode Baru -->
<div class="modal fade" id="modalCreate" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--ktb-navy);color:white">
        <h6 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Buat Periode Baru</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="create_period" value="1">
        <div class="modal-body">
          <?php
          $lastP = Database::fetchOne("
              SELECT * FROM eval_periods
              WHERE status IN ('active','closed')
              ORDER BY end_date DESC, id DESC LIMIT 1
          ");
          $minDate = $lastP && $lastP['end_date']
              ? date('Y-m-d', strtotime($lastP['end_date'] . ' +1 day'))
              : null;
          ?>
          <?php if ($lastP && $lastP['end_date']): ?>
          <div class="alert alert-light border small mb-3">
            <i class="bi bi-calendar-check me-1 text-primary"></i>
            Periode terakhir: <strong><?= h($lastP['name']) ?></strong>
            selesai <strong><?= date('d M Y', strtotime($lastP['end_date'])) ?></strong>.
            Tanggal mulai periode baru harus setelah tanggal ini.
          </div>
          <?php endif; ?>
          <div class="mb-3">
            <label class="form-label fw-semibold">Nama Periode <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required
              placeholder="cth: Evaluasi Tahunan 2025–2026 Semester 1">
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold">Tahun</label>
              <input type="number" name="year" class="form-control" value="<?= date('Y') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Mulai</label>
              <input type="date" name="start_date" class="form-control"
                <?= $minDate ? 'min="'.$minDate.'" value="'.$minDate.'"' : '' ?>>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Selesai</label>
              <input type="date" name="end_date" class="form-control">
            </div>
          </div>
          <div class="alert alert-light border small mt-3 mb-0">
            <i class="bi bi-info-circle me-1 text-primary"></i>
            Setelah dibuat, kamu akan diarahkan ke wizard setup 6 langkah.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-navy">
            <i class="bi bi-arrow-right me-1"></i>Buat & Mulai Setup
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php $content = ob_get_clean(); pageWrapper('Periode Evaluasi', $content); ?>