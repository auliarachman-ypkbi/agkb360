<?php
// AGKB 360° — Formulir Feedback Publik (tanpa login)
//
// Kembaran /feedback/index.php untuk pelapor yang belum punya akun.
// Perbedaan pokoknya:
//   · tidak ada requireLogin(), jadi tidak ada currentUser()
//   · identitas diisi sendiri dan TIDAK terverifikasi
//   · ada honeypot dan pembatasan laju per IP
//   · tidak ada lampiran — berkas dari sumber tak terverifikasi
//     terlalu berisiko untuk diterima begitu saja
//   · tidak ada opsi anonim: tanpa akun, email pelacakan sudah
//     jadi satu-satunya pegangan, jadi menyembunyikannya justru
//     membuat pelapor kehilangan akses ke tiketnya sendiri

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';
require_once __DIR__ . '/../includes/publik.php';
require_once __DIR__ . '/../includes/layout.php';

// Sesi tetap dimulai — hanya untuk token CSRF, bukan untuk login.
startSession();

$track   = $_POST['track'] ?? $_GET['track'] ?? 'inquiry';
$error   = '';
$sukses  = null;
$token   = null;

$allTracks = ['apresiasi','inquiry','safeguarding'];
if (!in_array($track, $allTracks, true)) $track = 'inquiry';

$categories = Database::fetchAll(
    "SELECT * FROM feedback_categories WHERE is_active=1 ORDER BY order_num, name");
$catByTrack = [];
foreach ($categories as $c) $catByTrack[$c['track']][] = $c;

$ipBin = pubClientIpBin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $nama    = trim($_POST['guest_name'] ?? '');
    $email   = trim(mb_strtolower($_POST['guest_email'] ?? ''));
    $telepon = trim($_POST['guest_phone'] ?? '');
    $hubung  = trim($_POST['guest_role'] ?? '');
    $catId   = (int)($_POST['category_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $impact  = $_POST['impact'] ?? null;

    $cat = $catId
        ? Database::fetchOne("SELECT * FROM feedback_categories WHERE id=? AND is_active=1", [$catId])
        : null;

    // Honeypot: kolom yang tersembunyi lewat CSS. Manusia tidak
    // pernah mengisinya; bot pengisi-semua-kolom hampir selalu iya.
    // Sengaja dibalas seolah berhasil, supaya bot tidak belajar.
    if (!empty($_POST['website'])) {
        $sukses = ['ticket_no' => 'AGKB-000000', 'track' => 'inquiry', 'due_at' => null];
        $token  = null;
    }
    elseif (($tolak = pubCekLaju($ipBin)) !== null) $error = $tolak;
    elseif (mb_strlen($nama) < 3)                   $error = 'Isi nama Anda.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
                                                    $error = 'Alamat email tidak valid. Email ini dipakai untuk mengirim tautan pelacakan.';
    elseif (!$cat)                                  $error = 'Pilih kategori terlebih dahulu.';
    elseif (mb_strlen($subject) < 5)                $error = 'Subjek terlalu pendek (minimal 5 karakter).';
    elseif (mb_strlen($message) < 20)               $error = 'Pesan terlalu pendek (minimal 20 karakter).';
    elseif ($cat['track'] === 'inquiry' && !in_array($impact, array_keys(fbImpacts()), true))
                                                    $error = 'Pilih cakupan dampaknya.';
    else {
        $track = $cat['track'];

        $hasil = pubBuatTiketTamu([
            'category_id' => $cat['id'],
            'track'       => $track,
            'subject'     => $subject,
            'message'     => $message,
            'impact'      => $track === 'inquiry' ? $impact : null,
            'guest_name'  => $nama,
            'guest_email' => $email,
            'guest_phone' => $telepon,
            'guest_role'  => $hubung,
            'ip_bin'      => $ipBin,
        ]);

        $sukses = fbLoadFull($hasil['id']);
        $token  = $hasil['token'];

        // Notifikasi ke penanganan memakai jalur yang sudah ada,
        // supaya aturan siapa-boleh-tahu tetap satu sumber.
        fbNotifyNew($hasil['id']);
        pubKirimEmailTiket($sukses, $token);
    }
}

$T = fbTracks();
ob_start(); ?>

<style>
.pb-wrap{max-width:760px;margin:0 auto}
.pb-card{background:#fff;border:1px solid #e3e5ea;border-radius:14px;overflow:hidden;margin-bottom:16px;box-shadow:var(--agkb-shadow-sm)}
.pb-hdr{padding:18px 22px;background:#040136;color:#fff;display:flex;align-items:center;gap:12px}
.pb-hdr-title{font-size:16px;font-weight:600}
.pb-hdr-sub{font-size:12px;opacity:.75;margin-top:2px;line-height:1.5}
.pb-body{padding:22px}
.pb-step{font-size:11px;font-weight:700;color:#6b6a83;letter-spacing:.7px;text-transform:uppercase;margin:0 0 12px;display:flex;align-items:center;gap:8px}
.pb-step::after{content:'';flex:1;height:1px;background:#e3e5ea}
.track-row{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:22px}
.track-opt{border:1.5px solid #e3e5ea;border-radius:11px;padding:14px 12px;text-decoration:none;display:block;transition:all .15s;background:#fff}
.track-opt:hover{border-color:#cdd0d8;transform:translateY(-1px);text-decoration:none}
.track-opt.active{border-color:#040136;background:#fafafb;box-shadow:0 0 0 3px rgba(4,1,54,.06)}
.track-opt.active.sg{border-color:#b42318;box-shadow:0 0 0 3px rgba(180,35,24,.08)}
.track-icon{font-size:20px;display:block;margin-bottom:7px}
.track-label{font-size:13px;font-weight:600;color:#040136}
.track-desc{font-size:11.5px;color:#6b6a83;margin-top:3px;line-height:1.5}
.field{margin-bottom:16px}
.field>label{display:block;font-size:11px;font-weight:600;color:#6b6a83;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.field input[type=text],.field input[type=email],.field input[type=tel],.field textarea,.field select{width:100%;border:1.5px solid #e3e5ea;border-radius:9px;padding:10px 13px;font-size:13.5px;color:#040136;font-family:inherit;outline:none;transition:border .15s,box-shadow .15s;background:#fff}
.field input:focus,.field textarea:focus,.field select:focus{border-color:#2201b2;box-shadow:0 0 0 3px rgba(34,1,178,.12)}
.field textarea{resize:vertical;line-height:1.7}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.cat-grid{display:grid;grid-template-columns:1fr 1fr;gap:9px}
.cat-opt{border:1.5px solid #e3e5ea;border-radius:9px;padding:11px 13px;cursor:pointer;transition:all .13s;position:relative;background:#fff}
.cat-opt input{position:absolute;opacity:0;width:0;height:0}
.cat-opt:hover{border-color:#cdd0d8}
.cat-opt:has(input:checked){border-color:#2201b2;background:#eeebfc}
.cat-opt.sg:has(input:checked){border-color:#b42318;background:#fdeceb}
.cat-name{font-size:13px;font-weight:600;color:#040136}
.cat-desc{font-size:11.5px;color:#6b6a83;margin-top:2px;line-height:1.45}
.char-count{font-size:11px;color:#6f6e85;text-align:right;margin-top:4px}
.info-box{background:#eeebfc;border:1px solid #b9aef2;border-radius:9px;padding:11px 14px;font-size:12.5px;color:#030870;margin-bottom:18px;display:flex;gap:9px;align-items:flex-start;line-height:1.6}
.warn-box{background:#fdeceb;border:1px solid #f3b5b0;border-radius:9px;padding:14px 16px;font-size:13px;color:#8c1610;margin-bottom:18px;line-height:1.65}
.warn-box strong{display:block;margin-bottom:4px;font-size:13.5px}
.err-box{background:#fdeceb;border:1px solid #f3b5b0;border-radius:9px;padding:11px 14px;font-size:13px;color:#8c1610;margin-bottom:16px;display:flex;gap:8px;align-items:center}
.hint{font-size:11.5px;color:#6b6a83;margin-top:5px;line-height:1.5}
.btn-row{display:flex;gap:10px;justify-content:flex-end;margin-top:8px}
.btn-submit{padding:11px 26px;border:none;border-radius:9px;background:#040136;color:#fff;font-size:13.5px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:7px}
.btn-submit:hover{background:#030870}
.btn-submit.sg{background:#b42318}.btn-submit.sg:hover{background:#8c1610}
.success-box{text-align:center;padding:34px 24px}
.success-icon{font-size:46px;color:#027a48;margin-bottom:14px;display:block}
.success-title{font-size:19px;font-weight:700;color:#040136;margin-bottom:8px}
.success-sub{font-size:14px;color:#6b6a83;line-height:1.7;margin-bottom:18px}
.ticket-chip{display:inline-block;background:#040136;color:#fff;border-radius:8px;padding:9px 20px;font-size:15px;font-weight:700;letter-spacing:.05em;margin-bottom:16px}
.ajak{border-top:1px dashed #e3e5ea;margin-top:26px;padding-top:22px;text-align:left}
.ajak-title{font-size:14.5px;font-weight:700;color:#040136;margin-bottom:5px}
.ajak-desc{font-size:12.5px;color:#6b6a83;line-height:1.65;margin-bottom:12px}
/* Honeypot — tak terlihat manusia, tetap terisi oleh bot */
.hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden}
@media(max-width:640px){.track-row{grid-template-columns:1fr}.cat-grid,.two-col{grid-template-columns:1fr}}
</style>

<div class="pb-wrap">

<?php if ($sukses): ?>
<div class="pb-card"><div class="pb-body"><div class="success-box">
  <i class="bi bi-check-circle-fill success-icon"></i>
  <div class="success-title">Laporan Anda tercatat</div>
  <div class="ticket-chip"><?= h($sukses['ticket_no']) ?></div>
  <div class="success-sub">
    <?php if ($token): ?>
      Kami sudah mengirim nomor tiket dan tautan pelacakan ke email Anda.<br>
      <?php if ($sukses['track'] === 'safeguarding'): ?>
        Laporan ini diterima Yayasan dan akan ditindaklanjuti dalam <strong>1×24 jam</strong>.
      <?php elseif ($sukses['due_at']): ?>
        Target penyelesaian: <strong><?= date('d M Y, H:i', strtotime($sukses['due_at'])) ?></strong>
      <?php endif; ?>
    <?php else: ?>
      Terima kasih atas masukan Anda.
    <?php endif; ?>
  </div>

  <?php if ($token): ?>
  <a href="<?= h(pubUrlLacak($token)) ?>" class="btn btn-navy btn-sm px-3">
    <i class="bi bi-search me-1"></i>Lacak Laporan Ini
  </a>

  <div class="ajak">
    <div class="ajak-title">Sering berurusan dengan sekolah?</div>
    <div class="ajak-desc">
      Dengan akun, Anda bisa melihat seluruh riwayat laporan dalam satu tempat dan
      membalas langsung di dalam tiket — tanpa perlu menyimpan tautan.
      AGKB 360° adalah sistem tertutup, jadi setiap pengajuan akun ditinjau admin
      terlebih dahulu. Anda akan dikabari lewat email setelah ditinjau.
    </div>
    <a href="<?= APP_URL ?>/publik/daftar.php?tiket=<?= (int)$sukses['id'] ?>"
       class="btn btn-outline-navy btn-sm px-3">
      <i class="bi bi-person-plus me-1"></i>Ajukan Akun
    </a>
  </div>
  <?php endif; ?>
</div></div></div>

<?php else: ?>

<div class="pb-card">
  <div class="pb-hdr">
    <i class="bi bi-chat-heart-fill" style="font-size:24px"></i>
    <div>
      <div class="pb-hdr-title">Sampaikan Feedback</div>
      <div class="pb-hdr-sub">Terbuka untuk siapa saja — tidak perlu punya akun</div>
    </div>
  </div>
  <div class="pb-body">

    <?php if ($error): ?>
    <div class="err-box"><i class="bi bi-exclamation-triangle-fill"></i><?= h($error) ?></div>
    <?php endif; ?>

    <div class="pb-step">1 · Jenis laporan</div>
    <div class="track-row">
      <?php
      $trackDesc = [
        'apresiasi'    => 'Akui kontribusi positif atau hal yang berjalan baik',
        'inquiry'      => 'Sampaikan kendala, keluhan, atau saran perbaikan',
        'safeguarding' => 'Laporan yang menyangkut keselamatan dan perlindungan anak',
      ];
      foreach ($allTracks as $tk):
        if (empty($catByTrack[$tk])) continue; ?>
        <a href="?track=<?= $tk ?>" class="track-opt <?= $track===$tk?'active':'' ?> <?= $tk==='safeguarding'?'sg':'' ?>">
          <i class="bi <?= $T[$tk]['icon'] ?> track-icon" style="color:<?= $T[$tk]['color'] ?>"></i>
          <div class="track-label"><?= h($T[$tk]['label']) ?></div>
          <div class="track-desc"><?= h($trackDesc[$tk]) ?></div>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($track === 'safeguarding'): ?>
    <div class="warn-box">
      <strong><i class="bi bi-exclamation-octagon-fill me-1"></i>Jika ada anak dalam bahaya saat ini, jangan menunggu sistem ini.</strong>
      Hubungi penanggung jawab perlindungan anak secara langsung, atau layanan darurat.
      Formulir ini untuk pencatatan dan tindak lanjut, bukan penanganan darurat.
    </div>
    <div class="info-box">
      <i class="bi bi-shield-check" style="flex-shrink:0;margin-top:1px"></i>
      <span>Laporan ini <strong>hanya dapat dilihat oleh Yayasan</strong> — tidak oleh admin
      maupun pimpinan sekolah. Tidak boleh ada tindakan balasan apa pun terhadap pelapor
      yang beritikad baik.</span>
    </div>
    <?php else: ?>
    <div class="info-box">
      <i class="bi bi-info-circle-fill" style="flex-shrink:0;margin-top:1px"></i>
      <span>Setiap laporan mendapat nomor tiket dan penanggung jawab. Nomor dan tautan
      pelacakan dikirim ke email Anda.</span>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="on">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="track" value="<?= h($track) ?>">

      <div class="hp" aria-hidden="true">
        <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <div class="pb-step">2 · Siapa Anda</div>

      <div class="two-col">
        <div class="field">
          <label>Nama <span style="color:#b42318">*</span></label>
          <input type="text" name="guest_name" maxlength="100" required autocomplete="name"
                 value="<?= h($_POST['guest_name'] ?? '') ?>" placeholder="Nama lengkap">
        </div>
        <div class="field">
          <label>Email <span style="color:#b42318">*</span></label>
          <input type="email" name="guest_email" maxlength="190" required autocomplete="email"
                 value="<?= h($_POST['guest_email'] ?? '') ?>" placeholder="nama@contoh.com">
        </div>
      </div>

      <div class="two-col">
        <div class="field">
          <label>Nomor HP <span style="font-weight:400;text-transform:none;letter-spacing:0">(opsional)</span></label>
          <input type="tel" name="guest_phone" maxlength="40" autocomplete="tel"
                 value="<?= h($_POST['guest_phone'] ?? '') ?>" placeholder="08…">
        </div>
        <div class="field">
          <label>Hubungan dengan Sekolah</label>
          <select name="guest_role">
            <option value="">— Pilih —</option>
            <?php foreach (['Orang Tua / Wali','Siswa','Alumni','Guru','Staf','Mitra / Vendor','Masyarakat Umum'] as $r): ?>
            <option value="<?= h($r) ?>" <?= ($_POST['guest_role']??'')===$r?'selected':'' ?>><?= h($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="hint" style="margin:-6px 0 20px">
        <i class="bi bi-envelope-check me-1"></i>Email wajib diisi karena hanya lewat
        situ Anda bisa memantau tindak lanjutnya. Kontak Anda tidak dipakai untuk hal lain.
      </div>

      <div class="pb-step">3 · Isi laporan</div>

      <div class="field">
        <label>Kategori <span style="color:#b42318">*</span></label>
        <div class="cat-grid">
          <?php foreach (($catByTrack[$track] ?? []) as $c): ?>
          <label class="cat-opt <?= $track==='safeguarding'?'sg':'' ?>">
            <input type="radio" name="category_id" value="<?= $c['id'] ?>" required
                   <?= (int)($_POST['category_id']??0)===(int)$c['id']?'checked':'' ?>>
            <div class="cat-name"><?= h($c['name']) ?></div>
            <?php if ($c['description']): ?><div class="cat-desc"><?= h($c['description']) ?></div><?php endif; ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($track === 'inquiry'): ?>
      <div class="field">
        <label>Cakupan Dampak <span style="color:#b42318">*</span></label>
        <select name="impact" required>
          <option value="">— Pilih —</option>
          <?php foreach (fbImpacts() as $k=>$v): ?>
          <option value="<?= $k ?>" <?= ($_POST['impact']??'')===$k?'selected':'' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint">Menentukan prioritas penanganan. Semakin luas dampaknya, semakin tinggi prioritasnya.</div>
      </div>
      <?php endif; ?>

      <div class="field">
        <label>Subjek <span style="color:#b42318">*</span></label>
        <input type="text" name="subject" maxlength="120" required id="subj"
               placeholder="Ringkas dalam satu kalimat"
               value="<?= h($_POST['subject'] ?? '') ?>"
               oninput="document.getElementById('subj-c').textContent=this.value.length">
        <div class="char-count"><span id="subj-c"><?= mb_strlen($_POST['subject']??'') ?></span>/120</div>
      </div>

      <div class="field">
        <label>Pesan <span style="color:#b42318">*</span></label>
        <textarea name="message" rows="7" maxlength="4000" required id="msg"
          placeholder="<?= $track==='safeguarding' ? 'Tuliskan apa yang Anda lihat atau dengar secara faktual: apa, kapan, di mana, siapa yang terlibat. Hindari kesimpulan atau dugaan.' : 'Ceritakan secara spesifik. Semakin detail, semakin mudah ditindaklanjuti…' ?>"
          oninput="document.getElementById('msg-c').textContent=this.value.length"><?= h($_POST['message'] ?? '') ?></textarea>
        <div class="char-count"><span id="msg-c"><?= mb_strlen($_POST['message']??'') ?></span>/4000 karakter</div>
        <div class="hint">
          <i class="bi bi-info-circle me-1"></i>Setelah terkirim, isi laporan tidak dapat
          diubah. Lampiran berkas belum tersedia lewat jalur publik — kalau ada bukti yang
          perlu disertakan, sebutkan di pesan, dan penanggung jawab akan menghubungi Anda.
        </div>
      </div>

      <div class="btn-row">
        <button type="submit" class="btn-submit <?= $track==='safeguarding'?'sg':'' ?>">
          <i class="bi bi-send-fill"></i>Kirim Laporan
        </button>
      </div>
    </form>
  </div>
</div>

<div class="text-center">
  <a href="<?= APP_URL ?>/publik/lacak.php" class="small text-decoration-none">
    <i class="bi bi-search me-1"></i>Sudah pernah mengirim? Lacak laporan Anda
  </a>
</div>

<?php endif; ?>
</div>

<?php
$content = ob_get_clean();
publicWrapper('Sampaikan Feedback', $content);
