<?php
/**
 * api/assignment_helper.php
 * AJAX endpoint untuk form penugasan
 * - GET ?action=get_package&evaluatee_id=X&evaluator_id=Y → paket yang valid
 * - GET ?action=get_evaluators&evaluatee_id=X             → daftar penilai yang valid
 * - GET ?action=search_users&q=keyword&type=evaluatee|evaluator&evaluatee_id=X
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$action = $_GET['action'] ?? '';

// ══════════════════════════════════════════════════════════════
// GET PACKAGE — berdasarkan kombinasi evaluatee + evaluator
// ══════════════════════════════════════════════════════════════
if ($action === 'get_package') {
    $evaluateeId = (int)($_GET['evaluatee_id'] ?? 0);
    $evaluatorId = (int)($_GET['evaluator_id'] ?? 0);

    if (!$evaluateeId || !$evaluatorId) {
        jsonResponse(['package' => null, 'message' => 'Pilih yang dinilai dan penilai terlebih dahulu.']);
    }

    $evaluatee = Database::fetchOne("SELECT id, name, role FROM users WHERE id=?", [$evaluateeId]);
    $evaluator = Database::fetchOne("SELECT id, name, role FROM users WHERE id=?", [$evaluatorId]);

    if (!$evaluatee || !$evaluator) {
        jsonResponse(['package' => null, 'message' => 'User tidak ditemukan.']);
    }

    // Tentukan eval_type_id berdasarkan role evaluatee
    $evalTypeId = match($evaluatee['role']) {
        'leader'  => 1,
        'teacher' => 2,
        default   => null,
    };

    if (!$evalTypeId) {
        jsonResponse(['package' => null, 'error' => 'Yang dinilai harus Leader atau Guru.']);
    }

    // Tentukan respondent_type berdasarkan role evaluator
    // CATATAN: respondent_type peer-review TIDAK pakai literal 'peer' —
    // sesuai matrix, Leader selalu pakai kode 'leader', Guru selalu pakai 'guru',
    // baik untuk peer-review maupun cross-evaluation.
    $respType = null;

    // Self reflection
    if ($evaluateeId === $evaluatorId) {
        $respType = 'self';
    }
    // Foundation/Yayasan → atasan (hanya untuk leader)
    elseif ($evaluator['role'] === 'foundation') {
        if ($evalTypeId === 2) {
            jsonResponse(['package' => null, 'error' => 'Yayasan tidak menilai Guru langsung.']);
        }
        $respType = 'atasan';
    }
    // Leader menilai (Leader maupun Guru) → selalu 'leader'
    elseif ($evaluator['role'] === 'leader') {
        $respType = 'leader';
    }
    // Guru menilai (Leader maupun Guru) → selalu 'guru'
    elseif ($evaluator['role'] === 'teacher') {
        $respType = 'guru';
    }
    // Orang tua → ortu
    elseif ($evaluator['role'] === 'parent') {
        $respType = 'ortu';
    }
    // Siswa → OSIS menilai leader = 'siswa', murid menilai guru (per kelas) = 'student_class'
    elseif ($evaluator['role'] === 'student') {
        $respType = ($evalTypeId === 1) ? 'siswa' : 'student_class';
    }

    if (!$respType) {
        jsonResponse(['package' => null, 'error' => 'Kombinasi peran ini tidak valid.']);
    }

    // Cari paket yang sesuai
    $package = Database::fetchOne(
        "SELECT id, code, name, respondent_type, is_self_reflection
         FROM packages
         WHERE eval_type_id = ? AND respondent_type = ?",
        [$evalTypeId, $respType]
    );

    if (!$package) {
        jsonResponse(['package' => null, 'error' => "Paket untuk kombinasi ini belum tersedia ($respType)."]);
    }

    jsonResponse([
        'package' => $package,
        'message' => "Paket otomatis: {$package['code']} — {$package['name']}",
    ]);
}

// ══════════════════════════════════════════════════════════════
// SEARCH USERS — untuk Select2 dengan AJAX search
// ══════════════════════════════════════════════════════════════
if ($action === 'search_users') {
    $q           = trim($_GET['q'] ?? '');
    $type        = $_GET['type'] ?? 'evaluator'; // evaluatee | evaluator
    $evaluateeId = (int)($_GET['evaluatee_id'] ?? 0);

    $where  = "u.is_active = 1";
    $params = [];

    // Filter berdasarkan tipe
    if ($type === 'evaluatee') {
        // Yang dinilai hanya leader dan teacher
        $where .= " AND u.role IN ('leader','teacher')";
    } elseif ($type === 'evaluator' && $evaluateeId) {
        // Filter penilai berdasarkan evaluatee yang dipilih
        $evaluatee = Database::fetchOne("SELECT id, role FROM users WHERE id=?", [$evaluateeId]);
        if ($evaluatee) {
            if ($evaluatee['role'] === 'leader') {
                // Leader bisa dinilai oleh: foundation, leader, teacher, parent (komite), student (OSIS)
                $where .= " AND (
                    u.role IN ('foundation','leader','teacher')
                    OR (u.role = 'parent' AND u.is_parent_committee = 1)
                    OR (u.role = 'student' AND u.is_osis = 1)
                    OR u.id = $evaluateeId
                )";
            } elseif ($evaluatee['role'] === 'teacher') {
                // Teacher bisa dinilai oleh: leader, teacher, parent (komite),
                // murid yang ada di kelas yang sama (via class_teachers + class_students — many-to-many)
                $where .= " AND (
                    u.role IN ('leader','teacher')
                    OR (u.role = 'parent' AND u.is_parent_committee = 1)
                    OR (u.role = 'student' AND u.id IN (
                        SELECT cs.student_id
                        FROM class_students cs
                        JOIN class_teachers ct ON ct.class_id = cs.class_id
                        WHERE ct.teacher_id = $evaluateeId
                    ))
                    OR u.id = $evaluateeId
                )";
            }
        }
    }

    // Search by name or email
    if ($q) {
        $where .= " AND (u.name LIKE ? OR u.email LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }

    $users = Database::fetchAll("
        SELECT u.id, u.name, u.role,
               u.is_osis, u.is_parent_committee
        FROM users u
        WHERE $where
        ORDER BY FIELD(u.role,'foundation','leader','teacher','parent','student'), u.name
        LIMIT 50
    ", $params);

    // Format untuk Select2
    $results = array_map(function($u) {
        $label = '[' . strtoupper($u['role']) . '] ' . $u['name'];
        $meta  = '';
        if ($u['role'] === 'student' && $u['is_osis']) {
            $meta = ' · OSIS';
        }
        if ($u['role'] === 'parent' && $u['is_parent_committee']) {
            $meta = ' · Komite';
        }
        return [
            'id'   => $u['id'],
            'text' => $label . $meta,
            'role' => $u['role'],
        ];
    }, $users);

    jsonResponse(['results' => $results]);
}

jsonResponse(['error' => 'Action tidak dikenal.'], 400);