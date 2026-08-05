<?php
/**
 * ============================================================
 * AGKB 360° — Cron: kampanye email terjadwal
 * ------------------------------------------------------------
 * Jalankan sekali sehari, pagi hari kerja:
 *   30 7 * * 1-5 docker exec ktb_php php /var/www/html/app/cron/campaigns.php >> /var/log/agkb-kampanye.log 2>&1
 *
 * Penjadwal ada di sini, bukan di Apps Script, karena yang tahu
 * siapa belum login dan siapa belum pernah mengirim adalah
 * database. Apps Script hanya gerbang pengiriman.
 *
 * Jeda antar kiriman dijaga oleh kampanye masing-masing lewat
 * jeda_hari, jadi menjalankan cron ini tiap hari aman — orang
 * tidak akan menerima email dua hari berturut-turut.
 *
 * Pakai manual:
 *   php cron/campaigns.php --dry              (simulasi semua)
 *   php cron/campaigns.php --dry --only=aktivasi
 *   php cron/campaigns.php --only=aktivasi
 * ============================================================
 */

$isCli = PHP_SAPI === 'cli';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';
require_once __DIR__ . '/../includes/campaigns.php';

if (!$isCli) {
    $token = defined('CRON_TOKEN') ? CRON_TOKEN : '';
    if (!$token || ($_GET['token'] ?? '') !== $token) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$opts    = $isCli ? getopt('', ['dry', 'only::', 'batas::']) : [];
$simulasi = isset($opts['dry']);
$hanya    = $opts['only'] ?? ($_GET['only'] ?? '');
$batas    = (int)($opts['batas'] ?? 400);

$waktu = date('Y-m-d H:i:s');
echo str_repeat('=', 66) . "\n";
echo "AGKB 360° — Kampanye Email   $waktu\n";
echo 'Mode : ' . ($simulasi ? 'SIMULASI (tidak mengirim apa pun)' : 'KIRIM') . "\n";
if (defined('FEEDBACK_DEMO_MODE') && FEEDBACK_DEMO_MODE) {
    echo "⚠  FEEDBACK_DEMO_MODE aktif — email ditahan, hanya dicatat di log.\n";
}
if (!defined('APPS_SCRIPT_URL') || !APPS_SCRIPT_URL) {
    echo "⚠  APPS_SCRIPT_URL tidak diatur — tidak ada email yang benar-benar keluar.\n";
}
echo str_repeat('=', 66) . "\n";

$defs   = kmpDefinisi();
$status = kmpStatus();
$total  = ['terkirim' => 0, 'gagal' => 0, 'dilewati' => 0];

foreach ($defs as $code => $d) {
    if ($hanya && $hanya !== $code) continue;

    $aktif = isset($status[$code]) && (int)$status[$code]['is_active'] === 1;
    $habis = !empty($status[$code]['ends_at']) && strtotime($status[$code]['ends_at']) < time();

    printf("\n── %-28s %s\n", $d['nama'], $aktif ? ($habis ? '[SUDAH BERAKHIR]' : '[aktif]') : '[mati]');

    if (!$simulasi && (!$aktif || $habis)) {
        echo "   dilewati — kampanye tidak aktif\n";
        continue;
    }

    $h = kmpJalankan($code, $simulasi, $batas);
    if (isset($h['error'])) { echo '   ERROR: ' . $h['error'] . "\n"; continue; }

    printf("   sasaran %d · %s %d · gagal %d · dilewati %d (belum waktunya / sudah cukup)\n",
        $h['sasaran'],
        $simulasi ? 'akan dikirim' : 'terkirim',
        $h['terkirim'], $h['gagal'], $h['dilewati']);

    if ($simulasi && $h['daftar']) {
        $n = min(8, count($h['daftar']));
        for ($i = 0; $i < $n; $i++) echo '     · ' . $h['daftar'][$i] . "\n";
        if (count($h['daftar']) > $n) echo '     … dan ' . (count($h['daftar']) - $n) . " orang lagi\n";
    }

    $total['terkirim'] += $h['terkirim'];
    $total['gagal']    += $h['gagal'];
    $total['dilewati'] += $h['dilewati'];
}

echo "\n" . str_repeat('-', 66) . "\n";
printf("TOTAL — %s %d · gagal %d · dilewati %d\n",
    $simulasi ? 'akan dikirim' : 'terkirim',
    $total['terkirim'], $total['gagal'], $total['dilewati']);
echo str_repeat('=', 66) . "\n";
