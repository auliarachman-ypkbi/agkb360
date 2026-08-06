-- ============================================================
-- AGKB 360° — Naskah Kampanye Dapat Disunting
-- Migration 016
-- ------------------------------------------------------------
-- Memindahkan naskah email kampanye dari kode ke database agar
-- dapat disunting dari halaman Blast Email.
--
-- Kolom NULL berarti "pakai naskah bawaan dari kode". Dengan
-- begitu tombol Kembalikan ke Bawaan cukup mengosongkan kolom.
--
-- Aman dijalankan berulang. Jalankan di ktb_production DAN
-- ktb_evaluation.
-- ============================================================

SET NAMES utf8mb4;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME   = 'email_campaign_state'
             AND COLUMN_NAME  = 'subjek');

SET @sql := IF(@c = 0,
  'ALTER TABLE `email_campaign_state`
     ADD COLUMN `subjek` VARCHAR(255) NULL COMMENT ''NULL berarti pakai bawaan kode'' AFTER `roles`,
     ADD COLUMN `judul`  VARCHAR(255) NULL COMMENT ''judul di dalam badan email''      AFTER `subjek`,
     ADD COLUMN `body`   TEXT         NULL COMMENT ''HTML isi email''                  AFTER `judul`,
     ADD COLUMN `cta`    VARCHAR(80)  NULL COMMENT ''tulisan pada tombol''             AFTER `body`',
  'SELECT ''kolom naskah sudah ada'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT code, is_active,
       IF(subjek IS NULL, 'bawaan', 'disunting') AS naskah
FROM `email_campaign_state` ORDER BY code;
