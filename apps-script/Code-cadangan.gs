/**
 * ============================================================
 * AGKB 360° — Gerbang Email Cadangan
 * ------------------------------------------------------------
 * Salinan gerbang utama, dipasang pada akun Google yang berbeda
 * sehingga punya kuota harian sendiri. Dipakai ketika kuota
 * gerbang utama habis.
 *
 * Kontraknya sama persis dengan gerbang utama — payload masuk
 * { to, subject, htmlBody, body, replyTo }, balasan keluar
 * { ok, ... } — sehingga dapat dipertukarkan tanpa mengubah
 * apa pun di sisi PHP.
 *
 * ⚠ Alamat pengirim mengikuti akun pemilik skrip ini. Bila
 *   akunnya bukan domain sekolah, email akan tampil berasal dari
 *   alamat itu, dan tidak menikmati tanda tangan DKIM domain
 *   sekolah. Pantas untuk keadaan mendesak, tidak untuk seterusnya.
 *
 * ⚠ WAJIB deploy sebagai Web app dengan:
 *     Execute as        : Me
 *     Who has access    : Anyone
 *   Lalu salin URL yang berakhiran /exec.
 * ============================================================
 */

var NAMA_PENGIRIM = 'AGKB 360°';

function doPost(e) {
  try {
    var d = JSON.parse(e.postData.contents);

    if (!d.to || !d.subject) {
      return balas({ ok: false, error: 'to dan subject wajib diisi' });
    }

    var sisa = MailApp.getRemainingDailyQuota();
    if (sisa < 1) {
      return balas({
        ok: false,
        error: 'Kuota email harian habis pada gerbang cadangan. Pulih dalam 24 jam.',
        sisaKuota: sisa
      });
    }

    var opsi = {
      to: d.to,
      subject: d.subject,
      htmlBody: d.htmlBody || '',
      body: d.body || 'Email ini memuat tampilan HTML. Buka dengan klien email yang mendukungnya.',
      name: NAMA_PENGIRIM
    };
    if (d.replyTo) opsi.replyTo = d.replyTo;

    MailApp.sendEmail(opsi);

    return balas({
      ok: true,
      to: d.to,
      gerbang: 'cadangan',
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
    layanan: 'AGKB 360° mail gateway (cadangan)',
    sisaKuota: MailApp.getRemainingDailyQuota()
  });
}

function balas(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
