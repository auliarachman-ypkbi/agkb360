<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
$user     = currentUser();
$assignId = (int)($_GET['assign'] ?? 0);

// Ambil info assignment
$assignment = $assignId ? Database::fetchOne("
    SELECT a.*, u.name as evaluatee_name, p.pkg_name, p.code as pkg_code
    FROM assignments a
    JOIN users u ON u.id = a.evaluatee_id
    JOIN packages p ON p.id = a.package_id
    WHERE a.id = ? AND a.evaluator_id = ?
", [$assignId, $user['id']]) : null;

// Hitung sisa kuesioner
$remaining = Database::fetchOne("
    SELECT COUNT(*) c FROM assignments
    WHERE evaluator_id = ? AND status IN ('pending','in_progress')
", [$user['id']])['c'] ?? 0;

ob_start(); ?>

<style>
.done-wrap{max-width:580px;margin:0 auto;text-align:center;padding:20px 0}
.done-icon{width:72px;height:72px;border-radius:50%;background:#EAF3DE;display:flex;align-items:center;justify-content:center;margin:0 auto 20px}
.done-icon i{font-size:36px;color:#3B6D11}
.done-title{font-size:22px;font-weight:600;color:#1e293b;margin-bottom:8px}
.done-sub{font-size:14px;color:#64748b;line-height:1.7;margin-bottom:28px}
.nudge-card{background:#fff;border:1.5px solid #ffc901;border-radius:14px;padding:24px;margin-bottom:20px;text-align:left}
.nudge-hdr{display:flex;align-items:center;gap:12px;margin-bottom:12px}
.nudge-icon{width:44px;height:44px;border-radius:10px;background:#fff3cd;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.nudge-icon i{font-size:22px;color:#854F0B}
.nudge-title{font-size:15px;font-weight:600;color:#1e293b}
.nudge-desc{font-size:13px;color:#64748b;line-height:1.6}
.nudge-desc{font-size:13px;color:#64748b;line-height:1.6;margin-bottom:16px}
.btn-feedback{display:inline-flex;align-items:center;gap:8px;padding:11px 24px;background:#2C5282;color:white;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;transition:background .15s}
.btn-feedback:hover{background:#1A365D;color:white}
.btn-skip{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;background:transparent;color:#64748b;border:1px solid #e2e8f0;border-radius:10px;text-decoration:none;font-size:13px;margin-left:10px;transition:all .15s}
.btn-skip:hover{background:#f8fafc;color:#475569}
.remaining-badge{display:inline-flex;align-items:center;gap:6px;background:#E6F1FB;color:#0C447C;border:0.5px solid #B5D4F4;border-radius:20px;padding:4px 12px;font-size:12px;margin-bottom:20px}
</style>

<div class="done-wrap">
  <div class="done-icon">
    <i class="bi bi-check-lg"></i>
  </div>
  <div class="done-title">Kuesioner berhasil dikumpulkan!</div>
  <div class="done-sub">
    Terima kasih telah menyelesaikan penilaian.
    <?php if ($assignment): ?>
    Jawaban Anda untuk <strong><?= h($assignment['evaluatee_name']) ?></strong> telah tersimpan.
    <?php endif; ?>
  </div>

  <?php if ($remaining > 0): ?>
  <div class="remaining-badge">
    <i class="bi bi-clipboard-check"></i>
    Masih ada <strong><?= $remaining ?> kuesioner</strong> yang perlu diisi
  </div>
  <?php endif; ?>

  <!-- NUDGE FEEDBACK -->
  <div class="nudge-card">
    <div class="nudge-hdr">
      <div class="nudge-icon">
        <i class="bi bi-chat-heart-fill"></i>
      </div>
      <div>
        <div class="nudge-title">Punya apresiasi atau masukan?</div>
      </div>
    </div>
    <p class="nudge-desc">
      Selain evaluasi periodik, Anda dapat menyampaikan apresiasi atas kontribusi positif
      atau masukan konstruktif kapan saja sepanjang tahun kepada Tim Yayasan Kader Bangsa.
      Suara Anda penting untuk perbaikan bersama.
    </p>
    <div>
      <a href="<?= APP_URL ?>/feedback/" class="btn-feedback">
        <i class="bi bi-chat-heart-fill"></i>Ya, saya mau sampaikan
      </a>
      <a href="<?= APP_URL ?>/survey/" class="btn-skip">
        <i class="bi bi-arrow-right"></i>Lanjut kuesioner lain
      </a>
    </div>
  </div>

  <div style="margin-top:8px">
    <a href="<?= APP_URL ?>/dashboard/" style="font-size:13px;color:#94a3b8;text-decoration:none">
      <i class="bi bi-house me-1"></i>Kembali ke Dashboard
    </a>
  </div>
</div>

<?php
$content = ob_get_clean();
pageWrapper('Kuesioner Selesai', $content);
