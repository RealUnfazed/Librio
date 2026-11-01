// Librio service worker. It deliberately caches NOTHING (books and pages are private);
// it only exists so Chrome offers "Install app".
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {});
