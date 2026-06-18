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

// ── Fetch assignments dengan filter & pagination ───────────────
$statusFilter    = $_GET['status'] ?? '';
$roleFilter      = $_GET['role'] ?? '';
$respFilter      = $_GET['resp'] ?? '';
$evaluateeIdF    = (int)($_GET['evaluatee_id'] ?? 0);
$evaluatorIdF    = (int)($_GET['evaluator_id'] ?? 0);
$searchQ         = trim($_GET['q'] ?? '');
$curPage         = max(1, (int)($_GET['page'] ?? 1));
$perPage         = 50;

$whereSql = "WHERE a.period_id = ?";
$qParams  = [$pid];

if ($statusFilter !== '') { $whereSql .= " AND a.status = ?"; $qParams[] = $statusFilter; }
if ($roleFilter   !== '') { $whereSql .= " AND u_ee.role = ?"; $qParams[] = $roleFilter; }
if ($respFilter   !== '') { $whereSql .= " AND p.respondent_type = ?"; $qParams[] = $respFilter; }
if ($evaluateeIdF > 0)    { $whereSql .= " AND a.evaluatee_id = ?"; $qParams[] = $evaluateeIdF; }
if ($evaluatorIdF > 0)    { $whereSql .= " AND a.evaluator_id = ?"; $qParams[] = $evaluatorIdF; }
if ($searchQ      !== '') {
    $whereSql .= " AND (u_ee.name LIKE ? OR u_or.name LIKE ?)";
    $qParams[] = "%$searchQ%"; $qParams[] = "%$searchQ%";
}

$filteredTotal = Database::fetchOne("
    SELECT COUNT(*) c
    FROM assignments a
    JOIN users u_ee ON a.evaluatee_id = u_ee.id
    JOIN users u_or ON a.evaluator_id = u_or.id
    JOIN packages p ON a.package_id = p.id
    $whereSql
", $qParams)['c'];

$pagi   = paginate($filteredTotal, $perPage, $curPage);
$offset = $pagi['offset'];

$assignments = Database::fetchAll("
    SELECT a.*, 
           u_ee.name as evaluatee_name, u_ee.role as evaluatee_role,
           u_or.name as evaluator_name, u_or.role as evaluator_role,
           p.name as pkg_name, p.code as pkg_code, p.respondent_type
    FROM assignments a
    JOIN users u_ee ON a.evaluatee_id = u_ee.id
    JOIN users u_or ON a.evaluator_id = u_or.id
    JOIN packages p ON a.package_id = p.id
    $whereSql
    ORDER BY a.status, u_ee.name, p.code
    LIMIT $perPage OFFSET $offset
", $qParams);

// Daftar respondent_type yang ada (untuk dropdown filter)
$respTypeOptions = Database::fetchAll("
    SELECT DISTINCT respondent_type FROM packages
    WHERE respondent_type IS NOT NULL ORDER BY respondent_type
");

// Daftar nama yang dinilai (evaluatee) — distinct, hanya yang punya assignment di periode ini
$evaluateeOptions = Database::fetchAll("
    SELECT DISTINCT u.id, u.name, u.role
    FROM users u
    JOIN assignments a ON a.evaluatee_id = u.id AND a.period_id = ?
    ORDER BY u.role, u.name
", [$pid]);

// Daftar nama penilai — distinct, hanya yang punya assignment di periode ini
$evaluatorOptions = Database::fetchAll("
    SELECT DISTINCT u.id, u.name, u.role
    FROM users u
    JOIN assignments a ON a.evaluator_id = u.id AND a.period_id = ?
    ORDER BY u.role, u.name
", [$pid]);

// Helper bangun query string, sambil mempertahankan filter lain
function buildAssignQS(array $overrides = []): string {
    $base = [
        'status'       => $_GET['status']       ?? '',
        'role'         => $_GET['role']         ?? '',
        'resp'         => $_GET['resp']         ?? '',
        'evaluatee_id' => $_GET['evaluatee_id'] ?? '',
        'evaluator_id' => $_GET['evaluator_id'] ?? '',
        'q'            => $_GET['q']            ?? '',
        'page'         => $_GET['page']         ?? '',
    ];
    $merged = array_filter(array_merge($base, $overrides), fn($v) => $v !== '' && $v !== null && $v !== 0 && $v !== '0');
    return '?' . htmlspecialchars(http_build_query($merged));
}

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
  <div class="col-4"><a href="<?= buildAssignQS(['status'=>'']) ?>" class="text-decoration-none">
    <div class="stat-card position-relative text-center">
      <div class="stat-number"><?= array_sum(array_column($summary,'c')) ?></div>
      <div class="stat-label">Total</div>
    </div>
  </a></div>
  <div class="col-4"><a href="<?= buildAssignQS(['status'=>'pending']) ?>" class="text-decoration-none">
    <div class="stat-card red position-relative text-center">
      <div class="stat-number"><?= $sumMap['pending'] ?? 0 ?></div>
      <div class="stat-label">Menunggu</div>
    </div>
  </a></div>
  <div class="col-4"><a href="<?= buildAssignQS(['status'=>'completed']) ?>" class="text-decoration-none">
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

<style>
.filter-bar{background:#fff;border:0.5px solid #e2e8f0;border-radius:10px;padding:14px 18px;margin-bottom:14px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap}
.filter-bar .f-group{display:flex;flex-direction:column;gap:5px}
.filter-bar label{font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.4px;margin:0}
.filter-bar select,.filter-bar input[type=text]{height:36px;border:1px solid #e2e8f0;border-radius:7px;padding:0 11px;font-size:13px;color:#1e293b;outline:none;min-width:160px}
.filter-bar select:focus,.filter-bar input:focus{border-color:#2C5282;box-shadow:0 0 0 3px rgba(44,82,130,.1)}
.filter-bar .f-search{min-width:220px;flex:1}
.filter-bar .f-actions{display:flex;gap:8px}
.filter-bar .btn-filter{height:36px;padding:0 16px;background:#2C5282;color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.filter-bar .btn-filter:hover{background:#1A365D}
.filter-bar .btn-clear{height:36px;width:36px;display:inline-flex;align-items:center;justify-content:center;background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;color:#64748b;text-decoration:none}

.status-tabs{display:flex;gap:6px}
.status-tab{padding:6px 14px;border-radius:20px;font-size:12px;font-weight:500;border:0.5px solid #e2e8f0;background:#fff;color:#64748b;text-decoration:none;white-space:nowrap}
.status-tab.active{background:#2C5282;color:#fff;border-color:#2C5282}

.assign-card{background:#fff;border:0.5px solid #e2e8f0;border-radius:12px;overflow:hidden}
.assign-card-hdr{padding:14px 18px;border-bottom:0.5px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.assign-card-title{font-size:14px;font-weight:600;color:#1e293b;display:flex;align-items:center;gap:8px}
.result-count{font-size:12px;color:#94a3b8;font-weight:400}

.assign-table{width:100%;border-collapse:collapse;font-size:13px}
.assign-table thead th{background:#f8fafc;color:#475569;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;padding:11px 16px;text-align:left;border-bottom:1px solid #e2e8f0;white-space:nowrap}
.assign-table tbody td{padding:12px 16px;vertical-align:middle;color:#1e293b;border-bottom:0.5px solid #f1f5f9}
.assign-table tbody tr:hover{background:#f8fafc}
.assign-table tbody tr:last-child td{border-bottom:none}
.name-primary{font-weight:600;color:#1e293b;font-size:13px}
.role-sub{font-size:11px;color:#94a3b8;margin-top:2px}
.pkg-badge{display:inline-block;background:#2C5282;color:#fff;font-size:11px;font-weight:600;padding:3px 10px;border-radius:6px;letter-spacing:.2px}
.resp-sub{font-size:11px;color:#94a3b8;margin-top:4px;display:block}
.due-date-cell{font-size:12px;color:#475569;white-space:nowrap}
.action-cell{display:flex;gap:6px}
.action-icon-btn{width:30px;height:30px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #e2e8f0;background:#fff;color:#64748b;text-decoration:none;cursor:pointer}
.action-icon-btn.danger:hover{background:#FCEBEB;border-color:#F09595;color:#791F1F}
.action-icon-btn.view:hover{background:#E6F1FB;border-color:#B5D4F4;color:#185FA5}
.empty-row{text-align:center;padding:40px;color:#94a3b8;font-size:13px}
.pager{display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-top:0.5px solid #e2e8f0;background:#fafbfc}
.pager-info{font-size:12px;color:#64748b}
.pager-btns{display:flex;gap:8px}
.pager-btn{padding:6px 14px;border-radius:7px;border:1px solid #e2e8f0;background:#fff;color:#475569;text-decoration:none;font-size:12px;display:inline-flex;align-items:center;gap:5px}
.pager-btn.disabled{opacity:.4;pointer-events:none}
</style>

<!-- FILTER BAR (langsung di atas tabel) -->
<form method="GET" class="filter-bar">
  <input type="hidden" name="status" value="<?= h($statusFilter) ?>">
  <div class="f-group">
    <label>Role yang Dinilai</label>
    <select name="role" onchange="this.form.submit()">
      <option value="">Semua Role</option>
      <option value="leader" <?= $roleFilter==='leader'?'selected':'' ?>>Pimpinan</option>
      <option value="teacher" <?= $roleFilter==='teacher'?'selected':'' ?>>Guru</option>
    </select>
  </div>
  <div class="f-group">
    <label>Tipe Responden</label>
    <select name="resp" onchange="this.form.submit()">
      <option value="">Semua Tipe</option>
      <?php foreach ($respTypeOptions as $rt): ?>
      <option value="<?= h($rt['respondent_type']) ?>" <?= $respFilter===$rt['respondent_type']?'selected':'' ?>>
        <?= h(respondentLabel($rt['respondent_type'])) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="f-group">
    <label>Yang Dinilai</label>
    <select name="evaluatee_id" onchange="this.form.submit()">
      <option value="">Semua</option>
      <?php $prevRole = null; foreach ($evaluateeOptions as $eo): ?>
        <?php if ($eo['role'] !== $prevRole): ?>
          <?php if ($prevRole !== null): ?></optgroup><?php endif; ?>
          <optgroup label="<?= h(roleLabel($eo['role'])) ?>">
          <?php $prevRole = $eo['role']; ?>
        <?php endif; ?>
        <option value="<?= $eo['id'] ?>" <?= $evaluateeIdF===$eo['id']?'selected':'' ?>><?= h($eo['name']) ?></option>
      <?php endforeach; ?>
      <?php if ($prevRole !== null): ?></optgroup><?php endif; ?>
    </select>
  </div>
  <div class="f-group">
    <label>Penilai</label>
    <select name="evaluator_id" onchange="this.form.submit()">
      <option value="">Semua</option>
      <?php $prevRole2 = null; foreach ($evaluatorOptions as $eo): ?>
        <?php if ($eo['role'] !== $prevRole2): ?>
          <?php if ($prevRole2 !== null): ?></optgroup><?php endif; ?>
          <optgroup label="<?= h(roleLabel($eo['role'])) ?>">
          <?php $prevRole2 = $eo['role']; ?>
        <?php endif; ?>
        <option value="<?= $eo['id'] ?>" <?= $evaluatorIdF===$eo['id']?'selected':'' ?>><?= h($eo['name']) ?></option>
      <?php endforeach; ?>
      <?php if ($prevRole2 !== null): ?></optgroup><?php endif; ?>
    </select>
  </div>
  <div class="f-group f-search">
    <label>Cari Nama (bebas)</label>
    <input type="text" name="q" placeholder="Cari yang dinilai / penilai..." value="<?= h($searchQ) ?>">
  </div>
  <div class="f-actions">
    <button type="submit" class="btn-filter"><i class="bi bi-funnel"></i>Filter</button>
    <?php if ($roleFilter || $respFilter || $evaluateeIdF || $evaluatorIdF || $searchQ): ?>
    <a href="<?= buildAssignQS(['role'=>null,'resp'=>null,'evaluatee_id'=>null,'evaluator_id'=>null,'q'=>null,'page'=>null]) ?>" class="btn-clear" title="Reset filter">
      <i class="bi bi-x-lg"></i>
    </a>
    <?php endif; ?>
  </div>
</form>

<!-- ASSIGNMENTS TABLE -->
<div class="assign-card">
  <div class="assign-card-hdr">
    <span class="assign-card-title">
      <i class="bi bi-list-check"></i>Daftar Penugasan
      <span class="result-count">(<?= $filteredTotal ?> hasil<?= ($roleFilter||$respFilter||$evaluateeIdF||$evaluatorIdF||$searchQ) ? ' terfilter' : '' ?>)</span>
    </span>
    <div class="status-tabs">
      <?php foreach ([''=>'Semua','pending'=>'Menunggu','in_progress'=>'Proses','completed'=>'Selesai'] as $s => $l): ?>
      <a href="<?= buildAssignQS(['status'=>$s,'page'=>null]) ?>" class="status-tab <?= $statusFilter===$s ? 'active' : '' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div style="overflow-x:auto">
    <table class="assign-table">
      <thead><tr>
        <th>Yang Dinilai</th><th>Penilai</th><th>Paket</th>
        <th>Deadline</th><th>Status</th><th style="text-align:center">Aksi</th>
      </tr></thead>
      <tbody>
        <?php if (empty($assignments)): ?>
        <tr><td colspan="6" class="empty-row">
          <i class="bi bi-inbox" style="font-size:28px;display:block;margin-bottom:8px;opacity:.4"></i>
          Tidak ada penugasan yang cocok dengan filter ini
        </td></tr>
        <?php else: ?>
        <?php foreach ($assignments as $a): ?>
        <tr>
          <td>
            <div class="name-primary"><?= h($a['evaluatee_name']) ?></div>
            <div class="role-sub"><?= h(roleLabel($a['evaluatee_role'])) ?></div>
          </td>
          <td>
            <a href="<?= APP_URL ?>/admin/evaluator_pov.php?id=<?= $a['evaluator_id'] ?>"
               class="name-primary" style="font-weight:500;color:#185FA5;text-decoration:none">
              <?= h($a['evaluator_name']) ?>
            </a>
            <div class="role-sub"><?= h(roleLabel($a['evaluator_role'])) ?></div>
          </td>
          <td>
            <span class="pkg-badge"><?= h($a['pkg_code']) ?></span>
            <span class="resp-sub"><?= h(respondentLabel($a['respondent_type'])) ?></span>
          </td>
          <td class="due-date-cell"><?= $a['due_date'] ? date('d M Y', strtotime($a['due_date'])) : '—' ?></td>
          <td><?= statusBadge($a['status']) ?></td>
          <td>
            <div class="action-cell" style="justify-content:center">
              <?php if ($a['status'] === 'pending'): ?>
              <form method="POST" onsubmit="return confirm('Hapus penugasan ini?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="assign_id" value="<?= $a['id'] ?>">
                <button type="submit" class="action-icon-btn danger" title="Hapus">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
              <?php elseif ($a['status'] === 'completed'): ?>
              <a href="<?= APP_URL ?>/survey/fill.php?id=<?= $a['id'] ?>&view=1"
                class="action-icon-btn view" title="Lihat jawaban">
                <i class="bi bi-eye"></i>
              </a>
              <?php else: ?>
              <span style="color:#cbd5e1">—</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- PAGINATION -->
  <?php if ($pagi['total_pages'] > 1): ?>
  <div class="pager">
    <span class="pager-info">
      Halaman <?= $pagi['page'] ?> dari <?= $pagi['total_pages'] ?>
      (menampilkan <?= $offset + 1 ?>–<?= min($offset + $perPage, $filteredTotal) ?> dari <?= $filteredTotal ?>)
    </span>
    <div class="pager-btns">
      <a href="<?= buildAssignQS(['page'=>max(1,$pagi['page']-1)]) ?>"
         class="pager-btn <?= $pagi['page']<=1?'disabled':'' ?>">
        <i class="bi bi-chevron-left"></i> Sebelumnya
      </a>
      <a href="<?= buildAssignQS(['page'=>min($pagi['total_pages'],$pagi['page']+1)]) ?>"
         class="pager-btn <?= $pagi['page']>=$pagi['total_pages']?'disabled':'' ?>">
        Selanjutnya <i class="bi bi-chevron-right"></i>
      </a>
    </div>
  </div>
  <?php endif; ?>
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