-- =============================================================
--  Madar Multi-Advisor + Activity Logs Upgrade
--  Sajjad Sayyadi = Senior Admin (role=admin)
--  Only admin can create advisors
-- =============================================================
SET NAMES utf8mb4;

-- 1. Add 'admin' role support (already exists, just ensure)
-- No change needed for users table

-- 2. Create Activity Logs table (clean and organized)
CREATE TABLE IF NOT EXISTS activity_logs (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  action        VARCHAR(60) NOT NULL,                    -- e.g. 'advisor_created', 'student_created', 'plan_published'
  target_type   VARCHAR(40) DEFAULT NULL,                -- 'user', 'plan', 'task', 'exam'
  target_id     INT UNSIGNED DEFAULT NULL,
  details       JSON DEFAULT NULL,                       -- extra info (JSON)
  ip_address    VARCHAR(45) DEFAULT NULL,
  user_agent    VARCHAR(255) DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_log_user (user_id),
  KEY idx_log_action (action),
  KEY idx_log_created (created_at),
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Ensure 'ریاضی جامع' is treated as general (omumi)
UPDATE chapters 
SET field = 'omumi' 
WHERE subject_name = 'ریاضی جامع' AND field = 'tajrobi';

-- 4. Make sure Sajjad (admin) exists with correct role (already handled in install)
-- No data change here.