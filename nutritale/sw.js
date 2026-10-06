// Service worker: makes NutriTale installable (Android/desktop "Install app",
// iOS "Add to Home Screen" opens standalone) and keeps it usable offline.
//
// Two caches, two strategies:
// - Static assets (CSS/JS/images/fonts under /assets/) are cache-first,
//   refreshed in the background - safe because they're versioned by URL
//   (?v=), so a deploy changes the URL rather than the cached file.
// - Page navigations are network-first: pages are per-user/session content
//   that must come from the network when possible. Offline, the last copy
//   of that page is shown, or a built-in "you're offline" page.
//
// CACHE_VERSION is bumped whenever this file's caching behavior changes, so
// activate() below tears down every previous version's caches - an old
// service worker's stale content can never outlive an update to this file.
//
// The production host puts every request behind an anti-bot check: without
// its 6-hour "__test" cookie, ANY URL - a page, a stylesheet, this file -
// answers 200 with a small HTML page that sets the cookie and reloads. The
// v2-v6 workers cached any 200, so that page could be stored as a recipe
// page or, worse, as the stylesheet (an unstyled app until the cache
// refreshed). Nothing here is cached unless it is really what was asked for.
const CACHE_VERSION = 'v7';
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
        }).catch(function () {}).then(function () { return self.clients.claim(); })
    );
});

function isHtml(response) {
    return (response.headers.get('content-type') || '').indexOf('text/html') !== -1;
}

// The host's challenge page: tiny, and loads /aes.js to compute the cookie.
function isHostChallenge(html) {
    return html.length < 4000 && html.indexOf('/aes.js') !== -1 && html.indexOf('__test') !== -1;
}

// Shown for a page that was never visited online, when there's no network.
// Built here rather than fetched and cached, so there is nothing for the
// host's challenge to replace, and it follows the device's light/dark mode.
function offlinePage() {
    const html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        + '<meta name="viewport" content="width=device-width, initial-scale=1">'
        + '<meta name="theme-color" content="#2fae66"><title>Offline · NutriTale</title><style>'
        + ':root{color-scheme:light dark;--bg:#f6f9f7;--card:#fff;--ink:#1c2521;--muted:#6b7a72;--btn:#1f7d49;--btn-ink:#fff}'
        + '@media (prefers-color-scheme:dark){:root{--bg:#111613;--card:#1a211d;--ink:#e7ede9;--muted:#93a39a;--btn:#6ee7a5;--btn-ink:#1a211d}}'
        + 'body{margin:0;min-height:100vh;min-height:100dvh;display:grid;place-items:center;background:var(--bg);color:var(--ink);'
        + 'font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;padding:24px;box-sizing:border-box}'
        + 'main{max-width:340px;text-align:center;background:var(--card);border-radius:20px;padding:32px 24px;box-shadow:0 20px 40px -28px rgba(0,0,0,.4)}'
        + 'h1{font:700 22px/1.2 Georgia,"Times New Roman",serif;margin:12px 0 8px}p{margin:0 0 22px;color:var(--muted)}'
        + 'button{font-family:inherit;font-size:15px;font-weight:700;line-height:1;border:0;border-radius:999px;padding:14px 26px;background:var(--btn);color:var(--btn-ink);cursor:pointer}'
        + '</style></head><body><main>'
        + '<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        + '<path d="M1 1l22 22M16.72 11.06A10.94 10.94 0 0 1 19 12.55M5 12.55a10.94 10.94 0 0 1 5.17-2.39M10.71 5.05A16 16 0 0 1 22.58 9M1.42 9a15.91 15.91 0 0 1 4.7-2.88M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>'
        + '<h1>You\u2019re offline</h1><p>This page hasn\u2019t been opened on this device yet, so there\u2019s no saved copy. Check your connection and try again.</p>'
        + '<button type="button" onclick="location.reload()">Try again</button></main></body></html>';
    return new Response(html, { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } });
}

function cachedPageOrOffline(request) {
    return caches.open(PAGE_CACHE)
        .then(function (cache) { return cache.match(request); })
        .catch(function () { return undefined; })
        .then(function (cached) { return cached || offlinePage(); });
}

self.addEventListener('fetch', function (event) {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        // Logging out drops every saved page: they belong to the account that
        // just left, and on a shared phone the next person offline would
        // otherwise be shown them.
        if (/\/logout\.php$/.test(url.pathname)) {
            event.respondWith(
                caches.delete(PAGE_CACHE).catch(function () {}).then(function () { return fetch(request); })
                    .catch(function () { return offlinePage(); })
            );
            return;
        }
        // Network-first: always try to get the current, per-session page.
        event.respondWith(
            fetch(request).then(function (response) {
                if (response && response.ok && response.type === 'basic' && isHtml(response)) {
                    const copy = response.clone();
                    copy.text().then(function (html) {
                        if (isHostChallenge(html)) return;
                        return caches.open(PAGE_CACHE).then(function (cache) {
                            return cache.put(request, new Response(html, {
                                status: copy.status,
                                statusText: copy.statusText,
                                headers: copy.headers,
                            }));
                        });
                    }).catch(function () {});
                }
                return response;
            }).catch(function () {
                return cachedPageOrOffline(request);
            })
        );
        return;
    }

    if (!url.pathname.includes('/assets/')) {
        return; // let the browser handle it normally (network, no caching)
    }

    // The cache is an optimisation, never a dependency. CacheStorage can
    // fail outright - caches.open() rejects with "Unexpected internal
    // error" when the browser profile's storage is unusable (seen for real
    // with a Chrome profile under C:\Windows\Temp; storage pressure, some
    // private-browsing modes and corrupted profiles do the same). A broken
    // cache just means "go to the network", same as having no worker.
    event.respondWith(
        caches.open(STATIC_CACHE).then(function (cache) {
            return cache.match(request).then(function (cached) {
                const fetchPromise = fetch(request).then(function (response) {
                    // A stylesheet, script, image or font URL answered with an
                    // HTML page is the host's challenge (or an error page),
                    // never the file: don't store it, and keep serving a good
                    // copy if there is one.
                    if (response && isHtml(response)) {
                        return cached || response;
                    }
                    if (response && response.ok && response.type === 'basic') {
                        cache.put(request, response.clone()).catch(function () {});
                    }
                    return response;
                });
                if (cached) {
                    // Serve the cached copy now; the fetch above only
                    // refreshes it in the background, so its failure (say,
                    // offline) is no one's problem.
                    fetchPromise.catch(function () {});
                    return cached;
                }
                return fetchPromise;
            });
        }).catch(function () {
            return fetch(request);
        })
    );
});
