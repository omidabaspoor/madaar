-- Upgrade: daily mood date
ALTER TABLE users ADD COLUMN mood_date DATE DEFAULT NULL AFTER mood;
UPDATE users SET mood_date=CURDATE() WHERE mood IS NOT NULL AND mood_date IS NULL;
