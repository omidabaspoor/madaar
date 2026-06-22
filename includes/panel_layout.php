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
            ['dashboard','داشبورد کلان','home','admin/dashboard.php'],
            is_chief_advisor() ? ['advisors','مدیریت مشاوران','users','admin/advisors.php'] : null,
            ['students','دانش‌آموزان من','users','admin/students.php'],
            ['plans','برنامه‌ریزی هفتگی','calendar','admin/plans.php'],
            ['meetings','برنامه‌ریزی جلسات','calendar','admin/schedule_meeting.php'],
            ['exams','آزمون‌ساز آنلاین','clipboard','admin/exams.php'],
            ['student_reports','گزارش‌های روزانه/حرفه‌ای','edit','admin/student_reports.php'],
            ['internal_exam','کارنامه‌ها و تحلیل آزمون','chart','admin/internal_exam_reports.php'],
            ['messages','پیام‌ها و چت‌باکس','message','admin/messages.php'],
        ]),
        'other' => [
            ['guide','راهنمای پنل','book','admin/guide.php'],
            ['achievements','مدیریت دستاوردها','trophy','admin/achievements.php'],
            ['settings','تنظیمات سامانه','settings','admin/settings.php'],
        ],
    ] : [
        'main' => [
            ['dashboard','خانه','home','student/dashboard.php'],
            ['plan','برنامه هفتگی','calendar','student/plan.php'],
            ['reports','گزارش حرفه‌ای','edit','student/reports.php?type=weekly'],
            ['meetings','جلسات مشاوره','calendar','student/meetings.php'],
            ['exams','آزمون‌های آنلاین','clipboard','student/exams.php'],
            ['exam_analyses','تحلیل آزمون‌ها','chart','student/exam_analyses.php'],
            ['messages','پیام با مشاور','message','student/messages.php'],
            ['progress','نمودار پیشرفت','chart','student/progress.php'],
            ['reviews','برنامه مرور','repeat','student/reviews.php'],
        ],
        'other' => [
            ['guide','راهنمای پنل','book','student/guide.php'],
            ['achievements','دستاوردها','trophy','student/achievements.php'],
            ['profile','پروفایل من','user','student/profile.php'],
        ],
    ];

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
      <a href="<?= url('admin/guide.php') ?>" class="btn btn-ghost btn-sm flex items-center gap-1.5" style="font-weight: 900; border: 1px solid var(--gold); border-radius: 12px; height: 38px; color: var(--gold-light);">
        📖 <span>راهنما</span>
      </a>
        <?php else: ?>
      <a href="<?= url('student/guide.php') ?>" class="btn btn-ghost btn-sm flex items-center gap-1.5" style="font-weight: 900; border: 1px solid var(--sage); border-radius: 12px; height: 38px; color: var(--sage-light);">
        📖 <span>راهنما</span>
      </a>
        <?php endif; ?>
        <a href="<?= url($role==='admin'?'admin/messages.php':'student/messages.php') ?>" class="tb-btn" data-tip="پیام‌ها"><?= icon('message',20) ?><?php if($msgCount>0):?><span class="dot"></span><?php endif;?></a>
        <button class="tb-btn" id="notifBtn" data-tip="اعلان‌ها"><?= icon('bell',20) ?><?php if($notifCount>0):?><span class="dot"></span><?php endif;?></button>
        <span class="badge badge-sage" style="padding:8px 12px"><?= icon('fire',15) ?> <?= fa_num($u['streak'] ?? 0) ?> روز</span>
      </div>
    </header>
    <main class="content">
    <?php foreach (get_flashes() as $f): ?>
      <div class="alert alert-<?= $f['type']==='success'?'success':($f['type']==='error'?'error':'info') ?>" style="margin-bottom:18px"><?= icon('info',18) ?><span><?= e($f['msg']) ?></span></div>
    <?php endforeach; ?>
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
</script>
<?php
  $finalJs = ['panel.js'];
  page_foot(array_merge($finalJs, $extraJs));
}
