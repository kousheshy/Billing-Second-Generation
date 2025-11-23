const CACHE_NAME = 'showbox-billing-v1.10.1';
const urlsToCache = [
  '/dashboard.html',
  '/index.html',
  '/dashboard.css',
  '/dashboard.js',
  '/sms-functions.js',
  '/manifest.json',
  '/BYekan+.ttf',
  '/icons/icon-192x192.png',
  '/icons/icon-512x512.png'
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

  // Skip caching for API requests (PHP files)
  if (url.pathname.endsWith('.php')) {
    event.respondWith(
      fetch(request)
        .catch(() => {
          return new Response(
            JSON.stringify({ error: 1, message: 'Offline - Cannot reach server' }),
            { headers: { 'Content-Type': 'application/json' } }
          );
        })
    );
    return;
  }

  // For static resources, use cache-first strategy
  event.respondWith(
    caches.match(request)
      .then(response => {
        if (response) {
          console.log('[Service Worker] Serving from cache:', request.url);
          return response;
        }

        console.log('[Service Worker] Fetching from network:', request.url);
        return fetch(request).then(response => {
          // Don't cache if not a valid response
          if (!response || response.status !== 200 || response.type !== 'basic') {
            return response;
          }

          // Clone the response
          const responseToCache = response.clone();

          caches.open(CACHE_NAME).then(cache => {
            cache.put(request, responseToCache);
          });

          return response;
        });
      })
      .catch(() => {
        // Return offline page for HTML requests
        if (request.headers.get('accept').includes('text/html')) {
          return caches.match('/index.html');
        }
      })
  );
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
        icon: '/icons/icon-192x192.png',
        badge: '/icons/icon-72x72.png',
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

// Handle notification click
self.addEventListener('notificationclick', event => {
  event.notification.close();

  // Open or focus the dashboard
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then(clientList => {
        // If dashboard is already open, focus it
        for (let client of clientList) {
          if (client.url.includes('dashboard.html') && 'focus' in client) {
            return client.focus();
          }
        }
        // Otherwise, open a new window
        if (clients.openWindow) {
          return clients.openWindow('/dashboard.html');
        }
      })
  );
});
