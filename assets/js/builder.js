/* =================================================================
   Plan Builder — fast, settings-driven planning
   - Defaults come from advisor settings (window.PLANNER_CFG)
   - Smart autofill from last choices (server memory)
   - Right-click context menu on pills and empty cells
   - Copy: single-paste or sticky multi-paste (configurable)
   - Clear day / clear whole-week unit
   ================================================================= */
(() => {
  'use strict';
  const grid = document.getElementById('planGrid');
  if (!grid) return;

  const CFG = Object.assign({
    defaultDuration: 90, defaultTestCount: 40, defaultPriority: 'normal',
    pasteMode: 'single', gridDensity: 'comfortable', smartAutofill: true,
    specialReading: 60, specialExam: 50,
  }, window.PLANNER_CFG || {});

  const planId = grid.dataset.plan;
  const form = document.getElementById('taskForm');
  const status = document.getElementById('saveStatus');
  const copyHint = document.getElementById('copyHint');
  const copyHintText = document.getElementById('copyHintText');
  const titleInput = document.getElementById('f_title');
  const subjectInput = document.getElementById('f_subject');
  const targetInput = document.getElementById('f_target');
  const unitInput = document.getElementById('f_unit');
  const durInput = document.getElementById('f_dur');
  const prioInput = document.getElementById('f_prio');
  const descInput = document.getElementById('f_desc');
  const srcInput = document.getElementById('f_source');
  const subjChips = document.getElementById('subjChips');
  const chapQuick = document.getElementById('chapQuick');

  const CLIP_KEY = 'madar_planner_task_clipboard_v1';
  let copiedTask = null;     // payload currently on the clipboard
  let stickyPaste = false;   // true => keep pasting until stopped
  let draggedId = null;
  let titleTouched = false;

  const closeIco = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>';
  const copyIco = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
  const srcIco = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>';

  const faNumLocal = (n)=>String(n).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
  const esc = (s)=>String(s||'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const dataAttr = (obj)=>esc(JSON.stringify(obj)).replace(/'/g,'&#39;');
  const intOrEmpty = (v)=> (v===null||v===undefined||v==='') ? '' : parseInt(v,10);

  const updateDurationHot = () => durInput?.classList.toggle('duration-hot', ['45','60','90'].includes(String(durInput.value)));
  durInput?.addEventListener('change', updateDurationHot);

  /* --- type defaults; duration & test-count pull from advisor settings --- */
  const DUR = CFG.defaultDuration || 90;
  const TESTS = CFG.defaultTestCount || 40;
  const PRIO = CFG.defaultPriority || 'normal';
  // اولویت پیش‌فرض «بدون اولویت» است؛ مشاور در صورت تمایل خودش تعیین می‌کند.
  const TYPE_DEFAULTS = {
    study:       { target:'',    unit:'درسنامه', dur:DUR, title:'مطالعه',      priority:'', desc:'' },
    test:        { target:TESTS, unit:'تست',     dur:DUR, title:'تست',         priority:'', desc:'' },
    review:      { target:'',    unit:'مبحث',    dur:45,  title:'مرور',        priority:'', desc:'' },
    textbook:    { target:20,    unit:'صفحه',    dur:DUR, title:'کتاب درسی',   priority:'', desc:'خواندن متن کتاب + نکات و شکل‌ها' },
    descriptive: { target:10,    unit:'سوال',    dur:DUR, title:'سوال تشریحی', priority:'', desc:'' },
    reading:     { target:1,     unit:'ساعت',    dur:CFG.specialReading||60, title:'روزخوانی', priority:'', desc:'' },
    exam:        { target:50,    unit:'دقیقه',   dur:CFG.specialExam||50,    title:'آزمونک',  priority:'', desc:'' },
    analysis:    { target:'',    unit:'دقیقه',   dur:60,  title:'تحلیل آزمون', priority:'', desc:'بررسی پاسخ‌نامه، علت غلط‌ها و درصدها' },
    special:     { target:'',    unit:'دقیقه',   dur:CFG.specialReading||60, title:'واحد ویژه', priority:'', desc:'' },
    mock:        { target:'',    unit:'دقیقه',   dur:120, title:'آزمون',       priority:'', desc:'' },
    custom:      { target:'',    unit:'تست',     dur:DUR, title:'تسک دلخواه',  priority:'', desc:'' },
  };
  // پیش‌فرض تعداد تست هر درس (مثل برنامه‌ی واقعی): زیست ۴۰، ریاضی ۳۵، شیمی/فیزیک ۳۰
  const SUBJECTS = window.SUBJECTS || [];
  const subjMap = {}; SUBJECTS.forEach(s=>subjMap[String(s.id)] = s);
  function subjectTestDefault(id){ const s = subjMap[String(id)]; return s ? (s.testDefault||TESTS) : TESTS; }

  const PRESETS = {
    // ترکیبیِ پرکاربرد: درسنامه + تست (مطابق برنامه‌ی واقعی)
    study_test30: { type:'study', target:30, unit:'تست', dur:DUR, title:'درسنامه + تست', combo:true },
    study_test35: { type:'study', target:35, unit:'تست', dur:DUR, title:'درسنامه + تست', combo:true },
    study_test40: { type:'study', target:40, unit:'تست', dur:DUR, title:'درسنامه + تست', combo:true },
    test20:   { type:'test',        target:20,    unit:'تست',   dur:45,  title:'تست' },
    test30:   { type:'test',        target:30,    unit:'تست',   dur:50,  title:'تست' },
    test40:   { type:'test',        target:40,    unit:'تست',   dur:60,  title:'تست' },
    test50:   { type:'test',        target:50,    unit:'تست',   dur:75,  title:'تست' },
    study60:  { type:'study',       target:'',    unit:'درسنامه', dur:60, title:'مطالعه' },
    study90:  { type:'study',       target:'',    unit:'درسنامه', dur:90, title:'مطالعه' },
    textbook: { type:'textbook',    target:20,    unit:'صفحه',  dur:60,  title:'کتاب درسی' },
    errorbook:{ type:'review',      target:'',    unit:'مبحث',  dur:45,  title:'غلط‌نامه' },
    review45: { type:'review',      target:'',    unit:'مبحث',  dur:45,  title:'مرور' },
    review15: { type:'review',      target:'',    unit:'مبحث',  dur:15,  title:'مرور ویژه' },
    desc:     { type:'descriptive', target:10,    unit:'سوال',  dur:60,  title:'سوال تشریحی' },
    reading:  { type:'reading',     target:1,     unit:'ساعت',  dur:CFG.specialReading||60, title:'روزخوانی' },
    exam:     { type:'exam',        target:50,    unit:'دقیقه', dur:CFG.specialExam||50,    title:'آزمونک' },
    class_video: { type:'study',    target:'',    unit:'درسنامه', dur:90, title:'مطابق کلاس/ویدیو', source:'کلاس/ویدیو' },
    test_bank:   { type:'test',     target:30,    unit:'تست',  dur:60,  title:'بانک تست', source:'بانک تست' },
    analysis:    { type:'analysis', target:'',    unit:'دقیقه', dur:60,  title:'تحلیل آزمون' },
    mock:        { type:'mock',     target:'',    unit:'دقیقه', dur:120, title:'آزمون' },
  };

  const setStatus = (state, text) => {
    if (!status) return;
    status.className = 'save-status ' + state;
    status.innerHTML = (state==='saving'?'<span class="spinner" style="width:14px;height:14px"></span>':
      '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>') + ' ' + text;
  };

  function ensureSelectValue(sel, val) {
    if (val === null || val === undefined) val = '';
    val = String(val);
    if (val !== '' && ![...sel.options].some(o => o.value === val)) {
      const opt = document.createElement('option');
      opt.value = val; opt.textContent = faNumLocal(val) + ' دقیقه';
      sel.appendChild(opt);
    }
    sel.value = val;
    if (sel === durInput) updateDurationHot();
  }

  function pillHTML(t) {
    const meta = [];
    if (t.target_count!==null && t.target_count!=='') meta.push(faNumLocal(t.target_count)+' '+(t.target_unit||''));
    if (t.duration_min) meta.push(faNumLocal(t.duration_min)+' دقیقه');
    const typeLabel = (window.TASK_TYPES?.[t.task_type]?.label) || t.type_label || t.task_type;
    const src = t.source || '';
    const data = {
      id:t.id, title:t.title, description:t.description||'', source:src, task_type:t.task_type,
      day_index:t.day_index, unit_index:t.unit_index,
      target_count:t.target_count??'', target_unit:t.target_unit||'تست',
      duration_min:t.duration_min??'', priority:t.priority||'normal', subject_id:t.subject_id??'',
      is_done:t.is_done?1:0
    };
    return `<div class="task-pill type-${esc(t.task_type)} ${t.is_done?'done':''}" draggable="true" data-id="${t.id}" data-json='${dataAttr(data)}'>
      <div class="tp-actions">
        <button class="tp-copy" data-copy title="کپی این تسک">${copyIco}</button>
        <button class="tp-del" data-del title="حذف">${closeIco}</button>
      </div>
      <span class="tp-title">${esc(t.title)}</span>
      ${meta.length?`<span class="tp-meta">${meta.join(' · ')}</span>`:''}
      ${src?`<span class="tp-src">${srcIco} ${esc(src)}</span>`:''}
      <span class="tp-type">${esc(typeLabel)}</span>
    </div>`;
  }

  function cellTasks(cell) { return cell.querySelector('.cell-tasks'); }
  function findCell(day, unit) { return grid.querySelector(`.cell[data-day="${day}"][data-unit="${unit}"]`); }

  /* ---------------- copy / paste ---------------- */
  function clearCopyMode() {
    copiedTask = null; stickyPaste = false;
    try { localStorage.removeItem(CLIP_KEY); } catch(_) {}
    document.body.classList.remove('copy-mode');
    if (copyHint) copyHint.style.display = 'none';
    document.querySelectorAll('.task-pill.copy-source').forEach(p=>p.classList.remove('copy-source'));
  }
  function setCopyMode(pill) {
    copiedTask = JSON.parse(pill.dataset.json);
    try { localStorage.setItem(CLIP_KEY, JSON.stringify(copiedTask)); } catch(_) {}
    stickyPaste = (CFG.pasteMode === 'sticky');
    document.body.classList.add('copy-mode');
    if (copyHint) copyHint.style.display = 'inline-flex';
    if (copyHintText) copyHintText.textContent = stickyPaste
      ? 'حالت چسبان: روی هر خانه بزنید تا پیست شود (Esc = پایان)'
      : 'برای پیست، خانه مقصد را بزنید';
    document.querySelectorAll('.task-pill.copy-source').forEach(p=>p.classList.remove('copy-source'));
    pill.classList.add('copy-source');
    toast(stickyPaste ? 'کپی شد؛ چند خانه پشت‌سرهم پیست کنید' : 'کپی شد؛ خانه مقصد را بزنید', 'info', 2400);
  }

  // نگه‌داری کلیپ‌بورد تسک بین هفته‌ها؛ می‌توان یک تسک را در هفته بعد پیست کرد.
  try {
    const saved = localStorage.getItem(CLIP_KEY);
    if (saved) {
      copiedTask = JSON.parse(saved);
      stickyPaste = (CFG.pasteMode === 'sticky');
      document.body.classList.add('copy-mode');
      if (copyHint) copyHint.style.display = 'inline-flex';
      if (copyHintText) copyHintText.textContent = 'تسک کپی‌شده آماده پیست است؛ خانه مقصد را بزنید (Esc = لغو)';
    }
  } catch(_) {}


  /* ---------------- subject helpers ---------------- */
  function currentSubjectId(){ return subjectInput.value || ''; }
  function currentSubjectName(){
    const s = subjMap[String(subjectInput.value)];
    return s ? s.name : '';
  }
  function setSubject(id){
    subjectInput.value = id || '';
    if (subjChips) subjChips.querySelectorAll('.subj-chip').forEach(c=>c.classList.toggle('active', (c.dataset.subject||'')===String(id||'')));
    // فصل سریع فقط وقتی درس انتخاب شده معنی دارد
    if (chapQuick) chapQuick.hidden = !id;
    // chapter picker button visibility
    const cpWrap = document.getElementById('chapPickerWrap');
    if (cpWrap) cpWrap.hidden = !id;
  }

  /* ---------------- modal helpers ---------------- */
  function setType(type, applyDefaults = true, force = false) {
    document.getElementById('taskType').value = type;
    document.querySelectorAll('.type-opt').forEach(o=>o.classList.toggle('active', o.dataset.type===type));
    if (applyDefaults) applyTypeDefaults(type, force);
    updatePreview();
  }

  // عنوان هوشمند: «نام‌درس فصل» اگر فصلی نوشته نشده، فقط نام درس
  function buildTitle(type, fallback){
    const name = currentSubjectName();
    if (['reading','exam'].includes(type)) return fallback || (TYPE_DEFAULTS[type]?.title || 'تسک');
    if (name) {
      // اگر کاربر فصلی در عنوان داشته، نگه‌دار
      const cur = titleInput.value.trim();
      const m = cur.match(/(ف[\u06F0-\u06F9\d]+|فصل\s*[\u06F0-\u06F9\d]+)/);
      const chap = m ? ' ' + m[0] : '';
      return name + chap;
    }
    return fallback || (TYPE_DEFAULTS[type]?.title || 'تسک');
  }

  function applyTypeDefaults(type, force = false) {
    const d = TYPE_DEFAULTS[type] || TYPE_DEFAULTS.study;
    // برای تست: تعداد را بر اساس درس انتخاب‌شده دقیق کن
    let target = d.target;
    if (type === 'test') target = currentSubjectId() ? subjectTestDefault(currentSubjectId()) : (d.target || TESTS);
    targetInput.value = target ?? '';
    unitInput.value = d.unit || 'تست';
    ensureSelectValue(durInput, d.dur ?? '');
    prioInput.value = d.priority ?? '';   // پیش‌فرض: بدون اولویت
    if (d.desc !== undefined) descInput.value = d.desc || '';
    if (!titleTouched || force) { titleInput.value = buildTitle(type, d.title); titleTouched = false; }
    updatePreview();
  }

  // وقتی درس عوض می‌شود: عنوان و تعداد تست بر اساس درس به‌روز شوند (بدون پاک‌کردن دستکاری کاربر)
  function onSubjectChange(){
    const type = document.getElementById('taskType').value;
    if (!titleTouched) titleInput.value = buildTitle(type);
    if (type === 'test' && currentSubjectId()) {
      // فقط اگر مقدار، همان پیش‌فرض عمومی بوده (یعنی کاربر دستی عوض نکرده)
      targetInput.value = subjectTestDefault(currentSubjectId());
    }
    updatePreview();
  }

  function applyPreset(key) {
    const p = PRESETS[key]; if (!p) return;
    setType(p.type, false);
    // برای ترکیبی، اگر درس انتخاب شده تعداد تستش را دقیق کن
    let target = p.target;
    if (p.combo && currentSubjectId()) target = subjectTestDefault(currentSubjectId());
    targetInput.value = target ?? '';
    unitInput.value = p.unit || 'تست';
    ensureSelectValue(durInput, p.dur ?? '');
    const d = TYPE_DEFAULTS[p.type] || {};
    prioInput.value = '';   // بدون اولویت مگر مشاور خودش بزند
    descInput.value = p.combo ? 'درسنامه + تست' : (d.desc || '');
    if (srcInput && p.source !== undefined) srcInput.value = p.source;
    titleInput.value = buildTitle(p.type, p.title);
    titleTouched = false;
    updatePreview();
  }

  /* ---------------- live preview ---------------- */
  function updatePreview(){
    const pill = document.getElementById('previewPill');
    if (!pill) return;
    const type = document.getElementById('taskType').value;
    pill.className = 'task-pill type-' + type;
    pill.style.cursor = 'default'; pill.style.paddingTop = '9px';
    document.getElementById('pvTitle').textContent = titleInput.value.trim() || buildTitle(type) || 'تسک';
    const meta = [];
    if (targetInput.value !== '') meta.push(faNumLocal(targetInput.value) + ' ' + (unitInput.value || ''));
    if (durInput.value) meta.push(faNumLocal(durInput.value) + ' دقیقه');
    if (srcInput && srcInput.value.trim()) meta.push('منبع: ' + srcInput.value.trim());
    document.getElementById('pvMeta').textContent = meta.join(' · ');
    document.getElementById('pvMeta').style.display = meta.length ? '' : 'none';
    document.getElementById('pvType').textContent = (window.TASK_TYPES?.[type]?.label) || type;
    // header icon/sub
    const sub = document.getElementById('taskModalSub');
    if (sub && !document.getElementById('taskId').value) {
      sub.textContent = currentSubjectName()
        ? ('درس: ' + currentSubjectName() + ' — بقیه موارد خودکار پر شد، در صورت نیاز ویرایش کنید.')
        : 'درس و نوع را انتخاب کنید؛ بقیه‌ی موارد خودکار پر می‌شوند.';
    }
  }

  /* ---------- apply a server "smart suggestion" into the modal ---------- */
  function applySuggestion(s) {
    if (!s) return false;
    if (s.subject_id) setSubject(String(s.subject_id));
    setType(s.task_type || 'study', false);
    targetInput.value = (s.target_count ?? '') === '' ? '' : s.target_count;
    unitInput.value = s.target_unit || (TYPE_DEFAULTS[s.task_type]?.unit) || 'تست';
    ensureSelectValue(durInput, s.duration_min ?? CFG.defaultDuration);
    prioInput.value = (s.priority && s.priority !== 'normal') ? s.priority : '';
    if (srcInput && s.source) srcInput.value = s.source;
    if (!titleTouched) titleInput.value = buildTitle(s.task_type || 'study');
    updatePreview();
    return true;
  }

  async function fetchSuggestion(unit, subjectId) {
    if (!CFG.smartAutofill) return null;
    try {
      const d = await api(window.API_TASKS, { method:'POST', body:{ action:'suggest', unit_index:unit, subject_id:subjectId||'' } });
      return d.suggestion || null;
    } catch(_) { return null; }
  }

  async function openNew(day, unit) {
    form.reset();
    titleTouched = false;
    setSubject('');
    document.getElementById('taskId').value = '';
    document.getElementById('taskDay').value = day;
    document.getElementById('taskUnit').value = unit;
    const defaultType = String(unit) === '8' ? 'reading' : 'test';
    setType(defaultType, true, true);
    document.getElementById('taskModalTitle').textContent = 'افزودن تسک';
    document.getElementById('taskHeadIco') && (document.getElementById('taskHeadIco').innerHTML = document.querySelector('.type-opt.active .icon-tile')?.innerHTML || document.getElementById('taskHeadIco').innerHTML);
    document.getElementById('deleteTaskBtn').style.display = 'none';
    openModal('taskModal');
    setTimeout(()=>{ const c = subjChips?.querySelector('.subj-chip[data-subject]:not([data-subject=""])'); (c||titleInput).focus(); },180);

    // smart autofill: override defaults with last choice for this unit (non-blocking)
    if (CFG.smartAutofill && String(unit) !== '8') {
      const s = await fetchSuggestion(unit, '');
      // only apply if modal is still the "new" one for this cell
      if (s && document.getElementById('taskId').value === '' &&
          String(document.getElementById('taskUnit').value) === String(unit) && !titleTouched) {
        applySuggestion(s);
      }
    }
  }

  function openEdit(pill) {
    const t = JSON.parse(pill.dataset.json);
    form.reset();
    titleTouched = true;
    document.getElementById('taskId').value = t.id;
    document.getElementById('taskDay').value = t.day_index ?? '';
    document.getElementById('taskUnit').value = t.unit_index ?? '';
    titleInput.value = t.title || '';
    descInput.value = t.description || '';
    if (srcInput) srcInput.value = t.source || '';
    targetInput.value = t.target_count===''?'':t.target_count;
    unitInput.value = t.target_unit || 'تست';
    ensureSelectValue(durInput, t.duration_min===''?'':t.duration_min);
    prioInput.value = t.priority || PRIO;
    setSubject(t.subject_id || '');
    setType(t.task_type, false);
    document.getElementById('taskModalTitle').textContent = 'ویرایش تسک';
    document.getElementById('deleteTaskBtn').style.display = '';
    document.getElementById('deleteTaskBtn').dataset.id = t.id;
    updatePreview();
    openModal('taskModal');
  }

  async function createInCell(data, cell, learn=false) {
    const payload = {
      action:'create', plan_id:planId,
      title:data.title || '', description:data.description || '', source:data.source || '', task_type:data.task_type || 'study',
      day_index:cell.dataset.day, unit_index:cell.dataset.unit,
      target_count:data.target_count ?? '', target_unit:data.target_unit || 'تست', duration_min:data.duration_min ?? '',
      subject_id:data.subject_id || '', priority:data.priority || '',
    };
    if (!learn) payload._no_learn = 1;
    setStatus('saving','در حال کپی…');
    const d = await api(window.API_TASKS, { method:'POST', body: payload });
    cellTasks(cell).insertAdjacentHTML('beforeend', pillHTML(d.task));
    setStatus('saved','ذخیره شد');
    recalc();
    return d.task;
  }

  async function pasteInto(cell) {
    if (!copiedTask) return;
    try {
      await createInCell(copiedTask, cell);
      toast('تسک پیست شد','success',1200);
      if (!stickyPaste) clearCopyMode();
    } catch(err){ toast(err.error||'خطا در کپی','error'); }
  }

  async function moveToCell(id, cell) {
    setStatus('saving','در حال جابه‌جایی…');
    const d = await api(window.API_TASKS, { method:'POST', body:{ action:'move', id, day_index:cell.dataset.day, unit_index:cell.dataset.unit } });
    document.querySelector(`.task-pill[data-id="${id}"]`)?.remove();
    cellTasks(cell).insertAdjacentHTML('beforeend', pillHTML(d.task));
    setStatus('saved','جابه‌جا شد');
    recalc();
    return d.task;
  }

  /* ---------------- grid clicks ---------------- */
  grid.addEventListener('click', async (e) => {
    hideCtx();
    const copy = e.target.closest('[data-copy]');
    if (copy) { e.stopPropagation(); setCopyMode(copy.closest('.task-pill')); return; }
    const del = e.target.closest('[data-del]');
    if (del) { e.stopPropagation(); deleteTask(del.closest('.task-pill')); return; }
    const pill = e.target.closest('.task-pill');
    if (pill) { e.stopPropagation(); if (copiedTask) { /* clicking pill while pasting still pastes into its cell */ }
      openEdit(pill); return; }
    const cell = e.target.closest('.cell');
    if (!cell) return;
    if (copiedTask) { await pasteInto(cell); return; }
    openNew(cell.dataset.day, cell.dataset.unit);
  });

  /* ---------------- right-click context menu ---------------- */
  const ctxMenu = document.getElementById('ctxMenu');
  const ctxCell = document.getElementById('ctxCell');
  let ctxPill = null, ctxCellEl = null;

  function showCtx(menu, x, y) {
    hideCtx();
    menu.hidden = false;
    const w = menu.offsetWidth || 200, h = menu.offsetHeight || 200;
    const px = Math.min(x, window.innerWidth - w - 8);
    const py = Math.min(y, window.innerHeight - h - 8);
    menu.style.left = px + 'px';
    menu.style.top = py + 'px';
    menu.classList.add('open');
  }
  function hideCtx() {
    [ctxMenu, ctxCell].forEach(m=>{ if(m){ m.hidden = true; m.classList.remove('open'); } });
  }

  grid.addEventListener('contextmenu', (e) => {
    const pill = e.target.closest('.task-pill');
    const cell = e.target.closest('.cell');
    if (pill) {
      e.preventDefault();
      ctxPill = pill; ctxCellEl = cell;
      const doneBtn = ctxMenu.querySelector('[data-act="done"]');
      const isDone = JSON.parse(pill.dataset.json).is_done;
      if (doneBtn) doneBtn.style.display = '';
      showCtx(ctxMenu, e.clientX, e.clientY);
    } else if (cell) {
      e.preventDefault();
      ctxCellEl = cell; ctxPill = null;
      const pasteBtn = ctxCell.querySelector('[data-act="paste"]');
      if (pasteBtn) pasteBtn.style.display = copiedTask ? '' : 'none';
      showCtx(ctxCell, e.clientX, e.clientY);
    }
  });

  ctxMenu?.addEventListener('click', async (e) => {
    const btn = e.target.closest('.ctx-item'); if (!btn || !ctxPill) return;
    const act = btn.dataset.act; const pill = ctxPill; hideCtx();
    if (act === 'edit') openEdit(pill);
    else if (act === 'copy') setCopyMode(pill);
    else if (act === 'duplicate') {
      const data = JSON.parse(pill.dataset.json);
      try { await createInCell(data, pill.closest('.cell')); toast('تسک تکثیر شد','success',1200); }
      catch(err){ toast(err.error||'خطا','error'); }
    }
    else if (act === 'done') toggleDoneAdvisor(pill);
    else if (act === 'delete') deleteTask(pill);
  });

  ctxCell?.addEventListener('click', async (e) => {
    const btn = e.target.closest('.ctx-item'); if (!btn || !ctxCellEl) return;
    const act = btn.dataset.act; const cell = ctxCellEl; hideCtx();
    if (act === 'add') openNew(cell.dataset.day, cell.dataset.unit);
    else if (act === 'paste') pasteInto(cell);
    else if (act === 'clear_cell') {
      const pills = [...cell.querySelectorAll('.task-pill')];
      if (!pills.length) return;
      if (!confirm('همه‌ی تسک‌های این خانه حذف شود؟')) return;
      for (const p of pills) {
        try { await api(window.API_TASKS,{method:'POST',body:{action:'delete',id:p.dataset.id}}); p.remove(); }
        catch(_) {}
      }
      recalc(); toast('خانه خالی شد','success',1200);
    }
  });

  document.addEventListener('click', (e)=>{ if(!e.target.closest('.ctx-menu')) hideCtx(); });
  document.addEventListener('scroll', hideCtx, true);
  window.addEventListener('resize', hideCtx);

  // advisor-side done toggle (visual + persisted as a simple flag)
  async function toggleDoneAdvisor(pill){
    const data = JSON.parse(pill.dataset.json);
    pill.classList.toggle('done');
    data.is_done = pill.classList.contains('done') ? 1 : 0;
    pill.dataset.json = JSON.stringify(data);
    recalc();
    toast('وضعیت نمایش تغییر کرد (نهایی‌سازی با دانش‌آموز است)','info',1800);
  }

  /* ---------------- drag & drop ---------------- */
  grid.addEventListener('dragstart', (e) => {
    const pill = e.target.closest('.task-pill'); if (!pill) return;
    draggedId = pill.dataset.id;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', draggedId);
    setTimeout(()=>pill.classList.add('dragging'),0);
  });
  grid.addEventListener('dragend', () => {
    draggedId = null;
    document.querySelectorAll('.task-pill.dragging').forEach(p=>p.classList.remove('dragging'));
    document.querySelectorAll('.cell.drop-target').forEach(c=>c.classList.remove('drop-target'));
  });
  grid.addEventListener('dragover', (e) => {
    const cell = e.target.closest('.cell'); if (!cell || !draggedId) return;
    e.preventDefault(); e.dataTransfer.dropEffect = 'move';
    document.querySelectorAll('.cell.drop-target').forEach(c=>{ if(c!==cell)c.classList.remove('drop-target'); });
    cell.classList.add('drop-target');
  });
  grid.addEventListener('dragleave', (e) => {
    const cell = e.target.closest('.cell'); if (cell && !cell.contains(e.relatedTarget)) cell.classList.remove('drop-target');
  });
  grid.addEventListener('drop', async (e) => {
    const cell = e.target.closest('.cell'); if (!cell) return;
    e.preventDefault(); cell.classList.remove('drop-target');
    const id = draggedId || e.dataTransfer.getData('text/plain'); if (!id) return;
    try { await moveToCell(id, cell); }
    catch(err){ toast(err.error||'خطا در جابه‌جایی','error'); setStatus('saved','ذخیره خودکار فعال'); }
  });

  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape'){ clearCopyMode(); hideCtx(); } });
  document.getElementById('typeGrid').addEventListener('click', e=>{
    const o=e.target.closest('.type-opt'); if(!o) return;
    setType(o.dataset.type, true, true);
    const hi = document.getElementById('taskHeadIco');
    if (hi) hi.innerHTML = o.querySelector('.icon-tile')?.innerHTML || hi.innerHTML;
  });
  document.getElementById('quickPresets')?.addEventListener('click', e=>{
    const b=e.target.closest('[data-preset]'); if(b) applyPreset(b.dataset.preset);
  });
  titleInput.addEventListener('input', ()=>{ titleTouched = true; updatePreview(); });
  targetInput.addEventListener('input', updatePreview);
  unitInput.addEventListener('change', updatePreview);
  durInput.addEventListener('change', updatePreview);
  srcInput?.addEventListener('input', updatePreview);

  // subject chips → set hidden select + smart refresh
  subjChips?.addEventListener('click', (e)=>{
    const chip = e.target.closest('.subj-chip'); if(!chip) return;
    setSubject(chip.dataset.subject || '');
    onSubjectChange();
  });
  // quick chapter buttons append/replace «فX» in the title
  chapQuick?.addEventListener('click', (e)=>{
    const b = e.target.closest('[data-chap]'); if(!b) return;
    const name = currentSubjectName();
    titleInput.value = (name ? name : titleInput.value.replace(/\s*(ف[\u06F0-\u06F9\d]+).*$/, '').trim())
                       + ' ' + b.dataset.chap;
    titleTouched = true; updatePreview(); titleInput.focus();
  });

  /* ---------- chapter picker modal ---------- */
  const chapterPickerModal = document.getElementById('chapterPickerModal');
  const chapterPickerBody = document.getElementById('chapterPickerBody');
  const chapterPickerBtn = document.getElementById('chapPickerBtn');

  chapterPickerBtn?.addEventListener('click', async ()=>{
    const sid = currentSubjectId();
    if (!sid) { toast('ابتدا یک درس انتخاب کنید','error'); return; }
    if (!window.API_CHAPTERS) { toast('سیستم فصل‌ها فعال نشده','error'); return; }
    openModal('chapterPickerModal');
    chapterPickerBody.innerHTML = '<div class="empty-state" style="padding:40px"><span class="spinner" style="width:28px;height:28px"></span><span>در حال بارگذاری فصل‌ها…</span></div>';
    try {
      const d = await api(window.API_CHAPTERS, { method:'POST', body: { action:'fetch', subject_id: sid, student_id: window.STUDENT_ID } });
      if (!d.ok || !Object.keys(d.chapters||{}).length) {
        chapterPickerBody.innerHTML = '<div class="empty-state" style="padding:40px"><div class="es-ico">📚</div><p>فصلی برای این درس و رشته/پایه‌ی دانش‌آموز یافت نشد.</p><p class="muted" style="font-size:.84rem">از فصل‌های سریع بالا یا عنوان دستی استفاده کنید.</p></div>';
        return;
      }
      renderChapterPicker(d.chapters, d.subject_name, d.field);
    } catch(err) {
      chapterPickerBody.innerHTML = '<div class="empty-state" style="padding:40px"><div class="es-ico">⚠️</div><p>' + (err.error || 'خطا در بارگذاری فصل‌ها') + '</p></div>';
    }
  });

  function renderChapterPicker(chapters, subjectName, field) {
    const fieldLabels = { tajrobi:'تجربی', riazi:'ریاضی', omumi:'عمومی' };
    let html = '<div class="cp-meta"><span class="badge badge-sage">' + esc(subjectName) + '</span>';
    html += '<span class="badge">' + (fieldLabels[field] || field) + '</span>';
    html += '<span class="badge badge-gold">همه پایه‌ها</span></div>';
    html += '<div class="cp-books">';
    let bookIndex = 0;
    for (const [bookName, items] of Object.entries(chapters)) {
      bookIndex++;
      html += '<div class="cp-book"><div class="cp-book-head"><span class="cp-book-num">' + faNumLocal(bookIndex) + '</span>';
      html += '<span class="cp-book-name">' + esc(bookName) + '</span><span class="badge">' + faNumLocal(items.length) + ' فصل</span></div>';
      html += '<div class="cp-chapters-grid">';
      for (const ch of items) {
        const chTitle = ch.chapter_name || '';
        const fullTitle = subjectName + ' ' + chTitle;
        html += '<button type="button" class="cp-chapter" data-chapter-title="' + esc(chTitle) + '" data-full-title="' + esc(fullTitle) + '">';
        html += '<span class="cp-chap-num">' + faNumLocal(ch.sort_order + 1) + '</span>';
        html += '<span class="cp-chap-name">' + esc(chTitle) + '</span>';
        html += '</button>';
      }
      html += '</div></div>';
    }
    html += '</div>';
    chapterPickerBody.innerHTML = html;

    chapterPickerBody.querySelectorAll('.cp-chapter').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const fullTitle = btn.dataset.fullTitle || (subjectName + ' ' + btn.dataset.chapterTitle);
        titleInput.value = fullTitle;
        titleTouched = true;
        updatePreview();
        closeModal('chapterPickerModal');
        titleInput.focus();
      });
    });
  }
  // Ctrl+Enter = quick save
  form.addEventListener('keydown', (e)=>{ if((e.ctrlKey||e.metaKey) && e.key==='Enter'){ e.preventDefault(); form.requestSubmit(); } });
  document.getElementById('stopPasteBtn')?.addEventListener('click', clearCopyMode);

  /* ---------------- save (create/update) ---------------- */
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('taskId').value;
    const fd = new FormData(form);
    fd.append('action', id ? 'update' : 'create');
    fd.append('plan_id', planId);
    setStatus('saving','در حال ذخیره…');
    try {
      const d = await api(window.API_TASKS, { method:'POST', body: fd });
      const t = d.task;
      if (id) document.querySelector(`.task-pill[data-id="${t.id}"]`)?.remove();
      const cell = findCell(t.day_index, t.unit_index)?.querySelector('.cell-tasks');
      cell?.insertAdjacentHTML('beforeend', pillHTML(t));
      closeModal('taskModal');
      setStatus('saved','ذخیره شد');
      recalc();
      toast(id?'تسک ویرایش شد':'تسک اضافه شد','success',1400);
    } catch(err) {
      setStatus('saved','ذخیره خودکار فعال');
      toast(err.error||'خطا در ذخیره','error');
    }
  });

  document.getElementById('deleteTaskBtn').addEventListener('click', async function(){
    const id = this.dataset.id;
    if (!confirm('این تسک حذف شود؟')) return;
    try {
      await api(window.API_TASKS,{method:'POST',body:{action:'delete',id}});
      document.querySelector(`.task-pill[data-id="${id}"]`)?.remove();
      closeModal('taskModal'); recalc(); toast('تسک حذف شد','success',1400);
    } catch(e){ toast(e.error||'خطا','error'); }
  });
  async function deleteTask(pill){
    if(!pill) return;
    const id = pill.dataset.id;
    if(!confirm('این تسک حذف شود؟')) return;
    try { await api(window.API_TASKS,{method:'POST',body:{action:'delete',id}}); pill.remove(); recalc(); toast('حذف شد','success',1200);}
    catch(e){ toast(e.error||'خطا','error'); }
  }

  /* ---------------- clear day ---------------- */
  document.querySelectorAll('[data-clear-day]').forEach(b=>{
    b.addEventListener('click', async (e)=>{
      e.stopPropagation();
      const day=b.dataset.clearDay;
      if(!confirm('همه تسک‌های این روز پاک شود؟')) return;
      try{ await api(window.API_TASKS,{method:'POST',body:{action:'clear_day',plan_id:planId,day_index:day}});
        grid.querySelectorAll(`.cell[data-day="${day}"] .task-pill`).forEach(p=>p.remove());
        recalc(); toast('روز پاک شد','success',1200);
      }catch(e){ toast(e.error||'خطا','error'); }
    });
  });

  /* ---------------- clear whole-week unit ---------------- */
  document.querySelectorAll('[data-clear-unit]').forEach(b=>{
    b.addEventListener('click', async (e)=>{
      e.stopPropagation();
      const unit=b.dataset.clearUnit;
      if(!confirm('این واحد در همه‌ی روزهای هفته حذف شود؟')) return;
      try{
        const d = await api(window.API_TASKS,{method:'POST',body:{action:'clear_unit',plan_id:planId,unit_index:unit}});
        grid.querySelectorAll(`.cell[data-unit="${unit}"] .task-pill`).forEach(p=>p.remove());
        recalc(); toast(faNumLocal(d.removed||0)+' تسک حذف شد','success',1400);
      }catch(e){ toast(e.error||'خطا','error'); }
    });
  });

  /* ---------------- publish ---------------- */
  const pubBtn = document.getElementById('publishBtn');
  pubBtn?.addEventListener('click', async ()=>{
    const cur = pubBtn.dataset.status;
    const next = cur==='published' ? 'draft' : 'published';
    try {
      const d = await api(window.API_TASKS,{method:'POST',body:{action:'publish',plan_id:planId,status:next}});
      pubBtn.dataset.status = d.status;
      const badge = document.getElementById('statusBadge');
      if(d.status==='published'){
        pubBtn.innerHTML='بازگشت به پیش‌نویس'; badge.className='badge badge-sage'; badge.textContent='منتشر شده';
        toast('برنامه منتشر شد و به دانش‌آموز اطلاع داده شد','success');
      } else {
        pubBtn.innerHTML='انتشار برنامه'; badge.className='badge badge-gold'; badge.textContent='پیش‌نویس';
        toast('برنامه به پیش‌نویس بازگشت','info');
      }
    } catch(e){ toast(e.error||'خطا','error'); }
  });

  document.getElementById('copyWeekBtn')?.addEventListener('click', async function(){
    if(!confirm('تسک‌های این هفته با کپی هفته قبل جایگزین شوند؟')) return;
    try{
      const d = await api(window.API_TASKS,{method:'POST',body:{action:'copy_week',plan_id:planId}});
      toast(faNumLocal(d.copied)+' تسک کپی شد. در حال بارگذاری…','success');
      setTimeout(()=>location.reload(),900);
    }catch(e){ toast(e.error||'خطا','error'); }
  });

  document.getElementById('seedSpecialBtn')?.addEventListener('click', async function(){
    try{
      const d = await api(window.API_TASKS,{method:'POST',body:{action:'seed_special',plan_id:planId}});
      if (d.added > 0) {
        toast(faNumLocal(d.added)+' تسک واحد ویژه اضافه شد','success');
        setTimeout(()=>location.reload(),700);
      } else toast('واحد ویژه از قبل کامل است','info');
    }catch(e){ toast(e.error||'خطا','error'); }
  });

  document.getElementById('copyToStudentBtn')?.addEventListener('click', async function(){
    const sel = document.getElementById('copyTargetStudent');
    const sid = sel?.value || '';
    if (!sid) { toast('دانش‌آموز مقصد را انتخاب کنید','error'); return; }
    const name = sel.options[sel.selectedIndex]?.textContent || 'دانش‌آموز مقصد';
    if (!confirm('کل برنامه این هفته برای «'+name+'» کپی شود؟ برنامه مقصد جایگزین و به پیش‌نویس تبدیل می‌شود.')) return;
    try{
      const d = await api(window.API_TASKS,{method:'POST',body:{action:'copy_to_student',plan_id:planId,student_id:sid}});
      toast(faNumLocal(d.copied)+' تسک برای '+name+' کپی شد','success',2600);
    }catch(e){ toast(e.error||'خطا در کپی برنامه','error'); }
  });

  function recalc(){
    const all = grid.querySelectorAll('.task-pill');
    const done = grid.querySelectorAll('.task-pill.done');
    const total = all.length, dn = done.length;
    const pct = total ? Math.round(dn/total*100) : 0;
    document.getElementById('sumTotal').textContent = faNumLocal(total);
    document.getElementById('sumDone').textContent = faNumLocal(dn);
    document.getElementById('sumPct').textContent = faNumLocal(pct)+'٪';
    document.getElementById('sumBar').style.width = pct+'%';
  }
})();
