<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireLogin();
requireRole(['admin','foundation','leader']);

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_POST['action'] ?? 'generate';

// ── Save Edited Suggestion ────────────────────────────────────
if ($action === 'save_edit') {
    $evaluateeId = (int)($_POST['evaluatee_id'] ?? 0);
    $periodId    = (int)($_POST['period_id'] ?? 0);
    $edited      = trim($_POST['edited_suggestion'] ?? '');

    if (!$evaluateeId || !$periodId) {
        jsonResponse(['error' => 'Parameter tidak lengkap.'], 400);
    }

    $exists = Database::fetchOne(
        "SELECT id FROM ai_suggestions WHERE evaluatee_id=? AND period_id=?",
        [$evaluateeId, $periodId]
    );
    if ($exists) {
        Database::update('ai_suggestions',
            ['edited_suggestion' => $edited, 'edited_by' => $_SESSION['user_id'], 'edited_at' => date('Y-m-d H:i:s')],
            'evaluatee_id=? AND period_id=?',
            [$evaluateeId, $periodId]
        );
    }

    // Redirect back to reports
    flash('Saran AI berhasil disimpan.', 'success');
    header('Location: ' . APP_URL . '/admin/reports.php?user_id=' . $evaluateeId);
    exit;
}

// ── Generate AI Suggestion ────────────────────────────────────
if ($action === 'generate' || !$action) {
    $evaluateeId = (int)($input['evaluatee_id'] ?? 0);
    $periodId    = (int)($input['period_id'] ?? 0);

    if (!$evaluateeId || !$periodId) {
        jsonResponse(['error' => 'Parameter tidak lengkap.'], 400);
    }

    $evaluatee = Database::fetchOne("SELECT * FROM users WHERE id=?", [$evaluateeId]);
    $period    = Database::fetchOne("SELECT * FROM eval_periods WHERE id=?", [$periodId]);

    if (!$evaluatee || !$period) {
        jsonResponse(['error' => 'Data tidak ditemukan.'], 404);
    }

    $scores = calculateScores($evaluateeId, $periodId);

    if (empty($scores) || $scores['overall'] <= 0) {
        jsonResponse(['error' => 'Belum ada data evaluasi yang cukup untuk menghasilkan saran.'], 400);
    }

    // Build context for Claude
    $scoreLines = [];
    foreach ($scores['byDomain'] as $d) {
        $scoreLines[] = "- Domain {$d['name']}: {$d['avg']}/4.0 (" . getScoreLevel($d['avg'])['label_id'] . ")";
    }

    $traitLines = [];
    foreach ($scores['byTrait'] as $t) {
        $traitLines[] = "- Trait {$t['name']}: {$t['avg']}/4.0";
    }

    $prompt = "Kamu adalah konsultan pengembangan profesional pendidikan IB (International Baccalaureate) yang berpengalaman.

Berdasarkan hasil evaluasi 360° berikut, buatkan saran pengembangan profesional (PDP) yang spesifik dan dapat ditindaklanjuti:

**Nama:** {$evaluatee['name']}
**Peran:** " . roleLabel($evaluatee['role']) . "
**Periode:** {$period['name']}
**Skor Keseluruhan:** {$scores['overall']}/4.0

**Skor per Domain:**
" . implode("\n", $scoreLines) . "

**Skor per Trait (10 IB Traits):**
" . implode("\n", $traitLines) . "

Tulis saran dalam Bahasa Indonesia yang profesional, dengan format:
1. Ringkasan kinerja (2-3 kalimat)
2. Kekuatan utama (2-3 poin dengan penjelasan)
3. Area pengembangan prioritas (2-3 poin dengan saran konkret)
4. Rencana Aksi (SMART Goals untuk semester depan)
5. Rekomendasi pelatihan/workshop IB yang relevan

Maksimum 500 kata. Gunakan bahasa yang konstruktif dan memotivasi.";

    // Call Claude API
    if (CLAUDE_API_KEY === 'YOUR_CLAUDE_API_KEY_HERE') {
        // Demo mode - return sample suggestion
        $suggestion = "**Ringkasan Kinerja**\n\n" .
            "{$evaluatee['name']} menunjukkan performa " . getScoreLevel($scores['overall'])['label_id'] . 
            " dengan skor keseluruhan {$scores['overall']}/4.0 pada periode {$period['name']}. " .
            "Hasil evaluasi mencerminkan komitmen yang kuat terhadap nilai-nilai IB.\n\n" .
            "**Kekuatan Utama**\n\n" .
            "• Konsistensi dalam menerapkan filosofi IB dalam praktik sehari-hari\n" .
            "• Komunikasi yang efektif dengan seluruh stakeholder\n" .
            "• Kolaborasi yang baik dengan rekan sejawat\n\n" .
            "**Area Pengembangan Prioritas**\n\n" .
            "• Tingkatkan penggunaan data asesmen untuk menginformasikan pengambilan keputusan\n" .
            "• Perkuat program mentoring untuk staf junior\n" .
            "• Kembangkan inisiatif student agency yang lebih terstruktur\n\n" .
            "**Rencana Aksi (SMART Goals)**\n\n" .
            "• S1 2025-2026: Implementasikan siklus data review bulanan untuk seluruh tim\n" .
            "• Ikuti IB Leadership Workshop sebelum akhir tahun ajaran\n" .
            "• Susun program Student Voice yang melibatkan minimal 80% siswa per semester\n\n" .
            "**Rekomendasi Pelatihan IB**\n\n" .
            "• IB Programme Standards & Practices Workshop\n" .
            "• Leading Curriculum Development in IB Schools\n\n" .
            "_[Catatan: Saran ini dihasilkan dalam mode demo. Aktifkan Claude API Key untuk saran yang dipersonalisasi berdasarkan data aktual.]_";
    } else {
        try {
            $ch = curl_init(CLAUDE_API_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'x-api-key: ' . CLAUDE_API_KEY,
                    'anthropic-version: 2023-06-01',
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model'      => CLAUDE_MODEL,
                    'max_tokens' => 1000,
                    'messages'   => [['role' => 'user', 'content' => $prompt]],
                ]),
                CURLOPT_TIMEOUT => 30,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                jsonResponse(['error' => "Claude API error: HTTP $httpCode"], 500);
            }

            $data = json_decode($response, true);
            $suggestion = $data['content'][0]['text'] ?? '';

            if (!$suggestion) {
                jsonResponse(['error' => 'Claude tidak mengembalikan respons.'], 500);
            }
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal menghubungi Claude API: ' . $e->getMessage()], 500);
        }
    }

    // Save to DB
    $exists = Database::fetchOne(
        "SELECT id FROM ai_suggestions WHERE evaluatee_id=? AND period_id=?",
        [$evaluateeId, $periodId]
    );
    if ($exists) {
        Database::update('ai_suggestions',
            ['raw_suggestion' => $suggestion, 'edited_suggestion' => $suggestion, 'generated_at' => date('Y-m-d H:i:s')],
            'evaluatee_id=? AND period_id=?',
            [$evaluateeId, $periodId]
        );
    } else {
        Database::insert('ai_suggestions', [
            'evaluatee_id'       => $evaluateeId,
            'period_id'          => $periodId,
            'raw_suggestion'     => $suggestion,
            'edited_suggestion'  => $suggestion,
        ]);
    }

    jsonResponse(['success' => true, 'suggestion' => $suggestion]);
}

jsonResponse(['error' => 'Action tidak dikenal.'], 400);
