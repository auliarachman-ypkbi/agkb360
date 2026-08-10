-- ============================================================
-- AGKB 360° — Perlindungan Anak Menjadi Kanal Yayasan
-- Migration 029
-- ------------------------------------------------------------
-- Jalur tertutup diperluas: bukan lagi khusus perlindungan anak,
-- melainkan kanal umum untuk hal yang tidak pantas melewati jalur
-- sekolah — termasuk masukan mengenai pimpinan, kehidupan asrama,
-- dan tata kelola.
--
-- Kode track di database tetap 'safeguarding'. Menggantinya
-- menyentuh enum, indeks, dan seluruh tiket lama tanpa manfaat.
-- Yang berubah label, kategori, dan siapa yang boleh membaca.
--
-- ── Aturan seragam, atas keputusan pemilik ──────────────────
-- Seluruh kategori kanal ini memakai aturan yang sama: P1,
-- tanggapan 24 jam, penyelesaian 72 jam, lampiran disegel, isi
-- tidak dapat diubah, boleh dikirim anonim.
--
-- Perlu dicatat: sebelumnya kategori perlindungan anak menuntut
-- PENYELESAIAN dalam 24 jam, kini 72 jam. Batas tanggapan pertama
-- tetap 24 jam, sehingga laporan tetap dilihat orang pada hari
-- yang sama. Bila kelak ingin dikembalikan, ubah SLA kategori
-- Perlindungan Anak lewat Admin CMS — satu layar, tanpa migrasi.
--
-- ── Akses ───────────────────────────────────────────────────
-- Tidak lagi berbasis peran. fbCanSeeSafeguarding() kini membaca
-- keanggotaan unit 'Kanal Yayasan'. Menambah atau mencabut orang
-- cukup lewat Admin CMS.
--
-- Jalankan di ktb_production DAN ktb_evaluation.
-- Aman dijalankan berulang.
-- ============================================================

SET NAMES utf8mb4;

-- ── 1. Ganti nama unit ──────────────────────────────────────

UPDATE `groups`
   SET `name` = 'Kanal Yayasan',
       `description` = 'Kanal tertutup ke Yayasan — akses terbatas pada anggota unit ini'
 WHERE `type` = 'penanganan'
   AND `name` = 'Perlindungan Anak (Yayasan)';

-- ── 2. Kategori baru ────────────────────────────────────────
-- Kelima kategori perlindungan anak yang lama dilebur jadi satu.
-- Rinciannya tetap tertulis pada keterangan, dan isi laporannya
-- sendiri yang menjelaskan duduk perkaranya.

INSERT INTO `feedback_categories`
 (`code`,`name`,`description`,`track`,`default_priority`,`sla_response_hours`,`sla_resolve_hours`,
  `start_level`,`is_sensitive`,`allow_anonymous`,`require_attachment`,`order_num`)
SELECT * FROM (
  SELECT 'ky_anak'     AS c,'Perlindungan Anak' AS n,
         'Perundungan, kekerasan fisik atau verbal, perilaku tidak pantas oleh dewasa, diskriminasi, dan kondisi yang membahayakan keselamatan siswa' AS d,
         'safeguarding' AS t,'P1' AS p,24 AS s1,72 AS s2,3 AS l,1 AS i,1 AS an,0 AS ra,310 AS o
  UNION ALL SELECT 'ky_kepemimpinan','Kepemimpinan Sekolah',
         'Masukan dan persepsi terhadap Kepala Sekolah serta jajaran pimpinan',
         'safeguarding','P1',24,72,3,1,1,0,320
  UNION ALL SELECT 'ky_kebhayangkaraan','Kebhayangkaraan',
         'Masukan dan persepsi terhadap program serta penerapan kebhayangkaraan',
         'safeguarding','P1',24,72,3,1,1,0,330
  UNION ALL SELECT 'ky_asrama','Kehidupan Asrama',
         'Pengasuhan, ketertiban, perlakuan, dan kehidupan siswa di asrama',
         'safeguarding','P1',24,72,3,1,1,0,340
  UNION ALL SELECT 'ky_sarana','Sarana & Prasarana',
         'Kondisi fasilitas yang dinilai perlu perhatian langsung Yayasan',
         'safeguarding','P1',24,72,3,1,1,0,350
  UNION ALL SELECT 'ky_tata_kelola','Tata Kelola & Keuangan',
         'Dugaan penyimpangan, benturan kepentingan, dan penggunaan dana',
         'safeguarding','P1',24,72,3,1,1,0,360
  UNION ALL SELECT 'ky_lain','Lain-lain',
         'Hal lain yang perlu disampaikan langsung ke Yayasan',
         'safeguarding','P1',24,72,3,1,1,0,370
) AS baru
WHERE NOT EXISTS (SELECT 1 FROM `feedback_categories` fc WHERE fc.code = baru.c);

-- ── 3. Samakan aturan kategori lama yang masih terpakai ─────

UPDATE `feedback_categories`
   SET `sla_resolve_hours` = 72
 WHERE `track` = 'safeguarding' AND `sla_resolve_hours` < 72;

-- ── 4. Pindahkan tiket lama ke kategori Perlindungan Anak ───

INSERT INTO `feedback_events` (`ticket_id`,`actor_id`,`event_type`,`from_value`,`to_value`,`note`)
SELECT t.id, NULL, 'kategori_diubah', c.name, 'Perlindungan Anak',
       'Dipindahkan otomatis: lima kategori perlindungan anak dilebur menjadi satu'
FROM `feedback_tickets` t
JOIN `feedback_categories` c ON c.id = t.category_id
WHERE c.code IN ('sg_bullying','sg_kekerasan','sg_perilaku','sg_keselamatan','sg_diskriminasi');

UPDATE `feedback_tickets` t
JOIN `feedback_categories` lama ON lama.id = t.category_id
                               AND lama.code IN ('sg_bullying','sg_kekerasan','sg_perilaku',
                                                 'sg_keselamatan','sg_diskriminasi')
JOIN `feedback_categories` baru ON baru.code = 'ky_anak'
SET t.category_id = baru.id;

-- Kategori lama dinonaktifkan, bukan dihapus — riwayat tetap utuh
-- dan bisa dihidupkan lagi bila keputusan ini ditinjau ulang.
UPDATE `feedback_categories` SET `is_active` = 0
 WHERE `code` IN ('sg_bullying','sg_kekerasan','sg_perilaku','sg_keselamatan','sg_diskriminasi');

-- ── 5. Semua kategori kanal ini ke unit Kanal Yayasan ───────

UPDATE `feedback_categories` c
JOIN `groups` g ON g.type = 'penanganan' AND g.name = 'Kanal Yayasan'
SET c.handler_group_id = g.id
WHERE c.track = 'safeguarding';

-- ── Hasil ───────────────────────────────────────────────────

SELECT c.order_num AS urut, c.name AS kategori, c.default_priority AS prioritas,
       CONCAT(c.sla_response_hours, 'j / ', c.sla_resolve_hours, 'j') AS sla,
       g.name AS unit
FROM `feedback_categories` c
LEFT JOIN `groups` g ON g.id = c.handler_group_id
WHERE c.track = 'safeguarding' AND c.is_active = 1
ORDER BY c.order_num;

SELECT u.name AS boleh_membaca, u.email, u.role AS peran
FROM `user_groups` ug
JOIN `groups` g ON g.id = ug.group_id AND g.name = 'Kanal Yayasan'
JOIN `users`  u ON u.id = ug.user_id AND u.is_active = 1
ORDER BY u.name;
