-- ============================================================
-- Migration 007: Revisi teks pertanyaan Bahasa Indonesia
-- Target: tabel `questions`, kolom `question_id_text`
-- Berlaku untuk: demo (ktb_evaluation) dan app (ktb_production)
-- ============================================================

-- ── LEADER (order_num 1–19 di eval_type_id = 1) ──────────────

-- L-1: Alignment with IB Mission & Learner Profile
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] secara konsisten mencerminkan misi IB dan profil pelajar dalam kepemimpinannya, sehingga setiap keputusan sekolah memperkuat nilai-nilai IB dan mendorong kemandirian siswa maupun guru?'
WHERE d.eval_type_id = 1 AND s.order_num = 1;

-- L-2: Strategic Vision for IB Programme
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] telah merumuskan dan mengomunikasikan rencana strategis yang jelas dan berkelanjutan untuk pengembangan program IB, dengan memperlihatkan wawasan visioner dan kemampuan beradaptasi terhadap perkembangan pendidikan ke depan?'
WHERE d.eval_type_id = 1 AND s.order_num = 2;

-- L-3: Consistency of School Goals & Priorities
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] mampu menjaga tujuan sekolah yang jelas dan konsisten sebagai pegangan praktik guru dan pengambilan keputusan, serta menjadi teladan kepemimpinan berprinsip yang selalu terbuka terhadap refleksi dan perbaikan?'
WHERE d.eval_type_id = 1 AND s.order_num = 3;

-- L-4: Communication & Clarity of Direction
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] menyampaikan harapan, jadwal, dan keputusan strategis dengan jelas, konsisten, dan transparan, serta membangun suasana di mana dialog terbuka dan mendengarkan secara aktif benar-benar terjadi?'
WHERE d.eval_type_id = 1 AND s.order_num = 4;

-- L-5: Collaborative School Culture
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] berhasil membangun lingkungan profesional yang kolaboratif dan inklusif, di mana guru merasa dihargai, didengar, dan bebas berbagi gagasan baru serta sudut pandang yang beragam?'
WHERE d.eval_type_id = 1 AND s.order_num = 5;

-- L-6: Professional Development Leadership
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] aktif mendukung pertumbuhan guru melalui akses yang merata terhadap pengembangan profesional IB yang bermakna, serta menjadi teladan sebagai pemelajar sepanjang hayat yang penuh rasa ingin tahu?'
WHERE d.eval_type_id = 1 AND s.order_num = 6;

-- L-7: Policy Alignment & Academic Integrity
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] secara sungguh-sungguh menegakkan kebijakan IB dan menumbuhkan budaya integritas akademik di seluruh sekolah, dengan selalu berlandaskan kejujuran, etika, dan keadilan dalam setiap tindakan kepemimpinannya?'
WHERE d.eval_type_id = 1 AND s.order_num = 7;

-- L-8: Visibility & Access to Leadership
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] selalu hadir dan mudah ditemui di lingkungan sekolah, serta tanggap dan responsif terhadap kebutuhan keseharian guru, siswa, maupun orang tua?'
WHERE d.eval_type_id = 1 AND s.order_num = 8;

-- L-9: Effectiveness of Decision-Making Processes
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] mengambil keputusan yang tepat waktu, logis, dan berbasis bukti, serta menjelaskan alasan di baliknya secara terbuka agar seluruh staf memahami tujuan dan arah dari setiap perubahan yang dilakukan?'
WHERE d.eval_type_id = 1 AND s.order_num = 9;

-- L-10: Quality & Fairness of Feedback to Teachers
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] memberikan umpan balik yang membangun, penuh hormat, dan dapat langsung ditindaklanjuti, sehingga benar-benar mendukung pertumbuhan profesional guru secara adil dan setara?'
WHERE d.eval_type_id = 1 AND s.order_num = 10;

-- L-11: Organisation, Timelines & Workload
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] menyusun jadwal yang realistis dan terstruktur serta memastikan kelancaran operasional sehari-hari, dengan senantiasa memperhatikan dan menjaga keseimbangan antara beban kerja dan kesejahteraan guru?'
WHERE d.eval_type_id = 1 AND s.order_num = 11;

-- L-12: Resource Management
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] mengelola dan mengalokasikan sumber daya serta fasilitas sekolah secara strategis untuk mendukung pembelajaran berbasis inkuiri dan memenuhi kebutuhan beragam warga sekolah secara optimal?'
WHERE d.eval_type_id = 1 AND s.order_num = 12;

-- L-13: Consistency of Policy Implementation
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] menerapkan kebijakan sekolah dan IB secara konsisten, sungguh-sungguh, dan adil kepada seluruh staf, serta secara aktif mencegah kebingungan maupun kesan adanya keberpihakan?'
WHERE d.eval_type_id = 1 AND s.order_num = 13;

-- L-14: Responsiveness in Problem-Solving
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] merespons permasalahan sistemik maupun operasional yang berdampak pada proses belajar mengajar secara cepat dan efektif, dengan menunjukkan inisiatif yang proaktif dan keteguhan dalam menghadapi tantangan?'
WHERE d.eval_type_id = 1 AND s.order_num = 14;

-- L-15: Support for Teacher Wellbeing
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] menunjukkan empati yang tulus dan secara nyata mendukung guru baik secara profesional maupun emosional, sehingga tercipta lingkungan kerja yang positif, aman, dan seimbang?'
WHERE d.eval_type_id = 1 AND s.order_num = 15;

-- L-16: Support for Student Agency
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] secara konsisten membangun struktur yang memberdayakan siswa untuk belajar secara aktif dan berbasis inkuiri, serta mendorong mereka untuk mengambil peran kepemimpinan dan bertindak secara bermakna dalam komunitas?'
WHERE d.eval_type_id = 1 AND s.order_num = 16;

-- L-17: Recognition & Appreciation of Teachers
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] secara konsisten mengapresiasi kerja keras guru dan merayakan pencapaian profesional mereka dengan cara yang tulus, peka terhadap budaya, dan mampu membangkitkan semangat?'
WHERE d.eval_type_id = 1 AND s.order_num = 17;

-- L-18: Fairness & Trustworthiness
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] membangun kepercayaan melalui perlakuan yang adil, tindakan yang transparan, dan komitmen yang teguh terhadap etika profesional?'
WHERE d.eval_type_id = 1 AND s.order_num = 18;

-- L-19: Support for Student Interventions
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] mendukung sistem dan struktur yang mampu merespons keberagaman kebutuhan belajar siswa, sehingga setiap siswa mendapatkan pendampingan yang sesuai dan tepat waktu?'
WHERE d.eval_type_id = 1 AND s.order_num = 19;

-- ── TEACHER (order_num 1–16 di eval_type_id = 2) ─────────────

-- T-1: Alignment with IB Mission & Learner Profile
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] mencerminkan misi IB dan profil pelajar dalam filosofi dan praktik mengajarnya, serta menunjukkan keterlibatan yang mendalam dengan bacaan, gagasan, isu terkini, dan dinamika perilaku siswa?'
WHERE d.eval_type_id = 2 AND s.order_num = 1;

-- T-2: Language Learning
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] mendukung pembelajaran bahasa — termasuk bahasa ibu dan bahasa tambahan — serta menyambut keberagaman perspektif untuk memperkaya pengalaman belajar di kelas dan memperluas pemahaman komunitas?'
WHERE d.eval_type_id = 2 AND s.order_num = 2;

-- T-3: Community Engagement
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] aktif berinteraksi dengan komunitas yang lebih luas untuk menumbuhkan tanggung jawab sosial dan semangat kewarganegaraan global, serta mendorong siswa untuk berinisiatif dan memimpin dengan penuh tujuan dan semangat pengabdian?'
WHERE d.eval_type_id = 2 AND s.order_num = 3;

-- T-4: Collaborative Culture
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] membangun budaya kolaboratif melalui interaksi profesional yang rutin dan bermakna, sehingga mendorong kerja sama dan pembelajaran bersama di antara siswa maupun sesama kolega?'
WHERE d.eval_type_id = 2 AND s.order_num = 4;

-- T-5: Professional Development
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] aktif mengikuti dan menerapkan pengembangan profesional sesuai standar IB, serta menjunjung tinggi kejujuran, etika, dan keadilan dalam kesehariannya?'
WHERE d.eval_type_id = 2 AND s.order_num = 5;

-- T-6: Policy Alignment
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] menjalankan kebijakan sekolah dengan sungguh-sungguh dan turut berkontribusi pada penyempurnaannya, serta menunjukkan keluwesan dan ketangguhan dalam menghadapi perubahan?'
WHERE d.eval_type_id = 2 AND s.order_num = 6;

-- T-7: Planning
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] merancang kurikulum yang selaras dengan persyaratan IB dan mendorong pengajaran yang inovatif, kreatif, serta penuh rasa ingin tahu dan gagasan yang segar?'
WHERE d.eval_type_id = 2 AND s.order_num = 7;

-- T-8: Interdisciplinary Connections
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] mengintegrasikan keterkaitan lintas disiplin — termasuk TOK dan konteks global — serta mengolah informasi secara bijak untuk membimbing siswa agar mampu berpikir secara mandiri dan kritis?'
WHERE d.eval_type_id = 2 AND s.order_num = 8;

-- T-9: Curriculum Reflection
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] merefleksikan dan merevisi kurikulum berdasarkan umpan balik siswa dan tuntutan program, dengan komunikasi yang jelas dan kemampuan mendengarkan secara aktif untuk membangun dialog pembelajaran yang bermakna?'
WHERE d.eval_type_id = 2 AND s.order_num = 9;

-- T-10: Inquiry-Based Learning
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] menggunakan pendekatan berbasis inkuiri untuk menumbuhkan rasa ingin tahu, keterlibatan, dan rasa kepemilikan belajar siswa, serta membimbing mereka untuk berpikir secara mandiri dan kritis?'
WHERE d.eval_type_id = 2 AND s.order_num = 10;

-- T-11: Differentiation
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] melakukan diferensiasi pembelajaran untuk memenuhi kebutuhan beragam setiap siswa secara efektif, sekaligus mendukung kesejahteraan mereka dan menjadi teladan gaya hidup yang sehat dan penuh makna?'
WHERE d.eval_type_id = 2 AND s.order_num = 11;

-- T-12: Language Support
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] memberikan dukungan yang konsisten dan terpadu untuk pengembangan kemampuan berbahasa di berbagai mata pelajaran, sambil berkomunikasi dengan jelas dan merangkul keberagaman perspektif siswa?'
WHERE d.eval_type_id = 2 AND s.order_num = 12;

-- T-13: Learner Profile Development
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] mendorong dan menilai atribut profil pelajar IB sebagai bagian dari pengalaman belajar sehari-hari, serta menjadi teladan keterbukaan pikiran, integritas, dan keseimbangan sebagai wujud pertumbuhan holistik?'
WHERE d.eval_type_id = 2 AND s.order_num = 13;

-- T-14: Alignment with IB Assessment Philosophy
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] menerapkan kriteria dan praktik penilaian IB secara konsisten untuk menghasilkan evaluasi yang autentik dan relevan, dengan tetap mengedepankan pemikiran kritis dan integritas dalam setiap keputusan?'
WHERE d.eval_type_id = 2 AND s.order_num = 14;

-- T-15: Student Reflection
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] memfasilitasi kegiatan refleksi siswa sebagai bagian yang rutin dan bermakna dari proses pembelajaran, sehingga menumbuhkan jiwa kepemimpinan dan rasa memiliki tujuan dalam diri peserta didik?'
WHERE d.eval_type_id = 2 AND s.order_num = 15;

-- T-16: Data Use
UPDATE questions q
JOIN standards s ON q.standard_id = s.id
JOIN domains d ON s.domain_id = d.id
SET q.question_id_text = 'Apakah [Nama] memanfaatkan data penilaian sebagai dasar pengambilan keputusan pembelajaran dan peningkatan hasil belajar siswa, dengan menunjukkan kemampuan analisis yang tajam dan inovatif terhadap bukti-bukti yang ada?'
WHERE d.eval_type_id = 2 AND s.order_num = 16;

-- ── VERIFIKASI (jalankan setelah UPDATE) ─────────────────────
-- SELECT s.order_num, d.eval_type_id, LEFT(q.question_id_text, 80) AS preview
-- FROM questions q
-- JOIN standards s ON q.standard_id = s.id
-- JOIN domains d ON s.domain_id = d.id
-- ORDER BY d.eval_type_id, s.order_num;
