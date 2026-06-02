<?php
/**
 * KTB 360° Evaluation Platform — Setup & Installer
 * Run this once after uploading files to your hosting.
 * DELETE or RENAME this file after setup is complete.
 */

define('SETUP_MODE', true);
require_once __DIR__ . '/config/config.php';

$step = $_GET['step'] ?? 'welcome';
$msg  = '';
$error = '';

// ── PDO without DB (for creation) ────────────────────────────
function getAdminPDO(): PDO {
    $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
    return new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function getPDO(): PDO {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    return new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}

// ── STEP: CREATE DATABASE & TABLES ───────────────────────────
function createDatabase(): bool {
    try {
        $pdo = getAdminPDO();
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = getPDO();
        // Drop existing tables first (clean install)
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $tables = ['ai_suggestions','responses','assignments','package_weights','package_questions',
                   'packages','grade_descriptors','questions','standard_traits','standards',
                   'domains','eval_periods','user_groups','`groups`','users','traits','eval_types','settings'];
        foreach ($tables as $t) {
            $pdo->exec("DROP TABLE IF EXISTS $t");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        $schema = file_get_contents(__DIR__ . '/sql/schema.sql');
        // Split by semicolons and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) $pdo->exec($stmt);
        }
        return true;
    } catch (Exception $e) {
        die("Database error: " . $e->getMessage());
    }
}

// ── SEED: MASTER DATA ─────────────────────────────────────────
function seedMasterData(PDO $pdo): void {
    // Eval Types
    $pdo->exec("INSERT IGNORE INTO eval_types (id,code,name) VALUES (1,'leader','Pimpinan Sekolah'),(2,'teacher','Guru')");

    // Traits (10 traits)
    $traits = [
        [1,'Highly Literate','Terlibat secara mendalam dengan riset pendidikan, kebijakan, isu global, dan dinamika sekolah untuk mendukung pengambilan keputusan strategis.'],
        [2,'Open Minded','Menghargai perspektif yang beragam dari guru, siswa, dan orang tua untuk panduan kebijakan inklusif.'],
        [3,'Critical Thinker','Mengevaluasi informasi, praktik, dan tantangan dengan analisis yang cermat berbasis bukti.'],
        [4,'Communicative','Berkomunikasi dengan transparansi dan kejelasan, serta mendengarkan secara aktif.'],
        [5,'Integrity','Memodelkan perilaku etis, keadilan, dan akuntabilitas.'],
        [6,'Collaborative','Mendorong lingkungan di mana kerja tim berkembang dan upaya kolektif mendorong kemajuan sekolah.'],
        [7,'Adaptable','Merespons secara efektif terhadap perubahan tuntutan pendidikan, kebijakan, dan kebutuhan komunitas.'],
        [8,'Balanced','Mendorong kesejahteraan di seluruh komunitas sekolah dengan beban kerja yang berkelanjutan.'],
        [9,'Innovative','Memimpin dengan visi dan kreativitas, mendorong ide-ide baru dan pendekatan berpikiran maju.'],
        [10,'Leadership','Memberikan arah strategis yang jelas dan membuat keputusan bijaksana yang mendukung pertumbuhan jangka panjang.'],
    ];
    $st = $pdo->prepare("INSERT IGNORE INTO traits (code,name,description) VALUES (?,?,?)");
    foreach ($traits as $t) $st->execute($t);

    // ── LEADER DOMAINS ────────────────────────────────────────
    $pdo->exec("INSERT IGNORE INTO domains (id,eval_type_id,code,name,order_num) VALUES
      (1,1,'A','IB Philosophy & Vision',1),
      (2,1,'B','Organizational & Professional Leadership',2),
      (3,1,'C','Operational & Programme Management',3),
      (4,1,'D','Teacher & Student Support',4),
      (5,2,'A','IB Philosophy',1),
      (6,2,'B','Organization & Professional Responsibilities',2),
      (7,2,'C','Curriculum Implementation',3),
      (8,2,'D','Teaching and Learning',4),
      (9,2,'E','Assessment',5)");

    // ── LEADER STANDARDS (19) ─────────────────────────────────
    $lStandards = [
        // domain_id, name, extended_description, order
        [1,'Alignment with IB Mission & Learner Profile','Demonstrates strong alignment with the IB mission and learner profile in administrative philosophy and practice, ensuring schoolwide decisions reflect IB values and empower both student and teacher agency.',1],
        [1,'Strategic Vision for IB Programme','Develops and articulates a clear, sustainable strategic plan for programme growth, guiding long-term improvement while demonstrating visionary thinking and adaptability to future educational trends.',2],
        [1,'Consistency of School Goals & Priorities','Maintains clear, consistent schoolwide goals that anchor teacher practice and institutional decision-making over time, modeling principled leadership and a commitment to continuous reflection.',3],
        [2,'Communication & Clarity of Direction','Communicates expectations, timelines, and strategic decisions with exceptional clarity, consistency, and transparency, fostering an environment of open dialogue and active listening.',4],
        [2,'Collaborative School Culture','Fosters a highly collaborative and inclusive professional environment where teachers feel valued, heard, and encouraged to share innovative ideas and diverse perspectives.',5],
        [2,'Professional Development Leadership','Champions teacher growth by providing equitable access to meaningful IB professional development, and actively participates as a lifelong learner to model intellectual curiosity.',6],
        [2,'Policy Alignment & Academic Integrity','Ensures rigorous adherence to IB policies and embeds a culture of academic integrity, acting consistently with honesty, ethics, and fairness in all leadership actions.',7],
        [2,'Visibility & Access to Leadership','Maintains a consistent, approachable presence across the school community, remaining accessible and highly responsive to the daily realities of teachers, students, and parents.',8],
        [2,'Effectiveness of Decision-Making Processes','Makes timely, logical, and evidence-based decisions, communicating the underlying rationale clearly to ensure staff understand the purpose and vision behind organizational changes.',9],
        [2,'Quality & Fairness of Feedback to Teachers','Provides constructive, respectful, and highly actionable feedback that meaningfully supports teacher professional growth while maintaining strict equity and fairness.',10],
        [3,'Organisation, Timelines & Workload','Creates realistic, well-structured timelines and ensures smooth daily operations, demonstrating a balanced approach that actively respects and manages teacher workload and wellbeing.',11],
        [3,'Resource Management','Strategically allocates and manages instructional resources and school facilities to optimally support inquiry-based learning and the diverse needs of the school community.',12],
        [3,'Consistency of Policy Implementation','Applies school and IB policies with fidelity, consistency, and fairness across all staff members, proactively avoiding confusion or any perception of bias.',13],
        [3,'Responsiveness in Problem-Solving','Responds promptly and effectively to systemic or daily issues affecting teaching and learning, demonstrating proactive problem-solving initiative and resilience in the face of challenges.',14],
        [4,'Support for Teacher Wellbeing','Demonstrates deep empathy and actively supports teachers both professionally and emotionally, cultivating a positive, safe, and balanced work environment.',15],
        [4,'Support for Student Agency','Systematically promotes structures that empower student-centered, inquiry-based learning, encouraging students to take meaningful action and leadership roles within the community.',16],
        [4,'Recognition & Appreciation of Teachers','Consistently acknowledges teacher effort and celebrates professional achievements in a meaningful, culturally responsive, and motivating manner.',17],
        [4,'Fairness & Trustworthiness','Builds trust through equitable treatment, transparent actions, and adherence to professional ethics.',18],
        [4,'Support for Student Interventions','Supports structures and systems that address diverse learning needs, ensuring students receive appropriate interventions.',19],
    ];
    $st = $pdo->prepare("INSERT INTO standards (domain_id,name,extended_description,order_num) VALUES (?,?,?,?)");
    foreach ($lStandards as $s) $st->execute($s);

    // ── TEACHER STANDARDS (16) ────────────────────────────────
    $tStandards = [
        [5,'Alignment with IB Mission & Learner Profile','Demonstrates alignment with the IB mission and learner profile in teaching philosophy and practice, engaging with books, ideas, current issues, and student behaviour with depth and insight.',1],
        [5,'Language Learning','Supports language learning, including both mother tongue and additional languages, while welcoming diverse perspectives to enrich classroom learning and community understanding.',2],
        [5,'Community Engagement','Engages with the wider community to promote responsible action and global citizenship, empowering students to take initiative and lead with purpose and service.',3],
        [6,'Collaborative Culture','Fosters a collaborative culture through regular and meaningful professional interactions, promoting teamwork and shared learning among students and colleagues.',4],
        [6,'Professional Development','Participates in and applies professional development aligned with IB expectations, while acting consistently with honesty, ethics, and fairness.',5],
        [6,'Policy Alignment','Implements school policies with fidelity and contributes to their ongoing refinement, responding flexibly to change and modeling resilience.',6],
        [7,'Planning','Develops curriculum plans that align with IB requirements and promote innovative teaching, encouraging creativity, curiosity, and fresh thinking.',7],
        [7,'Interdisciplinary Connections','Integrates interdisciplinary connections, including TOK and global contexts, analysing information thoughtfully to guide students in independent thinking.',8],
        [7,'Curriculum Reflection','Reflects on and revises curriculum based on student feedback and programme requirements, with clear communication and active listening to build meaningful dialogue.',9],
        [8,'Inquiry-Based Learning','Uses inquiry-based strategies to foster student curiosity, engagement, and ownership, guiding students to think independently and critically.',10],
        [8,'Differentiation','Differentiates instruction to effectively address the diverse needs of all learners, while supporting student well-being and modelling a healthy, purposeful lifestyle.',11],
        [8,'Language Support','Provides consistent and embedded support for language development across subjects, communicating clearly while embracing diverse perspectives.',12],
        [9,'Learner Profile Development','Promotes and assesses IB learner profile attributes as part of daily learning experiences, modelling open-mindedness, integrity, and balance as part of holistic growth.',13],
        [9,'Alignment with IB Assessment Philosophy','Applies IB assessment criteria and practices to ensure authentic and aligned evaluation, while demonstrating critical thinking and ethical decision-making.',14],
        [9,'Student Reflection','Facilitates student reflection as a meaningful and routine part of the learning process, encouraging leadership and a sense of purpose in learners.',15],
        [9,'Data Use','Uses assessment data to inform instruction and improve student learning outcomes, demonstrating high-level literacy and innovation in analysing evidence.',16],
    ];
    foreach ($tStandards as $s) $st->execute($s);
}

// ── SEED: STANDARD-TRAIT MAPPINGS ────────────────────────────
function seedTraitMappings(PDO $pdo): void {
    // Leader standard trait mappings (standard order_num → trait codes)
    // Based on the rubric document
    $lMappings = [
        1 => [1],        // Alignment with IB Mission → Highly Literate
        2 => [9],        // Strategic Vision → Innovative
        3 => [3],        // Consistency of Goals → Critical Thinker
        4 => [4],        // Communication → Communicative
        5 => [10,6],     // Collaborative Culture → Leadership, Collaborative
        6 => [10,6],     // PD Leadership → Leadership, Collaborative
        7 => [10,5],     // Policy Integrity → Leadership, Integrity
        8 => [6,4],      // Visibility → Collaborative, Communicative
        9 => [10,3],     // Decision Making → Leadership, Critical Thinker
        10 => [5,4],     // Feedback Quality → Integrity, Communicative
        11 => [8],       // Organisation/Workload → Balanced
        12 => [7],       // Resource Mgmt → Adaptable
        13 => [7],       // Consistency Policy → Adaptable
        14 => [3,7],     // Responsiveness → Critical Thinker, Adaptable
        15 => [8],       // Teacher Wellbeing → Balanced
        16 => [10],      // Student Agency → Leadership
        17 => [4,5],     // Recognition → Communicative, Integrity
        18 => [4,5],     // Fairness → Communicative, Integrity
        19 => [2,7],     // Student Interventions → Open Minded, Adaptable
    ];

    // Teacher standard trait mappings (order_num → trait codes)
    $tMappings = [
        1 => [1],        // IB Alignment → Highly Literate
        2 => [2],        // Language Learning → Open Minded
        3 => [10],       // Community Engagement → Leadership
        4 => [6],        // Collaborative Culture → Collaborative
        5 => [5],        // Professional Dev → Integrity
        6 => [7],        // Policy Alignment → Adaptable
        7 => [9],        // Planning → Innovative
        8 => [3],        // Interdisciplinary → Critical Thinker
        9 => [4],        // Curriculum Reflection → Communicative
        10 => [3],       // Inquiry → Critical Thinker
        11 => [8],       // Differentiation → Balanced
        12 => [4],       // Language Support → Communicative
        13 => [2,5,8],   // Learner Profile → Open Minded, Integrity, Balanced
        14 => [3,5],     // IB Assessment → Critical Thinker, Integrity
        15 => [10],      // Student Reflection → Leadership
        16 => [1,9],     // Data Use → Highly Literate, Innovative
    ];

    // Get trait IDs by code
    $traitIdByCode = [];
    foreach ($pdo->query("SELECT id, code FROM traits") as $row) {
        $traitIdByCode[(int)$row['code']] = (int)$row['id'];
    }

    // Get standard IDs by order_num for leaders (domain 1-4)
    $leaderStandards = $pdo->query("
        SELECT s.id, s.order_num FROM standards s 
        JOIN domains d ON s.domain_id = d.id 
        WHERE d.eval_type_id = 1 ORDER BY s.order_num
    ")->fetchAll();
    
    $leaderStdById = [];
    foreach ($leaderStandards as $s) {
        $leaderStdById[(int)$s['order_num']] = (int)$s['id'];
    }

    // Get standard IDs for teachers (domain 5-9)
    $teacherStandards = $pdo->query("
        SELECT s.id, s.order_num FROM standards s 
        JOIN domains d ON s.domain_id = d.id 
        WHERE d.eval_type_id = 2 ORDER BY s.order_num
    ")->fetchAll();
    
    $teacherStdById = [];
    foreach ($teacherStandards as $s) {
        $teacherStdById[(int)$s['order_num']] = (int)$s['id'];
    }

    $ins = $pdo->prepare("INSERT IGNORE INTO standard_traits (standard_id, trait_id) VALUES (?,?)");
    
    foreach ($lMappings as $orderNum => $traitCodes) {
        $stdId = $leaderStdById[$orderNum] ?? null;
        if (!$stdId) continue;
        foreach ($traitCodes as $tc) {
            $tid = $traitIdByCode[$tc] ?? null;
            if ($tid) $ins->execute([$stdId, $tid]);
        }
    }
    foreach ($tMappings as $orderNum => $traitCodes) {
        $stdId = $teacherStdById[$orderNum] ?? null;
        if (!$stdId) continue;
        foreach ($traitCodes as $tc) {
            $tid = $traitIdByCode[$tc] ?? null;
            if ($tid) $ins->execute([$stdId, $tid]);
        }
    }
}

// ── SEED: QUESTIONS & GRADE DESCRIPTORS ──────────────────────
function seedQuestions(PDO $pdo): void {
    // Get all standards with their order_nums and eval_type
    $standards = $pdo->query("
        SELECT s.id, s.order_num, d.eval_type_id, s.name
        FROM standards s JOIN domains d ON s.domain_id = d.id
        ORDER BY d.eval_type_id, s.order_num
    ")->fetchAll();

    $stdMap = []; // [eval_type_id][order_num] => standard_id
    foreach ($standards as $s) {
        $stdMap[$s['eval_type_id']][$s['order_num']] = $s['id'];
    }

    // Leader questions (1 per standard, order_num 1-19)
    $leaderQs = [
        1 => ['Apakah [Nama] telah menunjukkan keselarasan yang kuat dengan misi IB dan profil pelajar dalam filosofi administrasi dan praktik kepemimpinan, memastikan setiap keputusan sekolah mencerminkan nilai-nilai IB serta memberdayakan agency siswa maupun guru?','Has [Name] demonstrated strong alignment with the IB mission and learner profile in administrative philosophy and practice, ensuring schoolwide decisions reflect IB values and empower both student and teacher agency?',
            ['Tidak ada rujukan terhadap misi IB; keputusan tidak mencerminkan nilai-nilai IB.','Menunjukkan pemahaman dasar namun penerapannya terbatas dan tidak konsisten.','Secara konsisten menerapkan filosofi IB dalam setiap keputusan.','Menginspirasi keterlibatan seluruh sekolah dengan nilai-nilai IB.'],
            ['No reference to IB mission; decisions do not reflect IB values.','Shows basic understanding with inconsistent / limited application.','Consistently applies IB philosophy in decisions.','Inspires whole-school engagement with IB values.']],
        2 => ['Apakah [Nama] telah mengembangkan dan mengartikulasikan rencana strategis yang jelas dan berkelanjutan untuk pertumbuhan program IB, mengarahkan peningkatan jangka panjang dengan menunjukkan pemikiran visioner dan kemampuan beradaptasi terhadap tren pendidikan masa depan?','Has [Name] developed and articulated a clear, sustainable strategic plan for programme growth, guiding long-term improvement while demonstrating visionary thinking and adaptability to future educational trends?',
            ['Tidak ada arah jangka panjang yang jelas untuk program.','Visi ada namun kurang koheren atau tidak ditindaklanjuti.','Strategi yang koheren dengan pencapaian yang jelas.','Sangat strategis, berpikiran maju, dan mendorong inovasi.'],
            ['No clear long-term direction for the programme.','Vision exists but lacks coherence or follow-through.','Coherent strategy with clear milestones.','Highly strategic, forward-thinking, and drives innovation.']],
        3 => ['Apakah [Nama] telah mempertahankan tujuan sekolah yang jelas dan konsisten sebagai acuan praktik guru dan pengambilan keputusan institusional, memodelkan kepemimpinan berprinsip dan komitmen terhadap refleksi berkelanjutan?','Has [Name] maintained clear, consistent schoolwide goals that anchor teacher practice and institutional decision-making over time, modeling principled leadership and a commitment to continuous reflection?',
            ['Prioritas sering berubah; ekspektasi tidak jelas.','Beberapa prioritas dikomunikasikan namun tidak konsisten.','Tujuan yang jelas konsisten menjadi rujukan dalam tindakan dan komunikasi.','Tujuan secara konsisten memandu keputusan dan mendorong tujuan kolektif.'],
            ['Priorities shift frequently; unclear expectations.','Some priorities communicated but inconsistent.','Clear goals consistently referenced in actions and communication.','Goals consistently guide decisions and foster collective purpose.']],
        4 => ['Apakah [Nama] mengomunikasikan ekspektasi, jadwal, dan keputusan strategis dengan kejelasan, konsistensi, dan transparansi yang luar biasa, mendorong lingkungan dialog terbuka dan mendengarkan secara aktif?','Has [Name] communicated expectations, timelines, and strategic decisions with exceptional clarity, consistency, and transparency, fostering an environment of open dialogue and active listening?',
            ['Komunikasi tidak jelas atau tidak konsisten.','Komunikasi kadang tidak jelas atau terlambat.','Komunikasi jelas, tepat waktu, dan konsisten.','Komunikasi memperkuat budaya dan mengurangi ketidakpastian.'],
            ['Unclear or inconsistent communication.','Communication sometimes unclear or late.','Clear, timely, and consistent communication.','Communication strengthens culture and reduces uncertainty.']],
        5 => ['Apakah [Nama] telah membangun lingkungan profesional yang sangat kolaboratif dan inklusif, di mana guru merasa dihargai, didengar, dan didorong untuk berbagi ide inovatif serta perspektif yang beragam?','Has [Name] fostered a highly collaborative and inclusive professional environment where teachers feel valued, heard, and encouraged to share innovative ideas and diverse perspectives?',
            ['Jarang melibatkan guru dalam pengambilan keputusan.','Keterlibatan sesekali tetapi tidak konsisten.','Kolaborasi dan pengambilan keputusan bersama secara teratur.','Budaya kolaboratif yang kuat yang memberdayakan guru.'],
            ['Rarely involves teachers in decisions.','Occasional involvement but inconsistent.','Regular collaboration and shared decision-making.','Strong collaborative culture empowering teachers.']],
        6 => ['Apakah [Nama] telah mengadvokasi pertumbuhan guru dengan memberikan akses yang setara terhadap pengembangan profesional IB yang bermakna, serta berpartisipasi aktif sebagai pelajar seumur hidup untuk memodelkan rasa ingin tahu intelektual?','Has [Name] championed teacher growth by providing equitable access to meaningful IB professional development, and actively participated as a lifelong learner to model intellectual curiosity?',
            ['Tidak mendukung pertumbuhan guru.','Menyediakan PD namun kurang tindak lanjut.','Mendukung PD dan praktik reflektif.','Memimpin budaya PD dan memodelkan pembelajaran berkelanjutan.'],
            ['Does not support teacher growth.','Provides PD but lacks follow-up.','Supports PD and reflective practice.','Leads PD culture and models continuous learning.']],
        7 => ['Apakah [Nama] telah memastikan kepatuhan yang ketat terhadap kebijakan IB dan menanamkan budaya integritas akademik di seluruh sekolah, dengan bertindak konsisten berdasarkan kejujuran, etika, dan keadilan dalam semua tindakan kepemimpinan?','Has [Name] ensured rigorous adherence to IB policies and embedded a culture of academic integrity, acting consistently with honesty, ethics, and fairness in all leadership actions?',
            ['Penegakan kebijakan tidak konsisten.','Menyadari kebijakan namun penerapannya tidak konsisten.','Menerapkan kebijakan secara bertanggung jawab.','Membentuk pembaruan kebijakan dan menjadi model integritas.'],
            ['Inconsistent enforcement.','Aware but inconsistently applied.','Applies policies responsibly.','Shapes policy refinement and models integrity.']],
        8 => ['Apakah [Nama] mempertahankan kehadiran yang konsisten dan mudah dijangkau di seluruh komunitas sekolah, tetap aksesibel dan sangat responsif terhadap realitas keseharian guru, siswa, dan orang tua?','Has [Name] maintained a consistent, approachable presence across the school community, remaining accessible and highly responsive to the daily realities of teachers, students, and parents?',
            ['Pemimpin jarang terlihat atau sulit dijangkau.','Sesekali hadir namun tidak konsisten aksesibel.','Hadir, mudah didekati, dan terlibat dengan staf.','Sangat aksesibel dan dipercaya oleh seluruh komunitas.'],
            ['Leader rarely visible or difficult to reach.','Occasionally present but not consistently accessible.','Present, approachable, and engaged with staff.','Deeply accessible and trusted across the whole community.']],
        9 => ['Apakah [Nama] telah membuat keputusan yang tepat waktu, logis, dan berbasis bukti, mengomunikasikan rasionalitas di baliknya dengan jelas agar seluruh staf memahami tujuan dan visi dari setiap perubahan organisasional?','Has [Name] made timely, logical, and evidence-based decisions, communicating the underlying rationale clearly to ensure staff understand the purpose and vision behind organisational changes?',
            ['Keputusan tidak dapat diprediksi atau dikomunikasikan dengan buruk.','Keputusan diambil namun rasionalitasnya tidak jelas.','Keputusan tepat waktu, logis, dan dikomunikasikan dengan baik.','Keputusan transparan, konsultatif, dan membangun kepercayaan.'],
            ['Decisions unpredictable or poorly communicated.','Decisions made but with unclear rationale.','Decisions timely, logical, and well communicated.','Decisions transparent, consultative, and build trust.']],
        10 => ['Apakah [Nama] memberikan umpan balik yang konstruktif, penuh respek, dan sangat dapat ditindaklanjuti, yang secara bermakna mendukung pertumbuhan profesional guru dengan tetap menjaga kesetaraan dan keadilan?','Has [Name] provided constructive, respectful, and highly actionable feedback that meaningfully supports teacher professional growth while maintaining strict equity and fairness?',
            ['Umpan balik tidak ada atau tidak membantu.','Umpan balik ada namun kabur atau jarang.','Umpan balik adil, konstruktif, dan dapat ditindaklanjuti.','Umpan balik sangat mendukung dan meningkatkan pertumbuhan guru.'],
            ['Feedback absent or unhelpful.','Feedback exists but vague or infrequent.','Feedback fair, constructive, and actionable.','Feedback deeply supportive and improves teacher growth.']],
        11 => ['Apakah [Nama] membuat jadwal yang realistis dan terstruktur dengan baik serta memastikan kelancaran operasional harian, menunjukkan pendekatan yang seimbang yang secara aktif menghormati dan mengelola beban kerja serta kesejahteraan guru?','Has [Name] created realistic, well-structured timelines and ensured smooth daily operations, demonstrating a balanced approach that actively respects and manages teacher workload and wellbeing?',
            ['Tidak terorganisir; sering ada perubahan mendadak.','Jadwal ada namun tidak konsisten.','Jadwal terencana dengan baik dan koordinasi berjalan lancar.','Mengantisipasi tantangan; operasional berjalan sempurna.'],
            ['Disorganized; frequent last-minute changes.','Timelines exist but inconsistent.','Well-planned schedules and coordination.','Anticipates challenges; operations run flawlessly.']],
        12 => ['Apakah [Nama] mengalokasikan dan mengelola sumber daya pembelajaran serta fasilitas sekolah secara strategis untuk mendukung pembelajaran berbasis inkuiri dan kebutuhan beragam komunitas sekolah secara optimal?','Has [Name] strategically allocated and managed instructional resources and school facilities to optimally support inquiry-based learning and the diverse needs of the school community?',
            ['Sumber daya tidak memadai.','Sumber daya dasar tersedia.','Penyediaan yang memadai dan tepat waktu.','Alokasi strategis yang meningkatkan kualitas pembelajaran.'],
            ['Insufficient resources.','Basic resources provided.','Adequate and timely provision.','Strategic allocation enhancing learning.']],
        13 => ['Apakah [Nama] menerapkan kebijakan sekolah dan IB dengan fidelitas, konsistensi, dan keadilan terhadap seluruh staf, secara proaktif menghindari kebingungan atau persepsi keberpihakan?','Has [Name] applied school and IB policies with fidelity, consistency, and fairness across all staff members, proactively avoiding confusion or any perception of bias?',
            ['Kebijakan diterapkan secara tidak konsisten atau tidak adil.','Konsistensi dasar ada namun masih ada pengecualian.','Kebijakan jelas, adil, dan dapat diprediksi.','Kebijakan diterapkan secara transparan dan membangun kepercayaan.'],
            ['Policies applied inconsistently or unfairly.','Basic consistency but with exceptions.','Policies clear, fair, and predictable.','Policies applied transparently and build trust.']],
        14 => ['Apakah [Nama] merespons masalah sistemik maupun operasional yang memengaruhi proses belajar mengajar dengan cepat dan efektif, menunjukkan inisiatif pemecahan masalah yang proaktif dan ketahanan dalam menghadapi tantangan?','Has [Name] responded promptly and effectively to systemic or daily issues affecting teaching and learning, demonstrating proactive problem-solving initiative and resilience in the face of challenges?',
            ['Lambat atau tidak responsif terhadap masalah.','Merespons secara tidak konsisten.','Merespons dengan cepat dan menyelesaikan masalah secara efektif.','Mengantisipasi masalah dan mendukung guru secara proaktif.'],
            ['Slow or unresponsive to issues.','Responds inconsistently.','Responds promptly and resolves issues effectively.','Anticipates issues and supports teachers proactively.']],
        15 => ['Apakah [Nama] menunjukkan empati yang mendalam dan secara aktif mendukung guru baik secara profesional maupun emosional, membangun lingkungan kerja yang positif, aman, dan seimbang?','Has [Name] demonstrated deep empathy and actively supported teachers both professionally and emotionally, cultivating a positive, safe, and balanced work environment?',
            ['Kurang kepedulian terhadap kesejahteraan.','Empati tidak konsisten.','Suportif dan mudah didekati.','Menciptakan lingkungan yang sangat mendukung.'],
            ['Little concern for wellbeing.','Inconsistent empathy.','Supportive and approachable.','Creates a deeply supportive environment.']],
        16 => ['Apakah [Nama] secara sistematis mempromosikan struktur yang memberdayakan pembelajaran yang berpusat pada siswa dan berbasis inkuiri, mendorong siswa untuk mengambil tindakan bermakna dan peran kepemimpinan dalam komunitas?','Has [Name] systematically promoted structures that empower student-centred, inquiry-based learning, encouraging students to take meaningful action and leadership roles within the community?',
            ['Tidak ada fokus pada inkuiri atau agency siswa.','Integrasi minimal.','Dukungan yang konsisten terhadap inkuiri.','Memimpin inisiatif yang mendorong agency siswa yang kuat.'],
            ['No focus on inquiry or agency.','Minimal integration.','Consistent support for inquiry.','Leads initiatives promoting strong student agency.']],
        17 => ['Apakah [Nama] secara konsisten mengakui upaya guru dan merayakan pencapaian profesional dengan cara yang bermakna, responsif terhadap budaya, dan memotivasi?','Has [Name] consistently acknowledged teacher effort and celebrated professional achievements in a meaningful, culturally responsive, and motivating manner?',
            ['Jarang mengakui upaya guru.','Pengakuan sesekali atau superfisial.','Memberikan pengakuan dan apresiasi yang bermakna.','Membangun budaya di mana apresiasi tertanam dan membangun semangat.'],
            ['Rarely acknowledges teacher effort.','Acknowledgement occasional or superficial.','Provides meaningful recognition and appreciation.','Builds a culture where appreciation is embedded and uplifting.']],
        18 => ['Apakah [Nama] membangun kepercayaan melalui perlakuan yang adil, tindakan yang transparan, dan kepatuhan yang konsisten terhadap etika profesional?','Has [Name] built trust through equitable treatment, transparent actions, and consistent adherence to professional ethics?',
            ['Ada kekhawatiran tentang keadilan atau keberpihakan.','Menunjukkan keadilan namun kadang tidak konsisten.','Secara konsisten dapat dipercaya dan adil.','Sangat dihormati atas integritas dan profesionalismenya.'],
            ['Concerns about fairness or bias.','Shows fairness but occasionally inconsistent.','Consistently trustworthy and equitable.','Highly respected for integrity and professionalism.']],
        19 => ['Apakah [Nama] mendukung struktur dan sistem yang menangani kebutuhan belajar yang beragam, memastikan siswa mendapatkan intervensi yang tepat dan tepat waktu?','Has [Name] supported structures and systems that address diverse learning needs, ensuring students receive appropriate and timely interventions?',
            ['Memberikan sedikit dukungan untuk kebutuhan belajar yang beragam.','Dukungan ada namun terbatas atau tidak konsisten.','Mendukung intervensi yang tepat bagi siswa yang kesulitan maupun yang berbakat.','Aktif memperkuat sistem untuk memastikan setiap siswa mendapat dukungan.'],
            ['Offers little support for diverse learning needs.','Support present but limited or inconsistent.','Supports appropriate interventions for struggling or advanced learners.','Actively strengthens systems ensuring every student is supported.']],
    ];

    // Teacher questions (order_num 1-16)
    $teacherQs = [
        1 => ['Apakah [Nama] telah menunjukkan keselarasan dengan misi IB dan profil pelajar dalam filosofi dan praktik mengajar, serta terlibat secara mendalam dengan buku, ide, isu terkini, dan perilaku siswa?','Has [Name] demonstrated alignment with the IB mission and learner profile in teaching philosophy and practice, engaging with books, ideas, current issues, and student behaviour with depth and insight?',
            ['Tidak ada rujukan terhadap misi IB atau profil pelajar; keterlibatan terbatas.','Pemahaman dasar filosofi IB dengan penerapan di kelas yang terbatas.','Secara konsisten mengintegrasikan nilai-nilai dan literatur IB ke dalam praktik mengajar.','Memodelkan filosofi IB dan menginspirasi keterlibatan intelektual mendalam di seluruh sekolah.'],
            ['No reference to IB mission or learner profile; limited engagement.','Basic understanding of IB philosophy with limited classroom application.','Consistently integrates IB values and literature into teaching practice.','Models IB philosophy and inspires deep intellectual engagement across the school.']],
        2 => ['Apakah [Nama] mendukung pembelajaran bahasa, termasuk bahasa ibu dan bahasa tambahan, sambil menyambut perspektif yang beragam untuk memperkaya pembelajaran di kelas dan pemahaman komunitas?','Has [Name] supported language learning, including both mother tongue and additional languages, while welcoming diverse perspectives to enrich classroom learning and community understanding?',
            ['Tidak ada dukungan untuk keberagaman bahasa.','Mengakui keberagaman bahasa dengan strategi yang minimal.','Secara teratur mendukung dan mengintegrasikan pembelajaran multibahasa.','Menciptakan lingkungan multibahasa yang kaya dan inklusif yang meningkatkan suara dan identitas siswa.'],
            ['No support for language diversity.','Acknowledges language diversity with minimal strategies.','Regularly supports and integrates multilingual learning.','Creates a rich, inclusive multilingual environment that enhances student voice and identity.']],
        3 => ['Apakah [Nama] terlibat dengan komunitas yang lebih luas untuk mempromosikan tindakan bertanggung jawab dan kewarganegaraan global, memberdayakan siswa untuk berinisiatif dan memimpin dengan tujuan dan pelayanan?','Has [Name] engaged with the wider community to promote responsible action and global citizenship, empowering students to take initiative and lead with purpose and service?',
            ['Tidak ada keterhubungan dengan komunitas atau kepemimpinan siswa.','Keterlibatan siswa dan komunitas lokal yang sesekali.','Aktif mendorong agency siswa dan kemitraan komunitas melalui kegiatan sekolah.','Memimpin inisiatif berbasis siswa yang berdampak dengan relevansi global.'],
            ['No connection to community or student leadership.','Occasional student involvement and local engagement.','Actively promotes student agency and community partnerships through school events.','Leads impactful, student-led initiatives with global relevance.']],
        4 => ['Apakah [Nama] membangun budaya kolaboratif melalui interaksi profesional yang teratur dan bermakna, mendorong kerja tim dan pembelajaran bersama di antara siswa dan kolega?','Has [Name] fostered a collaborative culture through regular and meaningful professional interactions, promoting teamwork and shared learning among students and colleagues?',
            ['Bekerja secara terisolasi.','Sesekali berpartisipasi dalam perencanaan tim.','Secara teratur berkolaborasi dengan rekan dan mendorong kerja tim siswa.','Memimpin praktik kolaboratif dan membangun komunitas pembelajaran profesional yang kuat.'],
            ['Works in isolation.','Occasionally participates in team planning.','Regularly collaborates with peers and encourages student teamwork.','Leads collaborative practices and builds a strong professional learning community.']],
        5 => ['Apakah [Nama] berpartisipasi dalam dan menerapkan pengembangan profesional yang selaras dengan ekspektasi IB, sambil bertindak secara konsisten berdasarkan kejujuran, etika, dan keadilan?','Has [Name] participated in and applied professional development aligned with IB expectations, while acting consistently with honesty, ethics, and fairness?',
            ['Menghindari PD dan kurang integritas profesional.','Menghadiri PD namun menunjukkan refleksi etis atau penerapan yang terbatas.','Menerapkan pembelajaran PD dan menunjukkan perilaku etis.','Memfasilitasi PD dan menjadi model integritas dan profesionalisme.'],
            ['Avoids PD and lacks professional integrity.','Attends PD but shows limited ethical reflection or application.','Applies PD learning and demonstrates ethical conduct.','Facilitates PD and is a model of integrity and professionalism.']],
        6 => ['Apakah [Nama] mengimplementasikan kebijakan sekolah dengan fidelitas dan berkontribusi pada penyempurnaannya, merespons secara fleksibel terhadap perubahan dan memodelkan ketahanan?','Has [Name] implemented school policies with fidelity and contributed to their ongoing refinement, responding flexibly to change and modeling resilience?',
            ['Mengabaikan kebijakan dan menolak perubahan.','Menyadari kebijakan; mengikuti prosedur secara tidak konsisten.','Menerapkan kebijakan secara bertanggung jawab dan beradaptasi dengan tantangan.','Memengaruhi pengembangan kebijakan dan mendorong budaya sekolah yang tangguh.'],
            ['Disregards policies and resists change.','Aware of policies; inconsistently follows procedures.','Applies policies responsibly and adapts to challenges.','Influences policy development and fosters a resilient school culture.']],
        7 => ['Apakah [Nama] mengembangkan rencana kurikulum yang selaras dengan persyaratan IB dan mempromosikan pengajaran yang inovatif, mendorong kreativitas, rasa ingin tahu, dan pemikiran segar?','Has [Name] developed curriculum plans that align with IB requirements and promote innovative teaching, encouraging creativity, curiosity, and fresh thinking?',
            ['Tidak ada bukti perencanaan kurikulum atau inovasi.','Rencana dasar dengan kreativitas yang terbatas.','Merancang unit kurikulum yang terstruktur dengan baik dan inovatif.','Memimpin pengembangan kurikulum yang kreatif dan menginspirasi inovasi pedagogis.'],
            ['No evidence of curriculum planning or innovation.','Basic plans with limited creativity.','Designs well-structured, innovative curriculum units.','Leads creative curriculum development and inspires pedagogical innovation.']],
        8 => ['Apakah [Nama] mengintegrasikan koneksi lintas disiplin ilmu, termasuk TOK dan konteks global, menganalisis informasi secara bijaksana untuk membimbing siswa berpikir secara mandiri?','Has [Name] integrated interdisciplinary connections, including TOK and global contexts, analysing information thoughtfully to guide students in independent thinking?',
            ['Tidak ada integrasi lintas disiplin atau pemikiran kritis.','Mencoba membuat koneksi namun dengan kedalaman yang terbatas.','Secara teratur mengintegrasikan pemikiran global dan lintas disiplin.','Sangat menanamkan pembelajaran lintas disiplin dan mendorong inkuiri siswa yang kaya.'],
            ['No interdisciplinary or critical thinking integration.','Attempts connections with limited depth.','Regularly integrates global and interdisciplinary thinking.','Deeply embeds interdisciplinary learning and fosters rich student inquiry.']],
        9 => ['Apakah [Nama] merefleksikan dan merevisi kurikulum berdasarkan umpan balik siswa dan persyaratan program, dengan komunikasi yang jelas dan mendengarkan secara aktif untuk membangun dialog yang bermakna?','Has [Name] reflected on and revised curriculum based on student feedback and programme requirements, with clear communication and active listening to build meaningful dialogue?',
            ['Tidak ada refleksi kurikulum atau strategi komunikasi.','Refleksi jarang dengan masukan siswa yang terbatas.','Menggunakan umpan balik untuk merevisi pengajaran dan berkomunikasi secara efektif.','Memimpin praktik reflektif dan mendorong dialog terbuka dengan siswa dan staf.'],
            ['No curriculum reflection or communication strategies.','Infrequent reflection with limited student input.','Uses feedback to revise teaching and communicates effectively.','Leads reflective practice and fosters open dialogue with students and staff.']],
        10 => ['Apakah [Nama] menggunakan strategi berbasis inkuiri untuk menumbuhkan rasa ingin tahu, keterlibatan, dan kepemilikan belajar siswa, membimbing siswa untuk berpikir secara mandiri dan kritis?','Has [Name] used inquiry-based strategies to foster student curiosity, engagement, and ownership, guiding students to think independently and critically?',
            ['Pengajaran bersifat direktif; tidak ada inkuiri.','Beberapa elemen inkuiri namun kepemilikan siswa minimal.','Menerapkan strategi inkuiri yang mendukung pemikiran kritis.','Inkuiri menjadi inti pembelajaran; siswa memimpin investigasi mereka sendiri.'],
            ['Teaching is directive; no inquiry.','Some inquiry elements but minimal student ownership.','Applies inquiry strategies that support critical thinking.','Inquiry is central to learning; students lead their own investigations.']],
        11 => ['Apakah [Nama] mendifferensiasi pembelajaran untuk memenuhi kebutuhan beragam seluruh peserta didik secara efektif, sambil mendukung kesejahteraan siswa dan memodelkan gaya hidup yang sehat dan penuh tujuan?','Has [Name] differentiated instruction to effectively address the diverse needs of all learners, while supporting student well-being and modelling a healthy, purposeful lifestyle?',
            ['Pengajaran seragam tanpa perhatian terhadap kesejahteraan.','Beberapa strategi diferensiasi dan kesejahteraan.','Secara teratur menyesuaikan pengajaran dan memodelkan keseimbangan.','Sangat responsif terhadap kebutuhan siswa dan pendukung pendidikan holistik.'],
            ['One-size-fits-all instruction with no attention to well-being.','Some differentiation and well-being strategies.','Regularly adapts instruction and models balance.','Highly responsive to student needs and a champion of holistic education.']],
        12 => ['Apakah [Nama] memberikan dukungan yang konsisten dan terintegrasi untuk pengembangan bahasa di berbagai mata pelajaran, berkomunikasi dengan jelas sambil merangkul perspektif yang beragam?','Has [Name] provided consistent and embedded support for language development across subjects, communicating clearly while embracing diverse perspectives?',
            ['Dukungan bahasa dan komunikasi lemah.','Dukungan sesekali dan kejelasan dasar.','Mengintegrasikan strategi bahasa dan berkomunikasi secara efektif.','Unggul dalam praktik komunikasi yang kaya bahasa dan inklusif.'],
            ['Language support and communication are weak.','Occasional support and basic clarity.','Integrates language strategies and communicates effectively.','Excels in language-rich, inclusive communication practices.']],
        13 => ['Apakah [Nama] mempromosikan dan menilai atribut profil pelajar IB sebagai bagian dari pengalaman belajar sehari-hari, memodelkan keterbukaan pikiran, integritas, dan keseimbangan sebagai bagian dari pertumbuhan holistik?','Has [Name] promoted and assessed IB learner profile attributes as part of daily learning experiences, modelling open-mindedness, integrity, and balance as part of holistic growth?',
            ['Profil pelajar tidak dirujuk atau diteladankan.','Menyebut atribut sesekali dengan penilaian yang terbatas.','Mempromosikan dan menilai atribut dengan niat yang jelas.','Profil pelajar menjadi inti pembelajaran dan budaya sekolah.'],
            ['Learner profile not referenced or modelled.','Mentions attributes occasionally with limited assessment.','Promotes and assesses attributes with intention.','Learner profile is central to learning and school culture.']],
        14 => ['Apakah [Nama] menerapkan kriteria dan praktik penilaian IB untuk memastikan evaluasi yang autentik dan selaras, sambil menunjukkan pemikiran kritis dan pengambilan keputusan yang etis?','Has [Name] applied IB assessment criteria and practices to ensure authentic and aligned evaluation, while demonstrating critical thinking and ethical decision-making?',
            ['Menggunakan penilaian generik atau tidak selaras.','Beberapa penggunaan kriteria IB tanpa konsistensi.','Menggunakan kriteria secara tepat dan mengevaluasi dengan adil.','Praktik penilaian teladan dan mendukung pemahaman seluruh sekolah.'],
            ['Uses generic or misaligned assessments.','Some use of IB criteria without consistency.','Uses criteria appropriately and evaluates fairly.','Assessment practices are exemplary and support school-wide understanding.']],
        15 => ['Apakah [Nama] memfasilitasi refleksi siswa sebagai bagian yang bermakna dan rutin dari proses pembelajaran, mendorong kepemimpinan dan rasa tujuan pada diri peserta didik?','Has [Name] facilitated student reflection as a meaningful and routine part of the learning process, encouraging leadership and a sense of purpose in learners?',
            ['Tidak ada kesempatan untuk refleksi atau suara siswa.','Refleksi sesekali atau dipimpin guru.','Mendukung refleksi yang teratur dan terstruktur.','Membangun lingkungan belajar yang reflektif dan dipimpin oleh siswa.'],
            ['No opportunities for reflection or student voice.','Reflection is occasional or teacher-led.','Supports regular, structured reflection.','Cultivates a reflective, student-led learning environment.']],
        16 => ['Apakah [Nama] menggunakan data penilaian untuk menginformasikan pengajaran dan meningkatkan hasil belajar siswa, menunjukkan literasi tingkat tinggi dan inovasi dalam menganalisis bukti?','Has [Name] used assessment data to inform instruction and improve student learning outcomes, demonstrating high-level literacy and innovation in analysing evidence?',
            ['Data tidak dikumpulkan atau digunakan.','Data ditinjau namun jarang menginformasikan pengajaran.','Menggunakan data untuk merencanakan dan mendifferensiasi secara efektif.','Menganalisis dan berbagi wawasan data untuk memimpin peningkatan pengajaran.'],
            ['Data is not collected or used.','Data is reviewed but rarely informs teaching.','Uses data to plan and differentiate effectively.','Analyses and shares data insights to lead instructional improvements.']],
    ];

    $qInsert = $pdo->prepare("INSERT INTO questions (standard_id, question_id_text, question_en_text) VALUES (?,?,?)");
    $gInsert = $pdo->prepare("INSERT INTO grade_descriptors (question_id, grade, label_id, label_en, description_id, description_en) VALUES (?,?,?,?,?,?)");

    $labelIds = ['1 – Tidak Terlihat','2 – Berkembang','3 – Cakap','4 – Teladan'];
    $labelEns = ['1 – Not Evident','2 – Emerging','3 – Proficient','4 – Exemplary'];

    // Insert leader questions
    foreach ($leaderQs as $orderNum => $data) {
        $stdId = $stdMap[1][$orderNum] ?? null;
        if (!$stdId) continue;
        $qInsert->execute([$stdId, $data[0], $data[1]]);
        $qId = $pdo->lastInsertId();
        foreach ($data[2] as $i => $descId) {
            $gInsert->execute([$qId, $i+1, $labelIds[$i], $labelEns[$i], $descId, $data[3][$i]]);
        }
    }

    // Insert teacher questions
    foreach ($teacherQs as $orderNum => $data) {
        $stdId = $stdMap[2][$orderNum] ?? null;
        if (!$stdId) continue;
        $qInsert->execute([$stdId, $data[0], $data[1]]);
        $qId = $pdo->lastInsertId();
        foreach ($data[2] as $i => $descId) {
            $gInsert->execute([$qId, $i+1, $labelIds[$i], $labelEns[$i], $descId, $data[3][$i]]);
        }
    }
}

// ── SEED: PACKAGES ────────────────────────────────────────────
function seedPackages(PDO $pdo): void {
    // Get question IDs by standard order_num and eval_type
    $qByStdOrder = [];
    $rows = $pdo->query("
        SELECT q.id, s.order_num, d.eval_type_id
        FROM questions q JOIN standards s ON q.standard_id = s.id
        JOIN domains d ON s.domain_id = d.id
    ")->fetchAll();
    foreach ($rows as $r) {
        $qByStdOrder[$r['eval_type_id']][$r['order_num']] = $r['id'];
    }

    // Package definitions: [code, name, eval_type_id, respondent_type, is_self, [standard_order_nums], weight]
    $packages = [
        ['L1','Evaluasi Pimpinan – Oleh Atasan (YPKBI/YPKTB)',1,'atasan',0,[1,2,3,7,9,13,14],1.50],
        ['L2','Evaluasi Pimpinan – Oleh Rekan Sejawat',       1,'peer',  0,[1,2,3,4,5,7,8,9,10,11,12,13,14,17,18],1.20],
        ['L3','Evaluasi Pimpinan – Oleh Guru',                1,'guru',  0,[3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19],1.00],
        ['L4','Evaluasi Pimpinan – Oleh Orang Tua',           1,'ortu',  0,[4,7,8,9,14,16,19],0.80],
        ['L5','Evaluasi Pimpinan – Oleh Siswa (OSIS)',        1,'siswa', 0,[4,5,7,8,13,14,16,18,19],0.70],
        ['L6','Refleksi Mandiri – Pimpinan',                  1,'self',  1,[2,11],1.00],
        ['T1','Evaluasi Guru – Oleh Atasan (YPKBI/YPKTB)',    2,'atasan',0,[1,2,5,6,7,14],1.50],
        ['T2','Evaluasi Guru – Oleh Pimpinan Sekolah',        2,'leader',0,[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16],1.30],
        ['T3','Evaluasi Guru – Oleh Rekan Sejawat',           2,'peer',  0,[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16],1.00],
        ['T4','Evaluasi Guru – Oleh Orang Tua',               2,'ortu',  0,[2,3,4,13,14,15,16],0.80],
        ['T5','Evaluasi Guru – Oleh Siswa',                   2,'siswa', 0,[2,3,4,8,9,10,11,12,13,14,15,16],0.70],
        ['T6','Refleksi Mandiri – Guru',                      2,'self',  1,[1,5],1.00],
    ];

    $pInsert = $pdo->prepare("INSERT INTO packages (code,name,eval_type_id,respondent_type,is_self_reflection) VALUES (?,?,?,?,?)");
    $pqInsert = $pdo->prepare("INSERT INTO package_questions (package_id,question_id,order_num) VALUES (?,?,?)");
    $pwInsert = $pdo->prepare("INSERT INTO package_weights (package_id,weight,notes) VALUES (?,?,?)");

    foreach ($packages as $pkg) {
        [$code,$name,$etId,$respType,$isSelf,$stdOrders,$weight] = $pkg;
        $pInsert->execute([$code,$name,$etId,$respType,$isSelf]);
        $pkgId = $pdo->lastInsertId();
        $pwInsert->execute([$pkgId, $weight, "Default weight for $respType"]);
        foreach ($stdOrders as $i => $orderNum) {
            $qId = $qByStdOrder[$etId][$orderNum] ?? null;
            if ($qId) $pqInsert->execute([$pkgId, $qId, $i+1]);
        }
    }
}

// ── SEED: DUMMY USERS ─────────────────────────────────────────
function seedUsers(PDO $pdo): array {
    $pass = password_hash('KTB2025!', PASSWORD_BCRYPT);

    $users = [
        // Admin
        ['Administrator KTB', 'admin@ktb.sch.id', password_hash('Admin@KTB2025', PASSWORD_BCRYPT), 'admin'],
        // Foundation (3)
        ['Dr. Ahmad Fauzi', 'ahmad.fauzi@ypkbi.or.id', $pass, 'foundation'],
        ['Dr. Siti Rahayu', 'siti.rahayu@ypkbi.or.id', $pass, 'foundation'],
        ['Ir. Budi Santoso', 'budi.santoso@ypktb.or.id', $pass, 'foundation'],
        // Leaders (3)
        ['Drs. Hendra Kusuma, M.Pd.', 'hendra.kusuma@ktb.sch.id', $pass, 'leader'], // Kepala Sekolah
        ['Dewi Anggraini, S.Pd., M.M.', 'dewi.anggraini@ktb.sch.id', $pass, 'leader'], // Wakasek Akademik
        ['Reza Firmansyah, S.Pd.', 'reza.firmansyah@ktb.sch.id', $pass, 'leader'], // Wakasek Kesiswaan
        // Teachers (10)
        ['Agus Pramono, S.Pd.',     'agus.pramono@ktb.sch.id', $pass, 'teacher'],
        ['Bella Maharani, S.Pd.',   'bella.maharani@ktb.sch.id', $pass, 'teacher'],
        ['Cahyo Wibowo, S.Pd.',     'cahyo.wibowo@ktb.sch.id', $pass, 'teacher'],
        ['Dian Pertiwi, S.Pd.',     'dian.pertiwi@ktb.sch.id', $pass, 'teacher'],
        ['Eko Prasetyo, S.Pd.',     'eko.prasetyo@ktb.sch.id', $pass, 'teacher'],
        ['Fitria Handayani, S.Pd.', 'fitria.handayani@ktb.sch.id', $pass, 'teacher'],
        ['Gunawan Saputra, S.Pd.',  'gunawan.saputra@ktb.sch.id', $pass, 'teacher'],
        ['Hani Susanti, S.Pd.',     'hani.susanti@ktb.sch.id', $pass, 'teacher'],
        ['Irfan Hakim, S.Pd.',      'irfan.hakim@ktb.sch.id', $pass, 'teacher'],
        ['Juwita Lestari, S.Pd.',   'juwita.lestari@ktb.sch.id', $pass, 'teacher'],
    ];

    // Add 100 students
    for ($i = 1; $i <= 100; $i++) {
        $names = ['Aditya','Bagas','Citra','Dafa','Elsa','Farhan','Gita','Haris','Indra','Jasmine',
                  'Kevin','Luna','Mario','Nadia','Omar','Putri','Qori','Rizky','Salsa','Tara',
                  'Umar','Vina','Wahyu','Xena','Yogi','Zahra','Andre','Bunga','Candra','Dina'];
        $surnames = ['Putra','Putri','Sari','Pratama','Dewi','Rahman','Hidayat','Susanto','Kurnia','Wati'];
        $firstName = $names[($i-1) % count($names)];
        $lastName  = $surnames[($i-1) % count($surnames)];
        $users[] = ["$firstName $lastName", "student$i@ktb.sch.id", $pass, 'student'];
    }

    // Add 30 parents
    $teacherNames = ['Agus Pramono','Bella Maharani','Cahyo Wibowo','Dian Pertiwi','Eko Prasetyo',
                     'Fitria Handayani','Gunawan Saputra','Hani Susanti','Irfan Hakim','Juwita Lestari'];
    for ($i = 1; $i <= 30; $i++) {
        $users[] = ["Orang Tua Siswa $i", "parent$i@ktb.sch.id", $pass, 'parent'];
    }

    $ins = $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)");
    $ids = [];
    foreach ($users as $u) {
        $ins->execute($u);
        $ids[$u[1]] = $pdo->lastInsertId();
    }
    return $ids;
}

// ── SEED: GROUPS ──────────────────────────────────────────────
function seedGroups(PDO $pdo, array $userIds): void {
    $groups = [
        [1,'Pengurus Yayasan','foundation','YPKBI dan YPKTB'],
        [2,'Pimpinan Sekolah','leader','Kepala Sekolah dan Wakil Kepala Sekolah'],
        [3,'Guru IB DP','teacher','Seluruh staf pengajar IB Diploma Programme'],
        [4,'Kelas XI IPA','student','Siswa kelas XI IPA'],
        [5,'Kelas XI IPS','student','Siswa kelas XI IPS'],
        [6,'Kelas XII IPA','student','Siswa kelas XII IPA'],
        [7,'Kelas XII IPS','student','Siswa kelas XII IPS'],
        [8,'Komite Orang Tua','parent','Perwakilan Komite Sekolah'],
        [9,'OSIS KTB','student','Pengurus OSIS'],
    ];
    $gIns = $pdo->prepare("INSERT INTO `groups` (id,name,type,description) VALUES (?,?,?,?)");
    foreach ($groups as $g) $gIns->execute($g);

    // Assign users to groups
    $assignments = [
        'ahmad.fauzi@ypkbi.or.id' => [1], 'siti.rahayu@ypkbi.or.id' => [1], 'budi.santoso@ypktb.or.id' => [1],
        'hendra.kusuma@ktb.sch.id' => [2], 'dewi.anggraini@ktb.sch.id' => [2], 'reza.firmansyah@ktb.sch.id' => [2],
        'agus.pramono@ktb.sch.id' => [3], 'bella.maharani@ktb.sch.id' => [3], 'cahyo.wibowo@ktb.sch.id' => [3],
        'dian.pertiwi@ktb.sch.id' => [3], 'eko.prasetyo@ktb.sch.id' => [3], 'fitria.handayani@ktb.sch.id' => [3],
        'gunawan.saputra@ktb.sch.id' => [3], 'hani.susanti@ktb.sch.id' => [3], 'irfan.hakim@ktb.sch.id' => [3],
        'juwita.lestari@ktb.sch.id' => [3],
    ];
    // Assign students to class groups
    for ($i = 1; $i <= 100; $i++) {
        $grp = ($i <= 25) ? 4 : (($i <= 50) ? 5 : (($i <= 75) ? 6 : 7));
        $assignments["student$i@ktb.sch.id"] = [$grp];
        if ($i <= 15) $assignments["student$i@ktb.sch.id"][] = 9; // OSIS members
    }
    for ($i = 1; $i <= 30; $i++) {
        $assignments["parent$i@ktb.sch.id"] = [8];
    }

    $ugIns = $pdo->prepare("INSERT IGNORE INTO user_groups (user_id, group_id) VALUES (?,?)");
    foreach ($assignments as $email => $grpIds) {
        $uid = $userIds[$email] ?? null;
        if (!$uid) continue;
        foreach ($grpIds as $gid) $ugIns->execute([$uid, $gid]);
    }
}

// ── SEED: PERIOD + ASSIGNMENTS + RESPONSES ────────────────────
function seedEvalData(PDO $pdo, array $userIds): void {
    // Active evaluation period
    $pdo->exec("INSERT INTO eval_periods (id,name,year,start_date,end_date,is_active) VALUES (1,'Evaluasi Tahunan 2024–2025',2025,'2025-04-15','2025-05-31',1)");

    // Get package IDs
    $pkgs = [];
    foreach ($pdo->query("SELECT id, code FROM packages") as $row) {
        $pkgs[$row['code']] = $row['id'];
    }

    // Get question IDs per package
    $pkgQs = [];
    foreach ($pdo->query("SELECT package_id, question_id FROM package_questions ORDER BY order_num") as $row) {
        $pkgQs[$row['package_id']][] = $row['question_id'];
    }

    $leaders  = ['hendra.kusuma@ktb.sch.id','dewi.anggraini@ktb.sch.id','reza.firmansyah@ktb.sch.id'];
    $teachers = ['agus.pramono@ktb.sch.id','bella.maharani@ktb.sch.id','cahyo.wibowo@ktb.sch.id',
                 'dian.pertiwi@ktb.sch.id','eko.prasetyo@ktb.sch.id','fitria.handayani@ktb.sch.id',
                 'gunawan.saputra@ktb.sch.id','hani.susanti@ktb.sch.id','irfan.hakim@ktb.sch.id',
                 'juwita.lestari@ktb.sch.id'];
    $foundation = ['ahmad.fauzi@ypkbi.or.id','siti.rahayu@ypkbi.or.id','budi.santoso@ypktb.or.id'];

    $aIns = $pdo->prepare("INSERT INTO assignments (period_id,evaluatee_id,evaluator_id,package_id,status,due_date,completed_at) VALUES (1,?,?,?,?,?,?)");
    $rIns = $pdo->prepare("INSERT IGNORE INTO responses (assignment_id,question_id,grade) VALUES (?,?,?)");

    // Weighted random grade generator (realistic scores 2-4, avg ~3)
    $randomGrade = function(float $bias = 3.0): int {
        $r = mt_rand(0, 100) / 100;
        if ($bias >= 3.5) { // High performer
            if ($r < 0.10) return 2; if ($r < 0.40) return 3; return 4;
        } elseif ($bias >= 3.0) { // Good performer
            if ($r < 0.05) return 1; if ($r < 0.20) return 2; if ($r < 0.65) return 3; return 4;
        } elseif ($bias >= 2.5) { // Average
            if ($r < 0.10) return 1; if ($r < 0.40) return 2; if ($r < 0.80) return 3; return 4;
        } else { // Developing
            if ($r < 0.15) return 1; if ($r < 0.55) return 2; if ($r < 0.85) return 3; return 4;
        }
    };

    // Leader biases (how good each leader performs)
    $leaderBias = [
        'hendra.kusuma@ktb.sch.id' => 3.6, // Principal, strong
        'dewi.anggraini@ktb.sch.id' => 3.2, // Vice, good
        'reza.firmansyah@ktb.sch.id' => 2.8, // Vice, average-good
    ];
    $teacherBias = [];
    foreach ($teachers as $i => $t) {
        $teacherBias[$t] = 2.5 + ($i * 0.15); // range 2.5-3.85
    }

    $dueDate = '2025-05-31';
    $completedAt = '2025-05-20 10:00:00';

    // ── Leader evaluations ────────────────────────────────────
    foreach ($leaders as $leaderEmail) {
        $lId = $userIds[$leaderEmail];
        $bias = $leaderBias[$leaderEmail];

        // Foundation evaluates each leader (L1)
        foreach ($foundation as $fEmail) {
            $fId = $userIds[$fEmail];
            $pkgId = $pkgs['L1'];
            $aIns->execute([$lId, $fId, $pkgId, 'completed', $dueDate, $completedAt]);
            $aid = $pdo->lastInsertId();
            foreach (($pkgQs[$pkgId] ?? []) as $qid) {
                $rIns->execute([$aid, $qid, $randomGrade($bias)]);
            }
        }

        // Leaders peer-review each other (L2)
        foreach ($leaders as $peerEmail) {
            if ($peerEmail === $leaderEmail) continue;
            $pId = $userIds[$peerEmail];
            $pkgId = $pkgs['L2'];
            $aIns->execute([$lId, $pId, $pkgId, 'completed', $dueDate, $completedAt]);
            $aid = $pdo->lastInsertId();
            foreach (($pkgQs[$pkgId] ?? []) as $qid) {
                $rIns->execute([$aid, $qid, $randomGrade($bias)]);
            }
        }

        // Teachers evaluate each leader (L3) - first 5 teachers
        foreach (array_slice($teachers, 0, 5) as $tEmail) {
            $tId = $userIds[$tEmail];
            $pkgId = $pkgs['L3'];
            $aIns->execute([$lId, $tId, $pkgId, 'completed', $dueDate, $completedAt]);
            $aid = $pdo->lastInsertId();
            foreach (($pkgQs[$pkgId] ?? []) as $qid) {
                $rIns->execute([$aid, $qid, $randomGrade($bias)]);
            }
        }

        // Parents evaluate each leader (L4) - 3 parents
        for ($p = 1; $p <= 3; $p++) {
            $pId = $userIds["parent$p@ktb.sch.id"];
            $pkgId = $pkgs['L4'];
            $aIns->execute([$lId, $pId, $pkgId, 'completed', $dueDate, $completedAt]);
            $aid = $pdo->lastInsertId();
            foreach (($pkgQs[$pkgId] ?? []) as $qid) {
                $rIns->execute([$aid, $qid, $randomGrade($bias)]);
            }
        }

        // Students (OSIS) evaluate each leader (L5) - 5 OSIS students
        for ($s = 1; $s <= 5; $s++) {
            $sId = $userIds["student$s@ktb.sch.id"];
            $pkgId = $pkgs['L5'];
            $aIns->execute([$lId, $sId, $pkgId, 'completed', $dueDate, $completedAt]);
            $aid = $pdo->lastInsertId();
            foreach (($pkgQs[$pkgId] ?? []) as $qid) {
                $rIns->execute([$aid, $qid, $randomGrade($bias)]);
            }
        }

        // Self-reflection (L6)
        $pkgId = $pkgs['L6'];
        $aIns->execute([$lId, $lId, $pkgId, 'completed', $dueDate, $completedAt]);
        $aid = $pdo->lastInsertId();
        foreach (($pkgQs[$pkgId] ?? []) as $qid) {
            $rIns->execute([$aid, $qid, $randomGrade($bias + 0.2)]);
        }
    }

    // ── Teacher evaluations ───────────────────────────────────
    foreach ($teachers as $idx => $teacherEmail) {
        $tId = $userIds[$teacherEmail];
        $bias = $teacherBias[$teacherEmail];

        // Foundation evaluates (T1) - first foundation member
        $pkgId = $pkgs['T1'];
        $aIns->execute([$tId, $userIds[$foundation[0]], $pkgId, 'completed', $dueDate, $completedAt]);
        $aid = $pdo->lastInsertId();
        foreach (($pkgQs[$pkgId] ?? []) as $qid) $rIns->execute([$aid, $qid, $randomGrade($bias)]);

        // Leader evaluates (T2) - Principal
        $pkgId = $pkgs['T2'];
        $aIns->execute([$tId, $userIds[$leaders[0]], $pkgId, 'completed', $dueDate, $completedAt]);
        $aid = $pdo->lastInsertId();
        foreach (($pkgQs[$pkgId] ?? []) as $qid) $rIns->execute([$aid, $qid, $randomGrade($bias)]);

        // Peer evaluates (T3) - another teacher
        $peerEmail = $teachers[($idx + 1) % count($teachers)];
        $pkgId = $pkgs['T3'];
        $aIns->execute([$tId, $userIds[$peerEmail], $pkgId, 'completed', $dueDate, $completedAt]);
        $aid = $pdo->lastInsertId();
        foreach (($pkgQs[$pkgId] ?? []) as $qid) $rIns->execute([$aid, $qid, $randomGrade($bias)]);

        // Parents evaluate (T4) - 5 parents per teacher
        $pkgId = $pkgs['T4'];
        for ($p = ($idx*3+1); $p <= min($idx*3+3, 30); $p++) {
            $pEmail = "parent$p@ktb.sch.id";
            if (!isset($userIds[$pEmail])) continue;
            $aIns->execute([$tId, $userIds[$pEmail], $pkgId, 'completed', $dueDate, $completedAt]);
            $aid = $pdo->lastInsertId();
            foreach (($pkgQs[$pkgId] ?? []) as $qid) $rIns->execute([$aid, $qid, $randomGrade($bias)]);
        }

        // Students evaluate (T5) - 10 students per teacher
        $pkgId = $pkgs['T5'];
        $startS = ($idx * 10) + 1;
        for ($s = $startS; $s <= min($startS + 9, 100); $s++) {
            $sEmail = "student$s@ktb.sch.id";
            if (!isset($userIds[$sEmail])) continue;
            $aIns->execute([$tId, $userIds[$sEmail], $pkgId, 'completed', $dueDate, $completedAt]);
            $aid = $pdo->lastInsertId();
            foreach (($pkgQs[$pkgId] ?? []) as $qid) $rIns->execute([$aid, $qid, $randomGrade($bias)]);
        }

        // Self-reflection (T6)
        $pkgId = $pkgs['T6'];
        $aIns->execute([$tId, $tId, $pkgId, 'completed', $dueDate, $completedAt]);
        $aid = $pdo->lastInsertId();
        foreach (($pkgQs[$pkgId] ?? []) as $qid) $rIns->execute([$aid, $qid, $randomGrade($bias + 0.1)]);
    }

    // ── Some pending assignments for demo ────────────────────
    // A few pending surveys to show the workflow
    $pendingPkg = $pkgs['L3'];
    foreach (array_slice($teachers, 5) as $tEmail) {
        $tId = $userIds[$tEmail];
        foreach (array_slice($leaders, 0, 2) as $lEmail) {
            $lId = $userIds[$lEmail];
            $aIns->execute([$lId, $tId, $pendingPkg, 'pending', $dueDate, null]);
        }
    }
}

// ── SEED: AI SUGGESTIONS ─────────────────────────────────────
function seedAISuggestions(PDO $pdo, array $userIds): void {
    $suggestions = [
        'hendra.kusuma@ktb.sch.id' => "Berdasarkan hasil evaluasi 360° Periode 2024-2025, Bapak Hendra Kusuma menunjukkan performa yang sangat baik dengan skor rata-rata 3.6 dari 4.0. Kekuatan utama terlihat pada domain IB Philosophy & Vision dan Organizational Leadership.\n\nRekomendasi Pengembangan Profesional (PDP):\n\n1. **Pertahankan dan Perkuat:** Konsistensi dalam mengomunikasikan visi strategis kepada seluruh stakeholder perlu terus dipertahankan. Pertimbangkan untuk mendokumentasikan best practice kepemimpinan sebagai referensi institusional.\n\n2. **Area Pengembangan:** Berdasarkan feedback guru, aspek 'Support for Teacher Wellbeing' memerlukan perhatian lebih. Disarankan untuk mengadakan sesi one-on-one bulanan dengan masing-masing guru.\n\n3. **Target IB:** Perkuat alignment dengan IB Programme Standards khususnya pada aspek 'Collaborative School Culture' dengan menginisiasi Professional Learning Community (PLC) lintas departemen.\n\n4. **Rencana Aksi (SMART):** Implementasikan program mentoring peer-to-peer untuk guru baru paling lambat semester I tahun ajaran 2025-2026.",
        'dewi.anggraini@ktb.sch.id' => "Evaluasi 360° menunjukkan Bu Dewi Anggraini memiliki performa baik di skor 3.2. Terdapat ruang pengembangan yang signifikan pada beberapa aspek operasional.\n\nRekomendasi PDP:\n\n1. **Kekuatan:** Komunikasi dan keterbukaan terhadap guru mendapat penilaian tinggi. Pertahankan pendekatan collaborative decision-making.\n\n2. **Pengembangan Prioritas:** Tingkatkan kemampuan dalam data-driven decision making. Ikuti pelatihan IB Assessment Philosophy untuk memperkuat pemahaman terhadap standar penilaian program.\n\n3. **Rencana Aksi:** Hadiri minimal 2 IB Workshop dalam tahun ajaran 2025-2026, khususnya workshop bertema Leadership in IB Schools.",
        'reza.firmansyah@ktb.sch.id' => "Pak Reza menunjukkan pertumbuhan yang konsisten. Skor 2.8 mencerminkan potensi besar yang perlu dikembangkan secara terstruktur.\n\nRekomendasi PDP:\n\n1. **Fokus Utama:** Perkuat pemahaman tentang IB Philosophy and Mission untuk meningkatkan konsistensi dalam kebijakan kesiswaan sesuai standar IB.\n\n2. **Mentoring:** Disarankan untuk melakukan peer-observation dengan Bapak Hendra sebanyak minimal 4 kali dalam semester depan.\n\n3. **Target Konkret:** Kembangkan program Student Agency yang terukur dengan target peningkatan keterlibatan siswa dalam kegiatan sekolah sebesar 20%.",
    ];

    $ins = $pdo->prepare("INSERT INTO ai_suggestions (evaluatee_id,period_id,raw_suggestion,edited_suggestion) VALUES (?,1,?,?)");
    foreach ($suggestions as $email => $text) {
        $uid = $userIds[$email] ?? null;
        if ($uid) $ins->execute([$uid, $text, $text]);
    }
}

// ── SEED: SETTINGS ────────────────────────────────────────────
function seedSettings(PDO $pdo): void {
    $settings = [
        ['app_name', APP_NAME],
        ['school_name', APP_SCHOOL],
        ['current_period', '1'],
        ['ai_enabled', '1'],
        ['allow_self_registration', '0'],
        ['default_language', 'id'],
        ['setup_complete', '1'],
    ];
    $ins = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?,?)");
    foreach ($settings as $s) $ins->execute($s);
}

// ═════════════════════════════════════════════════════════════
//  HANDLE ACTIONS
// ═════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'install') {
        try {
            createDatabase();
            $pdo = getPDO();
            seedMasterData($pdo);
            seedTraitMappings($pdo);
            seedQuestions($pdo);
            seedPackages($pdo);
            $userIds = seedUsers($pdo);
            seedGroups($pdo, $userIds);
            seedEvalData($pdo, $userIds);
            seedAISuggestions($pdo, $userIds);
            seedSettings($pdo);
            $msg = 'Instalasi berhasil! Semua data telah dimuat. Silakan login.';
            $step = 'done';
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
    if ($_POST['action'] === 'test_db') {
        try {
            getPDO();
            $msg = 'Koneksi database berhasil!';
        } catch (Exception $e) {
            $error = 'Koneksi gagal: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>KTB 360° — Setup & Installer</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<style>
.setup-hero { background: linear-gradient(135deg,#0D2D5E,#1B3F72); min-height:100vh; }
.setup-card { border-radius:16px; border:none; box-shadow:0 20px 60px rgba(0,0,0,.2); }
.step-badge { background:#C9A227; color:#0D2D5E; padding:4px 14px; border-radius:20px; font-weight:700; font-size:.8rem; }
code { background:#f0f2f5; padding:2px 6px; border-radius:4px; }
</style>
</head>
<body class="setup-hero d-flex align-items-center justify-content-center py-5">
<div class="container" style="max-width:700px">
  <div class="card setup-card">
    <div class="card-body p-5">

      <div class="d-flex align-items-center gap-3 mb-4">
        <div class="ktb-logo-sm" style="width:60px;height:60px;font-size:1.1rem">360°</div>
        <div>
          <h4 class="mb-0 fw-bold text-navy">KTB 360° Evaluation Platform</h4>
          <span class="step-badge">Setup & Installer v1.0</span>
        </div>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger"><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($msg): ?>
        <div class="alert alert-success"><strong>✓</strong> <?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <?php if ($step === 'done'): ?>
        <!-- DONE -->
        <div class="text-center py-4">
          <div style="font-size:4rem">🎉</div>
          <h4 class="fw-bold text-navy mt-3">Instalasi Selesai!</h4>
          <p class="text-muted">Semua data telah berhasil dimuat ke database.</p>
          <div class="card border-0 bg-light p-3 text-start mb-4">
            <strong>Akun Admin:</strong><br>
            Email: <code>admin@ktb.sch.id</code><br>
            Password: <code>Admin@KTB2025</code><br><br>
            <strong>Semua akun lain:</strong> Password: <code>KTB2025!</code><br><br>
            <strong>⚠️ PENTING:</strong> Hapus atau rename file <code>setup.php</code> setelah ini!
          </div>
          <a href="/" class="btn btn-navy btn-lg">Buka Aplikasi →</a>
        </div>

      <?php elseif ($step === 'welcome'): ?>
        <!-- STEP 1: Verify Config -->
        <h5 class="fw-bold mb-3">Selamat Datang di KTB 360° Setup</h5>
        <p class="text-muted">Installer ini akan membuat database, tabel, dan memuat semua data awal termasuk data dummy untuk presentasi.</p>

        <div class="card border-warning mb-4">
          <div class="card-body">
            <strong>Konfigurasi Database (dari config/config.php):</strong>
            <ul class="mt-2 mb-0">
              <li>Host: <code><?= DB_HOST ?></code></li>
              <li>Database: <code><?= DB_NAME ?></code></li>
              <li>User: <code><?= DB_USER ?></code></li>
            </ul>
          </div>
        </div>

        <div class="card border-info mb-4">
          <div class="card-body">
            <strong>Data yang akan dimuat:</strong>
            <ul class="mt-2 mb-0 small">
              <li>✓ 10 Traits, 9 Domain, 35 Standard</li>
              <li>✓ 35 Pertanyaan + 140 Grade Descriptors</li>
              <li>✓ 12 Paket Kuesioner (L1-L6, T1-T6)</li>
              <li>✓ 4 Pengguna Yayasan/Admin</li>
              <li>✓ 3 Pimpinan Sekolah (1 Kepala Sekolah + 2 Wakasek)</li>
              <li>✓ 10 Guru dengan seluruh paket evaluasi</li>
              <li>✓ 100 Siswa + 30 Orang Tua</li>
              <li>✓ Periode evaluasi aktif 2024-2025</li>
              <li>✓ Data respons dummy dengan skor realistis</li>
              <li>✓ Saran pengembangan AI (contoh)</li>
            </ul>
          </div>
        </div>

        <div class="d-flex gap-2">
          <form method="post" class="d-inline">
            <input type="hidden" name="action" value="test_db">
            <button class="btn btn-outline-secondary">Test Koneksi DB</button>
          </form>
          <form method="post" class="d-inline" onsubmit="return confirm('Mulai instalasi? Proses ini tidak dapat dibatalkan.')">
            <input type="hidden" name="action" value="install">
            <button class="btn btn-navy btn-lg px-4">🚀 Mulai Instalasi</button>
          </form>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
