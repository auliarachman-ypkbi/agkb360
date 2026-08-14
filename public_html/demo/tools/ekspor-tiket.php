<?php
/**
 * ============================================================
 * AGKB 360° — Ekspor Tiket ke CSV
 * ------------------------------------------------------------
 * Menulis CSV ke keluaran standar, sehingga dapat langsung
 * ditarik ke komputer sendiri tanpa perlu menyalin berkas:
 *
 *   ssh root@... 'docker exec -i ktb_php php \
 *     /var/www/html/app/tools/ekspor-tiket.php' > tiket.csv
 *
 * Keterangan proses ditulis ke keluaran galat, bukan ke CSV-nya,
 * supaya berkasnya tetap bersih.
 *
 * ── Identitas ───────────────────────────────────────────────
 * Pelapor yang memilih anonim TETAP anonim pada hasil ekspor,
 * meski dijalankan oleh pengelola sistem. Kerahasiaan itu yang
 * dijanjikan kepadanya saat mengirim, dan berkas CSV mudah
 * berpindah tangan. Buka hanya bila memang diperlukan, dengan
 * --buka-identitas, dan sadari bahwa itu keputusan tersendiri.
 *
 * Pakai:
 *   php tools/ekspor-tiket.php                        semua tiket
 *   php tools/ekspor-tiket.php --dari=2026-01-01
 *   php tools/ekspor-tiket.php --jalur=inquiry
 *   php tools/ekspor-tiket.php --tanpa-kanal-yayasan
 *   php tools/ekspor-tiket.php --dengan-isi           sertakan isi laporan
 *   php tools/ekspor-tiket.php --pesan                ekspor balasan, bukan tiket
 * ============================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Hanya dapat dijalankan lewat baris perintah.');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';

$o = getopt('', [
    'dari::', 'sampai::', 'jalur::', 'tanpa-kanal-yayasan',
    'dengan-isi', 'buka-identitas', 'sertakan-uji', 'pesan',
]);

$dari      = $o['dari']   ?? '';
$sampai    = $o['sampai'] ?? '';
$jalur     = $o['jalur']  ?? '';
$tanpaKY   = isset($o['tanpa-kanal-yayasan']);
$denganIsi = isset($o['dengan-isi']);
$bukaId    = isset($o['buka-identitas']);
$adaUji    = isset($o['sertakan-uji']);
$modePesan = isset($o['pesan']);

$w = ['1=1'];
$p = [];

if ($dari)   { $w[] = 't.created_at >= ?'; $p[] = $dari . ' 00:00:00'; }
if ($sampai) { $w[] = 't.created_at <= ?'; $p[] = $sampai . ' 23:59:59'; }
if ($jalur)  { $w[] = 't.track = ?';       $p[] = $jalur; }
if ($tanpaKY){ $w[] = "t.track <> 'safeguarding'"; }
if (!$adaUji){ $w[] = 't.is_test = 0'; }

$where = implode(' AND ', $w);

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");   // BOM, supaya Excel membaca UTF-8

$L = fbStatuses();
$T = fbTracks();
$R = fbResolutions();

/** Nama pelapor sesuai aturan anonimitas. */
function pelapor(array $t, bool $buka): array {
    if (!empty($t['is_anonymous']) && !$buka) {
        return ['Pelapor Anonim', '', 'anonim'];
    }
    if (!empty($t['sender_name'])) {
        return [$t['sender_name'], (string)($t['sender_email'] ?? ''), 'pengguna'];
    }
    if (!empty($t['guest_name'])) {
        return [$t['guest_name'], (string)($t['guest_email'] ?? ''), 'publik'];
    }
    return ['—', '', 'tidak diketahui'];
}

// ── Mode pesan: balasan dan catatan ─────────────────────────

if ($modePesan) {
    fputcsv($out, ['Nomor tiket', 'Jalur', 'Kategori', 'Waktu', 'Penulis',
                   'Sifat', 'Isi']);

    $baris = Database::fetchAll("
        SELECT t.ticket_no, t.track, t.is_anonymous,
               COALESCE(c.name,'—') AS kategori,
               m.created_at, m.visibility, m.body, m.is_system,
               COALESCE(u.name,'Sistem') AS penulis
        FROM feedback_messages m
        JOIN feedback_tickets t ON t.id = m.ticket_id
        LEFT JOIN feedback_categories c ON c.id = t.category_id
        LEFT JOIN users u ON u.id = m.author_id
        WHERE $where
        ORDER BY t.ticket_no, m.created_at", $p);

    foreach ($baris as $b) {
        fputcsv($out, [
            $b['ticket_no'],
            $T[$b['track']]['label'] ?? $b['track'],
            $b['kategori'],
            $b['created_at'],
            $b['is_system'] ? 'Sistem' : $b['penulis'],
            $b['visibility'] === 'internal' ? 'Catatan internal' : 'Balasan ke pelapor',
            preg_replace('/\s+/u', ' ', (string)$b['body']),
        ]);
    }
    fclose($out);
    fwrite(STDERR, count($baris) . " baris pesan diekspor.\n");
    exit;
}

// ── Mode tiket ──────────────────────────────────────────────

$judul = ['Nomor tiket', 'Jalur', 'Kategori', 'Status', 'Prioritas', 'Level',
          'Pelapor', 'Email pelapor', 'Asal pelapor', 'Hubungan',
          'Subjek', 'Penanggung jawab', 'Unit penanganan',
          'Masuk', 'Batas waktu', 'Respons pertama', 'Diselesaikan',
          'Jam sampai respons', 'Jam sampai selesai', 'Tepat waktu',
          'Jenis penyelesaian', 'Keterangan penyelesaian',
          'Jumlah balasan', 'Jumlah lampiran', 'Uji coba'];
if ($denganIsi) $judul[] = 'Isi laporan';

fputcsv($out, $judul);

$baris = Database::fetchAll("
    SELECT t.*,
           COALESCE(c.name,'—') AS kategori,
           g.name  AS unit,
           s.name  AS sender_name, s.email AS sender_email,
           a.name  AS penanggung_jawab,
           (SELECT COUNT(*) FROM feedback_messages m
             WHERE m.ticket_id = t.id AND m.is_system = 0) AS jml_pesan,
           (SELECT COUNT(*) FROM feedback_attachments f
             WHERE f.ticket_id = t.id) AS jml_lampiran
    FROM feedback_tickets t
    LEFT JOIN feedback_categories c ON c.id = t.category_id
    LEFT JOIN `groups` g ON g.id = c.handler_group_id
    LEFT JOIN users s ON s.id = t.sender_id
    LEFT JOIN users a ON a.id = t.assignee_id
    WHERE $where
    ORDER BY t.created_at", $p);

$jam = function (?string $a, ?string $b): string {
    if (!$a || !$b) return '';
    return (string)round((strtotime($b) - strtotime($a)) / 3600, 1);
};

foreach ($baris as $t) {
    [$nama, $email, $asal] = pelapor($t, $bukaId);

    $tepat = '';
    if ($t['resolved_at'] && $t['due_at']) {
        $tepat = strtotime($t['resolved_at']) <= strtotime($t['due_at']) ? 'Ya' : 'Tidak';
    }

    $r = [
        $t['ticket_no'],
        $T[$t['track']]['label'] ?? $t['track'],
        $t['kategori'],
        $L[$t['status']]['label'] ?? $t['status'],
        $t['priority'],
        $t['level'],
        $nama,
        $bukaId || empty($t['is_anonymous']) ? $email : '',
        $asal,
        $t['guest_role'] ?? '',
        $t['subject'],
        $t['penanggung_jawab'] ?? 'Belum ditugaskan',
        $t['unit'] ?? '—',
        $t['created_at'],
        $t['due_at'] ?? '',
        $t['first_response_at'] ?? '',
        $t['resolved_at'] ?? '',
        $jam($t['created_at'], $t['first_response_at']),
        $jam($t['created_at'], $t['resolved_at']),
        $tepat,
        $t['resolution_type'] ? ($R[$t['resolution_type']] ?? $t['resolution_type']) : '',
        preg_replace('/\s+/u', ' ', (string)($t['resolution_note'] ?? '')),
        (int)$t['jml_pesan'],
        (int)$t['jml_lampiran'],
        $t['is_test'] ? 'Ya' : 'Tidak',
    ];
    if ($denganIsi) $r[] = preg_replace('/\s+/u', ' ', (string)$t['message']);

    fputcsv($out, $r);
}

fclose($out);

$anon = count(array_filter($baris, fn($t) => !empty($t['is_anonymous'])));
fwrite(STDERR, count($baris) . " tiket diekspor.\n");
if ($anon && !$bukaId) {
    fwrite(STDERR, "$anon di antaranya anonim; nama dan emailnya tidak disertakan.\n");
}
