-- ============================================================
-- AGKB 360° — Teknologi Masuk Sarana, dan Pemantau Yayasan
-- Migration 023
-- ------------------------------------------------------------
-- Tiga perubahan:
--
--   1. Kategori "Teknologi & Sistem AGKB" dilebur ke "Sarana &
--      Fasilitas". Migrasi 018 menonaktifkannya tanpa memindahkan
--      isinya, sehingga keluhan akun, login, dan perangkat tidak
--      punya tempat. Tiket lama ikut dipindahkan agar tidak
--      menggantung pada kategori yang tak terlihat.
--
--   2. Tim Teknologi Informasi dimasukkan ke unit Sarana &
--      Prasarana. Tanpa ini, keluhan teknis mendarat di unit yang
--      tidak punya orang yang memahaminya.
--
--   3. Ketua Yayasan ditambahkan sebagai pemantau Sarana &
--      Fasilitas atas permintaan sendiri — pengawasan dekat pada
--      kategori yang sedang jadi perhatian.
--
-- Jalankan di ktb_production DAN ktb_evaluation.
-- Aman dijalankan berulang.
-- ============================================================

SET NAMES utf8mb4;

-- ── 1. Perluas cakupan Sarana & Fasilitas ───────────────────

UPDATE `feedback_categories` SET
  description = 'Kebersihan, kerusakan fasilitas, keamanan lingkungan, dormitory, makanan, laundry, perpustakaan & lab, parkir, serta akun, login, dan perangkat sistem'
WHERE code = 'inq_sarana';

-- ── 2. Pindahkan tiket Teknologi ────────────────────────────
-- Dicatat di riwayat tiket supaya perpindahan ini bisa ditelusuri
-- kemudian, bukan berubah diam-diam.

INSERT INTO `feedback_events` (`ticket_id`,`actor_id`,`event_type`,`from_value`,`to_value`,`note`)
SELECT t.id, NULL, 'kategori_diubah',
       'Teknologi & Sistem AGKB', 'Sarana & Fasilitas',
       'Dipindahkan otomatis: kategori Teknologi dilebur ke Sarana & Fasilitas'
FROM `feedback_tickets` t
JOIN `feedback_categories` c ON c.id = t.category_id
WHERE c.code = 'inq_teknologi';

UPDATE `feedback_tickets` t
JOIN `feedback_categories` lama ON lama.id = t.category_id AND lama.code = 'inq_teknologi'
JOIN `feedback_categories` baru ON baru.code = 'inq_sarana'
SET t.category_id = baru.id;

-- ── 3. Tim Teknologi ikut menangani Sarana ──────────────────

INSERT IGNORE INTO `user_groups` (`user_id`,`group_id`)
SELECT ug.user_id, tujuan.id
FROM `user_groups` ug
JOIN `groups` asal   ON asal.id = ug.group_id
                    AND asal.type = 'penanganan'
                    AND asal.name = 'Teknologi Informasi'
JOIN `groups` tujuan ON tujuan.type = 'penanganan'
                    AND tujuan.name = 'Sarana & Prasarana';

-- ── 4. Ketua Yayasan memantau Sarana & Fasilitas ────────────
-- Keanggotaan unit, bukan PIC: beliau ikut melihat dan menerima
-- notifikasi, tanpa mengambil alih tanggung jawab Pak Toni.

INSERT IGNORE INTO `user_groups` (`user_id`,`group_id`)
SELECT u.id, g.id
FROM `users` u
CROSS JOIN `groups` g
WHERE u.email = 'aqsa@kaderbangsa.foundation'
  AND u.is_active = 1
  AND g.type = 'penanganan'
  AND g.name = 'Sarana & Prasarana';

-- ── Hasil ───────────────────────────────────────────────────

SELECT 'Tiket yang dipindahkan' AS keterangan,
       COUNT(*) AS jumlah
FROM `feedback_events`
WHERE event_type = 'kategori_diubah'
  AND from_value = 'Teknologi & Sistem AGKB';

SELECT u.name AS anggota_unit_sarana, u.email, u.role AS peran
FROM `user_groups` ug
JOIN `groups` g ON g.id = ug.group_id AND g.name = 'Sarana & Prasarana'
JOIN `users`  u ON u.id = ug.user_id AND u.is_active = 1
ORDER BY u.name;
