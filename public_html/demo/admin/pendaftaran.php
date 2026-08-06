<?php
// AGKB 360° — Persetujuan Pengajuan Akun
//
// Antrean dari /publik/daftar.php. Menyetujui berarti membuat baris
// users tanpa kata sandi yang bisa dipakai, lalu mengirim tautan
// pembuatan kata sandi — sama seperti alur aktivasi yang sudah ada.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';
require_once __DIR__ . '/../includes/publik.php';
require_once __DIR__ . '/../includes/campaigns.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole(['superadmin','admin']);
$me = currentUser();

$filter = $_GET['status'] ?? 'menunggu';
if (!in_array($filter, ['menunggu','disetujui','ditolak','semua'], true)) $filter = 'menunggu';

// ── TINDAKAN ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $id    = (int)($_POST['id'] ?? 0);
    $aksi  = $_POST['aksi'] ?? '';
    $catatan = trim($_POST['catatan'] ?? '');
    $r = Database::fetchOne("SELECT * FROM registration_requests WHERE id=?", [$id]);

    if (!$r || $r['status'] !== 'menunggu') {
        flash('Pengajuan tidak ditemukan atau sudah pernah diputuskan.', 'warning');
    }
    elseif ($aksi === 'setuju') {
        // Bisa saja email itu sudah dibuatkan akun secara manual
        // sementara pengajuan menunggu. Diperiksa ulang di sini.
        if (Database::fetchOne("SELECT id FROM users WHERE email=?", [$r['email']])) {
            flash('Email ini sudah punya akun. Pengajuan ditandai selesai tanpa membuat akun baru.', 'warning');
            Database::update('registration_requests', [
                'status' => 'ditolak', 'decided_by' => $me['id'],
                'decided_at' => date('Y-m-d H:i:s'),
                'decision_note' => 'Akun untuk email ini sudah ada.',
            ], 'id = ?', [$id]);
        } else {
            $peran = $_POST['peran'] ?? $r['requested_role'];

            // Kata sandi acak yang tidak diberitahukan ke siapa pun.
            // Akun baru bisa dipakai setelah pemiliknya menetapkan
            // kata sandinya sendiri lewat tautan aktivasi.
            $uid = Database::insert('users', [
                'name'      => $r['name'],
                'email'     => $r['email'],
                'password'  => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                'role'      => $peran,
                'is_active' => 1,
            ]);

            Database::update('registration_requests', [
                'status' => 'disetujui', 'decided_by' => $me['id'],
                'decided_at' => date('Y-m-d H:i:s'),
                'decision_note' => $catatan ?: null,
                'created_user_id' => $uid,
            ], 'id = ?', [$id]);

            // Tiket tamu yang memicu pengajuan ikut dipindahkan ke
            // akun barunya, supaya muncul di Laporan Saya.
            if ($r['ticket_id']) {
                Database::query(
                    "UPDATE feedback_tickets SET sender_id=? WHERE id=? AND sender_id IS NULL",
                    [$uid, $r['ticket_id']]);
            }

            $url = fbAppUrl() . '/auth/set-password.php?token=' . kmpBuatToken($uid);
            fbSendMail($r['email'], 'Akun AGKB 360° Anda sudah aktif',
                fbMailTemplate('Pengajuan akun Anda disetujui',
                    '<p>Halo ' . h($r['name']) . ',</p>'
                  . '<p>Pengajuan akun Anda sudah disetujui. Buat kata sandi lewat tombol di '
                  . 'bawah untuk mulai menggunakan AGKB 360°. Tautan ini berlaku 7 hari.</p>'
                  . ($catatan ? '<p style="color:#6b6a83;font-size:13px">Catatan admin: ' . h($catatan) . '</p>' : ''),
                    $url, 'Buat Kata Sandi'));

            flash('Pengajuan disetujui. Tautan pembuatan kata sandi sudah dikirim ke ' . $r['email'] . '.', 'success');
        }
    }
    elseif ($aksi === 'tolak') {
        Database::update('registration_requests', [
            'status' => 'ditolak', 'decided_by' => $me['id'],
            'decided_at' => date('Y-m-d H:i:s'),
            'decision_note' => $catatan ?: null,
        ], 'id = ?', [$id]);

        fbSendMail($r['email'], 'Pengajuan akun AGKB 360°',
            fbMailTemplate('Pengajuan akun Anda belum dapat disetujui',
                '<p>Halo ' . h($r['name']) . ',</p>'
              . '<p>Terima kasih atas pengajuan Anda. Untuk saat ini akun belum dapat kami '
              . 'aktifkan.</p>'
              . ($catatan ? '<p style="color:#6b6a83;font-size:13px">Catatan: ' . h($catatan) . '</p>' : '')
              . '<p>Anda tetap dapat menyampaikan feedback tanpa akun lewat formulir publik.</p>',
                fbAppUrl() . '/publik/', 'Formulir Publik'));

        flash('Pengajuan ditolak dan pemohon sudah dikabari.', 'success');
    }

    header('Location: ' . APP_URL . '/admin/pendaftaran.php?status=' . $filter);
    exit;
}

// ── DATA ────────────────────────────────────────────────────
$where = $filter === 'semua' ? '1=1' : 'r.status = ?';
$args  = $filter === 'semua' ? [] : [$filter];

$rows = Database::fetchAll(
    "SELECT r.*, d.name AS decider_name, t.ticket_no
       FROM registration_requests r
       LEFT JOIN users d ON d.id = r.decided_by
       LEFT JOIN feedback_tickets t ON t.id = r.ticket_id
      WHERE $where
      ORDER BY FIELD(r.status,'menunggu','disetujui','ditolak'), r.created_at DESC",
    $args);

$jml = Database::fetchOne(
    "SELECT
       SUM(status='menunggu')  AS menunggu,
       SUM(status='disetujui') AS disetujui,
       SUM(status='ditolak')   AS ditolak,
       COUNT(*)                AS semua
     FROM registration_requests");

$peranLabel = pubPeranPengajuan();

ob_start(); ?>

<style>
.rq-tabs{display:flex;gap:6px;margin-bottom:18px;flex-wrap:wrap}
.rq-tab{padding:7px 15px;border-radius:999px;border:1px solid #e3e5ea;font-size:12.5px;color:#6b6a83;text-decoration:none;background:#fff;display:inline-flex;align-items:center;gap:7px}
.rq-tab:hover{border-color:#cdd0d8;color:#040136;text-decoration:none}
.rq-tab.on{background:#040136;border-color:#040136;color:#fff}
.rq-tab .n{font-weight:700}
.rq-card{background:#fff;border:1px solid #e3e5ea;border-radius:12px;padding:16px 18px;margin-bottom:11px;box-shadow:var(--agkb-shadow-sm)}
.rq-top{display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:flex-start}
.rq-name{font-size:14.5px;font-weight:700;color:#040136}
.rq-mail{font-size:12.5px;color:#6b6a83;margin-top:1px}
.rq-badge{font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;letter-spacing:.3px}
.rq-badge.menunggu{background:#fff8ef;color:#a85a01;border:1px solid #f5d9b0}
.rq-badge.disetujui{background:#e7f6ef;color:#015c36;border:1px solid #a6e0c4}
.rq-badge.ditolak{background:#f3f4f6;color:#4a4863;border:1px solid #e3e5ea}
.rq-meta{display:flex;gap:16px;flex-wrap:wrap;font-size:12px;color:#6b6a83;margin-top:10px}
.rq-reason{background:#fafafb;border:1px solid #f0f1f4;border-radius:9px;padding:11px 14px;font-size:13px;color:#2f2d4d;line-height:1.65;margin-top:12px}
.rq-act{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-top:14px;padding-top:14px;border-top:1px dashed #e3e5ea}
.rq-act select,.rq-act input{border:1.5px solid #e3e5ea;border-radius:8px;padding:8px 11px;font-size:12.5px;font-family:inherit;outline:none}
.rq-act input{flex:1;min-width:180px}
.rq-act label{display:block;font-size:10.5px;font-weight:600;color:#6b6a83;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.rq-empty{text-align:center;padding:50px 20px;color:#6b6a83}
.rq-empty i{font-size:40px;color:#cdd0d8;display:block;margin-bottom:12px}
.rq-note{font-size:12px;color:#6b6a83;margin-top:10px;font-style:italic}
</style>

<div class="rq-tabs">
  <?php foreach (['menunggu'=>'Menunggu','disetujui'=>'Disetujui','ditolak'=>'Ditolak','semua'=>'Semua'] as $k=>$v): ?>
  <a href="?status=<?= $k ?>" class="rq-tab <?= $filter===$k?'on':'' ?>">
    <?= h($v) ?><span class="n"><?= (int)($jml[$k] ?? 0) ?></span>
  </a>
  <?php endforeach; ?>
</div>

<?php if (!$rows): ?>
<div class="rq-card rq-empty">
  <i class="bi bi-inbox"></i>
  Tidak ada pengajuan <?= $filter==='semua' ? '' : h($filter) ?> saat ini.
</div>
<?php endif; ?>

<?php foreach ($rows as $r): ?>
<div class="rq-card">
  <div class="rq-top">
    <div>
      <div class="rq-name"><?= h($r['name']) ?></div>
      <div class="rq-mail"><?= h($r['email']) ?><?= $r['phone'] ? ' · ' . h($r['phone']) : '' ?></div>
    </div>
    <span class="rq-badge <?= h($r['status']) ?>"><?= h(ucfirst($r['status'])) ?></span>
  </div>

  <div class="rq-meta">
    <span><i class="bi bi-person-badge me-1"></i><?= h($peranLabel[$r['requested_role']] ?? $r['requested_role']) ?></span>
    <span><i class="bi bi-clock me-1"></i><?= h(fbRelTime($r['created_at'])) ?></span>
    <?php if ($r['ticket_no']): ?>
    <span><i class="bi bi-ticket-detailed me-1"></i><?= h($r['ticket_no']) ?></span>
    <?php endif; ?>
    <span><i class="bi bi-hdd-network me-1"></i><?= h(pubIpText($r['ip'])) ?></span>
  </div>

  <?php if ($r['reason']): ?>
  <div class="rq-reason"><?= nl2br(h($r['reason'])) ?></div>
  <?php endif; ?>

  <?php if ($r['status'] === 'menunggu'): ?>
  <form method="POST" class="rq-act">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
    <div>
      <label>Peran</label>
      <select name="peran">
        <?php foreach ($peranLabel as $k=>$v): ?>
        <option value="<?= h($k) ?>" <?= $r['requested_role']===$k?'selected':'' ?>><?= h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="flex:1;min-width:180px">
      <label>Catatan <span style="font-weight:400;text-transform:none">(ikut terkirim ke pemohon)</span></label>
      <input type="text" name="catatan" maxlength="500" placeholder="Opsional">
    </div>
    <button name="aksi" value="setuju" class="btn btn-navy btn-sm px-3"
            onclick="return confirm('Setujui dan buatkan akun untuk <?= h($r['email']) ?>?')">
      <i class="bi bi-check-lg me-1"></i>Setujui
    </button>
    <button name="aksi" value="tolak" class="btn btn-outline-danger btn-sm px-3"
            onclick="return confirm('Tolak pengajuan ini?')">
      <i class="bi bi-x-lg me-1"></i>Tolak
    </button>
  </form>
  <?php else: ?>
  <div class="rq-note">
    <?= h(ucfirst($r['status'])) ?> oleh <?= h($r['decider_name'] ?? '—') ?>
    <?= $r['decided_at'] ? ' · ' . date('d M Y, H:i', strtotime($r['decided_at'])) : '' ?>
    <?= $r['decision_note'] ? ' — ' . h($r['decision_note']) : '' ?>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php
$content = ob_get_clean();
pageWrapper('Pengajuan Akun', $content);
