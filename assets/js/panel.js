/* =============== Panel interactions + live heartbeat =============== */
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

  const nb = document.getElementById('notifBtn');
  const nl = document.getElementById('notifList');

  function setDot(container, on, cls='dot') {
    if (!container) return;
    let dot = container.querySelector(':scope > .' + cls) || container.querySelector('.' + cls);
    if (on && !dot) {
      dot = document.createElement('span');
      dot.className = cls;
      container.appendChild(dot);
    } else if (!on && dot) dot.remove();
  }
  function setBadge(link, count) {
    if (!link) return;
    let b = link.querySelector('.badge-count');
    if (count > 0 && !b) {
      b = document.createElement('span');
      b.className = 'badge-count';
      link.appendChild(b);
    }
    if (b) b.textContent = window.faNum ? window.faNum(count) : String(count);
    if (count <= 0 && b) b.remove();
  }
  function updateLiveBadges(d) {
    const notifCount = parseInt(d.notif_count || 0, 10);
    const msgCount = parseInt(d.msg_count || 0, 10);
    setDot(nb, notifCount > 0);

    document.querySelectorAll('a[href*="/messages.php"], a[href$="messages.php"], a[href*="admin/messages.php"], a[href*="student/messages.php"]').forEach(a => {
      if (a.classList.contains('tb-btn')) setDot(a, msgCount > 0);
      if (a.classList.contains('side-link')) setBadge(a, msgCount);
      if (a.classList.contains('bn-item')) {
        const ico = a.querySelector('.bn-ico') || a;
        let dot = ico.querySelector('.bn-dot');
        if (msgCount > 0 && !dot) { dot = document.createElement('span'); dot.className = 'bn-dot'; ico.appendChild(dot); }
        if (msgCount <= 0 && dot) dot.remove();
      }
    });

    if (d.student) {
      window.dispatchEvent(new CustomEvent('madar:student-live', {detail:d.student}));
      const streakEls = document.querySelectorAll('[data-live-streak]');
      streakEls.forEach(el => el.textContent = window.faNum ? window.faNum(d.student.streak || 0) : String(d.student.streak || 0));
    }
    if (d.advisor) {
      document.querySelectorAll('[data-live-advisor]').forEach(el => {
        const key = el.dataset.liveAdvisor;
        if (key && d.advisor[key] !== undefined) el.textContent = window.faNum ? window.faNum(d.advisor[key]) : String(d.advisor[key]);
      });
      window.dispatchEvent(new CustomEvent('madar:advisor-live', {detail:d.advisor}));
    }
    window.dispatchEvent(new CustomEvent('madar:live', {detail:d}));
  }

  // notifications drawer
  async function loadNotifications(markRead = false) {
    if (!nl) return;
    try {
      const d = await api(window.NOTIF_URL);
      if (!d.items || !d.items.length) {
        nl.innerHTML = '<div class="empty-state"><div class="es-ico"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/></svg></div>اعلان جدیدی نداری</div>';
      } else {
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
      }
      if (markRead) {
        api(window.NOTIF_READ_URL, { method:'POST' }).then(()=> setDot(nb, false)).catch(()=>{});
      }
    } catch(e) {
      nl.innerHTML = '<div class="empty-state">خطا در دریافت اعلان‌ها</div>';
    }
  }

  nb?.addEventListener('click', async () => {
    openModal('notifModal');
    await loadNotifications(true);
  });

  // Global live heartbeat — every 2 seconds, paused while tab is hidden.
  let liveBusy = false;
  let liveTimer = null;
  async function liveTick() {
    if (liveBusy || document.hidden || !window.PANEL_LIVE_URL) return;
    liveBusy = true;
    try {
      const d = await api(window.PANEL_LIVE_URL);
      if (d && d.ok) updateLiveBadges(d);
      if (document.getElementById('notifModal')?.classList.contains('open')) loadNotifications(false);
    } catch(_) {}
    finally { liveBusy = false; }
  }
  function startLive() {
    clearInterval(liveTimer);
    liveTick();
    liveTimer = setInterval(liveTick, 2000);
  }
  startLive();
  document.addEventListener('visibilitychange', () => { if (!document.hidden) liveTick(); });
})();
