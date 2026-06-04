<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
startSession();
if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/dashboard/');
} else {
    header('Location: ' . APP_URL . '/login.php');
}
exit;
