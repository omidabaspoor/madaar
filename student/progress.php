<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('student');
$u = current_user();

$week = student_week_stats((int)$u['id']);
$chart = student_week_chart((int)$u['id']);
$subjects = student_subject_progress((int)$u['id']);
$maxBar = max(1, max(array_map(fn($c)=>max((float)$c['total'], (float)$c['done']),$chart))); 

// آمار کلی همه‌ی هفته‌ها
$scoreSql = task_score_sql('t');
$allTime = db()->prepare("SELECT COUNT(*) total, COALESCE(SUM($scoreSql),0) done, SUM(completion_status='full') full_count, SUM(completion_status='partial') partial_count, SUM(completion_status='missed') missed_count FROM tasks t WHERE student_id=?");
$allTime->execute([$u['id']]);
$at = $allTime->fetch();
$atPct = (int)$at['total'] ? round(((float)$at['done'])/(int)$at['total']*100) : 0;
$atDoneDisplay = score_display((float)($at['done'] ?? 0));

panel_start('گزارش پیشرفت', 'تحلیل عملکرد تو', 'student', 'progress', ['student.css']);

// line chart points
$pts = [];
$w = 600; $h = 150; $pad = 20;
$n = count($chart);
foreach ($chart as $i=>$c) {
    $x = $pad + ($i*($w-2*$pad)/max(1,$n-1));
    $y = $h-$pad - ($c['pct']/100)*($h-2*$pad);
    $pts[] = [$x,$y,$c];
}
$path = '';
foreach ($pts as $i=>$p) { $path .= ($i===0?'M':'L').round($p[0],1).' '.round($p[1],1).' '; }
$area = $path . 'L'.round($pts[count($pts)-1][0],1).' '.($h-$pad).' L'.$pad.' '.($h-$pad).' Z';
?>
<div class="stat-cards">
  <div class="panel stat reveal"><span class="icon-tile sage"><?= icon('target',24) ?></span><div><div class="v"><?= fa_num($atPct) ?>٪</div><div class="k">پیشرفت کلی</div></div></div>
  <div class="panel stat reveal" data-d="1"><span class="icon-tile"><?= icon('check-circle',24) ?></span><div><div class="v"><?= fa_num($atDoneDisplay) ?></div><div class="k">امتیاز تسک‌ها</div></div></div>
  <div class="panel stat reveal" data-d="2"><span class="icon-tile sage"><?= icon('fire',24) ?></span><div><div class="v"><?= fa_num($u['streak']) ?></div><div class="k">استریک فعلی</div></div></div>
  <div class="panel stat reveal" data-d="3"><span class="icon-tile" style="background:rgba(217,178,95,.14);color:var(--warn)"><?= icon('calendar',24) ?></span><div><div class="v"><?= fa_num($week['percent']) ?>٪</div><div class="k">این هفته · قرمز <?= fa_num($week['missed']) ?></div></div></div>
</div>

<div class="panel-grid cols-2">
  <!-- line chart -->
  <div class="panel reveal" data-d="1">
    <div class="panel-head"><h3><?= icon('chart',20) ?> روند هفتگی</h3></div>
    <div class="linechart">
      <svg viewBox="0 0 <?= $w ?> <?= $h ?>" preserveAspectRatio="none">
        <defs><linearGradient id="ag" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#6b8872" stop-opacity=".35"/><stop offset="1" stop-color="#6b8872" stop-opacity="0"/></linearGradient></defs>
        <path d="<?= $area ?>" fill="url(#ag)"/>
        <path d="<?= $path ?>" fill="none" stroke="#cbac80" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        <?php foreach ($pts as $p): ?><circle cx="<?= round($p[0],1) ?>" cy="<?= round($p[1],1) ?>" r="3.5" fill="#cbac80"/><?php endforeach; ?>
      </svg>
      <div class="flex between" style="margin-top:8px"><?php foreach ($chart as $c): ?><span class="blbl" style="font-size:.7rem"><?= mb_substr($c['day'],0,3) ?></span><?php endforeach; ?></div>
    </div>
  </div>

  <!-- subject progress -->
  <div class="panel reveal" data-d="2">
    <div class="panel-head"><h3><?= icon('pie',20) ?> پیشرفت هر درس</h3></div>
    <?php if (!$subjects): ?>
      <div class="empty-state" style="padding:30px">هنوز داده‌ای نیست</div>
    <?php else: foreach ($subjects as $s): ?>
    <div style="margin-bottom:14px">
      <div class="between" style="font-size:.85rem;margin-bottom:5px"><span class="flex gap-2" style="align-items:center"><span class="subj-dot" style="background:<?= e($s['color']) ?>"></span><?= e($s['name']) ?></span><span class="fw-700"><?= fa_num($s['pct']) ?>٪</span></div>
      <div class="progress"><span data-w="<?= $s['pct'] ?>" style="width:0;background:<?= e($s['color']) ?>"></span></div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- weekly bars -->
<div class="panel reveal mt-6" data-d="3">
  <div class="panel-head"><h3><?= icon('bar',20) ?> تسک‌های انجام‌شده در هفته</h3></div>
  <div class="barchart">
    <?php foreach ($chart as $c): $hh=round($c['done']/$maxBar*100); ?>
    <div class="bcol">
      <div class="bar" data-h="<?= $hh ?>" style="height:0" data-tip="<?= fa_num($c['done_display']) ?> از <?= fa_num($c['total']) ?>"></div>
      <span class="blbl"><?= mb_substr($c['day'],0,3) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php panel_end(); ?>
