<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['admin','foundation']);

$tab    = $_GET['tab'] ?? 'classes';
$action = $_POST['action'] ?? '';

// ══════════════════════════════════════════════════════════════
// HANDLE ACTIONS
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── CRUD Kelas ────────────────────────────────────────────
    if ($action === 'create_class') {
        $name  = trim($_POST['name'] ?? '');
        $year  = (int)($_POST['year_start'] ?? date('Y'));
        $desc  = trim($_POST['description'] ?? '');
        if (!$name) { flash('Nama kelas wajib diisi.','danger'); }
        else {
            Database::insert('classes', ['name'=>$name,'year_start'=>$year,'description'=>$desc,'is_active'=>1]);
            flash("Kelas \"$name\" berhasil ditambahkan.", 'success');
        }
        header('Location: ' . APP_URL . '/admin/classes.php?tab=classes'); exit;
    }

    if ($action === 'edit_class') {
        $id   = (int)$_POST['class_id'];
        $name = trim($_POST['name'] ?? '');
        $year = (int)($_POST['year_start'] ?? date('Y'));
        $desc = trim($_POST['description'] ?? '');
        Database::update('classes', ['name'=>$name,'year_start'=>$year,'description'=>$desc], 'id=?', [$id]);
        flash('Kelas berhasil diperbarui.', 'success');
        header('Location: ' . APP_URL . '/admin/classes.php?tab=classes'); exit;
    }

    if ($action === 'toggle_class') {
        $id = (int)$_POST['class_id'];
        $c  = Database::fetchOne("SELECT is_active FROM classes WHERE id=?", [$id]);
        Database::update('classes', ['is_active' => $c['is_active'] ? 0 : 1], 'id=?', [$id]);
        flash('Status kelas diperbarui.', 'info');
        header('Location: ' . APP_URL . '/admin/classes.php?tab=classes'); exit;
    }

    if ($action === 'delete_class') {
        $id      = (int)$_POST['class_id'];
        $hasStud = Database::fetchOne("SELECT COUNT(*) c FROM users WHERE class_id=? AND role='student'", [$id])['c'];
        if ($hasStud > 0) {
            flash("Tidak bisa menghapus — ada $hasStud siswa di kelas ini.", 'danger');
        } else {
            Database::query("DELETE FROM teacher_classes WHERE class_id=?", [$id]);
            Database::query("DELETE FROM classes WHERE id=?", [$id]);
            flash('Kelas berhasil dihapus.', 'warning');
        }
        header('Location: ' . APP_URL . '/admin/classes.php?tab=classes'); exit;
    }

    // ── Mapping Guru → Kelas ──────────────────────────────────
    if ($action === 'save_teacher_mapping') {
        $teacher_id = (int)$_POST['teacher_id'];
        $class_ids  = $_POST['class_ids'] ?? [];

        Database::query("DELETE FROM teacher_classes WHERE teacher_id=?", [$teacher_id]);
        if (!empty($class_ids)) {
            $ins = Database::getInstance()->prepare(
                "INSERT IGNORE INTO teacher_classes (teacher_id, class_id) VALUES (?,?)"
            );
            foreach ($class_ids as $cid) {
                $ins->execute([$teacher_id, (int)$cid]);
            }
        }
        $t = Database::fetchOne("SELECT name FROM users WHERE id=?", [$teacher_id]);
        flash("Mapping kelas untuk {$t['name']} berhasil disimpan.", 'success');
        header('Location: ' . APP_URL . '/admin/classes.php?tab=mapping&teacher_id=' . $teacher_id); exit;
    }
}

// ── FETCH DATA ────────────────────────────────────────────────
$classes = Database::fetchAll("
    SELECT c.*,
        COUNT(DISTINCT u.id) as student_count,
        COUNT(DISTINCT tc.teacher_id) as teacher_count
    FROM classes c
    LEFT JOIN users u ON u.class_id = c.id AND u.role = 'student'
    LEFT JOIN teacher_classes tc ON tc.class_id = c.id
    GROUP BY c.id, c.name, c.year_start, c.description, c.is_active, c.created_at
    ORDER BY c.year_start DESC, c.name
");

$teachers = Database::fetchAll("
    SELECT u.id, u.name,
        COUNT(tc.class_id) as class_count
    FROM users u
    LEFT JOIN teacher_classes tc ON tc.teacher_id = u.id
    WHERE u.role = 'teacher' AND u.is_active = 1
    GROUP BY u.id, u.name
    ORDER BY u.name
");

// For mapping tab
$selectedTeacherId = (int)($_GET['teacher_id'] ?? 0);
$selectedTeacher   = $selectedTeacherId
    ? Database::fetchOne("SELECT * FROM users WHERE id=? AND role='teacher'", [$selectedTeacherId])
    : null;
$mappedClassIds = $selectedTeacherId
    ? array_column(Database::fetchAll("SELECT class_id FROM teacher_classes WHERE teacher_id=?", [$selectedTeacherId]), 'class_id')
    : [];

// Edit target
$editClass = isset($_GET['edit']) ? Database::fetchOne("SELECT * FROM classes WHERE id=?", [(int)$_GET['edit']]) : null;

ob_start(); ?>

<?= showFlash() ?>

<!-- TAB NAV -->
<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <a class="nav-link <?= $tab==='classes'?'active fw-bold':'' ?>" href="?tab=classes">
      <i class="bi bi-building me-1"></i>Kelola Kelas
      <span class="badge bg-secondary ms-1"><?= count($classes) ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab==='mapping'?'active fw-bold':'' ?>" href="?tab=mapping">
      <i class="bi bi-diagram-2 me-1"></i>Mapping Guru → Kelas
      <span class="badge bg-secondary ms-1"><?= count($teachers) ?></span>
    </a>
  </li>
</ul>

<?php // ══════════════════════════════════
// TAB: KELOLA KELAS
// ══════════════════════════════════ ?>
<?php if ($tab === 'classes'): ?>

<div class="row g-4">
  <!-- FORM -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header <?= $editClass ? 'gold' : '' ?>">
        <i class="bi bi-<?= $editClass ? 'pencil' : 'plus-circle' ?> me-2"></i>
        <?= $editClass ? 'Edit Kelas' : 'Tambah Kelas Baru' ?>
      </div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="<?= $editClass ? 'edit_class' : 'create_class' ?>">
          <?php if ($editClass): ?>
          <input type="hidden" name="class_id" value="<?= $editClass['id'] ?>">
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label fw-semibold small">Nama Kelas <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required
              placeholder="cth: XI IPA 1"
              value="<?= h($editClass['name'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Tahun Ajaran Mulai <span class="text-danger">*</span></label>
            <input type="number" name="year_start" class="form-control"
              min="2020" max="2035" placeholder="cth: 2024"
              value="<?= h($editClass['year_start'] ?? date('Y')) ?>">
            <div class="form-text">Tahun mulai masuk, cth: 2024 untuk TA 2024/2025</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Deskripsi</label>
            <input type="text" name="description" class="form-control"
              placeholder="cth: Kelas 11 IPA Rombel 1"
              value="<?= h($editClass['description'] ?? '') ?>">
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-navy">
              <i class="bi bi-save me-1"></i><?= $editClass ? 'Simpan' : 'Tambah Kelas' ?>
            </button>
            <?php if ($editClass): ?>
            <a href="?tab=classes" class="btn btn-outline-secondary">Batal</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <!-- INFO BOX -->
    <div class="card mt-3" style="border-left:4px solid var(--ktb-gold)">
      <div class="card-body py-3 small">
        <strong class="text-navy"><i class="bi bi-info-circle me-1"></i>Catatan:</strong>
        <ul class="mt-2 mb-0 text-muted">
          <li>Kelas digunakan untuk menentukan siswa mana yang menilai guru tertentu</li>
          <li>Setiap siswa hanya bisa berada di <strong>1 kelas</strong></li>
          <li>Guru bisa mengajar di <strong>beberapa kelas</strong></li>
          <li>Siswa OSIS menilai Leader, bukan berdasarkan kelas</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- LIST -->
  <div class="col-md-8">
    <?php
    // Group by year
    $byYear = [];
    foreach ($classes as $c) $byYear[$c['year_start']][] = $c;
    krsort($byYear);
    foreach ($byYear as $year => $classList):
    ?>
    <h6 class="fw-bold text-navy mb-2">
      <i class="bi bi-calendar3 me-1"></i>Tahun Ajaran <?= $year ?>/<?= $year+1 ?>
    </h6>
    <div class="row g-2 mb-4">
      <?php foreach ($classList as $c): ?>
      <div class="col-md-6">
        <div class="card border h-100 <?= !$c['is_active'] ? 'opacity-50' : '' ?>">
          <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <strong class="text-navy"><?= h($c['name']) ?></strong>
                <?php if (!$c['is_active']): ?>
                <span class="badge bg-secondary ms-1 small">Nonaktif</span>
                <?php endif; ?>
                <div class="small text-muted mt-1">
                  <i class="bi bi-people me-1"></i><?= $c['student_count'] ?> siswa &nbsp;
                  <i class="bi bi-mortarboard me-1"></i><?= $c['teacher_count'] ?> guru
                </div>
                <?php if ($c['description']): ?>
                <div class="text-muted" style="font-size:.75rem"><?= h($c['description']) ?></div>
                <?php endif; ?>
              </div>
              <div class="d-flex flex-column gap-1">
                <a href="?tab=classes&edit=<?= $c['id'] ?>"
                   class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="toggle_class">
                  <input type="hidden" name="class_id" value="<?= $c['id'] ?>">
                  <button class="btn btn-sm btn-outline-warning" title="Toggle aktif">
                    <i class="bi bi-toggle-<?= $c['is_active'] ? 'on' : 'off' ?>"></i>
                  </button>
                </form>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="delete_class">
                  <input type="hidden" name="class_id" value="<?= $c['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"
                    data-confirm="Hapus kelas <?= h($c['name']) ?>?">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php // ══════════════════════════════════
// TAB: MAPPING GURU → KELAS
// ══════════════════════════════════ ?>
<?php elseif ($tab === 'mapping'): ?>

<div class="row g-4">
  <!-- Guru selector -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-mortarboard me-2"></i>Pilih Guru</div>
      <div class="card-body p-0">
        <?php foreach ($teachers as $t):
          $active = $selectedTeacherId == $t['id'] ? 'bg-light border-start border-3 border-warning' : '';
        ?>
        <a href="?tab=mapping&teacher_id=<?= $t['id'] ?>"
           class="d-flex justify-content-between align-items-center px-3 py-2 text-decoration-none text-dark border-bottom <?= $active ?>">
          <div>
            <div class="small fw-semibold"><?= h($t['name']) ?></div>
          </div>
          <span class="badge <?= $t['class_count'] > 0 ? 'bg-success' : 'bg-secondary' ?>">
            <?= $t['class_count'] ?> kelas
          </span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Mapping editor -->
  <div class="col-md-8">
    <?php if (!$selectedTeacher): ?>
    <div class="card">
      <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-diagram-2 display-4 mb-3"></i>
        <p>Pilih guru dari daftar kiri untuk mengatur kelas yang diajarnya.</p>
        <p class="small">Siswa di kelas yang dipilih akan otomatis menjadi penilai guru tersebut.</p>
      </div>
    </div>
    <?php else: ?>
    <div class="card">
      <div class="card-header">
        <i class="bi bi-building me-2"></i>
        Kelas yang diajar: <strong><?= h($selectedTeacher['name']) ?></strong>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-4">
          <i class="bi bi-info-circle me-1"></i>
          Centang semua kelas yang diajar oleh guru ini.
          Siswa di kelas yang dicentang akan otomatis mendapat penugasan menilai guru ini.
        </p>

        <form method="POST">
          <input type="hidden" name="action" value="save_teacher_mapping">
          <input type="hidden" name="teacher_id" value="<?= $selectedTeacher['id'] ?>">

          <?php
          $byYear = [];
          foreach ($classes as $c) $byYear[$c['year_start']][] = $c;
          krsort($byYear);
          foreach ($byYear as $year => $classList):
          ?>
          <h6 class="fw-bold text-muted small text-uppercase mb-2">
            Tahun Ajaran <?= $year ?>/<?= $year+1 ?>
          </h6>
          <div class="row g-2 mb-4">
            <?php foreach ($classList as $c):
              $checked  = in_array($c['id'], $mappedClassIds) ? 'checked' : '';
              $disabled = !$c['is_active'] ? 'disabled' : '';
            ?>
            <div class="col-md-6">
              <label class="d-flex align-items-center gap-3 p-3 rounded border cursor-pointer
                <?= $checked ? 'border-warning bg-warning bg-opacity-10' : '' ?>
                <?= $disabled ? 'opacity-50' : '' ?>"
                style="cursor:<?= $disabled ? 'not-allowed' : 'pointer' ?>">
                <input type="checkbox" name="class_ids[]" value="<?= $c['id'] ?>"
                  class="form-check-input" <?= $checked ?> <?= $disabled ?>>
                <div>
                  <div class="fw-semibold"><?= h($c['name']) ?></div>
                  <div class="small text-muted">
                    <i class="bi bi-people me-1"></i><?= $c['student_count'] ?> siswa
                    <?php if (!$c['is_active']): ?>
                    <span class="badge bg-secondary">Nonaktif</span>
                    <?php endif; ?>
                  </div>
                </div>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>

          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-navy">
              <i class="bi bi-save me-1"></i>Simpan Mapping
            </button>
            <a href="?tab=mapping" class="btn btn-outline-secondary">Batal</a>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php endif; // end tabs ?>

<?php
$content = ob_get_clean();
pageWrapper('Manajemen Kelas & Mapping Guru', $content);
?>
