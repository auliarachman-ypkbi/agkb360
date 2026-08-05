-- ============================================================
-- AGKB 360° — Samakan daftar peran di database demo
-- Migration 012
-- ------------------------------------------------------------
-- ktb_evaluation dibuat dari schema lama yang belum mengenal
-- peran 'staff' dan 'mentor', sehingga seeder gagal dengan
-- "Data truncated for column 'role'".
--
-- Jalankan HANYA di ktb_evaluation:
--   docker exec -i ktb_mysql mysql -u ktb_user -pktb_pass_2024 ktb_evaluation \
--     < migrations/012_align_demo_roles.sql
--
-- Aman dijalankan berulang. Tidak ada data yang hilang —
-- ini hanya memperluas pilihan yang diizinkan.
-- ============================================================

ALTER TABLE `users`
  MODIFY COLUMN `role`
  ENUM('superadmin','admin','foundation','leader','teacher','student','parent','tester','staff','mentor')
  COLLATE utf8mb4_unicode_ci NOT NULL;

SELECT COLUMN_TYPE AS daftar_peran_sekarang
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME = 'role';
