<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/result_view.php';
boot_session();
require_role('student');
$u = current_user();

$attemptId = (int)($_GET['attempt'] ?? 0);
$rep = attempt_report($attemptId);
if (!$rep || (int)$rep['attempt']['student_id'] !== (int)$u['id']) { flash('error','کارنامه یافت نشد'); redirect('student/exams.php'); }
if ($rep['attempt']['status'] !== 'submitted') redirect('student/exam.php?id='.$rep['attempt']['exam_id']);

$showAnswers = (int)$rep['exam']['show_review'] === 1;

panel_start('کارنامه آزمون', $rep['exam']['title'], 'student', 'exams', ['student.css','exam.css','result.css']);
?>
<div class="between wrap gap-3 mb-6" style="background:var(--surface-1); padding:16px 24px; border-radius:var(--r-md); border:1px solid var(--border);">
  <a href="<?= url('student/exams.php') ?>" class="btn btn-ghost btn-sm" style="font-weight:bold;"><?= icon('arrow-right',16) ?> بازگشت به لیست آزمون‌ها</a>
  <div class="flex items-center gap-3 wrap">
    <a href="<?= url('student/exam_pdf.php?id=' . $rep['exam']['id']) ?>" target="_blank" class="btn btn-ghost flex items-center gap-2" style="border-color:var(--sage); color:var(--sage-light); font-weight:800;">
      <?= icon('clipboard', 18) ?> <span>خروجی PDF دفترچه سوالات</span>
    </a>
    <a href="<?= url('student/exam_solution_pdf.php?attempt=' . $attemptId) ?>" target="_blank" class="btn btn-gold flex items-center gap-2 shadow-lg" style="font-weight:900;">
      <?= icon('star', 18) ?> <span>خروجی PDF کارنامه و پاسخنامه</span>
    </a>
  </div>
</div>
<div class="panel" style="border-color:rgba(203,172,128,.35);background:linear-gradient(135deg,rgba(203,172,128,.12),rgba(107,136,114,.08));margin-bottom:18px">
  <div class="between wrap gap-3" style="align-items:center">
    <div>
      <h3 style="margin-bottom:6px;color:var(--gold-light)"><?= icon('chart',18) ?> تحلیل آزمون داخلی مَدار</h3>
      <p class="muted">کارنامه آماده است؛ حالا ۲ دقیقه وقت بگذار و تحلیل رفتاری آزمونت را پر کن تا برنامه اقدام و گزارش کامل برای مشاور ارسال شود.</p>
    </div>
    <a class="btn btn-gold" href="<?= url('student/internal_exam_analysis.php?attempt='.$attemptId) ?>"><?= icon('sparkles',16) ?> تکمیل تحلیل آزمون</a>
  </div>
</div>
<?php render_result($rep, $showAnswers); ?>
<?php if(!$showAnswers): ?>
<div class="panel" style="margin-top:18px"><div class="empty-state"><div class="es-ico"><?= icon('lock',28) ?></div>پاسخنامه‌ی این آزمون توسط مشاور غیرفعال شده است.</div></div>
<?php endif; ?>
<?php panel_end(); ?>
