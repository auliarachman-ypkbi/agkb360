<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin','admin','foundation']);

$period = getPeriod();
$pid = $period['id'] ?? 0;

// ── Handle Create Assignment ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $evaluateeId = (int)$_POST['evaluatee_id'];
        $evaluatorId = (int)$_POST['evaluator_id'];
        $packageId   = (int)$_POST['package_id'];
        $dueDate     = $_POST['due_date'] ?? $period['end_date'];

        // ── VALIDASI BACKEND (lapis keamanan) ─────────────────
        $evaluatee = Database::fetchOne("SELECT * FROM users WHERE id=?", [$evaluateeId]);
        $evaluator = Database::fetchOne("SELECT * FROM users WHERE id=?", [$evaluatorId]);
        $package   = Database::fetchOne("SELECT * FROM packages WHERE id=?", [$packageId]);

        $error = null;

        if (!$evaluatee || !$evaluator || !$package) {
            $error = 'Data pengguna atau paket tidak ditemukan.';
        } elseif (!in_array($evaluatee['role'], ['leader','teacher'])) {
            $error = 'Yang dinilai harus berperan sebagai Leader atau Guru.';
        } else {
            // Validasi kombinasi role → paket
            $evalTypeId = $package['eval_type_id'];
            $respType   = $package['respondent_type'];

            // Paket harus sesuai tipe evaluatee
            if ($evaluatee['role'] === 'leader' && $evalTypeId != 1) {
                $error = 'Paket tidak sesuai — evaluatee adalah Leader, gunakan paket L1-L6.';
            } elseif ($evaluatee['role'] === 'teacher' && $evalTypeId != 2) {
                $error = 'Paket tidak sesuai — evaluatee adalah Guru, gunakan paket T1-T6.';
            }

            // Validasi role evaluator sesuai respondent_type paket
            if (!$error) {
                $validCombos = [
                    'atasan' => ['foundation'],
                    'peer'   => ['leader', 'teacher'],
                    'guru'   => ['teacher'],
                    'leader' => ['leader'],
                    'ortu'   => ['parent'],
                    'siswa'  => ['student'],
                    'self'   => [$evaluatee['role']],
                ];
                $allowedRoles = $validCombos[$respType] ?? [];
                if (!in_array($evaluator['role'], $allowedRoles)) {
                    $error = "Kombinasi tidak valid — paket '{$package['code']}' harus diisi oleh: " . implode(' atau ', $allowedRoles) . ", bukan {$evaluator['role']}.";
                }
            }

            // Validasi self-reflection: evaluator harus sama dengan evaluatee
            if (!$error && $package['is_self_reflection'] && $evaluatorId !== $evaluateeId) {
                $error = 'Paket refleksi mandiri hanya bisa diisi oleh orang yang dinilai itu sendiri.';
            }

            // Validasi peer: tidak menilai diri sendiri
            if (!$error && $respType === 'peer' && $evaluatorId === $evaluateeId) {
                $error = 'Peer evaluation tidak bisa menilai diri sendiri.';
            }
        }

        if ($error) {
            flash('⚠️ ' . $error, 'danger');
        } else {
            $exists = Database::fetchOne(
                "SELECT id FROM assignments WHERE period_id=? AND evaluatee_id=? AND evaluator_id=? AND package_id=?",
                [$pid, $evaluateeId, $evaluatorId, $packageId]
            );
            if ($exists) {
                flash('Penugasan ini sudah ada.', 'warning');
            } else {
                Database::insert('assignments', [
                    'period_id'    => $pid,
                    'evaluatee_id' => $evaluateeId,
                    'evaluator_id' => $evaluatorId,
                    'package_id'   => $packageId,
                    'status'       => 'pending',
                    'due_date'     => $dueDate,
                ]);
                flash('Penugasan berhasil dibuat.', 'success');
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['assign_id'];
        Database::query("DELETE FROM assignments WHERE id=? AND status='pending'", [$id]);
        flash('Penugasan dihapus.', 'warning');
    }

    if ($action === 'bulk_create') {
        $created  = 0;
        $dueDate  = $period['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
        $ins      = Database::getInstance()->prepare(
            "INSERT IGNORE INTO assignments (period_id,evaluatee_id,evaluator_id,package_id,status,due_date) VALUES (?,?,?,?,?,?)"
        );

        $leaders    = Database::fetchAll("SELECT id FROM users WHERE role='leader' AND is_active=1");
        $teachers   = Database::fetchAll("SELECT id FROM users WHERE role='teacher' AND is_active=1");
        $foundation = Database::fetchAll("SELECT id FROM users WHERE role='foundation' AND is_active=1");
        $osis       = Database::fetchAll("SELECT id FROM users WHERE role='student' AND is_active=1 AND is_osis=1");
        $committee  = Database::fetchAll("SELECT id FROM users WHERE role='parent' AND is_active=1 AND is_parent_committee=1");

        $pkgMap = [];
        foreach (Database::fetchAll("SELECT id, code FROM packages") as $p) {
            $pkgMap[$p['code']] = $p['id'];
        }

        // ── LEADER evaluations ────────────────────────────────
        foreach ($leaders as $leader) {
            $lid = $leader['id'];
            // L1: Yayasan → Leader
            foreach ($foundation as $er) { $ins->execute([$pid,$lid,$er['id'],$pkgMap['L1']??0,'pending',$dueDate]); $created++; }
            // L2: Leader sesama → Leader
            foreach ($leaders as $er) { if ($er['id']===$lid) continue; $ins->execute([$pid,$lid,$er['id'],$pkgMap['L2']??0,'pending',$dueDate]); $created++; }
            // L3: Semua Guru → Leader
            foreach ($teachers as $er) { $ins->execute([$pid,$lid,$er['id'],$pkgMap['L3']??0,'pending',$dueDate]); $created++; }
            // L4: Komite Ortu → Leader
            foreach ($committee as $er) { $ins->execute([$pid,$lid,$er['id'],$pkgMap['L4']??0,'pending',$dueDate]); $created++; }
            // L5: OSIS → Leader
            foreach ($osis as $er) { $ins->execute([$pid,$lid,$er['id'],$pkgMap['L5']??0,'pending',$dueDate]); $created++; }
            // L6: Self
            if (!empty($pkgMap['L6'])) { $ins->execute([$pid,$lid,$lid,$pkgMap['L6'],'pending',$dueDate]); $created++; }
        }

        // ── TEACHER evaluations ───────────────────────────────
        foreach ($teachers as $teacher) {
            $tid = $teacher['id'];
            // T1: Tidak ada Yayasan ke Guru
            // T2: Semua Leader → Guru
            foreach ($leaders as $er) { $ins->execute([$pid,$tid,$er['id'],$pkgMap['T2']??0,'pending',$dueDate]); $created++; }
            // T3: Guru sesama → Guru
            foreach ($teachers as $er) { if ($er['id']===$tid) continue; $ins->execute([$pid,$tid,$er['id'],$pkgMap['T3']??0,'pending',$dueDate]); $created++; }
            // T4: Komite Ortu → Guru
            foreach ($committee as $er) { $ins->execute([$pid,$tid,$er['id'],$pkgMap['T4']??0,'pending',$dueDate]); $created++; }
            // T5: Siswa sesuai kelas guru mengajar
            $studentEvaluators = Database::fetchAll("
                SELECT DISTINCT u.id FROM users u
                JOIN teacher_classes tc ON tc.class_id = u.class_id
                WHERE tc.teacher_id=? AND u.role='student' AND u.is_active=1
            ", [$tid]);
            foreach ($studentEvaluators as $er) { $ins->execute([$pid,$tid,$er['id'],$pkgMap['T5']??0,'pending',$dueDate]); $created++; }
            // T6: Self
            if (!empty($pkgMap['T6'])) { $ins->execute([$pid,$tid,$tid,$pkgMap['T6'],'pending',$dueDate]); $created++; }
        }

        flash("$created penugasan berhasil dibuat berdasarkan logika 360°.", 'success');
        header('Location: ' . APP_URL . '/admin/assignments.php'); exit;
    }
}

// ── Fetch assignments ─────────────────────────────────────────
$statusFilter = $_GET['status'] ?? '';
$where = $statusFilter ? "AND a.status='$statusFilter'" : '';

$assignments = Database::fetchAll("
    SELECT a.*, 
           u_ee.name as evaluatee_name, u_ee.role as evaluatee_role,
           u_or.name as evaluator_name, u_or.role as evaluator_role,
           p.name as pkg_name, p.code as pkg_code, p.respondent_type,
           (SELECT ROUND(AVG(r.grade),2) FROM responses r WHERE r.assignment_id=a.id) as avg_score
    FROM assignments a
    JOIN users u_ee ON a.evaluatee_id = u_ee.id
    JOIN users u_or ON a.evaluator_id = u_or.id
    JOIN packages p ON a.package_id = p.id
    WHERE a.period_id = ? $where
    ORDER BY a.status, u_ee.name, p.code
    LIMIT 300
", [$pid]);

// Form dropdowns
$allUsers   = Database::fetchAll("SELECT id, name, role FROM users WHERE is_active=1 ORDER BY role, name");
$packages   = Database::fetchAll("SELECT id, code, name, eval_type_id, respondent_type FROM packages ORDER BY code");

// Summary by status
$summary = Database::fetchAll("
    SELECT status, COUNT(*) c FROM assignments WHERE period_id=? GROUP BY status
", [$pid]);
$sumMap = [];
foreach ($summary as $s) $sumMap[$s['status']] = $s['c'];

ob_start(); ?>

<!-- PERIOD BANNER -->
<?php if ($period): ?>
<div class="alert alert-info py-2 d-flex align-items-center justify-content-between mb-4">
  <span><i class="bi bi-calendar-event me-2"></i>Periode: <strong><?= h($period['name']) ?></strong>
  | Deadline: <strong><?= date('d M Y', strtotime($period['end_date'])) ?></strong></span>
  <form method="POST" class="d-inline">
    <input type="hidden" name="action" value="bulk_create">
    <button class="btn btn-sm btn-gold"
      onclick="return confirm('Buat semua penugasan otomatis berdasarkan paket kuesioner? Duplikat akan diabaikan.')">
      <i class="bi bi-lightning me-1"></i>Auto-Create Semua Penugasan
    </button>
  </form>
</div>
<?php endif; ?>

<!-- SUMMARY STATS -->
<div class="row g-3 mb-4">
  <div class="col-4"><a href="?" class="text-decoration-none">
    <div class="stat-card position-relative text-center">
      <div class="stat-number"><?= array_sum(array_column($summary,'c')) ?></div>
      <div class="stat-label">Total</div>
    </div>
  </a></div>
  <div class="col-4"><a href="?status=pending" class="text-decoration-none">
    <div class="stat-card red position-relative text-center">
      <div class="stat-number"><?= $sumMap['pending'] ?? 0 ?></div>
      <div class="stat-label">Menunggu</div>
    </div>
  </a></div>
  <div class="col-4"><a href="?status=completed" class="text-decoration-none">
    <div class="stat-card green position-relative text-center">
      <div class="stat-number"><?= $sumMap['completed'] ?? 0 ?></div>
      <div class="stat-label">Selesai</div>
    </div>
  </a></div>
</div>

<?= showFlash() ?>

<!-- ADD ASSIGNMENT FORM -->
<div class="card mb-4">
  <div class="card-header gold"><i class="bi bi-plus-circle me-2"></i>Tambah Penugasan Manual</div>
  <div class="card-body">
    <form method="POST" id="assignForm">
      <input type="hidden" name="action" value="create">
      <div class="row g-3 align-items-start">

        <!-- Yang Dinilai -->
        <div class="col-md-3">
          <label class="form-label small fw-semibold">
            Yang Dinilai <span class="text-danger">*</span>
            <span class="text-muted fw-normal">(Leader / Guru)</span>
          </label>
          <select name="evaluatee_id" id="sel_evaluatee" class="form-select" required>
            <option value="">Ketik nama...</option>
          </select>
        </div>

        <!-- Penilai -->
        <div class="col-md-3">
          <label class="form-label small fw-semibold">
            Penilai <span class="text-danger">*</span>
          </label>
          <select name="evaluator_id" id="sel_evaluator" class="form-select" required disabled>
            <option value="">Pilih yang dinilai dulu</option>
          </select>
          <div class="form-text text-muted small">
            <i class="bi bi-funnel me-1"></i>Otomatis difilter sesuai yang dinilai
          </div>
        </div>

        <!-- Paket Kuesioner -->
        <div class="col-md-3">
          <label class="form-label small fw-semibold">
            Paket Kuesioner <span class="text-danger">*</span>
          </label>
          <select name="package_id" id="sel_package" class="form-select" required disabled>
            <option value="">Otomatis terpilih</option>
            <?php foreach ($packages as $p): ?>
            <option value="<?= $p['id'] ?>"><?= h($p['code'] . ' — ' . $p['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <!-- Hint box dengan tinggi tetap supaya layout tidak bergeser -->
          <div id="pkg_hint" style="min-height:28px;margin-top:4px"></div>
        </div>

        <!-- Deadline -->
        <div class="col-md-2">
          <label class="form-label small fw-semibold">Deadline</label>
          <input type="date" name="due_date" class="form-control"
            value="<?= h($period['end_date'] ?? '') ?>">
        </div>

        <!-- Submit -->
        <div class="col-md-1 d-flex align-items-start" style="padding-top:28px">
          <button type="submit" class="btn btn-navy w-100" id="btn_submit" disabled>
            <i class="bi bi-plus-lg"></i>
          </button>
        </div>

      </div>
    </form>
  </div>
</div>

<!-- ASSIGNMENTS TABLE -->
<div class="card">
  <div class="card-header d-flex justify-content-between">
    <span><i class="bi bi-list-check me-2"></i>Daftar Penugasan</span>
    <div class="d-flex gap-2">
      <?php foreach ([''=>'Semua','pending'=>'Menunggu','in_progress'=>'Proses','completed'=>'Selesai'] as $s => $l): ?>
      <a href="?status=<?= $s ?>" class="btn btn-sm <?= $statusFilter===$s ? 'btn-navy' : 'btn-outline-secondary' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover data-table mb-0" style="font-size:.85rem">
        <thead><tr>
          <th>Yang Dinilai</th><th>Penilai</th><th>Paket</th>
          <th>Deadline</th><th>Status</th><th style="text-align:center">Rata-rata</th><th>Aksi</th>
        </tr></thead>
        <tbody>
          <?php foreach ($assignments as $a): ?>
          <tr>
            <td><strong><?= h($a['evaluatee_name']) ?></strong><br>
              <small class="text-muted"><?= h(roleLabel($a['evaluatee_role'])) ?></small></td>
            <td><?= h($a['evaluator_name']) ?><br>
              <small class="text-muted"><?= h(roleLabel($a['evaluator_role'])) ?></small></td>
            <td><span class="badge badge-navy"><?= h($a['pkg_code']) ?></span><br>
              <small class="text-muted"><?= h(respondentLabel($a['respondent_type'])) ?></small></td>
            <td class="small"><?= $a['due_date'] ? date('d M Y', strtotime($a['due_date'])) : '—' ?></td>
            <td><?= statusBadge($a['status']) ?></td>
            <td style="text-align:center;vertical-align:middle">
              <?php if ($a['status']==='completed' && $a['avg_score']): ?>
              <?= scoreBadge((float)$a['avg_score']) ?>
              <?php else: ?>
              <span style="color:#cbd5e1">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($a['status'] === 'pending'): ?>
              <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="assign_id" value="<?= $a['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"
                  data-confirm="Hapus penugasan ini?" title="Hapus">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
              <?php elseif ($a['status'] === 'completed'): ?>
              <a href="<?= APP_URL ?>/survey/fill.php?id=<?= $a['id'] ?>&view=1"
                class="btn btn-sm btn-outline-secondary" title="Lihat jawaban">
                <i class="bi bi-eye"></i>
              </a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$extraCss = "
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css'>
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css'>
<style>
.select2-container { width: 100% !important; }
.select2-container--bootstrap-5 .select2-selection { min-height: 38px; font-size:.9rem; }
#pkg_hint { min-height: 28px; }
#pkg_hint .badge { font-size:.78rem; padding:4px 10px; white-space:normal; line-height:1.4 }
</style>
";
$content = ob_get_clean();
pageWrapper('Kelola Penugasan', $content, $extraCss);
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
const API = '<?= APP_URL ?>/api/assignment_helper.php';

$(document).ready(function () {

  // ── Select2: Yang Dinilai ──────────────────────────────────
  $('#sel_evaluatee').select2({
    theme: 'bootstrap-5',
    placeholder: 'Ketik nama leader atau guru...',
    allowClear: true,
    minimumInputLength: 0,
    ajax: {
      url: API,
      dataType: 'json',
      delay: 250,
      data: params => ({ action: 'search_users', type: 'evaluatee', q: params.term || '' }),
      processResults: data => ({ results: data.results || [] }),
      cache: true,
    },
  });

  // ── Select2: Penilai ───────────────────────────────────────
  function initEvaluatorSelect(evaluateeId) {
    // Destroy dulu kalau sudah ada
    if ($('#sel_evaluator').hasClass('select2-hidden-accessible')) {
      $('#sel_evaluator').select2('destroy');
    }

    const disabled = !evaluateeId;

    $('#sel_evaluator')
      .prop('disabled', disabled)
      .select2({
        theme: 'bootstrap-5',
        placeholder: disabled ? 'Pilih yang dinilai dulu' : 'Ketik nama penilai...',
        allowClear: true,
        minimumInputLength: 0,
        disabled: disabled,
        ajax: {
          url: API,
          dataType: 'json',
          delay: 250,
          data: params => ({
            action: 'search_users',
            type: 'evaluator',
            evaluatee_id: evaluateeId || '',
            q: params.term || '',
          }),
          processResults: data => ({ results: data.results || [] }),
          cache: false,
        },
      })
      .on('change', fetchPackage);
  }

  // Init awal — evaluator disabled
  initEvaluatorSelect(null);

  // ── Saat evaluatee berubah ─────────────────────────────────
  $('#sel_evaluatee').on('change', function () {
    const eeId = $(this).val();

    // Reset evaluator, paket, hint, tombol
    $('#sel_evaluator').val(null);
    resetPackage();

    // Reinit evaluator dengan evaluatee baru
    initEvaluatorSelect(eeId || null);
  });

  // ── Reset paket & tombol ───────────────────────────────────
  function resetPackage() {
    $('#pkg_hint').html('');
    $('#sel_package').val('').prop('disabled', true);
    $('#btn_submit').prop('disabled', true);
  }

  // ── Fetch paket via AJAX ───────────────────────────────────
  function fetchPackage() {
    const eeId = $('#sel_evaluatee').val();
    const erId = $('#sel_evaluator').val();

    if (!eeId || !erId) {
      resetPackage();
      return;
    }

    $('#pkg_hint').html(
      '<span class="text-muted small"><i class="bi bi-hourglass-split me-1"></i>Mengecek...</span>'
    );

    $.getJSON(API, {
      action: 'get_package',
      evaluatee_id: eeId,
      evaluator_id: erId,
    })
    .done(function (data) {
      if (data.error) {
        $('#pkg_hint').html(`
          <span class="badge bg-danger">
            <i class="bi bi-x-circle me-1"></i>${data.error}
          </span>`);
        $('#sel_package').prop('disabled', true).val('');
        $('#btn_submit').prop('disabled', true);

      } else if (data.package) {
        $('#sel_package').val(data.package.id).prop('disabled', false);
        $('#pkg_hint').html(`
          <span class="badge bg-success">
            <i class="bi bi-check-circle me-1"></i>
            <strong>${data.package.code}</strong> — ${data.package.name}
          </span>`);
        $('#btn_submit').prop('disabled', false);

      } else {
        $('#pkg_hint').html(`
          <span class="badge bg-warning text-dark">
            <i class="bi bi-exclamation-triangle me-1"></i>
            ${data.message || 'Pilih paket secara manual.'}
          </span>`);
        $('#sel_package').prop('disabled', false).val('');
        $('#btn_submit').prop('disabled', false);
      }
    })
    .fail(function () {
      $('#pkg_hint').html(
        '<span class="text-danger small"><i class="bi bi-wifi-off me-1"></i>Gagal terhubung ke server.</span>'
      );
    });
  }

});
</script>