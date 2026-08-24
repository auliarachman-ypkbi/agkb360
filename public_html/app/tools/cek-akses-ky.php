<?php
/**
 * ============================================================
 * AGKB 360° — Audit akses Kanal Yayasan
 * ------------------------------------------------------------
 * Menjawab satu pertanyaan: SIAPA SAJA yang saat ini bisa
 * membaca tiket berjalur safeguarding, dan lewat jalur mana.
 *
 *   ssh root@... 'docker exec -i ktb_php php \
 *     /var/www/html/app/tools/cek-akses-ky.php'
 *
 * Dibuat 24 Agustus 2026, setelah ditemukan bahwa role
 * foundation dan pemantau membaca laporan perlindungan anak
 * tanpa pernah dimasukkan ke unit Kanal Yayasan.
 *
 * Aturannya sekarang: keanggotaan unit 'Kanal Yayasan', dan
 * hanya itu. Berkas ini memeriksa apakah kode benar-benar
 * menepatinya — bukan membaca ulang niat di komentar, melainkan
 * memanggil fbAllowedTracks() dan fbCanView() yang sesungguhnya
 * dipakai halaman inbox dan halaman tiket.
 *
 * Jalankan setiap kali aturan akses disentuh, dan sesudah
 * menambah atau mencabut anggota unit.
 *
 * Read-only: tidak ada satu pun perintah tulis di sini. Isi
 * laporan tidak pernah dicetak — hanya nomor tiket.
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

/**
 * Sesi CLI kosong. fbCanSeeSafeguarding() dan kawan-kawannya
 * membaca $_SESSION sebagai cadangan bila $u tidak diberikan —
 * dikosongkan supaya tidak ada nilai nyasar yang membuat hasil
 * uji lebih longgar daripada keadaan sebenarnya.
 */
$_SESSION = [];

$garis = str_repeat('─', 78);

// ── Tiket yang dipertaruhkan ────────────────────────────────
$ky = Database::fetchAll(
    "SELECT id, ticket_no, status, assignee_id, category_id, track, level, sender_id, is_test
       FROM feedback_tickets WHERE track = 'safeguarding' ORDER BY id");

if (!$ky) exit("Tidak ada tiket berjalur safeguarding.\n");

// id → nomor tiket, supaya laporan menyebut KY-2026-0010 dan bukan id=40
$nomor = array_column($ky, 'ticket_no', 'id');

// ── Anggota unit: inilah daftar yang seharusnya ─────────────
$anggota = Database::fetchAll(
    "SELECT u.id, u.name, u.role
       FROM user_groups ug
       JOIN `groups` g ON g.id = ug.group_id
       JOIN users u    ON u.id = ug.user_id
      WHERE g.type = 'penanganan' AND g.name = 'Kanal Yayasan'
      ORDER BY u.name");
$idAnggota = array_column($anggota, 'id');

echo "$garis\nANGGOTA UNIT KANAL YAYASAN — daftar yang seharusnya\n$garis\n";
if (!$anggota) {
    echo "  (kosong — berarti tidak seorang pun boleh membaca tiket KY)\n";
} else {
    foreach ($anggota as $a) printf("  %-32s %-12s id=%d\n", $a['name'], $a['role'], $a['id']);
}

// ── Semua akun aktif yang mungkin membuka inbox ─────────────
$kandidat = Database::fetchAll(
    "SELECT id, name, role FROM users
      WHERE is_active = 1
        AND role IN ('superadmin','admin','foundation','leader','staff','teacher','mentor','pemantau')
      ORDER BY FIELD(role,'superadmin','pemantau','foundation','admin','leader'), name");

$bisa = [];
foreach ($kandidat as $u) {
    $lewatInbox = in_array('safeguarding', fbAllowedTracks($u), true);

    // fbCanView() diuji per tiket: sebuah akun bisa saja tertutup
    // di inbox namun tetap menembus lewat tautan langsung, dan
    // justru celah semacam itu yang dicari di sini.
    $lewatTautan = [];
    foreach ($ky as $t) if (fbCanView($t, $u)) $lewatTautan[] = $t['ticket_no'];

    if (!$lewatInbox && !$lewatTautan) continue;

    // Tiga dasar yang sah, dan masing-masing ditandai tersendiri:
    // keanggotaan unit, superadmin (demi pemeliharaan sistem), dan
    // pemberian per tiket. Semuanya tetap tercetak — daftar pembaca
    // laporan perlindungan anak tidak boleh menyembunyikan siapa pun,
    // termasuk yang haknya memang jelas.
    $diberi = array_map(
        fn($id) => $nomor[$id] ?? ('id=' . $id),
        fbTiketKyDiberikan((int)$u['id']));

    $bisa[] = [
        'u'       => $u,
        'inbox'   => $lewatInbox,
        'tautan'  => $lewatTautan,
        'anggota' => in_array((int)$u['id'], array_map('intval', $idAnggota), true),
        'sah'     => $u['role'] === 'superadmin',
        'diberi'  => $diberi,
        // Yang terbaca tanpa dasar apa pun. Inilah kebocorannya.
        'liar'    => array_values(array_diff($lewatTautan, $diberi)),
    ];
}

/**
 * printf() memberi lebar dalam byte, bukan aksara, sehingga satu tanda
 * pisah panjang atau nama beraksen menggeser seluruh kolom di kanannya.
 * Dipadankan sendiri berdasarkan mb_strlen supaya tabelnya tetap lurus.
 */
$kolom = fn(string $s, int $w): string
    => $s . str_repeat(' ', max(1, $w - mb_strlen($s)));

echo "\n$garis\nSIAPA YANG SESUNGGUHNYA BISA MEMBACA " . count($ky) . " TIKET KY\n$garis\n";
echo '  ' . $kolom('NAMA', 28) . $kolom('ROLE', 12) . $kolom('INBOX', 8)
   . $kolom('BACA', 7) . "DASAR\n";

foreach ($bisa as $b) {
    $dasar = $b['anggota'] ? 'anggota unit'
           : ($b['sah']    ? 'superadmin'
           : ($b['diberi'] && !$b['liar']
              ? 'diberi per tiket: ' . implode(', ', $b['diberi'])
              : 'TANPA DASAR'));

    // Cermin dari admin/feedback.php: jalur safeguarding dibuka di
    // inbox bila fbAllowedTracks() memuatnya ATAU ada pemberian per
    // tiket. Tanpa cabang kedua, kolom ini melaporkan '—' untuk orang
    // yang sesungguhnya melihat tiketnya di inbox.
    $inbox = $b['inbox'] ? 'semua'
           : ($b['diberi'] ? count($b['diberi']) . ' tiket' : '-');

    echo '  ' . $kolom(mb_substr($b['u']['name'], 0, 28), 28)
       . $kolom($b['u']['role'], 12)
       . $kolom($inbox, 8)
       . $kolom(count($b['tautan']) . '/' . count($ky), 7)
       . $dasar . "\n";
}

// ── Vonis ───────────────────────────────────────────────────
// Dua arah, dan keduanya penting. Yang bisa membaca tanpa dasar
// adalah kebocoran; yang jadi anggota namun tidak bisa membaca
// berarti tiket KY menganggur tanpa ada yang melihatnya.
$bocor  = array_values(array_filter($bisa,
    fn($b) => !$b['anggota'] && !$b['sah'] && $b['liar']));
$tembus = array_map(
    fn($b) => $b['u']['name'] . ' — ' . implode(', ', $b['liar']),
    $bocor);

$buta = [];
foreach ($anggota as $a) {
    $u = Database::fetchOne("SELECT id, name, role FROM users WHERE id=?", [$a['id']]);
    if ($u && !in_array('safeguarding', fbAllowedTracks($u), true)) $buta[] = $a['name'];
}

echo "\n$garis\nVONIS\n$garis\n";

if ($tembus) {
    echo "  ✗ BOCOR — bisa membaca tanpa dasar apa pun:\n";
    foreach ($tembus as $n) echo "      · $n\n";
} else {
    echo "  ✓ Setiap pembaca tiket KY punya dasar: keanggotaan unit,\n"
       . "    superadmin, atau pemberian per tiket.\n";
}

if ($buta) {
    echo "  ✗ TERTUTUP — anggota unit yang justru tidak melihatnya di inbox:\n";
    foreach ($buta as $n) echo "      · $n\n";
} else {
    echo "  ✓ Seluruh anggota unit melihat tiket KY di inbox mereka.\n";
}

// ── Jalur samping ───────────────────────────────────────────
// Pengintai dan rute eskalasi tidak melewati fbCanView(), jadi
// keduanya diperiksa terpisah: keduanya bisa mengantar isi tiket
// ke orang yang tidak berhak, lewat surel, tanpa pernah membuka
// aplikasinya sama sekali.
$idKy = array_column($ky, 'id');
$ph   = implode(',', array_fill(0, count($idKy), '?'));

$pengintai = Database::fetchAll(
    "SELECT DISTINCT u.id, u.name, u.role FROM feedback_watchers w
       JOIN users u ON u.id = w.user_id
      WHERE w.ticket_id IN ($ph) ORDER BY u.name", $idKy);

// Tabel ini memuat dua macam baris, dan membedakannya penting.
// Baris tanpa user_id maupun email bukan baris rusak: itu penamaan
// level, dibuat migrasi 009, dan tampil di Admin CMS sebagai judul
// rute. fbLevelRecipients() melewatinya karena alamatnya kosong,
// dan fbResolveAssignee() melewatinya karena user_id-nya kosong.
// Menghapusnya menghilangkan nama level itu dari daftar rute.
$rute = Database::fetchAll(
    "SELECT el.id, el.label, el.level, COALESCE(u.name, el.email) AS nama, u.role
       FROM feedback_escalation_levels el
       LEFT JOIN users u ON u.id = el.user_id
      WHERE el.is_active = 1 AND el.track = 'safeguarding'
      ORDER BY el.level, el.order_num");

echo "\n$garis\nJALUR SAMPING — tidak melewati fbCanView()\n$garis\n";

// Pengintai TIDAK dikirimi surel — penerima notifikasi dibangun
// hanya dari fbLevelRecipients() dan fbTembusanTetap(). Tabel ini
// dicetak karena inilah yang memberi akses per tiket, jadi patut
// ditengok berkala supaya pemberian tidak menumpuk tanpa ditinjau.
echo "  Pengintai (feedback_watchers) pada tiket KY — tidak dikirimi surel:\n";
if (!$pengintai) echo "      (tidak ada)\n";
foreach ($pengintai as $w) {
    $ok = in_array((int)$w['id'], array_map('intval', $idAnggota), true)
       || $w['role'] === 'superadmin';
    $diberi = fbTiketKyDiberikan((int)$w['id']);
    printf("      %s %-28s %-11s %s\n",
        $ok || $diberi ? '·' : '✗',
        mb_substr($w['name'], 0, 28),
        $w['role'],
        $ok ? '' : ($diberi
            ? 'diberi: ' . implode(', ', array_map(fn($i) => $nomor[$i] ?? $i, $diberi))
            : 'memantau tetapi TIDAK bisa membaca'));
}

echo "  Penerima notifikasi jalur safeguarding (feedback_escalation_levels):\n";
if (!$rute) echo "      (tidak ada)\n";
foreach ($rute as $r) {
    printf("      %s id=%-3d L%d  %-28s %s\n",
        $r['nama'] ? '·' : ' ',
        $r['id'], $r['level'],
        $r['nama'] ?: '(penamaan level, bukan penerima)',
        $r['nama'] ? ($r['role'] ?? '(alamat lepas, tanpa akun)') : $r['label']);
}

echo "\n  Catatan: fbTembusanTetap() menambahkan pengembang sebagai\n"
   . "  tembusan tetap pada setiap tiket, termasuk KY. Itu hardcode\n"
   . "  di includes/feedback.php dan tidak terbaca dari basis data.\n\n";
