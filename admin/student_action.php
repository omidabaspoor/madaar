<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/log.php';
boot_session();
require_role('advisor','admin');
$u = current_user();

$isAjax = isset($_SERVER['HTTP_X_CSRF_TOKEN']);
task_status_schema_ready();
require_csrf();

$id = (int)input('id');
$action = (string)input('action');
$student = get_user($id);

if (!$student || $student['role'] !== 'student') {
    if ($isAjax) json_out(['ok'=>false,'error'=>'دانش‌آموز یافت نشد'],404);
    flash('error','دانش‌آموز یافت نشد'); redirect('admin/students.php');
}

switch ($action) {
    case 'approve':
        db()->prepare('UPDATE users SET status="active", advisor_id=COALESCE(advisor_id,?), activated_at=COALESCE(activated_at,NOW()) WHERE id=?')->execute([$u['id'],$id]);
        notify($id, 'حساب شما تأیید شد ✅', 'مشاور شما را تأیید کرد. برنامه‌ات به‌زودی آماده می‌شود.', 'success', 'student/dashboard.php');
        log_activity((int)$u['id'], 'student_status_changed', 'user', $id, ['دانش‌آموز' => $student['full_name'], 'وضعیت' => 'تایید و فعال‌سازی']);
        $msg = $student['full_name'] . ' تأیید شد.';
        break;
    case 'suspend':
        db()->prepare('UPDATE users SET status="suspended" WHERE id=?')->execute([$id]);
        log_activity((int)$u['id'], 'student_status_changed', 'user', $id, ['دانش‌آموز' => $student['full_name'], 'وضعیت' => 'مسدودسازی']);
        $msg = $student['full_name'] . ' مسدود شد.';
        break;
    case 'activate':
        db()->prepare('UPDATE users SET status="active", activated_at=COALESCE(activated_at,NOW()) WHERE id=?')->execute([$id]);
        log_activity((int)$u['id'], 'student_status_changed', 'user', $id, ['دانش‌آموز' => $student['full_name'], 'وضعیت' => 'فعال‌سازی']);
        $msg = $student['full_name'] . ' فعال شد.';
        break;
    case 'delete':
        db()->prepare('DELETE FROM users WHERE id=? AND role="student"')->execute([$id]);
        log_activity((int)$u['id'], 'student_deleted', 'user', $id, ['دانش‌آموز حذف‌شده' => $student['full_name']]);
        $msg = 'دانش‌آموز حذف شد.';
        break;
    default:
        if ($isAjax) json_out(['ok'=>false,'error'=>'عملیات نامعتبر'],400);
        flash('error','عملیات نامعتبر'); redirect('admin/students.php');
}

if ($isAjax) json_out(['ok'=>true,'message'=>$msg]);
flash('success',$msg);
redirect('admin/students.php' . (isset($_POST['back']) ? $_POST['back'] : ''));
