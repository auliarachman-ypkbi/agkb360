<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin','admin','foundation']);

$action   = $_POST['action'] ?? '';
$viewPkg  = (int)($_GET['pkg'] ?? 0);
$filterEt = $_GET['et'] ?? '';

// ── ACTIONS ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save_pkg_question') {
        $pqId   = (int)$_POST['pq_id'];
        $textId = trim($_POST['text_id'] ?? '') ?: null;
        $textEn = trim($_POST['text_en'] ?? '') ?: null;
        Database::update('package_questions', [
            'question_id_text_override' => $textId,
            'question_en_text_override' => $textEn,
        ], 'id=?', [$pqId]);
        flash('Teks paket berhasil disimpan.', 'success');
        header('Location: ' . APP_URL . '/admin/questions_packages.php?pkg=' . $viewPkg . '&et=' . $filterEt);
        exit;
    }
}

// ── FETCH PACKAGES ────────────────────────────────────────────
$evalTypes = Database::fetchAll("SELECT * FROM eval_types ORDER BY id");

// Filter via PHP, bukan SQL injection risk
$whereEt = $filterEt ? "AND et.code = " . "'" . addslashes($filterEt) . "'" : '';
$allPackages = Database::fetchAll("
    SELECT p.*, et.name as et_name, et.code as et_code,
           MAX(pw.weight) as weight,
           COUNT(pq.id) as q_count
    FROM packages p
    JOIN eval_types et ON et.id = p.eval_type_id
    LEFT JOIN package_weights pw ON pw.package_id = p.id
    LEFT JOIN package_questions pq ON pq.package_id = p.id
    WHERE p.period_id IS NULL AND p.is_self_reflection = 0
    GROUP BY p.id, p.code, p.name, p.eval_type_id, p.respondent_type,
             p.description, p.is_self_reflection, et.name, et.code
    ORDER BY et.id, p.id
");

// Group by eval type
$byEt = [];
foreach ($allPackages as $pkg) {
    $byEt[$pkg['et_code']]['name']     = $pkg['et_name'];
    $byEt[$pkg['et_code']]['packages'][] = $pkg;
}

// ── VIEW DETAIL PAKET ─────────────────────────────────────────
$pkgDetail = null;
$pkgQuestions = [];
if ($viewPkg) {
    $pkgDetail = Database::fetchOne("
        SELECT p.*, et.name as et_name, pw.weight
        FROM packages p
        JOIN eval_types et ON et.id = p.eval_type_id
        LEFT JOIN package_weights pw ON pw.package_id = p.id
        WHERE p.id = ?
    ", [$viewPkg]);

    if ($pkgDetail) {
        $rows = Database::fetchAll("
            SELECT
                pq.id as pq_id, pq.order_num,
                pq.question_id_text_override,
                pq.question_en_text_override,
                q.id as q_id,
                q.question_id_text as master_id,
                q.question_en_text as master_en,
                s.id as s_id, s.name as s_name,
                d.name as d_name, d.code as d_code
            FROM package_questions pq
            JOIN questions q ON pq.question_id = q.id
            JOIN standards s ON q.standard_id = s.id
            JOIN domains d ON s.domain_id = d.id
            WHERE pq.package_id = ?
            ORDER BY pq.order_num
        ", [$viewPkg]);

        $byDomain = [];
        foreach ($rows as $r) {
            $byDomain[$r['d_code']]['name']  = $r['d_name'];
            $byDomain[$r['d_code']]['code']  = $r['d_code'];
            $byDomain[$r['d_code']]['items'][] = $r;
        }
        $pkgQuestions = $byDomain;
    }
}

// Key sesuai respondent_type di DB
$respColors = [
    'atasan'        => ['bg'=>'#1040B0','label'=>'Yayasan (YPKBI/YPKTB)'],
    'leader'        => ['bg'=>'#7c3aed','label'=>'Pimpinan Sekolah'],
    'peer'          => ['bg'=>'#6f42c1','label'=>'Rekan Sejawat'],
    'guru'          => ['bg'=>'#0891b2','label'=>'Guru'],
    'ortu'          => ['bg'=>'#d97706','label'=>'Komite Orang Tua'],
    'siswa'         => ['bg'=>'#16a34a','label'=>'OSIS / Siswa'],
    'student_class' => ['bg'=>'#0d9488','label'=>'Murid yang Diajar'],
    'self'          => ['bg'=>'#64748b','label'=>'Self Evaluation'],
];

ob_start();

// ── VIEW: DETAIL PAKET ────────────────────────────────────────
if ($viewPkg && $pkgDetail): ?>

<div class="mb-3">
  <a href="<?= APP_URL ?>/admin/questions_packages.php?et=<?= urlencode($filterEt) ?>"
     class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar Paket
  </a>
</div>

<?= showFlash() ?>

<div class="card mb-3">
  <div class="card-body py-3 d-flex align-items-center gap-3">
    <?php
    $rc = $respColors[$pkgDetail['respondent_type']] ?? ['bg'=>'#888','label'=>$pkgDetail['respondent_type']];
    ?>
    <div style="width:52px;height:52px;border-radius:12px;background:<?= $rc['bg'] ?>;display:flex;align-items:center;justify-content:center;color:white;font-size:1.2rem;font-weight:700;flex-shrink:0">
      <?= strtoupper(substr($pkgDetail['code'],0,2)) ?>
    </div>
    <div class="flex-grow-1">
      <h6 class="fw-bold text-navy mb-0"><?= h($pkgDetail['name']) ?></h6>
      <div class="d-flex gap-2 mt-1 flex-wrap">
        <span class="badge" style="background:<?= $rc['bg'] ?>;font-size:.7rem"><?= $rc['label'] ?></span>
        <span class="badge bg-secondary" style="font-size:.7rem"><?= $pkgDetail['et_name'] ?></span>
        <span class="badge bg-light text-dark border" style="font-size:.7rem">
          <?= count($pkgQuestions) > 0 ? array_sum(array_map(fn($d)=>count($d['items']),$pkgQuestions)) : 0 ?> soal
        </span>
      </div>
    </div>
  </div>
</div>

<?php $qNum = 0; foreach ($pkgQuestions as $dCode => $domain): ?>
<div class="card mb-3">
  <div class="card-header py-2 d-flex align-items-center gap-2" style="background:#f0f4fb">
    <span class="badge badge-navy"><?= h($domain['code']) ?></span>
    <strong class="text-navy small"><?= h($domain['name']) ?></strong>
  </div>
  <div class="card-body p-0">
    <?php foreach ($domain['items'] as $q):
      $qNum++;
      $activeId  = $q['question_id_text_override'] ?: $q['master_id'];
      $activeEn  = $q['question_en_text_override'] ?: $q['master_en'];
      $isOverride = !empty($q['question_id_text_override']);
    ?>
    <div class="border-bottom px-3 py-3">
      <div class="row align-items-start">
        <div class="col-md-9">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge badge-navy" style="min-width:26px"><?= $qNum ?></span>
            <span class="small fw-semibold text-muted"><?= h($q['s_name']) ?></span>
            <?php if ($isOverride): ?>
            <span class="badge bg-warning text-dark" style="font-size:.65rem">Adaptasi Paket</span>
            <?php else: ?>
            <span class="badge bg-light text-muted border" style="font-size:.65rem">Pakai Master</span>
            <?php endif; ?>
          </div>
          <p class="mb-1 small" style="line-height:1.6">
            <?= str_replace('[Nama]','<span class="badge" style="background:#001f3e;color:#ffc901">[Nama]</span>',h($activeId)) ?>
          </p>
          <?php if ($activeEn): ?>
          <p class="mb-0 text-muted" style="font-size:.8rem;font-style:italic">
            <?= str_replace('[Name]','<span class="badge" style="background:#001f3e;color:#ffc901">[Name]</span>',h($activeEn)) ?>
          </p>
          <?php endif; ?>
        </div>
        <div class="col-md-3 text-md-end mt-2 mt-md-0">
          <button class="btn btn-sm btn-outline-primary"
            data-bs-toggle="modal" data-bs-target="#editPQ<?= $q['pq_id'] ?>">
            <i class="bi bi-pencil me-1"></i>Edit Teks
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Edit -->
    <div class="modal fade" id="editPQ<?= $q['pq_id'] ?>" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header" style="background:var(--ktb-navy);color:white">
            <h6 class="modal-title">Edit Teks Paket — <?= h($q['s_name']) ?></h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST">
            <input type="hidden" name="action" value="save_pkg_question">
            <input type="hidden" name="pq_id" value="<?= $q['pq_id'] ?>">
            <div class="modal-body">
              <div class="alert alert-light border small mb-3">
                <strong>Master:</strong> <?= h($q['master_id'] ?: '(belum diisi)') ?>
              </div>
              <p class="text-muted small">Kosongkan untuk kembali ke teks master.</p>
              <div class="mb-3">
                <label class="form-label fw-semibold">🇮🇩 Adaptasi Bahasa Indonesia</label>
                <textarea name="text_id" class="form-control" rows="4"
                  style="line-height:1.7"
                  placeholder="Kosongkan = pakai master..."><?= h($q['question_id_text_override']??'') ?></textarea>
              </div>
              <div>
                <label class="form-label fw-semibold">🇬🇧 Adaptasi English</label>
                <textarea name="text_en" class="form-control" rows="4"
                  style="font-style:italic;line-height:1.7"
                  placeholder="Leave empty = use master..."><?= h($q['question_en_text_override']??'') ?></textarea>
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

    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<?php
// ── VIEW: DAFTAR PAKET (CARD/FOLDER) ─────────────────────────
else: ?>

<?= showFlash() ?>

<!-- Filter -->
<div class="d-flex gap-2 mb-4 flex-wrap">
  <a href="?et=" class="btn btn-sm <?= !$filterEt?'btn-navy':'btn-outline-secondary' ?>">Semua</a>
  <?php foreach ($evalTypes as $et): ?>
  <a href="?et=<?= urlencode($et['code']) ?>"
     class="btn btn-sm <?= $filterEt===$et['code']?'btn-navy':'btn-outline-secondary' ?>">
    <?= h($et['name']) ?>
  </a>
  <?php endforeach; ?>
</div>

<?php foreach ($byEt as $etCode => $etGroup):
  if ($filterEt && $filterEt !== $etCode) continue;
?>
<h6 class="fw-bold text-navy mb-3"><?= h($etGroup['name']) ?></h6>
<div class="row g-3 mb-4">
  <?php foreach ($etGroup['packages'] as $pkg):
    $rc = $respColors[$pkg['respondent_type']] ?? ['bg'=>'#888','label'=>ucfirst($pkg['respondent_type'])];
    $overrideCount = Database::fetchOne("
        SELECT COUNT(*) c FROM package_questions
        WHERE package_id=? AND question_id_text_override IS NOT NULL AND question_id_text_override != ''
    ", [$pkg['id']])['c'];
  ?>
  <div class="col-6 col-md-4 col-lg-3">
    <a href="?pkg=<?= $pkg['id'] ?>&et=<?= urlencode($etCode) ?>"
       class="text-decoration-none">
      <div class="card h-100 border-0 shadow-sm package-card"
           style="transition:all .2s;cursor:pointer"
           onmouseenter="this.style.transform='translateY(-4px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,.12)'"
           onmouseleave="this.style.transform='';this.style.boxShadow=''">
        <div class="card-body text-center p-3">
          <!-- Folder icon -->
          <div style="width:56px;height:56px;border-radius:14px;background:<?= $rc['bg'] ?>;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem">
            <i class="bi bi-folder-fill text-white"></i>
          </div>
          <span class="badge mb-2" style="background:<?= $rc['bg'] ?>;font-size:.72rem">
            <?= h($pkg['code']) ?>
          </span>
          <p class="small fw-semibold text-navy mb-1 lh-sm" style="font-size:.82rem">
            <?= h($pkg['name']) ?>
          </p>
          <div class="d-flex justify-content-center gap-2 mt-2">
            <span class="badge bg-light text-dark border" style="font-size:.65rem">
              <i class="bi bi-question-circle me-1"></i><?= $pkg['q_count'] ?> soal
            </span>
            <?php if ($overrideCount > 0): ?>
            <span class="badge bg-warning text-dark" style="font-size:.65rem">
              <?= $overrideCount ?> adaptasi
            </span>
            <?php endif; ?>
          </div>
        </div>
        <div class="card-footer py-1 text-center"
             style="background:<?= $rc['bg'] ?>18;border-top:1px solid <?= $rc['bg'] ?>22">
          <small style="color:<?= $rc['bg'] ?>;font-weight:600;font-size:.7rem">
            <?= $rc['label'] ?>
          </small>
        </div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php endif; ?>

<?php $content = ob_get_clean(); pageWrapper('Paket Pertanyaan', $content); ?>