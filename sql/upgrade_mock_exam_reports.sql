-- =============================================================
--  مهاجرت: افزودن صفحه آزمون آزمایشی/کنکور بیرونی و تحلیل هوشمند
-- =============================================================
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS mock_exam_reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id INT UNSIGNED NOT NULL,
  advisor_id INT UNSIGNED DEFAULT NULL,
  provider VARCHAR(80) DEFAULT NULL,
  provider_other VARCHAR(120) DEFAULT NULL,
  exam_title VARCHAR(180) DEFAULT NULL,
  exam_date DATE DEFAULT NULL,
  field VARCHAR(60) DEFAULT NULL,
  grade VARCHAR(40) DEFAULT NULL,
  total_score DECIMAL(8,2) DEFAULT NULL,
  total_percent DECIMAL(6,2) DEFAULT NULL,
  rank_in_exam INT UNSIGNED DEFAULT NULL,
  participants INT UNSIGNED DEFAULT NULL,
  total_questions INT UNSIGNED DEFAULT NULL,
  target_score DECIMAL(8,2) DEFAULT NULL,
  subjects_json LONGTEXT NULL,
  behavior_json LONGTEXT NULL,
  analysis_json LONGTEXT NULL,
  issues_json LONGTEXT NULL,
  student_note TEXT NULL,
  advisor_note TEXT NULL,
  status ENUM('draft','submitted') NOT NULL DEFAULT 'submitted',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mock_student (student_id, exam_date),
  KEY idx_mock_advisor (advisor_id, exam_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
