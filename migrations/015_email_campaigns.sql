-- ============================================================
-- AGKB 360° — Kampanye Email Terjadwal
-- Migration 015
-- ------------------------------------------------------------
-- Menyiapkan pencatatan kiriman email agar penjadwal tahu siapa
-- sudah dikirimi apa, berapa kali, dan kapan terakhir. Tanpa ini
-- kampanye berulang tidak bisa berhenti sendiri.
--
-- Aman dijalankan berulang. Tidak menghapus data apa pun.
-- Jalankan di ktb_production DAN ktb_evaluation.
-- ============================================================

SET NAMES utf8mb4;

-- ── 1. Tabel log kiriman ────────────────────────────────────
-- Dipakai admin/blast_email.php sejak lama, tapi tidak pernah
-- punya definisi resmi. Dibuat di sini kalau memang belum ada.
CREATE TABLE IF NOT EXISTS `email_blast_log` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `blast_type`      VARCHAR(40)  NOT NULL COMMENT 'role/segmen tujuan',
  `recipient_id`    INT          NULL,
  `recipient_email` VARCHAR(100) NOT NULL,
  `subject`         VARCHAR(255) NOT NULL,
  `status`          ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  `sent_by`         INT          NULL COMMENT 'NULL = dikirim penjadwal, bukan orang',
  `sent_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_recipient` (`recipient_id`),
  KEY `idx_sent_at`   (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Kolom kampanye ───────────────────────────────────────
SET @ada := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'email_blast_log'
               AND COLUMN_NAME  = 'campaign_code');
SET @sql := IF(@ada = 0,
  'ALTER TABLE `email_blast_log`
     ADD COLUMN `campaign_code` VARCHAR(40) NULL COMMENT ''kode kampanye terjadwal, NULL berarti blast manual'' AFTER `id`,
     ADD KEY `idx_campaign` (`campaign_code`, `recipient_id`, `sent_at`)',
  'SELECT ''kolom campaign_code sudah ada'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 3. sent_by harus boleh NULL ─────────────────────────────
-- Kiriman dari penjadwal tidak punya "orang yang menekan tombol".
ALTER TABLE `email_blast_log` MODIFY `sent_by` INT NULL;

-- ── 4. Pengaturan kampanye ──────────────────────────────────
-- Saklar, frekuensi, dan peran sasaran disimpan di sini — bukan
-- di kode — supaya admin bisa mengaturnya sendiri lewat halaman
-- Blast Email tanpa perlu deploy ulang.
CREATE TABLE IF NOT EXISTS `email_campaign_state` (
  `code`       VARCHAR(40) PRIMARY KEY,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 0,
  `jeda_hari`  SMALLINT     NOT NULL DEFAULT 7  COMMENT 'jarak minimum antar kiriman ke orang yang sama',
  `maks_kirim` SMALLINT     NOT NULL DEFAULT 0  COMMENT '0 = kirim terus sampai orangnya bertindak',
  `roles`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'peran sasaran, dipisah koma. kosong = semua peran',
  `started_at` DATETIME     NULL COMMENT 'kapan kampanye dinyalakan',
  `ends_at`    DATETIME     NULL COMMENT 'berhenti sendiri setelah tanggal ini',
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kolom menyusul, untuk pemasangan yang tabelnya sudah terlanjur ada
SET @t := 'email_campaign_state';
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @t AND COLUMN_NAME = 'jeda_hari');
SET @sql := IF(@c = 0,
  'ALTER TABLE `email_campaign_state`
     ADD COLUMN `jeda_hari`  SMALLINT     NOT NULL DEFAULT 7  AFTER `is_active`,
     ADD COLUMN `maks_kirim` SMALLINT     NOT NULL DEFAULT 0  AFTER `jeda_hari`,
     ADD COLUMN `roles`      VARCHAR(255) NOT NULL DEFAULT '''' AFTER `maks_kirim`',
  'SELECT ''kolom pengaturan sudah ada'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Semua kampanye MATI saat dipasang. Dinyalakan sendiri lewat
-- halaman Blast Email — supaya tidak ada email yang terkirim
-- tanpa seseorang benar-benar memutuskannya.
--
-- roles kosong = SEMUA peran ikut (guru, staf, mentor, pimpinan,
-- yayasan, siswa, orang tua). Admin bisa mempersempitnya di UI.
INSERT INTO `email_campaign_state` (`code`, `is_active`, `jeda_hari`, `maks_kirim`, `roles`)
SELECT * FROM (
  SELECT 'aktivasi'       AS c, 0 AS a, 5 AS j, 0 AS m, '' AS r UNION ALL
  SELECT 'mulai_feedback',      0,      7,      0,      ''      UNION ALL
  SELECT 'ajakan_rutin',        0,      7,      4,      ''      UNION ALL
  SELECT 'antrean_unit',        0,      7,      0,      ''      UNION ALL
  SELECT 'tiket_telat',         0,      1,      0,      ''
) AS baru
WHERE NOT EXISTS (
  SELECT 1 FROM `email_campaign_state` e WHERE e.code = baru.c
);

-- ── 4b. Kolom token set-password ────────────────────────────
-- Kampanye Aktivasi menulis token ke sini. Di produksi kolom ini
-- sudah dibuat migrasi 014; di database demo belum tentu ada.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
             AND COLUMN_NAME = 'password_reset_token');
SET @sql := IF(@c = 0,
  'ALTER TABLE `users`
     ADD COLUMN `password_reset_token` VARCHAR(64) NULL DEFAULT NULL AFTER `password`,
     ADD COLUMN `token_expires_at`     DATETIME    NULL DEFAULT NULL AFTER `password_reset_token`,
     ADD KEY `idx_reset_token` (`password_reset_token`)',
  'SELECT ''kolom token set-password sudah ada'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 5. Hasil ────────────────────────────────────────────────
SELECT code, is_active, jeda_hari, maks_kirim, roles, ends_at
FROM `email_campaign_state` ORDER BY code;
SELECT COUNT(*) AS baris_log FROM `email_blast_log`;
