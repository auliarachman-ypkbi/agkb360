<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin','admin']);
$me = currentUser();

$baseUrl     = 'https://agkb360.app/app';
$scriptUrl   = defined('APPS_SCRIPT_URL') ? APPS_SCRIPT_URL : '';
$defaultSubject = '[AGKB 360°] Introducing Our Performance Review Platform';

$defaultBody = 'Dear {{name}},

I hope you are doing well.

As part of our commitment to transparency, accountability, continuous improvement, and responsible growth, we are introducing a feedback and appreciation system that allows every member of our community to contribute to making our organization stronger. Through this platform, we hope to build a culture where everyone is encouraged to give their best, where positive contributions are recognized, and where constructive feedback can be heard and acted upon appropriately.

As we reach the end of the academic year, we would like to invite you to participate in this initiative through the platform that has been prepared for all stakeholders across the foundation.

How it works:

1. Log in to the platform
Please log in at {{url}} using the email address to which this message has been sent ({{email}}). Click the link below to set your password and access the platform directly.

2. Semester Feedback Package
The first section contains the feedback package that should be completed at the end of each semester. You will be asked to respond to a series of statements using a Likert scale from 1 to 4.

3. Continuous Feedback & Appreciation
The second section allows you to provide feedback or appreciation at any time throughout the year. Whether you wish to acknowledge a positive contribution or raise a constructive concern, this channel is available for your voice to be heard and for appropriate follow-up to take place.

Please note that all submissions will be attributed to the individual providing them. Access to the submissions will be limited to authorized personnel within the Foundation Executive Committee and, when relevant, designated school leaders who are responsible for reviewing and responding to the matter. Information will be shared strictly on a need-to-know basis to ensure appropriate follow-up and resolution.

Our intention is to create a culture of trust, not fear. We encourage everyone to share their observations, concerns, and appreciation openly and professionally. Input provided in good faith will be respected and considered carefully, and contributors should feel confident that the purpose of this system is organizational improvement, not personal judgment.

Thank you for your contribution and commitment to the AGKB community. We look forward to continuing to build an environment where everyone can grow, contribute positively, and find meaning in the work we do together.

Warm regards,
Dewi Amri
Chief Education Officer
Yayasan Kader Bangsa

---
P.S: As this platform is still in active development, please do not hesitate to report any errors or issues you encounter. Your feedback helps us improve.';

// ── Daftar kategori dinamis "Lainnya" (role di luar leader/teacher/student/parent) ──
$otherRoleOptions = Database::fetchAll("
    SELECT role, COUNT(*) as cnt
    FROM users
    WHERE role NOT IN ('superadmin','admin','tester','leader','teacher','student','parent')
    AND is_active = 1
    GROUP BY role
    ORDER BY role
");
$otherRoleKeys = array_column($otherRoleOptions, 'role');

// ── Helper label tampilan untuk tipe blast (termasuk 'osis' yang bukan role asli) ──
function blastTypeLabel(string $type): string {
    if ($type === 'osis') return 'OSIS';
    return roleLabel($type);
}

// ── Helper ambil daftar penerima sesuai tipe blast ──────────────
function getBlastRecipients(string $blastType): array {
    if ($blastType === 'osis') {
        return Database::fetchAll("
            SELECT u.id, u.name, u.email
            FROM users u
            JOIN user_groups ug ON ug.user_id = u.id
            JOIN `groups` g ON g.id = ug.group_id
            WHERE g.respondent_type = 'siswa' AND g.is_fixed = 1 AND u.is_active = 1
            ORDER BY u.name
        ");
    }
    return Database::fetchAll(
        "SELECT id, name, email FROM users WHERE role=? AND is_active=1 ORDER BY name",
        [$blastType]
    );
}

// ── HANDLE BLAST ─────────────────────────────────────────────
$blastResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['blast_type'])) {
    $blastType = $_POST['blast_type'];
    $subject   = trim($_POST['subject'] ?? $defaultSubject);
    $bodyTpl   = trim($_POST['body'] ?? $defaultBody);

    $allowedTypes = array_merge(['leader','teacher','student','parent','osis'], $otherRoleKeys);
    if (!in_array($blastType, $allowedTypes)) {
        flash('Tipe blast tidak valid.', 'danger');
        header('Location: ' . APP_URL . '/admin/blast_email.php');
        exit;
    }

    // Ambil penerima
    $recipients = getBlastRecipients($blastType);

    $sent = 0; $failed = 0;
    foreach ($recipients as $r) {
        // Generate token unik
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        Database::query(
            "UPDATE users SET password_reset_token=?, token_expires_at=? WHERE id=?",
            [$token, $expires, $r['id']]
        );

        $setPasswordUrl = $baseUrl . '/auth/set-password.php?token=' . $token;

        // Personalisasi body
        $body = str_replace(
            ['{{name}}', '{{email}}', '{{url}}', '{{set_password_url}}'],
            [$r['name'], $r['email'], $baseUrl, $setPasswordUrl],
            $bodyTpl
        );

        // HTML email
        $bodyHtml = nl2br(htmlspecialchars($body));
        $htmlEmail = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
            . '<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px"><tr><td align="center">'
            . '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0">'
            . '<tr><td style="background:#2C5282;padding:28px 32px;text-align:center">'
            . '<div style="font-size:28px;font-weight:700;color:#ffffff">AGKB <span style="color:#ffc901">360&deg;</span></div>'
            . '<div style="font-size:11px;color:rgba(255,255,255,0.7);margin-top:4px;letter-spacing:1px;text-transform:uppercase">Performance Review Platform</div>'
            . '</td></tr>'
            . '<tr><td style="padding:28px 32px">'
            . '<div style="font-size:14px;color:#334155;line-height:1.8">' . $bodyHtml . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:0 32px 28px;text-align:center">'
            . '<a href="' . $setPasswordUrl . '" style="display:inline-block;background:#2C5282;color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:700">Set Password &amp; Access Platform &rarr;</a>'
            . '<div style="margin-top:10px;font-size:11px;color:#94a3b8">Or copy: <a href="' . $setPasswordUrl . '" style="color:#185FA5">' . $setPasswordUrl . '</a></div>'
            . '</td></tr>'
            . '<tr><td style="background:#f8fafc;padding:14px 32px;border-top:1px solid #e2e8f0;text-align:center">'
            . '<div style="font-size:11px;color:#94a3b8">AGKB 360&deg; &bull; Yayasan Pendidikan Kader Bangsa Indonesia &bull; ' . date('Y') . '</div>'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';

        // Kirim via Apps Script
        $ok = false;
        if ($scriptUrl) {
            $payload = json_encode([
                'to'       => $r['email'],
                'subject'  => $subject,
                'htmlBody' => $htmlEmail,
            ]);
            $ctx = stream_context_create(['http'=>['method'=>'POST','header'=>'Content-Type: application/json','content'=>$payload,'timeout'=>10]]);
            $res = @file_get_contents($scriptUrl, false, $ctx);
            $ok  = $res !== false;
        }

        // Log
        Database::insert('email_blast_log', [
            'blast_type'      => $blastType,
            'recipient_id'    => $r['id'],
            'recipient_email' => $r['email'],
            'subject'         => $subject,
            'status'          => $ok ? 'sent' : 'failed',
            'sent_by'         => $me['id'],
        ]);

        $ok ? $sent++ : $failed++;
    }

    $blastResult = ['sent'=>$sent,'failed'=>$failed,'type'=>$blastType];
}

// ── HANDLE TEST BLAST ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_blast'])) {
    $subject = trim($_POST['subject'] ?? $defaultSubject);
    $bodyTpl = trim($_POST['body'] ?? $defaultBody);
    $testEmail = 'edu@kaderbangsa.foundation';

    // Dummy user untuk preview
    $dummyName  = 'Test User';
    $dummyEmail = $testEmail;
    $testToken  = 'test-token-preview';
    $setPasswordUrl = $baseUrl . '/auth/set-password.php?token=' . $testToken;

    $body = str_replace(
        ['{{name}}', '{{email}}', '{{url}}', '{{set_password_url}}'],
        [$dummyName, $dummyEmail, $baseUrl, $setPasswordUrl],
        $bodyTpl
    );

    $bodyHtml = nl2br(htmlspecialchars($body));
    $htmlEmail = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
        . '<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px"><tr><td align="center">'
        . '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0">'
        . '<tr><td style="background:#2C5282;padding:28px 32px;text-align:center">'
        . '<div style="font-size:28px;font-weight:700;color:#ffffff">AGKB <span style="color:#ffc901">360&deg;</span></div>'
        . '<div style="font-size:11px;color:rgba(255,255,255,0.7);margin-top:4px;letter-spacing:1px;text-transform:uppercase">Performance Review Platform</div>'
        . '</td></tr>'
        . '<tr><td style="padding:28px 32px">'
        . '<div style="font-size:14px;color:#334155;line-height:1.8">' . $bodyHtml . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:0 32px 28px;text-align:center">'
        . '<a href="' . $setPasswordUrl . '" style="display:inline-block;background:#2C5282;color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:700">Set Password &amp; Access Platform &rarr;</a>'
        . '<div style="margin-top:10px;font-size:11px;color:#94a3b8">Or copy: <a href="' . $setPasswordUrl . '" style="color:#185FA5">' . $setPasswordUrl . '</a></div>'
        . '</td></tr>'
        . '<tr><td style="background:#f8fafc;padding:14px 32px;border-top:1px solid #e2e8f0;text-align:center">'
        . '<div style="font-size:11px;color:#94a3b8">AGKB 360&deg; &bull; Yayasan Pendidikan Kader Bangsa Indonesia &bull; ' . date('Y') . '</div>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';

    $ok = false;
    if ($scriptUrl) {
        $payload = json_encode([
            'to'       => $testEmail,
            'subject'  => '[TEST] ' . $subject,
            'htmlBody' => $htmlEmail,
        ]);
        $ctx = stream_context_create(['http'=>['method'=>'POST','header'=>'Content-Type: application/json','content'=>$payload,'timeout'=>10]]);
        $res = @file_get_contents($scriptUrl, false, $ctx);
        $ok  = $res !== false;
    }

    $blastResult = ['sent'=>$ok?1:0,'failed'=>$ok?0:1,'type'=>'test','test'=>true];
}

// ── DATA ─────────────────────────────────────────────────────
$counts = [
    'leader'  => Database::fetchOne("SELECT COUNT(*) c FROM users WHERE role='leader' AND is_active=1")['c'],
    'teacher' => Database::fetchOne("SELECT COUNT(*) c FROM users WHERE role='teacher' AND is_active=1")['c'],
    'student' => Database::fetchOne("SELECT COUNT(*) c FROM users WHERE role='student' AND is_active=1")['c'],
    'parent'  => Database::fetchOne("SELECT COUNT(*) c FROM users WHERE role='parent' AND is_active=1")['c'],
    'osis'    => Database::fetchOne("
        SELECT COUNT(*) c FROM users u
        JOIN user_groups ug ON ug.user_id=u.id
        JOIN `groups` g ON g.id=ug.group_id
        WHERE g.respondent_type='siswa' AND g.is_fixed=1 AND u.is_active=1
    ")['c'],
];

// ── LOG dengan PAGINATION (10/halaman, terbaru di atas) ─────────
$logPage    = max(1, (int)($_GET['log_page'] ?? 1));
$logPerPage = 10;
$logTotal   = Database::fetchOne("SELECT COUNT(*) c FROM email_blast_log")['c'];
$logPagi    = paginate($logTotal, $logPerPage, $logPage);
$logs = Database::fetchAll("
    SELECT l.*, u.name as recipient_name, s.name as sender_name
    FROM email_blast_log l
    JOIN users u ON u.id = l.recipient_id
    JOIN users s ON s.id = l.sent_by
    ORDER BY l.sent_at DESC, l.id DESC
    LIMIT {$logPerPage} OFFSET {$logPagi['offset']}
");

// ── Rangkuman log: total + breakdown per kategori blast ─────────
$blastSummary = Database::fetchAll("
    SELECT blast_type,
           SUM(CASE WHEN status='sent'   THEN 1 ELSE 0 END) as sent_count,
           SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed_count,
           COUNT(*) as total_count
    FROM email_blast_log
    GROUP BY blast_type
    ORDER BY total_count DESC
");
$totalSentAll   = Database::fetchOne("SELECT COUNT(*) c FROM email_blast_log WHERE status='sent'")['c'];
$totalFailedAll = Database::fetchOne("SELECT COUNT(*) c FROM email_blast_log WHERE status='failed'")['c'];

ob_start(); ?>

<style>
.blast-card{background:#fff;border:0.5px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:16px}
.blast-hdr{padding:12px 20px;font-size:13px;font-weight:600;color:#1e293b;border-bottom:0.5px solid #e2e8f0;background:#f8fafc;display:flex;align-items:center;gap:8px}
.blast-body{padding:20px}
.type-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px}
.type-card{border:1.5px solid #e2e8f0;border-radius:10px;padding:16px;text-align:center}
.type-count{font-size:28px;font-weight:600;color:#2C5282}
.type-label{font-size:12px;color:#64748b;margin:4px 0 12px}
.btn-blast{width:100%;padding:9px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;color:white;transition:opacity .15s}
.btn-blast:hover{opacity:.85}
.btn-blast:disabled{opacity:.4;cursor:not-allowed}
.btn-blast.leader{background:#2C5282}
.btn-blast.teacher{background:#854F0B}
.btn-blast.student{background:#27500A}
.btn-blast.parent{background:#7C3AED}
.btn-blast.osis{background:#C2410C}
.btn-blast.other{background:#533AB7;width:auto;padding:0 18px;flex-shrink:0}
.other-blast-card{border:1.5px dashed #cbd5e1;border-radius:10px;padding:14px 16px;margin-bottom:20px;background:#fafbfc}
.other-blast-lbl{font-size:12px;font-weight:600;color:#64748b;margin-bottom:10px}
.other-blast-row{display:flex;gap:10px;align-items:stretch}
.other-blast-row select{flex:1;height:38px;border:1px solid #e2e8f0;border-radius:8px;padding:0 12px;font-size:13px;color:#1e293b;outline:none;background:#fff}
.other-blast-row select:focus{border-color:#2C5282;box-shadow:0 0 0 3px rgba(44,82,130,.1)}
.other-blast-row .btn-blast{height:38px;display:inline-flex;align-items:center}
.field{margin-bottom:14px}
.field label{display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.field input,.field textarea{width:100%;border:1px solid #e2e8f0;border-radius:8px;padding:9px 12px;font-size:13px;font-family:inherit;outline:none}
.field input:focus,.field textarea:focus{border-color:#2C5282;box-shadow:0 0 0 3px rgba(44,82,130,.1)}
.result-box{border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;display:flex;align-items:center;gap:8px}
.result-ok{background:#EAF3DE;border:0.5px solid #3B6D11;color:#27500A}
.result-warn{background:#FAEEDA;border:0.5px solid #854F0B;color:#633806}
.log-table{width:100%;font-size:12px;border-collapse:collapse}
.log-table th{background:#f8fafc;padding:8px 12px;text-align:left;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0}
.log-table td{padding:8px 12px;border-bottom:0.5px solid #f1f5f9;color:#334155}
.badge-sent{background:#EAF3DE;color:#27500A;border:0.5px solid #3B6D11;font-size:10px;padding:2px 8px;border-radius:20px}
.badge-failed{background:#FCEBEB;color:#791F1F;border:0.5px solid #F09595;font-size:10px;padding:2px 8px;border-radius:20px}
.badge-type-generic{display:inline-block;background:#E6F1FB;color:#0C447C;border:0.5px solid #B5D4F4;font-size:10px;font-weight:600;padding:2px 9px;border-radius:20px}
.log-summary{display:flex;gap:16px;align-items:stretch;padding:16px 20px;border-bottom:0.5px solid #e2e8f0;background:#fafbfc;flex-wrap:wrap}
.summary-total{flex-shrink:0;padding-right:16px;border-right:1px solid #e2e8f0;text-align:center;min-width:100px}
.summary-total-val{font-size:26px;font-weight:700;color:#2C5282;line-height:1.1}
.summary-total-lbl{font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.4px;margin-top:2px}
.summary-total-fail{font-size:10px;color:#dc2626;margin-top:3px}
.summary-breakdown{display:flex;gap:8px;flex-wrap:wrap;align-items:center;flex:1}
.summary-chip{background:#fff;border:0.5px solid #e2e8f0;border-radius:8px;padding:6px 12px;display:flex;align-items:center;gap:6px;font-size:12px}
.summary-chip-lbl{color:#64748b;font-weight:500}
.summary-chip-val{color:#1e293b;font-weight:700}
.summary-chip-fail{color:#dc2626;font-size:10px}
.log-pager{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-top:0.5px solid #e2e8f0;background:#fafbfc}
.log-pager-info{font-size:11px;color:#64748b}
.log-pager-btns{display:flex;gap:8px}
.log-pager-btn{padding:5px 12px;border-radius:7px;border:1px solid #e2e8f0;background:#fff;color:#475569;text-decoration:none;font-size:11px;display:inline-flex;align-items:center;gap:4px}
.log-pager-btn.disabled{opacity:.4;pointer-events:none}
</style>

<?php if ($blastResult): ?>
<div class="result-box <?= $blastResult['failed']===0?'result-ok':'result-warn' ?>">
  <i class="bi bi-<?= $blastResult['failed']===0?'check-circle-fill':'exclamation-triangle-fill' ?>"></i>
  <?php if (!empty($blastResult['test'])): ?>
    Test email <?= $blastResult['sent']>0?'berhasil dikirim ke <strong>edu@kaderbangsa.foundation</strong>':'gagal dikirim' ?>.
  <?php else: ?>
    Blast ke <strong><?= h(blastTypeLabel($blastResult['type'])) ?></strong> selesai —
    <strong><?= $blastResult['sent'] ?> terkirim</strong>
    <?= $blastResult['failed']>0 ? ', <strong>'.$blastResult['failed'].' gagal</strong>' : '' ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="blast-card">
  <div class="blast-hdr"><i class="bi bi-send-fill"></i>Blast Email</div>
  <div class="blast-body">

    <!-- Stat cards -->
    <div class="type-cards">
      <div class="type-card">
        <div class="type-count"><?= $counts['leader'] ?></div>
        <div class="type-label">Pimpinan</div>
        <form method="POST">
          <input type="hidden" name="blast_type" value="leader">
          <input type="hidden" name="subject" id="s_leader">
          <input type="hidden" name="body" id="b_leader">
          <button type="submit" class="btn-blast leader"
            onclick="syncFields('leader')"
            onsubmit="return confirm('Kirim ke <?= $counts['leader'] ?> Pimpinan?')">
            <i class="bi bi-send me-1"></i>Blast ke Pimpinan
          </button>
        </form>
      </div>
      <div class="type-card">
        <div class="type-count"><?= $counts['teacher'] ?></div>
        <div class="type-label">Guru</div>
        <form method="POST">
          <input type="hidden" name="blast_type" value="teacher">
          <input type="hidden" name="subject" id="s_teacher">
          <input type="hidden" name="body" id="b_teacher">
          <button type="submit" class="btn-blast teacher"
            onclick="syncFields('teacher')"
            onsubmit="return confirm('Kirim ke <?= $counts['teacher'] ?> Guru?')">
            <i class="bi bi-send me-1"></i>Blast ke Guru
          </button>
        </form>
      </div>
      <div class="type-card">
        <div class="type-count"><?= $counts['student'] ?></div>
        <div class="type-label">Murid</div>
        <form method="POST">
          <input type="hidden" name="blast_type" value="student">
          <input type="hidden" name="subject" id="s_student">
          <input type="hidden" name="body" id="b_student">
          <button type="submit" class="btn-blast student"
            onclick="syncFields('student')"
            onsubmit="return confirm('Kirim ke <?= $counts['student'] ?> Murid?')">
            <i class="bi bi-send me-1"></i>Blast ke Murid
          </button>
        </form>
      </div>
      <div class="type-card">
        <div class="type-count"><?= $counts['parent'] ?></div>
        <div class="type-label">Komite Orang Tua</div>
        <form method="POST">
          <input type="hidden" name="blast_type" value="parent">
          <input type="hidden" name="subject" id="s_parent">
          <input type="hidden" name="body" id="b_parent">
          <button type="submit" class="btn-blast parent"
            onclick="syncFields('parent')"
            onsubmit="return confirm('Kirim ke <?= $counts['parent'] ?> Komite Orang Tua?')">
            <i class="bi bi-send me-1"></i>Blast ke Komite Ortu
          </button>
        </form>
      </div>
      <div class="type-card">
        <div class="type-count"><?= $counts['osis'] ?></div>
        <div class="type-label">OSIS</div>
        <form method="POST">
          <input type="hidden" name="blast_type" value="osis">
          <input type="hidden" name="subject" id="s_osis">
          <input type="hidden" name="body" id="b_osis">
          <button type="submit" class="btn-blast osis"
            onclick="syncFields('osis')"
            onsubmit="return confirm('Kirim ke <?= $counts['osis'] ?> anggota OSIS?')"
            <?= $counts['osis']==0 ? 'disabled title="Belum ada anggota OSIS terdaftar"' : '' ?>>
            <i class="bi bi-send me-1"></i>Blast ke OSIS
          </button>
        </form>
      </div>
    </div>

    <!-- Blast Lainnya — kategori dinamis dari role user yang ada -->
    <?php if (!empty($otherRoleOptions)): ?>
    <div class="other-blast-card">
      <div class="other-blast-lbl"><i class="bi bi-people me-1"></i>Blast Lainnya</div>
      <form method="POST" class="other-blast-row" onsubmit="return syncOther(event)">
        <select name="blast_type" id="other_role_select" required>
          <option value="">Pilih kategori...</option>
          <?php foreach ($otherRoleOptions as $opt): ?>
          <option value="<?= h($opt['role']) ?>"><?= h(roleLabel($opt['role'])) ?> (<?= $opt['cnt'] ?> orang)</option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="subject" id="s_other">
        <input type="hidden" name="body" id="b_other">
        <button type="submit" class="btn-blast other">
          <i class="bi bi-send me-1"></i>Kirim
        </button>
      </form>
    </div>
    <?php endif; ?>

    <!-- Email composer -->
    <div class="field">
      <label>Subject Email</label>
      <input type="text" id="subject_main" value="<?= h($defaultSubject) ?>">
    </div>
    <div class="field">
      <label>Body Email <span style="font-size:11px;color:#94a3b8;font-weight:400">— gunakan {{name}}, {{email}}, {{url}}, {{set_password_url}} sebagai placeholder</span></label>
      <textarea id="body_main" rows="20" style="font-family:monospace;font-size:12px;line-height:1.7"><?= h($defaultBody) ?></textarea>
    </div>

    <div style="background:#E6F1FB;border-radius:8px;padding:10px 14px;font-size:12px;color:#0C447C;margin-bottom:12px">
      <i class="bi bi-info-circle me-1"></i>
      <strong>Placeholder tersedia:</strong>
      <code>{{name}}</code> — nama penerima &nbsp;|&nbsp;
      <code>{{email}}</code> — email penerima &nbsp;|&nbsp;
      <code>{{url}}</code> — URL platform &nbsp;|&nbsp;
      <code>{{set_password_url}}</code> — link set password unik per user
    </div>
    <form method="POST" style="text-align:right">
      <input type="hidden" name="test_blast" value="1">
      <input type="hidden" name="subject" id="s_test">
      <input type="hidden" name="body" id="b_test">
      <button type="submit" class="btn-blast" style="background:#533AB7;width:auto;padding:9px 20px"
        onclick="syncFields('test')"
        onsubmit="return confirm('Kirim test email ke edu@kaderbangsa.foundation?')">
        <i class="bi bi-bug me-1"></i>Test Blast (ke edu@kaderbangsa.foundation)
      </button>
    </form>
  </div>
</div>

<!-- LOG -->
<div class="blast-card">
  <div class="blast-hdr">
    <i class="bi bi-journal-text"></i>Log Pengiriman
    <span style="font-weight:400;color:#94a3b8;margin-left:4px">(<?= $logTotal ?> total)</span>
  </div>

  <!-- RANGKUMAN -->
  <?php if (!empty($blastSummary)): ?>
  <div class="log-summary">
    <div class="summary-total">
      <div class="summary-total-val"><?= $totalSentAll ?></div>
      <div class="summary-total-lbl">Total Terkirim</div>
      <?php if ($totalFailedAll > 0): ?>
      <div class="summary-total-fail"><?= $totalFailedAll ?> gagal</div>
      <?php endif; ?>
    </div>
    <div class="summary-breakdown">
      <?php foreach ($blastSummary as $bs): ?>
      <div class="summary-chip">
        <span class="summary-chip-lbl"><?= h(blastTypeLabel($bs['blast_type'])) ?></span>
        <span class="summary-chip-val"><?= $bs['sent_count'] ?></span>
        <?php if ($bs['failed_count'] > 0): ?>
        <span class="summary-chip-fail">(<?= $bs['failed_count'] ?> gagal)</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="blast-body" style="padding:0">
    <?php if (empty($logs)): ?>
    <div style="text-align:center;padding:32px;color:#94a3b8;font-size:13px">Belum ada log pengiriman</div>
    <?php else: ?>
    <table class="log-table">
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Tipe</th>
          <th>Penerima</th>
          <th>Email</th>
          <th>Status</th>
          <th>Oleh</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $l): ?>
        <tr>
          <td style="white-space:nowrap"><?= date('d M Y H:i', strtotime($l['sent_at'])) ?></td>
          <td><span class="badge-type-generic"><?= h(blastTypeLabel($l['blast_type'])) ?></span></td>
          <td><?= h($l['recipient_name']) ?></td>
          <td style="color:#64748b"><?= h($l['recipient_email']) ?></td>
          <td><span class="<?= $l['status']==='sent'?'badge-sent':'badge-failed' ?>"><?= $l['status']==='sent'?'Terkirim':'Gagal' ?></span></td>
          <td style="color:#64748b"><?= h($l['sender_name']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($logPagi['total_pages'] > 1): ?>
    <div class="log-pager">
      <span class="log-pager-info">
        Halaman <?= $logPagi['page'] ?> dari <?= $logPagi['total_pages'] ?>
        (<?= $logPagi['offset']+1 ?>–<?= min($logPagi['offset']+$logPerPage, $logTotal) ?> dari <?= $logTotal ?>)
      </span>
      <div class="log-pager-btns">
        <a href="?log_page=<?= max(1,$logPagi['page']-1) ?>" class="log-pager-btn <?= $logPagi['page']<=1?'disabled':'' ?>">
          <i class="bi bi-chevron-left"></i> Sebelumnya
        </a>
        <a href="?log_page=<?= min($logPagi['total_pages'],$logPagi['page']+1) ?>" class="log-pager-btn <?= $logPagi['page']>=$logPagi['total_pages']?'disabled':'' ?>">
          Selanjutnya <i class="bi bi-chevron-right"></i>
        </a>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<script>
function syncFields(type) {
  const subj = document.getElementById('subject_main').value;
  const body = document.getElementById('body_main').value;
  const sEl = document.getElementById('s_' + type);
  const bEl = document.getElementById('b_' + type);
  if (sEl) sEl.value = subj;
  if (bEl) bEl.value = body;
}

function syncOther(e) {
  const sel = document.getElementById('other_role_select');
  if (!sel.value) { return false; }
  const subj = document.getElementById('subject_main').value;
  const body = document.getElementById('body_main').value;
  document.getElementById('s_other').value = subj;
  document.getElementById('b_other').value = body;
  return confirm('Kirim ke kategori "' + sel.options[sel.selectedIndex].text + '"?');
}
</script>

<?php
$content = ob_get_clean();
pageWrapper('Blast Email', $content);