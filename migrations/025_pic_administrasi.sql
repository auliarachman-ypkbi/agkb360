-- ============================================================
-- AGKB 360° — PIC Administrasi & Keuangan, dan Sekretariat HoS
-- Migration 025
-- ------------------------------------------------------------
-- Migrasi 018 menjadikan admin@kaderbangsa.foundation sebagai PIC
-- Administrasi & Keuangan, berdasarkan tabel yang menyebutnya
-- "Admin KTB". Daftar kepegawaian kemudian menunjukkan alamat itu
-- milik Admin YPKBI, sementara Admin KTB yang sebenarnya adalah
-- Abu Hasan Baihaqi di adminktb@sma-ktb.sch.id — orang yang
-- mengurus surat keterangan, legalisasi, ID Card, dan perubahan
-- data, yaitu isi kategori tersebut.
--
-- Perubahannya:
--   · PIC Administrasi & Keuangan pindah ke Abu Hasan Baihaqi
--   · Admin YPKBI tetap di unit sebagai pemantau, tidak dicabut
--   · Herlangga Hizkiawan (Secretary of HoS) kembali ke unit Tata
--     Usaha dan Humas — tercabut migrasi 019, padahal kotak
--     hos_secretary@ adalah kotaknya sendiri
--
-- Rute eskalasi level 1 ikut diperbarui agar sejalan dengan PIC.
--
-- Jalankan di ktb_production DAN ktb_evaluation.
-- Aman dijalankan berulang.
-- ============================================================

SET NAMES utf8mb4;

-- ── 1. Pastikan kedua akun ada ──────────────────────────────

INSERT INTO `users` (`name`,`email`,`password`,`role`,`is_active`)
SELECT * FROM (
  SELECT 'Abu Hasan Baihaqi'    AS n,'adminktb@sma-ktb.sch.id'            AS e,SHA2(RAND(),256) AS p,'staff' AS r,1 AS a UNION ALL
  SELECT 'Herlangga Hizkiawan',     'herlangga.hizkiawan@sma-ktb.sch.id',      SHA2(RAND(),256),'staff',1
) AS baru
WHERE NOT EXISTS (SELECT 1 FROM `users` u WHERE u.email = baru.e);

-- ── 2. PIC Administrasi & Keuangan ──────────────────────────

UPDATE `feedback_categories` c
JOIN `users` u ON u.email = 'adminktb@sma-ktb.sch.id' AND u.is_active = 1
SET c.default_pic_id = u.id
WHERE c.code = 'inq_admin';

-- Rute level 1 mengikuti PIC. Baris lama dibuang lebih dulu agar
-- tidak ada dua penanggung jawab pada level yang sama.
DELETE el FROM `feedback_escalation_levels` el
JOIN `feedback_categories` c ON c.id = el.category_id
WHERE c.code = 'inq_admin' AND el.level = 1;

INSERT INTO `feedback_escalation_levels`
  (`level`,`label`,`track`,`category_id`,`user_id`,`order_num`,`is_active`)
SELECT 1, CONCAT('PIC — ', c.name), 'inquiry', c.id, u.id, 10, 1
FROM `feedback_categories` c
JOIN `users` u ON u.id = c.default_pic_id
WHERE c.code = 'inq_admin';

-- ── 3. Keanggotaan unit ─────────────────────────────────────
-- Abu Hasan Baihaqi ke Tata Usaha. Admin YPKBI dibiarkan di sana
-- sebagai pemantau — tidak dicabut.

INSERT IGNORE INTO `user_groups` (`user_id`,`group_id`)
SELECT u.id, g.id
FROM `users` u
CROSS JOIN `groups` g
WHERE u.email = 'adminktb@sma-ktb.sch.id'
  AND u.is_active = 1
  AND g.type = 'penanganan'
  AND g.name = 'Tata Usaha & Administrasi';

-- Herlangga ke dua unit yang memang dipegangnya lewat kotak
-- hos_secretary@ — Komunikasi & Info dan Lain-lain.

INSERT IGNORE INTO `user_groups` (`user_id`,`group_id`)
SELECT u.id, g.id
FROM `users` u
CROSS JOIN `groups` g
WHERE u.email = 'herlangga.hizkiawan@sma-ktb.sch.id'
  AND u.is_active = 1
  AND g.type = 'penanganan'
  AND g.name IN ('Humas & Komunikasi', 'Tata Usaha & Administrasi');

-- ── Hasil ───────────────────────────────────────────────────

SELECT c.order_num AS urut, c.name AS kategori,
       pic.name AS pic, pic.email AS email_pic
FROM `feedback_categories` c
LEFT JOIN `users` pic ON pic.id = c.default_pic_id
WHERE c.track = 'inquiry' AND c.is_active = 1
ORDER BY c.order_num;

SELECT g.name AS unit,
       GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ' · ') AS anggota
FROM `groups` g
LEFT JOIN `user_groups` ug ON ug.group_id = g.id
LEFT JOIN `users`       u  ON u.id = ug.user_id AND u.is_active = 1
WHERE g.type = 'penanganan'
  AND g.name IN ('Tata Usaha & Administrasi', 'Humas & Komunikasi')
GROUP BY g.id, g.name, g.order_num
ORDER BY g.order_num;
