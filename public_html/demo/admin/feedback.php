<?php
// AGKB 360° — Inbox tiket (sisi pengelola)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
// Peran pengelola selalu boleh. Peran lain boleh masuk HANYA kalau
// dia anggota unit penanganan — penyaringnya fbAllowedTracks() di
// bawah, yang mengembalikan daftar kosong untuk yang bukan penangan.
requireRole(['superadmin','admin','foundation','leader','staff','teacher','mentor','pemantau']);
$user = currentUser();

// Eskalasi otomatis dijalankan saat inbox dibuka (pengganti cron)
$escalated = fbRunAutoEscalation();
if ($escalated) flash("$escalated tiket dieskalasi otomatis karena melewati batas waktu.", 'warning');

$tracks = fbAllowedTracks($user);

// Tiket Kanal Yayasan yang diberikan satu per satu kepada orang yang
// bukan anggota unit. Jalur safeguarding dibuka untuknya di inbox,
// tetapi dibatasi baris demi baris di bawah — tanpa pembatas itu
// membuka track sama saja membuka kesepuluh tiketnya.
//
// Sengaja tidak dimasukkan ke fbAllowedTracks(): fungsi itu juga
// dipakai dasbor untuk menghitung angka agregat, dan angka yang
// mencakup tiket KY yang bukan haknya adalah kebocoran tersendiri.
$kyKhusus = fbCanSeeSafeguarding($user) ? [] : fbTiketKyDiberikan((int)$user['id']);
if ($kyKhusus && $tracks) $tracks[] = 'safeguarding';

if (!$tracks) { http_response_code(403); include BASE_PATH . '/includes/403.php'; exit; }

// ── Filter ──────────────────────────────────────────────────
$fStatus = $_GET['status']   ?? 'aktif';
$fTrack  = $_GET['track']    ?? '';
$fCat    = (int)($_GET['cat']  ?? 0);
$fPic    = (int)($_GET['pic']  ?? 0);
$fPrio   = $_GET['prio']     ?? '';
$fQ      = trim($_GET['q']   ?? '');
$showTest= !empty($_GET['test']);

$w = ["t.track IN (" . implode(',', array_fill(0, count($tracks), '?')) . ")"];
$p = $tracks;

// Baris ini yang menahan agar pembukaan track di atas tidak meluas:
// tiket safeguarding hanya lolos kalau memang diberikan kepadanya.
if ($kyKhusus) {
    $iph = implode(',', array_fill(0, count($kyKhusus), '?'));
    $w[] = "(t.track <> 'safeguarding' OR t.id IN ($iph))";
    $p   = array_merge($p, $kyKhusus);
}

// Saat mencari, filter status dan penyembunyian tiket tester diabaikan.
// Kalau tidak, mencari nomor tiket yang persis pun bisa tidak ketemu.
$modeCari = $fQ !== '';

if (!$showTest && !$modeCari)         { $w[] = "t.is_test = 0"; }
if ($fTrack && in_array($fTrack, $tracks, true)) { $w[] = "t.track = ?";  $p[] = $fTrack; }
if ($fCat)                            { $w[] = "t.category_id = ?";       $p[] = $fCat; }
if ($fPic)                            { $w[] = "t.assignee_id = ?";       $p[] = $fPic; }
if ($fPrio)                           { $w[] = "t.priority = ?";          $p[] = $fPrio; }
if ($fQ) { $w[] = "(t.subject LIKE ? OR t.ticket_no LIKE ?)"; $p[] = "%$fQ%"; $p[] = "%$fQ%"; }

switch ($modeCari ? 'semua' : $fStatus) {
    case 'aktif':     $w[] = "t.status IN ('baru','ditinjau','ditindaklanjuti','menunggu_pelapor')"; break;
    case 'terlambat': $w[] = "t.status IN ('baru','ditinjau','ditindaklanjuti') AND t.due_at < NOW()"; break;
    case 'selesai':   $w[] = "t.status IN ('selesai','ditutup')"; break;
    case 'saya':      $w[] = "t.assignee_id = ?"; $p[] = $user['id']; break;
    case 'antrean':
        // Tiket di unit saya yang belum diambil siapa pun
        $myUnits = array_column(fbUserUnits((int)$user['id']), 'id');
        if ($myUnits) {
            $uph = implode(',', array_fill(0, count($myUnits), '?'));
            $w[] = "t.assignee_id IS NULL AND t.category_id IN
                    (SELECT id FROM feedback_categories WHERE handler_group_id IN ($uph))";
            $p = array_merge($p, $myUnits);
        } else {
            $w[] = "t.assignee_id IS NULL";
        }
        $w[] = "t.status IN ('baru','ditinjau','ditindaklanjuti')";
        break;
}
if ($user['role'] === 'leader') { $w[] = "(t.level >= 2 OR t.assignee_id = ?)"; $p[] = $user['id']; }

// Penangan yang bukan pengelola hanya melihat tiket unitnya sendiri
// atau yang ditugaskan langsung kepadanya — bukan seluruh tiket sekolah.
// Pemantau dikecualikan: ia memang ditujukan melihat semuanya, dan
// tanpa pengecualian ini ia justru tidak melihat apa pun karena tidak
// terdaftar di unit mana pun.
if (!fbCanManage($user) && !in_array($user['role'], ['leader','pemantau'], true)) {
    $myUnits = array_column(fbUserUnits((int)$user['id']), 'id');
    if ($myUnits) {
        $uph = implode(',', array_fill(0, count($myUnits), '?'));
        $w[] = "(t.assignee_id = ? OR t.category_id IN
                 (SELECT id FROM feedback_categories WHERE handler_group_id IN ($uph)))";
        $p[] = $user['id'];
        $p   = array_merge($p, $myUnits);
    } else {
        $w[] = "t.assignee_id = ?";
        $p[] = $user['id'];
    }
}

$where = 'WHERE ' . implode(' AND ', $w);

$tickets = Database::fetchAll(
    "SELECT t.*, c.name AS category_name,
            s.name AS sender_name, s.role AS sender_role, s.email AS sender_email,
            a.name AS assignee_name
     FROM feedback_tickets t
     LEFT JOIN feedback_categories c ON c.id = t.category_id
     LEFT JOIN users s ON s.id = t.sender_id
     LEFT JOIN users a ON a.id = t.assignee_id
     $where
     ORDER BY FIELD(t.priority,'P1','P2','P3','P4'), t.due_at IS NULL, t.due_at ASC, t.created_at DESC
     LIMIT 300", $p);

// ── Ringkasan ───────────────────────────────────────────────
$ph = implode(',', array_fill(0, count($tracks), '?'));
$testCond = $showTest ? '' : ' AND is_test = 0';
$sum = Database::fetchOne(
    "SELECT
       COUNT(*) AS semua,
       SUM(status IN ('baru','ditinjau','ditindaklanjuti','menunggu_pelapor')) AS aktif,
       SUM(status = 'baru') AS baru,
       SUM(status IN ('baru','ditinjau','ditindaklanjuti') AND due_at < NOW()) AS terlambat,
       SUM(status = 'menunggu_pelapor') AS onhold,
       SUM(status IN ('selesai','ditutup')) AS selesai
     FROM feedback_tickets WHERE track IN ($ph) $testCond", $tracks);

$cats = Database::fetchAll(
    "SELECT id,name,track FROM feedback_categories WHERE is_active=1 ORDER BY order_num");
$pics = Database::fetchAll(
    "SELECT DISTINCT u.id,u.name FROM feedback_tickets t
     JOIN users u ON u.id=t.assignee_id ORDER BY u.name");

ob_start(); ?>
<style>
.kpi-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(132px,1fr));gap:10px;margin-bottom:16px}
.kpi{background:#fff;border:1px solid #e3e5ea;border-radius:11px;padding:13px 15px;text-decoration:none;display:block;transition:all .14s}
.kpi:hover{text-decoration:none;border-color:#cdd0d8;transform:translateY(-1px)}
.kpi.active{border-color:#040136;box-shadow:0 0 0 3px rgba(4,1,54,.07)}
.kpi-n{font-size:24px;font-weight:700;color:#040136;line-height:1.1}
.kpi-l{font-size:11.5px;color:#6b6a83;margin-top:3px}
.kpi.warn .kpi-n{color:#b42318}
.kpi.hold .kpi-n{color:#6b6a83}
.kpi.done .kpi-n{color:#027a48}
.flt{background:#fff;border:1px solid #e3e5ea;border-radius:11px;padding:12px 14px;margin-bottom:14px}
.flt select,.flt input{border:1px solid #e3e5ea;border-radius:8px;padding:5px 10px;font-size:12.5px;outline:none;color:#040136}
.flt select:focus,.flt input:focus{border-color:#2201b2}
.trow{display:block;background:#fff;border:1px solid #e3e5ea;border-radius:11px;padding:13px 16px;margin-bottom:8px;text-decoration:none;transition:all .13s;border-left:3px solid #e3e5ea}
.trow:hover{text-decoration:none;border-color:#cdd0d8;border-left-color:#040136;background:#fafafb}
.trow.p1{border-left-color:#b42318}.trow.p2{border-left-color:#b83a01}
.trow.late{background:#fffafa}
.trow-top{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:6px}
.trow-no{font-size:11px;font-weight:700;color:#030870;letter-spacing:.03em}
.trow-subj{font-size:14px;font-weight:600;color:#040136;line-height:1.4;margin-bottom:3px}
.trow-meta{font-size:11.5px;color:#6b6a83;display:flex;gap:12px;flex-wrap:wrap}
.empty{text-align:center;padding:48px 20px;color:#6f6e85;background:#fff;border:1px solid #e3e5ea;border-radius:12px}
</style>

<?= showFlash() ?>

<div class="kpi-row">
  <a href="?status=semua"     class="kpi <?= $fStatus==='semua'?'active':'' ?>"><div class="kpi-n"><?= (int)$sum['semua'] ?></div><div class="kpi-l">Total Masuk</div></a>
  <a href="?status=aktif"     class="kpi <?= $fStatus==='aktif'?'active':'' ?>"><div class="kpi-n"><?= (int)$sum['aktif'] ?></div><div class="kpi-l">Sedang Proses</div></a>
  <a href="?status=terlambat" class="kpi warn <?= $fStatus==='terlambat'?'active':'' ?>"><div class="kpi-n"><?= (int)$sum['terlambat'] ?></div><div class="kpi-l">Terlambat</div></a>
  <a href="?status=aktif&amp;q=" class="kpi hold"><div class="kpi-n"><?= (int)$sum['onhold'] ?></div><div class="kpi-l">Menunggu Pelapor</div></a>
  <a href="?status=selesai"   class="kpi done <?= $fStatus==='selesai'?'active':'' ?>"><div class="kpi-n"><?= (int)$sum['selesai'] ?></div><div class="kpi-l">Selesai</div></a>
  <a href="?status=antrean"   class="kpi <?= $fStatus==='antrean'?'active':'' ?>"><div class="kpi-n"><i class="bi bi-inboxes" style="font-size:20px"></i></div><div class="kpi-l">Antrean Unit Saya</div></a>
  <a href="?status=saya"      class="kpi <?= $fStatus==='saya'?'active':'' ?>"><div class="kpi-n"><i class="bi bi-person-check" style="font-size:20px"></i></div><div class="kpi-l">Ditugaskan ke Saya</div></a>
</div>

<?php $myUnitNames = array_column(fbUserUnits((int)$user['id']), 'name'); ?>
<?php if ($myUnitNames): ?>
<div class="small text-muted mb-2">
  <i class="bi bi-diagram-3 me-1"></i>Unit penanganan Anda: <strong><?= h(implode(' · ', $myUnitNames)) ?></strong>
</div>
<?php elseif (!fbCanManage($user)): ?>
<div class="alert alert-warning small py-2">
  <i class="bi bi-exclamation-triangle me-1"></i>
  Anda belum tergabung di unit penanganan mana pun, jadi tidak ada antrean yang bisa Anda ambil.
</div>
<?php endif; ?>

<div class="flt">
  <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
    <input type="hidden" name="status" value="<?= h($fStatus) ?>">
    <select name="track" onchange="this.form.submit()">
      <option value="">Semua jenis</option>
      <?php foreach (fbTracks() as $k=>$v): if(!in_array($k,$tracks,true))continue; ?>
      <option value="<?= $k ?>" <?= $fTrack===$k?'selected':'' ?>><?= h($v['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="cat" onchange="this.form.submit()">
      <option value="0">Semua kategori</option>
      <?php foreach ($cats as $c): if(!in_array($c['track'],$tracks,true))continue; ?>
      <option value="<?= $c['id'] ?>" <?= $fCat===(int)$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="prio" onchange="this.form.submit()">
      <option value="">Semua prioritas</option>
      <?php foreach (fbPriorities() as $k=>$v): ?>
      <option value="<?= $k ?>" <?= $fPrio===$k?'selected':'' ?>><?= $k ?> · <?= h($v['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="pic" onchange="this.form.submit()">
      <option value="0">Semua penanggung jawab</option>
      <?php foreach ($pics as $pc): ?>
      <option value="<?= $pc['id'] ?>" <?= $fPic===(int)$pc['id']?'selected':'' ?>><?= h($pc['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="q" value="<?= h($fQ) ?>" placeholder="Cari subjek / nomor tiket" style="min-width:190px"
           title="Pencarian menembus semua status dan termasuk tiket tester">
    <label class="d-flex align-items-center gap-1" style="font-size:12px;color:#6b6a83;cursor:pointer">
      <input type="checkbox" name="test" value="1" <?= $showTest?'checked':'' ?> onchange="this.form.submit()">
      Tampilkan tiket tester
    </label>
    <button class="btn btn-navy btn-sm px-3">Terapkan</button>
    <a href="?" class="btn btn-sm btn-outline-navy px-3">Reset</a>
    <span class="ms-auto d-flex gap-1 flex-wrap">
      <?php if (fbCanManage($user) || $user['role'] === 'leader'): ?>
      <a href="<?= APP_URL ?>/admin/feedback_dashboard.php" class="btn btn-sm btn-catalyst px-3">
        <i class="bi bi-graph-up me-1"></i>Dashboard
      </a>
      <?php endif; ?>
      <?php if (fbCanManage($user)): ?>
      <a href="<?= APP_URL ?>/admin/feedback_units.php" class="btn btn-sm btn-outline-navy px-3">
        <i class="bi bi-diagram-3 me-1"></i>Unit Penanganan
      </a>
      <a href="<?= APP_URL ?>/admin/feedback_categories.php" class="btn btn-sm btn-outline-navy px-3">
        <i class="bi bi-tags me-1"></i>Kategori
      </a>
      <?php endif; ?>
    </span>
  </form>
</div>

<?php if ($modeCari): ?>
<div class="alert alert-info small py-2 d-flex align-items-center gap-2">
  <i class="bi bi-search"></i>
  <span>Hasil pencarian <strong>"<?= h($fQ) ?>"</strong> — mencakup semua status, termasuk tiket tester.</span>
  <a href="?status=<?= h($fStatus) ?>" class="ms-auto text-decoration-none">Bersihkan pencarian</a>
</div>
<?php endif; ?>

<?php if (!$tickets): ?>
<div class="empty">
  <i class="bi bi-inbox" style="font-size:34px;display:block;margin-bottom:10px;opacity:.35"></i>
  <div style="font-size:14px">
    <?= $modeCari
        ? 'Tidak ada tiket yang cocok dengan pencarian ini.'
        : 'Tidak ada tiket yang cocok dengan filter ini.' ?>
  </div>
</div>
<?php else: foreach ($tickets as $t):
  $late = fbIsOverdue($t);
  $sd   = fbSenderDisplay($t, $user); ?>
<a href="<?= APP_URL ?>/admin/ticket.php?id=<?= $t['id'] ?>"
   class="trow <?= strtolower($t['priority']) ?> <?= $late?'late':'' ?>">
  <div class="trow-top">
    <span class="trow-no"><?= h($t['ticket_no']) ?></span>
    <?= fbBadgeTrack($t['track']) ?>
    <?= fbBadgeStatus($t['status']) ?>
    <?php if ($t['track']!=='apresiasi') echo fbBadgePriority($t['priority']); ?>
    <?= fbBadgeOverdue($t) ?>
    <?php if ($t['is_test']) echo fbChip('TESTER', '#fff', '#2201b2', '#2201b2'); ?>
    <?php if (!empty($sd['tamu'])) echo fbChip('PUBLIK', '#a85a01', '#fff8ef', '#f5d9b0'); ?>
    <span style="margin-left:auto;font-size:11px;color:#6b6a83"><?= fbRelTime($t['created_at']) ?></span>
  </div>
  <div class="trow-subj"><?= h($t['subject']) ?></div>
  <div class="trow-meta">
    <span><i class="bi bi-person me-1"></i><?= h($sd['name']) ?><?= $sd['masked']?' 🔒':'' ?></span>
    <span><i class="bi bi-tag me-1"></i><?= h($t['category_name'] ?? '—') ?></span>
    <span><i class="bi bi-person-badge me-1"></i><?= h($t['assignee_name'] ?? 'Belum ditugaskan') ?></span>
    <span><i class="bi bi-diagram-2 me-1"></i><?= h(fbLevels()[$t['level']] ?? '—') ?></span>
    <?php if ($t['due_at'] && !in_array($t['status'],['selesai','ditutup'],true)): ?>
    <span style="<?= $late?'color:#b42318;font-weight:600':'' ?>">
      <i class="bi bi-clock me-1"></i><?= date('d M, H:i', strtotime($t['due_at'])) ?>
    </span>
    <?php endif; ?>
  </div>
</a>
<?php endforeach; endif; ?>

<?php
$content = ob_get_clean();
pageWrapper('Inbox Tiket', $content);
