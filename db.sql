-- PrepX — Database Schema
-- Import this via phpMyAdmin before first use
SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone="+06:00";

CREATE TABLE IF NOT EXISTS `users`(
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(180) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('super_admin','teacher','student') NOT NULL DEFAULT 'student',
  `status` ENUM('free','premium') NOT NULL DEFAULT 'free',
  `phone` VARCHAR(20) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` DATETIME DEFAULT NULL,
  PRIMARY KEY(`id`),
  UNIQUE KEY`email`(`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Super Admin — password: Admin@1234
INSERT INTO `users`(`name`,`email`,`password`,`role`,`status`) VALUES
('Super Admin','admin@prepx.com',
 '$2y$10$TKh8H1.PfbuRoumIvLj0TukIgv4OKnmJRHPVh.fqcUX2EGG3WVmYa',
 'super_admin','premium');

CREATE TABLE IF NOT EXISTS `categories`(
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(140) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `type` ENUM('free','premium') NOT NULL DEFAULT 'free',
  `created_by` INT(11) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(`id`),
  UNIQUE KEY`slug`(`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `categories`(`name`,`slug`,`description`,`type`) VALUES
('Code of Criminal Procedure','crpc','CrPC exam set','premium'),
('Penal Code','penal-code','Bangladesh Penal Code','premium'),
('Evidence Act','evidence-act','Evidence Act questions','premium'),
('General Demo','general-demo','Free sample questions for all users','free');

CREATE TABLE IF NOT EXISTS `exams`(
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `category_id` INT(11) NOT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `duration_minutes` INT(5) NOT NULL DEFAULT 60,
  `total_marks` DECIMAL(8,2) NOT NULL DEFAULT 0,
  `pass_marks` DECIMAL(8,2) NOT NULL DEFAULT 0,
  `negative_marking` DECIMAL(4,2) NOT NULL DEFAULT 0.00,
  `mark_per_q` DECIMAL(4,2) NOT NULL DEFAULT 1.00,
  `type` ENUM('free','premium') NOT NULL DEFAULT 'free',
  `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  `instructions` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(`id`),
  KEY`category_id`(`category_id`),
  CONSTRAINT`fk_exam_cat` FOREIGN KEY(`category_id`) REFERENCES`categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `questions`(
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `exam_id` INT(11) NOT NULL,
  `question_text` TEXT NOT NULL,
  `option_a` TEXT NOT NULL,
  `option_b` TEXT NOT NULL,
  `option_c` TEXT NOT NULL,
  `option_d` TEXT NOT NULL,
  `correct_option` ENUM('A','B','C','D') NOT NULL,
  `explanation` TEXT DEFAULT NULL,
  `marks` DECIMAL(4,2) NOT NULL DEFAULT 1.00,
  `q_order` INT(6) NOT NULL DEFAULT 0,
  PRIMARY KEY(`id`),
  KEY`exam_id`(`exam_id`),
  CONSTRAINT`fk_q_exam` FOREIGN KEY(`exam_id`) REFERENCES`exams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `activation_codes`(
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(20) NOT NULL,
  `transaction_id` VARCHAR(100) DEFAULT NULL,
  `access_type` ENUM('full','category') NOT NULL DEFAULT 'full',
  `category_ids` VARCHAR(255) DEFAULT NULL,
  `is_used` TINYINT(1) NOT NULL DEFAULT 0,
  `assigned_to` INT(11) DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` DATETIME DEFAULT NULL,
  PRIMARY KEY(`id`),
  UNIQUE KEY`code`(`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_category_access`(
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `category_id` INT(11) NOT NULL,
  `granted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(`id`),
  UNIQUE KEY`user_cat`(`user_id`,`category_id`),
  CONSTRAINT`fk_uca_user` FOREIGN KEY(`user_id`) REFERENCES`users`(`id`) ON DELETE CASCADE,
  CONSTRAINT`fk_uca_cat` FOREIGN KEY(`category_id`) REFERENCES`categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `exam_attempts`(
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `exam_id` INT(11) NOT NULL,
  `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submitted_at` DATETIME DEFAULT NULL,
  `score` DECIMAL(8,2) DEFAULT NULL,
  `total_marks` DECIMAL(8,2) DEFAULT NULL,
  `correct_count` INT(6) DEFAULT 0,
  `wrong_count` INT(6) DEFAULT 0,
  `skipped_count` INT(6) DEFAULT 0,
  `status` ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',
  PRIMARY KEY(`id`),
  KEY`user_id`(`user_id`),KEY`exam_id`(`exam_id`),
  CONSTRAINT`fk_att_user` FOREIGN KEY(`user_id`) REFERENCES`users`(`id`) ON DELETE CASCADE,
  CONSTRAINT`fk_att_exam` FOREIGN KEY(`exam_id`) REFERENCES`exams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `student_answers`(
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` INT(11) NOT NULL,
  `question_id` INT(11) NOT NULL,
  `chosen_option` ENUM('A','B','C','D') DEFAULT NULL,
  `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY(`id`),
  KEY`attempt_id`(`attempt_id`),KEY`question_id`(`question_id`),
  CONSTRAINT`fk_ans_att` FOREIGN KEY(`attempt_id`) REFERENCES`exam_attempts`(`id`) ON DELETE CASCADE,
  CONSTRAINT`fk_ans_q` FOREIGN KEY(`question_id`) REFERENCES`questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `announcements`(
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `body` TEXT NOT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
