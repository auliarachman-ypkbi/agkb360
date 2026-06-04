-- ============================================================
-- KTB 360° Evaluation Platform — Database Schema
-- SMA Kemala Taruna Bhayangkara
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ── USERS ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','foundation','leader','teacher','student','parent') NOT NULL DEFAULT 'teacher',
  `is_active` TINYINT(1) DEFAULT 1,
  `last_login` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_role (`role`), INDEX idx_email (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── GROUPS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `groups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_groups` (
  `user_id` INT NOT NULL,
  `group_id` INT NOT NULL,
  PRIMARY KEY (`user_id`,`group_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── EVAL TYPES ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `eval_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TRAITS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `traits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  INDEX idx_code (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── DOMAINS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `domains` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `eval_type_id` INT NOT NULL,
  `code` VARCHAR(10),
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `order_num` INT DEFAULT 0,
  FOREIGN KEY (`eval_type_id`) REFERENCES `eval_types`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── STANDARDS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `standards` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `domain_id` INT NOT NULL,
  `name` VARCHAR(250) NOT NULL,
  `description` TEXT,
  `extended_description` TEXT,
  `order_num` INT DEFAULT 0,
  FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── STANDARD_TRAITS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `standard_traits` (
  `standard_id` INT NOT NULL,
  `trait_id` INT NOT NULL,
  PRIMARY KEY (`standard_id`,`trait_id`),
  FOREIGN KEY (`standard_id`) REFERENCES `standards`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`trait_id`) REFERENCES `traits`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── QUESTIONS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `standard_id` INT NOT NULL,
  `question_id_text` TEXT NOT NULL,
  `question_en_text` TEXT NOT NULL,
  FOREIGN KEY (`standard_id`) REFERENCES `standards`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── GRADE DESCRIPTORS ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `grade_descriptors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `question_id` INT NOT NULL,
  `grade` TINYINT NOT NULL,
  `label_id` VARCHAR(60),
  `label_en` VARCHAR(60),
  `description_id` TEXT,
  `description_en` TEXT,
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PACKAGES ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `packages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(20) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `eval_type_id` INT NOT NULL,
  `respondent_type` VARCHAR(50) NOT NULL,
  `description` TEXT,
  `is_self_reflection` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`eval_type_id`) REFERENCES `eval_types`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PACKAGE_QUESTIONS ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `package_questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `package_id` INT NOT NULL,
  `question_id` INT NOT NULL,
  `order_num` INT DEFAULT 0,
  FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`)
) ENGINE=InnoDB;

-- ── PACKAGE WEIGHTS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `package_weights` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `package_id` INT NOT NULL,
  `weight` DECIMAL(4,2) DEFAULT 1.00,
  `notes` VARCHAR(200),
  FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── EVAL PERIODS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `eval_periods` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `year` INT NOT NULL,
  `start_date` DATE,
  `end_date` DATE,
  `is_active` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ASSIGNMENTS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `period_id` INT NOT NULL,
  `evaluatee_id` INT NOT NULL,
  `evaluator_id` INT NOT NULL,
  `package_id` INT NOT NULL,
  `status` ENUM('pending','in_progress','completed') DEFAULT 'pending',
  `due_date` DATE,
  `completed_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`period_id`) REFERENCES `eval_periods`(`id`),
  FOREIGN KEY (`evaluatee_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`evaluator_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`),
  INDEX idx_evaluatee (`evaluatee_id`),
  INDEX idx_evaluator (`evaluator_id`),
  INDEX idx_status (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── RESPONSES ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `responses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `assignment_id` INT NOT NULL,
  `question_id` INT NOT NULL,
  `grade` TINYINT NOT NULL CHECK (`grade` BETWEEN 1 AND 4),
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_response` (`assignment_id`,`question_id`),
  FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── AI SUGGESTIONS ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ai_suggestions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `evaluatee_id` INT NOT NULL,
  `period_id` INT NOT NULL,
  `raw_suggestion` TEXT,
  `edited_suggestion` TEXT,
  `edited_by` INT NULL,
  `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `edited_at` DATETIME NULL,
  FOREIGN KEY (`evaluatee_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`period_id`) REFERENCES `eval_periods`(`id`),
  UNIQUE KEY `unique_suggestion` (`evaluatee_id`,`period_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SETTINGS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(100) PRIMARY KEY,
  `setting_value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
