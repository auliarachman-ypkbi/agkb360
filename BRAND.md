# AGKB 360° — Panduan Implementasi Brand

Referensi teknis untuk developer. Sumber kebenaran visual tetap *AGKB 360 Branding Guideline* (Helmi Atallah Priyono Putra, 2026).

---

## Tipografi

**Host Grotesk** — dimuat dari Google Fonts di `includes/layout.php`, `login.php`, dan `auth/set-password.php`.

```html
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Host+Grotesk:ital,wght@0,300..800;1,300..800&display=swap">
```

| Bobot | Penggunaan |
|-------|-----------|
| 300–400 | Body text, teks panjang |
| 500–600 | Subjudul, label, highlight |
| 700–800 | Judul utama, headline, angka statistik |

Fallback: `'Segoe UI', system-ui, -apple-system, sans-serif`.
Di email HTML, Host Grotesk tidak akan termuat — stack fallback sudah dipasang.

---

## Warna Inti

| Nama | Hex | Peran di aplikasi |
|------|-----|-------------------|
| Oblivion | `#040136` | Navbar, sidebar, card header, tombol utama, warna judul |
| Axiom Blue | `#030870` | Hover tombol utama, tombol `.btn-primary`, aksen struktural |
| Galactic Highway | `#2201b2` | Link, focus ring, data/chart, badge informasi |
| Catalyst | `#ff9101` | Aksen utama, CTA, nav aktif, garis aksen, dot |
| Catalyst Ember | `#ee4c01` | Aksen tegas, hover Catalyst, skor "Berkembang" |
| Clarity Gray | `#f3f4f6` | Latar halaman |

> **Catatan kontras.** Catalyst `#ff9101` **tidak boleh** dipakai sebagai warna teks di atas putih (rasio 2,1:1). Gunakan sebagai *fill* dengan teks Oblivion di atasnya, atau pakai `--agkb-ember-700` `#b83a01` untuk teks oranye.

## Turunan

```
--agkb-oblivion-900  #02001f    --agkb-catalyst-600  #d97701
--agkb-oblivion-700  #0a0750    --agkb-catalyst-300  #ffc36b
--agkb-axiom-600     #0a1490    --agkb-catalyst-100  #fff1dc
--agkb-axiom-500     #1b28b0    --agkb-catalyst-050  #fff8ef
--agkb-galactic-400  #4a2ee0    --agkb-ember-800     #a83401
--agkb-galactic-200  #b9aef2    --agkb-ember-700     #b83a01
--agkb-galactic-050  #eeebfc    --agkb-ember-100     #ffe6db
```

## Netral

```
--agkb-surface  #ffffff   --agkb-ink    #040136   (judul)
--agkb-bg       #f3f4f6   --agkb-body   #2f2d4d   (teks isi)
--agkb-line     #e3e5ea   --agkb-muted  #6b6a83   (keterangan)
--agkb-line-strong #cdd0d8 --agkb-faint #6f6e85   (hint, lolos AA)
                          --agkb-faint-deco #9b9ab0 (ikon/garis dekoratif saja)
```

## Semantik Skor

| Level | Warna | Latar |
|-------|-------|-------|
| Tidak Terlihat | `#b42318` | `#fdeceb` |
| Berkembang | `#ee4c01` | `#ffeee6` |
| Cakap | `#2201b2` | `#eeebfc` |
| Teladan | `#027a48` | `#e7f6ef` |

Definisi runtime ada di `config/config.php` → `SCORE_LEVELS` dan `getScoreLevel()`.
**File ini gitignored** — perubahan harus diterapkan manual di VPS.

## Palet Kategori Responden

Delapan warna, semuanya lolos WCAG AA dengan teks putih dan cukup berjarak satu sama lain.

| Kategori | Hex |
|----------|-----|
| Yayasan (atasan) | `#02001f` |
| Pimpinan (leader) | `#030870` |
| Rekan Sejawat (peer) | `#2201b2` |
| Guru | `#5b3fd6` |
| Komite Ortu | `#a85a01` |
| OSIS / Siswa | `#0f7a3d` |
| Murid yang Diajar (student_class) | `#0a6e78` |
| Self | `#6b6a83` |

Dipakai di `admin/questions_packages.php` (`$respColors`) dan `dashboard/index.php` (`COLORS`). **Jaga keduanya tetap sinkron.**

---

## Aset Logo

`public_html/app/assets/img/brand/` — diekstrak langsung dari vektor PDF guideline.

| File | Kegunaan |
|------|----------|
| `agkb-mark.svg` | Mark oranye — navbar, footer |
| `agkb-mark-white.svg` / `agkb-mark-navy.svg` | Varian mono |
| `agkb-lockup.svg` | Lockup horizontal, teks Oblivion — latar terang |
| `agkb-lockup-white.svg` | Lockup horizontal, teks putih — latar gelap (login, set-password) |
| `agkb-lockup.png` / `agkb-lockup-white.png` | Fallback raster 1200px |
| `agkb-mark.png` | Header email (512px) |
| `favicon.svg` | Mark oranye di kotak Oblivion, radius 56 |
| `favicon-32.png` / `-180.png` / `-512.png` | Favicon, apple-touch-icon, PWA |

**Clear space:** minimal setinggi satu modul mark di semua sisi.
**Ukuran minimum:** mark 24px, lockup 120px lebar.
**Jangan:** merotasi, memiringkan, mengubah proporsi, atau memberi outline pada mark.

---

## Kelas Utilitas

```
Teks     .text-ink .text-catalyst .text-galactic .text-axiom .text-ember
Latar    .bg-oblivion .bg-axiom .bg-catalyst .bg-galactic .bg-clarity
Tombol   .btn-oblivion .btn-axiom .btn-catalyst .btn-galactic .btn-ember
         .btn-outline-navy .btn-outline-catalyst
Badge    .badge-oblivion .badge-catalyst .badge-galactic .badge-axiom
         .badge-soft-catalyst .badge-soft-galactic
Kartu    .stat-card[.catalyst|.galactic|.axiom|.green|.red]
         .card-header[.catalyst|.axiom|.light]
Banner   .agkb-banner.agkb-banner-tester / .agkb-banner-viewas
```

Alias lama (`--ktb-navy`, `--ktb-gold`, `--ktb-blue`, `.btn-navy`, `.badge-gold`, dst.) tetap ada dan otomatis memetakan ke token baru — kode lama tidak akan pecah.

---

## Aturan Kerja

1. **Jangan tulis hex mentah di file PHP baru.** Pakai variabel CSS atau kelas utilitas.
2. **Cek kontras** sebelum memakai warna untuk teks — minimal 4,5:1 untuk teks normal, 3:1 untuk teks besar/komponen UI.
3. **Warna kategori** harus diambil dari palet kategori, bukan dibuat ad-hoc.
4. **Perubahan `config/config.php` tidak ikut git** — terapkan manual di VPS.
