-- Soft-delete / cancelled questions for exams
SET NAMES utf8mb4;

ALTER TABLE exam_questions
  ADD COLUMN is_cancelled TINYINT(1) NOT NULL DEFAULT 0 AFTER question_number,
  ADD COLUMN cancelled_at DATETIME DEFAULT NULL AFTER is_cancelled;
