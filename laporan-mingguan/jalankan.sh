#!/usr/bin/env bash
# ============================================================
# AGKB 360° — Rakit deck mingguan dari CSV
# ------------------------------------------------------------
# Dijalankan Claude di dalam sandbox-nya, bukan di Mac.
#
#   ./jalankan.sh <berkas.csv> <folder-kerja> <keluaran.pptx> [--dari=..] [--sampai=..]
#
# Folder kerja harus sudah berisi naskah.json.
# ============================================================
set -euo pipefail
cd "$(dirname "$0")"

CSV="$1"; KERJA="$2"; KELUAR="$3"; shift 3

if [ ! -f "$KERJA/naskah.json" ]; then
  echo "naskah.json belum ada di $KERJA — salin dari naskah-contoh.json lalu sesuaikan isinya." >&2
  exit 1
fi

# pptxgenjs tinggal di folder sementara yang dibersihkan tiap sesi,
# jadi dipasang ulang bila belum ada.
MOD="${AGKB_NODE_MODULES:-$HOME/.agkb-node}"
if [ ! -d "$MOD/node_modules/pptxgenjs" ]; then
  echo "Memasang pptxgenjs …"
  mkdir -p "$MOD" && (cd "$MOD" && npm install --silent --no-fund --no-audit pptxgenjs >/dev/null)
fi

python3 analisis.py "$CSV" "$KERJA" "$@" | tail -30
NODE_PATH="$MOD/node_modules" node buat_deck.js "$KERJA" "$KELUAR"
