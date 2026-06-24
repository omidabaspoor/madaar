-- =============================================================
--  مهاجرت: نوع‌های جدید تسک + ستون «منبع»
--  نوع‌های جدید: تحلیل آزمون (analysis)، واحد ویژه (special)، آزمون (mock)
--  ستون جدید: source (منبع آزاد که مشاور می‌نویسد، مثل کتاب/آزمون ماز)
--  در phpMyAdmin روی دیتابیس madar_konkur اجرا کنید (یک‌بار).
-- =============================================================
SET NAMES utf8mb4;

ALTER TABLE tasks
  MODIFY task_type ENUM(
    'test','study',
    'study_test','review','textbook','descriptive','exam','reading','custom',
    'analysis','special','mock'
  ) NOT NULL DEFAULT 'study';

-- ستون منبع (اگر وجود نداشت اضافه شود)
ALTER TABLE tasks
  ADD COLUMN source VARCHAR(120) DEFAULT NULL AFTER description;
