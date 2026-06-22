-- =============================================================
--  مهاجرت: افزودن سیستم آزمون به نصب موجود
--  در phpMyAdmin روی دیتابیس madar_konkur اجرا کنید (بدون از دست رفتن داده).
-- =============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS exams (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, advisor_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL, description VARCHAR(255) DEFAULT NULL,
  exam_type ENUM('single','comprehensive') NOT NULL DEFAULT 'single',
  timing_mode ENUM('total','per_section') NOT NULL DEFAULT 'total',
  duration_min INT UNSIGNED NOT NULL DEFAULT 60, negative_marking TINYINT(1) NOT NULL DEFAULT 1,
  show_review TINYINT(1) NOT NULL DEFAULT 1, shuffle_questions TINYINT(1) NOT NULL DEFAULT 0,
  start_at DATETIME DEFAULT NULL, end_at DATETIME DEFAULT NULL,
  status ENUM('draft','published','closed') NOT NULL DEFAULT 'draft', assign_all TINYINT(1) NOT NULL DEFAULT 1,
  target_fields_json TEXT DEFAULT NULL, target_grades_json TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_exam_advisor (advisor_id), KEY idx_exam_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_sections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, exam_id INT UNSIGNED NOT NULL, subject_id INT UNSIGNED DEFAULT NULL,
  name VARCHAR(80) NOT NULL, duration_min INT UNSIGNED DEFAULT NULL, sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id), KEY idx_sec_exam (exam_id),
  CONSTRAINT fk_sec_exam2 FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_questions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, exam_id INT UNSIGNED NOT NULL, section_id INT UNSIGNED NOT NULL,
  q_text TEXT DEFAULT NULL, q_image VARCHAR(255) DEFAULT NULL,
  opt1 VARCHAR(500), opt2 VARCHAR(500), opt3 VARCHAR(500), opt4 VARCHAR(500),
  correct_opt TINYINT UNSIGNED NOT NULL DEFAULT 1, explanation TEXT DEFAULT NULL, sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id), KEY idx_q_exam (exam_id), KEY idx_q_section (section_id, sort_order),
  CONSTRAINT fk_q_exam2 FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  CONSTRAINT fk_q_section2 FOREIGN KEY (section_id) REFERENCES exam_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, exam_id INT UNSIGNED NOT NULL, student_id INT UNSIGNED NOT NULL,
  status ENUM('in_progress','submitted') NOT NULL DEFAULT 'in_progress',
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, submitted_at DATETIME DEFAULT NULL, deadline_at DATETIME DEFAULT NULL,
  total_score DECIMAL(6,2) DEFAULT NULL, correct_count INT UNSIGNED DEFAULT 0, wrong_count INT UNSIGNED DEFAULT 0, blank_count INT UNSIGNED DEFAULT 0,
  PRIMARY KEY (id), UNIQUE KEY uq_attempt (exam_id, student_id), KEY idx_att_student (student_id),
  CONSTRAINT fk_att_exam2 FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_student2 FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_answers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, attempt_id INT UNSIGNED NOT NULL, question_id INT UNSIGNED NOT NULL,
  selected_opt TINYINT UNSIGNED DEFAULT NULL, flagged TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_answer (attempt_id, question_id), KEY idx_ans_attempt (attempt_id),
  CONSTRAINT fk_ans_attempt2 FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_ans_question2 FOREIGN KEY (question_id) REFERENCES exam_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
