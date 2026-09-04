<?php
// ============================================================
// AGKB 360° — Modul Feedback & Ticketing
// Library inti: SLA, prioritas, eskalasi, audit, izin, lampiran
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// ── LABEL & KONSTANTA ───────────────────────────────────────

function fbTracks(): array {
    return [
        'apresiasi'    => ['label'=>'Apresiasi',    'icon'=>'bi-star-fill',            'color'=>'#0f7a3d'],
        'inquiry'      => ['label'=>'Kendala / Masukan', 'icon'=>'bi-exclamation-circle-fill','color'=>'#030870'],
        // Kode 'safeguarding' dipertahankan di database — menggantinya
        // menyentuh enum, indeks, dan tiket lama tanpa manfaat. Yang
        // berubah cakupannya: kini kanal tertutup ke Yayasan untuk
        // segala hal sensitif, bukan perlindungan anak semata.
        'safeguarding' => ['label'=>'Kanal Yayasan',  'icon'=>'bi-shield-lock-fill','color'=>'#b42318'],
    ];
}

function fbStatuses(): array {
    return [
        'baru'             => ['label'=>'Baru',              'color'=>'#030870','bg'=>'#eeebfc','sla'=>true],
        'ditinjau'         => ['label'=>'Ditinjau',          'color'=>'#2201b2','bg'=>'#eeebfc','sla'=>true],
        'ditindaklanjuti'  => ['label'=>'Ditindaklanjuti',   'color'=>'#a85a01','bg'=>'#fff8ef','sla'=>true],
        'menunggu_pelapor' => ['label'=>'Menunggu Pelapor',  'color'=>'#6b6a83','bg'=>'#f3f4f6','sla'=>false],
        'selesai'          => ['label'=>'Selesai',           'color'=>'#015c36','bg'=>'#e7f6ef','sla'=>false],
        'ditutup'          => ['label'=>'Ditutup',           'color'=>'#2f2d4d','bg'=>'#f3f4f6','sla'=>false],
    ];
}

function fbPriorities(): array {
    return [
        'P1' => ['label'=>'Kritis', 'color'=>'#b42318','bg'=>'#fdeceb','rank'=>1],
        'P2' => ['label'=>'Tinggi', 'color'=>'#b83a01','bg'=>'#fff1dc','rank'=>2],
        'P3' => ['label'=>'Sedang', 'color'=>'#030870','bg'=>'#eeebfc','rank'=>3],
        'P4' => ['label'=>'Rendah', 'color'=>'#6b6a83','bg'=>'#f3f4f6','rank'=>4],
    ];
}

function fbImpacts(): array {
    return [
        'individu' => 'Perorangan',
        'kelompok' => 'Satu kelas / kelompok',
        'sekolah'  => 'Seluruh sekolah',
    ];
}

function fbResolutions(): array {
    return [
        'diselesaikan'                => 'Diselesaikan',
        'diteruskan_eksternal'        => 'Diteruskan ke pihak eksternal',
        'kebijakan_diubah'            => 'Kebijakan diubah',
        'tidak_dapat_ditindaklanjuti' => 'Tidak dapat ditindaklanjuti',
        'duplikat'                    => 'Duplikat',
        'informasi_tidak_cukup'       => 'Informasi tidak cukup',
        'tidak_terbukti'              => 'Tidak terbukti',
    ];
}

function fbLevels(): array {
    return [1=>'Admin & Staf Sekolah', 2=>'Pimpinan Sekolah', 3=>'Yayasan'];
}

/** SLA bawaan per prioritas — dipakai kalau kategori tidak menetapkan sendiri. */
function fbSlaDefaults(): array {
    return [
        'P1' => ['response'=>4,  'resolve'=>24],
        'P2' => ['response'=>24, 'resolve'=>72],
        'P3' => ['response'=>48, 'resolve'=>120],
        'P4' => ['response'=>72, 'resolve'=>240],
    ];
}

// ── KOMPONEN TAMPILAN ───────────────────────────────────────

function fbChip(string $text, string $color, string $bg, string $border = ''): string {
    $b = $border ?: $bg;
    return '<span style="display:inline-block;background:' . $bg . ';color:' . $color
         . ';border:1px solid ' . $b . ';border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600;white-space:nowrap">'
         . h($text) . '</span>';
}

function fbBadgeStatus(string $s): string {
    $x = fbStatuses()[$s] ?? ['label'=>$s,'color'=>'#6b6a83','bg'=>'#f3f4f6'];
    return fbChip($x['label'], $x['color'], $x['bg']);
}

function fbBadgePriority(string $p): string {
    $x = fbPriorities()[$p] ?? ['label'=>$p,'color'=>'#6b6a83','bg'=>'#f3f4f6'];
    return fbChip($p . ' · ' . $x['label'], $x['color'], $x['bg']);
}

function fbBadgeTrack(string $t): string {
    $x = fbTracks()[$t] ?? ['label'=>$t,'color'=>'#6b6a83'];
    return fbChip($x['label'], '#fff', $x['color'], $x['color']);
}

function fbBadgeOverdue(array $t): string {
    if (!fbIsOverdue($t)) return '';
    $late = abs((int)floor(fbHoursLeft($t)));
    $txt  = $late >= 48 ? round($late / 24) . ' hari' : $late . ' jam';
    return fbChip('Terlambat ' . $txt, '#fff', '#b42318', '#b42318');
}

function fbRelTime(?string $ts): string {
    if (!$ts) return '—';
    $d = time() - strtotime($ts);
    if ($d < 60)     return 'baru saja';
    if ($d < 3600)   return floor($d/60) . ' menit lalu';
    if ($d < 86400)  return floor($d/3600) . ' jam lalu';
    if ($d < 604800) return floor($d/86400) . ' hari lalu';
    return date('d M Y', strtotime($ts));
}

function fbDurationText(?string $from, ?string $to): string {
    if (!$from || !$to) return '—';
    $s = strtotime($to) - strtotime($from);
    if ($s < 3600)  return max(1, round($s/60)) . ' menit';
    if ($s < 86400) return round($s/3600, 1) . ' jam';
    return round($s/86400, 1) . ' hari';
}

// ── WAKTU & SLA ─────────────────────────────────────────────

/**
 * Tambah jam kerja, melewati Sabtu & Minggu.
 * Disederhanakan: hari kerja penuh 24 jam, bukan jam kantor.
 */
function fbAddWorkingHours(string $from, int $hours): string {
    $dt = new DateTime($from, new DateTimeZone('Asia/Jakarta'));
    $remaining = $hours;
    while ($remaining > 0) {
        $step = min($remaining, 24);
        $dt->modify("+{$step} hours");
        $remaining -= $step;
        // Kalau mendarat di akhir pekan, geser ke Senin
        while (in_array((int)$dt->format('N'), [6, 7], true)) {
            $dt->modify('+1 day');
        }
    }
    return $dt->format('Y-m-d H:i:s');
}

/** Prioritas = default kategori, digeser oleh dampak. Safeguarding selalu P1. */
function fbComputePriority(string $categoryPriority, ?string $impact, string $track): string {
    if ($track === 'safeguarding') return 'P1';

    $order = ['P1','P2','P3','P4'];
    $idx   = array_search($categoryPriority, $order, true);
    if ($idx === false) $idx = 2;

    if ($impact === 'sekolah')  $idx--;   // naik tingkat
    if ($impact === 'individu') $idx++;   // turun tingkat

    return $order[max(0, min(3, $idx))];
}

/** Hitung ulang tenggat respons & penyelesaian untuk level saat ini. */
function fbComputeDueDates(array $ticket, ?array $category = null, ?string $from = null): array {
    $from = $from ?: date('Y-m-d H:i:s');
    $def  = fbSlaDefaults()[$ticket['priority']] ?? fbSlaDefaults()['P3'];

    $respH = $category['sla_response_hours'] ?? $def['response'];
    $resH  = $category['sla_resolve_hours']  ?? $def['resolve'];

    // Safeguarding tidak mengenal hari kerja — jam kalender
    if ($ticket['track'] === 'safeguarding') {
        $tz = new DateTimeZone('Asia/Jakarta');
        $r  = (new DateTime($from, $tz))->modify("+{$respH} hours")->format('Y-m-d H:i:s');
        $d  = (new DateTime($from, $tz))->modify("+{$resH} hours")->format('Y-m-d H:i:s');
        return ['response_due_at'=>$r, 'due_at'=>$d];
    }

    return [
        'response_due_at' => fbAddWorkingHours($from, (int)$respH),
        'due_at'          => fbAddWorkingHours($from, (int)$resH),
    ];
}

/** Apakah tiket sudah melewati tenggat? */
function fbIsOverdue(array $t): bool {
    if (!in_array($t['status'], ['baru','ditinjau','ditindaklanjuti'], true)) return false;
    return !empty($t['due_at']) && strtotime($t['due_at']) < time();
}

/** Sisa waktu dalam jam (negatif = terlambat). */
function fbHoursLeft(array $t): ?float {
    if (empty($t['due_at'])) return null;
    return round((strtotime($t['due_at']) - time()) / 3600, 1);
}

// ── NOMOR TIKET ─────────────────────────────────────────────

function fbGenerateTicketNo(string $track): string {
    // KY = Kanal Yayasan. Tiket lama berawalan SG tetap apa adanya —
    // nomor yang sudah beredar tidak boleh berubah.
    $prefix = $track === 'safeguarding' ? 'KY' : 'AGKB';
    $year   = date('Y');
    $row = Database::fetchOne(
        "SELECT ticket_no FROM feedback_tickets
         WHERE ticket_no LIKE ? ORDER BY id DESC LIMIT 1",
        ["$prefix-$year-%"]
    );
    $next = $row ? ((int)substr($row['ticket_no'], -4)) + 1 : 1;
    return sprintf('%s-%s-%04d', $prefix, $year, $next);
}

// ── AUDIT LOG ───────────────────────────────────────────────

function fbLogEvent(int $ticketId, string $type, ?string $from = null, ?string $to = null, ?string $note = null): void {
    Database::insert('feedback_events', [
        'ticket_id'  => $ticketId,
        'actor_id'   => $_SESSION['user_id'] ?? null,
        'event_type' => $type,
        'from_value' => $from,
        'to_value'   => $to,
        'note'       => $note ? mb_substr($note, 0, 255) : null,
        'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function fbEventLabel(string $type): string {
    return [
        'dibuat'              => 'Tiket dibuat',
        'dilihat'             => 'Dibuka',
        'status_diubah'       => 'Status diubah',
        'prioritas_diubah'    => 'Prioritas diubah',
        'pic_diubah'          => 'Penanggung jawab diubah',
        'kategori_diubah'     => 'Kategori dipindahkan',
        'dieskalasi_otomatis' => 'Dieskalasi otomatis (SLA terlampaui)',
        'dieskalasi_manual'   => 'Dieskalasi manual',
        'dibalas'             => 'Dibalas',
        'catatan_internal'    => 'Catatan internal ditambahkan',
        'lampiran_diunggah'   => 'Lampiran diunggah',
        'lampiran_diunduh'    => 'Lampiran diunduh',
        'diselesaikan'        => 'Diselesaikan',
        'ditutup'             => 'Ditutup',
        'dibuka_kembali'      => 'Dibuka kembali',
        'diteruskan'          => 'Apresiasi diteruskan',
        'diambil'             => 'Diambil dari antrean unit',
        'identitas_dibuka'    => 'Identitas pelapor dibuka',
        'dipindahkan_dari_lama' => 'Dipindahkan dari sistem feedback lama',
    ][$type] ?? $type;
}

// ── PESAN / THREAD ──────────────────────────────────────────

function fbAddMessage(int $ticketId, ?int $authorId, string $body, string $visibility = 'publik', bool $isSystem = false): int {
    return Database::insert('feedback_messages', [
        'ticket_id'  => $ticketId,
        'author_id'  => $authorId,
        'body'       => $body,
        'visibility' => $visibility,
        'is_system'  => $isSystem ? 1 : 0,
    ]);
}

// ── UNIT PENANGANAN ─────────────────────────────────────────
// Unit memakai tabel `groups` dengan type='penanganan' dan
// respondent_type=NULL, sehingga tidak pernah masuk matriks evaluasi.

function fbUnits(bool $onlyActive = true): array {
    return Database::fetchAll(
        "SELECT g.*,
                (SELECT COUNT(*) FROM user_groups ug WHERE ug.group_id = g.id) AS anggota,
                (SELECT COUNT(*) FROM feedback_categories c WHERE c.handler_group_id = g.id) AS kategori
         FROM `groups` g WHERE g.type = 'penanganan'
         ORDER BY g.order_num, g.name");
}

function fbUnitMembers(int $groupId): array {
    return Database::fetchAll(
        "SELECT u.id, u.name, u.email, u.role
         FROM user_groups ug JOIN users u ON u.id = ug.user_id
         WHERE ug.group_id = ? AND u.is_active = 1
         ORDER BY u.name", [$groupId]);
}

/** Unit penanganan yang diikuti seorang pengguna. */
function fbUserUnits(int $userId): array {
    return Database::fetchAll(
        "SELECT g.id, g.name FROM user_groups ug
         JOIN `groups` g ON g.id = ug.group_id
         WHERE ug.user_id = ? AND g.type = 'penanganan'
         ORDER BY g.order_num", [$userId]);
}

/**
 * Simpan keanggotaan unit penanganan seorang pengguna.
 * Hanya menyentuh grup ber-type 'penanganan', sehingga keanggotaan
 * pengguna di kelompok evaluasi 360° tidak ikut terhapus.
 */
function fbSimpanUnitPengguna(int $userId, array $groupIds): void {
    $sah = array_column(
        Database::fetchAll("SELECT id FROM `groups` WHERE type='penanganan'"), 'id');
    $pilih = array_values(array_intersect(array_map('intval', $groupIds), array_map('intval', $sah)));

    if ($sah) {
        $ph = implode(',', array_fill(0, count($sah), '?'));
        Database::query(
            "DELETE FROM user_groups WHERE user_id = ? AND group_id IN ($ph)",
            array_merge([$userId], $sah));
    }
    foreach ($pilih as $gid) {
        Database::query("INSERT IGNORE INTO user_groups (user_id, group_id) VALUES (?,?)", [$userId, $gid]);
    }
}

function fbIsUnitMember(?int $groupId, int $userId): bool {
    if (!$groupId) return false;
    return (bool) Database::fetchOne(
        "SELECT 1 FROM user_groups WHERE group_id = ? AND user_id = ?", [$groupId, $userId]);
}

/** Unit penanganan untuk sebuah tiket, lewat kategorinya. */
function fbTicketUnit(array $t): ?array {
    if (empty($t['category_id'])) return null;
    return Database::fetchOne(
        "SELECT g.id, g.name FROM feedback_categories c
         JOIN `groups` g ON g.id = c.handler_group_id
         WHERE c.id = ?", [$t['category_id']]);
}

/** Seorang anggota unit mengambil tiket dari antrean. */
function fbClaimTicket(int $ticketId, int $userId): bool {
    $t = Database::fetchOne("SELECT * FROM feedback_tickets WHERE id=?", [$ticketId]);
    if (!$t || !empty($t['assignee_id'])) return false;

    $unit = fbTicketUnit($t);
    $u    = Database::fetchOne("SELECT name, role FROM users WHERE id=?", [$userId]);
    $boleh = ($unit && fbIsUnitMember((int)$unit['id'], $userId))
          || in_array($u['role'] ?? '', ['superadmin','admin','foundation'], true);
    if (!$boleh) return false;

    Database::update('feedback_tickets', ['assignee_id' => $userId], 'id = ?', [$ticketId]);
    fbLogEvent($ticketId, 'diambil', 'Antrean ' . ($unit['name'] ?? 'unit'), $u['name'] ?? '');
    fbAddMessage($ticketId, null,
        ($u['name'] ?? 'Seseorang') . ' mengambil tiket ini dari antrean '
        . ($unit['name'] ?? 'unit') . '.', 'internal', true);

    // Status ikut bergerak. Sebelumnya tiket tetap tertulis "Baru"
    // di mata pelapor meski sudah ada yang memegangnya — pelapor
    // tidak punya cara mengetahui laporannya mulai dikerjakan.
    if ($t['status'] === 'baru') {
        fbSetStatus($ticketId, 'ditinjau', 'Tiket diambil dari antrean');
        fbNotifyStatus($ticketId, 'ditinjau',
            'Laporan Anda sudah diterima penanggung jawabnya dan mulai ditinjau.');
    }
    return true;
}

// ── RUTE / PIC ──────────────────────────────────────────────

/**
 * Cari PIC untuk level tertentu. Kategori spesifik menang atas rute umum.
 *
 * Untuk jalur safeguarding ada satu syarat tambahan: calon penanggung
 * jawab harus orang yang boleh membaca tiketnya. Sejak akses Kanal
 * Yayasan ditentukan keanggotaan unit dan bukan role, tabel rute bisa
 * menunjuk pengurus yayasan yang tidak dapat membuka tiketnya sendiri.
 * Penugasan semacam itu lebih buruk daripada tidak ditugaskan: tiket
 * tampak tertangani, sementara yang ditunjuk hanya mendapat 403 dan
 * tidak ada yang tahu sampai ada yang memeriksa.
 *
 * Karena itu lebih baik pulang dengan tangan kosong. Tiket tanpa
 * penanggung jawab tetap masuk antrean unit, masih terlihat, dan
 * terhitung dalam metrik tiket yang belum diambil.
 */
function fbResolveAssignee(int $level, string $track, ?int $categoryId): ?array {
    // Penyaring tunggal untuk semua jalur pencarian di bawah, supaya
    // tidak ada rute yang lupa diperiksa saat kode ini ditambah nanti.
    $layak = function (?array $row) use ($track): ?array {
        if (!$row || empty($row['user_id']))  return null;
        if ($track !== 'safeguarding')        return $row;

        $u = Database::fetchOne(
            "SELECT id, role FROM users WHERE id=? AND is_active=1", [$row['user_id']]);
        return ($u && fbCanSeeSafeguarding($u)) ? $row : null;
    };

    if ($categoryId) {
        $row = Database::fetchOne(
            "SELECT * FROM feedback_escalation_levels
             WHERE level=? AND category_id=? AND is_active=1 AND user_id IS NOT NULL
             ORDER BY order_num LIMIT 1", [$level, $categoryId]);
        if ($r = $layak($row)) return $r;
    }
    $row = Database::fetchOne(
        "SELECT * FROM feedback_escalation_levels
         WHERE level=? AND (track=? OR track IS NULL) AND category_id IS NULL
           AND is_active=1 AND user_id IS NOT NULL
         ORDER BY order_num LIMIT 1", [$level, $track]);
    if ($r = $layak($row)) return $r;

    // Cadangan: level 3 selalu jatuh ke Yayasan.
    if ($level >= 3) {
        // Untuk Kanal Yayasan, "Yayasan" berarti anggota unitnya —
        // bukan pengguna ber-role foundation dengan id terkecil, yang
        // dipilih tanpa memeriksa apakah ia boleh membacanya.
        if ($track === 'safeguarding') {
            return Database::fetchOne(
                "SELECT u.id AS user_id
                   FROM users u
                   JOIN user_groups ug ON ug.user_id = u.id
                   JOIN `groups` g     ON g.id = ug.group_id
                  WHERE u.is_active = 1
                    AND g.type = 'penanganan' AND g.name = 'Kanal Yayasan'
                  ORDER BY u.id LIMIT 1") ?: null;
        }
        $f = Database::fetchOne("SELECT id AS user_id FROM users WHERE role='foundation' AND is_active=1 ORDER BY id LIMIT 1");
        if ($f) return $f;
    }
    return null;
}

/** Semua penerima notifikasi pada satu level (bisa lebih dari satu). */
function fbLevelRecipients(int $level, string $track, ?int $categoryId): array {
    $rows = Database::fetchAll(
        "SELECT el.user_id, el.email, u.name, u.email AS user_email
         FROM feedback_escalation_levels el
         LEFT JOIN users u ON u.id = el.user_id
         WHERE el.level=? AND el.is_active=1
           AND (el.track=? OR el.track IS NULL)
           AND (el.category_id IS NULL OR el.category_id=?)",
        [$level, $track, $categoryId]);
    $out = [];
    foreach ($rows as $r) {
        $mail = $r['user_email'] ?: $r['email'];
        if ($mail) $out[$mail] = $r['name'] ?: $mail;
    }
    return $out;
}

// ── BUAT TIKET ──────────────────────────────────────────────

function fbCreateTicket(array $in): int {
    $cat = !empty($in['category_id'])
        ? Database::fetchOne("SELECT * FROM feedback_categories WHERE id=?", [$in['category_id']])
        : null;

    $track    = $cat['track'] ?? ($in['track'] ?? 'inquiry');
    $priority = fbComputePriority($cat['default_priority'] ?? 'P3', $in['impact'] ?? null, $track);
    $level    = (int)($cat['start_level'] ?? 1);

    // Penentuan penanggung jawab, berurutan:
    //   1. Kategori punya PIC tetap  → langsung dipegang PIC itu,
    //      tanpa perlu ada yang mengambil dari antrean lebih dulu.
    //   2. Kategori punya unit tanpa PIC → masuk ANTREAN unit,
    //      sengaja tanpa penanggung jawab supaya diambil anggota.
    //   3. Sisanya → jatuh ke rute eskalasi level awal.
    // Anggota unit tetap dijadikan pemantau pada semua kasus, jadi
    // tiket ber-PIC pun tetap terlihat oleh satu unit penuh.
    $unitId   = $cat['handler_group_id'] ?? null;
    $picTetap = $cat['default_pic_id'] ?? null;
    $assignee = $picTetap ? ['user_id' => $picTetap]
              : ($unitId ? null
                 : (fbResolveAssignee($level, $track, $in['category_id'] ?? null)
                    ?: ['user_id' => null]));
    $due      = fbComputeDueDates(['priority'=>$priority, 'track'=>$track], $cat);

    $isTester = ($_SESSION['user_role'] ?? '') === 'tester';

    $ticketNo = fbGenerateTicketNo($track);

    $id = Database::insert('feedback_tickets', [
        'ticket_no'           => $ticketNo,
        'track'               => $track,
        // Kunci opsional dibaca dengan ?? lebih dulu: pemanggil tidak
        // semuanya mengirim seluruh kolom — jalur publik, misalnya,
        // tidak mengenal apresiasi ke orang tertentu.
        'category_id'         => ($in['category_id'] ?? null) ?: null,
        'sender_id'           => $in['sender_id'] ?? null,
        'is_anonymous'        => !empty($in['is_anonymous']) ? 1 : 0,
        'subject'             => $in['subject'],
        'message'             => $in['message'],
        'impact'              => ($in['impact'] ?? null) ?: null,
        'priority'            => $priority,
        'status'              => 'baru',
        'level'               => $level,
        'assignee_id'         => $assignee['user_id'] ?? null,
        'appreciated_user_id' => ($in['appreciated_user_id'] ?? null) ?: null,
        'response_due_at'     => $track === 'apresiasi' ? null : $due['response_due_at'],
        'due_at'              => $track === 'apresiasi' ? null : $due['due_at'],
        'is_test'             => $isTester ? 1 : 0,
    ]);

    fbLogEvent($id, 'dibuat', null, $ticketNo,
        $isTester ? 'Dikirim oleh tester — tidak dihitung dalam metrik' : null);

    // Seluruh anggota unit otomatis memantau, agar antrean terlihat
    // walau belum ada yang mengambil.
    if ($unitId) {
        foreach (fbUnitMembers((int)$unitId) as $m) {
            Database::query(
                "INSERT IGNORE INTO feedback_watchers (ticket_id,user_id) VALUES (?,?)",
                [$id, $m['id']]);
        }
    }

    return $id;
}

// ── UBAH STATUS ─────────────────────────────────────────────

function fbSetStatus(int $ticketId, string $new, ?string $note = null): bool {
    $t = Database::fetchOne("SELECT * FROM feedback_tickets WHERE id=?", [$ticketId]);
    if (!$t || !isset(fbStatuses()[$new]) || $t['status'] === $new) return false;

    $upd = ['status' => $new];
    $now = date('Y-m-d H:i:s');

    // Respons pertama tercatat sekali
    if (empty($t['first_response_at']) && in_array($new, ['ditinjau','ditindaklanjuti'], true)) {
        $upd['first_response_at'] = $now;
    }

    // Jeda SLA saat menunggu pelapor
    if ($new === 'menunggu_pelapor') {
        $upd['paused_at'] = $now;
    } elseif ($t['status'] === 'menunggu_pelapor' && !empty($t['paused_at'])) {
        $paused = time() - strtotime($t['paused_at']);
        $upd['paused_seconds'] = (int)$t['paused_seconds'] + $paused;
        $upd['paused_at']      = null;
        if (!empty($t['due_at'])) {
            $upd['due_at'] = date('Y-m-d H:i:s', strtotime($t['due_at']) + $paused);
        }
    }

    if ($new === 'selesai') $upd['resolved_at'] = $now;
    if ($new === 'ditutup') $upd['closed_at']   = $now;
    if (in_array($t['status'], ['selesai','ditutup'], true) && !in_array($new, ['selesai','ditutup'], true)) {
        $upd['resolved_at'] = null;
        $upd['closed_at']   = null;
    }

    Database::update('feedback_tickets', $upd, 'id = ?', [$ticketId]);

    $evt = $new === 'selesai' ? 'diselesaikan'
         : ($new === 'ditutup' ? 'ditutup'
         : (in_array($t['status'], ['selesai','ditutup'], true) ? 'dibuka_kembali' : 'status_diubah'));
    fbLogEvent($ticketId, $evt, $t['status'], $new, $note);

    return true;
}

// ── ESKALASI ────────────────────────────────────────────────

function fbEscalate(int $ticketId, bool $automatic = false, ?string $reason = null): bool {
    $t = Database::fetchOne("SELECT * FROM feedback_tickets WHERE id=?", [$ticketId]);
    if (!$t || $t['level'] >= 3) return false;
    if (in_array($t['status'], ['selesai','ditutup'], true)) return false;

    $newLevel = (int)$t['level'] + 1;
    $cat = $t['category_id']
        ? Database::fetchOne("SELECT * FROM feedback_categories WHERE id=?", [$t['category_id']])
        : null;

    $assignee = fbResolveAssignee($newLevel, $t['track'], $t['category_id']);
    $due      = fbComputeDueDates($t, $cat);

    Database::update('feedback_tickets', [
        'level'       => $newLevel,
        'assignee_id' => $assignee['user_id'] ?? $t['assignee_id'],
        'due_at'      => $due['due_at'],
        'status'      => $t['status'] === 'baru' ? 'baru' : $t['status'],
    ], 'id = ?', [$ticketId]);

    // PIC lama otomatis jadi watcher agar tetap terpantau
    if (!empty($t['assignee_id'])) {
        Database::query(
            "INSERT IGNORE INTO feedback_watchers (ticket_id,user_id,added_by) VALUES (?,?,?)",
            [$ticketId, $t['assignee_id'], $_SESSION['user_id'] ?? null]);
    }

    fbLogEvent($ticketId, $automatic ? 'dieskalasi_otomatis' : 'dieskalasi_manual',
        'Level ' . $t['level'], 'Level ' . $newLevel, $reason);

    fbAddMessage($ticketId, null, sprintf(
        'Tiket dieskalasi %s dari %s ke %s.%s',
        $automatic ? 'otomatis karena melewati batas waktu' : 'secara manual',
        fbLevels()[$t['level']] ?? 'Level ' . $t['level'],
        fbLevels()[$newLevel]   ?? 'Level ' . $newLevel,
        $reason ? ' Alasan: ' . $reason : ''
    ), 'internal', true);

    fbNotifyEscalation($ticketId, $newLevel);
    return true;
}

/** Dipanggil cron atau saat halaman admin dibuka. Mengembalikan jumlah tiket yang naik level. */
function fbRunAutoEscalation(int $limit = 50): int {
    // LIMIT tidak boleh di-bind sebagai parameter karena PDO::ATTR_EMULATE_PREPARES = false.
    $limit = max(1, min(500, $limit));
    $rows = Database::fetchAll(
        "SELECT id FROM feedback_tickets
         WHERE status IN ('baru','ditinjau','ditindaklanjuti')
           AND level < 3 AND due_at IS NOT NULL AND due_at < NOW()
           AND track <> 'apresiasi'
         ORDER BY due_at ASC LIMIT $limit");
    $n = 0;
    foreach ($rows as $r) { if (fbEscalate((int)$r['id'], true)) $n++; }
    return $n;
}

// ── IZIN AKSES ──────────────────────────────────────────────

/**
 * Siapa yang boleh membaca Kanal Yayasan.
 *
 * Dulu berbasis peran: siapa pun ber-role foundation ikut membaca.
 * Itu terlalu longgar untuk kanal yang isinya termasuk persepsi
 * terhadap pimpinan sekolah — menambah satu pengurus yayasan berarti
 * diam-diam menambah pembaca.
 *
 * Sekarang berbasis daftar orang: keanggotaan unit 'Kanal Yayasan'.
 * Selain itu hanya superadmin yang lolos, demi pemeliharaan sistem —
 * ia toh memegang basis datanya, jadi menutup pintu ini tidak menutup
 * apa pun yang sungguh-sungguh tertutup baginya.
 *
 * Role pemantau sengaja dicabut (24 Agustus 2026). Peran itu dipegang
 * pihak ketiga di luar yayasan, sehingga aksesnya ke laporan
 * perlindungan anak tidak pernah diputuskan siapa pun — hanya
 * terwarisi dari definisi peran yang dibuat untuk keperluan lain.
 * Role foundation juga tidak lagi lolos: menambah satu pengurus
 * yayasan berarti diam-diam menambah pembaca.
 *
 * Menambah atau mencabut akses cukup lewat Admin CMS, tanpa
 * menyentuh kode. Kalau suatu saat tidak ada satu pun anggota unit,
 * tiket KY hanya terlihat superadmin — itu konsekuensi yang
 * dikehendaki, bukan kekeliruan.
 */
function fbCanSeeSafeguarding(?array $u = null): bool {
    if (($u['role'] ?? ($_SESSION['user_role'] ?? '')) === 'superadmin') return true;

    $uid = (int)($u['id'] ?? ($_SESSION['user_id'] ?? 0));
    if ($uid <= 0) return false;

    // Dijawab sekali per permintaan: fungsi ini dipanggil untuk
    // setiap baris pada daftar tiket.
    static $ingatan = [];
    if (array_key_exists($uid, $ingatan)) return $ingatan[$uid];

    $ingatan[$uid] = (bool)Database::fetchOne(
        "SELECT 1 FROM user_groups ug
           JOIN `groups` g ON g.id = ug.group_id
          WHERE ug.user_id = ? AND g.type = 'penanganan'
            AND g.name = 'Kanal Yayasan' LIMIT 1", [$uid]);

    return $ingatan[$uid];
}

/**
 * Tiket yang diberikan kepada seseorang satu per satu, dengan
 * menjadikannya pemantau secara sengaja.
 *
 * Keanggotaan unit memberi seluruh tiket unit itu sekaligus, dan itu
 * terlalu kasar untuk keputusan seperti "Dewi Amri boleh membuka
 * KY-2026-0010" yang diambil di rapat internal. Jalur ini menyediakan
 * takaran yang lebih halus: satu tiket, satu orang.
 *
 * Menjadikan seseorang pemantau setara dengan menjadikannya
 * penanggung jawab — keduanya perbuatan sengaja oleh orang yang
 * berwenang, dan keduanya memberi hak yang sama: membuka dan
 * menjawab.
 *
 * Dua penjaga supaya tidak berubah jadi pintu belakang:
 *
 *   1. Hanya baris pengintai yang ada `added_by`-nya yang dihitung.
 *      Anggota unit yang ditambahkan otomatis saat tiket dibuat
 *      ber-`added_by` NULL, jadi tidak ikut memberi akses kepada
 *      siapa pun di luar aturan unit.
 *   2. Untuk jalur Kanal Yayasan, pemberinya harus orang yang sendiri
 *      boleh membacanya — superadmin atau anggota unit. Orang tidak
 *      bisa membagikan apa yang ia sendiri tidak boleh lihat. Jalur
 *      lain tidak perlu syarat ini karena aksesnya memang sudah luas.
 *
 * Pemberian semacam ini muncul tersendiri di tools/cek-akses-ky.php,
 * lengkap dengan siapa yang memberi, supaya tidak menumpuk tanpa ada
 * yang menengok.
 */
function fbTiketDiberikan(int $userId): array {
    if ($userId <= 0) return [];

    static $ingatan = [];
    if (array_key_exists($userId, $ingatan)) return $ingatan[$userId];

    $rows = Database::fetchAll(
        "SELECT DISTINCT w.ticket_id
           FROM feedback_watchers w
           JOIN feedback_tickets t ON t.id = w.ticket_id
           JOIN users p            ON p.id = w.added_by
           LEFT JOIN user_groups ug ON ug.user_id = p.id
           LEFT JOIN `groups` g     ON g.id = ug.group_id
                                   AND g.type = 'penanganan'
                                   AND g.name = 'Kanal Yayasan'
          WHERE w.user_id = ?
            AND (t.track <> 'safeguarding'
                 OR p.role = 'superadmin'
                 OR g.id IS NOT NULL)", [$userId]);

    return $ingatan[$userId] = array_map('intval', array_column($rows, 'ticket_id'));
}

/**
 * Tiket Kanal Yayasan yang terjangkau seseorang di luar keanggotaan
 * unit — entah karena dijadikan pemantau, entah karena dijadikan
 * penanggung jawab. Dipakai inbox untuk membuka jalur safeguarding
 * baris demi baris tanpa membuka seluruhnya.
 */
function fbTiketKyTerjangkau(int $userId): array {
    if ($userId <= 0) return [];

    $rows = Database::fetchAll(
        "SELECT id FROM feedback_tickets
          WHERE track = 'safeguarding' AND assignee_id = ?", [$userId]);
    $hasil = array_map('intval', array_column($rows, 'id'));

    $diberi = fbTiketDiberikan($userId);
    if ($diberi) {
        $ph  = implode(',', array_fill(0, count($diberi), '?'));
        $row2 = Database::fetchAll(
            "SELECT id FROM feedback_tickets
              WHERE track = 'safeguarding' AND id IN ($ph)", $diberi);
        $hasil = array_merge($hasil, array_map('intval', array_column($row2, 'id')));
    }

    return array_values(array_unique($hasil));
}

function fbCanManage(?array $u = null): bool {
    $role = $u['role'] ?? ($_SESSION['user_role'] ?? '');
    return in_array($role, ['superadmin','admin','foundation'], true);
}

/**
 * Apakah pengguna ini penangan tiket, yaitu anggota minimal satu
 * unit penanganan. Keanggotaan unit — bukan role — yang menentukan
 * seseorang punya antrean untuk dikerjakan.
 */
function fbIsHandler(?array $u = null): bool {
    $uid = (int)($u['id'] ?? ($_SESSION['user_id'] ?? 0));
    return $uid > 0 && fbUserUnits($uid) !== [];
}

/**
 * Track apa saja yang boleh dilihat di inbox oleh pengguna ini.
 *
 * Dua lapis, dan sengaja dipisah:
 *   1. Boleh membuka inbox sama sekali — ditentukan peran atau
 *      keanggotaan unit penanganan mana pun.
 *   2. Boleh melihat jalur safeguarding — ditentukan HANYA oleh
 *      fbCanSeeSafeguarding(), yaitu keanggotaan unit Kanal Yayasan.
 *
 * Sebelumnya lapis kedua ikut ditentukan peran, sehingga role
 * foundation membaca laporan perlindungan anak tanpa pernah
 * dimasukkan ke unitnya. Satu sumber kebenaran menutup celah itu.
 */
function fbAllowedTracks(?array $u = null): array {
    $role = $u['role'] ?? ($_SESSION['user_role'] ?? '');

    $boleh = in_array($role, ['superadmin','foundation','pemantau','admin','leader'], true)
          || fbIsHandler($u);
    if (!$boleh) return [];

    $tracks = ['apresiasi','inquiry'];
    if (fbCanSeeSafeguarding($u)) $tracks[] = 'safeguarding';
    return $tracks;
}

function fbCanView(array $t, array $u): bool {
    // Pemantau: melihat segalanya KECUALI Kanal Yayasan, dan mengubah
    // tidak satu pun. Kemampuan bertindak dijaga terpisah lewat
    // fbCanManage() dan pemeriksaan $canAct di halaman tiket.
    //
    // Pengecualian safeguarding harus ada di sini, bukan hanya di
    // fbAllowedTracks(): baris ini keluar lebih dulu dari seluruh
    // pemeriksaan track di bawah, jadi tanpa penjaga ini tiket KY
    // masih terbuka lewat tautan langsung meski hilang dari inbox.
    if (($u['role'] ?? '') === 'pemantau') {
        return $t['track'] !== 'safeguarding' || fbCanSeeSafeguarding($u);
    }

    if ((int)$t['sender_id'] === (int)$u['id'])   return true;

    // Dua perbuatan sengaja memberi hak yang sama atas satu tiket:
    // dijadikan pemantau, atau dijadikan penanggung jawab. Keduanya
    // dilakukan orang berwenang, keduanya berarti boleh membuka dan
    // menjawab, dan keduanya diperiksa lebih dulu daripada aturan
    // jalur di bawah — sebab justru aturan jalur itulah yang
    // dikecualikan untuk tiket yang bersangkutan.
    if (in_array((int)$t['id'], fbTiketDiberikan((int)$u['id']), true)) return true;

    // Menugaskan seseorang tanpa memberinya akses menghasilkan tiket
    // yang tampak tertangani padahal pemegangnya hanya mendapat 403.
    // Penugasan otomatis tidak bisa menyelundup lewat sini:
    // fbResolveAssignee() menolak memilih orang yang tak boleh
    // membaca, sehingga penanggung jawab KY di luar unit pasti hasil
    // penunjukan manusia.
    if ((int)($t['assignee_id'] ?? 0) === (int)$u['id']) return true;
    // Anggota unit penanganan boleh melihat antrean unitnya
    $unit = fbTicketUnit($t);
    if ($unit && fbIsUnitMember((int)$unit['id'], (int)$u['id'])) {
        if ($t['track'] === 'safeguarding' && !fbCanSeeSafeguarding($u)) return false;
        return true;
    }

    if (!in_array($t['track'], fbAllowedTracks($u), true)) return false;
    if (in_array($u['role'], ['superadmin','foundation'], true)) return true;
    if ($u['role'] === 'admin') return true;
    if ($u['role'] === 'leader') return (int)$t['level'] >= 2;

    return (bool)Database::fetchOne(
        "SELECT 1 FROM feedback_watchers WHERE ticket_id=? AND user_id=?", [$t['id'], $u['id']]);
}

/** Konflik kepentingan: tidak boleh menangani tiket tentang diri sendiri. */
function fbHasConflict(array $t, array $u): bool {
    return (int)($t['appreciated_user_id'] ?? 0) === (int)$u['id'] && $t['track'] !== 'apresiasi';
}

/** Sembunyikan identitas pelapor sesuai aturan anonimitas. */
/**
 * Apakah identitas pelapor tiket ini sudah dibuka secara resmi.
 *
 * Jawabannya dibaca dari feedback_events, bukan dari kolom tersendiri
 * maupun dari sesi. Alasannya: peristiwa 'identitas_dibuka' sudah
 * memuat siapa yang membuka, kapan, alasannya, dan dari alamat IP
 * mana — dan tidak dapat dihapus lewat antarmuka. Menjadikannya
 * satu-satunya penentu berarti tidak mungkin ada identitas terbuka
 * yang tidak ada catatannya.
 *
 * Sebelumnya penandanya disimpan di $_SESSION, sehingga terbatas pada
 * satu peramban dan hilang saat keluar. Padahal keputusan membuka
 * identitas adalah keputusan atas tiketnya, bukan atas sesi seseorang.
 */
function fbIdentitasDibuka(int $ticketId): bool {
    if ($ticketId <= 0) return false;

    static $ingatan = [];
    if (array_key_exists($ticketId, $ingatan)) return $ingatan[$ticketId];

    return $ingatan[$ticketId] = (bool)Database::fetchOne(
        "SELECT 1 FROM feedback_events
          WHERE ticket_id = ? AND event_type = 'identitas_dibuka' LIMIT 1", [$ticketId]);
}

/**
 * Sembunyikan identitas pelapor sesuai aturan anonimitas.
 *
 * Urutan pertanyaannya:
 *   1. Pelapor melihat tiketnya sendiri — selalu boleh.
 *   2. Identitas belum dibuka — tersamar bagi semua orang, termasuk
 *      superadmin. Ini yang menepati kalimat pada formulir publik:
 *      "hanya dapat dibuka oleh pengelola sistem ... setiap pembukaan
 *      tercatat". Sebelumnya superadmin melihat nama begitu saja
 *      tanpa menekan tombolnya, sehingga catatannya tidak pernah ada.
 *   3. Identitas sudah dibuka — terlihat oleh superadmin dan
 *      penanggung jawab tiket, tidak oleh yang lain.
 *
 * Penanggung jawab ikut melihat karena dialah yang menindaklanjuti;
 * membuka identitas tanpa memberitahunya membuat pembukaan itu tidak
 * ada gunanya. Konsekuensinya, mengganti penanggung jawab sesudah
 * pembukaan berarti memindahkan akses itu juga — itu memang wajar,
 * tetapi perlu disadari saat menugaskan ulang.
 */
function fbSenderDisplay(array $t, array $viewer): array {
    $tamu    = empty($t['sender_id']);
    $vid     = (int)($viewer['id'] ?? 0);
    $sendiri = $vid > 0 && (int)($t['sender_id'] ?? 0) === $vid;

    if (!empty($t['is_anonymous']) && !$sendiri) {
        $boleh = fbIdentitasDibuka((int)($t['id'] ?? 0))
              && (($viewer['role'] ?? '') === 'superadmin'
                  || ($vid > 0 && (int)($t['assignee_id'] ?? 0) === $vid));

        if (!$boleh) {
            return ['name'=>'Pelapor Anonim', 'email'=>null, 'role'=>null,
                    'masked'=>true, 'tamu'=>$tamu];
        }
    }

    // Tiket dari formulir publik: tidak ada baris users di baliknya.
    // Identitasnya diisi sendiri oleh pelapor dan TIDAK diverifikasi
    // — penanganan harus tahu itu sebelum menindaklanjuti.
    //
    // Ditandai dari sender_id yang kosong saja, bukan dari ada
    // tidaknya guest_email. Pelapor publik yang tidak meninggalkan
    // alamat surel tetap punya nama yang perlu ditampilkan; syarat
    // guest_email dulu membuat namanya jatuh ke cabang pengguna
    // ber-akun dan tampil sebagai tanda pisah.
    if ($tamu) {
        return [
            'name'   => ($t['guest_name'] ?? '') ?: 'Pelapor Publik',
            'email'  => ($t['guest_email'] ?? '') ?: null,
            'role'   => ($t['guest_role'] ?? '') ?: 'Publik',
            'masked' => false,
            'tamu'   => true,
        ];
    }

    return [
        'name'   => ($t['sender_name'] ?? '') ?: '—',
        'email'  => $t['sender_email'] ?? null,
        'role'   => $t['sender_role'] ?? null,
        'masked' => false,
        'tamu'   => false,
    ];
}

// ── LAMPIRAN ────────────────────────────────────────────────

function fbUploadDir(): string {
    if (defined('FEEDBACK_UPLOAD_DIR')) return rtrim(FEEDBACK_UPLOAD_DIR, '/');
    // BASE_PATH = .../public_html/app  →  naik dua tingkat = akar repo (di luar webroot)
    return dirname(dirname(BASE_PATH)) . '/storage/feedback';
}

function fbAllowedMimes(): array {
    return [
        'image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp',
        'application/pdf'=>'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx',
        'audio/mpeg'=>'mp3', 'audio/mp4'=>'m4a', 'video/mp4'=>'mp4',
    ];
}

const FB_MAX_FILE_BYTES = 10485760; // 10 MB
const FB_MAX_FILES      = 5;

/** @return array{ok:bool, error?:string, id?:int} */
function fbStoreUpload(int $ticketId, array $file, ?int $messageId, bool $sealed): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['ok'=>false,'error'=>'Berkas gagal diunggah.'];
    if ($file['size'] > FB_MAX_FILE_BYTES)                        return ['ok'=>false,'error'=>'Ukuran melebihi 10 MB.'];

    $count = Database::fetchOne("SELECT COUNT(*) c FROM feedback_attachments WHERE ticket_id=?", [$ticketId])['c'];
    if ($count >= FB_MAX_FILES) return ['ok'=>false,'error'=>'Maksimal ' . FB_MAX_FILES . ' lampiran per tiket.'];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']) ?: '';
    $allowed = fbAllowedMimes();
    if (!isset($allowed[$mime])) return ['ok'=>false,'error'=>'Tipe berkas tidak diizinkan.'];

    $dir = fbUploadDir();
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    if (!is_dir($dir) || !is_writable($dir)) return ['ok'=>false,'error'=>'Direktori penyimpanan tidak siap.'];

    // Lapisan kedua kalau direktori telanjur berada di dalam webroot
    $ht = $dir . '/.htaccess';
    if (!file_exists($ht)) @file_put_contents($ht, "Require all denied\nphp_flag engine off\n");

    $stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
        return ['ok'=>false,'error'=>'Gagal menyimpan berkas.'];
    }
    @chmod($dir . '/' . $stored, 0640);

    $id = Database::insert('feedback_attachments', [
        'ticket_id'     => $ticketId,
        'message_id'    => $messageId,
        'uploader_id'   => $_SESSION['user_id'] ?? null,
        'original_name' => mb_substr($file['name'], 0, 255),
        'stored_name'   => $stored,
        'mime'          => $mime,
        'size_bytes'    => (int)$file['size'],
        'sha256'        => hash_file('sha256', $dir . '/' . $stored),
        'is_sealed'     => $sealed ? 1 : 0,
    ]);

    fbLogEvent($ticketId, 'lampiran_diunggah', null, $file['name']);
    return ['ok'=>true, 'id'=>$id];
}

function fbFormatBytes(int $b): string {
    if ($b >= 1048576) return round($b / 1048576, 1) . ' MB';
    if ($b >= 1024)    return round($b / 1024) . ' KB';
    return $b . ' B';
}

// ── NOTIFIKASI EMAIL ────────────────────────────────────────

/**
 * Satu-satunya pintu keluar email dari platform.
 *
 * SEMUA pengiriman wajib lewat sini — notifikasi tiket, eskalasi,
 * blast, maupun kampanye. Kalau ada kode yang memanggil Apps Script
 * langsung, gerbang mode demo terlewati dan email benar-benar
 * terkirim dari lingkungan peragaan.
 *
 * Mengembalikan true bila dianggap berhasil ditangani.
 */
/**
 * Alamat balasan. Email tanpa Reply-To datang dari kotak yang tidak
 * bisa dibalas — merepotkan penerima sekaligus menurunkan reputasi
 * pengirim. Bisa ditimpa lewat MAIL_REPLY_TO di config.php.
 */
function fbReplyTo(): string {
    return defined('MAIL_REPLY_TO') && MAIL_REPLY_TO
         ? MAIL_REPLY_TO
         : 'info@sma-ktb.sch.id';
}

/**
 * Padanan teks biasa dari badan HTML.
 *
 * Bukan sekadar strip_tags: tautan diubah jadi "teks (alamat)"
 * supaya tombol ajakan tidak lenyap tanpa jejak bagi pembaca yang
 * klien emailnya hanya menampilkan teks.
 */
function fbTeksBiasa(string $html): string {
    $s = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);
    $s = preg_replace('#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is',
                      '$2 ($1)', $s);
    $s = preg_replace('#<br\s*/?>#i', "\n", $s);
    $s = preg_replace('#</(p|div|tr|h[1-6])>#i', "\n\n", $s);
    $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = preg_replace('/[ \t]+/', ' ', $s);
    $s = preg_replace('/\n{3,}/', "\n\n", $s);
    return trim($s);
}

/**
 * Catat setiap percobaan kirim ke email_blast_log.
 *
 * Sebelumnya hanya kampanye yang tercatat, sementara notifikasi
 * tiket dikirim lalu dilupakan — termasuk saat gagal. Akibatnya
 * pertanyaan "apakah email ini benar terkirim" tidak bisa dijawab
 * sama sekali. Kolom campaign_code dipakai menandai jenisnya.
 *
 * Kegagalan mencatat tidak boleh menggagalkan pengiriman, jadi
 * seluruhnya dibungkus try.
 */
function fbCatatKirim(string $to, string $subject, bool $ok, string $jenis): void {
    try {
        $u = Database::fetchOne("SELECT id, role FROM users WHERE email = ? LIMIT 1", [$to]);
        Database::insert('email_blast_log', [
            'campaign_code'   => $jenis,
            'blast_type'      => $u['role'] ?? 'luar',
            'recipient_id'    => $u['id'] ?? null,
            'recipient_email' => mb_substr($to, 0, 100),
            'subject'         => mb_substr($subject, 0, 255),
            'status'          => $ok ? 'sent' : 'failed',
            'sent_by'         => $_SESSION['user_id'] ?? null,
        ]);
    } catch (Throwable $e) {
        @error_log('[AGKB] gagal mencatat kiriman ke ' . $to . ': ' . $e->getMessage());
    }
}

/**
 * @param ?string $catat Jenis kiriman untuk dicatat. null berarti
 *                       jangan catat — dipakai kampanye, yang sudah
 *                       punya pencatatannya sendiri lewat kmpCatat().
 */
function fbSendMail(string $to, string $subject, string $htmlBody, ?string $catat = 'notifikasi'): bool {
    if (!$to) return false;

    // Mode demo: email tidak pernah benar-benar dikirim.
    // Aktifkan dengan define('FEEDBACK_DEMO_MODE', true); di config.php.
    if (defined('FEEDBACK_DEMO_MODE') && FEEDBACK_DEMO_MODE) {
        @error_log('[AGKB demo] email ditahan — tujuan: ' . $to . ' · subjek: ' . $subject);
        return true;
    }

    $url = defined('APPS_SCRIPT_URL') ? APPS_SCRIPT_URL : '';
    if (!$url) {
        @error_log('[AGKB] APPS_SCRIPT_URL belum diatur — email ke ' . $to . ' tidak terkirim.');
        if ($catat) fbCatatKirim($to, $subject, false, $catat);
        return false;
    }

    $payload = json_encode([
        'to'       => $to,
        'subject'  => $subject,
        'htmlBody' => $htmlBody,
        'body'     => fbTeksBiasa($htmlBody),
        'replyTo'  => fbReplyTo(),
    ]);
    $res = @file_get_contents($url, false, stream_context_create([
        'http' => ['method' => 'POST', 'header' => 'Content-Type: application/json',
                   'content' => $payload, 'timeout' => 15]
    ]));

    // Gerbang membalas JSON {ok:true|false}. Balasan yang bukan ok
    // berarti MailApp menolaknya — misalnya kuota harian habis —
    // dan itu berbeda dari kegagalan menghubungi gerbangnya.
    $ok = $res !== false;
    if ($ok) {
        $j = json_decode((string)$res, true);
        if (is_array($j) && array_key_exists('ok', $j) && !$j['ok']) {
            $ok = false;
            @error_log('[AGKB] gerbang menolak email ke ' . $to . ': ' . (string)$res);
        }
    } else {
        @error_log('[AGKB] gagal menghubungi gerbang email untuk ' . $to);
    }

    if ($catat) fbCatatKirim($to, $subject, $ok, $catat);
    return $ok;
}

/** Apakah lingkungan ini menahan email (peragaan / tanpa gerbang). */
function fbEmailDitahan(): bool {
    if (defined('FEEDBACK_DEMO_MODE') && FEEDBACK_DEMO_MODE) return true;
    return !defined('APPS_SCRIPT_URL') || !APPS_SCRIPT_URL;
}

function fbAppUrl(): string {
    $host = defined('PUBLIC_BASE_URL') ? PUBLIC_BASE_URL : 'https://agkb360.app';
    return rtrim($host, '/') . APP_URL;
}

/** Template email AGKB 360°. */
function fbMailTemplate(string $heading, string $bodyHtml, ?string $ctaUrl = null, ?string $ctaLabel = null, string $accent = '#ff9101'): string {
    $cta = '';
    if ($ctaUrl && $ctaLabel) {
        $cta = '<tr><td style="padding:22px 32px;text-align:center">'
             . '<a href="' . $ctaUrl . '" style="display:inline-block;background:#040136;color:#ffffff;text-decoration:none;padding:13px 28px;border-radius:8px;font-size:14px;font-weight:700">' . h($ctaLabel) . ' &rarr;</a>'
             . '</td></tr>';
    }
    return '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"></head>'
      . '<body style="margin:0;padding:0;background:#f3f4f6;font-family:\'Host Grotesk\',-apple-system,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif">'
      . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px"><tr><td align="center">'
      . '<table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e3e5ea">'
      . '<tr><td style="background:#040136;padding:26px 32px;text-align:center;border-bottom:3px solid ' . $accent . '">'
      . '<img src="' . fbAppUrl() . '/assets/img/brand/agkb-mark.png" width="38" height="38" alt="" style="display:block;margin:0 auto 10px;border:0">'
      . '<div style="font-size:24px;font-weight:700;color:#ffffff;letter-spacing:-.5px">AGKB <span style="color:#ff9101">360&deg;</span></div>'
      . '<div style="font-size:10px;color:rgba(255,255,255,.65);margin-top:4px;letter-spacing:1px;text-transform:uppercase">Platform Evaluasi Kinerja</div>'
      . '</td></tr>'
      . '<tr><td style="padding:26px 32px 0"><h2 style="margin:0;font-size:18px;font-weight:700;color:#040136">' . h($heading) . '</h2></td></tr>'
      . '<tr><td style="padding:14px 32px 0;font-size:14px;color:#2f2d4d;line-height:1.75">' . $bodyHtml . '</td></tr>'
      . $cta
      . '<tr><td style="background:#fafafb;padding:14px 32px;border-top:1px solid #e3e5ea;text-align:center">'
      . '<div style="font-size:11px;color:#6f6e85">Email otomatis dari AGKB 360&deg; &bull; Yayasan Pendidikan Kader Bangsa Indonesia</div>'
      . '</td></tr></table></td></tr></table></body></html>';
}

function fbTicketMetaHtml(array $t): string {
    $cat = $t['category_name'] ?? '—';
    $pri = fbPriorities()[$t['priority']]['label'] ?? $t['priority'];
    return '<table cellpadding="0" cellspacing="0" style="margin:14px 0;font-size:13px;color:#2f2d4d">'
        . '<tr><td style="padding:3px 14px 3px 0;color:#6b6a83">Nomor tiket</td><td><strong>' . h($t['ticket_no']) . '</strong></td></tr>'
        . '<tr><td style="padding:3px 14px 3px 0;color:#6b6a83">Kategori</td><td>' . h($cat) . '</td></tr>'
        . '<tr><td style="padding:3px 14px 3px 0;color:#6b6a83">Prioritas</td><td>' . h($pri) . '</td></tr>'
        . '</table>';
}

function fbLoadFull(int $id): ?array {
    return Database::fetchOne(
        "SELECT t.*, c.name AS category_name, c.track AS category_track,
                s.name AS sender_name, s.email AS sender_email, s.role AS sender_role,
                a.name AS assignee_name, a.email AS assignee_email
         FROM feedback_tickets t
         LEFT JOIN feedback_categories c ON c.id = t.category_id
         LEFT JOIN users s ON s.id = t.sender_id
         LEFT JOIN users a ON a.id = t.assignee_id
         WHERE t.id = ?", [$id]);
}

/**
 * Tembusan tetap — pihak yang selalu ikut diberi tahu, di luar
 * rute kategori mana pun.
 *
 * Sengaja hardcode dan bukan tabel: ini pengaturan sementara masa
 * pengembangan, dan menaruhnya di sini membuatnya terlihat jelas
 * saat waktunya dicabut. Pindahkan ke Admin CMS begitu susunan
 * Customer Care sudah tetap.
 *
 * Jalur safeguarding hanya menembus ke pengembang, karena penerima
 * laporan perlindungan anak belum ditetapkan kebijakannya.
 */
function fbTembusanTetap(string $track): array {
    $out = ['aulia.rachman@kaderbangsa.foundation' => 'Aulia (Pengembang)'];

    if ($track !== 'safeguarding') {
        $out['tasya.intern@kaderbangsa.foundation'] = 'Tasya (Customer Care)';
    }
    return $out;
}

// ── AKTIVASI PENERIMA YANG BELUM PUNYA KATA SANDI ───────────

/**
 * Apakah akun ini belum pernah menetapkan kata sandi.
 *
 * Akun PIC dibuat lewat migrasi dengan isian acak yang bukan hasil
 * password_hash(), sehingga password_get_info() tidak mengenali
 * algoritmanya. Itu penanda yang jauh lebih jujur daripada menebak
 * dari last_login, yang bisa kosong walau kata sandinya sudah ada.
 */
function fbPerluAktivasi(array $u): bool {
    $info = password_get_info((string)($u['password'] ?? ''));
    return empty($info['algo']);
}

/**
 * Kirim tautan pembuatan kata sandi, khusus untuk satu orang.
 *
 * SENGAJA email tersendiri, bukan disisipkan ke notifikasi tiket.
 * Notifikasi tiket berbadan sama untuk semua penerima dan masih
 * di-BCC ke alamat arsip — token pribadi di dalamnya berarti siapa
 * pun bisa menetapkan kata sandi akun orang lain.
 *
 * Token yang masih berlaku tidak diterbitkan ulang, supaya orang
 * yang belum sempat mengaktifkan tidak dibanjiri email tiap kali
 * ada tiket baru.
 */
function fbKirimAktivasi(array $u, ?string $tujuan = null): bool {
    if (!fbPerluAktivasi($u) || empty($u['email'])) return false;

    $adaToken = Database::fetchOne(
        "SELECT password_reset_token FROM users
          WHERE id=? AND password_reset_token IS NOT NULL AND token_expires_at > NOW()",
        [$u['id']]);
    if ($adaToken) return false;

    $token = bin2hex(random_bytes(32));
    Database::query(
        "UPDATE users SET password_reset_token=?, token_expires_at=? WHERE id=?",
        [$token, date('Y-m-d H:i:s', strtotime('+7 days')), $u['id']]);

    $url = fbAppUrl() . '/auth/set-password.php?token=' . $token;
    if ($tujuan) $url .= '&next=' . rawurlencode($tujuan);

    $body = '<p>Halo ' . h($u['name']) . ',</p>'
          . '<p>Akun AGKB 360° atas nama Anda sudah terdaftar sebagai penanggung jawab, '
          . 'tetapi kata sandinya belum pernah dibuat. Karena itu Anda belum bisa membuka '
          . 'tiket yang ditujukan kepada Anda.</p>'
          . '<p>Buat kata sandi lewat tombol di bawah. Sesudahnya Anda akan langsung '
          . 'diarahkan ke halaman yang dituju.</p>'
          . '<p style="font-size:12px;color:#6b6a83">Tautan ini berlaku 7 hari dan hanya '
          . 'untuk Anda — jangan diteruskan kepada siapa pun.</p>';

    return fbSendMail($u['email'], 'Buat kata sandi akun AGKB 360° Anda',
        fbMailTemplate('Akun Anda belum aktif', $body, $url, 'Buat Kata Sandi'));
}

/**
 * Untuk setiap alamat penerima yang ternyata milik akun belum aktif,
 * kirimkan tautan aktivasi pribadinya.
 */
function fbAktifkanPenerima(array $emails, ?string $tujuan = null): void {
    $emails = array_values(array_filter(array_unique($emails)));
    if (!$emails) return;

    $ph   = implode(',', array_fill(0, count($emails), '?'));
    $baris = Database::fetchAll(
        "SELECT id, name, email, password FROM users
          WHERE email IN ($ph) AND is_active = 1", $emails);

    foreach ($baris as $u) {
        try { fbKirimAktivasi($u, $tujuan); }
        catch (Throwable $e) {
            @error_log('[AGKB aktivasi] gagal untuk ' . $u['email'] . ': ' . $e->getMessage());
        }
    }
}

function fbNotifyNew(int $ticketId): void {
    $t = fbLoadFull($ticketId);
    if (!$t || $t['is_test']) return;

    $link  = fbAppUrl() . '/admin/ticket.php?id=' . $t['id'];
    $who   = $t['is_anonymous']
           ? 'Pelapor Anonim'
           : ($t['sender_name']
              ?? (!empty($t['guest_name'])
                  ? $t['guest_name'] . ' (publik — identitas belum diverifikasi)'
                  : '—'));
    $isSg  = $t['track'] === 'safeguarding';

    $body = ($isSg
        ? '<div style="background:#fdeceb;border:1px solid #f3b5b0;border-radius:8px;padding:12px 14px;color:#b42318;font-weight:600;margin-bottom:12px">Laporan Perlindungan Anak — memerlukan tindak lanjut dalam 24 jam.</div>'
        : '')
      . '<p style="margin:0 0 6px">Tiket baru masuk dari <strong>' . h($who) . '</strong>.</p>'
      . fbTicketMetaHtml($t)
      . '<div style="font-size:11px;font-weight:600;color:#6b6a83;text-transform:uppercase;letter-spacing:.5px;margin:14px 0 6px">Subjek</div>'
      . '<div style="font-size:15px;font-weight:600;color:#040136">' . h($t['subject']) . '</div>'
      . '<div style="font-size:14px;color:#2f2d4d;line-height:1.8;background:#fafafb;border-radius:8px;padding:14px;border-left:3px solid #040136;margin-top:10px">'
      . nl2br(h(mb_substr($t['message'], 0, 600))) . '</div>';

    $subject = ($isSg ? '[AGKB 360° · PRIORITAS] ' : '[AGKB 360°] ') . $t['ticket_no'] . ' — ' . $t['subject'];
    $html    = fbMailTemplate('Tiket Baru', $body, $link, 'Buka Tiket', $isSg ? '#b42318' : '#ff9101');

    $tujuan = fbLevelRecipients((int)$t['level'], $t['track'], $t['category_id'] ? (int)$t['category_id'] : null);

    // Seluruh anggota unit penanganan ikut diberi tahu — tiket masuk antrean mereka
    $unit = fbTicketUnit($t);
    if ($unit) {
        foreach (fbUnitMembers((int)$unit['id']) as $m) {
            if (!empty($m['email'])) $tujuan[$m['email']] = $m['name'];
        }
    }
    if (!empty($t['assignee_email'])) $tujuan[$t['assignee_email']] = $t['assignee_name'] ?? '';

    $tujuan += fbTembusanTetap($t['track']);

    foreach ($tujuan as $mail => $name) fbSendMail($mail, $subject, $html);

    // Penerima yang akunnya belum pernah diaktifkan tidak akan bisa
    // membuka tautan di atas. Mereka mendapat email tersendiri berisi
    // tautan pribadi yang, setelah kata sandi dibuat, mengantar
    // langsung ke tiket ini.
    fbAktifkanPenerima(array_keys($tujuan), APP_URL . '/admin/ticket.php?id=' . $t['id']);
}

function fbNotifyEscalation(int $ticketId, int $newLevel): void {
    $t = fbLoadFull($ticketId);
    if (!$t || $t['is_test']) return;

    $body = '<p style="margin:0 0 6px">Tiket ini dieskalasi ke <strong>' . h(fbLevels()[$newLevel] ?? "Level $newLevel") . '</strong> karena melewati batas waktu penanganan.</p>'
          . fbTicketMetaHtml($t)
          . '<div style="font-size:15px;font-weight:600;color:#040136;margin-top:10px">' . h($t['subject']) . '</div>';

    $html = fbMailTemplate('Tiket Dieskalasi', $body, fbAppUrl() . '/admin/ticket.php?id=' . $t['id'], 'Tangani Sekarang', '#ee4c01');
    $tujuan = fbLevelRecipients($newLevel, $t['track'], $t['category_id'] ? (int)$t['category_id'] : null)
            + fbTembusanTetap($t['track']);
    foreach ($tujuan as $mail => $n) {
        fbSendMail($mail, '[AGKB 360° · ESKALASI] ' . $t['ticket_no'] . ' — ' . $t['subject'], $html);
    }
}

/**
 * Alamat pelapor, apa pun jalurnya.
 *
 * Tiket dari formulir publik tidak punya baris users, jadi
 * sender_email-nya NULL. Semua notifikasi ke pelapor harus lewat
 * sini — kalau tidak, pelapor tamu tidak pernah dikabari apa pun.
 */
function fbPelaporEmail(array $t): ?string {
    return $t['sender_email'] ?: ($t['guest_email'] ?? null) ?: null;
}

/**
 * Tautan yang benar-benar bisa dibuka pelapor.
 * Pelapor tamu tidak punya akun, jadi diarahkan ke halaman
 * pelacakan bertoken, bukan ke halaman yang menuntut login.
 */
function fbPelaporUrl(array $t): string {
    if (empty($t['sender_id']) && !empty($t['guest_token'])) {
        return fbAppUrl() . '/publik/lacak.php?t=' . $t['guest_token'];
    }
    return fbAppUrl() . '/feedback/view.php?id=' . $t['id'];
}

function fbNotifyStatus(int $ticketId, string $newStatus, ?string $catatan = null): void {
    $t = fbLoadFull($ticketId);
    if (!$t || $t['is_test']) return;
    $to = fbPelaporEmail($t);
    if (!$to) return;

    $label = fbStatuses()[$newStatus]['label'] ?? $newStatus;
    $body  = '<p style="margin:0 0 6px">Status laporan Anda kini <strong>' . h($label) . '</strong>.</p>'
           . fbTicketMetaHtml($t)
           . '<div style="font-size:15px;font-weight:600;color:#040136">' . h($t['subject']) . '</div>';

    // Catatan yang ditulis saat mengubah status dulu hanya masuk ke
    // log peristiwa dan tidak pernah sampai ke pelapor — padahal di
    // situlah penjelasannya berada.
    if ($catatan !== null && trim($catatan) !== '') {
        $body .= '<div style="font-size:11px;font-weight:600;color:#6b6a83;text-transform:uppercase;'
              . 'letter-spacing:.5px;margin:16px 0 6px">Keterangan</div>'
              . '<div style="font-size:14px;color:#2f2d4d;line-height:1.8;background:#fafafb;'
              . 'border-radius:8px;padding:14px;border-left:3px solid #2201b2">'
              . nl2br(h(trim($catatan))) . '</div>';
    }

    fbSendMail($to,
        '[AGKB 360°] ' . $t['ticket_no'] . ' — ' . $label,
        fbMailTemplate('Perkembangan Laporan Anda', $body, fbPelaporUrl($t), 'Lihat Detail'));
}

/** Balasan penanganan yang ditujukan ke pelapor. */
function fbNotifyReply(int $ticketId, string $isi): void {
    $t = fbLoadFull($ticketId);
    if (!$t || $t['is_test']) return;
    $to = fbPelaporEmail($t);
    if (!$to) return;

    // Email memuat cuplikan saja. Balasan panjang membuat sebagian
    // klien memotong isinya sendiri di tengah kalimat tanpa memberi
    // tanda — lebih jujur kalau kita yang memotong dan mengatakannya.
    $batas    = 900;
    $terpotong = mb_strlen($isi) > $batas;
    $cuplikan = $terpotong ? mb_substr($isi, 0, $batas) . '…' : $isi;

    $body = '<p style="margin:0 0 12px">Ada balasan baru untuk laporan Anda.</p>'
          . fbTicketMetaHtml($t)
          . '<div style="font-size:15px;font-weight:600;color:#040136;margin-top:10px">' . h($t['subject']) . '</div>'
          . '<div style="font-size:14px;color:#2f2d4d;line-height:1.8;background:#fafafb;border-radius:8px;'
          . 'padding:14px;border-left:3px solid #2201b2;margin-top:10px">'
          . nl2br(h($cuplikan)) . '</div>'
          . ($terpotong
             ? '<p style="margin:12px 0 0;font-size:12px;color:#6b6a83">Balasan ini dipotong agar '
             . 'emailnya tidak terlalu panjang. Teks utuhnya ada di tautan di bawah.</p>'
             : '');

    fbSendMail($to,
        '[AGKB 360°] Balasan — ' . $t['ticket_no'] . ' · ' . $t['subject'],
        fbMailTemplate('Balasan atas Laporan Anda', $body, fbPelaporUrl($t), 'Lihat Detail'));
}

function fbNotifyResolved(int $ticketId): void {
    $t = fbLoadFull($ticketId);
    if (!$t || $t['is_test']) return;
    $to = fbPelaporEmail($t);
    if (!$to) return;
    $tamu = empty($t['sender_id']);

    $resLabel = fbResolutions()[$t['resolution_type']] ?? '—';
    $body = '<p style="margin:0 0 6px">Laporan Anda telah diselesaikan. Terima kasih telah menyampaikannya.</p>'
          . fbTicketMetaHtml($t)
          . '<div style="font-size:15px;font-weight:600;color:#040136">' . h($t['subject']) . '</div>'
          . '<div style="font-size:11px;font-weight:600;color:#6b6a83;text-transform:uppercase;letter-spacing:.5px;margin:16px 0 6px">Jenis Penyelesaian</div>'
          . '<div style="display:inline-block;background:#e7f6ef;color:#015c36;border:1px solid #a5dcc3;border-radius:20px;padding:5px 14px;font-size:12px;font-weight:600">' . h($resLabel) . '</div>'
          . '<div style="font-size:11px;font-weight:600;color:#6b6a83;text-transform:uppercase;letter-spacing:.5px;margin:16px 0 6px">Keterangan</div>'
          . '<div style="font-size:14px;color:#2f2d4d;line-height:1.8;background:#fafafb;border-radius:8px;padding:14px;border-left:3px solid #027a48">' . nl2br(h($t['resolution_note'] ?? '')) . '</div>'
          . ($tamu
             // Pelapor tamu tidak bisa membalas di dalam tiket —
             // halaman pelacakan sengaja hanya menampilkan status.
             // Jangan menjanjikan sesuatu yang tidak ada tombolnya.
             ? '<p style="margin:16px 0 0;font-size:12px;color:#6b6a83">Jika penyelesaian ini belum sesuai, '
             . 'balas email ini atau kirim laporan baru dengan menyebut nomor tiket di atas.</p>'
             : '<p style="margin:16px 0 0;font-size:12px;color:#6b6a83">Jika penyelesaian ini belum sesuai, '
             . 'Anda dapat membalas melalui tautan di bawah dalam 14 hari.</p>');

    fbSendMail($to,
        '[AGKB 360°] Selesai — ' . $t['ticket_no'] . ' · ' . $t['subject'],
        fbMailTemplate('Laporan Anda Telah Diselesaikan', $body, fbPelaporUrl($t), 'Lihat Detail', '#027a48'));
}

/**
 * Beri tahu orang yang baru ditunjuk menangani sebuah tiket, entah
 * sebagai penanggung jawab entah sebagai pemantau.
 *
 * Sebelum ini penunjukan berlangsung diam-diam: kolomnya berubah,
 * peristiwanya tercatat, dan orangnya baru tahu kalau kebetulan
 * membuka inbox. Untuk tiket P1 berbatas waktu 24 jam, itu selisih
 * yang menentukan.
 *
 * Isinya sengaja pendek — nomor, kategori, prioritas, batas waktu,
 * lalu tombol. TIDAK memuat isi laporan, dan tidak juga subjeknya.
 * Alasannya paling terasa pada Kanal Yayasan: orang ini baru saja
 * diberi akses supaya membacanya di dalam aplikasi, di mana
 * pembukaannya tercatat. Menyalin isinya ke kotak surat meniadakan
 * gunanya. Untuk jalur lain pun tidak ada ruginya singkat.
 */
function fbNotifyPenunjukan(int $ticketId, int $userId, string $sebagai): void {
    $t = fbLoadFull($ticketId);
    if (!$t || $t['is_test']) return;

    $u = Database::fetchOne(
        "SELECT name, email FROM users WHERE id=? AND is_active=1", [$userId]);
    if (!$u || empty($u['email'])) return;

    // Menunjuk diri sendiri tidak perlu dikabari — orangnya baru saja
    // menekan tombolnya.
    if ((int)$userId === (int)($_SESSION['user_id'] ?? 0)) return;

    $warna = $t['track'] === 'safeguarding' ? '#b42318' : '#ff9101';

    // fbTicketMetaHtml() tidak memuat batas waktu, padahal justru itu
    // yang paling perlu diketahui orang yang baru ditunjuk.
    $batas = $t['due_at']
        ? '<p style="margin:0 0 4px;font-size:13px;color:#2f2d4d">Batas waktu penanganan: <strong>'
          . date('j M Y, H:i', strtotime($t['due_at'])) . '</strong></p>'
        : '';

    $body = '<p style="margin:0 0 6px">Anda sekarang tercatat sebagai <strong>'
          . h($sebagai) . '</strong> untuk tiket <strong>' . h($t['ticket_no'])
          . '</strong>.</p>'
          . fbTicketMetaHtml($t)
          . $batas
          . '<p style="margin:16px 0 0;font-size:12px;color:#6b6a83">'
          . 'Isi laporan hanya dapat dibaca di dalam aplikasi.</p>';

    fbSendMail(
        $u['email'],
        '[AGKB 360°] ' . $t['ticket_no'] . ' — Anda ditunjuk sebagai ' . $sebagai,
        fbMailTemplate('Penunjukan Penanganan', $body,
            fbAppUrl() . '/admin/ticket.php?id=' . $t['id'], 'Buka Tiket', $warna));
}

function fbNotifyAppreciation(int $ticketId): void {
    $t = fbLoadFull($ticketId);
    if (!$t || $t['is_test'] || empty($t['appreciated_user_id'])) return;
    $to = Database::fetchOne("SELECT name,email FROM users WHERE id=?", [$t['appreciated_user_id']]);
    if (!$to || empty($to['email'])) return;

    $from = $t['is_anonymous'] ? 'seorang rekan' : ($t['sender_name'] ?? 'seorang rekan');
    $body = '<p style="margin:0 0 10px">Halo ' . h($to['name']) . ', ada apresiasi untuk Anda dari <strong>' . h($from) . '</strong>.</p>'
          . '<div style="font-size:15px;font-weight:600;color:#040136;margin-top:10px">' . h($t['subject']) . '</div>'
          . '<div style="font-size:14px;color:#2f2d4d;line-height:1.8;background:#fff8ef;border-radius:8px;padding:14px;border-left:3px solid #ff9101;margin-top:10px">'
          . nl2br(h($t['message'])) . '</div>';

    fbSendMail($to['email'], '[AGKB 360°] Apresiasi untuk Anda',
        fbMailTemplate('Ada Apresiasi untuk Anda', $body, null, null, '#ff9101'));
    fbLogEvent($ticketId, 'diteruskan', null, $to['email']);
}
