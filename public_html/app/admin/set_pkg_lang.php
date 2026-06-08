<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requireRole(['admin','foundation']);

header('Content-Type: application/json');

$pkgId = (int)($_POST['pkg_id'] ?? 0);
$lang  = $_POST['lang'] ?? '';

if (!$pkgId || !in_array($lang, ['both','id','en'])) {
    echo json_encode(['ok'=>false,'msg'=>'Parameter tidak valid.']);
    exit;
}

Database::update('packages', ['question_lang' => $lang], 'id=?', [$pkgId]);
echo json_encode(['ok'=>true,'msg'=>'Disimpan.']);
