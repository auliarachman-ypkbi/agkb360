<?php
/**
 * ============================================================
 * AGKB 360° — API Tiket Feedback (baca saja)
 * ------------------------------------------------------------
 * Menyajikan tiket sebagai JSON untuk diambil Google Apps Script,
 * lalu disimpan ke shared drive. Dari sana Claude membacanya.
 *
 *   GET /app/api/tiket.php?token=...&dari=2026-08-12&sampai=2026-08-19
 *
 * Parameter:
 *   token    wajib. Disimpan di config.php sebagai API_TOKEN_TIKET.
 *   dari     wajib. YYYY-MM-DD.
 *   sampai   wajib. YYYY-MM-DD.
 *   jalur    opsional: inquiry | apresiasi | safeguarding
 *
 * ── Batas yang disengaja ────────────────────────────────────
 * Rentang tanggal WAJIB dan dibatasi 400 hari. Tanpa itu, satu
 * permintaan bisa menarik seluruh basis data sekaligus — dan
 * token yang bocor jadi jauh lebih mahal akibatnya.
 *
 * ── Identitas ───────────────────────────────────────────────
 * Pelapor anonim TETAP anonim. Tidak ada parameter untuk
 * membukanya di sini; itu hanya tersedia lewat baris perintah
 * di server, sebagai keputusan tersendiri yang disadari.
 * ============================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

/** Berhenti dengan pesan galat yang seragam. */
function tolak(int $kode, string $pesan): never {
    http_response_code($kode);
    echo json_encode(['ok' => false, 'pesan' => $pesan],
                     JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── Token ───────────────────────────────────────────────────
// Dibandingkan dengan hash_equals, bukan ===, supaya lama
// perbandingannya tidak bergantung pada berapa karakter yang
// cocok — menutup celah menebak token dari selisih waktu.
if (!defined('API_TOKEN_TIKET') || API_TOKEN_TIKET === '') {
    tolak(503, 'API belum diaktifkan pada server ini.');
}
$token = (string)($_GET['token'] ?? $_SERVER['HTTP_X_API_TOKEN'] ?? '');
if (!hash_equals(API_TOKEN_TIKET, $token)) {
    error_log('API tiket: token salah dari ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    tolak(403, 'Token tidak dikenali.');
}

// ── Rentang ─────────────────────────────────────────────────
$sah = fn(string $t): bool =>
    (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) && strtotime($t) !== false;

$dari   = (string)($_GET['dari']   ?? '');
$sampai = (string)($_GET['sampai'] ?? '');

if (!$sah($dari) || !$sah($sampai)) {
    tolak(400, 'Parameter dari dan sampai wajib diisi, format YYYY-MM-DD.');
}
if (strtotime($dari) > strtotime($sampai)) {
    tolak(400, 'Tanggal mulai melewati tanggal akhir.');
}
if ((strtotime($sampai) - strtotime($dari)) / 86400 > 400) {
    tolak(400, 'Rentang maksimal 400 hari.');
}

$jalur = (string)($_GET['jalur'] ?? '');
if ($jalur !== '' && !in_array($jalur, ['inquiry', 'apresiasi', 'safeguarding'], true)) {
    tolak(400, 'Nilai jalur tidak dikenali.');
}

// ── Kueri ───────────────────────────────────────────────────
$w = ['t.is_test = 0', 't.created_at >= ?', 't.created_at <= ?'];
$p = [$dari . ' 00:00:00', $sampai . ' 23:59:59'];
if ($jalur !== '') { $w[] = 't.track = ?'; $p[] = $jalur; }

$baris = Database::fetchAll("
    SELECT t.*,
           COALESCE(c.name,'—') AS kategori,
           g.name AS unit,
           s.name AS sender_name,
           a.name AS penanggung_jawab,
           (SELECT COUNT(*) FROM feedback_messages m
             WHERE m.ticket_id = t.id AND m.is_system = 0) AS jml_pesan,
           (SELECT COUNT(*) FROM feedback_attachments f
             WHERE f.ticket_id = t.id) AS jml_lampiran
    FROM feedback_tickets t
    LEFT JOIN feedback_categories c ON c.id = t.category_id
    LEFT JOIN `groups` g ON g.id = c.handler_group_id
    LEFT JOIN users s ON s.id = t.sender_id
    LEFT JOIN users a ON a.id = t.assignee_id
    WHERE " . implode(' AND ', $w) . "
    ORDER BY t.created_at", $p);

$L = fbStatuses();
$T = fbTracks();
$R = fbResolutions();

$jam = function (?string $a, ?string $b): ?float {
    if (!$a || !$b) return null;
    return round((strtotime($b) - strtotime($a)) / 3600, 1);
};

/** Nama dan asal pelapor, dengan anonimitas dijaga. */
$pelapor = function (array $t): array {
    if (!empty($t['is_anonymous']))  return ['Pelapor Anonim', 'anonim'];
    if (!empty($t['sender_name']))   return [$t['sender_name'], 'pengguna'];
    if (!empty($t['guest_name']))    return [$t['guest_name'],  'publik'];
    return ['—', 'tidak diketahui'];
};

$tiket = [];
foreach ($baris as $t) {
    [$nama, $asal] = $pelapor($t);

    $tepat = null;
    if ($t['resolved_at'] && $t['due_at']) {
        $tepat = strtotime($t['resolved_at']) <= strtotime($t['due_at']);
    }

    $tiket[] = [
        'nomor'             => $t['ticket_no'],
        'jalur'             => $T[$t['track']]['label'] ?? $t['track'],
        'jalur_kode'        => $t['track'],
        'kategori'          => $t['kategori'],
        'status'            => $L[$t['status']]['label'] ?? $t['status'],
        'status_kode'       => $t['status'],
        'prioritas'         => $t['priority'],
        'level'             => (int)$t['level'],
        'pelapor'           => $nama,
        'asal'              => $asal,
        'hubungan'          => $t['guest_role'] ?? null,
        'subjek'            => $t['subject'],
        'isi'               => preg_replace('/\s+/u', ' ', (string)$t['message']),
        'penanggung_jawab'  => $t['penanggung_jawab'] ?? null,
        'unit'              => $t['unit'] ?? null,
        'masuk'             => $t['created_at'],
        'batas_waktu'       => $t['due_at'] ?? null,
        'respons_pertama'   => $t['first_response_at'] ?? null,
        'diselesaikan'      => $t['resolved_at'] ?? null,
        'jam_ke_respons'    => $jam($t['created_at'], $t['first_response_at']),
        'jam_ke_selesai'    => $jam($t['created_at'], $t['resolved_at']),
        'tepat_waktu'       => $tepat,
        'jenis_penyelesaian'=> $t['resolution_type']
                                 ? ($R[$t['resolution_type']] ?? $t['resolution_type'])
                                 : null,
        'catatan_penyelesaian' => $t['resolution_note']
                                 ? preg_replace('/\s+/u', ' ', (string)$t['resolution_note'])
                                 : null,
        'jumlah_balasan'    => (int)$t['jml_pesan'],
        'jumlah_lampiran'   => (int)$t['jml_lampiran'],
    ];
}

echo json_encode([
    'ok'      => true,
    'ditarik' => date('c'),
    'rentang' => ['dari' => $dari, 'sampai' => $sampai],
    'jalur'   => $jalur ?: 'semua',
    'jumlah'  => count($tiket),
    'catatan' => 'Identitas pelapor anonim tidak dibuka pada keluaran ini.',
    'tiket'   => $tiket,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
