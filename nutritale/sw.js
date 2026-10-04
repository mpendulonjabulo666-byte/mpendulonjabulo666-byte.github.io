// Minimal service worker: exists mainly so Chrome/Android treat NutriTale as
// installable (a fetch handler + a linked manifest are the install criteria).
//
// Two caches, two strategies:
// - Static assets (CSS/JS/images under /assets/) are cache-first: fast, and
//   safe to serve stale-then-revalidate since they're versioned by URL.
// - Page navigations are network-first: the page is always per-user/session
//   content that must come from the network when possible, but falling back
//   to the last cached copy on a network failure gives a usable offline
//   experience instead of the browser's default offline error page.
//
// CACHE_VERSION is bumped whenever this file's caching behavior changes, so
// activate() below tears down every previous version's caches — an old
// service worker's stale content can never outlive an update to this file.
const CACHE_VERSION = 'v3';
const STATIC_CACHE = 'nutritale-static-' + CACHE_VERSION;
const PAGE_CACHE = 'nutritale-pages-' + CACHE_VERSION;
const CURRENT_CACHES = [STATIC_CACHE, PAGE_CACHE];

self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (names) {
            return Promise.all(
                names.filter(function (name) { return CURRENT_CACHES.indexOf(name) === -1; })
                    .map(function (name) { return caches.delete(name); })
            );
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (event) {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        // Network-first: always try to get the current, per-session page.
        // Only fall back to whatever was last cached if the network is down.
        event.respondWith(
            fetch(request).then(function (response) {
                if (response && response.ok) {
                    const copy = response.clone();
                    caches.open(PAGE_CACHE).then(function (cache) { cache.put(request, copy); });
                }
                return response;
            }).catch(function () {
                return caches.open(PAGE_CACHE).then(function (cache) { return cache.match(request); });
            })
        );
        return;
    }

    if (!url.pathname.includes('/assets/')) {
        return; // let the browser handle it normally (network, no caching)
    }

    event.respondWith(
        caches.open(STATIC_CACHE).then(function (cache) {
            return cache.match(request).then(function (cached) {
                const fetchPromise = fetch(request).then(function (response) {
                    if (response && response.ok) {
                        cache.put(request, response.clone());
                    }
                    return response;
                }).catch(function () { return cached; });
                return cached || fetchPromise;
            });
        })
    );
});
