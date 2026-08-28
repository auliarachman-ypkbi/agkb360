/**
 * AGKB 360° — Deck laporan (7 slide).
 *
 * Angka dari data.json (hasil analisis.py); kutipan dan simpulan
 * dari naskah.json (ditulis setiap kali setelah membaca laporannya).
 * Tata letaknya tetap, jadi deck antar-periode mudah dibandingkan.
 *
 * Pakai:
 *   node buat_deck.js <folder-kerja> <berkas-keluaran.pptx>
 */
const pptxgen = require('pptxgenjs');
const fs = require('fs');
const path = require('path');

const KERJA = process.argv[2];
const KELUAR = process.argv[3];
if (!KERJA || !KELUAR) {
  console.error('Pakai: node buat_deck.js <folder-kerja> <keluaran.pptx>');
  process.exit(1);
}

const data = JSON.parse(fs.readFileSync(path.join(KERJA, 'data.json'), 'utf8'));
const naskah = JSON.parse(fs.readFileSync(path.join(KERJA, 'naskah.json'), 'utf8'));

const p = new pptxgen();
p.layout = 'LAYOUT_WIDE';
p.author = 'AGKB 360°';
p.title = `Laporan Kanal Feedback — ${naskah.rentang}`;

const OBL='040136', OBL9='02001F', GAL='2201B2', GAL05='EEEBFC', GAL20='B9AEF2';
const CAT='FF9101', CAT05='FFF8EF', CAT30='FFC36B', EMBER='B83A01';
const MERAH='B42318', MER05='FDECEB', MER20='F3B5B0', HIJAU='027A48', HIJ05='E7F6EF';
const BG='F3F4F6', LINE='E3E5EA', BODY='2F2D4D', ABU='6B6A83', PUTIH='FFFFFF';

/** Lato di seluruh deck. Harus terpasang di mesin yang membuka. */
const F = 'Lato';
const W = 13.3, H = 7.5;

let NO = 0;
const TOT = 7;

/** Warna garis tepi kutipan, berurutan: paling berat lebih dulu. */
const NADA = [[MERAH, MER05], [CAT, CAT05], [GAL, GAL05]];

const s0 = () => { const s = p.addSlide(); s.background = { color: BG }; return s; };

// ── Ukur teks ───────────────────────────────────────────────
// Ditera dari tata letak yang sudah terbukti rapi: pada lebar
// 12,06 inci dengan huruf 12,5 pt, satu baris memuat 92 aksara.
// Dari situ: aksara ≈ lebar × 95 ÷ ukuran huruf.
const KALIBRASI = 95;

function baris(teks, lebar, ukuran) {
  return Math.max(1, Math.ceil(teks.length / (lebar * KALIBRASI / ukuran)));
}
/** Tinggi satu baris dalam inci, termasuk jarak antarbaris. */
function tinggiBaris(ukuran) {
  return ukuran * 1.42 / 72;
}

// ── Elemen ──────────────────────────────────────────────────

function judul(s, t, sub) {
  s.addText(t, { x:.62, y:.34, w:W-2.7, h:.56, fontFace:F, fontSize:28,
    bold:true, color:OBL, margin:0 });
  if (sub) s.addText(sub, { x:.62, y:.95, w:W-2.7, h:.34, fontFace:F,
    fontSize:15, color:ABU, margin:0 });
}

function kaki(s) {
  NO++;
  s.addText(`AGKB 360°  ·  Laporan Kanal Feedback  ·  ${naskah.rentang}`,
    { x:.62, y:H-.46, w:8, h:.28, fontFace:F, fontSize:10.5, color:'9B9AB0', margin:0 });
  s.addText(`${NO} / ${TOT}`, { x:W-1.6, y:H-.46, w:1, h:.28, fontFace:F,
    fontSize:10.5, color:'9B9AB0', align:'right', margin:0 });
}

function kartu(s, x, y, w, h, isi, tepi) {
  s.addShape(p.ShapeType.roundRect, { x, y, w, h, rectRadius:.09,
    fill:{color:isi||PUTIH}, line:{color:tepi||LINE, width:1} });
}

/** Kutipan verbatim: garis warna di kiri, teks miring, sumber di bawah. */
function kutip(s, x, y, w, teks, sumber, warna, bg) {
  const uk = 14, lebar = w - .55;
  const n = baris('"' + teks + '"', lebar, uk);
  const th = n * tinggiBaris(uk) + .54;

  s.addShape(p.ShapeType.rect, { x, y, w:.05, h:th, fill:{color:warna} });
  s.addShape(p.ShapeType.rect, { x:x+.05, y, w:w-.05, h:th, fill:{color:bg} });
  s.addText('"' + teks + '"', { x:x+.3, y:y+.15, w:lebar, h:n*tinggiBaris(uk)+.06,
    fontFace:F, fontSize:uk, italic:true, color:BODY, lineSpacing:uk*1.42, margin:0 });
  s.addText(sumber, { x:x+.3, y:y+th-.31, w:lebar, h:.26,
    fontFace:F, fontSize:11.5, color:ABU, margin:0 });
  return y + th + .10;
}

/** Daftar berbutir: judul tebal, keterangan di bawahnya. */
function poin(s, x, y, w, daftar, uk) {
  uk = uk || 15.5;
  const ukIsi = uk - 2;
  const lebar = w - .3;
  let yy = y;

  daftar.forEach(([j, i]) => {
    s.addShape(p.ShapeType.ellipse, { x, y:yy+.1, w:.11, h:.11, fill:{color:CAT} });
    s.addText(j, { x:x+.28, y:yy-.04, w:lebar, h:.32, fontFace:F, fontSize:uk,
      bold:true, color:OBL, margin:0 });
    const n = baris(i, lebar, ukIsi);
    const bh = n * tinggiBaris(ukIsi) + .08;
    s.addText(i, { x:x+.28, y:yy+.3, w:lebar, h:bh, fontFace:F, fontSize:ukIsi,
      color:BODY, lineSpacing:ukIsi*1.42, margin:0 });
    yy += .36 + bh + .16;
  });
  return yy;
}

/** Lencana jumlah laporan di kanan atas. */
function lencana(s, jml) {
  s.addShape(p.ShapeType.roundRect, { x:W-2.5, y:.36, w:1.88, h:.66, rectRadius:.09,
    fill:{color:PUTIH}, line:{color:LINE, width:1} });
  s.addText(`${jml} laporan`, { x:W-2.5, y:.53, w:1.88, h:.34, fontFace:F,
    fontSize:14, bold:true, color:EMBER, align:'center', margin:0 });
}

/** Kotak simpulan di bawah kutipan. nada: netral | kuning | merah. */
function simpul(s, y, teks, nada) {
  const gaya = {
    kuning: [EMBER, CAT05, CAT30],
    merah:  [MERAH, MER05, MER20],
    netral: [null, PUTIH, LINE],
  }[nada || 'netral'];

  const uk = 14.5, lebar = W - 1.8;
  const n = baris(teks, lebar, uk);
  const th = n * tinggiBaris(uk) + .36;

  kartu(s, .62, y, W-1.24, th, gaya[1], gaya[2]);
  s.addText(teks, { x:.9, y:y+.17, w:lebar, h:n*tinggiBaris(uk)+.06,
    fontFace:F, fontSize:uk, bold: !!gaya[0], color: gaya[0] || BODY,
    lineSpacing:uk*1.42, margin:0 });
}

// ═══ 1 Judul ═══════════════════════════════════════════════
{
  const s = p.addSlide(); s.background = { color: OBL };
  s.addShape(p.ShapeType.rect, { x:0, y:5.6, w:W, h:1.9, fill:{color:OBL9} });
  s.addText('AGKB 360°  ·  KANAL FEEDBACK', { x:.95, y:1.5, w:10, h:.32,
    fontFace:F, fontSize:14, bold:true, color:CAT30, charSpacing:1, margin:0 });
  s.addText('Laporan Temuan', { x:.95, y:1.92, w:11, h:.95,
    fontFace:F, fontSize:48, bold:true, color:PUTIH, margin:0 });
  s.addText(naskah.subjudul, { x:.95, y:3.02, w:11, h:.55,
    fontFace:F, fontSize:21, color:GAL20, margin:0 });
  s.addText(`${data.total} laporan  ·  ${data.unik} pokok berbeda  ·  ${data.jalur['Apresiasi'] || 0} di antaranya apresiasi`,
    { x:.95, y:6.28, w:10, h:.34, fontFace:F, fontSize:14, color:'8A88A8', margin:0 });
  NO++;
}

// ═══ 2 Apa yang dikeluhkan ═════════════════════════════════
{
  const s = s0();
  judul(s, 'Apa yang dikeluhkan',
        'Dikelompokkan menurut isi laporan, bukan kategori yang dipilih pelapor');
  lencana(s, data.total);
  s.addImage({ path:path.join(KERJA, 'tema.png'), x:.55, y:1.72, w:7.0, h:3.8 });
  poin(s, 7.8, 1.78, 4.9, naskah.peta.poin, 14.5);

  const ukS = 14.5, lebarS = 4.5;
  const nS = baris(naskah.peta.sorot, lebarS, ukS);
  const thS = nS * tinggiBaris(ukS) + .42;
  kartu(s, 7.8, 6.45 - thS, 4.9, thS, CAT05, CAT30);
  s.addText(naskah.peta.sorot, { x:8.05, y:6.65 - thS, w:lebarS,
    h:nS*tinggiBaris(ukS)+.06, fontFace:F, fontSize:ukS, bold:true,
    color:EMBER, lineSpacing:ukS*1.42, margin:0 });
  kaki(s);
}

// ═══ 3–6 Empat tema ════════════════════════════════════════
naskah.tema.forEach((t, i) => {
  const s = s0();
  judul(s, `Tema ${i + 1} · ${t.judul}`, t.sub);
  lencana(s, data.tema[t.kunci] ?? 0);
  let y = 1.50;
  t.kutipan.forEach((k, j) => {
    const [warna, bg] = NADA[Math.min(j, NADA.length - 1)];
    y = kutip(s, .62, y, W-1.24, k[0], k[1], warna, bg);
  });
  simpul(s, y + .04, t.simpul, t.nada);
  kaki(s);
});

// ═══ 7 Yang berjalan baik ══════════════════════════════════
{
  const s = s0();
  const jml = data.jalur['Apresiasi'] || 0;
  judul(s, 'Yang berjalan baik juga tercatat',
        `${jml} laporan masuk melalui jalur apresiasi`);
  lencana(s, jml);

  let y = 1.50;
  naskah.apresiasi.kutipan.forEach(k => {
    y = kutip(s, .62, y, W-1.24, k[0], k[1], HIJAU, HIJ05);
  });

  const ukJ = 15, ukI = 13.5, lebar = W - 1.8;
  const nI = baris(naskah.apresiasi.kotak_isi, lebar, ukI);
  const th = .42 + nI * tinggiBaris(ukI) + .14;

  kartu(s, .62, y + .04, W-1.24, th, HIJ05, '9BD9BB');
  s.addText(naskah.apresiasi.kotak_judul, { x:.9, y:y+.18, w:lebar, h:.34,
    fontFace:F, fontSize:ukJ, bold:true, color:'014D2E', margin:0 });
  s.addText(naskah.apresiasi.kotak_isi, { x:.9, y:y+.56, w:lebar,
    h:nI*tinggiBaris(ukI)+.06, fontFace:F, fontSize:ukI, color:'014D2E',
    lineSpacing:ukI*1.42, margin:0 });
  kaki(s);
}

p.writeFile({ fileName: KELUAR }).then(() => console.log('OK', KELUAR, '·', NO, 'slide'));
