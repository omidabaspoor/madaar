-- =============================================================
--  مَدار · Madar Study OS — Meetings & Online Sessions
--  Idempotent. Includes:
--    1. consultation_sessions (with draft + SMS hooks)
--    2. sms_log
--    3. online_sessions + participants + whiteboard + chat
--       + reactions + hand_raises
-- =============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------
-- 1. consultation_sessions
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS consultation_sessions (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  advisor_id      INT UNSIGNED NOT NULL,
  student_id      INT UNSIGNED NOT NULL,
  title           VARCHAR(150) NOT NULL,
  session_type    ENUM('consultation','class') NOT NULL DEFAULT 'consultation',
  draft_group_id  VARCHAR(40) DEFAULT NULL,
  session_date    DATE NOT NULL,
  session_time    TIME NULL,
  notes           TEXT NULL,
  status          ENUM('draft','scheduled','completed','cancelled') NOT NULL DEFAULT 'draft',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_session_student (student_id, status, session_date),
  KEY idx_session_advisor (advisor_id, status, session_date),
  KEY idx_session_type    (advisor_id, session_type, session_date),
  KEY idx_session_draft   (advisor_id, draft_group_id, status),
  CONSTRAINT fk_sess_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_sess_advisor FOREIGN KEY (advisor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. sms_log
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS sms_log (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED NOT NULL,
  phone           VARCHAR(20) NOT NULL,
  message         TEXT NOT NULL,
  template_type   VARCHAR(40) NOT NULL,
  related_id      INT UNSIGNED NOT NULL,
  status          ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  api_response    TEXT NULL,
  api_message_id  VARCHAR(40) NULL,
  error_message   VARCHAR(255) NULL,
  sent_at         DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_sms_user         (user_id, created_at),
  INDEX idx_sms_related      (related_id, template_type),
  INDEX idx_sms_status       (status, created_at),
  INDEX idx_sms_template_type (template_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. online_sessions
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS online_sessions (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  advisor_id          INT UNSIGNED NOT NULL,
  title               VARCHAR(180) NOT NULL,
  description         TEXT,
  scheduled_at        DATETIME,
  duration_min        INT UNSIGNED DEFAULT 60,
  max_participants    INT UNSIGNED DEFAULT 6,
  jitsi_room_name     VARCHAR(80) NOT NULL,
  jitsi_password      VARCHAR(40),
  allow_student_mic   TINYINT(1) DEFAULT 1,
  allow_student_cam   TINYINT(1) DEFAULT 1,
  allow_screen_share  TINYINT(1) DEFAULT 1,
  allow_whiteboard    TINYINT(1) DEFAULT 1,
  allow_chat          TINYINT(1) DEFAULT 1,
  status              ENUM('draft','scheduled','live','ended','cancelled') NOT NULL DEFAULT 'draft',
  started_at          DATETIME,
  ended_at            DATETIME,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jitsi_room (jitsi_room_name),
  KEY idx_online_advisor (advisor_id, status, scheduled_at),
  KEY idx_online_status_sched (status, scheduled_at),
  CONSTRAINT fk_online_advisor FOREIGN KEY (advisor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4. session_participants
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS session_participants (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id      INT UNSIGNED NOT NULL,
  student_id      INT UNSIGNED NOT NULL,
  joined_at       DATETIME,
  left_at         DATETIME,
  duration_seconds INT UNSIGNED,
  is_present      TINYINT(1) DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_participant (session_id, student_id),
  KEY idx_part_session (session_id),
  KEY idx_part_student  (student_id),
  CONSTRAINT fk_part_session FOREIGN KEY (session_id) REFERENCES online_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_part_student  FOREIGN KEY (student_id) REFERENCES users(id)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 5. whiteboard_snapshots
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS whiteboard_snapshots (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id    INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  snapshot_json LONGTEXT NOT NULL,
  version       INT UNSIGNED DEFAULT 1,
  saved_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_wb_session (session_id, saved_at),
  KEY idx_wb_version (session_id, version DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 6. session_chat_messages
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS session_chat_messages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id  INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  user_name   VARCHAR(120) NOT NULL,
  user_role   VARCHAR(20)  NOT NULL,
  message     TEXT NOT NULL,
  message_type ENUM('text','emoji','system','file') DEFAULT 'text',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_chat_session (session_id, created_at),
  KEY idx_chat_user    (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 7. session_reactions
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS session_reactions (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id    INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  user_name     VARCHAR(120) NOT NULL,
  reaction_type VARCHAR(20)  NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_react_session (session_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 8. session_hand_raises
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS session_hand_raises (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id      INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  user_name       VARCHAR(120) NOT NULL,
  user_role       VARCHAR(20)  NOT NULL,
  raised_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  acknowledged_at DATETIME,
  PRIMARY KEY (id),
  KEY idx_hand_session_raised (session_id, raised_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
