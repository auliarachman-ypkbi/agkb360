<?php
// AGKB 360° — Pengajuan Akun (publik)
//
// Ini BUKAN pendaftaran. Tidak ada akun yang terbentuk di sini dan
// tidak ada kata sandi yang diminta — AGKB 360° sistem tertutup,
// jadi yang tersimpan hanya pengajuan yang menunggu persetujuan
// admin. Kata sandi baru dibuat lewat tautan aktivasi setelah
// disetujui, memakai alur set-password yang sudah ada.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';
require_once __DIR__ . '/../includes/publik.php';
require_once __DIR__ . '/../includes/layout.php';

startSession();

$error   = '';
$terkirim = false;
$ipBin   = pubClientIpBin();

// Tiket asal, kalau pengunjung datang dari halaman selesai kirim.
// Dipakai hanya sebagai penanda kaitan; tidak dipercaya sebagai
// bukti identitas apa pun.
$tiketId = (int)($_GET['tiket'] ?? $_POST['ticket_id'] ?? 0);
$tiket   = $tiketId
    ? Database::fetchOne("SELECT id, ticket_no, guest_name, guest_email
                            FROM feedback_tickets WHERE id=? AND sender_id IS NULL", [$tiketId])
    : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $nama   = trim($_POST['name'] ?? '');
    $email  = trim(mb_strtolower($_POST['email'] ?? ''));
    $telp   = trim($_POST['phone'] ?? '');
    $peran  = $_POST['requested_role'] ?? '';
    $alasan = trim($_POST['reason'] ?? '');

    if (!empty($_POST['website'])) {              // honeypot
        $terkirim = true;
    }
    elseif (mb_strlen($nama) < 3)                 $error = 'Isi nama Anda.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
                                                  $error = 'Alamat email tidak valid.';
    elseif (!isset(pubPeranPengajuan()[$peran]))  $error = 'Pilih peran yang Anda ajukan.';
    elseif (mb_strlen($alasan) < 10)              $error = 'Jelaskan singkat keperluan Anda (minimal 10 karakter).';
    elseif (($tolak = pubTolakPengajuan($email)) !== null) $error = $tolak;
    else {
        pubSimpanPengajuan([
            'name'           => $nama,
            'email'          => $email,
            'phone'          => $telp,
            'requested_role' => $peran,
            'reason'         => $alasan,
            'ticket_id'      => $tiket['id'] ?? null,
            'ip_bin'         => $ipBin,
        ]);

        // Kabari admin. Sengaja tanpa tautan langsung ke persetujuan:
        // admin harus masuk dulu, dan di sana izinnya diperiksa.
        foreach (Database::fetchAll(
                    "SELECT email FROM users
                      WHERE role IN ('superadmin','admin') AND is_active=1") as $a) {
            fbSendMail($a['email'], 'Pengajuan akun baru — ' . $nama,
                fbMailTemplate('Pengajuan akun baru menunggu tinjauan',
                    '<p><strong>' . h($nama) . '</strong> (' . h($email) . ') mengajukan akun '
                  . 'sebagai <strong>' . h(pubPeranPengajuan()[$peran]) . '</strong>.</p>'
                  . '<p style="color:#6b6a83;font-size:13px">Keperluan: ' . h($alasan) . '</p>',
                    fbAppUrl() . '/admin/pendaftaran.php', 'Tinjau Pengajuan'));
        }

        $terkirim = true;
    }
}

ob_start(); ?>

<style>
.df-wrap{max-width:620px;margin:0 auto}
.df-card{background:#fff;border:1px solid #e3e5ea;border-radius:14px;overflow:hidden;margin-bottom:16px;box-shadow:var(--agkb-shadow-sm)}
.df-hdr{padding:18px 22px;background:#040136;color:#fff}
.df-hdr-title{font-size:16px;font-weight:600}
.df-hdr-sub{font-size:12px;opacity:.75;margin-top:2px}
.df-body{padding:22px}
.field{margin-bottom:16px}
.field>label{display:block;font-size:11px;font-weight:600;color:#6b6a83;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.field input,.field select,.field textarea{width:100%;border:1.5px solid #e3e5ea;border-radius:9px;padding:10px 13px;font-size:13.5px;color:#040136;font-family:inherit;outline:none;background:#fff}
.field input:focus,.field select:focus,.field textarea:focus{border-color:#2201b2;box-shadow:0 0 0 3px rgba(34,1,178,.12)}
.field textarea{resize:vertical;line-height:1.7}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.info-box{background:#eeebfc;border:1px solid #b9aef2;border-radius:9px;padding:12px 15px;font-size:12.5px;color:#030870;margin-bottom:18px;line-height:1.65}
.err-box{background:#fdeceb;border:1px solid #f3b5b0;border-radius:9px;padding:11px 14px;font-size:13px;color:#8c1610;margin-bottom:16px;line-height:1.6}
.hint{font-size:11.5px;color:#6b6a83;margin-top:5px;line-height:1.5}
.ok{text-align:center;padding:34px 24px}
.ok-icon{font-size:46px;color:#027a48;margin-bottom:14px;display:block}
.ok-title{font-size:19px;font-weight:700;color:#040136;margin-bottom:8px}
.ok-sub{font-size:14px;color:#6b6a83;line-height:1.7;margin-bottom:18px}
.hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden}
@media(max-width:640px){.two-col{grid-template-columns:1fr}}
</style>

<div class="df-wrap">

<?php if ($terkirim): ?>
<div class="df-card"><div class="df-body"><div class="ok">
  <i class="bi bi-send-check-fill ok-icon"></i>
  <div class="ok-title">Pengajuan Anda terkirim</div>
  <div class="ok-sub">
    Admin akan meninjau pengajuan ini. Anda akan menerima email berisi tautan
    pembuatan kata sandi kalau disetujui, atau pemberitahuan kalau tidak.<br>
    Belum ada akun yang aktif sampai peninjauan selesai.
  </div>
  <a href="<?= APP_URL ?>/publik/" class="btn btn-outline-navy btn-sm px-3">
    <i class="bi bi-arrow-left me-1"></i>Kembali
  </a>
</div></div></div>

<?php else: ?>

<div class="df-card">
  <div class="df-hdr">
    <div class="df-hdr-title">Ajukan Akun</div>
    <div class="df-hdr-sub">Perlu persetujuan admin — AGKB 360° adalah sistem tertutup</div>
  </div>
  <div class="df-body">

    <?php if ($error): ?>
    <div class="err-box"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= h($error) ?></div>
    <?php endif; ?>

    <div class="info-box">
      <i class="bi bi-info-circle-fill me-1"></i>
      Formulir ini tidak langsung membuat akun. Admin memeriksa dulu apakah Anda memang
      bagian dari lingkungan sekolah. Karena itu tidak ada kolom kata sandi di sini —
      kata sandi Anda buat sendiri lewat tautan yang dikirim setelah disetujui.
    </div>

    <?php if ($tiket): ?>
    <div class="info-box" style="background:#e7f6ef;border-color:#a6e0c4;color:#015c36">
      <i class="bi bi-link-45deg me-1"></i>
      Dikaitkan dengan laporan <strong><?= h($tiket['ticket_no']) ?></strong>.
      Setelah akun aktif, laporan itu akan muncul di daftar laporan Anda.
    </div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="ticket_id" value="<?= (int)($tiket['id'] ?? 0) ?>">

      <div class="hp" aria-hidden="true">
        <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <div class="two-col">
        <div class="field">
          <label>Nama Lengkap <span style="color:#b42318">*</span></label>
          <input type="text" name="name" maxlength="100" required autocomplete="name"
                 value="<?= h($_POST['name'] ?? $tiket['guest_name'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Email <span style="color:#b42318">*</span></label>
          <input type="email" name="email" maxlength="190" required autocomplete="email"
                 value="<?= h($_POST['email'] ?? $tiket['guest_email'] ?? '') ?>">
        </div>
      </div>

      <div class="two-col">
        <div class="field">
          <label>Nomor HP <span style="font-weight:400;text-transform:none;letter-spacing:0">(opsional)</span></label>
          <input type="tel" name="phone" maxlength="40" autocomplete="tel"
                 value="<?= h($_POST['phone'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Peran yang Diajukan <span style="color:#b42318">*</span></label>
          <select name="requested_role" required>
            <option value="">— Pilih —</option>
            <?php foreach (pubPeranPengajuan() as $k=>$v): ?>
            <option value="<?= h($k) ?>" <?= ($_POST['requested_role']??'')===$k?'selected':'' ?>><?= h($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="field">
        <label>Keperluan <span style="color:#b42318">*</span></label>
        <textarea name="reason" rows="4" maxlength="500" required
          placeholder="Contoh: Saya orang tua dari Ananda … kelas … dan ingin memantau tindak lanjut laporan."><?= h($_POST['reason'] ?? '') ?></textarea>
        <div class="hint">Sebutkan kaitan Anda dengan sekolah sejelas mungkin — ini yang dipakai admin untuk memverifikasi.</div>
      </div>

      <div class="d-flex gap-2 justify-content-end">
        <a href="<?= APP_URL ?>/publik/" class="btn btn-outline-secondary btn-sm px-3">Batal</a>
        <button type="submit" class="btn btn-navy btn-sm px-3">
          <i class="bi bi-send me-1"></i>Kirim Pengajuan
        </button>
      </div>
    </form>
  </div>
</div>

<?php endif; ?>
</div>

<?php
$content = ob_get_clean();
publicWrapper('Ajukan Akun', $content);
