<?php
/**
 * ============================================================
 * AGKB 360° — Impor Siswa Angkatan Baru
 * Migration 026
 * ------------------------------------------------------------
 * IDEMPOTEN. Aman dijalankan berulang kali.
 *
 *   - Email sudah ada  → hanya nama yang diperbarui bila berbeda.
 *                        Password, last_login, role, dan class_id
 *                        TIDAK disentuh.
 *   - Email belum ada  → dibuat baru dengan peran 'student',
 *                        last_login NULL, dan kata sandi acak yang
 *                        tidak diketahui siapa pun. Akses diberikan
 *                        lewat tautan set-password dari blast email.
 *
 * class_id sengaja dibiarkan kosong. Penempatan kelas menentukan
 * siapa menilai guru mana pada evaluasi 360°, dan menebaknya lebih
 * berbahaya daripada mengosongkannya. Untuk kanal feedback, kelas
 * tidak diperlukan — siswa sudah bisa mengirim laporan tanpa itu.
 *
 * Sumber data: migrations/data/siswa-angkatan-2026.tsv
 * Berkas TSV berkolom: Nama <tab> Email, dengan satu baris judul.
 *
 * Pakai:
 *   php migrations/026_import_siswa_2026.php --dry
 *   php migrations/026_import_siswa_2026.php --db=ktb_production
 *   php migrations/026_import_siswa_2026.php --db=ktb_evaluation
 * ============================================================
 */

declare(strict_types=1);

$opts   = getopt('', ['dry', 'db::', 'file::']);
$dryRun = isset($opts['dry']);
$dbName = $opts['db']   ?? 'ktb_production';
$berkas = $opts['file'] ?? __DIR__ . '/data/siswa-angkatan-2026.tsv';

$host = getenv('DB_HOST') ?: 'mysql';
$user = getenv('DB_USER') ?: 'ktb_user';
$pass = getenv('DB_PASS') ?: 'ktb_pass_2024';

echo str_repeat('=', 70) . "\n";
echo "AGKB 360° — Impor Siswa Angkatan Baru\n";
echo 'Database : ' . $dbName . "\n";
echo 'Berkas   : ' . $berkas . "\n";
echo 'Mode     : ' . ($dryRun ? 'SIMULASI (tidak menulis apa pun)' : 'TULIS') . "\n";
echo str_repeat('=', 70) . "\n";

// ── Baca berkas ─────────────────────────────────────────────

if (!is_readable($berkas)) {
    fwrite(STDERR, "Berkas tidak terbaca: $berkas\n");
    exit(1);
}

$baris  = preg_split('/\R/', file_get_contents($berkas));
$roster = [];
$rusak  = [];

foreach ($baris as $i => $b) {
    $b = trim($b, "\xEF\xBB\xBF \t\r\n");
    if ($b === '') continue;

    $p = array_map('trim', explode("\t", $b));

    // Baris judul dilewati, dikenali dari isinya bukan dari nomornya
    if (count($p) >= 2 && strcasecmp($p[0], 'Nama') === 0) continue;

    if (count($p) < 2 || !filter_var($p[1], FILTER_VALIDATE_EMAIL)) {
        $rusak[] = ($i + 1) . ': ' . $b;
        continue;
    }
    $roster[] = [$p[0], mb_strtolower($p[1])];
}

if ($rusak) {
    echo "\n⚠  " . count($rusak) . " baris tidak terbaca dan dilewati:\n";
    foreach (array_slice($rusak, 0, 10) as $r) echo "   $r\n";
}

// Email ganda di dalam berkas — diambil yang pertama saja
$unik = [];
foreach ($roster as [$n, $m]) {
    if (!isset($unik[$m])) $unik[$m] = $n;
}
if (count($unik) !== count($roster)) {
    echo '⚠  ' . (count($roster) - count($unik)) . " email ganda di dalam berkas, diambil yang pertama.\n";
}

echo "\nSiswa dalam berkas : " . count($unik) . "\n";

// ── Sambung database ────────────────────────────────────────

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbName;charset=utf8mb4", $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    fwrite(STDERR, 'Gagal menyambung database: ' . $e->getMessage() . "\n");
    exit(1);
}

$cari    = $pdo->prepare('SELECT id, name, role FROM users WHERE email = ?');
$buat    = $pdo->prepare(
    'INSERT INTO users (name, email, password, role, is_active, class_id)
     VALUES (?, ?, ?, ?, 1, NULL)');
$perbarui = $pdo->prepare('UPDATE users SET name = ? WHERE id = ?');

$baru = 0; $ganti = 0; $tetap = 0; $bentrok = [];
$daftarBaru = [];

if (!$dryRun) $pdo->beginTransaction();

foreach ($unik as $email => $nama) {
    $cari->execute([$email]);
    $ada = $cari->fetch();

    if ($ada) {
        // Email sudah dipakai peran lain — jangan diubah diam-diam
        if ($ada['role'] !== 'student') {
            $bentrok[] = "$email — sudah terdaftar sebagai " . $ada['role'] . ' (' . $ada['name'] . ')';
            continue;
        }
        if ($ada['name'] !== $nama) {
            if (!$dryRun) $perbarui->execute([$nama, $ada['id']]);
            $ganti++;
        } else {
            $tetap++;
        }
        continue;
    }

    // Kata sandi acak yang tidak diberitahukan ke siapa pun.
    // Akun baru bisa dipakai setelah pemiliknya menetapkan sendiri
    // lewat tautan aktivasi.
    if (!$dryRun) {
        $buat->execute([
            $nama, $email,
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'student',
        ]);
    }
    $baru++;
    if (count($daftarBaru) < 8) $daftarBaru[] = "$nama <$email>";
}

if (!$dryRun) $pdo->commit();

// ── Laporan ─────────────────────────────────────────────────

echo "\n" . str_repeat('-', 70) . "\n";
printf("Akun baru        : %d\n", $baru);
printf("Nama diperbarui  : %d\n", $ganti);
printf("Sudah sesuai     : %d\n", $tetap);
printf("Bentrok peran    : %d\n", count($bentrok));

if ($daftarBaru) {
    echo "\nContoh akun baru:\n";
    foreach ($daftarBaru as $d) echo "   · $d\n";
    if ($baru > count($daftarBaru)) echo '   … dan ' . ($baru - count($daftarBaru)) . " lagi\n";
}

if ($bentrok) {
    echo "\n⚠  Email berikut sudah dipakai peran lain dan TIDAK disentuh:\n";
    foreach ($bentrok as $b) echo "   · $b\n";
}

$total = $pdo->query("SELECT COUNT(*) AS n FROM users WHERE role='student' AND is_active=1")->fetch()['n'];
echo "\nTotal siswa aktif di database : $total\n";

$belum = $pdo->query("SELECT COUNT(*) AS n FROM users WHERE is_active=1 AND last_login IS NULL")->fetch()['n'];
echo "Belum pernah login (semua peran) : $belum\n";
echo str_repeat('=', 70) . "\n";

if ($dryRun) {
    echo "\nSIMULASI — tidak ada yang ditulis. Jalankan tanpa --dry untuk menerapkan.\n";
}
