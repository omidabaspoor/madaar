<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('advisor','admin');
$u = current_user();
$stats = advisor_stats((int)$u['id']);
$students = advisor_students((int)$u['id']);
$pending = array_filter($students, fn($s)=>$s['status']==='pending');
$topStudents = array_slice(array_filter($students, fn($s)=>$s['status']==='active'), 0, 6);

// نمودار ۸ هفته‌ای ساده (تعداد تسک‌های تکمیل‌شده)
$chart = [];
for ($i=7; $i>=0; $i--) {
    $cnt = rand(0,0); // واقعی از لاگ، اینجا از تسک‌های done در آن بازه
}
$weekChart = db()->query("SELECT day_index, COUNT(*) total, COALESCE(SUM(is_done),0) done FROM tasks GROUP BY day_index ORDER BY day_index")->fetchAll();
$chartData = array_fill(0,7,['total'=>0,'done'=>0]);
foreach ($weekChart as $w) { $chartData[(int)$w['day_index']] = ['total'=>(int)$w['total'],'done'=>(int)$w['done']]; }
$maxBar = max(1, max(array_map(fn($c)=>$c['total'], $chartData)));

panel_start('داشبورد', 'سلام ' . explode(' ', (string)$u['full_name'])[0] . '، خلاصه‌ی امروز', 'admin', 'dashboard');

require_once __DIR__ . '/../includes/meetings.php';
meetings_schema_ready();
$todayMeetings = [];
try {
    $todayMeetings = db()->query('SELECT s.*, u.full_name student_name FROM consultation_sessions s JOIN users u ON u.id=s.student_id WHERE s.advisor_id='.(int)$u['id'].' AND s.session_date="'.date('Y-m-d').'" AND s.status="scheduled"')->fetchAll();
} catch (Throwable $e) {
    error_log($e->getMessage());
}
?>

<?php foreach($todayMeetings as $tm): ?>
<div class="panel alert-pulse" style="background: linear-gradient(135deg, #1c2823, #0c1512); border: 2px solid var(--gold); border-radius: 18px; padding: 18px; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; box-shadow: 0 0 20px rgba(178,148,95,0.15); animation: pulse Glow 2s infinite alternate;">
  <div style="display: flex; align-items: center; gap: 14px;">
    <div style="background: rgba(178, 148, 95, 0.15); color: var(--gold-light); width: 46px; height: 46px; border-radius: 50%; display: grid; place-items: center; font-size: 1.3rem;">
      🔔
    </div>
    <div>
      <span style="font-size: 11px; color: var(--gold-light); font-weight: 900; text-transform: uppercase;">هشدار زنگ جلسه امروز 📅</span>
      <h3 style="font-size: 15px; font-weight: 900; color: var(--text-1); margin-top: 3px;">جلسه با: «<?= e($tm['student_name']) ?>»</h3>
      <p class="muted" style="font-size: 12.5px; margin-top: 2px;">موضوع: <b><?= e($tm['title']) ?></b> · امروز <?= $tm['session_time'] ? ('ساعت ' . fa_num(substr((string)$tm['session_time'], 0, 5))) : 'ساعت توافقی' ?> برگزار خواهد شد.</p>
    </div>
  </div>
  <a href="<?= url('admin/schedule_meeting.php') ?>" class="btn btn-gold btn-sm" style="font-weight: 900;">مدیریت جلسات</a>
</div>
<?php endforeach; ?>

<!-- stat cards -->
<div class="stat-cards">
  <div class="panel stat reveal in"><span class="icon-tile sage"><?= icon('users',26) ?></span><div><div class="v"><?= fa_num($stats['total']) ?></div><div class="k">کل دانش‌آموزان</div></div></div>
  <div class="panel stat reveal" data-d="1"><span class="icon-tile"><?= icon('check-circle',26) ?></span><div><div class="v"><?= fa_num($stats['active']) ?></div><div class="k">فعال</div></div></div>
  <div class="panel stat reveal" data-d="2"><span class="icon-tile" style="background:rgba(217,178,95,.14);color:var(--warn)"><?= icon('clock',26) ?></span><div><div class="v"><?= fa_num($stats['pending']) ?></div><div class="k">در انتظار تأیید</div></div></div>
  <div class="panel stat reveal" data-d="3"><span class="icon-tile sage"><?= icon('target',26) ?></span><div><div class="v"><?= fa_num($stats['rate']) ?>٪</div><div class="k">نرخ تکمیل تسک‌ها</div><div class="trend up">از <?= fa_num($stats['tasksTotal']) ?> تسک</div></div></div>
</div>

<div class="panel-grid cols-2">
  <!-- chart -->
  <div class="panel reveal" data-d="1">
    <div class="panel-head"><h3><?= icon('bar',20) ?> فعالیت هفتگی (همه دانش‌آموزان)</h3></div>
    <div class="barchart">
      <?php foreach (DAY_NAMES as $i=>$dn): $c=$chartData[$i]; $h=round($c['total']/$maxBar*100); $dh=$c['total']?round($c['done']/$maxBar*100):0; ?>
      <div class="bcol">
        <div style="width:100%;display:flex;flex-direction:column;justify-content:flex-end;height:100%;gap:2px">
          <div class="bar gold" data-h="<?= $dh ?>" style="height:0" data-tip="<?= fa_num($c['done']) ?> انجام‌شده"></div>
        </div>
        <span class="blbl"><?= mb_substr($dn,0,3) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- pending approvals -->
  <div class="panel reveal" data-d="2">
    <div class="panel-head"><h3><?= icon('bell',20) ?> در انتظار تأیید</h3>
      <a href="<?= url('admin/students.php?status=pending') ?>" class="badge badge-gold"><?= fa_num(count($pending)) ?></a></div>
    <?php if (!$pending): ?>
      <div class="empty-state" style="padding:30px"><div class="es-ico"><?= icon('check-circle',28) ?></div>همه تأیید شده‌اند 🎉</div>
    <?php else: foreach (array_slice($pending,0,5) as $s): ?>
      <div class="between" style="padding:11px 0;border-bottom:1px solid var(--border-soft)">
        <div class="u-row"><span class="u-ava gold"><?= e(avatar_letters($s['full_name'])) ?></span>
          <div><div style="font-weight:700;font-size:.9rem"><?= e($s['full_name']) ?></div><div class="muted" style="font-size:.78rem"><?= e($s['field'] ?: 'نامشخص') ?> · <?= time_ago($s['created_at']) ?></div></div>
        </div>
        <form method="post" action="<?= url('admin/student_action.php') ?>" style="display:inline">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="approve">
          <button class="btn btn-sage btn-sm"><?= icon('check',15) ?> تأیید</button>
        </form>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- top students -->
<div class="panel reveal mt-6" data-d="3">
  <div class="panel-head"><h3><?= icon('trophy',20) ?> رتبه‌بندی دانش‌آموزان</h3>
    <a href="<?= url('admin/reports.php') ?>" class="btn btn-ghost btn-sm">گزارش کامل <?= icon('arrow-left',15) ?></a></div>
  <?php if (!$topStudents): ?>
    <div class="empty-state"><div class="es-ico"><?= icon('users',30) ?></div>هنوز دانش‌آموز فعالی نیست</div>
  <?php else: ?>
  <table class="tbl">
    <thead><tr><th>دانش‌آموز</th><th>رشته</th><th>استریک</th><th>پیشرفت</th><th></th></tr></thead>
    <tbody>
    <?php
    usort($topStudents, fn($a,$b)=> ($b['done_tasks']<=>$a['done_tasks']));
    foreach ($topStudents as $s):
      $pct = $s['total_tasks'] ? round($s['done_tasks']/$s['total_tasks']*100) : 0; ?>
      <tr>
        <td><div class="u-row"><span class="u-ava"><?= e(avatar_letters($s['full_name'])) ?></span><span style="font-weight:700"><?= e($s['full_name']) ?></span></div></td>
        <td><span class="badge"><?= e($s['field'] ?: '—') ?></span></td>
        <td><span class="badge badge-gold"><?= icon('fire',13) ?> <?= fa_num($s['streak']) ?></span></td>
        <td style="min-width:160px"><div class="between" style="gap:10px"><div class="progress" style="flex:1"><span data-w="<?= $pct ?>" style="width:0"></span></div><span style="font-size:.82rem;font-weight:700"><?= fa_num($pct) ?>٪</span></div></td>
        <td><a href="<?= url('admin/plan_builder.php?student='.(int)$s['id']) ?>" class="btn btn-ghost btn-sm"><?= icon('calendar',15) ?> برنامه</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php panel_end(); ?>
