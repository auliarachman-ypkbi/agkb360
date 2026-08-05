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
 * Yang ada DI SINI hanya hal yang memang milik kode: kueri sasaran,
 * naskah, tujuan tombol. Saklar, frekuensi, dan peran sasaran ada
 * di tabel email_campaign_state karena itu keputusan operasional
 * yang harus bisa diubah admin tanpa deploy ulang.
 *
 *   {PERAN}     : diganti daftar peran sesuai pengaturan
 *   atur_peran  : apakah peran sasaran relevan untuk kampanye ini
 *   perlu_token : menyertakan tautan set-password
 *   tujuan      : path tombol, relatif terhadap /app
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
        'subjek'      => 'Akun AGKB 360° Anda sudah siap',
        'sasaran'     => "SELECT id, name, email, role FROM users
                          WHERE is_active = 1 AND last_login IS NULL
                            AND role IN ($peran)",
        'isi' => function (array $u): array {
            return [
                'judul' => 'Selamat datang di AGKB 360°',
                'body'  => '<p>Halo <strong>' . h($u['name']) . '</strong>,</p>
<p>Akun Anda di AGKB 360° sudah dibuat, tetapi belum pernah dibuka. Platform ini dipakai untuk dua hal: penilaian 360° tiap semester, dan penyampaian masukan, apresiasi, atau keluhan kapan saja sepanjang tahun.</p>
<p>Yang perlu Anda lakukan sekarang hanya satu — membuat kata sandi. Setelah itu akun Anda aktif dan bisa dipakai.</p>
<p style="color:#6f6e85;font-size:13px">Tautan di bawah berlaku 7 hari. Kalau kedaluwarsa, Anda akan menerima tautan baru.</p>',
                'cta'   => 'Buat Kata Sandi',
            ];
        },
    ],

    // ── 2. Sudah login, belum pernah menyampaikan apa pun ───
    'mulai_feedback' => [
        'nama'        => 'Ajakan Mencoba Feedback',
        'penjelasan'  => 'Untuk yang sudah login tapi belum pernah mengirim satu pun masukan. Berhenti begitu orangnya mengirim.',
        'atur_peran'  => true,
        'perlu_token' => false,
        'tujuan'      => '/feedback/',
        'subjek'      => 'Ada yang ingin Anda sampaikan?',
        'sasaran'     => "SELECT u.id, u.name, u.email, u.role FROM users u
                          WHERE u.is_active = 1 AND u.last_login IS NOT NULL
                            AND u.role IN ($peran)
                            AND NOT EXISTS (
                              SELECT 1 FROM feedback_tickets t
                              WHERE t.sender_id = u.id AND t.is_test = 0
                            )",
        'isi' => function (array $u): array {
            return [
                'judul' => 'Suara Anda dibutuhkan',
                'body'  => '<p>Halo <strong>' . h($u['name']) . '</strong>,</p>
<p>Anda sudah masuk ke AGKB 360°, tetapi belum pernah menyampaikan apa pun lewat kanal masukan. Kanal ini bukan hanya untuk keluhan.</p>
<p><strong>Apresiasi</strong> — mengakui kerja baik rekan atau program yang berjalan bagus.<br>
<strong>Pertanyaan &amp; Masukan</strong> — hal yang mengganjal, usulan perbaikan, atau sesuatu yang perlu diperbaiki.<br>
<strong>Perlindungan Anak</strong> — laporan yang menyangkut keselamatan siswa, ditangani terpisah dengan kerahasiaan penuh.</p>
<p>Setiap laporan mendapat nomor tiket, penanggung jawab, dan batas waktu. Anda bisa memantau perkembangannya sendiri.</p>',
                'cta'   => 'Sampaikan Sekarang',
            ];
        },
    ],

    // ── 3. Pengingat rutin selama masa peluncuran ───────────
    'ajakan_rutin' => [
        'nama'        => 'Pengingat Mingguan',
        'penjelasan'  => 'Pengingat berkala untuk semua yang sudah aktif. Dibatasi 4 kiriman — cukup untuk satu bulan.',
        'atur_peran'  => true,
        'perlu_token' => false,
        'tujuan'      => '/feedback/',
        'subjek'      => 'Kanal masukan AGKB 360° terbuka minggu ini',
        'sasaran'     => "SELECT id, name, email, role FROM users
                          WHERE is_active = 1 AND last_login IS NOT NULL
                            AND role IN ($peran)",
        'isi' => function (array $u): array {
            return [
                'judul' => 'Satu menit untuk menyampaikan sesuatu',
                'body'  => '<p>Halo <strong>' . h($u['name']) . '</strong>,</p>
<p>Pengingat singkat: kalau minggu ini ada yang mengganjal, ada rekan yang pantas diapresiasi, atau ada hal yang menurut Anda bisa diperbaiki — kanalnya terbuka.</p>
<p>Tidak perlu menunggu masalahnya membesar. Justru masukan kecil yang datang lebih awal yang paling mudah ditindaklanjuti.</p>',
                'cta'   => 'Buka Kanal Masukan',
            ];
        },
    ],

    // ── 4. Penangan punya antrean menumpuk ──────────────────
    'antrean_unit' => [
        'nama'        => 'Ringkasan Antrean Penangan',
        'penjelasan'  => 'Untuk anggota unit penanganan yang unitnya punya tiket belum diambil. Hanya terkirim kalau memang ada antrean.',
        'atur_peran'  => false,
        'perlu_token' => false,
        'tujuan'      => '/admin/feedback.php?status=antrean',
        'subjek'      => 'Ada tiket menunggu di unit Anda',
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
        'isi' => function (array $u): array {
            $n = (int)($u['jumlah'] ?? 0);
            return [
                'judul' => $n . ' tiket menunggu diambil',
                'body'  => '<p>Halo <strong>' . h($u['name']) . '</strong>,</p>
<p>Di unit penanganan Anda ada <strong>' . $n . ' tiket</strong> yang belum diambil siapa pun. Tiket yang belum diambil tetap berjalan batas waktunya.</p>
<p>Membuka antrean lalu mengambil tiket yang bisa Anda tangani sudah cukup — sisanya tetap terlihat oleh rekan satu unit.</p>',
                'cta'   => 'Buka Antrean Unit',
            ];
        },
    ],

    // ── 5. Tiket melewati tenggat ───────────────────────────
    'tiket_telat' => [
        'nama'        => 'Peringatan Tiket Terlambat',
        'penjelasan'  => 'Untuk penanggung jawab tiket yang sudah lewat batas waktu. Harian, hanya kalau ada.',
        'atur_peran'  => false,
        'perlu_token' => false,
        'tujuan'      => '/admin/feedback.php?status=terlambat',
        'subjek'      => 'Tiket Anda melewati batas waktu',
        'sasaran'     => "SELECT u.id, u.name, u.email, u.role, COUNT(*) AS jumlah
                          FROM feedback_tickets t
                          JOIN users u ON u.id = t.assignee_id
                          WHERE t.is_test = 0 AND u.is_active = 1
                            AND t.status IN ('baru','ditinjau','ditindaklanjuti')
                            AND t.due_at < NOW()
                          GROUP BY u.id, u.name, u.email, u.role",
        'isi' => function (array $u): array {
            $n = (int)($u['jumlah'] ?? 0);
            return [
                'judul' => $n . ' tiket melewati batas waktu',
                'body'  => '<p>Halo <strong>' . h($u['name']) . '</strong>,</p>
<p>Ada <strong>' . $n . ' tiket</strong> atas nama Anda yang sudah lewat tenggat. Tiket yang dibiarkan akan naik ke tingkat eskalasi berikutnya secara otomatis.</p>
<p>Kalau tiketnya memang menunggu jawaban pelapor, ubah statusnya menjadi <em>Menunggu Pelapor</em> — jam SLA akan berhenti sementara.</p>',
                'cta'   => 'Lihat Tiket Terlambat',
            ];
        },
    ],

    ];
}

// ── Pengaturan ──────────────────────────────────────────────

/** Nilai bawaan kalau baris pengaturan belum ada di database. */
function kmpBawaan(): array {
    return [
        'aktivasi'       => ['jeda_hari' => 5, 'maks_kirim' => 0, 'roles' => ''],
        'mulai_feedback' => ['jeda_hari' => 7, 'maks_kirim' => 0, 'roles' => ''],
        'ajakan_rutin'   => ['jeda_hari' => 7, 'maks_kirim' => 4, 'roles' => ''],
        'antrean_unit'   => ['jeda_hari' => 7, 'maks_kirim' => 0, 'roles' => ''],
        'tiket_telat'    => ['jeda_hari' => 1, 'maks_kirim' => 0, 'roles' => ''],
    ];
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
            'is_active'  => (int)($s['is_active']  ?? 0),
            'jeda_hari'  => (int)($s['jeda_hari']  ?? $b['jeda_hari']),
            'maks_kirim' => (int)($s['maks_kirim'] ?? $b['maks_kirim']),
            'roles'      => (string)($s['roles']   ?? $b['roles']),
            'started_at' => $s['started_at'] ?? null,
            'ends_at'    => $s['ends_at']    ?? null,
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
function kmpSimpan(string $code, bool $aktif, int $jedaHari, int $maksKirim, array $roles, ?string $endsAt = null): void {
    $jedaHari  = max(1, min(90, $jedaHari));
    $maksKirim = max(0, min(50, $maksKirim));
    $roles     = implode(',', array_values(array_intersect(kmpSemuaPeran(), $roles)));
    $endsAt    = $endsAt ?: null;

    Database::query(
        "INSERT INTO email_campaign_state (code, is_active, jeda_hari, maks_kirim, roles, started_at, ends_at)
         VALUES (?, ?, ?, ?, ?, IF(?, NOW(), NULL), ?)
         ON DUPLICATE KEY UPDATE
            is_active  = VALUES(is_active),
            jeda_hari  = VALUES(jeda_hari),
            maks_kirim = VALUES(maks_kirim),
            roles      = VALUES(roles),
            started_at = IF(VALUES(is_active) AND started_at IS NULL, NOW(), started_at),
            ends_at    = VALUES(ends_at)",
        [$code, $aktif ? 1 : 0, $jedaHari, $maksKirim, $roles, $aktif ? 1 : 0, $endsAt]
    );
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

    if (!$simulasi && !kmpAktif($code)) {
        return ['code' => $code, 'nama' => $d['nama'], 'mati' => true,
                'sasaran' => 0, 'terkirim' => 0, 'gagal' => 0, 'dilewati' => 0, 'daftar' => []];
    }

    $sql   = str_replace('{PERAN}', kmpPeranSql($s['roles'] ?? ''), $d['sasaran']);
    $orang = Database::fetchAll($sql);

    $terkirim = 0; $gagal = 0; $dilewati = 0; $daftar = [];

    foreach ($orang as $u) {
        if ($terkirim + $gagal >= $batasKirim) break;

        if (!kmpBolehKirim($code, (int)$u['id'], (int)$s['jeda_hari'], (int)$s['maks_kirim'])) {
            $dilewati++;
            continue;
        }

        $isi = ($d['isi'])($u);
        $url = fbAppUrl() . ($d['tujuan'] ?? '/login.php');
        if (!empty($d['perlu_token'])) {
            $url = fbAppUrl() . '/auth/set-password.php?token='
                 . ($simulasi ? 'SIMULASI' : kmpBuatToken((int)$u['id']));
        }

        $daftar[] = $u['name'] . ' <' . $u['email'] . '> · ' . roleLabel($u['role']);

        if ($simulasi) { $terkirim++; continue; }

        $html = fbMailTemplate($isi['judul'], $isi['body'], $url, $isi['cta']);
        $ok   = true;
        try {
            fbSendMail($u['email'], $d['subjek'], $html);
        } catch (Throwable $e) {
            $ok = false;
            @error_log('[AGKB kampanye] gagal kirim ke ' . $u['email'] . ': ' . $e->getMessage());
        }
        kmpCatat($code, $u, $d['subjek'], $ok);
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
