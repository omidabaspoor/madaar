-- =============================================================
--  مهاجرت: افزودن سیستم دستاوردها به نصب موجود
--  اگر قبلاً نصب کرده‌اید و می‌خواهید بدون از دست دادن داده،
--  فقط این فایل را در phpMyAdmin روی دیتابیس madar_konkur اجرا کنید.
-- =============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS achievements (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  advisor_id      INT UNSIGNED DEFAULT NULL,
  title           VARCHAR(100) NOT NULL,
  description     VARCHAR(190) DEFAULT NULL,
  icon            VARCHAR(30) NOT NULL DEFAULT 'trophy',
  condition_type  ENUM('tasks_done','streak','manual') NOT NULL DEFAULT 'manual',
  threshold       INT UNSIGNED NOT NULL DEFAULT 0,
  sort_order      INT UNSIGNED NOT NULL DEFAULT 0,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ach_advisor (advisor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_achievements (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id      INT UNSIGNED NOT NULL,
  achievement_id  INT UNSIGNED NOT NULL,
  awarded_by      INT UNSIGNED DEFAULT NULL,
  earned_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_ach (student_id, achievement_id),
  KEY idx_sa_student (student_id),
  CONSTRAINT fk_sa_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_sa_ach FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- دستاوردهای پیش‌فرض (در صورت خالی بودن جدول)
INSERT INTO achievements (advisor_id,title,description,icon,condition_type,threshold,sort_order)
SELECT NULL,'شروع‌کننده','اولین تسکت را انجام دادی','rocket','tasks_done',1,0
WHERE NOT EXISTS (SELECT 1 FROM achievements);
INSERT INTO achievements (advisor_id,title,description,icon,condition_type,threshold,sort_order)
SELECT NULL,'استمرار','۳ روز پیاپی فعالیت','fire','streak',3,1
WHERE (SELECT COUNT(*) FROM achievements) = 1;
INSERT INTO achievements (advisor_id,title,description,icon,condition_type,threshold,sort_order)
SELECT NULL,'جنگجوی هفته','۷ روز استریک','fire','streak',7,2
WHERE (SELECT COUNT(*) FROM achievements) = 2;
INSERT INTO achievements (advisor_id,title,description,icon,condition_type,threshold,sort_order)
SELECT NULL,'نیم‌قرن','۵۰ تسک انجام‌شده','target','tasks_done',50,3
WHERE (SELECT COUNT(*) FROM achievements) = 3;
INSERT INTO achievements (advisor_id,title,description,icon,condition_type,threshold,sort_order)
SELECT NULL,'صدتایی','۱۰۰ تسک انجام‌شده','trophy','tasks_done',100,4
WHERE (SELECT COUNT(*) FROM achievements) = 4;
INSERT INTO achievements (advisor_id,title,description,icon,condition_type,threshold,sort_order)
SELECT NULL,'منتخب مشاور','نشان ویژه از طرف مشاور','sparkles','manual',0,5
WHERE (SELECT COUNT(*) FROM achievements) = 5;
