-- ============================================================
-- AGKB 360° — Siapa Menerima Apa
-- ------------------------------------------------------------
-- Untuk tiap kategori kendala: siapa saja yang menerima email
-- saat ada tiket baru, apa perannya, dan apakah akunnya sudah
-- bisa dipakai.
--
-- Penerima berasal dari empat sumber yang digabung oleh
-- fbNotifyNew():
--   1. PIC kategori          (default_pic_id)
--   2. Tujuan eskalasi L2    (feedback_escalation_levels)
--   3. Seluruh anggota unit  (handler_group_id)
--   4. Tembusan tetap        (hardcode di feedback.php)
--
-- Kolom kata sandi dibaca dari panjang hash: akun yang dibuat
-- lewat migrasi diisi SHA2 sepanjang 64 karakter dan TIDAK bisa
-- dipakai login. Kata sandi asli (bcrypt) panjangnya 60.
--
-- Jalankan: mysql ... ktb_production < laporan/penerima-per-kategori.sql
-- ============================================================

SET NAMES utf8mb4;

-- ── 1. Penerima per kategori ────────────────────────────────

SELECT
  c.order_num                                        AS urut,
  c.name                                             AS kategori,
  u.name                                             AS penerima,
  u.email                                            AS email,
  GROUP_CONCAT(DISTINCT s.peran ORDER BY s.peran SEPARATOR ' + ') AS peran,
  CASE WHEN CHAR_LENGTH(u.password) = 64
       THEN 'BELUM dibuat' ELSE 'Sudah dibuat' END   AS kata_sandi,
  IFNULL(DATE_FORMAT(u.last_login, '%d %b %Y'),
         'Belum pernah')                             AS login_terakhir
FROM (
      -- PIC kategori — langsung jadi penanggung jawab tiket
      SELECT c1.id AS cid, c1.default_pic_id AS uid, 'PIC' AS peran
        FROM feedback_categories c1
       WHERE c1.default_pic_id IS NOT NULL

      UNION ALL

      -- Tujuan eskalasi level 2
      SELECT el.category_id, el.user_id, 'Eskalasi L2'
        FROM feedback_escalation_levels el
       WHERE el.level = 2 AND el.is_active = 1 AND el.user_id IS NOT NULL

      UNION ALL

      -- Anggota unit penanganan kategori tersebut
      SELECT c2.id, ug.user_id, 'Anggota unit'
        FROM feedback_categories c2
        JOIN user_groups ug ON ug.group_id = c2.handler_group_id
     ) AS s
JOIN feedback_categories c ON c.id = s.cid
JOIN users u               ON u.id = s.uid AND u.is_active = 1
WHERE c.track = 'inquiry' AND c.is_active = 1
GROUP BY c.id, c.order_num, c.name, u.id, u.name, u.email, u.password, u.last_login
ORDER BY c.order_num, FIELD(MIN(s.peran), 'PIC', 'Anggota unit', 'Eskalasi L2'), u.name;


-- ── 2. Tembusan tetap ───────────────────────────────────────
-- Dua alamat ini menerima SEMUA kategori. Tidak tersimpan di
-- database — ditulis langsung di fbTembusanTetap() pada
-- includes/feedback.php.

SELECT 'aulia.rachman@kaderbangsa.foundation' AS email,
       'Pengembang'                           AS peran,
       'Semua jalur, termasuk perlindungan anak' AS cakupan
UNION ALL
SELECT 'tasya.intern@kaderbangsa.foundation',
       'Customer Care',
       'Apresiasi dan kendala — TIDAK termasuk perlindungan anak';


-- ── 3. Akun yang belum bisa dipakai ─────────────────────────
-- Selama kata sandinya belum dibuat, orang ini menerima email
-- tetapi tidak bisa membuka tiketnya. Sistem mengirimi mereka
-- tautan aktivasi tersendiri saat ada tiket masuk.

SELECT u.name AS penerima, u.email,
       IFNULL(DATE_FORMAT(u.token_expires_at, '%d %b %Y'),
              'tidak ada') AS tautan_aktivasi_berlaku_sampai
FROM users u
WHERE u.is_active = 1
  AND CHAR_LENGTH(u.password) = 64
  AND u.id IN (
        SELECT default_pic_id FROM feedback_categories WHERE default_pic_id IS NOT NULL
        UNION
        SELECT user_id FROM feedback_escalation_levels WHERE user_id IS NOT NULL
        UNION
        SELECT ug.user_id FROM user_groups ug
          JOIN `groups` g ON g.id = ug.group_id AND g.type = 'penanganan'
      )
ORDER BY u.name;


-- ── 4. Jalur perlindungan anak ──────────────────────────────
-- Sengaja terpisah: penerimanya tidak mengikuti pola di atas.

SELECT c.name AS kategori_safeguarding,
       IFNULL(u.name, '— belum ada —') AS penerima,
       IFNULL(u.email, '—')            AS email,
       IFNULL(g.name, '—')             AS unit
FROM feedback_categories c
LEFT JOIN `groups` g      ON g.id = c.handler_group_id
LEFT JOIN user_groups ug  ON ug.group_id = g.id
LEFT JOIN users u         ON u.id = ug.user_id AND u.is_active = 1
WHERE c.track = 'safeguarding' AND c.is_active = 1
ORDER BY c.order_num, u.name;
