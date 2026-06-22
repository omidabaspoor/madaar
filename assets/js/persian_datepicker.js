/* مَدار · Jalali/Persian date picker for every date input */
(() => {
  'use strict';
  const FA = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
  const EN = {'۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9','٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9'};
  const MONTHS = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
  const WEEK = ['ش','ی','د','س','چ','پ','ج'];
  const fa = n => String(n).replace(/\d/g, d => FA[d]);
  const en = s => String(s || '').replace(/[۰-۹٠-٩]/g, d => EN[d] ?? d);
  const pad = n => String(n).padStart(2,'0');

  function div(a,b){ return ~~(a / b); }
  function g2d(gy, gm, gd){
    let d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * ((gm + 9) % 12) + 2, 5) + gd - 34840408;
    d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
    return d;
  }
  function d2g(jdn){
    let j = 4 * jdn + 139361631;
    j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
    const i = div((j % 1461), 4) * 5 + 308;
    const gd = div((i % 153), 5) + 1;
    const gm = (div(i, 153) % 12) + 1;
    const gy = div(j, 1461) - 100100 + div(8 - gm, 6);
    return [gy, gm, gd];
  }
  function j2d(jy, jm, jd){
    jy = parseInt(jy); jm = parseInt(jm); jd = parseInt(jd);
    const epbase = jy - (jy >= 0 ? 474 : 473);
    const epyear = 474 + (epbase % 2820);
    return jd + (jm <= 7 ? (jm - 1) * 31 : ((jm - 1) * 30 + 6)) + div((epyear * 682) - 110, 2816) + (epyear - 1) * 365 + div(epbase, 2820) * 1029983 + (1948320 - 1);
  }
  function d2j(jdn){
    const depoch = jdn - j2d(475, 1, 1);
    const cycle = div(depoch, 1029983);
    const cyear = depoch % 1029983;
    let ycycle;
    if (cyear === 1029982) ycycle = 2820;
    else {
      const aux1 = div(cyear, 366), aux2 = cyear % 366;
      ycycle = div((2134 * aux1) + (2816 * aux2) + 2815, 1028522) + aux1 + 1;
    }
    let jy = ycycle + 2820 * cycle + 474;
    if (jy <= 0) jy--;
    const yday = jdn - j2d(jy, 1, 1) + 1;
    const jm = yday <= 186 ? Math.ceil(yday / 31) : Math.ceil((yday - 6) / 30);
    const jd = jdn - j2d(jy, jm, 1) + 1;
    return [jy, jm, jd];
  }
  function gToJ(gy,gm,gd){ return d2j(g2d(gy,gm,gd)); }
  function jToG(jy,jm,jd){ return d2g(j2d(jy,jm,jd)); }
  function isLeapJ(jy){ return (j2d(jy + 1, 1, 1) - j2d(jy, 1, 1)) === 366; }
  function monthLen(jy,jm){ return jm <= 6 ? 31 : (jm <= 11 ? 30 : (isLeapJ(jy) ? 30 : 29)); }
  function gregFromInput(v){ const m = String(v||'').match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? [+m[1],+m[2],+m[3]] : null; }
  function timeFromInput(v){ const m = String(v||'').match(/T(\d{2}:\d{2})/); return m ? m[1] : '08:00'; }
  function inputFromGreg(g){ return `${g[0]}-${pad(g[1])}-${pad(g[2])}`; }
  function labelFromJ(j){ return `${fa(j[0])}/${fa(pad(j[1]))}/${fa(pad(j[2]))}`; }
  function parseJalaliText(t){
    const nums = en(t).match(/\d+/g); if (!nums || nums.length < 3) return null;
    let [jy,jm,jd] = nums.map(Number); if (jy < 100) jy += 1400;
    if (jy < 1200 || jy > 1600 || jm < 1 || jm > 12 || jd < 1 || jd > monthLen(jy,jm)) return null;
    return [jy,jm,jd];
  }
  function todayJ(){ const d = new Date(); return gToJ(d.getFullYear(), d.getMonth()+1, d.getDate()); }
  function weekdayJ(jy,jm,jd){
    const [gy,gm,gd] = jToG(jy,jm,jd);
    const w = new Date(gy, gm-1, gd).getDay(); // Sun=0..Sat=6
    return ({6:0,0:1,1:2,2:3,3:4,4:5,5:6})[w];
  }

  let picker = null, active = null;
  function close(){ picker?.remove(); picker = null; active = null; }
  
  function setDate(ctx, j){
    const g = jToG(j[0], j[1], j[2]);
    ctx.original.value = ctx.hasTime ? (inputFromGreg(g) + 'T' + (ctx.time?.value || timeFromInput(ctx.original.value))) : inputFromGreg(g);
    ctx.visible.value = labelFromJ(j);
    ctx.original.dispatchEvent(new Event('input', {bubbles:true}));
    ctx.original.dispatchEvent(new Event('change', {bubbles:true}));
    close();
  }
  
  function render(ctx, jy, jm){
    picker?.remove();
    picker = document.createElement('div'); picker.className = 'pdp-pop';
    
    // Stop click events inside the picker from bubbling up to document and triggering close()
    picker.addEventListener('click', e => e.stopPropagation());
    
    const selectedG = gregFromInput(ctx.original.value); const selectedJ = selectedG ? gToJ(...selectedG) : null;
    const tJ = todayJ();
    const first = weekdayJ(jy,jm,1), len = monthLen(jy,jm);
    let cells = '';
    for (let i=0;i<first;i++) cells += '<span class="pdp-empty"></span>';
    for (let d=1; d<=len; d++) {
      const sel = selectedJ && selectedJ[0]===jy && selectedJ[1]===jm && selectedJ[2]===d;
      const tod = tJ[0]===jy && tJ[1]===jm && tJ[2]===d;
      cells += `<button type="button" class="pdp-day ${sel?'selected':''} ${tod?'today':''}" data-d="${d}">${fa(d)}</button>`;
    }
    picker.innerHTML = `<div class="pdp-head"><button type="button" data-pdp-prev>‹</button><b>${MONTHS[jm-1]} ${fa(jy)}</b><button type="button" data-pdp-next>›</button></div><div class="pdp-week">${WEEK.map(x=>`<span>${x}</span>`).join('')}</div><div class="pdp-grid">${cells}</div><div class="pdp-foot"><button type="button" data-pdp-today>امروز</button><button type="button" data-pdp-clear>پاک‌کردن</button></div>`;
    document.body.appendChild(picker);
    const r = ctx.visible.getBoundingClientRect();
    const top = Math.min(window.innerHeight - picker.offsetHeight - 10, r.bottom + 8);
    picker.style.top = Math.max(8, top) + 'px';
    picker.style.left = Math.max(8, Math.min(window.innerWidth - picker.offsetWidth - 8, r.left)) + 'px';
    picker.querySelector('[data-pdp-prev]').onclick = (e) => { e.stopPropagation(); jm--; if(jm<1){jm=12;jy--;} render(ctx,jy,jm); };
    picker.querySelector('[data-pdp-next]').onclick = (e) => { e.stopPropagation(); jm++; if(jm>12){jm=1;jy++;} render(ctx,jy,jm); };
    picker.querySelector('[data-pdp-today]').onclick = (e) => { e.stopPropagation(); setDate(ctx, todayJ()); };
    picker.querySelector('[data-pdp-clear]').onclick = (e) => { e.stopPropagation(); ctx.original.value=''; ctx.visible.value=''; ctx.original.dispatchEvent(new Event('change',{bubbles:true})); close(); };
    picker.querySelectorAll('.pdp-day').forEach(b => b.onclick = (e) => { e.stopPropagation(); setDate(ctx, [jy,jm,parseInt(b.dataset.d)]); });
  }
  
  function open(ctx){
    active = ctx;
    const g = gregFromInput(ctx.original.value); const j = g ? gToJ(...g) : todayJ();
    render(ctx, j[0], j[1]);
  }
  
  function enhance(input){
    if (input.dataset.pdpReady) return; input.dataset.pdpReady = '1';
    const originalType = input.type;
    const hasTime = originalType === 'datetime-local';
    const visible = document.createElement('input');
    visible.type = 'text'; visible.className = (input.className || 'input') + ' pdp-input';
    visible.placeholder = input.getAttribute('placeholder') || 'انتخاب تاریخ شمسی';
    visible.autocomplete = 'off'; visible.inputMode = 'numeric';
    visible.setAttribute('aria-label', input.getAttribute('aria-label') || 'تاریخ شمسی');
    const g = gregFromInput(input.value); if (g) visible.value = labelFromJ(gToJ(...g));
    const time = hasTime ? document.createElement('input') : null;
    if (time) { time.type='time'; time.className='input pdp-time'; time.value=timeFromInput(input.value); time.title='ساعت'; }
    input.type = 'hidden'; input.classList.add('pdp-original');
    input.insertAdjacentElement('afterend', visible);
    if (time) visible.insertAdjacentElement('afterend', time);
    const ctx = {original: input, visible, time, hasTime};
    const updateManual = () => {
      if (visible.value === '') {
        input.value = '';
        input.dispatchEvent(new Event('input', {bubbles:true}));
        input.dispatchEvent(new Event('change', {bubbles:true}));
        return;
      }
      const j = parseJalaliText(visible.value);
      if (j) {
        input.value = inputFromGreg(jToG(...j)) + (hasTime ? ('T' + (time?.value || '08:00')) : '');
        input.dispatchEvent(new Event('input', {bubbles:true}));
        input.dispatchEvent(new Event('change', {bubbles:true}));
      }
    };
    visible.addEventListener('focus', (e) => { e.stopPropagation(); open(ctx); });
    visible.addEventListener('click', (e) => { e.stopPropagation(); open(ctx); });
    visible.addEventListener('input', updateManual);
    visible.addEventListener('blur', () => {
      setTimeout(() => {
        if (visible.value === '') {
          input.value = '';
          input.dispatchEvent(new Event('change', {bubbles:true}));
        } else {
          const j = parseJalaliText(visible.value);
          if (j) {
            visible.value = labelFromJ(j);
            input.value = inputFromGreg(jToG(...j)) + (hasTime ? ('T' + (time?.value || '08:00')) : '');
            input.dispatchEvent(new Event('change', {bubbles:true}));
          }
        }
      }, 150);
    });
    time?.addEventListener('change', updateManual);
  }
  function init(){ document.querySelectorAll('input[type="date"]:not([data-no-persian-date]),input[type="datetime-local"]:not([data-no-persian-date])').forEach(enhance); }
  document.addEventListener('click', e => { if (picker && !picker.contains(e.target) && e.target !== active?.visible) close(); });
  document.addEventListener('keydown', e => { if(e.key === 'Escape') close(); });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
  window.MadarPersianDatepicker = { init, gToJ, jToG };
})();
