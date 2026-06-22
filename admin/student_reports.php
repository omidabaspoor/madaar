<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reporting.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('advisor','admin');
$u=current_user();
$studentId=(int)($_GET['student']??0);
$type=in_array($_GET['type']??'daily',['daily','weekly','monthly'],true)?$_GET['type']:'daily';
if(!$studentId){
  $students=advisor_students((int)$u['id'],'active');
  panel_start('گزارش حرفه‌ای','انتخاب دانش‌آموز', 'admin','student_reports',['student.css']); ?>
  <div class="student-grid">
    <?php foreach($students as $s): ?>
    <a class="panel student-card" href="?student=<?= (int)$s['id'] ?>&type=daily" style="text-decoration:none;color:inherit">
      <div class="sc-top"><span class="u-ava"><?= e(avatar_letters($s['full_name'])) ?></span><div><b><?= e($s['full_name']) ?></b><div class="muted">گزارش‌های روزانه، هفتگی و ماهانه</div></div></div>
      <div class="sc-meta"><span class="badge">× <?= fa_num($s['missed_tasks']??0) ?> قرمز</span><span class="badge badge-sage">✓ <?= fa_num($s['full_tasks']??0) ?> کامل</span></div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php panel_end(); exit;
}
$student=get_user($studentId);
if(!$student || $student['role']!=='student'){ flash('error','دانش‌آموز یافت نشد'); redirect('admin/student_reports.php'); }
if (($u['role'] ?? '') === 'advisor' && (int)($student['advisor_id'] ?? 0) !== (int)$u['id']) {
  http_response_code(403);
  require __DIR__ . '/../403.php';
  exit;
}
try { $reports=reports_for_student($studentId,$type,40); }
catch (Throwable $e) { $reports=[]; flash('error','خطا در خواندن گزارش‌ها. اگر نصب قدیمی است، install.php را یک‌بار اجرا کنید.'); }
$showInsight = advisor_feature_enabled((int)$u['id'], 'insight_enabled');
panel_start('گزارش حرفه‌ای', $student['full_name'].' · '.report_type_label($type), 'admin','student_reports',['student.css']);
?>
<div class="between mb-4 wrap gap-3">
  <div class="builder-student flex gap-3" style="align-items:center">
    <a href="<?= url('admin/student_reports.php') ?>" class="btn btn-ghost btn-icon"><?= icon('arrow-right',18) ?></a>
    <span class="u-ava"><?= e(avatar_letters($student['full_name'])) ?></span>
    <div><div style="font-weight:900"><?= e($student['full_name']) ?></div><div class="muted"><?= e($student['field']?:'') ?> · گزارش‌های ثبت‌شده</div></div>
  </div>
  <div class="report-tabs">
    <?php foreach(['daily'=>'روزانه','weekly'=>'هفتگی','monthly'=>'ماهانه'] as $k=>$lbl): ?><a class="chip <?= $type===$k?'active':'' ?>" href="?student=<?= $studentId ?>&type=<?= $k ?>"><?= e($lbl) ?></a><?php endforeach; ?>
  </div>
</div>

<div class="between wrap gap-3 mb-6" style="background:var(--surface-1); padding:16px 24px; border-radius:var(--r-md); border:1px solid var(--border);">
  <div class="flex items-center gap-3">
    <?= icon('chart', 20, 'text-gold') ?>
    <b style="font-size: 1.05rem; color: var(--text);">خروجی‌های چاپی و مدیریتی این پرونده:</b>
  </div>
  <div class="flex items-center gap-3 wrap">
    <a href="<?= url('admin/student_report_pdf.php?student=' . $studentId . '&type=' . $type) ?>" target="_blank" class="btn btn-gold flex items-center gap-2 shadow-lg" style="font-weight: 900;">
      <?= icon('pie', 18) ?> <span>دریافت PDF گزارش پیشرفته و تحلیل مَدار</span>
    </a>
  </div>
</div>

<?php if(!$reports): ?>
<div class="panel"><div class="empty-state"><div class="es-ico"><?= icon('chart',30) ?></div>هنوز گزارشی برای این بخش ثبت نشده</div></div>
<?php else: foreach($reports as $r): $s=$r['snapshot'] ?? []; $a=$r['advanced'] ?? []; $an=null; if($showInsight){ try { $an=report_build_analysis($studentId,(string)$r['report_type'],(string)$r['period_start'],(string)$r['period_end'],$s,$a); } catch (Throwable $e) { $an=null; } } ?>
<div class="panel report-admin-card mb-4 <?= $r['status']==='submitted'?'submitted':'draft' ?>">
  <div class="panel-head between wrap gap-2">
    <div class="flex items-center gap-3">
      <h3><?= e(report_type_label($r['report_type'])) ?> · <?= jalali_date($r['period_start']) ?><?= $r['period_start']!==$r['period_end']?' تا '.jalali_date($r['period_end']):'' ?></h3>
      <?php 
        $isReportLocked = report_deadline_passed((string)$r['report_type'], (string)$r['period_start'], (string)$r['period_end']);
        if ($r['status'] === 'submitted'): 
      ?>
        <span class="badge badge-sage">ارسال شده</span>
      <?php elseif ($isReportLocked): ?>
        <span class="badge" style="background: rgba(220, 53, 69, 0.15); border: 1px solid rgba(220, 53, 69, 0.3); color: #ea868f; padding: 4px 10px; border-radius: 99px; font-weight: bold;">قفل شده (عدم ثبت)</span>
      <?php else: ?>
        <span class="badge badge-gold">تکمیل نشده</span>
      <?php endif; ?>
    </div>
    <a href="<?= url('admin/student_report_pdf.php?report_id=' . $r['id']) ?>" target="_blank" class="btn btn-ghost btn-sm flex items-center gap-1.5" style="border-color:var(--gold); color:var(--gold-light); font-weight:800;">
      <?= icon('pie', 16) ?> <span>خروجی PDF این گزارش</span>
    </a>
  </div>
  <div class="stat-cards compact-stats">
    <div class="panel stat"><span class="icon-tile sage">٪</span><div><div class="v"><?= fa_num($s['progress_percent']??0) ?>٪</div><div class="k">پیشرفت</div></div></div>
    <div class="panel stat"><span class="icon-tile sage"><?= icon('clock',20) ?></span><div><div class="v"><?= fa_num($s['study_hours']??0) ?></div><div class="k">ساعت مؤثر</div></div></div>
    <div class="panel stat"><span class="icon-tile"><?= icon('check',20) ?></span><div><div class="v"><?= fa_num($s['tests_done']??0) ?></div><div class="k">تست</div></div></div>
    <div class="panel stat"><span class="icon-tile" style="background:rgba(217,116,116,.16);color:#ff9a9a">×</span><div><div class="v"><?= fa_num($s['missed']??0) ?></div><div class="k">قرمز</div></div></div>
  </div>

  <?php if($showInsight && $an): ?>
  <div class="insight-closed admin-insight-closed">
    <div><b><?= icon('sparkles',18) ?> تحلیل هوشمند مَدار <span class="beta-pill">بتا</span></b><span><?= e($an['summary']) ?></span></div>
    <button class="btn btn-gold btn-sm" type="button" data-modal="insightModal<?= (int)$r['id'] ?>">مشاهده</button>
  </div>
  <div class="modal-backdrop" id="insightModal<?= (int)$r['id'] ?>">
    <div class="modal insight-modal">
      <div class="modal-head"><h3><?= icon('sparkles',20) ?> تحلیل هوشمند مَدار <span class="beta-pill">بتا</span></h3><button class="modal-close" data-close><?= icon('close',18) ?></button></div>
      <div class="insight-score-head" style="display:flex;flex-wrap:wrap;gap:16px;justify-content:space-between;align-items:center;background:var(--surface-2);border:1px solid var(--border-soft);padding:18px 24px;border-radius:var(--r-lg);margin-bottom:20px">
        <div style="display:flex;align-items:center;gap:16px">
          <div style="background:var(--gold-glass);color:var(--gold);width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:900;box-shadow:0 0 16px rgba(217,178,95,0.2)">
            <?= fa_num($an['overall']) ?>٪
          </div>
          <div style="text-align:r">
            <div style="font-size:.78rem;color:var(--gold);text-transform:uppercase;letter-spacing:1px;font-weight:700">شاخص بازدهی و کیفیت مطالعاتی</div>
            <div style="font-size:1.1rem;font-weight:900;color:var(--text-1);margin-top:2px">نمره ارزیابی کلی سیستم</div>
          </div>
        </div>
        
        <div style="text-align:l">
          <div style="font-size:.78rem;color:var(--text-3)">ارزیابی وضعیت برنامه:</div>
          <span class="badge badge-gold" style="font-size:1rem;padding:6px 16px;font-weight:800;margin-top:4px;display:inline-block">
            <?= e($an['overall_label']) ?>
          </span>
        </div>
      </div>
      <p class="insight-summary"><?= e($an['summary']) ?></p>
      <div class="insight-mini-row"><?php foreach(['execution'=>'اجرا','consistency'=>'ثبات','tests'=>'تست','recovery'=>'خواب/انرژی','subject_balance'=>'تعادل','burnout_risk'=>'ریسک افت'] as $k=>$lbl): ?><span><?= e($lbl) ?>: <b><?= fa_num($an['scores'][$k]??0) ?>٪</b></span><?php endforeach; ?></div>
      <?php if($an['alerts']): ?><div class="insight-alerts compact"><?php foreach($an['alerts'] as $al): ?><div class="ia <?= e($al['level']) ?>"><b><?= e($al['title']) ?></b><span><?= e($al['text']) ?></span></div><?php endforeach; ?></div><?php endif; ?>
      <div class="insight-recs"><b>پیشنهادها</b><ul><?php foreach($an['recommendations'] as $rec): ?><li><?= e($rec) ?></li><?php endforeach; ?></ul></div>
      <?php if(!empty($an['action_plan'])): ?><div class="insight-recs action"><b>نقشه اقدام</b><ul><?php foreach($an['action_plan'] as $rec): ?><li><?= e($rec) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <?php if(!empty($an['method_notes'])): ?><div class="insight-method"><b><?= icon('book',16) ?> پشتوانه تحلیلی</b><?php foreach($an['method_notes'] as $note): ?><span><?= e($note) ?></span><?php endforeach; ?></div><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <div class="report-review-grid">
    <div class="rr-box"><b>خواب</b><span><?= isset($a['sleep_hours'])?fa_num($a['sleep_hours']).' ساعت':'—' ?> · کیفیت <?= isset($a['sleep_quality'])?fa_num($a['sleep_quality']).'/۵':'—' ?></span></div>
    <div class="rr-box"><b>تمرکز / انرژی / استرس</b><span><?= fa_num($a['focus_score']??'—') ?> / <?= fa_num($a['energy_score']??'—') ?> / <?= fa_num($a['stress_score']??'—') ?></span></div>
    <div class="rr-box"><b>موبایل و اتلاف وقت</b><span><?= fa_num($a['phone_minutes']??0) ?> دقیقه · اتلاف <?= fa_num($a['wasted_minutes']??0) ?> دقیقه</span></div>
    <div class="rr-box"><b>خودارزیابی</b><span><?= isset($a['self_score'])?fa_num($a['self_score']).' از ۲۰':'—' ?></span></div>
    <?php if(!empty($a['main_reason']) || !empty($a['plan_fit']) || !empty($a['monthly_mindset'])): ?><div class="rr-box"><b>جمع‌بندی بازه</b><span><?= e($a['main_reason'] ?? $a['plan_fit'] ?? $a['monthly_mindset'] ?? '') ?></span></div><?php endif; ?>
  </div>
  <div class="report-notes">
    <?php foreach(['best_win'=>'برد/نقطه قوت','main_challenge'=>'چالش','challenge_reason'=>'علت احتمالی','solution'=>'راهکار دانش‌آموز','next_priority'=>'اولویت بعدی','advisor_question'=>'سؤال از مشاور'] as $k=>$lbl): if(!empty($a[$k])): ?>
      <div><b><?= e($lbl) ?></b><p><?= nl2br(e($a[$k])) ?></p></div>
    <?php endif; endforeach; ?>
  </div>
  <?php if(!empty($s['by_subject'])): ?><div class="report-subjects mt-4"><?php foreach($s['by_subject'] as $name=>$x): ?><div class="report-subj"><b><?= e($name) ?></b><span><?= fa_num($x['tests']??0) ?> تست · <?= fa_num(round(($x['minutes']??0)/60,1)) ?> ساعت · قرمز <?= fa_num($x['missed']??0) ?></span></div><?php endforeach; ?></div><?php endif; ?>
</div>
<?php endforeach; endif; ?>
<?php panel_end(); ?>
