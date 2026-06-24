-- =============================================================
--  مَدار · Madar Study OS — Exams Upgrade (consolidated)
--  Idempotent. Safe to run multiple times.
--  Includes: exams + exam_sections + exam_questions + exam_attempts
--            + exam_answers + mock_exam_reports + internal_exam_analyses
--            + target_fields/target_grades + cancelled_questions
-- =============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------
-- exams
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS exams (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  advisor_id          INT UNSIGNED NOT NULL,
  title               VARCHAR(160) NOT NULL,
  description         VARCHAR(255) DEFAULT NULL,
  exam_type           ENUM('single','comprehensive') NOT NULL DEFAULT 'single',
  timing_mode         ENUM('total','per_section')    NOT NULL DEFAULT 'total',
  duration_min        INT UNSIGNED NOT NULL DEFAULT 60,
  negative_marking    TINYINT(1) NOT NULL DEFAULT 1,
  show_review         TINYINT(1) NOT NULL DEFAULT 1,
  shuffle_questions   TINYINT(1) NOT NULL DEFAULT 0,
  start_at            DATETIME DEFAULT NULL,
  end_at              DATETIME DEFAULT NULL,
  status              ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
  assign_all          TINYINT(1) NOT NULL DEFAULT 1,
  target_fields_json  TEXT DEFAULT NULL,
  target_grades_json  TEXT DEFAULT NULL,
  creation_mode       VARCHAR(30) NOT NULL DEFAULT 'standard',
  sheet_path          VARCHAR(255) DEFAULT NULL,
  sheet_paths_json    TEXT DEFAULT NULL,
  answer_key          VARCHAR(500) DEFAULT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_exam_advisor (advisor_id),
  KEY idx_exam_status  (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- exam_sections
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_sections (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  exam_id      INT UNSIGNED NOT NULL,
  name         VARCHAR(160) NOT NULL,
  subject_id   INT UNSIGNED DEFAULT NULL,
  duration_min INT UNSIGNED DEFAULT NULL,
  sort_order   INT UNSIGNED NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sec_exam (exam_id, sort_order),
  CONSTRAINT fk_sec_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- exam_questions
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_questions (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  exam_id          INT UNSIGNED NOT NULL,
  section_id       INT UNSIGNED NOT NULL,
  question_number  INT UNSIGNED DEFAULT NULL,
  q_text           TEXT,
  q_image          VARCHAR(255) DEFAULT NULL,
  opt1             VARCHAR(500) DEFAULT NULL,
  opt2             VARCHAR(500) DEFAULT NULL,
  opt3             VARCHAR(500) DEFAULT NULL,
  opt4             VARCHAR(500) DEFAULT NULL,
  correct_opt      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  explanation      TEXT,
  is_cancelled     TINYINT(1) NOT NULL DEFAULT 0,
  cancelled_at     DATETIME DEFAULT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_q_exam   (exam_id, section_id, question_number),
  CONSTRAINT fk_q_exam    FOREIGN KEY (exam_id)    REFERENCES exams(id)        ON DELETE CASCADE,
  CONSTRAINT fk_q_section FOREIGN KEY (section_id) REFERENCES exam_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- exam_attempts
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_attempts (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  exam_id       INT UNSIGNED NOT NULL,
  student_id    INT UNSIGNED NOT NULL,
  start_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deadline_at   DATETIME DEFAULT NULL,
  submit_at     DATETIME DEFAULT NULL,
  total_score   DECIMAL(8,2) DEFAULT NULL,
  total_percent DECIMAL(6,2) DEFAULT NULL,
  negative_count INT UNSIGNED DEFAULT 0,
  blank_count   INT UNSIGNED DEFAULT 0,
  correct_count INT UNSIGNED DEFAULT 0,
  status        ENUM('in_progress','submitted','expired') NOT NULL DEFAULT 'in_progress',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_exam (exam_id, student_id),
  KEY idx_attempt_student (student_id, status),
  CONSTRAINT fk_att_exam    FOREIGN KEY (exam_id)    REFERENCES exams(id)  ON DELETE CASCADE,
  CONSTRAINT fk_att_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- exam_answers
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_answers (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  attempt_id           INT UNSIGNED NOT NULL,
  question_id          INT UNSIGNED NOT NULL,
  selected_opt         TINYINT UNSIGNED DEFAULT NULL,
  flagged              TINYINT(1) NOT NULL DEFAULT 0,
  diagnostic_reason    VARCHAR(60) DEFAULT NULL,
  diagnostic_takeaway  VARCHAR(500) DEFAULT NULL,
  answered_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_answer (attempt_id, question_id),
  KEY idx_ans_question (question_id),
  CONSTRAINT fk_ans_attempt  FOREIGN KEY (attempt_id)  REFERENCES exam_attempts(id)  ON DELETE CASCADE,
  CONSTRAINT fk_ans_question FOREIGN KEY (question_id) REFERENCES exam_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- mock_exam_reports (آزمون‌های آزمایشی بیرونی)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS mock_exam_reports (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED NOT NULL,
  advisor_id       INT UNSIGNED DEFAULT NULL,
  provider         VARCHAR(80) DEFAULT NULL,
  provider_other   VARCHAR(120) DEFAULT NULL,
  exam_title       VARCHAR(180) DEFAULT NULL,
  exam_date        DATE DEFAULT NULL,
  field            VARCHAR(60) DEFAULT NULL,
  grade            VARCHAR(40) DEFAULT NULL,
  total_score      DECIMAL(8,2) DEFAULT NULL,
  total_percent    DECIMAL(6,2) DEFAULT NULL,
  rank_in_exam     INT UNSIGNED DEFAULT NULL,
  participants     INT UNSIGNED DEFAULT NULL,
  total_questions  INT UNSIGNED DEFAULT NULL,
  target_score     DECIMAL(8,2) DEFAULT NULL,
  subjects_json    LONGTEXT NULL,
  behavior_json    LONGTEXT NULL,
  analysis_json    LONGTEXT NULL,
  issues_json      LONGTEXT NULL,
  student_note     TEXT NULL,
  advisor_note     TEXT NULL,
  status           ENUM('draft','submitted') NOT NULL DEFAULT 'submitted',
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mock_student (student_id, exam_date),
  KEY idx_mock_advisor (advisor_id, exam_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- internal_exam_analyses (تحلیل آزمون داخلی)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS internal_exam_analyses (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  attempt_id    INT UNSIGNED NOT NULL,
  exam_id       INT UNSIGNED NOT NULL,
  student_id    INT UNSIGNED NOT NULL,
  advisor_id    INT UNSIGNED DEFAULT NULL,
  behavior_json LONGTEXT NULL,
  analysis_json LONGTEXT NULL,
  student_note  TEXT NULL,
  advisor_note  TEXT NULL,
  status        ENUM('draft','submitted') NOT NULL DEFAULT 'submitted',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_internal_attempt (attempt_id),
  KEY idx_student (student_id),
  KEY idx_advisor (advisor_id),
  KEY idx_exam    (exam_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
