// NutriTale UI helpers: page loader, button busy state, skeleton cards,
// toasts and opt-in form feedback. Styles: the "nt-" block in style.css.
// Visual feedback only - every rule (CSRF, validation, permissions) still
// runs on the server. Nothing here holds a key or talks to an API.
(function () {
    'use strict';
    var loader = null, loaderTimer = null, stack = null;

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text != null) n.textContent = text;
        return n;
    }

    function buildLoader() {
        loader = el('div', 'nt-page-loader');
        loader.setAttribute('role', 'status');
        loader.setAttribute('aria-live', 'polite');
        var panel = el('div', 'nt-loader-panel nt-glass');
        var mark = el('div', 'nt-brand-loader');
        mark.setAttribute('aria-hidden', 'true');
        mark.appendChild(el('span', 'nt-brand-loader__leaf nt-brand-loader__leaf--two'));
        mark.appendChild(el('span', 'nt-brand-loader__leaf'));
        mark.appendChild(el('span', 'nt-brand-loader__seed'));
        var track = el('div', 'nt-loader-track');
        track.setAttribute('aria-hidden', 'true');
        track.appendChild(el('span'));
        panel.appendChild(mark);
        panel.appendChild(el('div', 'nt-loader-title', 'NutriTale'));
        panel.appendChild(el('div', 'nt-loader-message'));
        panel.appendChild(track);
        loader.appendChild(panel);
        document.body.appendChild(loader);
    }

    function showPageLoader(message) {
        if (!loader) buildLoader();
        loader.querySelector('.nt-loader-message').textContent = message || 'Loading...';
        loader.classList.add('is-visible');
        // Never leave the screen covered: if the page has not changed after
        // 45 seconds something went wrong, so give the page back.
        clearTimeout(loaderTimer);
        loaderTimer = setTimeout(hidePageLoader, 45000);
    }

    function hidePageLoader() {
        clearTimeout(loaderTimer);
        if (loader) loader.classList.remove('is-visible');
    }

    function setButtonLoading(button, on, text) {
        if (!button) return;
        if (on) {
            if (button.classList.contains('is-nt-loading')) return;
            button.dataset.ntHtml = button.innerHTML;
            button.style.minWidth = button.offsetWidth + 'px';
            button.classList.add('is-nt-loading');
            button.setAttribute('aria-busy', 'true');
            button.textContent = '';
            var spin = el('span', 'nt-button-spinner');
            spin.setAttribute('aria-hidden', 'true');
            button.appendChild(spin);
            button.appendChild(el('span', '', text || button.dataset.loadingText || 'Working...'));
            // aria-disabled, not disabled: a disabled submit button can stop
            // its own form from sending in some browsers.
            button.setAttribute('aria-disabled', 'true');
        } else if (button.classList.contains('is-nt-loading')) {
            button.innerHTML = button.dataset.ntHtml || '';
            button.classList.remove('is-nt-loading');
            button.removeAttribute('aria-busy');
            button.removeAttribute('aria-disabled');
            button.style.minWidth = '';
        }
    }

    function showSkeleton(container, count) {
        if (!container) return function () {};
        container.setAttribute('aria-busy', 'true');
        var made = [];
        for (var i = 0; i < (count || 3); i++) {
            var card = el('div', 'nt-skeleton-card');
            card.setAttribute('aria-hidden', 'true');
            card.appendChild(el('div', 'nt-skeleton nt-skeleton--image'));
            var body = el('div', 'nt-skeleton-card__body');
            body.appendChild(el('div', 'nt-skeleton nt-skeleton--title'));
            body.appendChild(el('div', 'nt-skeleton nt-skeleton--line'));
            body.appendChild(el('div', 'nt-skeleton nt-skeleton--short'));
            card.appendChild(body);
            container.appendChild(card);
            made.push(card);
        }
        return function () {
            made.forEach(function (c) { c.remove(); });
            container.removeAttribute('aria-busy');
        };
    }

    function toast(message, type) {
        type = type === 'error' || type === 'info' ? type : 'success';
        if (!stack) {
            stack = el('div', 'nt-toast-stack');
            document.body.appendChild(stack);
        }
        var t = el('div', 'nt-toast nt-glass nt-toast--' + type);
        // Errors interrupt a screen reader; everything else waits its turn.
        t.setAttribute('role', type === 'error' ? 'alert' : 'status');
        var mark = el('span', 'nt-toast__mark', type === 'error' ? '!' : type === 'info' ? 'i' : '✓');
        mark.setAttribute('aria-hidden', 'true');
        var close = el('button', 'nt-toast__close', '×');
        close.type = 'button';
        close.setAttribute('aria-label', 'Dismiss message');
        t.appendChild(mark);
        t.appendChild(el('span', 'nt-toast__message', message));
        t.appendChild(close);
        stack.appendChild(t);
        requestAnimationFrame(function () { t.classList.add('is-visible'); });
        function remove() {
            t.classList.remove('is-visible');
            setTimeout(function () { t.remove(); }, 220);
        }
        close.addEventListener('click', remove);
        // Errors stay until dismissed; others clear themselves after 6s.
        if (type !== 'error') setTimeout(remove, 6000);
        return remove;
    }

    // Opt-in form feedback: <form data-nt-loading [data-nt-page-loader="true"]
    // [data-nt-loader-message="..."]>. Runs after the browser's own
    // validation, so an invalid form never shows a loader.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.matches || !form.matches('form[data-nt-loading]') || e.defaultPrevented) return;
        var button = e.submitter || form.querySelector('button[type="submit"], button:not([type])');
        setButtonLoading(button, true);
        if (form.dataset.ntPageLoader === 'true') showPageLoader(form.dataset.ntLoaderMessage);
    });

    // Coming back with the Back button restores the page as it was left:
    // clear any loader or busy button that was showing at that moment.
    window.addEventListener('pageshow', function () {
        hidePageLoader();
        document.querySelectorAll('.is-nt-loading').forEach(function (b) { setButtonLoading(b, false); });
    });

    window.addEventListener('offline', function () {
        toast("You're offline. Changes won't save until you're connected again.", 'error');
    });
    window.addEventListener('online', function () {
        toast("You're back online.", 'info');
    });

    window.NutriUI = {
        showPageLoader: showPageLoader,
        hidePageLoader: hidePageLoader,
        setButtonLoading: setButtonLoading,
        showSkeleton: showSkeleton,
        toast: toast
    };
})();
