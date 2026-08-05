-- ============================================================
-- AGKB 360° — Modul Feedback & Ticketing
-- Migration 009 · 5 Agustus 2026
-- ------------------------------------------------------------
-- Tabel `feedback` lama KOSONG (0 baris di production per 5 Ags 2026)
-- sehingga aman di-rename tanpa kehilangan data.
-- Jalankan di VPS dengan:
--   docker exec -i ktb_mysql mysql -u ktb_user -pktb_pass_2024 ktb_production < 009_feedback_ticketing.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── 0. Arsipkan tabel lama ──────────────────────────────────
-- PENTING: JANGAN pernah DROP feedback_legacy. Tabel itu berisi data asli
-- yang diselamatkan dari tabel `feedback` lama. Menjalankan ulang migration
-- ini setelah berhasil sekali akan gagal di baris RENAME — itu memang
-- disengaja, sebagai pengaman agar data lama tidak tertimpa.
CREATE TABLE IF NOT EXISTS `feedback` (`id` INT AUTO_INCREMENT PRIMARY KEY);
RENAME TABLE `feedback` TO `feedback_legacy`;

-- ── 1. Kategori ─────────────────────────────────────────────
DROP TABLE IF EXISTS `feedback_categories`;
CREATE TABLE `feedback_categories` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `code`                VARCHAR(40)  NOT NULL,
  `name`                VARCHAR(120) NOT NULL,
  `description`         VARCHAR(255) NULL,
  `track`               ENUM('apresiasi','inquiry','safeguarding') NOT NULL DEFAULT 'inquiry',
  `default_pic_id`      INT NULL,
  `default_priority`    ENUM('P1','P2','P3','P4') NOT NULL DEFAULT 'P3',
  `sla_response_hours`  INT NOT NULL DEFAULT 48,
  `sla_resolve_hours`   INT NOT NULL DEFAULT 120,
  `start_level`         TINYINT NOT NULL DEFAULT 1,
  `is_sensitive`        TINYINT(1) NOT NULL DEFAULT 0,
  `allow_anonymous`     TINYINT(1) NOT NULL DEFAULT 0,
  `require_attachment`  TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`           TINYINT(1) NOT NULL DEFAULT 1,
  `order_num`           INT NOT NULL DEFAULT 0,
  `created_at`          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_code` (`code`),
  KEY `idx_track` (`track`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `fc_pic` FOREIGN KEY (`default_pic_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Tiket ────────────────────────────────────────────────
DROP TABLE IF EXISTS `feedback_tickets`;
CREATE TABLE `feedback_tickets` (
  `id`                     INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_no`              VARCHAR(20) NOT NULL,
  `track`                  ENUM('apresiasi','inquiry','safeguarding') NOT NULL DEFAULT 'inquiry',
  `category_id`            INT NULL,
  `sender_id`              INT NOT NULL COMMENT 'Selalu tersimpan, termasuk saat anonim',
  `is_anonymous`           TINYINT(1) NOT NULL DEFAULT 0,
  `subject`                VARCHAR(255) NOT NULL,
  `message`                TEXT NOT NULL COMMENT 'IMMUTABLE - tidak boleh di-UPDATE',
  `impact`                 ENUM('individu','kelompok','sekolah') NULL,
  `priority`               ENUM('P1','P2','P3','P4') NOT NULL DEFAULT 'P3',
  `priority_overridden_by` INT NULL,
  `status`                 ENUM('baru','ditinjau','ditindaklanjuti','menunggu_pelapor','selesai','ditutup')
                           NOT NULL DEFAULT 'baru',
  `level`                  TINYINT NOT NULL DEFAULT 1,
  `assignee_id`            INT NULL,
  `appreciated_user_id`    INT NULL COMMENT 'Track apresiasi',
  `forwarded_at`           DATETIME NULL,
  `due_at`                 DATETIME NULL,
  `response_due_at`        DATETIME NULL,
  `first_response_at`      DATETIME NULL,
  `paused_at`              DATETIME NULL COMMENT 'Saat status menunggu_pelapor',
  `paused_seconds`         INT NOT NULL DEFAULT 0,
  `resolved_at`            DATETIME NULL,
  `closed_at`              DATETIME NULL,
  `resolution_type`        ENUM('diselesaikan','diteruskan_eksternal','kebijakan_diubah',
                                'tidak_dapat_ditindaklanjuti','duplikat','informasi_tidak_cukup',
                                'tidak_terbukti') NULL,
  `resolution_note`        TEXT NULL,
  `resolved_by`            INT NULL,
  `is_test`                TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`             TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_ticket_no` (`ticket_no`),
  KEY `idx_status`   (`status`),
  KEY `idx_track`    (`track`),
  KEY `idx_assignee` (`assignee_id`),
  KEY `idx_sender`   (`sender_id`),
  KEY `idx_due`      (`due_at`),
  KEY `idx_test`     (`is_test`),
  KEY `idx_cat`      (`category_id`),
  CONSTRAINT `ft_cat`      FOREIGN KEY (`category_id`)         REFERENCES `feedback_categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `ft_sender`   FOREIGN KEY (`sender_id`)           REFERENCES `users`(`id`),
  CONSTRAINT `ft_assignee` FOREIGN KEY (`assignee_id`)         REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `ft_apprec`   FOREIGN KEY (`appreciated_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `ft_resolver` FOREIGN KEY (`resolved_by`)         REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Thread pesan ─────────────────────────────────────────
DROP TABLE IF EXISTS `feedback_messages`;
CREATE TABLE `feedback_messages` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id`  INT NOT NULL,
  `author_id`  INT NULL COMMENT 'NULL = pesan sistem',
  `body`       TEXT NOT NULL,
  `visibility` ENUM('publik','internal') NOT NULL DEFAULT 'publik',
  `is_system`  TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_ticket` (`ticket_id`),
  CONSTRAINT `fm_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `feedback_tickets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fm_author` FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. Audit log (INSERT-ONLY) ──────────────────────────────
DROP TABLE IF EXISTS `feedback_events`;
CREATE TABLE `feedback_events` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id`  INT NOT NULL,
  `actor_id`   INT NULL,
  `event_type` VARCHAR(40) NOT NULL,
  `from_value` VARCHAR(120) NULL,
  `to_value`   VARCHAR(120) NULL,
  `note`       VARCHAR(255) NULL,
  `ip`         VARCHAR(45) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_ticket` (`ticket_id`),
  KEY `idx_type`   (`event_type`),
  CONSTRAINT `fe_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `feedback_tickets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fe_actor`  FOREIGN KEY (`actor_id`)  REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. Watcher ──────────────────────────────────────────────
DROP TABLE IF EXISTS `feedback_watchers`;
CREATE TABLE `feedback_watchers` (
  `ticket_id`  INT NOT NULL,
  `user_id`    INT NOT NULL,
  `added_by`   INT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ticket_id`,`user_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fw_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `feedback_tickets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fw_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. Lampiran ─────────────────────────────────────────────
DROP TABLE IF EXISTS `feedback_attachments`;
CREATE TABLE `feedback_attachments` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id`     INT NOT NULL,
  `message_id`    INT NULL,
  `uploader_id`   INT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name`   VARCHAR(80)  NOT NULL,
  `mime`          VARCHAR(100) NOT NULL,
  `size_bytes`    INT NOT NULL,
  `sha256`        CHAR(64) NULL,
  `is_sealed`     TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = bukti safeguarding, tak bisa dihapus',
  `created_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_stored` (`stored_name`),
  KEY `idx_ticket` (`ticket_id`),
  CONSTRAINT `fa_ticket`  FOREIGN KEY (`ticket_id`)  REFERENCES `feedback_tickets`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fa_message` FOREIGN KEY (`message_id`) REFERENCES `feedback_messages`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fa_user`    FOREIGN KEY (`uploader_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 7. Rute eskalasi ────────────────────────────────────────
DROP TABLE IF EXISTS `feedback_escalation_levels`;
CREATE TABLE `feedback_escalation_levels` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `level`       TINYINT NOT NULL,
  `label`       VARCHAR(80) NOT NULL,
  `track`       ENUM('apresiasi','inquiry','safeguarding') NULL COMMENT 'NULL = semua track',
  `category_id` INT NULL COMMENT 'NULL = semua kategori, diisi utk direct routing',
  `user_id`     INT NULL,
  `email`       VARCHAR(120) NULL COMMENT 'Untuk pihak luar tanpa akun',
  `order_num`   INT NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  KEY `idx_level` (`level`),
  KEY `idx_cat`   (`category_id`),
  CONSTRAINT `fel_cat`  FOREIGN KEY (`category_id`) REFERENCES `feedback_categories`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fel_user` FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED — Kategori bawaan
-- PIC sengaja NULL: diisi lewat Admin CMS → Kategori Feedback
-- ============================================================

INSERT INTO `feedback_categories`
 (`code`,`name`,`description`,`track`,`default_priority`,`sla_response_hours`,`sla_resolve_hours`,
  `start_level`,`is_sensitive`,`allow_anonymous`,`require_attachment`,`order_num`) VALUES
-- Apresiasi (tanpa SLA efektif)
('apr_guru',     'Apresiasi Guru / Staf',        'Akui kontribusi seorang guru atau staf',        'apresiasi','P4',720,720,1,0,0,0,10),
('apr_program',  'Apresiasi Program / Kegiatan', 'Program atau kegiatan yang berjalan baik',      'apresiasi','P4',720,720,1,0,0,0,20),
('apr_umum',     'Apresiasi Umum',               'Hal positif lain yang ingin disampaikan',       'apresiasi','P4',720,720,1,0,0,0,30),
-- Inquiry
('inq_akademik', 'Akademik & Pembelajaran',      'Kurikulum, pengajaran, penilaian, IB DP',       'inquiry','P2',24, 72, 1,0,0,0,110),
('inq_kesiswaan','Kesiswaan & Kedisiplinan',     'Kegiatan siswa, tata tertib, OSIS',             'inquiry','P2',24, 72, 1,0,0,0,120),
('inq_sarana',   'Sarana & Fasilitas',           'Gedung, ruang kelas, alat, kebersihan',         'inquiry','P3',48,120, 1,0,0,0,130),
('inq_teknologi','Teknologi & Sistem AGKB',      'Akun, login, error sistem, perangkat',          'inquiry','P2',24, 48, 1,0,0,0,140),
('inq_admin',    'Administrasi & Keuangan',      'Surat, pembayaran, dokumen, jadwal',            'inquiry','P3',48,120, 1,0,0,0,150),
('inq_komunikasi','Komunikasi & Informasi',      'Penyampaian informasi, kanal komunikasi',       'inquiry','P3',48,120, 1,0,0,0,160),
('inq_sdm',      'Kepegawaian / SDM',            'Hal terkait ketenagakerjaan (non-safeguarding)','inquiry','P2',24,120, 1,0,0,0,170),
('inq_lain',     'Lain-lain',                    'Tidak masuk kategori mana pun di atas',         'inquiry','P3',48,120, 1,0,0,0,180),
-- Safeguarding
('sg_bullying',  'Perundungan (Bullying)',       'Perundungan fisik, verbal, sosial, atau daring','safeguarding','P1',24,24,3,1,1,0,210),
('sg_kekerasan', 'Kekerasan Fisik atau Verbal',  'Tindakan kekerasan terhadap anak',              'safeguarding','P1',24,24,3,1,1,0,220),
('sg_perilaku',  'Perilaku Tidak Pantas oleh Dewasa','Perilaku dewasa yang tidak pantas terhadap anak','safeguarding','P1',24,24,3,1,1,0,230),
('sg_keselamatan','Keselamatan & Keamanan',      'Kondisi yang membahayakan keselamatan anak',    'safeguarding','P1',24,24,3,1,1,0,240),
('sg_diskriminasi','Diskriminasi',               'Perlakuan diskriminatif atas dasar apa pun',    'safeguarding','P1',24,24,3,1,1,0,250);

-- ============================================================
-- SEED — Rute eskalasi (SEMENTARA)
-- Level 1 & 2 sengaja kosong; isi lewat Admin CMS setelah
-- bagan pihak penanggung jawab tersedia.
-- Level 3 (Yayasan) diisi otomatis dari role `foundation`.
-- ============================================================

INSERT INTO `feedback_escalation_levels` (`level`,`label`,`track`,`order_num`) VALUES
 (1,'Admin & Staf Sekolah',   'inquiry', 10),
 (2,'Pimpinan Sekolah',       'inquiry', 20),
 (3,'Yayasan (YPKBI)',        'inquiry', 30),
 (3,'Yayasan (YPKBI)',        'safeguarding', 40);

-- Isi level 3 dengan semua user ber-role foundation yang aktif
INSERT INTO `feedback_escalation_levels` (`level`,`label`,`track`,`user_id`,`order_num`)
SELECT 3, 'Yayasan (YPKBI)', 'safeguarding', id, 50
FROM `users` WHERE role = 'foundation' AND is_active = 1;
