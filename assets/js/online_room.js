/* مَدار Online Room — Hybrid Jitsi + P2P + internal chat/whiteboard */
(function(){
'use strict';
const R=window.MADAR_ROOM||{}, CSRF=window.MADAR?.csrf||document.querySelector('meta[name="csrf-token"]')?.content||'';
const $=(s,root=document)=>root.querySelector(s), $$=(s,root=document)=>Array.from(root.querySelectorAll(s));
const api=(action,body={})=>fetch(`${R.apiBase}/online_room.php?action=${encodeURIComponent(action)}`,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(body)}).then(r=>r.json());
let jitsi=null,p2p=null,provider='none',chatLast=0,chatTimer=null,handTimer=null,reactionTimer=null,permTimer=null,permLast=0,wb=null,pdfDoc=null,pdfPage=1,startedAt=Date.now();
const EMO={clap:'👏',heart:'❤️',thumbs:'👍',fire:'🔥',star:'⭐',laugh:'😂',wow:'😮',sad:'😢'};
function toast(msg,ms=3800){const d=document.createElement('div');d.className='or-toast';d.textContent=msg;document.body.appendChild(d);setTimeout(()=>{d.style.opacity='0';d.style.transform='translateX(20px)';setTimeout(()=>d.remove(),250)},ms)}
function showRoom(){const l=$('#or-loading'),r=$('#or-room'); if(l) l.style.display='none'; if(r) r.style.display='grid'}
function setBtn(id,on,offClass=true){$$(`[id="${id}"]`).forEach(b=>{b.classList.toggle('active',!!on); if(offClass)b.classList.toggle('off',!on);});}
function bindAll(id,fn){$$(`[id="${id}"]`).forEach(el=>el.addEventListener('click',fn));}
function parseDomain(url){try{return new URL(url).hostname}catch(e){return 'meet.jit.si'}}
function loadScript(src,timeout=8500){return new Promise((res,rej)=>{if(window.JitsiMeetExternalAPI)return res();const s=document.createElement('script');let done=false;const t=setTimeout(()=>{if(!done){done=true;s.remove();rej(new Error('timeout'))}},timeout);s.src=src;s.async=true;s.onload=()=>{if(!done){done=true;clearTimeout(t);res()}};s.onerror=()=>{if(!done){done=true;clearTimeout(t);rej(new Error('load failed'))}};document.head.appendChild(s);});}
async function start(){
  document.documentElement.classList.add('online-room-ready');
  bindUi(); initChat(); initWhiteboard(); startTimers();
  if(R.isHost && R.status==='scheduled') await api('start_session',{session_id:R.sessionId}).catch(()=>{});
  if(!R.jitsiDisabled) await startJitsi().catch(async e=>{console.warn(e);toast('کلاس با مسیر داخلی مَدار آماده شد.'); await startP2P();}); else await startP2P();
  showRoom();
}
async function startJitsi(){
  provider='jitsi'; const host=$('#jitsi-host'); host.innerHTML='<div class="or-provider-pill">مسیر جایگزین کلاس</div>';
  await loadScript(`https://${parseDomain(R.jitsiServer)}/external_api.js`);
  const domain=parseDomain(R.jitsiServer), roomName=(R.jitsiRoom||('madar-'+R.sessionId)).replace(/[^a-zA-Z0-9_-]/g,'');
  const toolbar=['fullscreen','tileview','settings','hangup'];
  if(R.permissions.mic||R.isHost) toolbar.unshift('microphone');
  if(R.permissions.cam||R.isHost) toolbar.unshift('camera');
  if(R.permissions.screen||R.isHost) toolbar.unshift('desktop');
  jitsi=new JitsiMeetExternalAPI(domain,{roomName,parentNode:host,width:'100%',height:'100%',lang:'fa',userInfo:{displayName:R.displayName||R.userName},configOverwrite:{prejoinPageEnabled:false,disableDeepLinking:true,startWithAudioMuted:!R.isHost,startWithVideoMuted:!R.isHost,p2p:{enabled:true},channelLastN:6,enableNoAudioDetection:false,enableNoisyMicDetection:false,toolbarButtons:toolbar},interfaceConfigOverwrite:{MOBILE_APP_PROMO:false,SHOW_JITSI_WATERMARK:false,SHOW_BRAND_WATERMARK:false,DEFAULT_BACKGROUND:'#050807'}});
  jitsi.addEventListener('videoConferenceJoined',()=>{toast('به کلاس آنلاین وصل شدید.'); setBtn('or-mic-btn',R.isHost); setBtn('or-cam-btn',R.isHost);});
  jitsi.addEventListener('readyToClose',()=>leaveRoom());
  setTimeout(()=>{ if(provider==='jitsi') showRoom(); },700);
}
async function startP2P(){
  provider='p2p'; if(!navigator.mediaDevices||!window.RTCPeerConnection){ $('#jitsi-host').innerHTML='<div class="or-waiting"><div class="or-waiting-card"><h2>مرورگر پشتیبانی نمی‌شود</h2><p>برای کلاس آنلاین از Chrome یا Edge به‌روز استفاده کنید.</p></div></div>'; return; }
  p2p=await new window.MadarP2P(R,{toast,onReady:()=>toast('کلاس آماده است.')}).start($('#jitsi-host'));
  setBtn('or-mic-btn',p2p.micOn); setBtn('or-cam-btn',p2p.camOn);
}
function bindUi(){
  bindAll('or-mode-btn',async()=>{ if(provider==='jitsi'){jitsi?.dispose();jitsi=null;toast('مسیر داخلی کلاس فعال شد.'); await startP2P();} else {await p2p?.leave();p2p=null;toast('در حال بررسی مسیر جایگزین کلاس...'); await startJitsi().catch(()=>toast('مسیر جایگزین در دسترس نیست.'));} });
  bindAll('or-mic-btn',async()=>{ if(!R.permissions.mic&&!R.isHost)return requestPermission('mic','میکروفون'); if(provider==='jitsi'){jitsi?.executeCommand('toggleAudio');} else if(p2p){setBtn('or-mic-btn',await p2p.toggleMic());} });
  bindAll('or-cam-btn',async()=>{ if(!R.permissions.cam&&!R.isHost)return requestPermission('cam','دوربین'); if(provider==='jitsi'){jitsi?.executeCommand('toggleVideo');} else if(p2p){setBtn('or-cam-btn',await p2p.toggleCam());} });
  bindAll('or-screen-btn',async()=>{ if(!R.permissions.screen&&!R.isHost) return requestPermission('screen','اشتراک صفحه'); if(provider==='jitsi'){jitsi?.executeCommand('toggleShareScreen');} else if(p2p){setBtn('or-screen-btn',await p2p.toggleScreen(),false);} });
  bindAll('or-board-btn',()=>toggleBoard()); bindAll('or-settings-btn',()=>openSettings()); $('#or-settings-close')?.addEventListener('click',()=>closeSettings()); bindAll('or-chat-btn',()=>openSide('chat')); bindAll('or-people-btn',()=>openSide('people'));
  bindAll('or-side-toggle',()=>{ $('#or-side')?.classList.toggle('is-hidden'); $('#or-room')?.classList.toggle('side-collapsed'); });
  bindAll('or-end-btn',leaveRoom);
  bindAll('or-hand-btn',async()=>{const r=await api('hand_toggle',{session_id:R.sessionId}).catch(()=>null); if(r?.ok){setBtn('or-hand-btn',r.raised,false);toast(r.raised?'دست شما بالا رفت.':'دست شما پایین آمد.');} else toast(r?.error||'امکان ثبت دست وجود ندارد.');});
  bindAll('or-react-btn',()=>$('#or-quick-react')?.classList.toggle('active'));
  $$('#or-quick-react button').forEach(b=>b.addEventListener('click',async()=>{const type=b.dataset.react; floatReact(type); $('#or-quick-react')?.classList.remove('active'); await api('reaction_send',{session_id:R.sessionId,reaction_type:type}).catch(()=>{});}));
  $$('.or-side-tab').forEach(t=>t.addEventListener('click',()=>openSide(t.dataset.tab)));
  $('.wb-exit')?.addEventListener('click',()=>toggleBoard(false));
}
function openSide(tab){$('#or-side')?.classList.remove('is-hidden');$('#or-room')?.classList.remove('side-collapsed');$$('.or-side-tab').forEach(x=>x.classList.toggle('active',x.dataset.tab===tab));$$('.or-tab-content').forEach(c=>c.style.display=c.dataset.tabContent===tab?(tab==='chat'||tab==='people'?'flex':'block'):'none');}
async function leaveRoom(){ if(R.isHost && confirm('جلسه برای همه پایان داده شود؟')) await api('end_session',{session_id:R.sessionId}).catch(()=>{}); try{jitsi?.dispose()}catch(e){} await p2p?.leave().catch(()=>{}); location.href=(R.userRole==='student'?'student/online_sessions.php':'admin/online_sessions.php'); }
function startTimers(){setInterval(()=>{const s=Math.floor((Date.now()-startedAt)/1000),m=Math.floor(s/60),hh=Math.floor(m/60); $('#or-timer-text')&&( $('#or-timer-text').textContent=(hh?String(hh).padStart(2,'0')+':':'')+String(m%60).padStart(2,'0')+':'+String(s%60).padStart(2,'0'));},1000);}
function initChat(){const input=$('#or-chat-input'),send=$('#or-chat-send'); const sendFn=async()=>{const msg=input.value.trim(); if(!msg)return; input.value=''; const r=await api('chat_send',{session_id:R.sessionId,message:msg}); if(!r.ok) toast(r.error||'ارسال پیام ناموفق بود'); else pollChat();}; send?.addEventListener('click',sendFn); input?.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendFn();}}); pollChat(); chatTimer=setInterval(pollChat,2200); if(R.isHost){pollHands();handTimer=setInterval(pollHands,2500);} pollReactions(); reactionTimer=setInterval(pollReactions,2600); initPermissions();}
async function pollChat(){const r=await api('chat_list',{session_id:R.sessionId,after_id:chatLast}).catch(()=>null); if(!r?.ok)return; for(const m of r.messages||[]) addMsg(m);}
function addMsg(m){if(+m.id<=chatLast)return; chatLast=Math.max(chatLast,+m.id); const list=$('#or-chat-list'); if(!list)return; const mine=+m.user_id===+R.userId; const div=document.createElement('div'); div.className='or-msg '+(mine?'mine':''); div.innerHTML=`<div class="or-msg-ava">${esc((m.user_name||'?').slice(0,2))}</div><div class="or-msg-bubble"><div class="or-msg-meta"><span>${esc(m.user_name||'کاربر')}</span><span>${esc((m.created_at||'').slice(11,16))}</span></div><div class="or-msg-text">${esc(m.message||'')}</div></div>`; list.appendChild(div); list.scrollTop=list.scrollHeight;}
async function pollHands(){const r=await api('hand_list',{session_id:R.sessionId}).catch(()=>null); if(!r?.ok)return; const hands=r.hands||[]; if(hands.length){const b=$('#or-hand-banner'); $('#or-hand-banner-text').textContent='✋ '+hands.map(h=>h.user_name).join('، '); b?.classList.add('active');} }
$('#or-hand-banner-close')?.addEventListener('click',()=>$('#or-hand-banner')?.classList.remove('active'));
async function pollReactions(){const r=await api('reactions_list',{session_id:R.sessionId}).catch(()=>null); if(!r?.ok)return; (r.reactions||[]).forEach(x=>{ if(!window._seenReacts)window._seenReacts=new Set(); if(!window._seenReacts.has(x.id)){window._seenReacts.add(x.id); if(+x.user_id!==+R.userId)floatReact(x.reaction_type);} });}
function floatReact(type){const wrap=$('#or-reactions'); if(!wrap)return; const d=document.createElement('div'); d.className='float-react'; d.textContent=EMO[type]||'👏'; d.style.left=(15+Math.random()*70)+'%'; wrap.appendChild(d); setTimeout(()=>d.remove(),2400);}
function toggleBoard(force){if(!R.permissions.whiteboard&&!R.isHost)return requestPermission('whiteboard','تخته'); const host=$('#or-whiteboard-host'); const show=force===undefined?!host.classList.contains('active'):force; host.classList.toggle('active',show); if(show){openSide('whiteboard'); wb?.resize();}}

function permLabel(t){return {mic:'میکروفون',cam:'دوربین',screen:'اشتراک صفحه',whiteboard:'تخته'}[t]||'دسترسی';}
async function requestPermission(type,label){
  if(R.isHost) return;
  const r=await api('permission_request',{session_id:R.sessionId,permission_type:type}).catch(()=>null);
  toast(r?.ok ? `درخواست ${label} برای مشاور ارسال شد.` : (r?.error||'درخواست ارسال نشد.'));
}
function initPermissions(){
  if(R.isHost){ pollPermissionRequests(); permTimer=setInterval(pollPermissionRequests,2400); $$('#or-settings-modal [data-perm]').forEach(inp=>inp.addEventListener('change',saveClassPermissions)); }
  else { pollPermissionStatus(); permTimer=setInterval(pollPermissionStatus,2600); setInterval(syncClassPermissions,5000); }
}
function openSettings(){ if(!R.isHost)return; $('#or-settings-modal')?.classList.add('active'); pollPermissionRequests(); }
function closeSettings(){ $('#or-settings-modal')?.classList.remove('active'); }
async function saveClassPermissions(){
  const body={session_id:R.sessionId}; $$('#or-settings-modal [data-perm]').forEach(i=>body[i.dataset.perm]=i.checked?1:0);
  const r=await api('update_permissions',body).catch(()=>null); if(r?.ok){ Object.assign(R.permissions,{mic:!!body.mic,cam:!!body.cam,screen:!!body.screen,whiteboard:!!body.whiteboard,chat:!!body.chat}); toast('تنظیمات کلاس ذخیره شد.'); } else toast('ذخیره تنظیمات انجام نشد.');
}
async function pollPermissionRequests(){
  const box=$('#or-permission-requests'); if(!box)return; const r=await api('permission_list',{session_id:R.sessionId}).catch(()=>null); if(!r?.ok)return;
  const arr=r.requests||[]; if(!arr.length){box.innerHTML='<div style="color:#90a199;font-size:.82rem;padding:10px">درخواست فعالی وجود ندارد.</div>';return;}
  box.innerHTML=arr.map(x=>`<div class="or-request" data-id="${x.id}"><div><div class="name">${esc(x.user_name)}</div><div class="type">درخواست ${permLabel(x.permission_type)}</div></div><div class="or-request-actions"><button class="or-mini-btn ok" data-dec="approved">اجازه</button><button class="or-mini-btn no" data-dec="denied">رد</button></div></div>`).join('');
  box.querySelectorAll('button[data-dec]').forEach(b=>b.onclick=async()=>{const id=b.closest('.or-request').dataset.id; await api('permission_decide',{session_id:R.sessionId,request_id:id,decision:b.dataset.dec}); pollPermissionRequests(); toast(b.dataset.dec==='approved'?'دسترسی داده شد.':'درخواست رد شد.');});
}

async function syncClassPermissions(){
  const r=await api('permissions_state',{session_id:R.sessionId}).catch(()=>null); if(!r?.ok||!r.permissions)return;
  Object.assign(R.permissions,r.permissions);
  const inp=$('#or-chat-input'), btn=$('#or-chat-send'); if(inp&&btn){ const enabled=!!R.permissions.chat; inp.disabled=!enabled; btn.disabled=!enabled; if(!enabled) inp.placeholder='چت با اجازه مشاور فعال می‌شود'; else inp.placeholder='پیام آموزشی بنویسید...'; }
}

async function pollPermissionStatus(){
  const r=await api('permission_status',{session_id:R.sessionId,after_id:permLast}).catch(()=>null); if(!r?.ok)return;
  for(const x of r.requests||[]){permLast=Math.max(permLast,+x.id); if(x.status==='approved'){R.permissions[x.permission_type]=true; toast(`مشاور اجازه ${permLabel(x.permission_type)} را داد.`);} else toast(`درخواست ${permLabel(x.permission_type)} رد شد.`);}
}

function initWhiteboard(){const canvas=$('#wb-canvas'); if(!canvas)return; const ctx=canvas.getContext('2d'); wb={tool:'pen',color:'#000',size:4,drawing:false,last:null,dirty:false,version:0, resize(){const r=canvas.parentElement.getBoundingClientRect(),img=canvas.toDataURL(); canvas.width=Math.max(300,r.width*devicePixelRatio); canvas.height=Math.max(200,r.height*devicePixelRatio); ctx.setTransform(devicePixelRatio,0,0,devicePixelRatio,0,0); ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height); const im=new Image(); im.onload=()=>ctx.drawImage(im,0,0,r.width,r.height); im.src=img;}}; const pos=e=>{const r=canvas.getBoundingClientRect(),p=e.touches?e.touches[0]:e;return{x:p.clientX-r.left,y:p.clientY-r.top}}; const down=e=>{e.preventDefault();wb.drawing=true;wb.start=wb.last=pos(e)}; const move=e=>{if(!wb.drawing)return;e.preventDefault();const p=pos(e);ctx.lineCap='round';ctx.lineJoin='round';ctx.lineWidth=wb.size;ctx.strokeStyle=wb.tool==='eraser'?'#fff':wb.color;if(['pen','eraser'].includes(wb.tool)){ctx.beginPath();ctx.moveTo(wb.last.x,wb.last.y);ctx.lineTo(p.x,p.y);ctx.stroke();wb.last=p;wb.dirty=true;}}; const up=e=>{if(!wb.drawing)return;const p=pos(e.changedTouches?e.changedTouches[0]:e); if(['line','rect','circle'].includes(wb.tool)){ctx.strokeStyle=wb.color;ctx.lineWidth=wb.size;ctx.beginPath(); if(wb.tool==='line'){ctx.moveTo(wb.start.x,wb.start.y);ctx.lineTo(p.x,p.y);} if(wb.tool==='rect'){ctx.rect(wb.start.x,wb.start.y,p.x-wb.start.x,p.y-wb.start.y);} if(wb.tool==='circle'){ctx.ellipse((wb.start.x+p.x)/2,(wb.start.y+p.y)/2,Math.abs(p.x-wb.start.x)/2,Math.abs(p.y-wb.start.y)/2,0,0,Math.PI*2);} ctx.stroke();wb.dirty=true;} wb.drawing=false;}; ['mousedown','touchstart'].forEach(x=>canvas.addEventListener(x,down,{passive:false})); ['mousemove','touchmove'].forEach(x=>canvas.addEventListener(x,move,{passive:false})); ['mouseup','mouseleave','touchend'].forEach(x=>canvas.addEventListener(x,up)); $$('.wb-tool[data-tool]').forEach(b=>b.onclick=()=>{$$('.wb-tool[data-tool]').forEach(x=>x.classList.remove('active'));b.classList.add('active');wb.tool=b.dataset.tool;}); $('.wb-tool[data-tool="pen"]')?.classList.add('active'); $$('.wb-color').forEach(c=>c.onclick=()=>{$$('.wb-color').forEach(x=>x.classList.remove('active'));c.classList.add('active');wb.color=c.dataset.color;}); $('#wb-size').oninput=e=>wb.size=+e.target.value; $('[data-clear]')?.addEventListener('click',()=>{if(confirm('تخته پاک شود؟')){ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);wb.dirty=true;}}); $('#wb-pdf-btn')?.addEventListener('click',()=>$('#wb-pdf-input')?.click()); $('#wb-pdf-input')?.addEventListener('change',loadPdfToBoard); $('#wb-pdf-prev')?.addEventListener('click',()=>renderPdfPage(pdfPage-1)); $('#wb-pdf-next')?.addEventListener('click',()=>renderPdfPage(pdfPage+1)); $('#wb-download')?.addEventListener('click',downloadBoardPdf); window.addEventListener('resize',()=>wb.resize()); setTimeout(()=>wb.resize(),400); setInterval(saveBoard,1800); setInterval(loadBoard,2600); loadBoard();}

async function loadPdfToBoard(e){
  const file=e.target.files?.[0]; if(!file)return; if(!R.isHost){toast('افزودن فایل فقط برای مشاور فعال است.');return;}
  try{
    toast('در حال آماده‌سازی فایل درس...');
    const pdfjs=await import((R.assetBase||'assets/')+'js/vendor/pdf.min.mjs');
    pdfjs.GlobalWorkerOptions.workerSrc=(R.assetBase||'assets/')+'js/vendor/pdf.worker.min.mjs';
    const buf=await file.arrayBuffer(); pdfDoc=await pdfjs.getDocument({data:buf}).promise; pdfPage=1;
    ['#wb-pdf-prev','#wb-pdf-next','#wb-pdf-page'].forEach(sel=>$(sel)&&($(sel).style.display=''));
    await renderPdfPage(1); toast('PDF روی تخته قرار گرفت.');
  }catch(err){console.error(err);toast('فایل PDF خوانده نشد.');}
}
async function renderPdfPage(n){
  if(!pdfDoc||!wb)return; n=Math.max(1,Math.min(pdfDoc.numPages,n)); pdfPage=n; const page=await pdfDoc.getPage(n);
  const canvas=$('#wb-canvas'), ctx=canvas.getContext('2d'), host=canvas.parentElement.getBoundingClientRect();
  const vp0=page.getViewport({scale:1}); const scale=Math.min(host.width/vp0.width, host.height/vp0.height)*devicePixelRatio; const vp=page.getViewport({scale});
  const off=document.createElement('canvas'); off.width=vp.width; off.height=vp.height; await page.render({canvasContext:off.getContext('2d'),viewport:vp}).promise;
  ctx.fillStyle='#f3f4f6'; ctx.fillRect(0,0,canvas.width,canvas.height);
  const dw=off.width/devicePixelRatio, dh=off.height/devicePixelRatio, x=(canvas.clientWidth-dw)/2, y=(canvas.clientHeight-dh)/2;
  ctx.drawImage(off,x,y,dw,dh); wb.dirty=true; $('#wb-pdf-page')&&($('#wb-pdf-page').textContent=`${n} / ${pdfDoc.numPages}`);
}
function downloadBoardPdf(){
  const c=$('#wb-canvas'); if(!c)return;
  const data=c.toDataURL('image/jpeg',0.88).split(',')[1], bin=atob(data), imgLen=bin.length;
  const w=Math.round(c.clientWidth||1000), h=Math.round(c.clientHeight||700);
  const enc=x=>new TextEncoder().encode(x), parts=[]; let pos=0; const xref=[0];
  const add=x=>{const b=typeof x==='string'?enc(x):x; parts.push(b); pos+=b.length;};
  const obj=(id,body)=>{xref[id]=pos; add(`${id} 0 obj\n${body}\nendobj\n`);};
  add('%PDF-1.4\n');
  obj(1,'<< /Type /Catalog /Pages 2 0 R >>');
  obj(2,'<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
  obj(3,`<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${w} ${h}] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>`);
  xref[4]=pos;
  add(`4 0 obj\n<< /Type /XObject /Subtype /Image /Width ${c.width} /Height ${c.height} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${imgLen} >>\nstream\n`);
  const bytes=new Uint8Array(imgLen); for(let i=0;i<imgLen;i++)bytes[i]=bin.charCodeAt(i); add(bytes);
  add('\nendstream\nendobj\n');
  const content=`q ${w} 0 0 ${h} 0 0 cm /Im0 Do Q`;
  obj(5,`<< /Length ${content.length} >>\nstream\n${content}\nendstream`);
  const start=pos; add('xref\n0 6\n0000000000 65535 f \n');
  for(let i=1;i<=5;i++) add(String(xref[i]).padStart(10,'0')+' 00000 n \n');
  add(`trailer << /Root 1 0 R /Size 6 >>\nstartxref\n${start}\n%%EOF`);
  const blob=new Blob(parts,{type:'application/pdf'}), a=document.createElement('a');
  a.href=URL.createObjectURL(blob); a.download='madaar-class-board.pdf'; a.click(); setTimeout(()=>URL.revokeObjectURL(a.href),1000);
}

async function saveBoard(){if(!wb?.dirty)return; wb.dirty=false; $('#wb-status-text')&&( $('#wb-status-text').textContent='در حال ذخیره...'); const data=$('#wb-canvas').toDataURL('image/webp',.78); const r=await api('whiteboard_save',{session_id:R.sessionId,snapshot:JSON.stringify({type:'image',data,ts:Date.now()})}).catch(()=>null); if(r?.ok){wb.version=Math.max(wb.version,+r.version||0); $('#wb-status-text')&&( $('#wb-status-text').textContent='همگام‌شده');} }
async function loadBoard(){if(!wb||wb.dirty)return; const r=await api('whiteboard_load',{session_id:R.sessionId}).catch(()=>null); if(!r?.ok||!r.snapshot||+r.version<=wb.version)return; try{const obj=JSON.parse(r.snapshot); const im=new Image(); im.onload=()=>{const c=$('#wb-canvas'),ctx=c.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,c.width,c.height);ctx.drawImage(im,0,0,c.clientWidth,c.clientHeight);wb.version=+r.version;}; im.src=obj.data;}catch(e){} }
function esc(s){return String(s).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
window.addEventListener('beforeunload',()=>{try{p2p?.leave()}catch(e){}});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
})();
