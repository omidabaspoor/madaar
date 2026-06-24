-- =============================================================
--  مهاجرت: افزودن نوع‌های جدید تسک به برنامه‌ریز
--  نوع‌های جدید: کتاب درسی، سوال تشریحی
--  در phpMyAdmin روی دیتابیس madar_konkur اجرا کنید.
-- =============================================================
SET NAMES utf8mb4;

ALTER TABLE tasks
  MODIFY task_type ENUM('test','study','study_test','review','textbook','descriptive','exam','reading','custom') NOT NULL DEFAULT 'study';
