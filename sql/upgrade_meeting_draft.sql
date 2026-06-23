-- =============================================================
--  مَدار · Madar Study OS — Meeting Draft System
--  Date: 2026-06-23
--  Features:
--    1. اضافه کردن وضعیت 'draft' به consultation_sessions
--    2. اضافه کردن ستون draft_group_id برای گروه‌بندی پیش‌نویس‌های کلاس
-- =============================================================

SET NAMES utf8mb4;

-- ۱. اضافه کردن وضعیت 'draft' به ENUM
ALTER TABLE consultation_sessions
MODIFY COLUMN status ENUM('draft','scheduled','completed','cancelled') NOT NULL DEFAULT 'draft';

-- ۲. اضافه کردن ستون draft_group_id برای گروه‌بندی کلاس‌ها
ALTER TABLE consultation_sessions
ADD COLUMN IF NOT EXISTS draft_group_id VARCHAR(40) DEFAULT NULL AFTER session_type,
ADD INDEX IF NOT EXISTS idx_session_draft_group (advisor_id, draft_group_id, status);

-- ۳. تنظیم جلسات قدیمی به حالت scheduled (نه draft)
UPDATE consultation_sessions SET status='scheduled' WHERE status='draft';

-- پایان migration
