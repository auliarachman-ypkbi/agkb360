<?php
/**
 * ============================================================
 * AGKB 360° — Isi Keanggotaan Unit Penanganan
 * ------------------------------------------------------------
 * IDEMPOTEN. Hanya MENAMBAH keanggotaan, tidak pernah menghapus.
 * Hanya menyentuh grup ber-type = 'penanganan', jadi keanggotaan
 * di kelompok evaluasi 360° sama sekali tidak terganggu.
 *
 * Unit "Perlindungan Anak (Yayasan)" diisi otomatis dari pengguna
 * ber-role 'foundation' — bukan didaftar manual, supaya tidak ada
 * orang sekolah yang tidak sengaja masuk ke sana.
 *
 * Pakai:
 *   php tools/isi_unit_penanganan.php --dry
 *   php tools/isi_unit_penanganan.php
 * ============================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$opts   = getopt('', ['dry', 'db::']);
$dryRun = isset($opts['dry']);
$dbName = $opts['db'] ?? 'ktb_production';

$host = getenv('DB_HOST') ?: 'mysql';
$user = getenv('DB_USER') ?: 'ktb_user';
$pass = getenv('DB_PASS') ?: 'ktb_pass_2024';

// ── Peta unit → email anggota ───────────────────────────────
$peta = [
    'Tata Usaha & Administrasi' => [
        'herlangga.hizkiawan@sma-ktb.sch.id',   // Secretary of HoS
        'adminktb@sma-ktb.sch.id',              // National Admin Staff
        'toni.yunanto@sma-ktb.sch.id',          // Deputy Head of Operations
        'hos_secretary@sma-ktb.sch.id',         // kotak surat sekretariat
    ],
    'Sarana & Prasarana' => [
        'partono.warsito@sma-ktb.sch.id',       // GA Coordinator
        'helmi.arrasyid@sma-ktb.sch.id',        // Physics Lab
        'marni.limbong@sma-ktb.sch.id',         // Biology Lab
        'elda.rama@sma-ktb.sch.id',             // Chemistry Lab
    ],
    'Teknologi Informasi' => [
        'it.coordinator@sma-ktb.sch.id',        // IT Coordinator
        'it-admin@sma-ktb.sch.id',              // IT Support
    ],
    'Humas & Komunikasi' => [
        'socmed-prod@sma-ktb.sch.id',
        'socmed-spc@sma-ktb.sch.id',
        'info@sma-ktb.sch.id',                  // kotak surat humas
    ],
    'Kesiswaan' => [
        'danika.khafifah@sma-ktb.sch.id',       // CCA Admin
        'cca@sma-ktb.sch.id',                   // kotak surat CCA
        'bennartho.rapoho@sma-ktb.sch.id',      // Student Council Coordinator
        'boyke.rionaldo@sma-ktb.sch.id',        // CAS Coordinator
        'dian.marbun@sma-ktb.sch.id',           // SEL Counselor
        'salwa.dzahabyyah@sma-ktb.sch.id',      // Career Counselor
        'yosephine.sihombing@sma-ktb.sch.id',   // SEL/CC Counselor
    ],
    'Kurikulum & IB DP' => [
        'ibdpadmin@sma-ktb.sch.id',             // IBDP Admin Staff
        'librarian-admin@sma-ktb.sch.id',       // Librarian Staff
        'patresia.gultom@sma-ktb.sch.id',       // Teacher Librarian
    ],
    'Kepegawaian & SDM' => [
        'toni.yunanto@sma-ktb.sch.id',          // Deputy Head of Operations
    ],
    // 'Perlindungan Anak (Yayasan)' diisi otomatis dari role foundation.
];

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbName;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "GAGAL menyambung ke $dbName: " . $e->getMessage() . "\n");
    exit(1);
}

echo str_repeat('=', 70) . "\n";
echo "AGKB 360° — Isi Unit Penanganan\n";
echo "Database : $dbName\n";
echo "Mode     : " . ($dryRun ? 'SIMULASI (tidak menulis apa pun)' : 'EKSEKUSI') . "\n";
echo str_repeat('=', 70) . "\n";

// Isi otomatis unit Yayasan dari role foundation
$yayasan = $pdo->query("SELECT email FROM users WHERE role='foundation' AND is_active=1")
               ->fetchAll(PDO::FETCH_COLUMN);
$peta['Perlindungan Anak (Yayasan)'] = $yayasan;
if (!$yayasan) {
    echo "\n⚠  Tidak ada pengguna ber-role 'foundation'. Unit Perlindungan Anak\n";
    echo "   akan tetap kosong — laporan safeguarding tidak akan sampai ke siapa pun.\n";
}

$cariUnit = $pdo->prepare("SELECT id FROM `groups` WHERE name = ? AND type = 'penanganan'");
$cariUser = $pdo->prepare("SELECT id, name, role FROM users WHERE email = ? AND is_active = 1");
$sudahAda = $pdo->prepare("SELECT 1 FROM user_groups WHERE user_id = ? AND group_id = ?");
$tambah   = $pdo->prepare("INSERT INTO user_groups (user_id, group_id) VALUES (?, ?)");

$ditambah = 0; $dilewati = 0; $gagal = [];

foreach ($peta as $namaUnit => $emails) {
    $cariUnit->execute([$namaUnit]);
    $unitId = $cariUnit->fetchColumn();
    if (!$unitId) {
        $gagal[] = "Unit tidak ditemukan: $namaUnit";
        continue;
    }

    echo "\n── $namaUnit\n";
    if (!$emails) { echo "   (kosong)\n"; continue; }

    foreach ($emails as $email) {
        $cariUser->execute([strtolower(trim($email))]);
        $u = $cariUser->fetch(PDO::FETCH_ASSOC);
        if (!$u) { $gagal[] = "Pengguna tidak ditemukan: $email"; continue; }

        $sudahAda->execute([(int)$u['id'], (int)$unitId]);
        if ($sudahAda->fetchColumn()) {
            printf("   ·  %-34s %-9s sudah anggota\n", $u['name'], $u['role']);
            $dilewati++;
            continue;
        }

        if (!$dryRun) $tambah->execute([(int)$u['id'], (int)$unitId]);
        printf("   +  %-34s %-9s %s\n", $u['name'], $u['role'], $dryRun ? '(akan ditambah)' : 'DITAMBAH');
        $ditambah++;
    }
}

if ($gagal) {
    echo "\n── ⚠ MASALAH ──\n";
    foreach ($gagal as $g) echo "   $g\n";
}

// ── Ringkasan ───────────────────────────────────────────────
echo "\n" . str_repeat('-', 70) . "\n";
$rows = $pdo->query(
    "SELECT g.name,
            (SELECT COUNT(*) FROM user_groups ug WHERE ug.group_id = g.id) AS anggota,
            (SELECT COUNT(*) FROM feedback_categories c WHERE c.handler_group_id = g.id) AS kategori
     FROM `groups` g WHERE g.type = 'penanganan' ORDER BY g.order_num"
)->fetchAll(PDO::FETCH_ASSOC);

printf("%-32s %9s %10s\n", 'UNIT PENANGANAN', 'ANGGOTA', 'KATEGORI');
foreach ($rows as $r) {
    $tanda = (int)$r['anggota'] === 0 ? '  ← KOSONG' : '';
    printf("%-32s %9d %10d%s\n", $r['name'], $r['anggota'], $r['kategori'], $tanda);
}

echo "\nDitambah: $ditambah   ·   Sudah ada sebelumnya: $dilewati\n";
echo str_repeat('=', 70) . "\n";
echo $dryRun
    ? "SIMULASI selesai. Jalankan tanpa --dry untuk menerapkan.\n"
    : "Selesai.\n";
