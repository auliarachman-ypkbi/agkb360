-- ============================================================
-- AGKB 360° — Pelaksana Masuk Unit Penanganan
-- Migration 024
-- ------------------------------------------------------------
-- Migrasi 019 merapikan unit penanganan menjadi hanya PIC, tujuan
-- eskalasi, dan Customer Care. Setelah dicocokkan dengan daftar
-- kepegawaian, ternyata itu mencabut sebagian pelaksana yang justru
-- mengerjakan pekerjaannya, dan meninggalkan beberapa unit tanpa
-- orang yang memahami isinya.
--
-- Perubahannya:
--   · Partono Warsito (GA Coordinator) kembali ke Sarana &
--     Prasarana — General Affairs yang mengerjakan kebersihan,
--     perbaikan, keamanan, dan parkir.
--   · Septyano dan Aulia Azizah (Social Media) masuk Humas &
--     Komunikasi — kategori itu mencakup kanal resmi dan publikasi.
--   · Danika (CCA Admin) masuk unit CCA, mendampingi kotak bersama
--     cca@sma-ktb.sch.id yang tetap jadi PIC.
--   · Maya Vicha Rumengan (IBDP Admin) masuk Kurikulum & IB DP.
--
-- Laboran Fisika, Biologi, dan Kimia sengaja TIDAK dikembalikan:
-- urusan laboratorium memang masuk Sarana & Fasilitas, tetapi
-- mereka akan ikut menerima setiap laporan dormitory, laundry, dan
-- parkir yang tidak ada kaitannya dengan pekerjaan mereka.
--
-- Akun yang belum ada dibuatkan tanpa kata sandi yang bisa dipakai.
-- Pemiliknya menetapkan sendiri lewat kampanye Aktivasi Pengelola.
--
-- Jalankan di ktb_production DAN ktb_evaluation.
-- Aman dijalankan berulang.
-- ============================================================

SET NAMES utf8mb4;

-- ── 1. Akun yang belum ada ──────────────────────────────────

INSERT INTO `users` (`name`,`email`,`password`,`role`,`is_active`)
SELECT * FROM (
  SELECT 'Septyano Hadi Prayoga'    AS n,'socmed-prod@sma-ktb.sch.id'      AS e,SHA2(RAND(),256) AS p,'staff' AS r,1 AS a UNION ALL
  SELECT 'Aulia Azizah',                 'socmed-spc@sma-ktb.sch.id',           SHA2(RAND(),256),'staff',1 UNION ALL
  SELECT 'Danika Lailatul Khafifah',     'danika.khafifah@sma-ktb.sch.id',      SHA2(RAND(),256),'staff',1 UNION ALL
  SELECT 'Maya Vicha Rumengan',          'ibdpadmin@sma-ktb.sch.id',            SHA2(RAND(),256),'staff',1 UNION ALL
  SELECT 'Partono Warsito',              'partono.warsito@sma-ktb.sch.id',      SHA2(RAND(),256),'staff',1
) AS baru
WHERE NOT EXISTS (SELECT 1 FROM `users` u WHERE u.email = baru.e);

-- ── 2. Keanggotaan unit ─────────────────────────────────────

INSERT IGNORE INTO `user_groups` (`user_id`,`group_id`)
SELECT u.id, g.id
FROM `users` u
JOIN `groups` g ON g.type = 'penanganan' AND g.name = CASE u.email
    WHEN 'partono.warsito@sma-ktb.sch.id' THEN 'Sarana & Prasarana'
    WHEN 'socmed-prod@sma-ktb.sch.id'     THEN 'Humas & Komunikasi'
    WHEN 'socmed-spc@sma-ktb.sch.id'      THEN 'Humas & Komunikasi'
    WHEN 'danika.khafifah@sma-ktb.sch.id' THEN 'CCA & Competition'
    WHEN 'ibdpadmin@sma-ktb.sch.id'       THEN 'Kurikulum & IB DP'
    ELSE NULL
  END
WHERE u.is_active = 1;

-- ── Hasil ───────────────────────────────────────────────────

SELECT g.name AS unit,
       GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ' · ') AS anggota
FROM `groups` g
LEFT JOIN `user_groups` ug ON ug.group_id = g.id
LEFT JOIN `users`       u  ON u.id = ug.user_id AND u.is_active = 1
WHERE g.type = 'penanganan'
GROUP BY g.id, g.name, g.order_num
ORDER BY g.order_num;
