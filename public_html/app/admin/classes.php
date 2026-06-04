<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin','admin','foundation']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$tab    = $_GET['tab'] ?? 'classes';

// ── ACTIONS ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Buat kelas baru
    if ($action === 'create_class') {
        $name  = trim($_POST['name'] ?? '');
        $year  = (int)($_POST['year_start'] ?? date('Y'));
        $desc  = trim($_POST['description'] ?? '');
        if (!$name) { flash('Nama kelas wajib diisi.', 'danger'); }
        else {
            Database::insert('classes', ['name'=>$name,'year_start'=>$year,'description'=>$desc,'is_active'=>1]);
            flash("Kelas $name berhasil dibuat.", 'success');
        }
        header('Location: '.APP_URL.'/admin/classes.php?tab=classes'); exit;
    }

    // Hapus kelas
    if ($action === 'delete_class') {
        $cid = (int)$_POST['class_id'];
        Database::query("DELETE FROM classes WHERE id=?", [$cid]);
        flash('Kelas berhasil dihapus.', 'warning');
        header('Location: '.APP_URL.'/admin/classes.php?tab=classes'); exit;
    }

    // Simpan mapping guru → kelas
    if ($action === 'map_teacher') {
        $tid     = (int)$_POST['teacher_id'];
        $classIds = $_POST['class_ids'] ?? [];
        Database::query("DELETE FROM class_teachers WHERE teacher_id=?", [$tid]);
        foreach ($classIds as $cid) {
            Database::query("INSERT IGNORE INTO class_teachers (class_id, teacher_id) VALUES (?,?)", [(int)$cid, $tid]);
        }
        flash('Mapping guru berhasil disimpan.', 'success');
        header('Location: '.APP_URL.'/admin/classes.php?tab=teachers&tid='.$tid); exit;
    }

    // Simpan mapping murid → kelas
    if ($action === 'map_students') {
        $cid        = (int)$_POST['class_id'];
        $studentIds = $_POST['student_ids'] ?? [];
        Database::query("DELETE FROM class_students WHERE class_id=?", [$cid]);
        foreach ($studentIds as $sid) {
            Database::query("INSERT IGNORE INTO class_students (class_id, student_id) VALUES (?,?)", [$cid, (int)$sid]);
        }
        flash('Mapping murid berhasil disimpan.', 'success');
        header('Location: '.APP_URL.'/admin/classes.php?tab=students&cid='.$cid); exit;
    }
}

// ── FETCH DATA ────────────────────────────────────────────────
$classes  = Database::fetchAll("SELECT * FROM classes ORDER BY year_start DESC, name");
$teachers = Database::fetchAll("SELECT id, name FROM users WHERE role='teacher' AND is_active=1 ORDER BY name");
$students = Database::fetchAll("SELECT id, name FROM users WHERE role='student' AND is_active=1 ORDER BY name");

// Untuk tab guru
$selTeacher = (int)($_GET['tid'] ?? 0);
$teacherClassIds = [];
if ($selTeacher) {
    $rows = Database::fetchAll("SELECT class_id FROM class_teachers WHERE teacher_id=?", [$selTeacher]);
    $teacherClassIds = array_column($rows, 'class_id');
}

// Untuk tab murid
$selClass = (int)($_GET['cid'] ?? 0);
$classStudentIds = [];
if ($selClass) {
    $rows = Database::fetchAll("SELECT student_id FROM class_students WHERE class_id=?", [$selClass]);
    $classStudentIds = array_column($rows, 'student_id');
}

// Hitung jumlah guru & murid per kelas
$classStats = [];
foreach ($classes as $c) {
    $classStats[$c['id']] = [
        'teachers' => Database::fetchOne("SELECT COUNT(*) c FROM class_teachers WHERE class_id=?", [$c['id']])['c'],
        'students' => Database::fetchOne("SELECT COUNT(*) c FROM class_students WHERE class_id=?", [$c['id']])['c'],
    ];
}

ob_start();
?>

<style>
.tab-pill{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap}
.tab-pill a{padding:6px 18px;border-radius:20px;font-size:13px;font-weight:500;text-decoration:none;border:1px solid #e2e8f0;color:#64748b;background:#fff;transition:all .15s}
.tab-pill a.active{background:#2C5282;color:#fff;border-color:#2C5282}
.tab-pill a:hover:not(.active){background:#f1f5f9;border-color:#2C5282;color:#2C5282}
.teacher-card{border:1px solid #e2e8f0;border-radius:10px;padding:10px 14px;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:10px;text-decoration:none;color:#1e293b;background:#fff}
.teacher-card:hover{border-color:#2C5282;background:#f8fafc;color:#1e293b}
.teacher-card.active{border-color:#2C5282;background:#EBF4FF}
.class-check{display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:6px;background:#fff;transition:background .12s}
.class-check:has(input:checked){background:#EBF4FF;border-color:#2C5282}
.student-check{display:flex;align-items:center;gap:10px;padding:6px 12px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:5px;background:#fff;transition:background .12s}
.student-check:has(input:checked){background:#EBF4FF;border-color:#2C5282}
</style>

<!-- TAB NAVIGATION -->
<div class="tab-pill">
  <a href="?tab=classes"  class="<?= $tab==='classes' ?'active':'' ?>"><i class="bi bi-building me-1"></i>Kelola Kelas</a>
  <a href="?tab=teachers" class="<?= $tab==='teachers'?'active':'' ?>"><i class="bi bi-person-badge me-1"></i>Mapping Guru</a>
  <a href="?tab=students" class="<?= $tab==='students'?'active':'' ?>"><i class="bi bi-people me-1"></i>Mapping Murid</a>
</div>

<?= showFlash() ?>

<?php // ── TAB 1: KELOLA KELAS ────────────────────────────────
if ($tab === 'classes'): ?>

<div class="row g-3">
  <!-- Daftar Kelas -->
  <div class="col-md-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-building me-2"></i>Daftar Kelas</span>
        <button class="btn btn-sm btn-navy" data-bs-toggle="modal" data-bs-target="#modalAddClass">
          <i class="bi bi-plus me-1"></i>Tambah Kelas
        </button>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead><tr>
            <th>Nama Kelas</th>
            <th>Tahun Ajaran</th>
            <th class="text-center">Guru</th>
            <th class="text-center">Murid</th>
            <th class="text-center">Aksi</th>
          </tr></thead>
          <tbody>
          <?php foreach ($classes as $c): ?>
          <tr>
            <td class="fw-semibold"><?= h($c['name']) ?></td>
            <td class="text-muted small"><?= $c['year_start'] ?>/<?= $c['year_start']+1 ?></td>
            <td class="text-center">
              <span class="badge bg-light text-dark border"><?= $classStats[$c['id']]['teachers'] ?></span>
            </td>
            <td class="text-center">
              <span class="badge bg-light text-dark border"><?= $classStats[$c['id']]['students'] ?></span>
            </td>
            <td class="text-center">
              <div class="d-flex gap-1 justify-content-center">
                <a href="?tab=teachers&cid=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Mapping guru">
                  <i class="bi bi-person-badge" style="font-size:.8rem"></i>
                </a>
                <a href="?tab=students&cid=<?= $c['id'] ?>" class="btn btn-sm btn-outline-success py-0 px-2" title="Mapping murid">
                  <i class="bi bi-people" style="font-size:.8rem"></i>
                </a>
                <form method="POST" class="d-inline"
                  onsubmit="return confirm('Hapus kelas <?= h($c['name']) ?>? Semua mapping akan terhapus.')">
                  <input type="hidden" name="action" value="delete_class">
                  <input type="hidden" name="class_id" value="<?= $c['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger py-0 px-2">
                    <i class="bi bi-trash" style="font-size:.8rem"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($classes)): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">Belum ada kelas</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Info -->
  <div class="col-md-4">
    <div class="card" style="border-left:3px solid #2C5282">
      <div class="card-body">
        <h6 class="fw-bold text-navy mb-3">Panduan</h6>
        <p class="small text-muted mb-2">
          <i class="bi bi-person-badge text-primary me-1"></i>
          Klik ikon <strong>guru</strong> untuk mapping guru ke kelas ini.
        </p>
        <p class="small text-muted mb-2">
          <i class="bi bi-people text-success me-1"></i>
          Klik ikon <strong>murid</strong> untuk mapping murid ke kelas ini.
        </p>
        <p class="small text-muted mb-0">
          <i class="bi bi-info-circle text-warning me-1"></i>
          Satu murid bisa ada di beberapa kelas.
        </p>
      </div>
    </div>
  </div>
</div>

<!-- Modal Tambah Kelas -->
<div class="modal fade" id="modalAddClass" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--ktb-navy);color:white">
        <h6 class="modal-title"><i class="bi bi-building me-2"></i>Tambah Kelas Baru</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="create_class">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nama Kelas <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required placeholder="cth: X IPA 1">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Tahun Ajaran Mulai</label>
            <input type="number" name="year_start" class="form-control" value="<?= date('Y') ?>" placeholder="cth: 2024">
            <div class="form-text">Tahun awal, misal 2024 untuk TA 2024/2025</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Deskripsi</label>
            <input type="text" name="description" class="form-control" placeholder="Opsional">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-navy"><i class="bi bi-save me-1"></i>Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php // ── TAB 2: MAPPING GURU ────────────────────────────────
elseif ($tab === 'teachers'): ?>

<div class="row g-3">
  <!-- Daftar Guru -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-person-badge me-2"></i>Pilih Guru</div>
      <div class="card-body p-2" style="max-height:500px;overflow-y:auto">
        <?php foreach ($teachers as $t): ?>
        <a href="?tab=teachers&tid=<?= $t['id'] ?>"
           class="teacher-card mb-2 <?= $selTeacher===$t['id']?'active':'' ?>">
          <div style="width:32px;height:32px;border-radius:50%;background:#2C5282;color:white;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;flex-shrink:0">
            <?= strtoupper(substr($t['name'],0,2)) ?>
          </div>
          <span class="small fw-500"><?= h($t['name']) ?></span>
          <?php
          $cnt = Database::fetchOne("SELECT COUNT(*) c FROM class_teachers WHERE teacher_id=?", [$t['id']])['c'];
          ?>
          <?php if ($cnt > 0): ?>
          <span class="badge bg-primary ms-auto" style="font-size:.65rem"><?= $cnt ?> kelas</span>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Mapping Kelas -->
  <div class="col-md-8">
    <?php if ($selTeacher): ?>
    <?php $selT = array_filter($teachers, fn($t)=>$t['id']===$selTeacher); $selT = reset($selT); ?>
    <div class="card">
      <div class="card-header">
        <i class="bi bi-grid me-2"></i>Kelas untuk <strong><?= h($selT['name'] ?? '') ?></strong>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="map_teacher">
        <input type="hidden" name="teacher_id" value="<?= $selTeacher ?>">
        <div class="card-body">
          <p class="small text-muted mb-3">Centang kelas yang diajar oleh guru ini:</p>
          <div class="row g-2">
            <?php foreach ($classes as $c): ?>
            <div class="col-md-6">
              <label class="class-check w-100">
                <input type="checkbox" name="class_ids[]" value="<?= $c['id'] ?>"
                  <?= in_array($c['id'], $teacherClassIds)?'checked':'' ?>>
                <div>
                  <div class="fw-semibold small"><?= h($c['name']) ?></div>
                  <div class="text-muted" style="font-size:.72rem">TA <?= $c['year_start'] ?>/<?= $c['year_start']+1 ?></div>
                </div>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
          <span class="small text-muted">Klik simpan untuk menyimpan perubahan</span>
          <button type="submit" class="btn btn-navy btn-sm">
            <i class="bi bi-save me-1"></i>Simpan Mapping
          </button>
        </div>
      </form>
    </div>
    <?php else: ?>
    <div class="card">
      <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-arrow-left-circle display-4 d-block mb-2"></i>
        <p>Pilih guru di sebelah kiri untuk mengatur mapping kelasnya</p>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php // ── TAB 3: MAPPING MURID ────────────────────────────────
elseif ($tab === 'students'): ?>

<div class="row g-3">
  <!-- Pilih Kelas -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-building me-2"></i>Pilih Kelas</div>
      <div class="card-body p-2" style="max-height:500px;overflow-y:auto">
        <?php foreach ($classes as $c): ?>
        <?php $cnt = $classStats[$c['id']]['students']; ?>
        <a href="?tab=students&cid=<?= $c['id'] ?>"
           class="teacher-card mb-2 <?= $selClass===$c['id']?'active':'' ?>">
          <div style="width:32px;height:32px;border-radius:8px;background:#2C5282;color:white;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;flex-shrink:0">
            <i class="bi bi-building" style="font-size:.8rem"></i>
          </div>
          <div>
            <div class="small fw-semibold"><?= h($c['name']) ?></div>
            <div class="text-muted" style="font-size:.7rem">TA <?= $c['year_start'] ?>/<?= $c['year_start']+1 ?></div>
          </div>
          <?php if ($cnt > 0): ?>
          <span class="badge bg-success ms-auto" style="font-size:.65rem"><?= $cnt ?> murid</span>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Mapping Murid -->
  <div class="col-md-8">
    <?php if ($selClass): ?>
    <?php $selC = array_filter($classes, fn($c)=>$c['id']===$selClass); $selC = reset($selC); ?>
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-people me-2"></i>Murid di <strong><?= h($selC['name'] ?? '') ?></strong></span>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(true)">
            Pilih Semua
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)">
            Hapus Semua
          </button>
        </div>
      </div>
      <form method="POST" id="formStudents">
        <input type="hidden" name="action" value="map_students">
        <input type="hidden" name="class_id" value="<?= $selClass ?>">
        <div class="card-body" style="max-height:380px;overflow-y:auto">
          <!-- Search -->
          <input type="text" id="studentSearch" class="form-control form-control-sm mb-3"
            placeholder="Cari nama murid..." oninput="filterStudents(this.value)">
          <div id="studentList">
            <?php foreach ($students as $s): ?>
            <label class="student-check w-100" data-name="<?= strtolower(h($s['name'])) ?>">
              <input type="checkbox" name="student_ids[]" value="<?= $s['id'] ?>"
                class="student-cb"
                <?= in_array($s['id'], $classStudentIds)?'checked':'' ?>>
              <div style="width:28px;height:28px;border-radius:50%;background:#4F86C6;color:white;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:600;flex-shrink:0">
                <?= strtoupper(substr($s['name'],0,2)) ?>
              </div>
              <span class="small"><?= h($s['name']) ?></span>
              <?php
              // Cek apakah murid ini juga ada di kelas lain
              $otherClasses = Database::fetchAll("
                SELECT c.name FROM class_students cs
                JOIN classes c ON c.id = cs.class_id
                WHERE cs.student_id=? AND cs.class_id != ?
              ", [$s['id'], $selClass]);
              ?>
              <?php if (!empty($otherClasses)): ?>
              <span class="ms-auto badge bg-light text-muted border" style="font-size:.65rem">
                +<?= count($otherClasses) ?> kelas lain
              </span>
              <?php endif; ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
          <span class="small text-muted" id="countLabel">
            <?= count($classStudentIds) ?> murid terpilih
          </span>
          <button type="submit" class="btn btn-navy btn-sm">
            <i class="bi bi-save me-1"></i>Simpan Mapping
          </button>
        </div>
      </form>
    </div>
    <?php else: ?>
    <div class="card">
      <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-arrow-left-circle display-4 d-block mb-2"></i>
        <p>Pilih kelas di sebelah kiri untuk mengatur mapping muridnya</p>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
function toggleAll(check) {
  document.querySelectorAll('.student-cb').forEach(cb => cb.checked = check);
  updateCount();
}
function filterStudents(q) {
  document.querySelectorAll('#studentList label').forEach(label => {
    const name = label.dataset.name || '';
    label.style.display = name.includes(q.toLowerCase()) ? '' : 'none';
  });
}
function updateCount() {
  const cnt = document.querySelectorAll('.student-cb:checked').length;
  const el = document.getElementById('countLabel');
  if (el) el.textContent = cnt + ' murid terpilih';
}
document.querySelectorAll('.student-cb').forEach(cb => {
  cb.addEventListener('change', updateCount);
});
</script>

<?php endif; ?>

<?php $content = ob_get_clean(); pageWrapper('Kelas & Mapping', $content); ?>