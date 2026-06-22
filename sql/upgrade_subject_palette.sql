-- =============================================================
--  مهاجرت: رنگ‌بندی استاندارد و ملایم درس‌ها برای PDF/برنامه‌ریز
--  گروه‌ها: تجربی، ریاضی، عمومی‌ها
--  در phpMyAdmin روی دیتابیس madar_konkur اجرا کنید.
-- =============================================================
SET NAMES utf8mb4;

SET @advisor_id := (SELECT id FROM users WHERE role IN ('advisor','admin') ORDER BY id ASC LIMIT 1);

-- آپدیت رنگ درس‌های موجود
UPDATE subjects SET color='#6E5B9A' WHERE name IN ('ریاضی','حسابان');
UPDATE subjects SET color='#B58A45' WHERE name='شیمی';
UPDATE subjects SET color='#3F7F9F' WHERE name='فیزیک';
UPDATE subjects SET color='#3B8B5B' WHERE name IN ('زیست','زیست‌شناسی');
UPDATE subjects SET color='#4F8C86' WHERE name='هندسه';
UPDATE subjects SET color='#8A6A52' WHERE name='گسسته';
UPDATE subjects SET color='#6F6F78' WHERE name='هویت';
UPDATE subjects SET color='#C06C84' WHERE name='سلامت';
UPDATE subjects SET color='#A0754C' WHERE name='عربی';
UPDATE subjects SET color='#7A5AA6' WHERE name='دینی';
UPDATE subjects SET color='#9A5A8A' WHERE name='ادبیات';
UPDATE subjects SET color='#5578A6' WHERE name IN ('زبان','زبان انگلیسی');

-- افزودن درس‌های پیشنهادی اگر وجود ندارند
INSERT INTO subjects (advisor_id,name,color,icon)
SELECT @advisor_id,'ریاضی','#6E5B9A','target' WHERE @advisor_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM subjects WHERE name='ریاضی');
INSERT INTO subjects (advisor_id,name,color,icon)
SELECT @advisor_id,'شیمی','#B58A45','book' WHERE @advisor_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM subjects WHERE name='شیمی');
INSERT INTO subjects (advisor_id,name,color,icon)
SELECT @advisor_id,'فیزیک','#3F7F9F','zap' WHERE @advisor_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM subjects WHERE name='فیزیک');
INSERT INTO subjects (advisor_id,name,color,icon)
SELECT @advisor_id,'زیست‌شناسی','#3B8B5B','book' WHERE @advisor_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM subjects WHERE name IN ('زیست','زیست‌شناسی'));
INSERT INTO subjects (advisor_id,name,color,icon)
SELECT @advisor_id,'حسابان','#6E5B9A','target' WHERE @advisor_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM subjects WHERE name='حسابان');
INSERT INTO subjects (advisor_id,name,color,icon)
SELECT @advisor_id,'هندسه','#4F8C86','target' WHERE @advisor_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM subjects WHERE name='هندسه');
INSERT INTO subjects (advisor_id,name,color,icon)
SELECT @advisor_id,'گسسته','#8A6A52','target' WHERE @advisor_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM subjects WHERE name='گسسته');
INSERT INTO subjects (advisor_id,name,color,icon)
SELECT @advisor_id,'هویت','#6F6F78','user' WHERE @advisor_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM subjects WHERE name='هویت');
INSERT INTO subjects (advisor_id,name,color,icon)
SELECT @advisor_id,'سلامت','#C06C84','heart' WHERE @advisor_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM subjects WHERE name='سلامت');
INSERT INTO subjects (advisor_id,name,color,icon)
SELECT @advisor_id,'عربی','#A0754C','book' WHERE @advisor_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM subjects WHERE name='عربی');
INSERT INTO subjects (advisor_id,name,color,icon)
SELECT @advisor_id,'دینی','#7A5AA6','heart' WHERE @advisor_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM subjects WHERE name='دینی');
INSERT INTO subjects (advisor_id,name,color,icon)
SELECT @advisor_id,'ادبیات','#9A5A8A','book' WHERE @advisor_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM subjects WHERE name='ادبیات');
INSERT INTO subjects (advisor_id,name,color,icon)
SELECT @advisor_id,'زبان انگلیسی','#5578A6','globe' WHERE @advisor_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM subjects WHERE name IN ('زبان','زبان انگلیسی'));
