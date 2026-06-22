/* مَدار Service Worker — network-first برای CSS/JS و صفحات، cache-first برای عکس/فونت */
const VERSION = 'madar-v6';
const STATIC_CACHE = 'static-' + VERSION;
const PAGE_CACHE = 'pages-' + VERSION;

// مسیر پایه را از محل ثبت SW استخراج کن
const SCOPE = self.registration.scope.replace(/\/$/, '');
const OFFLINE_URL = SCOPE + '/offline.php';

const STATIC_ASSETS = [
  SCOPE + '/assets/css/app.css',
  SCOPE + '/assets/js/app.js',
  SCOPE + '/assets/img/logo.png',
  OFFLINE_URL,
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(STATIC_CACHE).then(c => c.addAll(STATIC_ASSETS).catch(()=>{})).then(()=>self.skipWaiting())
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(k => !k.endsWith(VERSION)).map(k => caches.delete(k))
    )).then(()=>self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  // درخواست‌های API: همیشه شبکه
  if (url.pathname.includes('/api/')) {
    e.respondWith(fetch(req).catch(()=>new Response(JSON.stringify({ok:false,error:'آفلاین'}),{headers:{'Content-Type':'application/json'}})));
    return;
  }

  // PDFهای بزرگ دفترچه و Range requestهای مرورگر PDF Viewer نباید وارد Cache API شوند.
  if (req.headers.has('range') || /\.pdf$/i.test(url.pathname)) {
    e.respondWith(fetch(req));
    return;
  }

  // CSS/JS: network-first (همیشه تازه وقتی آنلاین، کش وقتی آفلاین)
  if (/\.(css|js)$/.test(url.pathname)) {
    e.respondWith(
      fetch(req).then(res => {
        const copy = res.clone();
        caches.open(STATIC_CACHE).then(c => c.put(req, copy));
        return res;
      }).catch(() => caches.match(req))
    );
    return;
  }
  // عکس/فونت: cache-first (تغییر نمی‌کنند، سریع)
  if (/\.(svg|png|jpg|jpeg|webp|gif|woff2?|ttf)$/.test(url.pathname)) {
    e.respondWith(
      caches.match(req).then(cached => cached || fetch(req).then(res => {
        const copy = res.clone();
        caches.open(STATIC_CACHE).then(c => c.put(req, copy));
        return res;
      }).catch(()=>cached))
    );
    return;
  }

  // صفحات HTML: network-first با fallback آفلاین
  e.respondWith(
    fetch(req).then(res => {
      const copy = res.clone();
      caches.open(PAGE_CACHE).then(c => c.put(req, copy));
      return res;
    }).catch(() => caches.match(req).then(cached => cached || caches.match(OFFLINE_URL)))
  );
});

/* ---------- Notification Click Handler ---------- */
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = event.notification?.data?.url || SCOPE + '/student/dashboard.php';
  const url = target.startsWith('http') ? target : SCOPE + '/' + String(target).replace(/^\//,'');
  event.waitUntil(
    clients.matchAll({type:'window', includeUncontrolled:true}).then(list => {
      // اگه پنجره‌ای باز هست، فوکوس کن و برو به صفحه
      for (const c of list) {
        if ('focus' in c) {
          return c.focus().then(() => {
            if (c.navigate) return c.navigate(url);
            return c;
          });
        }
      }
      // وگرنه پنجره جدید باز کن
      return clients.openWindow(url);
    })
  );
});

/* ---------- Push Event (for future server-sent push) ---------- */
self.addEventListener('push', (event) => {
  let data = { title: 'مَدار', body: 'اعلان جدید', url: '/student/dashboard.php' };
  try {
    if (event.data) data = { ...data, ...event.data.json() };
  } catch (_) {}
  event.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: SCOPE + '/assets/icons/icon-192.png',
      badge: SCOPE + '/assets/icons/favicon-64.png',
      data: { url: data.url || '/student/dashboard.php' },
      requireInteraction: true,
      dir: 'rtl',
      lang: 'fa'
    })
  );
});

/* ---------- Periodic Background Sync (for supported browsers) ---------- */
self.addEventListener('periodicsync', (event) => {
  if (event.tag === 'madar-notif-check') {
    event.waitUntil(checkNotifications());
  }
});

async function checkNotifications() {
  try {
    const res = await fetch(SCOPE + '/api/notifications.php');
    const data = await res.json();
    const unread = (data.items || []).filter(n => !Number(n.is_read));
    if (unread.length > 0) {
      const latest = unread[0];
      const shownKey = 'sw_shown_' + latest.id;
      // Check if we already showed this
      const cache = await caches.open('madar-notif-state');
      const existing = await cache.match(shownKey);
      if (!existing) {
        await self.registration.showNotification(latest.title || 'مَدار', {
          body: latest.body || '',
          icon: SCOPE + '/assets/icons/icon-192.png',
          badge: SCOPE + '/assets/icons/favicon-64.png',
          data: { url: latest.link || '/student/dashboard.php' },
          tag: 'madar-bg-' + latest.id,
          dir: 'rtl', lang: 'fa'
        });
        await cache.put(shownKey, new Response('1'));
      }
    }
  } catch (_) {}
}
