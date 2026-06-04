<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin','admin','foundation','leader']);

// ── FETCH SEMUA ORANG YANG PERNAH DIEVALUASI ─────────────────
$allPersons = Database::fetchAll("
    SELECT DISTINCT u.id, u.name, u.role
    FROM users u
    JOIN assignments a ON a.evaluatee_id = u.id
    JOIN eval_periods ep ON ep.id = a.period_id
    WHERE u.role IN ('leader','teacher') AND u.is_active=1 AND ep.status='closed'
    ORDER BY u.role, u.name
");

// ── SEMUA PERIODE CLOSED ──────────────────────────────────────
$allPeriods = Database::fetchAll("
    SELECT * FROM eval_periods WHERE status='closed' ORDER BY start_date ASC, id ASC
");

$selectedUid = (int)($_GET['user_id'] ?? 0);
$selectedPid = (int)($_GET['period_id'] ?? 0);
$activeTab   = $_GET['tab'] ?? 'trend';

$selectedUser = $selectedUid
    ? Database::fetchOne("SELECT * FROM users WHERE id=?", [$selectedUid])
    : null;

// Default period = yang terakhir
if ($selectedUid && !$selectedPid && !empty($allPeriods)) {
    $selectedPid = end($allPeriods)['id'];
}

// ── DATA TREN PER PERIODE (untuk chart) ──────────────────────
$trendData = [];
$sourceData = [];
if ($selectedUid) {
    foreach ($allPeriods as $p) {
        $s = calculateScores($selectedUid, $p['id']);
        $trendData[$p['id']] = [
            'period_name' => $p['name'],
            'overall'     => $s['overall'],
            'byDomain'    => $s['byDomain'],
        ];

        // Skor per sumber responden
        $srcRows = Database::fetchAll("
            SELECT pkg.respondent_type, ROUND(AVG(r.grade),2) as avg
            FROM assignments a
            JOIN packages pkg ON pkg.id = a.package_id
            JOIN responses r ON r.assignment_id = a.id
            WHERE a.evaluatee_id = ? AND a.period_id = ?
              AND a.status = 'completed' AND pkg.is_self_reflection = 0
            GROUP BY pkg.respondent_type
        ", [$selectedUid, $p['id']]);
        $sourceData[$p['id']] = $srcRows;
    }
}

// ── SKOR DETAIL PERIODE TERPILIH ─────────────────────────────
$scores     = [];
$selfScores = [];
$byDomainAccordion = [];

if ($selectedUid && $selectedPid) {
    $scores = calculateScores($selectedUid, $selectedPid);

    // Self-reflection scores (tidak masuk calculateScores)
    $selfOverall = Database::fetchOne("
        SELECT ROUND(AVG(r.grade),2) as avg
        FROM assignments a
        JOIN packages p ON p.id = a.package_id
        JOIN responses r ON r.assignment_id = a.id
        WHERE a.evaluatee_id = ? AND a.period_id = ? AND p.is_self_reflection = 1
    ", [$selectedUid, $selectedPid]);

    $selfDomains = Database::fetchAll("
        SELECT d.name as domain_name, ROUND(AVG(r.grade),2) as avg
        FROM assignments a
        JOIN packages p ON p.id = a.package_id
        JOIN responses r ON r.assignment_id = a.id
        JOIN questions q ON q.id = r.question_id
        JOIN standards s ON s.id = q.standard_id
        JOIN domains d ON d.id = s.domain_id
        WHERE a.evaluatee_id = ? AND a.period_id = ? AND p.is_self_reflection = 1
        GROUP BY d.id, d.name ORDER BY d.id
    ", [$selectedUid, $selectedPid]);

    $selfScores = [
        'overall' => (float)($selfOverall['avg'] ?? 0),
        'byDomain' => $selfDomains,
    ];

    if (!empty($scores['byStandard'])) {
        foreach ($scores['byStandard'] as $sid => $s) {
            $byDomainAccordion[$s['domain_name']][] = array_merge($s, ['sid'=>$sid]);
        }
    }
}

// Sort traits
$sortedTraits = !empty($scores['byTrait'])
    ? array_values($scores['byTrait'])
    : [];
usort($sortedTraits, fn($a,$b) => $b['avg'] <=> $a['avg']);

// Period navigator
$periodIds = array_column($allPeriods, 'id');
$curPIdx   = $selectedPid ? array_search($selectedPid, $periodIds) : false;
$prevPid   = ($curPIdx !== false && $curPIdx > 0) ? $periodIds[$curPIdx-1] : null;
$nextPid   = ($curPIdx !== false && $curPIdx < count($periodIds)-1) ? $periodIds[$curPIdx+1] : null;
$curPeriod = $selectedPid ? Database::fetchOne("SELECT * FROM eval_periods WHERE id=?", [$selectedPid]) : null;

// Diff skor dari periode sebelumnya
$scoreDiff = null;
if ($selectedUid && $prevPid) {
    $prevScores = $trendData[$prevPid] ?? null;
    if ($prevScores && $prevScores['overall'] > 0 && ($scores['overall'] ?? 0) > 0) {
        $scoreDiff = round($scores['overall'] - $prevScores['overall'], 2);
    }
}

// AI suggestion
$aiSuggestion = ($selectedUid && $selectedPid)
    ? Database::fetchOne("SELECT * FROM ai_suggestions WHERE evaluatee_id=? AND period_id=?", [$selectedUid, $selectedPid])
    : null;

// JS data
$jsTrend = [];
foreach ($allPeriods as $p) {
    $jsTrend[] = [
        'id'     => $p['id'],
        'name'   => $p['name'],
        'score'  => $trendData[$p['id']]['overall'] ?? 0,
    ];
}

// ── SELF TREND DATA ──────────────────────────────────────────
$jsSelfTrend = [];
if ($selectedUid) {
    foreach ($allPeriods as $p) {
        $sr = Database::fetchOne("
            SELECT ROUND(AVG(r.grade),2) as avg
            FROM assignments a
            JOIN packages pkg ON pkg.id = a.package_id
            JOIN responses r ON r.assignment_id = a.id
            WHERE a.evaluatee_id = ? AND a.period_id = ?
              AND pkg.is_self_reflection = 1 AND a.evaluatee_id = a.evaluator_id
        ", [$selectedUid, $p['id']]);
        $jsSelfTrend[] = [
            'id'    => $p['id'],
            'name'  => $p['name'],
            'score' => (float)($sr['avg'] ?? 0),
        ];
    }
}

ob_start(); ?>

<style>
.page-wrap{display:flex;flex-direction:column;gap:16px}
.top-selector{background:#ffffff;border:1px solid #e2e8f0;border-left:3px solid #2C5282;border-radius:12px;padding:12px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.person-sel{height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 12px;font-size:13px;background:#f8fafc;color:#1e293b;min-width:280px;cursor:pointer}
.period-nav{background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:10px 16px;display:flex;align-items:center;gap:12px}
.nav-btn{width:32px;height:32px;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:18px;text-decoration:none;line-height:1}
.nav-btn:hover{border-color:#2C5282;color:#2C5282;background:#EBF4FF}
.nav-btn.disabled{opacity:.35;pointer-events:none}
.period-name{flex:1;text-align:center;font-size:13px;font-weight:500;color:#1e293b}
.tab-row{display:flex;gap:6px}
.tab-lnk{padding:6px 18px;border-radius:20px;font-size:12px;font-weight:500;border:1px solid #e2e8f0;background:#fff;color:#64748b;text-decoration:none;transition:all .15s}
.tab-lnk.active{background:#2C5282;color:#fff;border-color:#2C5282}
.metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.mc{background:#f8fafc;border-radius:10px;padding:12px 14px;border:1px solid #e2e8f0}
.mc-val{font-size:22px;font-weight:500;color:#1e293b;line-height:1.1}
.mc-lbl{font-size:11px;color:#64748b;margin-top:3px}
.mc-sub{font-size:11px;margin-top:4px}
.up{color:#3B6D11}.down{color:#A32D2D}.neutral{color:#64748b}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.dcard{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden}
.dcard-hdr{padding:10px 16px;font-size:12px;font-weight:600;color:#1e293b;border-bottom:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:space-between;align-items:center}
.dcard-body{padding:14px}
.bar-row{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.bar-row:last-child{margin-bottom:0}
.bar-lbl{font-size:11px;color:#64748b;width:90px;flex-shrink:0}
.bar-track{flex:1;height:8px;background:#f1f5f9;border-radius:4px;overflow:hidden}
.bar-fill{height:100%;border-radius:4px;transition:width .6s}
.bar-val{font-size:11px;font-weight:500;color:#1e293b;width:36px;text-align:right}
.self-banner{background:#f5f3ff;border:1px solid #ddd6fe;border-left:3px solid #7c3aed;border-radius:10px;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.accordion-domain{border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:8px}
.accordion-hdr{padding:10px 14px;background:#f8fafc;cursor:pointer;font-size:13px;font-weight:500;color:#1e293b;display:flex;justify-content:space-between;align-items:center;border:none;width:100%;text-align:left}
.accordion-hdr:hover{background:#f1f5f9}
.accordion-body{display:none;border-top:1px solid #e2e8f0}
.accordion-body.open{display:block}
.std-table{width:100%;font-size:12px;border-collapse:collapse}
.std-table td{padding:6px 14px;border-bottom:0.5px solid #f1f5f9}
.std-table tr:last-child td{border-bottom:none}
.chip{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:500}
.chip-green{background:#EAF3DE;color:#27500A}
.chip-blue{background:#E6F1FB;color:#0C447C}
.chip-amber{background:#FAEEDA;color:#633806}
.chip-red{background:#FCEBEB;color:#791F1F}
.empty-hint{text-align:center;padding:48px 20px;color:#64748b;background:#fff;border:1px solid #e2e8f0;border-radius:12px}
</style>

<div class="page-wrap">

  <!-- SELECTOR ORANG -->
  <div class="top-selector">
    <i class="bi bi-person-fill" style="color:#2C5282;font-size:16px"></i>
    <label style="font-size:12px;font-weight:600;color:#475569;white-space:nowrap">Analisis per orang:</label>
    <form method="GET" style="display:flex;align-items:center;gap:8px;flex:1">
      <?php if ($selectedPid): ?>
      <input type="hidden" name="period_id" value="<?= $selectedPid ?>">
      <?php endif; ?>
      <input type="hidden" name="tab" value="<?= $activeTab ?>">
      <select name="user_id" class="person-sel" onchange="this.form.submit()">
        <option value="">— Pilih nama —</option>
        <?php
        $lastRole = '';
        foreach ($allPersons as $p):
          if ($p['role'] !== $lastRole):
            if ($lastRole) echo '</optgroup>';
            echo '<optgroup label="' . h(roleLabel($p['role'])) . '">';
            $lastRole = $p['role'];
          endif;
        ?>
        <option value="<?= $p['id'] ?>" <?= $p['id']==$selectedUid?'selected':'' ?>>
          <?= h($p['name']) ?>
        </option>
        <?php endforeach; if ($lastRole) echo '</optgroup>'; ?>
      </select>
      <?php if ($selectedUser): ?>
      <span class="chip chip-blue">● <?= h($selectedUser['name']) ?></span>
      <?php endif; ?>
    </form>
  </div>

  <?php if (!$selectedUser): ?>
  <div class="empty-hint">
    <i class="bi bi-person-lines-fill" style="font-size:40px;display:block;margin-bottom:12px;opacity:.3"></i>
    <p style="font-size:14px">Pilih nama dari dropdown untuk melihat laporan tren kinerja</p>
  </div>

  <?php else: ?>

  <!-- TAB -->
  <div class="tab-row">
    <a href="?user_id=<?= $selectedUid ?>&period_id=<?= $selectedPid ?>&tab=trend"
       class="tab-lnk <?= $activeTab==='trend'?'active':'' ?>">
      <i class="bi bi-graph-up me-1"></i>Tren Kinerja
    </a>
    <a href="?user_id=<?= $selectedUid ?>&period_id=<?= $selectedPid ?>&tab=self"
       class="tab-lnk <?= $activeTab==='self'?'active':'' ?>">
      <i class="bi bi-journal-text me-1"></i>Refleksi Mandiri
    </a>
    <a href="?user_id=<?= $selectedUid ?>&period_id=<?= $selectedPid ?>&tab=detail"
       class="tab-lnk <?= $activeTab==='detail'?'active':'' ?>">
      <i class="bi bi-list-check me-1"></i>Detail Standard
    </a>
  </div>

  <?php if ($activeTab === 'trend'): ?>

  <!-- PERIOD NAVIGATOR -->
  <div class="period-nav">
    <a href="<?= $prevPid?"?user_id=$selectedUid&period_id=$prevPid&tab=trend":'#' ?>"
       class="nav-btn <?= !$prevPid?'disabled':'' ?>">‹</a>
    <div class="period-name">
      <?= $curPeriod ? h($curPeriod['name']) : '—' ?>
    </div>
    <a href="<?= $nextPid?"?user_id=$selectedUid&period_id=$nextPid&tab=trend":'#' ?>"
       class="nav-btn <?= !$nextPid?'disabled':'' ?>">›</a>
  </div>

  <!-- METRIC CARDS -->
  <div class="metrics">
    <div class="mc">
      <div class="mc-val"><?= $scores['overall']>0 ? number_format($scores['overall'],2) : '—' ?></div>
      <div class="mc-lbl">Skor periode ini</div>
      <?php if ($scoreDiff !== null): ?>
      <div class="mc-sub <?= $scoreDiff>=0?'up':'down' ?>">
        <?= $scoreDiff>=0?'↑ +':'↓ ' ?><?= number_format(abs($scoreDiff),2) ?> dari sebelumnya
      </div>
      <?php else: ?>
      <div class="mc-sub neutral">Periode pertama</div>
      <?php endif; ?>
    </div>
    <div class="mc">
      <div class="mc-val"><?= count($allPeriods) ?></div>
      <div class="mc-lbl">Periode dievaluasi</div>
      <div class="mc-sub neutral"><?= !empty($allPeriods)?h($allPeriods[0]['name']).' → '.h(end($allPeriods)['name']):'—' ?></div>
    </div>
    <div class="mc">
      <?php
      $totalResponden = Database::fetchOne("
          SELECT COUNT(DISTINCT a.evaluator_id) c FROM assignments a
          WHERE a.evaluatee_id=? AND a.period_id=? AND a.status='completed'
      ", [$selectedUid, $selectedPid])['c'] ?? 0;
      ?>
      <div class="mc-val"><?= $totalResponden ?></div>
      <div class="mc-lbl">Total responden</div>
      <div class="mc-sub neutral">periode ini</div>
    </div>
    <div class="mc">
      <?php
      $scores_arr = array_filter(array_column($jsTrend, 'score'), fn($v)=>$v>0);
      $trendDir = count($scores_arr)>=2
          ? (end($scores_arr) >= reset($scores_arr) ? 'up' : 'down')
          : 'neutral';
      ?>
      <div class="mc-val"><?= $trendDir==='up'?'↑':($trendDir==='down'?'↓':'—') ?></div>
      <div class="mc-lbl">Tren keseluruhan</div>
      <div class="mc-sub <?= $trendDir ?>">
        <?= $trendDir==='up'?'Meningkat':($trendDir==='down'?'Menurun':'Stabil') ?>
      </div>
    </div>
  </div>

  <!-- LEGEND -->
  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:10px 16px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
    <span style="font-size:11px;font-weight:600;color:#64748b;margin-right:4px">Skala:</span>
    <span class="chip" style="background:#EAF3DE;color:#27500A;border:1px solid #c0dd97">≥ 3.75 Sempurna</span>
    <span class="chip" style="background:#E6F1FB;color:#0C447C;border:1px solid #b5d4f4">≥ 3.25 Sangat Baik</span>
    <span class="chip" style="background:#dbeafe;color:#1e40af;border:1px solid #93c5fd">≥ 2.75 Baik</span>
    <span class="chip" style="background:#FAEEDA;color:#633806;border:1px solid #fac775">≥ 2.25 Cukup</span>
    <span class="chip" style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5">≥ 1.75 Kurang</span>
    <span class="chip" style="background:#FCEBEB;color:#791F1F;border:1px solid #f7c1c1">&lt; 1.75 Sangat Kurang</span>
  </div>

  <!-- TREN + TRAIT + DOMAIN -->
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
    <div class="dcard" style="border-left:3px solid #185FA5">
      <div class="dcard-hdr">
        Tren skor keseluruhan
        <?php if($trendDir==='up'): ?>
        <span class="chip chip-green">Meningkat</span>
        <?php elseif($trendDir==='down'): ?>
        <span class="chip chip-red">Menurun</span>
        <?php endif; ?>
      </div>
      <div class="dcard-body">
        <div style="position:relative;height:160px">
          <canvas id="trendChart"></canvas>
        </div>
      </div>
    </div>

    <div class="dcard" style="border-left:3px solid #533AB7">
      <div class="dcard-hdr">Skor per trait — periode ini</div>
      <div class="dcard-body">
        <?php if (!empty($sortedTraits)): ?>
        <?php foreach ($sortedTraits as $t):
          $tl = getScoreLevel($t['avg']);
        ?>
        <div class="bar-row">
          <div class="bar-lbl"><?= h($t['name']) ?></div>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?= round(($t['avg']/4)*100) ?>%;background:#533AB7"></div>
          </div>
          <div class="bar-val"><?= number_format($t['avg'],2) ?></div>
          <span class="chip" style="background:<?= $tl['bg'] ?>;color:<?= $tl['color'] ?>;border:1px solid <?= $tl['color'] ?>;font-size:10px;white-space:nowrap"><?= $tl['label_id'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <p style="font-size:12px;color:#64748b;text-align:center;padding:20px 0">Belum ada data</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="dcard" style="border-left:3px solid #854F0B">
      <div class="dcard-hdr">Skor per domain — periode ini</div>
      <div class="dcard-body">
        <?php if (!empty($scores['byDomain'])): ?>
        <?php foreach ($scores['byDomain'] as $d):
          $dl = getScoreLevel($d['avg']);
        ?>
        <div class="bar-row">
          <div class="bar-lbl"><?= h($d['name']) ?></div>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?= round(($d['avg']/4)*100) ?>%;background:#854F0B"></div>
          </div>
          <div class="bar-val"><?= number_format($d['avg'],2) ?></div>
          <span class="chip" style="background:<?= $dl['bg'] ?>;color:<?= $dl['color'] ?>;border:1px solid <?= $dl['color'] ?>;font-size:10px;white-space:nowrap"><?= $dl['label_id'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <p style="font-size:12px;color:#64748b;text-align:center;padding:20px 0">Belum ada data</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- SUMBER PENILAIAN -->
  <div class="dcard">
    <div class="dcard-hdr">Breakdown per sumber penilaian — periode ini</div>
    <div class="dcard-body">
      <?php
      $srcRows = $sourceData[$selectedPid] ?? [];
      if (!empty($srcRows)):
        $maxSrc = max(array_column($srcRows,'avg'));
      ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px">
        <?php foreach ($srcRows as $src):
          $pct = $maxSrc>0 ? round(($src['avg']/$maxSrc)*100) : 0;
          $label = respondentLabel($src['respondent_type']);
        ?>
        <div class="mc">
          <div class="mc-val"><?= number_format($src['avg'],2) ?></div>
          <div class="mc-lbl"><?= h($label) ?></div>
          <div class="bar-track" style="margin-top:8px;height:6px">
            <div class="bar-fill" style="width:<?= $pct ?>%;background:#185FA5;height:6px"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <p style="font-size:12px;color:#64748b;text-align:center;padding:12px 0">Belum ada data responden</p>
      <?php endif; ?>
    </div>
  </div>

  <?php elseif ($activeTab === 'self'): ?>

  <!-- PERIOD NAVIGATOR -->
  <div class="period-nav">
    <a href="<?= $prevPid?"?user_id=$selectedUid&period_id=$prevPid&tab=self":'#' ?>"
       class="nav-btn <?= !$prevPid?'disabled':'' ?>">‹</a>
    <div class="period-name"><?= $curPeriod ? h($curPeriod['name']) : '—' ?></div>
    <a href="<?= $nextPid?"?user_id=$selectedUid&period_id=$nextPid&tab=self":'#' ?>"
       class="nav-btn <?= !$nextPid?'disabled':'' ?>">›</a>
  </div>

  <!-- LEGEND -->
  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:10px 16px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
    <span style="font-size:11px;font-weight:600;color:#64748b;margin-right:4px">Skala:</span>
    <span class="chip" style="background:#EAF3DE;color:#27500A;border:1px solid #c0dd97">≥ 3.75 Sempurna</span>
    <span class="chip" style="background:#E6F1FB;color:#0C447C;border:1px solid #b5d4f4">≥ 3.25 Sangat Baik</span>
    <span class="chip" style="background:#dbeafe;color:#1e40af;border:1px solid #93c5fd">≥ 2.75 Baik</span>
    <span class="chip" style="background:#FAEEDA;color:#633806;border:1px solid #fac775">≥ 2.25 Cukup</span>
    <span class="chip" style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5">≥ 1.75 Kurang</span>
    <span class="chip" style="background:#FCEBEB;color:#791F1F;border:1px solid #f7c1c1">&lt; 1.75 Sangat Kurang</span>
  </div>

  <!-- SELF BANNER -->
  <div class="self-banner">
    <div>
      <div style="font-size:13px;font-weight:500;color:#5b21b6">
        <i class="bi bi-journal-text me-2"></i>Refleksi Mandiri
      </div>
      <div style="font-size:12px;color:#64748b;margin-top:3px">
        Penilaian diri sendiri — <strong>tidak dihitung</strong> dalam indeks skor evaluasi.
      </div>
    </div>
    <?php if ($selfScores['overall'] > 0): ?>
    <span class="chip chip-blue" style="background:#f5f3ff;color:#5b21b6;border:1px solid #ddd6fe;font-size:13px;padding:4px 14px">
      <?= number_format($selfScores['overall'],2) ?> / 4.00
    </span>
    <?php endif; ?>
  </div>

  <?php if ($selfScores['overall'] > 0): ?>
  <div class="grid2">
    <!-- Perbandingan evaluasi vs refleksi -->
    <div class="dcard">
      <div class="dcard-hdr">Evaluasi vs Refleksi diri</div>
      <div class="dcard-body">
        <div class="bar-row">
          <div class="bar-lbl">Skor evaluasi</div>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?= round(($scores['overall']/4)*100) ?>%;background:#185FA5"></div>
          </div>
          <div class="bar-val"><?= number_format($scores['overall'],2) ?></div>
        </div>
        <div class="bar-row">
          <div class="bar-lbl">Refleksi diri</div>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?= round(($selfScores['overall']/4)*100) ?>%;background:#7c3aed"></div>
          </div>
          <div class="bar-val"><?= number_format($selfScores['overall'],2) ?></div>
        </div>
        <?php $gap = round($scores['overall'] - $selfScores['overall'],2); ?>
        <p style="font-size:11px;color:#64748b;margin-top:12px">
          <?php if (abs($gap) < 0.2): ?>
          <i class="bi bi-check-circle-fill text-success me-1"></i>Persepsi diri selaras dengan penilaian eksternal.
          <?php elseif ($gap > 0): ?>
          <i class="bi bi-info-circle-fill text-primary me-1"></i>Refleksi diri lebih rendah — indikasi kerendahan hati atau area pengembangan.
          <?php else: ?>
          <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>Refleksi diri lebih tinggi dari penilaian eksternal — perlu introspeksi lebih dalam.
          <?php endif; ?>
        </p>
      </div>
    </div>

    <!-- Refleksi per domain -->
    <div class="dcard">
      <div class="dcard-hdr">Refleksi per domain</div>
      <div class="dcard-body">
        <?php foreach ($selfScores['byDomain'] as $d):
          $sdl = getScoreLevel($d['avg']);
        ?>
        <div class="bar-row">
          <div class="bar-lbl"><?= h($d['domain_name']) ?></div>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?= round(($d['avg']/4)*100) ?>%;background:#7c3aed"></div>
          </div>
          <div class="bar-val"><?= number_format($d['avg'],2) ?></div>
          <span class="chip" style="background:<?= $sdl['bg'] ?>;color:<?= $sdl['color'] ?>;border:1px solid <?= $sdl['color'] ?>;font-size:10px;white-space:nowrap"><?= $sdl['label_id'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Tren refleksi -->
  <div class="dcard">
    <div class="dcard-hdr">Tren refleksi mandiri dari waktu ke waktu</div>
    <div class="dcard-body">
      <div style="position:relative;height:140px">
        <canvas id="selfTrendChart"></canvas>
      </div>
    </div>
  </div>

  <?php else: ?>
  <div class="empty-hint">
    <i class="bi bi-journal-text" style="font-size:36px;display:block;margin-bottom:10px;opacity:.3"></i>
    <p>Belum ada data refleksi mandiri untuk periode ini.</p>
  </div>
  <?php endif; ?>

  <?php elseif ($activeTab === 'detail'): ?>

  <!-- PERIOD NAVIGATOR -->
  <div class="period-nav">
    <a href="<?= $prevPid?"?user_id=$selectedUid&period_id=$prevPid&tab=detail":'#' ?>"
       class="nav-btn <?= !$prevPid?'disabled':'' ?>">‹</a>
    <div class="period-name"><?= $curPeriod ? h($curPeriod['name']) : '—' ?></div>
    <a href="<?= $nextPid?"?user_id=$selectedUid&period_id=$nextPid&tab=detail":'#' ?>"
       class="nav-btn <?= !$nextPid?'disabled':'' ?>">›</a>
  </div>

  <?php if (!empty($byDomainAccordion)): ?>
  <!-- LEVEL LEGEND -->
  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:10px 16px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
    <span style="font-size:11px;font-weight:600;color:#64748b;margin-right:4px">Skala:</span>
    <span class="chip" style="background:#EAF3DE;color:#27500A;border:1px solid #c0dd97">≥ 3.75 Sempurna</span>
    <span class="chip" style="background:#E6F1FB;color:#0C447C;border:1px solid #b5d4f4">≥ 3.25 Sangat Baik</span>
    <span class="chip" style="background:#dbeafe;color:#1e40af;border:1px solid #93c5fd">≥ 2.75 Baik</span>
    <span class="chip" style="background:#FAEEDA;color:#633806;border:1px solid #fac775">≥ 2.25 Cukup</span>
    <span class="chip" style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5">≥ 1.75 Kurang</span>
    <span class="chip" style="background:#FCEBEB;color:#791F1F;border:1px solid #f7c1c1">&lt; 1.75 Sangat Kurang</span>
  </div>

  <?php if (!empty($sortedTraits)): ?>
  <div class="dcard" style="border-left:3px solid #533AB7">
    <div class="dcard-hdr">Skor per Trait</div>
    <div class="dcard-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px">
        <?php foreach ($sortedTraits as $t):
          $tl = getScoreLevel($t['avg']);
        ?>
        <div class="bar-row">
          <div class="bar-lbl"><?= h($t['name']) ?></div>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?= round(($t['avg']/4)*100) ?>%;background:#533AB7"></div>
          </div>
          <div class="bar-val"><?= number_format($t['avg'],2) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="dcard">
    <div class="dcard-hdr">
      Detail per Standard
      <span class="chip chip-blue">Skor keseluruhan: <?= number_format($scores['overall'],2) ?></span>
    </div>
    <div class="dcard-body" style="padding:10px">
      <?php foreach ($byDomainAccordion as $domainName => $standards):
        $domAvg = round(array_sum(array_column($standards,'avg'))/count($standards),2);
        $level  = getScoreLevel($domAvg);
      ?>
      <div class="accordion-domain">
        <button class="accordion-hdr" onclick="toggleAcc(this)">
          <span><?= h($domainName) ?> <span style="font-size:11px;color:#64748b">(<?= count($standards) ?> standard)</span></span>
          <span>
            <span class="chip" style="background:<?= $level['bg'] ?>;color:<?= $level['color'] ?>;border:1px solid <?= $level['color'] ?>">
              <?= number_format($domAvg,2) ?>
            </span>
            <i class="bi bi-chevron-down ms-2" style="font-size:11px;color:#64748b"></i>
          </span>
        </button>
        <div class="accordion-body open">
          <table class="std-table">
            <?php foreach ($standards as $s):
              $sl = getScoreLevel($s['avg']);
            ?>
            <tr>
              <td style="color:#1e293b"><?= h($s['name']) ?></td>
              <td style="width:60px;text-align:center;font-weight:500;color:<?= $sl['color'] ?>"><?= number_format($s['avg'],2) ?></td>
              <td style="width:120px">
                <span class="chip" style="background:<?= $sl['bg'] ?>;color:<?= $sl['color'] ?>;border:1px solid <?= $sl['color'] ?>">
                  <?= $sl['label_id'] ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </table>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- AI SUGGESTION -->
  <div class="dcard">
    <div class="dcard-hdr">
      <span><i class="bi bi-stars me-2"></i>Saran Pengembangan (AI)</span>
      <?php if ($scores['overall'] > 0): ?>
      <button class="btn btn-sm btn-outline-secondary" style="font-size:11px"
        onclick="generateAI(<?= $selectedUid ?>, <?= $selectedPid ?>)">
        <i class="bi bi-stars me-1"></i>Generate
      </button>
      <?php endif; ?>
    </div>
    <div class="dcard-body">
      <?php if ($aiSuggestion): ?>
      <form method="POST" action="<?= APP_URL ?>/api/ai.php">
        <input type="hidden" name="action" value="save_edit">
        <input type="hidden" name="evaluatee_id" value="<?= $selectedUid ?>">
        <input type="hidden" name="period_id" value="<?= $selectedPid ?>">
        <textarea name="edited_suggestion" class="form-control mb-2" rows="8"
          style="font-size:13px;line-height:1.7"><?= h($aiSuggestion['edited_suggestion'] ?? $aiSuggestion['raw_suggestion']) ?></textarea>
        <button type="submit" class="btn btn-sm btn-navy">
          <i class="bi bi-save me-1"></i>Simpan Editan
        </button>
      </form>
      <?php else: ?>
      <div style="text-align:center;padding:24px;color:#64748b">
        <i class="bi bi-stars" style="font-size:32px;display:block;margin-bottom:8px;opacity:.4"></i>
        <p style="font-size:13px">Belum ada saran AI. Klik "Generate" untuk membuat.</p>
        <div id="aiResult" style="display:none;text-align:left;margin-top:12px"></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php else: ?>
  <div class="empty-hint">
    <p>Belum ada data untuk periode ini.</p>
  </div>
  <?php endif; ?>

  <?php endif; // tab ?>
  <?php endif; // selectedUser ?>

</div>

<script>
const TREND_DATA = <?= json_encode($jsTrend) ?>;
const SELF_TREND  = <?= json_encode($jsSelfTrend ?? []) ?>;
const SELECTED_PID = <?= $selectedPid ?: 'null' ?>;

// Trend chart
document.addEventListener('DOMContentLoaded', () => {
  const tc = document.getElementById('trendChart');
  if (tc && TREND_DATA.length) {
    const labels = TREND_DATA.map(d => {
      const m = d.name.match(/Semester\s*(\d+).*(20\d{2}\/20\d{2})/i);
      return m ? 'Sem ' + m[1] + ' ' + m[2] : d.name;
    });
    const data   = TREND_DATA.map(d => d.score || null);
    const colors = TREND_DATA.map(d => d.id === SELECTED_PID ? '#185FA5' : 'rgba(24,95,165,0.35)');
    new Chart(tc, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          data,
          backgroundColor: colors,
          borderRadius: 4,
          borderSkipped: false,
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend:{display:false}, tooltip:{callbacks:{label:c=>' '+parseFloat(c.raw).toFixed(2)+' / 4.00'}} },
        scales: {
          y: { min:1.5, max:4.0, ticks:{stepSize:.5,font:{size:10}}, grid:{color:'rgba(0,0,0,.05)'} },
          x: { ticks:{font:{size:9},maxRotation:30}, grid:{display:false} }
        }
      }
    });
  }

  // Self trend chart
  const stc = document.getElementById('selfTrendChart');
  if (stc) {
    const labels = SELF_TREND.map(d => {
      const m = d.name.match(/Semester\s*(\d+)/i);
      return m ? 'Sem ' + m[1] : d.name;
    });
    new Chart(stc, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          data: SELF_TREND.map(d => d.score || null),
          borderColor: '#7c3aed',
          backgroundColor: 'rgba(124,58,237,0.06)',
          fill: true, tension: 0.4,
          pointRadius: 5, pointBackgroundColor: '#7c3aed',
          pointBorderColor: '#fff', pointBorderWidth: 2,
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {legend:{display:false}, tooltip:{callbacks:{label:c=>' '+parseFloat(c.raw).toFixed(2)+' / 4.00'}}},
        scales: {
          y: {min:1.5,max:4.0,ticks:{stepSize:.5,font:{size:10}},grid:{color:'rgba(0,0,0,.05)'}},
          x: {ticks:{font:{size:9}},grid:{display:false}}
        }
      }
    });
  }
});

function toggleAcc(btn) {
  const body = btn.nextElementSibling;
  body.classList.toggle('open');
}

function generateAI(uid, pid) {
  const btn = event.target;
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Generating...';
  fetch('<?= APP_URL ?>/api/ai.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `action=generate&evaluatee_id=${uid}&period_id=${pid}`
  }).then(r=>r.json()).then(d=>{
    if (d.success) location.reload();
    else { alert('Gagal: ' + (d.error||'Unknown error')); btn.disabled=false; btn.innerHTML='<i class="bi bi-stars me-1"></i>Generate'; }
  }).catch(()=>{btn.disabled=false;});
}
</script>

<?php $content = ob_get_clean(); pageWrapper('Laporan Kinerja', $content); ?>