-- ============================================================
-- AGKB 360° — Kategori Akademik pada Kanal Yayasan
-- Migration 030
-- ------------------------------------------------------------
-- Akademik terlewat saat menyusun kategori Kanal Yayasan di
-- migrasi 029. Padahal justru di bidang itu ada hal yang tidak
-- pantas melewati jalur sekolah — keberatan atas penilaian,
-- integritas ujian, atau perilaku pengajar di dalam kelas.
--
-- Berbeda dari kategori Akademik pada jalur Kendala/Masukan yang
-- ditangani Direktur Akademik, yang ini langsung ke Yayasan.
--
-- Jalankan di ktb_production DAN ktb_evaluation.
-- Aman dijalankan berulang.
-- ============================================================

SET NAMES utf8mb4;

INSERT INTO `feedback_categories`
 (`code`,`name`,`description`,`track`,`default_priority`,`sla_response_hours`,`sla_resolve_hours`,
  `start_level`,`is_sensitive`,`allow_anonymous`,`require_attachment`,`order_num`)
SELECT * FROM (
  SELECT 'ky_akademik' AS c,'Akademik' AS n,
         'Keberatan atas penilaian, integritas ujian, perilaku pengajar di kelas, dan hal akademik lain yang perlu perhatian Yayasan' AS d,
         'safeguarding' AS t,'P1' AS p,24 AS s1,72 AS s2,3 AS l,1 AS i,1 AS an,0 AS ra,315 AS o
) AS baru
WHERE NOT EXISTS (SELECT 1 FROM `feedback_categories` fc WHERE fc.code = baru.c);

-- Ikut unit yang sama seperti kategori kanal lainnya
UPDATE `feedback_categories` c
JOIN `groups` g ON g.type = 'penanganan' AND g.name = 'Kanal Yayasan'
SET c.handler_group_id = g.id
WHERE c.code = 'ky_akademik';

-- ── Hasil ───────────────────────────────────────────────────

SELECT c.order_num AS urut, c.name AS kategori,
       CONCAT(c.sla_response_hours, 'j / ', c.sla_resolve_hours, 'j') AS sla,
       g.name AS unit
FROM `feedback_categories` c
LEFT JOIN `groups` g ON g.id = c.handler_group_id
WHERE c.track = 'safeguarding' AND c.is_active = 1
ORDER BY c.order_num;
