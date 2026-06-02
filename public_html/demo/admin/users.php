<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['admin','foundation']);

// ── Handle Actions ────────────────────────────────────────────
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? 'teacher';
    $password = $_POST['password'] ?? '';
    $userId   = (int)($_POST['user_id'] ?? 0);

    if ($action === 'create') {
        if (!$name || !$email || !$password) {
            flash('Nama, email, dan password wajib diisi.', 'danger');
        } elseif (Database::fetchOne("SELECT id FROM users WHERE email=?", [$email])) {
            flash('Email sudah terdaftar.', 'danger');
        } else {
            Database::insert('users', [
                'name'                => $name,
                'email'               => $email,
                'password'            => password_hash($password, PASSWORD_BCRYPT),
                'role'                => $role,
                'class_id'            => ($role==='student' && !empty($_POST['class_id'])) ? (int)$_POST['class_id'] : null,
                'is_osis'             => ($role==='student' && !empty($_POST['is_osis'])) ? 1 : 0,
                'is_parent_committee' => ($role==='parent'  && !empty($_POST['is_parent_committee'])) ? 1 : 0,
            ]);
            flash("Pengguna $name berhasil ditambahkan.", 'success');
        }
    }

    if ($action === 'edit' && $userId) {
        $data = [
            'name'                => $name,
            'email'               => $email,
            'role'                => $role,
            'class_id'            => ($role==='student' && !empty($_POST['class_id'])) ? (int)$_POST['class_id'] : null,
            'is_osis'             => ($role==='student' && !empty($_POST['is_osis'])) ? 1 : 0,
            'is_parent_committee' => ($role==='parent'  && !empty($_POST['is_parent_committee'])) ? 1 : 0,
        ];
        if ($password) $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        Database::update('users', $data, 'id=?', [$userId]);
        flash("Data pengguna berhasil diperbarui.", 'success');
    }

    if ($action === 'toggle' && $userId) {
        $u = Database::fetchOne("SELECT is_active FROM users WHERE id=?", [$userId]);
        if ($u) {
            $newStatus = $u['is_active'] ? 0 : 1;
            Database::update('users', ['is_active' => $newStatus], 'id=?', [$userId]);
            flash('Status pengguna diperbarui.', 'info');
        }
    }

    header('Location: ' . APP_URL . '/admin/users.php');
    exit;
}

if ($action === 'delete' && isset($_GET['id'])) {
    $uid = (int)$_GET['id'];
    if ($uid !== $_SESSION['user_id']) {
        Database::query("DELETE FROM users WHERE id=?", [$uid]);
        flash('Pengguna dihapus.', 'warning');
    }
    header('Location: ' . APP_URL . '/admin/users.php');
    exit;
}

// ── Fetch users ───────────────────────────────────────────────
$search     = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$osisFilter   = $_GET['osis'] ?? '';

$where = 'WHERE 1=1';
$params = [];
if ($search) {
    $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($roleFilter) {
    $where .= ' AND u.role = ?';
    $params[] = $roleFilter;
}
if ($statusFilter !== '') {
    $where .= ' AND u.is_active = ?';
    $params[] = (int)$statusFilter;
}
if ($osisFilter === 'osis') {
    $where .= ' AND u.is_osis = 1';
} elseif ($osisFilter === 'komite') {
    $where .= ' AND u.is_parent_committee = 1';
}

$users = Database::fetchAll("
    SELECT u.*,
           (SELECT COUNT(*) FROM assignments a WHERE a.evaluator_id=u.id AND a.status='completed') as completed_surveys
    FROM users u $where
    ORDER BY FIELD(u.role,'admin','foundation','leader','teacher','student','parent'), u.name
", $params);

// Pre-load all classes to avoid N+1 query
$allClasses = [];
foreach (Database::fetchAll("SELECT id, name FROM classes") as $c) {
    $allClasses[$c['id']] = $c['name'];
}

// Edit target
$editUser = null;
if (isset($_GET['edit'])) {
    $editUser = Database::fetchOne("SELECT * FROM users WHERE id=?", [(int)$_GET['edit']]);
}

ob_start();
?>
<style>
.users-table { table-layout: fixed; width: 100%; }
.users-table th, .users-table td { vertical-align: middle; }
.users-table th:nth-child(1),.users-table td:nth-child(1){ width:22% }
.users-table th:nth-child(2),.users-table td:nth-child(2){ width:20% }
.users-table th:nth-child(3),.users-table td:nth-child(3){ width:16% }
.users-table th:nth-child(4),.users-table td:nth-child(4){ width:13% }
.users-table th:nth-child(5),.users-table td:nth-child(5){ width:7%; text-align:center }
.users-table th:nth-child(6),.users-table td:nth-child(6){ width:9%; text-align:center }
.users-table th:nth-child(7),.users-table td:nth-child(7){ width:13%; text-align:center }

/* DataTables controls padding fix */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
  padding: 12px 16px 8px;
}
.dataTables_wrapper .dataTables_filter { text-align: right; }
.dataTables_wrapper .dataTables_filter label { margin: 0; }
</style>

<!-- FILTER + ADD — horizontal layout -->
<div class="d-flex align-items-end gap-2 mb-4 flex-wrap">
  <div class="flex-grow-1" style="min-width:180px;max-width:280px">
    <label class="form-label small text-muted mb-1">Cari</label>
    <form method="GET" class="d-flex gap-2">
      <input type="text" name="search" class="form-control form-control-sm"
        placeholder="Nama atau email..." value="<?= h($search) ?>">
  </div>
  <div style="min-width:140px">
    <label class="form-label small text-muted mb-1">Peran</label>
    <select name="role" class="form-select form-select-sm">
      <option value="">Semua Peran</option>
      <?php foreach (ROLES as $key => $label): ?>
      <option value="<?= $key ?>" <?= $roleFilter===$key?'selected':'' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div style="min-width:120px">
    <label class="form-label small text-muted mb-1">Status</label>
    <select name="status" class="form-select form-select-sm">
      <option value="">Semua</option>
      <option value="1" <?= $statusFilter==='1'?'selected':'' ?>>Aktif</option>
      <option value="0" <?= $statusFilter==='0'?'selected':'' ?>>Nonaktif</option>
    </select>
  </div>
  <div style="min-width:130px">
    <label class="form-label small text-muted mb-1">Keanggotaan</label>
    <select name="osis" class="form-select form-select-sm">
      <option value="">Semua</option>
      <option value="osis" <?= $osisFilter==='osis'?'selected':'' ?>>OSIS</option>
      <option value="komite" <?= $osisFilter==='komite'?'selected':'' ?>>Komite Ortu</option>
    </select>
  </div>
  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-navy btn-sm">
      <i class="bi bi-search me-1"></i>Cari
    </button>
    <?php if ($search || $roleFilter || $statusFilter !== '' || $osisFilter): ?>
    <a href="?" class="btn btn-outline-secondary btn-sm">Reset</a>
    <?php endif; ?>
  </div>
  </form>

  <div class="ms-auto">
    <label class="form-label small text-muted mb-1 d-block">&nbsp;</label>
    <button class="btn btn-gold btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalUser">
      <i class="bi bi-person-plus me-1"></i>Tambah Pengguna
    </button>
  </div>
</div>

<?= showFlash() ?>

<!-- USERS TABLE -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-people-fill me-2"></i>Daftar Pengguna</span>
    <span class="badge bg-light text-dark"><?= count($users) ?> pengguna</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover data-table mb-0 users-table">
        <thead><tr>
          <th>Nama</th>
          <th>Email</th>
          <th>Peran</th>
          <th>Kelas / Keanggotaan</th>
          <th title="Jumlah kuesioner yang sudah diisi sebagai penilai">
            Diisi <i class="bi bi-info-circle" style="font-size:.65rem;opacity:.5"></i>
          </th>
          <th>Status</th>
          <th>Aksi</th>
        </tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar-sm flex-shrink-0"><?= h(avatarInitials($u['name'])) ?></div>
                <strong style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block">
                  <?= h($u['name']) ?>
                </strong>
              </div>
            </td>
            <td class="text-muted small" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= h($u['email']) ?>
            </td>
            <td>
              <span class="badge badge-navy" style="font-size:.7rem;white-space:normal;line-height:1.3">
                <?= h(roleLabel($u['role'])) ?>
              </span>
            </td>
            <td class="small">
              <?php if ($u['role']==='student'): ?>
                <?php $cname = $allClasses[$u['class_id'] ?? 0] ?? null; ?>
                <?= $cname ? "<span class='badge bg-info text-dark' style='font-size:.68rem'>".h($cname)."</span> " : "" ?>
                <?php if (!empty($u['is_osis'])): ?>
                <span class="badge bg-warning text-dark" style="font-size:.68rem">OSIS</span>
                <?php endif; ?>
                <?php if (!$cname && empty($u['is_osis'])): ?><span class="text-muted">—</span><?php endif; ?>
              <?php elseif ($u['role']==='parent' && !empty($u['is_parent_committee'])): ?>
                <span class="badge bg-success" style="font-size:.68rem">Komite</span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge bg-light text-dark border"><?= $u['completed_surveys'] ?></span>
            </td>
            <td>
              <?= $u['is_active']
                ? "<span class='badge bg-success' style='font-size:.7rem'>Aktif</span>"
                : "<span class='badge bg-secondary' style='font-size:.7rem'>Nonaktif</span>" ?>
            </td>
            <td>
              <div class="d-flex gap-1 justify-content-center">
                <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit data pengguna">
                  <i class="bi bi-pencil" style="font-size:.8rem"></i>
                </a>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button class="btn btn-sm btn-outline-warning py-0 px-2"
                    title="<?= $u['is_active']?'Nonaktifkan akun':'Aktifkan akun' ?>">
                    <i class="bi bi-<?= $u['is_active']?'toggle-on':'toggle-off' ?>" style="font-size:.8rem"></i>
                  </button>
                </form>
                <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                <a href="?action=delete&id=<?= $u['id'] ?>"
                   class="btn btn-sm btn-outline-danger py-0 px-2"
                   data-confirm="Hapus pengguna <?= h($u['name']) ?>?"
                   title="Hapus pengguna">
                  <i class="bi bi-trash" style="font-size:.8rem"></i>
                </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>


<!-- ADD/EDIT MODAL -->
<div class="modal fade" id="modalUser" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--ktb-navy);color:white">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>
          <?= $editUser ? 'Edit Pengguna' : 'Tambah Pengguna Baru' ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="<?= $editUser ? 'edit' : 'create' ?>">
        <?php if ($editUser): ?>
        <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
        <?php endif; ?>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required
              value="<?= h($editUser['name'] ?? '') ?>" placeholder="cth: Budi Santoso, S.Pd.">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" required
              value="<?= h($editUser['email'] ?? '') ?>" placeholder="nama@ktb.sch.id">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Peran <span class="text-danger">*</span></label>
            <select name="role" class="form-select" required id="roleSelect"
              onchange="toggleStudentFields(this.value)">
              <?php foreach (ROLES as $key => $label): ?>
              <option value="<?= $key ?>" <?= ($editUser['role']??'') === $key ? 'selected' : '' ?>>
                <?= h($label) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Khusus Siswa -->
          <div id="studentFields" style="display:none">
            <div class="mb-3">
              <label class="form-label fw-semibold small">Kelas</label>
              <?php
              $classes = Database::fetchAll("SELECT * FROM classes WHERE is_active=1 ORDER BY year_start DESC, name");
              ?>
              <select name="class_id" class="form-select">
                <option value="">— Pilih Kelas —</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>"
                  <?= ($editUser['class_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                  <?= h($c['name']) ?> (<?= $c['year_start'] ?>/<?= $c['year_start']+1 ?>)
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <div class="form-check form-switch">
                <input type="checkbox" name="is_osis" value="1"
                  class="form-check-input" id="isOsis"
                  <?= ($editUser['is_osis'] ?? 0) ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold small" for="isOsis">
                  Anggota OSIS / Komite Siswa
                  <span class="text-muted fw-normal">(dapat menilai Pimpinan Sekolah)</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Khusus Orang Tua -->
          <div id="parentFields" style="display:none">
            <div class="mb-3">
              <div class="form-check form-switch">
                <input type="checkbox" name="is_parent_committee" value="1"
                  class="form-check-input" id="isParentCommittee"
                  <?= ($editUser['is_parent_committee'] ?? 0) ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold small" for="isParentCommittee">
                  Anggota Komite Orang Tua
                  <span class="text-muted fw-normal">(dapat menilai Leader & Guru)</span>
                </label>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">
              Password <?= $editUser ? '<span class="text-muted small">(kosongkan jika tidak diubah)</span>' : '<span class="text-danger">*</span>' ?>
            </label>
            <input type="password" name="password" class="form-control"
              <?= $editUser ? '' : 'required' ?> placeholder="Min. 8 karakter">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-navy">
            <i class="bi bi-save me-1"></i><?= $editUser ? 'Simpan Perubahan' : 'Tambah Pengguna' ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($editUser): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  new bootstrap.Modal(document.getElementById('modalUser')).show();
  toggleStudentFields('<?= $editUser['role'] ?>');
});
</script>
<?php endif; ?>
<script>
function toggleStudentFields(role) {
  document.getElementById('studentFields').style.display = role === 'student' ? 'block' : 'none';
  document.getElementById('parentFields').style.display  = role === 'parent'  ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', () => {
  const role = document.getElementById('roleSelect');
  if (role) toggleStudentFields(role.value);
});
</script>

<?php $content = ob_get_clean(); pageWrapper('Kelola Pengguna', $content); ?>