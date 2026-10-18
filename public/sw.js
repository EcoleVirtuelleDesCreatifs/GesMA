/*
 * GesMA — service worker.
 * - Met en cache les fichiers de l'application (styles, scripts, icônes).
 * - Hors connexion, toute page demandée affiche la saisie hors connexion.
 * Les données métier ne sont pas mises en cache ici : elles sont chiffrées dans IndexedDB.
 */
const VERSION = 'gesma-v3';
const ASSETS_CACHE = 'gesma-assets-' + VERSION;
const PAGES_CACHE = 'gesma-pages';
// Racine de l'application = portée du service worker (ex. "/" ou "/gesma/")
const BASE = new URL('./', self.registration.scope).pathname;
const OFFLINE_URL = BASE + 'hors-ligne';
const ASSETS = [
    'css/app.css',
    'js/app.js',
    'js/offline-store.js',
    'js/offline-page.js',
    'icons/icon.svg',
    'icons/icon-192.png',
    'icons/icon-512.png',
    'manifest.webmanifest',
].map((p) => BASE + p);

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(ASSETS_CACHE).then((c) => c.addAll(ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((k) => k.startsWith('gesma-assets-') && k !== ASSETS_CACHE).map((k) => caches.delete(k))
        )).then(() => self.clients.claim())
    );
});

async function cacheOfflinePage(url) {
    try {
        const res = await fetch(url || OFFLINE_URL, { credentials: 'same-origin' });
        if (res.ok && !res.redirected) {
            const cache = await caches.open(PAGES_CACHE);
            await cache.put(OFFLINE_URL, res);
        }
    } catch (e) { /* hors connexion */ }
}

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'cache-offline-page') {
        event.waitUntil(cacheOfflinePage(event.data.url));
    }
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);
    if (req.method !== 'GET' || url.origin !== self.location.origin) return;

    // Pages : réseau d'abord, page hors connexion en secours
    if (req.mode === 'navigate') {
        event.respondWith((async () => {
            try {
                const res = await fetch(req);
                if (url.pathname === OFFLINE_URL && res.ok && !res.redirected) {
                    const cache = await caches.open(PAGES_CACHE);
                    cache.put(OFFLINE_URL, res.clone());
                }
                return res;
            } catch (e) {
                const cached = await caches.match(OFFLINE_URL, { cacheName: PAGES_CACHE });
                if (cached) return cached;
                return new Response(
                    '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width">' +
                    '<body style="font-family:sans-serif;padding:2rem;text-align:center"><h1>GesMA</h1>' +
                    '<p>Pas de connexion Internet.</p><p>Le mode hors connexion sera disponible après une première connexion sur cet appareil.</p></body>',
                    { headers: { 'Content-Type': 'text/html; charset=utf-8' }, status: 503 }
                );
            }
        })());
        return;
    }

    // Fichiers statiques : cache d'abord, mise à jour en arrière-plan
    if (ASSETS.includes(url.pathname)) {
        event.respondWith((async () => {
            const cache = await caches.open(ASSETS_CACHE);
            const cached = await cache.match(url.pathname);
            const network = fetch(req).then((res) => { if (res.ok) cache.put(url.pathname, res.clone()); return res; }).catch(() => null);
            return cached || (await network) || new Response('', { status: 504 });
        })());
    }
});
