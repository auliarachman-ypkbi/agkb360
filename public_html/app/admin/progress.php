<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin','admin','foundation','leader']);

$period = getPeriod();
if (!$period) {
    flash('Tidak ada periode evaluasi yang aktif.', 'warning');
    header('Location: ' . APP_URL . '/admin/periods.php');
    exit;
}

$pid = $period['id'];

// Stats umum
$totalAssign   = Database::fetchOne("SELECT COUNT(*) c FROM assignments WHERE period_id=?", [$pid])['c'];
$completedA    = Database::fetchOne("SELECT COUNT(*) c FROM assignments WHERE period_id=? AND status='completed'", [$pid])['c'];
$inProgressA   = Database::fetchOne("SELECT COUNT(*) c FROM assignments WHERE period_id=? AND status='in_progress'", [$pid])['c'];
$pendingA      = Database::fetchOne("SELECT COUNT(*) c FROM assignments WHERE period_id=? AND status='pending'", [$pid])['c'];
$completion    = $totalAssign > 0 ? round($completedA/$totalAssign*100) : 0;

// Progress per evaluatee
$evaluatees = Database::fetchAll("
    SELECT u.id, u.name, u.role,
           COUNT(a.id) as total,
           SUM(CASE WHEN a.status='completed'   THEN 1 ELSE 0 END) as done,
           SUM(CASE WHEN a.status='in_progress' THEN 1 ELSE 0 END) as ongoing,
           SUM(CASE WHEN a.status='pending'     THEN 1 ELSE 0 END) as pending
    FROM users u
    JOIN assignments a ON a.evaluatee_id=u.id AND a.period_id=?
    WHERE u.role IN ('leader','teacher')
    GROUP BY u.id, u.name, u.role
    ORDER BY u.role, (done/total) DESC, u.name
", [$pid]);

// Assignment belum selesai per evaluatee
$incomplete = Database::fetchAll("
    SELECT a.id, a.status,
           ue.name as evaluatee_name, uor.name as evaluator_name,
           p.name as pkg_name,
           (SELECT COUNT(*) FROM responses r WHERE r.assignment_id=a.id) as answered,
           (SELECT COUNT(*) FROM package_questions pq WHERE pq.package_id=a.package_id) as total_q
    FROM assignments a
    JOIN users ue  ON ue.id = a.evaluatee_id
    JOIN users uor ON uor.id = a.evaluator_id
    JOIN packages p ON p.id = a.package_id
    WHERE a.period_id=? AND a.status IN ('pending','in_progress')
    ORDER BY a.status DESC, ue.name, uor.name
    LIMIT 100
", [$pid]);

ob_start(); ?>

<style>
.prog-hero{background:var(--ktb-navy);color:white;border-radius:16px;padding:24px 28px;margin-bottom:20px}
.stat-mini{text-align:center;padding:.6rem}
.sval{font-size:22px;font-weight:500}
.slbl{font-size:11px;opacity:.7;margin-top:2px}
.ev-card{background:var(--color-background-primary);border:0.5px solid var(--color-border-tertiary);border-radius:10px;padding:10px 14px;margin-bottom:8px}
.pbar{height:6px;border-radius:3px;background:rgba(255,255,255,.2);overflow:hidden;margin-top:6px}
.pbfill{height:100%;border-radius:3px}
.status-dot{width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0}
</style>

<!-- Hero -->
<div class="prog-hero">
  <div class="d-flex justify-content-between align-items-start mb-4">
    <div>
      <h5 class="fw-bold mb-1">Progress Evaluasi Berjalan</h5>
      <div class="opacity-75 small">
        <i class="bi bi-circle-fill me-1" style="font-size:.5rem;color:#4ade80"></i>
        <?= h($period['name']) ?>
        <?php if ($period['end_date']): ?>
        · Berakhir <?= date('d M Y', strtotime($period['end_date'])) ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= APP_URL ?>/dashboard/" class="btn btn-sm btn-outline-light">
        <i class="bi bi-arrow-left me-1"></i>Dashboard
      </a>
      <a href="<?= APP_URL ?>/admin/periods.php?close=<?= $pid ?>"
         class="btn btn-sm btn-outline-danger"
         onclick="return confirm('Tutup periode ini?')">
        <i class="bi bi-archive me-1"></i>Tutup Periode
      </a>
    </div>
  </div>

  <!-- Overall progress bar -->
  <div class="mb-3">
    <div class="d-flex justify-content-between mb-2">
      <span style="font-size:13px">Progress keseluruhan</span>
      <span style="font-size:20px;font-weight:500"><?= $completion ?>%</span>
    </div>
    <div class="pbar" style="height:10px">
      <div class="pbfill" style="width:<?= $completion ?>%;background:<?= $completion>=80?'#4ade80':($completion>=50?'#fbbf24':'#f87171') ?>"></div>
    </div>
  </div>

  <!-- Stats row -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px">
    <div class="stat-mini">
      <div class="sval"><?= $totalAssign ?></div>
      <div class="slbl">Total penugasan</div>
    </div>
    <div class="stat-mini">
      <div class="sval" style="color:#4ade80"><?= $completedA ?></div>
      <div class="slbl">Selesai</div>
    </div>
    <div class="stat-mini">
      <div class="sval" style="color:#fbbf24"><?= $inProgressA ?></div>
      <div class="slbl">Sedang diisi</div>
    </div>
    <div class="stat-mini">
      <div class="sval" style="color:#f87171"><?= $pendingA ?></div>
      <div class="slbl">Belum mulai</div>
    </div>
  </div>
</div>

<div class="row g-4">

  <!-- Progress per evaluatee -->
  <div class="col-md-5">
    <div class="card h-100">
      <div class="card-header fw-semibold">
        <i class="bi bi-person-check me-2"></i>Progress per Orang
      </div>
      <div class="card-body p-2">
        <?php
        $roleLabels = ['leader'=>'Pimpinan','teacher'=>'Guru'];
        $byRole = [];
        foreach ($evaluatees as $ev) $byRole[$ev['role']][] = $ev;

        foreach ($byRole as $role => $people):
        ?>
        <div style="padding:6px 8px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--color-text-secondary);margin-top:4px">
          <?= $roleLabels[$role] ?? $role ?>
        </div>
        <?php foreach ($people as $ev):
          $pct = $ev['total'] > 0 ? round($ev['done']/$ev['total']*100) : 0;
          $c = $pct>=80?'#16a34a':($pct>=50?'#d97706':'#dc2626');
        ?>
        <div class="ev-card">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div style="font-size:12px;font-weight:500"><?= h($ev['name']) ?></div>
              <div style="font-size:10px;color:var(--color-text-secondary);margin-top:1px">
                <?= $ev['done'] ?>/<?= $ev['total'] ?> selesai
                <?php if ($ev['ongoing'] > 0): ?>
                · <?= $ev['ongoing'] ?> sedang diisi
                <?php endif; ?>
              </div>
            </div>
            <div style="font-size:14px;font-weight:500;color:<?= $c ?>"><?= $pct ?>%</div>
          </div>
          <div style="height:5px;border-radius:3px;background:var(--color-background-secondary);overflow:hidden;margin-top:6px">
            <div style="height:100%;width:<?= $pct ?>%;border-radius:3px;background:<?= $c ?>"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Incomplete assignments -->
  <div class="col-md-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">
          <i class="bi bi-clock-history me-2"></i>Belum Selesai
        </span>
        <span class="badge bg-secondary"><?= count($incomplete) ?></span>
      </div>
      <div class="card-body p-0" style="max-height:500px;overflow-y:auto">
        <?php if (empty($incomplete)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-check-circle display-4 mb-2 d-block text-success"></i>
          <p>Semua kuesioner sudah selesai!</p>
        </div>
        <?php else: ?>
        <table class="table table-sm table-hover mb-0 small">
          <thead style="position:sticky;top:0;background:var(--color-background-primary)">
            <tr>
              <th>Yang Dinilai</th>
              <th>Penilai</th>
              <th>Status</th>
              <th class="text-center">Progress</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($incomplete as $inc):
              $pct2 = $inc['total_q'] > 0 ? round($inc['answered']/$inc['total_q']*100) : 0;
            ?>
            <tr>
              <td class="fw-semibold"><?= h($inc['evaluatee_name']) ?></td>
              <td class="text-muted"><?= h($inc['evaluator_name']) ?></td>
              <td>
                <?php if ($inc['status']==='in_progress'): ?>
                <span class="badge bg-warning text-dark" style="font-size:.65rem">Diisi</span>
                <?php else: ?>
                <span class="badge bg-secondary" style="font-size:.65rem">Pending</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($inc['status']==='in_progress'): ?>
                <div style="width:60px;margin:0 auto">
                  <div style="font-size:.65rem;color:var(--color-text-secondary);margin-bottom:2px">
                    <?= $inc['answered'] ?>/<?= $inc['total_q'] ?>
                  </div>
                  <div style="height:4px;border-radius:2px;background:var(--color-background-secondary);overflow:hidden">
                    <div style="height:100%;width:<?= $pct2 ?>%;background:#d97706;border-radius:2px"></div>
                  </div>
                </div>
                <?php else: ?>
                <span style="font-size:.7rem;color:var(--color-text-secondary)">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
pageWrapper('Progress Evaluasi — ' . h($period['name']), $content);
