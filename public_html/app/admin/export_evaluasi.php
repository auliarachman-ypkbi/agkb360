<?php
// AGKB 360° — Ekspor hasil evaluasi ke CSV
//
// Empat berkas terpisah, masing-masing untuk keperluan berbeda.
// Semuanya memakai calculateScores() yang sama dengan halaman
// Laporan, sehingga angkanya tidak pernah berbeda antara yang
// dilihat di layar dan yang diunduh.
//
// IDENTITAS PENILAI TIDAK PERNAH DIEKSPOR. Evaluasi 360° bersandar
// pada kesediaan orang menilai dengan jujur, dan itu runtuh begitu
// jawabannya bisa ditelusuri balik ke penilainya. Yang diekspor
// hanya jenis respondennya — atasan, rekan sejawat, murid, dan
// seterusnya.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();
requireRole(['superadmin', 'admin', 'foundation']);

$periods = Database::fetchAll(
    "SELECT id, name, year, status FROM eval_periods ORDER BY year DESC, id DESC");

$pid = (int)($_GET['periode'] ?? 0);
if (!$pid && $periods) $pid = (int)$periods[0]['id'];

$periode = $pid
    ? Database::fetchOne("SELECT * FROM eval_periods WHERE id=?", [$pid])
    : null;

// ── UNDUH ───────────────────────────────────────────────────

function keluarkanCsv(string $namaBerkas, array $judul, array $baris): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $namaBerkas . '"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    // BOM supaya Excel membaca UTF-8 dengan benar; Google Sheets
    // tidak terganggu olehnya.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $judul);
    foreach ($baris as $b) fputcsv($out, $b);
    fclose($out);
    exit;
}

$unduh = $_GET['unduh'] ?? '';

if ($unduh && $periode) {
    $tag = preg_replace('/[^A-Za-z0-9]+/', '-', $periode['name'] . '-' . $periode['year']);

    // Orang yang dinilai pada periode ini
    $orang = Database::fetchAll("
        SELECT u.id, u.name, u.email, u.role, et.name AS jenis
        FROM period_evaluatees pe
        JOIN users u ON u.id = pe.user_id
        LEFT JOIN eval_types et ON et.id = pe.eval_type_id
        WHERE pe.period_id = ? AND pe.is_active = 1
        ORDER BY u.name", [$pid]);

    // ── 1. Ringkasan per orang ──────────────────────────────
    if ($unduh === 'ringkasan') {
        $domainNama = array_column(Database::fetchAll("
            SELECT DISTINCT d.name
            FROM assignments a
            JOIN responses r ON r.assignment_id = a.id
            JOIN questions q ON q.id = r.question_id
            JOIN standards s ON s.id = q.standard_id
            JOIN domains d   ON d.id = s.domain_id
            WHERE a.period_id = ? ORDER BY d.name", [$pid]), 'name');

        $judul = array_merge(
            ['Nama', 'Email', 'Peran', 'Jenis evaluasi', 'Penilai selesai', 'Penilai ditugaskan', 'Skor keseluruhan'],
            $domainNama);

        $baris = [];
        foreach ($orang as $o) {
            $s = calculateScores((int)$o['id'], $pid);
            $c = Database::fetchOne("
                SELECT COUNT(*) AS total, SUM(status='completed') AS selesai
                FROM assignments WHERE evaluatee_id=? AND period_id=?", [$o['id'], $pid]);

            $perDomain = [];
            foreach ($s['byDomain'] as $d) $perDomain[$d['name'] ?? ''] = $d['avg'] ?? '';

            $baris[] = array_merge([
                $o['name'], $o['email'], roleLabel($o['role']), $o['jenis'] ?? '',
                (int)($c['selesai'] ?? 0), (int)($c['total'] ?? 0),
                $s['overall'] ?: '',
            ], array_map(fn($n) => $perDomain[$n] ?? '', $domainNama));
        }
        keluarkanCsv("ringkasan-$tag.csv", $judul, $baris);
    }

    // ── 2. Skor per standar ─────────────────────────────────
    if ($unduh === 'standar') {
        $baris = [];
        foreach ($orang as $o) {
            $s = calculateScores((int)$o['id'], $pid);
            foreach ($s['byStandard'] as $st) {
                $baris[] = [
                    $o['name'], $o['email'], roleLabel($o['role']),
                    $st['domain_name'] ?? '', $st['name'] ?? '',
                    $st['avg'] ?? '', count($st['grades'] ?? []),
                ];
            }
        }
        keluarkanCsv("skor-per-standar-$tag.csv",
            ['Nama', 'Email', 'Peran', 'Domain', 'Standar', 'Rata-rata', 'Jumlah jawaban'], $baris);
    }

    // ── 3. Jawaban mentah ───────────────────────────────────
    // Tanpa identitas penilai. Yang tersimpan hanya jenis
    // respondennya, dan itu memang yang berguna untuk analisis.
    if ($unduh === 'jawaban') {
        $baris = Database::fetchAll("
            SELECT ev.name AS dinilai, ev.email AS email_dinilai,
                   ev.role AS peran_dinilai,
                   COALESCE(p.respondent_type,'—') AS jenis_penilai,
                   CASE WHEN p.is_self_reflection = 1 THEN 'Ya' ELSE 'Tidak' END AS refleksi_mandiri,
                   d.name AS domain, s.name AS standar,
                   q.question_id_text AS pertanyaan,
                   r.grade AS nilai,
                   COALESCE(r.notes,'') AS catatan,
                   a.completed_at AS diselesaikan
            FROM responses r
            JOIN assignments a ON a.id = r.assignment_id
            JOIN users ev      ON ev.id = a.evaluatee_id
            JOIN packages p    ON p.id = a.package_id
            JOIN questions q   ON q.id = r.question_id
            JOIN standards s   ON s.id = q.standard_id
            JOIN domains d     ON d.id = s.domain_id
            WHERE a.period_id = ? AND a.status = 'completed' AND r.is_test = 0
            ORDER BY ev.name, d.name, s.name", [$pid]);

        keluarkanCsv("jawaban-$tag.csv",
            ['Dinilai', 'Email', 'Peran', 'Jenis penilai', 'Refleksi mandiri',
             'Domain', 'Standar', 'Pertanyaan', 'Nilai', 'Catatan', 'Diselesaikan'],
            array_map('array_values', $baris));
    }

    // ── 4. Kelengkapan pengisian ────────────────────────────
    if ($unduh === 'kelengkapan') {
        $baris = Database::fetchAll("
            SELECT ev.name AS dinilai, ev.email,
                   COALESCE(p.respondent_type,'—') AS jenis_penilai,
                   COUNT(*) AS ditugaskan,
                   SUM(a.status='completed') AS selesai,
                   SUM(a.status='in_progress') AS sedang_diisi,
                   SUM(a.status='pending') AS belum_dibuka
            FROM assignments a
            JOIN users ev   ON ev.id = a.evaluatee_id
            JOIN packages p ON p.id = a.package_id
            WHERE a.period_id = ?
            GROUP BY ev.id, ev.name, ev.email, p.respondent_type
            ORDER BY ev.name, p.respondent_type", [$pid]);

        keluarkanCsv("kelengkapan-$tag.csv",
            ['Dinilai', 'Email', 'Jenis penilai', 'Ditugaskan', 'Selesai', 'Sedang diisi', 'Belum dibuka'],
            array_map('array_values', $baris));
    }
}

// ── TAMPILAN ────────────────────────────────────────────────

$jml = $periode ? Database::fetchOne("
    SELECT
      (SELECT COUNT(*) FROM period_evaluatees WHERE period_id=? AND is_active=1) AS dinilai,
      (SELECT COUNT(*) FROM assignments WHERE period_id=?) AS tugas,
      (SELECT COUNT(*) FROM assignments WHERE period_id=? AND status='completed') AS selesai,
      (SELECT COUNT(*) FROM responses r JOIN assignments a ON a.id=r.assignment_id
        WHERE a.period_id=? AND r.is_test=0) AS jawaban
", [$pid, $pid, $pid, $pid]) : null;

$berkas = [
    ['ringkasan',  'Ringkasan per orang',
     'Satu baris per orang yang dinilai: skor keseluruhan, skor tiap domain, dan berapa penilai yang sudah mengisi. Paling cocok untuk membandingkan antarorang.'],
    ['standar',    'Skor per standar',
     'Rincian sampai tingkat standar penilaian. Untuk melihat aspek mana yang menonjol dan mana yang tertinggal pada seseorang.'],
    ['jawaban',    'Jawaban mentah',
     'Setiap jawaban sebagai satu baris, lengkap dengan catatan tertulis penilai. Paling lengkap, dan paling besar berkasnya.'],
    ['kelengkapan','Kelengkapan pengisian',
     'Berapa penilai yang ditugaskan dan berapa yang sudah mengisi, dikelompokkan menurut jenis penilai. Untuk menilai seberapa dapat dipercaya angkanya.'],
];

ob_start(); ?>
<style>
.ex-kepala{background:#fff;border:1px solid #e3e5ea;border-radius:12px;padding:16px 18px;margin-bottom:14px}
.ex-pilih{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap}
.ex-f label{display:block;font-size:10.5px;font-weight:600;color:#6b6a83;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.ex-f select{border:1px solid #e3e5ea;border-radius:8px;padding:8px 12px;font-size:13.5px;font-family:inherit;color:#040136;outline:none;background:#fff;min-width:280px}
.ex-f select:focus{border-color:#040136;box-shadow:0 0 0 3px rgba(4,1,54,.08)}
.ex-angka{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-top:14px}
.ex-a{background:#fafafb;border-radius:9px;padding:11px 14px}
.ex-a b{display:block;font-size:21px;color:#040136;line-height:1.2}
.ex-a span{font-size:11.5px;color:#6b6a83}
.ex-kartu{background:#fff;border:1px solid #e3e5ea;border-radius:12px;padding:16px 18px;margin-bottom:10px;display:flex;gap:16px;align-items:center;flex-wrap:wrap}
.ex-kartu-isi{flex:1;min-width:260px}
.ex-kartu-nama{font-size:15px;font-weight:600;color:#040136;margin-bottom:3px}
.ex-kartu-ket{font-size:12.5px;color:#6b6a83;line-height:1.6}
.ex-unduh{background:#040136;color:#fff;border-radius:8px;padding:10px 20px;font-size:13px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:7px;white-space:nowrap}
.ex-unduh:hover{background:#030870;color:#fff;text-decoration:none}
.ex-nota{background:#eeebfc;border:1px solid #b9aef2;border-radius:10px;padding:13px 16px;font-size:12.5px;color:#030870;line-height:1.65;margin-top:14px}
</style>

<?= showFlash() ?>

<div class="ex-kepala">
  <form method="get" class="ex-pilih">
    <div class="ex-f">
      <label>Periode evaluasi</label>
      <select name="periode" onchange="this.form.submit()">
        <?php foreach ($periods as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $pid === (int)$p['id'] ? 'selected' : '' ?>>
          <?= h($p['name']) ?> · <?= (int)$p['year'] ?> · <?= h(ucfirst($p['status'])) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <?php if ($jml): ?>
  <div class="ex-angka">
    <div class="ex-a"><b><?= (int)$jml['dinilai'] ?></b><span>Orang dinilai</span></div>
    <div class="ex-a"><b><?= (int)$jml['tugas'] ?></b><span>Penilaian ditugaskan</span></div>
    <div class="ex-a"><b><?= (int)$jml['selesai'] ?></b><span>Sudah diisi</span></div>
    <div class="ex-a"><b><?= (int)$jml['jawaban'] ?></b><span>Butir jawaban</span></div>
  </div>
  <?php endif; ?>
</div>

<?php if (!$periode): ?>
<div class="ex-kepala">Belum ada periode evaluasi.</div>
<?php else: ?>

<?php foreach ($berkas as [$kode, $nama, $ket]): ?>
<div class="ex-kartu">
  <div class="ex-kartu-isi">
    <div class="ex-kartu-nama"><?= h($nama) ?></div>
    <div class="ex-kartu-ket"><?= h($ket) ?></div>
  </div>
  <a class="ex-unduh" href="?periode=<?= $pid ?>&unduh=<?= $kode ?>">
    <i class="bi bi-download"></i>Unduh CSV
  </a>
</div>
<?php endforeach; ?>

<div class="ex-nota">
  <strong>Identitas penilai tidak ikut diekspor.</strong>
  Evaluasi 360° bersandar pada kesediaan orang menilai dengan jujur, dan itu runtuh
  begitu jawabannya dapat ditelusuri balik ke penilainya. Yang tercantum hanya jenis
  respondennya, misalnya atasan, rekan sejawat, atau murid yang diajar.
  <br><br>
  Berkas CSV dapat dibuka langsung di Excel, atau diunggah ke Google Sheets lewat
  Berkas → Impor. Angkanya sama persis dengan yang tampil di halaman Laporan.
</div>

<?php endif; ?>

<?php
$content = ob_get_clean();
pageWrapper('Ekspor Hasil Evaluasi', $content);
