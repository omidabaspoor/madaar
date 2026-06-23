-- =============================================================
--  مَدار · Madar Study OS — SMS Notifications + Meeting Type
--  Date: 2026-06-23
--  Features:
--    1. افزودن ستون session_type به consultation_sessions
--    2. ساخت جدول sms_log برای ردگیری پیامک‌ها
-- =============================================================

SET NAMES utf8mb4;

-- ۱. افزودن ستون نوع جلسه (مشاوره/کلاس)
ALTER TABLE consultation_sessions
ADD COLUMN IF NOT EXISTS session_type ENUM('consultation', 'class') NOT NULL DEFAULT 'consultation' AFTER title;

-- ۲. ساخت جدول لاگ پیامک‌ها
CREATE TABLE IF NOT EXISTS sms_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    template_type VARCHAR(40) NOT NULL,
    related_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    api_response TEXT NULL,
    api_message_id VARCHAR(40) NULL,
    error_message VARCHAR(255) NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_sms_user (user_id, created_at),
    INDEX idx_sms_related (related_id, template_type),
    INDEX idx_sms_status (status, created_at),
    INDEX idx_sms_template_type (template_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- پایان migration
