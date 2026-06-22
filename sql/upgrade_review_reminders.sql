-- Upgrade: spaced repetition review reminders
CREATE TABLE IF NOT EXISTS review_reminders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id INT UNSIGNED NOT NULL,
  source_task_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED DEFAULT NULL,
  topic_title VARCHAR(180) NOT NULL,
  source VARCHAR(160) DEFAULT NULL,
  first_studied_at DATETIME NOT NULL,
  interval_days INT UNSIGNED NOT NULL,
  review_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
  profile_key VARCHAR(40) DEFAULT NULL,
  profile_label VARCHAR(80) DEFAULT NULL,
  suggested_minutes INT UNSIGNED DEFAULT 15,
  due_date DATE NOT NULL,
  status ENUM('pending','done','dismissed') NOT NULL DEFAULT 'pending',
  notified_at DATETIME DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  quality ENUM('hard','good','easy') DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_review_step (source_task_id, interval_days),
  KEY idx_review_student_due (student_id, status, due_date),
  KEY idx_review_source (source_task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotent repair for installations where the table existed before this migration
ALTER TABLE review_reminders ADD COLUMN IF NOT EXISTS student_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id;
ALTER TABLE review_reminders ADD COLUMN IF NOT EXISTS source_task_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER student_id;
ALTER TABLE review_reminders ADD COLUMN IF NOT EXISTS subject_id INT UNSIGNED DEFAULT NULL AFTER source_task_id;
ALTER TABLE review_reminders ADD COLUMN IF NOT EXISTS topic_title VARCHAR(180) NOT NULL DEFAULT '' AFTER subject_id;
ALTER TABLE review_reminders ADD COLUMN IF NOT EXISTS source VARCHAR(160) DEFAULT NULL AFTER topic_title;
ALTER TABLE review_reminders ADD COLUMN IF NOT EXISTS first_studied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER source;
ALTER TABLE review_reminders ADD COLUMN IF NOT EXISTS interval_days INT UNSIGNED NOT NULL DEFAULT 1 AFTER first_studied_at;
ALTER TABLE review_reminders ADD COLUMN IF NOT EXISTS review_no TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER interval_days;
ALTER TABLE review_reminders ADD COLUMN IF NOT EXISTS profile_key VARCHAR(40) DEFAULT NULL AFTER review_no;
ALTER TABLE review_reminders ADD COLUMN IF NOT EXISTS profile_label VARCHAR(80) DEFAULT NULL AFTER profile_key;
ALTER TABLE review_reminders ADD COLUMN IF NOT EXISTS suggested_minutes INT UNSIGNED DEFAULT 15 AFTER profile_label;
ALTER TABLE review_reminders ADD COLUMN IF NOT EXISTS due_date DATE NOT NULL DEFAULT '1970-01-01' AFTER suggested_minutes;
ALTER TABLE review_reminders ADD COLUMN IF NOT EXISTS status ENUM('pending','done','dismissed') NOT NULL DEFAULT 'pending' AFTER due_date;
ALTER TABLE review_reminders ADD COLUMN IF NOT EXISTS notified_at DATETIME DEFAULT NULL AFTER status;
ALTER TABLE review_reminders ADD COLUMN IF NOT EXISTS completed_at DATETIME DEFAULT NULL AFTER notified_at;
ALTER TABLE review_reminders ADD COLUMN IF NOT EXISTS quality ENUM('hard','good','easy') DEFAULT NULL AFTER completed_at;
ALTER TABLE review_reminders ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER quality;
ALTER TABLE review_reminders ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
