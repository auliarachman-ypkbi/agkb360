-- ============================================================
-- AGKB 360° — Alamat Cadangan Jalur Perlindungan Anak
-- Migration 028
-- ------------------------------------------------------------
-- Menambahkan reybiwrn@gmail.com sebagai penerima notifikasi
-- kedua untuk jalur perlindungan anak, di samping alamat yayasan
-- reybi@kaderbangsa.foundation dari migrasi 027.
--
-- Dibuat sebagai baris rute ber-email, BUKAN akun kedua. Tabel
-- feedback_escalation_levels memang menyediakan kolom email untuk
-- pihak yang perlu dikabari tanpa punya akun. Membuat akun kedua
-- untuk orang yang sama akan memecah riwayat penanganannya dan
-- menyulitkan penelusuran siapa mengerjakan apa.
--
-- Sifatnya HANYA notifikasi. Tautan di dalam email tetap menuntut
-- login, dan login itu memakai akun yayasan — alamat Gmail ini
-- tidak bisa membuka tiket apa pun.
--
-- ── Yang perlu disadari ─────────────────────────────────────
-- Email notifikasi memuat 600 karakter pertama isi laporan. Untuk
-- jalur perlindungan anak, itu berarti cuplikan laporan sensitif
-- masuk ke kotak Gmail pribadi — di luar kendali sekolah, tidak
-- tunduk pada kebijakan retensi maupun penonaktifan akun yayasan.
-- Sebagai jaring pengaman hal itu wajar; sebagai kanal utama
-- sebaiknya tidak.
--
-- Mencabutnya cukup dengan menghapus baris berlabel di bawah.
--
-- Jalankan di ktb_production DAN ktb_evaluation.
-- Aman dijalankan berulang.
-- ============================================================

SET NAMES utf8mb4;

-- Kategori safeguarding mulai pada level 3, jadi rute cadangan
-- ini dipasang di level yang sama. category_id NULL berarti
-- berlaku untuk kelima kategori sekaligus.
-- user_id NULL membuatnya tidak pernah terpilih sebagai
-- penanggung jawab — fbResolveAssignee() hanya melirik baris yang
-- punya user_id.

INSERT INTO `feedback_escalation_levels`
  (`level`,`label`,`track`,`category_id`,`user_id`,`email`,`order_num`,`is_active`)
SELECT * FROM (
  SELECT 3 AS l,
         'Cadangan — Perlindungan Anak (Reybi)' AS lb,
         'safeguarding' AS t,
         NULL AS c,
         NULL AS u,
         'reybiwrn@gmail.com' AS e,
         90 AS o,
         1 AS a
) AS baru
WHERE NOT EXISTS (
  SELECT 1 FROM `feedback_escalation_levels` el
   WHERE el.email = 'reybiwrn@gmail.com'
     AND el.track = 'safeguarding'
);

-- ── Hasil ───────────────────────────────────────────────────

SELECT 'Penerima notifikasi jalur perlindungan anak' AS keterangan;

SELECT u.name AS penerima, u.email, u.role AS peran, 'anggota unit' AS sumber
FROM `user_groups` ug
JOIN `groups` g ON g.id = ug.group_id AND g.name = 'Perlindungan Anak (Yayasan)'
JOIN `users`  u ON u.id = ug.user_id AND u.is_active = 1
UNION ALL
SELECT el.label, el.email, '—', 'rute cadangan'
FROM `feedback_escalation_levels` el
WHERE el.track = 'safeguarding' AND el.email IS NOT NULL AND el.is_active = 1
ORDER BY sumber, penerima;
