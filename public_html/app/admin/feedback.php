<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin','admin']);
$user = currentUser();

// Handle reply
$replySuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_feedback'])) {
    $fbId     = (int)$_POST['feedback_id'];
    $response = trim($_POST['admin_response'] ?? '');
    if ($fbId && strlen($response) >= 5) {
        $fb = Database::fetchOne("SELECT f.*, u.name as sender_name, u.email as sender_email FROM feedback f JOIN users u ON u.id=f.sender_id WHERE f.id=?", [$fbId]);
        if ($fb) {
            Database::query("UPDATE feedback SET admin_response=?, responded_by=?, status='responded', responded_at=NOW() WHERE id=?",
                [$response, $user['id'], $fbId]);

            // Kirim email balasan via Apps Script
            $scriptUrl = defined('APPS_SCRIPT_URL') ? APPS_SCRIPT_URL : '';
            if ($scriptUrl) {
                $payload = json_encode([
                    'to'      => $fb['sender_email'],
                    'subject' => '[AKGB 360°] Balasan untuk: ' . $fb['subject'],
                    'body'    => "Yth. {$fb['sender_name']},\n\nBerikut balasan atas feedback Anda:\n\n{$response}\n\n---\nSalam,\nTim Yayasan Kader Bangsa",
                ]);
                @file_get_contents($scriptUrl, false, stream_context_create([
                    'http' => ['method'=>'POST','header'=>'Content-Type: application/json','content'=>$payload]
                ]));
            }
            flash('Balasan berhasil dikirim ke ' . $fb['sender_email'], 'success');
        }
    }
    header('Location: ' . APP_URL . '/admin/feedback.php');
    exit;
}

// Filter
$filter   = $_GET['filter'] ?? 'all';
$detailId = (int)($_GET['id'] ?? 0);

$where = $filter === 'new' ? "WHERE f.status='new'" : ($filter === 'responded' ? "WHERE f.status='responded'" : '');
$feedbacks = Database::fetchAll("
    SELECT f.*, u.name as sender_name, u.role as sender_role, u.email as sender_email,
           r.name as responder_name
    FROM feedback f
    JOIN users u ON u.id = f.sender_id
    LEFT JOIN users r ON r.id = f.responded_by
    $where
    ORDER BY f.created_at DESC
");

$detailFb = $detailId ? Database::fetchOne("
    SELECT f.*, u.name as sender_name, u.role as sender_role, u.email as sender_email,
           r.name as responder_name
    FROM feedback f JOIN users u ON u.id=f.sender_id
    LEFT JOIN users r ON r.id=f.responded_by
    WHERE f.id=?", [$detailId]) : null;

$counts = [
    'all'       => Database::fetchOne("SELECT COUNT(*) c FROM feedback")['c'],
    'new'       => Database::fetchOne("SELECT COUNT(*) c FROM feedback WHERE status='new'")['c'],
    'responded' => Database::fetchOne("SELECT COUNT(*) c FROM feedback WHERE status='responded'")['c'],
];

ob_start(); ?>

<style>
.fb-layout{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start}
.fb-panel{background:#fff;border:0.5px solid #e2e8f0;border-radius:12px;overflow:hidden}
.fb-panel-hdr{padding:12px 16px;background:#2C5282;color:white;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px}
.filter-row{display:flex;gap:6px;padding:12px 14px;border-bottom:0.5px solid #e2e8f0}
.filter-btn{padding:4px 12px;border-radius:20px;font-size:12px;border:0.5px solid #e2e8f0;background:transparent;color:#64748b;cursor:pointer;text-decoration:none}
.filter-btn.active{background:#2C5282;color:white;border-color:#2C5282}
.fb-item{padding:12px 16px;border-bottom:0.5px solid #f1f5f9;cursor:pointer;transition:background .1s}
.fb-item:hover{background:#f8fafc}
.fb-item.selected{background:#EBF4FF;border-left:3px solid #2C5282}
.fb-item:last-child{border-bottom:none}
.badge-appr{background:#EAF3DE;color:#27500A;border:0.5px solid #3B6D11;font-size:10px;padding:2px 8px;border-radius:20px;font-weight:500}
.badge-conc{background:#FAEEDA;color:#633806;border:0.5px solid #854F0B;font-size:10px;padding:2px 8px;border-radius:20px;font-weight:500}
.badge-new{background:#E6F1FB;color:#0C447C;border:0.5px solid #185FA5;font-size:10px;padding:2px 8px;border-radius:20px}
.badge-done{background:#EAF3DE;color:#27500A;border:0.5px solid #3B6D11;font-size:10px;padding:2px 8px;border-radius:20px}
.fb-subj{font-size:13px;font-weight:500;color:#1e293b;margin:4px 0 2px}
.fb-from{font-size:11px;color:#94a3b8}
.detail-body{padding:16px}
.detail-msg{background:#f8fafc;border-radius:8px;padding:14px;font-size:13px;color:#1e293b;line-height:1.7;margin:12px 0;border-left:3px solid #2C5282}
.detail-label{font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.reply-form textarea{width:100%;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;font-size:13px;font-family:inherit;resize:vertical;outline:none}
.reply-form textarea:focus{border-color:#2C5282;box-shadow:0 0 0 3px rgba(44,82,130,.1)}
.btn-reply{padding:9px 20px;background:#2C5282;color:white;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;margin-top:10px}
.btn-reply:hover{background:#1A365D}
.response-box{background:#EAF3DE;border:0.5px solid #3B6D11;border-radius:8px;padding:14px;margin-top:12px}
.empty-state{text-align:center;padding:40px 16px;color:#94a3b8}
</style>

<?= showFlash() ?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px">
  <div style="background:#f8fafc;border-radius:10px;padding:12px 16px;border:0.5px solid #e2e8f0;text-align:center">
    <div style="font-size:24px;font-weight:500;color:#1e293b"><?= $counts['all'] ?></div>
    <div style="font-size:11px;color:#64748b">Total Feedback</div>
  </div>
  <div style="background:#E6F1FB;border-radius:10px;padding:12px 16px;border:0.5px solid #B5D4F4;text-align:center">
    <div style="font-size:24px;font-weight:500;color:#185FA5"><?= $counts['new'] ?></div>
    <div style="font-size:11px;color:#0C447C">Belum Dibalas</div>
  </div>
  <div style="background:#EAF3DE;border-radius:10px;padding:12px 16px;border:0.5px solid #C0DD97;text-align:center">
    <div style="font-size:24px;font-weight:500;color:#3B6D11"><?= $counts['responded'] ?></div>
    <div style="font-size:11px;color:#27500A">Sudah Dibalas</div>
  </div>
</div>

<div class="fb-layout">

  <!-- LIST -->
  <div class="fb-panel">
    <div class="fb-panel-hdr">
      <i class="bi bi-inbox-fill"></i> Inbox Feedback
    </div>
    <div class="filter-row">
      <a href="?filter=all" class="filter-btn <?= $filter==='all'?'active':'' ?>">Semua (<?= $counts['all'] ?>)</a>
      <a href="?filter=new" class="filter-btn <?= $filter==='new'?'active':'' ?>">Baru (<?= $counts['new'] ?>)</a>
      <a href="?filter=responded" class="filter-btn <?= $filter==='responded'?'active':'' ?>">Dibalas (<?= $counts['responded'] ?>)</a>
    </div>
    <?php if (empty($feedbacks)): ?>
    <div class="empty-state">
      <i class="bi bi-inbox" style="font-size:32px;display:block;margin-bottom:8px;opacity:.4"></i>
      <p style="font-size:13px">Belum ada feedback</p>
    </div>
    <?php else: ?>
    <?php foreach ($feedbacks as $fb): ?>
    <a href="?filter=<?= $filter ?>&id=<?= $fb['id'] ?>" style="text-decoration:none">
      <div class="fb-item <?= $detailId===$fb['id']?'selected':'' ?>">
        <div style="display:flex;gap:6px;align-items:center;margin-bottom:4px;flex-wrap:wrap">
          <span class="<?= $fb['type']==='appreciation'?'badge-appr':'badge-conc' ?>">
            <?= $fb['type']==='appreciation'?'Apresiasi':'Perhatian' ?>
          </span>
          <span class="<?= $fb['status']==='new'?'badge-new':'badge-done' ?>">
            <?= $fb['status']==='new'?'Baru':'Dibalas' ?>
          </span>
          <span style="font-size:10px;color:#94a3b8;margin-left:auto">
            <?= date('d M Y', strtotime($fb['created_at'])) ?>
          </span>
        </div>
        <div class="fb-subj"><?= h($fb['subject']) ?></div>
        <div class="fb-from">
          <i class="bi bi-person" style="font-size:10px"></i>
          <?= h($fb['sender_name']) ?> — <?= h(roleLabel($fb['sender_role'])) ?>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- DETAIL -->
  <div class="fb-panel">
    <div class="fb-panel-hdr">
      <i class="bi bi-chat-text-fill"></i> Detail & Balasan
    </div>
    <?php if (!$detailFb): ?>
    <div class="empty-state">
      <i class="bi bi-arrow-left" style="font-size:28px;display:block;margin-bottom:8px;opacity:.4"></i>
      <p style="font-size:13px">Pilih feedback dari daftar</p>
    </div>
    <?php else: ?>
    <div class="detail-body">
      <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
        <span class="<?= $detailFb['type']==='appreciation'?'badge-appr':'badge-conc' ?>" style="font-size:11px;padding:3px 10px">
          <?= $detailFb['type']==='appreciation'?'Apresiasi':'Perhatian / Masukan' ?>
        </span>
        <span class="<?= $detailFb['status']==='new'?'badge-new':'badge-done' ?>" style="font-size:11px;padding:3px 10px">
          <?= $detailFb['status']==='new'?'Belum Dibalas':'Sudah Dibalas' ?>
        </span>
      </div>

      <div class="detail-label">Subjek</div>
      <div style="font-size:15px;font-weight:500;color:#1e293b;margin-bottom:12px"><?= h($detailFb['subject']) ?></div>

      <div class="detail-label">Dari</div>
      <div style="font-size:13px;color:#475569;margin-bottom:12px">
        <i class="bi bi-person-fill me-1"></i>
        <?= h($detailFb['sender_name']) ?> (<?= h($detailFb['sender_email']) ?>)<br>
        <span style="font-size:11px;color:#94a3b8">
          <?= h(roleLabel($detailFb['sender_role'])) ?> · <?= date('d M Y H:i', strtotime($detailFb['created_at'])) ?>
        </span>
      </div>

      <div class="detail-label">Pesan</div>
      <div class="detail-msg"><?= nl2br(h($detailFb['message'])) ?></div>

      <?php if ($detailFb['status'] === 'responded'): ?>
      <div class="response-box">
        <div class="detail-label" style="color:#27500A">Balasan Admin</div>
        <div style="font-size:13px;color:#1e293b;line-height:1.7;margin-bottom:8px">
          <?= nl2br(h($detailFb['admin_response'])) ?>
        </div>
        <div style="font-size:11px;color:#64748b">
          Dibalas oleh <?= h($detailFb['responder_name'] ?? 'Admin') ?>
          · <?= date('d M Y H:i', strtotime($detailFb['responded_at'])) ?>
        </div>
      </div>
      <?php else: ?>
      <div class="reply-form">
        <div class="detail-label">Tulis Balasan</div>
        <form method="POST">
          <input type="hidden" name="reply_feedback" value="1">
          <input type="hidden" name="feedback_id" value="<?= $detailFb['id'] ?>">
          <textarea name="admin_response" rows="5"
            placeholder="Tulis balasan yang akan dikirim ke email <?= h($detailFb['sender_name']) ?>..."
            required minlength="5"></textarea>
          <div style="font-size:11px;color:#64748b;margin-top:4px">
            <i class="bi bi-envelope me-1"></i>
            Balasan akan dikirim ke <strong><?= h($detailFb['sender_email']) ?></strong>
          </div>
          <button type="submit" class="btn-reply">
            <i class="bi bi-send-fill"></i>Kirim Balasan
          </button>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php
$content = ob_get_clean();
pageWrapper('Feedback & Apresiasi', $content);
