/* مَدار Online Class — synced raw UI + PHP/MySQL signaling + P2P media */
(function(){
'use strict';
const R=window.MADAR_ROOM||{}, CSRF=window.MADAR?.csrf||document.querySelector('meta[name="csrf-token"]')?.content||'';
const $=(s,r=document)=>r.querySelector(s), $$=(s,r=document)=>Array.from(r.querySelectorAll(s));
const api=(action,body={})=>fetch(`${R.apiBase}/online_room.php?action=${encodeURIComponent(action)}`,{method:'POST',credentials:'include',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(Object.assign({_csrf:CSRF},body))}).then(r=>r.json());
const p2pApi=(action,body={})=>fetch(`${R.apiBase}/online_p2p.php?action=${encodeURIComponent(action)}&room_id=${R.sessionId}`,{method:'POST',credentials:'include',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(Object.assign({room_id:R.sessionId,_csrf:CSRF},body))}).then(r=>r.json());
let p2p=null, chatLast=0, unread=0, startTs=Date.now(), permLast=0, kickTarget=0, timers=[], pinned=null, hostStream=null;
const onlineUsers=new Map(), tileToUser=new Map(), handUsers=new Set(), seenPerm=new Set();
const cssEsc=(v)=>window.CSS&&CSS.escape?CSS.escape(String(v)):String(v).replace(/[^a-zA-Z0-9_-]/g,'\\$&');

function esc(s){return String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
function faNum(n){return String(n).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);}
function letters(name){name=String(name||'کاربر').trim();return name.slice(0,2)||'ک';}
function toast(m,t='in'){
 const ic={ok:'✓',wn:'!',er:'×',in:'i'}; const cl={ok:'tok',wn:'twn',er:'ter',in:'tin'};
 const c=$('#tts'); if(!c)return; const d=document.createElement('div'); d.className='tt';
 d.innerHTML=`<div class="ti ${cl[t]||'tin'}"><span style="font-weight:1000">${ic[t]||'i'}</span></div><span class="tm">${esc(m)}</span>`;
 c.appendChild(d); setTimeout(()=>{d.classList.add('out');setTimeout(()=>d.remove(),320)},3200);
}
function showMdl(id){$('#'+id)?.classList.add('on')} function hideMdl(id){$('#'+id)?.classList.remove('on')}
function setBtn(btn,on){btn.classList.toggle('on',!!on);btn.classList.toggle('off',!on)}
function setMicIcon(on){$('#micOn').style.display=on?'':'none';$('#micOff').style.display=on?'none':'';setBtn($('#micTB'),on)}
function setCamIcon(on){$('#camOn').style.display=on?'':'none';$('#camOff').style.display=on?'none':'';setBtn($('#camTB'),on)}
function updateCount(){const live=onlineUsers.size+1; $('#cnt')&&($('#cnt').textContent=faNum(Math.max(live,1))+' نفر');}

/* ---------- sidebar ---------- */
function openSB(t){const sb=$('#sb'); if(sb.classList.contains('open')&&document.body.dataset.tab===t){closeSB();return} sb.classList.add('open'); swTab(t); if(t==='ch'){unread=0;$('#cBdg')?.classList.remove('on')}}
function closeSB(){$('#sb')?.classList.remove('open')}
function swTab(t){document.body.dataset.tab=t; $('#tCh')?.classList.toggle('on',t==='ch'); $('#tPt')?.classList.toggle('on',t==='pt'); $('#chPn')?.classList.toggle('on',t==='ch'); $('#ptPn')?.classList.toggle('on',t==='pt')}

/* ---------- participants UI ---------- */
function renderParticipants(){
 const list=$('#ptList'); if(!list)return; list.innerHTML='';
 list.appendChild(partRow({id:R.advisor.id,name:R.advisor.name,avatar:R.advisor.avatar,role:'مشاور',advisor:true,online:R.isHost||onlineUsers.has(R.advisor.id)}));
 (R.participants||[]).forEach(p=>list.appendChild(partRow({id:p.id,name:p.name,avatar:p.avatar,role:'دانش‌آموز',advisor:false,online:onlineUsers.has(p.id)})));
 updateCount();
}
function partRow(p){
 const div=document.createElement('div'); div.className='pt-i '+(p.online?'':'offline'); div.dataset.userId=p.id;
 div.innerHTML=`<div class="pt-av" style="background:${p.advisor?'linear-gradient(135deg,var(--g1),var(--g3))':'linear-gradient(135deg,var(--blu),#1565c0)'}">${esc(p.avatar||letters(p.name))}<div class="od"></div></div><div class="pt-inf"><div class="pt-nm">${esc(p.name)}</div><div class="pt-rl ${p.advisor?'adv':''}">${esc(p.role)}</div></div><div class="media-state"><i class="mic"></i><i class="cam"></i><i class="screen"></i></div>${R.isHost&&!p.advisor?`<div class="pt-acts"><button class="pa pwb" data-force="wb_off" data-user="${p.id}" title="قطع تخته">🎨❌</button><button class="pa pm" data-force="mic_off" data-user="${p.id}" title="بستن میکروفون">${svgMic()}</button><button class="pa pc" data-force="cam_off" data-user="${p.id}" title="بستن دوربین">${svgCam()}</button><button class="pa pk" data-force="kick" data-user="${p.id}" title="اخراج">${svgX()}</button></div>`:''}`;
 return div;
}
function updatePartState(uid,meta={}){const row=$(`.pt-i[data-user-id="${uid}"]`); if(!row)return; row.classList.remove('offline'); row.querySelector('.media-state .mic')?.classList.toggle('on',!!+meta.mic_on); row.querySelector('.media-state .cam')?.classList.toggle('on',!!+meta.cam_on); row.querySelector('.media-state .screen')?.classList.toggle('on',!!+meta.screen_on);}
function handleMeta(peer){const uid=+peer.user_id;if(!uid)return;onlineUsers.set(uid,true);tileToUser.set(peer.peer_id,uid);updatePartState(uid,peer);if(!+peer.is_host&&!$(`.sc[data-user-id="${uid}"]`)){renderStudentPlaceholder(peer,uid)}updateCount();}
function renderStudentPlaceholder(peer,uid){const row=$('#sR');if(!row)return;const card=document.createElement('div');card.className='sc';card.dataset.tile=peer.peer_id;card.dataset.userId=uid;card.innerHTML=`<div class="ico-m ${+peer.mic_on?'':'on'}">${svgMicOff()}</div><div class="ico-h ${handUsers.has(uid)?'on':''}">${svgHand()}</div><div class="vbg"><div class="vav" style="background:linear-gradient(135deg,var(--blu),#1565c0)">${esc(letters(peer.name))}</div></div><div class="vbot"><div class="vlbl">${esc(peer.name||'دانش‌آموز')}</div></div>${R.isHost&&uid&&uid!==R.userId?`<div class="sc-ct"><button class="scb sl ${R.pinnedUserId===uid?'active':''}" data-promote="${uid}" title="قاب اصلی">${svgMax()}</button><button class="scb sm" data-force="mic_off" data-user="${uid}">${svgMic()}</button><button class="scb scc" data-force="cam_off" data-user="${uid}">${svgCam()}</button><button class="scb sk" data-force="kick" data-user="${uid}">${svgX()}</button></div>`:''}`;row.appendChild(card);}
function setHand(uid,on){const card=$(`.sc[data-user-id="${uid}"]`); card?.querySelector('.ico-h')?.classList.toggle('on',!!on);}
function svgMic(){return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>'}
function svgCam(){return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>'}
function svgX(){return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>'}
function svgMax(){return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 3H3v5M16 3h5v5M8 21H3v-5M21 16v5h-5"/></svg>'}
function svgMicOff(){return '<svg viewBox="0 0 24 24" fill="none" stroke="#ff4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="1" y1="1" x2="23" y2="23"/><path d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6"/><path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v1a7 7 0 0 1-.11 1.23"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>'}
function svgHand(){return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 11V6a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v0"/><path d="M14 10V4a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v2"/><path d="M10 10.5V6a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v8"/><path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/></svg>'}

/* ---------- media renderer for P2P hook ---------- */
function attachVideo(bg,stream){
 if(!bg)return; let v=bg.querySelector('video'); if(!v){bg.innerHTML=''; v=document.createElement('video'); v.autoplay=true; v.playsInline=true; bg.appendChild(v)} if(v.srcObject!==stream)v.srcObject=stream; bg.classList.add('has-video');
}
function onTile(t){
 const meta=t.meta||{}, uid=+meta.user_id || (t.isLocal?R.userId:0); if(uid) { onlineUsers.set(uid,true); updatePartState(uid,meta); }
 tileToUser.set(t.id,uid);
 const isScreen=t.id.includes('_screen') || !!+meta.screen_on;
 if(isScreen){ $('#scrC')?.classList.add('on'); attachVideo($('#screenBg'),t.stream); setBtn($('#scrTB'),!!(t.isLocal&&p2p?.screenOn)); return true; }
 if(t.isHost || !!+meta.is_host){
   hostStream=t.stream;
   if(!R.pinnedUserId){
     attachVideo($('#advBg'),t.stream);
     $('#advNameLbl')&&($('#advNameLbl').textContent=t.name.replace(' (شما)',''));
     const role=$('#advC .vrl'); if(role){role.textContent='مشاور';role.classList.add('adv')}
   }
   return true;
 }
 renderStudentTile(t,uid,meta);
 if(uid && +uid === +R.pinnedUserId) applyStagePin(uid);
 return true;
}
function onRemoveTile(id){
 const uid=tileToUser.get(id); tileToUser.delete(id);
 if(id.includes('_screen')){ if(![...tileToUser.keys()].some(x=>x.includes('_screen'))){ $('#scrC')?.classList.remove('on'); const bg=$('#screenBg'); if(bg){bg.classList.remove('has-video');bg.innerHTML='<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--g1)" stroke-width="1.5" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg><span class="screen-empty">صفحه مشاور</span>';}} return; }
 const card=$(`.sc[data-tile="${cssEsc(id)}"]`); if(card)card.remove();
 if(uid){onlineUsers.delete(uid); renderParticipants();}
}
function renderStudentTile(t,uid,meta){
 const row=$('#sR'); if(!row)return; let card=$(`.sc[data-tile="${cssEsc(t.id)}"]`);
 if(!card){card=document.createElement('div');card.className='sc';card.dataset.tile=t.id;card.dataset.userId=uid||'';row.appendChild(card)}
 card.innerHTML=`<div class="ico-m ${+meta.mic_on?'':'on'}">${svgMicOff()}</div><div class="ico-h ${handUsers.has(uid)?'on':''}">${svgHand()}</div><div class="vbg"></div><div class="vbot"><div class="vlbl">${esc(t.name.replace(' (شما)',''))}</div></div>${R.isHost&&uid&&uid!==R.userId?`<div class="sc-ct"><button class="scb sl ${R.pinnedUserId===uid?'active':''}" data-promote="${uid}" title="قاب اصلی">${svgMax()}</button><button class="scb sm" data-force="mic_off" data-user="${uid}">${svgMic()}</button><button class="scb scc" data-force="cam_off" data-user="${uid}">${svgCam()}</button><button class="scb sk" data-force="kick" data-user="${uid}">${svgX()}</button></div>`:''}`;
 const bg=card.querySelector('.vbg'); attachVideo(bg,t.stream); if(!+meta.cam_on && !+meta.screen_on){bg.classList.remove('has-video');bg.innerHTML=`<div class="vav" style="background:linear-gradient(135deg,var(--blu),#1565c0)">${esc(letters(t.name))}</div>`}
}
function applyStagePin(uid){
  uid = +uid || 0;
  R.pinnedUserId = uid;
  const advC = $('#advC');
  let unpinBtn = advC?.querySelector('.unpin-stage-btn');
  if(uid === 0){
    pinned = null;
    advC?.classList.remove('pinned-student');
    if(unpinBtn) unpinBtn.style.display = 'none';
    if(hostStream){
      attachVideo($('#advBg'), hostStream);
    }else{
      $('#advBg').classList.remove('has-video');
      $('#advBg').innerHTML = `<div class="vav" style="background:linear-gradient(135deg,var(--g1),var(--g3))">${esc(R.advisor.avatar||'م')}<div class="sp-rng"></div></div><span class="vav-nm">${esc(R.advisor.name)}</span>`;
    }
    $('#advNameLbl') && ($('#advNameLbl').textContent = R.advisor.name);
    const role = advC?.querySelector('.vrl');
    if(role){ role.textContent = 'مشاور'; role.classList.add('adv'); }
    $$('.scb.sl').forEach(b => b.classList.remove('active'));
    return;
  }
  const card = $(`.sc[data-user-id="${uid}"]`);
  const name = card?.querySelector('.vlbl')?.textContent || 'دانش‌آموز';
  const video = card?.querySelector('video');
  pinned = {uid, name};
  advC?.classList.add('pinned-student');
  if(!unpinBtn && advC){
    unpinBtn = document.createElement('button');
    unpinBtn.className = 'unpin-stage-btn';
    unpinBtn.type = 'button';
    unpinBtn.title = 'بازگشت قاب مشاور';
    unpinBtn.innerHTML = `${svgX()} بازگشت قاب مشاور`;
    unpinBtn.onclick = (e) => { e.stopPropagation(); if(R.isHost) api('stage_promote',{session_id:R.sessionId,user_id:0}); else toast('فقط مشاور می‌تواند قاب اصلی را تغییر دهد','wn'); };
    advC.appendChild(unpinBtn);
  }
  if(unpinBtn) unpinBtn.style.display = '';
  const bg = $('#advBg');
  if(video && video.srcObject){
    attachVideo(bg, video.srcObject);
  }else{
    bg.classList.remove('has-video');
    bg.innerHTML = `<div class="vav" style="background:linear-gradient(135deg,var(--blu),#1565c0)">${esc(letters(name))}<div class="sp-rng"></div></div><span class="vav-nm">${esc(name)}</span>`;
  }
  $('#advNameLbl') && ($('#advNameLbl').textContent = name);
  const role = advC?.querySelector('.vrl');
  if(role){ role.textContent = 'دانش‌آموز'; role.classList.remove('adv'); }
  $$('.scb.sl').forEach(b => {
    const p = b.closest('.sc');
    b.classList.toggle('active', p && +p.dataset.userId === uid);
  });
}
function promoteStudent(uid){
  if(!R.isHost){ toast('فقط مشاور می‌تواند قاب اصلی را تغییر دهد','wn'); return; }
  uid = +uid || 0;
  const target = (R.pinnedUserId === uid) ? 0 : uid;
  api('stage_promote', {session_id: R.sessionId, user_id: target}).then(r => {
    if(r?.ok) applyStagePin(target);
  });
  applyStagePin(target);
}
function restoreHost(){
  if(R.isHost) api('stage_promote', {session_id: R.sessionId, user_id: 0});
  applyStagePin(0);
}

/* ---------- P2P/session ---------- */
async function ensureLiveThenStart(){
 renderParticipants();
 if(R.pinnedUserId) applyStagePin(R.pinnedUserId);
 if(R.isHost && R.status==='scheduled'){ const r=await api('start_session',{session_id:R.sessionId}).catch(()=>null); if(r?.ok){R.status='live';toast('کلاس شروع شد','ok')} else toast(r?.error||'شروع کلاس ثبت نشد','er'); }
 if(!R.isHost && R.status!=='live'){
   addSystem('کلاس هنوز توسط مشاور شروع نشده است. این صفحه را باز نگه دارید.');
   const tm=setInterval(async()=>{const r=await api('session_state',{session_id:R.sessionId}).catch(()=>null); if(r?.ok){R.status=r.status;Object.assign(R.permissions,r.permissions||{}); if(r.pinned_user_id!==undefined&&+r.pinned_user_id!==+R.pinnedUserId)applyStagePin(+r.pinned_user_id); if(r.status==='live'){clearInterval(tm); toast('کلاس شروع شد','ok'); startMedia(); applyChatState();}}},2000); timers.push(tm);
 } else {
   startMedia();
 }
}
async function startMedia(){
 if(!window.MadarP2P){toast('موتور تماس بارگذاری نشد','er');return}
 try{p2p=await new window.MadarP2P(R,{toast,onTile,onRemoveTile,onMeta:handleMeta,onReady:()=>toast('اتصال کلاس آماده است','ok')}).start($('#p2pMount')); setMicIcon(!!p2p.micOn); setCamIcon(!!p2p.camOn);}catch(e){console.error(e);toast('اتصال دوربین/صدا برقرار نشد','er')}
}

/* ---------- chat ---------- */
function addSystem(text){const d=document.createElement('div');d.className='sys-msg';d.textContent=text;$('#chMs')?.appendChild(d)}
function addMsg(m){ if(+m.id<=chatLast)return; chatLast=Math.max(chatLast,+m.id); const list=$('#chMs'); if(!list)return; if(m.message_type==='system'){addSystem(m.message);return} const mine=+m.user_id===+R.userId; const av=letters(m.user_name); const advisor=m.user_role==='advisor'||m.user_role==='admin'; const div=document.createElement('div'); div.className='mg'; div.innerHTML=`<div class="mg-a ${advisor?'a':'s1'}">${esc(av)}</div><div class="mg-w"><div class="mg-mt"><span class="mg-nm">${esc(m.user_name||'کاربر')}</span><span class="mg-rl ${advisor?'a':'s'}">${advisor?'مشاور':'دانش‌آموز'}</span><span class="mg-tm">${esc((m.created_at||'').slice(11,16))}</span></div><div class="mg-bb ${mine||advisor?'adv':'stu'}">${esc(m.message||'')}</div></div>`; list.appendChild(div); list.scrollTop=list.scrollHeight; if(!$('#sb').classList.contains('open')||document.body.dataset.tab!=='ch'){unread++; const b=$('#cBdg'); b.textContent=unread; b.classList.add('on')} }
async function pollChat(){const r=await api('chat_list',{session_id:R.sessionId,after_id:chatLast}).catch(()=>null); if(r?.ok){(r.messages||[]).forEach(addMsg); if(r.pinned_user_id!==undefined&&+r.pinned_user_id!==+R.pinnedUserId)applyStagePin(+r.pinned_user_id);}}
async function sendChat(){const input=$('#chIn'), msg=input.value.trim(); if(!msg)return; if(!R.permissions.chat){toast('چت غیرفعال است','wn');return} const r=await api('chat_send',{session_id:R.sessionId,message:msg}).catch(()=>null); if(r?.ok){input.value='';pollChat()} else toast(r?.error||'ارسال پیام انجام نشد','er')}
function applyChatState(){const dis=!R.permissions.chat; $('#chatBox')?.classList.toggle('disabled',dis); $('#chIn').placeholder=dis?'چت غیرفعال است':'پیام...'}

/* ---------- permissions ---------- */
function permLabel(t){return {mic:'میکروفون',cam:'دوربین',screen:'اشتراک صفحه',whiteboard:'تخته'}[t]||'دسترسی'}
async function requestPerm(t){if(R.isHost)return true; const r=await api('permission_request',{session_id:R.sessionId,permission_type:t}).catch(()=>null); toast(r?.message||r?.error||'درخواست ارسال نشد',r?.ok?'ok':'er'); return false}
async function pollPermHost(){if(!R.isHost)return; const r=await api('permission_list',{session_id:R.sessionId}).catch(()=>null); if(!r?.ok)return; const box=$('#permReqs'); const arr=r.requests||[]; box.classList.toggle('on',arr.length>0); arr.forEach(x=>{if(!seenPerm.has(String(x.id))){seenPerm.add(String(x.id));toast(`${x.user_name} درخواست ${permLabel(x.permission_type)} دارد`,'wn');openSB('pt');}}); box.innerHTML=arr.map(x=>`<div class="req"><div><div class="req-n">${esc(x.user_name)}</div><div class="req-t">درخواست ${permLabel(x.permission_type)}</div></div><div class="req-b"><button class="rq ok" data-dec="approved" data-id="${x.id}">اجازه</button><button class="rq no" data-dec="denied" data-id="${x.id}">رد</button></div></div>`).join('')}
async function pollPermStudent(){if(R.isHost)return; const r=await api('permission_status',{session_id:R.sessionId,after_id:permLast}).catch(()=>null); if(!r?.ok)return; for(const x of r.requests||[]){permLast=Math.max(permLast,+x.id); const ok=x.status==='approved'; R.permissions[x.permission_type]=ok; toast(ok?`اجازه ${permLabel(x.permission_type)} فعال شد`:`درخواست ${permLabel(x.permission_type)} رد شد`,ok?'ok':'wn'); if(!ok){ if(x.permission_type==='mic'&&p2p)setMicIcon(await p2p.forceMic(false)); if(x.permission_type==='cam'&&p2p)setCamIcon(await p2p.forceCam(false)); if(x.permission_type==='screen'&&p2p){await p2p.stopScreen();setBtn($('#scrTB'),false)} } applyWhiteboardPermission(); applyChatState(); }}
async function syncState(){const r=await api('session_state',{session_id:R.sessionId}).catch(()=>null); if(!r?.ok)return; if(['ended','cancelled'].includes(r.status)){toast('کلاس پایان یافت','in');setTimeout(()=>location.href=R.exitUrl,900);return} R.status=r.status; Object.assign(R.permissions,r.permissions||{}); if(r.pinned_user_id!==undefined&&+r.pinned_user_id!==+R.pinnedUserId)applyStagePin(+r.pinned_user_id); applyWhiteboardPermission(); applyChatState();}

/* ---------- controls ---------- */
async function toggleMic(){if(!R.permissions.mic&&!R.isHost)return requestPerm('mic'); if(!p2p)return; setMicIcon(await p2p.toggleMic())}
async function toggleCam(){if(!R.permissions.cam&&!R.isHost)return requestPerm('cam'); if(!p2p)return; setCamIcon(await p2p.toggleCam())}
async function toggleScreen(){if(!R.permissions.screen&&!R.isHost)return requestPerm('screen'); if(!p2p)return; setBtn($('#scrTB'),await p2p.toggleScreen())}
async function toggleHand(){const r=await api('hand_toggle',{session_id:R.sessionId}).catch(()=>null); if(r?.ok){setBtn($('#handTB'),!!r.raised);toast(r.raised?'دست شما بالا رفت ✋':'دست پایین آمد','in')} else toast(r?.error||'ثبت دست انجام نشد','er')}
async function forceUser(uid,cmd){
  if(cmd==='wb_off'){
    api('revoke_user_perm', {session_id: R.sessionId, user_id: uid, type: 'whiteboard'});
    toast('دسترسی تخته دانش‌آموز قطع شد','wn');
    return;
  }
  if(cmd==='kick'){kickTarget=uid; $('#kMsg').textContent='دانش‌آموز از کلاس خارج شود؟'; showMdl('kMdl'); return;}
  const r=await p2pApi('command',{target_user_id:uid,command:cmd}).catch(()=>null); toast(r?.ok?'دستور ارسال شد':'دستور ارسال نشد',r?.ok?'ok':'er');
}
function toggleGlobalPerm(type){
  if(!R.isHost) return;
  const cur = R.permissions[type] !== false;
  const nxt = !cur;
  R.permissions[type] = nxt;
  api('update_permissions', {session_id: R.sessionId, [type]: nxt ? 1 : 0}).then(() => {
    toast(nxt ? `دسترسی عمومی ${permLabel(type)} آزاد شد` : `دسترسی عمومی ${permLabel(type)} قطع شد`, nxt ? 'ok' : 'wn');
    const b = $('#gPerm' + type.charAt(0).toUpperCase() + type.slice(1));
    if(b){ b.className = 'perm-btn ' + (nxt ? 'on' : 'off'); b.textContent = `${permLabel(type)}: ${nxt ? 'آزاد' : 'قطع'}`; }
  });
}
async function doKick(){if(!kickTarget)return; const r=await p2pApi('command',{target_user_id:kickTarget,command:'kick'}).catch(()=>null); hideMdl('kMdl'); toast(r?.ok?'دانش‌آموز خارج شد':'اخراج انجام نشد',r?.ok?'ok':'er'); kickTarget=0}
async function muteAll(){for(const p of (R.participants||[])) await p2pApi('command',{target_user_id:p.id,command:'mic_off'}).catch(()=>{}); toast('درخواست بستن میکروفون همه ارسال شد','wn')}
async function leave(endAll=false){try{if(endAll&&R.isHost)await api('end_session',{session_id:R.sessionId})}catch(e){} try{await p2p?.leave()}catch(e){} location.href=R.exitUrl}

/* ---------- hand host polling ---------- */
async function pollHands(){if(!R.isHost)return; const r=await api('hand_list',{session_id:R.sessionId}).catch(()=>null); if(!r?.ok)return; handUsers.clear(); (r.hands||[]).forEach(h=>handUsers.add(+h.user_id)); $$('.sc[data-user-id]').forEach(c=>setHand(+c.dataset.userId,handUsers.has(+c.dataset.userId)));}

/* ---------- whiteboard: raw-sent tools + DB snapshot sync ---------- */
let wb={open:false,tool:'pen',color:'#333',size:2,drawing:false,dirty:false,version:0,hist:[],redo:[],last:null,start:null,snapshot:null,shape:null,sel:null,selecting:false,moving:false,moveOff:null,pdfDoc:null,pdfPage:1,pdfTotal:0,pdfZoom:1,pdfOffset:{x:0,y:0},pan:false,panLast:null};
const pC=$('#pC'), dC=$('#dC'), uC=$('#uC'), px=pC.getContext('2d'), dx=dC.getContext('2d'), ux=uC.getContext('2d');
function wbAllowed(){return !!(R.isHost||R.permissions.whiteboard)}
function wbApplySizeClass(){const pn=$('#wbPn'),w=pn.getBoundingClientRect().width;pn.classList.remove('sz-l','sz-m','sz-s');pn.classList.add(w>620?'sz-l':(w>390?'sz-m':'sz-s'))}
function resizeWB(){const r=$('#wbCa')?.getBoundingClientRect()||{width:300,height:200}; const prev=(pC.width&&pC.height)?composite():null; const w=Math.max(300,Math.floor(r.width||300)); const h=Math.max(200,Math.floor(r.height||200)); [pC,dC,uC].forEach(c=>{c.width=w;c.height=h;}); px.fillStyle='#fff';px.fillRect(0,0,pC.width,pC.height);dx.clearRect(0,0,dC.width,dC.height);ux.clearRect(0,0,uC.width,uC.height); if(prev){const im=new Image();im.onload=()=>px.drawImage(im,0,0,pC.width,pC.height);im.src=prev}else if(wb.snapshot){const im=new Image();im.onload=()=>px.drawImage(im,0,0,pC.width,pC.height);im.src=wb.snapshot} if(wb.pdfDoc)renderPdfPage(wb.pdfPage); wbApplySizeClass()}
function applyWhiteboardPermission(){
  const canDraw = wbAllowed();
  dC.style.pointerEvents = canDraw ? 'auto' : 'none';
  $$('.wb-hd [data-tool], .wb-hd .cR, .wb-hd .sR, .wb-hd .wd, .wb-hd #wbUndo, .wb-hd #wbRedo, .wb-hd #wbClear, .wb-hd #wbPdfBtn').forEach(el => {
    el.style.display = canDraw ? '' : 'none';
  });
  let banner = $('#wbGuestBanner');
  if(!canDraw){
    if(!banner){
      banner = document.createElement('button');
      banner.id = 'wbGuestBanner';
      banner.className = 'wb-guest-banner';
      banner.type = 'button';
      banner.innerHTML = '👁️ مشاهده تخته · برای اجازه طراحی کلیک کنید ✋';
      banner.onclick = () => { requestPerm('whiteboard'); toast('درخواست طراحی ارسال شد','ok'); };
      $('.wb-hd')?.insertBefore(banner, $('.wb-hd')?.firstChild);
    }
    banner.style.display = '';
  } else if(banner) {
    banner.style.display = 'none';
  }
}
function toggleWB(force){
  const show=force===undefined?!wb.open:force;
  wb.open=show;
  $('#wbPn').classList.toggle('on',show);
  $('#wbOv').classList.toggle('on',show);
  setBtn($('#wbTB'),show);
  if(show){
    applyWhiteboardPermission();
    setTimeout(()=>{resizeWB();loadBoard(true)},50);
    setTimeout(resizeWB,380);
  }
}
function wbSetWidth(w){const pn=$('#wbPn'); if(innerWidth<=900){pn.style.width='100vw';return} pn.style.width=Math.max(300,Math.min(innerWidth-10,w))+'px'; wbApplySizeClass(); resizeWB()}
function wbStep(n){wbSetWidth(($('#wbPn').getBoundingClientRect().width||900)+n*(innerWidth<700?60:90))}
function wbFull(){const pn=$('#wbPn'),cur=pn.getBoundingClientRect().width,max=innerWidth-10,def=Math.min(innerWidth*.75,1120); wbSetWidth(cur>innerWidth*.92?def:max)}
function pos(e){const r=dC.getBoundingClientRect(),p=e.touches?e.touches[0]:(e.changedTouches?e.changedTouches[0]:e); const sx=dC.width/(r.width||1), sy=dC.height/(r.height||1); return{x:Math.max(0,Math.min(dC.width,(p.clientX-r.left)*sx)), y:Math.max(0,Math.min(dC.height,(p.clientY-r.top)*sy))}}
function saveHist(){try{wb.hist.push(dC.toDataURL('image/png')); wb.redo=[]; if(wb.hist.length>50)wb.hist.shift()}catch(e){}}
function restoreLayer(data){const im=new Image();im.onload=()=>{dx.clearRect(0,0,dC.width,dC.height);dx.drawImage(im,0,0,dC.width,dC.height);wb.dirty=true;scheduleSave()};im.src=data}
function wbUndo(){if(!wb.hist.length)return; try{wb.redo.push(dC.toDataURL('image/png')); restoreLayer(wb.hist.pop())}catch(e){}}
function wbRedo(){if(!wb.redo.length)return; try{wb.hist.push(dC.toDataURL('image/png')); restoreLayer(wb.redo.pop())}catch(e){}}
function clearUI(){ux.clearRect(0,0,uC.width,uC.height)}
function drawArrow(ctx,x1,y1,x2,y2){const a=Math.atan2(y2-y1,x2-x1),h=Math.max(12,wb.size*5);ctx.beginPath();ctx.moveTo(x1,y1);ctx.lineTo(x2,y2);ctx.stroke();ctx.beginPath();ctx.moveTo(x2,y2);ctx.lineTo(x2-h*Math.cos(a-Math.PI/6),y2-h*Math.sin(a-Math.PI/6));ctx.lineTo(x2-h*Math.cos(a+Math.PI/6),y2-h*Math.sin(a+Math.PI/6));ctx.closePath();ctx.fill()}
function strokeShape(ctx,start,p){ctx.save();ctx.strokeStyle=wb.color;ctx.fillStyle=wb.color;ctx.lineWidth=wb.size;ctx.lineCap='round';ctx.lineJoin='round';ctx.beginPath();if(wb.tool==='ln'){ctx.moveTo(start.x,start.y);ctx.lineTo(p.x,p.y)}else if(wb.tool==='rc')ctx.rect(start.x,start.y,p.x-start.x,p.y-start.y);else if(wb.tool==='ci')ctx.ellipse((start.x+p.x)/2,(start.y+p.y)/2,Math.abs(p.x-start.x)/2||1,Math.abs(p.y-start.y)/2||1,0,0,Math.PI*2);if(wb.tool==='ar')drawArrow(ctx,start.x,start.y,p.x,p.y);else ctx.stroke();ctx.restore()}
function normRect(a,b,c,d){const x=Math.max(0,Math.min(a,c)),y=Math.max(0,Math.min(b,d)),w=Math.min(dC.width,Math.max(a,c))-x,h=Math.min(dC.height,Math.max(b,d))-y;return{x:Math.round(x),y:Math.round(y),w:Math.round(w),h:Math.round(h)}}
function drawSelBox(r){clearUI();ux.save();ux.setLineDash([6,4]);ux.strokeStyle='#22c55e';ux.fillStyle='rgba(110,231,160,.08)';ux.fillRect(r.x,r.y,r.w,r.h);ux.strokeRect(r.x+.5,r.y+.5,Math.max(1,r.w-1),Math.max(1,r.h-1));ux.restore()}
function commitSel(){if(!wb.sel)return;dx.putImageData(wb.sel.img,Math.round(wb.sel.x),Math.round(wb.sel.y));wb.sel=null;clearUI();saveHist();wb.dirty=true;scheduleSave()}
function drawSel(){if(!wb.sel)return;clearUI();ux.putImageData(wb.sel.img,Math.round(wb.sel.x),Math.round(wb.sel.y));ux.save();ux.setLineDash([6,4]);ux.strokeStyle='#22c55e';ux.fillStyle='rgba(110,231,160,.08)';ux.fillRect(wb.sel.x,wb.sel.y,wb.sel.w,wb.sel.h);ux.strokeRect(wb.sel.x+.5,wb.sel.y+.5,Math.max(1,wb.sel.w-1),Math.max(1,wb.sel.h-1));ux.restore()}
function pointInSel(p){return wb.sel&&p.x>=wb.sel.x&&p.x<=wb.sel.x+wb.sel.w&&p.y>=wb.sel.y&&p.y<=wb.sel.y+wb.sel.h}
function wbDown(e){if(!wb.open||!wbAllowed())return; e.preventDefault(); const p=pos(e); if(wb.tool!=='sel'&&wb.sel)commitSel(); if(wb.tool==='pan'){wb.pan=true;wb.panLast=p;dC.style.cursor='grabbing';return} if(wb.tool==='tx'){const txt=prompt('متن:'); if(txt){saveHist();dx.save();dx.font=`700 ${Math.max(14,wb.size*7)}px Vazirmatn,Tahoma`;dx.fillStyle=wb.color;dx.direction=/[\u0600-\u06FF]/.test(txt)?'rtl':'ltr';dx.textAlign=dx.direction==='rtl'?'right':'left';txt.split(/\n/).forEach((line,i)=>dx.fillText(line,p.x,p.y+i*Math.max(18,wb.size*9)));dx.restore();wb.dirty=true;scheduleSave()}return} if(wb.tool==='sel'){if(pointInSel(p)){wb.moving=true;wb.moveOff={x:p.x-wb.sel.x,y:p.y-wb.sel.y};return} if(wb.sel)commitSel();wb.selecting=true;wb.start=p;return} saveHist();wb.drawing=true;wb.start=wb.last=p; if(['pen','hl','er'].includes(wb.tool)){dx.beginPath();dx.moveTo(p.x,p.y)}}
function wbMove(e){const p=pos(e); if(wb.pan){e.preventDefault();const dxo=p.x-wb.panLast.x,dyo=p.y-wb.panLast.y;wb.pdfOffset.x+=dxo;wb.pdfOffset.y+=dyo;wb.panLast=p;if(wb.pdfDoc)renderPdfPage(wb.pdfPage);return} if(wb.selecting){e.preventDefault();drawSelBox(normRect(wb.start.x,wb.start.y,p.x,p.y));return} if(wb.moving&&wb.sel){e.preventDefault();wb.sel.x=Math.max(0,Math.min(dC.width-wb.sel.w,p.x-wb.moveOff.x));wb.sel.y=Math.max(0,Math.min(dC.height-wb.sel.h,p.y-wb.moveOff.y));drawSel();return} if(!wb.drawing)return; e.preventDefault(); if(['pen','hl','er'].includes(wb.tool)){dx.save();dx.lineCap='round';dx.lineJoin='round';dx.lineWidth=wb.tool==='er'?wb.size*5:(wb.tool==='hl'?wb.size*5:wb.size);dx.strokeStyle=wb.tool==='er'?'#fff':wb.color;dx.globalAlpha=wb.tool==='hl'?.25:1;dx.globalCompositeOperation=wb.tool==='er'?'destination-out':'source-over';dx.lineTo(p.x,p.y);dx.stroke();dx.restore();dx.beginPath();dx.moveTo(p.x,p.y);wb.last=p;wb.dirty=true}else{clearUI();strokeShape(ux,wb.start,p)}}
function wbUp(e){const p=pos(e.changedTouches?e.changedTouches[0]:e); if(wb.pan){wb.pan=false;dC.style.cursor=wb.tool==='pan'?'grab':'crosshair';return} if(wb.selecting){wb.selecting=false;const r=normRect(wb.start.x,wb.start.y,p.x,p.y);clearUI();if(r.w>6&&r.h>6){const m=document.createElement('canvas');m.width=dC.width;m.height=dC.height;const mx=m.getContext('2d',{willReadFrequently:true});mx.fillStyle='#fff';mx.fillRect(0,0,m.width,m.height);mx.drawImage(pC,0,0);mx.drawImage(dC,0,0);wb.sel={...r,img:mx.getImageData(r.x,r.y,r.w,r.h)};px.fillStyle='#fff';px.fillRect(r.x,r.y,r.w,r.h);dx.clearRect(r.x,r.y,r.w,r.h);drawSel();toast('بخش انتخاب شد؛ برای جابه‌جایی بکشید','ok')}return} if(wb.moving){wb.moving=false;commitSel();return} if(!wb.drawing)return; if(['ln','rc','ci','ar'].includes(wb.tool)){clearUI();strokeShape(dx,wb.start,p);wb.dirty=true}wb.drawing=false; scheduleSave();}
function composite(){const c=document.createElement('canvas');c.width=dC.width;c.height=dC.height;const x=c.getContext('2d');x.fillStyle='#fff';x.fillRect(0,0,c.width,c.height);x.drawImage(pC,0,0);x.drawImage(dC,0,0);if(wb.sel){x.putImageData(wb.sel.img,Math.round(wb.sel.x),Math.round(wb.sel.y))}return c.toDataURL('image/webp',.84)}
let saveTimer=null;
function scheduleSave(){if(saveTimer)clearTimeout(saveTimer); saveTimer=setTimeout(saveBoard,250);}
async function saveBoard(){if(wb.selecting||wb.moving||wb.drawing)return; if(wb.sel)commitSel(); if(!wb.dirty||!wbAllowed())return; wb.dirty=false; $('#wbStatus').textContent='ذخیره...'; const r=await api('whiteboard_save',{session_id:R.sessionId,snapshot:JSON.stringify({type:'image',data:composite(),ts:Date.now()})}).catch(()=>null); if(r?.ok){wb.version=Math.max(wb.version,+r.version||0);$('#wbStatus').textContent='همگام'}else{$('#wbStatus').textContent='خطا';toast(r?.error||'ذخیره تخته انجام نشد','er')}}
async function loadBoard(force=false){if(wb.drawing||wb.dirty||wb.sel||wb.selecting||wb.moving)return; const r=await api('whiteboard_load',{session_id:R.sessionId}).catch(()=>null); if(!r?.ok||!r.snapshot||(!force&&+r.version<=wb.version))return; try{const o=JSON.parse(r.snapshot);const im=new Image();im.onload=()=>{px.fillStyle='#fff';px.fillRect(0,0,pC.width,pC.height);px.drawImage(im,0,0,pC.width,pC.height);dx.clearRect(0,0,dC.width,dC.height);wb.version=+r.version;wb.snapshot=o.data;$('#wbStatus').textContent='همگام';if(!wb.open)toggleWB(true);};im.src=o.data}catch(e){}}
async function loadPDF(file){if(!R.isHost){toast('افزودن PDF فقط برای مشاور فعال است','wn');return} try{const pdfjs=await import((R.assetBase||'assets/')+'js/vendor/pdf.min.mjs');pdfjs.GlobalWorkerOptions.workerSrc=(R.assetBase||'assets/')+'js/vendor/pdf.worker.min.mjs';wb.pdfDoc=await pdfjs.getDocument({data:await file.arrayBuffer(),useSystemFonts:true,disableFontFace:false}).promise;wb.pdfTotal=wb.pdfDoc.numPages;wb.pdfPage=1;wb.pdfZoom=1;wb.pdfOffset={x:0,y:0};$('#pdfNav').classList.add('on');await renderPdfPage(1);dx.clearRect(0,0,dC.width,dC.height);wb.dirty=true;scheduleSave();toast('PDF روی تخته قرار گرفت','ok')}catch(e){console.error(e);toast('PDF خوانده نشد','er')}}
async function renderPdfPage(n){if(!wb.pdfDoc)return;n=Math.max(1,Math.min(wb.pdfTotal,n));wb.pdfPage=n;const page=await wb.pdfDoc.getPage(n),vp0=page.getViewport({scale:1});const base=Math.min(pC.width/vp0.width,pC.height/vp0.height)*.94,scale=base*wb.pdfZoom;const vp=page.getViewport({scale});const off=document.createElement('canvas');off.width=Math.ceil(vp.width);off.height=Math.ceil(vp.height);await page.render({canvasContext:off.getContext('2d'),viewport:vp}).promise;px.fillStyle='#eceff3';px.fillRect(0,0,pC.width,pC.height);const x=(pC.width-off.width)/2+wb.pdfOffset.x,y=(pC.height-off.height)/2+wb.pdfOffset.y;px.fillStyle='#fff';px.fillRect(x,y,off.width,off.height);px.drawImage(off,x,y);$('#pgI').textContent=`${wb.pdfPage}/${wb.pdfTotal}`;$('#pdfZ').textContent=Math.round(wb.pdfZoom*100)+'%'}
function pdfGo(n){renderPdfPage(n);wb.dirty=true} function pdfZoom(m){wb.pdfZoom=Math.max(.3,Math.min(4,wb.pdfZoom*m));renderPdfPage(wb.pdfPage);wb.dirty=true} function pdfReset(){wb.pdfZoom=1;wb.pdfOffset={x:0,y:0};renderPdfPage(wb.pdfPage);wb.dirty=true} function pdfClose(){wb.pdfDoc=null;wb.pdfTotal=0;$('#pdfNav').classList.remove('on');px.fillStyle='#fff';px.fillRect(0,0,pC.width,pC.height);wb.dirty=true}
function downloadWB(){const a=document.createElement('a');a.download='madar-board.webp';a.href=composite();a.click()}

/* ---------- resize handles ---------- */
function setupResize(handle,onMove){let active=false,sx=0,sy=0,sw=0,sh=0;handle?.addEventListener('pointerdown',e=>{active=true;sx=e.clientX;sy=e.clientY;sw=$('#advC').getBoundingClientRect().width;sh=$('#sR').getBoundingClientRect().height;handle.setPointerCapture(e.pointerId);handle.classList.add('dr')});handle?.addEventListener('pointermove',e=>{if(active)onMove(e,{sx,sy,sw,sh})});['pointerup','pointercancel'].forEach(ev=>handle?.addEventListener(ev,()=>{active=false;handle.classList.remove('dr')}))}
function initResize(){setupResize($('#rH'),(e,o)=>{if(!$('#scrC').classList.contains('on'))return; const mv=$('#mV'), nw=Math.max(180,Math.min(mv.clientWidth-180,o.sw-(e.clientX-o.sx))); $('#advC').style.flex='none';$('#advC').style.width=(nw/mv.clientWidth*100)+'%'}); setupResize($('#rV'),(e,o)=>{const nh=Math.max(60,Math.min(250,o.sh+(o.sy-e.clientY))); $('#sR').style.height=nh+'px';$('#sR').style.minHeight=nh+'px'}); setupResize($('#wbRs'),(e)=>{if(innerWidth<=900)return; const w=Math.max(300,Math.min(innerWidth-10,innerWidth-e.clientX)); $('#wbPn').style.width=w+'px';resizeWB()})}

function bind(){
 $('#chatBtn').onclick=()=>openSB('ch'); $('#openChatBottom').onclick=()=>openSB('ch'); $('#peopleBtn').onclick=()=>openSB('pt'); $('#closeSide').onclick=closeSB; $('#tCh').onclick=()=>swTab('ch'); $('#tPt').onclick=()=>swTab('pt');
 $('#sendChat').onclick=sendChat; $('#chIn').addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendChat()}}); $('#chIn').addEventListener('input',function(){this.style.height='auto';this.style.height=Math.min(this.scrollHeight,70)+'px'});
 $('#micTB').onclick=toggleMic; $('#camTB').onclick=toggleCam; $('#scrTB').onclick=toggleScreen; $('#wbTB').onclick=()=>toggleWB(); $('#handTB').onclick=toggleHand; $('#muteAllTB')&&($('#muteAllTB').onclick=muteAll);
 $('#advC').ondblclick=()=>{if(pinned)restoreHost()}; $('#leaveTop').onclick=()=>showMdl('lvMdl'); $('#cancelLeave').onclick=()=>hideMdl('lvMdl'); $('#leaveOnly').onclick=()=>leave(false); $('#endForAll')&&($('#endForAll').onclick=()=>leave(true)); $('#cancelKick').onclick=()=>hideMdl('kMdl'); $('#doKick').onclick=doKick;
 document.addEventListener('click',e=>{const pr=e.target.closest('[data-promote]'); if(pr){promoteStudent(+pr.dataset.promote);return} const f=e.target.closest('[data-force]'); if(f&&R.isHost)forceUser(+f.dataset.user,f.dataset.force); const dec=e.target.closest('[data-dec]'); if(dec&&R.isHost){api('permission_decide',{session_id:R.sessionId,request_id:+dec.dataset.id,decision:dec.dataset.dec}).then(()=>pollPermHost())}});
 $('#wbOv').onclick=()=>toggleWB(false); $('#wbClose').onclick=()=>toggleWB(false);
 $$('#wbPn [data-tool]').forEach(b=>b.onclick=()=>{if(wb.sel)commitSel();$$('#wbPn [data-tool]').forEach(x=>x.classList.remove('on'));b.classList.add('on');wb.tool=b.dataset.tool;dC.style.cursor=wb.tool==='pan'?'grab':(wb.tool==='tx'?'text':(wb.tool==='er'?'cell':'crosshair'))});
 $$('#wbPn [data-color]').forEach(c=>c.onclick=()=>{$$('#wbPn [data-color]').forEach(x=>x.classList.remove('on'));c.classList.add('on');wb.color=c.dataset.color});
 $$('#wbPn [data-size]').forEach(c=>c.onclick=()=>{$$('#wbPn [data-size]').forEach(x=>x.classList.remove('on'));c.classList.add('on');wb.size=+c.dataset.size});
 dC.addEventListener('pointerdown',wbDown,{passive:false}); dC.addEventListener('pointermove',wbMove,{passive:false}); ['pointerup','pointercancel','pointerleave'].forEach(x=>dC.addEventListener(x,wbUp,{passive:false}));
 $('#wbClear').onclick=()=>{if(confirm('تخته پاک شود؟')){saveHist();px.fillStyle='#fff';px.fillRect(0,0,pC.width,pC.height);dx.clearRect(0,0,dC.width,dC.height);clearUI();wb.dirty=true}};
 $('#wbUndo').onclick=wbUndo; $('#wbRedo').onclick=wbRedo; $('#wbSizeMinus').onclick=()=>wbStep(-1); $('#wbSizePlus').onclick=()=>wbStep(1); $('#wbFullBtn').onclick=wbFull;
 $('#wbPdfBtn').onclick=()=>$('#pdfF').click(); $('#pdfF').onchange=e=>{const f=e.target.files[0]; if(f)loadPDF(f); e.target.value=''}; $('#wbDownload').onclick=downloadWB;
 $('#pdfPrev').onclick=()=>pdfGo(wb.pdfPage-1); $('#pdfNext').onclick=()=>pdfGo(wb.pdfPage+1); $('#pdfZoomOut').onclick=()=>pdfZoom(.85); $('#pdfZoomIn').onclick=()=>pdfZoom(1.18); $('#pdfReset').onclick=pdfReset; $('#pdfClose').onclick=pdfClose;
 window.addEventListener('resize',()=>{if(wb.open)resizeWB()});
 document.addEventListener('keydown',e=>{if(e.target.matches('input,textarea'))return; if(e.key==='m')toggleMic(); if(e.key==='v')toggleCam(); if(e.key==='w')toggleWB(); if(wb.open&&e.ctrlKey&&e.key.toLowerCase()==='z'){e.preventDefault();wbUndo()} if(wb.open&&e.ctrlKey&&e.key.toLowerCase()==='y'){e.preventDefault();wbRedo()} if(wb.open&&(e.key==='Delete'||e.key==='Backspace')&&wb.sel){e.preventDefault();wb.sel=null;clearUI();wb.dirty=true} if(e.key==='Escape'){if(wb.sel){wb.sel=null;clearUI();return} if(wb.open)toggleWB(false); else if($('#sb').classList.contains('open'))closeSB()}});
 initResize();
}

function tick(){const s=Math.floor((Date.now()-startTs)/1000); $('#tmr').textContent=String(Math.floor(s/3600)).padStart(2,'0')+':'+String(Math.floor(s%3600/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0')}
function init(){bind(); renderParticipants(); applyChatState(); resizeWB(); ensureLiveThenStart(); pollChat(); timers.push(setInterval(tick,1000),setInterval(pollChat,900),setInterval(loadBoard,400),setInterval(syncState,1500)); if(R.isHost)timers.push(setInterval(pollPermHost,1200),setInterval(pollHands,1800)); else timers.push(setInterval(pollPermStudent,1000)); toast('خوش آمدید 🎓','ok')}
window.addEventListener('beforeunload',()=>{try{p2p?.leave()}catch(e){}});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
