<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin','admin','foundation','leader']);

// Period selector — bisa pilih periode apapun (active atau closed)
$allPeriods = Database::fetchAll("
    SELECT * FROM eval_periods
    WHERE status IN ('active','closed')
    ORDER BY start_date DESC, id DESC
");
$selectedPid = (int)($_GET['period_id'] ?? 0);
if (!$selectedPid && !empty($allPeriods)) {
    // Default ke periode aktif, kalau tidak ada ambil yang pertama
    foreach ($allPeriods as $ap) {
        if ($ap['status'] === 'active') { $selectedPid = $ap['id']; break; }
    }
    if (!$selectedPid) $selectedPid = $allPeriods[0]['id'];
}
$period = Database::fetchOne("SELECT * FROM eval_periods WHERE id=?", [$selectedPid]);
$pid = $period['id'] ?? 0;

$selectedId   = (int)($_GET['user_id'] ?? 0);
$selectedUser = $selectedId ? Database::fetchOne("SELECT * FROM users WHERE id=?", [$selectedId]) : null;
$scores       = [];
$aiSuggestion = null;

if ($selectedUser && $pid) {
    $scores = calculateScores($selectedId, $pid);

    // Fallback trait query
    if (empty($scores['byTrait'])) {
        $traitRows = Database::fetchAll("
            SELECT t.id, t.code, t.name,
                   ROUND(AVG(r.grade), 2) as avg_grade
            FROM traits t
            JOIN standard_traits st ON t.id = st.trait_id
            JOIN standards s ON st.standard_id = s.id
            JOIN questions q ON q.standard_id = s.id
            JOIN responses r ON r.question_id = q.id
            JOIN assignments a ON r.assignment_id = a.id
            WHERE a.evaluatee_id = ? AND a.period_id = ?
            GROUP BY t.id, t.code, t.name
            ORDER BY t.code
        ", [$selectedId, $pid]);
        foreach ($traitRows as $t) {
            $scores['byTrait'][$t['id']] = [
                'name' => $t['name'],
                'code' => $t['code'],
                'avg'  => round(floatval($t['avg_grade']), 2),
            ];
        }
    }

    $aiSuggestion = Database::fetchOne(
        "SELECT * FROM ai_suggestions WHERE evaluatee_id=? AND period_id=?",
        [$selectedId, $pid]
    );
}

// Group evaluatees by role for sidebar
$evaluatees = Database::fetchAll("
    SELECT DISTINCT u.id, u.name, u.role,
        COUNT(DISTINCT a.id) as total_assign,
        SUM(CASE WHEN a.status='completed' THEN 1 ELSE 0 END) as completed
    FROM users u
    JOIN assignments a ON a.evaluatee_id = u.id AND a.period_id = ?
    WHERE u.role IN ('leader','teacher')
    GROUP BY u.id, u.name, u.role
    ORDER BY u.role, u.name
", [$pid]);

$evalByRole = [];
foreach ($evaluatees as $ev) $evalByRole[$ev['role']][] = $ev;

// Group byStandard per domain for accordion
$byDomainAccordion = [];
if (!empty($scores['byStandard'])) {
    foreach ($scores['byStandard'] as $sid => $s) {
        $byDomainAccordion[$s['domain_name']][] = array_merge($s, ['sid' => $sid]);
    }
}

// Sorted data (terbesar ke terkecil default)
$sortOrder = $_GET['sort'] ?? 'desc'; // desc, asc, default

function sortScores(array $arr, string $order): array {
    if ($order === 'default') return $arr;
    usort($arr, fn($a,$b) => $order === 'desc'
        ? $b['avg'] <=> $a['avg']
        : $a['avg'] <=> $b['avg']
    );
    return $arr;
}

$sortedDomains = !empty($scores['byDomain'])
    ? sortScores($scores['byDomain'], $sortOrder) : [];
$sortedTraits  = !empty($scores['byTrait'])
    ? sortScores(array_values($scores['byTrait']), $sortOrder) : [];

// Trait & Domain data for radar
$traitLabels  = array_values(array_column($sortedTraits, 'name'));
$traitData    = array_values(array_map(fn($t) => (float)$t['avg'], $sortedTraits));
$domainLabels = array_values(array_column($sortedDomains, 'name'));
$domainData   = array_values(array_map(fn($d) => (float)$d['avg'], $sortedDomains));

ob_start(); ?>

<style>
/* Sidebar */
.sidebar-sticky { position: sticky; top: 80px; }
.people-sidebar {
  max-height: calc(100vh - 140px);
  overflow-y: scroll;
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,.25) transparent;
  background: var(--ktb-navy);
}
.people-sidebar::-webkit-scrollbar { width: 4px; }
.people-sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.25); border-radius: 2px; }

.role-group:nth-child(odd)  .role-group-body { background: #001f3e; }
.role-group:nth-child(even) .role-group-body { background: #001628; }
.sidebar-role-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 10px 14px 8px; cursor: pointer; user-select: none;
  font-size: .72rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .1em; color: rgba(255,255,255,.6);
  transition: background .15s;
}
.role-group:nth-child(odd)  .sidebar-role-header { background: #001628; }
.role-group:nth-child(even) .sidebar-role-header { background: #001020; }
.role-chevron { transition: transform .2s; font-size: .65rem; }
.person-item {
  display: flex; flex-direction: column; gap: 4px;
  padding: 8px 14px; text-decoration: none;
  color: rgba(255,255,255,.72); border-left: 3px solid transparent;
  transition: all .12s;
}
.person-item:hover { background: rgba(255,255,255,.06); color: rgba(255,255,255,.95); }
.person-item.active { border-left-color: var(--ktb-gold); color: white; font-weight: 600; background: rgba(255,201,1,.08); }

/* Section accordion */
.report-section-header {
  display: flex; justify-content: space-between; align-items: center;
  cursor: pointer; user-select: none; padding: 14px 20px;
}
.section-chevron { transition: transform .25s; opacity: .7; font-size: .8rem; }
.report-section-header[aria-expanded="false"] .section-chevron { transform: rotate(-90deg); }

/* Progress bar animation */
.prog-bar { height: 0; width: 0; transition: width 1s cubic-bezier(.4,0,.2,1); border-radius: 6px; }
.prog-bar.animate { width: var(--w) !important; }

/* Domain accordion in detail */
.domain-hdr {
  display: flex; justify-content: space-between; align-items: center;
  padding: 10px 16px; cursor: pointer; user-select: none;
  background: #f4f6fb; border-bottom: 1px solid #dee2e6;
  font-weight: 600; font-size: .83rem; color: var(--ktb-navy);
  transition: background .12s;
}
.domain-hdr:hover { background: #eef1fa; }
.d-chev { transition: transform .2s; font-size: .68rem; }
.domain-hdr[aria-expanded="false"] .d-chev { transform: rotate(-90deg); }

/* Sort buttons */
.sort-btns .btn { font-size: .75rem; padding: 2px 10px; }
.sort-btns .btn.active { background: var(--ktb-navy); color: white; border-color: var(--ktb-navy); }

/* Radar container */
.radar-wrap { position: relative; width: 100%; height: 320px; }
.radar-wrap canvas { position: absolute; inset: 0; }
</style>

<div class="row g-4">

  <!-- Period selector -->
  <div class="col-12">
    <div class="card py-2 px-3">
      <div class="d-flex align-items-center gap-3">
        <label class="text-muted small mb-0" style="white-space:nowrap">
          <i class="bi bi-calendar3 me-1"></i>Periode:
        </label>
        <div class="d-flex gap-2 flex-wrap">
          <?php foreach ($allPeriods as $ap): ?>
          <a href="?period_id=<?= $ap['id'] ?>&sort=<?= $sortOrder ?>"
             class="btn btn-sm <?= $ap['id']==$selectedPid ? 'btn-navy' : 'btn-outline-secondary' ?>">
            <?= h($ap['name']) ?>
            <?php if ($ap['status']==='active'): ?>
            <span class="badge bg-success ms-1" style="font-size:.6rem">Aktif</span>
            <?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ── SIDEBAR ─────────────────────────────────────────── -->
  <div class="col-md-3 sidebar-sticky">
    <div class="card overflow-hidden">
      <div class="card-header py-2">
        <i class="bi bi-people me-2"></i>Pilih Orang
        <span class="badge bg-light text-dark ms-1"><?= count($evaluatees) ?></span>
      </div>
      <div class="people-sidebar">
        <?php
        $roleLabels = ['leader'=>'Pimpinan Sekolah','teacher'=>'Guru'];
        $ri = 0;
        foreach ($evalByRole as $role => $people):
          $ri++;
          $cid = 'sr'.$ri;
        ?>
        <div class="role-group">
          <div class="sidebar-role-header"
               data-bs-toggle="collapse" data-bs-target="#<?= $cid ?>"
               aria-expanded="true">
            <span><?= h($roleLabels[$role] ?? roleLabel($role)) ?></span>
            <div class="d-flex align-items-center gap-2">
              <span style="background:rgba(255,255,255,.12);padding:1px 8px;border-radius:10px;font-size:.65rem">
                <?= count($people) ?>
              </span>
              <i class="bi bi-chevron-down role-chevron"></i>
            </div>
          </div>
          <div class="collapse show role-group-body" id="<?= $cid ?>">
            <?php foreach ($people as $ev):
              $pct = $ev['total_assign'] > 0 ? round($ev['completed']/$ev['total_assign']*100) : 0;
              $isActive = $selectedId === (int)$ev['id'];
            ?>
            <a href="?period_id=<?= $selectedPid ?>&user_id=<?= $ev['id'] ?>&sort=<?= $sortOrder ?>"
               class="person-item <?= $isActive?'active':'' ?>">
              <div class="d-flex justify-content-between align-items-center">
                <span style="font-size:.82rem"><?= h($ev['name']) ?></span>
                <span style="font-size:.68rem;opacity:.55"><?= $pct ?>%</span>
              </div>
              <div style="height:3px;border-radius:2px;background:rgba(255,255,255,.1)">
                <div style="height:100%;width:<?= $pct ?>%;border-radius:2px;background:<?= $pct>=80?'#198754':'#ffc901' ?>"></div>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <div style="height:12px"></div>
      </div>
    </div>
  </div>

  <!-- ── MAIN PANEL ──────────────────────────────────────── -->
  <div class="col-md-9">

    <?php if (!$selectedUser): ?>
    <div class="card">
      <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-bar-chart-line display-4 mb-3 d-block"></i>
        <p>Pilih nama dari daftar kiri untuk melihat laporan kinerja.</p>
      </div>
    </div>

    <?php else: ?>

    <!-- HEADER — STICKY -->
    <div class="card mb-3" id="reportHeader">
      <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-start">
          <div class="d-flex align-items-center gap-3">
            <div class="avatar-sm" style="width:48px;height:48px;font-size:1rem;flex-shrink:0">
              <?= h(avatarInitials($selectedUser['name'])) ?>
            </div>
            <div>
              <h6 class="fw-bold text-navy mb-0"><?= h($selectedUser['name']) ?></h6>
              <div class="d-flex align-items-center gap-2 mt-1">
                <span class="badge badge-navy" style="font-size:.7rem"><?= h(roleLabel($selectedUser['role'])) ?></span>
                <span class="text-muted small"><?= h($period['name'] ?? '') ?></span>
              </div>
            </div>
          </div>
          <?php if (!empty($scores) && $scores['overall'] > 0):
            $ol = getScoreLevel($scores['overall']);
          ?>
          <div class="text-center flex-shrink-0">
            <div class="score-ring" style="color:<?= $ol['color'] ?>;border-color:<?= $ol['color'] ?>;width:56px;height:56px;font-size:1.1rem">
              <?= formatScore($scores['overall']) ?>
            </div>
            <div class="small fw-bold mt-1" style="color:<?= $ol['color'] ?>;font-size:.75rem">
              <?= $ol['label_id'] ?>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- LEGENDA SKALA -->
        <div class="mt-2 pt-2 border-top">
          <div class="d-flex flex-wrap gap-1 align-items-center">
            <span class="text-muted" style="font-size:.68rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-right:4px">Skala:</span>
            <?php
            $legends = [
              ['1.00–1.24', 'Sangat Kurang', '#991b1b', '#fef2f2'],
              ['1.25–1.74', 'Kurang',        '#dc2626', '#fef2f2'],
              ['1.75–2.24', 'Cukup',         '#ea580c', '#fff7ed'],
              ['2.25–2.74', 'Cukup Baik',    '#d97706', '#fffbeb'],
              ['2.75–3.24', 'Baik',          '#0891b2', '#ecfeff'],
              ['3.25–3.74', 'Sangat Baik',   '#2563eb', '#eff6ff'],
              ['3.75–3.99', 'Luar Biasa',    '#16a34a', '#f0fdf4'],
              ['4.00',      'Sempurna',      '#15803d', '#dcfce7'],
            ];
            foreach ($legends as [$range, $label, $color, $bg]):
            ?>
            <span style="background:<?= $bg ?>;color:<?= $color ?>;border:1px solid <?= $color ?>;border-radius:4px;padding:1px 7px;font-size:.65rem;font-weight:600;white-space:nowrap">
              <?= $range ?> <?= $label ?>
            </span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

<style>
/* Sticky header — JS handle ini agar tidak ada gap */
#reportHeader.is-fixed {
  position: fixed;
  top: 60px;
  z-index: 200;
  box-shadow: 0 4px 20px rgba(0,0,0,.15);
  border-radius: 0 0 8px 8px;
  background: white;
  left: var(--hdr-left);
  width: var(--hdr-width);
}
#reportHeaderSpacer { display: none; }
#reportHeaderSpacer.active { display: block; }
</style>
<div id="reportHeaderSpacer"></div>
<script>
(function(){
  const hdr = document.getElementById('reportHeader');
  const spacer = document.getElementById('reportHeaderSpacer');
  if (!hdr || !spacer) return;

  const nav = document.querySelector('.ktb-navbar');
  const navH = nav ? nav.offsetHeight + 4 : 64;

  let origTop, origH, origLeft, origWidth;

  function measure() {
    const rect = hdr.getBoundingClientRect();
    origTop   = rect.top + window.scrollY;
    origH     = hdr.offsetHeight;
    origLeft  = rect.left;
    origWidth = rect.width;
  }

  function onScroll() {
    if (!origTop) measure();
    if (window.scrollY > origTop - navH) {
      hdr.classList.add('is-fixed');
      hdr.style.setProperty('--hdr-left',  origLeft + 'px');
      hdr.style.setProperty('--hdr-width', origWidth + 'px');
      spacer.style.height = origH + 'px';
      spacer.classList.add('active');
    } else {
      hdr.classList.remove('is-fixed');
      spacer.classList.remove('active');
    }
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', () => { origTop = null; measure(); onScroll(); });
  measure();
})();
</script>

    <?php if (!empty($scores) && $scores['overall'] > 0): ?>

    <!-- Sort controls -->
    <div class="d-flex justify-content-end mb-2 gap-2 align-items-center">
      <small class="text-muted">Urutan:</small>
      <div class="sort-btns btn-group btn-group-sm">
        <a href="?period_id=<?= $selectedPid ?>&user_id=<?= $selectedId ?>&sort=desc"
           class="btn btn-outline-secondary <?= $sortOrder==='desc'?'active':'' ?>">
          ↓ Terbesar
        </a>
        <a href="?period_id=<?= $selectedPid ?>&user_id=<?= $selectedId ?>&sort=asc"
           class="btn btn-outline-secondary <?= $sortOrder==='asc'?'active':'' ?>">
          ↑ Terkecil
        </a>
        <a href="?period_id=<?= $selectedPid ?>&user_id=<?= $selectedId ?>&sort=default"
           class="btn btn-outline-secondary <?= $sortOrder==='default'?'active':'' ?>">
          ≡ Normal
        </a>
      </div>
    </div>

    <!-- ── ROW: DOMAIN + TRAITS ─────────────────────────── -->
    <div class="row g-3 mb-3">

      <!-- Skor per Domain -->
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-header report-section-header"
               data-bs-toggle="collapse" data-bs-target="#secDomain" aria-expanded="true">
            <span><i class="bi bi-bar-chart me-2"></i>Skor per Domain</span>
            <i class="bi bi-chevron-down section-chevron"></i>
          </div>
          <div class="collapse show" id="secDomain">
            <div class="card-body">
              <?php foreach ($sortedDomains as $d):
                $dl = getScoreLevel($d['avg']);
              ?>
              <div class="mb-3">
                <div class="d-flex justify-content-between mb-1">
                  <span class="small fw-semibold"><?= h($d['name']) ?></span>
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge" style="background:<?= $dl['bg'] ?>;color:<?= $dl['color'] ?>;border:1px solid <?= $dl['color'] ?>;font-size:.68rem">
                      <?= $dl['label_id'] ?>
                    </span>
                    <strong class="small" style="color:<?= $dl['color'] ?>">
                      <?= formatScore($d['avg']) ?>
                    </strong>
                  </div>
                </div>
                <div style="height:9px;border-radius:6px;background:#eee;overflow:hidden">
                  <div class="prog-bar" data-w="<?= ($d['avg']/4)*100 ?>"
                       style="--w:<?= ($d['avg']/4)*100 ?>%;height:100%;background:<?= $dl['color'] ?>">
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Skor per Traits -->
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-header report-section-header"
               data-bs-toggle="collapse" data-bs-target="#secTraits" aria-expanded="true">
            <span><i class="bi bi-tags me-2"></i>Skor per Traits</span>
            <i class="bi bi-chevron-down section-chevron"></i>
          </div>
          <div class="collapse show" id="secTraits">
            <div class="card-body">
              <?php if (empty($sortedTraits)): ?>
              <p class="text-muted small text-center py-3">
                Belum ada data traits.
                <a href="<?= APP_URL ?>/admin/foundation.php?tab=mapping">Isi mapping →</a>
              </p>
              <?php else: ?>
              <?php foreach ($sortedTraits as $trait):
                $tl = getScoreLevel($trait['avg']);
              ?>
              <div class="mb-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="trait-chip"><?= h($trait['name']) ?></span>
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge" style="background:<?= $tl['bg'] ?>;color:<?= $tl['color'] ?>;border:1px solid <?= $tl['color'] ?>;font-size:.65rem">
                      <?= $tl['label_id'] ?>
                    </span>
                    <strong class="small" style="color:<?= $tl['color'] ?>">
                      <?= formatScore($trait['avg']) ?>
                    </strong>
                  </div>
                </div>
                <div style="height:6px;border-radius:4px;background:#eee;overflow:hidden">
                  <div class="prog-bar" data-w="<?= ($trait['avg']/4)*100 ?>"
                       style="--w:<?= ($trait['avg']/4)*100 ?>%;height:100%;background:<?= $tl['color'] ?>">
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── RADAR: TRAITS + DOMAIN ────────────────────────── -->
    <?php if (!empty($traitLabels) || !empty($domainLabels)): ?>
    <div class="card mb-3">
      <div class="card-header report-section-header"
           data-bs-toggle="collapse" data-bs-target="#secRadar" aria-expanded="true">
        <span><i class="bi bi-diagram-3 me-2"></i>Profil Kinerja (Radar)</span>
        <i class="bi bi-chevron-down section-chevron"></i>
      </div>
      <div class="collapse show" id="secRadar">
        <div class="card-body">
          <div class="row g-3">
            <!-- Radar Traits -->
            <?php if (!empty($traitLabels)): ?>
            <div class="col-md-6">
              <div class="small fw-semibold text-center text-muted mb-2 text-uppercase">
                <i class="bi bi-tags me-1"></i>Traits
              </div>
              <div class="radar-wrap">
                <canvas id="radarTraits"></canvas>
              </div>
            </div>
            <?php endif; ?>
            <!-- Radar Domain -->
            <?php if (!empty($domainLabels)): ?>
            <div class="col-md-6">
              <div class="small fw-semibold text-center text-muted mb-2 text-uppercase">
                <i class="bi bi-bar-chart me-1"></i>Domain
              </div>
              <div class="radar-wrap">
                <canvas id="radarDomain"></canvas>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── DETAIL PER STANDAR ───────────────────────────── -->
    <div class="card mb-3">
      <div class="card-header report-section-header"
           data-bs-toggle="collapse" data-bs-target="#secDetail" aria-expanded="true">
        <span><i class="bi bi-list-ul me-2"></i>Detail per Standar</span>
        <i class="bi bi-chevron-down section-chevron"></i>
      </div>
      <div class="collapse show" id="secDetail">
        <div class="card-body p-0">
          <table class="table mb-0 small" style="table-layout:fixed">
            <thead><tr>
              <th style="width:42%;padding-left:14px">Standar</th>
              <th style="width:14%" class="text-center">Skor</th>
              <th style="width:25%">Level</th>
            </tr></thead>
          </table>
          <?php
          $dIdx = 0;
          foreach ($byDomainAccordion as $domainName => $standards):
            $dIdx++;
            $cid2 = 'dd'.$dIdx;
            $domainAvg = round(array_sum(array_column($standards,'avg'))/count($standards),2);
            $ddl = getScoreLevel($domainAvg);

            // Sort standards within domain
            if ($sortOrder === 'desc') {
                usort($standards, fn($a,$b) => $b['avg'] <=> $a['avg']);
            } elseif ($sortOrder === 'asc') {
                usort($standards, fn($a,$b) => $a['avg'] <=> $b['avg']);
            }
          ?>
          <div class="domain-hdr"
               data-bs-toggle="collapse" data-bs-target="#<?= $cid2 ?>"
               aria-expanded="true">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-chevron-down d-chev"></i>
              <span><?= h($domainName) ?></span>
              <span class="badge bg-secondary" style="font-size:.63rem"><?= count($standards) ?></span>
            </div>
            <span class="badge" style="background:<?= $ddl['bg'] ?>;color:<?= $ddl['color'] ?>;border:1px solid <?= $ddl['color'] ?>;font-size:.72rem">
              <?= formatScore($domainAvg) ?> — <?= $ddl['label_id'] ?>
            </span>
          </div>
          <div class="collapse show" id="<?= $cid2 ?>">
            <table class="table table-hover mb-0 small" style="table-layout:fixed">
              <tbody>
                <?php foreach ($standards as $s):
                  $sl = getScoreLevel($s['avg']);
                ?>
                <tr>
                  <td style="width:42%;padding-left:30px"><?= h($s['name']) ?></td>
                  <td style="width:14%" class="text-center">
                    <strong style="color:<?= $sl['color'] ?>"><?= formatScore($s['avg']) ?></strong>
                  </td>
                  <td style="width:25%">
                    <span class="badge" style="background:<?= $sl['bg'] ?>;color:<?= $sl['color'] ?>;border:1px solid <?= $sl['color'] ?>;font-size:.7rem">
                      <?= $sl['label_id'] ?>
                    </span>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <?php endif; // scores ?>

    <!-- ── AI SUGGESTIONS ───────────────────────────────── -->
    <div class="card mb-3">
      <div class="card-header report-section-header"
           data-bs-toggle="collapse" data-bs-target="#secAI" aria-expanded="true">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-stars me-1"></i>Saran Pengembangan (AI-Assisted)
        </div>
        <div class="d-flex align-items-center gap-2">
          <?php if (!empty($scores) && $scores['overall'] > 0): ?>
          <button class="btn btn-sm btn-outline-light" id="btn-generate-ai"
            onclick="event.stopPropagation();generateAISuggestion(<?= $selectedId ?>, <?= $pid ?>)">
            <i class="bi bi-stars me-1"></i>Generate
          </button>
          <?php endif; ?>
          <i class="bi bi-chevron-down section-chevron"></i>
        </div>
      </div>
      <div class="collapse show" id="secAI">
        <div class="card-body">
          <?php if ($aiSuggestion): ?>
          <div class="ai-box">
            <div class="d-flex justify-content-between mb-2">
              <span class="ai-badge">AI-Generated</span>
              <small class="text-muted"><?= date('d M Y H:i', strtotime($aiSuggestion['generated_at'])) ?></small>
            </div>
            <form method="POST" action="<?= APP_URL ?>/api/ai.php">
              <input type="hidden" name="action" value="save_edit">
              <input type="hidden" name="evaluatee_id" value="<?= $selectedId ?>">
              <input type="hidden" name="period_id" value="<?= $pid ?>">
              <textarea name="edited_suggestion" class="form-control mb-2" rows="10" id="ai-text"
                style="font-family:inherit;line-height:1.7"><?= h($aiSuggestion['edited_suggestion'] ?? $aiSuggestion['raw_suggestion']) ?></textarea>
              <button type="submit" class="btn btn-sm btn-navy">
                <i class="bi bi-save me-1"></i>Simpan Editan
              </button>
            </form>
          </div>
          <?php else: ?>
          <div class="text-center text-muted py-4">
            <i class="bi bi-stars display-4 mb-2 d-block"></i>
            <p>Belum ada saran AI. Klik "Generate" untuk membuat.</p>
            <textarea id="ai-text" class="form-control d-none" rows="8"></textarea>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php endif; // selectedUser ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

  // Progress bar animation
  const observer = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        setTimeout(() => e.target.classList.add('animate'), 120);
        observer.unobserve(e.target);
      }
    });
  }, { threshold: 0.1 });
  document.querySelectorAll('.prog-bar').forEach(b => observer.observe(b));

  // Accordion chevron sync
  document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(el => {
    el.addEventListener('click', function() {
      const chev = this.querySelector('.section-chevron,.d-chev,.role-chevron');
      if (!chev) return;
      const target = document.querySelector(this.dataset.bsTarget);
      if (!target) return;
      const opening = !target.classList.contains('show');
      chev.style.transform = opening ? '' : 'rotate(-90deg)';
    });
  });

  const radarOpts = (labels, data, color) => ({
    type: 'radar',
    data: {
      labels,
      datasets: [{
        label: 'Skor',
        data,
        backgroundColor: color + '18',
        borderColor: color,
        borderWidth: 2,
        pointBackgroundColor: '#ffc901',
        pointBorderColor: '#001f3e',
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 1200, easing: 'easeInOutQuart' },
      scales: {
        r: {
          min: 0, max: 4,
          ticks: { stepSize: 1, font: { size: 9 }, backdropColor: 'transparent', color: '#888' },
          pointLabels: { font: { size: 9, weight: '500' }, color: '#333' },
          grid: { color: 'rgba(0,0,0,0.06)' },
          angleLines: { color: 'rgba(0,0,0,0.06)' },
        }
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => ' ' + parseFloat(ctx.raw).toFixed(2) + ' / 4.00'
          }
        }
      }
    }
  });

  setTimeout(() => {
    <?php if (!empty($traitLabels)): ?>
    const tc = document.getElementById('radarTraits');
    if (tc) new Chart(tc, radarOpts(
      <?= json_encode($traitLabels) ?>,
      <?= json_encode($traitData) ?>,
      '#2c2bff'
    ));
    <?php endif; ?>

    <?php if (!empty($domainLabels)): ?>
    const dc = document.getElementById('radarDomain');
    if (dc) new Chart(dc, radarOpts(
      <?= json_encode($domainLabels) ?>,
      <?= json_encode($domainData) ?>,
      '#ffc901'
    ));
    <?php endif; ?>
  }, 350);

});
</script>

<?php $content = ob_get_clean(); pageWrapper('Laporan Kinerja', $content); ?>