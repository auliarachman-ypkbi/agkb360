-- ============================================================
-- AGKB 360° — Log Email untuk Penerima di Luar Sistem
-- Migration 032
-- ------------------------------------------------------------
-- email_blast_log.recipient_id ditetapkan NOT NULL oleh
-- database/schema.sql yang lama. Migrasi 015 menuliskannya boleh
-- kosong, tetapi memakai CREATE TABLE IF NOT EXISTS — sehingga
-- pada database yang tabelnya sudah ada, definisi lama bertahan.
--
-- Akibatnya pencatatan gagal untuk alamat yang bukan pengguna
-- sistem: alamat cadangan Gmail, tembusan tetap, dan pihak luar
-- pada rute eskalasi. Justru alamat-alamat itu yang paling perlu
-- dicatat, karena tidak punya jejak lain di mana pun.
--
-- Jalankan di ktb_production DAN ktb_evaluation.
-- Aman dijalankan berulang.
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `email_blast_log`
  MODIFY `recipient_id` INT NULL
  COMMENT 'NULL = penerima di luar sistem, misalnya tembusan atau alamat cadangan';

SELECT COLUMN_NAME AS kolom, IS_NULLABLE AS boleh_kosong, COLUMN_COMMENT AS keterangan
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'email_blast_log'
  AND COLUMN_NAME  = 'recipient_id';
