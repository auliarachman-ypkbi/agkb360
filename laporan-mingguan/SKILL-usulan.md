# Laporan Kanal Feedback — AGKB 360°

> Usulan isi skill `laporan-mingguan-agkb`. Baca dulu; kalau sudah pas,
> minta saya menyimpannya. Berkas ini sendiri tidak berpengaruh apa-apa
> sampai disimpan sebagai skill.

Deck 7 slide dari data tiket. Susunannya tetap supaya antar-periode
mudah dibandingkan: judul, peta tema, empat tema, yang berjalan baik.

## Alur

```
AGKB360 ──API──▶ Apps Script (Minggu 20.00) ──▶ Data/tiket-semua.json
                                                     │
                                           Drive sync ▼
                                    Claude baca ── olah ──▶ Deck/*.pptx
```

Apps Script menarik **seluruh riwayat** tiap pekan, bukan sepekan
terakhir. Deck rentang mana pun bisa dibuat dari satu berkas itu,
termasuk perbandingan antar-bulan, tanpa menarik ulang.

## Letak berkas

Shared drive **Feedback Weekly Update - KTB**:

| Folder | Isi | Boleh saya tulis? |
|---|---|---|
| `Data/tiket-semua.json` | Seluruh tiket, isi laporan lengkap | Tidak |
| `Data/rentang.json` | `dari`, `sampai`, `jumlah`, `diunggah` | Tidak |
| `Data/arsip/` | Potret bertanggal | Tidak |
| `Deck/` | **Hanya berkas .pptx** | Ya |

`Data/` milik Apps Script sepenuhnya. `Deck/` hanya untuk deck jadi —
berkas kerja (`data.json`, `naskah.json`, `tema.png`) tinggal di folder
sementara, jangan pernah disalin ke Drive.

Alat: `laporan-mingguan/` di repo `ktb-evaluation`
(`analisis.py`, `buat_deck.js`, `jalankan.sh`, `naskah-contoh.json`).

Berkas di shared drive sering berstatus cloud-only. Bila `bash` gagal
membacanya, pakai `Read` atau `Grep` lebih dulu untuk memicu unduhan,
baru olah dengan `bash`.

## Langkah

**1. Periksa kesegaran data.** Baca `Data/rentang.json`. Bila
`diunggah` lebih tua dari 8 hari, sampaikan itu sebelum lanjut —
kemungkinan pemicu Apps Script berhenti. Jangan diam-diam membuat
deck dari data basi.

**2. Tentukan rentang.** Ikuti permintaan. Bila tidak disebut, pakai
tujuh hari terakhir yang ada datanya.

**3. Jalankan analisis:**

```sh
python3 analisis.py "<drive>/Data/tiket-semua.json" /tmp/laporan \
  --dari=YYYY-MM-DD --sampai=YYYY-MM-DD
```

**4. Baca sendiri isi laporannya.** Bagian ini tidak bisa diserahkan
ke skrip. Dari `tiket-semua.json`, baca kolom `isi`. Cari:

- Empat tema dengan hitungan tertinggi di `data.json`
- Tiga kutipan verbatim per tema — utamakan yang menyebut waktu,
  tempat, atau angka konkret
- Laporan yang menagih perbaikan yang belum terlihat hasilnya;
  simpulannya diberi `"nada": "merah"`
- Laporan apresiasi, terutama yang menyebut masukan lama yang
  akhirnya dikerjakan

**5. Tulis `/tmp/laporan/naskah.json`** mengikuti `naskah-contoh.json`.

Sumber kutipan: `NOMOR-TIKET · Kategori · asal · tanggal`.
Laporan berulang: `Dilaporkan 4 kali · Kategori · tanggal`.

**6. Rakit:**

```sh
./jalankan.sh "<drive>/Data/tiket-semua.json" /tmp/laporan \
  "/tmp/laporan/Laporan-Kanal-Feedback-<rentang>.pptx" --dari=.. --sampai=..
```

**7. Periksa hasilnya sebelum diserahkan.** Ubah ke PDF, render, lihat
sendiri. Teks yang terlalu panjang bisa menabrak footer. Perbaiki
dengan memendekkan kalimat, bukan mengubah tata letak.

```sh
soffice --headless --convert-to pdf <keluar.pptx>
pdftoppm -png -r 62 <keluar.pdf> hal
```

**8. Salin `.pptx` saja ke `Deck/`.**

**9. Ringkas di percakapan:** jumlah tiket, empat tema teratas dengan
angkanya, perubahan dibanding periode sebelumnya, dan tiket mana yang
paling perlu diperhatikan.

## Rupa deck

- **Font Lato di seluruh deck.** Sudah tersetel di `buat_deck.js`.
- **Jangan mengecilkan ukuran huruf** untuk memuat lebih banyak teks.
  Kalau tidak muat, pendekkan kalimatnya. Deck ini dibaca di ruang
  rapat, sering dari layar yang jauh.
- Tetap 7 slide. Jangan menambah atau mengurangi.

## Bahasa

Formal dan lugas. Bukan berbunga-bunga, bukan dramatis. Ini laporan
kerja, bukan esai.

Yang dihindari — perumpamaan, personifikasi, kalimat yang mengajak
merenung:

- ✗ "Diamnya sebuah kanal mengubah masukan menjadi keluhan, dan
  keluhan menjadi ketidakpercayaan."
- ✓ "Laporan 12 Agustus menjelaskan masalahnya. Laporan 17 Agustus
  mempersoalkan perbaikan yang sudah dijanjikan namun belum terlihat
  hasilnya."

- ✗ "Suara mereka tidak akan pernah terdengar."
- ✓ "Tanpa jalur itu, laporan mereka tidak masuk ke catatan sekolah."

Kutipan pelapor tetap apa adanya — jangan dihaluskan atau dipertajam.
Yang perlu ditahan adalah bahasa saya, bukan bahasa mereka.

## Nomor tiket harus dicek

Jangan menyusun nomor tiket dari ingatan. Cocokkan subjek dengan kolom
`nomor` di `tiket-semua.json` sebelum menulisnya. Nomor yang salah
membuat orang membuka tiket yang keliru.

## Kerahasiaan

- Identitas pelapor anonim tidak dibuka. Data dari API sudah
  menyamarkannya; jangan mencoba memulihkannya dari isi laporan.
- Nama orang yang diadukan **tidak** dicantumkan di slide. Sebut
  perannya saja ("seorang mentor") dan nomor tiketnya. Aduan itu belum
  terverifikasi sedangkan deck beredar luas.
- Bila ada laporan menyangkut kesejahteraan seseorang — pelapor
  menyatakan dirinya sakit, kelelahan, atau tertekan — sebutkan di
  paling atas ringkasan percakapan, lengkap dengan nomor tiket dan
  statusnya. Jangan hanya menjadikannya kutipan di slide.
- `Deck/` bisa dibuka semua anggota shared drive. Ingat itu saat
  memilih kutipan dari jalur Kanal Yayasan.

## Permintaan di luar deck rutin

Karena `tiket-semua.json` berisi seluruh riwayat mentah, pertanyaan
dadakan bisa dijawab tanpa menarik ulang — misalnya "bandingkan
Agustus dengan September", "khusus keluhan asrama", atau "tren wifi
sejak Juni".

Jawab di percakapan bila cukup. Buat deck hanya bila diminta, dan
tetap pakai susunan 7 slide kecuali diminta lain.
