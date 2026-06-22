<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/mock_exam.php';
boot_session(); require_role('advisor','admin');
$u=current_user(); $studentId=(int)($_GET['student']??0);
if($studentId){ $st=get_user($studentId); if(!$st || $st['role']!=='student' || ($u['role']!=='admin' && (int)($st['advisor_id']??0)!==(int)$u['id'])){ flash('error','دانش‌آموز یافت نشد'); redirect('admin/mock_exam_reports.php'); } }
$reports=mock_reports_for_advisor($u['role']==='admin'?0:(int)$u['id'], $studentId ?: null, 120);
panel_start('آزمون آزمایشی/کنکور', $studentId?('گزارش‌های '.$st['full_name']):'تحلیل آزمون‌های بیرونی', 'admin','mock_exam',['mock_exam.css']);
?>
<div class="between wrap gap-3 mb-4"><div><span class="badge badge-gold"><?= icon('target',15) ?> <?= fa_num(count($reports)) ?> گزارش آزمون</span></div><a class="btn btn-ghost" href="<?= url('admin/students.php') ?>"><?= icon('users',16) ?> دانش‌آموزان</a></div>
<?php if(!$reports): ?><div class="panel"><div class="empty-state"><div class="es-ico"><?= icon('target',32) ?></div>هنوز گزارشی ثبت نشده است.</div></div><?php else: ?>
<div class="mock-list">
<?php foreach($reports as $r): $an=$r['analysis_json']?(json_decode($r['analysis_json'],true)?:[]):[]; ?>
  <div class="panel">
    <div class="between gap-2" style="align-items:flex-start"><div><b><?= e($r['exam_title'] ?: $r['provider']) ?></b><div class="muted"><?= e($r['student_name']) ?> · <?= e($r['field']?:'') ?> <?= $r['grade']?'· '.e($r['grade']):'' ?></div></div><span class="badge badge-sage"><?= fa_num($an['overall']??0) ?>٪</span></div>
    <div class="flex gap-2 wrap mt-3"><span class="badge"><?= jalali_date($r['exam_date']) ?></span><span class="badge">تراز <?= fa_num($r['total_score']??'-') ?></span><span class="badge">رتبه <?= fa_num($r['rank_in_exam']??'-') ?></span></div>
    <div class="flex gap-2 mt-4"><a class="btn btn-gold btn-sm" href="<?= url('student/mock_exam_pdf.php?id='.(int)$r['id']) ?>" target="_blank"><?= icon('clipboard',15) ?> PDF</a><a class="btn btn-ghost btn-sm" href="<?= url('admin/mock_exam_reports.php?student='.(int)$r['student_id']) ?>">همه گزارش‌های دانش‌آموز</a></div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php panel_end(); ?>
