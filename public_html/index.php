<?php
// ============================================================
// AGKB 360° — Halaman Beranda
// Satu halaman yang menjelaskan filosofi, alur, dan cara kerja
// platform: Monitoring & Evaluasi 360° + Feedback & Penanganan.
//
// Substansi diambil dari deck Monev-360.
// ============================================================
$tahun = date('Y');
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AGKB 360° — Mengukur untuk Bertumbuh</title>
<meta name="description" content="AGKB 360° adalah platform monitoring, evaluasi, dan umpan balik untuk ekosistem sekolah Yayasan Pendidikan Kader Bangsa Indonesia. Menghimpun banyak sudut pandang menjadi satu insight yang objektif.">
<meta name="theme-color" content="#040136">
<link rel="icon" type="image/svg+xml" href="/assets/img/brand/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/img/brand/favicon-32.png">
<link rel="apple-touch-icon" href="/assets/img/brand/favicon-180.png">
<meta property="og:title" content="AGKB 360° — Mengukur untuk Bertumbuh">
<meta property="og:description" content="Platform monitoring, evaluasi, dan umpan balik untuk ekosistem sekolah Kader Bangsa.">
<meta property="og:image" content="/assets/img/brand/agkb-lockup.png">
<meta property="og:type" content="website">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Host+Grotesk:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root{
  --oblivion:#040136; --oblivion-900:#02001f; --oblivion-700:#0a0750;
  --axiom:#030870;    --galactic:#2201b2;     --galactic-050:#eeebfc;
  --galactic-200:#b9aef2;
  --catalyst:#ff9101; --catalyst-300:#ffc36b; --catalyst-100:#fff1dc;
  --catalyst-050:#fff8ef;
  --ember:#ee4c01;    --ember-700:#b83a01;
  --clarity:#f3f4f6;  --line:#e3e5ea;         --line-strong:#cdd0d8;
  --ink:#040136;      --body:#2f2d4d;         --muted:#6b6a83;
  --success:#027a48;  --success-bg:#e7f6ef;
  --danger:#b42318;   --danger-bg:#fdeceb;
}

html{scroll-behavior:smooth}
body{
  font-family:'Host Grotesk','Segoe UI',system-ui,-apple-system,sans-serif;
  color:var(--body); background:#fff; line-height:1.7;
  -webkit-font-smoothing:antialiased; font-size:16px;
}
h1,h2,h3,h4{color:var(--ink); line-height:1.2; letter-spacing:-.025em; font-weight:700}
a{color:var(--galactic); text-decoration:none}
.wrap{max-width:1120px; margin:0 auto; padding:0 24px}
.narrow{max-width:760px}
section{padding:88px 0}
.eyebrow{
  font-size:11px; font-weight:700; letter-spacing:.16em; text-transform:uppercase;
  color:var(--catalyst); margin-bottom:14px;
}
.eyebrow.dark{color:var(--ember-700)}
.lead{font-size:18px; color:var(--muted); line-height:1.75}

/* ── NAV ─────────────────────────────────────────────── */
.nav{
  position:sticky; top:0; z-index:50; background:rgba(4,1,54,.94);
  backdrop-filter:blur(12px); border-bottom:1px solid rgba(255,255,255,.09);
}
.nav-in{display:flex; align-items:center; gap:26px; height:62px}
.nav-brand{display:flex; align-items:center; gap:10px}
.nav-brand img{height:30px; width:auto; display:block}
.nav-brand span{font-size:16px; font-weight:700; color:#fff; letter-spacing:-.02em}
.nav-brand span i{color:var(--catalyst); font-style:normal}
.nav-links{display:flex; gap:22px; margin-left:auto; align-items:center}
.nav-links a{color:rgba(255,255,255,.72); font-size:13.5px; font-weight:500}
.nav-links a:hover{color:#fff}
.btn{
  display:inline-flex; align-items:center; gap:8px; border-radius:9px;
  padding:10px 20px; font-size:14px; font-weight:600; border:1.5px solid transparent;
  transition:all .16s; cursor:pointer;
}
.btn-primary{background:var(--catalyst); color:var(--oblivion)}
.btn-primary:hover{background:var(--ember-700); color:#fff}
.btn-ghost{border-color:rgba(255,255,255,.28); color:#fff}
.btn-ghost:hover{background:rgba(255,255,255,.1)}
.btn-outline{border-color:var(--line-strong); color:var(--ink)}
.btn-outline:hover{border-color:var(--ink); background:var(--clarity)}
.btn-sm{padding:7px 15px; font-size:13px}

/* ── DROPDOWN MASUK ──────────────────────────────────── */
.dd{position:relative}
.dd-toggle{gap:7px}
.dd-caret{transition:transform .18s; display:inline-block; font-size:10px; opacity:.75}
.dd.open .dd-caret{transform:rotate(180deg)}
.dd-menu{
  position:absolute; right:0; top:calc(100% + 10px); min-width:266px;
  background:var(--oblivion-700); border:1px solid rgba(255,255,255,.14);
  border-radius:14px; padding:6px; box-shadow:0 20px 48px rgba(4,1,54,.42);
  opacity:0; visibility:hidden; transform:translateY(-6px);
  transition:opacity .16s, transform .16s, visibility .16s; z-index:60;
}
.dd.open .dd-menu{opacity:1; visibility:visible; transform:translateY(0)}
.dd-item{
  display:flex; gap:12px; align-items:flex-start; padding:12px 13px;
  border-radius:10px; transition:background .15s;
}
.dd-item:hover{background:rgba(255,255,255,.09)}
.dd-dot{
  flex:0 0 auto; width:9px; height:9px; border-radius:3px;
  background:var(--catalyst); margin-top:6px;
}
.dd-item.demo .dd-dot{background:var(--galactic-200)}
.dd-t{font-size:14px; font-weight:600; color:#fff; line-height:1.35}
.dd-d{font-size:12px; color:rgba(255,255,255,.58); line-height:1.55; margin-top:3px}
.dd-sep{height:1px; background:rgba(255,255,255,.1); margin:4px 9px}

/* Dropdown terang, untuk dipakai di area hero */
.dd-menu.light{background:#fff; border-color:var(--line)}
.dd-menu.light .dd-item:hover{background:var(--clarity)}
.dd-menu.light .dd-t{color:var(--ink)}
.dd-menu.light .dd-d{color:var(--muted)}
.dd-menu.light .dd-sep{background:var(--line)}

/* ── HERO ────────────────────────────────────────────── */
.hero{
  background:
    radial-gradient(900px 620px at 14% -6%, rgba(34,1,178,.5) 0%, transparent 62%),
    radial-gradient(760px 540px at 92% 106%, rgba(255,145,1,.24) 0%, transparent 58%),
    linear-gradient(158deg, var(--oblivion-900) 0%, var(--oblivion) 52%, var(--axiom) 100%);
  color:#fff; padding:104px 0 96px; position:relative; overflow:hidden;
}
.hero::before{
  content:''; position:absolute; inset:0;
  background-image:radial-gradient(rgba(255,255,255,.07) 1.5px, transparent 1.5px);
  background-size:30px 30px; pointer-events:none;
}
.hero .wrap{position:relative; z-index:1}
.hero-lockup{max-width:340px; width:74%; margin-bottom:34px; display:block}
.hero h1{color:#fff; font-size:clamp(34px,5.4vw,58px); margin-bottom:20px; font-weight:800}
.hero h1 em{color:var(--catalyst); font-style:normal}
.hero p{font-size:clamp(16px,2vw,19px); color:rgba(255,255,255,.7); max-width:610px; line-height:1.72}
.hero-cta{display:flex; gap:12px; margin-top:36px; flex-wrap:wrap}
.hero-stats{
  display:flex; gap:40px; margin-top:56px; padding-top:30px;
  border-top:1px solid rgba(255,255,255,.14); flex-wrap:wrap;
}
.hs-n{font-size:30px; font-weight:800; color:var(--catalyst); line-height:1; letter-spacing:-.03em}
.hs-l{font-size:12.5px; color:rgba(255,255,255,.6); margin-top:6px}

/* ── PEMBUKA FILOSOFIS ───────────────────────────────── */
.opening{background:var(--clarity); text-align:center}
.opening blockquote{
  font-size:clamp(21px,3.1vw,30px); color:var(--ink); font-weight:600;
  line-height:1.45; letter-spacing:-.025em; max-width:780px; margin:0 auto;
}
.opening .sub{font-size:17px; color:var(--muted); margin-top:26px; max-width:640px; margin-inline:auto}
.opening .punch{
  margin-top:44px; padding-top:34px; border-top:2px solid var(--catalyst);
  display:inline-block; max-width:600px;
}
.opening .punch p{font-size:19px; color:var(--ink); font-weight:600; line-height:1.55}

/* ── PILAR ───────────────────────────────────────────── */
.pillars{display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:44px}
.pillar{
  border:1px solid var(--line); border-radius:16px; padding:32px;
  background:#fff; transition:all .18s;
}
.pillar:hover{border-color:var(--line-strong); transform:translateY(-2px); box-shadow:0 12px 34px rgba(4,1,54,.08)}
.pillar-tag{
  display:inline-block; font-size:11px; font-weight:700; letter-spacing:.1em;
  text-transform:uppercase; padding:4px 11px; border-radius:20px; margin-bottom:16px;
}
.pillar-1 .pillar-tag{background:var(--galactic-050); color:var(--axiom)}
.pillar-2 .pillar-tag{background:var(--catalyst-100); color:var(--ember-700)}
.pillar h3{font-size:21px; margin-bottom:10px}
.pillar p{font-size:15px; color:var(--muted); margin-bottom:18px}
.pillar ul{list-style:none; font-size:14.5px}
.pillar li{padding:7px 0 7px 24px; position:relative; color:var(--body); border-top:1px solid var(--clarity)}
.pillar li:first-child{border-top:none}
.pillar li::before{
  content:''; position:absolute; left:0; top:15px; width:8px; height:8px;
  border-radius:2px; background:var(--catalyst);
}
.pillar-1 li::before{background:var(--galactic)}

/* ── NILAI ───────────────────────────────────────────── */
.values{background:var(--oblivion); color:#fff}
.values h2{color:#fff}
.values .lead{color:rgba(255,255,255,.62)}
.v-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:22px; margin-top:44px}
.v-card{border-top:3px solid var(--catalyst); padding-top:22px}
.v-card:nth-child(2){border-top-color:var(--ember)}
.v-card:nth-child(3){border-top-color:var(--galactic-200)}
.v-card h4{color:#fff; font-size:17px; margin-bottom:10px}
.v-card p{font-size:14.5px; color:rgba(255,255,255,.62); line-height:1.72}

/* ── TRAIT & DOMAIN ──────────────────────────────────── */
.trait-row{display:flex; flex-wrap:wrap; gap:9px; margin-top:28px}
.trait{
  border:1px solid var(--catalyst-300); background:var(--catalyst-050);
  color:var(--ember-700); border-radius:20px; padding:7px 16px;
  font-size:13.5px; font-weight:600;
}
.dom-grid{display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:38px}
.dom-col{border:1px solid var(--line); border-radius:16px; overflow:hidden; background:#fff}
.dom-head{background:var(--oblivion); color:#fff; padding:18px 24px}
.dom-head h4{color:#fff; font-size:17px}
.dom-head span{font-size:12.5px; color:rgba(255,255,255,.6)}
.dom-body{padding:8px 24px 20px}
.dom-item{
  display:flex; align-items:center; gap:12px; padding:13px 0;
  border-bottom:1px solid var(--clarity); font-size:14.5px;
}
.dom-item:last-child{border-bottom:none}
.dom-item b{color:var(--ink); font-weight:600; flex:1}
.dom-n{
  font-size:11.5px; font-weight:700; color:var(--axiom);
  background:var(--galactic-050); border-radius:20px; padding:3px 10px; white-space:nowrap;
}

/* ── 360 ─────────────────────────────────────────────── */
.circle-wrap{margin-top:40px; display:flex; justify-content:center}
.circle-wrap svg{max-width:100%; height:auto}
.note{
  font-size:13.5px; color:var(--muted); background:var(--clarity);
  border-left:3px solid var(--catalyst); border-radius:0 9px 9px 0;
  padding:14px 18px; margin-top:28px; line-height:1.7;
}

/* ── ALUR ────────────────────────────────────────────── */
.flow{display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-top:40px}
.flow-step{position:relative; padding-top:18px; border-top:2px solid var(--line)}
.flow-step.on{border-top-color:var(--catalyst)}
.flow-n{
  font-size:12px; font-weight:800; color:var(--catalyst);
  letter-spacing:.1em; margin-bottom:9px;
}
.flow-step h4{font-size:17px; margin-bottom:8px}
.flow-step p{font-size:14.5px; color:var(--muted)}

.rubric{display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-top:34px}
.rub{border-radius:12px; padding:18px; border:1px solid}
.rub-1{background:var(--danger-bg);   border-color:#f3b5b0}
.rub-2{background:var(--catalyst-100);border-color:var(--catalyst-300)}
.rub-3{background:var(--galactic-050);border-color:var(--galactic-200)}
.rub-4{background:var(--success-bg);  border-color:#a5dcc3}
.rub-n{font-size:26px; font-weight:800; line-height:1; letter-spacing:-.03em}
.rub-1 .rub-n{color:var(--danger)} .rub-2 .rub-n{color:var(--ember-700)}
.rub-3 .rub-n{color:var(--axiom)}  .rub-4 .rub-n{color:var(--success)}
.rub-id{font-size:15px; font-weight:700; color:var(--ink); margin-top:10px}
.rub-en{font-size:12.5px; color:var(--muted); font-style:italic}

/* ── CONTOH PERTANYAAN ───────────────────────────────── */
.qcompare{display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-top:30px}
.qbox{border:1px solid var(--line); border-radius:14px; padding:24px; background:#fff}
.qbox.alt{background:var(--catalyst-050); border-color:var(--catalyst-300)}
.qbox-label{
  font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
  color:var(--muted); margin-bottom:14px;
}
.qbox blockquote{font-size:16px; color:var(--ink); font-weight:500; line-height:1.6}
.qbox .hint{font-size:12.5px; color:var(--muted); margin-top:14px}

/* ── SIKLUS ──────────────────────────────────────────── */
.cycle{background:var(--clarity)}
.cyc-grid{display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:38px}
.cyc{background:#fff; border:1px solid var(--line); border-radius:16px; padding:28px; border-left:4px solid var(--galactic)}
.cyc:last-child{border-left-color:var(--catalyst)}
.cyc h4{font-size:19px; margin-bottom:6px}
.cyc .range{font-size:14.5px; color:var(--muted)}
.cyc .mark{font-size:13px; color:var(--ink); font-weight:600; margin-top:12px; padding-top:12px; border-top:1px solid var(--clarity)}

/* ── MANFAAT ─────────────────────────────────────────── */
.ben-grid{display:grid; grid-template-columns:repeat(2,1fr); gap:18px; margin-top:38px}
.ben{display:flex; gap:16px; align-items:flex-start}
.ben-n{
  flex:0 0 auto; width:32px; height:32px; border-radius:9px;
  background:var(--oblivion); color:var(--catalyst);
  display:flex; align-items:center; justify-content:center;
  font-size:14px; font-weight:800;
}
.ben h4{font-size:16px; margin-bottom:5px}
.ben p{font-size:14.5px; color:var(--muted)}

/* ── CTA ─────────────────────────────────────────────── */
.cta{
  background:
    radial-gradient(680px 400px at 18% 4%, rgba(34,1,178,.46) 0%, transparent 64%),
    linear-gradient(152deg, var(--oblivion-900) 0%, var(--oblivion) 56%, var(--axiom) 100%);
  color:#fff;
}
.cta h2{color:#fff}
.cta .lead{color:rgba(255,255,255,.66)}
.cta-grid{display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-top:40px}
.cta-card{
  border:1px solid rgba(255,255,255,.17); border-radius:16px; padding:28px;
  background:rgba(255,255,255,.045); transition:all .18s;
}
.cta-card:hover{background:rgba(255,255,255,.09); border-color:rgba(255,255,255,.3)}
.cta-card h3{color:#fff; font-size:19px; margin-bottom:8px}
.cta-card p{font-size:14.5px; color:rgba(255,255,255,.63); margin-bottom:20px; min-height:66px}

/* ── PENUTUP ─────────────────────────────────────────── */
.closing{text-align:center; background:var(--clarity)}
.closing blockquote{
  font-size:clamp(20px,2.9vw,28px); color:var(--ink); font-weight:600;
  line-height:1.45; max-width:700px; margin:0 auto; letter-spacing:-.02em;
}
.closing .who{font-size:14px; color:var(--muted); margin-top:20px}

/* ── FOOTER ──────────────────────────────────────────── */
footer{background:var(--oblivion-900); color:rgba(255,255,255,.62); padding:52px 0 34px}
.f-top{display:flex; gap:28px; align-items:flex-start; flex-wrap:wrap; padding-bottom:28px; border-bottom:1px solid rgba(255,255,255,.1)}
.f-brand img{height:34px; margin-bottom:12px; display:block}
.f-brand p{font-size:13.5px; max-width:330px; line-height:1.7}
.f-links{display:flex; gap:52px; margin-left:auto; flex-wrap:wrap}
.f-col h5{font-size:11px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:rgba(255,255,255,.42); margin-bottom:12px}
.f-col a,.f-col span{display:block; font-size:13.5px; color:rgba(255,255,255,.68); padding:4px 0}
.f-col a:hover{color:var(--catalyst)}
.f-bot{display:flex; justify-content:space-between; gap:16px; padding-top:22px; font-size:12.5px; flex-wrap:wrap}

h2{font-size:clamp(26px,3.6vw,38px); margin-bottom:16px}

@media(max-width:900px){
  section{padding:64px 0}
  .pillars,.dom-grid,.qcompare,.cyc-grid,.cta-grid,.ben-grid{grid-template-columns:1fr}
  .v-grid,.flow{grid-template-columns:1fr}
  .rubric{grid-template-columns:1fr 1fr}
  .nav-links a:not(.btn){display:none}
  .hero-stats{gap:26px}
}
</style>
</head>
<body>

<!-- ── NAV ───────────────────────────────────────────── -->
<nav class="nav">
  <div class="wrap nav-in">
    <a href="/" class="nav-brand">
      <img src="/assets/img/brand/agkb-mark.svg" alt="">
      <span>AGKB <i>360°</i></span>
    </a>
    <div class="nav-links">
      <a href="#mengapa">Mengapa</a>
      <a href="#diukur">Yang Diukur</a>
      <a href="#menilai">Siapa Menilai</a>
      <a href="#feedback">Feedback</a>

      <div class="dd" data-dd>
        <button type="button" class="btn btn-primary btn-sm dd-toggle" data-dd-toggle aria-expanded="false" aria-haspopup="true">
          Masuk Platform <span class="dd-caret">▼</span>
        </button>
        <div class="dd-menu" role="menu">
          <a href="/app" class="dd-item" role="menuitem">
            <span class="dd-dot"></span>
            <span>
              <span class="dd-t">Platform</span>
              <span class="dd-d">Data sebenarnya. Untuk pengguna yang terdaftar di platform.</span>
            </span>
          </a>
          <div class="dd-sep"></div>
          <a href="/demo" class="dd-item demo" role="menuitem">
            <span class="dd-dot"></span>
            <span>
              <span class="dd-t">Versi Demo</span>
              <span class="dd-d">Data contoh dengan nama fiktif. Bebas dijelajahi.</span>
            </span>
          </a>
        </div>
      </div>
    </div>
  </div>
</nav>

<!-- ── HERO ──────────────────────────────────────────── -->
<header class="hero">
  <div class="wrap">
    <img src="/assets/img/brand/agkb-lockup-white.svg" alt="AGKB 360° — Platform Evaluasi Kinerja" class="hero-lockup">
    <h1>Mengukur untuk <em>Bertumbuh</em></h1>
    <p>
      Sistem monitoring, evaluasi, dan umpan balik untuk ekosistem sekolah
      Yayasan Pendidikan Kader Bangsa Indonesia — menghimpun banyak sudut pandang
      menjadi satu gambaran yang objektif dan dapat dipercaya.
    </p>
    <div class="hero-cta">
      <div class="dd" data-dd>
        <button type="button" class="btn btn-primary dd-toggle" data-dd-toggle aria-expanded="false" aria-haspopup="true">
          Masuk ke Platform <span class="dd-caret">▼</span>
        </button>
        <div class="dd-menu light" role="menu" style="left:0; right:auto">
          <a href="/app" class="dd-item" role="menuitem">
            <span class="dd-dot"></span>
            <span>
              <span class="dd-t">Platform</span>
              <span class="dd-d">Data sebenarnya. Untuk pengguna yang terdaftar di platform.</span>
            </span>
          </a>
          <div class="dd-sep"></div>
          <a href="/demo" class="dd-item demo" role="menuitem">
            <span class="dd-dot"></span>
            <span>
              <span class="dd-t">Versi Demo</span>
              <span class="dd-d">Data contoh dengan nama fiktif. Bebas dijelajahi.</span>
            </span>
          </a>
        </div>
      </div>
      <a href="#platform" class="btn btn-ghost">Pelajari Dulu</a>
    </div>
    <div class="hero-stats">
      <div><div class="hs-n">360°</div><div class="hs-l">Sudut pandang penilaian</div></div>
      <div><div class="hs-n">10</div><div class="hs-l">Trait profil lulusan</div></div>
      <div><div class="hs-n">35</div><div class="hs-l">Standar penilaian</div></div>
      <div><div class="hs-n">2×</div><div class="hs-l">Siklus per tahun ajaran</div></div>
    </div>
  </div>
</header>

<!-- ── PEMBUKA ───────────────────────────────────────── -->
<section class="opening">
  <div class="wrap narrow">
    <div class="eyebrow dark">Sebelum Kita Mulai</div>
    <blockquote>
      Pendidikan bukan hanya soal KPI atau indikator kinerja.
    </blockquote>
    <p class="sub">
      Hal yang dibangun di ruang kelas setiap hari adalah manusia — cara berpikir,
      karakter, kepercayaan diri, dan masa depan mereka.
      <strong style="color:var(--ink)">Tidak ada angka yang bisa menggantikan itu.</strong>
    </p>
    <div class="punch">
      <p>
        Indikator itu bukan segalanya.<br>
        Tapi dengan indikator, kita tahu <em style="color:var(--ember-700);font-style:normal">ada di mana</em>,
        dan <em style="color:var(--ember-700);font-style:normal">mau ke mana</em>.
      </p>
    </div>
  </div>
</section>

<!-- ── DUA PILAR ─────────────────────────────────────── -->
<section id="platform">
  <div class="wrap">
    <div class="eyebrow">Satu Platform, Dua Pilar</div>
    <h2>Bukan hanya survei tahunan</h2>
    <p class="lead narrow">
      AGKB 360° tumbuh dari sistem evaluasi menjadi ekosistem umpan balik yang berjalan
      sepanjang tahun. Satu mengukur secara berkala, satu lagi menangkap yang terjadi hari ini.
    </p>

    <div class="pillars">
      <div class="pillar pillar-1">
        <span class="pillar-tag">Pilar 1</span>
        <h3>Monitoring &amp; Evaluasi 360°</h3>
        <p>Pengukuran berkala terhadap Pimpinan Sekolah dan Guru dari seluruh sudut pandang ekosistem sekolah.</p>
        <ul>
          <li>Dinilai dari lima sampai enam kelompok berbeda, bukan satu arah</li>
          <li>Standar dan instrumen seragam lintas sekolah</li>
          <li>Rubrik empat tingkat, bukan sekadar angka</li>
          <li>Laporan per individu sebagai dasar rencana pengembangan</li>
        </ul>
      </div>
      <div class="pillar pillar-2">
        <span class="pillar-tag">Pilar 2</span>
        <h3>Feedback &amp; Penanganan</h3>
        <p>Kanal apresiasi, keluhan, dan laporan yang terbuka setiap saat — dengan penanggung jawab dan batas waktu yang jelas.</p>
        <ul>
          <li>Tiga jalur terpisah: apresiasi, kendala, dan perlindungan anak</li>
          <li>Setiap laporan punya nomor tiket dan unit penanganan</li>
          <li>Batas waktu berjenjang, naik otomatis bila terlampaui</li>
          <li>Penyelesaian terstruktur yang dikirim balik ke pelapor</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- ── NILAI ─────────────────────────────────────────── -->
<section class="values">
  <div class="wrap">
    <div class="eyebrow">Tiga Nilai Dasar</div>
    <h2>Fondasi yang menopang seluruh sistem</h2>
    <p class="lead narrow">
      Setiap keputusan rancangan di platform ini — dari cara pertanyaan disusun sampai siapa
      yang boleh melihat apa — berangkat dari tiga nilai berikut.
    </p>
    <div class="v-grid">
      <div class="v-card">
        <h4>Integritas &amp; Objektivitas</h4>
        <p>
          Evaluasi yang adil dimulai dari data yang terpercaya. Setiap masukan diproses
          secara setara, bebas bias, dan terukur — sehingga menghasilkan insight yang
          kredibel sebagai dasar pengambilan keputusan.
        </p>
      </div>
      <div class="v-card">
        <h4>Pertumbuhan Berkelanjutan</h4>
        <p>
          Evaluasi bukanlah akhir, melainkan awal dari proses pengembangan. Setiap hasil
          menjadi landasan untuk mengenali potensi, memperkuat kompetensi, dan mendorong
          peningkatan kualitas secara berkelanjutan.
        </p>
      </div>
      <div class="v-card">
        <h4>Transparansi &amp; Akuntabilitas</h4>
        <p>
          Kepercayaan dibangun melalui proses yang terbuka. Setiap tahapan terdokumentasi
          dengan jelas, memberi ruang bagi seluruh pemangku kepentingan untuk berpartisipasi,
          serta menghasilkan laporan yang akurat dan mudah dipahami.
        </p>
      </div>
    </div>
  </div>
</section>

<!-- ── MENGAPA ───────────────────────────────────────── -->
<section id="mengapa">
  <div class="wrap">
    <div class="eyebrow">Mengapa Ini Penting</div>
    <h2>Untuk Yayasan: sudut pandang dari atas</h2>
    <p class="lead narrow">
      Sebagai yayasan induk, YPKBI perlu melihat seluruh ekosistem sekolah dalam perspektif
      yang luas — tanpa harus hadir di setiap ruang kelas.
    </p>
    <div class="ben-grid">
      <div class="ben">
        <div class="ben-n">1</div>
        <div>
          <h4>Tidak bisa hadir di setiap kelas</h4>
          <p>Yayasan tetap perlu mengetahui apakah standar mutu terjaga merata di semua sekolah.</p>
        </div>
      </div>
      <div class="ben">
        <div class="ben-n">2</div>
        <div>
          <h4>Perbandingan yang adil butuh data seragam</h4>
          <p>Tanpa standar dan instrumen yang sama, satu sekolah dan lainnya tidak bisa dibandingkan setara.</p>
        </div>
      </div>
      <div class="ben">
        <div class="ben-n">3</div>
        <div>
          <h4>Keputusan strategis berbasis bukti</h4>
          <p>Data objektif lintas sekolah menjadi dasar kebijakan dan alokasi sumber daya.</p>
        </div>
      </div>
      <div class="ben">
        <div class="ben-n">4</div>
        <div>
          <h4>Helicopter view atas ekosistem</h4>
          <p>Satu pandangan menyeluruh terhadap seluruh sekolah di lingkungan AGKB.</p>
        </div>
      </div>
    </div>

    <div style="margin-top:64px">
      <h2>Untuk Guru: apa untungnya bagi saya</h2>
      <p class="lead narrow">
        Monev-360 dirancang bukan hanya untuk kebutuhan yayasan atau sekolah,
        tetapi juga untuk pertumbuhan Anda secara pribadi.
      </p>
      <div class="ben-grid">
        <div class="ben">
          <div class="ben-n">1</div>
          <div>
            <h4>Penilaian yang adil, bukan opini satu orang</h4>
            <p>Skor Anda berasal dari banyak sudut pandang — rekan sejawat, siswa, atasan — bukan hanya dari satu penilai.</p>
          </div>
        </div>
        <div class="ben">
          <div class="ben-n">2</div>
          <div>
            <h4>Baseline pengembangan diri</h4>
            <p>Anda bisa melihat sendiri progres dari semester ke semester, bukan dinilai sekali lalu selesai.</p>
          </div>
        </div>
        <div class="ben">
          <div class="ben-n">3</div>
          <div>
            <h4>Tolok ukur lintas sekolah</h4>
            <p>Standar yang sama dipakai di semua sekolah, sehingga capaian Anda punya makna yang lebih luas.</p>
          </div>
        </div>
        <div class="ben">
          <div class="ben-n">4</div>
          <div>
            <h4>Dasar nyata untuk rencana pengembangan</h4>
            <p>Bukan asumsi atau kesan sesaat, tapi data konkret tentang kekuatan dan area yang perlu ditumbuhkan.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── YANG DIUKUR ───────────────────────────────────── -->
<section id="diukur" style="background:var(--clarity)">
  <div class="wrap">
    <div class="eyebrow dark">Bagian 1</div>
    <h2>Apa yang diukur</h2>
    <p class="lead narrow">
      Pengukuran berpijak pada sepuluh <em>trait</em> profil lulusan, yang kemudian diturunkan
      menjadi domain dan standar penilaian yang konkret.
    </p>

    <div class="trait-row">
      <span class="trait">Highly Literate</span>
      <span class="trait">Open Minded</span>
      <span class="trait">Critical Thinker</span>
      <span class="trait">Communicative</span>
      <span class="trait">Integrity</span>
      <span class="trait">Collaborative</span>
      <span class="trait">Adaptable</span>
      <span class="trait">Balanced</span>
      <span class="trait">Innovative</span>
      <span class="trait">Leadership</span>
    </div>

    <div class="dom-grid">
      <div class="dom-col">
        <div class="dom-head">
          <h4>Pimpinan Sekolah</h4>
          <span>4 domain · 19 standar penilaian</span>
        </div>
        <div class="dom-body">
          <div class="dom-item"><b>IB Philosophy &amp; Vision</b><span class="dom-n">3 standar</span></div>
          <div class="dom-item"><b>Organizational &amp; Professional Leadership</b><span class="dom-n">7 standar</span></div>
          <div class="dom-item"><b>Operational &amp; Programme Management</b><span class="dom-n">4 standar</span></div>
          <div class="dom-item"><b>Teacher &amp; Student Support</b><span class="dom-n">5 standar</span></div>
        </div>
      </div>
      <div class="dom-col">
        <div class="dom-head">
          <h4>Guru</h4>
          <span>5 domain · 16 standar penilaian</span>
        </div>
        <div class="dom-body">
          <div class="dom-item"><b>IB Philosophy</b><span class="dom-n">3 standar</span></div>
          <div class="dom-item"><b>Organization &amp; Professional Responsibilities</b><span class="dom-n">3 standar</span></div>
          <div class="dom-item"><b>Curriculum Implementation</b><span class="dom-n">3 standar</span></div>
          <div class="dom-item"><b>Teaching and Learning</b><span class="dom-n">3 standar</span></div>
          <div class="dom-item"><b>Assessment</b><span class="dom-n">4 standar</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── SIAPA MENILAI ─────────────────────────────────── -->
<section id="menilai">
  <div class="wrap">
    <div class="eyebrow">Bagian 2</div>
    <h2>Siapa yang menilai</h2>
    <p class="lead narrow">
      Pimpinan dan Guru tidak dinilai satu arah dari atasan saja, tetapi dari seluruh
      ekosistem sekolah. Setiap kelompok menilai standar yang relevan dengan pengalamannya.
    </p>

    <div class="circle-wrap">
      <svg viewBox="0 0 720 470" role="img" aria-label="Diagram penilaian 360 derajat">
        <defs>
          <radialGradient id="ctr" cx="50%" cy="35%">
            <stop offset="0%" stop-color="#0a0750"/>
            <stop offset="100%" stop-color="#040136"/>
          </radialGradient>
        </defs>
        <g stroke="#cdd0d8" stroke-width="1.5" stroke-dasharray="4 5">
          <line x1="360" y1="235" x2="360" y2="92"/>
          <line x1="360" y1="235" x2="206" y2="150"/>
          <line x1="360" y1="235" x2="514" y2="150"/>
          <line x1="360" y1="235" x2="206" y2="330"/>
          <line x1="360" y1="235" x2="514" y2="330"/>
          <line x1="360" y1="235" x2="360" y2="386"/>
        </g>
        <circle cx="360" cy="235" r="76" fill="url(#ctr)"/>
        <text x="360" y="228" text-anchor="middle" fill="#ff9101" font-size="12" font-weight="700"
              font-family="Host Grotesk, sans-serif" letter-spacing="1">YANG DINILAI</text>
        <text x="360" y="252" text-anchor="middle" fill="#ffffff" font-size="16" font-weight="700"
              font-family="Host Grotesk, sans-serif">Pimpinan &amp; Guru</text>

        <g font-family="Host Grotesk, sans-serif" text-anchor="middle">
          <g><rect x="272" y="40" width="176" height="52" rx="14" fill="#02001f"/>
             <text x="360" y="63" fill="#fff" font-size="14" font-weight="600">Yayasan</text>
             <text x="360" y="80" fill="rgba(255,255,255,.6)" font-size="11">Atasan langsung</text></g>

          <g><rect x="30" y="124" width="176" height="52" rx="14" fill="#030870"/>
             <text x="118" y="147" fill="#fff" font-size="14" font-weight="600">Pimpinan Sekolah</text>
             <text x="118" y="164" fill="rgba(255,255,255,.6)" font-size="11">peer review</text></g>

          <g><rect x="514" y="124" width="176" height="52" rx="14" fill="#2201b2"/>
             <text x="602" y="147" fill="#fff" font-size="14" font-weight="600">Guru</text>
             <text x="602" y="164" fill="rgba(255,255,255,.6)" font-size="11">peer review</text></g>

          <g><rect x="30" y="304" width="176" height="52" rx="14" fill="#a85a01"/>
             <text x="118" y="327" fill="#fff" font-size="14" font-weight="600">Komite Orang Tua</text>
             <text x="118" y="344" fill="rgba(255,255,255,.62)" font-size="11">perspektif keluarga</text></g>

          <g><rect x="514" y="304" width="176" height="52" rx="14" fill="#0f7a3d"/>
             <text x="602" y="327" fill="#fff" font-size="14" font-weight="600">OSIS / Siswa</text>
             <text x="602" y="344" fill="rgba(255,255,255,.62)" font-size="11">perwakilan siswa</text></g>

          <g><rect x="272" y="386" width="176" height="52" rx="14" fill="#0a6e78"/>
             <text x="360" y="409" fill="#fff" font-size="14" font-weight="600">Murid yang Diajar</text>
             <text x="360" y="426" fill="rgba(255,255,255,.62)" font-size="11">pengalaman di kelas</text></g>
        </g>
      </svg>
    </div>

    <div class="note narrow" style="margin-inline:auto">
      Tidak semua kelompok menilai semua standar. Komite Orang Tua tidak menilai domain
      <strong>Operational &amp; Programme Management</strong> karena bersifat teknis-operasional.
      Untuk penilaian Guru, Komite Orang Tua dan OSIS belum memiliki paket kuesioner pada sistem saat ini.
    </div>
  </div>
</section>

<!-- ── DARI STANDAR KE PERTANYAAN ────────────────────── -->
<section style="background:var(--clarity)">
  <div class="wrap">
    <div class="eyebrow dark">Bagian 3</div>
    <h2>Dari standar menjadi pertanyaan</h2>
    <p class="lead narrow">
      Setiap standar diterjemahkan menjadi satu pertanyaan, lalu dinilai dengan rubrik
      empat tingkat yang punya deskriptor jelas — bukan sekadar angka.
    </p>

    <div class="flow">
      <div class="flow-step on">
        <div class="flow-n">01</div>
        <h4>Standar</h4>
        <p>Contoh: <em>Support for Teacher Wellbeing</em> pada domain Teacher &amp; Student Support.</p>
      </div>
      <div class="flow-step on">
        <div class="flow-n">02</div>
        <h4>Pertanyaan Master</h4>
        <p>Ditulis dalam bahasa Indonesia dan Inggris, menjadi acuan dasar semua paket kuesioner.</p>
      </div>
      <div class="flow-step on">
        <div class="flow-n">03</div>
        <h4>Rubrik Empat Tingkat</h4>
        <p>Setiap jawaban dinilai dengan deskriptor yang jelas, sehingga bermakna dan dapat ditindaklanjuti.</p>
      </div>
    </div>

    <div class="rubric">
      <div class="rub rub-1"><div class="rub-n">1</div><div class="rub-id">Tidak Terlihat</div><div class="rub-en">Not Evident</div></div>
      <div class="rub rub-2"><div class="rub-n">2</div><div class="rub-id">Berkembang</div><div class="rub-en">Emerging</div></div>
      <div class="rub rub-3"><div class="rub-n">3</div><div class="rub-id">Cakap</div><div class="rub-en">Proficient</div></div>
      <div class="rub rub-4"><div class="rub-n">4</div><div class="rub-id">Teladan</div><div class="rub-en">Exemplary</div></div>
    </div>

    <div style="margin-top:64px">
      <h2 style="font-size:26px">Pertanyaan yang sama, disesuaikan sudut pandang</h2>
      <p class="lead narrow" style="font-size:16px">
        Standar dasarnya sama, tetapi kalimatnya beradaptasi tergantung siapa yang mengisi.
        Contoh untuk standar <em>Support for Student Agency</em>.
      </p>
      <div class="qcompare">
        <div class="qbox">
          <div class="qbox-label">Versi untuk Rekan Guru</div>
          <blockquote>“Sejauh mana [Nama] memberi ruang bagi siswa untuk mengambil keputusan dan bertanggung jawab atas proses belajarnya sendiri?”</blockquote>
          <div class="hint">Bahasa formal, kosakata profesional pendidikan.</div>
        </div>
        <div class="qbox alt">
          <div class="qbox-label">Versi untuk Siswa</div>
          <blockquote>“Apakah Bapak/Ibu [Nama] memberi kesempatan ke kamu untuk memilih cara belajar atau menyampaikan pendapatmu di kelas?”</blockquote>
          <div class="hint">Bahasa lebih sederhana dan personal, mudah dipahami siswa.</div>
        </div>
      </div>
      <div class="note">
        Contoh di atas bersifat ilustratif untuk menjelaskan mekanisme adaptasi paket —
        bukan kutipan literal dari kuesioner yang sedang berjalan.
      </div>
    </div>
  </div>
</section>

<!-- ── SIKLUS ────────────────────────────────────────── -->
<section class="cycle">
  <div class="wrap">
    <div class="eyebrow dark">Siklus</div>
    <h2>Dua kali setahun, mengikuti kalender akademik</h2>
    <p class="lead narrow">
      Siklus berulang setiap tahun ajaran dengan dua titik evaluasi resmi,
      selaras dengan penutupan semester sekolah.
    </p>

    <div class="cyc-grid">
      <div class="cyc">
        <h4>Semester Ganjil</h4>
        <div class="range">Juli — Desember</div>
        <div class="mark">Evaluasi di akhir Desember</div>
      </div>
      <div class="cyc">
        <h4>Semester Genap</h4>
        <div class="range">Januari — Juni</div>
        <div class="mark">Evaluasi di akhir Juni</div>
      </div>
    </div>

    <div class="flow" style="margin-top:34px">
      <div class="flow-step on">
        <div class="flow-n">01</div>
        <h4>Berjalan</h4>
        <p>Guru dan Pimpinan menjalani semester seperti biasa — mengajar, memimpin, berkembang.</p>
      </div>
      <div class="flow-step on">
        <div class="flow-n">02</div>
        <h4>Mengisi</h4>
        <p>Menjelang akhir semester, seluruh responden mengisi kuesioner 360°.</p>
      </div>
      <div class="flow-step on">
        <div class="flow-n">03</div>
        <h4>Menutup Periode</h4>
        <p>Hasil dirangkum menjadi laporan per individu — bahan refleksi dan rencana pengembangan profesional.</p>
      </div>
    </div>
  </div>
</section>

<!-- ── FEEDBACK ──────────────────────────────────────── -->
<section id="feedback">
  <div class="wrap">
    <div class="eyebrow">Pilar Kedua</div>
    <h2>Umpan balik yang tidak menunggu akhir semester</h2>
    <p class="lead narrow">
      Evaluasi berkala menangkap gambaran besar. Tetapi hal-hal yang terjadi hari ini —
      apresiasi yang layak disampaikan, kendala yang perlu diselesaikan, atau laporan yang
      tidak boleh menunggu — memerlukan kanalnya sendiri.
    </p>

    <div class="dom-grid" style="grid-template-columns:repeat(3,1fr)">
      <div class="dom-col">
        <div class="dom-head" style="background:#0f7a3d">
          <h4>Apresiasi</h4>
          <span>Mengakui kontribusi positif</span>
        </div>
        <div class="dom-body" style="padding-top:18px">
          <p style="font-size:14.5px;color:var(--muted)">
            Diteruskan langsung kepada orang yang diapresiasi. Tidak masuk antrean penanganan,
            karena apresiasi tidak perlu diselesaikan — ia perlu sampai.
          </p>
        </div>
      </div>
      <div class="dom-col">
        <div class="dom-head" style="background:#030870">
          <h4>Kendala &amp; Masukan</h4>
          <span>Menyelesaikan masalah</span>
        </div>
        <div class="dom-body" style="padding-top:18px">
          <p style="font-size:14.5px;color:var(--muted)">
            Bernomor tiket, punya kategori, prioritas, dan unit penanganan. Batas waktu naik
            berjenjang dari sekolah ke pimpinan hingga yayasan bila belum tertangani.
          </p>
        </div>
      </div>
      <div class="dom-col">
        <div class="dom-head" style="background:#b42318">
          <h4>Perlindungan Anak</h4>
          <span>Jalur khusus dan terbatas</span>
        </div>
        <div class="dom-body" style="padding-top:18px">
          <p style="font-size:14.5px;color:var(--muted)">
            Dapat dikirim tanpa menampilkan nama, hanya dapat diakses Yayasan, catatan tidak
            dapat diubah, dan setiap pembukaan tercatat.
          </p>
        </div>
      </div>
    </div>

    <div class="note narrow" style="margin-inline:auto;margin-top:34px;border-left-color:var(--danger)">
      Jalur perlindungan anak bukan kanal darurat. Bila ada anak dalam bahaya saat ini,
      hubungi penanggung jawab perlindungan anak secara langsung atau layanan darurat —
      jangan menunggu sistem.
    </div>
  </div>
</section>

<!-- ── CTA ───────────────────────────────────────────── -->
<section class="cta">
  <div class="wrap">
    <div class="eyebrow">Mulai</div>
    <h2>Dua pintu masuk</h2>
    <p class="lead narrow">
      Platform yang sedang berjalan, dan ruang peragaan berisi data contoh
      untuk Anda jelajahi tanpa khawatir mengubah apa pun.
    </p>
    <div class="cta-grid">
      <div class="cta-card">
        <h3>Platform</h3>
        <p>Untuk pimpinan, guru, siswa, orang tua, dan pengelola yang menjalankan evaluasi serta menangani umpan balik yang sebenarnya.</p>
        <a href="/app" class="btn btn-primary">Masuk ke Platform</a>
      </div>
      <div class="cta-card">
        <h3>Ruang Peragaan</h3>
        <p>Berisi data contoh dengan nama fiktif. Cocok untuk mengenal alur sistem sebelum menggunakannya secara sungguhan.</p>
        <a href="/demo" class="btn btn-ghost">Jelajahi Peragaan</a>
      </div>
    </div>
  </div>
</section>

<!-- ── PENUTUP ───────────────────────────────────────── -->
<section class="closing">
  <div class="wrap narrow">
    <div class="eyebrow dark">Penutup</div>
    <blockquote>
      “First, make it work.<br>You’ll always have time to perfect it.”
    </blockquote>
    <p class="who">
      Monev-360 akan terus beriterasi. Versi hari ini bukan versi terakhir —
      dan memang tidak dimaksudkan demikian.
    </p>
  </div>
</section>

<!-- ── FOOTER ────────────────────────────────────────── -->
<footer>
  <div class="wrap">
    <div class="f-top">
      <div class="f-brand">
        <img src="/assets/img/brand/agkb-lockup-white.svg" alt="AGKB 360°">
        <p>
          Sistem monitoring, evaluasi, dan umpan balik untuk ekosistem sekolah
          Yayasan Pendidikan Kader Bangsa Indonesia.
        </p>
      </div>
      <div class="f-links">
        <div class="f-col">
          <h5>Platform</h5>
          <a href="/app">Masuk</a>
          <a href="/demo">Ruang Peragaan</a>
        </div>
        <div class="f-col">
          <h5>Halaman Ini</h5>
          <a href="#mengapa">Mengapa Penting</a>
          <a href="#diukur">Yang Diukur</a>
          <a href="#menilai">Siapa Menilai</a>
          <a href="#feedback">Feedback</a>
        </div>
        <div class="f-col">
          <h5>Kontak</h5>
          <a href="mailto:edu@kaderbangsa.foundation">edu@kaderbangsa.foundation</a>
          <span>Yayasan Pendidikan<br>Kader Bangsa Indonesia</span>
        </div>
      </div>
    </div>
    <div class="f-bot">
      <span>© <?= $tahun ?> AGKB — Semua hak dilindungi.</span>
      <span>AGKB 360° — Platform Evaluasi Kinerja</span>
    </div>
  </div>
</footer>

<script>
// Dropdown "Masuk Platform" — buka/tutup, tutup saat klik di luar atau Esc.
(function () {
  var dds = document.querySelectorAll('[data-dd]');

  function tutupSemua(kecuali) {
    dds.forEach(function (d) {
      if (d === kecuali) return;
      d.classList.remove('open');
      var b = d.querySelector('[data-dd-toggle]');
      if (b) b.setAttribute('aria-expanded', 'false');
    });
  }

  dds.forEach(function (dd) {
    var btn = dd.querySelector('[data-dd-toggle]');
    if (!btn) return;
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var buka = !dd.classList.contains('open');
      tutupSemua(dd);
      dd.classList.toggle('open', buka);
      btn.setAttribute('aria-expanded', buka ? 'true' : 'false');
    });
  });

  document.addEventListener('click', function () { tutupSemua(null); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') tutupSemua(null);
  });
})();
</script>

</body>
</html>
