# Laporan Kanal Feedback AGKB 360° — Konteks Lengkap

> Berkas serah-terima. Dibuat 19 Agustus 2026, diperbarui 19 Agustus 2026 (§14 ditambahkan).
> Bacaan pertama untuk chat baru yang menangani laporan feedback.
> Sambungkan dua folder di bawah, baca berkas ini, lalu siap bekerja.

---

## 1. Apa yang dikerjakan

Setiap pekan, tiket dari kanal feedback AGKB 360° ditarik otomatis ke
Google Drive. Dari data itu dibuat deck 7 slide berisi tema keluhan,
kutipan verbatim, dan laporan apresiasi — untuk dibaca pengurus
yayasan dan sekolah.

Susunan deck sengaja tetap agar antar-periode bisa dibandingkan.

---

## 2. Alur

```
┌─────────────┐
│  AGKB 360°  │  agkb360.app — aplikasi tiket
└──────┬──────┘
       │ GET /app/api/tiket.php   (token di header)
       ▼
┌─────────────────────┐
│  Google Apps Script │  pemicu: Minggu 20.00 WIB
│  "AGKB360 —         │  fungsi: tarikSemua
│   Penarik Tiket"    │
└──────┬──────────────┘
       │ tulis
       ▼
┌──────────────────────────────────────┐
│  Shared Drive                        │
│  Feedback Weekly Update - KTB        │
│  ├── Data/   ← Apps Script           │
│  └── Deck/   ← Claude                │
└──────┬───────────────────────────────┘
       │ Google Drive Desktop (sync)
       ▼
┌─────────────┐
│  Mac + Claude │ baca Data/, olah, tulis Deck/
└─────────────┘
```

Tidak ada langkah manual di Terminal. Apps Script berjalan di server
Google — Mac tidak perlu menyala saat penarikan.

---

## 3. Yang perlu disambungkan di chat baru

| Folder | Jalur |
|---|---|
| Alat | `/Users/auliaalfahmy/Documents/ktb-evaluation` |
| Data & Deck | `/Users/auliaalfahmy/Library/CloudStorage/GoogleDrive-aulia.rachman@kaderbangsa.foundation/Shared drives/Feedback Weekly Update - KTB` |

Shared drive berjalan dalam mode *stream* (tidak bisa *mirror*), jadi
berkasnya sering cloud-only. Bila `bash` gagal membacanya dengan galat
`Resource deadlock avoided`, pakai `Read` atau `Grep` dulu untuk
memicu unduhan, baru olah dengan `bash`.

---

## 4. Isi shared drive

```
Feedback Weekly Update - KTB/
├── Data/                        ← milik Apps Script, jangan ditulis
│   ├── tiket-semua.json         ← sumber: SELURUH riwayat, isi lengkap
│   ├── rentang.json             ← dari, sampai, jumlah, diunggah
│   └── arsip/
│       └── tiket-YYYY-MM-DD.json    potret mingguan, jangan dihapus
├── Deck/                        ← HANYA berkas .pptx
└── Arsip/                       ← peninggalan; ada data.json/naskah.json/
                                    tema.png/CSV lama di "Arsip/data 2/".
                                    Ini persis jenis berkas kerja yang
                                    seharusnya tidak pernah masuk Drive
                                    (lihat §12 lama). Belum dibersihkan
                                    per 19 Agustus — tanyakan ke pengguna
                                    sebelum menghapus.
```

**`Deck/` hanya untuk deck jadi.** Berkas kerja (`data.json`,
`naskah.json`, `tema.png`) tinggal di folder sementara. Jangan pernah
disalin ke Drive — ini pernah terjadi dan diminta dibersihkan.

`tiket-semua.json` berisi seluruh riwayat sejak Juni, bukan sepekan
terakhir. Jadi perbandingan antar-bulan atau pertanyaan dadakan bisa
dijawab tanpa menarik ulang. **Tapi lihat §14** — riwayat itu sekarang
difilter permanen oleh `analisis.py`, bukan mentah lagi.

---

## 5. Alat di repo

`ktb-evaluation/laporan-mingguan/`

| Berkas | Guna |
|---|---|
| `analisis.py` | Baca JSON, hitung tema, hasilkan `data.json` + `tema.png`. Sejak 19 Agustus 2026 juga mengecualikan tiket era pra-eskalasi — lihat §14. |
| `buat_deck.js` | Rakit 7 slide dari `data.json` + `naskah.json` |
| `jalankan.sh` | Jalankan keduanya; memasang pptxgenjs bila belum ada |
| `naskah-contoh.json` | Templat naskah |
| `apps-script/TarikTiket.gs` | Salinan kode Apps Script |
| `SKILL-usulan.md` | Usulan isi skill, belum disimpan |
| `KONTEKS.md` | Berkas ini |

`tarik-tiket.sh` dan `vps/` adalah peninggalan rancangan lama (tarik
lewat SSH). Sudah tidak dipakai sejak Apps Script berjalan.

---

## 6. Cara membuat deck

**1. Periksa kesegaran data.** Baca `Data/rentang.json`. Bila
`diunggah` lebih tua dari 8 hari, sampaikan ke pengguna sebelum
lanjut — kemungkinan pemicu Apps Script berhenti. Jangan diam-diam
membuat deck dari data basi.

**2. Analisis:**

```sh
cd <repo>/laporan-mingguan
python3 analisis.py "<drive>/Data/tiket-semua.json" /tmp/laporan \
  --dari=YYYY-MM-DD --sampai=YYYY-MM-DD
```

**3. Baca sendiri isi laporannya.** Bagian ini tidak bisa diserahkan
ke skrip. Dari `tiket-semua.json`, baca kolom `isi`. Cari:

- Empat tema dengan hitungan tertinggi di `data.json`
- Tiga kutipan verbatim per tema — utamakan yang menyebut waktu,
  tempat, atau angka konkret
- Laporan yang menagih perbaikan yang belum terlihat hasilnya;
  simpulannya diberi `"nada": "merah"`
- Laporan apresiasi, terutama yang menyebut masukan lama yang
  akhirnya dikerjakan

**4. Tulis `/tmp/laporan/naskah.json`** mengikuti `naskah-contoh.json`.

Sumber kutipan: `NOMOR-TIKET · Kategori · asal · tanggal`
Laporan berulang: `Dilaporkan 4 kali · Kategori · tanggal`

**5. Rakit:**

```sh
./jalankan.sh "<drive>/Data/tiket-semua.json" /tmp/laporan \
  "/tmp/laporan/Laporan-Kanal-Feedback-<rentang>.pptx" \
  --dari=.. --sampai=..
```

**6. Periksa sendiri sebelum diserahkan.** Ubah ke PDF, render, lihat.
Teks panjang bisa menabrak footer. Perbaiki dengan memendekkan
kalimat, bukan mengubah tata letak.

```sh
soffice --headless --convert-to pdf <keluar.pptx>
pdftoppm -png -r 62 <keluar.pdf> hal
```

Sekaligus cocokkan tiap nomor tiket yang dikutip di slide dengan
`tiket-semua.json` (kategori, tanggal, isi) — jangan percaya dari
ingatan naskah yang ditulis sebelumnya.

**7. Salin `.pptx` saja ke `Deck/`.**

**8. Ringkas di percakapan:** jumlah tiket, empat tema teratas dengan
angkanya, perubahan dibanding periode sebelumnya, dan tiket mana yang
paling perlu diperhatikan.

---

## 7. Ketentuan deck

**Rupa**

- Font **Lato** di seluruh deck, sudah tersetel di `buat_deck.js`.
  Harus terpasang di mesin yang membuka, jika tidak PowerPoint
  menggantinya diam-diam.
- **Jangan mengecilkan ukuran huruf** untuk memuat lebih banyak teks.
  Kalau tidak muat, pendekkan kalimatnya. Deck dibaca di ruang rapat.
- Tetap 7 slide: judul, peta tema, empat tema, yang berjalan baik.

**Bahasa**

Formal dan lugas. Bukan berbunga-bunga, bukan dramatis. Ini laporan
kerja, bukan esai. Hindari perumpamaan, personifikasi, dan kalimat
yang mengajak merenung.

- ✗ "Diamnya sebuah kanal mengubah masukan menjadi keluhan, dan
  keluhan menjadi ketidakpercayaan."
- ✓ "Laporan 12 Agustus menjelaskan masalahnya. Laporan 17 Agustus
  mempersoalkan perbaikan yang sudah dijanjikan namun belum terlihat
  hasilnya."

Kutipan pelapor tetap apa adanya — jangan dihaluskan atau dipertajam.
Yang perlu ditahan adalah bahasa Claude, bukan bahasa pelapor.

**Nomor tiket harus dicek.** Cocokkan subjek dengan kolom `nomor` di
`tiket-semua.json` sebelum menulisnya. Jangan dari ingatan — nomor
yang salah membuat orang membuka tiket yang keliru.

---

## 8. Kerahasiaan

- **Identitas pelapor anonim tidak dibuka.** API sudah menyamarkannya
  dan tidak menyediakan parameter untuk membukanya. Jangan mencoba
  memulihkannya dari isi laporan.
- **Nama orang yang diadukan tidak dicantumkan di slide.** Sebut
  perannya saja ("seorang mentor") dan nomor tiketnya. Aduan belum
  terverifikasi sedangkan deck beredar luas. Perhatikan baik-baik:
  kolom `isi` tiket kadang memuat nama sungguhan pihak yang diadukan
  (mis. KY-2026-0011) — itu tidak boleh ikut ke kutipan di slide.
- **Laporan menyangkut kesejahteraan seseorang** — pelapor menyatakan
  dirinya sakit, kelelahan, atau tertekan — sebutkan di paling atas
  ringkasan percakapan, lengkap dengan nomor tiket dan statusnya.
  Jangan hanya menjadikannya kutipan di slide.
- `Deck/` bisa dibuka semua anggota shared drive. Ingat itu saat
  memilih kutipan dari jalur Kanal Yayasan.

---

## 9. API

```
GET https://agkb360.app/app/api/tiket.php?dari=YYYY-MM-DD&sampai=YYYY-MM-DD
Header: X-API-Token: <token>
```

- Kode: `public_html/app/api/tiket.php` (juga disalin ke `demo/`)
- Token: konstanta `API_TOKEN_TIKET` di `public_html/app/config/config.php`
  **di VPS**. Berkas itu gitignored — tidak ada salinannya di repo.
  Kalau VPS dibangun ulang, token harus dipasang lagi dan disamakan
  dengan Script Properties di Apps Script.
- Rentang tanggal **wajib**, maksimal 400 hari. Disengaja: token yang
  bocor tidak bisa menarik seluruh basis data sekaligus.
- Token dikirim lewat **header**, bukan query string — query string
  ikut tercatat di access log nginx.
- Opsional: `&jalur=inquiry|apresiasi|safeguarding`

---

## 10. Apps Script

- Proyek: **AGKB360 — Penarik Tiket** di script.google.com,
  akun `aulia.rachman@kaderbangsa.foundation`
- Kode: salinannya ada di `apps-script/TarikTiket.gs`
- Pemicu: **`tarikSemua`**, Minggu ±20.00 WIB
- Script Property yang wajib: **`API_TOKEN`**
  (ID folder sudah tertanam di kode, bukan rahasia)

Fungsi yang bisa dijalankan manual dari editor:

| Fungsi | Guna |
|---|---|
| `periksa` | Cek token, kedua folder, dan API tanpa menulis apa pun |
| `uji` | Tarik data sungguhan sekali jalan |
| `tarikSemua` | Yang dijalankan pemicu |
| `pasangPemicu` | Pasang ulang pemicu mingguan |
| `daftarPemicu` | Lihat pemicu yang terpasang |

ID folder:
`Data` = `1FmE0xexJMpEWLcZp6IddkdH1q0orTWyK`
`Deck` = `1bGpZhGXJn57BCFLTdmKZhYOh7vmII1MX`

---

## 11. Kenapa dirancang begini

Beberapa hal terlihat berputar; ini alasannya, supaya tidak
"disederhanakan" lalu rusak.

**Kenapa Apps Script, bukan Claude yang memanggil API?**
Sandbox Claude tidak punya akses jaringan sama sekali. Pengambil web
Claude hanya merender HTML — endpoint JSON pulang kosong. Sudah diuji.

**Kenapa bukan service account + rclone di VPS?**
Kebijakan organisasi Google memblokir pembuatan kunci service account
(`iam.disableServiceAccountKeyCreation`). Apps Script tidak perlu
kunci sama sekali karena berjalan atas nama akun yang sudah punya
akses ke shared drive.

**Kenapa menarik seluruh riwayat tiap pekan, bukan sepekan terakhir?**
Supaya pertanyaan seperti "bandingkan dengan bulan lalu" bisa dijawab
tanpa menarik ulang. Berkasnya kecil — 52 tiket ≈ 88 KB.

**Kenapa naskah terpisah dari data?**
Angka bisa dihitung mesin. Memilih tiga kutipan yang mewakili sebuah
tema, dan menangkap kapan nada laporan berubah, perlu membaca isinya.

---

## 12. Yang belum beres

- **Skill `laporan-mingguan-agkb` masih versi lama.** Isinya menyebut
  CSV dan folder yang sudah tidak dipakai. Usulan versi baru ada di
  `SKILL-usulan.md`, menunggu ditinjau pengguna.

- **Tugas terjadwal `laporan-mingguan-agkb`** (Minggu 21.10) masih
  memakai petunjuk lama: mencari CSV, folder `data/` huruf kecil,
  dan `tarik-tiket.sh`. Perlu diperbarui atau dihapus. Kalau tidak,
  ia akan gagal atau menghasilkan deck yang keliru.

- **Lato belum tentu terpasang di Mac pengguna.** Perlu dicek di
  Font Book.

- **Folder `Arsip/data 2/` di shared drive** berisi berkas kerja lama
  (`data.json`, `naskah.json`, `tema.png`, dua CSV) — jenis berkas
  yang seharusnya tidak pernah masuk Drive. Belum dibersihkan per 19
  Agustus 2026; tanyakan ke pengguna dulu sebelum menghapus.

---

## 13. Catatan isi laporan per 19 Agustus 2026

Deck periode 10–19 Agustus 2026 sudah jadi dan sudah diverifikasi
(nomor tiket dicocokkan satu-satu ke `tiket-semua.json`, angka tema
dicek ulang dengan menjalankan `analisis.py`, font Lato dicek di XML
`.pptx`). Ada di `Deck/Laporan-Kanal-Feedback-10-19-Agustus-2026.pptx`.

44 tiket dalam periode ini (setelah §14 diterapkan; sebelumnya 52
untuk seluruh riwayat, sekarang 44 karena era pra-eskalasi
dikecualikan). Yang perlu diketahui saat membaca data:

- **KY-2026-0010** — siswa menulis dirinya sakit, kelelahan, dan
  tugasnya menumpuk. Berprioritas P1, masih berstatus Baru tanpa
  penanggung jawab sejak 15 Agustus. Batas waktunya (18 Agustus)
  sudah lewat per 19 Agustus. Ini bukan urusan deck — sampaikan
  langsung ke pengguna di setiap sesi, bukan hanya jadi kutipan slide.
- **Seluruh 10 tiket Kanal Yayasan** berprioritas P1, tidak satu pun
  punya penanggung jawab, dan belum ada yang dibalas. Penyebabnya
  keanggotaan unit "Kanal Yayasan" belum diisi di aplikasi.
- Jalur Kendala/Masukan sehat: median balasan pertama 5,7 jam.
- Apresiasi naik cepat — 9 pada periode 10–19 Agustus, tiga di
  antaranya masuk pada 19 Agustus saja.

---

## 14. Tiket era pra-eskalasi (dikecualikan permanen sejak 19 Agustus 2026)

`AGKB-2026-0001` s.d. `AGKB-2026-0008` (19 Juni – 23 Juli 2026, 8
tiket, termasuk "HARGAI HARI LIBUR GURU...") mendahului sistem
eskalasi. Polanya beda total dari tiket sesudahnya:

- Prioritas datar — selalu P3 (keluhan) atau P4 (apresiasi), tidak
  pernah P1/P2.
- Satu penanggung jawab default (Qaedi Aqsa) atau kosong — bukan
  dibagi per unit.
- Status tidak pernah bergerak dari "Baru" — tidak ada yang pernah
  "Ditindaklanjuti" atau "Ditutup".
- Tidak ada jalur Kanal Yayasan sama sekali.

Ada jeda 19 hari kosong (23 Juli → 11 Agustus) sebelum pola berubah
total — kemungkinan itu waktu sistem eskalasi dipasang.

**Keputusan bersama pengguna, 19 Agustus 2026:** kedelapan tiket ini
dianggap selesai dan dikecualikan permanen dari laporan mingguan —
bukan cuma dari deck periode ini. Diterapkan sebagai filter tanggal
`AWAL_SISTEM_ESKALASI = '2026-08-11'` di awal `analisis.py`, sebelum
`--dari`/`--sampai` diterapkan. Berlaku untuk setiap pemanggilan
skrip, termasuk tanpa argumen rentang sama sekali.

Ini **hanya mengubah laporan**, bukan status sungguhan di
agkb360.app — Claude tidak punya akses untuk mengubah itu (lihat
§11). Kalau kedelapan tiket itu perlu ditutup betulan di aplikasi,
itu langkah manual terpisah oleh pengguna.

Kalau nanti ada data lama lain yang perlu perlakuan sama, ubah
konstanta `AWAL_SISTEM_ESKALASI` di `analisis.py`, jangan tambahkan
pengecualian tempel-tempel di tempat lain.

---

## 15. Akses Kanal Yayasan (ditata 24 Agustus 2026)

Ditemukan bahwa jalur `safeguarding` terbuka lewat **role**, bukan
lewat keanggotaan unit. `fbAllowedTracks()` memberikannya kepada
`superadmin`, `foundation`, dan `pemantau`, sedangkan
`fbCanSeeSafeguarding()` hanya mengakui anggota unit Kanal Yayasan —
dua sumber kebenaran yang tidak sepakat, dan yang longgar yang dipakai
inbox serta dasbor.

Akibatnya di produksi: satu pengurus yayasan membaca sepuluh laporan
perlindungan anak tanpa pernah dimasukkan ke unitnya, dan role
`pemantau` — dipegang PT Aksi Karya Bersama, pihak ketiga di luar
yayasan — membaca seluruhnya karena warisan definisi peran, bukan
karena keputusan siapa pun.

**Aturan sekarang, satu sumber saja:**

| Dasar | Cakupan |
|---|---|
| Anggota unit Kanal Yayasan | seluruh tiket KY |
| `superadmin` | seluruh tiket KY, demi pemeliharaan sistem |
| Pemberian per tiket | satu tiket tertentu |

Role `foundation` dan `pemantau` tidak lagi lolos otomatis. Menambah
atau mencabut akses dilakukan lewat keanggotaan unit di Admin CMS,
tanpa menyentuh kode.

Pemberian per tiket ada karena keanggotaan unit terlalu kasar untuk
keputusan seperti "Dewi Amri boleh membuka KY-2026-0010" yang diambil
di rapat internal. Dicatat sebagai baris `feedback_watchers` ber-
`added_by`, dan hanya sah bila pemberinya sendiri boleh membaca KY.

`fbResolveAssignee()` juga disaring: tiket KY tidak akan ditugaskan
kepada orang yang tidak bisa membukanya. Kalau tidak ada yang layak,
tiket dibiarkan tanpa penanggung jawab — tetap masuk antrean unit dan
terhitung belum diambil. Tiket yang ditugaskan kepada orang yang cuma
mendapat 403 lebih buruk daripada tiket kosong: tampak tertangani,
dan tidak berbunyi apa-apa.

**Cara memeriksanya:**

```sh
ssh root@145.79.10.123 'docker exec -i ktb_php php \
  /var/www/html/app/tools/cek-akses-ky.php'
```

Skrip itu memanggil `fbAllowedTracks()` dan `fbCanView()` yang
sesungguhnya dipakai halaman — bukan membaca ulang niat di komentar.
Tiga vonisnya: pembaca tanpa dasar, anggota unit yang justru tidak
melihat, dan tiket berpenanggung jawab yang pemegangnya tidak bisa
membuka. Jalankan setiap kali aturan akses disentuh atau keanggotaan
unit diubah.

### Yang tidak ikut tersaring

Notifikasi surel **tidak** melewati `fbCanView()`. Penerimanya
dibangun dari `feedback_escalation_levels` dan `fbTembusanTetap()`.
Per 24 Agustus 2026 rute jalur safeguarding berisi:

- `id=4` — **bukan penerima**, hanya penamaan level "Yayasan (YPKBI)"
  dari migrasi 009. Tampil di Admin CMS pada daftar rute. Jangan
  dihapus meski tampak kosong.
- `id=5` — Qaedi Aqsa, tertaut akun.
- `id=30` — `reybiwrn@gmail.com`, **alamat mentah tanpa `user_id`**.

Baris `id=30` disengaja: Reybi punya dua akun, dan yang dipakai untuk
KY memang yang Gmail. Yang perlu diingat: karena tidak tertaut akun,
**mencabut keanggotaan unit tidak menghentikan surelnya.** Kalau suatu
hari Reybi berhenti menangani KY, baris ini harus dihapus terpisah —
kalau tidak, notifikasi berisi 600 aksara pertama isi laporan tetap
datang ke kotak surat pribadinya.

`fbTembusanTetap()` juga masih menembuskan setiap tiket KY ke alamat
pengembang. Hardcode di `includes/feedback.php`, ditandai dalam
komentar sebagai pengaturan sementara masa pengembangan. Layak dicabut
begitu susunan penerimanya tetap.
