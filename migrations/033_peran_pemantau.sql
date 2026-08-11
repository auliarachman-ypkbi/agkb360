-- ============================================================
-- AGKB 360° — Peran Pemantau
-- Migration 033
-- ------------------------------------------------------------
-- Menambah peran yang hanya dapat MELIHAT seluruh tiket di semua
-- jalur — apresiasi, kendala, dan Kanal Yayasan — tanpa dapat
-- membalas, menyelesaikan, mengeskalasi, atau membuka Admin CMS.
--
-- Sebelumnya tidak ada peran seperti itu. Untuk melihat semua
-- tiket, seseorang harus diberi peran admin atau foundation, yang
-- sekaligus memberinya kuasa mengubah dan membuka seluruh menu
-- pengelolaan. Untuk pengawasan, itu jauh lebih luas dari perlunya.
--
-- Batasnya dijaga di kode, bukan di sini:
--   fbCanView()          → pemantau melihat semua tiket
--   fbAllowedTracks()    → ketiga jalur terbuka
--   fbCanSeeSafeguarding() → termasuk Kanal Yayasan
--   fbCanManage()        → TETAP false, sehingga tidak dapat bertindak
--
-- Akun dibuat sekalian di sini atas permintaan pemiliknya.
--
-- Jalankan di ktb_production DAN ktb_evaluation.
-- Aman dijalankan berulang.
-- ============================================================

SET NAMES utf8mb4;

-- ── 1. Perluas daftar peran ─────────────────────────────────

SET @sudah := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'users'
                  AND COLUMN_NAME  = 'role'
                  AND COLUMN_TYPE LIKE '%pemantau%');

SET @sql := IF(@sudah = 0,
  'ALTER TABLE `users`
     MODIFY COLUMN `role`
     ENUM(''superadmin'',''admin'',''foundation'',''leader'',''teacher'',''student'',
          ''parent'',''tester'',''staff'',''mentor'',''pemantau'')
     COLLATE utf8mb4_unicode_ci NOT NULL',
  'SELECT ''peran pemantau sudah ada'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 2. Akun pemantau ────────────────────────────────────────
-- Kata sandi TIDAK disetel di sini. Bcrypt tidak dapat dihasilkan
-- oleh MySQL, dan menuliskan hash secara harfiah di berkas yang
-- masuk riwayat git bukan kebiasaan yang pantas dipelihara.
--
-- Akun dibuat dengan isian acak yang tidak dapat dipakai masuk.
-- Kata sandinya disetel setelah migrasi ini, lewat:
--   tools/setel-kata-sandi.php --email=... --sandi=...

INSERT INTO `users` (`name`,`email`,`password`,`role`,`is_active`)
SELECT * FROM (
  SELECT 'Reybi (Pemantau)' AS n,
         'reybiwrn@gmail.com' AS e,
         SHA2(RAND(),256) AS p,
         'pemantau' AS r,
         1 AS a
) AS baru
WHERE NOT EXISTS (SELECT 1 FROM `users` u WHERE u.email = baru.e);

-- Bila akunnya sudah ada dengan peran lain, naikkan perannya saja.
UPDATE `users` SET `role` = 'pemantau'
 WHERE `email` = 'reybiwrn@gmail.com' AND `role` <> 'pemantau';

-- ── Hasil ───────────────────────────────────────────────────

SELECT name AS nama, email, role AS peran,
       CASE WHEN CHAR_LENGTH(password) = 64
            THEN 'BELUM dibuat' ELSE 'Sudah dibuat' END AS kata_sandi
FROM `users`
WHERE role = 'pemantau';
