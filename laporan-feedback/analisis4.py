#!/usr/bin/env python3
"""Analisis tiket AGKB 360° — data 18 Agustus 2026."""
import csv, io, re, os, json, collections
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
from wordcloud import WordCloud

D   = os.path.dirname(os.path.abspath(__file__))
SRC = '/sessions/kind-quirky-pasteur/mnt/uploads/tiket-agkb360-baru.csv'

OBL, CAT, MERAH, HIJAU, GAL = '#040136', '#ff9101', '#b42318', '#027a48', '#2201b2'
ABU = '#6b6a83'
plt.rcParams.update({'font.family': 'DejaVu Sans', 'font.size': 11,
    'text.color': OBL, 'figure.facecolor': 'white', 'axes.facecolor': 'white'})

r = list(csv.DictReader(io.StringIO(open(SRC, encoding='utf-8-sig').read())))
r = [x for x in r if '2026-08-10' <= x['Masuk'][:10] <= '2026-08-18']   # rentang 10–18 Agustus
n = len(r)

def polos(ax, sumbu='y'):
    for s in ['top', 'right', 'bottom', 'left']: ax.spines[s].set_visible(False)
    if sumbu == 'y': ax.set_xticks([])
    else: ax.set_yticks([])
    ax.tick_params(length=0)

def hit(k): return collections.Counter(x[k] for x in r if x[k])

isi = lambda x: x['Subjek'] + ' ' + x['Isi laporan']

# ── Tema, dikelompokkan dari isi laporan ────────────────────
tema = {
    'Konektivitas internet':        r'wi.?fi|internet|sinyal|koneksi|router|jaringan',
    'Waktu belajar dan jadwal':     r'waktu belajar|belajar malam|apel|jam belajar|olahraga pagi|pukul \d|jadwal',
    'Layanan dan kondisi asrama':   r'laundry|klinik|perawat|konsumsi|makan|air minum|mbg|dining|kamar mandi|toilet|wastafel|air ',
    'Pembinaan dan kedisiplinan':   r'misconduct|\bsp ?1\b|mentor|etika|kesopanan|house|hukuman|pembinaan|teguran',
    'Kebijakan akademik':           r'absen|kompetisi|osn|ibdp|kurikulum|silabus',
    'Beban kerja dan komunikasi':   r'liburan|tenggat|to.?do|manajemen|dikomunikasikan|slip gaji',
}
temaHit = {t: sum(1 for x in r if re.search(p, isi(x), re.I)) for t, p in tema.items()}

stat = {
    'total': n,
    'dari': min(x['Masuk'] for x in r)[:10],
    'sampai': max(x['Masuk'] for x in r)[:10],
    'jalur': dict(hit('Jalur')),
    'asal': dict(hit('Asal pelapor')),
    'belum_ditugaskan': sum(1 for x in r if x['Penanggung jawab'] == 'Belum ditugaskan'),
    'selesai': sum(1 for x in r if x['Diselesaikan']),
    'ditutup': sum(1 for x in r if x['Status'] == 'Ditutup'),
    'ada_respons': sum(1 for x in r if x['Respons pertama']),
    'anonim': sum(1 for x in r if x['Pelapor'] == 'Pelapor Anonim'),
    'tema': temaHit,
    'bulan': dict(sorted(collections.Counter(x['Masuk'][:7] for x in r).items())),
}

# ── 1 Jalur ─────────────────────────────────────────────────
fig, ax = plt.subplots(figsize=(5.6, 3.3))
nm = ['Kendala / Masukan', 'Kanal Yayasan', 'Apresiasi']
nl = [hit('Jalur').get(x, 0) for x in nm]
b = ax.barh(nm[::-1], nl[::-1], color=[HIJAU, MERAH, OBL], height=.55)
ax.bar_label(b, padding=6, fontsize=13, fontweight='bold', color=OBL)
ax.set_xlim(0, max(nl) * 1.25); polos(ax); ax.tick_params(labelsize=11)
plt.tight_layout(); plt.savefig(f'{D}/p1-jalur.png', dpi=200); plt.close()

# ── 2 Tema ──────────────────────────────────────────────────
fig, ax = plt.subplots(figsize=(6.8, 3.6))
it = sorted(temaHit.items(), key=lambda kv: kv[1])
nm = [k for k, _ in it]; nl = [v for _, v in it]
w = [CAT if v == max(nl) else OBL for v in nl]
b = ax.barh(nm, nl, color=w, height=.6)
ax.bar_label(b, padding=6, fontsize=12, fontweight='bold', color=OBL)
ax.set_xlim(0, max(nl) * 1.28); polos(ax); ax.tick_params(labelsize=10.5)
plt.tight_layout(); plt.savefig(f'{D}/p2-tema.png', dpi=200); plt.close()

# ── 3 Penanganan ────────────────────────────────────────────
fig, ax = plt.subplots(figsize=(5.6, 3.1))
lab = ['Belum ditugaskan', 'Sedang ditangani', 'Tuntas']
val = [stat['belum_ditugaskan'], n - stat['belum_ditugaskan'] - stat['ditutup'], stat['ditutup']]
b = ax.bar(lab, val, color=[MERAH, CAT, HIJAU], width=.5)
ax.bar_label(b, padding=5, fontsize=14, fontweight='bold', color=OBL)
ax.set_ylim(0, max(val) * 1.3); polos(ax, 'x'); ax.tick_params(labelsize=10.5)
plt.tight_layout(); plt.savefig(f'{D}/p3-penanganan.png', dpi=200); plt.close()

# ── 4 Asal ──────────────────────────────────────────────────
a = hit('Asal pelapor')
nm = ['Formulir publik tanpa akun', 'Warga sekolah (punya akun)', 'Anonim']
nl = [a.get('publik', 0), a.get('pengguna', 0), a.get('anonim', 0)]
fig, ax = plt.subplots(figsize=(6.4, 2.8))
b = ax.barh(nm[::-1], nl[::-1], color=[GAL, ABU, CAT][::-1], height=.5)
for rect, v in zip(b, nl[::-1]):
    ax.text(rect.get_width() + max(nl) * .03, rect.get_y() + rect.get_height() / 2,
            f'{v}  ({round(v/n*100)}%)', va='center', fontsize=12, fontweight='bold', color=OBL)
ax.set_xlim(0, max(nl) * 1.5); polos(ax); ax.tick_params(labelsize=11)
plt.tight_layout(); plt.savefig(f'{D}/p4-asal.png', dpi=200); plt.close()

# ── 5 Bulan ─────────────────────────────────────────────────
fig, ax = plt.subplots(figsize=(5.6, 2.8))
nb = {'06': 'Juni', '07': 'Juli', '08': 'Agustus'}
bl = [nb.get(k[5:7], k) for k in stat['bulan']]; vl = list(stat['bulan'].values())
b = ax.bar(bl, vl, color=OBL, width=.45)
ax.bar_label(b, padding=5, fontsize=13, fontweight='bold', color=OBL)
ax.set_ylim(0, max(vl) * 1.3); polos(ax, 'x'); ax.tick_params(labelsize=11)
plt.tight_layout(); plt.savefig(f'{D}/p5-bulan.png', dpi=200); plt.close()

# ── 6 Awan kata ─────────────────────────────────────────────
stop = set('''yang dan di ke dari untuk pada dengan ini itu ada tidak atau juga saya kami kita
akan sudah bisa dapat agar karena tetapi namun jika kalau saat lebih sangat masih belum
oleh para dalam adalah anak siswa siswi guru sekolah nya kan pun kepada bagi seperti
antara setiap semua banyak hal apa mana lain sebagai secara telah harus perlu mohon
terima kasih yth bapak ibu sehingga bahkan hanya saja tersebut mereka dia menjadi
selama setelah sebelum ketika hingga sampai bukan supaya maka sedang berada atas
selalu kalian kembali bahwa the and for that this with would have been are was they
you our not but all can will your from about make more just some what when where
diberikan mengikuti mencari dianggap seharusnya sekitar terkait mengenai adanya
kegiatan orang lalu sana sini situ oleh menjadi merasa sebuah dilakukan'''.split())
teks = ' '.join(isi(x).lower() for x in r)
kata = [w for w in re.findall(r'[a-zA-Z]{4,}', teks) if w not in stop]
gab = {'dormitory': 'dorm', 'wifi': 'sinyal', 'internet': 'sinyal',
       'connection': 'sinyal', 'koneksi': 'sinyal', 'jaringan': 'sinyal'}
freq = collections.Counter()
for w, c in collections.Counter(kata).items(): freq[gab.get(w, w)] += c
stat['kata'] = freq.most_common(30)

WordCloud(width=1600, height=900, background_color='white', prefer_horizontal=.92,
          max_words=70, relative_scaling=.45, min_font_size=13,
          color_func=lambda *a, **k: [OBL, CAT, GAL, MERAH, HIJAU][hash(a[0]) % 5]
         ).generate_from_frequencies(dict(freq.most_common(70))).to_file(f'{D}/p6-awankata.png')

# ── Daftar unik ─────────────────────────────────────────────
def norm(s): return re.sub(r'\W+', ' ', s.lower()).strip()
grup = collections.OrderedDict()
for x in sorted(r, key=lambda y: y['Masuk']): grup.setdefault(norm(x['Subjek']), []).append(x)

daftar = [{
    'jalur': g[0]['Jalur'].replace('Kendala / Masukan', 'Kendala'),
    'kategori': g[0]['Kategori'].replace('Sarana & Prasarana', 'Sarana & Fasilitas'),
    'subjek': re.sub(r'\s+', ' ', g[0]['Subjek']).strip(),
    'kali': len(g), 'masuk': g[0]['Masuk'][:10],
} for g in grup.values()]

stat['unik'] = len(daftar)
json.dump({'stat': stat, 'daftar': daftar}, open(f'{D}/data-agustus.json', 'w'), indent=1, ensure_ascii=False)
print(json.dumps({k: v for k, v in stat.items() if k != 'kata'}, indent=1, ensure_ascii=False))
print('unik:', len(daftar))
print('kata:', [w for w, _ in stat['kata'][:18]])
