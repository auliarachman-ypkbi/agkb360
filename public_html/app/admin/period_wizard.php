<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin','admin','foundation']);

$pid = (int)($_GET['id'] ?? 0);
$period = Database::fetchOne("SELECT * FROM eval_periods WHERE id=?", [$pid]);
if (!$period || $period['status'] === 'closed') {
    flash('Periode tidak ditemukan atau sudah ditutup.', 'danger');
    header('Location: ' . APP_URL . '/admin/periods.php');
    exit;
}

$currentStep = (int)($period['wizard_step'] ?? 0);
$action = $_POST['action'] ?? '';

// ── STEP HANDLERS ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // STEP 1: Info periode sudah dibuat, lanjut ke step 2
    if ($action === 'step1_done') {
        $name  = trim($_POST['name'] ?? $period['name']);
        $year  = (int)($_POST['year'] ?? $period['year']);
        $start = $_POST['start_date'] ?? $period['start_date'];
        $end   = $_POST['end_date']   ?? $period['end_date'];
        Database::update('eval_periods', [
            'name'        => $name,
            'year'        => $year,
            'start_date'  => $start ?: null,
            'end_date'    => $end   ?: null,
            'wizard_step' => max($currentStep, 1),
        ], 'id=?', [$pid]);
        header('Location: ?id=' . $pid . '&step=2');
        exit;
    }

    // STEP 2A: Simpan yang DINILAI
    if ($action === 'step2a_done') {
        $selected = $_POST['evaluatees'] ?? [];
        // Hapus yang lama, insert yang baru
        Database::query("DELETE FROM period_evaluatees WHERE period_id=?", [$pid]);
        foreach ($selected as $uid => $etId) {
            Database::insert('period_evaluatees', [
                'period_id'    => $pid,
                'user_id'      => (int)$uid,
                'eval_type_id' => (int)$etId,
                'is_active'    => 1,
            ]);
        }
        Database::update('eval_periods', ['wizard_step'=>max($currentStep,2)], 'id=?', [$pid]);
        header('Location: ?id=' . $pid . '&step=2b');
        exit;
    }

    // STEP 2B: Simpan yang MENILAI
    if ($action === 'step2b_done') {
        $selected = $_POST['evaluators'] ?? [];
        Database::query("DELETE FROM period_evaluators WHERE period_id=?", [$pid]);
        foreach ($selected as $grpId => $userIds) {
            foreach ($userIds as $uid => $val) {
                if (!$val) continue;
                Database::insert('period_evaluators', [
                    'period_id' => $pid,
                    'user_id'   => (int)$uid,
                    'group_id'  => (int)$grpId,
                    'is_active' => 1,
                ]);
            }
        }
        Database::update('eval_periods', ['wizard_step'=>max($currentStep,3)], 'id=?', [$pid]);
        header('Location: ?id=' . $pid . '&step=3');
        exit;
    }

    // STEP 3: Konfirmasi matriks
    if ($action === 'step3_done') {
        Database::update('eval_periods', ['wizard_step'=>max($currentStep,4)], 'id=?', [$pid]);
        header('Location: ?id=' . $pid . '&step=4');
        exit;
    }

    // STEP 4: Generate paket untuk periode ini
    if ($action === 'step4_generate') {
        _generatePeriodPackages($pid);
        Database::update('eval_periods', ['wizard_step'=>max($currentStep,5)], 'id=?', [$pid]);
        flash('Paket soal berhasil di-generate untuk periode ini.', 'success');
        header('Location: ?id=' . $pid . '&step=5');
        exit;
    }

    // STEP 5: Generate penugasan
    if ($action === 'step5_generate') {
        $count = _generateAssignments($pid);
        Database::update('eval_periods', ['wizard_step'=>max($currentStep,6)], 'id=?', [$pid]);
        flash("$count penugasan berhasil dibuat.", 'success');
        header('Location: ?id=' . $pid . '&step=6');
        exit;
    }

    // STEP 6: AKTIFKAN PERIODE
    if ($action === 'activate') {
        // Nonaktifkan periode lain
        Database::query("UPDATE eval_periods SET is_active=0, status='closed' WHERE is_active=1 AND id!=?", [$pid]);
        Database::update('eval_periods', [
            'status'     => 'active',
            'is_active'  => 1,
            'wizard_step'=> 6,
            'locked_at'  => date('Y-m-d H:i:s'),
        ], 'id=?', [$pid]);
        flash('Periode berhasil diaktifkan! Responden sudah bisa mengisi kuesioner.', 'success');
        header('Location: ' . APP_URL . '/admin/periods.php');
        exit;
    }
}

// ── HELPER: Generate Packages per Periode ─────────────────────
function _generatePeriodPackages(int $pid): void {
    // Ambil packages template (period_id NULL)
    $templates = Database::fetchAll("SELECT * FROM packages WHERE period_id IS NULL AND is_self_reflection=0");
    foreach ($templates as $tmpl) {
        // Cek apakah sudah ada untuk periode ini
        $exists = Database::fetchOne(
            "SELECT id FROM packages WHERE eval_type_id=? AND respondent_type=? AND period_id=?",
            [$tmpl['eval_type_id'], $tmpl['respondent_type'], $pid]
        );
        if ($exists) continue;

        // Buat paket baru terikat periode
        $newPkgId = Database::insert('packages', [
            'code'               => $tmpl['code'] . '_P' . $pid,
            'name'               => $tmpl['name'],
            'eval_type_id'       => $tmpl['eval_type_id'],
            'respondent_type'    => $tmpl['respondent_type'],
            'is_self_reflection' => 0,
            'period_id'          => $pid,
        ]);

        // Copy package_questions dari template
        $tplQs = Database::fetchAll(
            "SELECT * FROM package_questions WHERE package_id=? ORDER BY order_num",
            [$tmpl['id']]
        );
        foreach ($tplQs as $q) {
            Database::insert('package_questions', [
                'package_id'                => $newPkgId,
                'question_id'               => $q['question_id'],
                'order_num'                 => $q['order_num'],
                'question_id_text_override' => $q['question_id_text_override'],
                'question_en_text_override' => $q['question_en_text_override'],
            ]);
        }

        // Copy weights
        $tmplW = Database::fetchOne("SELECT weight FROM package_weights WHERE package_id=?", [$tmpl['id']]);
        Database::insert('package_weights', [
            'package_id' => $newPkgId,
            'weight'     => $tmplW['weight'] ?? 1.0,
        ]);
    }
}

// ── HELPER: Generate Assignments ──────────────────────────────
function _generateAssignments(int $pid): int {
    $count = 0;

    // Ambil evaluatees periode ini
    $evaluatees = Database::fetchAll(
        "SELECT pe.*, u.name, u.role, u.email FROM period_evaluatees pe JOIN users u ON pe.user_id = u.id WHERE pe.period_id=? AND pe.is_active=1",
        [$pid]
    );

    // Ambil evaluators periode ini (per grup)
    $evaluators = Database::fetchAll(
        "SELECT pev.*, u.name, u.role, g.respondent_type FROM period_evaluators pev JOIN users u ON pev.user_id=u.id JOIN `groups` g ON pev.group_id=g.id WHERE pev.period_id=? AND pev.is_active=1",
        [$pid]
    );

    // Group evaluators by respondent_type
    $evalByType = [];
    foreach ($evaluators as $ev) {
        $evalByType[$ev['respondent_type']][] = $ev;
    }

    // Paket untuk periode ini
    $packages = Database::fetchAll(
        "SELECT p.* FROM packages p WHERE p.period_id=? AND p.is_self_reflection=0",
        [$pid]
    );

    // Group packages by (eval_type_id, respondent_type)
    $pkgMap = [];
    foreach ($packages as $pkg) {
        $key = $pkg['eval_type_id'] . '_' . $pkg['respondent_type'];
        $pkgMap[$key] = $pkg['id'];
    }

    // Periode untuk due_date
    $period = Database::fetchOne("SELECT * FROM eval_periods WHERE id=?", [$pid]);
    $dueDate = $period['end_date'] ?? date('Y-m-d', strtotime('+30 days'));

    // Generate assignments
    foreach ($evaluatees as $evaluatee) {
        $etId = $evaluatee['eval_type_id'];

        foreach ($evalByType as $respType => $evaluatorList) {
            $key = $etId . '_' . $respType;
            if (!isset($pkgMap[$key])) continue;
            $pkgId = $pkgMap[$key];

            foreach ($evaluatorList as $evaluator) {
                // Skip jika evaluatee = evaluator (self tidak di sini)
                if ($evaluator['user_id'] === $evaluatee['user_id']) continue;

                // Cek apakah sudah ada
                $exists = Database::fetchOne(
                    "SELECT id FROM assignments WHERE period_id=? AND evaluatee_id=? AND evaluator_id=? AND package_id=?",
                    [$pid, $evaluatee['user_id'], $evaluator['user_id'], $pkgId]
                );
                if ($exists) continue;

                Database::insert('assignments', [
                    'period_id'    => $pid,
                    'evaluatee_id' => $evaluatee['user_id'],
                    'evaluator_id' => $evaluator['user_id'],
                    'package_id'   => $pkgId,
                    'status'       => 'pending',
                    'due_date'     => $dueDate,
                ]);
                $count++;
            }
        }
    }

    return $count;
}

// ── DETERMINE CURRENT VIEW STEP ───────────────────────────────
$viewStep = $_GET['step'] ?? (string)max(1, $currentStep);
// viewStep is string, no numeric bounds needed

// Fetch data per step
$evalTypes = Database::fetchAll("SELECT * FROM eval_types ORDER BY id");

// Step 2A data
$leaderTeacherUsers = Database::fetchAll("
    SELECT u.*, et.id as et_id, et.name as et_name
    FROM users u
    JOIN eval_types et ON (
        (u.role='leader' AND et.code='leader') OR
        (u.role='teacher' AND et.code='teacher')
    )
    WHERE u.is_active=1
    ORDER BY u.role, u.name
");
$selectedEvaluatees = [];
foreach (Database::fetchAll("SELECT * FROM period_evaluatees WHERE period_id=?", [$pid]) as $pe) {
    $selectedEvaluatees[$pe['user_id']] = $pe['eval_type_id'];
}

// Step 2B data — semua grup evaluator termasuk kelas murid
$fixedGroups = Database::fetchAll("
    SELECT g.*, COUNT(ug.user_id) as member_count
    FROM `groups` g
    LEFT JOIN user_groups ug ON ug.group_id = g.id
    WHERE g.respondent_type IS NOT NULL
      AND g.respondent_type != 'student_class'
    GROUP BY g.id
    HAVING member_count > 0
    ORDER BY g.is_fixed DESC, g.order_num, g.name
");

// Tambah grup kelas (murid per kelas) — type='student', is_fixed=0
$classGroups = Database::fetchAll("
    SELECT g.*, COUNT(ug.user_id) as member_count
    FROM `groups` g
    LEFT JOIN user_groups ug ON ug.group_id = g.id
    WHERE g.type IN ('student','siswa') AND g.is_fixed = 0
    GROUP BY g.id
    HAVING member_count > 0
    ORDER BY g.name
");
// Merge: kelas muncul terpisah di bawah
$allEvaluatorGroups = array_merge($fixedGroups, $classGroups);
$groupMembers = [];
foreach (array_merge($fixedGroups, $classGroups) as $grp) {
    $groupMembers[$grp['id']] = Database::fetchAll("
        SELECT u.* FROM users u
        JOIN user_groups ug ON ug.user_id = u.id
        WHERE ug.group_id=? AND u.is_active=1
        ORDER BY u.name
    ", [$grp['id']]);
}
$selectedEvaluators = [];
foreach (Database::fetchAll("SELECT * FROM period_evaluators WHERE period_id=?", [$pid]) as $pev) {
    $selectedEvaluators[$pev['group_id']][$pev['user_id']] = true;
}

// Step 3: Matriks summary
$matrixSummary = Database::fetchAll("
    SELECT et.name as et_name, srm.respondent_type, COUNT(*) as cnt
    FROM standard_respondent_mapping srm
    JOIN standards s ON srm.standard_id = s.id
    JOIN domains d ON s.domain_id = d.id
    JOIN eval_types et ON d.eval_type_id = et.id
    WHERE srm.period_id IS NULL AND srm.is_active=1
    GROUP BY et.name, srm.respondent_type
    ORDER BY et.name, srm.respondent_type
");

// Step 4: Preview paket
$periodPackages = Database::fetchAll("
    SELECT p.*, COUNT(pq.id) as q_count
    FROM packages p
    LEFT JOIN package_questions pq ON pq.package_id = p.id
    WHERE p.period_id=?
    GROUP BY p.id
", [$pid]);

// Step 5: Penugasan
$assignCount      = Database::fetchOne("SELECT COUNT(*) c FROM assignments WHERE period_id=?", [$pid])['c'];
$evaluateesCount  = Database::fetchOne("SELECT COUNT(*) c FROM period_evaluatees WHERE period_id=? AND is_active=1", [$pid])['c'];
$evaluatorsCount  = Database::fetchOne("SELECT COUNT(*) c FROM period_evaluators WHERE period_id=? AND is_active=1", [$pid])['c'];

// Step labels
$steps = [
    1 => ['label'=>'Info Periode',      'icon'=>'bi-calendar-plus'],
    2 => ['label'=>'Yang Dinilai',      'icon'=>'bi-person-check'],
    3 => ['label'=>'Matriks & Paket',   'icon'=>'bi-grid'],
    4 => ['label'=>'Generate Paket',    'icon'=>'bi-boxes'],
    5 => ['label'=>'Generate Penugasan','icon'=>'bi-send'],
    6 => ['label'=>'Aktifkan Periode',  'icon'=>'bi-rocket-takeoff'],
];

ob_start(); ?>

<?= showFlash() ?>

<!-- Wizard header -->
<div class="card mb-4" style="background:var(--ktb-navy);color:white">
  <div class="card-body py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h6 class="fw-bold mb-0"><?= h($period['name']) ?></h6>
        <small class="opacity-75">Setup Wizard — Step <?= $viewStep ?> dari 6</small>
      </div>
      <a href="<?= APP_URL ?>/admin/periods.php" class="btn btn-sm btn-outline-light">
        <i class="bi bi-arrow-left me-1"></i>Kembali
      </a>
    </div>
    <!-- Step indicators -->
    <div class="d-flex gap-1">
      <?php foreach ($steps as $n => $s): ?>
      <div class="flex-grow-1 text-center">
        <div class="d-flex flex-column align-items-center gap-1">
          <div style="width:32px;height:32px;border-radius:50%;border:2px solid <?= $n==$viewStep?'#ffc901':($n<=$currentStep?'#4ade80':'rgba(255,255,255,.3)') ?>;background:<?= $n==$viewStep?'#ffc901':($n<=$currentStep?'#16a34a':'transparent') ?>;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:<?= $n==$viewStep?'#001f3e':'white' ?>">
            <?php if ($n < $viewStep && $n <= $currentStep): ?>
            <i class="bi bi-check"></i>
            <?php else: ?>
            <?= $n ?>
            <?php endif; ?>
          </div>
          <span style="font-size:.6rem;opacity:<?= $n==$viewStep?'1':'.55' ?>;white-space:nowrap">
            <?= $s['label'] ?>
          </span>
        </div>
      </div>
      <?php if ($n < 6): ?>
      <div class="flex-grow-0 d-flex align-items-center pb-4" style="padding:0 2px">
        <div style="height:2px;width:20px;background:<?= $n<$currentStep?'#4ade80':'rgba(255,255,255,.2)' ?>"></div>
      </div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ── STEP CONTENT ─────────────────────────────────────────── -->

<?php if ($viewStep == '1'): ?>
<!-- STEP 1: INFO PERIODE -->
<div class="card">
  <div class="card-header fw-bold">
    <i class="bi bi-calendar-plus me-2"></i>Step 1 — Informasi Periode
  </div>
  <form method="POST">
    <input type="hidden" name="action" value="step1_done">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label fw-semibold">Nama Periode <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" required
            value="<?= h($period['name']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Tahun Akademik</label>
          <input type="number" name="year" class="form-control" value="<?= $period['year'] ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Tanggal Mulai</label>
          <input type="date" name="start_date" class="form-control"
            value="<?= $period['start_date'] ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Tanggal Selesai</label>
          <input type="date" name="end_date" class="form-control"
            value="<?= $period['end_date'] ?>">
        </div>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-end">
      <button type="submit" class="btn btn-navy">
        Lanjut ke Step 2 <i class="bi bi-arrow-right ms-1"></i>
      </button>
    </div>
  </form>
</div>

<?php elseif ($viewStep == '2'): ?>
<!-- STEP 2A: YANG DINILAI -->
<div class="card">
  <div class="card-header fw-bold">
    <i class="bi bi-person-check me-2"></i>Step 2A — Pilih yang Dinilai (Objek Evaluasi)
  </div>
  <form method="POST">
    <input type="hidden" name="action" value="step2a_done">
    <div class="card-body">
      <div class="alert alert-light border small mb-3">
        <i class="bi bi-info-circle me-1 text-primary"></i>
        Centang user yang akan menjadi <strong>objek evaluasi</strong> di periode ini.
        User yang tidak dicentang tidak akan mendapat kuesioner.
      </div>
      <div class="d-flex justify-content-end mb-2">
        <button type="button" class="btn btn-sm btn-outline-primary me-2"
          onclick="document.querySelectorAll('.evaluatee-cb').forEach(c=>c.checked=true)">
          <i class="bi bi-check-all me-1"></i>Pilih Semua
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary"
          onclick="document.querySelectorAll('.evaluatee-cb').forEach(c=>c.checked=false)">
          <i class="bi bi-x me-1"></i>Hapus Semua
        </button>
      </div>
      <?php
      $byRole = [];
      foreach ($leaderTeacherUsers as $u) {
          $byRole[$u['et_name']][] = $u;
      }
      foreach ($byRole as $etName => $users):
      ?>
      <h6 class="fw-bold text-navy mb-2"><?= h($etName) ?></h6>
      <div class="row g-2 mb-4">
        <?php foreach ($users as $u):
          $isSelected = isset($selectedEvaluatees[$u['id']]);
        ?>
        <div class="col-md-4">
          <div class="border rounded p-2 d-flex align-items-center gap-2
               <?= $isSelected?'border-success bg-success-subtle':'' ?>">
            <input type="checkbox"
              name="evaluatees[<?= $u['id'] ?>]"
              value="<?= $u['et_id'] ?>"
              class="form-check-input evaluatee-cb"
              <?= $isSelected?'checked':'' ?>>
            <div>
              <div class="small fw-semibold"><?= h($u['name']) ?></div>
              <div class="text-muted" style="font-size:.75rem"><?= h($u['email']) ?></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="card-footer d-flex justify-content-between">
      <a href="?id=<?= $pid ?>&step=1" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
      </a>
      <button type="submit" class="btn btn-navy">
        Lanjut ke 2B — Yang Menilai <i class="bi bi-arrow-right ms-1"></i>
      </button>
    </div>
  </form>
</div>

<?php elseif ($viewStep === '2b'): ?>
<!-- STEP 2B: YANG MENILAI -->
<div class="card">
  <div class="card-header fw-bold">
    <i class="bi bi-people me-2"></i>Step 2B — Pilih yang Menilai (Evaluator)
  </div>
  <form method="POST">
    <input type="hidden" name="action" value="step2b_done">
    <div class="card-body">
      <div class="alert alert-light border small mb-3">
        <i class="bi bi-info-circle me-1 text-primary"></i>
        Centang siapa saja yang akan <strong>memberikan penilaian</strong> di periode ini.
        User yang tidak dicentang tidak akan mendapat kuesioner untuk diisi.
      </div>
      <!-- Grup Fixed (Yayasan, Pimpinan, Guru, Ortu, OSIS) -->
      <h6 class="fw-bold text-navy mb-2 mt-2">Kelompok Penilai</h6>
      <?php foreach ($fixedGroups as $grp):
        $members = $groupMembers[$grp['id']] ?? [];
        if (empty($members)) continue;
        $grpCbId = 'grp_' . $grp['id'];
      ?>
      <div class="card mb-2 border-0 bg-light">
        <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <span class="fw-semibold text-navy"><?= h($grp['name']) ?></span>
            <span class="text-muted small"><?= count($members) ?> anggota</span>
          </div>
          <div class="d-flex gap-1">
            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2"
              onclick="selectAllGroup('<?= $grpCbId ?>', true)" style="font-size:.72rem">
              <i class="bi bi-check-all me-1"></i>Semua
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2"
              onclick="selectAllGroup('<?= $grpCbId ?>', false)" style="font-size:.72rem">
              <i class="bi bi-x"></i>
            </button>
          </div>
        </div>
        <div class="card-body py-2">
          <div class="row g-2">
            <?php foreach ($members as $u):
              $isSelected = !empty($selectedEvaluators[$grp['id']][$u['id']]);
            ?>
            <div class="col-md-4">
              <div class="border rounded p-2 d-flex align-items-center gap-2 bg-white
                   <?= $isSelected?'border-primary':'' ?>">
                <input type="checkbox"
                  name="evaluators[<?= $grp['id'] ?>][<?= $u['id'] ?>]"
                  value="1"
                  class="form-check-input grp-cb-<?= $grpCbId ?>"
                  <?= $isSelected?'checked':'' ?>>
                <div>
                  <div class="small fw-semibold"><?= h($u['name']) ?></div>
                  <div class="text-muted" style="font-size:.72rem"><?= h($u['email']) ?></div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Murid per Kelas -->
      <?php if (!empty($classGroups)): ?>
      <h6 class="fw-bold text-navy mb-2 mt-3">Murid (per Kelas)</h6>
      <?php foreach ($classGroups as $grp):
        $members = $groupMembers[$grp['id']] ?? [];
        if (empty($members)) continue;
        $grpCbId = 'grp_' . $grp['id'];
      ?>
      <div class="card mb-2 border-0" style="background:#f8faff">
        <div class="card-header py-2 d-flex justify-content-between align-items-center"
             style="background:#eef2ff">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-people-fill text-primary" style="font-size:.85rem"></i>
            <span class="fw-semibold text-navy"><?= h($grp['name']) ?></span>
            <span class="text-muted small"><?= count($members) ?> murid</span>
          </div>
          <div class="d-flex gap-1">
            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2"
              onclick="selectAllGroup('<?= $grpCbId ?>', true)" style="font-size:.72rem">
              <i class="bi bi-check-all me-1"></i>Semua
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2"
              onclick="selectAllGroup('<?= $grpCbId ?>', false)" style="font-size:.72rem">
              <i class="bi bi-x"></i>
            </button>
          </div>
        </div>
        <div class="card-body py-2">
          <div class="row g-1">
            <?php foreach ($members as $u):
              $isSelected = !empty($selectedEvaluators[$grp['id']][$u['id']]);
            ?>
            <div class="col-md-3 col-6">
              <div class="border rounded p-1 d-flex align-items-center gap-1 bg-white
                   <?= $isSelected?'border-primary':'' ?>" style="font-size:.8rem">
                <input type="checkbox"
                  name="evaluators[<?= $grp['id'] ?>][<?= $u['id'] ?>]"
                  value="1"
                  class="form-check-input grp-cb-<?= $grpCbId ?>"
                  style="width:.9em;height:.9em"
                  <?= $isSelected?'checked':'' ?>>
                <span class="text-truncate"><?= h($u['name']) ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="card-footer d-flex justify-content-between">
      <a href="?id=<?= $pid ?>&step=2" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
      </a>
      <button type="submit" class="btn btn-navy">
        Lanjut ke Step 3 <i class="bi bi-arrow-right ms-1"></i>
      </button>
    </div>
  </form>
</div>

<?php elseif ($viewStep == '3'): ?>
<!-- STEP 3: REVIEW MATRIKS -->
<div class="card">
  <div class="card-header fw-bold">
    <i class="bi bi-grid me-2"></i>Step 3 — Review Matriks & Paket
  </div>
  <div class="card-body">
    <div class="alert alert-light border small mb-3">
      <i class="bi bi-info-circle me-1 text-primary"></i>
      Ini adalah mapping standar yang akan digunakan. Jika perlu diubah,
      <a href="<?= APP_URL ?>/admin/matrix.php">buka Matriks Mapping</a> terlebih dahulu.
    </div>
    <table class="table table-sm small">
      <thead><tr>
        <th>Tipe Evaluasi</th>
        <th>Responden</th>
        <th class="text-center">Jumlah Standar</th>
      </tr></thead>
      <tbody>
        <?php foreach ($matrixSummary as $row): ?>
        <tr>
          <td><?= h($row['et_name']) ?></td>
          <td><?= h(respondentLabel($row['respondent_type'])) ?></td>
          <td class="text-center"><span class="badge bg-secondary"><?= $row['cnt'] ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($matrixSummary)): ?>
        <tr><td colspan="3" class="text-center text-danger">
          Belum ada matriks mapping! <a href="<?= APP_URL ?>/admin/matrix.php">Set matriks dulu →</a>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <form method="POST">
    <input type="hidden" name="action" value="step3_done">
    <div class="card-footer d-flex justify-content-between">
      <a href="?id=<?= $pid ?>&step=2b" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
      </a>
      <button type="submit" class="btn btn-navy" <?= empty($matrixSummary)?'disabled':'' ?>>
        Lanjut ke Step 4 <i class="bi bi-arrow-right ms-1"></i>
      </button>
    </div>
  </form>
</div>

<?php elseif ($viewStep == '4'): ?>
<!-- STEP 4: GENERATE PAKET -->
<div class="card">
  <div class="card-header fw-bold">
    <i class="bi bi-boxes me-2"></i>Step 4 — Generate Paket Soal untuk Periode Ini
  </div>
  <div class="card-body">
    <?php if (!empty($periodPackages)): ?>
    <div class="alert alert-success small">
      <i class="bi bi-check-circle me-1"></i>
      <?= count($periodPackages) ?> paket sudah di-generate untuk periode ini.
    </div>
    <table class="table table-sm small">
      <thead><tr><th>Nama Paket</th><th>Responden</th><th class="text-center">Soal</th></tr></thead>
      <tbody>
        <?php foreach ($periodPackages as $pkg): ?>
        <tr>
          <td><?= h($pkg['name']) ?></td>
          <td><?= h(respondentLabel($pkg['respondent_type'])) ?></td>
          <td class="text-center"><?= $pkg['q_count'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="alert alert-light border">
      <i class="bi bi-info-circle me-1"></i>
      Paket soal akan di-copy dari template global dan dikunci untuk periode ini.
      Perubahan template setelah ini tidak akan mempengaruhi periode yang aktif.
    </div>
    <?php endif; ?>
  </div>
  <form method="POST">
    <input type="hidden" name="action" value="step4_generate">
    <div class="card-footer d-flex justify-content-between">
      <a href="?id=<?= $pid ?>&step=3" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
      </a>
      <button type="submit" class="btn btn-navy">
        <i class="bi bi-boxes me-1"></i>
        <?= empty($periodPackages) ? 'Generate Paket Soal' : 'Re-generate Paket' ?>
        <i class="bi bi-arrow-right ms-1"></i>
      </button>
    </div>
  </form>
</div>

<?php elseif ($viewStep == '5'): ?>
<!-- STEP 5: GENERATE PENUGASAN -->
<div class="card">
  <div class="card-header fw-bold">
    <i class="bi bi-send me-2"></i>Step 5 — Generate Penugasan
  </div>
  <div class="card-body">
    <?php
    $pkgCount = Database::fetchOne("SELECT COUNT(*) c FROM packages WHERE period_id=?", [$pid])['c'];
    ?>
    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <div class="card text-center border-0 bg-light p-3">
          <div class="fs-3 fw-bold text-navy"><?= $evaluateesCount ?></div>
          <div class="small text-muted">Yang Dinilai</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center border-0 bg-light p-3">
          <div class="fs-3 fw-bold text-navy"><?= $evaluatorsCount ?></div>
          <div class="small text-muted">Evaluator</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center border-0 bg-light p-3">
          <div class="fs-3 fw-bold text-navy"><?= $pkgCount ?></div>
          <div class="small text-muted">Paket Soal</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center border-0 <?= $assignCount>0?'bg-success-subtle border-success':'' ?> p-3">
          <div class="fs-3 fw-bold <?= $assignCount>0?'text-success':'text-navy' ?>"><?= $assignCount ?></div>
          <div class="small text-muted">Penugasan Terbuat</div>
        </div>
      </div>
    </div>
    <?php if ($assignCount > 0): ?>
    <div class="alert alert-success small">
      <i class="bi bi-check-circle me-1"></i>
      <?= $assignCount ?> penugasan sudah dibuat. Klik Generate ulang jika ada perubahan.
    </div>
    <?php else: ?>
    <div class="alert alert-light border small">
      <i class="bi bi-info-circle me-1"></i>
      Sistem akan membuat kuesioner untuk setiap kombinasi (yang dinilai × evaluator × paket soal).
    </div>
    <?php endif; ?>
  </div>
  <form method="POST">
    <input type="hidden" name="action" value="step5_generate">
    <div class="card-footer d-flex justify-content-between">
      <a href="?id=<?= $pid ?>&step=4" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali
      </a>
      <button type="submit" class="btn btn-navy">
        <i class="bi bi-send me-1"></i>Generate Penugasan
        <i class="bi bi-arrow-right ms-1"></i>
      </button>
    </div>
  </form>
</div>

<?php elseif ($viewStep == '6'): ?>
<!-- STEP 6: AKTIFKAN -->
<div class="card">
  <div class="card-header fw-bold" style="background:var(--ktb-navy);color:white">
    <i class="bi bi-rocket-takeoff me-2"></i>Step 6 — Aktifkan Periode Evaluasi
  </div>
  <div class="card-body text-center py-5">
    <?php
    $otherActive = Database::fetchOne(
        "SELECT * FROM eval_periods WHERE status='active' AND id!=?", [$pid]
    );
    $assignCount2 = Database::fetchOne("SELECT COUNT(*) c FROM assignments WHERE period_id=?", [$pid])['c'];
    ?>
    <?php if ($otherActive): ?>
    <div class="alert alert-danger text-start mb-4" style="max-width:500px;margin:0 auto 1.5rem">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      <strong>Tidak bisa diaktifkan!</strong><br>
      <strong><?= h($otherActive['name']) ?></strong> masih aktif.
      Tutup periode tersebut dulu agar tidak menumpuk kuesioner di dashboard user.
      <div class="mt-2">
        <a href="<?= APP_URL ?>/admin/periods.php"
           class="btn btn-sm btn-danger">
          <i class="bi bi-arrow-left me-1"></i>Ke halaman Periode → Tutup dulu
        </a>
      </div>
    </div>
    <?php endif; ?>
    <div style="font-size:4rem;margin-bottom:1rem">🚀</div>
    <h5 class="fw-bold text-navy mb-2"><?= h($period['name']) ?></h5>
    <p class="text-muted mb-4">
      Semua setup selesai. Setelah diaktifkan:<br>
      <strong>Setting terkunci</strong> — matriks, paket, dan penugasan tidak bisa diubah.<br>
      Responden akan bisa mengisi kuesioner mereka.
    </p>
    <div class="d-flex justify-content-center gap-4 mb-4">
      <div class="text-center">
        <div class="fs-3 fw-bold text-navy"><?= $evaluateesCount ?></div>
        <div class="small text-muted">Yang Dinilai</div>
      </div>
      <div class="text-center">
        <div class="fs-3 fw-bold text-navy"><?= $assignCount2 ?></div>
        <div class="small text-muted">Penugasan</div>
      </div>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="activate">
      <div class="d-flex justify-content-center gap-3">
        <a href="?id=<?= $pid ?>&step=5" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
        <button type="submit" class="btn btn-lg"
          style="background:#16a34a;color:white;padding:.6rem 2rem"
          <?= $otherActive ? 'disabled title="Tutup periode aktif dulu"' : '' ?>
          onclick="return confirm('Yakin aktifkan periode ini? Setting akan terkunci.')">
          <i class="bi bi-rocket-takeoff me-2"></i>AKTIFKAN SEKARANG
        </button>
      </div>
    </form>
  </div>
</div>

<?php endif; ?>

<?php $content = ob_get_clean(); pageWrapper('Setup Wizard — ' . h($period['name']), $content . '
<script>
function selectAllGroup(grpId, checked) {
  document.querySelectorAll(".grp-cb-" + grpId).forEach(cb => cb.checked = checked);
}
</script>
'); ?>