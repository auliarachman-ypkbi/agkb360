<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_POST['action'] ?? '';

if ($action === 'save') {
    $assignId = (int)($input['assignment_id'] ?? 0);
    $grades   = $input['grades'] ?? []; // {question_id: grade}
    $notes    = $input['notes'] ?? [];
    $isFinal  = !empty($input['is_final']);

    if (!$assignId) {
        jsonResponse(['error' => 'Assignment ID diperlukan.'], 400);
    }

    // Verify access
    $assignment = Database::fetchOne(
        "SELECT * FROM assignments WHERE id=? AND evaluator_id=?",
        [$assignId, $_SESSION['user_id']]
    );
    if (!$assignment) {
        jsonResponse(['error' => 'Akses ditolak.'], 403);
    }
    if ($assignment['status'] === 'completed') {
        jsonResponse(['error' => 'Kuesioner sudah dikumpulkan.'], 400);
    }

    $saved = 0;
    foreach ($grades as $qId => $grade) {
        $grade = (int)$grade;
        if ($grade < 1 || $grade > 4) continue;
        $note = $notes[$qId] ?? '';

        $existing = Database::fetchOne(
            "SELECT id FROM responses WHERE assignment_id=? AND question_id=?",
            [$assignId, (int)$qId]
        );
        if ($existing) {
            Database::update('responses',
                ['grade' => $grade, 'notes' => $note],
                'assignment_id=? AND question_id=?',
                [$assignId, (int)$qId]
            );
        } else {
            Database::insert('responses', [
                'assignment_id' => $assignId,
                'question_id'   => (int)$qId,
                'grade'         => $grade,
                'notes'         => $note,
            ]);
        }
        $saved++;
    }

    // Count total questions for this package
    $totalQ = Database::fetchOne(
        "SELECT COUNT(*) c FROM package_questions WHERE package_id=?",
        [$assignment['package_id']]
    )['c'];
    $answeredQ = Database::fetchOne(
        "SELECT COUNT(*) c FROM responses WHERE assignment_id=?",
        [$assignId]
    )['c'];

    // Update status
    if ($isFinal && $answeredQ >= $totalQ) {
        Database::update('assignments',
            ['status' => 'completed', 'completed_at' => date('Y-m-d H:i:s')],
            'id=?', [$assignId]
        );
        jsonResponse(['success' => true, 'status' => 'completed', 'saved' => $saved]);
    } else {
        if ($assignment['status'] === 'pending') {
            Database::update('assignments', ['status' => 'in_progress'], 'id=?', [$assignId]);
        }
        jsonResponse([
            'success'   => true,
            'status'    => 'in_progress',
            'saved'     => $saved,
            'answered'  => $answeredQ,
            'total'     => $totalQ,
        ]);
    }
}

jsonResponse(['error' => 'Action tidak dikenal.'], 400);
