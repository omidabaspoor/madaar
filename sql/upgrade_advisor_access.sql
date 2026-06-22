-- Add access control for advisors
ALTER TABLE users 
ADD COLUMN access_mode ENUM('all','restricted') NOT NULL DEFAULT 'all' 
AFTER status;

-- Create advisor-student permission table (for restricted mode)
CREATE TABLE IF NOT EXISTS advisor_student_access (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    advisor_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_access (advisor_id, student_id),
    CONSTRAINT fk_asa_advisor FOREIGN KEY (advisor_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_asa_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
