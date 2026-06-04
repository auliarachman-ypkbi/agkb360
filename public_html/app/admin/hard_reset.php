<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin']);

$action = $_POST['action'] ?? '';
$result = null;

// ── FETCH STATS ───────────────────────────────────────────────
function getStats(): array {
    return [
        'responses'   => Database::fetchOne("SELECT COUNT(*) c FROM responses")['c'],
        'assignments' => Database::fetchOne("SELECT COUNT(*) c FROM assignments")['c'],
        'periods'     => Database::fetchOne("SELECT COUNT(*) c FROM eval_periods")['c'],
        'period_pkgs' => Database::fetchOne("SELECT COUNT(*) c FROM packages WHERE period_id IS NOT NULL")['c'],
        'users'       => Database::fetchOne("SELECT COUNT(*) c FROM users WHERE role NOT IN ('superadmin','admin','tester')")['c'],
        'ai'          => Database::fetchOne("SELECT COUNT(*) c FROM ai_suggestions")['c'],
    ];
}

// ── ACTIONS ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = trim($_POST['confirm_text'] ?? '');

    // SOFT RESET — hapus responses, assignments, periods saja
    if ($action === 'soft_reset' && $confirm === 'RESET') {
        try {
            Database::query("DELETE FROM ai_suggestions");
            Database::query("DELETE FROM responses");
            Database::query("DELETE FROM assignments");
            Database::query("DELETE FROM period_evaluatees");
            Database::query("DELETE FROM period_evaluators");
            Database::query("DELETE FROM period_snapshots");
            Database::query("DELETE FROM package_questions WHERE package_id IN (SELECT id FROM packages WHERE period_id IS NOT NULL)");
            Database::query("DELETE FROM package_weights WHERE package_id IN (SELECT id FROM packages WHERE period_id IS NOT NULL)");
            Database::query("DELETE FROM packages WHERE period_id IS NOT NULL");
            Database::query("DELETE FROM eval_periods");
            Database::query("ALTER TABLE eval_periods AUTO_INCREMENT = 1");
            Database::query("ALTER TABLE assignments AUTO_INCREMENT = 1");
            Database::query("ALTER TABLE responses AUTO_INCREMENT = 1");
            $result = ['type'=>'success', 'msg'=>'Soft Reset berhasil — semua periode, penugasan, dan respons telah dihapus.'];
        } catch (Exception $e) {
            $result = ['type'=>'danger', 'msg'=>'Error: ' . $e->getMessage()];
        }
    }

    // HARD RESET — hapus semua termasuk user non-admin
    if ($action === 'hard_reset' && $confirm === 'HARDRESET') {
        try {
            Database::query("DELETE FROM ai_suggestions");
            Database::query("DELETE FROM responses");
            Database::query("DELETE FROM assignments");
            Database::query("DELETE FROM period_evaluatees");
            Database::query("DELETE FROM period_evaluators");
            Database::query("DELETE FROM period_snapshots");
            Database::query("DELETE FROM package_questions WHERE package_id IN (SELECT id FROM packages WHERE period_id IS NOT NULL)");
            Database::query("DELETE FROM package_weights WHERE package_id IN (SELECT id FROM packages WHERE period_id IS NOT NULL)");
            Database::query("DELETE FROM packages WHERE period_id IS NOT NULL");
            Database::query("DELETE FROM eval_periods");
            Database::query("DELETE FROM user_groups WHERE user_id IN (SELECT id FROM users WHERE role NOT IN ('superadmin','admin','tester','foundation'))");
            Database::query("DELETE FROM class_students WHERE student_id IN (SELECT id FROM users WHERE role='student')");
            Database::query("DELETE FROM class_teachers WHERE teacher_id IN (SELECT id FROM users WHERE role='teacher')");
            Database::query("DELETE FROM users WHERE role NOT IN ('superadmin','admin','tester','foundation')");
            Database::query("ALTER TABLE eval_periods AUTO_INCREMENT = 1");
            Database::query("ALTER TABLE assignments AUTO_INCREMENT = 1");
            Database::query("ALTER TABLE responses AUTO_INCREMENT = 1");
            $result = ['type'=>'success', 'msg'=>'Hard Reset berhasil — semua data operasional dan user non-admin telah dihapus.'];
        } catch (Exception $e) {
            $result = ['type'=>'danger', 'msg'=>'Error: ' . $e->getMessage()];
        }
    }
}

$stats = getStats();

ob_start(); ?>

<style>
.reset-card{border-radius:14px;overflow:hidden;margin-bottom:20px}
.reset-hdr{padding:14px 20px;font-weight:600;font-size:14px;display:flex;align-items:center;gap:10px}
.reset-body{padding:20px;background:#fff}
.stat-pill{display:inline-flex;align-items:center;gap:6px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:6px 12px;font-size:13px;margin:4px}
.stat-pill strong{color:#2C5282}
.impact-list{list-style:none;padding:0;margin:12px 0}
.impact-list li{padding:5px 0;font-size:13px;display:flex;align-items:center;gap:8px}
.impact-list li::before{content:'✕';color:#dc3545;font-weight:700}
.keep-list li::before{content:'✓';color:#198754;font-weight:700}
</style>

<?php if ($result): ?>
<div class="alert alert-<?= $result['type'] ?> d-flex align-items-center gap-2 mb-4">
  <i class="bi bi-<?= $result['type']==='success'?'check-circle-fill':'exclamation-triangle-fill' ?> fs-5"></i>
  <?= h($result['msg']) ?>
</div>
<?php endif; ?>

<!-- STATUS DATABASE -->
<div class="card mb-4" style="border-left:4px solid #2C5282">
  <div class="card-body">
    <h6 class="fw-bold text-navy mb-3"><i class="bi bi-database me-2"></i>Status Database Saat Ini</h6>
    <div>
      <span class="stat-pill"><i class="bi bi-clock-history text-warning"></i> <strong><?= number_format($stats['periods']) ?></strong> periode</span>
      <span class="stat-pill"><i class="bi bi-send text-primary"></i> <strong><?= number_format($stats['assignments']) ?></strong> penugasan</span>
      <span class="stat-pill"><i class="bi bi-clipboard-data text-success"></i> <strong><?= number_format($stats['responses']) ?></strong> respons</span>
      <span class="stat-pill"><i class="bi bi-people text-info"></i> <strong><?= number_format($stats['users']) ?></strong> user aktif</span>
      <span class="stat-pill"><i class="bi bi-box text-secondary"></i> <strong><?= number_format($stats['period_pkgs']) ?></strong> paket terkunci</span>
      <span class="stat-pill"><i class="bi bi-robot text-purple"></i> <strong><?= number_format($stats['ai']) ?></strong> AI suggestions</span>
    </div>
  </div>
</div>

<div class="row g-4">

  <!-- SOFT RESET -->
  <div class="col-md-6">
    <div class="reset-card border border-warning">
      <div class="reset-hdr" style="background:#fff3cd;color:#856404">
        <i class="bi bi-arrow-counterclockwise fs-5"></i>
        Soft Reset
      </div>
      <div class="reset-body">
        <p class="text-muted small mb-3">Hapus semua data operasional, pertahankan semua user dan struktur konten.</p>

        <p class="fw-semibold small mb-1">Yang akan dihapus:</p>
        <ul class="impact-list">
          <li>Semua periode evaluasi (<?= $stats['periods'] ?>)</li>
          <li>Semua penugasan (<?= number_format($stats['assignments']) ?>)</li>
          <li>Semua respons (<?= number_format($stats['responses']) ?>)</li>
          <li>Paket terkunci per periode (<?= $stats['period_pkgs'] ?>)</li>
          <li>AI suggestions (<?= $stats['ai'] ?>)</li>
        </ul>

        <p class="fw-semibold small mb-1">Yang dipertahankan:</p>
        <ul class="impact-list keep-list">
          <li>Semua user (termasuk guru, siswa, ortu)</li>
          <li>Domain, Standard, Trait, Matrix</li>
          <li>Paket template (draft kuesioner)</li>
          <li>Kelas & mapping guru-murid</li>
        </ul>

        <form method="POST" onsubmit="return validateForm(this, 'RESET')">
          <input type="hidden" name="action" value="soft_reset">
          <div class="mb-2">
            <label class="form-label small fw-semibold">Ketik <code>RESET</code> untuk konfirmasi:</label>
            <input type="text" name="confirm_text" class="form-control form-control-sm"
              placeholder="RESET" autocomplete="off" required>
          </div>
          <button type="submit" class="btn btn-warning w-100 fw-semibold">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Jalankan Soft Reset
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- HARD RESET -->
  <div class="col-md-6">
    <div class="reset-card border border-danger">
      <div class="reset-hdr" style="background:#dc3545;color:white">
        <i class="bi bi-radiation fs-5"></i>
        Hard Reset
      </div>
      <div class="reset-body">
        <p class="text-muted small mb-3">Hapus semua data operasional <strong>dan</strong> semua user non-admin. Platform kembali ke kondisi siap deploy.</p>

        <p class="fw-semibold small mb-1">Yang akan dihapus:</p>
        <ul class="impact-list">
          <li>Semua periode, penugasan, respons</li>
          <li>Semua user (leader, teacher, student, parent)</li>
          <li>Mapping kelas-guru-murid</li>
          <li>Paket terkunci per periode</li>
          <li>AI suggestions</li>
        </ul>

        <p class="fw-semibold small mb-1">Yang dipertahankan:</p>
        <ul class="impact-list keep-list">
          <li>Superadmin, Admin, Tester</li>
          <li>Domain, Standard, Trait, Matrix</li>
          <li>Paket template (draft kuesioner)</li>
          <li>Kelas (tanpa mapping)</li>
        </ul>

        <form method="POST" onsubmit="return validateForm(this, 'HARDRESET')">
          <input type="hidden" name="action" value="hard_reset">
          <div class="mb-2">
            <label class="form-label small fw-semibold">Ketik <code>HARDRESET</code> untuk konfirmasi:</label>
            <input type="text" name="confirm_text" class="form-control form-control-sm"
              placeholder="HARDRESET" autocomplete="off" required>
          </div>
          <button type="submit" class="btn btn-danger w-100 fw-semibold">
            <i class="bi bi-radiation me-1"></i>Jalankan Hard Reset
          </button>
        </form>
      </div>
    </div>
  </div>

</div>

<div class="alert alert-secondary mt-4 small">
  <i class="bi bi-shield-lock me-2"></i>
  Halaman ini hanya dapat diakses oleh <strong>Superadmin</strong>.
  Semua tindakan di sini bersifat <strong>permanen dan tidak dapat dibatalkan</strong>.
</div>

<script>
function validateForm(form, expected) {
  const val = form.querySelector('[name="confirm_text"]').value.trim();
  if (val !== expected) {
    alert('Teks konfirmasi tidak sesuai. Ketik ' + expected + ' dengan benar.');
    return false;
  }
  return confirm('Yakin ingin melanjutkan? Tindakan ini tidak dapat dibatalkan!');
}
</script>

<?php
$content = ob_get_clean();
pageWrapper('Hard Reset', $content);
