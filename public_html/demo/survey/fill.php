<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
$user = currentUser();

$assignId = (int)($_GET['id'] ?? 0);
$viewMode = !empty($_GET['view']);

$assignment = Database::fetchOne("
    SELECT a.*, u_ee.name as evaluatee_name, u_ee.role as evaluatee_role,
           p.name as pkg_name, p.code as pkg_code, p.is_self_reflection, p.respondent_type
    FROM assignments a
    JOIN users u_ee ON a.evaluatee_id = u_ee.id
    JOIN packages p ON a.package_id = p.id
    WHERE a.id = ?
", [$assignId]);

if (!$assignment || ($assignment['evaluator_id'] != $user['id'] && !canAccessAdmin())) {
    http_response_code(403); die('Akses ditolak.');
}

// ── GUARD: cek periode masih aktif ───────────────────────────
$period = Database::fetchOne(
    "SELECT status FROM eval_periods WHERE id = ?",
    [$assignment['period_id']]
);
if ($period && $period['status'] === 'closed' && !$viewMode) {
    ob_start(); ?>
    <div class="card">
      <div class="card-body text-center py-5">
        <i class="bi bi-archive display-4 mb-3 d-block text-muted"></i>
        <h5 class="fw-bold text-navy mb-2">Periode Evaluasi Sudah Ditutup</h5>
        <p class="text-muted">
          Kuesioner ini tidak bisa diisi lagi karena periode evaluasi sudah berakhir.<br>
          Data yang sebelumnya belum di-submit tidak dihitung.
        </p>
        <a href="<?= APP_URL ?>/survey/" class="btn btn-outline-secondary mt-2">
          <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar Kuesioner
        </a>
      </div>
    </div>
    <?php
    $content = ob_get_clean();
    pageWrapper('Periode Sudah Ditutup', $content);
    exit;
}

// ── PERUBAHAN UTAMA: pakai COALESCE untuk teks override ───────
// Jika package_questions punya override → pakai override
// Jika tidak → pakai teks master dari questions
$questions = Database::fetchAll("
    SELECT q.id,
           COALESCE(pq.question_id_text_override, q.question_id_text) as question_id_text,
           COALESCE(pq.question_en_text_override,  q.question_en_text)  as question_en_text,
           s.name as standard_name, s.extended_description,
           d.name as domain_name, d.code as domain_code,
           pq.order_num
    FROM package_questions pq
    JOIN questions q ON pq.question_id = q.id
    JOIN standards s ON q.standard_id = s.id
    JOIN domains d ON s.domain_id = d.id
    WHERE pq.package_id = ?
    ORDER BY pq.order_num
", [$assignment['package_id']]);

$descriptors = [];
if (!empty($questions)) {
    $qIds = implode(',', array_column($questions, 'id'));
    $rows = Database::fetchAll("SELECT * FROM grade_descriptors WHERE question_id IN ($qIds) ORDER BY question_id, grade");
    foreach ($rows as $r) {
        $descriptors[$r['question_id']][$r['grade']] = $r;
    }
}

// Get existing responses
$existing = [];
$rows = Database::fetchAll("SELECT question_id, grade, notes FROM responses WHERE assignment_id = ?", [$assignId]);
foreach ($rows as $r) { $existing[$r['question_id']] = $r; }

// Group questions by domain
$byDomain = [];
foreach ($questions as $q) {
    $byDomain[$q['domain_name']][] = $q;
}

// Handle POST (save responses)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$viewMode) {
    $grades = $_POST['grade'] ?? [];
    $notes  = $_POST['notes'] ?? [];
    $isFinal = !empty($_POST['submit_final']);

    foreach ($grades as $qId => $grade) {
        $grade = (int)$grade;
        if ($grade < 1 || $grade > 4) continue;
        $note = $notes[$qId] ?? '';
        $existing2 = Database::fetchOne("SELECT id FROM responses WHERE assignment_id=? AND question_id=?", [$assignId, $qId]);
        if ($existing2) {
            Database::update('responses', ['grade'=>$grade,'notes'=>$note], 'assignment_id=? AND question_id=?', [$assignId, $qId]);
        } else {
            Database::insert('responses', ['assignment_id'=>$assignId,'question_id'=>(int)$qId,'grade'=>$grade,'notes'=>$note]);
        }
    }

    $answeredCount = Database::fetchOne("SELECT COUNT(*) c FROM responses WHERE assignment_id=?", [$assignId])['c'];
    $totalQ = count($questions);

    if ($isFinal && $answeredCount >= $totalQ) {
        Database::update('assignments', ['status'=>'completed','completed_at'=>date('Y-m-d H:i:s')], 'id=?', [$assignId]);
        flash('Kuesioner berhasil diselesaikan! Terima kasih atas penilaian Anda.', 'success');
        header('Location: ' . APP_URL . '/survey/');
        exit;
    } else {
        Database::update('assignments', ['status'=>'in_progress'], 'id=? AND status=?', [$assignId,'pending']);
        flash('Progres disimpan.', 'info');
        $rows = Database::fetchAll("SELECT question_id, grade, notes FROM responses WHERE assignment_id = ?", [$assignId]);
        $existing = [];
        foreach ($rows as $r) { $existing[$r['question_id']] = $r; }
    }
}

$answeredCount = count($existing);
$totalQ = count($questions);
$progress = $totalQ > 0 ? round(($answeredCount/$totalQ)*100) : 0;

ob_start();
?>
<!-- HEADER INFO -->
<div class="card mb-4">
  <div class="card-body">
    <div class="row align-items-center">
      <div class="col-md-8">
        <h5 class="fw-bold text-navy mb-1">
          <?= $assignment['is_self_reflection'] ? '📝 Refleksi Mandiri' : '📋 ' . h($assignment['pkg_name']) ?>
        </h5>
        <p class="text-muted mb-1 small">
          Yang Dinilai: <strong><?= h($assignment['evaluatee_name']) ?></strong>
          <?php if (!$assignment['is_self_reflection']): ?>
          | Peran Anda: <strong><?= h(respondentLabel($assignment['respondent_type'])) ?></strong>
          <?php endif; ?>
        </p>
        <?php if ($viewMode): ?>
        <span class="badge bg-success">Mode Lihat — Jawaban sudah dikunci</span>
        <?php endif; ?>
      </div>
      <div class="col-md-4 text-md-end">
        <div class="mb-1 small"><?= $answeredCount ?>/<?= $totalQ ?> pertanyaan dijawab</div>
        <div class="progress" style="height:12px">
          <div class="progress-bar navy" style="width:<?= $progress ?>%" role="progressbar">
            <?= $progress ?>%
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?= showFlash() ?>

<form method="POST" id="surveyForm">
  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

  <?php $qNum = 0; foreach ($byDomain as $domainName => $domainQs): ?>

  <div class="mb-4">
    <div class="d-flex align-items-center gap-2 mb-3">
      <div style="width:4px;height:24px;background:var(--ktb-navy);border-radius:2px"></div>
      <h6 class="fw-bold text-navy mb-0"><?= h($domainName) ?></h6>
    </div>

    <?php foreach ($domainQs as $q): ?>
    <?php $qNum++; $saved = $existing[$q['id']] ?? null; ?>

    <div class="question-card mb-3 <?= $saved ? 'border-success' : '' ?>">
      <div class="row">
        <div class="col-md-8">
          <div class="d-flex gap-2 mb-2">
            <span class="badge badge-navy" style="min-width:28px"><?= $qNum ?></span>
            <strong class="small text-muted"><?= h($q['standard_name']) ?></strong>
            <?php
            $stdData = Database::fetchOne(
                "SELECT elaboration_id, elaboration_en FROM standards
                 WHERE id=(SELECT standard_id FROM questions WHERE id=?)",
                [$q['id']]
            );
            if (!empty($stdData['elaboration_id'])):
            ?>
            <button type="button" class="btn btn-sm ms-auto py-0 px-2"
              style="background:#6f42c1;color:white;font-size:.7rem"
              data-bs-toggle="modal"
              data-bs-target="#elabModal<?= $q['id'] ?>">
              <i class="bi bi-lightbulb me-1"></i>Elaborasi
            </button>
            <?php endif; ?>
          </div>

          <!-- Pertanyaan ID — dengan COALESCE sudah otomatis pakai override jika ada -->
          <p class="fw-semibold mb-3" style="line-height:1.6">
            <?= str_replace('[Nama]',
                '<strong class="text-navy">' . h($assignment['evaluatee_name']) . '</strong>',
                h($q['question_id_text'])) ?>
          </p>
          <?php if (!empty($q['question_en_text'])): ?>
          <p class="text-muted mb-3" style="line-height:1.6;font-style:italic;font-size:.9rem">
            <?= str_replace('[Name]',
                '<strong>' . h($assignment['evaluatee_name']) . '</strong>',
                h($q['question_en_text'])) ?>
          </p>
          <?php endif; ?>

          <!-- Grade options -->
          <?php $gradeColors = ['1'=>'danger','2'=>'warning','3'=>'primary','4'=>'success']; ?>
          <?php foreach (range(1,4) as $g): ?>
          <?php $desc = $descriptors[$q['id']][$g] ?? null; ?>
          <div class="mb-1">
            <input type="radio" name="grade[<?= $q['id'] ?>]" id="g<?= $q['id'] ?>_<?= $g ?>"
              value="<?= $g ?>" class="grade-option grade-<?= $g ?>"
              <?= ($saved && $saved['grade'] == $g) ? 'checked' : '' ?>
              <?= $viewMode ? 'disabled' : '' ?>>
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
            placeholder="Contoh atau bukti pendukung..."
            <?= $viewMode ? 'readonly' : '' ?>><?= h($saved['notes'] ?? '') ?></textarea>
          <?php if ($saved): ?>
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

  <!-- ELABORASI MODALS -->
  <?php foreach ($questions as $q):
    $stdData = Database::fetchOne(
        "SELECT s.name, s.elaboration_id, s.elaboration_en
         FROM standards s WHERE s.id=(SELECT standard_id FROM questions WHERE id=?)",
        [$q['id']]
    );
    if (empty($stdData['elaboration_id'])) continue;
  ?>
  <div class="modal fade" id="elabModal<?= $q['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header" style="background:#6f42c1;color:white">
          <h6 class="modal-title">
            <i class="bi bi-lightbulb me-2"></i><?= h($stdData['name']) ?>
          </h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p style="line-height:1.8"><?= nl2br(h($stdData['elaboration_id'])) ?></p>
          <?php if ($stdData['elaboration_en']): ?>
          <hr>
          <p class="text-muted" style="line-height:1.8;font-style:italic">
            <?= nl2br(h($stdData['elaboration_en'])) ?>
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

  <!-- SUBMIT BUTTONS -->
  <?php if (!$viewMode): ?>
  <div class="card">
    <div class="card-body">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
        <div class="text-muted small">
          <i class="bi bi-info-circle me-1"></i>
          <?= $answeredCount ?>/<?= $totalQ ?> pertanyaan terjawab.
          <?php if ($answeredCount < $totalQ): ?>
          Masih ada <?= $totalQ - $answeredCount ?> pertanyaan yang belum dijawab.
          <?php else: ?>
          <strong class="text-success">Semua pertanyaan sudah dijawab!</strong>
          <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-outline-secondary">
            <i class="bi bi-save me-1"></i>Simpan Progres
          </button>
          <?php if ($answeredCount >= $totalQ): ?>
          <button type="submit" name="submit_final" value="1" class="btn btn-navy"
            onclick="return confirm('Yakin ingin menyelesaikan? Jawaban tidak dapat diubah setelah dikumpulkan.')">
            <i class="bi bi-check-circle me-1"></i>Selesai & Kumpulkan
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
  <?php else: ?>
  <div class="text-center mt-3">
    <a href="<?= APP_URL ?>/survey/" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
  </div>
  <?php endif; ?>
</form>

<?php
$content = ob_get_clean();
pageWrapper(($viewMode ? 'Lihat Jawaban: ' : 'Mengisi Kuesioner: ') . $assignment['pkg_name'], $content);