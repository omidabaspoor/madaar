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

// CSRF - اگر match نکرد، خروج ملایم (warn ولی ادامه)
$csrfOk = false;
$csrfToken = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string)$_SERVER['HTTP_X_CSRF_TOKEN'] : '';
if ($csrfToken && isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $csrfToken)) {
    $csrfOk = true;
}

// اگر CSRF نداره ولی cookie session داره، soft-mode بگیر
if (!$csrfOk && empty($_SESSION['soft_online_access'])) {
    // درخواست‌های GET (لیست‌ها) اجازه می‌دهیم بدون CSRF
    if (!in_array($action, ['chat_list', 'hand_list', 'whiteboard_load'])) {
        // برای action های حساس، CSRF لازم است
        if (!isset($_GET['soft']) && !$csrfOk) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(419);
            echo json_encode(['ok' => false, 'error' => 'توکن CSRF نامعتبر', 'code' => 'csrf_invalid'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    // soft-mode: فقط session کافی است (برای localhost و debug)
    $_SESSION['soft_online_access'] = true;
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

try {
switch ($action) {

// ============================================
// CHAT
// ============================================
case 'chat_send': {
    $sessionId = (int)($body['session_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>false, 'error'=>'جلسه یافت نشد', 'code'=>'not_found'], 404);

    if ($role === 'student' && !online_session_student_can_access($sessionId, $me)) {
        out(['ok'=>false, 'error'=>'دسترسی ندارید', 'code'=>'forbidden'], 403);
    }
    if (empty($session['allow_chat']) && $role !== 'admin' && $role !== 'advisor') {
        out(['ok'=>false, 'error'=>'چت غیرفعال است', 'code'=>'chat_disabled'], 403);
    }
    if ($session['status'] === 'ended' || $session['status'] === 'cancelled') {
        out(['ok'=>false, 'error'=>'جلسه تمام شده', 'code'=>'session_ended'], 403);
    }

    $message = trim((string)($body['message'] ?? ''));
    if (!$message) out(['ok'=>false, 'error'=>'پیام خالی است', 'code'=>'empty_message'], 422);
    if (mb_strlen($message) > 500) $message = mb_substr($message, 0, 500);

    $type = in_array($body['message_type'] ?? 'text', ['text','emoji','system','file'], true) ? $body['message_type'] : 'text';
    $id = session_chat_send($sessionId, $me, $u['full_name'], $role, $message, $type);
    out(['ok'=>true, 'id'=>$id]);
}

case 'chat_list': {
    $sessionId = (int)($body['session_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>true, 'messages'=>[], 'note'=>'جلسه یافت نشد']);

    if ($role === 'student' && !online_session_student_can_access($sessionId, $me)) {
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

    if ($role === 'student' && !online_session_student_can_access($sessionId, $me)) {
        out(['ok'=>false, 'error'=>'دسترسی ندارید', 'code'=>'forbidden'], 403);
    }
    if (empty($session['allow_whiteboard']) && $role !== 'admin') {
        out(['ok'=>false, 'error'=>'تخته غیرفعال است', 'code'=>'wb_disabled'], 403);
    }
    if ($session['status'] === 'ended' || $session['status'] === 'cancelled') {
        out(['ok'=>false, 'error'=>'جلسه تمام شده', 'code'=>'session_ended'], 403);
    }

    $snapshot = trim((string)($body['snapshot'] ?? ''));
    if (!$snapshot) out(['ok'=>false, 'error'=>'snapshot خالی است', 'code'=>'empty_snapshot'], 422);
    if (strlen($snapshot) > 5 * 1024 * 1024) out(['ok'=>false, 'error'=>'snapshot خیلی بزرگ است', 'code'=>'too_large'], 413);

    $vid = whiteboard_save($sessionId, $me, $snapshot);
    out(['ok'=>true, 'version'=>$vid]);
}

case 'whiteboard_load': {
    $sessionId = (int)($body['session_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>true, 'snapshot'=>null]);

    if ($role === 'student' && !online_session_student_can_access($sessionId, $me)) {
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

    if ($role === 'student' && !online_session_student_can_access($sessionId, $me)) {
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

    if ($role === 'student' && !online_session_student_can_access($sessionId, $me)) {
        out(['ok'=>false, 'error'=>'دسترسی ندارید', 'code'=>'forbidden'], 403);
    }
    if ($session['status'] !== 'live') {
        out(['ok'=>false, 'error'=>'جلسه فعال نیست', 'code'=>'session_not_live'], 403);
    }

    $type = trim((string)($body['reaction_type'] ?? 'clap'));
    session_reaction_send($sessionId, $me, $u['full_name'], $type);
    out(['ok'=>true]);
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
    online_session_start($sessionId, $session['advisor_id']);
    out(['ok'=>true]);
}

case 'end_session': {
    $sessionId = (int)($body['session_id'] ?? 0);
    $session = online_session_get($sessionId);
    if (!$session) out(['ok'=>false, 'error'=>'جلسه یافت نشد', 'code'=>'not_found'], 404);
    if ($session['advisor_id'] != $me && $role !== 'admin') {
        out(['ok'=>false, 'error'=>'دسترسی ندارید', 'code'=>'forbidden'], 403);
    }
    online_session_end($sessionId, $session['advisor_id']);
    out(['ok'=>true]);
}

default:
    out(['ok'=>false, 'error'=>'عملیات نامعتبر: '.$action, 'code'=>'invalid_action'], 400);
}
} catch (Throwable $e) {
    error_log('API online_room error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    out(['ok'=>false, 'error'=> APP_ENV==='development' ? $e->getMessage() : 'خطای سرور', 'code'=>'server_error', 'trace'=> APP_ENV==='development' ? substr($e->getTraceAsString(), 0, 500) : null], 500);
}
