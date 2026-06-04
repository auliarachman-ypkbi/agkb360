<?php
require_once __DIR__ . '/db.php';

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params(['lifetime' => SESSION_LIFETIME, 'httponly' => true, 'samesite' => 'Strict']);
        session_start();
    }
}

function login(string $email, string $password): bool {
    $user = Database::fetchOne("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);
    if ($user && password_verify($password, $user['password'])) {
        startSession();
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email']= $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        Database::query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
        return true;
    }
    return false;
}

function logout(): void {
    startSession();
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

function isLoggedIn(): bool {
    startSession();
    return !empty($_SESSION['logged_in']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php?ref=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function requireRole(array|string $roles): void {
    requireLogin();
    $allowed = is_array($roles) ? $roles : [$roles];
    if (!in_array($_SESSION['user_role'], $allowed)) {
        http_response_code(403);
        include BASE_PATH . '/includes/403.php';
        exit;
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return Database::fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

function isAdmin(): bool    { return isLoggedIn() && in_array($_SESSION['user_role'], ['superadmin','admin']); }
function isLeader(): bool   { return isLoggedIn() && in_array($_SESSION['user_role'], ['admin','leader','foundation']); }
function isTeacher(): bool  { return isLoggedIn() && $_SESSION['user_role'] === 'teacher'; }
function isStudent(): bool  { return isLoggedIn() && $_SESSION['user_role'] === 'student'; }
function isFoundation(): bool { return isLoggedIn() && in_array($_SESSION['user_role'], ['admin','foundation']); }

function canAccessAdmin(): bool {
    return isLoggedIn() && in_array($_SESSION['user_role'], ['superadmin','admin','foundation']);
}

function isSuperAdmin(): bool {
    return isLoggedIn() && $_SESSION['user_role'] === 'superadmin';
}

function isTester(): bool {
    return isLoggedIn() && $_SESSION['user_role'] === 'tester';
}

function csrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    startSession();
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token']));
    }
}