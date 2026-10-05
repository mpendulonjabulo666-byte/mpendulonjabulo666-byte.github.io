(function () {
    try {
        var saved = localStorage.getItem('nutritale-theme');
        var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
    } catch (e) {
        // localStorage unavailable (private mode etc) - fall back to light.
    }
})();

// Page-to-page transitions (@view-transition in style.css). Tapping on
// again before one finishes cuts it short, and the browser then rejects
// the transition's promises - with nothing listening, each one surfaced as
// "Uncaught (in promise) AbortError: Transition was skipped" in the
// console. Nothing here depends on them, so they are marked handled. This
// file is the one script every page runs before its first paint, which is
// when the incoming page's transition is handed over.
(function () {
    function settle(event) {
        var vt = event.viewTransition;
        if (!vt) return;
        vt.ready.catch(function () {});
        vt.finished.catch(function () {});
        if (vt.updateCallbackDone) vt.updateCallbackDone.catch(function () {});
    }
    window.addEventListener('pageswap', settle);
    window.addEventListener('pagereveal', settle);
    // After a form POST that redirects (log in, add a pantry item), the old
    // page starts a transition, the browser drops it, and the rejection
    // surfaces in the NEW page - which was never handed a transition
    // object, so there is nothing above to attach to. Only that exact
    // rejection is silenced; anything else still reports as normal.
    window.addEventListener('unhandledrejection', function (event) {
        var reason = event.reason;
        if (reason && reason.name === 'AbortError' && /transition was skipped/i.test(reason.message || '')) {
            event.preventDefault();
        }
    });
})();
