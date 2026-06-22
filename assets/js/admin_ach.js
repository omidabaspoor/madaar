/* =============== Admin Achievements management =============== */
(() => {
  'use strict';
  const faNum=(n)=>String(n).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
  const esc=(s)=>String(s||'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const ICO_TROPHY='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4z"/></svg>';

  /* ---- icon picker ---- */
  document.getElementById('iconPick')?.addEventListener('click',(e)=>{
    const b=e.target.closest('.icon-opt'); if(!b) return;
    document.querySelectorAll('.icon-opt').forEach(x=>x.classList.remove('active'));
    b.classList.add('active');
    document.getElementById('ach_icon').value=b.dataset.icon;
  });

  /* ---- condition type -> threshold field ---- */
  const ctype=document.getElementById('ach_ctype');
  function syncCtype(){
    const v=ctype.value, f=document.getElementById('thrField'), h=document.getElementById('ctypeHelp'), l=document.getElementById('thrLabel');
    if(v==='manual'){ f.style.display='none'; h.textContent='به‌صورت دستی توسط شما به دانش‌آموز اعطا می‌شود.'; }
    else if(v==='tasks_done'){ f.style.display=''; l.textContent='تعداد تسک لازم'; h.textContent='وقتی دانش‌آموز این تعداد تسک انجام دهد، خودکار اعطا می‌شود.'; }
    else { f.style.display=''; l.textContent='تعداد روز استریک'; h.textContent='وقتی استریک دانش‌آموز به این عدد برسد، خودکار اعطا می‌شود.'; }
  }
  ctype?.addEventListener('change',syncCtype);

  /* ---- open create ---- */
  document.getElementById('newAchBtn')?.addEventListener('click',()=>{
    document.getElementById('achForm').reset();
    document.getElementById('ach_id').value='';
    document.getElementById('ach_icon').value='trophy';
    document.getElementById('ach_active').checked=true;
    document.querySelectorAll('.icon-opt').forEach((x,i)=>x.classList.toggle('active',i===0));
    document.getElementById('achModalTitle').innerHTML='دستاورد جدید';
    syncCtype(); openModal('achModal');
    setTimeout(()=>document.getElementById('ach_title').focus(),200);
  });

  /* ---- open edit ---- */
  document.addEventListener('click',(e)=>{
    const b=e.target.closest('[data-edit]'); if(!b) return;
    const a=JSON.parse(b.dataset.edit);
    document.getElementById('ach_id').value=a.id;
    document.getElementById('ach_title').value=a.title;
    document.getElementById('ach_desc').value=a.description||'';
    document.getElementById('ach_icon').value=a.icon;
    document.querySelectorAll('.icon-opt').forEach(x=>x.classList.toggle('active',x.dataset.icon===a.icon));
    ctype.value=a.condition_type;
    document.getElementById('ach_thr').value=a.threshold||'';
    document.getElementById('ach_active').checked=String(a.is_active)==='1';
    document.getElementById('achModalTitle').innerHTML='ویرایش دستاورد';
    syncCtype(); openModal('achModal');
  });

  /* ---- submit create/edit ---- */
  document.getElementById('achForm')?.addEventListener('submit',async(e)=>{
    e.preventDefault();
    const fd=new FormData(e.target);
    const id=document.getElementById('ach_id').value;
    fd.append('action',id?'update':'create');
    if(!document.getElementById('ach_active').checked) fd.set('is_active','0');
    try{ await api(window.API_ACH,{method:'POST',body:fd});
      closeModal('achModal'); toast(id?'دستاورد ویرایش شد':'دستاورد ساخته شد','success');
      setTimeout(()=>location.reload(),700);
    }catch(err){ toast(err.error||'خطا','error'); }
  });

  /* ---- delete ---- */
  document.addEventListener('click',async(e)=>{
    const b=e.target.closest('[data-del-ach]'); if(!b) return;
    if(!confirm('این دستاورد حذف شود؟ از همه‌ی دانش‌آموزانی که گرفته‌اند هم حذف می‌شود.')) return;
    try{ await api(window.API_ACH,{method:'POST',body:{action:'delete',id:b.dataset.delAch}});
      toast('حذف شد','success'); setTimeout(()=>location.reload(),600);
    }catch(err){ toast(err.error||'خطا','error'); }
  });

  /* ---- recipients ---- */
  document.addEventListener('click',async(e)=>{
    const b=e.target.closest('[data-recipients]'); if(!b) return;
    document.getElementById('recipTitle').innerHTML='دریافت‌کنندگانِ «'+esc(b.dataset.title)+'»';
    const body=document.getElementById('recipBody');
    body.innerHTML='<div class="empty-state"><span class="spinner"></span></div>';
    openModal('recipModal');
    try{
      const d=await api(window.API_ACH_RECIP+'?id='+b.dataset.recipients);
      if(!d.items.length){ body.innerHTML='<div class="empty-state" style="padding:30px">هنوز کسی این دستاورد را نگرفته</div>'; return; }
      body.innerHTML=d.items.map(r=>`
        <div class="between" style="padding:11px 4px;border-bottom:1px solid var(--border-soft)">
          <div class="u-row"><span class="u-ava" style="width:36px;height:36px;font-size:.8rem">${esc(r.name.slice(0,2))}</span>
            <div><div style="font-weight:700;font-size:.9rem">${esc(r.name)}</div><div class="muted" style="font-size:.74rem">${esc(r.field)} · ${r.ago} ${r.manual?'· دستی':''}</div></div></div>
          <button class="btn btn-ghost btn-sm" style="color:var(--danger)" data-revoke="${b.dataset.recipients}" data-sid="${r.id}">لغو</button>
        </div>`).join('');
    }catch(err){ body.innerHTML='<div class="empty-state">خطا</div>'; }
  });

  /* ---- revoke ---- */
  document.addEventListener('click',async(e)=>{
    const b=e.target.closest('[data-revoke]'); if(!b) return;
    if(!confirm('این دستاورد از این دانش‌آموز لغو شود؟')) return;
    try{ await api(window.API_ACH,{method:'POST',body:{action:'revoke',id:b.dataset.revoke,student_id:b.dataset.sid}});
      b.closest('.between').remove(); toast('لغو شد','success',1500);
    }catch(err){ toast(err.error||'خطا','error'); }
  });

  /* ---- award to student ---- */
  let awardAchId=null;
  function renderAwardList(filter=''){
    const list=document.getElementById('awardList');
    const f=filter.trim();
    const items=(window.STUDENTS||[]).filter(s=>!f||s.name.includes(f));
    if(!items.length){ list.innerHTML='<div class="empty-state" style="padding:24px">دانش‌آموزی نیست</div>'; return; }
    list.innerHTML=items.map(s=>`
      <button class="award-row" data-give="${s.id}">
        <span class="u-ava" style="width:34px;height:34px;font-size:.78rem">${esc(s.name.slice(0,2))}</span>
        <div style="text-align:right;flex:1"><div style="font-weight:700;font-size:.9rem">${esc(s.name)}</div><div class="muted" style="font-size:.74rem">${esc(s.field||'')}</div></div>
        <span class="give-ico">${'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>'}</span>
      </button>`).join('');
  }
  document.addEventListener('click',(e)=>{
    const b=e.target.closest('[data-award]'); if(!b) return;
    awardAchId=b.dataset.award;
    document.getElementById('award_ach_id').value=awardAchId;
    document.getElementById('awardAchTitle').textContent=b.dataset.title;
    document.getElementById('awardSearch').value='';
    renderAwardList(''); openModal('awardModal');
  });
  document.getElementById('awardSearch')?.addEventListener('input',(e)=>renderAwardList(e.target.value));
  document.addEventListener('click',async(e)=>{
    const b=e.target.closest('[data-give]'); if(!b) return;
    try{ const d=await api(window.API_ACH,{method:'POST',body:{action:'award',id:awardAchId,student_id:b.dataset.give}});
      toast(d.awarded?'دستاورد اعطا شد 🏆':'این دانش‌آموز قبلاً این دستاورد را داشت','success');
      closeModal('awardModal'); setTimeout(()=>location.reload(),700);
    }catch(err){ toast(err.error||'خطا','error'); }
  });
})();
