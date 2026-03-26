/**
 * Arctic Wolves PWA Service Worker
 * Provides offline caching, push notifications, and background sync.
 */

const CACHE_VERSION = 'aw-pwa-v5';
const STATIC_ASSETS = [
  '/index.php',
  '/pwa.php',
  '/pwa_tablet.php',
  '/pwa_login.php',
  '/gameplan_tv.php',
  '/css/pwa.css',
  '/css/pwa-tablet.css',
  '/css/gameplan-tv.css',
  '/css/style-guide.css',
  '/css/components.css',
  '/manifest.json',
  '/manifest-gameplan-tv.json',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
  'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap'
];

// Install: cache static assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => {
      return cache.addAll(STATIC_ASSETS).catch(() => {
        // Non-critical: some assets may fail on first install
      });
    })
  );
  self.skipWaiting();
});

// Activate: clean old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k))
      );
    })
  );
  self.clients.claim();
});

// Fetch: network-first for API/PHP, cache-first for static assets
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Never cache POST requests, API calls, or process_ handlers
  if (
    event.request.method !== 'GET' ||
    url.pathname.startsWith('/api/') ||
    url.pathname.startsWith('/api_') ||
    url.pathname.startsWith('/process_') ||
    url.pathname.includes('logout')
  ) {
    return;
  }

  // Cache-first for static assets (CSS, JS, fonts, images)
  if (
    url.pathname.match(/\.(css|js|woff2?|ttf|eot|png|jpg|jpeg|svg|gif|ico)$/) ||
    url.hostname === 'cdnjs.cloudflare.com' ||
    url.hostname === 'fonts.googleapis.com' ||
    url.hostname === 'fonts.gstatic.com'
  ) {
    event.respondWith(
      caches.match(event.request).then((cached) => {
        return cached || fetch(event.request).then((response) => {
          if (response.ok) {
            const clone = response.clone();
            caches.open(CACHE_VERSION).then((cache) => cache.put(event.request, clone));
          }
          return response;
        });
      })
    );
    return;
  }

  // Network-first for PHP pages (always get fresh content when online)
  event.respondWith(
    fetch(event.request)
      .then((response) => {
        if (response.ok && url.origin === self.location.origin) {
          const clone = response.clone();
          caches.open(CACHE_VERSION).then((cache) => cache.put(event.request, clone));
        }
        return response;
      })
      .catch(() => {
        return caches.match(event.request).then((cached) => {
          return cached || new Response(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title>' +
            '<style>body{background:#0A0A0F;color:#fff;font-family:Inter,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center}' +
            '.offline{padding:40px}.offline h1{color:#6B46C1;font-size:24px;margin-bottom:16px}.offline p{color:#A8A8B8;font-size:14px}</style></head>' +
            '<body><div class="offline"><h1>You\'re Offline</h1><p>Please check your internet connection and try again.</p>' +
            '<button onclick="location.reload()" style="margin-top:20px;padding:12px 32px;background:#6B46C1;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer">Retry</button></div></body></html>',
            { headers: { 'Content-Type': 'text/html' } }
          );
        });
      })
  );
});

// Push notifications
self.addEventListener('push', (event) => {
  let data = { title: 'Arctic Wolves', body: 'You have a new notification', icon: '/assets/pwa/icon-192.png' };

  if (event.data) {
    try {
      data = Object.assign(data, event.data.json());
    } catch (e) {
      data.body = event.data.text();
    }
  }

  event.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: data.icon || '/assets/pwa/icon-192.png',
      badge: '/assets/pwa/icon-192.png',
      tag: data.tag || 'aw-notification',
      data: data.url ? { url: data.url } : {},
      vibrate: [200, 100, 200]
    })
  );
});

// Notification click: open the app
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data?.url || '/pwa.php';

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
      for (const client of clients) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          client.navigate(url);
          return client.focus();
        }
      }
      return self.clients.openWindow(url);
    })
  );
});
