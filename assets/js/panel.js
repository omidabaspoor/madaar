/* =============== Panel interactions =============== */
(() => {
  'use strict';
  const sb = document.getElementById('sidebar');
  const ov = document.querySelector('.sidebar-overlay');
  const open = () => { sb?.classList.add('open'); ov?.classList.add('open'); };
  const close = () => { sb?.classList.remove('open'); ov?.classList.remove('open'); };
  document.querySelector('[data-side-open]')?.addEventListener('click', open);
  document.querySelector('[data-side-close]')?.addEventListener('click', close);
  ov?.addEventListener('click', close);

  // bars animate
  document.querySelectorAll('.bar[data-h]').forEach(b => {
    requestAnimationFrame(() => setTimeout(() => { b.style.height = b.dataset.h + '%'; }, 100));
  });
  // rings
  document.querySelectorAll('.ring[data-p]').forEach(r => {
    requestAnimationFrame(() => setTimeout(() => { r.style.setProperty('--p', r.dataset.p); }, 120));
  });
  // progress bars
  document.querySelectorAll('.progress > span[data-w]').forEach(s => {
    requestAnimationFrame(() => setTimeout(() => { s.style.width = s.dataset.w + '%'; }, 120));
  });

  // notifications
  const nb = document.getElementById('notifBtn');
  const nl = document.getElementById('notifList');
  const base = document.querySelector('meta[name="csrf-token"]');
  nb?.addEventListener('click', async () => {
    openModal('notifModal');
    try {
      const d = await api(window.NOTIF_URL);
      if (!d.items || !d.items.length) {
        nl.innerHTML = '<div class="empty-state"><div class="es-ico"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/></svg></div>اعلان جدیدی نداری</div>';
        return;
      }
      nl.innerHTML = d.items.map(n => `
        <div class="flex gap-3" style="padding:13px 6px;border-bottom:1px solid var(--border-soft);align-items:flex-start">
          <span class="icon-tile ${n.is_read==0?'':'sage'}" style="width:38px;height:38px;border-radius:11px">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
          </span>
          <div style="flex:1">
            <div style="font-weight:700;font-size:.92rem">${n.title}</div>
            ${n.body?`<div style="font-size:.84rem;color:var(--text-3)">${n.body}</div>`:''}
            <div style="font-size:.74rem;color:var(--text-faint);margin-top:3px">${n.ago}</div>
          </div>
        </div>`).join('');
      // mark read
      api(window.NOTIF_READ_URL, { method:'POST' }).then(()=>{
        nb.querySelector('.dot')?.remove();
      }).catch(()=>{});
    } catch(e) {
      nl.innerHTML = '<div class="empty-state">خطا در دریافت اعلان‌ها</div>';
    }
  });
})();
