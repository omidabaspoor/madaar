<?php
/** هسته سیستم هماهنگی جلسات مشاوره مَدار - نسخه‌ی ۲ با سیستم پیش‌نویس */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/models.php';
require_once __DIR__ . '/sms.php';

function meetings_schema_ready(): bool {
    static $ok = null; if ($ok !== null) return $ok;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS consultation_sessions (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          advisor_id INT UNSIGNED NOT NULL,
          student_id INT UNSIGNED NOT NULL,
          title VARCHAR(150) NOT NULL,
          session_type ENUM('consultation', 'class') NOT NULL DEFAULT 'consultation',
          draft_group_id VARCHAR(40) DEFAULT NULL,
          session_date DATE NOT NULL,
          session_time TIME NULL,
          notes TEXT NULL,
          status ENUM('draft','scheduled','completed','cancelled') NOT NULL DEFAULT 'draft',
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_session_student (student_id, status, session_date),
          KEY idx_session_advisor (advisor_id, status, session_date),
          KEY idx_session_type (advisor_id, session_type, session_date),
          KEY idx_session_draft_group (advisor_id, draft_group_id, status),
          CONSTRAINT fk_sess_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
          CONSTRAINT fk_sess_advisor FOREIGN KEY (advisor_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // افزودن ستون‌های جدید به نصب‌های قدیمی
        $cols = [];
        foreach (db()->query("SHOW COLUMNS FROM consultation_sessions")->fetchAll() as $c) {
            $cols[$c['Field']] = true;
        }

        if (empty($cols['session_type'])) {
            db()->exec("ALTER TABLE consultation_sessions ADD COLUMN session_type ENUM('consultation', 'class') NOT NULL DEFAULT 'consultation' AFTER title");
        }
        if (empty($cols['draft_group_id'])) {
            db()->exec("ALTER TABLE consultation_sessions ADD COLUMN draft_group_id VARCHAR(40) DEFAULT NULL AFTER session_type");
            db()->exec("CREATE INDEX idx_session_draft_group ON consultation_sessions (advisor_id, draft_group_id, status)");
        }

        // به‌روزرسانی enum برای اضافه کردن 'draft'
        try {
            $colType = db()->query("SHOW COLUMNS FROM consultation_sessions LIKE 'status'")->fetch();
            $currentType = $colType['Type'] ?? '';
            if (strpos($currentType, "'draft'") === false) {
                db()->exec("ALTER TABLE consultation_sessions MODIFY COLUMN status ENUM('draft','scheduled','completed','cancelled') NOT NULL DEFAULT 'draft'");
                db()->exec("UPDATE consultation_sessions SET status='scheduled' WHERE status='draft'");
            }
        } catch (Throwable $e) {}

        return $ok = true;
    } catch (Throwable $e) { return $ok = false; }
}

/* ===================================================================
   دریافت جلسات
   =================================================================== */

function meetings_for_student(int $studentId): array {
    meetings_schema_ready();
    $st = db()->prepare('SELECT s.*, u.full_name advisor_name FROM consultation_sessions s JOIN users u ON u.id=s.advisor_id WHERE s.student_id=? ORDER BY s.session_date ASC, s.session_time ASC');
    $st->execute([$studentId]);
    return $st->fetchAll();
}

function meetings_for_advisor(int $advisorId): array {
    meetings_schema_ready();
    $st = db()->prepare('SELECT s.*, u.full_name student_name, u.field student_field, u.grade student_grade, u.phone student_phone FROM consultation_sessions s JOIN users u ON u.id=s.student_id WHERE s.advisor_id=? ORDER BY s.session_date ASC, s.session_time ASC');
    $st->execute([$advisorId]);
    return $st->fetchAll();
}

/**
 * دریافت گروه‌های پیش‌نویس جلسات یک مشاور
 */
function meetings_drafts_for_advisor(int $advisorId): array {
    meetings_schema_ready();
    $rows = db()->prepare('SELECT s.*, u.full_name student_name, u.phone student_phone
        FROM consultation_sessions s
        JOIN users u ON u.id=s.student_id
        WHERE s.advisor_id=? AND s.status="draft"
        ORDER BY s.created_at DESC, s.session_date ASC, s.session_time ASC')
        ->execute([$advisorId]);

    $rows = db()->prepare('SELECT s.*, u.full_name student_name, u.phone student_phone
        FROM consultation_sessions s
        JOIN users u ON u.id=s.student_id
        WHERE s.advisor_id=? AND s.status="draft"
        ORDER BY s.created_at DESC, s.session_date ASC, s.session_time ASC')
        ->execute([$advisorId]) ? [] : [];

    // دریافت با PDO
    $stmt = db()->prepare('SELECT s.*, u.full_name student_name, u.phone student_phone
        FROM consultation_sessions s
        JOIN users u ON u.id=s.student_id
        WHERE s.advisor_id=? AND s.status="draft"
        ORDER BY s.created_at DESC, s.session_date ASC, s.session_time ASC');
    $stmt->execute([$advisorId]);
    $rows = $stmt->fetchAll();

    // گروه‌بندی بر اساس draft_group_id (برای کلاس) یا id (برای مشاوره)
    $groups = [];
    foreach ($rows as $r) {
        $key = $r['draft_group_id'] ?: ('singleton-' . $r['id']);
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'group_id' => $key,
                'session_type' => $r['session_type'],
                'title' => $r['title'],
                'session_date' => $r['session_date'],
                'session_time' => $r['session_time'],
                'notes' => $r['notes'],
                'draft_group_id' => $r['draft_group_id'],
                'created_at' => $r['created_at'],
                'meeting_ids' => [],
                'students' => [],
                'has_phone_count' => 0,
                'total_students' => 0,
            ];
        }
        $groups[$key]['meeting_ids'][] = (int)$r['id'];
        $groups[$key]['students'][] = [
            'id' => (int)$r['student_id'],
            'name' => $r['student_name'],
            'phone' => $r['student_phone'],
            'meeting_id' => (int)$r['id'],
        ];
        $groups[$key]['total_students']++;
        if (!empty($r['student_phone'])) $groups[$key]['has_phone_count']++;
    }

    return array_values($groups);
}

/* ===================================================================
   ذخیره‌ی پیش‌نویس (بدون ارسال SMS)
   =================================================================== */

/**
 * ذخیره‌ی جلسه به‌صورت پیش‌نویس (بدون ارسال SMS)
 * اگر draft_group_id داده شود، ردیف‌های قبلی آن گروه حذف می‌شوند (برای ویرایش)
 */
function meetings_save_draft(
    int $advisorId,
    int|array $studentIds,
    string $title,
    string $date,
    string $time,
    ?string $notes,
    string $sessionType = 'consultation',
    ?string $existingDraftGroupId = null
): array {
    meetings_schema_ready();

    $studentIds = is_array($studentIds) ? $studentIds : [$studentIds];
    $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds), fn($v) => $v > 0)));

    $sessionType = ($sessionType === 'class') ? 'class' : 'consultation';
    $cleanTitle = trim($title) ?: ($sessionType === 'class' ? 'کلاس درسی' : 'جلسه مشاوره');
    $cleanNotes = !empty($notes) ? trim($notes) : null;

    // ساخت یا حفظ draft_group_id
    if ($existingDraftGroupId) {
        $draftGroupId = $existingDraftGroupId;
        // حذف ردیف‌های قبلی این گروه
        db()->prepare('DELETE FROM consultation_sessions WHERE advisor_id=? AND draft_group_id=? AND status="draft"')
            ->execute([$advisorId, $draftGroupId]);
    } else {
        $draftGroupId = bin2hex(random_bytes(8));
    }

    $insertedIds = [];
    foreach ($studentIds as $studentId) {
        $student = get_user($studentId);
        if (!$student || $student['role'] !== 'student') continue;

        db()->prepare('INSERT INTO consultation_sessions
            (advisor_id, student_id, title, session_type, draft_group_id, session_date, session_time, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, "draft")')
            ->execute([
                $advisorId, $studentId, $cleanTitle, $sessionType, $draftGroupId,
                $date, $time, $cleanNotes
            ]);
        $insertedIds[] = (int)db()->lastInsertId();
    }

    return [
        'draft_group_id' => $draftGroupId,
        'meeting_ids' => $insertedIds,
        'student_count' => count($insertedIds),
        'title' => $cleanTitle,
        'session_type' => $sessionType,
        'session_date' => $date,
        'session_time' => $time,
        'notes' => $cleanNotes,
    ];
}

/**
 * دریافت اطلاعات یک گروه پیش‌نویس برای ویرایش
 */
function meetings_get_draft_for_edit(int $advisorId, string $draftGroupId): ?array {
    meetings_schema_ready();
    $stmt = db()->prepare('SELECT s.*, u.full_name student_name, u.phone student_phone
        FROM consultation_sessions s
        JOIN users u ON u.id=s.student_id
        WHERE s.advisor_id=? AND s.draft_group_id=? AND s.status="draft"
        ORDER BY s.id ASC');
    $stmt->execute([$advisorId, $draftGroupId]);
    $rows = $stmt->fetchAll();
    if (!$rows) return null;

    return [
        'draft_group_id' => $draftGroupId,
        'session_type' => $rows[0]['session_type'],
        'title' => $rows[0]['title'],
        'session_date' => $rows[0]['session_date'],
        'session_time' => $rows[0]['session_time'],
        'notes' => $rows[0]['notes'],
        'students' => array_map(fn($r) => [
            'id' => (int)$r['student_id'],
            'name' => $r['student_name'],
        ], $rows),
    ];
}

/**
 * حذف یک گروه پیش‌نویس
 */
function meetings_delete_draft(int $advisorId, string $draftGroupId): bool {
    meetings_schema_ready();
    $stmt = db()->prepare('DELETE FROM consultation_sessions WHERE advisor_id=? AND draft_group_id=? AND status="draft"');
    $stmt->execute([$advisorId, $draftGroupId]);
    return $stmt->rowCount() > 0;
}

/* ===================================================================
   تایید نهایی + ارسال SMS
   =================================================================== */

/**
 * تایید نهایی یک گروه پیش‌نویس + ارسال SMS به همه
 */
function meetings_confirm(int $advisorId, string $draftGroupId): array {
    meetings_schema_ready();

    // دریافت ردیف‌های پیش‌نویس
    $stmt = db()->prepare('SELECT s.*, u.full_name student_name, u.phone student_phone, u.id student_id_check
        FROM consultation_sessions s
        JOIN users u ON u.id=s.student_id
        WHERE s.advisor_id=? AND s.draft_group_id=? AND s.status="draft"');
    $stmt->execute([$advisorId, $draftGroupId]);
    $rows = $stmt->fetchAll();

    if (!$rows) {
        return ['ok' => false, 'error' => 'پیش‌نویس یافت نشد'];
    }

    $sessionType = $rows[0]['session_type'];
    $title = $rows[0]['title'];
    $date = $rows[0]['session_date'];
    $time = $rows[0]['session_time'];
    $advName = db()->query('SELECT full_name FROM users WHERE id=' . (int)$advisorId)->fetchColumn() ?: 'مشاور شما';

    // به‌روزرسانی به scheduled
    db()->prepare('UPDATE consultation_sessions SET status="scheduled" WHERE advisor_id=? AND draft_group_id=? AND status="draft"')
        ->execute([$advisorId, $draftGroupId]);

    // ارسال اعلان + SMS به هر دانش‌آموز
    $results = ['ok' => true, 'sent' => 0, 'failed' => 0, 'no_phone' => 0, 'details' => []];

    foreach ($rows as $r) {
        $studentId = (int)$r['student_id'];

        // اعلان درون‌برنامه‌ای
        $timeText = $time ? ' ساعت ' . fa_num(substr((string)$time, 0, 5)) : ' (ساعت توافقی)';
        $typeLabel = $sessionType === 'class' ? 'کلاس درسی' : 'جلسه مشاوره';
        $body = ($sessionType === 'class' ? 'یک کلاس درسی' : 'یک جلسه مشاوره') . ' با عنوان «' . $title . '» برای تاریخ ' . jalali_date($date) . $timeText . ' توسط ' . $advName . ' تنظیم شد.';
        notify($studentId, '📅 ' . $typeLabel . ' جدید برنامه‌ریزی شد', $body, 'calendar', 'student/meetings.php');

        // ارسال پیامک
        $smsStatus = 'no_phone';
        $smsError = null;
        if (!empty($r['student_phone'])) {
            $message = sms_build_meeting_message($title, $date, $time, $sessionType);
            $smsResult = sms_send(
                $r['student_phone'],
                $message,
                $sessionType === 'class' ? 'meeting_class' : 'meeting_consultation',
                (int)$r['id'],
                $studentId
            );
            $smsStatus = $smsResult['status'];
            $smsError = $smsResult['error'];
            if ($smsStatus === 'sent') $results['sent']++;
            elseif ($smsStatus === 'failed') $results['failed']++;
        } else {
            $results['no_phone']++;
        }

        $results['details'][] = [
            'student_id' => $studentId,
            'student_name' => $r['student_name'],
            'sms_status' => $smsStatus,
            'sms_error' => $smsError,
        ];
    }

    return $results;
}

/* ===================================================================
   لغو جلسه
   =================================================================== */

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
    $typeLabel = ($meeting['session_type'] ?? 'consultation') === 'class' ? 'کلاس درسی' : 'جلسه مشاوره';

    if ($role === 'advisor') {
        notify((int)$meeting['student_id'], '❌ لغو ' . $typeLabel, $typeLabel . ' شما با عنوان «' . $meeting['title'] . '» برای تاریخ ' . $dateFormatted . $timeFormatted . ' توسط مشاور لغو گردید.', 'calendar', 'student/meetings.php');
    } else {
        $studentName = db()->query('SELECT full_name FROM users WHERE id='.(int)$meeting['student_id'])->fetchColumn() ?: 'دانش‌آموز';
        notify((int)$meeting['advisor_id'], '❌ انصراف دانش‌آموز از ' . $typeLabel, 'دانش‌آموز ' . $studentName . ' ' . $typeLabel . ' خود را برای تاریخ ' . $dateFormatted . $timeFormatted . ' لغو کرد.', 'calendar', 'admin/student_reports.php');
    }
    return true;
}

/* ===================================================================
   توابع کمکی برای UI
   =================================================================== */

/**
 * ساخت متن پیامک برای پیش‌نمایش (بدون ارسال واقعی)
 */
function meetings_preview_sms(string $title, string $date, string $time, string $sessionType = 'consultation'): string {
    return sms_build_meeting_message($title ?: ($sessionType === 'class' ? 'کلاس درسی' : 'جلسه مشاوره'), $date, $time, $sessionType);
}

/**
 * بررسی اینکه آیا یک جلسه قابل ویرایش است (فقط draft)
 */
function meetings_is_editable(array $meeting): bool {
    return ($meeting['status'] ?? '') === 'draft';
}
