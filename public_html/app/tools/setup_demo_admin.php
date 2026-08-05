<?php
// ============================================================
// AGKB 360° — Akun admin demo ringkas + diagnosis unit penanganan
// ------------------------------------------------------------
// Membuat satu akun admin beremail pendek untuk peragaan, lalu
// memasukkannya ke SEMUA unit penanganan agar bisa melihat
// seluruh antrean. Sekaligus menampilkan kondisi unit di database.
//
// Jalankan:
//   docker exec ktb_php php /var/www/html/demo/tools/setup_demo_admin.php
// ============================================================

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Hanya lewat CLI.\n"); }

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';

$EMAIL = 'demo@agkb360.app';
$PASS  = 'AGKBdemo2026';
$NAMA  = 'Admin Demo';

echo "Database: " . DB_NAME . "\n";
if (DB_NAME === 'ktb_production') {
    exit("DITOLAK. Skrip ini khusus database demo.\n");
}

// ── 1. Diagnosis unit penanganan ────────────────────────────
echo "\n── Kondisi tabel groups ──────────────────────────────\n";

$kolom = Database::fetchAll("SHOW COLUMNS FROM `groups`");
$punya = array_column($kolom, 'Field');
foreach (['type','respondent_type','order_num','is_fixed'] as $k) {
    printf("  kolom %-16s %s\n", $k, in_array($k, $punya, true) ? 'ADA' : '*** HILANG ***');
}

$tipe = Database::fetchAll("SELECT type, COUNT(*) c FROM `groups` GROUP BY type ORDER BY c DESC");
echo "\n  Jenis grup yang ada:\n";
foreach ($tipe as $t) printf("    %-16s %d grup\n", $t['type'] ?: '(kosong)', $t['c']);

$units = Database::fetchAll(
    "SELECT g.id, g.name, g.respondent_type,
            (SELECT COUNT(*) FROM user_groups ug WHERE ug.group_id=g.id) AS anggota,
            (SELECT COUNT(*) FROM feedback_categories c WHERE c.handler_group_id=g.id) AS kategori
     FROM `groups` g WHERE g.type='penanganan' ORDER BY g.order_num, g.name");

echo "\n── Unit penanganan ───────────────────────────────────\n";
if (!$units) {
    echo "  *** TIDAK ADA unit penanganan di database ini. ***\n";
    echo "  Jalankan: migrations/013_handler_units.sql pada " . DB_NAME . "\n\n";
} else {
    foreach ($units as $u) {
        printf("  #%-3s %-32s %2d anggota · %2d kategori%s\n",
            $u['id'], $u['name'], $u['anggota'], $u['kategori'],
            $u['respondent_type'] ? '  [!] respondent_type terisi, seharusnya kosong' : '');
    }
    echo "\n  Total: " . count($units) . " unit\n";
}

$catTanpaUnit = Database::fetchAll(
    "SELECT code, name FROM feedback_categories WHERE handler_group_id IS NULL AND is_active=1");
if ($catTanpaUnit) {
    echo "\n  Kategori yang belum punya unit:\n";
    foreach ($catTanpaUnit as $c) printf("    %-18s %s\n", $c['code'], $c['name']);
}

// ── 2. Akun admin demo ──────────────────────────────────────
echo "\n── Akun admin demo ───────────────────────────────────\n";

$row = Database::fetchOne("SELECT id FROM users WHERE email=?", [$EMAIL]);
if ($row) {
    Database::update('users', [
        'name'      => $NAMA,
        'password'  => password_hash($PASS, PASSWORD_DEFAULT),
        'role'      => 'admin',
        'is_active' => 1,
    ], 'id = ?', [$row['id']]);
    $uid = (int)$row['id'];
    echo "  Akun sudah ada — kata sandi disetel ulang.\n";
} else {
    $uid = Database::insert('users', [
        'name'      => $NAMA,
        'email'     => $EMAIL,
        'password'  => password_hash($PASS, PASSWORD_DEFAULT),
        'role'      => 'admin',
        'is_active' => 1,
    ]);
    echo "  Akun baru dibuat.\n";
}

// Masukkan ke semua unit penanganan agar melihat seluruh antrean
$masuk = 0;
foreach ($units as $u) {
    Database::query("INSERT IGNORE INTO user_groups (user_id, group_id) VALUES (?,?)", [$uid, $u['id']]);
    $masuk++;
}
echo "  Dimasukkan ke $masuk unit penanganan.\n";

echo "\n─────────────────────────────────────────────────────\n";
echo "  Email        : $EMAIL\n";
echo "  Kata sandi   : $PASS\n";
echo "  Peran        : admin\n";
echo "  Unit         : semua unit penanganan\n";
echo "─────────────────────────────────────────────────────\n";
echo "Buka: Admin CMS → Unit Penanganan\n";
