<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
$user = currentUser();

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type    = $_POST['type']    ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!in_array($type, ['appreciation','concern'])) {
        $error = 'Pilih jenis feedback terlebih dahulu.';
    } elseif (strlen($subject) < 5) {
        $error = 'Subjek terlalu pendek (minimal 5 karakter).';
    } elseif (strlen($message) < 20) {
        $error = 'Pesan terlalu pendek (minimal 20 karakter).';
    } else {
        $fbId = Database::insert('feedback', [
            'sender_id' => $user['id'],
            'type'      => $type,
            'subject'   => $subject,
            'message'   => $message,
            'status'    => 'new',
        ]);

        // Kirim email via Apps Script
        $scriptUrl = defined('APPS_SCRIPT_URL') ? APPS_SCRIPT_URL : '';
        if ($scriptUrl) {
            $typeLabel = $type === 'appreciation' ? 'Apresiasi' : 'Perhatian / Masukan';
            $replyUrl  = APP_URL . '/admin/feedback.php?id=' . $fbId;
            $payload = json_encode([
                'to'      => 'edu@kaderbangsa.foundation',
                'subject' => '[AKGB 360°] ' . $typeLabel . ' dari ' . $user['name'],
                'body'    => "Dari: {$user['name']} ({$user['email']})\nJenis: {$typeLabel}\nSubjek: {$subject}\n\nPesan:\n{$message}\n\n---\nBalas melalui platform:\n{$replyUrl}",
            ]);
            @file_get_contents($scriptUrl, false, stream_context_create([
                'http' => ['method'=>'POST','header'=>'Content-Type: application/json','content'=>$payload]
            ]));
        }

        $success = true;
    }
}

ob_start(); ?>

<style>
.fb-wrap{max-width:680px;margin:0 auto}
.fb-card{background:#fff;border:0.5px solid #e2e8f0;border-radius:14px;overflow:hidden;margin-bottom:16px}
.fb-hdr{padding:16px 22px;background:#2C5282;color:white;display:flex;align-items:center;gap:12px}
.fb-hdr-icon{font-size:22px}
.fb-hdr-title{font-size:15px;font-weight:600}
.fb-hdr-sub{font-size:12px;opacity:.8;margin-top:2px}
.fb-body{padding:22px}
.type-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px}
.type-opt{border:1.5px solid #e2e8f0;border-radius:10px;padding:16px;cursor:pointer;transition:all .15s;position:relative}
.type-opt input[type=radio]{position:absolute;opacity:0;width:0;height:0}
.type-opt:has(input:checked).appr{border-color:#3B6D11;background:#EAF3DE}
.type-opt:has(input:checked).conc{border-color:#854F0B;background:#FAEEDA}
.type-opt:hover{border-color:#2C5282}
.type-icon{font-size:22px;margin-bottom:8px;display:block}
.type-label{font-size:13px;font-weight:600;color:#1e293b}
.type-desc{font-size:12px;color:#64748b;margin-top:4px;line-height:1.5}
.field{margin-bottom:16px}
.field label{display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.field input,.field textarea{width:100%;border:1px solid #e2e8f0;border-radius:8px;padding:10px 13px;font-size:13px;color:#1e293b;font-family:inherit;outline:none;transition:border .15s}
.field input:focus,.field textarea:focus{border-color:#2C5282;box-shadow:0 0 0 3px rgba(44,82,130,.1)}
.field textarea{resize:vertical;line-height:1.7}
.char-count{font-size:11px;color:#94a3b8;text-align:right;margin-top:4px}
.info-box{background:#E6F1FB;border:0.5px solid #B5D4F4;border-radius:8px;padding:11px 14px;font-size:12px;color:#0C447C;margin-bottom:20px;display:flex;gap:8px;align-items:flex-start;line-height:1.6}
.btn-row{display:flex;gap:10px;justify-content:flex-end;margin-top:4px}
.btn-cancel{padding:10px 20px;border:1px solid #e2e8f0;border-radius:8px;background:transparent;font-size:13px;color:#64748b;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}
.btn-submit{padding:10px 24px;border:none;border-radius:8px;background:#2C5282;color:white;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.btn-submit:hover{background:#1A365D}
.success-box{text-align:center;padding:40px 20px}
.success-icon{font-size:48px;color:#3B6D11;margin-bottom:16px;display:block}
.success-title{font-size:18px;font-weight:600;color:#1e293b;margin-bottom:8px}
.success-sub{font-size:14px;color:#64748b;line-height:1.6;margin-bottom:24px}
.btn-back{display:inline-block;padding:10px 24px;background:#2C5282;color:white;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600}
.err-box{background:#FCEBEB;border:0.5px solid #F09595;border-radius:8px;padding:11px 14px;font-size:13px;color:#791F1F;margin-bottom:16px;display:flex;gap:8px;align-items:center}
</style>

<div class="fb-wrap">

<?php if ($success): ?>
<div class="fb-card">
  <div class="fb-body">
    <div class="success-box">
      <i class="bi bi-check-circle-fill success-icon"></i>
      <div class="success-title">Feedback berhasil dikirim!</div>
      <div class="success-sub">
        Terima kasih telah menyampaikan feedback Anda.<br>
        Tim Yayasan Kader Bangsa akan meninjau dan menindaklanjutinya.
      </div>
      <a href="<?= APP_URL ?>/dashboard/" class="btn-back">
        <i class="bi bi-house me-2"></i>Kembali ke Dashboard
      </a>
    </div>
  </div>
</div>

<?php else: ?>

<div class="fb-card">
  <div class="fb-hdr">
    <i class="bi bi-chat-heart-fill fb-hdr-icon"></i>
    <div>
      <div class="fb-hdr-title">Feedback & Apresiasi</div>
      <div class="fb-hdr-sub">Sampaikan apresiasi atau masukan konstruktif kapan saja sepanjang tahun</div>
    </div>
  </div>
  <div class="fb-body">

    <?php if ($error): ?>
    <div class="err-box">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <?= h($error) ?>
    </div>
    <?php endif; ?>

    <div class="info-box">
      <i class="bi bi-info-circle-fill" style="flex-shrink:0;margin-top:1px"></i>
      <span>Feedback Anda akan diterima oleh Tim Yayasan Kader Bangsa dan ditindaklanjuti secara langsung. Identitas pengirim terlihat oleh admin.</span>
    </div>

    <form method="POST">

      <div class="field">
        <label>Jenis Feedback</label>
        <div class="type-row">
          <label class="type-opt appr">
            <input type="radio" name="type" value="appreciation" <?= ($_POST['type']??'')==='appreciation'?'checked':'' ?> required>
            <i class="bi bi-star-fill type-icon" style="color:#3B6D11"></i>
            <div class="type-label">Apresiasi</div>
            <div class="type-desc">Akui kontribusi positif atau hal yang berjalan baik</div>
          </label>
          <label class="type-opt conc">
            <input type="radio" name="type" value="concern" <?= ($_POST['type']??'')==='concern'?'checked':'' ?>>
            <i class="bi bi-exclamation-triangle type-icon" style="color:#854F0B"></i>
            <div class="type-label">Perhatian / Masukan</div>
            <div class="type-desc">Sampaikan kekhawatiran atau saran konstruktif</div>
          </label>
        </div>
      </div>

      <div class="field">
        <label>Subjek <span style="color:#dc3545">*</span></label>
        <input type="text" name="subject" maxlength="80"
          placeholder="Contoh: Dukungan luar biasa dalam program IB bulan ini"
          value="<?= h($_POST['subject'] ?? '') ?>"
          id="subj" oninput="document.getElementById('subj-c').textContent=this.value.length">
        <div class="char-count"><span id="subj-c"><?= strlen($_POST['subject']??'') ?></span>/80</div>
      </div>

      <div class="field">
        <label>Pesan <span style="color:#dc3545">*</span></label>
        <textarea name="message" rows="6" maxlength="1000"
          placeholder="Ceritakan secara spesifik apa yang ingin Anda sampaikan. Semakin detail, semakin mudah ditindaklanjuti..."
          id="msg" oninput="document.getElementById('msg-c').textContent=this.value.length"><?= h($_POST['message'] ?? '') ?></textarea>
        <div class="char-count"><span id="msg-c"><?= strlen($_POST['message']??'') ?></span>/1000 karakter</div>
      </div>

      <div class="btn-row">
        <a href="<?= APP_URL ?>/dashboard/" class="btn-cancel">
          <i class="bi bi-x me-1"></i>Batal
        </a>
        <button type="submit" class="btn-submit">
          <i class="bi bi-send-fill"></i>Kirim Feedback
        </button>
      </div>

    </form>
  </div>
</div>

<?php endif; ?>
</div>

<?php
$content = ob_get_clean();
pageWrapper('Feedback & Apresiasi', $content);