<?php
// ============================================================
// AGKB 360° — Tiket dummy untuk akun tester
// ------------------------------------------------------------
// Membuat beberapa tiket atas nama satu akun tester, supaya saat
// login sebagai tester terlihat isi "Laporan Saya" dan alur
// pengirimannya. SEMUA tiket ditandai is_test=1 sehingga tidak
// pernah masuk dashboard maupun metrik mana pun.
//
// Jalankan:
//   docker exec ktb_php php /var/www/html/demo/tools/seed_tester_tickets.php
//
// Opsi:
//   --as=email          akun tester (default tester1@akgb360.app)
//   --db=nama_database  paksa database tertentu
//   --reset             hapus dulu tiket tester tersebut
// ============================================================

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Hanya lewat CLI.\n"); }

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';

$args  = $argv ?? [];
$reset = in_array('--reset', $args, true);
$email = 'tester1@akgb360.app';
$targetDb = DB_NAME;

foreach ($args as $a) {
    if (str_starts_with($a, '--as=')) $email = trim(substr($a, 5));
    if (str_starts_with($a, '--db=')) {
        $c = substr($a, 5);
        if (!preg_match('/^[A-Za-z0-9_]+$/', $c)) exit("Nama database tidak valid.\n");
        $targetDb = $c;
    }
}
if ($targetDb !== DB_NAME) { Database::query("USE `$targetDb`"); }

echo "Database : $targetDb\n";
echo "Akun     : $email\n";

// ── Pastikan akunnya ada ────────────────────────────────────
$u = Database::fetchOne("SELECT id, name, role FROM users WHERE email=?", [$email]);
if (!$u) {
    $nama = 'Tester ' . ucfirst(explode('@', $email)[0]);
    $uid  = Database::insert('users', [
        'name'      => $nama,
        'email'     => $email,
        'password'  => password_hash('KTB2026!', PASSWORD_DEFAULT),
        'role'      => 'tester',
        'is_active' => 1,
    ]);
    $u = ['id' => $uid, 'name' => $nama, 'role' => 'tester'];
    echo "Akun dibuat baru. Kata sandi: KTB2026!\n";
} else {
    echo "Akun ditemukan: {$u['name']} (peran: {$u['role']})\n";
    if ($u['role'] !== 'tester') {
        echo "PERINGATAN: peran akun ini bukan 'tester', jadi tiketnya TIDAK\n"
           . "otomatis ditandai adalah uji coba. Skrip tetap menandainya is_test=1.\n";
    }
}

if ($reset) {
    $n = Database::query("DELETE FROM feedback_tickets WHERE sender_id=? AND is_test=1", [$u['id']])->rowCount();
    echo "Tiket tester lama dihapus: $n\n";
}

// ── Kategori ────────────────────────────────────────────────
$C = [];
foreach (Database::fetchAll("SELECT id, code FROM feedback_categories") as $c) $C[$c['code']] = (int)$c['id'];
if (!$C) exit("Kategori belum ada. Jalankan migration 009 dulu.\n");

// PIC pengganti kalau kategori belum punya unit
$pic = Database::fetchOne(
    "SELECT id FROM users WHERE role IN ('admin','superadmin') AND is_active=1 ORDER BY id LIMIT 1");

// ── Tiket uji coba ──────────────────────────────────────────
// [kategori, subjek, pesan, hari_lalu, status, dampak, opsi]
$T = [
['inq_teknologi','Uji coba: tombol simpan kuesioner tidak merespons',
 "Saat menguji halaman pengisian kuesioner, tombol Simpan tidak memberi reaksi apa pun pada percobaan pertama. Setelah halaman dimuat ulang, tombolnya berfungsi normal. Perlu dicek apakah ada kaitannya dengan waktu sesi.",
 0,'baru','individu',[]],

['inq_teknologi','Uji coba: tampilan laporan meleset di layar kecil',
 "Pada lebar layar di bawah 400 piksel, tabel laporan keluar dari batas kartu sehingga kolom paling kanan terpotong. Di layar tablet ke atas tidak ada masalah.",
 1,'ditinjau','kelompok',[]],

['inq_sarana','Uji coba: pelaporan kerusakan proyektor',
 "Ini pengujian alur pelaporan sarana. Isi laporan sengaja dibuat menyerupai kejadian nyata untuk melihat bagaimana tiket dirutekan ke unit yang tepat dan berapa lama tenggat yang diberikan sistem.",
 2,'ditindaklanjuti','kelompok',[]],

['apr_umum','Uji coba: pengiriman apresiasi',
 "Menguji apakah apresiasi bisa diteruskan ke orang yang dituju dan apakah email pemberitahuannya terbentuk dengan benar. Tidak perlu ditindaklanjuti.",
 3,'baru','',[]],

['inq_admin','Uji coba: lampiran berkas pada tiket',
 "Menguji unggahan lampiran, batas ukuran berkas, dan apakah berkas benar-benar tersimpan di luar direktori web sehingga tidak bisa diakses lewat tautan langsung.",
 5,'menunggu_pelapor','individu',[]],

['inq_lain','Uji coba: penyelesaian tiket dan pemberitahuan pelapor',
 "Menguji alur penyelesaian: memilih jenis penyelesaian, menulis keterangan, lalu memastikan pelapor menerima pemberitahuan berisi keduanya.",
 8,'selesai','individu',
 ['res'=>'diselesaikan',
  'note'=>'Pengujian selesai. Jenis penyelesaian dan keterangan berhasil tersimpan serta terkirim ke pelapor. Tidak ditemukan masalah pada alur ini.']],
];

$dibuat = 0;
foreach ($T as [$code, $subj, $msg, $hari, $status, $impact, $opt]) {
    if (!isset($C[$code])) { echo "  ! kategori $code tidak ada, dilewati\n"; continue; }
    $cat = Database::fetchOne("SELECT * FROM feedback_categories WHERE id=?", [$C[$code]]);

    $created  = date('Y-m-d H:i:s', strtotime("-{$hari} days -2 hours"));
    $track    = $cat['track'];
    $selesai  = $status === 'selesai'
        ? date('Y-m-d H:i:s', strtotime($created) + 26 * 3600) : null;

    $due = ($track === 'apresiasi' || $selesai) ? null
        : date('Y-m-d H:i:s', time() + (int)$cat['sla_resolve_hours'] * 3600);

    $assignee = !empty($cat['handler_group_id'])
        ? null                                   // masuk antrean unit
        : ($cat['default_pic_id'] ?? $pic['id'] ?? null);

    $tid = Database::insert('feedback_tickets', [
        'ticket_no'        => fbGenerateTicketNo($track),
        'track'            => $track,
        'category_id'      => $cat['id'],
        'sender_id'        => $u['id'],
        'subject'          => $subj,
        'message'          => $msg,
        'impact'           => $track === 'inquiry' ? ($impact ?: 'individu') : null,
        'priority'         => $cat['default_priority'],
        'status'           => $status,
        'level'            => (int)$cat['start_level'],
        'assignee_id'      => $assignee,
        'due_at'           => $due,
        'first_response_at'=> $status === 'baru' ? null : date('Y-m-d H:i:s', strtotime($created) + 5 * 3600),
        'resolved_at'      => $selesai,
        'resolution_type'  => $opt['res']  ?? null,
        'resolution_note'  => $opt['note'] ?? null,
        'resolved_by'      => $selesai ? ($assignee ?: ($pic['id'] ?? null)) : null,
        'is_test'          => 1,           // ← kunci: tidak pernah masuk metrik
        'created_at'       => $created,
    ]);

    Database::insert('feedback_events', [
        'ticket_id'  => $tid,
        'actor_id'   => $u['id'],
        'event_type' => 'dibuat',
        'to_value'   => 'Tiket uji coba',
        'note'       => 'Dikirim oleh akun tester — tidak dihitung dalam metrik',
        'created_at' => $created,
    ]);

    if ($status === 'menunggu_pelapor') {
        Database::insert('feedback_messages', [
            'ticket_id'  => $tid,
            'author_id'  => $assignee ?: ($pic['id'] ?? null),
            'visibility' => 'publik',
            'body'       => 'Terima kasih atas pengujiannya. Boleh dilampirkan tangkapan layar saat berkas gagal diunggah?',
            'created_at' => date('Y-m-d H:i:s', strtotime($created) + 6 * 3600),
        ]);
    }

    // Anggota unit ikut memantau
    if (!empty($cat['handler_group_id'])) {
        foreach (fbUnitMembers((int)$cat['handler_group_id']) as $m) {
            Database::query("INSERT IGNORE INTO feedback_watchers (ticket_id,user_id) VALUES (?,?)",
                [$tid, $m['id']]);
        }
    }

    printf("  %-18s %-18s %s\n", $track, $status, mb_substr($subj, 0, 48));
    $dibuat++;
}

echo "\nTiket tester dibuat: $dibuat\n";
echo "Semua bertanda is_test=1 — tidak muncul di dashboard maupun metrik.\n";
echo "Di inbox admin, centang \"Tampilkan tiket tester\" untuk melihatnya.\n";
