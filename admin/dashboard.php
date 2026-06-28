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

// نمودار ۸ هفته‌ای ساده (تعداد تسک‌های تکمیل‌شده)
$chart = [];
for ($i=7; $i>=0; $i--) {
    $cnt = rand(0,0); // واقعی از لاگ، اینجا از تسک‌های done در آن بازه
}
$weekChart = db()->query("SELECT day_index, COUNT(*) total, COALESCE(SUM(is_done),0) done FROM tasks GROUP BY day_index ORDER BY day_index")->fetchAll();
$chartData = array_fill(0,7,['total'=>0,'done'=>0]);
foreach ($weekChart as $w) { $chartData[(int)$w['day_index']] = ['total'=>(int)$w['total'],'done'=>(int)$w['done']]; }
$maxBar = max(1, max(array_map(fn($c)=>$c['total'], $chartData)));

// عملکرد دیروز دانش‌آموزان فعال
$activeStudents = array_values(array_filter($students, fn($s) => $s['status'] === 'active'));
$yesterday = date('Y-m-d', strtotime('-1 day'));
$yesterdayRows = [];
if ($activeStudents) {
    $ids = array_map(fn($s)=>(int)$s['id'], $activeStudents);
    $nameMap = [];
    foreach ($activeStudents as $s) $nameMap[(int)$s['id']] = $s;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $scoreSql = task_score_sql('t');
    try {
        $stY = db()->prepare("SELECT t.student_id,
            COUNT(*) total,
            COALESCE(SUM($scoreSql),0) score,
            SUM(t.completion_status='full' OR (t.completion_status IS NULL AND t.is_done=1)) full_count,
            SUM(t.completion_status='partial') partial_count,
            SUM(t.completion_status='missed') missed_count,
            SUM(t.completion_status='pending') pending_count,
            SUM(CASE WHEN t.status_updated_at IS NOT NULL AND DATE(t.status_updated_at)=? THEN 1 ELSE 0 END) updated_yesterday
            FROM tasks t
            JOIN plans p ON p.id=t.plan_id
            WHERE t.student_id IN ($ph)
              AND p.status='published'
              AND DATE_ADD(p.week_start, INTERVAL t.day_index DAY)=?
            GROUP BY t.student_id");
        $params = array_merge([$yesterday], $ids, [$yesterday]);
        $stY->execute($params);
        foreach ($stY->fetchAll() as $r) {
            $sid = (int)$r['student_id'];
            $total = (int)$r['total'];
            $score = (float)$r['score'];
            $pct = $total ? round($score / $total * 100) : 0;
            $yesterdayRows[] = [
                'student' => $nameMap[$sid] ?? ['full_name'=>'دانش‌آموز','field'=>''],
                'student_id' => $sid,
                'total' => $total,
                'score' => $score,
                'pct' => $pct,
                'full' => (int)$r['full_count'],
                'partial' => (int)$r['partial_count'],
                'missed' => (int)$r['missed_count'],
                'pending' => (int)$r['pending_count'],
                'updated' => (int)$r['updated_yesterday'],
            ];
        }
        usort($yesterdayRows, fn($a,$b) => [$b['missed'], $a['pct'], $b['partial']] <=> [$a['missed'], $b['pct'], $a['partial']]);
    } catch (Throwable $e) { error_log($e->getMessage()); }
}
$yTotal = array_sum(array_column($yesterdayRows, 'total'));
$yScore = array_sum(array_column($yesterdayRows, 'score'));
$yPct = $yTotal ? round($yScore / $yTotal * 100) : 0;
$yFull = array_sum(array_column($yesterdayRows, 'full'));
$yPartial = array_sum(array_column($yesterdayRows, 'partial'));
$yMissed = array_sum(array_column($yesterdayRows, 'missed'));

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
  <div class="panel stat reveal in"><span class="icon-tile sage"><?= icon('users',26) ?></span><div><div class="v" data-live-advisor="students_total"><?= fa_num($stats['total']) ?></div><div class="k">کل دانش‌آموزان</div></div></div>
  <div class="panel stat reveal" data-d="1"><span class="icon-tile"><?= icon('check-circle',26) ?></span><div><div class="v" data-live-advisor="students_active"><?= fa_num($stats['active']) ?></div><div class="k">فعال</div></div></div>
  <div class="panel stat reveal" data-d="2"><span class="icon-tile" style="background:rgba(217,178,95,.14);color:var(--warn)"><?= icon('clock',26) ?></span><div><div class="v" data-live-advisor="students_pending"><?= fa_num($stats['pending']) ?></div><div class="k">در انتظار تأیید</div></div></div>
  <div class="panel stat reveal" data-d="3"><span class="icon-tile sage"><?= icon('target',26) ?></span><div><div class="v"><span data-live-advisor="completion_rate"><?= fa_num($stats['rate']) ?></span>٪</div><div class="k">نرخ تکمیل تسک‌ها</div><div class="trend up">از <span data-live-advisor="tasks_total"><?= fa_num($stats['tasksTotal']) ?></span> تسک</div></div></div>
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



<!-- yesterday performance -->
<div class="panel reveal mt-6" data-d="3" style="overflow:hidden">
  <div class="panel-head between wrap gap-3" style="align-items:center">
    <h3><?= icon('history',20) ?> عملکرد دیروز دانش‌آموزان</h3>
    <span class="badge badge-gold"><?= jalali_date($yesterday) ?></span>
  </div>
  <style>
    .yd-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px}.yd-card{background:var(--surface-2);border:1px solid var(--border-soft);border-radius:16px;padding:13px}.yd-card b{display:block;font-size:1.35rem;color:var(--text-1)}.yd-card span{font-size:.78rem;color:var(--text-3);font-weight:800}.yd-list{display:grid;gap:10px}.yd-row{display:grid;grid-template-columns:minmax(190px,1fr) 120px minmax(160px,240px) auto;gap:12px;align-items:center;background:linear-gradient(135deg,rgba(255,255,255,.025),var(--surface-2));border:1px solid var(--border-soft);border-radius:16px;padding:12px}.yd-row.warn{border-color:rgba(217,116,116,.34);background:linear-gradient(135deg,rgba(217,116,116,.08),var(--surface-2))}.yd-pills{display:flex;gap:6px;flex-wrap:wrap}.yd-pill{font-size:.72rem;font-weight:900;padding:4px 8px;border-radius:999px;background:var(--surface-1);border:1px solid var(--border-soft);color:var(--text-2)}.yd-pill.good{color:var(--sage-light);border-color:rgba(95,174,123,.32)}.yd-pill.bad{color:var(--danger);border-color:rgba(217,116,116,.34)}.yd-mini{height:8px;background:var(--surface-3);border-radius:99px;overflow:hidden}.yd-mini span{display:block;height:100%;background:linear-gradient(90deg,var(--sage),var(--gold));border-radius:99px}@media(max-width:760px){.yd-row{grid-template-columns:1fr}.yd-row .btn{width:100%}}
  </style>
  <div class="yd-summary">
    <div class="yd-card"><b><?= fa_num($yPct) ?>٪</b><span>میانگین اجرای دیروز</span></div>
    <div class="yd-card"><b><?= fa_num($yFull) ?></b><span>تسک کامل</span></div>
    <div class="yd-card"><b><?= fa_num($yPartial) ?></b><span>تسک ناقص</span></div>
    <div class="yd-card"><b><?= fa_num($yMissed) ?></b><span>عدم اجرا</span></div>
  </div>
  <?php if (!$yesterdayRows): ?>
    <div class="empty-state" style="padding:34px"><div class="es-ico"><?= icon('inbox',30) ?></div>برای دیروز داده‌ای ثبت نشده است.</div>
  <?php else: ?>
    <div class="yd-list">
      <?php foreach(array_slice($yesterdayRows, 0, 10) as $r): $st=$r['student']; $warn = $r['missed']>0 || $r['pct']<50; ?>
      <div class="yd-row <?= $warn?'warn':'' ?>">
        <div class="u-row"><span class="u-ava <?= $warn?'gold':'' ?>"><?= e(avatar_letters($st['full_name'])) ?></span><div><b><?= e($st['full_name']) ?></b><div class="muted" style="font-size:.78rem"><?= e($st['field'] ?: '—') ?></div></div></div>
        <div><b style="font-size:1.05rem"><?= fa_num($r['pct']) ?>٪</b><div class="yd-mini mt-1"><span style="width:<?= $r['pct'] ?>%"></span></div></div>
        <div class="yd-pills">
          <span class="yd-pill good">کامل <?= fa_num($r['full']) ?></span>
          <span class="yd-pill">ناقص <?= fa_num($r['partial']) ?></span>
          <span class="yd-pill bad">عدم اجرا <?= fa_num($r['missed']) ?></span>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= url('admin/plan_builder.php?student='.(int)$r['student_id']) ?>"><?= icon('calendar',14) ?> برنامه</a>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php panel_end(); ?>
