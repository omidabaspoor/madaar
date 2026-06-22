<?php
/** به‌روزرسانی پروفایل و گذرواژه */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';
boot_session();
require_login();
require_csrf();
$u = current_user();
$me = (int)$u['id'];
$action = (string)input('action');

if ($action === 'profile') {
    $name = trim((string)input('full_name'));
    $phone = trim((string)input('phone'));
    $field = trim((string)input('field'));
    $grade = trim((string)input('grade'));
    if (mb_strlen($name) < 3) { flash('error','نام را کامل وارد کنید'); redirect($u['role']==='student'?'student/profile.php':'admin/settings.php'); }
    if ($phone!=='' && !preg_match('/^09\d{9}$/',$phone)) { flash('error','شماره موبایل نامعتبر'); redirect($u['role']==='student'?'student/profile.php':'admin/settings.php'); }
    db()->prepare('UPDATE users SET full_name=?,phone=?,field=?,grade=? WHERE id=?')
        ->execute([$name,$phone ?: null,$field ?: null,$grade ?: null,$me]);
    log_activity($me, 'profile_updated', 'user', $me, ['نام' => $name, 'شماره تماس' => $phone]);
    flash('success','پروفایل به‌روزرسانی شد ✅');
    redirect($u['role']==='student'?'student/profile.php':'admin/settings.php');
}

if ($action === 'password') {
    $cur = (string)input('current'); $new = (string)input('new'); $new2 = (string)input('new2');
    $target = $u['role']==='student'?'student/profile.php':'admin/settings.php';
    if (!password_verify($cur, $u['password_hash'])) { flash('error','گذرواژه فعلی نادرست است'); redirect($target); }
    if (strlen($new) < 6) { flash('error','گذرواژه جدید حداقل ۶ کاراکتر'); redirect($target); }
    if ($new !== $new2) { flash('error','تکرار گذرواژه مطابقت ندارد'); redirect($target); }
    db()->prepare('UPDATE users SET password_hash=? WHERE id=?')
        ->execute([password_hash($new, PASSWORD_BCRYPT, ['cost'=>BCRYPT_COST]), $me]);
    log_activity($me, 'password_changed', 'user', $me);
    flash('success','گذرواژه تغییر کرد 🔒');
    redirect($target);
}

flash('error','عملیات نامعتبر');
redirect('index.php');
