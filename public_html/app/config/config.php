<?php
// ============================================================
// KTB 360° Evaluation Platform - Configuration
// SMA Kemala Taruna Bhayangkara
// ============================================================

define('APP_NAME', 'KTB 360° Evaluation');
define('APP_VERSION', '1.0.0');
define('APP_SCHOOL', 'SMA Kemala Taruna Bhayangkara');
define('APP_URL', '/app'); // Subfolder deployment

// ── DATABASE ─────────────────────────────────────────────────
// Update these with your cPanel database credentials
define('DB_HOST', 'mysql');            // Docker service name
define('DB_NAME', 'ktb_production');  // Your database name
define('DB_USER', 'ktb_user');        // Your database username
define('DB_PASS', 'ktb_pass_2024');   // Your database password
define('DB_CHARSET', 'utf8mb4');

// ── SESSION ───────────────────────────────────────────────────
define('SESSION_NAME', 'demo_session');
define('SESSION_LIFETIME', 7200); // 2 hours

// ── CLAUDE AI API ─────────────────────────────────────────────
define('CLAUDE_API_KEY', 'YOUR_CLAUDE_API_KEY_HERE');
define('CLAUDE_MODEL', 'claude-sonnet-4-20250514');
define('CLAUDE_API_URL', 'https://api.anthropic.com/v1/messages');

// AI Configuration — Google Gemini (AI Studio)
define('GEMINI_API_KEY', 'AQ.Ab8RN6K4LYuXrgcaLMPCywIpDRb9R41xzD7XQBozRfp7co2NDA');

// ── DEFAULT ADMIN ─────────────────────────────────────────────
define('DEFAULT_ADMIN_EMAIL', 'admin@ktb.sch.id');
define('DEFAULT_ADMIN_PASSWORD', 'Admin@KTB2025');

// ── PATHS ─────────────────────────────────────────────────────
define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes');

// ── TIMEZONE ──────────────────────────────────────────────────
date_default_timezone_set('Asia/Jakarta');

// ── ROLES ─────────────────────────────────────────────────────
define('ROLES', [
    'admin'       => 'Administrator',
    'foundation'  => 'Pengurus Yayasan (YPKBI/YPKTB)',
    'leader'      => 'Pimpinan Sekolah',
    'teacher'     => 'Guru',
    'student'     => 'Siswa',
    'parent'      => 'Orang Tua',
]);

// ── EVAL TYPES ────────────────────────────────────────────────
define('EVAL_TYPES', [
    'leader'  => 'Pimpinan Sekolah',
    'teacher' => 'Guru',
]);

// ── RESPONDENT TYPES ──────────────────────────────────────────
define('RESPONDENT_TYPES', [
    'atasan'  => 'Atasan Langsung (YPKBI/YPKTB)',
    'peer'    => 'Rekan Sejawat',
    'guru'    => 'Guru',
    'leader'  => 'Pimpinan Sekolah',
    'ortu'    => 'Orang Tua / Wali',
    'siswa'   => 'Siswa',
    'self'    => 'Refleksi Mandiri',
]);

// ── SCORE INTERPRETATION ──────────────────────────────────────
define('SCORE_LEVELS', [
    ['min' => 1.00, 'max' => 1.75, 'label_id' => 'Tidak Terlihat', 'label_en' => 'Not Evident',  'color' => '#dc3545', 'badge' => 'danger'],
    ['min' => 1.76, 'max' => 2.50, 'label_id' => 'Berkembang',     'label_en' => 'Emerging',     'color' => '#fd7e14', 'badge' => 'warning'],
    ['min' => 2.51, 'max' => 3.25, 'label_id' => 'Cakap',          'label_en' => 'Proficient',   'color' => '#0d6efd', 'badge' => 'primary'],
    ['min' => 3.26, 'max' => 4.00, 'label_id' => 'Teladan',        'label_en' => 'Exemplary',    'color' => '#198754', 'badge' => 'success'],
]);

function getScoreLevel(float $score): array {
    if ($score >= 4.00) return [
        'label_id' => 'Sempurna',
        'label_en' => 'Perfect',
        'color'    => '#15803d',
        'bg'       => '#dcfce7',
    ];
    if ($score >= 3.75) return [
        'label_id' => 'Luar Biasa',
        'label_en' => 'Outstanding',
        'color'    => '#16a34a',
        'bg'       => '#f0fdf4',
    ];
    if ($score >= 3.25) return [
        'label_id' => 'Sangat Baik',
        'label_en' => 'Very Good',
        'color'    => '#2563eb',
        'bg'       => '#eff6ff',
    ];
    if ($score >= 2.75) return [
        'label_id' => 'Baik',
        'label_en' => 'Good',
        'color'    => '#0891b2',
        'bg'       => '#ecfeff',
    ];
    if ($score >= 2.25) return [
        'label_id' => 'Cukup Baik',
        'label_en' => 'Fair',
        'color'    => '#d97706',
        'bg'       => '#fffbeb',
    ];
    if ($score >= 1.75) return [
        'label_id' => 'Cukup',
        'label_en' => 'Sufficient',
        'color'    => '#ea580c',
        'bg'       => '#fff7ed',
    ];
    if ($score >= 1.25) return [
        'label_id' => 'Kurang',
        'label_en' => 'Below Standard',
        'color'    => '#dc2626',
        'bg'       => '#fef2f2',
    ];
    return [
        'label_id' => 'Sangat Kurang',
        'label_en' => 'Insufficient',
        'color'    => '#991b1b',
        'bg'       => '#fef2f2',
    ];
}