# AGKB 360° — Modul Feedback & Ticketing

**Status:** Terimplementasi di localhost — belum di-deploy
**Versi:** 0.2 — 5 Agustus 2026
**Penyusun:** Aulia Rachman Alfahmy

> Bagian bertanda 🔒 memerlukan persetujuan Yayasan, bukan keputusan teknis.

## Cara Menjalankan di Localhost

```bash
cd ~/Documents/ktb-evaluation

# 1. Pastikan nama database lokal benar
docker exec ktb_mysql mysql -u ktb_user -pktb_pass_2024 -e "SHOW DATABASES;"

# 2. Cadangkan dulu.
#    --no-tablespaces WAJIB: user ktb_user tidak punya privilege PROCESS,
#    tanpa flag ini mysqldump gagal dan file backup jadi kosong.
docker exec ktb_mysql mysqldump --no-tablespaces -u ktb_user -pktb_pass_2024 ktb_production \
  > ~/Desktop/backup-lokal-$(date +%Y%m%d-%H%M).sql

# 3. Jalankan migration
docker exec -i ktb_mysql mysql -u ktb_user -pktb_pass_2024 ktb_production \
  < migrations/009_feedback_ticketing.sql

# 4. Pasang mount storage (docker-compose.yml sudah diperbarui)
docker compose up -d
docker exec ktb_php chown -R www-data:www-data /var/www/storage

# 5. Verifikasi
docker exec ktb_mysql mysql -u ktb_user -pktb_pass_2024 ktb_production \
  -e "SELECT COUNT(*) AS kategori FROM feedback_categories;"   # harus 16

# 6. Buka http://localhost/app/admin/feedback_categories.php
#    Isi PIC default tiap kategori + rute eskalasi L1/L2/L3
```

> ⚠️ Perintah di atas menargetkan `ktb_production` **di komputer lokal**, bukan VPS.
> Migration ini melakukan `RENAME TABLE feedback` — aman karena tabelnya kosong,
> tetapi jangan pernah dijalankan lewat `ssh` ke VPS tanpa memeriksa isi tabel dulu.

## Pemisahan Data: Produksi vs Demo

| | `/app` | `/demo` |
|---|---|---|
| Database | `ktb_production` | `ktb_evaluation` |
| Isi feedback | Data asli (termasuk hasil pindahan dari `feedback_legacy`) | Dummy nama fiktif |
| Akun | Pengguna nyata KTB | `@demo.agkb360.app` |

Dummy **tidak boleh** masuk ke `ktb_production`. Karena itu seeder punya opsi
`--db=` agar tidak perlu mengubah `config.php`.

**Data demo** — 19 tiket skenario dengan nama fiktif, masuk ke DB demo:

```bash
# Siapkan tabel di database demo (sekali saja)
docker exec -i ktb_mysql mysql -u ktb_user -pktb_pass_2024 ktb_evaluation \
  < migrations/009_feedback_ticketing.sql

# Isi dummy
docker exec ktb_php php /var/www/html/app/tools/seed_feedback_demo.php \
  --db=ktb_evaluation --reset
```

Membersihkan dummy kapan saja:

```sql
USE ktb_evaluation;
DELETE FROM users WHERE email LIKE '%@demo.agkb360.app';
```

Skrip menolak berjalan bila `PUBLIC_BASE_URL` bukan localhost, sehingga tidak
mungkin tereksekusi di VPS. Di lokal aman meskipun nama database-nya
`ktb_production`, karena itu salinan pengembangan.

Akun demo yang dibuat memakai domain `@demo.agkb360.app` dengan kata sandi
`DemoAGKB2026!`. Semua dapat dihapus sekaligus:

```sql
DELETE FROM users WHERE email LIKE '%@demo.agkb360.app';
```

**Cron eskalasi** (opsional — kalau tidak dipasang, eskalasi tetap jalan saat inbox dibuka):

```
0 * * * * docker exec ktb_php php /var/www/html/app/cron/escalate.php
```

## Berkas yang Dibuat

| Berkas | Isi |
|---|---|
| `migrations/009_feedback_ticketing.sql` | 7 tabel + 16 kategori bawaan |
| `public_html/app/includes/feedback.php` | Library inti: SLA, prioritas, eskalasi, audit, izin, lampiran, email |
| `public_html/app/feedback/index.php` | Formulir tiga track |
| `public_html/app/feedback/my.php` | Daftar laporan pengguna |
| `public_html/app/feedback/view.php` | Detail + thread (sisi pelapor) |
| `public_html/app/feedback/attachment.php` | Unduh lampiran terkendali |
| `public_html/app/admin/feedback.php` | Inbox tiket berfilter |
| `public_html/app/admin/ticket.php` | Penanganan tiket lengkap |
| `public_html/app/admin/feedback_categories.php` | CRUD kategori + rute eskalasi |
| `public_html/app/admin/feedback_dashboard.php` | Metrik & waktu penanganan |
| `public_html/app/cron/escalate.php` | Eskalasi otomatis |
| `public_html/app/tools/seed_feedback_demo.php` | 19 tiket demo, nama fiktif |

---

## 1. Ruang Lingkup

Mengembangkan modul Feedback dari formulir satu arah menjadi sistem penanganan berbasis tiket, dengan kategori, prioritas, SLA berjenjang, eskalasi, resolusi terstruktur, dan dashboard.

**Tidak termasuk dalam versi ini:** integrasi WhatsApp, aplikasi mobile, chatbot, pelaporan ke pihak eksternal secara otomatis.

## 2. Prinsip Rancangan

1. **Pintu masuk tetap ramah.** Nama yang dilihat pengguna tetap "Feedback & Apresiasi", bukan "Ticketing". Istilah tiket hanya muncul di sisi admin.
2. **Tiga track dengan perlakuan berbeda.** Apresiasi tidak boleh mengotori metrik penanganan keluhan.
3. **Setiap tiket punya satu penanggung jawab.** Watcher boleh banyak, PIC harus tunggal.
4. **Semua perubahan tercatat.** Tanpa audit log, pertanyaan "sudah sampai mana" tidak bisa dijawab.
5. **Kategori punya konsekuensi.** Menambah kategori wajib menetapkan PIC, SLA, dan prioritas default.
6. **Aktivitas tester tidak pernah masuk metrik.**

---

## 3. Tiga Track

| | **Apresiasi** | **Inquiry / Kendala** | **Safeguarding** 🔒 |
|---|---|---|---|
| Tujuan | Mengakui kontribusi positif | Menyelesaikan masalah operasional | Melindungi anak |
| Kategori | Ringan (opsional) | Wajib | Wajib, terbatas |
| Prioritas & SLA | Tidak ada | Ada | Selalu P1 |
| Eskalasi | Tidak | Ya | Langsung level tertinggi |
| Anonim | Tidak | Tidak | Ya, opsional |
| Lampiran | Boleh | Boleh | Boleh, disegel |
| Status | Baru → Diteruskan | Alur penuh | Alur penuh, terbatas |
| Terlihat oleh | Admin, PIC | Admin, PIC, Pimpinan | Yayasan + DSL saja |
| Hasil akhir | Diteruskan ke yang diapresiasi | Resolusi terstruktur | Catatan permanen |

**Apresiasi** cukup punya field tambahan "ditujukan kepada siapa" dan tombol *Teruskan* yang mengirim email ke orang tersebut. Tidak ada SLA, tidak muncul di grafik penyelesaian.

---

## 4. Skema Database

Tujuh tabel. Tabel `feedback` lama saat ini **kosong (0 baris di production)**, sehingga aman di-rename jadi `feedback_legacy` tanpa kehilangan data.

```
feedback_categories ──┐
                      ├──< feedback_tickets ──┬──< feedback_messages ──< feedback_attachments
users ────────────────┘                       ├──< feedback_events (audit, immutable)
                                              └──< feedback_watchers

feedback_escalation_levels  (routing per level / kategori)
```

### 4.1 `feedback_categories`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT PK | |
| `code` | VARCHAR(40) UNIQUE | Slug stabil, dipakai di kode |
| `name` | VARCHAR(120) | Nama tampil |
| `description` | VARCHAR(255) | Penjelasan singkat untuk pelapor |
| `track` | ENUM | `apresiasi` / `inquiry` / `safeguarding` |
| `default_pic_id` | INT NULL | **Wajib diisi saat membuat kategori** |
| `default_priority` | ENUM | P1–P4 |
| `sla_response_hours` | INT | Batas respons pertama |
| `sla_resolve_hours` | INT | Batas penyelesaian di level saat ini |
| `start_level` | TINYINT | Mulai dari level berapa (safeguarding = 3) |
| `is_sensitive` | TINYINT | Sembunyikan dari level bawah |
| `allow_anonymous` | TINYINT | |
| `require_attachment` | TINYINT | |
| `is_active` | TINYINT | Pensiun tanpa merusak data lama |
| `order_num` | INT | |

### 4.2 `feedback_tickets`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT PK | |
| `ticket_no` | VARCHAR(20) UNIQUE | `AGKB-2026-0001`, safeguarding `SG-2026-0001` |
| `track` | ENUM | |
| `category_id` | INT NULL | |
| `sender_id` | INT | Selalu tersimpan, bahkan saat anonim |
| `is_anonymous` | TINYINT | Menyembunyikan identitas dari PIC, bukan menghapusnya |
| `subject` | VARCHAR(255) | |
| `message` | TEXT | **Tidak dapat diubah setelah tersimpan** |
| `impact` | ENUM | `individu` / `kelompok` / `sekolah` |
| `priority` | ENUM | P1–P4, hasil hitung + override |
| `priority_overridden_by` | INT NULL | |
| `status` | ENUM | Lihat §6 |
| `level` | TINYINT | 1–3 |
| `assignee_id` | INT NULL | PIC tunggal |
| `appreciated_user_id` | INT NULL | Khusus track apresiasi |
| `forwarded_at` | DATETIME NULL | |
| `due_at` | DATETIME NULL | Dihitung dari SLA level saat ini |
| `first_response_at` | DATETIME NULL | |
| `resolved_at` / `closed_at` | DATETIME NULL | |
| `resolution_type` | ENUM NULL | Lihat §7 |
| `resolution_note` | TEXT NULL | |
| `resolved_by` | INT NULL | |
| `is_test` | TINYINT | 1 kalau pelapor ber-role `tester` |
| `created_at` / `updated_at` | TIMESTAMP | |

### 4.3 `feedback_messages` — thread percakapan

`id`, `ticket_id`, `author_id` (NULL untuk sistem), `body`, `visibility` (`publik` / `internal`), `is_system`, `created_at`.

Catatan internal tidak pernah terlihat pelapor. Ini yang memungkinkan PIC berdiskusi tanpa membuka isi ke pelapor.

### 4.4 `feedback_events` — audit log

`id`, `ticket_id`, `actor_id`, `event_type`, `from_value`, `to_value`, `note`, `ip`, `created_at`.

Jenis kejadian: `dibuat`, `dilihat`, `status_diubah`, `prioritas_diubah`, `pic_diubah`, `dieskalasi_otomatis`, `dieskalasi_manual`, `dibalas`, `lampiran_diunggah`, `lampiran_diunduh`, `diselesaikan`, `ditutup`, `dibuka_kembali`.

**Tabel ini hanya boleh INSERT.** Tidak ada UPDATE, tidak ada DELETE, termasuk untuk superadmin.

### 4.5 `feedback_watchers`

`ticket_id`, `user_id`, `added_by`, `created_at`. Primary key gabungan.

### 4.6 `feedback_attachments`

`id`, `ticket_id`, `message_id`, `uploader_id`, `original_name`, `stored_name` (acak), `mime`, `size_bytes`, `sha256`, `is_sealed`, `created_at`.

`sha256` untuk membuktikan berkas tidak berubah sejak diunggah. `is_sealed` menandai lampiran safeguarding yang tidak bisa dihapus siapa pun.

### 4.7 `feedback_escalation_levels`

`id`, `level`, `label`, `track`, `category_id` (NULL = berlaku umum), `user_id`, `email`, `order_num`, `is_active`.

> ⏳ **Isi tabel ini menunggu bagan pihak-pihak yang kamu maksud.** Struktur tabelnya sudah mengakomodasi *direct routing*: kategori tertentu bisa langsung menunjuk orang spesifik dan melewati level 1.

---

## 5. Kategori Bawaan (usulan — mohon dikoreksi)

### Track Apresiasi
| Kategori | PIC default |
|---|---|
| Apresiasi Guru / Staf | Kepala Sekolah |
| Apresiasi Program / Kegiatan | IB DP Coordinator |
| Apresiasi Umum | Admin Sekolah |

### Track Inquiry
| Kategori | Prioritas | Respons | Selesai | PIC usulan |
|---|---|---|---|---|
| Akademik & Pembelajaran | P2 | 1 hari | 3 hari | IB DP Coordinator |
| Kesiswaan & Kedisiplinan | P2 | 1 hari | 3 hari | Kepala Sekolah |
| Sarana & Fasilitas | P3 | 2 hari | 5 hari | Staf Sarana |
| Teknologi & Sistem AGKB | P2 | 1 hari | 2 hari | IT Admin |
| Administrasi & Keuangan | P3 | 2 hari | 5 hari | Admin Sekolah |
| Komunikasi & Informasi | P3 | 2 hari | 5 hari | Humas |
| Kepegawaian / SDM | P2 | 1 hari | 5 hari | Kepala Sekolah |
| Lain-lain | P3 | 2 hari | 5 hari | Admin Sekolah |

### Track Safeguarding 🔒
| Kategori | Prioritas | Pengakuan | Triase | Rute |
|---|---|---|---|---|
| Perundungan (bullying) | P1 | 24 jam | 24 jam | Yayasan + DSL |
| Kekerasan fisik atau verbal | P1 | 24 jam | 24 jam | Yayasan + DSL |
| Perilaku tidak pantas oleh dewasa | P1 | 24 jam | 24 jam | Yayasan saja |
| Keselamatan & keamanan | P1 | 24 jam | 24 jam | Yayasan + DSL |
| Diskriminasi | P1 | 24 jam | 24 jam | Yayasan + DSL |

Semua kategori safeguarding: `start_level = 3`, `is_sensitive = 1`, `allow_anonymous = 1`.

---

## 6. Alur Status

```
        ┌──────────────────────────────────┐
        ▼                                  │
  ┌─────────┐   ┌──────────┐   ┌───────────────────┐   ┌─────────┐   ┌────────┐
  │  BARU   │──▶│ DITINJAU │──▶│  DITINDAKLANJUTI  │──▶│ SELESAI │──▶│ DITUTUP│
  └─────────┘   └──────────┘   └───────────────────┘   └─────────┘   └────────┘
                                         │  ▲                │
                                         ▼  │                │ dibuka kembali
                              ┌────────────────────┐         │ (≤14 hari)
                              │ MENUNGGU PELAPOR   │◀────────┘
                              └────────────────────┘
```

| Status | Arti | SLA berjalan? |
|---|---|---|
| **Baru** | Belum dilihat PIC | Ya |
| **Ditinjau** | Sudah dibaca, sedang dinilai | Ya |
| **Ditindaklanjuti** | Sedang dikerjakan | Ya |
| **Menunggu Pelapor** | Butuh informasi tambahan | **Jeda** |
| **Selesai** | Sudah ada resolusi, pelapor diberi tahu | Berhenti |
| **Ditutup** | Final setelah 14 hari tanpa keberatan | Berhenti |

SLA berhenti saat status *Menunggu Pelapor* — kalau tidak, PIC dihukum karena pelapor lambat membalas.

---

## 7. Prioritas & Eskalasi

### Perhitungan prioritas

```
prioritas = prioritas_default_kategori
  + 1 tingkat  jika dampak = seluruh sekolah
  − 1 tingkat  jika dampak = perorangan
```

Batas P1–P4. Track safeguarding **selalu P1** dan tidak dapat diturunkan. Admin boleh override manual, tercatat di audit log beserta alasannya.

| | Respons | Selesai per level |
|---|---|---|
| **P1 Kritis** | 4 jam | 24 jam |
| **P2 Tinggi** | 1 hari kerja | 3 hari kerja |
| **P3 Sedang** | 2 hari kerja | 5 hari kerja |
| **P4 Rendah** | 3 hari kerja | 10 hari kerja |

### Eskalasi

**Otomatis** — saat `due_at` terlampaui dan status belum Selesai, tiket naik satu level, PIC berganti ke penanggung jawab level berikutnya, `due_at` dihitung ulang, dan notifikasi dikirim ke PIC lama, PIC baru, serta watcher.

**Manual** — PIC atau admin dapat menaikkan level kapan saja dengan alasan wajib.

Dijalankan lewat cron setiap jam. Kalau cron tidak tersedia, dicek saat halaman admin dibuka (*lazy escalation*).

> ⏳ Tabel rute per level menunggu bagan dari kamu.

---

## 8. Resolusi Terstruktur

Saat menyelesaikan tiket, PIC wajib memilih satu jenis resolusi **dan** menulis deskripsi. Keduanya dikirim ke email pelapor.

| Jenis | Kapan dipakai |
|---|---|
| Diselesaikan | Masalah tertangani |
| Diteruskan ke pihak eksternal | Di luar kewenangan sekolah |
| Kebijakan diubah | Menghasilkan perubahan aturan |
| Tidak dapat ditindaklanjuti | Di luar cakupan |
| Duplikat | Sudah dilaporkan sebelumnya |
| Informasi tidak cukup | Pelapor tidak merespons |
| Tidak terbukti | Setelah ditelusuri, tidak ditemukan dasarnya |

Deskripsi minimal 20 karakter. Ini yang membuat dashboard bisa menjawab "penyelesaian apa yang paling sering", bukan sekadar "berapa yang selesai".

---

## 9. Aturan Khusus Safeguarding 🔒

Mengacu pada praktik yang lazim di sekolah internasional (KCSIE, Keeping Children Safe International Standards, panduan ITFCP).

**Rute.** Sesuai keputusan saat ini: **semua laporan safeguarding masuk ke Yayasan (Bu Dewi & Aqsa)** sampai DSL resmi ditunjuk. Tidak terlihat oleh admin maupun pimpinan sekolah.

**Peringatan di formulir.** Teks tetap, tidak bisa dilewati:

> Jika ada anak dalam bahaya saat ini, **jangan menunggu sistem ini**. Hubungi penanggung jawab perlindungan anak secara langsung, atau layanan darurat.

**Tidak ada penyelidikan lewat sistem.** *Refer, don't investigate.* Balasan hanya untuk konfirmasi penerimaan dan pemberitahuan tindak lanjut.

**Catatan tidak dapat diubah.** Isi laporan tidak bisa diedit atau dihapus oleh siapa pun, termasuk superadmin. Penambahan hanya lewat catatan baru bertanggal.

**Akses tercatat.** Setiap pembukaan laporan dan pengunduhan lampiran masuk audit log.

**Konflik kepentingan.** Seseorang tidak dapat membuka tiket yang menyebut dirinya sebagai subjek. Sistem memeriksa `assignee_id` dan `appreciated_user_id`; untuk penyebutan di dalam teks, admin wajib menandai manual.

**Non-retaliasi.** Pernyataan perlindungan pelapor ditampilkan di formulir dan disertakan di email konfirmasi.

**Retensi.** Catatan safeguarding tidak dihapus setelah selesai. Standar internasional menyimpan hingga anak berusia 25 tahun. 🔒 Perlu keputusan Yayasan.

**Anonimitas.** Identitas tetap tersimpan di basis data, hanya terbuka bagi superadmin, dan setiap pembukaan identitas tercatat.

> ⚠️ **Catatan penting.** Modul ini tidak menggantikan kebijakan perlindungan anak sekolah. Sebelum dibuka untuk murid, KTB perlu menunjuk DSL resmi beserta deputinya, dan alur ini sebaiknya disetujui tertulis oleh Yayasan.

---

## 10. Keamanan Lampiran

Berlaku semua track, dengan pengetatan untuk safeguarding.

| Aspek | Aturan |
|---|---|
| Lokasi | Di luar webroot: `/var/agkb-uploads/`, bukan `public_html/` |
| Nama berkas | Acak 32 karakter, nama asli hanya di basis data |
| Akses | Lewat `feedback/attachment.php?id=` yang memeriksa izin, bukan tautan langsung |
| Tipe diizinkan | jpg, jpeg, png, webp, pdf, docx, xlsx, mp3, mp4, m4a |
| Validasi | Cek MIME sebenarnya (`finfo`), bukan ekstensi |
| Ukuran | Maks 10 MB per berkas, 5 berkas per tiket |
| Header | `Content-Disposition: attachment` + `X-Content-Type-Options: nosniff` |
| Safeguarding | `is_sealed = 1`, tidak dapat dihapus, setiap unduhan tercatat |

Direktori unggahan wajib punya `.htaccess` penolak eksekusi sebagai lapisan kedua, meski sudah di luar webroot.

---

## 11. Dashboard

### Kartu ringkas
Masuk · Sedang Diproses · Selesai · Menunggu Pelapor · **Terlambat (SLA lewat)**

### Metrik waktu
- Rata-rata waktu respons pertama
- Rata-rata waktu penyelesaian
- Persentase tiket selesai dalam SLA
- Median, bukan hanya rata-rata — satu tiket ekstrem bisa menyesatkan

### Rincian
- Per kategori (jumlah + waktu penyelesaian)
- Per PIC (beban aktif + kepatuhan SLA)
- Per jenis resolusi
- Tren bulanan
- Daftar tiket terlambat, diurutkan dari yang paling lama

**Semua metrik mengecualikan `is_test = 1`.** Track safeguarding hanya tampil sebagai jumlah agregat di dashboard umum; rinciannya di halaman terpisah dengan akses terbatas.

---

## 12. Hak Akses

| Role | Kirim | Lihat sendiri | Inbox umum | Kelola | Safeguarding |
|---|---|---|---|---|---|
| superadmin | ✅ | ✅ | ✅ | ✅ | ✅ + buka identitas |
| admin | ✅ | ✅ | ✅ | ✅ | ❌ |
| foundation | ✅ | ✅ | ✅ | ✅ | ✅ |
| leader | ✅ | ✅ | Level 2+ | Yang di-assign | ❌ |
| teacher / staff / mentor | ✅ | ✅ | ❌ | Yang di-assign | ❌ |
| student / parent | ✅ | ✅ | ❌ | ❌ | ❌ |
| **tester** | ✅ (ditandai) | ✅ | ❌ | ❌ | ❌ |

**Perbaikan dari kondisi sekarang:** role `foundation` mendapat akses inbox — saat ini tidak bisa, padahal semua email feedback dikirim ke `edu@kaderbangsa.foundation`.

**Tester** dapat mengirim dan melihat tiketnya sendiri, tetapi semua tiketnya bertanda `is_test = 1` dan tidak pernah masuk metrik. Banner ungu "Mode Tester" tetap tampil.

---

## 13. Notifikasi Email

| Pemicu | Penerima |
|---|---|
| Tiket baru | PIC + watcher |
| Tiket safeguarding baru | Yayasan, **segera, tanpa digest** |
| Status berubah | Pelapor |
| Dieskalasi | PIC lama, PIC baru, watcher |
| Mendekati batas SLA (75%) | PIC |
| SLA terlampaui | PIC + atasan level berikutnya |
| Selesai | Pelapor, berisi jenis + deskripsi resolusi |
| Apresiasi diteruskan | Orang yang diapresiasi |

Kuota Workspace 1.500/hari memadai. Tetap disarankan digest harian untuk notifikasi non-mendesak agar tidak membanjiri kotak masuk PIC. Semua email pakai template AGKB 360° yang sudah ada.

---

## 14. Data Demo

Untuk `/demo` (DB `ktb_evaluation`), **nama fiktif seluruhnya** — aman ditunjukkan ke pihak luar.

Sekitar 18 tiket yang mencakup semua kondisi:

| # | Kondisi yang ditunjukkan |
|---|---|
| 1–3 | Apresiasi: baru, sudah diteruskan, ke program |
| 4–6 | Inquiry baru di kategori berbeda |
| 7–8 | Sedang ditindaklanjuti, SLA masih aman |
| 9–10 | **Terlambat**, sudah tereskalasi otomatis ke level 2 |
| 11 | Tereskalasi manual ke Yayasan |
| 12 | Menunggu pelapor |
| 13–16 | Selesai dengan empat jenis resolusi berbeda |
| 17 | Ditutup, dengan thread percakapan panjang |
| 18 | Safeguarding anonim — hanya terlihat saat login sebagai Yayasan |

Tiket 18 penting untuk demo: memperlihatkan bahwa admin sekolah **tidak** bisa melihatnya. Itu yang paling meyakinkan pihak Yayasan.

---

## 15. Tahapan Kerja

| Tahap | Isi | Prasyarat |
|---|---|---|
| **0** | Perbaiki 4 bug: URL `/demo` hardcoded, akses foundation, email balasan plaintext, CSRF | — |
| **1** | Migration + kategori + tiket + thread + audit log | Kategori disetujui |
| **2** | Prioritas, SLA, eskalasi otomatis & manual | Bagan pihak eskalasi |
| **3** | Resolusi terstruktur + notifikasi email | — |
| **4** | Lampiran + keamanan berkas | Direktori VPS disiapkan |
| **5** | Dashboard & metrik | — |
| **6** | Track safeguarding | 🔒 Persetujuan Yayasan |
| **7** | Seed data demo + deploy `/demo` | — |

Tahap 0 bisa dikerjakan sekarang, tidak bergantung apa pun.

---

## 16. Yang Perlu Diputuskan Sekolah / Yayasan 🔒

1. **Penunjukan DSL resmi dan deputinya.** Sementara semua ke Yayasan, tapi ini harus diselesaikan sebelum modul dibuka untuk murid.
2. **Masa retensi catatan safeguarding.** Usulan: sampai murid berusia 25 tahun.
3. **Siapa yang berwenang membuka identitas pelapor anonim,** dan dalam keadaan apa.
4. **Apakah murid dan orang tua boleh mengakses track safeguarding,** atau hanya staf pada tahap awal.
5. **Kalimat pernyataan non-retaliasi** yang akan ditampilkan.
6. **Persetujuan daftar kategori dan angka SLA** di §5.

---

## Lampiran — Bug yang Ditemukan pada Kode Saat Ini

| Berkas | Masalah |
|---|---|
| `feedback/index.php:42` | `$fullReplyUrl` hardcoded ke `/demo/`, padahal ini aplikasi production |
| `feedback/index.php` | Formulir tidak memakai CSRF, padahal `csrfToken()` dan `verifyCsrf()` sudah tersedia |
| `admin/feedback.php:9` | `requireRole(['superadmin','admin'])` — role `foundation` tidak bisa masuk, padahal email dikirim ke Yayasan |
| `admin/feedback.php:29` | Email balasan dikirim sebagai `body` plaintext, sementara email masuk pakai `htmlBody` |
| `admin/feedback.php` | Setelah dibalas sekali, formulir hilang — tidak bisa berdialog |
| `admin/` | Ada berkas `Panduan Kemitraan Koperasi-modeartor-Aulia-rve1.pdf` yang dapat diakses publik |
