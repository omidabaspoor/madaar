-- =============================================================
--  مهاجرت: تنظیمات پیش‌فرض برنامه‌ریز + حافظه‌ی هوشمند انتخاب‌ها
--  در phpMyAdmin روی دیتابیس madar_konkur اجرا کنید (یک‌بار).
--  این مهاجرت دو جدول می‌سازد:
--    1) advisor_settings : پیش‌فرض‌های قابل‌تنظیم هر مشاور
--    2) planner_memory   : یادگیری آخرین انتخاب‌های مشاور برای پرکردن خودکار
-- =============================================================
SET NAMES utf8mb4;

-- ----------------------------------------------------------
--  advisor_settings : key/value پیکربندی هر مشاور
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS advisor_settings (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  advisor_id  INT UNSIGNED NOT NULL,
  skey        VARCHAR(60) NOT NULL,
  svalue      VARCHAR(255) DEFAULT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_advisor_key (advisor_id, skey),
  KEY idx_setting_advisor (advisor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
--  planner_memory : آخرین انتخاب‌های مشاور برای پرکردن خودکار هوشمند
--  scope: global | unit | subject  → کلیدِ context در ctx_key
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS planner_memory (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  advisor_id  INT UNSIGNED NOT NULL,
  scope       VARCHAR(20) NOT NULL DEFAULT 'global',  -- global | unit | subject
  ctx_key     VARCHAR(60) NOT NULL DEFAULT '*',        -- مثلا شماره‌ی واحد یا شناسه‌ی درس
  task_type   VARCHAR(20) DEFAULT NULL,
  subject_id  INT UNSIGNED DEFAULT NULL,
  target_count INT DEFAULT NULL,
  target_unit VARCHAR(20) DEFAULT NULL,
  duration_min INT DEFAULT NULL,
  priority    VARCHAR(10) DEFAULT NULL,
  source      VARCHAR(120) DEFAULT NULL,
  hits        INT UNSIGNED NOT NULL DEFAULT 1,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mem (advisor_id, scope, ctx_key),
  KEY idx_mem_advisor (advisor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
