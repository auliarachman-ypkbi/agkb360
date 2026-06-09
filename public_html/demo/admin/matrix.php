<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['admin','foundation','superadmin']);

$tab = $_GET['tab'] ?? '';
$evalTypes = Database::fetchAll("SELECT * FROM eval_types ORDER BY id");
if (!$tab && !empty($evalTypes)) $tab = $evalTypes[0]['code'];

$currentEt = null;
foreach ($evalTypes as $et) {
    if ($et['code'] === $tab) { $currentEt = $et; break; }
}

// Semua respondent types — harus cocok dengan nilai di DB
$allRespondentTypes = [
    'atasan'        => 'Yayasan (YPKBI/YPKTB)',
    'leader'        => 'Pimpinan Sekolah',
    'guru'          => 'Guru',
    'ortu'          => 'Komite Orang Tua',
    'siswa'         => 'OSIS / Siswa',
    'student_class' => 'Murid yang Diajar',
];

// ── HANDLE SAVE ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_matrix'])) {
    $etId      = (int)$_POST['eval_type_id'];
    $etCode    = $_POST['eval_type_code'] ?? '';
    $checked   = $_POST['mapping'] ?? [];
    $respTypes = $allRespondentTypes;

    // Get all standards for this eval type
    $standards = Database::fetchAll("
        SELECT s.id FROM standards s
        JOIN domains d ON s.domain_id = d.id
        WHERE d.eval_type_id = ?
    ", [$etId]);
    $standardIds = array_column($standards, 'id');

    // Clear existing mappings for this eval type
    if (!empty($standardIds)) {
        $placeholders = implode(',', array_fill(0, count($standardIds), '?'));
        Database::query(
            "DELETE FROM standard_respondent_mapping WHERE standard_id IN ($placeholders)",
            $standardIds
        );
    }

    // Insert new mappings
    $inserted = 0;
    foreach ($checked as $stdId => $respArr) {
        foreach ($respArr as $respType => $val) {
            if ($val && isset($respTypes[$respType])) {
                Database::insert('standard_respondent_mapping', [
                    'standard_id'     => (int)$stdId,
                    'respondent_type' => $respType,
                    'is_active'       => 1,
                ]);
                $inserted++;
            }
        }
    }

    // Auto-generate package_questions dari mapping baru
    _generatePackages($etId, $etCode, $respTypes, $checked, $standardIds);

    flash("Matriks disimpan — $inserted mapping aktif. Paket otomatis diperbarui.", 'success');
    header('Location: ' . APP_URL . '/admin/matrix.php?tab=' . urlencode($tab));
    exit;
}

// ── AUTO-GENERATE PACKAGES ────────────────────────────────────
function _generatePackages(int $etId, string $etCode, array $respTypes, array $checked, array $standardIds): void {
    foreach ($respTypes as $respType => $respLabel) {
        // Find or create package for this (eval_type, respondent_type)
        $pkg = Database::fetchOne(
            "SELECT * FROM packages WHERE eval_type_id=? AND respondent_type=? AND is_self_reflection=0 AND period_id IS NULL",
            [$etId, $respType]
        );

        if (!$pkg) {
            // Create new package
            $pkgCode = strtoupper(substr($etCode, 0, 1)) . strtoupper(substr($respType, 0, 2));
            $etLabel = $etCode === 'leader' ? 'Pimpinan Sekolah' : 'Guru';
            $pkgName = 'Evaluasi ' . $etLabel . ' – Oleh ' . $respLabel;
            $pkgId = Database::insert('packages', [
                'code'             => $pkgCode,
                'name'             => $pkgName,
                'eval_type_id'     => $etId,
                'respondent_type'  => $respType,
                'is_self_reflection' => 0,
                'question_lang'    => 'both',
            ]);
            // Add default weight
            Database::insert('package_weights', ['package_id' => $pkgId, 'weight' => 1.0, 'notes' => 'Auto-generated']);
        } else {
            $pkgId = $pkg['id'];
        }

        // Clear existing package_questions for this package
        // (only questions from standards of this eval_type)
        if (!empty($standardIds)) {
            $placeholders = implode(',', array_fill(0, count($standardIds), '?'));
            Database::query("
                DELETE pq FROM package_questions pq
                JOIN questions q ON pq.question_id = q.id
                WHERE pq.package_id = ? AND q.standard_id IN ($placeholders)
            ", array_merge([$pkgId], $standardIds));
        }

        // Insert package_questions for checked standards
        $orderNum = 1;
        foreach ($checked as $stdId => $respArr) {
            if (!isset($respArr[$respType]) || !$respArr[$respType]) continue;

            // Ensure question exists for this standard
            $q = Database::fetchOne(
                "SELECT id FROM questions WHERE standard_id=?",
                [(int)$stdId]
            );
            if (!$q) {
                // Auto-create blank question
                $qId = Database::insert('questions', [
                    'standard_id'      => (int)$stdId,
                    'question_id_text' => '',
                    'question_en_text' => '',
                ]);
                // Auto-create blank grade descriptors
                $gradeDefaults = [
                    1 => ['Tidak Terlihat', 'Not Evident'],
                    2 => ['Berkembang',     'Emerging'],
                    3 => ['Cakap',          'Proficient'],
                    4 => ['Teladan',        'Exemplary'],
                ];
                foreach ($gradeDefaults as $g => [$lid, $len]) {
                    Database::insert('grade_descriptors', [
                        'question_id'    => $qId,
                        'grade'          => $g,
                        'label_id'       => $lid,
                        'label_en'       => $len,
                        'description_id' => '',
                        'description_en' => '',
                    ]);
                }
            } else {
                $qId = $q['id'];
            }

            // Insert into package_questions
            $exists = Database::fetchOne(
                "SELECT id FROM package_questions WHERE package_id=? AND question_id=?",
                [$pkgId, $qId]
            );
            if (!$exists) {
                Database::insert('package_questions', [
                    'package_id'  => $pkgId,
                    'question_id' => $qId,
                    'order_num'   => $orderNum,
                ]);
            }
            $orderNum++;
        }
    }
}

// ── FETCH DATA ────────────────────────────────────────────────
$matrixData  = []; // [standard_id][respondent_type] = bool
$domains     = [];
$existingMap = [];

if ($currentEt) {
    // Get existing mappings
    $mappings = Database::fetchAll("
        SELECT srm.standard_id, srm.respondent_type
        FROM standard_respondent_mapping srm
        JOIN standards s ON srm.standard_id = s.id
        JOIN domains d ON s.domain_id = d.id
        WHERE d.eval_type_id = ? AND srm.is_active = 1
    ", [$currentEt['id']]);
    foreach ($mappings as $m) {
        $existingMap[$m['standard_id']][$m['respondent_type']] = true;
    }

    // Get standards grouped by domain
    $rows = Database::fetchAll("
        SELECT s.id as s_id, s.name as s_name, s.order_num as s_order,
               d.id as d_id, d.name as d_name, d.code as d_code, d.order_num as d_order
        FROM standards s
        JOIN domains d ON s.domain_id = d.id
        WHERE d.eval_type_id = ?
        ORDER BY d.order_num, d.id, s.order_num, s.id
    ", [$currentEt['id']]);

    foreach ($rows as $r) {
        $did = $r['d_id'];
        if (!isset($domains[$did])) {
            $domains[$did] = ['name'=>$r['d_name'],'code'=>$r['d_code'],'standards'=>[]];
        }
        $domains[$did]['standards'][$r['s_id']] = $r['s_name'];
    }
}

$currentRespTypes = $allRespondentTypes;

// Label & icon untuk dropdown bahasa
$langOptions = [
    'both' => ['label' => 'ID + EN', 'icon' => '🌐'],
    'id'   => ['label' => 'Indonesia', 'icon' => '🇮🇩'],
    'en'   => ['label' => 'English',   'icon' => '🇬🇧'],
];

ob_start(); ?>

<?= showFlash() ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
  <?php foreach ($evalTypes as $et): ?>
  <li class="nav-item">
    <a class="nav-link <?= $tab===$et['code']?'active fw-bold':'' ?>"
       href="?tab=<?= urlencode($et['code']) ?>">
      <i class="bi bi-grid me-1"></i><?= h($et['name']) ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<?php if (!$currentEt || empty($domains)): ?>
<div class="card">
  <div class="card-body text-center py-5 text-muted">
    <i class="bi bi-grid display-4 mb-3 d-block"></i>
    <p>Belum ada standard untuk tipe ini.</p>
    <a href="<?= APP_URL ?>/admin/foundation.php?tab=standards" class="btn btn-navy">
      Tambah Standard →
    </a>
  </div>
</div>
<?php else: ?>

<div class="alert alert-light border small mb-3">
  <i class="bi bi-info-circle me-1 text-primary"></i>
  <strong>Cara pakai:</strong> Centang kotak di baris standar dan kolom responden untuk menentukan
  siapa yang menilai standar tersebut. Klik <strong>Simpan & Generate Paket</strong> untuk
  memperbarui paket secara otomatis.
</div>

<form method="POST">
  <input type="hidden" name="save_matrix" value="1">
  <input type="hidden" name="eval_type_id" value="<?= $currentEt['id'] ?>">
  <input type="hidden" name="eval_type_code" value="<?= h($currentEt['code']) ?>">

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>
        <i class="bi bi-grid me-2"></i>
        Matriks: <strong><?= h($currentEt['name']) ?></strong>
      </span>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCheckAll">
          <i class="bi bi-check-all me-1"></i>Pilih Semua
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnUncheckAll">
          <i class="bi bi-x me-1"></i>Hapus Semua
        </button>
        <button type="submit" class="btn btn-sm btn-navy">
          <i class="bi bi-save me-1"></i>Simpan & Generate Paket
        </button>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered mb-0" style="min-width:700px">
          <thead>
            <tr style="background:var(--ktb-navy);color:white">
              <th style="min-width:280px;vertical-align:middle">Standard</th>
              <?php foreach ($currentRespTypes as $rType => $rLabel): ?>
              <th class="text-center" style="min-width:110px;vertical-align:middle;font-size:.8rem">
                <?= h($rLabel) ?>
              </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($domains as $did => $domain): ?>

            <!-- Domain row -->
            <tr style="background:#eef1fa">
              <td colspan="<?= count($currentRespTypes) + 1 ?>" class="py-2 px-3">
                <span class="badge me-2" style="background:var(--ktb-navy)">
                  <?= h($domain['code']??'—') ?>
                </span>
                <strong class="text-navy"><?= h($domain['name']) ?></strong>
              </td>
            </tr>

            <!-- Standard rows -->
            <?php foreach ($domain['standards'] as $sid => $sName): ?>
            <tr class="standard-row">
              <td class="ps-4 small" style="vertical-align:middle">
                <?= h($sName) ?>
              </td>
              <?php foreach ($currentRespTypes as $rType => $rLabel): ?>
              <td class="text-center" style="vertical-align:middle">
                <div class="form-check d-flex justify-content-center mb-0">
                  <input type="checkbox"
                    class="form-check-input matrix-cb"
                    name="mapping[<?= $sid ?>][<?= $rType ?>]"
                    value="1"
                    style="width:1.2em;height:1.2em;cursor:pointer"
                    <?= !empty($existingMap[$sid][$rType]) ? 'checked' : '' ?>>
                </div>
              </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>

            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <small class="text-muted">
        <span id="checkedCount">0</span> mapping aktif
      </small>
      <button type="submit" class="btn btn-navy">
        <i class="bi bi-save me-1"></i>Simpan & Generate Paket
      </button>
    </div>
  </div>
</form>

<!-- PREVIEW PAKET YANG SUDAH ADA -->
<?php
$existingPkgs = Database::fetchAll("
    SELECT p.*, COUNT(pq.id) as q_count
    FROM packages p
    LEFT JOIN package_questions pq ON pq.package_id = p.id
    WHERE p.eval_type_id = ? AND p.is_self_reflection = 0 AND p.period_id IS NULL
    GROUP BY p.id
    ORDER BY p.id
", [$currentEt['id']]);
?>
<?php if (!empty($existingPkgs)): ?>
<div class="card mt-4">
  <div class="card-header d-flex align-items-center gap-2">
    <i class="bi bi-boxes me-1"></i>
    <span>Paket yang Terbentuk</span>
    <span class="badge bg-secondary ms-1"><?= count($existingPkgs) ?> paket</span>
    <span class="ms-auto small" style="color:#fff;opacity:.85">
      <i class="bi bi-translate me-1"></i>
      Kolom <strong>Bahasa</strong> menentukan tampilan pertanyaan pada kuesioner responden.
    </span>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0 small">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Nama Paket</th>
          <th>Responden</th>
          <th class="text-center">Jumlah Soal</th>
          <th class="text-center">Bahasa Tampilan</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($existingPkgs as $pkg): ?>
        <?php $currentLang = $pkg['question_lang'] ?? 'both'; ?>
        <tr>
          <td><span class="badge badge-navy"><?= h($pkg['code']) ?></span></td>
          <td><?= h($pkg['name']) ?></td>
          <td class="text-muted"><?= h(respondentLabel($pkg['respondent_type'])) ?></td>
          <td class="text-center"><?= $pkg['q_count'] ?></td>
          <td class="text-center">
            <div class="d-flex align-items-center justify-content-center gap-2">
              <select class="form-select form-select-sm pkg-lang-select"
                      data-pkg-id="<?= $pkg['id'] ?>"
                      style="width:130px;font-size:.8rem">
                <?php foreach ($langOptions as $val => $opt): ?>
                <option value="<?= $val ?>" <?= $currentLang === $val ? 'selected' : '' ?>>
                  <?= $opt['icon'] ?> <?= $opt['label'] ?>
                </option>
                <?php endforeach; ?>
              </select>
              <span class="pkg-lang-status" style="font-size:.75rem;min-width:16px"></span>
            </div>
          </td>
          <td>
            <a href="<?= APP_URL ?>/admin/questions.php?tab=<?= urlencode($tab) ?>"
               class="btn btn-sm btn-outline-primary py-0 px-2"
               title="Edit pertanyaan">
              <i class="bi bi-pencil" style="font-size:.8rem"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const cbs = document.querySelectorAll('.matrix-cb');
  const countEl = document.getElementById('checkedCount');

  function updateCount() {
    const n = document.querySelectorAll('.matrix-cb:checked').length;
    if (countEl) countEl.textContent = n;
  }
  cbs.forEach(cb => cb.addEventListener('change', updateCount));
  updateCount();

  // Check all / Uncheck all
  document.getElementById('btnCheckAll')?.addEventListener('click', () => {
    cbs.forEach(cb => cb.checked = true);
    updateCount();
  });
  document.getElementById('btnUncheckAll')?.addEventListener('click', () => {
    cbs.forEach(cb => cb.checked = false);
    updateCount();
  });

  // Auto-save bahasa paket via AJAX
  document.querySelectorAll('.pkg-lang-select').forEach(sel => {
    sel.addEventListener('change', function () {
      const pkgId  = this.dataset.pkgId;
      const lang   = this.value;
      const status = this.closest('div').querySelector('.pkg-lang-status');
      status.textContent = '…';
      status.style.color = '#888';

      fetch('<?= APP_URL ?>/admin/set_pkg_lang.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'pkg_id=' + encodeURIComponent(pkgId) + '&lang=' + encodeURIComponent(lang)
      })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          status.textContent = '✓';
          status.style.color = '#198754';
        } else {
          status.textContent = '✗';
          status.style.color = '#dc3545';
        }
        setTimeout(() => { status.textContent = ''; }, 2000);
      })
      .catch(() => {
        status.textContent = '✗';
        status.style.color = '#dc3545';
        setTimeout(() => { status.textContent = ''; }, 2000);
      });
    });
  });

  // Highlight row on hover
  document.querySelectorAll('.standard-row').forEach(row => {
    row.addEventListener('mouseenter', () => row.style.background = '#f0f7ff');
    row.addEventListener('mouseleave', () => row.style.background = '');
  });
});
</script>

<?php
$content = ob_get_clean();
pageWrapper('Matriks Mapping Kuesioner', $content);
?>