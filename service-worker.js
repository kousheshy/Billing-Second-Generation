const CACHE_NAME = 'showbox-billing-v1.16.3';
const urlsToCache = [
  '/dashboard.php',
  '/index.html',
  '/dashboard.css',
  '/dashboard.js',
  '/sms-functions.js',
  '/manifest.json',
  '/assets/fonts/BYekan+.ttf',
  '/assets/icons/icon-192x192.png',
  '/assets/icons/icon-512x512.png'
];

// Install event - cache resources
self.addEventListener('install', event => {
  console.log('[Service Worker] Installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[Service Worker] Caching app shell');
        return cache.addAll(urlsToCache);
      })
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('[Service Worker] Activating...');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('[Service Worker] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip caching for PHP files (including dashboard.php with dynamic cache busting)
  // Always fetch PHP files from network to get fresh content
  if (url.pathname.endsWith('.php')) {
    // For API requests, pass through directly without service worker interference
    // This ensures credentials (cookies) are properly sent
    if (url.pathname.includes('/api/')) {
      return; // Let the browser handle API requests directly
    }
    event.respondWith(
      fetch(request, { credentials: 'same-origin' })
        .catch(() => {
          // For dashboard.php, try to return cached version if offline
          if (url.pathname.includes('dashboard.php')) {
            return caches.match('/dashboard.php');
          }
          return new Response(
            JSON.stringify({ error: 1, message: 'Offline - Cannot reach server' }),
            { headers: { 'Content-Type': 'application/json' } }
          );
        })
    );
    return;
  }

  // Use NETWORK-FIRST strategy for JS and CSS files (always get fresh versions)
  // Cache-first only for images and fonts
  const isJsOrCss = url.pathname.endsWith('.js') || url.pathname.endsWith('.css');

  if (isJsOrCss) {
    // Network-first strategy for JS/CSS
    event.respondWith(
      fetch(request)
        .then(response => {
          // Cache the new version
          if (response && response.status === 200) {
            const responseToCache = response.clone();
            caches.open(CACHE_NAME).then(cache => {
              cache.put(request, responseToCache);
            });
          }
          return response;
        })
        .catch(() => {
          // Fallback to cache if offline
          return caches.match(request);
        })
    );
  } else {
    // Cache-first strategy for images, fonts, etc.
    event.respondWith(
      caches.match(request)
        .then(response => {
          if (response) {
            return response;
          }
          return fetch(request).then(response => {
            if (response && response.status === 200) {
              const responseToCache = response.clone();
              caches.open(CACHE_NAME).then(cache => {
                cache.put(request, responseToCache);
              });
            }
            return response;
          });
        })
        .catch(() => {
          // Return offline page for HTML requests
          if (request.headers.get('accept') && request.headers.get('accept').includes('text/html')) {
            return caches.match('/index.html');
          }
        })
    );
  }
});

// Listen for messages from the main thread
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }

  // Handle expiry reminder notifications (v1.7.8)
  if (event.data && event.data.type === 'REMINDER_SENT') {
    const { sent, skipped, failed, total } = event.data.data;

    // Show notification if permission granted
    if (Notification.permission === 'granted') {
      const title = 'Expiry Reminders Sent';
      let body = `Sent: ${sent}, Skipped: ${skipped}`;

      if (failed > 0) {
        body += `, Failed: ${failed}`;
      }

      const options = {
        body: body,
        icon: '/assets/icons/icon-192x192.png',
        badge: '/assets/icons/icon-72x72.png',
        tag: 'reminder-notification',
        requireInteraction: false,
        silent: false,
        data: {
          sent,
          skipped,
          failed,
          total,
          timestamp: Date.now()
        }
      };

      self.registration.showNotification(title, options);
    }
  }
});

// Handle push notifications (v1.11.41)
self.addEventListener('push', event => {
  console.log('[Service Worker] Push received');

  let data = {
    title: 'ShowBox Notification',
    body: 'You have a new notification',
    icon: '/assets/icons/icon-192x192.png',
    badge: '/assets/icons/icon-72x72.png',
    data: {}
  };

  if (event.data) {
    try {
      data = { ...data, ...event.data.json() };
    } catch (e) {
      data.body = event.data.text();
    }
  }

  const options = {
    body: data.body,
    icon: data.icon || '/assets/icons/icon-192x192.png',
    badge: data.badge || '/assets/icons/icon-72x72.png',
    tag: data.type || 'showbox-notification',
    requireInteraction: true,
    vibrate: [200, 100, 200],
    data: data.data || {},
    actions: [
      { action: 'view', title: 'View' },
      { action: 'dismiss', title: 'Dismiss' }
    ]
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Handle notification click
self.addEventListener('notificationclick', event => {
  event.notification.close();

  const action = event.action;
  const data = event.notification.data || {};

  if (action === 'dismiss') {
    return;
  }

  // Determine URL to open
  let urlToOpen = '/dashboard.php';
  if (data.url) {
    urlToOpen = data.url;
  } else if (data.type === 'new_account' || data.type === 'renewal') {
    urlToOpen = '/dashboard.php?tab=accounts';
  }

  // Open or focus the dashboard
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then(clientList => {
        // If dashboard is already open, focus it and navigate
        for (let client of clientList) {
          if (client.url.includes('dashboard') && 'focus' in client) {
            client.focus();
            if (client.navigate && urlToOpen !== client.url) {
              return client.navigate(urlToOpen);
            }
            return;
          }
        }
        // Otherwise, open a new window
        if (clients.openWindow) {
          return clients.openWindow(urlToOpen);
        }
      })
  );
});
