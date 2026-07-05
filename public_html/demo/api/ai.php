<?php
/**
 * api/ai.php — Saran Pengembangan (AI-Assisted)
 *
 * Actions:
 *   generate       (POST, JSON)  : admin only — build data lengkap, panggil Gemini, simpan ke ai_suggestions
 *   download_input (GET,  .txt)  : admin only — download data+prompt sebagai file teks
 *   save_edit      (POST, form)  : admin only — simpan hasil editan manual
 *
 * Konfigurasi: tambahkan di config/config.php →  define('GEMINI_API_KEY', 'AIza...');
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$user    = currentUser();
$isAdmin = in_array($user['role'] ?? '', ['admin', 'superadmin'], true);
$action  = $_REQUEST['action'] ?? '';

// ═══════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════

function respondentLabel(string $key): string {
    return [
        'atasan'        => 'Atasan / Yayasan (YPKBI)',
        'leader'        => 'Pimpinan Sekolah',
        'guru'          => 'Guru (Rekan Sejawat)',
        'ortu'          => 'Orang Tua',
        'siswa'         => 'Siswa (OSIS)',
        'student_class' => 'Murid yang Diajar',
        'self'          => 'Refleksi Diri',
    ][$key] ?? ucfirst($key);
}

/**
 * Kumpulkan seluruh data evaluasi seorang evaluatee untuk AI.
 * Bekerja dengan data yang ADA saat ini (respons completed),
 * tidak menunggu periode selesai.
 */
function buildAIReportData(int $evaluateeId, int $periodId): array {
    $evaluatee = Database::fetchOne("SELECT * FROM users WHERE id=?", [$evaluateeId]);
    $period    = Database::fetchOne("SELECT * FROM eval_periods WHERE id=?", [$periodId]);
    if (!$evaluatee || !$period) {
        throw new Exception('Evaluatee atau periode tidak ditemukan.');
    }

    $scores = calculateScores($evaluateeId, $periodId);

    // Fallback trait (sama seperti reports.php)
    if (empty($scores['byTrait'])) {
        $traitRows = Database::fetchAll("
            SELECT t.id, t.code, t.name, ROUND(AVG(r.grade),2) AS avg_grade
            FROM traits t
            JOIN standard_traits st ON t.id = st.trait_id
            JOIN standards s  ON st.standard_id = s.id
            JOIN questions q  ON q.standard_id = s.id
            JOIN responses r  ON r.question_id = q.id
            JOIN assignments a ON r.assignment_id = a.id
            WHERE a.evaluatee_id=? AND a.period_id=?
            GROUP BY t.id, t.code, t.name ORDER BY t.code
        ", [$evaluateeId, $periodId]);
        foreach ($traitRows as $t) {
            $scores['byTrait'][$t['id']] = [
                'name' => $t['name'], 'code' => $t['code'],
                'avg'  => round((float)$t['avg_grade'], 2),
            ];
        }
    }

    // ── Breakdown per tipe responden (agregat) ────────────────
    $byRespondent = Database::fetchAll("
        SELECT
            CASE WHEN p.is_self_reflection=1 THEN 'self' ELSE p.respondent_type END AS rtype,
            COUNT(DISTINCT a.evaluator_id) AS n_responden,
            COUNT(r.id)                    AS n_jawaban,
            ROUND(AVG(r.grade),2)          AS avg_grade
        FROM responses r
        JOIN assignments a ON r.assignment_id = a.id
        JOIN packages p    ON a.package_id    = p.id
        WHERE a.evaluatee_id=? AND a.period_id=?
        GROUP BY rtype
        ORDER BY avg_grade DESC
    ", [$evaluateeId, $periodId]);

    // ── Breakdown per tipe responden × domain ─────────────────
    $byRespondentDomain = Database::fetchAll("
        SELECT
            CASE WHEN p.is_self_reflection=1 THEN 'self' ELSE p.respondent_type END AS rtype,
            d.name AS domain_name,
            ROUND(AVG(r.grade),2) AS avg_grade
        FROM responses r
        JOIN assignments a ON r.assignment_id = a.id
        JOIN packages p    ON a.package_id    = p.id
        JOIN questions q   ON r.question_id   = q.id
        JOIN standards s   ON q.standard_id   = s.id
        JOIN domains d     ON s.domain_id     = d.id
        WHERE a.evaluatee_id=? AND a.period_id=?
        GROUP BY rtype, d.id, d.name
        ORDER BY rtype, avg_grade DESC
    ", [$evaluateeId, $periodId]);

    $rdMap = [];
    foreach ($byRespondentDomain as $row) {
        $rdMap[$row['rtype']][] = $row;
    }

    // ── Catatan kualitatif (ANONIM — tanpa nama evaluator) ────
    $notes = Database::fetchAll("
        SELECT
            CASE WHEN p.is_self_reflection=1 THEN 'self' ELSE p.respondent_type END AS rtype,
            s.name AS standard_name,
            r.notes
        FROM responses r
        JOIN assignments a ON r.assignment_id = a.id
        JOIN packages p    ON a.package_id    = p.id
        JOIN questions q   ON r.question_id   = q.id
        JOIN standards s   ON q.standard_id   = s.id
        WHERE a.evaluatee_id=? AND a.period_id=?
          AND r.notes IS NOT NULL AND TRIM(r.notes) <> ''
        ORDER BY rtype, s.name
    ", [$evaluateeId, $periodId]);

    return [
        'evaluatee'            => $evaluatee,
        'period'               => $period,
        'scores'               => $scores,
        'by_respondent'        => $byRespondent,
        'by_respondent_domain' => $rdMap,
        'qualitative_notes'    => $notes,
    ];
}

/** Susun prompt lengkap dari data. */
function buildAIPrompt(array $d): string {
    $ev     = $d['evaluatee'];
    $period = $d['period'];
    $scores = $d['scores'];

    $lines = [];
    $lines[] = "Kamu adalah konsultan pengembangan profesional pendidikan IB (International Baccalaureate) yang berpengalaman.";
    $lines[] = "Analisis hasil evaluasi 360° berikut dan buat saran pengembangan profesional (PDP).";
    $lines[] = "Catatan: data ini adalah hasil SEMENTARA — periode evaluasi masih berjalan, jumlah responden dapat bertambah.";
    $lines[] = "";
    $lines[] = "=== PROFIL ===";
    $lines[] = "Nama: {$ev['name']}";
    $lines[] = "Peran: " . roleLabel($ev['role']);
    $lines[] = "Periode: {$period['name']}";
    $lines[] = "Skor Keseluruhan (sementara): {$scores['overall']}/4.0 (" . getScoreLevel($scores['overall'])['label_id'] . ")";
    $lines[] = "";

    $lines[] = "=== SKOR PER DOMAIN ===";
    foreach (($scores['byDomain'] ?? []) as $dom) {
        $lines[] = "- {$dom['name']}: {$dom['avg']}/4.0 (" . getScoreLevel($dom['avg'])['label_id'] . ")";
    }
    $lines[] = "";

    $lines[] = "=== SKOR PER TRAIT (10 IB Traits) ===";
    foreach (($scores['byTrait'] ?? []) as $t) {
        $lines[] = "- {$t['name']}: {$t['avg']}/4.0";
    }
    $lines[] = "";

    $lines[] = "=== PERBANDINGAN ANTAR TIPE RESPONDEN ===";
    foreach ($d['by_respondent'] as $r) {
        $label = respondentLabel($r['rtype']);
        $lines[] = "- {$label} (n={$r['n_responden']} responden, {$r['n_jawaban']} jawaban): rata-rata {$r['avg_grade']}/4.0";
        $doms = $d['by_respondent_domain'][$r['rtype']] ?? [];
        if ($doms) {
            $top = $doms[0];
            $low = end($doms);
            $lines[] = "  • Domain tertinggi: {$top['domain_name']} ({$top['avg_grade']})";
            $lines[] = "  • Domain terendah: {$low['domain_name']} ({$low['avg_grade']})";
        }
    }
    $lines[] = "";

    if (!empty($d['qualitative_notes'])) {
        $lines[] = "=== MASUKAN KUALITATIF (anonim, dikutip apa adanya) ===";
        $maxNotes = 60; // batasi agar prompt tidak membengkak
        foreach (array_slice($d['qualitative_notes'], 0, $maxNotes) as $n) {
            $note = trim(preg_replace('/\s+/', ' ', $n['notes']));
            if (mb_strlen($note) > 300) $note = mb_substr($note, 0, 300) . '…';
            $lines[] = "[" . respondentLabel($n['rtype']) . " — {$n['standard_name']}]: \"{$note}\"";
        }
        if (count($d['qualitative_notes']) > $maxNotes) {
            $lines[] = "(… dan " . (count($d['qualitative_notes']) - $maxNotes) . " catatan lainnya)";
        }
        $lines[] = "";
    }

    $lines[] = "=== INSTRUKSI ===";
    $lines[] = "Tulis dalam Bahasa Indonesia yang profesional, konstruktif, dan memotivasi, dengan format:";
    $lines[] = "1. Ringkasan Kinerja (2-3 kalimat)";
    $lines[] = "2. Analisis Perspektif 360°: identifikasi di mana persepsi antar tipe responden SELARAS dan di mana ada GAP signifikan (misalnya refleksi diri vs penilaian siswa/guru), dan apa maknanya";
    $lines[] = "3. Kekuatan Utama (2-3 poin; dukung dengan parafrase masukan kualitatif bila ada)";
    $lines[] = "4. Area Pengembangan Prioritas (2-3 poin dengan saran konkret)";
    $lines[] = "5. Rencana Aksi (SMART Goals untuk semester depan)";
    $lines[] = "6. Rekomendasi pelatihan/workshop IB yang relevan";
    $lines[] = "Maksimum 700 kata. Jangan menyebut nama responden individual (data anonim).";

    return implode("\n", $lines);
}

/** Panggil Google Gemini API (Google AI Studio). */
function callGemini(string $prompt): string {
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '' || GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE') {
        throw new Exception('GEMINI_API_KEY belum diset di config/config.php.');
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . GEMINI_API_KEY;

    $payload = json_encode([
        'contents' => [[ 'parts' => [[ 'text' => $prompt ]] ]],
        'generationConfig' => [
            'temperature'     => 0.7,
            'maxOutputTokens' => 2048,
        ],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 90,
    ]);
    $res  = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($res === false) throw new Exception("Koneksi ke Gemini gagal: {$err}");
    $data = json_decode($res, true);

    if ($http !== 200) {
        $msg = $data['error']['message'] ?? "HTTP {$http}";
        throw new Exception("Gemini API error: {$msg}");
    }

    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!$text) throw new Exception('Respons Gemini kosong / tidak valid.');
    return trim($text);
}

/** Simpan/replace saran ke ai_suggestions. */
function saveSuggestion(int $evaluateeId, int $periodId, string $text): void {
    $existing = Database::fetchOne(
        "SELECT id FROM ai_suggestions WHERE evaluatee_id=? AND period_id=?",
        [$evaluateeId, $periodId]
    );
    if ($existing) {
        Database::update('ai_suggestions',
            ['raw_suggestion' => $text, 'edited_suggestion' => null, 'generated_at' => date('Y-m-d H:i:s')],
            'id=?', [$existing['id']]
        );
    } else {
        Database::insert('ai_suggestions', [
            'evaluatee_id'   => $evaluateeId,
            'period_id'      => $periodId,
            'raw_suggestion' => $text,
            'generated_at'   => date('Y-m-d H:i:s'),
        ]);
    }
}

// ═══════════════════════════════════════════════════════════════
// ACTIONS
// ═══════════════════════════════════════════════════════════════

// ── GENERATE (AJAX, JSON) ─────────────────────────────────────
if ($action === 'generate') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!$isAdmin) throw new Exception('Hanya Admin yang dapat men-generate saran AI.');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Method tidak valid.');

        $evaluateeId = (int)($_POST['evaluatee_id'] ?? 0);
        $periodId    = (int)($_POST['period_id'] ?? 0);
        if (!$evaluateeId || !$periodId) throw new Exception('Parameter tidak lengkap.');

        $data = buildAIReportData($evaluateeId, $periodId);
        if (empty($data['scores']['overall']) || $data['scores']['overall'] <= 0) {
            throw new Exception('Belum ada respons masuk untuk evaluatee ini.');
        }

        $prompt     = buildAIPrompt($data);
        $suggestion = callGemini($prompt);
        saveSuggestion($evaluateeId, $periodId, $suggestion);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── DOWNLOAD INPUT (.txt) ─────────────────────────────────────
if ($action === 'download_input') {
    if (!$isAdmin) { http_response_code(403); exit('Hanya Admin.'); }

    $evaluateeId = (int)($_GET['evaluatee_id'] ?? 0);
    $periodId    = (int)($_GET['period_id'] ?? 0);

    try {
        $data   = buildAIReportData($evaluateeId, $periodId);
        $prompt = buildAIPrompt($data);
    } catch (Exception $e) {
        http_response_code(400); exit('Error: ' . $e->getMessage());
    }

    $slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower($data['evaluatee']['name']));
    $file = "ai_input_{$slug}_periode{$periodId}_" . date('Ymd') . ".txt";

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    echo $prompt;
    exit;
}

// ── SAVE EDIT (form POST) ─────────────────────────────────────
if ($action === 'save_edit') {
    if (!$isAdmin) { http_response_code(403); exit('Hanya Admin.'); }

    $evaluateeId = (int)($_POST['evaluatee_id'] ?? 0);
    $periodId    = (int)($_POST['period_id'] ?? 0);
    $edited      = trim($_POST['edited_suggestion'] ?? '');

    if ($evaluateeId && $periodId && $edited !== '') {
        $existing = Database::fetchOne(
            "SELECT id FROM ai_suggestions WHERE evaluatee_id=? AND period_id=?",
            [$evaluateeId, $periodId]
        );
        if ($existing) {
            Database::update('ai_suggestions', ['edited_suggestion' => $edited], 'id=?', [$existing['id']]);
        } else {
            Database::insert('ai_suggestions', [
                'evaluatee_id'      => $evaluateeId,
                'period_id'         => $periodId,
                'raw_suggestion'    => $edited,
                'edited_suggestion' => $edited,
                'generated_at'      => date('Y-m-d H:i:s'),
            ]);
        }
    }

    $back = $_SERVER['HTTP_REFERER'] ?? (APP_URL . '/reports.php?user_id=' . $evaluateeId);
    header('Location: ' . $back);
    exit;
}

http_response_code(400);
echo 'Action tidak dikenal.';
