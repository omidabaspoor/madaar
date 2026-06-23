-- =============================================================
--  مَدار · Madar Study OS — Online Sessions System (Beta)
--  Date: 2026-06-23
--  Features:
--    1. جدول جلسات آنلاین (online_sessions)
--    2. جدول شرکت‌کنندگان مجاز (session_participants)
--    3. جدول تخته سفید (whiteboard_snapshots)
--    4. جدول چت جلسه (session_chat_messages)
--    5. جدول واکنش‌ها (session_reactions)
--    6. جدول دست بلند کردن (session_hand_raises)
-- =============================================================

SET NAMES utf8mb4;

-- ۱. جدول جلسات آنلاین
CREATE TABLE IF NOT EXISTS online_sessions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    advisor_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT,
    scheduled_at DATETIME,
    duration_min INT UNSIGNED DEFAULT 60,
    max_participants INT UNSIGNED DEFAULT 6,
    jitsi_room_name VARCHAR(80) NOT NULL,
    jitsi_password VARCHAR(40),
    -- تنظیمات دسترسی
    allow_student_mic TINYINT(1) DEFAULT 1,
    allow_student_cam TINYINT(1) DEFAULT 1,
    allow_screen_share TINYINT(1) DEFAULT 1,
    allow_whiteboard TINYINT(1) DEFAULT 1,
    allow_chat TINYINT(1) DEFAULT 1,
    -- وضعیت
    status ENUM('draft','scheduled','live','ended','cancelled') NOT NULL DEFAULT 'draft',
    started_at DATETIME,
    ended_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_jitsi_room (jitsi_room_name),
    KEY idx_online_advisor (advisor_id, status, scheduled_at),
    KEY idx_online_status_sched (status, scheduled_at),
    CONSTRAINT fk_online_advisor FOREIGN KEY (advisor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ۲. شرکت‌کنندگان مجاز (دانش‌آموزان دعوت‌شده)
CREATE TABLE IF NOT EXISTS session_participants (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    joined_at DATETIME,
    left_at DATETIME,
    duration_seconds INT UNSIGNED,
    is_present TINYINT(1) DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_participant (session_id, student_id),
    KEY idx_part_session (session_id),
    KEY idx_part_student (student_id),
    CONSTRAINT fk_part_session FOREIGN KEY (session_id) REFERENCES online_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_part_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ۳. وضعیت تخته سفید (Collaborative Whiteboard)
CREATE TABLE IF NOT EXISTS whiteboard_snapshots (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    version INT UNSIGNED DEFAULT 1,
    saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_wb_session (session_id, saved_at),
    KEY idx_wb_version (session_id, version DESC),
    CONSTRAINT fk_wb_session FOREIGN KEY (session_id) REFERENCES online_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_wb_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ۴. پیام‌های چت جلسه
CREATE TABLE IF NOT EXISTS session_chat_messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    user_name VARCHAR(120) NOT NULL,
    user_role VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text','emoji','system','file') DEFAULT 'text',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_chat_session (session_id, created_at),
    KEY idx_chat_user (user_id, created_at),
    CONSTRAINT fk_chat_session FOREIGN KEY (session_id) REFERENCES online_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ۵. واکنش‌ها (Reactions)
CREATE TABLE IF NOT EXISTS session_reactions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    user_name VARCHAR(120) NOT NULL,
    reaction_type VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_react_session (session_id, created_at),
    CONSTRAINT fk_react_session FOREIGN KEY (session_id) REFERENCES online_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_react_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ۶. دست بلند کردن (Raise Hand)
CREATE TABLE IF NOT EXISTS session_hand_raises (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    user_name VARCHAR(120) NOT NULL,
    user_role VARCHAR(20) NOT NULL,
    raised_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    acknowledged_at DATETIME,
    PRIMARY KEY (id),
    KEY idx_hand_session_raised (session_id, raised_at),
    CONSTRAINT fk_hand_session FOREIGN KEY (session_id) REFERENCES online_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_hand_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- پایان migration
