document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('theme-toggle');
    if (!btn) return;

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

// Lets the browser offer "Add to Home Screen" / "Install app" on Android and
// desktop, and makes the app open standalone (no browser chrome) once
// installed on any platform, iOS included.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('sw.js').catch(function () {
            // Offline shell just won't be cached - the site still works online.
        });
    });
}
