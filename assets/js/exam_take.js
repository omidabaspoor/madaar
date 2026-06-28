/* =================================================================
   مَدار Exam-Taking Engine — Smart Dual-Panel & Onboarding Tour
   ================================================================= */
(() => {
  'use strict';
  const env = document.getElementById('examEnv');
  if (!env) return;
  const API = window.API_EXAM_TAKE;
  const attemptId = parseInt(env.dataset.attempt);
  const total = parseInt(env.dataset.total);
  const faNum = (n)=>String(n).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
  const qs = [...document.querySelectorAll('.exam-q')];
  let cur = 0;
  const state = window.EXAM_INIT || {};
  const pending = new Set();
  let submitted = false;

  const ICO={check:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>',
    chevronLeft:'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>'};

  /* =================================================================
     SAMURAI DUAL-PANEL IMAGE / BUBBLE INTERACTION
     ================================================================= */
  const zoomImg  = document.getElementById('bookletImg');
  const zoomTxt  = document.getElementById('zoomLevelText');
  const bArea    = document.getElementById('bookletScrollArea');
  const pdfWrap  = document.getElementById('bookletPdf');
  const pdfCanvas = document.getElementById('bookletPdfCanvas');
  const pdfLoading = document.getElementById('pdfPageLoading');
  const pdfFallback = document.getElementById('bookletPdfFallback');
  const pdfRetry = document.getElementById('bookletPdfRetry');
  const pdfCompatRetry = document.getElementById('bookletPdfCompatRetry');
  const pdfOpenLink = document.getElementById('bookletPdfOpenLink');
  const nativePdfFrame = document.getElementById('bookletNativePdf');
  const pdfCompatBtn = document.getElementById('pdfCompatBtn');
  const fileFallback = document.getElementById('bookletFileFallback');
  const fileOpenLink = document.getElementById('bookletFileOpenLink');
  const viewerPanel = document.getElementById('bookletViewerPanel');
  const toolsToggle = document.getElementById('bookletToolsToggle');
  const bookletToolbar = document.getElementById('bookletToolbar');
  const zoomControls = document.getElementById('imageZoomControls');
  const bookletHint = document.getElementById('bookletHint');
  const pdfPageControls = document.getElementById('pdfPageControls');
  const pdfPrevPage = document.getElementById('pdfPrevPage');
  const pdfNextPage = document.getElementById('pdfNextPage');
  const pdfPageText = document.getElementById('pdfPageText');
  let fitZoom = 1;
  let currentZoom = 1;
  let rotation = 0;
  let pdfRenderMode = 'canvas'; // canvas | native
  let panX = 0, panY = 0;
  const pointers = new Map();
  let dragStart = null;
  let pinchStart = null;
  let lastTapAt = 0;
  let pdfjsPromise = null;
  let pdfDoc = null;
  let pdfSrc = '';
  let pdfPageNo = 1;
  let pdfBaseScale = 1;
  let pdfRenderTask = null;
  let pdfRenderTimer = null;
  let pdfCssW = 0, pdfCssH = 0;

  function clamp(v, min, max){ return Math.max(min, Math.min(max, v)); }
  function viewerRect(){ return bArea?.getBoundingClientRect() || {width:0,height:0,left:0,top:0}; }
  function imgNatural(){ return { w: zoomImg?.naturalWidth || 1000, h: zoomImg?.naturalHeight || 1400 }; }
  function rotatedSize(w, h){ return Math.abs(rotation % 180) === 90 ? {w:h, h:w} : {w, h}; }
  function activeContentSize(){
    if (isPdfMode()) {
      const size = rotatedSize(pdfCssW || pdfCanvas?.offsetWidth || 1000, pdfCssH || pdfCanvas?.offsetHeight || 1400);
      return size;
    }
    const n = imgNatural();
    const size = rotatedSize(n.w * currentZoom, n.h * currentZoom);
    return size;
  }
  function isPdfMode(){ return bArea?.dataset.type === 'pdf'; }
  function isFileMode(){ return bArea?.dataset.type === 'file'; }
  function updatePdfControls(){
    if (!pdfPageControls) return;
    const show = isPdfMode() && pdfRenderMode === 'canvas' && !!pdfDoc;
    pdfPageControls.classList.toggle('hidden', !show);
    if (pdfPageText) pdfPageText.textContent = pdfDoc ? `${faNum(pdfPageNo)}/${faNum(pdfDoc.numPages)}` : '…';
    if (pdfPrevPage) pdfPrevPage.disabled = !pdfDoc || pdfPageNo <= 1;
    if (pdfNextPage) pdfNextPage.disabled = !pdfDoc || pdfPageNo >= pdfDoc.numPages;
  }
  function showPdfError(msg='نمایش PDF آماده نشد.'){
    if (pdfLoading) pdfLoading.classList.add('hidden');
    if (pdfFallback) {
      pdfFallback.classList.remove('hidden');
      const b = pdfFallback.querySelector('b'); if (b) b.textContent = msg;
    }
  }
  async function loadPdfJs(){
    if (!pdfjsPromise) {
      pdfjsPromise = import(window.PDFJS_URL || './assets/js/vendor/pdf.min.mjs').then(mod => {
        mod.GlobalWorkerOptions.workerSrc = window.PDFJS_WORKER || './assets/js/vendor/pdf.worker.min.mjs';
        return mod;
      });
    }
    return pdfjsPromise;
  }
  async function loadPdf(src){
    if (!src) return;
    if (pdfSrc === src && pdfDoc) { updatePdfControls(); return; }
    pdfSrc = src; pdfDoc = null; pdfPageNo = 1;
    if (pdfFallback) pdfFallback.classList.add('hidden');
    if (pdfLoading) pdfLoading.classList.remove('hidden');
    try {
      const pdfjs = await loadPdfJs();
      const task = pdfjs.getDocument({
        url: src,
        httpHeaders: {'X-Madar-Viewer':'1'},
        withCredentials: true,
        disableAutoFetch: false,
        disableStream: false,
        disableRange: false,
        stopAtErrors: false,
        useSystemFonts: true,
        disableFontFace: false,
        isEvalSupported: false,
        maxImageSize: -1
      });
      pdfDoc = await task.promise;
      updatePdfControls();
      await renderPdfPage(true);
    } catch (err) {
      console.warn('PDF load failed', err);
      showPdfError('نمایش PDF آماده نشد. دوباره تلاش کنید یا نمایش با مرورگر را انتخاب کنید.');
    }
  }
  async function renderPdfPage(resetPan=false){
    if (pdfRenderMode === 'native') return;
    if (!pdfDoc || !pdfCanvas || !bArea) return;
    clearTimeout(pdfRenderTimer);
    if (pdfRenderTask) { try { pdfRenderTask.cancel(); } catch(_){} }
    if (pdfLoading) pdfLoading.classList.remove('hidden');
    try {
      const page = await pdfDoc.getPage(pdfPageNo);
      const r = viewerRect();
      const vp1 = page.getViewport({scale:1, rotation});
      const pad = r.width < 640 ? 14 : 26;
      pdfBaseScale = Math.max(0.08, Math.min((r.width - pad) / vp1.width, (r.height - pad) / vp1.height));
      if (resetPan || !currentZoom || currentZoom < pdfBaseScale * .7) currentZoom = pdfBaseScale;
      currentZoom = clamp(currentZoom, pdfBaseScale * .75, Math.max(pdfBaseScale * 6, 3));
      const viewport = page.getViewport({scale:currentZoom, rotation});
      const dpr = Math.min(window.devicePixelRatio || 1, 3);
      pdfCanvas.width = Math.floor(viewport.width * dpr);
      pdfCanvas.height = Math.floor(viewport.height * dpr);
      pdfCanvas.style.width = viewport.width + 'px';
      pdfCanvas.style.height = viewport.height + 'px';
      pdfCssW = viewport.width; pdfCssH = viewport.height;
      const ctx = pdfCanvas.getContext('2d', {alpha:false});
      ctx.setTransform(dpr,0,0,dpr,0,0);
      pdfRenderTask = page.render({canvasContext:ctx, viewport});
      await pdfRenderTask.promise;
      pdfRenderTask = null;
      if (resetPan) { panX = 0; panY = 0; }
      updateZoomTransform();
      updatePdfControls();
      if (pdfLoading) pdfLoading.classList.add('hidden');
    } catch (err) {
      if (String(err?.name || '') === 'RenderingCancelledException') return;
      console.warn('PDF render failed', err);
      showPdfError('نمایش این صفحه PDF آماده نشد. دوباره تلاش کنید.');
    }
  }
  function schedulePdfRender(){
    clearTimeout(pdfRenderTimer);
    pdfRenderTimer = setTimeout(() => renderPdfPage(false), 90);
  }
  function setNativePdfMode(on){
    if (!isPdfMode()) return;
    pdfRenderMode = on ? 'native' : 'canvas';
    const isPhoneNow = window.matchMedia('(max-width: 760px)').matches;
    if (bArea) bArea.classList.toggle('native-pdf-mode', on);
    if (pdfWrap) pdfWrap.classList.toggle('native-mode', on);
    if (pdfCompatBtn) {
      pdfCompatBtn.innerHTML = on
        ? '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 21h8M12 18v3"/></svg><span>نمایش داخل برگه</span>'
        : '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 15h8M8 18h5"/></svg><span>نمایش با مرورگر</span>';
    }
    if (nativePdfFrame) {
      nativePdfFrame.classList.toggle('hidden', !on);
      if (on && pdfSrc) {
        nativePdfFrame.src = pdfSrc + (isPhoneNow ? '' : '#toolbar=0&navpanes=0&scrollbar=1&view=FitH');
        if (isPhoneNow && bArea) {
          nativePdfFrame.style.height = Math.max(1400, Math.round(bArea.clientHeight * 2.4)) + 'px';
        } else {
          nativePdfFrame.style.height = '';
        }
      }
    }
    if (pdfCanvas) pdfCanvas.classList.toggle('hidden', on);
    if (pdfLoading) pdfLoading.classList.add('hidden');
    if (pdfFallback) pdfFallback.classList.add('hidden');
    if (pdfPageControls) pdfPageControls.classList.toggle('hidden', on || !pdfDoc);
    if (zoomControls) zoomControls.classList.toggle('hidden', on);
    if (bookletHint) bookletHint.textContent = on ? 'نمایش با مرورگر؛ برای حرکت در فایل، صفحه را اسکرول کنید' : 'نمایش PDF؛ برای جابه‌جایی برگه را بکشید';
    if (on) { panX = 0; panY = 0; currentZoom = pdfBaseScale || 1; }
    updatePdfControls();
    updateZoomTransform();
  }

  function setViewerMode(type, src){
    const pdf = type === 'pdf';
    const image = type === 'image';
    const file = !pdf && !image;
    rotation = 0;
    if (bArea) {
      bArea.dataset.type = pdf ? 'pdf' : (image ? 'image' : 'file');
      bArea.classList.toggle('pdf-mode', pdf);
      bArea.classList.toggle('file-mode', file);
      bArea.classList.toggle('native-pdf-mode', false);
      bArea.style.cursor = file ? 'default' : 'grab';
    }
    if (fileFallback) fileFallback.classList.toggle('hidden', !file);
    if (fileOpenLink) fileOpenLink.href = src || '#';
    if (pdfOpenLink) pdfOpenLink.href = src || '#';
    if (pdfCompatBtn) pdfCompatBtn.classList.toggle('hidden', file || !src);
    if (zoomControls) zoomControls.style.display = file ? 'none' : '';
    pdfPageControls?.classList.toggle('hidden', !pdf || pdfRenderMode === 'native');
    if (bookletHint) bookletHint.textContent = pdf ? 'نمایش PDF؛ برای جابه‌جایی برگه را بکشید' : (image ? 'برای جابه‌جایی، برگه را بکشید' : 'پیش‌نمایش این فایل پشتیبانی نمی‌شود');
    if (pdf) {
      pdfRenderMode = 'canvas';
      if (pdfCompatBtn) pdfCompatBtn.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 15h8M8 18h5"/></svg><span>نمایش با مرورگر</span>';
      if (zoomImg) { zoomImg.classList.add('hidden'); zoomImg.removeAttribute('src'); }
      if (fileFallback) fileFallback.classList.add('hidden');
      if (nativePdfFrame) { nativePdfFrame.classList.add('hidden'); nativePdfFrame.removeAttribute('src'); }
      if (pdfCanvas) pdfCanvas.classList.remove('hidden');
      if (pdfWrap) { pdfWrap.classList.remove('hidden'); pdfWrap.classList.remove('native-mode'); pdfWrap.dataset.src = src || ''; }
      loadPdf(src);
    } else if (image) {
      if (pdfWrap) { pdfWrap.classList.add('hidden'); pdfWrap.classList.remove('native-mode'); }
      if (pdfFallback) pdfFallback.classList.add('hidden');
      if (nativePdfFrame) { nativePdfFrame.classList.add('hidden'); nativePdfFrame.removeAttribute('src'); }
      pdfDoc = null; pdfSrc = ''; pdfRenderMode = 'canvas';
      if (zoomImg) { zoomImg.classList.remove('hidden'); if (src && zoomImg.src !== src) zoomImg.src = src; }
      setTimeout(resetViewer, 40);
    } else {
      if (zoomImg) { zoomImg.classList.add('hidden'); zoomImg.removeAttribute('src'); }
      if (pdfWrap) { pdfWrap.classList.add('hidden'); pdfWrap.classList.remove('native-mode'); }
      if (pdfFallback) pdfFallback.classList.add('hidden');
      pdfDoc = null; pdfSrc = ''; panX = 0; panY = 0; currentZoom = 1;
    }
  }

  function computeFitZoom(){
    if(!bArea || !zoomImg) return 1;
    const r = viewerRect();
    const n0 = imgNatural();
    const n = rotatedSize(n0.w, n0.h);
    const pad = r.width < 640 ? 18 : 34;
    return Math.min(1, Math.max(0.08, Math.min((r.width - pad) / n.w, (r.height - pad) / n.h)));
  }

  function boundPan(){
    if(!bArea) return;
    const r = viewerRect();
    const size = activeContentSize();
    const w = size.w, h = size.h;
    const slack = 54;
    const maxX = Math.max(slack, (w - r.width) / 2 + slack);
    const maxY = Math.max(slack, (h - r.height) / 2 + slack);
    panX = clamp(panX, -maxX, maxX);
    panY = clamp(panY, -maxY, maxY);
    if (w <= r.width) panX = clamp(panX, -(r.width - w) / 3, (r.width - w) / 3);
    if (h <= r.height) panY = clamp(panY, -(r.height - h) / 3, (r.height - h) / 3);
  }

  function updateZoomTransform() {
    boundPan();
    if (isPdfMode()) {
      if (nativePdfFrame) {
        nativePdfFrame.style.transformOrigin = 'center center';
        nativePdfFrame.style.transform = pdfRenderMode === 'native' ? 'none' : `translate(-50%, -50%) translate(${panX}px, ${panY}px) rotate(${rotation}deg) scale(${currentZoom / (pdfBaseScale || 1)})`;
      }
      if (pdfCanvas) {
        pdfCanvas.style.left = '50%';
        pdfCanvas.style.top = '50%';
        pdfCanvas.style.transformOrigin = 'center center';
        pdfCanvas.style.transform = `translate(-50%, -50%) translate(${panX}px, ${panY}px)`;
      }
      if(zoomTxt) zoomTxt.textContent = `${Math.round((currentZoom / (pdfBaseScale || 1)) * 100)}%`;
      return;
    }
    if (isFileMode()) return;
    if(!zoomImg) return;
    zoomImg.style.left = '50%';
    zoomImg.style.top = '50%';
    zoomImg.style.transformOrigin = 'center center';
    zoomImg.style.transform = `translate(-50%, -50%) translate(${panX}px, ${panY}px) rotate(${rotation}deg) scale(${currentZoom})`;
    if(zoomTxt) zoomTxt.textContent = `${Math.round((currentZoom / fitZoom) * 100)}%`;
  }

  function resetViewer(){
    if (isPdfMode()) { currentZoom = pdfBaseScale || 1; panX = 0; panY = 0; if (pdfRenderMode === 'native') updateZoomTransform(); else renderPdfPage(true); return; }
    fitZoom = computeFitZoom();
    currentZoom = fitZoom;
    panX = 0; panY = 0;
    updateZoomTransform();
  }

  function zoomAt(nextZoom, cx = null, cy = null){
    if(!bArea) return;
    const old = currentZoom;
    const base = isPdfMode() ? (pdfBaseScale || 1) : fitZoom;
    const minZ = base * 0.75;
    const maxZ = Math.max(base * 7, 3);
    currentZoom = clamp(nextZoom, minZ, maxZ);
    if (cx !== null && cy !== null && old > 0) {
      const r = viewerRect();
      const dx = cx - (r.left + r.width / 2) - panX;
      const dy = cy - (r.top + r.height / 2) - panY;
      const ratio = currentZoom / old;
      panX -= dx * (ratio - 1);
      panY -= dy * (ratio - 1);
    }
    if (isPdfMode()) { if (pdfRenderMode === 'native') updateZoomTransform(); else schedulePdfRender(); } else updateZoomTransform();
  }

  document.getElementById('zoomInBtn')?.addEventListener('click', () => zoomAt(currentZoom * 1.18));
  document.getElementById('zoomOutBtn')?.addEventListener('click', () => zoomAt(currentZoom / 1.18));
  document.getElementById('zoomResetBtn')?.addEventListener('click', resetViewer);

  bArea?.addEventListener('wheel', e => {
    if (pdfRenderMode === 'native' || isFileMode()) return;
    e.preventDefault();
    const factor = e.deltaY < 0 ? 1.14 : 1 / 1.14;
    zoomAt(currentZoom * factor, e.clientX, e.clientY);
  }, {passive:false});

  bArea?.addEventListener('pointerdown', e => {
    if(pdfRenderMode === 'native' || isFileMode()) return;
    if(!zoomImg && !isPdfMode()) return;
    e.preventDefault();
    bArea.setPointerCapture?.(e.pointerId);
    pointers.set(e.pointerId, {x:e.clientX, y:e.clientY});
    bArea.classList.add('dragging');

    const now = Date.now();
    if (pointers.size === 1 && now - lastTapAt < 280) {
      const baseZoom = isPdfMode() ? (pdfBaseScale || 1) : fitZoom;
      const targetZoom = currentZoom > baseZoom * 1.45 ? baseZoom : baseZoom * 2.2;
      zoomAt(targetZoom, e.clientX, e.clientY);
      lastTapAt = 0;
    } else if (pointers.size === 1) {
      lastTapAt = now;
    }

    if (pointers.size === 1) {
      dragStart = {x:e.clientX, y:e.clientY, panX, panY};
      pinchStart = null;
    } else if (pointers.size === 2) {
      const pts = [...pointers.values()];
      const dist = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
      pinchStart = {dist, zoom:currentZoom, panX, panY, cx:(pts[0].x+pts[1].x)/2, cy:(pts[0].y+pts[1].y)/2};
    }
  });

  bArea?.addEventListener('pointermove', e => {
    if(pdfRenderMode === 'native' || isFileMode()) return;
    if(!pointers.has(e.pointerId) || (!zoomImg && !isPdfMode())) return;
    e.preventDefault();
    pointers.set(e.pointerId, {x:e.clientX, y:e.clientY});
    if (pointers.size === 1 && dragStart) {
      panX = dragStart.panX + (e.clientX - dragStart.x);
      panY = dragStart.panY + (e.clientY - dragStart.y);
      updateZoomTransform();
    } else if (pointers.size >= 2 && pinchStart) {
      const pts = [...pointers.values()].slice(0,2);
      const dist = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
      const cx = (pts[0].x + pts[1].x) / 2;
      const cy = (pts[0].y + pts[1].y) / 2;
      panX = pinchStart.panX + (cx - pinchStart.cx);
      panY = pinchStart.panY + (cy - pinchStart.cy);
      zoomAt(pinchStart.zoom * (dist / Math.max(1, pinchStart.dist)), cx, cy);
    }
  });

  function endPointer(e){
    pointers.delete(e.pointerId);
    if (pointers.size === 0) {
      dragStart = null; pinchStart = null;
      bArea?.classList.remove('dragging');
    } else if (pointers.size === 1) {
      const p = [...pointers.values()][0];
      dragStart = {x:p.x, y:p.y, panX, panY};
      pinchStart = null;
    }
  }
  bArea?.addEventListener('pointerup', endPointer);
  bArea?.addEventListener('pointercancel', endPointer);
  bArea?.addEventListener('pointerleave', e => { if (e.pointerType === 'mouse') endPointer(e); });

  zoomImg?.addEventListener('load', resetViewer);
  window.addEventListener('resize', () => setTimeout(resetViewer, 80));
  if (zoomImg?.complete) setTimeout(resetViewer, 40);

  // Multi-Page Sheet Navigator
  const sheetSelect = document.getElementById('sheetPageSelect');
  const nextSheet   = document.getElementById('nextSheetPageBtn');
  const prevSheet   = document.getElementById('prevSheetPageBtn');
  const badgeTitle  = document.getElementById('bookletTitleBadge');

  function goToSheet(index) {
    if(!sheetSelect) return;
    index = Math.max(0, Math.min(index, sheetSelect.options.length - 1));
    sheetSelect.value = String(index);
    const opt = sheetSelect.options[index]; if(!opt) return;
    const type = opt.dataset.type || (String(opt.dataset.src||'').toLowerCase().includes('.pdf') ? 'pdf' : 'image');
    panX = 0; panY = 0;
    setViewerMode(type, opt.dataset.src || '');
    if(nextSheet) nextSheet.disabled = (index === sheetSelect.options.length - 1);
    if(prevSheet) prevSheet.disabled = (index === 0);
    if(badgeTitle) badgeTitle.innerHTML = `دفترچه‌ی سوالات (ص ${faNum(index+1)} از ${faNum(sheetSelect.options.length)}${type==='pdf'?' · PDF':''})`;
  }

  sheetSelect?.addEventListener('change', e => goToSheet(parseInt(e.target.value)));
  nextSheet?.addEventListener('click', () => goToSheet(parseInt(sheetSelect.value || '0') + 1));
  prevSheet?.addEventListener('click', () => goToSheet(parseInt(sheetSelect.value || '0') - 1));
  function openPdfInBrowserTab(){
    const src = pdfWrap?.dataset.src || pdfSrc;
    if (!src) return false;
    window.open(src, '_blank', 'noopener');
    return true;
  }
  pdfPrevPage?.addEventListener('click', () => { if(pdfDoc && pdfPageNo > 1){ pdfPageNo--; renderPdfPage(true); } });
  pdfNextPage?.addEventListener('click', () => { if(pdfDoc && pdfPageNo < pdfDoc.numPages){ pdfPageNo++; renderPdfPage(true); } });
  pdfRetry?.addEventListener('click', () => { const src = pdfWrap?.dataset.src || pdfSrc; if(src){ pdfSrc=''; pdfRenderMode='canvas'; loadPdf(src); } });
  pdfCompatRetry?.addEventListener('click', () => { if (window.matchMedia('(max-width: 760px)').matches) openPdfInBrowserTab(); else setNativePdfMode(true); });
  pdfCompatBtn?.addEventListener('click', () => {
    if (!isPdfMode() || window.matchMedia('(max-width: 760px)').matches) { openPdfInBrowserTab(); return; }
    setNativePdfMode(pdfRenderMode !== 'native');
  });
  document.getElementById('rotateLeftBtn')?.addEventListener('click', () => { rotation = (rotation + 270) % 360; if (isPdfMode() && pdfRenderMode === 'canvas') renderPdfPage(true); else resetViewer(); });
  document.getElementById('rotateRightBtn')?.addEventListener('click', () => { rotation = (rotation + 90) % 360; if (isPdfMode() && pdfRenderMode === 'canvas') renderPdfPage(true); else resetViewer(); });
  if (sheetSelect) goToSheet(parseInt(sheetSelect.value || '0'));
  else if (bArea) setViewerMode(bArea.dataset.type || 'image', pdfWrap?.dataset.src || zoomImg?.getAttribute('src') || '');

  function setToolsCollapsed(collapsed){
    viewerPanel?.classList.toggle('booklet-tools-collapsed', !!collapsed);
    if (toolsToggle) {
      toolsToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      toolsToggle.innerHTML = collapsed
        ? '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/><circle cx="12" cy="12" r="3"/><path d="M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1"/></svg><span>ابزارها</span>'
        : '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg><span>بستن ابزار</span>';
    }
  }
  toolsToggle?.addEventListener('click', () => setToolsCollapsed(!viewerPanel?.classList.contains('booklet-tools-collapsed')));
  if (window.matchMedia('(max-width: 760px)').matches) {
    setTimeout(() => setToolsCollapsed(true), 4200);
    bookletToolbar?.addEventListener('click', () => { clearTimeout(window.__madarToolsTimer); window.__madarToolsTimer = setTimeout(() => setToolsCollapsed(true), 5000); });
  }

  // Mobile-first switcher: on phones the booklet and answer sheet become two clean tabs.
  const samuraiLayout = document.querySelector('.exam-samurai-layout');
  if (samuraiLayout && !document.querySelector('.mobile-exam-switch')) {
    document.body.classList.add('samurai-exam-mobile');
    env.classList.add('mobile-booklet-mode');
    const sw = document.createElement('div');
    sw.className = 'mobile-exam-switch';
    sw.innerHTML = `<button type="button" data-mobile-exam-mode="booklet" class="active">📄 دفترچه سوالات</button><button type="button" data-mobile-exam-mode="answer">🎯 پاسخ‌برگ <span id="mobileAnsweredMini">۰/${faNum(total)}</span></button>`;
    document.querySelector('.exam-bar')?.insertAdjacentElement('afterend', sw);
    sw.addEventListener('click', e => {
      const btn = e.target.closest('[data-mobile-exam-mode]'); if (!btn) return;
      const mode = btn.dataset.mobileExamMode;
      env.classList.toggle('mobile-booklet-mode', mode === 'booklet');
      env.classList.toggle('mobile-answer-mode', mode === 'answer');
      sw.querySelectorAll('button').forEach(b => b.classList.toggle('active', b === btn));
      setTimeout(() => {
        if (mode === 'booklet') resetViewer();
        else document.querySelector('.bubble-rows-container')?.scrollTo({top:0, behavior:'smooth'});
      }, 80);
    });
  }

  // Dual Bubble Sheet interaction
  // skip cancelled rows
  function isCancelledRow(el){
    return el?.closest('.cancelled-question, .bubble-row-item[data-cancelled="1"]');
  }
  env.addEventListener('click', e => {
    const bOpt = e.target.closest('.bubble-opt-btn');
    if (bOpt) {
      const parent = bOpt.closest('.bubble-row-item');
      if(isCancelledRow(parent)){ toast('این سوال خط خورده و قابل پاسخ نیست','info',1800); return; }
      const qid = parent.dataset.q;
      const val = parseInt(bOpt.dataset.opt);
      parent.querySelectorAll('.bubble-opt-btn').forEach(o => o.classList.remove('selected'));
      bOpt.classList.add('selected');
      const clrBtn = parent.querySelector('[data-clear], .bubble-clear-btn');
      if (clrBtn) clrBtn.classList.remove('hidden');
      setAnswer(qid, val);
      return;
    }

    const bClr = e.target.closest('[data-clear], .bubble-clear-btn');
    if (bClr) {
      const parent = bClr.closest('.bubble-row-item');
      const qid = parent.dataset.q;
      parent.querySelectorAll('.bubble-opt-btn').forEach(o => o.classList.remove('selected'));
      bClr.classList.add('hidden');
      setAnswer(qid, null);
      return;
    }

    const bBkm = e.target.closest('[data-flag], .bubble-flag-btn');
    if (bBkm) {
      const parent = bBkm.closest('.bubble-row-item');
      const qid = parent.dataset.q;
      const now = !(state[qid]?.f);
      bBkm.classList.toggle('active', now);
      bBkm.style.color = now ? '#000' : 'var(--text-2)';
      bBkm.style.background = now ? 'var(--gold)' : 'var(--surface-1)';
      setFlag(qid, now);
      return;
    }
  });

  const smartBtn = document.getElementById('finishSmartExamBtn');
  smartBtn?.addEventListener('click', () => {
    openSubmit();
  });


  // ===== More menu toggle (top bar) =====
  const moreBtn = document.getElementById('examMoreBtn');
  const moreMenu = document.getElementById('examMoreMenu');
  if (moreBtn && moreMenu) {
    moreBtn.addEventListener('click', e => {
      e.stopPropagation();
      moreMenu.hidden = !moreMenu.hidden;
    });
    document.addEventListener('click', e => {
      if (!moreMenu.hidden && !moreMenu.contains(e.target) && e.target !== moreBtn && !moreBtn.contains(e.target)) {
        moreMenu.hidden = true;
      }
    });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && !moreMenu.hidden) moreMenu.hidden = true;
    });
  }

  /* =================================================================
     CLEAN DESKTOP/MOBILE ONBOARDING TOUR
     ================================================================= */
  const tourOverlay = document.getElementById('examOnboardingTourOverlay');
  const skipTourBtn = document.getElementById('skipTourBtn');
  const prevTourBtn = document.getElementById('prevTourStepBtn');
  const nextTourBtn = document.getElementById('nextTourStepBtn');
  const replayTourBtn = document.getElementById('replayExamTour');
  const tourTitle   = document.getElementById('tourTitle');
  const tourText    = document.getElementById('tourText');
  const tourIco     = document.getElementById('tourIco');
  const tourDotsBox = document.getElementById('tourDots') || document.querySelector('.tour-dots');
  const isSamurai = !!document.querySelector('.exam-samurai-layout');
  const isPhone = () => window.matchMedia('(max-width: 760px)').matches;

  const desktopTourSteps = [
    {
      title:'دفترچه سوالات',
      ico:'📄',
      text:'سوالات آزمون در این بخش نمایش داده می‌شود. برای دیدن قسمت‌های مختلف برگه، آن را با موس بکشید. برای بزرگ‌تر یا کوچک‌تر کردن برگه از دکمه‌های بزرگ‌نمایی و کوچک‌نمایی استفاده کنید.',
      targetSelector:'.booklet-viewer-panel',
      mode:'booklet'
    },
    {
      title:'نمایش فایل با مرورگر',
      ico:'🗎',
      text:'اگر نوشته‌ها، تصویر یا PDF درست نمایش داده نشد، دکمه «نمایش با مرورگر» را بزنید. با این کار فایل با نمایشگر خود مرورگر باز می‌شود.',
      targetSelector:'#pdfCompatBtn',
      mode:'booklet'
    },
    {
      title:'پاسخ‌برگ',
      ico:'🎯',
      text:'برای پاسخ دادن، در ردیف هر سوال روی گزینه ۱، ۲، ۳ یا ۴ بزنید. پاسخ شما به صورت خودکار ذخیره می‌شود. اگر درباره سوالی مطمئن نیستید، می‌توانید علامت پرچم را بزنید.',
      targetSelector:'.bubble-sheet-panel',
      mode:'answer'
    },
    {
      title:'ثبت نهایی آزمون',
      ico:'✅',
      text:'پس از پایان پاسخ‌دادن، دکمه «ثبت» را بزنید. قبل از ثبت نهایی، تعداد پاسخ‌های داده‌شده، سوال‌های بی‌پاسخ و سوال‌های علامت‌دار نمایش داده می‌شود.',
      targetSelector:'.exam-bar',
      mode:'answer'
    }
  ];
  const mobileTourSteps = [
    {
      title:'انتخاب بخش موردنظر',
      ico:'↔️',
      text:'در بالای صفحه دو دکمه وجود دارد: «دفترچه سوالات» برای دیدن سوال‌ها و «پاسخ‌برگ» برای وارد کردن جواب‌ها. هر زمان لازم بود، با این دو دکمه بین سوالات و پاسخ‌برگ جابه‌جا شوید.',
      targetSelector:'.mobile-exam-switch',
      mode:'booklet'
    },
    {
      title:'دیدن سوالات',
      ico:'📄',
      text:'برای دیدن قسمت‌های مختلف برگه، انگشت خود را روی برگه بگذارید و بکشید. برای بزرگ‌نمایی یا کوچک‌نمایی می‌توانید از دو انگشت یا دکمه‌های ابزار استفاده کنید.',
      targetSelector:'.booklet-viewer-panel',
      mode:'booklet'
    },
    {
      title:'اگر فایل درست نمایش داده نشد',
      ico:'🗎',
      text:'اگر نوشته‌ها، تصویر یا PDF به‌هم‌ریخته بود، دکمه «نمایش با مرورگر» را بزنید. در موبایل، فایل در صفحه جدید مرورگر باز می‌شود تا بتوانید آن را راحت‌تر ببینید.',
      targetSelector:'#pdfCompatBtn',
      mode:'booklet'
    },
    {
      title:'وارد کردن پاسخ‌ها',
      ico:'🎯',
      text:'دکمه «پاسخ‌برگ» را بزنید. سپس در ردیف هر سوال، گزینه موردنظر خود را انتخاب کنید. برای پاک کردن پاسخ، از دکمه ضربدر همان ردیف استفاده کنید.',
      targetSelector:'.bubble-sheet-panel',
      mode:'answer'
    },
    {
      title:'ثبت نهایی',
      ico:'✅',
      text:'وقتی پاسخ‌دادن تمام شد، دکمه «ثبت» را بزنید. پس از تأیید نهایی، آزمون ثبت می‌شود و امکان تغییر پاسخ‌ها وجود ندارد.',
      targetSelector:'.exam-bar',
      mode:'answer'
    }
  ];
  const standardDesktopTourSteps = [
    {
      title:'صورت سوال',
      ico:'📝',
      text:'هر سوال به صورت جداگانه نمایش داده می‌شود. متن سوال را بخوانید و گزینه موردنظر خود را انتخاب کنید. پاسخ شما به صورت خودکار ذخیره می‌شود.',
      targetSelector:'.exam-q.active'
    },
    {
      title:'حرکت بین سوال‌ها',
      ico:'➡️',
      text:'برای رفتن به سوال بعدی یا قبلی، از دکمه‌های پایین صفحه استفاده کنید. دکمه فهرست سوالات، وضعیت سوال‌های پاسخ‌داده، بی‌پاسخ و علامت‌دار را نشان می‌دهد.',
      targetSelector:'.exam-nav'
    },
    {
      title:'ثبت نهایی آزمون',
      ico:'✅',
      text:'پس از پایان پاسخ‌دادن، دکمه ثبت را بزنید. قبل از ثبت نهایی، خلاصه وضعیت پاسخ‌ها نمایش داده می‌شود.',
      targetSelector:'.exam-bar'
    }
  ];
  const standardMobileTourSteps = [
    {
      title:'خواندن سوال',
      ico:'📝',
      text:'هر بار یک سوال نمایش داده می‌شود. سوال را بخوانید و گزینه موردنظر خود را لمس کنید. پاسخ به صورت خودکار ذخیره می‌شود.',
      targetSelector:'.exam-q.active'
    },
    {
      title:'رفتن به سوال‌های دیگر',
      ico:'➡️',
      text:'برای دیدن سوال بعدی یا قبلی، از دکمه‌های پایین صفحه استفاده کنید. با دکمه فهرست سوالات می‌توانید وضعیت همه سوال‌ها را ببینید.',
      targetSelector:'.exam-nav'
    },
    {
      title:'ثبت نهایی',
      ico:'✅',
      text:'وقتی پاسخ‌دادن تمام شد، دکمه ثبت را بزنید. قبل از ثبت نهایی، خلاصه پاسخ‌ها نمایش داده می‌شود.',
      targetSelector:'.exam-bar'
    }
  ];

  let currTourStep = 0;
  let activeTourSteps = desktopTourSteps;

  function setMobileMode(mode){
    if (!isSamurai || !isPhone()) return;
    env.classList.toggle('mobile-booklet-mode', mode === 'booklet');
    env.classList.toggle('mobile-answer-mode', mode === 'answer');
    document.querySelectorAll('[data-mobile-exam-mode]').forEach(b => b.classList.toggle('active', b.dataset.mobileExamMode === mode));
    setTimeout(() => { if (mode === 'booklet') resetViewer(); }, 70);
  }
  function clearTourHighlight(){
    document.querySelectorAll('.booklet-viewer-panel, .bubble-sheet-panel, .exam-bar, .mobile-exam-switch, .exam-q, .exam-nav').forEach(el => el.classList.remove('exam-tour-highlight'));
  }
  function buildTourDots(){
    if (!tourDotsBox) return;
    tourDotsBox.innerHTML = activeTourSteps.map((_,i)=>`<button type="button" class="tour-dot" data-step="${i}" aria-label="گام ${i+1}"></button>`).join('');
    tourDotsBox.querySelectorAll('.tour-dot').forEach(dot => dot.addEventListener('click', () => runTourStep(parseInt(dot.dataset.step || '0'))));
  }
  function runTourStep(stepIndex) {
    currTourStep = Math.max(0, Math.min(stepIndex, activeTourSteps.length - 1));
    const s = activeTourSteps[currTourStep]; if(!s) return;
    setMobileMode(s.mode || 'booklet');
    clearTourHighlight();
    setTimeout(() => document.querySelector(s.targetSelector)?.classList.add('exam-tour-highlight'), 90);
    if(prevTourBtn) prevTourBtn.disabled = currTourStep === 0;
    if(nextTourBtn) nextTourBtn.textContent = currTourStep === activeTourSteps.length - 1 ? 'شروع آزمون' : 'بعدی';
    if(tourIco) tourIco.textContent = s.ico;
    if(tourTitle) tourTitle.textContent = s.title;
    if(tourText) tourText.textContent = s.text;
    tourDotsBox?.querySelectorAll('.tour-dot').forEach(d => d.classList.toggle('active', parseInt(d.dataset.step || '0') === currTourStep));
  }
  function openTour(force=false){
    if (!tourOverlay) return;
    activeTourSteps = isSamurai ? (isPhone() ? mobileTourSteps : desktopTourSteps) : (isPhone() ? standardMobileTourSteps : standardDesktopTourSteps);
    buildTourDots();
    tourOverlay.classList.remove('hidden');
    runTourStep(0);
    if (force) localStorage.removeItem('madar_exam_tour_shown_v2_' + (window.EXAM_ID_PARAM || 0));
  }
  function finishTour() {
    if(tourOverlay) tourOverlay.classList.add('hidden');
    clearTourHighlight();
    localStorage.setItem('madar_exam_tour_shown_v2_' + (window.EXAM_ID_PARAM || 0), '1');
  }

  skipTourBtn?.addEventListener('click', finishTour);
  replayTourBtn?.addEventListener('click', () => openTour(true));
  nextTourBtn?.addEventListener('click', () => currTourStep === activeTourSteps.length - 1 ? finishTour() : runTourStep(currTourStep + 1));
  prevTourBtn?.addEventListener('click', () => runTourStep(currTourStep - 1));
  tourOverlay?.addEventListener('click', e => { if(e.target === tourOverlay) finishTour(); });
  window.addEventListener('resize', () => { if(tourOverlay && !tourOverlay.classList.contains('hidden')) openTour(false); });
  window.addEventListener('load', () => {
    if (!localStorage.getItem('madar_exam_tour_shown_v2_' + (window.EXAM_ID_PARAM || 0))) openTour(false);
  });

  /* =================================================================
     STANDARD QUESTION ENGINE
     ================================================================= */
  function show(i){
    if(i<0||i>=qs.length||!qs.length) return;
    qs[cur].classList.remove('active');
    cur=i; qs[cur].classList.add('active');
    const pBtn = document.getElementById('prevBtn');
    if(pBtn) pBtn.disabled = cur===0;
    const nextBtn=document.getElementById('nextBtn');
    if(nextBtn) {
      nextBtn.innerHTML = cur===qs.length-1 ? 'پایان و ثبت ' + ICO.check : 'بعدی ' + ICO.chevronLeft;
    }
    window.scrollTo({top:0,behavior:'smooth'});
    updateGridCurrent();
  }
  document.getElementById('prevBtn')?.addEventListener('click',()=>show(cur-1));
  document.getElementById('nextBtn')?.addEventListener('click',()=>{
    if(cur===qs.length-1){ openSubmit(); } else show(cur+1);
  });

  env.addEventListener('click',(e)=>{
    if(document.querySelector('.exam-samurai-layout')) return;
    const opt=e.target.closest('.eq-opt');
    if(opt){
      const q=opt.closest('.exam-q'); const qid=q.dataset.q; const val=parseInt(opt.dataset.opt);
      q.querySelectorAll('.eq-opt').forEach(o=>o.classList.remove('selected'));
      opt.classList.add('selected');
      const clr = q.querySelector('[data-clear]');
      if(clr) clr.classList.remove('hidden');
      setAnswer(qid,val);
      return;
    }
    const clr=e.target.closest('.exam-q [data-clear]');
    if(clr){
      const q=clr.closest('.exam-q'); const qid=q.dataset.q;
      q.querySelectorAll('.eq-opt').forEach(o=>o.classList.remove('selected'));
      clr.classList.add('hidden');
      setAnswer(qid,null);
      return;
    }
    const fl=e.target.closest('.exam-q [data-flag]');
    if(fl){
      const q=fl.closest('.exam-q'); const qid=q.dataset.q;
      const now=!(state[qid]?.f);
      fl.classList.toggle('on',now);
      setFlag(qid,now);
      return;
    }
  });

  function setAnswer(qid,val){
    // block cancelled questions
    const row = document.querySelector('.bubble-row-item[data-q="'+qid+'"], .exam-q[data-q="'+qid+'"]');
    if(row && (row.classList.contains('cancelled-question') || row.dataset.cancelled==='1')) return;
    state[qid]=state[qid]||{}; state[qid].s=val;
    pending.add(qid);
    saveOne(qid);
    refreshCounts(); updateGridCell(qid);
  }
  function setFlag(qid,on){
    state[qid]=state[qid]||{}; state[qid].f=on?1:0;
    pending.add(qid); saveOne(qid); updateGridCell(qid);
  }

  async function saveOne(qid){
    try{
      await api(API,{method:'POST',body:{action:'answer',attempt_id:attemptId,question_id:qid,selected_opt:state[qid]?.s??null}});
      if('f' in (state[qid]||{})) api(API,{method:'POST',body:{action:'flag',attempt_id:attemptId,question_id:qid,flagged:state[qid].f?'1':'0'}}).catch(()=>{});
      pending.delete(qid);
    }catch(e){
      if(e&&e.expired){ handleExpired(); }
    }
  }

  async function sync(){
    if(submitted) return;
    if(pending.size===0){
      try{ const d=await api(API,{method:'POST',body:{action:'sync',attempt_id:attemptId,answers:[]}}); if(d.expired) handleExpired(); else if(d.submitted){submitted=true;} else if(d.remain!=null) applyRemain(d.remain); }catch(e){}
      return;
    }
    const answers=[...pending].map(qid=>({q:parseInt(qid),s:state[qid]?.s??null,f:state[qid]?.f?1:0}));
    try{
      const d=await api(API,{method:'POST',body:{action:'sync',attempt_id:attemptId,answers}});
      if(d.expired){ handleExpired(); return; }
      if(d.submitted){ submitted=true; return; }
      pending.clear();
      if(d.remain!=null) applyRemain(d.remain);
    }catch(e){}
  }
  setInterval(sync,5000);

  function refreshCounts(){
    let answered=0; 
    document.querySelectorAll('.bubble-row-item').forEach(row => {
      const qid = row.dataset.q;
      if (state[qid]?.s) answered++;
    });
    if (answered === 0 && qs.length) {
      qs.forEach(q => { if(state[q.dataset.q]?.s) answered++; });
    }
    const ac = document.getElementById('answeredCount');
    if(ac) ac.textContent=faNum(answered);
    const mac = document.getElementById('mobileAnsweredMini');
    if(mac) mac.textContent = `${faNum(answered)}/${faNum(total)}`;
  }
  refreshCounts();

  const gridPanel=document.getElementById('qgridPanel');
  const gridOv=document.getElementById('qgridOverlay');
  function openGrid(){ gridPanel?.classList.add('open'); gridOv?.classList.add('open'); updateGridCurrent(); }
  function closeGrid(){ gridPanel?.classList.remove('open'); gridOv?.classList.remove('open'); }
  document.getElementById('gridToggle')?.addEventListener('click',openGrid);
  document.getElementById('gridClose')?.addEventListener('click',closeGrid);
  gridOv?.addEventListener('click',closeGrid);
  document.querySelectorAll('[data-goto]').forEach(c=>c.addEventListener('click',()=>{ show(parseInt(c.dataset.goto)); closeGrid(); }));
  
  function updateGridCell(qid){
    const cell=document.querySelector(`.qg-cell[data-q="${qid}"]`); if(!cell) return;
    cell.classList.toggle('answered', !!state[qid]?.s);
    cell.classList.toggle('flagged', !!state[qid]?.f);
  }

  function updateGridCurrent(){
    if(!qs[cur]) return;
    const qid=qs[cur].dataset.q;
    document.querySelectorAll('.qg-cell').forEach(c=>c.classList.toggle('current', c.dataset.q===qid));
  }

  const timer=document.getElementById('examTimer');
  let remain = timer ? parseInt(timer.dataset.remain) : null;
  function fmt(s){ const m=Math.floor(s/60), ss=s%60; return faNum(String(m).padStart(2,'0'))+':'+faNum(String(ss).padStart(2,'0')); }
  function applyRemain(r){ if(r==null) return; remain=r; renderTimer(); }
  function renderTimer(){
    if(remain==null||!timer) return;
    document.getElementById('timerText').textContent=fmt(Math.max(0,remain));
    timer.classList.toggle('warning', remain<=300 && remain>60);
    timer.classList.toggle('danger', remain<=60);
  }
  if(timer){
    renderTimer();
    setInterval(()=>{ if(remain==null||submitted) return; remain--; if(remain<=0){ remain=0; renderTimer(); autoSubmit('زمان آزمون تمام شد'); } else renderTimer(); },1000);
  }

  function openSubmit(){
    let answered=0,flagged=0; 
    
    const dualRows = document.querySelectorAll('.bubble-row-item');
    if (dualRows.length) {
      dualRows.forEach(row => {
        const qid = row.dataset.q;
        if(state[qid]?.s) answered++;
        if(state[qid]?.f) flagged++;
      });
    } else {
      qs.forEach(q=>{ const st=state[q.dataset.q]; if(st?.s)answered++; if(st?.f)flagged++; });
    }

    const ssa = document.getElementById('ssAnswered');
    const ssb = document.getElementById('ssBlank');
    const ssf = document.getElementById('ssFlagged');
    if(ssa) ssa.textContent=faNum(answered);
    if(ssb) ssb.textContent=faNum(total-answered);
    if(ssf) ssf.textContent=faNum(flagged);
    openModal('submitModal');
  }

  document.getElementById('finishBtn')?.addEventListener('click',openSubmit);
  document.getElementById('finishBtn2')?.addEventListener('click',()=>{ closeGrid(); openSubmit(); });
  document.getElementById('confirmSubmit')?.addEventListener('click',()=>doSubmit());

  async function doSubmit(retryCount=0){
    if(submitted) return;
    const btn=document.getElementById('confirmSubmit');
    if(btn) { btn.disabled=true; btn.innerHTML='<span class="spinner"></span> در حال ثبت…'; }
    
    let answers = [];
    const dualRows = document.querySelectorAll('.bubble-row-item:not(.cancelled-question):not([data-cancelled="1"])');
    if (dualRows.length) {
      dualRows.forEach(row => {
        const qid = row.dataset.q;
        answers.push({ q: parseInt(qid), s: state[qid]?.s ?? null, f: state[qid]?.f ? 1 : 0 });
      });
    } else {
      answers = qs.filter(q => !q.classList.contains('cancelled-question') && q.dataset.cancelled!=='1')
        .map(q => ({ q: parseInt(q.dataset.q), s: state[q.dataset.q]?.s ?? null, f: state[q.dataset.q]?.f ? 1 : 0 }));
    }

    try{
      const d=await api(API,{method:'POST',body:{action:'submit',attempt_id:attemptId,answers}});
      if(!d || d.ok===false) throw d || {error:'پاسخ نامعتبر'};
      submitted=true;
      window.removeEventListener('beforeunload',warnLeave);
      if(d.redirect){ location.href=d.redirect; }
      else { location.href = 'exam_result.php?attempt='+attemptId; }
    }catch(e){
      console.error('[Exam submit]', e);
      // auto retry once on network error
      if(retryCount < 1 && (!e || !e.error || String(e.error).includes('ارتباط'))){
        toast('در حال تلاش مجدد…','info',1800);
        setTimeout(()=>{ if(btn){ btn.disabled=false; } doSubmit(retryCount+1); }, 1200);
        return;
      }
      if(btn) { btn.disabled=false; btn.innerHTML='✓ ثبت نهایی و مشاهده کارنامه'; }
      const msg = (e && (e.error||e.message)) || 'خطا در ثبت نهایی';
      toast(msg+' — دوباره تلاش کنید','error', 5000);
    }
  }

  async function autoSubmit(reason){
    if(submitted) return;
    toast(reason+' — در حال ثبت نهایی خودکار…','info',2500);
    await doSubmit();
  }

  function handleExpired(){ 
    if(!submitted){ 
      submitted=true; toast('زمان آزمون به پایان رسید','info'); 
      setTimeout(()=>location.href=window.API_EXAM_TAKE.replace('api/exam_take.php','student/exam_result.php?attempt='+attemptId),800); 
    } 
  }

  function warnLeave(e){ if(!submitted){ e.preventDefault(); e.returnValue=''; } }
  window.addEventListener('beforeunload',warnLeave);

  document.addEventListener('keydown',(e)=>{
    if(document.querySelector('.modal-backdrop.open') || document.querySelector('.exam-samurai-layout')) return;
    if(e.key==='ArrowLeft') show(cur+1);
    else if(e.key==='ArrowRight') show(cur-1);
    else if(['1','2','3','4'].includes(e.key)){ const o=qs[cur]?.querySelector(`.eq-opt[data-opt="${e.key}"]`); if(o) o.click(); }
  });

  show(0);



})();
