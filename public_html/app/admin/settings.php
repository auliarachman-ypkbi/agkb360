<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin','admin']);

// ── Save Settings ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    $keys = ['app_name','school_name','ai_enabled','default_language'];
    foreach ($keys as $k) {
        $val = trim($_POST[$k] ?? '');
        $exists = Database::fetchOne("SELECT setting_key FROM settings WHERE setting_key=?", [$k]);
        if ($exists) {
            Database::update('settings', ['setting_value'=>$val], 'setting_key=?', [$k]);
        } else {
            Database::insert('settings', ['setting_key'=>$k,'setting_value'=>$val]);
        }
    }
    flash('Pengaturan berhasil disimpan.', 'success');
    header('Location: ' . APP_URL . '/admin/settings.php');
    exit;
}

// ── Load Settings ─────────────────────────────────────────────
$settingsRaw = Database::fetchAll("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($settingsRaw as $s) $settings[$s['setting_key']] = $s['setting_value'];

// ── DB Stats ──────────────────────────────────────────────────
$dbStats = [
    'users'       => Database::fetchOne("SELECT COUNT(*) c FROM users")['c'],
    'assignments' => Database::fetchOne("SELECT COUNT(*) c FROM assignments")['c'],
    'responses'   => Database::fetchOne("SELECT COUNT(*) c FROM responses")['c'],
    'ai'          => Database::fetchOne("SELECT COUNT(*) c FROM ai_suggestions")['c'],
];

ob_start(); ?>

<?= showFlash() ?>

<div class="row g-4">

  <!-- GENERAL SETTINGS -->
  <div class="col-md-7">
    <div class="card mb-4">
      <div class="card-header"><i class="bi bi-sliders me-2"></i>Pengaturan Umum</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="save_settings">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nama Aplikasi</label>
            <input type="text" name="app_name" class="form-control"
              value="<?= h($settings['app_name'] ?? APP_NAME) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Nama Sekolah</label>
            <input type="text" name="school_name" class="form-control"
              value="<?= h($settings['school_name'] ?? APP_SCHOOL) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">AI Suggestion</label>
            <select name="ai_enabled" class="form-select">
              <option value="1" <?= ($settings['ai_enabled']??'1')==='1'?'selected':'' ?>>Aktif</option>
              <option value="0" <?= ($settings['ai_enabled']??'1')==='0'?'selected':'' ?>>Nonaktif</option>
            </select>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold">Bahasa Default</label>
            <select name="default_language" class="form-select">
              <option value="id" <?= ($settings['default_language']??'id')==='id'?'selected':'' ?>>Indonesia</option>
              <option value="en" <?= ($settings['default_language']??'id')==='en'?'selected':'' ?>>English</option>
            </select>
          </div>
          <button type="submit" class="btn btn-navy">
            <i class="bi bi-save me-1"></i>Simpan Pengaturan
          </button>
        </form>
      </div>
    </div>

    <!-- IMPORT / EXPORT -->
    <div class="card mb-4">
      <div class="card-header gold"><i class="bi bi-arrow-left-right me-2"></i>Import / Export Data</div>
      <div class="card-body">
        <p class="text-muted small mb-3">Export untuk backup atau migrasi ke server lain. Import untuk restore.</p>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <div class="card border h-100">
              <div class="card-body text-center py-3">
                <i class="bi bi-cloud-download display-5 text-primary mb-2"></i>
                <p class="small fw-semibold mb-2">Export Semua Data</p>
                <div class="d-flex flex-column gap-1">
                  <button class="btn btn-sm btn-outline-primary" onclick="exportData('full')">
                    <i class="bi bi-download me-1"></i>Full Export (JSON)
                  </button>
                  <button class="btn btn-sm btn-outline-secondary" onclick="exportData('users')">
                    <i class="bi bi-people me-1"></i>Users Only
                  </button>
                  <button class="btn btn-sm btn-outline-secondary" onclick="exportData('questions')">
                    <i class="bi bi-question-circle me-1"></i>Questions Only
                  </button>
                  <button class="btn btn-sm btn-outline-secondary" onclick="exportData('responses')">
                    <i class="bi bi-clipboard-data me-1"></i>Responses Only
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="card border h-100">
              <div class="card-body text-center py-3">
                <i class="bi bi-cloud-upload display-5 text-success mb-2"></i>
                <p class="small fw-semibold mb-2">Import Data</p>
                <div id="importArea">
                  <input type="file" id="importFile" accept=".json" class="d-none">
                  <label for="importFile" class="btn btn-outline-success btn-sm w-100 mb-1">
                    <i class="bi bi-upload me-1"></i>Pilih File JSON
                  </label>
                  <select id="importType" class="form-select form-select-sm mb-1">
                    <option value="full">Full Import</option>
                    <option value="users">Users Only</option>
                    <option value="questions">Questions Only</option>
                  </select>
                  <button class="btn btn-success btn-sm w-100" onclick="doImport()">
                    <i class="bi bi-play me-1"></i>Mulai Import
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT SIDE -->
  <div class="col-md-5">
    <!-- DB STATS -->
    <div class="card mb-4">
      <div class="card-header"><i class="bi bi-database me-2"></i>Status Database</div>
      <div class="card-body">
        <?php foreach ($dbStats as $label => $count): ?>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
          <span class="text-muted"><?= ucfirst($label) ?></span>
          <strong class="text-navy"><?= number_format($count) ?></strong>
        </div>
        <?php endforeach; ?>
        <div class="d-flex justify-content-between align-items-center py-2">
          <span class="text-muted">Versi Aplikasi</span>
          <strong><?= APP_VERSION ?></strong>
        </div>
      </div>
    </div>

    <!-- ACCOUNT INFO -->
    <div class="card mb-4">
      <div class="card-header"><i class="bi bi-key me-2"></i>Info Akun Demo</div>
      <div class="card-body small">
        <table class="table table-sm mb-0">
          <thead><tr><th>Peran</th><th>Email</th><th>Password</th></tr></thead>
          <tbody>
            <tr><td>Admin</td><td><code>admin@ktb.sch.id</code></td><td><code>Admin@KTB2025</code></td></tr>
            <tr><td>Yayasan</td><td><code>ahmad.fauzi@ypkbi.or.id</code></td><td><code>KTB2025!</code></td></tr>
            <tr><td>Kepsek</td><td><code>hendra.kusuma@ktb.sch.id</code></td><td><code>KTB2025!</code></td></tr>
            <tr><td>Guru</td><td><code>agus.pramono@ktb.sch.id</code></td><td><code>KTB2025!</code></td></tr>
            <tr><td>Siswa</td><td><code>student1@ktb.sch.id</code></td><td><code>KTB2025!</code></td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- HARD RESET -->
    <div class="card border-danger">
      <div class="card-header" style="background:#dc3545;color:white">
        <i class="bi bi-exclamation-triangle me-2"></i>Danger Zone
      </div>
      <div class="card-body">
        <p class="small text-muted mb-3">Hard Reset akan menghapus <strong>semua responses, assignments, dan AI suggestions</strong> — namun user, pertanyaan, dan struktur tetap ada.</p>
        <button class="btn btn-danger w-100" onclick="hardReset()">
          <i class="bi bi-arrow-counterclockwise me-2"></i>Hard Reset Data
        </button>
        <hr>
        <p class="small text-muted mb-2">Hapus seluruh database dan install ulang dari awal (termasuk data dummy).</p>
        <button class="btn btn-outline-danger w-100" onclick="fullReset()">
          <i class="bi bi-nuclear me-2"></i>Full Reinstall (Hapus Semua)
        </button>
      </div>
    </div>
  </div>
</div>

<script>
async function doImport() {
  const file = document.getElementById('importFile').files[0];
  const type = document.getElementById('importType').value;
  if (!file) { Swal.fire('Error', 'Pilih file JSON terlebih dahulu.', 'error'); return; }
  const result = await importData(file, type);
  if (result.success) {
    Swal.fire('Berhasil', result.message || 'Import selesai.', 'success').then(() => location.reload());
  } else {
    Swal.fire('Gagal', result.error || 'Import gagal.', 'error');
  }
}

async function fullReset() {
  const result = await Swal.fire({
    title: '💀 FULL REINSTALL',
    html: 'Ini akan <strong>MENGHAPUS SEMUA DATA</strong> termasuk user, pertanyaan, dan seluruh struktur, lalu install ulang dari awal.<br><br>Ketik <code>FULLRESET</code> untuk konfirmasi.',
    input: 'text', icon: 'error',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    confirmButtonText: 'Hapus Semua',
    preConfirm: (val) => {
      if (val !== 'FULLRESET') { Swal.showValidationMessage('Ketik FULLRESET'); return false; }
      return true;
    }
  });
  if (result.isConfirmed) {
    const res = await apiPost('/api/data.php', { action: 'full_reset' });
    if (res.success) {
      Swal.fire('Selesai', 'Mengarahkan ke setup...', 'success').then(() => {
        window.location = '/setup.php';
      });
    } else {
      Swal.fire('Error', res.error || 'Gagal', 'error');
    }
  }
}
</script>

<?php $content = ob_get_clean(); pageWrapper('Pengaturan Sistem', $content); ?>
