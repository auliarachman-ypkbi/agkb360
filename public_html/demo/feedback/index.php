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

        $scriptUrl = defined('APPS_SCRIPT_URL') ? APPS_SCRIPT_URL : '';
        if ($scriptUrl) {
            $typeLabel       = $type === 'appreciation' ? 'Apresiasi' : 'Perhatian / Masukan';
            $typeBadgeColor  = $type === 'appreciation' ? '#27500A' : '#633806';
            $typeBadgeBg     = $type === 'appreciation' ? '#EAF3DE' : '#FAEEDA';
            $typeBadgeBorder = $type === 'appreciation' ? '#3B6D11' : '#854F0B';
            $senderInitial   = strtoupper(substr($user['name'], 0, 1));
            $senderRole      = roleLabel($user['role']);
            $fullReplyUrl    = 'https://agkb360.app/demo/admin/feedback.php?id=' . $fbId;
            $msgHtml         = nl2br(htmlspecialchars($message));

            $htmlBody = '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"></head>'
            . '<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px"><tr><td align="center">'
            . '<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0">'
            . '<tr><td style="background:#2C5282;padding:28px 32px;text-align:center">'
            . '<div style="font-size:28px;font-weight:700;color:#ffffff">AKGB <span style="color:#ffc901">360&deg;</span></div>'
            . '<div style="font-size:11px;color:rgba(255,255,255,0.7);margin-top:4px;letter-spacing:1px;text-transform:uppercase">Platform Evaluasi Kinerja</div>'
            . '</td></tr>'
            . '<tr><td style="padding:24px 32px 0">'
            . '<span style="background:' . $typeBadgeBg . ';color:' . $typeBadgeColor . ';border:1px solid ' . $typeBadgeBorder . ';border-radius:20px;padding:5px 14px;font-size:12px;font-weight:600">' . $typeLabel . '</span>'
            . '</td></tr>'
            . '<tr><td style="padding:12px 32px 0"><h2 style="margin:0;font-size:18px;font-weight:700;color:#1e293b">' . htmlspecialchars($subject) . '</h2></td></tr>'
            . '<tr><td style="padding:12px 32px 0"><table cellpadding="0" cellspacing="0"><tr>'
            . '<td style="width:36px;height:36px;background:#E6F1FB;border-radius:50%;text-align:center;vertical-align:middle;font-size:14px;font-weight:700;color:#185FA5">' . $senderInitial . '</td>'
            . '<td style="padding-left:10px"><div style="font-size:13px;font-weight:600;color:#1e293b">' . htmlspecialchars($user['name']) . '</div>'
            . '<div style="font-size:12px;color:#64748b">' . htmlspecialchars($user['email']) . ' &middot; ' . $senderRole . '</div></td>'
            . '</tr></table></td></tr>'
            . '<tr><td style="padding:16px 32px 0"><div style="border-top:1px solid #f1f5f9"></div></td></tr>'
            . '<tr><td style="padding:16px 32px 0">'
            . '<div style="font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Pesan</div>'
            . '<div style="font-size:14px;color:#334155;line-height:1.8;background:#f8fafc;border-radius:8px;padding:16px;border-left:3px solid #2C5282">' . $msgHtml . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:24px 32px;text-align:center">'
            . '<a href="' . $fullReplyUrl . '" style="display:inline-block;background:#2C5282;color:#ffffff;text-decoration:none;padding:13px 28px;border-radius:8px;font-size:14px;font-weight:700">Balas Feedback Ini &rarr;</a>'
            . '<div style="margin-top:10px;font-size:11px;color:#94a3b8">Atau salin: <a href="' . $fullReplyUrl . '" style="color:#185FA5">' . $fullReplyUrl . '</a></div>'
            . '</td></tr>'
            . '<tr><td style="background:#f8fafc;padding:14px 32px;border-top:1px solid #e2e8f0;text-align:center">'
            . '<div style="font-size:11px;color:#94a3b8">Email otomatis dari AKGB 360&deg; &bull; Yayasan Kader Bangsa Indonesia</div>'
            . '</td></tr></table></td></tr></table></body></html>';

            $payload = json_encode([
                'to'       => 'edu@kaderbangsa.foundation',
                'subject'  => '[AKGB 360°] ' . $typeLabel . ' dari ' . $user['name'],
                'htmlBody' => $htmlBody,
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