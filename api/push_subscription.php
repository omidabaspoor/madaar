<?php
/** Register/unregister browser Push API subscriptions for real background notifications. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/web_push.php';
boot_session();
require_login();

$u = current_user();
$action = $_GET['action'] ?? 'save';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_out([
        'ok' => true,
        'enabled' => web_push_enabled(),
        'publicKey' => web_push_enabled() ? VAPID_PUBLIC_KEY : '',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
}
require_csrf();
$in = body_json();

if ($action === 'delete') {
    web_push_delete_subscription((int)$u['id'], (string)($in['endpoint'] ?? ''));
    json_out(['ok' => true]);
}

$sub = is_array($in['subscription'] ?? null) ? $in['subscription'] : $in;
$ok = web_push_save_subscription((int)$u['id'], $sub, (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
json_out(['ok' => $ok, 'enabled' => web_push_enabled()]);
