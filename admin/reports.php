<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('advisor','admin');
user_mood_schema_ready();
$u = get_user((int)current_user()['id']);
task_status_schema_ready();

$studentId = (int)($_GET['student'] ?? 0);

if (!$studentId) {
    $students = advisor_students((int)$u['id'], 'active');
    usort($students, fn($a,$b)=> ((float)$b['done_tasks']<=>(float)$a['done_tasks']));
    panel_start('گزارش‌ها', 'رتبه‌بندی و تحلیل دقیق', 'admin', 'reports', ['student.css']);
    ?>
    <div class="panel">
      <div class="panel-head"><h3><?= icon('trophy',20) ?> رتبه‌بندی دانش‌آموزان</h3></div>
      <?php if(!$students):?><div class="empty-state"><div class="es-ico"><?= icon('users',30) ?></div>دانش‌آموز فعالی نیست</div>
      <?php else:?>
      <table class="tbl">
        <thead><tr><th>#</th><th>دانش‌آموز</th><th>امتیاز تسک</th><th>کامل/ناقص/قرمز</th><th>استریک</th><th>پیشرفت</th><th></th></tr></thead>
        <tbody>
        <?php foreach($students as $i=>$s): $pct=$s['total_tasks']?round(((float)$s['done_tasks'])/$s['total_tasks']*100):0; ?>
          <tr>
            <td><span class="badge <?= $i<3?'badge-gold':'' ?>"><?= fa_num($i+1) ?></span></td>
            <td><div class="u-row"><span class="u-ava <?= $i==0?'gold':'' ?>"><?= e(avatar_letters($s['full_name'])) ?></span><span style="font-weight:700"><?= e($s['full_name']) ?></span></div></td>
            <td><?= fa_num(score_display((float)$s['done_tasks'])) ?> / <?= fa_num($s['total_tasks']) ?></td>
            <td><span class="mini-status ok">✓ <?= fa_num($s['full_tasks']) ?></span> <span class="mini-status partial">● <?= fa_num($s['partial_tasks']) ?></span> <span class="mini-status missed">× <?= fa_num($s['missed_tasks']) ?></span></td>
            <td><span class="badge badge-gold"><?= icon('fire',13) ?> <?= fa_num($s['streak']) ?></span></td>
            <td style="min-width:150px"><div class="between" style="gap:10px"><div class="progress" style="flex:1"><span data-w="<?= $pct ?>" style="width:0"></span></div><span style="font-size:.82rem;font-weight:700"><?= fa_num($pct) ?>٪</span></div></td>
            <td><a href="?student=<?= (int)$s['id'] ?>" class="btn btn-ghost btn-sm">جزئیات <?= icon('arrow-left',14) ?></a></td>
          </tr>
        <?php endforeach;?>
        </tbody>
      </table>
      <?php endif;?>
    </div>
    <?php panel_end(); exit;
}

$student = get_user($studentId);
if (!$student || $student['role']!=='student') { flash('error','دانش‌آموز یافت نشد'); redirect('admin/reports.php'); }
auto_mark_missed_tasks($studentId);

$weekStart = isset($_GET['week']) ? week_saturday($_GET['week']) : week_saturday();
$prevWeek = date('Y-m-d', strtotime($weekStart.' -7 day'));
$nextWeek = date('Y-m-d', strtotime($weekStart.' +7 day'));
$p = db()->prepare('SELECT * FROM plans WHERE student_id=? AND week_start=? LIMIT 1');
$p->execute([$studentId,$weekStart]); $plan=$p->fetch();

$tasksByDay = []; $redTasks=[]; $stats=['total'=>0,'full'=>0,'partial'=>0,'missed'=>0,'score'=>0.0,'avg_course'=>null];
if ($plan) {
    $rows = db()->prepare('SELECT t.*, s.name subj_name, s.color subj_color FROM tasks t LEFT JOIN subjects s ON s.id=t.subject_id WHERE t.plan_id=? ORDER BY t.day_index,t.unit_index,t.id');
    $rows->execute([$plan['id']]);
    $all = $rows->fetchAll();
    $courses=[];
    $planPublishedBase = (string)($plan['published_at'] ?: ($plan['updated_at'] ?: $plan['created_at']));
    $effParts = [
      $plan['week_start'],
      date('Y-m-d', strtotime($planPublishedBase)),
      date('Y-m-d', strtotime((string)($student['activated_at'] ?: $student['created_at']))),
    ];
    $effectiveStart = max($effParts);
    foreach ($all as $t) {
        $taskDate = date('Y-m-d', strtotime($plan['week_start'].' +'.(int)$t['day_index'].' day'));
        $tasksByDay[(int)$t['day_index']][] = $t;
        if ($taskDate < $effectiveStart) continue; // قبل از انتشار/ثبت‌نام، در آمار مشاور حساب نشود
        $stt = task_status($t); $stats['total']++; $stats['score'] += task_score($t);
        if (isset($stats[$stt])) $stats[$stt]++;
        if ($t['course_percent'] !== null) $courses[]=(int)$t['course_percent'];
        if ($stt==='missed') $redTasks[]=$t;
    }
    $stats['avg_course'] = $courses ? round(array_sum($courses)/count($courses)) : null;
}
$stats['pct'] = $stats['total'] ? round($stats['score']/$stats['total']*100) : 0;
$chart = student_week_chart($studentId);
$maxBar = max(1, max(array_map(fn($c)=>max((float)$c['total'], (float)$c['done']),$chart))); 

panel_start('گزارش دانش‌آموز', $student['full_name'], 'admin', 'reports', ['student.css']);
?>
<div class="between mb-4 wrap gap-3">
  <div class="builder-student flex gap-3" style="align-items:center">
    <a href="<?= url('admin/reports.php') ?>" class="btn btn-ghost btn-icon"><?= icon('arrow-right',18) ?></a>
    <span class="u-ava"><?= e(avatar_letters($student['full_name'])) ?></span>
    <div><div style="font-weight:800"><?= e($student['full_name']) ?></div>
      <div class="muted" style="font-size:.78rem"><?= e($student['field'] ?: '') ?> · <?= icon('fire',12) ?> <?= fa_num($student['streak']) ?> روز
      <?php $m = current_mood_info($student); if($m): ?> · حال امروز: <span style="color:<?= e($m['color']) ?>"><?= $m['emoji'] ?> <?= e($m['label']) ?></span><?php endif; ?>
      </div></div>
  </div>
  <div class="week-nav flex gap-2" style="align-items:center">
    <a href="?student=<?= $studentId ?>&week=<?= $prevWeek ?>" class="btn btn-ghost btn-icon"><?= icon('chevron-right',18) ?></a>
    <span class="fw-700"><?= jalali_date($weekStart) ?></span>
    <a href="?student=<?= $studentId ?>&week=<?= $nextWeek ?>" class="btn btn-ghost btn-icon"><?= icon('chevron-left',18) ?></a>
  </div>
</div>

<div class="stat-cards mb-4">
  <div class="panel stat"><span class="icon-tile sage">✓</span><div><div class="v"><?= fa_num($stats['full']) ?></div><div class="k">کامل</div></div></div>
  <div class="panel stat"><span class="icon-tile">●</span><div><div class="v"><?= fa_num($stats['partial']) ?></div><div class="k">ناقص (نیم امتیاز)</div></div></div>
  <div class="panel stat"><span class="icon-tile" style="background:rgba(217,116,116,.16);color:#ff9a9a">×</span><div><div class="v"><?= fa_num($stats['missed']) ?></div><div class="k">تسک قرمز</div></div></div>
  <div class="panel stat"><span class="icon-tile sage"><?= icon('target',22) ?></span><div><div class="v"><?= fa_num($stats['pct']) ?>٪</div><div class="k">پیشرفت وزنی</div></div></div>
  <div class="panel stat"><span class="icon-tile" style="background:var(--gold-glass);color:var(--gold-light)">٪</span><div><div class="v"><?= $stats['avg_course']!==null?fa_num($stats['avg_course']).'٪':'—' ?></div><div class="k">میانگین کورس</div></div></div>
</div>

<div class="panel reveal mb-4">
  <div class="panel-head"><h3><?= icon('bar',20) ?> فعالیت هفته</h3>
    <div class="flex gap-2 wrap"><a href="<?= url('admin/student_reports.php?student='.$studentId.'&type=daily') ?>" class="btn btn-ghost btn-sm"><?= icon('chart',15) ?> گزارش حرفه‌ای</a><a href="<?= url('admin/plan_builder.php?student='.$studentId.'&week='.$weekStart) ?>" class="btn btn-gold btn-sm"><?= icon('edit',15) ?> ویرایش برنامه</a></div></div>
  <div class="barchart">
    <?php foreach($chart as $c): $hh=round($c['done']/$maxBar*100); ?>
    <div class="bcol"><div class="bar gold" data-h="<?= $hh ?>" style="height:0" data-tip="امتیاز <?= fa_num($c['done_display']) ?>/<?= fa_num($c['total']) ?>"></div><span class="blbl"><?= mb_substr($c['day'],0,3) ?></span></div>
    <?php endforeach;?>
  </div>
</div>

<div class="between mb-4 wrap gap-2">
  <button class="btn btn-ghost red-modal-btn" type="button" data-modal="redTasksModal">× تسک‌های قرمز <span><?= fa_num(count($redTasks)) ?></span></button>
</div>

<div class="modal-backdrop" id="redTasksModal">
  <div class="modal red-modal">
    <div class="modal-head">
      <h3>× تسک‌های قرمز</h3>
      <button class="modal-close" data-close><?= icon('close',18) ?></button>
    </div>
    <div class="red-modal-top">
      <a href="?student=<?= $studentId ?>&week=<?= $prevWeek ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-right',15) ?> هفته قبل</a>
      <div class="red-range"><?= jalali_date($weekStart) ?> تا <?= jalali_date(date('Y-m-d', strtotime($weekStart.' +6 day'))) ?></div>
      <a href="?student=<?= $studentId ?>&week=<?= $nextWeek ?>" class="btn btn-ghost btn-sm">هفته بعد <?= icon('chevron-left',15) ?></a>
    </div>
    <?php if(!$redTasks): ?>
      <div class="empty-state" style="padding:34px"><div class="es-ico">✓</div>برای این بازه تسک قرمزی ثبت نشده</div>
    <?php else: ?>
    <div class="compact-task-list red-modal-list">
      <?php foreach($redTasks as $t): ?>
        <div class="compact-task red"><b><?= e(DAY_NAMES[(int)$t['day_index']]) ?> · <?= e($t['title']) ?></b><span><?= e(TASK_TYPES[$t['task_type']]['label']??'') ?><?= $t['source']?' · '.e($t['source']):'' ?></span></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if(!$plan):?>
  <div class="panel"><div class="empty-state"><div class="es-ico"><?= icon('calendar',30) ?></div>برنامه‌ای برای این هفته نیست</div></div>
<?php else: foreach(DAY_NAMES as $di=>$dn): $dt=$tasksByDay[$di]??[]; if(!$dt) continue; ?>
<div class="panel reveal mb-4">
  <div class="panel-head"><h3><?= e($dn) ?></h3><span class="badge">امتیاز <?= fa_num(score_display(array_sum(array_map(fn($x)=>task_score($x),$dt)))) ?>/<?= fa_num(count($dt)) ?></span></div>
  <div class="task-list">
    <?php foreach($dt as $t): $stt=task_status($t); $fi=feeling_info($t['student_feeling']??null); ?>
    <div class="s-task <?= $stt==='full'?'done':e($stt) ?>">
      <span class="st-check icon-tile <?= $stt==='full'?'sage':'' ?>" style="width:38px;height:38px;border-radius:12px"><?= $stt==='full'?'✓':($stt==='partial'?'●':($stt==='missed'?'×':'…')) ?></span>
      <div class="st-body">
        <div class="st-title"><?= e($t['title']) ?>
          <?php if($t['subj_name']):?><span class="st-subj" style="background:<?= e($t['subj_color']) ?>22;color:<?= e($t['subj_color']) ?>"><?= e($t['subj_name']) ?></span><?php endif;?>
          <?= task_status_badge($t) ?>
        </div>
        <div class="st-meta">
          <span class="badge" style="font-size:.7rem"><?= e(TASK_TYPES[$t['task_type']]['label']??'') ?></span>
          <?php if($t['target_count']!==null):?><span class="st-prog"><?= fa_num($t['done_count']) ?>/<?= fa_num($t['target_count']) ?> <?= e($t['target_unit']) ?></span><?php endif;?>
          <?php if($t['course_percent']!==null):?><span class="st-prog"><?= fa_num($t['course_percent']) ?>٪ کورس</span><?php endif;?>
          <?php $bonusScore = task_score($t); if($bonusScore > 1): ?><span class="st-prog bonus-score">امتیاز <?= fa_num(number_format($bonusScore,2)) ?></span><?php endif;?>
          <?php if($t['completed_at']):?><span class="st-prog"><?= icon('check',12) ?> <?= jalali_date($t['completed_at'], true) ?></span><?php endif;?>
          <?php if($fi):?><span class="st-feel"><?= $fi['emoji'] ?> <?= e($fi['label']) ?></span><?php endif;?>
        </div>
        <?php if($t['student_note']):?><div class="st-prog" style="margin-top:5px">📝 <?= e($t['student_note']) ?></div><?php endif;?>
        <?php if($t['advisor_feedback']):?><div class="st-feedback"><span class="ico"><?= icon('message',15) ?></span><span><b>بازخورد شما:</b> <?= e($t['advisor_feedback']) ?></span></div><?php endif;?>
        <button class="st-note-btn" data-feedback="<?= (int)$t['id'] ?>" data-current="<?= e($t['advisor_feedback'] ?? '') ?>"><?= icon('message',14) ?> <?= $t['advisor_feedback']?'ویرایش بازخورد':'ثبت بازخورد' ?></button>
      </div>
    </div>
    <?php endforeach;?>
  </div>
</div>
<?php endforeach; endif; ?>

<div class="modal-backdrop" id="fbModal">
  <div class="modal" style="max-width:440px">
    <div class="modal-head"><h3><?= icon('message',18) ?> بازخورد مشاور</h3><button class="modal-close" data-close><?= icon('close',18) ?></button></div>
    <input type="hidden" id="fbTaskId">
    <textarea class="input" id="fbText" rows="4" placeholder="بازخوردت روی این تسک…"></textarea>
    <button class="btn btn-gold btn-block mt-4" id="saveFbBtn"><?= icon('check',16) ?> ارسال بازخورد</button>
  </div>
</div>
<script>
  window.API_TASKS='<?= url('api/tasks.php') ?>';
  window.NOTIF_URL='<?= url('api/notifications.php') ?>';
  window.NOTIF_READ_URL='<?= url('api/notifications.php?read=1') ?>';
  document.addEventListener('click',e=>{ const b=e.target.closest('[data-feedback]'); if(!b)return; document.getElementById('fbTaskId').value=b.dataset.feedback; document.getElementById('fbText').value=b.dataset.current||''; openModal('fbModal'); });
  document.getElementById('saveFbBtn')?.addEventListener('click', async function(){
    const id=document.getElementById('fbTaskId').value, fb=document.getElementById('fbText').value;
    try{ await api(window.API_TASKS,{method:'POST',body:{action:'feedback',id,feedback:fb}}); closeModal('fbModal'); toast('بازخورد ثبت شد','success'); setTimeout(()=>location.reload(),700); }
    catch(e){ toast(e.error||'خطا','error'); }
  });
</script>
<?php panel_end(); ?>
