<?php
/** هسته سیستم هماهنگی جلسات مشاوره مَدار */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/models.php';

function meetings_schema_ready(): bool {
    static $ok = null; if ($ok !== null) return $ok;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS consultation_sessions (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          advisor_id INT UNSIGNED NOT NULL,
          student_id INT UNSIGNED NOT NULL,
          title VARCHAR(150) NOT NULL,
          session_date DATE NOT NULL,
          session_time TIME NULL,
          notes TEXT NULL,
          status ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_session_student (student_id, status, session_date),
          KEY idx_session_advisor (advisor_id, status, session_date),
          CONSTRAINT fk_sess_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
          CONSTRAINT fk_sess_advisor FOREIGN KEY (advisor_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Ensure session_time allows NULL dynamically
        try {
            db()->exec("ALTER TABLE consultation_sessions MODIFY COLUMN session_time TIME NULL");
        } catch (Throwable $e) {}
        
        return $ok = true;
    } catch (Throwable $e) { return $ok = false; }
}

function meetings_for_student(int $studentId): array {
    meetings_schema_ready();
    $st = db()->prepare('SELECT s.*, u.full_name advisor_name FROM consultation_sessions s JOIN users u ON u.id=s.advisor_id WHERE s.student_id=? ORDER BY s.session_date ASC, s.session_time ASC');
    $st->execute([$studentId]);
    return $st->fetchAll();
}

function meetings_for_advisor(int $advisorId): array {
    meetings_schema_ready();
    $st = db()->prepare('SELECT s.*, u.full_name student_name, u.field student_field, u.grade student_grade FROM consultation_sessions s JOIN users u ON u.id=s.student_id WHERE s.advisor_id=? ORDER BY s.session_date ASC, s.session_time ASC');
    $st->execute([$advisorId]);
    return $st->fetchAll();
}

function meetings_save(int $advisorId, int $studentId, string $title, string $date, ?string $time, ?string $notes): int {
    meetings_schema_ready();
    $student = get_user($studentId);
    if (!$student || $student['role'] !== 'student') throw new RuntimeException('دانش‌آموز معتبر نیست.');
    
    $clean_time = !empty($time) ? trim($time) : null;
    
    db()->prepare('INSERT INTO consultation_sessions (advisor_id, student_id, title, session_date, session_time, notes, status) VALUES (?, ?, ?, ?, ?, ?, "scheduled")')
      ->execute([$advisorId, $studentId, trim($title) ?: 'جلسه مشاوره اختصاصی', $date, $clean_time, $notes ? trim($notes) : null]);
    $meetingId = (int)db()->lastInsertId();
    
    // Notify student
    $advName = db()->query('SELECT full_name FROM users WHERE id='.(int)$advisorId)->fetchColumn() ?: 'مشاور شما';
    $timeText = $clean_time ? ' ساعت ' . fa_num(substr($clean_time, 0, 5)) : ' (ساعت توافقی)';
    notify($studentId, '📅 جلسه مشاوره جدید برنامه‌ریزی شد', 'یک جلسه مشاوره جدید با عنوان «' . $title . '» برای تاریخ ' . jalali_date($date) . $timeText . ' توسط ' . $advName . ' تنظیم شد.', 'calendar', 'student/meetings.php');
    
    return $meetingId;
}

function meetings_cancel(int $meetingId, int $userId, string $role): bool {
    meetings_schema_ready();
    $st = db()->prepare('SELECT * FROM consultation_sessions WHERE id=? LIMIT 1');
    $st->execute([$meetingId]);
    $meeting = $st->fetch();
    if (!$meeting) return false;
    
    if ($role === 'student' && (int)$meeting['student_id'] !== $userId) return false;
    if ($role === 'advisor' && (int)$meeting['advisor_id'] !== $userId) return false;
    
    db()->prepare('UPDATE consultation_sessions SET status="cancelled" WHERE id=?')->execute([$meetingId]);
    
    $dateFormatted = jalali_date($meeting['session_date']);
    $clean_time = $meeting['session_time'];
    $timeFormatted = $clean_time ? ' ساعت ' . fa_num(substr((string)$clean_time, 0, 5)) : ' (ساعت توافقی)';
    
    if ($role === 'advisor') {
        notify((int)$meeting['student_id'], '❌ لغو جلسه مشاوره', 'جلسه مشاوره شما با عنوان «' . $meeting['title'] . '» برای تاریخ ' . $dateFormatted . $timeFormatted . ' توسط مشاور لغو گردید.', 'calendar', 'student/meetings.php');
    } else {
        $studentName = db()->query('SELECT full_name FROM users WHERE id='.(int)$meeting['student_id'])->fetchColumn() ?: 'دانش‌آموز';
        notify((int)$meeting['advisor_id'], '❌ انصراف دانش‌آموز از جلسه', 'دانش‌آموز ' . $studentName . ' جلسه مشاوره خود را برای تاریخ ' . $dateFormatted . $timeFormatted . ' لغو کرد.', 'calendar', 'admin/student_reports.php');
    }
    return true;
}
