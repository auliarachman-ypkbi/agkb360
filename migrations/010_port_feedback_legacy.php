<?php
// ============================================================
// AGKB 360° — Pindahkan data feedback lama ke struktur tiket baru
// ------------------------------------------------------------
// Membaca `feedback_legacy` (hasil rename dari tabel `feedback` lama)
// lalu membuat tiket setara di `feedback_tickets`.
//
// Aman dijalankan berulang: baris yang sudah dipindahkan dilewati,
// dikenali lewat catatan di feedback_events.
//
// Jalankan:
//   docker exec ktb_php php /var/www/html/../migrations/010_port_feedback_legacy.php
// atau dari dalam folder app:
//   docker exec ktb_php php /var/www/html/app/tools/port_legacy.php
//
// Tambahkan --dry untuk melihat rencananya tanpa menulis apa pun.
// ============================================================

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Hanya lewat CLI.\n"); }

$appDir = __DIR__ . '/../public_html/app';
if (!is_dir($appDir)) $appDir = '/var/www/html/app';

require_once $appDir . '/config/config.php';
require_once $appDir . '/includes/db.php';
require_once $appDir . '/includes/functions.php';
require_once $appDir . '/includes/feedback.php';

$dry = in_array('--dry', $argv ?? [], true);
echo "Database : " . DB_NAME . "\n";
echo "Mode     : " . ($dry ? "SIMULASI (tidak menulis apa pun)" : "TULIS") . "\n\n";

// ── Pastikan tabel sumber ada ───────────────────────────────
$exists = Database::fetchOne("SHOW TABLES LIKE 'feedback_legacy'");
if (!$exists) exit("Tabel feedback_legacy tidak ada. Tidak ada yang perlu dipindahkan.\n");

$rows = Database::fetchAll("SELECT * FROM feedback_legacy ORDER BY id");
if (!$rows) exit("feedback_legacy kosong. Selesai.\n");
echo "Ditemukan " . count($rows) . " baris di feedback_legacy.\n\n";

// ── Kategori tujuan ─────────────────────────────────────────
$catApr = Database::fetchOne("SELECT * FROM feedback_categories WHERE code='apr_umum'");
$catInq = Database::fetchOne("SELECT * FROM feedback_categories WHERE code='inq_lain'");
if (!$catApr || !$catInq) exit("Kategori bawaan belum ada. Jalankan migration 009 dulu.\n");

$ported = 0; $skipped = 0;

foreach ($rows as $r) {
    // Sudah pernah dipindahkan?
    $seen = Database::fetchOne(
        "SELECT 1 FROM feedback_events WHERE event_type='dipindahkan_dari_lama' AND to_value=?",
        ['feedback_legacy#' . $r['id']]);
    if ($seen) { $skipped++; continue; }

    $isApr = ($r['type'] ?? '') === 'appreciation';
    $cat   = $isApr ? $catApr : $catInq;
    $track = $cat['track'];

    $responded = ($r['status'] ?? '') === 'responded' && !empty($r['admin_response']);
    $status    = $responded ? 'selesai' : 'baru';

    $created  = $r['created_at']   ?: date('Y-m-d H:i:s');
    $resolved = $responded ? ($r['responded_at'] ?: $created) : null;

    // Jam SLA dihitung dari SEKARANG, bukan dari tanggal asli.
    // Kalau dihitung mundur, tiket lama langsung tercatat terlambat berbulan-bulan
    // lalu memicu eskalasi otomatis beruntun beserta emailnya.
    $due = ($track === 'apresiasi' || $responded) ? null
         : date('Y-m-d H:i:s', time() + (int)$cat['sla_resolve_hours'] * 3600);
    $respDue = ($track === 'apresiasi' || $responded) ? null
         : date('Y-m-d H:i:s', time() + (int)$cat['sla_response_hours'] * 3600);

    echo sprintf("  #%-4s %-11s %-9s %s\n",
        $r['id'], $isApr ? 'apresiasi' : 'inquiry', $status,
        mb_substr($r['subject'] ?? '(tanpa subjek)', 0, 52));

    if ($dry) { $ported++; continue; }

    $tid = Database::insert('feedback_tickets', [
        'ticket_no'        => fbGenerateTicketNo($track),
        'track'            => $track,
        'category_id'      => $cat['id'],
        'sender_id'        => (int)$r['sender_id'],
        'subject'          => mb_substr($r['subject'] ?? '(tanpa subjek)', 0, 255),
        'message'          => $r['message'] ?? '',
        'impact'           => $track === 'inquiry' ? 'individu' : null,
        'priority'         => $isApr ? 'P4' : 'P3',
        'status'           => $status,
        'level'            => 1,
        'assignee_id'      => $r['responded_by'] ? (int)$r['responded_by'] : $cat['default_pic_id'],
        'due_at'           => $due,
        'response_due_at'  => $respDue,
        'first_response_at'=> $resolved,
        'resolved_at'      => $resolved,
        'resolution_type'  => $responded ? 'diselesaikan' : null,
        'resolution_note'  => $responded ? $r['admin_response'] : null,
        'resolved_by'      => $r['responded_by'] ? (int)$r['responded_by'] : null,
        'is_test'          => 0,
        'created_at'       => $created,
    ]);

    Database::insert('feedback_events', [
        'ticket_id'  => $tid,
        'actor_id'   => null,
        'event_type' => 'dipindahkan_dari_lama',
        'from_value' => 'feedback (struktur lama)',
        'to_value'   => 'feedback_legacy#' . $r['id'],
        'note'       => 'Dipindahkan otomatis oleh migration 010',
        'created_at' => $created,
    ]);

    if ($responded) {
        Database::insert('feedback_messages', [
            'ticket_id'  => $tid,
            'author_id'  => $r['responded_by'] ? (int)$r['responded_by'] : null,
            'body'       => $r['admin_response'],
            'visibility' => 'publik',
            'created_at' => $resolved,
        ]);
    }
    $ported++;
}

echo "\n";
echo $dry
    ? "SIMULASI selesai. $ported baris siap dipindahkan, $skipped sudah pernah dipindahkan.\n"
      . "Jalankan ulang tanpa --dry untuk benar-benar memindahkan.\n"
    : "Selesai. $ported tiket dibuat, $skipped dilewati (sudah ada).\n"
      . "feedback_legacy TIDAK dihapus — biarkan sebagai arsip.\n";
