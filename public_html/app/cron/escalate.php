<?php
// ============================================================
// AGKB 360° — Cron: eskalasi otomatis tiket yang lewat SLA
// ------------------------------------------------------------
// Jalankan tiap jam:
//   0 * * * * docker exec ktb_php php /var/www/html/app/cron/escalate.php >> /var/log/agkb-escalate.log 2>&1
//
// Bisa juga dipanggil lewat HTTP dengan token:
//   https://agkb360.app/app/cron/escalate.php?token=...
// (isi CRON_TOKEN di config.php untuk mengaktifkan jalur HTTP)
// ============================================================

$isCli = PHP_SAPI === 'cli';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';

if (!$isCli) {
    $token = defined('CRON_TOKEN') ? CRON_TOKEN : '';
    if (!$token || ($_GET['token'] ?? '') !== $token) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$start = microtime(true);
$n = fbRunAutoEscalation(200);

// Peringatan dini: tiket yang sudah melewati 75% masa SLA tapi belum direspons
$soon = Database::fetchAll(
    "SELECT id, ticket_no, subject FROM feedback_tickets
     WHERE status IN ('baru','ditinjau')
       AND is_test = 0
       AND response_due_at IS NOT NULL
       AND response_due_at < NOW()
       AND first_response_at IS NULL
     LIMIT 100");

printf("[%s] Eskalasi otomatis: %d tiket naik level. Belum direspons melewati batas: %d. (%.2fs)\n",
    date('Y-m-d H:i:s'), $n, count($soon), microtime(true) - $start);
