<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['teacher','leader']);

$user = currentUser();
$uid  = (int)$user['id'];

// Ambil semua periode yang punya data untuk user ini
$periods = Database::fetchAll("
    SELECT DISTINCT ep.id, ep.name, ep.status, ep.start_date, ep.end_date
    FROM eval_periods ep
    JOIN assignments a ON a.period_id = ep.id
    WHERE a.evaluatee_id = ? AND ep.status IN ('closed','active')
    ORDER BY ep.start_date DESC
", [$uid]);

if (empty($periods)) {
    ob_start();
    echo showFlash();
    ?>
    <div style="text-align:center;padding:60px 20px;color:#6f6e85">
        <i class="bi bi-bar-chart" style="font-size:48px;display:block;margin-bottom:16px;opacity:.4"></i>
        <h5 style="color:#2f2d4d;font-weight:600">Belum Ada Data Evaluasi</h5>
        <p style="font-size:13px">Data laporan kinerja Anda akan muncul setelah evaluasi berlangsung.</p>
    </div>
    <?php
    $content = ob_get_clean();
    pageWrapper('Laporan Kinerja Saya', $content);
    exit;
}

// Periode yang dipilih
$selectedPid = (int)($_GET['period_id'] ?? $periods[0]['id']);
$curPeriod   = Database::fetchOne("SELECT * FROM eval_periods WHERE id=?", [$selectedPid]);

// Hitung skor
$scores = calculateScores($uid, $selectedPid);

// Breakdown per tipe responden
// Tipe responden dihitung lebih dulu di subkueri, baru dikelompokkan.
// MySQL 8 dengan only_full_group_by menolak bila CASE-nya ditulis
// langsung di SELECT list: kolom p.is_self_reflection di dalam
// subkueri n_total tidak dianggap bergantung pada alias rtype.
$byRespondent = Database::fetchAll("
    SELECT
        x.rtype,
        COUNT(DISTINCT x.evaluator_id) AS n_done,
        (SELECT COUNT(*) FROM assignments a2 JOIN packages p2 ON p2.id=a2.package_id
         WHERE a2.evaluatee_id=? AND a2.period_id=?
         AND (CASE WHEN p2.is_self_reflection=1 THEN 'self' ELSE p2.respondent_type END)
             = x.rtype
        ) AS n_total,
        ROUND(AVG(x.grade),2) AS avg_grade
    FROM (
        SELECT
            CASE WHEN p.is_self_reflection=1 THEN 'self' ELSE p.respondent_type END AS rtype,
            a.evaluator_id,
            r.grade
        FROM responses r
        JOIN assignments a ON r.assignment_id = a.id
        JOIN packages p    ON a.package_id    = p.id
        WHERE a.evaluatee_id=? AND a.period_id=?
    ) x
    GROUP BY x.rtype
    ORDER BY avg_grade DESC
", [$uid, $selectedPid, $uid, $selectedPid]);

$isPreliminary = $curPeriod && $curPeriod['status'] !== 'closed';
$overall = (float)($scores['overall'] ?? 0);

ob_start();
?>
<style>
.rep-hero{background:radial-gradient(520px 320px at 12% 0%,rgba(34,1,178,.5) 0%,transparent 68%),radial-gradient(420px 300px at 100% 110%,rgba(255,145,1,.18) 0%,transparent 62%),linear-gradient(150deg,#02001f 0%,#040136 55%,#030870 100%);color:#fff;border-radius:16px;padding:24px 28px;margin-bottom:20px}
.rep-hero-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.rep-name{font-size:18px;font-weight:600}
.rep-sub{font-size:12px;opacity:.75;margin-top:3px}
.rep-pct{text-align:right}
.rep-score-big{font-size:36px;font-weight:700;line-height:1}
.rep-score-lbl{font-size:11px;opacity:.7;margin-top:3px}
.rep-bar{height:8px;border-radius:4px;background:rgba(255,255,255,.15);overflow:hidden;margin-bottom:16px}
.rep-bar-fill{height:100%;border-radius:4px}
.prelim-badge{display:inline-flex;align-items:center;gap:5px;background:#fff1dc;color:#b83a01;border-radius:20px;padding:3px 12px;font-size:11px;font-weight:600}

.period-tabs{display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap}
.period-tab{padding:6px 16px;border-radius:20px;font-size:12px;font-weight:500;border:0.5px solid #e3e5ea;background:#fff;color:#6b6a83;text-decoration:none}
.period-tab.active{background:#040136;color:#fff;border-color:#040136}

.rep-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px}
.rep-card{background:#fff;border:0.5px solid #e3e5ea;border-radius:12px;padding:18px}
.rep-card-title{font-size:11px;font-weight:600;color:#6b6a83;text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px}
.domain-row{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.domain-name{font-size:12px;color:#2f2d4d;flex:1}
.domain-bar{flex:1;height:6px;border-radius:3px;background:#f3f4f6;overflow:hidden}
.domain-fill{height:100%;border-radius:3px;background:#040136}
.domain-score{font-size:12px;font-weight:600;color:#040136;width:36px;text-align:right}
.resp-chip{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:0.5px solid #f3f4f6}
.resp-chip:last-child{border-bottom:none}
.resp-lbl{font-size:12px;color:#2f2d4d}
.resp-meta{font-size:11px;color:#6f6e85}
.resp-score{font-size:13px;font-weight:600;color:#040136}

.trait-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:8px 16px}
.trait-row{display:flex;align-items:center;gap:10px}
.trait-name{font-size:12px;color:#2f2d4d;flex:1}
.trait-bar{width:80px;height:5px;border-radius:3px;background:#f3f4f6;overflow:hidden}
.trait-fill{height:100%;border-radius:3px}
.trait-score{font-size:12px;font-weight:600;color:#040136;width:32px;text-align:right}

@media(max-width:768px){.rep-grid{grid-template-columns:1fr}}
</style>

<?php if ($isPreliminary): ?>
<div style="background:#fff1dc;border:1px solid #ffc36b;border-radius:10px;padding:10px 16px;margin-bottom:14px;font-size:12px;color:#b83a01;display:flex;align-items:center;gap:8px">
    <i class="bi bi-hourglass-split"></i>
    Data <strong><?= h($curPeriod['name']) ?></strong> masih bersifat <strong>sementara</strong> — periode evaluasi masih berjalan.
</div>
<?php endif; ?>

<!-- PERIOD TABS -->
<?php if (count($periods) > 1): ?>
<div class="period-tabs">
    <?php foreach ($periods as $p): ?>
    <a href="?period_id=<?= $p['id'] ?>" class="period-tab <?= $p['id']==$selectedPid?'active':'' ?>">
        <?= h($p['name']) ?>
        <?php if ($p['status']==='active'): ?>
        <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#d97701;margin-left:4px;vertical-align:middle"></span>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- HERO -->
<div class="rep-hero">
    <div class="rep-hero-top">
        <div>
            <div class="rep-name"><?= h($user['name']) ?></div>
            <div class="rep-sub"><?= h(roleLabel($user['role'])) ?> · <?= h($curPeriod['name'] ?? '') ?></div>
            <?php if ($isPreliminary): ?>
            <div style="margin-top:8px"><span class="prelim-badge"><i class="bi bi-hourglass-split"></i>Sementara</span></div>
            <?php endif; ?>
        </div>
        <div class="rep-pct">
            <?php if ($overall > 0): ?>
            <div class="rep-score-big"><?= number_format($overall,2) ?></div>
            <div class="rep-score-lbl">/ 4.00 · <?= h(getScoreLevel($overall)['label_id']) ?></div>
            <?php else: ?>
            <div class="rep-score-big" style="opacity:.4">—</div>
            <div class="rep-score-lbl">Belum ada data</div>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($overall > 0): ?>
    <div class="rep-bar">
        <div class="rep-bar-fill" style="width:<?= round($overall/4*100) ?>%;background:<?= $overall>=3?'#43c58c':($overall>=2.5?'#ffc36b':'#d9483d') ?>"></div>
    </div>
    <?php endif; ?>
</div>

<?php if (empty($scores['byDomain'])): ?>
<div style="text-align:center;padding:40px;color:#6f6e85;font-size:13px;background:#fff;border-radius:12px;border:0.5px solid #e3e5ea">
    <i class="bi bi-inbox" style="font-size:28px;display:block;margin-bottom:8px;opacity:.4"></i>
    Belum ada respons masuk untuk periode ini.
</div>
<?php else: ?>

<!-- DOMAIN & RESPONDENT -->
<div class="rep-grid">
    <!-- Skor per Domain -->
    <div class="rep-card">
        <div class="rep-card-title">Skor per Domain</div>
        <?php foreach ($scores['byDomain'] as $dom):
            $pct = round($dom['avg']/4*100);
            $col = $dom['avg']>=3?'#027a48':($dom['avg']>=2.5?'#a85a01':'#b42318');
        ?>
        <div class="domain-row">
            <div class="domain-name"><?= h($dom['name']) ?></div>
            <div class="domain-bar"><div class="domain-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
            <div class="domain-score"><?= number_format($dom['avg'],2) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Breakdown per Responden -->
    <div class="rep-card">
        <div class="rep-card-title">Skor per Kelompok Penilai</div>
        <?php if (empty($byRespondent)): ?>
        <p style="font-size:12px;color:#6f6e85">Belum ada data</p>
        <?php else: ?>
        <?php foreach ($byRespondent as $r): ?>
        <div class="resp-chip">
            <div>
                <div class="resp-lbl"><?= h(respondentLabel($r['rtype'])) ?></div>
                <div class="resp-meta"><?= $r['n_done'] ?> responden</div>
            </div>
            <div class="resp-score"><?= number_format($r['avg_grade'],2) ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- TRAIT -->
<?php if (!empty($scores['byTrait'])): ?>
<div class="rep-card" style="margin-bottom:18px">
    <div class="rep-card-title">Skor per IB Learner Profile Trait</div>
    <div class="trait-grid">
        <?php foreach ($scores['byTrait'] as $t):
            $tpct = round($t['avg']/4*100);
            $tcol = $t['avg']>=3?'#027a48':($t['avg']>=2.5?'#a85a01':'#b42318');
        ?>
        <div class="trait-row">
            <div class="trait-name"><?= h($t['name']) ?></div>
            <div class="trait-bar"><div class="trait-fill" style="width:<?= $tpct ?>%;background:<?= $tcol ?>"></div></div>
            <div class="trait-score"><?= number_format($t['avg'],2) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php
$content = ob_get_clean();
pageWrapper('Laporan Kinerja Saya', $content);
