<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/internal_exam_analysis.php';
boot_session(); require_role('advisor','admin');
$u=current_user(); $studentId=(int)($_GET['student']??0);
if($studentId){ $st=get_user($studentId); if(!$st || $st['role']!=='student' || ($u['role']!=='admin' && (int)($st['advisor_id']??0)!==(int)$u['id'])){ flash('error','دانش‌آموز یافت نشد'); redirect('admin/internal_exam_reports.php'); } }
$reports=internal_analyses_for_advisor($u['role']==='admin'?0:(int)$u['id'], $studentId ?: null, 120);
panel_start('تحلیل آزمون داخلی مَدار', $studentId?('تحلیل‌های '.$st['full_name']):'تحلیل آزمون‌های داخل سامانه', 'admin','internal_exam',['mock_exam.css']);
?>
<div class="between wrap gap-3 mb-4"><div><span class="badge badge-gold"><?= icon('chart',15) ?> <?= fa_num(count($reports)) ?> تحلیل داخلی</span></div><a class="btn btn-ghost" href="<?= url('admin/exams.php') ?>"><?= icon('clipboard',16) ?> آزمون‌ها</a></div>
<?php if(!$reports): ?><div class="panel"><div class="empty-state"><div class="es-ico"><?= icon('chart',32) ?></div>هنوز تحلیل آزمون داخلی ثبت نشده است.</div></div><?php else: ?>
<div class="mock-list">
<?php foreach($reports as $r): $an=$r['analysis_json']?(json_decode($r['analysis_json'],true)?:[]):[]; ?>
  <div class="panel">
    <div class="between gap-2" style="align-items:flex-start"><div><b><?= e($r['exam_title']) ?></b><div class="muted"><?= e($r['student_name']) ?> · <?= e($r['field']?:'') ?> <?= $r['grade']?'· '.e($r['grade']):'' ?></div></div><span class="badge badge-sage"><?= fa_num($an['overall']??0) ?>٪</span></div>
    <div class="flex gap-2 wrap mt-3"><span class="badge"><?= jalali_date($r['submitted_at'],true) ?></span><span class="badge">نمره <?= fa_num(round((float)$r['total_score'],1)) ?>٪</span></div>
    <div class="flex gap-2 mt-4"><a class="btn btn-gold btn-sm" href="<?= url('student/internal_exam_analysis_pdf.php?id='.(int)$r['id']) ?>" target="_blank"><?= icon('clipboard',15) ?> PDF</a><a class="btn btn-ghost btn-sm" href="<?= url('admin/internal_exam_reports.php?student='.(int)$r['student_id']) ?>">همه تحلیل‌های دانش‌آموز</a></div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php panel_end(); ?>
