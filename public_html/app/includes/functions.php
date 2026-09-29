<?php
// ============================================================
// AGKB 360° — Platform Evaluasi Kinerja · functions.php
// ============================================================
// CATATAN:
// - getScoreLevel() ada di bawah, dengan cadangan bila config.php
//   pada instalasi ini belum memuatnya
// - startSession(), login(), logout(), isLoggedIn(),
//   requireLogin(), requireRole(), currentUser(),
//   canAccessAdmin(), csrfToken(), verifyCsrf() ada di auth.php

// ── NILAI BAWAAN KONSTANTA TAMPILAN ──────────────────────────
//
// Ketiganya semestinya ditetapkan di config/config.php. Masalahnya,
// config.php di-gitignore karena memuat kredensial, sehingga tidak
// pernah ikut ter-deploy — dan config yang dibuat lebih dulu bisa
// tertinggal tanpa ada yang menyadarinya.
//
// Itu benar-benar terjadi: pada 29 September 2026, config /demo di
// server tidak memuat APP_VERSION maupun APP_SCHOOL, sehingga halaman
// Pengaturan dan endpoint api/data.php mati dengan galat fatal —
// sementara /app baik-baik saja karena config-nya lebih baru.
//
// Nilai-nilai ini bukan rahasia, jadi tidak ada ruginya diberi
// cadangan di berkas yang ikut git. Config tetap menang bila
// mendefinisikannya, sebab config.php dimuat lebih dulu.
foreach ([
    'APP_NAME'    => 'AGKB 360°',
    'APP_VERSION' => '1.0.0',
    'APP_SCHOOL'  => 'SMA Kemala Taruna Bhayangkara',
] as $nama => $bawaan) {
    if (!defined($nama)) define($nama, $bawaan);
}

// ── BASIC HELPERS ─────────────────────────────────────────────
/**
 * Lolos-kan teks untuk ditampilkan di HTML.
 *
 * Menerima null dengan sengaja. Nilai yang datang dari database
 * sering kali NULL secara sah — pengirim penjadwal, nama pengguna
 * yang sudah dihapus, kolom opsional. Sebelumnya tipe ketat string
 * membuat satu nilai NULL menjatuhkan seluruh halaman dengan galat
 * fatal, padahal yang pantas terjadi hanyalah kolom itu kosong.
 */
function h(?string $s = ''): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function formatScore(float $score): string {
    return number_format($score, 2);
}

// ── FLASH MESSAGES ────────────────────────────────────────────
function flash(string $msg, string $type = 'info'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function showFlash(): string {
    if (empty($_SESSION['flash'])) return '';
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $icons = [
        'success' => 'check-circle-fill',
        'danger'  => 'exclamation-triangle-fill',
        'warning' => 'exclamation-triangle-fill',
        'info'    => 'info-circle-fill',
    ];
    $icon = $icons[$f['type']] ?? 'info-circle-fill';
    return sprintf(
        '<div class="alert alert-dismissible alert-%s d-flex align-items-center gap-2 mb-3" role="alert">
           <i class="bi bi-%s"></i>
           <span>%s</span>
           <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
         </div>',
        h($f['type']), h($icon), h($f['msg'])
    );
}

// ── ROLE & RESPONDENT LABELS ──────────────────────────────────
/**
 * Daftar peran resmi, dikelompokkan.
 *
 * Sengaja didefinisikan di sini, BUKAN di config.php — karena config.php
 * gitignored, sehingga penambahan peran di sana tidak akan ikut ke VPS.
 * Harus selaras dengan enum kolom `users.role` di basis data.
 */
function appRoleGroups(): array {
    return [
        'Pengelola Sistem' => [
            'superadmin' => 'Super Administrator',
            'admin'      => 'Administrator',
        ],
        'Yayasan & Pimpinan' => [
            'foundation' => 'Pengurus Yayasan (YPKBI)',
            'leader'     => 'Pimpinan Sekolah',
        ],
        'Tenaga Pendidik' => [
            'teacher'    => 'Guru',
            'mentor'     => 'Mentor / Pembina',
        ],
        'Tenaga Kependidikan (Non-Akademik)' => [
            'staff'      => 'Staf Non-Akademik',
        ],
        'Komunitas Sekolah' => [
            'student'    => 'Siswa',
            'parent'     => 'Orang Tua / Wali',
        ],
        'Pengawasan' => [
            // Melihat seluruh tiket di semua jalur, tanpa dapat
            // membalas, menyelesaikan, maupun membuka Admin CMS.
            'pemantau'   => 'Pemantau (hanya melihat)',
        ],
        'Uji Coba' => [
            'tester'     => 'Tester',
        ],
    ];
}

/** Semua peran sebagai satu larik datar: key => label. */
function appRoles(): array {
    $out = [];
    foreach (appRoleGroups() as $daftar) $out += $daftar;
    return $out;
}

/** Peran yang termasuk tenaga non-akademik. */
function isNonAkademik(string $role): bool {
    return in_array($role, ['staff'], true);
}

function roleLabel(string $role): string {
    return appRoles()[$role] ?? ucfirst($role);
}

function respondentLabel(string $type): string {
    return match($type) {
        'atasan'        => 'Yayasan',
        'leader'        => 'Pimpinan Sekolah',
        'peer'          => 'Rekan Sejawat',
        'guru'          => 'Guru',
        'teacher'       => 'Rekan Sejawat (Guru)',
        'ortu'          => 'Komite Orang Tua',
        'siswa'         => 'OSIS / Siswa',
        'student_class' => 'Murid yang Diajar',
        'self'          => 'Refleksi Mandiri',
        default         => ucfirst($type),
    };
}

// ── AVATAR INITIALS ───────────────────────────────────────────
function avatarInitials(string $name): string {
    $words = explode(' ', trim($name));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

// ── SCORE HELPERS ─────────────────────────────────────────────
// getScoreLevel() didefinisikan di akhir berkas ini, dijaga
// function_exists() supaya config.php yang sudah memuatnya tetap menang.

function getScoreColor(float $score): string {
    return getScoreLevel($score)['color'];
}

function scoreBadge(float $score): string {
    $level = getScoreLevel($score);
    return sprintf(
        '<span class="badge" style="background:%s;color:%s;border:1px solid %s;font-size:.72rem">%s — %s</span>',
        $level['bg'],
        $level['color'],
        $level['color'],
        number_format($score, 2),
        htmlspecialchars($level['label_id'])
    );
}

// ── PERIOD ────────────────────────────────────────────────────
function getPeriod(): array {
    $p = Database::fetchOne("
        SELECT * FROM eval_periods
        WHERE is_active = 1
        ORDER BY start_date DESC
        LIMIT 1
    ");
    return $p ?: [];
}

// ── CALCULATE SCORES ──────────────────────────────────────────
function calculateScores(int $evaluateeId, int $periodId): array {
    $rows = Database::fetchAll("
        SELECT
            r.grade,
            q.id   as question_id,
            s.id   as standard_id,
            s.name as standard_name,
            d.id   as domain_id,
            d.name as domain_name,
            d.code as domain_code
        FROM responses r
        JOIN assignments a ON r.assignment_id = a.id
        JOIN questions q   ON r.question_id = q.id
        JOIN standards s   ON q.standard_id = s.id
        JOIN domains d     ON s.domain_id = d.id
        WHERE a.evaluatee_id = ?
          AND a.period_id = ?
          AND a.status = 'completed'
    ", [$evaluateeId, $periodId]);

    if (empty($rows)) {
        return ['overall'=>0,'byDomain'=>[],'byStandard'=>[],'byTrait'=>[]];
    }

    // Sj — rata-rata grade per standard
    $byStandard = [];
    foreach ($rows as $r) {
        $sid = $r['standard_id'];
        if (!isset($byStandard[$sid])) {
            $byStandard[$sid] = [
                'name'        => $r['standard_name'],
                'domain_id'   => $r['domain_id'],
                'domain_name' => $r['domain_name'],
                'domain_code' => $r['domain_code'],
                'grades'      => [],
            ];
        }
        $byStandard[$sid]['grades'][] = (float)$r['grade'];
    }
    foreach ($byStandard as $sid => &$s) {
        $s['avg'] = round(array_sum($s['grades']) / count($s['grades']), 2);
    }
    unset($s);

    // Di — rata-rata Sj per domain
    $byDomain = [];
    foreach ($byStandard as $s) {
        $did = $s['domain_id'];
        if (!isset($byDomain[$did])) {
            $byDomain[$did] = [
                'name'   => $s['domain_name'],
                'code'   => $s['domain_code'],
                'scores' => [],
            ];
        }
        $byDomain[$did]['scores'][] = $s['avg'];
    }
    foreach ($byDomain as $did => &$d) {
        $d['avg'] = round(array_sum($d['scores']) / count($d['scores']), 2);
    }
    unset($d);

    // Overall — rata-rata Di
    $overall = count($byDomain) > 0
        ? round(array_sum(array_column($byDomain, 'avg')) / count($byDomain), 2)
        : 0;

    // Tt — rata-rata Sj per trait
    $traitRows = Database::fetchAll("
        SELECT t.id, t.code, t.name, s.id as standard_id
        FROM traits t
        JOIN standard_traits st ON t.id = st.trait_id
        JOIN standards s ON st.standard_id = s.id
        WHERE s.id IN (" . implode(',', array_keys($byStandard)) . ")
        ORDER BY t.code
    ");

    $traitScores = [];
    foreach ($traitRows as $tr) {
        $tid = $tr['id'];
        if (!isset($traitScores[$tid])) {
            $traitScores[$tid] = ['name'=>$tr['name'],'code'=>$tr['code'],'scores'=>[]];
        }
        if (isset($byStandard[$tr['standard_id']])) {
            $traitScores[$tid]['scores'][] = $byStandard[$tr['standard_id']]['avg'];
        }
    }

    $byTrait = [];
    foreach ($traitScores as $tid => $t) {
        if (!empty($t['scores'])) {
            $byTrait[$tid] = [
                'name' => $t['name'],
                'code' => $t['code'],
                'avg'  => round(array_sum($t['scores']) / count($t['scores']), 2),
            ];
        }
    }

    return [
        'overall'    => $overall,
        'byDomain'   => array_values($byDomain),
        'byStandard' => $byStandard,
        'byTrait'    => $byTrait,
    ];
}

// ── PAGINATION ────────────────────────────────────────────────
function paginate(int $total, int $perPage, int $page): array {
    $totalPages = (int)ceil($total / $perPage);
    $page = max(1, min($page, $totalPages));
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'page'        => $page,
        'total_pages' => $totalPages,
        'offset'      => ($page - 1) * $perPage,
    ];
}

// ── USER STATS ────────────────────────────────────────────────
function getUserStats(int $userId): array {
    $rows = Database::fetchAll("
        SELECT status, COUNT(*) as c
        FROM assignments
        WHERE evaluator_id = ?
        GROUP BY status
    ", [$userId]);

    $stats = ['pending'=>0,'in_progress'=>0,'completed'=>0];
    foreach ($rows as $r) {
        $stats[$r['status']] = (int)$r['c'];
    }
    $stats['progress'] = $stats['in_progress'];
    return $stats;
}

// ── STATUS BADGE ──────────────────────────────────────────────
function statusBadge(string $status): string {
    return match($status) {
        'completed'   => '<span class="badge bg-success">Selesai</span>',
        'in_progress' => '<span class="badge bg-warning text-dark">Sedang Diisi</span>',
        'pending'     => '<span class="badge bg-secondary">Menunggu</span>',
        default       => '<span class="badge bg-light text-dark">'.h($status).'</span>',
    };
}

// ── JSON API RESPONSE ────────────────────────────────────────
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Peringkat skor 1–4 beserta warnanya.
 *
 * Definisi aslinya ada di config/config.php, dan di situlah masalahnya:
 * config.php sengaja di-gitignore karena memuat kredensial, sehingga
 * TIDAK pernah ikut ter-deploy oleh `git pull`. Setiap fungsi baru yang
 * ditambahkan ke sana hanya hidup di mesin tempat ia ditulis.
 *
 * Akibatnya terlihat pada 29 September 2026: halaman Analisis per Orang
 * di /demo mati dengan "Call to undefined function getScoreLevel()",
 * sementara /app baik-baik saja — config /app kebetulan pernah
 * diperbarui tangan, config /demo tidak.
 *
 * Salinan ini dijaga function_exists() supaya server yang config-nya
 * sudah memuat definisi sendiri tetap memakai miliknya, tanpa galat
 * "cannot redeclare". config.php dimuat lebih dulu daripada berkas ini,
 * jadi yang di config selalu menang.
 *
 * Berkas ini ikut git, sehingga `git pull` sudah cukup dan tidak ada
 * config yang perlu disunting tangan di server.
 */
if (!function_exists('getScoreLevel')) {
    function getScoreLevel(float $score): array {
        if ($score >= 4.00) return [
            'label_id' => 'Sempurna',    'label_en' => 'Perfect',
            'color'    => '#015c36',     'bg'       => '#e7f6ef',
        ];
        if ($score >= 3.75) return [
            'label_id' => 'Luar Biasa',  'label_en' => 'Outstanding',
            'color'    => '#027a48',     'bg'       => '#e7f6ef',
        ];
        if ($score >= 3.25) return [
            'label_id' => 'Sangat Baik', 'label_en' => 'Very Good',
            'color'    => '#2201b2',     'bg'       => '#eeebfc',
        ];
        if ($score >= 2.75) return [
            'label_id' => 'Baik',        'label_en' => 'Good',
            'color'    => '#2201b2',     'bg'       => '#eeebfc',
        ];
        if ($score >= 2.25) return [
            'label_id' => 'Cukup Baik',  'label_en' => 'Fair',
            'color'    => '#a85a01',     'bg'       => '#fff8ef',
        ];
        if ($score >= 1.75) return [
            'label_id' => 'Cukup',       'label_en' => 'Sufficient',
            'color'    => '#b83a01',     'bg'       => '#fff8ef',
        ];
        if ($score >= 1.25) return [
            'label_id' => 'Kurang',      'label_en' => 'Below Standard',
            'color'    => '#b42318',     'bg'       => '#fdeceb',
        ];
        return [
            'label_id' => 'Sangat Kurang', 'label_en' => 'Insufficient',
            'color'    => '#8c1610',       'bg'       => '#fdeceb',
        ];
    }
}
