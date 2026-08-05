-- ============================================================
-- AGKB 360° — Perbaiki jam SLA tiket hasil pindahan
-- Migration 011
-- ------------------------------------------------------------
-- Migration 010 versi pertama menghitung tenggat dari tanggal
-- feedback aslinya, sehingga tiket lama langsung berstatus
-- terlambat dan memicu eskalasi otomatis beserta emailnya.
-- Skrip ini menyetel ulang jam SLA mulai dari sekarang.
-- Aman dijalankan berulang.
-- ============================================================

UPDATE feedback_tickets t
JOIN feedback_events e
  ON e.ticket_id = t.id AND e.event_type = 'dipindahkan_dari_lama'
LEFT JOIN feedback_categories c ON c.id = t.category_id
SET
  t.response_due_at = CASE
      WHEN t.track = 'apresiasi' OR t.status IN ('selesai','ditutup') THEN NULL
      ELSE DATE_ADD(NOW(), INTERVAL COALESCE(c.sla_response_hours, 48) HOUR)
    END,
  t.due_at = CASE
      WHEN t.track = 'apresiasi' OR t.status IN ('selesai','ditutup') THEN NULL
      ELSE DATE_ADD(NOW(), INTERVAL COALESCE(c.sla_resolve_hours, 120) HOUR)
    END,
  t.level = 1;

SELECT ticket_no, track, status, level, due_at
FROM feedback_tickets t
WHERE EXISTS (
  SELECT 1 FROM feedback_events e
  WHERE e.ticket_id = t.id AND e.event_type = 'dipindahkan_dari_lama'
)
ORDER BY t.id;
