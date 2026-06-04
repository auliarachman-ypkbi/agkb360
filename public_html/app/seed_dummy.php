<?php
/**
 * KTB 360° — Seed Dummy Data 2024
 * Jalankan sekali: http://localhost/demo/seed_dummy.php
 * HAPUS file ini setelah dijalankan!
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';

// ── SAFETY CHECK ──────────────────────────────────────────────
if (!isset($_GET['run'])) { ?>
<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem;max-width:600px;margin:0 auto">
<h2>⚠️ Seed Dummy Data 2024</h2>
<p>Script ini akan membuat:</p>
<ul>
  <li>2 periode dummy (Semester 1 & 2 tahun 2024)</li>
  <li>Assignments dan responses untuk semua leader & guru</li>
  <li>Tren skor: improving, stable, declining</li>
</ul>
<p style="color:red"><strong>Hanya jalankan sekali! Hapus file ini setelah selesai.</strong></p>
<a href="?run=1" style="background:#001f3e;color:white;padding:.6rem 1.5rem;text-decoration:none;border-radius:6px">
  ▶ Jalankan Sekarang
</a>
</body></html>
<?php exit; }

$log = [];
$errors = [];

// ── HELPER: Random grade dengan bias ─────────────────────────
function randomGrade(float $bias): int {
    $r = mt_rand(0, 100) / 100;
    if ($bias >= 3.5) {
        if ($r < 0.05) return 2; if ($r < 0.35) return 3; return 4;
    } elseif ($bias >= 3.0) {
        if ($r < 0.05) return 1; if ($r < 0.20) return 2; if ($r < 0.65) return 3; return 4;
    } elseif ($bias >= 2.5) {
        if ($r < 0.10) return 1; if ($r < 0.40) return 2; if ($r < 0.80) return 3; return 4;
    } else {
        if ($r < 0.20) return 1; if ($r < 0.55) return 2; if ($r < 0.85) return 3; return 4;
    }
}

// ── GET EXISTING DATA ─────────────────────────────────────────
// Users
$leaders = Database::fetchAll("SELECT id, name, email FROM users WHERE role='leader' AND is_active=1");
$teachers = Database::fetchAll("SELECT id, name, email FROM users WHERE role='teacher' AND is_active=1");
$foundation = Database::fetchAll("SELECT id FROM users WHERE role='foundation' AND is_active=1");
$parents = Database::fetchAll("SELECT id FROM users WHERE role='parent' AND is_active=1 LIMIT 5");
$osis = Database::fetchAll("
    SELECT DISTINCT u.id FROM users u
    JOIN user_groups ug ON ug.user_id = u.id
    JOIN `groups` g ON g.id = ug.group_id
    WHERE g.respondent_type = 'siswa' AND u.is_active=1
    LIMIT 10
");

// Packages (template global, period_id=NULL)
$pkgs = [];
$pkgRows = Database::fetchAll("SELECT id, code, respondent_type, eval_type_id FROM packages WHERE period_id IS NULL AND is_self_reflection=0");
foreach ($pkgRows as $p) {
    $pkgs[$p['code']] = $p;
}

// Package questions per package
$pkgQs = [];
foreach ($pkgRows as $p) {
    $qs = Database::fetchAll("SELECT question_id FROM package_questions WHERE package_id=? ORDER BY order_num", [$p['id']]);
    $pkgQs[$p['id']] = array_column($qs, 'question_id');
}

// ── BIAS SCORES per orang ─────────────────────────────────────
// Format: [sem1_2024, sem2_2024, 2024-2025(existing)]
$leaderBias = [];
foreach ($leaders as $i => $l) {
    if ($i === 0) $leaderBias[$l['id']] = [2.6, 2.9, 3.6]; // improving
    elseif ($i === 1) $leaderBias[$l['id']] = [3.1, 3.0, 3.2]; // stable
    else $leaderBias[$l['id']] = [3.4, 3.1, 2.8]; // declining
}

$teacherBias = [];
foreach ($teachers as $i => $t) {
    $patterns = [
        [2.4, 2.7, 2.9], // improving slowly
        [2.8, 3.0, 3.2], // improving
        [3.2, 3.3, 3.5], // improving strongly
        [3.0, 2.9, 2.8], // slight decline
        [2.6, 2.5, 2.6], // flat low
        [3.4, 3.5, 3.7], // consistently high
        [2.9, 3.1, 3.0], // up then down
        [2.7, 2.8, 3.1], // improving
        [3.1, 3.0, 2.9], // slight decline
        [2.5, 2.8, 3.2], // strong improving
    ];
    $teacherBias[$t['id']] = $patterns[$i % count($patterns)];
}

// ── BUAT PERIODS ──────────────────────────────────────────────
$periods = [
    [
        'name'       => 'Evaluasi Semester 1 2024',
        'year'       => 2024,
        'start_date' => '2024-01-15',
        'end_date'   => '2024-06-30',
        'bias_idx'   => 0,
    ],
    [
        'name'       => 'Evaluasi Semester 2 2024',
        'year'       => 2024,
        'start_date' => '2024-07-15',
        'end_date'   => '2024-12-31',
        'bias_idx'   => 1,
    ],
];

$createdPeriods = [];
foreach ($periods as $pd) {
    // Cek sudah ada
    $existing = Database::fetchOne("SELECT id FROM eval_periods WHERE name=?", [$pd['name']]);
    if ($existing) {
        $log[] = "Period '{$pd['name']}' sudah ada (id={$existing['id']}), skip.";
        $createdPeriods[] = array_merge($pd, ['id'=>$existing['id']]);
        continue;
    }
    $periodId = Database::insert('eval_periods', [
        'name'        => $pd['name'],
        'year'        => $pd['year'],
        'start_date'  => $pd['start_date'],
        'end_date'    => $pd['end_date'],
        'status'      => 'closed',
        'is_active'   => 0,
        'wizard_step' => 6,
        'locked_at'   => $pd['end_date'] . ' 23:59:00',
    ]);
    $createdPeriods[] = array_merge($pd, ['id'=>$periodId]);
    $log[] = "✅ Period '{$pd['name']}' dibuat (id=$periodId)";
}

// ── GENERATE ASSIGNMENTS & RESPONSES ─────────────────────────
$totalAssign = 0;
$totalResp   = 0;

foreach ($createdPeriods as $pd) {
    $periodId = $pd['id'];
    $biasIdx  = $pd['bias_idx'];
    $dueDate  = $pd['end_date'];
    $completedAt = date('Y-m-d', strtotime($pd['end_date'] . ' -7 days')) . ' 10:00:00';

    // Helper: insert assignment + responses
    $makeAssignment = function(int $evaluateeId, int $evaluatorId, array $pkg) use ($periodId, $pkgQs, $dueDate, $completedAt, &$totalAssign, &$totalResp, $leaderBias, $teacherBias, $biasIdx) {
        // Skip jika evaluatee = evaluator
        if ($evaluateeId === $evaluatorId) return;

        // Cek sudah ada
        $exists = Database::fetchOne(
            "SELECT id FROM assignments WHERE period_id=? AND evaluatee_id=? AND evaluator_id=? AND package_id=?",
            [$periodId, $evaluateeId, $evaluatorId, $pkg['id']]
        );
        if ($exists) return;

        $aid = Database::insert('assignments', [
            'period_id'    => $periodId,
            'evaluatee_id' => $evaluateeId,
            'evaluator_id' => $evaluatorId,
            'package_id'   => $pkg['id'],
            'status'       => 'completed',
            'due_date'     => $dueDate,
            'completed_at' => $completedAt,
        ]);
        $totalAssign++;

        // Tentukan bias
        $bias = 3.0;
        if (isset($leaderBias[$evaluateeId][$biasIdx])) {
            $bias = $leaderBias[$evaluateeId][$biasIdx];
        } elseif (isset($teacherBias[$evaluateeId][$biasIdx])) {
            $bias = $teacherBias[$evaluateeId][$biasIdx];
        }

        // Insert responses
        $qIds = $pkgQs[$pkg['id']] ?? [];
        foreach ($qIds as $qId) {
            $existing2 = Database::fetchOne(
                "SELECT id FROM responses WHERE assignment_id=? AND question_id=?",
                [$aid, $qId]
            );
            if ($existing2) continue;
            Database::insert('responses', [
                'assignment_id' => $aid,
                'question_id'   => $qId,
                'grade'         => randomGrade($bias),
            ]);
            $totalResp++;
        }
    };

    // ── Leader evaluations ────────────────────────────────────
    foreach ($leaders as $leader) {
        $lId = $leader['id'];

        // L1: Foundation menilai leader
        if (isset($pkgs['L1'])) {
            foreach ($foundation as $f) {
                $makeAssignment($lId, $f['id'], $pkgs['L1']);
            }
        }

        // L2: Leader menilai leader (peer)
        if (isset($pkgs['L2'])) {
            foreach ($leaders as $peer) {
                if ($peer['id'] !== $lId) {
                    $makeAssignment($lId, $peer['id'], $pkgs['L2']);
                }
            }
        }

        // L3: Guru menilai leader
        if (isset($pkgs['L3'])) {
            foreach (array_slice($teachers, 0, 5) as $t) {
                $makeAssignment($lId, $t['id'], $pkgs['L3']);
            }
        }

        // L4: Ortu menilai leader
        if (isset($pkgs['L4'])) {
            foreach (array_slice($parents, 0, 3) as $p) {
                $makeAssignment($lId, $p['id'], $pkgs['L4']);
            }
        }

        // L5: OSIS menilai leader
        if (isset($pkgs['L5'])) {
            foreach (array_slice($osis, 0, 5) as $s) {
                $makeAssignment($lId, $s['id'], $pkgs['L5']);
            }
        }
    }

    // ── Teacher evaluations ───────────────────────────────────
    foreach ($teachers as $idx => $teacher) {
        $tId = $teacher['id'];

        // T1: Foundation menilai guru (hanya guru pertama)
        if (isset($pkgs['T1']) && $idx === 0) {
            foreach ($foundation as $f) {
                $makeAssignment($tId, $f['id'], $pkgs['T1']);
            }
        }

        // T2: Leader menilai guru
        if (isset($pkgs['T2'])) {
            foreach (array_slice($leaders, 0, 1) as $l) {
                $makeAssignment($tId, $l['id'], $pkgs['T2']);
            }
        }

        // T3: Guru menilai guru (peer — 1 peer per guru)
        if (isset($pkgs['T3'])) {
            $peerIdx = ($idx + 1) % count($teachers);
            $makeAssignment($tId, $teachers[$peerIdx]['id'], $pkgs['T3']);
        }

        // T5: Siswa menilai guru
        if (isset($pkgs['T5'])) {
            foreach (array_slice($osis, 0, 5) as $s) {
                $makeAssignment($tId, $s['id'], $pkgs['T5']);
            }
        }
    }

    $log[] = "✅ Period {$pd['name']}: $totalAssign assignments, $totalResp responses";
    $totalAssign = 0;
    $totalResp   = 0;
}

// ── OUTPUT ────────────────────────────────────────────────────
?>
<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem;max-width:700px;margin:0 auto">
<h2>✅ Seed Dummy Data Selesai!</h2>
<ul>
<?php foreach ($log as $l): ?>
<li><?= htmlspecialchars($l) ?></li>
<?php endforeach; ?>
</ul>
<?php if (!empty($errors)): ?>
<h3 style="color:red">⚠️ Errors:</h3>
<ul><?php foreach ($errors as $e): ?><li style="color:red"><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<p style="margin-top:1.5rem;padding:1rem;background:#fef2f2;border-radius:6px">
  <strong>⚠️ PENTING:</strong> Hapus file <code>seed_dummy.php</code> dari server sekarang!
</p>
<a href="/demo/admin/reports.php" style="background:#001f3e;color:white;padding:.6rem 1.5rem;text-decoration:none;border-radius:6px">
  Lihat Laporan →
</a>
</body></html>
<?php
