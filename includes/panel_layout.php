<?php
/** لایه‌بندی پنل (سایدبار + توپ‌بار) برای مدیر و دانش‌آموز */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

function avatar_letters(string $name): string
{
    $p = preg_split('/\s+/u', trim($name));
    $s = mb_substr($p[0] ?? '', 0, 1);
    if (count($p) > 1) $s .= mb_substr($p[1], 0, 1);
    return $s ?: 'م';
}

/**
 * @param string $role 'admin' | 'student'
 * @param string $active کلید آیتم فعال
 */
function panel_start(string $title, string $subtitle, string $role, string $active, array $extraCss = []): void
{
    $u = current_user();
    page_head($title, '', array_merge(['panel.css'], $extraCss));

    $items = $role === 'admin' ? [
        'main' => array_filter([
            ['dashboard','داشبورد','home','admin/dashboard.php'],
            ['students','دانش‌آموزان','users','admin/students.php'],
            ['plans','برنامه‌ها','calendar','admin/plans.php'],
            ['student_reports','گزارش حرفه‌ای','edit','admin/student_reports.php'],
            ['reports','گزارش عملکرد','chart','admin/reports.php'],
            ['exams','آزمون‌ساز','clipboard','admin/exams.php'],
            ['internal_exam','تحلیل آزمون','chart','admin/internal_exam_reports.php'],
            ['meetings','جلسات','calendar','admin/schedule_meeting.php'],
            ['online_sessions','جلسات آنلاین','video','admin/online_sessions.php'],
            ['messages','پیام‌ها','message','admin/messages.php'],
            is_chief_advisor() ? ['chat_users','کاربران چت','message','admin/chat_users.php'] : null,
            is_chief_advisor() ? ['advisors','مشاوران','users','admin/advisors.php'] : null,
        ]),
        'other' => [
            ['guide','راهنما','book','admin/guide.php'],
            ['achievements','دستاوردها','trophy','admin/achievements.php'],
            ['settings','تنظیمات','settings','admin/settings.php'],
        ],
    ] : [
        'main' => [
            ['dashboard','خانه','home','student/dashboard.php'],
            ['plan','برنامه','calendar','student/plan.php'],
            ['reports','گزارش','edit','student/reports.php?type=daily'],
            ['progress','پیشرفت','chart','student/progress.php'],
            ['ranks','رتبه‌ها','trophy','student/ranks.php'],
            ['reviews','مرور','repeat','student/reviews.php'],
            ['exams','آزمون‌ها','clipboard','student/exams.php'],
            ['exam_analyses','تحلیل آزمون','chart','student/exam_analyses.php'],
            ['meetings','جلسات','calendar','student/meetings.php'],
            ['online_sessions','جلسات آنلاین','video','student/online_sessions.php'],
            ['messages','پیام‌ها','message','student/messages.php'],
        ],
        'other' => [
            ['guide','راهنما','book','student/guide.php'],
            ['achievements','دستاوردها','trophy','student/achievements.php'],
            ['profile','پروفایل','user','student/profile.php'],
        ],
    ];

    // فیلتر منو برای مشاورهایی که دسترسی صفحه‌ای محدود دارند.
    if ($role === 'admin' && ($u['role'] ?? '') === 'advisor' && advisor_has_custom_page_access((int)$u['id'])) {
        $items['main'] = array_values(array_filter($items['main'], fn($it) => advisor_can_access_page((int)$u['id'], $it[0])));
        $items['other'] = array_values(array_filter($items['other'], fn($it) => advisor_can_access_page((int)$u['id'], $it[0])));
    }

    $notifCount = unread_notif_count((int)$u['id']);
    $msgCount   = unread_msg_count((int)$u['id']);

    // ذخیره برای ساخت نوار پایین موبایل در panel_end
    $GLOBALS['_panel_ctx'] = ['items'=>$items, 'active'=>$active, 'role'=>$role, 'msg'=>$msgCount];
    ?>
<div class="app-shell">
  <div class="sidebar-overlay" data-side-close></div>
  <aside class="sidebar" id="sidebar">
    <?= brand_block() ?>
    <nav class="side-nav">
      <span class="label">منو اصلی</span>
      <?php foreach ($items['main'] as [$key,$label,$ic,$href]):
        $cnt = $key==='messages' ? $msgCount : 0; ?>
      <a href="<?= url($href) ?>" class="side-link <?= $active===$key?'active':'' ?>">
        <?= icon($ic,20) ?> <span><?= e($label) ?></span>
        <?php if ($cnt>0): ?><span class="badge-count"><?= fa_num($cnt) ?></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
      <span class="label">حساب</span>
      <?php foreach ($items['other'] as [$key,$label,$ic,$href]): ?>
      <a href="<?= url($href) ?>" class="side-link <?= $active===$key?'active':'' ?>"><?= icon($ic,20) ?> <span><?= e($label) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <div class="side-user">
        <span class="ava"><?= e(avatar_letters($u['full_name'])) ?></span>
        <div style="flex:1;min-width:0">
          <div class="nm" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($u['full_name']) ?></div>
          <div class="rl"><?= $role==='admin' ? 'مشاور' : (e($u['field'] ?? 'دانش‌آموز')) ?></div>
        </div>
      </div>
      <a href="<?= url('pwa_help.php') ?>" class="side-link" style="margin-top:6px;font-size:.82rem;color:var(--text-3)"><?= icon('phone',18) ?> <span>نصب وب‌اپ</span></a>
      <a href="<?= url('auth/logout.php') ?>" class="side-link" style="margin-top:6px;color:var(--danger)"><?= icon('logout',20) ?> <span>خروج</span></a>
    </div>
  </aside>

  <div class="app-main">
    <header class="topbar">
      <div class="flex gap-3" style="align-items:center">
        <button class="tb-btn mobile-bar" data-side-open aria-label="منو"><?= icon('menu') ?></button>
        <div class="tb-title">
          <h1><?= e($title) ?></h1>
          <?php if ($subtitle): ?><p><?= e($subtitle) ?></p><?php endif; ?>
        </div>
      </div>
      <div class="tb-actions">
        <?php if ($role === 'admin' || $role === 'advisor'): ?>
      <?php if (($u['role'] ?? '') !== 'advisor' || advisor_can_access_page((int)$u['id'], 'guide')): ?>
      <a href="<?= url('admin/guide.php') ?>" class="btn btn-ghost btn-sm flex items-center gap-1.5" style="font-weight: 900; border: 1px solid var(--gold); border-radius: 12px; height: 38px; color: var(--gold-light);">
        📖 <span>راهنما</span>
      </a>
      <?php endif; ?>
        <?php else: ?>
      <a href="<?= url('student/guide.php') ?>" class="btn btn-ghost btn-sm flex items-center gap-1.5" style="font-weight: 900; border: 1px solid var(--sage); border-radius: 12px; height: 38px; color: var(--sage-light);">
        📖 <span>راهنما</span>
      </a>
        <?php endif; ?>
        <a href="<?= url($role==='admin'?'admin/messages.php':'student/messages.php') ?>" class="tb-btn" data-tip="پیام‌ها"><?= icon('message',20) ?><?php if($msgCount>0):?><span class="dot"></span><?php endif;?></a>
        <button class="tb-btn" id="notifBtn" data-tip="اعلان‌ها"><?= icon('bell',20) ?><?php if($notifCount>0):?><span class="dot"></span><?php endif;?></button>
        <span class="badge badge-sage" style="padding:8px 12px"><?= icon('fire',15) ?> <span data-live-streak><?= fa_num($u['streak'] ?? 0) ?></span> روز</span>
      </div>
    </header>
    <main class="content">
    <?php foreach (get_flashes() as $f): ?>
      <div class="alert alert-<?= $f['type']==='success'?'success':($f['type']==='error'?'error':'info') ?>" style="margin-bottom:18px"><?= icon('info',18) ?><span><?= e($f['msg']) ?></span></div>
    <?php endforeach; ?>
    <?php
      $globalPendingReports = [];
      if ($role === 'student') {
          try {
              require_once __DIR__ . '/reporting.php';
              $globalPendingReports = report_pending_items((int)$u['id']);
          } catch (Throwable $e) { $globalPendingReports = []; }
      }
      if ($globalPendingReports && $active !== 'reports'):
        $firstReport = $globalPendingReports[0];
        $isUrgentReport = !empty($firstReport['urgent']);
    ?>
      <div class="panel report-due-global <?= $isUrgentReport ? 'urgent' : '' ?>" style="margin-bottom:18px;border:1px solid <?= $isUrgentReport ? 'rgba(255,107,53,.45)' : 'var(--gold)' ?>;background:<?= $isUrgentReport ? 'linear-gradient(135deg,rgba(255,107,53,.13),rgba(12,21,18,.92))' : 'linear-gradient(135deg,rgba(203,172,128,.12),rgba(12,21,18,.92))' ?>;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:12px">
          <span style="font-size:1.6rem"><?= $isUrgentReport ? '⏰' : '📝' ?></span>
          <div>
            <b><?= $isUrgentReport ? 'کجایی؟ گزارش امروز هنوز ثبت نشده' : 'گزارش در انتظار تکمیل داری' ?></b>
            <div class="muted" style="font-size:.84rem;margin-top:3px">
              <?= e($firstReport['label']) ?> · <?= jalali_date($firstReport['start']) ?><?= $firstReport['start']!==$firstReport['end']?' تا '.jalali_date($firstReport['end']):'' ?>
              <?php if (count($globalPendingReports) > 1): ?> · <?= fa_num(count($globalPendingReports)) ?> گزارش در انتظار<?php endif; ?>
            </div>
          </div>
        </div>
        <a class="btn btn-gold btn-sm" href="<?= e($firstReport['url']) ?>" style="font-weight:900">تکمیل گزارش</a>
      </div>
    <?php endif; ?>
<?php
}

function panel_end(array $extraJs = []): void
{
    $ctx = $GLOBALS['_panel_ctx'] ?? null;
    ?>
    </main>
  </div>
</div>
<?php if ($ctx):
    // نوار ناوبری پایین (فقط موبایل) — ۵ آیتم اصلی
    $bn = array_slice($ctx['items']['main'], 0, 5);
?>
<nav class="bottom-nav">
  <?php foreach ($bn as [$key,$label,$ic,$href]):
    $cnt = $key==='messages' ? $ctx['msg'] : 0; ?>
  <a href="<?= url($href) ?>" class="bn-item <?= $ctx['active']===$key?'active':'' ?>">
    <span class="bn-ico"><?= icon($ic,22) ?><?php if($cnt>0):?><span class="bn-dot"></span><?php endif;?></span>
    <span class="bn-lbl"><?= e($label) ?></span>
  </a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
<!-- notifications drawer -->
<div class="modal-backdrop" id="notifModal">
  <div class="modal">
    <div class="modal-head"><h3><?= icon('bell',20) ?> اعلان‌ها</h3><button class="modal-close" data-close><?= icon('close',18) ?></button></div>
    <div id="notifList"><div class="empty-state"><span class="spinner"></span></div></div>
  </div>
</div>
<script>
  window.NOTIF_URL = window.NOTIF_URL || '<?= url('api/notifications.php') ?>';
  window.NOTIF_READ_URL = window.NOTIF_READ_URL || '<?= url('api/notifications.php?read=1') ?>';
  window.PANEL_LIVE_URL = window.PANEL_LIVE_URL || '<?= url('api/live.php') ?>';
</script>
<?php
  $finalJs = ['panel.js'];
  page_foot(array_merge($finalJs, $extraJs));
}
