const CACHE_NAME = 'portal-nutricional-v1.0.1';
const ASSETS_TO_CACHE = [
  '/portal/assets/css/style.css',
  '/portal/assets/css/module-base.css',
  '/portal/assets/icons/android-chrome-192x192.png',
  '/portal/assets/icons/android-chrome-512x512.png',
  '/portal/assets/icons/apple-touch-icon.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE).catch((err) => {
        console.warn('[PWA SW] Falha ao pré-cachear alguns assets:', err);
      });
    }).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((name) => {
          if (name !== CACHE_NAME) {
            console.log('[PWA SW] Removendo cache legado:', name);
            return caches.delete(name);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // Ignora requisições de API, POST/PUT/DELETE, extensões e esquemas não http(s)
  if (req.method !== 'GET' || url.pathname.includes('/v1/') || url.protocol.startsWith('chrome-extension')) {
    return;
  }

  // Estratégia Stale-While-Revalidate para CSS, JS e Imagens
  if (
    url.pathname.endsWith('.css') ||
    url.pathname.endsWith('.js') ||
    url.pathname.endsWith('.png') ||
    url.pathname.endsWith('.jpg') ||
    url.pathname.endsWith('.svg') ||
    url.pathname.endsWith('.woff2')
  ) {
    event.respondWith(
      caches.open(CACHE_NAME).then(async (cache) => {
        const cachedResponse = await cache.match(req);
        const fetchPromise = fetch(req).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            cache.put(req, networkResponse.clone());
          }
          return networkResponse;
        }).catch(() => cachedResponse);

        return cachedResponse || fetchPromise;
      })
    );
    return;
  }

  // Para páginas de navegação HTML/PHP: Network First com fallback
  event.respondWith(
    fetch(req).catch(async () => {
      const cached = await caches.match(req);
      if (cached) return cached;
      return new Response(
        '<div style="font-family:sans-serif;text-align:center;padding:50px;"><h2>Sem conexão com a internet</h2><p>Verifique seu sinal para acessar o Portal Nutricional.</p></div>',
        { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
      );
    })
  );
});
