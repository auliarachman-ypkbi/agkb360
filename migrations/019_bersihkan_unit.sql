-- ============================================================
-- AGKB 360° — Rapikan Keanggotaan Unit Penanganan
-- Migration 019
-- ------------------------------------------------------------
-- Menyisakan tiga peran saja di tiap unit penanganan:
--   · PIC kategori           (penanggung jawab tiket)
--   · Tujuan eskalasi L2     (ikut memantau sejak awal)
--   · Tasya                  (Customer Care, semua kategori)
--
-- Dua unit sengaja TIDAK disentuh:
--   · Perlindungan Anak (Yayasan) — susunan penanganan laporan
--     perlindungan anak belum ditetapkan; mencabut aksesnya di
--     sini bisa membuat laporan sensitif tidak tertangani.
--   · Teknologi Informasi — kategorinya dinonaktifkan di migrasi
--     018, jadi anggotanya dibiarkan menunggu keputusan.
--
-- Yang dicabut hanya keanggotaan unit penanganan. Kelompok
-- evaluasi 360° dan akun penggunanya sama sekali tidak tersentuh.
--
-- Jalankan di ktb_production DAN ktb_evaluation.
-- Aman dijalankan berulang.
-- ============================================================

SET NAMES utf8mb4;

-- ── 1. Siapa yang akan tercabut ─────────────────────────────
-- Ditampilkan lebih dulu supaya ada jejak sebelum penghapusan.

SELECT g.name AS unit, u.name AS anggota_tercabut, u.email, u.role
FROM `user_groups` ug
JOIN `groups` g ON g.id = ug.group_id AND g.type = 'penanganan'
JOIN `users`  u ON u.id = ug.user_id
WHERE g.name NOT IN ('Perlindungan Anak (Yayasan)', 'Teknologi Informasi')
  AND u.email <> 'tasya.intern@kaderbangsa.foundation'
  AND NOT EXISTS (
        SELECT 1 FROM `feedback_categories` c
         WHERE c.handler_group_id = ug.group_id
           AND c.default_pic_id   = ug.user_id)
  AND NOT EXISTS (
        SELECT 1 FROM `feedback_escalation_levels` el
          JOIN `feedback_categories` c2 ON c2.id = el.category_id
         WHERE c2.handler_group_id = ug.group_id
           AND el.user_id          = ug.user_id)
ORDER BY g.order_num, u.name;

-- ── 2. Cabut ────────────────────────────────────────────────

DELETE ug FROM `user_groups` ug
JOIN `groups` g ON g.id = ug.group_id AND g.type = 'penanganan'
JOIN `users`  u ON u.id = ug.user_id
WHERE g.name NOT IN ('Perlindungan Anak (Yayasan)', 'Teknologi Informasi')
  AND u.email <> 'tasya.intern@kaderbangsa.foundation'
  AND NOT EXISTS (
        SELECT 1 FROM `feedback_categories` c
         WHERE c.handler_group_id = ug.group_id
           AND c.default_pic_id   = ug.user_id)
  AND NOT EXISTS (
        SELECT 1 FROM `feedback_escalation_levels` el
          JOIN `feedback_categories` c2 ON c2.id = el.category_id
         WHERE c2.handler_group_id = ug.group_id
           AND el.user_id          = ug.user_id);

-- ── 3. Susunan akhir ────────────────────────────────────────

SELECT g.name AS unit,
       GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ' · ') AS anggota
FROM `groups` g
LEFT JOIN `user_groups` ug ON ug.group_id = g.id
LEFT JOIN `users`       u  ON u.id = ug.user_id
WHERE g.type = 'penanganan'
GROUP BY g.id, g.name, g.order_num
ORDER BY g.order_num;
