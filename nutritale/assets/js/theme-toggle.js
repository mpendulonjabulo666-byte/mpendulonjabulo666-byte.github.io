document.addEventListener('DOMContentLoaded', function () {
    var btns = document.querySelectorAll('.theme-toggle-btn');
    if (!btns.length) return;

    btns.forEach(function (btn) {
    btn.addEventListener('click', function () {
        var current = document.documentElement.getAttribute('data-theme') || 'light';
        var next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        try {
            localStorage.setItem('nutritale-theme', next);
        } catch (e) {
            // localStorage unavailable - theme choice just won't persist.
        }
    });
    });
});

// Lets the browser offer "Add to Home Screen" / "Install app" on Android and
// desktop, and makes the app open standalone (no browser chrome) once
// installed on any platform, iOS included.
//
// Getting a NEW version to phones that already have the app: the browser
// re-checks sw.js on navigations, but two things on the production host can
// make that check fail and leave the old worker in charge. The host sends
// sw.js with a 30-day cache header (updateViaCache 'none' = never answer the
// check from the HTTP cache), and an expired anti-bot cookie makes sw.js
// itself come back as an HTML challenge page (see sw.js), which the browser
// rejects. By the time this load event fires the page has loaded, so the
// cookie is valid - update() re-checks right then.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('sw.js', { updateViaCache: 'none' }).then(function (registration) {
            if (registration.update) registration.update().catch(function () {});
        }).catch(function () {
            // Offline shell just won't be cached - the site still works online.
        });
    });
}

// Faster page changes.
//
// 1. Start loading before the tap finishes (Chrome, Edge, Android -
//    Speculation Rules; Safari ignores them and navigates as usual). Tab bar,
//    sidebar and Back links prefetch when a finger lands or the mouse rests
//    on them for a moment; every other app link when it is pressed - so a
//    prefetch is nearly always followed by the visit, and recipe view counts
//    stay honest. Prefetch only fetches the page; nothing runs until you
//    actually arrive. Never for links whose GET does something (log out,
//    payment hand-offs and returns, downloads, toggle/delete links, OAuth).
(function () {
    if (!window.HTMLScriptElement || !HTMLScriptElement.supports || !HTMLScriptElement.supports('speculationrules')) return;
    var appLink = "a[href]:not([href^='http']):not([href^='//']):not([href^='#']):not([href^='mailto:']):not([href^='tel:'])";
    var skip = "a[href*='logout'], a[href*='oauth_'], a[href*='checkout'], a[href*='_return'], a[href*='_cancel'],"
        + " a[href*='_export'], a[href*='toggle'], a[href*='delete'], a[href*='payfast'], a[href*='setup.php'],"
        + " a[download], a[target='_blank'], a[data-no-prefetch]";
    var rules = {
        prefetch: [
            { source: 'document', eagerness: 'moderate', where: { and: [
                { selector_matches: '.app-tabbar ' + appLink + ', .app-nav-links ' + appLink + ', ' + appLink + '.btn-back' },
                { not: { selector_matches: skip } }
            ] } },
            { source: 'document', eagerness: 'conservative', where: { and: [
                { selector_matches: appLink },
                { not: { selector_matches: skip } }
            ] } }
        ]
    };
    var script = document.createElement('script');
    script.type = 'speculationrules';
    script.textContent = JSON.stringify(rules);
    document.head.appendChild(script);
})();

// 2. A tap registers at once: html.is-navigating shows a slim progress bar
//    (style.css) from the moment a same-site link is tapped or a form is
//    sent until the next page replaces this one - an installed app has no
//    browser progress bar, so before this the old page just sat frozen.
(function () {
    var root = document.documentElement;
    var timer = null;
    function done() {
        root.classList.remove('is-navigating');
        clearTimeout(timer);
    }
    function navigating() {
        root.classList.add('is-navigating');
        clearTimeout(timer);
        timer = setTimeout(done, 10000); // never left running on a stalled request
    }
    // Document-level listeners run after the element's own, so a click or
    // submit that a script or a confirm() cancelled is already marked
    // defaultPrevented here and is ignored.
    document.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        var link = event.target.closest ? event.target.closest('a[href]') : null;
        if (!link || (link.target && link.target !== '_self') || link.hasAttribute('download')) return;
        var url = new URL(link.href, location.href);
        if (url.origin !== location.origin) return;
        if (url.pathname === location.pathname && url.search === location.search) return; // same page / #anchor
        if (/_export\.php$/.test(url.pathname)) return; // downloads leave this page in place
        navigating();
    });
    document.addEventListener('submit', function (event) {
        if (event.defaultPrevented || (event.target.target && event.target.target !== '_self')) return;
        navigating();
    });
    // Coming back via the back button restores this page exactly as it was
    // left (back/forward cache) - bar included, unless cleared here.
    window.addEventListener('pageshow', done);
    // iOS Safari only applies :active (the pressed look on tabs and buttons)
    // when the page has a touchstart listener; an empty passive one is enough.
    document.addEventListener('touchstart', function () {}, { passive: true });
})();
