/**
 * ============================================================
 * AGKB 360° — Gerbang Pengiriman Email
 * ------------------------------------------------------------
 * Satu-satunya jalan keluar email dari platform. Dipakai oleh:
 *   - admin/blast_email.php   (undangan & ajakan)
 *   - includes/feedback.php   (notifikasi tiket, eskalasi, resolusi)
 *
 * Keduanya mengirim payload yang sama: { to, subject, htmlBody }.
 * Karena itu arsip BCC cukup dipasang di sini — satu tempat,
 * berlaku untuk semua email, tidak ada yang terlewat.
 *
 * ⚠ WAJIB deploy ulang sebagai VERSI BARU setiap kali file ini
 *   diubah. Tanpa itu, perubahan tidak berlaku.
 * ============================================================
 */

// Arsip pemantauan — dikosongkan dengan sengaja.
//
// Dulu setiap email di-BCC ke sini sebagai catatan. Sejak setiap
// pengiriman tercatat di email_blast_log lengkap dengan alamat,
// subjek, dan statusnya, arsip ini tidak lagi memberi informasi
// baru. Yang tersisa hanya kerugiannya:
//
//   · Setiap email memakan TIGA penerima dari kuota harian, bukan
//     satu. Blast ke 386 orang berarti 1.158 dari jatah 1.500.
//   · Isi Kanal Yayasan yang sensitif tersalin ke kotak surat di
//     luar orang yang ditunjuk membacanya.
//
// Kalau suatu saat perlu dihidupkan lagi, isi kembali daftar ini
// dan perhitungkan pengali kuotanya.
var ARSIP = [];

var NAMA_PENGIRIM = 'AGKB 360°';

function doPost(e) {
  try {
    var d = JSON.parse(e.postData.contents);

    if (!d.to || !d.subject) {
      return balas({ ok: false, error: 'to dan subject wajib diisi' });
    }

    // Periksa kuota lebih dulu. Tanpa ini, MailApp melempar
    // pengecualian berbunyi "Layanan terlalu sering diminta di hari
    // yang sama" — benar tetapi tidak memberi tahu berapa sisanya
    // maupun berapa yang dibutuhkan.
    var sisa = MailApp.getRemainingDailyQuota();
    if (sisa < 1) {
      return balas({
        ok: false,
        error: 'Kuota email harian habis. Pulih dalam 24 jam.',
        sisaKuota: sisa
      });
    }

    // Jangan arsipkan email yang tujuannya memang alamat arsip itu
    // sendiri — kalau tidak, setiap uji coba masuk dua kali.
    var bcc = ARSIP.filter(function (a) {
      return a.toLowerCase() !== String(d.to).toLowerCase();
    }).join(',');

    var opsi = {
      to: d.to,
      subject: d.subject,
      htmlBody: d.htmlBody || '',
      // Versi teks biasa. Pesan yang hanya berisi HTML tanpa
      // padanan teks adalah salah satu penanda spam paling tua,
      // dan sebagian klien email memang hanya membaca yang ini.
      body: d.body || 'Email ini memuat tampilan HTML. Buka dengan klien email yang mendukungnya.',
      name: NAMA_PENGIRIM
    };
    if (bcc) opsi.bcc = bcc;
    if (d.replyTo) opsi.replyTo = d.replyTo;

    MailApp.sendEmail(opsi);

    return balas({
      ok: true,
      to: d.to,
      bcc: bcc,
      sisaKuota: MailApp.getRemainingDailyQuota()
    });

  } catch (err) {
    console.error('Gagal kirim: ' + err);
    return balas({ ok: false, error: String(err) });
  }
}

/** Cek kesehatan lewat browser: buka URL /exec langsung. */
function doGet() {
  return balas({
    ok: true,
    layanan: 'AGKB 360° mail gateway',
    arsip: ARSIP,
    sisaKuota: MailApp.getRemainingDailyQuota()
  });
}

function balas(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
