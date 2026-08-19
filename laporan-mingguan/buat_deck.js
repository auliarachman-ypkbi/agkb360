/**
 * AGKB 360° — Deck laporan mingguan (7 slide).
 *
 * Angka diambil dari data.json (hasil analisis.py); kutipan dan
 * simpulan dari naskah.json (ditulis tiap pekan setelah membaca
 * laporannya). Tata letaknya tetap, jadi deck tiap pekan terlihat
 * seragam dan mudah dibandingkan.
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
p.title = `Laporan Temuan Kanal Feedback — ${naskah.rentang}`;

const OBL='040136', OBL9='02001F', GAL='2201B2', GAL05='EEEBFC', GAL20='B9AEF2';
const CAT='FF9101', CAT05='FFF8EF', CAT30='FFC36B', EMBER='B83A01';
const MERAH='B42318', MER05='FDECEB', MER20='F3B5B0', HIJAU='027A48', HIJ05='E7F6EF';
const BG='F3F4F6', LINE='E3E5EA', BODY='2F2D4D', ABU='6B6A83', PUTIH='FFFFFF';
const F='Calibri', W=13.3, H=7.5;
let NO = 0;
const TOT = 7;

// Warna garis tepi kutipan, berurutan: paling berat lebih dulu.
const NADA = [[MERAH, MER05], [CAT, CAT05], [GAL, GAL05]];

const s0 = () => { const s = p.addSlide(); s.background = { color: BG }; return s; };

function judul(s, t, sub) {
  s.addText(t, { x:.62, y:.38, w:W-2.7, h:.52, fontFace:F, fontSize:27, bold:true, color:OBL, margin:0 });
  if (sub) s.addText(sub, { x:.62, y:.93, w:W-2.7, h:.32, fontFace:F, fontSize:13.5, color:ABU, margin:0 });
}
function kaki(s) {
  NO++;
  s.addText(`AGKB 360°  ·  Laporan Kanal Feedback  ·  ${naskah.rentang}`,
    { x:.62, y:H-.46, w:8, h:.26, fontFace:F, fontSize:9, color:'9B9AB0', margin:0 });
  s.addText(`${NO} / ${TOT}`, { x:W-1.6, y:H-.46, w:1, h:.26, fontFace:F, fontSize:9, color:'9B9AB0', align:'right', margin:0 });
}
function kartu(s, x, y, w, h, isi, tepi) {
  s.addShape(p.ShapeType.roundRect, { x, y, w, h, rectRadius:.09,
    fill:{color:isi||PUTIH}, line:{color:tepi||LINE, width:1} });
}
function kutip(s, x, y, w, teks, sumber, warna, bg) {
  const baris = Math.ceil(teks.length / 92);
  const h = baris * .26 + .62;
  s.addShape(p.ShapeType.rect, { x, y, w:.045, h, fill:{color:warna} });
  s.addShape(p.ShapeType.rect, { x:x+.045, y, w:w-.045, h, fill:{color:bg} });
  s.addText('"' + teks + '"', { x:x+.28, y:y+.14, w:w-.5, h:baris*.26+.1,
    fontFace:F, fontSize:12.5, italic:true, color:BODY, lineSpacing:18, margin:0 });
  s.addText(sumber, { x:x+.28, y:y+h-.4, w:w-.5, h:.26, fontFace:F, fontSize:10, color:ABU, margin:0 });
  return y + h + .16;
}
function poin(s, x, y, w, daftar, uk) {
  let yy = y;
  daftar.forEach(([j, i]) => {
    s.addShape(p.ShapeType.ellipse, { x, y:yy+.08, w:.1, h:.1, fill:{color:CAT} });
    s.addText(j, { x:x+.24, y:yy-.03, w:w-.24, h:.3, fontFace:F, fontSize:uk||14.5, bold:true, color:OBL, margin:0 });
    const bh = Math.ceil(i.length / 96) * .25 + .1;
    s.addText(i, { x:x+.24, y:yy+.26, w:w-.24, h:bh, fontFace:F, fontSize:(uk||14.5)-2.5, color:BODY, lineSpacing:18, margin:0 });
    yy += .32 + bh + .14;
  });
  return yy;
}
function lencana(s, jml) {
  s.addShape(p.ShapeType.roundRect, { x:W-2.5, y:.4, w:1.88, h:.62, rectRadius:.09,
    fill:{color:PUTIH}, line:{color:LINE, width:1} });
  s.addText(`${jml} laporan`, { x:W-2.5, y:.55, w:1.88, h:.32, fontFace:F, fontSize:13,
    bold:true, color:EMBER, align:'center', margin:0 });
}
/** Kotak simpulan. nada: 'netral' | 'kuning' | 'merah'. */
function simpul(s, y, teks, nada) {
  const gaya = {
    kuning: [EMBER, CAT05, CAT30],
    merah:  [MERAH, MER05, MER20],
    netral: [null, PUTIH, LINE],
  }[nada || 'netral'];
  kartu(s, .62, y, W-1.24, 1.0, gaya[1], gaya[2]);
  s.addText(teks, { x:.9, y:y+.2, w:W-1.8, h:.72, fontFace:F, fontSize:13.5,
    bold: !!gaya[0], color: gaya[0] || BODY, lineSpacing:18, margin:0 });
}

// ═══ 1 Judul ═══════════════════════════════════════════════
{
  const s = p.addSlide(); s.background = { color: OBL };
  s.addShape(p.ShapeType.rect, { x:0, y:5.6, w:W, h:1.9, fill:{color:OBL9} });
  s.addText('AGKB 360°  ·  KANAL FEEDBACK', { x:.95, y:1.55, w:10, h:.3, fontFace:F, fontSize:12.5, bold:true, color:CAT30, charSpacing:1, margin:0 });
  s.addText('Laporan Temuan', { x:.95, y:1.95, w:11, h:.9, fontFace:F, fontSize:46, bold:true, color:PUTIH, margin:0 });
  s.addText(naskah.subjudul, { x:.95, y:3.0, w:11, h:.5, fontFace:F, fontSize:20, color:GAL20, margin:0 });
  s.addText(`${data.total} laporan  ·  ${data.unik} pokok berbeda  ·  ${data.jalur['Apresiasi'] || 0} di antaranya apresiasi`,
    { x:.95, y:6.3, w:9, h:.32, fontFace:F, fontSize:13, color:'8A88A8', margin:0 });
  NO++;
}

// ═══ 2 Apa yang dikeluhkan ═════════════════════════════════
{
  const s = s0();
  judul(s, 'Apa yang dikeluhkan', 'Dikelompokkan menurut isi laporan, bukan kategori yang dipilih pelapor');
  lencana(s, data.total);
  s.addImage({ path:path.join(KERJA, 'tema.png'), x:.55, y:1.7, w:6.9, h:3.65 });
  poin(s, 7.7, 1.75, 5.0, naskah.peta.poin, 13.5);
  kartu(s, 7.7, 5.35, 5.0, 1.15, CAT05, CAT30);
  s.addText(naskah.peta.sorot,
    { x:7.95, y:5.55, w:4.5, h:.8, fontFace:F, fontSize:13.5, bold:true, color:EMBER, lineSpacing:18, margin:0 });
  kaki(s);
}

// ═══ 3–6 Empat tema ════════════════════════════════════════
naskah.tema.forEach((t, i) => {
  const s = s0();
  judul(s, `Tema ${i + 1} · ${t.judul}`, t.sub);
  lencana(s, data.tema[t.kunci] ?? 0);
  let y = 1.6;
  t.kutipan.forEach((k, j) => {
    const [warna, bg] = NADA[Math.min(j, NADA.length - 1)];
    y = kutip(s, .62, y, W-1.24, k[0], k[1], warna, bg);
  });
  simpul(s, y + .05, t.simpul, t.nada);
  kaki(s);
});

// ═══ 7 Yang berjalan baik ══════════════════════════════════
{
  const s = s0();
  const jml = data.jalur['Apresiasi'] || 0;
  judul(s, 'Yang berjalan baik juga tercatat', `${jml} laporan masuk melalui jalur apresiasi`);
  lencana(s, jml);
  let y = 1.6;
  naskah.apresiasi.kutipan.forEach(k => {
    y = kutip(s, .62, y, W-1.24, k[0], k[1], HIJAU, HIJ05);
  });
  kartu(s, .62, y + .02, W-1.24, 1.2, HIJ05, '9BD9BB');
  s.addText(naskah.apresiasi.kotak_judul,
    { x:.9, y:y+.16, w:W-1.8, h:.32, fontFace:F, fontSize:14.5, bold:true, color:'014D2E', margin:0 });
  s.addText(naskah.apresiasi.kotak_isi,
    { x:.9, y:y+.52, w:W-1.8, h:.66, fontFace:F, fontSize:12.5, color:'014D2E', lineSpacing:18, margin:0 });
  kaki(s);
}

p.writeFile({ fileName: KELUAR }).then(() => console.log('OK', KELUAR, '·', NO, 'slide'));
