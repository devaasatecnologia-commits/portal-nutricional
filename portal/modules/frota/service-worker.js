const CACHE_NAME = 'frota-motorista-v1';
const APP_SHELL = [
    '/portal/modules/frota/motorista-offline.php',
    '/portal/modules/frota/assets/motorista-offline.css',
    '/portal/modules/frota/assets/motorista-offline.js'
];
self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;
    event.respondWith(fetch(event.request).catch(() => caches.match(event.request).then((cached) => cached || caches.match('/portal/modules/frota/motorista-offline.php'))));
});
