<?php
/** API دستاوردها (مشاور): ساخت/ویرایش/حذف + اعطا/لغو دستی */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/log.php';
boot_session();
require_role('advisor','admin');
require_csrf();
$u = current_user();
$me = (int)$u['id'];
$in = array_merge($_POST, body_json());
$action = (string)($in['action'] ?? '');

$ICONS = ['trophy','star','fire','target','rocket','zap','heart','book','check-circle','sparkles','flag','graduation'];

try {
switch ($action) {

case 'create': {
    $title = trim((string)($in['title'] ?? ''));
    if ($title === '') json_out(['ok'=>false,'error'=>'عنوان دستاورد الزامی است'],422);
    $desc = trim((string)($in['description'] ?? '')) ?: null;
    $icon = in_array($in['icon'] ?? '', $ICONS, true) ? $in['icon'] : 'trophy';
    $ctype = in_array($in['condition_type'] ?? '', ['tasks_done','streak','manual'], true) ? $in['condition_type'] : 'manual';
    $thr = $ctype==='manual' ? 0 : max(0,(int)($in['threshold'] ?? 0));
    $so = (int)db()->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM achievements')->fetchColumn();
    $ins = db()->prepare('INSERT INTO achievements (advisor_id,title,description,icon,condition_type,threshold,sort_order) VALUES (?,?,?,?,?,?,?)');
    $ins->execute([$me,$title,$desc,$icon,$ctype,$thr,$so]);
    json_out(['ok'=>true,'id'=>(int)db()->lastInsertId()]);
}

case 'update': {
    $id = (int)($in['id'] ?? 0);
    $a = db()->prepare('SELECT id FROM achievements WHERE id=?'); $a->execute([$id]);
    if (!$a->fetch()) json_out(['ok'=>false,'error'=>'دستاورد یافت نشد'],404);
    $title = trim((string)($in['title'] ?? ''));
    if ($title === '') json_out(['ok'=>false,'error'=>'عنوان الزامی است'],422);
    $desc = trim((string)($in['description'] ?? '')) ?: null;
    $icon = in_array($in['icon'] ?? '', $ICONS, true) ? $in['icon'] : 'trophy';
    $ctype = in_array($in['condition_type'] ?? '', ['tasks_done','streak','manual'], true) ? $in['condition_type'] : 'manual';
    $thr = $ctype==='manual' ? 0 : max(0,(int)($in['threshold'] ?? 0));
    $active = isset($in['is_active']) ? (int)((string)$in['is_active']==='1') : 1;
    db()->prepare('UPDATE achievements SET title=?,description=?,icon=?,condition_type=?,threshold=?,is_active=? WHERE id=?')
        ->execute([$title,$desc,$icon,$ctype,$thr,$active,$id]);
    json_out(['ok'=>true]);
}

case 'delete': {
    $id = (int)($in['id'] ?? 0);
    db()->prepare('DELETE FROM achievements WHERE id=?')->execute([$id]);
    json_out(['ok'=>true]);
}

case 'award': { // اعطای دستی به یک دانش‌آموز
    $id = (int)($in['id'] ?? 0); $sid = (int)($in['student_id'] ?? 0);
    $s = get_user($sid);
    if (!$s || $s['role']!=='student') json_out(['ok'=>false,'error'=>'دانش‌آموز یافت نشد'],404);
    $new = award_achievement($sid, $id, $me);
    json_out(['ok'=>true,'awarded'=>$new]);
}

case 'revoke': { // لغو دستاورد از یک دانش‌آموز
    $id = (int)($in['id'] ?? 0); $sid = (int)($in['student_id'] ?? 0);
    db()->prepare('DELETE FROM student_achievements WHERE achievement_id=? AND student_id=?')->execute([$id,$sid]);
    json_out(['ok'=>true]);
}

default: json_out(['ok'=>false,'error'=>'عملیات نامعتبر'],400);
}
} catch (Throwable $e) {
    json_out(['ok'=>false,'error'=> APP_ENV==='development' ? $e->getMessage() : 'خطای سرور'],500);
}
