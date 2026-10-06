// First-visit guided tour (steps come from includes/app_tour.php). Dims the
// app, cuts a spotlight around the real tab (phone) or sidebar link (desktop)
// being explained, and shows a card beside it: Back / Next / Skip, arrow
// keys and Esc, focus kept inside the card. Shown once per account - it
// tells tour_done.php as soon as it starts, so closing the app halfway
// never brings it back uninvited. Replays (?tour=1) aren't recorded.
(function () {
    var dataEl = document.getElementById('app-tour-data');
    if (!dataEl) return;
    var config;
    try { config = JSON.parse(dataEl.textContent); } catch (e) { return; }

    var phoneQuery = window.matchMedia('(max-width: 900px)'); // the tab bar / sidebar breakpoint
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    var steps = [];
    var index = 0;
    var root, spot, card, countEl, titleEl, textEl, dotsEl, backBtn, nextBtn, skipBtn;
    var returnFocus = null;
    var frame = 0;

    function isPhone() { return phoneQuery.matches; }

    // Steps for the current layout (a phone skips desktop-only ones), with
    // the ones in between the welcome and the goodbye put in the order their
    // targets appear on screen - left to right along the phone tab bar, top
    // to bottom down the desktop sidebar - so the spotlight never jumps back.
    function stepsForLayout() {
        var mode = isPhone() ? 'phone' : 'desktop';
        var list = config.steps.filter(function (s) { return !s.only || s.only === mode; });
        if (list.length < 3) return list;
        var middle = list.slice(1, -1).map(function (step, i) { return { step: step, el: visibleTarget(step), i: i }; });
        middle.sort(function (a, b) {
            if (!a.el || !b.el) return a.el ? -1 : (b.el ? 1 : a.i - b.i);
            if (a.el === b.el) return a.i - b.i;
            return a.el.compareDocumentPosition(b.el) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1;
        });
        return [list[0]].concat(middle.map(function (m) { return m.step; }), [list[list.length - 1]]);
    }

    function visibleTarget(step) {
        var selector = isPhone() ? step.phone : step.desktop;
        var el = selector ? document.querySelector(selector) : null;
        if (!el) return null;
        var r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0 ? el : null;
    }

    function build() {
        root = document.createElement('div');
        root.className = 'tour';
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.setAttribute('aria-labelledby', 'tour-title');
        root.setAttribute('aria-describedby', 'tour-text');
        root.innerHTML =
            '<div class="tour-spot is-centered" aria-hidden="true"></div>' +
            '<div class="tour-card">' +
                '<p class="tour-count"></p>' +
                '<h2 class="tour-title" id="tour-title" tabindex="-1"></h2>' +
                '<p class="tour-text" id="tour-text"></p>' +
                '<div class="tour-dots" aria-hidden="true"></div>' +
                '<div class="tour-actions">' +
                    '<button type="button" class="tour-skip">Skip tour</button>' +
                    '<span class="tour-nav">' +
                        '<button type="button" class="tour-back">Back</button>' +
                        '<button type="button" class="tour-next">Next</button>' +
                    '</span>' +
                '</div>' +
            '</div>';
        spot = root.querySelector('.tour-spot');
        card = root.querySelector('.tour-card');
        countEl = root.querySelector('.tour-count');
        titleEl = root.querySelector('.tour-title');
        textEl = root.querySelector('.tour-text');
        dotsEl = root.querySelector('.tour-dots');
        backBtn = root.querySelector('.tour-back');
        nextBtn = root.querySelector('.tour-next');
        skipBtn = root.querySelector('.tour-skip');

        backBtn.addEventListener('click', function () { go(index - 1); });
        nextBtn.addEventListener('click', function () { if (index < steps.length - 1) { go(index + 1); } else { finish(); } });
        skipBtn.addEventListener('click', finish);
        root.addEventListener('keydown', onKeydown);
        document.body.appendChild(root);
        document.documentElement.classList.add('tour-open');
    }

    function render() {
        var step = steps[index];
        var phone = isPhone();
        var last = index === steps.length - 1;
        countEl.textContent = (index + 1) + ' of ' + steps.length;
        titleEl.textContent = (!phone && step.titleDesktop) || step.title;
        textEl.textContent = (!phone && step.textDesktop) || step.text;
        nextBtn.textContent = step.next || (last ? 'Done' : 'Next');
        backBtn.hidden = index === 0;
        skipBtn.hidden = last;
        dotsEl.innerHTML = steps.map(function (s, i) {
            return '<span class="tour-dot' + (i === index ? ' is-on' : '') + '"></span>';
        }).join('');
        place();
        titleEl.focus({ preventScroll: true });
    }

    // Spotlight around the target, card beside it: to the right of a
    // sidebar link, above a tab bar item, otherwise below or above
    // whichever has room. No target: the card sits centred.
    function place() {
        var step = steps[index];
        var target = visibleTarget(step);
        var vw = document.documentElement.clientWidth;
        var vh = window.innerHeight;
        var gutter = 16;
        var cardW = card.offsetWidth;
        var cardH = card.offsetHeight;
        var top, left;

        if (!target) {
            spot.classList.add('is-centered');
            spot.style.cssText = '';
            top = (vh - cardH) / 2;
            left = (vw - cardW) / 2;
        } else {
            var r = target.getBoundingClientRect();
            var pad = 6;
            var radius = parseFloat(getComputedStyle(target).borderTopLeftRadius) || 12;
            spot.classList.remove('is-centered');
            spot.style.top = (r.top - pad) + 'px';
            spot.style.left = (r.left - pad) + 'px';
            spot.style.width = (r.width + pad * 2) + 'px';
            spot.style.height = (r.height + pad * 2) + 'px';
            spot.style.borderRadius = Math.min(radius + pad, (r.height + pad * 2) / 2) + 'px';

            var roomRight = vw - r.right;
            if (r.left < vw / 2 && roomRight >= cardW + 32) {
                left = r.right + 20;
                top = r.top + r.height / 2 - cardH / 2;
            } else if (vh - r.bottom >= cardH + 32) {
                top = r.bottom + 18;
                left = r.left + r.width / 2 - cardW / 2;
            } else {
                top = r.top - cardH - 18;
                left = r.left + r.width / 2 - cardW / 2;
            }
        }
        card.style.left = Math.round(Math.max(gutter, Math.min(left, vw - cardW - gutter))) + 'px';
        card.style.top = Math.round(Math.max(gutter, Math.min(top, vh - cardH - gutter))) + 'px';
    }

    function go(to) {
        if (to < 0 || to >= steps.length) return;
        index = to;
        if (reduceMotion.matches || !card.animate) { render(); return; }
        card.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 110, easing: 'ease-in' }).onfinish = function () {
            render();
            card.animate([{ opacity: 0, transform: 'translateY(6px)' }, { opacity: 1, transform: 'none' }], { duration: 220, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' });
        };
    }

    function onKeydown(event) {
        if (event.key === 'Escape') { event.preventDefault(); finish(); return; }
        if (event.key === 'ArrowRight') { event.preventDefault(); if (index < steps.length - 1) go(index + 1); return; }
        if (event.key === 'ArrowLeft') { event.preventDefault(); go(index - 1); return; }
        if (event.key !== 'Tab') return;
        // Keep focus inside the card while the tour is open.
        var focusable = Array.prototype.filter.call(card.querySelectorAll('button'), function (b) { return !b.hidden; });
        if (!focusable.length) return;
        var first = focusable[0], lastBtn = focusable[focusable.length - 1];
        if (event.shiftKey && (document.activeElement === first || document.activeElement === titleEl)) { event.preventDefault(); lastBtn.focus(); }
        else if (!event.shiftKey && document.activeElement === lastBtn) { event.preventDefault(); first.focus(); }
    }

    function onResize() {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(function () {
            // Rotating a tablet can cross the phone/desktop line, which
            // changes which steps apply - keep the same step if it still does.
            var current = steps[index];
            steps = stepsForLayout();
            var kept = steps.indexOf(current);
            index = kept === -1 ? Math.min(index, steps.length - 1) : kept;
            render();
        });
    }

    function record() {
        if (!config.record) return;
        var body = new FormData();
        body.append('csrf_token', config.csrf);
        fetch('tour_done.php', { method: 'POST', body: body, credentials: 'same-origin', keepalive: true }).catch(function () {});
    }

    function finish() {
        window.removeEventListener('resize', onResize);
        document.documentElement.classList.remove('tour-open');
        // The app is usable the moment the tour is closed; the fade is only
        // for looks, and the timer removes it even if a busy device never
        // reports the animation finished.
        root.style.pointerEvents = 'none';
        var remove = function () { if (root && root.parentNode) root.parentNode.removeChild(root); };
        if (reduceMotion.matches || !root.animate) { remove(); } else {
            root.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 200, easing: 'ease-in', fill: 'forwards' }).onfinish = remove;
            setTimeout(remove, 400);
        }
        // A reload shouldn't replay it: drop ?tour=1 from the address bar.
        if (/[?&]tour=/.test(location.search) && history.replaceState) {
            var url = new URL(location.href);
            url.searchParams.delete('tour');
            history.replaceState(history.state, '', url.pathname + url.search + url.hash);
        }
        if (returnFocus && returnFocus.focus) returnFocus.focus({ preventScroll: true });
    }

    function start() {
        steps = stepsForLayout();
        if (!steps.length) return;
        returnFocus = document.activeElement;
        build();
        render();
        record();
        window.addEventListener('resize', onResize);
        if (!reduceMotion.matches && root.animate) {
            root.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 260, easing: 'ease-out' });
        }
    }

    // A beat after the page appears (and after a page transition has
    // finished playing), so the tour opens over a settled screen.
    function startSoon() { setTimeout(start, 450); }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startSoon);
    } else {
        startSoon();
    }
})();
