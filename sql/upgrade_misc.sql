-- =============================================================
--  مَدار · Madar Study OS — Misc Tables Upgrade (consolidated)
--  Idempotent. Includes:
--    1. advisor_settings + planner_memory
--    2. review_reminders (spaced repetition)
--    3. student_reports (advanced reporting)
--    4. web_push_subscriptions (browser notifications)
--    5. activity_logs (multi-advisor audit)
--    6. advisor_student_access (restricted access mode)
-- =============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------
-- 1. advisor_settings : پیکربندی هر مشاور (key/value)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS advisor_settings (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  advisor_id  INT UNSIGNED NOT NULL,
  skey        VARCHAR(60) NOT NULL,
  svalue      VARCHAR(255) DEFAULT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_advisor_key (advisor_id, skey),
  KEY idx_setting_advisor (advisor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. planner_memory : حافظه‌ی هوشمند پرکردن خودکار
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS planner_memory (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  advisor_id    INT UNSIGNED NOT NULL,
  scope         VARCHAR(20) NOT NULL DEFAULT 'global',
  ctx_key       VARCHAR(60) NOT NULL DEFAULT '*',
  task_type     VARCHAR(20) DEFAULT NULL,
  subject_id    INT UNSIGNED DEFAULT NULL,
  target_count  INT DEFAULT NULL,
  target_unit   VARCHAR(20) DEFAULT NULL,
  duration_min  INT DEFAULT NULL,
  priority      VARCHAR(10) DEFAULT NULL,
  source        VARCHAR(120) DEFAULT NULL,
  hits          INT UNSIGNED NOT NULL DEFAULT 1,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mem (advisor_id, scope, ctx_key),
  KEY idx_mem_advisor (advisor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. review_reminders : مرور فاصله‌دار (Spaced Repetition)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS review_reminders (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id        INT UNSIGNED NOT NULL,
  source_task_id    INT UNSIGNED NOT NULL,
  subject_id        INT UNSIGNED DEFAULT NULL,
  topic_title       VARCHAR(180) NOT NULL,
  source            VARCHAR(160) DEFAULT NULL,
  first_studied_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  interval_days     INT UNSIGNED NOT NULL DEFAULT 1,
  review_no         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  profile_key       VARCHAR(40) DEFAULT NULL,
  profile_label     VARCHAR(80) DEFAULT NULL,
  suggested_minutes INT UNSIGNED DEFAULT 15,
  due_date          DATE NOT NULL,
  status            ENUM('pending','done','dismissed') NOT NULL DEFAULT 'pending',
  notified_at       DATETIME DEFAULT NULL,
  completed_at      DATETIME DEFAULT NULL,
  quality           ENUM('hard','good','easy') DEFAULT NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_review_step (source_task_id, interval_days),
  KEY idx_review_student_due (student_id, status, due_date),
  KEY idx_review_source      (source_task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4. student_reports : گزارش‌های حرفه‌ای دانش‌آموز
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS student_reports (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id          INT UNSIGNED NOT NULL,
  report_type         ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
  period_start        DATE NOT NULL,
  period_end          DATE NOT NULL,
  auto_snapshot_json  LONGTEXT NULL,
  advanced_json       LONGTEXT NULL,
  status              ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
  submitted_at        DATETIME DEFAULT NULL,
  advisor_note        TEXT NULL,
  reviewed_by         INT UNSIGNED DEFAULT NULL,
  reviewed_at         DATETIME DEFAULT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_report (student_id, report_type, period_start),
  KEY idx_report_student (student_id, report_type, period_start),
  KEY idx_report_status  (status, submitted_at),
  CONSTRAINT fk_report_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 5. web_push_subscriptions : اعلان واقعی مرورگر
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS web_push_subscriptions (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED NOT NULL,
  endpoint        TEXT NOT NULL,
  p256dh          VARCHAR(255) NOT NULL,
  auth            VARCHAR(255) NOT NULL,
  user_agent      VARCHAR(255) DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_success_at DATETIME DEFAULT NULL,
  last_error      VARCHAR(255) DEFAULT NULL,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_wps_user (user_id, is_active),
  UNIQUE KEY uniq_wps_endpoint_hash (endpoint(191)),
  CONSTRAINT fk_wps_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 6. activity_logs : لاگ ممیزی فعالیت‌ها
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  action      VARCHAR(60) NOT NULL,
  target_type VARCHAR(40) DEFAULT NULL,
  target_id   INT UNSIGNED DEFAULT NULL,
  details     JSON DEFAULT NULL,
  ip_address  VARCHAR(45) DEFAULT NULL,
  user_agent  VARCHAR(255) DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_log_user    (user_id),
  KEY idx_log_action  (action),
  KEY idx_log_created (created_at),
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 7. advisor_student_access : تخصیص دانش‌آموز به مشاور محدود
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS advisor_student_access (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  advisor_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_access (advisor_id, student_id),
  CONSTRAINT fk_asa_advisor FOREIGN KEY (advisor_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_asa_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
