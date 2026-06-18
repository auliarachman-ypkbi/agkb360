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

$evaluatorId = (int)($_GET['id'] ?? 0);
$evaluator = $evaluatorId ? Database::fetchOne("SELECT * FROM users WHERE id=?", [$evaluatorId]) : null;

if (!$evaluator) {
    flash('Pengguna tidak ditemukan.', 'danger');
    header('Location: ' . APP_URL . '/admin/progress.php');
    exit;
}

// ── Filter status ────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? '';
$whereSql = "WHERE a.evaluator_id = ? AND a.period_id = ?";
$qParams  = [$evaluatorId, $pid];
if ($statusFilter !== '') { $whereSql .= " AND a.status = ?"; $qParams[] = $statusFilter; }

$assignments = Database::fetchAll("
    SELECT a.*, u_ee.name as evaluatee_name, u_ee.role as evaluatee_role,
           p.code as pkg_code, p.name as pkg_name, p.respondent_type,
           (SELECT COUNT(*) FROM responses r WHERE r.assignment_id=a.id) as answered,
           (SELECT COUNT(*) FROM package_questions pq WHERE pq.package_id=a.package_id) as total_q
    FROM assignments a
    JOIN users u_ee ON u_ee.id = a.evaluatee_id
    JOIN packages p ON p.id = a.package_id
    $whereSql
    ORDER BY a.status, u_ee.name
", $qParams);

// ── Stats keseluruhan (tanpa filter status) ─────────────────
$summaryRows = Database::fetchAll(
    "SELECT status, COUNT(*) c FROM assignments WHERE evaluator_id=? AND period_id=? GROUP BY status",
    [$evaluatorId, $pid]
);
$sumMap = [];
foreach ($summaryRows as $s) { $sumMap[$s['status']] = (int)$s['c']; }
$total      = array_sum($sumMap);
$completion = $total > 0 ? round((($sumMap['completed'] ?? 0) / $total) * 100) : 0;

function buildPovQS(int $id, array $overrides = []): string {
    $base = ['status' => $_GET['status'] ?? ''];
    $merged = array_filter(array_merge($base, $overrides), fn($v) => $v !== '' && $v !== null);
    return '?id=' . $id . (empty($merged) ? '' : '&' . http_build_query($merged));
}

ob_start(); ?>

<style>
.pov-hero{background:linear-gradient(135deg,#2C5282,#1A365D);color:#fff;border-radius:16px;padding:24px 28px;margin-bottom:20px;display:flex;align-items:center;gap:18px}
.pov-avatar{width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;flex-shrink:0}
.pov-info{flex:1}
.pov-name{font-size:18px;font-weight:600}
.pov-sub{font-size:12px;opacity:.75;margin-top:2px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.pov-role-chip{font-size:10px;padding:2px 9px;border-radius:10px;font-weight:600;background:rgba(255,255,255,.15)}
.pov-pct{font-size:32px;font-weight:700;text-align:right}
.pov-pct-lbl{font-size:11px;opacity:.7;text-align:right}
.back-link{font-size:12px;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:5px;margin-bottom:14px}
.back-link:hover{color:#2C5282}

.stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px}
.stat-box{background:#fff;border:0.5px solid #e2e8f0;border-radius:10px;padding:14px;text-align:center}
.stat-box-val{font-size:24px;font-weight:600}
.stat-box-lbl{font-size:11px;color:#94a3b8;margin-top:2px}

.status-tabs{display:flex;gap:6px;margin-bottom:14px}
.status-tab{padding:6px 14px;border-radius:20px;font-size:12px;font-weight:500;border:0.5px solid #e2e8f0;background:#fff;color:#64748b;text-decoration:none}
.status-tab.active{background:#2C5282;color:#fff;border-color:#2C5282}

.pov-table{width:100%;border-collapse:collapse;font-size:13px;background:#fff;border:0.5px solid #e2e8f0;border-radius:12px;overflow:hidden}
.pov-table thead th{background:#f8fafc;color:#475569;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;padding:11px 16px;text-align:left;border-bottom:1px solid #e2e8f0}
.pov-table tbody td{padding:12px 16px;vertical-align:middle;color:#1e293b;border-bottom:0.5px solid #f1f5f9}
.pov-table tbody tr:hover{background:#f8fafc}
.pov-table tbody tr:last-child td{border-bottom:none}
.name-primary{font-weight:600;color:#1e293b;font-size:13px}
.role-sub{font-size:11px;color:#94a3b8;margin-top:2px}
.pkg-badge{display:inline-block;background:#2C5282;color:#fff;font-size:11px;font-weight:600;padding:3px 10px;border-radius:6px}
.resp-sub{font-size:11px;color:#94a3b8;margin-top:4px;display:block}
.mini-bar-wrap{width:70px}
.mini-bar-track{height:5px;border-radius:3px;background:#e2e8f0;overflow:hidden;margin-bottom:3px}
.mini-bar-fill{height:100%;border-radius:3px;background:#d97706}
.mini-bar-lbl{font-size:10px;color:#94a3b8}
.action-icon-btn{width:30px;height:30px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;background:#fff;color:#64748b;text-decoration:none}
.action-icon-btn.view:hover{background:#E6F1FB;border-color:#B5D4F4;color:#185FA5}
.empty-state{text-align:center;padding:50px;color:#94a3b8;font-size:13px}
</style>

<a href="javascript:history.back()" class="back-link"><i class="bi bi-arrow-left"></i>Kembali</a>

<!-- HERO -->
<div class="pov-hero">
  <div class="pov-avatar"><?= h(avatarInitials($evaluator['name'])) ?></div>
  <div class="pov-info">
    <div class="pov-name"><?= h($evaluator['name']) ?></div>
    <div class="pov-sub">
      <span class="pov-role-chip"><?= h(roleLabel($evaluator['role'])) ?></span>
      <?= h($evaluator['email']) ?>
    </div>
  </div>
  <div>
    <div class="pov-pct"><?= $completion ?>%</div>
    <div class="pov-pct-lbl">selesai mengisi</div>
  </div>
</div>

<!-- STATS -->
<div class="stat-row">
  <div class="stat-box"><div class="stat-box-val"><?= $total ?></div><div class="stat-box-lbl">Total Tugas</div></div>
  <div class="stat-box"><div class="stat-box-val" style="color:#16a34a"><?= $sumMap['completed'] ?? 0 ?></div><div class="stat-box-lbl">Selesai</div></div>
  <div class="stat-box"><div class="stat-box-val" style="color:#dc2626"><?= ($sumMap['pending'] ?? 0) + ($sumMap['in_progress'] ?? 0) ?></div><div class="stat-box-lbl">Belum Selesai</div></div>
</div>

<!-- STATUS TABS -->
<div class="status-tabs">
  <?php foreach ([''=>'Semua','pending'=>'Menunggu','in_progress'=>'Proses','completed'=>'Selesai'] as $s => $l): ?>
  <a href="<?= buildPovQS($evaluatorId, ['status'=>$s]) ?>" class="status-tab <?= $statusFilter===$s?'active':'' ?>"><?= $l ?></a>
  <?php endforeach; ?>
</div>

<!-- TABLE -->
<table class="pov-table">
  <thead><tr>
    <th>Yang Dinilai</th><th>Paket</th><th>Status</th><th>Progress</th><th>Deadline</th><th style="text-align:center">Aksi</th>
  </tr></thead>
  <tbody>
    <?php if (empty($assignments)): ?>
    <tr><td colspan="6" class="empty-state">
      <i class="bi bi-inbox" style="font-size:28px;display:block;margin-bottom:8px;opacity:.4"></i>
      Tidak ada tugas untuk status ini
    </td></tr>
    <?php else: ?>
    <?php foreach ($assignments as $a):
      $apct = $a['total_q'] > 0 ? round($a['answered']/$a['total_q']*100) : 0;
    ?>
    <tr>
      <td>
        <div class="name-primary"><?= h($a['evaluatee_name']) ?></div>
        <div class="role-sub"><?= h(roleLabel($a['evaluatee_role'])) ?></div>
      </td>
      <td>
        <span class="pkg-badge"><?= h($a['pkg_code']) ?></span>
        <span class="resp-sub"><?= h(respondentLabel($a['respondent_type'])) ?></span>
      </td>
      <td><?= statusBadge($a['status']) ?></td>
      <td>
        <?php if ($a['status'] === 'in_progress'): ?>
        <div class="mini-bar-wrap">
          <div class="mini-bar-track"><div class="mini-bar-fill" style="width:<?= $apct ?>%"></div></div>
          <div class="mini-bar-lbl"><?= $a['answered'] ?>/<?= $a['total_q'] ?></div>
        </div>
        <?php else: ?>
        <span style="color:#cbd5e1;font-size:12px">—</span>
        <?php endif; ?>
      </td>
      <td style="font-size:12px;color:#475569;white-space:nowrap"><?= $a['due_date'] ? date('d M Y', strtotime($a['due_date'])) : '—' ?></td>
      <td style="text-align:center">
        <?php if ($a['status'] === 'completed'): ?>
        <a href="<?= APP_URL ?>/survey/fill.php?id=<?= $a['id'] ?>&view=1" class="action-icon-btn view" title="Lihat jawaban">
          <i class="bi bi-eye"></i>
        </a>
        <?php else: ?>
        <span style="color:#cbd5e1">—</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

<?php
$content = ob_get_clean();
pageWrapper('POV Penilai — ' . h($evaluator['name']), $content);
