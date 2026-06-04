<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSession();
if (isLoggedIn()) { header('Location: ' . APP_URL . '/dashboard/'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (login($email, $password)) {
        header('Location: ' . APP_URL . '/dashboard/');
        exit;
    }
    $error = 'Email atau kata sandi tidak valid.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — AKGB 360°</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root{
  --navy:#2C5282;
  --gold:#ffc901;
  --blue:#4F86C6;
  --white:#ffffff;
  --bg:#f5f7fa;
  --text:#111827;
  --muted:#6b7280;
  --border:#e5e7eb;
  --focus:rgba(26,86,204,.15);
}

html,body{height:100%;font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text)}

.page{
  min-height:100vh;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:24px;
}

.card{
  width:100%;
  max-width:880px;
  min-height:480px;
  display:grid;
  grid-template-columns:1fr 1fr;
  border-radius:20px;
  overflow:hidden;
  box-shadow:0 4px 6px rgba(0,0,0,.04),0 20px 60px rgba(0,0,0,.08);
  animation:up .4s cubic-bezier(.22,1,.36,1) both;
}
@keyframes up{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}

/* ── LEFT ── */
.left{
  background:var(--white);
  border-right:1px solid var(--border);
  padding:52px 44px;
  display:flex;
  flex-direction:column;
  justify-content:center;
  position:relative;
  overflow:hidden;
}

/* subtle dot grid - very light */
.left::before{
  content:'';
  position:absolute;
  inset:0;
  background-image:radial-gradient(rgba(0,31,62,.06) 1px,transparent 1px);
  background-size:24px 24px;
  pointer-events:none;
}

/* navy accent bottom */
.left::after{
  content:'';
  position:absolute;
  bottom:0;left:0;right:0;
  height:4px;
  background:#4F86C6;
  pointer-events:none;
}

.brand{position:relative;z-index:1}

.brand-headline{
  font-size:26px;
  font-weight:700;
  color:var(--navy);
  line-height:1.25;
  letter-spacing:-.4px;
  margin-bottom:10px;
}

.brand-sub{
  font-size:13px;
  color:var(--muted);
  line-height:1.65;
}

.logo-wrap{
  position:relative;
  z-index:1;
  margin-bottom:32px;
}

.logo-wrap img{
  height:64px;
  width:auto;
  object-fit:contain;
  display:block;
}

.logo-fallback{
  display:none;
  align-items:center;
  gap:10px;
}
.fallback-badge{
  background:var(--navy);
  color:var(--gold);
  font-size:13px;
  font-weight:700;
  padding:6px 14px;
  border-radius:8px;
}
.fallback-name{font-size:18px;font-weight:700;color:var(--navy)}
.fallback-name span{color:var(--gold)}

/* ── RIGHT ── */
.right{
  background:var(--white);
  padding:52px 48px;
  display:flex;
  flex-direction:column;
  justify-content:center;
}

.right-label{
  font-size:11px;
  font-weight:700;
  letter-spacing:1.4px;
  text-transform:uppercase;
  color:var(--blue);
  margin-bottom:8px;
}

.right-title{
  font-size:26px;
  font-weight:700;
  color:var(--text);
  letter-spacing:-.4px;
  margin-bottom:4px;
}

.right-sub{
  font-size:13.5px;
  color:var(--muted);
  margin-bottom:28px;
}

/* error */
.error-msg{
  display:flex;
  align-items:center;
  gap:8px;
  background:#fef2f2;
  border:1px solid #fecaca;
  border-radius:10px;
  padding:10px 14px;
  font-size:13px;
  color:#b91c1c;
  margin-bottom:18px;
}

/* field */
.field{margin-bottom:16px}

.field label{
  display:block;
  font-size:12px;
  font-weight:600;
  color:var(--muted);
  text-transform:uppercase;
  letter-spacing:.6px;
  margin-bottom:6px;
}

.input-wrap{position:relative}

.input-wrap svg.icon{
  position:absolute;
  left:14px;top:50%;
  transform:translateY(-50%);
  color:#9ca3af;
  pointer-events:none;
}

.field input{
  height:48px;
  width:100%;
  border:1.5px solid var(--border);
  border-radius:10px;
  padding:0 44px;
  font-family:inherit;
  font-size:14.5px;
  color:var(--text);
  background:var(--white);
  outline:none;
  transition:border-color .15s,box-shadow .15s;
}

.field input:focus{
  border-color:var(--blue);
  box-shadow:0 0 0 3px var(--focus);
}

.toggle-pw{
  position:absolute;
  right:12px;top:50%;
  transform:translateY(-50%);
  background:none;border:none;
  cursor:pointer;color:#9ca3af;
  padding:6px;line-height:0;
  transition:color .15s;
}
.toggle-pw:hover{color:var(--navy)}

/* button */
.btn-masuk{
  width:100%;
  height:48px;
  margin-top:8px;
  background:var(--navy);
  color:#fff;
  font-family:inherit;
  font-size:15px;
  font-weight:600;
  border:none;
  border-radius:10px;
  cursor:pointer;
  transition:background .15s,box-shadow .15s,transform .1s;
  letter-spacing:.01em;
}
.btn-masuk:hover{background:#1A365D;box-shadow:0 4px 14px rgba(0,31,62,.25)}
.btn-masuk:active{transform:scale(.98)}

/* footer */
.right-footer{
  margin-top:24px;
  padding-top:16px;
  border-top:1px solid var(--border);
  display:flex;
  align-items:center;
  justify-content:space-between;
  font-size:11.5px;
  color:var(--muted);
}

.version-dot{
  display:inline-block;
  width:6px;height:6px;
  border-radius:50%;
  background:var(--gold);
  margin-right:5px;
  vertical-align:middle;
}

/* responsive */
@media(max-width:640px){
  .card{grid-template-columns:1fr}
  .left{padding:36px 28px 28px;min-height:200px}
  .right{padding:32px 28px}
  .brand-headline{font-size:22px}
  .right-title{font-size:22px}
  .right-footer{flex-direction:column;gap:6px;text-align:center}
}
</style>
</head>
<body>
<div class="page">
  <div class="card">

    <!-- LEFT -->
    <div class="left">
      <div class="logo-wrap">
        <img src="<?= APP_URL ?>/assets/img/logoAKGB360.png" alt="AKGB 360°"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div class="logo-fallback">
          <div class="fallback-badge">360°</div>
          <div class="fallback-name">AKGB <span>360°</span></div>
        </div>
      </div>
      <div class="brand">
        <h1 class="brand-headline">Platform Evaluasi<br>Kinerja 360°</h1>
        <p class="brand-sub">Evaluasi multi-responden yang komprehensif untuk pengembangan profesional berkelanjutan.</p>
      </div>
    </div>

    <!-- RIGHT -->
    <div class="right">
      <div class="right-label">Selamat datang</div>
      <div class="right-title">Masuk ke akun Anda</div>
      <div class="right-sub">Gunakan email dan kata sandi yang terdaftar.</div>

      <?php if ($error): ?>
      <div class="error-msg">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <div class="field">
          <label for="email">Email</label>
          <div class="input-wrap">
            <svg class="icon" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <rect x="2" y="4" width="20" height="16" rx="2"/>
              <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
            </svg>
            <input type="email" id="email" name="email"
              placeholder="nama@sekolah.sch.id"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              autocomplete="username" autofocus required>
          </div>
        </div>

        <div class="field">
          <label for="password">Kata Sandi</label>
          <div class="input-wrap">
            <svg class="icon" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <rect x="3" y="11" width="18" height="11" rx="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <input type="password" id="password" name="password"
              placeholder="••••••••"
              autocomplete="current-password" required>
            <button type="button" class="toggle-pw" id="pwToggle">
              <svg id="eyeIcon" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-masuk">Masuk</button>
      </form>

      <div class="right-footer">
        <span><span class="version-dot"></span>AKGB 360° v2.0</span>
        <span>© 2025 AKGB</span>
      </div>
    </div>

  </div>
</div>

<script>
document.getElementById('pwToggle').addEventListener('click', function(){
  const pw = document.getElementById('password');
  pw.type = pw.type === 'password' ? 'text' : 'password';
});
</script>
</body>
</html>