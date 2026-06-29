<?php
/**
 * مَدار — اتاق کلاس آنلاین سینک‌شده با UI ارسالی کارفرما
 * Media: رایگان و سریع با WebRTC P2P داخلی + signaling روی PHP/MySQL
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/online_sessions.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/icons.php';

boot_session();

$errorTitle = '';
$errorMessage = '';
$errorLink = 'auth/login.php';
$errorLinkText = 'ورود به حساب';
$session = null;
$participants = [];
$u = current_user();

try {
    if (!$u) {
        http_response_code(401);
        throw new RuntimeException('برای ورود به اتاق کلاس باید ابتدا وارد حساب کاربری خود شوید.');
    }

    online_sessions_schema_ready();
    $sessionId = (int)($_GET['session'] ?? 0);
    if ($sessionId <= 0) {
        http_response_code(400);
        $errorLink = (($u['role'] ?? '') === 'student') ? 'student/online_sessions.php' : 'admin/online_sessions.php';
        $errorLinkText = 'بازگشت به جلسات آنلاین';
        throw new RuntimeException('شناسه کلاس آنلاین نامعتبر است.');
    }

    $session = online_session_get($sessionId);
    if (!$session) {
        http_response_code(404);
        $errorLink = (($u['role'] ?? '') === 'student') ? 'student/online_sessions.php' : 'admin/online_sessions.php';
        $errorLinkText = 'بازگشت به جلسات آنلاین';
        throw new RuntimeException('این کلاس آنلاین وجود ندارد یا حذف شده است.');
    }

    $role = (string)($u['role'] ?? 'student');
    $isHost = online_session_is_host($session, (int)$u['id'], $role);
    if ($role === 'student' && !online_session_student_can_access($sessionId, (int)$u['id'])) {
        http_response_code(403);
        $errorLink = 'student/online_sessions.php';
        $errorLinkText = 'بازگشت به کلاس‌های من';
        throw new RuntimeException('شما به این کلاس دعوت نشده‌اید.');
    }
    if ($role === 'advisor' && !$isHost) {
        http_response_code(403);
        $errorLink = 'admin/online_sessions.php';
        $errorLinkText = 'بازگشت به جلسات آنلاین';
        throw new RuntimeException('فقط مشاور سازنده یا مدیر ارشد می‌تواند وارد این اتاق شود.');
    }

    if (in_array((string)$session['status'], ['ended','cancelled'], true)) {
        http_response_code(410);
        $errorLink = $role === 'student' ? 'student/online_sessions.php' : 'admin/online_sessions.php';
        $errorLinkText = 'بازگشت به جلسات آنلاین';
        throw new RuntimeException($session['status'] === 'cancelled' ? 'این کلاس لغو شده است.' : 'این کلاس پایان یافته است.');
    }

    $participants = online_session_participants($sessionId);
    $effectivePermissions = online_permission_effective_state($session, (int)$u['id'], $role);
} catch (Throwable $e) {
    $errorTitle = 'ورود به کلاس ممکن نیست';
    $errorMessage = $e->getMessage();
}

if ($errorMessage):
?><!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($errorTitle) ?> · مَدار</title><link rel="stylesheet" href="<?= asset('css/app.css') ?>"><style>body{min-height:100vh;display:grid;place-items:center;background:#0b0d10;color:#f0f2f5;font-family:Vazirmatn,Tahoma,sans-serif;padding:20px}.box{width:min(520px,100%);background:#12151a;border:1px solid rgba(255,255,255,.08);border-radius:24px;padding:28px;text-align:center;box-shadow:0 24px 80px rgba(0,0,0,.4)}.box h1{font-size:1.2rem;color:#ff9a9a}.box p{color:#a0a8b4;line-height:1.9}.box a{display:inline-flex;align-items:center;gap:8px;margin-top:12px;background:linear-gradient(135deg,#6ee7a0,#48a86c);color:#0b0d10;border-radius:12px;padding:11px 18px;text-decoration:none;font-weight:900}</style></head><body><div class="box"><h1><?= e($errorTitle) ?></h1><p><?= e($errorMessage) ?></p><a href="<?= url($errorLink) ?>"><?= icon('arrow-right',16) ?> <?= e($errorLinkText) ?></a></div></body></html><?php exit; endif;

$sessionId = (int)$session['id'];
$role = (string)$u['role'];
$isHost = online_session_is_host($session, (int)$u['id'], $role);
$advisorName = (string)($session['advisor_name'] ?? 'مشاور');
$advisorLetters = mb_substr($advisorName, 0, 1) ?: 'م';
$participantCount = count($participants) + 1;
$exitUrl = $role === 'student' ? 'student/online_sessions.php' : 'admin/online_sessions.php';
$participantsPayload = array_map(fn($p) => [
    'id' => (int)$p['student_id'],
    'name' => (string)$p['full_name'],
    'username' => (string)($p['username'] ?? ''),
    'avatar' => mb_substr((string)$p['full_name'], 0, 1) ?: 'د',
], $participants);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<title><?= e($session['title']) ?> · کلاس آنلاین مَدار</title>
<meta name="theme-color" content="#0b0d10">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="stylesheet" href="<?= asset('css/online_class.css') ?>">
</head>
<body>
<header class="hdr">
  <div class="hdr-r">
    <a class="logo" href="<?= url($exitUrl) ?>" style="text-decoration:none">
      <div class="logo-i"><svg viewBox="0 0 24 24" fill="none" stroke="#0b0d10" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/></svg></div>
      <span class="logo-t">مَدار</span>
    </a>
    <div class="chip"><div class="dot-lv"></div><span class="tmr" id="tmr">00:00:00</span></div>
  </div>
  <div class="hdr-c"><span class="stitle"><?= e($session['title']) ?></span><span class="cnt" id="cnt"><?= fa_num($participantCount) ?> نفر</span></div>
  <div class="hdr-l">
    <button class="hb" id="peopleBtn" type="button" title="اعضا"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></button>
    <button class="hb" id="chatBtn" type="button" title="چت"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg><span class="bdg" id="cBdg">0</span></button>
    <button class="lv-btn" id="leaveTop" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span class="lt">خروج</span></button>
  </div>
</header>

<div class="main">
  <aside class="sb" id="sb">
    <div class="sb-h">
      <div class="sb-tabs">
        <button class="sb-t on" id="tCh" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>چت</button>
        <button class="sb-t" id="tPt" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>اعضا</button>
      </div>
      <button class="sb-x" id="closeSide" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
    </div>
    <div class="sb-bd">
      <div class="ch-p on" id="chPn">
        <div class="ch-ms" id="chMs"></div>
        <div class="ch-iw"><div class="ch-ib" id="chatBox"><textarea class="ch-in" id="chIn" placeholder="پیام..." rows="1"></textarea><button class="ch-s" id="sendChat" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg></button></div></div>
      </div>
      <div class="pt-p" id="ptPn">
        <?php if ($isHost): ?>
        <div style="padding:10px;background:var(--b2);border-bottom:1px solid var(--bd);margin-bottom:8px">
          <div style="font-size:11px;font-weight:bold;color:var(--g1);margin-bottom:8px">🔒 کنترل دسترسی عمومی اعضا:</div>
          <div style="display:flex;flex-wrap:wrap;gap:6px">
            <button class="perm-btn on" id="gPermWb" onclick="toggleGlobalPerm('whiteboard')">🎨 تخته: آزاد</button>
            <button class="perm-btn on" id="gPermMic" onclick="toggleGlobalPerm('mic')">🎙 میکروفون: آزاد</button>
            <button class="perm-btn on" id="gPermCam" onclick="toggleGlobalPerm('cam')">📷 دوربین: آزاد</button>
          </div>
        </div>
        <?php endif; ?>
        <div class="pt-reqs" id="permReqs"></div>
        <div class="pt-l" id="ptList"></div>
      </div>
    </div>
  </aside>

  <div class="va">
    <div class="vg">
      <div class="mv" id="mV">
        <div class="vc adv-c" id="advC" style="flex:1">
          <div class="vbg" id="advBg"><div class="vav" style="background:linear-gradient(135deg,var(--g1),var(--g3))"><?= e($advisorLetters) ?><div class="sp-rng"></div></div><span class="vav-nm"><?= e($advisorName) ?></span></div>
          <div class="vbot"><div class="vlbl"><span class="vrl adv">مشاور</span><span id="advNameLbl"><?= e($advisorName) ?></span></div><div class="abrs" id="advBars"><i></i><i></i><i></i></div></div>
        </div>
        <div class="rh" id="rH"></div>
        <div class="vc scr-c" id="scrC" style="flex:1">
          <div class="scr-bdg"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>اشتراک صفحه</div>
          <div class="vbg" id="screenBg" style="background:#080808"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--g1)" stroke-width="1.5" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg><span class="screen-empty">صفحه مشاور</span></div>
        </div>
      </div>
      <div class="rv" id="rV"></div>
      <div class="sr" id="sR"></div>
    </div>

    <div class="bb">
      <button class="tb" id="micTB" type="button" title="میکروفون"><svg id="micOn" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg><svg id="micOff" style="display:none;color:#ff4444" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="1" y1="1" x2="23" y2="23"/><path d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6"/><path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v1a7 7 0 0 1-.11 1.23"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg><span class="tp">میکروفون</span></button>
      <button class="tb" id="camTB" type="button" title="دوربین"><svg id="camOn" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg><svg id="camOff" style="display:none;color:#ff4444" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="1" y1="1" x2="23" y2="23"/><path d="M21 21H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h3m3-3h6l2 3h4a2 2 0 0 1 2 2v9.34m-7.72-2.06a4 4 0 1 1-5.56-5.56"/></svg><span class="tp">دوربین</span></button>
      <div class="tdv"></div>
      <button class="tb" id="scrTB" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg><span class="tp">اشتراک صفحه</span></button>
      <button class="tb" id="wbTB" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z"/></svg><span class="tp">تخته</span></button>
      <div class="tdv"></div>
      <button class="tb" id="handTB" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 11V6a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v0"/><path d="M14 10V4a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v2"/><path d="M10 10.5V6a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v8"/><path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/></svg><span class="tp">دست</span></button>
      <button class="tb" id="openChatBottom" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg><span class="tp">چت</span></button>
      <?php if ($isHost): ?><div class="tdv"></div><button class="tb" id="muteAllTB" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="1" y1="1" x2="23" y2="23"/><path d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6"/><path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v1a7 7 0 0 1-.11 1.23"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg><span class="tp">بی‌صدا</span></button><?php endif; ?>
    </div>
  </div>
</div>

<!-- Whiteboard -->
<div class="wb-ov" id="wbOv"></div>
<div class="wb-pn sz-l" id="wbPn">
  <div class="wb-rs" id="wbRs"></div>
  <div class="wb-hd">
    <div class="wb-ttl"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z"/></svg>تخته</div>
    <button class="wt wb-size-btn" id="wbSizeMinus" type="button" title="کوچک کردن تخته"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/></svg></button>
    <button class="wt wb-size-btn" id="wbSizePlus" type="button" title="بزرگ کردن تخته"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></button>
    <button class="wt wb-size-btn" id="wbFullBtn" type="button" title="تمام صفحه / اندازه استاندارد"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M16 21h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg></button>
    <div class="wd"></div>
    <button class="wt on" data-tool="pen" type="button" title="قلم"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z"/></svg></button>
    <button class="wt" data-tool="hl" type="button" title="هایلایت"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 11l-6 6v3h9l3-3"/><path d="M22 12l-4.6 4.6a2 2 0 0 1-2.8 0l-5.2-5.2a2 2 0 0 1 0-2.8L14 4"/></svg></button>
    <button class="wt" data-tool="er" type="button" title="پاک‌کن"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 20H7L3 16c-.4-.4-.4-1 0-1.4l9.6-9.6a2 2 0 0 1 2.8 0l5.2 5.2a2 2 0 0 1 0 2.8L15 18"/></svg></button>
    <button class="wt" data-tool="ln" type="button" title="خط"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="5" y1="19" x2="19" y2="5"/></svg></button>
    <button class="wt" data-tool="rc" type="button" title="مستطیل"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/></svg></button>
    <button class="wt" data-tool="ci" type="button" title="دایره"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg></button>
    <button class="wt" data-tool="ar" type="button" title="فلش"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="5" y1="19" x2="19" y2="5"/><polyline points="12 5 19 5 19 12"/></svg></button>
    <button class="wt" data-tool="tx" type="button" title="متن"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg></button>
    <button class="wt" data-tool="sel" type="button" title="انتخاب و جابه‌جایی"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="4 3"><rect x="4" y="4" width="16" height="16" rx="2"/></svg></button>
    <button class="wt" data-tool="pan" type="button" title="جابه‌جایی PDF"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 12.5V10a2 2 0 0 0-2-2 2 2 0 0 0-2 2"/><path d="M14 11V9a2 2 0 0 0-2-2 2 2 0 0 0-2 2v1"/><path d="M10 10.5V5a2 2 0 0 0-2-2 2 2 0 0 0-2 2v9"/></svg></button>
    <div class="wd"></div>
    <div class="cR"><div class="cd on" style="background:#333;width:20px;height:20px" data-color="#333"></div><div class="cd" style="background:#ef5350;width:20px;height:20px" data-color="#ef5350"></div><div class="cd" style="background:#42a5f5;width:20px;height:20px" data-color="#42a5f5"></div><div class="cd" style="background:#66bb6a;width:20px;height:20px" data-color="#66bb6a"></div><div class="cd" style="background:#ffb74d;width:20px;height:20px" data-color="#ffb74d"></div><div class="cd" style="background:#ab47bc;width:20px;height:20px" data-color="#ab47bc"></div><div class="cd" style="background:#fff;border:1px solid #ccc;width:20px;height:20px" data-color="#fff"></div></div>
    <div class="wd"></div>
    <div class="sR"><div class="sd ss1 on" data-size="2"></div><div class="sd ss2" data-size="4"></div><div class="sd ss3" data-size="7"></div><div class="sd ss4" data-size="12"></div></div>
    <div class="wd"></div>
    <button class="wt" id="wbUndo" type="button" title="برگشت"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg></button>
    <button class="wt" id="wbRedo" type="button" title="جلو"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg></button>
    <button class="wt" id="wbClear" type="button" title="پاک کردن"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg></button>
    <div class="wd"></div>
    <button class="wt" id="wbPdfBtn" type="button" title="افزودن PDF"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></button>
    <button class="wt" id="wbDownload" type="button" title="دانلود"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></button>
    <div class="wd"></div>
    <button class="wt" id="wbClose" type="button" style="color:var(--red)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
  </div>
  <div class="wb-ca" id="wbCa"><canvas id="pC"></canvas><canvas id="dC"></canvas><canvas id="uC"></canvas><div class="wb-status-sync" id="wbStatus">همگام</div><input type="file" id="pdfF" accept="application/pdf,.pdf" hidden></div>
  <div class="pdf-nav" id="pdfNav"><button class="pgb" id="pdfPrev" type="button" title="صفحه قبل"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg></button><span class="pgi" id="pgI">1/1</span><button class="pgb" id="pdfNext" type="button" title="صفحه بعد"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg></button><button class="pgb" id="pdfZoomOut" type="button" title="کوچک کردن PDF">−</button><span class="pdf-z" id="pdfZ">100%</span><button class="pgb" id="pdfZoomIn" type="button" title="بزرگ کردن PDF">+</button><button class="pgb" id="pdfReset" type="button" title="بازنشانی زوم">100</button><button class="pgb" id="pdfClose" type="button" title="بستن PDF" style="margin-right:5px;color:var(--red)">×</button></div>
</div>

<!-- Modals / Toasts / Hidden media mount -->
<div class="mdl-bg" id="lvMdl"><div class="mdl"><h3><?= icon('alert-circle',20) ?> خروج از کلاس</h3><p id="leaveMsg"><?= $isHost ? 'برای همه پایان داده شود یا فقط خارج می‌شوید؟' : 'از کلاس خارج می‌شوید؟' ?></p><div class="mdl-bt"><?php if($isHost): ?><button class="mb rd" id="endForAll" type="button">پایان برای همه</button><?php endif; ?><button class="mb cn" id="leaveOnly" type="button">فقط خروج</button><button class="mb cn" id="cancelLeave" type="button">انصراف</button></div></div></div>
<div class="mdl-bg" id="kMdl"><div class="mdl"><h3><?= icon('alert-circle',20) ?> اخراج از کلاس</h3><p id="kMsg">دانش‌آموز خارج شود؟</p><div class="mdl-bt"><button class="mb rd" id="doKick" type="button">اخراج</button><button class="mb cn" id="cancelKick" type="button">انصراف</button></div></div></div>
<div class="tts" id="tts"></div><div id="p2pMount"></div>

<script>
window.MADAR = window.MADAR || {};
window.MADAR.csrf = '<?= e(csrf_token()) ?>';
window.MADAR_ROOM = {
  apiBase: '<?= e(url('api')) ?>',
  assetBase: '<?= e(url('assets')) ?>/',
  sessionId: <?= $sessionId ?>,
  userId: <?= (int)$u['id'] ?>,
  userName: <?= json_encode((string)$u['full_name'], JSON_UNESCAPED_UNICODE) ?>,
  userRole: '<?= e($role) ?>',
  displayName: <?= json_encode((string)$u['full_name'], JSON_UNESCAPED_UNICODE) ?>,
  status: '<?= e((string)$session['status']) ?>',
  isHost: <?= $isHost ? 'true' : 'false' ?>,
  pinnedUserId: <?= (int)($session['pinned_user_id'] ?? 0) ?>,
  advisor: {id: <?= (int)$session['advisor_id'] ?>, name: <?= json_encode($advisorName, JSON_UNESCAPED_UNICODE) ?>, avatar: <?= json_encode($advisorLetters, JSON_UNESCAPED_UNICODE) ?>},
  participants: <?= json_encode($participantsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
  permissions: {
    mic: <?= !empty($effectivePermissions['mic']) ? 'true' : 'false' ?>,
    cam: <?= !empty($effectivePermissions['cam']) ? 'true' : 'false' ?>,
    screen: <?= !empty($effectivePermissions['screen']) ? 'true' : 'false' ?>,
    whiteboard: <?= !empty($effectivePermissions['whiteboard']) ? 'true' : 'false' ?>,
    chat: <?= !empty($effectivePermissions['chat']) ? 'true' : 'false' ?>
  },
  exitUrl: '<?= e(url($exitUrl)) ?>'
};
</script>
<script src="<?= asset('js/webrtc_p2p.js') ?>"></script>
<script src="<?= asset('js/online_class.js') ?>"></script>
</body>
</html>
