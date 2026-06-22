-- Upgrade: three-state task status + course percent + feeling
ALTER TABLE tasks ADD COLUMN completion_status ENUM('pending','full','partial','missed') NOT NULL DEFAULT 'pending' AFTER is_done;
ALTER TABLE tasks ADD COLUMN course_percent TINYINT UNSIGNED DEFAULT NULL AFTER completion_status;
ALTER TABLE tasks ADD COLUMN student_feeling VARCHAR(30) DEFAULT NULL AFTER course_percent;
ALTER TABLE tasks ADD COLUMN status_updated_at DATETIME DEFAULT NULL AFTER completed_at;
UPDATE tasks SET completion_status=IF(is_done=1,'full','pending') WHERE completion_status='pending';
