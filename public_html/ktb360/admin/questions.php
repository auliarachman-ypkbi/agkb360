<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['admin','foundation']);

$tab    = $_GET['tab'] ?? '';
$action = $_POST['action'] ?? '';

$evalTypes = Database::fetchAll("SELECT * FROM eval_types ORDER BY id");
if (!$tab && !empty($evalTypes)) $tab = $evalTypes[0]['code'];

$currentEt = null;
foreach ($evalTypes as $et) {
    if ($et['code'] === $tab) { $currentEt = $et; break; }
}

// ── ACTIONS ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Edit teks pertanyaan untuk paket tertentu (override)
    if ($action === 'edit_package_question') {
        $pqId   = (int)$_POST['pq_id'];
        $textId = trim($_POST['text_id'] ?? '');
        $textEn = trim($_POST['text_en'] ?? '');
        // Cek apakah ini teks master atau override
        $isMaster = !empty($_POST['is_master']);
        if ($isMaster) {
            // Edit pertanyaan master
            $qId = (int)$_POST['question_id'];
            Database::update('questions', [
                'question_id_text' => $textId,
                'question_en_text' => $textEn,
            ], 'id=?', [$qId]);
            flash('Teks master berhasil diperbarui.', 'success');
        } else {
            // Edit override paket
            Database::update('package_questions', [
                'question_id_text_override' => $textId ?: null,
                'question_en_text_override' => $textEn ?: null,
            ], 'id=?', [$pqId]);
            flash('Teks paket berhasil disimpan.', 'success');
        }
        header('Location: ' . APP_URL . '/admin/questions.php?tab=' . urlencode($tab));
        exit;
    }

    // Edit elaborasi
    if ($action === 'edit_elaboration') {
        $stdId = (int)$_POST['standard_id'];
        Database::update('standards', [
            'elaboration_id' => trim($_POST['elaboration_id'] ?? ''),
            'elaboration_en' => trim($_POST['elaboration_en'] ?? ''),
        ], 'id=?', [$stdId]);
        flash('Elaborasi berhasil disimpan.', 'success');
        header('Location: ' . APP_URL . '/admin/questions.php?tab=' . urlencode($tab));
        exit;
    }

    // Edit rubrik
    if ($action === 'edit_descriptor') {
        foreach ($_POST['descriptors'] as $gdId => $data) {
            Database::update('grade_descriptors', [
                'label_id'       => trim($data['label_id']       ?? ''),
                'label_en'       => trim($data['label_en']       ?? ''),
                'description_id' => trim($data['description_id'] ?? ''),
                'description_en' => trim($data['description_en'] ?? ''),
            ], 'id=?', [(int)$gdId]);
        }
        flash('Rubrik berhasil diperbarui.', 'success');
        header('Location: ' . APP_URL . '/admin/questions.php?tab=' . urlencode($tab));
        exit;
    }
}

// ── FETCH PACKAGES & QUESTIONS ────────────────────────────────
$packages = [];
if ($currentEt) {
    $pkgRows = Database::fetchAll("
        SELECT p.*, pw.weight
        FROM packages p
        LEFT JOIN package_weights pw ON pw.package_id = p.id
        WHERE p.eval_type_id = ? AND p.is_self_reflection = 0
        ORDER BY p.id
    ", [$currentEt['id']]);

    foreach ($pkgRows as $pkg) {
        // Ambil semua pertanyaan di paket ini, grouped by domain
        $qRows = Database::fetchAll("
            SELECT
                pq.id as pq_id,
                pq.order_num,
                pq.question_id_text_override,
                pq.question_en_text_override,
                q.id as q_id,
                q.question_id_text as master_id,
                q.question_en_text as master_en,
                s.id as s_id,
                s.name as s_name,
                s.elaboration_id,
                s.elaboration_en,
                d.id as d_id,
                d.name as d_name,
                d.code as d_code
            FROM package_questions pq
            JOIN questions q ON pq.question_id = q.id
            JOIN standards s ON q.standard_id = s.id
            JOIN domains d ON s.domain_id = d.id
            WHERE pq.package_id = ?
            ORDER BY pq.order_num, d.order_num, s.order_num
        ", [$pkg['id']]);

        // Group by domain
        $byDomain = [];
        foreach ($qRows as $q) {
            $byDomain[$q['d_id']]['name']   = $q['d_name'];
            $byDomain[$q['d_id']]['code']   = $q['d_code'];
            $byDomain[$q['d_id']]['items'][] = $q;
        }

        // Load descriptors untuk semua questions
        $qIds = array_unique(array_column($qRows, 'q_id'));
        $descriptors = [];
        if (!empty($qIds)) {
            $rows = Database::fetchAll(
                "SELECT * FROM grade_descriptors WHERE question_id IN (" . implode(',', $qIds) . ") ORDER BY question_id, grade"
            );
            foreach ($rows as $r) $descriptors[$r['question_id']][$r['grade']] = $r;
        }

        $packages[] = array_merge($pkg, [
            'byDomain'    => $byDomain,
            'descriptors' => $descriptors,
            'q_count'     => count($qRows),
        ]);
    }
}

$gradeColors = [1=>'danger',2=>'warning',3=>'primary',4=>'success'];

ob_start(); ?>

<?= showFlash() ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
  <?php foreach ($evalTypes as $et): ?>
  <li class="nav-item">
    <a class="nav-link <?= $tab===$et['code']?'active fw-bold':'' ?>"
       href="?tab=<?= urlencode($et['code']) ?>">
      <i class="bi bi-clipboard-check me-1"></i><?= h($et['name']) ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<?php if (empty($packages)): ?>
<div class="card">
  <div class="card-body text-center py-5 text-muted">
    <i class="bi bi-inbox display-4 mb-3 d-block"></i>
    <p>Belum ada paket untuk tipe ini.</p>
    <a href="<?= APP_URL ?>/admin/matrix.php?tab=<?= urlencode($tab) ?>" class="btn btn-navy">
      Buat via Matriks Mapping →
    </a>
  </div>
</div>
<?php else: ?>

<div class="alert alert-light border small mb-3">
  <i class="bi bi-info-circle me-1 text-primary"></i>
  Teks ditampilkan per paket. Klik <strong>Edit Teks</strong> untuk mengadaptasi bahasa per responden.
  Kosongkan untuk pakai teks master.
</div>

<?php foreach ($packages as $pi => $pkg):
  $cid = 'pkg' . $pkg['id'];
?>

<div class="card mb-3 border-0 shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center py-2"
       style="cursor:pointer;background:var(--ktb-navy);color:white"
       data-bs-toggle="collapse" data-bs-target="#<?= $cid ?>"
       aria-expanded="<?= $pi===0?'true':'false' ?>">
    <div class="d-flex align-items-center gap-2">
      <span class="badge" style="background:var(--ktb-gold);color:var(--ktb-navy);font-size:.8rem">
        <?= h($pkg['code']) ?>
      </span>
      <strong><?= h($pkg['name']) ?></strong>
      <span class="badge bg-white text-navy" style="font-size:.72rem">
        <?= $pkg['q_count'] ?> soal
      </span>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-white text-muted" style="font-size:.7rem">
        <?= h(respondentLabel($pkg['respondent_type'])) ?>
      </span>
      <i class="bi bi-chevron-<?= $pi===0?'up':'down' ?>"></i>
    </div>
  </div>

  <div class="collapse <?= $pi===0?'show':'' ?>" id="<?= $cid ?>">
    <div class="card-body p-0">
      <?php $qNum = 0; foreach ($pkg['byDomain'] as $did => $domain): ?>

      <!-- Domain subheader -->
      <div class="px-3 py-2 d-flex align-items-center gap-2"
           style="background:#f0f4fb;border-bottom:1px solid #dee2e6">
        <span class="badge" style="background:var(--ktb-navy);font-size:.7rem">
          <?= h($domain['code']) ?>
        </span>
        <span class="fw-semibold small text-navy"><?= h($domain['name']) ?></span>
        <span class="badge bg-secondary" style="font-size:.65rem"><?= count($domain['items']) ?></span>
      </div>

      <?php foreach ($domain['items'] as $q):
        $qNum++;
        $activeId = $q['question_id_text_override'] ?: $q['master_id'];
        $activeEn = $q['question_en_text_override'] ?: $q['master_en'];
        $isOverride = !empty($q['question_id_text_override']);
        $descs = $pkg['descriptors'][$q['q_id']] ?? [];
      ?>

      <div class="border-bottom px-3 py-3">
        <div class="row align-items-start">
          <div class="col-md-9">
            <!-- Header standar -->
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="badge badge-navy" style="min-width:26px"><?= $qNum ?></span>
              <span class="small fw-semibold text-muted"><?= h($q['s_name']) ?></span>
              <?php if ($isOverride): ?>
              <span class="badge bg-warning text-dark" style="font-size:.65rem">Adaptasi Paket</span>
              <?php else: ?>
              <span class="badge bg-light text-muted border" style="font-size:.65rem">Master</span>
              <?php endif; ?>
              <?php if (!empty($q['elaboration_id'])): ?>
              <button type="button" class="btn btn-sm py-0 px-2 ms-auto"
                style="background:#6f42c1;color:white;font-size:.7rem"
                data-bs-toggle="modal" data-bs-target="#elab<?= $q['s_id'] ?>">
                <i class="bi bi-lightbulb me-1"></i>Elaborasi
              </button>
              <?php endif; ?>
            </div>

            <!-- Teks aktif -->
            <p class="mb-1 fw-semibold" style="line-height:1.6;font-size:.95rem">
              <?= str_replace('[Nama]', '<span class="badge" style="background:#001f3e;color:#ffc901">[Nama]</span>', h($activeId)) ?>
            </p>
            <?php if ($activeEn): ?>
            <p class="mb-0 text-muted" style="font-size:.85rem;font-style:italic;line-height:1.5">
              <?= str_replace('[Name]', '<span class="badge" style="background:#001f3e;color:#ffc901">[Name]</span>', h($activeEn)) ?>
            </p>
            <?php endif; ?>
          </div>

          <!-- Aksi -->
          <div class="col-md-3 text-md-end mt-2 mt-md-0 d-flex flex-md-column gap-2 justify-content-end">
            <button class="btn btn-sm btn-outline-primary"
              data-bs-toggle="modal" data-bs-target="#editQ<?= $q['pq_id'] ?>">
              <i class="bi bi-pencil me-1"></i>Edit Teks
            </button>
            <button class="btn btn-sm btn-outline-secondary"
              data-bs-toggle="modal" data-bs-target="#rubrik<?= $q['q_id'] ?>">
              <i class="bi bi-table me-1"></i>Rubrik
            </button>
          </div>
        </div>
      </div>

      <!-- ── MODAL: Edit Teks ──────────────────────────── -->
      <div class="modal fade" id="editQ<?= $q['pq_id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header" style="background:var(--ktb-navy);color:white">
              <h6 class="modal-title">
                <i class="bi bi-pencil me-2"></i>
                [<?= h($pkg['code']) ?>] <?= h($q['s_name']) ?>
              </h6>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
              <input type="hidden" name="action" value="edit_package_question">
              <input type="hidden" name="pq_id" value="<?= $q['pq_id'] ?>">
              <input type="hidden" name="question_id" value="<?= $q['q_id'] ?>">
              <div class="modal-body">
                <!-- Referensi master -->
                <?php if ($isOverride): ?>
                <div class="alert alert-light border small mb-3">
                  <strong>Teks Master:</strong> <?= h($q['master_id']) ?>
                  <button type="submit" name="is_master" value="" class="btn btn-sm btn-link p-0 ms-2"
                    onclick="document.querySelector('#editQ<?= $q['pq_id'] ?> [name=text_id]').value='';
                             document.querySelector('#editQ<?= $q['pq_id'] ?> [name=text_en]').value=''">
                    Reset ke master
                  </button>
                </div>
                <?php endif; ?>

                <div class="mb-3">
                  <label class="form-label fw-semibold">
                    🇮🇩 Teks Bahasa Indonesia
                    <small class="text-muted fw-normal">(untuk paket <?= h($pkg['code']) ?>)</small>
                  </label>
                  <textarea name="text_id" class="form-control" rows="4" style="line-height:1.7"
                    placeholder="Kosongkan untuk pakai teks master..."><?= h($q['question_id_text_override'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">🇬🇧 English Text</label>
                  <textarea name="text_en" class="form-control" rows="4" style="font-style:italic;line-height:1.7"
                    placeholder="Leave empty to use master text..."><?= h($q['question_en_text_override'] ?? '') ?></textarea>
                </div>
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" id="isMaster<?= $q['pq_id'] ?>"
                    name="is_master" value="1">
                  <label class="form-check-label small text-muted" for="isMaster<?= $q['pq_id'] ?>">
                    Simpan sebagai teks <strong>master</strong> (berlaku ke semua paket yang tidak punya adaptasi)
                  </label>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-navy">
                  <i class="bi bi-save me-1"></i>Simpan
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- ── MODAL: Rubrik ─────────────────────────────── -->
      <div class="modal fade" id="rubrik<?= $q['q_id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-xl">
          <div class="modal-content">
            <div class="modal-header" style="background:var(--ktb-navy);color:white">
              <h6 class="modal-title">Rubrik — <?= h($q['s_name']) ?></h6>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
              <input type="hidden" name="action" value="edit_descriptor">
              <div class="modal-body">
                <div class="row g-3">
                  <?php foreach ([1,2,3,4] as $g):
                    $desc = $descs[$g] ?? null;
                    $cls  = $gradeColors[$g];
                  ?>
                  <div class="col-md-6">
                    <div class="card border-<?= $cls ?>">
                      <div class="card-header bg-<?= $cls ?> text-white py-1 small fw-bold">
                        Grade <?= $g ?> <?php if($desc) echo '— ' . h($desc['label_id']); ?>
                      </div>
                      <div class="card-body p-2">
                        <?php if ($desc): ?>
                        <input type="hidden" name="descriptors[<?= $desc['id'] ?>][grade]" value="<?= $g ?>">
                        <div class="row g-2">
                          <div class="col-6">
                            <label class="form-label small mb-1">🇮🇩 Label</label>
                            <input type="text" name="descriptors[<?= $desc['id'] ?>][label_id]"
                              class="form-control form-control-sm" value="<?= h($desc['label_id']) ?>">
                          </div>
                          <div class="col-6">
                            <label class="form-label small mb-1">🇬🇧 Label</label>
                            <input type="text" name="descriptors[<?= $desc['id'] ?>][label_en]"
                              class="form-control form-control-sm" value="<?= h($desc['label_en']) ?>">
                          </div>
                          <div class="col-6">
                            <label class="form-label small mb-1">🇮🇩 Deskripsi</label>
                            <textarea name="descriptors[<?= $desc['id'] ?>][description_id]"
                              class="form-control form-control-sm" rows="3"><?= h($desc['description_id']) ?></textarea>
                          </div>
                          <div class="col-6">
                            <label class="form-label small mb-1">🇬🇧 Description</label>
                            <textarea name="descriptors[<?= $desc['id'] ?>][description_en]"
                              class="form-control form-control-sm" rows="3"
                              style="font-style:italic"><?= h($desc['description_en']) ?></textarea>
                          </div>
                        </div>
                        <?php else: ?>
                        <p class="text-muted small mb-0">Rubrik belum ada.</p>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-navy">
                  <i class="bi bi-save me-1"></i>Simpan Rubrik
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- ── MODAL: Elaborasi ──────────────────────────── -->
      <?php if (!empty($q['elaboration_id'])): ?>
      <div class="modal fade" id="elab<?= $q['s_id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header" style="background:#6f42c1;color:white">
              <h6 class="modal-title">
                <i class="bi bi-lightbulb me-2"></i><?= h($q['s_name']) ?>
              </h6>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
              <input type="hidden" name="action" value="edit_elaboration">
              <input type="hidden" name="standard_id" value="<?= $q['s_id'] ?>">
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label fw-semibold">🇮🇩 Elaborasi Bahasa Indonesia</label>
                  <textarea name="elaboration_id" class="form-control" rows="6"
                    style="line-height:1.7"><?= h($q['elaboration_id']) ?></textarea>
                </div>
                <div>
                  <label class="form-label fw-semibold">🇬🇧 Elaboration in English</label>
                  <textarea name="elaboration_en" class="form-control" rows="6"
                    style="font-style:italic;line-height:1.7"><?= h($q['elaboration_en']) ?></textarea>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn" style="background:#6f42c1;color:white">
                  <i class="bi bi-save me-1"></i>Simpan Elaborasi
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php endforeach; // questions ?>
      <?php endforeach; // domains ?>
    </div>
  </div>
</div>

<?php endforeach; // packages ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
pageWrapper('Editor Kuesioner per Paket', $content);
?>