/* مَدار Online Room — free P2P WebRTC fallback/engine (mesh, max 6) */
(function(){
  'use strict';
  const W = window;
  class MadarP2P {
    constructor(room, hooks={}){
      this.room=room; this.hooks=hooks; this.api=(room.apiBase||'')+'/online_p2p.php';
      this.myPeerId=''; this.localStream=null; this.screenStream=null; this.peers=new Map(); this.tiles=new Map();
      this.pollTimer=null; this.started=false; this.initialPollDone=false; this.micOn=!!room.isHost && !!room.permissions.mic; this.camOn=!!room.isHost && !!room.permissions.cam;
      this.ice=[{urls:'stun:stun.l.google.com:19302'},{urls:'stun:stun1.l.google.com:19302'}];
    }
    async start(parent){
      this.parent=parent; parent.innerHTML='<div class="p2p-stage"><div class="p2p-badge">کلاس آنلاین مَدار</div><div class="p2p-grid" id="p2p-grid"></div></div>';
      this.grid=parent.querySelector('#p2p-grid');
      await this.initMedia();
      await this.register();
      this.addTile('local', this.room.userName+' (شما)', this.localStream, true, !!this.room.isHost);
      this.started=true; this.poll(); this.pollTimer=setInterval(()=>this.poll(),1800);
      document.addEventListener('fullscreenchange',()=>{ if(!document.fullscreenElement && this.grid){ this.grid.classList.remove('has-focused'); this.tiles.forEach(t=>t.classList.remove('focused')); }});
      this.hooks.onReady&&this.hooks.onReady();
      return this;
    }
    async initMedia(){
      // برای دانش‌آموزها ورود اولیه بدون درخواست دوربین/میکروفون است؛ فقط مشاور پیش‌فرض وصل می‌شود.
      const constraints={audio:!!this.room.isHost&&!!this.room.permissions.mic, video:!!this.room.isHost&&!!this.room.permissions.cam};
      try{
        if(constraints.audio||constraints.video){ this.localStream=await navigator.mediaDevices.getUserMedia(constraints); }
      }catch(e){ console.warn('P2P getUserMedia failed',e); this.hooks.toast&&this.hooks.toast('دسترسی به دوربین/میکروفون داده نشد؛ بدون تصویر وارد شدید.'); }
      if(!this.localStream) this.localStream=new MediaStream();
      this.localStream.getAudioTracks().forEach(t=>t.enabled=this.micOn);
      this.localStream.getVideoTracks().forEach(t=>t.enabled=this.camOn);
    }
    async register(){
      const r=await this.post('register',{room_id:this.room.sessionId,name:this.room.displayName||this.room.userName,is_host:this.room.isHost?1:0});
      if(!r.ok) throw new Error(r.error||'P2P register failed');
      this.myPeerId=r.my_id;
    }
    async poll(){
      if(!this.myPeerId) return;
      let r; try{ r=await this.post('poll',{room_id:this.room.sessionId,my_id:this.myPeerId}); }catch(e){return;}
      if(!r.ok) return;
      const live=new Set((r.peers||[]).map(p=>p.peer_id));
      for(const p of (r.peers||[])) if(!this.peers.has(p.peer_id)){ await this.createPeer(p, this.myPeerId < p.peer_id); if(this.initialPollDone) this.hooks.toast&&this.hooks.toast((p.name||'یک نفر')+' وارد کلاس شد.'); }
      for(const id of Array.from(this.peers.keys())) if(!live.has(id)){ const nm=this.peers.get(id)?.peer?.name||'یک نفر'; this.removePeer(id); this.hooks.toast&&this.hooks.toast(nm+' از کلاس خارج شد.'); }
      for(const m of (r.messages||[])) await this.handleSignal(m.from_peer_id, m.signal);
      this.initialPollDone=true; this.layout();
    }
    async createPeer(peer, politeOffer){
      const pc=new RTCPeerConnection({iceServers:this.ice});
      const state={pc, peer, makingOffer:false}; this.peers.set(peer.peer_id,state);
      this.localStream.getTracks().forEach(t=>pc.addTrack(t,this.localStream));
      pc.ontrack=ev=>{ const st=this.peers.get(peer.peer_id); if(!st.remoteStream) st.remoteStream=new MediaStream(); ev.streams[0].getTracks().forEach(t=>{ if(!st.remoteStream.getTracks().includes(t)) st.remoteStream.addTrack(t); }); this.addTile(peer.peer_id, peer.name||'کاربر', st.remoteStream, false, !!peer.is_host); };
      pc.onicecandidate=ev=>{ if(ev.candidate) this.signal(peer.peer_id,{candidate:ev.candidate}); };
      pc.onconnectionstatechange=()=>{ if(['failed','closed','disconnected'].includes(pc.connectionState)) setTimeout(()=>{ if(pc.connectionState!=='connected') this.removePeer(peer.peer_id); },8000); };
      pc.onnegotiationneeded=async()=>{ try{ state.makingOffer=true; await pc.setLocalDescription(await pc.createOffer()); await this.signal(peer.peer_id,{description:pc.localDescription}); }finally{ state.makingOffer=false; } };
      if(politeOffer){ try{ await pc.setLocalDescription(await pc.createOffer()); await this.signal(peer.peer_id,{description:pc.localDescription}); }catch(e){console.warn(e);} }
    }
    async handleSignal(from, signal){
      let st=this.peers.get(from); if(!st){ await this.createPeer({peer_id:from,name:'کاربر'}, false); st=this.peers.get(from); }
      const pc=st.pc;
      try{
        if(signal.description){
          const offerCollision=signal.description.type==='offer' && (st.makingOffer || pc.signalingState!=='stable');
          if(offerCollision && this.myPeerId > from) return;
          await pc.setRemoteDescription(signal.description);
          if(signal.description.type==='offer'){ await pc.setLocalDescription(await pc.createAnswer()); await this.signal(from,{description:pc.localDescription}); }
        } else if(signal.candidate){ await pc.addIceCandidate(signal.candidate).catch(()=>{}); }
      }catch(e){ console.warn('handleSignal',e); }
    }
    async signal(to, signal){ return this.post('signal',{room_id:this.room.sessionId,my_id:this.myPeerId,to_peer_id:to,signal}); }
    async post(action, body){
      const res=await fetch(`${this.api}?action=${encodeURIComponent(action)}&room_id=${this.room.sessionId}`,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify(body||{})});
      return res.json();
    }
    addTile(id,name,stream,isLocal,isHost=false){
      let tile=this.tiles.get(id);
      if(!tile){
        tile=document.createElement('div'); tile.className='p2p-tile'; tile.dataset.peer=id;
        tile.innerHTML=`<video ${isLocal?'muted':''} playsinline autoplay></video><div class="p2p-avatar"></div><div class="p2p-status"><span class="mic"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3M8 21h8"/></svg></span><span class="cam"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a3 3 0 0 1 3-3h7a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7z"/><path d="M17 10l5-3v10l-5-3z"/></svg></span></div><div class="p2p-tile-actions"><button class="p2p-tile-btn" type="button" title="بزرگ‌نمایی"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H3v5M16 3h5v5M8 21H3v-5M21 16v5h-5"/></svg></button></div><div class="p2p-name"></div>`;
        tile.querySelector('.p2p-tile-btn').addEventListener('click',()=>this.focusTile(id));
        this.grid.appendChild(tile); this.tiles.set(id,tile);
      }
      const v=tile.querySelector('video'); if(v.srcObject!==stream) v.srcObject=stream;
      tile.querySelector('.p2p-name').textContent=name;
      tile.querySelector('.p2p-avatar').textContent=(name||'?').trim().slice(0,2);
      const hasVideo=stream && stream.getVideoTracks().some(t=>t.readyState==='live'); tile.classList.toggle('has-video',hasVideo); tile.classList.toggle('is-host',!!isHost);
      this.layout();
    }
    layout(){ if(!this.grid) return; const n=this.tiles.size; const focused=this.grid.classList.contains('has-focused'); this.grid.className='p2p-grid count-'+Math.min(6,Math.max(1,n))+(focused?' has-focused':''); const tiles=[...this.tiles.values()]; if(!tiles.some(t=>t.classList.contains('is-host')||t.classList.contains('is-screen')) && tiles[0]) tiles[0].classList.add('is-main'); tiles.forEach((t,i)=>{ if(i && !t.classList.contains('is-host') && !t.classList.contains('is-screen')) t.classList.remove('is-main'); }); }
    removePeer(id){ const st=this.peers.get(id); if(st){ try{st.pc.close()}catch(e){} this.peers.delete(id); } const tile=this.tiles.get(id); if(tile){tile.remove(); this.tiles.delete(id);} this.layout(); }
    async toggleMic(){ if(!this.room.permissions.mic && !this.room.isHost) return false; const tracks=this.localStream.getAudioTracks(); if(!tracks.length){ try{ const s=await navigator.mediaDevices.getUserMedia({audio:true}); const t=s.getAudioTracks()[0]; this.localStream.addTrack(t); for(const {pc} of this.peers.values()) pc.addTrack(t,this.localStream); }catch(e){this.hooks.toast&&this.hooks.toast('میکروفون در دسترس نیست.'); return false;} }
      this.micOn=!this.micOn; this.localStream.getAudioTracks().forEach(t=>t.enabled=this.micOn); return this.micOn; }
    async toggleCam(){ if(!this.room.permissions.cam && !this.room.isHost) return false; const tracks=this.localStream.getVideoTracks(); if(!tracks.length){ try{ const s=await navigator.mediaDevices.getUserMedia({video:true}); const t=s.getVideoTracks()[0]; this.localStream.addTrack(t); for(const {pc} of this.peers.values()) pc.addTrack(t,this.localStream); }catch(e){this.hooks.toast&&this.hooks.toast('دوربین در دسترس نیست.'); return false;} }
      this.camOn=!this.camOn; this.localStream.getVideoTracks().forEach(t=>t.enabled=this.camOn); this.addTile('local',this.room.userName+' (شما)',this.localStream,true,!!this.room.isHost); return this.camOn; }
    focusTile(id){ const tile=this.tiles.get(id); if(!tile)return; const focused=tile.classList.toggle('focused'); this.grid.classList.toggle('has-focused',focused); if(focused && tile.requestFullscreen) tile.requestFullscreen().catch(()=>{}); else if(document.fullscreenElement) document.exitFullscreen().catch(()=>{}); }
    async toggleScreen(){
      if(!this.room.permissions.screen && !this.room.isHost) return false;
      if(this.screenStream){ await this.stopScreen(); return false; }
      try{ this.screenStream=await navigator.mediaDevices.getDisplayMedia({video:true,audio:false}); const screenTrack=this.screenStream.getVideoTracks()[0];
        for(const {pc} of this.peers.values()){ const sender=pc.getSenders().find(s=>s.track&&s.track.kind==='video'); if(sender) sender.replaceTrack(screenTrack); }
        const localVideo=this.tiles.get('local')?.querySelector('video'); if(localVideo) localVideo.srcObject=this.screenStream; this.tiles.get('local')?.classList.add('is-screen','has-video'); this.layout();
        screenTrack.onended=()=>this.stopScreen(); return true;
      }catch(e){ this.hooks.toast&&this.hooks.toast('اشتراک صفحه لغو شد یا پشتیبانی نمی‌شود.'); return false; }
    }
    async stopScreen(){ const camTrack=this.localStream.getVideoTracks()[0]||null; for(const {pc} of this.peers.values()){ const sender=pc.getSenders().find(s=>s.track&&s.track.kind==='video'); if(sender) sender.replaceTrack(camTrack); } if(this.screenStream){this.screenStream.getTracks().forEach(t=>t.stop()); this.screenStream=null;} const tile=this.tiles.get('local'); if(tile){ tile.querySelector('video').srcObject=this.localStream; tile.classList.remove('is-screen'); this.layout(); } }
    async leave(){ clearInterval(this.pollTimer); if(this.myPeerId) await this.post('leave',{room_id:this.room.sessionId,my_id:this.myPeerId}).catch(()=>{}); for(const id of Array.from(this.peers.keys())) this.removePeer(id); [this.localStream,this.screenStream].forEach(s=>s&&s.getTracks().forEach(t=>t.stop())); }
  }
  W.MadarP2P=MadarP2P;
})();
