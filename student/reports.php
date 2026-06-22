<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reporting.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('student');
$u = current_user();

report_lock_past_daily_reports((int)$u['id']);

// اگر صفحه بدون پارامتر باز شد، به گزارش هفتگی همین هفته هدایت کن تا همیشه صفحه پایدار و قابل نمایش باشد.
// گزارش روزانه همچنان از تب‌ها و لینک مستقیم در دسترس است.
if (!isset($_GET['type']) && !isset($_GET['date'])) {
    redirect('student/reports.php?type=weekly&date=' . week_saturday());
}
$type = in_array($_GET['type'] ?? 'weekly', ['daily','weekly','monthly'], true) ? $_GET['type'] : 'weekly';
$date = (string)($_GET['date'] ?? week_saturday());
[$start,$end] = report_period($type,$date);
// گزارش روزانه فقط تا پایان همان روز (ساعت ۰۰:۰۰) باز است — بعدش قفل!
$isLocked = ($type === 'daily' && $start < date('Y-m-d'));
try {
    $report = report_get_or_create((int)$u['id'],$type,$start);
} catch (Throwable $e) {
    if (APP_ENV === 'development') { throw $e; }
    error_log('Madar report page error: '.$e->getMessage());
    flash('error','گزارش‌ها فعلاً آماده نیستند. لطفاً کمی بعد دوباره تلاش کن.');
    redirect('student/progress.php');
}
$snap = $report['snapshot'] ?? [];
$adv = $report['advanced'] ?? [];
$snap += ['progress_percent'=>0,'full'=>0,'partial'=>0,'missed'=>0,'study_hours'=>0,'tests_done'=>0,'target_tests'=>0,'extra_tests'=>0,'by_subject'=>[]];
$showInsight = advisor_feature_enabled((int)($u['advisor_id'] ?? 0), 'insight_enabled');
$analysis = $showInsight ? report_build_analysis((int)$u['id'], $type, $start, $end, $snap, $adv) : null;
$prevDate = $type==='monthly' ? date('Y-m-d', strtotime($start.' -1 month')) : ($type==='weekly' ? date('Y-m-d', strtotime($start.' -7 day')) : date('Y-m-d', strtotime($start.' -1 day')));
$nextDate = $type==='monthly' ? date('Y-m-d', strtotime($start.' +1 month')) : ($type==='weekly' ? date('Y-m-d', strtotime($start.' +7 day')) : date('Y-m-d', strtotime($start.' +1 day')));
$pending = report_pending_items((int)$u['id']);
panel_start('گزارش‌دهی پیشرفته', report_type_label($type).' · '.jalali_date($start).($start!==$end?' تا '.jalali_date($end):''), 'student', 'reports', ['student.css']);
?>
<div class="report-tabs mb-4">
  <?php foreach(['daily'=>'روزانه','weekly'=>'هفتگی','monthly'=>'ماهانه'] as $k=>$lbl): ?>
  <a class="chip <?= $type===$k?'active':'' ?>" href="?type=<?= $k ?>&date=<?= $k==='daily'?date('Y-m-d'):$start ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
</div>

<div class="between wrap gap-3 mb-6" style="background:var(--surface-1); padding:16px 24px; border-radius:var(--r-md); border:1px solid var(--border);">
  <div class="flex items-center gap-3">
    <?= icon('chart', 20, 'text-gold') ?>
    <b style="font-size: 1.05rem; color: var(--text);">خروجی چاپی عملکرد و تحلیل هوشمند مَدار:</b>
  </div>
  <div class="flex items-center gap-3 wrap">
    <a href="<?= url('admin/student_report_pdf.php?student=' . $u['id'] . '&type=' . $type) ?>" target="_blank" class="btn btn-gold flex items-center gap-2 shadow-lg" style="font-weight: 900;">
      <?= icon('pie', 18) ?> <span>دریافت PDF گزارش پیشرفته و تحلیل مَدار</span>
    </a>
  </div>
</div>

<?php if($pending): ?>
<div class="panel report-due mb-4">
  <b><?= icon('bell',18) ?> گزارش‌های در انتظار</b>
  <div class="report-due-list">
    <?php foreach($pending as $p): ?><a href="<?= e($p['url']) ?>" class="badge badge-gold"><?= e($p['label']) ?> · <?= jalali_date($p['start']) ?></a><?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="between mb-4 wrap gap-2">
  <div class="week-nav flex gap-2" style="align-items:center">
    <a class="btn btn-ghost btn-icon" href="?type=<?= $type ?>&date=<?= $prevDate ?>"><?= icon('chevron-right',18) ?></a>
    <span class="fw-700"><?= jalali_date($start) ?><?= $start!==$end?' تا '.jalali_date($end):'' ?></span>
    <a class="btn btn-ghost btn-icon" href="?type=<?= $type ?>&date=<?= $nextDate ?>"><?= icon('chevron-left',18) ?></a>
  </div>
  <span class="badge <?= $report['status']==='submitted'?'badge-sage':'badge-gold' ?>"><?= $report['status']==='submitted'?'ارسال شده':'نیاز به تکمیل' ?></span>
</div>

<div class="report-hero panel mb-4">
  <div><span class="muted">گزارش خودکار سیستم</span><h2>عملکرد <?= e(report_type_label($type)) ?></h2><p>این بخش به‌صورت خودکار از عملکرد همین بازه ساخته شده و همیشه قابل پیگیری است.</p></div>
  <div class="report-score"><b><?= fa_num($snap['progress_percent']) ?>٪</b><span>پیشرفت وزنی</span></div>
</div>


<?php if($showInsight && $analysis): ?>
<div class="panel insight-closed mb-4">
  <div><b><?= icon('sparkles',18) ?> تحلیل هوشمند مَدار <span class="beta-pill">بتا</span></b><span>خلاصه و پیشنهادهای دقیق این بازه آماده است.</span></div>
  <button class="btn btn-gold btn-sm" type="button" data-modal="insightModal">مشاهده تحلیل</button>
</div>
<div class="modal-backdrop" id="insightModal">
  <div class="modal insight-modal">
    <div class="modal-head"><h3><?= icon('sparkles',20) ?> تحلیل هوشمند مَدار <span class="beta-pill">بتا</span></h3><button class="modal-close" data-close><?= icon('close',18) ?></button></div>
    <div class="insight-score-head" style="display:flex;flex-wrap:wrap;gap:16px;justify-content:space-between;align-items:center;background:var(--surface-2);border:1px solid var(--border-soft);padding:18px 24px;border-radius:var(--r-lg);margin-bottom:20px">
      <div style="display:flex;align-items:center;gap:16px">
        <div style="background:var(--gold-glass);color:var(--gold);width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:900;box-shadow:0 0 16px rgba(217,178,95,0.2)">
          <?= fa_num($analysis['overall']) ?>٪
        </div>
        <div style="text-align:r">
          <div style="font-size:.78rem;color:var(--gold);text-transform:uppercase;letter-spacing:1px;font-weight:700">شاخص بازدهی و کیفیت مطالعاتی</div>
          <div style="font-size:1.1rem;font-weight:900;color:var(--text-1);margin-top:2px">نمره ارزیابی کلی سیستم</div>
        </div>
      </div>
      
      <div style="text-align:l">
        <div style="font-size:.78rem;color:var(--text-3)">ارزیابی وضعیت برنامه:</div>
        <span class="badge badge-gold" style="font-size:1rem;padding:6px 16px;font-weight:800;margin-top:4px;display:inline-block">
          <?= e($analysis['overall_label']) ?>
        </span>
      </div>
    </div>
    <p class="insight-summary"><?= e($analysis['summary']) ?></p>
    <div class="insight-grid">
      <?php foreach(['execution'=>'اجرای برنامه','consistency'=>'ثبات','tests'=>'تست‌زنی','study_quality'=>'کیفیت مطالعه','recovery'=>'خواب و انرژی','subject_balance'=>'تعادل درس‌ها','distraction_control'=>'کنترل حاشیه'] as $k=>$lbl): $v=(int)($analysis['scores'][$k]??0); ?>
      <div class="insight-metric"><span><?= e($lbl) ?></span><b><?= fa_num($v) ?>٪</b><div class="progress"><span style="width:<?= min(100,$v) ?>%"></span></div></div>
      <?php endforeach; ?>
    </div>
    <?php if($analysis['alerts']): ?><div class="insight-alerts"><?php foreach($analysis['alerts'] as $al): ?><div class="ia <?= e($al['level']) ?>"><b><?= e($al['title']) ?></b><span><?= e($al['text']) ?></span></div><?php endforeach; ?></div><?php endif; ?>
    <div class="insight-recs"><b>پیشنهادهای عملی</b><ul><?php foreach($analysis['recommendations'] as $rec): ?><li><?= e($rec) ?></li><?php endforeach; ?></ul></div>
    <?php if(!empty($analysis['action_plan'])): ?><div class="insight-recs action"><b>نقشه اقدام کوتاه</b><ul><?php foreach($analysis['action_plan'] as $rec): ?><li><?= e($rec) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if(!empty($analysis['method_notes'])): ?><div class="insight-method"><b><?= icon('book',16) ?> پشتوانه تحلیلی</b><?php foreach($analysis['method_notes'] as $note): ?><span><?= e($note) ?></span><?php endforeach; ?></div><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="stat-cards mb-4">
  <div class="panel stat"><span class="icon-tile sage">✓</span><div><div class="v"><?= fa_num($snap['full']) ?></div><div class="k">کامل</div></div></div>
  <div class="panel stat"><span class="icon-tile">●</span><div><div class="v"><?= fa_num($snap['partial']) ?></div><div class="k">ناقص</div></div></div>
  <div class="panel stat"><span class="icon-tile" style="background:rgba(217,116,116,.16);color:#ff9a9a">×</span><div><div class="v"><?= fa_num($snap['missed']) ?></div><div class="k">قرمز</div></div></div>
  <div class="panel stat"><span class="icon-tile sage"><?= icon('clock',22) ?></span><div><div class="v"><?= fa_num($snap['study_hours']) ?></div><div class="k">ساعت مؤثر</div></div></div>
  <div class="panel stat"><span class="icon-tile" style="background:var(--gold-glass);color:var(--gold-light)"><?= icon('check',20) ?></span><div><div class="v"><?= fa_num($snap['tests_done']) ?></div><div class="k">تست زده‌شده</div></div></div>
  <div class="panel stat"><span class="icon-tile" style="background:var(--gold-glass);color:var(--gold-light)">+</span><div><div class="v"><?= fa_num($snap['extra_tests']) ?></div><div class="k">تست اضافه</div></div></div>
</div>

<div class="panel mb-4">
  <div class="panel-head"><h3><?= icon('pie',20) ?> ریز عملکرد درس‌ها</h3></div>
  <?php if(empty($snap['by_subject'])): ?><div class="empty-state">داده‌ای برای این بازه نیست</div><?php else: ?>
  <div class="report-subjects">
    <?php foreach($snap['by_subject'] as $name=>$r): $pct=$r['tasks']?round($r['score']/$r['tasks']*100):0; ?>
    <div class="report-subj"><b><?= e($name) ?></b><span><?= fa_num($pct) ?>٪ · <?= fa_num($r['tests']) ?> تست · <?= fa_num(round($r['minutes']/60,1)) ?> ساعت</span><div class="progress"><span style="width:<?= min(125,$pct) ?>%"></span></div></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<form class="panel report-form" id="reportForm">
  <div class="panel-head"><h3><?= icon('edit',20) ?> گزارش تکمیلی <?= e(report_type_label($type)) ?></h3><span class="muted">فقط موارد مهم همین بازه</span></div>
  <?php if($isLocked): ?>
    <div class="alert alert-error" style="margin: 16px 0; background: rgba(220, 53, 69, 0.1); border: 1px solid rgba(220, 53, 69, 0.2); color: #ea868f; padding: 12px 16px; border-radius: 12px; display: flex; align-items: center; gap: 10px;">
      <?= icon('lock', 20) ?>
      <span style="font-weight: bold; font-size: 13.5px;">⚠️ این گزارش قفل شده است! شما فقط تا پایان هر روز فرصت دارید گزارش روزانه همان روز را ثبت یا ویرایش کنید.</span>
    </div>
  <?php endif; ?>
  <div class="form-grid">
    <div class="field"><label>جمع/میانگین خواب مفید</label><input class="input" type="number" step="0.25" min="0" max="16" name="sleep_hours" value="<?= e((string)($adv['sleep_hours']??'')) ?>" placeholder="مثلاً ۷.۵"></div>
    <div class="field"><label>کیفیت خواب</label><select class="input" name="sleep_quality"><?php for($i=1;$i<=5;$i++): ?><option value="<?= $i ?>" <?= (string)($adv['sleep_quality']??'')===(string)$i?'selected':'' ?>><?= fa_num($i) ?> از ۵</option><?php endfor; ?></select></div>
    <div class="field"><label>تمرکز</label><input class="input" type="number" min="1" max="10" name="focus_score" value="<?= e((string)($adv['focus_score']??'')) ?>" placeholder="۱ تا ۱۰"></div>
    <div class="field"><label>انرژی</label><input class="input" type="number" min="1" max="10" name="energy_score" value="<?= e((string)($adv['energy_score']??'')) ?>" placeholder="۱ تا ۱۰"></div>
    <div class="field"><label>استرس</label><input class="input" type="number" min="1" max="10" name="stress_score" value="<?= e((string)($adv['stress_score']??'')) ?>" placeholder="۱ تا ۱۰"></div>
    <div class="field"><label>زمان موبایل/حاشیه (دقیقه)</label><input class="input" type="number" min="0" name="phone_minutes" value="<?= e((string)($adv['phone_minutes']??'')) ?>"></div>
    <div class="field"><label>اتلاف وقت تخمینی (دقیقه)</label><input class="input" type="number" min="0" name="wasted_minutes" value="<?= e((string)($adv['wasted_minutes']??'')) ?>"></div>
    <div class="field"><label>نمره خودارزیابی</label><input class="input" type="number" min="1" max="20" name="self_score" value="<?= e((string)($adv['self_score']??'')) ?>" placeholder="از ۲۰"></div>
    <?php if($type==='daily'): ?>
    <div class="field"><label>علت اصلی کامل اجرا نشدن امروز</label><select class="input" name="main_reason">
      <?php foreach([''=>'انتخاب کن','کمبود وقت'=>'کمبود وقت','خستگی'=>'خستگی','سختی مبحث'=>'سختی مبحث','حواس‌پرتی/موبایل'=>'حواس‌پرتی/موبایل','برنامه سنگین بود'=>'برنامه سنگین بود','مدرسه/کلاس'=>'مدرسه/کلاس','مشکل شخصی'=>'مشکل شخصی'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($adv['main_reason']??'')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select></div>
    <?php elseif($type==='weekly'): ?>
    <div class="field"><label>ارزیابی کلی هفته</label><select class="input" name="week_rating"><?php foreach(['عالی','خوب','متوسط','ضعیف'] as $v): ?><option value="<?= e($v) ?>" <?= ($adv['week_rating']??'')===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>تناسب برنامه هفته</label><select class="input" name="plan_fit"><?php foreach(['مناسب','سبک','سنگین','نامتعادل'] as $v): ?><option value="<?= e($v) ?>" <?= ($adv['plan_fit']??'')===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>نیاز به پیگیری مشاور</label><select class="input" name="advisor_followup"><?php foreach(['خیر','بله معمولی','بله فوری'] as $v): ?><option value="<?= e($v) ?>" <?= ($adv['advisor_followup']??'')===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <?php else: ?>
    <div class="field"><label>رضایت کلی ماه</label><input class="input" type="number" min="1" max="10" name="month_satisfaction" value="<?= e((string)($adv['month_satisfaction']??'')) ?>" placeholder="۱ تا ۱۰"></div>
    <div class="field"><label>وضعیت روحی کلی ماه</label><select class="input" name="monthly_mindset"><?php foreach(['پایدار','خسته','پراسترس','بی‌انگیزه','رو به رشد'] as $v): ?><option value="<?= e($v) ?>" <?= ($adv['monthly_mindset']??'')===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>هدف اصلی ماه بعد</label><select class="input" name="next_month_goal_type"><?php foreach(['افزایش ساعت','افزایش تست','جبران عقب‌ماندگی','تثبیت','جمع‌بندی','آزمون‌محور شدن'] as $v): ?><option value="<?= e($v) ?>" <?= ($adv['next_month_goal_type']??'')===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <?php if($type!=='daily'): ?>
    <div class="field"><label>بهترین درس این بازه</label><input class="input" name="best_subject" value="<?= e($adv['best_subject']??'') ?>"></div>
    <div class="field"><label>درس نیازمند رسیدگی</label><input class="input" name="weak_subject" value="<?= e($adv['weak_subject']??'') ?>"></div>
    <div class="field"><label>تعداد آزمون/آزمونک</label><input class="input" type="number" min="0" name="exam_count" value="<?= e((string)($adv['exam_count']??'')) ?>"></div>
    <div class="field"><label>کیفیت تحلیل آزمون</label><select class="input" name="exam_analysis_quality"><?php for($i=1;$i<=5;$i++): ?><option value="<?= $i ?>" <?= (string)($adv['exam_analysis_quality']??'')===(string)$i?'selected':'' ?>><?= fa_num($i) ?> از ۵</option><?php endfor; ?></select></div>
    <?php endif; ?>
  </div>
  <div class="form-grid cols-2 mt-4">
    <div class="field"><label>برد امروز/این بازه چی بود؟</label><textarea class="input" name="best_win" rows="3"><?= e($adv['best_win']??'') ?></textarea></div>
    <div class="field"><label>چالش اصلی</label><textarea class="input" name="main_challenge" rows="3"><?= e($adv['main_challenge']??'') ?></textarea></div>
    <div class="field"><label>علت احتمالی چالش</label><textarea class="input" name="challenge_reason" rows="3"><?= e($adv['challenge_reason']??'') ?></textarea></div>
    <div class="field"><label>راهکار پیشنهادی خودت</label><textarea class="input" name="solution" rows="3"><?= e($adv['solution']??'') ?></textarea></div>
    <div class="field"><label>اولویت فردا/بازه بعد</label><textarea class="input" name="next_priority" rows="3"><?= e($adv['next_priority']??'') ?></textarea></div>
    <div class="field"><label>سؤال از مشاور</label><textarea class="input" name="advisor_question" rows="3"><?= e($adv['advisor_question']??'') ?></textarea></div>
  </div>
  <button class="btn btn-gold btn-block mt-4" type="submit"><?= icon('check',16) ?> ثبت و ارسال گزارش</button>
</form>
<script>
window.API_REPORTS='<?= url('api/reports.php') ?>';
<?php if($isLocked): ?>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('reportForm');
  if (form) {
    form.querySelectorAll('input, select, textarea, button[type="submit"]').forEach(el => {
      el.disabled = true;
    });
  }
});
<?php endif; ?>

document.getElementById('reportForm')?.addEventListener('submit', async (e)=>{
  e.preventDefault();
  <?php if($isLocked): ?>
  toast('این گزارش قفل شده است و امکان ارسال یا تغییر ندارد.','error');
  return;
  <?php endif; ?>
  const fd=new FormData(e.currentTarget), advanced={}; fd.forEach((v,k)=>advanced[k]=v);
  try{ await api(window.API_REPORTS,{method:'POST',body:{action:'submit',report_type:'<?= $type ?>',date:'<?= $start ?>',advanced}}); toast('گزارش با موفقیت ثبت شد','success'); setTimeout(()=>location.reload(),700); }
  catch(err){ toast(err.error||'خطا در ثبت گزارش','error'); }
});
</script>
<?php panel_end(['student.js']); ?>
