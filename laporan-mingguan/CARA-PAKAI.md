# Laporan Mingguan Kanal Feedback

Alur tetap tiap pekan: tarik data di Mac, sisanya dikerjakan Claude,
hasilnya mendarat di folder Google Drive dan langsung tersinkron.

```
Mac  ──ssh──▶  VPS: ekspor-tiket.php  ──▶  CSV di folder Drive
                                             │
Claude  ◀── baca CSV ── analisis ── deck ──▶ .pptx di folder Drive
                                             │
                                    Drive sync ──▶ semua orang buka
```

Claude tidak punya akses jaringan keluar — tidak bisa SSH ke VPS
maupun ke internet. Karena itu langkah tarik data harus dijalankan
dari Mac. Setelah CSV mendarat, sisanya otomatis.

---

## Persiapan sekali saja

**1. Folder tujuannya** adalah shared drive `Feedback Weekly Update - KTB`.
Subfolder `data` dibuat otomatis oleh skrip.

**2. Letaknya di disk bukan `~/Google Drive/`.** Google Drive Desktop
menaruh shared drive di:

```
~/Library/CloudStorage/GoogleDrive-<email>/Shared drives/<nama folder>/
```

Skrip mencarinya sendiri, jadi biasanya tidak perlu diatur. Untuk
melihat jalur persisnya:

```sh
ls -d ~/Library/CloudStorage/GoogleDrive-*/Shared*/*/
```

Bila nama foldernya berubah, timpa lewat env:

```sh
export AGKB_FOLDER="Nama Folder Baru"
# atau langsung jalur lengkapnya
export AGKB_TUJUAN="/jalur/lengkap/ke/folder"
```

**3. Shared drive selalu mode Stream, tidak bisa Mirror.** Mirror hanya
tersedia untuk My Drive. Konsekuensinya: berkas baru diunduh saat
dibuka. Untuk berkas seukuran CSV dan pptx ini tidak jadi soal, tapi
beri jeda beberapa detik setelah menarik data sebelum meminta Claude
membacanya.

**4. Sambungkan folder itu ke Claude** lewat tombol folder di Cowork,
supaya Claude bisa membaca CSV dan menulis deck ke sana. Arahkan ke
jalur `~/Library/CloudStorage/...` di atas, bukan ke alias di Finder.

**5. Buat skripnya bisa dijalankan:**

```sh
chmod +x ~/Documents/ktb-evaluation/laporan-mingguan/*.sh
```

---

## Tiap pekan

**Di Terminal Mac:**

```sh
~/Documents/ktb-evaluation/laporan-mingguan/tarik-tiket.sh
```

Tanpa argumen berarti tujuh hari terakhir. Untuk rentang tertentu:

```sh
./tarik-tiket.sh 2026-08-10 2026-08-18
```

CSV tersimpan dua kali: bernama tanggal (arsip) dan sebagai
`tiket-terbaru.csv` (yang dibaca Claude).

**Lalu di Claude, cukup katakan:**

> buat laporan mingguan 10 Agustus sampai 18 Agustus

Claude membaca CSV, menghitung tema, membaca isi laporannya untuk
memilih kutipan dan menulis simpulan, lalu menaruh
`Laporan-Mingguan-<tanggal>.pptx` di folder Drive.

---

## Berkas di folder ini

| Berkas | Guna |
|---|---|
| `tarik-tiket.sh` | Dijalankan di **Mac**. SSH ke VPS, simpan CSV ke folder Drive. |
| `analisis.py` | Menghitung tema, menyatukan kiriman ganda, menulis `data.json` dan `tema.png`. |
| `buat_deck.js` | Merakit 7 slide dari `data.json` + `naskah.json`. |
| `jalankan.sh` | Menjalankan keduanya sekaligus. Dipakai Claude. |
| `naskah-contoh.json` | Templat naskah, terisi contoh pekan 10–18 Agustus. |

## Mengapa naskahnya terpisah

Angka bisa dihitung mesin; kutipan dan simpulan tidak. Memilih tiga
kutipan yang mewakili sebuah tema, dan menyimpulkan apa benang
merahnya, butuh membaca isi laporannya. Itu bagian yang Claude
kerjakan tiap pekan dan hasilnya ditulis ke `naskah.json`.

Susunan slide, warna, dan tata letaknya tetap — jadi deck tiap pekan
seragam dan mudah dibandingkan satu sama lain.

## Kerahasiaan

Ekspor **tidak** membuka identitas pelapor anonim, meski dijalankan
superadmin. Berkas CSV mudah berpindah tangan, apalagi di Drive
bersama. Bila suatu saat identitas benar-benar diperlukan, itu
keputusan tersendiri — bukan bagian dari rutinitas mingguan.

Pertimbangkan juga siapa yang punya akses ke folder Drive-nya.
Laporan Kanal Yayasan berisi hal yang paling sensitif; kalau folder
itu dibagikan luas, sebaiknya deck mingguan tidak mengutip verbatim
dari jalur tersebut.
