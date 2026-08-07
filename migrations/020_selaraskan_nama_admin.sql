-- ============================================================
-- AGKB 360° — Selaraskan Nama Admin YPKBI
-- Migration 020
-- ------------------------------------------------------------
-- Migrasi 018 membuat akun admin@kaderbangsa.foundation dengan
-- nama "Abu (Yayasan)", hasil penafsiran keliru atas tabel PIC:
-- di sana kolomnya tertulis "Admin KTB", padahal alamat itu
-- adalah Admin YPKBI di yayasan, bukan admin sekolah.
--
-- Namanya sudah dibetulkan manual di produksi. Migrasi ini
-- menyamakan lokal dengan produksi, sekaligus mencatat koreksi
-- itu di riwayat — supaya pemasangan baru tidak mengulang
-- kesalahan yang sama.
--
-- Hanya menyentuh kolom nama. Peran, keanggotaan unit, rute
-- kategori, dan kata sandi tidak diubah.
--
-- Jalankan di ktb_production DAN ktb_evaluation.
-- Aman dijalankan berulang.
-- ============================================================

SET NAMES utf8mb4;

UPDATE `users`
   SET `name` = 'Admin YPKBI'
 WHERE `email` = 'admin@kaderbangsa.foundation'
   AND `name` <> 'Admin YPKBI';

-- ── Hasil ───────────────────────────────────────────────────

SELECT u.name          AS nama,
       u.email,
       u.role          AS peran,
       CASE WHEN CHAR_LENGTH(u.password) = 64
            THEN 'BELUM dibuat' ELSE 'Sudah dibuat' END AS kata_sandi,
       GROUP_CONCAT(DISTINCT c.name ORDER BY c.order_num SEPARATOR ', ') AS pic_untuk
FROM `users` u
LEFT JOIN `feedback_categories` c ON c.default_pic_id = u.id
WHERE u.email = 'admin@kaderbangsa.foundation'
GROUP BY u.id, u.name, u.email, u.role, u.password;
