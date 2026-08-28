#!/usr/bin/env python3
"""
AGKB 360° — Analisis tiket untuk laporan.

Membaca tiket-semua.json (hasil tarikan Apps Script), menghitung
tema, dan menghasilkan data.json + tema.png untuk buat_deck.js.

Bagian yang dapat dihitung mesin dikerjakan di sini. Kutipan dan
simpulan tiap tema ditulis terpisah di naskah.json karena butuh
pembacaan, bukan penghitungan.

Pakai:
  python3 analisis.py <tiket-semua.json> <folder-kerja> [--dari=..] [--sampai=..]

Tanpa --dari/--sampai, seluruh isi berkas dipakai.
"""
import re, os, sys, json, collections
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt

OBL, CAT, MERAH, HIJAU, GAL = '#040136', '#ff9101', '#b42318', '#027a48', '#2201b2'
ABU = '#6b6a83'

# Lato agar seragam dengan deck. Kalau tidak terpasang, matplotlib
# jatuh ke DejaVu Sans — grafiknya tetap terbaca, hanya beda rupa.
plt.rcParams.update({
    'font.family': ['Lato', 'DejaVu Sans'],
    'font.size': 13,
    'text.color': OBL,
    'figure.facecolor': 'white',
    'axes.facecolor': 'white',
})

# ── Tema, dikenali dari isi laporan ─────────────────────────
# Satu laporan bisa masuk lebih dari satu tema. Itu disengaja:
# yang diukur adalah seberapa sering suatu hal disinggung.
TEMA = {
    'Pembinaan dan kedisiplinan': r'misconduct|\bsp ?1\b|mentor|etika|kesopanan|house|hukuman|pembinaan|teguran|apel',
    'Layanan dan kondisi asrama': r'laundry|klinik|perawat|konsumsi|makan|air minum|mbg|dining|kamar mandi|toilet|wastafel|air ',
    'Konektivitas internet':      r'wi.?fi|internet|sinyal|koneksi|router|jaringan',
    'Waktu belajar dan jadwal':   r'waktu belajar|belajar malam|jam belajar|olahraga pagi|pukul \d|jadwal',
    'Kebijakan akademik':         r'absen|kompetisi|osn|ibdp|kurikulum|silabus',
    'Beban kerja dan komunikasi': r'liburan|tenggat|to.?do|manajemen|dikomunikasikan|slip gaji',
}


def polos(ax):
    for s in ('top', 'right', 'bottom', 'left'):
        ax.spines[s].set_visible(False)
    ax.set_xticks([])
    ax.tick_params(length=0)


def main():
    arg = [a for a in sys.argv[1:] if not a.startswith('--')]
    opt = dict(a[2:].split('=', 1) for a in sys.argv[1:]
               if a.startswith('--') and '=' in a)
    if len(arg) < 2:
        sys.exit(__doc__)

    src, out = arg[0], arg[1]
    os.makedirs(out, exist_ok=True)

    sumber = json.load(open(src, encoding='utf-8'))
    r = sumber['tiket']

    # AGKB-2026-0001 s.d. 0008 (19 Jun–23 Jul 2026) dari sebelum sistem
    # eskalasi ada: prioritas datar, satu PJ default, tidak pernah
    # bergerak dari status Baru, tanpa jalur Kanal Yayasan. Diputuskan
    # 19 Agustus 2026 (bersama pengguna) untuk dianggap selesai dan
    # dikecualikan permanen dari laporan mingguan, bukan cuma periode
    # ini. Jangan hapus baris ini kalau menambah data lama lain.
    AWAL_SISTEM_ESKALASI = '2026-08-11'
    r = [x for x in r if x['masuk'][:10] >= AWAL_SISTEM_ESKALASI]
    if not r:
        sys.exit('Tidak ada tiket pada rentang ini (setelah era pra-eskalasi dikecualikan).')

    dari   = opt.get('dari')
    sampai = opt.get('sampai')
    if dari:   r = [x for x in r if x['masuk'][:10] >= dari]
    if sampai: r = [x for x in r if x['masuk'][:10] <= sampai]
    if not r:
        sys.exit('Tidak ada tiket pada rentang ini.')
    n = len(r)

    isi = lambda x: x['subjek'] + ' ' + (x.get('isi') or '')
    tema = {t: sum(1 for x in r if re.search(p, isi(x), re.I))
            for t, p in TEMA.items()}

    # ── Grafik tema ─────────────────────────────────────────
    fig, ax = plt.subplots(figsize=(7.0, 3.8))
    it = sorted(tema.items(), key=lambda kv: kv[1])
    nm = [k for k, _ in it]
    nl = [v for _, v in it]
    b = ax.barh(nm, nl, color=[CAT if v == max(nl) else OBL for v in nl], height=.6)
    ax.bar_label(b, padding=7, fontsize=14, fontweight='bold', color=OBL)
    ax.set_xlim(0, max(nl) * 1.28)
    polos(ax)
    ax.tick_params(labelsize=12.5)
    plt.tight_layout()
    plt.savefig(f'{out}/tema.png', dpi=200)
    plt.close()

    # ── Daftar pokok unik, kiriman ganda disatukan ──────────
    norm = lambda s: re.sub(r'\W+', ' ', s.lower()).strip()
    grup = collections.OrderedDict()
    for x in sorted(r, key=lambda y: y['masuk']):
        grup.setdefault(norm(x['subjek']), []).append(x)

    hit = lambda k: dict(collections.Counter(x[k] for x in r if x.get(k)))

    data = {
        'total':  n,
        'unik':   len(grup),
        'dari':   min(x['masuk'] for x in r)[:10],
        'sampai': max(x['masuk'] for x in r)[:10],
        'ditarik': sumber.get('ditarik'),
        'jalur':    hit('jalur'),
        'asal':     hit('asal'),
        'kategori': hit('kategori'),
        'status':   hit('status'),
        'tema':     tema,
        'belum_ditugaskan': sum(1 for x in r if not x.get('penanggung_jawab')),
        'ditutup':          sum(1 for x in r if x['status_kode'] == 'ditutup'),
        'ada_respons':      sum(1 for x in r if x.get('respons_pertama')),
        'daftar': [{
            'no':       g[0]['nomor'],
            'jalur':    g[0]['jalur'],
            'kategori': g[0]['kategori'],
            'subjek':   g[0]['subjek'],
            'masuk':    g[0]['masuk'][:10],
            'kali':     len(g),
            'asal':     g[0]['asal'],
            'prioritas': g[0]['prioritas'],
            'cuplik':   (g[0].get('isi') or '')[:400],
        } for g in grup.values()],
    }

    json.dump(data, open(f'{out}/data.json', 'w'), indent=1, ensure_ascii=False)

    print(json.dumps({k: v for k, v in data.items() if k != 'daftar'},
                     indent=1, ensure_ascii=False))
    print(f'\n{len(grup)} pokok unik dari {n} tiket → {out}/data.json')


if __name__ == '__main__':
    main()
