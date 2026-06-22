<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/log.php';
boot_session();
require_role('student');
require_csrf();
$u = current_user();
user_mood_schema_ready();
$mood = (string)(input('mood') ?: (body_json()['mood'] ?? ''));
$allowed = ['happy','ok','meh','tired','stressed'];
if (!in_array($mood, $allowed, true)) json_out(['ok'=>false,'error'=>'مقدار نامعتبر'],422);
db()->prepare('UPDATE users SET mood=?, mood_date=? WHERE id=?')->execute([$mood, date('Y-m-d'), $u['id']]);
log_activity((int)$u['id'], 'mood_recorded', 'user', (int)$u['id'], ['mood' => $mood]);
json_out(['ok'=>true]);
