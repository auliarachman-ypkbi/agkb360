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

// ── Stats umum ───────────────────────────────────────────────
$totalAssign = Database::fetchOne("SELECT COUNT(*) c FROM assignments WHERE period_id=?", [$pid])['c'];
$completedA  = Database::fetchOne("SELECT COUNT(*) c FROM assignments WHERE period_id=? AND status='completed'", [$pid])['c'];
$inProgressA = Database::fetchOne("SELECT COUNT(*) c FROM assignments WHERE period_id=? AND status='in_progress'", [$pid])['c'];
$pendingA    = Database::fetchOne("SELECT COUNT(*) c FROM assignments WHERE period_id=? AND status='pending'", [$pid])['c'];
$completion  = $totalAssign > 0 ? round($completedA/$totalAssign*100) : 0;

// ── Breakdown per kelompok penilai ────────────────
$respBreakdown = Database::fetchAll("
    SELECT p.respondent_type,
           COUNT(*) as total,
           SUM(CASE WHEN a.status='completed' THEN 1 ELSE 0 END) as done,
           ROUND(SUM(CASE WHEN a.status='completed' THEN 1 ELSE 0 END)/COUNT(*)*100,1) as pct
    FROM assignments a
    JOIN packages p ON p.id = a.package_id
    WHERE a.period_id = ? AND p.is_self_reflection = 0
    GROUP BY p.respondent_type ORDER BY pct DESC
", [$pid]);

// ── Partisipasi per kelompok (berapa orang sudah isi) ────────
$peopleParticipation = Database::fetchAll("
    SELECT u.role,
           COUNT(DISTINCT u.id) as total_orang,
           COUNT(DISTINCT CASE WHEN stat.selesai > 0 THEN u.id END) as sudah_isi,
           ROUND(COUNT(DISTINCT CASE WHEN stat.selesai > 0 THEN u.id END)/COUNT(DISTINCT u.id)*100,1) as pct
    FROM users u
    JOIN (
        SELECT a.evaluator_id,
               SUM(CASE WHEN a.status='completed' THEN 1 ELSE 0 END) as selesai
        FROM assignments a
        WHERE a.period_id = ?
        GROUP BY a.evaluator_id
    ) stat ON stat.evaluator_id = u.id
    WHERE u.is_active = 1 AND u.role IN ('leader','teacher','student','parent','foundation')
    GROUP BY u.role ORDER BY pct DESC
", [$pid]);

// ── Filter ───────────────────────────────────────────────────
$roleFilter = $_GET['role'] ?? '';
$searchQ    = trim($_GET['q'] ?? '');

$conditions = ["u.role IN ('leader','teacher')"];
$bindParams = [$pid]; // untuk JOIN ... a.period_id = ?
if ($roleFilter !== '') { $conditions[] = "u.role = ?"; $bindParams[] = $roleFilter; }
if ($searchQ    !== '') { $conditions[] = "u.name LIKE ?"; $bindParams[] = "%$searchQ%"; }
$whereSql = "WHERE " . implode(' AND ', $conditions);

// ── Breakdown per evaluatee per tipe responden (scalable, tidak flat) ──
$rows = Database::fetchAll("
    SELECT u.id, u.name, u.role,
           p.code as pkg_code, p.respondent_type,
           COUNT(a.id) as total,
           SUM(CASE WHEN a.status='completed'   THEN 1 ELSE 0 END) as done,
           SUM(CASE WHEN a.status='in_progress' THEN 1 ELSE 0 END) as ongoing
    FROM users u
    JOIN assignments a ON a.evaluatee_id = u.id AND a.period_id = ?
    JOIN packages p ON p.id = a.package_id
    $whereSql
    GROUP BY u.id, u.name, u.role, p.code, p.respondent_type
    ORDER BY u.role, u.name, p.code
", $bindParams);

// Susun ulang jadi struktur per-person dengan breakdown
$people = [];
foreach ($rows as $r) {
    $uid = $r['id'];
    if (!isset($people[$uid])) {
        $people[$uid] = [
            'id' => $uid, 'name' => $r['name'], 'role' => $r['role'],
            'total' => 0, 'done' => 0, 'ongoing' => 0,
            'breakdown' => [],
        ];
    }
    $people[$uid]['total']   += (int)$r['total'];
    $people[$uid]['done']    += (int)$r['done'];
    $people[$uid]['ongoing'] += (int)$r['ongoing'];
    $people[$uid]['breakdown'][] = [
        'code' => $r['pkg_code'], 'resp' => $r['respondent_type'],
        'total' => (int)$r['total'], 'done' => (int)$r['done'], 'ongoing' => (int)$r['ongoing'],
    ];
}
$people = array_values($people);
usort($people, function($a, $b) {
    $pa = $a['total']>0 ? $a['done']/$a['total'] : 1;
    $pb = $b['total']>0 ? $b['done']/$b['total'] : 1;
    if ($a['role'] !== $b['role']) return $a['role'] === 'leader' ? -1 : 1;
    return $pa <=> $pb;
});

// "Perlu perhatian" — completion paling rendah (top 4)
$needAttention = array_filter($people, fn($p) => $p['total'] > 0 && ($p['done']/$p['total']) < 0.5);
usort($needAttention, fn($a,$b) => ($a['done']/$a['total']) <=> ($b['done']/$b['total']));
$needAttention = array_slice($needAttention, 0, 4);

ob_start(); ?>

<style>
.prog-hero{background:linear-gradient(135deg,#2C5282,#1A365D);color:white;border-radius:16px;padding:28px 32px;margin-bottom:20px;position:relative;overflow:hidden}
.prog-hero::before{content:'';position:absolute;top:-60px;right:-60px;width:220px;height:220px;background:rgba(255,255,255,.04);border-radius:50%}
.hero-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:22px;flex-wrap:wrap;gap:10px}
.hero-title{font-size:18px;font-weight:600;margin-bottom:4px}
.hero-sub{font-size:12px;opacity:.75;display:flex;align-items:center;gap:6px}
.hero-actions{display:flex;gap:8px}
.btn-hero{padding:7px 14px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;border:1px solid rgba(255,255,255,.3);color:#fff;background:rgba(255,255,255,.08)}
.btn-hero:hover{background:rgba(255,255,255,.18);color:#fff}
.btn-hero.danger{border-color:rgba(248,113,113,.5);color:#fecaca}
.btn-hero.danger:hover{background:rgba(248,113,113,.15)}
.hero-bar-row{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px}
.hero-pct{font-size:32px;font-weight:700}
.hero-bar{height:10px;border-radius:6px;background:rgba(255,255,255,.15);overflow:hidden;margin-bottom:22px}
.hero-bar-fill{height:100%;border-radius:6px;transition:width .3s}
.hero-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.hstat{background:rgba(255,255,255,.06);border-radius:10px;padding:12px;text-align:center}
.hstat-val{font-size:22px;font-weight:600}
.hstat-lbl{font-size:11px;opacity:.7;margin-top:2px}

.attn-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
.attn-card{background:#fff;border:1px solid #FADCDC;border-radius:12px;padding:14px 16px;border-left:4px solid #dc2626}
.attn-name{font-size:13px;font-weight:600;color:#1e293b}
.attn-role{font-size:11px;color:#94a3b8;margin-bottom:8px}
.attn-pct{font-size:20px;font-weight:700;color:#dc2626}
.attn-sub{font-size:11px;color:#94a3b8}

.filter-bar{background:#fff;border:0.5px solid #e2e8f0;border-radius:10px;padding:14px 18px;margin-bottom:16px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap}
.filter-bar .f-group{display:flex;flex-direction:column;gap:5px}
.filter-bar label{font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.4px}
.filter-bar select,.filter-bar input{height:36px;border:1px solid #e2e8f0;border-radius:7px;padding:0 11px;font-size:13px;outline:none;min-width:160px}
.filter-bar select:focus,.filter-bar input:focus{border-color:#2C5282;box-shadow:0 0 0 3px rgba(44,82,130,.1)}
.filter-bar .f-search{min-width:240px;flex:1}
.btn-filter{height:36px;padding:0 16px;background:#2C5282;color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.btn-clear{height:36px;width:36px;display:inline-flex;align-items:center;justify-content:center;background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;color:#64748b;text-decoration:none}

.person-list{display:flex;flex-direction:column;gap:8px}
.person-card{background:#fff;border:0.5px solid #e2e8f0;border-radius:12px;overflow:hidden}
.person-row{display:flex;align-items:center;gap:14px;padding:14px 18px;cursor:pointer;transition:background .12s}
.person-row:hover{background:#f8fafc}
.p-avatar{width:38px;height:38px;border-radius:50%;background:#E6F1FB;color:#185FA5;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0}
.p-info{flex:1;min-width:0}
.p-name{font-size:13px;font-weight:600;color:#1e293b}
.p-role{font-size:11px;color:#94a3b8;display:flex;align-items:center;gap:6px;margin-top:1px}
.role-chip{font-size:10px;padding:1px 7px;border-radius:10px;font-weight:600}
.role-chip.leader{background:#E6F1FB;color:#0C447C}
.role-chip.teacher{background:#FAEEDA;color:#633806}
.p-bar-wrap{width:160px;flex-shrink:0}
.p-bar-track{height:6px;border-radius:4px;background:#f1f5f9;overflow:hidden;margin-bottom:4px}
.p-bar-fill{height:100%;border-radius:4px}
.p-bar-label{font-size:10px;color:#94a3b8;display:flex;justify-content:space-between}
.p-pct{font-size:16px;font-weight:700;width:48px;text-align:right;flex-shrink:0}
.p-chevron{color:#cbd5e1;transition:transform .2s;flex-shrink:0}
.person-card.open .p-chevron{transform:rotate(90deg)}
.p-detail{display:none;border-top:0.5px solid #f1f5f9;padding:14px 18px;background:#fafbfc}
.person-card.open .p-detail{display:block}
.detail-row{display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:0.5px solid #f1f5f9}
.detail-row:last-child{border-bottom:none}
.detail-label{flex:1;font-size:12px;color:#475569}
.detail-label .pkg-code{display:inline-block;background:#2C5282;color:#fff;font-size:10px;font-weight:600;padding:2px 7px;border-radius:5px;margin-right:6px}
.detail-bar-wrap{width:110px}
.detail-bar-track{height:5px;border-radius:3px;background:#e2e8f0;overflow:hidden}
.detail-bar-fill{height:100%;border-radius:3px}
.detail-count{font-size:11px;color:#94a3b8;width:54px;text-align:right}
.detail-link{font-size:11px;color:#185FA5;text-decoration:none;width:70px;text-align:right;flex-shrink:0}
.detail-link:hover{text-decoration:underline}
.empty-state{text-align:center;padding:50px;color:#94a3b8;font-size:13px}
</style>

<!-- HERO -->
<div class="prog-hero">
  <div class="hero-top">
    <div>
      <div class="hero-title">Progress Evaluasi Berjalan</div>
      <div class="hero-sub">
        <i class="bi bi-circle-fill" style="font-size:.5rem;color:#4ade80"></i>
        <?= h($period['name']) ?>
        <?php if ($period['end_date']): ?>
        · Berakhir <?= date('d M Y', strtotime($period['end_date'])) ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="hero-actions">
      <a href="<?= APP_URL ?>/dashboard/" class="btn-hero"><i class="bi bi-arrow-left"></i>Dashboard</a>
      <a href="<?= APP_URL ?>/admin/periods.php?close=<?= $pid ?>" class="btn-hero danger"
         onclick="return confirm('Tutup periode ini?')"><i class="bi bi-archive"></i>Tutup Periode</a>
    </div>
  </div>

  <div class="hero-bar-row">
    <span style="font-size:13px;opacity:.8">Progress keseluruhan</span>
    <span class="hero-pct"><?= $completion ?>%</span>
  </div>
  <div class="hero-bar">
    <div class="hero-bar-fill" style="width:<?= $completion ?>%;background:<?= $completion>=80?'#4ade80':($completion>=50?'#fbbf24':'#f87171') ?>"></div>
  </div>

  <div class="hero-stats">
    <div class="hstat"><div class="hstat-val"><?= $totalAssign ?></div><div class="hstat-lbl">Total penugasan</div></div>
    <div class="hstat"><div class="hstat-val" style="color:#4ade80"><?= $completedA ?></div><div class="hstat-lbl">Selesai</div></div>
    <div class="hstat"><div class="hstat-val" style="color:#fbbf24"><?= $inProgressA ?></div><div class="hstat-lbl">Sedang diisi</div></div>
    <div class="hstat"><div class="hstat-val" style="color:#f87171"><?= $pendingA ?></div><div class="hstat-lbl">Belum mulai</div></div>
  </div>
</div>

<!-- BREAKDOWN PER KELOMPOK PENILAI -->
<?php if (!empty($respBreakdown)): ?>
<div style="background:#fff;border:0.5px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:20px">
  <div style="padding:14px 18px;border-bottom:0.5px solid #e2e8f0;font-size:13px;font-weight:600;color:#1e293b">
    <i class="bi bi-people-fill me-2" style="color:#2C5282"></i>Progress per Kelompok Penilai
  </div>
  <div style="padding:16px 18px;display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px">
    <?php foreach ($respBreakdown as $rb):
      $rc = $rb['pct']>=80?'#16a34a':($rb['pct']>=50?'#d97706':'#dc2626');
    ?>
    <div style="background:#f8fafc;border-radius:10px;padding:14px 16px;border:1px solid #e2e8f0">
      <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px"><?= h(respondentLabel($rb['respondent_type'])) ?></div>
      <div style="font-size:26px;font-weight:700;color:<?= $rc ?>;line-height:1"><?= $rb['pct'] ?>%</div>
      <div style="font-size:11px;color:#94a3b8;margin-top:4px"><?= $rb['done'] ?>/<?= $rb['total'] ?> penugasan</div>
      <div style="margin-top:8px;height:4px;border-radius:2px;background:#e2e8f0;overflow:hidden">
        <div style="height:100%;border-radius:2px;background:<?= $rc ?>;width:<?= $rb['pct'] ?>%"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- PARTISIPASI PER KELOMPOK -->
<?php if (!empty($peopleParticipation)): ?>
<div style="background:#fff;border:0.5px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:20px">
  <div style="padding:14px 18px;border-bottom:0.5px solid #e2e8f0;font-size:13px;font-weight:600;color:#1e293b">
    <i class="bi bi-person-check-fill me-2" style="color:#2C5282"></i>Partisipasi per Kelompok
    <span style="font-size:11px;font-weight:400;color:#94a3b8;margin-left:4px">— berapa orang yang sudah mulai mengisi</span>
  </div>
  <div style="padding:16px 18px;display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px">
    <?php foreach ($peopleParticipation as $pp):
      $rc = $pp['pct']>=80?'#16a34a':($pp['pct']>=50?'#d97706':'#dc2626');
    ?>
    <div style="background:#f8fafc;border-radius:10px;padding:14px 16px;border:1px solid #e2e8f0">
      <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px"><?= h(roleLabel($pp['role'])) ?></div>
      <div style="font-size:26px;font-weight:700;color:<?= $rc ?>;line-height:1"><?= $pp['pct'] ?>%</div>
      <div style="font-size:11px;color:#94a3b8;margin-top:4px"><?= $pp['sudah_isi'] ?>/<?= $pp['total_orang'] ?> orang</div>
      <div style="margin-top:8px;height:4px;border-radius:2px;background:#e2e8f0;overflow:hidden">
        <div style="height:100%;border-radius:2px;background:<?= $rc ?>;width:<?= $pp['pct'] ?>%"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- PERLU PERHATIAN -->
<?php if (!empty($needAttention)): ?>
<div style="font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">
  <i class="bi bi-exclamation-triangle-fill me-1" style="color:#dc2626"></i>Perlu Perhatian — Progress di Bawah 50%
</div>
<div class="attn-row">
  <?php foreach ($needAttention as $na): $pct = round($na['done']/$na['total']*100); ?>
  <div class="attn-card">
    <div class="attn-name"><?= h($na['name']) ?></div>
    <div class="attn-role"><?= h(roleLabel($na['role'])) ?></div>
    <div class="attn-pct"><?= $pct ?>%</div>
    <div class="attn-sub"><?= $na['done'] ?>/<?= $na['total'] ?> selesai</div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- FILTER -->
<form method="GET" class="filter-bar">
  <div class="f-group">
    <label>Role</label>
    <select name="role" onchange="this.form.submit()">
      <option value="">Semua Role</option>
      <option value="leader" <?= $roleFilter==='leader'?'selected':'' ?>>Pimpinan</option>
      <option value="teacher" <?= $roleFilter==='teacher'?'selected':'' ?>>Guru</option>
    </select>
  </div>
  <div class="f-group f-search">
    <label>Cari Nama</label>
    <input type="text" name="q" placeholder="Cari nama..." value="<?= h($searchQ) ?>">
  </div>
  <button type="submit" class="btn-filter"><i class="bi bi-funnel"></i>Filter</button>
  <?php if ($roleFilter || $searchQ): ?>
  <a href="?" class="btn-clear" title="Reset"><i class="bi bi-x-lg"></i></a>
  <?php endif; ?>
</form>

<!-- PERSON LIST -->
<?php if (empty($people)): ?>
<div class="person-card"><div class="empty-state">
  <i class="bi bi-inbox" style="font-size:28px;display:block;margin-bottom:8px;opacity:.4"></i>
  Tidak ada data yang cocok dengan filter ini
</div></div>
<?php else: ?>
<div class="person-list">
  <?php foreach ($people as $i => $p):
    $pct = $p['total'] > 0 ? round($p['done']/$p['total']*100) : 0;
    $barColor = $pct>=80?'#16a34a':($pct>=50?'#d97706':'#dc2626');
  ?>
  <div class="person-card" id="pcard<?= $i ?>">
    <div class="person-row" onclick="document.getElementById('pcard<?= $i ?>').classList.toggle('open')">
      <div class="p-avatar"><?= h(avatarInitials($p['name'])) ?></div>
      <div class="p-info">
        <div class="p-name"><?= h($p['name']) ?></div>
        <div class="p-role">
          <span class="role-chip <?= $p['role'] ?>"><?= h(roleLabel($p['role'])) ?></span>
          <?= $p['done'] ?>/<?= $p['total'] ?> selesai
          <?php if ($p['ongoing'] > 0): ?>· <?= $p['ongoing'] ?> sedang diisi<?php endif; ?>
        </div>
      </div>
      <div class="p-bar-wrap">
        <div class="p-bar-track"><div class="p-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>"></div></div>
      </div>
      <div class="p-pct" style="color:<?= $barColor ?>"><?= $pct ?>%</div>
      <i class="bi bi-chevron-right p-chevron"></i>
    </div>
    <div class="p-detail">
      <?php foreach ($p['breakdown'] as $b):
        $bpct = $b['total'] > 0 ? round($b['done']/$b['total']*100) : 0;
        $bColor = $bpct>=80?'#16a34a':($bpct>=50?'#d97706':'#dc2626');
        $drillUrl = APP_URL . '/admin/assignments.php?role=' . urlencode($p['role'])
                  . '&resp=' . urlencode($b['resp']) . '&evaluatee_id=' . (int)$p['id'];
      ?>
      <div class="detail-row">
        <div class="detail-label">
          <span class="pkg-code"><?= h($b['code']) ?></span><?= h(respondentLabel($b['resp'])) ?>
        </div>
        <div class="detail-bar-wrap">
          <div class="detail-bar-track"><div class="detail-bar-fill" style="width:<?= $bpct ?>%;background:<?= $bColor ?>"></div></div>
        </div>
        <div class="detail-count"><?= $b['done'] ?>/<?= $b['total'] ?></div>
        <a href="<?= h($drillUrl) ?>" class="detail-link">Lihat detail →</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
pageWrapper('Progress Evaluasi — ' . h($period['name']), $content);