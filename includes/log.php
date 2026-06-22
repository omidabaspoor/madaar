<?php
/**
 * مَدار · Madar Study OS — Activity Logging System
 * --------------------------------------------------------
 * سیستم ثبت و مدیریت لاگ فعالیت‌های کاربران و مشاوران
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Log an activity
 */
function log_activity(
    int $userId,
    string $action,
    ?string $targetType = null,
    ?int $targetId = null,
    ?array $details = null
): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = db()->prepare("
            INSERT INTO activity_logs 
            (user_id, action, target_type, target_id, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $targetType,
            $targetId,
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            $ip,
            $ua
        ]);
    } catch (Throwable $e) {
        // Silent fail - logging should never break the app logic
        if (defined('APP_ENV') && APP_ENV === 'development') {
            error_log("Activity log error: " . $e->getMessage());
        }
    }
}

/**
 * Get recent activity logs with powerful advanced filters
 */
function get_recent_logs(int $limit = 200, ?int $advisorId = null, ?int $filterUserId = null, ?string $categoryFilter = null, string $searchQuery = ''): array {
    $sql = "
        SELECT l.*, u.full_name as user_name, u.username as user_username, u.role as user_role, u.field as user_field
        FROM activity_logs l
        LEFT JOIN users u ON u.id = l.user_id
        WHERE 1=1
    ";
    $params = [];

    // Filter by Advisor scope (if not Master Admin)
    if ($advisorId) {
        $sql .= " AND (u.id = ? OR u.advisor_id = ? OR u.id IN (SELECT student_id FROM advisor_student_access WHERE advisor_id = ?))";
        $params[] = $advisorId;
        $params[] = $advisorId;
        $params[] = $advisorId;
    }

    // Filter by specific User
    if ($filterUserId) {
        $sql .= " AND l.user_id = ?";
        $params[] = $filterUserId;
    }

    // Text search in action, details, user name, ip
    if ($searchQuery !== '') {
        $sql .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR l.action LIKE ? OR l.details LIKE ? OR l.ip_address LIKE ?)";
        $sq = "%$searchQuery%";
        $params[] = $sq; $params[] = $sq; $params[] = $sq; $params[] = $sq; $params[] = $sq;
    }

    $sql .= " ORDER BY l.created_at DESC LIMIT ?";
    $params[] = $limit;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // If there is a category filter, filter in PHP
    if ($categoryFilter && $categoryFilter !== 'all') {
        $logs = array_filter($logs, function($l) use ($categoryFilter) {
            $parsed = parse_human_log($l);
            $catKeyMap = [
                'auth'     => 'ورود و خروج',
                'users'    => 'مدیریت کاربران',
                'plans'    => 'برنامه‌ریزی تحصیلی',
                'study'    => 'وضعیت مطالعه',
                'exams'    => 'آزمون و سنجش',
                'messages' => 'پیام‌ها و تعاملات',
                'system'   => 'تنظیمات و سیستم',
                'achieves' => 'دستاوردها'
            ];
            $targetCat = $catKeyMap[$categoryFilter] ?? '';
            return $parsed['category_name'] === $targetCat;
        });
    }

    return $logs;
}

/**
 * Get logs for a specific user
 */
function get_user_logs(int $userId, int $limit = 50): array {
    $stmt = db()->prepare("
        SELECT l.*, u.full_name as user_name, u.role as user_role
        FROM activity_logs l
        LEFT JOIN users u ON u.id = l.user_id
        WHERE l.user_id = ? 
        ORDER BY l.created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

/**
 * Parse an activity log row into fully human-readable Persian UI data (No raw code!)
 */
function parse_human_log(array $log): array
{
    $action = $log['action'] ?? '';
    $detailsJson = $log['details'] ?? null;
    $details = $detailsJson ? json_decode($detailsJson, true) : [];
    if (!is_array($details)) $details = [];

    // Map actions to rich categories, UI styles, and precise Persian descriptions
    $categories = [
        // 1. Auth
        'user_login'             => ['ورود و خروج', 'blue', 'lock', 'ورود موفق به سامانه'],
        'login_failed'           => ['ورود و خروج', 'danger', 'lock', 'تلاش ناموفق برای ورود به سامانه'],
        'user_logout'            => ['ورود و خروج', 'info', 'logout', 'خروج از حساب کاربری'],
        'student_registered'     => ['ورود و خروج', 'sage', 'user-plus', 'ثبت‌نام دانش‌آموز جدید در سیستم'],

        // 2. Users & Advisors
        'advisor_created'        => ['مدیریت کاربران', 'gold', 'users', 'ثبت مشاور جدید در سامانه'],
        'ایجاد مشاور جدید'      => ['مدیریت کاربران', 'gold', 'users', 'ثبت مشاور جدید در سامانه'],
        'advisor_status_changed' => ['مدیریت کاربران', 'warn', 'shield', 'تغییر وضعیت حساب مشاور'],
        'فعال‌سازی حساب مشاور'    => ['مدیریت کاربران', 'sage', 'shield', 'فعال‌سازی حساب کاربری مشاور'],
        'مسدودسازی حساب مشاور'   => ['مدیریت کاربران', 'danger', 'shield', 'مسدودسازی حساب کاربری مشاور'],
        'advisor_access_changed' => ['مدیریت کاربران', 'info', 'shield', 'تغییر سطح دسترسی مشاور'],
        'تغییر سطح دسترسی مشاور' => ['مدیریت کاربران', 'info', 'shield', 'تغییر محدوده نظارت و دسترسی مشاور'],
        'تخصیص دانش‌آموزان به مشاور'=> ['مدیریت کاربران', 'warn', 'users', 'تخصیص دانش‌آموزان اختصاصی به مشاور'],
        'ویرایش مشخصات مشاور'    => ['مدیریت کاربران', 'gold', 'edit', 'ویرایش مشخصات و اطلاعات مشاور'],
        'advisor_deleted'        => ['مدیریت کاربران', 'danger', 'trash', 'حذف مشاور از سامانه'],
        'حذف مشاور از سامانه'     => ['مدیریت کاربران', 'danger', 'trash', 'حذف کامل مشاور از سامانه'],

        'student_created'        => ['مدیریت کاربران', 'sage', 'user-plus', 'ثبت پرونده دانش‌آموز جدید'],
        'student_edited'         => ['مدیریت کاربران', 'gold', 'edit', 'ویرایش مشخصات دانش‌آموز'],
        'student_status_changed' => ['مدیریت کاربران', 'warn', 'shield', 'تغییر وضعیت پرونده دانش‌آموز'],
        'student_password_reset' => ['مدیریت کاربران', 'info', 'key', 'بازنشانی رمز عبور دانش‌آموز'],
        'student_deleted'        => ['مدیریت کاربران', 'danger', 'trash', 'حذف پرونده دانش‌آموز'],

        // 3. Plans
        'plan_created'           => ['برنامه‌ریزی تحصیلی', 'gold', 'calendar', 'طراحی برنامه تحصیلی جدید'],
        'plan_updated'           => ['برنامه‌ریزی تحصیلی', 'gold', 'edit', 'بروزرسانی برنامه تحصیلی دانش‌آموز'],
        'plan_published'         => ['برنامه‌ریزی تحصیلی', 'sage', 'calendar', 'انتشار رسمی برنامه هفتگی'],
        'plan_deleted'           => ['برنامه‌ریزی تحصیلی', 'danger', 'trash', 'حذف برنامه تحصیلی'],
        'plan_pdf_downloaded'    => ['برنامه‌ریزی تحصیلی', 'info', 'download', 'دریافت خروجی PDF برنامه درسی'],

        // 4. Study & Tasks
        'task_status_updated'    => ['وضعیت مطالعه', 'sage', 'check-circle', 'ثبت گزارش عملکرد مطالعه تسک'],
        'mood_recorded'          => ['وضعیت مطالعه', 'warn', 'heart', 'ثبت وضعیت روحی روزانه (Mood)'],

        // 5. Exams
        'exam_created'           => ['آزمون و سنجش', 'warn', 'clipboard', 'طراحی آزمون سنجش جدید'],
        'exam_updated'           => ['آزمون و سنجش', 'warn', 'edit', 'بروزرسانی سوالات و مشخصات آزمون'],
        'exam_published'         => ['آزمون و سنجش', 'sage', 'clipboard', 'انتشار سراسری آزمون آنلاین'],
        'exam_graded'            => ['آزمون و سنجش', 'gold', 'star', 'تصحیح پاسخنامه و ثبت نمره آزمون'],
        'exam_started'           => ['آزمون و سنجش', 'info', 'play', 'آغاز شرکت در آزمون آنلاین'],
        'exam_submitted'         => ['آزمون و سنجش', 'sage', 'check', 'اتمام و ارسال پاسخنامه آزمون'],
        'exam_deleted'           => ['آزمون و سنجش', 'danger', 'trash', 'حذف آزمون از سامانه'],

        // 6. Messages
        'message_sent'           => ['پیام‌ها و تعاملات', 'info', 'message', 'ارسال پیام ارتباطی در سامانه'],
        'announcement_created'   => ['پیام‌ها و تعاملات', 'gold', 'globe', 'انتشار اطلاعیه یا پیام عمومی'],
        'review_scheduled'       => ['پیام‌ها و تعاملات', 'info', 'repeat', 'تنظیم برنامه یادآوری و مرور درسی'],

        // 7. System & Achievements
        'settings_updated'       => ['تنظیمات و سیستم', 'gold', 'settings', 'بروزرسانی پیکربندی مرکزی سامانه'],
        'profile_updated'        => ['تنظیمات و سیستم', 'info', 'user', 'بروزرسانی اطلاعات پروفایل شخصی'],
        'password_changed'       => ['تنظیمات و سیستم', 'gold', 'key', 'تغییر رمز عبور کاربری'],
        'achievement_created'    => ['دستاوردها', 'gold', 'trophy', 'تعریف نشان دستاورد جدید'],
        'achievement_assigned'   => ['دستاوردها', 'sage', 'trophy', 'اعطای نشان افتخار به دانش‌آموز'],
    ];

    if (isset($categories[$action])) {
        [$catName, $catColor, $catIcon, $persianAction] = $categories[$action];
    } else {
        if (preg_match('/[ا-ی]/u', $action)) {
            $catName = 'عملیات سیستمی'; $catColor = 'info'; $catIcon = 'info'; $persianAction = $action;
        } else {
            $catName = 'سایر فعالیت‌ها'; $catColor = 'info'; $catIcon = 'info'; $persianAction = str_replace('_', ' ', $action);
        }
    }

    // Transform raw JSON details into beautiful Native Madar dark chips/badges
    $detailsTags = [];
    if (!empty($details)) {
        foreach ($details as $k => $v) {
            $keyMap = [
                'full_name' => 'نام', 'username' => 'نام کاربری', 'status' => 'وضعیت', 'mode' => 'نوع دسترسی',
                'field' => 'رشته/تخصص', 'grade' => 'پایه', 'title' => 'عنوان', 'score' => 'نمره',
                'week_start' => 'شروع هفته', 'questions_count' => 'تعداد سوالات', 'subject_name' => 'درس',
                'chapter_name' => 'فصل', 'completion_status' => 'وضعیت تسک', 'mood' => 'حال روزانه',
                'recipient_count' => 'تعداد گیرندگان', 'student_name' => 'دانش‌آموز', 'exam_title' => 'عنوان آزمون',
                'score_percent' => 'درصد نمره', 'advisor_name' => 'مشاور', 'plan_title' => 'برنامه'
            ];
            $pk = $keyMap[$k] ?? (is_numeric($k) ? 'مورد' : $k);
            if (is_array($v)) $v = implode('، ', array_map(fn($item) => is_array($item) ? json_encode($item, JSON_UNESCAPED_UNICODE) : (string)$item, $v));
            if (is_bool($v)) $v = $v ? 'بله' : 'خیر';

            $detailsTags[] = sprintf(
                '<span class="badge flex items-center gap-1.5" style="background:var(--surface-3); border-color:var(--border); padding:5px 12px; font-size:.85rem; font-weight:800; color:var(--text); border-radius:10px;"><b>%s:</b> <span style="color:var(--gold-light);">%s</span></span>',
                e((string)$pk),
                e(mb_strimwidth((string)$v, 0, 45, '...'))
            );
        }
    }

    return [
        'category_name'     => $catName,
        'category_color'    => $catColor,
        'category_icon'     => $catIcon,
        'persian_action'    => $persianAction,
        'rich_details_html' => empty($detailsTags) ? '<span class="muted" style="font-size:.85rem; font-style:italic;">بدون جزئیات تکمیلی</span>' : implode(' ', $detailsTags)
    ];
}
