<?php
// AGKB 360° — Penanganan tiket
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin','admin','foundation','leader','teacher','staff','mentor']);
$user = currentUser();

$id = (int)($_GET['id'] ?? $_POST['ticket_id'] ?? 0);
$t  = $id ? fbLoadFull($id) : null;
if (!$t || !fbCanView($t, $user)) { http_response_code(403); include BASE_PATH . '/includes/403.php'; exit; }

if (fbHasConflict($t, $user)) {
    http_response_code(403);
    ob_start(); ?>
    <div class="alert alert-danger" style="max-width:620px;margin:40px auto">
      <h5 class="fw-bold mb-2"><i class="bi bi-shield-exclamation me-2"></i>Konflik Kepentingan</h5>
      Tiket ini menyebut Anda sebagai pihak terkait, sehingga tidak dapat Anda buka.
      Penanganan dialihkan ke level di atasnya.
    </div>
    <?php pageWrapper('Akses Ditolak', ob_get_clean()); exit;
}

$unit      = fbTicketUnit($t);
$isMember  = $unit && fbIsUnitMember((int)$unit['id'], (int)$user['id']);
$canAct    = fbCanManage($user) || (int)($t['assignee_id'] ?? 0) === (int)$user['id'] || $isMember;
$canClaim  = empty($t['assignee_id']) && ($isMember || fbCanManage($user));
$isClosed  = in_array($t['status'], ['ditutup'], true);

// ── AKSI ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canAct) {
    verifyCsrf();
    $act = $_POST['action'] ?? '';

    if ($act === 'status' && !$isClosed) {
        $new = $_POST['status'] ?? '';
        if (fbSetStatus($t['id'], $new, $_POST['note'] ?? null)) {
            fbNotifyStatus($t['id'], $new);
            flash('Status diperbarui.', 'success');
        }

    } elseif ($act === 'claim') {
        if (fbClaimTicket($t['id'], (int)$user['id'])) flash('Tiket diambil. Anda kini penanggung jawabnya.', 'success');
        else flash('Tiket sudah diambil orang lain atau Anda bukan anggota unit ini.', 'danger');

    } elseif ($act === 'assign') {
        $newPic = (int)($_POST['assignee_id'] ?? 0) ?: null;
        $old = $t['assignee_name'] ?? '—';
        Database::update('feedback_tickets', ['assignee_id'=>$newPic], 'id = ?', [$t['id']]);
        $newName = $newPic ? (Database::fetchOne("SELECT name FROM users WHERE id=?", [$newPic])['name'] ?? '—') : 'Tidak ada';
        fbLogEvent($t['id'], 'pic_diubah', $old, $newName);
        flash('Penanggung jawab diperbarui.', 'success');

    } elseif ($act === 'priority' && fbCanManage($user)) {
        $np = $_POST['priority'] ?? '';
        if (isset(fbPriorities()[$np]) && $t['track'] !== 'safeguarding') {
            Database::update('feedback_tickets',
                ['priority'=>$np, 'priority_overridden_by'=>$user['id']], 'id = ?', [$t['id']]);
            fbLogEvent($t['id'], 'prioritas_diubah', $t['priority'], $np, $_POST['reason'] ?? null);
            flash('Prioritas diubah.', 'success');
        }

    } elseif ($act === 'escalate') {
        $reason = trim($_POST['reason'] ?? '');
        if (mb_strlen($reason) < 5) flash('Alasan eskalasi wajib diisi.', 'danger');
        elseif (fbEscalate($t['id'], false, $reason)) flash('Tiket dieskalasi.', 'warning');
        else flash('Tiket sudah berada di level tertinggi.', 'danger');

    } elseif ($act === 'reply' && !$isClosed) {
        $body = trim($_POST['body'] ?? '');
        $vis  = ($_POST['visibility'] ?? 'publik') === 'internal' ? 'internal' : 'publik';
        if ($t['track'] === 'safeguarding' && $vis === 'publik') $vis = 'internal'; // refer, don't investigate
        if (mb_strlen($body) >= 3) {
            $mid = fbAddMessage($t['id'], $user['id'], $body, $vis);
            fbLogEvent($t['id'], $vis === 'internal' ? 'catatan_internal' : 'dibalas');
            if (empty($t['first_response_at']) && $vis === 'publik') {
                Database::update('feedback_tickets', ['first_response_at'=>date('Y-m-d H:i:s')], 'id = ?', [$t['id']]);
            }
            if (!empty($_FILES['attachments']['name'][0])) {
                $n = min(count($_FILES['attachments']['name']), FB_MAX_FILES);
                for ($i = 0; $i < $n; $i++) {
                    if (($_FILES['attachments']['error'][$i] ?? 4) !== UPLOAD_ERR_OK) continue;
                    fbStoreUpload($t['id'], [
                        'name'=>$_FILES['attachments']['name'][$i], 'tmp_name'=>$_FILES['attachments']['tmp_name'][$i],
                        'size'=>$_FILES['attachments']['size'][$i], 'error'=>$_FILES['attachments']['error'][$i],
                    ], $mid, $t['track'] === 'safeguarding');
                }
            }
            flash($vis === 'internal' ? 'Catatan internal disimpan.' : 'Balasan terkirim ke pelapor.', 'success');
        }

    } elseif ($act === 'resolve' && !$isClosed) {
        $rt   = $_POST['resolution_type'] ?? '';
        $note = trim($_POST['resolution_note'] ?? '');
        if (!isset(fbResolutions()[$rt]))  flash('Pilih jenis penyelesaian.', 'danger');
        elseif (mb_strlen($note) < 20)     flash('Keterangan penyelesaian minimal 20 karakter.', 'danger');
        else {
            Database::update('feedback_tickets', [
                'resolution_type'=>$rt, 'resolution_note'=>$note, 'resolved_by'=>$user['id'],
            ], 'id = ?', [$t['id']]);
            fbSetStatus($t['id'], 'selesai', fbResolutions()[$rt]);
            fbNotifyResolved($t['id']);
            flash('Tiket diselesaikan dan pelapor sudah diberi tahu.', 'success');
        }

    } elseif ($act === 'forward' && $t['track'] === 'apresiasi') {
        $to = (int)($_POST['appreciated_user_id'] ?? 0);
        if ($to) {
            Database::update('feedback_tickets',
                ['appreciated_user_id'=>$to, 'forwarded_at'=>date('Y-m-d H:i:s')], 'id = ?', [$t['id']]);
            fbNotifyAppreciation($t['id']);
            fbSetStatus($t['id'], 'selesai', 'Apresiasi diteruskan');
            flash('Apresiasi diteruskan.', 'success');
        }

    } elseif ($act === 'watcher') {
        $wid = (int)($_POST['user_id'] ?? 0);
        if ($wid) {
            Database::query("INSERT IGNORE INTO feedback_watchers (ticket_id,user_id,added_by) VALUES (?,?,?)",
                [$t['id'], $wid, $user['id']]);
            flash('Pemantau ditambahkan.', 'success');
        }

    } elseif ($act === 'unmask' && $user['role'] === 'superadmin') {
        fbLogEvent($t['id'], 'identitas_dibuka', null, $t['sender_name'], trim($_POST['reason'] ?? ''));
        $_SESSION['fb_unmask_' . $t['id']] = true;
        flash('Identitas dibuka. Tindakan ini tercatat permanen.', 'warning');
    }

    header('Location: ' . APP_URL . '/admin/ticket.php?id=' . $t['id']);
    exit;
}

fbLogEvent($t['id'], 'dilihat');
$t = fbLoadFull($t['id']);

$unmasked = !empty($_SESSION['fb_unmask_' . $t['id']]);
$sd = $unmasked && $user['role'] === 'superadmin'
    ? ['name'=>$t['sender_name'],'email'=>$t['sender_email'],'role'=>$t['sender_role'],'masked'=>false]
    : fbSenderDisplay($t, $user);

$messages = Database::fetchAll(
    "SELECT m.*, u.name AS author_name FROM feedback_messages m
     LEFT JOIN users u ON u.id=m.author_id WHERE m.ticket_id=? ORDER BY m.created_at ASC", [$t['id']]);
$events = Database::fetchAll(
    "SELECT e.*, u.name AS actor_name FROM feedback_events e
     LEFT JOIN users u ON u.id=e.actor_id WHERE e.ticket_id=?
     ORDER BY e.created_at DESC LIMIT 60", [$t['id']]);
$files = Database::fetchAll("SELECT * FROM feedback_attachments WHERE ticket_id=? ORDER BY id", [$t['id']]);
$watchers = Database::fetchAll(
    "SELECT u.id,u.name FROM feedback_watchers w JOIN users u ON u.id=w.user_id WHERE w.ticket_id=?", [$t['id']]);
$staff = Database::fetchAll(
    "SELECT id,name,role FROM users WHERE is_active=1
     AND role IN ('superadmin','admin','foundation','leader','teacher','staff','mentor') ORDER BY name");

ob_start(); ?>
<style>
.tk-grid{display:grid;grid-template-columns:1fr 320px;gap:16px;align-items:start}
.tk-hero{background:radial-gradient(420px 260px at 12% 0%,rgba(34,1,178,.45) 0%,transparent 68%),linear-gradient(150deg,#02001f 0%,#040136 58%,#030870 100%);color:#fff;border-radius:14px;padding:20px 24px;margin-bottom:14px}
.tk-hero.sg{background:radial-gradient(420px 260px at 12% 0%,rgba(180,35,24,.5) 0%,transparent 68%),linear-gradient(150deg,#2a0505 0%,#040136 62%,#8c1610 100%)}
.tk-no{font-size:11.5px;font-weight:700;color:#ff9101;letter-spacing:.06em;margin-bottom:5px}
.tk-subj{font-size:18px;font-weight:700;line-height:1.35;letter-spacing:-.3px}
.tk-chips{display:flex;gap:6px;flex-wrap:wrap;margin-top:11px}
.pnl{background:#fff;border:1px solid #e3e5ea;border-radius:12px;padding:16px 18px;margin-bottom:13px}
.pnl-t{font-size:10.5px;font-weight:700;color:#6b6a83;text-transform:uppercase;letter-spacing:.6px;margin-bottom:9px}
.msg-box{font-size:14px;color:#2f2d4d;line-height:1.8;background:#fafafb;border-radius:9px;padding:14px 16px;border-left:3px solid #040136;white-space:pre-wrap}
.msg{display:flex;gap:10px;margin-bottom:12px}
.msg-av{width:32px;height:32px;border-radius:50%;background:#eeebfc;color:#030870;display:flex;align-items:center;justify-content:center;font-size:11.5px;font-weight:700;flex-shrink:0}
.msg-b{flex:1;background:#fafafb;border-radius:9px;padding:10px 13px;font-size:13.5px;color:#2f2d4d;line-height:1.7}
.msg.int .msg-b{background:#fff8ef;border:1px dashed #ffc36b}
.msg-h{font-size:11px;color:#6b6a83;margin-bottom:3px}
.msg-h b{color:#040136}
.kv{display:flex;justify-content:space-between;gap:10px;padding:6px 0;border-bottom:1px solid #f3f4f6;font-size:12.5px}
.kv:last-child{border-bottom:none}
.kv .k{color:#6b6a83}.kv .v{color:#040136;font-weight:600;text-align:right}
.tl{border-left:2px solid #e3e5ea;padding-left:15px;margin-left:4px;max-height:340px;overflow-y:auto}
.tl-i{position:relative;padding-bottom:12px;font-size:12px;color:#2f2d4d}
.tl-i::before{content:'';position:absolute;left:-20px;top:4px;width:8px;height:8px;border-radius:50%;background:#ff9101;border:2px solid #fff}
.tl-t{font-size:10.5px;color:#6b6a83}
.file-row{display:flex;align-items:center;gap:9px;padding:8px 11px;border:1px solid #e3e5ea;border-radius:8px;margin-bottom:6px;font-size:12.5px;text-decoration:none;color:#040136}
.file-row:hover{background:#fafafb;text-decoration:none}
.sg-note{background:#fdeceb;border:1px solid #f3b5b0;border-radius:9px;padding:12px 14px;font-size:12.5px;color:#8c1610;line-height:1.65;margin-bottom:13px}
@media(max-width:992px){.tk-grid{grid-template-columns:1fr}}
</style>

<?= showFlash() ?>

<div class="tk-hero <?= $t['track']==='safeguarding'?'sg':'' ?>">
  <div class="tk-no"><?= h($t['ticket_no']) ?></div>
  <div class="tk-subj"><?= h($t['subject']) ?></div>
  <div class="tk-chips">
    <?= fbBadgeTrack($t['track']) ?>
    <?= fbBadgeStatus($t['status']) ?>
    <?php if ($t['track']!=='apresiasi') echo fbBadgePriority($t['priority']); ?>
    <?= fbBadgeOverdue($t) ?>
    <?php if ($t['is_test']) echo fbChip('TESTER — tidak dihitung', '#fff', '#2201b2', '#2201b2'); ?>
  </div>
</div>

<div class="tk-grid">
<div>

  <?php if ($t['track']==='safeguarding'): ?>
  <div class="sg-note">
    <strong><i class="bi bi-shield-lock-fill me-1"></i>Laporan Perlindungan Anak.</strong>
    Prinsip <em>refer, don't investigate</em> — jangan gunakan sistem ini untuk menyelidiki.
    Balasan hanya tersimpan sebagai catatan internal. Isi laporan dan lampiran tidak dapat diubah atau dihapus.
    Setiap pembukaan halaman ini tercatat.
  </div>
  <?php endif; ?>

  <div class="pnl">
    <div class="pnl-t">Isi Laporan</div>
    <div class="msg-box"><?= h($t['message']) ?></div>
  </div>

  <?php if ($files): ?>
  <div class="pnl">
    <div class="pnl-t">Lampiran (<?= count($files) ?>)</div>
    <?php foreach ($files as $f): ?>
    <a href="<?= APP_URL ?>/feedback/attachment.php?id=<?= $f['id'] ?>" class="file-row">
      <i class="bi bi-paperclip" style="color:#6b6a83"></i>
      <span style="flex:1"><?= h($f['original_name']) ?></span>
      <?php if ($f['is_sealed']): ?><i class="bi bi-shield-lock-fill" style="color:#b42318" title="Disegel — pengunduhan tercatat"></i><?php endif; ?>
      <span style="font-size:11px;color:#6b6a83"><?= fbFormatBytes((int)$f['size_bytes']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($messages): ?>
  <div class="pnl">
    <div class="pnl-t">Percakapan &amp; Catatan</div>
    <?php foreach ($messages as $m): $int = $m['visibility']==='internal'; ?>
    <div class="msg <?= $int?'int':'' ?>">
      <div class="msg-av"><?= $m['is_system'] ? '<i class="bi bi-gear-fill"></i>' : h(avatarInitials($m['author_name'] ?? 'S')) ?></div>
      <div class="msg-b">
        <div class="msg-h">
          <b><?= $m['is_system'] ? 'Sistem' : h($m['author_name'] ?? '—') ?></b> · <?= fbRelTime($m['created_at']) ?>
          <?php if ($int): ?><span style="color:#b83a01;font-weight:600"> · internal</span><?php endif; ?>
        </div>
        <?= nl2br(h($m['body'])) ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($canAct && !$isClosed): ?>
  <div class="pnl">
    <div class="pnl-t">Tanggapi</div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
      <input type="hidden" name="action" value="reply">
      <textarea name="body" rows="4" class="form-control mb-2" required minlength="3"
        placeholder="<?= $t['track']==='safeguarding' ? 'Catatan faktual. Tidak dikirim ke pelapor.' : 'Tulis balasan atau catatan...' ?>"></textarea>
      <div class="d-flex gap-2 align-items-center flex-wrap">
        <?php if ($t['track']!=='safeguarding'): ?>
        <select name="visibility" class="form-select form-select-sm" style="width:auto">
          <option value="publik">Balasan ke pelapor (dikirim email)</option>
          <option value="internal">Catatan internal (tidak terlihat pelapor)</option>
        </select>
        <?php else: ?>
        <input type="hidden" name="visibility" value="internal">
        <span class="small text-muted"><i class="bi bi-lock me-1"></i>Selalu tersimpan sebagai catatan internal</span>
        <?php endif; ?>
        <input type="file" name="attachments[]" multiple class="form-control form-control-sm" style="width:auto;max-width:230px">
        <button class="btn btn-navy btn-sm px-3 ms-auto"><i class="bi bi-send-fill me-1"></i>Kirim</button>
      </div>
    </form>
  </div>

  <?php if (!in_array($t['status'],['selesai','ditutup'],true) && $t['track']!=='apresiasi'): ?>
  <div class="pnl" style="border-color:#a5dcc3;background:#fbfefc">
    <div class="pnl-t" style="color:#015c36">Selesaikan Tiket</div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
      <input type="hidden" name="action" value="resolve">
      <select name="resolution_type" class="form-select form-select-sm mb-2" required>
        <option value="">— Pilih jenis penyelesaian —</option>
        <?php foreach (fbResolutions() as $k=>$v): ?><option value="<?= $k ?>"><?= h($v) ?></option><?php endforeach; ?>
      </select>
      <textarea name="resolution_note" rows="3" class="form-control mb-2" required minlength="20"
        placeholder="Jelaskan apa yang dilakukan dan hasilnya. Teks ini dikirim ke pelapor (minimal 20 karakter)."></textarea>
      <div class="text-end">
        <button class="btn btn-success btn-sm px-3"><i class="bi bi-check2-circle me-1"></i>Tandai Selesai</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <?php if ($t['track']==='apresiasi' && empty($t['forwarded_at'])): ?>
  <div class="pnl" style="border-color:#ffc36b;background:#fffcf7">
    <div class="pnl-t" style="color:#b83a01">Teruskan Apresiasi</div>
    <form method="POST" class="d-flex gap-2">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
      <input type="hidden" name="action" value="forward">
      <select name="appreciated_user_id" class="form-select form-select-sm" required>
        <option value="">— Pilih penerima —</option>
        <?php foreach ($staff as $s): ?>
        <option value="<?= $s['id'] ?>" <?= (int)$t['appreciated_user_id']===(int)$s['id']?'selected':'' ?>>
          <?= h($s['name']) ?> — <?= h(roleLabel($s['role'])) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-catalyst btn-sm px-3 text-nowrap"><i class="bi bi-send me-1"></i>Teruskan</button>
    </form>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</div>

<!-- ── SIDEBAR ─────────────────────────────────────────── -->
<div>
  <?php if ($canClaim): ?>
  <div class="pnl" style="border-color:#ffc36b;background:#fffcf7">
    <div class="pnl-t" style="color:#b83a01">Belum Ada Penanggung Jawab</div>
    <div style="font-size:12.5px;color:#2f2d4d;line-height:1.6;margin-bottom:10px">
      Tiket ini masih di antrean <strong><?= h($unit['name'] ?? 'unit') ?></strong>.
      Ambil untuk menjadi penanggung jawabnya.
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
      <input type="hidden" name="action" value="claim">
      <button class="btn btn-catalyst btn-sm w-100"><i class="bi bi-hand-index-thumb me-1"></i>Ambil Tiket Ini</button>
    </form>
  </div>
  <?php endif; ?>

  <div class="pnl">
    <div class="pnl-t">Ringkasan</div>
    <div class="kv"><span class="k">Pelapor</span><span class="v"><?= h($sd['name']) ?><?= $sd['masked']?' 🔒':'' ?></span></div>
    <?php if ($unit): ?>
    <div class="kv"><span class="k">Unit penanganan</span><span class="v"><?= h($unit['name']) ?></span></div>
    <?php endif; ?>
    <?php if ($sd['email']): ?><div class="kv"><span class="k">Email</span><span class="v" style="font-weight:400;font-size:11.5px"><?= h($sd['email']) ?></span></div><?php endif; ?>
    <div class="kv"><span class="k">Kategori</span><span class="v"><?= h($t['category_name'] ?? '—') ?></span></div>
    <?php if ($t['impact']): ?><div class="kv"><span class="k">Dampak</span><span class="v"><?= h(fbImpacts()[$t['impact']] ?? '—') ?></span></div><?php endif; ?>
    <div class="kv"><span class="k">Level</span><span class="v"><?= h(fbLevels()[$t['level']] ?? '—') ?></span></div>
    <div class="kv"><span class="k">Penanggung jawab</span>
      <span class="v" style="<?= empty($t['assignee_id']) ? 'color:#b83a01' : '' ?>">
        <?= h($t['assignee_name'] ?? 'Di antrean, belum diambil') ?>
      </span></div>
    <div class="kv"><span class="k">Masuk</span><span class="v"><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></span></div>
    <?php if ($t['due_at'] && !in_array($t['status'],['selesai','ditutup'],true)): ?>
    <div class="kv"><span class="k">Batas waktu</span>
      <span class="v" style="<?= fbIsOverdue($t)?'color:#b42318':'' ?>"><?= date('d M, H:i', strtotime($t['due_at'])) ?></span></div>
    <?php endif; ?>
    <?php if ($t['first_response_at']): ?>
    <div class="kv"><span class="k">Respons pertama</span><span class="v"><?= fbDurationText($t['created_at'],$t['first_response_at']) ?></span></div>
    <?php endif; ?>
    <?php if ($t['resolved_at']): ?>
    <div class="kv"><span class="k">Lama penanganan</span><span class="v"><?= fbDurationText($t['created_at'],$t['resolved_at']) ?></span></div>
    <?php endif; ?>
  </div>

  <?php if ($canAct): ?>
  <div class="pnl">
    <div class="pnl-t">Tindakan</div>

    <?php if (!$isClosed): ?>
    <form method="POST" class="mb-2">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
      <input type="hidden" name="action" value="status">
      <label class="form-label small mb-1">Ubah status</label>
      <div class="d-flex gap-1">
        <select name="status" class="form-select form-select-sm">
          <?php foreach (fbStatuses() as $k=>$v): if($k==='selesai')continue; ?>
          <option value="<?= $k ?>" <?= $t['status']===$k?'selected':'' ?>><?= h($v['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-navy px-2">OK</button>
      </div>
    </form>

    <form method="POST" class="mb-2">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
      <input type="hidden" name="action" value="assign">
      <label class="form-label small mb-1">Penanggung jawab</label>
      <div class="d-flex gap-1">
        <select name="assignee_id" class="form-select form-select-sm">
          <option value="0">— Belum ditugaskan —</option>
          <?php foreach ($staff as $s): ?>
          <option value="<?= $s['id'] ?>" <?= (int)$t['assignee_id']===(int)$s['id']?'selected':'' ?>><?= h($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-navy px-2">OK</button>
      </div>
    </form>

    <?php if (fbCanManage($user) && $t['track']!=='safeguarding'): ?>
    <form method="POST" class="mb-2">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
      <input type="hidden" name="action" value="priority">
      <label class="form-label small mb-1">Prioritas</label>
      <div class="d-flex gap-1">
        <select name="priority" class="form-select form-select-sm">
          <?php foreach (fbPriorities() as $k=>$v): ?>
          <option value="<?= $k ?>" <?= $t['priority']===$k?'selected':'' ?>><?= $k ?> · <?= h($v['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-navy px-2">OK</button>
      </div>
      <input type="text" name="reason" class="form-control form-control-sm mt-1" placeholder="Alasan perubahan (tercatat)">
    </form>
    <?php endif; ?>

    <?php if ($t['level'] < 3): ?>
    <form method="POST" class="mt-3 pt-2" style="border-top:1px solid #f3f4f6">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
      <input type="hidden" name="action" value="escalate">
      <label class="form-label small mb-1">Eskalasi ke <?= h(fbLevels()[$t['level']+1] ?? '') ?></label>
      <input type="text" name="reason" class="form-control form-control-sm mb-1" placeholder="Alasan (wajib)" required minlength="5">
      <button class="btn btn-ember btn-sm w-100"><i class="bi bi-arrow-up-circle me-1"></i>Eskalasi Sekarang</button>
    </form>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="pnl">
    <div class="pnl-t">Pemantau (<?= count($watchers) ?>)</div>
    <?php if ($watchers): ?>
    <div class="mb-2 d-flex flex-wrap gap-1">
      <?php foreach ($watchers as $wch) echo fbChip($wch['name'], '#030870', '#eeebfc'); ?>
    </div>
    <?php endif; ?>
    <form method="POST" class="d-flex gap-1">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
      <input type="hidden" name="action" value="watcher">
      <select name="user_id" class="form-select form-select-sm">
        <option value="">— Tambah pemantau —</option>
        <?php foreach ($staff as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-sm btn-outline-navy px-2">+</button>
    </form>
  </div>

  <?php if (!empty($t['is_anonymous']) && $user['role']==='superadmin' && !$unmasked): ?>
  <div class="pnl" style="border-color:#f3b5b0;background:#fffbfb">
    <div class="pnl-t" style="color:#b42318">Buka Identitas Pelapor</div>
    <div style="font-size:11.5px;color:#8c1610;line-height:1.6;margin-bottom:8px">
      Hanya untuk mencegah penyalahgunaan. Tindakan ini <strong>tercatat permanen</strong> dan tidak dapat dihapus.
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
      <input type="hidden" name="action" value="unmask">
      <input type="text" name="reason" class="form-control form-control-sm mb-1" placeholder="Alasan (wajib)" required minlength="10">
      <button class="btn btn-sm btn-danger w-100" data-confirm="Buka identitas pelapor? Tindakan ini tercatat permanen.">
        <i class="bi bi-eye me-1"></i>Buka Identitas
      </button>
    </form>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <div class="pnl">
    <div class="pnl-t">Riwayat</div>
    <div class="tl">
      <?php foreach ($events as $e): ?>
      <div class="tl-i">
        <div><?= h(fbEventLabel($e['event_type'])) ?>
          <?php if ($e['from_value'] || $e['to_value']): ?>
          <span style="color:#6b6a83"><?= h($e['from_value'] ?? '') ?> → <strong style="color:#040136"><?= h($e['to_value'] ?? '') ?></strong></span>
          <?php endif; ?>
        </div>
        <?php if ($e['note']): ?><div style="font-size:11px;color:#6b6a83;font-style:italic"><?= h($e['note']) ?></div><?php endif; ?>
        <div class="tl-t"><?= h($e['actor_name'] ?? 'Sistem') ?> · <?= date('d M Y, H:i', strtotime($e['created_at'])) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <a href="<?= APP_URL ?>/admin/feedback.php" class="btn btn-sm btn-outline-navy w-100">
    <i class="bi bi-arrow-left me-1"></i>Kembali ke Inbox
  </a>
</div>
</div>

<?php
$content = ob_get_clean();
pageWrapper('Tiket ' . $t['ticket_no'], $content);
