<?php
/**
 * ============================================================
 * AGKB 360° — Kirim Ulang Notifikasi Tiket
 * ------------------------------------------------------------
 * Menyusun ulang email notifikasi "Tiket Baru" untuk satu tiket
 * yang sudah ada, lalu mengirimkannya ke alamat yang disebutkan.
 *
 * Dipakai ketika notifikasi aslinya tidak sampai dan penyebabnya
 * belum diketahui — isi tiketnya tidak berubah, hanya emailnya
 * yang dikirim ulang.
 *
 * HANYA lewat baris perintah. Berkas ini berada di dalam webroot,
 * jadi penjagaan CLI di bawah bukan basa-basi: tanpa itu, siapa
 * pun yang menebak alamatnya bisa memicu pengiriman email.
 *
 * Pakai:
 *   php tools/kirim-ulang-notifikasi.php --tiket=KY-2026-0002 --ke=a@b.c,d@e.f
 *   php tools/kirim-ulang-notifikasi.php --tiket=KY-2026-0002 --ke=... --dry
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

$opts   = getopt('', ['tiket:', 'ke:', 'dry']);
$noTiket = trim($opts['tiket'] ?? '');
$simulasi = isset($opts['dry']);

if ($noTiket === '' || empty($opts['ke'])) {
    fwrite(STDERR, "Wajib: --tiket=NOMOR --ke=alamat1,alamat2\n");
    exit(1);
}

$tujuan = [];
foreach (explode(',', (string)$opts['ke']) as $m) {
    $m = trim($m);
    if (filter_var($m, FILTER_VALIDATE_EMAIL)) $tujuan[] = $m;
    else fwrite(STDERR, "Dilewati, bukan alamat sah: $m\n");
}
if (!$tujuan) { fwrite(STDERR, "Tidak ada alamat yang sah.\n"); exit(1); }

$baris = Database::fetchOne(
    "SELECT id FROM feedback_tickets WHERE ticket_no = ?", [$noTiket]);
if (!$baris) { fwrite(STDERR, "Tiket tidak ditemukan: $noTiket\n"); exit(1); }

$t = fbLoadFull((int)$baris['id']);

echo str_repeat('=', 66) . "\n";
echo "Kirim ulang notifikasi — {$t['ticket_no']}\n";
echo 'Jalur   : ' . (fbTracks()[$t['track']]['label'] ?? $t['track']) . "\n";
echo 'Subjek  : ' . $t['subject'] . "\n";
echo 'Status  : ' . $t['status'] . "\n";
echo 'Masuk   : ' . $t['created_at'] . "\n";
echo 'Mode    : ' . ($simulasi ? 'SIMULASI' : 'KIRIM') . "\n";
echo str_repeat('=', 66) . "\n";

// Susunan badan email sengaja disamakan dengan fbNotifyNew(),
// supaya yang diterima persis seperti yang seharusnya diterima.
$link = fbAppUrl() . '/admin/ticket.php?id=' . $t['id'];
$isSg = $t['track'] === 'safeguarding';
$who  = $t['is_anonymous']
      ? 'Pelapor Anonim'
      : ($t['sender_name'] ?? ($t['guest_name'] ?: '—'));

$body = ($isSg
    ? '<div style="background:#fdeceb;border:1px solid #f3b5b0;border-radius:8px;padding:12px 14px;color:#b42318;font-weight:600;margin-bottom:12px">Kanal Yayasan — memerlukan tindak lanjut dalam 24 jam.</div>'
    : '')
  . '<p style="margin:0 0 6px"><strong>Kiriman ulang.</strong> Notifikasi ini disusun ulang '
  . 'dari tiket yang sudah ada karena pemberitahuan sebelumnya tidak sampai.</p>'
  . '<p style="margin:0 0 6px">Tiket dari <strong>' . h($who) . '</strong>.</p>'
  . fbTicketMetaHtml($t)
  . '<div style="font-size:11px;font-weight:600;color:#6b6a83;text-transform:uppercase;letter-spacing:.5px;margin:14px 0 6px">Subjek</div>'
  . '<div style="font-size:15px;font-weight:600;color:#040136">' . h($t['subject']) . '</div>'
  . '<div style="font-size:14px;color:#2f2d4d;line-height:1.8;background:#fafafb;border-radius:8px;padding:14px;border-left:3px solid #040136;margin-top:10px">'
  . nl2br(h(mb_substr($t['message'], 0, 1500))) . '</div>';

$subjek = ($isSg ? '[AGKB 360° · PRIORITAS] ' : '[AGKB 360°] ')
        . $t['ticket_no'] . ' — ' . $t['subject'];
$html = fbMailTemplate('Tiket ' . $t['ticket_no'], $body, $link, 'Buka Tiket',
                       $isSg ? '#b42318' : '#ff9101');

$berhasil = 0; $gagal = 0;
foreach ($tujuan as $m) {
    if ($simulasi) { echo "  akan dikirim ke $m\n"; continue; }
    $ok = fbSendMail($m, $subjek, $html, 'kirim_ulang');
    printf("  %-42s %s\n", $m, $ok ? 'terkirim' : 'GAGAL');
    $ok ? $berhasil++ : $gagal++;
}

if (!$simulasi) {
    fbLogEvent((int)$t['id'], 'diteruskan', null, implode(', ', $tujuan),
        'Notifikasi dikirim ulang lewat baris perintah');
    echo str_repeat('-', 66) . "\n";
    echo "Terkirim $berhasil · gagal $gagal\n";
    echo "Tercatat di riwayat tiket dan di log email (kirim_ulang).\n";
}
echo str_repeat('=', 66) . "\n";
