-- ============================================================
-- AGKB 360° — Kategori & PIC Customer Care
-- Migration 018
-- ------------------------------------------------------------
-- Menetapkan 8 kategori kendala beserta penanggung jawabnya,
-- sehingga tiket langsung mendarat ke orang yang tepat tanpa
-- perlu triase manual.
--
-- Pola tiap kategori:
--   Level 1 — PIC, langsung jadi penanggung jawab tiket
--   Level 2 — tujuan eskalasi kalau SLA terlampaui
--   Tasya   — anggota semua unit, jadi bisa memantau, membalas,
--             mengeskalasi, dan menyelesaikan di kategori mana pun
--
-- Akun dibuat tanpa kata sandi yang bisa dipakai (hash acak).
-- Pemiliknya menetapkan sendiri lewat kampanye Aktivasi Akun.
--
-- Jalankan di ktb_production DAN ktb_evaluation.
-- Aman dijalankan berulang.
-- ============================================================

SET NAMES utf8mb4;

-- ── 1. Akun PIC ─────────────────────────────────────────────
-- Peran sengaja TIDAK memakai 'foundation' untuk siapa pun di
-- sini: peran itu membuka jalur safeguarding, dan penerimanya
-- belum ditetapkan. Nanti tinggal dinaikkan lewat Admin CMS.

INSERT INTO `users` (`name`,`email`,`password`,`role`,`is_active`)
SELECT * FROM (
  SELECT 'Lea Setyaningrum'    AS n,'lea.setyaningrum@sma-ktb.sch.id'          AS e,SHA2(RAND(),256) AS p,'leader' AS r,1 AS a UNION ALL
  SELECT 'Dewi Amri',               'dewi.amri@kaderbangsa.foundation',             SHA2(RAND(),256),'leader',1 UNION ALL
  SELECT 'Arif Sugiarto',           'arif.sugiarto@sma-ktb.sch.id',                 SHA2(RAND(),256),'leader',1 UNION ALL
  SELECT 'Cita Sari',               'cita.sari@sma-ktb.sch.id',                     SHA2(RAND(),256),'mentor',1 UNION ALL
  SELECT 'Danica',                  'cca@sma-ktb.sch.id',                           SHA2(RAND(),256),'staff', 1 UNION ALL
  SELECT 'Ibnu Susilo',             'ibnu.susilo@sma-ktb.sch.id',                   SHA2(RAND(),256),'staff', 1 UNION ALL
  SELECT 'Toni Yunanto',            'toni.yunanto@sma-ktb.sch.id',                  SHA2(RAND(),256),'leader',1 UNION ALL
  SELECT 'Hendri Adi',              'hendri.adi@sma-ktb.sch.id',                    SHA2(RAND(),256),'leader',1 UNION ALL
  SELECT 'Angga',                   'hos_secretary@sma-ktb.sch.id',                 SHA2(RAND(),256),'staff', 1 UNION ALL
  SELECT 'Abu (Yayasan)',           'admin@kaderbangsa.foundation',                 SHA2(RAND(),256),'staff', 1 UNION ALL
  SELECT 'Abu (Sekolah)',           'info@sma-ktb.sch.id',                          SHA2(RAND(),256),'staff', 1 UNION ALL
  SELECT 'Dyah Ayu Saraswati',      'dyahayu.saraswati@kaderbangsa.foundation',     SHA2(RAND(),256),'staff', 1 UNION ALL
  SELECT 'Tasya',                   'tasya.intern@kaderbangsa.foundation',          SHA2(RAND(),256),'staff', 1
) AS baru
WHERE NOT EXISTS (SELECT 1 FROM `users` u WHERE u.email = baru.e);

-- ── 2. Unit CCA & Competition ───────────────────────────────
INSERT INTO `groups` (`name`,`type`,`is_fixed`,`respondent_type`,`order_num`,`description`)
SELECT * FROM (
  SELECT 'CCA & Competition' AS n,'penanganan' AS t,0 AS f,NULL AS r,935 AS o,
         'Kegiatan ko-kurikuler, kompetisi, Maker Space & Robotic' AS d
) AS baru
WHERE NOT EXISTS (
  SELECT 1 FROM `groups` g WHERE g.name = baru.n AND g.type = 'penanganan');

-- ── 3. Kategori ─────────────────────────────────────────────
-- Tujuh kategori sudah ada dari migrasi 009; di sini namanya
-- diselaraskan dan cakupannya dipertegas.

INSERT INTO `feedback_categories`
 (`code`,`name`,`description`,`track`,`default_priority`,`sla_response_hours`,`sla_resolve_hours`,
  `start_level`,`is_sensitive`,`allow_anonymous`,`require_attachment`,`order_num`)
SELECT * FROM (
  SELECT 'inq_cca' AS c,'CCA & Competition' AS n,
         'Info CCA & kompetisi, biaya terkait, Maker Space & Robotic' AS d,
         'inquiry' AS t,'P3' AS p,48 AS s1,120 AS s2,1 AS l,0 AS i,0 AS an,0 AS ra,125 AS o
) AS baru
WHERE NOT EXISTS (SELECT 1 FROM `feedback_categories` fc WHERE fc.code = baru.c);

UPDATE `feedback_categories` SET
  name = 'Akademik',
  description = 'Pengajaran, kurikulum, beban tugas, nilai & rapor, ujian, elemen IB, bimbingan peminatan',
  order_num = 110
WHERE code = 'inq_akademik';

UPDATE `feedback_categories` SET
  name = 'Kesiswaan & Kedisiplinan',
  description = 'Perundungan, tata tertib, konflik antar siswa, kehadiran, keselamatan & kesehatan, perilaku staf, konseling',
  order_num = 120
WHERE code = 'inq_kesiswaan';

UPDATE `feedback_categories` SET
  name = 'Sarana & Fasilitas',
  description = 'Kebersihan, kerusakan fasilitas, keamanan lingkungan, dormitory, makanan, laundry, perpustakaan & lab, parkir',
  order_num = 130
WHERE code = 'inq_sarana';

UPDATE `feedback_categories` SET
  name = 'Komunikasi & Info',
  description = 'Informasi terlambat atau tidak sampai, kesalahan info di kanal resmi, respons lambat, masukan publikasi, permintaan informasi',
  order_num = 140
WHERE code = 'inq_komunikasi';

UPDATE `feedback_categories` SET
  name = 'Administrasi & Keuangan',
  description = 'SPP & tagihan, pendaftaran/PPDB, dokumen siswa, beasiswa, refund, portal administrasi orang tua',
  order_num = 150
WHERE code = 'inq_admin';

UPDATE `feedback_categories` SET
  name = 'Kepegawaian',
  description = 'Kontrak, cuti, gaji, BPJS',
  order_num = 160
WHERE code = 'inq_sdm';

UPDATE `feedback_categories` SET
  name = 'Lain-lain',
  description = 'Saran umum di luar kategori lain, kemitraan eksternal, isu yang belum jelas kategorinya, keluhan atas layanan customer care',
  order_num = 170
WHERE code = 'inq_lain';

-- Teknologi & Sistem AGKB tidak ada dalam daftar 8 kategori.
-- Dinonaktifkan, bukan dihapus: tiket lama tetap punya induk, dan
-- mengaktifkannya kembali cukup satu klik di Admin CMS.
UPDATE `feedback_categories` SET is_active = 0 WHERE code = 'inq_teknologi';

-- ── 4. Kategori CCA ke unitnya ──────────────────────────────
UPDATE `feedback_categories` c
JOIN `groups` g ON g.type='penanganan' AND g.name='CCA & Competition'
SET c.handler_group_id = g.id
WHERE c.code = 'inq_cca';

-- ── 5. PIC tiap kategori ────────────────────────────────────
UPDATE `feedback_categories` c
JOIN `users` u ON u.email = CASE c.code
    WHEN 'inq_akademik'   THEN 'lea.setyaningrum@sma-ktb.sch.id'
    WHEN 'inq_kesiswaan'  THEN 'arif.sugiarto@sma-ktb.sch.id'
    WHEN 'inq_cca'        THEN 'cca@sma-ktb.sch.id'
    WHEN 'inq_sarana'     THEN 'toni.yunanto@sma-ktb.sch.id'
    WHEN 'inq_komunikasi' THEN 'hos_secretary@sma-ktb.sch.id'
    WHEN 'inq_admin'      THEN 'admin@kaderbangsa.foundation'
    WHEN 'inq_sdm'        THEN 'dyahayu.saraswati@kaderbangsa.foundation'
    WHEN 'inq_lain'       THEN 'info@sma-ktb.sch.id'
    ELSE NULL END
SET c.default_pic_id = u.id;

-- ── 6. Rute level 1 & 2 ─────────────────────────────────────
-- Level 1 = PIC yang langsung memegang tiket.
-- Level 2 = tujuan eskalasi saat SLA terlampaui.

DELETE el FROM `feedback_escalation_levels` el
JOIN `feedback_categories` c ON c.id = el.category_id
WHERE c.code IN ('inq_akademik','inq_kesiswaan','inq_cca','inq_sarana',
                 'inq_komunikasi','inq_admin','inq_sdm','inq_lain');

INSERT INTO `feedback_escalation_levels` (`level`,`label`,`track`,`category_id`,`user_id`,`order_num`,`is_active`)
SELECT 1, CONCAT('PIC — ', c.name), 'inquiry', c.id, u.id, 10, 1
FROM `feedback_categories` c
JOIN `users` u ON u.id = c.default_pic_id
WHERE c.code IN ('inq_akademik','inq_kesiswaan','inq_cca','inq_sarana',
                 'inq_komunikasi','inq_admin','inq_sdm','inq_lain');

INSERT INTO `feedback_escalation_levels` (`level`,`label`,`track`,`category_id`,`user_id`,`order_num`,`is_active`)
SELECT 2, CONCAT('Eskalasi — ', c.name), 'inquiry', c.id, u.id, 10, 1
FROM `feedback_categories` c
JOIN `users` u ON u.email = CASE c.code
    WHEN 'inq_akademik'   THEN 'dewi.amri@kaderbangsa.foundation'
    WHEN 'inq_kesiswaan'  THEN 'cita.sari@sma-ktb.sch.id'
    WHEN 'inq_cca'        THEN 'ibnu.susilo@sma-ktb.sch.id'
    WHEN 'inq_sarana'     THEN 'hendri.adi@sma-ktb.sch.id'
    WHEN 'inq_komunikasi' THEN 'toni.yunanto@sma-ktb.sch.id'
    WHEN 'inq_admin'      THEN 'toni.yunanto@sma-ktb.sch.id'
    WHEN 'inq_sdm'        THEN 'dewi.amri@kaderbangsa.foundation'
    WHEN 'inq_lain'       THEN 'hos_secretary@sma-ktb.sch.id'
    ELSE NULL END
WHERE c.code IN ('inq_akademik','inq_kesiswaan','inq_cca','inq_sarana',
                 'inq_komunikasi','inq_admin','inq_sdm','inq_lain');

-- ── 7. Keanggotaan unit ─────────────────────────────────────
-- PIC dan tujuan eskalasi masuk unit kategorinya, supaya keduanya
-- bisa melihat dan mengerjakan antrean yang sama.

INSERT IGNORE INTO `user_groups` (`user_id`,`group_id`)
SELECT DISTINCT el.user_id, c.handler_group_id
FROM `feedback_escalation_levels` el
JOIN `feedback_categories` c ON c.id = el.category_id
WHERE el.user_id IS NOT NULL AND c.handler_group_id IS NOT NULL;

-- Tasya masuk SEMUA unit penanganan kecuali Perlindungan Anak.
-- Jalur safeguarding sengaja dibiarkan seperti sekarang sampai
-- penerimanya ditetapkan.
INSERT IGNORE INTO `user_groups` (`user_id`,`group_id`)
SELECT u.id, g.id
FROM `users` u
CROSS JOIN `groups` g
WHERE u.email = 'tasya.intern@kaderbangsa.foundation'
  AND g.type = 'penanganan'
  AND g.name <> 'Perlindungan Anak (Yayasan)';

-- ── 8. Hasil ────────────────────────────────────────────────
SELECT c.order_num AS urut, c.name AS kategori,
       pic.name AS pic, pic.email AS email_pic,
       esk.name AS eskalasi, esk.email AS email_eskalasi,
       g.name AS unit
FROM `feedback_categories` c
LEFT JOIN `users`  pic ON pic.id = c.default_pic_id
LEFT JOIN `groups` g   ON g.id  = c.handler_group_id
LEFT JOIN (SELECT el.category_id, u.name, u.email
             FROM `feedback_escalation_levels` el
             JOIN `users` u ON u.id = el.user_id
            WHERE el.level = 2) esk ON esk.category_id = c.id
WHERE c.track = 'inquiry' AND c.is_active = 1
ORDER BY c.order_num;
