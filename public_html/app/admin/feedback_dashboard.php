<?php
// AGKB 360° — Dashboard Feedback & Ticketing
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin','admin','foundation','leader','pemantau']);
$user = currentUser();

$tracks = fbAllowedTracks($user);
if (!$tracks) { http_response_code(403); include BASE_PATH . '/includes/403.php'; exit; }
$ph = implode(',', array_fill(0, count($tracks), '?'));

$days   = max(7, min(365, (int)($_GET['days'] ?? 90)));
$params = $tracks; // \$days diinterpolasi langsung (sudah divalidasi int)

// Metrik semua status mengecualikan tiket tester
$k = Database::fetchOne(
    "SELECT
       COUNT(*) AS masuk,
       SUM(status IN ('baru','ditinjau','ditindaklanjuti')) AS proses,
       SUM(status = 'menunggu_pelapor') AS onhold,
       SUM(status IN ('selesai','ditutup')) AS selesai,
       SUM(status IN ('baru','ditinjau','ditindaklanjuti') AND due_at < NOW()) AS terlambat
     FROM feedback_tickets
     WHERE is_test = 0 AND track IN ($ph) AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)", $params);

// Waktu penanganan — rata-rata & median
$times = Database::fetchAll(
    "SELECT TIMESTAMPDIFF(MINUTE, created_at, resolved_at) AS m
     FROM feedback_tickets
     WHERE is_test = 0 AND track IN ($ph) AND resolved_at IS NOT NULL
       AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
     ORDER BY m", $params);
$mins   = array_map(fn($r) => (int)$r['m'], $times);
$avgMin = $mins ? array_sum($mins) / count($mins) : 0;
$medMin = $mins ? $mins[intdiv(count($mins), 2)] : 0;

$resp = Database::fetchOne(
    "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) AS m
     FROM feedback_tickets
     WHERE is_test = 0 AND track IN ($ph) AND first_response_at IS NOT NULL
       AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)", $params);

// Kepatuhan SLA
$sla = Database::fetchOne(
    "SELECT COUNT(*) AS total, SUM(resolved_at <= due_at) AS tepat
     FROM feedback_tickets
     WHERE is_test = 0 AND track IN ($ph) AND resolved_at IS NOT NULL AND due_at IS NOT NULL
       AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)", $params);
$slaPct = (int)$sla['total'] ? round(100 * (int)$sla['tepat'] / (int)$sla['total']) : null;

$perCat = Database::fetchAll(
    "SELECT c.name, COUNT(*) AS n,
            AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at)) AS avg_h,
            SUM(t.status IN ('selesai','ditutup')) AS selesai
     FROM feedback_tickets t LEFT JOIN feedback_categories c ON c.id=t.category_id
     WHERE t.is_test = 0 AND t.track IN ($ph) AND t.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
     GROUP BY c.id, c.name ORDER BY n DESC", $params);

$perRes = Database::fetchAll(
    "SELECT resolution_type, COUNT(*) AS n FROM feedback_tickets
     WHERE is_test = 0 AND track IN ($ph) AND resolution_type IS NOT NULL
       AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
     GROUP BY resolution_type ORDER BY n DESC", $params);

$perPic = Database::fetchAll(
    "SELECT u.name, COUNT(*) AS aktif,
            SUM(t.status IN ('baru','ditinjau','ditindaklanjuti') AND t.due_at < NOW()) AS telat
     FROM feedback_tickets t JOIN users u ON u.id=t.assignee_id
     WHERE t.is_test = 0 AND t.track IN ($ph)
       AND t.status IN ('baru','ditinjau','ditindaklanjuti','menunggu_pelapor')
     GROUP BY u.id, u.name ORDER BY aktif DESC LIMIT 12", $tracks);

$trend = Database::fetchAll(
    "SELECT DATE_FORMAT(created_at,'%Y-%m') AS bln, COUNT(*) AS masuk,
            SUM(status IN ('selesai','ditutup')) AS selesai
     FROM feedback_tickets
     WHERE is_test = 0 AND track IN ($ph) AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
     GROUP BY bln ORDER BY bln", $params);

$late = Database::fetchAll(
    "SELECT t.*, c.name AS category_name, u.name AS assignee_name
     FROM feedback_tickets t
     LEFT JOIN feedback_categories c ON c.id=t.category_id
     LEFT JOIN users u ON u.id=t.assignee_id
     WHERE t.is_test = 0 AND t.track IN ($ph)
       AND t.status IN ('baru','ditinjau','ditindaklanjuti') AND t.due_at < NOW()
     ORDER BY t.due_at ASC LIMIT 12", $tracks);

$dur = fn($m) => $m <= 0 ? '—' : ($m < 60 ? round($m) . ' mnt' : ($m < 1440 ? round($m/60,1) . ' jam' : round($m/1440,1) . ' hari'));

ob_start(); ?>
<style>
.kpi-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(148px,1fr));gap:11px;margin-bottom:16px}
.kpi{background:#fff;border:1px solid #e3e5ea;border-radius:12px;padding:15px 17px;border-left:3px solid #040136}
.kpi.pr{border-left-color:#a85a01}.kpi.oh{border-left-color:#6b6a83}
.kpi.dn{border-left-color:#027a48}.kpi.lt{border-left-color:#b42318}
.kpi-n{font-size:27px;font-weight:700;color:#040136;line-height:1.05;letter-spacing:-.02em}
.kpi-l{font-size:11.5px;color:#6b6a83;margin-top:4px}
.kpi.lt .kpi-n{color:#b42318}.kpi.dn .kpi-n{color:#027a48}
.pnl{background:#fff;border:1px solid #e3e5ea;border-radius:12px;padding:16px 18px;margin-bottom:13px}
.pnl-t{font-size:10.5px;font-weight:700;color:#6b6a83;text-transform:uppercase;letter-spacing:.6px;margin-bottom:11px}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:13px}
.bar-row{margin-bottom:9px}
.bar-h{display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:3px}
.bar-h .n{color:#040136;font-weight:600}.bar-h .v{color:#6b6a83}
.bar-t{height:7px;background:#f3f4f6;border-radius:20px;overflow:hidden}
.bar-f{height:100%;background:#030870;border-radius:20px}
.lrow{display:flex;gap:9px;align-items:center;padding:9px 11px;border:1px solid #f3b5b0;background:#fffafa;border-radius:8px;margin-bottom:6px;font-size:12.5px;text-decoration:none;color:#040136}
.lrow:hover{background:#fdeceb;text-decoration:none}
.tm{display:flex;gap:20px;flex-wrap:wrap}
.tm-i .k{font-size:10.5px;color:#6b6a83;text-transform:uppercase;letter-spacing:.5px;font-weight:600}
.tm-i .v{font-size:19px;font-weight:700;color:#040136;margin-top:2px}
@media(max-width:900px){.g2{grid-template-columns:1fr}}
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="d-flex gap-1">
    <?php foreach ([30=>'30 hari',90=>'90 hari',180=>'6 bulan',365=>'1 tahun'] as $d=>$lb): ?>
    <a href="?days=<?= $d ?>" class="btn btn-sm <?= $days===$d?'btn-navy':'btn-outline-navy' ?> px-3"><?= $lb ?></a>
    <?php endforeach; ?>
  </div>
  <a href="<?= APP_URL ?>/admin/feedback.php" class="btn btn-sm btn-outline-navy px-3">
    <i class="bi bi-inbox me-1"></i>Kembali ke Inbox
  </a>
</div>

<div class="kpi-row">
  <div class="kpi"><div class="kpi-n"><?= (int)$k['masuk'] ?></div><div class="kpi-l">Masuk</div></div>
  <div class="kpi pr"><div class="kpi-n"><?= (int)$k['proses'] ?></div><div class="kpi-l">Sedang Proses</div></div>
  <div class="kpi oh"><div class="kpi-n"><?= (int)$k['onhold'] ?></div><div class="kpi-l">Menunggu Pelapor</div></div>
  <div class="kpi dn"><div class="kpi-n"><?= (int)$k['selesai'] ?></div><div class="kpi-l">Selesai</div></div>
  <div class="kpi lt"><div class="kpi-n"><?= (int)$k['terlambat'] ?></div><div class="kpi-l">Terlambat</div></div>
</div>

<div class="pnl">
  <div class="pnl-t">Waktu Penanganan</div>
  <div class="tm">
    <div class="tm-i"><div class="k">Respons pertama</div><div class="v"><?= $dur((float)($resp['m'] ?? 0)) ?></div></div>
    <div class="tm-i"><div class="k">Rata-rata selesai</div><div class="v"><?= $dur($avgMin) ?></div></div>
    <div class="tm-i"><div class="k">Median selesai</div><div class="v"><?= $dur($medMin) ?></div></div>
    <div class="tm-i"><div class="k">Tepat waktu (SLA)</div>
      <div class="v" style="<?= $slaPct!==null && $slaPct<80 ? 'color:#b42318' : ($slaPct!==null?'color:#027a48':'') ?>">
        <?= $slaPct !== null ? $slaPct . '%' : '—' ?>
      </div>
    </div>
    <div class="tm-i"><div class="k">Tiket selesai dihitung</div><div class="v"><?= count($mins) ?></div></div>
  </div>
  <?php if (count($mins) && abs($avgMin - $medMin) > $medMin * 0.5): ?>
  <div class="small text-muted mt-2">
    <i class="bi bi-info-circle me-1"></i>Rata-rata jauh berbeda dari median — ada beberapa tiket yang sangat lama dan menarik angkanya. Gunakan median sebagai acuan.
  </div>
  <?php endif; ?>
</div>

<div class="g2">
  <div class="pnl">
    <div class="pnl-t">Per Kategori</div>
    <?php $max = max(1, max(array_map(fn($r)=>(int)$r['n'], $perCat ?: [['n'=>1]])));
    foreach ($perCat as $r): ?>
    <div class="bar-row">
      <div class="bar-h">
        <span class="n"><?= h($r['name'] ?? 'Tanpa kategori') ?></span>
        <span class="v"><?= (int)$r['n'] ?> tiket<?= $r['avg_h'] ? ' · ' . round($r['avg_h']/24,1) . ' hari' : '' ?></span>
      </div>
      <div class="bar-t"><div class="bar-f" style="width:<?= round(100*(int)$r['n']/$max) ?>%"></div></div>
    </div>
    <?php endforeach; ?>
    <?php if (!$perCat): ?><div class="small text-muted">Belum ada data.</div><?php endif; ?>
  </div>

  <div class="pnl">
    <div class="pnl-t">Jenis Penyelesaian</div>
    <?php $maxR = max(1, max(array_map(fn($r)=>(int)$r['n'], $perRes ?: [['n'=>1]])));
    foreach ($perRes as $r): ?>
    <div class="bar-row">
      <div class="bar-h">
        <span class="n"><?= h(fbResolutions()[$r['resolution_type']] ?? $r['resolution_type']) ?></span>
        <span class="v"><?= (int)$r['n'] ?></span>
      </div>
      <div class="bar-t"><div class="bar-f" style="width:<?= round(100*(int)$r['n']/$maxR) ?>%;background:#027a48"></div></div>
    </div>
    <?php endforeach; ?>
    <?php if (!$perRes): ?><div class="small text-muted">Belum ada tiket yang diselesaikan.</div><?php endif; ?>
  </div>
</div>

<div class="g2">
  <div class="pnl">
    <div class="pnl-t">Beban per Penanggung Jawab</div>
    <?php foreach ($perPic as $r): ?>
    <div class="d-flex justify-content-between align-items-center py-1" style="border-bottom:1px solid #f3f4f6;font-size:12.5px">
      <span style="color:#040136;font-weight:600"><?= h($r['name']) ?></span>
      <span>
        <?= (int)$r['aktif'] ?> aktif
        <?php if ((int)$r['telat']): ?>
        <span style="color:#b42318;font-weight:700"> · <?= (int)$r['telat'] ?> telat</span>
        <?php endif; ?>
      </span>
    </div>
    <?php endforeach; ?>
    <?php if (!$perPic): ?><div class="small text-muted">Belum ada tiket aktif yang ditugaskan.</div><?php endif; ?>
  </div>

  <div class="pnl">
    <div class="pnl-t">Tren Bulanan</div>
    <?php $maxT = max(1, max(array_map(fn($r)=>(int)$r['masuk'], $trend ?: [['masuk'=>1]])));
    foreach ($trend as $r): ?>
    <div class="bar-row">
      <div class="bar-h">
        <span class="n"><?= date('M Y', strtotime($r['bln'] . '-01')) ?></span>
        <span class="v"><?= (int)$r['masuk'] ?> masuk · <?= (int)$r['selesai'] ?> selesai</span>
      </div>
      <div class="bar-t"><div class="bar-f" style="width:<?= round(100*(int)$r['masuk']/$maxT) ?>%"></div></div>
    </div>
    <?php endforeach; ?>
    <?php if (!$trend): ?><div class="small text-muted">Belum ada data.</div><?php endif; ?>
  </div>
</div>

<?php if ($late): ?>
<div class="pnl">
  <div class="pnl-t" style="color:#b42318">Perlu Perhatian — Melewati Batas Waktu</div>
  <?php foreach ($late as $t): ?>
  <a href="<?= APP_URL ?>/admin/ticket.php?id=<?= $t['id'] ?>" class="lrow">
    <span style="font-weight:700;color:#030870;font-size:11px"><?= h($t['ticket_no']) ?></span>
    <span style="flex:1"><?= h($t['subject']) ?></span>
    <span style="color:#6b6a83;font-size:11.5px"><?= h($t['assignee_name'] ?? 'Belum ditugaskan') ?></span>
    <?= fbBadgeOverdue($t) ?>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="small text-muted mt-2">
  <i class="bi bi-info-circle me-1"></i>Semua angka mengecualikan tiket yang dikirim akun tester.
  <?php if (!fbCanSeeSafeguarding($user)): ?> Laporan perlindungan anak tidak termasuk dalam tampilan ini.<?php endif; ?>
</div>

<?php
$content = ob_get_clean();
pageWrapper('Dashboard Feedback', $content);
