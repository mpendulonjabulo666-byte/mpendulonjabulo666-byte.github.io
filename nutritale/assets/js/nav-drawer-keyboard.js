// Makes the mobile/tablet nav drawer (includes/nav.php, driven by a
// hidden checkbox + <label> toggles - a pure-CSS pattern with no
// keyboard support of its own) actually operable by keyboard.
//
// A bare <label> is not a native interactive element: clicking it toggles
// its associated checkbox, but it never receives keyboard focus and
// Enter/Space do nothing on it. Before this, a keyboard-only user could
// never open the drawer at all - Tab skipped straight from the toggle
// button to the drawer's own (visually off-screen, but still present)
// links.
(function () {
    var checkbox = document.getElementById('nav-toggle');
    if (!checkbox) return;

    var toggleLabels = document.querySelectorAll('label[for="nav-toggle"].app-nav-toggle, label[for="nav-toggle"].app-nav-close');
    // .app-nav-backdrop is deliberately excluded - it's already
    // aria-hidden (a decorative click-to-dismiss layer), and the real
    // toggle/close controls plus Escape already cover keyboard dismissal
    // without adding a redundant, unlabelled extra tab stop.

    function syncExpanded() {
        toggleLabels.forEach(function (label) {
            if (label.classList.contains('app-nav-toggle')) {
                label.setAttribute('aria-expanded', String(checkbox.checked));
            }
        });
    }

    toggleLabels.forEach(function (label) {
        label.setAttribute('tabindex', '0');
        label.setAttribute('role', 'button');
        label.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                checkbox.checked = !checkbox.checked;
                syncExpanded();
            }
        });
    });

    checkbox.addEventListener('change', syncExpanded);
    syncExpanded();

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && checkbox.checked) {
            checkbox.checked = false;
            syncExpanded();
        }
    });
})();
