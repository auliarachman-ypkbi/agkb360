#!/usr/bin/env bash
# ============================================================
# AGKB 360° — Ekspor tiket mingguan dan unggah ke Google Drive
# ------------------------------------------------------------
# Dipasang di VPS, dijalankan cron tiap Minggu malam.
#
#   /root/agkb360/laporan-mingguan/vps/ekspor-mingguan.sh
#
# Perlu rclone terpasang dan remote bernama "ktbdrive" yang
# menunjuk ke shared drive "Feedback Weekly Update - KTB".
# Lihat PASANG.md untuk langkah penyiapannya.
# ============================================================
set -euo pipefail

REMOTE="${AGKB_RCLONE_REMOTE:-ktbdrive}"
KONTENER="${AGKB_KONTENER:-ktb_php}"
KERJA="${AGKB_KERJA:-/root/agkb360/laporan-mingguan-keluaran}"
LOG="$KERJA/riwayat.log"

mkdir -p "$KERJA"

catat() { printf '%s  %s\n' "$(date '+%F %T')" "$*" | tee -a "$LOG"; }

# Rentang: tujuh hari terakhir, berakhir hari ini.
SAMPAI="${2:-$(date +%F)}"
DARI="${1:-$(date -d '6 days ago' +%F)}"

BERKAS="$KERJA/tiket-$DARI-sd-$SAMPAI.csv"

catat "Mulai ekspor $DARI s.d. $SAMPAI"

# --dengan-isi diperlukan: analisis tema membaca isi laporan.
# Identitas pelapor anonim sengaja TIDAK dibuka.
if ! docker exec -i "$KONTENER" php \
      /var/www/html/app/tools/ekspor-tiket.php \
      --dari="$DARI" --sampai="$SAMPAI" --dengan-isi > "$BERKAS" 2>>"$LOG"; then
  catat "GAGAL: ekspor tidak berjalan"
  exit 1
fi

BARIS=$(($(wc -l < "$BERKAS") - 1))
if [ "$BARIS" -lt 1 ]; then
  catat "Tidak ada tiket pada rentang ini. Berhenti."
  rm -f "$BERKAS"
  exit 0
fi

catat "$BARIS tiket terekspor"

# Salinan bernama tetap: itulah yang dibaca Claude.
cp "$BERKAS" "$KERJA/tiket-terbaru.csv"

# ── Unggah ke shared drive ──────────────────────────────────
if ! rclone copy "$BERKAS" "$REMOTE:data/" --log-file="$LOG" --log-level INFO; then
  catat "GAGAL: unggah arsip"
  exit 1
fi
if ! rclone copy "$KERJA/tiket-terbaru.csv" "$REMOTE:data/" --log-file="$LOG" --log-level INFO; then
  catat "GAGAL: unggah tiket-terbaru.csv"
  exit 1
fi

# Penanda untuk Claude: rentang mana yang baru saja diunggah.
# Dibaca tugas terjadwal supaya tidak perlu menebak tanggal.
printf '{"dari":"%s","sampai":"%s","tiket":%s,"diunggah":"%s"}\n' \
  "$DARI" "$SAMPAI" "$BARIS" "$(date -Iseconds)" > "$KERJA/rentang.json"
rclone copy "$KERJA/rentang.json" "$REMOTE:data/" --log-level ERROR

catat "Selesai. $BARIS tiket diunggah ke $REMOTE:data/"

# ── Kaitan untuk peringatan laporan berat ───────────────────
# Belum diaktifkan. Bila suatu saat diperlukan, tempat memasangnya
# ada di sini: baca CSV, cari tiket P1 tanpa penanggung jawab,
# lalu kirim email. Menunggu deck dibuka bukan cara yang tepat
# untuk laporan yang menyangkut keselamatan seseorang.
# if [ -x "$(dirname "$0")/peringatan.sh" ]; then
#   "$(dirname "$0")/peringatan.sh" "$BERKAS" >>"$LOG" 2>&1 || true
# fi
