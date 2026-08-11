<?php
/**
 * ============================================================
 * AGKB 360° — Setel Kata Sandi Pengguna
 * ------------------------------------------------------------
 * Menetapkan kata sandi satu akun langsung dari baris perintah.
 * Dipakai untuk akun layanan dan akun yang dibuat lewat migrasi,
 * yang tidak melalui alur tautan aktivasi.
 *
 * Untuk pengguna biasa, jalur yang benar tetap tautan aktivasi:
 * di sana pemiliknya menentukan sendiri kata sandinya, dan tidak
 * ada orang lain yang pernah mengetahuinya.
 *
 * HANYA lewat baris perintah. Berkas ini berada di dalam webroot,
 * jadi penjagaan CLI di bawah bukan basa-basi.
 *
 * ⚠ Kata sandi yang diketik di terminal tersimpan di riwayat
 *   perintah. Bersihkan setelahnya bila perlu.
 *
 * Pakai:
 *   php tools/setel-kata-sandi.php --email=orang@contoh.com --sandi='RahasiaKuat123!'
 * ============================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Hanya dapat dijalankan lewat baris perintah.');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$opts  = getopt('', ['email:', 'sandi:']);
$email = trim($opts['email'] ?? '');
$sandi = (string)($opts['sandi'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Wajib: --email=alamat yang sah\n");
    exit(1);
}
if (strlen($sandi) < 8) {
    fwrite(STDERR, "Kata sandi minimal 8 karakter.\n");
    exit(1);
}

$u = Database::fetchOne(
    "SELECT id, name, role, is_active FROM users WHERE email = ?", [$email]);

if (!$u) {
    fwrite(STDERR, "Akun tidak ditemukan: $email\n");
    exit(1);
}

Database::query(
    "UPDATE users
        SET password = ?, password_reset_token = NULL, token_expires_at = NULL
      WHERE id = ?",
    [password_hash($sandi, PASSWORD_DEFAULT), $u['id']]);

echo "Kata sandi disetel.\n";
echo '  Nama   : ' . $u['name'] . "\n";
echo '  Email  : ' . $email . "\n";
echo '  Peran  : ' . $u['role'] . "\n";
echo '  Aktif  : ' . ($u['is_active'] ? 'ya' : 'TIDAK — akun nonaktif, belum bisa masuk') . "\n";
echo "\nTautan pemulihan yang masih berlaku ikut dibatalkan.\n";
