// Recipe folders (recipe.php): Ingredients, Method and Nutrition are three
// folder cards over one sheet. Opening a folder puts its contents in the
// sheet and closes the other two. The markup is plain links to #ingredients,
// #method and #nutrition, so without this file (and in print) the cards
// simply jump to three stacked sections - this only adds the tab behaviour.
(function () {
    var root = document.querySelector('[data-folders]');
    if (!root) return;
    var rail = root.querySelector('.folder-rail');
    var sheet = root.querySelector('.folder-sheet');
    var inner = root.querySelector('.folder-sheet-inner');
    if (!rail || !sheet || !inner) return;
    var tabs = Array.prototype.slice.call(rail.querySelectorAll('.folder-card'));
    var panels = tabs.map(function (tab) { return document.getElementById(tab.hash.slice(1)); });
    if (!tabs.length || panels.indexOf(null) !== -1) return;

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    var ease = 'cubic-bezier(0.22, 1, 0.36, 1)';
    var current = -1;
    var heightAnim = null;

    rail.setAttribute('role', 'tablist');
    rail.setAttribute('aria-label', 'Recipe sections');
    tabs.forEach(function (tab, i) {
        tab.setAttribute('role', 'tab');
        tab.setAttribute('aria-controls', panels[i].id);
        panels[i].setAttribute('role', 'tabpanel');
        panels[i].setAttribute('aria-labelledby', tab.id);
        // No tabindex on the panel: Chrome focuses a focusable #fragment
        // target on load, which ringed the whole sheet when opening
        // recipe.php#nutrition. The contents are text (the scaler's buttons
        // stay reachable), and aria-controls ties each tab to its panel.
    });
    root.classList.add('is-enhanced');

    function select(index, focusTab) {
        if (index === current) return;
        var animate = current !== -1 && !reduceMotion.matches && typeof inner.animate === 'function';
        // Measured mid-animation if a previous switch is still resizing, so
        // a quick second tap carries on from where the sheet is right now.
        var fromHeight = inner.offsetHeight;
        if (heightAnim) heightAnim.cancel();

        tabs.forEach(function (tab, i) {
            var on = i === index;
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
            tab.tabIndex = on ? 0 : -1;
            panels[i].hidden = !on;
        });
        // The sheet takes the open folder's colours and moves its pointer
        // under it; the CSS transitions both.
        sheet.style.setProperty('--folder-a', tabs[index].style.getPropertyValue('--folder-a'));
        sheet.style.setProperty('--folder-b', tabs[index].style.getPropertyValue('--folder-b'));
        sheet.style.setProperty('--folder-index', String(index));
        current = index;
        if (focusTab) tabs[index].focus();
        if (!animate) return;

        // The sheet grows or shrinks to fit the new contents instead of
        // snapping, and the contents rise in just behind it.
        var toHeight = inner.offsetHeight;
        if (fromHeight !== toHeight) {
            var anim = inner.animate(
                [{ height: fromHeight + 'px' }, { height: toHeight + 'px' }],
                { duration: 420, easing: ease }
            );
            heightAnim = anim;
            inner.classList.add('is-resizing');
            anim.onfinish = anim.oncancel = function () {
                if (heightAnim !== anim) return;
                heightAnim = null;
                inner.classList.remove('is-resizing');
            };
        }
        panels[index].animate(
            [{ opacity: 0, transform: 'translateY(10px)' }, { opacity: 1, transform: 'none' }],
            { duration: 340, delay: 70, easing: ease, fill: 'backwards' }
        );
    }

    // Kept in the address bar (without adding history entries) so a reload
    // reopens the same folder. recipe-share.js strips it from shared links.
    function rememberInUrl() {
        if (window.history && history.replaceState) {
            history.replaceState(history.state, '', '#' + panels[current].id);
        }
    }

    function indexForHash(hash) {
        for (var i = 0; i < panels.length; i++) {
            if ('#' + panels[i].id === hash) return i;
        }
        return -1;
    }

    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function (event) {
            event.preventDefault();
            select(i, false);
            rememberInUrl();
        });
    });

    // Arrow keys move between folders and open them (the WAI-ARIA tabs
    // pattern with automatic activation); Home/End jump to either end.
    rail.addEventListener('keydown', function (event) {
        var i = tabs.indexOf(document.activeElement);
        if (i === -1) return;
        var next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[event.key];
        if (next === undefined) return;
        event.preventDefault();
        select((next + tabs.length) % tabs.length, true);
        rememberInUrl();
    });

    // Any other link on the page to #method etc. opens that folder too.
    window.addEventListener('hashchange', function () {
        var i = indexForHash(location.hash);
        if (i !== -1) select(i, false);
    });

    var initial = indexForHash(location.hash);
    select(initial === -1 ? 0 : initial, false);
})();
