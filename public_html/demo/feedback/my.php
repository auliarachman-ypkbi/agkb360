<?php
// AGKB 360° — Daftar laporan milik pengguna
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
$user = currentUser();

$filter = $_GET['status'] ?? 'aktif';
$cond   = match ($filter) {
    'aktif'   => "AND t.status IN ('baru','ditinjau','ditindaklanjuti','menunggu_pelapor')",
    'selesai' => "AND t.status IN ('selesai','ditutup')",
    default   => '',
};

$tickets = Database::fetchAll(
    "SELECT t.*, c.name AS category_name
     FROM feedback_tickets t
     LEFT JOIN feedback_categories c ON c.id = t.category_id
     WHERE t.sender_id = ? $cond
     ORDER BY t.created_at DESC", [$user['id']]);

$counts = Database::fetchOne(
    "SELECT
       COUNT(*) AS semua,
       SUM(status IN ('baru','ditinjau','ditindaklanjuti','menunggu_pelapor')) AS aktif,
       SUM(status IN ('selesai','ditutup')) AS selesai
     FROM feedback_tickets WHERE sender_id = ?", [$user['id']]);

ob_start(); ?>
<style>
.tk-wrap{max-width:860px;margin:0 auto}
.tk-filters{display:flex;gap:7px;margin-bottom:16px;flex-wrap:wrap}
.tk-filter{padding:5px 14px;border-radius:20px;font-size:12.5px;border:1px solid #e3e5ea;background:#fff;color:#6b6a83;text-decoration:none;font-weight:500}
.tk-filter.active{background:#040136;color:#fff;border-color:#040136}
.tk-filter:hover{text-decoration:none;border-color:#cdd0d8}
.tk-item{display:block;background:#fff;border:1px solid #e3e5ea;border-radius:12px;padding:15px 18px;margin-bottom:10px;text-decoration:none;transition:all .14s}
.tk-item:hover{text-decoration:none;border-color:#cdd0d8;transform:translateY(-1px);box-shadow:var(--agkb-shadow-sm)}
.tk-top{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:7px}
.tk-no{font-size:11.5px;font-weight:700;color:#030870;letter-spacing:.03em}
.tk-subj{font-size:14.5px;font-weight:600;color:#040136;margin-bottom:4px;line-height:1.4}
.tk-meta{font-size:11.5px;color:#6b6a83}
.tk-empty{text-align:center;padding:52px 20px;color:#6f6e85;background:#fff;border:1px solid #e3e5ea;border-radius:12px}
</style>

<div class="tk-wrap">
  <div class="tk-filters">
    <a href="?status=aktif"  class="tk-filter <?= $filter==='aktif'?'active':'' ?>">Sedang Berjalan (<?= (int)$counts['aktif'] ?>)</a>
    <a href="?status=selesai" class="tk-filter <?= $filter==='selesai'?'active':'' ?>">Selesai (<?= (int)$counts['selesai'] ?>)</a>
    <a href="?status=semua"  class="tk-filter <?= $filter==='semua'?'active':'' ?>">Semua (<?= (int)$counts['semua'] ?>)</a>
    <a href="<?= APP_URL ?>/feedback/" class="tk-filter ms-auto" style="background:#ff9101;color:#040136;border-color:#ff9101">
      <i class="bi bi-plus-lg me-1"></i>Kirim Baru
    </a>
  </div>

  <?php if (!$tickets): ?>
  <div class="tk-empty">
    <i class="bi bi-inbox" style="font-size:34px;display:block;margin-bottom:10px;opacity:.35"></i>
    <div style="font-size:14px">Belum ada laporan pada filter ini.</div>
    <a href="<?= APP_URL ?>/feedback/" class="btn btn-navy btn-sm mt-3 px-3">Kirim Feedback Pertama</a>
  </div>
  <?php else: foreach ($tickets as $t): ?>
  <a href="<?= APP_URL ?>/feedback/view.php?id=<?= $t['id'] ?>" class="tk-item">
    <div class="tk-top">
      <span class="tk-no"><?= h($t['ticket_no']) ?></span>
      <?= fbBadgeTrack($t['track']) ?>
      <?= fbBadgeStatus($t['status']) ?>
      <?= fbBadgeOverdue($t) ?>
      <span class="tk-meta ms-auto"><?= fbRelTime($t['created_at']) ?></span>
    </div>
    <div class="tk-subj"><?= h($t['subject']) ?></div>
    <div class="tk-meta">
      <i class="bi bi-tag me-1"></i><?= h($t['category_name'] ?? 'Tanpa kategori') ?>
      <?php if ($t['track'] !== 'apresiasi' && $t['due_at'] && !in_array($t['status'],['selesai','ditutup'],true)): ?>
        · <i class="bi bi-clock me-1"></i>Target <?= date('d M Y', strtotime($t['due_at'])) ?>
      <?php elseif ($t['resolution_type']): ?>
        · <i class="bi bi-check2-circle me-1"></i><?= h(fbResolutions()[$t['resolution_type']] ?? '') ?>
      <?php endif; ?>
    </div>
  </a>
  <?php endforeach; endif; ?>
</div>

<?php
$content = ob_get_clean();
pageWrapper('Laporan Saya', $content);
