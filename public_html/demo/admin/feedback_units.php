<?php
// AGKB 360° — Unit Penanganan Feedback
// Struktur ini TERPISAH dari matriks evaluasi 360.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin','admin','foundation']);
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = $_POST['action'] ?? '';

    if ($act === 'save_unit') {
        $gid  = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (mb_strlen($name) < 3) flash('Nama unit terlalu pendek.', 'danger');
        else {
            $data = [
                'name'        => $name,
                'description' => trim($_POST['description'] ?? '') ?: null,
                'order_num'   => (int)($_POST['order_num'] ?? 900),
                'type'        => 'penanganan',
                // Sengaja NULL — unit penanganan bukan kelompok penilai,
                // jadi tidak boleh muncul di matriks evaluasi 360.
                'respondent_type' => null,
            ];
            if ($gid) { Database::update('groups', $data, 'id = ?', [$gid]); flash('Unit diperbarui.', 'success'); }
            else      { Database::insert('groups', $data); flash('Unit baru dibuat.', 'success'); }
        }

    } elseif ($act === 'del_unit') {
        $gid = (int)$_POST['id'];
        $g = Database::fetchOne("SELECT * FROM `groups` WHERE id=? AND type='penanganan'", [$gid]);
        if ($g && !$g['is_fixed']) {
            $dipakai = Database::fetchOne(
                "SELECT COUNT(*) c FROM feedback_categories WHERE handler_group_id=?", [$gid])['c'];
            if ($dipakai) flash("Unit masih dipakai $dipakai kategori. Pindahkan dulu kategorinya.", 'danger');
            else { Database::query("DELETE FROM `groups` WHERE id=?", [$gid]); flash('Unit dihapus.', 'success'); }
        } else flash('Unit ini tidak dapat dihapus.', 'danger');

    } elseif ($act === 'add_member') {
        $gid = (int)$_POST['group_id']; $uid = (int)$_POST['user_id'];
        if ($gid && $uid) {
            Database::query("INSERT IGNORE INTO user_groups (user_id, group_id) VALUES (?,?)", [$uid, $gid]);
            flash('Anggota ditambahkan.', 'success');
        }

    } elseif ($act === 'del_member') {
        Database::query("DELETE FROM user_groups WHERE user_id=? AND group_id=?",
            [(int)$_POST['user_id'], (int)$_POST['group_id']]);
        flash('Anggota dikeluarkan.', 'success');
    }
    header('Location: ' . APP_URL . '/admin/feedback_units.php');
    exit;
}

$units = fbUnits();
$staff = Database::fetchAll(
    "SELECT id, name, role FROM users WHERE is_active=1
     AND role IN ('superadmin','admin','foundation','leader','teacher','staff','mentor')
     ORDER BY name");

$edit = !empty($_GET['edit'])
    ? Database::fetchOne("SELECT * FROM `groups` WHERE id=? AND type='penanganan'", [(int)$_GET['edit']])
    : null;

ob_start(); ?>
<style>
.un-grid{display:grid;grid-template-columns:1fr 330px;gap:16px;align-items:start}
.pnl{background:#fff;border:1px solid #e3e5ea;border-radius:12px;padding:16px 18px;margin-bottom:13px}
.pnl-t{font-size:10.5px;font-weight:700;color:#6b6a83;text-transform:uppercase;letter-spacing:.6px;margin-bottom:11px}
.unit{background:#fff;border:1px solid #e3e5ea;border-radius:12px;padding:15px 17px;margin-bottom:11px}
.unit.kosong{border-color:#f3b5b0;background:#fffafa}
.unit-h{display:flex;align-items:flex-start;gap:10px;margin-bottom:9px}
.unit-n{font-size:14.5px;font-weight:700;color:#040136}
.unit-d{font-size:12px;color:#6b6a83;margin-top:2px;line-height:1.5}
.unit-m{font-size:11.5px;color:#6b6a83;display:flex;gap:12px;flex-wrap:wrap;margin-top:5px}
.mem{display:inline-flex;align-items:center;gap:5px;background:#eeebfc;color:#030870;border-radius:20px;padding:3px 5px 3px 11px;font-size:12px;font-weight:500;margin:0 4px 4px 0}
.mem button{background:none;border:none;color:#030870;opacity:.55;cursor:pointer;font-size:13px;line-height:1;padding:0 4px}
.mem button:hover{opacity:1;color:#b42318}
.form-label{font-size:11px;font-weight:600;color:#6b6a83;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.warn{background:#fff1dc;border:1px solid #ffc36b;border-radius:9px;padding:11px 14px;font-size:12.5px;color:#b83a01;line-height:1.6}
@media(max-width:992px){.un-grid{grid-template-columns:1fr}}
</style>

<?= showFlash() ?>

<div class="d-flex gap-1 flex-wrap mb-3">
  <a href="<?= APP_URL ?>/admin/feedback.php" class="btn btn-sm btn-outline-navy px-3"><i class="bi bi-inbox me-1"></i>Inbox Tiket</a>
  <a href="<?= APP_URL ?>/admin/feedback_dashboard.php" class="btn btn-sm btn-outline-navy px-3"><i class="bi bi-graph-up me-1"></i>Dashboard</a>
  <a href="<?= APP_URL ?>/admin/feedback_categories.php" class="btn btn-sm btn-outline-navy px-3"><i class="bi bi-tags me-1"></i>Kategori & Eskalasi</a>
</div>

<div class="alert alert-info small mb-3">
  <i class="bi bi-info-circle me-1"></i>
  <strong>Unit penanganan berbeda dari kelompok penilai di matriks 360.</strong>
  Matriks mengatur siapa menilai siapa. Unit mengatur siapa bertanggung jawab menyelesaikan apa.
  Seseorang bisa berada di keduanya, salah satu, atau tidak sama sekali — dan unit ini tidak akan
  pernah muncul di matriks evaluasi.
</div>

<div class="un-grid">
<div>
  <?php foreach ($units as $g):
    $members = fbUnitMembers((int)$g['id']);
    $kosong  = empty($members); ?>
  <div class="unit <?= $kosong?'kosong':'' ?>">
    <div class="unit-h">
      <div style="flex:1">
        <div class="unit-n">
          <?= h($g['name']) ?>
          <?php if ($g['is_fixed']) echo ' ' . fbChip('Terkunci','#fff','#b42318','#b42318'); ?>
        </div>
        <?php if ($g['description']): ?><div class="unit-d"><?= h($g['description']) ?></div><?php endif; ?>
        <div class="unit-m">
          <span><i class="bi bi-people me-1"></i><?= count($members) ?> anggota</span>
          <span><i class="bi bi-tags me-1"></i><?= (int)$g['kategori'] ?> kategori</span>
        </div>
      </div>
      <a href="?edit=<?= $g['id'] ?>" class="btn btn-sm btn-outline-navy px-2"><i class="bi bi-pencil"></i></a>
      <?php if (!$g['is_fixed']): ?>
      <form method="POST" class="d-inline">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="action" value="del_unit">
        <input type="hidden" name="id" value="<?= $g['id'] ?>">
        <button class="btn btn-sm btn-outline-navy px-2" data-confirm="Hapus unit ini?"><i class="bi bi-trash"></i></button>
      </form>
      <?php endif; ?>
    </div>

    <?php if ($kosong): ?>
    <div class="warn">
      <i class="bi bi-exclamation-triangle me-1"></i>
      Belum ada anggota. Tiket yang masuk ke unit ini tidak akan terlihat siapa pun sampai ada yang ditambahkan.
    </div>
    <?php else: ?>
    <div>
      <?php foreach ($members as $m): ?>
      <span class="mem">
        <?= h($m['name']) ?>
        <span style="opacity:.6;font-size:11px"><?= h(roleLabel($m['role'])) ?></span>
        <form method="POST" class="d-inline">
          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="action" value="del_member">
          <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
          <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
          <button title="Keluarkan dari unit">&times;</button>
        </form>
      </span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="d-flex gap-1 mt-2">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="action" value="add_member">
      <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
      <select name="user_id" class="form-select form-select-sm" required>
        <option value="">— Tambah anggota —</option>
        <?php foreach ($staff as $s): ?>
        <option value="<?= $s['id'] ?>"><?= h($s['name']) ?> — <?= h(roleLabel($s['role'])) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-sm btn-outline-navy px-2"><i class="bi bi-plus-lg"></i></button>
    </form>
  </div>
  <?php endforeach; ?>

  <?php if (!$units): ?>
  <div class="pnl text-center text-muted">
    Belum ada unit penanganan. Jalankan <code>migrations/013_handler_units.sql</code> terlebih dahulu.
  </div>
  <?php endif; ?>
</div>

<div>
  <div class="pnl">
    <div class="pnl-t"><?= $edit ? 'Ubah Unit' : 'Unit Baru' ?></div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="action" value="save_unit">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="mb-2">
        <label class="form-label">Nama Unit</label>
        <input type="text" name="name" class="form-control form-control-sm" required
               value="<?= h($edit['name'] ?? '') ?>" placeholder="Contoh: Perpustakaan">
      </div>
      <div class="mb-2">
        <label class="form-label">Keterangan</label>
        <input type="text" name="description" class="form-control form-control-sm"
               value="<?= h($edit['description'] ?? '') ?>" placeholder="Bidang yang ditangani">
      </div>
      <div class="mb-3">
        <label class="form-label">Urutan Tampil</label>
        <input type="number" name="order_num" class="form-control form-control-sm"
               value="<?= (int)($edit['order_num'] ?? 990) ?>">
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-navy btn-sm flex-fill"><i class="bi bi-check2 me-1"></i><?= $edit?'Simpan':'Tambah' ?></button>
        <?php if ($edit): ?><a href="?" class="btn btn-outline-navy btn-sm">Batal</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="pnl">
    <div class="pnl-t">Cara Kerjanya</div>
    <div style="font-size:12.5px;color:#2f2d4d;line-height:1.75">
      <p class="mb-2"><strong>1.</strong> Setiap kategori feedback menunjuk satu unit penanganan.</p>
      <p class="mb-2"><strong>2.</strong> Tiket baru masuk ke <em>antrean</em> unit itu — belum ada penanggung jawabnya.</p>
      <p class="mb-2"><strong>3.</strong> Seluruh anggota unit melihat dan menerima notifikasi.</p>
      <p class="mb-2"><strong>4.</strong> Satu orang menekan <em>Ambil Tiket</em> dan menjadi penanggung jawab.</p>
      <p class="mb-0"><strong>5.</strong> Anggota lain tetap memantau, jadi tiket tidak mati kalau yang bersangkutan cuti.</p>
    </div>
  </div>

  <a href="<?= APP_URL ?>/admin/feedback_categories.php" class="btn btn-sm btn-outline-navy w-100">
    <i class="bi bi-tags me-1"></i>Atur Kategori & Eskalasi
  </a>
</div>
</div>

<?php
$content = ob_get_clean();
pageWrapper('Unit Penanganan Feedback', $content);
