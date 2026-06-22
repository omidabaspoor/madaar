<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/reporting.php';
require_once __DIR__ . '/../includes/review_scheduler.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('student');
user_mood_schema_ready();
$u = get_user((int)current_user()['id']);
$webNotifEnabled = advisor_feature_enabled((int)($u['advisor_id'] ?? 0), 'web_notifications');
$todayMood = current_mood_key($u);

$todayTasks = student_today_tasks((int)$u['id']);
$week = student_week_stats((int)$u['id']);
$chart = student_week_chart((int)$u['id']);
if (advisor_feature_enabled((int)($u['advisor_id'] ?? 0), 'review_enabled')) {
    review_due_notifications((int)$u['id']);
    $reviewCounts = review_counts((int)$u['id']);
} else { $reviewCounts = ['due'=>0,'upcoming'=>0,'done'=>0]; }
$pendingReports = report_pending_items((int)$u['id']);
$todayIdx = persian_day_index(date('Y-m-d'));
$todayScore = array_sum(array_map(fn($t)=>task_score($t), $todayTasks));
$todayDone = count(array_filter($todayTasks, fn($t)=>task_status($t)==='full'));
$todayPartial = count(array_filter($todayTasks, fn($t)=>task_status($t)==='partial'));
$todayTotal = count($todayTasks);
$todayPct = $todayTotal ? round($todayScore/$todayTotal*100) : 0;

$hour = (int)date('H');
$greet = $hour<12 ? 'صبح بخیر' : ($hour<17 ? 'ظهر بخیر' : ($hour<20 ? 'عصر بخیر' : 'شب بخیر'));
$first = explode(' ', (string)$u['full_name'])[0];

$maxBar = max(1, max(array_map(fn($c)=>max((float)$c['total'], (float)$c['done']),$chart))); 

require_once __DIR__ . '/../includes/meetings.php';
meetings_schema_ready();
$todayMeetings = [];
try {
    $todayMeetings = db()->query('SELECT * FROM consultation_sessions WHERE student_id='.(int)$u['id'].' AND session_date="'.date('Y-m-d').'" AND status="scheduled"')->fetchAll();
} catch (Throwable $e) {
    error_log($e->getMessage());
}

panel_start('خانه', jalali_date('now'), 'student', 'dashboard', ['student.css']);
?>

<?php foreach($todayMeetings as $tm): ?>
<div class="panel alert-pulse" style="background: linear-gradient(135deg, #1c2823, #0c1512); border: 2px solid var(--gold); border-radius: 18px; padding: 18px; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; box-shadow: 0 0 20px rgba(178,148,95,0.15); animation: pulse Glow 2s infinite alternate;">
  <div style="display: flex; align-items: center; gap: 14px;">
    <div style="background: rgba(178, 148, 95, 0.15); color: var(--gold-light); width: 46px; height: 46px; border-radius: 50%; display: grid; place-items: center; font-size: 1.3rem;">
      🔔
    </div>
    <div>
      <span style="font-size: 11px; color: var(--gold-light); font-weight: 900; text-transform: uppercase;">هشدار زنگ جلسه امروز 📅</span>
      <h3 style="font-size: 15px; font-weight: 900; color: var(--text-1); margin-top: 3px;">جلسه مشاوره: «<?= e($tm['title']) ?>»</h3>
      <p class="muted" style="font-size: 12.5px; margin-top: 2px;">امروز <?= $tm['session_time'] ? ('ساعت <b>' . fa_num(substr((string)$tm['session_time'], 0, 5)) . '</b>') : '<b>ساعت توافقی</b>' ?> برگزار خواهد شد. لطفاً آماده باشید!</p>
    </div>
  </div>
  <a href="<?= url('student/meetings.php') ?>" class="btn btn-gold btn-sm" style="font-weight: 900;">ورود به اتاق جلسه</a>
</div>
<?php endforeach; ?>

<!-- greeting -->
<div class="greet-card reveal in">
  <div class="between" style="align-items:flex-start">
    <div>
      <div class="gc-sub"><?= e($greet) ?> 👋</div>
      <h2><?= e($first) ?> عزیز</h2>
    </div>
    <span class="badge" style="background:rgba(12,21,18,.25);color:#0c1512;border:none"><?= icon('fire',15) ?> <?= fa_num($u['streak']) ?> روز پیاپی</span>
  </div>
  <div class="greet-progress">
    <div class="between" style="font-size:.85rem;font-weight:700;margin-bottom:6px"><span>پیشرفت امروز</span><span><?= fa_num($todayPct) ?>٪ · کامل <?= fa_num($todayDone) ?> / ناقص <?= fa_num($todayPartial) ?></span></div>
    <div class="progress"><span data-w="<?= $todayPct ?>" style="width:0"></span></div>
  </div>
  <div class="mood-wrap">
    <span class="mood-label">حال امروزت چطوره؟</span>
    <div class="mood-row" id="moodRow">
      <?php foreach (['😄'=>'happy','🙂'=>'ok','😐'=>'meh','😴'=>'tired','😣'=>'stressed'] as $emo=>$key): ?>
      <button class="mood-btn <?= $todayMood===$key?'active':'' ?>" data-mood="<?= $key ?>" type="button"><?= $emo ?></button>
      <?php endforeach; ?>
    </div>
  </div>
</div>




<?php if($webNotifEnabled): ?>
<div class="panel notif-permission mb-4" style="display:none">
  <div><b>🔔 اعلان مرورها را فعال کن</b><span>برای اینکه وب‌اپ زمان مرورهای مهم را روی گوشی یادآوری کند، اجازه اعلان را فعال کن.</span></div>
  <button class="btn btn-gold btn-sm" type="button" data-notif-enable>فعال‌سازی اعلان</button>
</div>
<script>if('Notification' in window && Notification.permission==='default') document.currentScript.previousElementSibling.style.display='flex';</script>
<?php endif; ?>

<?php if(($reviewCounts['due'] ?? 0) > 0): ?>
<div class="panel review-due-banner reveal" data-d="1" style="margin-bottom:18px">
  <div><b>🔁 وقت مرور فاصله‌دار</b><span><?= fa_num($reviewCounts['due']) ?> مبحث برای مرور آماده است.</span></div>
  <a class="btn btn-gold btn-sm" href="<?= url('student/reviews.php') ?>">مشاهده مرورها</a>
</div>
<?php endif; ?>



<!-- ==== TODAY'S TASKS (most important — first) ==== -->
<div class="panel reveal" data-d="1" style="margin-bottom:18px">
  <div class="panel-head">
    <h3><?= icon('calendar',20) ?> تسک‌های امروز · <?= e(DAY_NAMES[$todayIdx]) ?></h3>
    <a href="<?= url('student/plan.php') ?>" class="btn btn-ghost btn-sm">هفته کامل <?= icon('arrow-left',15) ?></a>
  </div>
  <?php if (!$todayTasks): ?>
    <div class="empty-state"><div class="es-ico"><?= icon('inbox',30) ?></div><p>برای امروز تسکی ثبت نشده 🌱</p><p class="muted" style="font-size:.84rem">از یک روز استراحت لذت ببر یا برنامه‌ی کامل هفته را ببین.</p></div>
  <?php else: ?>
    <?php $todayMissed = count(array_filter($todayTasks, fn($t)=>task_status($t)==='missed')); ?>
    <?php if (($todayDone + $todayPartial + $todayMissed) < $todayTotal): ?>
    <div class="hint-bar"><?= icon('info',16) ?><span>از سه دکمه‌ی <b>✓ کامل</b>، <b>● ناقص</b> یا <b>× عدم اجرا</b> استفاده کن. <?= fa_num($todayTotal-$todayDone-$todayPartial-$todayMissed) ?> تسک مونده!</span></div>
    <?php else: ?>
    <div class="hint-bar done"><?= icon('check-circle',16) ?><span>عالیه! همه‌ی تسک‌های امروز رو زدی 🎉</span></div>
    <?php endif; ?>
    <div class="task-list" id="todayTasks">
      <?php foreach ($todayTasks as $t): ?>
        <?= student_task_card($t) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ==== compact stats ==== -->
<div class="stat-cards">
  <div class="panel stat reveal" data-d="2"><span class="icon-tile"><?= icon('check-circle',24) ?></span><div><div class="v"><?= fa_num($week['done_display']) ?>/<?= fa_num($week['total']) ?></div><div class="k">تسک‌های هفته</div></div></div>
  <div class="panel stat reveal" data-d="3"><span class="icon-tile sage"><?= icon('target',24) ?></span><div><div class="v"><?= fa_num($week['percent']) ?>٪</div><div class="k">پیشرفت هفته</div></div></div>
  <div class="panel stat reveal" data-d="4"><span class="icon-tile" style="background:rgba(217,178,95,.14);color:var(--warn)"><?= icon('fire',24) ?></span><div><div class="v"><?= fa_num($u['streak']) ?></div><div class="k">استریک</div></div></div>
  <div class="panel stat reveal" data-d="5"><span class="icon-tile sage"><?= icon('trophy',24) ?></span><div><div class="v"><?= fa_num($todayPct) ?>٪</div><div class="k">امروز</div></div></div>
</div>

<!-- ==== week chart ==== -->
<div class="panel reveal" data-d="3">
  <div class="panel-head"><h3><?= icon('bar',20) ?> نمودار هفته</h3>
    <a href="<?= url('student/progress.php') ?>" class="btn btn-ghost btn-sm">گزارش <?= icon('arrow-left',15) ?></a></div>
  <div class="barchart">
    <?php foreach ($chart as $i=>$c): $h = round($c['done']/$maxBar*100); ?>
    <div class="bcol">
      <div class="bar <?= $i===$todayIdx?'gold':'' ?>" data-h="<?= $h ?>" style="height:0" data-tip="<?= fa_num($c['done_display']) ?>/<?= fa_num($c['total']) ?>"></div>
      <span class="blbl"><?= mb_substr($c['day'],0,3) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/_modals.php'; ?>

<script>
  window.API_TASKS = '<?= url('api/tasks.php') ?>';
  window.NOTIF_URL = '<?= url('api/notifications.php') ?>';
  window.NOTIF_READ_URL = '<?= url('api/notifications.php?read=1') ?>';
  window.API_MOOD = '<?= url('api/mood.php') ?>';

  // ===== لایو داشبورد — آپدیت خودکار هر ۱۰ ثانیه =====
  (function(){
    let lastHash = '';
    async function liveRefresh(){
      try {
        const res = await fetch(window.API_TASKS + '?action=live_status&_t=' + Date.now(), {headers:{'X-CSRF-Token':window.MADAR?.csrf||''}});
        const d = await res.json();
        if(!d.ok) return;
        const hash = JSON.stringify(d);
        if(hash === lastHash) return; // هیچ تغییری نبوده
        lastHash = hash;
        // آپدیت streak
        const streakEl = document.querySelector('.badge');
        if(streakEl && d.streak !== undefined) {
          const fa = window.faNum || (n=>String(n));
          streakEl.innerHTML = streakEl.innerHTML.replace(/[\u06F0-\u06F9\d]+(?=\s*روز)/, fa(d.streak));
        }
        // آپدیت progress bar
        if(d.today_pct !== undefined) {
          const bar = document.querySelector('.greet-progress .progress > span');
          if(bar) bar.style.width = d.today_pct + '%';
          const lbl = document.querySelector('.greet-progress .between span:last-child');
          if(lbl && d.today_done !== undefined) {
            const fa = window.faNum || (n=>String(n));
            lbl.textContent = fa(d.today_pct) + '٪ · کامل ' + fa(d.today_done) + ' / ناقص ' + fa(d.today_partial||0);
          }
        }
        // آپدیت اعلان badge
        const dot = document.querySelector('#notifBtn .dot');
        if(d.unread_notif > 0 && !dot) {
          const nd = document.createElement('span'); nd.className='dot';
          document.getElementById('notifBtn')?.appendChild(nd);
        } else if(d.unread_notif <= 0 && dot) dot.remove();
        // آپدیت پیام badge
        const msgDot = document.querySelector('.tb-btn[data-tip="پیام‌ها"] .dot');
        if(d.unread_msg > 0 && !msgDot) {
          const md = document.createElement('span'); md.className='dot';
          document.querySelector('.tb-btn[data-tip="پیام‌ها"]')?.appendChild(md);
        } else if(d.unread_msg <= 0 && msgDot) msgDot.remove();
      } catch(_){}
    }
    setInterval(liveRefresh, 10000);
    // وقتی visible شد فوری چک کن
    document.addEventListener('visibilitychange',()=>{ if(!document.hidden) setTimeout(liveRefresh, 800); });
  })();
</script>
<?php
panel_end(['student.js']);

/* ---- student task card markup ---- */
function student_task_card(array $t): string {
    $status = task_status($t);
    $color = $t['subj_color'] ?? '#6b8872';
    $meta = [];
    if ($t['target_count']!==null) {
        $dc = (int)$t['done_count']; $tc=(int)$t['target_count'];
        $meta[] = '<span class="st-prog st-prog-count" data-unit="'.e($t['target_unit']).'">'.fa_num($dc).'/'.fa_num($tc).' '.e($t['target_unit']).'</span>';
    } elseif ($t['duration_min']) {
        $meta[] = '<span class="st-prog">'.fa_num($t['duration_min']).' دقیقه</span>';
    }
    if (isset($t['course_percent']) && $t['course_percent'] !== null) $meta[] = '<span class="st-prog st-course">'.fa_num((int)$t['course_percent']).'٪ کورس</span>';
    else $meta[] = '<span class="st-prog st-course"></span>';
    $typeLabel = TASK_TYPES[$t['task_type']]['label'] ?? $t['task_type'];
    $subjTag = $t['subj_name'] ? '<span class="st-subj" style="background:'.e($color).'22;color:'.e($color).'">'.e($t['subj_name']).'</span>' : '';
    $feel = feeling_info($t['student_feeling'] ?? null);
    $feelTag = $feel ? '<span class="st-feel">'.$feel['emoji'].' '.e($feel['label']).'</span>' : '';
    $fb = $t['advisor_feedback'] ? '<div class="st-feedback"><span class="ico">'.icon('message',15).'</span><span><b>بازخورد مشاور:</b> '.e($t['advisor_feedback']).'</span></div>' : '';
    $noteBlock = $t['student_note']
      ? '<div class="st-note-box"><span class="st-note-text" data-raw="'.e($t['student_note']).'">'.icon('note',13).' '.e($t['student_note']).'</span> <button class="st-note-edit" data-note="'.(int)$t['id'].'">ویرایش</button></div>'
      : '<button class="st-note-btn" data-note="'.(int)$t['id'].'">'.icon('note',14).' افزودن یادداشت</button>';
    $actions = '<div class="task-actions" aria-label="ثبت وضعیت">'
      .'<button type="button" class="task-action full '.($status==='full'?'active':'').'" data-status-action="full" title="اجرای کامل">✓</button>'
      .'<button type="button" class="task-action partial '.($status==='partial'?'active':'').'" data-status-action="partial" title="اجرای ناقص">●</button>'
      .'<button type="button" class="task-action missed '.($status==='missed'?'active':'').'" data-status-action="missed" title="عدم اجرا">×</button>'
      .'</div>';
    return '<div class="s-task '.($status==='full'?'done':$status).'" data-id="'.(int)$t['id'].'" data-status="'.$status.'" data-type="'.e($t['task_type']).'" data-target="'.($t['target_count']!==null?(int)$t['target_count']:'').'" data-target-unit="'.e($t['target_unit'] ?? '').'" data-done="'.(int)$t['done_count'].'" data-course="'.(isset($t['course_percent'])&&$t['course_percent']!==null?(int)$t['course_percent']:'').'" data-feeling="'.e($t['student_feeling'] ?? '').'">'
      .$actions
      .'<div class="st-body">'
        .'<div class="st-title"><span class="st-title-main">'.e($t['title']).'</span> '.$subjTag.' <span class="st-status-text">'.e(TASK_STATUS_LABELS[$status] ?? '').'</span></div>'
        .'<div class="st-meta"><span class="badge" style="font-size:.7rem;padding:2px 8px">'.e($typeLabel).'</span>'.implode('',$meta).$feelTag.(!empty($t['source'])?'<span class="st-src">'.icon('book',12).' '.e($t['source']).'</span>':'').'</div>'
        .$noteBlock
        .$fb
      .'</div>'
    .'</div>';
}
