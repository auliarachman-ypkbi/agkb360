<?php
// AGKB 360° — Pelacakan Tiket Publik
//
// Halaman ini sengaja pelit. Yang ditampilkan hanya status, subjek,
// dan tanggal — bukan isi pesan, bukan catatan penanganan, bukan
// identitas siapa pun. Token bisa bocor lewat riwayat peramban,
// email yang diteruskan, atau perangkat bersama, jadi halaman ini
// diperlakukan sebagai setengah publik.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';
require_once __DIR__ . '/../includes/publik.php';
require_once __DIR__ . '/../includes/layout.php';

$token = trim($_GET['t'] ?? $_POST['t'] ?? '');
$t     = $token !== '' ? pubTiketDariToken($token) : null;
$error = '';

if ($token !== '' && !$t) {
    $error = 'Tautan pelacakan tidak dikenali atau sudah kedaluwarsa. '
           . 'Periksa kembali tautan pada email yang Anda terima.';
}

// Riwayat status, tanpa catatan internal. fbLogEvent menyimpan
// catatan yang kadang berisi nama penanganan — di sini hanya
// perpindahan statusnya yang diambil.
$riwayat = [];
if ($t) {
    $riwayat = Database::fetchAll(
        "SELECT event_type, to_value, created_at
           FROM feedback_events
          WHERE ticket_id = ?
            AND event_type IN ('dibuat','status_diubah','diselesaikan','ditutup','dibuka_kembali')
          ORDER BY created_at",
        [$t['id']]);
}

$S = fbStatuses();
ob_start(); ?>

<style>
.lc-wrap{max-width:620px;margin:0 auto}
.lc-card{background:#fff;border:1px solid #e3e5ea;border-radius:14px;overflow:hidden;margin-bottom:16px;box-shadow:var(--agkb-shadow-sm)}
.lc-hdr{padding:18px 22px;background:#040136;color:#fff}
.lc-hdr-title{font-size:16px;font-weight:600}
.lc-hdr-sub{font-size:12px;opacity:.75;margin-top:2px}
.lc-body{padding:22px}
.lc-no{display:inline-block;background:#040136;color:#fff;border-radius:8px;padding:8px 18px;font-size:14.5px;font-weight:700;letter-spacing:.05em}
.lc-subj{font-size:15px;font-weight:600;color:#040136;margin:14px 0 4px;line-height:1.5}
.lc-meta{font-size:12px;color:#6b6a83}
.lc-row{display:flex;justify-content:space-between;gap:12px;padding:11px 0;border-bottom:1px solid #f0f1f4;font-size:13px}
.lc-row:last-child{border-bottom:0}
.lc-row .k{color:#6b6a83}
.lc-row .v{color:#040136;font-weight:600;text-align:right}
.tl{list-style:none;padding:0;margin:14px 0 0}
.tl li{position:relative;padding:0 0 16px 22px;font-size:12.5px;color:#2f2d4d;line-height:1.6}
.tl li::before{content:'';position:absolute;left:0;top:5px;width:9px;height:9px;border-radius:50%;background:#b9aef2}
.tl li:last-child::before{background:#027a48}
.tl li::after{content:'';position:absolute;left:4px;top:14px;bottom:0;width:1px;background:#e3e5ea}
.tl li:last-child::after{display:none}
.tl .w{display:block;font-size:11px;color:#6f6e85;margin-top:1px}
.err-box{background:#fdeceb;border:1px solid #f3b5b0;border-radius:9px;padding:12px 15px;font-size:13px;color:#8c1610;margin-bottom:16px;line-height:1.6}
.note{background:#f3f4f6;border-radius:9px;padding:12px 15px;font-size:12.5px;color:#4a4863;line-height:1.65;margin-top:18px}
.field label{display:block;font-size:11px;font-weight:600;color:#6b6a83;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.field input{width:100%;border:1.5px solid #e3e5ea;border-radius:9px;padding:10px 13px;font-size:13.5px;font-family:inherit;outline:none}
.field input:focus{border-color:#2201b2;box-shadow:0 0 0 3px rgba(34,1,178,.12)}
</style>

<div class="lc-wrap">

<?php if ($error): ?>
<div class="err-box"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= h($error) ?></div>
<?php endif; ?>

<?php if ($t): ?>
<div class="lc-card">
  <div class="lc-hdr">
    <div class="lc-hdr-title">Status Laporan</div>
    <div class="lc-hdr-sub">Terakhir diperbarui <?= h(fbRelTime($t['updated_at'])) ?></div>
  </div>
  <div class="lc-body">
    <div class="lc-no"><?= h($t['ticket_no']) ?></div>
    <div class="lc-subj"><?= h($t['subject']) ?></div>
    <div class="lc-meta">Dikirim <?= date('d M Y, H:i', strtotime($t['created_at'])) ?></div>

    <div style="margin-top:18px">
      <div class="lc-row">
        <span class="k">Status</span>
        <span class="v"><?= fbBadgeStatus($t['status']) ?></span>
      </div>
      <div class="lc-row">
        <span class="k">Jalur</span>
        <span class="v"><?= fbBadgeTrack($t['track']) ?></span>
      </div>
      <?php if ($t['track'] !== 'apresiasi' && $t['due_at']): ?>
      <div class="lc-row">
        <span class="k">Target penyelesaian</span>
        <span class="v"><?= date('d M Y', strtotime($t['due_at'])) ?></span>
      </div>
      <?php endif; ?>
      <?php if ($t['resolved_at']): ?>
      <div class="lc-row">
        <span class="k">Diselesaikan</span>
        <span class="v"><?= date('d M Y, H:i', strtotime($t['resolved_at'])) ?></span>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($riwayat): ?>
    <div style="margin-top:20px">
      <div style="font-size:11px;font-weight:700;color:#6b6a83;letter-spacing:.7px;text-transform:uppercase">Riwayat</div>
      <ul class="tl">
        <?php foreach ($riwayat as $r): ?>
        <li>
          <?= h($r['event_type'] === 'dibuat'
                ? 'Laporan diterima'
                : ($S[$r['to_value']]['label'] ?? ucfirst((string)$r['to_value']))) ?>
          <span class="w"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div class="note">
      <i class="bi bi-shield-lock me-1"></i>Demi menjaga kerahasiaan, halaman ini hanya
      menampilkan status. Isi laporan dan catatan penanganan tidak ditampilkan di sini.
      Penanggung jawab akan menghubungi Anda lewat email
      <strong><?= h($t['guest_email']) ?></strong> bila memerlukan keterangan tambahan.
    </div>
  </div>
</div>

<div class="text-center">
  <a href="<?= APP_URL ?>/publik/" class="small text-decoration-none">
    <i class="bi bi-plus-lg me-1"></i>Kirim laporan baru
  </a>
</div>

<?php else: ?>

<div class="lc-card">
  <div class="lc-hdr">
    <div class="lc-hdr-title">Lacak Laporan</div>
    <div class="lc-hdr-sub">Buka lewat tautan pada email yang Anda terima</div>
  </div>
  <div class="lc-body">
    <p style="font-size:13.5px;color:#4a4863;line-height:1.7;margin-bottom:18px">
      Pelacakan hanya bisa dibuka lewat tautan pribadi yang kami kirim ke email Anda
      saat laporan dikirim. Nomor tiket saja tidak cukup — ini justru untuk melindungi
      laporan Anda dari dibuka orang lain yang kebetulan tahu nomornya.
    </p>
    <div class="field">
      <label>Tempel tautan atau token pelacakan</label>
      <form method="get" onsubmit="var i=this.t;var m=i.value.match(/[0-9a-f]{64}/i);if(m)i.value=m[0];">
        <input type="text" name="t" placeholder="https://…/lacak.php?t=… atau tokennya saja"
               value="<?= h($token) ?>">
        <button class="btn btn-navy btn-sm px-3 mt-3" type="submit">
          <i class="bi bi-search me-1"></i>Lacak
        </button>
      </form>
    </div>
    <div class="note">
      Emailnya tidak ketemu? Coba periksa folder spam. Kalau tautannya benar-benar hilang,
      hubungi sekolah dengan menyebutkan nomor tiket Anda — petugas dapat memeriksakannya
      untuk Anda.
    </div>
  </div>
</div>

<div class="text-center">
  <a href="<?= APP_URL ?>/publik/" class="small text-decoration-none">
    <i class="bi bi-chat-heart me-1"></i>Kirim laporan baru
  </a>
</div>

<?php endif; ?>
</div>

<?php
$content = ob_get_clean();
publicWrapper('Lacak Laporan', $content);
