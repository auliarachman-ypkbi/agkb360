# Memasang ekspor mingguan otomatis di VPS

Sekali pasang. Sesudahnya VPS mengunggah CSV ke shared drive tiap
Minggu malam, tanpa Mac perlu menyala.

---

## 1. Buat service account Google

Service account adalah "akun robot" — ia punya alamat email sendiri dan
bisa diberi akses ke shared drive seperti orang biasa. VPS memakai
kuncinya, bukan akun pribadi Anda. Kalau kuncinya bocor, yang perlu
dicabut hanya akun robot itu.

1. Buka <https://console.cloud.google.com/> → buat proyek baru,
   misalnya `agkb360-laporan`.
2. **APIs & Services → Library** → cari *Google Drive API* → **Enable**.
3. **APIs & Services → Credentials → Create credentials →
   Service account**. Beri nama `agkb-laporan`.
4. Buka service account itu → tab **Keys** → **Add key → Create new key
   → JSON**. Berkas kunci terunduh. Simpan baik-baik; itu satu-satunya
   salinannya.
5. Salin alamat email service account-nya, bentuknya seperti
   `agkb-laporan@agkb360-laporan.iam.gserviceaccount.com`.

## 2. Beri akses ke shared drive

Buka shared drive **Feedback Weekly Update - KTB** di Google Drive →
**Kelola anggota** → tambahkan alamat email service account tadi
sebagai **Content manager**.

Cukup *Content manager*, jangan *Manager*. Robot ini perlu menulis
berkas, tidak perlu bisa menambah atau mengeluarkan anggota.

## 3. Pasang rclone di VPS

```sh
ssh root@145.79.10.123
curl https://rclone.org/install.sh | sudo bash
```

Salin berkas kunci JSON ke VPS. Dari **Mac**, di terminal baru:

```sh
scp ~/Downloads/agkb360-laporan-*.json root@145.79.10.123:/root/agkb-drive.json
ssh root@145.79.10.123 'chmod 600 /root/agkb-drive.json'
```

## 4. Cari ID shared drive-nya

Buka shared drive di browser. URL-nya berbentuk:

```
https://drive.google.com/drive/folders/0AB1cdEfGhIjKlMnOpQ
                                       └──── ini ID-nya ────┘
```

## 5. Atur remote rclone

Di VPS, buat berkas konfigurasinya langsung — lebih cepat daripada
lewat menu interaktif. Ganti `ID_SHARED_DRIVE` dengan yang tadi:

```sh
mkdir -p ~/.config/rclone
cat > ~/.config/rclone/rclone.conf <<'EOF'
[ktbdrive]
type = drive
scope = drive
service_account_file = /root/agkb-drive.json
team_drive = ID_SHARED_DRIVE
root_folder_id =
EOF
chmod 600 ~/.config/rclone/rclone.conf
```

Uji sambungannya:

```sh
rclone lsd ktbdrive:
```

Kalau folder `data` muncul, berarti sudah tersambung.

## 6. Pasang skripnya

```sh
cd /root/agkb360 && git pull
chmod +x laporan-mingguan/vps/ekspor-mingguan.sh
```

Coba jalankan sekali dengan rentang yang sudah diketahui isinya:

```sh
/root/agkb360/laporan-mingguan/vps/ekspor-mingguan.sh 2026-08-10 2026-08-18
```

Periksa hasilnya muncul di Drive, lalu lihat catatannya:

```sh
tail -20 /root/agkb360/laporan-mingguan-keluaran/riwayat.log
```

## 7. Jadwalkan tiap Minggu malam

```sh
crontab -e
```

Tambahkan satu baris. **Perhatikan:** crontab VPS berjalan pada UTC,
sedangkan Minggu 20.00 WIB berarti Minggu 13.00 UTC.

```cron
0 13 * * 0 /root/agkb360/laporan-mingguan/vps/ekspor-mingguan.sh >> /root/agkb360/laporan-mingguan-keluaran/cron.log 2>&1
```

---

## Yang perlu diingat

**Kunci JSON itu setara kata sandi.** Siapa pun yang memegangnya bisa
menulis ke shared drive tersebut. Ia hanya boleh ada di dua tempat:
komputer Anda dan `/root` di VPS. Jangan pernah masuk ke git — folder
`vps/` di repo ini hanya berisi skrip, bukan kunci.

**Bila VPS diganti atau dijual**, cabut kuncinya dari Google Cloud
Console lebih dulu. Menghapus berkasnya saja tidak mencabut aksesnya.

**Cron gagal diam-diam.** Kalau suatu Minggu tidak ada berkas baru di
Drive, periksa `cron.log` dan `riwayat.log`. Keduanya menyimpan alasan
kegagalan.

**Kaitan peringatan laporan berat** sudah disiapkan di bagian bawah
`ekspor-mingguan.sh`, masih dinonaktifkan. Bila nanti diperlukan,
tinggal buat `peringatan.sh` di folder yang sama dan hapus tanda
komentarnya.
