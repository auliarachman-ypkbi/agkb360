#!/usr/bin/env bash
# ============================================================
# AGKB 360° — Tarik tiket dari VPS ke folder Google Drive
# ------------------------------------------------------------
# Dijalankan dari Mac. Sandbox Claude tidak punya akses jaringan,
# jadi langkah inilah yang harus dikerjakan di komputer sendiri.
#
# Pakai:
#   ./tarik-tiket.sh                 tujuh hari terakhir
#   ./tarik-tiket.sh 2026-08-10      sejak tanggal itu sampai hari ini
#   ./tarik-tiket.sh 2026-08-10 2026-08-18
# ============================================================
set -euo pipefail

VPS="${AGKB_VPS:-root@145.79.10.123}"
NAMA_FOLDER="${AGKB_FOLDER:-Feedback Weekly Update - KTB}"

# ── Cari folder tujuan ──────────────────────────────────────
# Google Drive Desktop menaruh shared drive di ~/Library/CloudStorage,
# dengan nama folder yang mengandung alamat email dan bahasa antarmuka
# ("Shared drives" atau "Drive bersama"). Daripada menebak, dicari saja.
cari_tujuan() {
  if [ -n "${AGKB_TUJUAN:-}" ]; then
    printf '%s' "$AGKB_TUJUAN"
    return
  fi
  find "$HOME/Library/CloudStorage" -maxdepth 3 -type d \
       -name "$NAMA_FOLDER" -print -quit 2>/dev/null
}

TUJUAN="$(cari_tujuan)"

if [ -z "$TUJUAN" ] || [ ! -d "$TUJUAN" ]; then
  echo "Folder \"$NAMA_FOLDER\" tidak ditemukan." >&2
  echo >&2
  echo "Shared drive yang terbaca sekarang:" >&2
  find "$HOME/Library/CloudStorage" -maxdepth 3 -type d 2>/dev/null \
    | grep -iE 'shared|bersama' | sed 's/^/  /' >&2
  echo >&2
  echo "Bila namanya berbeda, jalankan dengan:" >&2
  echo "  AGKB_TUJUAN='/jalur/lengkap/folder' $0" >&2
  exit 1
fi

# ── Rentang: bawaan tujuh hari terakhir ─────────────────────
SAMPAI="${2:-$(date +%F)}"
DARI="${1:-$(date -v-6d +%F)}"

BERKAS="$TUJUAN/data/tiket-$DARI-sd-$SAMPAI.csv"
mkdir -p "$TUJUAN/data"

echo "Folder tujuan : $TUJUAN"
echo "Menarik tiket : $DARI s.d. $SAMPAI dari $VPS"
echo

# --dengan-isi diperlukan: analisis tema membaca isi laporan.
# Identitas pelapor anonim sengaja TIDAK dibuka; berkas ini akan
# berada di shared drive dan mudah berpindah tangan.
ssh "$VPS" "docker exec -i ktb_php php \
  /var/www/html/app/tools/ekspor-tiket.php \
  --dari=$DARI --sampai=$SAMPAI --dengan-isi" > "$BERKAS"

BARIS=$(($(wc -l < "$BERKAS") - 1))

if [ "$BARIS" -lt 1 ]; then
  echo "Tidak ada tiket pada rentang ini. Berkas dihapus." >&2
  rm -f "$BERKAS"
  exit 1
fi

# Salinan bernama tetap, supaya Claude selalu tahu mana yang terbaru.
cp "$BERKAS" "$TUJUAN/data/tiket-terbaru.csv"

echo "Selesai. $BARIS tiket tersimpan."
echo "  $BERKAS"
echo
echo "Menunggu Drive menyinkronkan …"
sleep 3
echo
echo "Langkah berikutnya — buka Claude, lalu katakan:"
echo "  buat laporan mingguan $DARI sampai $SAMPAI"
