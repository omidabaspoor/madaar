<?php
/**
 * ساده‌ترین تست ممکن برای online_room
 * فقط برای ادمین ارشد
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/online_sessions.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/icons.php';

boot_session();

$u = current_user();
if (!$u || ($u['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('دسترسی محدود - فقط admin');
}

$errors = [];
$warnings = [];
$ok = [];

// ۱. Session
try {
    if (session_status() === PHP_SESSION_ACTIVE) $ok[] = 'Session فعال است';
    else $errors[] = 'Session فعال نیست';
} catch (Throwable $e) {
    $errors[] = 'Session error: ' . $e->getMessage();
}

// ۲. Database
try {
    db()->query('SELECT 1');
    $ok[] = 'اتصال به پایگاه داده موفق';
} catch (Throwable $e) {
    $errors[] = 'DB error: ' . $e->getMessage();
}

// ۳. icon() function
if (function_exists('icon')) {
    $ok[] = 'تابع icon() موجود';
} else {
    $errors[] = 'تابع icon() موجود نیست! (مشکل اصلی)';
}

// ۴. e() function
if (function_exists('e')) $ok[] = 'تابع e() موجود';
else $errors[] = 'تابع e() موجود نیست';

// ۵. url() function
if (function_exists('url')) $ok[] = 'تابع url() موجود';
else $errors[] = 'تابع url() موجود نیست';

// ۶. asset() function
if (function_exists('asset')) $ok[] = 'تابع asset() موجود';
else $errors[] = 'تابع asset() موجود نیست'

// ۷. Schema
try {
    if (online_sessions_schema_ready()) $ok[] = 'Schema آماده';
    else $errors[] = 'Schema آماده نیست - install.php را اجرا کنید';
} catch (Throwable $e) {
    $errors[] = 'Schema error: ' . $e->getMessage();
}

// ۸. جداول
$tables = ['online_sessions', 'session_participants', 'whiteboard_snapshots', 'session_chat_messages', 'session_reactions', 'session_hand_raises'];
$missingTables = [];
foreach ($tables as $t) {
    try {
        $r = db()->query("SHOW TABLES LIKE " . db()->quote($t))->fetch();
        if ($r) {
            $count = db()->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            $ok[] = "جدول $t موجود ($count ردیف)";
        } else {
            $missingTables[] = $t;
        }
    } catch (Throwable $e) {
        $errors[] = "جدول $t error: " . $e->getMessage();
    }
}
if ($missingTables) {
    $errors[] = 'جداول موجود نیست: ' . implode(', ', $missingTables);
}

// ۹. جلسه تست
$sessions = online_sessions_for_advisor((int)$u['id']);
$ok[] = 'تعداد جلسات شما: ' . count($sessions);

// ۱۰. آخرین خطای PHP
$lastError = error_get_last();
if ($lastError && strpos($lastError['message'], 'online') !== false) {
    $errors[] = 'آخرین خطای PHP: ' . $lastError['message'];
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تست سیستم جلسات آنلاین</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>
body{background:#0c1512;color:#eef2ee;font-family:'Vazirmatn',sans-serif;padding:20px;margin:0}
.wrap{max-width:780px;margin:0 auto}
h1{color:#e0c595;border-bottom:1px solid #283530;padding-bottom:14px;margin-bottom:20px}
.ok{background:rgba(95,174,123,.10);border:1px solid rgba(95,174,123,.4);color:#85d89f;border-radius:10px;padding:12px 18px;margin-bottom:8px}
.err{background:rgba(217,116,116,.12);border:1px solid rgba(217,116,116,.4);color:#ff9a9a;border-radius:10px;padding:12px 18px;margin-bottom:8px}
.warn{background:rgba(217,178,95,.10);border:1px solid rgba(217,178,95,.4);color:#e0c595;border-radius:10px;padding:12px 18px;margin-bottom:8px}
.section{margin-top:20px;padding:14px 18px;background:#15201b;border-radius:14px;border:1px solid #283530;font-size:.86rem;color:#b9c4bd}
.btn{padding:10px 20px;background:linear-gradient(135deg,#e0c595,#b2945f);color:#1a1206;border-radius:12px;text-decoration:none;font-weight:900;display:inline-block;margin-top:18px}
.status-ok{color:#85d89f;font-weight:900}
.status-fail{color:#ff9a9a;font-weight:900}
h2{font-size:1.1rem;margin:20px 0 10px}
</style>
</head>
<body>
<div class="wrap">

<h1>🧪 تست سیستم جلسات آنلاین</h1>

<?php if (empty($errors)): ?>
    <div class="ok">✅ همه چیز سالم است! می‌توانید وارد جلسه شوید.</div>
<?php else: ?>
    <div class="err">❌ <?= count($errors) ?> مشکل پیدا شد</div>
<?php endif; ?>

<h2 style="color:#85d89f">✅ موارد سالم (<?= count($ok) ?>)</h2>
<?php foreach ($ok as $m): ?>
    <div class="ok"><?= e($m) ?></div>
<?php endforeach; ?>

<?php if (!empty($errors)): ?>
<h2 style="color:#ff9a9a">❌ خطاها (<?= count($errors) ?>)</h2>
<?php foreach ($errors as $m): ?>
    <div class="err"><?= e($m) ?></div>
<?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($warnings)): ?>
<h2 style="color:#e0c595">⚠️ هشدارها</h2>
<?php foreach ($warnings as $m): ?>
    <div class="warn"><?= e($m) ?></div>
<?php endforeach; ?>
<?php endif; ?>

<div class="section">
    <b>💡 راهنمای رفع مشکل:</b><br>
    • اگر جداول ❌ بود: <code>/install.php?update=1</code> را اجرا کنید<br>
    • اگر icon() ❌ بود: فایل <code>online_room.php</code> را بررسی کنید<br>
    • اگر Jitsi بلاک بود: VPN استفاده کنید یا سرور Jitsi داخلی راه‌اندازی کنید<br>
    • لاگ خطاهای PHP در سرور: <code>error_log</code> را بررسی کنید
</div>

<?php if (!empty($sessions)): ?>
<h2>📋 جلسات شما</h2>
<?php foreach ($sessions as $s): ?>
<div class="ok" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div>
        <b><?= e($s['title']) ?></b> 
        <span class="muted"><?= e($s['status']) ?> · ID: <?= (int)$s['id'] ?></span>
    </div>
    <a href="<?= url('online_room.php?session=' . (int)$s['id']) ?>" class="btn" style="margin:0;padding:8px 16px;font-size:.84rem">ورود</a>
</div>
<?php endforeach; ?>
<?php endif; ?>

<div style="text-align:center">
    <a href="<?= url('admin/online_sessions.php') ?>" class="btn">← بازگشت</a>
</div>

</div>
</body>
</html>
