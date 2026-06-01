<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
$user   = currentUser();
$period = getPeriod();

if (canAccessAdmin()) {

// ── FETCH SEMUA CLOSED PERIODS ────────────────────────────────
$closedPeriods = Database::fetchAll("
    SELECT ep.id, ep.name, ep.year, ep.start_date, ep.end_date
    FROM eval_periods ep
    JOIN assignments a ON a.period_id = ep.id AND a.status='completed'
    WHERE ep.status = 'closed'
    GROUP BY ep.id
    ORDER BY ep.start_date, ep.id
");

if (empty($closedPeriods)) {
    ob_start(); ?>
    <div class="card">
      <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-bar-chart display-4 mb-3 d-block"></i>
        <h5 class="fw-bold text-navy">Belum ada data evaluasi</h5>
        <p>Data tren akan muncul setelah minimal satu periode evaluasi selesai.</p>
        <a href="<?= APP_URL ?>/admin/periods.php" class="btn btn-navy mt-2">
          <i class="bi bi-calendar3 me-1"></i>Kelola Periode
        </a>
      </div>
    </div>
    <?php
    $content = ob_get_clean();
    pageWrapper('Dashboard', $content);
    exit;
}

$periodIds   = array_column($closedPeriods, 'id');
$periodNames = array_column($closedPeriods, 'name');

// ── METRICS ───────────────────────────────────────────────────
$totalUsers  = Database::fetchOne("SELECT COUNT(*) c FROM users WHERE is_active=1")['c'];
$cntLeader   = Database::fetchOne("SELECT COUNT(*) c FROM users WHERE role='leader' AND is_active=1")['c'];
$cntTeacher  = Database::fetchOne("SELECT COUNT(*) c FROM users WHERE role='teacher' AND is_active=1")['c'];
$totalClosed = count($closedPeriods);

// School avg per closed period
$schoolAvgRaw = Database::fetchAll("
    SELECT ep.id as period_id, ROUND(AVG(r.grade),2) as avg_score
    FROM eval_periods ep
    JOIN assignments a ON a.period_id=ep.id AND a.status='completed'
    JOIN responses r ON r.assignment_id=a.id
    WHERE ep.status='closed'
    GROUP BY ep.id
    ORDER BY ep.start_date, ep.id
");
$schoolAvgByPid = [];
foreach ($schoolAvgRaw as $s) $schoolAvgByPid[$s['period_id']] = (float)$s['avg_score'];

// Domain trend per eval type per period
$domainRaw = Database::fetchAll("
    SELECT et.code as et_code, et.name as et_name,
           d.id as domain_id, d.name as domain_name,
           ep.id as period_id, ROUND(AVG(r.grade),2) as avg_score
    FROM eval_periods ep
    JOIN assignments a ON a.period_id=ep.id AND a.status='completed'
    JOIN responses r ON r.assignment_id=a.id
    JOIN questions q ON r.question_id=q.id
    JOIN standards s ON q.standard_id=s.id
    JOIN domains d ON s.domain_id=d.id
    JOIN eval_types et ON d.eval_type_id=et.id
    WHERE ep.status='closed'
    GROUP BY et.code, et.name, d.id, d.name, ep.id
    ORDER BY et.id, d.id, ep.start_date
");

// Trait trend per period
$traitRaw = Database::fetchAll("
    SELECT t.id as trait_id, t.name as trait_name,
           ep.id as period_id, ROUND(AVG(r.grade),2) as avg_score
    FROM eval_periods ep
    JOIN assignments a ON a.period_id=ep.id AND a.status='completed'
    JOIN responses r ON r.assignment_id=a.id
    JOIN questions q ON r.question_id=q.id
    JOIN standards s ON q.standard_id=s.id
    JOIN standard_traits st ON st.standard_id=s.id
    JOIN traits t ON t.id=st.trait_id
    WHERE ep.status='closed'
    GROUP BY t.id, t.name, ep.id
    ORDER BY t.code, ep.start_date
");

// Individual trend per period (tetap untuk list individu & ranking)
$individualRaw = Database::fetchAll("
    SELECT u.id, u.name, u.role, ep.id as period_id,
           ROUND(AVG(r.grade),2) as avg_score
    FROM users u
    JOIN assignments a ON a.evaluatee_id=u.id AND a.status='completed'
    JOIN eval_periods ep ON ep.id=a.period_id AND ep.status='closed'
    JOIN responses r ON r.assignment_id=a.id
    WHERE u.role IN ('leader','teacher') AND u.is_active=1
    GROUP BY u.id, u.name, u.role, ep.id
    ORDER BY u.role, u.name, ep.start_date
");

// ── BUILD JS DATA STRUCTURES ──────────────────────────────────

// School: { period_id: avg }
$jsSchool = $schoolAvgByPid;

// Domain: struktur baru untuk memudahkan pembuatan chart dengan sumbu X = domain
// Kita ubah: per et_code, kita simpan daftar domain dan matriks skor [domain][period_id]
$jsDomain = [];
foreach ($domainRaw as $r) {
    $et = $r['et_code'];
    $dn = $r['domain_name'];
    $pid = $r['period_id'];
    if (!isset($jsDomain[$et])) {
        $jsDomain[$et] = [
            'name' => $r['et_name'],
            'domains' => [],         // daftar nama domain (urutan unik)
            'scores' => []           // scores[domain_name][period_id]
        ];
    }
    if (!in_array($dn, $jsDomain[$et]['domains'])) {
        $jsDomain[$et]['domains'][] = $dn;
    }
    if (!isset($jsDomain[$et]['scores'][$dn])) {
        $jsDomain[$et]['scores'][$dn] = [];
    }
    $jsDomain[$et]['scores'][$dn][$pid] = (float)$r['avg_score'];
}

// Trait: { trait_id: { name, data: { period_id: avg } } }
$jsTrait = [];
foreach ($traitRaw as $r) {
    $tid = $r['trait_id'];
    if (!isset($jsTrait[$tid])) $jsTrait[$tid] = ['name'=>$r['trait_name'],'data'=>[]];
    $jsTrait[$tid]['data'][$r['period_id']] = (float)$r['avg_score'];
}

// Individual: { user_id: { name, role, scores: { period_id: avg } } }
$jsIndividual = [];
foreach ($individualRaw as $r) {
    $uid = $r['id'];
    if (!isset($jsIndividual[$uid])) {
        $jsIndividual[$uid] = ['name'=>$r['name'],'role'=>$r['role'],'scores'=>[]];
    }
    $jsIndividual[$uid]['scores'][$r['period_id']] = (float)$r['avg_score'];
}

function initials(string $n): string {
    $w = explode(' ', trim($n));
    return count($w)>=2 ? strtoupper($w[0][0].$w[1][0]) : strtoupper(substr($n,0,2));
}
function heatColor(float $v): string {
    if ($v >= 3.5) return 'background:#EAF3DE;color:#27500A';
    if ($v >= 3.0) return 'background:#E6F1FB;color:#0C447C';
    if ($v >= 2.5) return 'background:#FAEEDA;color:#633806';
    return 'background:#FCEBEB;color:#791F1F';
}

ob_start(); ?>

<style>
.dash-g2{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.dash-g4{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}
.mcard{background:#ffffff;border-radius:10px;padding:.9rem 1.1rem;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,.04),0 4px 12px rgba(0,0,0,.04)}
.mval{font-size:24px;font-weight:500;color:#1e293b;line-height:1.1}
.mlbl{font-size:12px;color:#64748b;margin-top:3px}
.msub{font-size:11px;color:#64748b;margin-top:6px}
.dcard{background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,.04),0 4px 12px rgba(0,0,0,.04)}
.dcard-hdr{padding:10px 16px;font-size:12px;font-weight:600;color:#1e293b;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc}
.dcard-body{padding:14px;background:#ffffff}
/* Accent left borders per card type */
.dcard.accent-blue{border-left:3px solid #185FA5}
.dcard.accent-green{border-left:3px solid #3B6D11}
.dcard.accent-red{border-left:3px solid #A32D2D}
.dcard.accent-purple{border-left:3px solid #533AB7}
.dcard.accent-amber{border-left:3px solid #854F0B}
/* Section divider */
.section-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:10px;margin-top:4px;padding-left:2px;display:flex;align-items:center;gap:8px}
.section-label::after{content:"";flex:1;height:1px;background:#e2e8f0}
/* Tabs */
.tab-group{display:flex;gap:4px;flex-wrap:wrap}
.tab-btn{font-size:11px;padding:3px 10px;border-radius:4px;border:0.5px solid #e2e8f0;background:#f8fafc;color:#64748b;cursor:pointer;transition:all .12s}
.tab-btn.active{background:#185FA5!important;color:#ffffff!important;border-color:transparent}
.tab-panel{display:none}.tab-panel.active{display:block}
/* Slider */
.period-slider-wrap{background:#ffffff;border:1px solid #e2e8f0;border-left:3px solid #2C5282;border-radius:12px;padding:12px 20px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,.04),0 4px 12px rgba(0,0,0,.04)}
.slider-track{position:relative;height:6px;background:#f1f5f9;border-radius:3px;margin:20px 0 8px}
.slider-wrap-inner{width:75%;margin:0 auto}
.slider-fill{position:absolute;height:100%;background:#185FA5;border-radius:3px;pointer-events:none}
.slider-handle{position:absolute;top:-7px;width:20px;height:20px;border-radius:50%;background:white;border:2px solid #185FA5;cursor:grab;box-shadow:0 1px 4px rgba(0,0,0,.15);transform:translateX(-50%);transition:box-shadow .1s}
.slider-handle:active{cursor:grabbing;box-shadow:0 2px 8px rgba(24,95,165,.3)}
.slider-labels{display:flex;justify-content:space-between;font-size:10px;color:#64748b;margin-top:4px}
.period-info{font-size:13px;font-weight:500;text-align:center;color:#1e293b}
/* Rank rows */
.rank-row{display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #f1f5f9}
.rank-row:last-child{border-bottom:none}
.av{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:500;flex-shrink:0;background:#E6F1FB;color:#0C447C}
.rank-nm{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;font-weight:500}
.rank-role{font-size:10px;color:#64748b}
.score-chip{font-size:11px;font-weight:500;padding:2px 8px;border-radius:4px;flex-shrink:0}
.sline{display:flex;align-items:flex-end;gap:2px;height:18px;flex-shrink:0}
.sb{width:8px;border-radius:2px 2px 0 0}
.ql-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px}
.ql{display:flex;flex-direction:column;align-items:center;padding:12px 8px;border-radius:10px;border:1px solid #e2e8f0;background:#ffffff;font-size:12px;font-weight:500;text-align:center;gap:5px;text-decoration:none;color:#1e293b;transition:all .15s;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.ql:hover{background:#f8fafc;border-color:#2C5282;transform:translateY(-2px);box-shadow:0 4px 12px rgba(44,82,130,.12)}
.ql i{font-size:20px;color:#185FA5}
</style>

<!-- METRIC CARDS -->
<div class="dash-g4">
  <div class="mcard">
    <div class="mval"><?= $totalUsers ?></div>
    <div class="mlbl">Total pengguna aktif</div>
    <div class="msub"><?= $cntLeader ?> pimpinan · <?= $cntTeacher ?> guru</div>
  </div>
  <div class="mcard" id="metricAvg">
    <div class="mval" id="metricAvgVal">—</div>
    <div class="mlbl">Rata-rata skor</div>
    <div class="msub" id="metricAvgSub">Pilih rentang periode</div>
  </div>
  <div class="mcard">
    <div class="mval"><?= $totalClosed ?></div>
    <div class="mlbl">Periode selesai</div>
    <div class="msub"><?= $period ? '1 sedang berjalan' : 'Tidak ada periode aktif' ?></div>
  </div>
  <div class="mcard">
    <?php if ($period): ?>
    <div class="mval" id="metricNeedAttn">—</div>
    <div class="mlbl">Perlu perhatian</div>
    <div class="msub">Skor &lt; 2.75 periode akhir</div>
    <?php else: ?>
    <div class="mval text-muted">—</div>
    <div class="mlbl">Periode aktif</div>
    <div class="msub">Tidak ada periode berjalan</div>
    <?php endif; ?>
  </div>
</div>

<!-- PERIOD SLIDER -->
<div class="period-slider-wrap">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <span style="font-size:12px;font-weight:500;color:#64748b">
      <i class="bi bi-sliders me-1"></i>Rentang Periode
    </span>
    <span class="period-info" id="sliderInfo">—</span>
    <?php if ($period): ?>
    <a href="<?= APP_URL ?>/admin/progress.php" class="btn btn-sm btn-outline-primary" style="font-size:11px">
      <i class="bi bi-activity me-1"></i>Progress Aktif
    </a>
    <?php endif; ?>
  </div>
  <div class="slider-wrap-inner">
  <div class="slider-track" id="sliderTrack">
    <div class="slider-fill" id="sliderFill"></div>
    <div class="slider-handle" id="handleLeft"></div>
    <div class="slider-handle" id="handleRight"></div>
  </div>
  <div class="slider-labels" id="sliderLabels"></div>
  </div>
</div>

<!-- SCHOOL TREND + INDIVIDUAL TREND -->
<div class="section-label"><i class="bi bi-graph-up"></i>Tren Sekolah</div>
<div class="dash-g2">
  <div class="dcard accent-blue">
    <div class="dcard-hdr">Tren rata-rata sekolah</div>
    <div class="dcard-body">
      <div style="position:relative;height:180px">
        <canvas id="schoolChart"></canvas>
      </div>
    </div>
  </div>

  <div class="dcard" style="display:flex;flex-direction:column">
    <div class="dcard-hdr">Tren individual — semua orang</div>
    <div style="padding:8px 14px;overflow-y:auto;flex:1;height:180px" id="individualList">
    </div>
  </div>
</div>

<!-- DOMAIN TREND + TRAIT TREND -->
<div class="section-label"><i class="bi bi-bar-chart"></i>Tren per Domain &amp; Trait</div>
<div class="dash-g2">
  <div class="dcard accent-amber">
    <div class="dcard-hdr">
      <span>Tren per domain</span>
      <div class="tab-group" id="domainTabs"></div>
    </div>
    <div class="dcard-body" id="domainPanels">
      <?php foreach ($jsDomain as $etCode => $etData): ?>
      <div class="tab-panel" id="dpanel-<?= $etCode ?>">
        <div style="position:relative;height:260px">
          <canvas id="domainChart_<?= $etCode ?>"></canvas>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="dcard accent-purple">
    <div class="dcard-hdr">
      <span>Tren per trait</span>
      <div class="tab-group" id="traitTabs" style="max-width:200px;justify-content:flex-end"></div>
    </div>
    <div class="dcard-body" id="traitPanels">
      <?php foreach ($jsTrait as $tid => $tData): ?>
      <div class="tab-panel" id="tpanel-<?= $tid ?>">
        <div style="position:relative;height:200px">
          <canvas id="traitChart_<?= $tid ?>"></canvas>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- TOP 5 + BOTTOM 5 -->
<div class="section-label"><i class="bi bi-people"></i>Peringkat Kinerja</div>
<div class="dash-g2">
  <div class="dcard accent-green">
    <div class="dcard-hdr" style="color:#3B6D11">
      <span><i class="bi bi-trophy-fill me-1"></i>5 Performa Terbaik</span>
      <span style="font-size:10px;font-weight:400" id="rankPeriodLabel">periode akhir</span>
    </div>
    <div class="dcard-body" style="padding:8px 14px" id="top5List"></div>
  </div>
  <div class="dcard accent-red">
    <div class="dcard-hdr" style="color:#A32D2D">
      <span><i class="bi bi-exclamation-triangle-fill me-1"></i>5 Perlu Perhatian</span>
      <span style="font-size:10px;font-weight:400" id="rankPeriodLabel2">periode akhir</span>
    </div>
    <div class="dcard-body" style="padding:8px 14px" id="bottom5List"></div>
  </div>
</div>

<!-- QUICK LINKS -->
<div class="section-label"><i class="bi bi-lightning"></i>Akses Cepat</div>
<div class="ql-grid">
  <a href="<?= APP_URL ?>/admin/users.php" class="ql"><i class="bi bi-people-fill"></i>Kelola Pengguna</a>
  <a href="<?= APP_URL ?>/admin/reports.php" class="ql"><i class="bi bi-bar-chart-fill"></i>Laporan per Orang</a>
  <a href="<?= APP_URL ?>/admin/matrix.php" class="ql"><i class="bi bi-grid-fill"></i>Matriks Mapping</a>
  <a href="<?= APP_URL ?>/admin/periods.php" class="ql"><i class="bi bi-calendar3"></i>Periode Evaluasi</a>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
// ── DATA ──────────────────────────────────────────────────────
const PERIODS     = <?= json_encode(array_values($closedPeriods)) ?>;
const SCHOOL_AVG  = <?= json_encode($jsSchool) ?>;
const DOMAIN_DATA = <?= json_encode($jsDomain) ?>;  // struktur baru
const TRAIT_DATA  = <?= json_encode($jsTrait) ?>;
const INDIVIDUAL  = <?= json_encode($jsIndividual) ?>;

const COLORS = ['#185FA5','#3B6D11','#854F0B','#A32D2D','#533AB7','#0F6E56','#BA7517','#0C6B5F'];

// ── STATE ─────────────────────────────────────────────────────
let leftIdx       = 0;
let rightIdx      = PERIODS.length - 1;
let schoolChart   = null;
let activeDomainEt  = null;
let activeTraitId   = null;
const domainCharts  = {};
const traitCharts   = {};

// ── SLIDER ────────────────────────────────────────────────────
const track       = document.getElementById('sliderTrack');
const fill        = document.getElementById('sliderFill');
const handleL     = document.getElementById('handleLeft');
const handleR     = document.getElementById('handleRight');
const sliderInfo  = document.getElementById('sliderInfo');
const sliderLabels= document.getElementById('sliderLabels');

function pct(i) { return PERIODS.length < 2 ? 0 : (i / (PERIODS.length-1)) * 100; }

function renderSlider() {
  const lp = pct(leftIdx), rp = pct(rightIdx);
  fill.style.left  = lp + '%';
  fill.style.width = (rp - lp) + '%';
  handleL.style.left = lp + '%';
  handleR.style.left = rp + '%';
  const ln = PERIODS[leftIdx].name;
  const rn = PERIODS[rightIdx].name;
  sliderInfo.textContent = leftIdx === rightIdx
    ? ln
    : ln.split(' ').slice(-2).join(' ') + ' → ' + rn.split(' ').slice(-2).join(' ') + ' (' + (rightIdx-leftIdx+1) + ' periode)';
}

function formatPeriodLabel(name) {
  if (name.includes(' TA ')) {
    const parts = name.split(' TA ');
    return [parts[0].trim(), 'TA ' + parts[1].trim()];
  }
  const semMatch = name.match(/^(.*Semester\s*\d+)(.*)$/i);
  if (semMatch) {
    return [semMatch[1].trim(), semMatch[2].trim() || ''];
  }
  const words = name.split(' ');
  const mid = Math.ceil(words.length / 2);
  return [words.slice(0, mid).join(' '), words.slice(mid).join(' ')];
}

function renderLabels() {
  const fs = Math.max(8, Math.min(11, 13 - Math.floor(PERIODS.length / 3)));
  sliderLabels.innerHTML = PERIODS.map((p,i) => {
    const pos = pct(i);
    const [line1, line2] = formatPeriodLabel(p.name);
    return `<span style="position:absolute;left:${pos}%;transform:translateX(-50%);text-align:center;line-height:1.3;white-space:nowrap">
      <span style="display:block;font-size:${fs}px;font-weight:500;color:#1e293b">${line1}</span>
      ${line2 ? `<span style="display:block;font-size:${Math.max(7,fs-1)}px;color:#64748b">${line2}</span>` : ''}
    </span>`;
  }).join('');
  sliderLabels.style.position = 'relative';
  sliderLabels.style.height   = '30px';
}

function setupDrag(handle, isLeft) {
  let dragging = false;
  handle.addEventListener('mousedown', e => { dragging = true; e.preventDefault(); });
  document.addEventListener('mousemove', e => {
    if (!dragging) return;
    const rect = track.getBoundingClientRect();
    const ratio = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width));
    let idx = Math.round(ratio * (PERIODS.length - 1));
    if (isLeft) {
      idx = Math.min(idx, rightIdx);
      leftIdx = idx;
    } else {
      idx = Math.max(idx, leftIdx);
      rightIdx = idx;
    }
    renderSlider();
    updateAll();
  });
  document.addEventListener('mouseup', () => { dragging = false; });
  handle.addEventListener('touchstart', e => { dragging = true; e.preventDefault(); }, {passive:false});
  document.addEventListener('touchmove', e => {
    if (!dragging) return;
    const rect = track.getBoundingClientRect();
    const touch = e.touches[0];
    const ratio = Math.min(1, Math.max(0, (touch.clientX - rect.left) / rect.width));
    let idx = Math.round(ratio * (PERIODS.length - 1));
    if (isLeft) { idx = Math.min(idx, rightIdx); leftIdx = idx; }
    else        { idx = Math.max(idx, leftIdx);  rightIdx = idx; }
    renderSlider();
    updateAll();
  }, {passive:false});
  document.addEventListener('touchend', () => { dragging = false; });
}

setupDrag(handleL, true);
setupDrag(handleR, false);

// ── FILTERED DATA ─────────────────────────────────────────────
function filteredPids() {
  return PERIODS.slice(leftIdx, rightIdx + 1).map(p => p.id);
}
function filteredLabels() {
  return PERIODS.slice(leftIdx, rightIdx + 1).map(p => p.name);
}
function filteredPeriodNames() {
  return PERIODS.slice(leftIdx, rightIdx + 1).map(p => p.name);
}

// ── SCHOOL CHART (tetap) ──────────────────────────────────────
function updateSchoolChart() {
  const pids   = filteredPids();
  const labels = filteredLabels();
  const data   = pids.map(pid => SCHOOL_AVG[pid] ?? null);

  if (!schoolChart) {
    schoolChart = new Chart(document.getElementById('schoolChart'), {
      type: 'line',
      data: { labels, datasets: [{
        label: 'Rata-rata',
        data,
        borderColor: '#185FA5',
        backgroundColor: 'rgba(55,138,221,0.08)',
        fill: true, tension: 0.4,
        pointBackgroundColor: '#185FA5',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 6, pointHoverRadius: 8,
      }]},
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend:{display:false}, tooltip:{callbacks:{label:c=>' '+parseFloat(c.raw).toFixed(2)+' / 4.00'}} },
        scales: {
          y: { min:1.5, max:4.0, ticks:{stepSize:.5,font:{size:10}}, grid:{color:'rgba(0,0,0,.05)'} },
          x: { ticks:{font:{size:10},maxRotation:30}, grid:{display:false} }
        }
      }
    });
  } else {
    schoolChart.data.labels = labels;
    schoolChart.data.datasets[0].data = data;
    schoolChart.update();
  }

  const valid = data.filter(v=>v!==null);
  if (valid.length > 0) {
    const last  = valid[valid.length-1];
    const first = valid[0];
    const diff  = (last - first).toFixed(2);
    document.getElementById('metricAvgVal').textContent = last.toFixed(2);
    const sub = document.getElementById('metricAvgSub');
    const sign = diff >= 0 ? '+' : '';
    sub.innerHTML = `<span style="color:${diff>=0?'#3B6D11':'#A32D2D'}">${sign}${diff} dari periode awal rentang</span>`;
  }
}

// ── DOMAIN CHARTS (BAR CHART dengan sumbu X = domain, dataset = periode) ──
function buildDomainTabs() {
  const tabs = document.getElementById('domainTabs');
  let first = true;
  for (const etCode in DOMAIN_DATA) {
    const btn = document.createElement('button');
    btn.className = 'tab-btn';
    btn.textContent = DOMAIN_DATA[etCode].name;
    btn.onclick = () => switchDomainTab(etCode);
    btn.id = 'dtab-' + etCode;
    setTabInactive(btn);
    tabs.appendChild(btn);
    if (first) {
      activeDomainEt = etCode;
      setTabActive(btn);
      document.getElementById('dpanel-'+etCode)?.classList.add('active');
    }
    first = false;
  }
}

function setTabActive(btn) {
  btn.style.background = '#185FA5';
  btn.style.color = '#ffffff';
  btn.style.borderColor = '#185FA5';
  btn.style.fontWeight = '600';
  btn.style.boxShadow = '0 2px 6px rgba(24,95,165,0.35)';
}
function setTabInactive(btn) {
  btn.style.background = '#f8fafc';
  btn.style.color = '#64748b';
  btn.style.borderColor = '#e2e8f0';
  btn.style.fontWeight = '400';
  btn.style.boxShadow = 'none';
}
function switchDomainTab(etCode) {
  activeDomainEt = etCode;
  document.querySelectorAll('[id^="dpanel-"]').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('[id^="dtab-"]').forEach(b => setTabInactive(b));
  document.getElementById('dpanel-'+etCode)?.classList.add('active');
  const activeBtn = document.getElementById('dtab-'+etCode);
  if (activeBtn) setTabActive(activeBtn);
  updateDomainChart(etCode);
}

function updateDomainChart(etCode) {
  const et = DOMAIN_DATA[etCode];
  if (!et) return;

  const pids = filteredPids();
  const periodNames = filteredPeriodNames();
  const domains = et.domains; // array of domain names
  
  if (domains.length === 0) return;
  
  // Siapkan datasets: setiap periode menjadi satu dataset
  // Datasets = array of { label: nama_periode, data: array skor per domain }
  const datasets = periodNames.map((periodName, idx) => {
    const periodId = pids[idx];
    const dataPerDomain = domains.map(domainName => {
      const score = et.scores[domainName]?.[periodId];
      return score !== undefined ? score : null;
    });

const intensity = 0.3 + (idx / Math.max(1, periodNames.length - 1)) * 0.5; // range 0.3 - 0.8
const blueShade = `rgba(24, 95, 165, ${intensity})`;
const borderShade = `rgba(24, 95, 165, 0.8)`;

return {
  label: periodName,
  data: dataPerDomain,
  backgroundColor: blueShade,
  borderColor: borderShade,
  borderWidth: 1,
  borderRadius: 4,
};

  });
  
  const canvas = document.getElementById('domainChart_' + etCode);
  if (!canvas) return;
  
  if (!domainCharts[etCode]) {
    domainCharts[etCode] = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: domains,
        datasets: datasets,
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: true, position: 'bottom', labels: { font: { size: 10 }, boxWidth: 12, padding: 8 } },
          tooltip: { callbacks: { label: ctx => ` ${ctx.dataset.label}: ${ctx.raw?.toFixed(2)} / 4.00` } }
        },
        scales: {
          y: { min: 1.5, max: 4.0, ticks: { stepSize: 0.5, font: { size: 10 } }, grid: { color: 'rgba(0,0,0,.05)' } },
          x: { ticks: { font: { size: 6 }, maxRotation: 45 }, grid: { display: false } }
        }
      }
    });
  } else {
    domainCharts[etCode].data.labels = domains;
    domainCharts[etCode].data.datasets = datasets;
    domainCharts[etCode].update();
  }
}

function updateAllDomainCharts() {
  if (activeDomainEt) updateDomainChart(activeDomainEt);
}

// ── TRAIT CHARTS (tetap line chart) ──────────────────────────────
function buildTraitTabs() {
  const tabs = document.getElementById('traitTabs');
  tabs.style.display = 'flex';
  tabs.style.flexWrap = 'wrap';
  tabs.style.gap = '4px';
  tabs.style.maxWidth = 'none';
  let first = true;
  for (const tid in TRAIT_DATA) {
    const btn = document.createElement('button');
    btn.className = 'tab-btn';
    btn.textContent = TRAIT_DATA[tid].name;
    btn.onclick = () => switchTraitTab(tid);
    btn.id = 'ttab-' + tid;
    setTabInactive(btn);
    tabs.appendChild(btn);
    if (first) {
      activeTraitId = tid;
      setTabActive(btn);
      document.getElementById('tpanel-'+tid)?.classList.add('active');
    }
    first = false;
  }
}

function switchTraitTab(tid) {
  activeTraitId = tid;
  document.querySelectorAll('[id^="tpanel-"]').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('[id^="ttab-"]').forEach(b => setTabInactive(b));
  document.getElementById('tpanel-'+tid)?.classList.add('active');
  const activeBtn = document.getElementById('ttab-'+tid);
  if (activeBtn) setTabActive(activeBtn);
  updateTraitChart(tid);
}

function updateTraitChart(tid) {
  const pids   = filteredPids();
  const labels = filteredLabels();
  const trait  = TRAIT_DATA[tid];
  if (!trait) return;

  const data = pids.map(pid => trait.data?.[pid] ?? null);
  const canvas = document.getElementById('traitChart_' + tid);
  if (!canvas) return;

  if (!traitCharts[tid]) {
    traitCharts[tid] = new Chart(canvas, {
      type: 'line',
      data: { labels, datasets: [{
        label: trait.name, data,
        borderColor: '#533AB7',
        backgroundColor: 'rgba(83,58,183,0.06)',
        fill: true, tension: 0.4,
        pointBackgroundColor: '#533AB7',
        pointBorderColor: '#fff',
        pointBorderWidth: 2, pointRadius: 5, pointHoverRadius: 7,
      }]},
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend:{display:false}, tooltip:{callbacks:{label:c=>' '+parseFloat(c.raw).toFixed(2)+' / 4.00'}} },
        scales: {
          y: { min:1.5, max:4.0, ticks:{stepSize:.5,font:{size:10}}, grid:{color:'rgba(0,0,0,.05)'} },
          x: { ticks:{font:{size:10},maxRotation:30}, grid:{display:false} }
        }
      }
    });
  } else {
    traitCharts[tid].data.labels = labels;
    traitCharts[tid].data.datasets[0].data = data;
    traitCharts[tid].update();
  }
}

function updateAllTraitCharts() {
  if (activeTraitId) updateTraitChart(activeTraitId);
}

// ── INDIVIDUAL LIST (tanpa filter orang) ─────────────────────
function updateIndividualList() {
  const pids = filteredPids();
  const el = document.getElementById('individualList');
  if (!el) return;

  let html = '';
  for (const uid in INDIVIDUAL) {
    const p = INDIVIDUAL[uid];
    const vals = pids.map(pid => p.scores[pid]).filter(v => v !== undefined);
    if (!vals.length) continue;
    const first = vals[0], last = vals[vals.length-1];
    const diff = last - first;
    const color = diff > 0.05 ? '#185FA5' : diff < -0.05 ? '#E24B4A' : '#888780';
    const badge = diff > 0.05 ? `<span style="background:#EAF3DE;color:#3B6D11;font-size:10px;padding:1px 6px;border-radius:4px">↑ +${diff.toFixed(1)}</span>`
                : diff < -0.05 ? `<span style="background:#FCEBEB;color:#A32D2D;font-size:10px;padding:1px 6px;border-radius:4px">↓ ${diff.toFixed(1)}</span>`
                : `<span style="background:#F1EFE8;color:#5F5E5A;font-size:10px;padding:1px 6px;border-radius:4px">→ ${diff.toFixed(1)}</span>`;
    const bars = vals.map((v,i) => {
      const h = Math.max(20, Math.round((v/4)*100));
      const op = 0.35 + (i/Math.max(1,vals.length-1))*0.65;
      return `<div style="width:8px;height:${h}%;border-radius:2px 2px 0 0;background:${color};opacity:${op.toFixed(2)};align-self:flex-end"></div>`;
    }).join('');
    const nm = p.name.split(' ').slice(0,2).join(' ');
    html += `<div style="display:flex;align-items:center;gap:8px;padding:5px 0;border-bottom:.5px solid #e2e8f0">
      <div style="width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:500;flex-shrink:0;background:#E6F1FB;color:#0C447C">${initials(p.name)}</div>
      <div style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;font-weight:500">${nm}</div>
      <div style="display:flex;align-items:flex-end;gap:2px;height:20px">${bars}</div>
      ${badge}
    </div>`;
  }
  el.innerHTML = html || '<p style="font-size:12px;color:#64748b;text-align:center;padding:1rem">Tidak ada data</p>';
}

// ── TOP 5 / BOTTOM 5 (tanpa filter orang) ────────────────────
function initials(name) {
  const w = name.split(' ');
  return w.length >= 2 ? (w[0][0]+w[1][0]).toUpperCase() : name.substring(0,2).toUpperCase();
}

function heatStyle(v) {
  if (v >= 3.5) return 'background:#EAF3DE;color:#27500A';
  if (v >= 3.0) return 'background:#E6F1FB;color:#0C447C';
  if (v >= 2.5) return 'background:#FAEEDA;color:#633806';
  return 'background:#FCEBEB;color:#791F1F';
}

function roleLabel(r) { return r === 'leader' ? 'Pimpinan' : 'Guru'; }

function updateRankings() {
  const pids = filteredPids();
  const lastPid = pids[pids.length - 1];
  const lastPeriodName = PERIODS[rightIdx]?.name || '';

  const scores = [];
  for (const uid in INDIVIDUAL) {
    const p = INDIVIDUAL[uid];
    const v = p.scores[lastPid];
    if (v === undefined) continue;

    const vals = pids.map(pid => p.scores[pid]).filter(v=>v!==undefined);
    const first = vals[0]||v, diff = v - first;
    const barColor = diff > 0.05 ? '#185FA5' : diff < -0.05 ? '#E24B4A' : '#888780';
    const bars = vals.map((bv,i)=>{
      const h = Math.max(20, Math.round((bv/4)*100));
      const op = 0.3 + (i/Math.max(1,vals.length-1))*0.7;
      return `<div style="width:7px;height:${h}%;border-radius:2px 2px 0 0;background:${barColor};opacity:${op.toFixed(2)};align-self:flex-end"></div>`;
    }).join('');

    scores.push({ uid, name: p.name, role: p.role, score: v, bars, diff });
  }

  scores.sort((a,b) => b.score - a.score);
  const top5    = scores.slice(0, 5);
  const bottom5 = scores.slice(-5).reverse();

  const [ln1, ln2] = formatPeriodLabel(lastPeriodName);
  const shortLabel = ln2 ? ln1 + ' · ' + ln2 : ln1;
  document.getElementById('rankPeriodLabel').textContent  = shortLabel;
  document.getElementById('rankPeriodLabel2').textContent = shortLabel;

  function renderList(items, el) {
    el.innerHTML = items.map((p,i) => `
      <div class="rank-row">
        <div style="font-size:11px;font-weight:500;color:#64748b;min-width:14px">${i+1}</div>
        <div class="av">${initials(p.name)}</div>
        <div style="flex:1;min-width:0">
          <div class="rank-nm" title="${p.name}">${p.name.split(' ').slice(0,2).join(' ')}</div>
          <div class="rank-role">${roleLabel(p.role)}</div>
        </div>
        <div style="display:flex;align-items:flex-end;gap:2px;height:18px">${p.bars}</div>
        <div class="score-chip" style="${heatStyle(p.score)}">${p.score.toFixed(2)}</div>
      </div>`).join('');
  }

  renderList(top5, document.getElementById('top5List'));
  renderList(bottom5, document.getElementById('bottom5List'));

  const attn = scores.filter(s => s.score < 2.75).length;
  const attnEl = document.getElementById('metricNeedAttn');
  if (attnEl) {
    attnEl.textContent = attn;
    attnEl.style.color = attn > 0 ? '#A32D2D' : '#3B6D11';
  }
}

// ── MASTER UPDATE ─────────────────────────────────────────────
function updateAll() {
  updateSchoolChart();
  updateAllDomainCharts();
  updateAllTraitCharts();
  updateIndividualList();
  updateRankings();
}

// ── INIT ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  renderLabels();
  renderSlider();
  buildDomainTabs();
  buildTraitTabs();

  const firstEt = Object.keys(DOMAIN_DATA)[0];
  if (firstEt) updateDomainChart(firstEt);
  const firstTid = Object.keys(TRAIT_DATA)[0];
  if (firstTid) updateTraitChart(firstTid);

  updateAll();
});
</script>

<?php
    $content = ob_get_clean();
    pageWrapper('Dashboard', $content);
    exit;
}

// ── TEACHER / LEADER DASHBOARD ────────────────────────────────
$stats  = getUserStats($user['id']);
$scores = [];
if ($period && in_array($user['role'], ['leader','teacher'])) {
    $scores = calculateScores($user['id'], $period['id']);
}

ob_start(); ?>
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat-card position-relative">
    <div class="stat-label">Kuesioner Saya</div>
    <div class="stat-number"><?= $stats['pending'] + $stats['in_progress'] + $stats['completed'] ?></div>
    <i class="bi bi-clipboard stat-icon"></i>
  </div></div>
  <div class="col-6 col-md-3"><div class="stat-card red position-relative">
    <div class="stat-label">Menunggu</div>
    <div class="stat-number"><?= $stats['pending'] ?></div>
    <i class="bi bi-clock stat-icon"></i>
  </div></div>
  <div class="col-6 col-md-3"><div class="stat-card gold position-relative">
    <div class="stat-label">Sedang Diisi</div>
    <div class="stat-number"><?= $stats['in_progress'] ?></div>
    <i class="bi bi-pencil stat-icon"></i>
  </div></div>
  <div class="col-6 col-md-3"><div class="stat-card green position-relative">
    <div class="stat-label">Selesai</div>
    <div class="stat-number"><?= $stats['completed'] ?></div>
    <i class="bi bi-check-circle stat-icon"></i>
  </div></div>
</div>
<div class="row g-4">
  <?php if (!empty($scores) && $scores['overall'] > 0): ?>
  <div class="col-md-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-bar-chart-fill me-2"></i>Skor Kinerja Saya — <?= h($period['name']) ?></span>
        <?= scoreBadge($scores['overall']) ?>
      </div>
      <div class="card-body">
        <?php foreach ($scores['byDomain'] as $d):
          $level = getScoreLevel($d['avg']);
        ?>
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <strong class="small"><?= h($d['name']) ?></strong>
            <span style="color:<?= $level['color'] ?>;font-weight:500"><?= formatScore($d['avg']) ?></span>
          </div>
          <div class="progress" style="height:7px">
            <div class="progress-bar" style="width:<?= ($d['avg']/4)*100 ?>%;background:<?= $level['color'] ?>"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clipboard-check me-2"></i>Kuesioner yang Harus Diisi</span>
        <a href="<?= APP_URL ?>/survey/" class="btn btn-sm btn-gold">Lihat Semua</a>
      </div>
      <div class="card-body p-0">
        <?php $myPending = Database::fetchAll("
            SELECT a.id, a.status, u.name as evaluatee_name, p.name as pkg_name,
                   (SELECT COUNT(*) FROM responses r WHERE r.assignment_id=a.id) as answered,
                   (SELECT COUNT(*) FROM package_questions pq WHERE pq.package_id=a.package_id) as total
            FROM assignments a JOIN users u ON u.id=a.evaluatee_id
            JOIN packages p ON p.id=a.package_id
            JOIN eval_periods ep ON ep.id=a.period_id
            WHERE a.evaluator_id=? AND a.status IN ('pending','in_progress') AND ep.status='active'
            ORDER BY a.status DESC LIMIT 10", [$user['id']]);
        ?>
        <?php if (empty($myPending)): ?>
        <div class="text-center py-4 text-muted">
          <i class="bi bi-check-circle display-5 mb-2 d-block text-success"></i>
          <p>Semua kuesioner sudah selesai!</p>
        </div>
        <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($myPending as $a):
            $pct2 = $a['total']>0 ? round($a['answered']/$a['total']*100):0;
          ?>
          <div class="list-group-item px-3 py-3">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-semibold small"><?= h($a['evaluatee_name']) ?></div>
                <div class="text-muted" style="font-size:.78rem"><?= h($a['pkg_name']) ?></div>
                <?php if ($a['status']==='in_progress'): ?>
                <div class="mt-1" style="width:160px">
                  <div class="d-flex justify-content-between" style="font-size:.7rem">
                    <span class="text-muted">Progress</span><span><?= $a['answered'] ?>/<?= $a['total'] ?> soal</span>
                  </div>
                  <div class="progress" style="height:4px">
                    <div class="progress-bar bg-warning" style="width:<?= $pct2 ?>%"></div>
                  </div>
                </div>
                <?php endif; ?>
              </div>
              <a href="<?= APP_URL ?>/survey/fill.php?id=<?= $a['id'] ?>"
                 class="btn btn-sm <?= $a['status']==='pending'?'btn-navy':'btn-warning text-dark' ?>">
                <?= $a['status']==='pending'?'<i class="bi bi-play me-1"></i>Mulai':'<i class="bi bi-pencil me-1"></i>Lanjutkan' ?>
              </a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
pageWrapper('Dashboard', $content);
?>