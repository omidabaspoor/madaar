<?php
/**
 * صفحه‌ی اتاق جلسه آنلاین - با Error Handling کامل
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/online_sessions.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/icons.php'; // ⬅️ bug fix: icon() function was missing

// ایمنی: اگر icon() هنوز تعریف نشده، fallback ساده
if (!function_exists('icon')) {
    function icon(string $name, int $size = 18): string {
        return '<span style="display:inline-block;width:' . $size . 'px;height:' . $size . 'px;text-align:center;font-size:' . round($size*0.7) . 'px">●</span>';
    }
}

// متغیرهای مورد نیاز برای error page
$errorMode = false;
$errorTitle = '';
$errorMessage = '';
$redirectUrl = '';
$redirectText = 'بازگشت';

try {
    // === شروع session ===
    if (session_status() !== PHP_SESSION_ACTIVE) {
        boot_session();
    }

    // === بررسی لاگین ===
    if (!is_logged_in()) {
        http_response_code(401);
        $errorMode = true;
        $errorTitle = 'لطفاً وارد شوید';
        $errorMessage = 'برای ورود به اتاق جلسه باید ابتدا وارد حساب کاربری خود شوید.';
        $redirectUrl = url('auth/login.php');
        $redirectText = 'ورود به حساب';
    }

    if (!$errorMode) {
        // === ساخت جداول (اگر نیست) ===
        $schemaOk = online_sessions_schema_ready();
        if (!$schemaOk) {
            error_log('Online rooms: schema creation failed');
            throw new RuntimeException('جداول جلسات آنلاین در پایگاه داده ایجاد نشده‌اند. لطفاً فایل install.php را اجرا کنید.');
        }

        // === دریافت اطلاعات جلسه ===
        $sessionId = (int)($_GET['session'] ?? 0);
        if ($sessionId <= 0) {
            http_response_code(400);
            $errorMode = true;
            $errorTitle = 'شناسه‌ی جلسه نامعتبر';
            $errorMessage = 'هیچ جلسه‌ای انتخاب نشده است.';
        } else {
            $session = online_session_get($sessionId);

            if (!$session) {
                http_response_code(404);
                $errorMode = true;
                $u = current_user();
                $errorTitle = 'جلسه یافت نشد';
                $errorMessage = 'این جلسه وجود ندارد، حذف شده یا شناسه‌ی آن اشتباه است.';
                $redirectUrl = url(($u['role'] ?? 'student') === 'student' ? 'student/online_sessions.php' : 'admin/online_sessions.php');
                $redirectText = 'بازگشت به لیست جلسات';
            } else {
                $u = current_user();
                $role = $u['role'] ?? 'student';
                $isHost = ((int)$session['advisor_id'] === (int)$u['id']) || $role === 'admin';

                // === بررسی دسترسی ===
                $accessDenied = false;
                if ($role === 'student' && !online_session_student_can_access($sessionId, (int)$u['id'])) {
                    $accessDenied = true;
                    $redirectUrl = url('student/online_sessions.php');
                    $errorMessage = 'شما به این جلسه دعوت نشده‌اید. اگر فکر می‌کنید اشتباه است، با مشاور خود تماس بگیرید.';
                } elseif ($role === 'advisor' && !$isHost) {
                    $accessDenied = true;
                    $redirectUrl = url('admin/online_sessions.php');
                    $errorMessage = 'فقط مشاورِ سازنده‌ی جلسه یا مدیر ارشد می‌تواند وارد این اتاق شود.';
                }
                if ($accessDenied) {
                    http_response_code(403);
                    $errorMode = true;
                    $errorTitle = 'دسترسی ندارید';
                    $redirectText = 'بازگشت';
                } else {
                    // === همه چیز اوکی - رندر اتاق ===
                    $participants = online_session_participants($sessionId);
                    // displayName بدون emoji (Jitsi URL params حساس به JSON هستند)
// فقط متن ساده ارسال می‌شود
$displayName = $u['full_name']; // ساده - بدون ایموجی
                    $pageTitle = $session['title'];
                }
            }
        }
    }
} catch (Throwable $e) {
    error_log('online_room.php error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    http_response_code(500);
    $errorMode = true;
    $errorTitle = 'خطا در بارگذاری اتاق';
    $errorMessage = APP_ENV === 'development'
        ? $e->getMessage()
        : 'متأسفانه در اتصال به اتاق جلسه خطایی رخ داد. لطفاً دوباره تلاش کنید.';
    if (!isset($redirectUrl) || !$redirectUrl) {
        $role = current_user()['role'] ?? 'student';
        $redirectUrl = url($role === 'student' ? 'student/online_sessions.php' : 'admin/online_sessions.php');
    }
}

// === اگر خطا داریم، صفحه‌ی خطا رو نشون بده ===
if ($errorMode) {
    ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($errorTitle) ?> · مَدار</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>
body{display:grid;place-items:center;min-height:100vh;background:#0c1512;color:#eef2ee;font-family:'Vazirmatn',sans-serif;padding:20px;margin:0}
.err-box{background:rgba(217,116,116,.12);border:1px solid rgba(217,116,116,.4);border-radius:18px;padding:36px 28px;max-width:480px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4)}
.err-box h1{color:#ff9a9a;font-size:1.3rem;margin:0 0 14px;font-weight:900;display:flex;align-items:center;justify-content:center;gap:10px}
.err-box p{color:#b9c4bd;font-size:.94rem;line-height:1.85;margin:0 0 24px}
.err-box a,.err-box button{display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#e0c595,#b2945f);color:#1a1206;border-radius:12px;text-decoration:none;font-weight:900;border:none;font-family:inherit;font-size:.94rem;cursor:pointer;transition:.2s}
.err-box a:hover,.err-box button:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(178,148,95,.3)}
.err-dev{font-family:monospace;font-size:.78rem;background:rgba(0,0,0,.4);padding:10px;border-radius:8px;color:#fca5a5;text-align:right;direction:ltr;margin-top:14px;overflow-x:auto;max-height:140px;overflow-y:auto}
</style>
</head>
<body>
<div class="err-box">
  <h1><?= icon('alert-circle',32) ?> <?= e($errorTitle) ?></h1>
  <p><?= e($errorMessage) ?></p>
  <?php if (APP_ENV === 'development' && !empty($errorMessage) && strlen($errorMessage) > 30): ?>
    <div class="err-dev"><?= e($errorMessage) ?></div>
  <?php endif; ?>
  <a href="<?= e($redirectUrl) ?>"><?= icon('arrow-right',15) ?> <?= e($redirectText) ?></a>
</div>
</body>
</html>
    <?php
    exit;
}

// === تنظیمات Jitsi ===
// می‌توانید این را در config.php یا admin/online_sessions.php تنظیم کنید
$jitsiServer = defined('JITSI_SERVER_URL') ? JITSI_SERVER_URL : 'https://meet.jit.si';
$useFallback = false; // اگر Jitsi در دسترس نیست، true کنید

// === تنظیمات تماس ===
// پیش‌فرض جدید: کاملاً داخلی و رایگان با WebRTC P2P.
// دلیل: meet.jit.si برای اتاق‌های عمومی گاهی صفحه‌ی "I'm the host"/login نشان می‌دهد و برای اینترنت ایران پایدار نیست.
// اگر روزی خواستید Jitsi را دستی تست کنید: ?jitsi=1
$useP2P = empty($_GET['jitsi']);
$jitsiDisabled = $useP2P || isset($_GET['no_jitsi']);

// === رندر اتاق ===
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
<title><?= e($session['title']) ?> · مَدار</title>
<meta name="theme-color" content="#0c1512">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('css/online_room.css') ?>">
<link rel="stylesheet" href="<?= asset('css/online_p2p.css') ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
</head>
<body>

<!-- Loading -->
<div class="or-loading" id="or-loading">
  <div>
    <div class="or-spk"></div>
    <h2>در حال اتصال به اتاق جلسه...</h2>
    <p>لطفاً صبر کنید. اگر مرورگر اجازه‌ی دسترسی به میکروفون و دوربین خواست، اجازه دهید.</p>
    <p style="font-size:.78rem;margin-top:14px;color:#7f8d86">
      کلاس در حال آماده‌سازی است. لطفاً چند لحظه صبر کنید.
    </p>
  </div>
</div>

<div class="or-room side-collapsed" id="or-room" style="display:none">
  <!-- Top Bar -->
  <div class="or-topbar">
    <div class="or-topbar-left">
      <span class="or-live-dot"></span>
      <span class="or-room-name" title="<?= e($session['title']) ?>">
        <?= icon('video',15) ?>
        <?= e($session['title']) ?>
      </span>
      <?php if ($isHost): ?>
        <span class="badge" style="background:var(--gold-glass);color:var(--gold-light);font-weight:900;font-size:.7rem">👑 میزبان</span>
      <?php endif; ?>
      <span class="or-timer"><span id="or-timer-text">00:00</span></span>
    </div>
    <div class="or-topbar-right">
      <?php if (!empty($_GET['debug_provider'])): ?>
      <button class="or-btn-icon" id="or-mode-btn" title="مسیر جایگزین کلاس"><?= icon('video',18) ?></button>
      <?php endif; ?>
      <button class="or-btn-icon" id="or-people-btn" title="شرکت‌کنندگان"><?= icon('users',18) ?></button>
      <button class="or-btn-icon" id="or-chat-btn" title="چت"><?= icon('message',18) ?></button>
      <button class="or-btn-icon" id="or-board-btn" title="تخته"><?= icon('edit',18) ?></button>
      <?php if ($isHost): ?><button class="or-btn-icon" id="or-settings-btn" title="مدیریت کلاس"><?= icon('sliders',18) ?></button><?php endif; ?>
      <button class="or-btn-icon" id="or-side-toggle" title="باز/بستن پنل"><?= icon('sidebar',18) ?></button>
      <button class="or-btn-icon" id="or-end-btn" title="<?= $isHost ? 'پایان جلسه' : 'خروج' ?>" style="background:rgba(217,116,116,.16);color:#ff9a9a;border-color:rgba(217,116,116,.3)">
        <?= icon('phone-off',18) ?>
      </button>
    </div>
  </div>

  <!-- Hand Raise Banner -->
  <?php if ($isHost): ?>
  <div class="or-hand-banner" id="or-hand-banner">
    <span id="or-hand-banner-text">✋ دست بلند شد</span>
    <span class="close" id="or-hand-banner-close">×</span>
  </div>
  <?php endif; ?>

  <!-- Main -->
  <div class="or-main">
    <div class="or-stage">
      <div class="or-jitsi-host" id="jitsi-host"></div>

      <!-- Whiteboard Overlay -->
      <div class="or-whiteboard-host" id="or-whiteboard-host">
        <div class="wb-toolbar">
          <button class="wb-tool" data-tool="pen" title="قلم"><?= icon('edit',16) ?></button>
          <button class="wb-tool" data-tool="eraser" title="پاک‌کن"><?= icon('eraser',16) ?></button>
          <div class="wb-tool-sep"></div>
          <button class="wb-tool" data-tool="line" title="خط"><?= icon('minus',16) ?></button>
          <button class="wb-tool" data-tool="rect" title="مستطیل"><?= icon('square',16) ?></button>
          <button class="wb-tool" data-tool="circle" title="دایره"><?= icon('circle',16) ?></button>
          <div class="wb-tool-sep"></div>
          <span style="color:#aaa;font-size:.74rem;font-weight:700">رنگ:</span>
          <span class="wb-color active" data-color="#000000" style="background:#000000"></span>
          <span class="wb-color" data-color="#dc2626" style="background:#dc2626"></span>
          <span class="wb-color" data-color="#2563eb" style="background:#2563eb"></span>
          <span class="wb-color" data-color="#16a34a" style="background:#16a34a"></span>
          <span class="wb-color" data-color="#eab308" style="background:#eab308"></span>
          <span class="wb-color" data-color="#f97316" style="background:#f97316"></span>
          <span class="wb-color" data-color="#9333ea" style="background:#9333ea"></span>
          <div class="wb-tool-sep"></div>
          <input type="range" min="2" max="20" value="4" id="wb-size" class="wb-size" title="ضخامت قلم">
          <div class="wb-tool-sep"></div>
          <button class="wb-tool" data-clear title="پاک کردن همه"><?= icon('trash',15) ?></button>
          <?php if ($isHost): ?>
          <button class="wb-tool" id="wb-pdf-btn" title="افزودن PDF درس"><?= icon('pdf',16) ?></button>
          <button class="wb-tool" id="wb-pdf-prev" title="صفحه قبل" style="display:none"><?= icon('chevron-right',16) ?></button>
          <span id="wb-pdf-page" style="display:none;color:#e8efe9;font-size:.74rem;font-weight:900;min-width:48px;text-align:center">۱ / ۱</span>
          <button class="wb-tool" id="wb-pdf-next" title="صفحه بعد" style="display:none"><?= icon('chevron-left',16) ?></button>
          <input id="wb-pdf-input" type="file" accept="application/pdf" hidden>
          <?php endif; ?>
          <button class="wb-tool" id="wb-download" title="دانلود خروجی کلاس"><?= icon('download',15) ?></button>
          <button class="wb-tool wb-exit" title="بستن تخته"><?= icon('x',16) ?></button>
        </div>
        <div class="wb-canvas-host">
          <canvas id="wb-canvas"></canvas>
          <div class="wb-status">
            <span class="dot"></span>
            <span id="wb-status-text">همگام‌شده</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Side Panel -->
    <div class="or-side is-hidden" id="or-side">
      <div class="or-side-tabs">
        <button class="or-side-tab active" data-tab="chat"><?= icon('message',18) ?> <span>چت</span></button>
        <button class="or-side-tab" data-tab="people"><?= icon('users',18) ?> <span>افراد</span></button>
        <button class="or-side-tab" data-tab="whiteboard"><?= icon('edit',18) ?> <span>تخته</span></button>
      </div>

      <div class="or-side-content">
        <!-- Chat -->
        <div class="or-tab-content" data-tab-content="chat" style="display:flex;flex-direction:column">
          <div class="or-chat-list" id="or-chat-list">
            <?php if ($session['status'] === 'scheduled'): ?>
              <div class="empty-state" style="padding:24px 12px;color:var(--text-3);font-size:.84rem;text-align:center">
                <?= icon('clock',20) ?>
                <p style="margin-top:8px">چت پس از شروع جلسه فعال می‌شود.</p>
              </div>
            <?php endif; ?>
          </div>
          <div class="or-chat-input-wrap">
            <textarea class="or-chat-input" id="or-chat-input" placeholder="پیام بنویسید..." rows="1" maxlength="500" <?= !$session['allow_chat'] || $session['status']!=='live' ? 'disabled' : '' ?>></textarea>
            <button class="or-chat-send" id="or-chat-send" <?= !$session['allow_chat'] || $session['status']!=='live' ? 'disabled' : '' ?>>
              <?= icon('send',16) ?>
            </button>
          </div>
        </div>

        <!-- People -->
        <div class="or-tab-content" data-tab-content="people" style="display:none;flex-direction:column">
          <div class="or-people" id="or-people-list">
            <div class="or-person is-host">
              <span class="or-person-ava" style="background:var(--grad-gold);color:#1a1206"><?= e(mb_substr((string)($session['advisor_name'] ?? '?'), 0, 2)) ?></span>
              <div class="or-person-info">
                <div class="or-person-name">میزبان · <?= e($session['advisor_name'] ?? 'میزبان') ?></div>
                <div class="or-person-role">مشاور</div>
              </div>
              <div class="or-person-icons"><span class="ic on" title="میکروفون"><?= icon('mic',14) ?></span></div>
            </div>
            <?php foreach ($participants as $p): ?>
              <div class="or-person" id="person-<?= (int)$p['student_id'] ?>" data-user-id="<?= (int)$p['student_id'] ?>">
                <span class="or-person-ava"><?= e(mb_substr((string)($p['full_name'] ?? '?'), 0, 2)) ?></span>
                <div class="or-person-info">
                  <div class="or-person-name"><?= e($p['full_name'] ?? '') ?></div>
                  <div class="or-person-role">دانش‌آموز</div>
                </div>
                <div class="or-person-icons">
                  <span class="ic" title="میکروفون"><?= icon('mic',14) ?></span>
                  <span class="ic" title="دوربین"><?= icon('video',14) ?></span>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (empty($participants)): ?>
              <div class="empty-state" style="padding:20px;color:var(--text-3);font-size:.82rem;text-align:center">
                هنوز دانش‌آموزی دعوت نشده
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Whiteboard tab -->
        <div class="or-tab-content" data-tab-content="whiteboard" style="display:none">
          <div style="padding:30px 20px;text-align:center;color:var(--text-3);font-size:.9rem">
            <div style="font-size:3rem;margin-bottom:12px">✏️</div>
            <p>تخته‌ی تعاملی</p>
            <p style="margin-top:8px;font-size:.78rem">برای استفاده، روی تب «تخته» در بالا کلیک کنید.</p>
          </div>
        </div>
      </div>
    </div>
  </div>


  <?php if ($isHost): ?>
  <div class="or-modal" id="or-settings-modal" aria-hidden="true">
    <div class="or-modal-card">
      <div class="or-modal-head">
        <strong>مدیریت کلاس</strong>
        <button class="or-btn-icon" id="or-settings-close" type="button"><?= icon('x',18) ?></button>
      </div>
      <div class="or-setting-grid">
        <label><input type="checkbox" data-perm="mic" <?= !empty($session['allow_student_mic']) ? 'checked' : '' ?>> اجازه میکروفون دانش‌آموز</label>
        <label><input type="checkbox" data-perm="cam" <?= !empty($session['allow_student_cam']) ? 'checked' : '' ?>> اجازه دوربین دانش‌آموز</label>
        <label><input type="checkbox" data-perm="screen" <?= !empty($session['allow_screen_share']) ? 'checked' : '' ?>> اجازه اشتراک صفحه</label>
        <label><input type="checkbox" data-perm="whiteboard" <?= !empty($session['allow_whiteboard']) ? 'checked' : '' ?>> اجازه کار روی تخته</label>
        <label><input type="checkbox" data-perm="chat" <?= !empty($session['allow_chat']) ? 'checked' : '' ?>> اجازه چت</label>
      </div>
      <p class="or-setting-note">درخواست‌های دانش‌آموزان برای میکروفون، دوربین و اشتراک صفحه اینجا به شما نمایش داده می‌شود.</p>
      <div class="or-requests" id="or-permission-requests"></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Reactions -->
  <div class="or-reactions" id="or-reactions"></div>

  <!-- Quick Reactions -->
  <div class="or-quick-react" id="or-quick-react">
    <button data-react="clap" title="تشویق">👏</button>
    <button data-react="heart" title="محبوب">❤️</button>
    <button data-react="thumbs" title="عالی">👍</button>
    <button data-react="fire" title="آتشین">🔥</button>
    <button data-react="star" title="ستاره">⭐</button>
    <button data-react="laugh" title="خنده">😂</button>
    <button data-react="wow" title="تعجب">😮</button>
  </div>

  <!-- Controls Bar -->
  <div class="or-controls">
    <button class="or-ctrl" id="or-mic-btn" title="میکروفون">
      <?= icon('mic',20) ?>
      <span class="or-ctrl-label">میکروفون</span>
    </button>
    <button class="or-ctrl" id="or-cam-btn" title="دوربین">
      <?= icon('video',20) ?>
      <span class="or-ctrl-label">دوربین</span>
    </button>
    <button class="or-ctrl" id="or-screen-btn" title="اشتراک صفحه">
      <?= icon('monitor',20) ?>
      <span class="or-ctrl-label">اشتراک صفحه</span>
    </button>
    <button class="or-ctrl" id="or-board-btn" title="تخته سفید">
      <?= icon('edit',20) ?>
      <span class="or-ctrl-label">تخته</span>
    </button>
    <button class="or-ctrl" id="or-chat-btn" title="چت">
      <?= icon('message',20) ?>
      <span class="or-ctrl-label">چت</span>
    </button>
    <button class="or-ctrl" id="or-people-btn" title="افراد">
      <?= icon('users',20) ?>
      <span class="or-ctrl-label">افراد</span>
    </button>
    <div class="or-ctrl-divider"></div>
    <button class="or-ctrl" id="or-hand-btn" title="بلند کردن دست">
      <?= icon('hand',20) ?>
      <span class="or-ctrl-label">دست</span>
    </button>
    <?php if ($isHost): ?>
    <button class="or-ctrl" id="or-settings-btn" title="مدیریت کلاس">
      <?= icon('sliders',20) ?>
      <span class="or-ctrl-label">مدیریت</span>
    </button>
    <?php endif; ?>
    <button class="or-ctrl" id="or-react-btn" title="واکنش">
      <?= icon('smile',20) ?>
      <span class="or-ctrl-label">واکنش</span>
    </button>
    <div class="or-ctrl-divider"></div>
    <button class="or-ctrl danger" id="or-end-btn" title="<?= $isHost ? 'پایان جلسه' : 'خروج' ?>">
      <?= icon('phone-off',20) ?>
      <span class="or-ctrl-label"><?= $isHost ? 'پایان' : 'خروج' ?></span>
    </button>
  </div>
</div>

<script>
window.MADAR = window.MADAR || {};
window.MADAR.csrf = '<?= e(csrf_token()) ?>';
window.MADAR_ROOM = {
  apiBase: '<?= e(url('api')) ?>',
  assetBase: '<?= e(url('assets')) ?>/',
  sessionId: <?= (int)$sessionId ?>,
  userId: <?= (int)$u['id'] ?>,
  userName: '<?= e($u['full_name']) ?>',
  userRole: '<?= e($role) ?>',
  displayName: <?= json_encode($displayName, JSON_UNESCAPED_UNICODE) ?>,
  jitsiRoom: '<?= e($session['jitsi_room_name']) ?>',
  jitsiServer: '<?= e($jitsiServer) ?>',
  useFallback: <?= $useFallback ? 'true' : 'false' ?>,
  jitsiDisabled: <?= $jitsiDisabled ? 'true' : 'false' ?>,
  useP2P: <?= $useP2P ? 'true' : 'false' ?>,
  status: '<?= e($session['status'] ?? 'scheduled') ?>',
  isHost: <?= $isHost ? 'true' : 'false' ?>,
  permissions: {
    mic: <?= !empty($session['allow_student_mic']) || $isHost ? 'true' : 'false' ?>,
    cam: <?= !empty($session['allow_student_cam']) || $isHost ? 'true' : 'false' ?>,
    screen: <?= !empty($session['allow_screen_share']) || $isHost ? 'true' : 'false' ?>,
    whiteboard: <?= !empty($session['allow_whiteboard']) || $isHost ? 'true' : 'false' ?>,
    chat: <?= !empty($session['allow_chat']) || $isHost ? 'true' : 'false' ?>
  }
};
</script>
<script src="<?= asset('js/webrtc_p2p.js') ?>"></script>
<script src="<?= asset('js/online_room.js') ?>"></script>

</body>
</html>
<?php // end of room render ?>
