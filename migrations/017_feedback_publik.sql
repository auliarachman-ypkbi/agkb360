-- ============================================================
-- AGKB 360° — Feedback Publik & Antrean Pendaftaran
-- Migration 017
-- ------------------------------------------------------------
-- Membuka jalur feedback untuk pelapor non-user (tamu), dan
-- menyediakan antrean pendaftaran akun yang perlu persetujuan
-- admin.
--
-- Tiga perubahan:
--   1. feedback_tickets.sender_id boleh NULL (tiket tamu)
--   2. kolom identitas tamu + token pelacakan
--   3. tabel registration_requests
--
-- Aman dijalankan berulang.
-- ============================================================

SET NAMES utf8mb4;

-- ── 1. sender_id boleh NULL ─────────────────────────────────
-- Foreign key ke users perlu dilepas dulu kalau ada, karena
-- NOT NULL diubah jadi NULL.

SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'feedback_tickets'
              AND COLUMN_NAME = 'sender_id'
              AND REFERENCED_TABLE_NAME = 'users' LIMIT 1);

SET @sql := IF(@fk IS NOT NULL,
  CONCAT('ALTER TABLE `feedback_tickets` DROP FOREIGN KEY `', @fk, '`'),
  'SELECT ''tidak ada foreign key sender_id'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

ALTER TABLE `feedback_tickets`
  MODIFY `sender_id` INT NULL
  COMMENT 'NULL berarti pelapor tamu — lihat kolom guest_*';

SET @fk2 := (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'feedback_tickets'
               AND COLUMN_NAME = 'sender_id'
               AND REFERENCED_TABLE_NAME = 'users');

SET @sql := IF(@fk2 = 0,
  'ALTER TABLE `feedback_tickets`
     ADD CONSTRAINT `ft_sender` FOREIGN KEY (`sender_id`)
     REFERENCES `users`(`id`) ON DELETE SET NULL',
  'SELECT ''foreign key sender_id sudah ada'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 2. Identitas tamu ───────────────────────────────────────

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME   = 'feedback_tickets'
             AND COLUMN_NAME  = 'guest_email');

SET @sql := IF(@c = 0,
  'ALTER TABLE `feedback_tickets`
     ADD COLUMN `guest_name`  VARCHAR(100) NULL COMMENT ''Nama yang diisi sendiri — TIDAK terverifikasi'' AFTER `sender_id`,
     ADD COLUMN `guest_email` VARCHAR(190) NULL COMMENT ''Untuk tautan pelacakan dan tindak lanjut''      AFTER `guest_name`,
     ADD COLUMN `guest_phone` VARCHAR(40)  NULL COMMENT ''Opsional''                                      AFTER `guest_email`,
     ADD COLUMN `guest_role`  VARCHAR(60)  NULL COMMENT ''Hubungan dengan sekolah, diisi sendiri''        AFTER `guest_phone`,
     ADD COLUMN `guest_token` CHAR(64)     NULL COMMENT ''Token pelacakan, dikirim lewat email''          AFTER `guest_role`,
     ADD COLUMN `guest_ip`    VARBINARY(16) NULL COMMENT ''Untuk pembatasan laju dan penelusuran spam''   AFTER `guest_token`,
     ADD UNIQUE KEY `uq_guest_token` (`guest_token`)',
  'SELECT ''kolom tamu sudah ada'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 3. Antrean pendaftaran ──────────────────────────────────
-- Sengaja tabel terpisah, bukan baris users nonaktif: pengajuan
-- spam tidak boleh mengunci alamat email milik calon user sah.

CREATE TABLE IF NOT EXISTS `registration_requests` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `name`         VARCHAR(100) NOT NULL,
  `email`        VARCHAR(190) NOT NULL,
  `phone`        VARCHAR(40)  NULL,
  `requested_role` ENUM('leader','teacher','student','parent','foundation') NOT NULL DEFAULT 'parent',
  `reason`       VARCHAR(500) NULL COMMENT 'Alasan mengajukan akun',
  `ticket_id`    INT NULL COMMENT 'Tiket yang memicu ajakan mendaftar, kalau ada',
  `status`       ENUM('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
  `decided_by`   INT NULL,
  `decided_at`   DATETIME NULL,
  `decision_note` VARCHAR(500) NULL,
  `created_user_id` INT NULL COMMENT 'Baris users yang dibuat saat disetujui',
  `ip`           VARBINARY(16) NULL,
  `created_at`   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_status` (`status`),
  KEY `idx_email`  (`email`),
  CONSTRAINT `rr_ticket`  FOREIGN KEY (`ticket_id`)        REFERENCES `feedback_tickets`(`id`) ON DELETE SET NULL,
  CONSTRAINT `rr_decider` FOREIGN KEY (`decided_by`)       REFERENCES `users`(`id`)            ON DELETE SET NULL,
  CONSTRAINT `rr_created` FOREIGN KEY (`created_user_id`)  REFERENCES `users`(`id`)            ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sengaja TIDAK unik pada email: satu orang boleh mengajukan
-- lagi setelah ditolak. Pencegahan ganda dilakukan di aplikasi.

SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='feedback_tickets'
      AND COLUMN_NAME LIKE 'guest_%')                       AS kolom_tamu,
  (SELECT IS_NULLABLE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='feedback_tickets'
      AND COLUMN_NAME='sender_id')                          AS sender_id_nullable,
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='registration_requests')               AS tabel_pendaftaran;
