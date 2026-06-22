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
