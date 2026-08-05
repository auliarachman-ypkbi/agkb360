<?php
// AGKB 360° — Kategori Feedback & Rute Eskalasi
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

    if ($act === 'save_category') {
        $cid   = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $pic   = (int)($_POST['default_pic_id'] ?? 0);
        $track = $_POST['track'] ?? 'inquiry';

        $unitId = (int)($_POST['handler_group_id'] ?? 0);
        if (mb_strlen($name) < 3)                      flash('Nama kategori terlalu pendek.', 'danger');
        elseif (!$unitId && !$pic)                     flash('Isi salah satu: unit penanganan atau penanggung jawab default. Kategori tanpa keduanya tidak akan tertangani.', 'danger');
        else {
            $data = [
                'name'               => $name,
                'description'        => trim($_POST['description'] ?? '') ?: null,
                'track'              => $track,
                'handler_group_id'   => (int)($_POST['handler_group_id'] ?? 0) ?: null,
                'default_pic_id'     => $pic ?: null,
                'default_priority'   => $_POST['default_priority'] ?? 'P3',
                'sla_response_hours' => max(1, (int)($_POST['sla_response_hours'] ?? 48)),
                'sla_resolve_hours'  => max(1, (int)($_POST['sla_resolve_hours'] ?? 120)),
                'start_level'        => $track === 'safeguarding' ? 3 : (int)($_POST['start_level'] ?? 1),
                'is_sensitive'       => $track === 'safeguarding' ? 1 : (!empty($_POST['is_sensitive']) ? 1 : 0),
                'allow_anonymous'    => $track === 'safeguarding' ? 1 : (!empty($_POST['allow_anonymous']) ? 1 : 0),
                'is_active'          => !empty($_POST['is_active']) ? 1 : 0,
                'order_num'          => (int)($_POST['order_num'] ?? 0),
            ];
            if ($cid) {
                Database::update('feedback_categories', $data, 'id = ?', [$cid]);
                flash('Kategori diperbarui.', 'success');
            } else {
                $base = preg_replace('/[^a-z0-9]+/','_', mb_strtolower($name));
                $data['code'] = mb_substr(($track === 'safeguarding' ? 'sg_' : ($track === 'apresiasi' ? 'apr_' : 'inq_')) . $base, 0, 40);
                $n = 1; $code = $data['code'];
                while (Database::fetchOne("SELECT 1 FROM feedback_categories WHERE code=?", [$data['code']])) {
                    $data['code'] = mb_substr($code, 0, 36) . '_' . (++$n);
                }
                Database::insert('feedback_categories', $data);
                flash('Kategori baru dibuat.', 'success');
            }
        }

    } elseif ($act === 'toggle') {
        $cid = (int)$_POST['id'];
        Database::query("UPDATE feedback_categories SET is_active = 1 - is_active WHERE id=?", [$cid]);
        flash('Status kategori diubah.', 'success');

    } elseif ($act === 'save_route') {
        $level = (int)($_POST['level'] ?? 1);
        $uid   = (int)($_POST['user_id'] ?? 0);
        $trk   = $_POST['track'] ?: null;
        $cat   = (int)($_POST['category_id'] ?? 0) ?: null;
        if ($uid && $level >= 1 && $level <= 3) {
            Database::insert('feedback_escalation_levels', [
                'level'=>$level, 'label'=>fbLevels()[$level] ?? "Level $level",
                'track'=>$trk, 'category_id'=>$cat, 'user_id'=>$uid, 'order_num'=>10*$level,
            ]);
            flash('Rute eskalasi ditambahkan.', 'success');
        }

    } elseif ($act === 'del_route') {
        Database::query("DELETE FROM feedback_escalation_levels WHERE id=?", [(int)$_POST['id']]);
        flash('Rute dihapus.', 'success');
    }
    header('Location: ' . APP_URL . '/admin/feedback_categories.php');
    exit;
}

$cats = Database::fetchAll(
    "SELECT c.*, u.name AS pic_name, g.name AS unit_name,
            (SELECT COUNT(*) FROM feedback_tickets t WHERE t.category_id=c.id) AS ticket_count
     FROM feedback_categories c
     LEFT JOIN users u ON u.id = c.default_pic_id
     LEFT JOIN `groups` g ON g.id = c.handler_group_id
     ORDER BY c.track, c.order_num, c.name");

$routes = Database::fetchAll(
    "SELECT r.*, u.name AS user_name, c.name AS cat_name
     FROM feedback_escalation_levels r
     LEFT JOIN users u ON u.id=r.user_id
     LEFT JOIN feedback_categories c ON c.id=r.category_id
     ORDER BY r.level, r.order_num");

$staff = Database::fetchAll(
    "SELECT id,name,role FROM users WHERE is_active=1
     AND role IN ('superadmin','admin','foundation','leader','teacher','staff','mentor') ORDER BY name");

$edit = null;
if (!empty($_GET['edit'])) $edit = Database::fetchOne("SELECT * FROM feedback_categories WHERE id=?", [(int)$_GET['edit']]);

$byTrack = [];
foreach ($cats as $c) $byTrack[$c['track']][] = $c;

ob_start(); ?>
<style>
.ct-grid{display:grid;grid-template-columns:1fr 360px;gap:16px;align-items:start}
.pnl{background:#fff;border:1px solid #e3e5ea;border-radius:12px;padding:16px 18px;margin-bottom:13px}
.pnl-t{font-size:10.5px;font-weight:700;color:#6b6a83;text-transform:uppercase;letter-spacing:.6px;margin-bottom:11px}
.crow{display:flex;gap:11px;align-items:center;padding:10px 12px;border:1px solid #e3e5ea;border-radius:9px;margin-bottom:7px;background:#fff}
.crow.off{opacity:.5;background:#fafafb}
.crow-n{font-size:13.5px;font-weight:600;color:#040136}
.crow-d{font-size:11.5px;color:#6b6a83;margin-top:2px}
.crow-m{font-size:11px;color:#6b6a83;display:flex;gap:9px;flex-wrap:wrap;margin-top:4px}
.nopic{color:#b42318;font-weight:600}
.sec-h{font-size:13px;font-weight:700;color:#040136;margin:18px 0 9px;display:flex;align-items:center;gap:7px}
.sec-h:first-child{margin-top:0}
.rrow{display:flex;gap:9px;align-items:center;padding:8px 11px;border:1px solid #e3e5ea;border-radius:8px;margin-bottom:6px;font-size:12.5px}
.form-label{font-size:11px;font-weight:600;color:#6b6a83;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
@media(max-width:992px){.ct-grid{grid-template-columns:1fr}}
</style>

<?= showFlash() ?>

<div class="d-flex gap-1 flex-wrap mb-3">
  <a href="<?= APP_URL ?>/admin/feedback.php" class="btn btn-sm btn-outline-navy px-3"><i class="bi bi-inbox me-1"></i>Inbox Tiket</a>
  <a href="<?= APP_URL ?>/admin/feedback_dashboard.php" class="btn btn-sm btn-outline-navy px-3"><i class="bi bi-graph-up me-1"></i>Dashboard</a>
  <a href="<?= APP_URL ?>/admin/feedback_units.php" class="btn btn-sm btn-navy px-3"><i class="bi bi-diagram-3 me-1"></i>Unit Penanganan</a>
</div>

<div class="alert alert-info small mb-3">
  <i class="bi bi-info-circle me-1"></i>
  Kategori tanpa unit penanganan maupun PIC akan menghasilkan tiket yang tidak tertangani.
  Isi salah satunya — dan lengkapi SLA serta prioritas default.
</div>

<div class="ct-grid">
<div>
  <?php foreach (fbTracks() as $tk=>$tv):
    if (empty($byTrack[$tk])) continue; ?>
  <div class="sec-h"><i class="bi <?= $tv['icon'] ?>" style="color:<?= $tv['color'] ?>"></i><?= h($tv['label']) ?></div>
  <?php foreach ($byTrack[$tk] as $c): ?>
  <div class="crow <?= $c['is_active']?'':'off' ?>">
    <div style="flex:1">
      <div class="crow-n"><?= h($c['name']) ?>
        <?php if ($c['is_sensitive']) echo ' ' . fbChip('Sensitif','#fff','#b42318','#b42318'); ?>
        <?php if ($c['allow_anonymous']) echo ' ' . fbChip('Anonim OK','#030870','#eeebfc'); ?>
      </div>
      <?php if ($c['description']): ?><div class="crow-d"><?= h($c['description']) ?></div><?php endif; ?>
      <div class="crow-m">
        <span class="<?= ($c['handler_group_id'] || $c['default_pic_id']) ? '' : 'nopic' ?>">
          <?php if ($c['unit_name']): ?>
            <i class="bi bi-diagram-3 me-1"></i><?= h($c['unit_name']) ?>
          <?php elseif ($c['pic_name']): ?>
            <i class="bi bi-person-badge me-1"></i><?= h($c['pic_name']) ?>
          <?php else: ?>
            <i class="bi bi-exclamation-triangle me-1"></i>Belum ada unit / PIC
          <?php endif; ?>
        </span>
        <span><i class="bi bi-flag me-1"></i><?= h($c['default_priority']) ?></span>
        <span><i class="bi bi-clock me-1"></i><?= (int)$c['sla_response_hours'] ?>j / <?= (int)$c['sla_resolve_hours'] ?>j</span>
        <span><i class="bi bi-diagram-2 me-1"></i>Mulai L<?= (int)$c['start_level'] ?></span>
        <span><i class="bi bi-ticket me-1"></i><?= (int)$c['ticket_count'] ?> tiket</span>
      </div>
    </div>
    <a href="?edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline-navy px-2"><i class="bi bi-pencil"></i></a>
    <form method="POST" class="d-inline">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="action" value="toggle">
      <input type="hidden" name="id" value="<?= $c['id'] ?>">
      <button class="btn btn-sm btn-outline-navy px-2" title="<?= $c['is_active']?'Nonaktifkan':'Aktifkan' ?>">
        <i class="bi bi-<?= $c['is_active']?'eye-slash':'eye' ?>"></i>
      </button>
    </form>
  </div>
  <?php endforeach; endforeach; ?>
</div>

<div>
  <div class="pnl">
    <div class="pnl-t"><?= $edit ? 'Ubah Kategori' : 'Kategori Baru' ?></div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="action" value="save_category">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

      <div class="mb-2">
        <label class="form-label">Nama</label>
        <input type="text" name="name" class="form-control form-control-sm" required value="<?= h($edit['name'] ?? '') ?>">
      </div>
      <div class="mb-2">
        <label class="form-label">Keterangan</label>
        <input type="text" name="description" class="form-control form-control-sm" value="<?= h($edit['description'] ?? '') ?>">
      </div>
      <div class="mb-2">
        <label class="form-label">Jenis (track)</label>
        <select name="track" class="form-select form-select-sm" id="trk">
          <?php foreach (fbTracks() as $k=>$v): ?>
          <option value="<?= $k ?>" <?= ($edit['track']??'inquiry')===$k?'selected':'' ?>><?= h($v['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-2">
        <label class="form-label">Unit Penanganan</label>
        <select name="handler_group_id" class="form-select form-select-sm">
          <option value="0">— Tidak lewat unit —</option>
          <?php foreach (fbUnits() as $g): ?>
          <option value="<?= $g['id'] ?>" <?= (int)($edit['handler_group_id']??0)===(int)$g['id']?'selected':'' ?>>
            <?= h($g['name']) ?> (<?= (int)$g['anggota'] ?> anggota)</option>
          <?php endforeach; ?>
        </select>
        <div style="font-size:11px;color:#6b6a83;margin-top:4px;line-height:1.5">
          Tiket masuk ke antrean unit. Semua anggota melihat, satu orang mengambilnya.
        </div>
      </div>
      <div class="mb-2">
        <label class="form-label">Penanggung Jawab Default</label>
        <select name="default_pic_id" class="form-select form-select-sm">
          <option value="0">— Belum ditentukan —</option>
          <?php foreach ($staff as $s): ?>
          <option value="<?= $s['id'] ?>" <?= (int)($edit['default_pic_id']??0)===(int)$s['id']?'selected':'' ?>>
            <?= h($s['name']) ?> — <?= h(roleLabel($s['role'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="row g-2 mb-2">
        <div class="col-6">
          <label class="form-label">Prioritas</label>
          <select name="default_priority" class="form-select form-select-sm">
            <?php foreach (fbPriorities() as $k=>$v): ?>
            <option value="<?= $k ?>" <?= ($edit['default_priority']??'P3')===$k?'selected':'' ?>><?= $k ?> · <?= h($v['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label">Mulai level</label>
          <select name="start_level" class="form-select form-select-sm">
            <?php foreach (fbLevels() as $k=>$v): ?>
            <option value="<?= $k ?>" <?= (int)($edit['start_level']??1)===$k?'selected':'' ?>>L<?= $k ?> · <?= h($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label">SLA respons (jam)</label>
          <input type="number" name="sla_response_hours" min="1" class="form-control form-control-sm" value="<?= (int)($edit['sla_response_hours'] ?? 48) ?>">
        </div>
        <div class="col-6">
          <label class="form-label">SLA selesai (jam)</label>
          <input type="number" name="sla_resolve_hours" min="1" class="form-control form-control-sm" value="<?= (int)($edit['sla_resolve_hours'] ?? 120) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Urutan tampil</label>
          <input type="number" name="order_num" class="form-control form-control-sm" value="<?= (int)($edit['order_num'] ?? 100) ?>">
        </div>
      </div>
      <div class="form-check form-check-sm mb-1">
        <input class="form-check-input" type="checkbox" name="allow_anonymous" id="an" value="1" <?= !empty($edit['allow_anonymous'])?'checked':'' ?>>
        <label class="form-check-label small" for="an">Boleh dikirim anonim</label>
      </div>
      <div class="form-check form-check-sm mb-1">
        <input class="form-check-input" type="checkbox" name="is_sensitive" id="sv" value="1" <?= !empty($edit['is_sensitive'])?'checked':'' ?>>
        <label class="form-check-label small" for="sv">Sensitif — sembunyikan dari level bawah</label>
      </div>
      <div class="form-check form-check-sm mb-3">
        <input class="form-check-input" type="checkbox" name="is_active" id="ac" value="1" <?= (!$edit || $edit['is_active'])?'checked':'' ?>>
        <label class="form-check-label small" for="ac">Aktif</label>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-navy btn-sm flex-fill"><i class="bi bi-check2 me-1"></i><?= $edit?'Simpan':'Tambah' ?></button>
        <?php if ($edit): ?><a href="?" class="btn btn-outline-navy btn-sm">Batal</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="pnl">
    <div class="pnl-t">Rute Eskalasi</div>
    <?php foreach (fbLevels() as $lv=>$lb):
      $rs = array_filter($routes, fn($r) => (int)$r['level'] === $lv && $r['user_id']); ?>
    <div style="font-size:12px;font-weight:700;color:#040136;margin:10px 0 5px">L<?= $lv ?> · <?= h($lb) ?></div>
    <?php if (!$rs): ?>
      <div style="font-size:11.5px;color:#b42318;margin-bottom:6px">
        <i class="bi bi-exclamation-triangle me-1"></i>Belum ada penanggung jawab di level ini.
      </div>
    <?php else: foreach ($rs as $r): ?>
      <div class="rrow">
        <span style="flex:1"><?= h($r['user_name']) ?>
          <?php if ($r['cat_name']): ?><span style="color:#6b6a83;font-size:11px"> · <?= h($r['cat_name']) ?></span><?php endif; ?>
          <?php if ($r['track']): ?><span style="color:#6b6a83;font-size:11px"> · <?= h(fbTracks()[$r['track']]['label'] ?? '') ?></span><?php endif; ?>
        </span>
        <form method="POST" class="d-inline">
          <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="action" value="del_route">
          <input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button class="btn btn-sm btn-outline-navy px-2 py-0"><i class="bi bi-x"></i></button>
        </form>
      </div>
    <?php endforeach; endif; endforeach; ?>

    <form method="POST" class="mt-3 pt-2" style="border-top:1px solid #f3f4f6">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="action" value="save_route">
      <div class="row g-1 mb-1">
        <div class="col-4">
          <select name="level" class="form-select form-select-sm">
            <?php foreach (fbLevels() as $k=>$v): ?><option value="<?= $k ?>">L<?= $k ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-8">
          <select name="user_id" class="form-select form-select-sm" required>
            <option value="">— Pilih orang —</option>
            <?php foreach ($staff as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="row g-1 mb-2">
        <div class="col-6">
          <select name="track" class="form-select form-select-sm">
            <option value="">Semua jenis</option>
            <?php foreach (fbTracks() as $k=>$v): ?><option value="<?= $k ?>"><?= h($v['label']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-6">
          <select name="category_id" class="form-select form-select-sm">
            <option value="0">Semua kategori</option>
            <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <button class="btn btn-outline-navy btn-sm w-100"><i class="bi bi-plus-lg me-1"></i>Tambah Rute</button>
      <div style="font-size:11px;color:#6b6a83;margin-top:6px;line-height:1.5">
        Rute dengan kategori spesifik mengalahkan rute umum — pakai ini untuk mengarahkan langsung ke pihak tertentu.
      </div>
    </form>
  </div>
</div>
</div>

<script>
document.getElementById('trk').addEventListener('change', function(){
  var sg = this.value === 'safeguarding';
  if (sg) { document.getElementById('an').checked = true; document.getElementById('sv').checked = true; }
});
</script>

<?php
$content = ob_get_clean();
pageWrapper('Kategori Feedback & Eskalasi', $content);
