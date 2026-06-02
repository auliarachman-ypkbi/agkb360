<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['admin','foundation']);

$tab    = $_GET['tab'] ?? 'evaltypes';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ══════════════════════════════════════════════════════════════
// HANDLE POST ACTIONS
// ══════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── EVAL TYPES ────────────────────────────────────────────
    if ($action === 'create_evaltype') {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        if (!$name || !$code) {
            flash('Nama dan kode wajib diisi.', 'danger');
        } elseif (Database::fetchOne("SELECT id FROM eval_types WHERE code=?", [$code])) {
            flash("Kode '$code' sudah dipakai.", 'danger');
        } else {
            Database::insert('eval_types', ['code'=>$code,'name'=>$name]);
            flash("Tipe evaluasi \"$name\" berhasil ditambahkan.", 'success');
        }
        header('Location: ' . APP_URL . '/admin/foundation.php?tab=evaltypes'); exit;
    }

    if ($action === 'edit_evaltype') {
        $id   = (int)$_POST['evaltype_id'];
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $dup  = Database::fetchOne("SELECT id FROM eval_types WHERE code=? AND id!=?", [$code, $id]);
        if ($dup) {
            flash("Kode '$code' sudah dipakai tipe lain.", 'danger');
        } else {
            Database::update('eval_types', ['code'=>$code,'name'=>$name], 'id=?', [$id]);
            flash('Tipe evaluasi berhasil diperbarui.', 'success');
        }
        header('Location: ' . APP_URL . '/admin/foundation.php?tab=evaltypes'); exit;
    }

    if ($action === 'delete_evaltype') {
        $id = (int)$_POST['evaltype_id'];
        $hasDomains = Database::fetchOne("SELECT COUNT(*) c FROM domains WHERE eval_type_id=?", [$id])['c'];
        if ($hasDomains > 0) {
            flash("Tidak bisa menghapus — masih ada $hasDomains domain yang menggunakan tipe ini.", 'danger');
        } else {
            Database::query("DELETE FROM eval_types WHERE id=?", [$id]);
            flash('Tipe evaluasi berhasil dihapus.', 'warning');
        }
        header('Location: ' . APP_URL . '/admin/foundation.php?tab=evaltypes'); exit;
    }


    if ($action === 'create_domain') {
        $name     = trim($_POST['name'] ?? '');
        $code     = trim($_POST['code'] ?? '');
        $et_id    = (int)$_POST['eval_type_id'];
        $desc     = trim($_POST['description'] ?? '');
        $order    = (int)($_POST['order_num'] ?? 0);
        if ($name && $et_id) {
            Database::insert('domains', ['eval_type_id'=>$et_id,'code'=>$code,'name'=>$name,'description'=>$desc,'order_num'=>$order]);
            flash("Domain \"$name\" berhasil ditambahkan.", 'success');
        } else {
            flash('Nama dan tipe evaluasi wajib diisi.', 'danger');
        }
        header('Location: ' . APP_URL . '/admin/foundation.php?tab=domains'); exit;
    }

    if ($action === 'edit_domain') {
        $id   = (int)$_POST['domain_id'];
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $order= (int)($_POST['order_num'] ?? 0);
        Database::update('domains', ['name'=>$name,'code'=>$code,'description'=>$desc,'order_num'=>$order], 'id=?', [$id]);
        flash('Domain berhasil diperbarui.', 'success');
        header('Location: ' . APP_URL . '/admin/foundation.php?tab=domains'); exit;
    }

    if ($action === 'delete_domain') {
        $id = (int)$_POST['domain_id'];
        $hasStandards = Database::fetchOne("SELECT COUNT(*) c FROM standards WHERE domain_id=?", [$id])['c'];
        if ($hasStandards > 0) {
            flash('Tidak bisa menghapus domain yang masih memiliki standard.', 'danger');
        } else {
            Database::query("DELETE FROM domains WHERE id=?", [$id]);
            flash('Domain berhasil dihapus.', 'warning');
        }
        header('Location: ' . APP_URL . '/admin/foundation.php?tab=domains'); exit;
    }

    // ── STANDARDS ─────────────────────────────────────────────
    if ($action === 'create_standard') {
        $name     = trim($_POST['name'] ?? '');
        $domain_id= (int)$_POST['domain_id'];
        $ext_desc = trim($_POST['extended_description'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $order    = (int)($_POST['order_num'] ?? 0);
        if ($name && $domain_id) {
            $stdId = Database::insert('standards', ['domain_id'=>$domain_id,'name'=>$name,'description'=>$desc,'extended_description'=>$ext_desc,'order_num'=>$order]);

            // Auto-create pertanyaan master kosong
            $qId = Database::insert('questions', [
                'standard_id'      => $stdId,
                'question_id_text' => '',
                'question_en_text' => '',
            ]);
            // Auto-create grade descriptors default
            foreach ([1=>['Tidak Terlihat','Not Evident'],2=>['Berkembang','Emerging'],3=>['Cakap','Proficient'],4=>['Teladan','Exemplary']] as $g=>[$lid,$len]) {
                Database::insert('grade_descriptors', [
                    'question_id'    => $qId,
                    'grade'          => $g,
                    'label_id'       => $lid,
                    'label_en'       => $len,
                    'description_id' => '',
                    'description_en' => '',
                ]);
            }
            flash("Standard \"$name\" berhasil ditambahkan. Pertanyaan master kosong otomatis terbuat.", 'success');
        } else {
            flash('Nama dan domain wajib diisi.', 'danger');
        }
        header('Location: ' . APP_URL . '/admin/foundation.php?tab=standards'); exit;
    }

    if ($action === 'edit_standard') {
        $id       = (int)$_POST['standard_id'];
        $name     = trim($_POST['name'] ?? '');
        $domain_id= (int)$_POST['domain_id'];
        $ext_desc = trim($_POST['extended_description'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $order    = (int)($_POST['order_num'] ?? 0);
        Database::update('standards', ['domain_id'=>$domain_id,'name'=>$name,'description'=>$desc,'extended_description'=>$ext_desc,'order_num'=>$order], 'id=?', [$id]);
        flash('Standard berhasil diperbarui.', 'success');
        header('Location: ' . APP_URL . '/admin/foundation.php?tab=standards'); exit;
    }

    if ($action === 'delete_standard') {
        $id = (int)$_POST['standard_id'];
        $hasQ = Database::fetchOne("SELECT COUNT(*) c FROM questions WHERE standard_id=?", [$id])['c'];
        if ($hasQ > 0) {
            flash('Tidak bisa menghapus standard yang masih memiliki pertanyaan.', 'danger');
        } else {
            Database::query("DELETE FROM standard_traits WHERE standard_id=?", [$id]);
            Database::query("DELETE FROM standards WHERE id=?", [$id]);
            flash('Standard berhasil dihapus.', 'warning');
        }
        header('Location: ' . APP_URL . '/admin/foundation.php?tab=standards'); exit;
    }

    // ── TRAITS ────────────────────────────────────────────────
    if ($action === 'create_trait') {
        $name = trim($_POST['name'] ?? '');
        $code = (int)$_POST['code'];
        $desc = trim($_POST['description'] ?? '');
        if ($name && $code) {
            Database::insert('traits', ['code'=>$code,'name'=>$name,'description'=>$desc]);
            flash("Trait \"$name\" berhasil ditambahkan.", 'success');
        } else {
            flash('Nama dan kode wajib diisi.', 'danger');
        }
        header('Location: ' . APP_URL . '/admin/foundation.php?tab=traits'); exit;
    }

    if ($action === 'edit_trait') {
        $id   = (int)$_POST['trait_id'];
        $name = trim($_POST['name'] ?? '');
        $code = (int)$_POST['code'];
        $desc = trim($_POST['description'] ?? '');
        Database::update('traits', ['code'=>$code,'name'=>$name,'description'=>$desc], 'id=?', [$id]);
        flash('Trait berhasil diperbarui.', 'success');
        header('Location: ' . APP_URL . '/admin/foundation.php?tab=traits'); exit;
    }

    if ($action === 'delete_trait') {
        $id = (int)$_POST['trait_id'];
        Database::query("DELETE FROM standard_traits WHERE trait_id=?", [$id]);
        Database::query("DELETE FROM traits WHERE id=?", [$id]);
        flash('Trait berhasil dihapus.', 'warning');
        header('Location: ' . APP_URL . '/admin/foundation.php?tab=traits'); exit;
    }

    // ── MAPPING ───────────────────────────────────────────────
    if ($action === 'save_mapping') {
        $standard_id = (int)$_POST['standard_id'];
        $trait_ids   = $_POST['trait_ids'] ?? [];

        // Delete existing mappings for this standard
        Database::query("DELETE FROM standard_traits WHERE standard_id=?", [$standard_id]);

        // Insert new mappings
        if (!empty($trait_ids)) {
            $ins = Database::getInstance()->prepare(
                "INSERT IGNORE INTO standard_traits (standard_id, trait_id) VALUES (?,?)"
            );
            foreach ($trait_ids as $tid) {
                $ins->execute([$standard_id, (int)$tid]);
            }
        }
        flash('Mapping trait berhasil disimpan.', 'success');
        header('Location: ' . APP_URL . '/admin/foundation.php?tab=mapping&standard_id=' . $standard_id); exit;
    }
}

// ══════════════════════════════════════════════════════════════
// FETCH DATA
// ══════════════════════════════════════════════════════════════
$evalTypes = Database::fetchAll("SELECT et.*, COUNT(DISTINCT d.id) as domain_count, COUNT(DISTINCT s.id) as standard_count FROM eval_types et LEFT JOIN domains d ON d.eval_type_id = et.id LEFT JOIN standards s ON s.domain_id = d.id GROUP BY et.id, et.code, et.name ORDER BY et.id");
$domains   = Database::fetchAll("
    SELECT d.*, et.name as eval_type_name,
           COUNT(s.id) as standard_count
    FROM domains d
    JOIN eval_types et ON d.eval_type_id = et.id
    LEFT JOIN standards s ON s.domain_id = d.id
    GROUP BY d.id, d.eval_type_id, d.code, d.name, d.description, d.order_num, et.name
    ORDER BY d.eval_type_id, d.order_num, d.id
");
$standards = Database::fetchAll("
    SELECT s.*, d.name as domain_name, d.code as domain_code,
           et.name as eval_type_name, et.id as eval_type_id,
           COUNT(q.id) as question_count
    FROM standards s
    JOIN domains d ON s.domain_id = d.id
    JOIN eval_types et ON d.eval_type_id = et.id
    LEFT JOIN questions q ON q.standard_id = s.id
    GROUP BY s.id, s.domain_id, s.name, s.description, s.extended_description,
             s.order_num, d.name, d.code, et.name, et.id
    ORDER BY et.id, d.order_num, s.order_num
");
$traits = Database::fetchAll("SELECT * FROM traits ORDER BY code");

// Mapping data
$selectedStandardId = (int)($_GET['standard_id'] ?? 0);
$selectedStandard   = $selectedStandardId
    ? Database::fetchOne("SELECT s.*, d.name as domain_name FROM standards s JOIN domains d ON s.domain_id=d.id WHERE s.id=?", [$selectedStandardId])
    : null;
$mappedTraitIds = $selectedStandardId
    ? array_column(Database::fetchAll("SELECT trait_id FROM standard_traits WHERE standard_id=?", [$selectedStandardId]), 'trait_id')
    : [];

// Summary counts per standard (for mapping tab)
$mappingSummary = Database::fetchAll("
    SELECT s.id, s.name, s.order_num,
           d.name as domain_name, d.order_num as domain_order,
           et.name as eval_type_name, et.id as eval_type_id,
           COUNT(st.trait_id) as trait_count
    FROM standards s
    JOIN domains d ON s.domain_id = d.id
    JOIN eval_types et ON d.eval_type_id = et.id
    LEFT JOIN standard_traits st ON st.standard_id = s.id
    GROUP BY s.id, s.name, s.order_num, d.name, d.order_num, et.name, et.id
    ORDER BY et.id, d.order_num, s.order_num
");

// Edit targets
$editDomain   = isset($_GET['edit_domain'])   ? Database::fetchOne("SELECT * FROM domains WHERE id=?",   [(int)$_GET['edit_domain']])   : null;
$editStandard = isset($_GET['edit_standard']) ? Database::fetchOne("SELECT * FROM standards WHERE id=?", [(int)$_GET['edit_standard']]) : null;
$editTrait    = isset($_GET['edit_trait'])    ? Database::fetchOne("SELECT * FROM traits WHERE id=?",    [(int)$_GET['edit_trait']])    : null;

// Nav tabs
// Edit target eval type
$editEvalType = isset($_GET['edit_evaltype'])
    ? Database::fetchOne("SELECT * FROM eval_types WHERE id=?", [(int)$_GET['edit_evaltype']])
    : null;

$tabs = [
    'evaltypes' => ['bi-grid-3x2',   'Tipe Evaluasi'],
    'domains'   => ['bi-diagram-3',  'Domains'],
    'standards' => ['bi-list-check', 'Standards'],
    'traits'    => ['bi-tags',       'Traits'],
    'mapping'   => ['bi-diagram-2',  'Mapping'],
];

ob_start(); ?>

<?= showFlash() ?>

<!-- TAB NAV -->
<ul class="nav nav-tabs mb-4">
  <?php foreach ($tabs as $key => [$icon, $label]): ?>
  <li class="nav-item">
    <a class="nav-link <?= $tab===$key?'active fw-bold':'' ?>" href="?tab=<?= $key ?>">
      <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
      <?php $count = match($key) {
        'evaltypes' => count($evalTypes),
        'domains'   => count($domains),
        'standards' => count($standards),
        'traits'    => count($traits),
        'mapping'   => count($mappingSummary),
        default     => 0,
      }; ?>
      <span class="badge bg-secondary ms-1"><?= $count ?></span>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<?php // ══════════════════════════════════════════════════════
// TAB: EVAL TYPES
// ══════════════════════════════════════════════════════ ?>
<?php if ($tab === 'evaltypes'): ?>

<div class="row g-4">
  <!-- FORM -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header <?= $editEvalType ? 'gold' : '' ?>">
        <i class="bi bi-<?= $editEvalType ? 'pencil' : 'plus-circle' ?> me-2"></i>
        <?= $editEvalType ? 'Edit Tipe Evaluasi' : 'Tambah Tipe Baru' ?>
      </div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="<?= $editEvalType ? 'edit_evaltype' : 'create_evaltype' ?>">
          <?php if ($editEvalType): ?>
          <input type="hidden" name="evaltype_id" value="<?= $editEvalType['id'] ?>">
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label fw-semibold small">
              Kode <span class="text-danger">*</span>
              <span class="text-muted fw-normal">(unik, max 20 karakter)</span>
            </label>
            <input type="text" name="code" class="form-control" required maxlength="20"
              placeholder="cth: tendik"
              value="<?= h($editEvalType['code'] ?? '') ?>">
            <div class="form-text">Dipakai sebagai identifier internal</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Nama Tipe <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required
              placeholder="cth: Tenaga Kependidikan"
              value="<?= h($editEvalType['name'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Deskripsi</label>
            <textarea name="description" class="form-control" rows="3"
              placeholder="Jelaskan tipe evaluasi ini..."><?= h($editEvalType['description'] ?? '') ?></textarea>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-navy">
              <i class="bi bi-save me-1"></i><?= $editEvalType ? 'Simpan' : 'Tambah Tipe' ?>
            </button>
            <?php if ($editEvalType): ?>
            <a href="?tab=evaltypes" class="btn btn-outline-secondary">Batal</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <!-- INFO -->
    <div class="card mt-3" style="border-left:4px solid var(--ktb-gold)">
      <div class="card-body py-3 small text-muted">
        <strong class="text-navy"><i class="bi bi-info-circle me-1"></i>Catatan:</strong>
        <ul class="mt-2 mb-0">
          <li>Tipe <strong>leader</strong> dan <strong>teacher</strong> adalah tipe bawaan sistem</li>
          <li>Tipe baru bisa dipakai untuk kelompok seperti <em>Tenaga Kependidikan, Konselor</em>, dll</li>
          <li>Setelah tambah tipe, buat Domain dan Standard-nya di tab berikutnya</li>
          <li>Tipe hanya bisa dihapus jika belum punya Domain</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- LIST -->
  <div class="col-md-8">
    <div class="row g-3">
      <?php foreach ($evalTypes as $et):
        $isBuiltIn = in_array($et['code'], ['leader','teacher']);
      ?>
      <div class="col-12">
        <div class="card border <?= $isBuiltIn ? 'border-warning' : '' ?>">
          <div class="card-body py-3 px-4">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="badge" style="background:var(--ktb-navy);font-size:.8rem;padding:5px 10px">
                    <?= h($et['code']) ?>
                  </span>
                  <h5 class="mb-0 fw-bold text-navy"><?= h($et['name']) ?></h5>
                  <?php if ($isBuiltIn): ?>
                  <span class="badge bg-warning text-dark small">Bawaan Sistem</span>
                  <?php endif; ?>
                </div>
                <?php if (!empty($et['description'])): ?>
                <p class="text-muted small mb-2"><?= h($et['description']) ?></p>
                <?php endif; ?>
                <div class="d-flex gap-3 small text-muted">
                  <span><i class="bi bi-diagram-3 me-1"></i><?= $et['domain_count'] ?> Domain</span>
                  <span><i class="bi bi-list-check me-1"></i><?= $et['standard_count'] ?> Standard</span>
                  <span>
                    <a href="?tab=domains" class="text-decoration-none text-muted">
                      <i class="bi bi-arrow-right me-1"></i>Kelola Domain →
                    </a>
                  </span>
                </div>
              </div>
              <div class="d-flex gap-2">
                <a href="?tab=evaltypes&edit_evaltype=<?= $et['id'] ?>"
                   class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-pencil"></i>
                </a>
                <?php if (!$isBuiltIn): ?>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="delete_evaltype">
                  <input type="hidden" name="evaltype_id" value="<?= $et['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"
                    data-confirm="Hapus tipe '<?= h($et['name']) ?>'?">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
                <?php else: ?>
                <button class="btn btn-sm btn-outline-secondary" disabled title="Tipe bawaan tidak bisa dihapus">
                  <i class="bi bi-lock"></i>
                </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php // ══════════════════════════════════════════════════════
// TAB: DOMAINS
// ══════════════════════════════════════════════════════ ?>
<?php elseif ($tab === 'domains'): ?>

<div class="row g-4">
  <div class="col-md-5">
    <div class="card">
      <div class="card-header <?= $editDomain ? 'gold' : '' ?>">
        <i class="bi bi-<?= $editDomain ? 'pencil' : 'plus-circle' ?> me-2"></i>
        <?= $editDomain ? 'Edit Domain' : 'Tambah Domain Baru' ?>
      </div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="<?= $editDomain ? 'edit_domain' : 'create_domain' ?>">
          <?php if ($editDomain): ?>
          <input type="hidden" name="domain_id" value="<?= $editDomain['id'] ?>">
          <?php endif; ?>

          <?php if (!$editDomain): ?>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Tipe Evaluasi <span class="text-danger">*</span></label>
            <select name="eval_type_id" class="form-select" required>
              <option value="">— Pilih —</option>
              <?php foreach ($evalTypes as $et): ?>
              <option value="<?= $et['id'] ?>"><?= h($et['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label fw-semibold small">Kode <span class="text-muted">(opsional, cth: A, B, C)</span></label>
            <input type="text" name="code" class="form-control" maxlength="10"
              value="<?= h($editDomain['code'] ?? '') ?>" placeholder="A">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Nama Domain <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required
              value="<?= h($editDomain['name'] ?? '') ?>"
              placeholder="cth: IB Philosophy & Vision">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Deskripsi</label>
            <textarea name="description" class="form-control" rows="2"
              placeholder="Deskripsi singkat domain ini..."><?= h($editDomain['description'] ?? '') ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Urutan Tampil</label>
            <input type="number" name="order_num" class="form-control" min="0"
              value="<?= h($editDomain['order_num'] ?? 0) ?>">
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-navy">
              <i class="bi bi-save me-1"></i><?= $editDomain ? 'Simpan' : 'Tambah Domain' ?>
            </button>
            <?php if ($editDomain): ?>
            <a href="?tab=domains" class="btn btn-outline-secondary">Batal</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-7">
    <?php
    // Group domains by eval_type
    $domainsByType = [];
    foreach ($domains as $d) {
        $domainsByType[$d['eval_type_name']][] = $d;
    }
    $etIdx = 0;
    foreach ($domainsByType as $etName => $etDomains):
      $etIdx++;
      $collapseId = 'domainCollapse' . $etIdx;
      $isOpen = $etIdx === 1; // buka yang pertama by default
    ?>
    <div class="card mb-3 border-0 shadow-soft">
      <!-- Accordion Header -->
      <div class="card-header d-flex justify-content-between align-items-center"
           style="cursor:pointer;user-select:none"
           data-bs-toggle="collapse"
           data-bs-target="#<?= $collapseId ?>"
           aria-expanded="<?= $isOpen ? 'true' : 'false' ?>">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-people-fill"></i>
          <span class="fw-bold"><?= h($etName) ?></span>
          <span class="badge bg-white text-navy ms-1"><?= count($etDomains) ?> domain</span>
        </div>
        <i class="bi bi-chevron-<?= $isOpen ? 'up' : 'down' ?> accordion-chevron"></i>
      </div>

      <!-- Accordion Body -->
      <div class="collapse <?= $isOpen ? 'show' : '' ?>" id="<?= $collapseId ?>">
        <div class="card-body p-2">
          <?php foreach ($etDomains as $d): ?>
          <div class="d-flex justify-content-between align-items-center
                      px-3 py-2 rounded mb-1"
               style="background:#f8f9fa;border-left:3px solid var(--ktb-navy)">
            <div class="d-flex align-items-center gap-2">
              <span class="badge badge-navy"><?= h($d['code'] ?: '—') ?></span>
              <strong class="small"><?= h($d['name']) ?></strong>
              <span class="badge bg-light text-dark border"><?= $d['standard_count'] ?> std</span>
            </div>
            <div class="d-flex gap-1">
              <a href="?tab=domains&edit_domain=<?= $d['id'] ?>"
                 class="btn btn-sm btn-outline-primary py-0 px-2">
                <i class="bi bi-pencil"></i>
              </a>
              <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="delete_domain">
                <input type="hidden" name="domain_id" value="<?= $d['id'] ?>">
                <button class="btn btn-sm btn-outline-danger py-0 px-2"
                  data-confirm="Hapus domain <?= h($d['name']) ?>?">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </div>
          </div>
          <?php if ($d['description']): ?>
          <div class="px-3 pb-1 small text-muted"><?= h($d['description']) ?></div>
          <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php // ══════════════════════════════════════════════════════
// TAB: STANDARDS
// ══════════════════════════════════════════════════════ ?>
<?php elseif ($tab === 'standards'): ?>

<div class="row g-4">
  <div class="col-md-5">
    <div class="card">
      <div class="card-header <?= $editStandard ? 'gold' : '' ?>">
        <i class="bi bi-<?= $editStandard ? 'pencil' : 'plus-circle' ?> me-2"></i>
        <?= $editStandard ? 'Edit Standard' : 'Tambah Standard Baru' ?>
      </div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="<?= $editStandard ? 'edit_standard' : 'create_standard' ?>">
          <?php if ($editStandard): ?>
          <input type="hidden" name="standard_id" value="<?= $editStandard['id'] ?>">
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label fw-semibold small">Domain <span class="text-danger">*</span></label>
            <select name="domain_id" class="form-select" required>
              <option value="">— Pilih Domain —</option>
              <?php
              $etName = '';
              foreach ($domains as $d):
                if ($d['eval_type_name'] !== $etName) {
                  $etName = $d['eval_type_name'];
                  echo "<optgroup label='── {$etName} ──'>";
                }
                $sel = ($editStandard && $editStandard['domain_id'] == $d['id']) ? 'selected' : '';
              ?>
              <option value="<?= $d['id'] ?>" <?= $sel ?>>
                <?= h(($d['code'] ? $d['code'].'. ' : '') . $d['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Nama Standard <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required
              value="<?= h($editStandard['name'] ?? '') ?>"
              placeholder="cth: Alignment with IB Mission">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Deskripsi Extended
              <span class="text-muted small">(dipakai sebagai teks pertanyaan)</span>
            </label>
            <textarea name="extended_description" class="form-control" rows="4"
              placeholder="Teks rubrik lengkap yang menjadi dasar pertanyaan..."><?= h($editStandard['extended_description'] ?? '') ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Urutan Tampil</label>
            <input type="number" name="order_num" class="form-control" min="0"
              value="<?= h($editStandard['order_num'] ?? 0) ?>">
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-navy">
              <i class="bi bi-save me-1"></i><?= $editStandard ? 'Simpan' : 'Tambah Standard' ?>
            </button>
            <?php if ($editStandard): ?>
            <a href="?tab=standards" class="btn btn-outline-secondary">Batal</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-7">
    <?php
    // Group standards by eval_type → domain
    $stdByType = [];
    foreach ($standards as $s) {
        $stdByType[$s['eval_type_name']][$s['domain_name']][] = $s;
    }
    $etIdx = 0;
    foreach ($stdByType as $etName => $domainGroup):
      $etIdx++;
      $etCollapseId = 'stdEt' . $etIdx;
      $isEtOpen = $etIdx === 1;
    ?>
    <!-- Eval Type Accordion -->
    <div class="card mb-3 border-0 shadow-soft">
      <div class="card-header d-flex justify-content-between align-items-center"
           style="cursor:pointer;user-select:none"
           data-bs-toggle="collapse"
           data-bs-target="#<?= $etCollapseId ?>"
           aria-expanded="<?= $isEtOpen ? 'true' : 'false' ?>">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-people-fill"></i>
          <span class="fw-bold"><?= h($etName) ?></span>
          <?php
          $totalStd = array_sum(array_map('count', $domainGroup));
          ?>
          <span class="badge bg-white text-navy"><?= $totalStd ?> standard</span>
        </div>
        <i class="bi bi-chevron-<?= $isEtOpen ? 'up' : 'down' ?> accordion-chevron"></i>
      </div>

      <div class="collapse <?= $isEtOpen ? 'show' : '' ?>" id="<?= $etCollapseId ?>">
        <div class="card-body p-2">
          <?php
          $dIdx = 0;
          foreach ($domainGroup as $domainName => $stdList):
            $dIdx++;
            $dCollapseId = 'stdD' . $etIdx . '_' . $dIdx;
            $isDOpen = true; // semua domain terbuka by default
            // Get domain code from first standard
            $domainCode = $stdList[0]['domain_code'] ?? '';
          ?>
          <!-- Domain Sub-accordion -->
          <div class="mb-2 border rounded overflow-hidden">
            <div class="d-flex justify-content-between align-items-center px-3 py-2"
                 style="background:#eef1f7;cursor:pointer"
                 data-bs-toggle="collapse"
                 data-bs-target="#<?= $dCollapseId ?>">
              <div class="d-flex align-items-center gap-2">
                <span class="badge badge-navy"><?= h($domainCode ?: '—') ?></span>
                <span class="fw-semibold small text-navy"><?= h($domainName) ?></span>
                <span class="badge bg-secondary"><?= count($stdList) ?> std</span>
              </div>
              <i class="bi bi-chevron-down small text-muted"></i>
            </div>

            <div class="collapse show" id="<?= $dCollapseId ?>">
              <?php foreach ($stdList as $s): ?>
              <div class="d-flex justify-content-between align-items-start
                          px-3 py-2 border-top" style="background:white">
                <div style="max-width:70%">
                  <div class="small fw-semibold"><?= h($s['name']) ?></div>
                  <?php if ($s['extended_description']): ?>
                  <div class="text-muted" style="font-size:.7rem;line-height:1.4;margin-top:2px">
                    <?= h(substr($s['extended_description'], 0, 90)) ?>...
                  </div>
                  <?php endif; ?>
                </div>
                <div class="d-flex gap-1 align-items-center">
                  <span class="badge bg-light text-dark border me-1"><?= $s['question_count'] ?> soal</span>
                  <a href="?tab=standards&edit_standard=<?= $s['id'] ?>"
                     class="btn btn-sm btn-outline-primary py-0 px-2">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <a href="?tab=mapping&standard_id=<?= $s['id'] ?>"
                     class="btn btn-sm btn-outline-info py-0 px-2" title="Mapping trait">
                    <i class="bi bi-tags"></i>
                  </a>
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="delete_standard">
                    <input type="hidden" name="standard_id" value="<?= $s['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger py-0 px-2"
                      data-confirm="Hapus standard <?= h($s['name']) ?>?">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; // domainGroup ?>
        </div>
      </div>
    </div>
    <?php endforeach; // stdByType ?>
  </div>
</div>

<?php // ══════════════════════════════════════════════════════
// TAB: TRAITS
// ══════════════════════════════════════════════════════ ?>
<?php elseif ($tab === 'traits'): ?>

<div class="row g-4">
  <div class="col-md-4">
    <div class="card">
      <div class="card-header <?= $editTrait ? 'gold' : '' ?>">
        <i class="bi bi-<?= $editTrait ? 'pencil' : 'plus-circle' ?> me-2"></i>
        <?= $editTrait ? 'Edit Trait' : 'Tambah Trait Baru' ?>
      </div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="<?= $editTrait ? 'edit_trait' : 'create_trait' ?>">
          <?php if ($editTrait): ?>
          <input type="hidden" name="trait_id" value="<?= $editTrait['id'] ?>">
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label fw-semibold small">Nomor / Kode <span class="text-danger">*</span></label>
            <input type="number" name="code" class="form-control" min="1" max="99" required
              value="<?= h($editTrait['code'] ?? '') ?>" placeholder="cth: 11">
            <div class="form-text">Nomor urut trait (1-10 sudah ada)</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Nama Trait <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required
              value="<?= h($editTrait['name'] ?? '') ?>"
              placeholder="cth: Reflective">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Deskripsi</label>
            <textarea name="description" class="form-control" rows="3"
              placeholder="Jelaskan makna trait ini dalam konteks IB..."><?= h($editTrait['description'] ?? '') ?></textarea>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-navy">
              <i class="bi bi-save me-1"></i><?= $editTrait ? 'Simpan' : 'Tambah Trait' ?>
            </button>
            <?php if ($editTrait): ?>
            <a href="?tab=traits" class="btn btn-outline-secondary">Batal</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-tags me-2"></i>10 IB Learner Profile Traits</div>
      <div class="card-body">
        <div class="row g-3">
          <?php foreach ($traits as $t):
            $mappedCount = Database::fetchOne("SELECT COUNT(*) c FROM standard_traits WHERE trait_id=?", [$t['id']])['c'];
          ?>
          <div class="col-md-6">
            <div class="card border h-100">
              <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-start">
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark fw-bold"><?= $t['code'] ?></span>
                    <strong class="text-navy"><?= h($t['name']) ?></strong>
                  </div>
                  <div class="d-flex gap-1">
                    <a href="?tab=traits&edit_trait=<?= $t['id'] ?>"
                       class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="action" value="delete_trait">
                      <input type="hidden" name="trait_id" value="<?= $t['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger"
                        data-confirm="Hapus trait <?= h($t['name']) ?>?">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </div>
                </div>
                <?php if ($t['description']): ?>
                <p class="small text-muted mt-1 mb-1"><?= h(substr($t['description'], 0, 100)) ?><?= strlen($t['description'])>100?'...':'' ?></p>
                <?php endif; ?>
                <div class="small text-muted">
                  <i class="bi bi-diagram-2 me-1"></i>Terhubung ke <strong><?= $mappedCount ?></strong> standard
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php // ══════════════════════════════════════════════════════
// TAB: MAPPING
// ══════════════════════════════════════════════════════ ?>
<?php elseif ($tab === 'mapping'): ?>

<div class="row g-4">
  <!-- Standard selector -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-list-ul me-2"></i>Pilih Standard</div>
      <div class="card-body p-0" style="max-height:600px;overflow-y:auto">
        <?php
        // Group by eval_type first, then domain
        $grouped = [];
        foreach ($mappingSummary as $ms) {
            $grouped[$ms['eval_type_name']][$ms['domain_name']][] = $ms;
        }
        foreach ($grouped as $etName => $domainGroup):
        ?>
        <!-- Eval Type Header -->
        <div class="px-3 py-2 text-white fw-bold small d-flex align-items-center gap-2"
             style="background:var(--ktb-navy);position:sticky;top:0;z-index:1">
          <i class="bi bi-<?= str_contains($etName,'Pimpinan') ? 'person-badge' : 'mortarboard' ?>"></i>
          <?= h($etName) ?>
        </div>
        <?php foreach ($domainGroup as $domainName => $standards):
          // Domain subheader
        ?>
        <div class="px-3 py-1 small fw-bold text-muted border-bottom"
             style="background:#f8f9fa;font-size:.7rem;letter-spacing:.05em;text-transform:uppercase">
          <?= h($domainName) ?>
        </div>
        <?php foreach ($standards as $ms):
          $active = $selectedStandardId == $ms['id'] ? 'bg-light border-start border-3 border-warning' : '';
        ?>
        <a href="?tab=mapping&standard_id=<?= $ms['id'] ?>"
           class="d-flex justify-content-between align-items-center px-3 py-2 text-decoration-none text-dark border-bottom <?= $active ?>">
          <span class="small"><?= h($ms['name']) ?></span>
          <span class="badge <?= $ms['trait_count'] > 0 ? 'bg-success' : 'bg-secondary' ?> ms-2 flex-shrink-0">
            <?= $ms['trait_count'] ?>
          </span>
        </a>
        <?php endforeach; // standards ?>
        <?php endforeach; // domainGroup ?>
        <?php endforeach; // grouped ?>
      </div>
    </div>
  </div>

  <!-- Mapping editor -->
  <div class="col-md-8">
    <?php if (!$selectedStandard): ?>
    <div class="card">
      <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-diagram-2 display-4 mb-3"></i>
        <p>Pilih standard dari daftar kiri untuk mengatur mapping trait-nya.</p>
        <p class="small">Setiap standard bisa terhubung ke satu atau lebih trait IB Learner Profile.</p>
      </div>
    </div>
    <?php else: ?>
    <div class="card">
      <div class="card-header">
        <i class="bi bi-tags me-2"></i>
        Mapping Trait untuk: <strong><?= h($selectedStandard['name']) ?></strong>
        <div class="small opacity-75 mt-1"><?= h($selectedStandard['domain_name']) ?></div>
      </div>
      <div class="card-body">
        <?php if ($selectedStandard['extended_description']): ?>
        <div class="alert alert-light border small mb-4" style="line-height:1.7">
          <strong>Konteks Standard:</strong><br>
          <?= h($selectedStandard['extended_description']) ?>
        </div>
        <?php endif; ?>

        <form method="POST">
          <input type="hidden" name="action" value="save_mapping">
          <input type="hidden" name="standard_id" value="<?= $selectedStandard['id'] ?>">

          <p class="fw-semibold mb-3">Centang trait yang relevan dengan standard ini:</p>
          <div class="row g-3">
            <?php foreach ($traits as $t):
              $checked = in_array($t['id'], $mappedTraitIds) ? 'checked' : '';
            ?>
            <div class="col-md-6">
              <div class="form-check p-0">
                <label class="d-flex align-items-start gap-2 p-2 rounded border cursor-pointer
                  <?= $checked ? 'border-warning bg-warning bg-opacity-10' : '' ?>"
                  style="cursor:pointer">
                  <input type="checkbox" name="trait_ids[]" value="<?= $t['id'] ?>"
                    class="form-check-input mt-1" <?= $checked ?>>
                  <div>
                    <div class="d-flex align-items-center gap-2">
                      <span class="badge bg-warning text-dark"><?= $t['code'] ?></span>
                      <strong class="small"><?= h($t['name']) ?></strong>
                    </div>
                    <?php if ($t['description']): ?>
                    <div class="text-muted mt-1" style="font-size:.72rem">
                      <?= h(substr($t['description'], 0, 80)) ?>...
                    </div>
                    <?php endif; ?>
                  </div>
                </label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-navy">
              <i class="bi bi-save me-1"></i>Simpan Mapping
            </button>
            <a href="?tab=mapping" class="btn btn-outline-secondary">Batal</a>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php endif; // end tabs ?>

<?php
$content = ob_get_clean();
pageWrapper('Manajemen Fondasi (Domain → Standard → Trait)', $content);
?>