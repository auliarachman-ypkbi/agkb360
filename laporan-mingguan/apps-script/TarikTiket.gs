/**
 * ============================================================
 * AGKB 360° — Penarik Tiket ke Shared Drive
 * ------------------------------------------------------------
 * Memanggil API AGKB360, menyimpan seluruh riwayat tiket sebagai
 * JSON di folder "Data" pada shared drive "Automasi Feedback
 * Deck". Dari sana Claude membacanya lewat Google Drive Desktop
 * yang tersinkron di Mac, lalu menaruh decknya di folder "Deck".
 *
 * Berjalan di server Google — Mac tidak perlu menyala.
 *
 *   AGKB360 ──API──▶ Apps Script ──▶ Data/tiket-semua.json
 *                                        │
 *                              Drive sync ▼
 *                          Claude baca ── olah ──▶ Deck/*.pptx
 *
 * ── Yang ditarik ────────────────────────────────────────────
 * SELURUH riwayat sejak MULAI, bukan sepekan terakhir. Kalau
 * hanya sepekan yang tersimpan, pertanyaan seperti "bandingkan
 * dengan bulan lalu" atau "bagaimana tren keluhan wifi sejak
 * Juni" tidak bisa dijawab tanpa menarik ulang.
 *
 * Berkasnya kecil — puluhan hingga ratusan kilobyte per tahun.
 * Menyimpan semuanya jauh lebih murah daripada kehilangan
 * kemampuan bertanya.
 *
 * API membatasi satu permintaan maksimal 400 hari; rentang yang
 * lebih panjang dipecah otomatis lalu digabungkan di sini.
 *
 * ── Sebelum dipakai ─────────────────────────────────────────
 * Setelan proyek → Properti skrip, tambahkan SATU properti:
 *
 *   API_TOKEN   token dari config.php di VPS
 *
 * Token TIDAK ditulis di berkas ini. Kode Apps Script mudah
 * dibagikan dan disalin; properti skrip tidak ikut tersalin.
 * ID folder bukan rahasia, jadi boleh langsung di dalam kode.
 *
 * Lalu: jalankan uji() → periksa folder Data → pasangPemicu().
 * ============================================================
 */

const API_URL = 'https://agkb360.app/app/api/tiket.php';

/**
 * Shared Drive: Automasi Feedback Deck
 *   Data → https://drive.google.com/drive/u/0/folders/1FmE0xexJMpEWLcZp6IddkdH1q0orTWyK
 *   Deck → https://drive.google.com/drive/u/0/folders/1bGpZhGXJn57BCFLTdmKZhYOh7vmII1MX
 */
const FOLDER_DATA = '1FmE0xexJMpEWLcZp6IddkdH1q0orTWyK';
const FOLDER_DECK = '1bGpZhGXJn57BCFLTdmKZhYOh7vmII1MX';   // diisi Claude, bukan skrip ini

/** Tiket pertama masuk 19 Juni 2026. Tidak ada yang lebih tua. */
const MULAI = '2026-06-01';

/** Batas satu permintaan menurut API. Dipecah bila melebihi. */
const HARI_PER_PERMINTAAN = 365;


// ════════════════════════════════════════════════════════════
//  Utama
// ════════════════════════════════════════════════════════════

/**
 * Dijalankan pemicu mingguan. Menarik seluruh riwayat.
 * @return {number} jumlah tiket yang tersimpan
 */
function tarikSemua() {
  const token = PropertiesService.getScriptProperties().getProperty('API_TOKEN');
  if (!token) throw new Error('Properti skrip API_TOKEN belum diisi.');

  const hariIni = tanggal(new Date());
  const potong  = pecahRentang(MULAI, hariIni);

  // Digabung lewat peta bernomor tiket, bukan array biasa.
  // Potongan rentang bisa bersinggungan di tepinya; tanpa ini
  // ada tiket yang terhitung dua kali dan semua angka meleset.
  const peta = {};
  potong.forEach(function (r) {
    ambil(token, r.dari, r.sampai).forEach(function (t) {
      peta[t.nomor] = t;
    });
  });

  const tiket = Object.keys(peta)
    .map(function (k) { return peta[k]; })
    .sort(function (a, b) { return a.masuk < b.masuk ? -1 : 1; });

  if (!tiket.length) {
    // Berkas lama sengaja TIDAK ditimpa. Berkas kosong membuat
    // laporan tampak nihil, padahal mungkin hanya API yang
    // sedang bermasalah.
    catat('Tidak ada tiket sama sekali. Berkas lama dibiarkan.');
    return 0;
  }

  const isi = JSON.stringify({
    ok:      true,
    ditarik: new Date().toISOString(),
    rentang: { dari: MULAI, sampai: hariIni },
    jumlah:  tiket.length,
    catatan: 'Identitas pelapor anonim tidak dibuka pada keluaran ini.',
    tiket:   tiket,
  }, null, 1);

  const map = DriveApp.getFolderById(FOLDER_DATA);

  // Yang dibaca Claude.
  simpan(map, 'tiket-semua.json', isi);

  // Potret bertanggal. Tiket bisa disunting atau dihapus di
  // aplikasi; arsip ini menjaga apa yang terlihat saat itu.
  simpan(map, 'arsip/tiket-' + hariIni + '.json', isi);

  simpan(map, 'rentang.json', JSON.stringify({
    dari:     MULAI,
    sampai:   hariIni,
    jumlah:   tiket.length,
    diunggah: new Date().toISOString(),
  }, null, 1));

  catat('Berhasil: ' + tiket.length + ' tiket, ' + MULAI + ' s.d. ' + hariIni);
  return tiket.length;
}


/**
 * Satu permintaan ke API.
 * @return {Array<Object>} daftar tiket pada rentang itu
 */
function ambil(token, dari, sampai) {
  const url = API_URL
    + '?dari='   + encodeURIComponent(dari)
    + '&sampai=' + encodeURIComponent(sampai);

  // Token lewat header, bukan query string. Query string ikut
  // tercatat di access log server; header tidak.
  const res = UrlFetchApp.fetch(url, {
    method: 'get',
    headers: { 'X-API-Token': token },
    muteHttpExceptions: true,
  });

  const kode = res.getResponseCode();
  const teks = res.getContentText();

  if (kode !== 200) {
    catat('GAGAL ' + kode + ' pada ' + dari + '..' + sampai + ': ' + teks.slice(0, 200));
    throw new Error('API menjawab ' + kode + '. Periksa token dan alamat API.');
  }

  let data;
  try {
    data = JSON.parse(teks);
  } catch (e) {
    throw new Error('Jawaban API tidak dapat dibaca sebagai JSON.');
  }
  if (!data.ok) throw new Error(data.pesan || 'API menolak permintaan.');

  return data.tiket || [];
}


// ════════════════════════════════════════════════════════════
//  Pembantu
// ════════════════════════════════════════════════════════════

/**
 * Memecah rentang panjang menjadi potongan yang muat di API.
 * @return {Array<{dari: string, sampai: string}>}
 */
function pecahRentang(dari, sampai) {
  const hasil = [];
  let mulai   = new Date(dari + 'T00:00:00Z');
  const akhir = new Date(sampai + 'T00:00:00Z');

  while (mulai <= akhir) {
    let ujung = new Date(mulai.getTime() + HARI_PER_PERMINTAAN * 864e5);
    if (ujung > akhir) ujung = akhir;
    hasil.push({ dari: tanggalUtc(mulai), sampai: tanggalUtc(ujung) });
    mulai = new Date(ujung.getTime() + 864e5);
  }
  return hasil;
}


/**
 * Menulis berkas, menimpa yang bernama sama.
 *
 * Drive membolehkan dua berkas bernama sama dalam satu folder.
 * Kalau dibiarkan, "tiket-semua.json" akan beranak-pinak dan
 * Claude bisa membaca salinan yang salah.
 *
 * Bila nama mengandung "/", bagian depannya diperlakukan
 * sebagai subfolder dan dibuat bila belum ada.
 */
function simpan(map, nama, isi) {
  const bagian = nama.split('/');
  let tujuan = map;

  while (bagian.length > 1) {
    const sub = bagian.shift();
    const ada = tujuan.getFoldersByName(sub);
    tujuan = ada.hasNext() ? ada.next() : tujuan.createFolder(sub);
  }
  nama = bagian[0];

  const lama = tujuan.getFilesByName(nama);
  while (lama.hasNext()) lama.next().setTrashed(true);
  tujuan.createFile(nama, isi, MimeType.PLAIN_TEXT);
}


/** YYYY-MM-DD menurut waktu Jakarta. */
function tanggal(d) {
  return Utilities.formatDate(d, 'Asia/Jakarta', 'yyyy-MM-dd');
}

/** YYYY-MM-DD dari tanggal yang sudah dalam UTC. */
function tanggalUtc(d) {
  return d.toISOString().slice(0, 10);
}

/** Catatan ke log eksekusi Apps Script. */
function catat(pesan) {
  console.log('[AGKB360] ' + pesan);
}


// ════════════════════════════════════════════════════════════
//  Dijalankan manual
// ════════════════════════════════════════════════════════════

/**
 * Memeriksa penyiapan tanpa menyentuh apa pun: token terisi,
 * kedua folder terjangkau, API menjawab. Jalankan ini lebih
 * dulu bila ada yang tidak beres.
 */
function periksa() {
  const token = PropertiesService.getScriptProperties().getProperty('API_TOKEN');
  catat('API_TOKEN   : ' + (token ? 'terisi (' + token.length + ' karakter)' : 'KOSONG'));

  try {
    catat('Folder Data : ' + DriveApp.getFolderById(FOLDER_DATA).getName());
  } catch (e) {
    catat('Folder Data : TIDAK TERJANGKAU — ' + e.message);
  }
  try {
    catat('Folder Deck : ' + DriveApp.getFolderById(FOLDER_DECK).getName());
  } catch (e) {
    catat('Folder Deck : TIDAK TERJANGKAU — ' + e.message);
  }

  if (!token) return;
  const hariIni = tanggal(new Date());
  try {
    const n = ambil(token, hariIni, hariIni).length;
    catat('API         : menjawab dengan baik (' + n + ' tiket hari ini)');
  } catch (e) {
    catat('API         : GAGAL — ' + e.message);
  }
}


/**
 * Uji sekali jalan tanpa menunggu jadwal. Jalankan setelah
 * mengisi API_TOKEN, lalu periksa folder Data di Drive.
 */
function uji() {
  const n = tarikSemua();
  catat('Uji selesai. ' + n + ' tiket tersimpan di folder Data.');
}


/**
 * Memasang pemicu mingguan: Minggu, sekitar pukul 20.00 WIB.
 * Aman dijalankan berulang — pemicu lama dihapus lebih dulu.
 */
function pasangPemicu() {
  ScriptApp.getProjectTriggers()
    .filter(function (t) { return t.getHandlerFunction() === 'tarikSemua'; })
    .forEach(function (t) { ScriptApp.deleteTrigger(t); });

  ScriptApp.newTrigger('tarikSemua')
    .timeBased()
    .onWeekDay(ScriptApp.WeekDay.SUNDAY)
    .atHour(20)
    .inTimezone('Asia/Jakarta')
    .create();

  catat('Pemicu mingguan dipasang: Minggu sekitar pukul 20.00 WIB.');
}


/** Melihat pemicu yang sedang terpasang. */
function daftarPemicu() {
  const t = ScriptApp.getProjectTriggers();
  if (!t.length) { catat('Belum ada pemicu.'); return; }
  t.forEach(function (x) {
    catat('Pemicu: ' + x.getHandlerFunction() + ' (' + x.getEventType() + ')');
  });
}
