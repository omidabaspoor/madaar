<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('student');
$u = current_user();

// ارزیابی خودکار (در صورت رسیدن به شرط‌ها همین حالا اعطا شود)
evaluate_achievements((int)$u['id']);

$all = all_achievements(true);
$earned = student_earned_ids((int)$u['id']);
$streak = (int)$u['streak'];

$at = db()->prepare('SELECT COALESCE(SUM(is_done),0) done FROM tasks WHERE student_id=?');
$at->execute([$u['id']]); $doneCount = (int)$at->fetchColumn();

$earnedCount = count(array_filter($all, fn($a)=>isset($earned[(int)$a['id']])));
$total = count($all);

panel_start('دستاوردها', 'نشان‌های افتخار تو', 'student', 'achievements', ['student.css']);
?>
<div class="panel reveal in" style="margin-bottom:20px">
  <div class="between wrap gap-4">
    <div class="flex gap-4" style="align-items:center">
      <div class="ring-wrap"><div class="ring" data-p="<?= $total?round($earnedCount/$total*100):0 ?>" style="--p:0"><span><?= fa_num($earnedCount) ?>/<?= fa_num($total) ?></span></div></div>
      <div><h3 style="font-size:1.2rem">آفرین <?= e(explode(' ',(string)$u['full_name'])[0]) ?>! 🎉</h3>
        <p class="muted"><?php if($total):?><?= fa_num($earnedCount) ?> نشان از <?= fa_num($total) ?> نشان را کسب کرده‌ای.<?php else:?>هنوز نشانی تعریف نشده است.<?php endif;?></p></div>
    </div>
    <span class="badge badge-gold" style="padding:9px 16px"><?= icon('fire',15) ?> <?= fa_num($streak) ?> روز استریک</span>
  </div>
</div>

<?php if (!$all): ?>
  <div class="panel"><div class="empty-state"><div class="es-ico"><?= icon('trophy',34) ?></div><p>هنوز دستاوردی تعریف نشده است 🌱</p><p class="muted" style="font-size:.85rem">مشاورت به‌زودی نشان‌ها را اضافه می‌کند.</p></div></div>
<?php else: ?>
<div class="ach-grid">
  <?php foreach ($all as $i=>$a):
    $isEarned = isset($earned[(int)$a['id']]);
    // پیشرفت به سمت دستاورد
    $prog = '';
    if (!$isEarned && $a['condition_type']!=='manual' && $a['threshold']>0) {
        $cur = $a['condition_type']==='tasks_done' ? $doneCount : $streak;
        $pct = min(100, round($cur/$a['threshold']*100));
        $prog = '<div class="progress" style="margin-top:10px;height:6px"><span data-w="'.$pct.'" style="width:0"></span></div>'
              .'<div class="muted" style="font-size:.72rem;margin-top:4px">'.fa_num(min($cur,(int)$a['threshold'])).'/'.fa_num($a['threshold']).'</div>';
    }
    ?>
  <div class="panel card-glow ach <?= $isEarned?'earned':'locked' ?> reveal" data-d="<?= min($i+1,6) ?>">
    <div class="ach-ico"><?= icon($a['icon'],28) ?></div>
    <div class="ach-t"><?= e($a['title']) ?></div>
    <div class="ach-d"><?= e($a['description'] ?: '') ?></div>
    <?php if($isEarned):?>
      <span class="badge badge-sage" style="margin-top:10px"><?= icon('check',12) ?> کسب شد · <?= time_ago($earned[(int)$a['id']]) ?></span>
    <?php else:?>
      <span class="badge" style="margin-top:10px"><?= icon('lock',12) ?> <?= $a['condition_type']==='manual'?'با تأیید مشاور':'قفل' ?></span>
      <?= $prog ?>
    <?php endif;?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php panel_end(); ?>
