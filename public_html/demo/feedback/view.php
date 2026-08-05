<?php
// AGKB 360° — Detail laporan (sisi pelapor)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
$user = currentUser();

$id = (int)($_GET['id'] ?? 0);
$t  = $id ? fbLoadFull($id) : null;

if (!$t || !fbCanView($t, $user)) {
    http_response_code(403);
    include BASE_PATH . '/includes/403.php';
    exit;
}

// Balasan pelapor — hanya untuk tiketnya sendiri, dan bukan track safeguarding
$isOwner   = (int)$t['sender_id'] === (int)$user['id'];
$canReply  = $isOwner && $t['track'] !== 'safeguarding'
             && !in_array($t['status'], ['ditutup'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canReply) {
    verifyCsrf();
    $body = trim($_POST['body'] ?? '');
    if (mb_strlen($body) >= 3) {
        fbAddMessage($t['id'], $user['id'], $body, 'publik');
        fbLogEvent($t['id'], 'dibalas', null, 'pelapor');
        // Menunggu pelapor → lanjut ditangani
        if ($t['status'] === 'menunggu_pelapor') fbSetStatus($t['id'], 'ditindaklanjuti', 'Pelapor merespons');
        // Selesai lalu pelapor keberatan → buka kembali
        elseif ($t['status'] === 'selesai')      fbSetStatus($t['id'], 'ditindaklanjuti', 'Dibuka kembali oleh pelapor');
        flash('Balasan Anda terkirim.', 'success');
    }
    header('Location: ' . APP_URL . '/feedback/view.php?id=' . $t['id']);
    exit;
}

$messages = Database::fetchAll(
    "SELECT m.*, u.name AS author_name, u.role AS author_role
     FROM feedback_messages m LEFT JOIN users u ON u.id = m.author_id
     WHERE m.ticket_id = ? AND m.visibility = 'publik'
     ORDER BY m.created_at ASC", [$t['id']]);

$files = Database::fetchAll(
    "SELECT * FROM feedback_attachments WHERE ticket_id=? ORDER BY id", [$t['id']]);

$st = fbStatuses()[$t['status']];
ob_start(); ?>
<style>
.tv-wrap{max-width:800px;margin:0 auto}
.tv-hero{background:radial-gradient(420px 260px at 12% 0%,rgba(34,1,178,.45) 0%,transparent 68%),linear-gradient(150deg,#02001f 0%,#040136 58%,#030870 100%);color:#fff;border-radius:14px;padding:22px 26px;margin-bottom:16px}
.tv-no{font-size:12px;font-weight:700;color:#ff9101;letter-spacing:.06em;margin-bottom:6px}
.tv-subj{font-size:19px;font-weight:700;line-height:1.35;letter-spacing:-.3px}
.tv-chips{display:flex;gap:6px;flex-wrap:wrap;margin-top:12px}
.tv-card{background:#fff;border:1px solid #e3e5ea;border-radius:12px;padding:18px 20px;margin-bottom:14px}
.tv-label{font-size:10.5px;font-weight:700;color:#6b6a83;text-transform:uppercase;letter-spacing:.6px;margin-bottom:7px}
.tv-msg{font-size:14px;color:#2f2d4d;line-height:1.8;background:#fafafb;border-radius:9px;padding:14px 16px;border-left:3px solid #040136;white-space:pre-wrap}
.tv-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
.tv-kv .k{font-size:10.5px;color:#6b6a83;text-transform:uppercase;letter-spacing:.5px;font-weight:600}
.tv-kv .v{font-size:13.5px;color:#040136;font-weight:600;margin-top:3px}
.msg{display:flex;gap:11px;margin-bottom:14px}
.msg-av{width:34px;height:34px;border-radius:50%;background:#eeebfc;color:#030870;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0}
.msg-av.adm{background:#040136;color:#ff9101}
.msg-b{flex:1;background:#fafafb;border-radius:10px;padding:11px 14px;font-size:13.5px;color:#2f2d4d;line-height:1.7}
.msg.mine .msg-b{background:#eeebfc}
.msg-h{font-size:11.5px;color:#6b6a83;margin-bottom:4px}
.msg-h b{color:#040136}
.res-box{background:#e7f6ef;border:1px solid #a5dcc3;border-radius:11px;padding:16px 18px}
.file-row{display:flex;align-items:center;gap:10px;padding:9px 12px;border:1px solid #e3e5ea;border-radius:9px;margin-bottom:7px;font-size:13px;text-decoration:none;color:#040136;background:#fff}
.file-row:hover{border-color:#cdd0d8;text-decoration:none;background:#fafafb}
.tl{border-left:2px solid #e3e5ea;padding-left:16px;margin-left:5px}
.tl-item{position:relative;padding-bottom:14px;font-size:12.5px;color:#2f2d4d}
.tl-item::before{content:'';position:absolute;left:-21px;top:5px;width:8px;height:8px;border-radius:50%;background:#ff9101;border:2px solid #fff}
.tl-time{font-size:11px;color:#6b6a83}
</style>

<div class="tv-wrap">
  <?= showFlash() ?>

  <div class="tv-hero">
    <div class="tv-no"><?= h($t['ticket_no']) ?></div>
    <div class="tv-subj"><?= h($t['subject']) ?></div>
    <div class="tv-chips">
      <?= fbBadgeTrack($t['track']) ?>
      <?= fbBadgeStatus($t['status']) ?>
      <?php if ($t['track'] !== 'apresiasi') echo fbBadgePriority($t['priority']); ?>
      <?= fbBadgeOverdue($t) ?>
    </div>
  </div>

  <div class="tv-card">
    <div class="tv-grid">
      <div class="tv-kv"><div class="k">Kategori</div><div class="v"><?= h($t['category_name'] ?? '—') ?></div></div>
      <div class="tv-kv"><div class="k">Dikirim</div><div class="v"><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></div></div>
      <?php if ($t['track'] !== 'apresiasi'): ?>
      <div class="tv-kv">
        <div class="k"><?= in_array($t['status'],['selesai','ditutup'],true) ? 'Diselesaikan' : 'Target Selesai' ?></div>
        <div class="v"><?= in_array($t['status'],['selesai','ditutup'],true)
            ? ($t['resolved_at'] ? date('d M Y, H:i', strtotime($t['resolved_at'])) : '—')
            : ($t['due_at'] ? date('d M Y, H:i', strtotime($t['due_at'])) : '—') ?></div>
      </div>
      <div class="tv-kv"><div class="k">Ditangani</div><div class="v"><?= h(fbLevels()[$t['level']] ?? '—') ?></div></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="tv-card">
    <div class="tv-label">Isi Laporan</div>
    <div class="tv-msg"><?= h($t['message']) ?></div>
    <?php if (!empty($t['is_anonymous'])): ?>
    <div class="mt-2" style="font-size:11.5px;color:#6b6a83">
      <i class="bi bi-incognito me-1"></i>Dikirim tanpa menampilkan nama.
    </div>
    <?php endif; ?>
  </div>

  <?php if ($files): ?>
  <div class="tv-card">
    <div class="tv-label">Lampiran (<?= count($files) ?>)</div>
    <?php foreach ($files as $f): ?>
    <a href="<?= APP_URL ?>/feedback/attachment.php?id=<?= $f['id'] ?>" class="file-row">
      <i class="bi bi-paperclip" style="color:#6b6a83"></i>
      <span style="flex:1"><?= h($f['original_name']) ?></span>
      <?php if ($f['is_sealed']): ?><i class="bi bi-shield-lock-fill" title="Disegel" style="color:#b42318"></i><?php endif; ?>
      <span style="font-size:11.5px;color:#6b6a83"><?= fbFormatBytes((int)$f['size_bytes']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($t['status'] === 'selesai' || $t['status'] === 'ditutup'): ?>
  <div class="tv-card res-box">
    <div class="tv-label" style="color:#015c36">Penyelesaian</div>
    <div style="margin-bottom:9px">
      <?= fbChip(fbResolutions()[$t['resolution_type']] ?? '—', '#015c36', '#fff', '#a5dcc3') ?>
    </div>
    <div style="font-size:13.5px;color:#2f2d4d;line-height:1.75;white-space:pre-wrap"><?= h($t['resolution_note'] ?? '—') ?></div>
    <?php if ($t['resolved_at']): ?>
    <div style="font-size:11.5px;color:#6b6a83;margin-top:10px">
      Diselesaikan <?= date('d M Y, H:i', strtotime($t['resolved_at'])) ?>
      · lama penanganan <?= fbDurationText($t['created_at'], $t['resolved_at']) ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($messages): ?>
  <div class="tv-card">
    <div class="tv-label">Percakapan</div>
    <?php foreach ($messages as $m):
      $mine = (int)($m['author_id'] ?? 0) === (int)$user['id'];
      $sys  = !empty($m['is_system']); ?>
    <div class="msg <?= $mine?'mine':'' ?>">
      <div class="msg-av <?= (!$mine && !$sys)?'adm':'' ?>">
        <?= $sys ? '<i class="bi bi-gear-fill"></i>' : h(avatarInitials($m['author_name'] ?? 'S')) ?>
      </div>
      <div class="msg-b">
        <div class="msg-h"><b><?= $sys ? 'Sistem' : h($m['author_name'] ?? '—') ?></b> · <?= fbRelTime($m['created_at']) ?></div>
        <?= nl2br(h($m['body'])) ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($canReply): ?>
  <div class="tv-card">
    <div class="tv-label"><?= $t['status']==='selesai' ? 'Belum sesuai? Sampaikan di sini' : 'Tambahkan Keterangan' ?></div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <textarea name="body" rows="4" class="form-control mb-2" required minlength="3"
        placeholder="<?= $t['status']==='menunggu_pelapor' ? 'Penanggung jawab menunggu informasi tambahan dari Anda...' : 'Tulis di sini...' ?>"></textarea>
      <div class="text-end">
        <button class="btn btn-navy btn-sm px-3"><i class="bi bi-send-fill me-1"></i>Kirim</button>
      </div>
    </form>
  </div>
  <?php elseif ($isOwner && $t['track'] === 'safeguarding'): ?>
  <div class="tv-card" style="background:#f3f4f6;border-style:dashed">
    <div style="font-size:13px;color:#6b6a83;line-height:1.7">
      <i class="bi bi-shield-lock me-1"></i>
      Untuk menjaga keutuhan catatan, laporan perlindungan anak tidak dapat dibalas melalui sistem.
      Yayasan akan menghubungi Anda secara langsung bila diperlukan informasi tambahan.
    </div>
  </div>
  <?php endif; ?>

  <div class="text-center mt-3">
    <a href="<?= APP_URL ?>/feedback/my.php" class="small text-decoration-none">
      <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar laporan
    </a>
  </div>
</div>

<?php
$content = ob_get_clean();
pageWrapper('Detail Laporan', $content);
