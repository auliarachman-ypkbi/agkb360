-- ============================================================
-- AGKB 360° — Penerima Kedua Jalur Perlindungan Anak
-- Migration 027
-- ------------------------------------------------------------
-- Kelima kategori safeguarding selama ini hanya menuju satu orang,
-- Qaedi Aqsa. Laporan dengan batas waktu 24 jam yang bertumpu pada
-- satu orang berarti tidak ada yang menangani saat beliau
-- berhalangan — dan tidak ada yang mengetahuinya.
--
-- Reybi ditambahkan sebagai penerima kedua, berperan pemantau:
-- ikut melihat, menerima notifikasi, membalas, dan menyelesaikan.
-- Tanggung jawab utama tidak berpindah.
--
-- ── Catatan penting soal peran ──────────────────────────────
-- Keanggotaan unit TIDAK cukup untuk membuka tiket safeguarding.
-- fbCanView() memeriksa ulang lewat fbCanSeeSafeguarding(), yang
-- hanya meloloskan peran 'superadmin' dan 'foundation'.
--
-- Karena itu Reybi diberi peran 'foundation'. Perlu disadari,
-- peran itu TIDAK terbatas pada jalur perlindungan anak: pemegang
-- peran foundation dapat melihat seluruh tiket di semua kategori,
-- serta laporan tingkat yayasan pada modul evaluasi 360°.
--
-- Tidak ada cara memberi akses safeguarding saja tanpa sisanya,
-- kecuali mengubah model izin di kode.
--
-- Jalankan di ktb_production DAN ktb_evaluation.
-- Aman dijalankan berulang.
-- ============================================================

SET NAMES utf8mb4;

-- ── 1. Akun ─────────────────────────────────────────────────

INSERT INTO `users` (`name`,`email`,`password`,`role`,`is_active`)
SELECT * FROM (
  SELECT 'Reybi' AS n,'reybi@kaderbangsa.foundation' AS e,SHA2(RAND(),256) AS p,'foundation' AS r,1 AS a
) AS baru
WHERE NOT EXISTS (SELECT 1 FROM `users` u WHERE u.email = baru.e);

-- Kalau akunnya sudah ada dengan peran lain, naikkan ke foundation.
-- Tanpa ini keanggotaan unit di bawah tidak ada gunanya — tiketnya
-- tetap tertutup baginya.
UPDATE `users`
   SET `role` = 'foundation'
 WHERE `email` = 'reybi@kaderbangsa.foundation'
   AND `role` <> 'foundation';

-- ── 2. Masuk unit Perlindungan Anak ─────────────────────────

INSERT IGNORE INTO `user_groups` (`user_id`,`group_id`)
SELECT u.id, g.id
FROM `users` u
CROSS JOIN `groups` g
WHERE u.email = 'reybi@kaderbangsa.foundation'
  AND u.is_active = 1
  AND g.type = 'penanganan'
  AND g.name = 'Perlindungan Anak (Yayasan)';

-- ── Hasil ───────────────────────────────────────────────────

SELECT u.name AS penerima,
       u.email,
       u.role AS peran,
       CASE WHEN CHAR_LENGTH(u.password) = 64
            THEN 'BELUM dibuat' ELSE 'Sudah dibuat' END AS kata_sandi,
       CASE WHEN u.role IN ('superadmin','foundation')
            THEN 'Ya' ELSE 'TIDAK — tiket tetap tertutup' END AS bisa_baca_safeguarding
FROM `user_groups` ug
JOIN `groups` g ON g.id = ug.group_id AND g.name = 'Perlindungan Anak (Yayasan)'
JOIN `users`  u ON u.id = ug.user_id AND u.is_active = 1
ORDER BY u.name;
