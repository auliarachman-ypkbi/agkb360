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

// Arsip pemantauan. Semua email masuk ke sini diam-diam.
var ARSIP = [
  'edu@kaderbangsa.foundation',
  'aulia.rachman@kaderbangsa.foundation'
];

var NAMA_PENGIRIM = 'AGKB 360°';

function doPost(e) {
  try {
    var d = JSON.parse(e.postData.contents);

    if (!d.to || !d.subject) {
      return balas({ ok: false, error: 'to dan subject wajib diisi' });
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
