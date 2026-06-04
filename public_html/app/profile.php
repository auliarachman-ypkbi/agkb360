<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

requireLogin();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $pw   = $_POST['password'] ?? '';
    $pw2  = $_POST['password2'] ?? '';

    if (!$name) {
        flash('Nama tidak boleh kosong.', 'danger');
    } elseif ($pw && $pw !== $pw2) {
        flash('Password baru tidak cocok.', 'danger');
    } elseif ($pw && strlen($pw) < 6) {
        flash('Password minimal 6 karakter.', 'danger');
    } else {
        $data = ['name' => $name];
        if ($pw) $data['password'] = password_hash($pw, PASSWORD_BCRYPT);
        Database::update('users', $data, 'id=?', [$user['id']]);
        $_SESSION['user_name'] = $name;
        flash('Profil berhasil diperbarui.', 'success');
        header('Location: ' . APP_URL . '/profile.php');
        exit;
    }
}

$stats = getUserStats($user['id']);
$period = getPeriod();
$recentActivity = Database::fetchAll("
    SELECT a.status, a.completed_at, p.name as pkg_name, u.name as evaluatee_name
    FROM assignments a
    JOIN packages p ON a.package_id = p.id
    JOIN users u ON a.evaluatee_id = u.id
    WHERE a.evaluator_id = ?
    ORDER BY a.created_at DESC LIMIT 5
", [$user['id']]);

ob_start(); ?>

<div class="row g-4">
  <div class="col-md-4">
    <!-- PROFILE CARD -->
    <div class="card text-center mb-4">
      <div class="card-body py-4">
        <div class="mx-auto mb-3 d-flex align-items-center justify-content-center"
          style="width:80px;height:80px;border-radius:50%;background:var(--ktb-navy);color:var(--ktb-gold);font-size:2rem;font-weight:700">
          <?= h(avatarInitials($user['name'])) ?>
        </div>
        <h5 class="fw-bold text-navy"><?= h($user['name']) ?></h5>
        <span class="badge badge-navy"><?= h(roleLabel($user['role'])) ?></span>
        <p class="text-muted small mt-2"><?= h($user['email']) ?></p>
        <?php if ($user['last_login']): ?>
        <p class="text-muted small">Login terakhir: <?= date('d M Y H:i', strtotime($user['last_login'])) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- STATS -->
    <div class="card">
      <div class="card-header"><i class="bi bi-clipboard-check me-2"></i>Statistik Saya</div>
      <div class="card-body">
        <div class="d-flex justify-content-between py-2 border-bottom">
          <span class="text-muted">Menunggu</span>
          <strong class="text-danger"><?= $stats['pending'] ?></strong>
        </div>
        <div class="d-flex justify-content-between py-2 border-bottom">
          <span class="text-muted">Sedang Diisi</span>
          <strong class="text-warning"><?= $stats['progress'] ?></strong>
        </div>
        <div class="d-flex justify-content-between py-2">
          <span class="text-muted">Selesai</span>
          <strong class="text-success"><?= $stats['completed'] ?></strong>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <!-- EDIT FORM -->
    <div class="card mb-4">
      <div class="card-header"><i class="bi bi-person-gear me-2"></i>Edit Profil</div>
      <div class="card-body">
        <?= showFlash() ?>
        <form method="POST">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nama Lengkap</label>
            <input type="text" name="name" class="form-control" value="<?= h($user['name']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Email</label>
            <input type="email" class="form-control" value="<?= h($user['email']) ?>" disabled>
            <div class="form-text">Email tidak dapat diubah. Hubungi admin jika perlu.</div>
          </div>
          <hr>
          <p class="fw-semibold small">Ubah Password <span class="text-muted fw-normal">(kosongkan jika tidak ingin mengubah)</span></p>
          <div class="row g-3">
            <div class="col-md-6">
              <input type="password" name="password" class="form-control" placeholder="Password baru">
            </div>
            <div class="col-md-6">
              <input type="password" name="password2" class="form-control" placeholder="Konfirmasi password baru">
            </div>
          </div>
          <button type="submit" class="btn btn-navy mt-3">
            <i class="bi bi-save me-1"></i>Simpan Perubahan
          </button>
        </form>
      </div>
    </div>

    <!-- RECENT ACTIVITY -->
    <div class="card">
      <div class="card-header"><i class="bi bi-clock-history me-2"></i>Aktivitas Terbaru</div>
      <div class="card-body p-0">
        <?php if (empty($recentActivity)): ?>
        <p class="text-muted text-center py-3">Belum ada aktivitas.</p>
        <?php else: ?>
        <table class="table table-hover mb-0 small">
          <thead><tr><th>Yang Dinilai</th><th>Paket</th><th>Status</th><th>Tanggal</th></tr></thead>
          <tbody>
            <?php foreach ($recentActivity as $a): ?>
            <tr>
              <td><?= h($a['evaluatee_name']) ?></td>
              <td><?= h($a['pkg_name']) ?></td>
              <td><?= statusBadge($a['status']) ?></td>
              <td><?= $a['completed_at'] ? date('d M Y', strtotime($a['completed_at'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php $content = ob_get_clean(); pageWrapper('Profil Saya', $content); ?>
