-- =============================================================
--  مهاجرت: هدف‌گذاری انتشار آزمون بر اساس رشته و پایه
--  اگر نصب قدیمی دارید و install.php را اجرا نمی‌کنید، این فایل را اجرا کنید.
-- =============================================================
SET NAMES utf8mb4;

ALTER TABLE exams ADD COLUMN target_fields_json TEXT DEFAULT NULL AFTER assign_all;
ALTER TABLE exams ADD COLUMN target_grades_json TEXT DEFAULT NULL AFTER target_fields_json;
