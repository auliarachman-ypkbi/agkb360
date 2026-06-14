<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

session_name(SESSION_NAME);
session_start();

$token = trim($_GET['token'] ?? '');
$error = '';
$success = false;

// Validasi token
$user = $token ? Database::fetchOne(
    "SELECT * FROM users WHERE password_reset_token=? AND token_expires_at > NOW() AND is_active=1",
    [$token]
) : null;

if (!$user) {
    $error = 'Link tidak valid atau sudah kadaluarsa. Hubungi administrator.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $pw1 = $_POST['password']  ?? '';
    $pw2 = $_POST['password2'] ?? '';

    if (strlen($pw1) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($pw1 !== $pw2) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $hash = password_hash($pw1, PASSWORD_DEFAULT);
        Database::query(
            "UPDATE users SET password=?, password_reset_token=NULL, token_expires_at=NULL WHERE id=?",
            [$hash, $user['id']]
        );
        // Auto login
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email']= $user['email'];
        $success = true;
    }
}

if ($success) {
    header('Location: ' . APP_URL . '/dashboard/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Set Password — AGKB 360°</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f1f5f9;font-family:'Plus Jakarta Sans',sans-serif;padding:20px}
.card{background:#fff;border-radius:16px;box-shadow:0 4px 6px rgba(0,0,0,.04),0 20px 60px rgba(0,0,0,.08);overflow:hidden;width:100%;max-width:440px}
.card-hdr{background:#2C5282;padding:28px 32px;text-align:center}
.logo{font-size:26px;font-weight:700;color:#fff}
.logo span{color:#ffc901}
.logo-sub{font-size:11px;color:rgba(255,255,255,.7);margin-top:4px;letter-spacing:1px;text-transform:uppercase}
.card-body{padding:28px 32px}
.welcome{font-size:18px;font-weight:600;color:#1e293b;margin-bottom:4px}
.welcome-sub{font-size:13px;color:#64748b;margin-bottom:24px;line-height:1.6}
.field{margin-bottom:16px}
.field label{display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.field input{width:100%;height:44px;border:1px solid #e2e8f0;border-radius:8px;padding:0 14px;font-size:14px;font-family:inherit;outline:none;transition:border .15s}
.field input:focus{border-color:#2C5282;box-shadow:0 0 0 3px rgba(44,82,130,.1)}
.btn{width:100%;height:44px;background:#2C5282;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;margin-top:8px}
.btn:hover{background:#1A365D}
.err{background:#FCEBEB;border:0.5px solid #F09595;border-radius:8px;padding:10px 14px;font-size:13px;color:#791F1F;margin-bottom:16px}
.hint{font-size:11px;color:#94a3b8;margin-top:4px}
.user-info{background:#f8fafc;border-radius:8px;padding:10px 14px;font-size:13px;color:#475569;margin-bottom:20px;border-left:3px solid #2C5282}
</style>
</head>
<body>
<div class="card">
  <div class="card-hdr">
    <div class="logo">AGKB <span>360°</span></div>
    <div class="logo-sub">Performance Review Platform</div>
  </div>
  <div class="card-body">
    <?php if ($error): ?>
    <div class="err"><?= h($error) ?></div>
    <?php else: ?>
    <div class="welcome">Welcome, <?= h($user['name']) ?>!</div>
    <div class="welcome-sub">Please set your password to access the platform.</div>
    <div class="user-info">
      <i>Logging in as:</i><br>
      <strong><?= h($user['email']) ?></strong>
    </div>
    <form method="POST">
      <div class="field">
        <label>New Password</label>
        <input type="password" name="password" placeholder="Minimum 8 characters" required minlength="8" autofocus>
      </div>
      <div class="field">
        <label>Confirm Password</label>
        <input type="password" name="password2" placeholder="Re-enter your password" required>
        <div class="hint">After setting your password, you will be redirected to your dashboard.</div>
      </div>
      <button type="submit" class="btn">Set Password & Enter Platform →</button>
    </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
