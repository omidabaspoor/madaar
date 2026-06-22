<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/review_scheduler.php';
boot_session();
require_login();
$u = current_user();
if (($u['role'] ?? '') === 'student') { review_due_notifications((int)$u['id']); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['read'])) {
    require_csrf();
    db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$u['id']]);
    json_out(['ok' => true]);
}

$st = db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 30');
$st->execute([$u['id']]);
$rows = $st->fetchAll();
foreach ($rows as &$r) { $r['ago'] = time_ago($r['created_at']); }
json_out(['ok' => true, 'items' => $rows]);
