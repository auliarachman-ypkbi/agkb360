#!/usr/bin/env python3
"""
AGKB 360° — Analisis tiket untuk laporan mingguan.

Menghasilkan grafik tema dan berkas data.json yang dipakai
buat_deck.js. Bagian yang dapat dihitung mesin dikerjakan di
sini; kutipan dan simpulan tiap tema ditulis terpisah di
naskah.json karena butuh pembacaan, bukan penghitungan.

Pakai:
  python3 analisis.py <berkas.csv> <folder-keluaran> [--dari=..] [--sampai=..]
"""
import csv, io, re, os, sys, json, collections
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt

OBL, CAT, MERAH, HIJAU, GAL = '#040136', '#ff9101', '#b42318', '#027a48', '#2201b2'
ABU = '#6b6a83'
plt.rcParams.update({'font.family': 'DejaVu Sans', 'font.size': 11,
                     'text.color': OBL, 'figure.facecolor': 'white',
                     'axes.facecolor': 'white'})

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
    opt = dict(a[2:].split('=', 1) for a in sys.argv[1:] if a.startswith('--') and '=' in a)
    if len(arg) < 2:
        sys.exit(__doc__)
    src, out = arg[0], arg[1]
    os.makedirs(out, exist_ok=True)

    r = list(csv.DictReader(io.StringIO(open(src, encoding='utf-8-sig').read())))
    dari   = opt.get('dari')
    sampai = opt.get('sampai')
    if dari:   r = [x for x in r if x['Masuk'][:10] >= dari]
    if sampai: r = [x for x in r if x['Masuk'][:10] <= sampai]
    if not r:
        sys.exit('Tidak ada tiket pada rentang ini.')
    n = len(r)

    isi = lambda x: x['Subjek'] + ' ' + x.get('Isi laporan', '')
    tema = {t: sum(1 for x in r if re.search(p, isi(x), re.I)) for t, p in TEMA.items()}

    # ── Grafik tema ─────────────────────────────────────────
    fig, ax = plt.subplots(figsize=(6.8, 3.6))
    it = sorted(tema.items(), key=lambda kv: kv[1])
    nm = [k for k, _ in it]
    nl = [v for _, v in it]
    b = ax.barh(nm, nl, color=[CAT if v == max(nl) else OBL for v in nl], height=.6)
    ax.bar_label(b, padding=6, fontsize=12, fontweight='bold', color=OBL)
    ax.set_xlim(0, max(nl) * 1.28)
    polos(ax)
    ax.tick_params(labelsize=10.5)
    plt.tight_layout()
    plt.savefig(f'{out}/tema.png', dpi=200)
    plt.close()

    # ── Daftar pokok unik, kiriman ganda disatukan ──────────
    norm = lambda s: re.sub(r'\W+', ' ', s.lower()).strip()
    grup = collections.OrderedDict()
    for x in sorted(r, key=lambda y: y['Masuk']):
        grup.setdefault(norm(x['Subjek']), []).append(x)

    hit = lambda k: dict(collections.Counter(x[k] for x in r if x.get(k)))

    data = {
        'total': n,
        'unik': len(grup),
        'dari': min(x['Masuk'] for x in r)[:10],
        'sampai': max(x['Masuk'] for x in r)[:10],
        'jalur': hit('Jalur'),
        'asal': hit('Asal pelapor'),
        'kategori': hit('Kategori'),
        'tema': tema,
        'belum_ditugaskan': sum(1 for x in r if x['Penanggung jawab'] == 'Belum ditugaskan'),
        'ditutup': sum(1 for x in r if x['Status'] == 'Ditutup'),
        'ada_respons': sum(1 for x in r if x.get('Respons pertama')),
        'daftar': [{
            'no': g[0]['Nomor tiket'],
            'jalur': g[0]['Jalur'],
            'kategori': g[0]['Kategori'],
            'subjek': re.sub(r'\s+', ' ', g[0]['Subjek']).strip(),
            'masuk': g[0]['Masuk'][:10],
            'kali': len(g),
            'asal': g[0]['Asal pelapor'],
            'cuplik': re.sub(r'\s+', ' ', g[0].get('Isi laporan', ''))[:400],
        } for g in grup.values()],
    }

    json.dump(data, open(f'{out}/data.json', 'w'), indent=1, ensure_ascii=False)

    ringkas = {k: v for k, v in data.items() if k != 'daftar'}
    print(json.dumps(ringkas, indent=1, ensure_ascii=False))
    print(f'\n{len(grup)} pokok unik dari {n} tiket → {out}/data.json')


if __name__ == '__main__':
    main()
