<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
$user = currentUser();

// Hanya superadmin / tester yang boleh akses
if (!in_array($user['role'], ['superadmin', 'tester'])) {
    http_response_code(403); die('Akses ditolak.');
}

// ── Ambil semua package ───────────────────────────────────────
$packages = Database::fetchAll("
    SELECT p.id, p.name, p.code, p.respondent_type, p.is_self_reflection,
           COUNT(pq.id) as question_count
    FROM packages p
    LEFT JOIN package_questions pq ON pq.package_id = p.id
    WHERE p.period_id IS NULL
    GROUP BY p.id
    ORDER BY p.name
");

// ── Package yang dipilih ──────────────────────────────────────
$selectedPkgId = (int)($_GET['pkg'] ?? 0);
$selectedPkg   = null;
$questions     = [];
$descriptors   = [];
$byDomain      = [];

if ($selectedPkgId) {
    $selectedPkg = Database::fetchOne(
        "SELECT * FROM packages WHERE id = ?",
        [$selectedPkgId]
    );

    if ($selectedPkg) {
        $questions = Database::fetchAll("
            SELECT q.id,
                   COALESCE(pq.question_id_text_override, q.question_id_text) as question_id_text,
                   COALESCE(pq.question_en_text_override,  q.question_en_text)  as question_en_text,
                   s.name as standard_name, s.elaboration_id, s.elaboration_en,
                   d.name as domain_name, d.code as domain_code,
                   pq.order_num
            FROM package_questions pq
            JOIN questions q ON pq.question_id = q.id
            JOIN standards s ON q.standard_id = s.id
            JOIN domains d ON s.domain_id = d.id
            WHERE pq.package_id = ?
            ORDER BY pq.order_num
        ", [$selectedPkgId]);

        if (!empty($questions)) {
            $qIds = implode(',', array_column($questions, 'id'));
            $rows = Database::fetchAll(
                "SELECT * FROM grade_descriptors WHERE question_id IN ($qIds) ORDER BY question_id, grade"
            );
            foreach ($rows as $r) {
                $descriptors[$r['question_id']][$r['grade']] = $r;
            }
            foreach ($questions as $q) {
                $byDomain[$q['domain_name']][] = $q;
            }
        }
    }
}

// ── Handle POST (simulasi — tidak ada INSERT) ─────────────────
$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedPkgId) {
    $grades   = $_POST['grade'] ?? [];
    $isFinal  = !empty($_POST['submit_final']);
    $answered = count(array_filter($grades, fn($g) => (int)$g >= 1 && (int)$g <= 4));
    $total    = count($questions);

    if ($isFinal && $answered >= $total) {
        $testResult = ['type' => 'success', 'answered' => $answered, 'total' => $total];
    } else {
        $testResult = ['type' => 'save', 'answered' => $answered, 'total' => $total];
    }
    // Kembalikan grades ke view agar radio tetap checked setelah submit
    $submittedGrades = $grades;
}
$submittedGrades = $submittedGrades ?? [];

// Nama dummy untuk placeholder [Nama] / [Name]
$dummyName = 'Budi Santoso';

ob_start();
?>

<!-- ── BANNER TESTER MODE ─────────────────────────────────── -->
<div class="alert d-flex align-items-center gap-2 mb-4"
     style="background:#fff3cd;border:1.5px solid #ffc107;border-radius:10px">
  <i class="bi bi-flask text-warning fs-5"></i>
  <div>
    <strong>Mode Tester</strong> — Halaman ini hanya untuk preview kuesioner.
    Tidak ada data yang disimpan ke database.
  </div>
</div>

<!-- ── PILIH PACKAGE ─────────────────────────────────────── -->
<div class="card mb-4">
  <div class="card-body">
    <h6 class="fw-bold text-navy mb-3">
      <i class="bi bi-box me-2"></i>Pilih Paket Kuesioner
    </h6>
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-8">
        <label class="form-label small fw-semibold text-muted">Paket</label>
        <select name="pkg" class="form-select" onchange="this.form.submit()">
          <option value="">— Pilih paket —</option>
          <?php foreach ($packages as $pkg): ?>
          <option value="<?= $pkg['id'] ?>"
            <?= $selectedPkgId == $pkg['id'] ? 'selected' : '' ?>>
            <?= h($pkg['name']) ?>
            (<?= h($pkg['code']) ?> · <?= $pkg['question_count'] ?> soal
            <?php
              $rtLabel = match($pkg['respondent_type']) {
                  'self'   => '· Self',
                  'peer'   => '· Peer',
                  'leader' => '· Leader',
                  'student'=> '· Student',
                  'parent' => '· Parent',
                  default  => ''
              };
              echo $rtLabel;
            ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <button type="submit" class="btn btn-navy w-100">
          <i class="bi bi-eye me-1"></i>Preview
        </button>
      </div>
    </form>
  </div>
</div>

<?php if ($selectedPkg && empty($questions)): ?>
<div class="alert alert-warning">
  <i class="bi bi-exclamation-triangle me-2"></i>
  Paket ini belum memiliki pertanyaan.
</div>

<?php elseif ($selectedPkg && !empty($questions)):
  $totalQ    = count($questions);
  $answered  = 0;
  // Hitung answered dari submittedGrades
  foreach ($submittedGrades as $g) {
      if ((int)$g >= 1 && (int)$g <= 4) $answered++;
  }
  $progress = $totalQ > 0 ? round(($answered / $totalQ) * 100) : 0;
?>

<!-- ── TEST RESULT BANNER ─────────────────────────────────── -->
<?php if ($testResult): ?>
  <?php if ($testResult['type'] === 'success'): ?>
  <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-check-circle-fill fs-5"></i>
    <div>
      <strong>Simulasi Selesai!</strong>
      Semua <?= $testResult['total'] ?> pertanyaan dijawab.
      <em>(Tidak ada data yang tersimpan — ini hanya simulasi.)</em>
    </div>
  </div>
  <?php else: ?>
  <div class="alert alert-info d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-save fs-5"></i>
    <div>
      <strong>Progres Disimpan (Simulasi)</strong> —
      <?= $testResult['answered'] ?>/<?= $testResult['total'] ?> pertanyaan dijawab.
      <em>(Tidak ada data yang tersimpan ke database.)</em>
    </div>
  </div>
  <?php endif; ?>
<?php endif; ?>

<!-- ── HEADER INFO PACKAGE ───────────────────────────────── -->
<div class="card mb-4">
  <div class="card-body">
    <div class="row align-items-center">
      <div class="col-md-8">
        <h5 class="fw-bold text-navy mb-1">
          <?= $selectedPkg['is_self_reflection'] ? '📝 Refleksi Mandiri' : '📋 ' . h($selectedPkg['name']) ?>
        </h5>
        <p class="text-muted mb-1 small">
          Yang Dinilai (dummy): <strong><?= h($dummyName) ?></strong>
          <?php if (!$selectedPkg['is_self_reflection']): ?>
          | Tipe Responden: <strong><?= h($selectedPkg['respondent_type']) ?></strong>
          <?php endif; ?>
        </p>
        <span class="badge" style="background:#6f42c1">
          <i class="bi bi-flask me-1"></i>Preview Mode — Tidak ada data tersimpan
        </span>
      </div>
      <div class="col-md-4 text-md-end">
        <div class="mb-1 small"><?= $answered ?>/<?= $totalQ ?> pertanyaan dijawab</div>
        <div class="progress" style="height:12px">
          <div class="progress-bar navy" style="width:<?= $progress ?>%" role="progressbar">
            <?= $progress ?>%
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── FORM KUESIONER (simulasi) ─────────────────────────── -->
<form method="POST" id="surveyForm">
  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

  <?php $qNum = 0; foreach ($byDomain as $domainName => $domainQs): ?>

  <div class="mb-4">
    <div class="d-flex align-items-center gap-2 mb-3">
      <div style="width:4px;height:24px;background:var(--ktb-navy);border-radius:2px"></div>
      <h6 class="fw-bold text-navy mb-0"><?= h($domainName) ?></h6>
    </div>

    <?php foreach ($domainQs as $q): ?>
    <?php
      $qNum++;
      $savedGrade = (int)($submittedGrades[$q['id']] ?? 0);
      $isSaved    = $savedGrade >= 1 && $savedGrade <= 4;
    ?>

    <div class="question-card mb-3 <?= $isSaved ? 'border-success' : '' ?>">
      <div class="row">
        <div class="col-md-8">
          <div class="d-flex gap-2 mb-2">
            <span class="badge badge-navy" style="min-width:28px"><?= $qNum ?></span>
            <strong class="small text-muted"><?= h($q['standard_name']) ?></strong>
            <?php if (!empty($q['elaboration_id'])): ?>
            <button type="button" class="btn btn-sm ms-auto py-0 px-2"
              style="background:#6f42c1;color:white;font-size:.7rem"
              data-bs-toggle="modal"
              data-bs-target="#elabModal<?= $q['id'] ?>">
              <i class="bi bi-lightbulb me-1"></i>Elaborasi
            </button>
            <?php endif; ?>
          </div>

          <p class="fw-semibold mb-3" style="line-height:1.6">
            <?= str_replace(
                '[Nama]',
                '<strong class="text-navy">' . h($dummyName) . '</strong>',
                h($q['question_id_text'])
            ) ?>
          </p>
          <?php if (!empty($q['question_en_text'])): ?>
          <p class="text-muted mb-3" style="line-height:1.6;font-style:italic;font-size:.9rem">
            <?= str_replace(
                '[Name]',
                '<strong>' . h($dummyName) . '</strong>',
                h($q['question_en_text'])
            ) ?>
          </p>
          <?php endif; ?>

          <!-- Grade options -->
          <?php $gradeColors = ['1'=>'danger','2'=>'warning','3'=>'primary','4'=>'success']; ?>
          <?php foreach (range(1,4) as $g): ?>
          <?php $desc = $descriptors[$q['id']][$g] ?? null; ?>
          <div class="mb-1">
            <input type="radio" name="grade[<?= $q['id'] ?>]" id="g<?= $q['id'] ?>_<?= $g ?>"
              value="<?= $g ?>" class="grade-option grade-<?= $g ?>"
              <?= ($savedGrade === $g) ? 'checked' : '' ?>>
            <label for="g<?= $q['id'] ?>_<?= $g ?>">
              <span class="badge bg-<?= $gradeColors[$g] ?> me-2"><?= $g ?></span>
              <strong><?= h($desc['label_id'] ?? "Grade $g") ?></strong>
              <?php if ($desc): ?>
              <br><small class="text-muted ms-4"><?= h($desc['description_id']) ?></small>
              <?php endif; ?>
            </label>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="col-md-4">
          <label class="form-label small text-muted fw-semibold">Catatan (opsional):</label>
          <textarea name="notes[<?= $q['id'] ?>]" class="form-control form-control-sm" rows="4"
            placeholder="Contoh atau bukti pendukung..."></textarea>
          <?php if ($isSaved): ?>
          <div class="mt-1">
            <span class="badge bg-success-subtle text-success border border-success-subtle">
              ✓ Dijawab
            </span>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>

  <!-- ── ELABORASI MODALS ───────────────────────────────── -->
  <?php foreach ($questions as $q):
    if (empty($q['elaboration_id'])) continue;
  ?>
  <div class="modal fade" id="elabModal<?= $q['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header" style="background:#6f42c1;color:white">
          <h6 class="modal-title">
            <i class="bi bi-lightbulb me-2"></i><?= h($q['standard_name']) ?>
          </h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p style="line-height:1.8"><?= nl2br(h($q['elaboration_id'])) ?></p>
          <?php if (!empty($q['elaboration_en'])): ?>
          <hr>
          <p class="text-muted" style="line-height:1.8;font-style:italic">
            <?= nl2br(h($q['elaboration_en'])) ?>
          </p>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- ── SUBMIT BUTTONS ─────────────────────────────────── -->
  <div class="card">
    <div class="card-body">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
        <div class="text-muted small">
          <i class="bi bi-info-circle me-1"></i>
          <?= $answered ?>/<?= $totalQ ?> pertanyaan terjawab.
          <?php if ($answered < $totalQ): ?>
          Masih ada <?= $totalQ - $answered ?> pertanyaan yang belum dijawab.
          <?php else: ?>
          <strong class="text-success">Semua pertanyaan sudah dijawab!</strong>
          <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-outline-secondary">
            <i class="bi bi-save me-1"></i>Simpan Progres (Simulasi)
          </button>
          <?php if ($answered >= $totalQ): ?>
          <button type="submit" name="submit_final" value="1" class="btn btn-navy"
            onclick="return confirm('Ini hanya simulasi — tidak ada data yang disimpan. Lanjut?')">
            <i class="bi bi-check-circle me-1"></i>Selesai & Kumpulkan (Simulasi)
          </button>
          <?php else: ?>
          <button type="button" class="btn btn-secondary" disabled
            title="Jawab semua pertanyaan terlebih dahulu">
            <i class="bi bi-lock me-1"></i>Selesai & Kumpulkan
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</form>
<?php endif; ?>

<?php
$content = ob_get_clean();
$pageTitle = $selectedPkg ? 'Preview: ' . $selectedPkg['name'] : 'Tester — Preview Kuesioner';
pageWrapper($pageTitle, $content);