-- =============================================================
--  مَدار · Madar — Chapter Management Upgrade
--  Upgrade script to add chapters table for curriculum management
-- =============================================================

CREATE TABLE IF NOT EXISTS chapters (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_name  VARCHAR(80) NOT NULL,           -- normalized subject key (e.g., 'ریاضی', 'شیمی')
  grade         INT UNSIGNED NOT NULL,             -- 10, 11, 12
  field         VARCHAR(30) NOT NULL,              -- 'tajrobi', 'riazi', 'omumi'
  book_name     VARCHAR(120) NOT NULL,             -- e.g., 'ریاضی (۱)', 'حسابان (۱)'
  chapter_name  VARCHAR(200) NOT NULL,             -- actual chapter/lesson name
  sort_order    INT UNSIGNED NOT NULL DEFAULT 0,
  is_system     TINYINT(1) NOT NULL DEFAULT 1,     -- 1 = system default, 0 = custom
  advisor_id    INT UNSIGNED DEFAULT NULL,        -- owner if custom
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_chap_subject (subject_name, grade, field, is_active),
  KEY idx_chap_advisor (advisor_id),
  KEY idx_chap_book (book_name, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
