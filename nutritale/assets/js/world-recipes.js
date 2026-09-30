// Loads Premium's "More from around the world" section into pantry.php after
// the page itself has rendered (see external_matches.php).
(function () {
    var box = document.getElementById('world-recipes');
    if (!box) return;
    fetch(box.getAttribute('data-src'), { credentials: 'same-origin' })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.text();
        })
        .then(function (html) {
            box.innerHTML = html; // server-rendered, escaped fragment from our own endpoint
            box.removeAttribute('aria-busy');
        })
        .catch(function () {
            box.querySelector('.world-recipes-status').textContent = 'Worldwide recipes could not be loaded right now. Refresh to try again.';
            box.removeAttribute('aria-busy');
        });
})();
