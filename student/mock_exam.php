<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/mock_exam.php';
boot_session(); require_role('student');
$u=current_user(); $editId=(int)($_GET['id']??0); $editing=$editId?mock_report($editId):null;
if($editing && !mock_can_view($editing,$u)){ flash('error','گزارش یافت نشد'); redirect('student/mock_exam.php'); }
if($_SERVER['REQUEST_METHOD']==='POST'){
  require_csrf();
  try{ $id=mock_report_save((int)$u['id'], $_POST); flash('success','تحلیل آزمون با موفقیت ثبت شد.'); redirect('student/mock_exam.php?id='.$id.'&saved=1'); }
  catch(Throwable $e){ flash('error', APP_ENV==='development'?$e->getMessage():'خطا در ثبت گزارش آزمون'); }
}
$reports=mock_reports_for_student((int)$u['id']);
$r=$editing; $subj=$r['subjects']??[]; $beh=$r['behavior']??[]; $an=$r['analysis']??null; $issues=$r['issues']??[];
panel_start('تحلیل آزمون آزمایشی/کنکور','ثبت و تحلیل آزمون‌های بیرونی', 'student','exam_analyses',['mock_exam.css']);
?>
<div class="mock-hero panel">
  <div style="flex:1;min-width:0">
    <span class="badge badge-gold"><?= icon('target',15) ?> تحلیل آزمون بیرونی</span>
    <h2>تحلیل آزمون آزمایشی/کنکور</h2>
    <p>نتیجه آزمون‌های قلمچی، سنجش، گزینه‌دو، ماز یا کنکور را وارد کن تا مَدار تحلیل هوشمند و برنامه اقدام بدهد.</p>
  </div>
  <div class="mock-hero-actions">
    <?php if($r): ?><a target="_blank" class="btn btn-gold" href="<?= url('student/mock_exam_pdf.php?id='.(int)$r['id']) ?>"><?= icon('clipboard',16) ?> خروجی PDF</a><?php endif; ?>
    <button type="button" class="btn btn-ghost" id="fillMockSample">پر کردن نمونه</button>
  </div>
</div>

<?php if($an): ?>
<div class="mock-analysis panel">
  <div class="ma-head">
    <div class="ma-score"><?= fa_num($an['overall']) ?>٪</div>
    <div class="ma-head-info">
      <b>تحلیل هوشمند مَدار <span class="badge badge-gold">بتا</span></b>
      <span><?= e($an['overall_label']) ?></span>
    </div>
  </div>
  <p><?= e($an['summary'] ?? '') ?></p>
  <?php if(!empty($an['alerts'])): ?>
  <div class="ma-alerts">
    <?php foreach($an['alerts'] as $al): ?>
    <div class="ia <?= e($al['level']) ?>">
      <b><?= e($al['title']) ?></b>
      <span><?= e($al['text']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="ma-grid">
    <?php foreach(['result'=>'نتیجه','accuracy'=>'دقت','target'=>'هدف','risk'=>'ریسک'] as $k=>$lbl): ?>
    <div><span><?= e($lbl) ?></span><b><?= fa_num($an['scores'][$k]??0) ?>٪</b></div>
    <?php endforeach; ?>
  </div>
  <div class="ma-recs"><b>پیشنهادهای عملی</b>
    <ul>
      <?php foreach(($an['recommendations']??[]) as $rec): ?><li><?= e($rec) ?></li><?php endforeach; ?>
    </ul>
  </div>
  <div class="ma-recs" style="background:rgba(203,172,128,.08);border-color:rgba(203,172,128,.20)"><b>نقشه اقدام</b>
    <ul>
      <?php foreach(($an['action_plan']??[]) as $rec): ?><li><?= e($rec) ?></li><?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<form method="post" class="panel mock-form" id="mockForm">
  <?= csrf_field() ?><?php if($r): ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><?php endif; ?>

  <h3><?= icon('edit',18) ?> اطلاعات کلی آزمون</h3>
  <div class="grid gap-3 grid-4">
    <div class="field">
      <label><span class="ic">🏛</span> کجا آزمون دادی؟</label>
      <select class="select" name="provider">
        <?php foreach(MOCK_PROVIDERS as $p): ?><option <?= ($r['provider']??'')===$p?'selected':'' ?>><?= e($p) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label><span class="ic">📋</span> نام آزمون</label>
      <input class="input" name="exam_title" value="<?= e($r['exam_title']??'') ?>" placeholder="مثلاً جامع ۲۷ تیر">
    </div>
    <div class="field">
      <label><span class="ic">📅</span> تاریخ آزمون</label>
      <input class="input" type="date" name="exam_date" value="<?= e($r['exam_date']??date('Y-m-d')) ?>">
    </div>
    <div class="field">
      <label><span class="ic">🏫</span> اگر سایر، نام محل</label>
      <input class="input" name="provider_other" value="<?= e($r['provider_other']??'') ?>" placeholder="نام موسسه/مدرسه">
    </div>
  </div>

  <div class="grid gap-3 grid-6">
    <div class="field">
      <label><span class="ic">🎯</span> تراز / نمره کل</label>
      <input class="input" inputmode="numeric" name="total_score" value="<?= e((string)($r['total_score']??'')) ?>" placeholder="مثلاً ۶۸۵۰">
    </div>
    <div class="field">
      <label><span class="ic">📊</span> درصد کل</label>
      <input class="input" inputmode="numeric" name="total_percent" value="<?= e((string)($r['total_percent']??'')) ?>" placeholder="۰ تا ۱۰۰">
    </div>
    <div class="field">
      <label><span class="ic">🏆</span> رتبه</label>
      <input class="input" inputmode="numeric" name="rank_in_exam" value="<?= e((string)($r['rank_in_exam']??'')) ?>" placeholder="مثلاً ۱۲۴۰">
    </div>
    <div class="field">
      <label><span class="ic">👥</span> تعداد شرکت‌کننده</label>
      <input class="input" inputmode="numeric" name="participants" value="<?= e((string)($r['participants']??'')) ?>" placeholder="مثلاً ۱۸۵۰۰">
    </div>
    <div class="field">
      <label><span class="ic">📝</span> تعداد کل سوالات</label>
      <input class="input" inputmode="numeric" name="total_questions" value="<?= e((string)($r['total_questions']??'')) ?>" placeholder="مثلاً ۱۲۰">
    </div>
    <div class="field">
      <label><span class="ic">🎖</span> هدف/تراز مورد انتظار</label>
      <input class="input" inputmode="numeric" name="target_score" value="<?= e((string)($r['target_score']??'')) ?>" placeholder="مثلاً ۷۲۰۰">
    </div>
  </div>

  <h3>
    <?= icon('book',18) ?> ریزنتیجه درس‌ها
    <span class="muted">برای هر درس، تعداد درست/غلط/نزده و درصد را وارد کن</span>
  </h3>
  <div class="mock-subject-wrap" id="mockSubjectRows">
    <?php
      $rows=$subj ?: array_map(fn($n)=>['name'=>$n], array_slice(MOCK_SUBJECTS,0,6));
      foreach($rows as $i=>$s):
        $sname = $s['name']??'';
    ?>
      <div class="mock-subject-row" data-row-i="<?= $i ?>">
        <button type="button" class="ms-delete" data-del-row aria-label="حذف">×</button>

        <div class="ms-field" style="grid-column:1 / -1">
          <label><span class="ic">📚</span> نام درس</label>
          <input class="input" name="subjects[<?= $i ?>][name]" value="<?= e($sname) ?>" placeholder="مثلاً: ریاضی، فیزیک، شیمی، زیست">
        </div>

        <div class="ms-field">
          <label><span class="ic">⏮</span> از سوال</label>
          <input class="input" inputmode="numeric" name="subjects[<?= $i ?>][q_from]" value="<?= e((string)($s['q_from']??'')) ?>" placeholder="۱">
        </div>
        <div class="ms-field">
          <label><span class="ic">⏭</span> تا سوال</label>
          <input class="input" inputmode="numeric" name="subjects[<?= $i ?>][q_to]" value="<?= e((string)($s['q_to']??'')) ?>" placeholder="۳۰">
        </div>

        <div class="ms-field">
          <label><span class="ic">✅</span> درست</label>
          <input class="input" inputmode="numeric" name="subjects[<?= $i ?>][correct]" value="<?= e((string)($s['correct']??'')) ?>" placeholder="مثلاً ۸">
        </div>
        <div class="ms-field">
          <label><span class="ic">❌</span> غلط</label>
          <input class="input" inputmode="numeric" name="subjects[<?= $i ?>][wrong]" value="<?= e((string)($s['wrong']??'')) ?>" placeholder="مثلاً ۴">
        </div>
        <div class="ms-field">
          <label><span class="ic">⭕</span> نزده</label>
          <input class="input" inputmode="numeric" name="subjects[<?= $i ?>][blank]" value="<?= e((string)($s['blank']??'')) ?>" placeholder="مثلاً ۲">
        </div>
        <div class="ms-field">
          <label><span class="ic">📈</span> درصد</label>
          <input class="input" inputmode="numeric" name="subjects[<?= $i ?>][percent]" value="<?= e((string)($s['percent']??'')) ?>" placeholder="۰ تا ۱۰۰">
        </div>
        <div class="ms-field">
          <label><span class="ic">⏱</span> زمان (دقیقه)</label>
          <input class="input" inputmode="numeric" name="subjects[<?= $i ?>][time_min]" value="<?= e((string)($s['time_min']??'')) ?>" placeholder="مثلاً ۲۵">
        </div>
        <div class="ms-field">
          <label><span class="ic">🏅</span> رتبه در درس</label>
          <input class="input" inputmode="numeric" name="subjects[<?= $i ?>][rank]" value="<?= e((string)($s['rank']??'')) ?>" placeholder="اختیاری">
        </div>

        <div class="ms-field wide">
          <label><span class="ic">📝</span> یادداشت / علت افت</label>
          <input class="input" name="subjects[<?= $i ?>][note]" value="<?= e($s['note']??'') ?>" placeholder="مثلاً: زمان کم آوردم، مفهوم تعادل را بلد نبودم">
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <button type="button" class="btn btn-ghost btn-sm" id="addSubjectRow" style="min-height:46px"><?= icon('plus',14) ?> افزودن درس جدید</button>

  <h3><?= icon('target',18) ?> تحلیل سوالات غلط و نزده</h3>
  <div class="mock-issues-box">
    <div style="flex:1;min-width:220px">
      <b>برای تحلیل دقیق، سوال‌هایی که غلط زدی یا نزدی را ثبت کن.</b>
      <span>علت هر سوال باعث می‌شود تحلیل هوشمند مَدار بفهمد مشکل اصلی تو مفهوم، زمان، بی‌دقتی یا استراتژی بوده.</span>
    </div>
    <button type="button" class="btn btn-gold" id="openIssueModal" style="min-height:46px"><?= icon('plus',16) ?> ثبت علت سوال‌ها</button>
  </div>
  <div id="issuesPreview" class="issues-preview"></div>
  <div id="issuesHiddenFields"></div>

  <h3><?= icon('sparkles',18) ?> رفتار آزمونی و خودارزیابی</h3>
  <div class="grid gap-3 grid-4">
    <div class="field">
      <label><span class="ic">😴</span> خواب شب قبل (ساعت)</label>
      <input class="input" inputmode="numeric" name="sleep_hours" value="<?= e((string)($beh['sleep_hours']??'')) ?>" placeholder="مثلاً ۷">
    </div>
    <div class="field">
      <label><span class="ic">😰</span> استرس (۱ تا ۱۰)</label>
      <input class="input" inputmode="numeric" name="stress_score" value="<?= e((string)($beh['stress_score']??'')) ?>" placeholder="مثلاً ۵">
    </div>
    <div class="field">
      <label><span class="ic">🎯</span> تمرکز (۱ تا ۱۰)</label>
      <input class="input" inputmode="numeric" name="focus_score" value="<?= e((string)($beh['focus_score']??'')) ?>" placeholder="مثلاً ۷">
    </div>
    <div class="field">
      <label><span class="ic">⏳</span> مدیریت زمان</label>
      <select class="select" name="time_management">
        <option></option>
        <?php foreach(['عالی','خوب','متوسط','ضعیف','خیلی بد'] as $x): ?>
          <option <?= ($beh['time_management']??'')===$x?'selected':'' ?>><?= e($x) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="grid gap-3 grid-2">
    <div class="field">
      <label><span class="ic">🔍</span> علت اصلی نتیجه</label>
      <textarea class="input" name="main_cause" rows="3" placeholder="چه چیزی بیشترین تأثیر را در نتیجه‌ی این آزمون داشت؟"><?= e($beh['main_cause']??'') ?></textarea>
    </div>
    <div class="field">
      <label><span class="ic">🔁</span> الگوی غلط‌ها</label>
      <textarea class="input" name="mistake_pattern" rows="3" placeholder="الگوی تکرارشونده در غلط‌هایت چیست؟"><?= e($beh['mistake_pattern']??'') ?></textarea>
    </div>
    <div class="field">
      <label><span class="ic">🌟</span> بهترین کار در آزمون</label>
      <textarea class="input" name="best_action" rows="3" placeholder="کدام تصمیم/استراتژی در آزمون جواب داد؟"><?= e($beh['best_action']??'') ?></textarea>
    </div>
    <div class="field">
      <label><span class="ic">⚠️</span> بدترین اشتباه آزمون</label>
      <textarea class="input" name="worst_action" rows="3" placeholder="کدام تصمیم/اشتباه آزمون را خراب کرد؟"><?= e($beh['worst_action']??'') ?></textarea>
    </div>
    <div class="field">
      <label><span class="ic">🚀</span> استراتژی آزمون بعدی</label>
      <textarea class="input" name="next_strategy" rows="3" placeholder="برای آزمون بعدی چه تغییری می‌دهی؟"><?= e($beh['next_strategy']??'') ?></textarea>
    </div>
    <div class="field">
      <label><span class="ic">📓</span> یادداشت آزاد</label>
      <textarea class="input" name="student_note" rows="3" placeholder="هر نکته‌ای که می‌خواهی ثبت کن"><?= e($r['student_note']??'') ?></textarea>
    </div>
  </div>

  <button class="btn btn-gold btn-lg btn-block" style="font-weight:900;min-height:54px"><?= icon('check',18) ?> ثبت و ساخت تحلیل هوشمند</button>
</form>

<div class="panel mt-4">
  <h3 style="margin-bottom:14px;display:flex;align-items:center;gap:10px;color:var(--gold-light)"><?= icon('list',18) ?> گزارش‌های قبلی</h3>
  <?php if(!$reports): ?>
    <div class="empty-state">هنوز گزارشی ثبت نشده</div>
  <?php else: ?>
    <div class="mock-list">
      <?php foreach($reports as $it): ?>
      <a href="?id=<?= (int)$it['id'] ?>">
        <b><?= e($it['exam_title'] ?: $it['provider']) ?></b>
        <span><?= jalali_date($it['exam_date']) ?> · تراز <?= fa_num($it['total_score']??'-') ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Issues Modal -->
<div class="mock-issue-modal" id="issueModal">
  <div class="mock-issue-dialog panel">
    <div class="issue-modal-head" style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding-bottom:14px;border-bottom:1px solid var(--border-soft);margin-bottom:14px">
      <div style="flex:1;min-width:0">
        <h3 style="display:flex;align-items:center;gap:8px;margin:0"><?= icon('target',18) ?> ثبت علت سوالات غلط/نزده</h3>
        <p>لازم نیست همه سوال‌ها را وارد کنی؛ فقط سوال‌هایی که واقعاً غلط یا نزده بوده‌اند.</p>
      </div>
      <button type="button" class="btn btn-ghost btn-sm" id="closeIssueModal" style="min-width:40px;min-height:40px;width:40px;height:40px;border-radius:50%;padding:0;font-size:1.3rem">×</button>
    </div>

    <div class="issue-help-steps">
      <div><b>۱</b><span>شماره سوال را بنویس</span></div>
      <div><b>۲</b><span>غلط/نزده و علت را انتخاب کن</span></div>
      <div><b>۳</b><span>اگر خواستی توضیح کوتاه اضافه کن</span></div>
    </div>

    <details class="issue-fast-details">
      <summary><?= icon('sparkles',16) ?> ورود سریع چند سوال باهم</summary>
      <div class="issue-fast-entry">
        <div class="field">
          <label>سوال‌های غلط <small class="muted">با کاما/فاصله جدا کن</small></label>
          <textarea class="input" id="wrongBulk" rows="2" placeholder="مثلاً: ۳، ۷، ۱۸، ۲۲"></textarea>
        </div>
        <div class="field">
          <label>سوال‌های نزده <small class="muted">با کاما/فاصله جدا کن</small></label>
          <textarea class="input" id="blankBulk" rows="2" placeholder="مثلاً: ۴۱، ۴۲، ۸۹"></textarea>
        </div>
        <div class="field">
          <label>درس پیش‌فرض</label>
          <select class="select" id="bulkSubject"><option value="">تشخیص/بدون درس</option></select>
        </div>
        <div class="field">
          <label>علت پیش‌فرض</label>
          <select class="select" id="bulkReason">
            <option value="time">کمبود زمان</option>
            <option value="careless">بی‌دقتی</option>
            <option value="concept">ضعف مفهومی</option>
            <option value="doubt">شک بین گزینه‌ها</option>
            <option value="forgot">فراموشی نکته</option>
            <option value="strategy">استراتژی غلط</option>
            <option value="unknown">نامشخص</option>
          </select>
        </div>
        <button type="button" class="btn btn-gold" id="bulkAddIssues">اضافه کن</button>
      </div>
    </details>

    <div class="issue-toolbar">
      <span id="issueCountBadge" class="badge badge-sage">۰ مورد ثبت‌شده</span>
      <button type="button" class="btn btn-ghost btn-sm" id="sortIssuesBtn">مرتب‌سازی</button>
      <button type="button" class="btn btn-ghost btn-sm" id="clearIssuesBtn" style="color:var(--danger)">پاک‌کردن همه</button>
    </div>
    <div id="issueRows" class="issue-rows"></div>
    <div class="issue-actions">
      <button type="button" class="btn btn-ghost btn-sm" id="addIssueRow"><?= icon('plus',14) ?> افزودن ۵ ردیف</button>
      <button type="button" class="btn btn-ghost btn-sm" id="addTenIssueRows">افزودن ۱۰ ردیف</button>
      <button type="button" class="btn btn-gold" id="saveIssuesBtn" style="min-height:48px;font-weight:900"><?= icon('check',16) ?> ثبت و بستن</button>
    </div>
  </div>
</div>

<script>
let rowI=<?= count($rows)+1 ?>;
const initialIssues=<?= json_encode($issues, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '[]' ?>;
const emptyIssue = () => ({question_number:'', subject:'', type:'wrong', reason:'unknown', note:''});
let issues=[...(initialIssues||[])];
let issueDraftRows = issues.length ? issues.map(x=>({...emptyIssue(), ...x})) : Array.from({length:10}, emptyIssue);

function subjectMeta(){return [...document.querySelectorAll('.mock-subject-row')].map(r=>{const ins=[...r.querySelectorAll('input')];return {name:ins[0]?.value||'',from:parseInt(ins[1]?.value||'0'),to:parseInt(ins[2]?.value||'0')}}).filter(x=>x.name)}
function subjectOptions(){return subjectMeta().map(x=>x.name)}
function subjectForQuestion(q){const n=parseInt(q||0);const m=subjectMeta().find(x=>x.from&&x.to&&n>=x.from&&n<=x.to);return m?.name||''}
function parseNums(t){return String(t||'').replace(/[۰-۹]/g,d=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).split(/[^0-9]+/).map(Number).filter(Boolean)}
function refreshBulkSubjects(){const sel=document.getElementById('bulkSubject'); if(!sel)return; const cur=sel.value; sel.innerHTML='<option value="">تشخیص/بدون درس</option>'+subjectOptions().map(s=>`<option ${cur===s?'selected':''}>${s}</option>`).join('')}
function htmlAttr(v){return String(v??'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;')}

function issueRowHtml(x={},i=0){
  const subs=subjectOptions();
  x={...emptyIssue(),...x};
  return `<div class="issue-row" data-i="${i}">
    <button type="button" class="if-delete" data-del-issue aria-label="حذف">×</button>
    <div class="if-field">
      <label>🔢 شماره سوال</label>
      <input class="input" inputmode="numeric" data-issue="question_number" value="${htmlAttr(x.question_number)}" placeholder="مثلاً ۱۲">
    </div>
    <div class="if-field">
      <label>📚 درس</label>
      <select class="select" data-issue="subject"><option value="">انتخاب درس</option>${subs.map(s=>`<option ${x.subject===s?'selected':''}>${s}</option>`).join('')}</select>
    </div>
    <div class="if-field">
      <label>📌 وضعیت</label>
      <select class="select" data-issue="type">
        <option value="wrong" ${x.type==='wrong'?'selected':''}>غلط ✗</option>
        <option value="blank" ${x.type==='blank'?'selected':''}>نزده ○</option>
      </select>
    </div>
    <div class="if-field">
      <label>🔍 علت</label>
      <select class="select" data-issue="reason">
        <option value="unknown" ${x.reason==='unknown'?'selected':''}>انتخاب کن</option>
        <option value="concept" ${x.reason==='concept'?'selected':''}>ضعف مفهومی</option>
        <option value="careless" ${x.reason==='careless'?'selected':''}>بی‌دقتی</option>
        <option value="time" ${x.reason==='time'?'selected':''}>کمبود زمان</option>
        <option value="doubt" ${x.reason==='doubt'?'selected':''}>شک بین گزینه</option>
        <option value="forgot" ${x.reason==='forgot'?'selected':''}>فراموشی نکته</option>
        <option value="strategy" ${x.reason==='strategy'?'selected':''}>استراتژی غلط</option>
      </select>
    </div>
    <div class="if-field wide">
      <label>💬 توضیح کوتاه</label>
      <input class="input" data-issue="note" value="${htmlAttr(x.note)}" placeholder="اختیاری: مثلاً علامت منفی را جا انداختم">
    </div>
  </div>`;
}

function syncDraftFromDom(){
  const rows=[...document.querySelectorAll('.issue-row')];
  if(!rows.length) return;
  issueDraftRows=rows.map(r=>{
    let o={};
    r.querySelectorAll('[data-issue]').forEach(i=>o[i.dataset.issue]=i.value);
    return {...emptyIssue(),...o};
  });
}

function renderIssueRows(){
  refreshBulkSubjects();
  const box=document.getElementById('issueRows');
  if(!box)return;
  box.innerHTML=issueDraftRows.map((x,i)=>issueRowHtml(x,i)).join('');
  updateIssueBadge();
}

function updateIssueBadge(){
  const b=document.getElementById('issueCountBadge');
  if(b) b.textContent = `${issues.length} مورد ثبت‌شده`.replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
}

function collectIssues(){
  syncDraftFromDom();
  issues=issueDraftRows
    .filter(x=>x.question_number||x.subject||x.note)
    .map(x=>({...x, subject:x.subject||subjectForQuestion(x.question_number)}));
  renderIssuePreview();
}

function renderIssuePreview(){
  const p=document.getElementById('issuesPreview');
  const h=document.getElementById('issuesHiddenFields');
  if(!p||!h) return;

  if(!issues.length){
    p.innerHTML='<span class="ip-empty">هنوز علت سوالی ثبت نشده است.</span>';
    h.innerHTML='';
    updateIssueBadge();
    return;
  }

  const reasonMap={
    concept:'ضعف مفهومی',careless:'بی‌دقتی',time:'کمبود زمان',
    doubt:'شک بین گزینه',forgot:'فراموشی',strategy:'استراتژی غلط',unknown:'نامشخص'
  };

  p.innerHTML = issues.slice(0,30).map(x=>
    `<span><span class="ip-q">${x.question_number||'؟'}</span> · <span class="ip-s">${x.subject||'بدون درس'}</span> · <span class="ip-r">${x.type==='blank'?'نزده':'غلط'} · ${reasonMap[x.reason]||x.reason}</span></span>`
  ).join('') + (issues.length>30 ? `<span>+${issues.length-30} مورد دیگر</span>` : '');

  h.innerHTML = issues.map((x,i)=>
    Object.entries(x).map(([k,v])=>
      `<input type="hidden" name="issues[${i}][${k}]" value="${String(v||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;')}">`
    ).join('')
  ).join('');

  updateIssueBadge();
}

renderIssuePreview();

document.getElementById('openIssueModal')?.addEventListener('click',()=>{
  renderIssueRows();
  document.getElementById('issueModal').classList.add('open');
  document.body.style.overflow='hidden';
});
document.getElementById('closeIssueModal')?.addEventListener('click',()=>{
  document.getElementById('issueModal').classList.remove('open');
  document.body.style.overflow='';
  collectIssues();
});
document.getElementById('addIssueRow')?.addEventListener('click',()=>{
  syncDraftFromDom();
  issueDraftRows.push(...Array.from({length:5}, emptyIssue));
  renderIssueRows();
});
document.getElementById('addTenIssueRows')?.addEventListener('click',()=>{
  syncDraftFromDom();
  issueDraftRows.push(...Array.from({length:10}, emptyIssue));
  renderIssueRows();
});
document.getElementById('bulkAddIssues')?.addEventListener('click',()=>{
  collectIssues();
  const subj=document.getElementById('bulkSubject')?.value||'';
  const reason=document.getElementById('bulkReason')?.value||'unknown';
  const add=(nums,type)=>nums.forEach(q=>issues.push({question_number:q,subject:subj||subjectForQuestion(q),type,reason,note:''}));
  add(parseNums(document.getElementById('wrongBulk')?.value),'wrong');
  add(parseNums(document.getElementById('blankBulk')?.value),'blank');
  issues=issues.filter((x,i,a)=>a.findIndex(y=>String(y.question_number)===String(x.question_number)&&y.type===x.type)===i)
    .sort((a,b)=>(parseInt(a.question_number)||0)-(parseInt(b.question_number)||0));
  issueDraftRows = issues.length ? issues.map(x=>({...emptyIssue(),...x})) : Array.from({length:10}, emptyIssue);
  document.getElementById('wrongBulk').value='';
  document.getElementById('blankBulk').value='';
  renderIssueRows();
  renderIssuePreview();
});
document.getElementById('sortIssuesBtn')?.addEventListener('click',()=>{
  collectIssues();
  issues.sort((a,b)=>(parseInt(a.question_number)||0)-(parseInt(b.question_number)||0));
  issueDraftRows = issues.length ? issues.map(x=>({...emptyIssue(),...x})) : Array.from({length:10}, emptyIssue);
  renderIssueRows();
  renderIssuePreview();
});
document.getElementById('clearIssuesBtn')?.addEventListener('click',()=>{
  if(confirm('همه علت‌های ثبت‌شده پاک شود؟')){
    issues=[];
    issueDraftRows=Array.from({length:10}, emptyIssue);
    renderIssueRows();
    renderIssuePreview();
  }
});
document.getElementById('saveIssuesBtn')?.addEventListener('click',()=>{
  collectIssues();
  document.getElementById('issueModal').classList.remove('open');
  document.body.style.overflow='';
  toast('علت‌ها ثبت شد ✅','success');
});

document.addEventListener('click',e=>{
  if(e.target.closest('[data-del-issue]')){
    e.target.closest('.issue-row').remove();
    syncDraftFromDom();
    issueDraftRows = issueDraftRows.length ? issueDraftRows : Array.from({length:10}, emptyIssue);
    collectIssues();
    renderIssueRows();
  }
  if(e.target.closest('[data-del-row]')){
    e.target.closest('.mock-subject-row').remove();
    toast('درس حذف شد','info');
  }
});

// Add new subject row
document.getElementById('addSubjectRow')?.addEventListener('click',()=>{
  const w=document.getElementById('mockSubjectRows');
  w.insertAdjacentHTML('beforeend',`
    <div class="mock-subject-row" data-row-i="${rowI}">
      <button type="button" class="ms-delete" data-del-row aria-label="حذف">×</button>
      <div class="ms-field" style="grid-column:1 / -1">
        <label>📚 نام درس</label>
        <input class="input" name="subjects[${rowI}][name]" placeholder="مثلاً: ریاضی">
      </div>
      <div class="ms-field">
        <label>⏮ از سوال</label>
        <input class="input" inputmode="numeric" name="subjects[${rowI}][q_from]" placeholder="۱">
      </div>
      <div class="ms-field">
        <label>⏭ تا سوال</label>
        <input class="input" inputmode="numeric" name="subjects[${rowI}][q_to]" placeholder="۳۰">
      </div>
      <div class="ms-field">
        <label>✅ درست</label>
        <input class="input" inputmode="numeric" name="subjects[${rowI}][correct]" placeholder="مثلاً ۸">
      </div>
      <div class="ms-field">
        <label>❌ غلط</label>
        <input class="input" inputmode="numeric" name="subjects[${rowI}][wrong]" placeholder="مثلاً ۴">
      </div>
      <div class="ms-field">
        <label>⭕ نزده</label>
        <input class="input" inputmode="numeric" name="subjects[${rowI}][blank]" placeholder="مثلاً ۲">
      </div>
      <div class="ms-field">
        <label>📈 درصد</label>
        <input class="input" inputmode="numeric" name="subjects[${rowI}][percent]" placeholder="۰ تا ۱۰۰">
      </div>
      <div class="ms-field">
        <label>⏱ زمان (دقیقه)</label>
        <input class="input" inputmode="numeric" name="subjects[${rowI}][time_min]" placeholder="مثلاً ۲۵">
      </div>
      <div class="ms-field">
        <label>🏅 رتبه در درس</label>
        <input class="input" inputmode="numeric" name="subjects[${rowI}][rank]" placeholder="اختیاری">
      </div>
      <div class="ms-field wide">
        <label>📝 یادداشت</label>
        <input class="input" name="subjects[${rowI}][note]" placeholder="علت افت یا نکته">
      </div>
    </div>
  `);
  rowI++;
  toast('درس جدید اضافه شد ✅','success');
});

// Fill sample
document.getElementById('fillMockSample')?.addEventListener('click',()=>{
  if(!confirm('فرم با یک نمونه نمایشی پر شود؟')) return;
  const f=document.getElementById('mockForm');
  const set=(n,v)=>{const el=f.querySelector(`[name="${n}"]`); if(el) el.value=v};
  set('exam_title','نمونه آزمون جامع جمع‌بندی');
  set('provider','قلمچی');
  set('total_score','۶۸۵۰');
  set('total_percent','۵۶');
  set('rank_in_exam','۱۲۴۰');
  set('participants','۱۸۵۰۰');
  set('total_questions','۱۲۰');
  set('target_score','۷۲۰۰');
  set('sleep_hours','۶');
  set('stress_score','۷');
  set('focus_score','۶');
  set('time_management','متوسط');
  set('main_cause','در شیمی و فیزیک زمان کم آوردم و چند سوال ساده را با عجله غلط زدم.');
  set('mistake_pattern','بی‌دقتی در محاسبات و شک بین دو گزینه در سوالات مفهومی.');
  issues=[
    {question_number:17,subject:'ریاضی',type:'wrong',reason:'careless',note:'علامت منفی را جا انداختم'},
    {question_number:42,subject:'فیزیک',type:'blank',reason:'time',note:'وقت نکردم برگردم'},
    {question_number:78,subject:'شیمی',type:'wrong',reason:'concept',note:'مفهوم تعادل را کامل بلد نبودم'}
  ];
  issueDraftRows=issues.map(x=>({...emptyIssue(),...x}));
  renderIssuePreview();
  toast('نمونه فرم پر شد','success');
});

document.getElementById('mockForm')?.addEventListener('submit',()=>{
  if(document.getElementById('issueModal').classList.contains('open')){
    collectIssues();
  }
  renderIssuePreview();
});
</script>
<?php panel_end(); ?>
