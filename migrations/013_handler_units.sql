-- ============================================================
-- AGKB 360° — Unit Penanganan Feedback
-- Migration 013
-- ------------------------------------------------------------
-- Memisahkan dua struktur yang selama ini tercampur:
--   1. Struktur evaluasi 360  — siapa menilai siapa
--   2. Struktur penanganan    — siapa bertanggung jawab atas apa
--
-- Unit penanganan memakai tabel `groups` yang sudah ada dengan
-- type = 'penanganan' dan respondent_type = NULL, sehingga TIDAK
-- akan pernah muncul di matriks evaluasi.
--
-- Jalankan di ktb_production DAN ktb_evaluation.
-- Aman dijalankan berulang.
-- ============================================================

SET NAMES utf8mb4;

-- ── 1. Kolom unit penanganan pada kategori ──────────────────
SET @adaKolom := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'feedback_categories'
    AND COLUMN_NAME = 'handler_group_id'
);
SET @sql := IF(@adaKolom = 0,
  'ALTER TABLE `feedback_categories`
     ADD COLUMN `handler_group_id` INT NULL COMMENT ''Unit penanganan (groups.type=penanganan)'' AFTER `default_pic_id`,
     ADD KEY `idx_handler_group` (`handler_group_id`)',
  'SELECT ''kolom handler_group_id sudah ada'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 2. Unit penanganan ──────────────────────────────────────
-- respondent_type sengaja NULL: unit ini bukan kelompok penilai.
INSERT INTO `groups` (`name`,`type`,`is_fixed`,`respondent_type`,`order_num`,`description`)
SELECT * FROM (
  SELECT 'Tata Usaha & Administrasi' AS n,'penanganan' AS t,0 AS f,NULL AS r,910 AS o,'Surat, dokumen, pembayaran, jadwal' AS d UNION ALL
  SELECT 'Sarana & Prasarana','penanganan',0,NULL,920,'Gedung, ruang kelas, alat, kebersihan' UNION ALL
  SELECT 'Teknologi Informasi','penanganan',0,NULL,930,'Akun, sistem AGKB 360, perangkat, jaringan' UNION ALL
  SELECT 'Humas & Komunikasi','penanganan',0,NULL,940,'Penyampaian informasi, kanal komunikasi, apresiasi' UNION ALL
  SELECT 'Kesiswaan','penanganan',0,NULL,950,'Kegiatan siswa, tata tertib, OSIS' UNION ALL
  SELECT 'Kurikulum & IB DP','penanganan',0,NULL,960,'Kurikulum, pengajaran, penilaian, program IB' UNION ALL
  SELECT 'Kepegawaian & SDM','penanganan',0,NULL,970,'Ketenagakerjaan, beban kerja, pengembangan staf' UNION ALL
  SELECT 'Perlindungan Anak (Yayasan)','penanganan',1,NULL,980,'Penanganan laporan perlindungan anak — akses terbatas'
) AS baru
WHERE NOT EXISTS (
  SELECT 1 FROM `groups` g WHERE g.name = baru.n AND g.type = 'penanganan'
);

-- ── 3. Petakan kategori ke unit ─────────────────────────────
UPDATE `feedback_categories` c
JOIN `groups` g ON g.type = 'penanganan' AND g.name = CASE c.code
    WHEN 'apr_guru'        THEN 'Humas & Komunikasi'
    WHEN 'apr_program'     THEN 'Humas & Komunikasi'
    WHEN 'apr_umum'        THEN 'Humas & Komunikasi'
    WHEN 'inq_akademik'    THEN 'Kurikulum & IB DP'
    WHEN 'inq_kesiswaan'   THEN 'Kesiswaan'
    WHEN 'inq_sarana'      THEN 'Sarana & Prasarana'
    WHEN 'inq_teknologi'   THEN 'Teknologi Informasi'
    WHEN 'inq_admin'       THEN 'Tata Usaha & Administrasi'
    WHEN 'inq_komunikasi'  THEN 'Humas & Komunikasi'
    WHEN 'inq_sdm'         THEN 'Kepegawaian & SDM'
    WHEN 'inq_lain'        THEN 'Tata Usaha & Administrasi'
    WHEN 'sg_bullying'     THEN 'Perlindungan Anak (Yayasan)'
    WHEN 'sg_kekerasan'    THEN 'Perlindungan Anak (Yayasan)'
    WHEN 'sg_perilaku'     THEN 'Perlindungan Anak (Yayasan)'
    WHEN 'sg_keselamatan'  THEN 'Perlindungan Anak (Yayasan)'
    WHEN 'sg_diskriminasi' THEN 'Perlindungan Anak (Yayasan)'
    ELSE NULL
  END
SET c.handler_group_id = g.id
WHERE c.handler_group_id IS NULL;

-- ── 4. Hasil ────────────────────────────────────────────────
SELECT g.name AS unit_penanganan,
       COUNT(c.id) AS jumlah_kategori,
       (SELECT COUNT(*) FROM user_groups ug WHERE ug.group_id = g.id) AS jumlah_anggota
FROM `groups` g
LEFT JOIN `feedback_categories` c ON c.handler_group_id = g.id
WHERE g.type = 'penanganan'
GROUP BY g.id, g.name
ORDER BY g.order_num;
