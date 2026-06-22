<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('student');
$u = current_user();
$exams = student_exams((int)$u['id']);
$now = time();

panel_start('آزمون‌ها', 'آزمون‌های تو', 'student', 'exams', ['student.css']);
?>
<?php if (!$exams): ?>
  <div class="panel"><div class="empty-state"><div class="es-ico"><?= icon('clipboard',34) ?></div><p>فعلاً آزمونی منتشر نشده 🌱</p><p class="muted" style="font-size:.84rem">به‌محض انتشار آزمون توسط مشاور، اینجا نمایش داده می‌شود.</p></div></div>
<?php else: ?>
<div class="exam-grid">
  <?php foreach ($exams as $e):
    $taken = $e['attempt_status']==='submitted';
    $inProgress = $e['attempt_status']==='in_progress';
    $notYet = $e['start_at'] && strtotime($e['start_at']) > $now;
    $ended  = $e['end_at'] && strtotime($e['end_at']) < $now;
    ?>
  <div class="panel card-glow exam-card reveal">
    <div class="between" style="align-items:flex-start">
      <div style="flex:1;min-width:0">
        <div class="flex gap-2" style="align-items:center;margin-bottom:5px">
          <span class="badge <?= $e['exam_type']==='comprehensive'?'badge-gold':'' ?>" style="font-size:.7rem"><?= $e['exam_type']==='comprehensive'?'جامع':'تکی' ?></span>
          <?php if($taken):?><span class="badge badge-sage" style="font-size:.7rem"><?= icon('check',11) ?> داده‌شده</span><?php endif;?>
          <?php if($inProgress):?><span class="badge badge-gold" style="font-size:.7rem">ناتمام</span><?php endif;?>
        </div>
        <div style="font-weight:800;font-size:1.05rem"><?= e($e['title']) ?></div>
        <div class="muted" style="font-size:.8rem;margin-top:3px"><?= e($e['description'] ?: '') ?></div>
      </div>
      <span class="icon-tile" style="width:44px;height:44px"><?= icon('clipboard',22) ?></span>
    </div>
    <div class="flex gap-2 wrap" style="margin-top:12px">
      <span class="badge" style="font-size:.72rem"><?= icon('list',12) ?> <?= fa_num($e['q_count']) ?> سوال</span>
      <span class="badge" style="font-size:.72rem"><?= icon('clock',12) ?> <?= fa_num($e['duration_min']) ?> دقیقه</span>
      <?php if($e['negative_marking']):?><span class="badge badge-danger" style="font-size:.72rem">نمره منفی</span><?php endif;?>
    </div>
    <div class="mt-4">
      <?php if($taken): ?>
        <div class="between" style="margin-bottom:10px"><span class="muted" style="font-size:.82rem">درصد کل</span><span class="fw-800 gold" style="font-size:1.2rem"><?= fa_num(round((float)$e['total_score'])) ?>٪</span></div>
        <?php if($e['show_review']):?>
        <a href="<?= url('student/exam_result.php?attempt='.(int)$e['attempt_id']) ?>" class="btn btn-ghost btn-block"><?= icon('chart',16) ?> مشاهده کارنامه و پاسخنامه</a>
        <div class="flex items-center gap-2 mt-2 wrap">
          <a href="<?= url('student/exam_pdf.php?id='.(int)$e['id']) ?>" target="_blank" class="btn btn-ghost btn-sm flex-1 text-center flex items-center justify-center gap-1.5 border-sage/40 text-sage" style="font-size: .78rem; font-weight: bold;"><?= icon('clipboard',14) ?> PDF سوالات</a>
          <a href="<?= url('student/exam_solution_pdf.php?attempt='.(int)$e['attempt_id']) ?>" target="_blank" class="btn btn-gold btn-sm flex-1 text-center flex items-center justify-center gap-1.5 shadow-2xs" style="font-size: .78rem; font-weight: bold;"><?= icon('star',14) ?> PDF کارنامه و پاسخنامه</a>
        </div>
        <?php else:?>
        <button class="btn btn-ghost btn-block" disabled>پاسخنامه در دسترس نیست</button>
        <?php endif;?>
      <?php elseif($notYet): ?>
        <button class="btn btn-ghost btn-block" disabled><?= icon('clock',16) ?> شروع از <?= jalali_date($e['start_at'],true) ?></button>
      <?php elseif($ended): ?>
        <button class="btn btn-ghost btn-block" disabled>مهلت آزمون به پایان رسیده</button>
      <?php else: ?>
        <a href="<?= url('student/exam.php?id='.(int)$e['id']) ?>" class="btn btn-gold btn-block"><?= $inProgress?icon('play',16).' ادامه آزمون':icon('rocket',16).' شروع آزمون' ?></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php panel_end(); ?>
