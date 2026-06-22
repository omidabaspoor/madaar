<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('student');
$u = current_user();

$weekStart = isset($_GET['week']) ? week_saturday($_GET['week']) : week_saturday();
$st = db()->prepare('SELECT * FROM plans WHERE student_id=? AND week_start=? AND status="published" LIMIT 1');
$st->execute([$u['id'], $weekStart]);
$plan = $st->fetch();

$prevWeek = date('Y-m-d', strtotime($weekStart.' -7 day'));
$nextWeek = date('Y-m-d', strtotime($weekStart.' +7 day'));
$todayIdx = persian_day_index(date('Y-m-d'));

$tasksByDay = [];
if ($plan) {
    $rows = db()->prepare('SELECT t.*, s.color subj_color, s.name subj_name FROM tasks t LEFT JOIN subjects s ON s.id=t.subject_id WHERE t.plan_id=? ORDER BY t.day_index, t.unit_index, t.sort_order, t.id');
    $rows->execute([$plan['id']]);
    foreach ($rows->fetchAll() as $t) { $tasksByDay[(int)$t['day_index']][] = $t; }
}

panel_start('برنامه من', $plan ? (jalali_date($weekStart) . ' تا ' . jalali_date(date('Y-m-d', strtotime($weekStart.' +6 day')))) : '', 'student', 'plan', ['student.css']);
?>
<div class="between mb-4 wrap gap-3">
  <div class="week-nav flex gap-2" style="align-items:center">
    <a href="?week=<?= $prevWeek ?>" class="btn btn-ghost btn-icon" data-tip="هفته قبل"><?= icon('chevron-right',18) ?></a>
    <span class="fw-700"><?= jalali_date($weekStart) ?></span>
    <a href="?week=<?= $nextWeek ?>" class="btn btn-ghost btn-icon" data-tip="هفته بعد"><?= icon('chevron-left',18) ?></a>
  </div>
  <?php if ($plan): $pp = plan_progress((int)$plan['id']); ?>
  <div class="flex gap-2 wrap">
    <a href="<?= url('student/plan_pdf.php?week='.$weekStart) ?>" target="_blank" class="btn btn-gold btn-sm"><?= icon('paperclip',14) ?> چاپ / ذخیره PDF</a>
    <span class="badge badge-sage"><?= icon('target',14) ?> <?= fa_num($pp['percent']) ?>٪ تکمیل (<?= fa_num($pp['done_display']) ?>/<?= fa_num($pp['total']) ?>)</span>
  </div>
  <?php endif; ?>
</div>

<?php if (!$plan): ?>
  <div class="panel"><div class="empty-state"><div class="es-ico"><?= icon('calendar',34) ?></div><p>برای این هفته برنامه‌ای منتشر نشده</p><p class="muted" style="font-size:.85rem">مشاورت به‌زودی برنامه‌ات را آماده می‌کند 🌱</p></div></div>
<?php else: ?>

<!-- day tabs -->
<div class="day-tabs">
  <?php foreach (DAY_NAMES as $di=>$dn):
    $cnt = count($tasksByDay[$di] ?? []);
    $isToday = $di===$todayIdx; ?>
  <button class="day-tab <?= $isToday?'active today':'' ?>" data-day="<?= $di ?>">
    <?= e($dn) ?>
    <span class="dt-num"><?= $cnt?fa_num($cnt).' تسک':'—' ?><?= $isToday?' · امروز':'' ?></span>
  </button>
  <?php endforeach; ?>
</div>

<?php foreach (DAY_NAMES as $di=>$dn): ?>
<div data-day-panel="<?= $di ?>" class="<?= $di===$todayIdx?'':'hidden' ?>">
  <?php $dayTasks = $tasksByDay[$di] ?? []; if (!$dayTasks): ?>
    <div class="panel"><div class="empty-state" style="padding:36px"><div class="es-ico"><?= icon('inbox',28) ?></div>برای <?= e($dn) ?> تسکی نیست</div></div>
  <?php else: ?>
  <div class="task-list">
    <?php foreach ($dayTasks as $t): ?>
      <?= student_task_card($t) ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/_modals.php'; ?>
<script>
  window.API_TASKS='<?= url('api/tasks.php') ?>';
  window.NOTIF_URL='<?= url('api/notifications.php') ?>';
  window.NOTIF_READ_URL='<?= url('api/notifications.php?read=1') ?>';
</script>
<?php panel_end(['student.js']); ?>

<?php
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
?>
