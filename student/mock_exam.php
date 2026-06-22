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
  <div><span class="badge badge-gold"><?= icon('target',15) ?> تحلیل آزمون بیرونی</span><h2>تحلیل آزمون آزمایشی/کنکور</h2><p>نتیجه آزمون‌های قلمچی، سنجش، گزینه‌دو، ماز یا کنکور را وارد کن تا مَدار تحلیل هوشمند و برنامه اقدام بدهد.</p></div>
  <div class="flex gap-2 wrap"><?php if($r): ?><a target="_blank" class="btn btn-gold" href="<?= url('student/mock_exam_pdf.php?id='.(int)$r['id']) ?>"><?= icon('clipboard',16) ?> خروجی PDF</a><?php endif; ?><button type="button" class="btn btn-ghost" id="fillMockSample">پر کردن نمونه</button></div>
</div>

<?php if($an): ?>
<div class="mock-analysis panel">
  <div class="ma-head"><div class="ma-score"><?= fa_num($an['overall']) ?>٪</div><div><b>تحلیل هوشمند مَدار <span class="badge badge-gold">بتا</span></b><span><?= e($an['overall_label']) ?></span></div></div>
  <p><?= e($an['summary'] ?? '') ?></p>
  <?php if(!empty($an['alerts'])): ?><div class="ma-alerts"><?php foreach($an['alerts'] as $al): ?><div class="ia <?= e($al['level']) ?>"><b><?= e($al['title']) ?></b><span><?= e($al['text']) ?></span></div><?php endforeach; ?></div><?php endif; ?>
  <div class="ma-grid"><?php foreach(['result'=>'نتیجه','accuracy'=>'دقت','target'=>'هدف','risk'=>'ریسک'] as $k=>$lbl): ?><div><span><?= e($lbl) ?></span><b><?= fa_num($an['scores'][$k]??0) ?>٪</b></div><?php endforeach; ?></div>
  <div class="ma-recs"><b>پیشنهادهای عملی</b><ul><?php foreach(($an['recommendations']??[]) as $rec): ?><li><?= e($rec) ?></li><?php endforeach; ?></ul></div>
  <div class="ma-recs action"><b>نقشه اقدام</b><ul><?php foreach(($an['action_plan']??[]) as $rec): ?><li><?= e($rec) ?></li><?php endforeach; ?></ul></div>
</div>
<?php endif; ?>

<form method="post" class="panel mock-form" id="mockForm">
  <?= csrf_field() ?><?php if($r): ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><?php endif; ?>
  <h3><?= icon('edit',18) ?> اطلاعات کلی آزمون</h3>
  <div class="grid gap-3" style="grid-template-columns:repeat(4,1fr)">
    <div class="field"><label>کجا آزمون دادی؟</label><select class="select" name="provider"><?php foreach(MOCK_PROVIDERS as $p): ?><option <?= ($r['provider']??'')===$p?'selected':'' ?>><?= e($p) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>نام آزمون</label><input class="input" name="exam_title" value="<?= e($r['exam_title']??'') ?>" placeholder="مثلاً جامع ۲۷ تیر"></div>
    <div class="field"><label>تاریخ آزمون</label><input class="input" type="date" name="exam_date" value="<?= e($r['exam_date']??date('Y-m-d')) ?>"></div>
    <div class="field"><label>اگر سایر، نام محل</label><input class="input" name="provider_other" value="<?= e($r['provider_other']??'') ?>" placeholder="نام موسسه/مدرسه"></div>
  </div>
  <div class="grid gap-3" style="grid-template-columns:repeat(6,1fr)">
    <div class="field"><label>تراز / نمره کل</label><input class="input" name="total_score" value="<?= e((string)($r['total_score']??'')) ?>"></div>
    <div class="field"><label>درصد کل</label><input class="input" name="total_percent" value="<?= e((string)($r['total_percent']??'')) ?>"></div>
    <div class="field"><label>رتبه</label><input class="input" name="rank_in_exam" value="<?= e((string)($r['rank_in_exam']??'')) ?>"></div>
    <div class="field"><label>تعداد شرکت‌کننده</label><input class="input" name="participants" value="<?= e((string)($r['participants']??'')) ?>"></div>
    <div class="field"><label>تعداد کل سوالات</label><input class="input" name="total_questions" value="<?= e((string)($r['total_questions']??'')) ?>" placeholder="مثلاً ۱۲۰"></div>
    <div class="field"><label>هدف/تراز مورد انتظار</label><input class="input" name="target_score" value="<?= e((string)($r['target_score']??'')) ?>"></div>
  </div>

  <h3><?= icon('book',18) ?> ریزنتیجه درس‌ها <span class="muted">(همه اختیاری)</span></h3>
  <div class="mock-subject-table" id="mockSubjectRows">
    <div class="ms-head"><span>درس</span><span>از سوال</span><span>تا سوال</span><span>درست</span><span>غلط</span><span>نزده</span><span>درصد</span><span>زمان</span><span>رتبه</span><span>یادداشت</span><span></span></div>
    <?php $rows=$subj ?: array_map(fn($n)=>['name'=>$n], array_slice(MOCK_SUBJECTS,0,6)); foreach($rows as $i=>$s): ?>
      <div class="ms-row">
        <input class="input" name="subjects[<?= $i ?>][name]" value="<?= e($s['name']??'') ?>" placeholder="درس">
        <input class="input" name="subjects[<?= $i ?>][q_from]" value="<?= e((string)($s['q_from']??'')) ?>" placeholder="۱">
        <input class="input" name="subjects[<?= $i ?>][q_to]" value="<?= e((string)($s['q_to']??'')) ?>" placeholder="۳۰">
        <input class="input" name="subjects[<?= $i ?>][correct]" value="<?= e((string)($s['correct']??'')) ?>">
        <input class="input" name="subjects[<?= $i ?>][wrong]" value="<?= e((string)($s['wrong']??'')) ?>">
        <input class="input" name="subjects[<?= $i ?>][blank]" value="<?= e((string)($s['blank']??'')) ?>">
        <input class="input" name="subjects[<?= $i ?>][percent]" value="<?= e((string)($s['percent']??'')) ?>">
        <input class="input" name="subjects[<?= $i ?>][time_min]" value="<?= e((string)($s['time_min']??'')) ?>">
        <input class="input" name="subjects[<?= $i ?>][rank]" value="<?= e((string)($s['rank']??'')) ?>">
        <input class="input" name="subjects[<?= $i ?>][note]" value="<?= e($s['note']??'') ?>" placeholder="علت افت/نکته">
        <button type="button" class="btn btn-ghost btn-sm" data-del-row>×</button>
      </div>
    <?php endforeach; ?>
  </div>
  <button type="button" class="btn btn-ghost btn-sm" id="addSubjectRow"><?= icon('plus',14) ?> افزودن درس</button>

  <h3><?= icon('target',18) ?> تحلیل سوالات غلط و نزده</h3>
  <div class="mock-issues-box">
    <div><b>برای تحلیل دقیق، سوال‌هایی که غلط زدی یا نزدی را ثبت کن.</b><span class="muted">علت هر سوال باعث می‌شود تحلیل هوشمند مَدار بفهمد مشکل اصلی تو مفهوم، زمان، بی‌دقتی یا استراتژی بوده.</span></div>
    <button type="button" class="btn btn-gold" id="openIssueModal"><?= icon('plus',16) ?> ثبت علت سوال‌ها</button>
  </div>
  <div id="issuesPreview" class="issues-preview"></div>
  <div id="issuesHiddenFields"></div>

  <h3><?= icon('sparkles',18) ?> رفتار آزمونی و خودارزیابی</h3>
  <div class="grid gap-3" style="grid-template-columns:repeat(4,1fr)">
    <div class="field"><label>خواب شب قبل</label><input class="input" name="sleep_hours" value="<?= e((string)($beh['sleep_hours']??'')) ?>"></div>
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
    <div class="field"><label>یادداشت آزاد</label><textarea class="input" name="student_note" rows="3"><?= e($r['student_note']??'') ?></textarea></div>
  </div>
  <button class="btn btn-gold btn-lg" style="font-weight:900"><?= icon('check',18) ?> ثبت و ساخت تحلیل هوشمند</button>
</form>

<div class="panel mt-4"><h3><?= icon('list',18) ?> گزارش‌های قبلی</h3><?php if(!$reports): ?><div class="empty-state">هنوز گزارشی ثبت نشده</div><?php else: ?><div class="mock-list"><?php foreach($reports as $it): ?><a href="?id=<?= (int)$it['id'] ?>"><b><?= e($it['exam_title'] ?: $it['provider']) ?></b><span><?= jalali_date($it['exam_date']) ?> · تراز <?= fa_num($it['total_score']??'-') ?></span></a><?php endforeach; ?></div><?php endif; ?></div>
<div class="mock-issue-modal" id="issueModal">
  <div class="mock-issue-dialog panel">
    <div class="between mb-3 issue-modal-head" style="align-items:center"><div><h3><?= icon('target',18) ?> ثبت علت سوالات غلط/نزده</h3><p class="muted">لازم نیست همه سوال‌ها را وارد کنی؛ فقط سوال‌هایی که واقعاً غلط یا نزده بوده‌اند.</p></div><button type="button" class="btn btn-ghost btn-sm" id="closeIssueModal">×</button></div>
    <div class="issue-help-steps">
      <div><b>۱</b><span>شماره سوال را بنویس</span></div>
      <div><b>۲</b><span>غلط/نزده و علت را انتخاب کن</span></div>
      <div><b>۳</b><span>اگر خواستی توضیح کوتاه اضافه کن</span></div>
    </div>
    <details class="issue-fast-details">
      <summary><?= icon('sparkles',16) ?> ورود سریع چند سوال باهم</summary>
      <div class="issue-fast-entry">
        <div class="field"><label>سوال‌های غلط <small class="muted">با کاما/فاصله جدا کن</small></label><textarea class="input" id="wrongBulk" rows="2" placeholder="مثلاً: ۳، ۷، ۱۸، ۲۲"></textarea></div>
        <div class="field"><label>سوال‌های نزده <small class="muted">با کاما/فاصله جدا کن</small></label><textarea class="input" id="blankBulk" rows="2" placeholder="مثلاً: ۴۱، ۴۲، ۸۹"></textarea></div>
        <div class="field"><label>درس پیش‌فرض</label><select class="select" id="bulkSubject"><option value="">تشخیص/بدون درس</option></select></div>
        <div class="field"><label>علت پیش‌فرض</label><select class="select" id="bulkReason"><option value="time">کمبود زمان</option><option value="careless">بی‌دقتی</option><option value="concept">ضعف مفهومی</option><option value="doubt">شک بین گزینه‌ها</option><option value="forgot">فراموشی نکته</option><option value="strategy">استراتژی غلط</option><option value="unknown">نامشخص</option></select></div>
        <button type="button" class="btn btn-gold" id="bulkAddIssues">اضافه کن</button>
      </div>
    </details>
    <div class="issue-toolbar"><span id="issueCountBadge" class="badge badge-sage">۰ مورد ثبت‌شده</span><span class="muted issue-draft-note">برای شروع فقط ۱۰ ردیف نشان داده می‌شود؛ هر وقت لازم داشتی ردیف اضافه کن.</span><button type="button" class="btn btn-ghost btn-sm" id="sortIssuesBtn">مرتب‌سازی</button><button type="button" class="btn btn-ghost btn-sm" id="clearIssuesBtn" style="color:var(--danger)">پاک‌کردن همه</button></div>
    <div class="issue-row-head"><span>شماره</span><span>درس</span><span>وضعیت</span><span>علت</span><span>توضیح</span><span></span></div>
    <div id="issueRows" class="issue-rows compact"></div>
    <div class="issue-actions"><button type="button" class="btn btn-ghost btn-sm" id="addIssueRow"><?= icon('plus',14) ?> افزودن ۵ ردیف</button><button type="button" class="btn btn-ghost btn-sm" id="addTenIssueRows">افزودن ۱۰ ردیف</button><button type="button" class="btn btn-gold" id="saveIssuesBtn">ثبت علت‌ها</button></div>
  </div>
</div>
<script>
let rowI=<?= count($rows)+1 ?>;
const initialIssues=<?= json_encode($issues, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '[]' ?>;
const emptyIssue = () => ({question_number:'', subject:'', type:'wrong', reason:'unknown', note:''});
let issues=[...initialIssues];
let issueDraftRows = issues.length ? issues.map(x=>({...emptyIssue(), ...x})) : Array.from({length:10}, emptyIssue);
function subjectMeta(){return [...document.querySelectorAll('.ms-row')].map(r=>{const ins=[...r.querySelectorAll('input')];return {name:ins[0]?.value||'',from:parseInt(ins[1]?.value||'0'),to:parseInt(ins[2]?.value||'0')}}).filter(x=>x.name)}
function subjectOptions(){return subjectMeta().map(x=>x.name)}
function subjectForQuestion(q){const n=parseInt(q||0);const m=subjectMeta().find(x=>x.from&&x.to&&n>=x.from&&n<=x.to);return m?.name||''}
function parseNums(t){return String(t||'').replace(/[۰-۹]/g,d=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).split(/[^0-9]+/).map(Number).filter(Boolean)}
function refreshBulkSubjects(){const sel=document.getElementById('bulkSubject'); if(!sel)return; const cur=sel.value; sel.innerHTML='<option value="">تشخیص/بدون درس</option>'+subjectOptions().map(s=>`<option ${cur===s?'selected':''}>${s}</option>`).join('')}
function htmlAttr(v){return String(v??'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;')}
function issueRowHtml(x={},i=0){const subs=subjectOptions();x={...emptyIssue(),...x};return `<div class="issue-row"><input class="input" inputmode="numeric" data-issue="question_number" value="${htmlAttr(x.question_number)}" placeholder="مثلاً ۱۲"><select class="select" data-issue="subject"><option value="">درس</option>${subs.map(s=>`<option ${x.subject===s?'selected':''}>${s}</option>`).join('')}</select><select class="select" data-issue="type"><option value="wrong" ${x.type==='wrong'?'selected':''}>غلط</option><option value="blank" ${x.type==='blank'?'selected':''}>نزده</option></select><select class="select" data-issue="reason"><option value="unknown" ${x.reason==='unknown'?'selected':''}>انتخاب علت</option><option value="concept" ${x.reason==='concept'?'selected':''}>ضعف مفهومی</option><option value="careless" ${x.reason==='careless'?'selected':''}>بی‌دقتی</option><option value="time" ${x.reason==='time'?'selected':''}>کمبود زمان</option><option value="doubt" ${x.reason==='doubt'?'selected':''}>شک بین گزینه‌ها</option><option value="forgot" ${x.reason==='forgot'?'selected':''}>فراموشی نکته</option><option value="strategy" ${x.reason==='strategy'?'selected':''}>استراتژی غلط</option></select><input class="input" data-issue="note" value="${htmlAttr(x.note)}" placeholder="اختیاری"><button type="button" class="btn btn-ghost btn-sm" data-del-issue title="حذف ردیف">×</button></div>`}
function syncDraftFromDom(){const rows=[...document.querySelectorAll('.issue-row')]; if(!rows.length) return; issueDraftRows=rows.map(r=>{let o={};r.querySelectorAll('[data-issue]').forEach(i=>o[i.dataset.issue]=i.value);return {...emptyIssue(),...o}})}
function renderIssueRows(){refreshBulkSubjects();const box=document.getElementById('issueRows'); if(!box)return; box.innerHTML=issueDraftRows.map(issueRowHtml).join('');const b=document.getElementById('issueCountBadge');if(b)b.textContent=`${issues.length} مورد ثبت‌شده`.replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d])}
function collectIssues(){syncDraftFromDom();issues=issueDraftRows.filter(x=>x.question_number||x.subject||x.note).map(x=>({...x,subject:x.subject||subjectForQuestion(x.question_number)}));renderIssuePreview()}
function renderIssuePreview(){const p=document.getElementById('issuesPreview'), h=document.getElementById('issuesHiddenFields');p.innerHTML=issues.length?issues.slice(0,30).map(x=>`<span>${x.question_number||'؟'} · ${x.subject||'درس؟'} · ${x.type==='blank'?'نزده':'غلط'} · ${x.reason||''}</span>`).join('')+(issues.length>30?`<span>+${issues.length-30} مورد دیگر</span>`:''):'<span class="muted">هنوز علت سوالی ثبت نشده است.</span>';h.innerHTML=issues.map((x,i)=>Object.entries(x).map(([k,v])=>`<input type="hidden" name="issues[${i}][${k}]" value="${String(v||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;')}">`).join('')).join('');const b=document.getElementById('issueCountBadge');if(b)b.textContent=`${issues.length} مورد`.replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d])}
renderIssuePreview();
document.getElementById('openIssueModal')?.addEventListener('click',()=>{renderIssueRows();document.getElementById('issueModal').classList.add('open')});
document.getElementById('closeIssueModal')?.addEventListener('click',()=>document.getElementById('issueModal').classList.remove('open'));
document.getElementById('addIssueRow')?.addEventListener('click',()=>{syncDraftFromDom();issueDraftRows.push(...Array.from({length:5}, emptyIssue));renderIssueRows()});
document.getElementById('addTenIssueRows')?.addEventListener('click',()=>{syncDraftFromDom();issueDraftRows.push(...Array.from({length:10}, emptyIssue));renderIssueRows()});
document.getElementById('bulkAddIssues')?.addEventListener('click',()=>{collectIssues();const subj=document.getElementById('bulkSubject')?.value||'';const reason=document.getElementById('bulkReason')?.value||'unknown';const add=(nums,type)=>nums.forEach(q=>issues.push({question_number:q,subject:subj||subjectForQuestion(q),type,reason,note:''}));add(parseNums(document.getElementById('wrongBulk')?.value),'wrong');add(parseNums(document.getElementById('blankBulk')?.value),'blank');issues=issues.filter((x,i,a)=>a.findIndex(y=>String(y.question_number)===String(x.question_number)&&y.type===x.type)===i).sort((a,b)=>(parseInt(a.question_number)||0)-(parseInt(b.question_number)||0));issueDraftRows=issues.length?issues.map(x=>({...emptyIssue(),...x})):Array.from({length:10}, emptyIssue);document.getElementById('wrongBulk').value='';document.getElementById('blankBulk').value='';renderIssueRows();renderIssuePreview();});
document.getElementById('sortIssuesBtn')?.addEventListener('click',()=>{collectIssues();issues.sort((a,b)=>(parseInt(a.question_number)||0)-(parseInt(b.question_number)||0));issueDraftRows=issues.length?issues.map(x=>({...emptyIssue(),...x})):Array.from({length:10}, emptyIssue);renderIssueRows();renderIssuePreview()});
document.getElementById('clearIssuesBtn')?.addEventListener('click',()=>{if(confirm('همه علت‌های ثبت‌شده پاک شود؟')){issues=[];issueDraftRows=Array.from({length:10}, emptyIssue);renderIssueRows();renderIssuePreview()}});
document.getElementById('saveIssuesBtn')?.addEventListener('click',()=>{collectIssues();document.getElementById('issueModal').classList.remove('open')});
document.addEventListener('click',e=>{if(e.target.closest('[data-del-issue]')){e.target.closest('.issue-row').remove();collectIssues();issueDraftRows = issueDraftRows.length ? issueDraftRows : Array.from({length:10}, emptyIssue);renderIssueRows()} if(e.target.closest('[data-del-row]'))e.target.closest('.ms-row').remove();});
document.getElementById('addSubjectRow')?.addEventListener('click',()=>{const w=document.getElementById('mockSubjectRows');w.insertAdjacentHTML('beforeend',`<div class="ms-row"><input class="input" name="subjects[${rowI}][name]" placeholder="درس"><input class="input" name="subjects[${rowI}][q_from]" placeholder="از"><input class="input" name="subjects[${rowI}][q_to]" placeholder="تا"><input class="input" name="subjects[${rowI}][correct]"><input class="input" name="subjects[${rowI}][wrong]"><input class="input" name="subjects[${rowI}][blank]"><input class="input" name="subjects[${rowI}][percent]"><input class="input" name="subjects[${rowI}][time_min]"><input class="input" name="subjects[${rowI}][rank]"><input class="input" name="subjects[${rowI}][note]" placeholder="علت افت/نکته"><button type="button" class="btn btn-ghost btn-sm" data-del-row>×</button></div>`);rowI++;});
document.getElementById('fillMockSample')?.addEventListener('click',()=>{const f=document.getElementById('mockForm'); if(!confirm('فرم با یک نمونه نمایشی پر شود؟')) return; const set=(n,v)=>{const el=f.querySelector(`[name="${n}"]`); if(el) el.value=v}; set('exam_title','نمونه آزمون جامع جمع‌بندی'); set('provider','قلمچی'); set('total_score','۶۸۵۰'); set('total_percent','۵۶'); set('rank_in_exam','۱۲۴۰'); set('participants','۱۸۵۰۰'); set('total_questions','۱۲۰'); set('target_score','۷۲۰۰'); set('sleep_hours','۶'); set('stress_score','۷'); set('focus_score','۶'); set('time_management','متوسط'); set('main_cause','در شیمی و فیزیک زمان کم آوردم و چند سوال ساده را با عجله غلط زدم.'); set('mistake_pattern','بی‌دقتی در محاسبات و شک بین دو گزینه در سوالات مفهومی.'); issues=[{question_number:17,subject:'ریاضی',type:'wrong',reason:'careless',note:'علامت منفی را جا انداختم'},{question_number:42,subject:'فیزیک',type:'blank',reason:'time',note:'وقت نکردم برگردم'},{question_number:78,subject:'شیمی',type:'wrong',reason:'concept',note:'مفهوم تعادل را کامل بلد نبودم'}]; issueDraftRows=issues.map(x=>({...emptyIssue(),...x})); renderIssuePreview(); toast('نمونه فرم پر شد','success')});
document.getElementById('mockForm')?.addEventListener('submit',()=>{if(document.getElementById('issueModal').classList.contains('open')) collectIssues(); renderIssuePreview();});
</script>
<?php panel_end(); ?>
