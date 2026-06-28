/* مَدار Online Room — stable free P2P WebRTC engine (mesh, max 6) */
(function(){
  'use strict';
  const W = window;
  class MadarP2P {
    constructor(room, hooks={}){
      this.room=room; this.hooks=hooks; this.api=(room.apiBase||'')+'/online_p2p.php';
      this.myPeerId=''; this.localStream=null; this.screenStream=null; this.screenSenders=new Map(); this.peers=new Map(); this.tiles=new Map();
      this.pollTimer=null; this.started=false; this.initialPollDone=false;
      this.micOn=!!room.isHost && !!room.permissions.mic;
      this.camOn=!!room.isHost && !!room.permissions.cam;
      this.screenOn=false;
      this.ice=[{urls:'stun:stun.l.google.com:19302'},{urls:'stun:stun1.l.google.com:19302'},{urls:'stun:stun2.l.google.com:19302'},{urls:'stun:stun3.l.google.com:19302'},{urls:'stun:stun4.l.google.com:19302'}];
    }
    async start(parent){
      this.parent=parent;
      parent.innerHTML='<div class="p2p-stage"><div class="p2p-badge">کلاس آنلاین مَدار · اتصال پایدار</div><div class="p2p-grid" id="p2p-grid"></div></div>';
      this.grid=parent.querySelector('#p2p-grid');
      await this.initMedia();
      await this.register();
      this.addTile('local', this.room.userName+' (شما)', this.localStream, true, !!this.room.isHost, {user_id:this.room.userId,mic_on:this.micOn?1:0,cam_on:this.camOn?1:0,screen_on:0,is_host:this.room.isHost?1:0});
      this.started=true; this.poll(); this.pollTimer=setInterval(()=>this.poll(),1200);
      document.addEventListener('fullscreenchange',()=>{ if(!document.fullscreenElement && this.grid){ this.grid.classList.remove('has-focused'); this.tiles.forEach(t=>t.classList.remove('focused')); }});
      this.hooks.onReady&&this.hooks.onReady();
      return this;
    }
    async initMedia(){
      const constraints={audio:!!this.room.isHost&&!!this.room.permissions.mic, video:!!this.room.isHost&&!!this.room.permissions.cam};
      try{ if(constraints.audio||constraints.video) this.localStream=await navigator.mediaDevices.getUserMedia(constraints); }
      catch(e){ console.warn('P2P getUserMedia failed',e); this.hooks.toast&&this.hooks.toast('دسترسی به دوربین/میکروفون داده نشد؛ بدون تصویر وارد شدید.'); }
      if(!this.localStream) this.localStream=new MediaStream();
      this.localStream.getAudioTracks().forEach(t=>t.enabled=this.micOn);
      this.localStream.getVideoTracks().forEach(t=>t.enabled=this.camOn);
    }
    async register(){
      const r=await this.post('register',{room_id:this.room.sessionId,name:this.room.displayName||this.room.userName,is_host:this.room.isHost?1:0,mic_on:this.micOn?1:0,cam_on:this.camOn?1:0,screen_on:0});
      if(!r.ok) throw new Error(r.error||'P2P register failed');
      this.myPeerId=r.my_id;
    }
    async updateState(){
      if(!this.myPeerId) return;
      this.addTile('local', this.room.userName+' (شما)', this.localStream, true, !!this.room.isHost, {user_id:this.room.userId,mic_on:this.micOn?1:0,cam_on:this.camOn?1:0,screen_on:0,is_host:this.room.isHost?1:0});
      this.post('state',{room_id:this.room.sessionId,my_id:this.myPeerId,mic_on:this.micOn?1:0,cam_on:this.camOn?1:0,screen_on:this.screenOn?1:0}).catch(()=>{});
    }
    async poll(){
      if(!this.myPeerId) return;
      let r; try{ r=await this.post('poll',{room_id:this.room.sessionId,my_id:this.myPeerId}); }catch(e){return;}
      if(!r.ok){ if(r.error==='peer_not_found'){ try{ for(const id of Array.from(this.peers.keys())) this.removePeer(id); await this.register(); await this.updateState(); this.hooks.toast&&this.hooks.toast('اتصال کلاس دوباره برقرار شد.'); }catch(e){} } if(r.error==='session_not_live'){ this.hooks.toast&&this.hooks.toast('کلاس پایان یافته یا هنوز شروع نشده است.'); } return; }
      const live=new Set((r.peers||[]).map(p=>p.peer_id));
      for(const p of (r.peers||[])) {
        this.hooks.onMeta&&this.hooks.onMeta(p);
        if(!this.peers.has(p.peer_id)) { await this.createPeer(p, this.myPeerId < p.peer_id); if(this.initialPollDone) this.hooks.toast&&this.hooks.toast((p.name||'یک نفر')+' وارد کلاس شد.'); }
        else this.updatePeerMeta(p);
      }
      for(const id of Array.from(this.peers.keys())) if(!live.has(id)){ const nm=this.peers.get(id)?.peer?.name||'یک نفر'; this.removePeer(id); this.hooks.toast&&this.hooks.toast(nm+' از کلاس خارج شد.'); }
      for(const m of (r.messages||[])) await this.handleSignal(m.from_peer_id, m.signal);
      for(const c of (r.commands||[])) await this.handleCommand(c.command);
      this.initialPollDone=true; this.layout();
    }
    updatePeerMeta(peer){ this.hooks.onMeta&&this.hooks.onMeta(peer); const st=this.peers.get(peer.peer_id); if(st) st.peer=Object.assign(st.peer||{},peer); const tile=this.tiles.get(peer.peer_id); if(tile) this.applyTileState(tile, peer); const stl=this.tiles.get(peer.peer_id+'_screen'); if(stl){ if(+peer.screen_on) this.applyTileState(stl, Object.assign({},peer,{screen_on:1,cam_on:0,mic_on:0,is_host:0})); else this.removeTile(peer.peer_id+'_screen'); } }
    async createPeer(peer, politeOffer){
      const pc=new RTCPeerConnection({iceServers:this.ice});
      const state={pc, peer, makingOffer:false, remoteStream:null}; this.peers.set(peer.peer_id,state);
      this.localStream.getTracks().forEach(t=>pc.addTrack(t,this.localStream));
      if(this.screenStream){ const tr=this.screenStream.getVideoTracks()[0]; if(tr){ try{ this.screenSenders.set(peer.peer_id, pc.addTrack(tr,this.screenStream)); }catch(e){} } }
      pc.ontrack=ev=>{ const st=this.peers.get(peer.peer_id); if(!st)return; const tr=ev.track; const hasVideo=!!(st.remoteStream&&st.remoteStream.getVideoTracks().length>0); const wantsScreen=tr.kind==='video' && (hasVideo || (!!(+((st.peer||peer).screen_on)) && !+((st.peer||peer).cam_on)));  if(wantsScreen){ if(!st.remoteScreenStream) st.remoteScreenStream=new MediaStream(); if(!st.remoteScreenStream.getTracks().some(x=>x.id===tr.id)) st.remoteScreenStream.addTrack(tr); this.addTile(peer.peer_id+'_screen', (st.peer?.name||peer.name||'کاربر')+' · صفحه', st.remoteScreenStream, false, false, Object.assign({}, st.peer||peer, {screen_on:1, cam_on:0, mic_on:0, is_host:0})); return; } if(!st.remoteStream) st.remoteStream=new MediaStream(); if(!st.remoteStream.getTracks().some(x=>x.id===tr.id)) st.remoteStream.addTrack(tr); this.addTile(peer.peer_id, (st.peer?.name||peer.name||'کاربر'), st.remoteStream, false, !!(st.peer?.is_host||peer.is_host), st.peer||peer); };
      pc.onicecandidate=ev=>{ if(ev.candidate) this.signal(peer.peer_id,{candidate:ev.candidate}); };
      pc.onconnectionstatechange=()=>{ if(['failed','closed','disconnected'].includes(pc.connectionState)) setTimeout(()=>{ if(pc.connectionState!=='connected') this.removePeer(peer.peer_id); },8000); };
      pc.onnegotiationneeded=async()=>{ try{ state.makingOffer=true; await pc.setLocalDescription(await pc.createOffer()); await this.signal(peer.peer_id,{description:pc.localDescription}); }finally{ state.makingOffer=false; } };
      if(politeOffer){ try{ await pc.setLocalDescription(await pc.createOffer()); await this.signal(peer.peer_id,{description:pc.localDescription}); }catch(e){console.warn(e);} }
    }
    async handleSignal(from, signal){
      let st=this.peers.get(from); if(!st){ await this.createPeer({peer_id:from,name:'کاربر'}, false); st=this.peers.get(from); }
      const pc=st.pc;
      try{
        if(signal.description){ const offerCollision=signal.description.type==='offer' && (st.makingOffer || pc.signalingState!=='stable'); if(offerCollision && this.myPeerId > from) return; await pc.setRemoteDescription(signal.description); if(signal.description.type==='offer'){ await pc.setLocalDescription(await pc.createAnswer()); await this.signal(from,{description:pc.localDescription}); } }
        else if(signal.candidate){ await pc.addIceCandidate(signal.candidate).catch(()=>{}); }
      }catch(e){ console.warn('handleSignal',e); }
    }
    async handleCommand(cmd){
      if(cmd==='mic_off') { await this.forceMic(false); this.hooks.toast&&this.hooks.toast('میکروفون شما توسط مشاور بسته شد.'); }
      if(cmd==='cam_off') { await this.forceCam(false); this.hooks.toast&&this.hooks.toast('دوربین شما توسط مشاور بسته شد.'); }
      if(cmd==='screen_off') { await this.stopScreen(); this.hooks.toast&&this.hooks.toast('اشتراک صفحه شما توسط مشاور بسته شد.'); }
      if(cmd==='kick') { this.hooks.toast&&this.hooks.toast('مشاور شما را از کلاس خارج کرد.'); setTimeout(()=>location.href='student/online_sessions.php',800); }
    }
    async signal(to, signal){ return this.post('signal',{room_id:this.room.sessionId,my_id:this.myPeerId,to_peer_id:to,signal}); }
    async post(action, body){ const csrf=window.MADAR?.csrf||document.querySelector('meta[name="csrf-token"]')?.content||''; const res=await fetch(`${this.api}?action=${encodeURIComponent(action)}&room_id=${this.room.sessionId}`,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},credentials:'same-origin',body:JSON.stringify(body||{})}); return res.json(); }
    addTile(id,name,stream,isLocal,isHost=false,meta={}){
      if(this.hooks.onTile && this.hooks.onTile({id,name,stream,isLocal,isHost,meta})===true) return;
      let tile=this.tiles.get(id);
      if(!tile){ tile=document.createElement('div'); tile.className='p2p-tile'; tile.dataset.peer=id; tile.innerHTML=`<video ${isLocal?'muted':''} playsinline autoplay></video><div class="p2p-avatar"></div><div class="p2p-status"><span class="mic" title="میکروفون"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3M8 21h8"/></svg></span><span class="cam" title="دوربین"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7a3 3 0 0 1 3-3h7a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7z"/><path d="M17 10l5-3v10l-5-3z"/></svg></span><span class="screen" title="اشتراک صفحه"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg></span></div><div class="p2p-tile-actions"><button class="p2p-tile-btn" type="button" title="بزرگ‌نمایی"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H3v5M16 3h5v5M8 21H3v-5M21 16v5h-5"/></svg></button></div><div class="p2p-name"></div>`; tile.querySelector('.p2p-tile-btn').addEventListener('click',()=>this.focusTile(id)); this.grid.appendChild(tile); this.tiles.set(id,tile); }
      const v=tile.querySelector('video'); if(v.srcObject!==stream) v.srcObject=stream;
      tile.querySelector('.p2p-name').textContent=name; tile.querySelector('.p2p-avatar').textContent=(name||'?').trim().slice(0,2);
      this.applyTileState(tile,Object.assign({is_host:isHost?1:0},meta)); this.layout();
    }
    applyTileState(tile,meta){ const mic=!!(+meta.mic_on), cam=!!(+meta.cam_on), screen=!!(+meta.screen_on), host=!!(+meta.is_host); tile.classList.toggle('is-host',host); tile.classList.toggle('is-screen',screen); tile.classList.toggle('has-video',cam||screen); tile.querySelector('.mic')?.classList.toggle('on',mic); tile.querySelector('.cam')?.classList.toggle('on',cam); tile.querySelector('.screen')?.classList.toggle('on',screen); }
    layout(){ if(!this.grid) return; const tiles=[...this.tiles.values()]; const n=this.tiles.size; const focused=this.grid.classList.contains('has-focused'); const hasScreen=tiles.some(t=>t.classList.contains('is-screen')); const hasHost=tiles.some(t=>t.classList.contains('is-host')); this.grid.className='p2p-grid count-'+Math.min(6,Math.max(1,n))+(focused?' has-focused':'')+(hasScreen?' has-screen':'')+(hasHost?' has-host':''); tiles.forEach(t=>t.classList.remove('is-main')); const main=hasScreen?tiles.find(t=>t.classList.contains('is-screen')):(tiles.find(t=>t.classList.contains('is-host'))||tiles[0]); main&&main.classList.add('is-main'); }
    removeTile(id){ this.hooks.onRemoveTile&&this.hooks.onRemoveTile(id); const tile=this.tiles.get(id); if(tile){tile.remove(); this.tiles.delete(id);} this.layout(); }
    removePeer(id){ const st=this.peers.get(id); if(st){ try{st.pc.close()}catch(e){} this.peers.delete(id); } this.screenSenders.delete(id); this.removeTile(id); this.removeTile(id+'_screen'); }
    async forceMic(on){ this.micOn=!!on; this.localStream.getAudioTracks().forEach(t=>t.enabled=this.micOn); await this.updateState(); return this.micOn; }
    async forceCam(on){ this.camOn=!!on; this.localStream.getVideoTracks().forEach(t=>t.enabled=this.camOn); await this.updateState(); return this.camOn; }
    async toggleMic(){ if(!this.room.permissions.mic && !this.room.isHost) return false; if(!this.localStream.getAudioTracks().length){ try{ const s=await navigator.mediaDevices.getUserMedia({audio:true}); const t=s.getAudioTracks()[0]; this.localStream.addTrack(t); for(const {pc} of this.peers.values()) pc.addTrack(t,this.localStream); }catch(e){this.hooks.toast&&this.hooks.toast('میکروفون در دسترس نیست.'); return false;} } return this.forceMic(!this.micOn); }
    async toggleCam(){ if(!this.room.permissions.cam && !this.room.isHost) return false; if(!this.localStream.getVideoTracks().length){ try{ const s=await navigator.mediaDevices.getUserMedia({video:true}); const t=s.getVideoTracks()[0]; this.localStream.addTrack(t); for(const {pc} of this.peers.values()) pc.addTrack(t,this.localStream); }catch(e){this.hooks.toast&&this.hooks.toast('دوربین در دسترس نیست.'); return false;} } return this.forceCam(!this.camOn); }
    focusTile(id){ const tile=this.tiles.get(id); if(!tile)return; const focused=tile.classList.toggle('focused'); this.grid.classList.toggle('has-focused',focused); if(focused && tile.requestFullscreen) tile.requestFullscreen().catch(()=>{}); else if(document.fullscreenElement) document.exitFullscreen().catch(()=>{}); }
    async toggleScreen(){ if(!this.room.permissions.screen && !this.room.isHost) return false; if(this.screenStream){ await this.stopScreen(); return false; } try{ this.screenStream=await navigator.mediaDevices.getDisplayMedia({video:true,audio:false}); const screenTrack=this.screenStream.getVideoTracks()[0]; if(screenTrack) screenTrack.contentHint='detail'; this.screenSenders.clear(); for(const [pid,{pc}] of this.peers.entries()){ try{ this.screenSenders.set(pid, pc.addTrack(screenTrack,this.screenStream)); }catch(e){} } this.screenOn=true; this.addTile('local_screen','اشتراک صفحه شما',this.screenStream,true,false,{user_id:this.room.userId,mic_on:0,cam_on:0,screen_on:1,is_host:0}); await this.updateState(); screenTrack.onended=()=>this.stopScreen(); return true; }catch(e){ this.hooks.toast&&this.hooks.toast('اشتراک صفحه لغو شد یا پشتیبانی نمی‌شود.'); return false; } }
    async stopScreen(){ for(const [pid,sender] of this.screenSenders.entries()){ const st=this.peers.get(pid); if(st&&sender){ try{st.pc.removeTrack(sender)}catch(e){} } } this.screenSenders.clear(); if(this.screenStream){this.screenStream.getTracks().forEach(t=>t.stop()); this.screenStream=null;} this.screenOn=false; this.removeTile('local_screen'); await this.updateState(); }
    async leave(){ clearInterval(this.pollTimer); if(this.myPeerId) await this.post('leave',{room_id:this.room.sessionId,my_id:this.myPeerId}).catch(()=>{}); for(const id of Array.from(this.peers.keys())) this.removePeer(id); [this.localStream,this.screenStream].forEach(s=>s&&s.getTracks().forEach(t=>t.stop())); }
  }
  W.MadarP2P=MadarP2P;
})();
