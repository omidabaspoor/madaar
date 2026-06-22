<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/review_scheduler.php';
boot_session();
require_role('student');
require_csrf();
$u = current_user();
$in = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') ? body_json() : $_POST;
$action = (string)($in['action'] ?? '');
$id = (int)($in['id'] ?? 0);
$st = db()->prepare('SELECT * FROM review_reminders WHERE id=? AND student_id=? LIMIT 1');
$st->execute([$id, $u['id']]);
$r = $st->fetch();
if (!$r) json_out(['ok'=>false,'error'=>'مرور پیدا نشد'],404);
if ($action === 'done') {
    $quality = (string)($in['quality'] ?? 'good');
    review_complete_item($id, (int)$u['id'], $quality);
    json_out(['ok'=>true]);
}
if ($action === 'dismiss') {
    db()->prepare("UPDATE review_reminders SET status='dismissed' WHERE id=?")->execute([$id]);
    json_out(['ok'=>true]);
}
json_out(['ok'=>false,'error'=>'عملیات نامعتبر'],400);
