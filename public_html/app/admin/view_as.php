<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$action = $_GET['action'] ?? '';

if ($action === 'activate') {
    if (!in_array($_SESSION['user_role'], ['superadmin','admin'])) {
        http_response_code(403); exit('Akses ditolak.');
    }
    activateViewAs();
    header('Location: ' . APP_URL . '/survey/');
    exit;
}

if ($action === 'exit') {
    exitViewAs();
    header('Location: ' . APP_URL . '/admin/');
    exit;
}

header('Location: ' . APP_URL . '/admin/');
exit;
