// ======================================================================
// SERVICE WORKER - APP DO MOTORISTA (OFFLINE FIRST)
// ======================================================================
// Versão nova = cache renovado automaticamente no próximo carregamento.
// SEMPRE incremente este número ao publicar mudanças em HTML/CSS/JS.
// ======================================================================
const CACHE_NAME = 'frota-motorista-v51';
const APP_SHELL = [
    new URL('assets/motorista-offline.css', self.registration.scope).pathname,
    new URL('assets/motorista-offline.js', self.registration.scope).pathname,
    new URL('assets/frota.js', self.registration.scope).pathname,
    new URL('manifest-motorista.json', self.registration.scope).pathname,
    new URL('assets/icons/android-chrome-192x192.png', self.registration.scope).pathname,
    new URL('assets/icons/android-chrome-512x512.png', self.registration.scope).pathname,
    new URL('../../assets/js/config.js', self.registration.scope).pathname
];
const EXTERNAL_ASSETS = [
    'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css',
    'https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js',
    'https://cdn.jsdelivr.net/npm/sweetalert2@11'
];

// ======================================================================
// INSTALL: baixa todos os assets do app shell e ativa imediatamente
// ======================================================================
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => Promise.allSettled(
                [...APP_SHELL, ...EXTERNAL_ASSETS].map((asset) => cache.add(asset))
            ))
            .then(() => self.skipWaiting())
    );
});

// ======================================================================
// ACTIVATE: apaga caches antigos e assume o controle das abas abertas
// ======================================================================
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key.startsWith('frota-motorista-') && key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

// ======================================================================
// FETCH: estratégia por tipo de recurso
// ======================================================================
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);
    const path = url.pathname;

    // ─── NUNCA cachear requisições de API (sempre rede) ───
    if (path.includes('/v1/') || path.includes('/v2/') || path.includes('/API/v1/')) {
        return;
    }

    // ─── Navegação (HTML): Network-first com fallback para cache ───
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request)
                .then(async (response) => {
                    const responsePath = response.url ? new URL(response.url).pathname : '';
                    if (
                        path.endsWith('/motorista-offline.php')
                        && response.status === 200
                        && responsePath === path
                        && response.headers.get('content-type')?.includes('text/html')
                    ) {
                        try {
                            const cache = await caches.open(CACHE_NAME);
                            await cache.put(event.request, response.clone());
                        } catch (error) {
                            console.error('[Frota offline] Não foi possível guardar a página offline:', error);
                        }
                    }
                    return response;
                })
                .catch(async () => {
                    if (path.endsWith('/motorista-offline.php')) {
                        const cached = await caches.match(event.request);
                        if (cached) return cached;
                    }
                    return new Response(
                        '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Offline</title><body><h1>Sem conexão</h1><p>Esta tela ainda não está disponível offline. Reconecte-se para abrir novamente o app.</p></body></html>',
                        { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } }
                    );
                })
        );
        return;
    }

    // ─── Assets do app (CSS/JS/manifest/ícones/config): Cache-first ───
    const isMotoristaAsset =
        path.endsWith('/assets/motorista-offline.css') ||
        path.endsWith('/assets/motorista-offline.js') ||
        path.endsWith('/assets/frota.js') ||
        path.endsWith('/manifest-motorista.json') ||
        path.includes('/assets/js/config.js') ||     // ← ADICIONADO (includes porque tem ?v=)
        path.includes('/assets/icons/') ||
        url.hostname === 'unpkg.com' ||
        url.hostname === 'cdn.jsdelivr.net';

    if (!isMotoristaAsset) return;

    event.respondWith(
caches.match(event.request, { ignoreSearch: true }).then((cached) => {
            if (cached) return cached;
            return fetch(event.request).then((response) => {
                if (response && response.status === 200) {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
                }
                return response;
            }).catch(() => {
                return new Response('', {
                    status: 503,
                    statusText: 'Offline asset unavailable',
                    headers: { 'Cache-Control': 'no-store' }
                });
            });
        })
    );
});

// ======================================================================
// SYNC: dispara sincronização da fila offline quando voltar a conexão
// ======================================================================
self.addEventListener('sync', (event) => {
    if (event.tag === 'frota-offline-sync') {
        event.waitUntil(
            self.clients.matchAll({ type: 'window', includeUncontrolled: true })
                .then((clients) => {
                    clients.forEach((client) => {
                        client.postMessage({ type: 'frota-offline-sync' });
                    });
                })
        );
    }
});