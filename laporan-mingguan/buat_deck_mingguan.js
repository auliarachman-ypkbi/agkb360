#!/usr/bin/env node
// Laporan Mingguan Kanal Feedback — generator deck dari naskah.json.
//
// Template minimalis: putih polos, font Arial. Sama persis dengan yang
// dipakai untuk Tindak-Lanjut-Pak-Qaedi-Aqsa / Tindak-Lanjut-Bu-Dewi-Amri /
// Ringkasan-Tiket-Baru sebelumnya — supaya semua deck dari Kanal Feedback
// terlihat satu keluarga.
//
// Pakai:
//   node buat_deck_mingguan.js <naskah.json> <keluaran.pptx>
//
// naskah.json ditulis Claude (bukan skrip ini) — baca isi laporan
// (kolom "isi" di tiket-semua.json), susun ringkasan dan rekomendasi
// sendiri per topik. Lihat KONTEKS.md / SKILL laporan-mingguan-agkb
// untuk aturan kerahasiaan dan bahasa.
//
// Bentuk naskah.json:
// {
//   "judulDeck": "Laporan Mingguan Kanal Feedback",
//   "judulUtama": "Topik yang Masuk Minggu Ini",
//   "subjudul": "Jalur Kendala & Masukan — periode 24–30 Agustus 2026",
//   "catatanKaki": "Jalur Kanal Yayasan ditangani terpisah, tidak dimuat di sini.",
//   "stat": [["3","topik"], ["5","tiket masuk"], ["1","belum direspons"]],
//   "daftar": [
//     {
//       "judul": "...", "nomor": "...", "prioritas": "P2", "status": "Baru",
//       "meta": "AGKB-2026-0052  ·  Sarana & Fasilitas  ·  P2  ·  Baru",
//       "ringkasan": "...", "rekomendasi": "...",
//       "subjekAsli": "AGKB-2026-0052 — ...",
//       "metaKolom1": "Nomor tiket: ...\\nJalur: ...\\n...",
//       "metaKolom2": "Penanggung jawab: ...\\nUnit: ...\\n...",
//       "isiLengkap": "..."
//     }
//   ]
// }
const pptxgen = require('pptxgenjs');
const fs = require('fs');

const [, , naskahPath, keluarPath] = process.argv;
if (!naskahPath || !keluarPath) {
  console.error('Pakai: node buat_deck_mingguan.js <naskah.json> <keluaran.pptx>');
  process.exit(1);
}

const naskah = JSON.parse(fs.readFileSync(naskahPath, 'utf-8'));
const { judulDeck, judulUtama, subjudul, catatanKaki, stat, daftar } = naskah;

const FONT = 'Arial';
const HITAM = '000000';
const ABU = '595959';
const FOOTER = 'Feedback Laporan KTB';

function hitungFontIsi(teks, lebarIn, tinggiIn) {
  const lebar_pt = lebarIn * 72;
  const tinggi_pt = tinggiIn * 72;
  const faktorLebarKarakter = 0.50;
  const faktorTinggiBaris = 1.22;
  const efisiensiIsi = 0.80;
  const k = (lebar_pt * tinggi_pt * efisiensiIsi) / (faktorLebarKarakter * faktorTinggiBaris);
  let n = Math.sqrt(k / teks.length);
  n = Math.min(n, 18);
  n = Math.max(n, 9.5);
  return Math.floor(n * 2) / 2;
}

function headerKonteks(slide, nomorSlide, totalSlide) {
  slide.addText(`${judulDeck}  ·  ${nomorSlide}/${totalSlide}`, {
    x: 0.5, y: 0.35, w: 12.3, h: 0.3,
    fontFace: FONT, fontSize: 11, color: ABU, margin: 0,
  });
}

function footerSlide(slide) {
  slide.addText(FOOTER, {
    x: 0.5, y: 7.2, w: 12.3, h: 0.25,
    fontFace: FONT, fontSize: 9, color: ABU, margin: 0,
  });
}

function buatSlidePembuka(pres, totalSlide) {
  const s0 = pres.addSlide();
  s0.background = { color: 'FFFFFF' };
  headerKonteks(s0, 1, totalSlide);

  s0.addText(judulUtama, {
    x: 0.5, y: 0.75, w: 12.3, h: 0.65,
    fontFace: FONT, fontSize: 30, bold: true, color: HITAM, margin: 0,
  });
  s0.addText(subjudul, {
    x: 0.5, y: 1.4, w: 12.3, h: 0.4,
    fontFace: FONT, fontSize: 14, color: ABU, margin: 0,
  });

  const lebarStat = 12.3 / stat.length;
  stat.forEach((s, i) => {
    s0.addText(s[0], {
      x: 0.5 + i * lebarStat, y: 2.1, w: lebarStat - 0.3, h: 0.75,
      fontFace: FONT, fontSize: 40, bold: true, color: HITAM, margin: 0,
    });
    s0.addText(s[1], {
      x: 0.5 + i * lebarStat, y: 2.85, w: lebarStat - 0.3, h: 0.3,
      fontFace: FONT, fontSize: 12, color: ABU, margin: 0,
    });
  });

  const header = [
    { text: 'Topik', options: { bold: true, color: HITAM, fill: { color: 'FFFFFF' } } },
    { text: 'Nomor Tiket', options: { bold: true, color: HITAM, fill: { color: 'FFFFFF' } } },
    { text: 'Prioritas', options: { bold: true, color: HITAM, fill: { color: 'FFFFFF' } } },
    { text: 'Status', options: { bold: true, color: HITAM, fill: { color: 'FFFFFF' } } },
  ];
  const baris = daftar.map((d) => ([
    { text: d.judul, options: { color: HITAM } },
    { text: d.nomor, options: { color: ABU } },
    { text: d.prioritas, options: { color: HITAM } },
    { text: d.status, options: { color: ABU } },
  ]));

  s0.addTable([header, ...baris], {
    x: 0.5, y: 3.55, w: 12.3,
    colW: [6.5, 2.5, 1.5, 1.8],
    fontFace: FONT, fontSize: 13,
    border: { type: 'solid', pt: 0.5, color: 'D9D9D9' },
    autoPage: false,
    valign: 'middle',
    margin: [0.08, 0.1, 0.08, 0.1],
  });

  if (catatanKaki) {
    s0.addText(catatanKaki, {
      x: 0.5, y: 7.0, w: 12.3, h: 0.3,
      fontFace: FONT, fontSize: 10, italic: true, color: ABU, margin: 0,
    });
  }

  footerSlide(s0);
}

const pres = new pptxgen();
pres.layout = 'LAYOUT_WIDE';
const totalSlide = daftar.length * 2 + 1;
buatSlidePembuka(pres, totalSlide);
let idx = 1;

daftar.forEach((t) => {
  idx += 1;
  const s1 = pres.addSlide();
  s1.background = { color: 'FFFFFF' };
  headerKonteks(s1, idx, totalSlide);

  s1.addText(t.judul, {
    x: 0.5, y: 0.75, w: 12.3, h: 0.7,
    fontFace: FONT, fontSize: 26, bold: true, color: HITAM, margin: 0,
  });
  s1.addText(t.meta, {
    x: 0.5, y: 1.55, w: 12.3, h: 0.35,
    fontFace: FONT, fontSize: 13, color: ABU, margin: 0,
  });

  s1.addText('RINGKASAN LAPORAN', {
    x: 0.5, y: 2.2, w: 12.3, h: 0.3,
    fontFace: FONT, fontSize: 12, bold: true, color: HITAM, margin: 0, charSpacing: 1,
  });
  s1.addText(t.ringkasan, {
    x: 0.5, y: 2.55, w: 12.3, h: 2.15,
    fontFace: FONT, fontSize: 18, color: HITAM, margin: 0,
    valign: 'top', lineSpacingMultiple: 1.28,
  });

  s1.addText('REKOMENDASI / TINDAK LANJUT', {
    x: 0.5, y: 4.95, w: 12.3, h: 0.3,
    fontFace: FONT, fontSize: 12, bold: true, color: HITAM, margin: 0, charSpacing: 1,
  });
  s1.addText(t.rekomendasi, {
    x: 0.5, y: 5.3, w: 12.3, h: 1.7,
    fontFace: FONT, fontSize: 18, color: HITAM, margin: 0,
    valign: 'top', lineSpacingMultiple: 1.28,
  });
  footerSlide(s1);

  idx += 1;
  const s2 = pres.addSlide();
  s2.background = { color: 'FFFFFF' };
  headerKonteks(s2, idx, totalSlide);

  s2.addText(t.subjekAsli, {
    x: 0.5, y: 0.75, w: 12.3, h: 0.55,
    fontFace: FONT, fontSize: 18, bold: true, color: HITAM, margin: 0,
  });

  s2.addText(t.metaKolom1, {
    x: 0.5, y: 1.4, w: 6.0, h: 1.5,
    fontFace: FONT, fontSize: 12, color: HITAM, margin: 0, lineSpacingMultiple: 1.3,
  });
  s2.addText(t.metaKolom2, {
    x: 6.8, y: 1.4, w: 6.0, h: 1.5,
    fontFace: FONT, fontSize: 12, color: HITAM, margin: 0, lineSpacingMultiple: 1.3,
  });

  s2.addText('ISI LAPORAN (LENGKAP)', {
    x: 0.5, y: 3.0, w: 12.3, h: 0.3,
    fontFace: FONT, fontSize: 12, bold: true, color: HITAM, margin: 0, charSpacing: 1,
  });

  const lebarIsi = 12.3, tinggiIsi = 3.5;
  const fontIsi = hitungFontIsi(t.isiLengkap, lebarIsi, tinggiIsi);
  s2.addText(t.isiLengkap, {
    x: 0.5, y: 3.35, w: lebarIsi, h: tinggiIsi,
    fontFace: FONT, fontSize: fontIsi, color: HITAM, margin: 0,
    valign: 'top', lineSpacingMultiple: 1.22,
  });
  footerSlide(s2);
});

pres.writeFile({ fileName: keluarPath }).then(() => console.log('Tersimpan:', keluarPath));
