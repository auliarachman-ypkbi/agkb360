<?php
/**
 * ============================================================
 * AGKB 360° — Impor Roster Guru & Tenaga Kependidikan 2026
 * Migration 014
 * ------------------------------------------------------------
 * IDEMPOTEN. Aman dijalankan berulang kali.
 *
 *   - Email sudah ada  → hanya nama yang diperbarui (bila beda).
 *                        Password, last_login, role TIDAK disentuh.
 *   - Email belum ada  → dibuat baru, status "belum pernah login"
 *                        (last_login = NULL) dan password acak yang
 *                        tidak diketahui siapa pun. Akses diberikan
 *                        lewat tautan set-password dari blast email.
 *
 * TIDAK menyentuh: siswa, orang tua, tiket feedback, jawaban survei.
 * TIDAK membuat departemen / subject group — itu menyusul di revamp
 * dashboard 360. Di sini murni akun dan status.
 *
 * Pakai:
 *   php migrations/014_import_roster_2026.php --dry
 *   php migrations/014_import_roster_2026.php
 *   php migrations/014_import_roster_2026.php --db=ktb_evaluation
 * ============================================================
 */

declare(strict_types=1);

$opts   = getopt('', ['dry', 'db::']);
$dryRun = isset($opts['dry']);
$dbName = $opts['db'] ?? 'ktb_production';

$host = getenv('DB_HOST') ?: 'mysql';
$user = getenv('DB_USER') ?: 'ktb_user';
$pass = getenv('DB_PASS') ?: 'ktb_pass_2024';

// ── Roster resmi ────────────────────────────────────────────
// [nama lengkap, email, role, keterangan peran]
$roster = [
    // ── Tenaga Pendidik (37) ────────────────────────────────
    ['Marcellina Lintang',                    'marcellina.lintang@sma-ktb.sch.id',   'teacher', 'English · EE Coordinator'],
    ['Stefanus Angga Badara Prima',           'stefanus.angga@sma-ktb.sch.id',       'teacher', 'English'],
    ['Satiul Komariah',                       'satiul.komariah@sma-ktb.sch.id',      'teacher', 'English'],
    ['Mustaqim Haniru',                       'mustaqim.haniru@sma-ktb.sch.id',      'teacher', 'English'],
    ['Clarasia Kiky Anggraeni',               'c.kiky.anggraeni@sma-ktb.sch.id',     'teacher', 'Indonesia'],
    ['Heru Joni Putra',                       'heru.putra@sma-ktb.sch.id',           'teacher', 'Indonesia'],
    ['Wening Tri Widati',                     'wening.widati@sma-ktb.sch.id',        'teacher', 'Indonesia · Lang. Coordinator'],
    ['Sriati Sumowidagdo',                    'sriati.sumowidagdo@sma-ktb.sch.id',   'teacher', 'Economics & TOK'],
    ['Agung Prasetyo Wibowo',                 'agung.wibowo@sma-ktb.sch.id',         'teacher', 'Economics'],
    ['Mutiara Dewi',                          'mutiara.dewi@sma-ktb.sch.id',         'teacher', 'History'],
    ['Boyke Rionaldo',                        'boyke.rionaldo@sma-ktb.sch.id',       'teacher', 'Sociology & Global Politics · CAS Coordinator'],
    ['Muhammad Shobaruddin',                  'muhammad.shobaruddin@sma-ktb.sch.id', 'teacher', 'Sociology & Global Politics'],
    ['Abdul Latif',                           'abdul.latif@sma-ktb.sch.id',          'teacher', 'Business & Management'],
    ['Albert Adiputra',                       'albert.yang@sma-ktb.sch.id',          'teacher', 'Biology · TOK Coordinator'],
    ['Ramli Elones',                          'ramli@sma-ktb.sch.id',                'teacher', 'Biology'],
    ['Siti Maisaroh',                         'siti.maisaroh@sma-ktb.sch.id',        'teacher', 'Biology'],
    ['Mohammad Motia Herlambang',             'motia.herlambang@sma-ktb.sch.id',     'teacher', 'Chemistry · Science Coordinator'],
    ['Kusniar Deny Permana',                  'kusniar.d.permana@sma-ktb.sch.id',    'teacher', 'Chemistry'],
    ['Dea Sukrisna',                          'dea.sukrisna@sma-ktb.sch.id',         'teacher', 'Chemistry'],
    ['Sulthan Waliid Anggara Wisesa',         'sulthan.wisesa@sma-ktb.sch.id',       'teacher', 'Chemistry'],
    ['Safira Rachmaniar',                     'safira.rachmaniar@sma-ktb.sch.id',    'teacher', 'Physics'],
    ['Alif Darmawan',                         'alif.darmawan@sma-ktb.sch.id',        'teacher', 'Physics'],
    ['Eka Lamar Syari',                       'eka.syari@sma-ktb.sch.id',            'teacher', 'Physics'],
    ['Rahmat Darani',                         'rahmat.darani@sma-ktb.sch.id',        'teacher', 'Math AA & Extended · Math Coordinator'],
    ['Diah Lestari',                          'diah.lestari@sma-ktb.sch.id',         'teacher', 'Math AI & Standard'],
    ['Tua Darwin Sipayung',                   'darwin.sipayung@sma-ktb.sch.id',      'teacher', 'Math AI, AA & Standard'],
    ['Abdul Hasan Al Asyari',                 'hasan.asyari@sma-ktb.sch.id',         'teacher', 'Math Standard'],
    ['Binsar Tulus H Nainggolan',             'binsar.nainggolan@sma-ktb.sch.id',    'teacher', 'Math AA & Extended'],
    ['Bennartho Denys Rapoho',                'bennartho.rapoho@sma-ktb.sch.id',     'teacher', 'Pancasila Education, CAS, Global Inquiry · Student Council Coordinator'],
    ['Satria Aji',                            'satria.aji@sma-ktb.sch.id',           'teacher', 'Pancasila Education & CAS'],
    ['Ali Safiudin',                          'ali.saifudin@sma-ktb.sch.id',         'teacher', 'Bina Iman Coordinator'],
    ['Nizar Sadat',                           'nizar.sadat@sma-ktb.sch.id',          'teacher', 'Islamic Religious Studies'],
    ['Ibnu Susilo',                           'ibnu.susilo@sma-ktb.sch.id',          'teacher', 'Physical Education Coordinator'],
    ['Dian Marbun',                           'dian.marbun@sma-ktb.sch.id',          'teacher', 'SEL Counselor'],
    ['Salwa Dzahabyyah',                      'salwa.dzahabyyah@sma-ktb.sch.id',     'teacher', 'Career Counselor'],
    ['Yosephine Stefani Martanella Sihombing','yosephine.sihombing@sma-ktb.sch.id',  'teacher', 'SEL/CC Counselor'],
    ['Patresia Gultom',                       'patresia.gultom@sma-ktb.sch.id',      'teacher', 'Teacher Librarian'],

    // ── Tenaga Kependidikan / Operations (14) ───────────────
    ['Toni Yunanto',            'toni.yunanto@sma-ktb.sch.id',       'staff', 'Deputy Head of Operations'],
    ['Herlangga Hizkiawan',     'herlangga.hizkiawan@sma-ktb.sch.id','staff', 'Secretary of HoS'],
    ['Abu Hasan Baihaqi',       'adminktb@sma-ktb.sch.id',           'staff', 'National Admin Staff'],
    ['Maya Vicha Rumengan',     'ibdpadmin@sma-ktb.sch.id',          'staff', 'IBDP Admin Staff'],
    ['Helmi Majid Ar-Rasyid',   'helmi.arrasyid@sma-ktb.sch.id',     'staff', 'Physics Lab Assistant'],
    ['Marni Lidawati Limbong',  'marni.limbong@sma-ktb.sch.id',      'staff', 'Biology Lab Assistant'],
    ['Elda Ramadhani',          'elda.rama@sma-ktb.sch.id',          'staff', 'Chemistry Lab Assistant'],
    ['Partono Warsito',         'partono.warsito@sma-ktb.sch.id',    'staff', 'GA Coordinator'],
    ['Abdul Karim',             'it.coordinator@sma-ktb.sch.id',     'staff', 'IT Coordinator'],
    ['Rio Aprindo',             'it-admin@sma-ktb.sch.id',           'staff', 'IT Support Staff'],
    ['Septyano Hadi Prayoga',   'socmed-prod@sma-ktb.sch.id',        'staff', 'Social Media Production'],
    ['Aulia Azizah',            'socmed-spc@sma-ktb.sch.id',         'staff', 'Social Media Specialist'],
    ['Cahyo Hardian Adi Aksa',  'librarian-admin@sma-ktb.sch.id',    'staff', 'Librarian Staff'],
    ['Danika Lailatul Khafifah','danika.khafifah@sma-ktb.sch.id',    'staff', 'CCA Admin'],

    // ── Kotak surat fungsional (bukan perorangan) ───────────
    // Dipakai sebagai tujuan eskalasi. Dipegang bergantian oleh
    // petugas, karena itu namanya jabatan, bukan nama orang.
    ['Sekretariat Kepala Sekolah', 'hos_secretary@sma-ktb.sch.id', 'staff', 'Kotak surat — surat resmi, izin siswa, penjadwalan, jalur laporan siswa & orang tua'],
    ['Humas Sekolah',              'info@sma-ktb.sch.id',          'staff', 'Kotak surat — komunikasi umum, pertanyaan orang tua, media'],
    ['CCA & Competition',          'cca@sma-ktb.sch.id',           'staff', 'Kotak surat — pendaftaran CCA, kompetisi, koordinasi pembina'],
];

// ── Sambung ─────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbName;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "GAGAL menyambung ke $dbName: " . $e->getMessage() . "\n");
    exit(1);
}

$mode = $dryRun ? 'SIMULASI (tidak menulis apa pun)' : 'EKSEKUSI';
echo str_repeat('=', 68) . "\n";
echo "AGKB 360° — Impor Roster 2026\n";
echo "Database : $dbName\n";
echo "Mode     : $mode\n";
echo str_repeat('=', 68) . "\n\n";

// ── Pastikan kolom token set-password tersedia ──────────────
$kolom = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
$perluKolom = [
    'password_reset_token' => "ALTER TABLE users ADD COLUMN password_reset_token VARCHAR(64) NULL DEFAULT NULL AFTER password, ADD KEY idx_reset_token (password_reset_token)",
    'token_expires_at'     => "ALTER TABLE users ADD COLUMN token_expires_at DATETIME NULL DEFAULT NULL AFTER password_reset_token",
];
foreach ($perluKolom as $nama => $ddl) {
    if (!in_array($nama, $kolom, true)) {
        echo "  + kolom users.$nama belum ada";
        if ($dryRun) {
            echo " — akan ditambahkan\n";
        } else {
            $pdo->exec($ddl);
            echo " — DITAMBAHKAN\n";
        }
    }
}

// ── Proses ──────────────────────────────────────────────────
$cari   = $pdo->prepare("SELECT id, name, email, role, last_login FROM users WHERE email = ?");
$ubahNm = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
$buat   = $pdo->prepare(
    "INSERT INTO users (name, email, password, role, is_active, last_login, created_at)
     VALUES (?, ?, ?, ?, 1, NULL, NOW())"
);

$baru = []; $namaDiperbaiki = []; $samaSaja = []; $bedaRole = [];

foreach ($roster as [$nama, $email, $role, $peran]) {
    $email = strtolower(trim($email));
    $cari->execute([$email]);
    $ada = $cari->fetch(PDO::FETCH_ASSOC);

    if ($ada) {
        if ($ada['role'] !== $role) {
            $bedaRole[] = [$nama, $email, $ada['role'], $role];
        }
        if (trim($ada['name']) !== $nama) {
            $namaDiperbaiki[] = [$ada['name'], $nama, $email, $ada['last_login']];
            if (!$dryRun) $ubahNm->execute([$nama, (int)$ada['id']]);
        } else {
            $samaSaja[] = [$nama, $email, $ada['last_login']];
        }
        continue;
    }

    // Password acak — sengaja tidak diketahui siapa pun.
    // Akses diberikan lewat tautan set-password pada blast email.
    $acak = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
    $baru[] = [$nama, $email, $role, $peran];
    if (!$dryRun) $buat->execute([$nama, $email, $acak, $role]);
}

// ── Laporan ─────────────────────────────────────────────────
$kk = $dryRun ? 'akan dibuat' : 'DIBUAT';
echo "\n── AKUN BARU ($kk): " . count($baru) . " ──\n";
foreach ($baru as [$n, $e, $r, $p]) {
    printf("  %-40s %-38s %-8s %s\n", $n, $e, $r, $p);
}

$kk = $dryRun ? 'akan diperbaiki' : 'DIPERBAIKI';
echo "\n── NAMA $kk: " . count($namaDiperbaiki) . " ──\n";
foreach ($namaDiperbaiki as [$lama, $baruNama, $e, $ll]) {
    printf("  \"%s\" → \"%s\"  (%s)\n", $lama, $baruNama, $e);
}

echo "\n── SUDAH BENAR, TIDAK DISENTUH: " . count($samaSaja) . " ──\n";

if ($bedaRole) {
    echo "\n── ⚠ ROLE BERBEDA (TIDAK diubah otomatis — periksa manual) ──\n";
    foreach ($bedaRole as [$n, $e, $lama, $harusnya]) {
        printf("  %-38s %s : di DB '%s', roster '%s'\n", $n, $e, $lama, $harusnya);
    }
}

// ── Status login seluruh roster ─────────────────────────────
if (!$dryRun) {
    $emails = array_map(fn($r) => strtolower($r[1]), $roster);
    $ph = implode(',', array_fill(0, count($emails), '?'));
    $st = $pdo->prepare(
        "SELECT role,
                COUNT(*) AS total,
                SUM(last_login IS NULL) AS belum_login,
                SUM(last_login IS NOT NULL) AS sudah_login
         FROM users WHERE email IN ($ph) GROUP BY role"
    );
    $st->execute($emails);
    echo "\n── STATUS LOGIN ROSTER ──\n";
    printf("  %-10s %6s %14s %13s\n", 'role', 'total', 'belum login', 'sudah login');
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        printf("  %-10s %6d %14d %13d\n", $r['role'], $r['total'], $r['belum_login'], $r['sudah_login']);
    }

    $tot = $pdo->query("SELECT role, COUNT(*) n FROM users GROUP BY role ORDER BY n DESC")
               ->fetchAll(PDO::FETCH_ASSOC);
    echo "\n── SELURUH DATABASE ──\n";
    foreach ($tot as $r) printf("  %-12s %5d\n", $r['role'], $r['n']);
}

echo "\n" . str_repeat('=', 68) . "\n";
echo $dryRun
    ? "SIMULASI selesai. Tidak ada perubahan. Jalankan tanpa --dry untuk menerapkan.\n"
    : "Selesai. Akun baru belum punya password — beri akses lewat Blast Email.\n";
echo str_repeat('=', 68) . "\n";
