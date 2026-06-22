<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/internal_exam_analysis.php';
boot_session(); require_role('student');
$u=current_user();
$attemptId=(int)($_GET['attempt']??0); $id=(int)($_GET['id']??0);
if($id && !$attemptId){ $old=internal_analysis($id); if($old) $attemptId=(int)$old['attempt_id']; }
if(!$attemptId){
  $st=db()->prepare('SELECT a.id attempt_id,a.submitted_at,a.total_score,e.title exam_title, ia.id analysis_id FROM exam_attempts a JOIN exams e ON e.id=a.exam_id LEFT JOIN internal_exam_analyses ia ON ia.attempt_id=a.id WHERE a.student_id=? AND a.status="submitted" ORDER BY a.submitted_at DESC LIMIT 50');
  $st->execute([(int)$u['id']]); $items=$st->fetchAll();
  panel_start('تحلیل آزمون داخلی مَدار','یکی از آزمون‌های تکمیل‌شده را برای تحلیل انتخاب کن', 'student','exam_analyses',['mock_exam.css']);
  echo '<div class="mock-hero panel"><div><span class="badge badge-gold">تحلیل آزمون داخلی</span><h2>کدام آزمون را تحلیل کنیم؟</h2><p>بعد از هر آزمون داخلی، از همین صفحه می‌توانی تحلیل رفتاری و خروجی PDF بسازی.</p></div><a class="btn btn-ghost" href="'.url('student/exams.php').'">آزمون‌ها</a></div>';
  if(!$items) echo '<div class="panel"><div class="empty-state">هنوز آزمون داخلی ثبت‌شده‌ای نداری.</div></div>';
  else { echo '<div class="mock-list">'; foreach($items as $it){ $href=url('student/internal_exam_analysis.php?attempt='.(int)$it['attempt_id']); echo '<a class="panel" href="'.$href.'"><b>'.e($it['exam_title']).'</b><span>'.jalali_date($it['submitted_at'],true).' · نمره '.fa_num(round((float)$it['total_score'],1)).'٪</span><div class="mt-2"><span class="badge '.($it['analysis_id']?'badge-sage':'badge-gold').'">'.($it['analysis_id']?'تحلیل ثبت شده':'نیاز به تحلیل').'</span></div></a>'; } echo '</div>'; }
  panel_end(); exit;
}
$payload=internal_attempt_payload($attemptId);
if(!$payload || (int)$payload['rep']['attempt']['student_id'] !== (int)$u['id']){ flash('error','آزمون برای تحلیل یافت نشد.'); redirect('student/exams.php'); }
$existing=internal_analysis_by_attempt($attemptId);
if($_SERVER['REQUEST_METHOD']==='POST'){
  require_csrf();
  try{ $newId=internal_analysis_save((int)$u['id'],$attemptId,$_POST); flash('success','تحلیل آزمون داخلی با موفقیت ثبت شد و برای مشاور ارسال شد.'); redirect('student/internal_exam_analysis.php?attempt='.$attemptId.'&saved=1'); }
  catch(Throwable $e){ flash('error', APP_ENV==='development'?$e->getMessage():'خطا در ثبت تحلیل آزمون'); }
}
$r=$existing; $rep=$payload['rep']; $subjects=$payload['subjects']; $issues=$r['issues']??$payload['issues']; $an=$r['analysis']??null; $beh=$r['behavior']??[]; $reports=internal_analyses_for_student((int)$u['id']);
panel_start('تحلیل آزمون داخلی مَدار','تحلیل رفتاری و برنامه اقدام برای آزمون‌های داخل سامانه', 'student','exam_analyses',['mock_exam.css']);
?>
<div class="mock-hero panel">
  <div><span class="badge badge-gold"><?= icon('chart',15) ?> تحلیل آزمون داخلی مَدار</span><h2><?= e($rep['exam']['title']) ?></h2><p>نتیجه، درس‌ها و سوالات غلط/نزده از سیستم آزمون مَدار خوانده شده؛ فقط تجربه آزمون و جمع‌بندی خودت را بنویس تا تحلیل کامل ساخته شود.</p></div>
  <div class="flex gap-2 wrap"><?php if($r): ?><a target="_blank" class="btn btn-gold" href="<?= url('student/internal_exam_analysis_pdf.php?id='.(int)$r['id']) ?>"><?= icon('clipboard',16) ?> خروجی PDF</a><?php endif; ?><a class="btn btn-ghost" href="<?= url('student/exam_result.php?attempt='.$attemptId) ?>">کارنامه آزمون</a></div>
</div>

<div class="panel">
  <h3><?= icon('target',18) ?> خلاصه خودکار آزمون</h3>
  <div class="ma-grid">
    <div><span>نمره کل</span><b><?= fa_num(round((float)$rep['attempt']['total_score'],1)) ?>٪</b></div>
    <div><span>درست</span><b><?= fa_num($rep['attempt']['correct_count']) ?></b></div>
    <div><span>غلط</span><b><?= fa_num($rep['attempt']['wrong_count']) ?></b></div>
    <div><span>نزده</span><b><?= fa_num($rep['attempt']['blank_count']) ?></b></div>
  </div>
  <div class="issues-preview"><?php foreach(array_slice($issues,0,24) as $it): ?><span><?= fa_num($it['question_number']) ?> · <?= e($it['subject']) ?> · <?= $it['type']==='blank'?'نزده':'غلط' ?></span><?php endforeach; ?><?php if(count($issues)>24): ?><span>+<?= fa_num(count($issues)-24) ?> سوال دیگر</span><?php endif; ?></div>
</div>

<?php if($an): ?>
<div class="mock-analysis panel">
  <div class="ma-head"><div class="ma-score"><?= fa_num($an['overall']) ?>٪</div><div><b>تحلیل هوشمند آزمون داخلی <span class="badge badge-gold">مَدار</span></b><span><?= e($an['overall_label']) ?></span></div></div>
  <p><?= e($an['summary'] ?? '') ?></p>
  <?php if(!empty($an['alerts'])): ?><div class="ma-alerts"><?php foreach($an['alerts'] as $al): ?><div class="ia <?= e($al['level']) ?>"><b><?= e($al['title']) ?></b><span><?= e($al['text']) ?></span></div><?php endforeach; ?></div><?php endif; ?>
  <div class="ma-grid"><?php foreach(['result'=>'نتیجه','accuracy'=>'دقت','target'=>'هدف','risk'=>'ریسک'] as $k=>$lbl): ?><div><span><?= e($lbl) ?></span><b><?= fa_num($an['scores'][$k]??0) ?>٪</b></div><?php endforeach; ?></div>
  <div class="ma-recs"><b>پیشنهادهای عملی</b><ul><?php foreach(($an['recommendations']??[]) as $rec): ?><li><?= e($rec) ?></li><?php endforeach; ?></ul></div>
  <div class="ma-recs action"><b>نقشه اقدام</b><ul><?php foreach(($an['action_plan']??[]) as $rec): ?><li><?= e($rec) ?></li><?php endforeach; ?></ul></div>
</div>
<?php endif; ?>

<form method="post" class="panel mock-form" id="internalAnalysisForm">
  <?= csrf_field() ?>
  <h3><?= icon('sparkles',18) ?> رفتار آزمونی و خودارزیابی</h3>
  <div class="grid gap-3" style="grid-template-columns:repeat(4,1fr)">
    <div class="field"><label>خواب شب قبل</label><input class="input" name="sleep_hours" value="<?= e((string)($beh['sleep_hours']??'')) ?>" placeholder="مثلاً ۶.۵"></div>
    <div class="field"><label>استرس ۱ تا ۱۰</label><input class="input" name="stress_score" value="<?= e((string)($beh['stress_score']??'')) ?>"></div>
    <div class="field"><label>تمرکز ۱ تا ۱۰</label><input class="input" name="focus_score" value="<?= e((string)($beh['focus_score']??'')) ?>"></div>
    <div class="field"><label>مدیریت زمان</label><select class="select" name="time_management"><option></option><?php foreach(['عالی','خوب','متوسط','ضعیف','خیلی بد'] as $x): ?><option <?= ($beh['time_management']??'')===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
  </div>
  <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
    <div class="field"><label>علت اصلی نتیجه</label><textarea class="input" name="main_cause" rows="3"><?= e($beh['main_cause']??'') ?></textarea></div>
    <div class="field"><label>الگوی غلط‌ها</label><textarea class="input" name="mistake_pattern" rows="3"><?= e($beh['mistake_pattern']??'') ?></textarea></div>
    <div class="field"><label>بهترین کار در آزمون</label><textarea class="input" name="best_action" rows="3"><?= e($beh['best_action']??'') ?></textarea></div>
    <div class="field"><label>بدترین اشتباه آزمون</label><textarea class="input" name="worst_action" rows="3"><?= e($beh['worst_action']??'') ?></textarea></div>
    <div class="field"><label>استراتژی آزمون بعدی</label><textarea class="input" name="next_strategy" rows="3"><?= e($beh['next_strategy']??'') ?></textarea></div>
    <div class="field"><label>یادداشت آزاد برای مشاور</label><textarea class="input" name="student_note" rows="3"><?= e($r['student_note']??'') ?></textarea></div>
  </div>

  <?php if (!empty($issues)): ?>
  <div style="margin-top: 24px; border-top: 1px dashed #dfe7df; padding-top: 20px; margin-bottom: 24px;">
    <h3><?= icon('list',18) ?> ریشه‌یابی و علت‌یابی سوالات نادرست و نزده</h3>
    <p class="muted mb-3" style="font-size: 13px;">برای تحلیل رفتاری دقیق‌تر و هوشمند، لطفا علت خطای خود در هر یک از سوالات زیر را مشخص کنید:</p>
    <div style="display: flex; flex-direction: column; gap: 10px;">
      <?php foreach($issues as $it): $qNum = (int)$it['question_number']; ?>
        <div style="background: rgba(240, 244, 241, 0.5); border: 1px solid #dfe7df; border-radius: 12px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
          <div class="flex gap-2" style="align-items: center;">
            <span class="badge badge-gold" style="font-size: 12px; font-weight: bold; background: #2e4438; color: #fff;">سوال <?= fa_num($qNum) ?></span>
            <span class="badge badge-sage" style="font-size: 11px;"><?= e($it['subject']) ?></span>
            <span class="badge" style="font-size: 11px; background: <?= $it['type']==='blank'?'#f0ece1':'#fceeed' ?>; color: <?= $it['type']==='blank'?'#856404':'#721c24' ?>;">
              <?= $it['type']==='blank'?'نزده':'غلط' ?>
            </span>
          </div>
          
          <div class="field" style="margin: 0; min-width: 280px; flex-grow: 1; max-width: 450px;">
            <select class="select" name="issues[<?= $qNum ?>][reason]" style="width: 100%;">
              <option value="unknown">علت را انتخاب کنید...</option>
              <?php if($it['type'] === 'blank'): ?>
                <option value="not_studied" <?= ($it['reason']??'')==='not_studied'?'selected':'' ?>>عدم مطالعه یا حذف مبحث از قبل</option>
                <option value="not_mastered" <?= ($it['reason']??'')==='not_mastered'?'selected':'' ?>>عدم تسلط کافی (با وجود مطالعه مبحث)</option>
                <option value="no_time" <?= ($it['reason']??'')==='no_time'?'selected':'' ?>>کمبود زمان (اصلاً به سوال نرسیدم)</option>
                <option value="too_hard" <?= ($it['reason']??'')==='too_hard'?'selected':'' ?>>دشواری بیش از حد سوال (ارزش ریسک نداشت)</option>
                <option value="doubt_many" <?= ($it['reason']??'')==='doubt_many'?'selected':'' ?>>شک بین سه یا چهار گزینه</option>
                <option value="strategy" <?= ($it['reason']??'')==='strategy'?'selected':'' ?>>استراتژی اشتباه در اولویت‌بندی سوال</option>
              <?php else: ?>
                <option value="concept" <?= ($it['reason']??'')==='concept'?'selected':'' ?>>ضعف علمی و مفهومی (عدم درک مطلب)</option>
                <option value="careless_calc" <?= ($it['reason']??'')==='careless_calc'?'selected':'' ?>>بی‌دقتی در محاسبات عددی</option>
                <option value="careless_read" <?= ($it['reason']??'')==='careless_read'?'selected':'' ?>>بی‌دقتی در خواندن صورت سوال یا گزینه‌ها</option>
                <option value="forgot" <?= ($it['reason']??'')==='forgot'?'selected':'' ?>>فراموشی فرمول، فرضیه یا نکته کلیدی</option>
                <option value="doubt" <?= ($it['reason']??'')==='doubt'?'selected':'' ?>>شک بین دو گزینه (انتخاب اشتباه)</option>
                <option value="trap" <?= ($it['reason']??'')==='trap'?'selected':'' ?>>افتادن در تله آموزشی/علمی طراح</option>
                <option value="time_rush" <?= ($it['reason']??'')==='time_rush'?'selected':'' ?>>کمبود زمان و حل شتاب‌زده</option>
                <option value="bubble_err" <?= ($it['reason']??'')==='bubble_err'?'selected':'' ?>>اشتباه در وارد کردن گزینه در پاسخبرگ</option>
              <?php endif; ?>
              <option value="unknown" <?= ($it['reason']??'')==='unknown'?'selected':'' ?>>سایر موارد / نامشخص</option>
            </select>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <button class="btn btn-gold btn-lg" style="font-weight:900"><?= icon('check',18) ?> ثبت و ساخت تحلیل آزمون داخلی</button>
</form>

<div class="panel mt-4"><h3><?= icon('list',18) ?> تحلیل‌های داخلی قبلی</h3><?php if(!$reports): ?><div class="empty-state">هنوز تحلیلی ثبت نشده</div><?php else: ?><div class="mock-list"><?php foreach($reports as $it): ?><a href="?attempt=<?= (int)$it['attempt_id'] ?>"><b><?= e($it['exam_title']) ?></b><span><?= jalali_date($it['submitted_at'],true) ?> · نمره <?= fa_num(round((float)$it['total_score'],1)) ?>٪</span></a><?php endforeach; ?></div><?php endif; ?></div>
<?php panel_end(); ?>
