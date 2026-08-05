<?php
// ============================================================
// AGKB 360° — Seed data demo untuk modul Feedback & Ticketing
// ------------------------------------------------------------
// HANYA BOLEH DIJALANKAN DI DATABASE DEMO (ktb_evaluation).
// Semua nama fiktif — aman ditunjukkan ke pihak luar.
//
// Jalankan:
//   docker exec ktb_php php /var/www/html/app/tools/seed_feedback_demo.php
//
// Tambahkan --reset untuk menghapus data demo sebelumnya.
// ============================================================

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Hanya bisa dijalankan lewat CLI.\n"); }

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/feedback.php';

// ── Pengaman: jangan pernah jalan di produksi yang sebenarnya ──
// Nama database lokal kebetulan juga 'ktb_production', jadi yang dipakai
// sebagai pembeda adalah PUBLIC_BASE_URL — di VPS nilainya bukan localhost.
$argvSafe = $argv ?? [];
$force    = in_array('--force', $argvSafe, true);
$baseUrl  = defined('PUBLIC_BASE_URL') ? PUBLIC_BASE_URL : '';
$isLocal  = $baseUrl === '' || str_contains($baseUrl, 'localhost') || str_contains($baseUrl, '127.0.0.1');

if (DB_NAME === 'ktb_production' && !$isLocal && !$force) {
    exit("DITOLAK. Terdeteksi lingkungan produksi (PUBLIC_BASE_URL = $baseUrl).\n"
       . "Skrip ini hanya untuk lokal atau database demo.\n"
       . "Kalau benar-benar disengaja, tambahkan --force.\n");
}
if (DB_NAME === 'ktb_production' && $isLocal) {
    echo "CATATAN: menulis ke ktb_production LOKAL (bukan VPS). Lanjut.\n";
}

$reset = in_array('--reset', $argvSafe, true);

// ── Target database ─────────────────────────────────────────
// Default mengikuti config.php. Pakai --db=ktb_evaluation agar data dummy
// masuk ke database demo tanpa perlu mengubah config.php.
$targetDb = DB_NAME;
foreach ($argvSafe as $a) {
    if (str_starts_with($a, '--db=')) {
        $cand = substr($a, 5);
        if (!preg_match('/^[A-Za-z0-9_]+$/', $cand)) exit("Nama database tidak valid.\n");
        $targetDb = $cand;
    }
}
if ($targetDb !== DB_NAME) {
    Database::query("USE `$targetDb`");
    echo "Target dialihkan ke: $targetDb\n";
    $cek = Database::fetchOne("SHOW TABLES LIKE 'feedback_tickets'");
    if (!$cek) exit("Tabel feedback_tickets belum ada di $targetDb.\n"
                  . "Jalankan migrations/009_feedback_ticketing.sql di database itu dulu.\n");
}

echo "Database: $targetDb\n";

// ── 1. Pengguna fiktif ──────────────────────────────────────
$demoUsers = [
    // Akun untuk orang internal mencoba-coba
    ['Admin Demo',        'admin.demo@demo.agkb360.app',      'admin'],
    ['Tester Demo',       'tester.demo@demo.agkb360.app',     'tester'],
    ['Yayasan Demo',      'yayasan.demo@demo.agkb360.app',    'foundation'],
    ['Kepala Sekolah Demo','kepsek.demo@demo.agkb360.app',    'leader'],
    ['Guru Demo',         'guru.demo@demo.agkb360.app',       'teacher'],
    ['Murid Demo',        'murid.demo@demo.agkb360.app',      'student'],
    ['Orang Tua Demo',    'ortu.demo@demo.agkb360.app',       'parent'],
    // Tokoh dalam skenario tiket
    ['Rina Hapsari',      'rina.hapsari@demo.agkb360.app',    'admin'],
    ['Bayu Nugroho',      'bayu.nugroho@demo.agkb360.app',    'leader'],
    ['Sekar Ayuningtyas', 'sekar.ayu@demo.agkb360.app',       'leader'],
    ['Damar Prasetyo',    'damar.prasetyo@demo.agkb360.app',  'teacher'],
    ['Ratih Kusuma',      'ratih.kusuma@demo.agkb360.app',    'teacher'],
    ['Yoga Mahendra',     'yoga.mahendra@demo.agkb360.app',   'staff'],
    ['Pipit Larasati',    'pipit.larasati@demo.agkb360.app',  'staff'],
    ['Galih Wicaksana',   'galih.wicaksana@demo.agkb360.app', 'mentor'],
    ['Nadine Alesha',     'nadine.alesha@demo.agkb360.app',   'student'],
    ['Rafi Danendra',     'rafi.danendra@demo.agkb360.app',   'student'],
    ['Tiara Zahwa',       'tiara.zahwa@demo.agkb360.app',     'student'],
    ['Ibu Wulandari',     'wulandari.ortu@demo.agkb360.app',  'parent'],
    ['Bapak Suryadi',     'suryadi.ortu@demo.agkb360.app',    'parent'],
    ['Laksmi Paramitha',  'laksmi.yayasan@demo.agkb360.app',  'foundation'],
];

$pw = password_hash('DemoAGKB2026!', PASSWORD_DEFAULT);
$U  = [];
foreach ($demoUsers as [$name, $email, $role]) {
    $row = Database::fetchOne("SELECT id FROM users WHERE email=?", [$email]);
    if ($row) { $U[$email] = (int)$row['id']; continue; }
    $U[$email] = Database::insert('users', [
        'name'=>$name, 'email'=>$email, 'password'=>$pw, 'role'=>$role, 'is_active'=>1,
    ]);
}
echo "Pengguna demo siap: " . count($U) . "\n";

$id = fn(string $slug) => $U[$slug . '@demo.agkb360.app'] ?? array_values($U)[0];

// ── 2. Bersihkan tiket demo lama ────────────────────────────
if ($reset) {
    $ids = array_values($U);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    Database::query("DELETE FROM feedback_tickets WHERE sender_id IN ($ph)", $ids);
    echo "Tiket demo lama dihapus.\n";
}

// ── 3. Peta kategori ────────────────────────────────────────
$C = [];
foreach (Database::fetchAll("SELECT id, code FROM feedback_categories") as $c) $C[$c['code']] = (int)$c['id'];
if (!$C) exit("Kategori belum ada. Jalankan migrations/009_feedback_ticketing.sql dulu.\n");

// ── 4. Isi rute eskalasi demo ───────────────────────────────
Database::query("DELETE FROM feedback_escalation_levels WHERE user_id IS NOT NULL");
foreach ([
    [1, 'inquiry',      $id('rina.hapsari')],
    [1, 'apresiasi',    $id('rina.hapsari')],
    [2, 'inquiry',      $id('bayu.nugroho')],
    [2, 'apresiasi',    $id('bayu.nugroho')],
    [3, 'inquiry',      $id('laksmi.yayasan')],
    [3, 'safeguarding', $id('laksmi.yayasan')],
] as [$lv, $trk, $uid]) {
    Database::insert('feedback_escalation_levels', [
        'level'=>$lv, 'label'=>fbLevels()[$lv], 'track'=>$trk, 'user_id'=>$uid, 'order_num'=>$lv*10,
    ]);
}
echo "Rute eskalasi demo diisi.\n";

// ── 4b. Isi anggota unit penanganan ─────────────────────────
$unitMap = [
    'Tata Usaha & Administrasi'   => ['rina.hapsari','pipit.larasati','admin.demo'],
    'Sarana & Prasarana'          => ['yoga.mahendra','pipit.larasati'],
    'Teknologi Informasi'         => ['yoga.mahendra','admin.demo'],
    'Humas & Komunikasi'          => ['rina.hapsari','admin.demo'],
    'Kesiswaan'                   => ['bayu.nugroho','galih.wicaksana','kepsek.demo'],
    'Kurikulum & IB DP'           => ['sekar.ayu','kepsek.demo'],
    'Kepegawaian & SDM'           => ['bayu.nugroho','kepsek.demo'],
    'Perlindungan Anak (Yayasan)' => ['laksmi.yayasan','yayasan.demo'],
];
$isiUnit = 0;
foreach ($unitMap as $namaUnit => $anggota) {
    $g = Database::fetchOne("SELECT id FROM `groups` WHERE name=? AND type='penanganan'", [$namaUnit]);
    if (!$g) continue;
    Database::query("DELETE FROM user_groups WHERE group_id=?", [$g['id']]);
    foreach ($anggota as $slug) {
        Database::query("INSERT IGNORE INTO user_groups (user_id, group_id) VALUES (?,?)",
            [$id($slug), $g['id']]);
        $isiUnit++;
    }
}
echo "Anggota unit penanganan diisi: $isiUnit\n";

// ── 5. Definisi tiket ───────────────────────────────────────
$ago = fn(int $d, int $h = 0) => date('Y-m-d H:i:s', strtotime("-{$d} days -{$h} hours"));

$T = [
// [kategori, pengirim, subjek, pesan, hari_lalu, status, prioritas, level, PIC, dampak, opsi]
['apr_guru','tiara.zahwa','Bu Ratih sabar sekali menjelaskan ulang materi',
 "Waktu saya belum paham topik integral, Bu Ratih menyediakan waktu istirahatnya untuk menjelaskan ulang sampai saya benar-benar mengerti. Beliau tidak pernah membuat saya merasa bodoh karena bertanya.",
 2,'baru','P4',1,'rina.hapsari',null,['apprec'=>'ratih.kusuma']],

['apr_program','suryadi.ortu','Program Global Inquiry sangat berdampak',
 "Sebagai orang tua saya melihat perubahan besar pada cara anak saya berpikir sejak mengikuti Global Inquiry. Dia jadi lebih kritis dan berani menyampaikan pendapat. Terima kasih kepada seluruh tim.",
 9,'selesai','P4',1,'rina.hapsari',null,['apprec'=>'sekar.ayu','res'=>'diselesaikan',
 'note'=>'Apresiasi diteruskan kepada Koordinator IB DP dan dibacakan pada rapat guru bulanan.','fwd'=>true]],

['apr_umum','damar.prasetyo','Respons tim IT cepat sekali saat proyektor bermasalah',
 "Proyektor di ruang kelas XI-B mati mendadak saat jam pelajaran. Saya lapor lewat grup dan dalam sepuluh menit sudah ditangani. Kelas hanya tertunda sebentar.",
 5,'selesai','P4',1,'rina.hapsari',null,['apprec'=>'yoga.mahendra','res'=>'diselesaikan',
 'note'=>'Apresiasi diteruskan ke tim IT.','fwd'=>true]],

['inq_teknologi','ratih.kusuma','Tidak bisa masuk ke akun AGKB 360 sejak pagi',
 "Sejak pagi saya mencoba masuk tetapi selalu muncul pesan email atau kata sandi tidak valid. Saya sudah mencoba dari dua perangkat berbeda dengan hasil sama. Padahal batas pengisian kuesioner tinggal dua hari.",
 0,'baru','P2',1,'yoga.mahendra','individu',['antrean'=>true]],

['inq_sarana','nadine.alesha','AC ruang perpustakaan mati sudah seminggu',
 "AC di ruang baca perpustakaan tidak menyala sejak minggu lalu. Siang hari ruangan jadi sangat panas sehingga banyak yang memilih tidak belajar di sana. Padahal sedang musim ujian.",
 1,'baru','P3',1,'yoga.mahendra','kelompok',['antrean'=>true]],

['inq_akademik','wulandari.ortu','Jadwal remedial bentrok dengan ekstrakurikuler wajib',
 "Anak saya harus mengikuti remedial Matematika pada hari yang sama dengan ekstrakurikuler wajib. Keduanya tidak bisa ditinggalkan. Mohon ada penyesuaian jadwal agar tidak memaksa anak memilih.",
 3,'ditindaklanjuti','P2',1,'sekar.ayu','kelompok',[]],

['inq_komunikasi','rafi.danendra','Informasi perubahan jadwal ujian terlambat sampai',
 "Perubahan jadwal ujian Fisika baru diumumkan sehari sebelumnya lewat grup kelas. Beberapa teman tidak membaca dan hampir terlambat. Mungkin bisa lewat kanal resmi yang pasti terbaca semua.",
 6,'ditindaklanjuti','P3',1,'rina.hapsari','kelompok',[]],

['inq_admin','damar.prasetyo','Surat tugas pelatihan belum terbit padahal acara pekan depan',
 "Saya mengajukan surat tugas untuk pelatihan asesmen tiga minggu lalu dan sampai sekarang belum terbit. Panitia meminta salinannya paling lambat lusa.",
 8,'ditindaklanjuti','P2',2,'bayu.nugroho','individu',['overdue'=>true,'escalated'=>true]],

['inq_sarana','pipit.larasati','Keran wastafel lantai dua bocor terus',
 "Keran di wastafel dekat tangga lantai dua bocor sejak dua minggu lalu. Air terus mengalir walau sudah ditutup rapat. Selain boros, lantainya jadi licin dan berbahaya.",
 11,'ditinjau','P3',2,'bayu.nugroho','sekolah',['overdue'=>true,'escalated'=>true]],

['inq_sdm','galih.wicaksana','Beban jam mengajar tidak merata antar mentor',
 "Pembagian jam bimbingan antar mentor terasa timpang. Beberapa rekan memegang hampir dua kali lipat jam dibanding yang lain tanpa penjelasan dasar pembagiannya.",
 14,'ditindaklanjuti','P2',3,'laksmi.yayasan','kelompok',['escalated_manual'=>true]],

['inq_akademik','tiara.zahwa','Materi IB DP Semester 2 belum diunggah di portal',
 "Beberapa modul untuk semester dua belum tersedia di portal, padahal sudah masuk minggu ketiga. Teman-teman kesulitan belajar mandiri karena tidak ada acuan.",
 4,'menunggu_pelapor','P2',1,'sekar.ayu','kelompok',[]],

['inq_teknologi','ratih.kusuma','Nilai yang sudah disimpan hilang setelah halaman dimuat ulang',
 "Saya mengisi nilai untuk satu kelas penuh lalu menekan simpan. Setelah halaman dimuat ulang, sebagian nilai kembali kosong. Saya harus mengisi ulang dari awal.",
 20,'selesai','P2',1,'yoga.mahendra','kelompok',
 ['res'=>'diselesaikan','note'=>'Ditemukan kegagalan penyimpanan saat sesi kedaluwarsa di tengah pengisian. Batas waktu sesi diperpanjang dan sistem kini menyimpan draf otomatis setiap 30 detik. Sudah diuji ulang bersama pelapor.']],

['inq_sarana','nadine.alesha','Lampu koridor lantai tiga sering berkedip',
 "Lampu di koridor lantai tiga berkedip-kedip terutama sore hari. Cukup mengganggu dan membuat beberapa teman pusing saat lewat.",
 25,'selesai','P3',1,'yoga.mahendra','kelompok',
 ['res'=>'diselesaikan','note'=>'Ballast lampu diganti pada dua titik koridor lantai tiga. Sudah dicek kembali dan tidak berkedip lagi.']],

['inq_komunikasi','suryadi.ortu','Usul kanal resmi khusus pengumuman orang tua',
 "Informasi sekolah saat ini tersebar di beberapa grup yang berbeda sehingga sering terlewat. Mungkin bisa dibuat satu kanal resmi yang khusus untuk pengumuman penting.",
 30,'selesai','P3',2,'bayu.nugroho','sekolah',
 ['res'=>'kebijakan_diubah','note'=>'Usulan diterima. Mulai semester ini seluruh pengumuman resmi untuk orang tua dikirim lewat satu kanal terpusat, dan grup kelas hanya untuk koordinasi harian. Kebijakan sudah disosialisasikan.']],

['inq_admin','wulandari.ortu','Pertanyaan mengenai skema beasiswa daerah',
 "Saya ingin menanyakan skema beasiswa dari pemerintah daerah yang katanya bisa diakses lewat sekolah. Bagaimana prosedurnya dan apa saja syaratnya?",
 22,'selesai','P3',1,'rina.hapsari','individu',
 ['res'=>'diteruskan_eksternal','note'=>'Skema beasiswa ini dikelola langsung oleh Dinas Pendidikan daerah, bukan sekolah. Berkas pengantar dari sekolah sudah kami terbitkan dan permohonan diteruskan ke dinas terkait. Perkembangan selanjutnya akan diinformasikan.']],

['inq_lain','rafi.danendra','Usul penambahan menu sehat di kantin',
 "Pilihan makanan di kantin kebanyakan gorengan. Mungkin bisa ditambah menu yang lebih sehat seperti salad atau buah potong.",
 28,'selesai','P4',1,'rina.hapsari','sekolah',
 ['res'=>'informasi_tidak_cukup','note'=>'Kami menghubungi pelapor dua kali untuk menanyakan detail preferensi menu agar bisa dibahas dengan pengelola kantin, namun belum ada tanggapan. Tiket ditutup sementara dan dapat dibuka kembali kapan saja.']],

['inq_kesiswaan','damar.prasetyo','Ketidakhadiran beberapa siswa saat jam terakhir meningkat',
 "Dalam sebulan terakhir jumlah siswa yang tidak hadir pada jam pelajaran terakhir meningkat cukup terlihat, terutama hari Jumat. Perlu ditelusuri penyebabnya.",
 40,'ditutup','P2',2,'bayu.nugroho','kelompok',
 ['res'=>'diselesaikan','note'=>'Setelah ditelusuri bersama wali kelas, penyebab utamanya adalah jadwal antar-jemput sebagian siswa pada hari Jumat. Jadwal jam terakhir hari Jumat digeser 30 menit lebih awal mulai bulan depan. Kehadiran kembali normal.','closed'=>true,'thread'=>true]],

['sg_bullying','tiara.zahwa','Perundungan verbal berulang di grup kelas daring',
 "Saya melihat seorang teman terus menerus dijadikan bahan olokan di grup kelas daring selama beberapa minggu terakhir. Awalnya terlihat seperti bercanda, tapi belakangan semakin sering dan yang bersangkutan sudah beberapa kali meminta berhenti. Dia mulai menghindar dari kegiatan kelompok.",
 1,'ditinjau','P1',3,'laksmi.yayasan',null,['anon'=>true]],
];

// ── 6. Buat tiket ───────────────────────────────────────────
$made = 0;
foreach ($T as $row) {
    [$catCode, $sender, $subj, $msg, $daysAgo, $status, $prio, $level, $pic, $impact, $opt] = $row;
    if (!isset($C[$catCode])) { echo "  ! kategori $catCode tidak ada, dilewati\n"; continue; }

    $cat     = Database::fetchOne("SELECT * FROM feedback_categories WHERE id=?", [$C[$catCode]]);
    $created = $ago($daysAgo, 3);
    $track   = $cat['track'];

    // Tenggat: sengaja dibuat sudah lewat untuk skenario terlambat
    $due = null; $respDue = null;
    if ($track !== 'apresiasi') {
        $due     = date('Y-m-d H:i:s', strtotime($created) + (int)$cat['sla_resolve_hours'] * 3600);
        $respDue = date('Y-m-d H:i:s', strtotime($created) + (int)$cat['sla_response_hours'] * 3600);
    }

    $resolvedAt = in_array($status, ['selesai','ditutup'], true)
        ? date('Y-m-d H:i:s', strtotime($created) + rand(20, 90) * 3600) : null;

    $tid = Database::insert('feedback_tickets', [
        'ticket_no'           => fbGenerateTicketNo($track),
        'track'               => $track,
        'category_id'         => $cat['id'],
        'sender_id'           => $id($sender),
        'is_anonymous'        => !empty($opt['anon']) ? 1 : 0,
        'subject'             => $subj,
        'message'             => $msg,
        'impact'              => $impact,
        'priority'            => $prio,
        'status'              => $status,
        'level'               => $level,
        // 'antrean' = sengaja tanpa PIC, untuk memperagakan alur "Ambil Tiket"
        'assignee_id'         => !empty($opt['antrean']) ? null : $id($pic),
        'appreciated_user_id' => isset($opt['apprec']) ? $id($opt['apprec']) : null,
        'forwarded_at'        => !empty($opt['fwd']) ? $resolvedAt : null,
        'response_due_at'     => $respDue,
        'due_at'              => $due,
        'first_response_at'   => $status === 'baru' ? null : date('Y-m-d H:i:s', strtotime($created) + rand(2, 20) * 3600),
        'resolved_at'         => $resolvedAt,
        'closed_at'           => !empty($opt['closed']) ? date('Y-m-d H:i:s', strtotime($resolvedAt) + 14*86400) : null,
        'resolution_type'     => $opt['res']  ?? null,
        'resolution_note'     => $opt['note'] ?? null,
        'resolved_by'         => $resolvedAt ? $id($pic) : null,
        'is_test'             => 0,
        'created_at'          => $created,
    ]);

    // Semua anggota unit otomatis memantau tiket di antreannya
    $unit = Database::fetchOne(
        "SELECT g.id FROM feedback_categories c
         JOIN `groups` g ON g.id = c.handler_group_id WHERE c.id=?", [$cat['id']]);
    if ($unit) {
        foreach (fbUnitMembers((int)$unit['id']) as $m) {
            Database::query("INSERT IGNORE INTO feedback_watchers (ticket_id,user_id) VALUES (?,?)",
                [$tid, $m['id']]);
        }
    }

    Database::insert('feedback_events', [
        'ticket_id'=>$tid, 'actor_id'=>$id($sender), 'event_type'=>'dibuat',
        'to_value'=>'Tiket masuk', 'created_at'=>$created,
    ]);

    if (!empty($opt['escalated']) || !empty($opt['escalated_manual'])) {
        $auto = empty($opt['escalated_manual']);
        Database::insert('feedback_events', [
            'ticket_id'=>$tid, 'actor_id'=>$auto ? null : $id('rina.hapsari'),
            'event_type'=>$auto ? 'dieskalasi_otomatis' : 'dieskalasi_manual',
            'from_value'=>'Level 1', 'to_value'=>'Level ' . $level,
            'note'=>$auto ? null : 'Menyangkut kebijakan kepegawaian, perlu keputusan Yayasan.',
            'created_at'=>date('Y-m-d H:i:s', strtotime($created) + 26*3600),
        ]);
        Database::insert('feedback_messages', [
            'ticket_id'=>$tid, 'author_id'=>null, 'is_system'=>1, 'visibility'=>'internal',
            'body'=>'Tiket dieskalasi ' . ($auto ? 'otomatis karena melewati batas waktu' : 'secara manual') . '.',
            'created_at'=>date('Y-m-d H:i:s', strtotime($created) + 26*3600),
        ]);
    }

    if ($status === 'menunggu_pelapor') {
        Database::insert('feedback_messages', [
            'ticket_id'=>$tid, 'author_id'=>$id($pic), 'visibility'=>'publik',
            'body'=>'Terima kasih atas laporannya. Boleh disebutkan modul mana saja yang belum tersedia, agar kami bisa langsung menindaklanjuti ke guru pengampunya?',
            'created_at'=>date('Y-m-d H:i:s', strtotime($created) + 8*3600),
        ]);
    }

    if (!empty($opt['thread'])) {
        foreach ([
            [$id($pic), 'publik',   'Terima kasih atas laporannya. Kami akan menelusuri bersama wali kelas terlebih dahulu.', 6],
            [$id($pic), 'internal', 'Sudah dicek ke bagian tata usaha: pola ketidakhadiran memang terkonsentrasi pada hari Jumat.', 30],
            [$id($sender), 'publik','Terima kasih. Beberapa siswa menyampaikan ke saya bahwa mereka harus menyesuaikan jadwal jemputan.', 52],
            [$id($pic), 'publik',   'Masukan tersebut sangat membantu. Kami sedang menyiapkan penyesuaian jadwal jam terakhir hari Jumat.', 70],
        ] as [$aid, $vis, $body, $h]) {
            Database::insert('feedback_messages', [
                'ticket_id'=>$tid, 'author_id'=>$aid, 'visibility'=>$vis, 'body'=>$body,
                'created_at'=>date('Y-m-d H:i:s', strtotime($created) + $h*3600),
            ]);
        }
    }

    if ($resolvedAt) {
        Database::insert('feedback_events', [
            'ticket_id'=>$tid, 'actor_id'=>$id($pic), 'event_type'=>'diselesaikan',
            'from_value'=>'ditindaklanjuti', 'to_value'=>'selesai',
            'note'=>fbResolutions()[$opt['res'] ?? 'diselesaikan'] ?? null, 'created_at'=>$resolvedAt,
        ]);
    }
    $made++;
}

// ── 7. Satu tiket dari akun tester (harus dikecualikan metrik)
$tester = Database::fetchOne(
    "SELECT id FROM users WHERE email='tester.demo@demo.agkb360.app' LIMIT 1")
    ?: Database::fetchOne("SELECT id FROM users WHERE role='tester' AND is_active=1 LIMIT 1");
if ($tester && isset($C['inq_teknologi'])) {
    $tid = Database::insert('feedback_tickets', [
        'ticket_no'=>fbGenerateTicketNo('inquiry'), 'track'=>'inquiry',
        'category_id'=>$C['inq_teknologi'], 'sender_id'=>(int)$tester['id'],
        'subject'=>'Uji coba pengiriman tiket dari akun tester',
        'message'=>'Ini tiket percobaan untuk memastikan alur berjalan. Seharusnya tidak muncul di dashboard maupun metrik mana pun.',
        'impact'=>'individu', 'priority'=>'P3', 'status'=>'baru', 'level'=>1,
        'assignee_id'=>$id('rina.hapsari'), 'is_test'=>1, 'created_at'=>$ago(1),
    ]);
    Database::insert('feedback_events', [
        'ticket_id'=>$tid, 'actor_id'=>(int)$tester['id'], 'event_type'=>'dibuat',
        'note'=>'Dikirim oleh tester — tidak dihitung dalam metrik',
    ]);
    $made++;
}

echo "Tiket demo dibuat: $made\n\n";
echo "─────────────────────────────────────────────────────────\n";
echo " AKUN DEMO — kata sandi semua: DemoAGKB2026!\n";
echo "─────────────────────────────────────────────────────────\n";
foreach ([
    ['Admin',            'admin.demo@demo.agkb360.app',   'inbox tiket, kelola kategori, dashboard'],
    ['Tester',           'tester.demo@demo.agkb360.app',  'kirim tiket, ditandai & tidak masuk metrik'],
    ['Yayasan',          'yayasan.demo@demo.agkb360.app', 'satu-satunya yang bisa lihat laporan perlindungan anak'],
    ['Kepala Sekolah',   'kepsek.demo@demo.agkb360.app',  'tiket level 2 ke atas'],
    ['Guru',             'guru.demo@demo.agkb360.app',    'kirim feedback, lihat laporan sendiri'],
    ['Murid',            'murid.demo@demo.agkb360.app',   'kirim feedback, lihat laporan sendiri'],
    ['Orang Tua',        'ortu.demo@demo.agkb360.app',    'kirim feedback, lihat laporan sendiri'],
] as [$peran, $mail, $ket]) {
    printf(" %-16s %-32s %s\n", $peran, $mail, $ket);
}
echo "─────────────────────────────────────────────────────────\n";
echo " Coba ini saat peragaan: masuk sebagai Admin, buka Inbox\n";
echo " Tiket. Laporan perlindungan anak TIDAK akan terlihat.\n";
echo " Lalu masuk sebagai Yayasan — barulah muncul.\n";
echo "─────────────────────────────────────────────────────────\n";
echo "Selesai.\n";
