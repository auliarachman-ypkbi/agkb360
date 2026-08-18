/**
 * AGKB 360° — Laporan Temuan Kanal Feedback
 * Rentang 10–18 Agustus 2026. Ringkas: peta tema, empat tema, apresiasi.
 */
const pptxgen = require('pptxgenjs');
const fs = require('fs');
const path = require('path');
const D = __dirname;
const { stat } = JSON.parse(fs.readFileSync(path.join(D, 'data-agustus.json'), 'utf8'));

const p = new pptxgen();
p.layout = 'LAYOUT_WIDE';
p.author = 'AGKB 360°';
p.title = 'Laporan Temuan Kanal Feedback — 10–18 Agustus 2026';

const OBL='040136', OBL9='02001F', GAL='2201B2', GAL05='EEEBFC', GAL20='B9AEF2';
const CAT='FF9101', CAT05='FFF8EF', CAT30='FFC36B', EMBER='B83A01';
const MERAH='B42318', MER05='FDECEB', MER20='F3B5B0', HIJAU='027A48', HIJ05='E7F6EF';
const BG='F3F4F6', LINE='E3E5EA', BODY='2F2D4D', ABU='6B6A83', PUTIH='FFFFFF';
const F='Calibri', W=13.3, H=7.5;
let NO = 0;
const TOT = 7;
const RENTANG = '10 – 18 Agustus 2026';

const s0 = () => { const s = p.addSlide(); s.background = { color: BG }; return s; };

function judul(s, t, sub) {
  s.addText(t, { x:.62, y:.38, w:W-2.7, h:.52, fontFace:F, fontSize:27, bold:true, color:OBL, margin:0 });
  if (sub) s.addText(sub, { x:.62, y:.93, w:W-2.7, h:.32, fontFace:F, fontSize:13.5, color:ABU, margin:0 });
}
function kaki(s) {
  NO++;
  s.addText(`AGKB 360°  ·  Laporan Kanal Feedback  ·  ${RENTANG}`,
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
/** Lencana jumlah laporan di kanan atas. */
function lencana(s, jml) {
  s.addShape(p.ShapeType.roundRect, { x:W-2.5, y:.4, w:1.88, h:.62, rectRadius:.09,
    fill:{color:'FFFFFF'}, line:{color:LINE, width:1} });
  s.addText(`${jml} laporan`, { x:W-2.5, y:.55, w:1.88, h:.32, fontFace:F, fontSize:13,
    bold:true, color:EMBER, align:'center', margin:0 });
}
/** Kotak simpulan di bawah kutipan. */
function simpul(s, y, teks, warna, bg, tepi) {
  kartu(s, .62, y, W-1.24, 1.0, bg || PUTIH, tepi || LINE);
  s.addText(teks, { x:.9, y:y+.2, w:W-1.8, h:.72, fontFace:F, fontSize:13.5,
    bold: !!warna, color: warna || BODY, lineSpacing:18, margin:0 });
}

// ═══ 1 Judul ═══════════════════════════════════════════════
{
  const s = p.addSlide(); s.background = { color: OBL };
  s.addShape(p.ShapeType.rect, { x:0, y:5.6, w:W, h:1.9, fill:{color:OBL9} });
  s.addText('AGKB 360°  ·  KANAL FEEDBACK', { x:.95, y:1.55, w:10, h:.3, fontFace:F, fontSize:12.5, bold:true, color:CAT30, charSpacing:1, margin:0 });
  s.addText('Laporan Temuan', { x:.95, y:1.95, w:11, h:.9, fontFace:F, fontSize:46, bold:true, color:PUTIH, margin:0 });
  s.addText('Apa yang disampaikan warga sekolah pada 10–18 Agustus 2026', { x:.95, y:3.0, w:11, h:.5, fontFace:F, fontSize:20, color:GAL20, margin:0 });
  s.addText(`${stat.total} laporan  ·  ${stat.unik} pokok berbeda  ·  ${stat.jalur['Apresiasi']} di antaranya apresiasi`,
    { x:.95, y:6.3, w:9, h:.32, fontFace:F, fontSize:13, color:'8A88A8', margin:0 });
  NO++;
}

// ═══ 2 Peta tema ═══════════════════════════════════════════
{
  const s = s0();
  judul(s, 'Apa yang dikeluhkan', 'Dikelompokkan menurut isi laporan, bukan kategori yang dipilih pelapor');
  lencana(s, stat.total);
  s.addImage({ path:path.join(D,'p2-tema.png'), x:.55, y:1.7, w:6.9, h:3.65 });
  poin(s, 7.7, 1.75, 5.0, [
    ['Satu laporan bisa masuk beberapa tema',
     `Karena itu jumlahnya melebihi ${stat.total}. Angka ini menunjukkan seberapa sering suatu hal disinggung, bukan berapa tiketnya.`],
    ['Empat tema besar saling terkait',
     'Pembinaan, kondisi asrama, internet, dan waktu belajar sering muncul dalam satu laporan yang sama — semuanya bermuara pada hari yang terasa terlalu padat.'],
  ], 13.5);
  kartu(s, 7.7, 5.35, 5.0, 1.15, CAT05, CAT30);
  s.addText('Tema terbesar bukan fasilitas, melainkan cara siswa dibina dan didisiplinkan.',
    { x:7.95, y:5.55, w:4.5, h:.8, fontFace:F, fontSize:13.5, bold:true, color:EMBER, lineSpacing:18, margin:0 });
  kaki(s);
}

// ═══ 3 Tema 1 — Pembinaan dan kedisiplinan ═════════════════
{
  const s = s0();
  judul(s, 'Tema 1 · Pembinaan dan kedisiplinan', 'Bukan menolak aturan, melainkan mempersoalkan cara dan ukurannya');
  lencana(s, stat.tema['Pembinaan dan kedisiplinan']);
  let y = 1.6;
  y = kutip(s, .62, y, W-1.24,
    'Kami tetap dikategorikan sebagai terlambat dan diberikan hukuman berupa push-up sebanyak 20 kali … mentor juga menyampaikan kalimat yang menurut saya tidak pantas.',
    'KY-2026-0011 · Kanal Yayasan · anonim · 17 Agustus', MERAH, MER05);
  y = kutip(s, .62, y, W-1.24,
    'Kami diberitahu bahwa kami telah terjerat misconduct … Sedangkan, ada murid yang diberi kesempatan untuk memperbaiki pekerjaannya. Menurut kami ini sangat tidak adil.',
    'AGKB-2026-0030 · Akademik · 15 Agustus', CAT, CAT05);
  y = kutip(s, .62, y, W-1.24,
    'Beberapa siswa dari House yang menang melakukan ejekan terhadap House yang kalah.',
    'AGKB-2026-0032 · Kesiswaan & Kedisiplinan · 16 Agustus', GAL, GAL05);
  simpul(s, y + .05,
    'Benang merahnya: sanksi dianggap tidak seragam antar kelompok mentor dan tidak didahului peringatan. Yang diminta bukan penghapusan aturan, melainkan kejelasan dan kesetaraan penerapannya.');
  kaki(s);
}

// ═══ 4 Tema 2 — Waktu belajar ══════════════════════════════
{
  const s = s0();
  judul(s, 'Tema 2 · Waktu belajar yang terus terpotong', 'Tema yang baru muncul pada pekan ini');
  lencana(s, stat.tema['Waktu belajar dan jadwal']);
  let y = 1.6;
  y = kutip(s, .62, y, W-1.24,
    'Residential mengadakan apel malam tiap hari … waktu tidur jam 10 malam, sedangkan apel dilaksanakan jam 9.30. Secara instan, itu akan memotong waktu belajar murid-murid.',
    'Dilaporkan 4 kali · Kesiswaan & Kedisiplinan · 17–18 Agustus', MERAH, MER05);
  y = kutip(s, .62, y, W-1.24,
    'Olahraga pagi 05.00–05.50, pukul 06.10 sudah harus di sekolah, sekolah sampai 15.10, lalu marching band 15.45–17.45. Praktis tidak ada waktu mengerjakan tugas.',
    'KY-2026-0009 · Kehidupan Asrama · anonim · 15 Agustus', CAT, CAT05);
  y = kutip(s, .62, y, W-1.24,
    'Beban kurikulum seperti Internal Assessment, Extended Essay, dan CAS menuntut waktu pendalaman yang tidak bisa dipenuhi hanya dari jam pelajaran siang.',
    'AGKB-2026-0031 · siswa kelas 11 IBDP · 15 Agustus', GAL, GAL05);
  simpul(s, y + .05,
    'Tiga kebijakan yang masing-masing wajar — olahraga pagi, apel malam, kegiatan sore — bertemu pada satu hari yang sama dan menyisakan sedikit sekali waktu belajar mandiri.',
    EMBER, CAT05, CAT30);
  kaki(s);
}

// ═══ 5 Tema 3 — Asrama ═════════════════════════════════════
{
  const s = s0();
  judul(s, 'Tema 3 · Layanan dan kondisi asrama', 'Dari klinik dan konsumsi sampai kebutuhan dasar sehari-hari');
  lencana(s, stat.tema['Layanan dan kondisi asrama']);
  let y = 1.6;
  y = kutip(s, .62, y, W-1.24,
    'Saya malah disambut dengan pertanyaan tajam dan nada menyalahkan … bagaimana mungkin siswa mau terbuka mengenai kesehatannya jika tenaga medisnya sendiri bersikap antipati?',
    'KY-2026-0008 · Kanal Yayasan · anonim · 13 Agustus', MERAH, MER05);
  y = kutip(s, .62, y, W-1.24,
    'Aliran air di wastafel maupun toilet sangat kecil … bukan kejadian sekali dua kali, melainkan hampir selalu terjadi.',
    'AGKB-2026-0028 · Sarana & Fasilitas · 14 Agustus', CAT, CAT05);
  y = kutip(s, .62, y, W-1.24,
    'Waktu kosong saat di asrama menjadi monoton … fasilitas yang masih dalam proses pembangunan sehingga mereka jenuh.',
    'AGKB-2026-0033 · Sarana & Fasilitas · 16 Agustus', GAL, GAL05);
  simpul(s, y + .05,
    'Sebagian besar bukan keluhan tentang barang yang belum ada, melainkan tentang tidak jelasnya siapa yang bertanggung jawab ketika sesuatu tidak berjalan.');
  kaki(s);
}

// ═══ 6 Tema 4 — Internet ═══════════════════════════════════
{
  const s = s0();
  judul(s, 'Tema 4 · Konektivitas internet', 'Keluhan paling banyak jumlahnya, dan mulai berubah nada');
  lencana(s, stat.tema['Konektivitas internet']);
  let y = 1.6;
  y = kutip(s, .62, y, W-1.24,
    'Sudah sebulan di KTB wifi di dorm masih buruk di banyak tempat, katanya akan diperbaiki tapi seperti tidak ada perubahan.',
    'AGKB-2026-0034 · 17 Agustus — laporan terbaru dari rangkaian ini', MERAH, MER05);
  y = kutip(s, .62, y, W-1.24,
    'Kamar saya B203 tidak mendapatkan sinyal sama sekali dari hari pertama … sinyal wifi hanya bekerja di Pantry dan Library yang terbuka, di mana itu mengganggu privasi kami.',
    'AGKB-2026-0011 · Sarana & Fasilitas · 12 Agustus', CAT, CAT05);
  y = kutip(s, .62, y, W-1.24,
    'Para guru pun merasakannya; saat mau menghubungkan laptop dengan smartboard yang membutuhkan sinyal, akhirnya tidak digunakan karena sinyal tidak ada.',
    'AGKB-2026-0016 · dampak pada kegiatan mengajar · 12 Agustus', GAL, GAL05);
  simpul(s, y + .05,
    'Perhatikan perubahan nadanya. Laporan 12 Agustus masih menjelaskan masalah; laporan 17 Agustus sudah mempersoalkan janji perbaikan yang belum terlihat hasilnya.',
    MERAH, MER05, MER20);
  kaki(s);
}

// ═══ 7 Yang berjalan baik ══════════════════════════════════
{
  const s = s0();
  judul(s, 'Yang berjalan baik juga tercatat', `${stat.jalur['Apresiasi']} laporan masuk melalui jalur apresiasi`);
  lencana(s, stat.jalur['Apresiasi']);
  let y = 1.6;
  y = kutip(s, .62, y, W-1.24,
    'Setengah tahun lalu saya memberi masukan agar tim finance dapat memberikan slip gaji karyawan tiap bulan. Hal ini akhirnya sudah diwujudnyatakan … Terima kasih sudah mendengarkan saran kecil ini.',
    'AGKB-2026-0029 · Apresiasi Umum · 14 Agustus', HIJAU, HIJ05);
  y = kutip(s, .62, y, W-1.24,
    'Di tengah hiruk-pikuk cuaca dan debu di lingkungan ini kalian masih bekerja dengan sepenuh hati dan memiliki kinerja yang sangat baik.',
    'AGKB-2026-0021 · Apresiasi Guru / Staf · 13 Agustus', HIJAU, HIJ05);
  y = kutip(s, .62, y, W-1.24,
    'Saya senang saat menuju kamar mandi tidak harus takut ada yang meneriakkan untuk masuk kelas.',
    'AGKB-2026-0025 · Apresiasi Umum · 13 Agustus', HIJAU, HIJ05);
  kartu(s, .62, y + .02, W-1.24, 1.2, HIJ05, '9BD9BB');
  s.addText('Laporan pertama adalah bukti paling berharga dalam periode ini.',
    { x:.9, y:y+.16, w:W-1.8, h:.32, fontFace:F, fontSize:14.5, bold:true, color:'014D2E', margin:0 });
  s.addText('Masukan disampaikan, dikerjakan, lalu pelapornya kembali untuk mengatakannya. Lingkaran umpan balik yang tertutup seperti inilah yang membuat orang mau melapor lagi.',
    { x:.9, y:y+.52, w:W-1.8, h:.66, fontFace:F, fontSize:12.5, color:'014D2E', lineSpacing:18, margin:0 });
  kaki(s);
}

const out = path.join(D, 'Laporan-Temuan-10-18-Agustus.pptx');
p.writeFile({ fileName: out }).then(() => console.log('OK', out, '| slide:', NO));
