-- =============================================================
--  Madar · Upgrade: Add "ریاضی جامع" subject + chapters
--  Chapters from the image you sent
-- =============================================================
SET NAMES utf8mb4;

-- 1. Ensure subject exists
INSERT IGNORE INTO subjects (name, color, icon) 
VALUES ('ریاضی جامع', '#2E5A8C', 'target');

-- 2. Ensure chapters table exists
CREATE TABLE IF NOT EXISTS chapters (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_name  VARCHAR(80) NOT NULL,
  grade         INT UNSIGNED NOT NULL,
  field         VARCHAR(30) NOT NULL,
  book_name     VARCHAR(120) NOT NULL,
  chapter_name  VARCHAR(200) NOT NULL,
  sort_order    INT UNSIGNED NOT NULL DEFAULT 0,
  is_system     TINYINT(1) NOT NULL DEFAULT 1,
  advisor_id    INT UNSIGNED DEFAULT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_chap_subject (subject_name, grade, field, is_active),
  KEY idx_chap_advisor (advisor_id),
  KEY idx_chap_book (book_name, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Remove old system chapters for this subject
DELETE FROM chapters 
WHERE is_system = 1 
  AND advisor_id IS NULL 
  AND subject_name = 'ریاضی جامع';

-- 4. Insert chapters (from your image)
INSERT IGNORE INTO chapters (subject_name, grade, field, book_name, chapter_name, sort_order, is_system, advisor_id, is_active) VALUES
  ('ریاضی جامع', 12, 'tajrobi', 'ریاضی جامع', 'مجموعه، الگو و دنباله', 0, 1, NULL, 1),
  ('ریاضی جامع', 12, 'tajrobi', 'ریاضی جامع', 'توان‌های گویا و عبارت‌های جبری', 1, 1, NULL, 1),
  ('ریاضی جامع', 12, 'tajrobi', 'ریاضی جامع', 'معادله‌ها و نامعادله‌ها', 2, 1, NULL, 1),
  ('ریاضی جامع', 12, 'tajrobi', 'ریاضی جامع', 'هندسه تحلیلی', 3, 1, NULL, 1),
  ('ریاضی جامع', 12, 'tajrobi', 'ریاضی جامع', 'معادله درجه دو و سهمی', 4, 1, NULL, 1),
  ('ریاضی جامع', 12, 'tajrobi', 'ریاضی جامع', 'توابع نمایی و لگاریتمی', 5, 1, NULL, 1),
  ('ریاضی جامع', 12, 'tajrobi', 'ریاضی جامع', 'حد', 6, 1, NULL, 1),
  ('ریاضی جامع', 12, 'tajrobi', 'ریاضی جامع', 'پیوستگی', 7, 1, NULL, 1),
  ('ریاضی جامع', 12, 'tajrobi', 'ریاضی جامع', 'حد بی‌نهایت و حد در بی‌نهایت', 8, 1, NULL, 1),
  ('ریاضی جامع', 12, 'tajrobi', 'ریاضی جامع', 'تابع', 9, 1, NULL, 1),
  ('ریاضی جامع', 12, 'tajrobi', 'ریاضی جامع', 'مثلثات', 10, 1, NULL, 1),
  ('ریاضی جامع', 12, 'tajrobi', 'ریاضی جامع', 'مشتق', 11, 1, NULL, 1),
  ('ریاضی جامع', 12, 'tajrobi', 'ریاضی جامع', 'کاربرد مشتق', 12, 1, NULL, 1),
  ('ریاضی جامع', 12, 'tajrobi', 'ریاضی جامع', 'هندسه و مقاطع', 13, 1, NULL, 1),
  ('ریاضی جامع', 12, 'tajrobi', 'ریاضی جامع', 'آمار', 14, 1, NULL, 1),
  ('ریاضی جامع', 12, 'tajrobi', 'ریاضی جامع', 'شمارش بدون شمردن', 15, 1, NULL, 1),
  ('ریاضی جامع', 12, 'tajrobi', 'ریاضی جامع', 'احتمال', 16, 1, NULL, 1);