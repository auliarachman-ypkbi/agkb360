<?php
// ============================================================
// KTB 360° Evaluation Platform — functions.php
// ============================================================
// CATATAN:
// - getScoreLevel() ada di config.php
// - startSession(), login(), logout(), isLoggedIn(),
//   requireLogin(), requireRole(), currentUser(),
//   canAccessAdmin(), csrfToken(), verifyCsrf() ada di auth.php

// ── BASIC HELPERS ─────────────────────────────────────────────
function h(string $s = ''): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
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
function roleLabel(string $role): string {
    return match($role) {
        'superadmin' => 'Super Administrator',
        'admin'      => 'Administrator',
        'foundation' => 'Pengurus Yayasan',
        'leader'     => 'Pimpinan Sekolah',
        'teacher'    => 'Guru',
        'student'    => 'Siswa',
        'parent'     => 'Orang Tua / Wali',
        'tester'     => 'Tester',
        default      => ucfirst($role),
    };
}

function respondentLabel(string $type): string {
    return match($type) {
        'atasan'        => 'Yayasan (YPKBI/YPKTB)',
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
// getScoreLevel() ada di config.php

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