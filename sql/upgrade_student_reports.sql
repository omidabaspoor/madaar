-- Upgrade: advanced student reporting system
CREATE TABLE IF NOT EXISTS student_reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id INT UNSIGNED NOT NULL,
  report_type ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  auto_snapshot_json LONGTEXT NULL,
  advanced_json LONGTEXT NULL,
  status ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
  submitted_at DATETIME DEFAULT NULL,
  advisor_note TEXT NULL,
  reviewed_by INT UNSIGNED DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_report (student_id, report_type, period_start),
  KEY idx_report_student (student_id, report_type, period_start),
  KEY idx_report_status (status, submitted_at),
  CONSTRAINT fk_report_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
