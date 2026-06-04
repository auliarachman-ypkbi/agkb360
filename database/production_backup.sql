-- MySQL dump 10.13  Distrib 8.0.46, for Linux (aarch64)
--
-- Host: localhost    Database: ktb_production
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `ai_suggestions`
--

DROP TABLE IF EXISTS `ai_suggestions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_suggestions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `evaluatee_id` int NOT NULL,
  `period_id` int NOT NULL,
  `raw_suggestion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `edited_suggestion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `edited_by` int DEFAULT NULL,
  `generated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `edited_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_suggestion` (`evaluatee_id`,`period_id`),
  KEY `period_id` (`period_id`),
  CONSTRAINT `ai_suggestions_ibfk_1` FOREIGN KEY (`evaluatee_id`) REFERENCES `users` (`id`),
  CONSTRAINT `ai_suggestions_ibfk_2` FOREIGN KEY (`period_id`) REFERENCES `eval_periods` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_suggestions`
--

LOCK TABLES `ai_suggestions` WRITE;
/*!40000 ALTER TABLE `ai_suggestions` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_suggestions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assignments`
--

DROP TABLE IF EXISTS `assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `period_id` int NOT NULL,
  `evaluatee_id` int NOT NULL,
  `evaluator_id` int NOT NULL,
  `package_id` int NOT NULL,
  `status` enum('pending','in_progress','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `period_id` (`period_id`),
  KEY `package_id` (`package_id`),
  KEY `idx_evaluatee` (`evaluatee_id`),
  KEY `idx_evaluator` (`evaluator_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`period_id`) REFERENCES `eval_periods` (`id`),
  CONSTRAINT `assignments_ibfk_2` FOREIGN KEY (`evaluatee_id`) REFERENCES `users` (`id`),
  CONSTRAINT `assignments_ibfk_3` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`id`),
  CONSTRAINT `assignments_ibfk_4` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=876 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignments`
--

LOCK TABLES `assignments` WRITE;
/*!40000 ALTER TABLE `assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `class_students`
--

DROP TABLE IF EXISTS `class_students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `class_students` (
  `id` int NOT NULL AUTO_INCREMENT,
  `class_id` int NOT NULL,
  `student_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cs` (`class_id`,`student_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `class_students_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=159 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `class_students`
--

LOCK TABLES `class_students` WRITE;
/*!40000 ALTER TABLE `class_students` DISABLE KEYS */;
/*!40000 ALTER TABLE `class_students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `class_teachers`
--

DROP TABLE IF EXISTS `class_teachers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `class_teachers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `class_id` int NOT NULL,
  `teacher_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ct` (`class_id`,`teacher_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `class_teachers_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_teachers_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `class_teachers`
--

LOCK TABLES `class_teachers` WRITE;
/*!40000 ALTER TABLE `class_teachers` DISABLE KEYS */;
/*!40000 ALTER TABLE `class_teachers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `year_start` int NOT NULL COMMENT 'Tahun ajaran mulai, cth: 2024',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_year` (`year_start`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `domains`
--

DROP TABLE IF EXISTS `domains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `domains` (
  `id` int NOT NULL AUTO_INCREMENT,
  `eval_type_id` int NOT NULL,
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `order_num` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `eval_type_id` (`eval_type_id`),
  CONSTRAINT `domains_ibfk_1` FOREIGN KEY (`eval_type_id`) REFERENCES `eval_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `domains`
--

LOCK TABLES `domains` WRITE;
/*!40000 ALTER TABLE `domains` DISABLE KEYS */;
INSERT INTO `domains` VALUES (1,1,'A','IB Philosophy & Vision',NULL,1),(2,1,'B','Organizational & Professional Leadership',NULL,2),(3,1,'C','Operational & Programme Management',NULL,3),(4,1,'D','Teacher & Student Support',NULL,4),(5,2,'A','IB Philosophy',NULL,1),(6,2,'B','Organization & Professional Responsibilities',NULL,2),(7,2,'C','Curriculum Implementation',NULL,3),(8,2,'D','Teaching and Learning',NULL,4),(9,2,'E','Assessment',NULL,5);
/*!40000 ALTER TABLE `domains` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eval_periods`
--

DROP TABLE IF EXISTS `eval_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `eval_periods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `year` int NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '0',
  `status` enum('draft','active','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `wizard_step` tinyint DEFAULT '0' COMMENT 'Step wizard terakhir yang selesai (0-6)',
  `locked_at` datetime DEFAULT NULL COMMENT 'Waktu periode dikunci/diaktifkan',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eval_periods`
--

LOCK TABLES `eval_periods` WRITE;
/*!40000 ALTER TABLE `eval_periods` DISABLE KEYS */;
/*!40000 ALTER TABLE `eval_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eval_types`
--

DROP TABLE IF EXISTS `eval_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `eval_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eval_types`
--

LOCK TABLES `eval_types` WRITE;
/*!40000 ALTER TABLE `eval_types` DISABLE KEYS */;
INSERT INTO `eval_types` VALUES (1,'leader','Pimpinan Sekolah'),(2,'teacher','Guru');
/*!40000 ALTER TABLE `eval_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grade_descriptors`
--

DROP TABLE IF EXISTS `grade_descriptors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grade_descriptors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `question_id` int NOT NULL,
  `grade` tinyint NOT NULL,
  `label_id` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label_en` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description_id` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description_en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `grade_descriptors_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=141 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grade_descriptors`
--

LOCK TABLES `grade_descriptors` WRITE;
/*!40000 ALTER TABLE `grade_descriptors` DISABLE KEYS */;
INSERT INTO `grade_descriptors` VALUES (1,1,1,'1 – Tidak Terlihat','1 – Not Evident','Tidak ada rujukan terhadap misi IB; keputusan tidak mencerminkan nilai-nilai IB.','No reference to IB mission; decisions do not reflect IB values.'),(2,1,2,'2 – Berkembang','2 – Emerging','Menunjukkan pemahaman dasar namun penerapannya terbatas dan tidak konsisten.','Shows basic understanding with inconsistent / limited application.'),(3,1,3,'3 – Cakap','3 – Proficient','Secara konsisten menerapkan filosofi IB dalam setiap keputusan.','Consistently applies IB philosophy in decisions.'),(4,1,4,'4 – Teladan','4 – Exemplary','Menginspirasi keterlibatan seluruh sekolah dengan nilai-nilai IB.','Inspires whole-school engagement with IB values.'),(5,2,1,'1 – Tidak Terlihat','1 – Not Evident','Tidak ada arah jangka panjang yang jelas untuk program.','No clear long-term direction for the programme.'),(6,2,2,'2 – Berkembang','2 – Emerging','Visi ada namun kurang koheren atau tidak ditindaklanjuti.','Vision exists but lacks coherence or follow-through.'),(7,2,3,'3 – Cakap','3 – Proficient','Strategi yang koheren dengan pencapaian yang jelas.','Coherent strategy with clear milestones.'),(8,2,4,'4 – Teladan','4 – Exemplary','Sangat strategis, berpikiran maju, dan mendorong inovasi.','Highly strategic, forward-thinking, and drives innovation.'),(9,3,1,'1 – Tidak Terlihat','1 – Not Evident','Prioritas sering berubah; ekspektasi tidak jelas.','Priorities shift frequently; unclear expectations.'),(10,3,2,'2 – Berkembang','2 – Emerging','Beberapa prioritas dikomunikasikan namun tidak konsisten.','Some priorities communicated but inconsistent.'),(11,3,3,'3 – Cakap','3 – Proficient','Tujuan yang jelas konsisten menjadi rujukan dalam tindakan dan komunikasi.','Clear goals consistently referenced in actions and communication.'),(12,3,4,'4 – Teladan','4 – Exemplary','Tujuan secara konsisten memandu keputusan dan mendorong tujuan kolektif.','Goals consistently guide decisions and foster collective purpose.'),(13,4,1,'1 – Tidak Terlihat','1 – Not Evident','Komunikasi tidak jelas atau tidak konsisten.','Unclear or inconsistent communication.'),(14,4,2,'2 – Berkembang','2 – Emerging','Komunikasi kadang tidak jelas atau terlambat.','Communication sometimes unclear or late.'),(15,4,3,'3 – Cakap','3 – Proficient','Komunikasi jelas, tepat waktu, dan konsisten.','Clear, timely, and consistent communication.'),(16,4,4,'4 – Teladan','4 – Exemplary','Komunikasi memperkuat budaya dan mengurangi ketidakpastian.','Communication strengthens culture and reduces uncertainty.'),(17,5,1,'1 – Tidak Terlihat','1 – Not Evident','Jarang melibatkan guru dalam pengambilan keputusan.','Rarely involves teachers in decisions.'),(18,5,2,'2 – Berkembang','2 – Emerging','Keterlibatan sesekali tetapi tidak konsisten.','Occasional involvement but inconsistent.'),(19,5,3,'3 – Cakap','3 – Proficient','Kolaborasi dan pengambilan keputusan bersama secara teratur.','Regular collaboration and shared decision-making.'),(20,5,4,'4 – Teladan','4 – Exemplary','Budaya kolaboratif yang kuat yang memberdayakan guru.','Strong collaborative culture empowering teachers.'),(21,6,1,'1 – Tidak Terlihat','1 – Not Evident','Tidak mendukung pertumbuhan guru.','Does not support teacher growth.'),(22,6,2,'2 – Berkembang','2 – Emerging','Menyediakan PD namun kurang tindak lanjut.','Provides PD but lacks follow-up.'),(23,6,3,'3 – Cakap','3 – Proficient','Mendukung PD dan praktik reflektif.','Supports PD and reflective practice.'),(24,6,4,'4 – Teladan','4 – Exemplary','Memimpin budaya PD dan memodelkan pembelajaran berkelanjutan.','Leads PD culture and models continuous learning.'),(25,7,1,'1 – Tidak Terlihat','1 – Not Evident','Penegakan kebijakan tidak konsisten.','Inconsistent enforcement.'),(26,7,2,'2 – Berkembang','2 – Emerging','Menyadari kebijakan namun penerapannya tidak konsisten.','Aware but inconsistently applied.'),(27,7,3,'3 – Cakap','3 – Proficient','Menerapkan kebijakan secara bertanggung jawab.','Applies policies responsibly.'),(28,7,4,'4 – Teladan','4 – Exemplary','Membentuk pembaruan kebijakan dan menjadi model integritas.','Shapes policy refinement and models integrity.'),(29,8,1,'1 – Tidak Terlihat','1 – Not Evident','Pemimpin jarang terlihat atau sulit dijangkau.','Leader rarely visible or difficult to reach.'),(30,8,2,'2 – Berkembang','2 – Emerging','Sesekali hadir namun tidak konsisten aksesibel.','Occasionally present but not consistently accessible.'),(31,8,3,'3 – Cakap','3 – Proficient','Hadir, mudah didekati, dan terlibat dengan staf.','Present, approachable, and engaged with staff.'),(32,8,4,'4 – Teladan','4 – Exemplary','Sangat aksesibel dan dipercaya oleh seluruh komunitas.','Deeply accessible and trusted across the whole community.'),(33,9,1,'1 – Tidak Terlihat','1 – Not Evident','Keputusan tidak dapat diprediksi atau dikomunikasikan dengan buruk.','Decisions unpredictable or poorly communicated.'),(34,9,2,'2 – Berkembang','2 – Emerging','Keputusan diambil namun rasionalitasnya tidak jelas.','Decisions made but with unclear rationale.'),(35,9,3,'3 – Cakap','3 – Proficient','Keputusan tepat waktu, logis, dan dikomunikasikan dengan baik.','Decisions timely, logical, and well communicated.'),(36,9,4,'4 – Teladan','4 – Exemplary','Keputusan transparan, konsultatif, dan membangun kepercayaan.','Decisions transparent, consultative, and build trust.'),(37,10,1,'1 – Tidak Terlihat','1 – Not Evident','Umpan balik tidak ada atau tidak membantu.','Feedback absent or unhelpful.'),(38,10,2,'2 – Berkembang','2 – Emerging','Umpan balik ada namun kabur atau jarang.','Feedback exists but vague or infrequent.'),(39,10,3,'3 – Cakap','3 – Proficient','Umpan balik adil, konstruktif, dan dapat ditindaklanjuti.','Feedback fair, constructive, and actionable.'),(40,10,4,'4 – Teladan','4 – Exemplary','Umpan balik sangat mendukung dan meningkatkan pertumbuhan guru.','Feedback deeply supportive and improves teacher growth.'),(41,11,1,'1 – Tidak Terlihat','1 – Not Evident','Tidak terorganisir; sering ada perubahan mendadak.','Disorganized; frequent last-minute changes.'),(42,11,2,'2 – Berkembang','2 – Emerging','Jadwal ada namun tidak konsisten.','Timelines exist but inconsistent.'),(43,11,3,'3 – Cakap','3 – Proficient','Jadwal terencana dengan baik dan koordinasi berjalan lancar.','Well-planned schedules and coordination.'),(44,11,4,'4 – Teladan','4 – Exemplary','Mengantisipasi tantangan; operasional berjalan sempurna.','Anticipates challenges; operations run flawlessly.'),(45,12,1,'1 – Tidak Terlihat','1 – Not Evident','Sumber daya tidak memadai.','Insufficient resources.'),(46,12,2,'2 – Berkembang','2 – Emerging','Sumber daya dasar tersedia.','Basic resources provided.'),(47,12,3,'3 – Cakap','3 – Proficient','Penyediaan yang memadai dan tepat waktu.','Adequate and timely provision.'),(48,12,4,'4 – Teladan','4 – Exemplary','Alokasi strategis yang meningkatkan kualitas pembelajaran.','Strategic allocation enhancing learning.'),(49,13,1,'1 – Tidak Terlihat','1 – Not Evident','Kebijakan diterapkan secara tidak konsisten atau tidak adil.','Policies applied inconsistently or unfairly.'),(50,13,2,'2 – Berkembang','2 – Emerging','Konsistensi dasar ada namun masih ada pengecualian.','Basic consistency but with exceptions.'),(51,13,3,'3 – Cakap','3 – Proficient','Kebijakan jelas, adil, dan dapat diprediksi.','Policies clear, fair, and predictable.'),(52,13,4,'4 – Teladan','4 – Exemplary','Kebijakan diterapkan secara transparan dan membangun kepercayaan.','Policies applied transparently and build trust.'),(53,14,1,'1 – Tidak Terlihat','1 – Not Evident','Lambat atau tidak responsif terhadap masalah.','Slow or unresponsive to issues.'),(54,14,2,'2 – Berkembang','2 – Emerging','Merespons secara tidak konsisten.','Responds inconsistently.'),(55,14,3,'3 – Cakap','3 – Proficient','Merespons dengan cepat dan menyelesaikan masalah secara efektif.','Responds promptly and resolves issues effectively.'),(56,14,4,'4 – Teladan','4 – Exemplary','Mengantisipasi masalah dan mendukung guru secara proaktif.','Anticipates issues and supports teachers proactively.'),(57,15,1,'1 – Tidak Terlihat','1 – Not Evident','Kurang kepedulian terhadap kesejahteraan.','Little concern for wellbeing.'),(58,15,2,'2 – Berkembang','2 – Emerging','Empati tidak konsisten.','Inconsistent empathy.'),(59,15,3,'3 – Cakap','3 – Proficient','Suportif dan mudah didekati.','Supportive and approachable.'),(60,15,4,'4 – Teladan','4 – Exemplary','Menciptakan lingkungan yang sangat mendukung.','Creates a deeply supportive environment.'),(61,16,1,'1 – Tidak Terlihat','1 – Not Evident','Tidak ada fokus pada inkuiri atau agency siswa.','No focus on inquiry or agency.'),(62,16,2,'2 – Berkembang','2 – Emerging','Integrasi minimal.','Minimal integration.'),(63,16,3,'3 – Cakap','3 – Proficient','Dukungan yang konsisten terhadap inkuiri.','Consistent support for inquiry.'),(64,16,4,'4 – Teladan','4 – Exemplary','Memimpin inisiatif yang mendorong agency siswa yang kuat.','Leads initiatives promoting strong student agency.'),(65,17,1,'1 – Tidak Terlihat','1 – Not Evident','Jarang mengakui upaya guru.','Rarely acknowledges teacher effort.'),(66,17,2,'2 – Berkembang','2 – Emerging','Pengakuan sesekali atau superfisial.','Acknowledgement occasional or superficial.'),(67,17,3,'3 – Cakap','3 – Proficient','Memberikan pengakuan dan apresiasi yang bermakna.','Provides meaningful recognition and appreciation.'),(68,17,4,'4 – Teladan','4 – Exemplary','Membangun budaya di mana apresiasi tertanam dan membangun semangat.','Builds a culture where appreciation is embedded and uplifting.'),(69,18,1,'1 – Tidak Terlihat','1 – Not Evident','Ada kekhawatiran tentang keadilan atau keberpihakan.','Concerns about fairness or bias.'),(70,18,2,'2 – Berkembang','2 – Emerging','Menunjukkan keadilan namun kadang tidak konsisten.','Shows fairness but occasionally inconsistent.'),(71,18,3,'3 – Cakap','3 – Proficient','Secara konsisten dapat dipercaya dan adil.','Consistently trustworthy and equitable.'),(72,18,4,'4 – Teladan','4 – Exemplary','Sangat dihormati atas integritas dan profesionalismenya.','Highly respected for integrity and professionalism.'),(73,19,1,'1 – Tidak Terlihat','1 – Not Evident','Memberikan sedikit dukungan untuk kebutuhan belajar yang beragam.','Offers little support for diverse learning needs.'),(74,19,2,'2 – Berkembang','2 – Emerging','Dukungan ada namun terbatas atau tidak konsisten.','Support present but limited or inconsistent.'),(75,19,3,'3 – Cakap','3 – Proficient','Mendukung intervensi yang tepat bagi siswa yang kesulitan maupun yang berbakat.','Supports appropriate interventions for struggling or advanced learners.'),(76,19,4,'4 – Teladan','4 – Exemplary','Aktif memperkuat sistem untuk memastikan setiap siswa mendapat dukungan.','Actively strengthens systems ensuring every student is supported.'),(77,20,1,'1 – Tidak Terlihat','1 – Not Evident','Tidak ada rujukan terhadap misi IB atau profil pelajar; keterlibatan terbatas.','No reference to IB mission or learner profile; limited engagement.'),(78,20,2,'2 – Berkembang','2 – Emerging','Pemahaman dasar filosofi IB dengan penerapan di kelas yang terbatas.','Basic understanding of IB philosophy with limited classroom application.'),(79,20,3,'3 – Cakap','3 – Proficient','Secara konsisten mengintegrasikan nilai-nilai dan literatur IB ke dalam praktik mengajar.','Consistently integrates IB values and literature into teaching practice.'),(80,20,4,'4 – Teladan','4 – Exemplary','Memodelkan filosofi IB dan menginspirasi keterlibatan intelektual mendalam di seluruh sekolah.','Models IB philosophy and inspires deep intellectual engagement across the school.'),(81,21,1,'1 – Tidak Terlihat','1 – Not Evident','Tidak ada dukungan untuk keberagaman bahasa.','No support for language diversity.'),(82,21,2,'2 – Berkembang','2 – Emerging','Mengakui keberagaman bahasa dengan strategi yang minimal.','Acknowledges language diversity with minimal strategies.'),(83,21,3,'3 – Cakap','3 – Proficient','Secara teratur mendukung dan mengintegrasikan pembelajaran multibahasa.','Regularly supports and integrates multilingual learning.'),(84,21,4,'4 – Teladan','4 – Exemplary','Menciptakan lingkungan multibahasa yang kaya dan inklusif yang meningkatkan suara dan identitas siswa.','Creates a rich, inclusive multilingual environment that enhances student voice and identity.'),(85,22,1,'1 – Tidak Terlihat','1 – Not Evident','Tidak ada keterhubungan dengan komunitas atau kepemimpinan siswa.','No connection to community or student leadership.'),(86,22,2,'2 – Berkembang','2 – Emerging','Keterlibatan siswa dan komunitas lokal yang sesekali.','Occasional student involvement and local engagement.'),(87,22,3,'3 – Cakap','3 – Proficient','Aktif mendorong agency siswa dan kemitraan komunitas melalui kegiatan sekolah.','Actively promotes student agency and community partnerships through school events.'),(88,22,4,'4 – Teladan','4 – Exemplary','Memimpin inisiatif berbasis siswa yang berdampak dengan relevansi global.','Leads impactful, student-led initiatives with global relevance.'),(89,23,1,'1 – Tidak Terlihat','1 – Not Evident','Bekerja secara terisolasi.','Works in isolation.'),(90,23,2,'2 – Berkembang','2 – Emerging','Sesekali berpartisipasi dalam perencanaan tim.','Occasionally participates in team planning.'),(91,23,3,'3 – Cakap','3 – Proficient','Secara teratur berkolaborasi dengan rekan dan mendorong kerja tim siswa.','Regularly collaborates with peers and encourages student teamwork.'),(92,23,4,'4 – Teladan','4 – Exemplary','Memimpin praktik kolaboratif dan membangun komunitas pembelajaran profesional yang kuat.','Leads collaborative practices and builds a strong professional learning community.'),(93,24,1,'1 – Tidak Terlihat','1 – Not Evident','Menghindari PD dan kurang integritas profesional.','Avoids PD and lacks professional integrity.'),(94,24,2,'2 – Berkembang','2 – Emerging','Menghadiri PD namun menunjukkan refleksi etis atau penerapan yang terbatas.','Attends PD but shows limited ethical reflection or application.'),(95,24,3,'3 – Cakap','3 – Proficient','Menerapkan pembelajaran PD dan menunjukkan perilaku etis.','Applies PD learning and demonstrates ethical conduct.'),(96,24,4,'4 – Teladan','4 – Exemplary','Memfasilitasi PD dan menjadi model integritas dan profesionalisme.','Facilitates PD and is a model of integrity and professionalism.'),(97,25,1,'1 – Tidak Terlihat','1 – Not Evident','Mengabaikan kebijakan dan menolak perubahan.','Disregards policies and resists change.'),(98,25,2,'2 – Berkembang','2 – Emerging','Menyadari kebijakan; mengikuti prosedur secara tidak konsisten.','Aware of policies; inconsistently follows procedures.'),(99,25,3,'3 – Cakap','3 – Proficient','Menerapkan kebijakan secara bertanggung jawab dan beradaptasi dengan tantangan.','Applies policies responsibly and adapts to challenges.'),(100,25,4,'4 – Teladan','4 – Exemplary','Memengaruhi pengembangan kebijakan dan mendorong budaya sekolah yang tangguh.','Influences policy development and fosters a resilient school culture.'),(101,26,1,'1 – Tidak Terlihat','1 – Not Evident','Tidak ada bukti perencanaan kurikulum atau inovasi.','No evidence of curriculum planning or innovation.'),(102,26,2,'2 – Berkembang','2 – Emerging','Rencana dasar dengan kreativitas yang terbatas.','Basic plans with limited creativity.'),(103,26,3,'3 – Cakap','3 – Proficient','Merancang unit kurikulum yang terstruktur dengan baik dan inovatif.','Designs well-structured, innovative curriculum units.'),(104,26,4,'4 – Teladan','4 – Exemplary','Memimpin pengembangan kurikulum yang kreatif dan menginspirasi inovasi pedagogis.','Leads creative curriculum development and inspires pedagogical innovation.'),(105,27,1,'1 – Tidak Terlihat','1 – Not Evident','Tidak ada integrasi lintas disiplin atau pemikiran kritis.','No interdisciplinary or critical thinking integration.'),(106,27,2,'2 – Berkembang','2 – Emerging','Mencoba membuat koneksi namun dengan kedalaman yang terbatas.','Attempts connections with limited depth.'),(107,27,3,'3 – Cakap','3 – Proficient','Secara teratur mengintegrasikan pemikiran global dan lintas disiplin.','Regularly integrates global and interdisciplinary thinking.'),(108,27,4,'4 – Teladan','4 – Exemplary','Sangat menanamkan pembelajaran lintas disiplin dan mendorong inkuiri siswa yang kaya.','Deeply embeds interdisciplinary learning and fosters rich student inquiry.'),(109,28,1,'1 – Tidak Terlihat','1 – Not Evident','Tidak ada refleksi kurikulum atau strategi komunikasi.','No curriculum reflection or communication strategies.'),(110,28,2,'2 – Berkembang','2 – Emerging','Refleksi jarang dengan masukan siswa yang terbatas.','Infrequent reflection with limited student input.'),(111,28,3,'3 – Cakap','3 – Proficient','Menggunakan umpan balik untuk merevisi pengajaran dan berkomunikasi secara efektif.','Uses feedback to revise teaching and communicates effectively.'),(112,28,4,'4 – Teladan','4 – Exemplary','Memimpin praktik reflektif dan mendorong dialog terbuka dengan siswa dan staf.','Leads reflective practice and fosters open dialogue with students and staff.'),(113,29,1,'1 – Tidak Terlihat','1 – Not Evident','Pengajaran bersifat direktif; tidak ada inkuiri.','Teaching is directive; no inquiry.'),(114,29,2,'2 – Berkembang','2 – Emerging','Beberapa elemen inkuiri namun kepemilikan siswa minimal.','Some inquiry elements but minimal student ownership.'),(115,29,3,'3 – Cakap','3 – Proficient','Menerapkan strategi inkuiri yang mendukung pemikiran kritis.','Applies inquiry strategies that support critical thinking.'),(116,29,4,'4 – Teladan','4 – Exemplary','Inkuiri menjadi inti pembelajaran; siswa memimpin investigasi mereka sendiri.','Inquiry is central to learning; students lead their own investigations.'),(117,30,1,'1 – Tidak Terlihat','1 – Not Evident','Pengajaran seragam tanpa perhatian terhadap kesejahteraan.','One-size-fits-all instruction with no attention to well-being.'),(118,30,2,'2 – Berkembang','2 – Emerging','Beberapa strategi diferensiasi dan kesejahteraan.','Some differentiation and well-being strategies.'),(119,30,3,'3 – Cakap','3 – Proficient','Secara teratur menyesuaikan pengajaran dan memodelkan keseimbangan.','Regularly adapts instruction and models balance.'),(120,30,4,'4 – Teladan','4 – Exemplary','Sangat responsif terhadap kebutuhan siswa dan pendukung pendidikan holistik.','Highly responsive to student needs and a champion of holistic education.'),(121,31,1,'1 – Tidak Terlihat','1 – Not Evident','Dukungan bahasa dan komunikasi lemah.','Language support and communication are weak.'),(122,31,2,'2 – Berkembang','2 – Emerging','Dukungan sesekali dan kejelasan dasar.','Occasional support and basic clarity.'),(123,31,3,'3 – Cakap','3 – Proficient','Mengintegrasikan strategi bahasa dan berkomunikasi secara efektif.','Integrates language strategies and communicates effectively.'),(124,31,4,'4 – Teladan','4 – Exemplary','Unggul dalam praktik komunikasi yang kaya bahasa dan inklusif.','Excels in language-rich, inclusive communication practices.'),(125,32,1,'1 – Tidak Terlihat','1 – Not Evident','Profil pelajar tidak dirujuk atau diteladankan.','Learner profile not referenced or modelled.'),(126,32,2,'2 – Berkembang','2 – Emerging','Menyebut atribut sesekali dengan penilaian yang terbatas.','Mentions attributes occasionally with limited assessment.'),(127,32,3,'3 – Cakap','3 – Proficient','Mempromosikan dan menilai atribut dengan niat yang jelas.','Promotes and assesses attributes with intention.'),(128,32,4,'4 – Teladan','4 – Exemplary','Profil pelajar menjadi inti pembelajaran dan budaya sekolah.','Learner profile is central to learning and school culture.'),(129,33,1,'1 – Tidak Terlihat','1 – Not Evident','Menggunakan penilaian generik atau tidak selaras.','Uses generic or misaligned assessments.'),(130,33,2,'2 – Berkembang','2 – Emerging','Beberapa penggunaan kriteria IB tanpa konsistensi.','Some use of IB criteria without consistency.'),(131,33,3,'3 – Cakap','3 – Proficient','Menggunakan kriteria secara tepat dan mengevaluasi dengan adil.','Uses criteria appropriately and evaluates fairly.'),(132,33,4,'4 – Teladan','4 – Exemplary','Praktik penilaian teladan dan mendukung pemahaman seluruh sekolah.','Assessment practices are exemplary and support school-wide understanding.'),(133,34,1,'1 – Tidak Terlihat','1 – Not Evident','Tidak ada kesempatan untuk refleksi atau suara siswa.','No opportunities for reflection or student voice.'),(134,34,2,'2 – Berkembang','2 – Emerging','Refleksi sesekali atau dipimpin guru.','Reflection is occasional or teacher-led.'),(135,34,3,'3 – Cakap','3 – Proficient','Mendukung refleksi yang teratur dan terstruktur.','Supports regular, structured reflection.'),(136,34,4,'4 – Teladan','4 – Exemplary','Membangun lingkungan belajar yang reflektif dan dipimpin oleh siswa.','Cultivates a reflective, student-led learning environment.'),(137,35,1,'1 – Tidak Terlihat','1 – Not Evident','Data tidak dikumpulkan atau digunakan.','Data is not collected or used.'),(138,35,2,'2 – Berkembang','2 – Emerging','Data ditinjau namun jarang menginformasikan pengajaran.','Data is reviewed but rarely informs teaching.'),(139,35,3,'3 – Cakap','3 – Proficient','Menggunakan data untuk merencanakan dan mendifferensiasi secara efektif.','Uses data to plan and differentiate effectively.'),(140,35,4,'4 – Teladan','4 – Exemplary','Menganalisis dan berbagi wawasan data untuk memimpin peningkatan pengajaran.','Analyses and shares data insights to lead instructional improvements.');
/*!40000 ALTER TABLE `grade_descriptors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `groups`
--

DROP TABLE IF EXISTS `groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `groups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_fixed` tinyint(1) DEFAULT '0' COMMENT '1 = tidak bisa dihapus, selalu muncul di matriks',
  `respondent_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Key yang dipakai di packages & mapping (atasan, guru, dll)',
  `order_num` int DEFAULT '0',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `groups`
--

LOCK TABLES `groups` WRITE;
/*!40000 ALTER TABLE `groups` DISABLE KEYS */;
INSERT INTO `groups` VALUES (1,'Pengurus Yayasan','foundation',1,'atasan',1,'YPKBI dan YPKTB','2026-04-27 06:20:49'),(2,'Pimpinan Sekolah','leader',1,'leader',2,'Kepala Sekolah dan Wakil Kepala Sekolah','2026-04-27 06:20:49'),(3,'Guru','teacher',1,'guru',3,'Seluruh staf pengajar IB Diploma Programme','2026-04-27 06:20:49'),(4,'Kelas XI IPA','student',0,NULL,1,'Siswa kelas XI IPA','2026-04-27 06:20:49'),(5,'Kelas XI IPS','student',0,NULL,2,'Siswa kelas XI IPS','2026-04-27 06:20:49'),(6,'Kelas XII IPA','student',0,NULL,3,'Siswa kelas XII IPA','2026-04-27 06:20:49'),(7,'Kelas XII IPS','student',0,NULL,4,'Siswa kelas XII IPS','2026-04-27 06:20:49'),(8,'Komite Orang Tua','parent',1,'ortu',4,'Perwakilan Komite Sekolah','2026-04-27 06:20:49'),(9,'OSIS / Siswa','student',1,'siswa',5,'Pengurus OSIS','2026-04-27 06:20:49'),(23,'Rekan Sejawat (Guru)','peer',1,'peer',5,NULL,'2026-06-02 19:40:26');
/*!40000 ALTER TABLE `groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `package_questions`
--

DROP TABLE IF EXISTS `package_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `package_questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `package_id` int NOT NULL,
  `question_id` int NOT NULL,
  `question_id_text_override` text COMMENT 'Override teks ID untuk paket ini (NULL = pakai master)',
  `question_en_text_override` text COMMENT 'Override teks EN untuk paket ini (NULL = pakai master)',
  `order_num` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `package_id` (`package_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `package_questions_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `package_questions_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2502 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `package_questions`
--

LOCK TABLES `package_questions` WRITE;
/*!40000 ALTER TABLE `package_questions` DISABLE KEYS */;
INSERT INTO `package_questions` VALUES (1,1,1,NULL,NULL,1),(2,1,2,NULL,NULL,2),(3,1,3,NULL,NULL,3),(4,1,7,NULL,NULL,4),(5,1,9,NULL,NULL,5),(6,1,13,NULL,NULL,6),(7,1,14,NULL,NULL,7),(8,2,1,NULL,NULL,1),(9,2,2,NULL,NULL,2),(10,2,3,NULL,NULL,3),(11,2,4,NULL,NULL,4),(12,2,5,NULL,NULL,5),(13,2,7,NULL,NULL,6),(14,2,8,NULL,NULL,7),(15,2,9,NULL,NULL,8),(16,2,10,NULL,NULL,9),(17,2,11,NULL,NULL,10),(18,2,12,NULL,NULL,11),(19,2,13,NULL,NULL,12),(20,2,14,NULL,NULL,13),(21,2,17,NULL,NULL,14),(22,2,18,NULL,NULL,15),(23,3,3,NULL,NULL,1),(24,3,4,NULL,NULL,2),(25,3,5,NULL,NULL,3),(26,3,6,NULL,NULL,4),(27,3,7,NULL,NULL,5),(28,3,8,NULL,NULL,6),(29,3,9,NULL,NULL,7),(30,3,10,NULL,NULL,8),(31,3,11,NULL,NULL,9),(32,3,12,NULL,NULL,10),(33,3,13,NULL,NULL,11),(34,3,14,NULL,NULL,12),(35,3,15,NULL,NULL,13),(36,3,16,NULL,NULL,14),(37,3,17,NULL,NULL,15),(38,3,18,NULL,NULL,16),(39,3,19,NULL,NULL,17),(40,4,4,NULL,NULL,1),(41,4,7,NULL,NULL,2),(42,4,8,NULL,NULL,3),(43,4,9,NULL,NULL,4),(44,4,14,NULL,NULL,5),(45,4,16,NULL,NULL,6),(46,4,19,NULL,NULL,7),(47,5,4,NULL,NULL,1),(48,5,5,NULL,NULL,2),(49,5,7,NULL,NULL,3),(50,5,8,NULL,NULL,4),(51,5,13,NULL,NULL,5),(52,5,14,NULL,NULL,6),(53,5,16,NULL,NULL,7),(54,5,18,NULL,NULL,8),(55,5,19,NULL,NULL,9),(58,7,20,NULL,NULL,1),(59,7,21,NULL,NULL,2),(60,7,24,NULL,NULL,3),(61,7,25,NULL,NULL,4),(62,7,26,NULL,NULL,5),(63,7,33,NULL,NULL,6),(64,8,20,NULL,NULL,1),(65,8,21,NULL,NULL,2),(66,8,22,NULL,NULL,3),(67,8,23,NULL,NULL,4),(68,8,24,NULL,NULL,5),(69,8,25,NULL,NULL,6),(70,8,26,NULL,NULL,7),(71,8,27,NULL,NULL,8),(72,8,28,NULL,NULL,9),(73,8,29,NULL,NULL,10),(74,8,30,NULL,NULL,11),(75,8,31,NULL,NULL,12),(76,8,32,NULL,NULL,13),(77,8,33,NULL,NULL,14),(78,8,34,NULL,NULL,15),(79,8,35,NULL,NULL,16),(80,9,20,NULL,NULL,1),(81,9,21,NULL,NULL,2),(82,9,22,NULL,NULL,3),(83,9,23,NULL,NULL,4),(84,9,24,NULL,NULL,5),(85,9,25,NULL,NULL,6),(86,9,26,NULL,NULL,7),(87,9,27,NULL,NULL,8),(88,9,28,NULL,NULL,9),(89,9,29,NULL,NULL,10),(90,9,30,NULL,NULL,11),(91,9,31,NULL,NULL,12),(92,9,32,NULL,NULL,13),(93,9,33,NULL,NULL,14),(94,9,34,NULL,NULL,15),(95,9,35,NULL,NULL,16),(103,11,21,'Apakah [Nama] mendukung pembelajaran bahasa, termasuk bahasa ibu dan bahasa tambahan, sambil menyambut perspektif yang beragam untuk memperkaya pembelajaran di kelas dan pemahaman komunitas?',NULL,1),(104,11,22,NULL,NULL,2),(105,11,23,NULL,NULL,3),(106,11,27,NULL,NULL,4),(107,11,28,NULL,NULL,5),(108,11,29,NULL,NULL,6),(109,11,30,NULL,NULL,7),(110,11,31,NULL,NULL,8),(111,11,32,NULL,NULL,9),(112,11,33,NULL,NULL,10),(113,11,34,NULL,NULL,11),(114,11,35,NULL,NULL,12),(2440,6,1,'Apakah Anda telah menunjukkan keselarasan yang kuat dengan misi IB dan profil pelajar dalam filosofi administrasi dan praktik kepemimpinan, memastikan setiap keputusan sekolah mencerminkan nilai-nilai IB serta memberdayakan agency siswa maupun guru?','Has You demonstrated strong alignment with the IB mission and learner profile in administrative philosophy and practice, ensuring schoolwide decisions reflect IB values and empower both student and teacher agency?',1),(2441,6,2,'Apakah Anda telah mengembangkan dan mengartikulasikan rencana strategis yang jelas dan berkelanjutan untuk pertumbuhan program IB, mengarahkan peningkatan jangka panjang dengan menunjukkan pemikiran visioner dan kemampuan beradaptasi terhadap tren pendidikan masa depan?','Has You developed and articulated a clear, sustainable strategic plan for programme growth, guiding long-term improvement while demonstrating visionary thinking and adaptability to future educational trends?',2),(2442,6,3,'Apakah Anda telah mempertahankan tujuan sekolah yang jelas dan konsisten sebagai acuan praktik guru dan pengambilan keputusan institusional, memodelkan kepemimpinan berprinsip dan komitmen terhadap refleksi berkelanjutan?','Has You maintained clear, consistent schoolwide goals that anchor teacher practice and institutional decision-making over time, modeling principled leadership and a commitment to continuous reflection?',3),(2443,6,4,'Apakah Anda mengomunikasikan ekspektasi, jadwal, dan keputusan strategis dengan kejelasan, konsistensi, dan transparansi yang luar biasa, mendorong lingkungan dialog terbuka dan mendengarkan secara aktif?','Has You communicated expectations, timelines, and strategic decisions with exceptional clarity, consistency, and transparency, fostering an environment of open dialogue and active listening?',4),(2444,6,5,'Apakah Anda telah membangun lingkungan profesional yang sangat kolaboratif dan inklusif, di mana guru merasa dihargai, didengar, dan didorong untuk berbagi ide inovatif serta perspektif yang beragam?','Has You fostered a highly collaborative and inclusive professional environment where teachers feel valued, heard, and encouraged to share innovative ideas and diverse perspectives?',5),(2445,6,6,'Apakah Anda telah mengadvokasi pertumbuhan guru dengan memberikan akses yang setara terhadap pengembangan profesional IB yang bermakna, serta berpartisipasi aktif sebagai pelajar seumur hidup untuk memodelkan rasa ingin tahu intelektual?','Has You championed teacher growth by providing equitable access to meaningful IB professional development, and actively participated as a lifelong learner to model intellectual curiosity?',6),(2446,6,7,'Apakah Anda telah memastikan kepatuhan yang ketat terhadap kebijakan IB dan menanamkan budaya integritas akademik di seluruh sekolah, dengan bertindak konsisten berdasarkan kejujuran, etika, dan keadilan dalam semua tindakan kepemimpinan?','Has You ensured rigorous adherence to IB policies and embedded a culture of academic integrity, acting consistently with honesty, ethics, and fairness in all leadership actions?',7),(2447,6,8,'Apakah Anda mempertahankan kehadiran yang konsisten dan mudah dijangkau di seluruh komunitas sekolah, tetap aksesibel dan sangat responsif terhadap realitas keseharian guru, siswa, dan orang tua?','Has You maintained a consistent, approachable presence across the school community, remaining accessible and highly responsive to the daily realities of teachers, students, and parents?',8),(2448,6,9,'Apakah Anda telah membuat keputusan yang tepat waktu, logis, dan berbasis bukti, mengomunikasikan rasionalitas di baliknya dengan jelas agar seluruh staf memahami tujuan dan visi dari setiap perubahan organisasional?','Has You made timely, logical, and evidence-based decisions, communicating the underlying rationale clearly to ensure staff understand the purpose and vision behind organisational changes?',9),(2449,6,10,'Apakah Anda memberikan umpan balik yang konstruktif, penuh respek, dan sangat dapat ditindaklanjuti, yang secara bermakna mendukung pertumbuhan profesional guru dengan tetap menjaga kesetaraan dan keadilan?','Has You provided constructive, respectful, and highly actionable feedback that meaningfully supports teacher professional growth while maintaining strict equity and fairness?',10),(2450,6,11,'Apakah Anda membuat jadwal yang realistis dan terstruktur dengan baik serta memastikan kelancaran operasional harian, menunjukkan pendekatan yang seimbang yang secara aktif menghormati dan mengelola beban kerja serta kesejahteraan guru?','Has You created realistic, well-structured timelines and ensured smooth daily operations, demonstrating a balanced approach that actively respects and manages teacher workload and wellbeing?',11),(2451,6,12,'Apakah Anda mengalokasikan dan mengelola sumber daya pembelajaran serta fasilitas sekolah secara strategis untuk mendukung pembelajaran berbasis inkuiri dan kebutuhan beragam komunitas sekolah secara optimal?','Has You strategically allocated and managed instructional resources and school facilities to optimally support inquiry-based learning and the diverse needs of the school community?',12),(2452,6,13,'Apakah Anda menerapkan kebijakan sekolah dan IB dengan fidelitas, konsistensi, dan keadilan terhadap seluruh staf, secara proaktif menghindari kebingungan atau persepsi keberpihakan?','Has You applied school and IB policies with fidelity, consistency, and fairness across all staff members, proactively avoiding confusion or any perception of bias?',13),(2453,6,14,'Apakah Anda merespons masalah sistemik maupun operasional yang memengaruhi proses belajar mengajar dengan cepat dan efektif, menunjukkan inisiatif pemecahan masalah yang proaktif dan ketahanan dalam menghadapi tantangan?','Has You responded promptly and effectively to systemic or daily issues affecting teaching and learning, demonstrating proactive problem-solving initiative and resilience in the face of challenges?',14),(2454,6,15,'Apakah Anda menunjukkan empati yang mendalam dan secara aktif mendukung guru baik secara profesional maupun emosional, membangun lingkungan kerja yang positif, aman, dan seimbang?','Has You demonstrated deep empathy and actively supported teachers both professionally and emotionally, cultivating a positive, safe, and balanced work environment?',15),(2455,6,16,'Apakah Anda secara sistematis mempromosikan struktur yang memberdayakan pembelajaran yang berpusat pada siswa dan berbasis inkuiri, mendorong siswa untuk mengambil tindakan bermakna dan peran kepemimpinan dalam komunitas?','Has You systematically promoted structures that empower student-centred, inquiry-based learning, encouraging students to take meaningful action and leadership roles within the community?',16),(2456,6,17,'Apakah Anda secara konsisten mengakui upaya guru dan merayakan pencapaian profesional dengan cara yang bermakna, responsif terhadap budaya, dan memotivasi?','Has You consistently acknowledged teacher effort and celebrated professional achievements in a meaningful, culturally responsive, and motivating manner?',17),(2457,6,18,'Apakah Anda membangun kepercayaan melalui perlakuan yang adil, tindakan yang transparan, dan kepatuhan yang konsisten terhadap etika profesional?','Has You built trust through equitable treatment, transparent actions, and consistent adherence to professional ethics?',18),(2458,6,19,'Apakah Anda mendukung struktur dan sistem yang menangani kebutuhan belajar yang beragam, memastikan siswa mendapatkan intervensi yang tepat dan tepat waktu?','Has You supported structures and systems that address diverse learning needs, ensuring students receive appropriate and timely interventions?',19),(2471,12,20,'Apakah Anda telah menunjukkan keselarasan dengan misi IB dan profil pelajar dalam filosofi dan praktik mengajar, serta terlibat secara mendalam dengan buku, ide, isu terkini, dan perilaku siswa?','Has You demonstrated alignment with the IB mission and learner profile in teaching philosophy and practice, engaging with books, ideas, current issues, and student behaviour with depth and insight?',1),(2472,12,21,'Apakah Anda mendukung pembelajaran bahasa, termasuk bahasa ibu dan bahasa tambahan, sambil menyambut perspektif yang beragam untuk memperkaya pembelajaran di kelas dan pemahaman komunitas?','Has You supported language learning, including both mother tongue and additional languages, while welcoming diverse perspectives to enrich classroom learning and community understanding?',2),(2473,12,22,'Apakah Anda terlibat dengan komunitas yang lebih luas untuk mempromosikan tindakan bertanggung jawab dan kewarganegaraan global, memberdayakan siswa untuk berinisiatif dan memimpin dengan tujuan dan pelayanan?','Has You engaged with the wider community to promote responsible action and global citizenship, empowering students to take initiative and lead with purpose and service?',3),(2474,12,23,'Apakah Anda membangun budaya kolaboratif melalui interaksi profesional yang teratur dan bermakna, mendorong kerja tim dan pembelajaran bersama di antara siswa dan kolega?','Has You fostered a collaborative culture through regular and meaningful professional interactions, promoting teamwork and shared learning among students and colleagues?',4),(2475,12,24,'Apakah Anda berpartisipasi dalam dan menerapkan pengembangan profesional yang selaras dengan ekspektasi IB, sambil bertindak secara konsisten berdasarkan kejujuran, etika, dan keadilan?','Has You participated in and applied professional development aligned with IB expectations, while acting consistently with honesty, ethics, and fairness?',5),(2476,12,25,'Apakah Anda mengimplementasikan kebijakan sekolah dengan fidelitas dan berkontribusi pada penyempurnaannya, merespons secara fleksibel terhadap perubahan dan memodelkan ketahanan?','Has You implemented school policies with fidelity and contributed to their ongoing refinement, responding flexibly to change and modeling resilience?',6),(2477,12,26,'Apakah Anda mengembangkan rencana kurikulum yang selaras dengan persyaratan IB dan mempromosikan pengajaran yang inovatif, mendorong kreativitas, rasa ingin tahu, dan pemikiran segar?','Has You developed curriculum plans that align with IB requirements and promote innovative teaching, encouraging creativity, curiosity, and fresh thinking?',7),(2478,12,27,'Apakah Anda mengintegrasikan koneksi lintas disiplin ilmu, termasuk TOK dan konteks global, menganalisis informasi secara bijaksana untuk membimbing siswa berpikir secara mandiri?','Has You integrated interdisciplinary connections, including TOK and global contexts, analysing information thoughtfully to guide students in independent thinking?',8),(2479,12,28,'Apakah Anda merefleksikan dan merevisi kurikulum berdasarkan umpan balik siswa dan persyaratan program, dengan komunikasi yang jelas dan mendengarkan secara aktif untuk membangun dialog yang bermakna?','Has You reflected on and revised curriculum based on student feedback and programme requirements, with clear communication and active listening to build meaningful dialogue?',9),(2480,12,29,'Apakah Anda menggunakan strategi berbasis inkuiri untuk menumbuhkan rasa ingin tahu, keterlibatan, dan kepemilikan belajar siswa, membimbing siswa untuk berpikir secara mandiri dan kritis?','Has You used inquiry-based strategies to foster student curiosity, engagement, and ownership, guiding students to think independently and critically?',10),(2481,12,30,'Apakah Anda mendifferensiasi pembelajaran untuk memenuhi kebutuhan beragam seluruh peserta didik secara efektif, sambil mendukung kesejahteraan siswa dan memodelkan gaya hidup yang sehat dan penuh tujuan?','Has You differentiated instruction to effectively address the diverse needs of all learners, while supporting student well-being and modelling a healthy, purposeful lifestyle?',11),(2482,12,31,'Apakah Anda memberikan dukungan yang konsisten dan terintegrasi untuk pengembangan bahasa di berbagai mata pelajaran, berkomunikasi dengan jelas sambil merangkul perspektif yang beragam?','Has You provided consistent and embedded support for language development across subjects, communicating clearly while embracing diverse perspectives?',12),(2483,12,32,'Apakah Anda mempromosikan dan menilai atribut profil pelajar IB sebagai bagian dari pengalaman belajar sehari-hari, memodelkan keterbukaan pikiran, integritas, dan keseimbangan sebagai bagian dari pertumbuhan holistik?','Has You promoted and assessed IB learner profile attributes as part of daily learning experiences, modelling open-mindedness, integrity, and balance as part of holistic growth?',13),(2484,12,33,'Apakah Anda menerapkan kriteria dan praktik penilaian IB untuk memastikan evaluasi yang autentik dan selaras, sambil menunjukkan pemikiran kritis dan pengambilan keputusan yang etis?','Has You applied IB assessment criteria and practices to ensure authentic and aligned evaluation, while demonstrating critical thinking and ethical decision-making?',14),(2485,12,34,'Apakah Anda memfasilitasi refleksi siswa sebagai bagian yang bermakna dan rutin dari proses pembelajaran, mendorong kepemimpinan dan rasa tujuan pada diri peserta didik?','Has You facilitated student reflection as a meaningful and routine part of the learning process, encouraging leadership and a sense of purpose in learners?',15),(2486,12,35,'Apakah Anda menggunakan data penilaian untuk menginformasikan pengajaran dan meningkatkan hasil belajar siswa, menunjukkan literasi tingkat tinggi dan inovasi dalam menganalisis bukti?','Has You used assessment data to inform instruction and improve student learning outcomes, demonstrating high-level literacy and innovation in analysing evidence?',16);
/*!40000 ALTER TABLE `package_questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `package_weights`
--

DROP TABLE IF EXISTS `package_weights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `package_weights` (
  `id` int NOT NULL AUTO_INCREMENT,
  `package_id` int NOT NULL,
  `weight` decimal(4,2) DEFAULT '1.00',
  `notes` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `package_id` (`package_id`),
  CONSTRAINT `package_weights_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `package_weights`
--

LOCK TABLES `package_weights` WRITE;
/*!40000 ALTER TABLE `package_weights` DISABLE KEYS */;
INSERT INTO `package_weights` VALUES (1,1,1.50,'Default weight for atasan'),(2,2,1.20,'Default weight for peer'),(3,3,1.00,'Default weight for guru'),(4,4,0.80,'Default weight for ortu'),(5,5,0.70,'Default weight for siswa'),(6,6,1.00,'Default weight for self'),(7,7,1.50,'Default weight for atasan'),(8,8,1.30,'Default weight for leader'),(9,9,1.00,'Default weight for peer'),(11,11,0.70,'Default weight for siswa'),(12,12,1.00,'Default weight for self');
/*!40000 ALTER TABLE `package_weights` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `packages`
--

DROP TABLE IF EXISTS `packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `packages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `eval_type_id` int NOT NULL,
  `period_id` int DEFAULT NULL COMMENT 'NULL = template global, ada nilai = terikat periode',
  `respondent_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_self_reflection` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `eval_type_id` (`eval_type_id`),
  KEY `period_id` (`period_id`),
  CONSTRAINT `packages_ibfk_1` FOREIGN KEY (`eval_type_id`) REFERENCES `eval_types` (`id`),
  CONSTRAINT `packages_ibfk_2` FOREIGN KEY (`period_id`) REFERENCES `eval_periods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `packages`
--

LOCK TABLES `packages` WRITE;
/*!40000 ALTER TABLE `packages` DISABLE KEYS */;
INSERT INTO `packages` VALUES (1,'L1','Evaluasi Pimpinan – Oleh Atasan (YPKBI/YPKTB)',1,NULL,'atasan',NULL,0),(2,'L2','Evaluasi Pimpinan – Oleh Rekan Sejawat',1,NULL,'peer',NULL,0),(3,'L3','Evaluasi Pimpinan – Oleh Guru',1,NULL,'guru',NULL,0),(4,'L4','Evaluasi Pimpinan – Oleh Orang Tua',1,NULL,'ortu',NULL,0),(5,'L5','Evaluasi Pimpinan – Oleh Siswa (OSIS)',1,NULL,'siswa',NULL,0),(6,'L6','Refleksi Mandiri – Pimpinan',1,NULL,'self',NULL,1),(7,'T1','Evaluasi Guru – Oleh Atasan (YPKBI/YPKTB)',2,NULL,'atasan',NULL,0),(8,'T2','Evaluasi Guru – Oleh Pimpinan Sekolah',2,NULL,'leader',NULL,0),(9,'T3','Evaluasi Guru – Oleh Rekan Sejawat',2,NULL,'peer',NULL,0),(11,'T5','Evaluasi Guru – Oleh Siswa',2,NULL,'siswa',NULL,0),(12,'T6','Refleksi Mandiri – Guru',2,NULL,'self',NULL,1);
/*!40000 ALTER TABLE `packages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `period_evaluatees`
--

DROP TABLE IF EXISTS `period_evaluatees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `period_evaluatees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `period_id` int NOT NULL,
  `user_id` int NOT NULL,
  `eval_type_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pe` (`period_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `eval_type_id` (`eval_type_id`),
  CONSTRAINT `period_evaluatees_ibfk_1` FOREIGN KEY (`period_id`) REFERENCES `eval_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `period_evaluatees_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `period_evaluatees_ibfk_3` FOREIGN KEY (`eval_type_id`) REFERENCES `eval_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=195 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `period_evaluatees`
--

LOCK TABLES `period_evaluatees` WRITE;
/*!40000 ALTER TABLE `period_evaluatees` DISABLE KEYS */;
/*!40000 ALTER TABLE `period_evaluatees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `period_evaluators`
--

DROP TABLE IF EXISTS `period_evaluators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `period_evaluators` (
  `id` int NOT NULL AUTO_INCREMENT,
  `period_id` int NOT NULL,
  `user_id` int NOT NULL,
  `group_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pev` (`period_id`,`user_id`,`group_id`),
  KEY `user_id` (`user_id`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `period_evaluators_ibfk_1` FOREIGN KEY (`period_id`) REFERENCES `eval_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `period_evaluators_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `period_evaluators_ibfk_3` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=384 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `period_evaluators`
--

LOCK TABLES `period_evaluators` WRITE;
/*!40000 ALTER TABLE `period_evaluators` DISABLE KEYS */;
/*!40000 ALTER TABLE `period_evaluators` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `period_snapshots`
--

DROP TABLE IF EXISTS `period_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `period_snapshots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `period_id` int NOT NULL,
  `snapshot_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'packages, mappings, assignments, users',
  `snapshot_data` json NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `period_id` (`period_id`),
  CONSTRAINT `period_snapshots_ibfk_1` FOREIGN KEY (`period_id`) REFERENCES `eval_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `period_snapshots`
--

LOCK TABLES `period_snapshots` WRITE;
/*!40000 ALTER TABLE `period_snapshots` DISABLE KEYS */;
/*!40000 ALTER TABLE `period_snapshots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `standard_id` int NOT NULL,
  `question_id_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `question_en_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `standard_id` (`standard_id`),
  CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`standard_id`) REFERENCES `standards` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `questions`
--

LOCK TABLES `questions` WRITE;
/*!40000 ALTER TABLE `questions` DISABLE KEYS */;
INSERT INTO `questions` VALUES (1,1,'Apakah [Nama] telah menunjukkan keselarasan yang kuat dengan misi IB dan profil pelajar dalam filosofi administrasi dan praktik kepemimpinan, memastikan setiap keputusan sekolah mencerminkan nilai-nilai IB serta memberdayakan agency siswa maupun guru?','Has [Name] demonstrated strong alignment with the IB mission and learner profile in administrative philosophy and practice, ensuring schoolwide decisions reflect IB values and empower both student and teacher agency?'),(2,2,'Apakah [Nama] telah mengembangkan dan mengartikulasikan rencana strategis yang jelas dan berkelanjutan untuk pertumbuhan program IB, mengarahkan peningkatan jangka panjang dengan menunjukkan pemikiran visioner dan kemampuan beradaptasi terhadap tren pendidikan masa depan?','Has [Name] developed and articulated a clear, sustainable strategic plan for programme growth, guiding long-term improvement while demonstrating visionary thinking and adaptability to future educational trends?'),(3,3,'Apakah [Nama] telah mempertahankan tujuan sekolah yang jelas dan konsisten sebagai acuan praktik guru dan pengambilan keputusan institusional, memodelkan kepemimpinan berprinsip dan komitmen terhadap refleksi berkelanjutan?','Has [Name] maintained clear, consistent schoolwide goals that anchor teacher practice and institutional decision-making over time, modeling principled leadership and a commitment to continuous reflection?'),(4,4,'Apakah [Nama] mengomunikasikan ekspektasi, jadwal, dan keputusan strategis dengan kejelasan, konsistensi, dan transparansi yang luar biasa, mendorong lingkungan dialog terbuka dan mendengarkan secara aktif?','Has [Name] communicated expectations, timelines, and strategic decisions with exceptional clarity, consistency, and transparency, fostering an environment of open dialogue and active listening?'),(5,5,'Apakah [Nama] telah membangun lingkungan profesional yang sangat kolaboratif dan inklusif, di mana guru merasa dihargai, didengar, dan didorong untuk berbagi ide inovatif serta perspektif yang beragam?','Has [Name] fostered a highly collaborative and inclusive professional environment where teachers feel valued, heard, and encouraged to share innovative ideas and diverse perspectives?'),(6,6,'Apakah [Nama] telah mengadvokasi pertumbuhan guru dengan memberikan akses yang setara terhadap pengembangan profesional IB yang bermakna, serta berpartisipasi aktif sebagai pelajar seumur hidup untuk memodelkan rasa ingin tahu intelektual?','Has [Name] championed teacher growth by providing equitable access to meaningful IB professional development, and actively participated as a lifelong learner to model intellectual curiosity?'),(7,7,'Apakah [Nama] telah memastikan kepatuhan yang ketat terhadap kebijakan IB dan menanamkan budaya integritas akademik di seluruh sekolah, dengan bertindak konsisten berdasarkan kejujuran, etika, dan keadilan dalam semua tindakan kepemimpinan?','Has [Name] ensured rigorous adherence to IB policies and embedded a culture of academic integrity, acting consistently with honesty, ethics, and fairness in all leadership actions?'),(8,8,'Apakah [Nama] mempertahankan kehadiran yang konsisten dan mudah dijangkau di seluruh komunitas sekolah, tetap aksesibel dan sangat responsif terhadap realitas keseharian guru, siswa, dan orang tua?','Has [Name] maintained a consistent, approachable presence across the school community, remaining accessible and highly responsive to the daily realities of teachers, students, and parents?'),(9,9,'Apakah [Nama] telah membuat keputusan yang tepat waktu, logis, dan berbasis bukti, mengomunikasikan rasionalitas di baliknya dengan jelas agar seluruh staf memahami tujuan dan visi dari setiap perubahan organisasional?','Has [Name] made timely, logical, and evidence-based decisions, communicating the underlying rationale clearly to ensure staff understand the purpose and vision behind organisational changes?'),(10,10,'Apakah [Nama] memberikan umpan balik yang konstruktif, penuh respek, dan sangat dapat ditindaklanjuti, yang secara bermakna mendukung pertumbuhan profesional guru dengan tetap menjaga kesetaraan dan keadilan?','Has [Name] provided constructive, respectful, and highly actionable feedback that meaningfully supports teacher professional growth while maintaining strict equity and fairness?'),(11,11,'Apakah [Nama] membuat jadwal yang realistis dan terstruktur dengan baik serta memastikan kelancaran operasional harian, menunjukkan pendekatan yang seimbang yang secara aktif menghormati dan mengelola beban kerja serta kesejahteraan guru?','Has [Name] created realistic, well-structured timelines and ensured smooth daily operations, demonstrating a balanced approach that actively respects and manages teacher workload and wellbeing?'),(12,12,'Apakah [Nama] mengalokasikan dan mengelola sumber daya pembelajaran serta fasilitas sekolah secara strategis untuk mendukung pembelajaran berbasis inkuiri dan kebutuhan beragam komunitas sekolah secara optimal?','Has [Name] strategically allocated and managed instructional resources and school facilities to optimally support inquiry-based learning and the diverse needs of the school community?'),(13,13,'Apakah [Nama] menerapkan kebijakan sekolah dan IB dengan fidelitas, konsistensi, dan keadilan terhadap seluruh staf, secara proaktif menghindari kebingungan atau persepsi keberpihakan?','Has [Name] applied school and IB policies with fidelity, consistency, and fairness across all staff members, proactively avoiding confusion or any perception of bias?'),(14,14,'Apakah [Nama] merespons masalah sistemik maupun operasional yang memengaruhi proses belajar mengajar dengan cepat dan efektif, menunjukkan inisiatif pemecahan masalah yang proaktif dan ketahanan dalam menghadapi tantangan?','Has [Name] responded promptly and effectively to systemic or daily issues affecting teaching and learning, demonstrating proactive problem-solving initiative and resilience in the face of challenges?'),(15,15,'Apakah [Nama] menunjukkan empati yang mendalam dan secara aktif mendukung guru baik secara profesional maupun emosional, membangun lingkungan kerja yang positif, aman, dan seimbang?','Has [Name] demonstrated deep empathy and actively supported teachers both professionally and emotionally, cultivating a positive, safe, and balanced work environment?'),(16,16,'Apakah [Nama] secara sistematis mempromosikan struktur yang memberdayakan pembelajaran yang berpusat pada siswa dan berbasis inkuiri, mendorong siswa untuk mengambil tindakan bermakna dan peran kepemimpinan dalam komunitas?','Has [Name] systematically promoted structures that empower student-centred, inquiry-based learning, encouraging students to take meaningful action and leadership roles within the community?'),(17,17,'Apakah [Nama] secara konsisten mengakui upaya guru dan merayakan pencapaian profesional dengan cara yang bermakna, responsif terhadap budaya, dan memotivasi?','Has [Name] consistently acknowledged teacher effort and celebrated professional achievements in a meaningful, culturally responsive, and motivating manner?'),(18,18,'Apakah [Nama] membangun kepercayaan melalui perlakuan yang adil, tindakan yang transparan, dan kepatuhan yang konsisten terhadap etika profesional?','Has [Name] built trust through equitable treatment, transparent actions, and consistent adherence to professional ethics?'),(19,19,'Apakah [Nama] mendukung struktur dan sistem yang menangani kebutuhan belajar yang beragam, memastikan siswa mendapatkan intervensi yang tepat dan tepat waktu?','Has [Name] supported structures and systems that address diverse learning needs, ensuring students receive appropriate and timely interventions?'),(20,20,'Apakah [Nama] telah menunjukkan keselarasan dengan misi IB dan profil pelajar dalam filosofi dan praktik mengajar, serta terlibat secara mendalam dengan buku, ide, isu terkini, dan perilaku siswa?','Has [Name] demonstrated alignment with the IB mission and learner profile in teaching philosophy and practice, engaging with books, ideas, current issues, and student behaviour with depth and insight?'),(21,21,'Apakah [Nama] mendukung pembelajaran bahasa, termasuk bahasa ibu dan bahasa tambahan, sambil menyambut perspektif yang beragam untuk memperkaya pembelajaran di kelas dan pemahaman komunitas?','Has [Name] supported language learning, including both mother tongue and additional languages, while welcoming diverse perspectives to enrich classroom learning and community understanding?'),(22,22,'Apakah [Nama] terlibat dengan komunitas yang lebih luas untuk mempromosikan tindakan bertanggung jawab dan kewarganegaraan global, memberdayakan siswa untuk berinisiatif dan memimpin dengan tujuan dan pelayanan?','Has [Name] engaged with the wider community to promote responsible action and global citizenship, empowering students to take initiative and lead with purpose and service?'),(23,23,'Apakah [Nama] membangun budaya kolaboratif melalui interaksi profesional yang teratur dan bermakna, mendorong kerja tim dan pembelajaran bersama di antara siswa dan kolega?','Has [Name] fostered a collaborative culture through regular and meaningful professional interactions, promoting teamwork and shared learning among students and colleagues?'),(24,24,'Apakah [Nama] berpartisipasi dalam dan menerapkan pengembangan profesional yang selaras dengan ekspektasi IB, sambil bertindak secara konsisten berdasarkan kejujuran, etika, dan keadilan?','Has [Name] participated in and applied professional development aligned with IB expectations, while acting consistently with honesty, ethics, and fairness?'),(25,25,'Apakah [Nama] mengimplementasikan kebijakan sekolah dengan fidelitas dan berkontribusi pada penyempurnaannya, merespons secara fleksibel terhadap perubahan dan memodelkan ketahanan?','Has [Name] implemented school policies with fidelity and contributed to their ongoing refinement, responding flexibly to change and modeling resilience?'),(26,26,'Apakah [Nama] mengembangkan rencana kurikulum yang selaras dengan persyaratan IB dan mempromosikan pengajaran yang inovatif, mendorong kreativitas, rasa ingin tahu, dan pemikiran segar?','Has [Name] developed curriculum plans that align with IB requirements and promote innovative teaching, encouraging creativity, curiosity, and fresh thinking?'),(27,27,'Apakah [Nama] mengintegrasikan koneksi lintas disiplin ilmu, termasuk TOK dan konteks global, menganalisis informasi secara bijaksana untuk membimbing siswa berpikir secara mandiri?','Has [Name] integrated interdisciplinary connections, including TOK and global contexts, analysing information thoughtfully to guide students in independent thinking?'),(28,28,'Apakah [Nama] merefleksikan dan merevisi kurikulum berdasarkan umpan balik siswa dan persyaratan program, dengan komunikasi yang jelas dan mendengarkan secara aktif untuk membangun dialog yang bermakna?','Has [Name] reflected on and revised curriculum based on student feedback and programme requirements, with clear communication and active listening to build meaningful dialogue?'),(29,29,'Apakah [Nama] menggunakan strategi berbasis inkuiri untuk menumbuhkan rasa ingin tahu, keterlibatan, dan kepemilikan belajar siswa, membimbing siswa untuk berpikir secara mandiri dan kritis?','Has [Name] used inquiry-based strategies to foster student curiosity, engagement, and ownership, guiding students to think independently and critically?'),(30,30,'Apakah [Nama] mendifferensiasi pembelajaran untuk memenuhi kebutuhan beragam seluruh peserta didik secara efektif, sambil mendukung kesejahteraan siswa dan memodelkan gaya hidup yang sehat dan penuh tujuan?','Has [Name] differentiated instruction to effectively address the diverse needs of all learners, while supporting student well-being and modelling a healthy, purposeful lifestyle?'),(31,31,'Apakah [Nama] memberikan dukungan yang konsisten dan terintegrasi untuk pengembangan bahasa di berbagai mata pelajaran, berkomunikasi dengan jelas sambil merangkul perspektif yang beragam?','Has [Name] provided consistent and embedded support for language development across subjects, communicating clearly while embracing diverse perspectives?'),(32,32,'Apakah [Nama] mempromosikan dan menilai atribut profil pelajar IB sebagai bagian dari pengalaman belajar sehari-hari, memodelkan keterbukaan pikiran, integritas, dan keseimbangan sebagai bagian dari pertumbuhan holistik?','Has [Name] promoted and assessed IB learner profile attributes as part of daily learning experiences, modelling open-mindedness, integrity, and balance as part of holistic growth?'),(33,33,'Apakah [Nama] menerapkan kriteria dan praktik penilaian IB untuk memastikan evaluasi yang autentik dan selaras, sambil menunjukkan pemikiran kritis dan pengambilan keputusan yang etis?','Has [Name] applied IB assessment criteria and practices to ensure authentic and aligned evaluation, while demonstrating critical thinking and ethical decision-making?'),(34,34,'Apakah [Nama] memfasilitasi refleksi siswa sebagai bagian yang bermakna dan rutin dari proses pembelajaran, mendorong kepemimpinan dan rasa tujuan pada diri peserta didik?','Has [Name] facilitated student reflection as a meaningful and routine part of the learning process, encouraging leadership and a sense of purpose in learners?'),(35,35,'Apakah [Nama] menggunakan data penilaian untuk menginformasikan pengajaran dan meningkatkan hasil belajar siswa, menunjukkan literasi tingkat tinggi dan inovasi dalam menganalisis bukti?','Has [Name] used assessment data to inform instruction and improve student learning outcomes, demonstrating high-level literacy and innovation in analysing evidence?');
/*!40000 ALTER TABLE `questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `responses`
--

DROP TABLE IF EXISTS `responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `responses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `assignment_id` int NOT NULL,
  `question_id` int NOT NULL,
  `grade` tinyint NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_test` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_response` (`assignment_id`,`question_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `responses_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `responses_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  CONSTRAINT `responses_chk_1` CHECK ((`grade` between 1 and 4))
) ENGINE=InnoDB AUTO_INCREMENT=5596 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `responses`
--

LOCK TABLES `responses` WRITE;
/*!40000 ALTER TABLE `responses` DISABLE KEYS */;
/*!40000 ALTER TABLE `responses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `standard_respondent_mapping`
--

DROP TABLE IF EXISTS `standard_respondent_mapping`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `standard_respondent_mapping` (
  `id` int NOT NULL AUTO_INCREMENT,
  `standard_id` int NOT NULL,
  `period_id` int DEFAULT NULL COMMENT 'NULL = template global, ada nilai = terikat periode',
  `respondent_type` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_mapping_period` (`standard_id`,`respondent_type`,`period_id`),
  KEY `standard_id` (`standard_id`),
  KEY `period_id` (`period_id`),
  CONSTRAINT `standard_respondent_mapping_ibfk_1` FOREIGN KEY (`standard_id`) REFERENCES `standards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `standard_respondent_mapping_ibfk_2` FOREIGN KEY (`period_id`) REFERENCES `eval_periods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `standard_respondent_mapping`
--

LOCK TABLES `standard_respondent_mapping` WRITE;
/*!40000 ALTER TABLE `standard_respondent_mapping` DISABLE KEYS */;
INSERT INTO `standard_respondent_mapping` VALUES (1,1,NULL,'atasan',1,'2026-05-22 08:55:27'),(2,2,NULL,'atasan',1,'2026-05-22 08:55:27'),(3,3,NULL,'atasan',1,'2026-05-22 08:55:27'),(4,7,NULL,'atasan',1,'2026-05-22 08:55:27'),(5,9,NULL,'atasan',1,'2026-05-22 08:55:27'),(6,13,NULL,'atasan',1,'2026-05-22 08:55:27'),(7,14,NULL,'atasan',1,'2026-05-22 08:55:27'),(8,1,NULL,'peer',1,'2026-05-22 08:55:27'),(9,2,NULL,'peer',1,'2026-05-22 08:55:27'),(10,3,NULL,'peer',1,'2026-05-22 08:55:27'),(11,4,NULL,'peer',1,'2026-05-22 08:55:27'),(12,5,NULL,'peer',1,'2026-05-22 08:55:27'),(13,7,NULL,'peer',1,'2026-05-22 08:55:27'),(14,8,NULL,'peer',1,'2026-05-22 08:55:27'),(15,9,NULL,'peer',1,'2026-05-22 08:55:27'),(16,10,NULL,'peer',1,'2026-05-22 08:55:27'),(17,11,NULL,'peer',1,'2026-05-22 08:55:27'),(18,12,NULL,'peer',1,'2026-05-22 08:55:27'),(19,13,NULL,'peer',1,'2026-05-22 08:55:27'),(20,14,NULL,'peer',1,'2026-05-22 08:55:27'),(21,17,NULL,'peer',1,'2026-05-22 08:55:27'),(22,18,NULL,'peer',1,'2026-05-22 08:55:27'),(23,3,NULL,'guru',1,'2026-05-22 08:55:27'),(24,4,NULL,'guru',1,'2026-05-22 08:55:27'),(25,5,NULL,'guru',1,'2026-05-22 08:55:27'),(26,6,NULL,'guru',1,'2026-05-22 08:55:27'),(27,7,NULL,'guru',1,'2026-05-22 08:55:27'),(28,8,NULL,'guru',1,'2026-05-22 08:55:27'),(29,9,NULL,'guru',1,'2026-05-22 08:55:27'),(30,10,NULL,'guru',1,'2026-05-22 08:55:27'),(31,11,NULL,'guru',1,'2026-05-22 08:55:27'),(32,12,NULL,'guru',1,'2026-05-22 08:55:27'),(33,13,NULL,'guru',1,'2026-05-22 08:55:27'),(34,14,NULL,'guru',1,'2026-05-22 08:55:27'),(35,15,NULL,'guru',1,'2026-05-22 08:55:27'),(36,16,NULL,'guru',1,'2026-05-22 08:55:27'),(37,17,NULL,'guru',1,'2026-05-22 08:55:27'),(38,18,NULL,'guru',1,'2026-05-22 08:55:27'),(39,19,NULL,'guru',1,'2026-05-22 08:55:27'),(40,4,NULL,'ortu',1,'2026-05-22 08:55:27'),(41,7,NULL,'ortu',1,'2026-05-22 08:55:27'),(42,8,NULL,'ortu',1,'2026-05-22 08:55:27'),(43,9,NULL,'ortu',1,'2026-05-22 08:55:27'),(44,14,NULL,'ortu',1,'2026-05-22 08:55:27'),(45,16,NULL,'ortu',1,'2026-05-22 08:55:27'),(46,19,NULL,'ortu',1,'2026-05-22 08:55:27'),(47,4,NULL,'siswa',1,'2026-05-22 08:55:27'),(48,5,NULL,'siswa',1,'2026-05-22 08:55:27'),(49,7,NULL,'siswa',1,'2026-05-22 08:55:27'),(50,8,NULL,'siswa',1,'2026-05-22 08:55:27'),(51,13,NULL,'siswa',1,'2026-05-22 08:55:27'),(52,14,NULL,'siswa',1,'2026-05-22 08:55:27'),(53,16,NULL,'siswa',1,'2026-05-22 08:55:27'),(54,18,NULL,'siswa',1,'2026-05-22 08:55:27'),(55,19,NULL,'siswa',1,'2026-05-22 08:55:27'),(56,20,NULL,'atasan',1,'2026-05-22 08:55:27'),(57,21,NULL,'atasan',1,'2026-05-22 08:55:27'),(58,24,NULL,'atasan',1,'2026-05-22 08:55:27'),(59,25,NULL,'atasan',1,'2026-05-22 08:55:27'),(60,26,NULL,'atasan',1,'2026-05-22 08:55:27'),(61,33,NULL,'atasan',1,'2026-05-22 08:55:27'),(62,20,NULL,'leader',1,'2026-05-22 08:55:27'),(63,21,NULL,'leader',1,'2026-05-22 08:55:27'),(64,22,NULL,'leader',1,'2026-05-22 08:55:27'),(65,23,NULL,'leader',1,'2026-05-22 08:55:27'),(66,24,NULL,'leader',1,'2026-05-22 08:55:27'),(67,25,NULL,'leader',1,'2026-05-22 08:55:27'),(68,26,NULL,'leader',1,'2026-05-22 08:55:27'),(69,27,NULL,'leader',1,'2026-05-22 08:55:27'),(70,28,NULL,'leader',1,'2026-05-22 08:55:27'),(71,29,NULL,'leader',1,'2026-05-22 08:55:27'),(72,30,NULL,'leader',1,'2026-05-22 08:55:27'),(73,31,NULL,'leader',1,'2026-05-22 08:55:27'),(74,32,NULL,'leader',1,'2026-05-22 08:55:27'),(75,33,NULL,'leader',1,'2026-05-22 08:55:27'),(76,34,NULL,'leader',1,'2026-05-22 08:55:27'),(77,35,NULL,'leader',1,'2026-05-22 08:55:27'),(78,20,NULL,'peer',1,'2026-05-22 08:55:27'),(79,21,NULL,'peer',1,'2026-05-22 08:55:27'),(80,22,NULL,'peer',1,'2026-05-22 08:55:27'),(81,23,NULL,'peer',1,'2026-05-22 08:55:27'),(82,24,NULL,'peer',1,'2026-05-22 08:55:27'),(83,25,NULL,'peer',1,'2026-05-22 08:55:27'),(84,26,NULL,'peer',1,'2026-05-22 08:55:27'),(85,27,NULL,'peer',1,'2026-05-22 08:55:27'),(86,28,NULL,'peer',1,'2026-05-22 08:55:27'),(87,29,NULL,'peer',1,'2026-05-22 08:55:27'),(88,30,NULL,'peer',1,'2026-05-22 08:55:27'),(89,31,NULL,'peer',1,'2026-05-22 08:55:27'),(90,32,NULL,'peer',1,'2026-05-22 08:55:27'),(91,33,NULL,'peer',1,'2026-05-22 08:55:27'),(92,34,NULL,'peer',1,'2026-05-22 08:55:27'),(93,35,NULL,'peer',1,'2026-05-22 08:55:27'),(94,21,NULL,'siswa',1,'2026-05-22 08:55:27'),(95,22,NULL,'siswa',1,'2026-05-22 08:55:27'),(96,23,NULL,'siswa',1,'2026-05-22 08:55:27'),(97,27,NULL,'siswa',1,'2026-05-22 08:55:27'),(98,28,NULL,'siswa',1,'2026-05-22 08:55:27'),(99,29,NULL,'siswa',1,'2026-05-22 08:55:27'),(100,30,NULL,'siswa',1,'2026-05-22 08:55:27'),(101,31,NULL,'siswa',1,'2026-05-22 08:55:27'),(102,32,NULL,'siswa',1,'2026-05-22 08:55:27'),(103,33,NULL,'siswa',1,'2026-05-22 08:55:27'),(104,34,NULL,'siswa',1,'2026-05-22 08:55:27'),(105,35,NULL,'siswa',1,'2026-05-22 08:55:27');
/*!40000 ALTER TABLE `standard_respondent_mapping` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `standard_traits`
--

DROP TABLE IF EXISTS `standard_traits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `standard_traits` (
  `standard_id` int NOT NULL,
  `trait_id` int NOT NULL,
  PRIMARY KEY (`standard_id`,`trait_id`),
  KEY `trait_id` (`trait_id`),
  CONSTRAINT `standard_traits_ibfk_1` FOREIGN KEY (`standard_id`) REFERENCES `standards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `standard_traits_ibfk_2` FOREIGN KEY (`trait_id`) REFERENCES `traits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `standard_traits`
--

LOCK TABLES `standard_traits` WRITE;
/*!40000 ALTER TABLE `standard_traits` DISABLE KEYS */;
INSERT INTO `standard_traits` VALUES (1,1),(20,1),(35,1),(19,2),(21,2),(32,2),(3,3),(9,3),(14,3),(27,3),(29,3),(33,3),(4,4),(8,4),(10,4),(17,4),(18,4),(28,4),(31,4),(7,5),(10,5),(17,5),(18,5),(24,5),(32,5),(33,5),(5,6),(6,6),(8,6),(23,6),(12,7),(13,7),(14,7),(19,7),(25,7),(11,8),(15,8),(30,8),(32,8),(2,9),(26,9),(35,9),(5,10),(6,10),(7,10),(9,10),(16,10),(22,10),(34,10);
/*!40000 ALTER TABLE `standard_traits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `standards`
--

DROP TABLE IF EXISTS `standards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `standards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `domain_id` int NOT NULL,
  `name` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `extended_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `elaboration_id` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `elaboration_en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `order_num` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `domain_id` (`domain_id`),
  CONSTRAINT `standards_ibfk_1` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `standards`
--

LOCK TABLES `standards` WRITE;
/*!40000 ALTER TABLE `standards` DISABLE KEYS */;
INSERT INTO `standards` VALUES (1,1,'Alignment with IB Mission & Learner Profile',NULL,'Demonstrates strong alignment with the IB mission and learner profile in administrative philosophy and practice, ensuring schoolwide decisions reflect IB values and empower both student and teacher agency.','Tes','tes',1),(2,1,'Strategic Vision for IB Programme',NULL,'Develops and articulates a clear, sustainable strategic plan for programme growth, guiding long-term improvement while demonstrating visionary thinking and adaptability to future educational trends.',NULL,NULL,2),(3,1,'Consistency of School Goals & Priorities',NULL,'Maintains clear, consistent schoolwide goals that anchor teacher practice and institutional decision-making over time, modeling principled leadership and a commitment to continuous reflection.',NULL,NULL,3),(4,2,'Communication & Clarity of Direction',NULL,'Communicates expectations, timelines, and strategic decisions with exceptional clarity, consistency, and transparency, fostering an environment of open dialogue and active listening.',NULL,NULL,4),(5,2,'Collaborative School Culture',NULL,'Fosters a highly collaborative and inclusive professional environment where teachers feel valued, heard, and encouraged to share innovative ideas and diverse perspectives.',NULL,NULL,5),(6,2,'Professional Development Leadership',NULL,'Champions teacher growth by providing equitable access to meaningful IB professional development, and actively participates as a lifelong learner to model intellectual curiosity.',NULL,NULL,6),(7,2,'Policy Alignment & Academic Integrity',NULL,'Ensures rigorous adherence to IB policies and embeds a culture of academic integrity, acting consistently with honesty, ethics, and fairness in all leadership actions.',NULL,NULL,7),(8,2,'Visibility & Access to Leadership',NULL,'Maintains a consistent, approachable presence across the school community, remaining accessible and highly responsive to the daily realities of teachers, students, and parents.',NULL,NULL,8),(9,2,'Effectiveness of Decision-Making Processes',NULL,'Makes timely, logical, and evidence-based decisions, communicating the underlying rationale clearly to ensure staff understand the purpose and vision behind organizational changes.',NULL,NULL,9),(10,2,'Quality & Fairness of Feedback to Teachers',NULL,'Provides constructive, respectful, and highly actionable feedback that meaningfully supports teacher professional growth while maintaining strict equity and fairness.',NULL,NULL,10),(11,3,'Organisation, Timelines & Workload',NULL,'Creates realistic, well-structured timelines and ensures smooth daily operations, demonstrating a balanced approach that actively respects and manages teacher workload and wellbeing.',NULL,NULL,11),(12,3,'Resource Management',NULL,'Strategically allocates and manages instructional resources and school facilities to optimally support inquiry-based learning and the diverse needs of the school community.',NULL,NULL,12),(13,3,'Consistency of Policy Implementation',NULL,'Applies school and IB policies with fidelity, consistency, and fairness across all staff members, proactively avoiding confusion or any perception of bias.',NULL,NULL,13),(14,3,'Responsiveness in Problem-Solving',NULL,'Responds promptly and effectively to systemic or daily issues affecting teaching and learning, demonstrating proactive problem-solving initiative and resilience in the face of challenges.',NULL,NULL,14),(15,4,'Support for Teacher Wellbeing',NULL,'Demonstrates deep empathy and actively supports teachers both professionally and emotionally, cultivating a positive, safe, and balanced work environment.',NULL,NULL,15),(16,4,'Support for Student Agency',NULL,'Systematically promotes structures that empower student-centered, inquiry-based learning, encouraging students to take meaningful action and leadership roles within the community.',NULL,NULL,16),(17,4,'Recognition & Appreciation of Teachers',NULL,'Consistently acknowledges teacher effort and celebrates professional achievements in a meaningful, culturally responsive, and motivating manner.',NULL,NULL,17),(18,4,'Fairness & Trustworthiness',NULL,'Builds trust through equitable treatment, transparent actions, and adherence to professional ethics.',NULL,NULL,18),(19,4,'Support for Student Interventions',NULL,'Supports structures and systems that address diverse learning needs, ensuring students receive appropriate interventions.',NULL,NULL,19),(20,5,'Alignment with IB Mission & Learner Profile',NULL,'Demonstrates alignment with the IB mission and learner profile in teaching philosophy and practice, engaging with books, ideas, current issues, and student behaviour with depth and insight.',NULL,NULL,1),(21,5,'Language Learning',NULL,'Supports language learning, including both mother tongue and additional languages, while welcoming diverse perspectives to enrich classroom learning and community understanding.',NULL,NULL,2),(22,5,'Community Engagement',NULL,'Engages with the wider community to promote responsible action and global citizenship, empowering students to take initiative and lead with purpose and service.',NULL,NULL,3),(23,6,'Collaborative Culture',NULL,'Fosters a collaborative culture through regular and meaningful professional interactions, promoting teamwork and shared learning among students and colleagues.',NULL,NULL,4),(24,6,'Professional Development',NULL,'Participates in and applies professional development aligned with IB expectations, while acting consistently with honesty, ethics, and fairness.',NULL,NULL,5),(25,6,'Policy Alignment',NULL,'Implements school policies with fidelity and contributes to their ongoing refinement, responding flexibly to change and modeling resilience.',NULL,NULL,6),(26,7,'Planning',NULL,'Develops curriculum plans that align with IB requirements and promote innovative teaching, encouraging creativity, curiosity, and fresh thinking.',NULL,NULL,7),(27,7,'Interdisciplinary Connections',NULL,'Integrates interdisciplinary connections, including TOK and global contexts, analysing information thoughtfully to guide students in independent thinking.',NULL,NULL,8),(28,7,'Curriculum Reflection',NULL,'Reflects on and revises curriculum based on student feedback and programme requirements, with clear communication and active listening to build meaningful dialogue.',NULL,NULL,9),(29,8,'Inquiry-Based Learning',NULL,'Uses inquiry-based strategies to foster student curiosity, engagement, and ownership, guiding students to think independently and critically.',NULL,NULL,10),(30,8,'Differentiation',NULL,'Differentiates instruction to effectively address the diverse needs of all learners, while supporting student well-being and modelling a healthy, purposeful lifestyle.',NULL,NULL,11),(31,8,'Language Support',NULL,'Provides consistent and embedded support for language development across subjects, communicating clearly while embracing diverse perspectives.',NULL,NULL,12),(32,9,'Learner Profile Development',NULL,'Promotes and assesses IB learner profile attributes as part of daily learning experiences, modelling open-mindedness, integrity, and balance as part of holistic growth.',NULL,NULL,13),(33,9,'Alignment with IB Assessment Philosophy',NULL,'Applies IB assessment criteria and practices to ensure authentic and aligned evaluation, while demonstrating critical thinking and ethical decision-making.',NULL,NULL,14),(34,9,'Student Reflection',NULL,'Facilitates student reflection as a meaningful and routine part of the learning process, encouraging leadership and a sense of purpose in learners.',NULL,NULL,15),(35,9,'Data Use',NULL,'Uses assessment data to inform instruction and improve student learning outcomes, demonstrating high-level literacy and innovation in analysing evidence.',NULL,NULL,16);
/*!40000 ALTER TABLE `standards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_classes`
--

DROP TABLE IF EXISTS `teacher_classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teacher_classes` (
  `teacher_id` int NOT NULL,
  `class_id` int NOT NULL,
  PRIMARY KEY (`teacher_id`,`class_id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `teacher_classes_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_classes_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_classes`
--

LOCK TABLES `teacher_classes` WRITE;
/*!40000 ALTER TABLE `teacher_classes` DISABLE KEYS */;
/*!40000 ALTER TABLE `teacher_classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `traits`
--

DROP TABLE IF EXISTS `traits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `traits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `traits`
--

LOCK TABLES `traits` WRITE;
/*!40000 ALTER TABLE `traits` DISABLE KEYS */;
INSERT INTO `traits` VALUES (1,1,'Highly Literate','Terlibat secara mendalam dengan riset pendidikan, kebijakan, isu global, dan dinamika sekolah untuk mendukung pengambilan keputusan strategis.'),(2,2,'Open Minded','Menghargai perspektif yang beragam dari guru, siswa, dan orang tua untuk panduan kebijakan inklusif.'),(3,3,'Critical Thinker','Mengevaluasi informasi, praktik, dan tantangan dengan analisis yang cermat berbasis bukti.'),(4,4,'Communicative','Berkomunikasi dengan transparansi dan kejelasan, serta mendengarkan secara aktif.'),(5,5,'Integrity','Memodelkan perilaku etis, keadilan, dan akuntabilitas.'),(6,6,'Collaborative','Mendorong lingkungan di mana kerja tim berkembang dan upaya kolektif mendorong kemajuan sekolah.'),(7,7,'Adaptable','Merespons secara efektif terhadap perubahan tuntutan pendidikan, kebijakan, dan kebutuhan komunitas.'),(8,8,'Balanced','Mendorong kesejahteraan di seluruh komunitas sekolah dengan beban kerja yang berkelanjutan.'),(9,9,'Innovative','Memimpin dengan visi dan kreativitas, mendorong ide-ide baru dan pendekatan berpikiran maju.'),(10,10,'Leadership','Memberikan arah strategis yang jelas dan membuat keputusan bijaksana yang mendukung pertumbuhan jangka panjang.');
/*!40000 ALTER TABLE `traits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_groups`
--

DROP TABLE IF EXISTS `user_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_groups` (
  `user_id` int NOT NULL,
  `group_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`group_id`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `user_groups_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_groups_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_groups`
--

LOCK TABLES `user_groups` WRITE;
/*!40000 ALTER TABLE `user_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('superadmin','admin','foundation','leader','teacher','student','parent','tester') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `class_id` int DEFAULT NULL,
  `is_osis` tinyint(1) NOT NULL DEFAULT '0',
  `is_parent_committee` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=179 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (163,'Super Administrator','superadmin@akgb360.app','$2y$10$MrwNHHT5aoT.eQzUNinre.b.JqBdVok0slg7rlKOKwc3D0/Uvc71.','superadmin',NULL,0,0,1,NULL,'2026-06-04 08:32:11'),(164,'Admin Satu','admin1@akgb360.app','$2y$10$MrwNHHT5aoT.eQzUNinre.b.JqBdVok0slg7rlKOKwc3D0/Uvc71.','admin',NULL,0,0,1,NULL,'2026-06-04 08:32:11'),(165,'Admin Dua','admin2@akgb360.app','$2y$10$MrwNHHT5aoT.eQzUNinre.b.JqBdVok0slg7rlKOKwc3D0/Uvc71.','admin',NULL,0,0,1,NULL,'2026-06-04 08:32:11'),(166,'Admin Tiga','admin3@akgb360.app','$2y$10$MrwNHHT5aoT.eQzUNinre.b.JqBdVok0slg7rlKOKwc3D0/Uvc71.','admin',NULL,0,0,1,NULL,'2026-06-04 08:32:11'),(167,'Admin Empat','admin4@akgb360.app','$2y$10$MrwNHHT5aoT.eQzUNinre.b.JqBdVok0slg7rlKOKwc3D0/Uvc71.','admin',NULL,0,0,1,NULL,'2026-06-04 08:32:11'),(168,'Admin Lima','admin5@akgb360.app','$2y$10$MrwNHHT5aoT.eQzUNinre.b.JqBdVok0slg7rlKOKwc3D0/Uvc71.','admin',NULL,0,0,1,NULL,'2026-06-04 08:32:11'),(169,'Tester Satu','tester1@akgb360.app','$2y$10$MrwNHHT5aoT.eQzUNinre.b.JqBdVok0slg7rlKOKwc3D0/Uvc71.','tester',NULL,0,0,1,NULL,'2026-06-04 08:32:11'),(170,'Tester Dua','tester2@akgb360.app','$2y$10$MrwNHHT5aoT.eQzUNinre.b.JqBdVok0slg7rlKOKwc3D0/Uvc71.','tester',NULL,0,0,1,NULL,'2026-06-04 08:32:11'),(171,'Tester Tiga','tester3@akgb360.app','$2y$10$MrwNHHT5aoT.eQzUNinre.b.JqBdVok0slg7rlKOKwc3D0/Uvc71.','tester',NULL,0,0,1,NULL,'2026-06-04 08:32:11'),(172,'Tester Empat','tester4@akgb360.app','$2y$10$MrwNHHT5aoT.eQzUNinre.b.JqBdVok0slg7rlKOKwc3D0/Uvc71.','tester',NULL,0,0,1,NULL,'2026-06-04 08:32:11'),(173,'Tester Lima','tester5@akgb360.app','$2y$10$MrwNHHT5aoT.eQzUNinre.b.JqBdVok0slg7rlKOKwc3D0/Uvc71.','tester',NULL,0,0,1,NULL,'2026-06-04 08:32:11'),(174,'Tester Enam','tester6@akgb360.app','$2y$10$MrwNHHT5aoT.eQzUNinre.b.JqBdVok0slg7rlKOKwc3D0/Uvc71.','tester',NULL,0,0,1,NULL,'2026-06-04 08:32:11'),(175,'Tester Tujuh','tester7@akgb360.app','$2y$10$MrwNHHT5aoT.eQzUNinre.b.JqBdVok0slg7rlKOKwc3D0/Uvc71.','tester',NULL,0,0,1,NULL,'2026-06-04 08:32:11'),(176,'Tester Delapan','tester8@akgb360.app','$2y$10$MrwNHHT5aoT.eQzUNinre.b.JqBdVok0slg7rlKOKwc3D0/Uvc71.','tester',NULL,0,0,1,NULL,'2026-06-04 08:32:11'),(177,'Tester Sembilan','tester9@akgb360.app','$2y$10$MrwNHHT5aoT.eQzUNinre.b.JqBdVok0slg7rlKOKwc3D0/Uvc71.','tester',NULL,0,0,1,NULL,'2026-06-04 08:32:11'),(178,'Tester Sepuluh','tester10@akgb360.app','$2y$10$MrwNHHT5aoT.eQzUNinre.b.JqBdVok0slg7rlKOKwc3D0/Uvc71.','tester',NULL,0,0,1,NULL,'2026-06-04 08:32:11');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-04 18:54:14
