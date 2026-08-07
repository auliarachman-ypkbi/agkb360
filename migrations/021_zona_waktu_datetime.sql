-- ============================================================
-- AGKB 360° — Perbaikan Selisih Zona Waktu
-- Migration 021
-- ------------------------------------------------------------
-- PHP memakai Asia/Jakarta, MySQL berjalan pada UTC. Selisih
-- tujuh jam itu membuat eskalasi otomatis dan peringatan tiket
-- terlambat baru bekerja tujuh jam setelah seharusnya, karena
-- membandingkan due_at (ditulis PHP, WIB) dengan NOW() (UTC).
--
-- Zona waktu MySQL dibetulkan lewat docker-compose:
--     command: --default-time-zone=+07:00
-- Migrasi ini hanya membereskan sisa data lamanya.
--
-- ── Yang TIDAK perlu disentuh ───────────────────────────────
-- Kolom bertipe TIMESTAMP (created_at, updated_at, sent_at)
-- disimpan MySQL sebagai titik waktu mutlak dan diterjemahkan
-- saat dibaca. Begitu zona waktunya benar, kolom itu langsung
-- menampilkan WIB dengan sendirinya.
--
-- Kolom DATETIME yang ditulis PHP (due_at, response_due_at,
-- resolved_at, closed_at, token_expires_at, decided_at) sejak
-- awal sudah dalam WIB. Menggesernya justru merusak.
--
-- ── Yang perlu digeser ──────────────────────────────────────
-- Hanya kolom DATETIME yang diisi MySQL lewat NOW(), sehingga
-- nilainya tersimpan sebagai UTC secara harfiah:
--     users.last_login
--     email_campaign_state.started_at
--
-- Penggeseran hanya boleh terjadi SEKALI. Karena itu ada tabel
-- penanda; menjalankan berkas ini dua kali tidak akan menggeser
-- data dua kali.
--
-- Jalankan di ktb_production DAN ktb_evaluation, SESUDAH
-- container MySQL dijalankan ulang dengan zona waktu baru.
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `migrasi_tercatat` (
  `kode`            VARCHAR(64) PRIMARY KEY,
  `keterangan`      VARCHAR(255) NULL,
  `dijalankan_pada` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sudah := (SELECT COUNT(*) FROM `migrasi_tercatat`
                WHERE kode = '021_geser_datetime_utc');

-- ── users.last_login ────────────────────────────────────────
SET @sql := IF(@sudah = 0,
  'UPDATE `users` SET `last_login` = DATE_ADD(`last_login`, INTERVAL 7 HOUR)
    WHERE `last_login` IS NOT NULL',
  'SELECT ''sudah pernah digeser — dilewati'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── email_campaign_state.started_at ─────────────────────────
SET @sql := IF(@sudah = 0,
  'UPDATE `email_campaign_state` SET `started_at` = DATE_ADD(`started_at`, INTERVAL 7 HOUR)
    WHERE `started_at` IS NOT NULL',
  'SELECT ''dilewati'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── Catat ───────────────────────────────────────────────────
INSERT IGNORE INTO `migrasi_tercatat` (`kode`, `keterangan`, `dijalankan_pada`)
VALUES ('021_geser_datetime_utc',
        'Menggeser kolom DATETIME yang diisi NOW() dari UTC ke WIB',
        NOW());

-- ── Hasil ───────────────────────────────────────────────────

SELECT @@session.time_zone AS zona_mysql,
       NOW()               AS jam_mysql,
       CASE WHEN HOUR(NOW()) = HOUR(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '+07:00'))
            THEN 'BENAR — sudah WIB'
            ELSE 'MASIH SALAH — container belum dijalankan ulang'
       END AS status;

SELECT COUNT(*) AS tiket_terlambat_belum_dieskalasi
FROM `feedback_tickets`
WHERE is_test = 0
  AND status IN ('baru','ditinjau','ditindaklanjuti')
  AND due_at IS NOT NULL
  AND due_at < NOW();
