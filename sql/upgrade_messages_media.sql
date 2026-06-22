-- =============================================================
--  مهاجرت: افزودن ارسال عکس، ویس، PDF و فایل به پیام‌ها
--  در phpMyAdmin روی دیتابیس madar_konkur اجرا کنید (بدون از دست رفتن داده).
--  attachment_type: none | image | audio | pdf | file
--  نکته: api/messages.php هم تلاش می‌کند این ستون‌ها را خودکار بسازد.
-- =============================================================
SET NAMES utf8mb4;

ALTER TABLE messages
  ADD COLUMN attachment_type VARCHAR(20) NOT NULL DEFAULT 'none',
  ADD COLUMN attachment_path VARCHAR(255) DEFAULT NULL,
  ADD COLUMN attachment_name VARCHAR(190) DEFAULT NULL,
  ADD COLUMN attachment_mime VARCHAR(80) DEFAULT NULL,
  ADD COLUMN attachment_size INT UNSIGNED DEFAULT NULL;
