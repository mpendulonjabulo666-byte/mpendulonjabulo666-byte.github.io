// Minimal service worker: exists mainly so Chrome/Android treat NutriTale as
// installable (a fetch handler + a linked manifest are the install criteria).
// It only caches static, versioned assets (CSS/JS/images) — never the PHP
// pages themselves, since those are per-user/session content that must
// always come from the network fresh.
const CACHE_NAME = 'nutritale-static-v1';

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (names) {
            return Promise.all(
                names.filter(function (name) { return name !== CACHE_NAME; })
                    .map(function (name) { return caches.delete(name); })
            );
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (event) {
    const url = new URL(event.request.url);
    const isStaticAsset = event.request.method === 'GET'
        && url.origin === self.location.origin
        && url.pathname.includes('/assets/');

    if (!isStaticAsset) {
        return; // let the browser handle it normally (network, no caching)
    }

    event.respondWith(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.match(event.request).then(function (cached) {
                const fetchPromise = fetch(event.request).then(function (response) {
                    if (response && response.ok) {
                        cache.put(event.request, response.clone());
                    }
                    return response;
                }).catch(function () { return cached; });
                return cached || fetchPromise;
            });
        })
    );
});
