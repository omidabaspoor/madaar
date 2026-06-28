<?php
/**
 * API اتاق جلسه آنلاین — نسخه‌ی ۳ با Robust Error Handling
 * سازگار با PHP 7+
 */

// خطایابی - ثبت همه خطاها
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/online_sessions.php';
require_once __DIR__ . '/../includes/helpers.php';

// شروع session
if (session_status() !== PHP_SESSION_ACTIVE) {
    boot_session();
}

// بررسی لاگین - اگر نبود، خروج
$u = current_user();
if (!$u) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'لطفاً وارد شوید', 'code' => 'not_logged_in'], JSON_UNESCAPED_UNICODE);
    exit;
}

$me = (int)$u['id'];
$role = $u['role'];

// دریافت action و body
$action = isset($_GET['action']) ? (string)$_GET['action'] : '';
$rawInput = file_get_contents('php://input');
$in = $rawInput ? (json_decode($rawInput, true) ?: []) : [];
$body = array_merge($_POST, $in);

// CSRF: روی هاست اشتراکی و XAMPP هم با همان session کار می‌کند.
// فقط endpointهای خواندنی بدون CSRF مجازند؛ تغییرات کلاس حتماً توکن لازم دارند.
$csrfOk = false;
$csrfToken = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string)$_SERVER['HTTP_X_CSRF_TOKEN'] : '';
if ($csrfToken && isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $csrfToken)) {
    $csrfOk = true;
}
$readOnlyActions = ['chat_list','hand_list','whiteboard_load','reactions_list','permissions_state','permission_status','session_state','permission_list'];
if (!$csrfOk && !in_array($action, $readOnlyActions, true)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(419);
    echo json_encode(['ok' => false, 'error' => 'توکن CSRF نامعتبر', 'code' => 'csrf_invalid'], JSON_UNESCAPED_UNICODE);
    exit;
}

// اطمینان از وجود جداول
try {
    online_sessions_schema_ready();
} catch (Throwable $e) {
    error_log('API online_room: schema failed: ' . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'جداول جلسات ساخته نشده‌اند. install.php را اجرا کنید.', 'code' => 'schema_missing'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

/**
 * Helper برای خروجی JSON
 */
function out($d, $code = 200) {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}


// permission schema is defined in includes/online_sessions.php. Keep this guard for old installs.
if (!function_exists('online_room_permission_schema')) {
function online_room_permission_schema(): void {
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS session_permission_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            user_name VARCHAR(120) NOT NULL,
            permission_type VARCHAR(20) NOT NULL,
            status ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            decided_at DATETIME NULL,
            decided_by INT UNSIGNED NULL,
            PRIMARY KEY (id),
            KEY idx_perm_session (session_id, status, created_at),
            KEY idx_perm_user (session_id, user_id, permission_type, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) { error_log('permission schema failed: '.$e->getMessage()); }
}
}

function online_room_api_can_access(array $session, string $role, int $me): bool {
    if ($role === 'admin') return true;
    if ($role === 'advisor') return (int)($session['advisor_id'] ?? 0) === $me;
    if ($role === 'student') return online_session_student_can_access((int)$session['id'], $me);
    return false;
}

try {
switch ($action) {

// ============================================
// CHAT
// ============================================
case 'chat_send': {
    $sessionId = (int)($body['session_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>false, 'error'=>'جلسه یافت نشد', 'code'=>'not_found'], 404);

    if (!online_room_api_can_access($session, $role, $me)) {
        out(['ok'=>false, 'error'=>'دسترسی ندارید', 'code'=>'forbidden'], 403);
    }
    // چت کلاس باید همیشه برای اعضای مجاز کلاس کار کند؛ اجازه‌گیری فقط برای صدا/تصویر/اسکرین/تخته است.
    if (in_array(($session['status'] ?? ''), ['ended','cancelled'], true)) {
        out(['ok'=>false, 'error'=>'کلاس پایان یافته است', 'code'=>'session_ended'], 403);
    }

    $message = trim((string)($body['message'] ?? ''));
    if (!$message) out(['ok'=>false, 'error'=>'پیام خالی است', 'code'=>'empty_message'], 422);
    if (mb_strlen($message) > 500) $message = mb_substr($message, 0, 500);

    $type = in_array($body['message_type'] ?? 'text', ['text','emoji','system','file'], true) ? $body['message_type'] : 'text';
    $id = session_chat_send($sessionId, $me, $u['full_name'], $role, $message, $type);
    if (!$id) out(['ok'=>false, 'error'=>'ثبت پیام در دیتابیس انجام نشد. install.php?update=1 را اجرا کنید.', 'code'=>'chat_insert_failed'], 500);
    out(['ok'=>true, 'id'=>$id]);
}

case 'chat_list': {
    $sessionId = (int)($body['session_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>true, 'messages'=>[], 'note'=>'جلسه یافت نشد']);

    if (!online_room_api_can_access($session, $role, $me)) {
        out(['ok'=>true, 'messages'=>[], 'note'=>'دسترسی ندارید']);
    }

    $afterId = isset($body['after_id']) ? (int)$body['after_id'] : null;
    $messages = session_chat_list($sessionId, $afterId, 50);
    out(['ok'=>true, 'messages'=>$messages ?: []]);
}

// ============================================
// WHITEBOARD
// ============================================
case 'whiteboard_save': {
    $sessionId = (int)($body['session_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>false, 'error'=>'جلسه یافت نشد', 'code'=>'not_found'], 404);

    if (!online_room_api_can_access($session, $role, $me)) {
        out(['ok'=>false, 'error'=>'دسترسی ندارید', 'code'=>'forbidden'], 403);
    }
    if (!online_permission_user_allowed($session, $me, $role, 'whiteboard')) {
        out(['ok'=>false, 'error'=>'برای استفاده از تخته باید از مشاور اجازه بگیرید', 'code'=>'wb_disabled'], 403);
    }
    if (($session['status'] ?? '') !== 'live') {
        out(['ok'=>false, 'error'=>'کلاس هنوز فعال نیست', 'code'=>'session_not_live'], 403);
    }

    $snapshot = trim((string)($body['snapshot'] ?? ''));
    if (!$snapshot) out(['ok'=>false, 'error'=>'snapshot خالی است', 'code'=>'empty_snapshot'], 422);
    if (strlen($snapshot) > 15 * 1024 * 1024) out(['ok'=>false, 'error'=>'snapshot خیلی بزرگ است', 'code'=>'too_large'], 413);

    $vid = whiteboard_save($sessionId, $me, $snapshot);
    out(['ok'=>true, 'version'=>$vid]);
}

case 'whiteboard_load': {
    $sessionId = (int)($body['session_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>true, 'snapshot'=>null]);

    if (!online_room_api_can_access($session, $role, $me)) {
        out(['ok'=>true, 'snapshot'=>null]);
    }

    $latest = whiteboard_load_latest($sessionId);
    out(['ok'=>true, 'snapshot'=>$latest ? $latest['snapshot_json'] : null, 'version'=>$latest ? $latest['version'] : 0]);
}

// ============================================
// HAND RAISE
// ============================================
case 'hand_toggle': {
    $sessionId = (int)($body['session_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>false, 'error'=>'جلسه یافت نشد', 'code'=>'not_found'], 404);

    if (!online_room_api_can_access($session, $role, $me)) {
        out(['ok'=>false, 'error'=>'دسترسی ندارید', 'code'=>'forbidden'], 403);
    }
    if ($session['status'] !== 'live') {
        out(['ok'=>false, 'error'=>'جلسه فعال نیست', 'code'=>'session_not_live'], 403);
    }

    $raised = session_hand_toggle($sessionId, $me, $u['full_name'], $role);
    out(['ok'=>true, 'raised'=>$raised]);
}

case 'hand_list': {
    $sessionId = (int)($body['session_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>true, 'hands'=>[]]);

    if ($session['advisor_id'] != $me && $role !== 'admin') {
        out(['ok'=>true, 'hands'=>[]]);
    }

    $hands = session_hand_raised_list($sessionId);
    out(['ok'=>true, 'hands'=>$hands ?: []]);
}

case 'hand_ack': {
    $sessionId = (int)($body['session_id'] ?? 0);
    $userId = (int)($body['user_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>false, 'error'=>'جلسه یافت نشد', 'code'=>'not_found'], 404);

    if ($session['advisor_id'] != $me && $role !== 'admin') {
        out(['ok'=>false, 'error'=>'دسترسی ندارید', 'code'=>'forbidden'], 403);
    }
    session_hand_acknowledge($sessionId, $userId);
    out(['ok'=>true]);
}

// ============================================
// REACTIONS
// ============================================
case 'reaction_send': {
    $sessionId = (int)($body['session_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>false, 'error'=>'جلسه یافت نشد', 'code'=>'not_found'], 404);

    if (!online_room_api_can_access($session, $role, $me)) {
        out(['ok'=>false, 'error'=>'دسترسی ندارید', 'code'=>'forbidden'], 403);
    }
    if ($session['status'] !== 'live') {
        out(['ok'=>false, 'error'=>'جلسه فعال نیست', 'code'=>'session_not_live'], 403);
    }

    $type = trim((string)($body['reaction_type'] ?? 'clap'));
    session_reaction_send($sessionId, $me, $u['full_name'], $type);
    out(['ok'=>true]);
}


case 'reactions_list': {
    $sessionId = (int)($body['session_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>true, 'reactions'=>[]]);

    if (!online_room_api_can_access($session, $role, $me)) {
        out(['ok'=>true, 'reactions'=>[]]);
    }

    $reactions = session_reactions_recent($sessionId, 2);
    out(['ok'=>true, 'reactions'=>$reactions ?: []]);
}


// ============================================
// CLASS MANAGEMENT / PERMISSIONS
// ============================================
case 'update_permissions': {
    $sessionId = (int)($body['session_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>false, 'error'=>'جلسه یافت نشد', 'code'=>'not_found'], 404);
    if ((int)$session['advisor_id'] !== $me && $role !== 'admin') out(['ok'=>false, 'error'=>'دسترسی ندارید', 'code'=>'forbidden'], 403);
    $map = ['mic'=>'allow_student_mic','cam'=>'allow_student_cam','screen'=>'allow_screen_share','whiteboard'=>'allow_whiteboard','chat'=>'allow_chat'];
    $data = [];
    foreach ($map as $k=>$col) if (array_key_exists($k, $body)) $data[$col] = !empty($body[$k]) ? 1 : 0;
    if (!$data) out(['ok'=>false, 'error'=>'موردی برای ذخیره نیست'], 422);
    $ok = online_session_update($sessionId, (int)$session['advisor_id'], $data);
    // اگر مشاور دسترسی عمومی را خاموش کرد، اجازه‌های فردی قبلی همان نوع هم با یک تصمیم جدید لغو می‌شوند.
    if ($ok) {
        $revoked = [];
        foreach ($map as $k=>$col) if (array_key_exists($k, $body) && empty($body[$k]) && $k !== 'chat') $revoked[] = $k;
        if ($revoked) {
            online_room_permission_schema();
            $parts = online_session_participants($sessionId);
            $ins = db()->prepare('INSERT INTO session_permission_requests (session_id,user_id,user_name,permission_type,status,decided_at,decided_by) VALUES (?,?,?,?,"denied",NOW(),?)');
            foreach ($parts as $p) foreach ($revoked as $type) $ins->execute([$sessionId, (int)$p['student_id'], $p['full_name'], $type, $me]);
        }
    }
    out(['ok'=>$ok]);
}


case 'permissions_state': {
    $sessionId = (int)($body['session_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session || !online_room_api_can_access($session, $role, $me)) out(['ok'=>false], 403);
    out(['ok'=>true, 'permissions'=>online_permission_effective_state($session, $me, $role)]);
}

case 'permission_request': {
    online_room_permission_schema();
    $sessionId = (int)($body['session_id'] ?? 0);
    $type = (string)($body['permission_type'] ?? '');
    $allowed = ['mic','cam','screen','whiteboard'];
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>false, 'error'=>'جلسه یافت نشد'], 404);
    if (!online_room_api_can_access($session, $role, $me)) out(['ok'=>false, 'error'=>'دسترسی ندارید'], 403);
    if (!in_array($type, $allowed, true)) out(['ok'=>false, 'error'=>'درخواست نامعتبر'], 422);
    if ($role !== 'student') out(['ok'=>false, 'error'=>'این درخواست فقط برای دانش‌آموز است'], 422);
    if (($session['status'] ?? '') !== 'live') out(['ok'=>false, 'error'=>'کلاس هنوز فعال نیست'], 403);
    if (online_permission_user_allowed($session, $me, $role, $type)) out(['ok'=>true, 'already'=>true, 'message'=>'این دسترسی برای شما فعال است.']);
    $chk = db()->prepare('SELECT id FROM session_permission_requests WHERE session_id=? AND user_id=? AND permission_type=? AND status="pending" LIMIT 1');
    $chk->execute([$sessionId, $me, $type]);
    if ($chk->fetchColumn()) out(['ok'=>true, 'pending'=>true, 'message'=>'درخواست قبلاً برای مشاور ارسال شده است.']);
    db()->prepare('INSERT INTO session_permission_requests (session_id,user_id,user_name,permission_type) VALUES (?,?,?,?)')
        ->execute([$sessionId, $me, $u['full_name'], $type]);
    try {
        $permFa = ['mic'=>'میکروفون','cam'=>'دوربین','screen'=>'اشتراک صفحه','whiteboard'=>'تخته'][$type] ?? 'دسترسی';
        notify((int)$session['advisor_id'], 'درخواست دسترسی کلاس 🔔', $u['full_name'] . ' درخواست ' . $permFa . ' دارد.', 'video', 'online_room.php?session=' . $sessionId);
    } catch (Throwable $e) {}
    out(['ok'=>true, 'message'=>'درخواست برای مشاور ارسال شد.']);
}

case 'permission_list': {
    online_room_permission_schema();
    $sessionId = (int)($body['session_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>true, 'requests'=>[]]);
    if ((int)$session['advisor_id'] !== $me && $role !== 'admin') out(['ok'=>true, 'requests'=>[]]);
    $st = db()->prepare('SELECT * FROM session_permission_requests WHERE session_id=? AND status="pending" ORDER BY created_at ASC LIMIT 20');
    $st->execute([$sessionId]);
    out(['ok'=>true, 'requests'=>$st->fetchAll() ?: []]);
}

case 'permission_decide': {
    online_room_permission_schema();
    $sessionId = (int)($body['session_id'] ?? 0);
    $requestId = (int)($body['request_id'] ?? 0);
    $decision = (($body['decision'] ?? '') === 'approved') ? 'approved' : 'denied';
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>false, 'error'=>'جلسه یافت نشد'], 404);
    if ((int)$session['advisor_id'] !== $me && $role !== 'admin') out(['ok'=>false, 'error'=>'دسترسی ندارید'], 403);
    $stReq = db()->prepare('SELECT user_name, permission_type FROM session_permission_requests WHERE id=? AND session_id=? AND status="pending" LIMIT 1');
    $stReq->execute([$requestId, $sessionId]);
    $reqRow = $stReq->fetch();
    db()->prepare('UPDATE session_permission_requests SET status=?, decided_at=NOW(), decided_by=? WHERE id=? AND session_id=? AND status="pending"')
        ->execute([$decision, $me, $requestId, $sessionId]);
    if ($reqRow) {
        $permFa = ['mic'=>'میکروفون','cam'=>'دوربین','screen'=>'اشتراک صفحه','whiteboard'=>'تخته'][$reqRow['permission_type']] ?? 'دسترسی';
        $txt = ($decision === 'approved') ? ('اجازه ' . $permFa . ' برای ' . $reqRow['user_name'] . ' فعال شد.') : ('درخواست ' . $permFa . ' برای ' . $reqRow['user_name'] . ' رد شد.');
        try { session_chat_send($sessionId, $me, $u['full_name'], 'system', $txt, 'system'); } catch (Throwable $e) {}
    }
    out(['ok'=>true]);
}

case 'permission_status': {
    online_room_permission_schema();
    $sessionId = (int)($body['session_id'] ?? 0);
    $afterId = (int)($body['after_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session || $role !== 'student') out(['ok'=>true, 'requests'=>[]]);
    if (!online_session_student_can_access($sessionId, $me)) out(['ok'=>true, 'requests'=>[]]);
    $st = db()->prepare('SELECT * FROM session_permission_requests WHERE session_id=? AND user_id=? AND id>? AND status<>"pending" ORDER BY id ASC LIMIT 20');
    $st->execute([$sessionId, $me, $afterId]);
    out(['ok'=>true, 'requests'=>$st->fetchAll() ?: []]);
}


case 'session_state': {
    $sessionId = (int)($body['session_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>false, 'error'=>'جلسه یافت نشد'], 404);
    if (!online_room_api_can_access($session, $role, $me)) out(['ok'=>false, 'error'=>'دسترسی ندارید'], 403);
    out(['ok'=>true, 'status'=>$session['status'], 'permissions'=>online_permission_effective_state($session, $me, $role)]);
}

// ============================================
// SESSION CONTROL
// ============================================
case 'start_session': {
    $sessionId = (int)($body['session_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>false, 'error'=>'جلسه یافت نشد', 'code'=>'not_found'], 404);
    if ($session['advisor_id'] != $me && $role !== 'admin') {
        out(['ok'=>false, 'error'=>'دسترسی ندارید', 'code'=>'forbidden'], 403);
    }
    if ($session['status'] !== 'scheduled' && $session['status'] !== 'live') {
        out(['ok'=>false, 'error'=>'جلسه قابل شروع نیست', 'code'=>'invalid_state'], 422);
    }
    $ok = online_session_start($sessionId, (int)$session['advisor_id']);
    out(['ok'=>$ok, 'error'=>$ok ? null : 'شروع جلسه ناموفق بود']);
}

case 'end_session': {
    $sessionId = (int)($body['session_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>false, 'error'=>'جلسه یافت نشد', 'code'=>'not_found'], 404);
    if ($session['advisor_id'] != $me && $role !== 'admin') {
        out(['ok'=>false, 'error'=>'دسترسی ندارید', 'code'=>'forbidden'], 403);
    }
    $ok = online_session_end($sessionId, (int)$session['advisor_id']);
    out(['ok'=>$ok, 'error'=>$ok ? null : 'پایان جلسه ناموفق بود']);
}

default:
    out(['ok'=>false, 'error'=>'عملیات نامعتبر: '.$action, 'code'=>'invalid_action'], 400);
}
} catch (Throwable $e) {
    error_log('API online_room error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    out(['ok'=>false, 'error'=> APP_ENV==='development' ? $e->getMessage() : 'خطای سرور', 'code'=>'server_error', 'trace'=> APP_ENV==='development' ? substr($e->getTraceAsString(), 0, 500) : null], 500);
}
