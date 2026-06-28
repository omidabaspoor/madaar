<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/online_sessions.php';
require_once __DIR__ . '/../includes/panel_layout.php';

boot_session();
require_role('student');
$u = current_user();
$me = (int)$u['id'];

online_sessions_schema_ready();
$sessions = online_sessions_for_student($me);

$live = array_values(array_filter($sessions, fn($s) => ($s['status'] ?? '') === 'live'));
$upcoming = array_values(array_filter($sessions, fn($s) => ($s['status'] ?? '') === 'scheduled')); 
$history = array_values(array_filter($sessions, fn($s) => in_array(($s['status'] ?? ''), ['ended','cancelled'], true)));

panel_start('جلسات آنلاین', 'کلاس‌های زنده، سریع و هماهنگ با مَدار', 'student', 'online_sessions', ['student.css']);
?>
<style>
.so-hero{position:relative;overflow:hidden;margin-bottom:20px;border-radius:28px;border:1px solid rgba(110,231,160,.24);background:radial-gradient(circle at 12% 0%,rgba(110,231,160,.17),transparent 38%),linear-gradient(135deg,rgba(16,28,23,.96),rgba(7,13,11,.98));box-shadow:0 24px 80px rgba(0,0,0,.32);padding:24px}.so-hero h2{margin:8px 0 8px;font-size:1.42rem;font-weight:1000;color:#dfffe8}.so-hero p{max-width:820px;color:var(--text-2);line-height:2;margin:0}.so-guide{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:16px 0 22px}.so-guide div{border:1px solid var(--border-soft);background:var(--card);border-radius:17px;padding:13px}.so-guide b{display:block;color:var(--gold-light);font-size:.9rem;margin-bottom:4px}.so-guide span{font-size:.76rem;color:var(--text-3);line-height:1.7}.so-section{margin-bottom:22px}.so-section h3{display:flex;align-items:center;gap:9px;margin:0 0 13px;color:var(--text-1);font-size:1.08rem}.so-list{display:grid;gap:12px}.so-card{position:relative;overflow:hidden;border:1px solid var(--border-soft);border-radius:22px;background:linear-gradient(135deg,rgba(255,255,255,.045),rgba(255,255,255,.02));padding:17px;box-shadow:0 12px 34px rgba(0,0,0,.18)}.so-card.live{border-color:rgba(110,231,160,.5);box-shadow:0 0 0 1px rgba(110,231,160,.14) inset,0 18px 60px rgba(110,231,160,.10)}.so-card.cancelled,.so-card.ended{opacity:.72}.so-card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.so-title{font-weight:1000;font-size:1.02rem;color:var(--text-1);display:flex;align-items:center;gap:8px;flex-wrap:wrap}.so-live-dot{width:9px;height:9px;border-radius:50%;background:#6ee7a0;display:inline-block;box-shadow:0 0 0 0 rgba(110,231,160,.7);animation:soPulse 1.5s infinite}@keyframes soPulse{70%{box-shadow:0 0 0 10px rgba(110,231,160,0)}}.so-meta{display:flex;gap:12px;flex-wrap:wrap;color:var(--text-3);font-size:.82rem;margin-top:9px}.so-desc{margin-top:10px;line-height:1.8;color:var(--text-2);font-size:.86rem}.so-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;padding-top:14px;border-top:1px solid var(--border-soft)}.so-actions .btn{font-weight:950}.so-wait{padding:9px 12px;border-radius:14px;background:rgba(217,178,95,.10);border:1px solid rgba(217,178,95,.26);color:var(--warn);font-size:.82rem;font-weight:900}.so-perm-note{margin-top:12px;padding:10px 12px;border-radius:16px;background:rgba(110,231,160,.07);border:1px solid rgba(110,231,160,.16);color:#bceccc;font-size:.8rem;line-height:1.9}.so-empty{border:1px dashed var(--border);border-radius:22px;padding:34px;text-align:center;color:var(--text-3);background:rgba(255,255,255,.025)}@media(max-width:900px){.so-guide{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.so-guide{grid-template-columns:1fr}.so-card-top{flex-direction:column}.so-actions .btn,.so-actions a{width:100%;justify-content:center}.so-hero{padding:20px}}
</style>

<section class="so-hero">
  <span class="badge badge-sage" style="font-weight:1000">🔴 کلاس آنلاین مَدار</span>
  <h2>کلاس‌های آنلاین شما</h2>
  <p>وقتی مشاور کلاس را شروع کند، دکمه‌ی ورود فعال می‌شود. تصویر و صدا با مسیر داخلی رایگان و سریع برقرار می‌شود. برای روشن کردن میکروفون، دوربین، اشتراک صفحه یا نوشتن روی تخته، داخل کلاس از مشاور درخواست اجازه می‌دهی.</p>
</section>

<div class="so-guide">
  <div><b>۱. ورود با Chrome/Edge</b><span>برای بهترین پایداری از مرورگر به‌روز استفاده کن.</span></div>
  <div><b>۲. اجازه مرورگر</b><span>اگر مشاور اجازه داد، مرورگر هم دسترسی میکروفون/دوربین می‌خواهد.</span></div>
  <div><b>۳. اینترنت پایدار</b><span>برای کلاس تصویری، اینترنت ثابت‌تر بهتر است.</span></div>
  <div><b>۴. کنترل مشاور</b><span>میکروفون، دوربین، اسکرین و تخته با اجازه مشاور فعال می‌شود.</span></div>
</div>

<?php if ($live): ?>
<section class="so-section">
  <h3><?= icon('video',20) ?> کلاس‌های در حال برگزاری</h3>
  <div class="so-list">
    <?php foreach ($live as $s): ?>
      <article class="so-card live">
        <div class="so-card-top">
          <div>
            <div class="so-title"><span class="so-live-dot"></span><?= e($s['title']) ?><span class="badge badge-sage" style="font-size:.72rem">LIVE</span></div>
            <div class="so-meta">
              <span><?= icon('user',13) ?> مشاور: <?= e($s['advisor_name']) ?></span>
              <span><?= icon('clock',13) ?> شروع: <?= !empty($s['started_at']) ? fa_num(substr((string)$s['started_at'],11,5)) : 'اکنون' ?></span>
              <span><?= icon('timer',13) ?> <?= fa_num((int)$s['duration_min']) ?> دقیقه</span>
            </div>
          </div>
        </div>
        <?php if (!empty($s['description'])): ?><div class="so-desc"><?= e($s['description']) ?></div><?php endif; ?>
        <div class="so-perm-note">داخل اتاق، اگر بخواهی میکروفون/دوربین/اشتراک صفحه/تخته را فعال کنی، درخواست برای مشاور نمایش داده می‌شود.</div>
        <div class="so-actions">
          <a href="<?= url('online_room.php?session='.(int)$s['id']) ?>" class="btn btn-gold btn-lg"><?= icon('login',18) ?> ورود به اتاق کلاس</a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="so-section">
  <h3><?= icon('calendar',20) ?> کلاس‌های زمان‌بندی‌شده</h3>
  <?php if (!$upcoming): ?>
    <div class="so-empty">کلاس آنلاینی در انتظار شروع نداری.</div>
  <?php else: ?>
  <div class="so-list">
    <?php foreach ($upcoming as $s): ?>
      <article class="so-card <?= e($s['status']) ?>">
        <div class="so-card-top">
          <div>
            <div class="so-title"><?= icon('video',18) ?> <?= e($s['title']) ?><span class="badge badge-gold" style="font-size:.72rem"><?= e(online_session_status_label((string)$s['status'])) ?></span></div>
            <div class="so-meta">
              <span><?= icon('user',13) ?> مشاور: <?= e($s['advisor_name']) ?></span>
              <span><?= icon('calendar',13) ?> <?= !empty($s['scheduled_at']) ? jalali_date(substr((string)$s['scheduled_at'],0,10)) : '—' ?></span>
              <span><?= icon('clock',13) ?> <?= !empty($s['scheduled_at']) ? fa_num(substr((string)$s['scheduled_at'],11,5)) : '—' ?></span>
              <span><?= icon('timer',13) ?> <?= fa_num((int)$s['duration_min']) ?> دقیقه</span>
            </div>
          </div>
        </div>
        <?php if (!empty($s['description'])): ?><div class="so-desc"><?= e($s['description']) ?></div><?php endif; ?>
        <div class="so-actions"><span class="so-wait"><?= icon('clock',14) ?> منتظر شروع کلاس توسط مشاور</span></div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<section class="so-section">
  <h3><?= icon('history',20) ?> سابقه کلاس‌ها</h3>
  <?php if (!$history): ?>
    <div class="so-empty">هنوز سابقه‌ای ثبت نشده است.</div>
  <?php else: ?>
  <div class="so-list">
    <?php foreach ($history as $s): ?>
      <article class="so-card <?= e($s['status']) ?>">
        <div class="so-title"><?= icon('video',18) ?> <?= e($s['title']) ?><span class="badge" style="font-size:.72rem"><?= e(online_session_status_label((string)$s['status'])) ?></span></div>
        <div class="so-meta">
          <span><?= icon('user',13) ?> <?= e($s['advisor_name']) ?></span>
          <span><?= icon('calendar',13) ?> <?= !empty($s['scheduled_at']) ? jalali_date(substr((string)$s['scheduled_at'],0,10)) : '—' ?></span>
          <?php if (!empty($s['participant_joined_at'])): ?><span><?= icon('login',13) ?> ورود: <?= fa_num(substr((string)$s['participant_joined_at'],11,5)) ?></span><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php panel_end(); ?>
