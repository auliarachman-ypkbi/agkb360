-- ============================================================
-- AGKB 360° — Jam Kirim per Kampanye
-- Migration 022
-- ------------------------------------------------------------
-- Selama ini jam pengiriman kampanye ada di crontab VPS, sehingga
-- menggesernya menuntut akses SSH. Kolom ini memindahkannya ke
-- halaman Blast Email.
--
-- Cara kerjanya berubah: cron dijalankan SETIAP JAM, lalu tiap
-- kampanye hanya bekerja bila jamnya cocok. Jam dibaca memakai
-- waktu PHP (Asia/Jakarta), jadi angka di layar adalah WIB apa
-- adanya — tidak bergantung pada zona waktu sistem operasi VPS.
--
-- Setelah migrasi ini, crontab perlu diubah dari sekali sehari
-- menjadi tiap jam:
--     5 * * * 1-5 docker exec ktb_php php /var/www/html/app/cron/campaigns.php >> /var/log/agkb-kampanye.log 2>&1
--
-- Aman dijalankan berulang.
-- ============================================================

SET NAMES utf8mb4;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME   = 'email_campaign_state'
             AND COLUMN_NAME  = 'jam_kirim');

SET @sql := IF(@c = 0,
  'ALTER TABLE `email_campaign_state`
     ADD COLUMN `jam_kirim` TINYINT NULL
     COMMENT ''Jam WIB 0-23. NULL berarti pakai jam bawaan kampanye''
     AFTER `maks_kirim`',
  'SELECT ''kolom jam_kirim sudah ada'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT code,
       is_active,
       IFNULL(jam_kirim, '(bawaan)') AS jam_kirim
FROM `email_campaign_state`
ORDER BY code;
