/* =============== Student interactions =============== */
(() => {
  'use strict';
  const faNum = (n)=>String(n).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
  const feelingTypes = ['study','review','textbook','reading','analysis','custom'];
  let pendingRow = null;
  let selectedFeeling = '';

  function statusLabel(s){ return ({full:'اجرای کامل',partial:'اجرای ناقص',missed:'عدم اجرا',pending:'در انتظار'})[s]||s; }

  function applyResult(row, d) {
    const status = d.status || (d.is_done==1 ? 'full' : 'pending');
    row.dataset.status = status;
    row.dataset.done = d.done_count || 0;
    row.dataset.course = d.course_percent ?? '';
    row.dataset.feeling = d.student_feeling || '';
    row.classList.remove('done','partial','missed','pending');
    row.classList.add(status === 'full' ? 'done' : status);
    row.querySelectorAll('.task-action').forEach(b=>b.classList.toggle('active', b.dataset.statusAction===status));
    const prog = row.querySelector('.st-prog-count');
    if (prog && d.target!=null) prog.textContent = faNum(d.done_count||0)+'/'+faNum(d.target)+' '+(prog.dataset.unit||'');
    const course = row.querySelector('.st-course');
    if (course) course.textContent = d.course_percent!==null && d.course_percent!==undefined ? faNum(d.course_percent)+'٪ کورس' : '';
    const badge = row.querySelector('.st-status-text');
    if (badge) badge.textContent = statusLabel(status);
    updateProgress();
  }

  async function sendStatus(row, payload) {
    row.style.opacity = '.55';
    try {
      const d = await api(window.API_TASKS, { method:'POST', body: { action:'set_status', id: row.dataset.id, ...payload } });
      row.style.opacity = '';
      applyResult(row, d);
      if (d.status==='full') { toast('عالی! کامل ثبت شد ✅','success',1800); confetti(); }
      else if (d.status==='partial') toast('ثبت شد؛ ناقص هم نصف امتیاز دارد ●','info',1900);
      else if (d.status==='missed') toast('عدم اجرا ثبت شد ✕','error',1700);
      if (d.needs_report && d.report_url) {
        // فقط وقتی همه تسک‌های امروز تعیین‌وضعیت شدن
        const allTasks = document.querySelectorAll('.s-task');
        const pendingLeft = document.querySelectorAll('.s-task[data-status="pending"]');
        if (pendingLeft.length === 0 && allTasks.length > 0) {
          setTimeout(()=>{
            showReportPrompt(d.report_url);
          }, 900);
        }
      }
      return d;
    } catch(err) {
      row.style.opacity='';
      toast(err.error||'خطا در ثبت','error');
    }
  }

  function openStatusModal(row, status){
    pendingRow = row; selectedFeeling = row.dataset.feeling || '';
    const target = parseInt(row.dataset.target) || 0;
    const title = row.querySelector('.st-title-main')?.textContent.trim() || row.querySelector('.st-title')?.textContent.trim() || 'تسک';
    document.getElementById('smTaskId').value = row.dataset.id;
    document.getElementById('smStatus').value = status;
    document.getElementById('smTitle').textContent = title;
    document.getElementById('smIcon').textContent = status==='full' ? '✓' : '●';

    const amountWrap = document.getElementById('smAmountWrap');
    const count = document.getElementById('smCount');
    const range = document.getElementById('smRange');
    if (target > 0) {
      amountWrap.style.display = '';
      document.getElementById('smTargetText').textContent = 'از '+faNum(target)+' '+(row.dataset.targetUnit||'');
      const currentDone = parseInt(row.dataset.done)||0;
      const val = status==='full' ? Math.max(target, currentDone || target) : Math.max(1, currentDone || Math.ceil(target/2));
      const softMax = Math.max(target * 3, val, target + 50);
      count.removeAttribute('max'); range.max = softMax; count.value = val; range.value = Math.min(val, softMax);
    } else {
      amountWrap.style.display = 'none'; count.value = status==='full' ? 1 : 0; range.value = count.value;
    }

    const course = document.getElementById('smCourse');
    const courseRange = document.getElementById('smCourseRange');
    const cp = row.dataset.course ? parseInt(row.dataset.course) : (status==='full' ? 100 : 50);
    course.value = cp; courseRange.value = cp;

    const needsFeeling = feelingTypes.includes(row.dataset.type || '');
    document.getElementById('smFeelingWrap').style.display = needsFeeling ? '' : 'none';
    document.querySelectorAll('[data-feeling]').forEach(b=>b.classList.toggle('active', b.dataset.feeling===selectedFeeling));

    openModal('taskStatusModal');
    setTimeout(()=>course.focus(), 180);
  }

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-status-action]');
    if (!btn) return;
    const row = btn.closest('.s-task');
    const status = btn.dataset.statusAction;
    if (status === 'missed') {
      sendStatus(row, {status:'missed', done_count:0, course_percent:0});
      return;
    }
    openStatusModal(row, status);
  });

  document.getElementById('smRange')?.addEventListener('input', e=>{ document.getElementById('smCount').value=e.target.value; });
  document.getElementById('smCount')?.addEventListener('input', e=>{ const r=document.getElementById('smRange'); if(parseInt(e.target.value||0)>parseInt(r.max||0)) r.max=e.target.value; r.value=e.target.value; });
  document.getElementById('smCourseRange')?.addEventListener('input', e=>{ document.getElementById('smCourse').value=e.target.value; });
  document.getElementById('smCourse')?.addEventListener('input', e=>{ document.getElementById('smCourseRange').value=e.target.value; });
  document.addEventListener('click', e=>{
    const b=e.target.closest('[data-feeling]'); if(!b) return;
    selectedFeeling=b.dataset.feeling;
    document.querySelectorAll('[data-feeling]').forEach(x=>x.classList.toggle('active', x===b));
  });
  document.getElementById('smConfirm')?.addEventListener('click', async ()=>{
    if (!pendingRow) return;
    const status = document.getElementById('smStatus').value;
    const target = parseInt(pendingRow.dataset.target) || 0;
    const count = target > 0 ? document.getElementById('smCount').value : (status==='full'?1:0);
    const course = document.getElementById('smCourse').value;
    if (course === '' || parseInt(course)<0 || parseInt(course)>100) return toast('درصد کورس را بین ۰ تا ۱۰۰ وارد کن','error');
    if (target > 0 && (count === '' || parseInt(count)<0)) return toast('تعداد انجام‌شده معتبر نیست','error');
    if (feelingTypes.includes(pendingRow.dataset.type||'') && !selectedFeeling) return toast('حست را برای این تسک انتخاب کن','error');
    closeModal('taskStatusModal');
    await sendStatus(pendingRow, {status, done_count:count, course_percent:course, student_feeling:selectedFeeling});
    pendingRow = null;
  });

  function updateProgress() {
    const tasks = document.querySelectorAll('.s-task');
    if (!tasks.length) return;
    let score = 0;
    tasks.forEach(t=>{
      if(t.dataset.status==='partial') score += .5;
      else if(t.dataset.status==='full') {
        const target=parseInt(t.dataset.target)||0, done=parseInt(t.dataset.done)||0;
        score += (target>0 && done>target) ? 1 + Math.min(.25, ((done-target)/target)*.25) : 1;
      }
    });
    const pct = Math.round(score/tasks.length*100);
    const full = document.querySelectorAll('.s-task[data-status="full"]').length;
    const partial = document.querySelectorAll('.s-task[data-status="partial"]').length;
    const bar = document.querySelector('.greet-progress .progress > span');
    if (bar) { bar.style.width = pct+'%';
      const lbl = bar.closest('.greet-progress')?.querySelector('.between span:last-child');
      if (lbl) lbl.textContent = faNum(pct)+'٪ · کامل '+faNum(full)+' / ناقص '+faNum(partial);
    }
  }

  document.addEventListener('click', (e) => {
    const nb = e.target.closest('[data-note]');
    if (!nb) return;
    const id = nb.dataset.note;
    const row = document.querySelector(`.s-task[data-id="${id}"]`);
    document.getElementById('noteTaskId').value = id;
    document.getElementById('noteText').value = row?.querySelector('.st-note-text')?.dataset.raw || '';
    openModal('noteModal');
    setTimeout(()=>document.getElementById('noteText').focus(),200);
  });
  document.getElementById('saveNoteBtn')?.addEventListener('click', async function(){
    const id = document.getElementById('noteTaskId').value;
    const note = document.getElementById('noteText').value;
    try {
      await api(window.API_TASKS,{method:'POST',body:{action:'note',id,student_note:note}});
      closeModal('noteModal'); toast('یادداشت ذخیره شد','success',1600);
      setTimeout(()=>location.reload(),700);
    } catch(e){ toast(e.error||'خطا','error'); }
  });

  document.getElementById('moodRow')?.addEventListener('click', async (e)=>{
    const b = e.target.closest('.mood-btn'); if(!b) return;
    document.querySelectorAll('.mood-btn').forEach(x=>x.classList.remove('active'));
    b.classList.add('active');
    try { await api(window.API_MOOD,{method:'POST',body:{mood:b.dataset.mood}}); toast('حالت ثبت شد 🌿','success',1400); }
    catch(e){}
  });

  // ======== پیشنهاد زیبای پر کردن گزارش بعد از تکمیل همه تسک‌ها ========
  function showReportPrompt(url) {
    // فقط یک‌بار در روز نشون بده
    const key = 'madar_report_prompt_' + new Date().toISOString().slice(0,10);
    if (sessionStorage.getItem(key)) return;
    sessionStorage.setItem(key, '1');

    const overlay = document.createElement('div');
    overlay.id = 'reportPromptOverlay';
    overlay.style.cssText = 'position:fixed;inset:0;z-index:9998;background:rgba(0,0,0,.6);display:grid;place-items:center;animation:fadeIn .3s ease';
    overlay.innerHTML = `
      <div style="background:linear-gradient(145deg,#1a2f25,#0c1512);border:2px solid var(--gold,#cbac80);border-radius:24px;padding:32px 28px;max-width:400px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.5);animation:slideUp .4s ease">
        <div style="font-size:3rem;margin-bottom:12px">🎉</div>
        <h3 style="font-size:1.25rem;font-weight:900;color:#e0c595;margin-bottom:8px">آفرین! همه تسک‌های امروز رو زدی!</h3>
        <p style="font-size:.9rem;color:#8aa791;margin-bottom:24px;line-height:1.7">حالا وقتشه گزارش روزانه‌ات رو پر کنی تا مشاورت عملکردت رو ببینه و بهت بازخورد بده.</p>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
          <a href="${url}" style="background:linear-gradient(135deg,#cbac80,#a8935f);color:#0c1512;padding:12px 28px;border-radius:14px;font-weight:900;font-size:.95rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px">📝 پر کردن گزارش</a>
          <button onclick="document.getElementById('reportPromptOverlay').remove()" style="background:rgba(255,255,255,.08);color:#8aa791;padding:12px 20px;border-radius:14px;border:1px solid rgba(255,255,255,.1);cursor:pointer;font-size:.9rem">بعداً</button>
        </div>
      </div>`;
    document.body.appendChild(overlay);
    overlay.addEventListener('click', (e) => { if(e.target === overlay) overlay.remove(); });
  }

  // ======== سیستم هشدار ساعت ۲۳ — یادآوری تسک‌ها و گزارش ========
  function checkNightReminder() {
    const now = new Date();
    const hour = now.getHours();
    const min = now.getMinutes();
    const todayKey = 'madar_night_remind_' + now.toISOString().slice(0,10);

    // ساعت ۲۳:۰۰ تا ۲۳:۵۹ — یادآوری
    if (hour === 23 && !sessionStorage.getItem(todayKey)) {
      const pendingTasks = document.querySelectorAll('.s-task[data-status="pending"]');
      if (pendingTasks.length > 0) {
        sessionStorage.setItem(todayKey, '1');
        // نمایش آلارم در صفحه
        showNightWarning(pendingTasks.length);
        // ارسال Push notification
        pushNightReminder(pendingTasks.length);
      }
    }
  }

  function showNightWarning(count) {
    // حذف هشدار قبلی اگر بود
    document.getElementById('nightWarningBanner')?.remove();
    const banner = document.createElement('div');
    banner.id = 'nightWarningBanner';
    banner.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:9999;background:linear-gradient(135deg,#2d1810,#1a0e08);border-bottom:3px solid #ff6b35;padding:16px 20px;display:flex;align-items:center;gap:14px;justify-content:center;flex-wrap:wrap;animation:slideDown .4s ease;box-shadow:0 4px 20px rgba(255,107,53,.25)';
    banner.innerHTML = `
      <span style="font-size:2rem">⚠️</span>
      <div style="text-align:center">
        <div style="font-weight:900;font-size:15px;color:#ff9a6c">مشتی! ${faNum(count)} تسک امروزتو هنوز نزدی!</div>
        <div style="font-size:13px;color:#cca88a;margin-top:3px">اگه <b>۱ ساعت دیگه</b> (ساعت ۱۲ شب) پر نکنی، گزارش امروز قفل می‌شه و دیگه نمی‌تونی ثبتش کنی 🔒</div>
      </div>
      <button onclick="this.closest('#nightWarningBanner').remove()" style="background:rgba(255,107,53,.2);border:1px solid rgba(255,107,53,.4);color:#ff9a6c;padding:8px 16px;border-radius:10px;cursor:pointer;font-weight:700;font-size:13px">فهمیدم</button>
    `;
    document.body.prepend(banner);
  }

  async function pushNightReminder(count) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    const reg = await navigator.serviceWorker?.ready?.catch(()=>null);
    const title = '⏰ تسک‌های امروز مونده!';
    const opts = {
      body: `${count} تسک هنوز تعیین وضعیت نشده. ۱ ساعت دیگه گزارش قفل می‌شه!`,
      icon: window.MADAR_ICON || '/assets/icons/icon-192.png',
      badge: window.MADAR_BADGE || '/assets/icons/favicon-64.png',
      tag: 'night-reminder',
      requireInteraction: true,
      data: { url: '/student/dashboard.php' },
      dir: 'rtl', lang: 'fa'
    };
    if (reg?.showNotification) reg.showNotification(title, opts);
    else new Notification(title, opts);
  }

  // اجرای اولیه + هر ۲ دقیقه چک کن
  checkNightReminder();
  setInterval(checkNightReminder, 120000);

  document.querySelectorAll('.day-tab').forEach(tab=>{
    tab.addEventListener('click', ()=>{
      document.querySelectorAll('.day-tab').forEach(t=>t.classList.remove('active'));
      tab.classList.add('active');
      const day = tab.dataset.day;
      document.querySelectorAll('[data-day-panel]').forEach(p=>p.classList.toggle('hidden', p.dataset.dayPanel !== day));
    });
  });

  function confetti(){
    const colors=['#cbac80','#6b8872','#e0c595','#8aa791'];
    for(let i=0;i<14;i++){
      const c=document.createElement('div');
      c.style.cssText=`position:fixed;width:8px;height:8px;border-radius:2px;z-index:9999;pointer-events:none;background:${colors[i%4]};left:${50+(Math.random()-.5)*30}%;top:60%`;
      document.body.appendChild(c);
      const dx=(Math.random()-.5)*400, dy=-(Math.random()*350+150), rot=Math.random()*720;
      c.animate([{transform:'translate(0,0) rotate(0)',opacity:1},{transform:`translate(${dx}px,${dy}px) rotate(${rot}deg)`,opacity:0}],{duration:900+Math.random()*500,easing:'cubic-bezier(.2,.6,.4,1)'}).onfinish=()=>c.remove();
    }
  }
})();
