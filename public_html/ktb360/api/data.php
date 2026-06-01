<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole(['admin']);

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

// ── EXPORT ────────────────────────────────────────────────────
if ($action === 'export') {
    $type = $_GET['type'] ?? 'full';

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="ktb360_' . $type . '_' . date('Ymd_His') . '.json"');

    $export = [
        'meta' => [
            'app'       => APP_NAME,
            'version'   => APP_VERSION,
            'exported'  => date('c'),
            'type'      => $type,
        ]
    ];

    if (in_array($type, ['full','users'])) {
        $export['users'] = Database::fetchAll("SELECT id,name,email,role,is_active,created_at FROM users");
    }
    if (in_array($type, ['full','questions'])) {
        $export['traits']      = Database::fetchAll("SELECT * FROM traits");
        $export['domains']     = Database::fetchAll("SELECT * FROM domains");
        $export['standards']   = Database::fetchAll("SELECT * FROM standards");
        $export['questions']   = Database::fetchAll("SELECT * FROM questions");
        $export['grade_descriptors'] = Database::fetchAll("SELECT * FROM grade_descriptors");
        $export['packages']    = Database::fetchAll("SELECT * FROM packages");
        $export['package_questions'] = Database::fetchAll("SELECT * FROM package_questions");
    }
    if (in_array($type, ['full','responses'])) {
        $export['eval_periods']  = Database::fetchAll("SELECT * FROM eval_periods");
        $export['assignments']   = Database::fetchAll("SELECT * FROM assignments");
        $export['responses']     = Database::fetchAll("SELECT * FROM responses");
        $export['ai_suggestions'] = Database::fetchAll("SELECT * FROM ai_suggestions");
    }

    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json');

// ── IMPORT ────────────────────────────────────────────────────
if ($action === 'import') {
    $type = $input['type'] ?? 'full';
    $data = $input['data'] ?? [];

    if (empty($data)) {
        jsonResponse(['error' => 'Data import kosong.'], 400);
    }

    $imported = 0;
    $errors   = [];

    try {
        if (in_array($type, ['full','users']) && !empty($data['users'])) {
            $stmt = Database::getInstance()->prepare(
                "INSERT IGNORE INTO users (id,name,email,password,role,is_active) VALUES (?,?,?,?,?,?)"
            );
            foreach ($data['users'] as $u) {
                // Use a default password if not present in export
                $pw = $u['password'] ?? password_hash('KTB2025!', PASSWORD_BCRYPT);
                $stmt->execute([$u['id'],$u['name'],$u['email'],$pw,$u['role'],$u['is_active']??1]);
                $imported++;
            }
        }

        if (in_array($type, ['full','responses']) && !empty($data['assignments'])) {
            $stmt = Database::getInstance()->prepare(
                "INSERT IGNORE INTO assignments (id,period_id,evaluatee_id,evaluator_id,package_id,status,due_date) VALUES (?,?,?,?,?,?,?)"
            );
            foreach ($data['assignments'] as $a) {
                $stmt->execute([$a['id'],$a['period_id'],$a['evaluatee_id'],$a['evaluator_id'],$a['package_id'],$a['status'],$a['due_date']]);
                $imported++;
            }
        }

        if (in_array($type, ['full','responses']) && !empty($data['responses'])) {
            $stmt = Database::getInstance()->prepare(
                "INSERT IGNORE INTO responses (id,assignment_id,question_id,grade,notes) VALUES (?,?,?,?,?)"
            );
            foreach ($data['responses'] as $r) {
                $stmt->execute([$r['id'],$r['assignment_id'],$r['question_id'],$r['grade'],$r['notes']??'']);
                $imported++;
            }
        }
    } catch (Exception $e) {
        jsonResponse(['error' => 'Import gagal: ' . $e->getMessage()], 500);
    }

    jsonResponse(['success' => true, 'imported' => $imported, 'message' => "$imported record berhasil diimport."]);
}

// ── HARD RESET ────────────────────────────────────────────────
if ($action === 'hard_reset') {
    try {
        // Only delete transactional data, keep master data
        Database::getInstance()->exec("SET FOREIGN_KEY_CHECKS=0");
        Database::query("DELETE FROM ai_suggestions");
        Database::query("DELETE FROM responses");
        Database::query("DELETE FROM assignments");
        Database::getInstance()->exec("SET FOREIGN_KEY_CHECKS=1");

        // Reset sequences
        Database::getInstance()->exec("ALTER TABLE responses AUTO_INCREMENT = 1");
        Database::getInstance()->exec("ALTER TABLE assignments AUTO_INCREMENT = 1");
        Database::getInstance()->exec("ALTER TABLE ai_suggestions AUTO_INCREMENT = 1");

        jsonResponse(['success' => true, 'message' => 'Data transaksional berhasil dihapus. Struktur dan master data tetap ada.']);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Reset gagal: ' . $e->getMessage()], 500);
    }
}

// ── FULL RESET ────────────────────────────────────────────────
if ($action === 'full_reset') {
    try {
        $tables = ['ai_suggestions','responses','assignments','package_questions','package_weights',
                   'packages','grade_descriptors','questions','standard_traits','standards',
                   'domains','eval_periods','user_groups','groups','users','traits','eval_types','settings'];

        Database::getInstance()->exec("SET FOREIGN_KEY_CHECKS=0");
        foreach ($tables as $t) {
            Database::getInstance()->exec("TRUNCATE TABLE `$t`");
        }
        Database::getInstance()->exec("SET FOREIGN_KEY_CHECKS=1");

        jsonResponse(['success' => true, 'message' => 'Semua data dihapus. Jalankan setup.php untuk install ulang.']);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Full reset gagal: ' . $e->getMessage()], 500);
    }
}

jsonResponse(['error' => 'Action tidak dikenal.'], 400);
