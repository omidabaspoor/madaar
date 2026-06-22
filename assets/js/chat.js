/* =================================================================
   Madar Chat — reliable text, camera/gallery image, PDF/file, voice
   ================================================================= */
(() => {
  'use strict';
  window.MADAR_CHAT_READY = true;
  if (!window.API_MSG) return;

  const contactsEl = document.getElementById('chatContacts') || document.getElementById('chatList');
  const bodyEl = document.getElementById('chatBody');
  const form = document.getElementById('chatForm');
  const text = document.getElementById('chatText');
  const attachBtn = document.getElementById('attachBtn');
  const voiceBtn = document.getElementById('voiceBtn');
  const cameraInput = document.getElementById('chatCamera');
  const galleryInput = document.getElementById('chatGallery');
  const fileInput = document.getElementById('chatFile');
  const sendBtn = document.getElementById('chatSend');
  const searchInput = document.getElementById('chatSearch');
  const attachPreview = document.getElementById('attachPreview');
  const attachPreviewBody = document.getElementById('attachPreviewBody');
  const attachClear = document.getElementById('attachClear');
  const attachSheet = document.getElementById('attachSheet');
  const chatShell = document.querySelector('.chat-shell');
  const contactsToggle = document.getElementById('chatContactsToggle');
  const chatState = document.getElementById('chatState');
  const voiceRecordBar = document.getElementById('voiceRecordBar');
  const voiceTimer = document.getElementById('voiceTimer');
  const voiceStop = document.getElementById('voiceStop');
  const voiceCancel = document.getElementById('voiceCancel');
  const fa = window.faNum || ((n)=>String(n).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]));

  let active = null;
  let pollTimer = null;
  let contacts = [];
  let currentAttachment = null; // {type, file, url?}

  let recorder = null;
  let recStream = null;
  let recChunks = [];
  let recording = false;
  let recordTimerId = null;
  let recordStartedAt = 0;
  let cancelRecord = false;
  const MAX_RECORD_SECONDS = 5 * 60;

  const esc = (s)=>String(s||'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const escAttr = (s)=>esc(s).replace(/'/g,'&#39;');
  const letters = (n)=>{ const p=String(n||'').trim().split(/\s+/); return (p[0]?.[0]||'')+(p[1]?.[0]||'') || 'م'; };
  const setState = (s)=>{ if(chatState) chatState.textContent = s || ''; };
  const extOf = (name)=>String(name||'').split('.').pop().toLowerCase();
  const isPdf = (file)=>extOf(file.name)==='pdf' || file.type === 'application/pdf';
  const humanSize = (bytes=0)=>{
    bytes = Number(bytes)||0;
    if (bytes < 1024) return fa(bytes) + ' بایت';
    if (bytes < 1024*1024) return fa(Math.round(bytes/1024)) + ' کیلوبایت';
    return fa((bytes/1024/1024).toFixed(bytes < 10*1024*1024 ? 1 : 0)) + ' مگابایت';
  };
  const fmtTime = (sec)=>{
    sec = Math.max(0, Math.floor(sec));
    const m = String(Math.floor(sec/60)).padStart(2,'0');
    const s = String(sec%60).padStart(2,'0');
    return fa(m + ':' + s);
  };

  function openSheet() {
    if (window.openModal) openModal('attachSheet');
    else attachSheet?.classList.add('open');
  }
  function closeSheet() {
    if (window.closeModal && attachSheet) closeModal(attachSheet);
    else attachSheet?.classList.remove('open');
  }
  function openContactsPanel() { contactsEl.closest('.chat-list')?.classList.add('open'); }
  function closeContactsPanel() { contactsEl.closest('.chat-list')?.classList.remove('open'); }

  function renderContacts() {
    const q = (searchInput?.value || '').trim().toLowerCase();
    const rows = q ? contacts.filter(c => String(c.full_name||'').toLowerCase().includes(q) || String(c.field||'').toLowerCase().includes(q)) : contacts;
    if (!rows.length) {
      contactsEl.innerHTML = '<div class="empty-state" style="padding:30px">گفتگویی پیدا نشد</div>';
      return;
    }
    contactsEl.innerHTML = rows.map(c => `
      <div class="chat-item ${active==c.id?'active':''}" data-id="${c.id}" data-name="${escAttr(c.full_name)}" data-sub="${escAttr(c.field||'')}">
        <span class="u-ava">${esc(c.avatar || letters(c.full_name))}</span>
        <div style="flex:1;min-width:0">
          <div class="nm">${esc(c.full_name)}</div>
          <div class="lm">${esc(c.last || 'بدون پیام')}</div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px">
          ${c.last_ago?`<span class="meta-time">${esc(c.last_ago)}</span>`:''}
          ${c.unread>0?`<span class="badge-count">${fa(c.unread)}</span>`:''}
        </div>
      </div>`).join('');
  }

  async function loadContacts() {
    try {
      const d = await api(window.API_MSG + '?action=contacts');
      contacts = d.items || [];
      renderContacts();
      if (window.INIT_WITH && !active) openChat(window.INIT_WITH);
      else if (!active && window.MY_ROLE === 'student' && contacts.length > 0) openChat(contacts[0].id);
      else if (!active && window.matchMedia('(max-width: 900px)').matches) openContactsPanel();
    } catch(e) {
      contactsEl.innerHTML = '<div class="empty-state" style="padding:30px">خطا در دریافت گفتگوها</div>';
    }
  }

  async function openChat(id) {
    active = parseInt(id);
    renderContacts();
    const item = contacts.find(c => parseInt(c.id) === active) || {};
    document.getElementById('chatName').textContent = item.full_name || 'گفتگو';
    document.getElementById('chatSub').textContent = item.field || 'آنلاین نیست؟ پیام را بفرست، بعداً می‌بیند.';
    document.getElementById('chatAva').textContent = item.avatar || letters(item.full_name || 'م');
    form.style.display = 'flex';
    chatShell?.classList.add('has-active');
    closeContactsPanel();
    clearAttachment();
    await loadMessages(true);
    clearInterval(pollTimer);
    pollTimer = setInterval(()=>loadMessages(false), 3000); // لایو: هر ۳ ثانیه
  }

  function fileBubbleHTML(att) {
    const url = escAttr(att.url);
    const name = esc(att.name || (att.type === 'pdf' ? 'document.pdf' : 'file'));
    const kind = att.type === 'pdf' ? 'PDF' : (att.name ? extOf(att.name).toUpperCase() : 'FILE');
    return `<a class="bubble-file" href="${url}" target="_blank" rel="noopener" download>
      <span class="file-ico ${att.type==='pdf'?'pdf':''}">${att.type==='pdf'?'PDF':'📎'}</span>
      <span class="file-info"><b>${name}</b><small>${esc(kind)} ${att.size?`· ${humanSize(att.size)}`:''}</small></span>
    </a>`;
  }

  function mediaHTML(att) {
    if (!att || !att.url) return '';
    const url = escAttr(att.url);
    if (att.type === 'image') {
      return `<a class="bubble-media" href="${url}" target="_blank" rel="noopener"><img class="bubble-img" src="${url}" alt="تصویر ارسالی" loading="lazy"></a>`;
    }
    if (att.type === 'audio') {
      const uid = 'voice_' + Math.random().toString(36).slice(2,8);
      return `<div class="bubble-voice" data-voice-id="${uid}">
        <button class="voice-play-btn" type="button" data-voice-toggle="${uid}" aria-label="پخش ویس">
          <svg class="v-play" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><polygon points="6,4 20,12 6,20"/></svg>
          <svg class="v-pause" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="display:none"><rect x="5" y="4" width="4" height="16" rx="1"/><rect x="15" y="4" width="4" height="16" rx="1"/></svg>
        </button>
        <div class="voice-wave-wrap">
          <canvas class="voice-wave" data-voice-canvas="${uid}" width="180" height="36"></canvas>
          <div class="voice-progress-bar" data-voice-bar="${uid}"></div>
        </div>
        <span class="voice-dur" data-voice-dur="${uid}">--:--</span>
        <audio data-voice-audio="${uid}" preload="metadata" src="${url}"></audio>
      </div>`;
    }
    if (att.type === 'pdf' || att.type === 'file') return fileBubbleHTML(att);
    return '';
  }

  let lastMsgId = 0;
  let lastMsgCount = 0;

  async function loadMessages(scroll) {
    if (!active) return;
    try {
      const d = await api(`${window.API_MSG}?action=list&with=${active}`);
      const items = d.items || [];
      const atBottom = bodyEl.scrollHeight - bodyEl.scrollTop - bodyEl.clientHeight < 80;

      // تشخیص تغییر واقعی — جلوگیری از re-render بی‌مورد (لایو بدون چشمک)
      const newLastId = items.length ? parseInt(items[items.length-1].id) : 0;
      const newCount = items.length;
      if (!scroll && newLastId === lastMsgId && newCount === lastMsgCount) return; // بدون تغییر → هیچ‌کاری نکن
      lastMsgId = newLastId;
      lastMsgCount = newCount;

      if (!items.length) {
        bodyEl.innerHTML = '<div class="empty-state chat-empty"><div class="es-ico">💬</div><p>هنوز پیامی رد و بدل نشده</p><p class="muted" style="font-size:.82rem">اولین پیام را تو بفرست 🌿</p></div>';
        return;
      }
      let lastDate = '';
      bodyEl.innerHTML = items.map(m => {
        const sep = m.date !== lastDate ? `<div class="chat-day-sep">${esc(m.date)}</div>` : '';
        lastDate = m.date;
        const body = m.body ? `<div class="msg-text">${esc(m.body).replace(/\n/g,'<br>')}</div>` : '';
        return `${sep}<div class="bubble ${m.mine?'me':'them'}" data-id="${m.id}">${mediaHTML(m.attachment)}${body}<span class="time">${m.time}</span></div>`;
      }).join('');
      if (scroll || atBottom) bodyEl.scrollTop = bodyEl.scrollHeight;

      // اگه پیام جدید از طرف مقابل اومد → صدای نوتیف
      if (!scroll && items.length > 0) {
        const lastMsg = items[items.length-1];
        if (!lastMsg.mine && newLastId > (lastMsgId - 1)) {
          playMsgSound();
        }
      }
    } catch(e) { /* keep current view */ }
  }

  // صدای ملایم پیام جدید
  function playMsgSound() {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain); gain.connect(ctx.destination);
      osc.frequency.value = 880; osc.type = 'sine';
      gain.gain.setValueAtTime(0.08, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
      osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.3);
    } catch(_){}
  }

  function clearAttachment(revoke = true) {
    if (revoke && currentAttachment?.url) URL.revokeObjectURL(currentAttachment.url);
    currentAttachment = null;
    if (cameraInput) cameraInput.value = '';
    if (galleryInput) galleryInput.value = '';
    if (fileInput) fileInput.value = '';
    if (attachPreview) attachPreview.classList.add('hidden');
    if (attachPreviewBody) attachPreviewBody.innerHTML = '';
  }

  function setAttachment(file, type) {
    clearAttachment(true);
    currentAttachment = { file, type };
    if (type === 'image') {
      const url = URL.createObjectURL(file);
      currentAttachment.url = url;
      attachPreviewBody.innerHTML = `<img class="ap-thumb" src="${url}" alt=""><span>عکس آماده ارسال <small>${humanSize(file.size)}</small></span>`;
    } else if (type === 'audio') {
      const url = URL.createObjectURL(file);
      currentAttachment.url = url;
      attachPreviewBody.innerHTML = `
        <div class="ap-voice-ready">
          <div class="ap-voice-icon">🎙</div>
          <div class="ap-voice-info">
            <b>ویس آماده ارسال</b>
            <small>${humanSize(file.size)}</small>
          </div>
          <audio class="ap-audio-player" controls src="${url}"></audio>
        </div>`;
    } else {
      const pdf = isPdf(file);
      currentAttachment.type = 'file'; // field name for backend; backend classifies pdf/file
      attachPreviewBody.innerHTML = `<span class="ap-file-ico ${pdf?'pdf':''}">${pdf?'PDF':'📎'}</span><span class="ap-file"><b>${esc(file.name)}</b><small>${humanSize(file.size)}</small></span>`;
    }
    attachPreview?.classList.remove('hidden');
    text?.focus();
  }

  function validateImage(file) {
    if (!file) return false;
    if (!file.type.startsWith('image/')) { toast('فقط فایل عکس مجاز است','error'); return false; }
    if (file.size > 10 * 1024 * 1024) { toast('حجم عکس باید کمتر از ۱۰ مگابایت باشد','error'); return false; }
    return true;
  }
  function validateFile(file) {
    if (!file) return false;
    const allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','zip','rar','7z'];
    if (!allowed.includes(extOf(file.name))) { toast('فقط PDF و فایل‌های رایج مجاز هستند','error'); return false; }
    if (file.size > 20 * 1024 * 1024) { toast('حجم فایل باید کمتر از ۲۰ مگابایت باشد','error'); return false; }
    return true;
  }

  attachBtn?.addEventListener('click', openSheet);
  attachSheet?.addEventListener('click', (e) => {
    const b = e.target.closest('[data-pick]');
    if (!b) return;
    closeSheet();
    const pick = b.dataset.pick;
    // باید در همان gesture کاربر باز شود تا موبایل‌ها file picker را بلاک نکنند.
    if (pick === 'camera') cameraInput?.click();
    else if (pick === 'gallery') galleryInput?.click();
    else if (pick === 'file') fileInput?.click();
  });
  cameraInput?.addEventListener('change', () => {
    const file = cameraInput.files?.[0];
    if (validateImage(file)) setAttachment(file, 'image');
    else cameraInput.value = '';
  });
  galleryInput?.addEventListener('change', () => {
    const file = galleryInput.files?.[0];
    if (validateImage(file)) setAttachment(file, 'image');
    else galleryInput.value = '';
  });
  fileInput?.addEventListener('change', () => {
    const file = fileInput.files?.[0];
    if (validateFile(file)) setAttachment(file, 'file');
    else fileInput.value = '';
  });
  attachClear?.addEventListener('click', () => clearAttachment());

  function supportedRecorderOptions() {
    const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'];
    for (const mimeType of candidates) {
      if (window.MediaRecorder?.isTypeSupported?.(mimeType)) return { mimeType };
    }
    return {};
  }
  function startRecordTimer() {
    recordStartedAt = Date.now();
    voiceTimer.textContent = fmtTime(0);
    clearInterval(recordTimerId);
    recordTimerId = setInterval(() => {
      const sec = Math.floor((Date.now() - recordStartedAt) / 1000);
      voiceTimer.textContent = fmtTime(sec);
      if (sec >= MAX_RECORD_SECONDS) {
        toast('حداکثر زمان ویس ۵ دقیقه است؛ ضبط متوقف شد','info');
        stopRecording(false);
      }
    }, 250);
  }
  function resetRecordingUI() {
    clearInterval(recordTimerId);
    recordTimerId = null;
    recording = false;
    voiceBtn?.classList.remove('recording');
    voiceRecordBar?.classList.add('hidden');
    voiceBtn?.setAttribute('data-tip','ضبط ویس');
    setState('');
  }
  async function startRecording() {
    if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
      toast('مرورگر شما ضبط ویس را پشتیبانی نمی‌کند','error'); return;
    }
    try {
      clearAttachment();
      recStream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation:true, noiseSuppression:true } });
      recChunks = [];
      cancelRecord = false;
      recorder = new MediaRecorder(recStream, supportedRecorderOptions());
      recorder.ondataavailable = e => { if (e.data && e.data.size) recChunks.push(e.data); };
      recorder.onstop = () => {
        recStream?.getTracks().forEach(t=>t.stop());
        const wasCancelled = cancelRecord;
        resetRecordingUI();
        if (wasCancelled) { recChunks = []; return; }
        const mime = recorder.mimeType || recChunks[0]?.type || 'audio/webm';
        const blob = new Blob(recChunks, { type: mime });
        if (!blob.size) { toast('ویس ضبط نشد؛ دوباره تلاش کن','error'); return; }
        const ext = mime.includes('ogg') ? 'ogg' : (mime.includes('mp4') ? 'm4a' : 'webm');
        const file = new File([blob], 'voice_' + Date.now() + '.' + ext, { type: mime });
        if (file.size > 15 * 1024 * 1024) { toast('حجم ویس بیش از حد مجاز است','error'); return; }
        setAttachment(file, 'audio');
      };
      recorder.start(1000);
      recording = true;
      voiceBtn?.classList.add('recording');
      voiceRecordBar?.classList.remove('hidden');
      voiceBtn?.setAttribute('data-tip','پایان ضبط');
      setState('در حال ضبط ویس…');
      startRecordTimer();
    } catch(e) {
      resetRecordingUI();
      recStream?.getTracks().forEach(t=>t.stop());
      toast('اجازه دسترسی به میکروفون داده نشد','error');
    }
  }
  function stopRecording(cancel = false) {
    if (!recorder || !recording) return;
    cancelRecord = cancel;
    try { recorder.stop(); } catch(_) { resetRecordingUI(); }
  }

  voiceBtn?.addEventListener('click', () => recording ? stopRecording(false) : startRecording());
  voiceStop?.addEventListener('click', () => stopRecording(false));
  voiceCancel?.addEventListener('click', () => stopRecording(true));

  contactsToggle?.addEventListener('click', openContactsPanel);
  document.addEventListener('click', (e) => {
    const list = contactsEl.closest('.chat-list');
    if (!list?.classList.contains('open')) return;
    if (e.target.closest('.chat-list') || e.target.closest('#chatContactsToggle')) return;
    if (active) closeContactsPanel();
  });
  contactsEl.addEventListener('click', e => {
    const it = e.target.closest('.chat-item');
    if (it) openChat(it.dataset.id);
  });
  searchInput?.addEventListener('input', renderContacts);

  form.addEventListener('submit', async e => {
    e.preventDefault();
    if (!active) return;
    if (recording) { toast('اول ضبط ویس را تمام کن','info'); return; }
    const body = text.value.trim();
    if (!body && !currentAttachment) return;

    const fd = new FormData();
    fd.append('action','send');
    fd.append('with',active);
    fd.append('body',body);
    if (currentAttachment) {
      const field = currentAttachment.type === 'image' ? 'image' : (currentAttachment.type === 'audio' ? 'audio' : 'file');
      fd.append(field, currentAttachment.file);
    }

    sendBtn.disabled = true;
    attachBtn.disabled = true;
    voiceBtn.disabled = true;
    setState('در حال ارسال…');
    try {
      await api(window.API_MSG, { method:'POST', body: fd });
      text.value = '';
      clearAttachment();
      await loadMessages(true);
      loadContacts();
    } catch(err) {
      toast(err.error || 'خطا در ارسال پیام','error');
    } finally {
      sendBtn.disabled = false;
      attachBtn.disabled = false;
      voiceBtn.disabled = false;
      setState('');
      text.focus();
    }
  });

  loadContacts();
  setInterval(loadContacts, 8000); // لیست مخاطبین هم هر ۸ ثانیه آپدیت

  // ======== Custom Voice Player ========
  function drawVoiceWave(canvas, progress = 0) {
    const ctx = canvas.getContext('2d');
    const w = canvas.width, h = canvas.height;
    ctx.clearRect(0, 0, w, h);
    const bars = 32;
    const gap = 2;
    const barW = (w - (bars-1)*gap) / bars;
    for (let i = 0; i < bars; i++) {
      // شبیه‌سازی waveform با ارتفاع‌های شبه‌رندوم ولی ثابت
      const seed = Math.sin(i * 12.9898 + 78.233) * 43758.5453;
      const barH = (Math.abs(seed - Math.floor(seed)) * 0.7 + 0.3) * h * 0.85;
      const x = i * (barW + gap);
      const y = (h - barH) / 2;
      const pct = i / bars;
      if (pct <= progress) {
        ctx.fillStyle = 'rgba(203,172,128,0.9)'; // طلایی
      } else {
        ctx.fillStyle = 'rgba(255,255,255,0.2)';
      }
      ctx.beginPath();
      // roundRect polyfill for older browsers
      if (ctx.roundRect) { ctx.roundRect(x, y, barW, barH, 2); }
      else { ctx.rect(x, y, barW, barH); }
      ctx.fill();
    }
  }

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-voice-toggle]');
    if (!btn) return;
    const uid = btn.dataset.voiceToggle;
    const audio = document.querySelector(`[data-voice-audio="${uid}"]`);
    if (!audio) return;

    const playIcon = btn.querySelector('.v-play');
    const pauseIcon = btn.querySelector('.v-pause');
    const canvas = document.querySelector(`[data-voice-canvas="${uid}"]`);
    const durEl = document.querySelector(`[data-voice-dur="${uid}"]`);
    const barEl = document.querySelector(`[data-voice-bar="${uid}"]`);

    if (audio.paused) {
      // Pause all other playing audios
      document.querySelectorAll('[data-voice-audio]').forEach(a => {
        if (a !== audio && !a.paused) {
          a.pause();
          const otherUid = a.dataset.voiceAudio;
          const otherBtn = document.querySelector(`[data-voice-toggle="${otherUid}"]`);
          if (otherBtn) {
            otherBtn.querySelector('.v-play').style.display = '';
            otherBtn.querySelector('.v-pause').style.display = 'none';
          }
        }
      });
      audio.play();
      playIcon.style.display = 'none';
      pauseIcon.style.display = '';
    } else {
      audio.pause();
      playIcon.style.display = '';
      pauseIcon.style.display = 'none';
    }

    // Update duration display on load
    audio.addEventListener('loadedmetadata', () => {
      if (durEl && audio.duration && isFinite(audio.duration)) {
        durEl.textContent = fmtTime(Math.floor(audio.duration));
      }
    }, { once: true });

    // Progress animation
    function updateProgress() {
      if (audio.paused) return;
      const pct = audio.duration ? audio.currentTime / audio.duration : 0;
      if (barEl) barEl.style.width = (pct * 100) + '%';
      if (canvas) drawVoiceWave(canvas, pct);
      if (durEl && audio.duration && isFinite(audio.duration)) {
        durEl.textContent = fmtTime(Math.floor(audio.currentTime)) + ' / ' + fmtTime(Math.floor(audio.duration));
      }
      requestAnimationFrame(updateProgress);
    }
    if (!audio.paused) requestAnimationFrame(updateProgress);

    audio.onended = () => {
      playIcon.style.display = '';
      pauseIcon.style.display = 'none';
      if (barEl) barEl.style.width = '0%';
      if (canvas) drawVoiceWave(canvas, 0);
      if (durEl && audio.duration && isFinite(audio.duration)) {
        durEl.textContent = fmtTime(Math.floor(audio.duration));
      }
    };
  });

  // کلیک روی wave برای seek
  document.addEventListener('click', (e) => {
    const wrap = e.target.closest('.voice-wave-wrap');
    if (!wrap) return;
    const voice = wrap.closest('.bubble-voice');
    if (!voice) return;
    const uid = voice.dataset.voiceId;
    const audio = document.querySelector(`[data-voice-audio="${uid}"]`);
    if (!audio || !audio.duration) return;
    const rect = wrap.getBoundingClientRect();
    // RTL support
    const isRTL = getComputedStyle(wrap).direction === 'rtl';
    let pct = isRTL ? (rect.right - e.clientX) / rect.width : (e.clientX - rect.left) / rect.width;
    pct = Math.max(0, Math.min(1, pct));
    audio.currentTime = pct * audio.duration;
  });

  // اولین بار canvas ها رو رسم کن
  const observer = new MutationObserver(() => {
    document.querySelectorAll('[data-voice-canvas]').forEach(c => {
      if (!c.dataset.drawn) { drawVoiceWave(c, 0); c.dataset.drawn = '1'; }
    });
    // مدت ویس
    document.querySelectorAll('[data-voice-audio]').forEach(a => {
      if (a.dataset.durSet) return;
      a.dataset.durSet = '1';
      a.addEventListener('loadedmetadata', () => {
        const uid = a.dataset.voiceAudio;
        const durEl = document.querySelector(`[data-voice-dur="${uid}"]`);
        if (durEl && a.duration && isFinite(a.duration)) durEl.textContent = fmtTime(Math.floor(a.duration));
      });
    });
  });
  observer.observe(bodyEl, { childList: true, subtree: true });

})();
