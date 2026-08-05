<?php
// AGKB 360° — Unduh lampiran terkendali
// Berkas TIDAK pernah diakses langsung; selalu lewat pemeriksaan izin di sini.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';

requireLogin();
$user = currentUser();

$id = (int)($_GET['id'] ?? 0);
$a  = $id ? Database::fetchOne("SELECT * FROM feedback_attachments WHERE id=?", [$id]) : null;
if (!$a) { http_response_code(404); exit('Berkas tidak ditemukan.'); }

$t = fbLoadFull((int)$a['ticket_id']);
if (!$t || !fbCanView($t, $user)) {
    http_response_code(403);
    exit('Anda tidak memiliki akses ke berkas ini.');
}

$path = fbUploadDir() . '/' . basename($a['stored_name']);
if (!is_file($path)) { http_response_code(404); exit('Berkas tidak ditemukan di penyimpanan.'); }

// Setiap pengunduhan tercatat — wajib untuk lampiran disegel
fbLogEvent((int)$a['ticket_id'], 'lampiran_diunduh', null, $a['original_name']);

$name = preg_replace('/[^\w\.\- ]+/u', '_', $a['original_name']);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'');
header('Cache-Control: private, no-store');
header('Pragma: no-cache');

readfile($path);
exit;
