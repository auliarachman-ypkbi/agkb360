<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';
require_once __DIR__ . '/../includes/campaigns.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin','admin']);
$me = currentUser();

// ── SIMPAN PENGATURAN KAMPANYE ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_kampanye'])) {
    $code = (string)$_POST['simpan_kampanye'];
    if (isset(kmpDefinisi()[$code])) {
        kmpSimpan(
            $code,
            !empty($_POST['aktif']),
            (int)($_POST['jeda_hari']  ?? 7),
            (int)($_POST['maks_kirim'] ?? 0),
            (array)($_POST['roles']    ?? []),
            trim($_POST['ends_at'] ?? '') ?: null,
            isset($_POST['jam_kirim']) ? (int)$_POST['jam_kirim'] : null
        );
        flash('Pengaturan kampanye "' . h(kmpDefinisi()[$code]['nama']) . '" disimpan.', 'success');
    }
    header('Location: ' . APP_URL . '/admin/blast_email.php#kampanye');
    exit;
}

// ── SIMPAN NASKAH EMAIL KAMPANYE ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_naskah'])) {
    $code = (string)$_POST['simpan_naskah'];
    if (isset(kmpDefinisi()[$code])) {
        if (!empty($_POST['kembalikan_bawaan'])) {
            kmpSimpanNaskah($code, null, null, null, null);
            flash('Naskah dikembalikan ke bawaan.', 'success');
        } else {
            kmpSimpanNaskah(
                $code,
                $_POST['subjek'] ?? '',
                $_POST['judul']  ?? '',
                $_POST['body']   ?? '',
                $_POST['cta']    ?? ''
            );

            // Uji kirim: naskah disimpan lebih dulu, lalu satu email
            // dikirim ke alamat admin yang sedang membuka halaman ini.
            // Tidak menyentuh sasaran kampanye sama sekali, dan tidak
            // dicatat di log blast — ini uji coba, bukan pengiriman.
            if (!empty($_POST['kirim_uji'])) {
                $s  = kmpPengaturan($code);
                $d  = kmpDefinisi()[$code];
                $me = currentUser();

                // Alamat tujuan diisi sendiri — sering kali yang perlu
                // memeriksa tampilan email bukan orang yang membuka
                // halaman ini. Kosong berarti kirim ke diri sendiri.
                $tujuanUji = trim($_POST['uji_email'] ?? '');
                if (!filter_var($tujuanUji, FILTER_VALIDATE_EMAIL)) {
                    $tujuanUji = $me['email'] ?? '';
                }
                $_SESSION['uji_email_terakhir'] = $tujuanUji;

                if (!$tujuanUji) {
                    flash('Naskah disimpan, tetapi alamat uji tidak valid dan akun Anda tidak punya email.', 'danger');
                    header('Location: ' . APP_URL . '/admin/blast_email.php#kampanye');
                    exit;
                }

                // {{jumlah}} hanya ada pada kampanye berbasis hitungan;
                // diisi angka contoh supaya penandanya tidak tertinggal
                // mentah di email uji.
                $aku = $me + ['jumlah' => 3];

                $url = fbAppUrl() . ($d['tujuan'] ?? '/login.php');
                if (!empty($d['perlu_token'])) {
                    $url = fbAppUrl() . '/auth/set-password.php?token=CONTOH-TOKEN-UJI';
                }

                $ok = fbSendMail(
                    $tujuanUji,
                    '[UJI] ' . kmpIsiPenanda((string)$s['subjek'], $aku),
                    fbMailTemplate(
                        kmpIsiPenanda((string)$s['judul'], $aku),
                        kmpIsiPenanda((string)$s['body'],  $aku),
                        $url,
                        kmpIsiPenanda((string)$s['cta'],   $aku)
                    )
                );

                flash($ok
                    ? 'Naskah disimpan. Email uji dikirim ke ' . h($tujuanUji)
                      . ' — periksa juga folder spam.'
                    : 'Naskah disimpan, tetapi email uji gagal dikirim. Periksa pengaturan APPS_SCRIPT_URL.',
                    $ok ? 'success' : 'danger');

                header('Location: ' . APP_URL . '/admin/blast_email.php#kampanye');
                exit;
            }
            flash('Naskah email disimpan.', 'success');
        }
    }
    header('Location: ' . APP_URL . '/admin/blast_email.php#kmp-' . urlencode($code));
    exit;
}

// ── JALANKAN KAMPANYE SEKARANG (manual) ──────────────────────
$kmpHasil = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jalankan_kampanye'])) {
    $code     = (string)$_POST['jalankan_kampanye'];
    $simulasi = !empty($_POST['simulasi']);
    if (isset(kmpDefinisi()[$code])) {
        $kmpHasil = kmpJalankan($code, $simulasi);
        $kmpHasil['simulasi'] = $simulasi;
    }
}

$baseUrl     = (defined('PUBLIC_BASE_URL') ? PUBLIC_BASE_URL : 'https://agkb360.app') . APP_URL;
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
    WHERE role NOT IN ('superadmin','admin','tester','leader','teacher','student','parent','foundation')
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

    $allowedTypes = array_merge(['leader','teacher','student','parent','osis','foundation'], $otherRoleKeys);
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
            . '<body style="margin:0;padding:0;background:#f3f4f6;font-family:\'Host Grotesk\',-apple-system,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px"><tr><td align="center">'
            . '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e3e5ea">'
            . '<tr><td style="background:#040136;padding:28px 32px;text-align:center">'
            . '<img src="' . $baseUrl . '/assets/img/brand/agkb-mark.png" width="40" height="40" alt="" style="display:block;margin:0 auto 10px;border:0;outline:none;text-decoration:none">'
            . '<div style="font-size:26px;font-weight:700;color:#ffffff;letter-spacing:-.5px">AGKB <span style="color:#ff9101">360&deg;</span></div>'
            . '<div style="font-size:11px;color:rgba(255,255,255,0.7);margin-top:4px;letter-spacing:1px;text-transform:uppercase">Performance Review Platform</div>'
            . '</td></tr>'
            . '<tr><td style="padding:28px 32px">'
            . '<div style="font-size:14px;color:#2f2d4d;line-height:1.8">' . $bodyHtml . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:0 32px 28px;text-align:center">'
            . '<a href="' . $setPasswordUrl . '" style="display:inline-block;background:#040136;color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:700">Set Password &amp; Access Platform &rarr;</a>'
            . '<div style="margin-top:10px;font-size:11px;color:#6f6e85">Or copy: <a href="' . $setPasswordUrl . '" style="color:#030870">' . $setPasswordUrl . '</a></div>'
            . '</td></tr>'
            . '<tr><td style="background:#fafafb;padding:14px 32px;border-top:1px solid #e3e5ea;text-align:center">'
            . '<div style="font-size:11px;color:#6f6e85">AGKB 360&deg; &bull; Yayasan Pendidikan Kader Bangsa Indonesia &bull; ' . date('Y') . '</div>'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';

        // Kirim lewat gerbang tunggal — ini yang menghormati
        // FEEDBACK_DEMO_MODE, sehingga demo tidak pernah mengirim nyata.
        $ok = fbSendMail($r['email'], $subject, $htmlEmail);

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
        . '<body style="margin:0;padding:0;background:#f3f4f6;font-family:\'Host Grotesk\',-apple-system,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px"><tr><td align="center">'
        . '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e3e5ea">'
        . '<tr><td style="background:#040136;padding:28px 32px;text-align:center">'
        . '<img src="' . $baseUrl . '/assets/img/brand/agkb-mark.png" width="40" height="40" alt="" style="display:block;margin:0 auto 10px;border:0;outline:none;text-decoration:none">'
            . '<div style="font-size:26px;font-weight:700;color:#ffffff;letter-spacing:-.5px">AGKB <span style="color:#ff9101">360&deg;</span></div>'
        . '<div style="font-size:11px;color:rgba(255,255,255,0.7);margin-top:4px;letter-spacing:1px;text-transform:uppercase">Performance Review Platform</div>'
        . '</td></tr>'
        . '<tr><td style="padding:28px 32px">'
        . '<div style="font-size:14px;color:#2f2d4d;line-height:1.8">' . $bodyHtml . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:0 32px 28px;text-align:center">'
        . '<a href="' . $setPasswordUrl . '" style="display:inline-block;background:#040136;color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:700">Set Password &amp; Access Platform &rarr;</a>'
        . '<div style="margin-top:10px;font-size:11px;color:#6f6e85">Or copy: <a href="' . $setPasswordUrl . '" style="color:#030870">' . $setPasswordUrl . '</a></div>'
        . '</td></tr>'
        . '<tr><td style="background:#fafafb;padding:14px 32px;border-top:1px solid #e3e5ea;text-align:center">'
        . '<div style="font-size:11px;color:#6f6e85">AGKB 360&deg; &bull; Yayasan Pendidikan Kader Bangsa Indonesia &bull; ' . date('Y') . '</div>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';

    $ok = fbSendMail($testEmail, '[TEST] ' . $subject, $htmlEmail);

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
    'foundation' => Database::fetchOne("SELECT COUNT(*) c FROM users WHERE role='foundation' AND is_active=1")['c'],
];

// ── LOG dengan PAGINATION (10/halaman, terbaru di atas) ─────────
$logPage    = max(1, (int)($_GET['log_page'] ?? 1));
$logPerPage = 10;
$logTotal   = Database::fetchOne("SELECT COUNT(*) c FROM email_blast_log")['c'];
$logPagi    = paginate($logTotal, $logPerPage, $logPage);
$logs = Database::fetchAll("
    SELECT l.*, u.name as recipient_name, s.name as sender_name
    FROM email_blast_log l
    LEFT JOIN users u ON u.id = l.recipient_id
    LEFT JOIN users s ON s.id = l.sent_by
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
.blast-card{background:#fff;border:0.5px solid #e3e5ea;border-radius:12px;overflow:hidden;margin-bottom:16px}
.blast-hdr{padding:12px 20px;font-size:13px;font-weight:600;color:#040136;border-bottom:0.5px solid #e3e5ea;background:#fafafb;display:flex;align-items:center;gap:8px}
.blast-body{padding:20px}
.type-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px}
.type-card{border:1.5px solid #e3e5ea;border-radius:10px;padding:16px;text-align:center}
.type-count{font-size:28px;font-weight:600;color:#040136}
.type-label{font-size:12px;color:#6b6a83;margin:4px 0 12px}
.btn-blast{width:100%;padding:9px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;color:white;transition:opacity .15s}
.btn-blast:hover{opacity:.85}
.btn-blast:disabled{opacity:.4;cursor:not-allowed}
.btn-blast.leader{background:#040136}
.btn-blast.teacher{background:#b83a01}
.btn-blast.student{background:#015c36}
.btn-blast.parent{background:#2201b2}
.btn-blast.osis{background:#b83a01}
.btn-blast.foundation{background:#030870}
.btn-blast.other{background:#030870;width:auto;padding:0 18px;flex-shrink:0}
.other-blast-card{border:1.5px dashed #cdd0d8;border-radius:10px;padding:14px 16px;margin-bottom:20px;background:#fafafb}
.other-blast-lbl{font-size:12px;font-weight:600;color:#6b6a83;margin-bottom:10px}
.other-blast-row{display:flex;gap:10px;align-items:stretch}
.other-blast-row select{flex:1;height:38px;border:1px solid #e3e5ea;border-radius:8px;padding:0 12px;font-size:13px;color:#040136;outline:none;background:#fff}
.other-blast-row select:focus{border-color:#040136;box-shadow:0 0 0 3px rgba(4,1,54,.1)}
.other-blast-row .btn-blast{height:38px;display:inline-flex;align-items:center}
.field{margin-bottom:14px}
.field label{display:block;font-size:11px;font-weight:600;color:#6b6a83;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.field input,.field textarea{width:100%;border:1px solid #e3e5ea;border-radius:8px;padding:9px 12px;font-size:13px;font-family:inherit;outline:none}
.field input:focus,.field textarea:focus{border-color:#040136;box-shadow:0 0 0 3px rgba(4,1,54,.1)}
.result-box{border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;display:flex;align-items:center;gap:8px}
.result-ok{background:#e7f6ef;border:0.5px solid #027a48;color:#015c36}
.result-warn{background:#fff1dc;border:0.5px solid #b83a01;color:#b83a01}
.log-table{width:100%;font-size:12px;border-collapse:collapse}
.log-table th{background:#fafafb;padding:8px 12px;text-align:left;font-weight:600;color:#6b6a83;border-bottom:1px solid #e3e5ea}
.log-table td{padding:8px 12px;border-bottom:0.5px solid #f3f4f6;color:#2f2d4d}
.badge-sent{background:#e7f6ef;color:#015c36;border:0.5px solid #027a48;font-size:10px;padding:2px 8px;border-radius:20px}
.badge-failed{background:#fdeceb;color:#8c1610;border:0.5px solid #f3b5b0;font-size:10px;padding:2px 8px;border-radius:20px}
.badge-type-generic{display:inline-block;background:#eeebfc;color:#030870;border:0.5px solid #b9aef2;font-size:10px;font-weight:600;padding:2px 9px;border-radius:20px}
.log-summary{display:flex;gap:16px;align-items:stretch;padding:16px 20px;border-bottom:0.5px solid #e3e5ea;background:#fafafb;flex-wrap:wrap}
.summary-total{flex-shrink:0;padding-right:16px;border-right:1px solid #e3e5ea;text-align:center;min-width:100px}
.summary-total-val{font-size:26px;font-weight:700;color:#040136;line-height:1.1}
.summary-total-lbl{font-size:10px;color:#6f6e85;text-transform:uppercase;letter-spacing:.4px;margin-top:2px}
.summary-total-fail{font-size:10px;color:#b42318;margin-top:3px}
.summary-breakdown{display:flex;gap:8px;flex-wrap:wrap;align-items:center;flex:1}
.summary-chip{background:#fff;border:0.5px solid #e3e5ea;border-radius:8px;padding:6px 12px;display:flex;align-items:center;gap:6px;font-size:12px}
.summary-chip-lbl{color:#6b6a83;font-weight:500}
.summary-chip-val{color:#040136;font-weight:700}
.summary-chip-fail{color:#b42318;font-size:10px}
.log-pager{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-top:0.5px solid #e3e5ea;background:#fafafb}
.log-pager-info{font-size:11px;color:#6b6a83}
.log-pager-btns{display:flex;gap:8px}
.log-pager-btn{padding:5px 12px;border-radius:7px;border:1px solid #e3e5ea;background:#fff;color:#2f2d4d;text-decoration:none;font-size:11px;display:inline-flex;align-items:center;gap:4px}
.log-pager-btn.disabled{opacity:.4;pointer-events:none}

/* ── Kampanye terjadwal ── */
.kmp{border:1px solid #e3e5ea;border-radius:10px;margin-bottom:10px;overflow:hidden;background:#fff}
.kmp.on{border-color:#027a48;box-shadow:0 0 0 2px rgba(2,122,72,.08)}
.kmp-top{display:flex;align-items:center;gap:12px;padding:13px 16px;background:#fafafb;border-bottom:1px solid #f3f4f6;flex-wrap:wrap}
.kmp-nama{font-size:13.5px;font-weight:700;color:#040136}
.kmp-ket{font-size:11.5px;color:#6b6a83;margin-top:2px;line-height:1.5}
.kmp-lampu{width:9px;height:9px;border-radius:50%;background:#cdd0d8;flex-shrink:0}
.kmp-lampu.on{background:#027a48;box-shadow:0 0 0 3px rgba(2,122,72,.18)}
.kmp-sasaran{margin-left:auto;text-align:right;flex-shrink:0}
.kmp-sasaran b{font-size:20px;color:#040136;font-weight:700;line-height:1}
.kmp-sasaran span{display:block;font-size:10px;color:#6f6e85;text-transform:uppercase;letter-spacing:.4px;margin-top:2px}
.kmp-isi{padding:14px 16px;display:flex;gap:18px;flex-wrap:wrap;align-items:flex-end}
.kmp-f{display:flex;flex-direction:column;gap:5px}
.kmp-f label{font-size:10px;font-weight:600;color:#6b6a83;text-transform:uppercase;letter-spacing:.5px}
.kmp-f input[type=number],.kmp-f input[type=date],.kmp-f select{width:104px;border:1px solid #e3e5ea;border-radius:7px;padding:7px 10px;font-size:13px;outline:none;background:#fff;color:#040136;font-family:inherit}
.kmp-f input:focus,.kmp-f select:focus{border-color:#040136;box-shadow:0 0 0 3px rgba(4,1,54,.1)}
.kmp-peran{display:flex;gap:6px;flex-wrap:wrap;max-width:430px}
.kmp-peran label{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;color:#2f2d4d;border:1px solid #e3e5ea;border-radius:20px;padding:4px 11px;cursor:pointer;text-transform:none;letter-spacing:0;font-weight:500}
.kmp-peran label:has(input:checked){background:#eeebfc;border-color:#b9aef2;color:#030870;font-weight:600}
.kmp-saklar{display:inline-flex;align-items:center;gap:7px;font-size:12.5px;font-weight:600;color:#040136;cursor:pointer}
.kmp-aksi{margin-left:auto;display:flex;gap:7px;align-items:center}
.kmp-btn{border:1px solid #e3e5ea;background:#fff;color:#2f2d4d;border-radius:7px;padding:7px 14px;font-size:12px;font-weight:600;cursor:pointer}
.kmp-btn:hover{background:#fafafb}
.kmp-btn.primer{background:#040136;color:#fff;border-color:#040136}
.kmp-pratinjau{background:#fafafb;border:1px solid #e3e5ea;border-radius:9px;padding:13px 16px;margin-bottom:14px;font-size:12.5px}
.kmp-pratinjau ul{margin:8px 0 0;padding-left:18px;color:#2f2d4d;line-height:1.75}

/* ── Editor naskah ── */
.kmp-naskah{border:1px solid #e3e5ea;border-top:0;border-radius:0 0 10px 10px;margin:-11px 0 10px;background:#fff;overflow:hidden}
.kmp-naskah>summary{list-style:none;cursor:pointer;padding:10px 16px;font-size:12px;font-weight:600;color:#6b6a83;display:flex;align-items:center;gap:9px;background:#fafafb;border-top:1px dashed #e3e5ea}
.kmp-naskah>summary::-webkit-details-marker{display:none}
.kmp-naskah>summary:hover{background:#f3f4f6;color:#040136}
.kmp-naskah[open]>summary{border-bottom:1px solid #e3e5ea}
.kmp-tag{font-size:9.5px;text-transform:uppercase;letter-spacing:.5px;padding:2px 8px;border-radius:20px;background:#eef0f3;color:#6b6a83;border:1px solid #dfe1e7}
.kmp-tag.ubah{background:#fff1dc;color:#b83a01;border-color:#f0c896}
.kmp-subj-peek{margin-left:auto;font-weight:400;font-size:11.5px;color:#8a89a0;font-family:ui-monospace,Menlo,monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:340px}
.kmp-form-naskah{padding:16px}
/* Sakelar Visual / HTML pada editor naskah */
.nk-mode-baris{display:flex;align-items:center;justify-content:space-between;margin-bottom:5px}
.nk-mode{display:inline-flex;border:1px solid #e3e5ea;border-radius:8px;overflow:hidden;background:#fff}
.nk-mode button{border:0;background:transparent;padding:5px 13px;font-size:11.5px;font-weight:600;color:#6b6a83;cursor:pointer;font-family:inherit}
.nk-mode button:hover{background:#f3f4f6;color:#040136}
.nk-mode button.on{background:#040136;color:#fff}
.nk-html{width:100%;min-height:260px;border:1px solid #e3e5ea;border-radius:0 0 8px 8px;padding:13px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;line-height:1.65;color:#2f2d4d;outline:none;resize:vertical;background:#fafafb}
.nk-html:focus{border-color:#040136;box-shadow:0 0 0 3px rgba(4,1,54,.08)}
.nk-html-nota{font-size:11.5px;color:#6b6a83;line-height:1.6;margin-top:7px}
.nk-html-nota code{background:#f3f4f6;border-radius:4px;padding:1px 5px;font-size:11px}
.nk-baris{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.nk-f{display:flex;flex-direction:column;gap:5px;min-width:180px}
.nk-f>label{font-size:10px;font-weight:600;color:#6b6a83;text-transform:uppercase;letter-spacing:.5px}
.nk-f input[type=text]{border:1px solid #e3e5ea;border-radius:7px;padding:8px 11px;font-size:13px;font-family:inherit;outline:none;width:100%}
.nk-f input[type=text]:focus{border-color:#040136;box-shadow:0 0 0 3px rgba(4,1,54,.1)}
.nk-editor{background:#fff;font-size:14px;line-height:1.75;color:#2f2d4d;min-height:190px}
.nk-editor.ql-container{border:1px solid #e3e5ea;border-top:0;border-radius:0 0 8px 8px;font-family:inherit}
.ql-toolbar.ql-snow{border:1px solid #e3e5ea;border-radius:8px 8px 0 0;background:#fafafb}
.ql-snow .ql-stroke{stroke:#6b6a83}.ql-snow .ql-fill{fill:#6b6a83}
.ql-snow .ql-editor{padding:14px 16px}
.ql-snow .ql-editor p{margin-bottom:11px}
.nk-kaki{display:flex;align-items:center;gap:14px;margin-top:14px;flex-wrap:wrap}
.nk-penanda{font-size:11px;color:#6b6a83}
.nk-penanda code{background:#eeebfc;color:#030870;border:1px solid #d5cdf7;border-radius:5px;padding:1.5px 6px;font-size:10.5px;margin-left:3px}
.nk-uji-email{width:230px;border:1px solid #e3e5ea;border-radius:7px;padding:7px 11px;font-size:12.5px;font-family:inherit;color:#040136;outline:none;background:#fff}
.nk-uji-email:focus{border-color:#040136;box-shadow:0 0 0 3px rgba(4,1,54,.08)}
.nk-uji-email::placeholder{color:#9b9ab0}
</style>

<link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>

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

<?php if (fbEmailDitahan()): ?>
<div class="result-box result-warn">
  <i class="bi bi-shield-fill-check"></i>
  <div>
    <strong>Email ditahan di lingkungan ini.</strong>
    <?php if (defined('FEEDBACK_DEMO_MODE') && FEEDBACK_DEMO_MODE): ?>
      Ini lingkungan peragaan — tombol kirim tetap bisa ditekan dan tercatat di log,
      tetapi tidak ada satu pun email yang benar-benar keluar.
    <?php else: ?>
      <code>APPS_SCRIPT_URL</code> belum diatur di config, jadi tidak ada gerbang pengiriman.
      Aman untuk mencoba-coba.
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($kmpHasil): ?>
<div class="kmp-pratinjau" id="hasil">
  <strong><?= h($kmpHasil['nama']) ?></strong> —
  <?= !empty($kmpHasil['simulasi']) ? 'SIMULASI, tidak ada email yang dikirim' : 'dijalankan' ?>.
  Sasaran <b><?= (int)$kmpHasil['sasaran'] ?></b> orang ·
  <?= !empty($kmpHasil['simulasi']) ? 'akan dikirim' : 'terkirim' ?> <b><?= (int)$kmpHasil['terkirim'] ?></b> ·
  gagal <b><?= (int)$kmpHasil['gagal'] ?></b> ·
  dilewati <b><?= (int)$kmpHasil['dilewati'] ?></b> (belum waktunya atau sudah cukup)
  <?php if (!empty($kmpHasil['daftar'])): ?>
  <ul>
    <?php foreach (array_slice($kmpHasil['daftar'], 0, 12) as $d): ?>
    <li><?= h($d) ?></li>
    <?php endforeach; ?>
    <?php if (count($kmpHasil['daftar']) > 12): ?>
    <li style="color:#6b6a83">… dan <?= count($kmpHasil['daftar']) - 12 ?> orang lagi</li>
    <?php endif; ?>
  </ul>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="blast-card" id="kampanye">
  <div class="blast-hdr">
    <i class="bi bi-calendar-check-fill"></i>Kampanye Terjadwal
    <span style="margin-left:auto;font-weight:400;font-size:11.5px;color:#6b6a83">
      Dijalankan otomatis tiap pagi oleh penjadwal. Yang mati tidak akan mengirim apa pun.
    </span>
  </div>
  <div class="blast-body">
    <?php foreach (kmpStatus() as $code => $k):
        $aktif   = (bool)$k['is_active'];
        $terpilih = array_filter(array_map('trim', explode(',', $k['roles'])));
        $sasaran = kmpHitungSasaran($code);
    ?>
    <form method="post" class="kmp <?= $aktif ? 'on' : '' ?>" id="kmp-<?= h($code) ?>">
      <div class="kmp-top">
        <span class="kmp-lampu <?= $aktif ? 'on' : '' ?>"></span>
        <div style="flex:1;min-width:230px">
          <div class="kmp-nama"><?= h($k['nama']) ?></div>
          <div class="kmp-ket"><?= h($k['penjelasan']) ?></div>
        </div>
        <div class="kmp-sasaran">
          <b><?= $sasaran ?></b><span>sasaran saat ini</span>
        </div>
      </div>

      <div class="kmp-isi">
        <?php if (!empty($k['manual'])): ?>
        <span class="kmp-saklar" style="color:#a85a01;background:#fff8ef;border:1px solid #f5d9b0;
              border-radius:8px;padding:8px 12px;font-size:12px;font-weight:600">
          <i class="bi bi-hand-index-thumb me-1"></i>Manual saja
        </span>
        <?php else: ?>
        <label class="kmp-saklar">
          <input type="checkbox" name="aktif" value="1" <?= $aktif ? 'checked' : '' ?>>
          Aktif
        </label>
        <?php endif; ?>

        <div class="kmp-f">
          <label>Jeda (hari)</label>
          <input type="number" name="jeda_hari" min="1" max="90" value="<?= (int)$k['jeda_hari'] ?>">
        </div>

        <div class="kmp-f">
          <label>Maks kirim</label>
          <input type="number" name="maks_kirim" min="0" max="50" value="<?= (int)$k['maks_kirim'] ?>"
                 title="0 = kirim terus sampai orangnya bertindak">
        </div>

        <div class="kmp-f">
          <label>Jam kirim</label>
          <select name="jam_kirim" title="Waktu Indonesia Barat. Penjadwal berjalan tiap jam.">
            <?php for ($j = 0; $j < 24; $j++): ?>
            <option value="<?= $j ?>" <?= (int)$k['jam_kirim'] === $j ? 'selected' : '' ?>>
              <?= sprintf('%02d:00', $j) ?>
            </option>
            <?php endfor; ?>
          </select>
        </div>

        <div class="kmp-f">
          <label>Berhenti pada</label>
          <input type="date" name="ends_at" value="<?= h(substr((string)$k['ends_at'], 0, 10)) ?>">
        </div>

        <?php if ($k['atur_peran']): ?>
        <div class="kmp-f" style="flex:1;min-width:300px">
          <label>Peran sasaran — tidak ada yang dicentang berarti semua</label>
          <div class="kmp-peran">
            <?php foreach (kmpSemuaPeran() as $r): ?>
            <label>
              <input type="checkbox" name="roles[]" value="<?= h($r) ?>"
                     <?= in_array($r, $terpilih, true) ? 'checked' : '' ?>>
              <?= h(roleLabel($r)) ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="kmp-aksi">
          <button class="kmp-btn primer" name="simpan_kampanye" value="<?= h($code) ?>">Simpan</button>
          <button class="kmp-btn" name="jalankan_kampanye" value="<?= h($code) ?>"
                  formnovalidate onclick="this.form.simulasi.value='1'">Simulasi</button>
          <button class="kmp-btn" name="jalankan_kampanye" value="<?= h($code) ?>"
                  onclick="return confirm('Kirim sekarang ke <?= $sasaran ?> sasaran? Email akan benar-benar keluar.')">
            Kirim Sekarang
          </button>
          <input type="hidden" name="simulasi" value="">
        </div>
      </div>
    </form>

    <details class="kmp-naskah"<?= $k['disunting'] ? '' : '' ?>>
      <summary>
        <i class="bi bi-pencil-square"></i>
        Naskah email
        <span class="kmp-tag <?= $k['disunting'] ? 'ubah' : '' ?>">
          <?= $k['disunting'] ? 'sudah disunting' : 'naskah bawaan' ?>
        </span>
        <span class="kmp-subj-peek"><?= h($k['subjek']) ?></span>
      </summary>

      <form method="post" class="kmp-form-naskah" data-code="<?= h($code) ?>">
        <input type="hidden" name="simpan_naskah" value="<?= h($code) ?>">

        <div class="nk-baris">
          <div class="nk-f" style="flex:2">
            <label>Subjek email</label>
            <input type="text" name="subjek" maxlength="255" value="<?= h($k['subjek']) ?>">
          </div>
          <div class="nk-f" style="flex:2">
            <label>Judul di dalam email</label>
            <input type="text" name="judul" maxlength="255" value="<?= h($k['judul']) ?>">
          </div>
          <div class="nk-f" style="flex:1">
            <label>Tulisan tombol</label>
            <input type="text" name="cta" maxlength="80" value="<?= h($k['cta']) ?>">
          </div>
        </div>

        <div class="nk-f">
          <div class="nk-mode-baris">
            <label style="margin:0">Isi email</label>
            <div class="nk-mode">
              <button type="button" class="on" data-mode="visual">Visual</button>
              <button type="button" data-mode="html">HTML</button>
            </div>
          </div>
          <div class="nk-editor" data-body="<?= h($k['body']) ?>"></div>
          <textarea class="nk-html" spellcheck="false" hidden></textarea>
          <div class="nk-html-nota" hidden>
            Ditempel apa adanya ke badan email. Untuk email, gunakan tabel dan gaya sebaris
            (<code>style="…"</code>) — sebagian besar klien email mengabaikan CSS modern.
            Tag <code>script</code>, <code>iframe</code>, dan atribut peristiwa selalu dibuang.
          </div>
          <textarea name="body" hidden></textarea>
        </div>

        <div class="nk-kaki">
          <div class="nk-penanda">
            Penanda otomatis:
            <code>{{nama}}</code> <code>{{email}}</code> <code>{{peran}}</code>
            <?php if (!$k['atur_peran']): ?><code>{{jumlah}}</code><?php endif; ?>
          </div>
          <div style="display:flex;gap:7px">
            <?php if ($k['disunting']): ?>
            <button class="kmp-btn" name="kembalikan_bawaan" value="1"
                    onclick="return confirm('Kembalikan naskah ke bawaan? Suntinganmu akan hilang.')">
              Kembalikan ke Bawaan
            </button>
            <?php endif; ?>
            <input type="email" name="uji_email" class="nk-uji-email"
                   placeholder="alamat untuk uji kirim"
                   value="<?= h($_SESSION['uji_email_terakhir'] ?? $me['email'] ?? '') ?>">
            <button class="kmp-btn" name="kirim_uji" value="1"
                    title="Menyimpan naskah lalu mengirim satu email ke alamat di sebelah kiri">
              <i class="bi bi-send-check"></i> Uji Kirim
            </button>
            <button class="kmp-btn primer">Simpan Naskah</button>
          </div>
        </div>
      </form>
    </details>
    <?php endforeach; ?>

    <div style="font-size:11.5px;color:#6b6a83;margin-top:12px;line-height:1.7">
      <strong>Jeda</strong> menjaga jarak antar kiriman ke orang yang sama, jadi penjadwal boleh jalan tiap hari tanpa membanjiri siapa pun.
      <strong>Maks kirim</strong> membatasi total kiriman per orang — isi <strong>0</strong> untuk mengirim terus sampai orangnya bertindak.
      Sasaran menyusut sendiri: yang sudah login hilang dari Aktivasi, yang sudah mengirim tiket hilang dari Ajakan.
    </div>
  </div>
</div>

<div class="blast-card">
  <div class="blast-hdr"><i class="bi bi-send-fill"></i>Blast Manual Sekali Kirim</div>
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
      <div class="type-card">
        <div class="type-count"><?= $counts['foundation'] ?></div>
        <div class="type-label">Yayasan</div>
        <form method="POST">
          <input type="hidden" name="blast_type" value="foundation">
          <input type="hidden" name="subject" id="s_foundation">
          <input type="hidden" name="body" id="b_foundation">
          <button type="submit" class="btn-blast foundation"
            onclick="syncFields('foundation')"
            onsubmit="return confirm('Kirim ke <?= $counts['foundation'] ?> Pengurus Yayasan?')"
            <?= $counts['foundation']==0 ? 'disabled title="Belum ada Pengurus Yayasan terdaftar"' : '' ?>>
            <i class="bi bi-send me-1"></i>Blast ke Yayasan
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
      <label>Body Email <span style="font-size:11px;color:#6f6e85;font-weight:400">— gunakan {{name}}, {{email}}, {{url}}, {{set_password_url}} sebagai placeholder</span></label>
      <textarea id="body_main" rows="20" style="font-family:monospace;font-size:12px;line-height:1.7"><?= h($defaultBody) ?></textarea>
    </div>

    <div style="background:#eeebfc;border-radius:8px;padding:10px 14px;font-size:12px;color:#030870;margin-bottom:12px">
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
      <button type="submit" class="btn-blast" style="background:#030870;width:auto;padding:9px 20px"
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
    <span style="font-weight:400;color:#6f6e85;margin-left:4px">(<?= $logTotal ?> total)</span>
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
    <div style="text-align:center;padding:32px;color:#6f6e85;font-size:13px">Belum ada log pengiriman</div>
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
          <td><?= h($l['recipient_name'] ?? '—') ?></td>
          <td style="color:#6b6a83"><?= h($l['recipient_email']) ?></td>
          <td><span class="<?= $l['status']==='sent'?'badge-sent':'badge-failed' ?>"><?= $l['status']==='sent'?'Terkirim':'Gagal' ?></span></td>
          <td style="color:#6b6a83">
            <?php if ($l['sender_name']): ?>
              <?= h($l['sender_name']) ?>
            <?php else: ?>
              <span style="font-style:italic">Penjadwal</span>
            <?php endif; ?>
          </td>
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

<script>
// Editor naskah email. Dimuat malas: Quill baru dipasang saat
// bagian naskah dibuka, supaya halaman tidak berat oleh lima editor
// yang mungkin tidak pernah disentuh.
document.querySelectorAll('.kmp-naskah').forEach(function (det) {
  var siap = false;
  det.addEventListener('toggle', function () {
    if (!det.open || siap) return;
    siap = true;

    var wadah = det.querySelector('.nk-editor');
    var kotak = det.querySelector('textarea[name=body]');
    var mentah = det.querySelector('.nk-html');
    var nota   = det.querySelector('.nk-html-nota');
    var isi   = wadah.getAttribute('data-body') || '';

    var q = new Quill(wadah, {
      theme: 'snow',
      modules: {
        toolbar: [
          [{ header: [3, 4, false] }],
          ['bold', 'italic', 'underline'],
          [{ list: 'bullet' }, { list: 'ordered' }],
          ['link', 'blockquote'],
          ['clean']
        ]
      }
    });
    q.clipboard.dangerouslyPasteHTML(isi);

    // Mode HTML. Yang tersimpan selalu isi mode yang sedang aktif,
    // supaya tidak pernah ada dua sumber kebenaran.
    //
    // Berpindah dari HTML ke Visual bersifat merusak: Quill hanya
    // mengenal sebagian tag, sehingga tabel dan gaya sebaris akan
    // rontok. Karena itu perpindahan itu diminta persetujuan dulu.
    var modeHtml = false;

    function keHtml() {
      mentah.value = q.root.innerHTML === '<p><br></p>' ? '' : q.root.innerHTML;
      wadah.previousElementSibling.hidden = true;   // toolbar Quill
      wadah.hidden = true;
      mentah.hidden = false;
      nota.hidden = false;
      modeHtml = true;
    }

    function keVisual() {
      if (!confirm('Kembali ke mode Visual?\n\nTabel, gambar, dan gaya sebaris akan hilang karena editor visual tidak mengenalinya. Salin dulu HTML Anda bila masih diperlukan.')) {
        return false;
      }
      q.clipboard.dangerouslyPasteHTML(mentah.value || '');
      wadah.previousElementSibling.hidden = false;
      wadah.hidden = false;
      mentah.hidden = true;
      nota.hidden = true;
      modeHtml = false;
      return true;
    }

    det.querySelectorAll('.nk-mode button').forEach(function (b) {
      b.addEventListener('click', function () {
        var mau = b.getAttribute('data-mode');
        if (mau === 'html' && !modeHtml) keHtml();
        else if (mau === 'visual' && modeHtml && !keVisual()) return;
        det.querySelectorAll('.nk-mode button').forEach(function (x) {
          x.classList.toggle('on', x === b);
        });
      });
    });

    det.querySelector('form').addEventListener('submit', function () {
      var html = modeHtml ? mentah.value : q.root.innerHTML;
      kotak.value = (html === '<p><br></p>') ? '' : html;
    });
  });
});
</script>

<?php
$content = ob_get_clean();
pageWrapper('Blast Email', $content);
