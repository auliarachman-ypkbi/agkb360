<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSession();

/**
 * Halaman awal sesuai peran.
 *
 * Dashboard adalah beranda evaluasi 360°. Bagi pemantau, seluruh
 * isinya tidak berkaitan dengan apa yang boleh ia lakukan — jadi
 * ia langsung diantar ke Inbox Tiket.
 */
function berandaPeran(): string {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'pemantau') return APP_URL . '/admin/feedback.php';
    return APP_URL . '/dashboard/';
}

// Halaman yang ingin dituju sebelum diminta masuk — mis. tautan tiket
// dari email. Dibawa lewat ?ref= saat GET, lalu lewat input tersembunyi
// saat formulir dikirim, supaya tidak hilang setelah POST.
$ref  = tujuanAman($_REQUEST['ref'] ?? null);
$next = $ref ?: berandaPeran();

if (isLoggedIn()) { header('Location: ' . $next); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (login($email, $password)) {
        // berandaPeran() dihitung ulang: peran baru diketahui setelah login.
        header('Location: ' . ($ref ?: berandaPeran()));
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
<title>Masuk — AGKB 360°</title>
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/img/brand/favicon.svg">
<link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/img/brand/favicon-180.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Host+Grotesk:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root{
  /* AGKB 360° — Brand Identity 2026 */
  --oblivion:#040136;
  --oblivion-900:#02001f;
  --axiom:#030870;
  --galactic:#2201b2;
  --catalyst:#ff9101;
  --catalyst-300:#ffc36b;
  --ember:#ee4c01;
  --white:#ffffff;
  --bg:#f3f4f6;
  --text:#2f2d4d;
  --ink:#040136;
  --muted:#6b6a83;
  --border:#e3e5ea;
  --focus:rgba(34,1,178,.14);
}

html,body{height:100%;font-family:'Host Grotesk','Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased}

.page{
  min-height:100vh;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:24px;
  background:
    radial-gradient(1000px 560px at 8% -10%, rgba(34,1,178,.14) 0%, transparent 60%),
    radial-gradient(760px 460px at 96% 110%, rgba(255,145,1,.14) 0%, transparent 58%),
    var(--bg);
}

.card{
  width:100%;
  max-width:920px;
  min-height:500px;
  display:grid;
  grid-template-columns:1fr 1fr;
  border-radius:22px;
  overflow:hidden;
  box-shadow:0 4px 8px rgba(4,1,54,.05),0 24px 64px rgba(4,1,54,.16);
  animation:up .4s cubic-bezier(.22,1,.36,1) both;
}
@keyframes up{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}

/* ── LEFT ── */
.left{
  background:
    radial-gradient(720px 460px at 18% 8%, rgba(34,1,178,.55) 0%, transparent 62%),
    radial-gradient(560px 400px at 100% 100%, rgba(255,145,1,.22) 0%, transparent 60%),
    linear-gradient(158deg, var(--oblivion-900) 0%, var(--oblivion) 52%, var(--axiom) 100%);
  color:#fff;
  padding:52px 44px;
  display:flex;
  flex-direction:column;
  justify-content:center;
  position:relative;
  overflow:hidden;
}

/* modular dot grid — echo dari logo mark */
.left::before{
  content:'';
  position:absolute;
  inset:0;
  background-image:radial-gradient(rgba(255,255,255,.09) 1.5px,transparent 1.5px);
  background-size:26px 26px;
  pointer-events:none;
}

/* catalyst accent bottom */
.left::after{
  content:'';
  position:absolute;
  bottom:0;left:0;right:0;
  height:4px;
  background:linear-gradient(90deg,var(--catalyst) 0%,var(--ember) 48%,var(--galactic) 100%);
  pointer-events:none;
}

.brand{position:relative;z-index:1}

.brand-headline{
  font-size:27px;
  font-weight:700;
  color:#fff;
  line-height:1.24;
  letter-spacing:-.5px;
  margin-bottom:10px;
}
.brand-headline span{color:var(--catalyst)}

.brand-sub{
  font-size:13.5px;
  color:rgba(255,255,255,.66);
  line-height:1.68;
  max-width:330px;
}

.values{
  position:relative;z-index:1;
  margin-top:30px;
  display:flex;flex-direction:column;gap:9px;
}
.value-item{
  display:flex;align-items:center;gap:9px;
  font-size:12px;font-weight:500;
  color:rgba(255,255,255,.72);
}
.value-dot{
  width:7px;height:7px;border-radius:2px;
  background:var(--catalyst);flex:0 0 auto;
}
.value-item:nth-child(2) .value-dot{background:var(--ember)}
.value-item:nth-child(3) .value-dot{background:var(--catalyst-300)}

.logo-wrap{
  position:relative;
  z-index:1;
  margin-bottom:34px;
}

.logo-wrap img{
  height:58px;
  width:auto;
  object-fit:contain;
  display:block;
}

.logo-fallback{
  display:none;
  align-items:center;
  gap:12px;
}
.fallback-badge{
  background:var(--catalyst);
  color:var(--oblivion);
  font-size:13px;
  font-weight:800;
  padding:7px 14px;
  border-radius:10px;
}
.fallback-name{font-size:19px;font-weight:700;color:#fff;letter-spacing:-.3px}
.fallback-name span{color:var(--catalyst)}

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
  color:var(--galactic);
  margin-bottom:8px;
}

.right-title{
  font-size:26px;
  font-weight:700;
  color:var(--ink);
  letter-spacing:-.5px;
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
  background:#fdeceb;
  border:1px solid #f3b5b0;
  border-radius:10px;
  padding:10px 14px;
  font-size:13px;
  color:#b42318;
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
  color:#6f6e85;
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
  border-color:var(--galactic);
  box-shadow:0 0 0 3px var(--focus);
}

.toggle-pw{
  position:absolute;
  right:12px;top:50%;
  transform:translateY(-50%);
  background:none;border:none;
  cursor:pointer;color:#6f6e85;
  padding:6px;line-height:0;
  transition:color .15s;
}
.toggle-pw:hover{color:var(--oblivion)}

/* button */
.btn-masuk{
  width:100%;
  height:48px;
  margin-top:8px;
  background:var(--oblivion);
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
.btn-masuk:hover{background:var(--axiom);box-shadow:0 6px 18px rgba(4,1,54,.28)}
.btn-masuk:active{transform:scale(.98)}
.btn-masuk:focus-visible{outline:none;box-shadow:0 0 0 3px var(--focus)}

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
  background:var(--catalyst);
  margin-right:5px;
  vertical-align:middle;
}

/* responsive */
@media(max-width:640px){
  .card{grid-template-columns:1fr}
  .left{padding:36px 28px 28px;min-height:200px}
  .values{display:none}
  .logo-wrap{margin-bottom:24px}
  .logo-wrap img{height:46px}
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
        <img src="<?= APP_URL ?>/assets/img/brand/agkb-lockup-white.svg" alt="AGKB 360° — Platform Evaluasi Kinerja"
             onerror="this.onerror=null;this.src='<?= APP_URL ?>/assets/img/brand/agkb-lockup-white.png'">
        <div class="logo-fallback">
          <div class="fallback-badge">360°</div>
          <div class="fallback-name">AGKB <span>360°</span></div>
        </div>
      </div>
      <div class="brand">
        <h1 class="brand-headline">Multiple Perspectives,<br><span>One Insight</span></h1>
        <p class="brand-sub">Evaluasi multi-responden yang menghimpun setiap sudut pandang secara setara — untuk pengembangan profesional yang berkelanjutan.</p>
      </div>
      <div class="values">
        <div class="value-item"><span class="value-dot"></span>Integritas &amp; Objektivitas</div>
        <div class="value-item"><span class="value-dot"></span>Pertumbuhan Berkelanjutan</div>
        <div class="value-item"><span class="value-dot"></span>Transparansi &amp; Akuntabilitas</div>
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
        <?php if ($ref): ?><input type="hidden" name="ref" value="<?= h($ref) ?>"><?php endif; ?>
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
        <span><span class="version-dot"></span>AGKB 360° v2.0</span>
        <span>© 2025 AGKB</span>
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