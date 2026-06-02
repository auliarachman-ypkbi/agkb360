<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['admin','foundation']);

$action  = $_POST['action'] ?? '';
$filterEt = $_GET['et'] ?? '';

// ── ACTIONS ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save_master') {
        $qId   = (int)$_POST['question_id'];
        $textId = trim($_POST['question_id_text'] ?? '');
        $textEn = trim($_POST['question_en_text'] ?? '');
        Database::update('questions', [
            'question_id_text' => $textId,
            'question_en_text' => $textEn,
        ], 'id=?', [$qId]);
        flash('Pertanyaan master berhasil disimpan.', 'success');
        header('Location: ' . APP_URL . '/admin/questions_master.php?et=' . urlencode($filterEt));
        exit;
    }
    if ($action === 'save_elaboration') {
        $stdId = (int)$_POST['standard_id'];
        Database::update('standards', [
            'elaboration_id' => trim($_POST['elaboration_id'] ?? ''),
            'elaboration_en' => trim($_POST['elaboration_en'] ?? ''),
        ], 'id=?', [$stdId]);
        flash('Elaborasi berhasil disimpan.', 'success');
        header('Location: ' . APP_URL . '/admin/questions_master.php?et=' . urlencode($filterEt));
        exit;
    }
    if ($action === 'save_rubrik') {
        foreach ($_POST['descriptors'] as $gdId => $data) {
            Database::update('grade_descriptors', [
                'label_id'       => trim($data['label_id']       ?? ''),
                'label_en'       => trim($data['label_en']       ?? ''),
                'description_id' => trim($data['description_id'] ?? ''),
                'description_en' => trim($data['description_en'] ?? ''),
            ], 'id=?', [(int)$gdId]);
        }
        flash('Rubrik berhasil disimpan.', 'success');
        header('Location: ' . APP_URL . '/admin/questions_master.php?et=' . urlencode($filterEt));
        exit;
    }
}

// ── FETCH ─────────────────────────────────────────────────────
$evalTypes = Database::fetchAll("SELECT * FROM eval_types ORDER BY id");

$whereEt = $filterEt ? "AND et.code = '$filterEt'" : '';
$rows = Database::fetchAll("
    SELECT
        q.id as q_id, q.question_id_text, q.question_en_text,
        s.id as s_id, s.name as s_name, s.order_num as s_order,
        s.elaboration_id, s.elaboration_en,
        d.id as d_id, d.name as d_name, d.code as d_code, d.order_num as d_order,
        et.id as et_id, et.name as et_name, et.code as et_code
    FROM questions q
    JOIN standards s ON q.standard_id = s.id
    JOIN domains d ON s.domain_id = d.id
    JOIN eval_types et ON d.eval_type_id = et.id
    WHERE 1=1 $whereEt
    ORDER BY et.id, d.order_num, d.id, s.order_num, s.id
");

// Group: et > domain > standard
$grouped = [];
foreach ($rows as $r) {
    $etCode = $r['et_code'];
    $did    = $r['d_id'];
    $sid    = $r['s_id'];
    if (!isset($grouped[$etCode])) {
        $grouped[$etCode] = ['name'=>$r['et_name'],'domains'=>[]];
    }
    if (!isset($grouped[$etCode]['domains'][$did])) {
        $grouped[$etCode]['domains'][$did] = ['name'=>$r['d_name'],'code'=>$r['d_code'],'standards'=>[]];
    }
    if (!isset($grouped[$etCode]['domains'][$did]['standards'][$sid])) {
        $grouped[$etCode]['domains'][$did]['standards'][$sid] = [
            'name'           => $r['s_name'],
            'elaboration_id' => $r['elaboration_id'],
            'elaboration_en' => $r['elaboration_en'],
            'q_id'           => $r['q_id'],
            'text_id'        => $r['question_id_text'],
            'text_en'        => $r['question_en_text'],
        ];
        // Load descriptors
        $grouped[$etCode]['domains'][$did]['standards'][$sid]['descriptors'] =
            Database::fetchAll("SELECT * FROM grade_descriptors WHERE question_id=? ORDER BY grade", [$r['q_id']]);
        // Count paket yang pakai
        $grouped[$etCode]['domains'][$did]['standards'][$sid]['pkg_count'] =
            Database::fetchOne("SELECT COUNT(*) c FROM package_questions WHERE question_id=?", [$r['q_id']])['c'];
    }
}

$gradeColors = [1=>'danger',2=>'warning',3=>'primary',4=>'success'];
ob_start(); ?>

<?= showFlash() ?>

<!-- Filter by eval type -->
<div class="d-flex gap-2 mb-4 flex-wrap">
  <a href="?et=" class="btn btn-sm <?= !$filterEt?'btn-navy':'btn-outline-secondary' ?>">
    Semua Tipe
  </a>
  <?php foreach ($evalTypes as $et): ?>
  <a href="?et=<?= urlencode($et['code']) ?>"
     class="btn btn-sm <?= $filterEt===$et['code']?'btn-navy':'btn-outline-secondary' ?>">
    <?= h($et['name']) ?>
  </a>
  <?php endforeach; ?>
  <span class="ms-auto text-muted small align-self-center">
    <?= array_sum(array_map(fn($et)=>array_sum(array_map(fn($d)=>count($d['standards']),$et['domains'])),$grouped)) ?> master pertanyaan
  </span>
</div>

<?php foreach ($grouped as $etCode => $et):
  $qNum = 0;
?>
<div class="mb-4">
  <h6 class="fw-bold text-navy mb-3 d-flex align-items-center gap-2">
    <span class="badge" style="background:var(--ktb-navy)">
      <?= strtoupper($etCode) ?>
    </span>
    <?= h($et['name']) ?>
  </h6>

  <?php foreach ($et['domains'] as $did => $domain): ?>
  <div class="card mb-3">
    <div class="card-header py-2 d-flex align-items-center gap-2"
         style="background:#f0f4fb">
      <span class="badge badge-navy"><?= h($domain['code']??'—') ?></span>
      <strong class="text-navy small"><?= h($domain['name']) ?></strong>
      <span class="badge bg-secondary ms-auto"><?= count($domain['standards']) ?> standar</span>
    </div>
    <div class="card-body p-0">
      <?php foreach ($domain['standards'] as $sid => $std):
        $qNum++;
        $isEmpty = empty(trim($std['text_id']));
      ?>
      <div class="border-bottom px-3 py-3 <?= $isEmpty?'bg-warning-subtle':'' ?>">
        <div class="row align-items-start">
          <div class="col-md-8">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="badge badge-navy" style="min-width:28px"><?= $qNum ?></span>
              <strong class="small"><?= h($std['name']) ?></strong>
              <?php if ($isEmpty): ?>
              <span class="badge bg-warning text-dark" style="font-size:.65rem">Belum diisi</span>
              <?php endif; ?>
              <span class="badge bg-light text-muted border" style="font-size:.65rem">
                <?= $std['pkg_count'] ?> paket
              </span>
            </div>
            <?php if (!$isEmpty): ?>
            <p class="mb-1 small" style="line-height:1.6">
              <?= str_replace('[Nama]','<span class="badge" style="background:#001f3e;color:#ffc901">[Nama]</span>', h($std['text_id'])) ?>
            </p>
            <?php if ($std['text_en']): ?>
            <p class="mb-0 text-muted" style="font-size:.8rem;font-style:italic">
              <?= str_replace('[Name]','<span class="badge" style="background:#001f3e;color:#ffc901">[Name]</span>', h($std['text_en'])) ?>
            </p>
            <?php endif; ?>
            <?php else: ?>
            <p class="text-muted small mb-0 fst-italic">Pertanyaan master belum diisi</p>
            <?php endif; ?>
          </div>
          <div class="col-md-4 text-md-end mt-2 mt-md-0 d-flex flex-md-column gap-1 justify-content-end">
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
              data-bs-target="#editMaster<?= $std['q_id'] ?>">
              <i class="bi bi-pencil me-1"></i>Edit Master
            </button>
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
              data-bs-target="#rubrik<?= $std['q_id'] ?>">
              <i class="bi bi-table me-1"></i>Rubrik
            </button>
            <button class="btn btn-sm btn-outline-secondary" style="border-color:#6f42c1;color:#6f42c1"
              data-bs-toggle="modal" data-bs-target="#elab<?= $sid ?>">
              <i class="bi bi-lightbulb me-1"></i>Elaborasi
            </button>
          </div>
        </div>
      </div>

      <!-- Modal: Edit Master -->
      <div class="modal fade" id="editMaster<?= $std['q_id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header" style="background:var(--ktb-navy);color:white">
              <h6 class="modal-title"><i class="bi bi-pencil me-2"></i><?= h($std['name']) ?></h6>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
              <input type="hidden" name="action" value="save_master">
              <input type="hidden" name="question_id" value="<?= $std['q_id'] ?>">
              <div class="modal-body">
                <div class="alert alert-info small">
                  <i class="bi bi-info-circle me-1"></i>
                  Teks master berlaku di semua paket yang tidak punya adaptasi khusus.
                  Gunakan <strong>[Nama]</strong> / <strong>[Name]</strong> untuk nama yang dievaluasi.
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">🇮🇩 Bahasa Indonesia</label>
                  <textarea name="question_id_text" class="form-control" rows="4"
                    style="line-height:1.7"><?= h($std['text_id']) ?></textarea>
                </div>
                <div>
                  <label class="form-label fw-semibold">🇬🇧 English</label>
                  <textarea name="question_en_text" class="form-control" rows="4"
                    style="font-style:italic;line-height:1.7"><?= h($std['text_en']) ?></textarea>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-navy">
                  <i class="bi bi-save me-1"></i>Simpan Master
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Modal: Rubrik -->
      <div class="modal fade" id="rubrik<?= $std['q_id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-xl">
          <div class="modal-content">
            <div class="modal-header" style="background:var(--ktb-navy);color:white">
              <h6 class="modal-title">Rubrik — <?= h($std['name']) ?></h6>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
              <input type="hidden" name="action" value="save_rubrik">
              <div class="modal-body">
                <div class="row g-3">
                  <?php foreach ([1,2,3,4] as $g):
                    $desc = null;
                    foreach ($std['descriptors'] as $d) { if ($d['grade']==$g) { $desc=$d; break; } }
                    $cls = $gradeColors[$g];
                  ?>
                  <div class="col-md-6">
                    <div class="card border-<?= $cls ?>">
                      <div class="card-header bg-<?= $cls ?> text-white py-1 small fw-bold">
                        Grade <?= $g ?>
                      </div>
                      <div class="card-body p-2">
                        <?php if ($desc): ?>
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
                        <p class="text-muted small mb-0">Belum ada rubrik untuk grade ini.</p>
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

      <!-- Modal: Elaborasi -->
      <div class="modal fade" id="elab<?= $sid ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header" style="background:#6f42c1;color:white">
              <h6 class="modal-title"><i class="bi bi-lightbulb me-2"></i><?= h($std['name']) ?></h6>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
              <input type="hidden" name="action" value="save_elaboration">
              <input type="hidden" name="standard_id" value="<?= $sid ?>">
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label fw-semibold">🇮🇩 Elaborasi Indonesia</label>
                  <textarea name="elaboration_id" class="form-control" rows="6"
                    style="line-height:1.7"><?= h($std['elaboration_id']??'') ?></textarea>
                </div>
                <div>
                  <label class="form-label fw-semibold">🇬🇧 Elaboration English</label>
                  <textarea name="elaboration_en" class="form-control" rows="6"
                    style="font-style:italic;line-height:1.7"><?= h($std['elaboration_en']??'') ?></textarea>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn" style="background:#6f42c1;color:white">
                  <i class="bi bi-save me-1"></i>Simpan
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <?php endforeach; // standards ?>
    </div>
  </div>
  <?php endforeach; // domains ?>
</div>
<?php endforeach; // eval types ?>

<?php $content = ob_get_clean(); pageWrapper('Master Pertanyaan', $content); ?>
