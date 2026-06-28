(function(){
'use strict';
const R=window.MADAR_ROOM||{},CSRF=window.MADAR?.csrf||document.querySelector('meta[name="csrf-token"]')?.content||'';
const $=(s,r=document)=>r.querySelector(s),$$=(s,r=document)=>Array.from(r.querySelectorAll(s));
const api=(action,body={})=>fetch(`${R.apiBase}/online_room.php?action=${encodeURIComponent(action)}`,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(body)}).then(r=>r.json());
const S={chatLast:0,notif:0,participants:new Map(),provider:null,p2p:null,jitsi:null,screenOwner:null,hand:false,wbOpen:false,wbVersion:0,permLast:0,seenReactions:new Set()};
function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}
function initials(name){const p=String(name||'?').trim().split(/\s+/);return ((p[0]?.[0]||'؟')+(p[1]?.[0]||'')).trim()||'؟'}
function toast(msg,type='in'){const ic={ok:'✔',wn:'⚠',er:'✕',in:'i'};const c=$('#tts'),d=document.createElement('div');d.className='tt';d.innerHTML=`<div class="ti t${type==='ok'?'ok':type==='wn'?'wn':type==='er'?'er':'in'}"><span>${ic[type]}</span></div><span class="tm">${esc(msg)}</span>`;c.appendChild(d);setTimeout(()=>d.remove(),2800)}
function fmt(sec){const h=Math.floor(sec/3600),m=Math.floor(sec%3600/60),s=sec%60;return`${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`}
let started=Date.now();
setInterval(()=>{const t=$('#tmr');if(t)t.textContent=fmt(Math.floor((Date.now()-started)/1000));},1000);
function openSB(t){$('#sb').classList.add('open');switchTab(t);if(t==='ch')clearChatBadge()}
function closeSB(){$('#sb').classList.remove('open')}
function switchTab(t){$('#tCh').classList.toggle('on',t==='ch');$('#tPt').classList.toggle('on',t==='pt');$('#chPn').classList.toggle('on',t==='ch');$('#ptPn').classList.toggle('on',t==='pt')}
window.openSB=openSB;window.closeSB=closeSB;window.swTab=switchTab;
function clearChatBadge(){S.notif=0;$('#cBdg').classList.remove('on')}
function chatBadge(){if($('#chPn').classList.contains('on') && $('#sb').classList.contains('open')) return;S.notif++;const b=$('#cBdg');b.textContent=S.notif;b.classList.add('on')}
function addChat(m){const mine=+m.user_id===+R.userId;const role=(m.user_role==='advisor'||m.user_role==='admin')?'a':'s';const ac=role==='a'?'a':(['s1','s2','s3'][Math.abs((m.user_name||'').length)%3]);const div=document.createElement('div');div.className='mg';div.innerHTML=`<div class="mg-a ${ac}">${esc(initials(m.user_name||'کاربر'))}</div><div class="mg-w"><div class="mg-mt"><span class="mg-nm">${esc(m.user_name||'کاربر')}</span><span class="mg-rl ${role}">${role==='a'?'مشاور':'دانش‌آموز'}</span><span class="mg-tm">${esc(String(m.created_at||'').slice(11,16))}</span></div><div class="mg-bb ${mine?'adv':'stu'}">${esc(m.message||'')}</div></div>`;$('#chMs').appendChild(div);$('#chMs').scrollTop=$('#chMs').scrollHeight;if(!mine)chatBadge()}
async function pollChat(){const r=await api('chat_list',{session_id:R.sessionId,after_id:S.chatLast}).catch(()=>null);if(!r?.ok){if(r)console.error('chat_list_failed',r);return;}for(const m of(r.messages||[])){if(+m.id<=S.chatLast)continue;S.chatLast=Math.max(S.chatLast,+m.id);addChat(m)}}
async function sendChat(){const i=$('#chIn'),text=i.value.trim();if(!text)return;const r=await api('chat_send',{session_id:R.sessionId,message:text}).catch(()=>null);if(r?.ok){i.value='';i.style.height='auto';pollChat()}else{console.error('chat_send_failed',r);toast(r?.error||'ارسال پیام انجام نشد','er')}}
window.snd=sendChat;
function personGrad(i){return['linear-gradient(135deg,var(--blu),#1565c0)','linear-gradient(135deg,#ef5350,#c62828)','linear-gradient(135deg,var(--org),#f57c00)','linear-gradient(135deg,var(--pur),#6a1b9a)'][i%4]}
function participantName(p){return p.name||p.full_name||'دانش‌آموز'}
function setMainPlaceholder(label,isHost){const box=$('#advC .vbg');box.innerHTML=`<div class="vav speaking" style="background:${isHost?'linear-gradient(135deg,var(--g1),var(--g3))':'linear-gradient(135deg,var(--blu),#1565c0)'}">${esc(initials(label))}<div class="sp-rng"></div></div><span class="vav-nm">${esc(label)}</span>`;$('#advC .vlbl').innerHTML=`${isHost?'<span class="vrl adv">مشاور</span>':''}${esc(label)}`}
function attachVideo(container,stream,muted=false,contain=false){container.innerHTML='';const wrap=document.createElement('div');wrap.className='video-shell'+(contain?' screen':'');const v=document.createElement('video');v.autoplay=true;v.playsInline=true;v.muted=muted;v.srcObject=stream;wrap.appendChild(v);container.appendChild(wrap)}
function renderMainStage(){if(S.p2p?.screenStream){$('#scrC').classList.add('on');$('#rH').classList.add('vis');attachVideo($('#scrC .vbg'),S.p2p.screenStream,true,true);$('#advC').style.width='';attachVideo($('#advC .vbg'),S.p2p.localStream,true,false);$('#advC .vlbl').innerHTML=`<span class="vrl adv">${R.isHost?'مشاور':'شما'}</span>${esc(R.userName+' (شما)')}`;return}
 if(S.screenOwner){const p=S.participants.get(S.screenOwner);if(p?.stream){$('#scrC').classList.add('on');$('#rH').classList.add('vis');attachVideo($('#scrC .vbg'),p.stream,false,true);$('#advC .vlbl').innerHTML=R.isHost?`<span class="vrl adv">مشاور</span>${esc(R.hostName||R.userName)}`:esc(R.userName+' (شما)'); if(S.p2p?.localStream) attachVideo($('#advC .vbg'),S.p2p.localStream,true,false); else setMainPlaceholder(R.hostName||'مشاور',true); return}}
 $('#scrC').classList.remove('on');$('#rH').classList.remove('vis');
 if(S.p2p?.localStream){attachVideo($('#advC .vbg'),S.p2p.localStream,true,false);$('#advC .vlbl').innerHTML=`${R.isHost?'<span class="vrl adv">مشاور</span>':''}${esc(R.userName+' (شما)')}`;} else setMainPlaceholder(R.hostName||'مشاور',true)}
function renderParticipants(){const pt=$('#ptPn .pt-l'),sr=$('#sR');pt.innerHTML='';sr.innerHTML='';const hostName=R.hostName||'مشاور';const top=document.createElement('div');top.className='pt-i';top.innerHTML=`<div class="pt-av" style="background:linear-gradient(135deg,var(--g1),var(--g3))">${esc(initials(hostName))}<div class="od"></div></div><div class="pt-inf"><div class="pt-nm">${esc(hostName)}</div><div class="pt-rl adv">مشاور</div></div>`;pt.appendChild(top);const arr=[...S.participants.entries()];$('#cnt').textContent=`${arr.length+1} نفر`;S.screenOwner=null;
 arr.forEach(([key,p],idx)=>{if(p.screen_on)S.screenOwner=key;const grad=personGrad(idx);const acts=R.isHost?`<div class="pt-acts"><button class="pa pm" data-user="${key}" data-cmd="mic_off">🎙</button><button class="pa pc" data-user="${key}" data-cmd="cam_off">📷</button><button class="pa pk" data-user="${key}" data-cmd="kick">✕</button></div>`:'';const row=document.createElement('div');row.className='pt-i';row.innerHTML=`<div class="pt-av" style="background:${grad}">${esc(initials(participantName(p)))}<div class="od"></div></div><div class="pt-inf"><div class="pt-nm">${esc(participantName(p))}</div><div class="pt-rl">دانش‌آموز</div></div>${acts}`;pt.appendChild(row);
 const sc=document.createElement('div');sc.className='sc';sc.dataset.id=key;sc.innerHTML=`<div class="ico-m ${p.mic_on===0?'on':''}" id="m${key}">🎙</div><div class="ico-h ${p.hand_raised?'on':''}" id="h${key}">✋</div><div class="vbg"></div><div class="vbot"><div class="vlbl">${esc(participantName(p))}</div></div>${R.isHost?`<div class="sc-ct"><button class="scb sm" data-user="${key}" data-cmd="mic_off">🎙</button><button class="scb scc" data-user="${key}" data-cmd="cam_off">📷</button><button class="scb sk" data-user="${key}" data-cmd="kick">✕</button></div>`:''}`;sr.appendChild(sc);if(p.stream && !p.screen_on)attachVideo(sc.querySelector('.vbg'),p.stream,false,false);else sc.querySelector('.vbg').innerHTML=`<div class="vav" style="background:${grad}">${esc(initials(participantName(p)))}</div>`;});
 renderMainStage(); bindCommandButtons();}
function bindCommandButtons(){$$('[data-user][data-cmd]').forEach(b=>b.onclick=()=>sendCommand(b.dataset.user,b.dataset.cmd))}
async function sendCommand(key,cmd){const p=S.participants.get(key);if(!p)return;const r=await fetch(`${R.apiBase}/online_p2p.php?action=command&room_id=${R.sessionId}`,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({room_id:R.sessionId,target_user_id:p.user_id,command:cmd})}).then(x=>x.json()).catch(()=>null);toast(r?.ok?'دستور ارسال شد':'ارسال دستور انجام نشد',r?.ok?'ok':'er')}
async function toggleHand(){const r=await api('hand_toggle',{session_id:R.sessionId}).catch(()=>null);if(r?.ok){S.hand=!!r.raised;$('#handTB').className=S.hand?'tb on':'tb';toast(S.hand?'دست شما بالا رفت':'دست شما پایین آمد','in')}}
async function pollHands(){if(!R.isHost)return;const r=await api('hand_list',{session_id:R.sessionId}).catch(()=>null);if(!r?.ok)return;for(const [k,p] of S.participants.entries())p.hand_raised=false;for(const h of(r.hands||[])){for(const [k,p] of S.participants.entries())if(+p.user_id===+h.user_id)p.hand_raised=true}renderParticipants()}
async function pollReactions(){const r=await api('reactions_list',{session_id:R.sessionId}).catch(()=>null);if(!r?.ok)return;(r.reactions||[]).forEach(x=>{if(S.seenReactions.has(x.id))return;S.seenReactions.add(x.id);toast(`${x.user_name||''} ${({'clap':'👏','heart':'❤️','thumbs':'👍','fire':'🔥','star':'⭐','laugh':'😂','wow':'😮','sad':'😢'})[x.reaction_type]||'👏'}`,'ok')})}
async function requestPermission(type,label){const r=await api('permission_request',{session_id:R.sessionId,permission_type:type}).catch(()=>null);toast(r?.ok?`درخواست ${label} ارسال شد`:r?.error||'ارسال درخواست انجام نشد',r?.ok?'ok':'er')}
async function leaveRoom(){if(R.isHost){const end=confirm('برای همه پایان داده شود؟');if(end)await api('end_session',{session_id:R.sessionId}).catch(()=>{})}try{S.jitsi?.dispose()}catch(e){}await S.p2p?.leave?.().catch(()=>{});location.href=(R.userRole==='student'?'student/online_sessions.php':'admin/online_sessions.php')}
function bindUi(){switchTab('ch');$('#leaveBtn').onclick=()=>$('#lvMdl').classList.add('on');$('#leaveBtn2').onclick=()=>$('#lvMdl').classList.add('on');$('#lvLeave').onclick=leaveRoom;$('#peopleOpen').onclick=()=>openSB('pt');$('#chatOpen').onclick=()=>openSB('ch');$('#chatOpen2').onclick=()=>openSB('ch');$('#chIn').addEventListener('input',function(){this.style.height='auto';this.style.height=Math.min(this.scrollHeight,70)+'px'});$('#chIn').addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendChat()}});$('#micTB').onclick=async()=>{if(!R.permissions.mic&&!R.isHost)return requestPermission('mic','میکروفون');if(S.provider==='p2p'&&S.p2p){const on=await S.p2p.toggleMic();$('#micTB').className=on?'tb on':'tb off';$('#micOn').style.display=on?'':'none';$('#micOff').style.display=on?'none':''}else S.jitsi?.executeCommand('toggleAudio')};$('#camTB').onclick=async()=>{if(!R.permissions.cam&&!R.isHost)return requestPermission('cam','دوربین');if(S.provider==='p2p'&&S.p2p){const on=await S.p2p.toggleCam();$('#camTB').className=on?'tb on':'tb off';$('#camOn').style.display=on?'':'none';$('#camOff').style.display=on?'none':'';renderMainStage()}else S.jitsi?.executeCommand('toggleVideo')};$('#scrTB').onclick=async()=>{if(!R.permissions.screen&&!R.isHost)return requestPermission('screen','اشتراک صفحه');if(S.provider==='p2p'&&S.p2p){const on=await S.p2p.toggleScreen();$('#scrTB').className=on?'tb on':'tb';renderMainStage()}else S.jitsi?.executeCommand('toggleShareScreen')};$('#handTB').onclick=toggleHand;window.showMdl=id=>$('#'+id).classList.add('on');window.hideMdl=id=>$('#'+id).classList.remove('on')}
function parseDomain(url){try{return new URL(url).hostname}catch(e){return 'meet.jit.si'}}
function loadScript(src,timeout=8500){return new Promise((res,rej)=>{if(window.JitsiMeetExternalAPI)return res();const s=document.createElement('script');let done=false;const t=setTimeout(()=>{if(!done){done=true;s.remove();rej(new Error('timeout'))}},timeout);s.src=src;s.async=true;s.onload=()=>{if(!done){done=true;clearTimeout(t);res()}};s.onerror=()=>{if(!done){done=true;clearTimeout(t);rej(new Error('load fail'))}};document.head.appendChild(s)})}
async function startJitsi(){S.provider='jitsi';await loadScript(`https://${parseDomain(R.jitsiServer)}/external_api.js`);S.jitsi=new JitsiMeetExternalAPI(parseDomain(R.jitsiServer),{roomName:(R.jitsiRoom||('madar-'+R.sessionId)).replace(/[^a-zA-Z0-9_-]/g,''),parentNode:$('#jitsi-host'),width:'100%',height:'100%',lang:'fa',userInfo:{displayName:R.displayName||R.userName},configOverwrite:{prejoinPageEnabled:false,disableDeepLinking:true,startWithAudioMuted:!R.isHost,startWithVideoMuted:!R.isHost},interfaceConfigOverwrite:{MOBILE_APP_PROMO:false,SHOW_JITSI_WATERMARK:false,SHOW_BRAND_WATERMARK:false,DEFAULT_BACKGROUND:'#050807'}});S.jitsi.addEventListener('videoConferenceJoined',()=>toast('به جلسه وصل شدید','ok'));S.jitsi.addEventListener('readyToClose',leaveRoom)}
async function refreshPeerList(){
  const r=await fetch(`${R.apiBase}/online_p2p.php?action=peers&room_id=${R.sessionId}`,{credentials:'same-origin'}).then(x=>x.json()).catch(()=>null);
  if(!r?.ok) return;
  const seen=new Set();
  (r.peers||[]).forEach(p=>{
    if(+p.user_id===+R.userId) return;
    const key='u'+(p.user_id||p.peer_id);
    seen.add(key);
    const old=S.participants.get(key)||{};
    S.participants.set(key,{...old,peer_id:p.peer_id,user_id:p.user_id,name:p.name||old.name||'دانش‌آموز',mic_on:+(p.mic_on??old.mic_on??0),cam_on:+(p.cam_on??old.cam_on??0),screen_on:+(p.screen_on??old.screen_on??0),stream:old.stream||null});
  });
  for(const k of [...S.participants.keys()]) if(!seen.has(k)) S.participants.delete(k);
  renderParticipants();
}
async function startP2P(){
  S.provider='p2p';
  S.p2p=await new window.MadarP2P(R,{toast,onReady:()=>toast('اتصال کلاس برقرار شد','ok')}).start($('#jitsi-host'));
  const origAdd=S.p2p.addTile.bind(S.p2p);
  S.p2p.addTile=function(id,name,stream,isLocal,isHost,meta){
    origAdd(id,name,stream,isLocal,isHost,meta);
    if(isLocal){renderMainStage();return}
    const key='u'+(meta?.user_id||id);
    const old=S.participants.get(key)||{};
    S.participants.set(key,{...old,peer_id:id,user_id:meta?.user_id||old.user_id||0,name:name||old.name||'دانش‌آموز',mic_on:+(meta?.mic_on??old.mic_on??0),cam_on:+(meta?.cam_on??old.cam_on??0),screen_on:+(meta?.screen_on??old.screen_on??0),stream});
    renderParticipants();
  };
  const origUpdate=S.p2p.updatePeerMeta?.bind(S.p2p);
  S.p2p.updatePeerMeta=function(peer){
    if(origUpdate)origUpdate(peer);
    const key='u'+(peer.user_id||peer.peer_id);
    const old=S.participants.get(key)||{};
    S.participants.set(key,{...old,peer_id:peer.peer_id||old.peer_id,user_id:peer.user_id||old.user_id||0,name:peer.name||old.name||'دانش‌آموز',mic_on:+(peer.mic_on??old.mic_on??0),cam_on:+(peer.cam_on??old.cam_on??0),screen_on:+(peer.screen_on??old.screen_on??0),stream:old.stream||null});
    renderParticipants();
  };
  const origRemove=S.p2p.removePeer.bind(S.p2p);
  S.p2p.removePeer=function(id){
    for(const [k,v] of S.participants.entries()) if(v.peer_id===id || k==='u'+id) S.participants.delete(k);
    origRemove(id);
    renderParticipants();
  };
  const origHandle=S.p2p.handleCommand.bind(S.p2p);
  S.p2p.handleCommand=async function(cmd){await origHandle(cmd);renderMainStage()};
  renderMainStage();
  refreshPeerList();
  setInterval(refreshPeerList,1200);
}
let wbSaving=false,wbLast='';
const wb={open:false,tool:'pen',color:'#333',size:2,down:false,last:null,version:0};
const dC=()=>$('#dC'),dx=()=>dC().getContext('2d',{willReadFrequently:true});
function resizeWb(){const box=$('#wbCa');if(!box)return;const c=dC();c.width=box.clientWidth;c.height=box.clientHeight;$('#pC').width=c.width;$('#pC').height=c.height;$('#uC').width=c.width;$('#uC').height=c.height}
function pos(e){const r=dC().getBoundingClientRect(),p=e.touches?e.touches[0]:e;return{x:p.clientX-r.left,y:p.clientY-r.top}}
function drawSeg(a,b){const ctx=dx();ctx.lineCap='round';ctx.lineJoin='round';ctx.strokeStyle=wb.tool==='er'?'#fff':wb.color;ctx.globalAlpha=wb.tool==='hl'?0.24:1;ctx.lineWidth=wb.tool==='hl'?wb.size*5:(wb.tool==='er'?wb.size*6:wb.size);ctx.globalCompositeOperation=wb.tool==='er'?'destination-out':'source-over';ctx.beginPath();ctx.moveTo(a.x,a.y);ctx.lineTo(b.x,b.y);ctx.stroke();ctx.globalAlpha=1;ctx.globalCompositeOperation='source-over'}
function bindWbCanvas(){const c=dC();if(c.dataset.bound)return;c.dataset.bound='1';c.addEventListener('pointerdown',e=>{wb.down=true;wb.last=pos(e)});c.addEventListener('pointermove',e=>{if(!wb.down)return;const p=pos(e);drawSeg(wb.last,p);wb.last=p});['pointerup','pointerleave','pointercancel'].forEach(ev=>c.addEventListener(ev,()=>{wb.down=false}))}
async function saveBoard(){if(!wb.open||wbSaving||wb.down)return;const data=dC().toDataURL('image/webp',.45);if(data===wbLast)return;wbSaving=true;const r=await api('whiteboard_save',{session_id:R.sessionId,snapshot:JSON.stringify({type:'image',data,ts:Date.now()})}).catch(()=>null);wbSaving=false;if(r?.ok){wbLast=data;wb.version=Math.max(wb.version,+r.version||0)}}
async function loadBoard(){if(!wb.open||wb.down)return;const r=await api('whiteboard_load',{session_id:R.sessionId}).catch(()=>null);if(!r?.ok||!r.snapshot||+r.version<=wb.version)return;try{const obj=JSON.parse(r.snapshot);const img=new Image();img.onload=()=>{const ctx=dx();ctx.clearRect(0,0,dC().width,dC().height);ctx.drawImage(img,0,0,dC().width,dC().height);wb.version=+r.version;wbLast=dC().toDataURL('image/webp',.45)};img.src=obj.data}catch(e){}}
window.tgWB=function(){if(!R.permissions.whiteboard&&!R.isHost)return requestPermission('whiteboard','تخته');wb.open=!wb.open;$('#wbPn').classList.toggle('on',wb.open);$('#wbOv').classList.toggle('on',wb.open);$('#wbTB').className=wb.open?'tb on':'tb';if(wb.open){resizeWb();bindWbCanvas();}}
window.setT=t=>{wb.tool=t;$$('.wt[data-t]').forEach(b=>b.classList.toggle('on',b.dataset.t===t))};window.setC=el=>{wb.color=el.dataset.c;$$('.cd').forEach(x=>x.classList.toggle('on',x===el))};window.setS=el=>{wb.size=+el.dataset.s;$$('.sd').forEach(x=>x.classList.toggle('on',x===el))};window.wU=()=>{};window.wR=()=>{};window.wCl=()=>{dx().clearRect(0,0,dC().width,dC().height)};window.shUp=()=>$('#pdfUp').classList.toggle('on');window.hPDF=e=>{const f=e.target.files[0];if(!f)return;toast('PDF روی این نسخه در حال بهینه‌سازی است','in')};window.pvPg=()=>{};window.nxPg=()=>{};window.pdfZoom=()=>{};window.pdfResetView=()=>{};window.clPDF=()=>$('#pdfNav').classList.remove('on');window.dlWB=()=>{const a=document.createElement('a');a.href=dC().toDataURL('image/png');a.download='madar-board.png';a.click()};window.wbStep=()=>{};window.wbFull=()=>{};
async function connect(){if(!R.jitsiDisabled)await startJitsi().catch(startP2P);else await startP2P()}
async function start(){bindUi();if(R.status==='scheduled'&&R.isHost){await api('start_session',{session_id:R.sessionId}).catch(()=>{});R.status='live'}await connect();setInterval(pollChat,1200);setInterval(pollReactions,1800);if(R.isHost)setInterval(pollHands,1400);setInterval(loadBoard,450);setInterval(saveBoard,220);window.addEventListener('resize',resizeWb);$('#or-loading').style.display='none';$('#or-room').style.display='block';toast('خوش آمدید','ok');console.log('MADAR_ROOM_READY',R)}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
})();