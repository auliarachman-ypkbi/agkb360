<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin','admin','foundation']);

$tab = $_GET['tab'] ?? '';
$evalTypes = Database::fetchAll("SELECT * FROM eval_types ORDER BY id");
if (!$tab && !empty($evalTypes)) $tab = $evalTypes[0]['code'];

$currentEt = null;
foreach ($evalTypes as $et) {
    if ($et['code'] === $tab) { $currentEt = $et; break; }
}

// ── KOLOM RESPONDEN DARI DB (dinamis) ─────────────────────────
// Ambil distinct respondent_type dari standard_respondent_mapping
// sesuai eval_type yang aktif, lalu label dari fungsi respondentLabel()
$allRespondentTypes = [];
if ($currentEt) {
    $rtRows = Database::fetchAll("
        SELECT DISTINCT srm.respondent_type
        FROM standard_respondent_mapping srm
        JOIN standards s ON s.id = srm.standard_id
        JOIN domains d ON d.id = s.domain_id
        WHERE d.eval_type_id = ? AND srm.period_id IS NULL
        ORDER BY srm.respondent_type
    ", [$currentEt['id']]);
    foreach ($rtRows as $r) {
        $allRespondentTypes[$r['respondent_type']] = respondentLabel($r['respondent_type']);
    }
}

// ── HANDLE SAVE ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_matrix'])) {
    $etId    = (int)$_POST['eval_type_id'];
    $etCode  = $_POST['eval_type_code'] ?? '';
    $checked = $_POST['mapping'] ?? [];

    // Ambil semua standard_id untuk eval_type ini
    $standards = Database::fetchAll("
        SELECT s.id FROM standards s
        JOIN domains d ON s.domain_id = d.id
        WHERE d.eval_type_id = ?
    ", [$etId]);
    $standardIds = array_column($standards, 'id');

    // Hapus mapping lama (period_id NULL = template global)
    if (!empty($standardIds)) {
        $ph = implode(',', array_fill(0, count($standardIds), '?'));
        Database::query(
            "DELETE FROM standard_respondent_mapping
             WHERE standard_id IN ($ph) AND period_id IS NULL",
            $standardIds
        );
    }

    // Insert mapping baru
    $inserted = 0;
    foreach ($checked as $stdId => $respArr) {
        foreach ($respArr as $respType => $val) {
            if (!$val || !isset($allRespondentTypes[$respType])) continue;
            // Cek apakah ini peer
            $actualRespType = $respType;
            if (in_array($respType, $peerMap[$etCode] ?? [])) {
                $actualRespType = 'peer';
            }
            Database::insert('standard_respondent_mapping', [
                'standard_id'     => (int)$stdId,
                'respondent_type' => $actualRespType,
                'period_id'       => null,
                'is_active'       => 1,
            ]);
            $inserted++;
        }
    }

    // Auto-generate packages
    _generatePackages($etId, $etCode, $allRespondentTypes, $peerMap[$etCode] ?? [], $checked, $standardIds);

    flash("Matriks disimpan — $inserted mapping aktif. Paket otomatis diperbarui.", 'success');
    header('Location: ' . APP_URL . '/admin/matrix.php?tab=' . urlencode($tab));
    exit;
}

// ── AUTO-GENERATE PACKAGES ────────────────────────────────────
function _generatePackages(int $etId, string $etCode, array $respTypes, array $peerTypes, array $checked, array $standardIds): void {
    foreach ($respTypes as $respType => $respLabel) {
        // Tentukan actual respondent_type (peer atau tidak)
        $actualType = in_array($respType, $peerTypes) ? 'peer' : $respType;

        // Cek apakah ada standard yang dicentang untuk responden ini
        $hasChecked = false;
        foreach ($checked as $stdId => $respArr) {
            if (!empty($respArr[$respType])) { $hasChecked = true; break; }
        }
        if (!$hasChecked) continue;

        // Cari atau buat paket
        $pkg = Database::fetchOne(
            "SELECT * FROM packages
             WHERE eval_type_id=? AND respondent_type=? AND is_self_reflection=0 AND period_id IS NULL",
            [$etId, $actualType]
        );

        if (!$pkg) {
            $etLabel  = $etCode === 'leader' ? 'Pimpinan' : 'Guru';
            $pkgCode  = strtoupper(substr($etCode,0,1)) . '-' . strtoupper(substr($actualType,0,3));
            $pkgName  = "Evaluasi $etLabel — Oleh $respLabel";
            $pkgId = Database::insert('packages', [
                'code'              => $pkgCode . '_' . uniqid(),
                'name'              => $pkgName,
                'eval_type_id'      => $etId,
                'respondent_type'   => $actualType,
                'is_self_reflection'=> 0,
                'period_id'         => null,
            ]);
            Database::insert('package_weights', [
                'package_id' => $pkgId,
                'weight'     => 1.0,
                'notes'      => 'Auto-generated',
            ]);
        } else {
            $pkgId = $pkg['id'];
        }

        // Hapus package_questions lama untuk paket ini
        if (!empty($standardIds)) {
            $ph = implode(',', array_fill(0, count($standardIds), '?'));
            Database::query(
                "DELETE pq FROM package_questions pq
                 JOIN questions q ON pq.question_id = q.id
                 WHERE pq.package_id = ? AND q.standard_id IN ($ph)",
                array_merge([$pkgId], $standardIds)
            );
        }

        // Insert package_questions
        $orderNum = 1;
        foreach ($checked as $stdId => $respArr) {
            if (empty($respArr[$respType])) continue;

            // Pastikan question ada
            $q = Database::fetchOne("SELECT id FROM questions WHERE standard_id=?", [(int)$stdId]);
            if (!$q) {
                $qId = Database::insert('questions', [
                    'standard_id'      => (int)$stdId,
                    'question_id_text' => '',
                    'question_en_text' => '',
                ]);
                foreach ([1=>['Tidak Terlihat','Not Evident'],2=>['Berkembang','Emerging'],
                           3=>['Cakap','Proficient'],4=>['Teladan','Exemplary']] as $g=>[$lid,$len]) {
                    Database::insert('grade_descriptors', [
                        'question_id'=>$qId,'grade'=>$g,
                        'label_id'=>$lid,'label_en'=>$len,
                        'description_id'=>'','description_en'=>'',
                    ]);
                }
            } else {
                $qId = $q['id'];
            }

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
$existingMap = [];
$domains     = [];

if ($currentEt) {
    // Mapping yang ada (period_id NULL = template global)
    $mappings = Database::fetchAll("
        SELECT srm.standard_id, srm.respondent_type
        FROM standard_respondent_mapping srm
        JOIN standards s ON srm.standard_id = s.id
        JOIN domains d ON s.domain_id = d.id
        WHERE d.eval_type_id = ? AND srm.is_active = 1 AND srm.period_id IS NULL
    ", [$currentEt['id']]);

    // Konversi peer → kolom yang sesuai
    $peerCols = $peerMap[$tab] ?? [];
    foreach ($mappings as $m) {
        $displayType = $m['respondent_type'];
        if ($m['respondent_type'] === 'peer' && !empty($peerCols)) {
            $displayType = $peerCols[0]; // pakai kolom pertama dari peerTypes
        }
        $existingMap[$m['standard_id']][$displayType] = true;
    }

    // Standards grouped by domain
    $rows = Database::fetchAll("
        SELECT s.id as s_id, s.name as s_name,
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

$currentPeerTypes = $peerMap[$tab] ?? [];

ob_start(); ?>

<?= showFlash() ?>

<!-- Tabs per eval type -->
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
  <strong>Cara pakai:</strong> Centang kotak di baris standar dan kolom responden.
  Kolom yang sama dengan tipe evaluasi otomatis menjadi <strong>Peer-review</strong>.
  Klik <strong>Simpan & Generate Paket</strong> untuk memperbarui paket.
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
        <table class="table table-bordered mb-0" id="matrixTable">
          <thead>
            <tr style="background:var(--ktb-navy);color:white">
              <th style="min-width:260px;vertical-align:middle">Standard</th>
              <?php foreach ($allRespondentTypes as $rType => $rLabel):
                $isPeer = in_array($rType, $currentPeerTypes);
              ?>
              <th class="text-center" style="min-width:100px;vertical-align:middle;font-size:.78rem">
                <?= h($rLabel) ?>
                <?php if ($isPeer): ?>
                <br><span class="badge bg-warning text-dark" style="font-size:.6rem">Peer-review</span>
                <?php endif; ?>
              </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($domains as $did => $domain): ?>

            <!-- Domain header row -->
            <tr style="background:#eef1fa">
              <td colspan="<?= count($allRespondentTypes)+1 ?>" class="py-2 px-3">
                <span class="badge me-2" style="background:var(--ktb-navy)">
                  <?= h($domain['code']??'—') ?>
                </span>
                <strong class="text-navy"><?= h($domain['name']) ?></strong>
              </td>
            </tr>

            <?php foreach ($domain['standards'] as $sid => $sName): ?>
            <tr class="standard-row">
              <td class="ps-4 small" style="vertical-align:middle"><?= h($sName) ?></td>
              <?php foreach ($allRespondentTypes as $rType => $rLabel): ?>
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

<!-- Preview paket yang sudah ada -->
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
  <div class="card-header">
    <i class="bi bi-boxes me-2"></i>Paket yang Terbentuk
    <span class="badge bg-secondary ms-1"><?= count($existingPkgs) ?> paket</span>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0 small">
      <thead><tr>
        <th>Kode</th>
        <th>Nama Paket</th>
        <th>Responden</th>
        <th class="text-center">Soal</th>
        <th>Aksi</th>
      </tr></thead>
      <tbody>
        <?php foreach ($existingPkgs as $pkg): ?>
        <tr>
          <td><span class="badge badge-navy"><?= h($pkg['code']) ?></span></td>
          <td><?= h($pkg['name']) ?></td>
          <td class="text-muted"><?= h(respondentLabel($pkg['respondent_type'])) ?></td>
          <td class="text-center"><?= $pkg['q_count'] ?></td>
          <td>
            <a href="<?= APP_URL ?>/admin/questions_packages.php?pkg=<?= $pkg['id'] ?>&et=<?= urlencode($tab) ?>"
               class="btn btn-sm btn-outline-primary py-0 px-2">
              <i class="bi bi-folder me-1"></i>Lihat Paket
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
    if (countEl) countEl.textContent = document.querySelectorAll('.matrix-cb:checked').length;
  }
  cbs.forEach(cb => cb.addEventListener('change', updateCount));
  updateCount();

  document.getElementById('btnCheckAll')?.addEventListener('click', () => {
    cbs.forEach(cb => cb.checked = true); updateCount();
  });
  document.getElementById('btnUncheckAll')?.addEventListener('click', () => {
    cbs.forEach(cb => cb.checked = false); updateCount();
  });

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