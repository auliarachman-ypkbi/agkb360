/**
 * AGKB 360° — Laporan Temuan Kanal Feedback
 * Data 19 Juni – 18 Agustus 2026 (48 laporan).
 */
const pptxgen = require('pptxgenjs');
const fs = require('fs');
const path = require('path');
const D = __dirname;
const { stat, daftar } = JSON.parse(fs.readFileSync(path.join(D, 'data.json'), 'utf8'));

const p = new pptxgen();
p.layout = 'LAYOUT_WIDE';
p.author = 'AGKB 360°';
p.title = 'Laporan Temuan Kanal Feedback';

const OBL='040136', OBL9='02001F', GAL='2201B2', GAL05='EEEBFC', GAL20='B9AEF2';
const CAT='FF9101', CAT05='FFF8EF', CAT30='FFC36B', EMBER='B83A01';
const MERAH='B42318', MER05='FDECEB', MER20='F3B5B0', HIJAU='027A48', HIJ05='E7F6EF';
const BG='F3F4F6', LINE='E3E5EA', BODY='2F2D4D', ABU='6B6A83', PUTIH='FFFFFF';
const F='Calibri', W=13.3, H=7.5;
let NO = 0;
const TOT = 21;

const s0 = () => { const s = p.addSlide(); s.background = { color: BG }; return s; };

function judul(s, t, sub) {
  s.addText(t, { x:.62, y:.38, w:W-1.6, h:.52, fontFace:F, fontSize:27, bold:true, color:OBL, margin:0 });
  if (sub) s.addText(sub, { x:.62, y:.93, w:W-1.6, h:.32, fontFace:F, fontSize:13.5, color:ABU, margin:0 });
}
function kaki(s) {
  NO++;
  s.addText('AGKB 360°  ·  Laporan Kanal Feedback  ·  19 Juni – 18 Agustus 2026',
    { x:.62, y:H-.46, w:8, h:.26, fontFace:F, fontSize:9, color:'9B9AB0', margin:0 });
  s.addText(`${NO} / ${TOT}`, { x:W-1.6, y:H-.46, w:1, h:.26, fontFace:F, fontSize:9, color:'9B9AB0', align:'right', margin:0 });
}
function kartu(s, x, y, w, h, isi, tepi) {
  s.addShape(p.ShapeType.roundRect, { x, y, w, h, rectRadius:.09,
    fill:{color:isi||PUTIH}, line:{color:tepi||LINE, width:1} });
}
function angka(s, x, y, w, n, lbl, warna) {
  kartu(s, x, y, w, 1.2);
  s.addText(String(n), { x:x+.2, y:y+.14, w:w-.4, h:.58, fontFace:F, fontSize:32, bold:true, color:warna||OBL, margin:0 });
  s.addText(lbl, { x:x+.2, y:y+.74, w:w-.4, h:.32, fontFace:F, fontSize:11, color:ABU, margin:0 });
}
/** Kutipan verbatim dari laporan. */
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
/** Kepala tema: nomor besar + judul + jumlah laporan. */
function tema(s, n, jml) {
  s.addShape(p.ShapeType.roundRect, { x:W-2.5, y:.4, w:1.88, h:.62, rectRadius:.09,
    fill:{color:'FFFFFF'}, line:{color:LINE, width:1} });
  s.addText(`${jml} laporan`, { x:W-2.5, y:.55, w:1.88, h:.32, fontFace:F, fontSize:13,
    bold:true, color:EMBER, align:'center', margin:0 });
}

// ═══ 1 Judul ═══════════════════════════════════════════════
{
  const s = p.addSlide(); s.background = { color: OBL };
  s.addShape(p.ShapeType.rect, { x:0, y:5.6, w:W, h:1.9, fill:{color:OBL9} });
  s.addText('AGKB 360°  ·  KANAL FEEDBACK', { x:.95, y:1.55, w:10, h:.3, fontFace:F, fontSize:12.5, bold:true, color:CAT30, charSpacing:1, margin:0 });
  s.addText('Laporan Temuan', { x:.95, y:1.95, w:11, h:.9, fontFace:F, fontSize:46, bold:true, color:PUTIH, margin:0 });
  s.addText('Apa yang sebenarnya disampaikan warga sekolah', { x:.95, y:3.0, w:11, h:.5, fontFace:F, fontSize:20, color:GAL20, margin:0 });
  s.addText(`19 Juni – 18 Agustus 2026  ·  ${stat.total} laporan  ·  ${stat.unik} pokok berbeda`,
    { x:.95, y:6.3, w:9, h:.32, fontFace:F, fontSize:13, color:'8A88A8', margin:0 });
  NO++;
}

// ═══ 2 Gambaran umum ═══════════════════════════════════════
{
  const s = s0();
  judul(s, 'Gambaran umum', `${stat.total} laporan masuk, ${stat.unik} di antaranya pokok yang berbeda`);
  angka(s, .62, 1.55, 2.86, stat.total, 'Laporan masuk', OBL);
  angka(s, 3.66, 1.55, 2.86, stat.unik, 'Pokok berbeda', OBL);
  angka(s, 6.70, 1.55, 2.86, stat.jalur['Apresiasi'], 'Berisi apresiasi', HIJAU);
  angka(s, 9.74, 1.55, 2.94, stat.ditutup, 'Sudah tuntas', MERAH);

  s.addImage({ path:path.join(D,'n1-jalur.png'), x:.62, y:3.05, w:5.8, h:3.42 });
  s.addImage({ path:path.join(D,'n5-bulan.png'), x:6.85, y:3.4, w:5.68, h:2.84 });
  s.addText('Sebaran jalur', { x:.62, y:2.9, w:5, h:.26, fontFace:F, fontSize:11.5, bold:true, color:ABU, margin:0 });
  s.addText('Laporan per bulan', { x:6.85, y:2.9, w:5, h:.26, fontFace:F, fontSize:11.5, bold:true, color:ABU, margin:0 });
  kaki(s);
}

// ═══ 3 Konteks lonjakan ════════════════════════════════════
{
  const s = s0();
  judul(s, 'Lonjakan Agustus bukan karena masalah bertambah', 'Melainkan karena kanalnya baru terbuka');
  kartu(s, .62, 1.6, W-1.24, 1.35, GAL05, GAL20);
  s.addText(`${stat.bulan['2026-08']} dari ${stat.total} laporan masuk pada Agustus, bulan formulir publik dibuka dan diumumkan lewat email.`,
    { x:.95, y:1.82, w:W-1.9, h:.4, fontFace:F, fontSize:16.5, bold:true, color:'030870', margin:0 });
  s.addText('Juni dan Juli hanya menghasilkan 8 laporan, seluruhnya dari warga sekolah yang sudah punya akun.',
    { x:.95, y:2.28, w:W-1.9, h:.45, fontFace:F, fontSize:13.5, color:'030870', margin:0 });
  poin(s, .62, 3.25, W-1.24, [
    ['Keluhan sebelumnya tidak hilang, hanya tidak tercatat',
     'Beberapa laporan menyebut persoalannya berlangsung "sejak hari pertama" dan "sudah sebulan". Yang berubah adalah tersedianya tempat untuk menyampaikannya.'],
    ['Mayoritas laporan kini datang dari formulir publik',
     `${stat.asal.publik} dari ${stat.total} dikirim tanpa akun, sebagian besar oleh siswa. Tanpa jalur itu, suara mereka tidak akan masuk sama sekali ke dalam catatan sekolah.`],
    ['Angka dua bulan ini belum bisa jadi tolok ukur',
     'Masih tercampur masa perkenalan kanal. Ukuran yang dapat dipercaya baru muncul setelah satu bulan penuh dengan kanal yang sudah menjadi kebiasaan.'],
  ]);
  kaki(s);
}

// ═══ 4 Peta tema ═══════════════════════════════════════════
{
  const s = s0();
  judul(s, 'Apa yang sebenarnya dikeluhkan', 'Dikelompokkan menurut isi laporan, bukan kategori yang dipilih pelapor');
  s.addImage({ path:path.join(D,'n2-tema.png'), x:.55, y:1.7, w:6.9, h:3.65 });
  const t = stat.tema;
  poin(s, 7.7, 1.75, 5.0, [
    ['Satu laporan bisa masuk beberapa tema',
     'Karena itu jumlahnya melebihi 48. Angka ini menunjukkan seberapa sering suatu hal disinggung, bukan berapa tiketnya.'],
    ['Empat tema besar saling terkait',
     'Pembinaan, waktu belajar, kondisi asrama, dan internet muncul berulang dalam satu laporan yang sama — semuanya bermuara pada hari yang terasa terlalu padat.'],
  ], 13.5);
  kartu(s, 7.7, 5.35, 5.0, 1.15, CAT05, CAT30);
  s.addText('Tema terbesar bukan fasilitas, melainkan cara siswa dibina dan didisiplinkan.',
    { x:7.95, y:5.55, w:4.5, h:.8, fontFace:F, fontSize:13.5, bold:true, color:EMBER, lineSpacing:18, margin:0 });
  kaki(s);
}

// ═══ 5 Tema — Pembinaan dan kedisiplinan ═══════════════════
{
  const s = s0();
  judul(s, 'Tema 1 · Pembinaan dan kedisiplinan', 'Bukan menolak aturan, melainkan mempersoalkan cara dan ukurannya');
  tema(s, 1, stat.tema['Pembinaan dan kedisiplinan']);
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
  kartu(s, .62, y+.05, W-1.24, 1.0);
  s.addText('Benang merahnya: sanksi dianggap tidak seragam antar kelompok mentor dan tidak didahului peringatan. Yang diminta bukan penghapusan aturan, melainkan kejelasan dan kesetaraan penerapannya.',
    { x:.9, y:y+.22, w:W-1.8, h:.7, fontFace:F, fontSize:13.5, color:BODY, lineSpacing:18, margin:0 });
  kaki(s);
}

// ═══ 6 Tema — Waktu belajar ════════════════════════════════
{
  const s = s0();
  judul(s, 'Tema 2 · Waktu belajar yang terus terpotong', 'Tema baru yang belum muncul pada laporan bulan sebelumnya');
  tema(s, 2, stat.tema['Waktu belajar dan jadwal']);
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
  kartu(s, .62, y+.05, W-1.24, 1.0, CAT05, CAT30);
  s.addText('Tiga kebijakan yang masing-masing wajar — olahraga pagi, apel malam, kegiatan sore — bertemu pada satu hari yang sama dan menyisakan sedikit sekali waktu belajar mandiri.',
    { x:.9, y:y+.22, w:W-1.8, h:.7, fontFace:F, fontSize:13.5, bold:true, color:EMBER, lineSpacing:18, margin:0 });
  kaki(s);
}

// ═══ 7 Tema — Asrama ═══════════════════════════════════════
{
  const s = s0();
  judul(s, 'Tema 3 · Layanan dan kondisi asrama', 'Dari klinik, konsumsi, air, sampai kebutuhan dasar sehari-hari');
  tema(s, 3, stat.tema['Layanan dan kondisi asrama']);
  let y = 1.6;
  y = kutip(s, .62, y, W-1.24,
    'Saya malah disambut dengan pertanyaan tajam dan nada menyalahkan … bagaimana mungkin siswa mau terbuka mengenai kesehatannya jika tenaga medisnya sendiri bersikap antipati?',
    'KY-2026-0008 · Kanal Yayasan · anonim · 13 Agustus', MERAH, MER05);
  y = kutip(s, .62, y, W-1.24,
    'Aliran air di wastafel maupun toilet sangat kecil … bukan kejadian sekali dua kali, melainkan hampir selalu terjadi.',
    'AGKB-2026-0028 · Sarana & Fasilitas · 14 Agustus', CAT, CAT05);
  y = kutip(s, .62, y, W-1.24,
    'Tidak ada kejelasan siapa yang bertanggung jawab atas ketersediaan air minum untuk para guru … guru menemui orang yayasan dan OB, mereka bingung dan berkata "kami tidak tahu".',
    'AGKB-2026-0006 · guru · 17 Juli', GAL, GAL05);
  kartu(s, .62, y+.05, W-1.24, 1.0);
  s.addText('Sebagian besar bukan keluhan tentang barang yang belum ada, melainkan tentang tidak jelasnya siapa yang bertanggung jawab ketika sesuatu tidak berjalan.',
    { x:.9, y:y+.22, w:W-1.8, h:.7, fontFace:F, fontSize:13.5, color:BODY, lineSpacing:18, margin:0 });
  kaki(s);
}

// ═══ 8 Tema — Internet ═════════════════════════════════════
{
  const s = s0();
  judul(s, 'Tema 4 · Konektivitas internet', 'Keluhan tertua yang sekarang mulai berubah nada');
  tema(s, 4, stat.tema['Konektivitas internet']);
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
  kartu(s, .62, y+.05, W-1.24, 1.0, MER05, MER20);
  s.addText('Perhatikan perubahan nadanya. Laporan 12 Agustus masih menjelaskan masalah; laporan 17 Agustus sudah mempersoalkan janji perbaikan yang belum terlihat hasilnya.',
    { x:.9, y:y+.22, w:W-1.8, h:.7, fontFace:F, fontSize:13.5, bold:true, color:MERAH, lineSpacing:18, margin:0 });
  kaki(s);
}

// ═══ 9 Suara guru dan staf ═════════════════════════════════
{
  const s = s0();
  judul(s, 'Suara guru dan staf', 'Lebih sedikit jumlahnya, tetapi menyentuh cara yayasan dan sekolah bekerja sama');
  let y = 1.6;
  y = kutip(s, .62, y, W-1.24,
    'Sungguh tidak elok jika beberapa hal dilakukan ketika sudah mepet dengan tenggat waktu, sehingga pada masa liburan guru-guru dikejar untuk mengerjakan ini itu.',
    'AGKB-2026-0001 · guru · 19 Juni — laporan pertama yang masuk', CAT, CAT05);
  y = kutip(s, .62, y, W-1.24,
    'Hal receh namun sensitif itu tidak akan terjadi jika yayasan mau mendengarkan masukan dari kepala sekolah dan koordinator yang telah berdekade bekerja di pendidikan internasional.',
    'AGKB-2026-0001 · lanjutan · 19 Juni', MERAH, MER05);
  poin(s, .62, y+.1, W-1.24, [
    ['Pokoknya bukan beban kerja, melainkan urutan pengambilan keputusan',
     'Keluhan muncul ketika keputusan sampai ke sekolah dalam keadaan sudah jadi dan mendesak, bukan ketika pekerjaannya banyak.'],
    ['Nada laporan guru konstruktif dan menawarkan jalan keluar',
     'Dua laporan guru sama-sama diakhiri dengan usulan konkret, bukan sekadar keberatan. Ini modal yang layak dijaga.'],
  ], 14);
  kaki(s);
}

// ═══ 10 Apresiasi ══════════════════════════════════════════
{
  const s = s0();
  judul(s, 'Yang berjalan baik juga tercatat', `${stat.jalur['Apresiasi']} laporan masuk melalui jalur apresiasi`);
  let y = 1.6;
  y = kutip(s, .62, y, W-1.24,
    'Setengah tahun lalu saya memberi masukan agar tim finance dapat memberikan slip gaji karyawan tiap bulan. Hal ini akhirnya sudah diwujudnyatakan … Terima kasih sudah mendengarkan saran kecil ini.',
    'AGKB-2026-0029 · Apresiasi Umum · 14 Agustus', HIJAU, HIJ05);
  y = kutip(s, .62, y, W-1.24,
    'Saya senang saat menuju kamar mandi tidak harus takut ada yang meneriakkan untuk masuk kelas.',
    'AGKB-2026-0025 · Apresiasi Umum · 13 Agustus', HIJAU, HIJ05);
  kartu(s, .62, y+.1, W-1.24, 1.85, HIJ05, '9BD9BB');
  s.addText('Laporan pertama adalah bukti paling berharga dalam periode ini.',
    { x:.9, y:y+.3, w:W-1.8, h:.35, fontFace:F, fontSize:16, bold:true, color:'014D2E', margin:0 });
  s.addText('Seorang karyawan menyampaikan masukan, masukan itu dikerjakan, lalu ia kembali untuk mengatakannya. Itulah bentuk lingkaran umpan balik yang tertutup — dan itulah yang membuat orang mau melapor lagi. Sembilan laporan apresiasi ini semuanya belum ditanggapi satu pun.',
    { x:.9, y:y+.72, w:W-1.8, h:1.0, fontFace:F, fontSize:13.5, color:'014D2E', lineSpacing:18, margin:0 });
  kaki(s);
}

// ═══ 11 Siapa yang bicara ══════════════════════════════════
{
  const s = s0();
  judul(s, 'Siapa yang menyampaikan', 'Jalur tanpa akun kini menjadi pintu masuk utama');
  s.addImage({ path:path.join(D,'n4-asal.png'), x:.62, y:1.75, w:6.9, h:3.02 });
  poin(s, 7.9, 1.75, 4.9, [
    ['Formulir publik menjadi mayoritas',
     `${stat.asal.publik} dari ${stat.total} laporan datang tanpa akun. Membuka jalur ini terbukti menjangkau kelompok yang sebelumnya tidak terdengar.`],
    ['Pelapor anonim naik jadi ' + stat.anonim,
     'Seluruhnya masuk lewat Kanal Yayasan dan berisi hal yang paling sensitif: sikap mentor, layanan klinik, dan kelelahan siswa.'],
  ], 13.5);
  kartu(s, .62, 5.1, W-1.24, 1.35, GAL05, GAL20);
  s.addText('Naiknya laporan anonim bukan tanda memburuknya keadaan. Itu tanda orang mulai percaya bahwa menyampaikan hal sulit tidak akan merugikan dirinya — asalkan kepercayaan itu dijawab dengan tindakan.',
    { x:.95, y:5.35, w:W-1.9, h:.9, fontFace:F, fontSize:14, color:'030870', lineSpacing:19, margin:0 });
  kaki(s);
}

// ═══ 12 Awan kata ══════════════════════════════════════════
{
  const s = s0();
  judul(s, 'Kata yang paling sering muncul', 'Diambil dari seluruh isi laporan, kata umum dibuang');
  s.addImage({ path:path.join(D,'n6-awankata.png'), x:.62, y:1.6, w:8.5, h:4.78 });
  kartu(s, 9.4, 1.6, 3.3, 4.78);
  s.addText('Paling sering muncul', { x:9.65, y:1.8, w:2.9, h:.3, fontFace:F, fontSize:12, bold:true, color:ABU, margin:0 });
  stat.kata.slice(0, 8).forEach(([k, v], i) => {
    s.addText(k, { x:9.65, y:2.25 + i*.52, w:1.9, h:.32, fontFace:F, fontSize:14.5, bold:true, color:OBL, margin:0 });
    s.addText(String(v), { x:11.55, y:2.25 + i*.52, w:.9, h:.32, fontFace:F, fontSize:14.5, color:CAT, align:'right', margin:0 });
  });
  kaki(s);
}

// ═══ 13 Titik buta ═════════════════════════════════════════
{
  const s = s0();
  judul(s, 'Temuan operasional yang paling mendesak', 'Jalur sekolah berjalan; jalur yayasan belum tersentuh sama sekali');
  kartu(s, .62, 1.55, 6.0, 2.15, HIJ05, '9BD9BB');
  s.addText('Jalur Kendala / Masukan', { x:.9, y:1.75, w:5.4, h:.3, fontFace:F, fontSize:13, bold:true, color:'014D2E', margin:0 });
  s.addText('29 laporan · semua sudah punya penanggung jawab', { x:.9, y:2.08, w:5.4, h:.3, fontFace:F, fontSize:13, color:'014D2E', margin:0 });
  s.addText('Median 5,7 jam sampai balasan pertama', { x:.9, y:2.48, w:5.4, h:.42, fontFace:F, fontSize:19, bold:true, color:HIJAU, margin:0 });
  s.addText('Toni Yunanto memegang 12 tiket, Arif 6, Qaedi 5.', { x:.9, y:3.0, w:5.4, h:.3, fontFace:F, fontSize:12, color:'014D2E', margin:0 });

  kartu(s, 6.85, 1.55, 5.83, 2.15, MER05, MER20);
  s.addText('Jalur Kanal Yayasan', { x:7.15, y:1.75, w:5.2, h:.3, fontFace:F, fontSize:13, bold:true, color:MERAH, margin:0 });
  s.addText('10 laporan · seluruhnya berprioritas P1', { x:7.15, y:2.08, w:5.2, h:.3, fontFace:F, fontSize:13, color:MERAH, margin:0 });
  s.addText('0 dari 10 punya penanggung jawab', { x:7.15, y:2.48, w:5.2, h:.42, fontFace:F, fontSize:19, bold:true, color:MERAH, margin:0 });
  s.addText('Tidak satu pun sudah dibalas sejak 11 Agustus.', { x:7.15, y:3.0, w:5.2, h:.3, fontFace:F, fontSize:12, color:MERAH, margin:0 });

  poin(s, .62, 3.95, W-1.24, [
    ['Justru di jalur itulah laporan paling berat berada',
     'Sikap mentor saat pembinaan, layanan klinik, dan seorang siswa yang menulis bahwa dirinya sakit, kelelahan, dan pekerjaannya menumpuk. Ketiganya masih berstatus "Baru".'],
    ['Penyebabnya bukan sistem, melainkan keanggotaan unit',
     'Kanal Yayasan hanya dapat dilihat oleh anggota unit "Kanal Yayasan". Selama unit itu belum diisi dan pengurusnya belum aktif login, laporan akan terus menumpuk tanpa terbaca.'],
  ], 14);
  kaki(s);
}

// ═══ 14 Keadaan penanganan ═════════════════════════════════
{
  const s = s0();
  judul(s, 'Keadaan penanganan secara keseluruhan', 'Per 18 Agustus 2026');
  s.addImage({ path:path.join(D,'n3-penanganan.png'), x:.62, y:1.85, w:5.9, h:3.26 });
  angka(s, 7.0, 1.85, 2.7, stat.ada_respons, 'Sudah dibalas', OBL);
  angka(s, 9.95, 1.85, 2.73, stat.belum_ditugaskan, 'Belum ada pemilik', MERAH);
  poin(s, 7.0, 3.35, 5.68, [
    ['Menutup tiket belum menjadi kebiasaan',
     'Baru 1 tiket yang benar-benar ditutup. Selama status tidak diperbarui, pelapor tidak menerima kabar apa pun dan menyimpulkan laporannya diabaikan.'],
    ['Beban menumpuk pada sedikit orang',
     'Tiga penanggung jawab memegang 23 dari 29 tiket jalur sekolah.'],
  ], 13.5);
  kaki(s);
}

// ═══ 15 Pola yang terbaca ══════════════════════════════════
{
  const s = s0();
  judul(s, 'Pola yang terbaca dari seluruh laporan', 'Tiga hal yang berulang lintas tema');
  const kartuPola = (x, w, no, jdl, isi, warna, bg) => {
    kartu(s, x, 1.65, w, 4.5, bg, warna);
    s.addText(no, { x:x+.3, y:1.9, w:1, h:.6, fontFace:F, fontSize:38, bold:true, color:warna, margin:0 });
    s.addText(jdl, { x:x+.3, y:2.6, w:w-.6, h:.8, fontFace:F, fontSize:16.5, bold:true, color:OBL, lineSpacing:20, margin:0 });
    s.addText(isi, { x:x+.3, y:3.5, w:w-.6, h:2.4, fontFace:F, fontSize:13, color:BODY, lineSpacing:19, margin:0 });
  };
  kartuPola(.62, 3.9, '1', 'Yang dipersoalkan adalah kejelasan, bukan kekurangan',
    'Air minum, sanksi, jadwal, laundry — hampir semua laporan berhenti pada pertanyaan yang sama: siapa yang memutuskan ini dan kepada siapa saya bertanya. Kekurangan barang jauh lebih jarang disebut daripada ketidakjelasan tanggung jawab.', CAT30, CAT05);
  kartuPola(4.72, 3.9, '2', 'Nada berubah ketika laporan tidak dijawab',
    'Laporan wifi 12 Agustus masih menjelaskan keadaan dengan sabar. Laporan 17 Agustus sudah menyinggung janji yang belum terbukti. Diamnya sebuah kanal mengubah masukan menjadi keluhan, dan keluhan menjadi ketidakpercayaan.', MER20, MER05);
  kartuPola(8.82, 3.86, '3', 'Siswa melapor dengan bahasa yang tertata',
    'Sebagian besar laporan siswa menyebut waktu, tempat, dan usulan perbaikan. Beberapa bahkan membuka dengan ucapan terima kasih. Ini bukan gelombang protes, melainkan kesediaan berdialog yang perlu dijawab setara.', GAL20, GAL05);
  kaki(s);
}

// ═══ 16 Rekomendasi ════════════════════════════════════════
{
  const s = s0();
  judul(s, 'Rekomendasi', 'Diurutkan menurut kemendesakan');
  const baris = [
    ['Segera', 'Isi keanggotaan unit Kanal Yayasan dan aktifkan akun pengurusnya', 'Sepuluh laporan P1 menunggu tanpa pembaca. Ini satu-satunya tindakan yang tidak bisa ditunda.', MERAH, MER05],
    ['Segera', 'Tangani KY-2026-0010 dan KY-2026-0011 lebih dulu', 'Satu ditulis siswa yang menyatakan dirinya sakit dan kelelahan; satu lagi berisi pengaduan atas sikap saat pembinaan. Keduanya perlu ditemui, bukan sekadar dibalas.', MERAH, MER05],
    ['Pekan ini', 'Berikan kabar tertulis pada seluruh laporan wifi', 'Tiga belas laporan menunggu kabar. Cukup sampaikan apa yang sedang dikerjakan dan kapan diperiksa ulang, meskipun perbaikannya belum selesai.', CAT, CAT05],
    ['Bulan ini', 'Tinjau ulang susunan hari siswa secara menyeluruh', 'Olahraga pagi, apel malam, dan kegiatan sore dinilai terpisah-pisah. Perlu dilihat sebagai satu rangkaian bersama beban IBDP kelas 11.', CAT, CAT05],
    ['Bulan ini', 'Samakan pedoman sanksi antar kelompok mentor', 'Keluhan berulang menyebut hukuman yang berbeda untuk pelanggaran serupa dan sanksi tanpa peringatan lebih dulu.', GAL, GAL05],
    ['Berjalan', 'Jadikan penutupan tiket sebagai kewajiban, bukan pilihan', 'Satu dari 48 tiket yang ditutup membuat kanal ini tampak tidak berujung. Tutup dengan keterangan, meskipun jawabannya adalah penolakan.', OBL, PUTIH],
  ];
  let y = 1.42;
  baris.forEach(([kapan, jdl, isi, warna, bg]) => {
    const nb = Math.ceil(isi.length / 118);
    const th = .5 + nb * .22;
    kartu(s, .62, y, W-1.24, th, bg, warna === OBL ? LINE : warna);
    s.addShape(p.ShapeType.roundRect, { x:.8, y:y+.19, w:1.05, h:.34, rectRadius:.06, fill:{color:warna} });
    s.addText(kapan, { x:.8, y:y+.23, w:1.05, h:.26, fontFace:F, fontSize:9.5, bold:true, color:PUTIH, align:'center', margin:0 });
    s.addText(jdl, { x:2.0, y:y+.08, w:10.5, h:.3, fontFace:F, fontSize:13.5, bold:true, color:OBL, margin:0 });
    s.addText(isi, { x:2.0, y:y+.38, w:10.5, h:nb*.22+.06, fontFace:F, fontSize:11.5, color:BODY, lineSpacing:15, margin:0 });
    y += th + .07;
  });
  kaki(s);
}

// ═══ 17–20 Lampiran ════════════════════════════════════════
{
  const perHal = 10;
  const hal = Math.ceil(daftar.length / perHal);
  const warnaJalur = { 'Kendala': GAL, 'Apresiasi': HIJAU, 'Kanal Yayasan': MERAH };
  const bgJalur = { 'Kendala': GAL05, 'Apresiasi': HIJ05, 'Kanal Yayasan': MER05 };

  for (let h = 0; h < hal; h++) {
    const s = s0();
    judul(s, h === 0 ? 'Lampiran · Seluruh laporan yang masuk' : 'Lampiran · lanjutan',
      h === 0 ? `${daftar.length} pokok berbeda dari ${stat.total} laporan; kiriman ganda disatukan`
              : `Halaman ${h + 1} dari ${hal}`);
    let y = 1.5;
    s.addText('TGL',      { x:.62,  y, w:.85, h:.24, fontFace:F, fontSize:9, bold:true, color:ABU, margin:0 });
    s.addText('JALUR',    { x:1.5,  y, w:1.3, h:.24, fontFace:F, fontSize:9, bold:true, color:ABU, margin:0 });
    s.addText('KATEGORI', { x:2.85, y, w:1.9, h:.24, fontFace:F, fontSize:9, bold:true, color:ABU, margin:0 });
    s.addText('POKOK LAPORAN', { x:4.85, y, w:7.8, h:.24, fontFace:F, fontSize:9, bold:true, color:ABU, margin:0 });
    y += .3;
    daftar.slice(h * perHal, (h + 1) * perHal).forEach((d, i) => {
      const tg = .48;
      if (i % 2 === 0) s.addShape(p.ShapeType.rect, { x:.55, y:y-.05, w:W-1.1, h:tg, fill:{color:'FFFFFF'} });
      s.addText(d.masuk.slice(8) + '/' + d.masuk.slice(5,7),
        { x:.62, y:y+.03, w:.85, h:.28, fontFace:F, fontSize:10, color:ABU, margin:0 });
      s.addShape(p.ShapeType.roundRect, { x:1.5, y:y+.04, w:1.22, h:.27, rectRadius:.05,
        fill:{color:bgJalur[d.jalur] || BG} });
      s.addText(d.jalur, { x:1.5, y:y+.07, w:1.22, h:.22, fontFace:F, fontSize:8.5, bold:true,
        color:warnaJalur[d.jalur] || ABU, align:'center', margin:0 });
      s.addText(d.kategori, { x:2.85, y:y+.03, w:1.95, h:.28, fontFace:F, fontSize:9.5, color:ABU, margin:0 });
      s.addText(d.subjek.length > 92 ? d.subjek.slice(0, 90) + '…' : d.subjek,
        { x:4.85, y:y+.02, w:d.kali > 1 ? 7.1 : 7.8, h:.3, fontFace:F, fontSize:10.5, color:BODY, margin:0 });
      if (d.kali > 1) {
        s.addShape(p.ShapeType.roundRect, { x:12.1, y:y+.04, w:.55, h:.27, rectRadius:.05, fill:{color:CAT05} });
        s.addText(d.kali + '×', { x:12.1, y:y+.07, w:.55, h:.22, fontFace:F, fontSize:9, bold:true,
          color:EMBER, align:'center', margin:0 });
      }
      y += tg;
    });
    if (h === 0) s.addText('Tanda 2× / 3× / 4× menunjukkan laporan yang dikirim berkali-kali dengan isi sama.',
      { x:.62, y:6.6, w:9, h:.26, fontFace:F, fontSize:10, italic:true, color:ABU, margin:0 });
    kaki(s);
  }
}

// ═══ 21 Penutup ════════════════════════════════════════════
{
  const s = p.addSlide(); s.background = { color: OBL };
  s.addText('Catatan penutup', { x:.95, y:1.5, w:11, h:.6, fontFace:F, fontSize:32, bold:true, color:PUTIH, margin:0 });
  s.addText('Dalam dua bulan, 48 laporan masuk dari orang-orang yang sebelumnya tidak punya tempat untuk bicara. Isinya tertata, spesifik, dan sebagian besar disertai usulan perbaikan.\n\nYang menentukan apakah kanal ini bertahan bukan jumlah laporan berikutnya, melainkan apakah laporan hari ini dijawab. Satu apresiasi dalam periode ini datang justru karena masukan lama pernah dikerjakan — itulah bukti bahwa lingkaran umpan balik yang ditutup akan mengundang laporan berikutnya.',
    { x:.95, y:2.35, w:10.6, h:2.6, fontFace:F, fontSize:16, color:GAL20, lineSpacing:26, margin:0 });
  s.addShape(p.ShapeType.rect, { x:.95, y:5.3, w:2.2, h:.05, fill:{color:CAT} });
  s.addText('Disusun dari basis data AGKB 360° · Ditarik 18 Agustus 2026 · 48 tiket, tanpa data uji coba',
    { x:.95, y:5.65, w:11, h:.3, fontFace:F, fontSize:12, color:'8A88A8', margin:0 });
  s.addText('Identitas pelapor anonim tidak dibuka dalam laporan ini. Nama pihak yang diadukan sengaja tidak dicantumkan; rinciannya ada pada tiket masing-masing.',
    { x:.95, y:6.0, w:11, h:.5, fontFace:F, fontSize:12, color:'8A88A8', lineSpacing:17, margin:0 });
}

const out = path.join(D, 'Laporan-Temuan-Kanal-Feedback.pptx');
p.writeFile({ fileName: out }).then(() => console.log('OK', out, '| slide:', NO + 2));
