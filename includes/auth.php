<?php
/** نشست، احراز هویت، CSRF و کنترل دسترسی */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function boot_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name(SESSION_NAME);
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
        || (str_contains(strtolower($_SERVER['HTTP_CF_VISITOR'] ?? ''), 'https'));
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $secure,
        'samesite' => 'Lax',
    ]);
    session_start();
    if (!isset($_SESSION['_init'])) {
        session_regenerate_id(true);
        $_SESSION['_init'] = time();
    }
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}
function csrf_field(): string
{
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . e(csrf_token()) . '">';
}
function verify_csrf(): bool
{
    $sent = $_POST[CSRF_TOKEN_NAME] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $sent);
}
function require_csrf(): void
{
    if (!verify_csrf()) {
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            json_out(['ok' => false, 'error' => 'نشست شما منقضی شده است. صفحه را تازه کنید.'], 419);
        }
        http_response_code(419);
        die('درخواست نامعتبر (CSRF).');
    }
}

/* ---------- کاربر جاری ---------- */
function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    boot_session();
    if (empty($_SESSION['uid'])) return $cache = null;
    $st = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    return $cache = ($u ?: null);
}
function is_logged_in(): bool { return current_user() !== null; }
function user_role(): ?string { return current_user()['role'] ?? null; }


/* ---------- دسترسی صفحه‌های پنل مشاور ---------- */
function advisor_page_catalog(): array
{
    return [
        'dashboard'       => ['label'=>'داشبورد', 'icon'=>'home', 'path'=>'admin/dashboard.php'],
        'students'        => ['label'=>'دانش‌آموزان', 'icon'=>'users', 'path'=>'admin/students.php'],
        'plans'           => ['label'=>'برنامه‌ها و برنامه‌ریز', 'icon'=>'calendar', 'path'=>'admin/plans.php', 'aliases'=>['admin/plan_builder.php']],
        'student_reports' => ['label'=>'گزارش حرفه‌ای', 'icon'=>'edit', 'path'=>'admin/student_reports.php', 'aliases'=>['admin/student_report_pdf.php']],
        'reports'         => ['label'=>'گزارش عملکرد', 'icon'=>'chart', 'path'=>'admin/reports.php'],
        'reviews'         => ['label'=>'مرورهای دانش‌آموزان', 'icon'=>'repeat', 'path'=>'admin/reviews.php'],
        'mock_exam'       => ['label'=>'آزمون‌های آزمایشی', 'icon'=>'target', 'path'=>'admin/mock_exam_reports.php'],
        'exams'           => ['label'=>'آزمون‌ساز', 'icon'=>'clipboard', 'path'=>'admin/exams.php', 'aliases'=>['admin/exam_builder.php','admin/exam_results.php']],
        'internal_exam'   => ['label'=>'تحلیل آزمون', 'icon'=>'chart', 'path'=>'admin/internal_exam_reports.php'],
        'meetings'        => ['label'=>'جلسات', 'icon'=>'calendar', 'path'=>'admin/schedule_meeting.php'],
        'online_sessions' => ['label'=>'جلسات آنلاین', 'icon'=>'video', 'path'=>'admin/online_sessions.php'],
        'messages'        => ['label'=>'پیام‌ها', 'icon'=>'message', 'path'=>'admin/messages.php'],
        'achievements'    => ['label'=>'دستاوردها', 'icon'=>'trophy', 'path'=>'admin/achievements.php'],
        'guide'           => ['label'=>'راهنما', 'icon'=>'book', 'path'=>'admin/guide.php'],
        'settings'        => ['label'=>'تنظیمات', 'icon'=>'settings', 'path'=>'admin/settings.php'],
    ];
}

function advisor_page_access_schema_ready(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS advisor_page_access (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            advisor_id INT UNSIGNED NOT NULL,
            page_key VARCHAR(60) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_advisor_page (advisor_id, page_key),
            KEY idx_apa_advisor (advisor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return $ok = true;
    } catch (Throwable $e) { return $ok = false; }
}

function advisor_allowed_page_keys(int $advisorId): array
{
    if (!$advisorId || !advisor_page_access_schema_ready()) return [];
    try {
        $st = db()->prepare('SELECT page_key FROM advisor_page_access WHERE advisor_id=?');
        $st->execute([$advisorId]);
        return array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) { return []; }
}

function advisor_has_custom_page_access(int $advisorId): bool
{
    if (!$advisorId || !advisor_page_access_schema_ready()) return false;
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM advisor_page_access WHERE advisor_id=?');
        $st->execute([$advisorId]);
        return (int)$st->fetchColumn() > 0;
    } catch (Throwable $e) { return false; }
}

function advisor_can_access_page(int $advisorId, string $pageKey): bool
{
    if (!$advisorId) return false;
    if (!advisor_has_custom_page_access($advisorId)) return true; // سازگاری با مشاورهای قبلی: بدون تنظیم یعنی همه صفحات
    return in_array($pageKey, advisor_allowed_page_keys($advisorId), true);
}

function advisor_first_allowed_admin_path(int $advisorId): string
{
    foreach (advisor_page_catalog() as $key => $page) {
        if (advisor_can_access_page($advisorId, $key)) return $page['path'];
    }
    return '403.php';
}

function advisor_page_key_for_path(string $path): ?string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    foreach (advisor_page_catalog() as $key => $p) {
        $paths = array_merge([$p['path']], $p['aliases'] ?? []);
        foreach ($paths as $candidate) {
            if ($path === ltrim($candidate, '/')) return $key;
        }
    }
    return null;
}

function require_advisor_page_access(): void
{
    $u = current_user();
    if (!$u || ($u['role'] ?? '') !== 'advisor') return;
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $pos = strpos($script, '/admin/');
    if ($pos === false) return;
    $path = ltrim(substr($script, $pos + 1), '/');
    $key = advisor_page_key_for_path($path);
    if ($key && !advisor_can_access_page((int)$u['id'], $key)) {
        http_response_code(403);
        require __DIR__ . '/../403.php';
        exit;
    }
}

function login_user(array $user, bool $remember = false): void
{
    boot_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$user['id'];
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $st = db()->prepare('UPDATE users SET remember_token = ? WHERE id = ?');
        $st->execute([hash('sha256', $token), $user['id']]);
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
            || (str_contains(strtolower($_SERVER['HTTP_CF_VISITOR'] ?? ''), 'https'));
        setcookie('madar_remember', $user['id'] . ':' . $token, [
            'expires' => time() + 60 * 60 * 24 * 30, 'path' => '/', 'httponly' => true, 'secure' => $secure, 'samesite' => 'Lax',
        ]);
    }
}
function logout_user(): void
{
    boot_session();
    if (!empty($_SESSION['uid'])) {
        db()->prepare('UPDATE users SET remember_token = NULL WHERE id = ?')->execute([$_SESSION['uid']]);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    setcookie('madar_remember', '', time() - 42000, '/');
    session_destroy();
}

/* ---------- محافظت صفحات ---------- */
function require_login(): void
{
    if (!is_logged_in()) redirect('auth/login.php');
}
function require_role(string ...$roles): void
{
    require_login();
    $r = user_role();
    if (!in_array($r, $roles, true)) {
        http_response_code(403);
        require __DIR__ . '/../403.php';
        exit;
    }
    // دانش‌آموزِ هنوز تاییدنشده
    if ($r === 'student' && (current_user()['status'] ?? '') === 'pending') {
        redirect('auth/pending.php');
    }
    require_advisor_page_access();
}

function is_chief_advisor(?array $user = null): bool
{
    $u = $user ?? current_user();
    if (!$u) return false;
    return ($u['role'] ?? '') === 'admin';
}

function require_chief_advisor(): void
{
    require_login();
    if (!is_chief_advisor()) {
        http_response_code(403);
        require __DIR__ . '/../403.php';
        exit;
    }
}

/* ---------- اعلان ---------- */
function notify(int $userId, string $title, string $body = '', string $type = 'info', string $link = ''): void
{
    $st = db()->prepare('INSERT INTO notifications (user_id,title,body,type,link) VALUES (?,?,?,?,?)');
    $st->execute([$userId, $title, $body, $type, $link]);

    // اگر Web Push با VAPID فعال شده باشد، همان لحظه به مرورگرهای Subscribe‌شده هم ارسال می‌شود.
    try {
        require_once __DIR__ . '/web_push.php';
        web_push_send_to_user($userId, [
            'title' => $title,
            'body'  => $body,
            'type'  => $type,
            'url'   => $link ?: 'student/dashboard.php',
        ]);
    } catch (Throwable $e) {}
}
function unread_notif_count(int $userId): int
{
    $st = db()->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0');
    $st->execute([$userId]);
    return (int)$st->fetchColumn();
}
function unread_msg_count(int $userId): int
{
    $st = db()->prepare('SELECT COUNT(*) c FROM messages WHERE receiver_id=? AND is_read=0');
    $st->execute([$userId]);
    return (int)$st->fetchColumn();
}
