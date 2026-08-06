<?php
// AGKB 360° — Jalur publik (feedback tanpa login & pengajuan akun)
//
// Semua yang di sini dipakai oleh halaman di /publik/ yang TIDAK
// memanggil requireLogin(). Karena itu tidak boleh ada satu pun
// fungsi di berkas ini yang mengandaikan adanya sesi pengguna.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/feedback.php';

// Berapa lama tautan pelacakan berlaku. Sengaja panjang: tiket
// safeguarding bisa berbulan-bulan, dan pelapor tamu tidak punya
// cara lain untuk masuk kembali.
const PUB_TOKEN_TTL_DAYS = 180;

// Ambang pembatasan laju, per alamat IP.
const PUB_RATE_PER_HOUR = 3;
const PUB_RATE_PER_DAY  = 8;

// ── CSRF ────────────────────────────────────────────────────

/**
 * Versi verifyCsrf() yang tidak mematikan permintaan.
 *
 * verifyCsrf() membalas JSON lalu die() — pantas untuk API, tapi
 * merusak untuk form publik: pengunjung melihat teks mentah dan
 * kehilangan seluruh ketikannya. Di sini kegagalan cukup dilaporkan
 * sebagai pesan, sehingga halaman dapat menampilkannya kembali
 * lengkap dengan isian yang sudah diketik.
 *
 * Penyebab paling sering bukan serangan, melainkan sesi yang habis
 * atau server yang di-restart saat form dibiarkan terbuka lama.
 */
function pubCsrfSah(): bool {
    startSession();
    $dikirim = $_POST['csrf_token'] ?? '';
    $simpan  = $_SESSION['csrf_token'] ?? '';
    return $simpan !== '' && hash_equals($simpan, $dikirim);
}

function pubPesanCsrf(): string {
    return 'Sesi Anda kedaluwarsa sebelum laporan terkirim — biasanya karena halaman '
         . 'dibiarkan terbuka terlalu lama. Isian Anda masih utuh di bawah; '
         . 'silakan tekan Kirim sekali lagi.';
}

// ── IP ──────────────────────────────────────────────────────

/**
 * IP pengirim dalam bentuk biner, siap masuk kolom VARBINARY(16).
 * Mengembalikan null kalau tidak terbaca — pembatasan laju lalu
 * jatuh ke mode longgar, bukan memblokir orang yang sah.
 */
function pubClientIpBin(): ?string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // Di belakang nginx, IP asli ada di X-Forwarded-For. Header ini
    // bisa dipalsukan oleh klien, jadi hanya dipercaya kalau memang
    // ada proxy di depan — ditandai lewat konfigurasi.
    if (defined('TRUST_PROXY') && TRUST_PROXY && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip    = trim($parts[0]);
    }

    $bin = @inet_pton($ip);
    return $bin === false ? null : $bin;
}

function pubIpText(?string $bin): string {
    if ($bin === null || $bin === '') return '—';
    $s = @inet_ntop($bin);
    return $s === false ? '—' : $s;
}

// ── PEMBATASAN LAJU ─────────────────────────────────────────

/**
 * Apakah IP ini sudah terlalu sering mengirim.
 * Mengembalikan pesan kalau ditolak, null kalau boleh lanjut.
 *
 * Sengaja dihitung dari tiket yang benar-benar tersimpan, bukan
 * dari tabel penghitung terpisah, supaya tidak ada state tambahan
 * yang perlu dibersihkan.
 */
function pubCekLaju(?string $ipBin): ?string {
    if ($ipBin === null) return null;

    $jam = (int)Database::fetchOne(
        "SELECT COUNT(*) AS n FROM feedback_tickets
          WHERE guest_ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        [$ipBin])['n'];

    if ($jam >= PUB_RATE_PER_HOUR) {
        return 'Anda sudah mengirim beberapa laporan dalam satu jam terakhir. '
             . 'Silakan coba lagi nanti. Kalau ini mendesak, hubungi sekolah secara langsung.';
    }

    $hari = (int)Database::fetchOne(
        "SELECT COUNT(*) AS n FROM feedback_tickets
          WHERE guest_ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)",
        [$ipBin])['n'];

    if ($hari >= PUB_RATE_PER_DAY) {
        return 'Batas pengiriman harian dari perangkat ini sudah tercapai. '
             . 'Silakan coba lagi besok, atau hubungi sekolah secara langsung.';
    }

    return null;
}

// ── TOKEN PELACAKAN ─────────────────────────────────────────

function pubBuatToken(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Tiket tamu berdasarkan token, sekaligus memeriksa masa berlaku.
 * Dipakai halaman pelacakan. Sengaja hanya mengambil kolom yang
 * aman ditampilkan ke publik — isi pesan dan lampiran TIDAK ikut.
 */
function pubTiketDariToken(string $token): ?array {
    if (strlen($token) !== 64 || !ctype_xdigit($token)) return null;

    // Masa berlaku disisipkan sebagai angka, bukan parameter: sebagian
    // versi MySQL menolak placeholder di dalam INTERVAL. Nilainya
    // konstanta di kode, jadi tidak ada masukan pengguna di sini.
    $ttl = (int)PUB_TOKEN_TTL_DAYS;

    $t = Database::fetchOne(
        "SELECT id, ticket_no, track, status, subject, created_at, updated_at,
                due_at, resolved_at, closed_at, guest_name, guest_email
           FROM feedback_tickets
          WHERE guest_token = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL $ttl DAY)",
        [$token]);

    return $t ?: null;
}

function pubUrlLacak(string $token): string {
    return fbAppUrl() . '/publik/lacak.php?t=' . $token;
}

// ── BUAT TIKET TAMU ─────────────────────────────────────────

/**
 * Membungkus fbCreateTicket untuk pelapor tamu. Seluruh logika
 * prioritas, SLA, rute unit, dan eskalasi tetap milik fbCreateTicket
 * — di sini hanya identitas tamunya yang ditempelkan.
 *
 * Mengembalikan ['id' => int, 'token' => string].
 */
function pubBuatTiketTamu(array $in): array {
    $token = pubBuatToken();

    $id = fbCreateTicket([
        'category_id'  => $in['category_id'],
        'track'        => $in['track'],
        'sender_id'    => null,
        'is_anonymous' => 0,
        'subject'      => $in['subject'],
        'message'      => $in['message'],
        'impact'       => $in['impact'] ?? null,
    ]);

    Database::query(
        "UPDATE feedback_tickets
            SET guest_name=?, guest_email=?, guest_phone=?, guest_role=?,
                guest_token=?, guest_ip=?
          WHERE id=?",
        [$in['guest_name'], $in['guest_email'], $in['guest_phone'] ?: null,
         $in['guest_role'] ?: null, $token, $in['ip_bin'], $id]);

    fbLogEvent($id, 'dibuat', null, 'publik',
        'Dikirim lewat formulir publik oleh ' . $in['guest_name']
        . ' <' . $in['guest_email'] . '> — identitas TIDAK terverifikasi');

    return ['id' => $id, 'token' => $token];
}

/** Email berisi nomor tiket dan tautan pelacakan. */
function pubKirimEmailTiket(array $t, string $token): bool {
    $url  = pubUrlLacak($token);
    $body = '<p>Terima kasih, laporan Anda sudah tercatat dengan nomor '
          . '<strong>' . h($t['ticket_no']) . '</strong>.</p>'
          . '<p>Anda dapat memantau perkembangannya kapan saja lewat tautan di bawah. '
          . 'Simpan email ini — tautannya berlaku ' . PUB_TOKEN_TTL_DAYS . ' hari dan '
          . 'tidak dapat dikirim ulang kalau hilang.</p>';

    if (($t['track'] ?? '') === 'safeguarding') {
        $body .= '<p style="background:#fdeceb;border:1px solid #f3b5b0;border-radius:8px;'
               . 'padding:12px 14px;color:#8c1610;font-size:13px">Laporan Anda masuk jalur '
               . 'perlindungan anak dan hanya dapat dilihat oleh Yayasan. Kalau ada anak '
               . 'dalam bahaya saat ini, jangan menunggu sistem ini — hubungi layanan '
               . 'darurat secara langsung.</p>';
    }

    return fbSendMail(
        $t['guest_email'],
        'Laporan Anda tercatat — ' . $t['ticket_no'],
        fbMailTemplate('Laporan Anda tercatat', $body, $url, 'Lacak Laporan'));
}

// ── PENGAJUAN AKUN ──────────────────────────────────────────

function pubPeranPengajuan(): array {
    return [
        'parent'     => 'Orang Tua / Wali',
        'teacher'    => 'Guru',
        'leader'     => 'Pimpinan Sekolah',
        'student'    => 'Siswa',
        'foundation' => 'Pengurus Yayasan',
    ];
}

/**
 * Alasan kenapa email ini tidak boleh mengajukan, atau null kalau
 * boleh. Dipisah dari penyimpanan supaya form bisa memberi tahu
 * lebih awal tanpa menulis apa pun.
 */
function pubTolakPengajuan(string $email): ?string {
    if (Database::fetchOne("SELECT id FROM users WHERE email=?", [$email])) {
        return 'Alamat email ini sudah terdaftar. Silakan masuk seperti biasa, '
             . 'atau gunakan tautan lupa kata sandi.';
    }
    if (Database::fetchOne(
            "SELECT id FROM registration_requests WHERE email=? AND status='menunggu'",
            [$email])) {
        return 'Pengajuan untuk email ini sudah ada dan sedang menunggu persetujuan admin.';
    }
    return null;
}

function pubSimpanPengajuan(array $in): int {
    return Database::insert('registration_requests', [
        'name'           => $in['name'],
        'email'          => $in['email'],
        'phone'          => $in['phone'] ?: null,
        'requested_role' => $in['requested_role'],
        'reason'         => $in['reason'] ?: null,
        'ticket_id'      => $in['ticket_id'] ?: null,
        'ip'             => $in['ip_bin'],
    ]);
}

function pubHitungPengajuanMenunggu(): int {
    return (int)Database::fetchOne(
        "SELECT COUNT(*) AS n FROM registration_requests WHERE status='menunggu'")['n'];
}
