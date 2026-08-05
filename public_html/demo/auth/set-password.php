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
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/img/brand/favicon.svg">
<link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/img/brand/favicon-180.png">
<meta name="theme-color" content="#040136">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Host+Grotesk:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --oblivion:#040136;--oblivion-900:#02001f;--axiom:#030870;--galactic:#2201b2;
  --catalyst:#ff9101;--ember:#ee4c01;--bg:#f3f4f6;--line:#e3e5ea;
  --ink:#040136;--body:#2f2d4d;--muted:#6b6a83;
}
body{
  min-height:100vh;display:flex;align-items:center;justify-content:center;
  font-family:'Host Grotesk','Segoe UI',system-ui,sans-serif;padding:20px;
  color:var(--body);-webkit-font-smoothing:antialiased;
  background:
    radial-gradient(900px 520px at 10% -8%, rgba(34,1,178,.13) 0%, transparent 60%),
    radial-gradient(700px 440px at 96% 108%, rgba(255,145,1,.13) 0%, transparent 58%),
    var(--bg);
}
.card{background:#fff;border-radius:18px;box-shadow:0 4px 8px rgba(4,1,54,.05),0 24px 64px rgba(4,1,54,.16);overflow:hidden;width:100%;max-width:440px}
.card-hdr{
  padding:30px 32px 26px;text-align:center;position:relative;
  background:
    radial-gradient(520px 300px at 20% 0%, rgba(34,1,178,.5) 0%, transparent 66%),
    linear-gradient(150deg,var(--oblivion-900) 0%,var(--oblivion) 58%,var(--axiom) 100%);
}
.card-hdr::after{content:'';position:absolute;left:0;right:0;bottom:0;height:3px;background:linear-gradient(90deg,var(--catalyst) 0%,var(--ember) 48%,var(--galactic) 100%)}
.card-hdr img{height:44px;width:auto;display:block;margin:0 auto}
.logo{font-size:25px;font-weight:700;color:#fff;letter-spacing:-.4px}
.logo span{color:var(--catalyst)}
.logo-sub{font-size:10.5px;color:rgba(255,255,255,.6);margin-top:6px;letter-spacing:1.2px;text-transform:uppercase;font-weight:500}
.card-body{padding:28px 32px}
.welcome{font-size:18px;font-weight:700;color:var(--ink);margin-bottom:4px;letter-spacing:-.3px}
.welcome-sub{font-size:13px;color:var(--muted);margin-bottom:24px;line-height:1.6}
.field{margin-bottom:16px}
.field label{display:block;font-size:11px;font-weight:600;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.field input{width:100%;height:44px;border:1.5px solid var(--line);border-radius:9px;padding:0 14px;font-size:14px;font-family:inherit;color:var(--body);outline:none;transition:border .15s,box-shadow .15s}
.field input:focus{border-color:var(--galactic);box-shadow:0 0 0 3px rgba(34,1,178,.13)}
.btn{width:100%;height:46px;background:var(--oblivion);color:#fff;border:none;border-radius:9px;font-size:14.5px;font-weight:600;cursor:pointer;font-family:inherit;margin-top:8px;transition:background .15s,box-shadow .15s}
.btn:hover{background:var(--axiom);box-shadow:0 6px 18px rgba(4,1,54,.26)}
.err{background:#fdeceb;border:1px solid #f3b5b0;border-radius:9px;padding:10px 14px;font-size:13px;color:#b42318;margin-bottom:16px}
.hint{font-size:11px;color:#6f6e85;margin-top:4px}
.user-info{background:var(--bg);border-radius:9px;padding:10px 14px;font-size:13px;color:var(--body);margin-bottom:20px;border-left:3px solid var(--catalyst)}
</style>
</head>
<body>
<div class="card">
  <div class="card-hdr">
    <img src="<?= APP_URL ?>/assets/img/brand/agkb-lockup-white.svg" alt="AGKB 360° — Platform Evaluasi Kinerja"
         onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='block';this.nextElementSibling.nextElementSibling.style.display='block'">
    <div class="logo" style="display:none">AGKB <span>360°</span></div>
    <div class="logo-sub" style="display:none">Platform Evaluasi Kinerja</div>
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
