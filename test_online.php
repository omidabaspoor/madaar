<?php
/**
 * اسکریپت تست برای بررسی وضعیت جلسات آنلاین
 * فقط برای مدیر ارشد (admin) در دسترس است
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/online_sessions.php';
require_once __DIR__ . '/includes/helpers.php';

boot_session();

// فقط ادمین ارشد می‌تواند این صفحه را ببیند
$currentUser = current_user();
if (!$currentUser || ($currentUser['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><title>دسترسی محدود</title>
<style>body{background:#0c1512;color:#eef2ee;font-family:Vazirmatn,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;padding:20px}
.box{background:rgba(217,116,116,.12);border:1px solid rgba(217,116,116,.4);border-radius:14px;padding:30px;text-align:center;max-width:480px}
h1{color:#ff9a9a;margin:0 0 12px}a{color:#cbac80;text-decoration:none}</style>
</head>
<body><div class="box"><h1>🔒 دسترسی محدود</h1><p>این صفحه فقط برای مدیر ارشد در دسترس است.</p><a href="' . url('') . '">بازگشت به خانه</a></div></body>
</html>');
}

$tests = [];

// ۱. بررسی session
try {
    if (session_status() !== PHP_SESSION_ACTIVE) boot_session();
    $tests[] = ['✅ Session', 'فعال'];
} catch (Throwable $e) {
    $tests[] = ['❌ Session', $e->getMessage()];
}

// ۲. بررسی اتصال به دیتابیس
try {
    db()->query('SELECT 1');
    $tests[] = ['✅ اتصال به پایگاه داده', 'موفق'];
} catch (Throwable $e) {
    $tests[] = ['❌ اتصال به پایگاه داده', $e->getMessage()];
}

// ۳. ساخت جداول
try {
    $ok = online_sessions_schema_ready();
    if ($ok) {
        $tests[] = ['✅ ساخت جداول', 'موفق'];
    } else {
        $tests[] = ['❌ ساخت جداول', 'خطا - لاگ سرور را بررسی کنید'];
    }
} catch (Throwable $e) {
    $tests[] = ['❌ ساخت جداول', $e->getMessage()];
}

// ۴. بررسی وجود جداول
$tables = ['online_sessions', 'session_participants', 'whiteboard_snapshots', 'session_chat_messages', 'session_reactions', 'session_hand_raises'];
foreach ($tables as $t) {
    try {
        $r = db()->query("SHOW TABLES LIKE " . db()->quote($t))->fetch();
        if ($r) {
            $count = db()->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            $tests[] = ["✅ جدول $t", "موجود ($count ردیف)"];
        } else {
            $tests[] = ["❌ جدول $t", "موجود نیست!"];
        }
    } catch (Throwable $e) {
        $tests[] = ["❌ جدول $t", $e->getMessage()];
    }
}

// ۵. تست توابع
try {
    $u = current_user();
    if ($u) {
        $tests[] = ['✅ current_user()', $u['full_name']];
    } else {
        $tests[] = ['⚠️ current_user()', 'وارد نشده‌اید - لاگین کنید'];
    }
} catch (Throwable $e) {
    $tests[] = ['❌ current_user()', $e->getMessage()];
}

// ۶. تست توابع online_sessions
try {
    if (function_exists('online_session_get')) {
        $tests[] = ['✅ online_session_get', 'تابع موجود'];
    } else {
        $tests[] = ['❌ online_session_get', 'تابع موجود نیست!'];
    }
    if (function_exists('online_session_participants')) {
        $tests[] = ['✅ online_session_participants', 'تابع موجود'];
    }
    if (function_exists('online_sessions_schema_ready')) {
        $tests[] = ['✅ online_sessions_schema_ready', 'تابع موجود'];
    }
} catch (Throwable $e) {
    $tests[] = ['❌ توابع', $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تست سیستم جلسات آنلاین</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>
body{background:#0c1512;color:#eef2ee;font-family:'Vazirmatn',sans-serif;padding:20px;margin:0}
.wrap{max-width:760px;margin:0 auto}
h1{color:#e0c595;border-bottom:1px solid #283530;padding-bottom:14px}
.test{background:#15201b;border:1px solid #1f2a25;border-radius:10px;padding:14px 18px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}
.test b{font-weight:900}
.test .v{color:#b9c4bd;font-size:.86rem}
.btn{padding:10px 20px;background:#cbac80;color:#1a1206;border-radius:12px;text-decoration:none;font-weight:900;display:inline-block}
</style>
</head>
<body>
<div class="wrap">
<h1>🧪 تست سیستم جلسات آنلاین</h1>

<?php foreach ($tests as $t): ?>
<div class="test">
  <b><?= e($t[0]) ?></b>
  <span class="v"><?= e($t[1]) ?></span>
</div>
<?php endforeach; ?>

<div style="margin-top:24px;padding:16px;background:#15201b;border-radius:12px;border:1px solid #283530;font-size:.86rem;color:#b9c4bd">
  💡 <b>راهنما:</b> اگر همه چیز ✅ است، می‌توانید وارد جلسه شوید.<br>
  اگر جدول ❌ داشت، فایل <code>/install.php</code> را اجرا کنید.<br>
  اگر خطای ناشناخته دیدید، محتوای error_log سرور را بررسی کنید.
</div>

<div style="margin-top:24px;text-align:center">
  <a href="<?= url('admin/online_sessions.php') ?>" class="btn">← بازگشت به جلسات آنلاین</a>
</div>

</div>
</body>
</html>
