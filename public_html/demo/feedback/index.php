<?php
// AGKB 360° — Formulir Feedback & Apresiasi
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
$user = currentUser();

$track   = $_POST['track'] ?? $_GET['track'] ?? 'apresiasi';
$success = null;
$error   = '';

$allTracks = ['apresiasi','inquiry','safeguarding'];
if (!in_array($track, $allTracks, true)) $track = 'apresiasi';

$categories = Database::fetchAll(
    "SELECT * FROM feedback_categories WHERE is_active=1 ORDER BY order_num, name");
$catByTrack = [];
foreach ($categories as $c) $catByTrack[$c['track']][] = $c;

// Daftar orang untuk apresiasi
$people = Database::fetchAll(
    "SELECT id, name, role FROM users
     WHERE is_active=1 AND role IN ('leader','teacher','staff','mentor','admin','foundation')
     ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $catId   = (int)($_POST['category_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $impact  = $_POST['impact'] ?? null;
    $anon    = !empty($_POST['is_anonymous']);
    $apprec  = (int)($_POST['appreciated_user_id'] ?? 0);

    $cat = $catId ? Database::fetchOne("SELECT * FROM feedback_categories WHERE id=? AND is_active=1", [$catId]) : null;

    if (!$cat)                       $error = 'Pilih kategori terlebih dahulu.';
    elseif (mb_strlen($subject) < 5) $error = 'Subjek terlalu pendek (minimal 5 karakter).';
    elseif (mb_strlen($message) < 20)$error = 'Pesan terlalu pendek (minimal 20 karakter).';
    elseif ($cat['track'] === 'apresiasi' && $cat['code'] === 'apr_guru' && !$apprec)
                                     $error = 'Pilih siapa yang ingin Anda apresiasi.';
    elseif ($cat['track'] === 'inquiry' && !in_array($impact, array_keys(fbImpacts()), true))
                                     $error = 'Pilih cakupan dampaknya.';
    else {
        $track = $cat['track'];
        $id = fbCreateTicket([
            'category_id'         => $cat['id'],
            'track'               => $track,
            'sender_id'           => $user['id'],
            'is_anonymous'        => ($anon && $cat['allow_anonymous']) ? 1 : 0,
            'subject'             => $subject,
            'message'             => $message,
            'impact'              => $track === 'inquiry' ? $impact : null,
            'appreciated_user_id' => $track === 'apresiasi' ? ($apprec ?: null) : null,
        ]);

        // Lampiran
        $sealed = $track === 'safeguarding';
        if (!empty($_FILES['attachments']['name'][0])) {
            $n = min(count($_FILES['attachments']['name']), FB_MAX_FILES);
            for ($i = 0; $i < $n; $i++) {
                if (($_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                fbStoreUpload($id, [
                    'name'     => $_FILES['attachments']['name'][$i],
                    'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                    'size'     => $_FILES['attachments']['size'][$i],
                    'error'    => $_FILES['attachments']['error'][$i],
                ], null, $sealed);
            }
        }

        fbNotifyNew($id);
        if ($track === 'apresiasi' && $apprec) fbNotifyAppreciation($id);

        $success = fbLoadFull($id);
    }
}

$T = fbTracks();
ob_start(); ?>

<style>
.fb-wrap{max-width:760px;margin:0 auto}
.fb-card{background:#fff;border:1px solid #e3e5ea;border-radius:14px;overflow:hidden;margin-bottom:16px;box-shadow:var(--agkb-shadow-sm)}
.fb-hdr{padding:16px 22px;background:#040136;color:#fff;display:flex;align-items:center;gap:12px}
.fb-hdr-title{font-size:15px;font-weight:600}
.fb-hdr-sub{font-size:12px;opacity:.75;margin-top:2px}
.fb-body{padding:22px}
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
.field input[type=text],.field textarea,.field select{width:100%;border:1.5px solid #e3e5ea;border-radius:9px;padding:10px 13px;font-size:13.5px;color:#040136;font-family:inherit;outline:none;transition:border .15s,box-shadow .15s;background:#fff}
.field input:focus,.field textarea:focus,.field select:focus{border-color:#2201b2;box-shadow:0 0 0 3px rgba(34,1,178,.12)}
.field textarea{resize:vertical;line-height:1.7}
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
.anon-row{display:flex;gap:10px;align-items:flex-start;background:#f3f4f6;border-radius:9px;padding:12px 14px;margin-bottom:16px}
.anon-row input{margin-top:2px;flex-shrink:0}
.anon-label{font-size:13px;font-weight:600;color:#040136}
.anon-desc{font-size:11.5px;color:#6b6a83;margin-top:2px;line-height:1.55}
.btn-row{display:flex;gap:10px;justify-content:flex-end;margin-top:8px}
.btn-cancel{padding:10px 20px;border:1px solid #e3e5ea;border-radius:9px;background:transparent;font-size:13px;color:#6b6a83;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}
.btn-submit{padding:10px 24px;border:none;border-radius:9px;background:#040136;color:#fff;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:7px}
.btn-submit:hover{background:#030870}
.btn-submit.sg{background:#b42318}.btn-submit.sg:hover{background:#8c1610}
.success-box{text-align:center;padding:36px 24px}
.success-icon{font-size:46px;color:#027a48;margin-bottom:14px;display:block}
.success-title{font-size:19px;font-weight:700;color:#040136;margin-bottom:8px}
.success-sub{font-size:14px;color:#6b6a83;line-height:1.7;margin-bottom:18px}
.ticket-chip{display:inline-block;background:#040136;color:#fff;border-radius:8px;padding:8px 18px;font-size:14px;font-weight:700;letter-spacing:.04em;margin-bottom:18px}
.file-hint{font-size:11.5px;color:#6b6a83;margin-top:5px;line-height:1.5}
@media(max-width:640px){.track-row{grid-template-columns:1fr}.cat-grid{grid-template-columns:1fr}}
</style>

<div class="fb-wrap">

<?php if ($success): ?>
<div class="fb-card"><div class="fb-body"><div class="success-box">
  <i class="bi bi-check-circle-fill success-icon"></i>
  <div class="success-title">Laporan Anda tercatat</div>
  <div class="ticket-chip"><?= h($success['ticket_no']) ?></div>
  <div class="success-sub">
    <?php if ($success['track'] === 'apresiasi'): ?>
      Terima kasih. Apresiasi Anda sudah diteruskan.
    <?php elseif ($success['track'] === 'safeguarding'): ?>
      Laporan Anda diterima Yayasan dan akan ditindaklanjuti dalam <strong>1×24 jam</strong>.<br>
      Simpan nomor tiket di atas untuk memantau perkembangannya.
    <?php else: ?>
      Simpan nomor tiket di atas. Anda akan menerima email setiap ada perkembangan.<br>
      Target penyelesaian: <strong><?= $success['due_at'] ? date('d M Y, H:i', strtotime($success['due_at'])) : '—' ?></strong>
    <?php endif; ?>
  </div>
  <div class="d-flex gap-2 justify-content-center flex-wrap">
    <a href="<?= APP_URL ?>/feedback/my.php" class="btn btn-navy btn-sm px-3">
      <i class="bi bi-list-ul me-1"></i>Laporan Saya
    </a>
    <a href="<?= APP_URL ?>/feedback/" class="btn btn-outline-navy btn-sm px-3">
      <i class="bi bi-plus-lg me-1"></i>Kirim Lagi
    </a>
  </div>
</div></div></div>

<?php else: ?>

<div class="fb-card">
  <div class="fb-hdr">
    <i class="bi bi-chat-heart-fill" style="font-size:22px"></i>
    <div>
      <div class="fb-hdr-title">Feedback &amp; Apresiasi</div>
      <div class="fb-hdr-sub">Sampaikan apresiasi, kendala, atau laporan kapan saja sepanjang tahun</div>
    </div>
  </div>
  <div class="fb-body">

    <?php if ($error): ?>
    <div class="err-box"><i class="bi bi-exclamation-triangle-fill"></i><?= h($error) ?></div>
    <?php endif; ?>

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
      <span>Laporan ini <strong>hanya dapat dilihat oleh Yayasan</strong> — tidak oleh admin maupun pimpinan sekolah.
      Anda dilindungi: tidak boleh ada tindakan balasan apa pun terhadap pelapor yang beritikad baik.</span>
    </div>
    <?php else: ?>
    <div class="info-box">
      <i class="bi bi-info-circle-fill" style="flex-shrink:0;margin-top:1px"></i>
      <span>Setiap laporan mendapat nomor tiket dan penanggung jawab. Anda akan menerima email setiap ada perkembangan.</span>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="track" value="<?= h($track) ?>">

      <div class="field">
        <label>Kategori <span style="color:#b42318">*</span></label>
        <div class="cat-grid">
          <?php foreach (($catByTrack[$track] ?? []) as $c): ?>
          <label class="cat-opt <?= $track==='safeguarding'?'sg':'' ?>">
            <input type="radio" name="category_id" value="<?= $c['id'] ?>" required
                   data-code="<?= h($c['code']) ?>" data-anon="<?= (int)$c['allow_anonymous'] ?>"
                   <?= (int)($_POST['category_id']??0)===(int)$c['id']?'checked':'' ?>>
            <div class="cat-name"><?= h($c['name']) ?></div>
            <?php if ($c['description']): ?><div class="cat-desc"><?= h($c['description']) ?></div><?php endif; ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($track === 'apresiasi'): ?>
      <div class="field">
        <label>Ditujukan Kepada</label>
        <select name="appreciated_user_id">
          <option value="">— Tidak ditujukan ke orang tertentu —</option>
          <?php foreach ($people as $p): ?>
          <option value="<?= $p['id'] ?>" <?= (int)($_POST['appreciated_user_id']??0)===(int)$p['id']?'selected':'' ?>>
            <?= h($p['name']) ?> — <?= h(roleLabel($p['role'])) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div class="file-hint">Orang yang dipilih akan menerima email berisi apresiasi Anda.</div>
      </div>
      <?php endif; ?>

      <?php if ($track === 'inquiry'): ?>
      <div class="field">
        <label>Cakupan Dampak <span style="color:#b42318">*</span></label>
        <select name="impact" required>
          <option value="">— Pilih —</option>
          <?php foreach (fbImpacts() as $k=>$v): ?>
          <option value="<?= $k ?>" <?= ($_POST['impact']??'')===$k?'selected':'' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="file-hint">Menentukan prioritas penanganan. Semakin luas dampaknya, semakin tinggi prioritasnya.</div>
      </div>
      <?php endif; ?>

      <?php if ($track === 'safeguarding'): ?>
      <div class="anon-row">
        <input type="checkbox" name="is_anonymous" id="anon" value="1" <?= !empty($_POST['is_anonymous'])?'checked':'' ?>>
        <label for="anon" style="cursor:pointer;margin:0">
          <div class="anon-label">Kirim tanpa menampilkan nama saya</div>
          <div class="anon-desc">Identitas Anda tidak akan terlihat oleh penanganan tiket. Data tetap tersimpan terenkripsi di sistem dan hanya dapat dibuka oleh superadmin bila diperlukan untuk mencegah penyalahgunaan — setiap pembukaan tercatat.</div>
        </label>
      </div>
      <?php endif; ?>

      <div class="field">
        <label>Subjek <span style="color:#b42318">*</span></label>
        <input type="text" name="subject" maxlength="120" required id="subj"
          placeholder="<?= $track==='apresiasi' ? 'Contoh: Dukungan luar biasa dalam program IB bulan ini' : 'Ringkas dalam satu kalimat' ?>"
          value="<?= h($_POST['subject'] ?? '') ?>"
          oninput="document.getElementById('subj-c').textContent=this.value.length">
        <div class="char-count"><span id="subj-c"><?= mb_strlen($_POST['subject']??'') ?></span>/120</div>
      </div>

      <div class="field">
        <label>Pesan <span style="color:#b42318">*</span></label>
        <textarea name="message" rows="7" maxlength="4000" required id="msg"
          placeholder="<?= $track==='safeguarding' ? 'Tuliskan apa yang Anda lihat atau dengar secara faktual: apa, kapan, di mana, siapa yang terlibat. Hindari kesimpulan atau dugaan.' : 'Ceritakan secara spesifik. Semakin detail, semakin mudah ditindaklanjuti...' ?>"
          oninput="document.getElementById('msg-c').textContent=this.value.length"><?= h($_POST['message'] ?? '') ?></textarea>
        <div class="char-count"><span id="msg-c"><?= mb_strlen($_POST['message']??'') ?></span>/4000 karakter</div>
        <?php if ($track === 'safeguarding'): ?>
        <div class="file-hint"><i class="bi bi-info-circle me-1"></i>Setelah terkirim, isi laporan tidak dapat diubah atau dihapus oleh siapa pun. Ini untuk menjaga keutuhan catatan.</div>
        <?php endif; ?>
      </div>

      <div class="field">
        <label>Lampiran <span style="font-weight:400;text-transform:none;letter-spacing:0">(opsional)</span></label>
        <input type="file" name="attachments[]" multiple class="form-control form-control-sm"
               accept=".jpg,.jpeg,.png,.webp,.pdf,.docx,.xlsx,.mp3,.m4a,.mp4">
        <div class="file-hint">
          Maksimal <?= FB_MAX_FILES ?> berkas, 10 MB per berkas. Format: gambar, PDF, Word, Excel, audio, video.
          <?php if ($track === 'safeguarding'): ?><br><strong>Lampiran bukti disegel</strong> — tidak dapat dihapus, dan setiap pengunduhan tercatat.<?php endif; ?>
        </div>
      </div>

      <div class="btn-row">
        <a href="<?= APP_URL ?>/dashboard/" class="btn-cancel"><i class="bi bi-x me-1"></i>Batal</a>
        <button type="submit" class="btn-submit <?= $track==='safeguarding'?'sg':'' ?>">
          <i class="bi bi-send-fill"></i>Kirim
        </button>
      </div>
    </form>
  </div>
</div>

<div class="text-center">
  <a href="<?= APP_URL ?>/feedback/my.php" class="small text-decoration-none">
    <i class="bi bi-list-ul me-1"></i>Lihat laporan yang pernah saya kirim
  </a>
</div>

<?php endif; ?>
</div>

<?php
$content = ob_get_clean();
pageWrapper('Feedback & Apresiasi', $content);
