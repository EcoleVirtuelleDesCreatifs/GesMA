/* GesMA — service worker : met en cache l'application pour un usage hors connexion. */
const VERSION = 'gesma-__VERSION__';
const SHELL = __SHELL__;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(VERSION)
            .then((cache) => cache.addAll(SHELL.map((url) => new Request(url, { cache: 'reload' }))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Pages : réseau d'abord ; sans connexion, on bascule sur le mode hors connexion.
    // Les pages authentifiées ne sont jamais mises en cache (données protégées).
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/hors-ligne', { ignoreSearch: true }))
        );
        return;
    }

    // Données JSON (synchronisation, recherche) : toujours le réseau.
    if (url.pathname.startsWith('/sync') || url.pathname.startsWith('/api') || url.pathname.startsWith('/clients/recherche')) return;

    // Fichiers statiques de l'application : cache d'abord, mise à jour en arrière-plan.
    const path = url.pathname;
    if (SHELL.includes(path) || path.startsWith('/css/') || path.startsWith('/js/') || path.startsWith('/images/')) {
        event.respondWith(
            caches.open(VERSION).then((cache) =>
                cache.match(request, { ignoreSearch: true }).then((cached) => {
                    const network = fetch(request).then((response) => {
                        if (response.ok) cache.put(path, response.clone());
                        return response;
                    }).catch(() => cached);
                    return cached || network;
                })
            )
        );
    }
});
