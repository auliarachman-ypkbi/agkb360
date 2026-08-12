<?php
/**
 * ============================================================
 * AGKB 360° — Kampanye Email Terjadwal
 * ------------------------------------------------------------
 * Kampanye menyasar KEADAAN orang, bukan kelompoknya.
 *
 * Bedanya penting: kalau menyasar kelompok, guru yang sudah aktif
 * tetap menerima "ayo segera login" dan berhenti membaca email
 * dari platform. Kalau menyasar keadaan, daftarnya menyusut
 * sendiri seiring orang bertindak — dan berhenti total ketika
 * semua orang sudah melakukan yang diminta.
 *
 * Definisi kampanye ada di kode (karena berisi kueri), sedangkan
 * saklar nyala/mati ada di tabel email_campaign_state (karena itu
 * keputusan operasional yang boleh diubah tanpa menyentuh kode).
 * ============================================================
 */

/**
 * Semua peran yang bisa dijadikan sasaran kampanye.
 * Akun sistem (superadmin, admin, tester) tidak termasuk —
 * mereka bukan audiens, mereka pengelolanya.
 */
function kmpSemuaPeran(): array {
    return ['leader', 'teacher', 'staff', 'mentor', 'foundation', 'student', 'parent'];
}

/** Potongan SQL "IN (...)" dari daftar peran. Kosong = semua peran. */
function kmpPeranSql(string $csv = ''): string {
    $dipilih = array_values(array_intersect(
        kmpSemuaPeran(),
        array_filter(array_map('trim', explode(',', $csv)))
    ));
    if (!$dipilih) $dipilih = kmpSemuaPeran();
    return "'" . implode("','", $dipilih) . "'";
}

/**
 * Definisi seluruh kampanye.
 *
 * Naskah di sini adalah NASKAH BAWAAN. Kalau kolom yang sama pada
 * tabel email_campaign_state terisi, yang dipakai adalah isi tabel —
 * sehingga naskah dapat disunting dari halaman Blast Email tanpa
 * menyentuh kode.
 *
 * Penanda yang tersedia di dalam naskah:
 *   {{nama}}    nama penerima
 *   {{email}}   alamat email penerima
 *   {{peran}}   sebutan peran, misalnya Guru
 *   {{jumlah}}  angka terkait, dipakai kampanye antrean & tiket telat
 *
 *   {PERAN}     (pada kueri sasaran) diganti daftar peran terpilih
 *   atur_peran  apakah peran sasaran relevan untuk kampanye ini
 *   perlu_token menyertakan tautan aktivasi kata sandi
 *   tujuan      path tombol, relatif terhadap /app
 */
function kmpDefinisi(): array {
    $peran = '{PERAN}';

    return [

    // ── 1. Belum pernah login ───────────────────────────────
    'aktivasi' => [
        'nama'        => 'Aktivasi Akun',
        'penjelasan'  => 'Untuk yang akunnya sudah dibuat tapi belum pernah dibuka. Berhenti sendiri begitu orangnya login.',
        'atur_peran'  => true,
        'perlu_token' => true,
        'tujuan'      => null,
        'subjek'      => 'Aktivasi Akun AGKB 360°',
        'judul'       => 'Aktivasi Akun Anda',
        'cta'         => 'Aktifkan Akun',
        'body'        => '<p>Yth. {{nama}},</p>
<p>Akun Anda pada platform AGKB 360° telah dibuat dan saat ini menunggu aktivasi.</p>
<p>Platform ini digunakan untuk dua keperluan, yaitu pelaksanaan evaluasi 360° pada setiap akhir semester, serta penyampaian masukan, apresiasi, dan keluhan sepanjang tahun ajaran.</p>
<p>Untuk mengaktifkan akun, silakan menetapkan kata sandi melalui tautan berikut. Tautan berlaku selama 7 hari sejak email ini diterima.</p>',
        'sasaran'     => "SELECT id, name, email, role FROM users
                          WHERE is_active = 1 AND last_login IS NULL
                            AND role IN ($peran)",
    ],

    // ── 1b. Pengelola yang belum pernah login ───────────────
    // Sengaja terpisah dari kampanye Aktivasi Akun. Yang itu
    // menyasar seluruh warga sekolah — ratusan orang sekaligus.
    // Yang ini hanya menyentuh orang yang benar-benar memegang
    // tiket, sehingga bisa dijalankan lebih dulu tanpa menyentuh
    // guru, siswa, maupun orang tua.
    'aktivasi_pengelola' => [
        'nama'        => 'Aktivasi Akun Pengelola',
        'penjelasan'  => 'Khusus PIC, tujuan eskalasi, dan anggota unit penanganan yang belum pernah login. Tidak menyentuh guru, siswa, atau orang tua.',
        'atur_peran'  => false,
        'perlu_token' => true,
        'tujuan'      => null,
        'subjek'      => 'Aktivasi Akun Pengelola AGKB 360°',
        'judul'       => 'Akun Anda Menunggu Aktivasi',
        'cta'         => 'Buat Kata Sandi',
        'body'        => '<p>Yth. {{nama}},</p>
<p>Anda terdaftar sebagai penanggung jawab pada kanal masukan AGKB 360°. Laporan yang masuk sesuai kategori Anda akan dikirimkan ke alamat email ini beserta batas waktu penyelesaiannya.</p>
<p>Akun Anda sudah dibuat, namun kata sandinya belum ditetapkan. Selama belum ditetapkan, Anda menerima pemberitahuan tetapi belum dapat membuka maupun menanggapi tiketnya.</p>
<p>Silakan menetapkan kata sandi melalui tautan berikut. Tautan berlaku selama 7 hari sejak email ini diterima.</p>',
        // Penangan = anggota unit penanganan, ATAU PIC kategori,
        // ATAU tujuan eskalasi. Ketiganya dicakup karena seseorang
        // bisa memegang peran itu tanpa terdaftar di unit mana pun.
        'sasaran'     => "SELECT DISTINCT u.id, u.name, u.email, u.role
                          FROM users u
                          WHERE u.is_active = 1
                            AND u.last_login IS NULL
                            AND u.role <> 'tester'
                            AND (
                              EXISTS (SELECT 1 FROM user_groups ug
                                        JOIN `groups` g ON g.id = ug.group_id
                                                       AND g.type = 'penanganan'
                                       WHERE ug.user_id = u.id)
                              OR EXISTS (SELECT 1 FROM feedback_categories c
                                          WHERE c.default_pic_id = u.id)
                              OR EXISTS (SELECT 1 FROM feedback_escalation_levels el
                                          WHERE el.user_id = u.id AND el.is_active = 1)
                            )",
    ],

    // ── 1c. Pengumuman ke semua akun ────────────────────────
    // Satu-satunya kampanye tanpa syarat keadaan: siapa pun yang
    // punya akun aktif akan menerimanya.
    //
    // SENGAJA TIDAK UNTUK DIJADWALKAN. Kampanye lain berhenti
    // sendiri dengan menghitung berapa kali seseorang sudah
    // dikirimi. Pengumuman tidak begitu — isinya berganti tiap
    // kali, sehingga hitungan itu justru menghalangi pengumuman
    // berikutnya. Pakai tombol Kirim Sekarang, biarkan saklar
    // Aktif tetap mati.
    'pengumuman' => [
        'nama'        => 'Pengumuman Umum',
        'penjelasan'  => 'Ke semua akun aktif, tanpa syarat. Sunting naskahnya lebih dulu, lalu tekan Kirim Sekarang. Kampanye ini tidak pernah dijalankan penjadwal — hanya manual.',
        'atur_peran'  => true,
        // Penjadwal melewatinya, apa pun keadaan saklar Aktif.
        // Tanpa ini, satu centang tak sengaja berarti pengumuman
        // yang sama terkirim ke semua orang tiap hari.
        'manual'      => true,
        'perlu_token' => false,
        'tujuan'      => '/dashboard/',
        'subjek'      => 'Pengumuman dari AGKB 360°',
        'judul'       => 'Pengumuman',
        'cta'         => 'Buka AGKB 360°',
        'body'        => '<p>Yth. {{nama}},</p>
<p><em>Ganti bagian ini dengan isi pengumuman Anda sebelum mengirim.</em></p>
<p>Naskah ini tersimpan sampai diubah lagi, sehingga pengumuman berikutnya tidak perlu ditulis dari awal apabila bentuknya serupa.</p>',
        'sasaran'     => "SELECT id, name, email, role FROM users
                          WHERE is_active = 1
                            AND role <> 'tester'
                            AND role IN ($peran)",
    ],

    // ── 2. Sudah login, belum pernah menyampaikan apa pun ───
    'mulai_feedback' => [
        'nama'        => 'Ajakan Mencoba Feedback',
        'penjelasan'  => 'Untuk yang sudah login tapi belum pernah mengirim satu pun masukan. Berhenti begitu orangnya mengirim.',
        'atur_peran'  => true,
        'perlu_token' => false,
        'tujuan'      => '/feedback/',
        'subjek'      => 'Kanal Masukan dan Apresiasi AGKB 360°',
        'judul'       => 'Kanal Masukan dan Apresiasi',
        'cta'         => 'Buka Kanal Masukan',
        'body'        => '<p>Yth. {{nama}},</p>
<p>Akun Anda telah aktif, namun sampai saat ini belum terdapat masukan yang Anda sampaikan melalui platform.</p>
<p>Kanal ini tersedia untuk tiga jenis penyampaian:</p>
<p><strong>Apresiasi</strong> — pengakuan atas kerja baik rekan sejawat maupun program yang berjalan.<br>
<strong>Pertanyaan dan Masukan</strong> — pertanyaan, usulan perbaikan, atau hal yang memerlukan perhatian.<br>
<strong>Perlindungan Anak</strong> — laporan yang menyangkut keselamatan siswa, ditangani secara terpisah dengan kerahasiaan penuh.</p>
<p>Setiap penyampaian memperoleh nomor tiket, penanggung jawab, dan batas waktu penyelesaian yang dapat Anda pantau sendiri.</p>',
        'sasaran'     => "SELECT u.id, u.name, u.email, u.role FROM users u
                          WHERE u.is_active = 1 AND u.last_login IS NOT NULL
                            AND u.role IN ($peran)
                            AND NOT EXISTS (
                              SELECT 1 FROM feedback_tickets t
                              WHERE t.sender_id = u.id AND t.is_test = 0
                            )",
    ],

    // ── 3. Pengingat rutin selama masa peluncuran ───────────
    'ajakan_rutin' => [
        'nama'        => 'Pengingat Mingguan',
        'penjelasan'  => 'Pengingat berkala untuk semua yang sudah aktif. Dibatasi 4 kiriman — cukup untuk satu bulan.',
        'atur_peran'  => true,
        'perlu_token' => false,
        'tujuan'      => '/feedback/',
        'subjek'      => 'Pengingat Kanal Masukan AGKB 360°',
        'judul'       => 'Pengingat Berkala',
        'cta'         => 'Buka Kanal Masukan',
        'body'        => '<p>Yth. {{nama}},</p>
<p>Kami mengingatkan bahwa kanal masukan AGKB 360° terbuka setiap saat bagi seluruh warga sekolah.</p>
<p>Apabila terdapat hal yang perlu disampaikan, rekan sejawat yang layak memperoleh apresiasi, atau usulan perbaikan, Anda dapat menyampaikannya melalui platform.</p>
<p>Setiap masukan akan dicatat dan ditindaklanjuti oleh unit yang berwenang sesuai kategorinya.</p>',
        'sasaran'     => "SELECT id, name, email, role FROM users
                          WHERE is_active = 1 AND last_login IS NOT NULL
                            AND role IN ($peran)",
    ],

    // ── 4. Penangan punya antrean menumpuk ──────────────────
    'antrean_unit' => [
        'nama'        => 'Ringkasan Antrean Penangan',
        'penjelasan'  => 'Untuk anggota unit penanganan yang unitnya punya tiket belum diambil. Hanya terkirim kalau memang ada antrean.',
        'atur_peran'  => false,
        'perlu_token' => false,
        'tujuan'      => '/admin/feedback.php?status=antrean',
        'subjek'      => 'Tiket Menunggu Penanganan',
        'judul'       => '{{jumlah}} Tiket Menunggu Penanganan',
        'cta'         => 'Buka Antrean Unit',
        'body'        => '<p>Yth. {{nama}},</p>
<p>Terdapat <strong>{{jumlah}} tiket</strong> pada unit penanganan Anda yang belum diambil oleh siapa pun. Batas waktu penyelesaian tetap berjalan selama tiket belum ditangani.</p>
<p>Mohon membuka antrean unit dan mengambil tiket yang sesuai dengan kewenangan Anda. Tiket yang tidak Anda ambil tetap dapat dilihat dan ditangani oleh rekan satu unit.</p>',
        'sasaran'     => "SELECT DISTINCT u.id, u.name, u.email, u.role,
                                 (SELECT COUNT(*) FROM feedback_tickets t2
                                  JOIN feedback_categories c2 ON c2.id = t2.category_id
                                  JOIN user_groups ug2 ON ug2.group_id = c2.handler_group_id
                                  WHERE ug2.user_id = u.id AND t2.is_test = 0
                                    AND t2.assignee_id IS NULL
                                    AND t2.status IN ('baru','ditinjau','ditindaklanjuti')
                                 ) AS jumlah
                          FROM users u
                          JOIN user_groups ug ON ug.user_id = u.id
                          JOIN `groups` g ON g.id = ug.group_id AND g.type = 'penanganan'
                          JOIN feedback_categories c ON c.handler_group_id = g.id
                          JOIN feedback_tickets t ON t.category_id = c.id
                          WHERE u.is_active = 1 AND t.is_test = 0
                            AND t.assignee_id IS NULL
                            AND t.status IN ('baru','ditinjau','ditindaklanjuti')",
    ],

    // ── 5. Tiket melewati tenggat ───────────────────────────
    'tiket_telat' => [
        'nama'        => 'Peringatan Tiket Terlambat',
        'penjelasan'  => 'Untuk penanggung jawab tiket yang sudah lewat batas waktu. Harian, hanya kalau ada.',
        'atur_peran'  => false,
        'perlu_token' => false,
        'tujuan'      => '/admin/feedback.php?status=terlambat',
        'subjek'      => 'Tiket Melewati Batas Waktu',
        'judul'       => '{{jumlah}} Tiket Melewati Batas Waktu',
        'cta'         => 'Lihat Tiket Terlambat',
        'body'        => '<p>Yth. {{nama}},</p>
<p>Terdapat <strong>{{jumlah}} tiket</strong> atas nama Anda yang telah melewati batas waktu penyelesaian. Tiket yang tidak ditangani akan dieskalasi ke tingkat berikutnya secara otomatis.</p>
<p>Apabila tiket sedang menunggu jawaban dari pelapor, mohon ubah statusnya menjadi <em>Menunggu Pelapor</em> agar penghitungan batas waktu dihentikan sementara.</p>',
        'sasaran'     => "SELECT u.id, u.name, u.email, u.role, COUNT(*) AS jumlah
                          FROM feedback_tickets t
                          JOIN users u ON u.id = t.assignee_id
                          WHERE t.is_test = 0 AND u.is_active = 1
                            AND t.status IN ('baru','ditinjau','ditindaklanjuti')
                            AND t.due_at < NOW()
                          GROUP BY u.id, u.name, u.email, u.role",
    ],

    ];
}

/** Ganti penanda {{...}} dengan data penerima. */
function kmpIsiPenanda(string $teks, array $u): string {
    return strtr($teks, [
        '{{nama}}'   => h($u['name']  ?? ''),
        '{{email}}'  => h($u['email'] ?? ''),
        '{{peran}}'  => h(roleLabel($u['role'] ?? '')),
        '{{jumlah}}' => (string)(int)($u['jumlah'] ?? 0),
    ]);
}
// ── Pengaturan ──────────────────────────────────────────────

/** Nilai bawaan kalau baris pengaturan belum ada di database. */
function kmpBawaan(): array {
    return [
        // jam = jam WIB pengiriman. Sengaja berbeda-beda supaya
        // tidak semua kampanye menumpuk di menit yang sama, dan
        // supaya yang mendesak datang lebih dulu: peringatan tiket
        // terlambat sebelum jam kerja, ajakan ke orang tua sore.
        'aktivasi'           => ['jeda_hari' => 5, 'maks_kirim' => 0, 'roles' => '', 'jam' => 9],
        // Jeda pendek dan dibatasi tiga kiriman: pengelola jumlahnya
        // sedikit dan memang perlu segera masuk, tapi tidak pantas
        // diingatkan tanpa henti.
        'aktivasi_pengelola' => ['jeda_hari' => 3, 'maks_kirim' => 3, 'roles' => '', 'jam' => 8],
        // maks_kirim 0 supaya pengumuman kedua tidak terhalang oleh
        // hitungan pengumuman pertama. Jeda 1 hari sebagai pengaman
        // dari tombol Kirim Sekarang yang tertekan dua kali.
        'pengumuman'         => ['jeda_hari' => 1, 'maks_kirim' => 0, 'roles' => '', 'jam' => 10],
        'mulai_feedback' => ['jeda_hari' => 7, 'maks_kirim' => 0, 'roles' => '', 'jam' => 10],
        'ajakan_rutin'   => ['jeda_hari' => 7, 'maks_kirim' => 4, 'roles' => '', 'jam' => 16],
        'antrean_unit'   => ['jeda_hari' => 7, 'maks_kirim' => 0, 'roles' => '', 'jam' => 7],
        'tiket_telat'    => ['jeda_hari' => 1, 'maks_kirim' => 0, 'roles' => '', 'jam' => 7],
    ];
}

/**
 * Jam kirim sebuah kampanye, dalam WIB.
 * Nilai dari admin mengalahkan bawaan; kalau keduanya tidak ada,
 * jatuh ke jam 8 pagi.
 */
function kmpJam(string $code): int {
    $s = kmpStatus()[$code] ?? [];
    return (int)($s['jam_kirim'] ?? 8);
}

/**
 * Apakah kampanye ini memang dijadwalkan untuk jam sekarang.
 *
 * Jam dibaca lewat date() sehingga memakai Asia/Jakarta dari
 * config.php — tidak bergantung pada zona waktu sistem operasi
 * VPS, yang masih UTC.
 */
function kmpWaktunya(string $code): bool {
    return (int)date('G') === kmpJam($code);
}

/** Pengaturan seluruh kampanye: definisi kode + pengaturan database. */
function kmpStatus(): array {
    $db = [];
    foreach (Database::fetchAll("SELECT * FROM email_campaign_state") as $r) {
        $db[$r['code']] = $r;
    }
    $bawaan = kmpBawaan();
    $out = [];
    foreach (kmpDefinisi() as $code => $d) {
        $b = $bawaan[$code] ?? ['jeda_hari' => 7, 'maks_kirim' => 0, 'roles' => ''];
        $s = $db[$code] ?? [];
        $out[$code] = [
            'code'       => $code,
            'nama'       => $d['nama'],
            'penjelasan' => $d['penjelasan'],
            'atur_peran' => !empty($d['atur_peran']),
            'manual'     => !empty($d['manual']),
            'is_active'  => (int)($s['is_active']  ?? 0),
            'jeda_hari'  => (int)($s['jeda_hari']  ?? $b['jeda_hari']),
            'maks_kirim' => (int)($s['maks_kirim'] ?? $b['maks_kirim']),
            // Kolom jam_kirim baru ada sejak migrasi 022. Selama
            // belum dijalankan, ?? menjatuhkannya ke jam bawaan.
            'jam_kirim'  => (int)($s['jam_kirim'] ?? $b['jam'] ?? 8),
            'roles'      => (string)($s['roles']   ?? $b['roles']),
            'started_at' => $s['started_at'] ?? null,
            'ends_at'    => $s['ends_at']    ?? null,
            // Naskah: kolom terisi berarti sudah disunting admin;
            // NULL berarti tetap memakai naskah bawaan dari kode.
            'subjek'     => ($s['subjek'] ?? null) !== null ? $s['subjek'] : $d['subjek'],
            'judul'      => ($s['judul']  ?? null) !== null ? $s['judul']  : $d['judul'],
            'body'       => ($s['body']   ?? null) !== null ? $s['body']   : $d['body'],
            'cta'        => ($s['cta']    ?? null) !== null ? $s['cta']    : $d['cta'],
            'disunting'  => ($s['subjek'] ?? null) !== null,
            'bawaan'     => ['subjek'=>$d['subjek'],'judul'=>$d['judul'],'body'=>$d['body'],'cta'=>$d['cta']],
        ];
    }
    return $out;
}

function kmpPengaturan(string $code): array {
    $semua = kmpStatus();
    return $semua[$code] ?? [];
}

function kmpAktif(string $code): bool {
    $s = kmpPengaturan($code);
    if (!$s || !$s['is_active']) return false;
    if ($s['ends_at'] && strtotime($s['ends_at']) < time()) return false;
    return true;
}

/** Simpan pengaturan dari halaman admin. */
function kmpSimpan(string $code, bool $aktif, int $jedaHari, int $maksKirim, array $roles, ?string $endsAt = null, ?int $jam = null): void {
    $jedaHari  = max(1, min(90, $jedaHari));
    $maksKirim = max(0, min(50, $maksKirim));
    $roles     = implode(',', array_values(array_intersect(kmpSemuaPeran(), $roles)));
    $endsAt    = $endsAt ?: null;
    $jam       = $jam === null ? null : max(0, min(23, $jam));

    Database::query(
        "INSERT INTO email_campaign_state (code, is_active, jeda_hari, maks_kirim, jam_kirim, roles, started_at, ends_at)
         VALUES (?, ?, ?, ?, ?, ?, IF(?, NOW(), NULL), ?)
         ON DUPLICATE KEY UPDATE
            is_active  = VALUES(is_active),
            jeda_hari  = VALUES(jeda_hari),
            maks_kirim = VALUES(maks_kirim),
            jam_kirim  = VALUES(jam_kirim),
            roles      = VALUES(roles),
            started_at = IF(VALUES(is_active) AND started_at IS NULL, NOW(), started_at),
            ends_at    = VALUES(ends_at)",
        [$code, $aktif ? 1 : 0, $jedaHari, $maksKirim, $jam, $roles, $aktif ? 1 : 0, $endsAt]
    );
}

/**
 * Simpan naskah email dari editor. Semua kosong berarti
 * dikembalikan ke naskah bawaan (kolom di-NULL-kan).
 */
function kmpSimpanNaskah(string $code, ?string $subjek, ?string $judul, ?string $body, ?string $cta): void {
    $kosong = !trim((string)$subjek) && !trim((string)$judul) && !trim((string)$body);
    if ($kosong) {
        Database::query(
            "UPDATE email_campaign_state
             SET subjek = NULL, judul = NULL, body = NULL, cta = NULL
             WHERE code = ?", [$code]
        );
        return;
    }
    Database::query(
        "INSERT INTO email_campaign_state (code, subjek, judul, body, cta)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            subjek = VALUES(subjek), judul = VALUES(judul),
            body   = VALUES(body),   cta   = VALUES(cta)",
        [$code, mb_substr(trim((string)$subjek), 0, 255),
                mb_substr(trim((string)$judul), 0, 255),
                kmpBersihkanHtml((string)$body),
                mb_substr(trim((string)$cta), 0, 80)]
    );
}

/**
 * Saring HTML dari editor. Email hanya butuh penataan sederhana,
 * dan tag di luar daftar ini banyak yang tidak didukung klien email
 * atau justru berbahaya bila naskah disalin dari sumber lain.
 */
/**
 * Saring naskah email sebelum disimpan.
 *
 * Daftar tag diperlebar untuk mendukung mode HTML pada editor:
 * email yang tertata rapi disusun dengan tabel dan gaya sebaris,
 * bukan tata letak CSS modern yang diabaikan Outlook.
 *
 * Yang tetap dibuang tanpa kecuali: script, style, iframe, object,
 * embed, dan seluruh atribut peristiwa (onclick dan sejenisnya).
 * Blok <style> memang tidak berguna di email — yang bekerja adalah
 * gaya sebaris — jadi membuangnya tidak menghilangkan apa pun.
 */
function kmpBersihkanHtml(string $html): string {
    $html = preg_replace('#<(script|style|iframe|object|embed)\b.*?</\1>#is', '', $html);
    $html = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*/?>#i', '', $html);
    $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);

    // javascript: pada href atau src — satu-satunya jalan masuk yang
    // tersisa setelah atribut peristiwa dibuang.
    $html = preg_replace('#\s(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*\2#i', '', $html);

    $html = strip_tags($html,
        '<p><br><hr><strong><b><em><i><u><s><ul><ol><li><a><h1><h2><h3><h4><h5><h6>'
      . '<blockquote><div><span><table><thead><tbody><tfoot><tr><td><th><img><center><font>');

    return trim($html);
}

// ── Kelayakan kirim ─────────────────────────────────────────

/**
 * Boleh dikirimi sekarang? Dua syarat: belum melewati batas total
 * kiriman, dan kiriman terakhir sudah cukup lama.
 */
function kmpBolehKirim(string $code, int $userId, int $jedaHari, int $maksKirim): bool {
    $r = Database::fetchOne(
        "SELECT COUNT(*) AS n, MAX(sent_at) AS terakhir
         FROM email_blast_log
         WHERE campaign_code = ? AND recipient_id = ? AND status = 'sent'",
        [$code, $userId]
    );
    $n = (int)($r['n'] ?? 0);

    if ($maksKirim > 0 && $n >= $maksKirim) return false;
    if (!empty($r['terakhir']) && time() - strtotime($r['terakhir']) < $jedaHari * 86400) return false;
    return true;
}

/** Sisipkan token set-password baru, berlaku 7 hari. */
function kmpBuatToken(int $userId): string {
    $token = bin2hex(random_bytes(32));
    Database::query(
        "UPDATE users SET password_reset_token = ?, token_expires_at = ? WHERE id = ?",
        [$token, date('Y-m-d H:i:s', strtotime('+7 days')), $userId]
    );
    return $token;
}

function kmpCatat(string $code, array $u, string $subjek, bool $ok): void {
    Database::insert('email_blast_log', [
        'campaign_code'   => $code,
        'blast_type'      => $u['role'] ?? 'lainnya',
        'recipient_id'    => (int)$u['id'],
        'recipient_email' => $u['email'],
        'subject'         => $subjek,
        'status'          => $ok ? 'sent' : 'failed',
        'sent_by'         => null,
    ]);
}

/** Berapa orang yang jadi sasaran kampanye ini sekarang. */
function kmpHitungSasaran(string $code): int {
    $defs = kmpDefinisi();
    if (!isset($defs[$code])) return 0;
    $s   = kmpPengaturan($code);
    $sql = str_replace('{PERAN}', kmpPeranSql($s['roles'] ?? ''), $defs[$code]['sasaran']);
    $r   = Database::fetchOne("SELECT COUNT(*) AS n FROM ($sql) AS x");
    return (int)($r['n'] ?? 0);
}

/**
 * Jalankan satu kampanye. Mengembalikan ringkasan.
 * $batasKirim menjaga agar satu putaran tidak menghabiskan kuota harian.
 */
function kmpJalankan(string $code, bool $simulasi = false, int $batasKirim = 400): array {
    $defs = kmpDefinisi();
    if (!isset($defs[$code])) return ['error' => "Kampanye tidak dikenal: $code"];
    $d = $defs[$code];
    $s = kmpPengaturan($code);

    // Saklar Aktif mengatur apakah PENJADWAL boleh menjalankannya.
    // Kampanye manual tidak punya saklar itu sama sekali — bagi
    // mereka, menekan Kirim Sekarang sudah merupakan izinnya.
    // Tanpa pengecualian ini, kampanye manual tidak akan pernah bisa
    // mengirim apa pun.
    if (!$simulasi && empty($d['manual']) && !kmpAktif($code)) {
        return ['code' => $code, 'nama' => $d['nama'], 'mati' => true,
                'sasaran' => 0, 'terkirim' => 0, 'gagal' => 0, 'dilewati' => 0, 'daftar' => []];
    }

    $sql   = str_replace('{PERAN}', kmpPeranSql($s['roles'] ?? ''), $d['sasaran']);
    $orang = Database::fetchAll($sql);

    $terkirim = 0; $gagal = 0; $dilewati = 0; $daftar = [];

    foreach ($orang as $u) {
        if ($terkirim + $gagal >= $batasKirim) break;

        // Kampanye manual mengabaikan jeda dan batas kiriman.
        // Keduanya bekerja dengan menghitung berapa kali seseorang
        // sudah dikirimi kampanye ini — masuk akal untuk pengingat
        // yang isinya selalu sama, tetapi menghalangi pengumuman
        // yang isinya berganti tiap kali. Yang menekan tombolnya
        // manusia, dan itu sudah cukup jadi izinnya.
        if (empty($d['manual'])
            && !kmpBolehKirim($code, (int)$u['id'], (int)$s['jeda_hari'], (int)$s['maks_kirim'])) {
            $dilewati++;
            continue;
        }

        $subjek = kmpIsiPenanda((string)$s['subjek'], $u);
        $judul  = kmpIsiPenanda((string)$s['judul'],  $u);
        $body   = kmpIsiPenanda((string)$s['body'],   $u);
        $cta    = kmpIsiPenanda((string)$s['cta'],    $u);
        $url    = fbAppUrl() . ($d['tujuan'] ?? '/login.php');
        if (!empty($d['perlu_token'])) {
            $url = fbAppUrl() . '/auth/set-password.php?token='
                 . ($simulasi ? 'SIMULASI' : kmpBuatToken((int)$u['id']));
        }

        $daftar[] = $u['name'] . ' <' . $u['email'] . '> · ' . roleLabel($u['role']);

        if ($simulasi) { $terkirim++; continue; }

        $html = fbMailTemplate($judul, $body, $url, $cta);
        $ok   = true;
        try {
            // null: kampanye punya pencatatannya sendiri lewat
            // kmpCatat() di bawah, jadi jangan dicatat dua kali.
            $ok = fbSendMail($u['email'], $subjek, $html, null);
        } catch (Throwable $e) {
            $ok = false;
            @error_log('[AGKB kampanye] gagal kirim ke ' . $u['email'] . ': ' . $e->getMessage());
        }
        kmpCatat($code, $u, $subjek, $ok);
        $ok ? $terkirim++ : $gagal++;
    }

    return [
        'code'     => $code,
        'nama'     => $d['nama'],
        'sasaran'  => count($orang),
        'terkirim' => $terkirim,
        'gagal'    => $gagal,
        'dilewati' => $dilewati,
        'daftar'   => $daftar,
    ];
}
